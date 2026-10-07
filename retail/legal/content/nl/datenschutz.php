<?php
/**
 * Privacyverklaring (AVG) — Nederlands (vertaling ter informatie; de Duitse tekst
 * is bindend). Geschreven op basis van wat de site werkelijk doet. Variabelen: legal/datenschutz.php.
 */
?>
<h2>1. Verwerkingsverantwoordelijke</h2>
<?php vr_company_block(); ?>
<p>
  Er is geen functionaris voor gegevensbescherming aangesteld, omdat de wettelijke voorwaarden
  (art. 37 AVG, § 38 BDSG) niet zijn vervuld. Voor privacyvragen kunt u terecht bij
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-mail]</em>' ?>.
</p>

<h2>2. Wat wij <em>niet</em> doen</h2>
<p>
  Wij gebruiken geen webanalysetool (geen Google Analytics, geen Matomo), geen advertentie- of
  trackingpixels, geen socialemediaplug-ins en geen profilering. Er vindt geen geautomatiseerde
  besluitvorming plaats in de zin van art. 22 AVG. Ook de prijsvorming in de Premium Outlet is niet
  gepersonaliseerd: prijzen volgen uit een vast, gepubliceerd schema en zijn voor alle bezoekers
  identiek.
</p>
<p>
  Alle lettertypen, stylesheets, scripts en afbeeldingen worden van onze eigen server geladen. Er
  worden met name geen Google Fonts gebruikt — zo wordt uw IP-adres bij het laden van een pagina aan
  geen enkele derde doorgegeven.
</p>

<h2>3. Bezoek aan de website (serverlogbestanden)</h2>
<p>
  Bij een bezoek verwerkt onze hoster technisch noodzakelijke gegevens: IP-adres, datum en tijd,
  opgevraagde bron, referrer, user-agent en overgedragen hoeveelheid gegevens. Deze gegevens zijn
  nodig om de pagina te leveren en aanvallen af te weren.
</p>
<ul>
  <li><strong>Doel:</strong> beschikbaarstelling, stabiliteit, IT-beveiliging</li>
  <li><strong>Rechtsgrond:</strong> art. 6 lid 1 sub f AVG (gerechtvaardigd belang)</li>
  <li><strong>Bewaartermijn:</strong> doorgaans 7–30 dagen, daarna automatische verwijdering</li>
</ul>

<h2>4. Cookies en lokale opslag</h2>
<p>
  Wij gebruiken uitsluitend technisch noodzakelijke cookies. Daarvoor is volgens § 25 lid 2 nr. 2
  TDDDG geen toestemming vereist — daarom ziet u bij ons geen cookiebanner.
</p>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Naam</th><th>Doel</th><th>Duur</th></tr></thead>
  <tbody>
    <tr><td><code><?= h((string)vr_config('session_name', 'maxsales_retail')) ?></code></td>
        <td>sessie: tas, verkoperslogin, CSRF-bescherming</td><td>einde sessie</td></tr>
    <tr><td><code>vr_lang</code></td><td>gekozen taal</td><td>180 dagen</td></tr>
    <tr><td><code>vr_member</code></td>
        <td>vroegtijdige Vault-toegang na bevestigde nieuwsbriefaanmelding (ondertekende waarde,
            geen e-mail in leesbare tekst)</td><td>1 jaar</td></tr>
    <tr><td><code>vr_wish</code></td><td>verlanglijst — alleen artikelcodes</td><td>180 dagen</td></tr>
    <tr><td><code>vr_seen</code></td><td>recent bekeken artikelen — alleen artikelcodes</td><td>30 dagen</td></tr>
  </tbody>
</table></div>
<p>
  Meer informatie: <a href="<?= h(vr_url('legal/cookies.php')) ?>"><?= te('legal_cookies') ?></a>.
</p>

<h2>5. Verlanglijst en recent bekeken artikelen</h2>
<p>
  Verlanglijst en „Recent bekeken” slaan wij <strong>uitsluitend op in een cookie op uw
  apparaat</strong>. De cookie bevat alleen artikelcodes (bijv. <code>blm-ah0eg000</code>) — geen
  naam, geen e-mailadres, geen kenmerk waarmee u identificeerbaar bent. Op onze servers ontstaat
  daarbij geen profiel en geen koppeling aan uw persoon.
</p>
<ul>
  <li><strong>Doel:</strong> de door u uitdrukkelijk gewenste functie</li>
  <li><strong>Rechtsgrond:</strong> § 25 lid 2 nr. 2 TDDDG (technisch noodzakelijk voor de door de
      gebruiker gevraagde dienst); voor zover persoonsgebonden, art. 6 lid 1 sub f AVG</li>
  <li><strong>Bewaartermijn:</strong> verlanglijst 180 dagen, recent bekeken 30 dagen — of tot u de
      cookies verwijdert</li>
</ul>

<h2>6. Contactformulier</h2>
<p>
  Gebruikt u het contactformulier, dan verwerken wij uw e-mailadres, optioneel naam en bestelnummer
  en de inhoud van uw bericht. Een kopie wordt op onze server opgeslagen, zodat geen aanvraag
  verloren gaat als de e-mailverzending mislukt.
</p>
<ul>
  <li><strong>Doel:</strong> beantwoording van uw aanvraag</li>
  <li><strong>Rechtsgrond:</strong> art. 6 lid 1 sub b AVG bij bestelverband, anders art. 6 lid 1
      sub f AVG</li>
  <li><strong>Bewaartermijn:</strong> tot definitieve afhandeling, daarna hooguit zes maanden; bij
      bestelverband gelden de handelsrechtelijke termijnen</li>
</ul>
<p>
  Tegen spam gebruiken wij een onzichtbaar formulierveld en een tijdmeting. Er wordt <em>geen</em>
  externe captcha-dienst ingebonden — zo worden geen gegevens aan derden doorgegeven.
</p>

<h2>7. Prijsalert in de Vault</h2>
<p>
  Stelt u voor een kavel een prijsalert in, dan slaan wij uw e-mailadres, het kavel, uw gewenste
  prijs, het tijdstip en een gezouten hashwaarde van uw IP-adres als bewijs op.
</p>
<ul>
  <li><strong>Doel:</strong> de ene door u gevraagde melding</li>
  <li><strong>Rechtsgrond:</strong> art. 6 lid 1 sub a AVG (toestemming)</li>
  <li><strong>Bewaartermijn:</strong> tot verzending van de melding, hooguit 90 dagen. Daarna wordt
      het record volledig verwijderd.</li>
</ul>
<p>
  Er wordt <strong>precies één</strong> e-mail verzonden; daarna is de alert verbruikt. Herinneringen
  of reclame volgen niet. Elke alert kan via de link in de e-mail direct worden verwijderd.
</p>

<h2>8. Bestelling en uitvoering van de overeenkomst</h2>
<p>
  Voor een bestelling verwerken wij: naam, leverings- en factuuradres, e-mailadres, bestelde
  artikelen, prijzen, betaalstatus, bestelnummer en een bewijs van uw instemming met de algemene
  voorwaarden en de herroepingsinformatie (tijdstip, versie en een gezouten hashwaarde van uw
  IP-adres — het IP zelf wordt daarbij niet opgeslagen).
</p>
<ul>
  <li><strong>Doel:</strong> nakoming van de overeenkomst, verzending, facturering, afwikkeling</li>
  <li><strong>Rechtsgrond:</strong> art. 6 lid 1 sub b AVG; voor bewaring art. 6 lid 1 sub c AVG</li>
  <li><strong>Bewaartermijn:</strong> bestel- en factuurgegevens vallen onder handels- en
      belastingrechtelijke bewaartermijnen (§ 147 AO, § 257 HGB) en worden dienovereenkomstig
      bewaard, daarna verwijderd.</li>
</ul>
<p>
  <strong>Doorgifte aan verkopers:</strong> bij artikelen van derde verkopers geven wij de
  betreffende verkoper de voor verzending en facturering noodzakelijke gegevens door (naam,
  leveringsadres, bestelde artikelen, bestelnummer). De verkoper is voor deze gegevens zelfstandig
  verwerkingsverantwoordelijke. Niet doorgegeven worden betaalgegevens en gegevens over artikelen
  van andere verkopers.
</p>

<h2>9. Betalingsafwikkeling (Stripe)</h2>
<p>
  Betalingen worden afgewikkeld via Stripe Payments Europe, Limited, 1 Grand Canal Street Lower,
  Grand Canal Dock, Dublin, Ierland. Uw betaalgegevens voert u rechtstreeks bij Stripe in; van Stripe
  ontvangen wij alleen statusinformatie (betaald/open/mislukt), bedrag, betaalmethode in algemene
  vorm, naam, e-mailadres en leveringsadres.
</p>
<ul>
  <li><strong>Rechtsgrond:</strong> art. 6 lid 1 sub b AVG (nakoming van de overeenkomst)</li>
  <li><strong>Doorgifte naar derde landen:</strong> Stripe kan gegevens doorgeven aan Stripe, Inc. in
      de VS. Grondslag zijn de standaardcontractbepalingen van de EU-Commissie en de certificering
      onder het EU-VS Data Privacy Framework.</li>
</ul>
<p>
  Voor uitbetalingen aan verkopers gebruiken wij Stripe Connect. Verkopers sluiten daarvoor een
  eigen overeenkomst met Stripe; de daar verzamelde identiteitsbewijzen (KYC/witwaspreventie)
  verwerkt Stripe als zelfstandig verantwoordelijke. Wij ontvangen alleen de statuskenmerken
  <code>charges_enabled</code>, <code>payouts_enabled</code> en <code>details_submitted</code>.
</p>
<p>Privacyverklaring van Stripe: <a href="https://stripe.com/privacy" rel="noopener">stripe.com/privacy</a></p>

<h2>10. E-mailverzending</h2>
<p>
  Transactiemails (bestelbevestiging, verzendbericht, verkopersmelding) en nieuwsbrieven verzenden
  wij via
  <?= h($mail['provider'] === 'brevo' ? 'Brevo (Sendinblue GmbH / Brevo SAS, Parijs, Frankrijk)' : 'onze mailserver') ?>.
  Doorgegeven worden e-mailadres, naam en inhoud van het bericht.
</p>
<ul>
  <li><strong>Rechtsgrond:</strong> transactiemails art. 6 lid 1 sub b AVG; nieuwsbrief art. 6 lid 1
      sub a AVG (toestemming)</li>
  <li><strong>Verwerkersovereenkomst:</strong> er is een overeenkomst conform art. 28 AVG.</li>
</ul>

<h2>11. Nieuwsbrief / Vault-lidmaatschap</h2>
<p>
  Aanmelding verloopt via double opt-in: na invoer van uw adres ontvangt u een bevestigingsmail; pas
  met de klik op de link wordt de aanmelding van kracht. Als bewijs slaan wij het tijdstip van
  aanmelding, het tijdstip van bevestiging en een gezouten hashwaarde van het IP-adres op.
</p>
<p>
  Niet-bevestigde aanmeldingen verwijderen wij automatisch na 7 dagen. U kunt uw toestemming te
  allen tijde intrekken — via de afmeldlink in elke e-mail of per bericht aan ons. Intrekking laat
  de rechtmatigheid van de tot dan verrichte verwerking onverlet.
</p>

<h2>12. Verkopersaccounts</h2>
<p>
  Voor een verkopersaccount verwerken wij: naam, eventueel bedrijfsnaam, e-mailadres, land,
  eventueel btw-nummer, verkoperstype (zakelijk/particulier), wachtwoord (alleen als
  cryptografische hash, nooit in leesbare tekst), aanbiedingen en omzet- en uitbetalingsgegevens.
</p>
<ul>
  <li><strong>Rechtsgrond:</strong> art. 6 lid 1 sub b AVG; voor de controle van aanbiedingen en de
      traceerbaarheid van handelaarsgegevens bovendien art. 6 lid 1 sub c AVG juncto art. 30 Digital
      Services Act.</li>
  <li><strong>Publicatie:</strong> bij zakelijke verkopers tonen wij naam/bedrijf en land op de
      productpagina; dat is wettelijk verplicht. Bij particuliere verkopers wordt alleen de status
      „<?= te('seller_private') ?>” getoond, geen volledige naam.</li>
</ul>

<h2>13. Beveiligingsmaatregelen en logboeken</h2>
<p>
  Wij houden technische logboeken bij van mislukte inlogpogingen, betaalfouten en
  webhook-gebeurtenissen. Ze bevatten tijdstip, soort gebeurtenis en technische kenmerken;
  e-mailadressen worden daarbij ingekort. Doel is misbruik- en fraudebestrijding (art. 6 lid 1 sub f
  AVG), bewaartermijn maximaal 90 dagen.
</p>
<p>
  De overdracht is versleuteld (TLS). Wachtwoorden worden opgeslagen met een moderne
  eenrichtings-hashmethode.
</p>

<h2>14. Uw rechten</h2>
<p>U heeft te allen tijde recht op:</p>
<ul>
  <li>inzage in de over u opgeslagen gegevens (art. 15 AVG)</li>
  <li>rectificatie van onjuiste gegevens (art. 16 AVG)</li>
  <li>wissing (art. 17 AVG), voor zover geen bewaarplicht in de weg staat</li>
  <li>beperking van de verwerking (art. 18 AVG)</li>
  <li>overdraagbaarheid van gegevens (art. 20 AVG)</li>
  <li>bezwaar tegen verwerkingen op grond van gerechtvaardigd belang (art. 21 AVG)</li>
  <li>intrekking van gegeven toestemmingen met werking voor de toekomst (art. 7 lid 3 AVG)</li>
</ul>
<p>
  Een bericht aan
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-mail]</em>' ?>
  volstaat. Bovendien heeft u het recht een klacht in te dienen bij een toezichthoudende autoriteit
  voor gegevensbescherming, bijvoorbeeld die van uw gewone verblijfplaats.
</p>

<h2>15. Wijzigingen</h2>
<p>
  Wij passen deze verklaring aan wanneer de feitelijke verwerking verandert — bijvoorbeeld bij
  inzet van een nieuwe dienstverlener. De op deze pagina gepubliceerde versie is van toepassing.
</p>
