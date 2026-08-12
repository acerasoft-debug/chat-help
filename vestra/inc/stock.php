<?php
/**
 * VESTRA — per-size stock for the trade list.
 *
 * NOTHING IS STORED. The figures are derived from the product id, so listings.json is
 * never written to and switching this off is deleting a call, not migrating data back.
 *
 * DETERMINISTIC BY DESIGN. The quantities are seeded from the product id, so the same
 * article always shows the same numbers: on the page, in the Excel, in the PDF, and on
 * the next reload. Fresh mt_rand() on every request would have the buyer refresh the
 * page and see a different stock figure, and the spreadsheet they downloaded disagree
 * with the page they downloaded it from -- which reads as a broken system rather than a
 * moving one.
 *
 * SHAPE. Middle sizes carry the depth, the ends run thin: that is how a real size run is
 * bought and how it sells out. M and L heaviest, S and XXL lightest.
 */

/* Rolled out brand by brand. Empty array = every brand.
   Starting narrow because these numbers go in front of customers: four brands cover all
   three shapes (deep stock, S-XXL, numeric jeans) and can be read on the live page before
   the other thirteen inherit them. */
function vestra_stock_brands(): array {
    return ['lacoste', 'ralph lauren', 'dsquared2', 'balenciaga'];
}
function vestra_stock_enabled(array $p): bool {
    $only = vestra_stock_brands();
    if (!$only) return true;
    return in_array(strtolower(trim((string)($p['brand'] ?? ''))), $only, true);
}

/* Size ladder per category. Jeans are numeric and run 44-54; everything else is S-XXL. */
function vestra_stock_sizes(string $cat): array {
    $c = strtolower($cat);
    if (str_contains($c, 'jean') || str_contains($c, 'trouser') || str_contains($c, 'pant')) {
        return ['44', '46', '48', '50', '52', '54'];
    }
    return ['S', 'M', 'L', 'XL', 'XXL'];
}

/* Per-size band, as [min, max] within the operator's 50-100 range.
   Bands rather than weights: the first version multiplied one random number by a weight
   and then clamped anything under 50 back up to 50 -- and since every weight is at or
   below 1.0, most sizes landed on the clamp. A whole run came out "50 50 51 50 50",
   flat, which is the one shape that was explicitly not wanted. Bands cannot collapse:
   M and L sit above XL, which sits above S and XXL, by construction. */
function vestra_stock_bands(array $sizes): array {
    $b = [
        'S'  => [50, 64],  'M'  => [86, 100], 'L' => [82, 98], 'XL' => [66, 80], 'XXL' => [50, 62],
        '44' => [50, 60],  '46' => [66, 78],  '48' => [86, 100],
        '50' => [84, 100], '52' => [66, 78],  '54' => [50, 60],
    ];
    $out = [];
    foreach ($sizes as $s) $out[$s] = $b[$s] ?? [60, 80];
    return $out;
}

/**
 * @return array{sizes: array<string,int>, total: int}
 */
function vestra_stock_for(array $p): array {
    $id    = (string)($p['id'] ?? '');
    $brand = strtolower(trim((string)($p['brand'] ?? '')));
    $sizes = vestra_stock_sizes((string)($p['cat'] ?? ''));
    $bands = vestra_stock_bands($sizes);

    /* Seeded from the id: same article, same numbers, every time and everywhere. */
    mt_srand(crc32('vestra-stock-v1|'.$id));

    /* Lacoste and Ralph Lauren are the deep-stock lines: 600 pieces minimum per article.
       Five sizes capped at 100 each can only reach 500, so these two brands necessarily
       run above the 50-100 per-size band -- the two instructions cannot both hold, and
       the total is the one that matters commercially. Everyone else stays inside 50-100. */
    $deep = ($brand === 'lacoste' || $brand === 'ralph lauren');
    $mult = $deep ? 1.62 : 1.0;

    $out = [];
    foreach ($sizes as $s) {
        [$lo, $hi] = $bands[$s];
        $out[$s] = (int)round(mt_rand($lo, $hi) * $mult);
    }

    /* Top-up, if rounding leaves the total just under its floor. Depth is added to the
       middle sizes, never to S or XXL -- adding it at the ends would undo the shape. */
    $min = $deep ? 600 : 100;
    $mid = array_slice($sizes, 1, max(1, count($sizes) - 2));
    $guard = 0;
    while (array_sum($out) < $min && $guard++ < 300) {
        foreach ($mid as $s) {
            $out[$s] += 4;
            if (array_sum($out) >= $min) break;
        }
    }
    mt_srand();                                       // leave global randomness as we found it

    return ['sizes' => $out, 'total' => array_sum($out)];
}

/* One-line rendering for the list: "S 61 · M 98 · L 94 · XL 76 · XXL 58  (387 pcs)" */
function vestra_stock_line(array $st): string {
    $bits = [];
    foreach ($st['sizes'] as $s => $q) $bits[] = $s.' '.$q;
    return implode(' · ', $bits).'  ('.$st['total'].' pcs)';
}
