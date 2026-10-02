<?php
/**
 * API (admin) — Enregistre l'ordre d'affichage après un glisser-déposer.
 * POST JSON : { "table": "projects", "ids": [3, 1, 2] }  + en-tête X-CSRF-Token
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once ROOT_PATH . '/admin/includes/auth.php';

if (!is_post()) {
    json_response(['success' => false, 'message' => 'Méthode non autorisée.'], 405);
}
if (!current_admin()) {
    json_response(['success' => false, 'message' => 'Non authentifié.'], 401);
}
if (!csrf_check()) {
    json_response(['success' => false, 'message' => 'Jeton CSRF invalide.'], 419);
}

$payload = json_decode((string) file_get_contents('php://input'), true) ?: [];
// Liste blanche des tables réordonnables
$tables = ['projects', 'project_images', 'services', 'content_blocks', 'technologies', 'project_categories'];
$table = (string) ($payload['table'] ?? '');
$ids = array_values(array_filter(array_map('intval', (array) ($payload['ids'] ?? []))));

if (!in_array($table, $tables, true) || !$ids) {
    json_response(['success' => false, 'message' => 'Requête invalide.'], 422);
}

$stmt = db()->prepare("UPDATE {$table} SET sort_order = ? WHERE id = ?");
db()->beginTransaction();
foreach ($ids as $i => $id) {
    $stmt->execute([($i + 1) * 10, $id]);
}
db()->commit();

json_response(['success' => true, 'message' => 'Ordre enregistré.']);
