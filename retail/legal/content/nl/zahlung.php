<?php
/**
 * Betaalmethoden — Nederlands (vertaling ter informatie). $mode: legal/zahlung.php.
 */
?>
<h2>Hoe de betaling verloopt</h2>
<p>
  U legt artikelen in de tas, kiest bij het afrekenen het leveringsland en bevestigt de algemene
  voorwaarden en de herroepingsinformatie. Om te betalen leiden wij u door naar onze
  betaaldienstverlener Stripe. Daar voert u uw betaalgegevens in en rondt u de betaling af; daarna
  keert u terug naar de bestelbevestiging.
</p>
<p>
  <strong>Uw kaartgegevens bereiken onze servers nooit.</strong> Van Stripe ontvangen wij alleen of
  de betaling is geslaagd, het bedrag, de betaalmethode in algemene vorm en de voor verzending en
  factuur noodzakelijke gegevens.
</p>

<h2>Beschikbare betaalmethoden</h2>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Betaalmethode</th><th>Afschrijving</th><th>Opmerking</th></tr></thead>
  <tbody>
    <tr><td>Visa, Mastercard, American Express</td><td>direct</td>
        <td>3-D Secure-bevestiging van uw bank kan vereist zijn (SCA)</td></tr>
    <tr><td>Apple Pay</td><td>direct</td><td>op Apple-apparaten in Safari</td></tr>
    <tr><td>Google Pay</td><td>direct</td><td>in Chrome en op Android</td></tr>
    <tr><td>Klarna</td><td>afhankelijk van de gekozen optie</td>
        <td>achteraf betalen of in termijnen; de financieringsovereenkomst is met Klarna</td></tr>
    <tr><td>SEPA-incasso</td><td>1–3 bankwerkdagen</td>
        <td>verzending na vrijgave van de betaling</td></tr>
  </tbody>
</table></div>
<p class="doc__related">
  Welke betaalmethoden daadwerkelijk worden getoond, hangt af van leveringsland, bedrag en apparaat
  — Stripe toont alleen wat voor uw bestelling bruikbaar is. Geen van de aangeboden betaalmethoden
  brengt voor u extra kosten mee.
</p>

<?php if ($mode !== 'live'): ?>
  <div class="notice notice--demo">
    <strong>Deze installatie draait momenteel niet in live-modus.</strong>
    Er kunnen geen echte betalingen worden uitgevoerd
    (<?= $mode === 'test' ? 'Stripe-testmodus actief' : 'geen Stripe-sleutels ingesteld' ?>).
  </div>
<?php endif; ?>

<h2>Opeisbaarheid</h2>
<p>
  De koopprijs is opeisbaar bij het sluiten van de overeenkomst. Bij betaalmethoden met vertraagde
  afwikkeling reserveren wij uw artikelen en verzenden wij na vrijgave van de betaling.
</p>

<h2>Valuta en belasting</h2>
<p>
  Alle prijzen zijn in euro en inclusief de wettelijke btw van
  <?= h((int)vr_config('vat_rate_bps', 1900) / 100) ?> %, voor zover de betreffende verkoper
  btw-plichtig is. Bij artikelen van particuliere verkopers wordt geen btw vermeld. Rekent uw bank af
  in een andere valuta, dan kan uw bank wisselkosten in rekening brengen — daar hebben wij geen
  invloed op.
</p>

<h2>Factuur</h2>
<p>
  De factuur ontvangt u bij de artikelen of per e-mail. Bij artikelen van zakelijke derde verkopers
  stelt de betreffende handelaar de factuur op; bij eigen artikelen
  <?= h((string)(vr_config('company')['legal_name'] ?? '')) ?>. Particuliere verkopers stellen geen
  factuur met btw-vermelding op.
</p>

<h2>Terugbetalingen</h2>
<p>
  Terugbetalingen gebeuren altijd op hetzelfde betaalmiddel waarmee u hebt betaald. Bij Klarna loopt
  de terugbetaling via uw Klarna-account, bij SEPA naar de belaste rekening. Wij starten de
  verwerking na ontvangst en controle van de retourzending; tot de creditering bij uw bank kunnen
  afhankelijk van de betaalmethode enkele werkdagen verstrijken.
</p>

<h2>Mislukte betaling</h2>
<p>
  Wordt een betaling geweigerd, dan komt geen overeenkomst tot stand en wordt niets afgeschreven.
  Uw tas blijft bewaard, zodat u het opnieuw of met een andere betaalmethode kunt proberen. De
  meest voorkomende oorzaak is een niet-afgeronde 3-D Secure-bevestiging.
</p>

<h2>Betaalveiligheid</h2>
<p>
  De verbinding is volledig versleuteld via TLS. Stripe is als betaaldienstverlener gecertificeerd
  volgens PCI DSS Level 1 en in Europa toegelaten als betaalinstelling (Stripe Payments Europe,
  Limited, Dublin). Ter voorkoming van fraude controleert Stripe transacties geautomatiseerd; wij
  slaan geen kaartnummers op.
</p>
<p class="doc__related">
  Details over de gegevensverwerking: <a href="<?= h(vr_url('legal/datenschutz.php')) ?>"><?= te('legal_privacy') ?></a>.
</p>
