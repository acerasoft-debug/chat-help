<?php
/**
 * OxaPay webhook → Sarvesto sipariş sistemi.
 * OxaPay ödeme durumu değiştikçe bu adrese POST atar. İmza HMAC-SHA512 ile
 * doğrulanır; "paid" olunca Stripe ile AYNI fonksiyon (vr_order_mark_paid)
 * çalışır: stok düşer, los kapanır, kupon sayılır, satıcı payı, mailler.
 * OxaPay panelinde callback: https://sarvesto.com/odeme/oxapay-callback.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/orders.php';
require_once __DIR__ . '/../inc/oxapay.php';

header('Content-Type: text/plain; charset=utf-8');

$raw = (string)file_get_contents('php://input');
$sig = (string)($_SERVER['HTTP_HMAC'] ?? '');

if (!vr_oxapay_verify_callback($raw, $sig)) {
    http_response_code(400);
    vr_log('oxapay_bad_signature', ['len' => strlen($raw)]);
    echo 'invalid signature';
    exit;
}

$d = json_decode($raw, true);
if (!is_array($d)) { http_response_code(400); echo 'bad json'; exit; }

$orderId = (string)($d['order_id'] ?? '');
$status  = (string)($d['status'] ?? '');
$new     = vr_oxapay_status_map($status);
$order   = $orderId !== '' ? vr_order_get($orderId) : null;

if ($order === null) {
    // Sipariş yok: 200 dön (tekrar denemek düzeltmez), izi bırak.
    vr_log('oxapay_order_missing', ['order' => $orderId, 'status' => $status]);
    echo 'order not found';
    exit;
}

// Durumu uygula — sorgu yedeğiyle (vr_oxapay_sync) AYNI yol: paid → ödendi,
// expired → failed, refunded → stok iadesi; ara durumlar yalnız kaydedilir.
try {
    vr_oxapay_apply($order, $d);
    vr_log('oxapay_callback', ['order' => $orderId, 'status' => $status, 'mapped' => $new]);
} catch (Throwable $e) {
    http_response_code(500);                           // OxaPay tekrar dener
    vr_log('oxapay_callback_exception', ['order' => $orderId, 'err' => $e->getMessage()]);
    echo 'error';
    exit;
}

http_response_code(200);
echo 'ok';                                             // OxaPay 200 bekler
