<?php
/* Sunucuda (CLI) çalışır — find-customers.yml tarafından scp ile gönderilir.
   Runner'ın bulduğu leadleri data/leads.json'a EKLER ve tarama durumunu saklar.
     php vestra-push-leads.php <found.json> [state.json] [owner_uid]
   - Tekrar koruması: e-posta VE site alan adı (biri varsa eklenmez).
   - KURAL 1 (CLAUDE.md): her satır vestra_lead_is_blocked()'tan geçer (zincir,
     distribütör, monobrand, servis sağlayıcı adresi, kampanya dışı); KURAL 1d:
     park edilmiş alan adı adı (vestra_name_is_parked_domain) elenir.
   - Kayıt şeması vestra_leads_add ile aynı; premium_brands DOLU ve _screened=true
     geldiği için marka taraması bunları yeniden gezmez.
   - owner_uid verilirse leadler o SATICIYA ait olur (satıcı panelinde görünür).
   - state.json (taranan alan adları + sorgular) data/finder_state.json'a yazılır;
     repo PUBLIC olduğu için bu liste repoda tutulmaz.
   - Yazmadan önce zaman damgalı yedek alır. Hiçbir e-posta GÖNDERMEZ. */
ini_set('display_errors', '1');
error_reporting(E_ALL);
$home = getenv("HOME");
require       $home."/public_html/inc/products.php";
require_once  $home."/public_html/inc/leads.php";
require_once  $home."/public_html/inc/notify.php";

$file  = (string)($argv[1] ?? '');
$sfile = (string)($argv[2] ?? '');
$owner = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($argv[3] ?? ''));
if ($file === '' || !is_readable($file)) { fwrite(STDERR, "kullanim: php vestra-push-leads.php <found.json> [state.json] [owner_uid]\n"); exit(2); }
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
  if ($company === '') $company = $d !== '' ? $d : $email;
  $probe = ['company'=>$company, 'email'=>$email, 'website'=>$website];
  if ((function_exists('vestra_lead_is_blocked') && vestra_lead_is_blocked($probe))
      || (function_exists('vestra_name_is_parked_domain') && vestra_name_is_parked_domain($company))) {
    $blocked++; echo "  x KURAL 1 / park: {$company} <{$email}>\n"; continue;
  }

  $pb = array_values(array_filter(array_map('strval', (array)($r['premium_brands'] ?? []))));
  $notes = 'web-search '.date('Y-m-d');
  if (!empty($r['query']))  $notes .= ' · sorgu: '.mb_substr((string)$r['query'], 0, 80);
  if (!empty($r['alt_emails']) && is_array($r['alt_emails'])) $notes .= ' · alt: '.implode(', ', array_map('strval', $r['alt_emails']));

  $leads[] = [
    'id'=>'LD'.strtoupper(bin2hex(random_bytes(4))), 'added_at'=>date('c'), 'owner_uid'=>$owner,
    'company'=>$company, 'contact_name'=>'',
    'email'=>$email, 'country'=>trim((string)($r['country'] ?? '')),
    'website'=>$website, 'phone'=>trim((string)($r['phone'] ?? '')),
    'source'=>'web-search', 'category'=>'Boutique (multi-brand)',
    'notes'=>$notes, 'status'=>'new', 'last_contacted_at'=>'', 'unsub_token'=>bin2hex(random_bytes(16)),
    'premium'=>!empty($pb), 'premium_brands'=>$pb, '_screened'=>true,
  ];
  $byMail[$email] = true; if ($d !== '') $byDom[$d] = true;
  $added++;
  echo "  + {$company} <{$email}> | ".((string)($r['country'] ?? '') ?: '-')." | ".implode(', ', array_slice($pb, 0, 4))."\n";
}

if ($added > 0) {
  $lf = vestra_data_dir()."/leads.json";
  if (is_readable($lf)) @copy($lf, $lf.".bak-ws-".date("Ymd_His"));
  vestra_save_leads($leads);
}

if ($sfile !== '' && is_readable($sfile)) {
  $st = json_decode((string)file_get_contents($sfile), true);
  if (is_array($st) && isset($st['domains']) && is_array($st['domains'])) {
    vestra_write_json('finder_state.json', $st);
    echo "durum kaydedildi: ".count($st['domains'])." alan adi, ".count((array)($st['queries'] ?? []))." sorgu\n";
  } else {
    echo "UYARI: durum dosyasi gecersiz -- kaydedilmedi\n";
  }
}
echo "\nEKLENDI: {$added} | zaten vardi: {$dup} | gecersiz: {$bad} | junk: {$junk} | KURAL 1/park: {$blocked} | toplam lead: ".count($leads).($owner !== '' ? " | sahip: {$owner}" : '')."\n";
