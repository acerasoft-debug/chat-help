<?php
/** ChatHelp — dump-country-revert: ülke seçimi neden Almanya'ya geri dönüyor + üst bar ikon HTML (SADECE OKUR) */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
$idx=@file_get_contents(__DIR__.'/index.php');
if($idx===false) exit("index.php yok\n");

function FX($s,$name,$lbl,$cap=5000){
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

echo "###### 1) Sağ üstteki profil/avatar ikonu (bayrak gösteren daire) — nerede tanımlı ######\n";
foreach(['k-cc','profile-avatar','profil-avatar','avatar-flag','tb-avatar','k-avatar','sb-avatar'] as $k){
  $ps=ALL($idx,$k); echo "  '$k' -> ".(count($ps)?count($ps).' kez @'.implode(',',array_slice($ps,0,6)):'yok')."\n";
}
WIN($idx,'k-cc',-300,900,'k-cc TAM bağlam (nerede set ediliyor)');

echo "\n\n###### 2) CC değişkeni HER atanma yeri (kaç yerde CC= var, biri Almanya'ya sıfırlıyor olabilir) ######\n";
$ps=ALL($idx,'CC=');
echo "toplam ".count($ps)." atama\n";
foreach($ps as $p){ echo "\n[@$p]\n".substr($idx,max(0,$p-120),220)."\n"; }

echo "\n\n###### 3) Periyodik / interval bazlı CC okuma-yazma (localStorage'dan geri yükleme riski) ######\n";
foreach(['setInterval','loadProfile','restoreProfile','function applyCC','JSON.parse(localStorage.getItem(PK'] as $k){
  $ps=ALL($idx,$k); echo "  '$k' -> ".(count($ps)?count($ps).' kez':'yok')."\n";
}
FX($idx,'function applyCC','applyCC TAM');

echo "\n\n###### 4) genDoc'un gerçekten gönderdiği country değeri (backend'e giden) ######\n";
WIN($idx,"country:CC",-200,300,'country:CC gönderim bağlamı');
WIN($idx,"'country':CC",-200,300,'country alternatif gönderim');

echo "\n\n###### 5) Üst bar TÜM HTML (hamburger, ChatHelp logosu, bayrak, Acerasoft, belge ikonu, En ligne) ######\n";
WIN($idx,'sb-toggle',-200,2500,'Üst bar HTML bloğu (ikon estetiği için)');

echo "\n=== BİTTİ. SİL: rm dump-country-revert.php ===\n";
