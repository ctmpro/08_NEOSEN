<?php
/**
 * Mise à jour du schéma de la base (création des tables manquantes, sans toucher aux données).
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_admin('superadmin');
if (!is_post() || !csrf_check()) {
    redirect(admin_url());
}
$schema = preg_replace('/^\s*--.*$/m', '', (string) file_get_contents(ROOT_PATH . '/install/schema.sql'));
try {
    foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
        db()->exec($statement);
    }
    flash('success', 'Base de données mise à jour.');
} catch (Throwable $e) {
    error_log('NEOSEN: mise à jour du schéma — ' . $e->getMessage());
    flash('error', 'La mise à jour a échoué : ' . $e->getMessage());
}
redirect(admin_url('crm.php'));
