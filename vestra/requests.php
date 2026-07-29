<?php
/**
 * VESTRA — Sourcing board (buyer "Anzeigen"): buyers post what they're looking
 * for; verified sellers answer with offers or a direct message. Premium hub:
 * stats strip, rich request cards with offer counts, seller→buyer messaging,
 * and an inline view of received offers for the request's owner.
 */
require __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/auth.php';
$PAGE=t('Sourcing requests'); $NAV='requests'; $META='VESTRA sourcing requests — post what your boutique needs and let KYC-verified wholesale suppliers quote. Authentic branded fashion, invoice-based B2B ordering across Europe.'; require __DIR__.'/inc/head.php';
$posted=isset($_GET['posted']);

/* Real buyer requests (no demo seeds — the board only shows genuine Anzeigen). */
$userReqs=[]; $f=__DIR__.'/data/requests.csv';
if(is_readable($f) && ($h=@fopen($f,'r'))){ $head=fgetcsv($h, null, ',', '"', '\\'); while(($row=fgetcsv($h, null, ',', '"', '\\'))!==false){ if($head){ $n=count($head); $row=array_slice(array_pad($row,$n,''),0,$n); $userReqs[]=array_combine($head,$row);} } fclose($h); }
$userReqs=array_reverse($userReqs);

/* Offers per request (badge + owner's inline list). */
$offersByRef=[]; $fo=__DIR__.'/data/request_offers.csv';
if(is_readable($fo) && ($h=@fopen($fo,'r'))){ $head=fgetcsv($h, null, ',', '"', '\\'); while(($row=fgetcsv($h, null, ',', '"', '\\'))!==false){ if($head){ $n=count($head); $row=array_slice(array_pad($row,$n,''),0,$n); $o=array_combine($head,$row); $offersByRef[$o['request_ref']??''][]=$o; } } fclose($h); }

$openCount=count($userReqs);
$offerCount=array_sum(array_map('count',$offersByRef));
$cats=vestra_cats();
$isSeller=$AUTH_USER && ($AUTH_USER['type']??'')==='seller';
$myEmail=strtolower($AUTH_USER['email']??'');

/* Human age from the request timestamp. */
function vestra_req_age(string $ts): string {
  $t=strtotime($ts); if(!$t) return t('just now');
  $d=max(0,time()-$t);
  if($d<3600) return max(1,(int)($d/60)).' '.t('min ago');
  if($d<86400) return (int)($d/3600).' '.t('h ago');
  return (int)($d/86400).' '.t('d ago');
}
?>
<div class="wrap wide">
  <div class="phead" style="margin-bottom:18px">
    <div class="crumbs"><a href="/"><?= t('Home') ?></a> · <?= t('Sourcing requests') ?></div>
    <h1><?= t('Sourcing board') ?></h1>
    <p style="max-width:640px"><?= t("Can't find it in the catalog? <b>Post what you're looking for</b> and join the queue — verified sellers come to you with offers.") ?></p>
  </div>

  <?php if($posted): ?>
    <div class="banner ok">✓ <?= sprintf(t('Your request is live and <b>in the queue</b> (ref %s). Verified sellers can now send you offers. We\'ll email you when offers arrive.'), '<b>'.htmlspecialchars(substr($_GET['ref']??'',0,16)).'</b>') ?></div>
  <?php endif; ?>

  <div class="reqstats">
    <div class="stat"><b><?=$openCount?></b><span><?= t('open requests') ?></span></div>
    <div class="stat"><b><?=$offerCount?></b><span><?= t('offers sent') ?></span></div>
    <div class="stat"><b>~24h</b><span><?= t('avg. first offer') ?></span></div>
    <div class="stat"><b>✓ <?= t('verified') ?></b><span><?= t('sellers only') ?></span></div>
  </div>

  <div class="reqlayout">
    <!-- the queue -->
    <div>
      <h3 class="blocktitle"><?= t('Open requests') ?> <span class="hint">— <?= t('newest first') ?></span></h3>
      <div class="reqlist">
        <?php if(!$userReqs): ?>
          <div class="empty" style="padding:44px 20px"><?= t('No open requests yet — be the first: post what you\'re looking for and verified sellers will come to you.') ?></div>
        <?php endif; ?>
        <?php foreach($userReqs as $r):
          $ref=$r['ref']??'';
          $mine=$myEmail!=='' && strtolower($r['email']??'')===$myEmail;
          $offers=$offersByRef[$ref]??[];
          $ownerAcc=auth_find($r['email']??'');
          $canMsg=$isSeller && $ownerAcc && ($ownerAcc['type']??'')==='buyer';
        ?>
          <div class="reqcard<?= $mine?' mine':'' ?>">
            <div class="reqtop">
              <span class="qpos">#<?=htmlspecialchars($ref)?></span>
              <?php if($mine): ?><span class="status offers">★ <?= t('Your request') ?></span><?php endif; ?>
              <span class="status <?=count($offers)?'offers':'open'?>"><?= count($offers) ? count($offers).' '.t('offers') : t('Open') ?></span>
            </div>
            <div class="reqtitle"><?=htmlspecialchars($r['title']??'')?></div>
            <?php if(!empty($r['ref_image'])): ?><img src="<?=htmlspecialchars($r['ref_image'])?>" alt="" loading="lazy" style="width:100%;max-height:170px;object-fit:cover;border-radius:10px;margin:8px 0"><?php endif; ?>
            <div class="reqmeta">
              <?php if(!empty($r['cat'])): ?><span><?=htmlspecialchars(t($r['cat']))?></span><?php endif; ?>
              <?php if(!empty($r['qty'])): ?><span><?= t('Qty') ?> <b><?=htmlspecialchars($r['qty'])?></b></span><?php endif; ?>
              <?php if(!empty($r['target'])): ?><span><?= t('Target') ?> <b><?=htmlspecialchars($r['target'])?></b></span><?php endif; ?>
              <?php if(!empty($r['country'])): ?><span>📍 <?=htmlspecialchars($r['country'])?></span><?php endif; ?>
              <span><?= vestra_req_age($r['timestamp']??'') ?></span>
              <span>🛡️ <?= t('Verified buyer') ?></span>
            </div>
            <?php if(!empty($r['notes'])): ?><p class="hint" style="margin:8px 0 0;line-height:1.5"><?=htmlspecialchars(mb_substr($r['notes'],0,220))?></p><?php endif; ?>
            <?php if(!empty($r['ref_url'])): ?><a class="hint" href="<?=htmlspecialchars($r['ref_url'])?>" target="_blank" rel="noopener nofollow" style="display:inline-block;margin-top:4px">🔗 <?= t('View reference ↗') ?></a><?php endif; ?>

            <div class="reqact">
              <?php if($MEMBER): ?>
                <a class="btn btn-p btn-sm" href="/request-offer?ref=<?= urlencode($ref) ?>">💶 <?= t('Make an offer') ?></a>
              <?php else: ?>
                <a class="btn btn-o btn-sm" href="/login?back=<?= urlencode('/requests') ?>"><?= t('Sign in to make an offer') ?></a>
              <?php endif; ?>
              <?php if($canMsg): ?>
                <details class="respdetails" style="display:inline-block">
                  <summary class="btn btn-o btn-sm">💬 <?= t('Message buyer') ?></summary>
                  <form method="post" action="/seller?tab=messages" style="margin-top:10px;min-width:260px">
                    <input type="hidden" name="_action" value="send_message">
                    <input type="hidden" name="buyer_uid" value="<?= htmlspecialchars($ownerAcc['id']) ?>">
                    <textarea name="body" rows="3" required style="width:100%" placeholder="<?= htmlspecialchars(t('Ask about details, sizes, delivery…')) ?>"><?= htmlspecialchars('['.t('Re: request').' #'.$ref.'] ') ?></textarea>
                    <button class="btn btn-p btn-sm" type="submit" style="margin-top:8px"><?= t('Send') ?></button>
                  </form>
                </details>
              <?php endif; ?>
            </div>

            <?php if($mine && $offers): ?>
              <div style="margin-top:12px;border-top:1px solid var(--line);padding-top:10px">
                <div class="hint" style="font-weight:600;margin-bottom:6px">📥 <?= t('Offers on your request') ?></div>
                <?php foreach(array_reverse($offers) as $o): ?>
                  <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;padding:7px 0;border-bottom:1px dashed var(--line);font-size:13px">
                    <span><b><?=htmlspecialchars($o['seller_company']??'')?></b><?php if(!empty($o['message'])): ?> · <span class="hint"><?=htmlspecialchars(mb_substr($o['message'],0,90))?></span><?php endif; ?></span>
                    <span style="white-space:nowrap"><b class="acc">€<?=htmlspecialchars($o['price']??'')?></b><?php if(!empty($o['qty'])): ?> · <?=htmlspecialchars($o['qty'])?><?php endif; ?> · <span class="hint"><?=htmlspecialchars(substr($o['timestamp']??'',0,10))?></span></span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- post a request -->
    <div>
      <div class="postbox" id="post">
        <h3 class="blocktitle"><?= t('Post a request') ?></h3>
        <p class="hint" style="margin-top:-4px"><?= t("No payment, no commitment. You'll get offers from verified sellers.") ?></p>
        <form method="post" action="/request" enctype="multipart/form-data">
          <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px">
          <label class="hint"><?= t('What are you looking for?') ?> *</label>
          <input name="title" required placeholder="<?= htmlspecialchars(t('e.g. Lacoste polos, mixed sizes, EEA stock')) ?>" style="width:100%;margin-bottom:12px">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div><label class="hint"><?= t('Category') ?></label>
              <select name="cat" style="width:100%">
                <option value=""><?= t('Any') ?></option>
                <?php foreach($cats as $c): ?><option value="<?=htmlspecialchars($c)?>"><?=htmlspecialchars(t($c))?></option><?php endforeach; ?>
                <option value="Other"><?= t('Other') ?></option>
              </select></div>
            <div><label class="hint"><?= t('Quantity') ?></label><input name="qty" placeholder="<?= htmlspecialchars(t('e.g. 300 pc')) ?>" style="width:100%"></div>
            <div><label class="hint"><?= t('Target price') ?></label><input name="target" placeholder="<?= htmlspecialchars(t('e.g. €24/pc or best offer')) ?>" style="width:100%"></div>
            <div><label class="hint"><?= t('Country') ?></label><input name="country" placeholder="DE" style="width:100%"></div>
          </div>
          <div style="margin-top:12px"><label class="hint"><?= t('Work email') ?> *</label><input type="email" name="email" required style="width:100%" value="<?= htmlspecialchars($AUTH_USER['email']??'') ?>"></div>
          <div style="margin-top:12px"><label class="hint"><?= t('Reference link (optional)') ?></label>
            <input type="url" name="ref_url" placeholder="<?= htmlspecialchars(t('Link to the exact item — Amazon, eBay, brand site…')) ?>" style="width:100%">
            <p class="hint" style="margin:4px 0 0"><?= t("Found it on another site? Paste the link so sellers know exactly what you mean. We don't copy or import anything from it — it's just shown as a reference.") ?></p>
          </div>
          <div style="margin-top:12px"><label class="hint"><?= t('Reference photo (optional)') ?></label>
            <input type="file" name="ref_image" accept="image/png,image/jpeg,image/webp" style="width:100%">
          </div>
          <div style="margin-top:12px"><label class="hint"><?= t('Notes') ?></label><textarea name="notes" rows="2" style="width:100%" placeholder="<?= htmlspecialchars(t('Brands, condition, delivery terms…')) ?>"></textarea></div>
          <button class="btn btn-p" type="submit" style="width:100%;justify-content:center;margin-top:16px"><?= t('Post request &amp; join the queue') ?></button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__.'/inc/foot.php';
