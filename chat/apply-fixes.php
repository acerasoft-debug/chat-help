<?php
/**
 * ChatHelp — index.php otomatik düzeltme scripti
 * ------------------------------------------------
 * Tespit edilen 5 hatayı mevcut index.php'ye güvenli şekilde uygular,
 * önce zaman damgalı bir yedek alır, sonucu raporlar.
 *
 * KULLANIM (iki yoldan biri):
 *   A) Tarayıcı:  bu dosyayı html/chat/ içine yükle →
 *                 chat-help.com/chat/apply-fixes.php adresini bir kez aç.
 *   B) SSH:       html/chat/ klasöründe  ->  php apply-fixes.php
 *
 * !!! İŞ BİTİNCE BU DOSYAYI SUNUCUDAN SİL !!!
 */

header('Content-Type: text/plain; charset=UTF-8');

$file = __DIR__ . '/index.php';
if (!file_exists($file)) {
    exit("HATA: index.php bulunamadı. Bu scripti index.php ile aynı klasöre (html/chat/) koyun.\n");
}

$src  = file_get_contents($file);
$start = $src;

// 1) Yedek al
$bak = $file . '.bak-' . date('Ymd-His');
file_put_contents($bak, $src);

$report = [];
function patch(&$src, $label, $pattern, $replacement, $regex = false) {
    global $report;
    if ($regex) {
        $n = 0;
        $out = preg_replace($pattern, $replacement, $src, -1, $n);
        if ($out !== null) { $src = $out; }
        $report[] = [$label, $n];
    } else {
        $n = substr_count($src, $pattern);
        if ($n > 0) { $src = str_replace($pattern, $replacement, $src); }
        $report[] = [$label, $n];
    }
}

/* ── FIX #3 — Tarif modalı kapatma butonu (.pm-overlay yok → çöküyor) ── */
patch($src, '#3 Tarif modali kapatma',
    'onclick="this.closest(\'.pm-overlay\').remove()"',
    'onclick="cPM()"');

/* ── FIX #4 — Bozuk Unicode kaçışı (\00b7 → ·) ── */
patch($src, '#4 Bozuk unicode (orta nokta)',
    'nur auf Ihrem Server \\00b7 ChatHelp AI',
    'nur auf Ihrem Server · ChatHelp AI');

/* ── FIX #5 — Hedefleyici sorular çıkmıyor (doc.qs yerine doc.q) ── */
patch($src, '#5 Hedefleyici sorular (doc.q)',
    '(doc.qs||[]).forEach(q=>{',
    '(doc.q||doc.qs||[]).forEach(q=>{');

/* ── FIX #1 — Staatliche Formulare: görüntüleme herkese açık ── */
patch($src, '#1 Staatliche Formulare acilmasi',
    '/function openStaatForm\(fileUrl,tier\)\{.*?window\.location\.href=fileUrl;\s*\}/s',
    "function openStaatForm(fileUrl,tier){\n"
    . "  // Goruntuleme herkese acik — PDF kontrolu form-engine.js (exportFormPDF) icinde yapilir.\n"
    . "  if(!fileUrl) return;\n"
    . "  window.location.href = fileUrl;\n"
    . "}",
    true);

/* ── FIX #2 — "Meine Anträge" paneli boş (loadHistory çöküyor) ── */
$antBody = '$1'
    . "\n  </div>\n"
    . "  <div class=\"pnl-scroll\">\n"
    . "    <div class=\"ant-stats\" id=\"ant-stats\" style=\"display:none\">\n"
    . "      <div class=\"astat\"><div class=\"astat-n\" id=\"stat-tot\">0</div><div class=\"astat-l\">Anträge gesamt</div></div>\n"
    . "      <div class=\"astat\"><div class=\"astat-n gr\" id=\"stat-kept\">0</div><div class=\"astat-l\">Gespeichert</div></div>\n"
    . "      <div class=\"astat\"><div class=\"astat-n gold\" id=\"stat-exp\">0</div><div class=\"astat-l\">Läuft bald ab</div></div>\n"
    . "    </div>\n"
    . "    <div class=\"ant-list\" id=\"ant-list\"></div>\n"
    . "  </div>\n"
    . "</div>";
patch($src, '#2 Meine Antraege paneli',
    '/(<div id="ant-count"[^>]*><\/div>)\s*<\/div>\s*<\/div>/s',
    $antBody,
    true);

/* ── ESTETİK — küçük, güvenli, additive ince dokunuşlar ── */
$polish = "<style id=\"ch-polish\">\n"
    . "/* ChatHelp ince estetik dokunuslar (additive) */\n"
    . "*{scrollbar-width:thin;scrollbar-color:#21213a transparent}\n"
    . "button{transition:transform .12s ease,filter .12s ease}\n"
    . "button:active{transform:scale(.97)}\n"
    . ".wcat,.drow,.ccard,.co,.lang-opt,.sb-cat-hdr{transition:transform .15s ease,border-color .15s ease,background .15s ease}\n"
    . "#inp:focus{box-shadow:0 0 0 3px rgba(212,168,74,.10)}\n"
    . ".btn-g:active{transform:scale(.99)}\n"
    . "::selection{background:rgba(212,168,74,.30);color:#fff}\n"
    . "</style>\n</head>";
patch($src, 'Estetik dokunuslar', '</head>', $polish);

/* ── Yaz ── */
$changed = ($src !== $start);
if ($changed) {
    file_put_contents($file, $src);
}

/* ── Rapor ── */
echo "ChatHelp — index.php düzeltme raporu\n";
echo "=====================================\n";
echo "Yedek: " . basename($bak) . "\n\n";
foreach ($report as [$label, $n]) {
    echo ($n > 0 ? "  ✓ " : "  ✗ ") . $label . "  →  " . $n . " yerde uygulandı\n";
}
echo "\n";
echo $changed
    ? "DURUM: index.php güncellendi. ✅\n"
    : "DURUM: Hiçbir değişiklik yapılmadı (muhtemelen zaten düzeltilmiş).\n";
echo "\n";
echo "ÖNEMLİ:\n";
echo "  1) Siteyi test et (sert yenileme: Ctrl+Shift+R).\n";
echo "  2) Sorun olursa yedeği geri yükle:  cp " . basename($bak) . " index.php\n";
echo "  3) Bu scripti SUNUCUDAN SİL:  rm apply-fixes.php\n";
echo "\n";
echo "Not: '✗ 0 yerde' görünen bir satır varsa, o kalıp dosyada bulunamadı\n";
echo "     (kod elle değişmiş olabilir). Bana haber ver, o düzeltmeyi elle uygularız.\n";
