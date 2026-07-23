<?php
/**
 * ChatHelp — dump-stats2 — 'vorabkosten/verifizierte' TUM dosya turlerinde,
 *  BUYUK/kucuk harf duyarsiz ara (CSS uppercase olabilir) (OKUR).
 * KULLANIM: pull2.php?key=...&files=dump-stats2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(0);
set_time_limit(150);
echo "== dump-stats2 ==\n\n";
$root=dirname(__DIR__);
$found=0; $scanned=0;
$skipExt='/\.(png|jpe?g|gif|webp|ico|zip|gz|woff2?|ttf|eot|mp[34]|pdf|svgz)$/i';
$it=new RecursiveIteratorIterator(
  new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),
    function($cur){
      $n=$cur->getFilename();
      if($cur->isDir()) return !in_array($n,['node_modules','.git','vendor']);
      return strpos($n,'.bak')===false;
    }
  ), RecursiveIteratorIterator::LEAVES_ONLY
);
$it->setMaxDepth(6);
foreach($it as $f){
  $n=$f->getFilename();
  if(preg_match($GLOBALS['skipExt']??'/\.(png|jpe?g|gif|webp|ico|zip|gz|woff2?|ttf|eot|mp[34]|pdf|svgz)$/i',$n)) continue;
  $sz=$f->getSize(); if($sz>9000000||$sz<10) continue;
  $s=@file_get_contents($f->getPathname()); if($s===false) continue;
  $scanned++;
  $p=stripos($s,'vorabkosten');
  $lbl='vorabkosten';
  if($p===false){ $p=stripos($s,'verifizierte'); $lbl='verifizierte'; }
  if($p!==false){
    $rel=substr($f->getPathname(),strlen($root)+1);
    echo "  ✓ $rel  ('$lbl' @$p, ".number_format($sz)." B)\n";
    echo "  ...".str_replace(["\r"],'',substr($s,max(0,$p-500),1100))."...\n\n";
    $found++;
    if($found>=8) break;
  }
}
echo "\n$scanned dosya tarandi, $found eslesme.\n";
if(!$found){
  echo "HIC YOK — istatistik blogu bu sunucuda degil olabilir:\n";
  echo "  - Google Play magaza gorseli / reklam sablonu olabilir\n";
  echo "  - baska bir domain/subdomain olabilir\n";
  echo "  -> Ekran goruntusunun tam URL'sini soyle (adres cubugu).\n";
}
echo "\n== BITTI ==\n";
