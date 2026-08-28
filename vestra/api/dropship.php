<?php
/**
 * VESTRA — Dropship partner API.
 *
 *   GET  /api/dropship?a=list
 *        → every dropship-enabled article with its single-piece price.
 *
 *   GET  /api/dropship?a=stock&id=<id>
 *        → price, the three shipping zones, and whether stock is tracked.
 *
 *   POST /api/dropship?a=order
 *        JSON body: { "id", "colour", "size", "qty"?, "zone"? | "country"?,
 *                      "reference"?, "customer_email"?, "customer_name"? }
 *        → creates a pending order + a Stripe Checkout Session and returns
 *          { ok, ref, checkout_url }.
 *
 * WHO BUYS: a verified trade partner, for their own customer. The partner
 * completes checkout and enters THEIR CUSTOMER'S delivery address; no contract
 * of sale arises between VESTRA and that end customer (Terms, clause 2a).
 *
 * PRICE: the wholesale price of the smallest quantity tier plus 20%.
 *
 * SHIPPING: one zone per order, chosen BEFORE the session is created —
 * EU 16 EUR; GB, US, JP, SG, AE, SA, QA 30 EUR; AU, CA, KR 35 EUR. Pass "zone"
 * (the destination's ISO-2 code, or EU) or "country" (ISO-2, mapped for you).
 * The session then accepts only that zone's countries, so the rate charged and
 * the address entered cannot diverge. a=stock returns the live table rather
 * than these figures, so read it from there. Duties and import taxes at
 * destination are not included.
 *
 * STOCK: per-unit stock is not tracked for catalogue articles; a=stock reports
 * stock_tracked=false. Availability is confirmed with the seller after the
 * order and refunded in full if it cannot be met.
 *
 * Auth: every request needs  Authorization: Bearer <DROPSHIP_API_KEY>.
 * Ralph Lauren, Lacoste and boxershorts are excluded from dropshipping.
 */
require_once __DIR__ . '/../inc/api_auth.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/products.php';
require_once __DIR__ . '/../inc/stripe.php';
require_once __DIR__ . '/../inc/escrow.php';
require_once __DIR__ . '/../inc/dropship.php';

api_require_key();

const DROPSHIP_DEFAULT_ID = 'lac-polo-paris';

$action = (string)($_GET['a'] ?? '');

if ($action === 'stock' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = trim((string)($_GET['id'] ?? DROPSHIP_DEFAULT_ID));
    $p  = vestra_find($id);
    $ds = $p ? vestra_dropship_of($p) : null;
    if (!$p || $ds === null) {
        api_json(['ok' => false, 'error' => 'not_dropship_enabled'], 404);
    }
    $ship = [];
    foreach (vestra_dropship_zones() as $code => [$label, $fee]) $ship[$code] = $fee;
    api_json([
        'ok'       => true,
        'id'       => $p['id'],
        'brand'    => (string)($p['brand'] ?? ''),
        'name'     => (string)($p['name'] ?? ''),
        'currency' => 'eur',
        'price'    => (float)$ds['price'],
        'shipping' => $ship,
        /* Siparis govdesindeki colour/size serbest metin. Ortagin ne yazacagini
           tahmin etmesi gerekmesin diye ilanin bildigi degerler burada: bos dizi
           "bilmiyoruz" demek, "yok" demek degil -- o durumda ortak kendi
           musterisinin verdigi degeri yazar. */
        'colours'  => vestra_colour_options($p),
        'sizes'    => vestra_size_options($p),
        /* Katalog geneline acilan urunlerde adet bazli stok TUTULMUYOR ve
           uydurulmuyor. Bos bir harita "hepsi tukendi" diye okunabilecegi
           icin durumu ayrica soyluyoruz. */
        'stock'    => $ds['stock'] ?? [],
        'stock_tracked' => !empty($ds['stock']),
        'note'     => !empty($ds['stock']) ? null
                    : 'Per-unit stock is not tracked for this article; availability is confirmed with the seller after the order.',
    ]);
}

if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $out = [];
    foreach (vestra_products() as $p) {
        $ds = vestra_dropship_of($p);
        if ($ds === null) continue;
        $out[] = ['id' => (string)($p['id'] ?? ''), 'sku' => (string)($p['sku'] ?? ''),
                  'brand' => (string)($p['brand'] ?? ''), 'name' => (string)($p['name'] ?? ''),
                  'currency' => 'eur', 'price' => (float)$ds['price']];
    }
    api_json(['ok' => true, 'total' => count($out), 'items' => $out]);
}

if ($action === 'order' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = api_json_body();

    $id         = trim((string)($body['id'] ?? DROPSHIP_DEFAULT_ID));
    $colour     = trim((string)($body['colour'] ?? ''));
    $size       = trim((string)($body['size'] ?? ''));
    $qty        = max(1, (int)($body['qty'] ?? 1));
    $partnerRef = trim((string)($body['reference'] ?? ''));
    $custEmail  = trim((string)($body['customer_email'] ?? ''));
    $custName   = trim((string)($body['customer_name'] ?? ''));
    /* Bolge ya dogrudan ('zone': EU|US|JP) ya da varis ulkesinden ('country':
       ISO-2) verilebiliyor. Ortagin kendi sisteminde genelde ulke var, bolge
       yok; ulkeyi bolgeye biz cevirirsek entegrasyona bir eslestirme tablosu
       yazdirmamis oluruz. Verilmezse Avrupa. */
    $zone = trim((string)($body['zone'] ?? ''));
    if ($zone === '' && ($cc = trim((string)($body['country'] ?? ''))) !== '') {
        $zone = vestra_dropship_zone_for_country($cc);
    }
    $zone = vestra_dropship_zone($zone);

    $p = vestra_find($id);
    if (!$p) api_json(['ok' => false, 'error' => 'not_dropship_enabled'], 404);

    $r = dropship_create_order($p, $colour, $size, $qty, $custEmail, $custName, $partnerRef, null, null, $zone);
    $status = $r['ok'] ? 200 : ($r['status'] ?? 400);
    unset($r['status']);
    api_json($r, $status);
}

api_json(['ok' => false, 'error' => 'unknown_action'], 400);
