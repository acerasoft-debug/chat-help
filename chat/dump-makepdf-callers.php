<?php
/** ChatHelp — dump-makepdf-callers (SADECE OKUR)
 *  makePDF( baglamlari — printDoc() disinda baska cagiran var mi? jsPDF
 *  yolunu tamamen devre disi birakmadan once etki alanini olcmek icin.
 *  Ciktinin TAMAMINI gonder. SIL: rm dump-makepdf-callers.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx = @file_get_contents(__DIR__.'/index.php');
if ($idx===false) exit("index.php okunamadi\n");
echo "════════ makePDF( COK CAGRI BAGLAMLARI ════════\n\n";
$off=0;$n=0;
while(($p=strpos($idx,'makePDF(',$off))!==false && $n<40){
    $seg=substr($idx,max(0,$p-70),140); $seg=preg_replace('/\s+/',' ',$seg);
    echo "  @".$p.": ...".$seg."...\n";
    $off=$p+8; $n++;
}
echo "\nToplam 'makePDF(' gecisi: ".substr_count($idx,'makePDF(')."\n";
echo "\n════════ BITTI ════════\n";
