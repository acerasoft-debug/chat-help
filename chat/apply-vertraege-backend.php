<?php
/**
 * ChatHelp — Verträge backend: eksiksiz + güncel mevzuat üretimi
 * -------------------------------------------------------------
 * doGenerate, docType 'vtr_' ile başlıyorsa (sözleşme) Claude'a TAM, rechtssicher,
 * güncel kanuna (Stand {Jahr}) uygun, tüm zorunlu klozları içeren bir sözleşme
 * ürettirir; gerekiyorsa notarielle Beurkundung uyarısı koydurur.
 *
 * KULLANIM: chat-help.com/chat/apply-vertraege-backend.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/api.php';
if (!file_exists($file)) { exit("HATA: api.php yok.\n"); }
$src   = file_get_contents($file);
$start = $src;
file_put_contents($file . '.bak-vtrbk-' . date('Ymd-His'), $src);
$rep = [];

/* Sözleşme talimatı (sysLaw'a eklenir) */
$anchor = '$sysLaw = $lawSystems[$country] ?? $lawSystems[\'default\'];';
$inject = $anchor . "\n"
  . "    if (strpos(\$docType, 'vtr_') === 0) {\n"
  . "        \$sysLaw .= ' Erstelle einen VOLLSTÄNDIGEN, rechtssicheren deutschen Vertrag nach AKTUELLER Gesetzeslage (Stand ' . date('Y') . '), inklusive jüngster Reformen.'\n"
  . "          . ' Struktur: aussagekräftige Vertragsüberschrift; Vertragsparteien mit vollständigen Daten; durchnummerierte Paragraphen (§ 1, § 2, …), die ALLE für diesen Vertragstyp gesetzlich ZWINGENDEN und üblichen Klauseln enthalten'\n"
  . "          . ' (u.a. Vertragsgegenstand/Leistung, Vergütung/Preis & Fälligkeit, Laufzeit/Fristen, Pflichten und Rechte BEIDER Parteien, Haftung & Gewährleistung, Kündigung, Datenschutz/Vertraulichkeit falls relevant, Schlussbestimmungen mit salvatorischer Klausel und Schriftformklausel);'\n"
  . "          . ' am Ende Ort/Datum und Unterschriftsfelder BEIDER Parteien.'\n"
  . "          . ' Nenne einschlägige §§ (BGB, HGB, GmbHG, GbR/MoPeG, BetrVG, TzBfG, Nachweisgesetz usw.) korrekt.'\n"
  . "          . ' Wenn für diesen Vertrag eine notarielle Beurkundung oder Schriftform gesetzlich vorgeschrieben ist (z.B. Ehevertrag, GmbH-Satzung, Grundstücke, Bürgschaft), weise am Ende AUSDRÜCKLICH mit einem Hinweis darauf hin.'\n"
  . "          . ' Lasse keine Pflichtklausel aus. Reiner, vollständiger Vertragstext.';\n"
  . "    }";

$c = substr_count($src, $anchor);
if ($c > 0) { $src = str_replace($anchor, $inject, $src); $rep[] = ['#1 Sözleşme sysLaw talimatı', $c]; }
else { $rep[] = ['#1 anchor bulunamadı (sysLaw satırı)', 0]; }

/* Sözleşmeler uzun olabilir: final token limitini sözleşmede yükselt */
$old2 = '$final = callClaude("Precise document assistant. Document tone: ".$toneInstruction." Return ONLY the final document text, no explanations.", $finalPrompt, 3000);';
$new2 = '$final = callClaude("Precise document assistant. Document tone: ".$toneInstruction." Return ONLY the final document text, no explanations.", $finalPrompt, (strpos($docType,\'vtr_\')===0?5000:3000));';
$c2 = substr_count($src, $old2);
if ($c2 > 0) { $src = str_replace($old2, $new2, $src); $rep[] = ['#2 Sözleşmede token limiti 5000', $c2]; }
else { $rep[] = ['#2 final callClaude anchor bulunamadı (atlandı)', 0]; }

$changed = ($src !== $start);
if ($changed) {
    $tmp = tempnam(sys_get_temp_dir(), 'chk') . '.php';
    file_put_contents($tmp, $src);
    $lint = shell_exec('php -l ' . escapeshellarg($tmp) . ' 2>&1');
    @unlink($tmp);
    if ($lint !== null && strpos($lint, 'No syntax errors') === false) {
        echo "‼️ SÖZDİZİMİ HATASI — kaydedilmedi:\n$lint\n";
        exit;
    }
    file_put_contents($file, $src);
}

echo "ChatHelp — Verträge backend raporu\n==================================\n\n";
foreach ($rep as [$l,$n]) echo ($n>0?"  ✓ ":"  ✗ ").$l."  →  $n\n";
echo "\n" . ($changed ? "DURUM: api.php güncellendi. ✅\n" : "DURUM: Değişiklik yok.\n");
echo "\nSONRA: opcache-reset.php. SİL: rm apply-vertraege-backend.php\n";
