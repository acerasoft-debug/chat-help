<?php
/**
 * Stripe webhook
 * ==============
 * Stripe Dashboard → Developers → Webhooks → uç nokta:
 *     https://<host>/<kurulum>/api/webhook.php
 * Dinlenecek olaylar:
 *     checkout.session.completed
 *     checkout.session.async_payment_succeeded
 *     checkout.session.async_payment_failed
 *     checkout.session.expired
 *     charge.refunded
 *     account.updated                (Connect — satıcı doğrulaması bitti)
 *
 * Kurallar:
 *  • İmza doğrulanmadan HİÇBİR şey yapılmaz (vr_stripe_verify_webhook).
 *  • İşlenen olay id'leri kaydedilir — Stripe aynı olayı tekrar gönderirse
 *    ikinci kez iş yapılmaz. Zaten vr_order_mark_paid() de idempotent, yani
 *    iki katman koruma var.
 *  • Hata durumunda 500 döndürüyoruz: Stripe tekrar denesin. Yalnızca
 *    "bizim ilgilenmediğimiz olay" durumunda 200 dönüp sessizce geçiyoruz.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/boot.php';
require_once __DIR__ . '/../inc/orders.php';
require_once __DIR__ . '/../inc/accounts.php';

header('Content-Type: text/plain; charset=utf-8');

$cfg     = vr_stripe_settings();
$payload = (string)file_get_contents('php://input');
$sig     = (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

if (!$cfg['webhook_ready']) {
    http_response_code(500);
    vr_log('webhook_no_secret');
    echo "webhook secret missing";
    exit;
}

if (!vr_stripe_verify_webhook($payload, $sig, (string)$cfg['webhook_secret'])) {
    http_response_code(400);
    vr_log('webhook_bad_signature', ['len' => strlen($payload)]);
    echo "invalid signature";
    exit;
}

$event = json_decode($payload, true);
if (!is_array($event) || empty($event['id']) || empty($event['type'])) {
    http_response_code(400);
    echo "malformed event";
    exit;
}

$eventId = (string)$event['id'];
$type    = (string)$event['type'];
$obj     = (array)($event['data']['object'] ?? []);

// -------------------------------------------------------------- idempotency
$fresh = false;
vr_store_mutate('retail-webhook-seen.json', static function ($seen) use ($eventId, &$fresh) {
    if (!is_array($seen)) $seen = [];
    if (isset($seen[$eventId])) return null;            // daha önce işlendi

    // Dosya sonsuza büyümesin: 30 günden eski kayıtları at.
    $cut = time() - 30 * 86400;
    foreach ($seen as $k => $ts) {
        if ((int)$ts < $cut) unset($seen[$k]);
    }
    $seen[$eventId] = time();
    $fresh = true;
    return $seen;
});

if (!$fresh) {
    echo "duplicate ignored";
    exit;
}

// ------------------------------------------------------------------ yönlendir
try {
    switch ($type) {

        // ---------------------------------------------- ödeme tamamlandı
        case 'checkout.session.completed':
        case 'checkout.session.async_payment_succeeded':
            $orderId = (string)($obj['metadata']['order_id'] ?? $obj['client_reference_id'] ?? '');
            $order   = $orderId !== '' ? vr_order_get($orderId) : null;

            if ($order === null) {
                // Sipariş bulunamadı: kaydı bırak, Stripe'a 200 dön — tekrar
                // denemek düzeltmeyecek, ama izini kaybetmeyelim.
                vr_log('webhook_order_missing', ['event' => $eventId, 'order' => $orderId]);
                echo "order not found";
                break;
            }

            // Klarna/SEPA gibi gecikmeli yöntemlerde ödeme henüz tamam olmayabilir.
            $payStatus = (string)($obj['payment_status'] ?? '');
            if ($payStatus !== 'paid' && $payStatus !== 'no_payment_required') {
                vr_log('webhook_not_paid_yet', ['order' => $orderId, 'status' => $payStatus]);
                echo "awaiting payment";
                break;
            }

            $piId   = (string)($obj['payment_intent'] ?? '');
            $charge = '';
            if ($piId !== '' && vr_stripe_ready()) {
                // Ayrı transferler için charge id şart (source_transaction).
                $r = vr_stripe_request('GET', 'payment_intents/' . urlencode($piId));
                if ($r['ok']) {
                    $charge = (string)($r['data']['latest_charge'] ?? '');
                    if ($charge === '' && !empty($r['data']['charges']['data'][0]['id'])) {
                        $charge = (string)$r['data']['charges']['data'][0]['id'];
                    }
                }
            }

            $cd = (array)($obj['customer_details'] ?? []);
            $sd = (array)($obj['shipping_details'] ?? $obj['collected_information']['shipping_details'] ?? []);
            $ad = (array)($sd['address'] ?? $cd['address'] ?? []);

            vr_order_mark_paid($orderId, [
                'session_id'     => (string)($obj['id'] ?? ''),
                'payment_intent' => $piId,
                'charge_id'      => $charge,
                'customer'       => [
                    'email'   => (string)($cd['email'] ?? ''),
                    'name'    => (string)($sd['name'] ?? $cd['name'] ?? ''),
                    'phone'   => (string)($cd['phone'] ?? ''),
                    'address' => array_filter([
                        'name'        => (string)($sd['name'] ?? ''),
                        'line1'       => (string)($ad['line1'] ?? ''),
                        'line2'       => (string)($ad['line2'] ?? ''),
                        'postal_code' => (string)($ad['postal_code'] ?? ''),
                        'city'        => (string)($ad['city'] ?? ''),
                        'state'       => (string)($ad['state'] ?? ''),
                        'country'     => (string)($ad['country'] ?? ''),
                    ]),
                ],
            ]);
            echo "ok";
            break;

        // ------------------------------------------- ödeme düştü / süre bitti
        case 'checkout.session.async_payment_failed':
        case 'checkout.session.expired':
            $orderId = (string)($obj['metadata']['order_id'] ?? $obj['client_reference_id'] ?? '');
            if ($orderId !== '') {
                // Los rezervasyonları burada serbest kalıyor: süresi geçmiş bir
                // ödeme yüzünden parça sonsuza kadar kilitli kalmasın.
                vr_order_mark_failed($orderId, $type);
            }
            echo "ok";
            break;

        // ------------------------------------------------------------- iade
        case 'charge.refunded':
            $piId = (string)($obj['payment_intent'] ?? '');
            foreach (vr_orders() as $o) {
                if ((string)($o['stripe']['payment_intent'] ?? '') !== $piId || $piId === '') continue;

                $full = (int)($obj['amount_refunded'] ?? 0) >= (int)($obj['amount'] ?? 0);
                vr_order_update((string)$o['id'], [
                    'refund' => [
                        'amount_cents' => (int)($obj['amount_refunded'] ?? 0),
                        'full'         => $full,
                        'at'           => time(),
                    ],
                    'status' => $full ? 'refunded' : (string)$o['status'],
                ]);

                // Tam iade → stok geri gelsin (parça tekrar satılabilir).
                if ($full) vr_stock_restore((array)$o['lines']);
                break;
            }
            echo "ok";
            break;

        // ------------------------------------- Connect: satıcı durumu değişti
        case 'account.updated':
            $acct = (string)($obj['id'] ?? '');
            if ($acct === '') { echo "no account"; break; }

            foreach (vr_sellers() as $s) {
                if ((string)($s['stripe_account_id'] ?? '') !== $acct) continue;

                // Canlı taraftaki refresh-connect-status.yml ile aynı ölçüt:
                // satışa/aktarıma uygunluk = charges_enabled.
                vr_seller_update((string)$s['id'], [
                    'charges_enabled'    => !empty($obj['charges_enabled']),
                    'payouts_enabled'    => !empty($obj['payouts_enabled']),
                    'details_submitted'  => !empty($obj['details_submitted']),
                    'connect_checked_at' => time(),
                ]);
                break;
            }
            echo "ok";
            break;

        default:
            // Abone olmadığımız olaylar: 200 dön, Stripe tekrar denemesin.
            echo "ignored: " . $type;
    }
} catch (Throwable $e) {
    // İşleme sırasında hata → 500. Stripe tekrar dener, idempotency kaydını da
    // geri alıyoruz ki tekrar denemesi gerçekten işlenebilsin.
    vr_store_mutate('retail-webhook-seen.json', static function ($seen) use ($eventId) {
        if (!is_array($seen) || !isset($seen[$eventId])) return null;
        unset($seen[$eventId]);
        return $seen;
    });

    http_response_code(500);
    vr_log('webhook_exception', ['event' => $eventId, 'type' => $type, 'err' => $e->getMessage()]);
    echo "error";
}
