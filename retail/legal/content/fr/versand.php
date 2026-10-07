<?php
/**
 * Livraison — français (traduction de courtoisie).
 * $ship et $countries viennent de legal/versand.php ; les libellés sont ici.
 */
$zones = [
    'de'    => 'Allemagne',
    'eu'    => 'Union européenne',
    'ch'    => 'Suisse, Liechtenstein, Norvège, Royaume-Uni',
    'world' => 'Autres destinations',
];
?>
<h2>Frais de port et délais</h2>
<div class="tablewrap" tabindex="0"><table>
  <thead>
    <tr><th>Destination</th><th>Frais de port</th><th>Offerts à partir de</th><th>Délai</th></tr>
  </thead>
  <tbody>
  <?php foreach ($zones as $key => $label):
      $row = (array)($ship[$key] ?? []); ?>
    <tr>
      <td><?= h($label) ?></td>
      <td><?= h(vr_money((int)($row['cents'] ?? 0))) ?></td>
      <td><?= (int)($row['free_over'] ?? 0) > 0 ? h(vr_money((int)$row['free_over'])) : '—' ?></td>
      <td><?= h((string)($row['days'] ?? '')) ?> jours ouvrés</td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<p>
  Tous les montants sont des prix définitifs, TVA comprise. Les frais de port applicables à votre
  commande s'affichent dans le panier dès que vous avez choisi le pays de livraison, et sont
  rappelés au centime près lors de la commande, avant validation.
</p>

<h3>Pays de l'UE desservis</h3>
<p class="doc__list">
  <?= h(implode(' · ', vr_country_options($countries['eu']))) ?>
</p>

<h3>Autres destinations</h3>
<p class="doc__list">
  <?= h(implode(' · ', vr_country_options($countries['world']))) ?>
</p>
<p>
  Si votre pays n'y figure pas, écrivez-nous — beaucoup de choses se règlent au cas par cas.
</p>

<h2>Départ de l'expédition</h2>
<p>
  Le délai court à compter de la validation du paiement. Pour les paiements par carte, Apple Pay et
  Google Pay, elle est en général immédiate ; pour le prélèvement SEPA et Klarna, un à trois jours
  ouvrés bancaires peuvent s'ajouter. Les commandes validées avant 13 h partent en général le jour
  ouvré même.
</p>

<h2>Plusieurs vendeurs, plusieurs colis</h2>
<p>
  <?= h((string)vr_config('brand')) ?> est une place de marché. Si votre commande contient des
  articles de différents vendeurs, chacun expédie séparément. Vous recevez alors plusieurs colis et
  plusieurs liens de suivi — mais ne payez qu'une fois, et les frais de port ne sont facturés qu'une
  fois.
</p>

<h2>Suivi de colis</h2>
<p>
  Chaque envoi est assuré et expédié avec un numéro de suivi. Vous recevez le lien par e-mail dès la
  remise du colis au transporteur. Vous pouvez aussi consulter l'état actuel à tout moment sous
  <a href="<?= h(vr_url('order.php')) ?>"><?= te('order_status') ?></a> avec votre numéro de commande
  et votre adresse e-mail.
</p>

<h2>Consignes automatiques et adresse de livraison différente</h2>
<p>
  En Allemagne, nous livrons aux Packstations DHL ; indiquez la Packstation et votre numéro postal
  comme adresse de livraison. Pour les envois internationaux, une adresse postale complète est
  nécessaire.
</p>

<h2>Douane et taxes à l'importation</h2>
<p>
  Au sein de l'UE, aucun droit de douane ni taxe à l'importation ne s'applique. Pour les livraisons
  vers la Suisse, la Norvège, le Royaume-Uni ou hors d'Europe, la TVA à l'importation, des droits de
  douane et des frais de traitement du transporteur peuvent s'appliquer. Ils sont à la charge du
  destinataire et ne font pas partie du prix payé chez nous.
</p>

<h2>Colis non distribués</h2>
<p>
  Si un colis est renvoyé au vendeur comme non distribuable, nous convenons avec vous d'une nouvelle
  expédition. De nouveaux frais de port s'appliquent si la non-distribution résulte d'une adresse de
  livraison incomplète ou erronée.
</p>

<h2>Dommages de transport</h2>
<p>
  Si un colis arrive visiblement endommagé, acceptez-le, photographiez les dommages et contactez-nous
  sous 14 jours. Nous réglons ces cas indépendamment du droit de rétractation — y compris pour les
  vendeurs particuliers. Vos droits légaux n'en sont pas restreints.
</p>

<p class="doc__related">
  Voir aussi <a href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('legal_returns') ?></a> et
  <a href="<?= h(vr_url('legal/widerruf.php')) ?>"><?= te('legal_withdrawal') ?></a>.
</p>
