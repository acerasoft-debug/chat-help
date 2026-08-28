<?php
/**
 * VESTRA — Dropshipping, herkese acik anlatim sayfasi.
 *
 * /dropship SATIN ALMA sayfasi ve ticari hesap istiyor. Burasi ondan ayri ve
 * bilerek acik: soguk e-postalarda ve ortak yazismalarinda verilecek baglanti
 * bu -- kosullari okumak icin once hesap acmasi gereken bir kisi, kosullari
 * hic okumaz. Fiyat rakami yok, dolayisiyla acik olmasinin bir bedeli de yok.
 *
 * BUTUN SAYILAR KODDAN OKUNUYOR (zam orani, bolgeler, ucretler, sureler, kapali
 * markalar). Elle yazilmis bir tablo, ilk kargo zammindan sonra sessizce yalan
 * soylemeye baslar -- ve burasi ortagin fiyat verirken guvendigi sayfa.
 */
require_once __DIR__.'/inc/i18n.php';
require_once __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/dropship.php';

$PAGE = t('Dropshipping');
$NAV  = '';
$META = 'VESTRA dropshipping for trade partners — order a single piece after your customer buys, shipped direct to them. Wholesale price plus 20%, flat shipping by zone, no minimum, API included.';
require __DIR__.'/inc/head.php';

$dsZones   = vestra_dropship_zones();
$dsMarkup  = (int)round(VESTRA_DROPSHIP_MARKUP * 100);
$dsBrands  = vestra_dropship_excluded_brands();
$dsTerms   = vestra_dropship_excluded_terms();
?>
<style>
.dsdoc{max-width:860px;margin:0 auto;padding:0 0 60px}
.dsdoc h1{font-size:36px;line-height:1.15;margin:0 0 12px}
.dsdoc h2{font-size:21px;margin:38px 0 12px;padding-top:20px;border-top:1px solid var(--line)}
.dsdoc h3{font-size:15px;margin:24px 0 8px;color:var(--acc)}
.dsdoc p,.dsdoc li{color:var(--mut);line-height:1.7;font-size:14.5px}
.dsdoc li{margin-bottom:6px}
.dsdoc code{font-size:13px;background:rgba(255,255,255,.06);padding:2px 6px;border-radius:5px;color:var(--ink)}
.dsdoc pre{background:rgba(255,255,255,.04);border:1px solid var(--line);border-radius:10px;
  padding:14px 16px;overflow-x:auto;font-size:12.5px;line-height:1.65;color:var(--ink);margin:0 0 16px}
.dsdoc pre code{background:none;padding:0}
.dsdoc table{width:100%;border-collapse:collapse;font-size:13.5px;margin:0 0 16px}
.dsdoc th,.dsdoc td{text-align:left;padding:8px 10px;border-bottom:1px solid var(--line);vertical-align:top}
.dsdoc th{color:var(--ink);font-weight:600;white-space:nowrap}
.dsdoc td:first-child{white-space:nowrap;color:var(--ink)}
.dsnote{border:1px solid rgba(169,127,44,.45);background:rgba(169,127,44,.07);
  border-radius:10px;padding:14px 16px;margin:0 0 18px}
.dsnote p{margin:0;color:var(--ink);font-size:14px}
.dsnote p+p{margin-top:8px}
.dsscroll{overflow-x:auto}
.dssteps{counter-reset:dsstep;list-style:none;padding:0;margin:0 0 16px}
.dssteps li{counter-increment:dsstep;position:relative;padding-left:38px;margin-bottom:12px}
.dssteps li::before{content:counter(dsstep);position:absolute;left:0;top:1px;width:24px;height:24px;
  border-radius:50%;border:1px solid var(--acc);color:var(--acc);font-size:12px;font-weight:700;
  display:flex;align-items:center;justify-content:center}
.dscta{display:flex;gap:10px;flex-wrap:wrap;margin:22px 0 0}
</style>

<div class="wrap dsdoc">
  <div class="crumbs" style="margin:26px 0 10px"><a href="/"><?= t('Home') ?></a> · <?= t('Dropshipping') ?></div>
  <h1><?= t('Dropshipping') ?></h1>
  <p><?= t('Sell from our catalogue without holding stock. You order one piece at a time, after your own customer has bought it from you, and we ship it straight to their address. No minimum, no carton, no warehouse.') ?></p>

  <div class="dsnote">
    <p><b><?= t('This is a trade service, not a consumer shop.') ?></b>
       <?= t('The order is placed by a verified trade partner on behalf of their own customer. VESTRA has no contract of sale with that customer — they remain yours, and after-sales is between you and them. The full wording is clause 2a of the') ?>
       <a class="acc" href="/legal?doc=terms"><?= t('Terms of Service') ?></a>.</p>
  </div>

  <h2><?= t('What it costs') ?></h2>
  <p><?= sprintf(t('The dropship price of an article is the wholesale price of its <b>smallest quantity tier</b>, plus <b>%d%%</b>. The smallest tier is the most expensive one, because volume discounts belong to volume; a single piece does not earn the 300-piece price. The added margin covers picking, packing and invoicing one unit instead of a carton.'), $dsMarkup) ?></p>
  <p><?= t('The figure you see on an article page is the final goods price. Shipping is added once per order, by destination:') ?></p>

  <div class="dsscroll"><table>
    <tr><th><?= t('Destination') ?></th><th><?= t('Shipping') ?></th><th><?= t('Delivery') ?></th></tr>
    <?php foreach ($dsZones as $zc => [$zl, $zf, $zd]): ?>
    <tr>
      <td><?= htmlspecialchars(t($zl)) ?></td>
      <td>€<?= number_format((float)$zf, 2) ?></td>
      <td><?= htmlspecialchars(sprintf(t('%s working days'), $zd)) ?></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <p><?= t('Delivery is working days from dispatch and does not include time a consignment spends in customs. The destination is chosen before the payment page opens, and the page then accepts addresses only in that country — so the rate charged and the address entered can never diverge.') ?></p>

  <h2><?= t('What is not available') ?></h2>
  <p><?= t('Most of the catalogue is open to single-piece purchase. Two houses and one product type are not:') ?></p>
  <ul>
    <?php foreach ($dsBrands as $b): ?>
    <li><b><?= htmlspecialchars(mb_convert_case($b, MB_CASE_TITLE, 'UTF-8')) ?></b> — <?= t('excluded at the supplier\'s request.') ?></li>
    <?php endforeach; ?>
    <?php foreach ($dsTerms as $tm): ?>
    <li><b><?= t('Boxershorts and boxer briefs') ?></b> — <?= sprintf(t('any article whose name or category contains "%s". Underwear is not returnable, so it is not a sensible single-piece line.'), htmlspecialchars($tm)) ?></li>
    <?php endforeach; ?>
  </ul>
  <p><?= t('These rules are applied by the catalogue itself, so a listing cannot be opened to dropshipping by mistake. The feed is the authority: read it rather than filtering our brand list yourself.') ?></p>

  <h2><?= t('Availability') ?></h2>
  <p><?= t('Per-unit stock is <b>not tracked</b> for catalogue articles, and we do not invent a number to fill the gap. Availability is confirmed with the seller after your order. If it cannot be met, the order is refunded in full.') ?></p>
  <p><?= t('This is stated before you pay, and it is the single most important thing to carry into your own shop: do not promise your customer a delivery date from a stock figure you did not get from us, because we did not give you one.') ?></p>

  <h2><?= t('Duties and import taxes') ?></h2>
  <p><?= t('Duties and import taxes at the destination are <b>not included</b> in either the goods price or the shipping rate. They fall due on delivery. You can settle them yourself or leave them to your customer — decide which before you quote, because an unexpected invoice at the door is what makes a parcel get refused.') ?></p>
  <p><?= t('Goods of EU preferential origin may attract zero customs duty entering Japan under the EU–Japan Economic Partnership Agreement, when a statement on origin accompanies the consignment. That covers the tariff only. It does not cover consumption tax or the carrier\'s clearance fee.') ?></p>

  <h2><?= t('Photographs and product text') ?></h2>
  <p><?= t('Product photographs are supplied by our sellers and are <b>not VESTRA\'s property</b>. We are not in a position to grant you a licence to them. If you use them to resell in your own shop, that use and any consequence of it rests with you. Several of our partners photograph their own stock for exactly this reason.') ?></p>

  <h2><?= t('How an order runs') ?></h2>
  <ol class="dssteps">
    <li><?= t('Your customer buys the article from you, at your price.') ?></li>
    <li><?= t('You open the article on VESTRA and choose the colour, the size and the destination.') ?></li>
    <li><?= t('You pay by card and enter <b>your customer\'s</b> delivery address on the payment page.') ?></li>
    <li><?= t('The seller is notified with that address and ships the piece directly.') ?></li>
    <li><?= t('You receive the order reference and the invoice. Your customer receives the parcel.') ?></li>
  </ol>
  <p><?= t('The payment line on the card statement reads <code>Dropshipping · SKU … · Ident. …</code> and carries no brand name, because VESTRA is a wholesale marketplace and not an authorised representative of the houses it carries. The full article detail stays on your order confirmation and invoice.') ?></p>

  <h2><?= t('Doing it through the API') ?></h2>
  <p><?= t('Everything above can be driven from your own shop or ERP. Keys are issued per partner, free of charge, to verified trade accounts. Every request carries an <code>Authorization: Bearer</code> header.') ?></p>
  <pre><code>GET  /api/dropship?a=list
GET  /api/dropship?a=stock&amp;id=ARTICLE-ID
POST /api/dropship?a=order

{ "id": "ARTICLE-ID", "colour": "Black", "size": "M", "qty": 1,
  "country": "JP", "reference": "your-order-id",
  "customer_email": "…", "customer_name": "…" }</code></pre>
  <p><?= t('<code>a=stock</code> returns the price, the colours and sizes the listing knows, and the shipping table above with its transit times — so your integration reads one source instead of copying figures from this page. <code>a=order</code> returns a payment link you open to pay. Pass <code>country</code> as an ISO-2 code and we map it to the right zone for you.') ?></p>
  <p><?= t('The catalogue feed is separate and read-only: brands, articles, sizes, colours, minimum order quantities, tiered wholesale prices in EUR and photograph URLs, with a <code>since</code> filter so a nightly sync pulls only what changed. It carries <b>no EAN or GTIN</b> and <b>no live stock quantities</b>, said here rather than discovered after you have written an importer.') ?></p>
  <p><a class="acc" href="/api-docs"><?= t('Full API documentation') ?> →</a></p>

  <h2><?= t('Getting started') ?></h2>
  <p><?= t('Trade pricing and API keys go to verified trade accounts only. Register, then upload your trade licence or business registration in your account. <b>Wholesale prices open the moment that document is uploaded</b> — you do not wait for our review. The remaining documents complete the verification that unlocks seller names and line-sheet downloads.') ?></p>
  <p><?= t('For an API key, write to') ?> <a class="acc" href="mailto:support@vestrasales.com">support@vestrasales.com</a> <?= t('from the address on your account, telling us which brands or categories you intend to sell.') ?></p>
  <div class="dscta">
    <a class="btn btn-p" href="/register"><?= t('Register free') ?></a>
    <a class="btn btn-o" href="/dropship"><?= t('Browse single-piece articles') ?></a>
    <a class="btn btn-o" href="/catalog"><?= t('Download the selection (Excel)') ?></a>
  </div>
</div>
<?php require __DIR__.'/inc/foot.php'; ?>
