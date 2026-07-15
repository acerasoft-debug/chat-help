<?php
/**
 * ChatHelp — dump-zen (SADECE OKUR) — "kartlar gizli, sadece sohbet var".
 *  Teori: zen/temiz mod (body.ch-clean) localStorage'da kilitli. Bul:
 *  (1) ch-clean class'ini ekleyen/kaldiran kod + localStorage ANAHTARI,
 *  (2) wcat-grid'i (kartlari) hangi kural gizliyor (guard'li mi),
 *  (3) CH_ZEN bloklari baglami. Boylece tam sifirlama yazilir. HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-zen.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=(string)@file_get_contents(__DIR__.'/index.php');
echo "=== dump-zen — SADECE OKUR (".strlen($src)." bayt) ===\n\n";

function ctx($src,$needle,$pre,$len,$max=6){
  echo "──── '$needle' (toplam ".substr_count($src,$needle).") ────\n";
  $off=0;$n=0;
  while(($p=strpos($src,$needle,$off))!==false && $n<$max){
    $seg=substr($src,max(0,$p-$pre),$len); $seg=preg_replace('/\s+/',' ',$seg);
    echo "  …".$seg."…\n"; $off=$p+strlen($needle);$n++;
  }
  if(!$n) echo "  (YOK)\n";
  echo "\n";
}

echo "[1] ch-clean class ekleme/kaldirma:\n";
ctx($src,"classList.add('ch-clean')",50,140);
ctx($src,'classList.add("ch-clean")',50,140);
ctx($src,"classList.toggle('ch-clean'",50,150);
ctx($src,'classList.toggle("ch-clean"',50,150);
ctx($src,"'ch-clean'",30,90,10);
ctx($src,'"ch-clean"',30,90,10);

echo "[2] zen localStorage anahtarlari:\n";
ctx($src,'ch_zen',40,120,8);
ctx($src,'ch_clean',40,120,8);
ctx($src,'chZen',40,120,6);
ctx($src,"getItem('ch",20,80,12);
ctx($src,"setItem('ch",20,90,12);

echo "[3] kartlari (wcat-grid) gizleyen kurallar:\n";
$lines=preg_split('/\n/',$src); $shown=0;
foreach($lines as $i=>$ln){
  if($shown>=16) break;
  if(preg_match('/wcat-grid|wcat|\.wlc-quick|sec-hdr/',$ln) && preg_match('/display\s*:\s*none|visibility|opacity\s*:\s*0/i',$ln)){
    $t=trim($ln); if(strlen($t)>200)$t=substr($t,0,200).'…';
    echo "  L".($i+1).": ".$t."\n"; $shown++;
  }
}
if(!$shown) echo "  (wcat-grid gizleyen kural yok — sorun zen degil olabilir)\n";

echo "\n[4] CH_ZEN blok baglamlari:\n";
ctx($src,'CH_ZEN',60,180,6);

echo "\n[5] body/documentElement'e ch-clean UYGULANMASI (init/DOMContentLoaded):\n";
$off=0;$n=0;
while(($p=strpos($src,'ch-clean',$off))!==false && $n<14){
  $a=max(0,$p-120); $seg=substr($src,$a,220); $seg=preg_replace('/\s+/',' ',$seg);
  if(preg_match('/add|toggle|className|getItem|init|load/i',$seg)){
    echo "  [@$p] …".$seg."…\n"; $n++;
  }
  $off=$p+8;
}
if(!$n) echo "  (init baglaminda ch-clean yok)\n";

echo "\n=== BITTI (salt-okunur) ===\n";
