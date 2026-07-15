<?php
/**
 * ChatHelp — dump-abo-verwalten2 (SADECE OKUR) — CH_BILLING_PORTAL_UI'nin
 *  KALAN kismi: buton stili/ekleme + ensure() cagrilma sikligi (interval var mi,
 *  P.plan ASENKRON guncellenince ensure() tekrar calisiyor mu). HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-abo-verwalten2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=(string)@file_get_contents(__DIR__.'/index.php');
echo "=== dump-abo-verwalten2 — SADECE OKUR (".strlen($src)." bayt) ===\n\n";

echo "[1] CH_BILLING_PORTAL_UI TAM govde:\n";
$p=strpos($src,'id="ch-billing-portal-ui"');
if($p!==false){
  $s=strpos($src,'>',$p)+1; $e=strpos($src,'</script>',$s);
  echo substr($src,$s,$e-$s)."\n";
} else echo "(bulunamadi)\n";

echo "\n=== BITTI (salt-okunur). Ciktinin TAMAMINI gonder. ===\n";
