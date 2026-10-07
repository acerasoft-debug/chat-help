<?php
/**
 * Payment methods — English (courtesy translation). $mode comes from legal/zahlung.php.
 */
?>
<h2>How payment works</h2>
<p>
  You add items to your bag, choose the delivery country at checkout and confirm the Terms of Sale
  and the withdrawal notice. To pay, we redirect you to our payment service provider Stripe. There
  you enter your payment details and complete the payment; afterwards you return to the order
  confirmation.
</p>
<p>
  <strong>Your card details never reach our servers.</strong> From Stripe we receive only whether the
  payment succeeded, the amount, the payment method in general form and the details needed for
  shipping and invoicing.
</p>

<h2>Available payment methods</h2>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Payment method</th><th>Debited</th><th>Note</th></tr></thead>
  <tbody>
    <tr><td>Visa, Mastercard, American Express</td><td>immediately</td>
        <td>3-D Secure confirmation by your bank may be required (SCA)</td></tr>
    <tr><td>Apple Pay</td><td>immediately</td><td>on Apple devices in Safari</td></tr>
    <tr><td>Google Pay</td><td>immediately</td><td>in Chrome and on Android</td></tr>
    <tr><td>Klarna</td><td>depending on the option chosen</td>
        <td>invoice or instalments; the financing contract is with Klarna</td></tr>
    <tr><td>SEPA direct debit</td><td>1–3 bank working days</td>
        <td>dispatch after payment release</td></tr>
  </tbody>
</table></div>
<p class="doc__related">
  Which payment methods are actually shown depends on delivery country, amount and device — Stripe
  only displays what can be used for your order. None of the payment methods offered incurs
  additional costs for you.
</p>

<?php if ($mode !== 'live'): ?>
  <div class="notice notice--demo">
    <strong>This installation is currently not running in live mode.</strong>
    No real payments can be made
    (<?= $mode === 'test' ? 'Stripe test mode active' : 'no Stripe keys configured' ?>).
  </div>
<?php endif; ?>

<h2>Due date</h2>
<p>
  The purchase price is due upon conclusion of the contract. For payment methods with delayed
  settlement we reserve your items and dispatch after payment release.
</p>

<h2>Currency and tax</h2>
<p>
  All prices are quoted in euro and include statutory VAT at
  <?= h((int)vr_config('vat_rate_bps', 1900) / 100) ?> %, provided the respective seller is subject
  to VAT. No VAT is shown for items from private sellers. If your bank settles in another currency,
  your bank may charge a conversion fee — we have no influence on this.
</p>

<h2>Invoice</h2>
<p>
  You receive the invoice with the goods or by e-mail. For items from commercial third-party sellers
  the respective retailer issues the invoice; for our own goods
  <?= h((string)(vr_config('company')['legal_name'] ?? '')) ?>. Private sellers do not issue invoices
  showing VAT.
</p>

<h2>Refunds</h2>
<p>
  Refunds are always made to the same means of payment you paid with. With Klarna the refund runs
  through your Klarna account, with SEPA to the debited account. We start processing after receipt
  and inspection of the return; depending on the payment method a few working days may pass until
  the credit appears at your bank.
</p>

<h2>Failed payment</h2>
<p>
  If a payment is declined, no contract is concluded and nothing is debited. Your bag is kept so
  that you can try again or use another payment method. The most frequent cause is an incomplete
  3-D Secure confirmation.
</p>

<h2>Payment security</h2>
<p>
  The connection is encrypted end to end via TLS. Stripe is certified as a payment service provider
  under PCI DSS Level 1 and licensed in Europe as a payment institution (Stripe Payments Europe,
  Limited, Dublin). To prevent fraud Stripe screens transactions automatically; we store no card
  numbers.
</p>
<p class="doc__related">
  Details on data processing: <a href="<?= h(vr_url('legal/datenschutz.php')) ?>"><?= te('legal_privacy') ?></a>.
</p>
