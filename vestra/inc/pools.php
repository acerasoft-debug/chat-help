<?php
/**
 * VESTRA — group-buy pool commitments WITH a paid deposit.
 *
 * The original pool (inc/products.php + group-join.php) took no money at all: a
 * buyer filled in a form, a row landed in data/groups.csv, and the mail told them
 * "if the pool fails you pay nothing". That is still supported — those rows keep
 * counting — but a pool may now require a deposit instead, and this file holds
 * that state.
 *
 * Money flow for a deposit pool:
 *   1. join      → Stripe Checkout for deposit_pct % of (qty × unlocked price).
 *                  The commitment is written as `pending` BEFORE the redirect and
 *                  only counts toward the target once Stripe says it is paid.
 *   2. target hit→ balance (the remaining %) is invoiced, due within
 *                  group_balance_days days.
 *   3. target missed → the deadline is extended ONCE (group_extend_days), and if
 *                  it is still missed the deposit is refunded in full.
 *
 * Step 3 is the reason deposits live in JSON and not in the append-only CSV: a
 * commitment's status changes several times after it is written (paid → balance_due
 * → settled, or → refunded), and a CSV row cannot be updated in place.
 *
 *   ref             GRP-xxxxxxxx
 *   pool_id         product id the pool belongs to
 *   buyer_*         company / name / email / country (+ buyer_id when logged in)
 *   qty             units committed
 *   unit_price      unlocked price at the time of joining (frozen — a later
 *                   group_price edit must never re-price a paid commitment)
 *   total           qty × unit_price
 *   deposit / balance          EUR split of `total`
 *   deposit_pct                the % actually charged, frozen like unit_price
 *   session_id / payment_intent
 *   status          pending → paid → balance_due → settled
 *                                 └→ refunded (pool failed)
 *   created / paid_at / refunded_at / balance_due_at / settled_at
 */

require_once __DIR__ . '/products.php';

function pools_file(): string { return vestra_data_dir() . '/pool_commits.json'; }

function pool_commits_all(): array {
    $f = pools_file();
    if (!is_readable($f)) return [];
    $j = json_decode((string) file_get_contents($f), true);
    return is_array($j) ? $j : [];
}

function pool_commit_get(string $ref): ?array {
    $all = pool_commits_all();
    return $all[$ref] ?? null;
}

function pool_commit_find_by_session(string $sessionId): ?array {
    if ($sessionId === '') return null;
    foreach (pool_commits_all() as $rec) {
        if (($rec['session_id'] ?? '') === $sessionId) return $rec;
    }
    return null;
}

function pool_commit_save(array $rec): void {
    $ref = $rec['ref'] ?? '';
    if ($ref === '') return;
    $all = pool_commits_all();
    $all[$ref] = $rec;
    $dir = dirname(pools_file());
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @file_put_contents(pools_file(), json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function pool_commit_update(string $ref, array $patch): ?array {
    $all = pool_commits_all();
    if (!isset($all[$ref])) return null;
    $all[$ref] = array_merge($all[$ref], $patch);
    @file_put_contents(pools_file(), json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $all[$ref];
}

/* Statuses whose quantity is real money already taken, and therefore counts
   toward the target. `pending` deliberately does not: an abandoned Stripe
   Checkout would otherwise inflate the pool and could push it over the line
   with quantities nobody ever paid for. */
function pool_counting_statuses(): array { return ['paid', 'balance_due', 'settled']; }

/** Deposit-paid commitments for one pool, newest first. */
function pool_commits_for(string $poolId): array {
    $out = [];
    foreach (pool_commits_all() as $rec) {
        if (($rec['pool_id'] ?? '') !== $poolId) continue;
        if (!in_array($rec['status'] ?? '', pool_counting_statuses(), true)) continue;
        $out[] = $rec;
    }
    usort($out, fn($a, $b) => strcmp($b['created'] ?? '', $a['created'] ?? ''));
    return $out;
}

/** Every commitment for a pool regardless of status — admin views and the sweeper. */
function pool_commits_all_for(string $poolId): array {
    $out = [];
    foreach (pool_commits_all() as $rec) {
        if (($rec['pool_id'] ?? '') === $poolId) $out[] = $rec;
    }
    usort($out, fn($a, $b) => strcmp($b['created'] ?? '', $a['created'] ?? ''));
    return $out;
}

/* ── Deposit maths ───────────────────────────────────────────────────────────
   Rounding is done on the DEPOSIT and the balance is whatever is left, so
   deposit + balance == total to the cent for every quantity. Splitting both
   independently would drift by a cent and the buyer would see a balance
   invoice that does not reconcile with what they paid. */

function pool_deposit_pct(array $p): float {
    $pct = (float) ($p['group_deposit_pct'] ?? 0);
    return max(0.0, min(100.0, $pct));
}
function pool_has_deposit(array $p): bool { return pool_deposit_pct($p) > 0; }

function pool_balance_days(array $p): int {
    return max(1, (int) ($p['group_balance_days'] ?? 7));
}

/** [total, deposit, balance] in EUR for one commitment. */
function pool_split(array $p, int $qty, ?float $unit = null, ?float $pct = null): array {
    $unit = $unit ?? (float) vestra_group_price($p);
    $pct  = $pct  ?? pool_deposit_pct($p);
    $total   = round($qty * $unit, 2);
    $deposit = round($total * $pct / 100, 2);
    $balance = round($total - $deposit, 2);
    return [$total, $deposit, $balance];
}
