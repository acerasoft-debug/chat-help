<?php
/**
 * Tilgængelighedserklæring (BFSG) — dansk (vejledende oversættelse).
 * Variabler: legal/barrierefreiheit.php.
 */
?>
<h2>Vores mål</h2>
<p>
  Vi ønsker, at denne shop kan bruges af alle — med tastatur, med skærmlæser, med forstørret skrift,
  med reduceret bevægelse. Grundlaget er kravene i den tyske lov om styrkelse af tilgængelighed
  (BFSG) og standarden EN 301 549, der henviser til WCAG 2.1 niveau AA.
</p>

<h2>Status for implementeringen</h2>
<p>
  Efter vores vurdering er dette website <strong>i vidt omfang i overensstemmelse</strong> med
  WCAG 2.1 niveau AA. Vurderingen bygger på en intern gennemgang, ikke på en ekstern audit.
</p>

<h3>Hvad der er implementeret</h3>
<ul>
  <li><strong>Brugbart uden JavaScript:</strong> navigation, filtre, taske, kasse og sælgerområde
      fungerer fuldt ud med rene HTML-formularer. JavaScript giver kun komfort (nedtælling,
      mængdevælger, bløde indtoninger).</li>
  <li><strong>Tastaturbetjening:</strong> alle interaktive elementer kan nås, med synlig
      fokusmarkering; et „Spring til indhold“-link står øverst på siden.</li>
  <li><strong>Kontraster:</strong> hvert tekstelement på hovedsiderne er målt automatisk (faktisk
      gengivet farve mod faktisk gengivet baggrund, inklusive gennemsigtighed). Alle når mindst
      4,5:1, stor skrift mindst 3:1. Det mørke Vault-område har egne gråværdier, og accentfarver har
      en mørkere tekstvariant på lys baggrund.</li>
  <li><strong>Struktur:</strong> én H1 pr. side, logiske overskriftsniveauer, landemærker
      (<code>header</code>, <code>nav</code>, <code>main</code>, <code>footer</code>), mærkede
      formularfelter, tilknyttede fejlmeddelelser.</li>
  <li><strong>Billeder:</strong> produktbilleder har alternative tekster ud fra mærke og varenavn;
      rent dekorative grafikker er skjult for hjælpemidler.</li>
  <li><strong>Bevægelse:</strong> ved aktiveret systemindstilling „Reducér bevægelse“ slås rulletekst,
      indtoninger og overgange fra.</li>
  <li><strong>Zoom og små skærme:</strong> layoutet forbliver brugbart op til 400 % zoom uden
      vandret rulning af sideindholdet.</li>
  <li><strong>Sprog:</strong> sidens sprog er angivet i HTML og kan skiftes via sprogvælgeren (ti
      sprog).</li>
  <li><strong>Billedgalleri:</strong> hvert produktbillede er et almindeligt link til billedfilen.
      Uden JavaScript åbnes det direkte; med JavaScript i en lightbox, der lukkes med Esc og
      bladres med piletasterne.</li>
  <li><strong>Søgeforslag:</strong> kan betjenes med piletaster og Enter, lukkes med Esc. Selve
      søgefeltet fungerer også uden forslag som en almindelig formular.</li>
  <li><strong>Ønskeliste:</strong> udført som formular; tilstanden står i <code>aria-pressed</code>
      og gemmes korrekt også uden JavaScript.</li>
</ul>

<h3>Kendte begrænsninger</h3>
<ul>
  <li><strong>Produktbilleder fra tredjepartssælgere:</strong> alternative tekster dannes automatisk
      ud fra mærke og betegnelse. De beskriver ikke motivet i detaljer — ved spørgsmål om en vare
      beskriver vi den gerne pr. e-mail.</li>
  <li><strong>Betalingsside:</strong> betalingen foregår hos Stripe. Stripe er ansvarlig for disse
      siders tilgængelighed; vi har ikke kendskab til mangler, men har ikke selv testet dem.</li>
  <li><strong>Vault-nedtælling:</strong> den resterende tid opdateres hvert sekund. Den gældende pris
      står som tekst ved siden af og ændres først efter en sideindlæsning, så skærmlæserbrug ikke
      forstyrres af live-ændringer.</li>
  <li><strong>PDF-dokumenter:</strong> fakturaer og returlabels genereres delvist af sælgere og
      transportører og er muligvis ikke taggede. På anmodning stiller vi indholdet til rådighed i
      tilgængelig form.</li>
</ul>

<h2>Feedback og kontakt</h2>
<p>
  Støder du på en barriere, så skriv til <?= $mail ?> med emnet „Tilgængelighed“ og nævn så vidt
  muligt siden og dit hjælpemiddel. Vi svarer inden for én arbejdsdag og angiver en dato for
  udbedringen. Har du brug for en oplysning i anden form — større skrift, klartekst, oplæsning pr.
  telefon — så sig til; vi stiller den gratis til rådighed.
</p>

<h2>Håndhævelsesprocedure</h2>
<p>
  Hjælper vores svar ikke, kan du henvende dig til delstaternes markedsovervågningsmyndighed for
  tilgængelighed af produkter og tjenester (MDBD). Det er den efter BFSG kompetente instans for klager
  over tjenesters tilgængelighed:
  <a href="https://www.marktueberwachungsstelle.de" rel="noopener">marktueberwachungsstelle.de</a>.
</p>

<h2>Udarbejdelse af denne erklæring</h2>
<p>
  Denne erklæring blev udarbejdet den <?= h(vr_date(strtotime('2026-08-01'))) ?> på grundlag af en
  intern selvevaluering: tastaturnavigation, automatisk kontrastmåling af alle tekstelementer i en
  rigtig browser, test med deaktiveret JavaScript, kontrol af overskriftsstruktur og
  formularmærkninger. En ekstern audit har ikke fundet sted. Vi opdaterer erklæringen, når shoppen
  ændres væsentligt.
</p>
