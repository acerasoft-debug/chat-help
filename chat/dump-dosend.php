<?php
/** ChatHelp — dump-dosend: doSend() TAM govdesi (onKey'in cagirdigi asil gonderme fonksiyonu). SADECE OKUR. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=@file_get_contents(__DIR__.'/index.php');
if($idx===false) exit("okunamadi\n");
echo "dump-dosend (idx=".strlen($idx)." bayt)\n";

function FX($s,$name,$lbl,$cap=5000){
  echo "\n──── $lbl ────\n";
  $p=strpos($s,$name);
  if($p===false){ echo "(BULUNAMADI: $name)\n"; return; }
  $b=strpos($s,'{',$p); if($b===false){ echo substr($s,$p,300)."\n"; return; }
  $d=0;$i=$b;$L=strlen($s);
  for(;$i<$L;$i++){ $c=$s[$i]; if($c==='{')$d++; elseif($c==='}'){ $d--; if(!$d){ $i++; break; } } }
  echo substr($s,$p,min($i-$p,$cap)).(($i-$p)>$cap?"\n…(kirpildi ".($i-$p)." bayt)":"")."\n";
}

FX($idx,'function doSend','doSend',6000);
FX($idx,'function openFall','openFall (Fall eroffnen sistemi)',3000);
echo "\n=== BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-dosend.php ===\n";
