<?php
/**
 * Page Réalisations.
 */
$meta['title'] = (string) setting('seo_projects_title');
$meta['description'] = (string) setting('seo_projects_desc');
$projects = get_projects(null, false);

require ROOT_PATH . '/includes/header.php';
partial('page-hero', ['eyebrow' => setting('projects_eyebrow'), 'title' => setting('projects_title'), 'text' => setting('projects_intro'), 'breadcrumb' => [[t('nav.projects'), null]]]);
?>

<section class="section section--tight-top">
    <div class="container">
        <?php partial('projects-grid', ['projects' => $projects, 'show_filters' => true]); ?>
    </div>
</section>

<?php partial('cta', ['cta_title' => t('project.similar'), 'cta_text' => t('project.similar_text')]); ?>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
