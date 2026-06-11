<?php
/**
 * ChatHelp — Konto paneli ONARIM scripti
 * --------------------------------------
 * apply-fixes.php'deki #2 düzeltmesi, "Meine Anträge" panelini eklerken
 * üst barı kapatan bir </div> eksik bıraktı. Bu yüzden #pnl-konto yanlışlıkla
 * #pnl-ant'ın içine girip kayboldu. Bu script o eksik </div>'i geri ekler.
 *
 * KULLANIM:
 *   A) Tarayıcı:  bu dosyayı html/chat/ içine yükle →
 *                 chat-help.com/chat/fix-konto.php adresini bir kez aç.
 *   B) SSH:       html/chat/ klasöründe  ->  php fix-konto.php
 *
 * !!! İŞ BİTİNCE BU DOSYAYI SUNUCUDAN SİL !!!
 */

header('Content-Type: text/plain; charset=UTF-8');

$file = __DIR__ . '/index.php';
if (!file_exists($file)) {
    exit("HATA: index.php bulunamadı. Bu scripti html/chat/ klasörüne koyun.\n");
}

$src   = file_get_contents($file);
$start = $src;

// Yedek al
$bak = $file . '.bak-konto-' . date('Ymd-His');
file_put_contents($bak, $src);

// Onarım: ant-count div'i hemen ardından pnl-scroll geliyorsa (bozuk durum),
// aralarına üst barı kapatan </div>'i ekle. Zaten doğruysa eşleşmez (idempotent).
$n = 0;
$src = preg_replace(
    '/(<div id="ant-count"[^>]*><\/div>)\s*(<div class="pnl-scroll">)/s',
    "$1\n  </div>\n  $2",
    $src,
    -1,
    $n
);

// Güvenlik kontrolü: <div ... id="pnl-konto"> ile <div ... id="pnl-ant"> sayısı
$kontoCount = substr_count($src, 'id="pnl-konto"');
$antCount   = substr_count($src, 'id="pnl-ant"');

$changed = ($src !== $start);
if ($changed) {
    file_put_contents($file, $src);
}

echo "ChatHelp — Konto paneli onarım raporu\n";
echo "======================================\n";
echo "Yedek: " . basename($bak) . "\n\n";

if ($n > 0) {
    echo "  ✓ Eksik </div> eklendi  →  $n yerde onarıldı\n";
    echo "\nDURUM: Konto paneli onarıldı. ✅\n";
} else {
    echo "  ✗ Bozuk yapı bulunamadı (zaten onarılmış olabilir veya yapı farklı)\n";
    echo "\nDURUM: Değişiklik yapılmadı.\n";
}

echo "\nKontrol:  pnl-konto = $kontoCount adet, pnl-ant = $antCount adet";
echo (($kontoCount === 1 && $antCount === 1) ? "  (beklenen ✓)\n" : "  (BEKLENMEDİK — bana haber ver)\n");

echo "\nÖNEMLİ:\n";
echo "  1) Siteyi test et (Ctrl+Shift+R). Konto bölümü geri geldi mi?\n";
echo "  2) Sorun olursa yedek:  cp " . basename($bak) . " index.php\n";
echo "  3) Bu scripti SUNUCUDAN SİL:  rm fix-konto.php\n";
