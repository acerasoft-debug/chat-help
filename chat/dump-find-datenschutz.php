<?php
/** ChatHelp — dump-find-datenschutz: Datenschutz sayfasinin dosyasini + mevcut icerigini bulur.
 *  Kok dizin + /chat taranir. SADECE OKUR. SIL: rm dump-find-datenschutz.php */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);

$root = dirname(__DIR__);            // web kok (index2.php burada bulunmustu)
$dirs = [$root, __DIR__];
foreach (['legal','recht','pages','de'] as $d){ $p=$root.'/'.$d; if(is_dir($p)) $dirs[]=$p; }

echo "=== ARANAN DIZINLER ===\n";
foreach($dirs as $d) echo "  ".$d."\n";

echo "\n=== 'datenschutz' ADLI DOSYALAR ===\n";
$named=[];
foreach($dirs as $d){
  foreach((array)glob($d.'/*') as $f){
    if(is_file($f) && stripos(basename($f),'datenschutz')!==false){ echo "  ".$f."  (".filesize($f)." bayt)\n"; $named[]=$f; }
  }
}
if(!$named) echo "  (datenschutz adli ayri dosya yok — icerik baska dosyada olabilir)\n";

echo "\n=== ICINDE 'Datenschutzerkl' / buyuk Datenschutz basligi GECEN DOSYALAR ===\n";
$cand=[];
foreach($dirs as $d){
  foreach((array)glob($d.'/*.{php,html,htm}',GLOB_BRACE) as $f){
    if(!is_file($f)||strpos(basename($f),'.bak')!==false) continue;
    $s=@file_get_contents($f); if($s===false) continue;
    $n1=substr_count($s,'Datenschutz');
    if($n1>=3){ echo "  ".$f."  (Datenschutz x$n1, ".strlen($s)." bayt)\n"; $cand[$f]=$n1; }
  }
}

echo "\n=== EN GUCLU ADAYIN DATENSCHUTZ BOLUMU (ilk 3500 krk) ===\n";
if($cand){
  arsort($cand); $top=array_key_first($cand);
  echo "DOSYA: $top\n\n";
  $s=file_get_contents($top);
  $p=stripos($s,'Datenschutz');
  echo substr($s, max(0,$p-200), 3500)."\n";
} else {
  echo "(aday yok — bana chat-help.com/datenschutz sayfasinin URL'sinin ne acmasi gerektigini soyle)\n";
}
echo "\n=== BITTI. TAMAMINI yapistir. SIL: rm dump-find-datenschutz.php ===\n";
