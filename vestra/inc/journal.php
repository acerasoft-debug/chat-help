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

/** Built-in starter articles — loaded into data/journal.json on admin request. */
function vestra_journal_starters(): array {
    $now = date('c');
    return [
        [
            'title'    => 'Sourcing Authentic Branded Stock in 2026: A Buyer\'s Checklist',
            'category' => 'Market & Prices',
            'author'   => 'VESTRA Editorial',
            'cover'    => '',
            'excerpt'  => 'Counterfeits and non-delivery are the two biggest risks in branded wholesale. Here is how professional buyers de-risk every order.',
            'body'     => "The branded wholesale market moves fast, and the same speed that lets you land a great parcel of stock can also expose you to two familiar risks: goods that never arrive, and goods that are not what they claimed to be.\n\nStart with the paperwork. A serious seller can show provenance — invoices up the chain, or at minimum a clear statement of where the stock came from and a willingness to provide proof on request. Vague answers are a red flag.\n\nInspect the size ratio, not just the headline quantity. A \"pack of 8\" that is really 6 XL and 2 S is very different to a balanced run. Reputable listings state the ratio up front.\n\nFinally, protect the money. Paying a new supplier by open bank transfer means trusting a stranger with your cash before you have seen a single carton. Escrow flips that: the funds are held and only released to the seller once you confirm delivery — so a deal that goes wrong is recoverable, not written off.\n\nOn VESTRA every seller is verified before they can trade, and escrow-protected card payment is built into checkout. The result is a market where you can move quickly without moving carelessly.",
            'published' => true, 'created' => $now, 'updated' => $now,
        ],
        [
            'title'    => 'The Enduring Business of the Piqué Polo',
            'category' => 'Wholesale Trends',
            'author'   => 'VESTRA Editorial',
            'cover'    => '',
            'excerpt'  => 'Few garments sell as reliably at wholesale as the cotton piqué polo. We look at why it keeps turning over — season after season.',
            'body'     => "Trends come and go, but a handful of garments simply keep selling. The cotton piqué polo is one of them: seasonless, gender-broad, and instantly recognisable across price tiers from the high street to heritage houses.\n\nFor a buyer, that reliability is the whole point. A polo does not date the way a printed tee or a cut-of-the-moment jacket can, which means unsold stock carries far less markdown risk. It also travels across markets — the same core colours work in Germany, France, Italy and beyond.\n\nThe commercial art is in the assortment. The strongest sellers weight the middle of the size curve (M–XL) and lead with core colours — black, white, navy — before adding a few seasonal accents. Mixed-colour cartons let a retailer test breadth without committing to full packs of a single shade.\n\nAs a category to build a wholesale position around, the piqué polo remains hard to beat: dependable demand, broad appeal, and margins that hold.",
            'published' => true, 'created' => $now, 'updated' => $now,
        ],
        [
            'title'    => 'How to Read a Wholesale Line Sheet Like a Buyer',
            'category' => 'Brand News',
            'author'   => 'VESTRA Editorial',
            'cover'    => '',
            'excerpt'  => 'A line sheet is where a deal is really made or lost. Here is what experienced buyers look at first.',
            'body'     => "A line sheet looks simple — photos, prices, minimums — but experienced buyers read it in a specific order, and it saves them money.\n\nFirst, the minimum order quantity and pack structure. MOQ tells you the size of the commitment; the pack step tells you how the stock actually ships. A low unit price behind a huge MOQ is not a bargain if it ties up your cash.\n\nSecond, the price breaks. Tiered pricing rewards volume — knowing where the next break sits lets you decide whether nudging your order up a tier is worth it.\n\nThird, condition and origin. \"A1\" or first-quality is not the same as graded or returned stock, and EEA origin matters for both duty and authenticity. A good line sheet states both plainly.\n\nOnly then do the photos matter. Great imagery sells, but the numbers decide whether the deal works for your business. On VESTRA, every listing surfaces MOQ, pack size, tiers and condition up front — so you can read the deal in seconds and message the seller with the right question.",
            'published' => true, 'created' => $now, 'updated' => $now,
        ],
    ];
}
/** Load the starter articles into data/journal.json (skips titles that already exist). Returns count added. */
function vestra_journal_seed_starters(): int {
    $all = vestra_read_json('journal.json');
    $have = array_map(fn($p) => strtolower(trim((string)($p['title'] ?? ''))), $all);
    $added = 0;
    foreach (vestra_journal_starters() as $s) {
        if (in_array(strtolower(trim($s['title'])), $have, true)) continue;
        $s['id'] = 'jr_'.bin2hex(random_bytes(5));
        $s['slug'] = vestra_journal_slug($s['title']);
        $all[] = $s; $added++;
        $have[] = strtolower(trim($s['title']));
        // re-read-safe: keep $all local, write once at the end
    }
    if ($added) vestra_write_json('journal.json', $all);
    return $added;
}
