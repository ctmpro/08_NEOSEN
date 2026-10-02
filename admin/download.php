<?php
/**
 * Téléchargement sécurisé d'une pièce jointe de demande de contact.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

$stmt = db()->prepare('SELECT attachment_path, attachment_name FROM contact_messages WHERE id = ?');
$stmt->execute([(int) ($_GET['id'] ?? 0)]);
$row = $stmt->fetch();
$path = $row['attachment_path'] ?? '';
$full = ROOT_PATH . '/' . $path;

if (!$row || !str_starts_with($path, 'uploads/messages/') || str_contains($path, '..') || !is_file($full)) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

$name = $row['attachment_name'] ?: basename($path);
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', $name) . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header('Content-Length: ' . filesize($full));
header('X-Content-Type-Options: nosniff');
readfile($full);
