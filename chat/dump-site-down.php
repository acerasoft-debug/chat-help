<?php
/** ChatHelp — dump-site-down (SADECE OKUR) — SITE ACILMIYOR teshisi
 *  [1] index/api/fall-api lint  [2] siteye sunucu icinden HTTP istegi
 *  [3] PHP hata gunlugu  [4] son yedekler  [5] disk
 *  Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
@set_time_limit(60);
$D = __DIR__;

echo "════════ [1] LINT ════════\n";
foreach (['index.php','api.php','fall-api.php'] as $f) {
    $lo=[];$rc=0; exec('php -l '.escapeshellarg($D.'/'.$f).' 2>&1',$lo,$rc);
    echo ($rc===0?"  ✓ $f OK":"  ✗✗ $f HATALI: ".implode(' | ',$lo))."\n";
}

echo "\n════════ [2] SUNUCU ICINDEN SITE ISTEGI ════════\n";
$ch = curl_init('https://chat-help.com/chat/');
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>25,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_FOLLOWLOCATION=>true]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);
echo "HTTP: $code".($err?" · cURL: $err":"")."\n";
echo "govde boyutu: ".strlen((string)$res)." bayt\n";
echo "ilk 300: ".substr(preg_replace('/\s+/',' ',(string)$res),0,300)."\n";
echo "son 200: ".substr(preg_replace('/\s+/',' ',(string)$res),-200)."\n";

echo "\n════════ [3] PHP HATA GUNLUGU ════════\n";
$found=false;
foreach ([$D.'/error_log', dirname($D).'/error_log', ini_get('error_log')] as $el) {
    if ($el && @is_file($el)) { $found=true; echo "--- $el (son 2000 bayt) ---\n".substr(@file_get_contents($el),-2000)."\n"; break; }
}
if(!$found) echo "(erisilebilir error_log yok)\n";

echo "\n════════ [4] SON YEDEKLER (en yeni 10) ════════\n";
$baks = glob($D.'/index.php.bak-*');
usort($baks, fn($a,$b)=>filemtime($b)-filemtime($a));
foreach (array_slice($baks,0,10) as $b) echo "  ".date('m-d H:i:s',filemtime($b))."  ".basename($b)."  ".number_format(filesize($b))." B\n";
echo "index.php su an: ".number_format(@filesize($D.'/index.php'))." B · ".date('m-d H:i:s',@filemtime($D.'/index.php'))."\n";

echo "\n════════ [5] DISK ════════\n";
$df=@disk_free_space($D); echo $df!==false ? "bos: ".round($df/1048576)." MB\n" : "(okunamadi)\n";

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
