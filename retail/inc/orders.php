<?php
/**
 * Siparişler + para dağıtımı
 * ==========================
 * Sipariş, ödeme başlamadan ÖNCE "pending" olarak yazılır: Stripe'tan dönen
 * webhook'un tutunacağı bir kayıt olmalı. Ödeme onaylanınca (webhook, gerekirse
 * dönüş sayfası) vr_order_mark_paid() çalışır — ve o fonksiyon idempotenttir,
 * çünkü Stripe aynı olayı birden çok kez gönderebilir.
 *
 * Para dağıtımı:
 *   satıcı payı  = kendi satırlarının brütü − komisyon
 *   platform payı = komisyonlar + KARGO  (kargoyu biz ödüyoruz, geliri bizde
 *                   kalıyor — Verkäuferbedingungen'de açıkça yazıyor)
 *
 * Stok: canlı listings.json'a YAZMIYORUZ. Satılan adetler ayrı bir defterde
 * (retail-stock.json) tutulur, katalog okurken düşülür. Böylece toptan tarafın
 * tek yazar olma kuralı bozulmuyor.
 */

declare(strict_types=1);

require_once __DIR__ . '/boot.php';
require_once __DIR__ . '/cart.php';
require_once __DIR__ . '/stripe.php';
require_once __DIR__ . '/mail.php';

const VR_ORDERS_FILE = 'retail-orders.json';
// VR_STOCK_FILE ve vr_sold_map() catalog.php'de: okuma orada, yazma burada.

// ----------------------------------------------------------------- stok defteri
function vr_stock_consume(array $lines): void
{
    vr_store_mutate(VR_STOCK_FILE, static function ($m) use ($lines) {
        if (!is_array($m)) $m = [];
        foreach ($lines as $ln) {
            if (!empty($ln['vault'])) continue;                 // los ayrı yönetiliyor
            $k = (string)$ln['pid'] . '|' . strtoupper((string)$ln['size']);
            $m[$k] = (int)($m[$k] ?? 0) + (int)$ln['qty'];
        }
        return $m;
    });
}

/** İade/iptalde stoğu geri ver. */
function vr_stock_restore(array $lines): void
{
    vr_store_mutate(VR_STOCK_FILE, static function ($m) use ($lines) {
        if (!is_array($m)) return null;
        foreach ($lines as $ln) {
            if (!empty($ln['vault'])) continue;
            $k = (string)$ln['pid'] . '|' . strtoupper((string)$ln['size']);
            if (!isset($m[$k])) continue;
            $m[$k] = max(0, (int)$m[$k] - (int)$ln['qty']);
            if ($m[$k] === 0) unset($m[$k]);
        }
        return $m;
    });
}

// ---------------------------------------------------------------- oluşturma
function vr_order_number(): string
{
    // VS-YYMM-XXXXX — okunabilir, tarih taşır, tahmin edilemez son blok.
    return sprintf('%s-%s-%s',
        (string)vr_config('order_prefix', 'VS'),
        gmdate('ym'),
        strtoupper(substr(bin2hex(random_bytes(3)), 0, 5))
    );
}

/**
 * Sepetten sipariş taslağı kur (henüz kaydetmez).
 * Dönüş: [ok, siparis_veya_hata_anahtari]
 */
function vr_order_build(string $country): array
{
    $t = vr_cart_totals($country);
    if (!$t['lines']) return [false, 'cart_empty'];

    // ---- satıcı bazında brüt / komisyon
    $bySeller = [];
    foreach ($t['lines'] as $ln) {
        $uid = (string)($ln['seller_uid'] ?: 'vestra');
        if (!isset($bySeller[$uid])) {
            $bySeller[$uid] = [
                'seller_uid' => $ln['seller_uid'],
                'type'       => $ln['seller_type'],
                'name'       => $ln['seller_name'],
                'stripe'     => $ln['seller_stripe'],
                'ready'      => (bool)$ln['seller_ready'],
                'gross'      => 0,
                'commission' => 0,
                'net'        => 0,
            ];
        }
        $bySeller[$uid]['gross'] += (int)$ln['total_cents'];
        // Komisyon satır satır hesaplanır: Vault satırında oran farklı.
        $bySeller[$uid]['commission'] += vr_fee_cents(
            (int)$ln['total_cents'],
            (string)$ln['seller_type'],
            !empty($ln['vault'])
        );
    }

    $commissionTotal = 0;
    foreach ($bySeller as $uid => $row) {
        // Kendi stoğumuzda komisyon diye bir şey yok — para zaten bizde kalıyor.
        if ($uid === 'vestra') {
            $bySeller[$uid]['commission'] = 0;
            $bySeller[$uid]['net']        = 0;
            continue;
        }
        $bySeller[$uid]['net'] = max(0, $row['gross'] - $row['commission']);
        $commissionTotal += $bySeller[$uid]['commission'];
    }

    // ---- ödeme akışı modeli
    $external = array_filter($bySeller, fn($r, $uid) => $uid !== 'vestra', ARRAY_FILTER_USE_BOTH);
    $payoutMode  = 'none';        // her şey bizim stoğumuz → dağıtım yok
    $destination = '';

    if (count($external) === 1) {
        $one = reset($external);
        if ($one['ready'] && $one['stripe'] !== '' && count($bySeller) === 1) {
            // Tek satıcı, hazır ve sepette bizim ürünümüz yok → destination charge
            $payoutMode  = 'destination';
            $destination = (string)$one['stripe'];
        } else {
            $payoutMode = 'separate';
        }
    } elseif (count($external) > 1) {
        $payoutMode = 'separate';
    }

    // ---- Gutschein
    /**
     * İndirimi PLATFORM üstleniyor: satıcının payı hiç değişmiyor, nakit
     * tamamen bizim komisyonumuzdan iniyor. Aksi hâlde kendi kampanyamızın
     * bedelini satıcıya ödetmiş olurduk.
     *
     * Bu yüzden indirim komisyonla sınırlı — vr_voucher_check() zaten bunu
     * denetliyor ve aşan kuponu kabul etmiyor; buradaki min() son emniyet.
     */
    require_once __DIR__ . '/vouchers.php';
    $voucher  = vr_voucher_current();
    $discount = 0;

    if ($voucher !== '') {
        $email = '';
        if (function_exists('vr_current_customer')) {
            $me = vr_current_customer();
            $email = (string)($me['email'] ?? '');
        }
        // Kendi stoğumuzda komisyon yok ama para da bizde kalıyor: orada
        // indirimin üst sınırı satırın kendi tutarı.
        $ownGross = (int)($bySeller['vestra']['gross'] ?? 0);
        $cap = $commissionTotal + $ownGross;

        [$vok, $vcents] = vr_voucher_check($voucher, (int)$t['subtotal'], $cap, $email);
        if ($vok) {
            $discount = min($vcents, $cap);
        } else {
            $voucher = '';                 // geçersizse sessizce düşür
            vr_voucher_clear();
        }
    }

    // destination modelinde Stripe'a bildirdiğimiz application_fee, komisyonun
    // ÜSTÜNE kargoyu da içerir: aksi halde kargo bedeli satıcıya giderdi.
    // İndirim de buradan iniyor — satıcının payı korunuyor.
    $applicationFee = $payoutMode === 'destination'
        ? max(0, $commissionTotal + (int)$t['ship_cents'] - $discount)
        : 0;

    $order = [
        'id'         => 'o-' . bin2hex(random_bytes(10)),
        'token'      => bin2hex(random_bytes(16)),
        'number'     => vr_order_number(),
        'status'     => 'pending',
        'created_at' => time(),
        'lang'       => vr_lang(),
        'currency'   => (string)vr_config('currency', 'EUR'),
        'session_id' => session_id(),

        'lines'          => $t['lines'],
        'subtotal_cents' => (int)$t['subtotal'],
        'ship_cents'     => (int)$t['ship_cents'],
        'ship_label'     => (string)($t['shipping']['label'] ?? ''),
        'ship_zone'      => (string)($t['shipping']['zone'] ?? ''),
        'ship_country'   => strtoupper($country),
        'discount_cents' => $discount,
        'voucher_code'   => $discount > 0 ? $voucher : '',
        'total_cents'    => max(0, (int)$t['total'] - $discount),
        'vat_cents'      => vr_config('prices_include_vat')
                            ? vr_vat_part(max(0, (int)$t['total'] - $discount)) : 0,

        'by_seller'            => $bySeller,
        'commission_cents'     => max(0, $commissionTotal - $discount),
        'commission_gross_cents' => $commissionTotal,
        'application_fee_cents' => $applicationFee,
        'payout_mode'          => $payoutMode,
        'destination'          => $destination,
        'has_private'          => (bool)$t['has_private'],

        'stripe'    => ['session_id' => '', 'payment_intent' => '', 'charge_id' => ''],
        'transfers' => [],
        'payout_pending' => false,
        'customer'  => ['email' => '', 'name' => '', 'address' => []],
    ];

    return [true, $order];
}

function vr_order_save(array $order): bool
{
    return vr_store_append(VR_ORDERS_FILE, $order);
}

function vr_orders(): array
{
    $d = vr_store_read(VR_ORDERS_FILE, []);
    return is_array($d) ? $d : [];
}

function vr_order_get(string $id, ?string $token = null): ?array
{
    foreach (vr_orders() as $o) {
        if ((string)($o['id'] ?? '') !== $id) continue;
        // Sipariş sayfası girişsiz açılıyor: id + token birlikte doğru olmalı.
        if ($token !== null && !hash_equals((string)($o['token'] ?? ''), $token)) return null;
        return $o;
    }
    return null;
}

function vr_order_by_session_id(string $stripeSessionId): ?array
{
    foreach (vr_orders() as $o) {
        if ((string)($o['stripe']['session_id'] ?? '') === $stripeSessionId) return $o;
    }
    return null;
}

/** Sipariş alanlarını güncelle (kilitli). */
function vr_order_update(string $id, array $patch): ?array
{
    $updated = null;
    vr_store_mutate(VR_ORDERS_FILE, static function ($rows) use ($id, $patch, &$updated) {
        if (!is_array($rows)) return null;
        foreach ($rows as $i => $o) {
            if ((string)($o['id'] ?? '') !== $id) continue;
            unset($patch['id'], $patch['token']);
            $rows[$i] = array_replace_recursive($o, $patch);
            $updated  = $rows[$i];
            return $rows;
        }
        return null;
    });
    return $updated;
}

/** Bir satıcının siparişleri (panel). */
function vr_orders_for_seller(string $sellerId): array
{
    $out = [];
    foreach (vr_orders() as $o) {
        if (($o['status'] ?? '') !== 'paid') continue;
        if (!isset($o['by_seller'][$sellerId])) continue;
        $out[] = $o;
    }
    usort($out, fn($a, $b) => (int)$b['created_at'] <=> (int)$a['created_at']);
    return $out;
}

// -------------------------------------------------------------- ödeme onayı
/**
 * Ödeme onaylandı. Stripe aynı olayı tekrar gönderse de bir kez çalışır.
 * Dönüş: güncel sipariş veya null.
 */
function vr_order_mark_paid(string $orderId, array $stripeData = []): ?array
{
    // 1) Durumu kilit altında çevir — "zaten paid" ise hiçbir yan etki üretmeyiz.
    $firstTime = false;
    $order     = null;

    vr_store_mutate(VR_ORDERS_FILE, static function ($rows) use ($orderId, $stripeData, &$firstTime, &$order) {
        if (!is_array($rows)) return null;
        foreach ($rows as $i => $o) {
            if ((string)($o['id'] ?? '') !== $orderId) continue;

            if ((string)($o['status'] ?? '') === 'paid') {
                $order = $o;                    // idempotent çıkış
                return null;
            }

            $rows[$i]['status']    = 'paid';
            $rows[$i]['paid_at']   = time();
            $rows[$i]['stripe']    = array_merge((array)($o['stripe'] ?? []), array_filter([
                'session_id'     => (string)($stripeData['session_id'] ?? ''),
                'payment_intent' => (string)($stripeData['payment_intent'] ?? ''),
                'charge_id'      => (string)($stripeData['charge_id'] ?? ''),
            ]));
            if (!empty($stripeData['customer'])) {
                $rows[$i]['customer'] = array_merge((array)($o['customer'] ?? []), (array)$stripeData['customer']);
            }

            $firstTime = true;
            $order     = $rows[$i];
            return $rows;
        }
        return null;
    });

    if ($order === null) return null;
    if (!$firstTime)     return $order;         // yan etkiler bir kez çalıştı

    // 2) Stok düş + los kapat
    vr_stock_consume((array)$order['lines']);
    foreach ((array)$order['lines'] as $ln) {
        if (!empty($ln['vault']) && !empty($ln['lot'])) {
            vr_vault_mark_sold((string)$ln['lot'], (string)$order['id']);
        }
    }

    // 3) Gutschein sayacı — ödeme gerçekleştiğinde, yazarken değil. Yarıda
    //    bırakılan bir kasa hiçbir kuponu harcamıyor. Fonksiyon idempotent:
    //    aynı sipariş id'si sayacı bir kez artırıyor.
    if (($order['voucher_code'] ?? '') !== '') {
        require_once __DIR__ . '/vouchers.php';
        vr_voucher_redeem(
            (string)$order['voucher_code'],
            (string)$order['id'],
            (string)($order['customer']['email'] ?? '')
        );
    }

    // 4) Satıcı payları
    $order = vr_order_payout($order) ?? $order;

    // 5) Bildirimler — mail hatası siparişi bozmaz, sadece kaydedilir.
    try {
        vr_mail_order_buyer($order);
        vr_mail_order_sellers($order);
    } catch (Throwable $e) {
        vr_log('mail_failed', ['order' => $order['id'], 'err' => $e->getMessage()]);
    }

    return $order;
}

/**
 * "separate charges and transfers" modelinde satıcı transferlerini açar.
 * destination modelinde Stripe bunu ödeme anında zaten yaptı.
 */
function vr_order_payout(array $order): ?array
{
    if ((string)($order['payout_mode'] ?? '') !== 'separate') return $order;

    $charge     = (string)($order['stripe']['charge_id'] ?? '');
    $transfers  = (array)($order['transfers'] ?? []);
    $pending    = false;

    foreach ((array)$order['by_seller'] as $uid => $row) {
        if ($uid === 'vestra') continue;
        $net = (int)($row['net'] ?? 0);
        if ($net <= 0) continue;

        // Zaten aktarılmış mı? (webhook tekrarına karşı ikinci kalkan)
        foreach ($transfers as $tr) {
            if ((string)($tr['seller'] ?? '') === $uid && !empty($tr['id'])) continue 2;
        }

        $acct  = (string)($row['stripe'] ?? '');
        $ready = !empty($row['ready']);

        if (!$ready || $acct === '') {
            // Satıcı Connect'te hazır değil: pay platformda bekler, elle ödenir.
            $transfers[] = ['seller' => $uid, 'amount' => $net, 'id' => '', 'status' => 'held', 'reason' => 'connect_not_ready'];
            $pending = true;
            continue;
        }

        [$ok, $idOrErr] = vr_stripe_transfer($net, $acct, (string)$order['id'], $charge ?: null, 'tr-' . $order['id'] . '-' . $uid);
        if ($ok) {
            $transfers[] = ['seller' => $uid, 'amount' => $net, 'id' => $idOrErr, 'status' => 'sent', 'at' => time()];
        } else {
            $transfers[] = ['seller' => $uid, 'amount' => $net, 'id' => '', 'status' => 'error', 'reason' => $idOrErr];
            $pending = true;
            vr_stripe_log('transfer_failed', 0, ['order' => $order['id'], 'seller' => $uid, 'err' => $idOrErr]);
        }
    }

    return vr_order_update((string)$order['id'], [
        'transfers'      => $transfers,
        'payout_pending' => $pending,
    ]);
}

/** Ödeme başarısız/iptal — losları serbest bırak. */
function vr_order_mark_failed(string $orderId, string $reason = ''): void
{
    $o = vr_order_get($orderId);
    if ($o === null || (string)($o['status'] ?? '') === 'paid') return;

    vr_order_update($orderId, ['status' => 'failed', 'failed_reason' => $reason, 'failed_at' => time()]);

    foreach ((array)$o['lines'] as $ln) {
        if (!empty($ln['vault']) && !empty($ln['lot'])) {
            vr_vault_release((string)$ln['lot'], (string)($o['session_id'] ?? ''));
        }
    }
}

/** Sipariş durumunun okunabilir karşılığı. */
function vr_order_status_label(string $status): string
{
    return match ($status) {
        'paid'      => t('order_paid'),
        'failed'    => t('order_failed'),
        'cancelled' => t('order_cancelled'),
        default     => t('order_pending'),
    };
}
