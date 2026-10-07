<?php
/**
 * Returns — English (courtesy translation). Variables come from legal/rueckgabe.php.
 */
?>
<h2>At a glance</h2>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Seller</th><th>Period</th><th>Cost of the return shipment</th></tr></thead>
  <tbody>
    <tr><td><?= h((string)vr_config('brand')) ?> stock</td>
        <td><?= (int)$days ?> days (statutory <?= (int)$wd ?> + voluntary extension)</td>
        <td>free from Germany within the statutory period</td></tr>
    <tr><td>Retailer</td>
        <td><?= (int)$wd ?> days statutory withdrawal; many retailers grant more</td>
        <td>free from Germany within the statutory period</td></tr>
    <tr><td>Private seller</td>
        <td>no statutory right of withdrawal</td>
        <td>return only if the item deviates from the description</td></tr>
  </tbody>
</table></div>

<h2>How to return</h2>
<ol>
  <li>Write to
      <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-mail]</em>' ?>
      with your order number and the items you want to return.</li>
  <li>Within one working day you receive a return label and the return address of the respective
      seller.</li>
  <li>Pack the goods, ideally in the original box, enclose the delivery note and hand the parcel
      in.</li>
  <li>After receipt and inspection we refund to the same means of payment — no later than 14 days
      after receipt of your withdrawal notice, as soon as the goods are back with us or you have
      provided proof of dispatch.</li>
</ol>
<p>
  We also accept returns without prior notice; processing then takes longer because the parcel has
  to be matched manually.
</p>

<h2>Condition of the return</h2>
<p>
  Trying on is expressly fine — that is exactly what the right of return is for. Please send items
  back unworn, unwashed, unperfumed and with all original labels attached. For a loss in value
  caused by handling beyond that we may ask for compensation; we calculate it transparently and
  contact you first.
</p>

<h2>What cannot be returned</h2>
<ul>
  <li>swimwear and earrings without an intact hygiene seal;</li>
  <li>items individually adjusted or made to your specifications;</li>
  <li>items from private sellers, provided the goods match the description.</li>
</ul>

<h2>Exchange</h2>
<p>
  A direct exchange is not possible because most items are one-of-a-kind pieces or single size runs.
  Return the item and order the right size anew — if still available. If you are interested in a
  particular size, write to us; we will tell you whether restocking is to be expected.
</p>

<h2>Item defective or not as described</h2>
<p>
  Then it is not the right of return that applies but liability for defects — with stronger rights
  for you. Contact us with photos; in this case the return is always free, including for private
  sellers and also after the return period has ended, within the statutory limitation period.
</p>

<h2>Not genuine</h2>
<p>
  Should an item prove not to be genuine, we refund the full purchase price including shipping costs
  and bear the return shipment — regardless of which seller offered it. The seller concerned is
  removed from the platform.
</p>

<h2>Premium Outlet</h2>
<p>
  Reduced prices change nothing about your rights: the same periods as above apply to Vault
  purchases. Only the exception for private sellers remains.
</p>

<p class="doc__related">
  The legal text with the model withdrawal form:
  <a href="<?= h(vr_url('legal/widerruf.php')) ?>"><?= te('legal_withdrawal') ?></a>.
</p>
