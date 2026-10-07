<?php
/**
 * Integritetspolicy (GDPR) — svenska (vägledande översättning; den tyska texten
 * är bindande). Skriven utifrån vad webbplatsen faktiskt gör. Variabler: legal/datenschutz.php.
 */
?>
<h2>1. Personuppgiftsansvarig</h2>
<?php vr_company_block(); ?>
<p>
  Något dataskyddsombud har inte utsetts, eftersom de lagstadgade förutsättningarna (art. 37 GDPR,
  § 38 BDSG) inte föreligger. För dataskyddsfrågor, kontakta
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-post]</em>' ?>.
</p>

<h2>2. Vad vi <em>inte</em> gör</h2>
<p>
  Vi använder inget webbanalysverktyg (varken Google Analytics eller Matomo), inga reklam- eller
  spårningspixlar, inga insticksprogram för sociala medier och ingen profilering. Inget automatiserat
  beslutsfattande enligt art. 22 GDPR förekommer. Prissättningen i Premium Outlet är inte heller
  personaliserad: priserna följer en fast, publicerad plan och är identiska för alla besökare.
</p>
<p>
  Alla typsnitt, stilmallar, skript och bilder läses in från vår egen server. I synnerhet används
  inga Google Fonts — därmed överförs din IP-adress inte till någon tredje part när en sida läses
  in.
</p>

<h2>3. Besök på webbplatsen (serverloggar)</h2>
<p>
  Vid besök behandlar vårt webbhotell tekniskt nödvändiga uppgifter: IP-adress, datum och tid,
  hämtad resurs, referrer, user agent och överförd datamängd. Uppgifterna krävs för att leverera
  sidan och avvärja angrepp.
</p>
<ul>
  <li><strong>Ändamål:</strong> tillhandahållande, stabilitet, IT-säkerhet</li>
  <li><strong>Rättslig grund:</strong> art. 6.1 f GDPR (berättigat intresse)</li>
  <li><strong>Lagringstid:</strong> i regel 7–30 dagar, därefter automatisk radering</li>
</ul>

<h2>4. Cookies och lokal lagring</h2>
<p>
  Vi använder uteslutande tekniskt nödvändiga cookies. För dessa krävs inget samtycke enligt § 25
  st. 2 nr 2 TDDDG — därför ser du ingen cookiebanner hos oss.
</p>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Namn</th><th>Ändamål</th><th>Varaktighet</th></tr></thead>
  <tbody>
    <tr><td><code><?= h((string)vr_config('session_name', 'maxsales_retail')) ?></code></td>
        <td>session: väska, säljarinloggning, CSRF-skydd</td><td>sessionens slut</td></tr>
    <tr><td><code>vr_lang</code></td><td>valt språk</td><td>180 dagar</td></tr>
    <tr><td><code>vr_member</code></td>
        <td>förhandstillgång till Vault efter bekräftad nyhetsbrevsregistrering (signerat värde,
            ingen e-post i klartext)</td><td>1 år</td></tr>
    <tr><td><code>vr_wish</code></td><td>önskelista — endast artikelnummer</td><td>180 dagar</td></tr>
    <tr><td><code>vr_seen</code></td><td>senast visade artiklar — endast artikelnummer</td><td>30 dagar</td></tr>
  </tbody>
</table></div>
<p>
  Mer information: <a href="<?= h(vr_url('legal/cookies.php')) ?>"><?= te('legal_cookies') ?></a>.
</p>

<h2>5. Önskelista och senast visade artiklar</h2>
<p>
  Önskelistan och „Senast visade“ sparar vi <strong>uteslutande i en cookie på din enhet</strong>.
  Cookien innehåller endast artikelnummer (t.ex. <code>blm-ah0eg000</code>) — inget namn, ingen
  e-postadress, ingen identifierare som gör dig identifierbar. På våra servrar skapas ingen profil
  och ingen koppling till din person.
</p>
<ul>
  <li><strong>Ändamål:</strong> den funktion du uttryckligen begärt</li>
  <li><strong>Rättslig grund:</strong> § 25 st. 2 nr 2 TDDDG (tekniskt nödvändigt för den tjänst
      användaren begärt); i den mån personuppgifter, art. 6.1 f GDPR</li>
  <li><strong>Lagringstid:</strong> önskelista 180 dagar, senast visade 30 dagar — eller tills du
      raderar cookies</li>
</ul>

<h2>6. Kontaktformulär</h2>
<p>
  Använder du kontaktformuläret behandlar vi din e-postadress, valfritt namn och ordernummer samt
  innehållet i ditt meddelande. En kopia sparas på vår server så att ingen förfrågan går förlorad om
  e-postutskicket misslyckas.
</p>
<ul>
  <li><strong>Ändamål:</strong> besvara din förfrågan</li>
  <li><strong>Rättslig grund:</strong> art. 6.1 b GDPR vid koppling till en beställning, annars
      art. 6.1 f GDPR</li>
  <li><strong>Lagringstid:</strong> tills ärendet är slutbehandlat, därefter högst sex månader; vid
      koppling till en beställning gäller de handelsrättsliga fristerna</li>
</ul>
<p>
  Mot skräppost använder vi ett osynligt formulärfält och en tidsmätning. <em>Ingen</em> extern
  captcha-tjänst bäddas in — därmed överförs inga uppgifter till tredje part.
</p>

<h2>7. Prisbevakning i Vault</h2>
<p>
  Om du sätter en prisbevakning för ett parti sparar vi din e-postadress, partiet, ditt önskepris,
  tidpunkten och ett saltat hashvärde av din IP-adress som bevis.
</p>
<ul>
  <li><strong>Ändamål:</strong> den enda avisering du begärt</li>
  <li><strong>Rättslig grund:</strong> art. 6.1 a GDPR (samtycke)</li>
  <li><strong>Lagringstid:</strong> tills aviseringen skickats, högst 90 dagar. Därefter raderas
      posten fullständigt.</li>
</ul>
<p>
  <strong>Exakt ett</strong> e-postmeddelande skickas; därefter är bevakningen förbrukad. Inga
  påminnelser eller reklam följer. Varje bevakning kan raderas omedelbart via länken i
  e-postmeddelandet.
</p>

<h2>8. Beställning och avtalshantering</h2>
<p>
  För en beställning behandlar vi: namn, leverans- och faktureringsadress, e-postadress, beställda
  artiklar, priser, betalningsstatus, ordernummer samt bevis på ditt godkännande av köpvillkoren och
  ångerrättsinformationen (tidpunkt, version och ett saltat hashvärde av din IP-adress — själva
  IP-adressen sparas inte).
</p>
<ul>
  <li><strong>Ändamål:</strong> fullgörande av avtalet, frakt, fakturering, återgång</li>
  <li><strong>Rättslig grund:</strong> art. 6.1 b GDPR; för lagring art. 6.1 c GDPR</li>
  <li><strong>Lagringstid:</strong> order- och fakturauppgifter omfattas av handels- och
      skatterättsliga lagringsfrister (§ 147 AO, § 257 HGB) och bevaras därefter, sedan raderas de.</li>
</ul>
<p>
  <strong>Utlämnande till säljare:</strong> för artiklar från tredjepartssäljare lämnar vi till
  respektive säljare de uppgifter som krävs för frakt och fakturering (namn, leveransadress,
  beställda artiklar, ordernummer). Säljaren är självständigt personuppgiftsansvarig för dessa
  uppgifter. Betalningsuppgifter och uppgifter om andra säljares artiklar lämnas inte ut.
</p>

<h2>9. Betalningshantering (Stripe)</h2>
<p>
  Betalningar hanteras via Stripe Payments Europe, Limited, 1 Grand Canal Street Lower, Grand Canal
  Dock, Dublin, Irland. Dina betalningsuppgifter anger du direkt hos Stripe; från Stripe får vi
  endast statusinformation (betald/öppen/misslyckad), belopp, betalsätt i allmän form, namn,
  e-postadress och leveransadress.
</p>
<ul>
  <li><strong>Rättslig grund:</strong> art. 6.1 b GDPR (fullgörande av avtalet)</li>
  <li><strong>Överföring till tredjeland:</strong> Stripe kan överföra uppgifter till Stripe, Inc. i
      USA. Grunden är EU-kommissionens standardavtalsklausuler samt certifiering enligt EU-US Data
      Privacy Framework.</li>
</ul>
<p>
  För utbetalningar till säljare använder vi Stripe Connect. Säljare ingår för detta ett eget avtal
  med Stripe; de identitetsbevis som samlas in där (KYC/penningtvättsförebyggande) behandlar Stripe
  som självständigt ansvarig. Vi får endast statusflaggorna <code>charges_enabled</code>,
  <code>payouts_enabled</code> och <code>details_submitted</code>.
</p>
<p>Stripes integritetspolicy: <a href="https://stripe.com/privacy" rel="noopener">stripe.com/privacy</a></p>

<h2>10. E-postutskick</h2>
<p>
  Transaktionsmejl (orderbekräftelse, fraktavisering, säljaravisering) och nyhetsbrev skickar vi via
  <?= h($mail['provider'] === 'brevo' ? 'Brevo (Sendinblue GmbH / Brevo SAS, Paris, Frankrike)' : 'vår e-postserver') ?>.
  E-postadress, namn och meddelandets innehåll överförs.
</p>
<ul>
  <li><strong>Rättslig grund:</strong> transaktionsmejl art. 6.1 b GDPR; nyhetsbrev art. 6.1 a GDPR
      (samtycke)</li>
  <li><strong>Personuppgiftsbiträde:</strong> ett avtal enligt art. 28 GDPR finns.</li>
</ul>

<h2>11. Nyhetsbrev / Vault-medlemskap</h2>
<p>
  Registreringen sker med dubbel bekräftelse (double opt-in): efter att du angett adressen får du
  ett bekräftelsemejl; först när du klickar på länken blir registreringen giltig. Som bevis sparar
  vi tidpunkten för registrering, tidpunkten för bekräftelse och ett saltat hashvärde av
  IP-adressen.
</p>
<p>
  Obekräftade registreringar raderas automatiskt efter 7 dagar. Du kan när som helst återkalla ditt
  samtycke — via avregistreringslänken i varje mejl eller genom att skriva till oss. Återkallelsen
  påverkar inte lagligheten av den behandling som skett dessförinnan.
</p>

<h2>12. Säljarkonton</h2>
<p>
  För ett säljarkonto behandlar vi: namn, ev. företagsnamn, e-postadress, land, ev.
  momsregistreringsnummer, säljartyp (näringsidkare/privat), lösenord (endast som kryptografisk
  hash, aldrig i klartext), erbjudanden samt omsättnings- och utbetalningsuppgifter.
</p>
<ul>
  <li><strong>Rättslig grund:</strong> art. 6.1 b GDPR; för granskning av erbjudanden och
      spårbarhet av näringsidkaruppgifter dessutom art. 6.1 c GDPR jämförd med art. 30 Digital
      Services Act.</li>
  <li><strong>Publicering:</strong> för näringsidkande säljare visar vi namn/företag och land på
      produktsidan; det är lagstadgat. För privata säljare visas endast statusen
      „<?= te('seller_private') ?>“, inte fullständigt namn.</li>
</ul>

<h2>13. Säkerhetsåtgärder och loggar</h2>
<p>
  Vi för tekniska loggar över misslyckade inloggningsförsök, betalningsfel och webhook-händelser. De
  innehåller tidpunkt, händelsetyp och tekniska identifierare; e-postadresser förkortas. Ändamålet
  är att motverka missbruk och bedrägeri (art. 6.1 f GDPR), lagringstid högst 90 dagar.
</p>
<p>
  Överföringen är krypterad (TLS). Lösenord lagras med en modern envägs-hashmetod.
</p>

<h2>14. Dina rättigheter</h2>
<p>Du har när som helst rätt till:</p>
<ul>
  <li>tillgång till de uppgifter som lagras om dig (art. 15 GDPR)</li>
  <li>rättelse av felaktiga uppgifter (art. 16 GDPR)</li>
  <li>radering (art. 17 GDPR), om inte en lagringsskyldighet hindrar det</li>
  <li>begränsning av behandlingen (art. 18 GDPR)</li>
  <li>dataportabilitet (art. 20 GDPR)</li>
  <li>invändning mot behandling som grundas på berättigat intresse (art. 21 GDPR)</li>
  <li>återkallelse av lämnade samtycken med verkan för framtiden (art. 7.3 GDPR)</li>
</ul>
<p>
  Ett meddelande till
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-post]</em>' ?>
  räcker. Du har dessutom rätt att lämna in klagomål till en tillsynsmyndighet för dataskydd, t.ex.
  myndigheten där du har din hemvist.
</p>

<h2>15. Ändringar</h2>
<p>
  Vi anpassar denna policy när den faktiska behandlingen ändras — t.ex. vid anlitande av en ny
  leverantör. Den version som publiceras på denna sida gäller.
</p>
