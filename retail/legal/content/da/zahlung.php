<?php
/**
 * Betalingsmetoder — dansk (vejledende oversættelse). $mode: legal/zahlung.php.
 */
?>
<h2>Sådan foregår betalingen</h2>
<p>
  Du lægger varer i tasken, vælger leveringsland ved kassen og bekræfter salgsbetingelserne og
  fortrydelsesvejledningen. For at betale sender vi dig videre til vores betalingsudbyder Stripe.
  Dér indtaster du dine betalingsoplysninger og gennemfører betalingen; derefter kommer du tilbage
  til ordrebekræftelsen.
</p>
<p>
  <strong>Dine kortoplysninger når aldrig vores servere.</strong> Fra Stripe modtager vi kun
  oplysning om, hvorvidt betalingen lykkedes, beløbet, betalingsmetoden i generel form og de
  oplysninger, der er nødvendige til forsendelse og faktura.
</p>

<h2>Tilgængelige betalingsmetoder</h2>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Betalingsmetode</th><th>Trækning</th><th>Bemærkning</th></tr></thead>
  <tbody>
    <tr><td>Visa, Mastercard, American Express</td><td>straks</td>
        <td>3-D Secure-bekræftelse fra din bank kan være påkrævet (SCA)</td></tr>
    <tr><td>Apple Pay</td><td>straks</td><td>på Apple-enheder i Safari</td></tr>
    <tr><td>Google Pay</td><td>straks</td><td>i Chrome og på Android</td></tr>
    <tr><td>Klarna</td><td>afhængigt af valgt mulighed</td>
        <td>faktura eller ratebetaling; finansieringsaftalen indgås med Klarna</td></tr>
    <tr><td>SEPA-direkte debitering</td><td>1–3 bankdage</td>
        <td>afsendelse efter frigivelse af betalingen</td></tr>
  </tbody>
</table></div>
<p class="doc__related">
  Hvilke betalingsmetoder der faktisk vises, afhænger af leveringsland, beløb og enhed — Stripe
  viser kun, hvad der kan bruges til din ordre. Ingen af de tilbudte betalingsmetoder medfører
  ekstra omkostninger for dig.
</p>

<?php if ($mode !== 'live'): ?>
  <div class="notice notice--demo">
    <strong>Denne installation kører i øjeblikket ikke i live-tilstand.</strong>
    Der kan ikke gennemføres rigtige betalinger
    (<?= $mode === 'test' ? 'Stripe-testtilstand aktiv' : 'ingen Stripe-nøgler gemt' ?>).
  </div>
<?php endif; ?>

<h2>Forfald</h2>
<p>
  Købesummen forfalder ved aftaleindgåelsen. Ved betalingsmetoder med forsinket afvikling reserverer
  vi dine varer og afsender efter frigivelse af betalingen.
</p>

<h2>Valuta og moms</h2>
<p>
  Alle priser er angivet i euro og inkluderer den lovpligtige moms på
  <?= h((int)vr_config('vat_rate_bps', 1900) / 100) ?> %, såfremt den pågældende sælger er
  momspligtig. Ved varer fra private sælgere angives ingen moms. Afregner din bank i en anden valuta,
  kan dit pengeinstitut opkræve et vekselgebyr — det har vi ingen indflydelse på.
</p>

<h2>Faktura</h2>
<p>
  Fakturaen modtager du sammen med varen eller pr. e-mail. Ved varer fra erhvervsdrivende
  tredjepartssælgere udsteder den pågældende forhandler fakturaen; ved egne varer
  <?= h((string)(vr_config('company')['legal_name'] ?? '')) ?>. Private sælgere udsteder ingen
  faktura med momsangivelse.
</p>

<h2>Refusioner</h2>
<p>
  Refusioner sker altid til det samme betalingsmiddel, som du har betalt med. Ved Klarna sker
  refusionen via din Klarna-konto, ved SEPA til den debiterede konto. Vi starter behandlingen efter
  modtagelse og kontrol af returvaren; indtil krediteringen hos din bank kan der afhængigt af
  betalingsmetoden gå nogle arbejdsdage.
</p>

<h2>Mislykket betaling</h2>
<p>
  Afvises en betaling, indgås ingen aftale, og der trækkes intet. Din taske bevares, så du kan prøve
  igen eller med en anden betalingsmetode. Den hyppigste årsag er en ikke-afsluttet 3-D
  Secure-bekræftelse.
</p>

<h2>Betalingssikkerhed</h2>
<p>
  Forbindelsen er gennemgående krypteret med TLS. Stripe er som betalingsudbyder certificeret efter
  PCI DSS niveau 1 og godkendt i Europa som betalingsinstitut (Stripe Payments Europe, Limited,
  Dublin). Til svindelbekæmpelse kontrollerer Stripe transaktioner automatisk; vi gemmer ingen
  kortnumre.
</p>
<p class="doc__related">
  Detaljer om databehandlingen: <a href="<?= h(vr_url('legal/datenschutz.php')) ?>"><?= te('legal_privacy') ?></a>.
</p>
