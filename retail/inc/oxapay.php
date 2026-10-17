<?php
/**
 * OxaPay — kripto ödeme (SDK'sız, doğrudan REST)
 * ==============================================
 * Sarvesto'nun KENDİ sipariş sistemine bağlı: fatura tutarı siparişin EUR
 * toplamından, onay webhook ile vr_order_mark_paid'e gidiyor (Stripe ile aynı
 * fonksiyon) — stok düşer, sipariş admin panelde görünür, marka e-postası gider.
 *
 * Anahtar KODA YAZILMAZ. Sırayla: ortam değişkeni OXAPAY_MERCHANT_KEY >
 * data/oxapay_settings.json ("merchant_key"). Yoksa kripto ödeme kibarca
 * kapalı kalır (vr_oxapay_ready() false), site çökmez.
 *
 * Callback ve return adresleri OxaPay panelinde sabit:
 *   callback: https://sarvesto.com/odeme/oxapay-callback.php
 *   return:   https://sarvesto.com/odeme/tesekkurler.php
 */

declare(strict_types=1);

require_once __DIR__ . '/boot.php';

const VR_OXAPAY_API = 'https://api.oxapay.com/v1/';

function vr_oxapay_settings(): array
{
    static $s = null;
    if ($s !== null) return $s;

    $file = vr_data_dir() . '/oxapay_settings.json';
    $json = is_readable($file) ? (json_decode((string)file_get_contents($file), true) ?: []) : [];
    if (!is_array($json)) $json = [];

    $envKey = getenv('OXAPAY_MERCHANT_KEY');
    $key = (is_string($envKey) && $envKey !== '') ? trim($envKey) : trim((string)($json['merchant_key'] ?? ''));

    // Yer tutucu anahtar yapılandırılmamış sayılır.
    if ($key === '' || str_contains($key, 'BURAYA') || str_contains($key, 'MERCHANT_API_KEY')) $key = '';

    $envSb = getenv('OXAPAY_SANDBOX');
    $sandbox = $envSb !== false ? in_array(strtolower((string)$envSb), ['1', 'true', 'yes'], true)
                                : (bool)($json['sandbox'] ?? false);

    $s = [
        'merchant_key' => $key,
        'configured'   => $key !== '',
        'sandbox'      => $sandbox,
        'lifetime'     => max(15, min(2880, (int)($json['lifetime'] ?? 60))),
    ];
    return $s;
}

function vr_oxapay_ready(): bool
{
    return vr_oxapay_settings()['configured'];
}

/** Kripto ödeme müşteriye sunulsun mu (anahtar var ve kapatılmamış). */
function vr_oxapay_enabled(): bool
{
    if (!vr_oxapay_ready()) return false;
    // data/retail-settings.json ile elle kapatılabilsin.
    return (bool)vr_config('crypto_enabled', true);
}

/**
 * Sipariş için OxaPay faturası oluştur. Dönüş: [ok, data|hata].
 * data: ['payment_url'=>…, 'track_id'=>…]
 */
function vr_oxapay_create_invoice(array $order): array
{
    $cfg = vr_oxapay_settings();
    if (!$cfg['configured']) return [false, 'oxapay_not_configured'];

    $origin = vr_canonical_origin();
    $amount = round((int)$order['total_cents'] / 100, 2);
    if ($amount <= 0) return [false, 'amount_zero'];

    $payload = [
        'amount'              => $amount,
        'currency'            => (string)vr_config('currency', 'EUR'),
        'lifetime'            => $cfg['lifetime'],
        'fee_paid_by_payer'   => 0,
        'under_paid_coverage' => 0,
        'callback_url'        => $origin . '/odeme/oxapay-callback.php',
        'return_url'          => $origin . '/odeme/tesekkurler.php?order=' . urlencode((string)$order['id']) . '&t=' . urlencode((string)$order['token']),
        'email'               => (string)($order['customer']['email'] ?? ''),
        'order_id'            => (string)$order['id'],
        'description'         => (string)vr_config('brand') . ' ' . (string)$order['number'],
        'sandbox'             => $cfg['sandbox'],
    ];

    $ch = curl_init(VR_OXAPAY_API . 'payment/invoice');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'merchant_api_key: ' . $cfg['merchant_key'],
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);
    $raw  = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cErr = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        vr_log('oxapay_network', ['err' => $cErr]);
        return [false, 'network: ' . $cErr];
    }
    $res = json_decode((string)$raw, true);
    $url = $res['data']['payment_url'] ?? null;
    $tid = $res['data']['track_id'] ?? null;

    if ($http !== 200 || !$url) {
        vr_log('oxapay_invoice_failed', ['http' => $http, 'resp' => mb_substr((string)$raw, 0, 300)]);
        return [false, (string)($res['message'] ?? ('HTTP ' . $http))];
    }
    return [true, ['payment_url' => (string)$url, 'track_id' => (string)$tid]];
}

/**
 * Webhook imzasını doğrula. OxaPay: HMAC header = hash_hmac('sha512',
 * ham_gövde, merchant_api_key). Sabit zamanlı karşılaştırma.
 */
function vr_oxapay_verify_callback(string $raw, string $sig): bool
{
    $cfg = vr_oxapay_settings();
    if (!$cfg['configured'] || $raw === '' || $sig === '') return false;
    $calc = hash_hmac('sha512', $raw, $cfg['merchant_key']);
    return hash_equals($calc, $sig);
}

/** OxaPay durumu → Sarvesto sipariş eylemi. */
function vr_oxapay_status_map(string $status): string
{
    return match (strtolower($status)) {
        'paid'                 => 'paid',
        'paying', 'confirming' => 'confirming',
        'expired'              => 'expired',
        'refunding', 'refunded' => 'refunded',
        default                => '',
    };
}

/**
 * OxaPay'den fatura durumu (v1: GET payment/{track_id}). Dönüş: [ok, data|hata].
 * data: track_id, status (küçük harf: waiting/paying/confirming/paid/expired/…),
 * amount, currency, order_id, txs[], expired_at … (canlıda doğrulandı).
 */
function vr_oxapay_fetch(string $trackId): array
{
    $cfg = vr_oxapay_settings();
    if (!$cfg['configured']) return [false, 'oxapay_not_configured'];
    if ($trackId === '')     return [false, 'no_track_id'];

    $ch = curl_init(VR_OXAPAY_API . 'payment/' . rawurlencode($trackId));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER     => ['merchant_api_key: ' . $cfg['merchant_key']],
    ]);
    $raw  = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cErr = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        vr_log('oxapay_fetch_network', ['err' => $cErr]);
        return [false, 'network: ' . $cErr];
    }
    $res = json_decode((string)$raw, true);
    $d   = (is_array($res) && is_array($res['data'] ?? null)) ? $res['data'] : null;
    if ($http !== 200 || $d === null) {
        vr_log('oxapay_fetch_failed', ['http' => $http, 'resp' => mb_substr((string)$raw, 0, 300)]);
        return [false, (string)($res['message'] ?? ('HTTP ' . $http))];
    }
    return [true, $d];
}

/**
 * OxaPay durumunu siparişe uygula. Webhook (odeme/oxapay-callback.php) ve
 * sorgu (vr_oxapay_sync) AYNI yoldan geçer: paid → vr_order_mark_paid (stok,
 * los, kupon, satıcı payı, mailler; idempotent), expired → failed, refunded →
 * stok iadesi. Çağıranın inc/orders.php'yi yüklemiş olması gerekir.
 * Dönüş: eşlenmiş durum ('' = sipariş durumunu değiştirmeyen ara durum).
 */
function vr_oxapay_apply(array $order, array $d): string
{
    $orderId = (string)($order['id'] ?? '');
    $status  = (string)($d['status'] ?? '');
    $new     = vr_oxapay_status_map($status);

    // Kripto işlem özeti (varsa) + track id — her durumda kaydet.
    $crypto = '';
    if (!empty($d['txs'][0]) && is_array($d['txs'][0])) {
        $t = $d['txs'][0];
        $crypto = trim((string)($t['amount'] ?? '') . ' ' . (string)($t['currency'] ?? '') . ' ' . (string)($t['network'] ?? ''));
    }
    vr_order_update($orderId, ['payment_method' => 'crypto', 'oxapay' => array_filter([
        'track_id'    => (string)($d['track_id'] ?? ($order['oxapay']['track_id'] ?? '')),
        'status'      => $status,
        'paid_crypto' => $crypto,
        'updated_at'  => time(),
    ])]);

    $cur = (string)($order['status'] ?? '');
    if ($new === 'paid') {
        vr_order_mark_paid($orderId);                  // stok/los/kupon/payout/mail (idempotent)
    } elseif ($new === 'expired') {
        if ($cur !== 'paid') vr_order_mark_failed($orderId, 'oxapay_expired');
    } elseif ($new === 'refunded') {
        if ($cur === 'paid') {
            vr_order_update($orderId, ['status' => 'refunded', 'refunded_at' => time()]);
            vr_stock_restore((array)($order['lines'] ?? []));
        }
    }
    // 'confirming' (paying/confirming): yalnız oxapay durumu güncellendi; sipariş pending kalır.
    return $new;
}

/**
 * Bekleyen kripto siparişini OxaPay ile eşitle — webhook gecikir ya da
 * engellenirse müşteri dönüş/sipariş sayfasında yine de doğru durumu görsün.
 * $minInterval: aynı siparişe en az bu kadar saniyede bir sorulsun (0 = hemen).
 * Dönüş: güncel sipariş ya da null (eşitleme gerekmedi / yapılamadı).
 */
function vr_oxapay_sync(array $order, int $minInterval = 20): ?array
{
    if ((string)($order['payment_method'] ?? '') !== 'crypto') return null;
    if ((string)($order['status'] ?? '') !== 'pending')        return null;
    $tid = (string)($order['oxapay']['track_id'] ?? '');
    if ($tid === '' || !vr_oxapay_ready())                      return null;

    $last = (int)($order['oxapay']['synced_at'] ?? 0);
    if ($minInterval > 0 && $last > 0 && time() - $last < $minInterval) return null;
    vr_order_update((string)$order['id'], ['oxapay' => ['synced_at' => time()]]);

    [$ok, $d] = vr_oxapay_fetch($tid);
    if (!$ok) return null;
    try {
        vr_oxapay_apply($order, $d);
    } catch (Throwable $e) {
        vr_log('oxapay_sync_exception', ['order' => (string)$order['id'], 'err' => $e->getMessage()]);
        return null;
    }
    return vr_order_get((string)$order['id']);
}
