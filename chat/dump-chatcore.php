<?php
/** ChatHelp — dump-chatcore: onKey/showAllCats TAM govdesi + ust bar (online yazisi + ikonlar) markup. SADECE OKUR. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=@file_get_contents(__DIR__.'/index.php');
if($idx===false) exit("okunamadi\n");
echo "dump-chatcore (idx=".strlen($idx)." bayt)\n";

function FX($s,$name,$lbl,$cap=4000){
  echo "\n──── $lbl ────\n";
  $p=strpos($s,$name);
  if($p===false){ echo "(BULUNAMADI: $name)\n"; return; }
  $b=strpos($s,'{',$p); if($b===false){ echo substr($s,$p,300)."\n"; return; }
  $d=0;$i=$b;$L=strlen($s);
  for(;$i<$L;$i++){ $c=$s[$i]; if($c==='{')$d++; elseif($c==='}'){ $d--; if(!$d){ $i++; break; } } }
  echo substr($s,$p,min($i-$p,$cap)).(($i-$p)>$cap?"\n…(kirpildi ".($i-$p)." bayt)":"")."\n";
}
function WIN($s,$needle,$lbl,$before=150,$len=900,$max=6){
  echo "\n──── $lbl ────\n";
  $o=0;$n=0;
  while(($p=stripos($s,$needle,$o))!==false && $n<$max){
    echo "@$p: ".trim(preg_replace('/\s+/',' ',substr($s,max(0,$p-$before),$len)))."\n---\n";
    $o=$p+strlen($needle); $n++;
  }
  if(!$n) echo "  (yok)\n";
}

echo "\n################ A) onKey TAM GOVDE ################\n";
FX($idx,'function onKey','onKey',4000);

echo "\n################ B) showAllCats / showQuestions ################\n";
FX($idx,'function showAllCats','showAllCats',2500);

echo "\n################ C) 'online' kelimesi gecen TUM yerler ################\n";
WIN($idx,'online','online kelimesi',150,500,8);
WIN($idx,'Online','Online (buyuk O)',150,500,8);

echo "\n################ D) top bar / header markup (id=tb veya benzeri) ################\n";
WIN($idx,'id="tb"','#tb header markup',100,900,2);
WIN($idx,'class="tb-','tb- ile baslayan siniflar',100,500,4);

echo "\n################ E) 'Fall eröffnen' / sb-new / sb-nav-item siralamasi ################\n";
WIN($idx,'Fall eröffnen','Fall eröffnen metni',200,500,4);
WIN($idx,'sb-new','sb-new markup',100,700,2);
WIN($idx,'sb-nav-item','sb-nav-item TUM gorunumler (siralama icin)',60,260,6);

echo "\n=== BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-chatcore.php ===\n";
