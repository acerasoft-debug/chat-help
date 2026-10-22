<?php
/**
 * ChatHelp — dump-swcheck (SADECE OKUR) — diskteki sw.js'yi aynen basar ve
 *  gercek respondWith CAGRISI var mi kontrol eder (yorumdaki kelime sayilmaz).
 * KULLANIM: pull2.php?key=...&files=dump-swcheck.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f=__DIR__.'/sw.js';
$s=(string)@file_get_contents($f);
echo "=== dump-swcheck — SADECE OKUR ===\n\n";
if($s===''){ exit("sw.js YOK veya bos!\n"); }
echo "boyut: ".strlen($s)." B\n";
echo "CH_SW_SAFE marker: ".(strpos($s,'CH_SW_SAFE')!==false?'VAR ✓':'YOK ✗')."\n";
echo "respondWith( CAGRISI: ".(strpos($s,'respondWith(')!==false?'VAR ✗ (sorunlu surum!)':'YOK ✓ (pass-through)')."\n";
echo "caches.match kullanim: ".(strpos($s,'caches.match')!==false?'VAR ✗':'YOK ✓')."\n\n";
echo "───── sw.js icerigi (aynen) ─────\n".$s."\n─────────────────────────────\n";
echo "\nSONUC: yukarida 3 satir ✓ ise service worker artik hicbir istege dokunmuyor.\n";
