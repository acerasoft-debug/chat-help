<?php
/**
 * ChatHelp — Belge: Almanca (asıl) + ana dilde açıklama
 * doGenerate, lang Almanca DEĞİLSE: resmî belge Almanca üretilir, sonuna
 * ayrı bir "— Erklärung ({lang}) —" bölümü kullanıcının dilinde eklenir.
 * Hukuk her zaman Alman hukuku; resmî metin her zaman Almanca.
 * KULLANIM: chat-help.com/chat/apply-doc-native.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/api.php';
if (!file_exists($file)) { exit("HATA: api.php yok.\n"); }
$src=file_get_contents($file); $start=$src;
file_put_contents($file . '.bak-docnative-' . date('Ymd-His'), $src);
$rep=[];

/* doGenerate'in final system prompt'una ana dil açıklama talimatı ekle */
$anchor = '$final = callClaude("Precise document assistant. Document tone: ".$toneInstruction." Return ONLY the final document text, no explanations.", $finalPrompt, ';
if (strpos($src, 'ch_native_expl') !== false) {
    $rep[] = ['#1 (zaten ekli)', 0];
} elseif (strpos($src, $anchor) === false) {
    $rep[] = ['#1 anchor (final callClaude) BULUNAMADI', 0];
} else {
    $inject = "/*ch_native_expl*/\n"
      . "    \$LN=['tr'=>'Türkisch','ru'=>'Russisch','ar'=>'Arabisch','en'=>'Englisch','fr'=>'Französisch','es'=>'Spanisch','it'=>'Italienisch'];\n"
      . "    \$ulang=strtolower(\$b['ui_lang'] ?? \$lang ?? 'de');\n"
      . "    \$nativeNote='';\n"
      . "    if(isset(\$LN[\$ulang])){ \$nativeNote=' WICHTIG: Das offizielle Dokument bleibt vollständig auf DEUTSCH (es wird bei einer deutschen Stelle eingereicht). Füge danach eine durch eine Trennlinie abgesetzte Sektion hinzu mit dem Titel \"— Erklärung auf '.\$LN[\$ulang].' —\" und erkläre dort in '.\$LN[\$ulang].' kurz: worum es geht, wichtige Fristen, und die nächsten Schritte (drucken, unterschreiben, per Einschreiben senden). Nur diese Erklärsektion in '.\$LN[\$ulang].', das Dokument selbst bleibt Deutsch.'; }\n"
      . "    \$final = callClaude(\"Precise document assistant. Document tone: \".\$toneInstruction.\$nativeNote.\" Return ONLY the final document text, no explanations.\", \$finalPrompt, ";
    $src = str_replace($anchor, $inject, $src);
    $rep[] = ['#1 ana dil açıklama talimatı eklendi', 1];
}

$changed=($src!==$start);
if($changed){
    $tmp=tempnam(sys_get_temp_dir(),'chk').'.php'; file_put_contents($tmp,$src);
    $lint=shell_exec('php -l '.escapeshellarg($tmp).' 2>&1'); @unlink($tmp);
    if($lint!==null && strpos($lint,'No syntax errors')===false){ echo "‼️ SÖZDİZİMİ HATASI — kaydedilmedi:\n$lint\n"; exit; }
    file_put_contents($file,$src);
}
echo "ChatHelp — Belge ana dil açıklama raporu\n========================================\n\n";
foreach($rep as [$l,$n]) echo ($n>0?"  ✓ ":"  ✗ ").$l."  →  $n\n";
echo "\n".($changed?"DURUM: api.php güncellendi. ✅\n":"DURUM: Değişiklik yok.\n");
echo "SONRA: opcache-reset.php. SİL: rm apply-doc-native.php\n";
echo "Test: dili Rusça yap -> dilekçe oluştur -> belge Almanca + altta '— Erklärung auf Russisch —'.\n";
