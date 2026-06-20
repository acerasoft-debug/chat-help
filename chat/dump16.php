<?php
/** ChatHelp — dump16: form-engine.js TAM (staatliche ödeme yaması için) */
header('Content-Type: text/plain; charset=UTF-8');
$fe=@file_get_contents(__DIR__.'/form-engine.js');
if($fe===false) exit("form-engine.js yok\n");
echo "=== form-engine.js (".strlen($fe)." byte) ===\n\n";
echo $fe;
echo "\n\n=== BİTTİ. SİL: rm dump16.php ===\n";
