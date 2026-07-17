<?php
/**
 * ChatHelp — apply-golden-save — SISTEMIN CALISAN HALINI MUHURLER.
 *  index.php + sw.js + manifest.json'in su anki halini "altin kopya" olarak
 *  kaydeder (index-golden.php, sw-golden.js, manifest-golden.json).
 *  Bir sey bozulursa apply-golden-restore.php ile TEK TIKLA bu ana donulur.
 *  Her calistirmada onceki altin kopyanin da tarihli yedegi alinir.
 * KULLANIM: pull2.php?key=...&files=apply-golden-save.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-golden-save BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$D=__DIR__;
$files=[['index.php','index-golden.php'],['sw.js','sw-golden.js'],['manifest.json','manifest-golden.json']];
foreach($files as $f){
  $s=(string)@file_get_contents("$D/".$f[0]);
  if($s===''){ echo "  ⚠ ".$f[0]." bos/yok — atlandi\n"; continue; }
  if(is_file("$D/".$f[1])) @copy("$D/".$f[1],"$D/".$f[1].'.prev-'.date('Ymd-His'));
  $w=@file_put_contents("$D/".$f[1],$s);
  $chk=(string)@file_get_contents("$D/".$f[1]);
  echo ($w!==false && strlen($chk)===strlen($s))
    ? "  ✓ ".$f[0]." -> ".$f[1]." (".strlen($s)." B) MUHURLENDI\n"
    : "  ✗ ".$f[1]." yazilamadi!\n";
}
echo "\n✓ ALTIN KOPYA kaydedildi (".date('d.m.Y H:i').").\n";
echo "  Geri donus: pull2.php?key=...&files=apply-golden-restore.php\n";
echo "  Saglik kontrolu: pull2.php?key=...&files=dump-health.php\n";
