<?php
/* Sunucuda (CLI) çalışır — find-customers.yml tarafından scp ile gönderilir.
   Runner'a iki şey verir (STDOUT'ta SADECE JSON; uyarılar STDERR'e):
     emails/domains : data/leads.json'daki TÜM adresler ve site alan adları — daha önce
                      eklenmiş (gönderilmiş, bounce etmiş, çıkmış) bir butik tekrar eklenmesin.
     state          : data/finder_state.json — taranan alan adları + çalışan sorgular.
                      Repo PUBLIC olduğu için bu liste repoda değil sunucuda tutulur.
   Yalnızca OKUR, hiçbir şeyi değiştirmez. */
ini_set('display_errors', 'stderr');
error_reporting(E_ALL);
$home = getenv("HOME");
require       $home."/public_html/inc/products.php";
require_once  $home."/public_html/inc/leads.php";

$leads = vestra_leads();
$emails = []; $domains = [];
foreach ($leads as $l) {
  $e = strtolower(trim((string)($l['email'] ?? '')));
  if ($e !== '') $emails[$e] = true;
  $w = trim((string)($l['website'] ?? ''));
  if ($w !== '') {
    if (!preg_match('#^https?://#i', $w)) $w = 'https://'.$w;
    $h = strtolower((string)(parse_url($w, PHP_URL_HOST) ?? ''));
    $h = preg_replace('/^www\d*\./', '', $h);
    if ($h !== '') $domains[$h] = true;
  }
}
$state = vestra_read_json('finder_state.json');
require_once $home."/public_html/inc/notify.php";
if (is_readable($home."/public_html/inc/discover_google.php")) require_once $home."/public_html/inc/discover_google.php";
/* Yalnızca VAR/YOK — anahtarın kendisi sunucudan hiç çıkmaz. */
$keys = [
  'brave'  => trim((string)vestra_cfg('brave_key', '')) !== '',
  'google' => function_exists('vestra_google_key') && vestra_google_key() !== '',
];
/* 11 Eki 2026 (operatör: "günde 500'e kadar butik bulsun"): bugün (UTC) web aramasıyla eklenen admin leadleri. */
$foundToday = 0; $todayUtc = gmdate('Y-m-d');
foreach ($leads as $l) if (($l['source'] ?? '') === 'web-search' && ($l['owner_uid'] ?? '') === ''
  && gmdate('Y-m-d', (int)strtotime((string)($l['added_at'] ?? ''))) === $todayUtc) $foundToday++;
echo json_encode([
  'found_today' => $foundToday,
  'emails'  => array_keys($emails),
  'domains' => array_keys($domains),
  'total'   => count($leads),
  'state'   => $state ?: new stdClass(),
  'keys'    => $keys,
  /* 16 Eki 2026: gönderimi durdurulan ülkeler (mailbox.json paused_countries) — bulucu otomatik turda o ülkelerin
     şehir/karelerini taramaz (bulunanlar zaten gönderilemezdi). Fonksiyon yoksa boş. */
  'paused'  => (function () use ($home) { if (is_readable($home.'/public_html/inc/finder.php')) { require_once $home.'/public_html/inc/finder.php'; }
                 return function_exists('vestra_send_paused_countries') ? array_values(vestra_send_paused_countries()) : []; })(),
], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), "\n";
