<?php
/**
 * ChatHelp — apply-doc-polish (CH_DOC_POLISH)
 *  Uretilen belgede kozmetik temizlik (deterministik, AI'ya guvenmeden):
 *   • Markdown kalintilari: ** kalin isaretleri, --- ayirici satirlar
 *   • Bos alan satirlari: "Telefon:", "Geburtsdatum:" gibi degeri olmayan etiketler
 *   • Adres tekrari: onceki satirin sonu ile birebir ayni olan kisa satir
 *  + Prompt kuralina "plain text only" eklenir (kaynaginda da azaltir).
 * KULLANIM: pull-updates.php?files=apply-doc-polish.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-doc-polish BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/api.php';
$src  = file_get_contents($file);
if ($src === false) exit("api.php okunamadi\n");
if (strpos($src, 'CH_DOC_POLISH') !== false) exit("Zaten ekli (CH_DOC_POLISH).\n");
$bak = $file . '.bak-docpolish-' . date('Ymd-His');
file_put_contents($bak, $src);

/* ── 1) $final son islem: temizlik blogu ── */
$anchor = "if (!\$final) { respond(['error' => 'Document generation failed. Check API key.']); return; }";
$insert = $anchor . "\n" . <<<'PHP'

    /* CH_DOC_POLISH — kozmetik temizlik (markdown, bos etiket, adres tekrari) */
    $final = str_replace('**', '', $final);
    $chLines = explode("\n", $final);
    $chOut = [];
    foreach ($chLines as $chLn) {
        $chT = trim($chLn);
        if ($chT === '---' || $chT === '***' || $chT === '___') continue;
        if (preg_match('/^(Telefon|Tel\.?|Mobil|Fax|E-?Mail|Geburtsdatum|Geboren am|Kundennummer|Vertragsnummer|Aktenzeichen|IBAN|Steuer-?ID)\s*:\s*$/iu', $chT)) continue;
        $chPrev = '';
        for ($chI = count($chOut) - 1; $chI >= 0; $chI--) { if (trim($chOut[$chI]) !== '') { $chPrev = trim($chOut[$chI]); break; } }
        if ($chT !== '' && mb_strlen($chT) >= 6 && $chPrev !== '' && $chT !== $chPrev
            && mb_substr($chPrev, -mb_strlen($chT)) === $chT) continue;
        if ($chT !== '' && $chT === $chPrev) continue;
        $chOut[] = $chLn;
    }
    $final = implode("\n", $chOut);
    $final = preg_replace("/\n{3,}/", "\n\n", $final);
PHP;

$src2 = str_replace($anchor, $insert, $src, $c1);
if ($c1) { $src = $src2; echo "  ✓ [1] temizlik blogu eklendi ($c1 nokta)\n"; }
else { echo "  ✗ [1] anchor bulunamadi — YAZILMADI\n"; exit; }

/* ── 2) prompt kurali: plain text only ── */
$pAnchor = '- Return ONLY the document text, no explanations';
$pNew = $pAnchor . "\n- Plain text only: NEVER use Markdown (no **, no ---, no #). Omit any field whose value is unknown (no empty labels like \"Telefon:\").";
$src2 = str_replace($pAnchor, $pNew, $src, $c2);
if ($c2) { $src = $src2; echo "  ✓ [2] prompt kurali eklendi ($c2 nokta)\n"; }
else { echo "  - [2] prompt anchor yok (atlandi — temizlik blogu yeterli)\n"; }

/* ── lint sonra yaz ── */
$tmp = tempnam(sys_get_temp_dir(), 'api') . '.php';
file_put_contents($tmp, $src);
$lo = []; $rc = 0;
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc);
@unlink($tmp);
if ($rc !== 0) { echo "\nLINT HATASI — api.php degistirilmedi:\n  " . implode("\n  ", $lo) . "\n"; exit; }
file_put_contents($file, $src);
echo "\n✓ CH_DOC_POLISH uygulandi (yedek: " . basename($bak) . ")\n";
echo "Test: dump-gentest.php'yi tekrar ac -> belge basliginda ** olmamali, 'Telefon:' bos satiri ve adres tekrari kaybolmali.\n";
