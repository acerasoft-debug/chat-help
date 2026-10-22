<?php
/** ChatHelp — dump-printdoc (SADECE OKUR)
 *  Canli index.php'deki printDoc() fonksiyonunun (chat-PDF ureticisi) TAM
 *  guncel halini gosterir — apply-aichat-pdf-polish anchor'i eslesmedi,
 *  demek ki baska bir yama bu fonksiyonu degistirmis. Ciktinin TAMAMINI
 *  gonder. SIL: rm dump-printdoc.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx = @file_get_contents(__DIR__.'/index.php');
if ($idx===false) exit("index.php okunamadi\n");

echo "════════ printDoc TESHIS ════════\n\n";

/* tum "function printDoc" olusumlarini bul (birden fazla olabilir) */
$off = 0; $n = 0;
while (($p = strpos($idx, 'function printDoc', $off)) !== false) {
    $n++;
    $end = strpos($idx, "\n  }", $p);
    if ($end === false) $end = $p + 1200;
    $snippet = substr($idx, $p, min($end - $p + 4, 2000));
    echo "###### olusum #$n @".$p." ######\n";
    echo $snippet."\n\n";
    $off = $p + 10;
    if ($n > 5) break;
}
if (!$n) echo "printDoc fonksiyonu HIC bulunamadi!\n";

echo "════════ BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-printdoc.php ════════\n";
