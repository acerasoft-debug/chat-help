<?php
/**
 * VESTRA — Web Push endpoint (the app's side of inc/push.php).
 *
 *   GET  /push?a=vapid        {publicKey}                      anyone
 *   POST /push?a=subscribe    link this browser to the signed-in account
 *   POST /push?a=unsubscribe  unlink this browser               (panel "Turn off")
 *   POST /push?a=sync         "whose is this subscription?" → on | relinked | off
 *   POST /push?a=test         one test notification to THIS device only
 *   POST /push?a=renew        service worker: the browser rotated the subscription
 *   POST /push?a=pending      service worker: queued notification for this device
 *   GET  /push?a=pending      service workers older than v3 (per-account queue)
 *
 * Bodies are the browser's own PushSubscription JSON ({endpoint, keys:{p256dh, auth}}).
 * The session cookie is SameSite=Lax, so a cross-site page cannot ride it here.
 */
require_once __DIR__.'/inc/auth.php';
require_once __DIR__.'/inc/push.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$a = (string)($_GET['a'] ?? '');
$post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
$out = function (array $j, int $code = 200): void { http_response_code($code); echo json_encode($j); exit; };

if ($a === 'vapid') {
    $k = vestra_vapid_keys();
    $out(['publicKey' => $k['publicKey'] ?? '']);
}

/* A PushSubscription is a few hundred bytes; anything larger is not one. */
$raw = $post ? (string)file_get_contents('php://input', false, null, 0, 8192) : '';
$in = $raw !== '' ? json_decode($raw, true) : null;
$in = is_array($in) ? $in : [];
$uid = (string)($_SESSION['uid'] ?? '');

/* Service-worker endpoints first: they may run without a session (the device's
   own auth secret stands in for it — only that browser and this server know it). */
if ($a === 'pending') {
    if (!$post) {                                   // service worker < v3
        if ($uid === '') $out(['error' => 'signin'], 401);
        $out(['notifs' => vestra_push_pending_take($uid)]);
    }
    $h = vestra_push_ep_hash((string)($in['endpoint'] ?? ''));
    $own = vestra_push_owner($h);
    $ok = $own && ($own[0] === $uid || vestra_push_auth_matches($own[1], (string)($in['auth'] ?? '')));
    if (!$ok) $out(['error' => 'unknown_device'], 403);
    $out(['notifs' => vestra_push_queue_take($h)]);
}

if ($a === 'renew' && $post) {
    $sub = (array)($in['sub'] ?? []);
    $old = (array)($in['old'] ?? []);
    $label = vestra_push_device_label((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $owner = $uid;
    if ($owner === '' && !empty($old['endpoint'])) {
        $o = vestra_push_owner(vestra_push_ep_hash((string)$old['endpoint']));
        if ($o && vestra_push_auth_matches($o[1], (string)($old['auth'] ?? ''))) $owner = $o[0];
    }
    if ($owner === '') $out(['error' => 'signin'], 401);
    $h = vestra_push_link($owner, $sub, $label);
    if ($h === '') $out(['error' => 'refused'], 400);
    if (!empty($old['endpoint'])) {
        $oh = vestra_push_ep_hash((string)$old['endpoint']);
        if ($oh !== $h) vestra_push_unlink($owner, $oh);
    }
    $out(['ok' => true]);
}

if ($uid === '') $out(['error' => 'signin'], 401);
if (!$post) $out(['error' => 'bad_request'], 400);

switch ($a) {
    case 'subscribe':
        $h = vestra_push_link($uid, $in, vestra_push_device_label((string)($_SERVER['HTTP_USER_AGENT'] ?? '')));
        if ($h === '') $out(['ok' => false, 'error' => 'refused'], 400);
        vestra_push_session_stamp($h);
        $out(['ok' => true, 'state' => 'on']);

    case 'unsubscribe':
        $h = vestra_push_ep_hash((string)($in['endpoint'] ?? ''));
        vestra_push_unlink($uid, $h);
        if (is_array($_SESSION['push_dev'] ?? null)) $_SESSION['push_dev'] = array_values(array_diff($_SESSION['push_dev'], [$h]));
        $out(['ok' => true, 'state' => 'off']);

    case 'sync':
        $out(['state' => vestra_push_sync($uid, $in)]);

    case 'test':
        $h = vestra_push_ep_hash((string)($in['endpoint'] ?? ''));
        /* "Is it mine", not "who is first": a device v2 wrote under two accounts
           answered 409 to the second one even while linked to it. */
        if (!vestra_push_holds($uid, $h)) $out(['ok' => false, 'error' => 'not_linked'], 409);
        /* Push services rate-limit per sender; a test button someone keeps tapping
           must not spend that budget. */
        if (time() - (int)($_SESSION['push_test_at'] ?? 0) < 20) $out(['ok' => false, 'error' => 'wait'], 429);
        $_SESSION['push_test_at'] = time();
        require_once __DIR__.'/inc/push_texts.php';
        $acc = auth_user();
        $panel = (($acc['type'] ?? '') === 'seller') ? '/seller' : '/buyer';
        $n = vestra_push_compose('test', ['url' => $panel.'?tab=profile#notifications'], vestra_push_lang($acc));
        $r = $n ? vestra_push_deliver($uid, $n, $h) : ['ok' => 0];
        $out(['ok' => $r['ok'] > 0, 'error' => $r['ok'] > 0 ? '' : 'push_service']);
}

$out(['error' => 'bad_request'], 400);
