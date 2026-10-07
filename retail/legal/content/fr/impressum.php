<?php
/**
 * Mentions légales (§ 5 DDG, § 18 MStV) — français (traduction de courtoisie). Variables : legal/impressum.php.
 */
?>
<h2>Éditeur</h2>
<?php vr_company_block(); ?>

<h2>Exploitant de la place de marché</h2>
<p>
  <?= h((string)vr_config('brand')) ?> est une place de marché en ligne exploitée par
  <?= h((string)($c['legal_name'] ?? '')) ?>. La plateforme propose à la fois des articles propres
  de l'exploitant et des articles de tiers (revendeurs professionnels et vendeurs particuliers).
  L'identité de votre cocontractant est indiquée sur chaque page produit et lors de la commande,
  avant validation.
</p>

<h2>Responsable du contenu</h2>
<p>
  Responsable au sens du § 18 al. 2 MStV : la personne habilitée à représenter la société indiquée
  ci-dessus, adresse comme ci-dessus.
</p>

<h2>Contact pour les demandes des consommateurs</h2>
<p>
  Pour toute question relative aux commandes, aux retours et aux réclamations, écrivez à
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[adresse e-mail]</em>' ?>.
  Nous répondons en général sous un jour ouvré. Pour les questions concernant un article d'un
  vendeur tiers, nous vous mettons en relation avec le vendeur ou transmettons votre demande.
</p>

<h2>Règlement des litiges de consommation</h2>
<p>
  Nous ne sommes ni tenus ni disposés à participer à une procédure de règlement des litiges devant un
  organisme de médiation de la consommation. Cela n'exclut pas un règlement amiable avec nous —
  adressez-vous d'abord directement à nous. Plus d'informations sous
  <a href="<?= h(vr_url('legal/streitbeilegung.php')) ?>"><?= te('legal_disputes') ?></a>.
</p>

<h2>Prestataire de paiement</h2>
<p>
  Les paiements sont traités via Stripe (Stripe Payments Europe, Limited, 1 Grand Canal Street
  Lower, Grand Canal Dock, Dublin, Irlande). Les versements aux vendeurs tiers s'effectuent via
  Stripe Connect. Les données de carte sont traitées exclusivement chez Stripe et n'atteignent pas
  nos systèmes.
</p>

<h2>Responsabilité pour les contenus</h2>
<p>
  En tant que prestataire de services, nous sommes responsables de nos propres contenus sur ces
  pages conformément aux lois générales. Pour les offres de vendeurs tiers, nous ne sommes pas tenus
  de surveiller les informations transmises ou stockées par des tiers ni de rechercher des
  circonstances révélant une activité illicite (art. 6 et 8 du règlement sur les services
  numériques). Dès que nous avons connaissance d'une infraction concrète, nous retirons sans délai
  le contenu concerné. Les signalements sont à adresser à
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[adresse e-mail]</em>' ?>.
</p>
<p>
  Nous vérifions la plausibilité des offres de vendeurs tiers avant leur mise en ligne et exigeons
  des justificatifs de provenance. Ce contrôle volontaire ne constitue pas une garantie de la licéité
  ou de l'authenticité de chaque offre et laisse intacte notre exonération de responsabilité en tant
  qu'hébergeur.
</p>

<h2>Responsabilité pour les liens</h2>
<p>
  Notre site contient des liens vers des sites externes de tiers sur le contenu desquels nous
  n'avons aucune influence. Le fournisseur concerné est toujours responsable de ces contenus. Au
  moment de la création des liens, aucun contenu illicite n'était identifiable.
</p>

<h2>Droit d'auteur</h2>
<p>
  Les contenus et œuvres créés par l'exploitant sur ces pages sont protégés par le droit d'auteur.
  Les images et descriptions de produits de vendeurs tiers sont fournies par ceux-ci ; ils nous
  garantissent disposer des droits nécessaires. Les noms de marques et de produits sont la propriété
  de leurs titulaires respectifs. Leur mention sert uniquement à décrire les articles proposés et
  n'établit aucune relation commerciale avec les titulaires des marques.
</p>

<h2>Remarque sur les droits de marque</h2>
<p>
  <?= h((string)vr_config('brand')) ?> n'est pas un distributeur agréé des marques citées, sauf
  indication expresse contraire. Les articles proposés sont des produits authentiques mis pour la
  première fois sur le marché dans l'Espace économique européen ; leur revente est donc licite en
  vertu du principe d'épuisement du droit des marques (§ 24 MarkenG, art. 15 RMUE).
</p>
