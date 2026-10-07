<?php
/**
 * Toegankelijkheidsverklaring (BFSG) — Nederlands (vertaling ter informatie).
 * Variabelen: legal/barrierefreiheit.php.
 */
?>
<h2>Onze ambitie</h2>
<p>
  Wij willen dat deze winkel voor iedereen bruikbaar is — met toetsenbord, met schermlezer, met
  vergrote tekst, met verminderde beweging. Basis zijn de eisen van de Duitse wet ter versterking van
  de toegankelijkheid (BFSG) en de norm EN 301 549, die verwijst naar WCAG 2.1 niveau AA.
</p>

<h2>Stand van de uitvoering</h2>
<p>
  Naar onze inschatting voldoet deze website <strong>grotendeels</strong> aan WCAG 2.1 niveau AA. De
  inschatting berust op een interne toets, niet op een externe audit.
</p>

<h3>Wat is gerealiseerd</h3>
<ul>
  <li><strong>Bruikbaar zonder JavaScript:</strong> navigatie, filters, tas, kassa en
      verkopersomgeving werken volledig met pure HTML-formulieren. JavaScript levert alleen comfort
      (aftelklok, aantalkeuze, zachte overgangen).</li>
  <li><strong>Toetsenbordbediening:</strong> alle interactieve elementen zijn bereikbaar, met een
      zichtbare focusring; een link „Naar de inhoud” staat bovenaan de pagina.</li>
  <li><strong>Contrasten:</strong> elk tekstknooppunt op de hoofdpagina's is geautomatiseerd gemeten
      (daadwerkelijk gerenderde kleur tegen daadwerkelijk gerenderde achtergrond, inclusief
      transparanties). Alle halen minstens 4,5:1, grote tekst minstens 3:1. Het donkere Vault-gedeelte
      heeft eigen grijswaarden en accentkleuren hebben op lichte achtergrond een donkerdere
      tekstvariant.</li>
  <li><strong>Structuur:</strong> één H1 per pagina, logische kopniveaus, oriëntatiepunten
      (<code>header</code>, <code>nav</code>, <code>main</code>, <code>footer</code>), gelabelde
      formuliervelden, gekoppelde foutmeldingen.</li>
  <li><strong>Afbeeldingen:</strong> productafbeeldingen hebben alternatieve teksten uit merk en
      artikelnaam; puur decoratieve graphics zijn voor hulpmiddelen verborgen.</li>
  <li><strong>Beweging:</strong> bij ingeschakelde systeeminstelling „Beweging verminderen” worden
      lichtkrant, overgangen en animaties uitgeschakeld.</li>
  <li><strong>Zoom en kleine schermen:</strong> de lay-out blijft bruikbaar tot 400 % zoom zonder
      horizontaal scrollen van de pagina-inhoud.</li>
  <li><strong>Taal:</strong> de paginataal is in de HTML gemarkeerd en via de taalkeuze te wisselen
      (tien talen).</li>
  <li><strong>Afbeeldingengalerij:</strong> elke productafbeelding is een gewone link naar het
      afbeeldingsbestand. Zonder JavaScript opent die direct; met JavaScript in een lightbox die met
      Esc sluit en met de pijltjestoetsen te doorbladeren is.</li>
  <li><strong>Zoeksuggesties:</strong> te bedienen met pijltjestoetsen en Enter, te sluiten met Esc.
      Het zoekveld zelf werkt ook zonder suggesties als gewoon formulier.</li>
  <li><strong>Verlanglijst:</strong> als formulier uitgevoerd; de status staat in
      <code>aria-pressed</code> en wordt ook zonder JavaScript correct opgeslagen.</li>
</ul>

<h3>Bekende beperkingen</h3>
<ul>
  <li><strong>Productafbeeldingen van derde verkopers:</strong> alternatieve teksten worden
      automatisch gevormd uit merk en naam. Ze beschrijven het motief niet in detail — bij vragen
      over een artikel beschrijven wij het graag per e-mail.</li>
  <li><strong>Betaalpagina:</strong> het betaalproces verloopt bij Stripe. Voor de toegankelijkheid
      van die pagina's is Stripe verantwoordelijk; ons zijn geen conformiteitstekorten bekend, maar
      wij hebben ze niet zelf getoetst.</li>
  <li><strong>Vault-aftelklok:</strong> de resterende tijd wordt elke seconde bijgewerkt. De
      bepalende prijs staat als tekst ernaast en verandert alleen na het laden van de pagina, zodat
      schermlezergebruik niet door live-wijzigingen wordt verstoord.</li>
  <li><strong>PDF-documenten:</strong> facturen en retourlabels worden deels door verkopers en
      vervoerders gegenereerd en zijn mogelijk niet getagd. Op verzoek stellen wij de inhoud in
      toegankelijke vorm beschikbaar.</li>
</ul>

<h2>Feedback en contact</h2>
<p>
  Stuit u op een barrière, schrijf dan naar <?= $mail ?> met als onderwerp „Toegankelijkheid” en
  noem zo mogelijk de pagina en uw hulpmiddel. Wij antwoorden binnen één werkdag en noemen een datum
  voor de oplossing. Heeft u informatie in een andere vorm nodig — grotere tekst, platte tekst,
  voorlezen per telefoon — zeg het gewoon; wij stellen die kosteloos beschikbaar.
</p>

<h2>Handhavingsprocedure</h2>
<p>
  Helpt ons antwoord niet verder, dan kunt u zich wenden tot de markttoezichtautoriteit van de
  deelstaten voor de toegankelijkheid van producten en diensten (MDBD). Dit is de volgens de BFSG
  bevoegde instantie voor klachten over de toegankelijkheid van diensten:
  <a href="https://www.marktueberwachungsstelle.de" rel="noopener">marktueberwachungsstelle.de</a>.
</p>

<h2>Opstelling van deze verklaring</h2>
<p>
  Deze verklaring is op <?= h(vr_date(strtotime('2026-08-01'))) ?> opgesteld op basis van een
  interne zelfbeoordeling: toetsenbordnavigatie, geautomatiseerde contrastmeting van alle
  tekstknooppunten in een echte browser, test met uitgeschakeld JavaScript, controle van de
  kopstructuur en de formulierlabels. Een externe audit heeft niet plaatsgevonden. Wij actualiseren
  de verklaring wanneer de winkel wezenlijk verandert.
</p>
