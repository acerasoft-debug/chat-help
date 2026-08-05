<?php
/** Çıkış. GET ile de çalışıyor ama oturumu yalnızca kendi çerezi kapatabiliyor. */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';

vr_seller_logout();
vr_flash(t('logout'), 'ok');
vr_redirect('sell.php');
