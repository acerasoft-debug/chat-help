<?php
/* Sunucuda (CLI) çalışır — find-customers.yml tarafından scp ile gönderilir.
   data/leads.json'daki TÜM e-postaları ve site alan adlarını JSON olarak basar;
   runner bunu indirir ki daha önce eklenmiş (gönderilmiş, bounce etmiş, çıkmış) bir
   butik ikinci kez eklenmesin. Yalnızca OKUR, hiçbir şeyi değiştirmez.
   Çıktı STDOUT'ta SADECE JSON olmalı: uyarılar STDERR'e gider. */
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
echo json_encode([
  'emails'  => array_keys($emails),
  'domains' => array_keys($domains),
  'total'   => count($leads),
], JSON_UNESCAPED_SLASHES), "\n";
