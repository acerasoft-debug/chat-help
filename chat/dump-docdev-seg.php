<?php
/**
 * ChatHelp — dump-docdev-seg (SADECE OKUR) — "Belge olustur & iyilestir" /
 *  "Dokument erstellen & Verbessern" segmentini bul: HTML kabi (id/class),
 *  hizalama/genislik CSS'i (margin/max-width/text-align) — diger segmentler gibi
 *  ORTALAMAK icin. HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-docdev-seg.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=(string)@file_get_contents(__DIR__.'/index.php');
echo "=== dump-docdev-seg — SADECE OKUR (".strlen($src)." bayt) ===\n\n";

/* [1] baslik metni gecen yerler (çok dilli) */
$titles=['Belge oluştur','Dokument erstellen','Belge oluştur & iyileştir','erstellen & Verbessern','Konunuzu anlatın','mevcut belgenizi yapıştır','Doc-Dev','docdev','doc-dev','dev-card','ch-dev'];
echo "[1] Segment metin/marker sayimlari:\n";
foreach($titles as $t){ $n=substr_count($src,$t); if($n>0) echo "  ".str_pad($t,26)." : ".$n."\n"; }

/* [2] 'Belge oluştur' / 'Dokument erstellen' baglami (±260) */
echo "\n[2] Baslik baglamlari (HTML kabi):\n";
foreach(['Belge oluştur','Dokument erstellen'] as $t){
  $p=strpos($src,$t);
  if($p!==false){ $seg=substr($src,max(0,$p-260),520); $seg=preg_replace('/\s+/',' ',$seg); echo "\n  ── '$t' ──\n  …".$seg."…\n"; }
}

/* [3] ch-dev / dev-card / doc-dev id-class CSS (hizalama) */
echo "\n[3] Segment CSS (margin/max-width/text-align/justify):\n";
$lines=preg_split('/\n/',$src); $shown=0;
foreach($lines as $i=>$ln){
  if($shown>=22) break;
  if(preg_match('/ch-dev|dev-card|docdev|doc-dev|dokdev|ddev|#ch-dd|\.dd-/i',$ln) && preg_match('/margin|max-width|width|text-align|justify|padding|flex|grid|center/i',$ln)){
    $t=trim($ln); if(strlen($t)>200)$t=substr($t,0,200).'…';
    echo "  L".($i+1).": ".$t."\n"; $shown++;
  }
}
if(!$shown) echo "  (dogrudan CSS bulunamadi — marker adini [1]'den kullan)\n";

/* [4] Referans: DIGER ortalanmis segmentler nasil (wlc / usp ortalanma) */
echo "\n[4] Referans — ortalanmis segment kaliplari (wlc/usp max-width+margin auto):\n";
$shown=0;
foreach($lines as $i=>$ln){
  if($shown>=10) break;
  if(preg_match('/\.wlc\b|\.usp|\.wlc-|sec-hdr/',$ln) && preg_match('/margin\s*:\s*0 auto|max-width|text-align\s*:\s*center/i',$ln)){
    $t=trim($ln); if(strlen($t)>200)$t=substr($t,0,200).'…';
    echo "  L".($i+1).": ".$t."\n"; $shown++;
  }
}
if(!$shown) echo "  (referans kalip bulunamadi)\n";

echo "\n=== BITTI (salt-okunur) ===\n";
