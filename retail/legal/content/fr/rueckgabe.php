<?php
/**
 * Retours — français (traduction de courtoisie). Variables : legal/rueckgabe.php.
 */
?>
<h2>En un coup d'œil</h2>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Vendeur</th><th>Délai</th><th>Frais de retour</th></tr></thead>
  <tbody>
    <tr><td>Stock <?= h((string)vr_config('brand')) ?></td>
        <td><?= (int)$days ?> jours (<?= (int)$wd ?> légaux + extension volontaire)</td>
        <td>gratuits depuis l'Allemagne pendant le délai légal</td></tr>
    <tr><td>Revendeur</td>
        <td><?= (int)$wd ?> jours de rétractation légale ; beaucoup de revendeurs accordent plus</td>
        <td>gratuits depuis l'Allemagne pendant le délai légal</td></tr>
    <tr><td>Vendeur particulier</td>
        <td>pas de droit légal de rétractation</td>
        <td>reprise uniquement en cas d'écart avec la description</td></tr>
  </tbody>
</table></div>

<h2>Comment retourner</h2>
<ol>
  <li>Écrivez à
      <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-mail]</em>' ?>
      en indiquant votre numéro de commande et les articles à retourner.</li>
  <li>Vous recevez sous un jour ouvré une étiquette de retour et l'adresse de retour du vendeur
      concerné.</li>
  <li>Emballez l'article, de préférence dans sa boîte d'origine, joignez le bon de livraison et
      déposez le colis.</li>
  <li>Après réception et contrôle, nous remboursons sur le même moyen de paiement — au plus tard
      14 jours après réception de votre déclaration de rétractation, dès que l'article est chez
      nous ou que vous avez justifié de son expédition.</li>
</ol>
<p>
  Nous acceptons aussi les retours sans annonce préalable ; le traitement est alors plus long, car
  le rapprochement se fait manuellement.
</p>

<h2>État du retour</h2>
<p>
  Essayer est expressément permis — c'est précisément à cela que sert le droit de retour. Merci de
  renvoyer les articles non portés, non lavés, non parfumés et munis de toutes leurs étiquettes
  d'origine. Pour une dépréciation due à une manipulation allant au-delà, nous pouvons demander une
  compensation ; nous la calculons de façon transparente et vous contactons au préalable.
</p>

<h2>Ce qui ne peut pas être repris</h2>
<ul>
  <li>maillots de bain et boucles d'oreilles sans protection hygiénique intacte ;</li>
  <li>articles ajustés individuellement ou réalisés selon vos indications ;</li>
  <li>articles de vendeurs particuliers, dès lors qu'ils sont conformes à la description.</li>
</ul>

<h2>Échange</h2>
<p>
  Un échange direct n'est pas possible, la plupart des articles étant des pièces uniques ou des
  tailles isolées. Retournez l'article et commandez la bonne taille — si elle est encore disponible.
  Si une taille précise vous intéresse, écrivez-nous ; nous vous dirons si un réassort est à prévoir.
</p>

<h2>Article défectueux ou non conforme</h2>
<p>
  Dans ce cas, ce n'est pas le droit de retour qui s'applique mais la garantie légale — avec des
  droits plus étendus pour vous. Contactez-nous avec des photos ; le retour est alors toujours
  gratuit, y compris pour les vendeurs particuliers et même après l'expiration du délai de retour,
  dans la limite de la prescription légale.
</p>

<h2>Article non authentique</h2>
<p>
  S'il s'avérait qu'un article n'est pas authentique, nous remboursons l'intégralité du prix d'achat,
  frais de port compris, et prenons en charge le retour — quel que soit le vendeur qui l'a proposé.
  Le vendeur concerné est retiré de la plateforme.
</p>

<h2>Premium Outlet</h2>
<p>
  Les prix réduits ne changent rien à vos droits : les mêmes délais que ci-dessus s'appliquent aux
  achats du Vault. Seule l'exception relative aux vendeurs particuliers demeure.
</p>

<p class="doc__related">
  Le texte légal avec le formulaire type de rétractation :
  <a href="<?= h(vr_url('legal/widerruf.php')) ?>"><?= te('legal_withdrawal') ?></a>.
</p>
