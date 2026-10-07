<?php
/**
 * Conditions vendeur — français (traduction de courtoisie ; le texte allemand fait foi).
 * Les taux de commission viennent de la configuration via legal/verkaeufer.php, afin que
 * le texte du contrat et le taux facturé ne puissent jamais diverger.
 */
?>
<nav class="doc__toc">
  <a href="#v1">1. Objet</a>
  <a href="#v2">2. Compte vendeur et inscription</a>
  <a href="#v3">3. Professionnel ou particulier</a>
  <a href="#v4">4. Annonces et contrôle</a>
  <a href="#v5">5. Marchandises interdites</a>
  <a href="#v6">6. Commission</a>
  <a href="#v7">7. Paiement et versement</a>
  <a href="#v8">8. Expédition et retours</a>
  <a href="#v9">9. Premium Outlet</a>
  <a href="#v10">10. Obligations issues du Digital Services Act</a>
  <a href="#v11">11. Droits sur les contenus</a>
  <a href="#v12">12. Suspension et résiliation</a>
  <a href="#v13">13. Responsabilité et garantie</a>
  <a href="#v14">14. Dispositions finales</a>
</nav>

<h2 id="v1">1. Objet</h2>
<p>
  1.1 Les présentes conditions régissent la relation entre <?= h($co) ?>, en tant qu'exploitant de la
  place de marché <?= h($brand) ?>, et les personnes qui proposent des marchandises via la plateforme
  (« Vendeurs »).
</p>
<p>
  1.2 L'Exploitant met à disposition l'espace de vente, le traitement des paiements via Stripe et la
  gestion des commandes. Le contrat de vente portant sur les marchandises proposées est conclu
  exclusivement entre le Vendeur et l'acheteur ; l'Exploitant n'y est pas partie.
</p>
<p>
  1.3 L'Exploitant est habilité à recevoir les paiements des acheteurs au nom du Vendeur avec effet
  libératoire, ainsi qu'à recevoir et transmettre pour le compte du Vendeur les déclarations des
  acheteurs relatives au contrat de vente (notamment rétractation et réclamations pour défaut).
</p>

<h2 id="v2">2. Compte vendeur et inscription</h2>
<p>
  2.1 L'inscription se fait en ligne. Les informations doivent être exactes, complètes et à jour.
  Toute modification — notamment de raison sociale, d'adresse, de numéro fiscal ou de type de
  vendeur — doit être mise à jour sans délai.
</p>
<p>
  2.2 Les identifiants de connexion doivent rester secrets. Le Vendeur répond des actes effectués via
  son compte dans la mesure de sa faute.
</p>
<p>
  2.3 Avant l'activation de la première annonce, la vérification auprès de Stripe (Stripe Connect)
  doit être achevée. Sans vérification achevée, aucun versement n'est possible ; les annonces restent
  alors hors ligne.
</p>

<h2 id="v3">3. Professionnel ou particulier</h2>
<p>
  3.1 Lors de l'inscription, le Vendeur indique s'il vend en tant que professionnel (« Revendeur »)
  ou en tant que particulier (« Vendeur particulier »). Cette information est affichée aux acheteurs
  sur la page produit et lors du paiement, et détermine les droits des consommateurs applicables.
</p>
<p>
  3.2 Quiconque vend de manière systématique, répétée et dans un but lucratif agit à titre
  professionnel — indépendamment de sa propre appréciation. La qualification correcte et toutes les
  obligations fiscales, commerciales et réglementaires incombent au Vendeur.
</p>
<p>
  3.3 L'Exploitant est habilité, après avis préalable, à requalifier un compte en « Revendeur » ou à
  le suspendre si l'activité de vente réelle est de nature professionnelle. Sont notamment pris en
  compte le nombre d'annonces, le chiffre d'affaires et la régularité.
</p>
<p>
  3.4 Les Revendeurs sont tenus d'accorder aux consommateurs le droit de rétractation légal, d'établir
  des factures en bonne et due forme et d'assumer la garantie légale de conformité.
</p>

<h2 id="v4">4. Annonces et contrôle</h2>
<p>
  4.1 Les annonces doivent être exactes, complètes et à jour : marque, nom du modèle, taille, état,
  prix TVA comprise et au moins une photo personnelle, non retouchée, de l'article réellement en
  stock.
</p>
<p>
  4.2 Pour les articles d'occasion, les traces d'usure doivent être décrites. Les informations
  manquantes ou enjolivées sont à la charge du Vendeur.
</p>
<p>
  4.3 Chaque annonce nouvelle ou modifiée est contrôlée avant activation. Le contrôle porte sur la
  plausibilité, la qualité des images et le prix ; l'Exploitant peut demander des justificatifs de
  provenance (preuves d'achat). Il n'existe aucun droit à l'activation.
</p>
<p>
  4.4 Le Vendeur conserve les preuves d'achat au moins pendant la durée de l'annonce plus deux ans et
  les présente sur demande dans un délai de cinq jours ouvrés.
</p>
<p>
  4.5 Le Vendeur garantit la disponibilité des marchandises proposées. Des non-livraisons répétées
  après une vente autorisent l'Exploitant à suspendre le compte.
</p>

<h2 id="v5">5. Marchandises interdites</h2>
<p>Ne peuvent notamment pas être proposés :</p>
<ul>
  <li>les contrefaçons, répliques, « dupes » et articles dont les marquages ont été retirés ou
      modifiés ;</li>
  <li>les articles sans provenance traçable ou d'origine illicite ;</li>
  <li>les articles mis sur le marché pour la première fois hors de l'EEE, sauf accord du titulaire de
      la marque pour la revente dans l'EEE ;</li>
  <li>les échantillons non destinés à la vente (« not for resale »), les articles réservés au
      personnel soumis à une interdiction de revente ;</li>
  <li>les articles contrevenant à la réglementation sur la sécurité des produits, l'étiquetage ou
      l'étiquetage textile ;</li>
  <li>les fourrures et cuirs exotiques sans la documentation requise (CITES).</li>
</ul>
<p>
  Toute infraction entraîne le retrait immédiat de l'annonce et, en règle générale, la résiliation du
  compte vendeur.
</p>

<h2 id="v6">6. Commission</h2>
<p>6.1 Une commission est prélevée sur chaque vente réalisée via la plateforme :</p>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Type de vendeur</th><th>Commission</th><th>Premium Outlet (Vault)</th></tr></thead>
  <tbody>
    <tr><td>Revendeur</td><td><?= h($feeB) ?> % + <?= h($fixed) ?> par article vendu</td><td><?= h($feeV) ?> %</td></tr>
    <tr><td>Vendeur particulier</td><td><?= h($feeP) ?> % + <?= h($fixed) ?> par article vendu</td><td><?= h($feeV) ?> %</td></tr>
  </tbody>
</table></div>
<p>
  6.2 L'assiette est le prix de vente brut des articles du Vendeur. Les frais d'expédition n'entrent
  pas dans l'assiette et restent acquis à l'Exploitant, qui supporte les frais d'expédition vis-à-vis
  du transporteur.
</p>
<p>
  6.3 Il n'y a ni frais d'insertion, ni frais de mise en ligne, ni frais mensuels.
</p>
<p>
  6.4 La commission est retenue automatiquement lors du paiement par l'acheteur. En cas d'annulation
  complète (rétractation, résolution, non-livraison), la commission est remboursée, à l'exception du
  montant fixe prévu à l'article 6.1 lorsque l'annulation est imputable au Vendeur.
</p>
<p>
  6.5 Toute modification de la commission est annoncée par e-mail au moins 30 jours à l'avance. Si le
  Vendeur ne s'y oppose pas avant son entrée en vigueur, la nouvelle commission est réputée acceptée ;
  le droit de résiliation prévu à l'article 12 demeure.
</p>

<h2 id="v7">7. Paiement et versement</h2>
<p>
  7.1 Le traitement des paiements est assuré via Stripe. À cet effet, le Vendeur conclut son propre
  contrat avec Stripe (Stripe Connected Account Agreement) et en accepte les conditions.
</p>
<p>
  7.2 Selon la composition de la commande, le paiement est soit effectué directement sur le compte du
  Vendeur (paiement avec transfert), soit d'abord encaissé sur le compte de la plateforme puis
  transféré au Vendeur par virement séparé. Dans les deux cas, le Vendeur reçoit le prix de vente brut
  de ses articles, déduction faite de la commission.
</p>
<p>
  7.3 Le rythme des versements sur le compte bancaire suit les règles de Stripe. L'Exploitant ne
  conserve pas de fonds clients et ne doit aucun intérêt.
</p>
<p>
  7.4 Si la vérification Stripe n'est pas encore achevée, la part du Vendeur reste sur le compte de la
  plateforme jusqu'à son achèvement. Si la vérification n'est pas achevée dans les 180 jours,
  l'Exploitant peut annuler les commandes concernées et rembourser les acheteurs.
</p>
<p>
  7.5 L'Exploitant peut retenir ou compenser des versements dans la mesure où il détient des créances
  fondées contre le Vendeur — notamment au titre de remboursements aux acheteurs, de rejets de débit
  (chargebacks) ou de violations de l'article 5. La retenue est motivée et limitée au montant des
  créances.
</p>

<h2 id="v8">8. Expédition et retours</h2>
<p>
  8.1 Le Vendeur expédie dans les deux jours ouvrés suivant la réception du paiement, avec assurance et
  suivi, et saisit les données d'expédition sans délai.
</p>
<p>
  8.2 Le Vendeur accepte les retours à l'adresse qu'il a indiquée. Les Revendeurs remboursent les
  rétractations dans le délai ; à défaut, l'Exploitant est habilité à rembourser l'acheteur et à
  compenser le montant avec les versements du Vendeur.
</p>
<p>
  8.3 Il n'existe pas de droit de rétractation légal à l'égard des vendeurs particuliers. Toutefois,
  si l'article s'écarte substantiellement de la description ou n'est pas authentique, le Vendeur est
  tenu de le reprendre et de rembourser.
</p>

<h2 id="v9">9. Premium Outlet</h2>
<p>
  9.1 Le Vendeur peut mettre des articles à disposition du Vault. Le prix d'ouverture, le prix
  plancher et le calendrier des paliers sont convenus avant l'ouverture et ne sont plus modifiés
  ensuite.
</p>
<p>
  9.2 Le Vendeur reconnaît que la vente peut se conclure à tout prix compris entre le prix
  d'ouverture et le prix plancher, et qu'un retrait de la participation n'est pas possible après
  l'ouverture du lot, tant que le lot est en cours.
</p>
<p>
  9.3 Le prix plancher n'est jamais franchi à la baisse. Si un lot n'est pas vendu à l'échéance, il est
  clôturé et peut être proposé à nouveau par la voie habituelle.
</p>

<h2 id="v10">10. Obligations issues du Digital Services Act</h2>
<p>
  10.1 Les Vendeurs professionnels fournissent les informations requises par l'art. 30 du DSA : nom,
  adresse, numéro de téléphone, adresse e-mail, numéro d'immatriculation au registre du commerce ou
  identifiant comparable et numéro de TVA le cas échéant. L'Exploitant vérifie ces informations par
  des moyens raisonnables et peut mettre l'annonce hors ligne jusqu'à clarification.
</p>
<p>
  10.2 Le Vendeur garantit que ses annonces respectent la réglementation sur la sécurité des produits
  et l'étiquetage et qu'il détient les autorisations nécessaires.
</p>
<p>
  10.3 Les signalements de contenus illicites sont traités conformément à l'art. 16 du DSA. Les
  Vendeurs concernés sont informés des retraits avec motifs et peuvent les contester
  (<a href="<?= h(vr_url('legal/streitbeilegung.php')) ?>"><?= te('legal_disputes') ?></a>).
</p>

<h2 id="v11">11. Droits sur les contenus</h2>
<p>
  11.1 Le Vendeur concède à l'Exploitant un droit simple, sans limitation territoriale et gratuit
  d'utiliser, de modifier (recadrage, correction colorimétrique, redimensionnement) et de reproduire
  les textes et images publiés, aux fins d'exploitation et de promotion de la plateforme.
</p>
<p>
  11.2 Ce droit d'utilisation subsiste après la fin de l'annonce dans la mesure où il sert à
  documenter les ventes conclues.
</p>
<p>
  11.3 Le Vendeur garantit détenir tous les droits nécessaires sur les contenus publiés et ne porter
  atteinte à aucun droit de tiers.
</p>

<h2 id="v12">12. Suspension et résiliation</h2>
<p>
  12.1 Chaque partie peut résilier la relation vendeur à tout moment, par écrit (forme textuelle),
  moyennant un préavis de 14 jours. Les commandes en cours doivent être intégralement exécutées.
</p>
<p>
  12.2 L'Exploitant peut retirer immédiatement des annonces et suspendre le compte en cas de
  violation de l'article 5, d'informations erronées sur le statut de vendeur, de non-livraisons
  répétées ou de soupçon fondé de contrefaçon. La suspension est motivée.
</p>
<p>
  12.3 Après résiliation, les versements dus sont ordonnés après expiration des délais de retour et de
  rejet de débit, au plus tard 90 jours après la dernière commande.
</p>

<h2 id="v13">13. Responsabilité et garantie</h2>
<p>
  13.1 L'Exploitant répond sans limitation en cas de dol et de négligence grave, d'atteinte à la vie,
  à l'intégrité physique ou à la santé, et lorsqu'une garantie a été donnée. En cas de manquement par
  négligence légère à des obligations contractuelles essentielles, la responsabilité est limitée au
  dommage prévisible et typique du contrat ; elle est exclue pour le surplus.
</p>
<p>
  13.2 Le Vendeur garantit l'Exploitant contre les réclamations de tiers fondées sur une violation des
  présentes conditions — notamment en droit des marques, droit d'auteur ou droit de la concurrence et
  en matière de protection des consommateurs. Cette garantie inclut les frais raisonnables de défense
  juridique.
</p>
<p>
  13.3 Aucune garantie de chiffre d'affaires ou de succès n'est donnée. La visibilité, le placement et
  le tri des annonces sont déterminés par l'Exploitant.
</p>

<h2 id="v14">14. Dispositions finales</h2>
<p>
  14.1 Le droit allemand s'applique. Si le Vendeur est un professionnel, le tribunal compétent est
  celui du siège de l'Exploitant, dans la mesure permise par la loi.
</p>
<p>
  14.2 Toute modification des présentes conditions est communiquée par e-mail au moins 30 jours à
  l'avance. Version en vigueur : <?= h(VR_TERMS_VERSION) ?>.
</p>
<p>
  14.3 Contact pour toute question vendeur :
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-mail]</em>' ?>.
</p>

<div class="doc__box">
  <strong>Note sur cette traduction</strong>
  <p style="margin-top:8px">
    Ce texte français est une traduction de courtoisie des Conditions vendeur allemandes, seules
    juridiquement contraignantes et que vous acceptez lors de l'inscription. En cas de divergence, la
    version allemande prévaut. Il ne constitue pas un conseil juridique.
  </p>
</div>
