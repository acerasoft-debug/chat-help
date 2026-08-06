<?php
/**
 * Fremden Shop importieren — CLI
 * ==============================
 *   php tools/import-shop.php --from=https://fashionbrandshome.com/collections/lacoste --dry
 *   php tools/import-shop.php --from=… --brand=LACOSTE --images
 *   php tools/import-shop.php --from=… --multiplier=1.0 --cat="Polos"
 *
 * Liest eine Shopify-Kollektion vollständig aus: Titel, Beschreibung, Marke,
 * Kategorie, Modellnummer, Größen aus den Varianten, Bilder und die
 * Quell-ID jedes Artikels. Legt Marke und Kategorie dabei automatisch an —
 * beide werden im Katalog aus den Zeilen selbst abgeleitet, es gibt keine
 * getrennte Liste, die man vorher pflegen müsste.
 *
 * RECHTLICHER HINWEIS, EINMAL UND DEUTLICH
 * ----------------------------------------
 * Produktfotos und Beschreibungstexte eines fremden Shops sind in aller Regel
 * urheberrechtlich geschützt; die Datenbank dahinter zusätzlich über §87b
 * UrhG. Sie zu übernehmen ist nur zulässig, wenn Ihnen die Rechte zustehen —
 * etwa weil der Shop Ihnen gehört, Sie Vertriebspartner sind oder eine
 * schriftliche Freigabe vorliegt. Deshalb lädt dieses Werkzeug Bilder NUR mit
 * ausdrücklichem --images, und ohne --images bleiben die erzeugten Grafiken
 * stehen. Die Entscheidung liegt beim Betreiber, nicht beim Werkzeug.
 *
 * PREISE
 * ------
 * Anders als beim B2B-Import (tools/import-live.php, Faktor ×3 auf den
 * Großhandelspreis) sind die Preise eines Endkundenshops BEREITS Endpreise.
 * Der Standardfaktor ist deshalb 1,0. Wer aufschlagen will, setzt
 * --multiplier ausdrücklich.
 *
 * Geschrieben wird ausschließlich in data/retail-imported.json (zusammen-
 * geführt, nicht überschrieben) und nach uploads/. data/listings.json bleibt
 * unberührt.
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
$opt = ['from' => '', 'brand' => '', 'cat' => '', 'images' => false, 'dry' => false,
        'limit' => 0, 'multiplier' => 1.0, 'qty' => 1, 'prefix' => '', 'max_images' => 0];
foreach (array_slice($argv, 1) as $a) {
    if (str_starts_with($a, '--from='))            $opt['from']  = substr($a, 7);
    elseif (str_starts_with($a, '--brand='))       $opt['brand'] = strtoupper(trim(substr($a, 8)));
    elseif (str_starts_with($a, '--cat='))         $opt['cat']   = trim(substr($a, 6));
    elseif (str_starts_with($a, '--limit='))       $opt['limit'] = (int)substr($a, 8);
    elseif (str_starts_with($a, '--multiplier='))  $opt['multiplier'] = max(0.01, (float)substr($a, 13));
    elseif (str_starts_with($a, '--qty='))         $opt['qty']   = max(1, (int)substr($a, 6));
    elseif (str_starts_with($a, '--prefix='))      $opt['prefix'] = preg_replace('/[^a-z0-9-]/', '', strtolower(substr($a, 9))) ?: '';
    elseif (str_starts_with($a, '--max-images=')) $opt['max_images'] = max(0, (int)substr($a, 13));
    elseif ($a === '--images')                     $opt['images'] = true;
    elseif ($a === '--dry')                        $opt['dry']    = true;
}

if ($opt['from'] === '' || !preg_match('#^https?://#', $opt['from'])) {
    echo <<<TXT
    Aufruf:
      php tools/import-shop.php --from=<Kollektions-URL> [Optionen]

      --from=https://shop.tld/collections/lacoste   Shopify-Kollektion
      --brand=LACOSTE       Marke erzwingen (sonst: Feld "vendor" des Shops)
      --cat="Polos"         Kategorie erzwingen (sonst: "product_type")
      --multiplier=1.0      Preisfaktor (Standard 1,0 — Endkundenpreise)
      --qty=1               Stück je verfügbarer Größe (öffentliche API
                            liefert keine echten Bestände)
      --prefix=fbh          Präfix der Artikel-IDs (Standard: aus der Domain)
      --images              Bilder herunterladen — Rechte vorher prüfen
      --max-images=0        Bilder je Artikel; 0 = ALLE (Standard)
      --limit=20            nur die ersten N Artikel (Test)
      --dry                 nichts schreiben, nur berichten

    TXT;
    exit(1);
}

$from = rtrim($opt['from'], '/');
$host = (string)parse_url($from, PHP_URL_HOST);
if ($opt['prefix'] === '') {
    $parts = explode('.', $host);
    $opt['prefix'] = preg_replace('/[^a-z0-9]/', '', strtolower($parts[0] ?? 'shop')) ?: 'shop';
    $opt['prefix'] = substr($opt['prefix'], 0, 8);
}

echo "MAXSALES — Shop-Import" . ($opt['dry'] ? ' (Probelauf)' : '') . "\n";
echo str_repeat('=', 68) . "\n";
echo "Quelle    : {$from}\n";
echo "Faktor    : ×" . rtrim(rtrim(number_format($opt['multiplier'], 2, ',', ''), '0'), ',') . "\n";
echo "Bilder    : " . ($opt['images']
    ? ('werden geladen — ' . ($opt['max_images'] > 0 ? 'max. ' . $opt['max_images'] . ' je Artikel' : 'ALLE je Artikel'))
    : 'NICHT geladen (erzeugte Grafik bleibt)') . "\n";
echo "ID-Präfix : {$opt['prefix']}-\n\n";

// ------------------------------------------------------------------ Helfer
/** HTTP GET mit Browser-Kennung; WAFs blocken sonst häufig. */
function is_get(string $url, int $timeout = 40): ?string
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
        CURLOPT_HTTPHEADER     => ['Accept: application/json, text/html;q=0.9', 'Accept-Language: de,en;q=0.8'],
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($body === false || $code >= 400) {
        fwrite(STDERR, "  ! {$url} → " . ($code ?: $err) . "\n");
        return null;
    }
    return (string)$body;
}

/** HTML-Beschreibung → sauberer Fließtext, Absätze bleiben erhalten. */
function is_text(string $html): string
{
    $s = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
    $s = preg_replace('#<br\s*/?>#i', "\n", $s) ?? $s;
    $s = preg_replace('#</(p|div|li|h[1-6])>#i', "\n\n", $s) ?? $s;
    $s = preg_replace('#<li[^>]*>#i', '· ', $s) ?? $s;
    $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = preg_replace('/[ \t\x{00A0}]+/u', ' ', $s) ?? $s;
    $s = preg_replace('/\n{3,}/', "\n\n", $s) ?? $s;
    return trim($s);
}

/**
 * Kategorien des Quellshops auf unsere Namen abbilden. Trifft nichts zu,
 * bleibt der Originalname stehen — die Kategorie entsteht im Katalog
 * ohnehin aus den Zeilen selbst.
 */
function is_cat(string $raw): string
{
    $k = mb_strtolower(trim($raw));
    if ($k === '') return 'Sonstiges';
    // Reihenfolge und Wortgrenzen sind hier entscheidend: "sweatshirts"
    // enthält die Zeichenfolge "tshirt" und landete ohne \b bei den T-Shirts.
    $map = [
        'polo'                       => 'Polos',
        'hood|sweat|felpa'           => 'Hoodies & Sweatshirts',
        '\bt-?shirts?\b|\btees?\b'   => 'T-Shirts',
        'jean|denim'                 => 'Jeans',
        'trouser|pant|hose|chino'    => 'Trousers',
        'short|bermuda'              => 'Shorts',
        'jacket|coat|mantel|parka|blazer|gilet|vest' => 'Coats & Jackets',
        'knit|jumper|pullover|cardigan|sweater'      => 'Knitwear',
        'shirt|hemd|camicia'         => 'Shirts',
        'dress|kleid'                => 'Dresses',
        'skirt|rock'                 => 'Skirts',
        'swim|bade|beach|bikini'     => 'Swimwear',
        'shoe|sneaker|trainer|boot'  => 'Shoes',
        'bag|tasche|backpack|clutch' => 'Bags',
        'cap|hat|belt|scarf|sock|accessor' => 'Accessories',
        'track|jog'                  => 'Tracksuits',
    ];
    foreach ($map as $re => $label) {
        if (preg_match('/' . $re . '/u', $k)) return $label;
    }
    return mb_convert_case(trim($raw), MB_CASE_TITLE, 'UTF-8');
}

// -------------------------------------------------- Kollektion einlesen
/**
 * Shopify legt jede Kollektion zusätzlich als JSON offen:
 *   /collections/<handle>/products.json?limit=250&page=N
 * Das ist die saubere Quelle — kein HTML-Parsen, alle Varianten und Bilder
 * sind enthalten. Ist sie gesperrt, fallen wir auf die Produktseiten zurück.
 */
$products = [];
$page = 1;
$base = preg_replace('#\?.*$#', '', $from);

while (true) {
    $url  = $base . '/products.json?limit=250&page=' . $page;
    $body = is_get($url);
    if ($body === null) break;

    $j = json_decode($body, true);
    if (!is_array($j) || !isset($j['products']) || !is_array($j['products'])) break;
    if (!$j['products']) break;

    foreach ($j['products'] as $p) $products[] = $p;
    echo "  Seite {$page}: " . count($j['products']) . " Artikel\n";

    if (count($j['products']) < 250) break;
    if ($opt['limit'] && count($products) >= $opt['limit']) break;
    if (++$page > 40) break;                       // Sicherheitsnetz
    usleep(400000);                                 // höflich bleiben
}

if (!$products) {
    // Rückfall: Produktlinks aus dem HTML holen, jede Seite als .json ziehen.
    echo "  products.json nicht verfügbar — versuche Produktseiten\n";
    $html = is_get($from);
    if ($html !== null && preg_match_all('#/products/([a-z0-9][a-z0-9\-_]*)#i', $html, $m)) {
        $origin = (string)parse_url($from, PHP_URL_SCHEME) . '://' . $host;
        foreach (array_unique($m[1]) as $handle) {
            if ($opt['limit'] && count($products) >= $opt['limit']) break;
            $one = is_get($origin . '/products/' . $handle . '.json');
            if ($one === null) continue;
            $j = json_decode($one, true);
            if (is_array($j['product'] ?? null)) { $products[] = $j['product']; echo "  · {$handle}\n"; }
            usleep(350000);
        }
    }
}

if (!$products) {
    echo "\nKein Artikel gelesen. Mögliche Gründe:\n";
    echo "  • Der Shop ist kein Shopify-Shop oder products.json ist gesperrt.\n";
    echo "  • Eine Firewall (Cloudflare o. ä.) blockt Serveranfragen.\n";
    echo "  • Die Kollektions-URL stimmt nicht.\n";
    echo "Prüfen Sie die URL im Browser mit angehängtem /products.json\n";
    exit(1);
}

if ($opt['limit']) $products = array_slice($products, 0, $opt['limit']);
echo "\nGelesen: " . count($products) . " Artikel\n\n";

// ------------------------------------------------------------- abbilden
$rows = [];
$imgOk = $imgSkip = $imgFail = 0;
$noPrice = 0;
$brands = $cats = [];

foreach ($products as $p) {
    $srcId  = (string)($p['id'] ?? '');
    $handle = (string)($p['handle'] ?? '');
    $title  = trim((string)($p['title'] ?? ''));
    if ($title === '' || ($srcId === '' && $handle === '')) continue;

    $brand = $opt['brand'] !== '' ? $opt['brand'] : strtoupper(trim((string)($p['vendor'] ?? '')));
    if ($brand === '') $brand = 'OHNE MARKE';
    $cat = $opt['cat'] !== '' ? $opt['cat'] : is_cat((string)($p['product_type'] ?? ''));

    // Größen aus den Varianten. Die öffentliche API nennt keine echten
    // Bestände, nur available — deshalb je verfügbarer Größe --qty Stück.
    $sizes = [];
    $price = 0.0;
    $skus  = [];
    foreach ((array)($p['variants'] ?? []) as $v) {
        $pv = (float)($v['price'] ?? 0);
        if ($pv > 0 && ($price === 0.0 || $pv < $price)) $price = $pv;
        // Größensuffix gleich hier abschneiden ("L1212-166-M" → "L1212-166").
        // Erst danach den gemeinsamen Anfang bilden: bei Zahlengrößen wie
        // 41/42/43 fräst der gemeinsame Anfang sonst die letzte Ziffer der
        // echten Nummer mit weg ("…-21G-4").
        $vs = trim((string)($v['sku'] ?? ''));
        if ($vs !== '') {
            $lbl = trim((string)($v['option1'] ?? $v['title'] ?? ''));
            if ($lbl !== '' && preg_match('/^(.*?)[-_ .]?' . preg_quote($lbl, '/') . '$/iu', $vs, $mm)) {
                $vs = $mm[1] !== '' ? $mm[1] : $vs;
            }
            $skus[] = $vs;
        }
        if (isset($v['available']) && !$v['available']) continue;

        $label = strtoupper(trim((string)($v['option1'] ?? $v['title'] ?? '')));
        if ($label === '' || $label === 'DEFAULT TITLE') $label = 'ONE';
        $sizes[$label] = ($sizes[$label] ?? 0) + $opt['qty'];
    }

    if ($price <= 0) { $noPrice++; continue; }

    // Modellnummer: viele Shops hängen die Größe an die Varianten-SKU
    // ("L1212-166-M"). Der gemeinsame Anfang aller Varianten ist die
    // eigentliche Nummer; das Größensuffix fällt dabei von selbst weg.
    $sku = $skus ? $skus[0] : '';
    foreach ($skus as $cand) {
        $n = min(strlen($sku), strlen($cand));
        $i = 0;
        while ($i < $n && $sku[$i] === $cand[$i]) $i++;
        $sku = substr($sku, 0, $i);
    }
    $sku = rtrim($sku, "-_/ .");
    if (strlen($sku) < 3) $sku = $skus ? $skus[0] : '';
    if ($sku === '') $sku = strtoupper(substr($handle !== '' ? $handle : $srcId, 0, 24));

    $id = $opt['prefix'] . '-' . vr_slug($handle !== '' ? $handle : ($brand . '-' . $srcId));

    // Bilder
    $images = [];
    foreach ((array)($p['images'] ?? []) as $i => $im) {
        $src = (string)($im['src'] ?? '');
        // Standardmaessig ALLE Bilder eines Artikels. Shopify liefert die
        // Reihenfolge des Shops, das erste ist das Hauptbild.
        if ($src === '') continue;
        if ($opt['max_images'] > 0 && $i >= $opt['max_images']) continue;
        if (!$opt['images']) continue;

        $ext = strtolower(pathinfo((string)parse_url($src, PHP_URL_PATH), PATHINFO_EXTENSION)) ?: 'jpg';
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif'], true)) $ext = 'jpg';
        $rel  = '/uploads/imported/' . $id . '-' . ($i + 1) . '.' . $ext;
        $dest = vr_doc_root() . $rel;

        if (is_file($dest) && filesize($dest) > 0) { $images[] = $rel; $imgSkip++; continue; }
        if ($opt['dry']) { $images[] = $rel; continue; }

        @mkdir(dirname($dest), 0755, true);
        $bin = is_get($src, 60);
        if ($bin === null || strlen($bin) < 512) { $imgFail++; continue; }
        if (@file_put_contents($dest, $bin) !== false) { @chmod($dest, 0644); $images[] = $rel; $imgOk++; }
        else $imgFail++;
        usleep(150000);
    }

    $desc = is_text((string)($p['body_html'] ?? ''));

    $row = [
        'id'           => $id,
        'status'       => 'active',
        'brand'        => $brand,
        'name'         => $title,
        'sku'          => $sku,
        'cat'          => $cat,
        'retail_price' => round($price * $opt['multiplier'], 2),
        'size_stock'   => $sizes ?: ['ONE' => $opt['qty']],
        'images'       => $images,
        'seller_type'  => 'vestra',
        // Quellangaben bleiben stehen: nachvollziehbar, woher die Zeile kommt.
        'origin'       => ['shop' => $host, 'id' => $srcId, 'handle' => $handle,
                           'url'  => 'https://' . $host . '/products/' . $handle],
        'imported_at'  => time(),
    ];
    // Eine echte Beschreibung schaltet die Textmaschine ab (inc/copy.php).
    // Feld leeren, wenn wieder generierter Text gewünscht ist.
    if (mb_strlen($desc) >= 40) $row['copy'] = mb_substr($desc, 0, 4000);

    $rows[] = $row;
    $brands[$brand] = ($brands[$brand] ?? 0) + 1;
    $cats[$cat] = ($cats[$cat] ?? 0) + 1;
}

// -------------------------------------------------------------- Bericht
echo "Übernommen        : " . count($rows) . "\n";
if ($noPrice) echo "  ohne Preis (raus): {$noPrice}\n";
if ($opt['images']) echo "Bilder            : {$imgOk} neu · {$imgSkip} vorhanden · {$imgFail} fehlgeschlagen\n";

echo "\nMarken:\n";
arsort($brands);
foreach ($brands as $b => $n) printf("  %-24s %d\n", $b, $n);

echo "\nKategorien:\n";
arsort($cats);
foreach ($cats as $c => $n) printf("  %-24s %d\n", $c, $n);

echo "\nBeispiele:\n";
foreach (array_slice($rows, 0, 6) as $r) {
    $sz = implode(' · ', array_map(fn($k, $v) => "{$k}×{$v}",
        array_keys($r['size_stock']), array_values($r['size_stock'])));
    printf("  %-14s %-30s %9s  %s\n", $r['brand'],
        mb_substr($r['name'], 0, 30), vr_money((int)round($r['retail_price'] * 100)),
        mb_substr($sz, 0, 26));
}

if ($opt['dry']) {
    echo "\nProbelauf — nichts geschrieben.\n";
    exit(0);
}

// ------------------------------------------------------------ speichern
/**
 * Zusammenführen, nicht überschreiben: derselbe Betreiber kann mehrere
 * Kollektionen und daneben den B2B-Import fahren. Schlüssel ist die id.
 */
$file = 'retail-imported.json';
$merged = 0; $added = 0;
vr_store_mutate($file, static function ($old) use ($rows, &$merged, &$added) {
    $byId = [];
    foreach (is_array($old) ? $old : [] as $r) {
        if (is_array($r) && ($r['id'] ?? '') !== '') $byId[(string)$r['id']] = $r;
    }
    foreach ($rows as $r) {
        if (isset($byId[$r['id']])) $merged++; else $added++;
        $byId[$r['id']] = $r;
    }
    return array_values($byId);
}, []);

echo "\nGespeichert: " . vr_store_path($file) . "\n";
echo "  neu: {$added} · aktualisiert: {$merged}\n";
echo "Der Shop liest diese Datei automatisch mit. Marke und Kategorie entstehen\n";
echo "dabei von selbst — sie werden aus den Zeilen abgeleitet.\n";
