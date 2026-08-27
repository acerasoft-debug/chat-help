<?php
/**
 * VESTRA — Katalog API'si (is ortagi beslemesi).
 *
 *   GET /api/catalog?a=products[&page=1&per=100&brand=&cat=&since=]
 *   GET /api/catalog?a=product&id=<id>
 *   GET /api/catalog?a=brands
 *   GET /api/catalog?a=whoami
 *
 * Kimlik: her istekte  Authorization: Bearer vsk_…
 * Anahtarlar ortak BASINA veriliyor ve tek tek iptal edilebiliyor (inc/api_keys.php).
 *
 * ── STOK BU UCTAN GECMIYOR, VE BU BILEREK BOYLE ─────────────────────────────
 * Sitede gorunen beden bazli stok rakamlari GERCEK ENVANTER DEGIL: inc/stock.php
 * onlari urun kimliginden tureterek uretiyor (kendi baslik yorumu da bunu acikca
 * yaziyor). Vitrinde bu savunulabilir bir gosterim; bir API'de degil.
 *
 * Cunku bir API'den "stock: 42" alan ortak onu musterisine SATAR. Turetilmis bir
 * sayiyi canli envanter diye vermek, Tokyo'daki bir musteriye var olmayan bir mali
 * satmak demektir -- ve fatura bize degil, ortagin itibarina cikar. O yuzden uc,
 * stok alanini bos gecmek yerine acikca "tracked: false" diyor: entegrasyonu yazan
 * kisi bunu okumak zorunda kalsin, tahmin etmesin.
 *
 * Gercek envanter takibi eklendigi gun burasi "tracked": true ve gercek sayilar
 * dondurur; sozlesme degismez, dolayisiyla ortagin kodu da degismez.
 *
 * ── EAN / GTIN ALANI YOK ────────────────────────────────────────────────────
 * Barkod verisi tutmuyoruz. Bos bir sutun gondermek, olmayan bir sutundan KOTU:
 * ortak onun uzerine kurar, sonra fark eder. Alan hic yok; ne oldugu da whoami
 * yanitinda yaziyor.
 */

require_once __DIR__.'/../inc/api_auth.php';     // api_json()
require_once __DIR__.'/../inc/api_keys.php';
require_once __DIR__.'/../inc/products.php';
require_once __DIR__.'/../inc/commission.php';

const VESTRA_API_BASE   = 'https://vestrasales.com';
const VESTRA_API_MAXPER = 200;

/* ── kimlik ─────────────────────────────────────────────────────────────────── */
$hdr = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
$key = preg_match('/^Bearer\s+(.+)$/i', trim($hdr), $m) ? trim($m[1]) : '';
$who = $key !== '' ? vestra_api_key_verify($key) : null;
if (!$who) {
    api_json(['ok' => false, 'error' => 'unauthorized',
              'hint' => 'Authorization: Bearer vsk_… basligi gerekiyor'], 401);
}
vestra_api_key_touch((string)$who['id']);

header('Cache-Control: private, max-age=60');

/* ── urun bicimi ────────────────────────────────────────────────────────────── */

/** Gorselleri mutlak adrese cevir: bir feed'i iceri alan taraf goreli yol cozemez. */
function api_abs(string $path): string {
    $path = trim($path);
    if ($path === '') return '';
    if (preg_match('#^https?://#i', $path)) return $path;
    return VESTRA_API_BASE.'/'.ltrim($path, '/');
}

function api_product(array $p): array {
    $id    = (string)($p['id'] ?? '');
    $tiers = [];
    foreach ((array)($p['tiers'] ?? []) as $t) {
        $min = (int)($t['min'] ?? 0); $pr = (float)($t['price'] ?? 0);
        if ($min > 0 && $pr > 0) $tiers[] = ['min_qty' => $min, 'price' => round($pr, 2)];
    }
    usort($tiers, fn($a, $b) => $a['min_qty'] <=> $b['min_qty']);

    $imgs = [];
    foreach ((array)($p['images'] ?? []) as $i) { $u = api_abs((string)$i); if ($u !== '') $imgs[] = $u; }
    if (!$imgs && !empty($p['image'])) $imgs[] = api_abs((string)$p['image']);

    $mode = (string)($p['mode'] ?? 'fixed');
    return [
        'id'          => $id,
        'sku'         => (string)($p['sku'] ?? ''),
        'brand'       => (string)($p['brand'] ?? ''),
        'name'        => (string)($p['name'] ?? ''),
        'category'    => (string)($p['cat'] ?? ''),
        'description' => (string)($p['desc'] ?? ''),
        'unit'        => (string)($p['unit'] ?? 'pc'),
        'moq'         => (int)($p['moq'] ?? 0),
        'sizes'       => (string)($p['sizes'] ?? ''),
        'colours'     => array_values(array_filter(array_map('strval', (array)($p['colors'] ?? [])))),
        /* Para birimi HER ZAMAN EUR. Vitrin ziyaretciye US$/A$/C$ cevirebiliyor ama
           o bir gosterim kolayligi; sozlesme ve fatura EUR. Bir feed'e cevrilmis
           fiyat koymak, ortagin kendi fiyatini bir gun eski kurla hesaplamasi
           demek olurdu. */
        'currency'    => 'EUR',
        'pricing'     => $mode === 'offer' ? 'on_request' : $mode,   // fixed | sale | on_request
        'price_tiers' => $tiers,
        'price_from'  => $tiers ? $tiers[0]['price'] : null,
        'list_price'  => ($lp = (float)($p['list'] ?? 0)) > 0 ? round($lp, 2) : null,
        'rrp'         => ($rr = (float)($p['rrp'] ?? 0)) > 0 ? round($rr, 2) : null,
        'origin'      => (string)($p['origin'] ?? ''),
        'seller'      => empty($p['hide_seller']) ? (string)($p['seller'] ?? '') : 'via VESTRA',
        'images'      => $imgs,
        'url'         => $id !== '' ? VESTRA_API_BASE.'/product?id='.rawurlencode($id) : '',
        'added_at'    => (string)($p['added_at'] ?? ''),
        /* Bkz. dosya basligi: rakam uydurmaktansa takip etmedigimizi soyluyoruz. */
        'stock'       => ['tracked' => false, 'quantity' => null,
                          'note'    => 'Per-unit stock is not tracked; confirm availability before promising a delivery date.'],
    ];
}

/* ── uclar ──────────────────────────────────────────────────────────────────── */

$action = (string)($_GET['a'] ?? 'products');

if ($action === 'whoami') {
    api_json([
        'ok'      => true,
        'partner' => (string)$who['label'],
        'key'     => (string)$who['hint'],
        'since'   => (string)($who['created_at'] ?? ''),
        'catalogue' => [
            'currency' => 'EUR',
            'fields'   => ['id','sku','brand','name','category','description','unit','moq',
                           'sizes','colours','price_tiers','list_price','rrp','origin','seller','images','url','added_at'],
            /* Ne YOK sorusunun cevabi da ucun kendisinde dursun: entegrasyonu
               yazan kisi bunu bir e-postayi beklemeden ogrensin. */
            'not_available' => [
                'ean_gtin'   => 'Barcode data is not held for this catalogue.',
                'live_stock' => 'Per-unit stock is not tracked. Every product reports stock.tracked = false.',
                'xml_ftp'    => 'No XML or FTP feed. JSON here, or CSV/XLSX from the site.',
            ],
        ],
    ]);
}

if ($action === 'brands') {
    $count = [];
    foreach (vestra_products() as $p) {
        $b = trim((string)($p['brand'] ?? ''));
        if ($b !== '') $count[$b] = ($count[$b] ?? 0) + 1;
    }
    ksort($count, SORT_NATURAL | SORT_FLAG_CASE);
    api_json(['ok' => true, 'total' => count($count),
              'items' => array_map(fn($b, $n) => ['brand' => $b, 'products' => $n],
                                   array_keys($count), array_values($count))]);
}

if ($action === 'product') {
    $id = trim((string)($_GET['id'] ?? ''));
    $p  = $id !== '' ? vestra_find($id) : null;
    if (!$p) api_json(['ok' => false, 'error' => 'not_found'], 404);
    api_json(['ok' => true, 'item' => api_product($p)]);
}

if ($action === 'products') {
    $all = vestra_products();

    $brand = mb_strtolower(trim((string)($_GET['brand'] ?? '')));
    $cat   = mb_strtolower(trim((string)($_GET['cat'] ?? '')));
    $since = trim((string)($_GET['since'] ?? ''));
    $sinceTs = $since !== '' ? (strtotime($since) ?: 0) : 0;
    if ($since !== '' && $sinceTs === 0) {
        api_json(['ok' => false, 'error' => 'bad_since',
                  'hint' => 'since bir tarih olmali (ornek: 2026-08-01)'], 400);
    }

    $items = [];
    foreach ($all as $p) {
        if ($brand !== '' && mb_strtolower(trim((string)($p['brand'] ?? ''))) !== $brand) continue;
        if ($cat !== ''   && mb_strtolower(trim((string)($p['cat'] ?? '')))   !== $cat)   continue;
        if ($sinceTs > 0) {
            $ts = strtotime((string)($p['added_at'] ?? '')) ?: 0;
            if ($ts < $sinceTs) continue;
        }
        $items[] = $p;
    }

    $total = count($items);
    $per   = (int)($_GET['per'] ?? 100);
    $per   = max(1, min(VESTRA_API_MAXPER, $per));
    $pages = max(1, (int)ceil($total / $per));
    $page  = max(1, min($pages, (int)($_GET['page'] ?? 1)));

    api_json([
        'ok'           => true,
        'generated_at' => date('c'),
        'page'         => $page,
        'per_page'     => $per,
        'pages'        => $pages,
        'total'        => $total,
        /* Sonraki sayfayi ortagin kendi kurmasi gerekmesin: uc hazir veriyor,
           son sayfada null. Sayfalamanin yanlis yazilmasi, feed entegrasyonlarinda
           en sik goruleni ve en sessiz olani -- yarim katalog cekilir ve kimse
           fark etmez. */
        'next_page'    => $page < $pages ? $page + 1 : null,
        'items'        => array_map('api_product', array_slice($items, ($page - 1) * $per, $per)),
    ]);
}

api_json(['ok' => false, 'error' => 'unknown_action',
          'actions' => ['products', 'product', 'brands', 'whoami']], 400);
