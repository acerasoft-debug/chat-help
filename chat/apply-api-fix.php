<?php
/**
 * ChatHelp — api.php düzeltme
 *  #1 Hata/uyarı çıktısını sustur (çift define uyarısı JSON'u bozuyordu) — ASIL FIX
 *  #2 CLAUDE_KEY/DEEPSEEK_KEY define'larını guard'la (çift tanım uyarısı bitsin)
 *  #3 'strategie' action'ını match'e ekle (Elite stratejisi kırıktı)
 *
 * KULLANIM: chat-help.com/chat/apply-api-fix.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/api.php';
if (!file_exists($file)) { exit("HATA: api.php yok.\n"); }
$src = file_get_contents($file);
$start = $src;
file_put_contents($file . '.bak-' . date('Ymd-His'), $src);
$report = [];
function rep(&$src, $label, $old, $new) {
    global $report;
    $n = substr_count($src, $old);
    if ($n > 0) $src = str_replace($old, $new, $src);
    $report[] = [$label, $n];
}

/* #1 Hata çıktısını sustur (JSON bozulmasın) — en kritik */
rep($src, '#1 Hata ciktisi susturuldu',
    "header('Content-Type: application/json; charset=UTF-8');",
    "error_reporting(0); @ini_set('display_errors','0');\nheader('Content-Type: application/json; charset=UTF-8');");

/* #2 Çift define -> guard */
rep($src, '#2a CLAUDE_KEY guard',
    "define('CLAUDE_KEY',   'sk-ant",
    "if(!defined('CLAUDE_KEY')) define('CLAUDE_KEY',   'sk-ant");
rep($src, '#2b DEEPSEEK_KEY guard',
    "define('DEEPSEEK_KEY', 'sk-b0",
    "if(!defined('DEEPSEEK_KEY')) define('DEEPSEEK_KEY', 'sk-b0");

/* #3 strategie route */
rep($src, '#3 strategie action eklendi',
    "'detect_location' => doLocation(),",
    "'detect_location' => doLocation(),\n    'strategie'       => doStrategie(\$body),");

$changed = ($src !== $start);
if ($changed) file_put_contents($file, $src);
echo "ChatHelp — api.php fix raporu\n=============================\n\n";
foreach ($report as [$l,$n]) echo ($n>0?"  ✓ ":"  ✗ ").$l."  →  $n\n";
echo "\n".($changed?"DURUM: api.php güncellendi. ✅\n":"DURUM: Değişiklik yok.\n");
echo "\nSONRA: opcache-reset.php. SİL: rm apply-api-fix.php\n";
echo "Test: bir dilekçe oluştur — artık AI cevap vermeli (önce 'Document generation failed' diyorduysa).\n";
