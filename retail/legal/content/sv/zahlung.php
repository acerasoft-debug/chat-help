<?php
/**
 * Betalsätt — svenska (vägledande översättning). $mode: legal/zahlung.php.
 */
?>
<h2>Så går betalningen till</h2>
<p>
  Du lägger varor i väskan, väljer leveransland i kassan och bekräftar köpvillkoren och
  ångerrättsinformationen. För att betala skickas du vidare till vår betaltjänstleverantör Stripe.
  Där anger du dina betalningsuppgifter och slutför betalningen; därefter kommer du tillbaka till
  orderbekräftelsen.
</p>
<p>
  <strong>Dina kortuppgifter når aldrig våra servrar.</strong> Från Stripe får vi endast
  information om huruvida betalningen lyckades, beloppet, betalsättet i allmän form och de uppgifter
  som krävs för frakt och faktura.
</p>

<h2>Tillgängliga betalsätt</h2>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Betalsätt</th><th>Dragning</th><th>Anmärkning</th></tr></thead>
  <tbody>
    <tr><td>Visa, Mastercard, American Express</td><td>omedelbart</td>
        <td>3-D Secure-bekräftelse från din bank kan krävas (SCA)</td></tr>
    <tr><td>Apple Pay</td><td>omedelbart</td><td>på Apple-enheter i Safari</td></tr>
    <tr><td>Google Pay</td><td>omedelbart</td><td>i Chrome och på Android</td></tr>
    <tr><td>Klarna</td><td>beroende på valt alternativ</td>
        <td>faktura eller delbetalning; finansieringsavtalet ingås med Klarna</td></tr>
    <tr><td>SEPA-autogiro</td><td>1–3 bankdagar</td>
        <td>frakt efter att betalningen frigjorts</td></tr>
  </tbody>
</table></div>
<p class="doc__related">
  Vilka betalsätt som faktiskt visas beror på leveransland, belopp och enhet — Stripe visar bara det
  som kan användas för din beställning. Inget av de erbjudna betalsätten medför extra kostnader för
  dig.
</p>

<?php if ($mode !== 'live'): ?>
  <div class="notice notice--demo">
    <strong>Denna installation körs för närvarande inte i skarpt läge.</strong>
    Inga riktiga betalningar kan genomföras
    (<?= $mode === 'test' ? 'Stripe-testläge aktivt' : 'inga Stripe-nycklar inlagda' ?>).
  </div>
<?php endif; ?>

<h2>Förfallodag</h2>
<p>
  Köpeskillingen förfaller till betalning vid avtalets ingående. Vid betalsätt med fördröjd
  avräkning reserverar vi dina varor och skickar efter att betalningen frigjorts.
</p>

<h2>Valuta och moms</h2>
<p>
  Alla priser anges i euro och inkluderar lagstadgad moms om
  <?= h((int)vr_config('vat_rate_bps', 1900) / 100) ?> %, såvida respektive säljare är momspliktig.
  För varor från privata säljare anges ingen moms. Om din bank avräknar i en annan valuta kan den
  ta ut en växlingsavgift — det har vi ingen påverkan på.
</p>

<h2>Faktura</h2>
<p>
  Fakturan får du med varan eller per e-post. För varor från näringsidkande tredjepartssäljare
  utfärdar respektive återförsäljare fakturan; för egna varor
  <?= h((string)(vr_config('company')['legal_name'] ?? '')) ?>. Privata säljare utfärdar ingen
  faktura med momsangivelse.
</p>

<h2>Återbetalningar</h2>
<p>
  Återbetalningar sker alltid till samma betalningsmedel som du betalade med. Vid Klarna sker
  återbetalningen via ditt Klarna-konto, vid SEPA till det debiterade kontot. Vi påbörjar
  handläggningen efter mottagande och kontroll av returen; tills beloppet syns hos din bank kan det
  beroende på betalsätt gå några arbetsdagar.
</p>

<h2>Misslyckad betalning</h2>
<p>
  Om en betalning nekas ingås inget avtal och inget dras. Din väska finns kvar så att du kan försöka
  igen eller med ett annat betalsätt. Den vanligaste orsaken är en inte slutförd 3-D
  Secure-bekräftelse.
</p>

<h2>Betalsäkerhet</h2>
<p>
  Anslutningen är genomgående krypterad med TLS. Stripe är som betaltjänstleverantör certifierad
  enligt PCI DSS nivå 1 och auktoriserad i Europa som betalningsinstitut (Stripe Payments Europe,
  Limited, Dublin). För bedrägeribekämpning granskar Stripe transaktioner automatiskt; vi lagrar inga
  kortnummer.
</p>
<p class="doc__related">
  Detaljer om databehandlingen: <a href="<?= h(vr_url('legal/datenschutz.php')) ?>"><?= te('legal_privacy') ?></a>.
</p>
