<?php
/**
 * ChatHelp — FRANSA KATALOG GENİŞLETME (+27 belge: CDI/CDD + Fransa'nın ihtiyaçları)
 * ----------------------------------------------------------------------------------
 *  İlk Fransa modülüne (CH_FR_INLINE) ek olarak, aynı scope'a daha fazla Fransız belgesi
 *  enjekte eder. Mevcut 6 kategoriye eklenir (Travail/Logement/Litige/Conso/Admin/Résil.).
 *  -> Toplam ~55 Fransız belge; Almanca yapısının dengi.
 *
 * KULLANIM: chat-help.com/chat/apply-france-plus.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
echo "ChatHelp — apply-france-plus BAŞLADI ✓ (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src = file_get_contents($file);
$start = $src;

$anchor = "function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if (strpos($src, 'CH_FR_INLINE2') !== false) { exit("Zaten ekli (CH_FR_INLINE2). Değişiklik yok.\n"); }
if (strpos($src, $anchor) === false) { exit("✗ anchor (getDocPrice) bulunamadı.\n"); }
file_put_contents($file . '.bak-frplus-' . date('Ymd-His'), $src);

$js = <<<'JS'

/* CH_FR_INLINE2 — Fransa katalog genişletme (aynı scope) */
try{(function(){
  function Pf(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var F={
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
  for(var k in F){ if(typeof DOCS!=='undefined' && !DOCS[k]) DOCS[k]=F[k]; if(typeof DOC_TIER!=='undefined') DOC_TIER[k]=F[k].tier||'standard'; }
  var FRCATS={fr_resiliation:{n:"Résiliations",ic:"📄"},fr_logement:{n:"Logement & Bail",ic:"🏠"},fr_travail:{n:"Travail & Emploi",ic:"💼"},fr_litige:{n:"Litiges & Réclamations",ic:"⚖️"},fr_conso:{n:"Consommation",ic:"🛒"},fr_admin:{n:"Administratif",ic:"🏛️"}};
  if(typeof CATS!=='undefined'){
    for(var ck in FRCATS){ if(!CATS[ck]) CATS[ck]={n:FRCATS[ck].n,ic:FRCATS[ck].ic,docs:[],country:'FR'}; }
    for(var k2 in F){ var c2=F[k2].cat; if(CATS[c2]){ if(!CATS[c2].docs) CATS[c2].docs=[]; if(CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); } }
  }
})();}catch(e){}
JS;

$src = str_replace($anchor, $anchor . $js, $src);

if ($src !== $start) {
    file_put_contents($file, $src);
    echo "✓ Fransa kataloğu genişletildi (CH_FR_INLINE2).  → +27 belge\n";
    echo "  • Travail: CDI, CDD, apprentissage, attestation, augmentation, sanction, arrêt maladie\n";
    echo "  • Logement: quittance, travaux, cautionnement, contestation loyer, congé bailleur\n";
    echo "  • Litige: plainte, injonction de payer, huissier, médiateur\n";
    echo "  • Conso: opposition chèque, remb. transport, garantie légale, démarchage\n";
    echo "  • Admin: acte de naissance, changement d'adresse, logement social, CAF, hébergement, AST\n";
    echo "\nToplam Fransız belge: ~55. Hepsi tier'lı -> Stripe ödeme otomatik çalışır.\n";
} else {
    echo "✗ Değişiklik uygulanamadı.\n";
}

echo "\nSONRA: opcache-reset.php. SİL: rm apply-france-plus.php\n";
echo "Test: 🇫🇷 -> Travail & Emploi -> 'Contrat de travail CDI' -> üret.\n";
