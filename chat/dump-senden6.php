<?php
/**
 * ChatHelp — dump-senden6 — sonuc kartindaki TUM Senden buton kaynaklarini + KI
 *  fax-bulma cagrisini bulur (OKUR).
 * KULLANIM: pull2.php?key=...&files=dump-senden6.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-senden6 (".strlen($src)." B) ==\n\n";

echo "=== [1] '.ch-senden-btn' + 'ch-send-row' + 'ch-sd2' + CH_SENDEN markerlar ===\n";
foreach(['ch-senden-btn'=>0,'ch-send-row'=>0,'ch-sd2'=>0,'CH_SENDEN1_OFF'=>0,'CH_SENDEN3'=>0,'CH_SENDEN5'=>0,'ch-doccard-send'=>0] as $m=>$z){
  echo "  '$m': ".substr_count($src,$m)."\n";
}

echo "\n=== [2] Senden buton olusturan yerler (label 'Senden' + createElement/innerHTML) ===\n";
$off=0;$n=0;
while(($p=strpos($src,"Senden",$off))!==false && $n<14){
  $seg=substr($src,max(0,$p-90),150);
  if(preg_match('/innerHTML|createElement|textContent|>.{0,20}Senden|class=|L=\{|lab:/',$seg)){
    echo "  [@$p] ...".str_replace("\n"," ",$seg)."...\n";
    $n++;
  }
  $off=$p+6;
}

echo "\n=== [3] eski enjektor inject() durumu (CH_SENDEN1_OFF return var mi) ===\n";
$p=strpos($src,'CH_SENDEN1_OFF');
if($p!==false){ echo "  ...".substr($src,max(0,$p-80),200)."...\n"; }
else echo "  CH_SENDEN1_OFF YOK — enjektor hala aktif olabilir\n";

echo "\n=== [4] KI fax-bulma (aichat + OFFIZIELLE Faxnummer) ===\n";
$off=0;$n=0;
while(($p=strpos($src,'Faxnummer',$off))!==false && $n<6){
  $seg=substr($src,max(0,$p-120),400);
  if(stripos($seg,'aichat')!==false || stripos($seg,'UNBEKANNT')!==false || stripos($seg,'deepseek')!==false){
    echo "  [@$p] ...".$seg."...\n\n"; $n++;
  }
  $off=$p+9;
}
echo "  'CH_FAX_AIFIND' geciyor mu: ".(strpos($src,'CH_FAX_AIFIND')!==false?'EVET':'HAYIR')."\n";

echo "\n== BITTI ==\n";
