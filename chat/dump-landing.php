<?php
/**
 * ChatHelp — dump-landing — '500+ Verifizierte Käufer / Vorabkosten' istatistik
 *  blogu /chat/index.php'de YOK -> kok dizinde (landing/WP) ara (OKUR).
 * KULLANIM: pull2.php?key=...&files=dump-landing.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
set_time_limit(120);
echo "== dump-landing ==\n\n";
$root=dirname(__DIR__);
echo "kok: $root\n\n";

echo "=== kok dizin listesi ===\n";
foreach((array)@scandir($root) as $e){
  if($e==='.'||$e==='..') continue;
  $p="$root/$e";
  echo "  ".(is_dir($p)?'[D] ':'    ').$e.(is_file($p)?' ('.number_format(filesize($p)).' B)':'')."\n";
}

echo "\n=== 'Vorabkosten' / 'Verifizierte' taramasi (max derinlik 4) ===\n";
$found=0; $scanned=0;
$it=new RecursiveIteratorIterator(
  new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),
    function($cur,$key,$ite){
      $n=$cur->getFilename();
      if($cur->isDir()) return !in_array($n,['node_modules','.git','vendor','cache','uploads']);
      return (bool)preg_match('/\.(php|html?|js)$/i',$n) && strpos($n,'.bak')===false;
    }
  ), RecursiveIteratorIterator::LEAVES_ONLY
);
$it->setMaxDepth(4);
foreach($it as $f){
  $sz=$f->getSize(); if($sz>9000000) continue;
  $s=@file_get_contents($f->getPathname()); if($s===false) continue;
  $scanned++;
  foreach(['Vorabkosten','Verifizierte K','Verifizierte&nbsp;K','K&auml;ufer'] as $pat){
    $p=strpos($s,$pat);
    if($p!==false){
      $rel=substr($f->getPathname(),strlen($root)+1);
      echo "\n  ✓ $rel  ('$pat' @$p, dosya ".number_format($sz)." B)\n";
      echo "  ...".str_replace(["\r"],'',substr($s,max(0,$p-450),1000))."...\n";
      $found++;
      break;
    }
  }
  if($found>=6) break;
}
echo "\n$scanned dosya tarandi, $found eslesme.\n";
if(!$found) echo "Bulunamadi — istatistik baska yerde (belki veritabani/harici sayfa).\n";
echo "\n== BITTI ==\n";
