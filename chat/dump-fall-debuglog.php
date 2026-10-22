<?php
/** ChatHelp — dump-fall-debuglog (SADECE OKUR) — fall-debug.log iceriji.
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$lf = __DIR__.'/data/fall-debug.log';
echo "════════ fall-debug.log ════════\n\n";
if (!file_exists($lf)) { echo "(log dosyasi henuz yok — once apply-fall-debuglog.php deploy edilmeli ve sitede Fall akisi bir kez denenmelidir)\n"; exit; }
$c = file_get_contents($lf);
echo "boyut: ".strlen($c)." bayt\n\n";
echo substr($c, -4000)."\n";
echo "\n════════ BITTI ════════\n";
