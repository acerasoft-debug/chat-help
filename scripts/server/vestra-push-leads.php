<?php
/* Sunucuda (CLI) çalışır — find-customers.yml tarafından scp ile gönderilir.
   Runner'ın bulduğu leadleri (out/leads.json) data/leads.json'a EKLER.
     php vestra-push-leads.php <found.json>
   - Tekrar koruması: e-posta VE site alan adı (biri varsa eklenmez).
   - Kayıt şeması add-lead.yml ile aynı; premium_brands DOLU ve _screened=true geldiği
     için marka taraması bunları yeniden gezmez, gönderim seçicisi (2+ marka) hemen görür.
   - Sunucudaki vestra_email_is_junk / vestra_name_is_blocked varsa son kapı olarak çalışır.
   - Yazmadan önce zaman damgalı yedek alır. Hiçbir e-posta GÖNDERMEZ. */
ini_set('display_errors', '1');
error_reporting(E_ALL);
$home = getenv("HOME");
require       $home."/public_html/inc/products.php";
require_once  $home."/public_html/inc/leads.php";
require_once  $home."/public_html/inc/notify.php";

$file = (string)($argv[1] ?? '');
if ($file === '' || !is_readable($file)) { fwrite(STDERR, "kullanim: php vestra-push-leads.php <found.json>\n"); exit(2); }
$in = json_decode((string)file_get_contents($file), true);
if (!is_array($in)) { fwrite(STDERR, "gecersiz JSON: {$file}\n"); exit(2); }

function vws_domain(string $u): string {
  if ($u === '') return '';
  if (!preg_match('#^https?://#i', $u)) $u = 'https://'.$u;
  $h = strtolower((string)(parse_url($u, PHP_URL_HOST) ?? ''));
  return (string)preg_replace('/^www\d*\./', '', $h);
}

$leads = vestra_leads();
$byMail = []; $byDom = [];
foreach ($leads as $l) {
  $e = strtolower(trim((string)($l['email'] ?? '')));   if ($e !== '') $byMail[$e] = true;
  $d = vws_domain(trim((string)($l['website'] ?? ''))); if ($d !== '') $byDom[$d] = true;
}

$added = 0; $dup = 0; $bad = 0; $junk = 0; $blocked = 0;
foreach ($in as $r) {
  if (!is_array($r)) continue;
  $email = strtolower(trim((string)($r['email'] ?? '')));
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $bad++; continue; }
  if (function_exists('vestra_email_is_junk') && vestra_email_is_junk($email)) { $junk++; echo "  ~ junk adres atlandi: {$email}\n"; continue; }
  $company = trim((string)($r['company'] ?? ''));
  $website = trim((string)($r['website'] ?? ''));
  $d = vws_domain($website);
  if (isset($byMail[$email]) || ($d !== '' && isset($byDom[$d]))) { $dup++; continue; }
  if (function_exists('vestra_name_is_blocked') && vestra_name_is_blocked($company, '')) { $blocked++; echo "  x zincir (sunucu blocklist): {$company}\n"; continue; }
  if ($company === '') $company = $d !== '' ? $d : $email;

  $pb = array_values(array_filter(array_map('strval', (array)($r['premium_brands'] ?? []))));
  $notes = 'web-search '.date('Y-m-d');
  if (!empty($r['query']))  $notes .= ' · sorgu: '.mb_substr((string)$r['query'], 0, 80);
  if (!empty($r['phone']))  $notes .= ' · tel: '.(string)$r['phone'];
  if (!empty($r['alt_emails']) && is_array($r['alt_emails'])) $notes .= ' · alt: '.implode(', ', array_map('strval', $r['alt_emails']));

  $leads[] = [
    'id'=>'LD'.strtoupper(bin2hex(random_bytes(4))), 'added_at'=>date('c'), 'owner_uid'=>'',
    'company'=>$company, 'contact_name'=>'',
    'email'=>$email, 'country'=>trim((string)($r['country'] ?? '')),
    'website'=>$website, 'source'=>'web-search', 'category'=>'Boutique (multi-brand)',
    'notes'=>$notes, 'status'=>'new', 'last_contacted_at'=>'', 'unsub_token'=>bin2hex(random_bytes(16)),
    'premium'=>!empty($pb), 'premium_brands'=>$pb, '_screened'=>true,
  ];
  $byMail[$email] = true; if ($d !== '') $byDom[$d] = true;
  $added++;
  echo "  + {$company} <{$email}> | ".((string)($r['country'] ?? '') ?: '-')." | ".implode(', ', array_slice($pb, 0, 4))."\n";
}

if ($added > 0) {
  $lf = $home."/public_html/data/leads.json";
  if (is_readable($lf)) @copy($lf, $lf.".bak-ws-".date("Ymd_His"));
  vestra_save_leads($leads);
}
echo "\nEKLENDI: {$added} | zaten vardi: {$dup} | gecersiz: {$bad} | junk: {$junk} | zincir: {$blocked} | toplam lead: ".count($leads)."\n";
