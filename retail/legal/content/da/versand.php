<?php
/**
 * Forsendelse & levering — dansk (vejledende oversættelse).
 * $ship og $countries kommer fra legal/versand.php; etiketterne står her.
 */
$zones = [
    'de'    => 'Tyskland',
    'eu'    => 'Den Europæiske Union',
    'ch'    => 'Schweiz, Liechtenstein, Norge, Storbritannien',
    'world' => 'Øvrige destinationer',
];
?>
<h2>Forsendelsesomkostninger og leveringstider</h2>
<div class="tablewrap" tabindex="0"><table>
  <thead>
    <tr><th>Destination</th><th>Forsendelse</th><th>Gratis fra</th><th>Leveringstid</th></tr>
  </thead>
  <tbody>
  <?php foreach ($zones as $key => $label):
      $row = (array)($ship[$key] ?? []); ?>
    <tr>
      <td><?= h($label) ?></td>
      <td><?= h(vr_money((int)($row['cents'] ?? 0))) ?></td>
      <td><?= (int)($row['free_over'] ?? 0) > 0 ? h(vr_money((int)$row['free_over'])) : '—' ?></td>
      <td><?= h((string)($row['days'] ?? '')) ?> arbejdsdage</td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<p>
  Alle beløb er slutpriser inklusive moms. De forsendelsesomkostninger, der gælder for din ordre,
  vises i tasken, så snart du har valgt leveringsland, og angives igen med nøjagtigt beløb under
  bestillingen, inden ordren afgives.
</p>

<h3>EU-lande, vi leverer til</h3>
<p class="doc__list">
  <?= h(implode(' · ', vr_country_options($countries['eu']))) ?>
</p>

<h3>Øvrige destinationer</h3>
<p class="doc__list">
  <?= h(implode(' · ', vr_country_options($countries['world']))) ?>
</p>
<p>
  Er dit land ikke på listen, så skriv til os — meget kan løses individuelt.
</p>

<h2>Forsendelsens start</h2>
<p>
  Leveringstiden begynder ved frigivelse af betalingen. Ved kortbetaling, Apple Pay og Google Pay
  sker det som regel straks; ved SEPA-direkte debitering og Klarna kan der komme en til tre
  bankdage til. Ordrer, der er frigivet inden kl. 13.00, afsendes som regel samme arbejdsdag.
</p>

<h2>Flere sælgere, flere pakker</h2>
<p>
  <?= h((string)vr_config('brand')) ?> er en markedsplads. Indeholder din ordre varer fra
  forskellige sælgere, afsender hver sælger separat. Du modtager så flere pakker og flere
  sporingslinks — men betaler kun én gang, og forsendelsesomkostninger beregnes kun én gang.
</p>

<h2>Sporing</h2>
<p>
  Hver forsendelse er forsikret og afsendes med sporingsnummer. Du modtager linket pr. e-mail, så
  snart pakken er overdraget. Den aktuelle status kan du desuden til enhver tid se under
  <a href="<?= h(vr_url('order.php')) ?>"><?= te('order_status') ?></a> med ordrenummer og
  e-mailadresse.
</p>

<h2>Pakkebokse og afvigende leveringsadresse</h2>
<p>
  Inden for Tyskland leverer vi til DHL-Packstationer; angiv Packstationen med postnummer som
  leveringsadresse. Til internationale forsendelser kræves en gadeadresse.
</p>

<h2>Told og importafgifter</h2>
<p>
  Inden for EU påløber ingen told eller importafgifter. Ved levering til Schweiz, Norge,
  Storbritannien eller uden for Europa kan der påløbe importmoms, told og transportørens
  ekspeditionsgebyrer. Disse bæres af modtageren og er ikke en del af den pris, der er betalt hos
  os.
</p>

<h2>Ikke-leverede forsendelser</h2>
<p>
  Returneres en pakke til sælgeren som uanbringelig, aftaler vi en ny forsendelse med dig. Der
  påløber nye forsendelsesomkostninger, hvis uanbringeligheden skyldes en ufuldstændig eller forkert
  leveringsadresse.
</p>

<h2>Transportskader</h2>
<p>
  Ankommer en pakke synligt beskadiget, så tag gerne imod den, dokumentér skaden med fotos og
  kontakt os inden 14 dage. Vi ordner sådanne sager uafhængigt af fortrydelsesretten — også ved
  private sælgere. Dine lovbestemte rettigheder begrænses ikke herved.
</p>

<p class="doc__related">
  Se også <a href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('legal_returns') ?></a> og
  <a href="<?= h(vr_url('legal/widerruf.php')) ?>"><?= te('legal_withdrawal') ?></a>.
</p>
