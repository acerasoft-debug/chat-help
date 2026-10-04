<?php
/**
 * ChatHelp — apply-golden-restore — ALTIN KOPYAYA GERI DONER.
 *  index.php + sw.js + manifest.json, apply-golden-save.php ile muhurlenen
 *  son calisan hale dondurulur. Donmeden once mevcut durumun tarihli
 *  yedegi alinir (geri-geri donus de mumkun).
 * KULLANIM: pull2.php?key=...&files=apply-golden-restore.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-golden-restore BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$D=__DIR__;
$files=[['index-golden.php','index.php'],['sw-golden.js','sw.js'],['manifest-golden.json','manifest.json']];
$any=false;
foreach($files as $f){
  $g=(string)@file_get_contents("$D/".$f[0]);
  if($g===''){ echo "  ⚠ ".$f[0]." yok — once apply-golden-save.php calistirilmali\n"; continue; }
  @copy("$D/".$f[1],"$D/".$f[1].'.bak-beforerestore-'.date('Ymd-His'));
  if($f[1]==='index.php'){
    $tmp=tempnam(sys_get_temp_dir(),'gr').'.php';
    file_put_contents($tmp,$g);
    $lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
    if($rc!==0){ echo "  ✗ altin kopya lint HATALI — geri donus IPTAL\n"; continue; }
  }
  $w=@file_put_contents("$D/".$f[1],$g);
  $chk=(string)@file_get_contents("$D/".$f[1]);
  if($w!==false && strlen($chk)===strlen($g)){ echo "  ✓ ".$f[1]." altin kopyaya dondu (".strlen($g)." B)\n"; $any=true; }
  else echo "  ✗ ".$f[1]." yazilamadi!\n";
}
echo $any ? "\n✓ SISTEM ALTIN KOPYAYA DONDU. Sayfayi 1-2 kez yenileyin.\n" : "\n⚠ Hicbir dosya donmedi.\n";
