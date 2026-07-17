<?php
/**
 * ChatHelp — dump-health (SADECE OKUR) — TEK TIK SAGLIK KONTROLU:
 *  tum kritik katman markerlari + sw.js + lint + altin kopya durumu.
 *  Her seyin yerinde olup olmadigini 10 saniyede gosterir.
 * KULLANIM: pull2.php?key=...&files=dump-health.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$src=(string)@file_get_contents("$D/index.php");
echo "=== ChatHelp SAGLIK KONTROLU — ".date('d.m.Y H:i')." ===\n\n";
echo "index.php: ".strlen($src)." bayt\n\n";
$layers=[
 'CH_VIZE_V3'=>'Vize Asistani V3 (ana konsept)',
 'CH_FOTO_PANEL'=>'Belge & Foto Merkezi',
 'CH_ANAFIX'=>'Ana sayfa paket duzeltmesi',
 'CH_FOTO_MULTI'=>'8li yukleme + belge hafizasi',
 'CH_CARDS_KAT_FIX'=>'Kart titreme duzeltmesi',
 'CH_ABO_REFRESH'=>'Abonelik tanima',
 'CH_MIC_TRY'=>'Mikrofon (dogrudan deneme)',
 'CH_FIX_FOTOFRAME'=>'Foto 📎 duzeltmesi',
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
$sw=(string)@file_get_contents("$D/sw.js");
$swok=(strpos($sw,'CH_SW_SAFE')!==false && strpos($sw,'respondWith(')===false);
if(!$swok)$bad++;
echo "  ".($swok?'✓':'✗ SORUN')."  ".str_pad('sw.js',18)." — service worker guvenli (pass-through)\n";
$tmp=tempnam(sys_get_temp_dir(),'hl').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0)$bad++;
echo "  ".($rc===0?'✓':'✗ HATA')."  ".str_pad('php lint',18)." — index.php soz dizimi\n\n";
$g=(string)@file_get_contents("$D/index-golden.php");
echo "Altin kopya: ".($g===''?'HENUZ ALINMADI — apply-golden-save.php calistirin':
  (strlen($g)." B ".(strlen($g)===strlen($src)?'(su anki halle AYNI ✓)':'(su anki halden FARKLI — degisiklik var)')))."\n\n";
echo $bad===0 ? "SONUC: HER SEY YERINDE ✓✓✓\n"
  : "SONUC: $bad SORUN VAR — ciktiyi gonderin, duzeltilir. Acilse: apply-golden-restore.php\n";
