<?php require_once __DIR__.'/inc/products.php'; $PAGE=t('Group buys'); $NAV='groups'; require __DIR__.'/inc/head.php';
$pools=vestra_group_pools();
?>
<style>
.ghow{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin:8px 0 26px}
.ghow .step{background:var(--card,#16161a);border:1px solid var(--line);border-radius:12px;padding:16px}
.ghow .n{display:inline-flex;width:26px;height:26px;align-items:center;justify-content:center;border-radius:50%;background:var(--acc);color:#0e0e11;font-weight:700;font-size:13px;margin-bottom:8px}
.ghow h4{margin:0 0 4px;font-size:15px}.ghow p{margin:0;color:var(--mut);font-size:13px;line-height:1.5}
.gpools{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:18px}
.gpool{background:var(--card,#16161a);border:1px solid var(--line);border-radius:14px;overflow:hidden;display:flex;flex-direction:column}
.gpool .top{height:96px;position:relative;display:flex;align-items:flex-end;padding:12px 14px}
.gpool .top .bn{font-family:'Playfair Display',serif;font-size:20px;color:#fff;text-shadow:0 1px 8px rgba(0,0,0,.5)}
.gpool .body{padding:14px 16px 16px;display:flex;flex-direction:column;gap:10px;flex:1}
.gpool .pname{font-weight:600;font-size:15px;line-height:1.3}
.gbar{height:9px;border-radius:6px;background:rgba(255,255,255,.08);overflow:hidden}
.gbar > i{display:block;height:100%;background:linear-gradient(90deg,var(--acc),#e7cd92);border-radius:6px}
.gmeta{display:flex;justify-content:space-between;font-size:12.5px;color:var(--mut)}
.gprice{display:flex;align-items:baseline;gap:8px}
.gprice .now{font-size:22px;font-weight:700;color:var(--acc)}.gprice .was{font-size:13px;color:var(--mut);text-decoration:line-through}
.gpill{display:inline-flex;align-items:center;gap:5px;font-size:11.5px;font-weight:600;padding:3px 9px;border-radius:20px}
.gpill.open{background:rgba(201,168,106,.14);color:var(--acc)}
.gpill.funded{background:rgba(70,180,120,.16);color:#5fcf94}
.gpill.expired{background:rgba(255,255,255,.06);color:var(--mut)}
@media(max-width:680px){.ghow{grid-template-columns:1fr}}
</style>
<div class="wrap">
  <div class="phead">
    <div class="crumbs"><a href="/"><?= t('Home') ?></a> · <?= t('Group buys') ?></div>
    <h1><?= t('Group buys') ?></h1>
    <p class="sub" style="max-width:640px"><?= t('Pool your order with other verified buyers to reach the wholesale MOQ together — and unlock the best tier price, even if you only need a small quantity.') ?></p>
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
      $statusLabel=['open'=>$p['_daysLeft'].' '.t('days left'),'funded'=>t('Target reached'),'expired'=>t('Closed')][$p['_status']]; ?>
      <a class="gpool" href="/group?id=<?=urlencode($p['id'])?>" style="text-decoration:none;color:inherit">
        <div class="top" style="background:linear-gradient(135deg,<?=$p['accent']?>,#0e0e11)">
          <?php if(!empty($p['image']) && $MEMBER): ?><img src="<?=htmlspecialchars($p['image'])?>" alt="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:.55"><?php endif; ?>
          <span class="bn" style="position:relative"><?=htmlspecialchars($p['brand'])?></span>
        </div>
        <div class="body">
          <div class="pname"><?=htmlspecialchars($p['name'])?></div>
          <div class="gprice"><span class="now"><?=eur($tierTop)?></span><span class="hint">/ <?=htmlspecialchars($p['unit'])?></span><span class="was"><?=eur($from)?></span></div>
          <div class="gbar"><i style="width:<?=$p['_pct']?>%"></i></div>
          <div class="gmeta">
            <span><b style="color:var(--ink,#eee)"><?=number_format($p['_committed'])?></b> / <?=number_format($p['_target'])?> <?=htmlspecialchars($p['unit'])?> · <?=$p['_pct']?>%</span>
            <span class="gpill <?=$p['_status']?>"><?=$statusLabel?></span>
          </div>
          <div class="gmeta"><span>👥 <?=$p['_participants']?> <?= t('buyers in') ?></span><span class="acc"><?= t('Join pool →') ?></span></div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php require __DIR__.'/inc/foot.php';
