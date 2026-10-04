<?php
/** ChatHelp — apply-be-expand-more (CH_BE_EXPAND_MORE) — BE katalog genisletme
 *  Workflow ile uretildi: +16 belge, 4 kategori. Deferred injection.
 *  KULLANIM: pull-updates.php?files=apply-be-expand-more.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-be-expand-more BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_BE_EXPAND_MORE')!==false) exit("Zaten ekli (CH_BE_EXPAND_MORE).\n");
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if (strpos($src,$anchor)===false) exit("HATA: anchor (getDocPrice) yok.\n");
$js = <<<'CHJS'

/* CH_BE_EXPAND_MORE — BE katalog genisletme (workflow, deferred) */
try{(function(){
  function Pe(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var NEWCATS={
    "be_famille":{n:"Famille & Successions",ic:"👨‍👩‍👧"},
    "be_dettes":{n:"Dettes & Recouvrement",ic:"💰"},
    "be_donnees":{n:"Données personnelles (RGPD)",ic:"🔐"},
    "be_voisinage":{n:"Voisinage & Copropriété",ic:"🏘️"}
  };
  var E={
    "bex_divorce_consentement":{ic:"💔",n:"Conventions préalables au divorce par consentement mutuel",cat:"be_famille",tier:"komplex",q:[Pe("epoux1","Époux/épouse 1 : nom, prénom, date de naissance et adresse",true),Pe("epoux2","Époux/épouse 2 : nom, prénom, date de naissance et adresse",true),Pe("mariage","Date et lieu du mariage ; régime matrimonial",true),Pe("enfants","Enfants mineurs communs (noms, dates de naissance) — le cas échéant",false),Pe("hebergement","Modalités d'hébergement et contribution alimentaire pour les enfants",false),Pe("pension_epoux","Pension alimentaire entre époux après le divorce (montant, durée) — le cas échéant",false),Pe("biens","Partage des biens et du logement (immeuble, comptes, attribution)",true)]},
    "bex_pension_alimentaire":{ic:"🧒",n:"Demande / révision de pension alimentaire",cat:"be_famille",tier:"standard",q:[Pe("debiteur","Débiteur : parent tenu à la contribution (nom et adresse)",true),Pe("beneficiaire","Enfant(s) / bénéficiaire(s) concerné(s)",true),Pe("objet","Objet de la demande",true,["Fixation d'une pension","Révision à la hausse","Révision à la baisse","Indexation"]),Pe("situation","Besoins de l'enfant / changement de situation (frais scolaires, médicaux, revenus)",true),Pe("montant","Montant mensuel demandé (€)",false),Pe("coordonnees","Vos nom, prénom et adresse",true)]},
    "bex_cohabitation_legale":{ic:"💞",n:"Déclaration de cohabitation légale",cat:"be_famille",tier:"einfach",q:[Pe("partenaire","Cohabitant : nom, prénom et date de naissance",true),Pe("adresse","Adresse du domicile commun",true),Pe("commune","Commune (officier de l'état civil) où la déclaration est faite",true),Pe("convention","Souhaitez-vous une convention de cohabitation (règlement du patrimoine) ?",false,["Oui","Non"]),Pe("coordonnees","Vos nom, prénom et date de naissance",true)]},
    "bex_testament_olographe":{ic:"📜",n:"Testament olographe",cat:"be_famille",tier:"standard",q:[Pe("testateur","Testateur : nom, prénom, date et lieu de naissance, adresse",true),Pe("legataires","Bénéficiaires (héritiers/légataires) et ce que vous léguez à chacun",true),Pe("biens","Biens particuliers légués (immeuble, comptes, objets)",false),Pe("reserve","Avez-vous des enfants (héritiers réservataires) ?",true,["Oui","Non"]),Pe("executeur","Exécuteur testamentaire (optionnel)",false),Pe("lieu_date","Lieu et date de rédaction",true)]},
    "bex_renonciation_succession":{ic:"🚫",n:"Renonciation à succession",cat:"be_famille",tier:"standard",q:[Pe("defunt","Défunt : nom, prénom et date du décès",true),Pe("lien","Votre lien de parenté avec le défunt",true),Pe("notaire","Notaire chargé de la succession (le cas échéant)",false),Pe("motif","Motif de la renonciation (succession déficitaire, dettes) — optionnel",false),Pe("coordonnees","Vos nom, prénom, date de naissance et adresse",true)]},
    "bex_plan_apurement":{ic:"🗓️",n:"Demande de plan d'apurement (échelonnement de dette)",cat:"be_dettes",tier:"einfach",q:[Pe("creancier","Créancier : nom et adresse",true),Pe("reference","Référence de la dette / n° de facture",true),Pe("montant","Montant total dû (€)",true),Pe("proposition","Proposition d'échelonnement (mensualités, durée)",true),Pe("situation","Votre situation financière (revenus, charges) — optionnel",false),Pe("coordonnees","Vos nom, prénom et adresse",true)]},
    "bex_contestation_recouvrement":{ic:"✋",n:"Contestation d'un recouvrement amiable (Livre XIX CDE)",cat:"be_dettes",tier:"standard",q:[Pe("societe","Société de recouvrement / créancier : nom et adresse",true),Pe("reference","Référence du dossier de recouvrement",true),Pe("dette","Dette réclamée (montant et origine)",true),Pe("motifs","Motifs de contestation",true,["Dette inexistante ou déjà payée","Montant / frais contestés","Dette prescrite","Absence de justificatifs","Autre"]),Pe("coordonnees","Vos nom, prénom et adresse",true)]},
    "bex_reglement_collectif_dettes":{ic:"⚖️",n:"Requête en règlement collectif de dettes",cat:"be_dettes",tier:"komplex",q:[Pe("identite","Vos nom, prénom, date de naissance, adresse et n° de registre national",true),Pe("situation_pro","Situation professionnelle et revenus mensuels",true),Pe("dettes","Liste des dettes et créanciers (montants)",true),Pe("charges","Charges mensuelles (loyer, énergie, famille)",true),Pe("patrimoine","Patrimoine (immeuble, véhicule, comptes) — optionnel",false),Pe("cause","Cause du surendettement (perte d'emploi, maladie, crédits) — optionnel",false)]},
    "bex_delai_grace":{ic:"⏳",n:"Demande de délai de grâce (termes et délais)",cat:"be_dettes",tier:"standard",q:[Pe("creancier","Créancier : nom et adresse",true),Pe("dette","Dette concernée (montant, échéance)",true),Pe("difficulte","Difficulté financière (cause, caractère temporaire)",true),Pe("demande","Délai ou échelonnement sollicité",true),Pe("coordonnees","Vos nom, prénom et adresse",true)]},
    "bex_rgpd_acces":{ic:"🔎",n:"Demande d'accès à vos données (art. 15 RGPD)",cat:"be_donnees",tier:"einfach",q:[Pe("responsable","Organisme (responsable du traitement) : nom et adresse",true),Pe("relation","Votre relation avec l'organisme (client, employé, abonné…)",true),Pe("donnees","Données souhaitées (toutes, ou catégories précises)",false),Pe("format","Format souhaité pour la copie",false,["Électronique","Papier"]),Pe("identite","Vos nom, prénom et coordonnées (pièce d'identité jointe)",true)]},
    "bex_rgpd_effacement":{ic:"🧹",n:"Demande d'effacement / droit à l'oubli (art. 17 RGPD)",cat:"be_donnees",tier:"einfach",q:[Pe("responsable","Organisme (responsable du traitement) : nom et adresse",true),Pe("donnees","Données dont vous demandez l'effacement",true),Pe("motif","Motif de l'effacement",true,["Retrait du consentement","Données non nécessaires","Traitement illicite","Opposition au traitement","Autre"]),Pe("identite","Vos nom, prénom et coordonnées",true)]},
    "bex_plainte_apd":{ic:"🛡️",n:"Plainte à l'Autorité de protection des données (APD)",cat:"be_donnees",tier:"standard",q:[Pe("entreprise","Organisme mis en cause : nom et adresse",true),Pe("faits","Faits reprochés (violation constatée, dates)",true),Pe("demarches","Démarches préalables auprès de l'organisme (demande restée sans suite…)",true),Pe("preuves","Preuves disponibles (courriels, captures) — optionnel",false),Pe("demande","Ce que vous demandez à l'APD",false),Pe("coordonnees","Vos nom, prénom et coordonnées",true)]},
    "bex_troubles_voisinage":{ic:"🔇",n:"Mise en demeure pour troubles de voisinage",cat:"be_voisinage",tier:"standard",q:[Pe("voisin","Voisin concerné : nom et adresse",true),Pe("nuisance","Nature du trouble",true,["Bruit","Odeurs / fumées","Vue / empiètement","Eaux / humidité","Animaux","Autre"]),Pe("description","Description des faits et de leur fréquence",true),Pe("prejudice","Préjudice subi (trouble excédant les inconvénients normaux du voisinage)",true),Pe("demande","Ce que vous demandez (cesser, réparer, indemniser)",true),Pe("coordonnees","Vos nom, prénom et adresse",true)]},
    "bex_mitoyennete":{ic:"🌳",n:"Courrier relatif à la mitoyenneté / plantations",cat:"be_voisinage",tier:"einfach",q:[Pe("voisin","Voisin : nom et adresse",true),Pe("objet","Objet",true,["Haie / plantations trop proches","Mur ou clôture mitoyen","Branches / racines débordantes","Entretien de la limite","Autre"]),Pe("situation","Description (distances, débordements…)",true),Pe("demande","Votre demande (élagage, recul, participation aux frais)",true),Pe("coordonnees","Vos nom, prénom et adresse",true)]},
    "bex_copro_convocation_ag":{ic:"🏢",n:"Demande d'inscription d'un point à l'ordre du jour (copropriété)",cat:"be_voisinage",tier:"standard",q:[Pe("syndic","Syndic : nom et adresse",true),Pe("residence","Résidence / immeuble et n° de lot",true),Pe("objet","Objet de la demande",true,["Inscrire un point à l'ordre du jour","Convoquer une AG extraordinaire","Obtenir des documents de copropriété"]),Pe("point","Point / question à soumettre à l'assemblée générale",true),Pe("coordonnees","Vos nom, prénom (copropriétaire) et coordonnées",true)]},
    "bex_copro_contestation":{ic:"🗳️",n:"Contestation d'une décision de l'assemblée générale des copropriétaires",cat:"be_voisinage",tier:"komplex",q:[Pe("syndic","Syndic / association des copropriétaires : nom et adresse",true),Pe("residence","Résidence et n° de lot",true),Pe("ag_date","Date de l'assemblée générale contestée",true),Pe("decision","Décision contestée (point de l'ordre du jour)",true),Pe("motifs","Motifs",true,["Irrégularité de procédure","Décision abusive","Décision frauduleuse","Majorité non atteinte","Autre"]),Pe("coordonnees","Vos nom, prénom et coordonnées",true)]}
  };
  function inject(){
    try{
      if(typeof DOCS==="undefined"||!DOCS) return false;
      if(typeof CATS==="undefined"||!CATS) return false;
      for(var k in E){ if(!DOCS[k]) DOCS[k]=E[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=E[k].tier||"standard"; }
      for(var nc in NEWCATS){ if(!CATS[nc]) CATS[nc]={n:NEWCATS[nc].n,ic:NEWCATS[nc].ic,docs:[],country:"BE"}; if(!CATS[nc].docs) CATS[nc].docs=[]; }
      for(var k2 in E){ var c2=E[k2].cat; if(CATS[c2]&&CATS[c2].docs&&CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); }
      if(typeof CAT_LABELS!=="undefined"){ for(var cl in NEWCATS) CAT_LABELS[cl]=NEWCATS[cl].n; }
      if(typeof CAT_DOCS!=="undefined"){
        for(var cd in NEWCATS){ if(!CAT_DOCS[cd]) CAT_DOCS[cd]=[]; }
        for(var k3 in E){ var c3=E[k3].cat; if(CAT_DOCS[c3]){ var ex=false; for(var i=0;i<CAT_DOCS[c3].length;i++){ if(CAT_DOCS[c3][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[c3].push({k:k3,ic:E[k3].ic,t:E[k3].n,tier:E[k3].tier||"standard"}); } }
      }
      return !!(DOCS.bex_divorce_consentement);
    }catch(e){ return false; }
  }
  var done=false, ticks=0;
  function run(){ if(!done){ if(inject()) done=true; } if(ticks++<40) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}

CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
$tmp = tempnam(sys_get_temp_dir(),'be').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-bemore-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "  ✓ BE: +16 belge, 4 kategori eklendi\n\n✓ CH_BE_EXPAND_MORE uygulandi.\n";
