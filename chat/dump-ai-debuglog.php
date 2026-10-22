<?php
/** ChatHelp — dump-ai-debuglog (SADECE OKUR) — HER IKI log birden:
 *  chat-debug.log (ana sohbet) + fall-debug.log (Dava Ac).
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
foreach ([['ANA SOHBET', __DIR__.'/data/chat-debug.log'],
          ['DAVA AC (Fall)', __DIR__.'/data/fall-debug.log']] as $pair) {
    list($ttl,$lf)=$pair;
    echo "════════ $ttl — ".basename($lf)." ════════\n\n";
    if (!file_exists($lf)) { echo "(log yok — bu akis hic tetiklenmemis)\n\n"; continue; }
    $c = file_get_contents($lf);
    echo "boyut: ".strlen($c)." bayt\n".substr($c,-3000)."\n\n";
}
echo "════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
