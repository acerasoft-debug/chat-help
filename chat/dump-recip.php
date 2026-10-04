<?php
/**
 * ChatHelp — dump-recip — 'ai-rec' (alici/Empfänger) alaninin DOLUM kaynagini DOGRULA (OKUR).
 *  BUG: alici adresi yerine gonderici (dilekce yapanin) adresi geliyor.
 * KULLANIM: pull2.php?key=...&files=dump-recip.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
function mask($s){ return preg_replace('/(sk-[A-Za-z0-9_\-]{40,})/','***',$s); }
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-recip (".strlen($src)." B) ==\n\n";

echo "=== [1] 'ai-rec').value=' TUM atama yerleri (±60 before, 320) ===\n";
$off=0;$n=0;
while(($p=strpos($src,"ai-rec').value",$off))!==false && $n<10){
  echo "  [$n] @$p ...".mask(substr($src,max(0,$p-140),380))."...\n\n"; $off=$p+14;$n++;
}
if($n===0) echo "  yok\n";

echo "=== [2] extractRecipient tam tanimi ===\n";
$p=strpos($src,'function extractRecipient');
if($p!==false) echo mask(substr($src,$p,1100))."\n\n"; else echo "  yok\n";

echo "=== [2b] injectRecip tam tanimi (ONEMLI — bug supheli) ===\n";
$p=strpos($src,'function injectRecip');
if($p!==false) echo mask(substr($src,$p,1400))."\n\n"; else echo "  function injectRecip yok\n";
echo "  'injectRecip(' cagri sayisi: ".substr_count($src,'injectRecip(')."\n";

echo "=== [3] 'ai-rec' TUM referanslari (sayim + kisa baglam) ===\n";
echo "  toplam 'ai-rec' gecisi: ".substr_count($src,'ai-rec')."\n";
$off=0;$n=0;
while(($p=strpos($src,'ai-rec',$off))!==false && $n<20){
  $seg=trim(substr($src,max(0,$p-50),110));
  echo "  [$n] @$p ...".mask($seg)."...\n"; $off=$p+6;$n++;
}

echo "\n=== [4] recVal() tanimi ===\n";
$p=strpos($src,'function recVal');
if($p!==false) echo mask(substr($src,$p,300))."\n";

echo "\n== BITTI ==\n";
