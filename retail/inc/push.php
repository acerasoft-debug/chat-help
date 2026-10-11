<?php
/**
 * Bildirimler (Web Push) — bağımlılıksız
 * =====================================
 * Kurulu uygulamaya (iPhone/iPad ana ekran uygulaması iOS 16.4+, Android
 * Chrome, Windows/Mac Chrome-Edge-Safari) bildirim gönderir. Standartlar:
 *   • RFC 8291  — yük şifreleme (aes128gcm, ECDH P-256, HKDF-SHA256)
 *   • RFC 8292  — VAPID (sunucunun kimliği, ES256 imzalı JWT)
 *
 * Abonelikler data/push-subscriptions.json'da (uç adres + iki anahtar + dil).
 * VAPID anahtar çifti ilk ihtiyaçta sunucuda üretilir: data/push-vapid.json
 * (0600; web'den erişilemez — data/ dizini kök dışında). Depoya girmez.
 *
 * Kişisel veri: uç adres tarayıcı üreticisinin sunucusuna (Apple, Google,
 * Mozilla) işaret eden rastgele bir adres; kimlik, e-posta, konum yok.
 * Abonelik yalnızca kullanıcının açık onayıyla (düğme + tarayıcı izni)
 * oluşur, aynı yerden kapatılır. Bkz. Datenschutz → Push-Benachrichtigungen.
 */

declare(strict_types=1);

require_once __DIR__ . '/boot.php';

const VR_PUSH_SUBS  = 'push-subscriptions.json';
const VR_PUSH_VAPID = 'push-vapid.json';

/* ------------------------------------------------------------- yardımcılar */

function vr_b64u_enc(string $b): string { return rtrim(strtr(base64_encode($b), '+/', '-_'), '='); }
function vr_b64u_dec(string $s): string
{
    $s = strtr($s, '-_', '+/');
    return (string)base64_decode($s . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
}

/** HKDF (RFC 5869), SHA-256, tek blok (≤ 32 bayt çıktı — burada yeterli). */
function vr_hkdf(string $salt, string $ikm, string $info, int $len): string
{
    $prk = hash_hmac('sha256', $ikm, $salt, true);
    return substr(hash_hmac('sha256', $info . "\x01", $prk, true), 0, $len);
}

/** 65 baytlık sıkıştırılmamış P-256 noktası → PEM açık anahtar. */
function vr_p256_pub_pem(string $point): string
{
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $point;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/** openssl anahtarından 65 baytlık sıkıştırılmamış nokta. */
function vr_p256_point(\OpenSSLAsymmetricKey $k): string
{
    $d = openssl_pkey_get_details($k);
    $x = str_pad((string)$d['ec']['x'], 32, "\0", STR_PAD_LEFT);
    $y = str_pad((string)$d['ec']['y'], 32, "\0", STR_PAD_LEFT);
    return "\x04" . $x . $y;
}

/** DER kodlu ECDSA imzası → JWT'nin istediği 64 baytlık r||s. */
function vr_der_to_jose(string $der): string
{
    $off = 2;                                   // SEQUENCE başlığı (uzunluk < 128)
    if (ord($der[1]) & 0x80) $off += ord($der[1]) & 0x7f;
    $out = '';
    for ($i = 0; $i < 2; $i++) {
        $len = ord($der[$off + 1]);
        $int = substr($der, $off + 2, $len);
        $out .= str_pad(ltrim($int, "\0"), 32, "\0", STR_PAD_LEFT);
        $off += 2 + $len;
    }
    return $out;
}

/* ------------------------------------------------------------------- VAPID */

/** Sunucunun VAPID anahtarı: ['pem' => özel anahtar, 'pub' => b64url nokta]. */
function vr_push_vapid(): array
{
    static $v = null;
    if ($v !== null) return $v;
    $file = vr_data_dir() . '/' . VR_PUSH_VAPID;
    $j = is_readable($file) ? json_decode((string)file_get_contents($file), true) : null;
    if (!is_array($j) || empty($j['pem']) || empty($j['pub'])) {
        $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if (!$k) throw new RuntimeException('VAPID anahtarı üretilemedi (openssl EC desteği yok)');
        openssl_pkey_export($k, $pem);
        $j = ['pem' => $pem, 'pub' => vr_b64u_enc(vr_p256_point($k)), 'created' => gmdate('c')];
        $tmp = $file . '.' . getmypid() . '.tmp';
        file_put_contents($tmp, json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        @chmod($tmp, 0600);
        // Yarışta ikinci üreten yazmasın: önce gelenin anahtarı geçerli kalır
        // (aboneler o anahtarla kayıtlı).
        if (!@link($tmp, $file)) { @unlink($tmp); return $v = vr_push_vapid_reread($file); }
        @unlink($tmp);
    }
    return $v = $j;
}

function vr_push_vapid_reread(string $file): array
{
    $j = json_decode((string)@file_get_contents($file), true);
    if (!is_array($j) || empty($j['pem'])) throw new RuntimeException('VAPID dosyası okunamadı');
    return $j;
}

/** Tarayıcıya verilecek açık anahtar (applicationServerKey). */
function vr_push_public_key(): string { return (string)vr_push_vapid()['pub']; }

/** VAPID yetki başlığı (RFC 8292). */
function vr_push_vapid_header(string $endpoint): string
{
    $v = vr_push_vapid();
    $u = parse_url($endpoint);
    $aud = ($u['scheme'] ?? 'https') . '://' . ($u['host'] ?? '') . (isset($u['port']) ? ':' . $u['port'] : '');
    $mail = (string)(vr_config('company')['email'] ?? '') ?: 'support@' . (parse_url(vr_origin(), PHP_URL_HOST) ?: 'sarvesto.com');
    $h = vr_b64u_enc(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $c = vr_b64u_enc(json_encode(['aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => 'mailto:' . $mail], JSON_UNESCAPED_SLASHES));
    $key = openssl_pkey_get_private((string)$v['pem']);
    openssl_sign($h . '.' . $c, $der, $key, OPENSSL_ALGO_SHA256);
    return 'vapid t=' . $h . '.' . $c . '.' . vr_b64u_enc(vr_der_to_jose($der)) . ', k=' . $v['pub'];
}

/* -------------------------------------------------------------- şifreleme */

/**
 * RFC 8291 aes128gcm gövdesi. $salt ve $eph yalnızca testte verilir.
 * @param string $uaPub  abonenin p256dh (65 bayt, ham)
 * @param string $auth   abonenin auth sırrı (16 bayt, ham)
 */
function vr_push_encrypt(string $payload, string $uaPub, string $auth, ?string $salt = null, ?\OpenSSLAsymmetricKey $eph = null): string
{
    if (strlen($uaPub) !== 65 || $uaPub[0] !== "\x04" || strlen($auth) !== 16) {
        throw new InvalidArgumentException('geçersiz abonelik anahtarı');
    }
    $eph  ??= openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $salt ??= random_bytes(16);
    $asPub = vr_p256_point($eph);

    $secret = openssl_pkey_derive(openssl_pkey_get_public(vr_p256_pub_pem($uaPub)), $eph, 32);
    if ($secret === false) throw new RuntimeException('ECDH başarısız');

    $ikm   = vr_hkdf($auth, $secret, "WebPush: info\0" . $uaPub . $asPub, 32);
    $cek   = vr_hkdf($salt, $ikm, "Content-Encoding: aes128gcm\0", 16);
    $nonce = vr_hkdf($salt, $ikm, "Content-Encoding: nonce\0", 12);

    $ct = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    if ($ct === false) throw new RuntimeException('AES-GCM başarısız');
    return $salt . pack('N', 4096) . chr(65) . $asPub . $ct . $tag;
}

/* ------------------------------------------------------------- abonelikler */

function vr_push_subs(): array
{
    $s = vr_store_read(VR_PUSH_SUBS, []);
    return is_array($s) ? $s : [];
}

/** Uç adresin güvenli olduğu (yalnızca bilinen push servisleri, https). */
function vr_push_endpoint_ok(string $ep): bool
{
    $u = parse_url($ep);
    if (($u['scheme'] ?? '') !== 'https' || empty($u['host'])) return false;
    // SSRF koruması: sunucu yalnızca tarayıcı üreticilerinin push servislerine
    // istek atar; abonelik formuyla rastgele bir adrese istek yaptırılamaz.
    return (bool)preg_match('/(^|\.)(push\.apple\.com|fcm\.googleapis\.com|android\.googleapis\.com|push\.services\.mozilla\.com|notify\.windows\.com|push\.api\.chromium\.org)$/i', $u['host']);
}

/** Aboneliği kaydet/güncelle. Dönen: anahtar (uç adresin özeti). */
function vr_push_subscribe(array $sub, string $lang): ?string
{
    $ep   = (string)($sub['endpoint'] ?? '');
    $p256 = (string)($sub['keys']['p256dh'] ?? '');
    $auth = (string)($sub['keys']['auth'] ?? '');
    if (!vr_push_endpoint_ok($ep) || strlen(vr_b64u_dec($p256)) !== 65 || strlen(vr_b64u_dec($auth)) !== 16) return null;
    $key = substr(hash('sha256', $ep), 0, 24);
    vr_store_mutate(VR_PUSH_SUBS, function (array $all) use ($key, $ep, $p256, $auth, $lang) {
        $all[$key] = [
            'endpoint' => $ep, 'p256dh' => $p256, 'auth' => $auth,
            'lang'     => $lang,
            'created'  => $all[$key]['created'] ?? gmdate('c'),
            'seen'     => gmdate('c'),
        ];
        return $all;
    }, []);
    return $key;
}

function vr_push_unsubscribe(string $endpoint): void
{
    $key = substr(hash('sha256', $endpoint), 0, 24);
    vr_store_mutate(VR_PUSH_SUBS, function (array $all) use ($key) { unset($all[$key]); return $all; }, []);
}

/* ------------------------------------------------------------------- gönder */

/**
 * Tek aboneye gönder. Dönen: HTTP kodu (201 = teslim alındı; 404/410 =
 * abonelik bitmiş — çağıran siler).
 */
function vr_push_send_one(array $s, array $msg): int
{
    $body = vr_push_encrypt(json_encode($msg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            vr_b64u_dec((string)$s['p256dh']), vr_b64u_dec((string)$s['auth']));
    $ch = curl_init((string)$s['endpoint']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'TTL: 86400',
            'Urgency: normal',
            'Authorization: ' . vr_push_vapid_header((string)$s['endpoint']),
        ],
    ]);
    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code;
}

/**
 * Herkese (ya da bir dildeki abonelere) gönder.
 * @return array{sent:int, failed:int, removed:int, total:int}
 */
function vr_push_broadcast(string $title, string $body, string $url, string $lang = ''): array
{
    $msg = [
        'title' => mb_substr(trim($title), 0, 80),
        'body'  => mb_substr(trim($body), 0, 240),
        'url'   => $url,
        'tag'   => 'sv-' . substr(sha1($title . $body . $url), 0, 10),
    ];
    $r = ['sent' => 0, 'failed' => 0, 'removed' => 0, 'total' => 0];
    $dead = [];
    foreach (vr_push_subs() as $key => $s) {
        if ($lang !== '' && ($s['lang'] ?? '') !== $lang) continue;
        $r['total']++;
        try { $code = vr_push_send_one($s, $msg); } catch (Throwable) { $code = 0; }
        if ($code >= 200 && $code < 300) $r['sent']++;
        elseif ($code === 404 || $code === 410) { $dead[] = $key; $r['removed']++; }
        else $r['failed']++;
    }
    if ($dead) {
        vr_store_mutate(VR_PUSH_SUBS, function (array $all) use ($dead) {
            foreach ($dead as $k) unset($all[$k]);
            return $all;
        }, []);
    }
    vr_store_mutate('push-log.json', function (array $log) use ($msg, $lang, $r) {
        array_unshift($log, ['at' => gmdate('c'), 'lang' => $lang, 'title' => $msg['title'], 'url' => $msg['url']] + $r);
        return array_slice($log, 0, 100);
    }, []);
    return $r;
}
