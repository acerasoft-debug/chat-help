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

/**
 * Resmî fiyat (UVP) ara: önce SKU, sonra marka + kategori.
 * Dönüş kuruş, yoksa null.
 *
 * Tablo data/official-prices.json'da ve ELLE dolduruluyor — kaynak
 * stapellerde resmî fiyat yok. Boş bırakılan satırlar hiçbir şey yapmıyor:
 * o ürünler eski fiyatlamada kalıyor ve üstü çizili fiyat basılmıyor.
 */
function vr_official_rrp(string $brand, string $cat, string $sku): ?int
{
    static $bySku = null, $byCat = null;
    if ($bySku === null) {
        $bySku = $byCat = [];
        foreach ((array)vr_store_read('official-prices.json', []) as $r) {
            if (!is_array($r)) continue;
            $rrp = (float)($r['rrp'] ?? 0);
            if ($rrp <= 0) continue;
            $cents = (int)round($rrp * 100);
            $s = strtoupper(trim((string)($r['sku'] ?? '')));
            if ($s !== '') { $bySku[$s] = $cents; continue; }
            $k = strtoupper(trim((string)($r['brand'] ?? ''))) . '||' . mb_strtolower(trim((string)($r['cat'] ?? '')));
            $byCat[$k] = $cents;
        }
    }
    $s = strtoupper(trim($sku));
    if ($s !== '' && isset($bySku[$s])) return $bySku[$s];
    $k = strtoupper(trim($brand)) . '||' . mb_strtolower(trim($cat));
    return $byCat[$k] ?? null;
}

/**
 * Marka basamağı × kategori çarpanı → satış fiyatı (kuruş), yoksa null.
 *
 * Cazip yuvarlama (X9) BİLEREK uygulanmıyor: işletmecinin verdiği çapalar
 * 220 ve 550 gibi tam sayılar, X9'a çevirmek dediğini bozardı. Tam avroya
 * yuvarlanıyor, o kadar.
 */
function vr_tier_price(string $brand, string $cat): ?int
{
    $base = (array)vr_config('brand_base_eur', []);
    $fact = (array)vr_config('cat_factor', []);
    if (!$base || !$fact) return null;

    $b = null;
    foreach ($base as $name => $eur) {
        if (strcasecmp(trim($name), trim($brand)) === 0) { $b = (float)$eur; break; }
    }
    if ($b === null || $b <= 0) return null;

    $f = null;
    foreach ($fact as $name => $mult) {
        if (strcasecmp(trim($name), trim($cat)) === 0) { $f = (float)$mult; break; }
    }
    if ($f === null || $f <= 0) return null;

    // Taban fiyat (T-Shirt, çarpan 1,0) AYNEN kalıyor: işletmecinin verdiği
    // rakam o. Türetilen kategoriler 5 avroluk ızgaraya oturuyor — çarpım
    // 264,10 ya da 323,00 gibi dağınık sayılar üretiyor ve bir vitrinde
    // düşünülmemiş duruyor. 5'lik ızgara çapaları bozmuyor (220, 550, 95
    // zaten beşin katı; 139 çarpansız olduğu için hiç dokunulmuyor).
    if (abs($f - 1.0) < 0.0001) return (int)round($b) * 100;
    return (int)(round($b * $f / 5) * 5) * 100;
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

    // Resmî fiyat girilmişse satış fiyatı ondan türüyor: UVP − %20
    // (official_discount_bps). Sabit outlet kuralları yine önde — onlar
    // işletmecinin bilerek dikte ettiği rakamlar, otomatik hesap onları
    // ezmemeli.
    $rrpCents = vr_official_rrp(
        (string)($p['brand'] ?? ''),
        (string)($p['cat'] ?? ''),
        (string)($p['sku'] ?? '')
    );
    if ($rrpCents !== null && !isset($p['rrp'])) $p['rrp'] = $rrpCents / 100;

    if ($ruleCents !== null) {
        $price = $ruleCents;
    } elseif ($rrpCents !== null) {
        $bps   = (int)vr_config('official_discount_bps', 2000);
        $price = vr_charm_round((int)round($rrpCents * (10000 - $bps) / 10000));
    } elseif (($tierCents = vr_tier_price((string)($p['brand'] ?? ''), (string)($p['cat'] ?? ''))) !== null) {
        $price = $tierCents;
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
            if (!$n) continue;

            /* FOTOĞRAFLAR BİRLEŞİYOR, ÜZERİNE YAZILMIYOR.
               Aynı ürün hem canlı katalogda hem içe aktarılmış stapelde
               olabiliyor ve iki satırın fotoğraf listesi aynı olmak zorunda
               değil: canlı tarafta operatörün sonradan eklediği kareler,
               stapelde bizim böldüğümüz paneller var. Satırı olduğu gibi
               değiştirmek canlı taraftakileri sessizce siliyordu — kendi
               fotoğrafımızı kendimiz kaybediyorduk.

               Sıra korunuyor: içe aktarılan liste önde (kategori/fiyat
               düzeltmeleri onunla geldi), canlı tarafın fazlası arkada.
               Katalog sonra vitrinlik olanı zaten öne alıyor. */
            if (isset($cat[$n['id']])) {
                $merged = array_values(array_unique(array_merge(
                    array_filter((array)$n['images'], 'strlen'),
                    array_filter((array)($cat[$n['id']]['images'] ?? []), 'strlen')
                )));
                if ($merged) $n['images'] = $merged;
            }
            $cat[$n['id']] = $n;
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
            $rows = vr_showcase_order($rows);
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

/**
 * Vitrin sırası — "Featured" seçildiğinde (varsayılan) kullanılan dizilim.
 *
 * NEDEN ÖZEL BİR SIRA
 * -------------------
 * Önceki sıra marka adına göre alfabetikti. Sonuç: mağazanın ilk sayfası
 * baştan sona BALMAIN iç çamaşırıydı — 24 karenin 24'ü mayo ve boxer. Katalog
 * iyi, ilk izlenim felaketti. Bir mağazanın vitrini ne rastgele ne alfabetik
 * dizilir; en iyi duran parça öne konur ve evler birbirine karıştırılır.
 *
 * İki kural:
 *   1. Parçanın kendi hâli — gerçek ve tek özneli fotoğrafı olan, birden çok
 *      kadrajı bulunan, kategorisi vitrinlik olan parça öne geçer.
 *   2. Evler sırayla — her evden bir parça, sonra tekrar başa. Böylece ilk
 *      sayfada on üç evin hepsi görünüyor.
 *
 * Rastgelelik yok: aynı ziyaretçi aynı sırayı görür, sayfalama tutarlı kalır.
 */
function vr_showcase_order(array $rows): array
{
    if (count($rows) < 2) return $rows;

    $grid = vr_photo_grid_index();
    $rank = function (array $p) use ($grid): array {
        $real = $single = 0;
        foreach ((array)($p['images'] ?? []) as $img) {
            $img = (string)$img;
            if ($img === '' || !str_starts_with($img, '/uploads/')) continue;
            $real++;
            if (!isset($grid[$img])) $single++;
        }
        return [
            empty($p['featured']) ? 1 : 0,
            ($p['stock'] ?? 0) > 0 ? 0 : 1,
            $single > 0 ? 0 : 1,              // ızgara kontakt sayfası geriye
            vr_showcase_cat_rank((string)($p['cat'] ?? '')),
            -min($real, 4),                   // çok kadrajlı öne
            -(int)($p['price_cents'] ?? 0),   // pahalı olan evin vitrin parçası
            (string)($p['name'] ?? ''),
        ];
    };

    // Evlere ayır, her evin içini sırala.
    $houses = [];
    foreach ($rows as $p) {
        $houses[(string)($p['brand'] ?? '')][] = $p;
    }
    foreach ($houses as &$list) {
        usort($list, fn($a, $b) => $rank($a) <=> $rank($b));
    }
    unset($list);

    // Evler kendi en iyi parçasına göre sıralanır: en güçlü ev başı çeker.
    uasort($houses, fn($a, $b) => $rank($a[0]) <=> $rank($b[0]));

    // Sırayla topla.
    $out  = [];
    $more = true;
    for ($i = 0; $more; $i++) {
        $more = false;
        foreach ($houses as $list) {
            if (isset($list[$i])) {
                $out[] = $list[$i];
                $more  = true;
            }
        }
    }
    return $out;
}

/**
 * Kategorinin vitrindeki yeri. Küçük sayı öne gelir.
 *
 * Sıra ticari: dışarıdan görünen, fotoğrafı iyi duran ve sepet tutarı yüksek
 * parçalar önde; iç giyim ve mayo en sonda. Listede olmayan kategori ortada
 * kalır, yani yeni bir kategori eklendiğinde hiçbir şey bozulmuyor.
 */
function vr_showcase_cat_rank(string $cat): int
{
    static $order = [
        'jacken' => 1, 'tracksuit sets' => 2, 'hoodies & sweatshirts' => 3,
        'westen' => 4, 'polos' => 5, 't-shirts' => 6, 'jeans' => 7,
        'trousers' => 8, 'shorts' => 9, 'jeans shorts' => 10, 'bademode' => 20,
    ];
    return $order[mb_strtolower(trim($cat))] ?? 12;
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
 * Kartlık sıra: ürünün kareleri, 4:5 karoda en iyi duracak olan başta.
 *
 * Dosya sırası tedarikçinin klasör sırası; onun kadrajla bir ilgisi yok. Bir
 * üründe hem düzgün bir boy çekimi hem de ızgaradan ayrılmış enlemesine bir
 * detay paneli varsa vitrine boy çekimi çıkmalı — detay ikinci kareye düşsün.
 *
 * Sıralama üç şeye bakıyor: kontakt sayfası mı (en ağır ceza), oranı karoya ne
 * kadar yakın, dosya sırası (eşitlikte ilk gelen kazanıyor — mevcut davranış
 * hiç bozulmasın diye).
 */
function vr_card_frames(array $p): array
{
    $imgs = array_values(array_filter((array)($p['images'] ?? []), 'strlen'));
    if (count($imgs) < 2) return $imgs;

    $grid  = vr_photo_grid_index();
    $shape = function_exists('vr_photo_shape_index') ? vr_photo_shape_index() : [];

    $score = [];
    foreach ($imgs as $i => $img) {
        $s = 0.0;
        if (isset($grid[$img])) $s += 100;                 // ızgara: en sona
        // İki–üç mankenli kare: bilgi olarak iyi, karo olarak kötü. Aynı yüz
        // yan yana iki kez görününce sayfa çoğaltılmış gibi duruyor.
        $s += max(0, (int)($shape[$img]['n'] ?? 1) - 1) * 14;
        $r = (float)($shape[$img]['r'] ?? 0.8);
        if ($r > 0) {
            // 0.8'e logaritmik uzaklık: 0.4 ile 1.6 aynı ağırlıkta cezalansın.
            $s += abs(log($r / 0.8)) * 10;
        }
        $s += $i * 0.35;                                   // dosya sırası: hafif tercih
        $score[$i] = $s;
    }
    asort($score);
    $out = [];
    foreach (array_keys($score) as $i) $out[] = $imgs[$i];
    return $out;
}

/** Vitrin kartının yüzü: karoya en iyi oturan kare. */
function vr_card_image(array $p): string
{
    $f = vr_card_frames($p);
    return $f[0] ?? vr_product_image($p);
}

/**
 * Vitrin kartının üzerine gelince beliren ikinci kare. Gerçek fotoğrafı olan
 * satırda ikinci fotoğraf; olmayanda flat-lay kadrajı (yakın detay kartlık
 * boyutta okunmuyor). İkinci kare yoksa boş döner ve kart tek görselle kalır.
 */
function vr_product_image_alt(array $p): string
{
    $f = vr_card_frames($p);
    if (isset($f[1])) return (string)$f[1];
    if ($f)           return '';
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

    /**
     * ELDE NE VARSA O.
     *
     * Burası eskiden bir ya da iki fotoğrafı olan ürünlerde galeriyi üretilen
     * çizgi kadrajlarla beşe tamamlıyordu — gerekçe "tek kare sayfayı yarım
     * bırakıyor" idi. 2×2 ızgarada küçük durdukları için sorun görünmüyordu.
     * Galeri masaüstünde tek sütuna geçince ortaya çıktı: tek gerçek fotoğrafı
     * olan bir BALMAIN sweatshirt sayfasında bir fotoğrafın altında ekran boyu
     * DÖRT çizim vardı. Sayfa ürünü değil, ürünün yokluğunu gösteriyordu.
     *
     * Bir fotoğraf bir fotoğraftır. Yan sütun zaten uzun ve yapışkan; tek
     * kadraj onun yanında durunca sayfa eksik görünmüyor. Üretilen kadraj
     * yalnızca hiç fotoğraf YOKSA devreye giriyor — o zaman da alternatifi
     * boş bir kutu.
     */
    if ($real) return array_slice($real, 0, $max);

    $seed = substr(sha1((string)$p['id']), 0, 12);
    return [vr_url('assets/art.php', ['s' => $seed, 'c' => $p['cat'], 'v' => 0])];
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
