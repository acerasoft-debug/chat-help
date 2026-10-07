<?php
/**
 * Terms of Sale — English (courtesy translation; the German text is binding).
 * Variables come from legal/agb.php. Structure and numbering mirror the German
 * original paragraph for paragraph so that references ("clause 10.3") match.
 */
?>
<nav class="doc__toc">
  <a href="#s1">1. Scope and contracting parties</a>
  <a href="#s2">2. Role of the platform</a>
  <a href="#s3">3. Conclusion of the contract</a>
  <a href="#s4">4. Prices and shipping costs</a>
  <a href="#s5">5. Payment</a>
  <a href="#s6">6. Delivery</a>
  <a href="#s7">7. Retention of title</a>
  <a href="#s8">8. Withdrawal and voluntary returns</a>
  <a href="#s9">9. Liability for defects</a>
  <a href="#s10">10. Purchases from private sellers</a>
  <a href="#s11">11. Premium Outlet (Vault)</a>
  <a href="#s12">12. Authenticity and provenance</a>
  <a href="#s13">13. Liability</a>
  <a href="#s14">14. Vouchers</a>
  <a href="#s15">15. Data protection</a>
  <a href="#s16">16. Final provisions</a>
</nav>

<h2 id="s1">1. Scope and contracting parties</h2>
<p>
  1.1 These Terms of Sale apply to all orders placed through
  <?= h($brand) ?> (<a href="<?= h(vr_origin()) ?>"><?= h(vr_origin()) ?></a>).
  The platform is operated by <?= h($co) ?> (hereinafter the "Operator", "we").
</p>
<p>
  1.2 Three kinds of offers are listed on the platform, each marked as such on the product page:
</p>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Label</th><th>Seller</th><th>Your contracting party for the sale</th></tr></thead>
  <tbody>
    <tr><td><?= h($brand) ?> stock</td><td>the Operator itself</td><td><?= h($co) ?></td></tr>
    <tr><td>Retailer</td><td>commercial third-party seller</td><td>the respective retailer</td></tr>
    <tr><td>Private seller</td><td>private individual</td><td>the respective private individual</td></tr>
  </tbody>
</table></div>
<p>
  1.3 For offers from third-party sellers the contract of sale is concluded exclusively between you
  and the respective seller. The Operator does not become a party to the contract of sale. For the
  use of the platform itself — payment processing, order overview, intermediation — these Terms
  apply between you and the Operator.
</p>
<p>
  1.4 A consumer is any natural person who enters into a legal transaction for purposes that are
  predominantly outside their trade, business or profession (§ 13 BGB, German Civil Code). Deviating
  terms of the customer do not become part of the contract unless we expressly agree to them in text
  form.
</p>

<h2 id="s2">2. Role of the platform</h2>
<p>
  2.1 The Operator provides the technical infrastructure, reviews offers from third-party sellers for
  plausibility and proof of provenance before they go live, processes payment through the payment
  service provider Stripe, and forwards the seller's share to the seller after deducting the
  commission.
</p>
<p>
  2.2 The Operator is authorised by the third-party sellers to receive payments from the buyer with
  discharging effect. Your payment obligation towards the seller is fulfilled upon successful
  payment through the platform.
</p>
<p>
  2.3 Declarations concerning the contract of sale — in particular withdrawal, notice of defects and
  rescission — may be validly addressed to
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '[e-mail]' ?>.
  We forward them to the seller concerned without delay and assist with the settlement.
</p>

<h2 id="s3">3. Conclusion of the contract</h2>
<p>
  3.1 The presentation of goods on the platform does not constitute a legally binding offer but an
  invitation to order.
</p>
<p>
  3.2 By clicking the order button ("<?= te('checkout_go') ?>" followed by payment at Stripe) you
  submit a binding offer to purchase the goods in your bag. Before doing so you can review and
  correct your entries on the checkout page.
</p>
<p>
  3.3 We confirm receipt of your order immediately by e-mail. This acknowledgement of receipt does
  not yet constitute acceptance. The contract of sale is concluded when we or the seller declare
  acceptance or dispatch the goods — at the latest with the order confirmation, provided it
  expressly declares acceptance.
</p>
<p>
  3.4 If the contract does not come about, for instance because the goods are no longer available
  after ordering, we inform you without delay and refund any payments already made in full.
</p>
<p>
  3.5 The text of the contract is stored and sent to you with the order confirmation in text form
  (e-mail), including these Terms and the withdrawal notice.
</p>

<h2 id="s4">4. Prices and shipping costs</h2>
<p>
  4.1 All prices quoted are final prices in euro and include statutory VAT at the current rate of
  <?= h($vat) ?> %, provided the respective seller is subject to VAT. For private sellers no VAT is
  shown (§ 19 UStG, German VAT Act, or sale by a non-trader).
</p>
<p>
  4.2 Shipping costs are charged in addition to the prices of the goods. They are shown separately
  and to the exact amount on the checkout page before you place your order. Details under
  <a href="<?= h(vr_url('legal/versand.php')) ?>"><?= te('legal_shipping') ?></a>.
</p>
<p>
  4.3 If your order contains goods from several sellers, shipping costs are charged only once; the
  parcels may arrive separately.
</p>
<p>
  4.4 For deliveries to countries outside the EU, customs duties, import VAT and handling fees may
  additionally apply and are borne by the recipient.
</p>

<h2 id="s5">5. Payment</h2>
<p>
  5.1 Payment is made through the payment service provider Stripe (Stripe Payments Europe, Limited,
  Dublin, Ireland). The available payment methods are shown during checkout; an overview can be
  found under <a href="<?= h(vr_url('legal/zahlung.php')) ?>"><?= te('legal_payment') ?></a>.
</p>
<p>
  5.2 The purchase price is due upon conclusion of the contract. For payment methods with delayed
  settlement (e.g. SEPA direct debit, Klarna) we dispatch once the payment has been released by the
  payment service provider.
</p>
<p>
  5.3 Payment data, in particular card data, is processed exclusively by the payment service
  provider. The Operator neither receives nor stores complete card data.
</p>
<p>
  5.4 In the case of chargebacks for which you are responsible, we are entitled to charge the costs
  incurred thereby, provided you culpably caused the chargeback.
</p>

<h2 id="s6">6. Delivery</h2>
<p>
  6.1 Delivery is made to the delivery address you specify. Delivery times and destinations are set
  out under <a href="<?= h(vr_url('legal/versand.php')) ?>"><?= te('legal_shipping') ?></a> and begin
  with the release of payment.
</p>
<p>
  6.2 Goods from third-party sellers are dispatched by the respective seller. You receive separate
  tracking for each shipment.
</p>
<p>
  6.3 If, exceptionally, goods cannot be delivered although they were shown as available on the
  platform, we inform you without delay and refund the amount paid in full. There is no entitlement
  to subsequent delivery of a comparable item, as many items are one-of-a-kind pieces.
</p>
<p>
  6.4 For consumers, the risk of accidental loss and accidental deterioration passes only upon
  handover of the goods to you, even if shipment is carried out by a carrier (§ 475 (2) BGB).
</p>

<h2 id="s7">7. Retention of title</h2>
<p>
  The goods remain the property of the respective seller until paid for in full.
</p>

<h2 id="s8">8. Withdrawal and voluntary returns</h2>
<p>
  8.1 Consumers have a statutory right of withdrawal of <?= (int)$wd ?> days for contracts with
  commercial sellers. The full notice including the model withdrawal form can be found under
  <a href="<?= h(vr_url('legal/widerruf.php')) ?>"><?= te('legal_withdrawal') ?></a>.
</p>
<p>
  8.2 Beyond the statutory right of withdrawal, we grant a voluntary right of return of
  <?= (int)$days ?> days from receipt for our own goods. Condition: the goods are unworn, undamaged
  and carry all original labels. The voluntary right of return does not limit your statutory rights.
  The cost of the return shipment within the voluntary extension is borne by the buyer; details
  under <a href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('legal_returns') ?></a>.
</p>
<p>
  8.3 There is no statutory right of withdrawal for purchases from private sellers (see clause 10).
</p>

<h2 id="s9">9. Liability for defects</h2>
<p>
  9.1 For commercial sellers, statutory liability for defects under §§ 434 et seq. BGB applies. For
  consumers the limitation period is two years from receipt of the goods.
</p>
<p>
  9.2 For used goods, the limitation period towards consumers may be shortened to one year if this
  was expressly and separately agreed before the contract was concluded. Such a notice is shown on
  the product page where applicable.
</p>
<p>
  9.3 Signs of wear that are stated in the item description do not constitute a defect. Deviations
  in colour rendering caused by screen display are not a defect.
</p>
<p>
  9.4 Please report transport damage to us within 14 days with photos. We handle these cases
  regardless of the question of the right of withdrawal, including for private sellers.
</p>

<h2 id="s10">10. Purchases from private sellers</h2>
<p>
  10.1 Offers from private individuals are marked as "<?= te('seller_private') ?>" on the product
  page, in the bag and during checkout. Before completing your order you must expressly confirm the
  particular consequences.
</p>
<p>
  10.2 As the seller is not a trader, there is no statutory right of withdrawal. Statutory liability
  for defects can be validly excluded or limited by the private seller; such an exclusion does not
  apply to fraudulent intent or deliberately false statements.
</p>
<p>
  10.3 Irrespective of this: if the goods delivered deviate substantially from the description or are
  not genuine, we refund the full purchase price including shipping costs. In these cases we withhold
  or reclaim the seller's share.
</p>
<p>
  10.4 Private sellers who in fact sell systematically, repeatedly and with the intention of making a
  profit act commercially. If we establish this, we reclassify or suspend the account; in that case,
  the rights towards traders apply to contracts already concluded.
</p>

<h2 id="s11">11. Premium Outlet (Vault)</h2>
<p>
  11.1 In the "Premium Outlet" (Vault) each piece is offered as an individual lot with an opening
  price, a floor price and a published reduction schedule. The price falls in <?= (int)$steps ?>
  equal steps, one step every <?= (int)$hours ?> hours, down to the floor price.
</p>
<p>
  11.2 The schedule is fixed when the lot opens and is not changed thereafter. The price cannot
  rise. It is calculated server-side from the schedule and is identical for all visitors; there is
  no personalised pricing.
</p>
<p>
  11.3 The decisive price is the one displayed at the moment you add the lot to your bag; it is
  reserved for you for the duration of the checkout (20 minutes). When the reservation expires the
  lot is released again.
</p>
<p>
  11.4 Each lot exists only once. It ends with the first effective purchase. There is no entitlement
  to acquire a lot at a later, lower step.
</p>
<p>
  11.5 For price reductions we display, in accordance with § 11 PAngV (German Price Indication
  Ordinance), the lowest price of the last 30 days. As the Vault price only ever falls, this is the
  price that applied immediately before the current step. In the first step there is no price
  reduction and no reference price is advertised.
</p>
<p>
  11.6 Your statutory rights, in particular withdrawal and liability for defects, apply unchanged in
  the Vault. Clause 10 remains applicable to private sellers.
</p>
<p>
  11.7 Members with a confirmed e-mail registration receive access to new lots before general
  release. Membership is free of charge and can be revoked at any time; there is no entitlement to
  early access.
</p>
<p>
  11.8 A price alert and saving a piece to your wishlist do <strong>not</strong> reserve it and do
  not establish any right of first refusal. The notification is sent once and without guarantee of
  delivery or timing; availability at the moment of ordering alone is decisive.
</p>

<h2 id="s12">12. Authenticity and provenance</h2>
<p>
  12.1 Only genuine goods are offered. Sellers are obliged to keep the purchase receipts for their
  goods and to present them to us on request.
</p>
<p>
  12.2 If an item turns out after purchase not to be genuine, we refund the full purchase price plus
  shipping costs and bear the cost of the return shipment. This claim exists irrespective of the type
  of seller.
</p>
<p>
  12.3 Claims under clause 12.2 require that you make the goods and your complaint available to us
  within 30 days of receipt and allow us to examine them.
</p>

<h2 id="s13">13. Liability</h2>
<p>
  13.1 We are liable without limitation for intent and gross negligence, for injury to life, body or
  health, under the provisions of the German Product Liability Act and to the extent of any
  guarantee we have given.
</p>
<p>
  13.2 In the case of a slightly negligent breach of a material contractual obligation, liability is
  limited to the foreseeable damage typical of the contract. Otherwise liability is excluded.
</p>
<p>
  13.3 We are not liable for breaches of the contract of sale by third-party sellers; in this respect
  our responsibility is governed by the provisions on hosting services (Art. 6 Digital Services Act).
  Clauses 10.3 and 12.2 remain unaffected.
</p>
<p>
  13.4 No guarantee is given for the uninterrupted availability of the platform.
</p>

<h2 id="s14">14. Vouchers</h2>
<p>
  14.1 Promotional vouchers (vouchers that were not purchased but issued as part of a promotion) can
  be redeemed only within the stated period and only once. Redemption is excluded after expiry; no
  extension is granted.
</p>
<p>
  14.2 The voucher value is credited against the value of the goods, not against shipping costs.
  Cash payment, interest or a credit note for any remaining value is excluded.
</p>
<p>
  14.3 If a voucher is tied to an e-mail address or expressly marked as a welcome or first-order
  voucher, only the holder of that address may redeem it, and only for the first paid order.
  Transfer to third parties or resale is excluded.
</p>
<p>
  14.4 Several vouchers cannot be combined unless the terms of the respective voucher provide
  otherwise. A minimum order value applies if stated with the voucher; the value of the goods
  excluding shipping costs is decisive.
</p>
<p>
  14.5 If you withdraw from an order in whole or in part, we refund the amount actually paid. A
  redeemed promotional voucher does not revive; there is no entitlement to a new voucher.
</p>
<p>
  14.6 Where there is reasonable suspicion of misuse — in particular multiple accounts created to
  repeatedly use first-order vouchers — we may block individual vouchers.
</p>

<h2 id="s15">15. Data protection</h2>
<p>
  Information on the processing of your personal data can be found in the
  <a href="<?= h(vr_url('legal/datenschutz.php')) ?>"><?= te('legal_privacy') ?></a>.
  To process your order we pass the data required for shipping and invoicing on to the respective
  seller.
</p>

<h2 id="s16">16. Final provisions</h2>
<p>
  16.1 The law of the Federal Republic of Germany applies. For consumers resident in another state,
  the mandatory consumer protection provisions of their state of residence remain unaffected
  (Art. 6 (2) Rome I Regulation).
</p>
<p>
  16.2 Place of performance and jurisdiction are governed by the statutory provisions. The statutory
  places of jurisdiction apply to consumers.
</p>
<p>
  16.3 Should individual provisions of these Terms be invalid, the validity of the remaining
  provisions remains unaffected. The statutory provision takes the place of the invalid provision.
</p>
<p>
  16.4 We reserve the right to amend these Terms with effect for the future. For contracts already
  concluded, the version available at the time of conclusion applies; the version accepted is stored
  with your order (current version: <?= h(VR_TERMS_VERSION) ?>).
</p>

<div class="doc__box">
  <strong>Note on this translation</strong>
  <p style="margin-top:8px">
    This English text is a courtesy translation of the German Terms of Sale, which alone are legally
    binding. It is provided so that you can read what you are agreeing to; in case of any discrepancy
    the German version prevails. It is not legal advice.
  </p>
</div>
