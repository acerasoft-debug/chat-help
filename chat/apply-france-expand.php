<?php
/**
 * ChatHelp — FRANSA KATALOG GENİŞLETME (~45 belge) + resmî formlar + FR'de Almanca Verträge gizle
 * Aynı kanıtlanmış deferred yöntem (CH_FR_FIX gibi). CAF/France Travail/Impôts/CPAM/Préfecture/Retraite
 * dahil. Tüm belgeler tier'lı -> Stripe otomatik (Almanya mantığı).
 * KULLANIM: chat-help.com/chat/apply-france-expand.php -> opcache-reset.php. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-france-expand BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,"CH_FR_EXPAND")!==false) exit("Zaten ekli (CH_FR_EXPAND).\n");
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-frexpand-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_FR_EXPAND — Fransa katalog genişletme (~45 belge) + FR'de Almanca bölümleri gizle */
try{(function(){
  function Pf(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var FRC={
    fr_resiliation:{n:"Résiliations",ic:"📄"},
    fr_logement:{n:"Logement & Bail",ic:"🏠"},
    fr_travail:{n:"Travail & Emploi",ic:"💼"},
    fr_litige:{n:"Litiges & Réclamations",ic:"⚖️"},
    fr_conso:{n:"Consommation",ic:"🛒"},
    fr_admin:{n:"Administratif",ic:"🏛️"}
  };
  var F={
    /* Résiliations */
    fr_resil_edf:{ic:"⚡",n:"Résiliation contrat d'énergie (EDF/Engie)",cat:"fr_resiliation",tier:"einfach",q:[Pf("fournisseur","Fournisseur : nom & adresse",true),Pf("num_contrat","N° de contrat / point de livraison (PDL)",true),Pf("adresse","Adresse du logement",true),Pf("motif","Motif",false,["Déménagement","Changement de fournisseur","Autre"]),Pf("date","Date de résiliation souhaitée",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_resil_eau:{ic:"💧",n:"Résiliation contrat d'eau",cat:"fr_resiliation",tier:"einfach",q:[Pf("fournisseur","Service des eaux : nom",true),Pf("num_contrat","N° d'abonné",true),Pf("adresse","Adresse du logement",true),Pf("date","Date de départ / résiliation",true),Pf("releve","Relevé de compteur (optionnel)",false),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_resil_mutuelle:{ic:"🩺",n:"Résiliation mutuelle santé",cat:"fr_resiliation",tier:"einfach",q:[Pf("organisme","Mutuelle : nom & adresse",true),Pf("num_adherent","N° d'adhérent",true),Pf("motif","Motif",false,["Échéance annuelle","Résiliation infra-annuelle (après 1 an)","Mutuelle obligatoire employeur","Changement de situation"]),Pf("date","Date d'effet souhaitée",false),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_resil_pret:{ic:"🏦",n:"Remboursement anticipé de prêt",cat:"fr_resiliation",tier:"standard",q:[Pf("banque","Banque / organisme : nom & adresse",true),Pf("num_pret","N° du prêt",true),Pf("type","Type",false,["Total","Partiel"]),Pf("montant","Montant à rembourser (€, si partiel)",false),Pf("date","Date souhaitée",false),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_resil_bail_meuble:{ic:"🛋️",n:"Résiliation bail meublé (locataire)",cat:"fr_resiliation",tier:"einfach",q:[Pf("bailleur","Bailleur / agence : nom & adresse",true),Pf("adresse_logement","Adresse du logement",true),Pf("date_depart","Date de départ (préavis 1 mois)",true),Pf("coordonnees","Vos nom & adresse",true)]},
    /* Logement & Bail */
    fr_demande_apl:{ic:"🏠",n:"Demande / réclamation APL (CAF)",cat:"fr_logement",tier:"standard",q:[Pf("num_allocataire","N° allocataire CAF (optionnel)",false),Pf("objet","Objet",true,["Demande d'APL","Réclamation / révision","Contestation d'un trop-perçu"]),Pf("situation","Votre situation (logement, revenus)",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_trouble_voisinage:{ic:"🔊",n:"Trouble de voisinage (mise en demeure)",cat:"fr_logement",tier:"standard",q:[Pf("voisin","Voisin concerné : nom & adresse",true),Pf("trouble","Nature du trouble (bruit, odeurs…)",true),Pf("frequence","Fréquence / horaires",false),Pf("demarches","Démarches déjà faites",false),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_assurance_sinistre:{ic:"🌊",n:"Déclaration de sinistre habitation",cat:"fr_logement",tier:"standard",q:[Pf("assureur","Assureur : nom & adresse",true),Pf("num_contrat","N° de contrat",true),Pf("type_sinistre","Type",true,["Dégât des eaux","Incendie","Vol/cambriolage","Bris de glace","Catastrophe naturelle"]),Pf("date_sinistre","Date du sinistre",true),Pf("description","Description & dommages",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_logement_insalubre:{ic:"⚠️",n:"Signalement de logement insalubre",cat:"fr_logement",tier:"komplex",q:[Pf("bailleur","Bailleur : nom & adresse",true),Pf("adresse_logement","Adresse du logement",true),Pf("problemes","Problèmes (humidité, chauffage, sécurité…)",true),Pf("demarches","Démarches déjà effectuées",false),Pf("demande","Votre demande",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_renouvellement_bail:{ic:"📄",n:"Demande de renouvellement de bail",cat:"fr_logement",tier:"standard",q:[Pf("bailleur","Bailleur : nom & adresse",true),Pf("adresse_logement","Adresse du logement",true),Pf("date_fin","Date de fin du bail actuel",true),Pf("coordonnees","Vos nom & adresse",true)]},
    /* Travail & Emploi (+ contrats) */
    fr_contrat_prestation:{ic:"🤝",n:"Contrat de prestation de services",cat:"fr_travail",tier:"komplex",q:[Pf("role","Vous êtes",true,["Prestataire","Client"]),Pf("autre_partie","Autre partie : nom & adresse",true),Pf("prestation","Description de la prestation",true),Pf("date_debut","Date de début",true),Pf("duree","Durée / échéances",false),Pf("prix","Prix & modalités de paiement (€)",true),Pf("clauses","Clauses particulières (confidentialité…)",false)]},
    fr_contrat_stage:{ic:"🎓",n:"Convention de stage",cat:"fr_travail",tier:"standard",q:[Pf("entreprise","Entreprise d'accueil : nom & adresse",true),Pf("stagiaire","Stagiaire : nom",true),Pf("etablissement","Établissement / école",true),Pf("missions","Missions du stage",true),Pf("date_debut","Date de début",true),Pf("date_fin","Date de fin",true),Pf("gratification","Gratification (€/mois, optionnel)",false)]},
    fr_solde_tout_compte:{ic:"🧾",n:"Contestation du solde de tout compte",cat:"fr_travail",tier:"standard",q:[Pf("employeur","Employeur : nom & adresse",true),Pf("date_fin","Date de fin de contrat",true),Pf("elements","Éléments contestés (sommes manquantes…)",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_heures_sup:{ic:"⏰",n:"Réclamation d'heures supplémentaires",cat:"fr_travail",tier:"standard",q:[Pf("employeur","Employeur : nom & adresse",true),Pf("poste","Votre poste",true),Pf("periode","Période concernée",true),Pf("heures","Heures dues (estimation)",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_demande_teletravail:{ic:"🏡",n:"Demande de télétravail",cat:"fr_travail",tier:"einfach",q:[Pf("employeur","Employeur / responsable",true),Pf("poste","Votre poste",true),Pf("rythme","Rythme souhaité (jours/semaine)",true),Pf("motif","Motif",false),Pf("coordonnees","Vos nom & poste",true)]},
    fr_harcelement_travail:{ic:"🚨",n:"Signalement de harcèlement au travail",cat:"fr_travail",tier:"komplex",q:[Pf("destinataire","Destinataire (employeur, RH, CSE…)",true),Pf("type","Type",true,["Harcèlement moral","Harcèlement sexuel","Discrimination"]),Pf("faits","Faits (dates, personnes, descriptions)",true),Pf("temoins","Témoins (optionnel)",false),Pf("demande","Votre demande",true),Pf("coordonnees","Vos nom & poste",true)]},
    fr_france_travail_courrier:{ic:"📨",n:"Courrier France Travail (inscription / actualisation)",cat:"fr_travail",tier:"standard",q:[Pf("identifiant","Identifiant France Travail (optionnel)",false),Pf("objet","Objet",true,["Inscription","Actualisation","Demande d'information","Mise à jour de situation"]),Pf("situation","Votre situation",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_contest_france_travail:{ic:"⚖️",n:"Contestation France Travail (radiation / trop-perçu)",cat:"fr_travail",tier:"komplex",q:[Pf("identifiant","Identifiant France Travail",true),Pf("decision","Décision contestée",true),Pf("date_decision","Date de la décision",true),Pf("motifs","Motifs de votre contestation",true),Pf("coordonnees","Vos nom & adresse",true)]},
    /* Litiges & Réclamations */
    fr_devis_litige:{ic:"🧰",n:"Litige devis / travaux non conformes",cat:"fr_litige",tier:"standard",q:[Pf("professionnel","Professionnel : nom & adresse",true),Pf("objet","Objet (travaux/devis)",true),Pf("probleme","Problème (malfaçon, retard, surfacturation…)",true),Pf("demande","Votre demande",true),Pf("montant","Montant en jeu (€, optionnel)",false),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_assurance_refus:{ic:"🛡️",n:"Contestation de refus d'indemnisation",cat:"fr_litige",tier:"komplex",q:[Pf("assureur","Assureur : nom & adresse",true),Pf("num_contrat","N° de contrat / sinistre",true),Pf("decision","Décision de refus",true),Pf("arguments","Vos arguments",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_credit_conso_litige:{ic:"💳",n:"Litige crédit à la consommation",cat:"fr_litige",tier:"standard",q:[Pf("organisme","Organisme de crédit : nom & adresse",true),Pf("num_contrat","N° de contrat",true),Pf("objet","Objet du litige",true),Pf("demande","Votre demande",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_surendettement:{ic:"🆘",n:"Dossier de surendettement (Banque de France)",cat:"fr_litige",tier:"komplex",q:[Pf("situation","Situation financière (revenus, charges)",true),Pf("dettes","Liste des dettes & créanciers",true),Pf("cause","Origine des difficultés",false),Pf("coordonnees","Vos nom, adresse & situation familiale",true)]},
    fr_petites_creances:{ic:"💶",n:"Recouvrement de petite créance",cat:"fr_litige",tier:"komplex",q:[Pf("debiteur","Débiteur : nom & adresse",true),Pf("montant","Montant dû (€, < 5000 €)",true),Pf("origine","Origine de la créance",true),Pf("relances","Relances déjà envoyées ?",false,["Oui","Non"]),Pf("coordonnees","Vos nom & adresse",true)]},
    /* Consommation */
    fr_annulation_commande:{ic:"🚫",n:"Annulation de commande",cat:"fr_conso",tier:"einfach",q:[Pf("vendeur","Vendeur : nom & adresse",true),Pf("commande","Référence de commande",true),Pf("date","Date de commande",true),Pf("motif","Motif (optionnel)",false),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_vice_cache:{ic:"🔍",n:"Produit défectueux / vice caché",cat:"fr_conso",tier:"standard",q:[Pf("vendeur","Vendeur : nom & adresse",true),Pf("produit","Produit concerné",true),Pf("date_achat","Date d'achat",true),Pf("defaut","Défaut / vice constaté",true),Pf("demande","Votre demande",true,["Remboursement","Remplacement","Réparation"]),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_reconduction_tacite:{ic:"🔁",n:"Contestation de reconduction tacite",cat:"fr_conso",tier:"einfach",q:[Pf("societe","Société : nom & adresse",true),Pf("num_contrat","N° de contrat",true),Pf("objet","Service concerné",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_litige_telecom:{ic:"📶",n:"Litige opérateur télécom (médiateur)",cat:"fr_conso",tier:"standard",q:[Pf("operateur","Opérateur : nom",true),Pf("num_client","N° client / ligne",true),Pf("probleme","Problème (facturation, réseau, engagement…)",true),Pf("demarches","Démarches déjà faites",false),Pf("demande","Votre demande",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_arnaque:{ic:"🎣",n:"Signalement d'arnaque / escroquerie",cat:"fr_conso",tier:"standard",q:[Pf("objet","Type d'arnaque",true),Pf("auteur","Auteur (site, société, personne)",false),Pf("faits","Description des faits",true),Pf("prejudice","Préjudice (€, optionnel)",false),Pf("coordonnees","Vos nom & adresse",true)]},
    /* Administratif & Formulaires (CAF, France Travail, Impôts, CPAM, Préfecture, Retraite) */
    fr_caf_rsa:{ic:"💶",n:"Demande / réclamation RSA (CAF)",cat:"fr_admin",tier:"standard",q:[Pf("num_allocataire","N° allocataire (optionnel)",false),Pf("objet","Objet",true,["Demande de RSA","Réclamation","Contestation d'un indu"]),Pf("situation","Votre situation (foyer, ressources)",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_caf_prime_activite:{ic:"📈",n:"Demande de prime d'activité (CAF)",cat:"fr_admin",tier:"standard",q:[Pf("num_allocataire","N° allocataire (optionnel)",false),Pf("situation","Situation pro & revenus",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_impots_reclamation:{ic:"🧾",n:"Réclamation aux impôts",cat:"fr_admin",tier:"standard",q:[Pf("centre","Centre des finances publiques",true),Pf("num_fiscal","N° fiscal (optionnel)",false),Pf("objet","Objet (impôt, avis concerné)",true),Pf("motif","Motif de la réclamation",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_impots_delai:{ic:"📅",n:"Demande de délai de paiement (impôts)",cat:"fr_admin",tier:"einfach",q:[Pf("centre","Centre des finances publiques",true),Pf("impot","Impôt concerné & montant (€)",true),Pf("situation","Votre situation financière",true),Pf("echeancier","Échéancier souhaité",false),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_cpam_remboursement:{ic:"🏥",n:"Demande de remboursement (CPAM)",cat:"fr_admin",tier:"standard",q:[Pf("num_secu","N° de sécurité sociale (optionnel)",false),Pf("objet","Objet (soins, frais)",true),Pf("montant","Montant (€)",false),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_css:{ic:"🩺",n:"Demande de Complémentaire santé solidaire (CSS)",cat:"fr_admin",tier:"standard",q:[Pf("num_secu","N° de sécurité sociale (optionnel)",false),Pf("situation","Situation & ressources du foyer",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_carte_grise:{ic:"🚗",n:"Demande / contestation carte grise (ANTS)",cat:"fr_admin",tier:"standard",q:[Pf("objet","Objet",true,["Changement de titulaire","Changement d'adresse","Duplicata","Contestation"]),Pf("immat","Plaque d'immatriculation",true),Pf("details","Détails de la demande",false),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_titre_sejour:{ic:"🛂",n:"Demande / renouvellement de titre de séjour",cat:"fr_admin",tier:"komplex",q:[Pf("prefecture","Préfecture concernée",true),Pf("type","Type de titre",true,["Première demande","Renouvellement","Changement de statut"]),Pf("motif","Motif (travail, études, familial…)",true),Pf("situation","Votre situation",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_naturalisation:{ic:"🇫🇷",n:"Demande de naturalisation",cat:"fr_admin",tier:"komplex",q:[Pf("prefecture","Préfecture / plateforme",true),Pf("fondement","Fondement",true,["Décret (résidence)","Mariage (déclaration)"]),Pf("situation","Votre parcours (résidence, intégration)",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_retraite_demande:{ic:"👴",n:"Demande de retraite (CNAV / Assurance retraite)",cat:"fr_admin",tier:"standard",q:[Pf("num_secu","N° de sécurité sociale (optionnel)",false),Pf("date_depart","Date de départ souhaitée",true),Pf("carriere","Carrière (résumé)",false),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_retraite_releve:{ic:"📊",n:"Demande de relevé de carrière",cat:"fr_admin",tier:"einfach",q:[Pf("num_secu","N° de sécurité sociale (optionnel)",false),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_acte_mariage:{ic:"💍",n:"Demande d'acte de mariage",cat:"fr_admin",tier:"einfach",q:[Pf("mairie","Mairie du lieu de mariage",true),Pf("epoux","Noms des époux",true),Pf("date_mariage","Date du mariage",true),Pf("type","Type d'acte",false,["Copie intégrale","Extrait"]),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_casier_judiciaire:{ic:"📁",n:"Demande d'extrait de casier judiciaire (B3)",cat:"fr_admin",tier:"einfach",q:[Pf("personne","Nom & date de naissance",true),Pf("lieu_naissance","Lieu de naissance",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_inscription_electorale:{ic:"🗳️",n:"Inscription sur les listes électorales",cat:"fr_admin",tier:"einfach",q:[Pf("mairie","Mairie de la commune",true),Pf("adresse","Adresse dans la commune",true),Pf("coordonnees","Vos nom, prénom & date de naissance",true)]}
  };

  function inject(){
    try{
      if(typeof DOCS==="undefined"||!DOCS) return false;
      for(var k in F){ if(!DOCS[k]) DOCS[k]=F[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=F[k].tier||"standard"; }
      if(typeof CATS!=="undefined"&&CATS){
        for(var ck in FRC){ if(!CATS[ck]) CATS[ck]={n:FRC[ck].n,ic:FRC[ck].ic,docs:[],country:"FR"}; if(!CATS[ck].docs) CATS[ck].docs=[]; }
        for(var k2 in F){ var c2=F[k2].cat; if(CATS[c2]&&CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); }
      }
      if(typeof CAT_LABELS!=="undefined"){ for(var cl in FRC) CAT_LABELS[cl]=FRC[cl].n; }
      if(typeof CAT_DOCS!=="undefined"){
        for(var cd in FRC){ if(!CAT_DOCS[cd]) CAT_DOCS[cd]=[]; }
        for(var k3 in F){ var c3=F[k3].cat; if(CAT_DOCS[c3]){ var ex=false; for(var i=0;i<CAT_DOCS[c3].length;i++){ if(CAT_DOCS[c3][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[c3].push({k:k3,ic:F[k3].ic,t:F[k3].n,tier:F[k3].tier||"standard"}); } }
      }
      return (typeof DOCS!=="undefined" && DOCS.fr_caf_rsa)?true:false;
    }catch(e){ return false; }
  }

  /* FR seçiliyken Almanca Verträge bölümünü gizle (güvenli, bilinen sınıf) */
  function hideGermanSections(){
    try{
      var fr=(typeof CC!=="undefined" && CC==="FR");
      document.querySelectorAll(".ch-vtr-sec").forEach(function(el){ el.style.display=fr?"none":""; });
    }catch(e){}
  }

  var done=false, ticks=0;
  function run(){
    if(!done){ if(inject()) done=true; }
    hideGermanSections();
    if(ticks++<40) setTimeout(run, 500);
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}

CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
if($src!==$start){ file_put_contents($file,$src); echo "Fransa genisletme eklendi (CH_FR_EXPAND) - ~45 belge + resmi formlar + FR'de Almanca Vertraege gizli.\n"; }
else echo "degisiklik yok\n";
echo "\nStripe: yeni belgeler tier'li -> odeme otomatik (Almanya gibi).\n";
echo "SONRA: opcache-reset.php. SIL: rm apply-france-expand.php\n";
echo "Test: FR France -> Administratif & Formulaires (CAF/Impots/CPAM/Prefecture) + Travail (contrats) genis.\n";
