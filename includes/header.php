<?php
/**
 * En-tête HTML commun. Attend $meta (title, description, canonical, image, type, schema) et $current_page.
 */
$siteName   = site_name();
$metaTitle  = $meta['title'] ?? $siteName;
$metaDesc   = excerpt($meta['description'] ?? '', 300);
$ogImage    = media_url($meta['image'] ?? setting('og_image'), true);
$logo       = (string) setting('logo');
$logoHeight = max(20, min(80, (int) setting('logo_height', 34)));
$navItems = [
    'home'     => ['', t('nav.home')],
    'about'    => ['a-propos', t('nav.about')],
    'services' => ['services', t('nav.services')],
    'projects' => ['realisations', t('nav.projects')],
    'data'     => ['data', t('nav.data')],
    'contact'  => ['contact', t('nav.contact')],
];
$activeMap = ['service' => 'services', 'project' => 'projects'];
$active = $activeMap[$current_page] ?? $current_page;
$isHome = $current_page === 'home';
$darkHero = in_array($current_page, ['data'], true);
$logoLight = (string) (setting('logo_light') ?: $logo);

$schemas = $meta['schema'] ?? [];
$schemas[] = [
    '@context' => 'https://schema.org',
    '@type'    => 'Organization',
    'name'     => $siteName,
    'url'      => absolute_url(''),
    'logo'     => media_url($logo, true),
    'email'    => setting('contact_email'),
    'telephone'=> setting('contact_phone'),
    'address'  => ['@type' => 'PostalAddress', 'addressLocality' => setting('contact_address')],
    'sameAs'   => array_values(social_links()),
];
?><!doctype html>
<html lang="<?= e(current_lang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <script>document.documentElement.classList.add('js')</script>
    <title><?= e($metaTitle) ?></title>
    <meta name="description" content="<?= e($metaDesc) ?>">
    <link rel="canonical" href="<?= e($meta['canonical']) ?>">
    <?php if (!setting_bool('seo_indexing') || !empty($meta['noindex'])): ?>
    <meta name="robots" content="noindex, nofollow">
    <?php endif; ?>
    <?php if (count(APP_LANGS) > 1): foreach (APP_LANGS as $l): ?>
    <link rel="alternate" hreflang="<?= e($l) ?>" href="<?= e(absolute_url($meta['path'] ?? '', $l)) ?>">
    <?php endforeach; endif; ?>

    <meta property="og:site_name" content="<?= e($siteName) ?>">
    <meta property="og:type" content="<?= e($meta['type'] ?? 'website') ?>">
    <meta property="og:title" content="<?= e($metaTitle) ?>">
    <meta property="og:description" content="<?= e($metaDesc) ?>">
    <meta property="og:url" content="<?= e($meta['canonical']) ?>">
    <meta property="og:locale" content="<?= current_lang() === 'en' ? 'en_US' : 'fr_FR' ?>">
    <?php if ($ogImage): ?><meta property="og:image" content="<?= e($ogImage) ?>"><?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
    <meta name="theme-color" content="<?= e(setting('color_dark')) ?>">

    <link rel="icon" href="<?= e(media_url(setting('favicon'))) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Sora:wght@500;600;700&display=swap">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <style>:root{--primary:<?= e(setting('color_primary')) ?>;--accent:<?= e(setting('color_accent')) ?>;--dark:<?= e(setting('color_dark')) ?>}</style>
    <?php foreach ($schemas as $schema): ?>
    <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
    <?php endforeach; ?>
    <?php if ($ga = trim((string) setting('analytics_id'))): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($ga) ?>"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','<?= e($ga) ?>');</script>
    <?php endif; ?>
</head>
<body class="page-<?= e($current_page) ?><?= $isHome ? ' is-home' : '' ?><?= $darkHero ? ' has-dark-hero' : '' ?>">
<a class="skip-link" href="#main"><?= e(t('nav.skip')) ?></a>

<header class="site-header" data-header>
    <div class="container header-inner">
        <a class="brand" href="<?= e(url()) ?>" aria-label="<?= e($siteName) ?> — <?= e(t('nav.home')) ?>">
            <?php if ($logo): ?>
                <img class="logo-default" src="<?= e(media_url($logo)) ?>" alt="<?= e($siteName) ?>" height="<?= $logoHeight ?>" style="height:<?= $logoHeight ?>px">
                <?php if ($darkHero): ?><img class="logo-on-dark" src="<?= e(media_url($logoLight)) ?>" alt="" aria-hidden="true" height="<?= $logoHeight ?>" style="height:<?= $logoHeight ?>px"><?php endif; ?>
            <?php else: ?>
                <span class="brand-text"><?= e($siteName) ?></span>
            <?php endif; ?>
        </a>

        <nav class="main-nav" aria-label="Navigation principale">
            <ul>
                <?php foreach ($navItems as $key => [$href, $label]): ?>
                <li><a href="<?= e(url($href)) ?>"<?= $active === $key ? ' class="is-active" aria-current="page"' : '' ?>><?= e($label) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="header-actions">
            <a class="btn btn-primary btn-sm header-cta" href="<?= e(url('contact')) ?>">
                <span><?= e(setting('header_cta_label')) ?></span><?= icon('arrow', 'icon btn-icon') ?>
            </a>
            <button class="nav-toggle" type="button" aria-label="<?= e(t('nav.menu')) ?>" aria-expanded="false" aria-controls="mobile-nav" data-nav-toggle>
                <span></span><span></span>
            </button>
        </div>
    </div>
</header>

<div class="mobile-nav" id="mobile-nav" data-mobile-nav aria-hidden="true">
    <nav aria-label="Navigation mobile">
        <ul>
            <?php $i = 0; foreach ($navItems as $key => [$href, $label]): $i++; ?>
            <li style="--i:<?= $i ?>"><a href="<?= e(url($href)) ?>"<?= $active === $key ? ' class="is-active" aria-current="page"' : '' ?>><span class="mono">0<?= $i ?></span><?= e($label) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </nav>
    <div class="mobile-nav-footer">
        <a class="btn btn-primary btn-block" href="<?= e(url('contact')) ?>"><?= e(setting('header_cta_label')) ?></a>
        <div class="mobile-nav-contact">
            <?php if ($email = setting('contact_email')): ?><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a><?php endif; ?>
            <?php if ($phone = setting('contact_phone')): ?><a href="<?= e(tel_link($phone)) ?>"><?= e($phone) ?></a><?php endif; ?>
        </div>
    </div>
</div>

<main id="main">
