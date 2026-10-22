<?php
/**
 * ChatHelp — apply-model-fix (CH_MODEL_FIX) — KRITIK: gecersiz Claude modeli
 *  KANIT (dump-critfix): api.php'deki UC Claude fonksiyonu da (callClaude,
 *  callClaudeVision, doPhoto) 'claude-sonnet-4-6' kullaniyor — boyle bir model
 *  YOK; Anthropic API hata donuyor, icerik bos. Sonuc:
 *   • Foto analizi TAMAMEN calismiyor (sadece Claude vision kullaniyor, DeepSeek
 *     yedegi yok) -> ne ceviri/ozet ne de metin geliyor.
 *   • Belge uretiminde Claude her cagrildiginda sessizce basarisiz (chat cogu
 *     zaman DeepSeek kullandigi icin fark edilmiyordu).
 *  DUZELTME: 'claude-sonnet-4-6' -> 'claude-sonnet-4-5' (gecerli, stabil model)
 *  api.php'de 3 gecisin HEPSINDE. Sadece bu string; baska sey degismez.
 * KULLANIM: pull2.php?key=...&files=apply-model-fix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-model-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/api.php';
$src = @file_get_contents($file);
if ($src===false) exit("api.php okunamadi\n");
if (strpos($src,'CH_MODEL_FIX')!==false) exit("Zaten ekli (CH_MODEL_FIX).\n");

$old = "'model'=>'claude-sonnet-4-6'";
$new = "'model'=>'claude-sonnet-4-5'";
$c = substr_count($src,$old);
echo "  bulunan 'claude-sonnet-4-6' gecisi: $c\n";
if ($c < 1) exit("HATA: gecersiz model stringi bulunamadi — api.php DEGISTIRILMEDI.\n");

$src = str_replace($old,$new,$src);
/* iz birak (idempotency) — PHP yorumu olarak, kod akisina dokunmaz */
$marker = "\n/* CH_MODEL_FIX: claude-sonnet-4-6 -> claude-sonnet-4-5 ($c yer) */\n";
$src = preg_replace('/^<\?php/', "<?php".$marker, $src, 1);

echo "  ✓ $c yerde model gecerli hale getirildi (claude-sonnet-4-5)\n";

$tmp = tempnam(sys_get_temp_dir(),'mf').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — api.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
$bk=@file_put_contents($file.'.bak-modelfix-'.date('Ymd-His'), @file_get_contents($file));
if ($bk===false) echo "  ⚠ yedek yazilamadi — devam\n";
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_MODEL_FIX')===false || strpos($chk,'claude-sonnet-4-6')!==false) {
    echo "\n✗ DOGRULAMA: hala eski model var ya da marker yok.\n"; exit;
}
echo "  ✓ DOGRULAMA: CH_MODEL_FIX diskte, 'claude-sonnet-4-6' kalmadi (".strlen($chk)." bayt)\n";
echo "\n✓ CH_MODEL_FIX uygulandi. Foto analizi ve Claude belge uretimi artik calismali.\n";
echo "   Foto denemesi hala bosca donerse: model erisim sorunu olabilir — o zaman\n";
echo "   'claude-sonnet-5' denenir. Once bunu test edin.\n";
