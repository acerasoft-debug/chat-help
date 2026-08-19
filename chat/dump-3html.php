<?php
/**
 * ChatHelp — dump-3html — 3 alanin GERCEK HTML/JS snippet'i (SADECE OKUR):
 *  [A] Verlauf render mantigi (ch-verlauf-recent-js) + nav-verlauf tanimi
 *  [B] Google Play / QR buton alani (play.google.com civari) + ch-qr-btn
 *  [C] Meine Themen paneli (ch-themen5-js / ch-topics-sec render'i)
 * KULLANIM: pull2.php?key=...&files=dump-3html.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false) exit("index.php okunamadi\n");
echo "== dump-3html ==\n\n";

function snip($src,$needle,$before=200,$after=1100,$label=''){
  $p=strpos($src,$needle);
  if($p===false){ echo "-- [$label] '$needle' BULUNAMADI\n\n"; return; }
  $s=max(0,$p-$before);
  echo "-- [$label] '$needle' civari --\n".substr($src,$s,$before+$after)."\n----\n\n";
}

echo "########## [A] VERLAUF ##########\n";
snip($src,'ch-verlauf-recent-js',60,1500,'A1 render');
snip($src,'id="nav-verlauf"',260,260,'A2 nav-verlauf DOM');

echo "########## [B] GOOGLE PLAY / QR ##########\n";
snip($src,'ch-qr-btn',300,900,'B1 qr-btn');
snip($src,'play.google.com',400,500,'B2 play link');
snip($src,'ch-appstore',200,1200,'B3 appstore-ios');

echo "########## [C] MEINE THEMEN ##########\n";
snip($src,'ch-themen5-js',60,1600,'C1 themen5 render');
snip($src,'ch-topics-sec',200,700,'C2 topics-sec');

echo "== BITTI ==\n";
