<?php
/**
 * Tableau de bord.
 */
require_once __DIR__ . '/includes/bootstrap.php';
$admin = require_admin();

$count = fn(string $sql) => (int) db()->query($sql)->fetchColumn();
$stats = [
    ['Réalisations', $count('SELECT COUNT(*) FROM projects'), $count('SELECT COUNT(*) FROM projects WHERE status = "published" AND is_visible = 1') . ' publiées', 'image', 'projects.php'],
    ['Services', $count('SELECT COUNT(*) FROM services'), $count('SELECT COUNT(*) FROM services WHERE is_active = 1') . ' actifs', 'layers', 'services.php'],
    ['Demandes de contact', $count('SELECT COUNT(*) FROM contact_messages'), $count('SELECT COUNT(*) FROM contact_messages WHERE created_at > (NOW() - INTERVAL 30 DAY)') . ' sur 30 jours', 'inbox', 'messages.php'],
    ['Messages non lus', $count('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0 AND is_archived = 0'), 'à traiter', 'mail', 'messages.php?filter=unread'],
];
$latestMessages = db()->query('SELECT id, first_name, last_name, company, project_type, is_read, created_at FROM contact_messages WHERE is_archived = 0 ORDER BY created_at DESC LIMIT 6')->fetchAll();
$latestProjects = db()->query('SELECT id, name, category_label, main_image, status, is_visible, created_at FROM projects ORDER BY created_at DESC, id DESC LIMIT 5')->fetchAll();
$byType = db()->query('SELECT COALESCE(project_type, "—") AS t, COUNT(*) AS n FROM contact_messages GROUP BY project_type ORDER BY n DESC LIMIT 6')->fetchAll();
$maxType = max(array_column($byType, 'n') ?: [1]);

admin_header('Tableau de bord', 'dashboard', [[icon('plus', 'icon') . 'Nouvelle réalisation', admin_url('project-edit.php'), 'btn-primary']]);
?>
<section class="welcome">
    <div>
        <p class="muted">Bonjour <?= e(explode(' ', $admin['name'])[0]) ?> 👋</p>
        <h2>Voici l'activité de votre site.</h2>
    </div>
    <?php if ($admin['last_login_at']): ?><p class="muted small">Dernière connexion : <?= e(format_date($admin['last_login_at'], true)) ?></p><?php endif; ?>
</section>

<div class="stat-grid">
    <?php foreach ($stats as [$label, $value, $sub, $ico, $href]): ?>
    <a class="stat-card" href="<?= e(admin_url($href)) ?>">
        <span class="stat-icon"><?= icon($ico) ?></span>
        <span class="stat-label"><?= e($label) ?></span>
        <strong class="stat-value"><?= $value ?></strong>
        <span class="stat-sub"><?= e($sub) ?></span>
    </a>
    <?php endforeach; ?>
</div>

<div class="grid-2">
    <section class="card">
        <div class="card-head">
            <h2>Dernières demandes</h2>
            <a class="link" href="<?= e(admin_url('messages.php')) ?>">Tout voir</a>
        </div>
        <?php if ($latestMessages): ?>
        <ul class="list">
            <?php foreach ($latestMessages as $m): ?>
            <li>
                <a href="<?= e(admin_url('messages.php', ['id' => $m['id']])) ?>" class="list-row<?= $m['is_read'] ? '' : ' is-unread' ?>">
                    <span class="avatar avatar--soft"><?= e(mb_strtoupper(mb_substr($m['first_name'], 0, 1))) ?></span>
                    <span class="list-main"><strong><?= e($m['first_name'] . ' ' . $m['last_name']) ?></strong><small><?= e(trim(($m['company'] ? $m['company'] . ' · ' : '') . $m['project_type'])) ?></small></span>
                    <span class="list-meta"><?= e(format_date($m['created_at'])) ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?><p class="empty">Aucune demande pour le moment.</p><?php endif; ?>
    </section>

    <section class="card">
        <div class="card-head">
            <h2>Dernières réalisations</h2>
            <a class="link" href="<?= e(admin_url('projects.php')) ?>">Gérer</a>
        </div>
        <ul class="list">
            <?php foreach ($latestProjects as $p): ?>
            <li>
                <a href="<?= e(admin_url('project-edit.php', ['id' => $p['id']])) ?>" class="list-row">
                    <span class="thumb"><?php if ($p['main_image']): ?><img src="<?= e(media_url($p['main_image'])) ?>" alt=""><?php endif; ?></span>
                    <span class="list-main"><strong><?= e($p['name']) ?></strong><small><?= e($p['category_label']) ?></small></span>
                    <span class="pill pill--<?= $p['status'] === 'published' && $p['is_visible'] ? 'success' : 'muted' ?>"><?= $p['status'] === 'published' ? ($p['is_visible'] ? 'Publié' : 'Masqué') : 'Brouillon' ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>

<?php if ($byType): ?>
<section class="card">
    <div class="card-head"><h2>Demandes par type de projet</h2></div>
    <ul class="bars">
        <?php foreach ($byType as $row): ?>
        <li><span class="bars-label"><?= e($row['t']) ?></span><span class="bars-track"><span style="width:<?= round($row['n'] / $maxType * 100) ?>%"></span></span><strong><?= (int) $row['n'] ?></strong></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<section class="card quick-links">
    <div class="card-head"><h2>Raccourcis</h2></div>
    <div class="quick-grid">
        <a href="<?= e(admin_url('settings.php', ['tab' => 'hero'])) ?>"><?= icon('rocket') ?>Modifier le hero</a>
        <a href="<?= e(admin_url('settings.php', ['tab' => 'contact'])) ?>"><?= icon('phone') ?>Coordonnées</a>
        <a href="<?= e(admin_url('settings.php', ['tab' => 'general'])) ?>"><?= icon('layers') ?>Logo & couleurs</a>
        <a href="<?= e(admin_url('blocks.php', ['section' => 'approach'])) ?>"><?= icon('flow') ?>Méthodologie</a>
        <a href="<?= e(admin_url('technologies.php')) ?>"><?= icon('code') ?>Technologies</a>
        <a href="<?= e(admin_url('settings.php', ['tab' => 'seo'])) ?>"><?= icon('search') ?>SEO</a>
    </div>
</section>
<?php admin_footer(); ?>
