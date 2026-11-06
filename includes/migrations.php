<?php
/**
 * Migrations de la base : crée les tables manquantes et ajoute les colonnes ajoutées
 * dans les versions suivantes, sans jamais supprimer de données.
 *
 * Exécutées automatiquement à l'ouverture de l'administration quand la version change,
 * ainsi que par install/install.php. Les tables sont créées à partir de install/schema.sql (à conserver).
 */

const DB_VERSION = 8;

/** Colonnes ajoutées après la création initiale des tables : [table, colonne, définition]. */
function db_added_columns(): array {
    return [
        ['crm_documents', 'is_deposit', "TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Facture d''acompte' AFTER deposit_pct"],
        ['crm_documents', 'operation_type', "VARCHAR(20) NOT NULL DEFAULT 'services' AFTER is_deposit"],
        ['crm_documents', 'seller_snapshot', 'TEXT NULL AFTER operation_type'],
        ['crm_documents', 'terms_snapshot', 'TEXT NULL AFTER seller_snapshot'],
        ['crm_documents', 'pdf_path', 'VARCHAR(255) NULL AFTER terms_snapshot'],
        ['crm_documents', 'pdf_hash', 'CHAR(64) NULL AFTER pdf_path'],
    ];
}

function db_migrate(PDO $pdo): void {
    $schemaFile = dirname(__DIR__) . '/install/schema.sql';
    if (is_file($schemaFile)) {
        $schema = preg_replace('/^\s*--.*$/m', '', (string) file_get_contents($schemaFile));
        foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
            $pdo->exec($statement);
        }
    }
    foreach (db_added_columns() as [$table, $column, $definition]) {
        $exists = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE " . $pdo->quote($column))->fetch();
        if (!$exists) {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    }
    // Réalisations ajoutées après l'installation initiale (insérées si absentes)
    db_add_seed_projects($pdo, ['nioulink', 'afamshop']);
    // v5 : technologies de NiouLink précisées (seulement si la fiche n'a pas été modifiée entre-temps)
    $pdo->prepare('UPDATE projects SET technologies = ? WHERE slug = ? AND technologies = ?')
        ->execute(['React Native, Expo, iOS, Android, Application web, API REST, Assistant IA, Mobile money', 'nioulink', 'iOS, Android, Application web, API REST, Assistant IA, Mobile money']);
    // v5 : « Expo » ajouté aux technologies mobiles (si absent)
    if ((int) $pdo->query("SELECT COUNT(*) FROM technologies")->fetchColumn() > 0
        && !(int) $pdo->query("SELECT COUNT(*) FROM technologies WHERE name = 'Expo'")->fetchColumn()) {
        $after = $pdo->query("SELECT sort_order, category FROM technologies WHERE name = 'React Native' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $pdo->prepare('INSERT INTO technologies (name, category, sort_order) VALUES (?, ?, ?)')
            ->execute(['Expo', $after['category'] ?? 'Mobile', ($after['sort_order'] ?? 100) + 5]);
    }
    // v6 : carte de visite NEOSEN par défaut
    if ((int) $pdo->query('SELECT COUNT(*) FROM cards')->fetchColumn() === 0) {
        $get = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? AND lang = 'fr'");
        $set = function (string $k, string $default) use ($get) { $get->execute([$k]); $v = $get->fetchColumn(); return $v !== false && $v !== '' ? (string) $v : $default; };
        $pdo->prepare('INSERT INTO cards (code, label, person_name, job_title, company, tagline, intro, phone, whatsapp, email, website, cta_label, campaign) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute(['neosen', 'Carte NEOSEN', 'Cheikh Tidiane MBAYE', 'CEO', 'NEOSEN', 'Web · Software · Data',
                "Nous concevons des sites web, des applications et des solutions Data sur mesure, de l'idée à la mise en production.",
                $set('contact_phone', '+33 7 88 40 51 21'), $set('contact_whatsapp', '+33788405121'), 'cheikh.mbaye@neosen.tech',
                $set('site_domain', 'neosen.tech'), 'Scannez pour découvrir nos réalisations', 'Carte de visite']);
    }
    // v7 : la réalisation EKTO SEN devient KAYOR (kayor.sn)
    db_replace_seed_project($pdo, 'ekto-sen', 'kayor');
    $pdo->prepare("INSERT INTO settings (setting_key, lang, setting_value) VALUES ('db_version', 'fr', ?)
                   ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([(string) DB_VERSION]);
}

/**
 * Insère une réalisation des données initiales (install/seed.php) avec ses captures.
 */
function db_insert_seed_project(PDO $pdo, array $p, int $sortOrder, string $lang = 'fr'): void {
    $seedDir = dirname(__DIR__) . '/install/seed-images';
    $paths = [];
    foreach ($p['images'] as $file) {
        $src = $seedDir . '/' . $file;
        if (is_file($src) && function_exists('process_image_file')) {
            $res = process_image_file($src, $file, detect_mime($src), 'projects/' . $p['slug'], 1600);
            if (isset($res['path'])) $paths[] = $res['path'];
        }
    }
    $cat = $pdo->prepare('SELECT id FROM project_categories WHERE slug = ? AND lang = ?');
    $cat->execute([$p['category'], $lang]);
    $pdo->prepare('INSERT INTO projects (lang, name, slug, category_id, category_label, client, url, year, short_description, long_description, need, solution, features, results, technologies, main_image, is_featured, is_visible, status, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,"published",?)')
        ->execute([$lang, $p['name'], $p['slug'], $cat->fetchColumn() ?: null, $p['category_label'], $p['client'], $p['url'], $p['year'],
            $p['short_description'], $p['long_description'], $p['need'], $p['solution'], $p['features'], $p['results'], $p['technologies'],
            $paths[0] ?? null, $p['is_featured'], $sortOrder]);
    $pid = (int) $pdo->lastInsertId();
    $img = $pdo->prepare('INSERT INTO project_images (project_id, path, alt, sort_order) VALUES (?,?,?,?)');
    foreach (array_slice($paths, 1) as $k => $path) {
        $img->execute([$pid, $path, $p['name'] . ' — capture ' . ($k + 1), ($k + 1) * 10]);
    }
}

/**
 * Ajoute en tête de liste les réalisations indiquées si elles n'existent pas encore.
 */
function db_add_seed_projects(PDO $pdo, array $slugs): void {
    $seedFile = dirname(__DIR__) . '/install/seed.php';
    if (!is_file($seedFile)) return;
    // Uniquement sur un site déjà installé (sinon l'installateur s'en charge)
    if ((int) $pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn() === 0) return;
    $seed = require $seedFile;
    $exists = $pdo->prepare('SELECT COUNT(*) FROM projects WHERE slug = ?');
    $min = (int) $pdo->query('SELECT COALESCE(MIN(sort_order), 10) FROM projects')->fetchColumn();
    foreach (array_reverse($seed['projects']) as $p) {
        if (!in_array($p['slug'], $slugs, true)) continue;
        $exists->execute([$p['slug']]);
        if ((int) $exists->fetchColumn() > 0) continue;
        $min -= 10;
        db_insert_seed_project($pdo, $p, $min);
    }
}

/**
 * Remplace une réalisation par sa nouvelle version du fichier seed (textes + images),
 * en conservant sa position, sa visibilité et son statut.
 */
function db_replace_seed_project(PDO $pdo, string $oldSlug, string $newSlug): void {
    $seedFile = dirname(__DIR__) . '/install/seed.php';
    if (!is_file($seedFile)) return;
    $stmt = $pdo->prepare('SELECT * FROM projects WHERE slug = ?');
    $stmt->execute([$oldSlug]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) return;
    $check = $pdo->prepare('SELECT COUNT(*) FROM projects WHERE slug = ?');
    $check->execute([$newSlug]);
    if ((int) $check->fetchColumn() > 0) return;
    $seed = require $seedFile;
    $p = null;
    foreach ($seed['projects'] as $candidate) {
        if ($candidate['slug'] === $newSlug) $p = $candidate;
    }
    if (!$p) return;
    foreach ($rows as $old) {
        // Anciennes images supprimées
        $imgs = $pdo->prepare('SELECT path FROM project_images WHERE project_id = ?');
        $imgs->execute([$old['id']]);
        foreach ([$old['main_image'], ...$imgs->fetchAll(PDO::FETCH_COLUMN)] as $path) {
            if (function_exists('delete_media')) delete_media($path);
        }
        $pdo->prepare('DELETE FROM project_images WHERE project_id = ?')->execute([$old['id']]);
        $pdo->prepare('DELETE FROM projects WHERE id = ?')->execute([$old['id']]);
        db_insert_seed_project($pdo, $p, (int) $old['sort_order'], $old['lang']);
        $pdo->prepare('UPDATE projects SET is_visible = ?, status = ?, is_featured = ? WHERE slug = ? AND lang = ?')
            ->execute([$old['is_visible'], $old['status'], $old['is_featured'], $newSlug, $old['lang']]);
    }
    if (defined('UPLOAD_PATH')) @rmdir(UPLOAD_PATH . '/projects/' . $oldSlug);
}

/** Lance les migrations si la base n'est pas à jour (silencieux en cas d'échec, consigné dans les logs). */
function db_migrate_if_needed(PDO $pdo): void {
    try {
        $v = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'db_version' AND lang = 'fr'")->fetchColumn();
        if ((int) $v < DB_VERSION) {
            db_migrate($pdo);
        }
    } catch (Throwable $e) {
        error_log('NEOSEN: migration de la base impossible — ' . $e->getMessage());
    }
}
