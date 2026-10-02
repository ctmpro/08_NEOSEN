<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (is_post() && csrf_check()) {
    admin_logout();
    flash('success', 'Vous êtes déconnecté.');
}
redirect(admin_url('login.php'));
