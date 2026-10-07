<?php
/**
 * Privacy Policy (GDPR) — English (courtesy translation; the German text is binding).
 * Written from what the site actually does — no analytics, no pixels, no CDN,
 * no Google Fonts. Variables come from legal/datenschutz.php.
 */
?>
<h2>1. Controller</h2>
<?php vr_company_block(); ?>
<p>
  No data protection officer has been appointed, as the statutory requirements (Art. 37 GDPR,
  § 38 BDSG) are not met. For data protection enquiries please contact
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-mail]</em>' ?>.
</p>

<h2>2. What we do <em>not</em> do</h2>
<p>
  We use no web analytics tool (no Google Analytics, no Matomo), no advertising or tracking pixels,
  no social media plugins and no profiling. There is no automated decision-making within the meaning
  of Art. 22 GDPR. Pricing in the Premium Outlet is not personalised either: prices follow a fixed,
  published schedule and are identical for all visitors.
</p>
<p>
  All fonts, stylesheets, scripts and images are loaded from our own server. In particular, no Google
  Fonts are used — so your IP address is not transmitted to any third party when a page loads.
</p>

<h2>3. Visiting the website (server log files)</h2>
<p>
  When you visit, our hosting provider processes technically necessary data: IP address, date and
  time, resource requested, referrer, user agent and the amount of data transferred. This data is
  required to deliver the page and to defend against attacks.
</p>
<ul>
  <li><strong>Purpose:</strong> provision, stability, IT security</li>
  <li><strong>Legal basis:</strong> Art. 6 (1) (f) GDPR (legitimate interest)</li>
  <li><strong>Retention:</strong> usually 7–30 days, then automatic deletion</li>
</ul>

<h2>4. Cookies and local storage</h2>
<p>
  We use only technically necessary cookies. Under § 25 (2) no. 2 TDDDG no consent is required for
  these — which is why you see no cookie banner here.
</p>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Name</th><th>Purpose</th><th>Duration</th></tr></thead>
  <tbody>
    <tr><td><code><?= h((string)vr_config('session_name', 'maxsales_retail')) ?></code></td>
        <td>session: bag, seller login, CSRF protection</td><td>end of session</td></tr>
    <tr><td><code>vr_lang</code></td><td>chosen language</td><td>180 days</td></tr>
    <tr><td><code>vr_member</code></td>
        <td>Vault early access after confirmed newsletter sign-up (signed value, no plain-text
            e-mail)</td><td>1 year</td></tr>
    <tr><td><code>vr_wish</code></td><td>wishlist — item identifiers only</td><td>180 days</td></tr>
    <tr><td><code>vr_seen</code></td><td>recently viewed items — item identifiers only</td><td>30 days</td></tr>
  </tbody>
</table></div>
<p>
  Further details: <a href="<?= h(vr_url('legal/cookies.php')) ?>"><?= te('legal_cookies') ?></a>.
</p>

<h2>5. Wishlist and recently viewed items</h2>
<p>
  We store the wishlist and "recently viewed" <strong>exclusively in a cookie on your device</strong>.
  The cookie contains only item identifiers (e.g. <code>blm-ah0eg000</code>) — no name, no e-mail
  address, no identifier that could identify you. No profile and no link to your person is created
  on our servers.
</p>
<ul>
  <li><strong>Purpose:</strong> the function you expressly requested</li>
  <li><strong>Legal basis:</strong> § 25 (2) no. 2 TDDDG (technically required for the service
      requested by the user); where personal, Art. 6 (1) (f) GDPR</li>
  <li><strong>Retention:</strong> wishlist 180 days, recently viewed 30 days — or until you delete
      the cookies</li>
</ul>

<h2>6. Contact form</h2>
<p>
  If you use the contact form, we process your e-mail address, optionally your name and order
  number, and the content of your message. A copy is stored on our server so that no enquiry is lost
  if e-mail delivery fails.
</p>
<ul>
  <li><strong>Purpose:</strong> answering your enquiry</li>
  <li><strong>Legal basis:</strong> Art. 6 (1) (b) GDPR where related to an order, otherwise
      Art. 6 (1) (f) GDPR</li>
  <li><strong>Retention:</strong> until the matter is closed, then no longer than six months; for
      order-related enquiries the commercial retention periods apply</li>
</ul>
<p>
  To prevent spam we use an invisible form field and a timing check. <em>No</em> external captcha
  service is embedded — so no data is transferred to third parties.
</p>

<h2>7. Price alert in the Vault</h2>
<p>
  If you set a price alert for a lot, we store your e-mail address, the lot, your target price, the
  time and a salted hash of your IP address as evidence.
</p>
<ul>
  <li><strong>Purpose:</strong> the one notification you requested</li>
  <li><strong>Legal basis:</strong> Art. 6 (1) (a) GDPR (consent)</li>
  <li><strong>Retention:</strong> until the notification is sent, at most 90 days. The record is then
      deleted completely.</li>
</ul>
<p>
  <strong>Exactly one</strong> e-mail is sent; the alert is then used up. No reminders or advertising
  follow. Every alert can be deleted immediately via the link in the e-mail.
</p>

<h2>8. Orders and contract processing</h2>
<p>
  For an order we process: name, delivery and billing address, e-mail address, items ordered,
  prices, payment status, order number and evidence of your consent to the Terms and the withdrawal
  notice (time, version and a salted hash of your IP address — the IP itself is not stored).
</p>
<ul>
  <li><strong>Purpose:</strong> performance of the contract, shipping, invoicing, reversal</li>
  <li><strong>Legal basis:</strong> Art. 6 (1) (b) GDPR; for retention Art. 6 (1) (c) GDPR</li>
  <li><strong>Retention:</strong> order and invoice data are subject to commercial and tax retention
      periods (§ 147 AO, § 257 HGB) and are kept accordingly, then deleted.</li>
</ul>
<p>
  <strong>Disclosure to sellers:</strong> for items from third-party sellers we transmit to the
  respective seller the data required for shipping and invoicing (name, delivery address, items
  ordered, order number). The seller is an independent controller for this data. Payment data and
  details of items from other sellers are not transmitted.
</p>

<h2>9. Payment processing (Stripe)</h2>
<p>
  Payments are processed by Stripe Payments Europe, Limited, 1 Grand Canal Street Lower, Grand Canal
  Dock, Dublin, Ireland. You enter your payment details directly with Stripe; from Stripe we receive
  only status information (paid/open/failed), amount, payment method in general form, name, e-mail
  address and delivery address.
</p>
<ul>
  <li><strong>Legal basis:</strong> Art. 6 (1) (b) GDPR (performance of the contract)</li>
  <li><strong>Third-country transfer:</strong> Stripe may transfer data to Stripe, Inc. in the USA.
      This is based on the EU Commission's standard contractual clauses and certification under the
      EU-US Data Privacy Framework.</li>
</ul>
<p>
  For payouts to sellers we use Stripe Connect. Sellers conclude their own agreement with Stripe for
  this; the identity evidence collected there (KYC/anti-money-laundering) is processed by Stripe as
  an independent controller. We receive only the status flags <code>charges_enabled</code>,
  <code>payouts_enabled</code> and <code>details_submitted</code>.
</p>
<p>Stripe's privacy notice: <a href="https://stripe.com/privacy" rel="noopener">stripe.com/privacy</a></p>

<h2>10. E-mail delivery</h2>
<p>
  Transactional e-mails (order confirmation, shipping notice, seller notification) and newsletters
  are sent via
  <?= h($mail['provider'] === 'brevo' ? 'Brevo (Sendinblue GmbH / Brevo SAS, Paris, France)' : 'our mail server') ?>.
  E-mail address, name and the content of the message are transmitted.
</p>
<ul>
  <li><strong>Legal basis:</strong> transactional e-mails Art. 6 (1) (b) GDPR; newsletter
      Art. 6 (1) (a) GDPR (consent)</li>
  <li><strong>Processing on our behalf:</strong> a contract under Art. 28 GDPR is in place.</li>
</ul>

<h2>11. Newsletter / Vault membership</h2>
<p>
  Sign-up uses double opt-in: after entering your address you receive a confirmation e-mail; the
  sign-up only takes effect when you click the link. As evidence we store the time of sign-up, the
  time of confirmation and a salted hash of the IP address.
</p>
<p>
  Unconfirmed sign-ups are deleted automatically after 7 days. You can withdraw your consent at any
  time — via the unsubscribe link in every e-mail or by writing to us. Withdrawal does not affect
  the lawfulness of processing carried out before it.
</p>

<h2>12. Seller accounts</h2>
<p>
  For a seller account we process: name, company name if applicable, e-mail address, country, VAT ID
  if applicable, seller type (commercial/private), password (only as a cryptographic hash, never in
  plain text), listings and sales and payout data.
</p>
<ul>
  <li><strong>Legal basis:</strong> Art. 6 (1) (b) GDPR; for reviewing listings and the traceability
      of trader information additionally Art. 6 (1) (c) GDPR in conjunction with Art. 30 Digital
      Services Act.</li>
  <li><strong>Publication:</strong> for commercial sellers we show name/company and country on the
      product page; this is required by law. For private sellers only the status
      "<?= te('seller_private') ?>" is shown, not the full name.</li>
</ul>

<h2>13. Security measures and logs</h2>
<p>
  We keep technical logs of failed login attempts, payment errors and webhook events. They contain
  time, event type and technical identifiers; e-mail addresses are truncated. The purpose is the
  prevention of abuse and fraud (Art. 6 (1) (f) GDPR); retention at most 90 days.
</p>
<p>
  Transmission is encrypted (TLS). Passwords are stored using a modern one-way hashing method.
</p>

<h2>14. Your rights</h2>
<p>You have the right at any time to:</p>
<ul>
  <li>access to the data stored about you (Art. 15 GDPR)</li>
  <li>rectification of inaccurate data (Art. 16 GDPR)</li>
  <li>erasure (Art. 17 GDPR), unless a retention obligation stands in the way</li>
  <li>restriction of processing (Art. 18 GDPR)</li>
  <li>data portability (Art. 20 GDPR)</li>
  <li>object to processing based on legitimate interests (Art. 21 GDPR)</li>
  <li>withdraw consent given, with effect for the future (Art. 7 (3) GDPR)</li>
</ul>
<p>
  A message to
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-mail]</em>' ?>
  is sufficient. You also have the right to lodge a complaint with a data protection supervisory
  authority, for instance the authority of your habitual residence.
</p>

<h2>15. Changes</h2>
<p>
  We update this policy when the actual processing changes — for example when a new service
  provider is used. The version published on this page applies.
</p>
