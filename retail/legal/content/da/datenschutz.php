<?php
/**
 * Privatlivspolitik (GDPR) — dansk (vejledende oversættelse; den tyske tekst er
 * bindende). Skrevet ud fra det, sitet faktisk gør. Variabler: legal/datenschutz.php.
 */
?>
<h2>1. Dataansvarlig</h2>
<?php vr_company_block(); ?>
<p>
  Der er ikke udpeget en databeskyttelsesrådgiver, da de lovbestemte betingelser (art. 37 GDPR,
  § 38 BDSG) ikke er opfyldt. Henvendelser om databeskyttelse rettes til
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-mail]</em>' ?>.
</p>

<h2>2. Hvad vi <em>ikke</em> gør</h2>
<p>
  Vi anvender intet webanalyseværktøj (hverken Google Analytics eller Matomo), ingen reklame- eller
  sporingspixels, ingen plugins til sociale medier og ingen profilering. Der finder ingen
  automatiseret beslutningstagning sted i henhold til art. 22 GDPR. Prisdannelsen i Premium Outlet
  er heller ikke personaliseret: priserne følger en fast, offentliggjort plan og er identiske for
  alle besøgende.
</p>
<p>
  Alle skrifttyper, stylesheets, scripts og billeder indlæses fra vores egen server. Der anvendes
  navnlig ingen Google Fonts — derfor overføres din IP-adresse ikke til nogen tredjepart ved
  sidevisning.
</p>

<h2>3. Besøg på websitet (serverlogfiler)</h2>
<p>
  Ved besøg behandler vores hostingudbyder teknisk nødvendige data: IP-adresse, dato og klokkeslæt,
  hentet ressource, referrer, user agent og overført datamængde. Disse data er nødvendige for at
  levere siden og afværge angreb.
</p>
<ul>
  <li><strong>Formål:</strong> levering, stabilitet, it-sikkerhed</li>
  <li><strong>Retsgrundlag:</strong> art. 6, stk. 1, litra f, GDPR (legitim interesse)</li>
  <li><strong>Opbevaring:</strong> normalt 7–30 dage, derefter automatisk sletning</li>
</ul>

<h2>4. Cookies og lokal lagring</h2>
<p>
  Vi anvender udelukkende teknisk nødvendige cookies. Hertil kræves ikke samtykke efter § 25, stk. 2,
  nr. 2, TDDDG — derfor ser du intet cookiebanner hos os.
</p>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Navn</th><th>Formål</th><th>Varighed</th></tr></thead>
  <tbody>
    <tr><td><code><?= h((string)vr_config('session_name', 'maxsales_retail')) ?></code></td>
        <td>session: taske, sælgerlogin, CSRF-beskyttelse</td><td>sessionens afslutning</td></tr>
    <tr><td><code>vr_lang</code></td><td>valgt sprog</td><td>180 dage</td></tr>
    <tr><td><code>vr_member</code></td>
        <td>tidlig Vault-adgang efter bekræftet nyhedsbrevstilmelding (signeret værdi, ingen e-mail
            i klartekst)</td><td>1 år</td></tr>
    <tr><td><code>vr_wish</code></td><td>ønskeliste — kun varenumre</td><td>180 dage</td></tr>
    <tr><td><code>vr_seen</code></td><td>senest viste varer — kun varenumre</td><td>30 dage</td></tr>
  </tbody>
</table></div>
<p>
  Yderligere oplysninger: <a href="<?= h(vr_url('legal/cookies.php')) ?>"><?= te('legal_cookies') ?></a>.
</p>

<h2>5. Ønskeliste og senest viste varer</h2>
<p>
  Ønskeliste og „Senest set“ gemmer vi <strong>udelukkende i en cookie på din enhed</strong>.
  Cookien indeholder kun varenumre (f.eks. <code>blm-ah0eg000</code>) — intet navn, ingen
  e-mailadresse, intet kendetegn, der kan identificere dig. På vores servere opstår hverken en
  profil eller en tilknytning til din person.
</p>
<ul>
  <li><strong>Formål:</strong> den funktion, du udtrykkeligt har ønsket</li>
  <li><strong>Retsgrundlag:</strong> § 25, stk. 2, nr. 2, TDDDG (teknisk nødvendigt for den
      tjeneste, brugeren har anmodet om); i det omfang personhenførbart, art. 6, stk. 1, litra f,
      GDPR</li>
  <li><strong>Opbevaring:</strong> ønskeliste 180 dage, senest set 30 dage — eller indtil du sletter
      cookies</li>
</ul>

<h2>6. Kontaktformular</h2>
<p>
  Bruger du kontaktformularen, behandler vi din e-mailadresse, eventuelt navn og ordrenummer samt
  indholdet af din besked. En kopi gemmes på vores server, så ingen henvendelse går tabt, hvis
  e-mailforsendelsen fejler.
</p>
<ul>
  <li><strong>Formål:</strong> besvarelse af din henvendelse</li>
  <li><strong>Retsgrundlag:</strong> art. 6, stk. 1, litra b, GDPR ved tilknytning til en ordre,
      ellers art. 6, stk. 1, litra f, GDPR</li>
  <li><strong>Opbevaring:</strong> indtil endelig behandling, derefter højst seks måneder; ved
      tilknytning til en ordre gælder de handelsretlige frister</li>
</ul>
<p>
  Mod spam anvender vi et usynligt formularfelt og en tidsmåling. Der indlejres <em>ingen</em>
  ekstern captcha-tjeneste — dermed overføres ingen data til tredjeparter.
</p>

<h2>7. Prisalarm i Vault</h2>
<p>
  Sætter du en prisalarm for et parti, gemmer vi din e-mailadresse, partiet, din ønskepris,
  tidspunktet og en saltet hashværdi af din IP-adresse som dokumentation.
</p>
<ul>
  <li><strong>Formål:</strong> den ene meddelelse, du har bedt om</li>
  <li><strong>Retsgrundlag:</strong> art. 6, stk. 1, litra a, GDPR (samtykke)</li>
  <li><strong>Opbevaring:</strong> indtil meddelelsen er sendt, højst 90 dage. Derefter slettes
      posten fuldstændigt.</li>
</ul>
<p>
  Der sendes <strong>præcis én</strong> e-mail; derefter er alarmen brugt. Der følger ingen
  påmindelser eller reklame. Hver alarm kan slettes med det samme via linket i e-mailen.
</p>

<h2>8. Ordre og aftaleafvikling</h2>
<p>
  Til en ordre behandler vi: navn, leverings- og faktureringsadresse, e-mailadresse, bestilte
  varer, priser, betalingsstatus, ordrenummer samt dokumentation for dit samtykke til
  salgsbetingelser og fortrydelsesvejledning (tidspunkt, version og en saltet hashværdi af din
  IP-adresse — selve IP'en gemmes ikke).
</p>
<ul>
  <li><strong>Formål:</strong> opfyldelse af aftalen, forsendelse, fakturering, tilbageførsel</li>
  <li><strong>Retsgrundlag:</strong> art. 6, stk. 1, litra b, GDPR; for opbevaring art. 6, stk. 1,
      litra c, GDPR</li>
  <li><strong>Opbevaring:</strong> ordre- og fakturadata er underlagt handels- og skatteretlige
      opbevaringsfrister (§ 147 AO, § 257 HGB) og opbevares tilsvarende, derefter slettes de.</li>
</ul>
<p>
  <strong>Videregivelse til sælgere:</strong> Ved varer fra tredjepartssælgere videregiver vi til
  den pågældende sælger de oplysninger, der er nødvendige for forsendelse og fakturering (navn,
  leveringsadresse, bestilte varer, ordrenummer). Sælgeren er selvstændig dataansvarlig for disse
  oplysninger. Betalingsdata og oplysninger om andre sælgeres varer videregives ikke.
</p>

<h2>9. Betalingsafvikling (Stripe)</h2>
<p>
  Betalinger afvikles via Stripe Payments Europe, Limited, 1 Grand Canal Street Lower, Grand Canal
  Dock, Dublin, Irland. Dine betalingsoplysninger indtaster du direkte hos Stripe; fra Stripe
  modtager vi kun statusoplysninger (betalt/åben/fejlet), beløb, betalingsmetode i generel form,
  navn, e-mailadresse og leveringsadresse.
</p>
<ul>
  <li><strong>Retsgrundlag:</strong> art. 6, stk. 1, litra b, GDPR (opfyldelse af aftalen)</li>
  <li><strong>Overførsel til tredjeland:</strong> Stripe kan overføre data til Stripe, Inc. i USA.
      Grundlaget er EU-Kommissionens standardkontraktbestemmelser samt certificering under EU-US
      Data Privacy Framework.</li>
</ul>
<p>
  Til udbetalinger til sælgere anvender vi Stripe Connect. Sælgere indgår hertil en egen aftale med
  Stripe; den dér indsamlede identitetsdokumentation (KYC/hvidvaskforebyggelse) behandler Stripe
  som selvstændig dataansvarlig. Vi modtager kun statusmarkeringerne <code>charges_enabled</code>,
  <code>payouts_enabled</code> og <code>details_submitted</code>.
</p>
<p>Stripes privatlivspolitik: <a href="https://stripe.com/privacy" rel="noopener">stripe.com/privacy</a></p>

<h2>10. E-mailforsendelse</h2>
<p>
  Transaktions-e-mails (ordrebekræftelse, forsendelsesmeddelelse, sælgernotifikation) og nyhedsbreve
  sender vi via
  <?= h($mail['provider'] === 'brevo' ? 'Brevo (Sendinblue GmbH / Brevo SAS, Paris, Frankrig)' : 'vores mailserver') ?>.
  Der overføres e-mailadresse, navn og beskedens indhold.
</p>
<ul>
  <li><strong>Retsgrundlag:</strong> transaktionsmails art. 6, stk. 1, litra b, GDPR; nyhedsbrev
      art. 6, stk. 1, litra a, GDPR (samtykke)</li>
  <li><strong>Databehandleraftale:</strong> Der foreligger en aftale efter art. 28 GDPR.</li>
</ul>

<h2>11. Nyhedsbrev / Vault-medlemskab</h2>
<p>
  Tilmelding sker med dobbelt opt-in: Efter indtastning af adressen modtager du en
  bekræftelses-e-mail; først ved klik på linket træder tilmeldingen i kraft. Som dokumentation
  gemmer vi tidspunktet for tilmelding, tidspunktet for bekræftelse og en saltet hashværdi af
  IP-adressen.
</p>
<p>
  Ubekræftede tilmeldinger sletter vi automatisk efter 7 dage. Du kan til enhver tid tilbagekalde
  dit samtykke — via afmeldingslinket i hver e-mail eller ved besked til os. Tilbagekaldelsen
  berører ikke lovligheden af den behandling, der er sket indtil da.
</p>

<h2>12. Sælgerkonti</h2>
<p>
  Til en sælgerkonto behandler vi: navn, evt. firmanavn, e-mailadresse, land, evt. momsnummer,
  sælgertype (erhvervsdrivende/privat), adgangskode (kun som kryptografisk hash, aldrig i klartekst),
  tilbud samt omsætnings- og udbetalingsdata.
</p>
<ul>
  <li><strong>Retsgrundlag:</strong> art. 6, stk. 1, litra b, GDPR; til kontrol af tilbud og
      sporbarhed af forhandleroplysninger desuden art. 6, stk. 1, litra c, GDPR sammenholdt med
      art. 30 Digital Services Act.</li>
  <li><strong>Offentliggørelse:</strong> Ved erhvervsdrivende sælgere viser vi navn/firma og land på
      produktsiden; det er lovpligtigt. Ved private sælgere vises kun status
      „<?= te('seller_private') ?>“, ikke fuldt navn.</li>
</ul>

<h2>13. Sikkerhedsforanstaltninger og logfiler</h2>
<p>
  Vi fører tekniske logfiler over mislykkede loginforsøg, betalingsfejl og webhook-hændelser. De
  indeholder tidspunkt, hændelsestype og tekniske kendetegn; e-mailadresser afkortes. Formålet er
  misbrugs- og svindelbekæmpelse (art. 6, stk. 1, litra f, GDPR), opbevaring højst 90 dage.
</p>
<p>
  Overførslen er krypteret (TLS). Adgangskoder gemmes med en moderne envejs-hashmetode.
</p>

<h2>14. Dine rettigheder</h2>
<p>Du har til enhver tid ret til:</p>
<ul>
  <li>indsigt i de oplysninger, der er gemt om dig (art. 15 GDPR)</li>
  <li>berigtigelse af urigtige oplysninger (art. 16 GDPR)</li>
  <li>sletning (art. 17 GDPR), medmindre en opbevaringspligt er til hinder</li>
  <li>begrænsning af behandlingen (art. 18 GDPR)</li>
  <li>dataportabilitet (art. 20 GDPR)</li>
  <li>indsigelse mod behandling baseret på legitime interesser (art. 21 GDPR)</li>
  <li>tilbagekaldelse af givne samtykker med virkning for fremtiden (art. 7, stk. 3, GDPR)</li>
</ul>
<p>
  En besked til
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-mail]</em>' ?>
  er tilstrækkelig. Du har desuden ret til at klage til en databeskyttelsesmyndighed, f.eks.
  myndigheden i det land, hvor du sædvanligvis opholder dig.
</p>

<h2>15. Ændringer</h2>
<p>
  Vi tilpasser denne erklæring, når den faktiske behandling ændrer sig — f.eks. ved brug af en ny
  tjenesteudbyder. Den version, der er offentliggjort på denne side, er gældende.
</p>
