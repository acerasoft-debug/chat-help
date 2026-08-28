<?php
/**
 * VESTRA — dropship order state + checkout.
 *
 * A dropship order is a single-item purchase at a product's dropship.price,
 * placed either through the external API (api/dropship.php, a partner's own
 * system) or the on-site "Buy now" form (dropship-checkout.php) — either
 * way the buyer is whoever completes Stripe Checkout, never necessarily a
 * logged-in VESTRA account. Same two payment paths as sample orders (see
 * inc/samples.php): direct charge on the seller's connected account when
 * Connect-ready, otherwise VESTRA's platform account.
 *
 *   ref                DRP-xxxxxxxx
 *   product_id / brand / name / sku / colour / size / qty
 *   partner_reference  the API caller's own order id, echoed back (optional)
 *   customer_email/name  optional, supplied by the caller
 *   shipping_address   filled in from Stripe once paid
 *   amount             EUR (float) — item total; shipping is a separate
 *                       Stripe shipping_option, not included here
 *   seller_uid / acct_id / fee / payout   same meaning as samples
 *   session_id / payment_intent
 *   status             pending → paid → released (direct-charge only)
 *   created / paid_at / released_at
 */

require_once __DIR__ . '/products.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/stripe.php';
require_once __DIR__ . '/escrow.php';

function dropship_file(): string { return __DIR__ . '/../data/dropship_orders.json'; }

function dropship_all(): array {
    $f = dropship_file();
    if (!is_readable($f)) return [];
    $j = json_decode((string) file_get_contents($f), true);
    return is_array($j) ? $j : [];
}

function dropship_get(string $ref): ?array {
    $all = dropship_all();
    return $all[$ref] ?? null;
}

function dropship_save(array $rec): void {
    $ref = $rec['ref'] ?? '';
    if ($ref === '') return;
    $all = dropship_all();
    $all[$ref] = $rec;
    $dir = dirname(dropship_file());
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @file_put_contents(dropship_file(), json_encode($all, JSON_PRETTY_PRINT), LOCK_EX);
}

function dropship_update(string $ref, array $patch): ?array {
    $all = dropship_all();
    if (!isset($all[$ref])) return null;
    $all[$ref] = array_merge($all[$ref], $patch);
    @file_put_contents(dropship_file(), json_encode($all, JSON_PRETTY_PRINT), LOCK_EX);
    return $all[$ref];
}

/** How many units are left for one product/colour/size (0 if unknown). */
function dropship_stock_left(array $p, string $colour, string $size): int {
    return (int)($p['dropship']['stock'][$colour][$size] ?? 0);
}

/* ── KATALOG GENELI DROPSHIP ──────────────────────────────────────────────────
 *
 * Once dropship tek tek ilana elle aciliyordu: her urunun kendi dropship
 * blogu vardi (fiyat, iki kargo ucreti, renk/beden stok haritasi) ve
 * katalogda bunu tasiyan TEK urun vardi. Artik kural katalogun tamamina
 * isliyor ve rakamlar TURETILIYOR -- 344 ilana elle blok yazmak ne
 * yapilabilir ne de bakimi mumkun bir sey.
 *
 * Kural:
 *   - Ralph Lauren, Lacoste ve boxershort HARIC butun katalog dropship'e acik
 *   - fiyat = toptan fiyat + %20   (saticilarin tek adet isleme payi)
 *   - kargo bolgeye gore: bkz. vestra_dropship_zones()
 */

const VESTRA_DROPSHIP_MARKUP = 0.20;

/** Dropship'e KAPALI markalar (kucuk harf karsilastirilir). */
function vestra_dropship_excluded_brands(): array {
    return ['ralph lauren', 'lacoste'];
}

/**
 * Dropship'e KAPALI urun turleri: kategoride YA DA urun adinda gecmesi yeter.
 *
 * Neden ad da taraniyor: kategori satici tarafindan seciliyor ve bosluk birakma
 * ya da "Basics" gibi genel bir kutuya atma cok yaygin -- boxer bir ilan
 * "Underwear" degil "Basics" altinda durabiliyor. Kurali yalnizca kategoriye
 * baglamak, kuralin en cok isleyecegi ilanlari disarida birakirdi.
 *
 * Govde eslesmesi ("boxer"), cunku ayni sey Boxershorts / Boxer Shorts /
 * Boxershort / Boxerbriefs diye yaziliyor ve hepsi ayni urun.
 */
function vestra_dropship_excluded_terms(): array {
    return ['boxer'];
}

/**
 * Kargo bolgeleri: kod => [Stripe'ta gorunecek ad, EUR ucret].
 *
 * AB disindaki her varis TEK ULKELIK kendi bolgesi, ayni ucreti tasiyanlar bile.
 * Tek bir "30 EUR bolgesi" kurmak daha kisa olurdu ama Stripe oturumu o zaman
 * yedi ulkenin adresini birden kabul ederdi: Japonya'ya diye acilan bir siparis
 * Katar'a gidebilirdi ve satici bunu ancak paket hazirlarken gorurdu. Bolge
 * varisin kendisi olunca, secilen ucret ile girilebilecek adres ayni sey.
 *
 * Bolge kodu = varis ulkesinin ISO-2 kodu (AB icin 'EU'). Boylece ulkeden
 * bolgeye cevirim bir arama tablosu istemiyor ve eski siparislerdeki 'US' /
 * 'JP' kodlari da aynen gecerli kaliyor.
 */
function vestra_dropship_zones(): array {
    /* Ucuncu deger IS GUNU olarak gonderi suresi. Ucretle AYNI yerde duruyor,
       cunku ikisi de "bu varise ne kadara ve ne kadar surede" sorusunun parcasi;
       ayri bir tabloya koymak, yeni bir bolge eklendiginde birinin guncellenip
       digerinin unutulmasi demekti. */
    return [
        'EU' => ['EU delivery',                   16.00, '5–7'],
        'GB' => ['United Kingdom delivery',       30.00, '5–10'],
        'US' => ['United States delivery',        30.00, '5–10'],
        'JP' => ['Japan delivery',                30.00, '7–14'],
        'SG' => ['Singapore delivery',            30.00, '7–14'],
        /* "Dubai" bir sehir; Stripe ulke istiyor, o yuzden BAE. */
        'AE' => ['United Arab Emirates delivery', 30.00, '5–8'],
        'SA' => ['Saudi Arabia delivery',         30.00, '5–8'],
        'QA' => ['Qatar delivery',                30.00, '5–8'],
        'AU' => ['Australia delivery',            35.00, '7–14'],
        'CA' => ['Canada delivery',               35.00, '5–10'],
        'KR' => ['South Korea delivery',          35.00, '7–14'],
    ];
}

/** Bolgenin gonderi suresi, is gunu araligi olarak ("5–7"). */
function vestra_dropship_transit(string $zone): string {
    return (string)(vestra_dropship_zones()[vestra_dropship_zone($zone)][2] ?? '');
}

/** Stripe'in adres kabul edecegi butun ulkeler: AB 27 + tekil bolgeler. */
function vestra_dropship_countries(): array {
    $out = [];
    foreach (array_keys(vestra_dropship_zones()) as $z) {
        foreach (vestra_dropship_zone_countries($z) as $cc) $out[$cc] = true;
    }
    return array_keys($out);
}

/**
 * Bir bolgenin kapsadigi ulkeler.
 *
 * NEDEN GEREKLI: Stripe Checkout kargo seceneklerini adrese gore KISITLAMIYOR
 * -- hepsini herkese gosteriyor. Ilk halinde Tokyo'ya gonderilen bir siparis
 * "Europe delivery 16 EUR" secilerek odenebiliyordu ve aradaki 14 euro kimse
 * fark etmeden kayboluyordu. Cozum, secimi ODEME OTURUMU ACILMADAN once
 * yapmak: oturuma yalnizca o bolgenin ucreti ve yalnizca o bolgenin ulkeleri
 * konuyor, boylece secilen kargo ile girilen adres AYRISAMIYOR.
 */
function vestra_dropship_zone_countries(string $zone): array {
    $z = strtoupper(trim($zone));
    if ($z === 'EU') {
        require_once __DIR__ . '/money.php';             // vestra_eu_countries()
        return vestra_eu_countries();
    }
    return isset(vestra_dropship_zones()[$z]) ? [$z] : vestra_dropship_zone_countries('EU');
}

/** Gecerli bolge kodu, taninmayan deger icin varsayilan 'EU'. */
function vestra_dropship_zone(string $zone): string {
    $z = strtoupper(trim($zone));
    return isset(vestra_dropship_zones()[$z]) ? $z : 'EU';
}

/**
 * Varis ulkesinden bolge. Ortagin kendi sisteminde genelde ulke var, bolge yok;
 * cevirimi burada yaparsak entegrasyona eslestirme tablosu yazdirmamis oluruz.
 * Taninmayan ulke AB'ye dusuyor -- ve AB'ye dusen bir siparis o ulkenin adresini
 * kabul etmiyor, yani sessizce yanlis ucretle gitmiyor, en fazla reddediliyor.
 */
function vestra_dropship_zone_for_country(string $cc): string {
    $c = strtoupper(trim($cc));
    if ($c === '') return 'EU';
    if (isset(vestra_dropship_zones()[$c]) && $c !== 'EU') return $c;
    return 'EU';
}

/**
 * Zam uygulanacak taban fiyat: EN DUSUK ADETLI kademenin fiyati.
 *
 * Kademeler adet arttikca UCUZLUYOR, yani en dusuk adetli kademe en PAHALI
 * olani. Tek adet alan biri hacim indirimini hak etmiyor; taban olarak
 * ucuz kademeyi almak, bir adedi 300 adet fiyatindan satmak olurdu.
 */
function vestra_dropship_base_price(array $p): float {
    $tiers = (array)($p['tiers'] ?? []);
    $best = null;
    foreach ($tiers as $t) {
        $min = (int)($t['min'] ?? 0); $pr = (float)($t['price'] ?? 0);
        if ($min <= 0 || $pr <= 0) continue;
        if ($best === null || $min < $best[0]) $best = [$min, $pr];
    }
    if ($best !== null) return $best[1];
    $fallback = (float)($p['list'] ?? $p['price'] ?? 0);
    return $fallback > 0 ? $fallback : 0.0;
}

/**
 * Bu urunun dropship ayari, ya da null (kapali).
 * Elle yazilmis bir dropship blogu varsa O gecerli; ama marka ve urun turu
 * yasaklari her seyin ustunde -- "Lacoste haric" dendiginde elle acilmis bir
 * Lacoste ilani da kapanir, yoksa kural kural olmazdi.
 */
function vestra_dropship_of(array $p): ?array {
    $brand = mb_strtolower(trim((string)($p['brand'] ?? '')));
    if ($brand !== '' && in_array($brand, vestra_dropship_excluded_brands(), true)) return null;

    $hay = mb_strtolower(trim((string)($p['cat'] ?? '') . ' ' . (string)($p['name'] ?? '')));
    foreach (vestra_dropship_excluded_terms() as $term) {
        if ($term !== '' && mb_strpos($hay, $term) !== false) return null;
    }

    if (!empty($p['dropship']['enabled'])) {
        $d = (array)$p['dropship'];
        $d['derived'] = false;
        return $d;
    }
    /* "Teklife acik" ilanda sabit fiyat yok; zam uygulanacak bir taban da yok. */
    if ((string)($p['mode'] ?? 'fixed') === 'offer') return null;

    /* Durum alanina BAKMIYORUZ ve bu bilerek. Ilk yazdigimda status'un 'active'
       olmasini sart kosmustum; canli ilanlarda deger 'approved' ve o tek satir
       18 DSQUARED2 urununu sessizce dropship disinda birakti. Zaten
       vestra_products() yalnizca YAYINDAKI ilanlari donduruyor -- yayinda
       olmanin testi bir kez yapilmis durumda, ikinci kez ve yanlis sozlukle
       yapmak eleme disinda bir ise yaramiyor. */

    $base = vestra_dropship_base_price($p);
    if ($base <= 0) return null;

    return [
        'enabled' => true,
        'derived' => true,
        'base'    => round($base, 2),
        'markup'  => VESTRA_DROPSHIP_MARKUP,
        'price'   => round($base * (1 + VESTRA_DROPSHIP_MARKUP), 2),
    ];
}

function vestra_dropship_enabled(array $p): bool { return vestra_dropship_of($p) !== null; }

/**
 * Stripe odeme sayfasinda gorunecek satir adi.
 *
 * MARKA ADI BILEREK YOK. Odeme sayfasina "Balenciaga …" basmak, yetkili
 * bayisi olmadigimiz bir markanin adini bir odeme ekranina koymak demek --
 * marka sahibinin sikayeti ve Stripe'in hesap incelemesi buradan geliyor.
 * Alicinin ne aldigini SKU ve VESTRA referansi zaten tanimliyor; siparis
 * kaydinda marka ve urun adi tam haliyle duruyor, yani bilgi kaybolmuyor,
 * sadece odeme ekranina cikmiyor.
 */
function vestra_dropship_line_name(array $p, string $colour = '', string $size = '', string $orderRef = ''): string {
    $sku = trim((string)($p['sku'] ?? ''));
    $bits = ['Dropshipping'];
    if ($sku !== '') $bits[] = 'SKU ' . $sku;
    /* Ident olarak SIPARIS referansi kullaniliyor, urun kimligi degil. Kimlikler
       markayi tasiyabiliyor ("amiri-core-polo") ve o zaman marka adini odeme
       ekranindan uzak tutma cabasi kendi kendini bozardi. Siparis referansi hem
       notr hem de destek yazismasinda zaten sorulan numara. */
    if ($orderRef !== '') $bits[] = 'Ident. ' . strtoupper($orderRef);
    $line = implode(' · ', $bits);
    $var  = trim(trim($colour) . ($size !== '' ? ' / ' . trim($size) : ''), ' /');
    return $var !== '' ? $line . ' — ' . $var : $line;
}

/**
 * Create a pending dropship order + a Stripe Checkout Session for it, and
 * return either {ok:true, ref, checkout_url} or {ok:false, error, message,
 * status}. Shared by api/dropship.php (partner API, JSON) and
 * dropship-checkout.php (on-site "Buy now" form, redirect) so the payment
 * logic exists exactly once.
 */
function dropship_create_order(
    array $p, string $colour, string $size, int $qty,
    string $custEmail = '', string $custName = '', string $partnerRef = '',
    ?string $successUrl = null, ?string $cancelUrl = null, string $zone = 'EU'
): array {
    $zone = vestra_dropship_zone($zone);
    if ($colour === '' || $size === '') {
        return ['ok' => false, 'error' => 'missing_fields', 'message' => 'colour and size are required', 'status' => 400];
    }
    if ($custEmail !== '' && !filter_var($custEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'invalid_email', 'message' => 'invalid customer_email', 'status' => 400];
    }
    $ds = vestra_dropship_of($p);
    if ($ds === null) {
        return ['ok' => false, 'error' => 'not_dropship_enabled', 'message' => 'this product is not dropship-enabled', 'status' => 404];
    }

    $qty = max(1, $qty);
    /* Stok kontrolu YALNIZCA gercek bir stok haritasi olan ilanlarda. Katalog
       geneline turetilen dropship'te boyle bir harita yok ve uydurulmuyor:
       katalog urunleri "buyuk olasilikla gonderime acik", kesinlik siparisten
       sonra saticiyla teyit ediliyor. Olmayan bir sayiyi kapi olarak kullanmak,
       ya her siparisi reddeder ya da hicbirini -- ikisi de bilgi degil. */
    if (!empty($ds['stock'])) {
        $left = dropship_stock_left($p, $colour, $size);
        if ($left <= 0 || $qty > $left) {
            return ['ok' => false, 'error' => 'out_of_stock', 'message' => "Only {$left} left in {$colour} / {$size}.", 'status' => 409];
        }
    }
    if (!stripe_available()) {
        return ['ok' => false, 'error' => 'payments_unavailable', 'message' => 'payments are not configured', 'status' => 503];
    }

    $unit   = (float)$ds['price'];
    $amount = round($unit * $qty, 2);
    $cents  = (int) round($amount * 100);

    $ref = 'DRP-' . strtoupper(bin2hex(random_bytes(4)));

    // Resolve the product's seller and whether they can receive a direct charge —
    // same readiness check the wholesale escrow cart and sample orders use.
    $seller = null;
    if (!empty($p['seller_uid'])) {
        foreach (auth_accounts() as $a) { if (($a['id'] ?? '') === $p['seller_uid']) { $seller = $a; break; } }
    }
    $directCharge = $seller && !empty($seller['stripe_account_id']) && escrow_seller_ready($seller);

    $feeCents = 0; $payout = $amount;
    if ($directCharge) {
        $feeCents = (int) round($cents * vestra_seller_commission_rate($seller['membership_tier'] ?? ''));
        $payout   = round($amount - $feeCents / 100, 2);
    }

    $rec = [
        'ref'               => $ref,
        'product_id'        => $p['id'],
        'brand'             => (string)($p['brand'] ?? ''),
        'name'              => (string)($p['name'] ?? ''),
        'sku'               => (string)($p['sku'] ?? ''),
        'colour'            => $colour,
        'size'              => $size,
        'qty'               => $qty,
        'partner_reference' => $partnerRef,
        'customer_email'    => $custEmail,
        'customer_name'     => $custName,
        'amount'            => $amount,
        'currency'          => 'eur',
        'ship_zone'         => $zone,
        'ship_fee'          => vestra_dropship_zones()[$zone][1],
        'status'            => 'pending',
        'created'           => date('c'),
    ];
    if ($seller) $rec['seller_uid'] = $seller['id'];
    if ($directCharge) { $rec['acct_id'] = $seller['stripe_account_id']; $rec['fee'] = $feeCents / 100; $rec['payout'] = $payout; }
    dropship_save($rec);

    /* Kargo bolgesi ODEME OTURUMUNDAN ONCE belli. Oturuma yalnizca o bolgenin
       ucreti ve yalnizca o bolgenin ulkeleri giriyor -- yani secilen kargo ile
       girilen adres ayrisamiyor. Uc secenegi birden koydugumuz ilk halinde
       Tokyo'ya giden bir siparis "Europe 16 EUR" ile odenebiliyordu. */
    [$zLabel, $zFee] = vestra_dropship_zones()[$zone];
    $lineName   = vestra_dropship_line_name($p, $colour, $size, $ref);
    $successUrl = $successUrl ?? ('https://vestrasales.com/dropship-confirm?ref=' . rawurlencode($ref) . '&paid=1');
    $cancelUrl  = $cancelUrl  ?? ('https://vestrasales.com/dropship-confirm?ref=' . rawurlencode($ref));

    $extra = [
        'shipping_address_collection' => ['allowed_countries' => vestra_dropship_zone_countries($zone)],
        'shipping_options'            => [
            ['shipping_rate_data' => [
                'type'         => 'fixed_amount',
                'fixed_amount' => ['amount' => (int)round($zFee * 100), 'currency' => 'eur'],
                'display_name' => $zLabel,
            ]],
        ],
    ];

    try {
        if ($directCharge) {
            $session = stripe_escrow_checkout(
                $seller['stripe_account_id'],
                [['name' => $lineName, 'amount' => $cents, 'qty' => 1]],
                $feeCents, $ref, $custEmail, 'eur', 'dropship',
                $successUrl, $cancelUrl, $extra
            );
        } else {
            $params = [
                'mode'                => 'payment',
                'client_reference_id' => $ref,
                'line_items'          => [[
                    'quantity'   => 1,
                    'price_data' => [
                        'currency'     => 'eur',
                        'unit_amount'  => $cents,
                        'product_data' => ['name' => $lineName],
                    ],
                ]],
                'metadata'            => ['kind' => 'dropship', 'order_ref' => $ref],
                'payment_intent_data' => ['metadata' => ['kind' => 'dropship', 'order_ref' => $ref]],
                'success_url'         => $successUrl,
                'cancel_url'          => $cancelUrl,
            ] + $extra;
            if ($custEmail !== '') $params['customer_email'] = $custEmail;
            $session = stripe_api('POST', '/v1/checkout/sessions', $params);
        }
        dropship_update($ref, ['session_id' => $session->id ?? '']);
        return ['ok' => true, 'ref' => $ref, 'reference' => $partnerRef, 'checkout_url' => $session->url];
    } catch (\Throwable $e) {
        error_log('[VESTRA dropship] Checkout error: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'stripe_error', 'message' => $e->getMessage(), 'status' => 502];
    }
}

/** Mark a dropship order PAID. Idempotent: only flips pending→paid once. */
function dropship_mark_paid(string $ref, string $paymentIntent, ?array $shippingAddress = null): ?array {
    $rec = dropship_get($ref);
    if (!$rec || ($rec['status'] ?? '') !== 'pending') return null;
    $patch = [
        'status'         => 'paid',
        'payment_intent' => $paymentIntent,
        'paid_at'        => date('c'),
    ];
    if ($shippingAddress) $patch['shipping_address'] = $shippingAddress;
    return dropship_update($ref, $patch);
}

/**
 * Decrement dropship stock for one colour/size by $qty, clamped at 0.
 * Best-effort — the JSON store has no cross-request locking (same tradeoff
 * as the rest of the catalog: see set-product.yml), so a burst of concurrent
 * orders on the last unit could in theory oversell by a unit; that's logged,
 * not fatal, since the payment has already been captured by the time this
 * runs.
 */
function dropship_decrement_stock(string $productId, string $colour, string $size, int $qty): void {
    $all = vestra_listings();
    foreach ($all as $i => $l) {
        if (($l['id'] ?? '') !== $productId) continue;
        /* Stok haritasi OLMAYAN ilanda hicbir sey yapma. Bu satir olmadan
           fonksiyon her odemede ilana uydurma bir dropship.stock blogu
           yaziyordu (had 0, sold 1) ve her seferinde "oversold" diye yanlis
           alarm logluyordu. Katalog geneline acilan urunlerde dusurulecek bir
           sayi yok -- olmayan bir sayiyi bir eksiltmek, sifiri veri sanmak. */
        if (empty($l['dropship']['stock'])) return;
        $have = (int)($all[$i]['dropship']['stock'][$colour][$size] ?? 0);
        $left = max(0, $have - $qty);
        $all[$i]['dropship']['stock'][$colour][$size] = $left;
        if ($have < $qty) {
            error_log("[VESTRA dropship] oversold {$productId} {$colour}/{$size}: had {$have}, sold {$qty}");
        }
        vestra_save_listings($all);
        return;
    }
}

/**
 * Post-payment side effects: customer confirmation email (if an address was
 * given) + admin/seller notify with the Stripe-collected shipping address,
 * so the order can actually be fulfilled. Idempotent — guarded by
 * 'fulfilled' so a webhook + confirm-page double-fire can't send duplicate
 * emails or double-decrement stock.
 */
function dropship_fulfill(array $rec): void {
    $ref = $rec['ref'] ?? '';
    if ($ref === '' || !empty($rec['fulfilled'])) return;
    dropship_update($ref, ['fulfilled' => true]); // claim first — avoids double-fire on races

    dropship_decrement_stock(
        (string)($rec['product_id'] ?? ''),
        (string)($rec['colour'] ?? ''),
        (string)($rec['size'] ?? ''),
        (int)($rec['qty'] ?? 1)
    );

    require_once __DIR__ . '/notify.php';

    $amount = number_format((float)($rec['amount'] ?? 0), 2);
    $addr = $rec['shipping_address'] ?? null;
    $addrLine = $addr
        ? trim(($addr['name'] ?? '') . "\n" . trim(($addr['line1'] ?? '') . ' ' . ($addr['line2'] ?? '')) . "\n" .
               trim(($addr['postal_code'] ?? '') . ' ' . ($addr['city'] ?? '')) . ', ' . ($addr['country'] ?? ''))
        : '(no shipping address on file — check the Stripe payment)';

    $itemLine = "{$rec['brand']} {$rec['name']} — {$rec['colour']} / {$rec['size']} × {$rec['qty']}";

    /* "Yakinda gonderiyoruz" bir taahhut degil, bir bosluk: siparisi veren ortak
       kendi musterisine bir tarih soylemek zorunda ve o tarihi bir yerden almasi
       gerekiyor. Bolgenin suresi zaten tabloda; onaya da yaziliyor. */
    $zoneDays = vestra_dropship_transit((string)($rec['ship_zone'] ?? 'EU'));
    $etaLine  = $zoneDays !== ''
        ? "Delivery: {$zoneDays} working days from dispatch, excluding time in customs.\n\n"
        : "We'll ship it out shortly.\n\n";

    if (!empty($rec['customer_email'])) {
        vestra_send_mail($rec['customer_email'], "VESTRA — order confirmed ({$ref})",
            "Hello " . ($rec['customer_name'] ?: 'there') . ",\n\n" .
            "Your order is confirmed and paid.\n\n" .
            "Order ref: {$ref}\n" .
            "Item: {$itemLine}\n" .
            "Amount paid: €{$amount}\n\n" .
            $etaLine .
            "— VESTRA · vestrasales.com");
    }

    $directCharge = !empty($rec['acct_id']);
    $payoutLine = $directCharge
        ? "Seller payout: €" . number_format((float)($rec['payout'] ?? 0), 2) . " — HELD on their Stripe balance (no release UI yet for dropship orders — release manually via Stripe Dashboard, or ask to have the admin button added).\n"
        : '';
    vestra_notify(
        "Dropship order paid — {$ref}",
        "A dropship API order has been paid.\n\n" .
        "Ref: {$ref}" . (!empty($rec['partner_reference']) ? " (partner ref: {$rec['partner_reference']})" : "") . "\n" .
        "Item: {$itemLine}\n" .
        "Amount: €{$amount}" . (!empty($rec['customer_email']) ? " · Customer: {$rec['customer_email']}" : "") . "\n" .
        "Shipping: " . (string)($rec['ship_zone'] ?? '?') . " · €" . number_format((float)($rec['ship_fee'] ?? 0), 2)
            . " (paid separately at checkout; duties at destination are NOT included)\n" .
        /* Adet bazli stok tutulmadigi icin bu satir uyari degil TALIMAT: mali
           gondermeden once saticiyla teyit et. Bunu yazmazsak "odenmis siparis
           = gonderilebilir mal" varsayilir ve teyit adimi atlanir. */
        "Stock: not tracked for this article — CONFIRM AVAILABILITY WITH THE SELLER BEFORE SHIPPING.\n" .
        "If it cannot be met, refund in full.\n" .
        "Ship to:\n{$addrLine}\n\n" .
        $payoutLine . "\n" .
        ($directCharge
            ? "Ship it and mark it sent."
            : "Ship it and mark it sent — VESTRA collected the payment directly, no seller escrow step.")
    );
}
