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
    usleep(1100000);                                   // ücretsiz/kredili plan: saniyede 1 istek
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

if ($web) $out['web'] = $web;
if ($places) $out['places'] = $places;
echo json_encode($out, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), "\n";
