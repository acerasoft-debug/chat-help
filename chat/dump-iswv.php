<?php
/**
 * ChatHelp — dump-iswv — isWV global mi, degil mi? (kok neden kaniti)
 *  Beacon 'isWV=fn-yok' dedi. Hipotez: isWV bir IIFE icinde tanimli, global
 *  degil; bu yuzden makePDF/chPrintDoc icindeki (typeof isWV==='function')
 *  kontrolleri hep false donuyor ve App dallari HIC calismiyor.
 * KULLANIM: pull2.php?key=...&files=dump-iswv.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
$s=(string)@file_get_contents(__DIR__.'/index.php');
if($s==='') exit("index.php okunamadi\n");
echo "== dump-iswv (".strlen($s)." B) ==\n\n";

echo "=== [1] Sayimlar ===\n";
foreach(['window.isWV','isWV=function','function isWV',"typeof isWV==='function'",'typeof isWV=="function"','window.chIsApp'] as $k){
  echo "  ".str_pad($k,30)." : ".substr_count($s,$k)."\n";
}

echo "\n=== [2] 'function isWV' etrafi (IIFE mi, global mi) ===\n";
$p=strpos($s,'function isWV');
if($p!==false){ echo substr($s,max(0,$p-260),1500)."\n"; } else echo "  YOK\n";

echo "\n=== [3] isWV IIFE'sinin SONU (export var mi?) ===\n";
if($p!==false){
  $seg=substr($s,$p,9000);
  $q=strpos($seg,'})();');
  if($q===false) $q=strpos($seg,'})()');
  echo $q!==false ? "...".substr($seg,max(0,$q-500),700)."\n" : "  (IIFE sonu 9000 karakterde bulunamadi)\n";
}

echo "\n=== [4] TUM 'typeof isWV' kullanimlari (nerede?) ===\n";
$off=0;$n=0;
while(($q=strpos($s,'typeof isWV',$off))!==false && $n<20){
  echo "  [@$q] ...".preg_replace('/\s+/',' ',substr($s,max(0,$q-110),240))."...\n\n"; $off=$q+11;$n++;
}
if($n===0) echo "  YOK\n";

echo "== BITTI ==\n";
