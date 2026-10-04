<?php
/** ChatHelp — dump-gpfn: orijinal gP() fonksiyonunun TAM govdesi. SADECE OKUR. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=@file_get_contents(__DIR__.'/index.php');
if($idx===false) exit("okunamadi\n");
echo "dump-gpfn (idx=".strlen($idx)." bayt)\n";

function FX($s,$name,$lbl,$cap=3000){
  echo "\n──── $lbl ────\n";
  $p=strpos($s,$name);
  if($p===false){ echo "(BULUNAMADI: $name)\n"; return; }
  $b=strpos($s,'{',$p); if($b===false){ echo substr($s,$p,300)."\n"; return; }
  $d=0;$i=$b;$L=strlen($s);
  for(;$i<$L;$i++){ $c=$s[$i]; if($c==='{')$d++; elseif($c==='}'){ $d--; if(!$d){ $i++; break; } } }
  echo substr($s,$p,min($i-$p,$cap)).(($i-$p)>$cap?"\n…(kirpildi)":"")."\n";
}
/* ilk gP tanimi (orijinal, sarmalayicilardan ONCE gelen) */
FX($idx,'function gP(', 'orijinal gP tanimi (ilk gorunum)', 2500);
echo "\n=== BITTI ===\n";
