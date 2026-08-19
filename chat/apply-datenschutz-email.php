<?php
/**
 * ChatHelp — DATENSCHUTZ E-POSTASI: business@ -> support@chat-help.de
 *  business/bussines yazim varyantlarini ve .de/.com uzantilarini kapsar.
 *  /chat/ + ust dizin + legal klasorlerindeki .php/.html/.htm taranir,
 *  degisen dosyalar raporlanir (.bak yedek + php lint korumasi).
 * KULLANIM: pull-updates.php?files=apply-datenschutz-email.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-datenschutz-email BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$NEW="support@chat-help.de";
$OLDS=["business@chat-help.de","bussines@chat-help.de","busines@chat-help.de",
       "business@chat-help.com","bussines@chat-help.com","busines@chat-help.com"];

$dirs=[__DIR__, dirname(__DIR__)];
foreach(['legal','recht','pages'] as $d){ $p=dirname(__DIR__).'/'.$d; if(is_dir($p)) $dirs[]=$p; }
$files=[];
foreach($dirs as $d){
  foreach((array)glob($d.'/*.{php,html,htm}',GLOB_BRACE) as $f){
    if(is_file($f) && strpos(basename($f),'.bak')===false && basename($f)!==basename(__FILE__)) $files[]=$f;
  }
}
$files=array_unique($files);
echo "Taranan dosya sayisi: ".count($files)."\n\n";

$hits=0;$changed=0;
foreach($files as $f){
  $s=@file_get_contents($f); if($s===false) continue;
  $tot=0; foreach($OLDS as $o) $tot+=substr_count($s,$o);
  if($tot<1) continue;
  $hits++;
  echo "BULUNDU: ".$f."  ($tot kez)\n";
  $new=str_replace($OLDS,$NEW,$s);
  if(substr($f,-4)==='.php'){
    $tmp=$f.".tmp-ds"; file_put_contents($tmp,$new);
    $out=[];$code=0; exec("php -l ".escapeshellarg($tmp)." 2>&1",$out,$code); @unlink($tmp);
    if($code!==0){ echo "  ✗ LINT HATASI — bu dosya DEGISTIRILMEDI\n"; continue; }
  }
  @copy($f,$f.".bak-dsmail-".date("Ymd-His"));
  file_put_contents($f,$new);
  echo "  ✓ degistirildi -> $NEW\n";
  $changed++;
}

if($hits===0){
  echo "HIC BULUNAMADI (business/bussines varyantlari yok).\n";
  echo "-> Datenschutz icerigindeki TUM e-postalar (hangisi hedefse soyle):\n";
  foreach($files as $f){
    $s=@file_get_contents($f); if($s===false) continue;
    if(stripos($s,'datenschutz')===false && stripos(basename($f),'datenschutz')===false) continue;
    if(preg_match_all('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',$s,$m)){
      echo "  ".$f."  ->  ".implode(", ",array_unique($m[0]))."\n";
    }
  }
}
echo "\nSONUC: $changed dosya guncellendi ($hits dosyada bulundu).\n";
echo "SIL: rm apply-datenschutz-email.php\n";
echo "Test: Datenschutz sayfasini gizli pencerede ac -> support@chat-help.de gorunmeli.\n";
