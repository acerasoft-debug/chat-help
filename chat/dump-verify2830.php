<?php
/** ChatHelp — dump-verify2830 (SADECE OKUR)
 *  #28: foto -> sablon dilekce akisina gercekten gidiyor mu (canli dogrulama).
 *  #30: Bewerbung uctan uca — jobs modal alanlari + doCoverLetter promptu +
 *       e-posta gonderme yolu. Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
function W($src,$needle,$before,$len,$label){
    echo "──────── $label ────────\n";
    $p=strpos($src,$needle);
    if($p===false){ echo "  (yok: '$needle')\n\n"; return; }
    echo "[@$p]\n".substr($src,max(0,$p-$before),$len)."\n---\n\n";
}
$idx=@file_get_contents(__DIR__.'/index.php');
$api=@file_get_contents(__DIR__.'/api.php');

echo "###### #28 FOTO -> SABLON DILEKCE ######\n";
if($idx!==false){
    foreach(['photos:Object.values(window._chPhotos','window._chPhotos','_chDocFiles','syncDocPhotos'] as $k)
        echo "  idx '$k' -> ".substr_count($idx,$k)." kez\n";
    W($idx,'JSON.stringify({docType:dk,docName:doc.n',-60,300,"genDoc fetch body (photos icinde mi?)");
}
if($api!==false){
    foreach(["\$b['photos']","b['photos']","callClaudeVision","AUS HOCHGELADENEN DOKUMENTEN"] as $k)
        echo "  api '$k' -> ".substr_count($api,$k)." kez\n";
    W($api,"b['photos']",-200,450,"doGenerate photos okuma");
}

echo "\n###### #30 BEWERBUNG UCTAN UCA ######\n";
if($api!==false){
    $s=strpos($api,'function doCoverLetter');
    if($s!==false){ $e=strpos($api,"\nfunction ",$s+10); echo "── doCoverLetter TAM ──\n".substr($api,$s,($e===false?3000:min($e-$s,3000)))."\n---\n\n"; }
    else echo "  (doCoverLetter yok)\n";
}
if($idx!==false){
    /* jobs modal alanlari */
    foreach(['jb-kw','jb-loc','jb-cv','jb-lang','jb-mail','jb-eintritt','job.email','mailto:'] as $k)
        echo "  idx '$k' -> ".substr_count($idx,$k)." kez\n";
    W($idx,'function genLetter',0,2200,"genLetter TAM (payload + sonuc modal)");
    W($idx,'Arbeitgeber-E-Mail',-200,400,"isveren e-postasi kullanim yeri");
    W($idx,'mailto:',-250,400,"mailto (kendi e-postasindan gonderme)");
    W($idx,'send-doc.php',-150,350,"send-doc.php kullanimi (varsa)");
}
echo "\n=== BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-verify2830.php ===\n";
