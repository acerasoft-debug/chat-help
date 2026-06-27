<?php
/** VESTRA — promo code / invite code system for seller marketing. */

define('VESTRA_PROMOS', __DIR__.'/../data/promos.json');

function promo_all(): array {
    if (!is_readable(VESTRA_PROMOS)) return [];
    return json_decode((string)file_get_contents(VESTRA_PROMOS), true) ?: [];
}

function promo_save(array $list): void {
    file_put_contents(VESTRA_PROMOS, json_encode($list, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function promo_find(string $code): ?array {
    return promo_all()[strtoupper(trim($code))] ?? null;
}

function promo_validate(string $code): array|string {
    $p = promo_find($code);
    if (!$p) return 'not_found';
    if (!($p['active'] ?? true)) return 'inactive';
    if (!empty($p['expiry']) && strtotime($p['expiry']) < time()) return 'expired';
    if (!empty($p['max_uses']) && ($p['used'] ?? 0) >= (int)$p['max_uses']) return 'exhausted';
    return $p;
}

function promo_use(string $code): void {
    $all = promo_all();
    $key = strtoupper(trim($code));
    if (isset($all[$key])) {
        $all[$key]['used'] = ($all[$key]['used'] ?? 0) + 1;
        promo_save($all);
    }
}

function promo_create(array $d): void {
    $all  = promo_all();
    $code = $d['code'] !== '' ? strtoupper(trim($d['code'])) : promo_generate();
    $all[$code] = [
        'code'     => $code,
        'desc'     => trim($d['desc'] ?? ''),
        'benefit'  => $d['benefit'] ?? 'instant_kyb',
        'expiry'   => $d['expiry'] ?? '',
        'max_uses' => (int)($d['max_uses'] ?? 0),
        'used'     => 0,
        'active'   => true,
        'created'  => date('c'),
    ];
    promo_save($all);
}

function promo_generate(): string {
    return 'VESTRA-'.strtoupper(bin2hex(random_bytes(3)));
}

function promo_benefit_label(string $b): string {
    return match($b) {
        'instant_kyb'         => 'Instant KYB approval',
        'commission_free_3m'  => '0 % commission — 3 months',
        'commission_free_6m'  => '0 % commission — 6 months',
        'reduced_commission'  => '3.5 % commission (half rate) — 6 months',
        'priority_listing'    => 'Priority placement in catalog',
        default               => $b,
    };
}
