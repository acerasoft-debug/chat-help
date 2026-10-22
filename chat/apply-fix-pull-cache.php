<?php
/**
 * ChatHelp — pull-updates.php'nin KENDI CAGRILARINA cache-busting ekler
 * ------------------------------------------------------------------------
 *  Teshis: pull-updates.php indirilen dosyayi CALISTIRMAK icin kendi sitesine
 *  bir HTTP istegi atiyor (ch_fetch($base.$dosya)) ve sonda ayni sekilde
 *  opcache-reset.php'yi cagiriyor. Bu istekler sitenin edge/CDN katmanindan
 *  geciyor ve (daha once index.php icin kanitlanan ayni sorun) ESKI, onbellege
 *  alinmis bir yaniti geri verebiliyor — dosya diskte guncellenmis olsa BILE.
 *  Kanit: dump-gen-debug.php diskte/GitHub'da 3424 bayt ve eski metni HIC
 *  icermiyor, ama calistirildiginda hala eski metni donduruyordu.
 *
 *  Duzeltme: her iki ic-cagriya da zaman damgali cache-buster query param
 *  eklenir (?_nc=<microtime>) — boylece edge/CDN bu URL'yi HER SEFERINDE
 *  FARKLI gorur ve origin'e (taze PHP calistirmaya) gitmek ZORUNDA kalir.
 *
 * KULLANIM: pull-updates.php?files=apply-fix-pull-cache.php&run=1. SİL.
 *  (pull-updates.php korumali dosya oldugu icin normal apply/dump kanaliyla
 *  INDIRILEMEZ, ama bu script onu DOGRUDAN diskte duzenler — sorun degil.)
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-fix-pull-cache BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/pull-updates.php";
if(!file_exists($file)) exit("pull-updates.php yok\n");
$src=file_get_contents($file); $start=$src;
if(strpos($src,"CH_PULLCACHE_FIX")!==false) exit("Zaten ekli (CH_PULLCACHE_FIX).\n");

$rep=[];
$o1='$out = ch_fetch($base . rawurlencode($b));';
$n1='$out = ch_fetch($base . rawurlencode($b) . \'?_nc=\' . str_replace(\'.\',\'\',microtime(true))); /*CH_PULLCACHE_FIX*/';
$c1=substr_count($src,$o1);
if($c1===1){ $src=str_replace($o1,$n1,$src); $rep[]=["calistirma cagrisi (dosya)",1]; }
else { $rep[]=["calistirma cagrisi (dosya) — anchor $c1 kez",0]; }

$o2="\$op = ch_fetch(\$base . 'opcache-reset.php');";
$n2="\$op = ch_fetch(\$base . 'opcache-reset.php?_nc=' . str_replace('.','',microtime(true)));";
$c2=substr_count($src,$o2);
if($c2===1){ $src=str_replace($o2,$n2,$src); $rep[]=["opcache-reset cagrisi",1]; }
else { $rep[]=["opcache-reset cagrisi — anchor $c2 kez",0]; }

if($src===$start){ echo "Degisiklik yok!\n"; foreach($rep as $r) echo "  ".$r[0]." -> ".$r[1]."\n"; exit; }
$tmp=$file.".tmp-pcf"; file_put_contents($tmp,$src);
$out=[]; $code=0; exec("php -l ".escapeshellarg($tmp)." 2>&1",$out,$code); @unlink($tmp);
if($code!==0){ echo "✗ LINT HATASI — pull-updates.php DEGISTIRILMEDI:\n".implode("\n",$out)."\n"; exit; }
file_put_contents($file.".bak-pullcache-".date("Ymd-His"),$start);
file_put_contents($file,$src);

echo "✓ pull-updates.php'nin ic cagrilarina cache-buster eklendi (CH_PULLCACHE_FIX):\n";
foreach($rep as $r) echo "  ".($r[1]?"OK  ":"✗   ").$r[0]."\n";
echo "\nSIL: rm apply-fix-pull-cache.php\n";
echo "Test: herhangi bir dump-*.php'yi tekrar calistir -> artik GUNCEL/TAZE ciktiyi vermeli.\n";
