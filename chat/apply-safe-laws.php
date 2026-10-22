<?php
/**
 * ChatHelp — apply-safe-laws (CH_SAFE_LAWS)
 *  "§§ HIC yok" (asiri kati) -> "AI GUVENLI §§ versin" dengesi.
 *  Zaten deploy edilmis sıkı promptlari (intake_solve + chat [[DOC]]) gunceller:
 *   • AI ilgili §§/maddeleri VERIR, ancak yalnizca iyi bilinen, gercek ve
 *     bu olaya tam uyan maddeleri; numaradan emin degilse madde uydurmaz,
 *     noktayi kelimelerle ifade eder; emsal karar uydurmaz.
 *   • Claude denetci: her §§'yi dogrular; gercekligine/uygunluguna emin
 *     olmadigi maddenin numarasini siler (cumle kalir); yeni §§/emsal eklemez.
 *  intake-api.php (CH_NOHALLUCINATE) + api.php (CH_AICHAT_NOHALL) guncellenir.
 * KULLANIM: pull-updates.php?files=apply-safe-laws.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-safe-laws BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$apiFile = __DIR__ . '/api.php';
$itFile  = __DIR__ . '/intake-api.php';
$api = @file_get_contents($apiFile);
$it  = @file_get_contents($itFile);
if ($api === false) exit("api.php okunamadi\n");
if ($it === false) exit("intake-api.php okunamadi\n");
if (strpos($api, 'CH_SAFE_LAWS') !== false || strpos($it, 'CH_SAFE_LAWS') !== false) exit("Zaten ekli (CH_SAFE_LAWS).\n");
file_put_contents($apiFile . '.bak-safelaws-' . date('Ymd-His'), $api);
file_put_contents($itFile  . '.bak-safelawsit-' . date('Ymd-His'), $it);

$hit = 0;

/* ═══ intake-api.php (CH_NOHALLUCINATE) ═══ */

/* draftSys rule 2: §§ yasagini kaldir (rule 3 pozitif ele alacak) */
$o = 'keine zusätzlichen Fakten, keine Paragraphen/§§, keine rechtlichen Argumente,';
$nw = 'keine zusätzlichen Fakten, keine rechtlichen Argumente,';
$c=0; $it=str_replace($o,$nw,$it,$c); $hit+=$c; echo ($c?"  ✓ intake draftSys rule2 (§§ yasagi kalkti)\n":"  ✗ intake rule2 anchor yok\n");

/* draftSys rule 3 (ternary dahil): §§ yalnizca kullanici -> guvenli §§ */
$o = "      . \"3) Paragraphen (§§) NUR nennen, wenn der Nutzer sie selbst genannt hat\"\n"
   . "      . (\$lawList ? \" oder wenn sie in dieser festen Liste stehen: \$lawList. \" : \" — sonst KEINE Paragraphen nennen. \")";
$nw = "      . \"3) Nenne die einschlägigen §§/Artikel, aber NUR gut etablierte Vorschriften, bei denen du sicher bist, dass sie real sind und exakt auf diesen Fall passen; bei Unsicherheit über die genaue Nummer formuliere den Punkt in Worten OHNE eine Vorschrift zu erfinden; erfinde keine Rechtsprechung. \"";
$c=0; $it=str_replace($o,$nw,$it,$c); $hit+=$c; echo ($c?"  ✓ intake draftSys rule3 (guvenli §§)\n":"  ✗ intake rule3 anchor yok\n");

/* verifySys (ternary dahil): 'sadece kullanicidan' -> 'emin oldugun gercek/uygun maddeler' */
$o = "      . \"Paragraphen nur behalten, wenn der Nutzer sie nannte\"\n"
   . "      . (\$lawList ? \" oder sie in dieser Liste stehen: \$lawList. \" : \" — andernfalls ALLE §§ streichen. \")";
$nw = "      . \"Paragraphen nur behalten, wenn sie gut etabliert, real und für diesen Fall korrekt anwendbar sind — bei Zweifel an Existenz oder Anwendbarkeit die Nummer entfernen (den Satz behalten); keine neuen §§ oder Rechtsprechung hinzufügen. \"";
$c=0; $it=str_replace($o,$nw,$it,$c); $hit+=$c; echo ($c?"  ✓ intake verifySys (guvenli §§ denetimi)\n":"  ✗ intake verifySys anchor yok\n");

/* ═══ api.php (CH_AICHAT_NOHALL — chat [[DOC]]) ═══ */
$o = "— invent nothing: no extra facts, no law paragraphs/§§, no legal arguments and no demands the user did not state; cite a paragraph ONLY if the user explicitly named it, otherwise cite none;";
$nw = "— invent no extra facts, no legal arguments and no demands the user did not state; cite the applicable well-established paragraphs/§§ you are certain are real and correctly apply (when unsure of the exact number, state the point in plain words without inventing one; never invent case-law);";
$c=0; $api=str_replace($o,$nw,$api,$c); $hit+=$c; echo ($c?"  ✓ chat [[DOC]] (guvenli §§)\n":"  ✗ chat anchor yok\n");

if ($hit < 3) { echo "\nHATA: beklenen degisikliklerin cogu eslesmedi ($hit) — HICBIR dosya degistirilmedi.\n"; exit; }

/* marker + lint + yaz */
$api = str_replace('/*CH_AICHAT_NOHALL*/', '/*CH_AICHAT_NOHALL CH_SAFE_LAWS*/', $api, $mk1);
$it  = str_replace('/* CH_NOHALLUCINATE', '/* CH_NOHALLUCINATE CH_SAFE_LAWS', $it, $mk2);
foreach ([[$apiFile,$api],[$itFile,$it]] as $pair) {
    $tmp = tempnam(sys_get_temp_dir(), 'sl') . '.php';
    file_put_contents($tmp, $pair[1]);
    $lo=[]; $rc=0; exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc); @unlink($tmp);
    if ($rc !== 0) { echo "\nLINT HATASI (".basename($pair[0]).") — HICBIR dosya degistirilmedi:\n  " . implode("\n  ", $lo) . "\n"; exit; }
}
file_put_contents($apiFile, $api);
file_put_contents($itFile, $it);
echo "\n✓ CH_SAFE_LAWS uygulandi ($hit degisiklik). AI artik ilgili §§'yi verir — ama yalnizca guvenli/emin/uygun olanlari; Claude denetci emin olmadigini siler.\n";
