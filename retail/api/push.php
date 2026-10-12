<?php
/**
 * Bildirim aboneliği (JSON)
 * -------------------------
 *   POST action=subscribe   sub=<PushSubscription JSON>   _csrf=…
 *   POST action=unsubscribe endpoint=<url>                 _csrf=…
 *
 * Abonelik yalnızca kullanıcı düğmeye basıp tarayıcının izin penceresinde
 * "İzin ver" dediğinde oluşur (bkz. assets/js/maxsales.js). Uç adres yalnızca
 * tarayıcı üreticilerinin push servislerinden biri olabilir (SSRF koruması —
 * vr_push_endpoint_ok).
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/push.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}
/* AYNI SITE KONTROLU, oturum jetonu DEGIL (12 Eki 2026). Kurulu uygulama saatlerce
   acik kalabiliyor; paylasimli sunucuda oturum dosyalari 24 dk bosta kalinca
   siliniyor ve jeton tutmayinca "Das hat nicht geklappt" cikiyordu -- canlida tek
   bir abonelik bile kaydedilmemisti. Tarayici bu istege Sec-Fetch-Site / Origin
   basliklarini kendisi koyar ve sayfa bunlari degistiremez; baska bir siteden
   gelen istek yine reddedilir. Baslik gondermeyen eski istemcide jeton yolu duruyor. */
$sfs    = strtolower((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
$host   = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$sameOrigin = $sfs === 'same-origin'
    || ($sfs === '' && $origin !== '' && strtolower((string)parse_url($origin, PHP_URL_HOST)) === preg_replace('/:\d+$/', '', $host));
if (in_array($sfs, ['cross-site', 'same-site'], true) || ($origin !== '' && !$sameOrigin)) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}
if (!$sameOrigin) vr_csrf_check();
if (!vr_rate_ok('push', 30, 600)) {
    http_response_code(429);
    echo json_encode(['ok' => false]);
    exit;
}

$action = (string)($_POST['action'] ?? '');
if ($action === 'subscribe') {
    $sub = json_decode((string)($_POST['sub'] ?? ''), true);
    $ep  = is_array($sub) ? (string)($sub['endpoint'] ?? '') : '';
    $new = $ep !== '' && !isset(vr_push_subs()[substr(hash('sha256', $ep), 0, 24)]);
    $key = is_array($sub) ? vr_push_subscribe($sub, vr_lang()) : null;
    if ($key === null) {
        vr_push_note_failure('server-reject', 'endpoint/keys', (string)parse_url($ep, PHP_URL_HOST));
        http_response_code(422); echo json_encode(['ok' => false]); exit;
    }
    // İlk kez açan cihaza tek bir karşılama bildirimi: hem "oldu" onayı hem de
    // yolun uçtan uca çalıştığının kanıtı. Sessiz tazelemelerde gönderilmez.
    if ($new) {
        try {
            vr_push_send_one(vr_push_subs()[$key] ?? [], [
                'title' => (string)vr_config('brand'),
                'body'  => t('push_welcome'),
                'url'   => '/outlet.php',
                'tag'   => 'sv-welcome',
            ]);
        } catch (Throwable) { /* karşılama gitmese de abonelik geçerli */ }
    }
    echo json_encode(['ok' => true, 'new' => $new]);
    exit;
}
/* Telefonda bir adim duserse NEDENI buraya yazilir (asama + hata adi + tarayici
   ailesi; kisisel veri yok). Teshis: diag-push.yml. */
if ($action === 'fail') {
    vr_push_note_failure((string)($_POST['stage'] ?? ''), (string)($_POST['err'] ?? ''), (string)($_POST['host'] ?? ''));
    echo json_encode(['ok' => true]);
    exit;
}
if ($action === 'unsubscribe') {
    vr_push_unsubscribe((string)($_POST['endpoint'] ?? ''));
    echo json_encode(['ok' => true]);
    exit;
}
http_response_code(400);
echo json_encode(['ok' => false]);
