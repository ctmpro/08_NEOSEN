<?php
/**
 * Gabarit du back-office.
 */

function admin_menu(): array {
    $unread = 0;
    try {
        $unread = (int) db()->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0 AND is_archived = 0')->fetchColumn();
    } catch (Throwable $e) {}
    return [
        ['dashboard', 'dashboard.php', 'Tableau de bord', 'grid'],
        ['messages', 'messages.php', 'Demandes', 'inbox', $unread],
        '— Contenus',
        ['projects', 'projects.php', 'Réalisations', 'image'],
        ['categories', 'categories.php', 'Catégories', 'tag'],
        ['services', 'services.php', 'Services', 'layers'],
        ['blocks', 'blocks.php', 'Blocs de contenu', 'puzzle'],
        ['technologies', 'technologies.php', 'Technologies', 'code'],
        ['settings', 'settings.php', 'Textes, médias & réglages', 'settings'],
        '— Administration',
        ['users', 'users.php', 'Administrateurs', 'users'],
        ['account', 'account.php', 'Mon compte', 'user'],
    ];
}

function admin_header(string $title, string $active = '', array $actions = []): void {
    $admin = current_admin();
    $siteName = site_name();
    ?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?> — Administration <?= e($siteName) ?></title>
    <link rel="icon" href="<?= e(media_url(setting('favicon'))) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Sora:wght@600&display=swap">
    <link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
    <style>:root{--primary:<?= e(setting('color_primary')) ?>;--accent:<?= e(setting('color_accent')) ?>;--dark:<?= e(setting('color_dark')) ?>}</style>
</head>
<body class="admin">
<div class="admin-shell">
    <aside class="sidebar" data-sidebar>
        <div class="sidebar-brand">
            <a href="<?= e(admin_url()) ?>">
                <?php if ($logo = setting('logo_light')): ?><img src="<?= e(media_url($logo)) ?>" alt="<?= e($siteName) ?>"><?php else: ?><strong><?= e($siteName) ?></strong><?php endif; ?>
            </a>
            <span class="sidebar-tag">Admin</span>
        </div>
        <nav class="sidebar-nav">
            <?php foreach (admin_menu() as $item):
                if (is_string($item)): ?>
                <p class="sidebar-label"><?= e(ltrim($item, '— ')) ?></p>
                <?php continue; endif;
                [$key, $href, $label, $ico] = $item;
                if ($key === 'users' && !is_superadmin()) continue; ?>
                <a href="<?= e(admin_url($href)) ?>" class="<?= $active === $key ? 'is-active' : '' ?>">
                    <?= icon($ico) ?><span><?= e($label) ?></span>
                    <?php if (!empty($item[4])): ?><span class="badge"><?= (int) $item[4] ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <a href="<?= e(url()) ?>" target="_blank" rel="noopener"><?= icon('external') ?><span>Voir le site</span></a>
            <form method="post" action="<?= e(admin_url('logout.php')) ?>">
                <?= csrf_field() ?>
                <button type="submit"><?= icon('logout') ?><span>Déconnexion</span></button>
            </form>
        </div>
    </aside>
    <div class="sidebar-backdrop" data-sidebar-close></div>

    <div class="admin-main">
        <header class="topbar">
            <button class="burger" type="button" data-sidebar-toggle aria-label="Menu"><?= icon('menu') ?></button>
            <h1 class="topbar-title"><?= e($title) ?></h1>
            <div class="topbar-actions">
                <?php foreach ($actions as [$label, $href, $style]): ?>
                <a class="btn btn-sm <?= e($style ?? 'btn-primary') ?>" href="<?= e($href) ?>"><?= $label ?></a>
                <?php endforeach; ?>
                <?php if ($admin): ?>
                <a class="avatar" href="<?= e(admin_url('account.php')) ?>" title="<?= e($admin['name']) ?>"><?= e(mb_strtoupper(mb_substr($admin['name'], 0, 1))) ?></a>
                <?php endif; ?>
            </div>
        </header>
        <main class="admin-content">
            <?php foreach (get_flashes() as $flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?><button type="button" class="alert-close" aria-label="Fermer">×</button></div>
            <?php endforeach; ?>
<?php
}

function admin_footer(): void {
    ?>
        </main>
    </div>
</div>
<script src="<?= asset('js/admin.js') ?>" defer></script>
</body>
</html>
<?php
}

/**
 * Sélecteur de langue de contenu (affiché seulement si plusieurs langues sont actives).
 */
function admin_lang_switcher(): string {
    if (count(APP_LANGS) < 2) {
        return '';
    }
    $current = admin_lang();
    $html = '<div class="lang-switch">Langue du contenu :';
    foreach (APP_LANGS as $l) {
        $q = array_merge($_GET, ['lang' => $l]);
        $html .= ' <a href="?' . e(http_build_query($q)) . '" class="' . ($l === $current ? 'is-active' : '') . '">' . strtoupper($l) . '</a>';
    }
    return $html . '</div>';
}
