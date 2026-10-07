<?php
/**
 * Déclaration d'accessibilité (BFSG) — français (traduction de courtoisie).
 * Variables : legal/barrierefreiheit.php.
 */
?>
<h2>Notre exigence</h2>
<p>
  Nous voulons que cette boutique soit utilisable par tous — au clavier, avec un lecteur d'écran,
  avec une police agrandie, avec des animations réduites. La base en est les exigences de la loi
  allemande sur le renforcement de l'accessibilité (BFSG) et la norme EN 301 549, qui renvoie aux
  WCAG 2.1 niveau AA.
</p>

<h2>État de la mise en œuvre</h2>
<p>
  Selon notre évaluation, ce site est <strong>largement conforme</strong> aux WCAG 2.1 niveau AA.
  Cette évaluation repose sur un contrôle interne, non sur un audit externe.
</p>

<h3>Ce qui est mis en œuvre</h3>
<ul>
  <li><strong>Utilisable sans JavaScript :</strong> navigation, filtres, panier, caisse et espace
      vendeur fonctionnent entièrement avec de simples formulaires HTML. JavaScript n'apporte que du
      confort (compte à rebours, sélecteur de quantité, apparitions en fondu).</li>
  <li><strong>Utilisation au clavier :</strong> tous les éléments interactifs sont atteignables, avec
      un indicateur de focus visible ; un lien « Aller au contenu » figure en haut de page.</li>
  <li><strong>Contrastes :</strong> chaque nœud de texte des pages principales a été mesuré
      automatiquement (couleur réellement rendue sur fond réellement rendu, transparences comprises).
      Tous atteignent au moins 4,5:1, les grands caractères au moins 3:1. La zone sombre du Vault
      utilise ses propres valeurs de gris, et les couleurs d'accent ont une variante texte plus foncée
      sur fond clair.</li>
  <li><strong>Structure :</strong> un H1 par page, niveaux de titres logiques, points de repère
      (<code>header</code>, <code>nav</code>, <code>main</code>, <code>footer</code>), champs de
      formulaire étiquetés, messages d'erreur liés.</li>
  <li><strong>Images :</strong> les images produit portent des textes alternatifs composés de la
      marque et de la désignation ; les graphismes purement décoratifs sont masqués aux technologies
      d'assistance.</li>
  <li><strong>Mouvement :</strong> lorsque le réglage système « Réduire les animations » est activé,
      le bandeau défilant, les fondus et les transitions sont désactivés.</li>
  <li><strong>Zoom et petits écrans :</strong> la mise en page reste utilisable jusqu'à 400 % de zoom
      sans défilement horizontal du contenu.</li>
  <li><strong>Langue :</strong> la langue de la page est déclarée dans le HTML et peut être changée
      via le sélecteur de langue (dix langues).</li>
  <li><strong>Galerie d'images :</strong> chaque image produit est un lien ordinaire vers le fichier
      image. Sans JavaScript, elle s'ouvre directement ; avec JavaScript, dans une visionneuse qui se
      ferme avec Échap et se parcourt avec les flèches.</li>
  <li><strong>Suggestions de recherche :</strong> utilisables avec les flèches et Entrée, fermables
      avec Échap. Le champ de recherche fonctionne aussi sans suggestions comme un formulaire
      ordinaire.</li>
  <li><strong>Favoris :</strong> réalisés sous forme de formulaire ; l'état figure dans
      <code>aria-pressed</code> et est correctement enregistré même sans JavaScript.</li>
</ul>

<h3>Limites connues</h3>
<ul>
  <li><strong>Images produit de vendeurs tiers :</strong> les textes alternatifs sont générés
      automatiquement à partir de la marque et de la désignation. Ils ne décrivent pas le motif en
      détail — pour toute question sur un article, nous vous le décrivons volontiers par e-mail.</li>
  <li><strong>Page de paiement :</strong> le paiement se déroule chez Stripe. Stripe est responsable
      de l'accessibilité de ces pages ; nous n'avons connaissance d'aucun défaut de conformité mais
      ne les avons pas testées nous-mêmes.</li>
  <li><strong>Compte à rebours du Vault :</strong> le temps restant se met à jour chaque seconde. Le
      prix déterminant figure en texte à côté et ne change qu'après un rechargement de la page, de
      sorte que l'usage d'un lecteur d'écran n'est pas perturbé par des changements en direct.</li>
  <li><strong>Documents PDF :</strong> les factures et étiquettes de retour sont en partie générées
      par les vendeurs et les transporteurs et peuvent ne pas être balisées. Sur demande, nous
      fournissons leur contenu sous une forme accessible.</li>
</ul>

<h2>Retour d'information et contact</h2>
<p>
  Si vous rencontrez un obstacle, écrivez à <?= $mail ?> avec l'objet « Accessibilité » en indiquant
  si possible la page et votre technologie d'assistance. Nous répondons sous un jour ouvré et
  indiquons une date de correction. Si vous avez besoin d'une information sous une autre forme —
  gros caractères, texte brut, lecture par téléphone — dites-le simplement ; nous la fournissons
  gratuitement.
</p>

<h2>Procédure de recours</h2>
<p>
  Si notre réponse ne vous aide pas, vous pouvez vous adresser à l'autorité de surveillance du marché
  des Länder pour l'accessibilité des produits et services (MDBD). C'est l'organisme compétent au
  titre du BFSG pour les réclamations relatives à l'accessibilité des services :
  <a href="https://www.marktueberwachungsstelle.de" rel="noopener">marktueberwachungsstelle.de</a>.
</p>

<h2>Établissement de cette déclaration</h2>
<p>
  Cette déclaration a été établie le <?= h(vr_date(strtotime('2026-08-01'))) ?> sur la base d'une
  auto-évaluation interne : navigation au clavier, mesure automatisée du contraste de tous les nœuds
  de texte dans un vrai navigateur, test avec JavaScript désactivé, vérification de la structure des
  titres et des étiquettes de formulaire. Aucun audit externe n'a eu lieu. Nous mettons la
  déclaration à jour lorsque la boutique évolue de façon substantielle.
</p>
