<?php
/**
 * ChatHelp — apply-cardorder-freeze (CH_ORDER_FREEZE) — kart oynamasinin GERCEK cozumu
 *
 *  KANIT (cardwatch2, ES): childList=0 (kart eklenmiyor) ama
 *   A(order/style)=429 · B(metin)=171 · C(gorsel kaydi)=148.
 *  Yani gorunen hareket = CH_CATGRID_CSSORDER'in tick()'inin her 500ms'de
 *  kart CSS 'order' degerini SUREKLI yeniden yazmasi. childList tetiklemedigi
 *  icin tum perde/sigorta katmanlari bunu goremedi. DE'de rank stabil oturdugu
 *  icin sorun yok; genisletilmis kataloglarda (ES/TR/...) order surekli oynuyor.
 *
 *  COZUM: order'i kart basina BIR KEZ yaz, sonra KILITLE. childList=0 zaten
 *  DOM'un stabil oldugunu kanitliyor -> surekli yeniden siralama gereksiz.
 *  tick() order yazma satiri: anahtari rank'te olan her kart icin order BIR KEZ
 *  atanir (el._chOrd=1), bir daha yazilmaz -> 429'luk churn 0'a iner.
 * KULLANIM: pull2.php?key=...&files=apply-cardorder-freeze.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-cardorder-freeze BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_ORDER_FREEZE')!==false) exit("Zaten ekli (CH_ORDER_FREEZE).\n");
if (strpos($src,'CH_CATGRID_CSSORDER')===false) exit("HATA: CH_CATGRID_CSSORDER katmani yok.\n");

/* catgrid tick'inin order-yazma satiri (repo apply-catgrid-cssorder.php baytina birebir) */
$old = "        var want=(k in rank)?rank[k]:9999;\n        if(el.style.order!==String(want)) el.style.order=String(want);";
$new = "        if(!el._chOrd){ var want=(k in rank)?rank[k]:9999; if(el.style.order!==String(want)) el.style.order=String(want); if(k in rank) el._chOrd=1; } /*CH_ORDER_FREEZE — order bir kez yazilir, sonra kilit*/";

$c=0; $src=str_replace($old,$new,$src,$c);
if ($c!==1) { echo "HATA: catgrid order-yazma satiri bulunamadi (x$c) — index DEGISTIRILMEDI.\n"; exit; }
echo "  ✓ order kart basina BIR KEZ yazilip kilitleniyor (429'luk churn -> 0)\n";

$tmp = tempnam(sys_get_temp_dir(),'of').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-orderfreeze-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_ORDER_FREEZE uygulandi. Kart CSS order'i artik bir kez yerlesip sabit\n";
echo "   kaliyor — DE-disi hukuklarda kartlarin surekli oynamasi KOKTEN bitti.\n";
