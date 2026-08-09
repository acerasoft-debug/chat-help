<?php
/**
 * Kontakt sayfalarını panellere ayır — CLI
 * ========================================
 *   php tools/split-contact-sheets.php --dry
 *   php tools/split-contact-sheets.php
 *
 * NEDEN
 * -----
 * Toptan kataloğun fotoğrafları tek tek çekim değil: bir ürünün ön, arka ve
 * detay görüntüsü TEK bir geniş dosyada yan yana duruyor, en altta da model
 * kodu basılı beyaz bir şerit var:
 *
 *   +-----------+-----------+-----------+
 *   |   ön      |   arka    |  detay    |
 *   +-----------+-----------+-----------+
 *   |      BALMAIN AH0EG000 BC27        |   <- toptancı şeridi
 *   +-----------------------------------+
 *
 * Yani aylardır aradığımız "ikinci, üçüncü, dördüncü fotoğraf" internette
 * değil, elimizdeki dosyanın içinde. Onları dışarı çıkarmak hem galeriyi
 * dolduruyor hem de kart görselini düzeltiyor: 3:1 oranında bir şerit ürün
 * kartında ya çok küçük kalıyor ya da ortadan kırpılıp yanlış paneli
 * gösteriyordu.
 *
 * Alt şeritteki model kodu ayrıca perakende sayfasında toptancı izlenimi
 * veriyordu; kesilince o da gidiyor.
 *
 * NASIL
 * -----
 * 1. Alttaki yazı şeridi: görüntünün alt %28'inde, tamamen beyaz yatay bir
 *    ayraç aranıyor. Altında içerik varsa (yazı) oradan kesiliyor.
 * 2. Dikey oluklar: baştan sona beyaz olan sütun kümeleri panel sınırı.
 *    Yeterince geniş olan her oluktan bölünüyor.
 * 3. Kabul kriteri: 2–5 panel, her biri en az 200px geniş ve oranı 0,45–1,6
 *    arasında. Tutmuyorsa dosyaya DOKUNULMUYOR — yanlış kesmektense hiç
 *    kesmemek iyidir.
 *
 * İSİMLENDİRME — ÖNEMLİ
 * ---------------------
 * Paneller "-p1.jpg", "-p2.jpg" … olarak yazılıyor, orijinalin yanına.
 *
 * İlk denemede "-a", "-b" kullanılmıştı ve bu bir dosya kaybına yol açtı:
 * toptancının kendi isimlendirmesinde "-b" zaten "back" demek, yani
 * "xyz-b.jpg" GERÇEK bir dosya. "xyz.jpg"nin ikinci paneli de aynı ada
 * yazılınca orijinalin üstüne biniyordu. Bu yüzden hem ek "-pN" oldu hem de
 * yazmadan önce hedefin varlığı kontrol ediliyor: dosya varsa ve bizim
 * ürettiğimiz değilse ona DOKUNULMUYOR.
 *
 * Orijinal dosya hiçbir koşulda silinmiyor. Kesim kötü çıkarsa "-pN"
 * dosyalarını silip katalogu yeniden içe aktarmak yeterli.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Nur über die Kommandozeile.\n";
    exit(1);
}

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/uploads.php';

$opt = ['dry' => false, 'limit' => 0, 'quality' => 88, 'force' => false];
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--dry')                       $opt['dry']   = true;
    elseif ($a === '--force')                 $opt['force'] = true;
    elseif (str_starts_with($a, '--limit='))  $opt['limit'] = (int)substr($a, 8);
    elseif (str_starts_with($a, '--quality=')) $opt['quality'] = max(60, min(95, (int)substr($a, 10)));
}

echo "MAXSALES — Kontaktbogen zerlegen" . ($opt['dry'] ? ' (Probelauf)' : '') . "\n";
echo str_repeat('=', 68) . "\n\n";

// ------------------------------------------------------------------ Bausteine

/** Bild laden, egal in welchem Format. */
function cs_load(string $file): ?\GdImage
{
    $s = @getimagesize($file);
    if (!$s) return null;
    $im = match ($s[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($file),
        IMAGETYPE_PNG  => @imagecreatefrompng($file),
        IMAGETYPE_WEBP => @imagecreatefromwebp($file),
        IMAGETYPE_AVIF => function_exists('imagecreatefromavif') ? @imagecreatefromavif($file) : false,
        default        => false,
    };
    return $im instanceof \GdImage ? $im : null;
}

/**
 * Helligkeitsprofil je Spalte bzw. Zeile.
 * Wir tasten in Schritten ab statt jedes Pixel zu lesen — bei 3500×1800 sind
 * das sonst sechs Millionen Aufrufe je Bild, und für „ist diese Spalte weiß“
 * reicht jede vierte Zeile vollkommen.
 */
function cs_profile(\GdImage $im, bool $columns, int $step = 4): array
{
    $w = imagesx($im); $h = imagesy($im);
    $outer = $columns ? $w : $h;
    $inner = $columns ? $h : $w;
    $out = [];
    for ($i = 0; $i < $outer; $i++) {
        $dark = 0; $n = 0;
        for ($j = 0; $j < $inner; $j += $step) {
            $rgb = imagecolorat($im, $columns ? $i : $j, $columns ? $j : $i);
            // Ortalama parlaklık; 238'in altını "içerik" sayıyoruz. Ürün
            // fotoğraflarının zemini saf beyaz değil, hafif gri olabiliyor.
            $lum = (($rgb >> 16 & 255) + ($rgb >> 8 & 255) + ($rgb & 255)) / 3;
            if ($lum < 238) $dark++;
            $n++;
        }
        $out[$i] = $n ? $dark / $n : 0.0;
    }
    return $out;
}

/**
 * Untere Beschriftungsleiste finden. Rückgabe: neue Höhe (oder die alte,
 * wenn keine Leiste gefunden wurde).
 */
function cs_trim_caption(array $rows, int $h): int
{
    $zone = (int)($h * 0.72);            // nur im unteren Viertel suchen
    $bestCut = $h;
    $runStart = null;

    for ($y = $zone; $y < $h; $y++) {
        $white = $rows[$y] < 0.01;
        if ($white) {
            if ($runStart === null) $runStart = $y;
            continue;
        }
        // Inhalt gefunden. War davor ein weißer Streifen von mindestens
        // 8 Pixeln, ist das der Trenner über der Beschriftung.
        if ($runStart !== null && $y - $runStart >= 8) {
            $bestCut = $runStart;
            break;
        }
        $runStart = null;
    }
    // Nur zuschneiden, wenn die Leiste plausibel klein ist.
    return ($bestCut < $h && $h - $bestCut <= $h * 0.28) ? $bestCut : $h;
}

/**
 * Weißrinnen finden und daraus Bandgrenzen bauen.
 * Rückgabe: Liste von [start, länge].
 *
 * Die Funktion kennt keine Achse: mit dem Spaltenprofil liefert sie senkrechte
 * Panels, mit dem Zeilenprofil waagerechte. Das ist der Grund, warum sie hier
 * so allgemein steht — die Bögen des Großhändlers sind nicht nur nebeneinander
 * gesetzt, sondern oft ein Raster aus zwei bis drei Reihen.
 */
function cs_bands(array $prof, int $len, float $thr = 0.005): array
{
    return cs_panels($prof, $len, $thr);
}

/**
 * Senkrechte Weißrinnen finden und daraus Panelgrenzen bauen.
 * Rückgabe: Liste von [x, breite].
 */
function cs_gutters(array $prof, int $len, float $thr = 0.005): array
{
    $minGutter = max(6, (int)($len * 0.004));

    $gutters = []; $start = null;
    for ($x = 0; $x < $len; $x++) {
        if ($prof[$x] < $thr) {
            if ($start === null) $start = $x;
        } elseif ($start !== null) {
            if ($x - $start >= $minGutter) $gutters[] = [$start, $x - $start];
            $start = null;
        }
    }
    if ($start !== null && $len - $start >= $minGutter) $gutters[] = [$start, $len - $start];

    // Randrinnen sind kein Trenner, sondern Rand.
    return array_values(array_filter($gutters, static fn($g) => $g[0] > $len * 0.06 && $g[0] + $g[1] < $len * 0.94));
}

function cs_panels(array $cols, int $w, float $thr = 0.005): array
{
    $inner = cs_gutters($cols, $w, $thr);
    if (!$inner) return [];

    // Aus den Rinnenmitten Schnitte machen.
    $cuts = array_map(static fn($g) => (int)($g[0] + $g[1] / 2), $inner);
    $edges = array_merge([0], $cuts, [$w]);

    $panels = [];
    for ($i = 0; $i < count($edges) - 1; $i++) {
        $panels[] = [$edges[$i], $edges[$i + 1] - $edges[$i]];
    }
    return $panels;
}

/** Bir dikdörtgenin içinde ne kadar "içerik" var (0 = bembeyaz). */
function cs_density(\GdImage $im, int $x, int $y, int $w, int $h): float
{
    $sub = imagecrop($im, ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h]);
    if (!$sub instanceof \GdImage) return 1.0;
    $rows = cs_profile($sub, false, 6);
    imagedestroy($sub);
    return $rows ? array_sum($rows) / count($rows) : 1.0;
}

/**
 * Bir kareyi hücrelere ÖZYİNELEMELİ olarak ayır.
 *
 * NEDEN ÖZYİNELEME
 * ----------------
 * Önceki iki sürüm önce sütun bantlarını, sonra satır bantlarını buluyor ve
 * kesişimlerini hücre sayıyordu. Bu yalnızca DÜZGÜN ızgarada doğru. Toptancının
 * bogenlerinin çoğu düzgün değil: ölçtüğümüz bir Burberry karesinde üstte iki
 * geniş panel, altta iki dar panel var; kesişim yöntemi orada dört yerine
 * yamuk hücreler üretiyor ve doğrulamaya takılıyor. Sonuç: kare hiç
 * bölünmüyor ve üç ürünlü bir kadraj ürün kartı oluyordu.
 *
 * Özyineleme bunu doğal olarak çözüyor: her adımda EN GENİŞ iç oluk hangi
 * eksende ise oradan ikiye bölünüyor, iki parçanın her biri kendi içinde
 * yeniden aranıyor. Düzgün ızgara da, yamuk yerleşim de aynı yoldan çıkıyor.
 *
 * Derinlik üçle sınırlı (en çok sekiz hücre) ve her bölme en az 200 px'lik
 * parçalar bırakmalı — yoksa gürültü, kumaş deseni ya da modelin bacak arası
 * "oluk" sanılıp ürün ortadan ikiye bölünür.
 */
function cs_cells(\GdImage $im, int $x, int $y, int $w, int $h, int $depth = 0): array
{
    $self = [[$x, $y, $w, $h]];
    if ($depth >= 3 || $w < 400 || $h < 400) return $self;

    $sub = imagecrop($im, ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h]);
    if (!$sub instanceof \GdImage) return $self;

    $best = null;   // [eksen, kesim, genişlik]
    foreach ([0.005, 0.02, 0.05] as $thr) {
        foreach ([[true, $w], [false, $h]] as [$isCol, $len]) {
            $prof = cs_profile($sub, $isCol, 4);
            foreach (cs_gutters($prof, $len, $thr) as [$gs, $gw]) {
                // Bölme her iki yana en az 200 px bırakmalı.
                $cut = (int)($gs + $gw / 2);
                if ($cut < 200 || $len - $cut < 200) continue;
                if ($best === null || $gw > $best[2]) $best = [$isCol, $cut, $gw];
            }
        }
        if ($best !== null) break;   // en katı eşikte bulunduysa gevşetme
    }
    imagedestroy($sub);

    if ($best === null) return $self;

    [$isCol, $cut] = $best;
    if ($isCol) {
        return array_merge(
            cs_cells($im, $x,        $y, $cut,      $h, $depth + 1),
            cs_cells($im, $x + $cut, $y, $w - $cut, $h, $depth + 1)
        );
    }
    return array_merge(
        cs_cells($im, $x, $y,        $w, $cut,      $depth + 1),
        cs_cells($im, $x, $y + $cut, $w, $h - $cut, $depth + 1)
    );
}

/**
 * Ist dieser Zielpfad frei?
 *
 * Frei heißt: es gibt die Datei nicht, ODER sie stammt aus einem früheren
 * Lauf dieses Werkzeugs. Alles andere ist fremdes Material — die
 * Ausgangsbilder heißen teils selbst "…-b.jpg" (back), und ein Panel darf
 * niemals eine echte Aufnahme überschreiben.
 */
function cs_free(string $abs): bool
{
    if (!file_exists($abs)) return true;
    return isset($GLOBALS['cs_written'][$abs]);
}

/**
 * Weißrand einer Zelle wegschneiden — in BEIDEN Richtungen.
 * Rückgabe: [x, breite, y, höhe].
 *
 * Waagerecht allein reichte, solange ein Bogen eine einzige Reihe war. Beim
 * Raster steht über und unter jedem Motiv eine Rinne; ohne senkrechtes
 * Beschneiden bekäme jede Zelle die volle Bogenhöhe und damit ein Format,
 * das die Prüfung (0,45–1,6) nie besteht.
 */
function cs_tighten(\GdImage $im, int $x, int $w, int $y = 0, ?int $h = null): array
{
    $h = $h ?? imagesy($im);
    $sub = imagecrop($im, ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h]);
    if (!$sub instanceof \GdImage) return [$x, $w, $y, $h];

    $cols = cs_profile($sub, true, 6);
    $rows = cs_profile($sub, false, 6);
    imagedestroy($sub);

    $l = 0; $r = $w - 1;
    while ($l < $r && $cols[$l] < 0.005) $l++;
    while ($r > $l && $cols[$r] < 0.005) $r--;

    $t = 0; $b = $h - 1;
    while ($t < $b && $rows[$t] < 0.005) $t++;
    while ($b > $t && $rows[$b] < 0.005) $b--;

    // Etwas Luft stehen lassen — auf Kante beschnittene Ware wirkt eng.
    $padX = (int)($w * 0.02); $padY = (int)($h * 0.02);
    $l = max(0, $l - $padX); $r = min($w - 1, $r + $padX);
    $t = max(0, $t - $padY); $b = min($h - 1, $b + $padY);

    return [$x + $l, max(1, $r - $l + 1), $y + $t, max(1, $b - $t + 1)];
}

// --------------------------------------------------------------------- Lauf

$catalog = vr_catalog();
if ($opt['limit'] > 0) $catalog = array_slice($catalog, 0, $opt['limit'], true);

$patch = [];
$statSplit = $statTrim = $statSkip = $statPanels = $statFail = $statBlocked = 0;
$GLOBALS['cs_written'] = [];

foreach ($catalog as $id => $p) {
    $images = array_values(array_filter((array)$p['images'], 'strlen'));
    if (!$images) continue;

    $newList = [];
    $changed = false;

    foreach ($images as $rel) {
        $file = vr_doc_root() . $rel;
        if (!is_file($file)) { $newList[] = $rel; continue; }

        // Schon zerlegt? Dann die vorhandenen Teile nehmen.
        $dot  = strrpos($rel, '.');
        $stem = substr($rel, 0, $dot);
        $ext  = '.jpg';
        if (!$opt['force'] && is_file(vr_doc_root() . $stem . '-p1' . $ext)) {
            for ($k = 1; $k <= 5; $k++) {
                if (is_file(vr_doc_root() . $stem . '-p' . $k . $ext)) $newList[] = $stem . '-p' . $k . $ext;
            }
            $changed = true;
            continue;
        }

        $im = cs_load($file);
        if (!$im) { $newList[] = $rel; $statFail++; continue; }

        $w = imagesx($im); $h = imagesy($im);
        $rows   = cs_profile($im, false);
        $cutH   = cs_trim_caption($rows, $h);
        $hadCap = $cutH < $h;

        $work = $cutH < $h
            ? (imagecrop($im, ['x' => 0, 'y' => 0, 'width' => $w, 'height' => $cutH]) ?: $im)
            : $im;

        /* IKI EKSENDE BÖLME.
           İlk sürüm yalnızca dikey oluk arıyordu, yani bir bogen tek sıra
           kabul ediliyordu. Oysa toptancının dosyalarının büyük kısmı ızgara:
           ölçtüğümüz bir Dolce & Gabbana karesi 2744×2483 içinde altı ayrı
           model taşıyor — üç sütun, iki satır. Tek eksende bakınca üç panel
           çıkıyor ama her panel hâlâ iki ürün gösteriyor, oran testine de
           takılıyordu. Sonuç: 478 üründen 244'ünün vitrinlik tek kadrajı yoktu.

           Artık satır ve sütun bantları ayrı bulunuyor, kesişimleri hücre.
           Tek satır çıkarsa davranış eskisiyle aynı. */
        /* Hücreleri özyinelemeli bul (bkz. cs_cells). Düzgün ızgara da,
           üstte iki geniş altta iki dar panel gibi yamuk yerleşim de aynı
           yoldan çıkıyor. */
        $panels = [];
        $ok = false;
        $cells = cs_cells($work, 0, 0, $w, $cutH);

        // Üst sınır sekiz: daha kalabalık bir kare artık ürün çekimi değil,
        // katalog sayfası — onu bölmek işe yaramıyor.
        if (count($cells) >= 2 && count($cells) <= 8) {
            $ok = true;
            foreach ($cells as [$cx, $cy, $cw, $ch]) {
                [$tx, $tw, $ty, $th] = cs_tighten($work, $cx, $cw, $cy, $ch);

                /* Yazı hücresi ATILIYOR, ama bölmeyi bozmuyor.
                   Bazı bogenlerde model kodu kendi kutusunda duruyor
                   ("BALENCIAGA 612966TLVF1 UNISEX") ve oluk testine göre
                   düpedüz bir panel. Kesip galeriye koyduğumuzda ürün
                   fotoğrafı diye bir yazı karesi çıkıyor. Doluluk oranı
                   %5'in altındaysa orada ürün yok. Bu hücre eleniyor,
                   diğerleri yazılmaya devam ediyor. */
                if (cs_density($work, $tx, $ty, $tw, $th) < 0.05) continue;

                $ratio = $tw / max(1, $th);
                /* Alt oran sınırı 0,45 değil 0,33: bir Dolce & Gabbana bogeni
                   2744×2483 ve üç ayakta model taşıyor, her panel 915×2483,
                   yani oran 0,37. Ayakta boy çekimi bu kadar dar olur. */
                if ($tw < 200 || $th < 200 || $ratio < 0.33 || $ratio > 1.6) { $ok = false; break; }
                $panels[] = [$tx, $tw, $ty, $th];
            }
            // Yazı hücreleri elendikten sonra elde tek panel kaldıysa bölmenin
            // anlamı yok: orijinal zaten o ürünün kadrajı.
            if (count($panels) < 2) $ok = false;
        }

        if (!$ok) {
            // Bölünmüyor. Yine de yazı şeridi varsa onu kırpmaya değer.
            $dest = $stem . '-p1' . $ext;
            if ($hadCap && cs_free(vr_doc_root() . $dest)) {
                if ($opt['dry']) {
                    $newList[] = $dest; $changed = true; $statTrim++;
                } elseif (imagejpeg($work, vr_doc_root() . $dest, $opt['quality'])) {
                    @chmod(vr_doc_root() . $dest, 0644);
                    $GLOBALS['cs_written'][vr_doc_root() . $dest] = true;
                    $newList[] = $dest; $changed = true; $statTrim++;
                } else {
                    $newList[] = $rel;
                }
            } else {
                $newList[] = $rel; $statSkip++;
            }
            if ($work !== $im) imagedestroy($work);
            imagedestroy($im);
            continue;
        }

        // Bölünüyor. Hedeflerden biri bile doluysa hiçbirini yazma —
        // yarısı yeni yarısı eski bir galeri en kötü sonuç olurdu.
        $targets = [];
        foreach ($panels as $i => $_) $targets[] = $stem . '-p' . ($i + 1) . $ext;
        foreach ($targets as $t) {
            if (!cs_free(vr_doc_root() . $t)) { $ok = false; break; }
        }
        if (!$ok) {
            $newList[] = $rel; $statBlocked++;
            if ($work !== $im) imagedestroy($work);
            imagedestroy($im);
            continue;
        }

        $written = 0;
        foreach ($panels as $i => [$tx, $tw, $ty, $th]) {
            $dest = $targets[$i];
            if ($opt['dry']) { $newList[] = $dest; $written++; continue; }

            $cut = imagecrop($work, ['x' => $tx, 'y' => $ty, 'width' => $tw, 'height' => $th]);
            if (!$cut instanceof \GdImage) continue;
            if (imagejpeg($cut, vr_doc_root() . $dest, $opt['quality'])) {
                @chmod(vr_doc_root() . $dest, 0644);
                $GLOBALS['cs_written'][vr_doc_root() . $dest] = true;
                $newList[] = $dest; $written++;
            }
            imagedestroy($cut);
        }

        if ($written > 0) {
            $changed = true; $statSplit++; $statPanels += $written;
            printf("  %-34s %d Panel%s%s\n", mb_substr(basename($rel), 0, 34), $written,
                   $written === 1 ? '' : 'e', $hadCap ? ' · Leiste ab' : '');
        } else {
            $newList[] = $rel;
        }

        if ($work !== $im) imagedestroy($work);
        imagedestroy($im);
    }

    if ($changed) {
        // Tekrarları at, sırayı koru.
        $patch[$id] = array_values(array_unique($newList));
    }
}

echo "\n" . str_repeat('-', 68) . "\n";
printf("Zerlegt        : %d Bilder → %d Panels\n", $statSplit, $statPanels);
printf("Nur Leiste ab  : %d\n", $statTrim);
printf("Unverändert    : %d\n", $statSkip);
if ($statBlocked) printf("Nicht angefasst (Zielname belegt): %d\n", $statBlocked);
if ($statFail) printf("Nicht lesbar   : %d\n", $statFail);
printf("Artikel betroffen: %d\n", count($patch));

if ($opt['dry']) { echo "\nProbelauf — nichts geschrieben.\n"; exit(0); }
if (!$patch)     { echo "\nNichts zu ändern.\n"; exit(0); }

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

echo "\nGespeichert: " . vr_store_path('retail-imported.json') . " ({$upd} Artikel)\n";
$rest = count($patch) - $upd;
if ($rest > 0) {
    echo "Hinweis: {$rest} Artikel stehen in data/listings.json — die bleibt unangetastet.\n";
}
