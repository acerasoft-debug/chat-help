<?php
/**
 * VESTRA — per-size stock for the trade list.
 *
 * NOTHING IS STORED. The figures are derived from the product id, so listings.json is
 * never written to and switching this off is deleting a call, not migrating data back.
 * One exception, and it only ever makes a figure truer: a listing that carries its own
 * recorded stock ('stock', from a supplier line sheet) prints that instead -- see
 * vestra_stock_real().
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
 * REAL stock, where the listing carries it ('stock' => ['S' => 2, 'M' => 6, ...]).
 *
 * A supplier line sheet sometimes gives the actual quantity on hand per size. The
 * Burberry piqué polo sheet of 29 Sep 2026 did: 19 to 97 pieces per model, and one
 * model with no size S at all. The derived band for a polo is 100-150, so without this
 * the trade list would have printed "116 pcs in stock" for an article that has 19 --
 * and the order that follows could not be filled. A recorded figure always wins; the
 * derived one is only the fallback for listings that carry none.
 *
 * Sizes are kept in the listing's own order and a zero stays a zero ("S 0" is the
 * truth about that model, and dropping the size would hide it). A malformed map
 * (negative, fractional or non-numeric quantity, empty size label) is ignored as a
 * whole rather than half-read: a partly-read stock line is a wrong stock line.
 *
 * TWO SHAPES. Flat, one model: ['S' => 2, 'M' => 6]. Nested, one listing that
 * carries several colourways (operator, 29 Sep 2026: the eight Burberry polos
 * became ONE listing, "fotolari ve listeleri tek bir ilanda"): the keys are the
 * listing's colour names and each value is a flat map --
 * ['Green (8099164)' => ['S' => 2, ...], 'Black (8096425)' => [...]]. The nested
 * shape returns the SAME 'sizes'/'total' (summed across colours, sizes in order of
 * first appearance) so every existing reader -- line sheets, price list, e-mail --
 * keeps working unchanged, plus 'by_colour' for the readers that want the split.
 * Mixing the two shapes in one map is malformed and rejected as a whole.
 *
 * @return array{sizes: array<string,int>, total: int, real: true, by_colour?: array<string, array{sizes: array<string,int>, total: int}>}|null
 */
function vestra_stock_real(array $p): ?array {
    $raw = $p['stock'] ?? null;
    if (!is_array($raw) || !$raw) return null;
    $flat = function (array $m): ?array {
        if (!$m) return null;
        $out = [];
        foreach ($m as $size => $qty) {
            $size = trim((string)$size);
            if ($size === '' || is_bool($qty) || is_array($qty) || !is_numeric($qty)) return null;
            if ((float)$qty < 0 || (float)$qty != (int)$qty) return null;
            $out[$size] = (int)$qty;
        }
        return $out;
    };
    $nested = true; $anyArr = false;
    foreach ($raw as $v) { if (is_array($v)) $anyArr = true; else $nested = false; }
    if (!$anyArr) {
        $out = $flat($raw);
        if ($out === null) return null;
        return ['sizes' => $out, 'total' => array_sum($out), 'real' => true];
    }
    if (!$nested) return null;                       // mixed shape: half a map is no map
    $by = []; $sizes = [];
    foreach ($raw as $colour => $m) {
        $colour = trim((string)$colour);
        $one = $flat((array)$m);
        if ($colour === '' || $one === null) return null;
        $by[$colour] = ['sizes' => $one, 'total' => array_sum($one)];
        foreach ($one as $s => $q) $sizes[$s] = ($sizes[$s] ?? 0) + $q;
    }
    return ['sizes' => $sizes, 'total' => array_sum($sizes), 'real' => true, 'by_colour' => $by];
}

/**
 * @return array{sizes: array<string,int>, total: int, real: bool}
 */
function vestra_stock_for(array $p): array {
    $real = vestra_stock_real($p);
    if ($real !== null) return $real;

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

    return ['sizes' => $out, 'total' => array_sum($out), 'real' => false];
}

/* One-line rendering: "S 18 · M 29 · L 29 · XL 23 · XXL 17  (116 pcs)" */
function vestra_stock_line(array $st): string {
    $bits = [];
    foreach ($st['sizes'] as $s => $q) $bits[] = $s.' '.$q;
    return implode(' · ', $bits).'  ('.$st['total'].' pcs)';
}
/* The per-colour split as a row list, in the listing's own colour order --
   [['colour' => 'Green (8099164)', 'sizes' => [...], 'total' => 19], ...]. A flat
   (single-model) stock comes back as one row with an empty colour, so a reader
   that wants "one line per colourway" needs no second code path. */
function vestra_stock_rows(array $st): array {
    if (!empty($st['by_colour']) && is_array($st['by_colour'])) {
        $rows = [];
        foreach ($st['by_colour'] as $c => $x) $rows[] = ['colour' => (string)$c, 'sizes' => (array)$x['sizes'], 'total' => (int)$x['total']];
        return $rows;
    }
    return [['colour' => '', 'sizes' => (array)($st['sizes'] ?? []), 'total' => (int)($st['total'] ?? 0)]];
}
