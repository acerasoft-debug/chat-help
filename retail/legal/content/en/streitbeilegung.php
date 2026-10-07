<?php
/**
 * Dispute resolution + DSA contact point + notice procedure (Art. 16 DSA) — English.
 * Variables come from legal/streitbeilegung.php.
 */
?>
<h2>The direct route first</h2>
<p>
  Most problems can be solved in one e-mail. Write to <?= $mail ?> with your order number and a
  short description. We usually reply within one working day and get back to you with a decision or
  an interim update after seven days at the latest.
</p>

<h2>Complaints about sellers</h2>
<p>
  In case of problems with a third-party seller — goods not arrived, condition differs, refund not
  made — we mediate and can withhold the seller's share until the matter is resolved. If no
  solution is reached, we refund ourselves in the cases set out in
  <a href="<?= h(vr_url('legal/agb.php')) ?>#s10">clause 10.3</a> and
  <a href="<?= h(vr_url('legal/agb.php')) ?>#s12">12.2</a> of the Terms of Sale.
</p>

<h2>Reporting illegal content (Art. 16 DSA)</h2>
<p>
  Anyone can report listings to us that they consider illegal — for example counterfeits, trademark
  infringements or prohibited products. Please state:
</p>
<ul>
  <li>the exact address (URL) of the listing,</li>
  <li>an explanation of why the content is said to be illegal,</li>
  <li>your name and an e-mail address (except for reports of criminal offences against persons),</li>
  <li>a statement that your information is accurate and complete to the best of your knowledge.</li>
</ul>
<p>
  Send reports to <?= $mail ?> with the subject "DSA report". We confirm receipt immediately, decide
  promptly and carefully, and inform you of the decision together with the reasons. Rights holders
  who repeatedly send us accurate reports are treated with priority (Art. 22 DSA).
</p>

<h2>If we remove a listing</h2>
<p>
  Affected sellers are informed, with reasons, of any removal, suspension or reduction in
  visibility (Art. 17 DSA) and may object to the decision by e-mail within 14 days. The objection is
  reviewed by a person who was not involved in the original decision. We communicate the decision on
  the objection with reasons.
</p>
<p>
  In the case of manifestly unfounded reports or repeatedly illegal listings we suspend processing
  after reasonable prior warning or suspend the account (Art. 23 DSA).
</p>

<h2>Out-of-court dispute resolution for consumers</h2>
<p>
  We are neither obliged nor willing to participate in dispute resolution proceedings before a
  consumer arbitration board under the German Consumer Dispute Resolution Act (VSBG). Your option to
  take legal action remains unaffected; so does our effort to resolve every case directly first.
</p>
<p>
  Note: the European Commission's former online dispute resolution platform (ODR platform) was
  discontinued on 20 July 2025. A link to it has therefore been removed. In cross-border cases you
  may contact the European Consumer Centre (<a href="https://www.evz.de" rel="noopener">evz.de</a>).
</p>

<h2>Jurisdiction and applicable law</h2>
<p>
  German law applies. The statutory places of jurisdiction apply to consumers; mandatory consumer
  protection provisions of the state of residence remain unaffected (Art. 6 (2) Rome I Regulation).
</p>

<h2>Contact point for authorities</h2>
<p>
  For enquiries from authorities and courts under Art. 11 DSA you can reach us at <?= $mail ?>.
  Procedural languages are German and English.
</p>
