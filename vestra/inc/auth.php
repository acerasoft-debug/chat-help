<?php
/**
 * VESTRA — File-based account auth (no database required).
 * Accounts stored in data/accounts.json (server-side, not web-accessible).
 */

define('VESTRA_ACCOUNTS', __DIR__.'/../data/accounts.json');

function auth_accounts(): array {
    if (!is_file(VESTRA_ACCOUNTS)) return [];
    return json_decode(file_get_contents(VESTRA_ACCOUNTS), true) ?: [];
}

function auth_save_accounts(array $list): void {
    file_put_contents(VESTRA_ACCOUNTS, json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function auth_find(string $email): ?array {
    foreach (auth_accounts() as $a)
        if (strtolower($a['email'] ?? '') === strtolower(trim($email))) return $a;
    return null;
}

function auth_user(): ?array {
    if (empty($_SESSION['uid'])) return null;
    foreach (auth_accounts() as $a)
        if (($a['id'] ?? '') === $_SESSION['uid']) return $a;
    return null;
}

function auth_set(array $acc): void {
    $_SESSION['uid']    = $acc['id'];
    $_SESSION['member'] = true;
    $_SESSION['utype']  = $acc['type'];
}

function auth_logout(): void {
    unset($_SESSION['uid'], $_SESSION['member'], $_SESSION['utype']);
}

function auth_register(array $d): array|string {
    if (auth_find($d['email'] ?? '')) return 'email_taken';
    if (strlen($d['password'] ?? '') < 8) return 'password_short';
    if (($d['password'] ?? '') !== ($d['password2'] ?? '')) return 'password_mismatch';

    $promo_code = strtoupper(trim($d['promo_code'] ?? ''));
    $promo_data = null;
    if ($promo_code !== '') {
        require_once __DIR__.'/promos.php';
        $pv = promo_validate($promo_code);
        if (is_string($pv)) return 'promo_'.$pv;
        $promo_data = $pv;
    }

    $list = auth_accounts();
    $acc  = [
        'id'            => bin2hex(random_bytes(8)),
        'email'         => strtolower(trim($d['email'])),
        'hash'          => password_hash($d['password'], PASSWORD_DEFAULT),
        'type'          => in_array($d['type'] ?? '', ['seller', 'buyer']) ? $d['type'] : 'buyer',
        'status'        => 'active',
        'name'          => trim($d['name']        ?? ''),
        'company'       => trim($d['company']     ?? ''),
        'vat_id'        => trim($d['vat_id']      ?? ''),
        'reg_number'    => trim($d['reg_number']  ?? ''),
        'country'       => trim($d['country']     ?? ''),
        'address'       => trim($d['address']     ?? ''),
        'phone'         => trim($d['phone']       ?? ''),
        'website'       => trim($d['website']     ?? ''),
        'kyb_status'    => $promo_data ? 'approved' : 'pending',
        'promo_code'    => $promo_code,
        'promo_benefit' => $promo_data['benefit'] ?? '',
        'promo_expiry'  => $promo_data['expiry']  ?? '',
        'created'       => date('c'),
    ];
    $list[] = $acc;
    auth_save_accounts($list);
    if ($promo_data) { promo_use($promo_code); }
    return $acc;
}

function auth_login(string $email, string $password): array|false {
    $acc = auth_find($email);
    if (!$acc) return false;
    if (!password_verify($password, $acc['hash'] ?? '')) return false;
    return $acc;
}

function auth_update(string $id, array $fields): void {
    $list   = auth_accounts();
    $locked = ['id', 'hash', 'email', 'created'];
    foreach ($list as &$a) {
        if ($a['id'] === $id) {
            foreach ($fields as $k => $v)
                if (!in_array($k, $locked)) $a[$k] = $v;
            break;
        }
    }
    auth_save_accounts($list);
}
