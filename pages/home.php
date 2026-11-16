<?php
/**
 * Page d'accueil.
 */
$meta['title'] = (string) setting('seo_home_title');
$meta['description'] = (string) setting('seo_home_desc');
$meta['schema'][] = [
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => site_name(),
    'url' => absolute_url(''),
    'inLanguage' => current_lang(),
];

$services  = get_services();
$projects  = get_projects(max(1, (int) setting('home_projects_limit', 6)), true);
$allProjects = get_projects();
$approach  = get_blocks('approach');
$why       = get_blocks('why');
$stats     = get_blocks('stats');
$dataOffer = get_blocks('data_offer');
$techs     = get_technologies_grouped();
$heroVisual = (string) setting('hero_visual', 'animation');

require ROOT_PATH . '/includes/header.php';
?>

<!-- ============================== HERO ============================== -->
<section class="hero" aria-labelledby="hero-title">
    <div class="hero-bg" aria-hidden="true">
        <canvas class="hero-canvas" data-network></canvas>
        <div class="grid-lines"></div>
        <div class="glow glow-1"></div>
        <div class="glow glow-2"></div>
    </div>

    <div class="container hero-inner">
        <div class="hero-content">
            <p class="eyebrow hero-anim" style="--d:1"><span class="pulse-dot"></span><?= e(setting('hero_eyebrow')) ?></p>
            <h1 id="hero-title" class="hero-title hero-anim" style="--d:2"><?= highlight(setting('hero_title')) ?></h1>
            <p class="hero-lead hero-anim" style="--d:3"><?= e(setting('hero_subtitle')) ?></p>
            <div class="btn-group hero-anim" style="--d:4">
                <a class="btn btn-primary btn-lg" href="<?= e(link_to(setting('hero_cta1_link'))) ?>"><?= e(setting('hero_cta1_label')) ?><?= icon('arrow', 'icon btn-icon') ?></a>
                <a class="btn btn-ghost btn-lg" href="<?= e(link_to(setting('hero_cta2_link'))) ?>"><?= e(setting('hero_cta2_label')) ?></a>
            </div>
            <?php if ($badges = setting_lines('hero_badges')): ?>
            <ul class="hero-badges hero-anim" style="--d:5">
                <?php foreach ($badges as $badge): ?><li><?= icon('check-circle') ?><?= e($badge) ?></li><?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <div class="hero-visual hero-anim" style="--d:3">
            <?php if ($heroVisual === 'image' && setting('hero_image')): ?>
                <div class="hero-media"><?= img_tag(setting('hero_image'), site_name(), '', false) ?></div>
            <?php elseif ($heroVisual === 'video' && setting('hero_video')): ?>
                <div class="hero-media">
                    <video autoplay muted loop playsinline preload="metadata" <?= setting('hero_video_poster') ? 'poster="' . e(media_url(setting('hero_video_poster'))) . '"' : '' ?>>
                        <source src="<?= e(media_url(setting('hero_video'))) ?>">
                    </video>
                </div>
            <?php else: ?>
            <!-- Composition animée : code + interface + données -->
            <div class="hv" aria-hidden="true">
                <svg class="hv-links" viewBox="0 0 520 520" preserveAspectRatio="none">
                    <path d="M150 170 C 250 170, 260 300, 360 320" />
                    <path d="M170 380 C 230 380, 260 300, 360 320" />
                    <path d="M150 170 C 120 260, 130 330, 170 380" />
                </svg>

                <div class="hv-card hv-code">
                    <div class="hv-bar"><i></i><i></i><i></i><span class="mono">app.php</span></div>
                    <pre class="mono" data-typing><code><span class="k">class</span> <span class="t">Project</span> {
  <span class="k">public function</span> <span class="f">launch</span>() {
    <span class="v">$idea</span> = <span class="s">'votre vision'</span>;
    <span class="k">return</span> <span class="f">build</span>(<span class="v">$idea</span>);
  }
}</code></pre>
                </div>

                <div class="hv-card hv-dash">
                    <div class="hv-dash-head">
                        <span class="mono">KPI · Power BI</span>
                        <span class="hv-trend"><?= icon('trending') ?>+24%</span>
                    </div>
                    <div class="hv-value">1,28<small>M</small></div>
                    <div class="hv-bars">
                        <span style="--h:38%"></span><span style="--h:52%"></span><span style="--h:46%"></span><span style="--h:64%"></span><span style="--h:58%"></span><span style="--h:78%"></span><span style="--h:92%"></span>
                    </div>
                    <svg class="hv-line" viewBox="0 0 200 60" preserveAspectRatio="none"><path d="M0 50 L30 42 L60 46 L90 30 L120 34 L150 18 L200 8"/></svg>
                </div>

                <div class="hv-card hv-app">
                    <div class="hv-app-notch"></div>
                    <div class="hv-app-row"><span class="hv-avatar"></span><span class="hv-lines"><i></i><i></i></span></div>
                    <div class="hv-app-tile"><span></span><span></span></div>
                    <div class="hv-app-list"><i></i><i></i><i></i></div>
                    <div class="hv-app-btn"></div>
                </div>

                <div class="hv-node hv-node-1"><?= icon('code') ?></div>
                <div class="hv-node hv-node-2"><?= icon('database') ?></div>
                <div class="hv-node hv-node-3"><?= icon('app') ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ============================== RÉFÉRENCES ============================== -->
<?php if (setting_bool('home_show_trust') && $allProjects): ?>
<section class="trust" aria-label="<?= e(setting('home_trust_title')) ?>">
    <div class="container trust-inner">
        <p class="trust-title"><?= e(setting('home_trust_title')) ?></p>
        <div class="marquee" data-marquee>
            <ul class="marquee-track">
                <?php for ($r = 0; $r < 2; $r++): foreach ($allProjects as $p): ?>
                <li<?= $r ? ' aria-hidden="true"' : '' ?>><span class="marquee-dot"></span><?= e($p['client'] ?: $p['name']) ?></li>
                <?php endforeach; endfor; ?>
            </ul>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================== EXPERTISES ============================== -->
<section class="section" id="expertises">
    <div class="container">
        <?php partial('section-head', ['eyebrow' => setting('home_expertises_eyebrow'), 'title' => setting('home_expertises_title'), 'text' => setting('home_expertises_text')]); ?>

        <div class="expertise-grid">
            <?php foreach ($services as $i => $service): ?>
            <article class="expertise-card" data-reveal style="--d:<?= $i ?>">
                <div class="expertise-top">
                    <span class="expertise-num mono"><?= e($service['eyebrow'] ?: sprintf('%02d', $i + 1)) ?></span>
                    <span class="icon-box"><?= icon($service['icon'] ?: 'sparkles', 'icon', $service['name']) ?></span>
                </div>
                <h3 class="expertise-title"><?= e($service['name']) ?></h3>
                <p class="expertise-desc"><?= e($service['short_description']) ?></p>
                <?php if ($items = lines($service['items'])): ?>
                <ul class="expertise-list">
                    <?php foreach (array_slice($items, 0, 8) as $item): ?><li><?= e($item) ?></li><?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <a class="link-arrow" href="<?= e($service['link_url'] ? link_to($service['link_url']) : service_url($service)) ?>">
                    <?= e($service['cta_label'] ?: t('common.discover')) ?><?= icon('arrow') ?>
                    <span class="sr-only"> — <?= e($service['name']) ?></span>
                </a>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== RÉALISATIONS ============================== -->
<section class="section section--soft" id="realisations">
    <div class="container">
        <div class="section-head-row">
            <?php partial('section-head', ['eyebrow' => setting('home_projects_eyebrow'), 'title' => setting('home_projects_title'), 'text' => setting('home_projects_text')]); ?>
            <a class="btn btn-ghost" href="<?= e(url('realisations')) ?>" data-reveal><?= e(t('common.see_all_projects')) ?><?= icon('arrow', 'icon btn-icon') ?></a>
        </div>
        <?php partial('projects-grid', ['projects' => $projects, 'show_filters' => true]); ?>
    </div>
</section>

<!-- ============================== APPROCHE ============================== -->
<?php if ($approach): ?>
<section class="section" id="approche">
    <div class="container">
        <?php partial('section-head', ['eyebrow' => setting('home_approach_eyebrow'), 'title' => setting('home_approach_title'), 'text' => setting('home_approach_text'), 'align' => 'center']); ?>
        <ol class="timeline" data-timeline>
            <span class="timeline-progress" aria-hidden="true"></span>
            <?php foreach ($approach as $i => $step): ?>
            <li class="timeline-step" data-reveal style="--d:<?= $i % 3 ?>">
                <span class="timeline-dot"><?= icon($step['icon'] ?: 'sparkles') ?></span>
                <div class="timeline-body">
                    <span class="timeline-num mono"><?= e($step['subtitle'] ?: sprintf('%02d', $i + 1)) ?></span>
                    <h3><?= e($step['title']) ?></h3>
                    <p><?= e($step['description']) ?></p>
                </div>
            </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>
<?php endif; ?>

<!-- ============================== DATA ============================== -->
<section class="section section--dark data-band" id="data">
    <div class="data-band-bg" aria-hidden="true"><div class="grid-lines grid-lines--light"></div><div class="glow glow-1"></div></div>
    <div class="container data-band-inner">
        <div class="data-band-content" data-reveal>
            <p class="eyebrow eyebrow--light"><?= e(setting('home_data_eyebrow')) ?></p>
            <h2 class="section-title"><?= highlight(setting('home_data_title')) ?></h2>
            <p class="section-text"><?= e(setting('home_data_text')) ?></p>
            <?php if ($dataOffer): ?>
            <ul class="data-pills">
                <?php foreach ($dataOffer as $offer): ?><li><?= icon($offer['icon'] ?: 'data') ?><?= e($offer['title']) ?></li><?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <a class="btn btn-light" href="<?= e(url('data')) ?>"><?= e(setting('home_data_cta')) ?><?= icon('arrow', 'icon btn-icon') ?></a>
        </div>
        <?php partial('data-pipeline'); ?>
    </div>
</section>

<!-- ============================== POURQUOI NEOSEN ============================== -->
<?php if ($why): ?>
<section class="section" id="pourquoi">
    <div class="container">
        <?php partial('section-head', ['eyebrow' => setting('home_why_eyebrow'), 'title' => setting('home_why_title'), 'text' => setting('home_why_text')]); ?>
        <div class="why-grid">
            <?php foreach ($why as $i => $arg): ?>
            <article class="why-card" data-reveal style="--d:<?= $i % 3 ?>">
                <span class="icon-box icon-box--soft"><?= icon($arg['icon'] ?: 'sparkles') ?></span>
                <h3><?= e($arg['title']) ?></h3>
                <p><?= e($arg['description']) ?></p>
            </article>
            <?php endforeach; ?>
        </div>

        <?php if ($stats && setting_bool('home_show_stats')): ?>
        <div class="stats" data-reveal>
            <?php foreach ($stats as $stat): ?>
            <div class="stat">
                <span class="stat-value" data-count="<?= e($stat['title']) ?>"><?= e($stat['title']) ?></span>
                <span class="stat-label"><?= e($stat['subtitle'] ?: $stat['description']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- ============================== TECHNOLOGIES ============================== -->
<?php if ($techs): ?>
<section class="section section--soft" id="technologies">
    <div class="container">
        <?php partial('section-head', ['eyebrow' => setting('home_tech_eyebrow'), 'title' => setting('home_tech_title'), 'text' => setting('home_tech_text'), 'align' => 'center']); ?>
        <?php partial('tech-groups', ['techs' => $techs]); ?>
    </div>
</section>
<?php endif; ?>

<?php partial('cta'); ?>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
