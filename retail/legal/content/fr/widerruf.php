<?php
/**
 * Information sur la rétractation + formulaire type — français (traduction de
 * courtoisie). Suit le modèle officiel de la directive 2011/83/UE (annexe I),
 * comme l'original allemand suit le BGB. Variables : legal/widerruf.php.
 */
?>
<div class="notice">
  <strong>Important pour les achats sur la place de marché :</strong> le droit légal de rétractation
  n'existe que pour les contrats entre un consommateur et un <em>professionnel</em>. Pour les
  articles portant la mention « <?= te('seller_private') ?> » sur la page produit, il n'existe donc
  <strong>aucun</strong> droit de rétractation. Cette mention est visible avant le paiement et vous
  devez la confirmer expressément lors de la commande.
</div>

<h2>Droit de rétractation</h2>
<p>
  Vous avez le droit de vous rétracter du présent contrat sans donner de motif dans un délai de
  <?= (int)$wd ?> jours.
</p>
<p>
  Le délai de rétractation expire <?= (int)$wd ?> jours après le jour où vous-même, ou un tiers autre
  que le transporteur et désigné par vous, prend physiquement possession du bien.
</p>
<p>
  Dans le cas d'un contrat portant sur plusieurs biens commandés au moyen d'une seule commande et
  livrés séparément, le délai de rétractation expire <?= (int)$wd ?> jours après le jour où
  vous-même, ou un tiers autre que le transporteur et désigné par vous, prend physiquement
  possession du dernier bien.
</p>
<p>
  Pour exercer le droit de rétractation, vous devez nous notifier
</p>
<div class="doc__box">
  <?= h($co) ?><br>
  <?= h($addrShown) ?><br>
  <?= $email !== '' ? 'E-mail : <a href="mailto:' . h($email) . '">' . h($email) . '</a>' : 'E-mail : [adresse e-mail]' ?>
</div>
<p>
  votre décision de rétractation du présent contrat au moyen d'une déclaration dénuée d'ambiguïté
  (par exemple, lettre envoyée par la poste ou courrier électronique). Vous pouvez utiliser le
  modèle de formulaire de rétractation ci-joint, mais ce n'est pas obligatoire.
</p>
<p>
  Si la rétractation concerne un article d'un vendeur tiers professionnel, une déclaration qui nous
  est adressée suffit ; nous sommes habilités à la recevoir et la transmettons sans délai.
</p>
<p>
  Pour que le délai de rétractation soit respecté, il suffit que vous transmettiez votre
  communication relative à l'exercice du droit de rétractation avant l'expiration du délai de
  rétractation.
</p>

<h2>Effets de la rétractation</h2>
<p>
  En cas de rétractation de votre part du présent contrat, nous vous rembourserons tous les paiements
  reçus de vous, y compris les frais de livraison (à l'exception des frais supplémentaires découlant
  du fait que vous avez choisi, le cas échéant, un mode de livraison autre que le mode moins coûteux
  de livraison standard proposé par nous), sans retard excessif et, en tout état de cause, au plus
  tard quatorze jours à compter du jour où nous sommes informés de votre décision de rétractation du
  présent contrat. Nous procéderons au remboursement en utilisant le même moyen de paiement que
  celui que vous aurez utilisé pour la transaction initiale, sauf si vous convenez expressément d'un
  moyen différent ; en tout état de cause, ce remboursement n'occasionnera pas de frais pour vous.
</p>
<p>
  Nous pouvons différer le remboursement jusqu'à ce que nous ayons reçu le bien ou jusqu'à ce que
  vous ayez fourni une preuve d'expédition du bien, la date retenue étant celle du premier de ces
  faits.
</p>
<p>
  Vous devrez renvoyer ou rendre le bien, à nous-mêmes ou au vendeur indiqué sur l'étiquette de
  retour, sans retard excessif et, en tout état de cause, au plus tard quatorze jours après que vous
  nous aurez communiqué votre décision de rétractation du présent contrat. Ce délai est réputé
  respecté si vous renvoyez le bien avant l'expiration du délai de quatorze jours.
</p>
<p>
  Nous prenons en charge les frais de renvoi du bien si le retour est effectué depuis l'Allemagne.
  Pour les retours depuis d'autres pays, vous devrez prendre en charge les frais directs de renvoi
  du bien.
</p>
<p>
  Votre responsabilité n'est engagée qu'à l'égard de la dépréciation du bien résultant de
  manipulations autres que celles nécessaires pour établir la nature, les caractéristiques et le bon
  fonctionnement de ce bien. Essayer un vêtement est autorisé ; le porter, le laver ou en retirer les
  étiquettes va au-delà.
</p>

<h2>Exclusion du droit de rétractation</h2>
<p>Le droit de rétractation n'existe pas ou s'éteint pour les contrats suivants :</p>
<ul>
  <li>contrats avec des vendeurs qui ne sont pas des professionnels (vendeurs particuliers) ;</li>
  <li>contrats de fourniture de biens confectionnés selon les spécifications du consommateur ou
      nettement personnalisés ;</li>
  <li>contrats de fourniture de biens scellés ne pouvant être renvoyés pour des raisons de
      protection de la santé ou d'hygiène et qui ont été descellés après la livraison (par exemple
      boucles d'oreilles, maillots de bain sans protection hygiénique) ;</li>
  <li>contrats dans lesquels vous agissez en tant que professionnel (B2B).</li>
</ul>

<h2>Modèle de formulaire de rétractation</h2>
<div class="doc__box">
  <p><em>(Veuillez compléter et renvoyer le présent formulaire uniquement si vous souhaitez vous
  rétracter du contrat.)</em></p>
  <p>
    À l'attention de<br>
    <?= h($co) ?><br>
    <?= h($addrShown) ?><br>
    <?= $email !== '' ? h($email) : '[adresse e-mail]' ?>
  </p>
  <p>
    Je/nous (*) vous notifie/notifions (*) par la présente ma/notre (*) rétractation du contrat
    portant sur la vente des biens (*) ci-dessous :
  </p>
  <p>
    ______________________________________________<br>
    ______________________________________________
  </p>
  <p>
    Numéro de commande : ____________________________<br>
    Commandé le (*) / reçu le (*) : ____________________________<br>
    Nom du (des) consommateur(s) : ____________________________<br>
    Adresse du (des) consommateur(s) : ____________________________<br>
    ____________________________
  </p>
  <p>
    Signature du (des) consommateur(s) <em>(uniquement en cas de notification du présent formulaire
    sur papier)</em> : ____________________________<br>
    Date : ____________________________
  </p>
  <p><em>(*) Rayez la mention inutile.</em></p>
</div>

<p class="doc__related">
  Au-delà du droit légal de rétractation, nous accordons un droit de retour volontaire pour nos
  propres articles — détails sous
  <a href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('legal_returns') ?></a>.
</p>
