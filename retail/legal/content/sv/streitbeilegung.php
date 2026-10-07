<?php
/**
 * Tvistlösning + DSA-kontaktpunkt + anmälningsförfarande (art. 16 DSA) — svenska.
 * Variabler: legal/streitbeilegung.php.
 */
?>
<h2>Först den direkta vägen</h2>
<p>
  De flesta problem går att lösa i ett mejl. Skriv till <?= $mail ?> med ditt ordernummer och en kort
  beskrivning. Vi svarar i regel inom en arbetsdag och återkommer senast efter sju dagar med ett
  beslut eller en lägesrapport.
</p>

<h2>Klagomål på säljare</h2>
<p>
  Vid problem med en tredjepartssäljare — varan har inte kommit, skicket avviker, återbetalning
  uteblir — medlar vi och kan hålla inne säljarens andel tills saken är utredd. Nås ingen lösning
  återbetalar vi själva i de fall som anges i köpvillkorens
  <a href="<?= h(vr_url('legal/agb.php')) ?>#s10">punkt 10.3</a> och
  <a href="<?= h(vr_url('legal/agb.php')) ?>#s12">12.2</a>.
</p>

<h2>Anmälan av olagligt innehåll (art. 16 DSA)</h2>
<p>
  Vem som helst kan anmäla erbjudanden till oss som hen anser olagliga — t.ex. förfalskningar,
  varumärkesintrång eller otillåtna produkter. Ange gärna:
</p>
<ul>
  <li>erbjudandets exakta adress (URL),</li>
  <li>en motivering till varför innehållet skulle vara olagligt,</li>
  <li>ditt namn och en e-postadress (utom vid anmälan av brott mot personer),</li>
  <li>en försäkran om att dina uppgifter såvitt du vet är riktiga och fullständiga.</li>
</ul>
<p>
  Anmälningar skickas till <?= $mail ?> med ämnet „DSA-anmälan“. Vi bekräftar mottagandet
  omgående, beslutar skyndsamt och omsorgsfullt och meddelar dig beslutet med motivering.
  Rättighetshavare som upprepat skickar oss korrekta anmälningar behandlas med förtur (art. 22
  DSA).
</p>

<h2>Om vi tar bort ett erbjudande</h2>
<p>
  Berörda säljare informeras med motivering om borttagning, spärrning eller minskad synlighet
  (art. 17 DSA) och kan invända mot beslutet via e-post inom 14 dagar. Invändningen prövas av en
  person som inte deltog i det ursprungliga beslutet. Beslutet om invändningen meddelas med
  motivering.
</p>
<p>
  Vid uppenbart ogrundade anmälningar eller upprepat olagliga erbjudanden avbryter vi handläggningen
  efter rimlig förvarning respektive spärrar kontot (art. 23 DSA).
</p>

<h2>Tvistlösning utanför domstol för konsumenter</h2>
<p>
  Vi är varken skyldiga eller villiga att delta i tvistlösningsförfaranden inför en nämnd för
  konsumenttvister enligt den tyska lagen VSBG. Din möjlighet att gå till domstol påverkas inte;
  inte heller vår strävan att först lösa varje ärende direkt.
</p>
<p>
  Observera: Europeiska kommissionens tidigare plattform för tvistlösning online (ODR-plattformen)
  lades ned den 20 juli 2025. Länken dit har därför tagits bort. Vid gränsöverskridande ärenden kan
  du vända dig till Europeiska konsumentcentrumet (<a href="https://www.evz.de" rel="noopener">evz.de</a>).
</p>

<h2>Behörig domstol och tillämplig lag</h2>
<p>
  Tysk lag gäller. För konsumenter gäller de lagstadgade forumreglerna; tvingande
  konsumentskyddsregler i vistelsestaten berörs inte (art. 6.2 Rom I-förordningen).
</p>

<h2>Kontaktpunkt för myndigheter</h2>
<p>
  För förfrågningar från myndigheter och domstolar enligt art. 11 DSA når du oss på <?= $mail ?>.
  Handläggningsspråk är tyska och engelska.
</p>
