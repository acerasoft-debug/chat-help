<?php
/** ChatHelp — dump-design: premium renk uyumu + iki dil ayarı için gereken bölümler (SADECE OKUR)
 *  1) :root tasarım değişkenleri  2) dilekçe/kategori kutu stilleri + render fonksiyonları
 *  3) Konto/ayarlar paneli dil bölümü (setLang UI)  4) applyCC/applyUI tam gövde
 */
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

echo "###### 1) :root tasarım değişkenleri (tema paleti) ######\n";
WIN($idx,':root',0,2600,':root bloğu');

echo "\n\n###### 2) Dilekçe/kategori KUTULARI — stiller ######\n";
WIN($idx,'.wcat',-20,1800,'.wcat stilleri (hoş geldin kartları)');
foreach(['.doc-','.dc-','.cat-','.dli','.docitem','doc-btn','cat-item','list-item'] as $k){
  $ps=ALL($idx,$k); echo "  '$k' -> ".(count($ps)?count($ps).' kez @'.implode(',',array_slice($ps,0,4)):'yok')."\n";
}

echo "\n\n###### 3) Kutu RENDER fonksiyonları (hangi class/inline stil üretiliyor) ######\n";
FX($idx,'function showCat','showCat TAM',7000);
FX($idx,'function showAllCats','showAllCats TAM',5000);

echo "\n\n###### 4) Konto/Ayarlar paneli — dil bölümü (iki dil ayarı buraya eklenecek) ######\n";
$ps=ALL($idx,'setLang('); echo "  'setLang(' -> ".count($ps)." kez @".implode(',',array_slice($ps,0,8))."\n";
foreach(array_slice($ps,0,4) as $i=>$p){ echo "\n[setLang #".($i+1)." @$p]\n".substr($idx,max(0,$p-350),650)."\n"; }
WIN($idx,'Sprache',-250,900,"'Sprache' ilk geçiş (ayar etiketi olabilir)");
WIN($idx,'ksec',-100,700,'ksec (konto bölüm) stilleri');
FX($idx,'function setLang','setLang TAM',2500);

echo "\n\n###### 5) applyUI + applyCC TAM (bayrak/dil/hukuk güncelleme mantığı) ######\n";
FX($idx,'function applyUI','applyUI TAM',5000);
FX($idx,'function applyCC','applyCC TAM',4000);

echo "\n\n###### 6) Sohbet/giriş alanı stilleri (uyum kontrolü) ######\n";
WIN($idx,'.msg',-20,1200,'.msg (mesaj balonu) stilleri');
WIN($idx,'#inp',-150,500,'#inp (giriş) bağlamı');

echo "\n=== BİTTİ. SİL: rm dump-design.php ===\n";
