<?php
/**
 * Mise à jour du schéma de la base (création des tables manquantes, sans toucher aux données).
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_admin('superadmin');
if (!is_post() || !csrf_check()) {
    redirect(admin_url());
}
require_once ROOT_PATH . '/includes/migrations.php';
try {
    db_migrate(db());
    flash('success', 'Base de données mise à jour.');
} catch (Throwable $e) {
    error_log('NEOSEN: mise à jour du schéma — ' . $e->getMessage());
    flash('error', 'La mise à jour a échoué : ' . $e->getMessage());
}
redirect(admin_url('crm.php'));
