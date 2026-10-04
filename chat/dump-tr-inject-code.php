<?php
/**
 * ChatHelp — dump-tr-inject-code (SADECE OKUR) — CH_TR_EXPAND_MORE blogunun
 *  inject() fonksiyonunun TAM govdesi (NEWCATS kayit + DOCS/CAT_DOCS merge
 *  mantigi) -> yeni TR kategorilerini (icra/aile/idari) AYNI kaliba gore
 *  eklemek icin birebir kopyalanacak. HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-tr-inject-code.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$src=(string)@file_get_contents("$D/index.php");
echo "=== dump-tr-inject-code — SADECE OKUR ===\n\n";
echo "boyut: ".strlen($src)." bayt\n\n";

$p1 = strpos($src,'CH_TR_EXPAND_MORE');
if ($p1===false) exit("CH_TR_EXPAND_MORE bulunamadi\n");
echo "[1] CH_TR_EXPAND_MORE konumu: $p1\n\n";

$p2 = strpos($src,'function inject',$p1);
if ($p2===false) exit("function inject bulunamadi (TR blogu icinde)\n");
echo "[2] inject() TAM govde (3400 karakter):\n";
echo substr($src,$p2,3400)."\n";

echo "\n[3] TR blogunda E={ oncesi ile NEWCATS arasi (yapinin basi, 700 kr):\n";
$p3 = strpos($src,'var NEWCATS={',$p1);
if ($p3!==false) echo substr($src,$p3,700)."\n"; else echo "(yok)\n";

echo "\n=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
