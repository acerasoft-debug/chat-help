<?php
/**
 * ChatHelp — apply-nohallucinate (CH_NOHALLUCINATE)
 *  Halüsinasyon önleme + iki-asamali dogrulama (Fall/intake_solve akisi):
 *   ESKI (riskli): DeepSeek "uygun §§ + en guclu argumanlari belirle" ->
 *                  Claude bunlarla yazar (UYDURMA kaynak).
 *   YENI (guvenli):
 *     1) DeepSeek SADIK TASLAK: yalnizca kullanicinin kendi belirttigi olay/
 *        talepleri resmi Almanca mektuba cevirir. Hicbir §§, hukuki arguman
 *        veya talep EKLEMEZ. §§ yalnizca kullanicinin adi gecen VEYA sabit
 *        listeden (lawlist) gelir; liste bossa HIC §§ yok.
 *     2) Claude DENETCI: taslagi kullanicinin orijinal sozleriyle karsilastirir,
 *        desteklenmeyen her sey (uydurma olay/§§/arguman/talep) silinir/duzeltilir.
 *   + Vision adimi: fotodan yalnizca sacik olay verileri (tarih/Frist/Aktenzeichen/
 *     tutar/makam) — Empfänger'in kisisel ad/adresi yansitilmaz.
 *   + Frontend: solve() artik yuklenen fotolari (_chPhotos) gonderiyor.
 *  intake-api.php (backend) + index.php (frontend solve) guncellenir.
 * KULLANIM: pull-updates.php?files=apply-nohallucinate.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-nohallucinate BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$apiFile = __DIR__ . '/intake-api.php';
$idxFile = __DIR__ . '/index.php';
$api = @file_get_contents($apiFile);
$idx = @file_get_contents($idxFile);
if ($api === false) exit("intake-api.php okunamadi\n");
if ($idx === false) exit("index.php okunamadi\n");
if (strpos($api, 'CH_NOHALLUCINATE') !== false) exit("Zaten ekli (CH_NOHALLUCINATE).\n");
file_put_contents($apiFile . '.bak-nohall-' . date('Ymd-His'), $api);
file_put_contents($idxFile . '.bak-nohallidx-' . date('Ymd-His'), $idx);

$okApi = 0;

/* ── [A] struct adimini (uydurma §§ kaynagi) kaldir ── */
$oldStruct = "    // 1) Yapı + hukuki çerçeve (anlama derinleştirme)\n"
  . "    \$struct = ch_ds([\n"
  . "        ['role'=>'system','content'=>'Du bist deutscher Jurist. Fasse den Bedarf des Nutzers präzise zusammen und bestimme: passender Dokumententyp, einschlägige §§, Fristen, die stärksten Argumente. Kompakt und strukturiert.'],\n"
  . "        ['role'=>'user','content'=>\"GESPRÄCH:\\n\$convo\"],\n"
  . "    ], 0.3, 1500);\n";
$newStruct = "    /* CH_NOHALLUCINATE: '§§ + Argumente bestimmen' adimi kaldirildi (uydurma kaynagiydi) */\n";
$c=0; $api=str_replace($oldStruct,$newStruct,$api,$c); if($c){ $okApi++; echo "  ✓ [A] uydurma §§/arguman adimi kaldirildi ($c)\n"; } else echo "  ✗ [A] struct anchor yok\n";

/* ── [B] tek-asamali Claude yazimi -> iki-asamali sadik-taslak + denetim ── */
$oldGen = "    \$sys = \"Du bist ein erfahrener deutscher Rechtsanwalt. Erstelle ein vollständiges, sofort einsetzbares \"\n"
  . "         . \"deutsches Dokument, das GENAU auf das Anliegen des Nutzers zugeschnitten ist. \"\n"
  . "         . \"Korrekte Briefform: Absender (echte Daten), Empfänger, Ort + Datum, Betreff, Anrede, \"\n"
  . "         . \"ausführliche Begründung mit §-Verweisen, klare Forderung + Frist, Grußformel. \"\n"
  . "         . \"Schreibstil: \$toneStr. \"\n"
  . "         . \"Reiner Fließtext — kein Markdown, keine Sternchen, keine Emojis. \"\n"
  . "         . \"Echte Absenderdaten und Datum verwenden. Kein Versandvermerk (z.B. 'Per Einschreiben'). \"\n"
  . "         . \"Nur das fertige Dokument ausgeben.\";\n"
  . "    \$usr = \$profBlock . \"ANLIEGEN (Gespräch):\\n\$convo\\n\\nJURISTISCHE EINORDNUNG:\\n\$struct\\n\\nErstelle jetzt das finale Dokument.\";\n"
  . "    \$doc = ch_claude(\$sys, \$usr, 4000);\n";
$newGen = <<<'PHPGEN'
    /* CH_NOHALLUCINATE — 1) DeepSeek SADIK taslak, 2) Claude DENETCI */
    $lawList = trim((string)($body['lawlist'] ?? ''));
    $draftSys = "Du bist ein deutscher Schreibassistent, KEIN kreativer Jurist. Formuliere die Angaben des Nutzers "
      . "in ein korrektes, formelles deutsches Schreiben um. STRENGE REGELN, ausnahmslos: "
      . "1) Verwende AUSSCHLIESSLICH die Sachverhalte, Anliegen und Forderungen, die der Nutzer SELBST genannt hat. "
      . "2) Erfinde NICHTS hinzu: keine zusätzlichen Fakten, keine Paragraphen/§§, keine rechtlichen Argumente, "
      . "keine Forderungen, die der Nutzer nicht ausdrücklich gestellt hat. "
      . "3) Paragraphen (§§) NUR nennen, wenn der Nutzer sie selbst genannt hat"
      . ($lawList ? " oder wenn sie in dieser festen Liste stehen: $lawList. " : " — sonst KEINE Paragraphen nennen. ")
      . "4) Korrekte Briefform: Absender (echte Daten), Empfänger, Ort + Datum, Betreff, Anrede, "
      . "Sachverhalt (nur Nutzer-Fakten), die vom Nutzer gewünschte Forderung, Grußformel. "
      . "Stil: $toneStr. Reiner Fließtext, kein Markdown, keine Emojis, kein Versandvermerk.";
    $draft = ch_ds([
        ['role'=>'system','content'=>$draftSys],
        ['role'=>'user','content'=>$profBlock . "ANGABEN DES NUTZERS (Gespräch):\n$convo\n\nSchreibe jetzt den Brief."],
    ], 0.2, 2500);

    $verifySys = "Du bist ein strenger juristischer Korrektor. Du erhältst (A) die ORIGINALANGABEN des Nutzers und "
      . "(B) einen BRIEFENTWURF. Prüfe den Entwurf Satz für Satz und ENTFERNE bzw. korrigiere ALLES, was NICHT durch "
      . "die Originalangaben gedeckt ist: erfundene Sachverhalte, nicht vom Nutzer genannte Paragraphen/§§, "
      . "hinzugedichtete rechtliche Argumente, nicht gestellte Forderungen. "
      . "Paragraphen nur behalten, wenn der Nutzer sie nannte"
      . ($lawList ? " oder sie in dieser Liste stehen: $lawList. " : " — andernfalls ALLE §§ streichen. ")
      . "Ändere die vom Nutzer genannten Fakten NICHT und erfinde keine neuen. Verbessere nur Form und Sprache. "
      . "Gib ausschließlich den finalen, geprüften Brief als reinen Fließtext aus, sonst nichts.";
    $doc = ch_claude($verifySys, "ORIGINALANGABEN DES NUTZERS:\n$convo\n\nBRIEFENTWURF:\n$draft", 4000);
    if (!$doc) $doc = $draft;

PHPGEN;
$c=0; $api=str_replace($oldGen,$newGen,$api,$c); if($c){ $okApi++; echo "  ✓ [B] iki-asamali sadik-taslak + denetim kuruldu ($c)\n"; } else echo "  ✗ [B] generation anchor yok\n";

/* ── [C] vision: kisisel ad/adres yansitma ── */
$oldVis = "\"Du wertest hochgeladene Dokumente/Belege aus. Extrahiere alle relevanten Fakten \"\n"
  . "            . \"(Daten, Fristen, Aktenzeichen, Beträge, Behörde/Absender, Kernaussagen) als knappe deutsche Stichpunkte. Keine Einleitung.\",";
$newVis = "\"Du wertest hochgeladene Dokumente/Belege aus. Extrahiere NUR sachliche Falldaten \"\n"
  . "            . \"(Daten, Fristen, Aktenzeichen, Beträge, absendende Behörde/Firma, Kernaussagen) als knappe deutsche Stichpunkte. \"\n"
  . "            . \"Gib KEINE persönlichen Namen oder Privatadressen des Empfängers wieder. Keine Einleitung.\",";
$c=0; $api=str_replace($oldVis,$newVis,$api,$c); if($c){ $okApi++; echo "  ✓ [C] vision kisisel veri yansitmiyor ($c)\n"; } else echo "  ✗ [C] vision anchor yok\n";

if ($okApi < 2) { echo "\nHATA: intake-api.php'de beklenen degisiklikler eksik ($okApi) — HICBIR sey degistirilmedi.\n"; exit; }

/* ── [D] frontend solve(): fotolari gonder ── */
$oldSolve = "tone:(window._chTone||'formell')";
$newSolve = "tone:(window._chTone||'formell'),\n        photos:Object.values(window._chPhotos||{})";
$c=0; $idx=str_replace($oldSolve,$newSolve,$idx,$c);
$okIdx = $c; if($c){ echo "  ✓ [D] solve() artik fotolari gonderiyor ($c)\n"; } else echo "  ⚠ [D] solve() anchor yok (frontend degismedi, backend yine de guvenli)\n";

/* lint + yaz */
foreach ([[$apiFile,$api],[$idxFile,$idx]] as $pair) {
    $tmp = tempnam(sys_get_temp_dir(), 'nh') . '.php';
    file_put_contents($tmp, $pair[1]);
    $lo=[]; $rc=0; exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc); @unlink($tmp);
    if ($rc !== 0) { echo "\nLINT HATASI (".basename($pair[0]).") — HICBIR dosya degistirilmedi:\n  " . implode("\n  ", $lo) . "\n"; exit; }
}
file_put_contents($apiFile, $api);
file_put_contents($idxFile, $idx);
echo "\n✓ CH_NOHALLUCINATE uygulandi (intake-api:$okApi, index:$okIdx).\n";
echo "Artik: DeepSeek yalnizca kullanici sozlerinden sadik taslak -> Claude uydurma §§/arguman/talep varsa siler. §§ yalnizca kullanicidan/sabit listeden.\n";
