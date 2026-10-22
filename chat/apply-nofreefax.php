<?php
/**
 * ChatHelp — apply-nofreefax (CH_NOFREEFAX) — Einzeldokument icin UCRETSIZ FAX kaldirilir.
 *  Politika: fax/Senden sadece PAKET (abo: basic/pro/elite) sahibinin hakkidir.
 *  Paket disi herkes (free-plan + tek-belge alicisi) fax icin ODER.
 *  Tek-belge alicisi metin+PDF'i alir (o hak korunur) ama Senden paywall'a gider.
 *
 *  Teknik: apply-paiddoc'un Senden kapisina actigi '&& !_isPaidCard' delisini kapatir.
 *  Idempotent + fail-safe: eslesme yoksa DEGISTIRMEZ, durumu raporlar.
 * KULLANIM: pull2.php?key=...&files=apply-nofreefax.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-nofreefax BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");

if(strpos($src,'CH_NOFREEFAX')!==false) exit("Zaten uygulandi (CH_NOFREEFAX). Einzeldokument free-fax kapali.\n");

$hole='const _up4=P.plan||_lsU4.plan||\'free\';if((!_tk4||_up4===\'free\') && !_isPaidCard){showPaywall(dk||CD,{});return;}/*CH_PAIDDOC*/';
$fixed='const _up4=P.plan||_lsU4.plan||\'free\';if(!_tk4||_up4===\'free\'){showPaywall(dk||CD,{});return;}/*CH_NOFREEFAX — Einzeldokument free-fax yok; paket disi herkes oder*/';
$orig='const _up4=P.plan||_lsU4.plan||\'free\';if(!_tk4||_up4===\'free\'){showPaywall(dk||CD,{});return;}';

$c=substr_count($src,$hole);
if($c===1){
  $new=str_replace($hole,$fixed,$src);
  echo "  ✓ paiddoc free-fax deligi bulundu ($c) — kapatiliyor.\n";
} elseif($c>1){
  exit("  ✗ delik $c kez bulundu (1 beklenir) — guvenlik icin DEGISTIRILMEDI.\n");
} else {
  /* delik yok: ya paiddoc deploy degil, ya baska surumde */
  if(strpos($src,'CH_PAIDDOC')!==false){
    echo "  ⚠ CH_PAIDDOC ekli ama Senden deligi bu formatta degil.\n";
    /* paiddoc'un _isPaidCard bypass'ini genel olarak ara */
    $alt='(!_tk4||_up4===\'free\') && !_isPaidCard';
    $ca=substr_count($src,$alt);
    if($ca===1){
      $new=str_replace($alt,'(!_tk4||_up4===\'free\')/*CH_NOFREEFAX*/',$src);
      echo "  ✓ alternatif eslesme ile delik kapatildi.\n";
    } else {
      exit("  ✗ Senden bypass bulunamadi (alt=$ca). Dump gerekli — DEGISTIRILMEDI.\n");
    }
  } else {
    /* paiddoc deploy degil: orijinal kapi zaten paket disini paywall'a gonderiyor */
    $co=substr_count($src,$orig);
    echo "  · CH_PAIDDOC ekli DEGIL — paiddoc deploy edilmemis.\n";
    if($co>=1){
      echo "  ✓ Orijinal Senden kapisi zaten paket disini paywall'a gonderiyor ($co).\n";
      echo "    Yani Einzeldokument icin free-fax ZATEN YOK. Ek degisiklik gerekmiyor.\n";
      echo "  (Iz birakmak icin isaret ekleniyor.)\n";
      $new=str_replace($orig,$orig.'/*CH_NOFREEFAX — free-fax yok; paket sart (orijinal kapi korundu)*/',$src);
    } else {
      exit("  ✗ Senden kapisi hic bulunamadi — once dump-payflow calistir.\n");
    }
  }
}

if(!isset($new)) exit("  ✗ Ic hata: yeni icerik olusmadi.\n");

$tmp=tempnam(sys_get_temp_dir(),'nf').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "  ✗ LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }

@file_put_contents($file.'.bak-nofreefax-'.date('Ymd-His'),$src);
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("  ✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_NOFREEFAX')===false) exit("  ✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_NOFREEFAX diskte (".strlen($chk)." B)\n";
echo "  · Einzeldokument: metin + PDF acik, ama Senden(fax) icin PAKET sart.\n";
echo "  · Paket sahipleri (basic/pro/elite): free-fax kotasi degismedi.\n";
