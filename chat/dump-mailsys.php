<?php
/** ChatHelp — dump-mailsys: send-doc.php'nin GERCEK posta gonderme mekanizmasi (attachment destegi var mi?). SADECE OKUR. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f = @file_get_contents(__DIR__.'/send-doc.php');
if ($f === false) { echo "send-doc.php bulunamadi/okunamadi\n"; exit; }
echo "dump-mailsys (send-doc.php=".strlen($f)." bayt)\n\n";
echo "###### TAM ICERIK ######\n";
echo $f . "\n";

echo "\n###### sendMail() fonksiyonu (varsa, ayri dosyada) ######\n";
foreach (['mail-lib.php','mailer.php','smtp.php'] as $cand) {
    $p = __DIR__.'/'.$cand;
    if (file_exists($p)) { echo "\n--- $cand ---\n" . file_get_contents($p) . "\n"; }
}
$api = @file_get_contents(__DIR__.'/api.php');
if ($api !== false) {
    $p = strpos($api, 'function sendMail');
    if ($p !== false) {
        $b=strpos($api,'{',$p); $d=0;$i=$b;$L=strlen($api);
        for(;$i<$L;$i++){ $c=$api[$i]; if($c==='{')$d++; elseif($c==='}'){ $d--; if(!$d){ $i++; break; } } }
        echo "\n--- api.php icindeki sendMail() ---\n" . substr($api,$p,min($i-$p,2000)) . "\n";
    }
}
echo "\n=== BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-mailsys.php ===\n";
