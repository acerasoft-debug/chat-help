<?php
/**
 * VESTRA — dependency-free Web Push (VAPID / RFC 8292 + payload encryption / RFC 8291).
 *
 * Same philosophy as inc/stripe.php and inc/pdf.php: no composer, no SDK.
 *
 * THE NOTIFICATION TRAVELS INSIDE THE PUSH (since 27 Sep 2026). The first version
 * sent PAYLOADLESS pushes: the service worker woke up and fetched the text from
 * /push?a=pending with the session cookie. That had three failure modes, all of
 * which ended in the same vague English "You have news on VESTRA.":
 *   - two devices: the queue was per ACCOUNT and the first device to wake up
 *     took everything — the phone showed the order, the laptop showed nothing;
 *   - the queue kept one hour, the push service keeps a message 24 hours: a phone
 *     that was off for two hours woke up to an empty queue;
 *   - no session (signed out, cookie gone) → 401 → the vague line again.
 * Now each device gets its own copy, encrypted to that device's key (aes128gcm,
 * RFC 8291). The push service cannot read it; only that browser can. The queue
 * remains as a fallback for a device whose keys we cannot use, and a per-account
 * copy is still written for service workers older than v3 until they update.
 *
 * Crypto used: ECDH P-256 (openssl_pkey_derive), HKDF-SHA256 (hash_hkdf),
 * AES-128-GCM (openssl_encrypt), ES256 JWT for VAPID (openssl_sign). The encryptor
 * is pinned to the RFC 8291 Appendix A test vector in tests/push_crypto_test.php.
 *
 * Storage (all under data/, never web-accessible):
 *   vapid.json          P-256 keypair, generated once on first use
 *   push_subs.json      { uid: { deviceHash: {endpoint, keys, label, added, last_*} } }
 *   push_parked.json    { deviceHash: {uid, at, keys} }  device of a user who signed out
 *   push_queue.json     { deviceHash: [notif, …] }       fallback when a payload can't be sent
 *   push_pending.json   { uid: [notif, …] }              legacy queue, service workers < v3
 *   push_log.json       operator broadcasts
 */

function vestra_push_dir(): string {
    /* Overridable for tests, like VESTRA_ACCOUNTS: without it a test would write the
       real device registry. */
    return defined('VESTRA_PUSH_DIR') ? (string)VESTRA_PUSH_DIR : dirname(__DIR__).'/data';
}

/* ── JSON stores, read-modify-write under ONE lock ─────────────────────────
   Two requests used to be able to read push_subs.json, change different
   entries and write it back — the second write silently dropped the first
   one's change. A device subscribed while a push was pruning another one
   simply vanished, and nobody would ever have known why its notifications
   stopped. Network I/O never happens inside the lock. */
function vestra_push_store_read(string $name): array {
    $f = vestra_push_dir().'/'.$name;
    if (!is_readable($f)) return [];
    $v = json_decode((string)file_get_contents($f), true);
    return is_array($v) ? $v : [];
}
function vestra_push_store_update(string $name, callable $fn): array {
    $dir = vestra_push_dir();
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $path = $dir.'/'.$name;
    $lk = @fopen($path.'.lock', 'c');
    if ($lk) @flock($lk, LOCK_EX);
    try {
        $cur = vestra_push_store_read($name);
        $new = $fn($cur);
        if (!is_array($new)) $new = $cur;
        if ($new !== $cur) {
            @file_put_contents($path, json_encode($new, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
        }
        return $new;
    } finally {
        if ($lk) { @flock($lk, LOCK_UN); @fclose($lk); }
    }
}

/* ── base64url ──────────────────────────────────────────────────────────── */
function vestra_b64url(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function vestra_b64url_dec(string $s): string {
    $s = strtr(trim($s), '-_', '+/');
    if (strlen($s) % 4) $s .= str_repeat('=', 4 - strlen($s) % 4);
    $v = base64_decode($s, true);
    return $v === false ? '' : $v;
}

/* ── VAPID keypair (auto-generated once, under a lock) ─────────────────────
   Two first requests racing used to generate two different keys; subscriptions
   made against the losing one would have been refused (403) forever after. */
function vestra_vapid_keys(): array {
    $ok = fn($k) => is_array($k) && !empty($k['publicKey']) && !empty($k['privatePem']);
    $k = vestra_push_store_read('vapid.json');
    if ($ok($k)) return $k;
    $out = vestra_push_store_update('vapid.json', function (array $cur) use ($ok) {
        if ($ok($cur)) return $cur;
        $res = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if (!$res || !openssl_pkey_export($res, $pem)) return $cur;
        $d = openssl_pkey_get_details($res);
        return ['publicKey' => vestra_b64url(vestra_push_raw_point($d)), 'privatePem' => $pem];
    });
    return $ok($out) ? $out : [];
}

/** Uncompressed EC point 0x04 || X(32) || Y(32) from openssl_pkey_get_details(). */
function vestra_push_raw_point(array $details): string {
    return "\x04".str_pad((string)($details['ec']['x'] ?? ''), 32, "\0", STR_PAD_LEFT)
                 .str_pad((string)($details['ec']['y'] ?? ''), 32, "\0", STR_PAD_LEFT);
}

/* ── VAPID JWT (ES256) for one push-service origin ──────────────────────── */
function vestra_vapid_jwt(string $audience): string {
    $k = vestra_vapid_keys();
    if (!$k) return '';
    $seg = fn(array $a) => vestra_b64url(json_encode($a, JSON_UNESCAPED_SLASHES));
    $data = $seg(['typ' => 'JWT', 'alg' => 'ES256']).'.'
          . $seg(['aud' => $audience, 'exp' => time() + 43200, 'sub' => 'mailto:support@vestrasales.com']);
    if (!openssl_sign($data, $der, $k['privatePem'], OPENSSL_ALGO_SHA256)) return '';
    // DER ECDSA-Sig-Value → raw JOSE r||s (32+32 bytes)
    $off = 3; // SEQUENCE, len, INTEGER tag
    $rl = ord($der[$off]); $r = substr($der, $off + 1, $rl);
    $off += 1 + $rl + 1; $sl = ord($der[$off]); $s = substr($der, $off + 1, $sl);
    $pad = fn(string $i) => str_pad(ltrim($i, "\0"), 32, "\0", STR_PAD_LEFT);
    return $data.'.'.vestra_b64url($pad($r).$pad($s));
}

/* ── Payload encryption (RFC 8291 over RFC 8188 aes128gcm) ─────────────── */

/** Raw uncompressed P-256 point → OpenSSL public key (SubjectPublicKeyInfo). */
function vestra_push_p256_public(string $raw) {
    if (strlen($raw) !== 65 || $raw[0] !== "\x04") return false;
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').$raw;
    return openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END PUBLIC KEY-----\n");
}

/**
 * Encrypt one message for one browser subscription. Returns the request body
 * (salt ‖ rs ‖ idlen ‖ server public key ‖ ciphertext+tag), or '' when it
 * cannot be done — the caller then falls back to a payloadless push.
 * $asKey/$salt exist only so the RFC 8291 test vector can be reproduced; in
 * production every message gets a fresh ephemeral key and a random salt.
 */
function vestra_push_encrypt(string $plain, string $p256dh, string $auth, $asKey = null, ?string $salt = null): string {
    if (!function_exists('openssl_pkey_derive') || !function_exists('hash_hkdf')) return '';
    $uaPub = vestra_b64url_dec($p256dh);
    $secret = vestra_b64url_dec($auth);
    if (strlen($uaPub) !== 65 || strlen($secret) !== 16) return '';
    /* One record only: 4096 − header(86) − delimiter(1) − tag(16). Push services
       refuse bodies over 4096 bytes, and a refused push is a silent one. */
    if (strlen($plain) > 3993) return '';
    $uaKey = vestra_push_p256_public($uaPub);
    if (!$uaKey) return '';
    $as = $asKey ?: openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    if (!$as) return '';
    $asPub = vestra_push_raw_point(openssl_pkey_get_details($as));
    $ecdh = @openssl_pkey_derive($uaKey, $as, 32);
    if (!is_string($ecdh) || strlen($ecdh) !== 32) return '';
    $salt = $salt ?? random_bytes(16);
    if (strlen($salt) !== 16) return '';
    $ikm   = hash_hkdf('sha256', $ecdh, 32, "WebPush: info\0".$uaPub.$asPub, $secret);
    $cek   = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
    $tag = '';
    // 0x02 = padding delimiter of the last (and only) record, RFC 8188 §2.
    $ct = openssl_encrypt($plain."\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    if ($ct === false || strlen($tag) !== 16) return '';
    return $salt.pack('N', 4096).chr(65).$asPub.$ct.$tag;
}

/* ── Devices ────────────────────────────────────────────────────────────── */

function vestra_push_ep_hash(string $endpoint): string { return substr(sha1($endpoint), 0, 16); }

/**
 * Only real push services. The endpoint is a URL the BROWSER hands us and the
 * server then POSTs to — accepting any https:// URL let a signed-in account make
 * this server call an address of its choosing (an internal one included). The
 * four suffixes cover Chrome/Android/Opera/Samsung/Brave (FCM), Firefox
 * (Mozilla autopush), Safari/iOS (Apple) and Edge (Windows push). A browser
 * outside the list is logged, so the list can be extended instead of guessed.
 */
function vestra_push_endpoint_ok(string $endpoint): bool {
    $p = parse_url($endpoint);
    if (!is_array($p) || strtolower($p['scheme'] ?? '') !== 'https' || empty($p['host'])) return false;
    if (isset($p['port']) && (int)$p['port'] !== 443) return false;
    if (isset($p['user']) || isset($p['pass'])) return false;
    $host = strtolower($p['host']);
    if (filter_var($host, FILTER_VALIDATE_IP) || str_starts_with($host, '[')) return false;
    $allowed = ['.googleapis.com', '.mozilla.com', '.push.apple.com', '.notify.windows.com'];
    /* A browser outside the four (a push service we have not met yet) is added in
       inc/config.php — define('VESTRA_PUSH_EXTRA_HOSTS', '.example-push.net') — not here. */
    if (defined('VESTRA_PUSH_EXTRA_HOSTS')) {
        foreach (preg_split('/[\s,]+/', (string)VESTRA_PUSH_EXTRA_HOSTS, -1, PREG_SPLIT_NO_EMPTY) as $x) {
            $allowed[] = '.'.ltrim(strtolower($x), '.');
        }
    }
    foreach ($allowed as $suffix) {
        if (str_ends_with($host, $suffix)) return true;
    }
    return false;
}

/** Coarse device label for the operator's device list ("Android · Chrome"). Never the raw UA. */
function vestra_push_device_label(string $ua): string {
    $os = match (true) {
        (bool)preg_match('/iPhone|iPad|iPod/i', $ua)   => 'iPhone/iPad',
        (bool)preg_match('/Android/i', $ua)            => 'Android',
        (bool)preg_match('/Windows/i', $ua)            => 'Windows',
        (bool)preg_match('/Macintosh|Mac OS X/i', $ua) => 'Mac',
        (bool)preg_match('/Linux|CrOS/i', $ua)         => 'Linux',
        default => '',
    };
    $br = match (true) {
        (bool)preg_match('/Edg\//', $ua)                          => 'Edge',
        (bool)preg_match('/OPR\//', $ua)                          => 'Opera',
        (bool)preg_match('/SamsungBrowser/', $ua)                 => 'Samsung Internet',
        (bool)preg_match('/Firefox\//', $ua)                      => 'Firefox',
        (bool)preg_match('/Chrome\//', $ua)                       => 'Chrome',
        (bool)preg_match('/Safari\//', $ua)                       => 'Safari',
        default => '',
    };
    return trim($os.($os !== '' && $br !== '' ? ' · ' : '').$br);
}

function vestra_push_subs(): array { return vestra_push_store_read('push_subs.json'); }

/** [uid, record] of the account a device belongs to, or null. */
function vestra_push_owner(string $hash): ?array {
    foreach (vestra_push_subs() as $u => $devs) {
        if (is_array($devs) && isset($devs[$hash])) return [(string)$u, (array)$devs[$hash]];
    }
    return null;
}

/**
 * Link one browser subscription to one account. A device belongs to ONE account:
 * the same endpoint is removed from any other account first — otherwise that
 * account's (now readable) notifications would keep arriving on a device someone
 * else is signed in on. Returns the device hash, or '' when refused.
 */
function vestra_push_link(string $uid, array $sub, string $label = ''): string {
    $ep = (string)($sub['endpoint'] ?? '');
    if ($uid === '' || !vestra_push_endpoint_ok($ep)) {
        if ($ep !== '') error_log('[VESTRA Push] endpoint refused: '.(parse_url($ep, PHP_URL_HOST) ?: '?'));
        return '';
    }
    $keys = [];
    $p = (string)($sub['keys']['p256dh'] ?? ''); $a = (string)($sub['keys']['auth'] ?? '');
    if (strlen(vestra_b64url_dec($p)) === 65 && strlen(vestra_b64url_dec($a)) === 16) $keys = ['p256dh' => $p, 'auth' => $a];
    $h = vestra_push_ep_hash($ep);
    vestra_push_store_update('push_subs.json', function (array $all) use ($uid, $h, $ep, $keys, $label, $sub) {
        foreach ($all as $u => $devs) {
            if ((string)$u !== $uid && is_array($devs) && isset($devs[$h])) {
                unset($all[$u][$h]);
                if (!$all[$u]) unset($all[$u]);
            }
        }
        $prev = is_array($all[$uid][$h] ?? null) ? $all[$uid][$h] : [];
        $rec = ['endpoint' => $ep, 'expirationTime' => $sub['expirationTime'] ?? null];
        if ($keys) $rec['keys'] = $keys;
        $rec['added'] = $prev['added'] ?? date('c');
        $rec['label'] = $label !== '' ? $label : (string)($prev['label'] ?? '');
        foreach (['last_ok', 'last_at', 'last_code', 'fails'] as $f) if (isset($prev[$f])) $rec[$f] = $prev[$f];
        $all[$uid][$h] = $rec;
        return $all;
    });
    vestra_push_store_update('push_parked.json', function (array $p) use ($h) { unset($p[$h]); return $p; });
    return $h;
}

/**
 * Remove one device from one account. $park keeps a note that this account owned
 * it, so the SAME account signing in again on that device is relinked without a
 * second opt-in — while a different account signing in inherits nothing.
 */
function vestra_push_unlink(string $uid, string $hash, bool $park = false): bool {
    $gone = null;
    vestra_push_store_update('push_subs.json', function (array $all) use ($uid, $hash, &$gone) {
        if (!isset($all[$uid][$hash])) return $all;
        $gone = $all[$uid][$hash];
        unset($all[$uid][$hash]);
        if (!$all[$uid]) unset($all[$uid]);
        return $all;
    });
    if ($gone === null) return false;
    if ($park) {
        vestra_push_store_update('push_parked.json', function (array $p) use ($uid, $hash, $gone) {
            $p[$hash] = ['uid' => $uid, 'at' => time(), 'rec' => $gone];
            foreach ($p as $k => $v) if (($v['at'] ?? 0) < time() - 90 * 86400) unset($p[$k]);
            return $p;
        });
    }
    vestra_push_store_update('push_queue.json', function (array $q) use ($hash) { unset($q[$hash]); return $q; });
    return true;
}

/** Does $auth (base64url) match the stored secret of a device record? Constant time. */
function vestra_push_auth_matches(array $rec, string $auth): bool {
    $have = (string)($rec['keys']['auth'] ?? '');
    return $have !== '' && $auth !== '' && hash_equals(vestra_b64url_dec($have), vestra_b64url_dec($auth));
}

/** Does this account hold this device? (A device CAN sit under two accounts in
    records written by v2 — see vestra_push_sync — so "the owner" is not always one.) */
function vestra_push_holds(string $uid, string $hash): bool {
    return $uid !== '' && isset(vestra_push_subs()[$uid][$hash]);
}

/**
 * The page asks, once per session: "this browser has subscription X — whose is it?"
 *   'on'       linked to you (session stamped, so signing out can unlink it)
 *   'relinked' you had signed out on this device before; linked again
 *   'off'      not linked (a different account's link is removed, see below)
 * Removing another account's link needs the device's own auth secret, which only
 * that browser holds — a leaked endpoint URL alone cannot unlink anybody.
 *
 * v2 could write the SAME browser under several accounts; the live probe of
 * 27 Sep 2026 found 1 device of 6 like that. Asking "who is THE owner" returned
 * whichever account came first in the file, so the account signed in on the
 * device either read 'off' while still linked, or read 'on' and left the other
 * account's link in place — that account's notifications kept arriving here.
 * The question is "is it MINE", and every other account's link to this browser
 * is cleared by the browser's own secret (parked for them, not inherited).
 */
function vestra_push_sync(string $uid, array $sub): string {
    $ep = (string)($sub['endpoint'] ?? '');
    $auth = (string)($sub['keys']['auth'] ?? '');
    if ($uid === '' || $ep === '') return 'off';
    $h = vestra_push_ep_hash($ep);
    $all = vestra_push_subs();
    foreach ($all as $u => $devs) {
        $u = (string)$u;
        if ($u === $uid || !is_array($devs) || !is_array($devs[$h] ?? null)) continue;
        if (vestra_push_auth_matches($devs[$h], $auth)) vestra_push_unlink($u, $h, true);
    }
    $mine = is_array($all[$uid][$h] ?? null) ? $all[$uid][$h] : null;
    if ($mine !== null) {
        /* Keys can rotate under the same endpoint; keep what the browser has now —
           in THIS account's record only. Re-linking would clear every other
           account's link without the secret having proven anything about them. */
        $p = (string)($sub['keys']['p256dh'] ?? '');
        if ($auth !== '' && !vestra_push_auth_matches($mine, $auth)
            && strlen(vestra_b64url_dec($p)) === 65 && strlen(vestra_b64url_dec($auth)) === 16) {
            vestra_push_store_update('push_subs.json', function (array $s) use ($uid, $h, $p, $auth) {
                if (isset($s[$uid][$h])) $s[$uid][$h]['keys'] = ['p256dh' => $p, 'auth' => $auth];
                return $s;
            });
        }
        vestra_push_session_stamp($h);
        return 'on';
    }
    $parked = vestra_push_store_read('push_parked.json')[$h] ?? null;
    if (is_array($parked) && ($parked['uid'] ?? '') === $uid && vestra_push_auth_matches((array)($parked['rec'] ?? []), $auth)) {
        if (vestra_push_link($uid, $sub, (string)($parked['rec']['label'] ?? '')) !== '') {
            vestra_push_session_stamp($h);
            return 'relinked';
        }
    }
    return 'off';
}

/** Remember in the session which device(s) this sign-in linked, so sign-out can unlink them. */
function vestra_push_session_stamp(string $hash): void {
    if (session_status() !== PHP_SESSION_ACTIVE) return;
    $list = is_array($_SESSION['push_dev'] ?? null) ? $_SESSION['push_dev'] : [];
    if (!in_array($hash, $list, true)) $list[] = $hash;
    $_SESSION['push_dev'] = array_slice($list, -8);
}

/** Called by auth_logout(): the devices this session linked stop receiving this account's pushes. */
function vestra_push_signout(string $uid): void {
    $list = is_array($_SESSION['push_dev'] ?? null) ? $_SESSION['push_dev'] : [];
    foreach ($list as $h) if (is_string($h) && $uid !== '') vestra_push_unlink($uid, $h, true);
    unset($_SESSION['push_dev']);
}

/* Kept for callers written against the first version. */
function vestra_push_subscribe(string $uid, array $sub): bool { return vestra_push_link($uid, $sub) !== ''; }
function vestra_push_save_subs(array $s): void { vestra_push_store_update('push_subs.json', fn() => $s); }

/* ── Queues ─────────────────────────────────────────────────────────────── */

/** Legacy per-account queue — read only by service workers older than v3. */
function vestra_push_pending_add(string $uid, array $notif): void {
    vestra_push_store_update('push_pending.json', function (array $all) use ($uid, $notif) {
        $all[$uid][] = ['title' => (string)($notif['title'] ?? ''), 'body' => (string)($notif['body'] ?? ''),
                        'url' => (string)($notif['url'] ?? '/'), 'at' => time()];
        $all[$uid] = array_slice($all[$uid], -5);
        foreach ($all as $u => $l) {
            $all[$u] = array_values(array_filter((array)$l, fn($n) => ($n['at'] ?? 0) > time() - 86400));
            if (!$all[$u]) unset($all[$u]);
        }
        return $all;
    });
}
function vestra_push_pending_take(string $uid): array {
    $out = [];
    vestra_push_store_update('push_pending.json', function (array $all) use ($uid, &$out) {
        $out = (array)($all[$uid] ?? []);
        unset($all[$uid]);
        return $all;
    });
    return array_values(array_filter($out, fn($n) => ($n['at'] ?? 0) > time() - 86400));
}

/** Per-DEVICE queue: only for a push that had to go out without a payload. */
function vestra_push_queue_add(string $hash, array $notif): void {
    vestra_push_store_update('push_queue.json', function (array $q) use ($hash, $notif) {
        $q[$hash][] = $notif + ['at' => time()];
        $q[$hash] = array_slice($q[$hash], -5);
        return $q;
    });
}
function vestra_push_queue_take(string $hash): array {
    $out = [];
    vestra_push_store_update('push_queue.json', function (array $q) use ($hash, &$out) {
        $out = (array)($q[$hash] ?? []);
        unset($q[$hash]);
        return $q;
    });
    $ttl = VESTRA_PUSH_TTL;
    return array_values(array_filter($out, fn($n) => ($n['at'] ?? 0) > time() - $ttl));
}

/* ── Delivery ───────────────────────────────────────────────────────────── */

/* How long a push service keeps a message for a device that is offline. The first
   version said one day; a phone switched off over a weekend lost Friday's order. */
if (!defined('VESTRA_PUSH_TTL')) define('VESTRA_PUSH_TTL', 3 * 86400);

/**
 * POST one push. Returns the HTTP status (201 = accepted), -1 on a network error,
 * -2 when no VAPID token could be made. Tests replace the transport through
 * $GLOBALS['vestra_push_transport'] (callable: endpoint, headers, body → status).
 */
function vestra_push_post(string $endpoint, string $body, int $ttl, string $urgency): int {
    $keys = vestra_vapid_keys();
    $jwt = vestra_vapid_jwt((string)preg_replace('#^(https://[^/]+).*$#', '$1', $endpoint));
    if (!$keys || $jwt === '') return -2;
    /* No Topic header, on purpose: it would only save bandwidth (the notification
       `tag` already collapses a thread's messages on the device), and a push
       service that rejected it would fail every tagged push without a sound —
       not something that can be tested from here against Apple or Microsoft. */
    $h = ['Authorization: vapid t='.$jwt.', k='.$keys['publicKey'], 'TTL: '.$ttl, 'Urgency: '.$urgency];
    if ($body !== '') { $h[] = 'Content-Type: application/octet-stream'; $h[] = 'Content-Encoding: aes128gcm'; }
    else $h[] = 'Content-Length: 0';
    $t = $GLOBALS['vestra_push_transport'] ?? null;
    if (is_callable($t)) return (int)$t($endpoint, $h, $body);
    if (!function_exists('curl_init')) return -1;
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_HTTPHEADER => $h,
    ]);
    curl_exec($ch);
    $code = curl_errno($ch) ? -1 : (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    unset($ch);
    return $code;
}

/** Keep what the service worker needs, and only that; bounded so it always fits one push. */
function vestra_push_payload(array $n): array {
    $url = (string)($n['url'] ?? '/');
    if ($url === '' || $url[0] !== '/' || str_starts_with($url, '//') || str_starts_with($url, '/\\')) $url = '/';
    $out = [
        'title' => mb_substr(trim((string)($n['title'] ?? 'VESTRA')), 0, 90) ?: 'VESTRA',
        /* Yalniz ASCII bosluklar tek bosluga iner: /\s/u bolunmez boslugu (U+00A0) da
           yiyordu ve "1.234,50 €" satir sonunda tutarindan kopabiliyordu. */
        'body'  => mb_substr(trim(preg_replace('/[ \t\r\n]+/', ' ', (string)($n['body'] ?? ''))), 0, 220),
        'url'   => $url,
        'ts'    => (int)($n['ts'] ?? round(microtime(true) * 1000)),
    ];
    foreach (['tag', 'kind', 'lang', 'dir'] as $f) {
        if (isset($n[$f]) && (string)$n[$f] !== '') $out[$f] = mb_substr((string)$n[$f], 0, 64);
    }
    if (isset($n['unread']) && is_int($n['unread'])) $out['unread'] = max(0, $n['unread']);
    return $out;
}

/**
 * Deliver one notification to the devices of one account ($only: a single device).
 * Every device gets its own encrypted copy. 404/410 = the browser dropped the
 * subscription → removed. Returns ['devices','ok','failed','pruned'].
 */
function vestra_push_deliver(string $uid, array $n, string $only = ''): array {
    $res = ['devices' => 0, 'ok' => 0, 'failed' => 0, 'pruned' => 0];
    $mine = $uid !== '' ? (array)(vestra_push_subs()[$uid] ?? []) : [];
    if ($only !== '') $mine = isset($mine[$only]) ? [$only => $mine[$only]] : [];
    if (!$mine || !vestra_vapid_keys()) return $res;
    $p = vestra_push_payload($n);
    $json = json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $ttl = (int)($n['ttl'] ?? VESTRA_PUSH_TTL);
    $urg = in_array($n['urgency'] ?? '', ['very-low', 'low', 'normal', 'high'], true) ? $n['urgency'] : 'normal';
    if ($only === '') vestra_push_pending_add($uid, $p);   // for service workers < v3
    $outcome = [];
    foreach ($mine as $h => $sub) {
        $res['devices']++;
        $ep = (string)($sub['endpoint'] ?? '');
        /* A device stored before the host check existed is NOT deleted for failing it —
           that would be a silent loss. It is skipped, marked -3 and logged, so the
           operator's device list shows it and the allowlist can be extended. */
        if (!vestra_push_endpoint_ok($ep)) {
            $outcome[$h] = -3;
            error_log('[VESTRA Push] skipped, push host not allowed: '.(parse_url($ep, PHP_URL_HOST) ?: '?'));
            continue;
        }
        $body = vestra_push_encrypt($json, (string)($sub['keys']['p256dh'] ?? ''), (string)($sub['keys']['auth'] ?? ''));
        if ($body === '') vestra_push_queue_add((string)$h, $p);  // the SW fetches it by device
        $outcome[$h] = vestra_push_post($ep, $body, $ttl, $urg);
    }
    $now = date('c');
    vestra_push_store_update('push_subs.json', function (array $all) use ($uid, $outcome, $now, &$res) {
        foreach ($outcome as $h => $code) {
            if (!isset($all[$uid][$h])) continue;          // unsubscribed meanwhile
            if ($code === 404 || $code === 410) { unset($all[$uid][$h]); $res['pruned']++; continue; }
            $all[$uid][$h]['last_at'] = $now;
            $all[$uid][$h]['last_code'] = $code;
            if ($code >= 200 && $code < 300) { $all[$uid][$h]['last_ok'] = $now; $all[$uid][$h]['fails'] = 0; }
            else $all[$uid][$h]['fails'] = (int)($all[$uid][$h]['fails'] ?? 0) + 1;
        }
        if (isset($all[$uid]) && !$all[$uid]) unset($all[$uid]);
        return $all;
    });
    foreach ($outcome as $code) {
        if ($code >= 200 && $code < 300) $res['ok']++;
        elseif ($code !== 404 && $code !== 410) {
            $res['failed']++;
            if ($code !== -3) error_log("[VESTRA Push] endpoint HTTP $code for uid $uid");   // -3 already logged as skipped
        }
    }
    return $res;
}

/** Raw title/body (operator broadcasts, callers not yet on the catalogue). */
function vestra_push_send(string $uid, string $title, string $body, string $url = '/'): void {
    vestra_push_deliver($uid, ['title' => $title, 'body' => $body, 'url' => $url]);
}

/**
 * The localized notification for one account — the way every event in the site
 * should notify. Text comes from inc/push_texts.php in the RECIPIENT's language
 * (never the current request's vlang(): an admin approving a German seller must
 * not push English). $who: uid or the account array itself.
 */
function vestra_push_notify($who, string $kind, array $facts = []): array {
    require_once __DIR__.'/push_texts.php';
    $acc = is_array($who) ? $who : null;
    $uid = is_array($who) ? (string)($who['id'] ?? '') : (string)$who;
    if ($uid === '') return ['devices' => 0, 'ok' => 0, 'failed' => 0, 'pruned' => 0];
    /* No device, no work — most accounts never enabled notifications, and the
       texts would be built for nobody. */
    if (empty(vestra_push_subs()[$uid])) return ['devices' => 0, 'ok' => 0, 'failed' => 0, 'pruned' => 0];
    if ($acc === null && function_exists('auth_accounts')) {
        foreach (auth_accounts() as $a) if (($a['id'] ?? '') === $uid) { $acc = $a; break; }
    }
    $n = vestra_push_compose($kind, $facts, vestra_push_lang($acc));
    return $n ? vestra_push_deliver($uid, $n) : ['devices' => 0, 'ok' => 0, 'failed' => 0, 'pruned' => 0];
}

/* ── Admin notification center helpers ─────────────────────────────────── */

/** Broadcast to many accounts. Returns ['users' => reached accounts, 'ok' => devices accepted, 'failed' => …]. */
function vestra_push_broadcast(array $uids, string $title, string $body, string $url = '/'): array {
    $subs = vestra_push_subs();
    $out = ['users' => 0, 'ok' => 0, 'failed' => 0];
    foreach (array_unique(array_filter($uids)) as $u) {
        if (empty($subs[$u])) continue;
        $out['users']++;
        $r = vestra_push_deliver((string)$u, ['title' => $title, 'body' => $body, 'url' => $url, 'kind' => 'broadcast']);
        $out['ok'] += $r['ok']; $out['failed'] += $r['failed'];
    }
    return $out;
}

/** How many accounts / devices are push-enabled, and how many of those answered last time. */
function vestra_push_stats(): array {
    $s = array_filter(vestra_push_subs());
    $healthy = 0; $failing = 0;
    foreach ($s as $devs) foreach ((array)$devs as $d) {
        if ((int)($d['fails'] ?? 0) > 0) $failing++; elseif (!empty($d['last_ok'])) $healthy++;
    }
    return ['users' => count($s), 'devices' => array_sum(array_map('count', $s)), 'healthy' => $healthy, 'failing' => $failing];
}

/** Append to the broadcast log (last 50 kept). */
function vestra_push_log(array $entry): void {
    vestra_push_store_update('push_log.json', function (array $l) use ($entry) { $l[] = $entry; return array_slice($l, -50); });
}
function vestra_push_log_all(): array { return array_reverse(vestra_push_store_read('push_log.json')); }
