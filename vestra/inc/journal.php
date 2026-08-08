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
