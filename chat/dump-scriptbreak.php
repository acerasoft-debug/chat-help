<?php
/** ChatHelp — dump-scriptbreak: <script> etiketlerinin dengesini kontrol eder,
 *  "Italiano" (dil secici) sonrasindaki HAM kaynak kodu gosterir (kirilma noktasi burada). SADECE OKUR. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$idx=@file_get_contents(__DIR__.'/index.php');
if($idx===false) exit("okunamadi\n");
echo "dump-scriptbreak (idx=".strlen($idx)." bayt)\n\n";

/* ── 1) genel <script sayimi ── */
$opens = substr_count($idx, '<script');
$closes = substr_count($idx, '</script>');
echo "###### <script sayimi ######\n";
echo "  <script acilis sayisi: $opens\n";
echo "  </script> kapanis sayisi: $closes\n";
echo "  fark: " . ($opens - $closes) . " (0 olmali)\n";

/* ── 2) her <script ... id="..."> etiketini offset+id ile listele, kapanisiyla eslestir ── */
echo "\n###### TUM <script> etiketleri (offset, id, ilk 60 char) ######\n";
$pos = 0; $i = 0;
while (($p = strpos($idx, '<script', $pos)) !== false) {
    $tagEnd = strpos($idx, '>', $p);
    if ($tagEnd === false) break;
    $tag = substr($idx, $p, $tagEnd - $p + 1);
    $close = strpos($idx, '</script>', $tagEnd);
    $len = $close !== false ? $close - $tagEnd : -1;
    echo "  #".($i++)." @$p  tag=".trim($tag)."  icerik_uzunluk=".$len."  kapanis_offset=".($close===false?'YOK':$close)."\n";
    $pos = $tagEnd + 1;
    if ($i > 200) { echo "  ...(200'den fazla, kesildi)\n"; break; }
}

/* ── 3) 'Italiano' civari HAM kaynak (kirilma noktasi burada olabilir) ── */
echo "\n###### 'Italiano' SONRASI 3000 bayt (HAM kaynak) ######\n";
$p = strrpos($idx, 'Italiano');
if ($p !== false) {
    echo substr($idx, $p, 3000) . "\n";
} else { echo "(Italiano bulunamadi)\n"; }

echo "\n=== BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-scriptbreak.php ===\n";
