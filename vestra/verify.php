<?php
require_once __DIR__.'/inc/i18n.php';
require_once __DIR__.'/inc/auth.php';

$token = trim($_GET['token'] ?? '');
$ok    = false;

if ($token !== '') {
    $list = auth_accounts();
    foreach ($list as &$a) {
        if (($a['email_token'] ?? '') === $token && ($a['status'] ?? '') === 'pending_email') {
            $a['email_verified'] = true;
            $a['email_token']    = '';
            $a['status']         = 'pending'; // awaiting KYB review
            $ok = true;
            break;
        }
    }
    unset($a);
    if ($ok) auth_save_accounts($list);
}

if ($ok) {
    header('Location: /login?verified=1');
} else {
    header('Location: /login?verify_error=1');
}
exit;
