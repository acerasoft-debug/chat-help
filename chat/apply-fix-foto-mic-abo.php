<?php
/**
 * ChatHelp — apply-fix-foto-mic-abo (CH_FIX_A) — ACIL 4'lu paketin 1. bolumu:
 *  BACKEND + FRONTEND-GATE duzeltmeleri (index.php'ye DOKUNMAZ; ayri dosyalar).
 *  [A] intake-api.php: gecersiz model 'claude-sonnet-4-6' -> 'claude-sonnet-4-5'
 *      (2 gecis: ch_claude + ch_vision). Fall/intake yolundaki foto->dilekce
 *      hatti bu yuzden bostu (CH_MODEL_FIX yalniz api.php'yi duzeltmisti).
 *  Bu betik SADECE intake-api.php'yi (protected uretim dosyasi) sunucuda
 *  yaminlar; lint + .bak + dogrulama. node/JS yok.
 * KULLANIM: pull2.php?key=...&files=apply-fix-foto-mic-abo.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fix-foto-mic-abo BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/intake-api.php';
$src = @file_get_contents($file);
if ($src===false) exit("HATA: intake-api.php okunamadi\n");

$bad = "'model'=>'claude-sonnet-4-6'";
$good= "'model'=>'claude-sonnet-4-5'";
$cnt = substr_count($src,$bad);
echo "  intake-api.php'de gecersiz model gecisi: $cnt\n";
if ($cnt===0) { echo "  ✓ Zaten duzeltilmis (gecersiz model yok) — degisiklik gerekmez.\n"; exit; }
$src = str_replace($bad,$good,$src);
echo "  ✓ $cnt gecis 'claude-sonnet-4-5' olarak duzeltildi\n";

$tmp = tempnam(sys_get_temp_dir(),'ia').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — intake-api.php DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-fixA-'.date('Ymd-His'), (string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,$bad)!==false) { echo "\n✗ DOGRULAMA BASARISIZ (hala gecersiz model var).\n"; exit; }
echo "  ✓ DOGRULAMA: intake-api.php temiz (".strlen($chk)." bayt)\n";
echo "\n✓ Fall/Intake yolundaki foto->dilekce hatti canlandi (gecerli model).\n";
echo "   (api.php zaten CH_MODEL_FIX ile duzeltilmisti; simdi intake-api de dogru.)\n";
