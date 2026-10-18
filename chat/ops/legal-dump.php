<?php
/**
 * ChatHelp ops — legal-dump: zeigt die bestehenden Rechtstexte (Impressum, Datenschutz, AGB) der alten App auf dem Server – nur lesen.
 */
header('Content-Type: text/plain; charset=UTF-8');
echo "ChatHelp — legal-dump (".gmdate('c').")\n\n";
$dir = dirname(__DIR__);
echo "Dateien in chat/: ".implode(', ', array_map('basename', array_filter(glob($dir.'/*.php'), fn($f)=>preg_match('/impress|datenschutz|agb|legal|privacy|terms|views?|pages?/i', basename($f)))))."\n";
foreach (glob($dir.'/*') as $f) { if (is_dir($f)) echo "Ordner: ".basename($f)."\n"; }
foreach (['index.php','views.php','pages.php','legal.php','impressum.php'] as $n) {
    $f = $dir.'/'.$n; if (!is_file($f)) continue;
    $t = (string)file_get_contents($f);
    echo "\n== $n (".strlen($t)." Bytes)\n";
    if (preg_match_all('/impressum/i', $t, $m)) echo "  'impressum' kommt ".count($m[0])."× vor\n";
    foreach (['impressum','datenschutz','agb'] as $k) {
        if (preg_match('/'.$k.'/i', $t, $mm, PREG_OFFSET_CAPTURE)) {
            $pos = $mm[0][1]; $chunk = substr($t, max(0,$pos-200), 2600);
            $chunk = preg_replace('/\s+/', ' ', strip_tags($chunk));
            echo "  [$k] …".mb_substr($chunk, 0, 2200)."…\n";
        }
    }
}
/* Alle Dateien nach Firmenangaben durchsuchen (nur Treffer-Zeilen, kein Schlüssel-Dump) */
echo "\n== Treffer zu Firmenangaben\n";
foreach (glob($dir.'/*.php') as $f) {
    if (in_array(basename($f), ['db.php','config.php'], true)) continue;
    $t = (string)file_get_contents($f);
    if (preg_match_all('/[^\n]{0,120}(Acerasoft|Handelsregister|HRB|USt-?Id|Steuernummer|Geschäftsführ|vertreten durch|Inhaber|Anbieter|Verantwortlich|Postanschrift|Sokak|Mahalle|Türkiye|Istanbul|İstanbul|\b\d{5}\s+[A-ZÄÖÜ][a-zäöü]+)[^\n]{0,160}/u', $t, $m)) {
        echo "-- ".basename($f)."\n";
        foreach (array_unique(array_slice($m[0], 0, 12)) as $line) echo "   ".trim(strip_tags($line))."\n";
    }
}
