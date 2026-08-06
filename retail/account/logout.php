<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/customers.php';
vr_customer_logout();
vr_flash(t('logout'), 'ok');
vr_redirect('/');
