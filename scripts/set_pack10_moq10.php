<?php
/**
 * VESTRA — one-time migration: every listing EXCEPT Lacoste / Ralph Lauren
 * becomes pack-of-10 with a 10-piece minimum (MOQ 10, size_step 10, first
 * price tier starting at 10). Built-in demo products are already updated in
 * code (inc/products.php) — this fixes seller-added listings on the live
 * server (data/listings.json).
 *
 * Run on the server from the site root (public_html):
 *   php scripts/set_pack10_moq10.php          ← dry run (shows what would change)
 *   php scripts/set_pack10_moq10.php --apply  ← writes the changes
 */
$root = dirname(__DIR__);
foreach ([$root.'/vestra/inc/products.php', $root.'/inc/products.php'] as $inc) {
    if (is_file($inc)) { require $inc; break; }
}
if (!function_exists('vestra_listings')) { fwrite(STDERR, "inc/products.php not found — run from the site root.\n"); exit(1); }

$apply = in_array('--apply', $argv, true);
$KEEP  = ['lacoste', 'ralph lauren'];   // brands to leave untouched

$all = vestra_listings();
$changed = 0;
foreach ($all as &$p) {
    $brand = strtolower(trim($p['brand'] ?? ''));
    if (in_array($brand, $KEEP, true)) continue;
    $before = [$p['moq'] ?? null, $p['size_step'] ?? null, $p['tiers'][0]['min'] ?? null];
    $p['moq'] = 10;
    $p['size_step'] = 10;
    if (!empty($p['tiers']) && is_array($p['tiers'])) {
        $p['tiers'][0]['min'] = 10;
        // keep tiers strictly ascending after the change
        usort($p['tiers'], fn($a, $b) => ($a['min'] ?? 0) <=> ($b['min'] ?? 0));
    }
    $after = [$p['moq'], $p['size_step'], $p['tiers'][0]['min'] ?? null];
    if ($before !== $after) {
        $changed++;
        printf("%s %-16s %-34s moq %s→%s  step %s→%s  tier1 %s→%s\n",
            $apply ? '✔' : '·', $p['brand'] ?? '?', mb_substr($p['name'] ?? '?', 0, 34),
            $before[0] ?? '-', $after[0], $before[1] ?? '-', $after[1], $before[2] ?? '-', $after[2]);
    }
}
unset($p);

echo $changed
    ? ($apply ? "\n$changed listing updated & saved.\n" : "\n$changed listing WOULD change — re-run with --apply to write.\n")
    : "Nothing to change — all non-Lacoste/RL listings are already MOQ 10 / pack 10.\n";
if ($apply && $changed) vestra_save_listings($all);
