<?php
/** ChatHelp — dump-healthcheck2 (SADECE OKUR) — KOMPLE SITE SAGLIK DENETIMI
 *  Tum canli PHP dosyalarini lint eder, yama (CH_) butunlugunu + cakismalari,
 *  kritik fonksiyonlari, script/style blok dengesini, bilinen hata desenlerini
 *  ve render edilmis ciktinin yapisini tarar. Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
@set_time_limit(120);
$D = __DIR__;

echo "════════ ChatHelp SAGLIK DENETIMI ".date('Y-m-d H:i')." ════════\n\n";

/* ── 1) PHP LINT (tum canli dosyalar) ── */
echo "###### 1) PHP LINT ######\n";
$phpFiles = ['index.php','api.php','fall-api.php','intake-api.php','auth.php','ver.php','pull-updates.php','db.php','config.php','send-doc.php','geo.php'];
$lintErr = 0;
foreach ($phpFiles as $f) {
    $p = $D.'/'.$f;
    if (!file_exists($p)) { echo "  – $f (yok)\n"; continue; }
    $lo=[]; $rc=0; exec('php -l '.escapeshellarg($p).' 2>&1', $lo, $rc);
    if ($rc===0) echo "  ✓ $f OK\n";
    else { echo "  ✗✗ $f LINT HATASI: ".implode(' | ',$lo)."\n"; $lintErr++; }
}
echo $lintErr? "\n  ⚠ $lintErr dosyada PHP hatasi!\n" : "\n  ✓ Tum PHP dosyalari temiz.\n";

$idx = @file_get_contents($D.'/index.php');
$api = @file_get_contents($D.'/api.php');

/* ── 2) SCRIPT/STYLE/TAG DENGESI ── */
echo "\n###### 2) HTML/SCRIPT DENGESI (index.php) ######\n";
if ($idx!==false) {
    $so = substr_count($idx,'<script'); $sc = substr_count($idx,'</script>');
    $yo = substr_count($idx,'<style');  $yc = substr_count($idx,'</style>');
    $bo = substr_count($idx,'<body');   $bc = substr_count($idx,'</body>');
    echo "  <script>: $so acik / $sc kapali ".($so===$sc?"✓":"✗✗ DENGESIZ!")."\n";
    echo "  <style>:  $yo acik / $yc kapali ".($yo===$yc?"✓":"✗✗ DENGESIZ!")."\n";
    echo "  <body>:   $bo acik / $bc kapali ".($bc>=1?"✓":"✗")."\n";
    echo "  boyut: ".number_format(strlen($idx))." bayt\n";
    /* her id'li script blogunun {}()[] net dengesi (kaba isaret) */
    echo "\n  -- id'li <script> bloklari net parantez dengesi --\n";
    if (preg_match_all('/<script id="([^"]+)">(.*?)<\/script>/s', $idx, $m, PREG_SET_ORDER)) {
        foreach ($m as $blk) {
            $id=$blk[1]; $b=$blk[2];
            $cur=substr_count($b,'{')-substr_count($b,'}');
            $par=substr_count($b,'(')-substr_count($b,')');
            $sq=substr_count($b,'[')-substr_count($b,']');
            $flag=($cur!==0||$par!==0||$sq!==0);
            if($flag) echo "    ⚠ $id  {}=".$cur." ()=".$par." []=".$sq." (string/regex icindeki parantezler yaniltabilir — sadece isaret)\n";
        }
        echo "    (yukarida listelenmeyen bloklar net dengede)\n";
    }
} else echo "  index.php okunamadi\n";

/* ── 3) CH_ MARKER ENVANTERI + CAKISMA ── */
echo "\n###### 3) YAMA (CH_) ENVANTERI ######\n";
if ($idx!==false) {
    preg_match_all('/CH_[A-Z0-9_]+/', $idx, $mm);
    $counts = array_count_values($mm[0]);
    ksort($counts);
    $dup=0;
    foreach ($counts as $k=>$v) {
        /* bir marker'in 1-3 kez gecmesi normal (tanim+kullanim); 6+ suphe */
        $s = ($v>=8)?" ⚠ COK FAZLA":"";
        if($v>=8)$dup++;
        echo "  ".str_pad($k,26)." x$v$s\n";
    }
    echo "  Toplam benzersiz marker: ".count($counts)."\n";
}

/* ── 4) KRITIK FONKSIYON/OGE VARLIGI ── */
echo "\n###### 4) KRITIK OGELER ######\n";
$idxNeed = ['function gP(','function showResult','function applyCC','function setCountry','openFall','function genDoc','id="sb"','id="pnl-chat"','window.chDlGate','window.chVSF','window.chOpenVDos','ch-menufix2-js','ch-cachebust-js'];
foreach ($idxNeed as $k) echo "  index ".str_pad("'$k'",34)." ".($idx!==false&&strpos($idx,$k)!==false?"✓":"✗✗ EKSIK")."\n";
$apiNeed = ['function doGenerate','function doAiChat','function doKeep','function doHistory','function doSaveDoc','function doCoverLetter'];
foreach ($apiNeed as $k) echo "  api   ".str_pad("'$k'",34)." ".($api!==false&&strpos($api,$k)!==false?"✓":"✗✗ EKSIK")."\n";

/* ── 5) BILINEN HATA DESENLERI ── */
echo "\n###### 5) BILINEN HATA DESENLERI ######\n";
if ($idx!==false) {
    $chk = [
      "cift </body></html> (JS print-string disinda)" => (substr_count($idx,'</body>')>3),
      "'undefined' etiketi (katalog bug)" => (strpos($idx,'>undefined<')!==false),
      "eski enforceOrder AKTIF (menufix2 sonrasi return olmali)" => (strpos($idx,'function enforceOrder(){ return;')===false && strpos($idx,'function enforceOrder(){')!==false),
      "Weitere Angaben SABIT Almanca (lang-leak sonrasi olmamali)" => (strpos($idx,'data-de="Weitere Angaben')!==false),
      "leaked <?php script ici (render sorunu)" => false,
    ];
    foreach ($chk as $label=>$bad) echo "  ".($bad?"⚠ SORUN":"✓ temiz").": $label\n";
}

/* ── 6) YEDEK BIRIKIMI ── */
echo "\n###### 6) YEDEK/DISK ######\n";
$baks = glob($D.'/*.bak-*'); $tot=0; foreach($baks as $b)$tot+=filesize($b);
echo "  .bak-* dosya: ".count($baks)." · ".round($tot/1048576,1)." MB\n";
$dumps = glob($D.'/dump-*.php');
echo "  dump-*.php: ".count($dumps)."\n";
if (function_exists('disk_free_space')){ $df=@disk_free_space($D); if($df) echo "  Bos disk: ".round($df/1073741824,2)." GB\n"; }

echo "\n════════ DENETIM BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-healthcheck2.php ════════\n";
