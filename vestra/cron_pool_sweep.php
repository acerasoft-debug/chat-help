<?php
/**
 * VESTRA — group-buy pool lifecycle sweeper (cron / CLI).
 *
 * Runs the three things that must happen to a deposit pool WITHOUT anyone
 * clicking anything, because each of them is a promise already made to a buyer
 * who has paid real money:
 *
 *   1. target reached   → invoice the balance, due in group_balance_days days
 *   2. deadline missed  → extend the pool ONCE by group_extend_days
 *   3. missed again     → refund every deposit in full
 *
 * (3) is the one that must never be skipped: the deposit page and the
 * confirmation mail both promise a full refund if the pool falls short, so a
 * silent failure here is a broken promise, not a missed cron. Refunds are
 * therefore per-commitment and idempotent — a partial run is safe to repeat, and
 * an individual Stripe error leaves that one commitment marked `refund_failed`
 * for a human instead of aborting the sweep for everyone else.
 *
 * Usage:  php cron_pool_sweep.php [--dry-run]
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/inc/products.php';
require_once __DIR__ . '/inc/pools.php';
require_once __DIR__ . '/inc/stripe.php';
require_once __DIR__ . '/inc/notify.php';

$DRY = in_array('--dry-run', $argv ?? [], true);
$now = time();
function sweep_log(string $s): void { echo $s . "\n"; }
sweep_log($DRY ? '— DRY RUN, hicbir sey degismeyecek —' : '— canli calisma —');

/** Patch fields onto a listing and persist. */
function pool_patch_listing(string $id, array $patch): void {
    $all = vestra_listings();
    foreach ($all as &$l) {
        if (($l['id'] ?? '') === $id) { $l = array_merge($l, $patch); break; }
    }
    unset($l);
    vestra_save_listings($all);
}

$pools = array_values(array_filter(vestra_group_pools(), fn($p) => pool_has_deposit($p)));
sweep_log('kaporali havuz sayisi: ' . count($pools));

foreach ($pools as $p) {
    $id    = $p['id'];
    $label = trim(($p['brand'] ?? '') . ' ' . ($p['name'] ?? ''));
    sweep_log("\n== {$label} ({$id}) — {$p['_committed']}/{$p['_target']} {$p['unit']} ({$p['_pct']}%), durum={$p['_status']}");

    $commits  = pool_commits_for($id);           // paid / balance_due / settled
    $deadline = strtotime($p['_deadline']);

    /* ── 1. Target reached → invoice balances once ────────────────────────── */
    if ($p['_status'] === 'funded') {
        $due     = date('c', $now + pool_balance_days($p) * 86400);
        $dueHuman = date('d.m.Y', strtotime($due));
        $n = 0;
        foreach ($commits as $c) {
            if (($c['status'] ?? '') !== 'paid') continue;   // already invoiced or settled
            $n++;
            if ($DRY) continue;
            pool_commit_update($c['ref'], ['status' => 'balance_due', 'balance_due_at' => $due]);
            $msg = "Hello {$c['name']},\n\n"
                 . "Good news — the {$label} group buy reached its target of {$p['_target']} {$p['unit']}. "
                 . "Your price of €" . number_format((float) $c['unit_price'], 2) . " per {$c['unit']} is locked in.\n\n"
                 . "Reference: {$c['ref']}\n"
                 . "Quantity: {$c['qty']} {$c['unit']}\n"
                 . "Order value: €" . number_format((float) $c['total'], 2) . "\n"
                 . "Deposit already paid: €" . number_format((float) $c['deposit'], 2) . "\n"
                 . "Balance now due: €" . number_format((float) $c['balance'], 2) . "\n"
                 . "Payment due by: {$dueHuman}\n\n"
                 . "We will send the payment link and the invoice separately. Goods are dispatched once the balance is settled.\n\n"
                 . "— VESTRA · acerasoft LLC";
            vestra_send_mail($c['email'], "VESTRA — balance due for {$label} ({$c['ref']})", $msg);
        }
        if ($n) {
            sweep_log("  hedef tuttu → {$n} katilimciya bakiye faturasi (son odeme {$dueHuman})");
            if (!$DRY) vestra_notify("Pool funded — {$label}", "{$n} commitments moved to balance_due, due {$dueHuman}.\nPool {$p['_committed']}/{$p['_target']} {$p['unit']}.");
        } else {
            sweep_log('  hedef tuttu, bakiye faturalari zaten gonderilmis');
        }
        continue;
    }

    /* ── still open, deadline not reached → nothing to do ─────────────────── */
    if ($deadline > $now) {
        sweep_log('  hala acik, son tarihe zaman var');
        continue;
    }

    /* ── 2. Deadline missed, never extended → extend once ─────────────────── */
    if (empty($p['group_extended_to'])) {
        $extraDays = max(1, (int) ($p['group_extend_days'] ?? 7));
        $newDl     = date('c', $deadline + $extraDays * 86400);
        sweep_log("  hedef tutmadi → {$extraDays} gun uzatiliyor (yeni son tarih " . date('d.m.Y', strtotime($newDl)) . ')');
        if ($DRY) continue;
        pool_patch_listing($id, ['group_extended_to' => $newDl, 'group_extended_at' => date('c')]);
        foreach ($commits as $c) {
            $msg = "Hello {$c['name']},\n\n"
                 . "The {$label} group buy has not yet reached its target of {$p['_target']} {$p['unit']} "
                 . "(currently {$p['_committed']}). We have extended it by {$extraDays} days, until "
                 . date('d.m.Y', strtotime($newDl)) . ".\n\n"
                 . "Your commitment of {$c['qty']} {$c['unit']} and your deposit of €"
                 . number_format((float) $c['deposit'], 2) . " stand unchanged.\n"
                 . "If the pool still falls short by the new date, your deposit is refunded in full.\n\n"
                 . "Reference: {$c['ref']}\n\n— VESTRA · acerasoft LLC";
            vestra_send_mail($c['email'], "VESTRA — {$label} group buy extended", $msg);
        }
        vestra_notify("Pool extended — {$label}", "Target missed at {$p['_committed']}/{$p['_target']}. Extended {$extraDays} days to " . date('d.m.Y', strtotime($newDl)) . ".");
        continue;
    }

    /* ── 3. Missed after the extension → refund every deposit ─────────────── */
    sweep_log('  uzatmadan sonra da tutmadi → kaporalar iade ediliyor');
    $ok = 0; $fail = 0;
    foreach ($commits as $c) {
        if (($c['status'] ?? '') === 'refunded') continue;
        $pi = (string) ($c['payment_intent'] ?? '');
        if ($pi === '') { sweep_log("    ! {$c['ref']}: payment_intent yok, elle bakilmali"); $fail++; continue; }
        if ($DRY) { sweep_log("    (kuru) iade {$c['ref']} €" . number_format((float) $c['deposit'], 2)); $ok++; continue; }
        try {
            /* No amount → Stripe refunds the full charge, which is exactly what
               was promised. Never pass a computed amount here: a rounding slip
               would short-change the buyer on money we said we would return. */
            $r = stripe_api('POST', '/v1/refunds', ['payment_intent' => $pi, 'metadata' => ['kind' => 'pool_refund', 'pool_ref' => $c['ref']]]);
            pool_commit_update($c['ref'], ['status' => 'refunded', 'refunded_at' => date('c'), 'refund_id' => $r->id ?? '']);
            $msg = "Hello {$c['name']},\n\n"
                 . "The {$label} group buy did not reach its target of {$p['_target']} {$p['unit']} "
                 . "even after the extension, so the pool is closed and nothing will be produced.\n\n"
                 . "Your deposit of €" . number_format((float) $c['deposit'], 2) . " has been refunded in full "
                 . "to the card you paid with. Depending on your bank it appears within 5–10 working days. "
                 . "You owe nothing further.\n\n"
                 . "Reference: {$c['ref']}\n\n"
                 . "Thank you for taking part — we will let you know when this article opens again.\n\n"
                 . "— VESTRA · acerasoft LLC";
            vestra_send_mail($c['email'], "VESTRA — deposit refunded ({$label})", $msg);
            $ok++;
        } catch (\Throwable $e) {
            /* Flagged, not fatal: one card failing must not stop the other refunds. */
            pool_commit_update($c['ref'], ['status' => 'refund_failed', 'refund_error' => $e->getMessage()]);
            error_log('[VESTRA Pool] refund failed ' . $c['ref'] . ': ' . $e->getMessage());
            sweep_log("    ! {$c['ref']} iade HATASI: " . $e->getMessage());
            $fail++;
        }
    }
    sweep_log("  iade: {$ok} basarili, {$fail} hatali");
    if (!$DRY && ($ok || $fail)) {
        pool_patch_listing($id, ['group' => false, 'group_closed_at' => date('c')]);
        vestra_notify("Pool failed & refunded — {$label}", "Refunded {$ok}, failed {$fail}. Pool closed at {$p['_committed']}/{$p['_target']} {$p['unit']}.");
    }
}

sweep_log("\nbitti.");
