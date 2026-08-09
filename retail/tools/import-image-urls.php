<?php
/**
 * Lisanslı fotoğraf listesini içe aktar — CLI
 * ===========================================
 *   php tools/import-image-urls.php --dry
 *   php tools/import-image-urls.php
 *
 * NE İŞE YARAR
 * ------------
 * data/image-sources.json içindeki adres listesini indirir, doğrular ve
 * ürünlere bağlar. Liste ELLE hazırlanır; kaynağı işletmeci belirler:
 * tedarikçinin medya paketi, markanın basın portalı, yetkili bayilik
 * beslemesi ya da kendi çektiğiniz kareler.
 *
 * NE İŞE YARAMAZ — VE NEDEN
 * -------------------------
 * Bu araç internette fotoğraf ARAMAZ. Balenciaga'nın, Dolce & Gabbana'nın ya
 * da bir perakendecinin sitesindeki ürün çekimi o evin veya o fotoğrafçının
 * eseridir; başkasının sitesinden alıp kendi mağazanızda yayımlamak
 * Almanya'da klasik bir Abmahnung sebebi ve ticari bir sitede riski gerçek.
 * Teknik olarak da güvenilmez: yanlış renk, yanlış sezon, başkasının
 * filigranı, farklı ışık — vitrin bir anda derme çatma görünüyor.
 *
 * Bu yüzden araç şunu istiyor: adresleri siz veriyorsunuz ve o adreslerdeki
 * görselleri kullanma hakkına sahip olduğunuzu beyan ediyorsunuz. Dosyanın
 * başındaki "rights_confirmed" alanı true değilse hiçbir şey indirilmiyor.
 *
 * DOSYA BİÇİMİ — data/image-sources.json
 * --------------------------------------
 *   {
 *     "rights_confirmed": true,
 *     "note": "Tedarikçi medya paketi, sözleşme 2026-03",
 *     "items": [
 *       { "sku": "612966TLVF1", "urls": ["https://…/a.jpg", "https://…/b.jpg"] },
 *       { "id":  "blm-xh16b005", "urls": ["https://…/c.jpg"] }
 *     ]
 *   }
 *
 * Her görsel indirildikten sonra: gerçekten görsel mi (GD ile açılıyor mu),
 * yeterince büyük mü (en az 600×700 — kart 4:5 ve daha küçüğü bulanık),
 * daha önce indirilmiş mi (içerik özeti) diye bakılıyor. Dosyalar
 * uploads/licensed/<marka>/ altına, kaynağı belli olsun diye ayrı bir
 * klasöre iniyor.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Nur über die Kommandozeile.\n";
    exit(1);
}

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/uploads.php';

$dry = in_array('--dry', $argv, true);

$src = vr_store_read('image-sources.json', null);
if (!is_array($src)) {
    echo "data/image-sources.json yok.\n\n";
    echo "Örnek:\n";
    echo json_encode([
        'rights_confirmed' => false,
        'note'  => 'Kaynağı ve hakkın dayanağını buraya yazın',
        'items' => [['sku' => 'ÖRNEK-KOD', 'urls' => ['https://…/foto.jpg']]],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    exit(1);
}

if (empty($src['rights_confirmed'])) {
    echo "DURDU: rights_confirmed false.\n";
    echo "Bu adreslerdeki görselleri yayımlama hakkına sahip olduğunuzu\n";
    echo "onaylamadan indirme yapılmıyor. Not alanına dayanağı yazın\n";
    echo "(tedarikçi sözleşmesi, medya paketi izni, kendi çekiminiz).\n";
    exit(1);
}

$items = is_array($src['items'] ?? null) ? $src['items'] : [];
if (!$items) { echo "items boş.\n"; exit(0); }

// ---- ürün dizini: sku ve id ile
$catalog = vr_catalog();
$bySku = $byId = [];
foreach ($catalog as $id => $p) {
    $byId[strtolower($id)] = $id;
    $s = strtolower(preg_replace('/[^A-Za-z0-9]/', '', (string)($p['sku'] ?? '')) ?? '');
    if ($s !== '') $bySku[$s][] = $id;
}

// ---- mevcut içerik özetleri (aynı kareyi iki kez indirmeyelim)
$seen = [];
$root = vr_doc_root() . '/uploads';
if (is_dir($root)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile() || str_contains($f->getPathname(), '/.thumbs/')) continue;
        if (!preg_match('/\.(jpe?g|png|webp|avif)$/i', $f->getPathname())) continue;
        $seen[md5_file($f->getPathname()) ?: ''] = true;
    }
}

printf("MAXSALES — lisanslı görsel içe aktarma%s\n", $dry ? ' (Probelauf)' : '');
echo str_repeat('=', 68) . "\n";
echo 'kaynak notu: ' . (string)($src['note'] ?? '—') . "\n\n";

$add = [];
$ok = $skipDup = $skipSmall = $skipBad = $skipNoProd = 0;

foreach ($items as $row) {
    if (!is_array($row)) continue;
    $urls = array_values(array_filter((array)($row['urls'] ?? []), 'strlen'));
    if (!$urls) continue;

    // hedef ürün
    $target = null;
    $rid = strtolower(trim((string)($row['id'] ?? '')));
    if ($rid !== '' && isset($byId[$rid])) {
        $target = $byId[$rid];
    } else {
        $rsku = strtolower(preg_replace('/[^A-Za-z0-9]/', '', (string)($row['sku'] ?? '')) ?? '');
        if ($rsku !== '' && count($bySku[$rsku] ?? []) === 1) $target = $bySku[$rsku][0];
    }
    if ($target === null) { $skipNoProd += count($urls); continue; }

    $p = $catalog[$target];
    $dir = 'licensed/' . (preg_replace('/[^a-z0-9]+/', '-', strtolower($p['brand'])) ?: 'other');

    foreach ($urls as $u) {
        if (!preg_match('#^https://#i', $u)) { $skipBad++; continue; }

        if ($dry) { $add[$target][] = $u; $ok++; continue; }

        $ctx = stream_context_create(['http' => [
            'timeout' => 25,
            'header'  => "User-Agent: MAXSALES image import\r\n",
        ]]);
        $bin = @file_get_contents($u, false, $ctx);
        if ($bin === false || strlen($bin) < 4096) { $skipBad++; continue; }

        $hash = md5($bin);
        if (isset($seen[$hash])) { $skipDup++; continue; }

        $info = @getimagesizefromstring($bin);
        if (!$info) { $skipBad++; continue; }
        if ($info[0] < 600 || $info[1] < 700) { $skipSmall++; continue; }

        $ext  = match ($info[2]) {
            IMAGETYPE_PNG  => '.png',
            IMAGETYPE_WEBP => '.webp',
            default        => '.jpg',
        };
        $rel = '/uploads/' . $dir . '/' . substr($hash, 0, 12) . $ext;
        $abs = vr_doc_root() . $rel;
        if (!is_dir(dirname($abs))) @mkdir(dirname($abs), 0755, true);
        if (@file_put_contents($abs, $bin) === false) { $skipBad++; continue; }
        @chmod($abs, 0644);

        $seen[$hash] = true;
        $add[$target][] = $rel;
        $ok++;
    }
}

printf("indirildi/eşleşti : %d\n", $ok);
printf("zaten var         : %d\n", $skipDup);
printf("çok küçük         : %d (600×700 altı vitrine uygun değil)\n", $skipSmall);
printf("okunamadı/geçersiz: %d\n", $skipBad);
printf("ürün bulunamadı   : %d\n\n", $skipNoProd);

if ($dry || !$add) { echo $dry ? "Probelauf — nichts geschrieben.\n" : "Yazılacak bir şey yok.\n"; exit(0); }

$file = vr_data_dir() . '/retail-imported.json';
$rows = json_decode((string)@file_get_contents($file), true);
if (!is_array($rows)) { echo "HATA: retail-imported.json okunamadı.\n"; exit(1); }

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
file_put_contents($file, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
printf("%d ürün güncellendi → %s\n", $touched, $file);
echo "Ardından: php tools/index-photo-quality.php\n";
