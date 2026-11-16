<?php
/**
 * Gestion des fichiers envoyés : validation MIME réelle, renommage aléatoire,
 * redimensionnement et conversion WebP des images (si GD le permet).
 */

const IMAGE_MIMES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
    'image/svg+xml' => 'svg',
    'image/avif' => 'avif',
    'image/x-icon' => 'ico',
    'image/vnd.microsoft.icon' => 'ico',
];

const VIDEO_MIMES = [
    'video/mp4'  => 'mp4',
    'video/webm' => 'webm',
];

const ATTACHMENT_MIMES = [
    'application/pdf' => 'pdf',
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'application/vnd.ms-excel' => 'xls',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    'application/vnd.ms-powerpoint' => 'ppt',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'application/zip' => 'zip',
    'application/x-zip-compressed' => 'zip',
    'text/plain' => 'txt',
];

function upload_error_message(int $code): string {
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Fichier trop volumineux.',
        UPLOAD_ERR_PARTIAL  => 'Envoi incomplet, merci de réessayer.',
        UPLOAD_ERR_NO_FILE  => 'Aucun fichier envoyé.',
        default             => 'Erreur lors de l\'envoi du fichier.',
    };
}

function has_upload(?array $file): bool {
    return is_array($file) && isset($file['error']) && $file['error'] !== UPLOAD_ERR_NO_FILE;
}

/**
 * Transforme $_FILES['x'] (multiple) en liste de fichiers individuels.
 */
function normalize_files(?array $files): array {
    if (!$files || !isset($files['name'])) {
        return [];
    }
    if (!is_array($files['name'])) {
        return [$files];
    }
    $out = [];
    foreach ($files['name'] as $i => $name) {
        $out[] = [
            'name' => $name, 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i], 'size' => $files['size'][$i],
        ];
    }
    return $out;
}

function detect_mime(string $path): string {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    return (string) $finfo->file($path);
}

function ensure_dir(string $dir): void {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

function random_filename(string $original, string $ext): string {
    $base = slugify(pathinfo($original, PATHINFO_FILENAME));
    return substr($base, 0, 40) . '-' . bin2hex(random_bytes(5)) . '.' . $ext;
}

/**
 * Vérifie sommairement qu'un SVG ne contient pas de script.
 */
function svg_is_safe(string $path): bool {
    $content = (string) file_get_contents($path);
    return !preg_match('/<script|on[a-z]+\s*=|javascript:|<foreignObject/i', $content);
}

/**
 * Enregistre une image envoyée. Retourne ['path' => 'uploads/…'] ou ['error' => '…'].
 */
function store_image(array $file, string $subdir, int $maxWidth = 1920): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => upload_error_message($file['error'])];
    }
    if ($file['size'] > MAX_IMAGE_SIZE) {
        return ['error' => 'Image trop volumineuse (8 Mo maximum).'];
    }
    $mime = detect_mime($file['tmp_name']);
    if (!isset(IMAGE_MIMES[$mime])) {
        return ['error' => 'Format d\'image non autorisé (JPG, PNG, WebP, GIF, SVG, AVIF).'];
    }
    if ($mime === 'image/svg+xml' && !svg_is_safe($file['tmp_name'])) {
        return ['error' => 'Ce fichier SVG contient du code non autorisé.'];
    }
    return process_image_file($file['tmp_name'], $file['name'], $mime, $subdir, $maxWidth, true);
}

/**
 * Traite une image locale : redimensionne et convertit en WebP si possible.
 */
function process_image_file(string $src, string $originalName, string $mime, string $subdir, int $maxWidth = 1920, bool $isUpload = false): array {
    $dir = UPLOAD_PATH . '/' . trim($subdir, '/');
    ensure_dir($dir);
    $ext = IMAGE_MIMES[$mime];

    $canProcess = in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)
        && function_exists('imagecreatefromstring') && function_exists('imagewebp');

    if ($canProcess) {
        $img = @imagecreatefromstring((string) file_get_contents($src));
        if ($img !== false) {
            $w = imagesx($img);
            $h = imagesy($img);
            if ($w > $maxWidth) {
                $nh = (int) round($h * $maxWidth / $w);
                $resized = imagecreatetruecolor($maxWidth, $nh);
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                imagecopyresampled($resized, $img, 0, 0, 0, 0, $maxWidth, $nh, $w, $h);
                imagedestroy($img);
                $img = $resized;
            } else {
                imagepalettetotruecolor($img);
                imagealphablending($img, false);
                imagesavealpha($img, true);
            }
            $name = random_filename($originalName, 'webp');
            $ok = imagewebp($img, $dir . '/' . $name, 82);
            imagedestroy($img);
            if ($ok) {
                return ['path' => 'uploads/' . trim($subdir, '/') . '/' . $name];
            }
        }
    }

    $name = random_filename($originalName, $ext);
    $moved = $isUpload ? move_uploaded_file($src, $dir . '/' . $name) : copy($src, $dir . '/' . $name);
    if (!$moved) {
        return ['error' => 'Impossible d\'enregistrer le fichier.'];
    }
    return ['path' => 'uploads/' . trim($subdir, '/') . '/' . $name];
}

function store_video(array $file, string $subdir): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => upload_error_message($file['error'])];
    }
    if ($file['size'] > MAX_VIDEO_SIZE) {
        return ['error' => 'Vidéo trop volumineuse (60 Mo maximum).'];
    }
    $mime = detect_mime($file['tmp_name']);
    if (!isset(VIDEO_MIMES[$mime])) {
        return ['error' => 'Format vidéo non autorisé (MP4 ou WebM).'];
    }
    $dir = UPLOAD_PATH . '/' . trim($subdir, '/');
    ensure_dir($dir);
    $name = random_filename($file['name'], VIDEO_MIMES[$mime]);
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return ['error' => 'Impossible d\'enregistrer la vidéo.'];
    }
    return ['path' => 'uploads/' . trim($subdir, '/') . '/' . $name];
}

/**
 * Pièce jointe du formulaire de contact (stockée dans un dossier non accessible publiquement).
 */
function store_attachment(array $file): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => upload_error_message($file['error'])];
    }
    if ($file['size'] > MAX_ATTACHMENT_SIZE) {
        return ['error' => 'Pièce jointe trop volumineuse (5 Mo maximum).'];
    }
    $mime = detect_mime($file['tmp_name']);
    if (!isset(ATTACHMENT_MIMES[$mime])) {
        return ['error' => 'Format de pièce jointe non autorisé.'];
    }
    $dir = UPLOAD_PATH . '/messages/' . date('Y-m');
    ensure_dir($dir);
    $name = bin2hex(random_bytes(16)) . '.' . ATTACHMENT_MIMES[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return ['error' => 'Impossible d\'enregistrer la pièce jointe.'];
    }
    $original = preg_replace('/[^\w\s.\-()àâäéèêëïîôöùûüç]/u', '_', basename($file['name']));
    return ['path' => 'uploads/messages/' . date('Y-m') . '/' . $name, 'name' => mb_substr($original, 0, 200)];
}

/**
 * Supprime un fichier uploadé (uniquement dans /uploads).
 */
function delete_media(?string $path): void {
    $path = (string) $path;
    if ($path === '' || !str_starts_with($path, 'uploads/') || str_contains($path, '..')) {
        return;
    }
    $full = ROOT_PATH . '/' . $path;
    if (is_file($full)) {
        @unlink($full);
    }
}

/**
 * Dimensions d'une image locale (pour les attributs width/height, évite le CLS).
 */
function image_size(?string $path): array {
    static $cache = [];
    $path = (string) $path;
    if ($path === '' || preg_match('#^(https?:)?//#', $path)) {
        return [null, null];
    }
    if (!isset($cache[$path])) {
        $full = ROOT_PATH . '/' . ltrim($path, '/');
        $size = (is_file($full) && !str_ends_with($path, '.svg')) ? @getimagesize($full) : false;
        $cache[$path] = $size ? [$size[0], $size[1]] : [null, null];
    }
    return $cache[$path];
}

/**
 * Balise <img> optimisée (lazy loading, dimensions, alt).
 */
function img_tag(?string $path, string $alt = '', string $class = '', bool $lazy = true, string $sizes = ''): string {
    if (!$path) {
        return '';
    }
    [$w, $h] = image_size($path);
    $attrs = 'src="' . e(media_url($path)) . '" alt="' . e($alt) . '"';
    if ($class) $attrs .= ' class="' . e($class) . '"';
    if ($w && $h) $attrs .= ' width="' . $w . '" height="' . $h . '"';
    if ($sizes) $attrs .= ' sizes="' . e($sizes) . '"';
    $attrs .= $lazy ? ' loading="lazy" decoding="async"' : ' fetchpriority="high" decoding="async"';
    return '<img ' . $attrs . '>';
}
