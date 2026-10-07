<?php
/**
 * Colofon (§ 5 DDG, § 18 MStV) — Nederlands (vertaling ter informatie). Variabelen: legal/impressum.php.
 */
?>
<h2>Aanbieder</h2>
<?php vr_company_block(); ?>

<h2>Exploitant van de marktplaats</h2>
<p>
  <?= h((string)vr_config('brand')) ?> is een online marktplaats, geëxploiteerd door
  <?= h((string)($c['legal_name'] ?? '')) ?>. Via het platform worden zowel eigen artikelen van de
  exploitant als artikelen van derden (zakelijke handelaren en particuliere verkopers) aangeboden.
  Wie uw wederpartij bij de koop is, wordt op elke productpagina en tijdens het bestelproces vóór het
  plaatsen van de bestelling getoond.
</p>

<h2>Verantwoordelijk voor de inhoud</h2>
<p>
  Verantwoordelijk volgens § 18 lid 2 MStV is de hierboven genoemde vertegenwoordigingsbevoegde
  persoon, adres als boven.
</p>

<h2>Contact voor consumentenvragen</h2>
<p>
  Vragen over bestellingen, retourzendingen en klachten richt u aan
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-mailadres]</em>' ?>.
  Wij antwoorden doorgaans binnen één werkdag. Bij vragen over een artikel van een derde verkoper
  brengen wij u in contact met de betreffende verkoper of sturen wij uw vraag door.
</p>

<h2>Consumentengeschillenbeslechting</h2>
<p>
  Wij zijn niet verplicht en niet bereid deel te nemen aan geschillenbeslechtingsprocedures voor een
  consumentengeschillencommissie. Dat sluit een minnelijke schikking met ons niet uit — wend u eerst
  rechtstreeks tot ons. Meer informatie onder
  <a href="<?= h(vr_url('legal/streitbeilegung.php')) ?>"><?= te('legal_disputes') ?></a>.
</p>

<h2>Betaaldienstverlener</h2>
<p>
  Betalingen worden afgewikkeld via Stripe (Stripe Payments Europe, Limited, 1 Grand Canal Street
  Lower, Grand Canal Dock, Dublin, Ierland). Uitbetalingen aan derde verkopers verlopen via Stripe
  Connect. Kaartgegevens worden uitsluitend bij Stripe verwerkt en bereiken onze systemen niet.
</p>

<h2>Aansprakelijkheid voor inhoud</h2>
<p>
  Als dienstverlener zijn wij voor eigen inhoud op deze pagina's verantwoordelijk volgens de
  algemene wetten. Voor aanbiedingen van derde verkopers zijn wij niet verplicht doorgegeven of
  opgeslagen informatie van derden te controleren of naar omstandigheden te zoeken die op
  onrechtmatige activiteit wijzen (art. 6, 8 Digital Services Act). Zodra wij kennis krijgen van een
  concrete rechtsschending, verwijderen wij de betreffende inhoud onverwijld. Meldingen richt u aan
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-mailadres]</em>' ?>.
</p>
<p>
  Wij toetsen aanbiedingen van derde verkopers vóór publicatie op plausibiliteit en verlangen
  herkomstbewijzen. Deze vrijwillige toets vormt geen garantie voor de rechtmatigheid of echtheid
  van elke afzonderlijke aanbieding en laat ons aansprakelijkheidsprivilege als hostingdienstverlener
  onverlet.
</p>

<h2>Aansprakelijkheid voor links</h2>
<p>
  Ons aanbod bevat links naar externe websites van derden, op de inhoud waarvan wij geen invloed
  hebben. Voor die inhoud is steeds de betreffende aanbieder verantwoordelijk. Op het moment van
  linken was geen onrechtmatige inhoud herkenbaar.
</p>

<h2>Auteursrecht</h2>
<p>
  De door de exploitant gemaakte inhoud en werken op deze pagina's vallen onder het auteursrecht.
  Productafbeeldingen en -beschrijvingen van derde verkopers worden door hen aangeleverd; zij
  garanderen ons over de vereiste rechten te beschikken. Merk- en productnamen zijn eigendom van de
  respectieve rechthebbenden. Hun vermelding dient uitsluitend ter beschrijving van de aangeboden
  artikelen en vestigt geen handelsrelatie met de merkhouders.
</p>

<h2>Opmerking over merkrechten</h2>
<p>
  <?= h((string)vr_config('brand')) ?> is geen geautoriseerde dealer van de genoemde merken, tenzij
  uitdrukkelijk anders vermeld. De aangeboden artikelen zijn originele waar die voor het eerst binnen
  de Europese Economische Ruimte in het verkeer is gebracht; doorverkoop is daarmee toegestaan
  volgens het beginsel van uitputting van het merkrecht (§ 24 MarkenG, art. 15 UMVo).
</p>
