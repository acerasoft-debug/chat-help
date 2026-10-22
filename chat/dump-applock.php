<?php
/**
 * ChatHelp — dump-applock — App-yazdirma ozelliginin SAGLIK KONTROLU.
 *  Kilitlenmis hal ile su anki hali karsilastirir; bir sey bozulmussa soyler.
 *  Hicbir sey degistirmez (salt-okunur).
 * KULLANIM: /chat/pull2.php?key=...&files=dump-applock.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
$dir=__DIR__; $file=$dir.'/index.php';
echo "== dump-applock ==\n";
$src=(string)@file_get_contents($file);
if($src===''){ echo "index.php okunamadi\n"; exit; }
echo "index.php : ".strlen($src)." B   ".date('Y-m-d H:i',filemtime($file))."\n";

$lockf=$dir.'/ch-applock.json';
$lock=is_file($lockf)?json_decode((string)@file_get_contents($lockf),true):null;
if(!$lock){ echo "\n! ch-applock.json YOK — henuz kilitlenmemis (apply-applock.php calistir)\n"; }
else{
  echo "kilit     : ".$lock['locked_at']."   sha1 ".substr($lock['sha1'],0,12)."…  (".$lock['size']." B)\n";
  echo "su anki   : sha1 ".substr(sha1($src),0,12)."…\n";
  echo (sha1($src)===$lock['sha1'] ? "durum     : BIREBIR AYNI (hic degismemis)\n"
                                   : "durum     : DEGISMIS (baska yamalar uygulanmis olabilir — normal)\n");
}

echo "\n=== Kritik isaretler ===\n";
$need=['CH_ISWV_GLOBAL'=>1,'CH_APPVIEW3'=>1,'CH_APPVIEW2C'=>4,'CH_MAILBTN'=>1,'CH_FILLGAPS'=>1,
       'CH_NATIVEPDF'=>1,'CH_PDFGOLD'=>1];
$bad=[];
foreach($need as $m=>$min){
  $c=substr_count($src,$m);
  $ok=($c>=$min);
  printf("  %-16s %2d / en az %d   %s\n",$m,$c,$min,$ok?'OK':'✗ EKSIK');
  if(!$ok)$bad[]=$m;
}

echo "\n=== api.php (belge uretimi) ===\n";
$apiS=(string)@file_get_contents($dir.'/api.php');
if($apiS===''){ echo "  ✗ api.php okunamadi\n"; $bad[]='api.php'; }
else{
  foreach(['CH_BLANKFILL'=>1,'CH_DOC_POLISH'=>1,'function doGenerate'=>1] as $m=>$min){
    $c=substr_count($apiS,$m); $ok=($c>=$min);
    printf("  %-22s %2d / en az %d   %s\n",$m,$c,$min,$ok?'OK':'✗ EKSIK');
    if(!$ok)$bad[]=$m;
  }
}

echo "\n=== Dosyalar ===\n";
foreach(['pv.php','pvsave.php','pvmail.php','pdflib.php','index.php.GOOD-appprint'] as $f){
  $p=$dir.'/'.$f; $ok=is_file($p);
  printf("  %-28s %s\n",$f,$ok?('OK '.filesize($p).' B'):'✗ YOK');
  if(!$ok)$bad[]=$f;
}
$pvd=$dir.'/pv'; echo "  ".str_pad('pv/ (belge klasoru)',28)." ".(is_dir($pvd)?('OK, '.count((array)@glob($pvd.'/*.txt')).' kayit'):'yok (ilk kullanimda olusur)')."\n";

echo "\n";
if($bad){
  echo "✗ SORUN VAR — eksik: ".implode(', ',array_unique($bad))."\n";
  echo "  Geri yukleme: pull2.php?...&files=apply-applock-restore.php\n";
}else{
  echo "✓ HER SEY YERINDE — App yazdirma ozelligi saglam.\n";
}
