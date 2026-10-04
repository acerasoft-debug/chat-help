<?php
/** ChatHelp — dump-vk-bew (SADECE OKUR) — 2 sorun:
 *  [A] "Verträge kündigen" karti neden sozlesme gosteriyor? kart onclick +
 *      showCatDocs('vertraege') yonlendirmesi + CAT_DOCS['vertraege'] gercek
 *      icerigi (kuendigung_* mi vtr_/se_/be_ sozlesme mi) + data-cat redirect.
 *  [B] Bewerbung karti su an var mi, neye gidiyor? nav-cv/nav-jobs/gP hedefleri. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$s=(string)@file_get_contents(__DIR__.'/index.php');
echo "=== dump-vk-bew — SADECE OKUR ===\n\n";
function occ($l,$s,$n,$pre=90,$len=340,$max=5){
  echo "[$l] '$n' (".substr_count($s,$n)."):\n"; $o=0;$c=0;
  while(($p=strpos($s,$n,$o))!==false && $c<$max){ echo "  @$p …".preg_replace('/\s+/',' ',substr($s,max(0,$p-$pre),$len))."…\n"; $o=$p+strlen($n);$c++; }
  if(!$c) echo "  (yok)\n"; echo "\n";
}
echo "──── [A] Verträge kündigen ────\n";
occ('A1',$s,"showCatDocs('vertraege')",120,300,4);
occ('A2',$s,'Verträge kündigen',120,300,3);
/* CAT_DOCS['vertraege'] tanimi — ilk 900 kr */
$p=strpos($s,"const CAT_DOCS=");
if($p!==false){ $q=strpos($s,"'vertraege':",$p); if($q!==false) echo "[A3] CAT_DOCS.vertraege tanim (900 kr):\n".preg_replace('/\s+/',' ',substr($s,$q,900))."\n\n"; }
/* runtime'da vertraege'ye push edenler (prefix'ler) */
echo "[A4] vertraege'ye push/merge (prefix ipucu):\n";
foreach(["cat:'vertraege'","cat:\"vertraege\"","CAT_DOCS['vertraege'].push","CAT_DOCS[c3].push"] as $n){
  echo "  '$n': ".substr_count($s,$n)." kez\n";
}
/* CH_ANAFIX beyaz liste hala var mi */
echo "\n[A5] CH_ANAFIX / kuendigung_ beyaz liste:\n";
foreach(['CH_ANAFIX','CH_CARDS_KAT_FIX',"kuendigung_')===0","k.indexOf('kuendigung"] as $n) echo "  '$n': ".substr_count($s,$n)."\n";
occ('A6',$s,'data-cat',80,200,4);
occ('A7',$s,"key===\"vertraege\"",90,260,3);
occ('A8',$s,"gP('vst')",90,220,4);
echo "──── [B] Bewerbung karti ────\n";
occ('B1',$s,"showCatDocs('bewerbung')",110,280,5);
occ('B2',$s,'>Bewerbung',90,220,6);
occ('B3',$s,'ch-jobs-card',90,220,4);
occ('B4',$s,"gP('cv')",80,200,4);
occ('B5',$s,"gP('jobs')",80,200,4);
occ('B6',$s,'nav-jobs',70,180,3);
occ('B7',$s,'nav-cv',70,180,3);
echo "=== BITTI. Ciktinin TAMAMINI gonder. ===\n";
