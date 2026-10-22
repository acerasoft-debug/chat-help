<?php
/**
 * ChatHelp — dump-stats — hero istatistik blogunun ('Verifizierte Käufer',
 *  'Länder', 'Vorabkosten') GERCEK yazimini gosterir (OKUR).
 * KULLANIM: pull2.php?key=...&files=dump-stats.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-stats (".strlen($src)." B) ==\n\n";
foreach(['Verifizierte','Käufer','Kaeufer','Vorabkosten','Länder','Laender','Zufriedenheit'] as $pat){
  $off=0;$n=0;
  echo "=== '$pat' ===\n";
  while(($p=strpos($src,$pat,$off))!==false && $n<6){
    echo "  @$p:\n  ...".str_replace(["\r"],'',substr($src,max(0,$p-350),800))."...\n\n";
    $off=$p+strlen($pat); $n++;
  }
  if($n===0) echo "  (yok)\n\n";
}
echo "== BITTI ==\n";
