<?php
/**
 * Règlement des litiges + point de contact DSA + procédure de signalement (art. 16 DSA) — français.
 * Variables : legal/streitbeilegung.php.
 */
?>
<h2>D'abord la voie directe</h2>
<p>
  La plupart des problèmes se règlent en un e-mail. Écrivez à <?= $mail ?> en indiquant votre numéro
  de commande et une brève description. Nous répondons en général sous un jour ouvré et revenons vers
  vous au plus tard après sept jours avec une décision ou un point d'étape.
</p>

<h2>Réclamations concernant des vendeurs</h2>
<p>
  En cas de problème avec un vendeur tiers — marchandise non reçue, état non conforme,
  remboursement non effectué — nous intervenons comme médiateur et pouvons retenir la part du
  vendeur jusqu'à ce que l'affaire soit réglée. Faute de solution, nous remboursons nous-mêmes dans
  les cas prévus aux <a href="<?= h(vr_url('legal/agb.php')) ?>#s10">clauses 10.3</a> et
  <a href="<?= h(vr_url('legal/agb.php')) ?>#s12">12.2</a> des CGV.
</p>

<h2>Signalement de contenus illicites (art. 16 DSA)</h2>
<p>
  Toute personne peut nous signaler des offres qu'elle estime illicites — par exemple des
  contrefaçons, des atteintes au droit des marques ou des produits interdits. Veuillez indiquer :
</p>
<ul>
  <li>l'adresse exacte (URL) de l'offre,</li>
  <li>les raisons pour lesquelles le contenu serait illicite,</li>
  <li>votre nom et une adresse e-mail (sauf pour les signalements d'infractions contre des
      personnes),</li>
  <li>une déclaration attestant que vos indications sont exactes et complètes en toute bonne foi.</li>
</ul>
<p>
  Adressez les signalements à <?= $mail ?> avec l'objet « Signalement DSA ». Nous accusons réception
  sans délai, décidons rapidement et avec diligence, et vous communiquons la décision motivée. Les
  titulaires de droits qui nous adressent de manière répétée des signalements fondés sont traités en
  priorité (art. 22 DSA).
</p>

<h2>Si nous retirons une offre</h2>
<p>
  Les vendeurs concernés sont informés, avec motifs, de tout retrait, blocage ou réduction de
  visibilité (art. 17 DSA) et peuvent contester la décision par e-mail dans un délai de 14 jours. La
  contestation est examinée par une personne n'ayant pas participé à la décision initiale. Nous
  communiquons la décision motivée sur la contestation.
</p>
<p>
  En cas de signalements manifestement infondés ou d'offres illicites répétées, nous suspendons le
  traitement après un avertissement préalable raisonnable ou bloquons le compte (art. 23 DSA).
</p>

<h2>Règlement extrajudiciaire des litiges de consommation</h2>
<p>
  Nous ne sommes ni tenus ni disposés à participer à une procédure de règlement des litiges devant
  un organisme de médiation de la consommation au sens de la loi allemande sur le règlement
  extrajudiciaire des litiges de consommation (VSBG). Votre possibilité d'agir en justice n'en est
  pas affectée ; pas plus que notre volonté de régler d'abord chaque cas directement.
</p>
<p>
  Remarque : l'ancienne plateforme de règlement en ligne des litiges (plateforme RLL) de la
  Commission européenne a été supprimée le 20 juillet 2025. Le lien correspondant a donc été retiré.
  Pour les litiges transfrontaliers, vous pouvez vous adresser au Centre européen des consommateurs
  (<a href="https://www.evz.de" rel="noopener">evz.de</a>).
</p>

<h2>Juridiction compétente et droit applicable</h2>
<p>
  Le droit allemand s'applique. Pour les consommateurs, les juridictions légalement compétentes
  s'appliquent ; les dispositions impératives de protection des consommateurs de l'État de résidence
  demeurent applicables (art. 6 al. 2 du règlement Rome I).
</p>

<h2>Point de contact pour les autorités</h2>
<p>
  Pour les demandes des autorités et des juridictions au titre de l'art. 11 DSA, vous pouvez nous
  joindre à <?= $mail ?>. Les langues de procédure sont l'allemand et l'anglais.
</p>
