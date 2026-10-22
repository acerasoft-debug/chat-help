<?php
/** ChatHelp — dump-sw-check (SADECE OKUR)
 *  Site sunucudan saglam donuyor ama kullanicinin cihazinda bos —
 *  birincil suphe: PWA service worker eski/bozuk kopyayi gosteriyor.
 *  [1] index.php'de serviceWorker kaydi var mi + baglami
 *  [2] webroot'ta sw/service-worker/workbox dosyalari
 *  [3] manifest baglantisi
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D = __DIR__;
$idx = @file_get_contents($D.'/index.php');
if ($idx===false) exit("index.php okunamadi\n");

echo "════════ [1] serviceWorker KAYITLARI (index.php) ════════\n";
$off=0;$n=0;
while(($p=stripos($idx,'serviceWorker',$off))!==false && $n<10){
    $seg=substr($idx,max(0,$p-80),240); $seg=preg_replace('/\s+/',' ',$seg);
    echo "  @".$p.": ...".$seg."...\n"; $off=$p+10; $n++;
}
if(!$n) echo "  (index.php'de serviceWorker kaydi YOK)\n";

echo "\n════════ [2] SW/CACHE DOSYALARI ════════\n";
foreach ([$D, dirname($D)] as $dir) {
    foreach (glob($dir.'/*.js') as $f) {
        $b=basename($f);
        if (preg_match('/sw|service|worker|workbox|cache/i',$b))
            echo "  $f  ".number_format(filesize($f))." B  ".date('m-d H:i',filemtime($f))."\n";
    }
    foreach (['sw.js','service-worker.js','serviceworker.js'] as $c) {
        if (is_file($dir.'/'.$c)) echo "  BULUNDU: $dir/$c\n";
    }
}
echo "  (listelenmediyse SW dosyasi yok)\n";

echo "\n════════ [3] MANIFEST ════════\n";
if (preg_match('/<link[^>]*manifest[^>]*>/i',$idx,$m)) echo "  ".$m[0]."\n";
else echo "  (manifest linki yok)\n";
foreach ([$D.'/manifest.json', dirname($D).'/manifest.json'] as $mf) {
    if (is_file($mf)) echo "  manifest dosyasi: $mf (".number_format(filesize($mf))." B)\n";
}

echo "\n════════ BITTI. Ciktinin TAMAMINI + gizli-sekme deneme sonucunu gonder. ════════\n";
