<?php
/**
 * Cookiepolicy — svenska (vägledande översättning). Ingen banner, eftersom ingen spårning.
 */
?>
<div class="doc__box">
  <strong>Varför du inte ser någon cookiebanner här</strong>
  <p style="margin-top:8px">
    En banner krävs bara om cookies sätts som går utöver det tekniskt nödvändiga — analys, reklam,
    spårning. Vi använder inget av detta. För rent funktionella cookies tillåter § 25 st. 2 nr 2
    TDDDG att de sätts utan samtycke. Alltså: ingen banner, ingen „Acceptera alla“-knapp, ingen
    samtyckestjänst.
  </p>
</div>

<h2>Fullständig lista</h2>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Namn</th><th>Typ</th><th>Ändamål</th><th>Lagringstid</th></tr></thead>
  <tbody>
    <tr>
      <td><code><?= h((string)vr_config('session_name', 'maxsales_retail')) ?></code></td>
      <td>sessionscookie</td>
      <td>Håller ihop din session: väskans innehåll, Vault-reservationer, säljarinloggning och
          säkerhetstoken mot formulärförfalskning (CSRF). Innehåller endast en slumpmässig
          identifierare, inget personligt innehåll.</td>
      <td>till webbläsarsessionens slut</td>
    </tr>
    <tr>
      <td><code>vr_lang</code></td>
      <td>funktionell</td>
      <td>Kommer ihåg valt språk så att du slipper välja det vid varje klick. Innehåll: en språkkod
          som <code>sv</code>.</td>
      <td>180 dagar</td>
    </tr>
    <tr>
      <td><code>vr_member</code></td>
      <td>funktionell</td>
      <td>Sätts bara när du bekräftat Vault-medlemskapet via e-post och låser upp förhandstillgång
          till nya partier. Innehåller ett utgångsdatum, ett förkortat hashvärde av din e-postadress
          och en signatur — inte din adress i klartext.</td>
      <td>1 år</td>
    </tr>
    <tr>
      <td><code>vr_wish</code></td>
      <td>funktionell</td>
      <td>Din önskelista. Innehåll: en lista med artikelnummer, inget annat. Rapporteras inte till
          servern annat än när du själv öppnar önskelistesidan.</td>
      <td>180 dagar</td>
    </tr>
    <tr>
      <td><code>vr_seen</code></td>
      <td>funktionell</td>
      <td>De artiklar du senast tittat på, så att du hittar dem igen. Likaså endast artikelnummer.</td>
      <td>30 dagar</td>
    </tr>
  </tbody>
</table></div>

<h2>Cookies från Stripe</h2>
<p>
  Vid betalning går du över till en sida hos Stripe. Stripe sätter där egna cookies som krävs för
  betalningshantering och bedrägeriförebyggande. Det sker på Stripes domän och omfattas av
  <a href="https://stripe.com/privacy" rel="noopener">Stripes integritetspolicy</a>. På våra egna
  sidor bäddas inget Stripe-skript in.
</p>

<h2>Ingen lokal lagring, inga fingeravtryck</h2>
<p>
  Vi använder varken <code>localStorage</code> eller <code>sessionStorage</code>, inga pixlar, inga
  fingerprinting-tekniker och ingen igenkänning över enheter. Alla typsnitt, stilar, skript och
  bilder ligger på vår egen server; vid sidinläsning upprättas ingen anslutning till tredje part.
</p>

<h2>Radera eller blockera cookies</h2>
<p>
  Du kan när som helst radera eller blockera cookies i webbläsarens inställningar. Blockerar du
  sessionscookien fungerar inte väska, kassa och säljarinloggning längre — då saknas den tekniska
  tråd som binder ihop dina steg. Språk och Vault-tillgång kan blockeras utan problem; då frågar vi
  efter språket igen och förhandstillgången uteblir.
</p>
<p class="doc__related">
  Utförligt om databehandlingen:
  <a href="<?= h(vr_url('legal/datenschutz.php')) ?>"><?= te('legal_privacy') ?></a>.
</p>
