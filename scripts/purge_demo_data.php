<?php
/**
 * VESTRA — live-data audit & purge: demo/test seller accounts and their listings.
 *
 * Run on the server from the site root (public_html):
 *   php scripts/purge_demo_data.php
 *       → READ-ONLY: lists every account and every seller listing with ids.
 *   php scripts/purge_demo_data.php --delete-account=email@x.com [--delete-account=...]
 *       → deletes those accounts AND all listings they own (repeatable flag; id or email).
 *   php scripts/purge_demo_data.php --delete-listing=LISTING_ID [--delete-listing=...]
 *       → deletes individual listings only.
 *
 * Orders/invoices are historical records and are never touched. Everything else
 * is explicit — nothing is guessed or auto-deleted.
 */
$root = dirname(__DIR__);
foreach ([$root.'/vestra/inc/products.php', $root.'/inc/products.php'] as $inc) {
    if (is_file($inc)) { require $inc; break; }
}
foreach ([$root.'/vestra/inc/auth.php', $root.'/inc/auth.php'] as $inc) {
    if (is_file($inc)) { require_once $inc; break; }
}
if (!function_exists('auth_accounts') || !function_exists('vestra_listings')) {
    fwrite(STDERR, "inc/ not found — run from the site root (public_html).\n"); exit(1);
}

$delAccounts = []; $delListings = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--delete-account=(.+)$/', $arg, $m)) $delAccounts[] = strtolower(trim($m[1]));
    if (preg_match('/^--delete-listing=(.+)$/', $arg, $m)) $delListings[] = trim($m[1]);
}

$accounts = auth_accounts();
$listings = vestra_listings();
$byOwner  = [];
foreach ($listings as $l) $byOwner[$l['seller_uid'] ?? ''][] = $l;

/* ── deletion mode ─────────────────────────────────────────────────────── */
if ($delAccounts || $delListings) {
    $keptA = []; $removedA = 0; $removedL = 0; $removedIds = [];
    foreach ($accounts as $a) {
        $hit = in_array(strtolower($a['email'] ?? ''), $delAccounts, true)
            || in_array(strtolower($a['id'] ?? ''), $delAccounts, true);
        if ($hit) {
            $removedA++;
            foreach ($byOwner[$a['id'] ?? ''] ?? [] as $l) $removedIds[$l['id'] ?? ''] = true;
            printf("✗ hesap silindi: %s (%s, %s) + %d ilanı\n",
                $a['email'] ?? '?', $a['type'] ?? '?', $a['company'] ?? '—',
                count($byOwner[$a['id'] ?? ''] ?? []));
        } else {
            $keptA[] = $a;
        }
    }
    $keptL = [];
    foreach ($listings as $l) {
        $id = $l['id'] ?? '';
        if (isset($removedIds[$id]) || in_array($id, $delListings, true)) {
            $removedL++;
            if (!isset($removedIds[$id])) printf("✗ ilan silindi: %s — %s %s\n", $id, $l['brand'] ?? '', $l['name'] ?? '');
            continue;
        }
        $keptL[] = $l;
    }
    if ($removedA) auth_save_accounts($keptA);
    if ($removedL) vestra_save_listings($keptL);
    printf("\nSonuç: %d hesap + %d ilan silindi · kalan: %d hesap, %d ilan\n",
        $removedA, $removedL, count($keptA), count($keptL));
    if (!$removedA && !$removedL) echo "(eşleşme yok — email/id'yi kontrol et)\n";
    exit(0);
}

/* ── read-only audit ───────────────────────────────────────────────────── */
echo "=== HESAPLAR (".count($accounts).") ===\n";
foreach ($accounts as $a) {
    printf("%-7s %-32s %-24s durum=%-12s ilan=%-3d kayıt=%s\n",
        $a['type'] ?? '?', $a['email'] ?? '?', mb_substr($a['company'] ?? '—', 0, 24),
        $a['status'] ?? '?', count($byOwner[$a['id'] ?? ''] ?? []),
        substr($a['created'] ?? '', 0, 10));
}
echo "\n=== SATICI İLANLARI (listings.json — ".count($listings).") ===\n";
foreach ($listings as $l) {
    $owner = '—';
    foreach ($accounts as $a) if (($a['id'] ?? '') === ($l['seller_uid'] ?? '')) { $owner = $a['email'] ?? $a['company'] ?? '?'; break; }
    printf("%-14s %-12s %-34s durum=%-10s sahip=%s\n",
        $l['id'] ?? '?', mb_substr($l['brand'] ?? '?', 0, 12), mb_substr($l['name'] ?? '?', 0, 34),
        $l['status'] ?? '?', $owner);
}
echo "\nSilmek için:  php scripts/purge_demo_data.php --delete-account=EMAIL  (ilanlarıyla birlikte)\n";
echo "Tek ilan:     php scripts/purge_demo_data.php --delete-listing=ILAN_ID\n";
