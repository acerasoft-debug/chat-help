<?php
/**
 * ChatHelp — apply-photo-pdf (CH_PHOTO_PDF) — analyze_photo'ya PDF destegi.
 *  KOK NEDEN: api.php doPhoto() her dosyayi 'type'=>'image' blogu olarak
 *  gonderiyor; PDF'lerde Anthropic API bunu reddediyor. intake-api'deki
 *  ch_vision zaten dogru deseni kullaniyor: application/pdf ->
 *  'type'=>'document'. AYNI dallanma doPhoto'ya eklenir (ternary, tek anchor).
 *  Boylece Belge & Foto Merkezi'ne yuklenen PDF'ler de tam analiz edilir.
 * KULLANIM: pull2.php?key=...&files=apply-photo-pdf.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-photo-pdf BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/api.php';
$src = @file_get_contents($file);
if ($src===false) exit("api.php okunamadi\n");
if (strpos($src,'CH_PHOTO_PDF')!==false) exit("Zaten ekli (CH_PHOTO_PDF).\n");

$o = "['type'=>'image','source'=>['type'=>'base64','media_type'=>\$mime,'data'=>\$b64]],";
$n = "(\$mime==='application/pdf' ? ['type'=>'document','source'=>['type'=>'base64','media_type'=>'application/pdf','data'=>\$b64]] : ['type'=>'image','source'=>['type'=>'base64','media_type'=>\$mime,'data'=>\$b64]]), /*CH_PHOTO_PDF*/";
$c=substr_count($src,$o);
if($c!==1) exit("HATA: anchor $c kez (1 olmali) — api.php DEGISTIRILMEDI.\n");
$src=str_replace($o,$n,$src);
echo "  ✓ doPhoto: application/pdf -> 'document' blogu (goruntuler ayni kalir)\n";

$tmp = tempnam(sys_get_temp_dir(),'pp').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — api.php DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-photopdf-'.date('Ymd-His'), (string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_PHOTO_PDF')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_PHOTO_PDF diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Belge & Foto Merkezi'ne yuklenen PDF belgeler artik TAM analiz edilir\n";
echo "   (ozet + tur + gonderen + sure + tutar, kullanicinin dilinde).\n";
echo "   Test: panele bir PDF yukleyin — ozet gelmeli.\n";
