<?php
/**
 * Verzending & levering — Nederlands (vertaling ter informatie).
 * $ship en $countries komen uit legal/versand.php; de labels staan hier.
 */
$zones = [
    'de'    => 'Duitsland',
    'eu'    => 'Europese Unie',
    'ch'    => 'Zwitserland, Liechtenstein, Noorwegen, Verenigd Koninkrijk',
    'world' => 'Overige bestemmingen',
];
?>
<h2>Verzendkosten en levertijden</h2>
<div class="tablewrap" tabindex="0"><table>
  <thead>
    <tr><th>Bestemming</th><th>Verzendkosten</th><th>Gratis vanaf</th><th>Levertijd</th></tr>
  </thead>
  <tbody>
  <?php foreach ($zones as $key => $label):
      $row = (array)($ship[$key] ?? []); ?>
    <tr>
      <td><?= h($label) ?></td>
      <td><?= h(vr_money((int)($row['cents'] ?? 0))) ?></td>
      <td><?= (int)($row['free_over'] ?? 0) > 0 ? h(vr_money((int)$row['free_over'])) : '—' ?></td>
      <td><?= h((string)($row['days'] ?? '')) ?> werkdagen</td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<p>
  Alle bedragen zijn eindprijzen inclusief btw. De voor uw bestelling geldende verzendkosten worden
  in de tas getoond zodra u het leveringsland hebt gekozen, en tijdens het bestelproces vóór het
  plaatsen van de bestelling nogmaals op de cent nauwkeurig vermeld.
</p>

<h3>Belevering van EU-landen</h3>
<p class="doc__list">
  <?= h(implode(' · ', vr_country_options($countries['eu']))) ?>
</p>

<h3>Overige bestemmingen</h3>
<p class="doc__list">
  <?= h(implode(' · ', vr_country_options($countries['world']))) ?>
</p>
<p>
  Staat uw land er niet bij, schrijf ons dan — veel is individueel op te lossen.
</p>

<h2>Start van de verzending</h2>
<p>
  De levertijd gaat in bij vrijgave van de betaling. Bij kaartbetaling, Apple Pay en Google Pay is
  dat doorgaans direct; bij SEPA-incasso en Klarna kunnen één tot drie bankwerkdagen bijkomen.
  Bestellingen die vóór 13:00 uur zijn vrijgegeven, gaan doorgaans dezelfde werkdag de deur uit.
</p>

<h2>Meerdere verkopers, meerdere pakketten</h2>
<p>
  <?= h((string)vr_config('brand')) ?> is een marktplaats. Bevat uw bestelling artikelen van
  verschillende verkopers, dan verzendt elke verkoper afzonderlijk. U ontvangt dan meerdere
  pakketten en meerdere trackinglinks — maar betaalt slechts eenmaal, en verzendkosten worden
  slechts eenmaal berekend.
</p>

<h2>Track &amp; trace</h2>
<p>
  Elke zending is verzekerd en wordt met trackingnummer verzonden. De link ontvangt u per e-mail
  zodra het pakket is overgedragen. De actuele status kunt u bovendien te allen tijde opvragen via
  <a href="<?= h(vr_url('order.php')) ?>"><?= te('order_status') ?></a> met bestelnummer en
  e-mailadres.
</p>

<h2>Pakketkluizen en afwijkend leveringsadres</h2>
<p>
  Binnen Duitsland leveren wij aan DHL-Packstations; geef de Packstation met postnummer als
  leveringsadres op. Voor internationale zendingen is een straatadres vereist.
</p>

<h2>Douane en invoerheffingen</h2>
<p>
  Binnen de EU zijn geen invoerrechten of invoerheffingen verschuldigd. Bij leveringen naar
  Zwitserland, Noorwegen, het Verenigd Koninkrijk of buiten Europa kunnen invoer-btw, invoerrechten
  en afhandelingskosten van de vervoerder verschuldigd zijn. Deze komen voor rekening van de
  ontvanger en maken geen deel uit van de bij ons betaalde prijs.
</p>

<h2>Niet-bezorgde zendingen</h2>
<p>
  Wordt een pakket als onbestelbaar aan de verkoper geretourneerd, dan stemmen wij een nieuwe
  verzending met u af. Nieuwe verzendkosten zijn verschuldigd als de onbestelbaarheid het gevolg is
  van een onvolledig of onjuist leveringsadres.
</p>

<h2>Transportschade</h2>
<p>
  Komt een pakket zichtbaar beschadigd aan, neem het dan gerust aan, documenteer de schade met
  foto's en meld u binnen 14 dagen bij ons. Wij regelen zulke gevallen onafhankelijk van het
  herroepingsrecht — ook bij particuliere verkopers. Uw wettelijke rechten worden daardoor niet
  beperkt.
</p>

<p class="doc__related">
  Zie ook <a href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('legal_returns') ?></a> en
  <a href="<?= h(vr_url('legal/widerruf.php')) ?>"><?= te('legal_withdrawal') ?></a>.
</p>
