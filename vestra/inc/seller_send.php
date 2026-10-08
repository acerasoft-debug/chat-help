<?php
/**
 * VESTRA — satıcının kendi adresinden kampanya gönderimi: kurulum denetimi + test gönderimi
 * (operatör, 8 Eki 2026: "test email gönderimi de koy hem satıcılara hem bana ve mümkünse
 * ücretsiz kendi emailinden kampanya göndermek için kurulum ayarlarını da yap onlara").
 *
 * NEDEN BREVO, NEDEN SMTP DEĞİL: 8 Eki 2026 smtp_probe ölçümü — bu barındırmadan giden
 * SMTP'nin TAMAMI kapalı (smtp.gmail.com 587/465, Yahoo, iCloud, Zoho, Outlook, Brevo SMTP:
 * hepsi "Connection timed out"). Yani Gmail uygulama şifresi gibi bir SMTP girişi bu sunucuda
 * ÇALIŞMAZ. HTTPS (443) açık; Brevo'nun API'si oradan gider ve ücretsiz planı (günde 300)
 * satıcının KENDİ doğrulanmış adresinden gönderir. Kurulum ekranı bu yüzden Brevo'ya göre.
 *
 * TEST GÖNDERİMİ: satıcının gerçekten kullandığı kampanya (etkin Claude kampanyası ya da
 * standart davet) örnek bir dükkân adıyla, konu "[TEST]" önekiyle, müşterinin göreceği gibi.
 *  - Gönderim kurulu ise: satıcının KENDİ Brevo'su ile, istediği adrese (kurulumu da sınar).
 *  - Kurulu değilse: platform GÖNDERMEZ (operatör, 8 Eki 2026: "sadece Gmail'de aç + satıcının
 *    kendi Brevo'su", platform kotasına hiç dokunulmasın) — test satıcının kendi Gmail'inde açılır.
 *
 * GMAIL'DE AÇ (vestra_seller_compose): kurulumsuz, ücretsiz, gerçekten satıcının adresinden.
 * Sunucu yalnız o müşteriye çizilmiş konu + metni döndürür (çıkış linki dahil); tarayıcı
 * satıcının Gmail / Outlook / e-posta uygulamasında yazma penceresini açar, "Gönder"e satıcı basar.
 * VESTRA'dan hiçbir e-posta çıkmaz.
 */
require_once __DIR__.'/notify.php';
require_once __DIR__.'/leads.php';

const VESTRA_TEST_SAMPLE_SHOP = 'Boutique Example';

/**
 * Brevo anahtarını ve gönderen adresi sınar — iki ücretsiz okuma (GET /v3/account, /v3/senders),
 * hiçbir e-posta gitmez, kredi harcanmaz.
 * Döner: ['ok'=>bool, 'code'=>'ok'|'format'|'invalid'|'unreachable', 'account'=>string,
 *         'sender_ok'=>bool|null, 'senders'=>[doğrulanmış adresler], 'checked_at'=>ISO]
 * $http(url, key): [httpKodu, çözülmüş JSON|null] — testte sahtesi verilir.
 */
function vestra_brevo_check(string $key, string $from, ?callable $http = null): array {
    $key = trim($key); $from = strtolower(trim($from));
    $r = ['ok' => false, 'code' => 'format', 'account' => '', 'sender_ok' => null, 'senders' => [], 'checked_at' => date('c')];
    if (!preg_match('/^xkeysib-[A-Za-z0-9\-]{20,}$/', $key)) return $r;
    $http = $http ?? function (string $url, string $k): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['api-key: '.$k, 'Accept: application/json']]);
        $raw = curl_exec($ch); $c = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        return [$c, is_string($raw) ? json_decode($raw, true) : null];
    };
    [$c, $acc] = $http('https://api.brevo.com/v3/account', $key);
    if ($c === 401 || $c === 403) { $r['code'] = 'invalid'; return $r; }
    if ($c !== 200) { $r['code'] = 'unreachable'; return $r; }
    $r['ok'] = true; $r['code'] = 'ok';
    $r['account'] = trim((string)($acc['companyName'] ?? '')) ?: trim((string)($acc['email'] ?? ''));
    [$c2, $snd] = $http('https://api.brevo.com/v3/senders', $key);
    if ($c2 === 200 && is_array($snd)) {
        foreach ((array)($snd['senders'] ?? []) as $s) {
            if (!empty($s['active']) && ($e = strtolower(trim((string)($s['email'] ?? '')))) !== '') $r['senders'][] = $e;
        }
        $r['sender_ok'] = $from !== '' && in_array($from, $r['senders'], true);
    }
    return $r;
}

/** Test için örnek müşteri: kampanya ona çizilir; çıkış linki jetonsuz kalır. */
function vestra_test_sample_lead(string $to): array {
    return ['id' => 'TEST', 'company' => VESTRA_TEST_SAMPLE_SHOP, 'contact_name' => '', 'email' => $to,
            'country' => '', 'unsub_token' => '', 'status' => 'new'];
}

/** Satıcının şu an kullandığı kampanya: etkin Claude kampanyası, yoksa standart davet. */
function vestra_seller_test_template(string $uid, ?string $campId = null): array {
    require_once __DIR__.'/ai_campaign.php';
    if ($campId !== null && $campId !== '') {
        $c = vestra_ai_camp_get($campId, $uid);
        if ($c) return [vestra_ai_camp_template($c), 'ai'];
    }
    $act = vestra_ai_camp_active($uid);
    return $act ? [vestra_ai_camp_template($act), 'ai'] : [vestra_lead_template(), 'standard'];
}

/**
 * Satıcı test gönderimi — YALNIZ satıcının kendi kurulumuyla. [ok, kod, gidenAdres]
 * kod: 'own' | 'nosetup' (kurulum yok → testi Gmail'de açsın) | 'badto' | 'fail'
 * $send: testte sahte gönderici (vestra_send_mail imzası).
 */
function vestra_seller_send_test(string $uid, string $sName, string $to, ?string $campId = null, ?callable $send = null): array {
    $send = $send ?? 'vestra_send_mail';
    $sc = vestra_seller_mail($uid);
    if (!vestra_seller_can_send($sc)) return [false, 'nosetup', ''];
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return [false, 'badto', ''];
    [$tpl] = vestra_seller_test_template($uid, $campId);
    [$s, $b] = vestra_lead_render_email(vestra_test_sample_lead($to), $tpl);
    $heroImg = ($tpl['img'] ?? '') !== '' ? 'https://vestrasales.com'.$tpl['img'] : '';
    $ok = (bool)$send($to, '[TEST] '.$s, $b, '', $sName, $sc, $heroImg);
    if ($ok) {
        $sc['last_test_ok_at'] = date('c'); $sc['last_test_to'] = $to;
        vestra_seller_mail_save($uid, $sc);
    }
    return [$ok, $ok ? 'own' : 'fail', $to];
}

/**
 * "Gmail'de aç": o müşteriye çizilmiş e-posta. VESTRA hiçbir şey GÖNDERMEZ.
 * $leadId '' ise TEST: örnek dükkânla, satıcının hesap adresine, "[TEST]" önekli, kayda dokunmaz.
 * Gerçek müşteride: yalnız satıcının KENDİ müşterisi, e-postası geçerli, abonelikten çıkmamış;
 * müşteri "yazıldı" işaretlenir (pencere açıldı — gönderildiği bilinmiyor, 'contact_via' bunu söyler).
 * Döner: ['ok'=>bool, 'error'=>''|'notfound'|'noemail'|'unsub', 'to','subject','body','company']
 */
function vestra_seller_compose(string $uid, string $leadId, string $accountEmail, ?string $campId = null, string $via = 'gmail'): array {
    $out = ['ok' => false, 'error' => 'notfound', 'to' => '', 'subject' => '', 'body' => '', 'company' => ''];
    [$tpl] = vestra_seller_test_template($uid, $campId);
    if ($leadId === '') {
        if (!filter_var($accountEmail, FILTER_VALIDATE_EMAIL)) { $out['error'] = 'noemail'; return $out; }
        [$s, $b] = vestra_lead_render_email(vestra_test_sample_lead($accountEmail), $tpl);
        return ['ok' => true, 'error' => '', 'to' => $accountEmail, 'subject' => '[TEST] '.$s, 'body' => $b, 'company' => VESTRA_TEST_SAMPLE_SHOP];
    }
    $leads = vestra_leads(); $hit = null;
    foreach ($leads as $i => $l) {
        if (($l['id'] ?? '') !== $leadId || (string)($l['owner_uid'] ?? '') !== $uid) continue;
        $hit = $i; break;
    }
    if ($hit === null) return $out;
    $l = $leads[$hit]; $out['company'] = (string)($l['company'] ?? '');
    if (($l['status'] ?? '') === 'unsubscribed' || !empty($l['unsubscribed'])) { $out['error'] = 'unsub'; return $out; }
    if (!filter_var($l['email'] ?? '', FILTER_VALIDATE_EMAIL)) { $out['error'] = 'noemail'; return $out; }
    [$s, $b] = vestra_lead_render_email($l, $tpl);
    $leads[$hit]['last_contacted_at'] = date('c');
    $leads[$hit]['contact_via'] = in_array($via, ['gmail', 'outlook', 'mailapp'], true) ? $via : 'gmail';
    if (($l['status'] ?? 'new') === 'new') $leads[$hit]['status'] = 'contacted';
    vestra_save_leads($leads);
    return ['ok' => true, 'error' => '', 'to' => (string)$l['email'], 'subject' => $s, 'body' => $b, 'company' => $out['company']];
}
