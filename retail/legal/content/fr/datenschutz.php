<?php
/**
 * Politique de confidentialité (RGPD) — français (traduction de courtoisie ; le
 * texte allemand fait foi). Écrite d'après ce que le site fait réellement.
 * Variables : legal/datenschutz.php.
 */
?>
<h2>1. Responsable du traitement</h2>
<?php vr_company_block(); ?>
<p>
  Aucun délégué à la protection des données n'a été désigné, les conditions légales (art. 37 RGPD,
  § 38 BDSG) n'étant pas réunies. Pour toute demande relative à la protection des données,
  adressez-vous à
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-mail]</em>' ?>.
</p>

<h2>2. Ce que nous ne faisons <em>pas</em></h2>
<p>
  Nous n'utilisons aucun outil d'analyse d'audience (ni Google Analytics, ni Matomo), aucun pixel
  publicitaire ou de suivi, aucun plug-in de réseaux sociaux et aucun profilage. Il n'y a pas de
  décision automatisée au sens de l'art. 22 RGPD. La formation des prix dans le Premium Outlet n'est
  pas personnalisée non plus : les prix suivent un calendrier fixe et publié, identique pour tous les
  visiteurs.
</p>
<p>
  Toutes les polices, feuilles de style, scripts et images sont chargés depuis notre propre serveur.
  En particulier, aucune Google Font n'est utilisée — votre adresse IP n'est donc transmise à aucun
  tiers lors de l'affichage d'une page.
</p>

<h2>3. Consultation du site (fichiers journaux du serveur)</h2>
<p>
  Lors de la consultation, notre hébergeur traite des données techniquement nécessaires : adresse
  IP, date et heure, ressource consultée, référent, agent utilisateur et volume de données
  transféré. Ces données sont nécessaires à la fourniture de la page et à la défense contre les
  attaques.
</p>
<ul>
  <li><strong>Finalité :</strong> mise à disposition, stabilité, sécurité informatique</li>
  <li><strong>Base juridique :</strong> art. 6 § 1 f) RGPD (intérêt légitime)</li>
  <li><strong>Durée de conservation :</strong> en règle générale 7 à 30 jours, puis suppression
      automatique</li>
</ul>

<h2>4. Cookies et stockage local</h2>
<p>
  Nous n'utilisons que des cookies techniquement nécessaires. Aucun consentement n'est requis pour
  ceux-ci en vertu du § 25 al. 2 n° 2 TDDDG — c'est pourquoi vous ne voyez pas de bandeau cookies
  chez nous.
</p>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Nom</th><th>Finalité</th><th>Durée</th></tr></thead>
  <tbody>
    <tr><td><code><?= h((string)vr_config('session_name', 'maxsales_retail')) ?></code></td>
        <td>session : panier, connexion vendeur, protection CSRF</td><td>fin de session</td></tr>
    <tr><td><code>vr_lang</code></td><td>langue choisie</td><td>180 jours</td></tr>
    <tr><td><code>vr_member</code></td>
        <td>accès anticipé au Vault après inscription confirmée à la newsletter (valeur signée, pas
            d'e-mail en clair)</td><td>1 an</td></tr>
    <tr><td><code>vr_wish</code></td><td>favoris — identifiants d'articles uniquement</td><td>180 jours</td></tr>
    <tr><td><code>vr_seen</code></td><td>articles vus récemment — identifiants d'articles uniquement</td><td>30 jours</td></tr>
  </tbody>
</table></div>
<p>
  Plus d'informations : <a href="<?= h(vr_url('legal/cookies.php')) ?>"><?= te('legal_cookies') ?></a>.
</p>

<h2>5. Favoris et articles vus récemment</h2>
<p>
  Les favoris et « Vus récemment » sont stockés <strong>exclusivement dans un cookie sur votre
  appareil</strong>. Le cookie ne contient que des identifiants d'articles (p. ex.
  <code>blm-ah0eg000</code>) — ni nom, ni adresse e-mail, ni identifiant permettant de vous
  identifier. Aucun profil ni rattachement à votre personne n'est créé sur nos serveurs.
</p>
<ul>
  <li><strong>Finalité :</strong> la fonction que vous avez expressément demandée</li>
  <li><strong>Base juridique :</strong> § 25 al. 2 n° 2 TDDDG (techniquement nécessaire au service
      demandé par l'utilisateur) ; en cas de données personnelles, art. 6 § 1 f) RGPD</li>
  <li><strong>Durée de conservation :</strong> favoris 180 jours, vus récemment 30 jours — ou jusqu'à
      la suppression des cookies</li>
</ul>

<h2>6. Formulaire de contact</h2>
<p>
  Si vous utilisez le formulaire de contact, nous traitons votre adresse e-mail, le cas échéant
  votre nom et votre numéro de commande, ainsi que le contenu de votre message. Une copie est
  enregistrée sur notre serveur afin qu'aucune demande ne soit perdue si l'envoi de l'e-mail échoue.
</p>
<ul>
  <li><strong>Finalité :</strong> réponse à votre demande</li>
  <li><strong>Base juridique :</strong> art. 6 § 1 b) RGPD en cas de lien avec une commande, sinon
      art. 6 § 1 f) RGPD</li>
  <li><strong>Durée de conservation :</strong> jusqu'au traitement définitif, puis au maximum six
      mois ; en cas de lien avec une commande, les délais de conservation du droit commercial
      s'appliquent</li>
</ul>
<p>
  Pour lutter contre le spam, nous utilisons un champ de formulaire invisible et une mesure du
  temps. <em>Aucun</em> service de captcha externe n'est intégré — aucune donnée n'est donc
  transmise à des tiers.
</p>

<h2>7. Alerte de prix dans le Vault</h2>
<p>
  Si vous définissez une alerte de prix pour un lot, nous enregistrons votre adresse e-mail, le lot,
  votre prix souhaité, l'horodatage et un hachage salé de votre adresse IP à titre de preuve.
</p>
<ul>
  <li><strong>Finalité :</strong> la seule notification que vous avez demandée</li>
  <li><strong>Base juridique :</strong> art. 6 § 1 a) RGPD (consentement)</li>
  <li><strong>Durée de conservation :</strong> jusqu'à l'envoi de la notification, au maximum
      90 jours. L'enregistrement est ensuite entièrement supprimé.</li>
</ul>
<p>
  <strong>Un seul</strong> e-mail est envoyé ; l'alerte est ensuite consommée. Aucun rappel ni
  publicité ne suit. Chaque alerte peut être supprimée immédiatement via le lien contenu dans
  l'e-mail.
</p>

<h2>8. Commande et exécution du contrat</h2>
<p>
  Pour une commande, nous traitons : nom, adresse de livraison et de facturation, adresse e-mail,
  articles commandés, prix, statut du paiement, numéro de commande ainsi qu'une preuve de votre
  acceptation des CGV et de l'information sur la rétractation (horodatage, version et hachage salé
  de votre adresse IP — l'IP elle-même n'est pas conservée).
</p>
<ul>
  <li><strong>Finalité :</strong> exécution du contrat, expédition, facturation, annulation</li>
  <li><strong>Base juridique :</strong> art. 6 § 1 b) RGPD ; pour la conservation, art. 6 § 1 c)
      RGPD</li>
  <li><strong>Durée de conservation :</strong> les données de commande et de facturation sont
      soumises aux délais de conservation du droit commercial et fiscal (§ 147 AO, § 257 HGB) et sont
      conservées en conséquence, puis supprimées.</li>
</ul>
<p>
  <strong>Transmission aux vendeurs :</strong> pour les articles de vendeurs tiers, nous transmettons
  au vendeur concerné les données nécessaires à l'expédition et à la facturation (nom, adresse de
  livraison, articles commandés, numéro de commande). Le vendeur est responsable de traitement
  autonome pour ces données. Ne sont pas transmises les données de paiement ni les informations sur
  les articles d'autres vendeurs.
</p>

<h2>9. Traitement des paiements (Stripe)</h2>
<p>
  Les paiements sont traités par Stripe Payments Europe, Limited, 1 Grand Canal Street Lower, Grand
  Canal Dock, Dublin, Irlande. Vous saisissez vos données de paiement directement chez Stripe ; nous
  ne recevons de Stripe que des informations de statut (payé/ouvert/échoué), le montant, le moyen de
  paiement sous forme générale, le nom, l'adresse e-mail et l'adresse de livraison.
</p>
<ul>
  <li><strong>Base juridique :</strong> art. 6 § 1 b) RGPD (exécution du contrat)</li>
  <li><strong>Transfert vers un pays tiers :</strong> Stripe peut transférer des données à Stripe,
      Inc. aux États-Unis, sur la base des clauses contractuelles types de la Commission européenne
      et de la certification au titre du cadre de protection des données UE–États-Unis.</li>
</ul>
<p>
  Pour les versements aux vendeurs, nous utilisons Stripe Connect. Les vendeurs concluent pour cela
  leur propre accord avec Stripe ; les justificatifs d'identité collectés à cette occasion
  (KYC/lutte contre le blanchiment) sont traités par Stripe en tant que responsable autonome. Nous
  ne recevons que les indicateurs d'état <code>charges_enabled</code>, <code>payouts_enabled</code>
  et <code>details_submitted</code>.
</p>
<p>Politique de confidentialité de Stripe : <a href="https://stripe.com/privacy" rel="noopener">stripe.com/privacy</a></p>

<h2>10. Envoi d'e-mails</h2>
<p>
  Les e-mails transactionnels (confirmation de commande, avis d'expédition, notification vendeur) et
  la newsletter sont envoyés via
  <?= h($mail['provider'] === 'brevo' ? 'Brevo (Sendinblue GmbH / Brevo SAS, Paris, France)' : 'notre serveur de messagerie') ?>.
  Sont transmis l'adresse e-mail, le nom et le contenu du message.
</p>
<ul>
  <li><strong>Base juridique :</strong> e-mails transactionnels art. 6 § 1 b) RGPD ; newsletter
      art. 6 § 1 a) RGPD (consentement)</li>
  <li><strong>Sous-traitance :</strong> un contrat au sens de l'art. 28 RGPD est en place.</li>
</ul>

<h2>11. Newsletter / adhésion au Vault</h2>
<p>
  L'inscription se fait par double opt-in : après saisie de votre adresse, vous recevez un e-mail de
  confirmation ; l'inscription ne devient effective qu'après un clic sur le lien. À titre de preuve,
  nous enregistrons l'horodatage de l'inscription, celui de la confirmation et un hachage salé de
  l'adresse IP.
</p>
<p>
  Les inscriptions non confirmées sont automatiquement supprimées après 7 jours. Vous pouvez retirer
  votre consentement à tout moment — via le lien de désinscription présent dans chaque e-mail ou en
  nous écrivant. Le retrait n'affecte pas la licéité du traitement effectué jusque-là.
</p>

<h2>12. Comptes vendeurs</h2>
<p>
  Pour un compte vendeur, nous traitons : nom, le cas échéant raison sociale, adresse e-mail, pays,
  le cas échéant numéro de TVA, type de vendeur (professionnel/particulier), mot de passe
  (uniquement sous forme de hachage cryptographique, jamais en clair), offres ainsi que données de
  chiffre d'affaires et de versement.
</p>
<ul>
  <li><strong>Base juridique :</strong> art. 6 § 1 b) RGPD ; pour la vérification des offres et la
      traçabilité des informations des professionnels, en outre art. 6 § 1 c) RGPD combiné à
      l'art. 30 du règlement sur les services numériques.</li>
  <li><strong>Publication :</strong> pour les vendeurs professionnels, nous affichons le nom/la
      raison sociale et le pays sur la page produit ; la loi l'exige. Pour les vendeurs
      particuliers, seul le statut « <?= te('seller_private') ?> » est affiché, pas le nom complet.</li>
</ul>

<h2>13. Mesures de sécurité et journaux</h2>
<p>
  Nous tenons des journaux techniques des tentatives de connexion échouées, des erreurs de paiement
  et des événements webhook. Ils contiennent l'horodatage, le type d'événement et des identifiants
  techniques ; les adresses e-mail y sont tronquées. La finalité est la prévention des abus et de la
  fraude (art. 6 § 1 f) RGPD), la durée de conservation est de 90 jours au maximum.
</p>
<p>
  La transmission est chiffrée (TLS). Les mots de passe sont stockés avec un procédé de hachage à
  sens unique moderne.
</p>

<h2>14. Vos droits</h2>
<p>Vous disposez à tout moment des droits suivants :</p>
<ul>
  <li>accès aux données vous concernant (art. 15 RGPD)</li>
  <li>rectification des données inexactes (art. 16 RGPD)</li>
  <li>effacement (art. 17 RGPD), sauf obligation de conservation contraire</li>
  <li>limitation du traitement (art. 18 RGPD)</li>
  <li>portabilité des données (art. 20 RGPD)</li>
  <li>opposition aux traitements fondés sur l'intérêt légitime (art. 21 RGPD)</li>
  <li>retrait des consentements donnés, avec effet pour l'avenir (art. 7 § 3 RGPD)</li>
</ul>
<p>
  Un message à
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-mail]</em>' ?>
  suffit. Vous avez en outre le droit d'introduire une réclamation auprès d'une autorité de contrôle
  de la protection des données, par exemple celle de votre résidence habituelle.
</p>

<h2>15. Modifications</h2>
<p>
  Nous adaptons cette déclaration lorsque le traitement effectif change — par exemple en cas de
  recours à un nouveau prestataire. La version publiée sur cette page fait foi.
</p>
