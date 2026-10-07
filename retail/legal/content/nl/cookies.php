<?php
/**
 * Cookiebeleid — Nederlands (vertaling ter informatie). Geen banner, want geen tracking.
 */
?>
<div class="doc__box">
  <strong>Waarom u hier geen cookiebanner ziet</strong>
  <p style="margin-top:8px">
    Een banner is alleen nodig als er cookies worden geplaatst die verder gaan dan het technisch
    noodzakelijke — analyse, reclame, tracking. Daarvan gebruiken wij niets. Voor puur functionele
    cookies staat § 25 lid 2 nr. 2 TDDDG plaatsing zonder toestemming toe. Dus: geen banner, geen
    knop „Alles accepteren”, geen consent-dienst.
  </p>
</div>

<h2>Volledige lijst</h2>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Naam</th><th>Type</th><th>Doel</th><th>Bewaartermijn</th></tr></thead>
  <tbody>
    <tr>
      <td><code><?= h((string)vr_config('session_name', 'maxsales_retail')) ?></code></td>
      <td>sessiecookie</td>
      <td>Houdt uw sessie bij elkaar: inhoud van de tas, Vault-reserveringen, verkoperslogin en het
          beveiligingstoken tegen formuliervervalsing (CSRF). Bevat alleen een willekeurig kenmerk,
          geen persoonsgegevens.</td>
      <td>tot het einde van de browsersessie</td>
    </tr>
    <tr>
      <td><code>vr_lang</code></td>
      <td>functioneel</td>
      <td>Onthoudt de gekozen taal, zodat u die niet bij elke klik opnieuw hoeft te kiezen. Inhoud:
          een taalcode zoals <code>nl</code>.</td>
      <td>180 dagen</td>
    </tr>
    <tr>
      <td><code>vr_member</code></td>
      <td>functioneel</td>
      <td>Wordt alleen geplaatst wanneer u het Vault-lidmaatschap per e-mail hebt bevestigd en geeft
          vroegtijdige toegang tot nieuwe kavels. Bevat een vervaldatum, een ingekorte hashwaarde van
          uw e-mailadres en een handtekening — niet uw adres in leesbare tekst.</td>
      <td>1 jaar</td>
    </tr>
    <tr>
      <td><code>vr_wish</code></td>
      <td>functioneel</td>
      <td>Uw verlanglijst. Inhoud: een lijst artikelcodes, verder niets. Wordt niet aan de server
          gemeld, behalve wanneer u zelf de verlanglijstpagina opent.</td>
      <td>180 dagen</td>
    </tr>
    <tr>
      <td><code>vr_seen</code></td>
      <td>functioneel</td>
      <td>De door u recent bekeken artikelen, zodat u ze terugvindt. Eveneens alleen artikelcodes.</td>
      <td>30 dagen</td>
    </tr>
  </tbody>
</table></div>

<h2>Cookies van Stripe</h2>
<p>
  Bij het betalen gaat u naar een pagina van Stripe. Stripe plaatst daar eigen cookies die nodig
  zijn voor betalingsafwikkeling en fraudepreventie. Dat gebeurt op het domein van Stripe en valt
  onder de <a href="https://stripe.com/privacy" rel="noopener">privacyverklaring van Stripe</a>. Op
  onze eigen pagina's wordt geen Stripe-script ingebonden.
</p>

<h2>Geen local storage, geen fingerprints</h2>
<p>
  Wij gebruiken noch <code>localStorage</code> noch <code>sessionStorage</code>, geen pixels, geen
  fingerprintingtechnieken en geen herkenning over apparaten heen. Alle lettertypen, stijlen,
  scripts en afbeeldingen staan op onze eigen server; bij het laden van een pagina wordt geen
  verbinding met derden gemaakt.
</p>

<h2>Cookies verwijderen of blokkeren</h2>
<p>
  U kunt cookies te allen tijde in uw browserinstellingen verwijderen of blokkeren. Blokkeert u de
  sessiecookie, dan werken tas, kassa en verkoperslogin niet meer — dan ontbreekt de technische
  draad die uw stappen verbindt. Taal en Vault-toegang kunnen probleemloos worden geblokkeerd; dan
  vragen wij de taal opnieuw en vervalt de vroegtijdige toegang.
</p>
<p class="doc__related">
  Uitgebreid over de gegevensverwerking:
  <a href="<?= h(vr_url('legal/datenschutz.php')) ?>"><?= te('legal_privacy') ?></a>.
</p>
