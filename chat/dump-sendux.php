<?php
/**
 * ChatHelp — dump-sendux — [1] CH_FAX_PRIMARY zaten canlida mi (Fax en-uste/varsayilan
 *  ozelligi) [2] chPrintDoc'un GUNCEL TAM govdesi (normal/browser dalinda hala
 *  window.open ile 'baska sayfaya' mi gidiyor) [3] gonderim secici modalinin
 *  option-siralamasi/etiketleri (Brief/Post/Fax) (OKUR).
 * KULLANIM: pull2.php?key=...&files=dump-sendux.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f=__DIR__.'/index.php';
$src=@file_get_contents($f);
if($src===false){ echo "index.php YOK\n"; exit; }
echo "== dump-sendux (".strlen($src)." B) ==\n\n";

echo "=== [1] CH_FAX_PRIMARY canlida var mi? ===\n";
echo strpos($src,'CH_FAX_PRIMARY')!==false ? "  VAR (zaten deploy edilmis)\n" : "  YOK (hic deploy edilmemis)\n";
echo "  'ai-vsa' sayisi: ".substr_count($src,'ai-vsa')."\n\n";

echo "=== [2] chPrintDoc TAM govde (ilk kopya, 200 once - fonksiyonun sonuna kadar tahmini 2600) ===\n";
$p=strpos($src,'function chPrintDoc');
if($p!==false){ echo substr($src,max(0,$p-100),2800)."\n"; }
else echo "  chPrintDoc bulunamadi\n";

echo "\n=== [3] gonderim secici option etiketleri (Brief/Post/Fax/Einschreiben civari) ===\n";
$off=0;$n=0;
while(($p=strpos($src,'ai-vsa',$off))!==false && $n<8){
  echo "  [$n] @$p ...".substr($src,max(0,$p-140),320)."...\n\n"; $off=$p+6;$n++;
}

echo "\n== BITTI ==\n";
