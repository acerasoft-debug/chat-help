<?php
/**
 * ChatHelp ops — live-check: canlı uç noktaları sunucudan test et (yazmaz)
 * =======================================================================
 * Recht arka ucu (api.php: detect_location, aichat), token API (tokens-api.php), concierge (GET + POST → gerçek AI
 * yanıtı), widget.js, agent.php, ana sayfa. Her istek için durum kodu, süre ve gövde özeti yazdırılır.
 * args: {"ai":"0"} → AI çağrıları (aichat, concierge POST) atlanır.
 */
header('Content-Type: text/plain; charset=UTF-8');
@set_time_limit(180);
$base = 'https://chat-help.com';
$ai = ($_GET['ai'] ?? '1') !== '0';
echo "ChatHelp — live-check  (".date('c').")  AI testleri: ".($ai ? 'açık' : 'kapalı')."\n\n";

function req(string $label, string $url, ?array $json = null, int $to = 60, int $show = 320): array {
    $ch = curl_init($url);
    $opt = [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>$to, CURLOPT_HEADER=>true, CURLOPT_USERAGENT=>'ChatHelp-live-check'];
    if ($json !== null) { $opt[CURLOPT_POST] = true; $opt[CURLOPT_POSTFIELDS] = json_encode($json, JSON_UNESCAPED_UNICODE); $opt[CURLOPT_HTTPHEADER] = ['Content-Type: application/json']; }
    curl_setopt_array($ch, $opt);
    $t = microtime(true); $res = (string)curl_exec($ch); $dt = microtime(true) - $t;
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE); $err = curl_error($ch); curl_close($ch);
    $hdr = substr($res, 0, $hs); $body = substr($res, $hs);
    $ct = preg_match('/^content-type:\s*(.+)$/mi', $hdr, $m) ? trim($m[1]) : '?';
    $cache = preg_match('/^cf-cache-status:\s*(.+)$/mi', $hdr, $m) ? trim($m[1]) : '-';
    echo sprintf("=== %s ===\n  %s %s\n  status=%s  %.2fs  type=%s  cf-cache=%s  len=%d%s\n", $label, $json !== null ? 'POST' : 'GET', $url, $code ?: 'ERR', $dt, $ct, $cache, strlen($body), $err ? "  curl: $err" : '');
    $b = trim(preg_replace('/\s+/', ' ', strip_tags($body)));
    echo "  ".mb_substr($b, 0, $show)."\n\n";
    return ['code'=>$code, 'body'=>$body, 'cache'=>$cache, 'hdr'=>$hdr];
}

/* Recht arka ucu */
$r = req('Recht api.php — detect_location', "$base/chat/api.php?action=detect_location", null, 20);
$j = json_decode($r['body'], true);
echo "  → ".(isset($j['country']) ? "JSON ✓ country=".$j['country'] : "beklenen JSON değil ✗")."\n\n";
if ($ai) {
    $r = req('Recht api.php — aichat (gerçek AI)', "$base/chat/api.php?action=aichat",
        ['message'=>'Antworte nur mit dem Wort OK.', 'history'=>[], 'provider'=>'claude', 'lang'=>'de', 'country'=>'DE'], 90);
    $j = json_decode($r['body'], true);
    echo "  → ".(isset($j['reply']) && $j['reply'] !== '' ? "reply ✓ (".mb_substr(trim($j['reply']), 0, 60).")" : "reply YOK ✗ ".mb_substr($r['body'], 0, 200))."\n\n";
}

/* Token API */
$r = req('tokens-api — /v1/sectors', "$base/chat/tokens-api.php/v1/sectors", null, 20, 200);
$j = json_decode($r['body'], true);
echo "  → ".(isset($j['sectors']) ? "sectors ✓ (".count($j['sectors']).") plans=".(isset($j['plans']) ? count($j['plans']) : 0) : "JSON ✗")."\n\n";
req('tokens-api — /v1/me (anahtarsız → 401 beklenir)', "$base/chat/tokens-api.php/v1/me", null, 20, 160);
/* Ödeme yönlendirmesi önbelleğe giriyor mu? Aynı taze URL iki kez: ikincisi HIT olmamalı */
$fresh = "$base/chat/tokens-checkout.php?plan=agent-sales&_=".bin2hex(random_bytes(4));
$r1 = req('tokens-checkout — 1. istek (303 Stripe beklenir)', $fresh, null, 30, 120);
$r2 = req('tokens-checkout — 2. istek aynı URL (cf-cache HIT OLMAMALI)', $fresh, null, 30, 120);
echo "  → ".(($r2['cache'] ?? '') === 'HIT' ? "✗ 303 ÖNBELLEĞE GİRİYOR (cf-cache=HIT) — herkes aynı Stripe oturumuna gider!" : "✓ yönlendirme önbelleğe girmiyor (cf-cache=".($r2['cache'] ?? '?').")")."\n\n";

/* Concierge */
$r = req('concierge — GET greeting', "$base/chat/concierge.php?lang=de", null, 20, 240);
if ($ai) {
    $r = req('concierge — POST (gerçek AI)', "$base/chat/concierge.php",
        ['messages'=>[['role'=>'user','content'=>'Was kostet ein Widerspruch? Antworte in einem Satz.']], 'lang'=>'de'], 90, 400);
    $j = json_decode($r['body'], true);
    echo "  → ".(isset($j['reply']) ? "reply ✓ source=".($j['source'] ?? $j['mode'] ?? '?') : "reply YOK ✗")."\n\n";
}

/* Agent-Live-Demo (Startseite) */
req('concierge — GET demo greeting (agent-phone)', "$base/chat/concierge.php?agent=agent-phone", null, 20, 260);
if ($ai) {
    $r = req('concierge — POST demo (agent-inkasso, echter Agent)', "$base/chat/concierge.php",
        ['agent'=>'agent-inkasso','messages'=>[['role'=>'user','content'=>'Forderungsaufstellung: Rechnung 2026-118 über 2.450 € brutto, fällig seit 2. Juli, B2B. Nur die Gesamtsumme in einem Satz.']],'lang'=>'de'], 90, 400);
    $j = json_decode($r['body'], true); echo "  → ".(isset($j['reply']) ? "demo reply ✓ source=".($j['source'] ?? '?') : "✗ ".mb_substr($r['body'],0,200))."\n\n";
}
/* Telefon-Webhook (Twilio) */
req('phone-webhook — GET (Hinweistext)', "$base/chat/phone-webhook.php?key=wk_test", null, 20, 160);
$r = req('phone-webhook — POST ungültiger Schlüssel (TwiML „nicht aktiv“ erwartet)', "$base/chat/phone-webhook.php?key=wk_".str_repeat('0',36), ['CallSid'=>'CAtest','From'=>'+491700000000'], 20, 220);
echo "  → ".(str_contains($r['body'],'<Response>') ? "TwiML ✓" : "kein TwiML ✗")."\n\n";
/* Medien */
foreach (['media/hero.webm','media/hero.mp4','media/hero-poster.jpg'] as $m) {
    $ch=curl_init("$base/$m"); curl_setopt_array($ch,[CURLOPT_NOBODY=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_HEADER=>true]); $h=(string)curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); $len=curl_getinfo($ch,CURLINFO_CONTENT_LENGTH_DOWNLOAD); $ct=curl_getinfo($ch,CURLINFO_CONTENT_TYPE); curl_close($ch);
    echo "=== $m ===\n  status=$code type=$ct length=".($len>0?round($len/1024).' KB':'?')."\n\n";
}
/* Studio-Login end-to-end: args {"auth_email":"…"} → Code anfordern · {"auth_email":"…","code":"123456"} → anmelden + Agent testen */
$em = (string)($_GET['auth_email'] ?? ''); $code = preg_replace('/\D/','',(string)($_GET['code'] ?? ''));
if ($em !== '' && $code === '') {
    $r = req('auth/request — Code per E-Mail', "$base/chat/tokens-api.php/v1/auth/request", ['email'=>$em,'lang'=>'de'], 30, 200);
    echo "  → Jetzt Code aus dem Postfach lesen und mit args {\"auth_email\":\"$em\",\"code\":\"…\"} erneut laufen lassen.\n\n";
}
if ($em !== '' && $code !== '') {
    $r = req('auth/verify — Code → API-Key', "$base/chat/tokens-api.php/v1/auth/verify", ['email'=>$em,'code'=>$code], 30, 60);
    $j = json_decode($r['body'], true); $key = (string)($j['key'] ?? '');
    if ($key === '') { echo "  ✗ kein Key: ".mb_substr($r['body'],0,200)."\n\n"; }
    else {
        echo "  → key ✓ ".substr($key,0,12)."…  trial_credited=".($j['trial_credited'] ?? 0)."  balance=".($j['balance'] ?? '?')."\n\n";
        $auth = function(string $label, string $path, ?array $json=null, int $to=60, int $show=300) use ($base,$key) {
            $ch=curl_init("$base/chat/tokens-api.php/v1/$path"); $opt=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>$to,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json']];
            if($json!==null){ $opt[CURLOPT_POST]=true; $opt[CURLOPT_POSTFIELDS]=json_encode($json,JSON_UNESCAPED_UNICODE); }
            curl_setopt_array($ch,$opt); $t=microtime(true); $b=(string)curl_exec($ch); $dt=microtime(true)-$t; $c=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
            echo "=== $label ===\n  ".($json!==null?'POST':'GET')." /v1/$path  status=$c  ".sprintf('%.2f',$dt)."s\n  ".mb_substr(trim(preg_replace('/\s+/',' ',$b)),0,$show)."\n\n"; return json_decode($b,true) ?: [];
        };
        $me = $auth('me','me');
        $auth('agent/config GET (agent-kanzlei)','agent/config?plan=agent-kanzlei');
        $auth('agent/config POST (Wissensbasis speichern, Telefon-Felder)','agent/config?plan=agent-kanzlei',['company'=>'Kanzlei Test & Partner','kb'=>"Öffnungszeiten Mo–Fr 9–17 Uhr. Erstberatung 30 Min 150 € netto. Rechtsgebiete: Arbeitsrecht, Mietrecht.",'phone_greeting'=>'Kanzlei Test und Partner, hier ist der digitale Assistent.','phone_voice'=>'de-DE','phone_forward'=>'+49 221 000000','phone_on'=>1,'widget_on'=>1], 30, 420);
        $a = $auth('agent POST (Chat, agent-kanzlei)','agent',['plan'=>'agent-kanzlei','messages'=>[['role'=>'user','content'=>'Was kostet eine Erstberatung und wann haben Sie geöffnet? Kurz.']],'lang'=>'de'], 90, 500);
        $p = $auth('agent POST (Telefon-Modus)','agent',['plan'=>'agent-kanzlei','channel'=>'phone','messages'=>[['role'=>'user','content'=>'Ich habe gestern eine Kündigung bekommen.']],'lang'=>'de'], 90, 400);
        $auth('phone/calls','phone/calls?plan=agent-kanzlei', null, 30, 200);
        $me2 = $auth('me (nachher)','me', null, 30, 160);
        echo "  → Guthaben vorher ".($me['balance'] ?? '?')." · nachher ".($me2['balance'] ?? '?')." · Chat-Zugang: ".($a['access'] ?? '?')." · Telefon-Antwort: ".(isset($p['result'])?'✓':'✗')."\n\n";
    }
}

/* Statik / sayfalar */
req('widget.js', "$base/chat/widget.js", null, 20, 80);
req('agent.php (Studio)', "$base/chat/agent.php", null, 20, 80);
req('home.php', "$base/home.php", null, 20, 80);
$r = req('/ (kök — Cloudflare önbelleği?)', "$base/", null, 20, 80);
echo "  → ".(strpos($r['body'], 'KI, die') !== false || strpos($r['body'], 'Agenten') !== false ? "YENİ SİTE ✓" : "eski/WordPress içerik (edge cache) ✗ — panelden Cache leeren")."\n\n";
req('/chat/ (Recht uygulaması)', "$base/chat/", null, 20, 80);
echo "BİTTİ\n";
