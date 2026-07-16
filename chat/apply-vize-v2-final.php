<?php
/**
 * ChatHelp — apply-vize-v2-final (CH_VIZE_V2F) — gentest raporundaki 3 kalite
 *  kusurunun duzeltmesi:
 *   [1] Mektup tarihleri yanlisti (model bugunu bilmiyor, 2025 atiyordu)
 *       -> prompt'a bugunun tarihi eklenir ("als Briefdatum verwenden")
 *       + "KEIN Markdown, keine Sternchen" kurali.
 *   [2] Davet mektubunda [Geburtsdatum] yer tutucusu kaliyordu cunku davet
 *       edenin dogum tarihi formda sorulmuyordu -> alan eklendi.
 *   [3] **kalin** markdown yildizlari PDF'e ham geciyordu -> temizleyici
 *       artik ** ayiklar.
 *  3 count-guard'li degisiklik, CH_VIZE_V2 blogu icinde. Simulasyonla test.
 * KULLANIM: pull2.php?key=...&files=apply-vize-v2-final.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-vize-v2-final BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_VIZE_V2')===false) exit("HATA: once CH_VIZE_V2 gerekli.\n");
if (strpos($src,'CH_VIZE_V2F')!==false) exit("Zaten ekli (CH_VIZE_V2F).\n");

$fail=[];
function rep(&$src,&$fail,$label,$o,$nw){
  $c=substr_count($src,$o);
  if($c!==1){ echo "  ✗ [$label] anchor ($c)\n"; $fail[]=$label; return; }
  $src=str_replace($o,$nw,$src);
  echo "  ✓ [$label]\n";
}
$o_x = <<<'ANC'
    var tail=extra(g)+'\nKöşeli parantezli yer tutucu ASLA bırakma; bilinmeyen alan varsa cümleyi bilgisiz kur.';
ANC;
$n_x = <<<'ANC'
    var td=new Date(),tds=('0'+td.getDate()).slice(-2)+'.'+('0'+(td.getMonth()+1)).slice(-2)+'.'+td.getFullYear(); var tail=extra(g)+'\nHeutiges Datum: '+tds+' — dieses Datum als Briefdatum verwenden.\nKöşeli parantezli yer tutucu ASLA bırakma; eksik bilgi varsa o cümleyi bilgisiz kur. KEIN Markdown, keine Sternchen, reiner Brieftext.';
ANC;
rep($src,$fail,'tarih+markdown-kurali',$o_x,$n_x);
$o_x = <<<'ANC'
    invitation:[['davet_ad','Davet eden kişinin adı soyadı',0],['davet_adres','Davet edenin adresi (gidilecek ülkede)',0],['yakinlik','Yakınlık dereceniz',0],['masraf','Masrafları kim karşılıyor?',0]],
ANC;
$n_x = <<<'ANC'
    invitation:[['davet_ad','Davet eden kişinin adı soyadı',0],['davet_dogum','Davet edenin doğum tarihi (opsiyonel)',0],['davet_adres','Davet edenin adresi (gidilecek ülkede)',0],['yakinlik','Yakınlık dereceniz',0],['masraf','Masrafları kim karşılıyor?',0]],
ANC;
rep($src,$fail,'davet-dogum-alani',$o_x,$n_x);
$o_x = <<<'ANC'
  function clean(s){ return String(s||'').replace(/```[a-z]*\n?|```/g,'').replace(/\[\[\/?DOC\]\]/g,'').trim(); }
ANC;
$n_x = <<<'ANC'
  function clean(s){ /*CH_VIZE_V2F*/ return String(s||'').replace(/```[a-z]*\n?|```/g,'').replace(/\[\[\/?DOC\]\]/g,'').replace(/\*\*/g,'').trim(); }
ANC;
rep($src,$fail,'yildiz-temizleyici',$o_x,$n_x);

if ($fail) { echo "\nHATA: eslesmeyen: ".implode(', ',$fail)." — index DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(),'v2f').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-v2f-'.date('Ymd-His'), (string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_VIZE_V2F')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "\n  ✓ DOGRULAMA: CH_VIZE_V2F diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Belgeler artik: dogru bugun tarihiyle, markdown'siz, davet mektubu\n";
echo "   dogum tarihi alanli — konsolosluk formatinda tam temiz cikar.\n";
