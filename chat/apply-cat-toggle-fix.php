<?php
/**
 * ChatHelp — apply-cat-toggle-fix (CH_CAT_TGFIX) — Ana sayfa kategori
 *  "anzeigen/ausblenden" dugmeleri calismiyor. KOK NEDEN (dump ile kanitli):
 *  IKI ayri toggle sistemi ayni dugmeye baglaniyor:
 *   (1) .ch-cat-tg bar'ini olusturan asil sistem — btn.onclick kendi grid'ini
 *       acar/kapatir (DOGRU calisan sistem).
 *   (2) wireToggle: metni (anzeigen|ausblenden|einblenden) regex'ine uyan HER
 *       button/span/div'e IKINCI bir click dinleyicisi ekler ve SAYFADAKI ILK
 *       grid'i toggle'lar. Ayni tiklamada iki handler birden calisinca grid
 *       iki kez toggle olup ESKI HALINE doner -> dugme "hic calismiyor".
 *  DUZELTME (tek, count-guard'li str_replace): (2)'nin element secicisi
 *  hicbir seye uymayan olu bir seciciyle degistirilir -> cakisan katman
 *  devre disi, asil sistem (1) tek basina dogru calisir. Baska hicbir sey
 *  DEGISMEZ.
 * KULLANIM: pull2.php?key=...&files=apply-cat-toggle-fix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-cat-toggle-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_CAT_TGFIX')!==false) exit("Zaten ekli (CH_CAT_TGFIX).\n");

$o1 = "querySelectorAll('.ch-cat-tg button, button, span, div')";
$n1 = "querySelectorAll('.ch-cat-tg-disabled-CH_CAT_TGFIX')";

$c1 = substr_count($src,$o1);
if ($c1!==1) { echo "  ✗ anchor bulunamadi (eslesme sayisi: $c1) — index.php DEGISTIRILMEDI.\n"; exit; }
$src = str_replace($o1,$n1,$src);
echo "  ✓ Cakisan 2. toggle katmani (wireToggle genel tarayici) devre disi\n";
echo "    -> Kategoriler dugmesi artik TEK handler ile calisir (ac/kapa net)\n";

$tmp = tempnam(sys_get_temp_dir(),'tg').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-tgfix-'.date('Ymd-His'), (string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_CAT_TGFIX')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "\n  ✓ DOGRULAMA: CH_CAT_TGFIX diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Ana sayfadaki 'Kategorien — anzeigen/ausblenden' dugmesi artik calisiyor:\n";
echo "   tikla -> kategoriler gizlenir (anzeigen ▼), tekrar tikla -> gorunur (ausblenden ▲).\n";
