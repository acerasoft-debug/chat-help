<?php
/** VESTRA — public seller showroom: the "Seller profile & showroom" every membership
 *  tier advertises. Shows the seller's identity card (or a privacy-preserving alias
 *  when all their listings hide the company name) plus their live catalog. */
require __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/auth.php';

$sid = trim($_GET['id'] ?? '');
$sellerAcc = null;
if ($sid !== '') {
    foreach (auth_accounts() as $a) {
        if (($a['id'] ?? '') === $sid && ($a['type'] ?? '') === 'seller') { $sellerAcc = $a; break; }
    }
}
$items = $sellerAcc ? array_values(array_filter(vestra_live_listings(),
    fn($p) => ($p['seller_uid'] ?? '') === $sid)) : [];

if (!$sellerAcc || !$items) {
    http_response_code(404);
    $PAGE = t('Showroom'); $NAV = 'shop'; require __DIR__.'/inc/head.php';
    echo '<div class="wrap" style="padding:70px 0;text-align:center"><h1>'.t('Showroom not found').'</h1>
      <p style="color:var(--mut);margin-top:10px">'.t('This seller has no public showroom (yet).').'</p>
      <a class="btn btn-p" href="/shop" style="margin-top:20px">'.t('Browse the catalog').'</a></div>';
    require __DIR__.'/inc/foot.php'; exit;
}

/* Showrooms reveal the seller's identity, so they are approval-gated: the viewer must be
   signed in AND freigeschaltet (active / KYB-approved / documents submitted). The gate is
   decided BEFORE head.php so the seller's name never leaks via <title>/OG tags either. */
if (session_status() === PHP_SESSION_NONE) session_start();
$viewer = auth_user();
if (!auth_user_approved($viewer)) {
    $PAGE = t('Showroom'); $NAV = 'shop'; $NOINDEX = true; require __DIR__.'/inc/head.php';
    $cta = $viewer === null
        ? '<a class="btn btn-p" href="/login?back='.htmlspecialchars(urlencode('/showroom?id='.$sid)).'">'.t('Sign in').'</a> <a class="btn btn-o" href="/register">'.t('Register free').'</a>'
        : '<a class="btn btn-p" href="'.((($viewer['type'] ?? '') === 'seller') ? '/seller?tab=kyc' : '/buyer?tab=kyc').'">'.t('Complete verification').'</a>';
    echo '<div class="wrap"><div class="gate" style="margin:70px auto;max-width:480px;text-align:center">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="var(--acc)" stroke-width="1.6" style="margin:0 auto 8px"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
      <h3 style="margin:0 0 6px">'.t('Showrooms are for verified members').'</h3>
      <p style="color:var(--mut);margin:0 0 16px">'.t('Seller identities and product photos unlock once your business is verified.').'</p>
      <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">'.$cta.'</div>
    </div></div>';
    require __DIR__.'/inc/foot.php'; exit;
}

/* Anonymity: a seller who hides their name on EVERY listing gets an alias here too. */
$allHidden = !array_filter($items, fn($p) => empty($p['hide_seller']));
$dispName  = $allHidden || ($sellerAcc['company'] ?? '') === ''
    ? t('Verified Seller').' #'.strtoupper(substr($sid, 0, 4))
    : $sellerAcc['company'];
$verified  = ($sellerAcc['kyb_status'] ?? '') === 'approved';
$since     = !empty($sellerAcc['created']) ? date('Y', strtotime($sellerAcc['created'])) : '';
$cats      = array_values(array_unique(array_map(fn($p) => $p['cat'] ?? '', $items)));

$PAGE = $dispName.' — '.t('Showroom'); $NAV = 'shop'; require __DIR__.'/inc/head.php';
?>
<div class="wrap">
  <div class="phead" style="margin-bottom:26px">
    <div class="crumbs"><a href="/"><?= t('Home') ?></a> · <a href="/shop"><?= t('Catalog') ?></a> · <?= t('Showroom') ?></div>
    <h1 style="display:flex;align-items:center;gap:12px;flex-wrap:wrap"><?= htmlspecialchars($dispName) ?>
      <?php if ($verified): ?><span class="gal-vbadge" style="position:static"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg> <?= t('Verified seller') ?></span><?php endif; ?>
    </h1>
    <p style="color:var(--mut)">
      <?= count($items) ?> <?= t('live listings') ?>
      <?php if ($cats): ?> · <?= htmlspecialchars(implode(' · ', array_slice($cats, 0, 4))) ?><?php endif; ?>
      <?php if (!$allHidden && !empty($sellerAcc['country'])): ?> · <?= htmlspecialchars($sellerAcc['country']) ?><?php endif; ?>
      <?php if ($since): ?> · <?= t('Member since') ?> <?= $since ?><?php endif; ?>
    </p>
  </div>

  <div class="shopgrid">
    <?php foreach ($items as $p):
      $from = vestra_from_price($p);
      $imgCount = $APPROVED ? count($p['images'] ?? (vestra_primary_image($p) ? [vestra_primary_image($p)] : [])) : 0;
      ?>
      <a class="scard" href="/product?id=<?= urlencode($p['id']) ?>">
        <div class="sthumb" style="background:linear-gradient(135deg,<?= $p['accent'] ?? '#2a2b31' ?>,#0e0e11)">
          <?php if (!empty($p['verified'])): ?>
            <span class="svbadge"><svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg> <?= t('Verified seller') ?></span>
          <?php endif; ?>
          <?php $blogo = vestra_brand_logo($p['brand'] ?? ''); echo $blogo ?: '<span class="sbname">'.htmlspecialchars($p['brand'] ?? '').'</span>'; ?>
          <?php if (($p['mode'] ?? '') === 'sale'): ?><span class="smodetag sale">−<?= vestra_discount($p) ?>%</span>
          <?php elseif (($p['mode'] ?? '') === 'offer'): ?><span class="smodetag offer"><?= t('Offers') ?></span><?php endif; ?>
          <?php if ($imgCount > 1): ?><span class="sphotocount">🖼 <?= $imgCount ?></span><?php endif; ?>
        </div>
        <div class="sbody">
          <span class="sbrand"><?= htmlspecialchars($p['brand'] ?? '') ?></span>
          <span class="stitle"><?= htmlspecialchars($p['name'] ?? '') ?></span>
          <span class="smeta"><?= htmlspecialchars($p['cat'] ?? '') ?> &middot; SKU <?= htmlspecialchars($p['sku'] ?? '') ?></span>
          <span class="smeta">MOQ <b><?= $p['moq'] ?? '?' ?></b> <?= htmlspecialchars($p['unit'] ?? 'pc') ?></span>
          <div class="sprice">
            <?php if (!$MEMBER): ?>
              <span class="slock"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg><?= t('Members only') ?></span>
            <?php elseif (($p['mode'] ?? '') === 'offer'): ?>
              <span class="soffer">💬 <?= t('Open to offers') ?></span>
            <?php elseif (($p['mode'] ?? '') === 'sale'): ?>
              <span class="swas"><?= eur($p['list'] ?? 0) ?></span>
              <span class="samt"><?= eur($from) ?></span>
              <span class="sfrom">/<?= htmlspecialchars($p['unit'] ?? 'pc') ?></span>
            <?php else: ?>
              <span class="sfrom"><?= t('from') ?></span>
              <span class="samt"><?= eur($from) ?></span>
              <span class="sfrom">/<?= htmlspecialchars($p['unit'] ?? 'pc') ?></span>
            <?php endif; ?>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__.'/inc/foot.php';
