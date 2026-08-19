<?php
/**
 * ChatHelp — dump-senden — Cloudflare'in DISARIYA sundugu sayfada Senden butonu
 *  duzeltmelerinin TAM olup olmadigini birebir dogrular (OKUR). Cihaz mi Cloudflare
 *  mi kesin ayirir.
 * KULLANIM: pull2.php?key=...&files=dump-senden.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "== dump-senden ==\n\n";

function hit($url){
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>25,CURLOPT_FOLLOWLOCATION=>true,
    CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_USERAGENT=>'ChatHelpProbe/2',
    CURLOPT_HTTPHEADER=>['Cache-Control: no-cache','Pragma: no-cache']]);
  $b=curl_exec($ch); $err=curl_error($ch); curl_close($ch);
  return $b===false?['b'=>'','err'=>$err]:['b'=>$b,'err'=>''];
}

$disk=(string)@file_get_contents(__DIR__.'/index.php');
$live=hit('https://chat-help.com/chat/?_s='.time());
$L=$live['b'];
echo "disk: ".number_format(strlen($disk))." B · live: ".number_format(strlen($L))." B ".($live['err']?"(HATA: {$live['err']})":"")."\n\n";

$check=[
  'CH_SENDEN_BTN'      =>'Senden butonu var',
  'CH_SENDEN_BIG'      =>'buyuk/yesil CSS marker',
  'CH_FORCEMODAL_BTN'  =>'Senden->modal zorlama',
  'CH_PDFDIRECT2'      =>'PDF-direkt bayrak-duyarli',
  'CH_MICOFF_APP'      =>'App mikrofon off',
  'CH_NOCACHE3'        =>'cache-bypass cerezi',
  'CH_CFKILL'          =>'CF no-store',
];
echo "=== DISK vs LIVE (Cloudflare cikisi) ===\n";
foreach($check as $k=>$d){
  $dv=strpos($disk,$k)!==false; $lv=strpos($L,$k)!==false;
  echo "  ".($dv?'✓':'✗')." disk  ".($lv?'✓':'✗')." live   $k — $d\n";
}

echo "\n=== Canli sayfada Senden butonu CSS'i (gercek renk) ===\n";
$p=strpos($L,'.ch-senden-btn{');
if($p!==false){ echo "  ".substr($L,$p,240)."\n"; }
else echo "  .ch-senden-btn CSS'i canli sayfada YOK — cihaz cok eski kopya goruyor olabilir\n";

echo "\n=== Yesil gradyan (#37d17d) canli sayfada? ===\n";
echo "  ".(strpos($L,'#37d17d')!==false ? "✓ VAR (yesil buton kodu sunuluyor)" : "✗ YOK (eski altin buton sunuluyor)")."\n";

echo "\n=== HTML uzunluk farki ===\n";
$diff=strlen($disk)-strlen($L);
echo "  disk - live = $diff bayt ".($diff===0?"(AYNI — cikis guncel!)":($diff>0?"(live daha kisa — biraz eski)":"(?)"))."\n";

echo "\n== BITTI ==\n";
