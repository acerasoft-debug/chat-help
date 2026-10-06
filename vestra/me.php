<?php
/**
 * VESTRA — /me?tab=orders : "my panel", whichever panel that is.
 *
 * The installed app's shortcuts (long-press on the icon) pointed straight at
 * /buyer?tab=… — a seller who tapped "Messages" landed in the BUYER panel. This
 * resolves the panel from the account, and a signed-out tap comes back to the
 * same place after signing in.
 */
require_once __DIR__.'/inc/auth.php';

$tab = (string)($_GET['tab'] ?? '');
if (!in_array($tab, ['overview', 'orders', 'offers', 'messages', 'profile', 'listings', 'requests', 'kyc'], true)) $tab = '';

$u = auth_user();
if (!$u) {
    header('Location: /login?back='.rawurlencode('/me'.($tab !== '' ? '?tab='.$tab : '')));
    exit;
}
$seller = ($u['type'] ?? '') === 'seller';
if ($tab === 'listings' && !$seller) $tab = 'orders';   // a buyer has no listings
if ($tab === 'requests' && $seller)  $tab = 'orders';   // sourcing requests are the buyer's
header('Location: '.($seller ? '/seller' : '/buyer').($tab !== '' && $tab !== 'overview' ? '?tab='.$tab : ''));
exit;
