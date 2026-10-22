<?php
/**
 * ChatHelp — IMPRESSUM E-POSTA DEGISIKLIGI: info@chat-help.de -> support@chat-help.de
 *  Impressum'un yeri bilinmedigi icin /chat/ ve UST dizindeki .php/.html/.htm
 *  dosyalarini tarar, e-postayi degistirir, nerede degistigini raporlar.
 *  Her degisen dosyanin .bak yedegi alinir; .php dosyalari lint'ten gecirilir.
 * KULLANIM: pull-updates.php?files=apply-impressum-email.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-impressum-email BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$OLD="info@chat-help.de"; $NEW="support@chat-help.de";
$dirs=[__DIR__, dirname(__DIR__)];
/* varsa yaygin hukuk klasorleri */
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
  $c=substr_count($s,$OLD);
  if($c<1) continue;
  $hits++;
  echo "BULUNDU: ".$f."  ($c kez)\n";
  $new=str_replace($OLD,$NEW,$s);
  if(substr($f,-4)==='.php'){
    $tmp=$f.".tmp-imp"; file_put_contents($tmp,$new);
    $out=[];$code=0; exec("php -l ".escapeshellarg($tmp)." 2>&1",$out,$code); @unlink($tmp);
    if($code!==0){ echo "  ✗ LINT HATASI — bu dosya DEGISTIRILMEDI\n"; continue; }
  }
  @copy($f,$f.".bak-impmail-".date("Ymd-His"));
  file_put_contents($f,$new);
  echo "  ✓ degistirildi -> $NEW\n";
  $changed++;
}

if($hits===0){
  echo "HIC BULUNAMADI: '$OLD' bu dizinlerde yok.\n";
  echo "-> Impressum baska yerde olabilir (ör. ayri alt klasor). 'info@' gecen yerler:\n";
  foreach($files as $f){
    $s=@file_get_contents($f); if($s===false) continue;
    if(stripos($s,'info@')!==false){
      $p=stripos($s,'info@');
      echo "  ".$f."  ...".substr($s,max(0,$p-30),80)."...\n";
    }
  }
}
echo "\nSONUC: $changed dosya guncellendi ($hits dosyada bulundu).\n";
echo "SIL: rm apply-impressum-email.php\n";
echo "Test: Impressum sayfasini gizli pencerede ac -> support@chat-help.de gorunmeli.\n";
