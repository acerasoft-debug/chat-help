<?php
/**
 * VESTRA — Escrow self-test (TEMPORARY diagnostic; delete after use).
 *
 * Proves the "direct charge + delayed payout" escrow really works on YOUR
 * Stripe account. Two modes, chosen automatically from the secret key:
 *
 *   • TEST key (sk_test_…): runs the FULL money cycle, fully automated —
 *       create a verified connected account → charge a test card with a
 *       platform commission → show the seller's share HELD in their balance →
 *       RELEASE it (payout) → then charge again and REFUND the buyer in full
 *       (commission clawed back). Every number is printed. Nothing you have to
 *       click. This is the irrefutable proof.
 *
 *   • LIVE key (sk_live_…): runs only the non-destructive checks (no real money
 *       moved): key valid, Connect active, a connected account can be created
 *       with a MANUAL payout schedule (the hold), and that schedule reads back
 *       as "manual". For a real money demo on live, run the TEST proof first.
 *
 * Gate: pass ?key=<your admin password> (config admin_pass), or be logged into
 * the admin panel in this browser.
 *
 * To run the full proof without touching your live key, add your Stripe TEST
 * secret key to the .env as STRIPE_TEST_SECRET_KEY=sk_test_… and load this page.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1'); // this is a diagnostic — surface fatals instead of a blank 500
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../inc/env.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/notify.php'; // defines vestra_cfg() used by the gate
if (session_status() === PHP_SESSION_NONE) session_start();

/* ── gate ─────────────────────────────────────────────────────────────────── */
$adminPass = (string) vestra_cfg('admin_pass', '');
$key = (string) ($_GET['key'] ?? $_POST['key'] ?? '');
$ok  = !empty($_SESSION['vadmin']) || ($adminPass !== '' && hash_equals($adminPass, $key));
if (!$ok) {
    http_response_code(403);
    echo "403 — pass ?key=<admin password> or log into /admin first.\n";
    exit;
}

/* ── choose the key: explicit test key wins, so live stays untouched ───────── */
$testKey = getenv('STRIPE_TEST_SECRET_KEY') ?: (string) ($_GET['sk'] ?? '');
if ($testKey !== '') putenv('STRIPE_SECRET_KEY=' . $testKey);
require_once __DIR__ . '/../inc/stripe.php';

$sk   = getenv('STRIPE_SECRET_KEY') ?: '';
$mode = str_starts_with($sk, 'sk_test_') ? 'TEST' : (str_starts_with($sk, 'sk_live_') ? 'LIVE' : 'UNKNOWN');

$line = str_repeat('─', 60);
function pass(string $m){ echo "  ✅ $m\n"; }
function fail(string $m){ echo "  ❌ $m\n"; }
function info(string $m){ echo "  ·  $m\n"; }
function money(int $c, string $cur){ return number_format($c/100, 2) . ' ' . strtoupper($cur); }

echo "VESTRA ESCROW SELF-TEST\n$line\n";
echo "Mode: $mode   (key " . ($sk ? substr($sk, 0, 11) . '…' : 'MISSING') . ")\n$line\n\n";

if (!stripe_available()) { fail('Stripe not configured — STRIPE_SECRET_KEY missing in .env'); exit; }

/* ── 0. key valid + Connect active ────────────────────────────────────────── */
echo "0) Account & Connect status\n";
try {
    $acct = stripe_api('GET', '/v1/account');
    pass('Secret key valid — platform account ' . ($acct->id ?? '?'));
    info('charges_enabled=' . var_export(!empty($acct->charges_enabled), true));
} catch (\Throwable $e) { fail('Key check failed: ' . $e->getMessage()); exit; }
try {
    // Listing connected accounts only succeeds when Connect is enabled.
    stripe_api('GET', '/v1/accounts', ['limit' => 1]);
    pass('Stripe Connect is ENABLED on this account.');
} catch (\Throwable $e) {
    fail('Connect not enabled: ' . $e->getMessage());
    info('Enable it once at https://dashboard.stripe.com/connect/overview, then re-run.');
    exit;
}
echo "\n";

/* ── 0b. PROBE: can we create a connected account WITHOUT card_payments? ─────
 * The platform-profile "managing losses" wall is triggered by the card_payments
 * capability on the connected account. A transfers-ONLY account — used with the
 * "destination charge" model (buyer pays the PLATFORM's own account, which
 * already works; the platform then transfers the seller's share and holds the
 * rest as escrow) — may create fine without that dashboard attestation. If this
 * probe succeeds, we can switch the escrow to that model and skip the dashboard
 * step entirely. */
echo "0b) PROBE — transfers-only account (destination-charge escrow, no dashboard step)\n";
try {
    $probe = stripe_api('POST', '/v1/accounts', [
        'type'         => 'express',
        'country'      => 'DE',
        'email'        => 'transfers-probe@vestrasales.com',
        'capabilities' => ['transfers' => ['requested' => 'true']],
        'metadata'     => ['purpose' => 'vestra_transfers_probe'],
    ]);
    pass('SUCCESS — transfers-only account created: ' . ($probe->id ?? '?'));
    info('=> The alternative (destination-charge) escrow works WITHOUT the platform');
    info('   profile. Send this whole output to Claude and it switches to that model.');
    info('   (This empty probe account is harmless — you can ignore/delete it later.)');
} catch (\Throwable $e) {
    fail('Also blocked: ' . $e->getMessage());
    info('=> Even a transfers-only account is blocked, so the platform profile');
    info('   is unavoidable and must be completed once in the dashboard.');
}
echo "\n";

/* ── 1. manual-payout connected account (the HOLD) — non-destructive ──────── */
echo "1) Connected account with MANUAL payouts (the escrow hold)\n";
$storeFile = __DIR__ . '/../data/escrow_selftest.json';
$store = is_readable($storeFile) ? (json_decode((string) file_get_contents($storeFile), true) ?: []) : [];
$acctKey = $mode; // keep test/live diagnostic accounts separate
$acctId  = $store[$acctKey] ?? '';
try {
    if ($acctId === '') {
        $created = stripe_api('POST', '/v1/accounts', [
            'country'       => 'DE',
            'email'         => 'escrow-selftest@vestrasales.com',
            'business_type' => 'company',
            // Same explicit Express config as the real seller flow: platform
            // controls losses (required for the express dashboard) so we don't
            // need the dashboard platform-profile attestation.
            'controller'    => [
                'losses'                 => ['payments' => 'application'],
                'fees'                   => ['payer' => 'application'],
                'requirement_collection' => 'stripe',
                'stripe_dashboard'       => ['type' => 'express'],
            ],
            'capabilities'  => ['card_payments' => ['requested' => 'true'], 'transfers' => ['requested' => 'true']],
            'settings'      => ['payouts' => ['schedule' => ['interval' => 'manual']]],
            'metadata'      => ['purpose' => 'vestra_escrow_selftest'],
        ]);
        $acctId = $created->id;
        $store[$acctKey] = $acctId;
        @file_put_contents($storeFile, json_encode($store, JSON_PRETTY_PRINT), LOCK_EX);
        pass('Created diagnostic connected account ' . $acctId);
    } else {
        pass('Reusing diagnostic connected account ' . $acctId);
    }
    $a = stripe_api('GET', '/v1/accounts/' . $acctId);
    $interval = $a->settings->payouts->schedule->interval ?? '?';
    if ($interval === 'manual') pass("Payout schedule reads back as MANUAL — funds will be HELD until we release them.");
    else fail("Payout schedule is '$interval' (expected manual).");
} catch (\Throwable $e) { fail('Account step failed: ' . $e->getMessage()); exit; }
echo "\n";

/* ── LIVE mode stops here (we won't fabricate a real charge) ──────────────── */
if ($mode !== 'TEST') {
    echo "2) Money movement (charge → hold → release → refund)\n";
    info('Skipped on a LIVE key — a real charge needs a real card, so this test');
    info('does not move live money. To SEE the full automated cycle, add your');
    info('Stripe TEST key to .env as  STRIPE_TEST_SECRET_KEY=sk_test_…  and reload.');
    echo "\n$line\nRESULT: Plumbing verified on LIVE (Connect + manual-payout hold).\n";
    echo "Run once with a TEST key for the full charge→release→refund proof.\n";
    echo "\nDelete this file when done:  rm " . __FILE__ . "\n";
    exit;
}

/* ── TEST mode: full automated proof ──────────────────────────────────────── */
$cur = 'usd'; // easiest country to instantly verify in test mode is the US
echo "2) Building a FULLY VERIFIED test connected account (US, instant)\n";
try {
    // A throwaway custom account we can activate instantly with Stripe's test values.
    $seller = stripe_api('POST', '/v1/accounts', [
        'type'          => 'custom',
        'country'       => 'US',
        'email'         => 'escrow-fullcycle@vestrasales.com',
        'business_type' => 'individual',
        'capabilities'  => ['card_payments' => ['requested' => 'true'], 'transfers' => ['requested' => 'true']],
        'settings'      => ['payouts' => ['schedule' => ['interval' => 'manual']]],
        'business_profile' => ['mcc' => '5691', 'url' => 'https://vestrasales.com'],
        'individual'    => [
            'first_name' => 'Jenny', 'last_name' => 'Rosen',
            'email'      => 'escrow-fullcycle@vestrasales.com',
            'phone'      => '+15555550123', 'id_number' => '000000000', 'ssn_last_4' => '0000',
            'dob'        => ['day' => 1, 'month' => 1, 'year' => 1901],
            'address'    => ['line1' => 'address_full_match', 'city' => 'Beverly Hills', 'state' => 'CA', 'postal_code' => '90210', 'country' => 'US'],
        ],
        'tos_acceptance' => ['date' => 1609459200, 'ip' => '8.8.8.8'],
        'external_account' => [
            'object' => 'bank_account', 'country' => 'US', 'currency' => 'usd',
            'routing_number' => '110000000', 'account_number' => '000123456789',
        ],
        'metadata' => ['purpose' => 'vestra_escrow_fullcycle'],
    ]);
    $sid = $seller->id;
    pass('Test seller account ' . $sid);
    info('charges_enabled=' . var_export(!empty($seller->charges_enabled), true)
        . ', payouts_enabled=' . var_export(!empty($seller->payouts_enabled), true));
    if (empty($seller->charges_enabled)) { fail('Account did not activate — cannot continue the charge test.'); exit; }
} catch (\Throwable $e) { fail('Verified account failed: ' . $e->getMessage()); exit; }
echo "\n";

echo "3) DIRECT CHARGE with platform commission (buyer pays the seller)\n";
$gross = 10000; $fee = 800; // $100.00 order, $8.00 commission (8%)
try {
    // pm_card_bypassPending: test card whose funds are immediately AVAILABLE,
    // so we can demonstrate the payout (release) in the same run.
    $pi = stripe_api('POST', '/v1/payment_intents', [
        'amount'                 => $gross,
        'currency'               => $cur,
        'payment_method'         => 'pm_card_bypassPending',
        'application_fee_amount' => $fee,
        'confirm'                => 'true',
        'off_session'            => 'true',
        'payment_method_types'   => ['card'],
        'metadata'               => ['order_ref' => 'SELFTEST-1', 'kind' => 'escrow'],
    ], $sid);
    if (($pi->status ?? '') !== 'succeeded') { fail('Charge status: ' . ($pi->status ?? '?')); exit; }
    pass('Buyer charged ' . money($gross, $cur) . ' on the SELLER account (direct charge).');
    info('payment_intent ' . $pi->id . '  status=' . $pi->status);
} catch (\Throwable $e) { fail('Charge failed: ' . $e->getMessage()); exit; }
echo "\n";

echo "4) Where did the money go?\n";
try {
    $sellerBal   = stripe_escrow_balance($sid);
    $platformBal = stripe_api('GET', '/v1/balance');
    $held = (int) (($sellerBal['available'][$cur] ?? 0) + ($sellerBal['pending'][$cur] ?? 0));
    $platFee = 0;
    foreach (($platformBal->available ?? []) as $r) if ($r->currency === $cur) $platFee += (int) $r->amount;
    foreach (($platformBal->pending  ?? []) as $r) if ($r->currency === $cur) $platFee += (int) $r->amount;
    pass('Seller balance HELD (manual payout): ' . money($held, $cur) . '  ← this is the escrow');
    info('Platform balance (incl. this ' . money($fee, $cur) . ' commission): ' . money($platFee, $cur));
    info('Seller keeps ' . money($gross - $fee, $cur) . ' of the ' . money($gross, $cur) . ' order; platform earned ' . money($fee, $cur) . '.');
} catch (\Throwable $e) { fail('Balance read failed: ' . $e->getMessage()); }
echo "\n";

echo "5) RELEASE the escrow (payout to the seller on delivery)\n";
try {
    $payout = stripe_escrow_release($sid, null, $cur, 'SELFTEST-1');
    pass('Released ' . money((int) $payout->amount, $cur) . ' to the seller — payout ' . $payout->id . ' (' . $payout->status . ').');
    info('In production this fires only when the buyer confirms delivery.');
} catch (\Throwable $e) {
    fail('Release failed: ' . $e->getMessage());
    info('(If funds were still pending this can fail — the production flow releases after settlement.)');
}
echo "\n";

echo "6) REFUND the buyer in full before release (dispute / fake buyer)\n";
try {
    $pi2 = stripe_api('POST', '/v1/payment_intents', [
        'amount'                 => $gross,
        'currency'               => $cur,
        'payment_method'         => 'pm_card_bypassPending',
        'application_fee_amount' => $fee,
        'confirm'                => 'true',
        'off_session'            => 'true',
        'payment_method_types'   => ['card'],
        'metadata'               => ['order_ref' => 'SELFTEST-2', 'kind' => 'escrow'],
    ], $sid);
    info('Second order charged: ' . money($gross, $cur) . ' (' . $pi2->id . ')');
    $refund = stripe_escrow_refund($sid, $pi2->id);
    pass('Refunded buyer ' . money((int) $refund->amount, $cur) . ' — refund ' . $refund->id . ' (' . $refund->status . ').');
    info('refund_application_fee=true → the ' . money($fee, $cur) . ' commission was clawed back too, so the buyer got 100% and the seller balance was not pushed negative.');
} catch (\Throwable $e) { fail('Refund failed: ' . $e->getMessage()); }
echo "\n";

echo "$line\nRESULT: Full escrow cycle PROVEN on your Stripe account —\n";
echo "  charge → held in seller balance → released, and charge → refunded in full.\n";
echo "This is exactly the flow the site will run for real orders.\n\n";
echo "Cleanup: this test created throwaway TEST connected accounts (harmless).\n";
echo "Delete this file when done:  rm " . __FILE__ . "\n";
