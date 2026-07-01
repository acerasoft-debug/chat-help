<?php
/** ChatHelp — dump-fall-ui: Fall eröffnen sayfası yapısı (foto menüsü, ikonlar, düzen) — SADECE OKUR */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
$idx=@file_get_contents(__DIR__.'/index.php');
if($idx===false) exit("index.php yok\n");

function FX($s,$name,$lbl,$cap=6000){
  echo "\n──────── $lbl ────────\n";
  $p=strpos($s,$name);
  if($p===false){ echo "(BULUNAMADI: $name)\n"; return; }
  $b=strpos($s,'{',$p); if($b===false){ echo substr($s,$p,300)."\n"; return; }
  $depth=0;$i=$b;$len=strlen($s);
  for(;$i<$len;$i++){ $c=$s[$i]; if($c==='{')$depth++; elseif($c==='}'){ $depth--; if($depth===0){ $i++; break; } } }
  $body=substr($s,$p,min($i-$p,$cap));
  echo $body.(($i-$p)>$cap?"\n…(kırpıldı)":"")."\n";
}
function WIN($s,$needle,$b,$l,$lbl){echo "\n──────── $lbl ────────\n";$p=strpos($s,$needle);if($p===false){echo "(yok: $needle)\n";return;}echo "[@$p]\n".substr($s,max(0,$p-$b),$l)."\n";}
function ALL($s,$needle){$out=[];$o=0;while(($p=strpos($s,$needle,$o))!==false){$out[]=$p;$o=$p+1;}return $out;}

echo "###### 1) Fall eröffnen ana fonksiyonu (openFall) — TAM ######\n";
FX($idx,'function openFall','openFall (tam gövde)',9000);

echo "\n\n###### 2) sb-photo-btn (menüdeki 'Fall eröffnen' satırı, kaldırılacak foto ikonu burada mı) ######\n";
WIN($idx,'sb-photo-btn',-300,900,'sb-photo-btn HTML bağlamı');
foreach(['sb-photo-t','sb-photo-s','openFall()'] as $k){
  $ps=ALL($idx,$k); echo "  '$k' -> ".count($ps)." kez\n";
}

echo "\n\n###### 3) Fall UI: fall-modal / fall-sheet (sayfa içeriği, ok/ikon/yazı düzeni) ######\n";
foreach(['fall-modal','fall-sheet','fall-header','fall-step','fall-arrow','fall-icon','fall-title','fall-progress'] as $k){
  $ps=ALL($idx,$k); echo "  '$k' -> ".(count($ps)?count($ps).' kez @'.implode(',',array_slice($ps,0,5)):'yok')."\n";
}
WIN($idx,'fall-modal',-100,1500,'fall-modal ilk geçiş bağlamı');

echo "\n\n###### 4) Fall CSS (stil bloğu, buton/ikon/yazı boyutları) ######\n";
WIN($idx,'#fall-modal-css',-20,2000,'fall-modal-css stil bloğu (varsa)');
WIN($idx,'.fall-',-20,100,'.fall- class ilk geçiş');

echo "\n=== BİTTİ. SİL: rm dump-fall-ui.php ===\n";
