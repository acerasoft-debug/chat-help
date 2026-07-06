<?php
/**
 * ChatHelp — apply-gen-quality (CH_GEN_QUALITY)
 *  1) fall-api.php: guvenli-§§ dengesi (uydurma yok) + Telefon alani duzeltmesi (f5).
 *  2) api.php: chat [[DOC]] guvenli-§§ (kalan katı sürüm) + Anschreiben promptu
 *     daha estetik/ayrintili + Eintrittstermin/Gehalt vurgusu.
 *  3) index.php: Bewerbung 'Eintrittstermin' sorusu daha net.
 *  Guvenlik: gercek ad/adres yine profBlock ile sunucuda birlestirilir, 3.taraf AI'ya
 *  ham gitmez (mevcut anonimlestirme korunur).
 * KULLANIM: pull-updates.php?files=apply-gen-quality.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-gen-quality BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$fallF = __DIR__.'/fall-api.php';
$apiF  = __DIR__.'/api.php';
$idxF  = __DIR__.'/index.php';
$fall = @file_get_contents($fallF);
$api  = @file_get_contents($apiF);
$idx  = @file_get_contents($idxF);
if ($fall===false) exit("fall-api.php okunamadi\n");
if ($api===false)  exit("api.php okunamadi\n");
if ($idx===false)  exit("index.php okunamadi\n");
if (strpos($fall,'CH_GEN_QUALITY')!==false || strpos($api,'CH_GEN_QUALITY')!==false) exit("Zaten ekli (CH_GEN_QUALITY).\n");

$crit=0; $opt=0;

/* ═══ 1) fall-api.php ═══ */
/* 1a) guvenli-§§ */
$o='"Betreff, Anrede, ausführliche Begründung mit §-Verweisen, klare Forderung + Frist, Grußformel. "';
$nw='"Betreff, Anrede, ausführliche sachliche Begründung, klare Forderung + Frist, Grußformel. '
   .'Nenne einschlägige §§/Artikel NUR, wenn du sicher bist, dass sie real sind und exakt auf diesen Fall zutreffen; '
   .'bei Unsicherheit über die genaue Nummer formuliere den rechtlichen Punkt in Worten OHNE einen Paragraphen zu erfinden; '
   .'erfinde niemals Gesetze oder Rechtsprechung; ändere keine vom Nutzer genannten Fakten. "/*CH_GEN_QUALITY*/';
$c=0; $fall=str_replace($o,$nw,$fall,$c); $crit+=$c; echo ($c?"  ✓ [1a] fall-api guvenli-§§\n":"  ✗ [1a] fall-api §§ anchor yok\n");

/* 1b) Telefon f5 (f6 hatasi) */
$o='. "Telefon: " . ($prof[\'f6\'] ?? \'\') . "\n"';
$nw='. "Telefon: " . ($prof[\'f5\'] ?? ($prof[\'f6\'] ?? \'\')) . "\n"';
$c=0; $fall=str_replace($o,$nw,$fall,$c); $opt+=$c; echo ($c?"  ✓ [1b] fall-api Telefon f5\n":"  ✗ [1b] fall-api Telefon anchor yok (atlandi)\n");

/* ═══ 2) api.php ═══ */
/* 2a) chat [[DOC]] guvenli-§§ */
$o='— invent nothing: no extra facts, no law paragraphs/§§, no legal arguments and no demands the user did not state; cite a paragraph ONLY if the user explicitly named it, otherwise cite none;';
$nw='— invent no extra facts, no legal arguments and no demands the user did not state; cite the applicable well-established paragraphs/§§ you are certain are real and correctly apply (when unsure of the exact number, state the point in plain words without inventing one; never invent case-law);';
$c=0; $api=str_replace($o,$nw,$api,$c); $crit+=$c; echo ($c?"  ✓ [2a] chat [[DOC]] guvenli-§§\n":"  ✗ [2a] chat anchor yok\n");

/* 2b) Anschreiben promptu: estetik + ayrintili + Eintrittstermin */
$o='Fließtext mit konkreten Stärken/Bezug zur Stelle, Grußformel. Falls eine Stellenanzeige beigefügt ist, gehe gezielt darauf ein.';
$nw='mehreren gut strukturierten Absätzen: ein starker Einstieg mit Bezug zur konkreten Stelle, Ihre Motivation, Ihre wichtigsten Qualifikationen mit konkreten Beispielen/Erfolgen, der Mehrwert für das Unternehmen, ein professioneller Schlussabsatz mit Wunsch nach einem Gespräch. Nenne den möglichen Eintrittstermin, wenn angegeben, und die Gehaltsvorstellung nur, wenn angegeben. Grußformel. Ästhetisch, flüssig und überzeugend formuliert, sachlich und ohne leere Floskeln, ca. 250–350 Wörter. Falls eine Stellenanzeige beigefügt ist, gehe gezielt auf deren Anforderungen ein.';
$c=0; $api=str_replace($o,$nw,$api,$c); $crit+=$c; echo ($c?"  ✓ [2b] Anschreiben estetik/ayrintili + Eintrittstermin\n":"  ✗ [2b] Anschreiben anchor yok\n");

/* ═══ 3) index.php ═══ */
/* 3a) Eintrittstermin sorusu daha net */
$o="P('eintritt','Möglicher Eintrittstermin',false)";
$nw="P('eintritt','Ab wann können Sie beginnen? (Eintrittstermin, z.B. sofort / ab 01.09.)',false)";
$c=0; $idx=str_replace($o,$nw,$idx,$c); $opt+=$c; echo ($c?"  ✓ [3a] Eintrittstermin sorusu netlestirildi\n":"  ✗ [3a] Eintrittstermin anchor yok (atlandi)\n");

if ($crit < 3) { echo "\nHATA: kritik degisikliklerden yalnizca $crit/3 uygulandi — HICBIR dosya degistirilmedi.\n"; exit; }

/* marker + lint + yaz */
$fall = str_replace('<?php', '<?php /*CH_GEN_QUALITY*/', $fall, $m1);
$api  = str_replace('<?php', '<?php /*CH_GEN_QUALITY*/', $api,  $m2);
foreach ([[$fallF,$fall],[$apiF,$api],[$idxF,$idx]] as $pair) {
    $tmp = tempnam(sys_get_temp_dir(),'gq').'.php';
    file_put_contents($tmp,$pair[1]);
    $lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
    if ($rc!==0){ echo "\nLINT HATASI (".basename($pair[0]).") — HICBIR dosya degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
}
/* yedek + yaz */
file_put_contents($fallF.'.bak-gq-'.date('Ymd-His'), @file_get_contents($fallF));
file_put_contents($apiF.'.bak-gq-'.date('Ymd-His'),  @file_get_contents($apiF));
file_put_contents($idxF.'.bak-gq-'.date('Ymd-His'),  @file_get_contents($idxF));
file_put_contents($fallF,$fall);
file_put_contents($apiF,$api);
file_put_contents($idxF,$idx);
echo "\n✓ CH_GEN_QUALITY uygulandi (kritik:$crit, opsiyonel:$opt). Bewerbung daha estetik+Eintrittstermin, fall-api guvenli-§§ + Telefon duzeldi, chat guvenli-§§.\n";
