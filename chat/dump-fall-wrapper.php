<?php
/** ChatHelp — dump-fall-wrapper (SADECE OKUR)
 *  $sys promptlarini (@~8395 Q&A, @~13063 belge) SARAN fonksiyonlarin
 *  TAM govdesini + dosyanin routing/dispatch bolumunu gosterir.
 *  Ciktinin TAMAMINI gonder. SIL: rm dump-fall-wrapper.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$f = @file_get_contents(__DIR__.'/fall-api.php');
if ($f===false) exit("fall-api.php okunamadi\n");

/* $sys atamasinin oldugu noktalari saran fonksiyonu bul */
function enclosingFn($src, $pos) {
    // geriye dogru en yakin "function X(" ara
    $fp = strrpos(substr($src,0,$pos), 'function ');
    if ($fp===false) return null;
    $b = strpos($src, '{', $fp);
    if ($b===false || $b>$pos) return null;
    $depth=0; $i=$b; $len=strlen($src);
    for(;$i<$len;$i++){
        if($src[$i]==='{')$depth++;
        elseif($src[$i]==='}'){$depth--; if($depth===0){$i++;break;}}
    }
    if ($i <= $pos) return null; // pos fonksiyonun disinda kaldi, yanlis eslesme
    return substr($src,$fp,$i-$fp);
}

$p1 = strpos($f, '$sys = "Du bist ein erfahrener, einfühlsamer');
$p2 = strpos($f, '$sys = "Du bist ein erfahrener deutscher Rechtsanwalt');

echo "════════ Q&A PROMPTUNU SARAN FONKSIYON (Rueckfragen) ════════\n\n";
if ($p1!==false) { $fn=enclosingFn($f,$p1); echo $fn===null?"bulunamadi\n":$fn."\n"; }

echo "\n════════ BELGE PROMPTUNU SARAN FONKSIYON ════════\n\n";
if ($p2!==false) { $fn=enclosingFn($f,$p2); echo $fn===null?"bulunamadi\n":$fn."\n"; }

echo "\n════════ DOSYA SONU / ROUTING (son 2500 bayt) ════════\n\n";
echo substr($f, -2500)."\n";

echo "\n════════ DOSYA BASI (ilk 1500 bayt — routing/dispatch genelde en ustte olur) ════════\n\n";
echo substr($f, 0, 1500)."\n";

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-fall-wrapper.php ════════\n";
