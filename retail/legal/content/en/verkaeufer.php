<?php
/**
 * Seller Terms — English (courtesy translation; the German text is binding).
 * Fee rates come from configuration via legal/verkaeufer.php so that the contract
 * text and the invoiced rate can never drift apart.
 */
?>
<nav class="doc__toc">
  <a href="#v1">1. Subject matter</a>
  <a href="#v2">2. Seller account and registration</a>
  <a href="#v3">3. Commercial or private</a>
  <a href="#v4">4. Listings and review</a>
  <a href="#v5">5. Prohibited goods</a>
  <a href="#v6">6. Commission</a>
  <a href="#v7">7. Payment and payout</a>
  <a href="#v8">8. Shipping and returns</a>
  <a href="#v9">9. Premium Outlet</a>
  <a href="#v10">10. Obligations under the Digital Services Act</a>
  <a href="#v11">11. Rights in content</a>
  <a href="#v12">12. Suspension and termination</a>
  <a href="#v13">13. Liability and indemnity</a>
  <a href="#v14">14. Final provisions</a>
</nav>

<h2 id="v1">1. Subject matter</h2>
<p>
  1.1 These terms govern the relationship between <?= h($co) ?> as operator of the marketplace
  <?= h($brand) ?> and persons who offer goods through the platform ("Sellers").
</p>
<p>
  1.2 The Operator provides the sales space, payment processing via Stripe and order management.
  The contract of sale for the goods offered is concluded exclusively between the Seller and the
  buyer; the Operator does not become a party to it.
</p>
<p>
  1.3 The Operator is entitled to receive buyers' payments on behalf of the Seller with discharging
  effect and to accept and forward buyers' declarations concerning the contract of sale (in
  particular withdrawal and notices of defects) on the Seller's behalf.
</p>

<h2 id="v2">2. Seller account and registration</h2>
<p>
  2.1 Registration takes place online. Information must be true, complete and up to date. Changes —
  in particular of company name, address, tax number or seller type — must be updated without
  delay.
</p>
<p>
  2.2 Login credentials must be kept secret. The Seller is liable for actions carried out through
  their account to the extent they are at fault.
</p>
<p>
  2.3 Before the first listing is activated, verification with Stripe (Stripe Connect) must be
  completed. Without completed verification no payout can be made; listings remain offline in that
  case.
</p>

<h2 id="v3">3. Commercial or private</h2>
<p>
  3.1 On registration the Seller must state whether they sell as a trader ("Retailer") or as a
  private individual ("Private seller"). This information is shown to buyers on the product page and
  during checkout and determines which consumer rights apply.
</p>
<p>
  3.2 Anyone who sells systematically, repeatedly and with the intention of making a profit acts
  commercially — regardless of self-assessment. Correct classification and all tax, trade and
  commercial law obligations are the Seller's responsibility.
</p>
<p>
  3.3 The Operator is entitled, after prior notice, to reclassify an account as "Retailer" or to
  suspend it if the actual selling activity is commercial in nature. Relevant criteria are in
  particular the number of listings, turnover and regularity.
</p>
<p>
  3.4 Retailers are obliged to grant consumers the statutory right of withdrawal, to issue proper
  invoices and to fulfil statutory liability for defects.
</p>

<h2 id="v4">4. Listings and review</h2>
<p>
  4.1 Listings must be accurate, complete and up to date: brand, model name, size, condition, price
  including VAT and at least one own, unedited image of the goods actually in stock.
</p>
<p>
  4.2 For pre-owned goods, signs of wear must be described. Missing or embellished information is at
  the Seller's expense.
</p>
<p>
  4.3 Every new or amended listing is reviewed before activation. The review covers plausibility,
  image quality and price; the Operator may request proof of provenance (purchase receipts). There is
  no entitlement to activation.
</p>
<p>
  4.4 The Seller keeps purchase receipts for at least the duration of the listing plus two years and
  presents them on request within five working days.
</p>
<p>
  4.5 The Seller ensures that goods offered are available. Repeated non-delivery after a sale
  entitles the Operator to suspend the account.
</p>

<h2 id="v5">5. Prohibited goods</h2>
<p>The following in particular may not be offered:</p>
<ul>
  <li>counterfeits, replicas, "dupes" and goods with removed or altered markings;</li>
  <li>goods without traceable provenance or from an unlawful source;</li>
  <li>goods first placed on the market outside the EEA, unless the brand owner has consented to
      resale within the EEA;</li>
  <li>samples not released for sale ("not for resale"), staff goods subject to a resale ban;</li>
  <li>goods that violate product safety, labelling or textile labelling regulations;</li>
  <li>fur and exotic leather without the required documentation (CITES).</li>
</ul>
<p>
  Violations lead to immediate removal of the listing and, as a rule, to termination of the seller
  account.
</p>

<h2 id="v6">6. Commission</h2>
<p>6.1 A commission is charged for every sale brokered through the platform:</p>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Seller type</th><th>Commission</th><th>Premium Outlet (Vault)</th></tr></thead>
  <tbody>
    <tr><td>Retailer</td><td><?= h($feeB) ?> % + <?= h($fixed) ?> per item sold</td><td><?= h($feeV) ?> %</td></tr>
    <tr><td>Private seller</td><td><?= h($feeP) ?> % + <?= h($fixed) ?> per item sold</td><td><?= h($feeV) ?> %</td></tr>
  </tbody>
</table></div>
<p>
  6.2 The basis of assessment is the gross sale price of the Seller's items. Shipping costs are not
  part of the basis of assessment and remain with the Operator, who bears the shipping costs towards
  the carrier.
</p>
<p>
  6.3 There are no listing, insertion or monthly fees.
</p>
<p>
  6.4 The commission is withheld automatically when the buyer pays. In the case of complete
  reversal (withdrawal, rescission, non-delivery) the commission is refunded, except for the fixed
  amount under clause 6.1 where the reversal was caused by the Seller.
</p>
<p>
  6.5 Changes to the commission are announced by e-mail at least 30 days in advance. If the Seller
  does not object before they take effect, the new commission is deemed agreed; the right of
  termination under clause 12 remains unaffected.
</p>

<h2 id="v7">7. Payment and payout</h2>
<p>
  7.1 Payment processing is handled via Stripe. For this the Seller concludes their own agreement
  with Stripe (Stripe Connected Account Agreement) and accepts its terms.
</p>
<p>
  7.2 Depending on the composition of the order, payment is either made directly to the Seller's
  account (payment with forwarding) or the amount is first collected on the platform account and
  then transferred to the Seller as a separate transfer. In both cases the Seller receives the gross
  sale price of their items less commission.
</p>
<p>
  7.3 The payout rhythm to the bank account follows Stripe's rules. The Operator does not hold
  customer funds and owes no interest.
</p>
<p>
  7.4 If Stripe verification has not yet been completed, the Seller's share remains on the platform
  account until verification is completed. If verification is not completed within 180 days, the
  Operator may cancel the orders concerned and refund the buyers.
</p>
<p>
  7.5 The Operator may withhold or set off payouts to the extent that justified claims against the
  Seller exist — in particular from refunds to buyers, chargebacks or breaches of clause 5. The
  withholding is justified and limited to the amount of the claims.
</p>

<h2 id="v8">8. Shipping and returns</h2>
<p>
  8.1 The Seller ships within two working days of receipt of payment, insured and with tracking, and
  enters the shipment data without delay.
</p>
<p>
  8.2 The Seller accepts returns at the address they have specified. Retailers refund withdrawals
  within the deadline; if no refund is made, the Operator is entitled to refund the buyer and set off
  the amount against the Seller's payouts.
</p>
<p>
  8.3 There is no statutory right of withdrawal for private sellers. However, if the goods deviate
  substantially from the description or are not genuine, the Seller is obliged to take them back and
  refund.
</p>

<h2 id="v9">9. Premium Outlet</h2>
<p>
  9.1 The Seller may make goods available for the Vault. Opening price, floor price and step
  schedule are agreed before opening and not changed thereafter.
</p>
<p>
  9.2 The Seller acknowledges that the sale may come about at any price between the opening and
  floor price, and that withdrawal of participation is not possible once the lot has opened, for as
  long as the lot is running.
</p>
<p>
  9.3 The floor price is never undercut. If a lot is not sold by the end, it is closed and may be
  offered again in the regular way.
</p>

<h2 id="v10">10. Obligations under the Digital Services Act</h2>
<p>
  10.1 Commercial Sellers provide the information required under Art. 30 DSA: name, address,
  telephone number, e-mail address, commercial register or comparable identifier and VAT ID where
  available. The Operator verifies this information by reasonable means and may take the listing
  offline until clarified.
</p>
<p>
  10.2 The Seller warrants that their listings comply with product safety and labelling regulations
  and that they hold the necessary permits.
</p>
<p>
  10.3 Reports of illegal content are handled under Art. 16 DSA. Affected Sellers are informed of
  removals with reasons and may object
  (<a href="<?= h(vr_url('legal/streitbeilegung.php')) ?>"><?= te('legal_disputes') ?></a>).
</p>

<h2 id="v11">11. Rights in content</h2>
<p>
  11.1 The Seller grants the Operator a simple, territorially unlimited, royalty-free right to use,
  edit (cropping, colour adjustment, resizing) and reproduce the texts and images posted for the
  operation and promotion of the platform.
</p>
<p>
  11.2 The right of use continues beyond the end of the listing to the extent it serves to document
  completed sales.
</p>
<p>
  11.3 The Seller warrants that they hold all necessary rights in the content posted and do not
  infringe any third-party rights.
</p>

<h2 id="v12">12. Suspension and termination</h2>
<p>
  12.1 Either side may terminate the seller relationship at any time in text form with 14 days'
  notice. Pending orders must still be fully completed.
</p>
<p>
  12.2 The Operator may remove listings immediately and suspend the account in the event of a breach
  of clause 5, false information about seller status, repeated non-delivery or reasonable suspicion
  of counterfeits. Reasons are given for the suspension.
</p>
<p>
  12.3 After termination, payouts due are instructed after expiry of the return and chargeback
  periods, at the latest 90 days after the last order.
</p>

<h2 id="v13">13. Liability and indemnity</h2>
<p>
  13.1 The Operator is liable without limitation for intent and gross negligence, for injury to
  life, body or health and where a guarantee has been given. In the case of a slightly negligent
  breach of material contractual obligations, liability is limited to the foreseeable damage typical
  of the contract; otherwise it is excluded.
</p>
<p>
  13.2 The Seller indemnifies the Operator against third-party claims based on a breach of these
  terms — in particular under trademark, copyright or competition law and from consumer protection
  violations. The indemnity includes reasonable costs of legal defence.
</p>
<p>
  13.3 No turnover or success guarantee is given. Visibility, placement and sorting of listings are
  determined by the Operator.
</p>

<h2 id="v14">14. Final provisions</h2>
<p>
  14.1 German law applies. If the Seller is a trader, the place of jurisdiction is the Operator's
  registered office, to the extent legally permissible.
</p>
<p>
  14.2 Changes to these terms are communicated by e-mail at least 30 days in advance. Current
  version: <?= h(VR_TERMS_VERSION) ?>.
</p>
<p>
  14.3 Contact for all seller matters:
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-mail]</em>' ?>.
</p>

<div class="doc__box">
  <strong>Note on this translation</strong>
  <p style="margin-top:8px">
    This English text is a courtesy translation of the German Seller Terms, which alone are legally
    binding and which you accept on registration. In case of any discrepancy the German version
    prevails. It is not legal advice.
  </p>
</div>
