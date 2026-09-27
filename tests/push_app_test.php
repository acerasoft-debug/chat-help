<?php
/* VESTRA uygulamasi ve bildirimleri (27 Eyl 2026; operator: "sitenin APP ini daha
 * profesyonel ve eksiksiz hale getirirmisin ozellikle bildirimleri...ve
 * eksiklikleri duzelt").
 *
 * NE OLCULUYOR:
 *  1. Sifreleme (RFC 8291) — RFC'nin Ek A test vektoru BAYT BAYT; rastgele
 *     anahtarla gidis-donus BU dosyadaki bagimsiz bir cozucuyle (HMAC elle,
 *     uretimdeki hash_hkdf kullanilmadan).
 *  2. Cihaz kaydi — bir cihaz TEK hesabin; cikis cihazi ayirir ve park eder;
 *     ayni hesap geri gelince tekrar baglanir, BASKA hesap devralmaz; yanlis
 *     auth sirri kimseyi ayiramaz; teslim sirasinda baglanan cihaz kaybolmaz.
 *  3. Teslim — her cihaz kendi sifreli kopyasini alir; 410 budanir, izinsiz
 *     host SILINMEZ (-3); anahtarsiz cihaz cihaz-kuyruguna duser.
 *  4. Metin katalogu — 9 dil, ayni yer tutucular, eksik bilgi ham "{…}"
 *     basmaz, para/tarih alicinin yaziminda, Arapca rtl.
 *  5. UCTAN UCA HTTP — sitenin kum havuzu kopyasi php -S ile: giris, abone ol,
 *     test bildirimi (govde cozuluyor, Almanca), cikis, baska kullanici, geri
 *     donus, renew, /me yonlendirmesi, acik yonlendirme kapisi, panel karti.
 *  6. Servis calisani — GERCEK sw.js Node VM'de (tests/sw_check.js).
 *  7. Kablolama — hicbir cagri yeri eski Ingilizce vestra_push_send'e donmedi. */

$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? "  ok   " : "  FAIL ").$n."\n"; };
$root = dirname(__DIR__);
$sb = sys_get_temp_dir().'/vpush_app_'.getmypid();
@mkdir($sb.'/data', 0777, true);
define('VESTRA_PUSH_DIR', $sb.'/data');
require $root.'/vestra/inc/push.php';
require $root.'/vestra/inc/push_texts.php';

/* ── bagimsiz cozucu (RFC 8291 §3.4 + RFC 8188), uretim kodundan ayri yazildi ── */
function t_decrypt(string $body, $uaPriv, string $uaPubRaw, string $auth): ?string {
    if (strlen($body) < 21 + 65 + 17) return null;
    $salt = substr($body, 0, 16); $idlen = ord($body[20]); $keyid = substr($body, 21, $idlen);
    $ct = substr($body, 21 + $idlen);
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').$keyid;
    $as = openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END PUBLIC KEY-----\n");
    if (!$as) return null;
    $ecdh = openssl_pkey_derive($as, $uaPriv, 32);
    $prkKey = hash_hmac('sha256', $ecdh, $auth, true);
    $ikm = hash_hmac('sha256', "WebPush: info\0".$uaPubRaw.$keyid."\x01", $prkKey, true);
    $prk = hash_hmac('sha256', $ikm, $salt, true);
    $cek = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\0\x01", $prk, true), 0, 16);
    $nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\0\x01", $prk, true), 0, 12);
    $pt = openssl_decrypt(substr($ct, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($ct, -16));
    if ($pt === false) return null;
    $pt = rtrim($pt, "\0");
    return substr($pt, -1) === "\x02" ? substr($pt, 0, -1) : null;
}
/* A browser: its P-256 keypair + auth secret, as PushSubscription JSON */
function t_browser(string $endpoint): array {
    $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $pub = vestra_push_raw_point(openssl_pkey_get_details($k));
    $auth = random_bytes(16);
    return ['priv' => $k, 'pub' => $pub, 'auth' => $auth,
            'sub' => ['endpoint' => $endpoint, 'expirationTime' => null,
                      'keys' => ['p256dh' => vestra_b64url($pub), 'auth' => vestra_b64url($auth)]]];
}
$SENT = [];
$GLOBALS['vestra_push_transport'] = function (string $ep, array $h, string $b) use (&$SENT) {
    $SENT[] = ['ep' => $ep, 'h' => $h, 'b' => $b];
    if (str_contains($ep, '/gone')) return 410;
    if (str_contains($ep, '/broken')) return 500;
    return 201;
};
$wipe = function () use ($sb) { foreach (glob($sb.'/data/push_*') as $f) @unlink($f); };

echo "== 1. Sifreleme ==\n";
/* RFC 8291 Ek A'nin YAYIMLANMIS test vektoru (uygulama sunucusu anahtar cifti,
   tuz, alici anahtarlari, beklenen sifreli metin). Gercek bir anahtar DEGIL;
   sunucunun VAPID anahtari data/vapid.json'da ve sunucudan hic cikmiyor. */
$d = vestra_b64url_dec('yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw');
$asPub = vestra_b64url_dec('BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8');
$der = hex2bin('30770201010420').$d.hex2bin('a00a06082a8648ce3d030107a144034200').$asPub;
$asKey = openssl_pkey_get_private("-----BEGIN EC PRIVATE KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END EC PRIVATE KEY-----\n");
$vec = vestra_push_encrypt('When I grow up, I want to be a watermelon',
    'BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4',
    'BTBZMqHH6r4Tts7J_aSIgg', $asKey, vestra_b64url_dec('DGv6ra1nlYgDCS1FRnbzlw'));
$t('RFC 8291 Ek A test vektoru bayt bayt', vestra_b64url($vec) ===
   'DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPTpK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXLWyouBWLVWGNWQexSgSxsj_Qulcy4a-fN');
$br = t_browser('https://fcm.googleapis.com/fcm/send/x');
$msg = json_encode(['title' => 'Neue Bestellung', 'body' => '1.234,50 € — طلبية 注文'], JSON_UNESCAPED_UNICODE);
$c1 = vestra_push_encrypt($msg, $br['sub']['keys']['p256dh'], $br['sub']['keys']['auth']);
$c2 = vestra_push_encrypt($msg, $br['sub']['keys']['p256dh'], $br['sub']['keys']['auth']);
$t('bagimsiz cozucu uretimin ciktisini acar (UTF-8 dahil)', t_decrypt($c1, $br['priv'], $br['pub'], $br['auth']) === $msg);
$t('her mesaj taze anahtar + tuz (iki cikti farkli)', $c1 !== $c2 && substr($c1, 0, 16) !== substr($c2, 0, 16));
$t('baska cihazin sirriyla ACILAMAZ', t_decrypt($c1, $br['priv'], $br['pub'], random_bytes(16)) === null);
$t('bozuk p256dh reddedilir', vestra_push_encrypt('x', 'abc', $br['sub']['keys']['auth']) === '');
$t('bozuk auth reddedilir', vestra_push_encrypt('x', $br['sub']['keys']['p256dh'], 'abc') === '');
$t('tek kayda sigmayan govde reddedilir (sessiz 413 yerine)', vestra_push_encrypt(str_repeat('a', 4000), $br['sub']['keys']['p256dh'], $br['sub']['keys']['auth']) === '');

echo "\n== 2. Push servisi kapisi (SSRF) ==\n";
foreach (['https://fcm.googleapis.com/fcm/send/a', 'https://updates.push.services.mozilla.com/wpush/v2/a',
          'https://web.push.apple.com/QA', 'https://wns2-par02p.notify.windows.com/w/?token=a'] as $u) {
    $t('kabul: '.parse_url($u, PHP_URL_HOST), vestra_push_endpoint_ok($u));
}
foreach (['http://fcm.googleapis.com/x', 'https://127.0.0.1/x', 'https://localhost/x', 'https://[::1]/x',
          'https://evil.example/x', 'https://fcm.googleapis.com.evil.example/x', 'https://evilgoogleapis.com/x',
          'https://fcm.googleapis.com:8443/x', 'https://u:p@fcm.googleapis.com/x', 'https://169.254.169.254/latest'] as $u) {
    $t('red: '.$u, !vestra_push_endpoint_ok($u));
}

echo "\n== 3. Cihaz kaydi ==\n";
$A = t_browser('https://fcm.googleapis.com/fcm/send/devA');
$t('baglandi', vestra_push_link('uA', $A['sub'], 'Android · Chrome') !== '');
$hA = vestra_push_ep_hash($A['sub']['endpoint']);
$t('sahibi uA', (vestra_push_owner($hA)[0] ?? '') === 'uA');
vestra_push_link('uB', $A['sub']);
$t('ayni cihaz baska hesaba baglaninca ESKISINDEN silinir', (vestra_push_owner($hA)[0] ?? '') === 'uB' && empty(vestra_push_subs()['uA']));
vestra_push_link('uA', $A['sub']);
/* CLI'da oturum yok: damgayi oturumun kendisi gibi yaziyoruz (uretimde subscribe/sync yazar). */
$_SESSION = ['push_dev' => [$hA]];
vestra_push_signout('uA');
$t('cikis: cihaz ayrildi', vestra_push_owner($hA) === null);
$t('cikis: cihaz uA icin park edildi', (vestra_push_store_read('push_parked.json')[$hA]['uid'] ?? '') === 'uA');
$t('baska hesap park edilmis cihazi DEVRALMAZ', vestra_push_sync('uB', $A['sub']) === 'off' && vestra_push_owner($hA) === null);
$t('ayni hesap geri gelince yeniden baglanir', vestra_push_sync('uA', $A['sub']) === 'relinked' && (vestra_push_owner($hA)[0] ?? '') === 'uA');
$wrongSub = $A['sub']; $wrongSub['keys']['auth'] = vestra_b64url(random_bytes(16));
$t('YANLIS auth sirriyla baskasinin cihazi ayrilamaz', vestra_push_sync('uB', $wrongSub) === 'off' && (vestra_push_owner($hA)[0] ?? '') === 'uA');
$t('dogru sirla baska hesap girince eski sahip ayrilir', vestra_push_sync('uB', $A['sub']) === 'off' && vestra_push_owner($hA) === null);
$t('izinsiz host baglanmaz', vestra_push_link('uA', ['endpoint' => 'https://evil.example/p', 'keys' => $A['sub']['keys']]) === '');

echo "\n== 4. Teslim ==\n";
$wipe(); $SENT = [];
$D1 = t_browser('https://fcm.googleapis.com/fcm/send/d1');
$D2 = t_browser('https://web.push.apple.com/d2');
$DG = t_browser('https://fcm.googleapis.com/fcm/send/gone');
$DB = t_browser('https://fcm.googleapis.com/fcm/send/broken');
foreach ([$D1, $D2, $DG, $DB] as $x) vestra_push_link('uS', $x['sub']);
$acc = ['id' => 'uS', 'lang' => 'de', 'type' => 'seller'];
$r = vestra_push_notify($acc, 'order_new', ['ref' => 'VES-1A2B3C4D', 'company' => 'Boutique Lina', 'qty' => 24, 'amount' => 1234.5]);
$t('dort cihaz, ikisi kabul, biri budandi, biri hatali', $r['devices'] === 4 && $r['ok'] === 2 && $r['pruned'] === 1 && $r['failed'] === 1);
$byEp = []; foreach ($SENT as $s) $byEp[$s['ep']] = $s;
$s1 = $byEp[$D1['sub']['endpoint']] ?? ['h' => [], 'b' => ''];
$j1 = json_decode((string)t_decrypt($s1['b'], $D1['priv'], $D1['pub'], $D1['auth']), true) ?: [];
$j2 = json_decode((string)t_decrypt(($byEp[$D2['sub']['endpoint']] ?? ['b' => ''])['b'], $D2['priv'], $D2['pub'], $D2['auth']), true) ?: [];
$t('cihaz 1 kendi kopyasini cozer: Almanca baslik', ($j1['title'] ?? '') === 'Neue Bestellung VES-1A2B3C4D');
$t('cihaz 1: govde Almanca yazimla', ($j1['body'] ?? '') === "Boutique Lina · 24 Stk. · 1.234,50\u{00A0}€");
$t('cihaz 1: derin baglanti siparisin kendisi', ($j1['url'] ?? '') === '/seller?tab=orders&view=VES-1A2B3C4D');
$t('cihaz 2 (Apple) de kendi kopyasini cozer', ($j2['title'] ?? '') === 'Neue Bestellung VES-1A2B3C4D');
$t('iki cihazin govdesi FARKLI (her biri kendi anahtarina)', $s1['b'] !== ($byEp[$D2['sub']['endpoint']]['b'] ?? ''));
$hdr = implode("\n", $s1['h']);
$t('basliklar: aes128gcm + vapid + TTL 3 gun + Urgency high', str_contains($hdr, 'Content-Encoding: aes128gcm')
   && str_contains($hdr, 'Authorization: vapid t=') && str_contains($hdr, 'TTL: 259200') && str_contains($hdr, 'Urgency: high'));
$t('Topic basligi YOK (Apple/Windows olculemedi)', !str_contains($hdr, 'Topic:'));
$subs = vestra_push_subs()['uS'] ?? [];
$t('410 veren cihaz budandi', !isset($subs[vestra_push_ep_hash($DG['sub']['endpoint'])]));
$hB = vestra_push_ep_hash($DB['sub']['endpoint']);
$t('500 veren cihaz KALDI, hata sayildi', isset($subs[$hB]) && ($subs[$hB]['last_code'] ?? 0) === 500 && ($subs[$hB]['fails'] ?? 0) === 1);
$t('basarili cihazda last_ok yazildi', !empty($subs[vestra_push_ep_hash($D1['sub']['endpoint'])]['last_ok']));
$t('eski servis calisanlari icin hesap kuyrugu da yazildi', count(vestra_push_store_read('push_pending.json')['uS'] ?? []) === 1);
$t('anahtarli cihazlar icin CIHAZ kuyrugu bos', vestra_push_store_read('push_queue.json') === []);
/* anahtarsiz cihaz → yuksuz push + cihaz kuyrugu */
$wipe(); $SENT = [];
vestra_push_link('uK', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/nokeys']);
vestra_push_notify(['id' => 'uK', 'lang' => 'fr'], 'order_paid', ['ref' => 'VES-9']);
$hK = vestra_push_ep_hash('https://fcm.googleapis.com/fcm/send/nokeys');
$t('anahtarsiz cihaz: yuksuz push gitti', ($SENT[0]['b'] ?? 'x') === '' && in_array('Content-Length: 0', $SENT[0]['h'] ?? [], true));
$q = vestra_push_queue_take($hK);
$t('anahtarsiz cihaz: metin CIHAZ kuyrugunda, Fransizca', ($q[0]['title'] ?? '') === 'Paiement confirmé · VES-9');
/* izinsiz host eski kayit: silinmez, -3 */
$wipe(); $SENT = [];
vestra_push_store_update('push_subs.json', fn() => ['uL' => ['old1' => ['endpoint' => 'https://push.example.net/x', 'keys' => $D1['sub']['keys']]]]);
vestra_push_deliver('uL', ['title' => 't', 'body' => 'b']);
$t('izinsiz hosta ISTEK GITMEDI', $SENT === []);
$t('izinsiz host kaydi SILINMEDI, -3 isaretlendi', (vestra_push_subs()['uL']['old1']['last_code'] ?? 0) === -3);
/* yaris: teslim surerken baglanan cihaz kaybolmamali */
$wipe();
$E1 = t_browser('https://fcm.googleapis.com/fcm/send/gone'); vestra_push_link('uR', $E1['sub']);
$E2 = t_browser('https://fcm.googleapis.com/fcm/send/late');
$GLOBALS['vestra_push_transport'] = function ($ep, $h, $b) use ($E2) { vestra_push_link('uR', $E2['sub']); return 410; };
vestra_push_deliver('uR', ['title' => 't', 'body' => 'b']);
$t('YARIS: teslim sirasinda baglanan cihaz kaybolmadi', isset(vestra_push_subs()['uR'][vestra_push_ep_hash($E2['sub']['endpoint'])]));
$t('YARIS: 410 veren eski cihaz yine budandi', !isset(vestra_push_subs()['uR'][vestra_push_ep_hash($E1['sub']['endpoint'])]));
$GLOBALS['vestra_push_transport'] = function (string $ep, array $h, string $b) use (&$SENT) { $SENT[] = ['ep' => $ep, 'h' => $h, 'b' => $b]; return 201; };
/* cihazi olmayan hesap icin hic is yapilmaz */
$SENT = [];
$r0 = vestra_push_notify(['id' => 'nobody', 'lang' => 'de'], 'order_new', ['ref' => 'X']);
$t('cihazi olmayan hesap: istek yok', $SENT === [] && $r0['devices'] === 0);

echo "\n== 5. Metin katalogu ==\n";
$T = vestra_push_texts(); $L = ['en', 'de', 'fr', 'it', 'es', 'pt', 'ru', 'ar', 'ja'];
$miss = 0; $ph = 0; $raw = 0;
foreach ($T as $k => $v) {
    if ($k[0] === '_') { foreach ($L as $l) if (empty($v[$l])) $miss++; continue; }
    foreach ($L as $l) {
        if (empty($v[$l]['t']) || empty($v[$l]['b'])) { $miss++; continue; }
        preg_match_all('/\{[a-z_]+\}/', $v['en']['t'].$v['en']['b'].($v['en']['b0'] ?? ''), $e);
        preg_match_all('/\{[a-z_]+\}/', $v[$l]['t'].$v[$l]['b'].($v[$l]['b0'] ?? ''), $x);
        $a = array_unique($e[0]); sort($a); $b = array_unique($x[0]); sort($b);
        if ($a !== $b || isset($v['en']['b0']) !== isset($v[$l]['b0'])) $ph++;
        $n = vestra_push_compose($k, [], $l);   // NO facts at all
        if (!$n || str_contains($n['title'].$n['body'], '{')) $raw++;
    }
}
$t('her tur 9 dilde baslik + govde', $miss === 0);
$t('her dilde AYNI yer tutucular (b0 dahil)', $ph === 0);
$t('bilgi eksikken ham "{…}" hicbir dilde basilmaz', $raw === 0);
$t('bilinmeyen tur null', vestra_push_compose('nope', [], 'en') === null && vestra_push_compose('_seller', [], 'en') === null);
$m = vestra_push_compose('message_new', ['seller_ident' => 'SH9626', 'from' => 'Garage Le Paris', 'text' => 'Hallo', 'thread' => 't1', 'url' => '/buyer?tab=messages&thread=t1'], 'de');
$t('aliciya satici URUN KIMLIGIYLE, magaza adi YOK (KURAL 8)', $m['title'] === 'Verkäufer SH9626' && !str_contains(json_encode($m), 'Garage'));
$t('mesaj konusmanin kendisini acar, konusma basina etiket', $m['url'] === '/buyer?tab=messages&thread=t1' && $m['tag'] === 'msg-t1');
$ar = vestra_push_compose('order_shipped', ['ref' => 'VES-9'], 'ar');
$t('Arapca rtl', $ar['dir'] === 'rtl' && $ar['lang'] === 'ar');
$t('bilinmeyen dil Ingilizceye duser', vestra_push_compose('order_paid', ['ref' => 'V'], 'xx')['lang'] === 'en');
$t('para: fr/ru/en/ja yazimi', vestra_push_money(1234.5, 'fr') === "1\u{202F}234,50\u{00A0}€" && vestra_push_money(1234.5, 'ru', 'USD') === "1\u{00A0}234,50\u{00A0}US$"
   && vestra_push_money(1234.5, 'en', 'usd') === 'US$1,234.50' && vestra_push_money(9, 'ja') === '€9.00');
$ts = mktime(12, 0, 0, 10, 1, 2026);
$t('tarih: fr 1er, it 1º, ja, ru genitif', vestra_push_date($ts, 'fr') === '1er octobre 2026' && vestra_push_date($ts, 'it') === '1º ottobre 2026'
   && vestra_push_date($ts, 'ja') === '2026年10月1日' && vestra_push_date($ts, 'ru') === '1 октября 2026 г.');
$dl = vestra_push_compose('order_delivered', ['ref' => 'VES-5', 'date' => $ts], 'en');
$t('teslim bildirimi "otomatik odeme" DEMIYOR (havale siparisinde yanlisti)', !str_contains(strtolower($dl['body']), 'released') && str_contains($dl['body'], '1 October 2026'));
foreach (['order_new', 'message_new', 'escrow_paid'] as $k) $t('acil tur Urgency high: '.$k, (vestra_push_compose($k, ['ref' => 'R'], 'en')['urgency'] ?? '') === 'high');
$t('siradan tur normal', vestra_push_compose('listing_approved', ['product' => 'P'], 'en')['urgency'] === 'normal');
$t('baslikta "VESTRA —" oneki ve emoji yok', !preg_match('/VESTRA —|[\x{1F300}-\x{1FAFF}]/u', json_encode(array_map(fn($k) => vestra_push_compose($k, ['ref' => 'R', 'product' => 'P'], 'en')['title'] ?? '', array_filter(array_keys($T), fn($k) => $k[0] !== '_')), JSON_UNESCAPED_UNICODE)));
$pl = vestra_push_payload(['title' => 't', 'body' => 'b', 'url' => '//evil.example']);
$t('yuk: site disi URL "/"ye doner', $pl['url'] === '/');

echo "\n== 6. Servis calisani (gercek sw.js, Node) ==\n";
if (trim((string)shell_exec('command -v node')) === '') { $t('node bulunamadi — sw_check atlandi', false); }
else {
    $lines = preg_split('/\R/', trim((string)shell_exec('node '.escapeshellarg(__DIR__.'/sw_check.js').' 2>&1')));
    $n = 0;
    foreach ($lines as $ln) { if (preg_match('/^(ok|FAIL)\s+(.*)$/', $ln, $mm)) { $n++; $t('sw: '.$mm[2], $mm[1] === 'ok'); } }
    $t('sw_check en az 20 iddia bildirdi', $n >= 20);
}

echo "\n== 7. Uctan uca HTTP (kum havuzu kopyasi, php -S) ==\n";
$wipe();
$site = $sb.'/site';
$copy = function (string $from, string $to) use (&$copy) {
    if (is_dir($from)) { @mkdir($to, 0777, true); foreach (scandir($from) as $f) if ($f !== '.' && $f !== '..') $copy("$from/$f", "$to/$f"); }
    else copy($from, $to);
};
@mkdir($site, 0777, true);
foreach (glob($root.'/vestra/*.php') as $f) copy($f, $site.'/'.basename($f));
foreach (['sw.js', 'site.webmanifest', 'offline.html', 'icon-badge-96.png'] as $f) copy($root.'/vestra/'.$f, $site.'/'.$f);
$copy($root.'/vestra/inc', $site.'/inc');
@mkdir($site.'/data', 0777, true);
$pw = password_hash('pw-test-123', PASSWORD_DEFAULT);
$mk = fn($id, $email, $type, $lang) => ['id' => $id, 'email' => $email, 'hash' => $pw, 'type' => $type, 'status' => 'active',
    'kyb_status' => 'approved', 'email_verified' => true, 'lang' => $lang, 'company' => "Co $id", 'name' => "N $id", 'country' => 'Germany'];
file_put_contents($site.'/data/accounts.json', json_encode([$mk('aaaa1111', 'a@example.test', 'buyer', 'de'),
    $mk('bbbb2222', 'b@example.test', 'buyer', 'fr'), $mk('ssss3333', 's@example.test', 'seller', 'it')]));
file_put_contents($site.'/_test_router.php', '<?php
$GLOBALS["vestra_push_transport"] = function (string $ep, array $h, string $b) {
    file_put_contents(__DIR__."/data/_sent.jsonl", json_encode(["ep" => $ep, "h" => $h, "b" => base64_encode($b)])."\n", FILE_APPEND | LOCK_EX);
    return 201;
};
return require __DIR__."/_router_local.php";');
$port = 19000 + (getmypid() % 1500);
$log = $sb.'/php_err.log';
$proc = proc_open(['php', '-d', 'display_errors=0', '-d', 'log_errors=1', '-d', 'error_log='.$log, '-S', "127.0.0.1:$port", '-t', $site, $site.'/_test_router.php'],
                  [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pp);
$up = false;
for ($i = 0; $i < 60 && !$up; $i++) { usleep(100000); $s = @fsockopen('127.0.0.1', $port); if ($s) { $up = true; fclose($s); } }
$t('php -S ayaga kalkti', $up);
$req = function (string $method, string $path, ?string $jar, $body = null, array $hdr = []) use ($port): array {
    $ch = curl_init("http://127.0.0.1:$port$path");
    $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 20,
          CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => array_merge(['User-Agent: Mozilla/5.0 (Linux; Android 14) Chrome/128.0 Mobile'], $hdr)];
    if ($jar) { $o[CURLOPT_COOKIEJAR] = $jar; $o[CURLOPT_COOKIEFILE] = $jar; }
    if ($body !== null) $o[CURLOPT_POSTFIELDS] = is_array($body) ? http_build_query($body) : $body;
    curl_setopt_array($ch, $o);
    $raw = (string)curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $hs = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $h = substr($raw, 0, $hs); $b = substr($raw, $hs);
    preg_match('/^Location:\s*(\S+)/mi', $h, $lm);
    return ['code' => $code, 'body' => $b, 'loc' => $lm[1] ?? '', 'json' => json_decode($b, true)];
};
$api = fn(string $a, ?string $jar, array $j) => $req('POST', '/push?a='.$a, $jar, json_encode($j), ['Content-Type: application/json']);
$login = function (string $email, string $jar) use ($req) { @unlink($jar); return $req('POST', '/login', $jar, ['email' => $email, 'password' => 'pw-test-123']); };
$jarA = $sb.'/a.jar'; $jarB = $sb.'/b.jar'; $jarS = $sb.'/s.jar';
$sentLog = $site.'/data/_sent.jsonl';
$reg = fn() => json_decode((string)@file_get_contents($site.'/data/push_subs.json'), true) ?: [];
$ownerOf = function (string $h) use ($reg) { foreach ($reg() as $u => $d) if (isset($d[$h])) return $u; return null; };

if ($up) {
    $r = $login('a@example.test', $jarA);
    $t('A girdi (302 → /buyer)', $r['code'] === 302 && $r['loc'] === '/buyer');
    $v = $req('GET', '/push?a=vapid', null);
    $t('vapid: 65 baytlik acik anahtar', strlen(vestra_b64url_dec((string)($v['json']['publicKey'] ?? ''))) === 65);
    $t('girissiz abonelik reddedilir (401)', $api('subscribe', null, $A['sub'])['code'] === 401);
    $P = t_browser('https://fcm.googleapis.com/fcm/send/phoneA');
    $hP = vestra_push_ep_hash($P['sub']['endpoint']);
    $r = $api('subscribe', $jarA, $P['sub']);
    $t('A telefonu bagladi', ($r['json']['ok'] ?? false) === true && $ownerOf($hP) === 'aaaa1111');
    $t('etiket UA\'dan kaba: Android · Chrome', ($reg()['aaaa1111'][$hP]['label'] ?? '') === 'Android · Chrome');
    $r = $api('subscribe', $jarA, ['endpoint' => 'https://127.0.0.1/steal', 'keys' => $P['sub']['keys']]);
    $t('SSRF: ic adres abonelik olarak reddedildi (400)', $r['code'] === 400);
    @unlink($sentLog);
    $r = $api('test', $jarA, ['endpoint' => $P['sub']['endpoint']]);
    $t('test bildirimi: sunucu tamam dedi', ($r['json']['ok'] ?? false) === true);
    $sl = array_values(array_filter(array_map('json_decode', file((string)$sentLog, FILE_IGNORE_NEW_LINES) ?: [], array_fill(0, 99, true))));
    $t('test bildirimi YALNIZ bu cihaza (1 istek)', count($sl) === 1 && ($sl[0]['ep'] ?? '') === $P['sub']['endpoint']);
    $tj = json_decode((string)t_decrypt(base64_decode($sl[0]['b'] ?? ''), $P['priv'], $P['pub'], $P['auth']), true) ?: [];
    $t('test bildirimi cozuldu ve HESABIN dilinde (de)', ($tj['title'] ?? '') === 'Benachrichtigungen sind aktiv' && ($tj['lang'] ?? '') === 'de');
    $t('test bildirimi ayar kartina acilir', ($tj['url'] ?? '') === '/buyer?tab=profile#notifications');
    $t('hemen ikinci test: 429 bekle', $api('test', $jarA, ['endpoint' => $P['sub']['endpoint']])['code'] === 429);
    $t('baskasinin cihazina test gonderilemez', $api('test', $jarA, ['endpoint' => 'https://fcm.googleapis.com/fcm/send/other'])['code'] === 409);

    $pg = $req('GET', '/buyer?tab=profile', $jarA, null, ['Accept-Language: de-DE']);
    $t('panel: bildirim karti basildi', str_contains($pg['body'], 'data-vpush-card') && str_contains($pg['body'], 'Benachrichtigungen'));
    $t('panel: app.js + boot, oturum damgali (stamped:true)', str_contains($pg['body'], '/inc/app.js?v=') && str_contains($pg['body'], '"stamped":true') && str_contains($pg['body'], '"signedIn":true'));
    $ov = $req('GET', '/buyer', $jarA, null, ['Accept-Language: de-DE']);
    $t('genel bakis: tek satirlik bildirim onerisi', str_contains($ov['body'], 'data-vpush-nudge'));
    $home = $req('GET', '/', null);
    $t('ana sayfa: app.js yukleniyor, misafir signedIn:false', str_contains($home['body'], '/inc/app.js?v=') && str_contains($home['body'], '"signedIn":false'));
    $t('ana sayfa: eski ikinci kopya (vestraPushOptIn) yok', !str_contains($home['body'], 'vestraPushOptIn'));

    $req('GET', '/login?signout=1', $jarA);
    $t('CIKIS: telefon A\'dan ayrildi', $ownerOf($hP) === null);
    $login('b@example.test', $jarB);
    $r = $api('sync', $jarB, $P['sub']);
    $t('ayni tarayicida B girdi: A\'nin cihazini DEVRALMADI', ($r['json']['state'] ?? '') === 'off' && $ownerOf($hP) === null);
    $login('a@example.test', $jarA);
    $r = $api('sync', $jarA, $P['sub']);
    $t('A geri geldi: cihaz kendiliginden yeniden baglandi', ($r['json']['state'] ?? '') === 'relinked' && $ownerOf($hP) === 'aaaa1111');
    $wrong = $P['sub']; $wrong['keys']['auth'] = vestra_b64url(random_bytes(16));
    $api('sync', $jarB, $wrong);
    $t('B yanlis sirla A\'yi ayiramadi', $ownerOf($hP) === 'aaaa1111');
    $api('sync', $jarB, $P['sub']);
    $t('B dogru sirla (ayni tarayici): A ayrildi, B otomatik baglanmadi', $ownerOf($hP) === null);
    $api('sync', $jarA, $P['sub']);  // A tekrar sahip
    $N = t_browser('https://fcm.googleapis.com/fcm/send/phoneA-new');
    $r = $api('renew', null, ['sub' => $N['sub'], 'old' => ['endpoint' => $P['sub']['endpoint'], 'auth' => $P['sub']['keys']['auth']]]);
    $t('renew (oturumsuz, eski sirla): yeni abonelik A\'ya', ($r['json']['ok'] ?? false) === true && $ownerOf(vestra_push_ep_hash($N['sub']['endpoint'])) === 'aaaa1111');
    $t('renew: eski abonelik silindi', $ownerOf($hP) === null);
    $r = $api('renew', null, ['sub' => t_browser('https://fcm.googleapis.com/fcm/send/x9')['sub'], 'old' => ['endpoint' => $N['sub']['endpoint'], 'auth' => vestra_b64url(random_bytes(16))]]);
    $t('renew yanlis sirla: 401', $r['code'] === 401);
    $r = $api('pending', null, ['endpoint' => $N['sub']['endpoint'], 'auth' => vestra_b64url(random_bytes(16))]);
    $t('cihaz kuyrugu yanlis sirla okunamaz (403)', $r['code'] === 403);
    $r = $api('pending', null, ['endpoint' => $N['sub']['endpoint'], 'auth' => $N['sub']['keys']['auth']]);
    $t('cihaz kuyrugu kendi sirriyla okunur (oturumsuz)', $r['code'] === 200 && is_array($r['json']['notifs'] ?? null));
    $r = $api('unsubscribe', $jarA, ['endpoint' => $N['sub']['endpoint']]);
    $t('"Kapat": cihaz ayrildi', ($r['json']['state'] ?? '') === 'off' && $ownerOf(vestra_push_ep_hash($N['sub']['endpoint'])) === null);

    $login('s@example.test', $jarS);
    $t('/me?tab=messages: satici → satici paneli', $req('GET', '/me?tab=messages', $jarS)['loc'] === '/seller?tab=messages');
    $t('/me?tab=requests: satici → siparisleri (talep alicinin)', $req('GET', '/me?tab=requests', $jarS)['loc'] === '/seller?tab=orders');
    $t('/me?tab=orders: alici → alici paneli', $req('GET', '/me?tab=orders', $jarA)['loc'] === '/buyer?tab=orders');
    $t('/me misafir → giris, geri donus adresiyle', $req('GET', '/me?tab=messages', null)['loc'] === '/login?back=%2Fme%3Ftab%3Dmessages');
    $t('/me bilinmeyen sekme → panelin kendisi', $req('GET', '/me?tab=<x>', $jarA)['loc'] === '/buyer');
    $t('ACIK YONLENDIRME: //evil (girisli) → panel', $req('GET', '/login?back=//evil.example/x', $jarA)['loc'] === '/buyer');
    $t('ACIK YONLENDIRME: /\\evil (girisli) → panel', $req('GET', '/login?back=/%5Cevil.example', $jarA)['loc'] === '/buyer');
    @unlink($jarB);
    $r = $req('POST', '/login?back='.rawurlencode('//evil.example'), $jarB, ['email' => 'b@example.test', 'password' => 'pw-test-123']);
    $t('ACIK YONLENDIRME: giris POST //evil → panel', $r['code'] === 302 && $r['loc'] === '/buyer');
    @unlink($jarB);
    $r = $req('POST', '/login?back='.rawurlencode('/buyer?tab=orders&view=VES-7'), $jarB, ['email' => 'b@example.test', 'password' => 'pw-test-123']);
    $t('derin baglanti giristen sonra korunur', $r['loc'] === '/buyer?tab=orders&view=VES-7');
    $g = $req('GET', '/buyer?tab=orders&view=VES-7', null);
    $t('oturumsuz panel kapisi derin baglantiyi login\'e tasir', str_contains($g['body'], '/login?back=%2Fbuyer%3Ftab%3Dorders%26view%3DVES-7'));
    $errs = (string)@file_get_contents($log);
    $t('HTTP turu boyunca PHP Fatal yok', !str_contains($errs, 'Fatal'));
    if (str_contains($errs, 'Fatal')) echo substr($errs, 0, 800), "\n";
}
if (is_resource($proc)) { proc_terminate($proc); proc_close($proc); }

echo "\n== 7b. Mesaj bildirimi uctan uca (gercek vestra_msg_send, kum havuzu) ==\n";
/* En sik bildirim mesaj. Olculen: baslik GONDEREN (aliciya satici URUN KIMLIGIYLE,
   magaza adi degil — KURAL 8), govde mesajin kendisi, dokununca O konusma, rozet =
   okunmamis konusma sayisi, dil alicinin hesabindan. Kum havuzu KOPYASINA karsi
   kosuyor: auth.php oturumu kendi data/sessions'ina yaziyor ve gercek depoya
   dokunmamali. */
$BA = t_browser('https://fcm.googleapis.com/fcm/send/buyerA');
$BS = t_browser('https://updates.push.services.mozilla.com/wpush/v2/sellerS');
file_put_contents($sb.'/msgpush.php', '<?php
$site = getenv("VSITE");
define("VESTRA_PUSH_DIR", $site."/data");
$GLOBALS["vestra_push_transport"] = function ($ep, $h, $b) use ($site) {
    file_put_contents($site."/data/_msg_sent.jsonl", json_encode(["ep" => $ep, "b" => base64_encode($b)])."\n", FILE_APPEND);
    return 201;
};
require $site."/inc/i18n.php"; require $site."/inc/auth.php"; require $site."/inc/push.php"; require $site."/inc/messages.php";
vestra_push_link("aaaa1111", json_decode(getenv("VSUB_A"), true));
vestra_push_link("ssss3333", json_decode(getenv("VSUB_S"), true));
$r1 = vestra_msg_send("aaaa1111", "ssss3333", "ssss3333", "Hallo, 10 Stück sind verfügbar.", "lst-1");
$r2 = vestra_msg_send("aaaa1111", "ssss3333", "aaaa1111", "Perfetto, prendo 20 pezzi.", "lst-1");
echo json_encode([$r1, $r2]);');
@unlink($site.'/data/_msg_sent.jsonl');
$env = 'VSITE='.escapeshellarg($site).' VSUB_A='.escapeshellarg(json_encode($BA['sub'])).' VSUB_S='.escapeshellarg(json_encode($BS['sub']));
$outp = (string)shell_exec($env.' php -d display_errors=stderr '.escapeshellarg($sb.'/msgpush.php').' 2>&1');
$res = json_decode(substr($outp, (int)strrpos($outp, '[[') ?: 0), true);
$t('iki mesaj yazildi', ($res[0]['ok'] ?? false) === true && ($res[1]['ok'] ?? false) === true);
if (!is_array($res)) echo "    cikti: ".substr($outp, 0, 600)."\n";
$ms = array_map(fn($l) => json_decode($l, true), file($site.'/data/_msg_sent.jsonl', FILE_IGNORE_NEW_LINES) ?: []);
$toA = current(array_filter($ms, fn($x) => str_ends_with($x['ep'] ?? '', '/buyerA'))) ?: ['b' => ''];
$toS = current(array_filter($ms, fn($x) => str_ends_with($x['ep'] ?? '', '/sellerS'))) ?: ['b' => ''];
$ja = json_decode((string)t_decrypt(base64_decode($toA['b']), $BA['priv'], $BA['pub'], $BA['auth']), true) ?: [];
$js = json_decode((string)t_decrypt(base64_decode($toS['b']), $BS['priv'], $BS['pub'], $BS['auth']), true) ?: [];
$tid = $res[0]['thread_id'] ?? '?';
$t('aliciya: baslik "Verkäufer <urun kimligi>" (Almanca hesap)', ($ja['title'] ?? '') === 'Verkäufer lst-1');
$t('aliciya: magaza/firma adi YOK', !str_contains(json_encode($ja, JSON_UNESCAPED_UNICODE), 'Co ssss3333'));
$t('aliciya: govde mesajin kendisi', ($ja['body'] ?? '') === 'Hallo, 10 Stück sind verfügbar.');
$t('aliciya: dokununca O konusma', ($ja['url'] ?? '') === '/buyer?tab=messages&thread='.$tid && ($ja['tag'] ?? '') === 'msg-'.$tid);
$t('aliciya: rozet = okunmamis konusma (1)', ($ja['unread'] ?? -1) === 1);
$t('saticiya: baslik alicinin firmasi (satici aliciyi gorur)', ($js['title'] ?? '') === 'Co aaaa1111');
$t('saticiya: dil hesabindan (it)', ($js['lang'] ?? '') === 'it' && ($js['url'] ?? '') === '/seller?tab=messages&thread='.$tid);

echo "\n== 8. Kablolama (kaynak) ==\n";
$left = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/vestra', FilesystemIterator::SKIP_DOTS)) as $f) {
    if ($f->getExtension() !== 'php' || str_ends_with($f->getPathname(), '/inc/push.php')) continue;
    if (preg_match('/\bvestra_push_send\s*\(/', (string)file_get_contents($f->getPathname()))) $left[] = basename($f->getPathname());
}
$t('hicbir sayfa eski Ingilizce vestra_push_send cagirmiyor ('.implode(',', $left).')', $left === []);
$auth = (string)file_get_contents($root.'/vestra/inc/auth.php');
$t('auth_logout cihazi ayiriyor', (bool)preg_match('/function auth_logout\(\).*?vestra_push_signout/s', $auth));
$foot = (string)file_get_contents($root.'/vestra/inc/foot.php');
$t('foot.php kendi SW kaydini/opt-in kopyasini tasimiyor', !str_contains($foot, 'serviceWorker.register') && !str_contains($foot, 'pushManager'));
$tab = (string)file_get_contents($root.'/vestra/inc/tabbar.php');
$t('tabbar.php app istemcisini tek yerden yukluyor', str_contains($tab, 'vestra_app_boot()'));
$mf = json_decode((string)file_get_contents($root.'/vestra/site.webmanifest'), true);
$t('manifest kisayollari rol-bagimsiz (/buyer|/seller dogrudan yok)', !preg_match('#"/(buyer|seller)#', json_encode($mf['shortcuts'] ?? [])));
$t('manifest yon kilidi yok, launch_handler var', ($mf['orientation'] ?? '') === 'any' && !empty($mf['launch_handler']));
$off = (string)file_get_contents($root.'/vestra/offline.html');
$offHd = ['Sie sind offline', 'Vous êtes hors ligne', 'Sei offline', 'Estás sin conexión', 'Está offline', 'Нет подключения', 'أنت غير متصل', 'オフラインです'];
$t('cevrimdisi sayfa 8 dil daha tasiyor', count(array_filter($offHd, fn($h) => str_contains($off, $h))) === 8 && str_contains($off, 'vlang='));
$sw = (string)file_get_contents($root.'/vestra/sw.js');
$t('SW onbellegi v3 (offline.html degisti)', str_contains($sw, "const CACHE = 'vestra-v3'"));
$css = (string)file_get_contents($root.'/vestra/inc/style.css');
$t('CSS: gizli dugme gercekten gizli ([hidden] zorlaniyor)', str_contains($css, '.vpush [hidden]') && str_contains($css, 'display:none!important'));
/* Durum rozeti SARILABILMELI: nowrap iken Fransizca iPhone durumu kartin 63px disina
   tasiyordu (cizilerek olculdu). Olcut kuralin kendi govdesi, dosyanin tamami degil. */
$stRule = preg_match('/\.vpush-st\{([^}]*)\}/', $css, $mm) ? $mm[1] : '';
$t('CSS: durum rozeti sarilabiliyor (nowrap yok, ust sinir var)', $stRule !== '' && !str_contains($stRule, 'nowrap') && str_contains($stRule, 'max-width'));
$de = include $root.'/vestra/inc/lang/de.php';
$keys = ['On for this device', 'Turn on', 'Send a test', 'Notifications', 'On iPhone and iPad, notifications work once VESTRA is on your Home Screen: tap Share → Add to Home Screen, open VESTRA from there, then turn notifications on.'];
$t('yeni arayuz metinleri de.php\'de', count(array_filter($keys, fn($k) => isset($de[$k]))) === count($keys));

/* Kum havuzunu kaldir (gercek data/'ya hic dokunulmadi; hepsi gecici dizinde). */
$rm = function (string $p) use (&$rm) { if (is_dir($p) && !is_link($p)) { foreach (scandir($p) as $f) if ($f !== '.' && $f !== '..') $rm("$p/$f"); @rmdir($p); } else @unlink($p); };
$rm($sb);

echo "\n".($bad ? "BASARISIZ: $ok iddia gecti, $bad dustu\n" : "push_app_test: $ok iddia gecti\n");
exit($bad ? 1 : 0);
