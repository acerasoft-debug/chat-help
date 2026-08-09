<?php
/**
 * Dış kaynaktan fotoğraf ADAYI indir — CLI
 * ========================================
 *   php tools/fetch-photo-candidates.php --dry
 *   php tools/fetch-photo-candidates.php
 *
 * NE YAPAR
 * --------
 * data/image-sources.json'daki adresleri açar, sayfadaki ürün fotoğraflarını
 * bulur, indirir ve uploads/candidates/<ürün-id>/ altına koyar. Her ürün için
 * bir kontrol kolajı üretir: solda ELDEKİ kare, sağda adaylar.
 *
 * NE YAPMAZ — VE NEDEN
 * --------------------
 * Hiçbir şeyi ürüne BAĞLAMAZ. Model kodu araması çoğu zaman doğru sayfayı
 * buluyor, ama "çoğu zaman" bir vitrin için yeterli değil: aynı kodun beş
 * rengi var, bir kısmı kadın bedeni, bir kısmı başka sezon. Yanlış fotoğrafı
 * ürüne bağlamak tek fotoğrafla kalmaktan kötü — müşteri başka bir şey
 * alıyor. Onay gözle veriliyor; bağlama işi tools/approve-candidates.php.
 *
 * NEREDE ÇALIŞIR
 * --------------
 * Geliştirme kapsayıcısının dışarı çıkışı kapalı (proxy 403). Bu betik
 * .github/workflows/fetch-photo-candidates.yml içinde, GitHub runner'ında
 * çalışıyor; runner'ın açık interneti var.
 *
 * HAK MESELESİ
 * ------------
 * İndirilen kareler markanın ya da perakendecinin eseri. Bu betik onları
 * yalnızca ADAY olarak diske koyuyor; yayımlama kararı ve sorumluluğu
 * işletmecinin. data/image-sources.json'daki "rights_confirmed" alanı bu
 * beyanın kaydı; false ise hiçbir şey indirilmiyor.
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
if (!is_array($src)) { echo "data/image-sources.json yok.\n"; exit(1); }
if (empty($src['rights_confirmed'])) {
    echo "DURDU: rights_confirmed false.\n";
    exit(1);
}
$items = is_array($src['items'] ?? null) ? $src['items'] : [];
if (!$items) { echo "items boş.\n"; exit(0); }

$root = vr_doc_root();
$catalog = vr_catalog();

// ---- ürün dizini
$byId = $bySku = [];
foreach ($catalog as $id => $p) {
    $byId[strtolower((string)$id)] = (string)$id;
    $s = strtolower(preg_replace('/[^A-Za-z0-9]/', '', (string)($p['sku'] ?? '')) ?? '');
    if ($s !== '') $bySku[$s][] = (string)$id;
}

// ---- elde ne var: aynı kareyi ikinci kez indirmeyelim
$seen = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/uploads', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile()) continue;
    $pn = $f->getPathname();
    if (str_contains($pn, '/.thumbs/') || str_contains($pn, '/.orig/')) continue;
    if (!preg_match('/\.(jpe?g|png|webp|avif)$/i', $pn)) continue;
    $seen[(string)md5_file($pn)] = true;
}

/** Tarayıcı gibi davran: birçok mağaza betik olmayan istemciye 403 veriyor. */
function fpc_get(string $url): ?string
{
    $ctx = stream_context_create(['http' => [
        'timeout' => 25,
        'follow_location' => 1,
        'max_redirects' => 5,
        'header' => implode("\r\n", [
            'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
        ]),
    ], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $b = @file_get_contents($url, false, $ctx);
    return $b === false ? null : $b;
}

/**
 * Sayfadan ürün fotoğrafı adresleri çıkar.
 *
 * Üç yerden bakıyoruz, sırayla güvenilirlikten sıradanlığa:
 *   1) JSON-LD Product.image  — mağazanın kendi beyanı, en temizi
 *   2) og:image / twitter:image — paylaşım karesi, hep ürünün kendisi
 *   3) <img> ve srcset — geri kalan; ikon ve rozetleri boyutla eliyoruz
 */
function fpc_images(string $html, string $base): array
{
    $out = [];
    $abs = function (string $u) use ($base): string {
        $u = trim(html_entity_decode($u, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($u === '') return '';
        if (str_starts_with($u, '//')) return 'https:' . $u;
        if (preg_match('#^https?://#i', $u)) return $u;
        $p = parse_url($base);
        if (!$p) return '';
        $origin = ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '');
        return str_starts_with($u, '/') ? $origin . $u : $origin . '/' . ltrim($u, '/');
    };

    // 1) JSON-LD
    if (preg_match_all('#<script[^>]+application/ld\+json[^>]*>(.*?)</script>#is', $html, $m)) {
        foreach ($m[1] as $blob) {
            $j = json_decode(trim($blob), true);
            if (!is_array($j)) continue;
            array_walk_recursive($j, function ($v, $k) use (&$out, $abs) {
                if ($k === 'image' && is_string($v)) { $u = $abs($v); if ($u !== '') $out[] = $u; }
            });
            // image bir dizi olabiliyor; walk_recursive dizinin elemanlarını
            // anahtarsız gördüğü için ayrıca bakıyoruz.
            $stack = [$j];
            while ($stack) {
                $n = array_pop($stack);
                if (!is_array($n)) continue;
                foreach ($n as $k => $v) {
                    if ($k === 'image' && is_array($v)) {
                        foreach ($v as $vv) if (is_string($vv)) { $u = $abs($vv); if ($u !== '') $out[] = $u; }
                    } elseif (is_array($v)) $stack[] = $v;
                }
            }
        }
    }

    // 2) og:image / twitter:image
    if (preg_match_all('#<meta[^>]+(?:property|name)=["\'](?:og:image(?::secure_url)?|twitter:image)["\'][^>]*>#i', $html, $m)) {
        foreach ($m[0] as $tag) {
            if (preg_match('#content=["\']([^"\']+)["\']#i', $tag, $c)) {
                $u = $abs($c[1]); if ($u !== '') $out[] = $u;
            }
        }
    }

    // 3) <img src> ve srcset
    if (preg_match_all('#<img[^>]+>#i', $html, $m)) {
        foreach ($m[0] as $tag) {
            foreach (['src', 'data-src', 'data-zoom-image', 'data-large_image'] as $a) {
                if (preg_match('#\b' . $a . '=["\']([^"\']+)["\']#i', $tag, $c)) {
                    $u = $abs($c[1]); if ($u !== '') $out[] = $u;
                }
            }
            if (preg_match('#\bsrcset=["\']([^"\']+)["\']#i', $tag, $c)) {
                foreach (explode(',', $c[1]) as $part) {
                    $u = $abs(trim(explode(' ', trim($part))[0] ?? ''));
                    if ($u !== '') $out[] = $u;
                }
            }
        }
    }

    // ikon/rozet/piksel adresleri: adında belli olanları at
    $out = array_values(array_unique(array_filter($out, function ($u) {
        if (!preg_match('#\.(jpe?g|png|webp|avif)(\?|$)#i', $u)) return false;
        return !preg_match('#(sprite|icon|logo|badge|flag|placeholder|pixel|1x1|loader|favicon)#i', $u);
    })));

    return $out;
}

printf("MAXSALES — fotoğraf adayı indirme%s\n%s\n", $dry ? ' (Probelauf)' : '', str_repeat('=', 62));
echo 'kaynak notu: ' . (string)($src['note'] ?? '—') . "\n\n";

$ledger = [];
$okImg = $skipSmall = $skipDup = $skipBad = $noProd = 0;

foreach ($items as $row) {
    if (!is_array($row)) continue;
    $urls = array_values(array_filter((array)($row['urls'] ?? []), 'strlen'));
    if (!$urls) continue;

    $target = null;
    $rid = strtolower(trim((string)($row['id'] ?? '')));
    if ($rid !== '' && isset($byId[$rid])) $target = $byId[$rid];
    if ($target === null) {
        $rsku = strtolower(preg_replace('/[^A-Za-z0-9]/', '', (string)($row['sku'] ?? '')) ?? '');
        if ($rsku !== '' && count($bySku[$rsku] ?? []) === 1) $target = $bySku[$rsku][0];
    }
    if ($target === null) { $noProd++; echo "  ! ürün bulunamadı: " . ($row['id'] ?? $row['sku'] ?? '?') . "\n"; continue; }

    $dir = '/uploads/candidates/' . $target;
    $got = [];

    foreach ($urls as $u) {
        $direct = (bool)preg_match('#\.(jpe?g|png|webp|avif)(\?|$)#i', $u);
        $imgUrls = [$u];

        if (!$direct) {
            $html = fpc_get($u);
            if ($html === null) { echo "  ! sayfa açılmadı: {$u}\n"; $skipBad++; continue; }
            $imgUrls = array_slice(fpc_images($html, $u), 0, 8);
            if (!$imgUrls) { echo "  ! sayfada kare bulunamadı: {$u}\n"; $skipBad++; continue; }
        }

        foreach ($imgUrls as $iu) {
            if (count($got) >= 6) break;
            if ($dry) { $got[] = ['url' => $iu, 'file' => null]; $okImg++; continue; }

            $bin = fpc_get($iu);
            if ($bin === null || strlen($bin) < 6000) { $skipBad++; continue; }

            $hash = md5($bin);
            if (isset($seen[$hash])) { $skipDup++; continue; }

            $info = @getimagesizefromstring($bin);
            if (!$info) { $skipBad++; continue; }
            if ($info[0] < 600 || $info[1] < 700) { $skipSmall++; continue; }

            $ext = match ($info[2]) {
                IMAGETYPE_PNG  => '.png',
                IMAGETYPE_WEBP => '.webp',
                default        => '.jpg',
            };
            $rel = $dir . '/' . substr($hash, 0, 10) . $ext;
            $abs = $root . $rel;
            if (!is_dir(dirname($abs))) @mkdir(dirname($abs), 0755, true);
            if (@file_put_contents($abs, $bin) === false) { $skipBad++; continue; }
            @chmod($abs, 0644);

            $seen[$hash] = true;
            $got[] = ['url' => $iu, 'file' => $rel];
            $okImg++;
        }
    }

    if ($got) {
        $ledger[$target] = ['source' => $urls, 'candidates' => $got];
        printf("  %-52s %d aday\n", $target, count($got));
    }
}

printf("\nindirilen : %d\n", $okImg);
printf("çok küçük : %d\n", $skipSmall);
printf("zaten var : %d\n", $skipDup);
printf("okunmadı  : %d\n", $skipBad);
printf("ürün yok  : %d\n", $noProd);

if ($dry || !$ledger) { echo "\n" . ($dry ? "Probelauf.\n" : "İndirilen yok.\n"); exit(0); }

vr_store_write('photo-candidates.json', $ledger);

// ---- kontrol kolajları: solda eldeki kare, sağda adaylar
$sheetDir = $root . '/uploads/candidates/_sheets';
if (!is_dir($sheetDir)) @mkdir($sheetDir, 0755, true);
$cw = 260; $ch = 330;
foreach ($ledger as $pid => $row) {
    $have = vr_card_image($catalog[$pid]);
    $tiles = [];
    if (str_starts_with($have, '/uploads/')) $tiles[] = ['ELDE', $root . $have];
    foreach ($row['candidates'] as $i => $c) $tiles[] = ['ADAY ' . ($i + 1), $root . $c['file']];

    $sheet = imagecreatetruecolor($cw * count($tiles), $ch);
    imagefilledrectangle($sheet, 0, 0, imagesx($sheet), $ch, imagecolorallocate($sheet, 236, 236, 236));
    $ink = imagecolorallocate($sheet, 20, 20, 20);
    foreach ($tiles as $i => [$label, $path]) {
        $im = @imagecreatefromstring((string)@file_get_contents($path));
        if ($im) {
            imagecopyresampled($sheet, $im, $i * $cw + 3, 20, 0, 0, $cw - 6, $ch - 26,
                imagesx($im), imagesy($im));
            imagedestroy($im);
        }
        imagestring($sheet, 3, $i * $cw + 6, 4, $label, $ink);
    }
    imagejpeg($sheet, $sheetDir . '/' . $pid . '.jpg', 86);
    imagedestroy($sheet);
}

printf("\n%d ürün için aday indirildi.\n", count($ledger));
echo "Kontrol kolajları: uploads/candidates/_sheets/\n";
echo "Onaylamak için: tools/approve-candidates.php\n";
