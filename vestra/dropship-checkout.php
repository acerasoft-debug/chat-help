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
/* Through auth.php even though this checkout needs no login: it is auth.php that
   points the session at data/sessions. A bare session_start() here would put this
   page's session in the host's shared /tmp, where it is collected within minutes --
   and a checkout that forgets its own basket mid-flow is worse than one that asks
   for a login it does not need. */
require_once __DIR__ . '/inc/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /'); exit; }

/* Kapi SUNUCUDA. Sayfadaki formu gizlemek bir gorunum tercihi; bu uc ise
   dogrudan POST atilabilir, dolayisiyla "ticari hesap" sarti burada da
   sinaniyor. Aksi halde sart yalnizca dogru davranan ziyaretci icin gecerli
   olurdu -- yani hic gecerli olmazdi. */
$dsUser = auth_user();
if (!$dsUser || !auth_prices_unlocked($dsUser)) {
    header('Location: /login?back=' . urlencode('/dropship?id=' . (string)($_POST['id'] ?? '')));
    exit;
}

$id = trim((string)($_POST['id'] ?? ''));
$backUrl = '/dropship?id=' . rawurlencode($id);

if (!empty($_POST['website'])) { header('Location: ' . $backUrl); exit; } // honeypot

/* Iki bicim de kabul ediliyor: stok haritasi olan ilanlarda tek bir
   "renk|beden" secimi geliyor, katalog geneline acilan ilanlarda ise renk ve
   beden AYRI alanlar -- cunku oralarda secilecek bir harita yok. */
$variant = (string)($_POST['variant'] ?? '');
if ($variant !== '') {
    $parts  = explode('|', $variant, 2);
    $colour = trim($parts[0] ?? '');
    $size   = trim($parts[1] ?? '');
} else {
    $colour = trim((string)($_POST['colour'] ?? ''));
    $size   = trim((string)($_POST['size'] ?? ''));
}
$qty = max(1, (int)($_POST['qty'] ?? 1));

$p = $id !== '' ? vestra_find($id) : null;
if (!$p || !vestra_dropship_enabled($p)) { header('Location: ' . $backUrl); exit; }

$r = dropship_create_order($p, $colour, $size, $qty);

if (!$r['ok']) {
    header('Location: ' . $backUrl . '&dropship_error=' . rawurlencode($r['error'] ?? 'error'));
    exit;
}

header('Location: ' . $r['checkout_url']);
