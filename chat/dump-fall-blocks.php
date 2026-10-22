<?php
/** ChatHelp — dump-fall-blocks (SADECE OKUR)
 *  KANIT: "Erstelle jetzt das finale Dokument auf Deutsch." — belge SONUNDA
 *  ACIKCA ALMANCA'YA KILITLENIYOR. Kod fonksiyon icinde degil, duz prosedurel
 *  if/elseif bloklari halinde. Bu dump her iki $sys etrafinda GENIS baglam
 *  (1600 bayt once, 1000 bayt sonra) + $body/$action parse noktasini gosterir.
 *  Ciktinin TAMAMINI gonder. SIL: rm dump-fall-blocks.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f = @file_get_contents(__DIR__.'/fall-api.php');
if ($f===false) exit("fall-api.php okunamadi\n");

$p1 = strpos($f, '$sys = "Du bist ein erfahrener, einfühlsamer');
$p2 = strpos($f, '$sys = "Du bist ein erfahrener deutscher Rechtsanwalt');

echo "════════ [1] Q&A BLOGU GENIS BAGLAM (@".$p1.") ════════\n\n";
if ($p1!==false) echo substr($f, max(0,$p1-1600), 2800)."\n";

echo "\n════════ [2] BELGE BLOGU GENIS BAGLAM (@".$p2.") ════════\n\n";
if ($p2!==false) echo substr($f, max(0,$p2-1600), 3000)."\n";

echo "\n════════ [3] '\$body' / '\$_POST' / 'action' PARSE NOKTASI (ilk gecisler) ════════\n\n";
$off=0;$n=0;
while(($p=strpos($f,'$body',$off))!==false && $n<8){
    echo "  @".$p.": ...".preg_replace('/\s+/',' ',substr($f,max(0,$p-70),160))."...\n";
    $off=$p+5; $n++;
}
echo "\n";
$off=0;$n=0;
while(($p=stripos($f,'php://input',$off))!==false && $n<5){
    echo "  @".$p.": ...".preg_replace('/\s+/',' ',substr($f,max(0,$p-80),200))."...\n";
    $off=$p+10; $n++;
}

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-fall-blocks.php ════════\n";
