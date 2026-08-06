<?php
/**
 * Yönetici erişimi
 * ================
 * CLI araçları (moderate, vault-open, import-live) duruyor ve kalacak: SSH'i
 * olan zaten sunucunun sahibi. Bu panel onların yerine değil, YANINA geliyor —
 * telefondan sipariş bakmak, kargo numarası girmek, bir losu kapatmak için.
 *
 * Web'de duran her yönetici paneli korunması gereken yeni bir saldırı yüzeyidir.
 * Bu yüzden:
 *   • Kimlik bilgisi kodda DEĞİL: ya ortam değişkeninde ya data/admin.json'da,
 *     ikisi de depoya girmez. Hiçbiri yoksa panel tamamen KAPALI (404 gibi
 *     davranmıyoruz ama giriş de mümkün değil, açıkça "kurulmamış" diyoruz).
 *   • Şifre password_hash ile saklanır, düz metin asla.
 *   • Oturum anahtarı müşteri ve satıcıdan ayrı; biri diğerine dönüşemez.
 *   • Girişte hız sınırı, her sayfada noindex.
 *   • İsteğe bağlı IP kısıtı: VR_ADMIN_IPS="1.2.3.4,5.6.7.8".
 *
 * Şifreyi kurmak için:  php tools/admin-user.php set <e-posta> <şifre>
 */

declare(strict_types=1);

require_once __DIR__ . '/boot.php';

const VR_ADMIN_FILE = 'admin.json';

/**
 * Yapılandırılmış yönetici. Dönüş: ['email' => …, 'pw_hash' => …] veya null.
 * Ortam değişkeni dosyanın önüne geçer — konteyner kurulumlarında dosya
 * yazmadan yapılandırılabilsin.
 */
function vr_admin_config(): ?array
{
    $email = trim((string)getenv('VR_ADMIN_USER'));
    $hash  = trim((string)getenv('VR_ADMIN_PW_HASH'));
    if ($email !== '' && $hash !== '') return ['email' => strtolower($email), 'pw_hash' => $hash];

    $d = vr_store_read(VR_ADMIN_FILE, []);
    if (!is_array($d)) return null;
    $email = strtolower(trim((string)($d['email'] ?? '')));
    $hash  = trim((string)($d['pw_hash'] ?? ''));
    if ($email === '' || $hash === '') return null;

    return ['email' => $email, 'pw_hash' => $hash];
}

/** Panel hiç kurulmamışsa true. Giriş ekranı bunu açıkça yazıyor. */
function vr_admin_unconfigured(): bool
{
    return vr_admin_config() === null;
}

/**
 * İsteğe bağlı IP kısıtı. Liste boşsa herkese açık (kimlik doğrulama yine de
 * şart). Ters vekil arkasındaysa gerçek adres X-Forwarded-For'un İLK
 * girdisidir; bunu yalnızca liste tanımlıysa okuyoruz — tanımsızken sahte
 * başlıkla oynanacak bir karar yok.
 */
function vr_admin_ip_ok(): bool
{
    $raw = trim((string)getenv('VR_ADMIN_IPS'));
    if ($raw === '') return true;

    $allow = array_filter(array_map('trim', explode(',', $raw)));
    if (!$allow) return true;

    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $fwd = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
    if ($fwd !== '') $ip = trim(explode(',', $fwd)[0]);

    return in_array($ip, $allow, true);
}

/** Giriş. Dönüş: [ok, hata_anahtari] */
function vr_admin_login(string $email, string $pw): array
{
    if (!vr_admin_ip_ok())              return [false, 'admin_ip_blocked'];
    if (!vr_rate_ok('alogin', 8, 600))  return [false, 'login_throttled'];

    $cfg = vr_admin_config();
    // Kurulmamışken de bir hash doğrulaması yapıyoruz: cevap süresi "panel
    // kurulu mu" sorusunu sızdırmasın.
    $hash = $cfg['pw_hash'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidin';
    $okPw = password_verify($pw, $hash);

    if ($cfg === null || !$okPw) return [false, 'login_failed'];
    if (strtolower(trim($email)) !== $cfg['email']) return [false, 'login_failed'];

    vr_session_start();
    vr_session_reid();
    $_SESSION['vr_admin'] = $cfg['email'];
    $_SESSION['vr_admin_at'] = time();
    return [true, ''];
}

function vr_admin_logout(): void
{
    vr_session_start();
    unset($_SESSION['vr_admin'], $_SESSION['vr_admin_at']);
    vr_session_reid();
}

/**
 * Giriş yapmış yönetici e-postası veya null.
 * Oturum 8 saatten eskiyse düşüyor: panel açık unutulmuş bir sekmede
 * süresiz kalmasın.
 */
function vr_current_admin(): ?string
{
    vr_session_start();
    $who = (string)($_SESSION['vr_admin'] ?? '');
    if ($who === '') return null;
    if (time() - (int)($_SESSION['vr_admin_at'] ?? 0) > 28800) { vr_admin_logout(); return null; }
    if (!vr_admin_ip_ok()) { vr_admin_logout(); return null; }
    return $who;
}

/** Panel sayfalarının ilk satırı. */
function vr_admin_require(): string
{
    $who = vr_current_admin();
    if ($who === null) vr_redirect('admin/login.php');
    return $who;
}

/** Şifreyi kur/değiştir — tools/admin-user.php kullanır. */
function vr_admin_set(string $email, string $pw): array
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return [false, 'E-Mail ungültig'];
    if (mb_strlen($pw) < 12) return [false, 'Passwort: mindestens 12 Zeichen'];

    $ok = vr_store_write(VR_ADMIN_FILE, [
        'email'      => $email,
        'pw_hash'    => password_hash($pw, PASSWORD_DEFAULT),
        'updated_at' => time(),
    ]);
    return $ok ? [true, ''] : [false, 'Datei konnte nicht geschrieben werden'];
}
