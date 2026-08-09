<?php
/**
 * Stripe — SDK'sız, doğrudan REST
 * ===============================
 * Composer bağımlılığı yok (canlı site de öyle çalışıyor). Tek dosyada:
 * istemci, Checkout, Connect (hesap + onboarding linki), Transfer, webhook
 * imza doğrulaması.
 *
 * Pazaryeri para akışı — iki model, duruma göre otomatik seçilir:
 *
 *  A) TEK satıcı + satıcı Connect'te hazır  → "destination charge"
 *     Ödeme platform hesabında oluşur, application_fee_amount kadarı bizde
 *     kalır, gerisi anında satıcının hesabına aktarılır.
 *
 *  B) ÇOK satıcı, veya satıcı henüz hazır değil → "separate charges & transfers"
 *     Ödeme tamamen platformda toplanır; webhook, charge'ı kaynak göstererek
 *     her satıcıya AYRI Transfer açar. Checkout bir oturumda tek destination
 *     desteklediği için çok satıcılı sepetin tek doğru yolu budur.
 *
 * Hazır olmayan satıcının payı platformda bekler ve siparişte
 * payout_pending=true olarak işaretlenir — canlı taraftaki not ile aynı mantık:
 * "hala MAXSALES platform hesabindan gececek".
 */

declare(strict_types=1);

require_once __DIR__ . '/boot.php';

const VR_STRIPE_API      = 'https://api.stripe.com/v1/';
const VR_STRIPE_VERSION  = '2024-06-20';   // sabit sürüm: Stripe güncellemesi akışı bozmasın

/** Stripe kullanılabilir mi? */
function vr_stripe_ready(): bool
{
    return vr_stripe_settings()['configured'];
}

/**
 * İç içe diziyi Stripe'ın beklediği form gösterimine çevir:
 *   ['a' => ['b' => 1], 'list' => [x, y]]  →  a[b]=1&list[0]=x&list[1]=y
 */
function vr_stripe_form(array $params, string $prefix = ''): array
{
    $out = [];
    foreach ($params as $k => $v) {
        if ($v === null) continue;                       // null alanları hiç göndermiyoruz
        $key = $prefix === '' ? (string)$k : $prefix . '[' . $k . ']';

        if (is_array($v)) {
            $out += vr_stripe_form($v, $key);
        } elseif (is_bool($v)) {
            $out[$key] = $v ? 'true' : 'false';
        } else {
            $out[$key] = (string)$v;
        }
    }
    return $out;
}

/**
 * Stripe API isteği.
 * $opts: idempotency_key, stripe_account (Connect hesabı adına), timeout
 * Dönüş: ['ok'=>bool, 'status'=>int, 'data'=>array, 'error'=>string]
 */
function vr_stripe_request(string $method, string $path, array $params = [], array $opts = []): array
{
    $cfg = vr_stripe_settings();
    if (!$cfg['configured']) {
        return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'stripe_not_configured'];
    }

    $method = strtoupper($method);
    $url    = VR_STRIPE_API . ltrim($path, '/');
    $form   = vr_stripe_form($params);
    $body   = http_build_query($form, '', '&', PHP_QUERY_RFC3986);

    $headers = [
        'Authorization: Bearer ' . $cfg['secret_key'],
        'Stripe-Version: ' . VR_STRIPE_VERSION,
        'Content-Type: application/x-www-form-urlencoded',
    ];
    if (!empty($opts['idempotency_key'])) {
        // Aynı anahtarla ikinci istek yeni bir ücret oluşturmaz — ağ hatası
        // sonrası tekrar denemek güvenli olsun diye her yazma isteğinde var.
        $headers[] = 'Idempotency-Key: ' . substr((string)$opts['idempotency_key'], 0, 255);
    }
    if (!empty($opts['stripe_account'])) {
        $headers[] = 'Stripe-Account: ' . $opts['stripe_account'];
    }

    if ($method === 'GET' && $body !== '') {
        $url .= (str_contains($url, '?') ? '&' : '?') . $body;
        $body = '';
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => (int)($opts['timeout'] ?? 30),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    if ($method !== 'GET' && $body !== '') curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

    $raw    = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cErr   = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        vr_stripe_log($method . ' ' . $path, 0, ['curl' => $cErr]);
        return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'network: ' . $cErr];
    }

    $data = json_decode((string)$raw, true);
    if (!is_array($data)) $data = [];

    if ($status < 200 || $status >= 300) {
        $msg = (string)($data['error']['message'] ?? ('HTTP ' . $status));
        vr_stripe_log($method . ' ' . $path, $status, ['error' => $data['error'] ?? $raw]);
        return ['ok' => false, 'status' => $status, 'data' => $data, 'error' => $msg];
    }

    return ['ok' => true, 'status' => $status, 'data' => $data, 'error' => ''];
}

/** Hata/olay kaydı — anahtarlar ASLA yazılmaz, sadece uç nokta ve mesaj. */
function vr_stripe_log(string $what, int $status, array $ctx = []): void
{
    $line = [
        'at'     => gmdate('c'),
        'what'   => $what,
        'status' => $status,
        'ctx'    => $ctx,
    ];
    $f = vr_data_dir() . '/retail-stripe.log';
    @file_put_contents($f, json_encode($line, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    @chmod($f, 0640);
}

// ------------------------------------------------------------------ Checkout
/**
 * Checkout Session oluştur.
 *
 * $order: orders.php'nin ürettiği sipariş dizisi (satırlar, kargo, toplamlar).
 * Dönüş: [ok, session_dizisi_veya_hata_metni]
 */
/**
 * Sipariş için tek kullanımlık Stripe kuponu. Kimlik olarak sipariş id'si
 * kullanılıyor: aynı sipariş için tekrar denenirse Stripe aynı kuponu döndürür
 * (idempotency), ikinci bir indirim oluşmaz.
 *
 * Dönüş: [ok, kupon_id|hata]
 */
function vr_stripe_coupon(int $amountCents, string $code, string $orderId): array
{
    $cur = strtolower((string)vr_config('currency', 'eur'));
    $id  = 'ord-' . preg_replace('/[^a-zA-Z0-9-]/', '', $orderId);

    $res = vr_stripe_request('POST', 'coupons', [
        'id'              => $id,
        'amount_off'      => $amountCents,
        'currency'        => $cur,
        'duration'        => 'once',
        'max_redemptions' => 1,
        'name'            => $code !== '' ? mb_substr($code, 0, 40) : 'Gutschein',
        'metadata'        => ['order_id' => $orderId, 'voucher' => mb_substr($code, 0, 40)],
    ], ['idempotency_key' => 'coupon-' . $id]);

    if ($res['ok']) return [true, (string)($res['data']['id'] ?? $id)];

    // Zaten varsa (aynı siparişin ikinci denemesi) onu kullan.
    $g = vr_stripe_request('GET', 'coupons/' . urlencode($id), []);
    if ($g['ok'] && !empty($g['data']['valid'])) return [true, $id];

    return [false, (string)($res['error'] ?: 'coupon_failed')];
}

function vr_stripe_create_checkout(array $order): array
{
    $cur = strtolower((string)vr_config('currency', 'eur'));

    $lineItems = [];
    foreach ($order['lines'] as $ln) {
        $name = mb_substr(trim(($ln['brand'] ?? '') . ' ' . ($ln['name'] ?? '')), 0, 250);
        $descParts = [];
        if (!empty($ln['size']) && $ln['size'] !== 'ONE') $descParts[] = t('size') . ' ' . $ln['size'];
        if (!empty($ln['sku']))  $descParts[] = (string)$ln['sku'];
        if (!empty($ln['vault'])) $descParts[] = 'VAULT';

        $product = ['name' => $name !== '' ? $name : 'Article'];
        if ($descParts) $product['description'] = mb_substr(implode(' · ', $descParts), 0, 250);

        // Görseli Stripe kendi sunucusundan çeker: mutlak ve halka açık olmalı.
        // Ölçeklenmiş kopyayı veriyoruz — Stripe ödeme sayfasında kareyi
        // küçük gösteriyor, oraya 3 MB'lık orijinali yollamanın anlamı yok
        // ve büyük dosyada çekme zaman aşımına düşebiliyor.
        if (!empty($ln['image'])) {
            $img = (string)$ln['image'];
            if (!str_starts_with($img, 'http') && function_exists('vr_img')) $img = vr_img($img, 600);
            $product['images'] = [str_starts_with($img, 'http') ? $img : vr_origin() . $img];
        }

        $lineItems[] = [
            'quantity'   => (int)$ln['qty'],
            'price_data' => [
                'currency'     => $cur,
                'unit_amount'  => (int)$ln['unit_cents'],
                'product_data' => $product,
                // Fiyatlar brüt (KDV dahil) — Stripe Tax kapalıyken vergiyi
                // ayrıştırmıyoruz, faturada biz gösteriyoruz.
                'tax_behavior' => vr_config('stripe_tax') ? 'inclusive' : null,
            ],
        ];
    }

    $params = [
        'mode'       => 'payment',
        'line_items' => $lineItems,
        'locale'     => vr_stripe_locale(),
        'client_reference_id' => $order['id'],
        'success_url' => vr_abs('order.php', ['id' => $order['id'], 'token' => $order['token']]) . '&paid=1',
        'cancel_url'  => vr_abs('cart.php', ['cancelled' => 1]),
        // Ödeme sayfası açık kalırken sepet/Vault rezervasyonu da bekliyor;
        // ikisi aynı süreyi paylaşsın diye oturumun ömrünü sınırlıyoruz.
        'expires_at' => time() + 30 * 60,
        'billing_address_collection' => 'required',
        'metadata' => [
            'order_id'    => $order['id'],
            'order_token' => $order['token'],
            'sellers'     => implode(',', array_slice(array_keys($order['by_seller']), 0, 10)),
        ],
        'payment_intent_data' => [
            'transfer_group' => $order['id'],
            'metadata'       => ['order_id' => $order['id']],
            'description'    => vr_config('brand') . ' ' . $order['number'],
        ],
    ];

    // ---- kargo: ülkeyi kendi kasamızda soruyoruz, tutarı burada sabitliyoruz
    if (!empty($order['ship_country'])) {
        $params['shipping_address_collection'] = ['allowed_countries' => [strtoupper((string)$order['ship_country'])]];
        $params['shipping_options'] = [[
            'shipping_rate_data' => [
                'type'         => 'fixed_amount',
                'display_name' => (string)$order['ship_label'],
                'fixed_amount' => ['amount' => (int)$order['ship_cents'], 'currency' => $cur],
                'delivery_estimate' => null,
            ],
        ]];
    }

    if (vr_config('stripe_tax')) {
        $params['automatic_tax'] = ['enabled' => true];
    }

    /**
     * ---- Gutschein
     * Stripe kabul etmediği için satır tutarlarını elle kırpmıyoruz: bir kupon
     * nesnesi oluşturup Checkout'a veriyoruz. Böylece indirim Stripe'ın kendi
     * makbuzunda ve panelinde ayrı bir satır olarak görünüyor — muhasebe
     * tarafında "neden eksik tahsilat" sorusu doğmuyor.
     *
     * Kupon TUTAR olarak oluşturuluyor (yüzde değil): indirimi zaten biz
     * hesapladık ve komisyonla sınırladık; yüzde verseydik Stripe kargoyu da
     * kapsayabilir ve iki taraf farklı rakam bulurdu.
     *
     * Kupon oluşturulamazsa (ağ/anahtar sorunu) ödeme indirimsiz TAM tutardan
     * geçmesin diye işlemi durduruyoruz — müşteriden fazla tahsilat yapmaktansa
     * hata göstermek doğru.
     */
    $discount = (int)($order['discount_cents'] ?? 0);
    if ($discount > 0) {
        [$cok, $coupon] = vr_stripe_coupon($discount, (string)($order['voucher_code'] ?? ''), $order['id']);
        if (!$cok) return [false, 'stripe_error', $coupon];
        $params['discounts'] = [['coupon' => $coupon]];
    }

    // ---- Connect: tek satıcı ve hazırsa doğrudan hedefe aktar
    if ($order['payout_mode'] === 'destination') {
        $params['payment_intent_data']['transfer_data'] = ['destination' => $order['destination']];
        if ((int)$order['fee_total_cents'] > 0) {
            $params['payment_intent_data']['application_fee_amount'] = (int)$order['fee_total_cents'];
        }
    }

    $res = vr_stripe_request('POST', 'checkout/sessions', $params, [
        // Sipariş id'si başına tek oturum: kullanıcı "Öde"ye iki kez basarsa
        // iki farklı Checkout açılmaz.
        'idempotency_key' => 'checkout-' . $order['id'],
    ]);

    return $res['ok'] ? [true, $res['data']] : [false, $res['error']];
}

/** Stripe'ın desteklediği locale koduna eşle. */
function vr_stripe_locale(): string
{
    return match (vr_lang()) {
        'de' => 'de', 'fr' => 'fr', 'it' => 'it', 'es' => 'es',
        default => 'en',
    };
}

function vr_stripe_get_session(string $id): ?array
{
    $res = vr_stripe_request('GET', 'checkout/sessions/' . urlencode($id), [
        'expand' => ['payment_intent', 'line_items'],
    ]);
    return $res['ok'] ? $res['data'] : null;
}

// ------------------------------------------------------------------- Connect
/**
 * Satıcı için Connect hesabı (Express). business_type satıcı tipini izler:
 * Privatverkäufer → individual, Händler → company.
 */
function vr_stripe_connect_create(array $seller): array
{
    $type = (string)($seller['seller_type'] ?? 'private');

    $params = [
        'type'    => 'express',
        'country' => (string)($seller['country'] ?? 'DE') ?: 'DE',
        'email'   => (string)($seller['email'] ?? ''),
        'business_type' => $type === 'business' ? 'company' : 'individual',
        'capabilities'  => [
            'card_payments' => ['requested' => true],
            'transfers'     => ['requested' => true],
        ],
        'business_profile' => [
            'product_description' => 'Resale of apparel and accessories via the MAXSALES marketplace',
            'mcc'                 => '5651',   // Family Clothing Stores
            'url'                 => vr_origin(),
        ],
        'metadata' => [
            'vr_seller_id' => (string)($seller['id'] ?? ''),
            'vr_type'      => $type,
        ],
        'settings' => [
            'payouts' => ['schedule' => ['interval' => 'daily', 'delay_days' => 'minimum']],
        ],
    ];
    if ($type === 'business' && trim((string)($seller['company'] ?? '')) !== '') {
        $params['company'] = ['name' => (string)$seller['company']];
    }

    $res = vr_stripe_request('POST', 'accounts', $params, [
        'idempotency_key' => 'acct-' . (string)($seller['id'] ?? bin2hex(random_bytes(8))),
    ]);
    return $res['ok'] ? [true, (string)($res['data']['id'] ?? '')] : [false, $res['error']];
}

/** Onboarding / bilgi güncelleme linki (tek kullanımlık, kısa ömürlü). */
function vr_stripe_account_link(string $accountId, string $returnUrl, string $refreshUrl, string $type = 'account_onboarding'): array
{
    $res = vr_stripe_request('POST', 'account_links', [
        'account'     => $accountId,
        'return_url'  => $returnUrl,
        'refresh_url' => $refreshUrl,
        'type'        => $type,
        'collection_options' => ['fields' => 'eventually_due'],
    ]);
    return $res['ok'] ? [true, (string)($res['data']['url'] ?? '')] : [false, $res['error']];
}

/** Express Dashboard linki (satıcı kendi ödemelerini görsün). */
function vr_stripe_login_link(string $accountId): array
{
    $res = vr_stripe_request('POST', 'accounts/' . urlencode($accountId) . '/login_links');
    return $res['ok'] ? [true, (string)($res['data']['url'] ?? '')] : [false, $res['error']];
}

/**
 * Canlı hesap durumu. Tek ölçüt charges_enabled — canlı taraftaki
 * refresh-connect-status.yml ile bilinçli olarak AYNI kural.
 */
function vr_stripe_connect_status(string $accountId): array
{
    if ($accountId === '') return ['ok' => false, 'charges_enabled' => false, 'payouts_enabled' => false, 'details_submitted' => false];

    $res = vr_stripe_request('GET', 'accounts/' . urlencode($accountId));
    if (!$res['ok']) {
        return ['ok' => false, 'charges_enabled' => false, 'payouts_enabled' => false, 'details_submitted' => false, 'error' => $res['error']];
    }
    $d = $res['data'];
    return [
        'ok'                => true,
        'charges_enabled'   => !empty($d['charges_enabled']),
        'payouts_enabled'   => !empty($d['payouts_enabled']),
        'details_submitted' => !empty($d['details_submitted']),
        'requirements'      => $d['requirements'] ?? [],
    ];
}

/**
 * Satıcının durum önbelleğini tazele (retail-sellers.json). Webhook
 * account.updated ile aynı alanları yazar.
 */
function vr_stripe_refresh_seller(array $seller, bool $live = true): array
{
    $acct = (string)($seller['stripe_account_id'] ?? '');
    if ($acct === '') return $seller;
    if (!$live)       return $seller;

    $st = vr_stripe_connect_status($acct);
    if (!$st['ok']) return $seller;

    $patch = [
        'charges_enabled'    => $st['charges_enabled'],
        'payouts_enabled'    => $st['payouts_enabled'],
        'details_submitted'  => $st['details_submitted'],
        'connect_checked_at' => time(),
    ];
    // Yalnızca gerçekten değiştiyse yaz — gereksiz disk yazması yok.
    $changed = false;
    foreach ($patch as $k => $v) {
        if (($seller[$k] ?? null) !== $v && $k !== 'connect_checked_at') $changed = true;
    }
    if ($changed || (int)($seller['connect_checked_at'] ?? 0) < time() - 3600) {
        require_once __DIR__ . '/accounts.php';
        vr_seller_update((string)$seller['id'], $patch);
    }
    return array_merge($seller, $patch);
}

// ------------------------------------------------------------------ Transfer
/**
 * Ayrı transfer (B modeli). $sourceCharge verilirse fon uygunluğu o ücrete
 * bağlanır — bakiye henüz düşmemişken de transfer açılabilir.
 */
function vr_stripe_transfer(int $amountCents, string $destination, string $transferGroup, ?string $sourceCharge = null, string $idemKey = ''): array
{
    if ($amountCents <= 0 || $destination === '') return [false, 'gecersiz transfer'];

    $params = [
        'amount'         => $amountCents,
        'currency'       => strtolower((string)vr_config('currency', 'eur')),
        'destination'    => $destination,
        'transfer_group' => $transferGroup,
        'metadata'       => ['order_id' => $transferGroup],
    ];
    if ($sourceCharge !== null && $sourceCharge !== '') $params['source_transaction'] = $sourceCharge;

    $res = vr_stripe_request('POST', 'transfers', $params, [
        'idempotency_key' => $idemKey !== '' ? $idemKey : ('tr-' . $transferGroup . '-' . $destination),
    ]);
    return $res['ok'] ? [true, (string)($res['data']['id'] ?? '')] : [false, $res['error']];
}

// ------------------------------------------------------------------- webhook
/**
 * Webhook imzası doğrula (Stripe-Signature: t=…,v1=…).
 * SDK'nın yaptığının aynısı: imzalanan metin "t.payload", HMAC-SHA256, sabit
 * zamanlı karşılaştırma, varsayılan 5 dakika tolerans (replay'e karşı).
 */
function vr_stripe_verify_webhook(string $payload, string $sigHeader, string $secret, int $tolerance = 300): bool
{
    if ($secret === '' || $sigHeader === '') return false;

    $ts = null;
    $v1 = [];
    foreach (explode(',', $sigHeader) as $part) {
        $kv = explode('=', trim($part), 2);
        if (count($kv) !== 2) continue;
        if ($kv[0] === 't')  $ts = (int)$kv[1];
        if ($kv[0] === 'v1') $v1[] = $kv[1];
    }
    if ($ts === null || !$v1) return false;
    if ($tolerance > 0 && abs(time() - $ts) > $tolerance) return false;

    $expected = hash_hmac('sha256', $ts . '.' . $payload, $secret);
    foreach ($v1 as $sig) {
        if (hash_equals($expected, $sig)) return true;
    }
    return false;
}

/** Ödeme sağlayıcı adları — ürün sayfasındaki güven satırı için. */
function vr_payment_methods(): array
{
    return ['Visa', 'Mastercard', 'American Express', 'Apple Pay', 'Google Pay', 'Klarna', 'SEPA'];
}
