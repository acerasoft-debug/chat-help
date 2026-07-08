<?php
require __DIR__.'/inc/products.php'; $PAGE=t('Sourcing requests'); $NAV='requests'; require __DIR__.'/inc/head.php';
$posted=isset($_GET['posted']);
$userReqs=[]; $f=__DIR__.'/data/requests.csv';
if(is_readable($f) && ($h=@fopen($f,'r'))){ $head=fgetcsv($h); while(($row=fgetcsv($h))!==false){ if($head&&count($row)===count($head)) $userReqs[]=array_combine($head,$row); } fclose($h); }
$userReqs=array_reverse($userReqs);
$seed=vestra_requests();
$openCount=count($seed)+count($userReqs);
$cats=vestra_cats();
?>
<div class="wrap">
  <div class="phead">
    <div class="crumbs"><a href="/"><?= t('Home') ?></a> · <?= t('Sourcing requests') ?></div>
    <h1><?= t('Sourcing board') ?></h1>
    <p><?= t("Can't find it in the catalog? <b>Post what you're looking for</b> and join the queue — verified sellers come to you with offers.") ?></p>
  </div>

  <?php if($posted): ?>
    <div class="banner ok">✓ <?= sprintf(t('Your request is live and <b>in the queue</b> (ref %s). Verified sellers can now send you offers. We\'ll email you when offers arrive.'), '<b>'.htmlspecialchars(substr($_GET['ref']??'',0,16)).'</b>') ?></div>
  <?php endif; ?>

  <div class="reqstats">
    <div class="stat"><b><?=$openCount?></b><span><?= t('open requests') ?></span></div>
    <div class="stat"><b>~24h</b><span><?= t('avg. first offer') ?></span></div>
    <div class="stat"><b>✓ <?= t('verified') ?></b><span><?= t('sellers only') ?></span></div>
  </div>

  <div class="reqlayout">
    <!-- the queue -->
    <div>
      <h3 class="blocktitle"><?= t('Open requests') ?> <span class="hint">— <?= t('newest first') ?></span></h3>
      <div class="reqlist">
        <?php foreach($userReqs as $r): ?>
          <div class="reqcard mine">
            <div class="reqtop">
              <span class="qpos">#<?=htmlspecialchars($r['ref']??'—')?></span>
              <span class="status open"><?= t('In queue') ?></span>
            </div>
            <div class="reqtitle"><?=htmlspecialchars($r['title']??'')?></div>
            <div class="reqmeta">
              <?php if(!empty($r['cat'])): ?><span><?=htmlspecialchars($r['cat'])?></span><?php endif; ?>
              <?php if(!empty($r['qty'])): ?><span><?= t('Qty') ?> <b><?=htmlspecialchars($r['qty'])?></b></span><?php endif; ?>
              <?php if(!empty($r['target'])): ?><span><?= t('Target') ?> <b><?=htmlspecialchars($r['target'])?></b></span><?php endif; ?>
              <?php if(!empty($r['country'])): ?><span><?=htmlspecialchars($r['country'])?></span><?php endif; ?>
              <span><?= t('just now') ?></span>
            </div>
            <div class="reqact">
              <?php if($MEMBER): ?>
                <a class="btn btn-o btn-sm" href="/request-offer?ref=<?= urlencode($r['ref']??'') ?>"><?= t('Make an offer') ?></a>
              <?php else: ?>
                <a class="hint" href="/login"><?= t('Sign in to make an offer') ?></a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>

        <?php foreach($seed as $r): ?>
          <div class="reqcard">
            <div class="reqtop">
              <span class="qpos">#<?=htmlspecialchars($r['id'])?></span>
              <span class="status <?=$r['offers']>0?'offers':'open'?>"><?= $r['offers']>0 ? $r['offers'].' '.t('offers') : t('Open') ?></span>
            </div>
            <div class="reqtitle"><?=htmlspecialchars($r['title'])?></div>
            <div class="reqmeta">
              <span><?=htmlspecialchars($r['cat'])?></span>
              <span><?= t('Qty') ?> <b><?=htmlspecialchars($r['qty'])?></b></span>
              <span><?= t('Target') ?> <b><?=htmlspecialchars($r['target'])?></b></span>
              <span><?=htmlspecialchars($r['country'])?></span>
              <span><?=htmlspecialchars($r['age'])?> <?= t('ago') ?></span>
            </div>
            <div class="reqact">
              <?php if($MEMBER): ?>
                <a class="btn btn-o btn-sm" href="/request-offer?ref=<?= urlencode($r['id']) ?>&seed=1"><?= t('Make an offer') ?></a>
              <?php else: ?>
                <a class="hint" href="/login"><?= t('Sign in to make an offer') ?></a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- post a request -->
    <div>
      <div class="postbox" id="post">
        <h3 class="blocktitle"><?= t('Post a request') ?></h3>
        <p class="hint" style="margin-top:-4px"><?= t("No payment, no commitment. You'll get offers from verified sellers.") ?></p>
        <form method="post" action="/request">
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
          <div style="margin-top:12px"><label class="hint"><?= t('Work email') ?> *</label><input type="email" name="email" required style="width:100%"></div>
          <div style="margin-top:12px"><label class="hint"><?= t('Notes') ?></label><textarea name="notes" rows="2" style="width:100%" placeholder="<?= htmlspecialchars(t('Brands, condition, delivery terms…')) ?>"></textarea></div>
          <button class="btn btn-p" type="submit" style="width:100%;justify-content:center;margin-top:16px"><?= t('Post request &amp; join the queue') ?></button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__.'/inc/foot.php';
