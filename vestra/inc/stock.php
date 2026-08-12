<?php
/**
 * VESTRA — per-size stock for the trade list.
 *
 * NOTHING IS STORED. The figures are derived from the product id, so listings.json is
 * never written to and switching this off is deleting a call, not migrating data back.
 *
 * DETERMINISTIC BY DESIGN. Seeded from the product id, so the same article always shows
 * the same numbers: on the page, in the Excel, in the PDF, and on the next reload. Fresh
 * randomness per request would have a buyer refresh the page and see a different stock
 * figure, and the spreadsheet they downloaded disagree with the page they downloaded it
 * from — which reads as a broken system rather than a moving one.
 *
 * TOTAL FIRST, THEN THE SPLIT. The article's total comes from its category band; the
 * sizes divide that total by weight. The earlier version worked the other way round —
 * a band per size, total falling out of the sum — and could not express "shorts run
 * 500-600 a piece" without rewriting every band.
 */

/* Every brand. Narrow this array to roll the figures out gradually instead. */
function vestra_stock_brands(): array {
    return [];
}
function vestra_stock_enabled(array $p): bool {
    $only = vestra_stock_brands();
    if (!$only) return true;
    return in_array(strtolower(trim((string)($p['brand'] ?? ''))), $only, true);
}

/* Deep-stock lines: carried in volume, quoted in volume. */
function vestra_stock_deep_brands(): array {
    return ['lacoste', 'ralph lauren', 'fred perry'];
}

/* Size ladder per category. Denim is numeric and runs 44-54; everything else is S-XXL. */
function vestra_stock_sizes(string $cat): array {
    $c = strtolower($cat);
    if (str_contains($c, 'jean') || str_contains($c, 'trouser') || str_contains($c, 'pant')) {
        return ['44', '46', '48', '50', '52', '54'];
    }
    return ['S', 'M', 'L', 'XL', 'XXL'];
}

/* Relative depth per size — the shape of a bought run: middles deep, ends thin. */
function vestra_stock_weights(array $sizes): array {
    $w = [
        'S'  => 0.62, 'M'  => 1.00, 'L'  => 1.00, 'XL' => 0.78, 'XXL' => 0.60,
        '44' => 0.60, '46' => 0.80, '48' => 1.00, '50' => 1.00, '52' => 0.80, '54' => 0.60,
    ];
    $out = [];
    foreach ($sizes as $s) $out[$s] = $w[$s] ?? 0.80;
    return $out;
}

/**
 * Total pieces per article, as [min, max].
 *
 * Order matters. "Jeans Shorts" matches both denim and shorts, and the two bands are far
 * apart (100-200 against 500-600), so the tie is broken deliberately rather than by
 * whichever str_contains ran first: it is checked against denim, because it is bought and
 * sized like denim (the 44-54 ladder) even though the garment is a short. One line moves
 * it if that reads wrong on the shelf.
 */
function vestra_stock_band(string $cat, string $brand): array {
    if (in_array(strtolower(trim($brand)), vestra_stock_deep_brands(), true)) return [600, 820];

    $c = strtolower($cat);
    if (str_contains($c, 'swim') || str_contains($c, 'beach'))  return [50, 100];   // before shorts: "Swim Shorts"
    if (str_contains($c, 'jean') || str_contains($c, 'denim'))  return [100, 200];  // before shorts: "Jeans Shorts"
    if (str_contains($c, 'short'))                              return [500, 600];
    return [100, 150];                                                              // tees, polos, sweats, the rest
}

/**
 * @return array{sizes: array<string,int>, total: int}
 */
function vestra_stock_for(array $p): array {
    $id    = (string)($p['id'] ?? '');
    $brand = (string)($p['brand'] ?? '');
    $cat   = (string)($p['cat'] ?? '');
    $sizes = vestra_stock_sizes($cat);
    $w     = vestra_stock_weights($sizes);

    mt_srand(crc32('vestra-stock-v2|'.$id));
    [$lo, $hi] = vestra_stock_band($cat, $brand);
    $total = mt_rand($lo, $hi);
    mt_srand();                                  // leave global randomness as we found it

    /* Split by weight. Rounding each share independently loses or gains a few pieces
       against the drawn total, so the drift is settled on the deepest size — the printed
       per-size figures then add up to exactly the printed total. A stock list whose
       column does not sum to its own total is the first thing a buyer notices. */
    $sum   = array_sum($w);
    $out   = [];
    foreach ($sizes as $s) $out[$s] = max(1, (int)round($total * $w[$s] / $sum));

    $drift = $total - array_sum($out);
    if ($drift !== 0) {
        $deepest = array_keys($w, max($w))[0];
        $out[$deepest] = max(1, $out[$deepest] + $drift);
    }

    return ['sizes' => $out, 'total' => array_sum($out)];
}

/* One-line rendering: "S 18 · M 29 · L 29 · XL 23 · XXL 17  (116 pcs)" */
function vestra_stock_line(array $st): string {
    $bits = [];
    foreach ($st['sizes'] as $s => $q) $bits[] = $s.' '.$q;
    return implode(' · ', $bits).'  ('.$st['total'].' pcs)';
}
