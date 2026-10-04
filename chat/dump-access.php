<?php
/**
 * ChatHelp — dump-access — sunucu erisim kayitlarinda telefon/App isteklerini arar.
 *  Amac: App siteye HIC istek atiyor mu, atiyorsa HANGI adresi istiyor?
 *  Log dizinlerini tarar, son satirlarda Android/mobil UA iceren kayitlari gosterir.
 * KULLANIM: pull2.php?key=...&files=dump-access.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
echo "== dump-access ==\n";
echo "DOCUMENT_ROOT: ".($_SERVER['DOCUMENT_ROOT']??'-')."\n";
echo "HOME         : ".(getenv('HOME')?:'-')."\n";
echo "sunucu saati : ".date('Y-m-d H:i:s')."\n\n";

$root=dirname(__DIR__);
$cand=[];
foreach([$root,dirname($root),dirname(dirname($root)),getenv('HOME')] as $b){
  if(!$b) continue;
  foreach(['logs','log','.logs','access-logs','statistics/logs'] as $d) $cand[]=rtrim($b,'/').'/'.$d;
}
$cand=array_unique(array_merge($cand,['/var/log/apache2','/var/log/httpd','/var/log/nginx']));

echo "=== [1] Log dizini adaylari ===\n";
$found=[];
foreach($cand as $d){
  if(is_dir($d)){
    echo "  VAR  $d\n";
    $fs=@scandir($d); if($fs) foreach($fs as $x){ if($x==='.'||$x==='..')continue; $p=$d.'/'.$x;
      if(is_file($p)&&is_readable($p)){ printf("        %-34s %10d B  %s\n",$x,filesize($p),date('m-d H:i',filemtime($p))); $found[]=$p; } }
  }
}
if(!$found) echo "  (okunabilir log dosyasi BULUNAMADI)\n";

echo "\n=== [2] Son kayitlarda mobil/Android istekleri ===\n";
$shown=0;
foreach(array_slice($found,0,6) as $p){
  $sz=filesize($p); if($sz<1) continue;
  $fh=@fopen($p,'rb'); if(!$fh) continue;
  fseek($fh, max(0,$sz-400000));
  $buf=(string)fread($fh,400000); fclose($fh);
  $lines=array_slice(explode("\n",$buf),-4000);
  $hit=[];
  foreach($lines as $ln){
    if(preg_match('/Android|; wv\)|Mobile Safari|iPhone/i',$ln)) $hit[]=$ln;
  }
  if($hit){
    echo "-- ".basename($p)." (son ".count($hit)." mobil kayit) --\n";
    foreach(array_slice($hit,-25) as $ln){ echo "  ".substr(trim($ln),0,300)."\n"; $shown++; }
    echo "\n";
  }
}
if(!$shown) echo "  (mobil UA iceren kayit bulunamadi)\n";

echo "\n=== [3] Son 15 kayit (filtresiz, ilk log dosyasi) ===\n";
if($found){
  $p=$found[0]; $sz=filesize($p);
  $fh=@fopen($p,'rb'); if($fh){ fseek($fh,max(0,$sz-60000)); $buf=(string)fread($fh,60000); fclose($fh);
    foreach(array_slice(array_filter(explode("\n",$buf)),-15) as $ln) echo "  ".substr(trim($ln),0,300)."\n"; }
} else echo "  yok\n";

echo "\n== BITTI ==\n";
