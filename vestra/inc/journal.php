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
function vestra_journal_body_html(string $body): string {
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $out = '';
    foreach (preg_split('/\n{2,}/', trim($body)) as $b) {
        $b = trim($b);
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
function vestra_journal_reading_min(string $body): int {
    return max(1, (int)ceil(str_word_count(strip_tags($body)) / 200));
}
/* Deterministic editorial cover art per article — a category-themed gradient with
   concentric rings, thin diagonals and a faint serif "V" monogram. Self-contained
   (no external images), so covers always render and stay on-brand. */
function vestra_journal_cover_svg(array $p): string {
    $themes = [
        'Brand News'       => ['#241a12', '#4a3623', '#caa465'], // espresso + gold
        'Market & Prices'  => ['#12332a', '#1f5340', '#79c0a1'], // deep green + mint
        'Wholesale Trends' => ['#4f2a1a', '#8a4a2c', '#eac59a'], // terracotta + cream
        'Style Edit'       => ['#3f1d2b', '#78334a', '#e2a6ba'], // burgundy + blush
    ];
    $cat = (string)($p['category'] ?? 'Brand News');
    [$c1, $c2, $acc] = $themes[$cat] ?? $themes['Brand News'];
    $h   = crc32(($p['slug'] ?? '').'|'.($p['title'] ?? ''));       // stable per-article seed
    $cx  = 150 + ($h % 520);
    $cy  = 70  + (intdiv($h, 7)  % 360);
    $r1  = 165 + (intdiv($h, 13) % 120);
    $r2  = $r1 - 60;
    $r3  = max(28, $r1 - 116);
    $la  = 150 + (intdiv($h, 17) % 240);
    $lb  = $la - 130; $lc = $la + 76; $ld = $la - 44;
    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 520" preserveAspectRatio="xMidYMid slice">
<defs>
<linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="$c1"/><stop offset="1" stop-color="$c2"/></linearGradient>
<radialGradient id="vig" cx="0.5" cy="0.42" r="0.85"><stop offset="0.55" stop-color="#000000" stop-opacity="0"/><stop offset="1" stop-color="#000000" stop-opacity="0.3"/></radialGradient>
</defs>
<rect width="800" height="520" fill="url(#bg)"/>
<g fill="none" stroke="$acc" stroke-opacity="0.22"><circle cx="$cx" cy="$cy" r="$r1" stroke-width="1.4"/><circle cx="$cx" cy="$cy" r="$r2" stroke-width="1"/></g>
<circle cx="$cx" cy="$cy" r="$r3" fill="$acc" fill-opacity="0.1"/>
<g stroke="$acc" stroke-opacity="0.16" stroke-width="1"><line x1="0" y1="$la" x2="800" y2="$lb"/><line x1="0" y1="$lc" x2="800" y2="$ld"/></g>
<text x="40" y="480" font-family="Playfair Display, Georgia, serif" font-size="360" font-weight="700" fill="#ffffff" fill-opacity="0.05">V</text>
<rect width="800" height="520" fill="url(#vig)"/>
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
    static $map = [
        // 'article-slug' => '/uploads/journal/whatever.jpg',
    ];
    return $map[(string)($p['slug'] ?? '')] ?? '';
}
/* Full CSS background-image value for a cover: the real photo (admin cover, or a
   keyword model photo) layered OVER the generated editorial SVG data-URI, so if
   the photo fails to load the art shows through instead of a blank/broken block. */
function vestra_journal_cover_bg(array $p): string {
    $svg = "url('data:image/svg+xml;base64,".base64_encode(vestra_journal_cover_svg($p))."')";
    if (!empty($p['cover'])) return "url('".$p['cover']."'), ".$svg;
    $model = vestra_journal_model_photo($p);
    if ($model !== '')       return "url('".$model."'), ".$svg;
    return $svg;
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
