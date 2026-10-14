<?php
/**
 * VESTRA — POST /orders/{ref}/pay/crypto  (oxapay.pay)
 * Yalnız sipariş sahibi (giriş yapmış, e-postası siparişle aynı). Ödenmişse 400.
 * Yaptırımlı ülke/bölge ise 403 (kasa bu seçeneği zaten çizmez; karar burada).
 * OxaPay faturası oluşturur, payment_method=oxapay / payment_status=pending / track_id yazar,
 * payment_url'e yönlendirir. Açık (süresi dolmamış) bir fatura varsa ona yönlendirir — ikinci tık
 * ikinci fatura açmaz.
 */
require __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/auth.php';
require_once __DIR__.'/inc/orders.php';
require_once __DIR__.'/inc/invoice.php';
require_once __DIR__.'/inc/addresses.php';
require_once __DIR__.'/inc/oxapay.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$ref = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($_GET['ref'] ?? ($_POST['ref'] ?? '')));
$fail = function (int $code, string $msg, string $ref = '') {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg.($ref !== '' ? "\n\n/order-confirm?ref=".$ref : ''), "\n";
    exit;
};
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /order-confirm?ref='.urlencode($ref), true, 303); exit; }
if ($ref === '') $fail(404, 'Order not found.');
if (!oxapay_enabled()) $fail(400, 'Crypto payment is not available right now.', $ref);

$row = oxapay_order_row($ref);
if (!$row) $fail(404, 'Order not found.');
$me = auth_user();
if (!$me || strtolower(trim((string)($me['email'] ?? ''))) !== strtolower(trim((string)($row['email'] ?? '')))) $fail(403, 'Only the buyer who placed this order can pay it.', $ref);

$entry = (array)((vestra_read_json('order_statuses.json'))[$ref] ?? ['status' => 'pending']);
if (($entry['status'] ?? '') === 'cancelled') $fail(400, 'This order is cancelled.', $ref);
if (vestra_order_payment_settled($ref, $entry)['settled'] || ($entry['payment_status'] ?? '') === 'paid') $fail(400, 'This order is already paid.', $ref);
if (($why = vestra_order_crypto_block($row)) !== '') {
    error_log('[VESTRA OxaPay] crypto refused '.$ref.' ('.$why.')');
    header('Location: /order-confirm?ref='.urlencode($ref).'&crypto=blocked', true, 303); exit;
}

/* Açık fatura varsa yeniden kullan (OxaPay lifetime dakika). */
$cfg = oxapay_cfg();
$url = (string)($entry['oxapay_payment_url'] ?? '');
$made = strtotime((string)($entry['oxapay_created_at'] ?? '')) ?: 0;
$st = (string)($entry['payment_status'] ?? '');
if ($url !== '' && ($entry['payment_method'] ?? '') === 'oxapay' && in_array($st, ['pending', 'confirming'], true)
    && $made > 0 && (time() - $made) < ($cfg['lifetime'] - 2) * 60) {
    header('Location: '.$url, true, 303); exit;
}

$res = oxapay_create_invoice($row);
if (!$res['ok']) {
    header('Location: /order-confirm?ref='.urlencode($ref).'&crypto='.($res['error'] === 'sanctioned' ? 'blocked' : 'error'), true, 303); exit;
}
header('Location: '.$res['payment_url'], true, 303); exit;
