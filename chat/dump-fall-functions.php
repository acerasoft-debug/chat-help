<?php
/** ChatHelp — dump-fall-functions (SADECE OKUR)
 *  chAI1 ve chAI2 fonksiyonlarinin TAM govdesini gosterir — kesin duzeltme
 *  icin lang parametresinin nereden gelip gelmedigini ve callClaude/
 *  callDeepSeek cagrilarinin gercek imzasini gormek gerekiyor.
 *  Ciktinin TAMAMINI gonder. SIL: rm dump-fall-functions.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f = @file_get_contents(__DIR__.'/fall-api.php');
if ($f===false) exit("fall-api.php okunamadi\n");

function extractFn($src, $name) {
    $p = strpos($src, 'function '.$name);
    if ($p===false) return null;
    $b = strpos($src, '{', $p);
    $depth=0; $i=$b; $len=strlen($src);
    for(;$i<$len;$i++){ if($src[$i]==='{')$depth++; elseif($src[$i]==='}'){$depth--; if($depth===0){$i++;break;}} }
    return substr($src,$p,$i-$p);
}

echo "════════ chAI1 TAM GOVDE ════════\n\n";
$c1 = extractFn($f,'chAI1');
echo $c1===null ? "bulunamadi\n" : $c1."\n";

echo "\n════════ chAI2 TAM GOVDE ════════\n\n";
$c2 = extractFn($f,'chAI2');
echo $c2===null ? "bulunamadi\n" : $c2."\n";

echo "\n════════ 'doFall' veya ana giris noktasi (chAI1/chAI2'yi cagiran) ════════\n\n";
foreach (['doFall','handleFall','function fall(','case \'fall','action===\'fall'] as $needle) {
    $p = stripos($f, $needle);
    if ($p!==false) {
        echo "  '$needle' @".$p.":\n  ...".preg_replace('/\s+/',' ',substr($f,max(0,$p-40),300))."...\n\n";
    }
}

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-fall-functions.php ════════\n";
