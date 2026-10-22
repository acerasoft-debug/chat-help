<?php
/**
 * ChatHelp — apply-bak-cleanup (CH_BAK_CLEANUP) — eski .bak yedeklerini temizle
 *  Her apply, index.php'nin ~1,6 MB'lik bir .bak kopyasini birakiyor; onlarcasi
 *  birikti ve disk kotasini yiyor (dolu kota = SESSIZ yazma kaybi — vst-more2
 *  "uygulandi" deyip diske yazamamis olabilir). Bu betik her hedef dosyanin
 *  EN YENI 2 yedegini tutar, gerisini siler; oncesi/sonrasi bos alani yazar.
 *  index.php/api.php'ye DOKUNMAZ — yalniz *.bak-* dosyalarini siler.
 * KULLANIM: pull2.php?key=...&files=apply-bak-cleanup.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-bak-cleanup BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$D=__DIR__;

echo "once: bos alan ".round(@disk_free_space($D)/1048576,1)." MB\n";

/* prefix -> [dosyalar] grupla: 'index.php.bak-zen-...' => grup 'index.php' */
$groups=[];
foreach (glob("$D/*.bak-*") as $f) {
    $b=basename($f);
    $pre=substr($b,0,strpos($b,'.bak-'));
    $groups[$pre][]=$f;
}
$freed=0;$kept=0;$del=0;
foreach ($groups as $pre=>$files) {
    usort($files,function($a,$b){ return @filemtime($b)-@filemtime($a); });
    foreach ($files as $i=>$f) {
        if ($i<2) { $kept++; continue; }   /* en yeni 2 kalir */
        $s=@filesize($f);
        if (@unlink($f)) { $freed+=$s; $del++; }
    }
}
echo "silinen: $del dosya (".round($freed/1048576,1)." MB) — tutulan: $kept (grup basina en yeni 2)\n";
echo "sonra: bos alan ".round(@disk_free_space($D)/1048576,1)." MB\n";
echo "\n✓ CH_BAK_CLEANUP bitti. index.php/api.php'ye dokunulmadi.\n";
