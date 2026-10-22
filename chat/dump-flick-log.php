<?php
header('Content-Type: text/plain; charset=UTF-8'); error_reporting(E_ERROR|E_PARSE);
$l=@file_get_contents(__DIR__.'/flick-log.txt');
echo "=== flick-log.txt ===\n".($l!==false?$l:"(bos — sayfa acilip 7sn beklenmedi mi?)")."\n";
