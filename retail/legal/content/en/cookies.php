<?php
/**
 * Cookie policy — English (courtesy translation). No banner, because no tracking.
 */
?>
<div class="doc__box">
  <strong>Why you see no cookie banner here</strong>
  <p style="margin-top:8px">
    A banner is only necessary when cookies are set that go beyond what is technically required —
    analytics, advertising, tracking. We use none of these. For purely functional cookies,
    § 25 (2) no. 2 TDDDG permits setting them without consent. Hence: no banner, no "Accept all"
    button, no consent service.
  </p>
</div>

<h2>Complete list</h2>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Name</th><th>Type</th><th>Purpose</th><th>Retention</th></tr></thead>
  <tbody>
    <tr>
      <td><code><?= h((string)vr_config('session_name', 'maxsales_retail')) ?></code></td>
      <td>session cookie</td>
      <td>Holds your session together: contents of the bag, Vault reservations, seller login and
          the security token against form forgery (CSRF). Contains only a random identifier, no
          personal content.</td>
      <td>until the end of the browser session</td>
    </tr>
    <tr>
      <td><code>vr_lang</code></td>
      <td>functional</td>
      <td>Remembers the chosen language so you do not have to choose it again on every click.
          Content: a language code such as <code>en</code>.</td>
      <td>180 days</td>
    </tr>
    <tr>
      <td><code>vr_member</code></td>
      <td>functional</td>
      <td>Set only once you have confirmed Vault membership by e-mail, and unlocks early access to
          new lots. Contains an expiry date, a truncated hash of your e-mail address and a
          signature — not your address in plain text.</td>
      <td>1 year</td>
    </tr>
    <tr>
      <td><code>vr_wish</code></td>
      <td>functional</td>
      <td>Your wishlist. Content: a list of item identifiers, nothing else. Not reported to the
          server unless you open the wishlist page yourself.</td>
      <td>180 days</td>
    </tr>
    <tr>
      <td><code>vr_seen</code></td>
      <td>functional</td>
      <td>The items you viewed most recently, so you can find them again. Likewise item
          identifiers only.</td>
      <td>30 days</td>
    </tr>
  </tbody>
</table></div>

<h2>Cookies from Stripe</h2>
<p>
  When paying you switch to a page operated by Stripe. There Stripe sets its own cookies, which are
  required for payment processing and fraud prevention. This happens on Stripe's domain and is
  subject to <a href="https://stripe.com/privacy" rel="noopener">Stripe's privacy policy</a>. No
  Stripe script is embedded on our own pages.
</p>

<h2>No local storage, no fingerprints</h2>
<p>
  We use neither <code>localStorage</code> nor <code>sessionStorage</code>, no pixels, no
  fingerprinting techniques and no cross-device recognition. All fonts, styles, scripts and images
  are hosted on our own server; no connection to third parties is made when a page loads.
</p>

<h2>Deleting or blocking cookies</h2>
<p>
  You can delete or block cookies at any time in your browser settings. If you block the session
  cookie, the bag, checkout and seller login stop working — the technical thread connecting your
  steps is missing. Language and Vault access can be blocked without problems; we then ask for the
  language again and early access is not available.
</p>
<p class="doc__related">
  In detail on data processing:
  <a href="<?= h(vr_url('legal/datenschutz.php')) ?>"><?= te('legal_privacy') ?></a>.
</p>
