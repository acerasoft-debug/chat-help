<?php
/**
 * Conditions générales de vente — français (traduction de courtoisie ; le texte
 * allemand fait foi). Variables : legal/agb.php. La numérotation suit l'original
 * paragraphe par paragraphe pour que les renvois (« clause 10.3 ») restent exacts.
 */
?>
<nav class="doc__toc">
  <a href="#s1">1. Champ d'application et parties au contrat</a>
  <a href="#s2">2. Rôle de la plateforme</a>
  <a href="#s3">3. Conclusion du contrat</a>
  <a href="#s4">4. Prix et frais de port</a>
  <a href="#s5">5. Paiement</a>
  <a href="#s6">6. Livraison</a>
  <a href="#s7">7. Réserve de propriété</a>
  <a href="#s8">8. Rétractation et retour volontaire</a>
  <a href="#s9">9. Garantie légale de conformité</a>
  <a href="#s10">10. Achats auprès de vendeurs particuliers</a>
  <a href="#s11">11. Premium Outlet (Vault)</a>
  <a href="#s12">12. Authenticité et provenance</a>
  <a href="#s13">13. Responsabilité</a>
  <a href="#s14">14. Bons de réduction</a>
  <a href="#s15">15. Protection des données</a>
  <a href="#s16">16. Dispositions finales</a>
</nav>

<h2 id="s1">1. Champ d'application et parties au contrat</h2>
<p>
  1.1 Les présentes conditions générales de vente s'appliquent à toutes les commandes passées via
  <?= h($brand) ?> (<a href="<?= h(vr_origin()) ?>"><?= h(vr_origin()) ?></a>).
  L'exploitant de la plateforme est <?= h($co) ?> (ci-après « l'Exploitant », « nous »).
</p>
<p>
  1.2 Trois types d'offres sont proposés sur la plateforme, chacun signalé sur la page produit :
</p>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Mention</th><th>Vendeur</th><th>Votre cocontractant pour la vente</th></tr></thead>
  <tbody>
    <tr><td>Stock <?= h($brand) ?></td><td>l'Exploitant lui-même</td><td><?= h($co) ?></td></tr>
    <tr><td>Revendeur</td><td>vendeur tiers professionnel</td><td>le revendeur concerné</td></tr>
    <tr><td>Vendeur particulier</td><td>personne privée</td><td>la personne privée concernée</td></tr>
  </tbody>
</table></div>
<p>
  1.3 Pour les offres de vendeurs tiers, le contrat de vente est conclu exclusivement entre vous et
  le vendeur concerné. L'Exploitant ne devient pas partie au contrat de vente. Pour l'utilisation de
  la plateforme elle-même — traitement du paiement, suivi des commandes, intermédiation — les
  présentes conditions s'appliquent entre vous et l'Exploitant.
</p>
<p>
  1.4 Est consommateur toute personne physique qui conclut un acte juridique à des fins qui
  n'entrent pas, pour l'essentiel, dans le cadre de son activité professionnelle ou commerciale
  (§ 13 BGB, Code civil allemand). Les conditions divergentes du client ne font pas partie du
  contrat, sauf acceptation expresse de notre part par écrit.
</p>

<h2 id="s2">2. Rôle de la plateforme</h2>
<p>
  2.1 L'Exploitant met à disposition l'infrastructure technique, vérifie la plausibilité et les
  justificatifs de provenance des offres de vendeurs tiers avant leur mise en ligne, traite le
  paiement via le prestataire de paiement Stripe et reverse au vendeur sa part après déduction de la
  commission.
</p>
<p>
  2.2 L'Exploitant est mandaté par les vendeurs tiers pour encaisser les paiements de l'acheteur
  avec effet libératoire. Votre obligation de paiement envers le vendeur est remplie dès que le
  paiement via la plateforme a abouti.
</p>
<p>
  2.3 Les déclarations relatives au contrat de vente — notamment rétractation, signalement d'un
  défaut et résolution — peuvent valablement être adressées à
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '[e-mail]' ?>.
  Nous les transmettons sans délai au vendeur concerné et vous accompagnons dans le règlement.
</p>

<h2 id="s3">3. Conclusion du contrat</h2>
<p>
  3.1 La présentation des produits sur la plateforme ne constitue pas une offre juridiquement
  contraignante mais une invitation à commander.
</p>
<p>
  3.2 En cliquant sur le bouton de commande (« <?= te('checkout_go') ?> » puis paiement chez Stripe),
  vous émettez une offre ferme d'achat des articles de votre panier. Vous pouvez auparavant vérifier
  et corriger vos saisies sur la page de paiement.
</p>
<p>
  3.3 Nous accusons réception de votre commande sans délai par e-mail. Cet accusé de réception ne
  vaut pas encore acceptation. Le contrat de vente est conclu lorsque nous ou le vendeur déclarons
  l'acceptation ou expédions la marchandise — au plus tard avec la confirmation de commande, si
  celle-ci déclare expressément l'acceptation.
</p>
<p>
  3.4 Si le contrat n'est pas conclu, par exemple parce que l'article n'est plus disponible après la
  commande, nous vous en informons sans délai et remboursons intégralement les paiements déjà
  effectués.
</p>
<p>
  3.5 Le texte du contrat est enregistré et vous est transmis avec la confirmation de commande sous
  forme textuelle (e-mail), y compris les présentes conditions et l'information sur la rétractation.
</p>

<h2 id="s4">4. Prix et frais de port</h2>
<p>
  4.1 Tous les prix indiqués sont des prix définitifs en euros, TVA légale comprise au taux actuel de
  <?= h($vat) ?> %, dès lors que le vendeur concerné est assujetti à la TVA. Pour les vendeurs
  particuliers, aucune TVA n'est mentionnée (§ 19 UStG, loi allemande sur la TVA, ou vente par un
  non-professionnel).
</p>
<p>
  4.2 Des frais de port s'ajoutent au prix des articles. Ils sont indiqués séparément et au centime
  près sur la page de paiement avant la validation de la commande. Détails sous
  <a href="<?= h(vr_url('legal/versand.php')) ?>"><?= te('legal_shipping') ?></a>.
</p>
<p>
  4.3 Si votre commande contient des articles de plusieurs vendeurs, les frais de port ne sont
  facturés qu'une seule fois ; les colis peuvent vous parvenir séparément.
</p>
<p>
  4.4 Pour les livraisons hors UE, des droits de douane, la TVA à l'importation et des frais de
  traitement peuvent s'ajouter ; ils sont à la charge du destinataire.
</p>

<h2 id="s5">5. Paiement</h2>
<p>
  5.1 Le paiement s'effectue via le prestataire Stripe (Stripe Payments Europe, Limited, Dublin,
  Irlande). Les moyens de paiement disponibles sont affichés lors de la commande ; vous en trouverez
  un aperçu sous <a href="<?= h(vr_url('legal/zahlung.php')) ?>"><?= te('legal_payment') ?></a>.
</p>
<p>
  5.2 Le prix d'achat est exigible à la conclusion du contrat. Pour les moyens de paiement à
  règlement différé (prélèvement SEPA, Klarna, par exemple), nous expédions après validation du
  paiement par le prestataire.
</p>
<p>
  5.3 Les données de paiement, en particulier les données de carte, sont traitées exclusivement par
  le prestataire de paiement. L'Exploitant ne reçoit ni ne conserve de données de carte complètes.
</p>
<p>
  5.4 En cas de rejet de paiement qui vous est imputable, nous sommes en droit de vous facturer les
  frais qui en résultent, si vous avez provoqué ce rejet de manière fautive.
</p>

<h2 id="s6">6. Livraison</h2>
<p>
  6.1 La livraison est effectuée à l'adresse de livraison que vous indiquez. Les délais et zones de
  livraison figurent sous <a href="<?= h(vr_url('legal/versand.php')) ?>"><?= te('legal_shipping') ?></a>
  et courent à compter de la validation du paiement.
</p>
<p>
  6.2 Les articles de vendeurs tiers sont expédiés par le vendeur concerné. Vous recevez un suivi
  distinct pour chaque envoi.
</p>
<p>
  6.3 Si, exceptionnellement, un article ne peut être livré alors qu'il était affiché comme
  disponible sur la plateforme, nous vous en informons sans délai et remboursons intégralement le
  montant payé. Il n'existe pas de droit à la livraison ultérieure d'un article comparable, de
  nombreuses pièces étant uniques.
</p>
<p>
  6.4 Pour les consommateurs, le risque de perte et de détérioration fortuites n'est transféré qu'à
  la remise de la marchandise, même si l'expédition est confiée à un transporteur (§ 475 al. 2 BGB).
</p>

<h2 id="s7">7. Réserve de propriété</h2>
<p>
  La marchandise reste la propriété du vendeur concerné jusqu'à son paiement intégral.
</p>

<h2 id="s8">8. Rétractation et retour volontaire</h2>
<p>
  8.1 Les consommateurs disposent d'un droit légal de rétractation de <?= (int)$wd ?> jours pour les
  contrats conclus avec des vendeurs professionnels. L'information complète, avec le formulaire type
  de rétractation, se trouve sous
  <a href="<?= h(vr_url('legal/widerruf.php')) ?>"><?= te('legal_withdrawal') ?></a>.
</p>
<p>
  8.2 Au-delà du droit légal de rétractation, nous accordons pour nos propres articles un droit de
  retour volontaire de <?= (int)$days ?> jours à compter de la réception. Condition : l'article est
  non porté, intact et muni de toutes ses étiquettes d'origine. Le droit de retour volontaire ne
  restreint pas vos droits légaux. Les frais de retour dans le cadre de l'extension volontaire sont
  à la charge de l'acheteur ; détails sous
  <a href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('legal_returns') ?></a>.
</p>
<p>
  8.3 Il n'existe pas de droit légal de rétractation pour les achats auprès de vendeurs particuliers
  (voir clause 10).
</p>

<h2 id="s9">9. Garantie légale de conformité</h2>
<p>
  9.1 Pour les vendeurs professionnels, la garantie légale des §§ 434 et suivants BGB s'applique.
  Pour les consommateurs, le délai de prescription est de deux ans à compter de la réception.
</p>
<p>
  9.2 Pour les articles d'occasion, ce délai peut être ramené à un an à l'égard des consommateurs
  si cela a été convenu expressément et séparément avant la conclusion du contrat. Le cas échéant,
  une mention figure sur la page produit.
</p>
<p>
  9.3 Les traces d'usage mentionnées dans la description de l'article ne constituent pas un défaut.
  Les écarts de rendu des couleurs dus à l'affichage à l'écran ne constituent pas un défaut.
</p>
<p>
  9.4 Merci de nous signaler les dommages de transport sous 14 jours avec photos. Nous traitons ces
  cas indépendamment de la question du droit de rétractation, y compris pour les vendeurs
  particuliers.
</p>

<h2 id="s10">10. Achats auprès de vendeurs particuliers</h2>
<p>
  10.1 Les offres de personnes privées portent la mention « <?= te('seller_private') ?> » sur la
  page produit, dans le panier et lors de la commande. Avant de valider la commande, vous devez
  confirmer expressément les conséquences particulières.
</p>
<p>
  10.2 Le vendeur n'étant pas un professionnel, il n'existe pas de droit légal de rétractation. La
  garantie légale peut être valablement exclue ou limitée par le vendeur particulier ; une telle
  exclusion ne vaut pas en cas de dol ou de déclarations sciemment inexactes.
</p>
<p>
  10.3 Indépendamment de cela : si l'article livré s'écarte substantiellement de la description ou
  n'est pas authentique, nous remboursons l'intégralité du prix d'achat, frais de port compris. Dans
  ces cas, nous retenons ou réclamons la part du vendeur.
</p>
<p>
  10.4 Les vendeurs particuliers qui vendent en réalité de manière planifiée, répétée et dans un but
  lucratif agissent à titre professionnel. Si nous le constatons, nous reclassons ou suspendons le
  compte ; les contrats déjà conclus relèvent alors des droits applicables à l'égard des
  professionnels.
</p>

<h2 id="s11">11. Premium Outlet (Vault)</h2>
<p>
  11.1 Dans l'espace « Premium Outlet » (Vault), chaque pièce est proposée comme un lot individuel
  avec un prix d'ouverture, un prix plancher et un calendrier de baisse publié. Le prix baisse en
  <?= (int)$steps ?> paliers égaux, un palier toutes les <?= (int)$hours ?> heures, jusqu'au prix
  plancher.
</p>
<p>
  11.2 Le calendrier est fixé à l'ouverture du lot et n'est plus modifié ensuite. Le prix ne peut
  pas augmenter. Il est calculé côté serveur à partir du calendrier et est identique pour tous les
  visiteurs ; il n'y a pas de tarification personnalisée.
</p>
<p>
  11.3 Le prix déterminant est celui affiché au moment de l'ajout au panier ; il vous est réservé
  pendant la durée de la commande (20 minutes). À l'expiration de la réservation, le lot est
  remis en vente.
</p>
<p>
  11.4 Chaque lot n'existe qu'une seule fois. Il prend fin avec le premier achat effectif. Il
  n'existe aucun droit à acquérir un lot à un palier ultérieur, plus bas.
</p>
<p>
  11.5 En cas de baisse de prix, nous indiquons, conformément au § 11 PAngV (règlement allemand sur
  l'indication des prix), le prix le plus bas des 30 derniers jours. Le prix du Vault ne faisant que
  baisser, il s'agit du prix en vigueur juste avant le palier actuel. Au premier palier, il n'y a pas
  de baisse de prix ; aucun prix de référence n'y est annoncé.
</p>
<p>
  11.6 Vos droits légaux, en particulier la rétractation et la garantie légale, s'appliquent sans
  changement dans le Vault. La clause 10 reste applicable aux vendeurs particuliers.
</p>
<p>
  11.7 Les membres dont l'inscription par e-mail est confirmée accèdent aux nouveaux lots avant leur
  ouverture générale. L'adhésion est gratuite et révocable à tout moment ; il n'existe aucun droit
  à l'accès anticipé.
</p>
<p>
  11.8 Une alerte de prix ou l'ajout d'une pièce à vos favoris ne la réserve <strong>pas</strong> et
  ne crée aucun droit de préemption. La notification est envoyée une seule fois, sans garantie de
  réception ni d'horaire ; seule la disponibilité au moment de la commande est déterminante.
</p>

<h2 id="s12">12. Authenticité et provenance</h2>
<p>
  12.1 Seuls des articles authentiques sont proposés. Les vendeurs sont tenus de conserver les
  justificatifs d'achat de leurs articles et de nous les présenter sur demande.
</p>
<p>
  12.2 S'il s'avère après l'achat qu'un article n'est pas authentique, nous remboursons
  l'intégralité du prix d'achat, frais de port compris, et prenons en charge les frais de retour. Ce
  droit existe quel que soit le type de vendeur.
</p>
<p>
  12.3 Les droits au titre de la clause 12.2 supposent que vous nous rendiez l'article et votre
  réclamation accessibles dans les 30 jours suivant la réception et nous permettiez de l'examiner.
</p>

<h2 id="s13">13. Responsabilité</h2>
<p>
  13.1 Nous répondons sans limitation en cas de faute intentionnelle ou de négligence grave, en cas
  d'atteinte à la vie, à l'intégrité physique ou à la santé, selon la loi allemande sur la
  responsabilité du fait des produits ainsi que dans la limite d'une garantie que nous aurions
  accordée.
</p>
<p>
  13.2 En cas de violation par négligence légère d'une obligation contractuelle essentielle, la
  responsabilité est limitée au dommage prévisible et typique du contrat. Pour le reste, la
  responsabilité est exclue.
</p>
<p>
  13.3 Nous ne répondons pas des manquements des vendeurs tiers au contrat de vente ; notre
  responsabilité est régie à cet égard par les dispositions relatives aux services d'hébergement
  (art. 6 du règlement sur les services numériques, DSA). Les clauses 10.3 et 12.2 demeurent
  applicables.
</p>
<p>
  13.4 La disponibilité ininterrompue de la plateforme n'est pas garantie.
</p>

<h2 id="s14">14. Bons de réduction</h2>
<p>
  14.1 Les bons promotionnels (bons non achetés mais émis dans le cadre d'une opération commerciale)
  ne peuvent être utilisés que pendant la période indiquée et une seule fois. Toute utilisation est
  exclue après expiration ; aucune prolongation n'est accordée.
</p>
<p>
  14.2 La valeur du bon est imputée sur la valeur des articles, non sur les frais de port. Tout
  paiement en espèces, intérêt ou avoir sur un éventuel reliquat est exclu.
</p>
<p>
  14.3 Si un bon est lié à une adresse e-mail ou expressément désigné comme bon de bienvenue ou de
  première commande, seul le titulaire de cette adresse peut l'utiliser, et uniquement pour la
  première commande payée. Toute cession à un tiers ou revente est exclue.
</p>
<p>
  14.4 Plusieurs bons ne sont pas cumulables, sauf disposition contraire dans les conditions du bon
  concerné. Un montant minimum de commande s'applique s'il est indiqué avec le bon ; la valeur des
  articles hors frais de port est déterminante.
</p>
<p>
  14.5 Si vous vous rétractez d'une commande en tout ou partie, nous remboursons le montant
  effectivement payé. Un bon promotionnel utilisé n'est pas réactivé ; il n'existe aucun droit à
  l'émission d'un nouveau bon.
</p>
<p>
  14.6 En cas de soupçon fondé d'utilisation abusive — notamment la création de plusieurs comptes
  pour utiliser de façon répétée des bons de première commande — nous pouvons bloquer certains bons.
</p>

<h2 id="s15">15. Protection des données</h2>
<p>
  Les informations sur le traitement de vos données personnelles figurent dans la
  <a href="<?= h(vr_url('legal/datenschutz.php')) ?>"><?= te('legal_privacy') ?></a>.
  Pour l'exécution de votre commande, nous transmettons au vendeur concerné les données nécessaires
  à l'expédition et à la facturation.
</p>

<h2 id="s16">16. Dispositions finales</h2>
<p>
  16.1 Le droit de la République fédérale d'Allemagne s'applique. Pour les consommateurs résidant
  dans un autre État, les dispositions impératives de protection des consommateurs de leur État de
  résidence demeurent applicables (art. 6 al. 2 du règlement Rome I).
</p>
<p>
  16.2 Le lieu d'exécution et la juridiction compétente sont déterminés par la loi. Pour les
  consommateurs, les juridictions légalement compétentes s'appliquent.
</p>
<p>
  16.3 Si certaines dispositions des présentes conditions étaient nulles, la validité des autres
  dispositions n'en serait pas affectée. La disposition légale se substitue à la disposition nulle.
</p>
<p>
  16.4 Nous nous réservons le droit de modifier les présentes conditions pour l'avenir. Pour les
  contrats déjà conclus, la version consultable au moment de la conclusion s'applique ; la version
  acceptée est enregistrée avec votre commande (version actuelle : <?= h(VR_TERMS_VERSION) ?>).
</p>

<div class="doc__box">
  <strong>Note sur cette traduction</strong>
  <p style="margin-top:8px">
    Ce texte français est une traduction de courtoisie des conditions générales de vente allemandes,
    seules juridiquement contraignantes. Il vous permet de lire ce à quoi vous consentez ; en cas de
    divergence, la version allemande prévaut. Il ne constitue pas un conseil juridique.
  </p>
</div>
