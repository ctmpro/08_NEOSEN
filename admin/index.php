<?php
require_once __DIR__ . '/includes/bootstrap.php';
redirect(current_admin() ? admin_url('dashboard.php') : admin_url('login.php'));
