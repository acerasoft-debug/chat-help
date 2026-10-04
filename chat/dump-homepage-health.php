<?php
/**
 * ChatHelp — dump-homepage-health (SADECE OKUR) — "ana sayfa arayuz cikmiyor"
 *  ACIL teshisi: index.php butunlugu + son eklenen bloklarin durumu +
 *  ana iskelet elementleri + zen/clean sinifi tuzaklari. HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-homepage-health.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$src=(string)@file_get_contents("$D/index.php");
echo "=== dump-homepage-health — SADECE OKUR ===\n\n";
echo "[0] boyut: ".strlen($src)." bayt\n";

$lo=[];$rc=0; exec('php -l '.escapeshellarg("$D/index.php").' 2>&1',$lo,$rc);
echo "[1] php -l: ".($rc===0?"TEMIZ":implode(' ',$lo))."\n\n";

echo "[2] Iskelet elementleri:\n";
foreach(['<div id="app">','<div id="sb">','id="inp"','id="pb"','</body>','</html>'] as $m)
  echo "  ".str_pad($m,18).": ".substr_count($src,$m)." kez\n";

echo "\n[3] Son eklenen marker'lar:\n";
foreach(['CH_NAVY_ICON','CH_THEME_HTMLBG','CH_VIZE_DATEFIX','CH_CAT_TGFIX','CH_MIC_UNI','CH_MIC_WVDIRECT','CH_CLEANFIX','CH_TR_EXPAND3'] as $m)
  echo "  ".str_pad($m,18).": ".substr_count($src,$m)." kez\n";

echo "\n[4] Dosyanin SON 1200 karakteri (kapanis saglam mi):\n";
echo substr($src,-1200)."\n";

echo "\n[5] </body> ile </html> arasi uzunluk + son </body>'den sonra script var mi:\n";
$pb=strripos($src,'</body>'); $ph=strripos($src,'</html>');
echo "  son </body> pozisyonu: $pb ; son </html>: $ph ; aradaki mesafe: ".($ph!==false&&$pb!==false?($ph-$pb):'?')."\n";

echo "\n[6] 'ch-clean' / zen tuzaklari:\n";
foreach(['ch-clean','ch_zen','chZen'] as $m)
  echo "  ".str_pad($m,10).": ".substr_count($src,$m)." kez\n";

echo "\n[7] <body ...> etiketi (class/onload var mi):\n";
$p=stripos($src,'<body');
if($p!==false) echo "  ".preg_replace('/\s+/',' ',substr($src,$p,220))."\n";

echo "\n=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
