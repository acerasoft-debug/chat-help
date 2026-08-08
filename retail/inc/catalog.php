<?php
/**
 * Katalog — canlı toptan verisi üzerine perakende katmanı
 * ------------------------------------------------------
 * İki kaynak birleşir:
 *
 *   1) data/listings.json  — canlı B2B kataloğu. SALT OKUNUR. Bu dosyaya hiçbir
 *      koşulda yazmıyoruz; toptan tarafın workflow'ları (add-products.yml,
 *      set-product.yml …) tek yazar olarak kalıyor.
 *   2) data/retail-listings.json — perakendeye özel, satıcıların bu panelden
 *      girdiği ürünler. Yazma yalnızca buraya.
 *
 * Perakende fiyat: satırda retail_price varsa o, yoksa toptan birim fiyat ×
 * retail_multiplier, cazip fiyata yuvarlanmış. Yani toptan tarafta fiyat
 * güncellendiğinde perakende vitrin kendiliğinden takip eder.
 *
 * Stok: B2B satırındaki "sizes" metni ("S×1 · M×3 · L×3 · 10/pack") gerçek bir
 * beden dökümü içeriyor — perakende için tam ihtiyacımız olan şey bu. Metni
 * ayrıştırıp beden başına adet çıkarıyoruz.
 */

declare(strict_types=1);

require_once __DIR__ . '/boot.php';

const VR_SELLABLE_STATUS = ['', 'approved', 'live', 'active', 'published'];
const VR_STOCK_FILE      = 'retail-stock.json';

/**
 * Perakendede satılmış adetler: "pid|BEDEN" → adet.
 * Canlı listings.json'a yazmadan stok düşebilmenin yolu bu defter: katalog
 * okunurken bedenden düşülür, yazma yalnızca orders.php'de olur.
 */
function vr_sold_map(): array
{
    static $m = null;
    if ($m !== null) return $m;
    $d = vr_store_read(VR_STOCK_FILE, []);
    return $m = is_array($d) ? $d : [];
}

/** Beden dökümünü ayrıştır: "S×1 · M×3 · XL×2 · 10/pack" → [['S',1],['M',3],['XL',2]] */
function vr_parse_sizes(string $s): array
{
    $out = [];
    // × (U+00D7), x ve * hepsi ayırıcı olarak geçer; "10/pack" gibi çarpansız
    // parçalar (paket bilgisi) eşleşmediği için kendiliğinden dışarıda kalır.
    if (preg_match_all('/([0-9]{2,3}|[A-Za-z]{1,4})\s*[×x\*]\s*([0-9]{1,3})/u', $s, $m, PREG_SET_ORDER)) {
        foreach ($m as $hit) {
            $label = strtoupper(trim($hit[1]));
            $qty   = (int)$hit[2];
            if ($qty <= 0 || $label === '') continue;
            // "10/pack" ya da "PACK" gibi paket etiketlerini beden sanmayalım.
            if (in_array($label, ['PACK', 'PC', 'PCS', 'STK', 'SET'], true)) continue;
            $out[$label] = ($out[$label] ?? 0) + $qty;
        }
    }
    $rows = [];
    foreach ($out as $label => $qty) $rows[] = ['label' => $label, 'qty' => $qty];
    return $rows;
}

/** Toptan birim fiyattan perakende fiyat türet (kuruş). */
function vr_derive_retail_cents(float $wholesale): int
{
    $mult = (float)vr_config('retail_multiplier', 2.4);
    return vr_charm_round((int)round($wholesale * $mult * 100));
}

/**
 * Sabit outlet fiyatı ara. Eşleşen kural varsa kuruş, yoksa null.
 * Kurallar sırayla denenir, ilk eşleşen kazanır (bkz. inc/config.php).
 */
function vr_price_rule(string $brand, string $cat, string $name): ?int
{
    $brand = strtoupper(trim($brand));
    if ($brand === '') return null;

    foreach ((array)vr_config('price_rules', []) as $r) {
        if (strtoupper(trim((string)($r['brand'] ?? ''))) !== $brand) continue;

        $rc = trim((string)($r['cat'] ?? ''));
        if ($rc !== '' && mb_strtolower($rc) !== mb_strtolower(trim($cat))) continue;

        $re = (string)($r['match'] ?? '');
        if ($re !== '' && !preg_match($re, $name)) continue;

        $cents = (int)($r['cents'] ?? 0);
        if ($cents > 0) return $cents;
    }
    return null;
}

/** Ham katalog satırını tek biçimli perakende ürününe çevir. */
function vr_normalize_product(array $p, string $source): ?array
{
    $id = trim((string)($p['id'] ?? ''));
    if ($id === '') return null;

    $status = strtolower(trim((string)($p['status'] ?? '')));
    if (!in_array($status, VR_SELLABLE_STATUS, true)) return null;
    if (!empty($p['hold']) || !empty($p['hidden'])) return null;

    // Kategori/isim düzeltmesi — HER ŞEYDEN ÖNCE.
    //
    // Toptan stapellerdeki "cat" ve "name" güvenilmez: aynı şablon bir
    // markanın tüm ürünlerine yapıştırılmış, mayoya "T-Shirt" diyor.
    // Düzeltme gözle yapıldı ve ayrı bir katmanda duruyor (data/
    // category-fix.json) ki bir sonraki içe aktarma onu ezmesin.
    //
    // Burada, fiyat kuralları ve metin motoru çalışmadan önce uygulanıyor:
    // ikisi de ürün adına bakıyor, yanlış adla yanlış karar verirlerdi.
    $cf = vr_category_fix();
    if (isset($cf[$id])) {
        if (($cf[$id]['cat'] ?? '') !== '')  $p['cat']  = (string)$cf[$id]['cat'];
        if (($cf[$id]['name'] ?? '') !== '') $p['name'] = (string)$cf[$id]['name'];
    }

    // ---- fiyat
    // Sabit outlet fiyatı her şeyin önünde: eşleşen kural varsa çarpan da
    // yuvarlama da devreye girmiyor, yazan rakam basılıyor.
    // match YALNIZCA ürün adına uygulanır. Kategoriyi de katarsak
    // "Hoodies & Sweatshirts" içindeki "hood" yüzünden o kategorideki HER
    // ürün kapüşonlu sayılır ve fermuarlı/düz sweat kuralları hiç çalışmaz.
    $ruleCents = vr_price_rule(
        (string)($p['brand'] ?? ''),
        (string)($p['cat'] ?? ''),
        trim((string)($p['name'] ?? ''))
    );

    if ($ruleCents !== null) {
        $price = $ruleCents;
    } elseif (isset($p['retail_price']) && (float)$p['retail_price'] > 0) {
        $price = (int)round((float)$p['retail_price'] * 100);
    } elseif (isset($p['price_cents']) && (int)$p['price_cents'] > 0) {
        $price = (int)$p['price_cents'];
    } elseif (isset($p['list']) && (float)$p['list'] > 0) {
        $price = vr_derive_retail_cents((float)$p['list']);
    } else {
        return null;                             // fiyatsız satır vitrine çıkmaz
    }

    // ---- beden / stok
    $sizes = [];
    if (isset($p['size_stock']) && is_array($p['size_stock'])) {
        foreach ($p['size_stock'] as $label => $qty) {
            if ((int)$qty > 0) $sizes[] = ['label' => strtoupper((string)$label), 'qty' => (int)$qty];
        }
    } elseif (is_string($p['sizes'] ?? null)) {
        $sizes = vr_parse_sizes((string)$p['sizes']);
    }

    if (!$sizes) {
        // Beden dökümü yoksa tek beden ("One size") olarak ele al.
        $one = max(0, (int)($p['stock'] ?? ($source === 'b2b' ? 1 : 0)));
        if ($one > 0) $sizes = [['label' => 'ONE', 'qty' => $one]];
    }

    // Perakendede satılanları düş.
    $sold  = vr_sold_map();
    $stock = 0;
    $left  = [];
    foreach ($sizes as $s) {
        $qty = (int)$s['qty'] - (int)($sold[$id . '|' . strtoupper((string)$s['label'])] ?? 0);
        if ($qty <= 0) continue;
        $left[] = ['label' => strtoupper((string)$s['label']), 'qty' => $qty];
        $stock += $qty;
    }
    $sizes = $left;

    $images = [];
    foreach ((array)($p['images'] ?? []) as $img) {
        if (is_string($img) && preg_match('#^/uploads/[A-Za-z0-9._/-]+$#', $img)) $images[] = $img;
    }

    // Izgara kontakt sayfaları arkaya.
    //
    // Toptancının bazı dosyaları tek çekim değil: bir kadrajda sekiz küçük
    // ürün, altında model kodu. Ürün sayfasında kalmalarında sakınca yok —
    // alıcı beden dökümünü orada görüyor. Ama kart görselinin ONLARDAN biri
    // olması vitrini depo çıktısına çeviriyor, üstelik kırpılınca kadrajın
    // ortasındaki yazı büyüyor.
    //
    // Sıralama KARARLI: aynı gruptakilerin kendi arasındaki sırası bozulmuyor,
    // yalnızca ızgaralar sona alınıyor. İndeks yoksa hiçbir şey değişmez.
    if (count($images) > 1) {
        $grid = vr_photo_grid_index();
        if ($grid) {
            $clean = $dirty = [];
            foreach ($images as $img) {
                if (isset($grid[$img])) $dirty[] = $img; else $clean[] = $img;
            }
            if ($clean) $images = array_merge($clean, $dirty);
        }
    }

    $sellerType = strtolower((string)($p['seller_type'] ?? ''));
    if (!in_array($sellerType, ['business', 'private', 'vestra'], true)) {
        // B2B kataloğundaki satıcısız satırlar bizim kendi stoğumuz.
        $sellerType = ($p['seller_uid'] ?? '') !== '' ? 'business' : 'vestra';
    }

    $name = trim((string)($p['name'] ?? ''));
    if ($name === '') $name = trim(((string)($p['brand'] ?? '')) . ' ' . ((string)($p['sku'] ?? '')));

    return [
        'id'          => $id,
        'source'      => $source,
        'brand'       => trim((string)($p['brand'] ?? '')) ?: '—',
        'name'        => $name,
        'cat'         => trim((string)($p['cat'] ?? '')) ?: 'Other',
        'sku'         => trim((string)($p['sku'] ?? '')),
        'price_cents' => $price,
        'rrp_cents'   => isset($p['rrp']) && (float)$p['rrp'] > 0 ? (int)round((float)$p['rrp'] * 100) : null,
        'images'      => $images,
        'desc'        => trim((string)($p['desc'] ?? '')),
        // Operatörün yazdığı/aktardığı metin: doluysa metin üreteci devre dışı
        // kalır (bkz. inc/copy.php). Boşsa üretilen metin kullanılır.
        'copy'        => trim((string)($p['copy'] ?? '')),
        // Satırın geldiği dış kaynak (mağaza, kaynak id, ürün adresi). 'source'
        // adı zaten hangi DOSYADAN geldiğini tutuyor, o yüzden ayrı ad.
        'origin'      => is_array($p['origin'] ?? null) ? $p['origin'] : null,
        'sizes'       => $sizes,
        'stock'       => $stock,
        'seller_uid'  => (string)($p['seller_uid'] ?? ''),
        'seller_type' => $sellerType,
        'condition'   => (string)($p['condition'] ?? 'new'),
        'created_at'  => (int)($p['created_at'] ?? $p['added_at'] ?? 0),
        'featured'    => !empty($p['featured']),
        'demo'        => !empty($p['demo']),
        'slug'        => vr_slug(($p['brand'] ?? '') . '-' . $name),
    ];
}

/** Tüm satılabilir ürünler (id → ürün). Sonuç istek başına bir kez hesaplanır. */
function vr_catalog(): array
{
    static $cat = null;
    if ($cat !== null) return $cat;

    $cat = [];

    // 1) canlı toptan kataloğu (salt okunur)
    $b2b = vr_store_read('listings.json', []);
    if (is_array($b2b)) {
        foreach ($b2b as $row) {
            if (!is_array($row)) continue;
            $n = vr_normalize_product($row, 'b2b');
            if ($n) $cat[$n['id']] = $n;
        }
    }

    // 2) içe aktarılmış katalog (tools/import-live.php) — perakende AYRI bir
    //    sunucudaysa canlı katalogun kopyası buradan gelir. Aynı sunucudaysa bu
    //    dosya hiç oluşmaz ve adım sessizce atlanır.
    $imported = vr_store_read('retail-imported.json', []);
    if (is_array($imported)) {
        foreach ($imported as $row) {
            if (!is_array($row)) continue;
            $n = vr_normalize_product($row, 'b2b');
            if ($n) $cat[$n['id']] = $n;
        }
    }

    // 3) perakende satıcı ürünleri — aynı id varsa perakende satır kazanır
    $retail = vr_store_read('retail-listings.json', []);
    if (is_array($retail)) {
        foreach ($retail as $row) {
            if (!is_array($row)) continue;
            $n = vr_normalize_product($row, 'retail');
            if ($n) $cat[$n['id']] = $n;
        }
    }

    // 4) hiç veri yoksa vitrin boş kalmasın — demo katalog (bariz etiketli)
    if (!$cat && vr_config('demo_catalog')) {
        $demo = json_decode((string)@file_get_contents(VR_ROOT . '/data/seed/demo-catalog.json'), true);
        if (is_array($demo)) {
            foreach ($demo as $row) {
                if (!is_array($row)) continue;
                $row['demo'] = true;
                $n = vr_normalize_product($row, 'demo');
                if ($n) $cat[$n['id']] = $n;
            }
        }
    }

    return $cat;
}

/** Vitrin gerçek veriyle mi çalışıyor, demo tohumla mı? */
function vr_catalog_is_demo(): bool
{
    foreach (vr_catalog() as $p) return !empty($p['demo']);
    return false;
}

function vr_product(string $id): ?array
{
    return vr_catalog()[$id] ?? null;
}

/** Bir bedende kaç adet var? */
function vr_size_qty(array $product, string $size): int
{
    foreach ($product['sizes'] as $s) {
        if (strcasecmp((string)$s['label'], $size) === 0) return (int)$s['qty'];
    }
    return 0;
}

/**
 * Filtreleme + sıralama + sayfalama.
 * $o: brand, cat, q, min, max, seller, sort, page, per_page, exclude_vault, ids
 */
function vr_query(array $o = []): array
{
    $rows = array_values(vr_catalog());

    // Vault'taki tekil loslar vitrinde iki kez görünmesin.
    if (!empty($o['exclude_vault'])) {
        require_once __DIR__ . '/vault.php';
        $inVault = vr_vault_product_ids();
        $rows = array_values(array_filter($rows, fn($p) => !isset($inVault[$p['id']])));
    }

    if (!empty($o['ids']) && is_array($o['ids'])) {
        $want = array_flip($o['ids']);
        $rows = array_values(array_filter($rows, fn($p) => isset($want[$p['id']])));
    }
    if (!empty($o['brand'])) {
        $b = mb_strtolower((string)$o['brand']);
        $rows = array_values(array_filter($rows, fn($p) => mb_strtolower($p['brand']) === $b));
    }
    if (!empty($o['cat'])) {
        $c = mb_strtolower((string)$o['cat']);
        $rows = array_values(array_filter($rows, fn($p) => mb_strtolower($p['cat']) === $c));
    }
    if (!empty($o['seller'])) {
        $s = (string)$o['seller'];
        $rows = array_values(array_filter($rows, fn($p) => $p['seller_type'] === $s));
    }
    if (!empty($o['seller_uid'])) {
        $u = (string)$o['seller_uid'];
        $rows = array_values(array_filter($rows, fn($p) => $p['seller_uid'] === $u));
    }
    if (isset($o['min']) && $o['min'] !== '' && $o['min'] !== null) {
        $min = (int)$o['min'] * 100;
        $rows = array_values(array_filter($rows, fn($p) => $p['price_cents'] >= $min));
    }
    if (isset($o['max']) && $o['max'] !== '' && $o['max'] !== null) {
        $max = (int)$o['max'] * 100;
        $rows = array_values(array_filter($rows, fn($p) => $p['price_cents'] <= $max));
    }
    if (!empty($o['in_stock'])) {
        $rows = array_values(array_filter($rows, fn($p) => $p['stock'] > 0));
    }
    if (!empty($o['q'])) {
        $q     = mb_strtolower(trim((string)$o['q']));
        $terms = array_filter(preg_split('/\s+/', $q) ?: []);
        $rows  = array_values(array_filter($rows, function ($p) use ($terms) {
            $hay = mb_strtolower($p['brand'] . ' ' . $p['name'] . ' ' . $p['sku'] . ' ' . $p['cat'] . ' ' . $p['desc']);
            foreach ($terms as $t) {
                if (mb_strpos($hay, $t) === false) return false;   // TÜM kelimeler geçmeli
            }
            return true;
        }));
    }

    $total = count($rows);

    switch ((string)($o['sort'] ?? 'featured')) {
        case 'price_asc':  usort($rows, fn($a, $b) => $a['price_cents'] <=> $b['price_cents']); break;
        case 'price_desc': usort($rows, fn($a, $b) => $b['price_cents'] <=> $a['price_cents']); break;
        case 'new':        usort($rows, fn($a, $b) => ($b['created_at'] <=> $a['created_at']) ?: strcmp($a['id'], $b['id'])); break;
        default:
            // Öne çıkanlar → stokta olanlar → marka adı. Rastgelelik yok: aynı
            // ziyaretçi aynı sırayı görsün, sayfalama tutarlı kalsın.
            usort($rows, function ($a, $b) {
                return [$b['featured'], $a['stock'] > 0 ? 0 : 1, $a['brand'], $a['name']]
                   <=> [$a['featured'], $b['stock'] > 0 ? 0 : 1, $b['brand'], $b['name']];
            });
    }

    $per  = max(1, (int)($o['per_page'] ?? vr_config('per_page', 24)));
    $page = max(1, (int)($o['page'] ?? 1));
    $pages = max(1, (int)ceil($total / $per));
    $page = min($page, $pages);

    return [
        'rows'  => array_slice($rows, ($page - 1) * $per, $per),
        'total' => $total,
        'page'  => $page,
        'pages' => $pages,
        'per'   => $per,
    ];
}

/** Filtre kenar çubuğu için marka/kategori sayımları. */
function vr_facets(array $base = []): array
{
    $rows = vr_query(array_merge($base, ['per_page' => 100000, 'page' => 1]))['rows'];
    $brands = $cats = [];
    foreach ($rows as $p) {
        $brands[$p['brand']] = ($brands[$p['brand']] ?? 0) + 1;
        $cats[$p['cat']]     = ($cats[$p['cat']] ?? 0) + 1;
    }
    ksort($brands, SORT_NATURAL | SORT_FLAG_CASE);
    ksort($cats, SORT_NATURAL | SORT_FLAG_CASE);
    return ['brands' => $brands, 'cats' => $cats];
}

/** Aynı evden diğer parçalar. */
function vr_related(array $product, int $limit = 4): array
{
    $rows = vr_query(['brand' => $product['brand'], 'in_stock' => true, 'per_page' => $limit + 1])['rows'];
    $out  = [];
    foreach ($rows as $p) {
        if ($p['id'] === $product['id']) continue;
        $out[] = $p;
        if (count($out) >= $limit) break;
    }
    if (count($out) < $limit) {                    // aynı evde yeterli yoksa kategoriden tamamla
        foreach (vr_query(['cat' => $product['cat'], 'in_stock' => true, 'per_page' => $limit * 2])['rows'] as $p) {
            if ($p['id'] === $product['id']) continue;
            foreach ($out as $o) if ($o['id'] === $p['id']) continue 2;
            $out[] = $p;
            if (count($out) >= $limit) break;
        }
    }
    return $out;
}

/**
 * Ürün görseli. Gerçek fotoğraf yoksa deterministik üretilmiş kompozisyon
 * (assets/art.php) devreye girer — vitrin hiçbir zaman kırık kutu göstermez.
 */
function vr_product_image(array $p, int $index = 0): string
{
    if (!empty($p['images'][$index])) return $p['images'][$index];
    if (!empty($p['images'][0]))      return $p['images'][0];
    return vr_url('assets/art.php', ['s' => substr(sha1($p['id']), 0, 12), 'c' => $p['cat']]);
}

/**
 * Vitrin kartının üzerine gelince beliren ikinci kare. Gerçek fotoğrafı olan
 * satırda ikinci fotoğraf; olmayanda flat-lay kadrajı (yakın detay kartlık
 * boyutta okunmuyor). İkinci kare yoksa boş döner ve kart tek görselle kalır.
 */
function vr_product_image_alt(array $p): string
{
    if (!empty($p['images'][1])) return (string)$p['images'][1];
    if (!empty($p['images']))    return '';
    return vr_url('assets/art.php', ['s' => substr(sha1($p['id']), 0, 12), 'c' => $p['cat'], 'v' => 2]);
}

/**
 * Görüntülenecek ürün adı: marka zaten ayrı gösterildiği için addan baştaki
 * marka tekrarını atıyoruz. Sepet, kasa, e-posta ve Stripe satırları da bunu
 * kullanır — yoksa her yerde "BALENCIAGA BALENCIAGA …" çıkıyor.
 */
function vr_card_name(array $p): string
{
    $n = trim((string)($p['name'] ?? ''));
    $b = trim((string)($p['brand'] ?? ''));
    if ($b !== '' && stripos($n, $b) === 0) $n = trim(substr($n, strlen($b)));
    return $n !== '' ? $n : (string)($p['sku'] ?? '');
}

/**
 * Ürün galerisi. Gerçek fotoğraf varsa onlar; yoksa aynı siluetin beş farklı
 * kadrajı (önden, detay, flat lay, arkadan, kumaş makro) — ürün sayfası tek
 * kareyle yetinmesin. Gerçek fotoğraf GELDİĞİ anda üretilenler kendiliğinden
 * çekilir.
 *
 * Beş kare, dört değil: tek sayıda kare olduğunda ilk kadraj tam genişliğe
 * yayılıp altına 2×2 ızgara geliyor (bkz. .pdp__media--multi) — sütun boyu
 * sağdaki satın alma sütununa yaklaşıyor, altta boş oluk kalmıyor.
 */
function vr_product_gallery(array $p, int $max = 5): array
{
    $real = array_values(array_filter((array)($p['images'] ?? []), 'strlen'));

    // Üç ve üzeri gerçek fotoğraf varsa galeri zaten dolu — üretilen kareye
    // gerek yok.
    if (count($real) >= 3) return array_slice($real, 0, $max);

    /**
     * Bir ya da iki gerçek fotoğrafta galeriyi ÜRETİLEN kadrajlarla
     * tamamlıyoruz. Alternatifi tek başına duran bir kare: ürün sayfasının
     * yarısı boş kalıyor ve parça ucuz görünüyor.
     *
     * Gerçek fotoğraf DAİMA başta; üretilenler fotoğraf taklidi değil,
     * bilinçli çizgi kadrajlar — bir başkasının fotoğrafını buraya koymuyoruz.
     * Üçüncü fotoğraf geldiği an üretilenler kendiliğinden çekiliyor.
     */
    $seed = substr(sha1((string)$p['id']), 0, 12);
    $out  = $real;

    // Tek sayıda kare: ilk kadraj tam genişlik + altına 2×2 ızgara
    // (bkz. .pdp__media--multi). Çift sayı düz ızgarada da kusursuz.
    $target = $max;
    if ($target % 2 === 0) $target--;

    // Gerçek fotoğraf varsa önden görünüşü (v=0) atlıyoruz: onun yerini
    // zaten fotoğraf tutuyor, iki kez aynı açı olmasın.
    $v = $real ? 1 : 0;
    while (count($out) < $target && $v <= 4) {
        $out[] = vr_url('assets/art.php', ['s' => $seed, 'c' => $p['cat'], 'v' => $v]);
        $v++;
    }
    return array_slice($out, 0, $max);
}

function vr_product_url(array $p): string
{
    return vr_url('product.php', ['id' => $p['id']]);
}

/** Kategori adını görüntülemeye hazırla (veride İngilizce duruyor). */
function vr_cat_label(string $cat): string
{
    $map = vr_dict()['cat_' . vr_slug($cat)] ?? null;
    return is_string($map) ? $map : $cat;
}

/**
 * Gözle yapılmış kategori/isim düzeltmeleri.
 *
 * Dosya yoksa boş dizi döner ve hiçbir şey değişmez — düzeltme katmanı
 * isteğe bağlıdır, katalog onsuz da çalışır.
 */
function vr_category_fix(): array
{
    static $fix = null;
    if ($fix === null) {
        $raw = vr_store_read('category-fix.json', []);
        $fix = is_array($raw) ? $raw : [];
    }
    return $fix;
}

/** Izgara kontakt sayfası olan fotoğrafların yol => 1 haritası. */
function vr_photo_grid_index(): array
{
    static $ix = null;
    if ($ix === null) {
        $raw = vr_store_read('photo-quality.json', []);
        $ix  = is_array($raw) ? $raw : [];
    }
    return $ix;
}
