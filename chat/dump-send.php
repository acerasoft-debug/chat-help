<?php
/**
 * ChatHelp — dump-send — "gonderim yonu" paketi icin gerekli anchorlar (OKUR):
 *  [1] PDF/kaydet butonu (Als PDF speichern) yapisi + fonksiyon adi
 *  [2] Gonderim akisi (Normal/Einschreiben/Fax, postversand)
 *  [3] Intro/hero (wlc-h1, "Ihr Recht")
 *  [4] Fax kota/paket gosterimi (CH_FAX_QUOTA / CH_FAX_PLANS)
 * KULLANIM: pull2.php?key=...&files=dump-send.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false) exit("index.php okunamadi\n");
echo "== dump-send (".strlen($src)." B) ==\n\n";

function snip($src,$needle,$b=180,$a=700,$label=''){
  $p=stripos($src,$needle);
  if($p===false){ echo "-- [$label] '$needle' YOK\n\n"; return; }
  echo "-- [$label] '$needle' --\n".substr($src,max(0,$p-$b),$b+$a)."\n----\n\n";
}
function cnt($src,$s){ return substr_count($src,$s); }

echo "########## [1] PDF / KAYDET BUTONU ##########\n";
foreach(['Als PDF','PDF speichern','Als PDF speichern','pdf-btn','downloadPdf','genPdf','makePdf','erstellen & '] as $k){ echo "  '".$k."': ".cnt($src,$k)."\n"; }
snip($src,'Als PDF',150,500,'1a Als PDF metni');
snip($src,'PDF speichern',150,500,'1b PDF speichern');

echo "########## [2] GONDERIM (Versand) ##########\n";
foreach(['postversand','Einschreiben','send_fax','Versand','Fax senden','ai-faxwrap','ch-versand'] as $k){ echo "  '".$k."': ".cnt($src,$k)."\n"; }
snip($src,'Einschreiben',260,700,'2a versand secenekleri');

echo "########## [3] INTRO / HERO ##########\n";
foreach(['wlc-h1','Ihr Recht','ch-hero','wlc-sub','hero-sub'] as $k){ echo "  '".$k."': ".cnt($src,$k)."\n"; }
snip($src,'Ihr Recht',200,600,'3a hero metni');
snip($src,'wlc-h1',120,500,'3b wlc-h1');

echo "########## [4] FAX KOTA/PAKET ##########\n";
foreach(['CH_FAX_QUOTA','CH_FAX_PLANS','ch-fax-plan','Gratis-Fax','chFaxQuota'] as $k){ echo "  '".$k."': ".cnt($src,$k)."\n"; }

echo "\n== BITTI ==\n";
