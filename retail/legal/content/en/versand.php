<?php
/**
 * Shipping & Delivery — English (courtesy translation).
 * $ship and $countries come from legal/versand.php; labels live here.
 */
$zones = [
    'de'    => 'Germany',
    'eu'    => 'European Union',
    'ch'    => 'Switzerland, Liechtenstein, Norway, United Kingdom',
    'world' => 'Other destinations',
];
?>
<h2>Shipping costs and delivery times</h2>
<div class="tablewrap" tabindex="0"><table>
  <thead>
    <tr><th>Destination</th><th>Shipping</th><th>Free from</th><th>Delivery time</th></tr>
  </thead>
  <tbody>
  <?php foreach ($zones as $key => $label):
      $row = (array)($ship[$key] ?? []); ?>
    <tr>
      <td><?= h($label) ?></td>
      <td><?= h(vr_money((int)($row['cents'] ?? 0))) ?></td>
      <td><?= (int)($row['free_over'] ?? 0) > 0 ? h(vr_money((int)$row['free_over'])) : '—' ?></td>
      <td><?= h((string)($row['days'] ?? '')) ?> working days</td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<p>
  All amounts are final prices including VAT. The shipping costs applicable to your order are shown
  in the bag as soon as you have selected the delivery country, and are stated again to the exact
  amount during checkout before you place the order.
</p>

<h3>EU countries we deliver to</h3>
<p class="doc__list">
  <?= h(implode(' · ', vr_country_options($countries['eu']))) ?>
</p>

<h3>Other destinations</h3>
<p class="doc__list">
  <?= h(implode(' · ', vr_country_options($countries['world']))) ?>
</p>
<p>
  If your country is not listed, write to us — much can be arranged individually.
</p>

<h2>When shipping starts</h2>
<p>
  The delivery time begins with the release of payment. For card payments, Apple Pay and Google Pay
  this is usually immediate; for SEPA direct debit and Klarna one to three bank working days may be
  added. Orders released by 13:00 usually leave the same working day.
</p>

<h2>Several sellers, several parcels</h2>
<p>
  <?= h((string)vr_config('brand')) ?> is a marketplace. If your order contains items from different
  sellers, each seller ships separately. You then receive several parcels and several tracking links
  — but pay only once, and shipping is charged only once.
</p>

<h2>Tracking</h2>
<p>
  Every shipment is insured and sent with a tracking number. You receive the link by e-mail as soon
  as the parcel has been handed over. You can also check the current status at any time under
  <a href="<?= h(vr_url('order.php')) ?>"><?= te('order_status') ?></a> with your order number and
  e-mail address.
</p>

<h2>Parcel lockers and different delivery addresses</h2>
<p>
  Within Germany we deliver to DHL Packstations; enter the Packstation together with your post number
  as the delivery address. A street address is required for international shipments.
</p>

<h2>Customs and import charges</h2>
<p>
  Within the EU no customs duties or import charges apply. For deliveries to Switzerland, Norway, the
  United Kingdom or outside Europe, import VAT, customs duties and the carrier's handling fees may
  apply. These are borne by the recipient and are not part of the price paid to us.
</p>

<h2>Undelivered shipments</h2>
<p>
  If a parcel is returned to the seller as undeliverable, we arrange re-shipment with you. Shipping
  is charged again if the parcel could not be delivered because of an incomplete or incorrect
  delivery address.
</p>

<h2>Transport damage</h2>
<p>
  If a parcel arrives visibly damaged, feel free to accept it, document the damage with photos and
  contact us within 14 days. We settle such cases regardless of the right of withdrawal — including
  for private sellers. Your statutory rights are not limited by this.
</p>

<p class="doc__related">
  See also <a href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('legal_returns') ?></a> and
  <a href="<?= h(vr_url('legal/widerruf.php')) ?>"><?= te('legal_withdrawal') ?></a>.
</p>
