<?php
require __DIR__.'/inc/products.php';
$p = vestra_find($_GET['id'] ?? '');
if(!$p){ http_response_code(404); $PAGE=t('Not found'); $NOINDEX=true; require __DIR__.'/inc/head.php';
  echo '<div class="wrap"><div class="empty">'.t('Product not found.').' <a class="acc" href="/shop">'.t('Back to catalog').'</a></div></div>';
  require __DIR__.'/inc/foot.php'; exit; }

$PAGE = trim(($p['brand'] ?? '').' '.($p['name'] ?? '')) ?: ($p['name'] ?? 'Product');
$_pcat = $p['cat'] ?? 'fashion'; $_pmoq = (int)($p['moq'] ?? 0); $_punit = $p['unit'] ?? 'pc';
$META = sprintf(t('%s — wholesale %s. %sVerified B2B supplier on VESTRA — invoice-based ordering across Europe.'),
        $PAGE, $_pcat, $_pmoq ? "MOQ {$_pmoq} {$_punit}. " : '');
$_purl = 'https://vestrasales.com/product?id='.rawurlencode($p['id'] ?? '');
$_pimgs = [];
foreach ((!empty($p['images'])&&is_array($p['images']) ? $p['images'] : (vestra_primary_image($p)?[vestra_primary_image($p)]:[])) as $im) {
  if ($im === '') continue;
  $_pimgs[] = (strncmp($im,'http',4)===0) ? $im : 'https://vestrasales.com'.$im;
}
if ($_pimgs) $OG_IMAGE = $_pimgs[0];   // product photo as the social/preview image (not the generic logo)
$_prod = [
  '@context'=>'https://schema.org', '@type'=>'Product',
  'name'=>$PAGE,
  'brand'=>['@type'=>'Brand','name'=>$p['brand'] ?? 'VESTRA'],
  'category'=>$_pcat,
  'sku'=>$p['sku'] ?? ($p['id'] ?? ''),
  'description'=>$META,
  'url'=>$_purl,
];
if ($_pimgs) $_prod['image'] = $_pimgs;
$JSONLD = [
  $_prod,
  ['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>[
    ['@type'=>'ListItem','position'=>1,'name'=>'Home','item'=>'https://vestrasales.com/'],
    ['@type'=>'ListItem','position'=>2,'name'=>'Catalog','item'=>'https://vestrasales.com/shop'],
    ['@type'=>'ListItem','position'=>3,'name'=>$PAGE,'item'=>$_purl],
  ]],
];
$NAV='shop'; require __DIR__.'/inc/head.php';
$mode=$p['mode']; $from=vestra_from_price($p); $disc=vestra_discount($p);
$offered=isset($_GET['offered']);
$images = !empty($p['images'])&&is_array($p['images']) ? $p['images'] : (vestra_primary_image($p)?[vestra_primary_image($p)]:[]);
$photosLocked = !$MEMBER && $images;   // photos require being signed in (any status) — not full KYB approval
if(!$MEMBER) $images = [];
/* Where the locked-photos CTA sends the viewer: guests sign in; signed-in-but-unverified
   accounts go straight to their verification tab. */
$verifyHref = !$AUTH_USER
    ? '/login?back='.urlencode('/product?id='.$p['id'])
    : ((($AUTH_USER['type'] ?? '') === 'seller') ? '/seller?tab=kyc' : '/buyer?tab=kyc');

/* Carton/lot listings (e.g. Lacoste & Ralph Lauren polos: min_colors + size_step) get a
   per-colour quantity picker — 0/8/16/24… per colour — instead of plain checkboxes, so the
   buyer builds their own colour mix directly (matches how these ship: cartons per colourway). */
$cqMode = !empty($p['colors']) && !empty($p['min_colors']) && (int)($p['size_step'] ?? 0) > 1;
function vestra_colorqty_picker(array $p, string $idSuffix): string {
    $step = (int)($p['size_step'] ?? 1);
    $pal  = vestra_colors();
    $h = '<div class="colorqty" id="cq-'.$idSuffix.'">';
    foreach ((array)$p['colors'] as $cn) {
        $h .= '<div class="cqrow"><span class="cdot" style="background:'.($pal[$cn] ?? '#666').'"></span>'
            . '<span class="cqname">'.htmlspecialchars(t($cn)).'</span>'
            . '<select name="cq['.htmlspecialchars($cn).']" data-color="'.htmlspecialchars($cn).'" onchange="cqSync(\''.$idSuffix.'\')">';
        for ($k = 0; $k <= 8; $k++) { $v = $k * $step; $h .= '<option value="'.$v.'">'.$v.'</option>'; }
        $h .= '</select></div>';
    }
    return $h.'</div>';
}
?>
<div class="wrap">
  <div class="crumbs" style="margin-top:24px">
    <a href="/"><?= t('Home') ?></a> · <a href="/shop"><?= t('Catalog') ?></a> · <?= htmlspecialchars($p['brand']) ?>
  </div>

<?php if(!$MEMBER): ?>
  <!-- Unregistered visitors get the product rendered but unreadable: the whole detail block
       is blurred behind a lock panel. The markup is deliberately still emitted rather than
       withheld, because a search-engine crawler arrives as an unregistered visitor too --
       stripping the text server-side would empty every product page out of the index and
       undo the SEO work. Blur is a display treatment; the page still carries its content,
       its structured data and its meta description for crawlers. -->
  <div class="lockwrap">
    <div class="lockveil" aria-hidden="true"></div>
    <div class="lockpanel">
      <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="var(--acc)" stroke-width="1.5"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
      <h3><?= t('Verified buyers only') ?></h3>
      <p><?= t('Register free to see this product, its photos and trade pricing.') ?></p>
      <div class="lockbtns">
        <a class="btn btn-p" href="/register"><?= t('Register free') ?></a>
        <a class="btn btn-o" href="/login?back=<?= urlencode('/product?id='.$p['id']) ?>"><?= t('Sign in') ?></a>
      </div>
    </div>
    <div class="lockblur" inert>
<?php endif; ?>

  <div class="pdetail">
    <!-- ── Gallery ────────────────────────────────────────────────────────── -->
    <div class="gal-col">
      <div class="gal-main" id="gal-wrap">
        <!-- Approved viewers open on the product photo; the brand card is the LAST
             slide. Non-approved viewers have no photos, so the card is all they see. -->
        <div class="gal-placeholder" id="gal-card" style="background:linear-gradient(135deg,<?= htmlspecialchars(vestra_accent($p)) ?>,#0e0e11);flex-direction:column;gap:14px<?= $images ? ';display:none' : '' ?>">
          <?php echo vestra_brand_card($p['brand']); ?>
          <?php if($photosLocked): ?>
            <a href="<?= htmlspecialchars($verifyHref) ?>" style="display:inline-flex;align-items:center;gap:7px;font-size:12.5px;font-weight:600;color:#fff;background:rgba(14,14,17,.55);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,.22);padding:7px 14px;border-radius:999px;position:relative;z-index:3">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
              <?= $AUTH_USER ? t('Complete verification to view photos') : t('Sign in to view product photos') ?>
            </a>
          <?php endif; ?>
        </div>
        <?php if($images): ?>
          <img class="gal-img" id="gal-main-img" src="<?= htmlspecialchars($images[0]) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
          <button class="gal-nav prev" onclick="galGo(-1)" aria-label="Previous"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg></button>
          <button class="gal-nav next" onclick="galGo(1)" aria-label="Next"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg></button>
        <?php endif; ?>
        <?php if($mode==='sale'): ?><span class="modetag sale">SALE −<?= $disc ?>%</span>
        <?php elseif($mode==='offer'): ?><span class="modetag offer"><?= t('Open to offers') ?></span><?php endif; ?>
        <?php if(!empty($p['verified'])): ?><span class="gal-vbadge"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg> <?= t('Verified seller') ?></span><?php endif; ?>
      </div>
      <?php if($images && $MEMBER): ?>
      <!-- Premium zoom katmanlari. Bir toptanci kumasin dokusunu, dikisi ve baski
           kalitesini gormek ister; kucuk bir kart fotografi bunu gostermez. Masaustunde
           imlecin altindaki bolge sagdaki panelde orijinal cozunurlukte acilir,
           dokunmatikte tam ekran pinch-zoom. Kaynak HER ZAMAN orijinal dosya.
           Sadece uyelere: fotograflar zaten freischaltung ile kapali. -->
      <div class="vzoom-lens" id="vzoomLens" aria-hidden="true"></div>
      <div class="vzoom-panel" id="vzoomPanel" aria-hidden="true"><div class="vzoom-surface" id="vzoomSurface"></div><span class="vzoom-badge" id="vzoomBadge">1:1</span></div>
      <div class="vzoom-full" id="vzoomFull" role="dialog" aria-modal="true" aria-label="<?= htmlspecialchars(t('Zoom')) ?>">
        <button class="vzoom-close" id="vzoomClose" aria-label="<?= htmlspecialchars(t('Close')) ?>">&times;</button>
        <div class="vzoom-stage" id="vzoomStage"><img id="vzoomFullImg" alt="<?= htmlspecialchars($p['name']) ?>" draggable="false"></div>
        <div class="vzoom-bar"><b id="vzoomPct">100%</b><i id="vzoomTip"><?= htmlspecialchars(t('Double-tap or pinch to zoom · drag to pan')) ?></i></div>
      </div>
      <?php endif; ?>
      <?php if($images): ?>
      <div class="gal-thumbs" id="gal-thumbs">
        <?php foreach($images as $i=>$img): ?>
          <button class="gal-thumb <?= $i===0?'active':'' ?>" onclick="galSet(<?= $i ?>)">
            <img src="<?= htmlspecialchars($img) ?>" alt="" loading="lazy">
          </button>
        <?php endforeach; ?>
        <button class="gal-thumb" onclick="galSet(<?= count($images) ?>)" title="<?= htmlspecialchars($p['brand']) ?>">
          <span style="display:block;width:100%;height:100%;background:linear-gradient(135deg,<?= htmlspecialchars(vestra_accent($p)) ?>,#0e0e11)"></span>
        </button>
      </div>
      <?php endif; ?>
    </div>

    <!-- ── Product info ───────────────────────────────────────────────────── -->
    <div class="pinfo">
      <span class="acc" style="font-size:12px;letter-spacing:1.5px;text-transform:uppercase;font-weight:700"><?= htmlspecialchars($p['brand']) ?></span>
      <h1 style="margin:6px 0 10px"><?= htmlspecialchars($p['name']) ?></h1>
      <?php if(!empty($p['desc'])): ?>
        <p style="color:var(--mut);margin:0 0 18px;line-height:1.65"><?= htmlspecialchars($p['desc']) ?></p>
      <?php endif; ?>

      <div class="spec-grid">
        <div class="spec-row"><span><?= t('SKU') ?></span><b><?= htmlspecialchars($p['sku']) ?></b></div>
        <div class="spec-row"><span><?= t('Category') ?></span><b><?= htmlspecialchars($p['cat']) ?></b></div>
        <div class="spec-row"><span><?= t('Min. order (MOQ)') ?></span><b><?= $p['moq'] ?> <?= htmlspecialchars($p['unit']) ?></b></div>
        <?php if(!empty($p['sizes'])): ?><div class="spec-row"><span><?= t('Size mix') ?></span><b><?= htmlspecialchars(vestra_sizes_label((string)$p['sizes'])) ?></b></div><?php endif; ?>
        <?php if(!empty($p['colors'])): ?><div class="spec-row"><span><?= t('Colours') ?></span><b style="display:flex;justify-content:flex-end"><?= vestra_color_dots((array)$p['colors'], 13) ?></b></div><?php endif; ?>
        <?php if(!empty($p['seller']) && empty($p['hide_seller'])): ?><div class="spec-row"><span><?= t('Seller') ?></span><b><?php
          // Seller identity is approval-gated: unverified viewers only ever see a masked name.
          if ($APPROVED) {
            echo htmlspecialchars($p['seller']);
            echo !empty($p['verified']) ? ' · '.t('Verified business') : '';
            echo !empty($p['seller_uid']) ? ' · <a class="acc" href="/showroom?id='.urlencode($p['seller_uid']).'">'.t('Showroom →').'</a>' : '';
          } else {
            echo htmlspecialchars(vestra_mask_seller($p['seller'])).' · '.t('Verified business');
            echo ' <a class="acc" href="'.htmlspecialchars($verifyHref).'" style="font-size:11px">🔒 '.($AUTH_USER ? t('Verify to see the name') : t('Sign in to see the name')).'</a>';
          }
        ?></b></div>
        <?php elseif(!empty($p['verified'])): ?><div class="spec-row"><span><?= t('Seller') ?></span><b><?= t('Verified business') ?> · <?= t('via VESTRA') ?></b></div><?php endif; ?>
        <?php if(!empty($p['origin'])): ?><div class="spec-row"><span><?= t('Origin / auth.') ?></span><b><?= htmlspecialchars($p['origin']) ?></b></div><?php endif; ?>
      </div>

      <?php if(!empty($p['linesheet'])): ?>
        <?php if($APPROVED): ?>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin:14px 0">
          <?php if(!empty($p['sheet_file'])): ?>
          <a class="btn btn-o btn-sm" href="/linesheet?id=<?= urlencode($p['id']) ?>&fmt=pdf" target="_blank" rel="noopener">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12m0 0l-4-4m4 4l4-4"/><path d="M4 21h16"/></svg>
            <?= t('Line sheet (PDF)') ?>
          </a>
          <?php endif; ?>
          <a class="btn btn-o btn-sm" href="/linesheet?id=<?= urlencode($p['id']) ?>&fmt=xls">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 3v18"/></svg>
            <?= t('Line sheet (Excel)') ?>
          </a>
        </div>
        <?php else: ?>
        <div class="hint" style="margin:14px 0">🔒 <?= $AUTH_USER ? t('Complete verification to download the line sheet (PDF & Excel).') : t('Sign in to download the line sheet (PDF & Excel).') ?></div>
        <?php endif; ?>
      <?php elseif(!empty($p['sheet'])): ?>
        <a class="btn btn-o btn-sm" style="margin:14px 0" href="<?= htmlspecialchars($p['sheet']) ?>" target="_blank" rel="noopener">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12m0 0l-4-4m4 4l4-4"/><path d="M4 21h16"/></svg>
          <?= t('Line sheet') ?> (<?= strtoupper(htmlspecialchars(pathinfo($p['sheet'],PATHINFO_EXTENSION))) ?>)
        </a>
      <?php endif; ?>

      <?php if(!empty($p['group'])): $gp=vestra_group_pool($p['id']); if($gp): ?>
        <a href="/group?id=<?= urlencode($p['id']) ?>" class="banner info" style="display:block;margin:14px 0;text-decoration:none">
          🤝 <?= sprintf(t('Group buy: pool with others to unlock %s/%s — %d%% committed. Join →'), eur($gp['_gprice']), htmlspecialchars($p['unit']), $gp['_pct']) ?>
        </a>
      <?php endif; endif; ?>

      <?php if(!$MEMBER): ?>
        <div class="gate" style="margin-top:22px">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="var(--acc)" stroke-width="1.6" style="margin:0 auto 8px"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
          <h3 style="margin:0 0 6px"><?= t('Verified buyers only') ?></h3>
          <p style="color:var(--mut);margin:0 0 16px"><?= t('Sign in as a verified business buyer to see pricing') ?><?= $mode==='offer'?' '.t('and make an offer'):' '.t('and order') ?>.</p>
          <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
            <a class="btn btn-p" href="/login?back=/product?id=<?= urlencode($p['id']) ?>"><?= t('Sign in') ?></a>
            <a class="btn btn-o" href="/register"><?= t('Register free') ?></a>
          </div>
        </div>

      <?php elseif($offered): ?>
        <div class="banner ok" style="margin-top:18px">✓ <?= t('Your offer is in the queue (ref') ?> <b><?= htmlspecialchars(substr($_GET['ref']??'',0,16)) ?></b>). <?= t('The seller will respond — track it under') ?> <a href="/buyer?tab=offers" class="acc"><?= t('My offers') ?></a>.</div>
        <a class="btn btn-o" href="/shop"><?= t('Continue browsing') ?></a>

      <?php elseif($mode==='offer'): ?>
        <div class="banner info" style="margin-top:18px">💬 <?= t('This item is <b>open to offers</b>.') ?> <?= htmlspecialchars($p['guide']??'') ?></div>
        <table class="tiers">
          <thead><tr><th><?= t('Volume') ?></th><th><?= t('Indicative unit') ?></th></tr></thead>
          <tbody>
          <?php foreach($p['tiers'] as $i=>$tier): ?>
            <tr><td><?= $tier['min'] ?><?= isset($p['tiers'][$i+1])?'–'.($p['tiers'][$i+1]['min']-1):'+' ?> <?= htmlspecialchars($p['unit']) ?></td><td class="amt"><?= eur($tier['price']) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <div class="order-box">
          <?php if(isset($_GET['colerr'])): ?>
          <div class="banner" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.35);color:var(--bad);margin-bottom:10px">
            <?= vestra_colours_warn((int)($p['min_colors']??1)) ?></div>
          <?php endif; ?>
          <form method="post" action="/offer" onsubmit="return <?= $cqMode?'cqOk(this,\'main\')':'vcolOk(this)' ?>">
            <input type="hidden" name="id" value="<?= htmlspecialchars($p['id']) ?>">
            <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px">
            <?php if($cqMode): ?>
            <div style="margin-bottom:12px">
              <label class="hint"><?= t('Quantity per colour') ?> — <?= vestra_colours_phrase((int)$p['min_colors']) ?> · <?= sprintf(t('multiples of %d'), (int)$p['size_step']) ?></label>
              <?= vestra_colorqty_picker($p,'main') ?>
              <div class="warn" id="cqwarn-main" style="display:none;margin-top:8px"></div>
              <div class="hint" style="margin-top:6px"><?= t('Total quantity') ?>: <b><span id="cqtotal-main">0</span> <?= htmlspecialchars($p['unit']) ?></b></div>
            </div>
            <input type="hidden" name="qty" id="qty-main" value="0">
            <div style="max-width:280px">
              <div><label class="hint"><?= t('Your offer') ?> (€ / <?= htmlspecialchars($p['unit']) ?>)</label>
                <input type="number" name="price" step="0.01" min="0" placeholder="<?= htmlspecialchars(t('e.g. 95.00')) ?>" required style="width:100%"></div>
            </div>
            <?php else: ?>
            <?php if(!empty($p['colors']) && !empty($p['min_colors'])): ?>
            <div style="margin-bottom:12px"><label class="hint"><?= t('Choose your colours') ?> — <?= sprintf(t('at least %d'), (int)$p['min_colors']) ?></label>
              <div class="colorpick" data-min="<?= (int)$p['min_colors'] ?>">
                <?php $pal=vestra_colors(); foreach((array)$p['colors'] as $cn): ?>
                <label class="colorchip"><input type="checkbox" name="colors[]" value="<?= htmlspecialchars($cn) ?>"><span class="cdot" style="background:<?= $pal[$cn]??'#666' ?>"></span><?= htmlspecialchars(t($cn)) ?></label>
                <?php endforeach; ?>
              </div>
              <div class="warn vcolwarn" style="display:none;margin-top:8px"><?= vestra_colours_warn((int)$p['min_colors']) ?></div>
            </div>
            <?php endif; ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
              <div><label class="hint"><?= t('Quantity') ?> (<?= htmlspecialchars($p['unit']) ?>) — <?= t('min') ?> <?= $p['moq'] ?></label>
                <input type="number" name="qty" min="<?= $p['moq'] ?>" step="<?= (int)($p['size_step'] ?? 1) ?>" value="<?= $p['moq'] ?>" required style="width:100%"></div>
              <div><label class="hint"><?= t('Your offer') ?> (€ / <?= htmlspecialchars($p['unit']) ?>)</label>
                <input type="number" name="price" step="0.01" min="0" placeholder="<?= htmlspecialchars(t('e.g. 95.00')) ?>" required style="width:100%"></div>
            </div>
            <?php endif; ?>
            <div style="margin-top:12px"><label class="hint"><?= t('Message to seller') ?></label>
              <textarea name="message" rows="2" style="width:100%" placeholder="<?= htmlspecialchars(t('Sizes, delivery, terms…')) ?>"></textarea></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:12px">
              <div><label class="hint"><?= t('Company') ?> *</label><input name="company" required style="width:100%" value="<?= htmlspecialchars($AUTH_USER['company'] ?? '') ?>"></div>
              <div><label class="hint"><?= t('Work email') ?> *</label><input type="email" name="email" required style="width:100%" value="<?= htmlspecialchars($AUTH_USER['email'] ?? '') ?>"></div>
            </div>
            <button class="btn btn-p" type="submit" style="width:100%;justify-content:center;margin-top:16px"><?= t('Submit offer →') ?></button>
            <div class="hint" style="margin-top:10px"><?= t("Your offer joins the seller's queue. If accepted, you receive an <b>invoice</b> — payment by bank transfer.") ?></div>
            <?php if($AUTH_USER && ($AUTH_USER['type']??'')==='buyer' && !empty($p['seller_uid'])): ?>
            <div class="hint" style="margin-top:6px">💬 <?= t('Your offer will also appear in Messages, linked to this product.') ?></div>
            <?php endif; ?>
          </form>
        </div>

      <?php else: ?>
        <?php if($mode==='sale'): ?>
          <div class="saleline">
            <span class="was"><?= eur($p['list']) ?></span>
            <span class="now"><?= t('from') ?> <?= eur($from) ?></span>
            <span class="badge-sale">−<?= $disc ?>%</span>
            <span class="hint">/ <?= htmlspecialchars($p['unit']) ?> · <?= t('clearance') ?></span>
          </div>
        <?php endif; ?>
        <table class="tiers" id="tiers">
          <thead><tr><th><?= t('Quantity') ?> (<?= htmlspecialchars($p['unit']) ?>)</th><th><?= $mode==='sale'?t('Sale unit'):t('Unit price') ?></th><th><?= t('Saving') ?></th></tr></thead>
          <tbody>
          <?php foreach($p['tiers'] as $i=>$tier):
            $base=$mode==='sale'?$p['list']:$p['tiers'][0]['price'];
            $save=(int)round(100*($base-$tier['price'])/$base); ?>
            <tr data-min="<?= $tier['min'] ?>" data-price="<?= $tier['price'] ?>">
              <td><?= $tier['min'] ?><?= isset($p['tiers'][$i+1])?'–'.($p['tiers'][$i+1]['min']-1):'+' ?></td>
              <td class="amt"><?= eur($tier['price']) ?></td>
              <td><?= $save>0?'−'.$save.'%':'—' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <div class="order-box">
          <?php $colorQtyMode = !empty($p['colors']) && !empty($p['min_colors']) && (int)($p['size_step']??0) > 1; ?>
          <?php if($colorQtyMode): $cqStep=(int)$p['size_step']; ?>
          <div style="margin-bottom:14px">
            <label class="hint"><?= t('Quantity per colour') ?> — <?= vestra_colours_phrase((int)$p['min_colors']) ?> · <?= sprintf(t('multiples of %d'), $cqStep) ?></label>
            <div class="colorqty" id="ordColors">
              <?php $pal=vestra_colors(); foreach((array)$p['colors'] as $cn): ?>
              <div class="cqrow">
                <span class="cdot" style="background:<?= $pal[$cn]??'#666' ?>"></span>
                <span class="cqname"><?= htmlspecialchars(t($cn)) ?></span>
                <select data-color="<?= htmlspecialchars($cn) ?>" onchange="recalc()">
                  <?php for($k=0;$k<=8;$k++): $v=$k*$cqStep; ?><option value="<?= $v ?>"><?= $v ?></option><?php endfor; ?>
                </select>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <input id="qty" type="hidden" value="0">
          <div class="qtyrow">
            <span class="hint"><?= t('Total quantity') ?>: <b><span id="cqtotal">0</span> <?= htmlspecialchars($p['unit']) ?></b></span>
            <span class="hint"><?= t('Min order') ?> <b><?= $p['moq'] ?> <?= htmlspecialchars($p['unit']) ?></b></span>
          </div>
          <?php else: ?>
          <?php if(!empty($p['colors']) && !empty($p['min_colors'])): ?>
          <div style="margin-bottom:14px"><label class="hint"><?= t('Choose your colours') ?> — <?= sprintf(t('at least %d'), (int)$p['min_colors']) ?></label>
            <div class="colorpick" id="ordColors">
              <?php $pal=vestra_colors(); foreach((array)$p['colors'] as $cn): ?>
              <label class="colorchip"><input type="checkbox" value="<?= htmlspecialchars($cn) ?>" onchange="recalc()"><span class="cdot" style="background:<?= $pal[$cn]??'#666' ?>"></span><?= htmlspecialchars(t($cn)) ?></label>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
          <div class="qtyrow">
            <div class="stepper">
              <button type="button" onclick="bump(-step())">−</button>
              <input id="qty" type="number" min="<?= $p['moq'] ?>" step="<?= (int)($p['size_step'] ?? 1) ?>" value="<?= $p['moq'] ?>" oninput="recalc()">
              <button type="button" onclick="bump(step())">+</button>
            </div>
            <span class="hint"><?= t('Min order') ?> <b><?= $p['moq'] ?> <?= htmlspecialchars($p['unit']) ?></b></span>
          </div>
          <?php endif; ?>
          <div class="calc">
            <div class="unit"><?= t('Unit:') ?> <span id="uprice"><?= eur($from) ?></span> · <span id="tier"></span></div>
            <div class="total" id="total"><?= eur($from*$p['moq']) ?> <small><?= t('excl. taxes & shipping') ?></small></div>
          </div>
          <div id="warn" class="warn" style="display:none"></div>
          <button class="btn btn-p" id="addBtn" style="width:100%;justify-content:center" onclick="addToOrder()"><?= t('Add to order') ?></button>
          <div class="hint" style="margin-top:10px"><?= t('Payment is currently by <b>invoice</b> — you receive a proforma invoice and goods ship after bank-transfer payment.') ?></div>
        </div>
        <?php if(!empty($p['offers'])): ?>
        <div class="order-box" style="margin-top:14px">
          <div class="hint" style="margin-bottom:8px">💬 <?= t('This seller also accepts offers.') ?></div>
          <details class="offerdetails">
            <summary class="btn btn-o" style="width:100%;justify-content:center"><?= t('Make an offer') ?></summary>
            <form method="post" action="/offer" style="margin-top:12px" onsubmit="return <?= $cqMode?'cqOk(this,\'sub\')':'vcolOk(this)' ?>">
              <input type="hidden" name="id" value="<?= htmlspecialchars($p['id']) ?>">
              <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px">
              <?php if($cqMode): ?>
              <div style="margin-bottom:10px">
                <label class="hint"><?= t('Quantity per colour') ?> — <?= vestra_colours_phrase((int)$p['min_colors']) ?> · <?= sprintf(t('multiples of %d'), (int)$p['size_step']) ?></label>
                <?= vestra_colorqty_picker($p,'sub') ?>
                <div class="warn" id="cqwarn-sub" style="display:none;margin-top:8px"></div>
                <div class="hint" style="margin-top:6px"><?= t('Total quantity') ?>: <b><span id="cqtotal-sub">0</span> <?= htmlspecialchars($p['unit']) ?></b></div>
              </div>
              <input type="hidden" name="qty" id="qty-sub" value="0">
              <div style="max-width:280px">
                <div><label class="hint"><?= t('Your offer') ?> (€/<?= htmlspecialchars($p['unit']) ?>)</label>
                  <input type="number" name="price" step="0.01" min="0" required style="width:100%"></div>
              </div>
              <?php else: ?>
              <?php if(!empty($p['colors']) && !empty($p['min_colors'])): ?>
              <div style="margin-bottom:10px"><label class="hint"><?= t('Choose your colours') ?> — <?= sprintf(t('at least %d'), (int)$p['min_colors']) ?></label>
                <div class="colorpick" data-min="<?= (int)$p['min_colors'] ?>">
                  <?php $pal=vestra_colors(); foreach((array)$p['colors'] as $cn): ?>
                  <label class="colorchip"><input type="checkbox" name="colors[]" value="<?= htmlspecialchars($cn) ?>"><span class="cdot" style="background:<?= $pal[$cn]??'#666' ?>"></span><?= htmlspecialchars(t($cn)) ?></label>
                  <?php endforeach; ?>
                </div>
                <div class="warn vcolwarn" style="display:none;margin-top:8px"><?= vestra_colours_warn((int)$p['min_colors']) ?></div>
              </div>
              <?php endif; ?>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div><label class="hint"><?= t('Quantity') ?> — <?= t('min') ?> <?= $p['moq'] ?></label>
                  <input type="number" name="qty" min="<?= $p['moq'] ?>" step="<?= (int)($p['size_step'] ?? 1) ?>" value="<?= $p['moq'] ?>" required style="width:100%"></div>
                <div><label class="hint"><?= t('Your offer') ?> (€/<?= htmlspecialchars($p['unit']) ?>)</label>
                  <input type="number" name="price" step="0.01" min="0" required style="width:100%"></div>
              </div>
              <?php endif; ?>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px">
                <div><label class="hint"><?= t('Company') ?> *</label><input name="company" required style="width:100%" value="<?= htmlspecialchars($AUTH_USER['company'] ?? '') ?>"></div>
                <div><label class="hint"><?= t('Work email') ?> *</label><input type="email" name="email" required style="width:100%" value="<?= htmlspecialchars($AUTH_USER['email'] ?? '') ?>"></div>
              </div>
              <button class="btn btn-p" type="submit" style="width:100%;justify-content:center;margin-top:12px"><?= t('Submit offer →') ?></button>
              <?php if($AUTH_USER && !empty($p['seller_uid']) && $AUTH_USER['id']!==$p['seller_uid']): ?>
              <div class="hint" style="margin-top:8px">💬 <?= t('Your offer will also appear in Messages, linked to this product.') ?></div>
              <?php endif; ?>
            </form>
          </details>
        </div>
        <?php endif; ?>
        <?php $isOwnListing = $AUTH_USER && !empty($p['seller_uid']) && $AUTH_USER['id']===$p['seller_uid']; ?>
        <?php if(!$isOwnListing && !empty($p['sample_price']) && is_numeric($p['sample_price']) && (float)$p['sample_price']>0): ?>
        <div class="order-box" style="margin-top:14px">
          <div class="hint" style="margin-bottom:8px">📦 <?= t('Want to check it in hand first?') ?></div>
          <details class="offerdetails">
            <summary class="btn btn-o" style="width:100%;justify-content:center">📦 <?= t('Sample order') ?> — <?= eur((float)$p['sample_price']) ?></summary>
            <?php if($AUTH_USER): ?>
            <form method="post" action="/sample-checkout" style="margin-top:12px">
              <input type="hidden" name="id" value="<?= htmlspecialchars($p['id']) ?>">
              <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px">
              <label class="hint"><?= t('Size or note (optional)') ?></label>
              <input type="text" name="note" maxlength="200" placeholder="<?= htmlspecialchars(t('e.g. size M, or a note for us')) ?>" style="width:100%">
              <div class="hint" style="margin-top:8px"><?= t('EU-wide, shipping included.') ?> <?= t('The exact size you request may not always be available — we ship the closest match from current sample stock.') ?></div>
              <button class="btn btn-p" type="submit" style="width:100%;justify-content:center;margin-top:10px"><?= t('Order sample') ?> — <?= eur((float)$p['sample_price']) ?></button>
            </form>
            <?php else: ?>
            <a class="btn btn-p" href="/login?back=<?= urlencode('/product?id='.$p['id']) ?>" style="width:100%;justify-content:center;margin-top:12px"><?= t('Sign in to order a sample') ?></a>
            <?php endif; ?>
          </details>
        </div>
        <?php endif; ?>
        <?php if(!$isOwnListing): ?>
        <div class="order-box" style="margin-top:14px">
          <?php if($AUTH_USER): ?>
          <details class="offerdetails">
            <summary class="btn btn-o" style="width:100%;justify-content:center">💬 <?= t('Message seller') ?></summary>
            <form method="post" action="/buyer?tab=messages" style="margin-top:12px">
              <input type="hidden" name="_action" value="send_message">
              <input type="hidden" name="listing_id" value="<?= htmlspecialchars($p['id']) ?>">
              <textarea name="body" rows="3" placeholder="<?= htmlspecialchars(t('Ask about MOQ, samples, delivery…')) ?>" required style="width:100%"></textarea>
              <button class="btn btn-p" type="submit" style="width:100%;justify-content:center;margin-top:10px"><?= t('Send') ?></button>
              <div class="hint" style="margin-top:8px"><?= t('Do not share email addresses, phone numbers, or bank details — keep all communication and payment on VESTRA.') ?></div>
            </form>
          </details>
          <?php else: ?>
          <a class="btn btn-o" href="/login?back=<?= urlencode('/product?id='.$p['id']) ?>" style="width:100%;justify-content:center">💬 <?= t('Sign in to message seller') ?></a>
          <?php endif; ?>
        </div>
        <?php endif; ?>
        <script>
        var P=<?= json_encode(['id'=>$p['id'],'brand'=>$p['brand'],'name'=>$p['name'],'sku'=>$p['sku'],'unitLabel'=>$p['unit'],'moq'=>(int)$p['moq'],'step'=>(int)($p['size_step']??0),'minColors'=>(int)($p['min_colors']??0),'tiers'=>array_map(function($t){return ['min'=>(int)$t['min'],'price'=>(float)$t['price']];},$p['tiers'])]) ?>;
        function step(){ return P.step||(P.moq>=100?100:(P.moq>=50?50:10)); }
        function unitPrice(q){ var pr=P.tiers[0].price; P.tiers.forEach(function(t){ if(q>=t.min) pr=t.price; }); return pr; }
        function tierLabel(q){ var lab='—'; P.tiers.forEach(function(t,i){ if(q>=t.min){ var n=P.tiers[i+1]; lab=t.min+(n?'–'+(n.min-1):'+'); } }); return lab; }
        function eur(n){ return '€'+Number(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}); }
        function bump(d){ var el=document.getElementById('qty'); el.value=Math.max(P.moq,(parseInt(el.value)||P.moq)+d); recalc(); }
        /* Per-colour qty selects (carton listings) vs plain checkboxes */
        function cqSelects(){ var el=document.getElementById('ordColors'); if(!el) return null;
          var s=el.querySelectorAll('select[data-color]'); return s.length?s:null; }
        function ordColors(){ var s=cqSelects();
          if(s) return Array.prototype.filter.call(s,function(x){return parseInt(x.value)>0;})
                     .map(function(x){return x.dataset.color+' ×'+parseInt(x.value);});
          var el=document.getElementById('ordColors'); if(!el) return [];
          return Array.prototype.map.call(el.querySelectorAll('input:checked'), function(i){return i.value;}); }
        function cqTotal(){ var s=cqSelects(), t=0; if(!s) return 0;
          Array.prototype.forEach.call(s,function(x){t+=parseInt(x.value)||0;}); return t; }
        function recalc(){
          var cq=!!cqSelects(), warn=document.getElementById('warn'), btn=document.getElementById('addBtn');
          if(cq){ var t=cqTotal(); document.getElementById('qty').value=t;
            var tt=document.getElementById('cqtotal'); if(tt) tt.textContent=t; }
          var q=parseInt(document.getElementById('qty').value)||0;
          if(P.minColors>0 && ordColors().length<P.minColors){ warn.style.display='block'; warn.textContent=<?= json_encode(vestra_colours_warn((int)($p['min_colors']??0))) ?>; btn.disabled=true; }
          else if(q<P.moq){ warn.style.display='block'; warn.textContent='<?= addslashes(t('Minimum order is')) ?> '+P.moq+' '+P.unitLabel+'.'; btn.disabled=true; }
          else { warn.style.display='none'; btn.disabled=false; }
          var u=unitPrice(q);
          document.getElementById('uprice').textContent=eur(u);
          document.getElementById('tier').textContent=<?= json_encode(t('tier')) ?>+' '+tierLabel(q)+' '+P.unitLabel;
          document.getElementById('total').innerHTML=eur(u*q)+' <small>'+<?= json_encode(t('excl. taxes & shipping')) ?>+'</small>';
          document.querySelectorAll('#tiers tbody tr').forEach(function(tr){ tr.classList.toggle('active', q>=parseInt(tr.dataset.min)&&(!tr.nextElementSibling||q<parseInt(tr.nextElementSibling.dataset.min))); });
        }
        function addToOrder(){ var q=parseInt(document.getElementById('qty').value)||0; if(q<P.moq) return; var u=unitPrice(q);
          var cols=ordColors(); if(P.minColors>0 && cols.length<P.minColors){ recalc(); return; }
          VCart.add({id:P.id,brand:P.brand,name:P.name,sku:P.sku,unitLabel:P.unitLabel,qty:q,unit:u,colors:cols});
          var b=document.getElementById('addBtn'); b.textContent='✓ '+<?= json_encode(t('Added to order')) ?>; setTimeout(function(){b.textContent=<?= json_encode(t('Add to order')) ?>;},1400); }
        recalc();
        </script>
      <?php endif; ?>
    </div>
  </div>

  <?php
  /* ── Full-width detail sections (the "open in more detail" view) ────────────
     Specifications, the per-colour article/model breakdown, and related items.
     Specs & article codes are catalogue data → shown to everyone; only the
     variant thumbnails stay photo-gated (freischaltung), like the gallery. */
  $specs = (!empty($p['specs']) && is_array($p['specs'])) ? $p['specs'] : [];
  $variants = (!empty($p['variants']) && is_array($p['variants'])) ? $p['variants'] : [];
  $related = [];
  $pid = $p['id'] ?? '';
  foreach (vestra_products() as $rp) {
    if (($rp['id'] ?? '') === $pid) continue;
    $s = (!empty($p['seller_uid']) && ($rp['seller_uid'] ?? '') === $p['seller_uid']) ? 2 : 0;
    $c = (($rp['cat'] ?? '') !== '' && ($rp['cat'] ?? '') === ($p['cat'] ?? '')) ? 1 : 0;
    if ($s + $c > 0) $related[] = ['p' => $rp, 's' => $s + $c];
  }
  usort($related, fn($a, $b) => $b['s'] <=> $a['s']);
  $related = array_slice(array_map(fn($x) => $x['p'], $related), 0, 4);
  ?>
  <?php if ($specs || $variants || $related): ?>
  <style>
    .pmore{margin:40px 0 0;padding-top:30px;border-top:1px solid var(--line)}
    .pmore>h2{font-size:21px;margin:0 0 16px}
    .specs2{display:grid;grid-template-columns:1fr 1fr;background:var(--bg2);border:1px solid var(--line);border-radius:12px;overflow:hidden}
    .specs2 .spec-row{padding:11px 16px}
    @media(max-width:640px){.specs2{grid-template-columns:1fr}}
    .vscroll{overflow-x:auto;border:1px solid var(--line);border-radius:12px;background:var(--bg2)}
    .vartable{width:100%;border-collapse:collapse;font-size:13.5px;min-width:460px}
    .vartable th,.vartable td{padding:11px 14px;border-bottom:1px solid var(--line);text-align:left;white-space:nowrap}
    .vartable th{color:var(--mut);font-weight:500;font-size:11.5px;text-transform:uppercase;letter-spacing:.05em}
    .vartable tr:last-child td{border-bottom:none}
    .vartable .mono{font-family:ui-monospace,'SF Mono',Menlo,Consolas,monospace;font-size:12.5px;color:var(--acc)}
    .vartable .vcol{display:flex;align-items:center;gap:9px}
    .vartable .varthumb{width:32px;height:32px;object-fit:cover;border-radius:6px;border:1px solid var(--line);flex:none}
    .vartable .vdot{width:13px;height:13px;border-radius:50%;border:1px solid rgba(255,255,255,.25);flex:none}
  </style>
  <?php endif; ?>

  <?php if ($specs): ?>
  <section class="pmore">
    <h2><?= t('Specifications') ?></h2>
    <div class="specs2">
      <?php foreach ($specs as $k => $v): ?>
      <div class="spec-row"><span><?= htmlspecialchars(t($k)) ?></span><b><?= htmlspecialchars((string)$v) ?></b></div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($variants): $pal = vestra_colors(); ?>
  <section class="pmore">
    <h2><?= t('Article & colour breakdown') ?> <span style="color:var(--mut);font-weight:400;font-size:14px">· <?= count($variants) ?> <?= t('colourways') ?></span></h2>
    <div class="vscroll"><table class="vartable">
      <thead><tr>
        <th><?= t('Colour') ?></th><th><?= t('Article no.') ?></th><th><?= t('Model') ?></th>
        <?php if (!empty($p['size_step'])): ?><th><?= t('Carton') ?></th><?php endif; ?>
      </tr></thead>
      <tbody>
        <?php foreach ($variants as $v): $cn = $v['color'] ?? ''; ?>
        <tr>
          <td><div class="vcol">
            <?php if ($MEMBER && !empty($v['image'])): ?><img class="varthumb" src="<?= htmlspecialchars($v['image']) ?>" alt="" loading="lazy">
            <?php else: ?><span class="vdot" style="background:<?= htmlspecialchars($pal[$cn] ?? '#888') ?>"></span><?php endif; ?>
            <?= htmlspecialchars(t($cn)) ?>
          </div></td>
          <td class="mono"><?= htmlspecialchars($v['art'] ?? '—') ?></td>
          <td class="mono"><?= htmlspecialchars($v['model'] ?? '—') ?></td>
          <?php if (!empty($p['size_step'])): ?><td><?= (int)$p['size_step'] ?> <?= htmlspecialchars($p['unit'] ?? 'pc') ?></td><?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
  <?php endif; ?>

  <?php if ($related): ?>
  <section class="pmore">
    <h2><?= t('You might also like') ?></h2>
    <div class="shopgrid">
      <?php foreach ($related as $rp): $rfrom = vestra_from_price($rp);
        $rimgs = ($MEMBER && !empty($rp['images']) && is_array($rp['images'])) ? array_values(array_filter($rp['images'])) : [];
        $rimg = $rimgs[0] ?? ''; ?>
        <a class="scard" href="/product?id=<?= urlencode($rp['id']) ?>">
          <div class="sthumb" style="background:linear-gradient(135deg,<?= htmlspecialchars(vestra_accent($rp)) ?>,#0e0e11)">
            <?php if ($rimg): ?><img src="<?= htmlspecialchars($rimg) ?>" alt="" loading="lazy" class="sthumbi"><?php endif; ?>
            <?php if (!empty($rp['verified'])): ?><span class="svbadge"><svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg> <?= t('Verified seller') ?></span><?php endif; ?>
            <?php if (!$rimg) echo vestra_brand_card($rp['brand'] ?? ''); ?>
            <?php if (($rp['mode'] ?? '') === 'sale'): ?><span class="smodetag sale">−<?= vestra_discount($rp) ?>%</span>
            <?php elseif (($rp['mode'] ?? '') === 'offer'): ?><span class="smodetag offer"><?= t('Offers') ?></span><?php endif; ?>
          </div>
          <div class="sbody">
            <span class="sbrand"><?= htmlspecialchars($rp['brand'] ?? '') ?></span>
            <span class="stitle"><?= htmlspecialchars($rp['name'] ?? '') ?></span>
            <span class="smeta"><?= htmlspecialchars($rp['cat'] ?? '') ?> · MOQ <b><?= $rp['moq'] ?? '?' ?></b> <?= htmlspecialchars($rp['unit'] ?? 'pc') ?></span>
            <div class="sprice">
              <?php if (!$MEMBER): ?>
                <span class="slock"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg><?= t('Members only') ?></span>
              <?php elseif (($rp['mode'] ?? '') === 'offer'): ?>
                <span class="soffer">💬 <?= t('Open to offers') ?></span>
              <?php else: ?>
                <span class="sfrom"><?= t('from') ?></span><span class="samt"><?= eur($rfrom) ?></span><span class="sfrom">/<?= htmlspecialchars($rp['unit'] ?? 'pc') ?></span>
              <?php endif; ?>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
<?php if(!$MEMBER): ?>
    </div><!-- /.lockblur -->
  </div><!-- /.lockwrap -->
<?php endif; ?>
</div>

<?php if($images): ?>
<script>
/* Slides: 0..n-1 = product photos (opens on the first photo); n = the brand card (last). */
var galImgs=<?= json_encode($images) ?>, galN=galImgs.length, galIdx=0;
function galSet(i){
  galIdx=i;
  var img=document.getElementById('gal-main-img'), card=document.getElementById('gal-card');
  if(i>=galN){ if(img) img.style.display='none'; card.style.display='flex'; }
  else { img.src=galImgs[i]; img.style.display='block'; card.style.display='none'; }
  document.querySelectorAll('.gal-thumb').forEach(function(t,j){ t.classList.toggle('active', j===i); });
}
function galGo(d){
  var n=galN+1;                           // slides: photos + brand card
  galSet(((galIdx+d)%n+n)%n);
}
document.addEventListener('keydown', function(e){
  if(e.key==='ArrowLeft') galGo(-1);
  if(e.key==='ArrowRight') galGo(1);
});

/* ── Büyüteç ────────────────────────────────────────────────────────────────
   Masaüstü: imleç lensi + yanda 1:1 panel. Dokunmatik/tık: tam ekran, pinch ve
   çift-dokunuşla yakınlaştırma, sürükleyerek gezinme.
   Panel ölçeği görüntünün DOĞAL boyutuna göre hesaplanır; ekrandaki küçültülmüş
   kopyayı büyütmek bulanık verir, oysa asıl amaç dokuyu göstermek. */
(function(){
  var main   = document.getElementById('gal-main-img');
  var lens   = document.getElementById('vzoomLens');
  var panel  = document.getElementById('vzoomPanel');
  var surf   = document.getElementById('vzoomSurface');
  var badge  = document.getElementById('vzoomBadge');
  var full   = document.getElementById('vzoomFull');
  var stage  = document.getElementById('vzoomStage');
  var fimg   = document.getElementById('vzoomFullImg');
  var pct    = document.getElementById('vzoomPct');
  if(!main || !full) return;           // üye değilse katmanlar basılmaz

  var LENS = 150;                       // lens kenarı (px)
  var nat  = {w:0,h:0};                 // aktif görüntünün doğal boyutu

  function measure(){
    nat.w = main.naturalWidth || 0;
    nat.h = main.naturalHeight || 0;
  }
  measure();
  main.addEventListener('load', measure);

  /* Masaüstü lens+panel. Doğal boyut okunamadıysa (henüz yüklenmediyse) hiç
     açma: yanlış ölçekli bir panel göstermektense hiç göstermemek doğru. */
  function lensMove(e){
    if(!nat.w || panel.offsetParent === null) return;
    var r = main.getBoundingClientRect();
    var x = e.clientX - r.left, y = e.clientY - r.top;
    if(x < 0 || y < 0 || x > r.width || y > r.height){ lensOff(); return; }

    var half = LENS/2;
    var lx = Math.max(0, Math.min(x - half, r.width  - LENS));
    var ly = Math.max(0, Math.min(y - half, r.height - LENS));
    lens.style.width = lens.style.height = LENS+'px';
    lens.style.transform = 'translate('+lx+'px,'+ly+'px)';
    lens.classList.add('on');

    /* Panelde 1:1: doğal piksel / ekran pikseli. */
    var scale = nat.w / r.width;
    var pw = panel.clientWidth, ph = panel.clientHeight;
    surf.style.backgroundImage = 'url("'+main.src+'")';
    surf.style.backgroundSize  = (r.width*scale)+'px '+(r.height*scale)+'px';
    surf.style.backgroundPosition =
      (-(lx*scale) + (pw - LENS*scale)/2)+'px '+
      (-(ly*scale) + (ph - LENS*scale)/2)+'px';
    panel.classList.add('on');
    if(badge) badge.textContent = scale >= 1 ? '1:1' : Math.round(scale*100)+'%';
  }
  function lensOff(){ lens.classList.remove('on'); panel.classList.remove('on'); }

  /* Kaba işaretçide (parmak) lens anlamsız — sadece tam ekran. */
  if(window.matchMedia && window.matchMedia('(hover:hover) and (pointer:fine)').matches){
    main.addEventListener('mousemove', lensMove);
    main.addEventListener('mouseleave', lensOff);
  }

  /* ── Tam ekran ── */
  var z=1, tx=0, ty=0, base=1;
  function apply(){
    fimg.style.transform = 'translate('+tx+'px,'+ty+'px) scale('+z+')';
    if(pct) pct.textContent = Math.round(z*base*100)+'%';
  }
  function clamp(){
    /* Görüntüyü sahnenin dışına kaçırma: her eksende taşma kadar izin ver. */
    var r = fimg.getBoundingClientRect(), s = stage.getBoundingClientRect();
    var ox = Math.max(0, (r.width  - s.width )/2);
    var oy = Math.max(0, (r.height - s.height)/2);
    tx = Math.max(-ox, Math.min(ox, tx));
    ty = Math.max(-oy, Math.min(oy, ty));
  }
  function openFull(){
    if(galIdx >= galN) return;          // marka kartı, fotoğraf değil
    fimg.src = galImgs[galIdx];
    z=1; tx=0; ty=0;
    full.classList.add('on');
    document.body.style.overflow='hidden';
    fimg.onload = function(){
      var r = fimg.getBoundingClientRect();
      base = (fimg.naturalWidth && r.width) ? (r.width/fimg.naturalWidth) : 1;
      apply();
    };
    apply();
  }
  function closeFull(){
    full.classList.remove('on');
    document.body.style.overflow='';
  }
  main.addEventListener('click', openFull);
  document.getElementById('vzoomClose').addEventListener('click', closeFull);
  full.addEventListener('click', function(e){ if(e.target === full || e.target === stage) closeFull(); });
  document.addEventListener('keydown', function(e){ if(e.key==='Escape' && full.classList.contains('on')) closeFull(); });

  /* Tekerlek: imlecin altındaki nokta sabit kalacak şekilde yakınlaştır. */
  stage.addEventListener('wheel', function(e){
    if(!full.classList.contains('on')) return;
    e.preventDefault();
    var prev=z;
    z = Math.max(1, Math.min(6, z * (e.deltaY < 0 ? 1.12 : 1/1.12)));
    var s = stage.getBoundingClientRect();
    var cx = e.clientX - s.left - s.width/2  - tx;
    var cy = e.clientY - s.top  - s.height/2 - ty;
    tx -= cx*(z/prev - 1); ty -= cy*(z/prev - 1);
    clamp(); apply();
  }, {passive:false});

  /* Sürükle (fare + tek parmak) */
  var drag=false, sx=0, sy=0;
  stage.addEventListener('pointerdown', function(e){
    if(!full.classList.contains('on') || e.pointerType==='touch' && pts.size>1) return;
    drag=true; sx=e.clientX-tx; sy=e.clientY-ty;
    stage.classList.add('dragging'); stage.setPointerCapture(e.pointerId);
  });
  stage.addEventListener('pointermove', function(e){
    if(!drag || pts.size>1) return;
    tx=e.clientX-sx; ty=e.clientY-sy; clamp(); apply();
  });
  ['pointerup','pointercancel'].forEach(function(ev){
    stage.addEventListener(ev, function(){ drag=false; stage.classList.remove('dragging'); });
  });

  /* Pinch: iki parmak arası mesafe oranı kadar ölçekle. */
  var pts = new Map(), pd0=0, pz0=1;
  stage.addEventListener('pointerdown', function(e){ pts.set(e.pointerId,{x:e.clientX,y:e.clientY}); if(pts.size===2){ pd0=dist(); pz0=z; } });
  stage.addEventListener('pointermove', function(e){
    if(!pts.has(e.pointerId)) return;
    pts.set(e.pointerId,{x:e.clientX,y:e.clientY});
    if(pts.size===2 && pd0>0){ z = Math.max(1, Math.min(6, pz0 * (dist()/pd0))); clamp(); apply(); }
  });
  ['pointerup','pointercancel'].forEach(function(ev){
    stage.addEventListener(ev, function(e){ pts.delete(e.pointerId); if(pts.size<2) pd0=0; });
  });
  function dist(){
    var a=Array.from(pts.values()); if(a.length<2) return 0;
    return Math.hypot(a[0].x-a[1].x, a[0].y-a[1].y);
  }

  /* Çift dokunuş / çift tık: 1x ↔ 2.5x */
  var lastTap=0;
  stage.addEventListener('pointerup', function(e){
    if(pts.size) return;
    var now = e.timeStamp;
    if(now - lastTap < 320){ z = (z>1.05) ? 1 : 2.5; tx=0; ty=0; clamp(); apply(); lastTap=0; }
    else lastTap = now;
  });
})();
</script>
<?php endif; ?>
<script>
function vcolOk(f){
  var cp=f.querySelector('.colorpick[data-min]'); if(!cp) return true;
  var need=parseInt(cp.dataset.min)||0, got=cp.querySelectorAll('input:checked').length;
  var w=f.querySelector('.vcolwarn'); if(got<need){ if(w) w.style.display='block'; return false; }
  if(w) w.style.display='none'; return true;
}
/* Per-colour qty picker (offer forms) — sums the selects, writes the hidden qty field
   and the running total display for the given picker instance ('main' | 'sub'). */
function cqSync(suffix){
  var wrap=document.getElementById('cq-'+suffix); if(!wrap) return 0;
  var t=0; wrap.querySelectorAll('select[data-color]').forEach(function(s){ t+=parseInt(s.value)||0; });
  var qtyEl=document.getElementById('qty-'+suffix); if(qtyEl) qtyEl.value=t;
  var totEl=document.getElementById('cqtotal-'+suffix); if(totEl) totEl.textContent=t;
  return t;
}
function cqOk(f,suffix){
  var need=<?= (int)($p['min_colors']??0) ?>;
  var wrap=document.getElementById('cq-'+suffix), warn=document.getElementById('cqwarn-'+suffix);
  var got=0; if(wrap) wrap.querySelectorAll('select[data-color]').forEach(function(s){ if(parseInt(s.value)>0) got++; });
  var t=cqSync(suffix);
  if(got<need || t<=0){
    if(warn){ warn.textContent=<?= json_encode(vestra_colours_warn((int)($p['min_colors']??0))) ?>; warn.style.display='block'; }
    return false;
  }
  if(warn) warn.style.display='none';
  return true;
}
</script>
<?php require __DIR__.'/inc/foot.php';
