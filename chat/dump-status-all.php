<?php
/* dump-status-all — CANLI durum: index.php katmanlari + postversand/fax + config.
   Neyin canli oldugunu tek bakista gosterir. Salt-okunur.
   KULLANIM: pull2.php?key=...&files=dump-status-all.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "=== ChatHelp CANLI DURUM ===\n\n";
function m($ok){ return $ok?'[✓ CANLI]':'[✗ yok  ]'; }

$idx=@file_get_contents(__DIR__.'/index.php');
echo "index.php: ".($idx?strlen($idx):0)." B\n\n";
echo "-- Frontend katmanlar (index.php) --\n";
$marks=[
 'CH_ADRES_IMZA2'=>'Adres onayi (chPrintDoc)',
 'CH_ADRES_IMZA3'=>'Imza & aliciya gonder',
 'CH_ADRES_IMZA4'=>'Alici belgeye + Themen icerik',
 'CH_ADRES_IMZA5'=>'GONDERIM SECICI + FAKS + fiyatlar (ANA MODAL)',
 'CH_FORM_FLAGS'=>'Uc bayrak (schriftform/frist/Einschreiben)',
 'CH_EMSAL'=>'Emsal modulu',
 'CH_EMSAL_HUB'=>'Emsal hub opt-in',
 'CH_ABO_REFRESH'=>'Plan/abonelik tazele',
];
foreach($marks as $k=>$d){ echo "  ".m($idx&&strpos($idx,$k)!==false)."  $k — $d\n"; }

echo "\n-- Faks UI sinyalleri (index.php) --\n";
foreach([
 'ch-adres-imza5-js'=>'imza5 script blogu',
 "name=\"ai-vsa\""=>'gonderim turu secici (radio)',
 'flat_np'=>'paketli/paketsiz sabit fiyat',
 'ppg:0.50'=>'faks sayfa-basi +0,50',
 'proof:1'=>'ispatli teslim rozeti',
 'wethome'=>'islak imza -> kendi adrese posta',
 "act:'send_fax'"=>'faks gonderim aksiyonu',
] as $s=>$d){ echo "  ".m($idx&&strpos($idx,$s)!==false)."  $d\n"; }

echo "\n-- Backend (postversand.php) --\n";
$pv=@file_get_contents(__DIR__.'/postversand.php');
echo "  postversand.php: ".($pv?strlen($pv):0)." B\n";
foreach([
 'fax_send_clicksend'=>'ClickSend faks adaptoru',
 "action==='fax_ping'"=>'baglanti ping',
 "action==='send_letter'"=>'mektup/Einschreiben',
 "\$spec['registered']"=>'Einschreiben registered',
] as $s=>$d){ echo "  ".m($pv&&strpos($pv,$s)!==false)."  $d\n"; }

echo "\n-- Canli baglanti (postversand status) --\n";
$base='https://'.($_SERVER['HTTP_HOST']??'chat-help.com').rtrim(dirname($_SERVER['SCRIPT_NAME']??'/chat/x'),'/').'/postversand.php';
$c=curl_init($base.'?action=status&_cb='.str_replace('.','',microtime(true))); curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_HTTPHEADER=>['Cache-Control: no-cache']]); $r=curl_exec($c); curl_close($c);
$j=json_decode((string)$r,true);
echo "  LetterXpress configured: ".m(is_array($j)&&!empty($j['configured']))." (mode=".($j['mode']??'?').")\n";
echo "  Fax configured        : ".m(is_array($j)&&!empty($j['fax_configured']))." (provider=".($j['fax_provider']??'?').")\n";

echo "\n=== OZET ===\n";
$modalLive=$idx&&strpos($idx,'CH_ADRES_IMZA5')!==false;
$backLive=$pv&&strpos($pv,'fax_send_clicksend')!==false;
echo "  Backend faks (ClickSend)      : ".($backLive?'CANLI ✓':'DEGIL ✗')."\n";
echo "  Frontend faks modali (imza5)  : ".($modalLive?'CANLI ✓':'DEGIL ✗ -> apply-imza5.php deploy gerekli')."\n";
echo "  Uc bayrak (form-flags)        : ".($idx&&strpos($idx,'CH_FORM_FLAGS')!==false?'CANLI ✓':'DEGIL ✗ -> apply-form-flags.php')."\n";
echo "  Emsal hub                     : ".($idx&&strpos($idx,'CH_EMSAL_HUB')!==false?'CANLI ✓':'DEGIL ✗ -> apply-emsal-hub.php')."\n";
