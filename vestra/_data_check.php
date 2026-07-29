<?php
/**
 * VESTRA — data diagnostic (TEMPORARY; delete after use).
 * Shows what is actually in the data files the admin panel reads, so we can see
 * why listings/orders may not appear. Gate: ?key=<admin password> or logged into /admin.
 */
error_reporting(E_ALL); ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__.'/inc/products.php';  // vestra_data_dir, vestra_listings, vestra_read_csv
require_once __DIR__.'/inc/notify.php';    // vestra_cfg
require_once __DIR__.'/inc/auth.php';      // auth_accounts
if (session_status() === PHP_SESSION_NONE) session_start();

$adminPass = (string) vestra_cfg('admin_pass', '');
$key = (string) ($_GET['key'] ?? '');
if (empty($_SESSION['vadmin']) && !($adminPass !== '' && hash_equals($adminPass, $key))) {
    http_response_code(403); echo "403 — pass ?key=<admin password> or log into /admin first.\n"; exit;
}

$line = str_repeat('─', 58);
$dir  = vestra_data_dir();
$finfo = function ($path) {
    if (!file_exists($path)) return 'MISSING';
    return (is_readable($path) ? 'readable' : '⚠ NOT READABLE').', '.number_format(filesize($path)).' bytes'
        .(is_writable($path) ? '' : ', ⚠ not writable');
};

echo "VESTRA DATA CHECK\n$line\n";
echo "data dir : $dir\n";
echo "dir readable/writable: ".(is_readable($dir)?'yes':'⚠ NO').' / '.(is_writable($dir)?'yes':'⚠ NO')."\n\n";

/* ── Listings ── */
echo "1) listings.json — ".$finfo($dir.'/listings.json')."\n";
$raw = @file_get_contents($dir.'/listings.json');
if ($raw !== false && $raw !== '') {
    $j = json_decode($raw, true);
    if (!is_array($j)) echo "   ⚠ JSON PARSE ERROR: ".json_last_error_msg()." — the file is corrupt, so NO listings load.\n";
}
$listings = vestra_listings();
echo "   total listings loaded: ".count($listings)."\n";
$byStatus = [];
foreach ($listings as $p) { $s = $p['status'] ?? '(no status)'; $byStatus[$s] = ($byStatus[$s] ?? 0) + 1; }
foreach ($byStatus as $s => $n) echo "     • status '$s': $n\n";
$pending = array_values(array_filter($listings, fn($p) => ($p['status'] ?? 'approved') === 'pending'));
echo "   PENDING (should appear in Approvals): ".count($pending)."\n";
foreach (array_slice($pending, 0, 12) as $p)
    echo "     - ".trim(($p['brand']??'').' '.($p['name']??''))."  [id=".($p['id']??'?').", seller_uid=".($p['seller_uid']??'—')."]\n";
echo "\n";

/* ── Orders ── */
echo "2) orders.csv — ".$finfo($dir.'/orders.csv')."\n";
$orders = vestra_read_csv('orders.csv');
echo "   total orders: ".count($orders)."\n";
foreach (array_slice($orders, 0, 6) as $o)
    echo "     - ".($o['ref']??'?')."  ·  ".($o['company']??$o['name']??'')."  ·  €".($o['total']??'?')."  ·  ".substr($o['timestamp']??'',0,10)."\n";
echo "\n";

/* ── Escrow (card) orders live in a separate store ── */
if (is_file(__DIR__.'/inc/escrow.php')) {
    require_once __DIR__.'/inc/escrow.php';
    if (function_exists('escrow_all')) {
        $esc = escrow_all();
        echo "3) escrow (card) orders: ".count($esc)."\n";
        foreach (array_slice(array_values($esc), 0, 5) as $e)
            echo "     - ".($e['ref']??'?')."  ·  status=".($e['status']??'?')."  ·  €".($e['total']??'?')."\n";
        echo "\n";
    }
}

/* ── Accounts ── */
echo "4) accounts.json — ".$finfo($dir.'/accounts.json')."\n";
$acc = function_exists('auth_accounts') ? auth_accounts() : [];
$sel = count(array_filter($acc, fn($a) => ($a['type']??'') === 'seller'));
$buy = count(array_filter($acc, fn($a) => ($a['type']??'') === 'buyer'));
echo "   accounts: ".count($acc)."  (sellers=$sel, buyers=$buy)\n\n";

echo "5) other: offers.csv=".count(vestra_read_csv('offers.csv'))
    ." · requests.csv=".count(vestra_read_csv('requests.csv'))
    ." · request_offers.csv=".count(vestra_read_csv('request_offers.csv'))."\n";

echo "\n$line\n";
echo "How to read this:\n";
echo " • If listings.json is MISSING/NOT READABLE → a permissions/path problem (that's the bug).\n";
echo " • If PENDING = 0 but sellers added products → their listings are not 'pending'\n";
echo "   (maybe added before approval was enabled, or auto-approved) → nothing to approve.\n";
echo " • If PENDING > 0 → they DO show in admin → Approvals; tell Claude and we look at the UI.\n";
echo " • If orders total = 0 → no orders have been placed/persisted yet.\n\n";
echo "Delete this file when done:  rm ".__FILE__."\n";
