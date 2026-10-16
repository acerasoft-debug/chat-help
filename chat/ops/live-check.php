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

/* Statik / sayfalar */
req('widget.js', "$base/chat/widget.js", null, 20, 80);
req('agent.php (Studio)', "$base/chat/agent.php", null, 20, 80);
req('home.php', "$base/home.php", null, 20, 80);
$r = req('/ (kök — Cloudflare önbelleği?)', "$base/", null, 20, 80);
echo "  → ".(strpos($r['body'], 'KI, die') !== false || strpos($r['body'], 'Agenten') !== false ? "YENİ SİTE ✓" : "eski/WordPress içerik (edge cache) ✗ — panelden Cache leeren")."\n\n";
req('/chat/ (Recht uygulaması)', "$base/chat/", null, 20, 80);
echo "BİTTİ\n";
