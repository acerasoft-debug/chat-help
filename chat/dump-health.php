<?php
/**
 * ChatHelp — dump-health (SADECE OKUR) — TEK TIK SAGLIK KONTROLU v2:
 *  tum kritik katman markerlari (bugunku yeni moduller dahil) + api.php
 *  + sw.js + lint + altin kopya durumu. HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-health.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$src=(string)@file_get_contents("$D/index.php");
echo "=== ChatHelp SAGLIK KONTROLU v2 — ".date('d.m.Y H:i')." ===\n\n";
echo "index.php: ".strlen($src)." bayt\n\n";
$layers=[
 'CH_VIZE_V3'=>'Vize Asistani V3 (ana konsept)',
 'CH_VIZE_V4'=>'Vize V4: dil safligi + premium',
 'CH_FOTO_PANEL'=>'Belge & Foto Merkezi',
 'CH_FOTO_BELGE'=>'Merkez: PDF kabul + en uste',
 'CH_FOTO_PANEL2'=>'Merkez: hero alti + hata gorunur',
 'CH_FOTO_GRUP'=>'Ayni konuda TEK birlesik cevap',
 'CH_CHAT_ATT'=>'Chat 📎 -> Merkez (tek mantik)',
 'CH_FOTO_MULTI'=>'Belge hafizasi + profil',
 'CH_CHATPDF_GUARD'=>'Bos PDF korumasi',
 'CH_EMSAL'=>'Emsal Karar modulu',
 'CH_THEMEN5'=>'Themen duzeni + Chat girisi kaldirma',
 'CH_ANAFIX'=>'Ana sayfa paket duzeltmesi',
 'CH_CARDS_KAT_FIX'=>'Kart titreme duzeltmesi',
 'CH_ABO_REFRESH'=>'Abonelik tanima',
 'CH_MIC_TRY'=>'Mikrofon (dogrudan deneme)',
 'CH_TRVIZE_AI2'=>'TR vize PDF cevirisi',
 'CH_PWA'=>'PWA / App altyapisi',
 'CH_THEME_BRIDGE'=>'Tema koprusu',
];
$bad=0;
foreach($layers as $m=>$desc){
  $ok=strpos($src,$m)!==false;
  if(!$ok)$bad++;
  echo "  ".($ok?'✓':'✗ EKSIK')."  ".str_pad($m,18)." — $desc\n";
}
$api=(string)@file_get_contents("$D/api.php");
$apiok=strpos($api,'CH_PHOTO_PDF')!==false;
if(!$apiok)$bad++;
echo "  ".($apiok?'✓':'✗ EKSIK')."  ".str_pad('CH_PHOTO_PDF',18)." — api.php: PDF analiz destegi\n";
$sw=(string)@file_get_contents("$D/sw.js");
$swok=(strpos($sw,'CH_SW_SAFE')!==false && strpos($sw,'respondWith(')===false);
if(!$swok)$bad++;
echo "  ".($swok?'✓':'✗ SORUN')."  ".str_pad('sw.js',18)." — service worker guvenli (pass-through)\n";
$tmp=tempnam(sys_get_temp_dir(),'hl').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0)$bad++;
echo "  ".($rc===0?'✓':'✗ HATA')."  ".str_pad('php lint',18)." — index.php soz dizimi\n";
$al=(string)@file_get_contents(dirname($D).'/.well-known/assetlinks.json');
echo "  ".($al!==''?'✓':'○')."  ".str_pad('assetlinks.json',18)." — ".($al!==''?'TWA dogrulamasi kurulu':'henuz kurulmadi (App/TWA adimi)')."\n\n";
$g=(string)@file_get_contents("$D/index-golden.php");
echo "Altin kopya: ".($g===''?'HENUZ ALINMADI — apply-golden-save.php calistirin':
  (strlen($g)." B ".(strlen($g)===strlen($src)?'(su anki halle AYNI ✓)':'(su anki halden ESKI — apply-golden-save.php ile guncelleyin)')))."\n\n";
echo $bad===0 ? "SONUC: HER SEY YERINDE ✓✓✓\n"
  : "SONUC: $bad SORUN VAR — ciktiyi gonderin, duzeltilir. Acilse: apply-golden-restore.php\n";
