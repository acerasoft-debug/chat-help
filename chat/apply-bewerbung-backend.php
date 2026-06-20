<?php
/**
 * ChatHelp — Bewerbung backend: doGenerate bew_ için CV/Anschreiben formatı (§§ değil)
 * KULLANIM: chat-help.com/chat/apply-bewerbung-backend.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/api.php';
if (!file_exists($file)) { exit("HATA: api.php yok.\n"); }
$src=file_get_contents($file); $start=$src;
file_put_contents($file . '.bak-bewbk-' . date('Ymd-His'), $src);

$anchor = '$sysLaw = $lawSystems[$country] ?? $lawSystems[\'default\'];';
if (strpos($src,'ch_bew_doc')!==false){ exit("Zaten ekli.\n"); }
if (strpos($src,$anchor)===false){ exit("✗ anchor (sysLaw) bulunamadı.\n"); }

$inject = $anchor . "\n"
  . "    if (strpos(\$docType, 'bew_') === 0) { /*ch_bew_doc*/\n"
  . "        \$sysLaw = 'Du bist ein erstklassiger deutscher Bewerbungs- und Karriereberater. Erstelle überzeugende, professionelle deutsche Bewerbungsunterlagen nach aktuellen Standards. KEINE Gesetzesparagraphen. Reiner, einsetzbarer Text.';\n"
  . "        if (strpos(\$docType,'lebenslauf')!==false) \$sysLaw .= ' Erstelle einen modernen, übersichtlichen TABELLARISCHEN Lebenslauf mit klaren Abschnitten (Persönliche Daten, Berufserfahrung, Ausbildung, Kenntnisse, Sprachen, ggf. Interessen), rückwärts-chronologisch, prägnant und fehlerfrei.';\n"
  . "        else if (strpos(\$docType,'selbstauskunft')!==false) \$sysLaw .= ' Erstelle eine vollständige, seriöse Mieterselbstauskunft mit allen üblichen Angaben als klar strukturiertes Formular/Dokument.';\n"
  . "        else if (strpos(\$docType,'wohnung')!==false) \$sysLaw .= ' Erstelle ein sympathisches, überzeugendes Bewerbungsschreiben für eine Mietwohnung in korrekter Briefform, das den Bewerber als zuverlässigen, idealen Mieter präsentiert.';\n"
  . "        else \$sysLaw .= ' Erstelle ein individuelles, überzeugendes Anschreiben in korrekter deutscher Geschäftsbriefform: Absender, Empfänger, Ort/Datum, aussagekräftiger Betreff, persönliche Anrede, motivierender Fließtext mit konkreten Stärken/Bezug zur Stelle, Grußformel. Falls eine Stellenanzeige beigefügt ist, gehe gezielt darauf ein.';\n"
  . "    }";

$src=str_replace($anchor, $inject, $src);
$changed=($src!==$start);
if($changed){
    $tmp=tempnam(sys_get_temp_dir(),'chk').'.php'; file_put_contents($tmp,$src);
    $lint=shell_exec('php -l '.escapeshellarg($tmp).' 2>&1'); @unlink($tmp);
    if($lint!==null && strpos($lint,'No syntax errors')===false){ echo "‼️ SÖZDİZİMİ HATASI — kaydedilmedi:\n$lint\n"; exit; }
    file_put_contents($file,$src);
}
echo "ChatHelp — Bewerbung backend raporu\n===================================\n\n";
echo ($changed?"  ✓ bew_ format talimatı eklendi\n":"  ✗ değişiklik yok\n");
echo "\n".($changed?"DURUM: api.php güncellendi. ✅\n":"\n");
echo "SONRA: opcache-reset.php. SİL: rm apply-bewerbung-backend.php\n";
