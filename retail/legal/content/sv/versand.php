<?php
/**
 * Frakt & leverans — svenska (vägledande översättning).
 * $ship och $countries kommer från legal/versand.php; etiketterna finns här.
 */
$zones = [
    'de'    => 'Tyskland',
    'eu'    => 'Europeiska unionen',
    'ch'    => 'Schweiz, Liechtenstein, Norge, Storbritannien',
    'world' => 'Övriga destinationer',
];
?>
<h2>Fraktkostnader och leveranstider</h2>
<div class="tablewrap" tabindex="0"><table>
  <thead>
    <tr><th>Destination</th><th>Frakt</th><th>Fraktfritt från</th><th>Leveranstid</th></tr>
  </thead>
  <tbody>
  <?php foreach ($zones as $key => $label):
      $row = (array)($ship[$key] ?? []); ?>
    <tr>
      <td><?= h($label) ?></td>
      <td><?= h(vr_money((int)($row['cents'] ?? 0))) ?></td>
      <td><?= (int)($row['free_over'] ?? 0) > 0 ? h(vr_money((int)$row['free_over'])) : '—' ?></td>
      <td><?= h((string)($row['days'] ?? '')) ?> arbetsdagar</td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<p>
  Alla belopp är slutpriser inklusive moms. De fraktkostnader som gäller för din beställning visas i
  väskan så snart du valt leveransland, och anges åter med exakt belopp under beställningen innan
  ordern läggs.
</p>

<h3>EU-länder vi levererar till</h3>
<p class="doc__list">
  <?= h(implode(' · ', vr_country_options($countries['eu']))) ?>
</p>

<h3>Övriga destinationer</h3>
<p class="doc__list">
  <?= h(implode(' · ', vr_country_options($countries['world']))) ?>
</p>
<p>
  Finns inte ditt land med, skriv till oss — mycket går att lösa individuellt.
</p>

<h2>När frakten startar</h2>
<p>
  Leveranstiden börjar löpa när betalningen frigjorts. Vid kortbetalning, Apple Pay och Google Pay
  sker det i regel omedelbart; vid SEPA-autogiro och Klarna kan en till tre bankdagar tillkomma.
  Beställningar som frigjorts före kl. 13.00 skickas i regel samma arbetsdag.
</p>

<h2>Flera säljare, flera paket</h2>
<p>
  <?= h((string)vr_config('brand')) ?> är en marknadsplats. Om din beställning innehåller varor
  från olika säljare skickar varje säljare separat. Du får då flera paket och flera spårningslänkar
  — men betalar bara en gång, och fraktkostnad tas ut bara en gång.
</p>

<h2>Spårning</h2>
<p>
  Varje försändelse är försäkrad och skickas med spårningsnummer. Länken får du per e-post så snart
  paketet överlämnats. Aktuell status kan du dessutom alltid se under
  <a href="<?= h(vr_url('order.php')) ?>"><?= te('order_status') ?></a> med ordernummer och
  e-postadress.
</p>

<h2>Paketboxar och avvikande leveransadress</h2>
<p>
  Inom Tyskland levererar vi till DHL Packstation; ange Packstation med postnummer som
  leveransadress. För internationella försändelser krävs en gatuadress.
</p>

<h2>Tull och importavgifter</h2>
<p>
  Inom EU tillkommer ingen tull eller importavgift. Vid leverans till Schweiz, Norge, Storbritannien
  eller utanför Europa kan importmoms, tull och transportörens hanteringsavgifter tillkomma. Dessa
  bärs av mottagaren och ingår inte i det pris som betalats hos oss.
</p>

<h2>Ej utlämnade försändelser</h2>
<p>
  Om ett paket returneras till säljaren som obeställbart kommer vi överens med dig om ny
  försändelse. Ny fraktkostnad tillkommer om obeställbarheten beror på en ofullständig eller
  felaktig leveransadress.
</p>

<h2>Transportskador</h2>
<p>
  Om ett paket anländer synligt skadat, ta gärna emot det, dokumentera skadan med foton och hör av
  dig till oss inom 14 dagar. Vi reglerar sådana ärenden oberoende av ångerrätten — även hos privata
  säljare. Dina lagstadgade rättigheter begränsas inte av detta.
</p>

<p class="doc__related">
  Se även <a href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('legal_returns') ?></a> och
  <a href="<?= h(vr_url('legal/widerruf.php')) ?>"><?= te('legal_withdrawal') ?></a>.
</p>
