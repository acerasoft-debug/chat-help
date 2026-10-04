<?php
/**
 * ChatHelp — BELÇİKA MODÜLÜ (~36 belge, 6 kategori, Fransızca, Belçika hukuku) — CH_TR_MODULE ile aynı kanıtlanmış desen
 * -------------------------------------------------------------------------------------------------------
 *  • Kategoriler country:'BE' etiketli -> genelleştirilmiş ülke filtresi (CH_TR_MODULE) otomatik çalışır
 *  • Welcome grid kartları data-cccountry='BE' -> kart görünürlüğü otomatik
 *  • Dil: setCountry('BE') zaten UL='fr' yapıyor (CC_DATA.BE.l) — ek dil kodu gerekmez
 *  • Belge dili/hukuku: api.php'de lawSystems['BE'] + doc-lang BE->fr (apply-lawsys-all.php ile)
 *  • Belçika özellikleri: bail 3-6-9, garantie locative, ONEM, mutuelle, commune, SPF Finances, SAC
 *
 * KULLANIM: pull-updates.php?files=apply-belgique.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-belgique BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,"CH_BE_MODULE")!==false) exit("Zaten ekli (CH_BE_MODULE).\n");
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-be-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_BE_MODULE — Belçika hukuku modülü (deferred; DOCS/CATS hazır olunca yüklenir) */
try{(function(){
  function Pq(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var XC={
    be_resiliations:{n:"Résiliations",ic:"📄"},
    be_logement:{n:"Logement & Bail",ic:"🏠"},
    be_travail:{n:"Travail & Emploi",ic:"💼"},
    be_conso:{n:"Consommation & Litiges",ic:"🛒"},
    be_contrats:{n:"Contrats",ic:"📝"},
    be_admin:{n:"Administrations",ic:"🏛️"}
  };
  var X_ORDER=["be_resiliations","be_logement","be_travail","be_conso","be_contrats","be_admin"];
  var CCODE="BE", DOCWORD="documents";

  var D={
    /* Résiliations */
    be_resiliation_energie:{ic:"⚡",n:"Résiliation de contrat d'énergie (électricité/gaz)",cat:"be_resiliations",tier:"einfach",q:[Pq("fournisseur","Fournisseur : nom et adresse",true),Pq("num_client","N° de client / code EAN",true),Pq("adresse","Adresse du point de fourniture",true),Pq("date","Date de résiliation souhaitée",true),Pq("coordonnees","Vos nom, prénom et adresse",true)]},
    be_resiliation_telecom:{ic:"📱",n:"Résiliation télécom / internet",cat:"be_resiliations",tier:"einfach",q:[Pq("operateur","Opérateur : nom",true),Pq("num_client","N° de client / de ligne",true),Pq("engagement","Avez-vous une période d'engagement ?",false,["Non","Oui","Je ne sais pas"]),Pq("date","Date de résiliation souhaitée",false),Pq("coordonnees","Vos nom, prénom et adresse",true)]},
    be_resiliation_salle_sport:{ic:"🏋️",n:"Résiliation d'abonnement de salle de sport",cat:"be_resiliations",tier:"einfach",q:[Pq("club","Club : nom et adresse",true),Pq("num_membre","N° de membre",false),Pq("date","Date de résiliation",true),Pq("motif","Motif (optionnel)",false),Pq("coordonnees","Vos nom et prénom",true)]},
    be_resiliation_assurance:{ic:"🛡️",n:"Résiliation de contrat d'assurance",cat:"be_resiliations",tier:"einfach",q:[Pq("assureur","Assureur : nom et adresse",true),Pq("num_police","N° de police",true),Pq("type","Type d'assurance",true,["Auto","Habitation/Incendie","Familiale (RC)","Hospitalisation","Autre"]),Pq("echeance","Date d'échéance annuelle (préavis 3 mois, loi Verwilghen)",true),Pq("coordonnees","Vos nom, prénom et adresse",true)]},
    be_cloture_compte:{ic:"🏦",n:"Clôture de compte bancaire",cat:"be_resiliations",tier:"einfach",q:[Pq("banque","Banque : nom et agence",true),Pq("iban","IBAN du compte à clôturer",true),Pq("transfert","IBAN pour le transfert du solde (optionnel)",false),Pq("coordonnees","Vos nom, prénom et adresse",true)]},
    be_resiliation_abonnement:{ic:"📺",n:"Résiliation d'abonnement numérique",cat:"be_resiliations",tier:"einfach",q:[Pq("plateforme","Plateforme (Netflix, Spotify…)",true),Pq("compte","E-mail du compte",true),Pq("date","Date de résiliation (optionnel)",false),Pq("coordonnees","Vos nom et prénom",true)]},

    /* Logement & Bail */
    be_resiliation_bail:{ic:"🏠",n:"Résiliation de bail (locataire)",cat:"be_logement",tier:"einfach",q:[Pq("bailleur","Bailleur/agence : nom et adresse",true),Pq("adresse","Adresse du logement loué",true),Pq("date_sortie","Date de fin souhaitée (préavis 3 mois, bail 3-6-9)",true),Pq("coordonnees","Vos nom, prénom et adresse",true)]},
    be_garantie_locative:{ic:"💶",n:"Restitution de la garantie locative",cat:"be_logement",tier:"standard",q:[Pq("bailleur","Bailleur : nom et adresse",true),Pq("adresse","Adresse du logement",true),Pq("date_sortie","Date de remise des clés / état des lieux de sortie",true),Pq("montant","Montant de la garantie locative (€)",true),Pq("compte","Compte bloqué : banque et n° (optionnel)",false),Pq("coordonnees","Vos nom et prénom",true)]},
    be_etat_des_lieux:{ic:"📋",n:"Contestation de l'état des lieux",cat:"be_logement",tier:"standard",q:[Pq("bailleur","Bailleur : nom et adresse",true),Pq("adresse","Adresse du logement",true),Pq("type","État des lieux contesté",true,["Entrée","Sortie"]),Pq("points","Points contestés (dégâts imputés, vétusté…)",true),Pq("coordonnees","Vos nom et prénom",true)]},
    be_defauts_logement:{ic:"🔧",n:"Signalement de défauts au bailleur",cat:"be_logement",tier:"standard",q:[Pq("bailleur","Bailleur : nom et adresse",true),Pq("adresse","Adresse du logement",true),Pq("probleme","Description du défaut / de la panne (humidité, chauffage…)",true),Pq("urgence","Est-ce urgent ?",false,["Oui","Non"]),Pq("coordonnees","Vos nom et prénom",true)]},
    be_contrat_bail:{ic:"📄",n:"Contrat de bail de résidence principale (3-6-9)",cat:"be_logement",tier:"komplex",q:[Pq("role","Vous êtes",true,["Bailleur","Locataire"]),Pq("autre_partie","Autre partie : nom et adresse",true),Pq("adresse","Adresse du logement",true),Pq("region","Région (droit du bail régionalisé)",true,["Wallonie","Bruxelles","Flandre"]),Pq("debut","Date de début",true),Pq("loyer","Loyer mensuel (€)",true),Pq("garantie","Garantie locative (€, max 2-3 mois selon la région)",false),Pq("charges","Charges (provision ou forfait)",false)]},
    be_indexation_loyer:{ic:"📈",n:"Contestation d'indexation du loyer",cat:"be_logement",tier:"standard",q:[Pq("bailleur","Bailleur : nom et adresse",true),Pq("adresse","Adresse du logement",true),Pq("loyer_actuel","Loyer actuel (€)",true),Pq("loyer_demande","Loyer indexé demandé (€)",true),Pq("motifs","Motifs de contestation (indice santé, bail non enregistré…)",true)]},

    /* Travail & Emploi */
    be_demission:{ic:"👋",n:"Lettre de démission",cat:"be_travail",tier:"einfach",q:[Pq("employeur","Employeur : nom et adresse",true),Pq("poste","Votre fonction",true),Pq("anciennete","Ancienneté (le préavis en dépend)",true),Pq("dernier_jour","Date de début du préavis souhaitée",true),Pq("coordonnees","Vos nom, prénom et adresse",true)]},
    be_reclamation_salaire:{ic:"🧾",n:"Réclamation de salaire / pécule de vacances",cat:"be_travail",tier:"standard",q:[Pq("employeur","Employeur : nom et adresse",true),Pq("poste","Votre fonction et période concernée",true),Pq("elements","Éléments réclamés (salaire, pécule de vacances, prime de fin d'année…)",true),Pq("montant","Montant estimé (€)",false),Pq("coordonnees","Vos nom et prénom",true)]},
    be_teletravail:{ic:"🏡",n:"Demande de télétravail",cat:"be_travail",tier:"einfach",q:[Pq("employeur","Employeur/responsable",true),Pq("poste","Votre fonction",true),Pq("regime","Régime souhaité (jours/semaine)",true),Pq("motif","Motif (optionnel)",false)]},
    be_attestation_occupation:{ic:"📄",n:"Demande d'attestation d'occupation",cat:"be_travail",tier:"einfach",q:[Pq("employeur","Employeur / service RH",true),Pq("periode","Période d'occupation",true),Pq("usage","Usage de l'attestation (bail, banque, administration…)",false),Pq("coordonnees","Vos nom, prénom et fonction",true)]},
    be_onem:{ic:"💼",n:"Courrier à l'ONEM (chômage)",cat:"be_travail",tier:"standard",q:[Pq("objet","Objet",true,["Demande d'allocations","Contestation d'une décision","Demande d'information"]),Pq("situation","Votre situation (dernier employeur, date de fin, formulaire C4…)",true),Pq("coordonnees","Vos nom, prénom et n° de registre national",true)]},
    be_mutuelle:{ic:"🏥",n:"Courrier à la mutuelle (incapacité de travail)",cat:"be_travail",tier:"standard",q:[Pq("mutuelle","Mutuelle : nom et bureau",true),Pq("objet","Objet",true,["Déclaration d'incapacité","Indemnités","Contestation d'une décision","Autre"]),Pq("situation","Votre situation (certificat médical, dates…)",true),Pq("coordonnees","Vos nom, prénom et n° de registre national",true)]},

    /* Consommation & Litiges */
    be_retractation:{ic:"🔄",n:"Rétractation d'achat (14 jours)",cat:"be_conso",tier:"einfach",q:[Pq("vendeur","Vendeur : nom et adresse",true),Pq("commande","N° de commande",true),Pq("date","Date d'achat / de livraison",true),Pq("produits","Produits concernés",true),Pq("iban","IBAN pour le remboursement (optionnel)",false)]},
    be_garantie_legale:{ic:"🔧",n:"Réclamation de garantie légale (2 ans)",cat:"be_conso",tier:"standard",q:[Pq("vendeur","Vendeur : nom et adresse",true),Pq("produit","Produit (marque/modèle)",true),Pq("date_achat","Date d'achat",true),Pq("defaut","Défaut constaté",true),Pq("demande","Votre demande",true,["Réparation","Remplacement","Réduction du prix","Remboursement"])]},
    be_compagnie_aerienne:{ic:"✈️",n:"Réclamation compagnie aérienne (retard/annulation)",cat:"be_conso",tier:"standard",q:[Pq("compagnie","Compagnie aérienne",true),Pq("vol","N° de vol et date",true),Pq("probleme","Problème",true,["Retard","Annulation","Surbooking","Bagages"]),Pq("demande","Votre demande (indemnisation CE 261/2004…)",true),Pq("coordonnees","Vos nom et prénom",true)]},
    be_mise_en_demeure:{ic:"⚖️",n:"Mise en demeure",cat:"be_conso",tier:"standard",q:[Pq("destinataire","Destinataire : nom et adresse",true),Pq("creance","Objet de la créance / obligation (facture impayée, remboursement…)",true),Pq("montant","Montant réclamé (€, le cas échéant)",false),Pq("delai","Délai accordé (p.ex. 15 jours)",true),Pq("coordonnees","Vos nom, prénom et adresse",true)]},
    be_contestation_facture:{ic:"🧾",n:"Contestation de facture",cat:"be_conso",tier:"standard",q:[Pq("emetteur","Émetteur de la facture : nom et adresse",true),Pq("facture","N° et date de la facture",true),Pq("montant","Montant contesté (€)",true),Pq("motifs","Motifs de la contestation",true),Pq("coordonnees","Vos nom et prénom",true)]},
    be_mediateur_conso:{ic:"🤝",n:"Saisine du Service de Médiation pour le Consommateur",cat:"be_conso",tier:"standard",q:[Pq("entreprise","Entreprise concernée : nom et adresse",true),Pq("litige","Résumé du litige (faits, dates)",true),Pq("demarches","Démarches déjà entreprises (plainte préalable auprès de l'entreprise)",true),Pq("demande","Votre demande",true),Pq("coordonnees","Vos nom, prénom et adresse",true)]},

    /* Contrats */
    be_reconnaissance_dette:{ic:"💶",n:"Reconnaissance de dette",cat:"be_contrats",tier:"standard",q:[Pq("creancier","Créancier : nom et adresse",true),Pq("debiteur","Débiteur : nom et adresse",true),Pq("montant","Montant (€, en chiffres et en lettres)",true),Pq("remboursement","Modalités de remboursement",true),Pq("interets","Avec intérêts ?",false,["Sans intérêts","Avec intérêts"])]},
    be_vente_particuliers:{ic:"🛒",n:"Contrat de vente entre particuliers",cat:"be_contrats",tier:"einfach",q:[Pq("role","Vous êtes",true,["Vendeur","Acheteur"]),Pq("autre_partie","Autre partie : nom et adresse",true),Pq("objet","Objet de la vente",true),Pq("prix","Prix (€)",true),Pq("remise","Date de remise",false)]},
    be_pret:{ic:"🤝",n:"Contrat de prêt entre particuliers",cat:"be_contrats",tier:"standard",q:[Pq("preteur","Prêteur : nom et adresse",true),Pq("emprunteur","Emprunteur : nom et adresse",true),Pq("montant","Montant prêté (€)",true),Pq("remboursement","Plan de remboursement",true),Pq("interets","Taux d'intérêt (%, optionnel)",false)]},
    be_procuration:{ic:"📝",n:"Procuration",cat:"be_contrats",tier:"einfach",q:[Pq("mandataire","Mandataire : nom et date de naissance",true),Pq("objet","Objet de la procuration",true),Pq("duree","Durée de validité (optionnel)",false),Pq("coordonnees","Vos (mandant) nom, prénom et adresse",true)]},
    be_nda:{ic:"🔒",n:"Accord de confidentialité (NDA)",cat:"be_contrats",tier:"standard",q:[Pq("autre_partie","Autre partie : nom et adresse",true),Pq("objet","Objet de la collaboration",true),Pq("portee","Portée des informations confidentielles",true),Pq("duree","Durée (optionnel)",false)]},
    be_prestation:{ic:"🧰",n:"Contrat de prestation de services",cat:"be_contrats",tier:"komplex",q:[Pq("role","Vous êtes",true,["Prestataire","Client"]),Pq("autre_partie","Autre partie : nom et n° BCE (si entreprise)",true),Pq("service","Description du service",true),Pq("prix","Prix et modalités de paiement (€)",true),Pq("delai","Délai/durée",false),Pq("clauses","Clauses particulières (optionnel)",false)]},

    /* Administrations */
    be_amende_sac:{ic:"🏛️",n:"Contestation d'amende SAC (sanction administrative communale)",cat:"be_admin",tier:"standard",q:[Pq("commune","Commune / fonctionnaire sanctionnateur",true),Pq("reference","N° de dossier / référence",true),Pq("date","Date des faits reprochés",true),Pq("motifs","Motifs de la contestation",true),Pq("coordonnees","Vos nom, prénom et adresse",true)]},
    be_amende_roulage:{ic:"🚗",n:"Contestation d'amende de roulage",cat:"be_admin",tier:"standard",q:[Pq("reference","N° de procès-verbal / de perception immédiate",true),Pq("date","Date de l'infraction",true),Pq("plaque","Plaque d'immatriculation",false),Pq("motifs","Motifs de la contestation",true),Pq("coordonnees","Vos nom, prénom et adresse",true)]},
    be_spf_finances:{ic:"🧾",n:"Courrier au SPF Finances (réclamation / plan de paiement)",cat:"be_admin",tier:"komplex",q:[Pq("bureau","Bureau/centre du SPF Finances",true),Pq("objet","Objet",true,["Réclamation (avertissement-extrait de rôle)","Plan de paiement","Demande d'information"]),Pq("reference","N° d'article / de dossier",true),Pq("expose","Exposé de votre situation / vos arguments",true),Pq("coordonnees","Vos nom, prénom et n° de registre national",true)]},
    be_changement_adresse:{ic:"🏠",n:"Déclaration de changement d'adresse (commune)",cat:"be_admin",tier:"einfach",q:[Pq("commune","Commune de la nouvelle résidence",true),Pq("nouvelle_adresse","Nouvelle adresse",true),Pq("date_demenagement","Date du déménagement",true),Pq("menage","Personnes du ménage qui déménagent (optionnel)",false),Pq("coordonnees","Vos nom, prénom et n° de registre national",true)]},
    be_casier_judiciaire:{ic:"📜",n:"Demande d'extrait de casier judiciaire",cat:"be_admin",tier:"einfach",q:[Pq("commune","Commune de résidence",true),Pq("modele","Type d'extrait",true,["Modèle de base (art. 595)","Modèle 596-1 (activité réglementée)","Modèle 596-2 (contact avec mineurs)"]),Pq("usage","Usage prévu (employeur, administration…)",true),Pq("coordonnees","Vos nom, prénom et n° de registre national",true)]},
    be_cpas:{ic:"🤲",n:"Courrier au CPAS",cat:"be_admin",tier:"standard",q:[Pq("cpas","CPAS de la commune",true),Pq("objet","Objet",true,["Revenu d'intégration","Aide sociale","Contestation d'une décision","Autre"]),Pq("situation","Votre situation (revenus, ménage, logement)",true),Pq("demande","Votre demande",true),Pq("coordonnees","Vos nom, prénom et adresse",true)]}
  };

  function inject(){
    try{
      if(typeof DOCS==="undefined"||!DOCS) return false;
      for(var k in D){ if(!DOCS[k]) DOCS[k]=D[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=D[k].tier||"standard"; }
      if(typeof CATS!=="undefined"&&CATS){
        for(var ck in XC){ if(!CATS[ck]) CATS[ck]={n:XC[ck].n,ic:XC[ck].ic,docs:[],country:CCODE}; if(!CATS[ck].docs) CATS[ck].docs=[]; }
        for(var k2 in D){ var c2=D[k2].cat; if(CATS[c2]&&CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); }
      }
      if(typeof CAT_LABELS!=="undefined"){ for(var cl in XC) CAT_LABELS[cl]=XC[cl].n; }
      if(typeof CAT_DOCS!=="undefined"){
        for(var cd in XC){ if(!CAT_DOCS[cd]) CAT_DOCS[cd]=[]; }
        for(var k3 in D){ var c3=D[k3].cat; if(CAT_DOCS[c3]){ var ex=false; for(var i=0;i<CAT_DOCS[c3].length;i++){ if(CAT_DOCS[c3][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[c3].push({k:k3,ic:D[k3].ic,t:D[k3].n,tier:D[k3].tier||"standard"}); } }
      }
      return (typeof DOCS!=="undefined" && DOCS.be_mise_en_demeure)?true:false;
    }catch(e){ return false; }
  }

  /* Ülke filtresi (CH_TR_MODULE kuruyor; yoksa aynı korumalı sarmalayıcı burada da kurulur) */
  function installMultiCountryFilter(){
    try{
      if(typeof showAllCats!=="function" || showAllCats.__multiCountry) return;
      var _sac=showAllCats;
      var w=function(){
        try{
          if(typeof CATS==="undefined") return _sac.apply(this,arguments);
          var cur=(typeof CC!=="undefined"?CC:"DE");
          var hid={};
          for(var k in CATS){
            var cc=(CATS[k]&&CATS[k].country)||"DE";
            if(cc!==cur){ hid[k]=CATS[k]; delete CATS[k]; }
          }
          try{ return _sac.apply(this,arguments); } finally { for(var h in hid) CATS[h]=hid[h]; }
        }catch(e){ return _sac.apply(this,arguments); }
      };
      w.__multiCountry=1; showAllCats=w; if(typeof window!=="undefined") window.showAllCats=w;
    }catch(e){}
  }

  /* Welcome grid: bu ülkenin kartları (data-cccountry) + görünürlük */
  function xGrid(){
    try{
      var grid=document.querySelector(".wcat-grid"); if(!grid) return;
      var cur=(typeof CC!=="undefined"?CC:"DE");
      if(cur===CCODE){
        X_ORDER.forEach(function(ck){
          if(grid.querySelector('[data-cccountry="'+CCODE+'"][data-ccat="'+ck+'"]')) return;
          var c=XC[ck]; var el=document.createElement("div"); el.className="wcat";
          el.setAttribute("data-cccountry",CCODE); el.setAttribute("data-ccat",ck);
          el.onclick=function(){ try{ if(typeof showCat==="function") showCat(ck); }catch(e){} };
          var n=(typeof CATS!=="undefined"&&CATS[ck]&&CATS[ck].docs)?CATS[ck].docs.length:0;
          el.innerHTML='<div class="wcat-ic">'+c.ic+'</div><div class="wcat-t">'+c.n+'</div><div class="wcat-s">'+n+' '+DOCWORD+'</div>';
          grid.appendChild(el);
        });
      }
      grid.querySelectorAll(".wcat").forEach(function(el){
        var cc=el.getAttribute("data-cccountry")||(el.getAttribute("data-frcat")?"FR":(el.getAttribute("data-trcat")?"TR":"DE"));
        el.style.display=(cc===cur)?"":"none";
      });
    }catch(e){}
  }

  function installCountryHook(){
    try{
      if(typeof setCountry!=="function" || setCountry["__m"+CCODE]) return;
      var _sc=setCountry;
      var s=function(cc){ var r; try{ r=_sc.apply(this,arguments); }catch(e){} try{ xGrid(); }catch(e){} return r; };
      s["__m"+CCODE]=1; setCountry=s; if(typeof window!=="undefined") window.setCountry=s;
    }catch(e){}
  }

  var done=false, ticks=0;
  function run(){ if(!done){ if(inject()) done=true; } installMultiCountryFilter(); installCountryHook(); try{ xGrid(); }catch(e){} if(ticks++<40) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}
CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
if($src!==$start){ file_put_contents($file,$src); echo "Belcika modulu eklendi (CH_BE_MODULE) - ~36 belge, 6 kategori, Fransizca (Belcika hukuku).\n"; }
else echo "degisiklik yok\n";
echo "\nNOT: Belge dili/hukuku icin apply-lawsys-all.php de calistirilmali (lawSystems['BE'] + doc-lang).\n";
echo "SONRA: opcache-reset (pull-updates otomatik yapar). SIL: rm apply-belgique.php\n";
echo "Test: Ayarlar -> Belgique sec -> kategoriler Fransizca -> 'Mise en demeure' uret.\n";
