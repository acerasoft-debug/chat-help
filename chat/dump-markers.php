<?php
/* dump-markers — canli index.php'de hangi katmanlar yuklu (salt-okunur).
   KULLANIM: pull2.php?key=...&files=dump-markers.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false) exit("index.php okunamadi\n");
echo "index.php ".strlen($src)." B\n\n";
$marks=['CH_ADRES_IMZA','CH_ADRES_IMZA2','CH_ADRES_IMZA3','CH_ADRES_IMZA4',
        'CH_ABO_REFRESH','CH_EMSAL','CH_EMSAL_HUB','CH_THEMEN5','CH_VKBEW','CH_AI_KONTROL',
        'CH_MODHUB','CH_FOTO_MULTI','CH_VIZE_V4'];
foreach($marks as $m){ echo str_pad($m,18)." : ".(strpos($src,$m)!==false?'VAR':'yok')."\n"; }
echo "\n-- priceStr / fiyat --\n";
echo "  '1,20 €' : ".substr_count($src,'1,20 €')."\n";
echo "  '1,50 €' : ".substr_count($src,'1,50 €')."\n";
echo "  '2,99 €' : ".substr_count($src,'2,99 €')."\n";
echo "  priceStr(: ".substr_count($src,'priceStr(')."\n";
echo "  chPrintDoc: ".substr_count($src,'chPrintDoc')."\n";
