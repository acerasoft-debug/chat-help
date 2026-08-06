<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/admin.php';
vr_admin_logout();
vr_redirect('admin/login.php');
