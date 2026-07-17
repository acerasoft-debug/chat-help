<?php
/**
 * VESTRA — dependency-free Web Push (VAPID / RFC 8292).
 *
 * Same philosophy as inc/stripe.php and inc/pdf.php: no composer, no SDK.
 * We send PAYLOADLESS pushes (no aes128gcm encryption needed): the service
 * worker wakes up, fetches the pending notification for the signed-in user
 * from /push?a=pending (session cookie rides along) and shows it. The only
 * cryptography required is the VAPID ES256 JWT, which PHP's openssl does.
 *
 * Storage (all under data/, never web-accessible):
 *   vapid.json         P-256 keypair, generated once on first use
 *   push_subs.json     { uid: { endpointHash: subscriptionJson } }
 *   push_pending.json  { uid: [ {title, body, url, at}, … ] }
 */

function vestra_push_dir(): string { return dirname(__DIR__).'/data'; }

/* ── VAPID keypair (auto-generated once) ────────────────────────────────── */
function vestra_vapid_keys(): array {
    $f = vestra_push_dir().'/vapid.json';
    if (is_readable($f)) {
        $k = json_decode((string)file_get_contents($f), true);
        if (!empty($k['publicKey']) && !empty($k['privatePem'])) return $k;
    }
    $res = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    if (!$res) return [];
    openssl_pkey_export($res, $pem);
    $d = openssl_pkey_get_details($res);
    // Uncompressed EC point: 0x04 || X(32) || Y(32) — the browser-facing public key.
    $pub = "\x04".str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT).str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT);
    $k = ['publicKey' => vestra_b64url($pub), 'privatePem' => $pem];
    if (!is_dir(vestra_push_dir())) @mkdir(vestra_push_dir(), 0775, true);
    @file_put_contents($f, json_encode($k), LOCK_EX);
    return $k;
}

function vestra_b64url(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }

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

/* ── Subscriptions ──────────────────────────────────────────────────────── */
function vestra_push_subs(): array {
    $f = vestra_push_dir().'/push_subs.json';
    return is_readable($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
}
function vestra_push_save_subs(array $s): void {
    @file_put_contents(vestra_push_dir().'/push_subs.json', json_encode($s), LOCK_EX);
}
function vestra_push_subscribe(string $uid, array $sub): bool {
    $ep = (string)($sub['endpoint'] ?? '');
    if ($uid === '' || !str_starts_with($ep, 'https://')) return false;
    $all = vestra_push_subs();
    $all[$uid][substr(sha1($ep), 0, 16)] = $sub;
    vestra_push_save_subs($all);
    return true;
}

/* ── Pending notification store (what the SW fetches and shows) ─────────── */
function vestra_push_pending_add(string $uid, array $notif): void {
    $f = vestra_push_dir().'/push_pending.json';
    $all = is_readable($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
    $all[$uid][] = $notif + ['at' => time()];
    $all[$uid] = array_slice($all[$uid], -5);   // keep the last few only
    @file_put_contents($f, json_encode($all), LOCK_EX);
}
function vestra_push_pending_take(string $uid): array {
    $f = vestra_push_dir().'/push_pending.json';
    $all = is_readable($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
    $out = $all[$uid] ?? [];
    if ($out) { unset($all[$uid]); @file_put_contents($f, json_encode($all), LOCK_EX); }
    // drop stale entries (>1h) — the push that should have fetched them is gone
    return array_values(array_filter($out, fn($n) => ($n['at'] ?? 0) > time() - 3600));
}

/**
 * Send a notification to every device of one account. Fire-and-forget:
 * failures only log; 404/410 prune the dead subscription.
 */
function vestra_push_send(string $uid, string $title, string $body, string $url = '/'): void {
    if ($uid === '' || !function_exists('curl_init')) return;
    $all = vestra_push_subs();
    if (empty($all[$uid])) return;
    vestra_push_pending_add($uid, ['title' => $title, 'body' => $body, 'url' => $url]);
    $keys = vestra_vapid_keys();
    if (!$keys) return;
    $changed = false;
    foreach ($all[$uid] as $h => $sub) {
        $ep = (string)($sub['endpoint'] ?? '');
        $aud = preg_replace('#^(https://[^/]+).*$#', '$1', $ep);
        $jwt = vestra_vapid_jwt($aud);
        if ($jwt === '') continue;
        $ch = curl_init($ep);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => '',
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Authorization: vapid t='.$jwt.', k='.$keys['publicKey'],
                'TTL: 86400', 'Urgency: normal', 'Content-Length: 0',
            ],
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if (in_array($code, [404, 410], true)) { unset($all[$uid][$h]); $changed = true; }
        elseif ($code >= 400) error_log("[VESTRA Push] endpoint HTTP $code for uid $uid");
    }
    if ($changed) vestra_push_save_subs($all);
}
