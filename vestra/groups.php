<?php
/** VESTRA — Sammelkauf hub: pooled group buys. Premium panel with live stats,
 *  rich pool cards (progress, unlock price, savings, participants) and direct
 *  view / join / message-the-seller actions. */
require_once __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/auth.php';
$PAGE=t('Group buys'); $NAV='groups'; $META=t('VESTRA group buys — pool wholesale orders with other verified boutiques to reach minimum quantities and unlock better trade pricing on authentic branded fashion.'); require __DIR__.'/inc/head.php';
$pools=vestra_group_pools();

/* Hub stats across all pools. */
$openPools=count(array_filter($pools,fn($p)=>$p['_status']==='open'));
$totCommitted=array_sum(array_map(fn($p)=>(int)$p['_committed'],$pools));
$totBuyers=array_sum(array_map(fn($p)=>(int)$p['_participants'],$pools));
$bestSave=0;
foreach($pools as $p){ $from=$p['tiers'][0]['price']??0; $gp=$p['_gprice']??0;
  if($from>0&&$gp>0) $bestSave=max($bestSave,(int)round(100*($from-$gp)/$from)); }
$isBuyer=$AUTH_USER && ($AUTH_USER['type']??'')==='buyer';
?>
<style>
.ghow{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin:8px 0 26px}
.ghow .step{background:var(--card,#16161a);border:1px solid var(--line);border-radius:12px;padding:16px}
.ghow .n{display:inline-flex;width:26px;height:26px;align-items:center;justify-content:center;border-radius:50%;background:var(--acc);color:#0e0e11;font-weight:700;font-size:13px;margin-bottom:8px}
.ghow h4{margin:0 0 4px;font-size:15px}.ghow p{margin:0;color:var(--mut);font-size:13px;line-height:1.5}
.gpools{display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:18px}
.gpool{background:var(--card,#16161a);border:1px solid var(--line);border-radius:14px;overflow:hidden;display:flex;flex-direction:column;transition:border-color .2s,transform .2s}
.gpool:hover{border-color:rgba(201,168,106,.45);transform:translateY(-2px)}
.gpool .top{height:104px;position:relative;display:flex;align-items:flex-end;padding:12px 14px}
.gpool .top .bn{font-family:'Playfair Display',serif;font-size:20px;color:#fff;text-shadow:0 1px 8px rgba(0,0,0,.5)}
.gpool .savebadge{position:absolute;top:10px;right:10px;background:#e0533a;color:#fff;font-size:11px;font-weight:800;padding:4px 9px;border-radius:999px;letter-spacing:.3px}
.gpool .body{padding:14px 16px 16px;display:flex;flex-direction:column;gap:10px;flex:1}
.gpool .pname{font-weight:600;font-size:15px;line-height:1.3}
.gbar{height:9px;border-radius:6px;background:rgba(255,255,255,.08);overflow:hidden}
.gbar > i{display:block;height:100%;background:linear-gradient(90deg,var(--acc),#e7cd92);border-radius:6px}
.gmeta{display:flex;justify-content:space-between;font-size:12.5px;color:var(--mut);gap:8px;flex-wrap:wrap}
.gprice{display:flex;align-items:baseline;gap:8px}
.gprice .now{font-size:22px;font-weight:700;color:var(--acc)}.gprice .was{font-size:13px;color:var(--mut);text-decoration:line-through}
.gpill{display:inline-flex;align-items:center;gap:5px;font-size:11.5px;font-weight:600;padding:3px 9px;border-radius:20px}
.gpill.open{background:rgba(201,168,106,.14);color:var(--acc)}
.gpill.funded{background:rgba(70,180,120,.16);color:#5fcf94}
.gpill.expired{background:rgba(255,255,255,.06);color:var(--mut)}
.gact{display:flex;gap:8px;flex-wrap:wrap;margin-top:auto;padding-top:4px}
@media(max-width:680px){.ghow{grid-template-columns:1fr}}
</style>
<div class="wrap wide">
  <div class="phead" style="margin-bottom:16px">
    <div class="crumbs"><a href="/"><?= t('Home') ?></a> · <?= t('Group buys') ?></div>
    <h1><?= t('Group buys') ?></h1>
    <p class="sub" style="max-width:640px"><?= t('Pool your order with other verified buyers to reach the wholesale MOQ together — and unlock the best tier price, even if you only need a small quantity.') ?></p>
  </div>

  <div class="reqstats">
    <div class="stat"><b><?=$openPools?></b><span><?= t('open pools') ?></span></div>
    <div class="stat"><b><?=number_format($totCommitted)?></b><span><?= t('pieces committed') ?></span></div>
    <div class="stat"><b><?=$totBuyers?></b><span><?= t('buyers pooling') ?></span></div>
    <div class="stat"><b><?=$bestSave?'−'.$bestSave.'%':'—'?></b><span><?= t('best pool saving') ?></span></div>
  </div>

  <div class="ghow">
    <div class="step"><span class="n">1</span><h4><?= t('Join a pool') ?></h4><p><?= t('Commit the quantity you actually need — no minimum of your own.') ?></p></div>
    <div class="step"><span class="n">2</span><h4><?= t('Reach the target together') ?></h4><p><?= t('When all buyers’ quantities add up to the MOQ before the deadline, the wholesale price unlocks for everyone.') ?></p></div>
    <div class="step"><span class="n">3</span><h4><?= t('Pay by invoice, ship separately') ?></h4><p><?= t('You pay by bank transfer against an invoice. The seller ships to each buyer separately. If the target isn’t met, nobody pays.') ?></p></div>
  </div>

  <?php if(!$pools): ?>
    <div class="empty"><?= t('No group buys are open right now.') ?> <a class="acc" href="/shop"><?= t('Browse the catalog →') ?></a></div>
  <?php else: ?>
  <div class="gpools">
    <?php foreach($pools as $p):
      $from=$p['tiers'][0]['price']??vestra_from_price($p); $tierTop=$p['_gprice'];
      $save=($from>0&&$tierTop>0)?(int)round(100*($from-$tierTop)/$from):0;
      $remaining=max(0,(int)$p['_target']-(int)$p['_committed']);
      $statusLabel=['open'=>$p['_daysLeft'].' '.t('days left'),'funded'=>t('Target reached'),'expired'=>t('Closed')][$p['_status']];
      $sellerUid=$p['seller_uid']??''; ?>
      <div class="gpool">
        <a href="/group?id=<?=urlencode($p['id'])?>" style="text-decoration:none;color:inherit;display:block">
          <div class="top" style="background:linear-gradient(135deg,<?= htmlspecialchars(vestra_accent($p)) ?>,#0e0e11)">
            <?php /* group.php ile ayni duzeltme: fotograflar images[] dizisinde, cogunda
                     skaler 'image' alani hic yok -- bu kart fotografi olan bir havuzda bile
                     bos renk blogu gosteriyordu. */ ?>
            <?php $cardImg = vestra_primary_image($p); ?>
            <?php if($cardImg !== '' && $MEMBER): ?><img src="<?=htmlspecialchars($cardImg)?>" alt="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:.55"><?php endif; ?>
            <?php if($save>0): ?><span class="savebadge">−<?=$save?>%</span><?php endif; ?>
            <span class="bn" style="position:relative"><?=htmlspecialchars($p['brand'])?></span>
          </div>
        </a>
        <div class="body">
          <?php $cardModels = vestra_group_models($p); ?>
          <div class="pname"><a href="/group?id=<?=urlencode($p['id'])?>" style="color:inherit;text-decoration:none"><?=htmlspecialchars(vestra_group_title($p))?></a></div>
          <?php if(count($cardModels) > 1): ?>
            <div class="hint" style="font-size:12px;margin-top:-4px"><?= sprintf(t('%d models · mixed assortment'), count($cardModels)) ?></div>
          <?php endif; ?>
          <?php /* Havuz fiyati da toptan fiyat. Bu satir hicbir kapiya bagli degildi:
                   giris yapmamis bir ziyaretci bile rakami goruyordu. */ ?>
          <?php if($PRICES): ?>
          <div class="gprice"><span class="now"><?=vestra_money($tierTop)?></span><span class="hint">/ <?=htmlspecialchars($p['unit'])?></span><span class="was"><?=vestra_money($from)?></span></div>
          <?php else: ?>
          <div class="gprice"><a class="hint" style="color:var(--acc);text-decoration:none" href="<?= htmlspecialchars($PRICE_GATE==='doc' ? $KYC_URL : '/register') ?>">🔒 <?= $PRICE_GATE==='doc' ? t('Upload document') : t('Trade only') ?></a></div>
          <?php endif; ?>
          <div class="gbar"><i style="width:<?=$p['_pct']?>%"></i></div>
          <div class="gmeta">
            <span><b style="color:var(--ink,#eee)"><?=number_format($p['_committed'])?></b> / <?=number_format($p['_target'])?> <?=htmlspecialchars($p['unit'])?> · <?=$p['_pct']?>%</span>
            <span class="gpill <?=$p['_status']?>"><?=$statusLabel?></span>
          </div>
          <div class="gmeta">
            <span>👥 <?=$p['_participants']?> <?= t('buyers in') ?></span>
            <?php if($p['_status']==='open' && $remaining>0): ?><span><?= sprintf(t('%s pc to unlock'), number_format($remaining)) ?></span><?php endif; ?>
          </div>
          <div class="gact">
            <a class="btn btn-p btn-sm" href="/group?id=<?=urlencode($p['id'])?>"><?= t('Join pool →') ?></a>
            <?php if($sellerUid && $isBuyer): ?>
              <details class="respdetails" style="display:inline-block">
                <summary class="btn btn-o btn-sm">💬 <?= t('Ask the seller') ?></summary>
                <form method="post" action="/buyer?tab=messages" style="margin-top:10px;min-width:250px">
                  <input type="hidden" name="_action" value="send_message">
                  <input type="hidden" name="listing_id" value="<?= htmlspecialchars($p['id']) ?>">
                  <textarea name="body" rows="3" required style="width:100%" placeholder="<?= htmlspecialchars(t('Ask about MOQ, samples, delivery…')) ?>"></textarea>
                  <button class="btn btn-p btn-sm" type="submit" style="margin-top:8px"><?= t('Send') ?></button>
                </form>
              </details>
            <?php elseif(!$MEMBER): ?>
              <a class="btn btn-o btn-sm" href="/login?back=<?= urlencode('/groups') ?>">💬 <?= t('Sign in to message') ?></a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php require __DIR__.'/inc/foot.php';
