<?php
/* ChatHelp - dump-ping (READ-ONLY) - kanal testi. Hata veremez. */
header('Content-Type: text/plain; charset=UTF-8');
echo "PING OK\n";
echo "PHP ".PHP_VERSION."\n";
echo "index.php: ".(@file_exists(__DIR__.'/index.php')?'VAR':'yok')."\n";
echo "boyut: ".(@filesize(__DIR__.'/index.php'))."\n";
echo "BITTI\n";
