<?php
/**
 * VESTRA — POST /oxapay/webhook  (oxapay.webhook)
 * Ham gövde üzerinden HMAC-SHA512 doğrulanır (başlık: HMAC, anahtar: OXAPAY_MERCHANT_API_KEY);
 * geçersizse 400. Tip invoice/white_label değilse 200 'ok'. İşleme inc/oxapay.php'de, idempotent
 * (OxaPay 5 kez tekrar dener). Her zaman 200 'ok' — bir mektup hatası tekrar gönderime yol açmasın.
 * CSRF katmanı yok (düz PHP); güvenlik HMAC'ta. Oturum açılmaz.
 */
require __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/auth.php';
require_once __DIR__.'/inc/orders.php';
require_once __DIR__.'/inc/invoice.php';
require_once __DIR__.'/inc/addresses.php';
require_once __DIR__.'/inc/oxapay.php';

header('Content-Type: text/plain; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo "method\n"; exit; }
$raw  = (string)file_get_contents('php://input');
$hmac = (string)($_SERVER['HTTP_HMAC'] ?? '');
if (!oxapay_verify_webhook($raw, $hmac)) { http_response_code(400); echo "invalid signature\n"; exit; }
$p = json_decode($raw, true);
if (!is_array($p)) { http_response_code(400); echo "invalid body\n"; exit; }
try {
    $r = oxapay_handle_webhook($p);
    error_log('[VESTRA OxaPay] webhook '.(string)($p['status'] ?? '?').' → '.$r['action'].(isset($r['ref']) ? ' '.$r['ref'] : ''));
} catch (\Throwable $e) {
    error_log('[VESTRA OxaPay] webhook error: '.$e->getMessage());
}
http_response_code(200);
echo "ok";
