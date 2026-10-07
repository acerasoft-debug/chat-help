<?php
/**
 * Tillgänglighetsredogörelse (BFSG) — svenska (vägledande översättning).
 * Variabler: legal/barrierefreiheit.php.
 */
?>
<h2>Vår ambition</h2>
<p>
  Vi vill att den här butiken ska gå att använda för alla — med tangentbord, med skärmläsare, med
  förstorad text, med reducerad rörelse. Grunden är kraven i den tyska lagen om stärkt tillgänglighet
  (BFSG) och standarden EN 301 549, som hänvisar till WCAG 2.1 nivå AA.
</p>

<h2>Genomförandestatus</h2>
<p>
  Enligt vår bedömning är webbplatsen <strong>till stor del förenlig</strong> med WCAG 2.1 nivå AA.
  Bedömningen bygger på en intern granskning, inte på en extern revision.
</p>

<h3>Vad som är genomfört</h3>
<ul>
  <li><strong>Användbar utan JavaScript:</strong> navigering, filter, väska, kassa och säljarområde
      fungerar fullt ut med rena HTML-formulär. JavaScript ger bara bekvämlighet (nedräkning,
      antalsväljare, mjuka intoningar).</li>
  <li><strong>Tangentbordsstyrning:</strong> alla interaktiva element kan nås, med synlig
      fokusmarkering; en „Hoppa till innehåll“-länk finns överst på sidan.</li>
  <li><strong>Kontraster:</strong> varje textnod på huvudsidorna har mätts automatiskt (faktiskt
      renderad färg mot faktiskt renderad bakgrund, inklusive genomskinlighet). Alla når minst 4,5:1,
      stor text minst 3:1. Det mörka Vault-området har egna gråvärden och accentfärger har en mörkare
      textvariant på ljus bakgrund.</li>
  <li><strong>Struktur:</strong> en H1 per sida, logiska rubriknivåer, landmärken
      (<code>header</code>, <code>nav</code>, <code>main</code>, <code>footer</code>), etiketterade
      formulärfält, kopplade felmeddelanden.</li>
  <li><strong>Bilder:</strong> produktbilder har alternativtexter från märke och artikelnamn; rent
      dekorativ grafik är dold för hjälpmedel.</li>
  <li><strong>Rörelse:</strong> med systeminställningen „Minska rörelse“ aktiverad stängs löptext,
      intoningar och övergångar av.</li>
  <li><strong>Zoom och små skärmar:</strong> layouten förblir användbar upp till 400 % zoom utan
      horisontell rullning av sidinnehållet.</li>
  <li><strong>Språk:</strong> sidans språk är angivet i HTML och kan bytas via språkväljaren (tio
      språk).</li>
  <li><strong>Bildgalleri:</strong> varje produktbild är en vanlig länk till bildfilen. Utan
      JavaScript öppnas den direkt; med JavaScript i en lightbox som stängs med Esc och bläddras med
      piltangenterna.</li>
  <li><strong>Sökförslag:</strong> kan hanteras med piltangenter och Enter, stängs med Esc. Själva
      sökfältet fungerar även utan förslag som ett vanligt formulär.</li>
  <li><strong>Önskelista:</strong> utförd som formulär; tillståndet finns i <code>aria-pressed</code>
      och sparas korrekt även utan JavaScript.</li>
</ul>

<h3>Kända begränsningar</h3>
<ul>
  <li><strong>Produktbilder från tredjepartssäljare:</strong> alternativtexter bildas automatiskt av
      märke och benämning. De beskriver inte motivet i detalj — vid frågor om en artikel beskriver
      vi den gärna via e-post.</li>
  <li><strong>Betalningssida:</strong> betalningen sker hos Stripe. Stripe ansvarar för dessa sidors
      tillgänglighet; vi känner inte till några brister men har inte testat dem själva.</li>
  <li><strong>Vault-nedräkning:</strong> den återstående tiden uppdateras varje sekund. Det
      avgörande priset står som text bredvid och ändras bara efter en sidinläsning, så att
      skärmläsaranvändning inte störs av live-ändringar.</li>
  <li><strong>PDF-dokument:</strong> fakturor och returetiketter genereras delvis av säljare och
      transportörer och är kanske inte taggade. På begäran tillhandahåller vi innehållet i
      tillgänglig form.</li>
</ul>

<h2>Återkoppling och kontakt</h2>
<p>
  Stöter du på ett hinder, skriv till <?= $mail ?> med ämnet „Tillgänglighet“ och ange om möjligt
  sidan och ditt hjälpmedel. Vi svarar inom en arbetsdag och anger ett datum för åtgärd. Behöver du
  information i annan form — större text, klartext, uppläsning per telefon — säg bara till; vi
  tillhandahåller den kostnadsfritt.
</p>

<h2>Tillsynsförfarande</h2>
<p>
  Om vårt svar inte hjälper kan du vända dig till delstaternas marknadskontrollmyndighet för
  tillgänglighet hos produkter och tjänster (MDBD). Det är den enligt BFSG behöriga instansen för
  klagomål om tjänsters tillgänglighet:
  <a href="https://www.marktueberwachungsstelle.de" rel="noopener">marktueberwachungsstelle.de</a>.
</p>

<h2>Upprättande av denna redogörelse</h2>
<p>
  Denna redogörelse upprättades den <?= h(vr_date(strtotime('2026-08-01'))) ?> på grundval av en
  intern självbedömning: tangentbordsnavigering, automatisk kontrastmätning av alla textnoder i en
  riktig webbläsare, test med avstängt JavaScript, kontroll av rubrikstruktur och
  formuläretiketter. Ingen extern revision har gjorts. Vi uppdaterar redogörelsen när butiken
  ändras väsentligt.
</p>
