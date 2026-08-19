<?php
/**
 * ChatHelp — apply-lxlive — LetterXpress'i TEST modundan LIVE moda alir.
 *  DIKKAT: Bu calistirildiktan sonra Brief/Einschreiben gonderimleri GERCEK olur
 *  (gercek baski + posta + LetterXpress bakiyenizden gercek ucret). Bakiye
 *  YETERLIYSE calistirin (su an -1,92 EUR — ONCE YUKLEME YAPIN: letterxpress.de).
 *  Sadece config.php'deki LETTERXPRESS_MODE degerini degistirir, baska hicbir
 *  seye dokunmaz. Deger asla ekrana basilmaz.
 * KULLANIM: pull2.php?key=...&files=apply-lxlive.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-lxlive BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/config.php';
$src=@file_get_contents($file);
if($src===false) exit("config.php okunamadi\n");

$cnt=0;
$new=preg_replace('/define\(\s*([\'"])LETTERXPRESS_MODE\1\s*,\s*([\'"])test\2\s*\)/',"define('LETTERXPRESS_MODE','live')",$src,1,$cnt);
if($cnt!==1){
  if(preg_match('/define\(\s*([\'"])LETTERXPRESS_MODE\1\s*,\s*([\'"])live\2\s*\)/',$src)) exit("  = Zaten LIVE modda. Degisiklik yok.\n");
  exit("  ✗ LETTERXPRESS_MODE 'test' taniminda bulunamadi ($cnt) — HICBIR sey degistirilmedi.\n");
}

$tmp=tempnam(sys_get_temp_dir(),'lx').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-lxlive-'.date('Ymd-His'),$src);
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(!preg_match('/define\(\s*[\'"]LETTERXPRESS_MODE[\'"]\s*,\s*[\'"]live[\'"]\s*\)/',$chk)) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ LETTERXPRESS_MODE artik LIVE — Brief/Einschreiben gonderimleri GERCEK.\n";
echo "  Kontrol: https://chat-help.com/chat/postversand.php?action=status\n";
echo "  -> \"mode\":\"live\" gorunmeli. Bakiye de POZITIF olmali (su an eksideyse yukleyin).\n";
