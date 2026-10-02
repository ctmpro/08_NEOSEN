<?php
/**
 * robots.txt dynamique (respecte le paramètre d'indexation de l'admin).
 */
header('Content-Type: text/plain; charset=utf-8');
echo "User-agent: *\n";
if (!setting_bool('seo_indexing')) {
    echo "Disallow: /\n";
    return;
}
echo "Disallow: /admin/\nDisallow: /api/\nDisallow: /uploads/messages/\nAllow: /\n\n";
echo 'Sitemap: ' . absolute_url('sitemap.xml') . "\n";
