<?php
/* Sunucuda (CLI) çalışır — find-customers.yml'nin ARAMA VEKİLİ.
   Neden sunucuda: anahtarlar (Brave, Google) admin panelinden girilir ve sunucudaki
   data/email_settings.json'da kalır. Repo PUBLIC; anahtar hiçbir zaman koda, workflow
   girdisine ya da Actions loguna girmez (inc/discover_google.php'deki kural). Runner
   sorguları STDIN'den JSON olarak verir, URL listesini STDOUT'tan JSON olarak alır.

   STDIN : {"web":[{"q":"…","lang":"de","cc":"DE"}…],
            "places":[{"country":"Italy","city":"Milano"}…],
            "places_phrases":4, "budget":300}
   STDOUT: {"keys":{"brave":bool,"google":bool},
            "web":{"<q>":["url",…]},
            "places":{"Italy|Milano":[{"url","name","address","phone"}…]},
            "stats":{"brave":{"ok","err","note"},"places":{"ok","err","note"}}}

   Arama motoru HTML'i KAZINMAZ — yalnızca resmi API'ler (Brave Search API, Google
   Places API (New)). Sonuç yalnızca aday URL listesidir; e-posta burada aranmaz. */
ini_set('display_errors', 'stderr');
error_reporting(E_ALL);
@set_time_limit(0);
$home = getenv("HOME");
require       $home."/public_html/inc/products.php";
require_once  $home."/public_html/inc/notify.php";
if (is_readable($home."/public_html/inc/discover_google.php")) require_once $home."/public_html/inc/discover_google.php";

$req = json_decode((string)stream_get_contents(STDIN), true);
if (!is_array($req)) $req = [];
$budget = max(30, min(900, (int)($req['budget'] ?? 300)));
$deadline = time() + $budget;

$brave = trim((string)vestra_cfg('brave_key', ''));
$gkey  = function_exists('vestra_google_key') ? vestra_google_key() : '';
$out = [
  'keys'   => ['brave' => $brave !== '', 'google' => $gkey !== ''],
  'web'    => new stdClass(),
  'places' => new stdClass(),
  'stats'  => ['brave' => ['ok'=>0, 'err'=>0, 'note'=>''], 'places' => ['ok'=>0, 'err'=>0, 'note'=>'']],
];
$web = []; $places = [];

/* ---- Brave Search API ---- */
function vs_brave(string $key, string $q, string $cc, string $lang, bool $withLocale): array {
  $p = ['q' => $q, 'count' => 20, 'safesearch' => 'off'];
  if ($withLocale && $cc !== '')   $p['country'] = $cc;
  if ($withLocale && $lang !== '') $p['search_lang'] = $lang;
  $ch = curl_init('https://api.search.brave.com/res/v1/web/search?'.http_build_query($p));
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_ENCODING => '',
    CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-Subscription-Token: '.$key],
  ]);
  $raw = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  return [$code, is_string($raw) ? $raw : ''];
}
if ($brave !== '') {
  /* Brave'in dil/ülke kodu listesi her dili kapsamıyor; desteklenmeyen kod 422 döner.
     O durumda aynı sorgu yerel ayarsız tekrarlanır — sonuç daha az yerel ama boş değil. */
  $LANG_OK = ['en','de','it','fr','es','nl','pl','el','cs','da','sv','nb','fi','hu','ro','tr'];
  $CC_OK   = ['DE','AT','CH','IT','FR','ES','NL','BE','PT','PL','GR','CZ','DK','SE','NO','FI','GB','IE','US','CA','AU','NZ','JP','TR'];
  foreach ((array)($req['web'] ?? []) as $w) {
    if (time() > $deadline) { $out['stats']['brave']['note'] = 'süre bütçesi doldu'; break; }
    $q = trim((string)($w['q'] ?? '')); if ($q === '') continue;
    $lang = strtolower((string)($w['lang'] ?? '')); if (!in_array($lang, $LANG_OK, true)) $lang = '';
    $cc   = strtoupper((string)($w['cc'] ?? ''));   if (!in_array($cc, $CC_OK, true)) $cc = '';
    [$code, $raw] = vs_brave($brave, $q, $cc, $lang, true);
    if ($code === 422 || $code === 400) [$code, $raw] = vs_brave($brave, $q, '', '', false);
    if ($code === 429) { sleep(2); [$code, $raw] = vs_brave($brave, $q, $cc, $lang, true); }
    if ($code === 401 || $code === 403) { $out['stats']['brave']['err']++; $out['stats']['brave']['note'] = "Brave anahtarı reddedildi (HTTP {$code}) — admin panelinden yeni anahtar girin."; break; }
    if ($code === 402) { $out['stats']['brave']['err']++; $out['stats']['brave']['note'] = 'Brave aylık kredisi bitti (HTTP 402) — Brave panelinden planı kontrol edin.'; break; }
    if ($code !== 200) { $out['stats']['brave']['err']++; $out['stats']['brave']['note'] = "Brave HTTP {$code}"; usleep(1100000); continue; }
    $d = json_decode($raw, true); $urls = [];
    foreach ((array)($d['web']['results'] ?? []) as $r) { $u = (string)($r['url'] ?? ''); if ($u !== '') $urls[] = $u; }
    $web[$q] = $urls; $out['stats']['brave']['ok']++;
    usleep(400000);                                    // 13 Eki: kredili Search planı 50 istek/sn; 2,5/sn yeterince nazik
  }
}

/* ---- Google Places API (New) — mevcut admin anahtarı (inc/discover_google.php) ---- */
if ($gkey !== '' && function_exists('vestra_google_places_call') && function_exists('vestra_google_locale')) {
  $nPh = max(1, min(6, (int)($req['places_phrases'] ?? 4)));
  foreach ((array)($req['places'] ?? []) as $p) {
    if (time() > $deadline) { $out['stats']['places']['note'] = 'süre bütçesi doldu'; break; }
    $country = trim((string)($p['country'] ?? '')); $city = trim((string)($p['city'] ?? ''));
    if ($country === '') continue;
    [$region, $lang, $phrases] = vestra_google_locale($country);
    $key = $country.'|'.$city; $rows = []; $seen = [];
    foreach (array_slice($phrases, 0, $nPh) as $ph) {
      $q = trim($ph.' '.($city !== '' ? $city : $country).($region === '' && $city !== '' ? ' '.$country : ''));
      $d = vestra_google_places_call($q, $region, $lang);
      if (!$d) {
        $out['stats']['places']['err']++;
        $out['stats']['places']['note'] = function_exists('vestra_google_note') ? vestra_google_note() : 'Google Places hatası';
        if (function_exists('vestra_google_ok') && !vestra_google_ok() && preg_match('/anahtar|faturaland|etkin değil|kota/u', $out['stats']['places']['note'])) break 2;
        continue;
      }
      $out['stats']['places']['ok']++;
      foreach ((array)($d['places'] ?? []) as $pl) {
        if (($pl['businessStatus'] ?? 'OPERATIONAL') !== 'OPERATIONAL') continue;
        $u = trim((string)($pl['websiteUri'] ?? '')); if ($u === '' || isset($seen[$u])) continue;
        $seen[$u] = true;
        $rows[] = ['url' => $u, 'name' => (string)($pl['displayName']['text'] ?? ''),
                   'address' => (string)($pl['formattedAddress'] ?? ''), 'phone' => (string)($pl['nationalPhoneNumber'] ?? '')];
      }
      usleep(300000);
    }
    $places[$key] = $rows;
  }
}

/* ---- Ücretsiz motorlar (13 Eki 2026, Brave iptal): DuckDuckGo HTML + Bing, sunucunun IP'sinden ----
   GitHub koşucuları bu motorlarda captcha yiyor; paylaşımlı barındırma IP'si tarayıcı gibi davranınca geçiyor.
   Engellenirse (captcha / 403 / 429) o motor bu koşuda kapanır; sorgular arası 2,5 sn. */
function vs_http(string $url, array $hdr = [], ?string $post = null): array {
  $ch = curl_init($url);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_ENCODING => '',
    CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
    CURLOPT_HTTPHEADER => array_merge(['Accept: text/html,application/xhtml+xml', 'Accept-Language: en,de;q=0.8,it;q=0.7,fr;q=0.7'], $hdr)]);
  if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $post); }
  $raw = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  return [$code, is_string($raw) ? $raw : ''];
}
function vs_unwrap(string $u): string {
  if (preg_match('#^https?://(?:www\.)?bing\.com/ck/#i', $u) && preg_match('/[?&]u=a1([A-Za-z0-9_-]+)/', $u, $m)) {
    $b = strtr($m[1], '-_', '+/'); $d = base64_decode($b.str_repeat('=', (4 - strlen($b) % 4) % 4), true); return is_string($d) ? $d : '';
  }
  if (preg_match('#duckduckgo\.com/l/\?.*uddg=([^&]+)#i', $u, $m)) return urldecode($m[1]);
  return $u;
}
function vs_ddg(string $q): array {
  [$c, $b] = vs_http('https://html.duckduckgo.com/html/', ['Content-Type: application/x-www-form-urlencoded'], http_build_query(['q' => $q]));
  if ($c !== 200) return [$c, []];
  if (stripos($b, 'anomaly') !== false || stripos($b, 'captcha') !== false) return [429, []];
  preg_match_all('/class="result__a"[^>]+href="([^"]+)"/', $b, $m);
  $urls = []; foreach ($m[1] as $h) { $u = vs_unwrap(html_entity_decode($h)); if (preg_match('#^https?://#i', $u)) $urls[] = $u; }
  return [200, array_values(array_unique($urls))];
}
function vs_bing(string $q, string $lang): array {
  [$c, $b] = vs_http('https://www.bing.com/search?'.http_build_query(['q' => $q, 'count' => 20, 'setlang' => $lang ?: 'en']));
  if ($c !== 200) return [$c, []];
  if (preg_match('/captcha|verify you are human|b_captcha/i', $b)) return [429, []];
  preg_match_all('/<li class="b_algo".*?<h2>\s*<a[^>]+href="([^"]+)"/s', $b, $m);
  $urls = []; foreach ($m[1] as $h) { $u = vs_unwrap(html_entity_decode($h)); if (preg_match('#^https?://#i', $u)) $urls[] = $u; }
  return [200, array_values(array_unique($urls))];
}
$free = (array)($req['web_free'] ?? []);
if ($free) {
  $st = ['ddg_ok' => 0, 'ddg_err' => 0, 'bing_ok' => 0, 'bing_err' => 0, 'note' => ''];
  $dead = ['ddg' => false, 'bing' => false];
  foreach ($free as $w) {
    if (time() > $deadline) { $st['note'] = 'süre bütçesi doldu'; break; }
    if ($dead['ddg'] && $dead['bing']) { $st['note'] = 'iki motor da engelledi (captcha/429)'; break; }
    $q = trim((string)($w['q'] ?? '')); if ($q === '') continue;
    $lang = preg_replace('/[^a-z]/', '', strtolower((string)($w['lang'] ?? '')));
    $urls = [];
    if (!$dead['ddg']) { [$c, $u] = vs_ddg($q); if ($c === 200 && $u) { $urls = $u; $st['ddg_ok']++; } else { $st['ddg_err']++; if (in_array($c, [403, 429, 202], true)) $dead['ddg'] = true; } usleep(2500000); }
    if (!$urls && !$dead['bing']) { [$c, $u] = vs_bing($q, $lang); if ($c === 200 && $u) { $urls = $u; $st['bing_ok']++; } else { $st['bing_err']++; if (in_array($c, [403, 429], true)) $dead['bing'] = true; } usleep(2500000); }
    if ($urls) $web[$q] = $urls;
  }
  $out['stats']['free'] = $st;
}

if ($web) $out['web'] = $web;
if ($places) $out['places'] = $places;
echo json_encode($out, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), "\n";
