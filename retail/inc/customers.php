<?php
/**
 * Müşteri (alıcı) hesapları
 * =========================
 * Satın almak için hesap GEREKMİYOR ve gerekmeyecek: misafir ödeme açık kalıyor
 * (kayıt zorunluluğu dönüşümü düşürür, ayrıca gereksiz veri toplamak DSGVO
 * Art. 5'teki veri minimizasyonuna aykırıdır). Hesap tamamen isteğe bağlı bir
 * kolaylık: sipariş geçmişi, adres hatırlama, tek tıkla iade talebi.
 *
 * Güvenlikte kritik olan tek karar — SİPARİŞ GEÇMİŞİ DOĞRULANMIŞ E-POSTA İSTER
 * ----------------------------------------------------------------------------
 * Siparişler alıcının e-postasına bağlı. Biri başkasının adresiyle hesap açıp
 * doğrulamadan giriş yapabilseydi o kişinin sipariş geçmişini, adresini ve
 * satın aldıklarını görürdü. Bu yüzden:
 *   • kayıt hesabı açar ama sipariş geçmişini AÇMAZ,
 *   • geçmiş yalnızca e-posta doğrulandıktan sonra görünür,
 *   • eşleşme daima doğrulanmış adres üzerinden yapılır.
 *
 * Şifre sıfırlama ve doğrulama jetonları hash'lenmiş saklanır: dosya sızsa bile
 * jetonlar kullanılamaz. Her ikisi de tek kullanımlık ve süreli.
 */

declare(strict_types=1);

require_once __DIR__ . '/boot.php';
require_once __DIR__ . '/orders.php';

const VR_CUSTOMERS_FILE   = 'retail-customers.json';
const VR_VERIFY_TTL       = 172800;   // doğrulama bağlantısı 48 saat
const VR_RESET_TTL        = 3600;     // şifre sıfırlama 1 saat
const VR_CUSTOMER_PW_MIN  = 10;

// ------------------------------------------------------------------ okuma
function vr_customers(): array
{
    $d = vr_store_read(VR_CUSTOMERS_FILE, []);
    return is_array($d) ? $d : [];
}

function vr_customer(string $id): ?array
{
    foreach (vr_customers() as $c) {
        if ((string)($c['id'] ?? '') === $id) return $c;
    }
    return null;
}

function vr_customer_by_email(string $email): ?array
{
    $email = strtolower(trim($email));
    if ($email === '') return null;
    foreach (vr_customers() as $c) {
        if (strtolower((string)($c['email'] ?? '')) === $email) return $c;
    }
    return null;
}

/**
 * Jeton üret: ham hâli e-postaya gider, dosyaya yalnızca hash'i yazılır.
 * Dönüş: [ham, hash].
 */
function vr_customer_token(): array
{
    $raw = bin2hex(random_bytes(20));
    return [$raw, hash('sha256', $raw)];
}

// ------------------------------------------------------------------ kayıt
/**
 * Yeni hesap. Dönüş: [ok, hata_anahtari|müşteri, ham_doğrulama_jetonu].
 *
 * E-posta zaten kayıtlıysa hata döndürüyoruz ama ÇAĞIRAN bunu kullanıcıya
 * "bu adres kayıtlı" diye göstermemeli — bkz. account/register.php, orada her
 * iki durumda da aynı nötr mesaj basılıyor (hesap sayımı sızmasın).
 */
function vr_customer_create(array $in): array
{
    $email = strtolower(trim((string)($in['email'] ?? '')));
    $pw    = (string)($in['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL))  return [false, 'email_invalid', ''];
    if (mb_strlen($pw) < VR_CUSTOMER_PW_MIN)         return [false, 'pw_too_short', ''];

    [$raw, $hash] = vr_customer_token();

    $c = [
        'id'         => 'rc-' . substr(sha1($email . '|' . microtime(true)), 0, 14),
        'email'      => $email,
        'pw_hash'    => password_hash($pw, PASSWORD_DEFAULT),
        'name'       => mb_substr(trim((string)($in['name'] ?? '')), 0, 120),
        'status'     => 'active',
        'verified'   => false,
        'verify_hash'    => $hash,
        'verify_expires' => time() + VR_VERIFY_TTL,
        'reset_hash'     => '',
        'reset_expires'  => 0,
        'address'    => ['street' => '', 'zip' => '', 'city' => '', 'country' => ''],
        'lang'       => vr_lang(),
        'created_at' => time(),
        'last_login' => 0,
    ];

    $ok = false;
    vr_store_mutate(VR_CUSTOMERS_FILE, static function ($rows) use ($c, &$ok) {
        if (!is_array($rows)) $rows = [];
        // Kilit altında ikinci kontrol: eşzamanlı iki kayıt aynı adresle iki
        // hesap açmasın.
        foreach ($rows as $r) {
            if (strtolower((string)($r['email'] ?? '')) === $c['email']) return null;
        }
        $rows[] = $c;
        $ok = true;
        return $rows;
    });

    if (!$ok) return [false, 'email_taken', ''];
    return [true, $c, $raw];
}

/** Kaydı güncelle (kilitli). id, pw_hash ve e-posta dışarıdan ezilemez. */
function vr_customer_update(string $id, array $patch): bool
{
    $ok = false;
    vr_store_mutate(VR_CUSTOMERS_FILE, static function ($rows) use ($id, $patch, &$ok) {
        if (!is_array($rows)) return null;
        foreach ($rows as $i => $r) {
            if ((string)($r['id'] ?? '') !== $id) continue;
            unset($patch['id'], $patch['pw_hash'], $patch['email']);
            $rows[$i] = array_merge($r, $patch);
            $ok = true;
            return $rows;
        }
        return null;
    });
    return $ok;
}

/** Şifre değiştir — eski şifre doğrulanarak. Dönüş: [ok, hata_anahtari] */
function vr_customer_set_password(string $id, string $new, ?string $old = null): array
{
    if (mb_strlen($new) < VR_CUSTOMER_PW_MIN) return [false, 'pw_too_short'];

    $ok = false; $err = 'login_failed';
    vr_store_mutate(VR_CUSTOMERS_FILE, static function ($rows) use ($id, $new, $old, &$ok, &$err) {
        if (!is_array($rows)) return null;
        foreach ($rows as $i => $r) {
            if ((string)($r['id'] ?? '') !== $id) continue;
            if ($old !== null && !password_verify($old, (string)($r['pw_hash'] ?? ''))) {
                $err = 'pw_wrong';
                return null;
            }
            $rows[$i]['pw_hash'] = password_hash($new, PASSWORD_DEFAULT);
            // Sıfırlama jetonu varsa yakılır: şifre değişti, eski bağlantı ölsün.
            $rows[$i]['reset_hash'] = '';
            $rows[$i]['reset_expires'] = 0;
            $ok = true;
            return $rows;
        }
        return null;
    });
    return $ok ? [true, ''] : [false, $err];
}

// ------------------------------------------------------------- doğrulama
/** E-posta doğrulama bağlantısı. Dönüş: [ok, hata_anahtari] */
function vr_customer_verify(string $email, string $rawToken): array
{
    $email = strtolower(trim($email));
    $hash  = hash('sha256', $rawToken);
    $now   = time();

    $ok = false;
    vr_store_mutate(VR_CUSTOMERS_FILE, static function ($rows) use ($email, $hash, $now, &$ok) {
        if (!is_array($rows)) return null;
        foreach ($rows as $i => $r) {
            if (strtolower((string)($r['email'] ?? '')) !== $email) continue;
            if (!empty($r['verified'])) { $ok = true; return null; }   // zaten doğrulanmış
            if ((string)($r['verify_hash'] ?? '') === '') return null;
            if ((int)($r['verify_expires'] ?? 0) < $now)   return null;
            if (!hash_equals((string)$r['verify_hash'], $hash)) return null;

            $rows[$i]['verified']       = true;
            $rows[$i]['verified_at']    = $now;
            $rows[$i]['verify_hash']    = '';    // tek kullanımlık
            $rows[$i]['verify_expires'] = 0;
            $ok = true;
            return $rows;
        }
        return null;
    });
    return $ok ? [true, ''] : [false, 'verify_failed'];
}

/** Doğrulama bağlantısını yenile. Dönüş: [ok, ham_jeton] */
function vr_customer_reverify(string $id): array
{
    [$raw, $hash] = vr_customer_token();
    $ok = vr_customer_update($id, [
        'verify_hash'    => $hash,
        'verify_expires' => time() + VR_VERIFY_TTL,
    ]);
    return $ok ? [true, $raw] : [false, ''];
}

// -------------------------------------------------------- şifre sıfırlama
/**
 * Sıfırlama jetonu üret. Hesap YOKSA da [true, ''] döner: çağıran her iki
 * durumda aynı mesajı basar, böylece "bu adres kayıtlı mı" sızmaz.
 */
function vr_customer_reset_request(string $email): array
{
    $c = vr_customer_by_email($email);
    if ($c === null) return [true, ''];

    [$raw, $hash] = vr_customer_token();
    vr_customer_update((string)$c['id'], [
        'reset_hash'    => $hash,
        'reset_expires' => time() + VR_RESET_TTL,
    ]);
    return [true, $raw];
}

/** Jetonla şifre belirle. Dönüş: [ok, hata_anahtari] */
function vr_customer_reset_apply(string $email, string $rawToken, string $new): array
{
    if (mb_strlen($new) < VR_CUSTOMER_PW_MIN) return [false, 'pw_too_short'];

    $email = strtolower(trim($email));
    $hash  = hash('sha256', $rawToken);
    $now   = time();

    $ok = false;
    vr_store_mutate(VR_CUSTOMERS_FILE, static function ($rows) use ($email, $hash, $now, $new, &$ok) {
        if (!is_array($rows)) return null;
        foreach ($rows as $i => $r) {
            if (strtolower((string)($r['email'] ?? '')) !== $email) continue;
            if ((string)($r['reset_hash'] ?? '') === '')       return null;
            if ((int)($r['reset_expires'] ?? 0) < $now)         return null;
            if (!hash_equals((string)$r['reset_hash'], $hash))  return null;

            $rows[$i]['pw_hash']       = password_hash($new, PASSWORD_DEFAULT);
            $rows[$i]['reset_hash']    = '';       // tek kullanımlık
            $rows[$i]['reset_expires'] = 0;
            // Sıfırlama bağlantısına erişebilen posta kutusunu kanıtlamıştır.
            $rows[$i]['verified']      = true;
            $ok = true;
            return $rows;
        }
        return null;
    });
    return $ok ? [true, ''] : [false, 'reset_failed'];
}

// ---------------------------------------------------------------- oturum
/** Giriş. Dönüş: [ok, hata_anahtari] */
function vr_customer_login(string $email, string $pw): array
{
    if (!vr_rate_ok('clogin', 10, 300)) return [false, 'login_throttled'];

    $c = vr_customer_by_email($email);
    // Hesap yoksa da bir hash doğrulaması yapıyoruz: cevap süresi "bu e-posta
    // kayıtlı mı" sorusunu sızdırmasın.
    $hash = (string)($c['pw_hash'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidin');
    if (!password_verify($pw, $hash) || $c === null)      return [false, 'login_failed'];
    if ((string)($c['status'] ?? 'active') !== 'active')  return [false, 'login_failed'];

    vr_session_start();
    vr_session_reid();                             // oturum sabitlemeye karşı
    $_SESSION['vr_customer_id'] = $c['id'];
    vr_customer_update((string)$c['id'], ['last_login' => time()]);
    return [true, ''];
}

function vr_customer_logout(): void
{
    vr_session_start();
    unset($_SESSION['vr_customer_id']);
    vr_session_reid();
}

function vr_current_customer(): ?array
{
    vr_session_start();
    $id = (string)($_SESSION['vr_customer_id'] ?? '');
    return $id === '' ? null : vr_customer($id);
}

/** Hesap sayfalarının başında: giriş yoksa login'e yolla. */
function vr_customer_require(): array
{
    $c = vr_current_customer();
    if ($c === null) vr_redirect('account/login.php');
    return $c;
}

// -------------------------------------------------------------- siparişler
/**
 * Hesabın siparişleri — YALNIZCA e-posta doğrulanmışsa.
 * Doğrulanmamış hesap boş liste alır; dosyadaki başka birinin siparişini
 * görmenin yolu yok.
 */
function vr_customer_orders(array $customer): array
{
    if (empty($customer['verified'])) return [];

    $email = strtolower((string)($customer['email'] ?? ''));
    if ($email === '') return [];

    $out = [];
    foreach (vr_orders() as $o) {
        if (strtolower((string)($o['customer']['email'] ?? '')) !== $email) continue;
        // Ödenmemiş/yarım kalmış oturumlar geçmişte görünmesin.
        if (in_array((string)($o['status'] ?? ''), ['pending', 'failed'], true)) continue;
        $out[] = $o;
    }
    usort($out, fn($a, $b) => (int)($b['created_at'] ?? 0) <=> (int)($a['created_at'] ?? 0));
    return $out;
}

/** Tek sipariş — sahiplik doğrulanarak. */
function vr_customer_order(array $customer, string $orderId): ?array
{
    foreach (vr_customer_orders($customer) as $o) {
        if ((string)($o['id'] ?? '') === $orderId) return $o;
    }
    return null;
}

// ------------------------------------------------------------ hesap silme
/**
 * DSGVO Art. 17 — silme hakkı.
 *
 * Hesap kaydı tamamen silinir. SİPARİŞLER SİLİNMEZ: ticari ve vergisel
 * saklama yükümlülüğü var (§147 AO, §257 HGB — 10 yıl fatura, 6 yıl ticari
 * yazışma). Bu, Art. 17 Abs. 3 lit. b'deki istisnanın tam olarak kapsadığı
 * durumdur ve hesap silme ekranında kullanıcıya bu açıkça yazılıyor.
 */
function vr_customer_delete(string $id): bool
{
    $ok = false;
    vr_store_mutate(VR_CUSTOMERS_FILE, static function ($rows) use ($id, &$ok) {
        if (!is_array($rows)) return null;
        foreach ($rows as $i => $r) {
            if ((string)($r['id'] ?? '') !== $id) continue;
            unset($rows[$i]);
            $ok = true;
            return array_values($rows);
        }
        return null;
    });
    if ($ok) vr_customer_logout();
    return $ok;
}
