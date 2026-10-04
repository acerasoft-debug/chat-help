<?php
/**
 * ChatHelp — apply-pvfix — pv.php'deki BOZUK script kapanisini onarir.
 *
 * HATA: apply-pdfmail, pv.php'ye eklediği bloğu '<\/script>' ile kapatti.
 * PHP tek tirnakli dizede ters bolu oldugu gibi kalir -> HTML'e '<\/script>'
 * yazildi -> script HIC KAPANMADI -> sayfanin tum JS'i oldu.
 * Sonuc: Drucken / PDF / E-Mail dugmelerinin hicbiri calismiyor.
 *
 * DUZELTME: pv.php icindeki '<\/script>' -> '</script>'.
 * Baska hicbir sey degismez. Lint + .bak + dogrulama.
 *
 * KULLANIM: /chat/pull2.php?key=...&files=apply-pvfix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
echo "apply-pvfix BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$dir=__DIR__; $f=$dir.'/pv.php';
$src=@file_get_contents($f);
if($src===false||$src==='') exit("pv.php okunamadi\n");

$bad=substr_count($src,'<\\/script>');
echo "bozuk kapanis sayisi: $bad\n";
if($bad<1){
  echo "· Bozuk kapanis yok. Kontrol:\n";
  echo "  CH_PVMAILUI=".substr_count($src,'CH_PVMAILUI')."  </script>=".substr_count($src,'</script>')."\n";
  exit("DEGISIKLIK YOK.\n");
}

$new=str_replace('<\\/script>','</script>',$src);

/* HTML tutarlilik: acilan ve kapanan script sayisi esit mi */
$open=preg_match_all('/<script\b/i',$new);
$close=substr_count($new,'</script>');
echo "script acilis=$open  kapanis=$close\n";
if($open!==$close) exit("✗ script sayilari esit degil — GUVENLIK GEREGI DEGISIKLIK YOK\n");

$tmp=tempnam(sys_get_temp_dir(),'pvf').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0) exit("\n✗ LINT HATASI — pv.php DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n");

@file_put_contents($f.'.bak-pvfix-'.date('Ymd-His'),$src);
$w=@file_put_contents($f,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
@copy($f,$f.'.GOOD');
if(function_exists('opcache_reset')) @opcache_reset();

$chk=(string)@file_get_contents($f);
echo "\n✓ pv.php onarildi (".strlen($src)." -> ".strlen($chk)." bayt)\n";
echo "  kalan bozuk kapanis: ".substr_count($chk,'<\\/script>')."\n";
echo "  CH_PVMAILUI=".substr_count($chk,'CH_PVMAILUI')."\n";
echo "\nTEST: App'te 'Kostenlos selbst ausdrucken' -> 🖨 Drucken -> acilan sayfada\n";
echo "  '📧 E-Mail' dugmesine bas -> adres kutusu ACILMALI -> Senden.\n";
echo "  (Drucken ve PDF App icinde yine calismaz — o APK ozelligi; E-Mail calisir.)\n";
