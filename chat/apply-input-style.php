<?php
/**
 * ChatHelp — Soru kutularını daha görünür ve estetik yap
 * ------------------------------------------------------
 * Dilekçe/profil formlarındaki yazı alanları çok koyu/silik görünüyordu.
 * Bu script daha açık zemin, belirgin kenarlık, net yazı ve odak parıltısı
 * ekler (sadece stil — yapıya dokunmaz).
 *
 * KULLANIM: html/chat/ içine yükle -> chat-help.com/chat/apply-input-style.php aç
 * !!! İŞ BİTİNCE SİL !!!
 */

header('Content-Type: text/plain; charset=UTF-8');

$file = __DIR__ . '/index.php';
if (!file_exists($file)) {
    exit("HATA: index.php bulunamadı.\n");
}

$src   = file_get_contents($file);
$start = $src;
$bak = $file . '.bak-input-' . date('Ymd-His');
file_put_contents($bak, $src);

$style = '<style id="ch-input-style">'
    // Etiketler daha okunur
    . '.fi label{color:#eaeaff;font-size:11px;letter-spacing:.4px}'
    // Yazı alanları: açık zemin, belirgin kenarlık, net yazı
    . '.fbody .fi input,.fbody .fi textarea,.fbody .fi select,'
    . '.ksec-body .fi input,.fi input,.fi textarea,.fi select{'
    . 'background:#15152a !important;'
    . 'border:1.5px solid rgba(212,168,74,.32) !important;'
    . 'color:#ffffff !important;'
    . 'font-size:15px;'
    . 'padding:13px 14px;'
    . 'border-radius:12px;'
    . 'box-shadow:inset 0 1px 3px rgba(0,0,0,.35);'
    . 'transition:border-color .15s ease,background .15s ease,box-shadow .15s ease}'
    // Placeholder daha görünür
    . '.fi input::placeholder,.fi textarea::placeholder{color:#9090c0 !important;opacity:1}'
    // Odakta altın parıltı
    . '.fi input:focus,.fi textarea:focus,.fi select:focus{'
    . 'border-color:var(--gold) !important;'
    . 'background:#1c1c38 !important;'
    . 'box-shadow:0 0 0 3px rgba(212,168,74,.20) !important}'
    // Tarih alanı: takvim ikonu altın, dokunması kolay
    . '.fi input[type=date]{color-scheme:dark;cursor:pointer;min-height:48px}'
    // Mobilde 16px (iOS zoom engeli) ve biraz daha ferah
    . '@media(max-width:700px){.fi input,.fi textarea,.fi select{font-size:16px;padding:14px}}'
    . "</style>\n</head>";

$exists = substr_count($src, '<style id="ch-input-style">');
$n = 0;
if ($exists === 0) {
    $src = preg_replace('/<\/head>/', $style, $src, 1, $n);
}

$changed = ($src !== $start);
if ($changed) { file_put_contents($file, $src); }

echo "ChatHelp — Soru kutuları estetik raporu\n";
echo "========================================\n";
echo "Yedek: " . basename($bak) . "\n\n";
echo ($n > 0 ? "  ✓ Estetik stil eklendi  →  $n\n" : "  ✗ Eklenmedi (zaten var veya </head> bulunamadı)\n");
echo "\n" . ($changed ? "DURUM: Güncellendi. ✅\n" : "DURUM: Değişiklik yok.\n");
echo "\nTest: dilekçe seç → yazı kutuları artık daha açık ve belirgin olmalı.\n";
echo "Sil:  rm apply-input-style.php\n";
