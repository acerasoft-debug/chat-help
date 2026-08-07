<?php
/**
 * Weitere Produktfotos einsammeln — CLI
 * =====================================
 *   php tools/fetch-extra-images.php --dry
 *   php tools/fetch-extra-images.php --base=https://vestrasales.com
 *
 * Sucht zu jedem Artikel, der erst ein oder zwei Fotos hat, WEITERE Aufnahmen
 * und haengt sie an. Drei Quellen, alle auf der eigenen Seite:
 *
 *   1. Der Live-Katalog. data/listings.json ist die Wahrheit; die Stapel im
 *      Repository sind nur der Ausgangsstand. Wer dort spaeter Fotos ergaenzt
 *      hat, findet sie hier wieder.
 *   2. Die Produktseite der Live-Seite. Alles, was dort als /uploads/… haengt,
 *      gehoert zum Artikel — auch wenn es im Katalog-JSON fehlt.
 *   3. Geschwisterdateien. Die vorhandenen Namen folgen erkennbaren Mustern
 *      (…-b.jpg, …-2.jpg, …-back.jpg). Wir probieren die ueblichen Endungen
 *      durch und behalten, was mit 200 antwortet.
 *
 * WAS DIESES WERKZEUG NICHT TUT
 * -----------------------------
 * Es durchsucht NICHT das offene Internet nach Markenfotos. Produktaufnahmen
 * von Balenciaga, Dolce & Gabbana oder Burberry gehoeren dem jeweiligen Haus
 * oder dem Haendler, der sie gemacht hat; sie zu uebernehmen ist in
 * Deutschland ein klassischer Abmahnungsgrund. Ausserdem waere das Ergebnis
 * unzuverlaessig — falsche Farbe, falsches Modelljahr, fremdes Wasserzeichen.
 *
 * Wo kein echtes Foto zu finden ist, fuellt die Galerie mit erzeugten
 * Kadrierungen auf (vr_product_gallery). Die sind erkennbar gezeichnet und
 * geben sich nicht als Aufnahme aus.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Nur über die Kommandozeile.\n";
    exit(1);
}

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/uploads.php';

$opt = ['base' => 'https://vestrasales.com', 'dry' => false, 'limit' => 0, 'want' => 4, 'probe' => true];
foreach (array_slice($argv, 1) as $a) {
    if (str_starts_with($a, '--base='))      $opt['base']  = rtrim(substr($a, 7), '/');
    elseif (str_starts_with($a, '--limit=')) $opt['limit'] = (int)substr($a, 8);
    elseif (str_starts_with($a, '--want='))  $opt['want']  = max(2, (int)substr($a, 7));
    elseif ($a === '--no-probe')             $opt['probe'] = false;
    elseif ($a === '--dry')                  $opt['dry']   = true;
}

echo "MAXSALES — weitere Produktfotos" . ($opt['dry'] ? ' (Probelauf)' : '') . "\n";
echo str_repeat('=', 68) . "\n";
echo "Quelle : {$opt['base']}\n";
echo "Ziel   : bis zu {$opt['want']} Fotos je Artikel\n\n";

// -------------------------------------------------------------- Helfer
function fx_req(string $url, bool $headOnly = false, int $timeout = 30): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_ENCODING       => '',
        CURLOPT_NOBODY         => $headOnly,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 '
                                . '(KHTML, like Gecko) Chrome/124.0 Safari/537.36',
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    return ['ok' => $body !== false && $code < 400, 'code' => $code,
            'body' => is_string($body) ? $body : '', 'type' => $type];
}

/** Alle /uploads/-Pfade aus einem HTML-Dokument. */
function fx_paths(string $html): array
{
    $out = [];
    if (preg_match_all('#(?:src|href|data-src|content)="(/uploads/[A-Za-z0-9._/-]+\.(?:jpe?g|png|webp|avif))"#i', $html, $m)) {
        foreach ($m[1] as $x) $out[$x] = true;
    }
    // Absolute Adressen derselben Seite kommen auch vor.
    if (preg_match_all('#https?://[^"\']+(/uploads/[A-Za-z0-9._/-]+\.(?:jpe?g|png|webp|avif))#i', $html, $m2)) {
        foreach ($m2[1] as $x) $out[$x] = true;
    }
    return array_keys($out);
}

/**
 * Uebliche Geschwister-Endungen. Aus den vorhandenen Namen abgelesen:
 * -b, -2, -f0, -g, -n0, -1 … dazu die sprechenden Varianten.
 */
function fx_siblings(string $rel): array
{
    $dot = strrpos($rel, '.');
    if ($dot === false) return [];
    $stem = substr($rel, 0, $dot);
    $ext  = substr($rel, $dot);

    // Endet der Name schon auf eine Nummer, zaehlen wir sie hoch.
    $base = $stem;
    if (preg_match('/^(.*?)-(\d{1,2})$/', $stem, $m) && (int)$m[2] <= 9) $base = $m[1];

    // Reihenfolge = Trefferwahrscheinlichkeit. Die Liste ist bewusst kurz:
    // jeder Kandidat kostet eine HEAD-Anfrage, und bei 400 Artikeln summiert
    // sich das. Zuerst die Endung des vorhandenen Fotos, denn ein Shop
    // speichert Geschwisterbilder fast immer im selben Format.
    $sfx = ['-2', '-b', '-3', '-back', '-c', '-4', '-detail', '-side'];
    $out = [];
    foreach ($sfx as $s) {
        $cand = $base . $s . $ext;
        if ($cand !== $rel) $out[$cand] = true;
    }
    // Nur für die drei häufigsten Endungen noch die anderen Formate.
    foreach (['-2', '-b', '-3'] as $s) {
        foreach (['.jpg', '.webp'] as $e) {
            if ($e === $ext) continue;
            $cand = $base . $s . $e;
            if ($cand !== $rel) $out[$cand] = true;
        }
    }
    return array_keys($out);
}

// ------------------------------------------------- 1) Live-Katalog lesen
$liveById = [];
foreach (['/data/listings.json', '/listings.json', '/api/listings.php'] as $path) {
    $r = fx_req($opt['base'] . $path);
    if (!$r['ok']) continue;
    $j = json_decode($r['body'], true);
    if (!is_array($j)) continue;
    $rows = isset($j['products']) && is_array($j['products']) ? $j['products'] : $j;
    if (!is_array($rows)) continue;
    foreach ($rows as $x) {
        if (is_array($x) && ($x['id'] ?? '') !== '') $liveById[(string)$x['id']] = $x;
    }
    echo "Live-Katalog: {$path} → " . count($liveById) . " Artikel\n";
    break;
}
if (!$liveById) echo "Live-Katalog: nicht erreichbar (Quelle 1 entfällt)\n";
echo "\n";

// ------------------------------------------------------------- arbeiten
$catalog = vr_catalog();
if ($opt['limit'] > 0) $catalog = array_slice($catalog, 0, $opt['limit'], true);

$added = 0; $dl = 0; $fail = 0; $touched = 0;
$fromLive = $fromPage = $fromProbe = 0;
$patch = [];

foreach ($catalog as $id => $p) {
    $have = array_values(array_filter((array)$p['images'], 'strlen'));
    if (count($have) >= $opt['want']) continue;

    $found = $have;
    $need  = static fn() => count($found) < $opt['want'];

    // Quelle 1 — Live-Katalog
    if ($need() && isset($liveById[$id])) {
        foreach ((array)($liveById[$id]['images'] ?? []) as $x) {
            $x = (string)$x;
            if (preg_match('#^/uploads/[A-Za-z0-9._/-]+$#', $x) && !in_array($x, $found, true)) {
                $found[] = $x; $fromLive++;
                if (!$need()) break;
            }
        }
    }

    // Quelle 2 — Produktseite
    if ($need()) {
        foreach (['/product.php?id=' . rawurlencode($id), '/p/' . rawurlencode($id)] as $u) {
            $r = fx_req($opt['base'] . $u);
            if (!$r['ok'] || !str_contains(strtolower($r['type']), 'html')) continue;
            foreach (fx_paths($r['body']) as $x) {
                if (!in_array($x, $found, true)) {
                    $found[] = $x; $fromPage++;
                    if (!$need()) break;
                }
            }
            break;
        }
        usleep(200000);
    }

    // Quelle 3 — Geschwisterdateien abklopfen
    if ($need() && $opt['probe'] && $have) {
        foreach (fx_siblings($have[0]) as $cand) {
            if (!$need()) break;
            if (in_array($cand, $found, true)) continue;
            $r = fx_req($opt['base'] . $cand, true, 15);
            if ($r['ok'] && str_contains(strtolower($r['type']), 'image')) {
                $found[] = $cand; $fromProbe++;
            }
            usleep(60000);
        }
    }

    $new = array_values(array_diff($found, $have));
    if (!$new) continue;

    // Herunterladen
    $kept = $have;
    foreach ($new as $rel) {
        $dest = vr_doc_root() . $rel;
        if (is_file($dest) && filesize($dest) > 0) { $kept[] = $rel; continue; }
        if ($opt['dry']) { $kept[] = $rel; continue; }

        @mkdir(dirname($dest), 0755, true);
        $r = fx_req($opt['base'] . $rel, false, 60);
        if (!$r['ok'] || strlen($r['body']) < 512) { $fail++; continue; }
        if (@file_put_contents($dest, $r['body']) !== false) { @chmod($dest, 0644); $kept[] = $rel; $dl++; }
        else $fail++;
        usleep(120000);
    }

    if (count($kept) > count($have)) {
        $patch[$id] = $kept;
        $added += count($kept) - count($have);
        $touched++;
        printf("  %-40s %d → %d\n", mb_substr($p['brand'] . ' ' . vr_card_name($p), 0, 40),
               count($have), count($kept));
    }
}

echo "\n" . str_repeat('-', 68) . "\n";
printf("Artikel ergänzt : %d\n", $touched);
printf("Fotos gefunden  : %d  (Katalog %d · Produktseite %d · Geschwister %d)\n",
       $added, $fromLive, $fromPage, $fromProbe);
if (!$opt['dry']) printf("Heruntergeladen : %d neu · %d fehlgeschlagen\n", $dl, $fail);

if ($opt['dry']) { echo "\nProbelauf — nichts geschrieben.\n"; exit(0); }
if (!$patch)     { echo "\nNichts zu ergänzen.\n"; exit(0); }

// -------------------------------------------------------- zurückschreiben
$upd = 0;
vr_store_mutate('retail-imported.json', static function ($rows) use ($patch, &$upd) {
    if (!is_array($rows)) return null;
    foreach ($rows as $i => $r) {
        $id = (string)($r['id'] ?? '');
        if (!isset($patch[$id])) continue;
        $rows[$i]['images'] = $patch[$id];
        $upd++;
    }
    return $upd > 0 ? $rows : null;
}, []);

echo "\nGespeichert: " . vr_store_path('retail-imported.json') . " ({$upd} Artikel aktualisiert)\n";
$rest = count($patch) - $upd;
if ($rest > 0) {
    echo "Hinweis: {$rest} Artikel stammen aus data/listings.json und werden dort\n";
    echo "         nicht angefasst — diese Datei bleibt schreibgeschützt.\n";
}
