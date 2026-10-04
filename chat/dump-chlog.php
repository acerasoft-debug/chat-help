<?php
/**
 * ChatHelp — dump-chlog — teshis log'unu pull2 uzerinden okur.
 *  Dogrudan chlog9k1.php?read=1 URL'i CDN'de bos olarak onbellege alindigi
 *  icin okunamiyor; bu betik dosyayi diskten okur, onbellek devre disi.
 * KULLANIM: pull2.php?key=...&files=dump-chlog.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
$f=__DIR__.'/chlog9k1.txt';
echo "== dump-chlog ==\n";
if(!is_file($f)){ echo "chlog9k1.txt YOK (hic kayit gelmemis)\n"; exit; }
echo "dosya: ".filesize($f)." B   son yazma: ".date('Y-m-d H:i:s',filemtime($f))."\n";
echo "sunucu saati : ".date('Y-m-d H:i:s')."\n";
echo "----------------------------------------\n";
echo (string)@file_get_contents($f);
echo "----------------------------------------\n";
$s=(string)@file_get_contents($f);
echo "LOAD satiri : ".substr_count($s,'LOAD ')."\n";
echo "CLICK satiri: ".substr_count($s,'CLICK ')."\n";
echo "== BITTI ==\n";
