<?php
/**
 * Kategori düzeltmesi — CLI
 * =========================
 *   php tools/apply-category-fix.php --from=<sınıflandırma.tsv> [--dry]
 *
 * NEDEN VAR
 * ---------
 * Toptan katalogdaki "cat" ve "name" alanları güvenilir değil. Aynı şablon
 * bir markanın bütün ürünlerine yapıştırılmış: Balmain'in 35 ürününün hepsi
 * "Embroidered / printed T-Shirt" diyor, ama içlerinde mayo, slip, şort,
 * polo ve sweatshirt var. Dolce & Gabbana'da "Hoodies & Sweatshirts" etiketi
 * jean, denim şort ve t-shirt'lerin üzerinde duruyor.
 *
 * Bu kozmetik bir kusur değil: perakende sayfasında bir boksöre "T-Shirt"
 * demek yanlış ürün tanımıdır — hem müşteri güvenini bitirir hem de
 * §5a UWG anlamında yanıltıcı beyandır.
 *
 * NASIL DÜZELTİLDİ
 * ----------------
 * Tahminle değil, GÖZLE. 398 ürünün fotoğrafı kontakt sayfalarına dizildi ve
 * her biri tek tek sınıflandırıldı. Girdi dosyası o sınıflandırma:
 *
 *     <ürün-id><TAB><kategori>
 *
 * Sonuç data/category-fix.json'a yazılıyor ve katalog okunurken uygulanıyor
 * (vr_normalize_product). Kaynak stapeller DEĞİŞTİRİLMİYOR: bir sonraki
 * içe aktarma düzeltmeyi ezmesin diye ayrı bir katman.
 *
 * İSİM DE DÜZELİYOR
 * -----------------
 * Yanlış kategori isme de sızmış durumda ("BALMAIN Embroidered / printed
 * T-Shirt BKBU90650"). İsimdeki yanlış giysi adı, doğru olanla
 * değiştiriliyor; model kodu ve marka olduğu gibi kalıyor.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Nur über die Kommandozeile.\n";
    exit(1);
}

require_once __DIR__ . '/../inc/view.php';

$opt = ['from' => '', 'dry' => false];
foreach (array_slice($argv, 1) as $a) {
    if (str_starts_with($a, '--from=')) $opt['from'] = substr($a, 7);
    elseif ($a === '--dry')             $opt['dry']  = true;
}
if ($opt['from'] === '' || !is_file($opt['from'])) {
    echo "Nutzung: php tools/apply-category-fix.php --from=<datei.tsv> [--dry]\n";
    exit(1);
}

/** Kategoriye karşılık gelen giysi adı — isim düzeltmesinde kullanılıyor. */
function cf_noun(string $cat, string $lang = 'de'): string
{
    return match ($cat) {
        'T-Shirts'              => 'T-Shirt',
        'Polos'                 => 'Poloshirt',
        'Hoodies & Sweatshirts' => 'Sweatshirt',
        'Jeans'                 => 'Jeans',
        'Jeans Shorts'          => 'Jeansshorts',
        'Shorts'                => 'Shorts',
        // Tek parça mayo mu, slip mi, şort mu — hepsini tek sözcükle
        // adlandırmak yerine nötr kalıyoruz. Yanlış söylemektense genel söyle.
        'Bademode'              => 'Bademode',
        'Tracksuit Sets'        => 'Trainingsanzug',
        'Jacken'                => 'Jacke',
        'Westen'                => 'Weste',
        'Trousers'              => 'Hose',
        default                 => $cat,
    };
}

/**
 * İsimdeki yanlış giysi adını değiştir.
 *
 * Kaynak isimler iki kalıpta: "MARKA <giysi tanımı> KOD" ve "<giysi> — Renk".
 * Bilinen giysi sözcüklerini arayıp doğrusuyla değiştiriyoruz; hiçbiri
 * bulunmazsa isim OLDUĞU GİBİ bırakılıyor — uydurmaktansa dokunmamak iyi.
 */
function cf_fix_name(string $name, string $cat): string
{
    $noun = cf_noun($cat);

    // Sık geçen yanlış tanımlar, uzundan kısaya (kısası uzunun içinde geçiyor).
    $patterns = [
        '/Embroidered\s*\/\s*printed\s+T-?Shirts?/i',
        '/Hoodies?\s*&\s*Sweatshirts?/i',
        '/Jeans\s*Shorts?/i',
        '/Tracksuit\s*Sets?/i',
        '/Sweatshirts?/i',
        '/T-?Shirts?/i',
        '/Poloshirts?/i',
        '/\bPolos?\b/i',
        '/\bHoodie\b/i',
        '/\bShorts\b/i',
        '/\bJeans\b/i',
    ];
    foreach ($patterns as $re) {
        if (preg_match($re, $name)) {
            $out = preg_replace($re, $noun, $name, 1);
            // İki kez aynı sözcük kalmasın ("Sweatshirt Sweatshirt").
            return trim(preg_replace('/\b(' . preg_quote($noun, '/') . ')(\s+\1)+\b/i', '$1', (string)$out));
        }
    }
    return $name;
}

// ------------------------------------------------------------------- oku
$fix = [];
$bad = 0;
foreach (file($opt['from'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $parts = explode("\t", $line, 2);
    if (count($parts) !== 2) { $bad++; continue; }
    [$id, $cat] = array_map('trim', $parts);
    if ($id === '' || $cat === '') { $bad++; continue; }
    $fix[$id] = $cat;
}
printf("Eingelesen : %d Zuordnungen%s\n", count($fix), $bad ? " ({$bad} unlesbar)" : '');

// -------------------------------------------------------------- vergleichen
$catalog = vr_catalog();
$changedCat = $changedName = $missing = $same = 0;
$out = [];
$byCat = [];

foreach ($fix as $id => $cat) {
    $p = $catalog[$id] ?? null;
    if (!$p) { $missing++; continue; }

    $row = [];
    if ($p['cat'] !== $cat) { $row['cat'] = $cat; $changedCat++; }
    else                    { $same++; }

    $newName = cf_fix_name((string)$p['name'], $cat);
    if ($newName !== '' && $newName !== $p['name']) { $row['name'] = $newName; $changedName++; }

    if ($row) $out[$id] = $row;
    $byCat[$cat] = ($byCat[$cat] ?? 0) + 1;
}

printf("Kategorie neu : %d\n", $changedCat);
printf("Kategorie ok  : %d\n", $same);
printf("Name korrigiert: %d\n", $changedName);
if ($missing) printf("Nicht im Katalog: %d\n", $missing);

echo "\nNeue Verteilung:\n";
arsort($byCat);
foreach ($byCat as $c => $n) printf("  %-24s %3d\n", $c, $n);

echo "\nBeispiele:\n";
$shown = 0;
foreach ($out as $id => $row) {
    if (!isset($row['name'])) continue;
    printf("  %-34s %s\n", mb_substr($id, 0, 34), mb_substr($row['name'], 0, 60));
    if (++$shown >= 8) break;
}

if ($opt['dry']) { echo "\nProbelauf — nichts geschrieben.\n"; exit(0); }

vr_store_write('category-fix.json', $out);
echo "\nGespeichert: " . vr_store_path('category-fix.json') . " (" . count($out) . " Artikel)\n";
echo "Der Katalog wendet die Korrektur beim Lesen an (vr_category_fix).\n";
