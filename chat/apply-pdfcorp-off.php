<?php
/**
 * ChatHelp — apply-pdfcorp-off — CH_PDF_CORP override'ini GERI ALIR.
 *  Amac: makePDF override'i "ausdrucken"/PDF print yolunu bozduysa, tek komutla
 *  onceki calisir haline don. SADECE <script id="ch-pdf-corporate">...</script>
 *  blogu cikarilir; index.php'nin geri kalani AYNEN kalir. Idempotent + lint + bak.
 * KULLANIM: /chat/pull2.php?key=...&files=apply-pdfcorp-off.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-pdfcorp-off BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_PDF_CORP')===false && strpos($src,'ch-pdf-corporate')===false)
  exit("Zaten yok (CH_PDF_CORP bulunmadi) — DEGISIKLIK YOK.\n");

/* Blogu bul: <script id="ch-pdf-corporate"> ... ilk </script> (blok kendi ic
   scriptini <\/scr'+'ipt> diye kacisli yazar, o yuzden ilk gercek </script> = blok sonu). */
$start = strpos($src,'<script id="ch-pdf-corporate">');
if ($start===false) exit("HATA: blok baslangici bulunamadi — DEGISIKLIK YOK.\n");
$end = stripos($src,'</script>',$start);
if ($end===false) exit("HATA: blok sonu (</script>) bulunamadi — DEGISIKLIK YOK.\n");
$end += strlen('</script>');
/* varsa hemen onundeki/sonundaki tek newline'i de temizle */
$before = substr($src,0,$start);
$after  = substr($src,$end);
$before = rtrim($before,"\n")."\n";
$after  = ltrim($after,"\n");
$new = $before.$after;

if (strpos($new,'CH_PDF_CORP')!==false) {
  echo "  ! Uyari: CH_PDF_CORP hala var (birden fazla enjeksiyon olabilir), tekrar calistir.\n";
}

$tmp = tempnam(sys_get_temp_dir(),'po').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }

@file_put_contents($file.'.bak-pdfcorpoff-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
if (function_exists('opcache_reset')) @opcache_reset();

$chk=(string)@file_get_contents($file);
$still = strpos($chk,'ch-pdf-corporate')!==false;
echo "  ✓ blok cikarildi (".strlen($src)." -> ".strlen($chk)." bayt)\n";
echo $still ? "  ! DIKKAT: hala kalinti var — tekrar calistir.\n"
            : "  ✓ DOGRULAMA: CH_PDF_CORP tamamen kaldirildi. makePDF orijinal haline dondu.\n";
echo "\n✓ 'ausdrucken'/PDF yolu onceki calisir durumuna dondu. App'te tekrar dene.\n";
