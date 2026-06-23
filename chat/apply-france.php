<?php
/**
 * ChatHelp — FRANSA MODÜLÜ (tek uygulama, ülke seçici + IP otomatik)
 * ------------------------------------------------------------------
 *  Tek sistem; Fransa'dan giren kullanıcı için HER ŞEY Fransızca + Fransız hukuku.
 *   • Fransız belge kataloğu (~28 belge, 6 kategori) -> DOCS/CATS/DOC_TIER (aynı scope, inline)
 *   • Ülke filtresi: CC='FR' iken sadece Fransız kategoriler; CC='DE' iken Almanca (showAllCats + welcome grid)
 *   • IP otomatik: geo.php -> Fransa ise setCountry('FR') (dil fr, hukuk FR) — manuel seçim her şeyi ezer
 *   • Fiyat kademeleri mevcut sistemle aynı (einfach/standard/komplex)
 *
 *  Belge dili (api.php tarafı) ayrı kontrol edilir — bunun için dump-generate.php çıktısına bak.
 *
 * KULLANIM: chat-help.com/chat/apply-france.php -> opcache-reset.php. SİL: rm apply-france.php
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src   = file_get_contents($file);
$start = $src;
file_put_contents($file . '.bak-france-' . date('Ymd-His'), $src);
$rep = [];

$anchor = "function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if (strpos($src, 'CH_FR_INLINE') !== false) {
    $rep[] = ['#1 Fransa modülü (zaten ekli)', 0];
} elseif (strpos($src, $anchor) === false) {
    $rep[] = ['#1 anchor (getDocPrice) BULUNAMADI', 0];
} else {
    $js = <<<'JS'

/* CH_FR_INLINE — Fransa modülü (aynı scope: DOCS/CATS/DOC_TIER/CC/setCountry/showAllCats/showCat) */
try{(function(){
  function Pf(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}

  /* Fransız kategoriler */
  var FRC={
    fr_resiliation:{n:"Résiliations",ic:"📄"},
    fr_logement:{n:"Logement & Bail",ic:"🏠"},
    fr_travail:{n:"Travail & Emploi",ic:"💼"},
    fr_litige:{n:"Litiges & Réclamations",ic:"⚖️"},
    fr_conso:{n:"Consommation",ic:"🛒"},
    fr_admin:{n:"Administratif",ic:"🏛️"}
  };
  var FR_ORDER=["fr_resiliation","fr_logement","fr_travail","fr_litige","fr_conso","fr_admin"];

  /* Fransız belge kataloğu */
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
    fr_recours_administratif:{ic:"🏛️",n:"Recours administratif (RAPO)",cat:"fr_admin",tier:"komplex",q:[Pf("administration","Administration concernée",true),Pf("decision","Décision contestée",true),Pf("date_decision","Date de notification",true),Pf("type","Type de recours",false,["Gracieux","Hiérarchique"]),Pf("motifs","Motifs (faits & droit)",true),Pf("demande","Votre demande",true),Pf("coordonnees","Vos nom & adresse",true)]}
  };

  /* 1) Belgeleri DOCS/DOC_TIER'a; kategorileri CATS'e ekle (ülke etiketli) */
  for(var k in F){ if(typeof DOCS!=='undefined' && !DOCS[k]) DOCS[k]=F[k]; if(typeof DOC_TIER!=='undefined') DOC_TIER[k]=F[k].tier||'standard'; }
  if(typeof CATS!=='undefined'){
    for(var ck in FRC){ if(!CATS[ck]) CATS[ck]={n:FRC[ck].n,ic:FRC[ck].ic,docs:[],country:'FR'}; }
    for(var k2 in F){ var c2=F[k2].cat; if(CATS[c2]){ if(!CATS[c2].docs) CATS[c2].docs=[]; if(CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); } }
    for(var gc in CATS){ if(gc.indexOf('fr_')!==0 && !CATS[gc].country) CATS[gc].country='DE'; }
  }

  /* 2) Ülke filtresi: showAllCats CC'ye göre yalnız ilgili ülke kategorilerini gösterir */
  function installFilter(){
    try{
      if(typeof showAllCats!=='function' || showAllCats.__fr) return;
      var _sac=showAllCats;
      var w=function(){
        try{
          if(typeof CATS==='undefined') return _sac.apply(this,arguments);
          var cur=(typeof CC!=='undefined'?CC:'DE');
          var hid={};
          for(var k in CATS){
            var isFr=(k.indexOf('fr_')===0)||(CATS[k]&&CATS[k].country==='FR');
            var want=(cur==='FR')?isFr:!isFr;
            if(!want){ hid[k]=CATS[k]; delete CATS[k]; }
          }
          try{ return _sac.apply(this,arguments); } finally { for(var h in hid) CATS[h]=hid[h]; }
        }catch(e){ return _sac.apply(this,arguments); }
      };
      w.__fr=1; showAllCats=w; if(typeof window!=='undefined') window.showAllCats=w;
    }catch(e){}
  }

  /* 3) Welcome grid: CC='FR' iken Fransız kartlar, Almanca kartlar gizli (ve tersi) */
  function frGrid(){
    try{
      var grid=document.querySelector('.wcat-grid'); if(!grid) return;
      var cur=(typeof CC!=='undefined'?CC:'DE'); var fr=(cur==='FR');
      if(fr){
        FR_ORDER.forEach(function(ck){
          if(grid.querySelector('[data-frcat="'+ck+'"]')) return;
          var c=FRC[ck]; var el=document.createElement('div'); el.className='wcat'; el.setAttribute('data-frcat',ck);
          el.onclick=function(){ try{ if(typeof showCat==='function') showCat(ck); }catch(e){} };
          var n=(typeof CATS!=='undefined'&&CATS[ck]&&CATS[ck].docs)?CATS[ck].docs.length:0;
          el.innerHTML='<div class="wcat-ic">'+c.ic+'</div><div class="wcat-t">'+c.n+'</div><div class="wcat-s">'+n+' documents</div>';
          grid.appendChild(el);
        });
      }
      grid.querySelectorAll('.wcat').forEach(function(el){
        var isFr=!!el.getAttribute('data-frcat');
        el.style.display=(fr?(isFr?'':'none'):(isFr?'none':''));
      });
    }catch(e){}
  }

  /* 4) setCountry hook: ülke değişince grid senkron + FR'de belge dili fr */
  function installCountryHook(){
    try{
      if(typeof setCountry!=='function' || setCountry.__fr) return;
      var _sc=setCountry;
      var s=function(cc){
        var r; try{ r=_sc.apply(this,arguments); }catch(e){}
        try{ if(cc==='FR' && typeof dLang!=='undefined' && !localStorage.getItem('ch_lm')) dLang='fr'; }catch(e){}
        try{ frGrid(); }catch(e){}
        return r;
      };
      s.__fr=1; setCountry=s; if(typeof window!=='undefined') window.setCountry=s;
    }catch(e){}
  }

  /* 5) IP otomatik: geo.php -> Fransa ise setCountry('FR') (manuel dil seçimi varsa dokunma) */
  function frGeo(){
    try{
      if(localStorage.getItem('ch_lm')) return;        // kullanıcı dili elle seçtiyse ezme
      if(localStorage.getItem('ch_geo_fr')) return;     // sadece bir kez tespit
      fetch('geo.php',{cache:'no-store'}).then(function(r){return r.json();}).then(function(d){
        try{ localStorage.setItem('ch_geo_fr','1'); }catch(e){}
        if(d && d.country==='FR' && (typeof CC==='undefined'||CC!=='FR') && typeof setCountry==='function'){ setCountry('FR'); }
      }).catch(function(){});
    }catch(e){}
  }

  function boot(){ installFilter(); installCountryHook(); try{frGrid();}catch(e){} setTimeout(frGeo,80); setInterval(function(){ try{frGrid();}catch(e){} },1200); }
  if(document.readyState==='loading'){ document.addEventListener('DOMContentLoaded',boot); } else { boot(); }
})();}catch(e){}
JS;
    $src = str_replace($anchor, $anchor . $js, $src);
    $rep[] = ['#1 Fransa modülü inline (28 belge, 6 kategori, ülke filtresi, IP otomatik)', 1];
}

$changed = ($src !== $start);
if ($changed) file_put_contents($file, $src);

echo "ChatHelp — Fransa Modülü raporu\n================================\n\n";
foreach ($rep as [$l,$n]) echo ($n>0?"  ✓ ":"  ✗ ").$l."  →  $n\n";

/* Bugünkü tüm güncellemeler index.php'de mi? */
$markers=[
  'CH_FR_INLINE'    =>'Fransa modülü (FR belgeler + filtre + IP)',
  'CH_VTR_INLINE'   =>'Verträge (34 sözleşme)',
  'CH_BEW_INLINE'   =>'Bewerbung (Lebenslauf vs.)',
  'CH_CLEANQ_INLINE'=>'Teknik soru temizliği',
  'ch-i18n-layer'   =>'8 dil + çeviri',
  'ch-home-cats'    =>'Ana sayfa Verträge/Bewerbung kartı',
  'ch-attach-v2'    =>'Foto/dosya ekleme',
  'CH_NOCACHE'      =>'Tarayıcı önbellek kapalı',
];
echo "\n[Güncellemeler index.php'de mi?]\n";
foreach($markers as $m=>$d){ echo (strpos($src,$m)!==false?"  ✓ ":"  ✗ EKSİK ").$d."  ($m)\n"; }

echo "\n" . ($changed ? "DURUM: index.php güncellendi. ✅\n" : "DURUM: Değişiklik yok.\n");
echo "\nNasıl çalışır:\n";
echo "  • Fransa IP -> geo.php -> setCountry('FR') -> arayüz+belgeler Fransızca, hukuk FR.\n";
echo "  • Ülke seçici (🇫🇷) ile elle de geçilebilir; manuel seçim IP'yi ezer.\n";
echo "  • CC='FR' iken yalnız Fransız kategoriler görünür; Almanca gizli (ve tersi).\n";
echo "\nÖNEMLİ — Belge METNİ Fransızca mı? api.php (doGenerate) kontrolü gerek:\n";
echo "  -> dump-generate.php yükle & çalıştır, çıktıyı bana gönder; gerekirse backend yamasını veririm.\n";
echo "\nSONRA: opcache-reset.php. SİL: rm apply-france.php\n";
echo "Test: Fransa IP/VPN ile aç -> her şey Fransızca; bir belge seç (örn. 'Mise en demeure') -> üret.\n";
