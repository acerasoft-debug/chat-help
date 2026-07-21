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
/** Safe plain-text → HTML: escape, blank lines become paragraphs, single newlines <br>. */
function vestra_journal_body_html(string $body): string {
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $out = '';
    foreach (preg_split('/\n{2,}/', trim($body)) as $b) {
        $b = trim($b);
        if ($b !== '') $out .= '<p>'.nl2br(htmlspecialchars($b)).'</p>';
    }
    return $out;
}
function vestra_journal_reading_min(string $body): int {
    return max(1, (int)ceil(str_word_count(strip_tags($body)) / 200));
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
            if (empty($all[$idx]['i18n']) && !empty($s['i18n'])) { // back-fill translations onto an English-only starter
                $all[$idx]['i18n']    = $s['i18n'];
                $all[$idx]['updated'] = date('c');
                $changed++;
            }
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
