<?php
/**
 * VESTRA Journal — public magazine section.
 * ?slug=…  → single article.   otherwise → grid (optional ?cat=… filter).
 */
require_once __DIR__.'/inc/i18n.php';
require_once __DIR__.'/inc/journal.php';

/* Never show an empty magazine: if nothing has been published yet but the
   built-in starter articles ship with the code, seed them on first view.
   Idempotent — once data/journal.json holds published pieces this does nothing,
   and it silently no-ops if data/ is not writable (page still renders). */
if (function_exists('vestra_journal_seed_starters') && !vestra_journal_published()) {
    @vestra_journal_seed_starters();
}

$slug = trim($_GET['slug'] ?? '');
$cat  = trim($_GET['cat'] ?? '');

$fmtDate = fn($iso) => $iso ? date('j M Y', strtotime($iso)) : '';

/* ─────────────────────────────  SINGLE ARTICLE  ───────────────────────────── */
if ($slug !== '') {
    $art = vestra_journal_find($slug);
    if (!$art || empty($art['published'])) { http_response_code(404); $slug=''; }
    else {
        $lang = vlang();
        $art  = vestra_journal_localize($art, $lang);
        $PAGE = $art['title'];
        $META = mb_substr(trim($art['excerpt'] ?: strip_tags($art['body'] ?? '')), 0, 180);
        $NAV  = 'journal';
        require __DIR__.'/inc/head.php';
        $more = array_values(array_filter(vestra_journal_published(), fn($p) => ($p['id'] ?? '') !== ($art['id'] ?? '')));
        $more = array_map(fn($p) => vestra_journal_localize($p, $lang), array_slice($more, 0, 3));
        ?>
        <style><?= vestra_journal_css() ?></style>
        <article class="jr-article">
          <div class="jr-crumbs"><a href="/">Home</a> · <a href="/journal"><?= t('Journal') ?></a> · <span><?= htmlspecialchars(t($art['category'] ?? '')) ?></span></div>
          <div class="jr-cat"><?= htmlspecialchars(t($art['category'] ?? '')) ?></div>
          <h1 class="jr-title"><?= htmlspecialchars($art['title'] ?? '') ?></h1>
          <div class="jr-meta"><?= htmlspecialchars($art['author'] ?? 'VESTRA Editorial') ?> · <?= $fmtDate($art['created'] ?? '') ?> · <?= vestra_journal_reading_min($art['body'] ?? '') ?> <?= t('min read') ?></div>
          <div class="jr-hero-img" role="img" aria-label="<?= htmlspecialchars($art['title'] ?? '') ?>" style="background-image:<?= vestra_journal_cover_bg($art) ?>"></div>
          <div class="jr-body"><?= vestra_journal_body_html($art['body'] ?? '') ?></div>
          <div class="jr-share">
            <a class="btn btn-o" href="/journal">← <?= t('All articles') ?></a>
            <a class="btn btn-p" href="/shop"><?= t('Browse the catalogue') ?> →</a>
          </div>
        </article>

        <?php if ($more): ?>
        <div class="jr-wrap">
          <h3 class="jr-more-h"><?= t('More from the Journal') ?></h3>
          <div class="jr-grid">
            <?php foreach ($more as $p): ?>
            <a class="jr-card" href="/journal?slug=<?= urlencode($p['slug'] ?? '') ?>">
              <div class="jr-thumb" style="background-image:<?= vestra_journal_cover_bg($p) ?>"><span class="jr-badge"><?= htmlspecialchars(t($p['category'] ?? '')) ?></span></div>
              <div class="jr-cbody"><h4><?= htmlspecialchars($p['title'] ?? '') ?></h4><p><?= htmlspecialchars(mb_substr($p['excerpt'] ?? '', 0, 110)) ?></p></div>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif;
        require __DIR__.'/inc/foot.php';
        exit;
    }
}

/* ─────────────────────────────  ARTICLE GRID  ─────────────────────────────── */
$PAGE = t('Journal'); $NAV = 'journal';
$META = t('Fashion, brand and wholesale-market news from VESTRA — updated regularly.');
require __DIR__.'/inc/head.php';

$all = vestra_journal_published();
if ($cat !== '') $all = array_values(array_filter($all, fn($p) => ($p['category'] ?? '') === $cat));
$lang = vlang();
$all = array_map(fn($p) => vestra_journal_localize($p, $lang), $all);
$featured = $all[0] ?? null;
$rest = $featured ? array_slice($all, 1) : [];
?>
<style><?= vestra_journal_css() ?></style>
<div class="jr-wrap">
  <div class="jr-hero">
    <div class="jr-eyebrow"><?= t('VESTRA Journal') ?></div>
    <h1><?= t('The pulse of branded wholesale') ?></h1>
    <p><?= t('Fashion, brands, market moves and the trade behind them — insight for buyers and sellers who move fast.') ?></p>
  </div>

  <div class="jr-cats">
    <a href="/journal" class="<?= $cat===''?'on':'' ?>"><?= t('All') ?></a>
    <?php foreach (VESTRA_JOURNAL_CATS as $c): ?>
      <a href="/journal?cat=<?= urlencode($c) ?>" class="<?= $cat===$c?'on':'' ?>"><?= htmlspecialchars(t($c)) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (!$all): ?>
    <div class="jr-empty"><?= t('No articles yet — check back soon.') ?></div>
  <?php else: ?>
    <?php if ($featured): ?>
    <a class="jr-feature" href="/journal?slug=<?= urlencode($featured['slug'] ?? '') ?>">
      <div class="jr-feature-img" style="background-image:<?= vestra_journal_cover_bg($featured) ?>"></div>
      <div class="jr-feature-txt">
        <span class="jr-badge gold"><?= htmlspecialchars(t($featured['category'] ?? '')) ?></span>
        <h2><?= htmlspecialchars($featured['title'] ?? '') ?></h2>
        <p><?= htmlspecialchars(mb_substr($featured['excerpt'] ?? '', 0, 200)) ?></p>
        <div class="jr-meta"><?= htmlspecialchars($featured['author'] ?? 'VESTRA Editorial') ?> · <?= $fmtDate($featured['created'] ?? '') ?></div>
      </div>
    </a>
    <?php endif; ?>

    <?php if ($rest): ?>
    <div class="jr-grid">
      <?php foreach ($rest as $p): ?>
      <a class="jr-card" href="/journal?slug=<?= urlencode($p['slug'] ?? '') ?>">
        <div class="jr-thumb" style="background-image:<?= vestra_journal_cover_bg($p) ?>"><span class="jr-badge"><?= htmlspecialchars(t($p['category'] ?? '')) ?></span></div>
        <div class="jr-cbody">
          <h4><?= htmlspecialchars($p['title'] ?? '') ?></h4>
          <p><?= htmlspecialchars(mb_substr($p['excerpt'] ?? '', 0, 120)) ?></p>
          <div class="jr-meta"><?= $fmtDate($p['created'] ?? '') ?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php require __DIR__.'/inc/foot.php';

/* Scoped premium styling (shared between grid and article views). */
function vestra_journal_css(): string { return <<<CSS
/* Light editorial theme — dark header/footer stay, the Journal reads as a clean magazine */
body{background:#f5f3ef}
/* The site footer sits on the light body here, so its default light text/border would
   wash out — give it a warm dark band so the bottom links stay clearly readable. */
footer{background:#14110c;border-top-color:#2a241a;color:#c3bbac}
footer a{color:#d8bd86}
footer a:hover{color:#ffffff}
.jr-wrap,.jr-article{--bg2:#ffffff;--ink:#1b1813;--mut:#6f695f;--line:#e8e2d7;color:#1b1813}
.jr-wrap{max-width:1080px;margin:0 auto;padding:0 20px 72px;background:#f5f3ef}
.jr-hero{text-align:center;padding:52px 20px 30px;border-bottom:1px solid var(--line);margin-bottom:26px}
.jr-hero .jr-eyebrow{font-size:12px;letter-spacing:2.5px;text-transform:uppercase;color:var(--acc);font-weight:600;margin-bottom:12px}
.jr-hero h1{font-family:'Playfair Display',serif;font-size:clamp(28px,4vw,44px);font-weight:700;margin:0 0 10px;line-height:1.08}
.jr-hero p{color:var(--mut);font-size:16px;max-width:600px;margin:0 auto;line-height:1.6}
.jr-cats{display:flex;gap:8px;flex-wrap:wrap;justify-content:center;margin-bottom:30px}
.jr-cats a{font-size:13px;padding:7px 15px;border:1px solid var(--line);border-radius:999px;color:var(--mut);transition:.2s}
.jr-cats a:hover{color:var(--ink);border-color:var(--acc)}
.jr-cats a.on{background:var(--acc);color:#1a1205;border-color:var(--acc);font-weight:600}
.jr-empty{text-align:center;color:var(--mut);padding:60px 20px}
.jr-feature{display:grid;grid-template-columns:1.15fr 1fr;gap:0;border:1px solid var(--line);border-radius:20px;overflow:hidden;margin-bottom:30px;text-decoration:none;color:inherit;transition:.22s;background:var(--bg2)}
.jr-feature:hover{border-color:var(--acc);transform:translateY(-3px);box-shadow:0 24px 50px -24px rgba(60,50,30,.16)}
.jr-feature-img{min-height:300px;background:linear-gradient(135deg,#ece7df,#ddd6c8);background-size:cover;background-position:center}
.jr-feature-txt{padding:34px;display:flex;flex-direction:column;justify-content:center;gap:12px}
.jr-feature-txt h2{font-family:'Playfair Display',serif;font-size:26px;line-height:1.2;margin:0}
.jr-feature-txt p{color:var(--mut);font-size:14.5px;line-height:1.6;margin:0}
.jr-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}
.jr-card{border:1px solid var(--line);border-radius:16px;overflow:hidden;text-decoration:none;color:inherit;transition:.22s;background:var(--bg2);display:flex;flex-direction:column}
.jr-card:hover{border-color:var(--acc);transform:translateY(-3px);box-shadow:0 20px 44px -24px rgba(60,50,30,.16)}
.jr-thumb{aspect-ratio:16/10;background:linear-gradient(135deg,#ece7df,#ddd6c8);background-size:cover;background-position:center;position:relative}
.jr-cbody{padding:16px 18px 18px;display:flex;flex-direction:column;gap:7px;flex:1}
.jr-cbody h4{font-size:16px;font-weight:700;margin:0;line-height:1.3}
.jr-cbody p{color:var(--mut);font-size:13px;line-height:1.5;margin:0;flex:1}
.jr-badge{display:inline-block;font-size:11px;font-weight:600;letter-spacing:.4px;padding:4px 9px;border-radius:6px;background:rgba(0,0,0,.55);color:#fff;position:absolute;top:12px;left:12px;backdrop-filter:blur(4px)}
.jr-badge.gold{position:static;background:rgba(176,125,42,.14);color:#8a6420;align-self:flex-start}
.jr-card,.jr-feature{box-shadow:0 1px 3px rgba(60,50,30,.06)}
.jr-meta{font-size:12px;color:var(--mut)}
/* article */
.jr-article{max-width:760px;margin:0 auto;padding:34px 20px 10px}
.jr-crumbs{font-size:12.5px;color:var(--mut);margin-bottom:20px}
.jr-crumbs a{color:var(--acc)}
.jr-article .jr-cat{font-size:12px;letter-spacing:1.5px;text-transform:uppercase;color:var(--acc);font-weight:600;margin-bottom:10px}
.jr-title{font-family:'Playfair Display',serif;font-size:clamp(26px,4vw,40px);line-height:1.15;margin:0 0 14px}
.jr-article .jr-meta{margin-bottom:22px}
.jr-cover{width:100%;border-radius:16px;margin-bottom:26px;border:1px solid var(--line)}
.jr-hero-img{width:100%;aspect-ratio:21/9;border-radius:16px;margin-bottom:26px;border:1px solid var(--line);background-size:cover;background-position:center}
.jr-body{font-size:17px;line-height:1.75;color:var(--ink)}
.jr-body p{margin:0 0 20px}
/* Inline article figures — see the [img:…] construct in vestra_journal_body_html(). */
.jr-fig{margin:30px 0;padding:0}
.jr-fig img{width:100%;display:block;border-radius:14px;border:1px solid var(--line);background:#f4f1ec}
.jr-fig figcaption{margin-top:10px;font-size:13.5px;line-height:1.5;color:var(--mut);text-align:center}
.jr-share{display:flex;gap:10px;flex-wrap:wrap;margin:36px 0 8px;padding-top:24px;border-top:1px solid var(--line)}
.jr-more-h{font-family:'Playfair Display',serif;font-size:22px;margin:44px 0 18px}
@media(max-width:820px){ .jr-grid{grid-template-columns:1fr 1fr} .jr-feature{grid-template-columns:1fr} .jr-feature-img{min-height:200px} }
@media(max-width:560px){ .jr-grid{grid-template-columns:1fr} }
CSS;
}
