<?php
/**
 * VESTRA — Sourcing board (buyer "Anzeigen"): buyers post what they're looking
 * for; verified sellers answer with offers or a direct message. Premium hub:
 * stats strip, rich request cards with offer counts, seller→buyer messaging,
 * and an inline view of received offers for the request's owner.
 */
require __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/auth.php';
$PAGE=t('Sourcing requests'); $NAV='requests'; $META=t('VESTRA sourcing requests — post what your boutique needs and let KYC-verified wholesale suppliers quote. Authentic branded fashion, invoice-based B2B ordering across Europe.'); require __DIR__.'/inc/head.php';
$posted=isset($_GET['posted']);

/* Real buyer requests (no demo seeds — the board only shows genuine Anzeigen). */
$userReqs=[]; $f=__DIR__.'/data/requests.csv';
if(is_readable($f) && ($h=@fopen($f,'r'))){ $head=fgetcsv($h, null, ',', '"', '\\'); while(($row=fgetcsv($h, null, ',', '"', '\\'))!==false){ if($head){ $n=count($head); $row=array_slice(array_pad($row,$n,''),0,$n); $userReqs[]=array_combine($head,$row);} } fclose($h); }
$userReqs=array_reverse($userReqs);

/* Offers per request (badge + owner's inline list). */
$offersByRef=[]; $fo=__DIR__.'/data/request_offers.csv';
if(is_readable($fo) && ($h=@fopen($fo,'r'))){ $head=fgetcsv($h, null, ',', '"', '\\'); while(($row=fgetcsv($h, null, ',', '"', '\\'))!==false){ if($head){ $n=count($head); $row=array_slice(array_pad($row,$n,''),0,$n); $o=array_combine($head,$row); $offersByRef[$o['request_ref']??''][]=$o; } } fclose($h); }

/* Example requests — shown so a new board is not an empty page, and marked as examples on
   every card. They are NOT written to requests.csv and NOT counted in the stats above: a
   seller who answers a request discloses their company, e-mail, unit price and delivery
   terms into request_offers.csv, and against an invented buyer there is no address for any
   of it to reach. So these carry no "Make an offer" button at all — they demonstrate what a
   good request looks like and point the reader at the form. Real requests always render
   above them. Delete this array once the board carries genuine traffic. */
$exampleReqs = [
  ['title'=>t('Children\'s shoes, boxed assortments — sizes 24–35'),
   'cat'=>'Flats','qty'=>'40–60 boxes','target'=>'7–11 €','country'=>'ES',
   'notes'=>t('Two children\'s shops, restocking for the new school term. Boxed size runs suit us better than loose pairs — a mixed box per model is fine. Leather and textile both work; we need the size run stated per box before we commit.')],
  ['title'=>t('Barefoot / flexible-sole kids\' footwear'),
   'cat'=>'Sneakers','qty'=>'200+ pairs','target'=>'8–14 €','country'=>'DE',
   'notes'=>t('Growing demand for respectful footwear in our stores and parents ask for it by name. Wide toe box and a genuinely flexible sole are the two things we are judged on. Happy to start with one model across the full size run.')],
  ['title'=>t('Classic cotton piqué polos, mixed-size packs'),
   'cat'=>'Polos','qty'=>'300–500','target'=>'45–70 €','country'=>'NL',
   'notes'=>t('Multi-brand store, two locations. Prefer mixed-size packs S–XXL, classic colours plus one seasonal. Need an invoice with the full EU trail and delivery before the season set.')],
  ['title'=>t('Graphic tees and logo sweats — streetwear labels'),
   'cat'=>'T-Shirts','qty'=>'150–300','target'=>'45–90 €','country'=>'PL',
   'notes'=>t('Buying for a younger floor. Logo-forward pieces that photograph well online sell fastest for us. Article codes and photographs needed before we commit; can repeat quickly if sell-through holds.')],
  ['title'=>t('Winter knitwear and hoodies, autumn delivery'),
   'cat'=>'Hoodies & Sweatshirts','qty'=>'200–400','target'=>'55–95 €','country'=>'AT',
   'notes'=>t('Alpine town, season starts early for us. Heavier weights and muted colours move best. Would take a mixed series across two or three models rather than depth in one.')],
  ['title'=>t('House slippers for the winter floor'),
   'cat'=>'Slippers','qty'=>'30–50 boxes','target'=>'6–9 €','country'=>'FR',
   'notes'=>t('Seasonal buy, sells from October to February. Adult and children\'s both. Boxed assortments are fine; we mainly need to know what the size split inside a box looks like.')],
  ['title'=>t('Men\'s leather shoes, sizes 40–45'),
   'cat'=>'Loafers','qty'=>'120–200 pairs','target'=>'18–28 €','country'=>'IT',
   'notes'=>t('Classic town shoe for an older customer. Real leather upper matters, the sole less so. Would confirm a first test order the same week if photographs and the size run check out.')],
  ['title'=>t('Denim, Italian sizing 44–54'),
   'cat'=>'Jeans','qty'=>'120–200','target'=>'110–160 €','country'=>'GR',
   'notes'=>t('Two stores, distressed and clean washes both sell for us. A mixed series of 10 per model is fine. Stock on hand matters more than price — the season is already running.')],
];

$openCount=count($userReqs);
$offerCount=array_sum(array_map('count',$offersByRef));
$cats=vestra_cats();

/* Arz tarafi rakamlari. Pano yeniyken "0 acik talep / 0 teklif" iki sifir gosteriyordu
   ve bir alicinin gordugu ilk sey buydu -- talep yoklugu, tedarik yoklugu gibi okunuyor.
   Oysa arz tarafi bos degil: katalog dolu ve saticilar dogrulanmis. Asagidaki sayilarin
   hepsi CANLI veriden gelir, hicbiri uydurma degil; sifir olanlar zaten gizleniyor. */
$liveProducts = vestra_products();
$liveCount    = count($liveProducts);
$brandCount   = count(array_unique(array_filter(array_map(
    static fn($p) => trim((string)($p['brand'] ?? '')), $liveProducts))));
/* Panoda gercek hareket: en yeni ilanlar. Talep tarafi hic yazi almamis olsa bile
   sayfada gercekten olan bir sey durur, ve okuyucuyu katalogda ise yarar bir yere atar. */
$freshest = $liveProducts;
usort($freshest, static fn($a, $b) =>
    strtotime((string)($b['added_at'] ?? '1970-01-01')) <=> strtotime((string)($a['added_at'] ?? '1970-01-01')));
$freshest = array_slice(array_filter($freshest, static fn($p) => !empty($p['added_at'])), 0, 6);
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
    <?php /* Talep tarafi rakamlari sadece GERCEKTEN varsa cikar: "0 acik talep" yazmak
             panoyu olu gosteriyor ve zaten altindaki liste ayni seyi soyluyor. */ ?>
    <?php if($openCount): ?><div class="stat"><b><?=$openCount?></b><span><?= t('open requests') ?></span></div><?php endif; ?>
    <?php if($offerCount): ?><div class="stat"><b><?=$offerCount?></b><span><?= t('offers sent') ?></span></div><?php endif; ?>
    <div class="stat"><b><?=$liveCount?></b><span><?= t('live listings') ?></span></div>
    <div class="stat"><b><?=$brandCount?></b><span><?= t('brands ready to quote') ?></span></div>
    <div class="stat"><b>✓ <?= t('verified') ?></b><span><?= t('sellers only') ?></span></div>
  </div>

  <?php if($freshest): ?>
  <div class="reqfresh">
    <div class="reqfresh-head">
      <span class="reqfresh-title"><?= t('Just added to the catalog') ?></span>
      <a class="acc" href="/shop"><?= t('Browse all') ?> →</a>
    </div>
    <div class="reqfresh-rail">
      <?php foreach($freshest as $fp):
        /* Fotograflar katalogla AYNI kurala tabi: misafire gosterilmez, marka karosu
           kapi olarak kalir. Burada gevsetmek, /shop'taki kapiyi anlamsiz kilardi. */
        $fimg = $MEMBER ? vestra_primary_image($fp) : ''; ?>
        <a class="reqfresh-card" href="/product?id=<?= urlencode((string)($fp['id'] ?? '')) ?>">
          <span class="reqfresh-thumb" style="background:linear-gradient(135deg,<?= htmlspecialchars(vestra_accent($fp)) ?>,#0e0e11)">
            <?php if($fimg): ?><img src="<?= htmlspecialchars($fimg) ?>" alt="" loading="lazy">
            <?php else: echo vestra_brand_card((string)($fp['brand'] ?? '')); endif; ?>
          </span>
          <span class="reqfresh-brand"><?= htmlspecialchars((string)($fp['brand'] ?? '')) ?></span>
          <span class="reqfresh-name"><?= htmlspecialchars((string)($fp['name'] ?? '')) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="reqlayout">
    <!-- the queue -->
    <div>
      <h3 class="blocktitle"><?= t('Open requests') ?> <span class="hint">— <?= t('newest first') ?></span></h3>
      <div class="reqlist">
        <?php if(!$userReqs): ?>
          <div class="empty" style="padding:26px 20px"><?= t('No open requests yet — be the first: post what you\'re looking for and verified sellers will come to you.') ?></div>
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

        <?php if($exampleReqs): ?>
          <div class="exhead">
            <span class="exline"></span>
            <span class="exlabel"><?= t('What a good request looks like') ?></span>
            <span class="exline"></span>
          </div>
          <p class="hint" style="margin:0 0 12px;font-size:12.5px;line-height:1.55">
            <?= t('These are illustrations, not live buyers — they show the detail that gets a fast, accurate quote. Post yours and it appears above them.') ?>
          </p>
          <?php foreach($exampleReqs as $x): ?>
            <div class="reqcard isexample">
              <div class="reqtop">
                <span class="status example"><?= t('Example') ?></span>
              </div>
              <div class="reqtitle"><?= htmlspecialchars($x['title']) ?></div>
              <div class="reqmeta">
                <span><?= htmlspecialchars(t($x['cat'])) ?></span>
                <span><?= t('Qty') ?> <b><?= htmlspecialchars($x['qty']) ?></b></span>
                <span><?= t('Target') ?> <b><?= htmlspecialchars($x['target']) ?></b></span>
                <span>📍 <?= htmlspecialchars($x['country']) ?></span>
              </div>
              <p class="hint" style="margin:8px 0 0;line-height:1.5"><?= htmlspecialchars($x['notes']) ?></p>
              <div class="reqact">
                <a class="btn btn-o btn-sm" href="#post"><?= t('Post a request like this') ?></a>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
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
