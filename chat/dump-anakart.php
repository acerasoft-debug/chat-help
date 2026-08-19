<?php
/**
 * ChatHelp — dump-anakart (SADECE OKUR) — 3 sikayet + genel saglik:
 *  [1] Katman markerlari sayimi (son duzeltmeler diskte mi?)
 *  [2] showCatDocs govdesi + cagri noktalari (vertraege kirliligi render yolu)
 *  [3] CAT_DOCS yapisi + vertraege'ye push eden donguler (string mi obje mi?)
 *  [4] Anzeigen/Ausblenden toggle kodu + CH_CAT_TGFIX blogu
 *  [5] Ana sayfa Bewerbung karti nerede tanimli
 *  HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-anakart.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$src=(string)@file_get_contents("$D/index.php");
echo "=== dump-anakart — SADECE OKUR ===\n\nindex.php: ".strlen($src)." bayt\n\n";

function occ($label,$src,$needle,$pre=100,$len=340,$max=6){
  echo "[$label] '$needle' (toplam ".substr_count($src,$needle)."):\n";
  $off=0;$n=0;
  while(($p=strpos($src,$needle,$off))!==false && $n<$max){
    $seg=substr($src,max(0,$p-$pre),$len); $seg=preg_replace('/\s+/',' ',$seg);
    echo "  @".$p." …".$seg."…\n"; $off=$p+strlen($needle);$n++;
  }
  if($n===0) echo "  (yok)\n"; echo "\n";
}

echo "───── [1] Katman sagligi ─────\n";
foreach(['CH_CARDS_KAT_FIX','CH_ABO_REFRESH','CH_FOTO_MULTI','CH_VIZE_V3','CH_VIZE_V2','CH_MIC_TRY','CH_FIX_FOTOFRAME','CH_CAT_TGFIX','CH_PWA','CH_TRVIZE_AI2'] as $m)
  echo "  ".str_pad($m,18).": ".substr_count($src,$m)." kez\n";
$sw=(string)@file_get_contents("$D/sw.js");
echo "  sw.js            : ".strlen($sw)." B, CH_SW_SAFE ".(strpos($sw,'CH_SW_SAFE')!==false?'VAR ✓':'YOK ✗').", respondWith( ".(strpos($sw,'respondWith(')!==false?'VAR ✗':'YOK ✓')."\n\n";

echo "───── [2] showCatDocs ─────\n";
$p=strpos($src,'function showCatDocs');
if($p!==false){ $seg=substr($src,$p,1900); echo "[2a] govde (1900 kr):\n".$seg."\n\n"; }
else echo "[2a] 'function showCatDocs' YOK\n\n";
occ('2b',$src,'showCatDocs(',90,240,10);
occ('2c',$src,'window.showCatDocs',80,300,6);

echo "───── [3] CAT_DOCS / vertraege besleme ─────\n";
occ('3a',$src,"CAT_DOCS['vertraege']",140,460,8);
occ('3b',$src,'CAT_DOCS[',80,300,14);
/* vertraege yakininda push/concat */
echo "[3c] 'vertraege' cevresinde push/merge (ilk 6):\n";
$off=0;$n=0;
while(($q=strpos($src,'vertraege',$off))!==false && $n<40){
  $ctx=substr($src,max(0,$q-160),380);
  if(strpos($ctx,'.push(')!==false || strpos($ctx,'concat')!==false || strpos($ctx,'for(')!==false){
    $seg=preg_replace('/\s+/',' ',$ctx); echo "  @".$q." …".$seg."…\n"; $n+=9;
  }
  $off=$q+9;$n++;
}
echo "\n";
occ('3d',$src,"'vertraege':",90,600,3);

echo "───── [4] Anzeigen/Ausblenden toggle ─────\n";
occ('4a',$src,'Ausblenden',130,380,6);
occ('4b',$src,'ausblenden',130,380,4);
occ('4c',$src,'ch-cat-tg',110,340,8);
$p=strpos($src,'CH_CAT_TGFIX');
if($p!==false){ echo "[4d] CH_CAT_TGFIX ilk blok (900 kr):\n".preg_replace('/\s+/',' ',substr($src,$p,900))."\n\n"; }

echo "───── [5] Ana sayfa Bewerbung karti ─────\n";
occ('5a',$src,'Bewerbung',110,320,10);
occ('5b',$src,'bewerbung',90,260,8);

echo "=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
