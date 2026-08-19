<?php
header('Content-Type: text/plain; charset=UTF-8'); error_reporting(E_ERROR|E_PARSE);
$f=__DIR__.'/index.php'; $s=@file_get_contents($f); if($s===false) exit("okunamadi\n");
$n=preg_replace('#<script id="ch-flickdbg">.*?</script>#s','',$s,1);
if($n===$s){ echo "yok\n"; exit; }
@file_put_contents($f,$n); if(function_exists('opcache_reset'))@opcache_reset();
echo "✓ flickdbg kaldirildi\n";
