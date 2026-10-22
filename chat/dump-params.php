<?php
/**
 * ChatHelp — dump-params — pull2 ekstra URL parametrelerini script'e geciriyor mu?
 *  TEST: ...&files=dump-params.php&k=AAA&s=BBB  -> asagida AAA/BBB gorunmeli.
 * KULLANIM: pull2.php?key=...&files=dump-params.php&k=AAA&s=BBB
 */
header('Content-Type: text/plain; charset=UTF-8');
echo "== dump-params ==\n\n";
echo "GET k = '".($_GET['k'] ?? '(YOK)')."'\n";
echo "GET s = '".($_GET['s'] ?? '(YOK)')."'\n";
echo "\nTum GET anahtarlari: ".implode(', ', array_keys($_GET))."\n";
echo "QUERY_STRING: ".($_SERVER['QUERY_STRING'] ?? '(yok)')."\n";
echo "\n== BITTI ==\n";
