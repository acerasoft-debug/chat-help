<?php
require_once __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/pools.php';
$p = vestra_group_pool($_GET['id'] ?? '');
if(!$p){ http_response_code(404); $PAGE=t('Not found'); require __DIR__.'/inc/head.php';
  echo '<div class="wrap"><div class="empty">'.t('This group buy is not available.').' <a class="acc" href="/groups">'.t('See open group buys →').'</a></div></div>';
  require __DIR__.'/inc/foot.php'; exit; }
$GTITLE=vestra_group_title($p); $GMODELS=vestra_group_models($p); $GASSORT=count($GMODELS)>1;
$PAGE=$GTITLE.' — '.t('Group buy'); $NAV='groups'; require __DIR__.'/inc/head.php';
$from=$p['tiers'][0]['price']??vestra_from_price($p); $joined=isset($_GET['joined']);
$saving = $from>0 ? (int)round(100*($from-$p['_gprice'])/$from) : 0;
/* Deposit pools charge on join; the legacy free-commitment pools do not. Every
   money-facing branch below keys off $DEPOSIT so one pool type can never render
   the other's copy — a "no charge now" line above a Stripe button would be a
   misrepresentation, not a cosmetic slip. */
$DEPOSIT = pool_has_deposit($p);
$PCT     = pool_deposit_pct($p);
$PCTLBL  = rtrim(rtrim(number_format($PCT,1),'0'),'.');
$BALDAYS = pool_balance_days($p);
$GMIN    = vestra_group_min_qty($p);
$GMINCOL = vestra_group_min_colors($p);
[$TOT0,$DEP0,$BAL0] = pool_split($p,$GMIN);
?>
<style>
.gwrap{display:grid;grid-template-columns:1.05fr .95fr;gap:34px;margin:26px 0 10px}
@media(max-width:860px){.gwrap{grid-template-columns:1fr}}
.ghero{height:240px;border-radius:16px;position:relative;display:flex;align-items:flex-end;padding:18px;overflow:hidden}
.ghero img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:.6}
.ghero .bn{position:relative;font-family:'Playfair Display',serif;font-size:30px;color:#fff;text-shadow:0 2px 12px rgba(0,0,0,.55)}
.gbar{height:13px;border-radius:8px;background:rgba(255,255,255,.09);overflow:hidden;margin:6px 0}
.gbar > i{display:block;height:100%;background:linear-gradient(90deg,var(--acc),#e7cd92)}
.gstat{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:16px 0}
.gstat .c{background:var(--card,#16161a);border:1px solid var(--line);border-radius:12px;padding:12px;text-align:center}
.gstat .v{font-size:20px;font-weight:700}.gstat .v.acc{color:var(--acc)}.gstat .l{font-size:11.5px;color:var(--mut);margin-top:2px}
.gpill{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;padding:4px 11px;border-radius:20px}
.gpill.open{background:rgba(201,168,106,.14);color:var(--acc)}
.gpill.funded{background:rgba(70,180,120,.16);color:#5fcf94}
.gpill.expired{background:rgba(255,255,255,.06);color:var(--mut)}
.glist{width:100%;border-collapse:collapse;margin-top:6px}
.glist td{padding:8px 4px;border-top:1px solid var(--line);font-size:13px}
.gcd{display:flex;gap:8px}
.gcd .u{background:var(--card,#16161a);border:1px solid var(--line);border-radius:9px;padding:7px 0;min-width:52px;text-align:center}
.gcd .u b{font-size:19px;display:block}.gcd .u span{font-size:10px;color:var(--mut);text-transform:uppercase;letter-spacing:.5px}
/* Karma havuzun kapsadigi modeller */
.gmods{display:grid;grid-template-columns:repeat(auto-fill,minmax(96px,1fr));gap:10px}
.gmod{display:flex;flex-direction:column;gap:5px;text-decoration:none;color:inherit}
.gmod img,.gmod .ph{width:100%;aspect-ratio:1;object-fit:cover;border-radius:10px;border:1px solid var(--line);background:var(--card,#16161a)}
.gmod .ph{display:flex;align-items:center;justify-content:center;font-size:10px;color:var(--mut);text-align:center;padding:4px;line-height:1.3}
.gmod .cap{font-size:10.5px;color:var(--mut);text-align:center;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.gmod:hover img,.gmod:hover .ph{border-color:rgba(201,168,106,.5)}
</style>
<div class="wrap">
  <div class="crumbs" style="margin-top:24px"><a href="/"><?= t('Home') ?></a> · <a href="/groups"><?= t('Group buys') ?></a> · <?=htmlspecialchars($p['brand'])?></div>

  <?php if($joined): ?>
    <div class="banner ok" style="margin-top:16px">✓ <?= t('You’ve joined the pool. We’ll email you when the target is reached and your invoice is ready.') ?> <?= t('Reference:') ?> <b><?=htmlspecialchars(substr($_GET['ref']??'',0,16))?></b></div>
  <?php endif; ?>

  <div class="gwrap">
    <!-- LEFT: the pool -->
    <div>
      <div class="ghero" style="background:linear-gradient(135deg,<?= htmlspecialchars(vestra_accent($p)) ?>,#0e0e11)">
        <?php /* vestra_primary_image(), duz $p['image'] degil: urunlerin fotograflari
                 images[] dizisinde duruyor ve cogunda skaler 'image' alani hic yok --
                 bu sayfa o yuzden fotografi olan bir urunde bile bos renk blogu
                 gosteriyordu. Uyelik kapisi korunuyor: fotograflar site genelinde
                 uyelere ozel (bkz. shop.php). */ ?>
        <?php $heroImg = vestra_primary_image($p); ?>
        <?php if($heroImg !== '' && $MEMBER): ?><img src="<?=htmlspecialchars($heroImg)?>" alt=""><?php endif; ?>
        <span class="bn"><?=htmlspecialchars($p['brand'])?></span>
      </div>
      <h1 style="margin:16px 0 4px"><?=htmlspecialchars($GTITLE)?></h1>
      <p class="sub"><?=htmlspecialchars($p['desc'])?></p>

      <?php /* Tek urunlu havuzda katalogdaki BUTUN fotograflar; karma havuzda
               modellerin kendi fotograflari (asagidaki blok). Ikisi birden
               gosterilmiyor -- karma havuzda barindirici urunun galerisi
               yanilticidir, o sadece 10 modelden biri. */ ?>
      <?php $GAL = $GASSORT ? [] : vestra_group_gallery($p); ?>
      <?php if($MEMBER && count($GAL) > 1): ?>
        <h3 style="margin:22px 0 8px"><?= sprintf(t('%d photos'), count($GAL)) ?></h3>
        <div class="gmods">
          <?php foreach($GAL as $gi): ?>
            <span class="gmod"><img src="<?=htmlspecialchars($gi)?>" alt="<?=htmlspecialchars($p['name'])?>" loading="lazy"></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if($GASSORT): ?>
        <h3 style="margin:22px 0 8px"><?= sprintf(t('%d models in this pool'), count($GMODELS)) ?></h3>
        <p class="hint" style="margin:0 0 12px"><?= t('Your quantity is spread across these models. Tell us your split after the pool closes — or leave it to us and we send a balanced assortment.') ?></p>
        <div class="gmods">
          <?php foreach($GMODELS as $mdl): ?>
            <a class="gmod" href="/product?id=<?=urlencode($mdl['id'])?>">
              <?php /* Ayni uyelik kapisi: fotograflar site genelinde uyelere ozel. */ ?>
              <?php if($MEMBER && $mdl['image']!==''): ?>
                <img src="<?=htmlspecialchars($mdl['image'])?>" alt="<?=htmlspecialchars($mdl['name'])?>" loading="lazy">
              <?php else: ?>
                <span class="ph"><?= htmlspecialchars(substr($mdl['sku']!==''?$mdl['sku']:$mdl['name'],0,12)) ?></span>
              <?php endif; ?>
              <span class="cap"><?=htmlspecialchars($mdl['sku']!==''?$mdl['sku']:$mdl['name'])?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div style="display:flex;align-items:center;gap:12px;margin:10px 0 2px">
        <span class="gpill <?=$p['_status']?>">
          <?= ['open'=>'⏳ '.t('Open'),'funded'=>'✓ '.t('Target reached'),'expired'=>'• '.t('Closed')][$p['_status']] ?>
        </span>
        <span class="hint"><?=$p['_participants']?> <?= t('buyers committed') ?></span>
      </div>

      <div class="gbar"><i style="width:<?=$p['_pct']?>%"></i></div>
      <div class="gmeta" style="display:flex;justify-content:space-between;font-size:13px;color:var(--mut)">
        <span><b style="color:var(--ink,#eee);font-size:15px"><?=number_format($p['_committed'])?></b> / <?=number_format($p['_target'])?> <?=htmlspecialchars($p['unit'])?></span>
        <span><b style="color:var(--acc)"><?=$p['_pct']?>%</b> · <?=number_format($p['_remaining'])?> <?=htmlspecialchars($p['unit'])?> <?= t('to go') ?></span>
      </div>

      <div class="gstat">
        <div class="c"><div class="v acc"><?=vestra_money($p['_gprice'])?></div><div class="l"><?= t('unlocked unit price') ?></div></div>
        <div class="c"><div class="v" style="text-decoration:line-through;color:var(--mut)"><?=vestra_money($from)?></div><div class="l"><?= t('your solo price') ?></div></div>
        <div class="c"><div class="v acc">−<?=$saving?>%</div><div class="l"><?= t('you save') ?></div></div>
      </div>

      <?php if($p['_status']==='open'): ?>
        <div style="margin:18px 0 6px"><span class="hint"><?= t('Pool closes in') ?></span></div>
        <div class="gcd" id="cd" data-deadline="<?=strtotime($p['_deadline'])*1000?>">
          <div class="u"><b id="cd_d">–</b><span><?= t('days') ?></span></div>
          <div class="u"><b id="cd_h">–</b><span><?= t('hrs') ?></span></div>
          <div class="u"><b id="cd_m">–</b><span><?= t('min') ?></span></div>
          <div class="u"><b id="cd_s">–</b><span><?= t('sec') ?></span></div>
        </div>
      <?php endif; ?>

      <h3 style="margin:26px 0 6px"><?= t('Who’s in this pool') ?></h3>
      <table class="glist">
        <?php if($p['group_seed_n']??0): ?>
          <tr><td>🏷️ <?= sprintf(t('%d verified boutiques already committed'), (int)$p['group_seed_n']) ?></td><td class="r hint"><?=number_format((int)($p['group_seed']??0))?> <?=htmlspecialchars($p['unit'])?></td></tr>
        <?php endif; ?>
        <?php foreach(array_slice($p['_commits'],0,8) as $c): ?>
          <tr><td>👤 <?=htmlspecialchars($c['country']?:t('Verified buyer'))?> · <span class="hint"><?=htmlspecialchars(substr($c['timestamp']??'',0,10))?></span></td><td class="r"><?=htmlspecialchars($c['qty']??'')?> <?=htmlspecialchars($p['unit'])?></td></tr>
        <?php endforeach; ?>
        <?php if(!($p['group_seed_n']??0) && !$p['_commits']): ?>
          <tr><td class="hint"><?= t('Be the first to join this pool.') ?></td><td></td></tr>
        <?php endif; ?>
      </table>
    </div>

    <!-- RIGHT: join form -->
    <div>
      <div class="order-box" style="position:sticky;top:90px">
        <?php if($p['_status']!=='open'): ?>
          <div class="banner <?=$p['_status']==='funded'?'ok':'info'?>">
            <?= $p['_status']==='funded' ? t('This pool reached its target — the wholesale price is locked in for everyone who joined.') : t('This pool has closed. Browse other open group buys.') ?>
          </div>
          <a class="btn btn-o" href="/groups" style="width:100%;justify-content:center;margin-top:12px"><?= t('See open group buys') ?></a>
        <?php elseif(!$PRICES): ?>
          <?php /* Havuz fiyati da bir toptan fiyat: katalogla ayni kapiya bagli
                   kalmali, yoksa /shop'ta gizlenen rakam buradan sizar. */ ?>
          <div class="gate">
          <?php if($PRICE_GATE==='approval'): ?>
            <?php /* Eski metin "yukleyince hemen acilir, onay beklemezsiniz" diyordu:
                     KURAL 2'nin tam tersi. Kapiyi onay acar. */ ?>
            <h3 style="margin:0 0 6px"><?= t('Your account is being reviewed') ?></h3>
            <p style="color:var(--mut);margin:0 0 16px"><?= t('Wholesale prices and pool access open as soon as we activate your account — usually the same day.') ?></p>
            <?php if(!in_array(auth_trade_doc_status($AUTH_USER), ['uploaded','approved'], true)): ?>
            <a class="btn btn-o" href="<?= htmlspecialchars($KYC_URL) ?>" style="width:100%;justify-content:center"><?= t('Add document') ?></a>
            <?php endif; ?>
          <?php else: ?>
            <h3 style="margin:0 0 6px"><?= t('Verified buyers only') ?></h3>
            <p style="color:var(--mut);margin:0 0 16px"><?= t('Sign in as a verified business buyer to join this pool.') ?></p>
            <a class="btn btn-p" href="/login?back=<?=urlencode('/group?id='.$p['id'])?>" style="width:100%;justify-content:center"><?= t('Sign in') ?></a>
          <?php endif; ?>
          </div>
        <?php else: ?>
          <h3 style="margin:0 0 4px"><?= t('Join this pool') ?></h3>
          <?php if($DEPOSIT): ?>
            <p class="hint" style="margin:0 0 14px"><?= sprintf(t('Commit your quantity at the unlocked price of %s / %s. A %s%% deposit is charged now to secure your place; the balance follows once the pool reaches %s %s.'), vestra_money($p['_gprice']), htmlspecialchars($p['unit']), $PCTLBL, number_format($p['_target']), htmlspecialchars($p['unit'])) ?></p>
          <?php else: ?>
            <p class="hint" style="margin:0 0 14px"><?= sprintf(t('Commit your quantity at the unlocked price of %s / %s. You’re only charged if the pool reaches %s %s.'), vestra_money($p['_gprice']), htmlspecialchars($p['unit']), number_format($p['_target']), htmlspecialchars($p['unit'])) ?></p>
          <?php endif; ?>
          <?php /* onsubmit dogrulamasi product.php'deki vcolOk() ile ayni mantik, ama
                   bu sayfa product.php'yi yuklemedigi icin kendi kopyasi asagida.
                   Sunucu tarafi (group-checkout.php) ayrica dogruluyor -- JS kapali
                   bir tarayici kaporayi eksik renkle odeyemesin. */ ?>
          <form method="post" action="<?= $DEPOSIT ? '/group-checkout' : '/group-join' ?>" onsubmit="return gcolOk(this)">
            <input type="hidden" name="id" value="<?=htmlspecialchars($p['id'])?>">
            <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px">
            <label class="hint"><?= t('Quantity you need') ?> (<?=htmlspecialchars($p['unit'])?>) — <?= t('min') ?> <?=number_format($GMIN)?></label>
            <input type="number" name="qty" min="<?=$GMIN?>" value="<?=$GMIN?>" required style="width:100%" id="gq" oninput="gcalc()">
            <div class="calc" style="margin:10px 0">
              <div class="total"><span id="gtot"><?=vestra_money($TOT0)?></span> <small><?= t('at unlocked price · excl. taxes & shipping') ?></small></div>
            </div>
            <?php if($GMINCOL > 0 && !empty($p['colors'])): ?>
              <?php /* Urun sayfasindaki ile AYNI bilesen (.colorpick/.colorchip/data-min):
                       alici ayni kurali iki yerde iki farkli sekilde ogrenmesin. */ ?>
              <div style="margin:12px 0">
                <label class="hint"><?= t('Choose your colours') ?> — <?= sprintf(t('at least %d'), $GMINCOL) ?></label>
                <div class="colorpick" data-min="<?=$GMINCOL?>">
                  <?php $pal=vestra_colors(); foreach((array)$p['colors'] as $cn): ?>
                    <label class="colorchip"><input type="checkbox" name="colors[]" value="<?=htmlspecialchars($cn)?>"><span class="cdot" style="background:<?= $pal[$cn]??'#666' ?>"></span><?=htmlspecialchars(t($cn))?></label>
                  <?php endforeach; ?>
                </div>
                <div class="warn vcolwarn" style="display:none;margin-top:8px"><?= vestra_colours_warn($GMINCOL) ?></div>
              </div>
            <?php endif; ?>
            <?php if($DEPOSIT): ?>
              <div class="calc" style="margin:10px 0;display:grid;gap:6px">
                <div style="display:flex;justify-content:space-between;font-size:13.5px">
                  <span><?= sprintf(t('Deposit now (%s%%)'), $PCTLBL) ?></span>
                  <b id="gdep" style="color:var(--acc)"><?=vestra_money($DEP0)?></b>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:13.5px;color:var(--mut)">
                  <span><?= sprintf(t('Balance, due %d days after the pool closes'), $BALDAYS) ?></span>
                  <b id="gbal"><?=vestra_money($BAL0)?></b>
                </div>
              </div>
            <?php endif; ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div><label class="hint"><?= t('Company') ?> *</label><input name="company" required style="width:100%"></div>
              <div><label class="hint"><?= t('Contact name') ?> *</label><input name="name" required style="width:100%"></div>
              <div><label class="hint"><?= t('Work email') ?> *</label><input type="email" name="email" required style="width:100%"></div>
              <div><label class="hint"><?= t('Country') ?></label><input name="country" style="width:100%"></div>
            </div>
            <label style="display:flex;gap:9px;align-items:flex-start;margin:14px 0 4px;font-size:13px;color:var(--mut);cursor:pointer">
              <input type="checkbox" name="consent" value="1" required style="margin-top:3px;flex:none">
              <span><?= sprintf(t('I have read and accept the %s, %s and %s, and I confirm I act as a business.'),
                '<a href="/legal?doc=terms" target="_blank" class="acc">'.t('Terms of Service').'</a>',
                '<a href="/legal?doc=privacy" target="_blank" class="acc">'.t('Privacy Policy').'</a>',
                '<a href="/legal?doc=payments" target="_blank" class="acc">'.t('Payments &amp; Escrow').'</a>') ?></span>
            </label>
            <button class="btn btn-p" type="submit" style="width:100%;justify-content:center;margin-top:10px"><?= $DEPOSIT ? sprintf(t('Pay %s deposit & join'), vestra_money($DEP0)) : t('Commit to the pool') ?></button>
            <?php if($DEPOSIT): ?>
              <div class="hint" style="margin-top:10px"><?= t('Secure payment via Stripe. If the pool does not reach its target we extend it once — and if it still falls short, your deposit is refunded in full.') ?></div>
            <?php else: ?>
              <div class="hint" style="margin-top:10px"><?= t('No charge now. If the pool fails, you pay nothing.') ?></div>
            <?php endif; ?>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<script>
function gcolOk(f){
  var cp=f.querySelector('.colorpick[data-min]'); if(!cp) return true;
  var need=parseInt(cp.dataset.min)||0, got=cp.querySelectorAll('input:checked').length;
  var w=f.querySelector('.vcolwarn');
  if(got<need){ if(w) w.style.display='block'; return false; }
  if(w) w.style.display='none'; return true;
}
var GP=<?=$p['_gprice']?>, GMOQ=<?=$GMIN?>, GPCT=<?=$PCT?>;
function geur(n){ return '€'+n.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function gcalc(){ var q=parseInt(document.getElementById('gq').value)||0; if(q<GMOQ)q=GMOQ;
  var tot=Math.round(GP*q*100)/100;
  document.getElementById('gtot').textContent=geur(tot);
  var dep=document.getElementById('gdep'); if(!dep) return;
  /* Same order as pool_split(): round the deposit, derive the balance from it.
     Computing both independently drifts a cent and the page stops matching the
     Stripe amount the buyer is about to be charged. */
  var d=Math.round(tot*GPCT)/100;
  dep.textContent=geur(d);
  document.getElementById('gbal').textContent=geur(Math.round((tot-d)*100)/100); }
(function(){ var el=document.getElementById('cd'); if(!el) return;
  var dl=parseInt(el.dataset.deadline);
  function tick(){ var s=Math.max(0,Math.floor((dl-Date.now())/1000));
    var d=Math.floor(s/86400),h=Math.floor(s%86400/3600),m=Math.floor(s%3600/60),sec=s%60;
    cd_d.textContent=d; cd_h.textContent=h; cd_m.textContent=String(m).padStart(2,'0'); cd_s.textContent=String(sec).padStart(2,'0'); }
  tick(); setInterval(tick,1000);
})();
</script>
<?php require __DIR__.'/inc/foot.php';
