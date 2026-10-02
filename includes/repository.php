<?php
/**
 * Accès aux données publiques (requêtes préparées, résultats mis en cache par requête HTTP).
 */

/* ---------------------------- Services ---------------------------- */

function get_services(bool $onlyActive = true): array {
    static $cache = [];
    $k = content_lang() . (int) $onlyActive;
    if (!isset($cache[$k])) {
        $sql = 'SELECT * FROM services WHERE lang = ?' . ($onlyActive ? ' AND is_active = 1' : '') . ' ORDER BY sort_order, id';
        $stmt = db()->prepare($sql);
        $stmt->execute([content_lang()]);
        $cache[$k] = $stmt->fetchAll();
    }
    return $cache[$k];
}

function get_service(string $slug): ?array {
    foreach (get_services() as $service) {
        if ($service['slug'] === $slug) {
            return $service;
        }
    }
    return null;
}

function service_url(array $service): string {
    return url('services/' . $service['slug']);
}

/* --------------------------- Réalisations --------------------------- */

function get_categories(): array {
    static $cache = [];
    $lang = content_lang();
    if (!isset($cache[$lang])) {
        $stmt = db()->prepare(
            'SELECT c.*, (SELECT COUNT(*) FROM projects p
                          WHERE p.category_id = c.id AND p.status = "published" AND p.is_visible = 1) AS project_count
             FROM project_categories c WHERE c.lang = ? ORDER BY c.sort_order, c.name'
        );
        $stmt->execute([$lang]);
        $cache[$lang] = $stmt->fetchAll();
    }
    return $cache[$lang];
}

/**
 * Réalisations publiées et visibles.
 */
function get_projects(?int $limit = null, bool $featuredFirst = false): array {
    $sql = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug
            FROM projects p
            LEFT JOIN project_categories c ON c.id = p.category_id
            WHERE p.lang = ? AND p.status = "published" AND p.is_visible = 1
            ORDER BY ' . ($featuredFirst ? 'p.is_featured DESC, ' : '') . 'p.sort_order, p.id DESC';
    if ($limit) {
        $sql .= ' LIMIT ' . (int) $limit;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute([content_lang()]);
    return $stmt->fetchAll();
}

function get_project(string $slug, bool $allowDraft = false): ?array {
    $sql = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug
            FROM projects p LEFT JOIN project_categories c ON c.id = p.category_id
            WHERE p.slug = ? AND p.lang = ?';
    if (!$allowDraft) {
        $sql .= ' AND p.status = "published" AND p.is_visible = 1';
    }
    $stmt = db()->prepare($sql . ' LIMIT 1');
    $stmt->execute([$slug, content_lang()]);
    return $stmt->fetch() ?: null;
}

function get_project_images(int $projectId): array {
    $stmt = db()->prepare('SELECT * FROM project_images WHERE project_id = ? ORDER BY sort_order, id');
    $stmt->execute([$projectId]);
    return $stmt->fetchAll();
}

function project_url(array $project): string {
    return url('realisations/' . $project['slug']);
}

/* ------------------------- Blocs & technologies ------------------------- */

/**
 * Sections disponibles pour les blocs de contenu.
 */
function block_sections(): array {
    return [
        'approach'     => 'Notre approche (méthodologie)',
        'why'          => 'Pourquoi NEOSEN ? (arguments)',
        'stats'        => 'Chiffres clés',
        'data_offer'   => 'Data — Prestations',
        'about_values' => 'À propos — Valeurs',
    ];
}

function get_blocks(string $section): array {
    static $cache = [];
    $k = content_lang() . ':' . $section;
    if (!isset($cache[$k])) {
        $stmt = db()->prepare('SELECT * FROM content_blocks WHERE lang = ? AND section = ? AND is_active = 1 ORDER BY sort_order, id');
        $stmt->execute([content_lang(), $section]);
        $cache[$k] = $stmt->fetchAll();
    }
    return $cache[$k];
}

/**
 * Technologies actives groupées par catégorie : ['Web' => [...], 'Mobile' => [...]]
 */
function get_technologies_grouped(): array {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $rows = db()->query('SELECT * FROM technologies WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
        foreach ($rows as $row) {
            $cache[$row['category']][] = $row;
        }
    }
    return $cache;
}
