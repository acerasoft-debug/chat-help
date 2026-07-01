<?php
/**
 * ChatHelp — FRANSA KATALOG GENİŞLETME 2 (+30 belge, 1 yeni kategori: Famille)
 * ------------------------------------------------------------------------------
 *  CH_FR_INLINE (~28) + CH_FR_INLINE2 (+27) + CH_FR_EXPAND (+40) üzerine ek.
 *  Mevcut 6 FR kategorisine yeni belgeler + "Famille" kategorisi (nafaka, JAF,
 *  attestation de témoin Cerfa 11527 vb.). Aynı kanıtlanmış deferred yöntem.
 *  Tüm belgeler tier'lı -> Stripe otomatik. Belge dili CC=FR ile otomatik Fransızca.
 *
 * KULLANIM: chat-help.com/chat/apply-france-expand2.php -> opcache-reset.php. SİL.
 * GEREKLİ: apply-france.php önce çalıştırılmış olmalı (CH_FR_INLINE, fr_* kategoriler).
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-france-expand2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,"CH_FR_EXPAND2")!==false) exit("Zaten ekli (CH_FR_EXPAND2).\n");
if(strpos($src,"CH_FR_INLINE")===false) exit("CH_FR_INLINE yok — once apply-france.php calistir.\n");
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-frexpand2-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_FR_EXPAND2 — Fransa katalog genişletme 2 (+30 belge, yeni kategori: Famille; deferred yükleme) */
try{(function(){
  function Pf(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}

  var NEWCATS={
    fr_famille:{n:"Famille",ic:"👨‍👩‍👧"}
  };

  var F={
    /* Famille (nouvelle catégorie) */
    fr_pension_alimentaire:{ic:"💶",n:"Mise en demeure — pension alimentaire impayée",cat:"fr_famille",tier:"komplex",q:[Pf("debiteur","Débiteur (autre parent) : nom & adresse",true),Pf("titre","Fondement (jugement, convention…)",true),Pf("montant_mensuel","Montant mensuel dû (€)",true),Pf("arrieres","Arriérés dus (mois & total €)",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_revision_pension:{ic:"📉",n:"Demande de révision de pension alimentaire",cat:"fr_famille",tier:"komplex",q:[Pf("autre_parent","Autre parent : nom & adresse",true),Pf("montant_actuel","Montant actuel (€)",true),Pf("sens","Sens de la demande",true,["Diminution","Augmentation"]),Pf("changement","Changement de situation (revenus, charges, garde)",true),Pf("montant_souhaite","Montant souhaité (€, optionnel)",false),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_jaf_garde:{ic:"👨‍👩‍👧",n:"Courrier au JAF (garde / droit de visite)",cat:"fr_famille",tier:"komplex",q:[Pf("tribunal","Tribunal judiciaire (JAF) concerné",true),Pf("enfants","Enfants concernés (prénoms & âges)",true),Pf("situation","Situation actuelle (garde, visites)",true),Pf("demande","Votre demande (garde alternée, modification…)",true),Pf("motifs","Motifs (intérêt de l'enfant)",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_attestation_vie_commune:{ic:"🏠",n:"Attestation de vie commune (concubinage)",cat:"fr_famille",tier:"einfach",q:[Pf("partenaires","Les deux partenaires : noms & prénoms",true),Pf("adresse","Adresse commune",true),Pf("depuis","Vie commune depuis (date)",true),Pf("destinataire","Organisme destinataire (CAF, mutuelle…)",false)]},
    fr_livret_famille:{ic:"📜",n:"Demande de duplicata du livret de famille",cat:"fr_famille",tier:"einfach",q:[Pf("mairie","Mairie du mariage / du domicile",true),Pf("motif","Motif",true,["Perte","Vol","Détérioration","Séparation/divorce"]),Pf("famille","Noms des époux/parents & dates",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_temoin_attestation:{ic:"✍️",n:"Attestation de témoin (justice)",cat:"fr_famille",tier:"standard",q:[Pf("affaire","Affaire concernée (parties, juridiction)",true),Pf("faits","Faits dont vous témoignez (précis, datés)",true),Pf("lien","Lien avec les parties",true),Pf("coordonnees","Vos nom, prénom, date & lieu de naissance, profession, adresse",true)]},
    fr_pacs_dissolution:{ic:"💔",n:"Déclaration de dissolution de PACS",cat:"fr_famille",tier:"standard",q:[Pf("partenaires","Les deux partenaires : noms",true),Pf("lieu_pacs","Lieu d'enregistrement du PACS",true),Pf("date_pacs","Date du PACS",true),Pf("type","Dissolution",true,["Commune (les deux)","Unilatérale (par huissier)"]),Pf("coordonnees","Vos nom & adresse",true)]},

    /* Travail & Emploi */
    fr_conge_parental:{ic:"👶",n:"Demande de congé parental",cat:"fr_travail",tier:"einfach",q:[Pf("employeur","Employeur : nom & adresse",true),Pf("enfant","Enfant (date de naissance / adoption)",true),Pf("date_debut","Date de début souhaitée",true),Pf("duree","Durée souhaitée",true),Pf("temps","Forme",false,["Congé total","Temps partiel"]),Pf("coordonnees","Vos nom & poste",true)]},
    fr_saisine_prudhommes:{ic:"⚖️",n:"Saisine du conseil de prud'hommes (requête)",cat:"fr_travail",tier:"komplex",q:[Pf("conseil","Conseil de prud'hommes compétent (ville)",true),Pf("employeur","Employeur : nom & adresse",true),Pf("poste","Votre poste & dates du contrat",true),Pf("litige","Objet du litige (licenciement, salaires…)",true),Pf("demandes","Vos demandes chiffrées (€)",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_refus_modification:{ic:"🚫",n:"Refus de modification du contrat de travail",cat:"fr_travail",tier:"standard",q:[Pf("employeur","Employeur : nom & adresse",true),Pf("modification","Modification proposée (lieu, salaire, horaires…)",true),Pf("date_proposition","Date de la proposition",true),Pf("raisons","Raisons de votre refus",true),Pf("coordonnees","Vos nom & poste",true)]},
    fr_demande_formation:{ic:"🎓",n:"Demande de formation (CPF / plan de développement)",cat:"fr_travail",tier:"einfach",q:[Pf("employeur","Employeur / RH",true),Pf("formation","Formation visée (intitulé, organisme)",true),Pf("dates","Dates & durée",true),Pf("financement","Financement",false,["CPF","Plan de développement","Pro-A","Autre"]),Pf("motivation","Lien avec votre poste / projet",true)]},
    fr_accident_travail:{ic:"🚑",n:"Déclaration d'accident du travail (salarié)",cat:"fr_travail",tier:"standard",q:[Pf("employeur","Employeur : nom & adresse",true),Pf("date_accident","Date & heure de l'accident",true),Pf("lieu","Lieu de l'accident",true),Pf("circonstances","Circonstances & lésions",true),Pf("temoins","Témoins (optionnel)",false),Pf("coordonnees","Vos nom & poste",true)]},

    /* Logement & Bail */
    fr_contrat_colocation:{ic:"🛏️",n:"Pacte de colocation (entre colocataires)",cat:"fr_logement",tier:"standard",q:[Pf("colocataires","Tous les colocataires : noms",true),Pf("adresse_logement","Adresse du logement",true),Pf("repartition_loyer","Répartition du loyer & charges",true),Pf("regles","Règles communes (ménage, invités, départ…)",false),Pf("date_debut","Date de début",true)]},
    fr_demande_sous_location:{ic:"🔑",n:"Demande d'autorisation de sous-location",cat:"fr_logement",tier:"einfach",q:[Pf("bailleur","Bailleur : nom & adresse",true),Pf("adresse_logement","Adresse du logement",true),Pf("periode","Période de sous-location",true),Pf("motif","Motif",false),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_punaises_lit:{ic:"🐛",n:"Signalement punaises de lit / nuisibles",cat:"fr_logement",tier:"standard",q:[Pf("bailleur","Bailleur / agence : nom & adresse",true),Pf("adresse_logement","Adresse du logement",true),Pf("constat","Constat (depuis quand, pièces touchées)",true),Pf("demande","Votre demande (traitement, prise en charge)",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_regularisation_charges:{ic:"🧾",n:"Demande de justificatifs de charges locatives",cat:"fr_logement",tier:"einfach",q:[Pf("bailleur","Bailleur : nom & adresse",true),Pf("adresse_logement","Adresse du logement",true),Pf("periode","Période / année concernée",true),Pf("coordonnees","Vos nom & adresse",true)]},

    /* Consommation */
    fr_livraison_non_recue:{ic:"📦",n:"Commande non reçue / livraison en retard",cat:"fr_conso",tier:"einfach",q:[Pf("vendeur","Vendeur : nom & adresse",true),Pf("commande","Référence de commande",true),Pf("date_commande","Date de commande",true),Pf("date_prevue","Date de livraison prévue",false),Pf("demande","Votre demande",true,["Livraison sous délai","Annulation & remboursement"]),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_contestation_cb:{ic:"💳",n:"Contestation d'un paiement par carte (banque)",cat:"fr_conso",tier:"standard",q:[Pf("banque","Banque : nom & adresse",true),Pf("operation","Opération contestée (date, montant, bénéficiaire)",true),Pf("motif","Motif",true,["Fraude / paiement non autorisé","Double débit","Montant erroné","Service non fourni"]),Pf("opposition","Carte mise en opposition ?",false,["Oui","Non"]),Pf("coordonnees","Vos nom & n° de compte",true)]},
    fr_voyage_forfait:{ic:"✈️",n:"Réclamation voyage à forfait / agence",cat:"fr_conso",tier:"standard",q:[Pf("agence","Agence / voyagiste : nom & adresse",true),Pf("dossier","Référence du dossier",true),Pf("voyage","Voyage (destination, dates)",true),Pf("probleme","Problème rencontré (annulation, non-conformité…)",true),Pf("demande","Votre demande (remboursement, indemnisation)",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_location_voiture:{ic:"🚗",n:"Litige location de voiture",cat:"fr_conso",tier:"standard",q:[Pf("loueur","Loueur : nom & adresse",true),Pf("contrat","N° de contrat / réservation",true),Pf("dates","Dates de location",true),Pf("litige","Litige (frais abusifs, dommages contestés, caution…)",true),Pf("demande","Votre demande",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_frais_bancaires:{ic:"🏦",n:"Remboursement de frais bancaires",cat:"fr_conso",tier:"einfach",q:[Pf("banque","Banque : nom & agence",true),Pf("frais","Frais contestés (nature, dates, montants)",true),Pf("motif","Motif de la contestation",true),Pf("coordonnees","Vos nom & n° de compte",true)]},

    /* Litiges & Réclamations */
    fr_usurpation_identite:{ic:"🆔",n:"Signalement d'usurpation d'identité",cat:"fr_litige",tier:"komplex",q:[Pf("destinataire","Destinataire (banque, organisme, plateforme)",true),Pf("faits","Faits constatés (comptes, crédits, démarches à votre nom)",true),Pf("decouverte","Date de découverte",true),Pf("plainte","Plainte déposée ?",false,["Oui","Pas encore"]),Pf("demande","Votre demande (clôture, suppression, blocage)",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_cyberharcelement:{ic:"📵",n:"Signalement de cyberharcèlement",cat:"fr_litige",tier:"standard",q:[Pf("destinataire","Destinataire (plateforme, école, employeur, police)",true),Pf("faits","Faits (messages, comptes, dates)",true),Pf("preuves","Preuves disponibles (captures…)",false),Pf("demande","Votre demande",true),Pf("coordonnees","Vos nom & coordonnées",true)]},
    fr_indemnisation_tiers:{ic:"🏚️",n:"Demande d'indemnisation (dommage causé par un tiers)",cat:"fr_litige",tier:"standard",q:[Pf("responsable","Responsable : nom & adresse",true),Pf("faits","Faits & date du dommage",true),Pf("dommages","Dommages subis (description)",true),Pf("montant","Montant réclamé (€)",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_contestation_facture:{ic:"🧾",n:"Contestation de facture",cat:"fr_litige",tier:"einfach",q:[Pf("emetteur","Émetteur de la facture : nom & adresse",true),Pf("facture","Référence & date de la facture",true),Pf("montant","Montant contesté (€)",true),Pf("motif","Motif de la contestation",true),Pf("coordonnees","Vos nom & adresse",true)]},

    /* Administratif */
    fr_bourse_crous:{ic:"🎓",n:"Demande / recours bourse étudiante (CROUS)",cat:"fr_admin",tier:"standard",q:[Pf("crous","CROUS concerné (académie)",true),Pf("objet","Objet",true,["Demande initiale","Recours contre refus","Réexamen (changement de situation)"]),Pf("situation","Situation (études, foyer, revenus)",true),Pf("ine","N° INE / dossier (optionnel)",false),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_duplicata_permis:{ic:"🪪",n:"Demande de duplicata du permis de conduire",cat:"fr_admin",tier:"einfach",q:[Pf("motif","Motif",true,["Perte","Vol","Détérioration"]),Pf("num_permis","N° de permis (si connu)",false),Pf("declaration","Déclaration de perte/vol faite ?",false,["Oui","Non"]),Pf("coordonnees","Vos nom, date de naissance & adresse",true)]},
    fr_recours_refus_visa:{ic:"🛂",n:"Recours contre un refus de visa",cat:"fr_admin",tier:"komplex",q:[Pf("consulat","Consulat / autorité ayant refusé",true),Pf("date_refus","Date du refus",true),Pf("type_visa","Type de visa demandé",true),Pf("motif_refus","Motif de refus indiqué",true),Pf("arguments","Vos arguments & pièces nouvelles",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_attestation_accueil:{ic:"🏠",n:"Demande d'attestation d'accueil (mairie)",cat:"fr_admin",tier:"standard",q:[Pf("mairie","Mairie du domicile",true),Pf("visiteur","Personne accueillie : nom, nationalité, passeport",true),Pf("dates","Dates du séjour (max 90 jours)",true),Pf("logement","Votre logement (adresse, surface)",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_changement_nom:{ic:"✍️",n:"Demande de changement de nom / prénom",cat:"fr_admin",tier:"komplex",q:[Pf("objet","Objet",true,["Changement de prénom (mairie)","Changement de nom (nom de famille)"]),Pf("actuel","Nom/prénom actuel",true),Pf("souhaite","Nom/prénom souhaité",true),Pf("motifs","Motifs (intérêt légitime)",true),Pf("coordonnees","Vos nom, date de naissance & adresse",true)]}
  };

  function inject(){
    try{
      if(typeof DOCS==="undefined"||!DOCS) return false;
      if(typeof CATS==="undefined"||!CATS.fr_travail) return false; /* CH_FR_INLINE yüklenmiş olmalı */
      for(var k in F){ if(!DOCS[k]) DOCS[k]=F[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=F[k].tier||"standard"; }
      for(var nc in NEWCATS){ if(!CATS[nc]) CATS[nc]={n:NEWCATS[nc].n,ic:NEWCATS[nc].ic,docs:[],country:"FR"}; if(!CATS[nc].docs) CATS[nc].docs=[]; }
      for(var k2 in F){ var c2=F[k2].cat; if(CATS[c2]&&CATS[c2].docs&&CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); }
      if(typeof CAT_LABELS!=="undefined"){ for(var cl in NEWCATS) CAT_LABELS[cl]=NEWCATS[cl].n; }
      if(typeof CAT_DOCS!=="undefined"){
        for(var cd in NEWCATS){ if(!CAT_DOCS[cd]) CAT_DOCS[cd]=[]; }
        for(var k3 in F){ var c3=F[k3].cat; if(CAT_DOCS[c3]){ var ex=false; for(var i=0;i<CAT_DOCS[c3].length;i++){ if(CAT_DOCS[c3][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[c3].push({k:k3,ic:F[k3].ic,t:F[k3].n,tier:F[k3].tier||"standard"}); } }
      }
      return (typeof DOCS!=="undefined" && DOCS.fr_saisine_prudhommes)?true:false;
    }catch(e){ return false; }
  }

  var done=false, ticks=0;
  function run(){ if(!done){ if(inject()) done=true; } if(ticks++<40) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}
CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
if($src!==$start){ file_put_contents($file,$src); echo "Fransa genisletme 2 eklendi (CH_FR_EXPAND2) - +30 belge, yeni kategori: Famille.\n"; }
else echo "degisiklik yok\n";
echo "\nOzet:\n";
echo "  Famille (YENI): pension alimentaire, revision, JAF, vie commune, livret de famille, temoin, PACS (+7)\n";
echo "  Travail: conge parental, prud'hommes, refus modification, formation CPF, accident du travail (+5)\n";
echo "  Logement: colocation, sous-location, punaises de lit, justificatifs charges (+4)\n";
echo "  Conso: livraison non recue, contestation CB, voyage a forfait, location voiture, frais bancaires (+5)\n";
echo "  Litige: usurpation d'identite, cyberharcelement, indemnisation tiers, contestation facture (+4)\n";
echo "  Admin: bourse CROUS, duplicata permis, recours refus visa, attestation d'accueil, changement de nom (+5)\n";
echo "\nStripe: tum belgeler tier'li -> odeme otomatik. Belge dili CC=FR ile otomatik Fransizca.\n";
echo "SONRA: opcache-reset.php. SIL: rm apply-france-expand2.php\n";
echo "Test: FR sec -> 'Famille' kategorisi gorunmeli; Travail'da 'Saisine du conseil de prud'hommes' gorunmeli.\n";
