<?php
/**
 * NEOSEN — Installation de la base de données.
 *
 * En ligne de commande (recommandé) :
 *     php install/install.php
 *     php install/install.php --admin-email=moi@domaine.com --admin-password="MotDePasse!" --admin-name="Prénom Nom"
 *     php install/install.php --reset-content   (réinjecte les contenus de démonstration si les tables sont vides)
 *
 * Depuis le navigateur (hébergement sans SSH) :
 *     https://votre-domaine/install/install.php?token=VALEUR_DE_INSTALL_TOKEN
 *     → nécessite INSTALL_TOKEN dans le .env ; à supprimer une fois l'installation terminée.
 *
 * Étapes : création des tables, données initiales (si vides), copie des captures, compte administrateur.
 */

require_once __DIR__ . '/../config/env.php';
loadEnvFile();
require_once __DIR__ . '/../config/app.php';

$cli = PHP_SAPI === 'cli';
if (!$cli) {
    $token = (string) env('INSTALL_TOKEN', '');
    if ($token === '' || !hash_equals($token, (string) ($_GET['token'] ?? ''))) {
        http_response_code(403);
        exit('Installation désactivée. Définissez INSTALL_TOKEN dans le .env et passez ?token=… dans l\'URL.');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

$args = [];
foreach ($argv ?? [] as $arg) {
    if (preg_match('/^--([\w-]+)(?:=(.*))?$/', $arg, $m)) {
        $args[$m[1]] = $m[2] ?? true;
    }
}

function out(string $msg): void {
    echo $msg . PHP_EOL;
    @ob_flush();
    flush();
}

/* ---------------------------------------------------------------
 * 1. Connexion et création de la base si nécessaire
 * --------------------------------------------------------------- */
$host = env('DB_HOST', 'localhost');
$port = env('DB_PORT', '3306');
$name = env('DB_NAME', 'neosen');
$user = env('DB_USER', 'root');
$pass = (string) env('DB_PASS', '');

try {
    $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    try {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . str_replace('`', '', $name) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    } catch (PDOException $e) {
        out('ℹ Impossible de créer la base (droits insuffisants ?) — on suppose qu\'elle existe déjà.');
    }
    $pdo->exec("USE `" . str_replace('`', '', $name) . "`");
} catch (PDOException $e) {
    out('✖ Connexion MySQL impossible : ' . $e->getMessage());
    exit(1);
}
out("✔ Connecté à MySQL ({$host}/{$name})");

/* ---------------------------------------------------------------
 * 2. Schéma
 * --------------------------------------------------------------- */
$schema = file_get_contents(__DIR__ . '/schema.sql');
$schema = preg_replace('/^\s*--.*$/m', '', $schema);
foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
    $pdo->exec($statement);
}
out('✔ Tables créées / vérifiées');

// Le reste du code applicatif utilise db()
require_once __DIR__ . '/../config/database.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/i18n.php';
require_once ROOT_PATH . '/includes/uploads.php';

$seed = require __DIR__ . '/seed.php';
$lang = APP_DEFAULT_LANG;
$count = fn(string $table) => (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();

/* ---------------------------------------------------------------
 * 3. Données initiales (uniquement si les tables sont vides)
 * --------------------------------------------------------------- */
if ($count('services') === 0) {
    $stmt = $pdo->prepare('INSERT INTO services (lang, name, slug, eyebrow, short_description, description, icon, items, cta_label, seo_title, seo_description, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($seed['services'] as $i => $s) {
        $stmt->execute([$lang, $s['name'], $s['slug'], $s['eyebrow'], $s['short_description'], $s['description'], $s['icon'], $s['items'], $s['cta_label'], $s['seo_title'], $s['seo_description'], ($i + 1) * 10]);
    }
    out('✔ Services créés (' . count($seed['services']) . ')');
}

if ($count('project_categories') === 0) {
    $stmt = $pdo->prepare('INSERT INTO project_categories (lang, name, slug, sort_order) VALUES (?,?,?,?)');
    foreach ($seed['categories'] as $i => $c) {
        $stmt->execute([$lang, $c['name'], $c['slug'], ($i + 1) * 10]);
    }
    out('✔ Catégories créées');
}

if ($count('projects') === 0) {
    $cats = $pdo->query('SELECT slug, id FROM project_categories')->fetchAll(PDO::FETCH_KEY_PAIR);
    $stmt = $pdo->prepare('INSERT INTO projects (lang, name, slug, category_id, category_label, client, url, year, short_description, long_description, need, solution, features, results, technologies, main_image, is_featured, is_visible, status, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,"published",?)');
    $img = $pdo->prepare('INSERT INTO project_images (project_id, path, alt, sort_order) VALUES (?,?,?,?)');
    foreach ($seed['projects'] as $i => $p) {
        // Copie des captures dans /uploads/projects/{slug}
        $paths = [];
        foreach ($p['images'] as $file) {
            $src = __DIR__ . '/seed-images/' . $file;
            if (is_file($src)) {
                $res = process_image_file($src, $file, detect_mime($src), 'projects/' . $p['slug'], 1600);
                if (isset($res['path'])) $paths[] = $res['path'];
            }
        }
        $stmt->execute([
            $lang, $p['name'], $p['slug'], $cats[$p['category']] ?? null, $p['category_label'], $p['client'], $p['url'], $p['year'],
            $p['short_description'], $p['long_description'], $p['need'], $p['solution'], $p['features'], $p['results'], $p['technologies'],
            $paths[0] ?? null, $p['is_featured'], ($i + 1) * 10,
        ]);
        $pid = (int) $pdo->lastInsertId();
        foreach (array_slice($paths, 1) as $k => $path) {
            $img->execute([$pid, $path, $p['name'] . ' — capture ' . ($k + 1), ($k + 1) * 10]);
        }
    }
    out('✔ Réalisations créées (' . count($seed['projects']) . ') avec leurs captures');
}

if ($count('content_blocks') === 0) {
    $stmt = $pdo->prepare('INSERT INTO content_blocks (lang, section, title, subtitle, description, icon, items, sort_order) VALUES (?,?,?,?,?,?,?,?)');
    foreach ($seed['blocks'] as $section => $blocks) {
        foreach ($blocks as $i => $b) {
            $stmt->execute([$lang, $section, $b['title'], $b['subtitle'] ?? null, $b['description'] ?? null, $b['icon'] ?? null, $b['items'] ?? null, ($i + 1) * 10]);
        }
    }
    out('✔ Blocs de contenu créés');
}

if ($count('technologies') === 0) {
    $stmt = $pdo->prepare('INSERT INTO technologies (name, category, sort_order) VALUES (?,?,?)');
    $order = 0;
    foreach ($seed['technologies'] as $category => $names) {
        foreach ($names as $tech) {
            $stmt->execute([$tech, $category, $order += 10]);
        }
    }
    out('✔ Technologies créées');
}

/* ---------------------------------------------------------------
 * 4. Compte administrateur
 * --------------------------------------------------------------- */
if ($count('admins') === 0) {
    $email = $args['admin-email'] ?? env('INSTALL_ADMIN_EMAIL');
    $password = $args['admin-password'] ?? env('INSTALL_ADMIN_PASSWORD');
    $adminName = $args['admin-name'] ?? env('INSTALL_ADMIN_NAME', 'Administrateur');

    if ($cli && (!$email || !$password)) {
        echo 'Email de l\'administrateur : ';
        $email = $email ?: trim((string) fgets(STDIN));
        echo 'Mot de passe (10 caractères min.) : ';
        $password = $password ?: trim((string) fgets(STDIN));
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen((string) $password) < 10) {
        out('✖ Administrateur non créé : email invalide ou mot de passe trop court (10 caractères minimum).');
        out('  Relancez : php install/install.php --admin-email=… --admin-password=…');
        exit(1);
    }
    $pdo->prepare('INSERT INTO admins (name, email, password_hash, role) VALUES (?, ?, ?, "superadmin")')
        ->execute([$adminName, strtolower($email), password_hash($password, PASSWORD_DEFAULT)]);
    out("✔ Administrateur créé : {$email}");
} else {
    out('ℹ Un administrateur existe déjà — étape ignorée.');
}

out('');
out('Installation terminée. Back-office : ' . rtrim((string) env('APP_URL', ''), '/') . '/admin/');
if (!$cli) {
    out('⚠ Pensez à retirer INSTALL_TOKEN du fichier .env.');
}
