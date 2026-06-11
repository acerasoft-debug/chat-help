<?php
/**
 * ChatHelp — Staatliche Formulare açılmıyor hatası ONARIMI
 * -------------------------------------------------------
 * showStaatKat() -> checkFormQuota() -> getMonth() çağırıyor, ama getMonth()
 * hiçbir yerde tanımlı değil. Bu yüzden bir staatliche kategoriye tıklayınca
 * "getMonth is not defined" hatası oluşuyor ve pencere açılmıyor.
 * Bu script eksik getMonth() fonksiyonunu ekler.
 *
 * KULLANIM: html/chat/ içine yükle -> chat-help.com/chat/fix-staatliche.php aç
 * !!! İŞ BİTİNCE SİL !!!
 */

header('Content-Type: text/plain; charset=UTF-8');

$file = __DIR__ . '/index.php';
if (!file_exists($file)) {
    exit("HATA: index.php bulunamadı.\n");
}

$src   = file_get_contents($file);
$start = $src;
$bak = $file . '.bak-staat-' . date('Ymd-His');
file_put_contents($bak, $src);

$report = [];

// getMonth() zaten tanımlı mı?
$already = (strpos($src, 'function getMonth(') !== false);

if ($already) {
    $report[] = ['getMonth() zaten tanimli', 0];
} else {
    // checkFormQuota tanımının hemen önüne getMonth() ekle
    $anchor = 'function checkFormQuota(){';
    $inject = "function getMonth(){return new Date().getFullYear()+'_'+(new Date().getMonth()+1);}\n"
            . $anchor;
    $n = substr_count($src, $anchor);
    if ($n > 0) {
        $src = str_replace($anchor, $inject, $src);
        $report[] = ['getMonth() eklendi (checkFormQuota onunde)', $n];
    } else {
        // Yedek: ilk <script> içine global ekle
        $n2 = 0;
        $src = preg_replace('/(<script>)/',
            "$1\nfunction getMonth(){return new Date().getFullYear()+'_'+(new Date().getMonth()+1);}\n",
            $src, 1, $n2);
        $report[] = ['getMonth() eklendi (ilk script bloguna - yedek yontem)', $n2];
    }
}

$changed = ($src !== $start);
if ($changed) { file_put_contents($file, $src); }

echo "ChatHelp — Staatliche Formulare onarım raporu\n";
echo "=============================================\n";
echo "Yedek: " . basename($bak) . "\n\n";
foreach ($report as [$label, $n]) {
    echo (($n > 0 || $already) ? "  ✓ " : "  ✗ ") . $label . "  →  " . $n . "\n";
}
echo "\n" . ($changed ? "DURUM: getMonth() eklendi — staatliche pencereler artık açılmalı. ✅\n"
                      : ($already ? "DURUM: Zaten düzeltilmiş.\n" : "DURUM: Kalıp bulunamadı — bana haber ver.\n"));
echo "\nTest: Staatliche → Jobcenter → form listesi açılmalı → Bürgergeld'e tıkla → form açılmalı.\n";
echo "Sil:  rm fix-staatliche.php\n";
