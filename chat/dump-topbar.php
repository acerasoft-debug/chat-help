<?php
/** ChatHelp — dump-topbar: üst bar + sağ üstteki bayraklı avatar ikonunu bulur (geniş arama) — SADECE OKUR */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
$idx=@file_get_contents(__DIR__.'/index.php');
if($idx===false) exit("index.php yok\n");

function WIN($s,$needle,$b,$l,$lbl){echo "\n──────── $lbl ────────\n";$p=strpos($s,$needle);if($p===false){echo "(yok: $needle)\n";return;}echo "[@$p]\n".substr($s,max(0,$p-$b),$l)."\n";}
function ALL($s,$needle){$out=[];$o=0;while(($p=strpos($s,$needle,$o))!==false){$out[]=$p;$o=$p+1;}return $out;}

echo "###### 1) Genel üst bar / header isim adayları ######\n";
foreach(['topbar','top-bar','app-bar','appbar','header','hamburger','sb-tgl','menu-btn','nav-flag','hdr-'] as $k){
  $ps=ALL($idx,$k); echo "  '$k' -> ".(count($ps)?count($ps).' kez @'.implode(',',array_slice($ps,0,5)):'yok')."\n";
}

echo "\n\n###### 2) 'Acerasoft' (hesap adı) HTML bağlamı — yanındaki bayrak ikonu burada olabilir ######\n";
WIN($idx,'Acerasoft',-1200,1600,'Acerasoft civarı TAM HTML');

echo "\n\n###### 3) 'En ligne' / 'Online' durum göstergesi civarı ######\n";
WIN($idx,'En ligne',-800,1000,'En ligne civarı (hardcoded olabilir mi kontrol)');
foreach(ALL($idx,'Online') as $p){ echo "\n[Online @$p]\n".substr($idx,max(0,$p-150),250)."\n"; }

echo "\n\n###### 4) CC_DATA[cc].f (bayrak) kullanılan TÜM yerler (applyCC dışında kaçak var mı) ######\n";
$ps=ALL($idx,'.f+');
echo "'.f+' toplam ".count($ps)." kez\n";
foreach($ps as $p){ echo "\n[@$p]\n".substr($idx,max(0,$p-150),260)."\n"; }
$ps2=ALL($idx,"c.f");
echo "\n'c.f' toplam ".count($ps2)." kez, konumlar: ".implode(',',array_slice($ps2,0,10))."\n";

echo "\n\n###### 5) Hamburger menü ikonu (☰) civarı — üst barın başlangıcı olabilir ######\n";
$p=strpos($idx,'☰');
if($p!==false){ echo "[@$p]\n".substr($idx,max(0,$p-200),1800)."\n"; } else echo "(☰ bulunamadı)\n";

echo "\n=== BİTTİ. SİL: rm dump-topbar.php ===\n";
