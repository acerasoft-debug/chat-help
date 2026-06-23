<?php
/**
 * ChatHelp — Belge DİLİ ülkeye göre (FR belgeler Fransızca) — backend yaması
 * --------------------------------------------------------------------------
 *  Sorun: doGenerate, arayüz dili Almanca değilse belgeyi ZORLA Almanca yazdırıyordu
 *         ("Das offizielle Dokument bleibt vollständig auf DEUTSCH ... deutschen Stelle").
 *         -> Fransız kullanıcı için yanlış (Fransız makamına Almanca dilekçe).
 *  Çözüm: Resmî belge dili = ülkenin dili (DE/AT/CH=de, FR/BE/LU=fr, IT=it, ES=es, NL=nl).
 *         Açıklama bölümü yalnız kullanıcının dili belge dilinden FARKLIYSA eklenir.
 *         -> FR kullanıcı (dil fr): belge tamamen Fransızca, gereksiz tekrar yok.
 *         -> Almanca davranış aynen korunur.
 *
 *  Güvenli: değişiklik geçici dosyaya yazılır, php -l ile DENETLENİR, ancak temizse api.php'ye uygulanır.
 *
 * KULLANIM: chat-help.com/chat/apply-france-backend.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
echo "ChatHelp — apply-france-backend BAŞLADI ✓ (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/api.php';
if (!file_exists($file)) { exit("HATA: api.php yok.\n"); }
$src = file_get_contents($file);

if (strpos($src, 'CH_FR_DOCLANG') !== false) {
    exit("Zaten uygulanmış (CH_FR_DOCLANG). Değişiklik yok.\n");
}

/* Dump-1'den BİREBİR alınan hedef cümle (Almanca zorlaması) */
$target = "if(isset(\$LN[\$ulang])){ \$nativeNote=' WICHTIG: Das offizielle Dokument bleibt vollständig auf DEUTSCH (es wird bei einer deutschen Stelle eingereicht).";

if (strpos($src, $target) === false) {
    echo "✗ Hedef cümle bulunamadı (api.php farklı olabilir). Hiçbir şey değiştirilmedi.\n";
    echo "  Aranan: if(isset(\$LN[\$ulang])){ \$nativeNote=' WICHTIG: Das offizielle Dokument bleibt vollständig auf DEUTSCH ...\n";
    exit;
}

/* Ülkeye göre resmî belge dili + açıklama yalnız dil farklıysa */
$replacement =
  "\$__ll=['DE'=>'de','AT'=>'de','CH'=>'de','FR'=>'fr','BE'=>'fr','LU'=>'fr','IT'=>'it','ES'=>'es','NL'=>'nl'][\$country]??'de'; /*CH_FR_DOCLANG*/ "
. "\$__lln=['de'=>'DEUTSCH','fr'=>'FRANZÖSISCH','it'=>'ITALIENISCH','es'=>'SPANISCH','nl'=>'NIEDERLÄNDISCH'][\$__ll]??'DEUTSCH'; "
. "if(isset(\$LN[\$ulang]) && \$ulang!==\$__ll){ \$nativeNote=' WICHTIG: Das offizielle Dokument bleibt vollständig in der jeweiligen Landessprache ('.\$__lln.').";

$new = str_replace($target, $replacement, $src);
$count = substr_count($src, $target);

if ($new === $src) { exit("✗ Değişiklik uygulanamadı.\n"); }

/* Yedek */
$bak = $file . '.bak-frdl-' . date('Ymd-His');
file_put_contents($bak, $src);

/* Güvenlik: geçici dosyada php -l denetimi */
$tmp = $file . '.tmp-frdl';
file_put_contents($tmp, $new);
$out = []; $code = 0;
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
@unlink($tmp);

if ($code !== 0) {
    echo "✗ LINT HATASI — api.php DEĞİŞTİRİLMEDİ (güvenli):\n" . implode("\n", $out) . "\n";
    echo "Yedek duruyor: " . basename($bak) . "\n";
    exit;
}

file_put_contents($file, $new);

echo "✓ Belge dili ülkeye göre ayarlandı (CH_FR_DOCLANG).  → $count yer\n";
echo "  • FR/BE/LU  -> belge Fransızca\n";
echo "  • DE/AT/CH  -> belge Almanca (önceki davranış korunur)\n";
echo "  • IT/ES/NL  -> ilgili dil\n";
echo "  • Açıklama bölümü yalnızca kullanıcının dili belge dilinden farklıysa eklenir (FR kullanıcıda tekrar yok).\n";
echo "\nLINT: api.php sözdizimi temiz ✓\n";
echo "Yedek: " . basename($bak) . "\n";
echo "\nSONRA: opcache-reset.php. SİL: rm apply-france-backend.php\n";
echo "Test: Fransa IP/VPN -> 'Mise en demeure' seç -> üret -> belge tamamen FRANSIZCA olmalı.\n";
