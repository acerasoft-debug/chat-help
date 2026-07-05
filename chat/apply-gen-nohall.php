<?php
/**
 * ChatHelp — apply-gen-nohall (CH_GEN_NOHALL)
 *  Sablon dilekce uretimini (api.php doGenerate) halusinasyon-korumali yapar:
 *   1) DeepSeek "research" adimi ARTIK KANUN UYDURMUYOR — sadece kullanicinin
 *      kendi verdigi olgulari sadik ozetliyor (uydurma §§/kanun/arguman yok).
 *   2) draftPrompt: "§§ belirt" itisi -> sıkı "hicbir sey uydurma; §§ yalnizca
 *      kullanici belirttiyse".
 *   3) Final Claude cagrisi: cila DEGIL DENETCI — kullanicinin CASE DETAILS'inde
 *      olmayan her §§/arguman/talep silinir, olgu degistirilmez.
 *   4) Foto vision: Empfänger'in kisisel ad/adresi yansitilmaz.
 * KULLANIM: pull-updates.php?files=apply-gen-nohall.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-gen-nohall BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/api.php';
$src  = @file_get_contents($file);
if ($src === false) exit("api.php okunamadi\n");
if (strpos($src, 'CH_GEN_NOHALL') !== false) exit("Zaten ekli (CH_GEN_NOHALL).\n");
if (strpos($src, 'function doGenerate') === false) exit("HATA: doGenerate bulunamadi.\n");
file_put_contents($file . '.bak-gennohall-' . date('Ymd-His'), $src);

$n = 0;

/* ── 1) DeepSeek research: kanun uydurma -> sadik ozet ── */
$old1 = '$dsPrompt = "Research current legal requirements for: {$docName} in {$country}. Max 5 bullet points. Include key laws, deadlines, tips. No personal data. Language: {$lang}.";';
$new1 = '$dsPrompt = "Liste NUR die Fakten, die der Nutzer für das Dokument \'{$docName}\' selbst angegeben hat, als knappe Stichpunkte (Anliegen, beteiligte Stelle/Person, Daten/Fristen, gewünschte Forderung). Erfinde NICHTS hinzu: keine Gesetze, keine Paragraphen/§§, keine rechtlichen Argumente, keine Forderungen, die der Nutzer nicht genannt hat. Sprache: {$lang}.\n\nANGABEN DES NUTZERS:\n{$answersText}"; /*CH_GEN_NOHALL*/';
$c=0; $src=str_replace($old1,$new1,$src,$c); $n+=$c; echo ($c?"  ✓ [1] DeepSeek research artik uydurmuyor\n":"  ✗ [1] research anchor yok\n");

/* research etiketi de olgu-only olsun */
$old1b = 'if ($dsR) $research = $dsR;';
$new1b = 'if ($dsR) $research = "NUR BELEGTE NUTZERANGABEN (nichts ergänzen, nichts erfinden):\n".$dsR;';
$c=0; $src=str_replace($old1b,$new1b,$src,$c); echo ($c?"  ✓ [1b] research etiketi olgu-only\n":"  ✗ [1b] research atama anchor yok\n");

/* ── 2) draftPrompt: "§§ belirt" -> sıkı uydurma yasagi ── */
$old2 = '- Cite specific laws with §§ or Article numbers';
$new2 = '- Do NOT invent anything: add no facts, no law paragraphs/§§, no legal arguments and no demands that are not present in CASE DETAILS; formalize ONLY what the user provided; cite a §/article/statute ONLY if the user themselves stated it, otherwise cite none';
$c=0; $src=str_replace($old2,$new2,$src,$c); $n+=$c; echo ($c?"  ✓ [2] draftPrompt uydurma yasagi\n":"  ✗ [2] draftPrompt anchor yok\n");

/* ── 3) Final adim: cila -> DENETCI ── */
$old3 = '"Precise document assistant. Document tone: "';
$new3 = '"Precise document assistant AND strict fact-checker: remove from the document any law paragraph/§, legal argument or demand that is NOT present in the user CASE DETAILS; keep only what the user stated; invent nothing and change no facts. Document tone: "';
$c=0; $src=str_replace($old3,$new3,$src,$c); $n+=$c; echo ($c?"  ✓ [3] final adim denetciye cevrildi\n":"  ✗ [3] final anchor yok\n");

/* ── 4) Foto vision: kisisel veri korumasi ── */
$old4 = 'Extrahiere alle relevanten Fakten (Daten, Fristen, Aktenzeichen, Beträge, Behörde/Absender, Kernaussagen) als knappe deutsche Stichpunkte. Keine Einleitung, keine Erklärungen.';
$new4 = 'Extrahiere NUR sachliche Falldaten (Daten, Fristen, Aktenzeichen, Beträge, absendende Behörde/Firma, Kernaussagen) als knappe deutsche Stichpunkte. Gib KEINE persönlichen Namen oder Privatadressen des Empfängers wieder. Keine Einleitung, keine Erklärungen.';
$c=0; $src=str_replace($old4,$new4,$src,$c); echo ($c?"  ✓ [4] foto vision kisisel veri korumali\n":"  ✗ [4] vision anchor yok\n");

if ($n < 3) { echo "\nHATA: kritik degisikliklerden yalnizca $n uygulandi — api.php DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(), 'api') . '.php';
file_put_contents($tmp, $src);
$lo = []; $rc = 0;
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc);
@unlink($tmp);
if ($rc !== 0) { echo "\nLINT HATASI — api.php degistirilmedi:\n  " . implode("\n  ", $lo) . "\n"; exit; }
file_put_contents($file, $src);
echo "\n✓ CH_GEN_NOHALL uygulandi. Sablon dilekce uretimi artik uydurma §§/kanun/arguman/talep icermez; final adim denetci.\n";
