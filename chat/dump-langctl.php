<?php
/** ChatHelp — dump-langctl: TUM dil/hukuk kontrolleri + kalicilik mekanizmasi. SADECE OKUR. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx = @file_get_contents(__DIR__.'/index.php');
if ($idx === false) exit("index.php okunamadi\n");
echo "dump-langctl (idx=".strlen($idx)." bayt)\n";

function FX($s,$name,$lbl,$cap=2200){
  echo "\n──── $lbl ────\n";
  $p=strpos($s,$name);
  if($p===false){ echo "(BULUNAMADI)\n"; return; }
  $b=strpos($s,'{',$p); if($b===false){ echo substr($s,$p,300)."\n"; return; }
  $d=0;$i=$b;$L=strlen($s);
  for(;$i<$L;$i++){ $c=$s[$i]; if($c==='{')$d++; elseif($c==='}'){ $d--; if(!$d){ $i++; break; } } }
  echo substr($s,$p,min($i-$p,$cap)).(($i-$p)>$cap?"\n…(kirpildi)":"")."\n";
}
function WIN($s,$needle,$lbl,$before=120,$len=800,$max=5){
  echo "\n──── $lbl ────\n";
  $o=0;$n=0;
  while(($p=strpos($s,$needle,$o))!==false && $n<$max){
    echo "@$p: ".substr($s,max(0,$p-$before),$len)."\n---\n";
    $o=$p+strlen($needle); $n++;
  }
  if(!$n) echo "  (yok)\n";
}

FX($idx,'function savePref','savePref — ne kaydediyor');
FX($idx,'function loadPref','loadPref — ne yuklyor');
WIN($idx,'loadPref','loadPref cagri noktalari',60,300,4);
WIN($idx,'lang-opt','lang-opt seciciler (markup+handler)',150,700,5);
WIN($idx,'Interface','Interface Sprache ayari',150,800,4);
WIN($idx,'dLang','dLang (belge/hukuk dili) kullanimi',100,500,8);
WIN($idx,'CH_LANG_LAW_SEP','dil-hukuk ayrimi katmani',80,1600,1);
WIN($idx,'setUILang','setUILang varsa',100,700,3);
echo "\n=== BITTI ===\n";
