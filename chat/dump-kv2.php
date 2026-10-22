<?php
/* dump-kv2 (READ-ONLY) — (A) Konto 'Pläne & Preise'/'Abmelden' cift bloklarinin
   yerleri+baglami; (B) Verlauf: renderVerlauf/loadSession/addVerlaufNav + oturum
   depolama (ch_chat_ enumerasyon). Kesin duzeltme icin.
   KULLANIM: pull2.php?key=...&files=dump-kv2.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "dump-kv2 (READ-ONLY)\n\n";
$s=@file_get_contents(__DIR__.'/index.php');
if($s===false) exit("okunamadi\n");
function W($s,$p,$b,$a){ return trim(preg_replace('/\s+/',' ',substr($s,max(0,$p-$b),$b+$a))); }

echo "=== [A1] 'Pläne & Preise' 2 yeri (±140) ===\n";
$off=0;$c=0; while(($p=strpos($s,'Pläne & Preise',$off))!==false && $c<3){ echo "  #".($c+1)." [".$p."] …".W($s,$p,140,120)."…\n\n"; $off=$p+5;$c++; }

echo "=== [A2] 'Abmelden' 2 yeri (±120) ===\n";
$off=0;$c=0; while(($p=strpos($s,'Abmelden',$off))!==false && $c<3){ echo "  #".($c+1)." [".$p."] …".W($s,$p,120,90)."…\n\n"; $off=$p+5;$c++; }

echo "=== [A3] pnl-konto icindeki blok kimlikleri (id= ve sec-title, sirasiyla) ===\n";
$ps=strpos($s,'id="pnl-konto"'); $pe=strpos($s,'id="pnl-',$ps+5);
$seg=substr($s,$ps, ($pe!==false? min($pe-$ps, 40000):40000));
if(preg_match_all('/(id="(pnl-konto|[a-z0-9\-]*konto[a-z0-9\-]*|ch-[a-z\-]+)"|class="sec-title"[^>]*>([^<]{1,40})|class="ksec-hdr-t"[^>]*>([^<]{1,40}))/i',$seg,$m,PREG_SET_ORDER)){
  $i=0; foreach($m as $x){ if($i++>=40) break; echo "  ".trim($x[0])."\n"; }
}

echo "\n\n=== [B1] renderVerlauf TAM ===\n";
$p=strpos($s,'function renderVerlauf');
if($p!==false){ $e=strpos($s,"\n  function ",$p+10); if($e===false||$e-$p>3000)$e=$p+2600; echo substr($s,$p,$e-$p)."\n"; }

echo "\n=== [B2] loadSession TAM ===\n";
$p=strpos($s,'function loadSession');
if($p!==false){ echo W($s,$p,0,600)."\n"; }

echo "\n=== [B3] addVerlaufNav TAM (nav-verlauf yapisi) ===\n";
$p=strpos($s,'function addVerlaufNav');
if($p!==false){ $e=strpos($s,"\n  function ",$p+10); if($e===false||$e-$p>2400)$e=$p+2000; echo substr($s,$p,$e-$p)."\n"; }

echo "\n=== [B4] oturum listesi depolama (ch_chat_ / index) ===\n";
foreach(['ch_chat_index','ch_chat_list','ch_chats','listSessions','allSessions','Object.keys(localStorage'] as $k){
  $p=strpos($s,$k); if($p!==false) echo "  '".$k."' [".$p."] ".W($s,$p,20,120)."\n";
}
/* renderVerlauf oturumlari nasil topluyor? */
$p=strpos($s,'renderVerlauf'); 
echo "\nBITTI.\n";
