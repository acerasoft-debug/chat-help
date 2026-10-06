<?php
/**
 * VESTRA — order summary PDF.  /order-pdf?ref=VES-XXXXXXXX
 *
 * Access is checked against the order itself, not against "is somebody logged in": the ref
 * is short and appears in e-mail subjects, so a signed-in stranger who guessed or was
 * forwarded one must not be able to pull another company's order down. Three ways in —
 * the admin, the buyer the order belongs to, and a seller who has a line in it.
 */
require_once __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/auth.php';
require_once __DIR__.'/inc/orders.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$ref = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($_GET['ref'] ?? ''));
if ($ref === '') { http_response_code(400); exit('Missing order reference.'); }

$order = null;
foreach (vestra_read_csv('orders.csv') as $row) { if (($row['ref'] ?? '') === $ref) { $order = $row; break; } }
if (!$order) { http_response_code(404); exit('Order not found.'); }

$ld    = vestra_order_lines($order);
$lines = $ld['lines'];

$me      = auth_user();
$isAdmin = !empty($_SESSION['vadmin']);
$isBuyer = $me && strtolower(trim((string)($me['email'] ?? ''))) === strtolower(trim((string)($order['email'] ?? '')));
$isSeller = false;
if ($me && ($me['type'] ?? '') === 'seller') {
    foreach ($lines as $l) { if (($l['seller_uid'] ?? '') === ($me['id'] ?? '')) { $isSeller = true; break; } }
}
if (!$isAdmin && !$isBuyer && !$isSeller) {
    /* Not "403 forbidden" for a signed-out visitor — they may simply be the buyer on a
       fresh browser, so send them to log in and back to the order they asked for. */
    if (!$me) { header('Location: /login?back='.urlencode('/buyer?tab=orders&view='.$ref)); exit; }
    http_response_code(403);
    exit('This order does not belong to your account.');
}

$statuses = vestra_read_json('order_statuses.json');
$status   = $statuses[$ref]['status'] ?? 'pending';
/* Admin's own vlang cookie must never leak into a PDF they pull for someone else's
   order — same "English only in the admin panel" rule as the status picker. */
$label    = vestra_order_in_review($ref, $status) ? ($isAdmin ? 'In review' : t('In review')) : vestra_order_status_label($status, $isAdmin);

$pdf = vestra_render_order_pdf($order, $lines, $label);

/* Same delivery discipline as the invoice endpoint: a stray byte in front of the header
   makes the file unopenable, and gzip on a Content-Length we already computed truncates it. */
while (ob_get_level() > 0) ob_end_clean();
if (function_exists('ini_set')) @ini_set('zlib.output_compression', 'Off');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="VESTRA-order-'.$ref.'.pdf"');
header('Content-Length: '.strlen($pdf));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, no-store');
echo $pdf;
