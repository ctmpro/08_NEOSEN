<?php
/**
 * Chargement de chillerlan/php-qrcode 5.0.5 et chillerlan/php-settings-container 3.3.0
 * (licences MIT / Apache 2.0) sans Composer.
 */
spl_autoload_register(function (string $class): void {
    $map = [
        'chillerlan\\QRCode\\'   => __DIR__ . '/php-qrcode/',
        'chillerlan\\Settings\\' => __DIR__ . '/settings-container/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
            return;
        }
    }
});
