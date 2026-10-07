<?php
/**
 * Moyens de paiement — français (traduction de courtoisie). $mode : legal/zahlung.php.
 */
?>
<h2>Comment se déroule le paiement</h2>
<p>
  Vous ajoutez des articles au panier, choisissez le pays de livraison lors de la commande et
  confirmez les conditions générales et l'information sur la rétractation. Pour payer, nous vous
  redirigeons vers notre prestataire de paiement Stripe. Vous y saisissez vos données de paiement et
  finalisez le règlement ; vous revenez ensuite à la confirmation de commande.
</p>
<p>
  <strong>Vos données de carte n'atteignent jamais nos serveurs.</strong> Stripe nous transmet
  uniquement l'issue du paiement, le montant, le moyen de paiement sous forme générale et les
  informations nécessaires à l'expédition et à la facturation.
</p>

<h2>Moyens de paiement disponibles</h2>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Moyen de paiement</th><th>Débit</th><th>Remarque</th></tr></thead>
  <tbody>
    <tr><td>Visa, Mastercard, American Express</td><td>immédiat</td>
        <td>une confirmation 3-D Secure de votre banque peut être requise (SCA)</td></tr>
    <tr><td>Apple Pay</td><td>immédiat</td><td>sur appareils Apple dans Safari</td></tr>
    <tr><td>Google Pay</td><td>immédiat</td><td>dans Chrome et sur Android</td></tr>
    <tr><td>Klarna</td><td>selon l'option choisie</td>
        <td>paiement sur facture ou en plusieurs fois ; le contrat de financement est conclu avec Klarna</td></tr>
    <tr><td>Prélèvement SEPA</td><td>1 à 3 jours ouvrés bancaires</td>
        <td>expédition après validation du paiement</td></tr>
  </tbody>
</table></div>
<p class="doc__related">
  Les moyens de paiement effectivement affichés dépendent du pays de livraison, du montant et de
  l'appareil — Stripe ne propose que ce qui est utilisable pour votre commande. Aucun des moyens de
  paiement proposés n'entraîne de frais supplémentaires pour vous.
</p>

<?php if ($mode !== 'live'): ?>
  <div class="notice notice--demo">
    <strong>Cette installation ne fonctionne pas actuellement en mode réel.</strong>
    Aucun paiement réel ne peut être effectué
    (<?= $mode === 'test' ? 'mode test Stripe actif' : 'aucune clé Stripe configurée' ?>).
  </div>
<?php endif; ?>

<h2>Exigibilité</h2>
<p>
  Le prix d'achat est exigible à la conclusion du contrat. Pour les moyens de paiement à règlement
  différé, nous réservons vos articles et expédions après validation du paiement.
</p>

<h2>Devise et taxes</h2>
<p>
  Tous les prix sont indiqués en euros, TVA légale comprise au taux de
  <?= h((int)vr_config('vat_rate_bps', 1900) / 100) ?> %, dès lors que le vendeur concerné y est
  assujetti. Aucune TVA n'est mentionnée pour les articles de vendeurs particuliers. Si votre banque
  règle dans une autre devise, elle peut facturer des frais de conversion — sur lesquels nous n'avons
  aucune influence.
</p>

<h2>Facture</h2>
<p>
  Vous recevez la facture avec la marchandise ou par e-mail. Pour les articles de vendeurs tiers
  professionnels, c'est le revendeur concerné qui établit la facture ; pour nos propres articles,
  <?= h((string)(vr_config('company')['legal_name'] ?? '')) ?>. Les vendeurs particuliers
  n'établissent pas de facture avec mention de TVA.
</p>

<h2>Remboursements</h2>
<p>
  Les remboursements sont toujours effectués sur le moyen de paiement utilisé. Avec Klarna, le
  remboursement passe par votre compte Klarna ; avec SEPA, sur le compte débité. Nous lançons le
  traitement après réception et contrôle du retour ; selon le moyen de paiement, quelques jours
  ouvrés peuvent s'écouler avant que le crédit n'apparaisse auprès de votre banque.
</p>

<h2>Paiement refusé</h2>
<p>
  Si un paiement est refusé, aucun contrat n'est conclu et rien n'est débité. Votre panier est
  conservé afin que vous puissiez réessayer ou choisir un autre moyen de paiement. La cause la plus
  fréquente est une confirmation 3-D Secure non finalisée.
</p>

<h2>Sécurité du paiement</h2>
<p>
  La connexion est chiffrée de bout en bout (TLS). Stripe est certifié prestataire de paiement
  PCI DSS niveau 1 et agréé en Europe en tant qu'établissement de paiement (Stripe Payments Europe,
  Limited, Dublin). Pour prévenir la fraude, Stripe contrôle les transactions de manière
  automatisée ; nous ne conservons aucun numéro de carte.
</p>
<p class="doc__related">
  Détails sur le traitement des données : <a href="<?= h(vr_url('legal/datenschutz.php')) ?>"><?= te('legal_privacy') ?></a>.
</p>
