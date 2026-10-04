<?php
/**
 * ChatHelp — apply-fr-expand (CH_FR_EXPAND) — Fransa katalog genişletme
 *  Yeni kategori "Famille" (fr_famille) + mevcut kategorilere toplam ~23 yeni
 *  Fransız belgesi (kendi hukukuna göre: Code civil, Code du travail, loi 89,
 *  Code de la consommation, CRPA, RGPD). ES genişletmesinin kanıtlanmış
 *  deferred-injection deseniyle: DOCS + CATS + CAT_DOCS + CAT_LABELS.
 * KULLANIM: pull-updates.php?files=apply-fr-expand.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fr-expand BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_FR_EXPAND')!==false) exit("Zaten ekli (CH_FR_EXPAND).\n");
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if (strpos($src,$anchor)===false) exit("HATA: anchor (getDocPrice) yok.\n");

$js = <<<'CHJS'

/* CH_FR_EXPAND — Fransa katalog genişletme (yeni kategori Famille + ~23 belge, deferred) */
try{(function(){
  function Pe(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var NEWCATS={ fr_famille:{n:"Famille",ic:"👨‍👩‍👧"} };

  var E={
    /* Famille (nouvelle catégorie) */
    fr_divorce_consentement:{ic:"📜",n:"Convention de divorce par consentement mutuel (projet)",cat:"fr_famille",tier:"komplex",q:[Pe("epoux","Noms et prénoms des deux époux",true),Pe("date_mariage","Date et lieu du mariage",true),Pe("enfants","Enfants (nom/âge, sinon 'aucun')",true),Pe("residence","Résidence des enfants et droit de visite convenus",false),Pe("pension","Pension alimentaire convenue (€/mois, sinon 'aucune')",false),Pe("biens","Répartition des biens / liquidation (résumé)",true),Pe("nota","NOTE : la convention doit être contresignée par avocats et déposée chez notaire",false)]},
    fr_pension_alimentaire:{ic:"💶",n:"Demande de pension alimentaire",cat:"fr_famille",tier:"komplex",q:[Pe("debiteur","Parent débiteur : nom et prénom",true),Pe("enfants","Enfants concernés (nom/âge)",true),Pe("charges","Charges de l'enfant (scolarité, garde, santé…)",true),Pe("ressources","Ressources de chaque parent (résumé)",true),Pe("montant","Montant demandé (€/mois)",true),Pe("demandeur","Vos nom, prénom et adresse",true)]},
    fr_revision_pension:{ic:"⚖️",n:"Demande de révision de pension alimentaire",cat:"fr_famille",tier:"komplex",q:[Pe("decision","Décision fixant la pension (date et tribunal)",true),Pe("autre_parent","Autre parent : nom et prénom",true),Pe("montant_actuel","Montant actuel (€/mois)",true),Pe("changement","Changement de situation justifiant la révision",true),Pe("montant_demande","Nouveau montant demandé (€/mois)",true),Pe("demandeur","Vos nom, prénom et adresse",true)]},
    fr_autorisation_sortie:{ic:"✈️",n:"Autorisation de sortie du territoire (mineur)",cat:"fr_famille",tier:"standard",q:[Pe("mineur","Nom, prénom et date de naissance du mineur",true),Pe("titulaire","Titulaire de l'autorité parentale qui autorise : nom et pièce d'identité",true),Pe("accompagnant","Personne accompagnant le mineur (nom, lien)",false),Pe("destination","Pays de destination",true),Pe("dates","Dates du voyage",true)]},
    fr_droit_visite:{ic:"👪",n:"Demande de droit de visite et d'hébergement",cat:"fr_famille",tier:"komplex",q:[Pe("autre_parent","Autre parent : nom et prénom",true),Pe("enfants","Enfants concernés (nom/âge)",true),Pe("situation","Situation actuelle (résidence, obstacles rencontrés)",true),Pe("demande","Droit de visite souhaité (week-ends, vacances…)",true),Pe("demandeur","Vos nom, prénom et adresse",true)]},
    fr_pacs:{ic:"🤝",n:"Convention de PACS (déclaration conjointe)",cat:"fr_famille",tier:"standard",q:[Pe("partenaires","Noms, prénoms et dates de naissance des deux partenaires",true),Pe("adresse","Adresse commune",true),Pe("regime","Régime des biens choisi",true,["Séparation des patrimoines","Indivision"]),Pe("nota","NOTE : à enregistrer en mairie ou chez notaire",false)]},

    /* Travail & Emploi */
    fr_rupture_conventionnelle:{ic:"🤝",n:"Demande de rupture conventionnelle",cat:"fr_travail",tier:"standard",q:[Pe("employeur","Employeur : nom et adresse",true),Pe("poste","Votre poste et date d'embauche",true),Pe("motif","Motif (facultatif)",false),Pe("date_souhaitee","Date de fin souhaitée",false),Pe("salarie","Vos nom et prénom",true)]},
    fr_contestation_solde:{ic:"🧾",n:"Contestation du solde de tout compte",cat:"fr_travail",tier:"standard",q:[Pe("employeur","Employeur : nom et adresse",true),Pe("date_fin","Date de fin de contrat",true),Pe("sommes","Sommes contestées (congés payés, prime, préavis…)",true),Pe("montant","Montant réclamé (€)",true),Pe("salarie","Vos nom, prénom et adresse",true)]},
    fr_heures_sup:{ic:"⏱️",n:"Réclamation d'heures supplémentaires impayées",cat:"fr_travail",tier:"komplex",q:[Pe("employeur","Employeur : nom et adresse",true),Pe("periode","Période concernée",true),Pe("heures","Heures supplémentaires effectuées (détail/estimation)",true),Pe("montant","Montant réclamé (€, si estimable)",false),Pe("salarie","Vos nom, prénom et poste",true)]},
    fr_docs_fin_contrat:{ic:"📄",n:"Demande de documents de fin de contrat",cat:"fr_travail",tier:"einfach",q:[Pe("employeur","Employeur : nom et adresse",true),Pe("date_fin","Date de fin de contrat",true),Pe("documents","Documents demandés",true,["Certificat de travail","Attestation France Travail (Pôle emploi)","Solde de tout compte","Tous"]),Pe("salarie","Vos nom et prénom",true)]},
    fr_demande_teletravail:{ic:"🏠",n:"Demande de télétravail",cat:"fr_travail",tier:"einfach",q:[Pe("employeur","Employeur/RH",true),Pe("poste","Votre poste",true),Pe("rythme","Rythme souhaité (jours/semaine)",true),Pe("motif","Motif (facultatif)",false),Pe("salarie","Vos nom et prénom",true)]},

    /* Logement & Bail */
    fr_restitution_depot:{ic:"🔑",n:"Demande de restitution du dépôt de garantie",cat:"fr_logement",tier:"standard",q:[Pe("bailleur","Bailleur : nom et adresse",true),Pe("logement","Adresse du logement loué",true),Pe("date_depart","Date de restitution des clés",true),Pe("montant","Montant du dépôt (€)",true),Pe("retard","Retard / retenues contestées (le cas échéant)",false),Pe("locataire","Vos nom, prénom et nouvelle adresse",true)]},
    fr_contestation_etat_lieux:{ic:"📋",n:"Contestation de retenue sur dépôt (état des lieux)",cat:"fr_logement",tier:"komplex",q:[Pe("bailleur","Bailleur : nom et adresse",true),Pe("logement","Adresse du logement",true),Pe("retenues","Retenues contestées (détail)",true),Pe("arguments","Vos arguments (vétusté, absence de preuve…)",true),Pe("montant","Montant réclamé (€)",true),Pe("locataire","Vos nom, prénom et adresse",true)]},
    fr_demande_travaux:{ic:"🔧",n:"Demande de travaux au bailleur",cat:"fr_logement",tier:"standard",q:[Pe("bailleur","Bailleur : nom et adresse",true),Pe("logement","Adresse du logement",true),Pe("desordres","Désordres / travaux nécessaires (détail)",true),Pe("delai","Délai raisonnable demandé",false),Pe("locataire","Vos nom, prénom et adresse",true)]},
    fr_conge_preavis_reduit:{ic:"📆",n:"Congé du locataire (préavis réduit, zone tendue)",cat:"fr_logement",tier:"standard",q:[Pe("bailleur","Bailleur : nom et adresse",true),Pe("logement","Adresse du logement",true),Pe("motif","Motif du préavis réduit (1 mois)",true,["Zone tendue","Mutation professionnelle","Perte d'emploi","État de santé (65+)","Bénéficiaire RSA/AAH"]),Pe("date_depart","Date de départ souhaitée",true),Pe("locataire","Vos nom, prénom et adresse",true)]},

    /* Consommation */
    fr_retractation:{ic:"↩️",n:"Rétractation d'un achat à distance (14 jours)",cat:"fr_conso",tier:"einfach",q:[Pe("vendeur","Vendeur/commerçant : nom",true),Pe("commande","Référence et date de commande/livraison",true),Pe("produit","Produit(s) concerné(s)",true),Pe("montant","Montant à rembourser (€)",true),Pe("client","Vos nom, prénom et adresse",true)]},
    fr_garantie_conformite:{ic:"🛠️",n:"Garantie légale de conformité",cat:"fr_conso",tier:"standard",q:[Pe("vendeur","Vendeur : nom et adresse",true),Pe("produit","Produit et date d'achat",true),Pe("defaut","Défaut constaté",true),Pe("demande","Demande",true,["Réparation","Remplacement","Remboursement"]),Pe("client","Vos nom, prénom et adresse",true)]},
    fr_reclamation_banque:{ic:"🏦",n:"Réclamation auprès de la banque (médiateur)",cat:"fr_conso",tier:"komplex",q:[Pe("banque","Banque : nom et agence",true),Pe("compte","Nº de compte/contrat concerné",true),Pe("faits","Faits reclamés (frais, incident, refus…)",true),Pe("prealable","Réclamation préalable au service client (date/résultat)",true),Pe("demande","Votre demande (remboursement €, rectification…)",true),Pe("client","Vos nom, prénom et adresse",true)]},

    /* Administratif */
    fr_recours_gracieux:{ic:"🏛️",n:"Recours gracieux (administration)",cat:"fr_admin",tier:"komplex",q:[Pe("administration","Administration concernée",true),Pe("decision","Décision contestée (nº et date de notification)",true),Pe("motifs","Motifs du recours (arguments)",true),Pe("demande","Votre demande (annulation, réexamen…)",true),Pe("demandeur","Vos nom, prénom et adresse",true)]},
    fr_contestation_caf:{ic:"📨",n:"Contestation d'une décision CAF / France Travail",cat:"fr_admin",tier:"komplex",q:[Pe("organisme","Organisme",true,["CAF","France Travail (Pôle emploi)","CPAM","Autre"]),Pe("decision","Décision contestée (date, nº dossier)",true),Pe("motifs","Motifs de la contestation",true),Pe("demande","Votre demande",true),Pe("demandeur","Vos nom, prénom et nº allocataire/identifiant",true)]},
    fr_rgpd:{ic:"🔒",n:"Exercice des droits RGPD (CNIL)",cat:"fr_admin",tier:"standard",q:[Pe("responsable","Responsable de traitement (entreprise/organisme)",true),Pe("droit","Droit exercé",true,["Accès","Effacement","Rectification","Opposition","Portabilité","Limitation"]),Pe("relation","Votre relation (client, ancien salarié…)",true),Pe("demandeur","Vos nom, prénom et adresse",true)]},

    /* Litiges & Réclamations */
    fr_mise_en_demeure:{ic:"⚠️",n:"Mise en demeure de payer",cat:"fr_litige",tier:"standard",q:[Pe("debiteur","Débiteur : nom et adresse",true),Pe("creance","Origine de la créance (facture, prêt…)",true),Pe("montant","Montant dû (€)",true),Pe("echeance","Date d'échéance dépassée",true),Pe("delai","Délai accordé pour payer (jours)",false),Pe("creancier","Vos nom, prénom et adresse",true)]},
    fr_injonction_payer:{ic:"⚖️",n:"Requête en injonction de payer (projet)",cat:"fr_litige",tier:"komplex",q:[Pe("debiteur","Débiteur : nom et adresse complète",true),Pe("creance","Nature et justificatifs de la créance",true),Pe("montant","Montant en principal (€) + intérêts éventuels",true),Pe("mise_demeure","Une mise en demeure a-t-elle été envoyée ?",true,["Oui","Non"]),Pe("creancier","Vos nom, prénom et adresse",true)]}
  };

  function inject(){
    try{
      if(typeof DOCS==='undefined'||!DOCS) return false;
      if(typeof CATS==='undefined'||!CATS.fr_travail) return false; /* base FR yüklü olmalı */
      for(var k in E){ if(!DOCS[k]) DOCS[k]=E[k]; if(typeof DOC_TIER!=='undefined') DOC_TIER[k]=E[k].tier||'standard'; }
      for(var nc in NEWCATS){ if(!CATS[nc]) CATS[nc]={n:NEWCATS[nc].n,ic:NEWCATS[nc].ic,docs:[],country:'FR'}; if(!CATS[nc].docs) CATS[nc].docs=[]; }
      for(var k2 in E){ var c2=E[k2].cat; if(CATS[c2]&&CATS[c2].docs&&CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); }
      if(typeof CAT_LABELS!=='undefined'){ for(var cl in NEWCATS) CAT_LABELS[cl]=NEWCATS[cl].n; }
      if(typeof CAT_DOCS!=='undefined'){
        for(var cd in NEWCATS){ if(!CAT_DOCS[cd]) CAT_DOCS[cd]=[]; }
        for(var k3 in E){ var c3=E[k3].cat; if(CAT_DOCS[c3]){ var ex=false; for(var i=0;i<CAT_DOCS[c3].length;i++){ if(CAT_DOCS[c3][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[c3].push({k:k3,ic:E[k3].ic,t:E[k3].n,tier:E[k3].tier||'standard'}); } }
      }
      return !!(DOCS.fr_mise_en_demeure);
    }catch(e){ return false; }
  }
  var done=false, ticks=0;
  function run(){ if(!done){ if(inject()) done=true; } if(ticks++<40) setTimeout(run,500); }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',run); else run();
})();}catch(e){}
CHJS;

$src=str_replace($anchor,$anchor.$js,$src);

$tmp = tempnam(sys_get_temp_dir(),'fx').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-frexpand-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "  ✓ 23 yeni Fransız belgesi + 'Famille' kategorisi eklendi\n";
echo "\n✓ CH_FR_EXPAND uygulandi. Fransa kataloğu genişletildi (Famille, Travail, Logement, Consommation, Administratif, Litiges).\n";
