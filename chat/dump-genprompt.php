<?php
/** ChatHelp — dump-genprompt: api.php'deki BELGE URETIM promptlarini gosterir
 *  (sablon dilekce akisi doGenerate + lawSystems + claudeVision + Anschreiben).
 *  Amac: halusinasyon-korumasini bu akislara da uygulamak icin tam prompt metnini almak.
 *  SADECE OKUR. Ciktinin TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$api = @file_get_contents(__DIR__ . '/api.php');
if ($api === false) exit("api.php okunamadi\n");
echo "api.php: " . strlen($api) . " bayt\n\n";

function w($s, $needle, $before, $len, $label) {
    echo "###### $label ######\n";
    $p = strpos($s, $needle);
    if ($p === false) { echo "  (bulunamadi: '$needle')\n\n"; return; }
    echo substr($s, max(0, $p - $before), $len) . "\n\n";
}

/* dispatch table — hangi action hangi fonksiyona */
w($api, "'aichat'", -20, 260, "dispatch: aichat");
w($api, "'generate'", -20, 260, "dispatch: generate");

/* ana uretim fonksiyonu */
w($api, 'function doGenerate', 0, 2600, "doGenerate() govdesi (ilk 2600)");

/* lawSystems — ulke bazli hukuk promptlari (uydurma-riskli '§ belirt' talimatlari) */
w($api, '$lawSystems', -10, 1800, "lawSystems tanimi (ilk 1800)");
w($api, 'lawSystems =', -10, 1800, "lawSystems = (alternatif)");

/* claudeVision — foto analizi */
w($api, 'function claudeVision', 0, 900, "claudeVision() govdesi");

/* Anschreiben / cover letter uretimi (is basvurusu) */
w($api, 'Anschreiben', -120, 400, "Anschreiben uretim promptu civari");
w($api, "'jobletter'", -30, 300, "dispatch: jobletter (varsa)");
w($api, 'doJobLetter', 0, 1200, "doJobLetter() (varsa)");

echo "\n=== BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-genprompt.php ===\n";
