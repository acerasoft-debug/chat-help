<?php
/** ChatHelp — dump-vststate (SADECE OKUR) — yazma kaybi suphesi:
 *  vst-more2 "uygulandi" dedi ama hemen ardindan tr2 ncxBuild bulamadi.
 *  Hicbir apply file_put_contents donusunu kontrol etmiyor — disk kotasi
 *  dolduysa yazim SESSIZCE kaybolur. Bu dump kaniti toplar:
 *  hangi markerlar gercekten diskte + boyutlar + bos alan + .bak envanteri. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$idx=(string)@file_get_contents("$D/index.php");
$api=(string)@file_get_contents("$D/api.php");

echo "index.php: ".strlen($idx)." bayt, mtime ".date('Y-m-d H:i:s',@filemtime("$D/index.php"))."\n";
echo "api.php  : ".strlen($api)." bayt, mtime ".date('Y-m-d H:i:s',@filemtime("$D/api.php"))."\n\n";

echo "── markerlar (index.php) ──\n";
foreach(['CH_STAB_CALM','CH_STAB_FINAL4','CH_VST_MORE','ncxBuild','CH_VST_TR','CH_DEVPLAN_FIX','CH_ZEN_NOPERSIST','CH_MENU_CLEAN','CH_SUGG_FIX','ch-docdev-js'] as $m)
    echo sprintf("  %-18s %d\n",$m,substr_count($idx,$m));
echo "── markerlar (api.php) ──\n";
foreach(['CH_SUGG_FIX','CH_AIROUTE','[[DOC]] format'] as $m)
    echo sprintf("  %-18s %d\n",$m,substr_count($api,$m));

echo "\n── disk ──\n";
echo "  disk_free_space : ".@disk_free_space($D)." bayt (".round(@disk_free_space($D)/1048576,1)." MB)\n";
echo "  disk_total_space: ".@disk_total_space($D)." bayt\n";

echo "\n── .bak envanteri ──\n";
$tot=0;$n=0;
foreach(glob("$D/*.bak-*") as $f){ $s=@filesize($f); $tot+=$s; $n++; }
echo "  $n adet .bak, toplam ".round($tot/1048576,1)." MB\n";
$all=glob("$D/*.bak-*");
usort($all,function($a,$b){ return @filemtime($b)-@filemtime($a); });
foreach(array_slice($all,0,12) as $f)
    echo "   ".basename($f)."  ".round(@filesize($f)/1048576,2)."MB  ".date('m-d H:i',@filemtime($f))."\n";
if(count($all)>12) echo "   … +".(count($all)-12)." dosya daha\n";

echo "\n── yazma testi (kucuk dosya) ──\n";
$t="$D/.ch-writetest-".time();
$w=@file_put_contents($t,str_repeat("x",4096));
echo "  4KB deneme: ".var_export($w,true); if($w!==false){ @unlink($t); echo " ✓"; } echo "\n";

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
