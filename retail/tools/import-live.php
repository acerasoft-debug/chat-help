<?php
/**
 * Canlı katalogdan içe aktarma — CLI
 * ==================================
 *   php tools/import-live.php --from=/home/user/public_html          # aynı sunucu (dosyadan)
 *   php tools/import-live.php --from=https://vestrasales.com         # başka sunucu (HTTP)
 *   php tools/import-live.php --from=... --images                    # görselleri de kopyala
 *   php tools/import-live.php --from=... --extra=ek-gorseller.json   # elle verilen ek görseller
 *   php tools/import-live.php --dry                                  # sadece raporla
 *
 * ÖNCE ŞUNU BİL: perakende katmanı canlı siteyle AYNI sunucuya kurulduğunda bu
 * araca gerek YOKTUR. vr_catalog() zaten ~/public_html/data/listings.json'u
 * doğrudan okur, yani tüm ürünler kendiliğinden vitrine düşer ve fiyatları
 * retail_multiplier (şu an ×3) ile hesaplanır. Bu araç iki durum için var:
 *
 *   1) Perakende AYRI bir sunucuda/domainde duracaksa — katalogu ve görselleri
 *      oraya kopyalar.
 *   2) Kataloğun donmuş bir kopyası istenirse (yedek, test, staging).
 *
 * Yazma yalnızca kendi dosyalarımıza olur: data/retail-imported.json ve
 * uploads/. Canlı listings.json'a bu araç da DOKUNMAZ.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Nur über die Kommandozeile.\n";
    exit(1);
}

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/uploads.php';

// ------------------------------------------------------------------ argümanlar
$opt = ['from' => '', 'extra' => '', 'images' => false, 'dry' => false, 'limit' => 0];
foreach (array_slice($argv, 1) as $a) {
    if (str_starts_with($a, '--from='))  $opt['from']  = substr($a, 7);
    elseif (str_starts_with($a, '--extra=')) $opt['extra'] = substr($a, 8);
    elseif (str_starts_with($a, '--limit=')) $opt['limit'] = (int)substr($a, 8);
    elseif ($a === '--images') $opt['images'] = true;
    elseif ($a === '--dry')    $opt['dry']    = true;
}

if ($opt['from'] === '') {
    echo <<<TXT
    Aufruf:
      php tools/import-live.php --from=<Pfad|URL> [--images] [--extra=datei.json] [--dry]

      --from=/home/user/public_html     lokaler Pfad der Live-Installation
      --from=https://vestrasales.com    entfernte Installation über HTTPS
      --images                          Bilddateien ebenfalls kopieren/laden
      --extra=datei.json                zusätzliche Bild-URLs je Artikel
      --limit=50                        nur die ersten N Artikel (Test)
      --dry                             nichts schreiben, nur berichten

    Hinweis: Läuft der Shop auf demselben Server wie der Live-Katalog, ist
    dieser Import überflüssig — data/listings.json wird direkt gelesen.

    TXT;
    exit(1);
}

$from   = rtrim($opt['from'], '/');
$remote = str_starts_with($from, 'http://') || str_starts_with($from, 'https://');

echo "MAXSALES — Katalogimport" . ($opt['dry'] ? ' (Probelauf)' : '') . "\n";
echo str_repeat('=', 66) . "\n";
echo "Quelle : {$from}" . ($remote ? ' (HTTP)' : ' (lokal)') . "\n";
echo "Faktor : ×" . vr_config('retail_multiplier') . " auf den Großhandelspreis\n\n";

// --------------------------------------------------------------- yardımcılar
/** HTTP GET — proxy/WAF arkasındaki siteler için tarayıcı kimliğiyle. */
function il_get(string $url, int $timeout = 40): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 4,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 '
                                . '(KHTML, like Gecko) Chrome/126.0 Safari/537.36',
    ]);
    $body   = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($body === false) { fwrite(STDERR, "  netzwerk: {$err}\n"); return null; }
    if ($status < 200 || $status >= 300) { fwrite(STDERR, "  HTTP {$status} für {$url}\n"); return null; }
    return (string)$body;
}

/** Kaynaktan katalogu oku (dosya ya da HTTP). */
function il_load_catalog(string $from, bool $remote): ?array
{
    $candidates = $remote
        ? ["{$from}/data/listings.json", "{$from}/listings.json", "{$from}/api/listings.php"]
        : ["{$from}/data/listings.json", "{$from}/listings.json"];

    foreach ($candidates as $src) {
        $raw = $remote ? il_get($src) : (is_readable($src) ? (string)file_get_contents($src) : null);
        if ($raw === null || $raw === '') continue;

        $d = json_decode($raw, true);
        if (is_array($d) && $d) {
            echo "Katalog gefunden: {$src} (" . count($d) . " Zeilen)\n";
            return $d;
        }
    }
    return null;
}

// ------------------------------------------------------------------ katalog
$rows = il_load_catalog($from, $remote);
if ($rows === null) {
    fwrite(STDERR, "FEHLER: Katalog nicht lesbar.\n");
    fwrite(STDERR, $remote
        ? "  Bei HTTP-Quellen liegt data/ oft hinter einer Sperre (403). Dann den Import\n"
        . "  direkt AUF dem Live-Server mit --from=/home/<user>/public_html ausführen.\n"
        : "  Pfad prüfen: erwartet wird <Pfad>/data/listings.json\n");
    exit(1);
}

if ($opt['limit'] > 0) $rows = array_slice($rows, 0, $opt['limit']);

// ---------------------------------------------------- elle verilen ek görseller
/**
 * Biçim: { "<urun-id veya SKU>": ["https://…/1.jpg", "https://…/2.jpg"] }
 * Bunlar operatörün KENDİ sağladığı, kullanım hakkına sahip olduğu görsellerdir
 * — araç kendiliğinden internette görsel aramaz (bkz. README, telif notu).
 */
$extra = [];
if ($opt['extra'] !== '') {
    $e = json_decode((string)@file_get_contents($opt['extra']), true);
    if (!is_array($e)) { fwrite(STDERR, "FEHLER: --extra Datei ist kein JSON-Objekt\n"); exit(1); }
    $extra = $e;
    echo "Zusatzbilder: " . count($extra) . " Einträge aus " . $opt['extra'] . "\n";
}

// --------------------------------------------------------------------- işle
$docRoot  = vr_doc_root();
$out      = [];
$imgOk    = 0;
$imgFail  = 0;
$imgSkip  = 0;
$noPrice  = 0;
$noImage  = 0;

foreach ($rows as $row) {
    if (!is_array($row)) continue;

    $id = trim((string)($row['id'] ?? ''));
    if ($id === '') continue;

    // Fiyat: listings.json'daki toptan birim fiyat. Perakende fiyatı BURADA
    // sabitlemiyoruz — katalog katmanı retail_multiplier ile hesaplasın ki
    // çarpanı değiştirdiğinde tüm vitrin birlikte değişsin.
    if ((float)($row['list'] ?? 0) <= 0 && (float)($row['retail_price'] ?? 0) <= 0) {
        $noPrice++;
        continue;
    }

    // ---- görseller
    $images = [];
    foreach ((array)($row['images'] ?? []) as $img) {
        if (!is_string($img) || !preg_match('#^/uploads/[A-Za-z0-9._/-]+$#', $img)) continue;

        if (!$opt['images']) { $images[] = $img; continue; }

        $dest = $docRoot . $img;
        if (is_file($dest)) { $images[] = $img; $imgSkip++; continue; }

        if ($opt['dry']) { $images[] = $img; continue; }

        @mkdir(dirname($dest), 0755, true);
        $bin = $remote ? il_get($from . $img, 60)
                       : (is_readable($from . $img) ? (string)file_get_contents($from . $img) : null);

        if ($bin === null || strlen($bin) < 512) { $imgFail++; continue; }
        // İçerik gerçekten görsel mi? Yanlış yapılandırılmış bir sunucu HTML
        // hata sayfası döndürebiliyor — onu .jpg diye kaydetmeyelim.
        if (@getimagesizefromstring($bin) === false) { $imgFail++; continue; }

        if (@file_put_contents($dest, $bin) !== false) { @chmod($dest, 0644); $images[] = $img; $imgOk++; }
        else $imgFail++;
    }

    // ---- operatörün eklediği görseller
    $key = $id;
    $sku = trim((string)($row['sku'] ?? ''));
    $urls = (array)($extra[$key] ?? $extra[$sku] ?? []);
    foreach ($urls as $u) {
        if (!is_string($u)) continue;
        if (str_starts_with($u, '/uploads/')) { $images[] = $u; continue; }   // zaten yerel
        if (!preg_match('#^https?://#', $u)) continue;

        $ext = strtolower(pathinfo(parse_url($u, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) $ext = 'jpg';

        $rel  = '/uploads/imported/' . vr_slug($id) . '-' . substr(sha1($u), 0, 8) . '.' . $ext;
        $dest = $docRoot . $rel;

        if (is_file($dest)) { $images[] = $rel; $imgSkip++; continue; }
        if ($opt['dry'])    { $images[] = $rel; continue; }

        @mkdir(dirname($dest), 0755, true);
        vr_upload_harden(dirname($dest));

        $bin = il_get($u, 60);
        if ($bin === null || @getimagesizefromstring($bin) === false) { $imgFail++; continue; }
        if (@file_put_contents($dest, $bin) !== false) { @chmod($dest, 0644); $images[] = $rel; $imgOk++; }
        else $imgFail++;
    }

    $images = array_values(array_unique($images));
    if (!$images) $noImage++;

    // Satırı olduğu gibi taşıyoruz: alan adları zaten katalog katmanının
    // beklediği biçimde (id/brand/name/cat/sku/list/sizes/…).
    $row['images'] = $images;
    $row['imported_at'] = time();
    $out[] = $row;
}

// ------------------------------------------------------------------- kaydet
echo "\n";
printf("Artikel gelesen      : %d\n", count($rows));
printf("Artikel übernommen   : %d\n", count($out));
printf("  ohne Preis (raus)  : %d\n", $noPrice);
printf("  ohne Bild          : %d  (generierte Grafik springt ein)\n", $noImage);
if ($opt['images']) {
    printf("Bilder geladen       : %d neu · %d vorhanden · %d fehlgeschlagen\n", $imgOk, $imgSkip, $imgFail);
}

// Örnek fiyatlandırma — çarpanın ne yaptığını gözle görelim.
echo "\nPreisbeispiele (Großhandel → Verkaufspreis):\n";
$shown = 0;
foreach ($out as $r) {
    $w = (float)($r['list'] ?? 0);
    if ($w <= 0) continue;
    printf("  %-34s %8s  →  %s\n",
        mb_substr(($r['brand'] ?? '') . ' ' . ($r['name'] ?? ''), 0, 34),
        number_format($w, 2, ',', '.') . ' €',
        vr_money(vr_charm_round((int)round($w * (float)vr_config('retail_multiplier') * 100))));
    if (++$shown >= 8) break;
}

if ($opt['dry']) {
    echo "\nProbelauf — nichts geschrieben.\n";
    exit(0);
}

vr_store_backup('retail-imported.json');
$ok = vr_store_write('retail-imported.json', $out);

echo "\n" . ($ok
    ? "Gespeichert: " . vr_data_dir() . "/retail-imported.json (" . count($out) . " Artikel)\n"
      . "Der Shop liest diese Datei automatisch mit — ohne data/listings.json zu verändern.\n"
    : "FEHLER beim Schreiben.\n");

exit($ok ? 0 : 1);
