<?php
/**
 * ChatHelp — apply-nodupaddr (CH_NODUP) — draftPrompt'a 2 net kural ekler (api.php):
 *  [1] Kapanis/imza satirindan SONRA adres (gonderici/alici) TEKRARLAMA yasagi.
 *      Kanit: "Mit freundlichen Grüßen" + isim arasina alicinin adresi sizmisti.
 *  [2] Eksik/belirli bir alana (IBAN, Kundennummer, Telefon...) ILGISIZ GERCEK bir
 *      deger (orn. adres) YAPISTIRMA yasagi — bos ________ birak.
 *      Kanit: 'IBAN: Kundenservice, Landgrabenweg 151, 53227 Bonn' (alici adresi IBAN alaninda).
 *  2 sayac-korumali str_replace, api.php (SADECE prompt metni, mantik/kod degismez).
 * KULLANIM: pull2.php?key=...&files=apply-nodupaddr.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-nodupaddr BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/api.php';
$src=@file_get_contents($file);
if($src===false) exit("api.php okunamadi\n");
if(strpos($src,'CH_NODUP')!==false) exit("Zaten ekli (CH_NODUP).\n");

$fail=[];

/* [1] kapanis-sonrasi adres tekrarlama yasagi */
$o1='end with a signature line showing the sender name. Recipient block:';
$n1='end with a signature line showing ONLY the sender\'s name — after the closing salutation/signature, NEVER repeat any address (sender\'s or recipient\'s) or any other block; the letter ends right after the name. /*CH_NODUP*/ Recipient block:';
$c1=substr_count($src,$o1);
if($c1===1){ $src=str_replace($o1,$n1,$src); echo "  ✓ [1] kapanis-sonrasi adres-tekrar yasagi eklendi\n"; }
else { echo "  ✗ [1] anchor ($c1, 1 beklenir)\n"; $fail[]=1; }

/* [2] ilgisiz gercek deger (adres) ile alan doldurma yasagi (IBAN vb.) */
$o2='If a needed detail is truly missing, write a short blank ________ instead of a bracket. /*CH_DOCQUAL*/';
$n2='If a needed detail is truly missing, write a short blank ________ instead of a bracket. Never substitute an unrelated real value (e.g. an address) for a missing specific field such as IBAN, Kundennummer, Telefonnummer or Vertragsnummer — leave that field as a blank ________ instead. /*CH_DOCQUAL*/ /*CH_NODUP*/';
$c2=substr_count($src,$o2);
if($c2===1){ $src=str_replace($o2,$n2,$src); echo "  ✓ [2] ilgisiz-deger-ile-doldurma yasagi eklendi (IBAN vb.)\n"; }
else { echo "  ✗ [2] anchor ($c2, 1 beklenir)\n"; $fail[]=2; }

if($fail){ echo "\nHATA: eslesmeyen adim(lar): ".implode(', ',$fail)." — HICBIR sey degistirilmedi.\n"; exit; }

$tmp=tempnam(sys_get_temp_dir(),'nd').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-nodup-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_NODUP')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_NODUP diskte (".strlen($chk)." B)\n";
echo "  Test: yeni bir dilekce uret (ör. Kündigung) -> imzadan sonra adres YOK,\n";
echo "        IBAN/Kundennummer eksikse ________ (bos), alici adresi ORAYA sizmiyor.\n";
