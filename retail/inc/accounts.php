<?php
/**
 * Satıcı hesapları (Anmeldung)
 * ----------------------------
 * Perakende satıcıları KENDİ dosyasında durur: data/retail-sellers.json.
 * Canlı B2B tarafının hesap dosyasına yazmıyoruz — orada auth_accounts() ve
 * escrow mantığı çalışıyor, tek yazar olarak kalmalı.
 *
 * Ama köprü kuruyoruz: kayıt olan e-posta canlı hesap dosyasında zaten varsa ve
 * o hesabın bir stripe_account_id'si varsa onu DEVRALIYORUZ (salt okuma).
 * Böylece toptan tarafta Stripe onboarding'i bitmiş bir satıcı perakendede
 * aynı işlemi ikinci kez yapmak zorunda kalmıyor.
 *
 * Alıcı tarafında hesap YOK: misafir ödeme + sipariş no ile takip. Satın almak
 * için kayıt zorunluluğu koymak dönüşümü düşürür, ayrıca gereksiz veri toplamak
 * veri minimizasyonuna (DSGVO Art. 5) aykırı olur.
 */

declare(strict_types=1);

require_once __DIR__ . '/boot.php';

const VR_SELLERS_FILE = 'retail-sellers.json';
const VR_TERMS_VERSION = '2026-08';

function vr_sellers(): array
{
    $d = vr_store_read(VR_SELLERS_FILE, []);
    return is_array($d) ? $d : [];
}

function vr_seller(string $id): ?array
{
    foreach (vr_sellers() as $s) {
        if ((string)($s['id'] ?? '') === $id) return $s;
    }
    return null;
}

function vr_seller_by_email(string $email): ?array
{
    $e = strtolower(trim($email));
    foreach (vr_sellers() as $s) {
        if (strtolower((string)($s['email'] ?? '')) === $e) return $s;
    }
    return null;
}

/**
 * Canlı B2B hesap dosyasında bu e-postayı ara (SALT OKUNUR).
 * Dosya adı kurulumdan kuruluma değişebildiği için birkaç adayı deniyoruz.
 */
function vr_legacy_account(string $email): ?array
{
    $e = strtolower(trim($email));
    foreach (['accounts.json', 'users.json', 'sellers.json'] as $file) {
        $rows = vr_store_read($file, null);
        if (!is_array($rows)) continue;
        foreach ($rows as $a) {
            if (!is_array($a)) continue;
            if (strtolower((string)($a['email'] ?? '')) === $e) return $a;
        }
    }
    return null;
}

/**
 * Yeni satıcı. Dönüş: [ok, satici_veya_hata_anahtari]
 */
function vr_seller_create(array $in): array
{
    $email = strtolower(trim((string)($in['email'] ?? '')));
    $pw    = (string)($in['password'] ?? '');
    $type  = (string)($in['seller_type'] ?? 'private');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return [false, 'email_invalid'];
    if (mb_strlen($pw) < 10)                        return [false, 'pw_too_short'];
    if (!in_array($type, ['business', 'private'], true)) $type = 'private';
    if (vr_seller_by_email($email) !== null)        return [false, 'email_taken'];

    // Toptan tarafta zaten onboarding yapmış mı?
    $legacy = vr_legacy_account($email);

    $seller = [
        'id'          => 'rs-' . substr(sha1($email . '|' . microtime(true)), 0, 14),
        'email'       => $email,
        'pw_hash'     => password_hash($pw, PASSWORD_DEFAULT),
        'name'        => trim((string)($in['name'] ?? '')),
        'company'     => $type === 'business' ? trim((string)($in['company'] ?? '')) : '',
        'seller_type' => $type,
        'country'     => strtoupper(substr(trim((string)($in['country'] ?? 'DE')), 0, 2)),
        'vat'         => trim((string)($in['vat'] ?? '')),
        'status'      => 'active',
        'created_at'  => time(),
        'terms_accepted_at' => time(),
        'terms_version'     => VR_TERMS_VERSION,
        // Stripe alanları — onboarding sonrası webhook/refresh doldurur.
        'stripe_account_id' => is_array($legacy) ? (string)($legacy['stripe_account_id'] ?? '') : '',
        'charges_enabled'   => false,
        'payouts_enabled'   => false,
        'details_submitted' => false,
        'linked_legacy'     => is_array($legacy) ? (string)($legacy['id'] ?? '') : '',
    ];

    $ok = false;
    vr_store_mutate(VR_SELLERS_FILE, static function ($rows) use ($seller, &$ok) {
        if (!is_array($rows)) $rows = [];
        // Kilit altında e-postayı bir kez daha kontrol et: iki eşzamanlı kayıt
        // isteği aynı adresle iki hesap açmasın.
        foreach ($rows as $r) {
            if (strtolower((string)($r['email'] ?? '')) === $seller['email']) return null;
        }
        $rows[] = $seller;
        $ok = true;
        return $rows;
    });

    if (!$ok) return [false, 'email_taken'];
    return [true, $seller];
}

/** Satıcı kaydını güncelle (kilitli). $patch alanları üzerine yazar. */
function vr_seller_update(string $id, array $patch): bool
{
    $ok = false;
    vr_store_mutate(VR_SELLERS_FILE, static function ($rows) use ($id, $patch, &$ok) {
        if (!is_array($rows)) return null;
        foreach ($rows as $i => $r) {
            if ((string)($r['id'] ?? '') !== $id) continue;
            // pw_hash ve id dışarıdan ezilemez.
            unset($patch['id'], $patch['pw_hash']);
            $rows[$i] = array_merge($r, $patch);
            $ok = true;
            return $rows;
        }
        return null;
    });
    return $ok;
}

/** Giriş. Dönüş: [ok, hata_anahtari] */
function vr_seller_login(string $email, string $pw): array
{
    if (!vr_rate_ok('login', 10, 300)) return [false, 'login_throttled'];

    $s = vr_seller_by_email($email);
    // Kullanıcı yoksa da bir hash doğrulaması yapıyoruz: cevap süresi
    // "bu e-posta kayıtlı mı" sorusunu sızdırmasın.
    $hash = (string)($s['pw_hash'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidin');
    if (!password_verify($pw, $hash) || $s === null) return [false, 'login_failed'];
    if ((string)($s['status'] ?? 'active') !== 'active') return [false, 'login_failed'];

    vr_session_start();
    vr_session_reid();                              // oturum sabitlemeye karşı
    $_SESSION['vr_seller_id'] = $s['id'];
    return [true, ''];
}

function vr_seller_logout(): void
{
    vr_session_start();
    unset($_SESSION['vr_seller_id']);
    vr_session_reid();
}

function vr_current_seller(): ?array
{
    vr_session_start();
    $id = (string)($_SESSION['vr_seller_id'] ?? '');
    return $id === '' ? null : vr_seller($id);
}

/** Panel sayfalarının başında: giriş yoksa login'e yolla. */
function vr_seller_require(): array
{
    $s = vr_current_seller();
    if ($s === null) vr_redirect('seller/login.php');
    return $s;
}

/** Satıcı adı — gösterimde şirket varsa şirket, yoksa kişi adı. */
function vr_seller_display(?array $s): string
{
    if ($s === null) return (string)vr_config('brand');
    $n = trim((string)($s['company'] ?? ''));
    if ($n !== '') return $n;
    $n = trim((string)($s['name'] ?? ''));
    if ($n !== '') return $n;
    return (string)($s['seller_type'] ?? '') === 'private' ? t('seller_private') : t('seller_business');
}

/**
 * Ürünün satıcı bilgisi — ürün sayfasında "kimden alıyorsunuz" bloğu için.
 * Perakende satıcısı yoksa (B2B/kendi stoğumuz) MAXSALES döner.
 */
function vr_product_seller(array $product): array
{
    $uid = (string)($product['seller_uid'] ?? '');
    if ($uid !== '') {
        $s = vr_seller($uid);
        if ($s !== null) {
            return [
                'type'     => (string)($s['seller_type'] ?? 'business'),
                'name'     => vr_seller_display($s),
                'country'  => (string)($s['country'] ?? ''),
                'id'       => (string)$s['id'],
                'stripe'   => (string)($s['stripe_account_id'] ?? ''),
                'ready'    => !empty($s['charges_enabled']),
            ];
        }
    }
    return [
        'type'    => 'vestra',
        'name'    => (string)vr_config('brand'),
        'country' => '',
        'id'      => '',
        'stripe'  => '',
        'ready'   => true,
    ];
}

/** Satıcı tipine göre komisyon (baz puan). */
function vr_fee_bps(string $sellerType, bool $isVault = false): int
{
    if ($isVault) return (int)vr_config('fee_bps_outlet', 1500);
    return $sellerType === 'private'
        ? (int)vr_config('fee_bps_private', 900)
        : (int)vr_config('fee_bps_business', 1200);
}

/** Bir satır için komisyon tutarı (kuruş). */
function vr_fee_cents(int $grossCents, string $sellerType, bool $isVault = false): int
{
    $bps = vr_fee_bps($sellerType, $isVault);
    $fee = (int)round($grossCents * $bps / 10000) + (int)vr_config('fee_fixed_cents', 35);
    // Komisyon tutarın tamamını yiyemez.
    return max(0, min($fee, $grossCents - 1));
}
