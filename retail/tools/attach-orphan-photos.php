<?php
/**
 * Sahipsiz fotoğrafları ürünlere bağla — CLI
 * ==========================================
 *   php tools/attach-orphan-photos.php --dry
 *   php tools/attach-orphan-photos.php
 *
 * NEDEN
 * -----
 * uploads/ altında 1.733 görsel var, katalog bunların 1.230'unu gösteriyor.
 * Geriye 503 dosya kalıyor: aynı ürünün başka açıları, arka görünüşleri,
 * detay kadrajları. Aylardır "ikinci fotoğrafı nereden bulacağız" diye
 * konuşulan şeyin bir kısmı en baştan diskte duruyordu, sadece hiçbir satır
 * onları göstermiyordu.
 *
 * Bu, internetten marka fotoğrafı toplamaya göre her bakımdan iyi: dosyalar
 * zaten bizim, ürünün kendisi, doğru sezon, filigransız ve telif sorunu yok.
 *
 * EŞLEŞTİRME
 * ----------
 * İki ölçüt, sıkıdan gevşeğe:
 *
 *   1. GÖVDE EŞLEŞMESİ (güvenilir). Dosya adından panel eki ("-p2"),
 *      arka/ön eki ("-b") ve ayraçlar atılınca kalan gövde, ürünün MEVCUT
 *      bir fotoğrafının gövdesiyle aynıysa aynı çekimin başka karesidir.
 *      "balenciaga-612966-tmvg7-p1.jpg" ile "balenciaga-612966-tmvg7.jpg"
 *      aynı gövdeye iniyor.
 *
 *   2. MODEL KODU. Gövde, ürünün model kodunu içeriyorsa (ya da tersi).
 *      En az altı karakter aranıyor: kısa kodlar rastgele eşleşiyor —
 *      ölçtük, dört karakterde bir Burberry kazağı bir mayoya bağlanıyordu.
 *
 * Bir dosya BİRDEN ÇOK ürüne uyuyorsa hiçbirine bağlanmıyor: yanlış ürüne
 * fotoğraf koymak, fotoğrafsız bırakmaktan kötüdür.
 *
 * Sıra korunuyor: yeni kareler mevcutların ARDINA ekleniyor, sonra katalog
 * kendi kuralıyla (vitrinlik olan öne) yeniden sıralıyor.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Nur über die Kommandozeile.\n";
    exit(1);
}

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/uploads.php';
require_once __DIR__ . '/lib-photo-match.php';
require_once __DIR__ . '/lib-photo-colour.php';

$dry   = in_array('--dry', $argv, true);
$limit = 0;
foreach ($argv as $a) if (str_starts_with($a, '--limit=')) $limit = (int)substr($a, 8);

/** Dosya adını karşılaştırılabilir bir gövdeye indir. */
function ao_stem(string $rel): string
{
    $base = strtolower(pathinfo($rel, PATHINFO_FILENAME));
    // Panel ve yön ekleri: -p1, -p2-p1, -b, -back, -front, _01, -2
    $base = preg_replace('/(-p\d+)+$/', '', $base) ?? $base;
    $base = preg_replace('/[-_](b|back|front|f|v|r|\d{1,2})$/', '', $base) ?? $base;
    // Yaygın boyut ekleri
    $base = preg_replace('/[-_](large|medium|small|thumb|\d{3,4}x\d{3,4})$/', '', $base) ?? $base;
    return preg_replace('/[^a-z0-9]/', '', $base) ?? $base;
}

/** Model kodu benzeri anahtar. */
function ao_key(string $s): string
{
    return preg_replace('/[^a-z0-9]/', '', strtolower($s)) ?? '';
}

// ---- ürün dizinleri
$catalog = vr_catalog();
$byStem  = [];   // gövde  => [ürün kimliği, …]
$byCode  = [];   // kod    => [ürün kimliği, …]
$owned   = [];   // zaten kullanılan dosyalar

foreach ($catalog as $id => $p) {
    foreach ((array)$p['images'] as $img) {
        $img = (string)$img;
        if ($img === '') continue;
        $owned[$img] = true;
        $st = pm_stem($img);
        if (strlen($st) >= 6) $byStem[$st][$id] = true;
    }
    $sku = ao_key((string)($p['sku'] ?? ''));
    // "VS-" ile başlayanlar bizim iç referansımız, dosya adlarında geçmiyor.
    if (strlen($sku) >= 6 && !str_starts_with(strtolower((string)$p['sku']), 'vs-')) {
        $byCode[$sku][$id] = true;
    }
}

// ---- diskteki dosyalar
$root = vr_doc_root() . '/uploads';
$all  = [];
if (is_dir($root)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile()) continue;
        $abs = $f->getPathname();
        // .orig/ ZORUNLU OLARAK DIŞARIDA.
        // Orası trim-caption-bands.php'nin yedeği: tedarikçi model kodu
        // şeridi HÂLÂ ÜZERİNDE olan kırpılmamış asıllar. İlk çalıştırmada
        // burası elenmiyordu ve 39 üründe kart yüzü kırpılmamış asıla
        // geçti — yani şeridi kestiğimiz fotoğraflar vitrine geri geldi.
        // Ölçüldü, geri alındı. candidates/ de aynı sebeple dışarıda:
        // orası onay bekleyen indirmeler, katalog değil.
        foreach (['/.thumbs/', '/.orig/', '/candidates/'] as $skipDir) {
            if (str_contains($abs, $skipDir)) continue 2;
        }
        if (!preg_match('/\.(jpe?g|png|webp|avif)$/i', $abs)) continue;
        $all[] = '/uploads/' . substr($abs, strlen($root) + 1);
    }
}
sort($all);

$orphans = array_values(array_filter($all, fn($r) => !isset($owned[$r])));
$idx = pm_build_index($catalog);
printf("MAXSALES — sahipsiz fotoğraf eşleştirme%s\n", $dry ? ' (Probelauf)' : '');
echo str_repeat('=', 68) . "\n";
printf("diskte %d · katalogda %d · sahipsiz %d\n\n", count($all), count($owned), count($orphans));

// ---- eşleştir
$add = [];              // ürün kimliği => [dosya, …]
$stemHit = $codeHit = $ambiguous = $noHit = $rejected = 0;
$rejectBy = [];
$refPx = [];
$docRoot = vr_doc_root();

foreach ($orphans as $rel) {
    if ($limit > 0 && count($add) >= $limit) break;

    // Kural ortak kitaplikta (lib-photo-match.php): once TAM model kodu,
    // en uzun kod kazanacak sekilde; sonra govde. Denetim araci ayni
    // fonksiyonu cagiriyor, boylece kolajda gorulen ile yazilan ayni.
    [$hits, $how] = pm_match($rel, $idx, $byStem);

    if (!$hits)            { $noHit++; continue; }
    if (count($hits) > 1)  { $ambiguous++; continue; }

    // RENK KAPISI (lib-photo-colour.php)
    // Ad eşleşmesi ürünü buluyor ama rengi garanti etmiyor: tedarikçi aynı
    // gövde kodunu bütün renkler için kullanıyor. Gözle bakıldı — turuncu
    // tişörte beyaz kareler, siyah boksere beyaz, siyah mayoya fuşya
    // bağlanıyordu. Bunlar buradan geçmiyor. Ayrıca üzerinde yalnızca model
    // numarası yazan tedarikçi kartları da eleniyor.
    $id = $hits[0];
    if (!isset($refPx[$id])) {
        $refPx[$id] = false;
        foreach ((array)($catalog[$id]['images'] ?? []) as $u) {
            $m = cac_pixels($docRoot . '/' . ltrim((string)$u, '/'));
            if ($m && $m[1] > 0.03) { $refPx[$id] = $m; break; }
        }
    }
    $cm = cac_pixels($docRoot . '/' . ltrim($rel, '/'));
    if ($refPx[$id] && $cm) {
        [$karar, ] = cac_decide($refPx[$id][0], $refPx[$id][1], $cm[0], $cm[1]);
        if ($karar !== 'kabul') { $rejected++; $rejectBy[$karar] = ($rejectBy[$karar] ?? 0) + 1; continue; }
    }

    $add[$id][] = $rel;
    if ($how === 'gövde') $stemHit++; else $codeHit++;
}

printf("eşleşti  : %d dosya (%d gövde, %d model kodu) → %d ürün\n", $stemHit + $codeHit, $stemHit, $codeHit, count($add));
printf("belirsiz : %d (birden çok ürüne uyuyor — bağlanmadı)\n", $ambiguous);
printf("eşleşmedi: %d\n", $noHit);
printf("renk/yazı kapısı: %d elendi (%s)\n\n", $rejected,
       $rejectBy ? implode(', ', array_map(fn($k, $v) => "$k: $v", array_keys($rejectBy), $rejectBy)) : '-');

if (!$add) { echo "Yapılacak bir şey yok.\n"; exit(0); }

// örnekler
$i = 0;
foreach ($add as $id => $files) {
    if ($i++ >= 12) break;
    $p = $catalog[$id];
    printf("  %-13s %-30s +%d kare\n", $p['brand'], substr(vr_card_name($p), 0, 30), count($files));
}
if (count($add) > 12) printf("  … ve %d ürün daha\n", count($add) - 12);

if ($dry) { echo "\nProbelauf — nichts geschrieben.\n"; exit(0); }

// ---- yaz
$file = vr_data_dir() . '/retail-imported.json';
$rows = json_decode((string)@file_get_contents($file), true);
if (!is_array($rows)) { echo "\nHATA: retail-imported.json okunamadı.\n"; exit(1); }

$touched = 0;
foreach ($rows as $k => $row) {
    if (!is_array($row)) continue;
    $id = (string)($row['id'] ?? '');
    if ($id === '' || empty($add[$id])) continue;

    $imgs = array_values(array_filter((array)($row['images'] ?? []), 'strlen'));
    foreach ($add[$id] as $rel) if (!in_array($rel, $imgs, true)) $imgs[] = $rel;

    $rows[$k]['images'] = $imgs;
    $touched++;
}

// Katalogda B2B satırı olan ürünler retail-imported.json'da olmayabilir;
// onlar için satır UYDURMUYORUZ — canlı katalog salt okunur, oraya
// yazmak kuralın dışında. Kaç tanesi böyle, söylüyoruz.
$missing = count($add) - $touched;

file_put_contents($file, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
printf("\n%d ürün güncellendi → %s\n", $touched, $file);
if ($missing > 0) {
    printf("%d ürün canlı B2B kataloğundan geliyor, oraya yazılmadı (salt okunur).\n", $missing);
}
