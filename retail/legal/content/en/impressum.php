<?php
/**
 * Imprint (§ 5 DDG, § 18 MStV) — English (courtesy translation). Variables from legal/impressum.php.
 */
?>
<h2>Provider</h2>
<?php vr_company_block(); ?>

<h2>Marketplace operator</h2>
<p>
  <?= h((string)vr_config('brand')) ?> is an online marketplace operated by
  <?= h((string)($c['legal_name'] ?? '')) ?>. The platform offers both the operator's own goods and
  goods of third parties (commercial retailers and private sellers). Who your contracting party is
  for the sale is shown on every product page and during checkout before you place your order.
</p>

<h2>Responsible for content</h2>
<p>
  Responsible under § 18 (2) MStV is the authorised representative named above, address as above.
</p>

<h2>Contact for consumer enquiries</h2>
<p>
  Please address enquiries about orders, returns and complaints to
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-mail address]</em>' ?>.
  We usually reply within one working day. For questions about an item of a third-party seller we
  put you in touch with the respective seller or forward your enquiry.
</p>

<h2>Consumer dispute resolution</h2>
<p>
  We are neither obliged nor willing to participate in dispute resolution proceedings before a
  consumer arbitration board. This does not rule out an amicable settlement with us — please contact
  us directly first. Further information under
  <a href="<?= h(vr_url('legal/streitbeilegung.php')) ?>"><?= te('legal_disputes') ?></a>.
</p>

<h2>Payment service provider</h2>
<p>
  Payments are processed via Stripe (Stripe Payments Europe, Limited, 1 Grand Canal Street Lower,
  Grand Canal Dock, Dublin, Ireland). Payouts to third-party sellers are made via Stripe Connect.
  Card data is processed exclusively at Stripe and never reaches our systems.
</p>

<h2>Liability for content</h2>
<p>
  As a service provider we are responsible for our own content on these pages under the general
  laws. For listings of third-party sellers we are not obliged to monitor transmitted or stored
  third-party information or to investigate circumstances indicating illegal activity (Art. 6, 8
  Digital Services Act). As soon as we become aware of a specific infringement we remove the content
  concerned without delay. Reports go to
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-mail address]</em>' ?>.
</p>
<p>
  We review listings of third-party sellers for plausibility before they go live and require proof
  of provenance. This voluntary review does not constitute a guarantee of the legality or
  authenticity of every individual listing and leaves our liability privilege as a hosting service
  provider unaffected.
</p>

<h2>Liability for links</h2>
<p>
  Our site contains links to external third-party websites over whose content we have no influence.
  The respective provider is always responsible for that content. At the time of linking no illegal
  content was identifiable.
</p>

<h2>Copyright</h2>
<p>
  Content and works created by the operator on these pages are subject to copyright. Product images
  and descriptions of third-party sellers are provided by them; they warrant to us that they hold
  the necessary rights. Brand and product names are the property of their respective rights
  holders. Their mention serves solely to describe the goods offered and does not establish a
  commercial relationship with the brand owners.
</p>

<h2>Note on trademark rights</h2>
<p>
  <?= h((string)vr_config('brand')) ?> is not an authorised dealer of the brands mentioned unless
  expressly stated otherwise. The goods offered are genuine goods first placed on the market within
  the European Economic Area; their resale is therefore permitted under the principle of exhaustion
  of trademark rights (§ 24 MarkenG, Art. 15 EUTMR).
</p>
