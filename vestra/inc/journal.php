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
        $B = count($blocks);
        /* How many photographs a piece carries follows how long it is, rather than being
           fixed at two. A four-paragraph note broken up three times is mostly pictures;
           a twelve-paragraph piece with two is a wall of text with a break in it. One
           image per three paragraphs, floored at two and capped at four — past four the
           reader is scrolling through a gallery looking for where the argument resumed. */
        $shots = vestra_journal_body_photos($art, max(2, min(4, intdiv($B, 3))));
        if ($shots && $B >= 4) {
            /* Evenly spaced: n images across B blocks sit at B*j/(n+1). Never at index 0 —
               an article opens with its own first paragraph, not with a photograph — and
               never past the last block, which would leave a picture with nothing after it. */
            $cuts = [];
            for ($j = 1; $j <= count($shots); $j++) {
                $at = max(1, intdiv($B * $j, count($shots) + 1));
                /* at least one paragraph between two pictures — on a short piece the even
                   spacing collapses to adjacent slots and the images stack with a single
                   line wedged between them */
                if ($cuts && $at <= end($cuts) + 1) $at = end($cuts) + 2;
                if ($at >= $B) break;
                $cuts[] = $at;
            }
            $mixed = []; $k = 0;
            foreach ($blocks as $i => $b) {
                if ($k < count($cuts) && $i === $cuts[$k]) {
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
                /* alt describes the picture, figcaption names the photographer. Reusing the
                   credit as alt read "Photographer · CC BY-SA 4.0" to a screen reader, which
                   tells someone who cannot see the image nothing about what is in it. */
                $alt = vestra_journal_photo_desc($src) ?: $cap;
                $out .= '<figure class="jr-fig"><img src="'.htmlspecialchars($src).'" alt="'
                     .htmlspecialchars($alt).'" loading="lazy" decoding="async">'
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
        /* cloth/wool/silk/mill: kumas cografyasi yazilari da kumas cizimi almali,
           yoksa hepsi varsayilan muhure dusuyor ve dergi tek desenli gorunuyor. */
        'swatch'   => ['sample', 'fabric', 'piqué', 'pique', 'staple', 'cloth', 'wool', 'silk', 'mill'],
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
function vestra_journal_photo_pool(bool $fresh = false): array {
    static $pool = null;
    if ($fresh) $pool = null;
    if ($pool !== null) return $pool;
    $pool = [];
    $reject = vestra_journal_photo_reject();
    foreach (vestra_journal_credits($fresh) as $file => $c) {
        if (empty($c['artist']) || !is_readable(__DIR__.'/../uploads/journal/'.$file)) continue;
        /* The same reject list is applied here, not only at fetch time. Files downloaded
           before a subject was ruled out would otherwise stay publishable forever, and
           the pool is the last gate before a picture reaches a page — so ruling something
           out here takes it off the site without needing to touch the server. Hyphens
           become spaces first: the stored name is the Commons title with its spaces
           replaced, so 'Dog sweater 2.jpg' is on disk as 'dog-sweater-2-…'. */
        $name = str_replace('-', ' ', mb_strtolower($file));
        foreach ($reject as $bad) if (strpos($name, $bad) !== false) continue 2;
        $pool[] = '/uploads/journal/'.$file;
    }
    sort($pool);
    return $pool;
}

/** file name => ['artist','license','source','desc'], written by the journal-photos job. */
function vestra_journal_credits(bool $fresh = false): array {
    static $c = null;
    /* The fetch job reads credits, downloads, writes credits, then asks how big the pool
       got — and got 0 every time, because this static still held the pre-download copy.
       clearstatcache() does not touch PHP statics, so the caller needs a way to say
       "re-read from disk". Page rendering never passes it and keeps the one-read-per-
       request behaviour this cache exists for. */
    if ($fresh) $c = null;
    if ($c !== null) return $c;
    $c = [];
    $f = __DIR__.'/../uploads/journal/credits.json';
    if (is_readable($f)) {
        $j = json_decode((string)file_get_contents($f), true);
        if (is_array($j)) $c = $j;
    }
    return $c;
}

/** Default subjects for the editorial photo fetch — fashion, not product packshots.
 *  Kept to two or three plain words each: an early pass used phrases like
 *  "clothing rail retail store" and "textile fabric detail macro", and Commons
 *  returned nothing at all for three of six — its search wants subjects, not
 *  descriptions of a shot.
 *
 *  Deliberately about cloth, craft and shop floors rather than people. Asking Commons
 *  for "fashion model" or "fashion week" returns red-carpet pictures of named
 *  individuals, and a CC licence grants copyright permission only — it says nothing
 *  about the subject's likeness, which a commercial B2B page would be trading on.
 *  These articles are about buying, pricing and assortment anyway, so cloth and rails
 *  illustrate them better than a portrait does. */
function vestra_journal_photo_queries(): array {
    return [
        /* the original twelve */
        'denim fabric',
        'wool knitwear',
        'textile fabric',
        'clothes shop interior',
        'boutique window display',
        'tailor workshop',
        'sewing atelier',
        'linen fabric',
        'wool yarn',
        'garment factory',
        'silk fabric',
        'knitted sweater',
        /* Widened because twenty-one articles were drawing covers and body shots from one
           small pool, so the same photograph turned up on several pieces. Each addition
           names a cloth, a weave or a workroom — the two things Commons indexes well and
           the journal can legally publish. Every term here has a matching entry in
           vestra_journal_photo_require(); a query whose word is not on that list returns
           results that are then all rejected, which reads as "Commons has nothing" when
           in fact we filtered it out ourselves. */
        'tweed fabric',
        'corduroy',
        'velvet fabric',
        'cashmere wool',
        'embroidery textile',
        'weaving loom',
        'spinning wheel wool',
        'textile dyeing',
        'sewing machine',
        'thread spool',
        'haberdashery shop',
        'mannequin clothing',
        'cotton mill',
        'jacquard weaving',
        'tartan cloth',
        'houndstooth fabric',
        'gingham fabric',
        'flax fibre',
    ];
}

/** A Commons title must contain one of these or the file is not published.
 *
 *  Commons searches description text, not just titles, so a query drags in whatever
 *  happens to mention the words. 'clothing rail' returned the interior of a Bergen
 *  LIGHT RAIL tram; 'folded shirts' returned an airplane and a Godzilla show booth;
 *  'leather workshop' returned an iPhone in a leather case. Blocking bad subjects
 *  one at a time cannot win against that -- the junk is unbounded and unpredictable,
 *  while what belongs here is a short, nameable list. So the test is inverted: say
 *  what the journal illustrates itself with, and let everything else fall away. */
function vestra_journal_photo_require(): array {
    return ['denim', 'fabric', 'textile', 'tailor', 'atelier', 'couture', 'sewing', 'sewn',
            'yarn', 'wool', 'merino', 'knit', 'linen', 'cotton', 'silk', 'garment', 'apparel',
            'clothes', 'clothing', 'boutique', 'dressmak', 'weav', 'loom', 'spinning', 'thread',
            'costume', 'shirt', 'knitwear', 'sweater',
            /* cloths, weaves and workroom subjects added alongside the widened query list.
               'mill' is deliberately absent even though 'cotton mill' is queried: it matches
               Miller, Millennium and every Mill Street, and the cotton in the query already
               carries those results through. Whole words where a fragment would over-match --
               'dyeing' and 'dyed' rather than 'dye', which any surname Dyer would satisfy. */
            'tweed', 'corduroy', 'velvet', 'cashmere', 'embroider', 'dyeing', 'dyed',
            'spool', 'bobbin', 'haberdash', 'mannequin', 'jacquard', 'tartan', 'houndstooth',
            'gingham', 'flax'];
}

/** Commons titles carrying any of these are never published, whatever their licence.
 *
 *  Three separate reasons, all found by reading an actual shortlist rather than guessing:
 *  awareness campaigns arrive under garment words but are about assault, not clothing
 *  ("Denim Day"); museum accession shots are the catalogue packshot this journal is
 *  explicitly not illustrated with; and children are not a subject a wholesale site
 *  should be putting on a page at all. */
function vestra_journal_photo_reject(): array {
    return ['denim day', 'objectnr', 'inventarisnummer', 'accession', 'child', 'kinder',
            'baby', 'nude', 'lingerie model', 'red carpet', 'premiere', 'award',
            /* drawings and renders answer these queries too, and read as clip art next to
               a photograph — 'A solarpunk tailor workshop' came back from 'tailor workshop' */
            'solarpunk', 'illustration', 'drawing', 'painting', 'engraving', 'clipart', 'logo',
            /* a mill's front gate and a heritage plaque both mention the textile mill they
               belong to, and both photograph as signage rather than as cloth */
            'gate of', 'sign of', 'signboard',
            /* 'knitted sweater' found a dog in one, and 'garment factory' found workers'
               barracks — a labour-conditions picture, not a trade one. Storm and flood
               damage arrives through clothes-shop queries the same way. */
            'dog sweater', 'dog coat', 'dog jumper', 'barracks', 'flood', 'hurricane',
            'katrina', 'debris', 'silty', 'barbie',
            /* 'velvet' earns its place in the require list as a cloth, but the word belongs
               to a band, a bloodless coup and an insect before it belongs to fabric. */
            'velvet underground', 'velvet revolution', 'velvet ant', 'velvet worm',
            /* a mannequin in a war museum is wearing a uniform, and one in a shop window at
               Halloween is a costume display -- neither is the clothing trade */
            'museum', 'halloween', 'waxwork', 'madame tussaud',
            /* Read off the first fetch that used the widened query list, which is how every
               other entry here was found. Two failures the existing list did not cover:
               'spinning wheel wool' returned three Delacroix lithographs of Marguerite at
               her wheel -- artwork, and the existing 'drawing'/'engraving' guards miss it
               because the title names the sitter, not the medium; and 'gingham fabric'
               returned a named idol at a promotional event, which is the likeness problem
               'red carpet' and 'premiere' exist to stop, arriving through a cloth word. */
            'au rouet', 'lithograph', 'bnk48', 'akb48', 'roadshow', 'idol'];
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
        /* iiprop MUST list mime: the first run asked for url|size|extmetadata, so every
           result came back without a mime type and the raster check below rejected all
           120 of them. filetype:bitmap keeps SVGs and PDFs out of the results entirely
           rather than fetching and discarding them. */
        $raw = $get('https://commons.wikimedia.org/w/api.php?action=query&format=json'
            .'&generator=search&gsrnamespace=6&gsrlimit=40&gsrsearch='.rawurlencode($q.' filetype:bitmap')
            .'&prop=imageinfo&iiprop=url|size|mime|extmetadata&iiurlwidth=1600');
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

            $title   = (string)($p['title'] ?? '');
            $lcTitle = mb_strtolower($title);
            $onTopic = false;
            foreach (vestra_journal_photo_require() as $word)
                if (strpos($lcTitle, $word) !== false) { $onTopic = true; break; }
            if (!$onTopic) { $bump('off topic'); continue; }
            foreach (vestra_journal_photo_reject() as $bad)
                if (strpos($lcTitle, $bad) !== false) { $bump('subject not suitable'); continue 2; }

            $em = $ii['extmetadata'] ?? [];
            $license = $clean($em['LicenseShortName']['value'] ?? '');
            if (!$licOk($license)) { $bump('licence not usable commercially'); continue; }
            $artist = $clean($em['Artist']['value'] ?? '') ?: 'Wikimedia Commons';
            if (mb_strlen($artist) > 70) $artist = mb_substr($artist, 0, 70).'…';

            $src   = (string)($ii['thumburl'] ?? $ii['url'] ?? ''); if ($src === '') { $bump('no url'); continue; }
            $ext   = strtolower((string)pathinfo((string)parse_url($src, PHP_URL_PATH), PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) $ext = 'jpg';
            /* The 60-char cut is what keeps the name readable, but it also made ten
               different "Fashion time modeling photography in Tbilisi, Iranian model …"
               files collapse onto one path: nine downloads overwritten, and a credit
               line naming the wrong photograph. The title hash restores uniqueness
               without giving up the readable stem. */
            $stem  = substr(strtolower(trim(preg_replace('/[^a-z0-9]+/i','-', preg_replace('/^File:/','',$title)), '-')), 0, 52);
            $name  = trim($stem, '-').'-'.substr(dechex(crc32($title)), 0, 6).'.'.$ext;

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

/* What the photograph shows, for alt text — Commons' own description, cleaned of the
   markup and language wrappers it arrives wrapped in. Empty when the uploader wrote
   none, in which case the caller falls back rather than inventing a description. */
function vestra_journal_photo_desc(string $path): string {
    $c = vestra_journal_credits()[basename($path)] ?? null;
    $d = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string)($c['desc'] ?? '')), ENT_QUOTES, 'UTF-8')));
    return mb_strlen($d) > 140 ? mb_substr($d, 0, 137).'…' : $d;
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
 *  without touching any article the admin has since edited. Returns count changed.
 *
 *  $refresh republishes the shipped text over an article that already exists —
 *  body, excerpt and every translation. It is off by default and stays that way
 *  at both ordinary call sites, because the whole point of the normal path is
 *  that it cannot flatten something edited in the admin panel. It exists because
 *  without it, rewriting an article in journal_seed.json changes nothing on the
 *  site: the title still matches, so the record is skipped and the old body
 *  stays published. Translations are replaced rather than merged — once the
 *  English is rewritten, a translation of the previous version is no longer a
 *  translation of what is on the page. id, slug, published and created survive,
 *  so a refresh keeps the article's URL and does not silently unpublish it. */
function vestra_journal_seed_starters(bool $refresh = false): int {
    $all = vestra_read_json('journal.json');
    $byTitle = [];
    foreach ($all as $idx => $p) $byTitle[strtolower(trim((string)($p['title'] ?? '')))] = $idx;
    $changed = 0;
    foreach (vestra_journal_starters() as $s) {
        $key = strtolower(trim($s['title']));
        if (isset($byTitle[$key])) {
            $idx = $byTitle[$key];
            if ($refresh) {
                $was = $all[$idx];
                foreach (['body', 'excerpt', 'category', 'author'] as $f)
                    if (isset($s[$f]) && $s[$f] !== '') $all[$idx][$f] = $s[$f];
                $all[$idx]['i18n'] = $s['i18n'] ?? [];
                /* Only count and re-stamp a record that actually moved, so running a
                   refresh twice reports 0 the second time instead of claiming it
                   rewrote everything again. */
                if ($all[$idx] !== $was) { $all[$idx]['updated'] = date('c'); $changed++; }
                continue;
            }
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
