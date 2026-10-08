<?php
/* Sunucuda (CLI) çalışır — find-customers.yml'nin isteğe bağlı gönderim adımı.
   Henüz HİÇ yazılmamış, 2+ farklı premium marka taşıyan leadlere Les Garage de Paris
   kampanyasını gönderir (inc/notify.php · vestra_campaign_preview). Dil ülkeye göre.
   Eski daily-customers.yml 3. adımının devamıdır; farkları:
     - tavan SEND_LIMIT (varsayılan 40, en çok 200): tek çalışma listeyi boşaltamaz
     - bounced / unsubscribed / junk adres ve zincir (sunucu blocklist) atlanır
     - EN YENİ eklenen önce gider: web aramasından gelen taze butikler bekletilmez
   Her gönderilen lead: status=contacted, last_contacted_at, last_campaign damgalanır → aynı
   adrese asla ikinci kez gitmez. Kişiye özel tek-tık unsubscribe linki gömülür. */
ini_set('display_errors', '1');
error_reporting(E_ALL);
@set_time_limit(0); @ini_set('implicit_flush', '1');
while (ob_get_level() > 0) { @ob_end_flush(); }
$home = getenv("HOME");
require       $home."/public_html/inc/products.php";
require_once  $home."/public_html/inc/leads.php";
require_once  $home."/public_html/inc/notify.php";

$LIMIT = (int)(getenv('SEND_LIMIT') ?: 40);
if ($LIMIT < 1) $LIMIT = 1;
if ($LIMIT > 200) $LIMIT = 200;
$FROM = 'Les Garage de Paris';
$LANG_MAP = [
  'netherlands'=>'nl','the netherlands'=>'nl','nederland'=>'nl','holland'=>'nl',
  'france'=>'fr','italy'=>'it','italia'=>'it','portugal'=>'pt',
  'czech republic'=>'cs','czechia'=>'cs','poland'=>'pl','polska'=>'pl',
  'spain'=>'es','españa'=>'es','espana'=>'es','greece'=>'el',
  'germany'=>'de','deutschland'=>'de','austria'=>'de','österreich'=>'de','osterreich'=>'de',
];
$REGION_LANG = ['belgium'=>['default'=>'nl','match'=>[
  'fr'=>['bruxelles','brussels','brussel','liège','liege','namur','charleroi','mons','tournai','arlon','wavre','verviers'],
]]];
$block = function_exists('vestra_discover_blocklist') ? vestra_discover_blocklist() : [];

$leads = vestra_leads();
$contacted = [];
foreach ($leads as $l) {
  if (trim((string)($l['last_contacted_at'] ?? '')) !== '') {
    $e = strtolower(trim((string)($l['email'] ?? ''))); if ($e !== '') $contacted[$e] = true;
  }
}

$picked = []; $seen = [];
for ($i = count($leads) - 1; $i >= 0; $i--) {                      // en yeni önce
  if (count($picked) >= $LIMIT) break;
  $l = $leads[$i];
  if (trim((string)($l['last_contacted_at'] ?? '')) !== '') continue;
  $status = (string)($l['status'] ?? 'new');
  if (!empty($l['unsubscribed']) || $status === 'unsubscribed' || $status === 'bounced') continue;
  $mail = strtolower(trim((string)($l['email'] ?? '')));
  if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) continue;
  if (isset($contacted[$mail]) || isset($seen[$mail])) continue;
  if (function_exists('vestra_email_is_junk') && vestra_email_is_junk($mail)) continue;

  /* KRİTER: en az İKİ ayrı premium marka. Tek marka = monobrand bayi, sıfır = kendi etiketi. */
  $pb = $l['premium_brands'] ?? [];
  if (!is_array($pb) || count(array_filter($pb)) < 2) continue;

  $name = (string)($l['company'] ?? '');
  if (function_exists('vestra_name_is_blocked') && vestra_name_is_blocked($name, (string)($l['brand'] ?? ''))) continue;
  $lname = strtolower($name); $isChain = false;
  foreach ($block as $b) if ($b !== '' && str_contains($lname, $b)) { $isChain = true; break; }
  if ($isChain) continue;

  $ctg = strtolower((string)($l['category'] ?? ''));
  foreach (['shoes','bag','leather','lingerie','underwear','jewelry','watches','tailor'] as $bad) if (str_contains($ctg, $bad)) continue 2;

  $seen[$mail] = true;
  $picked[$i] = $l;
}
echo "uygun secilen: ".count($picked)." / tavan: {$LIMIT}\n\n";
if (!$picked) { echo "Kriterleri gecen gonderilmemis lead yok -- gonderim yapilmadi.\n"; exit(0); }

$sent = 0; $fail = 0; $langCounts = [];
foreach ($picked as $i => $l) {
  $company = (string)($l['company'] ?? '');
  $mail    = strtolower(trim((string)($l['email'] ?? '')));
  $ckey    = strtolower(trim((string)($l['country'] ?? '')));
  if (isset($REGION_LANG[$ckey])) {
    $lang = $REGION_LANG[$ckey]['default'];
    $hay  = strtolower($company.' '.(string)($l['notes'] ?? '').' '.(string)($l['website'] ?? ''));
    foreach ($REGION_LANG[$ckey]['match'] as $lg => $nds)
      foreach ($nds as $nd) if (str_contains($hay, $nd)) { $lang = $lg; break 2; }
  } else {
    $lang = $LANG_MAP[$ckey] ?? 'en';
  }
  list($subject, $body, $opts) = vestra_campaign_preview($company, $lang);
  $token = (string)($l['unsub_token'] ?? '');
  if ($token !== '') $body = str_replace(
    'https://vestrasales.com/lead-unsubscribe',
    'https://vestrasales.com/lead-unsubscribe?token='.rawurlencode($token), $body);

  if (vestra_send_mail($mail, $subject, $body, '', $FROM, null, '', $opts)) {
    $leads[$i]['last_contacted_at'] = date('c');
    if (($leads[$i]['status'] ?? 'new') === 'new') $leads[$i]['status'] = 'contacted';
    $leads[$i]['last_campaign'] = 'les-garage/'.$lang;
    $note = trim((string)($leads[$i]['notes'] ?? ''));
    $leads[$i]['notes'] = trim($note.($note !== '' ? ' · ' : '').'campaign('.$lang.') '.date('Y-m-d'));
    $sent++; $langCounts[$lang] = ($langCounts[$lang] ?? 0) + 1;
    echo "  + {$company} <{$mail}> [{$lang}] ".implode(', ', array_slice(array_values(array_filter($l['premium_brands'])), 0, 3))."\n";
  } else { $fail++; echo "  x HATA: {$mail}\n"; }
  flush();
}
if ($sent > 0) vestra_save_leads($leads);
echo "\n----\ndil dagilimi: "; foreach ($langCounts as $k => $v) echo "{$k}={$v}  ";
echo "\nGONDERIM BITTI. gonderildi: {$sent}, hata: {$fail}, toplam lead: ".count($leads)."\n";
