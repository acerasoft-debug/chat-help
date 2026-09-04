<?php
/**
 * VESTRA — legal documents (rendered live by legal.php).
 * Launch draft for a US LLC operating an EU-facing B2B marketplace.
 * Strongly protective B2B terms — still have a US+EU lawyer review before relying on them.
 *
 * The English set below is the single source. Translated sets live in
 * inc/legal/{de,fr,it,es}.php and override per the active language; any doc a
 * translation omits falls back to English. Company name / address / e-mails /
 * the brand "VESTRA" stay identical in every language.
 */
require_once __DIR__.'/i18n.php';

/* Dispatcher: returns the legal doc set for the active language (English fallback). */
function vestra_legal(){
  $en = vestra_legal_en();
  $l  = function_exists('vlang') ? vlang() : 'en';
  if($l==='en') return $en;
  $f = __DIR__.'/legal/'.$l.'.php';
  if(is_readable($f)){
    $tr = require $f;
    if(is_array($tr)){
      $out=[];
      foreach($en as $k=>$d){
        $out[$k] = [
          'title'=> isset($tr[$k]['title']) && $tr[$k]['title']!=='' ? $tr[$k]['title'] : $d['title'],
          'html' => isset($tr[$k]['html'])  && $tr[$k]['html']!==''  ? $tr[$k]['html']  : $d['html'],
        ];
      }
      return $out;
    }
  }
  return $en;
}

/* English source set. */
function vestra_legal_en(){
  $co='Acerasoft LLC';
  $addr='8 The Green, Suite B, Dover, Delaware 19901, USA';
  $email='legal@vestrasales.com';
  $eff='26 June 2026';
  return [
  'imprint'=>['title'=>'Imprint / Legal Notice','html'=>"
    <p>Information pursuant to applicable e-commerce and consumer-information rules.</p>
    <h3>Operator</h3>
    <ul><li><b>Company:</b> {$co}</li>
    <li><b>Legal form:</b> US Limited Liability Company (State of Delaware)</li>
    <li><b>Registered address:</b> {$addr}</li>
    <li><b>Represented by:</b> Sarah J. Mitchell (Managing Member)</li>
    <li><b>Contact:</b> <a href='mailto:{$email}'>{$email}</a> · <a href='mailto:support@vestrasales.com'>support@vestrasales.com</a></li></ul>
    <h3>Role</h3>
    <p>VESTRA operates an online B2B wholesale marketplace and acts as an <b>intermediary and technical platform only</b>.
    It is not a party to the sales contracts concluded between sellers and buyers and does not take title to goods.</p>
    <h3>Online dispute resolution</h3>
    <p>The EU ODR platform is available at ec.europa.eu/consumers/odr. VESTRA serves businesses (B2B).</p>"],

  'terms'=>['title'=>'Terms of Service','html'=>"
    <p><b>Operator:</b> {$co}, {$addr} (&ldquo;VESTRA&rdquo;, &ldquo;we&rdquo;). <b>Effective:</b> {$eff}.
    By creating an account, joining the waitlist, listing, ordering or otherwise using VESTRA, you confirm you are a
    business acting in a commercial capacity and you accept these Terms.</p>
    <h3>1. What VESTRA is</h3><p>VESTRA is a B2B wholesale marketplace connecting verified business sellers and buyers.
    VESTRA is an <b>intermediary and technical platform only</b>. It is <b>not a party</b> to any sale; it does not own,
    hold, inspect, store, ship or take title to goods, and does not custody funds (a licensed third-party escrow/payment
    provider does). Contracts of sale are concluded <b>exclusively between buyer and seller</b>.</p>
    <h3>2. Business users only (no consumers)</h3><p>VESTRA is strictly for businesses (B2B); it is not directed to
    consumers and consumer-withdrawal rights do not apply. You must complete verification (KYB/KYC) before transacting and
    warrant that all information you provide is accurate and kept current.</p>
    <h3>2a. Dropshipping orders</h3><p>Single-piece <b>dropshipping</b> orders are placed by a verified trade partner <b>for onward sale to that partner's own customer</b>. The partner is the buyer and the seller of record towards their customer; they supply the delivery address, colour and size at checkout. <b>No contract of sale arises between VESTRA and the partner's end customer</b>, and this section does not open the platform to consumers. Dropshipping prices are the wholesale price plus a handling margin, plus the shipping rate for the destination zone shown at checkout. Per-unit stock is not tracked: availability is confirmed with the seller after the order and the order is refunded in full if it cannot be met. <b>Duties, import taxes and customs clearance charges in the destination country are not included in the price or the shipping rate</b> and are payable on delivery. They are the responsibility of the ordering trade partner, who may settle them directly or arrange for their own customer to do so. Goods of EU preferential origin may qualify for zero customs duty into Japan under the EU&ndash;Japan Economic Partnership Agreement where a statement on origin accompanies the consignment; this does not cover consumption tax or carrier clearance fees.</p>
    <h3>3. Listings, orders &amp; fulfilment</h3><p>Sellers are <b>solely responsible</b> for their listings and for the
    legality, safety, conformity, labelling, description, pricing, authenticity, delivery, warranties and taxes of their
    goods. An order forms a binding contract between buyer and seller; VESTRA is not responsible for either party's performance.</p>
    <h3>3a. Returns &amp; claims</h3><p>Orders placed on VESTRA are wholesale purchases between businesses and are
    <b>closed to returns</b>: there is no right of return for change of mind, and the consumer right of withdrawal does
    not apply to a trade purchase for resale. Goods that are <b>wrong, missing or faulty</b> may be claimed within the
    notification period, on the conditions set out in the
    <a href=\"/faq?cat=returns\">Returns &amp; Claims policy</a>, which forms part of these terms. Goods must not be
    sent back without written authorisation.</p>
    <h3>3b. Inspection &amp; notice of defects</h3><p>The buyer is a merchant and must inspect the goods promptly on
    delivery. <b>Apparent defects, shortages and wrong deliveries must be notified in writing within the notification
    period</b> stated in the Returns &amp; Claims policy; hidden defects must be notified immediately upon discovery.
    Where notice is not given in time the goods are deemed accepted and warranty claims lapse. This mirrors the
    inspection duty of a commercial buyer under, among others, German commercial law (HGB \u00a7377).</p>
    <h3>4. Payments, escrow &amp; fees</h3><p>Payments are processed and held in escrow by a licensed third-party provider and
    released per agreed conditions (e.g. buyer confirmation / verified delivery). VESTRA charges a platform commission (a
    seller commission plus a buyer-protection fee) and/or membership fees; provider fees apply as charged. Fees are shown
    before checkout and are non-refundable except where required by law.</p>
    <h3>5. Authenticity &amp; intellectual property</h3><p>Only genuine, lawful goods the seller is entitled to sell may be
    listed. Counterfeit, replica and unverified grey-market goods are prohibited; we operate a notice-and-takedown process
    (see the IP &amp; Anti-Counterfeit Policy).</p>
    <h3>6. Prohibited conduct</h3><p>No illegal activity, fraud, misrepresentation, IP infringement, circumvention of
    verification/escrow/fees, scraping, or off-platform solicitation to evade fees or protections. You are responsible for all
    activity under your account and for keeping your credentials secure.</p>
    <h3>7. Disclaimer of warranties</h3><p>The platform is provided <b>&ldquo;as is&rdquo; and &ldquo;as available&rdquo;</b>,
    without warranties of any kind, express or implied, including merchantability, fitness for a particular purpose,
    non-infringement, accuracy, or uninterrupted/error-free operation. VESTRA does <b>not warrant or guarantee</b> any seller,
    buyer, listing, good, description, authenticity, quantity, quality or delivery, the outcome of any transaction, or any
    third-party provider.</p>
    <h3>8. Limitation of liability</h3><p>To the maximum extent permitted by applicable law: (a) VESTRA is <b>not liable</b>
    for the goods, listings, acts or omissions of buyers, sellers or third parties, for non-delivery, defects or authenticity,
    or for any dispute between users; (b) VESTRA is not liable for any <b>indirect, incidental, special, consequential,
    exemplary or punitive damages</b>, or for loss of profit, revenue, business, goodwill or data; and (c) VESTRA's
    <b>total aggregate liability</b> arising out of or relating to the platform or these Terms shall not exceed the greater of
    the platform fees actually paid by you to VESTRA in the <b>three (3) months</b> before the event giving rise to the claim,
    or <b>EUR 100</b>. Nothing excludes liability that cannot be limited by law (e.g. fraud, gross negligence, or death/personal
    injury caused by our negligence); your mandatory rights are unaffected.</p>
    <h3>9. Indemnification</h3><p>You agree to <b>indemnify, defend and hold harmless</b> {$co}, its affiliates, officers,
    members and staff from and against any claims, demands, losses, liabilities, fines, penalties, damages and reasonable legal
    costs arising out of or related to your use of the platform, your goods, listings or content, your transactions, your breach
    of these Terms or of any law, or your infringement of any third-party right.</p>
    <h3>10. Suspension &amp; termination</h3><p>We may suspend, restrict or terminate access at any time, with or without
    notice, for breach, suspected fraud, legal/risk reasons, non-payment or repeated infringement. Provisions that by their
    nature should survive (including sections 7–9 and 11–13) survive termination.</p>
    <h3>11. Content licence &amp; force majeure</h3><p>You grant VESTRA a non-exclusive licence to host and display your
    listings and content for the purpose of operating the platform, and warrant you hold the rights to do so. VESTRA is not
    liable for any failure or delay caused by events beyond its reasonable control (force majeure).</p>
    <h3>12. Changes</h3><p>We may update these Terms; the current version is published here with its effective date.
    Continued use after changes constitutes acceptance.</p>
    <h3>13. Governing law &amp; disputes</h3><p>These Terms are governed by the laws of the <b>State of Delaware, USA</b>,
    without regard to conflict-of-law rules. Subject to mandatory law, the courts located in Delaware shall have jurisdiction;
    the parties may agree to resolve B2B disputes by binding arbitration. <b>Mandatory provisions</b> of the user's local law
    and the EU ODR platform (ec.europa.eu/consumers/odr) remain available where applicable.</p>
    <h3>14. Miscellaneous</h3><p>If any provision is unenforceable, the remainder stays in effect (severability). These Terms
    are the entire agreement on their subject matter. We may assign these Terms in connection with a merger, acquisition or
    sale of assets; you may not assign without our consent. Our failure to enforce a provision is not a waiver.</p>
    <p class='muted'>Acceptance is recorded at registration (date, version, language). Contact: <a href='mailto:{$email}'>{$email}</a></p>"],

  'privacy'=>['title'=>'Privacy Policy','html'=>"
    <p><b>Controller:</b> {$co}, {$addr}. <b>Contact:</b> privacy@vestrasales.com. <b>Effective:</b> {$eff}.</p>
    <h3>1. Data we collect</h3><p>Account &amp; verification data (name, business details, tax/VAT ID, beneficial-owner
    identity &amp; address documents), transactional data (orders, listings, communications) and technical/log data.</p>
    <h3>2. Why (purposes &amp; legal bases)</h3><ul>
    <li>Provide and secure the marketplace — contract.</li>
    <li>Identity verification, fraud prevention, AML/IP duties — legal obligation / legitimate interests.</li>
    <li>Process payments/escrow — contract (shared with the licensed provider).</li></ul>
    <h3>3. Sharing</h3><p>With the licensed payment/escrow provider, KYC vendors, the counterparty to a transaction,
    service providers and authorities where legally required. We do not sell personal data.</p>
    <h3>4. International transfers</h3><p>Where data leaves the EEA, appropriate safeguards (e.g., SCCs) apply.</p>
    <h3>5. Retention</h3><p>Only as long as necessary and to meet legal retention requirements.</p>
    <h3>6. Your rights</h3><p>Access, rectification, erasure, restriction, portability, objection, and complaint to a
    supervisory authority.</p>"],

  'seller'=>['title'=>'Seller Agreement','html'=>"
    <p>Between {$co} and the registered business seller. <b>Effective:</b> {$eff}.</p>
    <h3>1. Verification</h3><p>Provide and keep current business registration, tax/VAT ID and beneficial-owner identity.</p>
    <h3>2. Seller of record</h3><p>The seller is the legal seller of its goods and is solely responsible for conformity, safety,
    delivery, warranties and taxes. VESTRA is an intermediary only and is not a party to the sale.</p>
    <h3>3. Authenticity &amp; right to sell</h3><p>For every item listed on VESTRA, the seller gives the following warranty:
    the goods are <b>genuine</b> (not counterfeit, replica or imitation); the seller is <b>lawfully entitled to sell</b> them
    in the destination market; and — for branded goods — the goods were <b>first placed on the EEA market by or with the
    consent of the trade-mark owner</b> (EEA exhaustion), or the seller holds documented authorisation to sell them in the EEA.
    The seller will provide written proof of authenticity and lawful provenance (e.g. supplier invoice, authorisation letter)
    on request by VESTRA, a buyer or a rights holder, within 5 business days.</p>
    <h3>4. Seller warranty &amp; full indemnification</h3><p>The seller <b>warrants</b> the accuracy of every declaration made
    under §3. The seller <b>fully indemnifies, defends and holds harmless</b> {$co}, its affiliates, officers, members and
    staff from and against <b>all</b> claims, demands, losses, damages, fines, penalties and reasonable legal costs —
    including claims by brand owners, customs authorities, buyers, and any other third party — arising out of or relating to:
    (a) any breach of the §3 warranty; (b) the goods being counterfeit, infringing, unlawfully sourced or not exhausted under
    EEA trade-mark law; or (c) any misrepresentation in a listing. This indemnity applies regardless of whether VESTRA was
    aware of the breach. VESTRA's own liability remains limited as set out in the Terms of Service.</p>
    <h3>5. Per-listing declaration</h3><p>By submitting each listing the seller expressly confirms: &ldquo;I confirm this
    product is genuine, lawfully acquired, and was first placed on the EEA market by or with the brand owner's consent.
    I accept full personal and corporate liability for any third-party claims against VESTRA arising from a breach of this
    declaration.&rdquo; This declaration is recorded at the time of submission and forms part of the seller's contractual
    obligations.</p>
    <h3>6. Notice-and-takedown</h3><p>The seller will comply with the IP &amp; Anti-Counterfeit Policy, respond to notices, and
    accept removal of listings pending resolution.</p>
    <h3>7. Orders, escrow &amp; payouts</h3><p>Funds are held in escrow and released after buyer confirmation / verified
    delivery, less VESTRA's commission.</p>
    <h3>8. Strikes &amp; suspension</h3><p>Counterfeit, IP infringement, repeated valid complaints or fraud lead to removal,
    strikes and suspension. Manifest counterfeit/fraud may cause immediate suspension.</p>"],

  'ip'=>['title'=>'IP &amp; Anti-Counterfeit / Notice-and-Takedown','html'=>"
    <h3>Zero tolerance</h3><p>Counterfeit, replica, unauthorised-brand and unverified grey-market goods, and any
    IP-infringing listing, are prohibited.</p>
    <h3>Report an infringement</h3><p>Send a notice to <a href='mailto:ip@vestrasales.com'>ip@vestrasales.com</a> with:
    the right relied on (e.g., trademark number) and proof of ownership; the exact listing URL(s); the reason it
    infringes; and a good-faith statement with contact details. One notice may list multiple URLs.</p>
    <h3>Our process</h3><ol><li>Acknowledge receipt.</li><li>Assess; remove/disable substantiated or manifest cases promptly.</li>
    <li>Notify the seller with the reason.</li><li>Allow a counter-notice with evidence (authenticity/authorisation/EEA proof).</li>
    <li>Reinstate only on sufficient proof; when in doubt the listing stays down.</li></ol>
    <h3>Repeat infringers &amp; stay-down</h3><p>A strike system applies; repeated valid complaints lead to suspension.
    For identified infringing items we take reasonable measures to prevent re-listing (stay-down).</p>
    <h3>Trusted rights-holders</h3><p>Brand owners may request a priority channel; we preserve a fair counter-notice
    process for sellers (consistent with EU DSA duties). Abusive notices may be restricted.</p>"],

  'aml'=>['title'=>'AML / KYC Policy','html'=>"
    <h3>Purpose</h3><p>Prevent money laundering, sanctions evasion, fraud and terrorist financing, and verify business users.</p>
    <h3>Verification (KYB/KYC)</h3><p>Before transacting we verify: business registration; tax/VAT identifier;
    Ultimate Beneficial Owners (&ge;25%) identity and address; business address.</p>
    <h3>Sanctions screening</h3><p>Users and beneficial owners are screened against applicable lists (OFAC, EU, UN).
    We do not onboard users in prohibited/sanctioned jurisdictions.</p>
    <h3>Funds</h3><p>Collection, escrow and settlement are performed by a licensed payment/escrow provider; VESTRA does
    not hold or transmit user funds.</p>
    <h3>Monitoring &amp; records</h3><p>We monitor for suspicious patterns and keep verification and transaction records
    for the legally required period.</p>"],

  'payments'=>['title'=>'Payments, Escrow &amp; Refunds','html'=>"
    <h3>How payment works</h3><p>Buyers pay via the licensed escrow provider (SEPA bank transfer for EU B2B; cards available).
    Funds are <b>held in escrow</b> — VESTRA never holds the money.</p>
    <h3>Escrow release</h3><p>Funds release on buyer confirmation, or automatically once the buyer's claim
    window has run and no problem has been reported to support. The automatic release is never earlier than
    the end of that window, so payment cannot leave escrow while the buyer may still complain.</p>
    <h3>Fees</h3><p>VESTRA charges a platform commission per order — a seller commission plus a small buyer-protection fee — and/or a membership fee; provider fees as charged. Exact amounts are shown before checkout.</p>
    <h3>Refunds &amp; disputes</h3><p>During a dispute funds remain in escrow. If resolved for the buyer (non-delivery,
    materially not-as-described, proven counterfeit), escrowed funds are refunded before release.</p>
    <h3>Returns</h3><p>Wholesale orders are closed to returns; goods that are wrong, missing or faulty are
    claimable within the notification period. The binding rules — what qualifies, the deadline, the evidence
    required and who bears the return freight — are the <a href=\"/faq?cat=returns\">Returns &amp; Claims policy</a>,
    which forms part of these terms.</p>
    <h3>Chargebacks</h3><p>SEPA payments are not subject to card chargebacks; card payments follow the provider's process.</p>"],

  'prohibited'=>['title'=>'Prohibited &amp; Restricted Items','html'=>"
    <h3>Prohibited</h3><ul>
    <li>Counterfeit, replica or imitation goods.</li>
    <li>Unauthorised-brand goods, or goods the seller is not entitled to sell in the destination market (incl. unverified
    grey-market / parallel imports without proof of EEA exhaustion or authorisation).</li>
    <li>Stolen, smuggled or illegally sourced goods; any IP-infringing item.</li>
    <li>Illegal items, weapons, drugs, hazardous/recalled products.</li></ul>
    <h3>Restricted (conditions/proof)</h3><ul>
    <li>Branded goods — require verification and, on request, proof of authenticity/authorisation/provenance.</li>
    <li>Categories with safety/labelling rules (e.g., textile fibre-composition labelling, EU GPSR) must comply.</li></ul>
    <h3>Enforcement</h3><p>Violations lead to removal, strikes and suspension, and may be reported to rights holders and
    authorities. Manifest counterfeit/fraud → immediate suspension.</p>"],
  ];
}
