<?php
/** ChatHelp — dump-chatflow: callDeepSeek/callClaude TAM govdesi + mevcut sohbet/detect akisi. SADECE OKUR. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$api=@file_get_contents(__DIR__.'/api.php');
$idx=@file_get_contents(__DIR__.'/index.php');
if($api===false) exit("api.php okunamadi\n");
echo "dump-chatflow (api=".strlen($api)." idx=".($idx!==false?strlen($idx):'?')." bayt)\n";

function FX($s,$name,$lbl,$cap=3500){
  echo "\n──── $lbl ────\n";
  $p=strpos($s,$name);
  if($p===false){ echo "(BULUNAMADI: $name)\n"; return; }
  $b=strpos($s,'{',$p); if($b===false){ echo substr($s,$p,300)."\n"; return; }
  $d=0;$i=$b;$L=strlen($s);
  for(;$i<$L;$i++){ $c=$s[$i]; if($c==='{')$d++; elseif($c==='}'){ $d--; if(!$d){ $i++; break; } } }
  echo substr($s,$p,min($i-$p,$cap)).(($i-$p)>$cap?"\n…(kirpildi ".($i-$p)." bayt)":"")."\n";
}
function WIN($s,$needle,$lbl,$before=150,$len=900,$max=5){
  echo "\n──── $lbl ────\n";
  $o=0;$n=0;
  while(($p=stripos($s,$needle,$o))!==false && $n<$max){
    echo "@$p: ".trim(preg_replace('/\s+/',' ',substr($s,max(0,$p-$before),$len)))."\n---\n";
    $o=$p+strlen($needle); $n++;
  }
  if(!$n) echo "  (yok)\n";
}

echo "\n################ A) callDeepSeek TAM GOVDE ################\n";
FX($api,'function callDeepSeek','callDeepSeek',3500);
FX($api,'function callDeepseek','callDeepseek (kucuk harf varyant)',3500);

echo "\n################ B) callClaude (asil, vision degil) ################\n";
FX($api,'function callClaude(','callClaude',2500);

echo "\n################ C) deepseek dispatch/kullanim noktalari ################\n";
WIN($api,'deepseek','api.php deepseek gecen TUM yerler',100,400,15);

echo "\n################ D) match() dispatch tam listesi ################\n";
$p=strpos($api,'match(');
if($p!==false) echo substr($api,$p,1200)."\n";

echo "\n################ E) frontend: mevcut sohbet/detect akisi (index.php) ################\n";
if($idx!==false){
  FX($idx,'function detect','detect fonksiyonu (frontend)',2000);
  WIN($idx,"action=detect","detect action cagri noktasi",100,500,3);
  WIN($idx,'function addMsg','addMsg fonksiyonu',80,600,2);
  WIN($idx,"id=\"inp\"",'giris kutusu (inp) markup',150,500,2);
  WIN($idx,'sendMessage\|function sm(\|function send(','gonderme fonksiyonu adaylari',100,400,4);
}

echo "\n=== BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-chatflow.php ===\n";
