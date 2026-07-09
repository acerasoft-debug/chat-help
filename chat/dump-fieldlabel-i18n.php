<?php
/** ChatHelp — dump-fieldlabel-i18n (SADECE OKUR)
 *  ch-i18n-full (TreeWalker tabanli genel katman) <label> etiketlerini
 *  BILEREK atliyor (SKIP_TAGS icinde LABEL:1). Demek ki '.fi label' alan
 *  basliklarini ceviren AYRI, ESKI bir sistem var — o, cevrilmis metni
 *  ORIJINALIN ALTINA span olarak EKLIYOR (degistirmiyor). Bu dump, o
 *  '.fi label:not([data-i18n-done])' sorgusunu iceren <script> blogunun
 *  TAMAMINI (basindan sonuna) cikarir.
 *  Ciktinin TAMAMINI gonder. SIL: rm dump-fieldlabel-i18n.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx = @file_get_contents(__DIR__.'/index.php');
if ($idx===false) exit("index.php okunamadi\n");

$needle = ".fi label:not([data-i18n-done])";
$p = strpos($idx, $needle);
if ($p===false) { echo "anchor bulunamadi\n"; exit; }

/* geriye dogru en yakin <script id="..."> */
$scriptStart = strrpos(substr($idx,0,$p), '<script');
/* ileriye dogru en yakin </script> */
$scriptEnd = strpos($idx, '</script>', $p);
if ($scriptStart===false || $scriptEnd===false) { echo "script sinirlari bulunamadi\n"; exit; }
$blk = substr($idx, $scriptStart, $scriptEnd - $scriptStart + 9);

echo "════════ ALAN-BASLIGI i18n SCRIPT BLOGU (".strlen($blk)." bayt) ════════\n\n";
echo $blk."\n";
echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-fieldlabel-i18n.php ════════\n";
