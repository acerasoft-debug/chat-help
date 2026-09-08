<?php
/**
 * VESTRA — toptan erisim aboneligi (dropship) icin Stripe Checkout oturumu.
 * POST /stripe/dropship-plan
 *
 * Operator karari, 8 Eyl 2026: *"tiklandiginda aylik odeme funktionu da olsun
 * ... 199,90 eur olacak fiyati, eger bu fiyat odenirse toptan fiyatina satin
 * alinabilir"*. Abonelik tek adet dropship alisverisindeki %20 zammi kaldirir.
 *
 * SATICI UYELIGINDEN AYRI. Ayni Stripe musterisi iki abonelik tasiyabilir
 * (satici tier'i + bu plan), o yuzden `metadata.plan` her iki tarafa da
 * yaziliyor: webhook hangi aboneligin degistigini BUNDAN ayirt ediyor,
 * musteri kimliginden degil. Ayirt etmeseydi bir alicinin bu abonelikten
 * cikmasi, satici tarafindaki `membership_status`u ezip "ilanlariniz kapandi"
 * mektubunu tetikleyecekti.
 *
 * FIYAT KODDAN, Stripe panelinden degil: `price_data` ile satir ici. Onceden
 * kurulmus bir Price'a (PRICE_* env) baglamak, rakami sunucuda gorunmeyen
 * ikinci bir yere daha yazmak olurdu -- KURAL 6'nin escrow tavani dersi.
 */
require_once __DIR__ . '/../inc/i18n.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/stripe.php';
require_once __DIR__ . '/../inc/dropship.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$back = '/dropship';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . $back); exit; }

/* CSRF JETONU YOK, ve bu bilinclidir: sitenin ACIK formlarinda (sepet,
   dropship-checkout) jeton yok, `csrfField()` yalniz admin.php'de tanimli.
   `function_exists()` ile sarilmis bir cagri burada hicbir sey dogrulamaz --
   yalnizca dogruluyormus gibi gorunurdu, ki bu depoda kayitli en pahali hata
   turu. Risk de sinirli: bu uc yalnizca Stripe oturumu ACIYOR; tahsilat icin
   kullanicinin Stripe sayfasinda karti girip onaylamasi gerekiyor. */
$user = auth_user();
if (!$user) { header('Location: /login?back=' . urlencode($back)); exit; }

/* Dropship dugmesi zaten onayli ticari hesabin arkasinda (dropship.php
   basligi). Abonelik de ayni kapiyi kullaniyor: onaysiz bir hesaptan para
   almak, kullanamayacagi bir ayricaligi satmak olurdu. */
if (!auth_prices_unlocked($user)) { header('Location: ' . $back . '?plan=gate'); exit; }

/* Zaten aboneyse IKINCI abonelik acilmaz -- iki kez tahsilat demek olurdu.
   Yonetim (iptal, kart degisimi) Stripe'in kendi portalinda. */
if (vestra_dropship_plan_active($user)) { header('Location: ' . $back . '?plan=already'); exit; }

if (!stripe_configured()) { header('Location: ' . $back . '?plan=notready'); exit; }

try {
    $customerId = stripe_ensure_customer($user);
    $meta = ['plan' => 'dropship_wholesale', 'account_id' => $user['id']];

    $session = stripe_api('POST', '/v1/checkout/sessions', [
        'mode'       => 'subscription',
        'customer'   => $customerId,
        'line_items' => [[
            'price_data' => [
                'currency'     => VESTRA_DROPSHIP_PLAN_CURRENCY,
                'unit_amount'  => (int) round(VESTRA_DROPSHIP_PLAN_PRICE * 100),
                'recurring'    => ['interval' => VESTRA_DROPSHIP_PLAN_INTERVAL],
                'product_data' => [
                    'name'        => 'VESTRA Wholesale Access',
                    'description' => 'Single-piece dropshipping at wholesale price, billed monthly. Cancel any time.',
                ],
            ],
            'quantity' => 1,
        ]],
        'subscription_data'    => ['metadata' => $meta],
        'payment_method_types' => stripe_sepa_enabled() ? ['sepa_debit', 'card'] : ['card'],
        'metadata'             => $meta,
        'success_url' => 'https://vestrasales.com/dropship?plan=ok',
        'cancel_url'  => 'https://vestrasales.com/dropship?plan=cancel',
    ]);

    header('Location: ' . $session->url);
    exit;

} catch (\Throwable $e) {
    error_log('[VESTRA dropship-plan] checkout error: ' . $e->getMessage());
    header('Location: ' . $back . '?plan=error');
    exit;
}
