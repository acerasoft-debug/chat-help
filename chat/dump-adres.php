<?php
/** ChatHelp — dump-adres (SADECE OKUR) — adres onayi + imza sistemi icin:
 *  [A] profil/adres localStorage anahtarlari + alan haritasi (f1..f7 vs)
 *  [B] makePDF imza — cagrildigi yerler + govde girisi (araya girme noktasi)
 *  [C] Stripe checkout fonksiyonu (2,99 ödeme icin) + mevcut fiyat/urun anahtarlari
 *  Gizli anahtarlar maskeli. HICBIR SEY YAZMAZ. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$s=(string)@file_get_contents(__DIR__.'/index.php');
echo "=== dump-adres — SADECE OKUR ===\n\nindex.php: ".strlen($s)." B\n\n";
function occ($l,$s,$n,$pre=100,$len=320,$max=5){
  echo "[$l] '$n' (".substr_count($s,$n)."):\n"; $o=0;$c=0;
  while(($p=strpos($s,$n,$o))!==false && $c<$max){ echo "  @$p …".preg_replace('/\s+/',' ',substr($s,max(0,$p-$pre),$len))."…\n"; $o=$p+strlen($n);$c++; }
  if(!$c) echo "  (yok)\n"; echo "\n";
}
echo "──── [A] profil/adres ────\n";
occ('A1',$s,'ch_prof_v3',80,220,4);
occ('A2',$s,"P.f3",50,120,6);
occ('A3',$s,'Adresse',60,160,8);
occ('A4',$s,'restore(',60,220,4);
/* f1..f7 anlamlari */
$p=strpos($s,'[VORNAME]'); if($p!==false) echo "[A5] placeholder haritasi:\n  ".preg_replace('/\s+/',' ',substr($s,max(0,$p-60),360))."\n\n";
echo "──── [B] makePDF cagri girisi ────\n";
$p=strpos($s,'function makePDF'); if($p!==false) echo "[B1] makePDF ilk 260:\n  ".preg_replace('/\s+/',' ',substr($s,$p,260))."\n\n";
echo "[B2] makePDF( cagrilma sayisi: ".substr_count($s,'makePDF(')."\n\n";
echo "──── [C] Stripe / odeme ────\n";
occ('C1',$s,'stripeCheckout',70,240,6);
occ('C2',$s,'window.stripeCheckout',60,260,3);
occ('C3',$s,'devdoc',60,180,4);
occ('C4',$s,'action=checkout',70,200,4);
echo "=== BITTI. Ciktinin TAMAMINI gonder. ===\n";
