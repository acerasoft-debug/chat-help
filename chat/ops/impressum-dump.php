<?php
/**
 * ChatHelp ops — impressum-dump: zeigt den bestehenden Impressum-Block der alten Startseite (index2.php im Webroot)
 * und die Rechtstext-Ordner (datenschutz/) – nur lesen, keine Schlüssel, db.php/config.php werden nie geöffnet.
 */
header('Content-Type: text/plain; charset=UTF-8');
echo "ChatHelp — impressum-dump (".gmdate('c').")\n\n";
$root = dirname(__DIR__);
$clean = function (string $t): string {
    $t = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', ' ', $t);
    $t = preg_replace('#<br\s*/?>|</(p|div|li|h\d|tr|dt|dd)>#i', "\n", $t);
    $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace('/[ \t]+/', ' ', $t);
    return trim(preg_replace("/\n\s*\n+/", "\n", $t));
};
foreach (['index2.php', 'index.php', 'index.html'] as $n) {
    $f = $root.'/'.$n; if (!is_file($f)) continue;
    $t = (string)file_get_contents($f);
    echo "== $n (".strlen($t)." Bytes)\n";
    $off = 0; $hits = 0;
    while ($hits < 3 && preg_match('/Acerasoft LLC|Impressum|Angaben gem/iu', $t, $m, PREG_OFFSET_CAPTURE, $off)) {
        $pos = $m[0][1]; $off = $pos + 4000; $hits++;
        echo "-- Treffer '".$m[0][0]."' @ $pos\n".mb_substr($clean(substr($t, max(0, $pos - 600), 5000)), 0, 3200)."\n\n";
    }
}
foreach (['datenschutz', 'konto', 'platform'] as $d) {
    $p = $root.'/'.$d; if (!is_dir($p)) continue;
    echo "== Ordner $d: ".implode(', ', array_map('basename', glob($p.'/*')))."\n";
    foreach (glob($p.'/*.{php,html}', GLOB_BRACE) as $f) {
        if (in_array(basename($f), ['db.php', 'config.php'], true)) continue;
        $t = (string)file_get_contents($f);
        if (!preg_match('/Acerasoft|Verantwortlich|Anschrift|Adresse|Kontakt/iu', $t, $m, PREG_OFFSET_CAPTURE)) continue;
        echo "-- ".basename($f)." @ ".$m[0][1]."\n".mb_substr($clean(substr($t, max(0, $m[0][1] - 400), 3500)), 0, 1800)."\n\n";
    }
}
