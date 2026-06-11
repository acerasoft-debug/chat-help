<?php
/**
 * ChatHelp — Ana sayfa + mobil + Anträge estetik iyileştirmeleri
 * --------------------------------------------------------------
 * Tasarım eleştirisindeki somut noktaları düzeltir (sadece CSS, additive):
 *   • Kamera banner üst kırpılması (padding/line-height)
 *   • Kategori kartları daha sıkı (daha fazlası ekrana sığar)
 *   • İkon kutuları daha belirgin/premium (kenarlık + iç gölge + hover)
 *   • Silik alt metin daha okunur
 *   • Anträge kartları premium görünüm
 *   • Mobil için ayrı ince ayarlar
 *
 * KULLANIM: html/chat/ -> chat-help.com/chat/apply-home-style.php
 * İŞ BİTİNCE SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');

$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php bulunamadı.\n"); }

$src   = file_get_contents($file);
$start = $src;
$bak = $file . '.bak-homestyle-' . date('Ymd-His');
file_put_contents($bak, $src);

$style = '<style id="chp-home-style">'
    // ── Kamera banner: üst kırpılmayı önle ──
    . '.wlc-scan{padding:18px;align-items:center}'
    . '.wlc-scan-txt{min-width:0}'
    . '.wlc-scan-t{line-height:1.35;white-space:normal}'
    . '.wlc-scan-s{line-height:1.45}'
    // ── Kategori grid: daha sıkı, daha fazlası ekrana ──
    . '.wlc .wcat-grid{gap:9px;margin:14px 0}'
    . '.wlc .wcat{padding:13px 11px;transition:transform .15s ease,box-shadow .15s ease,border-color .15s ease}'
    . '.wlc .wcat:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(0,0,0,.35);border-color:rgba(255,255,255,.16)}'
    . '.wlc .wcat-t{font-size:12.5px;font-weight:700}'
    // ── Alt metin daha okunur ──
    . '.wlc .wcat-s{color:#9a9ab8 !important;font-size:11px}'
    // ── İkon kutuları premium: kenarlık + iç ışıltı ──
    . '.wlc .wcat-ic{width:38px;height:38px;margin-bottom:9px;border:1px solid rgba(255,255,255,.10);box-shadow:inset 0 1px 7px rgba(255,255,255,.05),0 2px 6px rgba(0,0,0,.25)}'
    // ── Üst boşluklar ──
    . '.wlc{padding-top:14px}'
    . '.sec-hdr{margin-bottom:12px}'
    . '.sec-title{font-weight:800;letter-spacing:-.3px}'
    . '.sec-sub{color:#9a9ab8}'
    // ── Anträge bölümü premium ──
    . '.acard{border-radius:16px;transition:transform .15s ease,border-color .15s ease,box-shadow .15s ease}'
    . '.acard:hover{transform:translateY(-1px);box-shadow:0 8px 22px rgba(0,0,0,.30);border-color:rgba(212,168,74,.25)}'
    . '.acard-ic{border:1px solid rgba(212,168,74,.20);box-shadow:inset 0 1px 7px rgba(255,255,255,.05)}'
    . '.astat{border-radius:14px;transition:border-color .15s ease}'
    . '.astat:hover{border-color:rgba(212,168,74,.25)}'
    . '.ab{border-radius:10px}'
    // ── Mobil için ayrı ince ayarlar ──
    . '@media(max-width:700px){'
    . '.wlc-scan{padding:15px 14px}'
    . '.wlc-scan-ic{width:42px;height:42px}'
    . '.wlc-scan-t{font-size:13.5px;line-height:1.3}'
    . '.wlc-scan-s{font-size:11px}'
    . '.wlc .wcat-grid{gap:8px}'
    . '.wlc .wcat{padding:12px 10px}'
    . '.wlc .wcat-ic{width:36px;height:36px}'
    . '.wlc .wcat-t{font-size:12px}'
    . '.acard-hdr{gap:10px}'
    . '.acard-ic{width:38px;height:38px}'
    . '.ab{padding:8px 11px;font-size:11.5px}'
    . '.ant-stats{gap:7px}'
    . '}'
    . "</style>\n</head>";

$exists = (strpos($src, 'id="chp-home-style"') !== false);
$n = 0;
if (!$exists) {
    $src = preg_replace('/<\/head>/', $style, $src, 1, $n);
}

$changed = ($src !== $start);
if ($changed) { file_put_contents($file, $src); }

echo "ChatHelp — Ana sayfa/mobil/Anträge estetik raporu\n";
echo "==================================================\n";
echo "Yedek: " . basename($bak) . "\n\n";
echo ($n > 0 ? "  ✓ Estetik stiller eklendi  →  $n\n" : "  ✗ Eklenmedi (zaten var veya </head> yok)\n");
echo "\n" . ($changed ? "DURUM: Güncellendi. ✅\n" : "DURUM: Değişiklik yok.\n");
echo "\nTest: ana sayfa → banner kırpılmıyor, kartlar daha sıkı, ikonlar belirgin, alt metin okunur.\n";
echo "Sil:  rm apply-home-style.php\n";
