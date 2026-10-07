<?php
/**
 * Politique relative aux cookies — français (traduction de courtoisie). Pas de bandeau, car pas de suivi.
 */
?>
<div class="doc__box">
  <strong>Pourquoi vous ne voyez pas de bandeau cookies ici</strong>
  <p style="margin-top:8px">
    Un bandeau n'est nécessaire que si des cookies allant au-delà du strict nécessaire technique
    sont déposés — analyse, publicité, suivi. Nous n'utilisons rien de tout cela. Pour les cookies
    purement fonctionnels, le § 25 al. 2 n° 2 TDDDG autorise le dépôt sans consentement. Donc : pas
    de bandeau, pas de bouton « Tout accepter », pas de service de consentement.
  </p>
</div>

<h2>Liste complète</h2>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Nom</th><th>Type</th><th>Finalité</th><th>Durée de conservation</th></tr></thead>
  <tbody>
    <tr>
      <td><code><?= h((string)vr_config('session_name', 'maxsales_retail')) ?></code></td>
      <td>cookie de session</td>
      <td>Maintient votre session : contenu du panier, réservations dans le Vault, connexion
          vendeur et jeton de sécurité contre la falsification de formulaires (CSRF). Ne contient
          qu'un identifiant aléatoire, aucune donnée personnelle.</td>
      <td>jusqu'à la fin de la session du navigateur</td>
    </tr>
    <tr>
      <td><code>vr_lang</code></td>
      <td>fonctionnel</td>
      <td>Mémorise la langue choisie pour que vous n'ayez pas à la resélectionner à chaque clic.
          Contenu : un code de langue tel que <code>fr</code>.</td>
      <td>180 jours</td>
    </tr>
    <tr>
      <td><code>vr_member</code></td>
      <td>fonctionnel</td>
      <td>Déposé uniquement après confirmation par e-mail de votre adhésion au Vault ; il débloque
          l'accès anticipé aux nouveaux lots. Contient une date d'expiration, un hachage tronqué de
          votre adresse e-mail et une signature — pas votre adresse en clair.</td>
      <td>1 an</td>
    </tr>
    <tr>
      <td><code>vr_wish</code></td>
      <td>fonctionnel</td>
      <td>Vos favoris. Contenu : une liste d'identifiants d'articles, rien d'autre. N'est pas
          transmis au serveur, sauf si vous ouvrez vous-même la page des favoris.</td>
      <td>180 jours</td>
    </tr>
    <tr>
      <td><code>vr_seen</code></td>
      <td>fonctionnel</td>
      <td>Les derniers articles que vous avez consultés, pour les retrouver. Là aussi, uniquement
          des identifiants d'articles.</td>
      <td>30 jours</td>
    </tr>
  </tbody>
</table></div>

<h2>Cookies de Stripe</h2>
<p>
  Lors du paiement, vous passez sur une page de Stripe. Stripe y dépose ses propres cookies,
  nécessaires au traitement du paiement et à la prévention de la fraude. Cela se produit sur le
  domaine de Stripe et relève de la
  <a href="https://stripe.com/privacy" rel="noopener">politique de confidentialité de Stripe</a>.
  Aucun script Stripe n'est intégré sur nos propres pages.
</p>

<h2>Pas de stockage local, pas d'empreinte numérique</h2>
<p>
  Nous n'utilisons ni <code>localStorage</code> ni <code>sessionStorage</code>, aucun pixel, aucune
  technique de fingerprinting ni de reconnaissance entre appareils. Toutes les polices, styles,
  scripts et images sont hébergés sur notre propre serveur ; aucune connexion à des tiers n'est
  établie lors du chargement d'une page.
</p>

<h2>Supprimer ou bloquer les cookies</h2>
<p>
  Vous pouvez à tout moment supprimer ou bloquer les cookies dans les paramètres de votre
  navigateur. Si vous bloquez le cookie de session, le panier, la caisse et la connexion vendeur ne
  fonctionnent plus — il manque alors le fil technique qui relie vos étapes. La langue et l'accès au
  Vault peuvent être bloqués sans problème ; nous redemandons alors la langue et l'accès anticipé
  n'est plus disponible.
</p>
<p class="doc__related">
  En détail sur le traitement des données :
  <a href="<?= h(vr_url('legal/datenschutz.php')) ?>"><?= te('legal_privacy') ?></a>.
</p>
