<?php
/**
 * VESTRA — customer discount vouchers (Gutschein) applied to an order total.
 *
 * Deliberately NOT part of inc/promos.php. That file is the SELLER invite-code system:
 * its benefits are instant_kyb / commission_free_3m / priority_listing, it is consumed at
 * registration, and it never touches an order. Overloading it would mean one code space
 * where "VESTRA-A1B2" might waive a seller's KYB or might take 5% off a buyer's invoice
 * depending on which form it was typed into. Two different things, two different stores.
 *
 * WHERE THE MONEY COMES FROM. A voucher reduces the goods subtotal, so the buyer pays less
 * and the seller's payout falls by the same amount. On bank-transfer orders VESTRA charges
 * 0% to both sides (VESTRA_FEE_SELLER/BUYER are 0.0) — there is no platform margin on the
 * transaction to absorb a discount from, so a voucher that left the payout untouched would
 * mean VESTRA paying out money it never collected. The discount is therefore recorded
 * explicitly on the order (voucher_code + discount) and shown to buyer, seller and admin,
 * so nobody discovers it only when the payout looks short.
 *
 * Codes are normally bound to one customer's e-mail and single-use: the welcome campaign
 * issues a distinct code per registered customer rather than one shared code, so a code
 * posted publicly cannot be spent by strangers, and redemption is traceable to an account.
 */

require_once __DIR__.'/products.php';   // vestra_read_csv() for the first-order check

define('VESTRA_VOUCHERS', __DIR__.'/../data/vouchers.json');

function voucher_all(): array {
    if (!is_readable(VESTRA_VOUCHERS)) return [];
    $d = json_decode((string)file_get_contents(VESTRA_VOUCHERS), true);
    return is_array($d) ? $d : [];
}

function voucher_save(array $list): void {
    $dir = dirname(VESTRA_VOUCHERS);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    file_put_contents(VESTRA_VOUCHERS, json_encode($list, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function voucher_norm(string $code): string {
    return strtoupper(trim($code));
}

function voucher_find(string $code): ?array {
    $all = voucher_all();
    return $all[voucher_norm($code)] ?? null;
}

/** How many orders this e-mail has already placed — drives the first_order_only rule. */
function voucher_customer_order_count(string $email): int {
    $email = strtolower(trim($email));
    if ($email === '') return 0;
    $n = 0;
    foreach (vestra_read_csv('orders.csv') as $row) {
        if (strtolower(trim((string)($row['email'] ?? ''))) === $email) $n++;
    }
    return $n;
}

/**
 * Validate a code for a specific buyer and basket value.
 * Returns the voucher array when usable, otherwise a short error key the caller maps to text.
 */
function voucher_validate(string $code, string $email, float $subtotal): array|string {
    $v = voucher_find($code);
    if (!$v)                                    return 'not_found';
    if (!($v['active'] ?? true))                return 'inactive';
    if (!empty($v['expiry']) && strtotime((string)$v['expiry']) < time()) return 'expired';

    $maxUses = (int)($v['max_uses'] ?? 1);
    if ($maxUses > 0 && (int)($v['used'] ?? 0) >= $maxUses) return 'used';

    /* Bound codes: the welcome mail sends a personal code, so it only works for the address
       it was issued to. Compared case-insensitively — buyers retype their address by hand. */
    $bound = strtolower(trim((string)($v['email'] ?? '')));
    if ($bound !== '' && $bound !== strtolower(trim($email))) return 'wrong_customer';

    if (!empty($v['first_order_only']) && voucher_customer_order_count($email) > 0) return 'not_first_order';

    $min = (float)($v['min_subtotal'] ?? 0);
    if ($min > 0 && $subtotal < $min) return 'min_subtotal';

    return $v;
}

/** Money off the goods subtotal, rounded to cents and never more than the subtotal itself. */
function voucher_discount(array $v, float $subtotal): float {
    $type = (string)($v['type'] ?? 'percent');
    $val  = (float)($v['value'] ?? 0);
    $d    = $type === 'fixed' ? $val : $subtotal * ($val / 100);
    $d    = round($d, 2);
    if ($d < 0) $d = 0.0;
    if ($d > $subtotal) $d = round($subtotal, 2);
    return $d;
}

/** Human label for a code ("5%" / "€25"), for the cart, the invoice and the e-mail. */
function voucher_label(array $v): string {
    $val = (float)($v['value'] ?? 0);
    return ((string)($v['type'] ?? 'percent') === 'fixed')
        ? eur($val)
        : rtrim(rtrim(number_format($val, 2, '.', ''), '0'), '.').'%';
}

/**
 * Mark a code as spent. Re-reads and re-checks the use count under the write so two orders
 * placed at the same second cannot both consume a single-use code.
 */
function voucher_redeem(string $code, string $ref, string $email, float $amount): bool {
    $all = voucher_all();
    $key = voucher_norm($code);
    if (!isset($all[$key])) return false;

    $maxUses = (int)($all[$key]['max_uses'] ?? 1);
    if ($maxUses > 0 && (int)($all[$key]['used'] ?? 0) >= $maxUses) return false;

    $all[$key]['used'] = (int)($all[$key]['used'] ?? 0) + 1;
    $all[$key]['used_by'] = array_values(array_merge((array)($all[$key]['used_by'] ?? []), [[
        'ref' => $ref, 'email' => strtolower(trim($email)), 'amount' => round($amount, 2), 'at' => date('c'),
    ]]));
    voucher_save($all);
    return true;
}

/** Create or overwrite a code. Returns the stored record. */
function voucher_create(array $d): array {
    $all  = voucher_all();
    $code = voucher_norm((string)($d['code'] ?? ''));
    if ($code === '') $code = voucher_generate((string)($d['prefix'] ?? 'VES'));
    $all[$code] = [
        'code'             => $code,
        'type'             => (string)($d['type'] ?? 'percent'),
        'value'            => (float)($d['value'] ?? 0),
        'email'            => strtolower(trim((string)($d['email'] ?? ''))),
        'first_order_only' => !empty($d['first_order_only']),
        'min_subtotal'     => (float)($d['min_subtotal'] ?? 0),
        'max_uses'         => (int)($d['max_uses'] ?? 1),
        'used'             => 0,
        'used_by'          => [],
        'expiry'           => trim((string)($d['expiry'] ?? '')),
        'active'           => true,
        'campaign'         => trim((string)($d['campaign'] ?? '')),
        'created'          => date('c'),
    ];
    voucher_save($all);
    return $all[$code];
}

/* Ambiguous characters are left out of the alphabet: these codes get read off a screen and
   retyped by hand, and O/0 and I/1/L cost a support mail every time they are confused. */
function voucher_generate(string $prefix = 'VES'): string {
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < 8; $i++) $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    return strtoupper($prefix).'-'.substr($out, 0, 4).'-'.substr($out, 4, 4);
}

/** Error key → buyer-facing sentence. */
function voucher_error_text(string $key): string {
    return match ($key) {
        'not_found'       => t('This voucher code was not found.'),
        'inactive'        => t('This voucher code is no longer active.'),
        'expired'         => t('This voucher code has expired.'),
        'used'            => t('This voucher code has already been used.'),
        'wrong_customer'  => t('This voucher code was issued to a different account.'),
        'not_first_order' => t('This voucher is valid on a first order only.'),
        'min_subtotal'    => t('Your order is below the minimum value for this voucher.'),
        default           => t('This voucher code cannot be used.'),
    };
}
