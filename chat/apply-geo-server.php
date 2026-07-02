<?php
/**
 * ChatHelp — geo.php DESTEK LİSTESİ GENİŞLETME (sunucu tarafı)
 * --------------------------------------------------------------
 *  Sorun: geo.php $SUP listesinde PL/SE/PT yok (Polonya IP'si DE'ye düşüyordu)
 *  ve NL dili 'en' yazılmış ('nl' olmalı). Modülü olan tüm ülkeler eklenir.
 * KULLANIM: pull-updates.php?files=apply-geo-server.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-geo-server BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/geo.php";
if(!file_exists($file)) exit("geo.php yok\n");
$src=file_get_contents($file); $start=$src;
if(strpos($src,"CH_GEO_SUP")!==false) exit("Zaten ekli (CH_GEO_SUP).\n");

$old = "\$SUP = ['DE'=>'de','AT'=>'de','CH'=>'de','FR'=>'fr','BE'=>'fr','LU'=>'fr',\n        'GB'=>'en','US'=>'en','TR'=>'tr','RU'=>'ru','ES'=>'es','IT'=>'it','NL'=>'en'];";
$new = "\$SUP = ['DE'=>'de','AT'=>'de','CH'=>'de','FR'=>'fr','BE'=>'fr','LU'=>'fr',\n        'GB'=>'en','US'=>'en','TR'=>'tr','RU'=>'ru','ES'=>'es','IT'=>'it','NL'=>'nl',\n        'PL'=>'pl','SE'=>'sv','PT'=>'pt']; /*CH_GEO_SUP*/";

if(strpos($src,$old)===false){
  exit("✗ \$SUP anchor bulunamadi (geo.php farkli olabilir) — dokunulmadi. Dosyanin 20-30. satirlarini bana gonder.\n");
}
file_put_contents($file.".bak-geosup-".date("Ymd-His"),$src);
$src=str_replace($old,$new,$src);

/* php -l guvenligi */
$tmp=$file.".tmp-sup"; file_put_contents($tmp,$src);
$out=[]; $code=0; exec("php -l ".escapeshellarg($tmp)." 2>&1",$out,$code); @unlink($tmp);
if($code!==0){ echo "✗ LINT HATASI — geo.php DEGISTIRILMEDI:\n".implode("\n",$out)."\n"; exit; }

file_put_contents($file,$src);
echo "✓ geo.php guncellendi: PL/SE/PT eklendi, NL dili 'nl' duzeltildi.\n";
echo "SIL: rm apply-geo-server.php\n";
echo "Test: curl geo.php -> Polonya IP'sinde artik country:PL donmeli (DE degil).\n";
