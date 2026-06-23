<?php
/**
 * ChatHelp — FRANSA KESİN FIX: deferred katalog birleştirme + dil senkronu + debug kutusunu kaldır
 * FR-DEBUG kanıtladı: DOCS/CATS getDocPrice anında tanımsızdı -> birleştirme atlanıyordu (fr_count=0).
 * Bu modül DOMContentLoaded + tekrarlarla DOCS/CATS/CAT_DOCS hazır olunca ~53 Fransız belgeyi yükler;
 * CC=FR iken arayüz dilini fr yapar (diller karışmaz).
 * KULLANIM: chat-help.com/chat/apply-france-fix.php -> opcache-reset.php. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-france-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,"CH_FR_FIX")!==false) exit("Zaten ekli (CH_FR_FIX).\n");
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-frfix-".date("Ymd-His"),$src);

/* 1) Debug kutusunu (CH_FRDEBUG) kaldir */
$re=preg_replace('/\n*\/\* CH_FRDEBUG.*?\}\)\(\);\}catch\(e\)\{\}/s','',$src,1,$cnt);
if($cnt>0){ $src=$re; echo "Debug kutusu (CH_FRDEBUG) kaldirildi.\n"; } else { echo "Debug kutusu zaten yok.\n"; }

/* 2) Fransa fix modulunu ekle */
$js=<<<'CHJS'

/* CH_FR_FIX — Fransa kataloğu DEFERRED birleştirme (DOCS/CATS/CAT_DOCS hazır olunca) + dil senkronu */
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
  var FR_ORDER=["fr_resiliation","fr_logement","fr_travail","fr_litige","fr_conso","fr_admin"];
  var F={
/* Résiliations */
    fr_resil_bail:{ic:"🏠",n:"Résiliation de bail (congé locataire)",cat:"fr_resiliation",tier:"einfach",q:[Pf("destinataire","Bailleur / agence : nom & adresse",true),Pf("adresse_logement","Adresse du logement",true),Pf("zone_tendue","Le logement est-il en zone tendue ?",false,["Oui (préavis 1 mois)","Non (préavis 3 mois)","Je ne sais pas"]),Pf("motif_reduit","Motif de préavis réduit (le cas échéant)",false,["Aucun","Mutation professionnelle","Premier emploi","Perte d'emploi","Raisons de santé","Bénéficiaire RSA/AAH"]),Pf("date_depart","Date de départ souhaitée",true),Pf("coordonnees","Vos nom, adresse & téléphone",true)]},
    fr_resil_assurance:{ic:"🛡️",n:"Résiliation d'assurance",cat:"fr_resiliation",tier:"einfach",q:[Pf("assureur","Assureur : nom & adresse",true),Pf("num_contrat","Numéro de contrat",true),Pf("type","Type d'assurance",true,["Auto","Habitation","Santé/Mutuelle","Emprunteur","Autre"]),Pf("motif","Motif de résiliation",false,["Échéance annuelle","Loi Hamon (après 1 an)","Changement de situation","Vente du bien"]),Pf("date_effet","Date d'effet souhaitée",false),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_resil_abonnement:{ic:"📱",n:"Résiliation d'abonnement",cat:"fr_resiliation",tier:"einfach",q:[Pf("societe","Société : nom & adresse",true),Pf("num_client","Numéro de client / contrat",true),Pf("type","Type d'abonnement",true,["Téléphonie mobile","Internet/Box","Salle de sport","Streaming/TV","Presse","Autre"]),Pf("motif","Motif (optionnel)",false),Pf("date_souhaitee","Date de résiliation souhaitée",false),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_resil_banque:{ic:"🏦",n:"Clôture de compte bancaire",cat:"fr_resiliation",tier:"einfach",q:[Pf("banque","Banque : nom & adresse de l'agence",true),Pf("num_compte","Numéro de compte / IBAN",true),Pf("motif","Motif (optionnel)",false),Pf("solde","Virer le solde vers (IBAN, optionnel)",false),Pf("coordonnees","Vos nom & adresse",true)]},
    /* Logement */
    fr_preavis_logement:{ic:"🏠",n:"Préavis de départ (locataire)",cat:"fr_logement",tier:"einfach",q:[Pf("bailleur","Bailleur / agence : nom & adresse",true),Pf("adresse_logement","Adresse du logement",true),Pf("zone_tendue","Zone tendue ?",false,["Oui (1 mois)","Non (3 mois)","Je ne sais pas"]),Pf("motif_reduit","Motif de préavis réduit (le cas échéant)",false),Pf("date_depart","Date de départ",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_etat_lieux:{ic:"📋",n:"Contestation d'état des lieux",cat:"fr_logement",tier:"standard",q:[Pf("bailleur","Bailleur : nom & adresse",true),Pf("adresse_logement","Adresse du logement",true),Pf("date_etat","Date de l'état des lieux",true),Pf("points","Points contestés (détaillez)",true),Pf("demande","Votre demande",true)]},
    fr_depot_garantie:{ic:"💶",n:"Restitution du dépôt de garantie",cat:"fr_logement",tier:"standard",q:[Pf("bailleur","Bailleur : nom & adresse",true),Pf("adresse_logement","Adresse du logement",true),Pf("date_depart","Date de remise des clés",true),Pf("montant","Montant du dépôt (€)",true),Pf("retard","Jours de retard (optionnel)",false),Pf("interets","Demander les intérêts de retard ?",false,["Oui","Non"]),Pf("coordonnees","Vos nom, adresse & IBAN",true)]},
    fr_contest_charges:{ic:"🧾",n:"Contestation de charges locatives",cat:"fr_logement",tier:"standard",q:[Pf("bailleur","Bailleur : nom & adresse",true),Pf("adresse_logement","Adresse du logement",true),Pf("annee","Année des charges",true),Pf("montant","Montant contesté (€)",true),Pf("raisons","Raisons de la contestation",true)]},
    fr_bail_habitation:{ic:"🏠",n:"Contrat de bail (habitation)",cat:"fr_logement",tier:"standard",q:[Pf("role","Vous êtes",true,["Bailleur","Locataire"]),Pf("autre_partie","Autre partie : nom & adresse",true),Pf("adresse_logement","Adresse du logement",true),Pf("surface","Surface (m²) & nombre de pièces",true),Pf("type","Type de location",true,["Vide","Meublée"]),Pf("date_debut","Date de début",true),Pf("loyer","Loyer mensuel hors charges (€)",true),Pf("charges","Provision pour charges (€)",false),Pf("depot","Dépôt de garantie (€)",false)]},
    /* Travail */
    fr_demission:{ic:"💼",n:"Lettre de démission",cat:"fr_travail",tier:"einfach",q:[Pf("employeur","Employeur : nom & adresse",true),Pf("poste","Votre poste / fonction",true),Pf("date_embauche","Date d'embauche (optionnel)",false),Pf("preavis","Préavis",false,["J'effectue mon préavis","Je demande une dispense de préavis"]),Pf("date_depart","Date de fin souhaitée",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_rupture_conv:{ic:"🤝",n:"Demande de rupture conventionnelle",cat:"fr_travail",tier:"standard",q:[Pf("employeur","Employeur : nom & adresse",true),Pf("poste","Votre poste",true),Pf("anciennete","Ancienneté (années)",false),Pf("motif","Motif de la demande",true),Pf("indemnite","Indemnité souhaitée (optionnel)",false),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_contest_licenciement:{ic:"⚖️",n:"Contestation de licenciement",cat:"fr_travail",tier:"komplex",q:[Pf("employeur","Employeur : nom & adresse",true),Pf("poste","Votre poste",true),Pf("date_licenciement","Date du licenciement",true),Pf("motif_invoque","Motif invoqué par l'employeur",true),Pf("raisons","Pourquoi contestez-vous ? (faits)",true),Pf("demande","Votre demande",false,["Indemnités","Réintégration","Requalification"]),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_demande_conges:{ic:"🌴",n:"Demande de congés payés",cat:"fr_travail",tier:"einfach",q:[Pf("employeur","Employeur / responsable",true),Pf("periode_du","Du (date)",true),Pf("periode_au","Au (date)",true),Pf("nb_jours","Nombre de jours (optionnel)",false),Pf("coordonnees","Vos nom & poste",true)]},
    fr_attestation_employeur:{ic:"📄",n:"Demande de documents à l'employeur",cat:"fr_travail",tier:"einfach",q:[Pf("employeur","Employeur : nom & adresse",true),Pf("documents","Documents demandés",true,["Attestation France Travail","Certificat de travail","Solde de tout compte","Bulletins de paie"]),Pf("date_fin","Date de fin de contrat (optionnel)",false),Pf("coordonnees","Vos nom & adresse",true)]},
    /* Litiges */
    fr_mise_en_demeure:{ic:"⚖️",n:"Mise en demeure",cat:"fr_litige",tier:"standard",q:[Pf("destinataire","Destinataire : nom & adresse",true),Pf("objet","Objet (de quoi s'agit-il ?)",true),Pf("faits","Faits / contexte",true),Pf("demande","Ce que vous exigez",true),Pf("montant","Montant réclamé (€, optionnel)",false),Pf("delai","Délai accordé",false,["8 jours","15 jours","30 jours"]),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_reclamation:{ic:"📨",n:"Lettre de réclamation",cat:"fr_litige",tier:"einfach",q:[Pf("destinataire","Destinataire : nom & adresse",true),Pf("objet","Objet de la réclamation",true),Pf("description","Description du problème",true),Pf("demande","Ce que vous demandez",true),Pf("date_incident","Date de l'incident (optionnel)",false),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_contest_amende:{ic:"🚗",n:"Contestation d'amende / PV",cat:"fr_litige",tier:"standard",q:[Pf("autorite","Autorité (OMP / ANTAI)",true),Pf("num_avis","Numéro d'avis de contravention",true),Pf("date_infraction","Date de l'infraction",true),Pf("motif","Motif de contestation",true),Pf("immat","Plaque d'immatriculation (optionnel)",false),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_recours_gracieux:{ic:"🏛️",n:"Recours gracieux (administration)",cat:"fr_litige",tier:"standard",q:[Pf("administration","Administration concernée",true),Pf("decision","Décision contestée",true),Pf("date_decision","Date de la décision",true),Pf("motifs","Motifs du recours",true),Pf("demande","Votre demande",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_relance_impaye:{ic:"💶",n:"Lettre de relance (impayé)",cat:"fr_litige",tier:"einfach",q:[Pf("destinataire","Débiteur : nom & adresse",true),Pf("montant","Montant dû (€)",true),Pf("facture","Référence de facture (optionnel)",false),Pf("echeance","Date d'échéance dépassée",true),Pf("delai","Nouveau délai de paiement",false),Pf("coordonnees","Vos nom & adresse",true)]},
    /* Consommation */
    fr_retractation:{ic:"🔄",n:"Droit de rétractation (14 jours)",cat:"fr_conso",tier:"einfach",q:[Pf("vendeur","Vendeur : nom & adresse",true),Pf("commande","Référence de commande",true),Pf("date_commande","Date de commande / réception",true),Pf("produits","Produits concernés",true),Pf("remboursement","IBAN pour remboursement (optionnel)",false),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_remboursement:{ic:"💶",n:"Demande de remboursement",cat:"fr_conso",tier:"einfach",q:[Pf("societe","Société : nom & adresse",true),Pf("objet","Objet",true),Pf("motif","Motif de la demande",true),Pf("montant","Montant (€)",true),Pf("date_achat","Date d'achat (optionnel)",false),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_sav:{ic:"🔧",n:"Réclamation SAV / garantie",cat:"fr_conso",tier:"standard",q:[Pf("societe","Société / SAV : nom & adresse",true),Pf("produit","Produit concerné",true),Pf("date_achat","Date d'achat",true),Pf("probleme","Description du problème",true),Pf("demande","Votre demande",true,["Réparation","Remplacement","Remboursement"]),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_opposition_prelevement:{ic:"🏦",n:"Opposition à prélèvement",cat:"fr_conso",tier:"einfach",q:[Pf("banque","Banque : nom & adresse",true),Pf("creancier","Créancier concerné",true),Pf("num_mandat","Référence du mandat (optionnel)",false),Pf("motif","Motif de l'opposition",true),Pf("coordonnees","Vos nom, adresse & IBAN",true)]},
    /* Administratif */
    fr_attestation_honneur:{ic:"✍️",n:"Attestation sur l'honneur",cat:"fr_admin",tier:"einfach",q:[Pf("objet","Objet de l'attestation",true),Pf("declaration","Ce que vous attestez (détaillez)",true),Pf("lieu","Fait à (ville)",false),Pf("coordonnees","Vos nom, prénom & adresse",true)]},
    fr_reconnaissance_dette:{ic:"💶",n:"Reconnaissance de dette",cat:"fr_admin",tier:"standard",q:[Pf("creancier","Créancier (à qui vous devez) : nom & adresse",true),Pf("montant_chiffres","Montant en chiffres (€)",true),Pf("montant_lettres","Montant en toutes lettres",true),Pf("motif","Motif (optionnel)",false),Pf("date_remboursement","Date de remboursement prévue",true),Pf("interets","Intérêts ?",false,["Sans intérêt","Avec intérêt"]),Pf("coordonnees","Vos nom, prénom & adresse",true)]},
    fr_procuration:{ic:"📝",n:"Procuration",cat:"fr_admin",tier:"einfach",q:[Pf("mandataire","Mandataire (qui agit pour vous) : nom & adresse",true),Pf("objet","Objet de la procuration (pour quoi)",true),Pf("duree","Durée / date (optionnel)",false),Pf("lieu","Fait à (ville)",false),Pf("coordonnees","Vos (mandant) nom & adresse",true)]},
    fr_recours_administratif:{ic:"🏛️",n:"Recours administratif (RAPO)",cat:"fr_admin",tier:"komplex",q:[Pf("administration","Administration concernée",true),Pf("decision","Décision contestée",true),Pf("date_decision","Date de notification",true),Pf("type","Type de recours",false,["Gracieux","Hiérarchique"]),Pf("motifs","Motifs (faits & droit)",true),Pf("demande","Votre demande",true),Pf("coordonnees","Vos nom & adresse",true)]},
/* Travail & Emploi */
    fr_cdi:{ic:"📄",n:"Contrat de travail CDI",cat:"fr_travail",tier:"komplex",q:[Pf("role","Vous êtes",true,["Employeur","Salarié"]),Pf("autre_partie","Autre partie : nom & adresse",true),Pf("poste","Poste / intitulé",true),Pf("qualification","Qualification / classification",false),Pf("date_debut","Date de début",true),Pf("lieu_travail","Lieu de travail",true),Pf("duree_hebdo","Durée hebdomadaire",true,["35h (temps plein)","Temps partiel","Forfait jours"]),Pf("remuneration","Rémunération brute mensuelle (€)",true),Pf("periode_essai","Période d'essai",false,["Aucune","1 mois","2 mois","3 mois","4 mois"]),Pf("convention","Convention collective (le cas échéant)",false)]},
    fr_cdd:{ic:"📄",n:"Contrat de travail CDD",cat:"fr_travail",tier:"komplex",q:[Pf("role","Vous êtes",true,["Employeur","Salarié"]),Pf("autre_partie","Autre partie : nom & adresse",true),Pf("poste","Poste / intitulé",true),Pf("motif","Motif de recours au CDD",true,["Remplacement d'un salarié","Accroissement d'activité","Emploi saisonnier","CDD d'usage"]),Pf("date_debut","Date de début",true),Pf("date_fin","Date de fin (ou terme)",true),Pf("duree_hebdo","Durée hebdomadaire",true,["35h (temps plein)","Temps partiel"]),Pf("remuneration","Rémunération brute mensuelle (€)",true),Pf("periode_essai","Période d'essai (le cas échéant)",false)]},
    fr_apprentissage:{ic:"🎓",n:"Contrat d'apprentissage",cat:"fr_travail",tier:"standard",q:[Pf("employeur","Employeur : nom & adresse",true),Pf("apprenti","Apprenti : nom & date de naissance",true),Pf("diplome","Diplôme préparé",true),Pf("cfa","CFA / établissement",true),Pf("date_debut","Date de début",true),Pf("duree","Durée du contrat",true),Pf("remuneration","Rémunération (% du SMIC, optionnel)",false)]},
    fr_attestation_travail:{ic:"📄",n:"Attestation de travail",cat:"fr_travail",tier:"einfach",q:[Pf("employeur","Employeur : nom & adresse",true),Pf("salarie","Salarié : nom",true),Pf("poste","Poste occupé",true),Pf("date_debut","Date de début",true),Pf("date_fin","Date de fin (si terminé)",false),Pf("temps","Temps de travail",false,["Temps plein","Temps partiel"])]},
    fr_demande_augmentation:{ic:"📈",n:"Demande d'augmentation de salaire",cat:"fr_travail",tier:"einfach",q:[Pf("employeur","Employeur / responsable",true),Pf("poste","Votre poste",true),Pf("anciennete","Ancienneté",false),Pf("salaire_actuel","Salaire actuel (€)",false),Pf("arguments","Vos arguments (résultats, responsabilités…)",true),Pf("souhait","Augmentation souhaitée (optionnel)",false)]},
    fr_contestation_sanction:{ic:"⚖️",n:"Contestation de sanction disciplinaire",cat:"fr_travail",tier:"standard",q:[Pf("employeur","Employeur : nom & adresse",true),Pf("sanction","Sanction prononcée",true),Pf("date_sanction","Date de la sanction",true),Pf("faits","Faits reprochés",true),Pf("raisons","Raisons de votre contestation",true)]},
    fr_arret_maladie:{ic:"🏥",n:"Information d'arrêt maladie (employeur)",cat:"fr_travail",tier:"einfach",q:[Pf("employeur","Employeur : nom & adresse",true),Pf("date_debut","Date de début de l'arrêt",true),Pf("duree","Durée prévue",true),Pf("coordonnees","Vos nom & poste",true)]},
    /* Logement & Bail */
    fr_quittance_loyer:{ic:"🧾",n:"Quittance de loyer",cat:"fr_logement",tier:"einfach",q:[Pf("locataire","Locataire : nom",true),Pf("adresse_logement","Adresse du logement",true),Pf("mois","Mois concerné",true),Pf("loyer","Montant du loyer (€)",true),Pf("charges","Montant des charges (€, optionnel)",false)]},
    fr_demande_travaux:{ic:"🔧",n:"Demande de travaux au bailleur",cat:"fr_logement",tier:"standard",q:[Pf("bailleur","Bailleur : nom & adresse",true),Pf("adresse_logement","Adresse du logement",true),Pf("travaux","Travaux demandés (détaillez)",true),Pf("urgence","Caractère urgent ?",false,["Oui","Non"])]},
    fr_acte_caution:{ic:"✍️",n:"Acte de cautionnement (garant)",cat:"fr_logement",tier:"standard",q:[Pf("bailleur","Bailleur : nom & adresse",true),Pf("locataire","Locataire garanti : nom",true),Pf("adresse_logement","Adresse du logement",true),Pf("loyer","Loyer mensuel (€)",true),Pf("type_caution","Type de caution",true,["Simple","Solidaire"]),Pf("duree","Durée de l'engagement",false),Pf("coordonnees","Vos (garant) nom & adresse",true)]},
    fr_contest_augmentation_loyer:{ic:"💶",n:"Contestation d'augmentation de loyer",cat:"fr_logement",tier:"standard",q:[Pf("bailleur","Bailleur : nom & adresse",true),Pf("adresse_logement","Adresse du logement",true),Pf("ancien_loyer","Ancien loyer (€)",true),Pf("nouveau_loyer","Nouveau loyer demandé (€)",true),Pf("raisons","Raisons de la contestation",true)]},
    fr_conge_bailleur:{ic:"🏠",n:"Congé du bailleur (vente / reprise)",cat:"fr_logement",tier:"komplex",q:[Pf("locataire","Locataire : nom & adresse",true),Pf("adresse_logement","Adresse du logement",true),Pf("motif","Motif du congé",true,["Vente","Reprise pour habiter","Motif légitime et sérieux"]),Pf("date_effet","Date d'effet (fin de bail)",true),Pf("coordonnees","Vos (bailleur) nom & adresse",true)]},
    /* Litiges & Réclamations */
    fr_plainte:{ic:"🚓",n:"Plainte (procureur de la République)",cat:"fr_litige",tier:"komplex",q:[Pf("faits","Faits (que s'est-il passé ?)",true),Pf("date_faits","Date des faits",true),Pf("lieu","Lieu des faits",true),Pf("auteur","Auteur",false,["Connu","Inconnu"]),Pf("prejudice","Préjudice subi",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_injonction_payer:{ic:"💶",n:"Demande d'injonction de payer",cat:"fr_litige",tier:"komplex",q:[Pf("debiteur","Débiteur : nom & adresse",true),Pf("montant","Montant réclamé (€)",true),Pf("origine","Origine de la créance",true),Pf("factures","Références factures (optionnel)",false),Pf("mises_en_demeure","Mises en demeure déjà envoyées ?",false,["Oui","Non"]),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_reponse_huissier:{ic:"📨",n:"Réponse à un huissier / commissaire de justice",cat:"fr_litige",tier:"standard",q:[Pf("huissier","Étude / huissier : nom & adresse",true),Pf("ref_dossier","Référence du dossier",true),Pf("objet","Objet du courrier reçu",true),Pf("position","Votre position",true,["Contestation","Demande de délai","Proposition de paiement"]),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_mediateur:{ic:"🤝",n:"Saisine du médiateur",cat:"fr_litige",tier:"standard",q:[Pf("organisme","Médiateur / organisme",true),Pf("litige","Objet du litige",true),Pf("demarches","Démarches déjà effectuées",true),Pf("demande","Votre demande",true),Pf("coordonnees","Vos nom & adresse",true)]},
    /* Consommation */
    fr_opposition_cheque:{ic:"🏦",n:"Opposition sur chèque",cat:"fr_conso",tier:"einfach",q:[Pf("banque","Banque : nom & adresse",true),Pf("num_cheque","Numéro du chèque (optionnel)",false),Pf("montant","Montant (€, optionnel)",false),Pf("motif","Motif",true,["Perte","Vol","Utilisation frauduleuse"]),Pf("coordonnees","Vos nom, adresse & n° de compte",true)]},
    fr_remb_transport:{ic:"🚆",n:"Remboursement de billet (SNCF / avion)",cat:"fr_conso",tier:"einfach",q:[Pf("transporteur","Transporteur : nom",true),Pf("ref_billet","Référence du billet / dossier",true),Pf("date_voyage","Date du voyage",true),Pf("motif","Motif",true,["Retard","Annulation","Grève"]),Pf("montant","Montant (€, optionnel)",false),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_garantie_legale:{ic:"🔧",n:"Garantie légale de conformité",cat:"fr_conso",tier:"standard",q:[Pf("vendeur","Vendeur : nom & adresse",true),Pf("produit","Produit concerné",true),Pf("date_achat","Date d'achat",true),Pf("defaut","Défaut constaté",true),Pf("demande","Votre demande",true,["Réparation","Remplacement","Remboursement"]),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_retract_demarchage:{ic:"🚫",n:"Rétractation après démarchage",cat:"fr_conso",tier:"einfach",q:[Pf("societe","Société : nom & adresse",true),Pf("contrat_ref","Référence du contrat (optionnel)",false),Pf("date_signature","Date de signature / souscription",true),Pf("coordonnees","Vos nom & adresse",true)]},
    /* Administratif */
    fr_acte_naissance:{ic:"📜",n:"Demande d'acte de naissance",cat:"fr_admin",tier:"einfach",q:[Pf("mairie","Mairie du lieu de naissance",true),Pf("personne","Personne concernée : nom",true),Pf("date_naissance","Date de naissance",true),Pf("type","Type d'acte",true,["Copie intégrale","Extrait avec filiation","Extrait sans filiation"]),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_changement_adresse:{ic:"🏠",n:"Déclaration de changement d'adresse",cat:"fr_admin",tier:"einfach",q:[Pf("organisme","Organisme destinataire (CAF, Impôts, CPAM…)",true),Pf("ancienne_adresse","Ancienne adresse",true),Pf("nouvelle_adresse","Nouvelle adresse",true),Pf("date","Date du déménagement",true),Pf("coordonnees","Vos nom & références",true)]},
    fr_logement_social:{ic:"🏢",n:"Demande de logement social",cat:"fr_admin",tier:"standard",q:[Pf("organisme","Bailleur social / mairie",true),Pf("situation","Situation familiale",true),Pf("revenus","Revenus mensuels du foyer (€)",true),Pf("composition","Composition du foyer",true),Pf("secteur","Secteur / commune souhaitée",false),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_courrier_caf:{ic:"💶",n:"Courrier CAF (réclamation / demande)",cat:"fr_admin",tier:"standard",q:[Pf("objet","Objet du courrier",true),Pf("num_allocataire","N° allocataire (optionnel)",false),Pf("situation","Votre situation",true),Pf("demande","Votre demande",true),Pf("coordonnees","Vos nom & adresse",true)]},
    fr_attestation_hebergement:{ic:"🏠",n:"Attestation d'hébergement",cat:"fr_admin",tier:"einfach",q:[Pf("heberge","Personne hébergée : nom",true),Pf("adresse","Adresse du domicile",true),Pf("depuis","Hébergée depuis (date)",true),Pf("coordonnees","Vos (hébergeant) nom & coordonnées",true)]},
    fr_autorisation_sortie:{ic:"✈️",n:"Autorisation de sortie du territoire (mineur)",cat:"fr_admin",tier:"einfach",q:[Pf("enfant","Enfant : nom & prénom",true),Pf("naissance_enfant","Date de naissance de l'enfant",true),Pf("titulaire","Titulaire de l'autorité parentale : nom",true),Pf("destination","Destination (optionnel)",false),Pf("dates","Dates du voyage (optionnel)",false)]}
  };

  function inject(){
    try{
      if(typeof DOCS==="undefined"||!DOCS) return false;
      for(var k in F){ if(!DOCS[k]) DOCS[k]=F[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=F[k].tier||"standard"; }
      if(typeof CATS!=="undefined"&&CATS){
        for(var ck in FRC){ if(!CATS[ck]) CATS[ck]={n:FRC[ck].n,ic:FRC[ck].ic,docs:[],country:"FR"}; if(!CATS[ck].docs) CATS[ck].docs=[]; }
        for(var k2 in F){ var c2=F[k2].cat; if(CATS[c2]&&CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); }
        for(var gc in CATS){ if(gc.indexOf("fr_")!==0 && !CATS[gc].country) CATS[gc].country="DE"; }
      }
      if(typeof CAT_LABELS!=="undefined"){ for(var cl in FRC) CAT_LABELS[cl]=FRC[cl].n; }
      if(typeof CAT_DOCS!=="undefined"){
        for(var cd in FRC){ if(!CAT_DOCS[cd]) CAT_DOCS[cd]=[]; }
        for(var k3 in F){ var c3=F[k3].cat; if(CAT_DOCS[c3]){ var ex=false; for(var i=0;i<CAT_DOCS[c3].length;i++){ if(CAT_DOCS[c3][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[c3].push({k:k3,ic:F[k3].ic,t:F[k3].n,tier:F[k3].tier||"standard"}); } }
      }
      return (typeof CATS!=="undefined"&&CATS&&CATS.fr_resiliation&&CATS.fr_resiliation.docs.length>0);
    }catch(e){ return false; }
  }

  function langSync(){
    try{
      if(typeof CC!=="undefined" && CC==="FR"){
        if(localStorage.getItem("ch_uilang")!=="fr"){ localStorage.setItem("ch_uilang","fr"); }
        try{ if(typeof UL!=="undefined") UL="fr"; }catch(e){}
        try{ if(typeof dLang!=="undefined") dLang="fr"; }catch(e){}
      }
    }catch(e){}
  }
  function hookSC(){
    try{
      if(typeof setCountry!=="function" || setCountry.__frfix) return;
      var _o=setCountry;
      var w=function(cc){ var r; try{ r=_o.apply(this,arguments);}catch(e){} try{ if(cc==="FR"){ localStorage.setItem("ch_uilang","fr"); } }catch(e){} return r; };
      w.__frfix=1; setCountry=w; if(typeof window!=="undefined") window.setCountry=w;
    }catch(e){}
  }

  var done=false, ticks=0;
  function run(){
    if(!done){ if(inject()) done=true; }
    hookSC(); langSync();
    if(ticks++<40) setTimeout(run, 400);
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}

CHJS;
$src=str_replace($anchor,$anchor.$js,$src);

if($src!==$start){ file_put_contents($file,$src); echo "Fransa fix eklendi (CH_FR_FIX) - ~53 belge deferred yuklenir + dil senkronu.\n"; }
else echo "degisiklik yok\n";
echo "\nSONRA: opcache-reset.php. SIL: rm apply-france-fix.php\n";
echo "Test: FR France -> kategoriler X documents (>0) -> tikla -> Fransiz belgeler -> arayuz tamamen Fransizca.\n";
