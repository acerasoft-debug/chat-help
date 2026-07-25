<?php
/**
 * ChatHelp — apply-wvlog2 — chPrintDoc App dallarina sessiz beacon.
 *  'Kostenlos selbst ausdrucken' akisi makePDF'ten degil chPrintDoc'tan
 *  geciyor (doFree -> proceed -> CTX.orig = chPrintDoc). CH_BRANCHLOG sadece
 *  makePDF'teydi; bu betik _isWv2 (3 yer) ve _isWv3 (1 yer) kontrollerinin
 *  hemen ardina da beacon koyar. Boylece CH_ISWV_GLOBAL sonrasi App dalinin
 *  gercekten calisip calismadigi log'dan kesin gorulur.
 * KULLANIM: /chat/pull2.php?key=...&files=apply-wvlog2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
echo "apply-wvlog2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_CPDLOG')!==false) exit("Zaten ekli (CH_CPDLOG) — DEGISIKLIK YOK.\n");

$i=0;
$new=preg_replace_callback(
  '/(var (_isWv2|_isWv3)=false;\s*try\{\s*\2=\(typeof isWV===.function.\)&&isWV\(\);\s*\}catch\(e0\)\{\})/',
  function($m) use (&$i){
    $i++;
    return $m[1].' /*CH_CPDLOG*/try{new Image().src="chlog9k1.php?k=chl_2607&t="+(new Date().getTime())+"&d=CPD'.$i.'+"+'.$m[2].';}catch(_ec){}';
  },
  $src
);
if($new===null) exit("preg hatasi\n");
if($i<1) exit("Anchor bulunamadi (_isWv2/_isWv3) — DEGISIKLIK YOK.\n");

$tmp=tempnam(sys_get_temp_dir(),'wl2').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0) exit("\n✗ LINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n");

@file_put_contents($file.'.bak-wvlog2-'.date('Ymd-His'),$src);
@file_put_contents($file,$new);
if(function_exists('opcache_reset')) @opcache_reset();
echo "  ✓ CH_CPDLOG eklendi ($i yer)\n";
echo "\nTEST: App kapat-ac -> 'Kostenlos selbst ausdrucken' -> sonra log oku (z'yi degistir):\n";
echo "  curl -s \"https://chat-help.com/chat/chlog9k1.php?k=chl_2607&read=1&z=70001\"\n";
echo "  'CPD1+true' / 'CPD2+true' gorursen -> App dali ARTIK CALISIYOR (kalan is indirme).\n";
echo "  'CPD1+false' gorursen -> isWV hala false donuyor (baska sebep).\n";
