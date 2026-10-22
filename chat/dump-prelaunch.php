<?php
/**
 * ChatHelp — dump-prelaunch — LANSMAN ONCESI durum kontrolu (OKUR, hicbir sey degistirmez):
 *  [1] Gonderim yapilandirmasi: LetterXpress/Fax saglayici anahtarlar TANIMLI MI (deger ASLA basilmaz)
 *  [2] postversand.php canli surumu + desteklenen action'lar
 *  [3] api.php: 'aichat' action var mi (KI-fax-bulucu buna bagli) + action listesi
 *  [4] index.php: recVal() tanimi + 'ai-recipient' kullanim yerleri (Empfänger bug'i icin)
 *  [5] dl.php mevcut mu + boyut
 *  [6] Stabilite marker yoklamasi (hangi duzeltmeler canlida)
 * KULLANIM: pull2.php?key=...&files=dump-prelaunch.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "== dump-prelaunch ==\n\n";

echo "=== [1] Gonderim yapilandirmasi (sadece TANIMLI/EKSIK — degerler asla basilmaz) ===\n";
@include_once __DIR__.'/config.php';
$defs=['LETTERXPRESS_USER','LETTERXPRESS_APIKEY','LETTERXPRESS_MODE','FAX_PROVIDER','CLICKSEND_USER','CLICKSEND_KEY','PHAXIO_KEY','PHAXIO_SECRET'];
foreach($defs as $d){
  if(defined($d)){
    $v=constant($d);
    if($d==='LETTERXPRESS_MODE'||$d==='FAX_PROVIDER') echo "  $d = ".$v."\n"; /* mod/saglayici adi gizli degil */
    else echo "  $d = TANIMLI (".(strlen((string)$v)>0?'dolu':'BOS').")\n";
  } else echo "  $d = EKSIK\n";
}

echo "\n=== [2] postversand.php canli ===\n";
$pv=@file_get_contents(__DIR__.'/postversand.php');
if($pv===false) echo "  YOK!\n";
else{
  echo "  boyut: ".strlen($pv)." B\n";
  if(preg_match("/define\('PV_VERSION','([^']+)'\)/",$pv,$m)) echo "  PV_VERSION: ".$m[1]."\n";
  preg_match_all("/action==='([a-z_]+)'/",$pv,$mm);
  echo "  action'lar: ".implode(', ',array_unique($mm[1]))."\n";
}

echo "\n=== [3] api.php action'lari ('aichat' KI-fax-bulucu icin sart) ===\n";
$ap=@file_get_contents(__DIR__.'/api.php');
if($ap===false) echo "  api.php okunamadi\n";
else{
  echo "  boyut: ".strlen($ap)." B\n";
  echo "  'aichat' geciyor mu: ".(strpos($ap,'aichat')!==false?"EVET":"HAYIR — KI-fax-bulucu CALISMAZ!")."\n";
  preg_match_all("/\\\$action\s*===?\s*'([a-z_0-9]+)'/",$ap,$m2);
  preg_match_all("/case\s+'([a-z_0-9]+)'/",$ap,$m3);
  $acts=array_unique(array_merge($m2[1]??[],$m3[1]??[]));
  echo "  action listesi: ".implode(', ',$acts)."\n";
}

echo "\n=== [4] index.php: recVal() + ai-recipient ===\n";
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "  index.php okunamadi\n"; }
else{
  echo "  boyut: ".strlen($src)." B\n";
  $p=strpos($src,'function recVal');
  if($p!==false){ echo "  [recVal @$p]\n  ".substr($src,$p,420)."\n\n"; }
  else echo "  recVal() bulunamadi\n";
  $off=0;$n=0;
  while(($p=strpos($src,'ai-recipient',$off))!==false && $n<6){
    echo "  [ai-recipient #$n @$p] ...".substr($src,max(0,$p-110),240)."...\n\n"; $off=$p+12;$n++;
  }
  echo "  toplam 'ai-recipient': ".substr_count($src,'ai-recipient')."\n";

  echo "\n=== [5] dl.php ===\n";
  $dl=@file_get_contents(__DIR__.'/dl.php');
  echo $dl===false ? "  YOK!\n" : "  VAR (".strlen($dl)." B)\n";

  echo "\n=== [6] Marker yoklamasi ===\n";
  foreach(['CH_PRINTDOC_WVFIRST','CH_PROFBOOTFIX','CH_FAXPRIME_FIX','CH_FAX_PRIMARY','CH_FAX_AIFIND','CH_FAX_UX','CH_SENDGATE','CH_SENDOK','CH_NOCACHE2','CH_CURPLANFIX','CH_FORCEWIPE','CH_NOCROSS','CH_PDFFORM'] as $mk){
    echo "  ".str_pad($mk,22).": ".(strpos($src,$mk)!==false?'VAR':'YOK')."\n";
  }
  echo "  chPrintDoc tanim sayisi: ".substr_count($src,'window.chPrintDoc=function')." (2 normal: ilki olu, ikincisi aktif)\n";
}
echo "\n== BITTI ==\n";
