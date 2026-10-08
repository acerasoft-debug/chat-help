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
echo json_encode([
  'emails'  => array_keys($emails),
  'domains' => array_keys($domains),
  'total'   => count($leads),
  'state'   => $state ?: new stdClass(),
], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), "\n";
