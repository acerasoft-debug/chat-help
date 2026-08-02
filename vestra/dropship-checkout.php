<?php
/**
 * VESTRA — on-site "Buy now" for a dropship-enabled product.
 * POST /dropship-checkout  (body: id, variant="Colour|Size", qty)
 * No VESTRA login required — same public-checkout model as the partner API
 * (api/dropship.php), just triggered by a real visitor's browser instead of
 * a partner's server. Creates the same kind of order + Stripe Checkout
 * Session via dropship_create_order() and redirects straight to Stripe.
 */
require_once __DIR__ . '/inc/i18n.php';
require_once __DIR__ . '/inc/products.php';
require_once __DIR__ . '/inc/dropship.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /'); exit; }

$id = trim((string)($_POST['id'] ?? ''));
$backUrl = '/dropship?id=' . rawurlencode($id);

if (!empty($_POST['website'])) { header('Location: ' . $backUrl); exit; } // honeypot

$variant = (string)($_POST['variant'] ?? '');
$parts = explode('|', $variant, 2);
$colour = trim($parts[0] ?? '');
$size   = trim($parts[1] ?? '');
$qty    = max(1, (int)($_POST['qty'] ?? 1));

$p = $id !== '' ? vestra_find($id) : null;
if (!$p || empty($p['dropship']['enabled'])) { header('Location: ' . $backUrl); exit; }

$r = dropship_create_order($p, $colour, $size, $qty);

if (!$r['ok']) {
    header('Location: ' . $backUrl . '&dropship_error=' . rawurlencode($r['error'] ?? 'error'));
    exit;
}

header('Location: ' . $r['checkout_url']);
