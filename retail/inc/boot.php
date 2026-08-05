<?php
/**
 * Ortak önyükleme — her sayfanın ilk satırında require edilir.
 * Oturum, güvenlik başlıkları, CSRF, biçimlendirme yardımcıları, dil.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/i18n.php';

if (vr_config('debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

// ------------------------------------------------------------------ oturum
function vr_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $secure = str_starts_with(vr_origin(), 'https://');
    session_name((string)vr_config('session_name'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => vr_base_url() === '' ? '/' : vr_base_url() . '/',
        'httponly' => true,
        'secure'   => $secure,
        // Stripe Checkout'tan geri dönüşte (cross-site GET) oturumun sağ kalması
        // gerekiyor; 'Strict' olsa sepet/sipariş görünmezdi.
        'samesite' => 'Lax',
    ]);
    @session_start();
}

// ----------------------------------------------------- güvenlik başlıkları
function vr_security_headers(): void
{
    if (headers_sent()) return;

    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(self)');
    header_remove('X-Powered-By');

    // CSP: kendi CSS/JS'imiz dışında hiçbir dış kaynak yok. Google Fonts
    // BİLE yok — DE'de yazı tipini Google'dan çekmek kişisel veri aktarımı
    // sayıldığı için (LG München 3 O 17493/20) tipografi sistem yazı
    // tipleriyle kuruldu. 'unsafe-inline' style için gerekli: kritik CSS ve
    // ürün kartlarındaki --img değişkeni satır içi geliyor.
    header(
        "Content-Security-Policy: "
        . "default-src 'self'; "
        . "img-src 'self' data:; "
        . "style-src 'self' 'unsafe-inline'; "
        . "script-src 'self'; "
        . "frame-src https://js.stripe.com https://hooks.stripe.com; "
        . "connect-src 'self' https://api.stripe.com; "
        . "form-action 'self' https://checkout.stripe.com; "
        . "base-uri 'self'; object-src 'none'"
    );
}

// ---------------------------------------------------------------- yardımcı
/** HTML kaçışı. Şablonlarda çıktı DAİMA bundan geçer. */
function h(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Uygulama içi URL üret; dil parametresini korur. */
function vr_url(string $path = '', array $params = []): string
{
    $base = vr_base_url();
    $path = '/' . ltrim($path, '/');
    if ($path === '/') $path = '/';

    $lang = vr_lang();
    if ($lang !== vr_config('primary_lang') && !isset($params['lang'])) {
        $params['lang'] = $lang;
    }
    $qs = $params ? '?' . http_build_query($params) : '';
    return $base . ($path === '/' ? '/' : $path) . $qs;
}

/** Mutlak URL (canonical, e-posta, Stripe redirect). */
function vr_abs(string $path = '', array $params = []): string
{
    return vr_origin() . vr_url($path, $params);
}

/** Kuruş → görüntülenecek para birimi. 12900 → "129,00 €" */
function vr_money(int $cents, bool $symbol = true): string
{
    $s = number_format($cents / 100, 2, ',', '.');
    // U+202F: sayı ile simge arasında bölünmeyen ince boşluk — satır sonunda
    // "129,00" ve "€" ayrı düşmesin.
    return $symbol ? $s . "\u{202F}" . vr_config('currency_symbol') : $s;
}

/** Stripe/JSON-LD için ondalık nokta: 12900 → "129.00" */
function vr_amount(int $cents): string
{
    return number_format($cents / 100, 2, '.', '');
}

/**
 * Cazip fiyata yuvarla: 10 €'luk ızgarada "…9,00" ile biten en YAKIN değere.
 * 17 970 → 17 900 (179,00 €) · 18 400 → 18 900 (189,00 €)
 *
 * En yakını seçiyoruz, yukarı değil: 3 × 59,90 = 179,70 iken 189,00 basmak
 * müşteriye ~9 € fazla, kataloğun tamamında sistematik bir sapma demekti.
 */
function vr_charm_round(int $cents): int
{
    $step = max(1, (int)vr_config('retail_round_to', 900));
    $unit = 1000;                                    // 10,00 € ızgarası
    $base = (int)floor($cents / $unit) * $unit;

    $low  = $base + $step;
    if ($low > $cents) $low -= $unit;                // alttaki cazip fiyat
    $high = $low + $unit;                            // üstteki cazip fiyat

    $out = ($cents - $low) <= ($high - $cents) ? $low : $high;
    return max((int)vr_config('retail_min_cents', 2900), $out);
}

/** Brüt fiyattan KDV payını çıkar (bilgi amaçlı gösterim). */
function vr_vat_part(int $grossCents): int
{
    $bps = (int)vr_config('vat_rate_bps', 1900);
    if ($bps <= 0) return 0;
    return (int)round($grossCents - ($grossCents * 10000 / (10000 + $bps)));
}

/** Güvenli id/slug. */
function vr_slug(string $s): string
{
    $s = strtolower(trim($s));
    $s = strtr($s, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', 'é' => 'e', 'è' => 'e', 'ç' => 'c', 'à' => 'a', 'ù' => 'u', 'ò' => 'o', 'í' => 'i', 'ñ' => 'n']);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-');
}

// -------------------------------------------------------------------- CSRF
function vr_csrf_token(): string
{
    vr_session_start();
    if (empty($_SESSION['vr_csrf'])) {
        $_SESSION['vr_csrf'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['vr_csrf'];
}

function vr_csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(vr_csrf_token()) . '">';
}

/** POST isteklerinde token doğrula. Hatalıysa 400 ile biter. */
function vr_csrf_check(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;

    $sent = (string)($_POST['_csrf'] ?? '');
    if ($sent === '' || !hash_equals(vr_csrf_token(), $sent)) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Sicherheitsprüfung fehlgeschlagen (CSRF). Bitte Seite neu laden.";
        exit;
    }
}

// ------------------------------------------------------------------- flash
function vr_flash(?string $msg = null, string $type = 'info'): array
{
    vr_session_start();
    if ($msg !== null) {
        $_SESSION['vr_flash'][] = ['msg' => $msg, 'type' => $type];
        return [];
    }
    $out = $_SESSION['vr_flash'] ?? [];
    unset($_SESSION['vr_flash']);
    return is_array($out) ? $out : [];
}

function vr_redirect(string $path, array $params = []): never
{
    // Yalnızca uygulama içi yollara yönlendiriyoruz — açık yönlendirme yok.
    header('Location: ' . vr_url($path, $params), true, 303);
    exit;
}

// ------------------------------------------------------------------- kayıt
/**
 * Olay/hata kaydı. Gizli anahtar, kart verisi ve tam e-posta adresi ASLA
 * yazılmaz — dosya sunucuda kalıyor ve gerekirse desteğe gönderiliyor.
 */
function vr_log(string $what, array $ctx = [], string $file = 'retail.log'): void
{
    $line = ['at' => gmdate('c'), 'what' => $what, 'ctx' => $ctx];
    $path = vr_data_dir() . '/' . $file;
    @file_put_contents($path, json_encode($line, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    @chmod($path, 0640);
}

// --------------------------------------------------------------- istemci IP
function vr_client_ip(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    // Proxy arkasındaysak ilk XFF girdisi; doğrulanmış bir değer değil, yalnızca
    // hız sınırı/kayıt amaçlı kullanıyoruz.
    $xff = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    if ($xff !== '') {
        $first = trim(explode(',', $xff)[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) $ip = $first;
    }
    return $ip;
}

/**
 * Basit hız sınırı (dosya tabanlı). Kayıt/giriş/ödeme uçlarında kaba kuvveti
 * yavaşlatmak için. $limit istek / $windowSec saniye.
 */
function vr_rate_ok(string $bucket, int $limit, int $windowSec): bool
{
    $key     = vr_slug($bucket . '-' . vr_client_ip());
    $now     = time();
    $allowed = true;                                   // depo yazamıyorsa engellemeyiz

    vr_store_mutate('retail-ratelimit.json', static function ($all) use ($key, $now, $limit, $windowSec, &$allowed) {
        if (!is_array($all)) $all = [];
        // Süresi geçmiş kovaları temizle (dosya sınırsız büyümesin).
        foreach ($all as $k => $v) {
            if (!is_array($v) || (int)($v['reset'] ?? 0) < $now - 86400) unset($all[$k]);
        }
        $cur = $all[$key] ?? null;
        if (!is_array($cur) || (int)($cur['reset'] ?? 0) <= $now) {
            $cur = ['count' => 0, 'reset' => $now + $windowSec];
        }
        $cur['count'] = (int)$cur['count'] + 1;
        $all[$key]    = $cur;
        $allowed      = $cur['count'] <= $limit;
        return $all;
    });

    return $allowed;
}

// ------------------------------------------------------------------- başlat
vr_session_start();
vr_security_headers();
vr_lang_init();
