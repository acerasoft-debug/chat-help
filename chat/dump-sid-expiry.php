<?php
/** ChatHelp — dump-sid-expiry (SADECE OKUR)
 *  Meine Antraege kaliciligi: ch_sid cerez omru + belge temizleme/expiry mantigi
 *  + doHistory (keep nasil ele aliniyor). Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$api=@file_get_contents(__DIR__.'/api.php');
if($api===false) exit("api.php okunamadi\n");
function W($src,$needle,$before,$len,$label){
    echo "──────── $label ────────\n";
    $p=strpos($src,$needle);
    if($p===false){ echo "  (yok: '$needle')\n\n"; return; }
    echo "[@$p]\n".substr($src,max(0,$p-$before),$len)."\n---\n\n";
}
W($api,"setcookie('ch_sid'",-260,520,"ch_sid cerez (omur dahil TAM)");
W($api,'function doHistory',0,1400,"doHistory TAM (expiry/keep mantigi)");
W($api,'expiresIn',-300,500,"expiresIn hesabi");
W($api,'unlink',-300,500,"unlink (otomatik silme)");
W($api,'glob(',-160,400,"glob (dosya tarama)");
W($api,'86400',-200,400,"24h sabiti (varsa)");
W($api,"'history'",-120,300,"history dispatch");
echo "\n=== BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-sid-expiry.php ===\n";
