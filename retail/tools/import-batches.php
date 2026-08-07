<?php
/**
 * Katalog aus den Repository-Stapeln importieren — CLI
 * ====================================================
 *   php tools/import-batches.php --dry
 *   php tools/import-batches.php --images
 *   php tools/import-batches.php --from=../product-batches --base=https://vestrasales.com --images
 *
 * Die B2B-Artikel dieses Projekts liegen als JSON-Stapel im Repository
 * (product-batches/*.json) — genau in der Form, die vr_normalize_product()
 * erwartet: id, brand, name, cat, sku, list, sizes, images, desc.
 *
 * Dieses Werkzeug führt sie zusammen und legt sie in die Katalogdatei der
 * Ladenseite. Damit steht der volle Bestand auch dort bereit, wo die Live-
 * Installation nicht danebenliegt — auf einem Runner, einem Testserver oder
 * einer zweiten Domain.
 *
 * PREIS
 * -----
 * Übernommen wird der Großhandelspreis aus "list". Den Endpreis rechnet der
 * Katalog daraus mit retail_multiplier (×3) und der Charme-Rundung; feste
 * Outlet-Preise aus price_rules gehen weiterhin vor. Hier wird also bewusst
 * NICHT gerundet und NICHT multipliziert — sonst läge der Faktor an zwei
 * Stellen und würde beim nächsten Lauf doppelt greifen.
 *
 * BILDER
 * ------
 * Die Stapel verweisen auf /uploads/… — die Fotos liegen auf der eigenen
 * Live-Seite. Mit --images werden sie von dort geladen und unter uploads/
 * abgelegt. Das sind eigene Aufnahmen des Betreibers, kein fremdes Material.
 * Fehlt ein Foto, bleibt die erzeugte Grafik stehen; nichts bricht.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Nur über die Kommandozeile.\n";
    exit(1);
}

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/uploads.php';

// ------------------------------------------------------------------ Argumente
$opt = [
    'from'   => realpath(__DIR__ . '/../../product-batches') ?: __DIR__ . '/../../product-batches',
    'base'   => 'https://vestrasales.com',
    'images' => false,
    'dry'    => false,
    'limit'  => 0,
];
foreach (array_slice($argv, 1) as $a) {
    if (str_starts_with($a, '--from='))       $opt['from']  = rtrim(substr($a, 7), '/');
    elseif (str_starts_with($a, '--base='))   $opt['base']  = rtrim(substr($a, 7), '/');
    elseif (str_starts_with($a, '--limit='))  $opt['limit'] = (int)substr($a, 8);
    elseif ($a === '--images')                $opt['images'] = true;
    elseif ($a === '--dry')                   $opt['dry']    = true;
}

echo "MAXSALES — Stapelimport" . ($opt['dry'] ? ' (Probelauf)' : '') . "\n";
echo str_repeat('=', 68) . "\n";
echo "Quelle : {$opt['from']}\n";
echo "Bilder : " . ($opt['images'] ? "werden von {$opt['base']} geladen" : 'NICHT geladen') . "\n";
echo "Faktor : ×" . vr_config('retail_multiplier') . " (rechnet der Katalog aus \"list\")\n\n";

if (!is_dir($opt['from'])) {
    echo "Verzeichnis nicht gefunden: {$opt['from']}\n";
    exit(1);
}

/** HTTP GET mit Browser-Kennung. */
function ib_get(string $url, int $timeout = 45): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 4,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 '
                                . '(KHTML, like Gecko) Chrome/124.0 Safari/537.36',
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return ($body === false || $code >= 400) ? null : (string)$body;
}

// -------------------------------------------------------------- einlesen
$files = glob($opt['from'] . '/*.json') ?: [];
sort($files);

$byId   = [];
$dupes  = 0;
$skipped = 0;

foreach ($files as $f) {
    $raw = json_decode((string)file_get_contents($f), true);
    if (!is_array($raw)) { echo "  ! ungültiges JSON: " . basename($f) . "\n"; continue; }
    $rows = isset($raw['products']) && is_array($raw['products']) ? $raw['products'] : $raw;
    if (!is_array($rows)) continue;

    $n = 0;
    foreach ($rows as $r) {
        if (!is_array($r) || trim((string)($r['id'] ?? '')) === '') { $skipped++; continue; }
        $id = trim((string)$r['id']);
        // Spätere Stapel korrigieren frühere: das ist die Reihenfolge, in der
        // auch add-products.yml die Dateien anwendet.
        if (isset($byId[$id])) { $dupes++; $byId[$id] = array_merge($byId[$id], $r); }
        else                   { $byId[$id] = $r; }
        $n++;
    }
    printf("  %-34s %3d Zeilen\n", basename($f), $n);
}

if ($opt['limit'] > 0) $byId = array_slice($byId, 0, $opt['limit'], true);

echo "\nGelesen  : " . count($byId) . " Artikel"
   . ($dupes ? " ({$dupes} Korrektur(en) zusammengeführt)" : '')
   . ($skipped ? " · {$skipped} ohne id übersprungen" : '') . "\n";

// -------------------------------------------------------------- abbilden
$out = [];
$imgOk = $imgSkip = $imgFail = 0;
$noPrice = 0;
$brands = $cats = [];

foreach ($byId as $id => $r) {
    $list = (float)($r['list'] ?? 0);
    if ($list <= 0 && (float)($r['retail_price'] ?? 0) <= 0) { $noPrice++; continue; }

    // Bilder holen. Pfade sind seitenrelativ (/uploads/…) und liegen auf der
    // eigenen Live-Seite.
    $images = [];
    foreach ((array)($r['images'] ?? []) as $rel) {
        $rel = (string)$rel;
        if ($rel === '' || !preg_match('#^/uploads/[A-Za-z0-9._/-]+$#', $rel)) continue;

        if (!$opt['images']) { $images[] = $rel; continue; }

        $dest = vr_doc_root() . $rel;
        if (is_file($dest) && filesize($dest) > 0) { $images[] = $rel; $imgSkip++; continue; }
        if ($opt['dry']) { $images[] = $rel; continue; }

        @mkdir(dirname($dest), 0755, true);
        $bin = ib_get($opt['base'] . $rel, 60);
        if ($bin === null || strlen($bin) < 512) { $imgFail++; continue; }
        if (@file_put_contents($dest, $bin) !== false) { @chmod($dest, 0644); $images[] = $rel; $imgOk++; }
        else $imgFail++;
        usleep(80000);
    }

    $brand = trim((string)($r['brand'] ?? ''));
    $cat   = trim((string)($r['cat'] ?? ''));

    $row = [
        'id'     => (string)$id,
        'status' => (string)($r['status'] ?? ''),
        'brand'  => $brand !== '' ? $brand : '—',
        'name'   => trim((string)($r['name'] ?? '')),
        'sku'    => trim((string)($r['sku'] ?? '')),
        'cat'    => $cat !== '' ? $cat : 'Sonstiges',
        // "list" bleibt Großhandel — der Faktor gehört in den Katalog, nicht hierher.
        'list'   => $list,
        'sizes'  => (string)($r['sizes'] ?? ''),
        'images' => $images,
        'desc'   => trim((string)($r['desc'] ?? '')),
        'origin' => ['source' => 'product-batches', 'id' => (string)$id],
        'imported_at' => time(),
    ];
    if (isset($r['retail_price']) && (float)$r['retail_price'] > 0) $row['retail_price'] = (float)$r['retail_price'];
    if (isset($r['rrp']))       $row['rrp']       = (float)$r['rrp'];
    if (isset($r['condition'])) $row['condition'] = (string)$r['condition'];

    $out[] = $row;
    $brands[$row['brand']] = ($brands[$row['brand']] ?? 0) + 1;
    $cats[$row['cat']]     = ($cats[$row['cat']] ?? 0) + 1;
}

// --------------------------------------------------------------- Bericht
echo "Übernommen: " . count($out) . "\n";
if ($noPrice) echo "  ohne Preis (raus): {$noPrice}\n";
if ($opt['images']) echo "Bilder    : {$imgOk} neu · {$imgSkip} vorhanden · {$imgFail} fehlgeschlagen\n";

echo "\nMarken:\n";
arsort($brands);
foreach ($brands as $b => $n) printf("  %-24s %d\n", $b, $n);

echo "\nKategorien:\n";
arsort($cats);
foreach ($cats as $c => $n) printf("  %-24s %d\n", $c, $n);

echo "\nPreisbeispiele (Großhandel → Verkaufspreis):\n";
foreach (array_slice($out, 0, 8) as $r) {
    $cents = vr_price_rule($r['brand'], $r['cat'], $r['name'])
          ?? vr_derive_retail_cents((float)$r['list']);
    printf("  %-30s %8s €  →  %10s\n",
        mb_substr($r['brand'] . ' ' . $r['name'], 0, 30),
        number_format((float)$r['list'], 2, ',', '.'), vr_money($cents));
}

if ($opt['dry']) {
    echo "\nProbelauf — nichts geschrieben.\n";
    exit(0);
}

// ------------------------------------------------------------ speichern
/**
 * Zusammenführen statt überschreiben: neben diesen Stapeln kann eine
 * importierte Fremdkollektion in derselben Datei liegen. Schlüssel ist die id.
 */
$merged = 0; $added = 0;
vr_store_mutate('retail-imported.json', static function ($old) use ($out, &$merged, &$added) {
    $map = [];
    foreach (is_array($old) ? $old : [] as $r) {
        if (is_array($r) && ($r['id'] ?? '') !== '') $map[(string)$r['id']] = $r;
    }
    foreach ($out as $r) {
        if (isset($map[$r['id']])) $merged++; else $added++;
        $map[$r['id']] = $r;
    }
    return array_values($map);
}, []);

echo "\nGespeichert: " . vr_store_path('retail-imported.json') . "\n";
echo "  neu: {$added} · aktualisiert: {$merged}\n";
