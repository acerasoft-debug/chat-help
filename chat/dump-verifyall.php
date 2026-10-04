<?php
/**
 * ChatHelp — dump-verifyall — son gunlerdeki TUM guncellemeler canlida disk uzerinde
 *  GERCEKTEN var mi + hangi cache-control header'lari cikiyor (OKUR).
 * KULLANIM: pull2.php?key=...&files=dump-verifyall.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-verifyall (index.php ".strlen($src)." B) ==\n\n";

$marks=[
 'CH_PRINTDOC_WVFIRST'=>'App PDF kok neden',
 'CH_PDFDIRECT'       =>'Als PDF -> direkt PDF',
 'CH_SENDEN_BIG'      =>'Senden buyuk yesil',
 'CH_FORCEMODAL_BTN'  =>'Senden modali zorlar',
 'CH_IBANFIX'         =>'IBAN bosluk-adres KAPALI',
 'CH_GAPBOX'          =>'Bosluk kutulari',
 'CH_REVISE2'         =>'Uberarbeiten sablonsuz',
 'CH_FALLNEW'         =>'Neuer Fall premium',
 'CH_MICOFF_APP'      =>'App mikrofon off',
 'CH_SENDPERUSER'     =>'Gonderiler kullaniciya ozel',
 'CH_QUOTA40'         =>'Basic 40',
 'CH_KONTOCSS'        =>'Konto premium',
 'CH_NOCACHE2'        =>'no-cache header',
];
$eksik=0;
foreach($marks as $m=>$d){ $v=strpos($src,$m)!==false; if(!$v)$eksik++; echo ($v?'  ✓ ':'  ✗ EKSIK ').str_pad($m,20).' '.$d."\n"; }

echo "\n  postversand.php CH_LXV2: ".((($pv=@file_get_contents(__DIR__.'/postversand.php'))!==false && strpos($pv,'CH_LXV2')!==false)?'VAR':'YOK')."\n";
echo "  dl.php CH_DL2: ".((($dl=@file_get_contents(__DIR__.'/dl.php'))!==false && strpos($dl,'CH_DL2')!==false)?'VAR':'YOK')."\n";
$sw=(string)@file_get_contents(__DIR__.'/sw.js'); if(preg_match('/service worker (v\d+)/',$sw,$mm)) echo "  sw.js: ".$mm[1]."\n";

echo "\n  === index.php'nin ILK 400 baytinda cache-header PHP'si var mi? ===\n";
echo "  ".(strpos(substr($src,0,400),'no-store')!==false ? "VAR (header gonderiliyor)" : "YOK")."\n";

echo "\n  === HTTP yanit header'lari (bu betigin kendi cikttisi) ===\n";
foreach(headers_list() as $h){ echo "  $h\n"; }

echo "\n  ".($eksik===0 ? "TUM MARKERLAR DISKTE. Tarayici/App eskiyi goruyorsa sebep EDGE/CDN ONBELLEK." : "$eksik EKSIK — yeniden deploy gerek.")."\n";
echo "\n== BITTI ==\n";
