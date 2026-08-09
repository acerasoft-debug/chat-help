<?php
/**
 * VESTRA Journal — premium editorial / news section.
 * Articles live in data/journal.json (admin-managed). A small set of built-in
 * "starter" pieces can be loaded into that file with one click from the admin
 * so the section is never empty at launch. Bodies are plain text (blank line =
 * new paragraph) and are always HTML-escaped on render.
 */
require_once __DIR__.'/products.php'; // vestra_read_json / vestra_write_json / vestra_data_dir

const VESTRA_JOURNAL_CATS = ['Brand News', 'Market & Prices', 'Wholesale Trends', 'Style Edit'];

function vestra_journal_all(): array {
    $a = vestra_read_json('journal.json');
    usort($a, fn($x, $y) => strcmp((string)($y['created'] ?? ''), (string)($x['created'] ?? ''))); // newest first
    return $a;
}
function vestra_journal_published(): array {
    return array_values(array_filter(vestra_journal_all(), fn($p) => !empty($p['published'])));
}
function vestra_journal_find(string $slug): ?array {
    foreach (vestra_journal_all() as $p) if (($p['slug'] ?? '') === $slug) return $p;
    return null;
}
function vestra_journal_find_id(string $id): ?array {
    foreach (vestra_journal_all() as $p) if (($p['id'] ?? '') === $id) return $p;
    return null;
}
function vestra_journal_slug(string $title, string $exceptId = ''): string {
    $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($title)), '-');
    if ($base === '') $base = 'article';
    $base = substr($base, 0, 70);
    $exists = function ($s) use ($exceptId) {
        foreach (vestra_journal_all() as $p) if (($p['slug'] ?? '') === $s && ($p['id'] ?? '') !== $exceptId) return true;
        return false;
    };
    $slug = $base; $i = 2;
    while ($exists($slug)) { $slug = $base.'-'.$i; $i++; }
    return $slug;
}
/** Upsert an article by id; assigns id/slug/created on first save. Returns the saved record. */
function vestra_journal_save(array $data): array {
    $all = vestra_read_json('journal.json');
    $id  = $data['id'] ?? '';
    if ($id === '') { $id = 'jr_'.bin2hex(random_bytes(5)); $data['id'] = $id; $data['created'] = date('c'); }
    $data['updated'] = date('c');
    $found = false;
    foreach ($all as &$p) { if (($p['id'] ?? '') === $id) { $p = array_merge($p, $data); $data = $p; $found = true; break; } }
    unset($p);
    if (!$found) { if (empty($data['created'])) $data['created'] = date('c'); $all[] = $data; }
    vestra_write_json('journal.json', $all);
    return $data;
}
function vestra_journal_delete(string $id): void {
    vestra_write_json('journal.json', array_values(array_filter(vestra_read_json('journal.json'), fn($p) => ($p['id'] ?? '') !== $id)));
}
function vestra_journal_toggle(string $id): void {
    $all = vestra_read_json('journal.json');
    foreach ($all as &$p) if (($p['id'] ?? '') === $id) { $p['published'] = empty($p['published']); $p['updated'] = date('c'); break; }
    unset($p);
    vestra_write_json('journal.json', $all);
}
/** Safe plain-text → HTML: escape, blank lines become paragraphs, single newlines <br>.
 *
 * A block consisting only of [img:/uploads/…] or [img:/uploads/…|caption] becomes a figure.
 * Deliberately NOT general HTML or Markdown: bodies are admin-editable, so the renderer stays
 * escape-everything and understands exactly one extra construct. The path is required to be a
 * site-relative /uploads/ file — no scheme, no host, no traversal — so a body can never point
 * the reader at a third-party URL or smuggle an attribute out of the src. */
function vestra_journal_body_html(string $body, array $art = []): string {
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $blocks = array_values(array_filter(array_map('trim', preg_split('/\n{2,}/', trim($body))), 'strlen'));

    /* Editorial photographs are woven in here rather than written into the article text,
       because the text ships in the repo and the photographs do not: they are fetched to
       the server, so their filenames are not known when the piece is written. Placing
       them at render time also means one insertion covers all five languages, and a
       piece never carries a marker pointing at a file that has not arrived yet. */
    if ($art && !preg_grep('/^\[img:/', $blocks)) {
        $shots = vestra_journal_body_photos($art);
        if ($shots && count($blocks) >= 4) {
            $cuts = [max(1, intdiv(count($blocks), 3)), max(2, intdiv(count($blocks) * 2, 3))];
            $mixed = []; $k = 0;
            foreach ($blocks as $i => $b) {
                if ($k < count($shots) && $i === $cuts[$k]) {
                    $cap = vestra_journal_credit($shots[$k]);
                    $mixed[] = '[img:'.$shots[$k].($cap !== '' ? '|'.$cap : '').']';
                    $k++;
                }
                $mixed[] = $b;
            }
            $blocks = $mixed;
        }
    }

    $out = '';
    foreach ($blocks as $b) {
        if ($b === '') continue;
        if (preg_match('/^\[img:([^|\]]+)(?:\|([^\]]*))?\]$/', $b, $m)) {
            $src = trim($m[1]);
            if (preg_match('#^/uploads/[A-Za-z0-9._/-]+$#', $src) && strpos($src, '..') === false) {
                $cap = trim($m[2] ?? '');
                $out .= '<figure class="jr-fig"><img src="'.htmlspecialchars($src).'" alt="'
                     .htmlspecialchars($cap).'" loading="lazy" decoding="async">'
                     .($cap !== '' ? '<figcaption>'.htmlspecialchars($cap).'</figcaption>' : '')
                     .'</figure>';
                continue;
            }
            /* Malformed or off-site path: fall through and print it as text rather than
               silently dropping a line the author wrote. */
        }
        $out .= '<p>'.nl2br(htmlspecialchars($b)).'</p>';
    }
    return $out;
}
/* Two photographs for the body of an article, chosen stably from the credited pool and
   never repeating the one already used as the cover. The two picks sit half the pool
   apart so a short pool still yields two different pictures. */
function vestra_journal_body_photos(array $art, int $n = 2): array {
    $cover = vestra_journal_cover_path($art);
    $pool  = array_values(array_filter(vestra_journal_photo_pool(), fn($p) => $p !== $cover));
    $c     = count($pool);
    if ($c === 0) return [];
    $i0   = crc32(($art['slug'] ?? '').'|body') % $c;
    $step = max(1, intdiv($c, max(1, $n)));
    $out  = [];
    for ($i = 0; $i < min($n, $c); $i++) $out[] = $pool[($i0 + $i * $step) % $c];
    return array_values(array_unique($out));
}
function vestra_journal_reading_min(string $body): int {
    return max(1, (int)ceil(str_word_count(strip_tags($body)) / 200));
}
/* Editorial cover art for a journal article.
 *
 * Replaces a category gradient with concentric rings — the same drawing on every piece, which
 * read as decoration rather than illustration. Each article now gets a motif chosen from what it
 * is actually about: a price table becomes ledger rules, a piece on sampling becomes a fabric
 * swatch, one on buying depth becomes a hung rail.
 *
 * Constraints this design works within. The same square is cropped three ways — 21:9 (article
 * hero), 16:10 (grid thumb) and roughly 16:9 (feature panel) — and 21:9 is the tightest, leaving
 * only y 88..431 of the 520 visible, so every motif is drawn inside ±160 of its anchor. Nothing
 * is cropped horizontally anywhere. A category badge sits at the thumb's top-left, so the art is
 * weighted right of centre. And the file is base64'd into a CSS url(), so it stays geometric.
 */
function vestra_journal_cover_svg(array $p): string {
    $themes = [
        'Brand News'       => ['#1c1611', '#3d2f1f', '#d8b878'], // espresso · gold
        'Market & Prices'  => ['#0e2620', '#1b4a3a', '#7fc9a6'], // pine · mint
        'Wholesale Trends' => ['#3a2016', '#7a4026', '#f0c9a0'], // terracotta · cream
        'Style Edit'       => ['#2e1520', '#66293d', '#e8adbe'], // burgundy · blush
    ];
    $cat = (string)($p['category'] ?? 'Brand News');
    [$c1, $c2, $acc] = $themes[$cat] ?? $themes['Brand News'];

    /* Motif per subject. Keyed on words in the title so a new article picks a sensible drawing
       without anyone maintaining a slug list. The first match wins, so this list is ordered by
       how specific the word is to one subject: 'colour' before 'assortment', 'sample' before
       'carton', and the generic trust words last. */
    $t = strtolower((string)($p['title'] ?? ''));
    $motif = 'seal';
    foreach ([
        'ledger'   => ['price', 'cost', 'tier', 'line sheet', 'landed', 'margin'],
        'swatch'   => ['sample', 'fabric', 'piqué', 'pique', 'staple'],
        'rail'     => ['depth', 'presentation', 'rail', 'sell-through'],
        'colour'   => ['colour', 'color', 'palette', 'shade'],
        'sizerun'  => ['size', 'assortment', 'pack', 'curve'],
        'calendar' => ['season', 'drop', 'calendar', 'lead time'],
        'loop'     => ['resale', 'circular', 'second-hand', 'pre-loved'],
        'tag'      => ['logo', 'passport', 'label', 'swing'],
        'rings'    => ['escrow', 'deposit', 'held'],
        'seal'     => ['trust', 'verified', 'authentic', 'checklist', 'first order', 'counterfeit'],
        'carton'   => ['stock', 'carton', 'warehouse', 'eea', 'pre-order', 'shipment'],
    ] as $name => $words) {
        foreach ($words as $w) if (strpos($t, $w) !== false) { $motif = $name; break 2; }
    }

    /* One stable number per article drives every placement, so a piece keeps the same cover
       between page loads but no two sit side by side looking identical. */
    $h   = crc32(($p['slug'] ?? '').'|'.($p['title'] ?? ''));
    $rnd = fn(int $n, int $lo, int $hi) => $lo + (intdiv($h, max(1, $n)) % max(1, $hi - $lo + 1));
    $ox  = $rnd(3, 402, 468);   // anchor kept right of the category badge
    $oy  = $rnd(7, 252, 268);   // and inside the 21:9 crop, given the ±160 drawing budget
    $rot = $rnd(11, -7, 7);
    $art = '';

    if ($motif === 'ledger') {
        // A price table: rules with bars of falling length, the way a tier column steps down.
        // One row is picked out, because a tier sheet is read for the line you can actually hit.
        $hot  = $rnd(13, 1, 5);
        $rows = '';
        for ($i = 0; $i < 7; $i++) {
            $y = 104 + $i * 44;
            $w = 300 - $i * 26 + (intdiv($h, $i + 2) % 40);
            $rows .= '<line x1="0" y1="'.$y.'" x2="800" y2="'.$y.'" stroke="'.$acc.'" stroke-opacity=".10" stroke-width="1"/>';
            if ($i === $hot) $rows .= '<rect x="'.($ox - 236).'" y="'.($y - 30).'" width="500" height="34" fill="'.$acc.'" fill-opacity=".07"/>';
            $rows .= '<rect x="'.($ox - 60).'" y="'.($y - 21).'" width="'.$w.'" height="9" rx="4.5" fill="'.$acc.'" fill-opacity="'.round($i === $hot ? 0.48 : 0.30 - $i * 0.028, 3).'"/>';
        }
        $art = $rows;
    } elseif ($motif === 'swatch') {
        /* Overlapping fabric swatches with a cut edge. Three articles use this motif, so the
           stack direction, the depth of the pile and the weave on the top swatch all come off
           the hash — otherwise they would sit in the grid as three copies of one picture. */
        $dir  = $rnd(17, 0, 1) ? 1 : -1;   // pile leaning right or left
        $deep = $rnd(19, 2, 3);            // two swatches or three
        $wv   = $rnd(23, 0, 2);            // grid weave · horizontal ribs · twill
        $s    = 190;
        $art  = '<g transform="translate('.$ox.','.$oy.') rotate('.$rot.')">';
        for ($i = $deep - 1; $i >= 0; $i--) {
            $x = -$s / 2 + $dir * (-$i * 35 + ($deep - 1) * 17);
            $y = -$s / 2 + (-$i * 35 + ($deep - 1) * 17);
            if ($i === 0) { $tx = $x; $ty = $y; }
            $art .= '<rect x="'.round($x, 1).'" y="'.round($y, 1).'" width="'.$s.'" height="'.$s.'" rx="5" fill="'.$acc.'" fill-opacity="'.round(0.15 - $i * 0.04, 3).'"/>';
        }
        $art .= '<g stroke="'.$acc.'" stroke-opacity=".20" stroke-width="1.1">';
        for ($i = 1; $i < 9; $i++) {
            $d = $ty + $i * 21;
            if ($wv !== 2) $art .= '<line x1="'.$tx.'" y1="'.round($d, 1).'" x2="'.($tx + $s).'" y2="'.round($d, 1).'"/>';
            if ($wv === 0) $art .= '<line x1="'.round($tx + $i * 21, 1).'" y1="'.$ty.'" x2="'.round($tx + $i * 21, 1).'" y2="'.($ty + $s).'"/>';
            if ($wv === 2) // twill runs on the bias, the way a woven diagonal actually sits
                $art .= '<line x1="'.$tx.'" y1="'.round($ty + $i * 42 - 20, 1).'" x2="'.round($tx + $i * 42 - 20, 1).'" y2="'.$ty.'"/>'
                      . '<line x1="'.round($tx + $s, 1).'" y1="'.round($ty + $s - $i * 42 + 20, 1).'" x2="'.round($tx + $s - $i * 42 + 20, 1).'" y2="'.round($ty + $s, 1).'"/>';
        }
        $art .= '</g><rect x="'.$tx.'" y="'.$ty.'" width="'.$s.'" height="'.$s.'" rx="5" fill="none" stroke="'.$acc.'" stroke-opacity=".42" stroke-width="1.6" stroke-dasharray="7 5"/></g>';
    } elseif ($motif === 'rail') {
        /* Garments hung in depth: one rail, five of the same shirt, the middle ones nearest.
           Everything hangs below the rail, so the group is pushed down to sit centred on the
           anchor rather than leaving the bottom third of every crop empty. */
        $art = '<g transform="translate('.$ox.','.($oy + 74).')">'
             . '<line x1="-250" y1="-155" x2="250" y2="-155" stroke="'.$acc.'" stroke-opacity=".5" stroke-width="3"/>';
        for ($i = 0; $i < 5; $i++) {
            $x  = -168 + $i * 84;
            $op = 0.34 - abs($i - 2) * 0.055;
            // hook, then a shirt: shoulders out, sleeves wider, body tapering in
            $art .= '<path d="M'.$x.' -155 q7 13 0 24" fill="none" stroke="'.$acc.'" stroke-opacity=".42" stroke-width="1.6"/>'
                  . '<g transform="translate('.$x.',-131)"><path d="M0 0 l-40 20 -10 -12 -14 16 22 18 12 -10 v104 h60 v-104 l12 10 22 -18 -14 -16 -10 12 -40 -20 z" '
                  . 'fill="'.$acc.'" fill-opacity="'.round($op, 3).'"/>'
                  . '<path d="M-11 3 l11 15 11 -15" fill="none" stroke="'.$c1.'" stroke-opacity=".33" stroke-width="2.4"/></g>';
        }
        $art .= '</g>';
    } elseif ($motif === 'colour') {
        // A colour card: one paper card, bands graded light to dark, the way a range is chosen.
        $art = '<g transform="translate('.$ox.','.$oy.') rotate('.$rot.')">'
             . '<rect x="-118" y="-158" width="236" height="316" rx="10" fill="'.$acc.'" fill-opacity=".07" stroke="'.$acc.'" stroke-opacity=".26" stroke-width="1.4"/>'
             . '<circle cx="0" cy="-134" r="9" fill="none" stroke="'.$acc.'" stroke-opacity=".34" stroke-width="1.6"/>';
        for ($i = 0; $i < 6; $i++) {
            $o = 0.09 + $i * 0.062 + ((intdiv($h, $i + 3) % 12) / 400);
            $art .= '<rect x="-98" y="'.(-112 + $i * 43).'" width="196" height="35" rx="4" fill="'.$acc.'" fill-opacity="'.round($o, 3).'"/>';
        }
        $art .= '</g>';
    } elseif ($motif === 'sizerun') {
        // A size curve: how many of each size actually move, which is never a flat line.
        $art = '<g transform="translate('.$ox.','.$oy.')">'
             . '<line x1="-215" y1="130" x2="215" y2="130" stroke="'.$acc.'" stroke-opacity=".45" stroke-width="2"/>';
        // A bell, nudged per article — the middle sizes always carry the run.
        $shape = [0.34, 0.62, 0.94, 1.0, 0.74, 0.42];
        for ($i = 0; $i < 6; $i++) {
            $hgt = (int)round($shape[$i] * 250) - (intdiv($h, $i + 2) % 34);
            $x   = -204 + $i * 70;
            $art .= '<rect x="'.$x.'" y="'.(130 - $hgt).'" width="48" height="'.$hgt.'" rx="5" fill="'.$acc.'" fill-opacity="'.round(0.14 + $shape[$i] * 0.2, 3).'"/>'
                  . '<rect x="'.($x + 14).'" y="142" width="20" height="6" rx="3" fill="'.$acc.'" fill-opacity=".28"/>';
        }
        $art .= '</g>';
    } elseif ($motif === 'calendar') {
        // Two seasons as timeline strips, with the windows worth buying in marked above them.
        $art = '<g transform="translate('.$ox.','.$oy.')">';
        for ($r = 0; $r < 2; $r++) {
            $y = -66 + $r * 132;
            $art .= '<line x1="-250" y1="'.$y.'" x2="250" y2="'.$y.'" stroke="'.$acc.'" stroke-opacity=".42" stroke-width="2"/>';
            for ($i = 0; $i < 11; $i++) {
                $x    = -250 + $i * 50;
                $tall = ($i % 3 === 0);
                $art .= '<line x1="'.$x.'" y1="'.($y - ($tall ? 15 : 8)).'" x2="'.$x.'" y2="'.($y + ($tall ? 15 : 8)).'" '
                      . 'stroke="'.$acc.'" stroke-opacity="'.($tall ? '.5' : '.24').'" stroke-width="'.($tall ? 2 : 1.2).'"/>';
            }
            // Two windows per season, the second always at least two ticks after the first —
            // picking both freely let them overlap into one shapeless block.
            $slot = [intdiv($h, $r + 2) % 4, 0];
            $slot[1] = $slot[0] + 2 + (intdiv($h, $r + 5) % 4);
            foreach ($slot as $k => $i)
                $art .= '<rect x="'.(-250 + $i * 50).'" y="'.($y - 62).'" width="'.(100 - $k * 32).'" height="36" rx="5" '
                      . 'fill="'.$acc.'" fill-opacity="'.($r ? '.30' : '.20').'"/>';
        }
        $art .= '</g>';
    } elseif ($motif === 'loop') {
        // Two arrows chasing each other: stock that comes back round and sells a second time.
        $arc = function (float $r, float $a0, float $sweepDeg, float $op, float $w) use ($acc) {
            $a1 = $a0 + $sweepDeg;
            $x0 = $r * cos(deg2rad($a0)); $y0 = $r * sin(deg2rad($a0));
            $x1 = $r * cos(deg2rad($a1)); $y1 = $r * sin(deg2rad($a1));
            $large = abs($sweepDeg) > 180 ? 1 : 0;
            $swp   = $sweepDeg > 0 ? 1 : 0;
            // Heading at the end of the arc, so the head sits on the tangent rather than beside it.
            $head = $a1 + ($sweepDeg > 0 ? 90 : -90);
            return '<path d="M'.round($x0, 1).' '.round($y0, 1).' A'.$r.' '.$r.' 0 '.$large.' '.$swp.' '.round($x1, 1).' '.round($y1, 1).'" '
                 . 'fill="none" stroke="'.$acc.'" stroke-opacity="'.$op.'" stroke-width="'.$w.'" stroke-linecap="round"/>'
                 . '<path d="M0 0 l-30 -14 v28 z" fill="'.$acc.'" fill-opacity="'.$op.'" '
                 . 'transform="translate('.round($x1, 1).','.round($y1, 1).') rotate('.round($head, 1).')"/>';
        };
        $art = '<g transform="translate('.$ox.','.$oy.') rotate('.$rot.')">'
             . $arc(148, 200, 268, 0.42, 13)
             . $arc(84, 20, -268, 0.26, 10)
             . '</g>';
    } elseif ($motif === 'tag') {
        // A swing tag on its string: what a garment says about itself.
        $art = '<g transform="translate('.$ox.','.$oy.') rotate('.($rot + 5).')">'
             . '<path d="M-104 -125 h174 a14 14 0 0 1 14 14 v222 a14 14 0 0 1 -14 14 h-174 a14 14 0 0 1 -14 -14 l-56 -111 56 -111 a14 14 0 0 1 14 -14 z" '
             . 'fill="'.$acc.'" fill-opacity=".13" stroke="'.$acc.'" stroke-opacity=".38" stroke-width="1.6"/>'
             . '<circle cx="-92" cy="0" r="13" fill="none" stroke="'.$acc.'" stroke-opacity=".5" stroke-width="2.4"/>'
             . '<path d="M-105 0 C-168 -34 -196 -92 -190 -152" fill="none" stroke="'.$acc.'" stroke-opacity=".3" stroke-width="1.6"/>';
        for ($i = 0; $i < 4; $i++)
            $art .= '<rect x="-60" y="'.(-46 + $i * 34).'" width="'.(134 - $i * 30).'" height="9" rx="4.5" fill="'.$acc.'" fill-opacity="'.round(0.26 - $i * 0.04, 3).'"/>';
        $art .= '</g>';
    } elseif ($motif === 'rings') {
        // Money held between two parties: two rings, and the part they both hold.
        $art = '<g transform="translate('.$ox.','.$oy.')">'
             . '<circle cx="-70" cy="0" r="112" fill="'.$acc.'" fill-opacity=".05" stroke="'.$acc.'" stroke-opacity=".40" stroke-width="3"/>'
             . '<circle cx="70" cy="0" r="112" fill="'.$acc.'" fill-opacity=".05" stroke="'.$acc.'" stroke-opacity=".40" stroke-width="3"/>'
             // the lens where they overlap, drawn as two arcs meeting at the crossing points
             . '<path d="M0 -87.5 A112 112 0 0 1 0 87.5 A112 112 0 0 1 0 -87.5 z" fill="'.$acc.'" fill-opacity=".22"/>'
             . '<path d="M-26 0 l19 21 37 -44" fill="none" stroke="'.$c1.'" stroke-opacity=".45" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>'
             . '</g>';
    } elseif ($motif === 'carton') {
        // Cartons already sitting somewhere: stock you can ship, not stock you have to wait for.
        $box = fn(int $x, int $y, int $w, int $ht, float $op) =>
            '<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="'.$ht.'" rx="7" fill="'.$acc.'" fill-opacity="'.round($op, 3).'"/>'
          . '<line x1="'.$x.'" y1="'.($y + (int)round($ht * 0.30)).'" x2="'.($x + $w).'" y2="'.($y + (int)round($ht * 0.30)).'" stroke="'.$acc.'" stroke-opacity=".26" stroke-width="1.4"/>'
          . '<line x1="'.($x + intdiv($w, 2)).'" y1="'.$y.'" x2="'.($x + intdiv($w, 2)).'" y2="'.($y + (int)round($ht * 0.30)).'" stroke="'.$acc.'" stroke-opacity=".26" stroke-width="1.4"/>'
          . '<rect x="'.($x + intdiv($w, 2) - 30).'" y="'.($y + (int)round($ht * 0.30) + 20).'" width="60" height="8" rx="4" fill="'.$c1.'" fill-opacity=".22"/>';
        $art = '<g transform="translate('.$ox.','.$oy.')">'
             . $box(-206, 12, 180, 146, 0.15)
             . $box(6, 12, 180, 146, 0.20)
             . $box(-104, -152, 180, 146, 0.28)
             . '</g>';
    } else { // seal — trust, provenance, a check that was actually done
        // Three articles land here, so scale and the certificate rules vary off the hash.
        $sc  = $rnd(29, 86, 104) / 100;
        $art = '<g transform="translate('.$ox.','.$oy.') rotate('.$rot.') scale('.$sc.')">'
             . '<path d="M0 -155 L135 -104 v104 C135 88 76 141 0 164 -76 141 -135 88 -135 0 V-104 z" '
             . 'fill="'.$acc.'" fill-opacity=".10" stroke="'.$acc.'" stroke-opacity=".40" stroke-width="2"/>'
             . '<path d="M0 -120 L102 -82 v78 C102 64 56 105 0 123 -56 105 -102 64 -102 -4 V-82 z" '
             . 'fill="none" stroke="'.$acc.'" stroke-opacity=".22" stroke-width="1.2"/>'
             . '<path d="M-43 -14 l31 33 61 -69" fill="none" stroke="'.$acc.'" stroke-opacity=".62" stroke-width="9" stroke-linecap="round" stroke-linejoin="round"/>';
        if ($rnd(31, 0, 1))
            $art .= '<rect x="-38" y="46" width="76" height="8" rx="4" fill="'.$acc.'" fill-opacity=".26"/>'
                  . '<rect x="-24" y="68" width="48" height="8" rx="4" fill="'.$acc.'" fill-opacity=".18"/>';
        $art .= '</g>';
    }

    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 520" preserveAspectRatio="xMidYMid slice">
<defs>
<linearGradient id="bg" x1="0.05" y1="0" x2="0.9" y2="1">
<stop offset="0" stop-color="$c1"/><stop offset="0.55" stop-color="$c2"/><stop offset="1" stop-color="$c1"/>
</linearGradient>
<radialGradient id="glow" cx="0.6" cy="0.42" r="0.62">
<stop offset="0" stop-color="$acc" stop-opacity="0.16"/><stop offset="1" stop-color="$acc" stop-opacity="0"/>
</radialGradient>
<radialGradient id="vig" cx="0.5" cy="0.46" r="0.86">
<stop offset="0.45" stop-color="#000" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity="0.42"/>
</radialGradient>
<filter id="grain"><feTurbulence type="fractalNoise" baseFrequency="0.9" numOctaves="2"/>
<feColorMatrix type="saturate" values="0"/></filter>
</defs>
<rect width="800" height="520" fill="url(#bg)"/>
<rect width="800" height="520" fill="url(#glow)"/>
$art
<rect width="800" height="520" fill="url(#vig)"/>
<rect width="800" height="520" filter="url(#grain)" opacity="0.05"/>
<rect x="46" y="44" width="708" height="432" fill="none" stroke="#fff" stroke-opacity="0.10" stroke-width="1"/>
</svg>
SVG;
}
/* Real fashion/model photography for a few articles, pulled automatically from
   free stock BY KEYWORD (LoremFlickr — keyword-matched, so there are no fragile
   per-photo IDs to guess), with a stable per-article lock so the image doesn't
   reshuffle on every load. It is layered OVER the generated editorial art (see
   vestra_journal_cover_bg), so a photo that can't load simply reveals the art —
   never a broken image. An admin-set cover (Journal editor) overrides it. */
/* Editorial cover photography for an article, when we have any.
 *
 * Two things this must NOT be, both of which it has been:
 *   - loremflickr.com keyword URLs (random third-party images of goods we do not sell,
 *     blank whenever the service was slow);
 *   - catalogue packshots from /uploads (product shots on white — that is the shop's
 *     imagery, not a magazine's).
 *
 * The journal wants fashion/editorial photography, and the project owns none yet. Until
 * such files exist under /uploads/journal/, this returns nothing and every cover falls
 * back to vestra_journal_cover_svg() — the generated on-brand art, which is a deliberate
 * design and never breaks. Drop editorial files into that folder and set each article's
 * 'cover' from the admin, or extend the map below. */
function vestra_journal_model_photo(array $p): string {
    $pool = vestra_journal_photo_pool();
    if (!$pool) return '';
    $slug = (string)($p['slug'] ?? '');
    if ($slug === '') return '';
    /* Stable pick, so an article keeps the same cover between page loads. */
    return $pool[crc32($slug) % count($pool)];
}

/* Editorial photos available under /uploads/journal, newest-agnostic and cached per
   request. Only files that carry a credit entry are offered: the CC-BY / CC-BY-SA
   licences these arrive under require the photographer to be named wherever the image
   is shown, so an uncredited file is one we are not allowed to publish. */
function vestra_journal_photo_pool(): array {
    static $pool = null;
    if ($pool !== null) return $pool;
    $pool = [];
    foreach (vestra_journal_credits() as $file => $c) {
        if (!empty($c['artist']) && is_readable(__DIR__.'/../uploads/journal/'.$file)) {
            $pool[] = '/uploads/journal/'.$file;
        }
    }
    sort($pool);
    return $pool;
}

/** file name => ['artist','license','source','desc'], written by the journal-photos job. */
function vestra_journal_credits(): array {
    static $c = null;
    if ($c !== null) return $c;
    $c = [];
    $f = __DIR__.'/../uploads/journal/credits.json';
    if (is_readable($f)) {
        $j = json_decode((string)file_get_contents($f), true);
        if (is_array($j)) $c = $j;
    }
    return $c;
}

/** Default subjects for the editorial photo fetch — fashion, not product packshots. */
function vestra_journal_photo_queries(): array {
    return [
        'fashion editorial photography',
        'fashion model portrait studio',
        'fashion boutique interior',
        'clothing rail retail store',
        'menswear lookbook',
        'textile fabric detail macro',
    ];
}

/* Fetch editorial photography from Wikimedia Commons into uploads/journal/ and record who
 * shot each one in credits.json.
 *
 * Commons needs no API key, which is why it is the source; the price is the licence, which
 * requires the photographer to be named wherever the picture appears. That is why nothing
 * here writes an image without also writing its credit — vestra_journal_photo_pool() only
 * offers credited files, so an image can never reach a page unattributed.
 *
 * Commercial-use filter: this is a commercial site, so NonCommercial, NoDerivatives and
 * fair-use files are rejected outright.
 *
 * Returns a report: ['examined'=>int,'saved'=>int,'skipped'=>[reason=>n],'files'=>[…],'errors'=>[…]]
 */
function vestra_journal_fetch_photos(array $queries = [], int $per = 6, int $minWidth = 1400, bool $dry = true): array {
    $dir = __DIR__.'/../uploads/journal';
    $rep = ['examined'=>0, 'saved'=>0, 'skipped'=>[], 'files'=>[], 'errors'=>[]];
    $queries = $queries ?: vestra_journal_photo_queries();
    $per = max(1, $per); $minWidth = max(400, $minWidth);

    $bump = function (string $why) use (&$rep) { $rep['skipped'][$why] = ($rep['skipped'][$why] ?? 0) + 1; };

    $licOk = function (string $l): bool {
        $l = strtolower($l);
        if ($l === '') return false;
        foreach (['non-commercial','noncommercial','-nc','fair use','no derivative','-nd'] as $bad)
            if (strpos($l, $bad) !== false) return false;
        foreach (['cc0','public domain','cc by','cc-by','attribution'] as $ok)
            if (strpos($l, $ok) !== false) return true;
        return false;
    };
    $get = function (string $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true,
            CURLOPT_TIMEOUT=>60, CURLOPT_CONNECTTIMEOUT=>20,
            // Commons blocks callers that do not identify themselves.
            CURLOPT_USERAGENT=>'VESTRA-Journal/1.0 (https://vestrasales.com; support@vestrasales.com)',
        ]);
        $b = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        return $code === 200 ? $b : null;
    };
    $clean = fn($s) => trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string)$s), ENT_QUOTES, 'UTF-8')));

    if (!$dry && !is_dir($dir)) @mkdir($dir, 0755, true);
    $credits = vestra_journal_credits();

    foreach ($queries as $q) {
        $q = trim($q); if ($q === '') continue;
        $raw = $get('https://commons.wikimedia.org/w/api.php?action=query&format=json'
            .'&generator=search&gsrnamespace=6&gsrlimit=40&gsrsearch='.rawurlencode($q)
            .'&prop=imageinfo&iiprop=url|size|extmetadata&iiurlwidth=1600');
        if ($raw === null) { $rep['errors'][] = 'no API response for: '.$q; continue; }
        $pages = json_decode($raw, true)['query']['pages'] ?? [];
        if (!$pages) { $rep['errors'][] = 'no results for: '.$q; continue; }

        $n = 0;
        foreach ($pages as $p) {
            if ($n >= $per) break;
            $ii = $p['imageinfo'][0] ?? null; if (!$ii) continue;
            $rep['examined']++;
            $mime = (string)($ii['mime'] ?? '');
            if (strpos($mime, 'image/') !== 0 || strpos($mime, 'svg') !== false) { $bump('not a raster image'); continue; }
            if ((int)($ii['width'] ?? 0) < $minWidth) { $bump('too small'); continue; }

            $em = $ii['extmetadata'] ?? [];
            $license = $clean($em['LicenseShortName']['value'] ?? '');
            if (!$licOk($license)) { $bump('licence not usable commercially'); continue; }
            $artist = $clean($em['Artist']['value'] ?? '') ?: 'Wikimedia Commons';
            if (mb_strlen($artist) > 70) $artist = mb_substr($artist, 0, 70).'…';

            $title = (string)($p['title'] ?? '');
            $src   = (string)($ii['thumburl'] ?? $ii['url'] ?? ''); if ($src === '') { $bump('no url'); continue; }
            $ext   = strtolower((string)pathinfo((string)parse_url($src, PHP_URL_PATH), PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) $ext = 'jpg';
            $name  = substr(strtolower(trim(preg_replace('/[^a-z0-9]+/i','-', preg_replace('/^File:/','',$title)), '-')), 0, 60).'.'.$ext;

            $n++;
            $rep['files'][] = ['file'=>$name, 'width'=>(int)($ii['width'] ?? 0), 'license'=>$license, 'artist'=>$artist];
            if ($dry) continue;

            $bin = $get($src);
            if ($bin === null || strlen($bin) < 8000) { $rep['errors'][] = 'download failed: '.$name; continue; }
            file_put_contents($dir.'/'.$name, $bin);
            $credits[$name] = [
                'artist'  => $artist,
                'license' => $license,
                'source'  => 'https://commons.wikimedia.org/wiki/'.rawurlencode(str_replace(' ', '_', $title)),
                'desc'    => mb_substr($clean($em['ImageDescription']['value'] ?? ''), 0, 140),
            ];
            $rep['saved']++;
        }
    }
    if (!$dry && $rep['saved'] > 0) {
        file_put_contents($dir.'/credits.json',
            json_encode($credits, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    }
    return $rep;
}

/** Attribution line for an image path, or '' when the file needs none (our own art). */
function vestra_journal_credit(string $path): string {
    if (strpos($path, '/uploads/journal/') !== 0) return '';
    $c = vestra_journal_credits()[basename($path)] ?? null;
    if (!$c || empty($c['artist'])) return '';
    $s = $c['artist'];
    if (!empty($c['license'])) $s .= ' · '.$c['license'];
    return $s;
}
/* Full CSS background-image value for a cover: the real photo (admin cover, or a
   keyword model photo) layered OVER the generated editorial SVG data-URI, so if
   the photo fails to load the art shows through instead of a blank/broken block. */
function vestra_journal_cover_bg(array $p): string {
    $svg  = "url('data:image/svg+xml;base64,".base64_encode(vestra_journal_cover_svg($p))."')";
    $path = vestra_journal_cover_path($p);
    return $path !== '' ? "url('".$path."'), ".$svg : $svg;
}

/** The photo an article's cover resolves to ('' when it falls back to the generated art). */
function vestra_journal_cover_path(array $p): string {
    if (!empty($p['cover'])) return (string)$p['cover'];
    return vestra_journal_model_photo($p);
}
/* Return the article with title/excerpt/body swapped to the reader's language
   when a translation exists under $a['i18n'][$lang]; English is the base/fallback. */
function vestra_journal_localize(array $a, string $lang): array {
    if ($lang !== 'en' && !empty($a['i18n'][$lang]) && is_array($a['i18n'][$lang])) {
        foreach (['title', 'excerpt', 'body'] as $f)
            if (!empty($a['i18n'][$lang][$f])) $a[$f] = $a['i18n'][$lang][$f];
    }
    return $a;
}

/** Built-in starter articles — loaded into data/journal.json on admin request.
 *  The content (English + de/fr/it/es translations) lives in inc/journal_seed.json,
 *  which ships with the code, so it deploys like any other source file and needs
 *  no escaping gymnastics in PHP. Timestamps are staggered by one day per article
 *  so the newest-first ordering is stable and reads as a natural publishing run. */
function vestra_journal_starters(): array {
    $file = __DIR__.'/journal_seed.json';
    $seed = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    if (!is_array($seed)) return [];
    $out = []; $i = 0;
    foreach ($seed as $s) {
        if (empty($s['title'])) continue;
        $ts = date('c', time() - $i * 86400);
        $out[] = [
            'title'     => (string)$s['title'],
            'category'  => (string)($s['category'] ?? 'Brand News'),
            'author'    => (string)($s['author'] ?? 'VESTRA Editorial'),
            'cover'     => (string)($s['cover'] ?? ''),
            'excerpt'   => (string)($s['excerpt'] ?? ''),
            'body'      => (string)($s['body'] ?? ''),
            'i18n'      => (isset($s['i18n']) && is_array($s['i18n'])) ? $s['i18n'] : [],
            'published' => true,
            'created'   => $ts,
            'updated'   => $ts,
        ];
        $i++;
    }
    return $out;
}
/** Load starter articles into data/journal.json. New titles are added in full;
 *  an existing starter (matched by title) has its translations back-filled if it
 *  has none yet — so clicking again upgrades older English-only starters in place
 *  without touching any article the admin has since edited. Returns count changed. */
function vestra_journal_seed_starters(): int {
    $all = vestra_read_json('journal.json');
    $byTitle = [];
    foreach ($all as $idx => $p) $byTitle[strtolower(trim((string)($p['title'] ?? '')))] = $idx;
    $changed = 0;
    foreach (vestra_journal_starters() as $s) {
        $key = strtolower(trim($s['title']));
        if (isset($byTitle[$key])) {
            $idx = $byTitle[$key];
            $cur = (isset($all[$idx]['i18n']) && is_array($all[$idx]['i18n'])) ? $all[$idx]['i18n'] : [];
            $touched = false;
            foreach (($s['i18n'] ?? []) as $l => $tr) {          // add any language the stored record lacks;
                if (empty($cur[$l])) { $cur[$l] = $tr; $touched = true; } // never overwrite an existing translation
            }
            if ($touched) { $all[$idx]['i18n'] = $cur; $all[$idx]['updated'] = date('c'); $changed++; }
            continue;
        }
        $s['id']   = 'jr_'.bin2hex(random_bytes(5));
        $s['slug'] = vestra_journal_slug($s['title']);
        $all[] = $s;
        $byTitle[$key] = array_key_last($all);
        $changed++;
    }
    if ($changed) vestra_write_json('journal.json', $all);
    return $changed;
}
