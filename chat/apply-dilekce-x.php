<?php
/**
 * ChatHelp — apply-dilekce-x (CH_DILEKCE_X) — 7 ulkeye +21 dilekce (anahtar sorulu)
 *  Ispanya, Fransa, Italya, Ingiltere, Isvec, Isvicre, Belcika kataloglarina
 *  ulke dilinde, kendi hukukuna atifli 3'er pratik dilekce eklenir; her biri
 *  mevcut soru-motoru (Pe) formatinda anahtar sorularla gelir.
 *  (ABD ve Luksemburg uygulamada henuz hukuk ulkesi olarak yok — ayri is.)
 * KULLANIM: pull2.php?key=...&files=apply-dilekce-x.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-dilekce-x BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_DILEKCE_X')!==false) exit("Zaten ekli (CH_DILEKCE_X).\n");
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
$ac=substr_count($src,$anchor);
if ($ac!==1) exit("HATA: getDocPrice anchor $ac kez (beklenen 1).\n");

$js = <<<'CHJS'

/* CH_DILEKCE_X — 7 ulkeye 3'er pratik dilekce (anahtar sorulu) */
try{(function(){
  function Pe(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var NEWCATS={
    "esx2_practico":{n:"Escritos Prácticos II",ic:"🧰",country:"ES"},
    "frx2_pratique":{n:"Courriers Pratiques II",ic:"🧰",country:"FR"},
    "itx2_pratico":{n:"Lettere Pratiche II",ic:"🧰",country:"IT"},
    "gbx2_practical":{n:"Practical Letters II",ic:"🧰",country:"GB"},
    "sex2_praktisk":{n:"Praktiska Skrivelser II",ic:"🧰",country:"SE"},
    "chx2_praktisch":{n:"Praktische Schreiben II",ic:"🧰",country:"CH"},
    "bex2_pratique":{n:"Courriers Pratiques II",ic:"🧰",country:"BE"}
  };
  var E={
    /* ── ES ── */
    "esx2_reclamacion_aerolinea":{ic:"✈️",n:"Reclamación a aerolínea por retraso o cancelación (Reg. 261/2004)",cat:"esx2_practico",tier:"standard",q:[
      Pe("pasajero","Nombre completo del pasajero",true),Pe("vuelo","Número de vuelo y fecha",true),Pe("trayecto","Origen y destino del vuelo",true),
      Pe("incidencia","Tipo de incidencia",true,["Retraso de más de 3 horas","Cancelación","Denegación de embarque","Pérdida de conexión"]),
      Pe("distancia","Distancia aproximada del vuelo",true,["Hasta 1.500 km (250 €)","1.500–3.500 km (400 €)","Más de 3.500 km (600 €)"]),
      Pe("gastos","Gastos adicionales ocasionados (comida, hotel, transporte)",false)]},
    "esx2_oposicion_subida_alquiler":{ic:"🏠",n:"Oposición a subida de renta no ajustada a contrato o ley",cat:"esx2_practico",tier:"standard",q:[
      Pe("inquilino","Nombre del inquilino",true),Pe("arrendador","Nombre del arrendador",true),Pe("vivienda","Dirección de la vivienda",true),
      Pe("subida_comunicada","Subida comunicada (importe y fecha de la notificación)",true),
      Pe("motivo_oposicion","Motivo de la oposición",true,["Supera el índice legal aplicable","No corresponde según el contrato","Notificación fuera de plazo","Contrato sin cláusula de actualización"]),
      Pe("renta_actual","Renta mensual actual (€)",true)]},
    "esx2_reclamacion_comisiones_banco":{ic:"🏦",n:"Reclamación al banco por comisiones indebidas",cat:"esx2_practico",tier:"standard",q:[
      Pe("titular","Nombre del titular de la cuenta",true),Pe("banco","Nombre del banco",true),Pe("cuenta","IBAN o número de cuenta (parcial)",true),
      Pe("comisiones","Comisiones reclamadas (concepto e importes)",true),Pe("periodo","Periodo de las comisiones",true),
      Pe("motivo","Motivo de la reclamación",true,["No pactadas en contrato","Servicio no prestado efectivamente","Cobro duplicado","Falta de transparencia"])]},
    /* ── FR ── */
    "frx2_contestation_amende":{ic:"🚗",n:"Contestation d'un avis de contravention (OMP)",cat:"frx2_pratique",tier:"standard",q:[
      Pe("contrevenant","Nom complet du destinataire de l'avis",true),Pe("num_avis","Numéro de l'avis de contravention",true),Pe("date_infraction","Date et lieu de l'infraction reprochée",true),
      Pe("motif","Motif de la contestation",true,["Je n'étais pas le conducteur","Véhicule vendu ou volé","Erreur matérielle sur l'avis","Signalisation absente ou non conforme","Autre motif"]),
      Pe("faits","Exposé des faits",true),Pe("consignation","Une consignation a-t-elle été versée ?",false,["Oui","Non","Non requise"])]},
    "frx2_resiliation_bail_locataire":{ic:"🏠",n:"Congé donné par le locataire (préavis réduit le cas échéant)",cat:"frx2_pratique",tier:"einfach",q:[
      Pe("locataire","Nom du locataire",true),Pe("bailleur","Nom et adresse du bailleur",true),Pe("logement","Adresse du logement loué",true),
      Pe("preavis","Durée de préavis invoquée",true,["3 mois (droit commun)","1 mois — zone tendue","1 mois — mutation professionnelle","1 mois — perte d'emploi","1 mois — état de santé","1 mois — bénéficiaire RSA/AAH"]),
      Pe("date_depart","Date de départ souhaitée",true)]},
    "frx2_mise_en_demeure_remboursement":{ic:"💶",n:"Mise en demeure de rembourser (achat non conforme ou annulé)",cat:"frx2_pratique",tier:"standard",q:[
      Pe("demandeur","Nom du demandeur",true),Pe("vendeur","Nom et adresse du vendeur/prestataire",true),Pe("achat","Objet de l'achat et date",true),
      Pe("montant","Montant à rembourser (€)",true),
      Pe("motif","Motif du remboursement",true,["Produit non conforme ou défectueux","Rétractation dans le délai de 14 jours","Commande non livrée","Prestation annulée par le vendeur"]),
      Pe("demarches","Démarches déjà effectuées",false)]},
    /* ── IT ── */
    "itx2_disdetta_utenza":{ic:"📞",n:"Disdetta contratto telefonia/utenze (L. 40/2007)",cat:"itx2_pratico",tier:"einfach",q:[
      Pe("intestatario","Nome dell'intestatario del contratto",true),Pe("operatore","Operatore/fornitore",true),Pe("codice_cliente","Codice cliente o numero contratto",true),
      Pe("servizio","Servizio da disdire",true,["Telefonia mobile","Telefonia fissa/Internet","Luce","Gas","Pay TV"]),
      Pe("decorrenza","Data di decorrenza richiesta",true),Pe("motivo","Motivo (facoltativo)",false)]},
    "itx2_reclamo_aereo":{ic:"✈️",n:"Reclamo a compagnia aerea per ritardo/cancellazione (Reg. 261/2004)",cat:"itx2_pratico",tier:"standard",q:[
      Pe("passeggero","Nome del passeggero",true),Pe("volo","Numero e data del volo",true),Pe("tratta","Aeroporto di partenza e arrivo",true),
      Pe("problema","Tipo di problema",true,["Ritardo superiore a 3 ore","Cancellazione","Negato imbarco","Coincidenza persa"]),
      Pe("distanza","Distanza del volo",true,["Fino a 1.500 km (250 €)","1.500–3.500 km (400 €)","Oltre 3.500 km (600 €)"]),
      Pe("spese","Spese aggiuntive sostenute",false)]},
    "itx2_diffida_stipendi":{ic:"💼",n:"Diffida al datore di lavoro per stipendi arretrati",cat:"itx2_pratico",tier:"standard",q:[
      Pe("lavoratore","Nome del lavoratore",true),Pe("datore","Datore di lavoro (ragione sociale)",true),Pe("mansione","Mansione e data di assunzione",true),
      Pe("mensilita","Mensilità non pagate (quali e importo)",true),Pe("termine","Termine concesso per il pagamento",true,["8 giorni","10 giorni","15 giorni"]),
      Pe("rapporto_in_corso","Il rapporto di lavoro è ancora in corso?",true,["Sì","No, è cessato"])]},
    /* ── GB ── */
    "gbx2_letter_before_action":{ic:"⚖️",n:"Letter Before Action (small claims — Pre-Action Protocol)",cat:"gbx2_practical",tier:"standard",q:[
      Pe("claimant","Your full name",true),Pe("defendant","Name and address of the person/company owing you",true),
      Pe("claim_basis","What the claim is about",true,["Unpaid invoice","Faulty goods or services","Unreturned deposit","Loan not repaid","Other debt"]),
      Pe("amount","Amount claimed (£)",true),Pe("background","Brief background and key dates",true),
      Pe("deadline","Deadline for payment before court action",true,["14 days","21 days","30 days"])]},
    "gbx2_disrepair_complaint":{ic:"🏠",n:"Complaint to landlord about disrepair (s.11 LTA 1985)",cat:"gbx2_practical",tier:"standard",q:[
      Pe("tenant","Tenant's full name",true),Pe("landlord","Landlord's/agent's name and address",true),Pe("property","Property address",true),
      Pe("defects","Defects to be repaired (describe)",true),Pe("first_reported","When were the defects first reported?",true),
      Pe("impact","Impact on you",false,["Health affected (damp/mould)","Property partly unusable","Increased bills","Damage to belongings"])]},
    "gbx2_pcn_appeal":{ic:"🚗",n:"Parking Charge Notice (PCN) appeal",cat:"gbx2_practical",tier:"einfach",q:[
      Pe("driver","Your full name",true),Pe("issuer","Issuer of the PCN (council or private operator)",true),Pe("pcn_ref","PCN reference number and date",true),
      Pe("ground","Ground of appeal",true,["Valid ticket/permit held","Signage inadequate or misleading","Vehicle broken down","Loading/unloading exemption","Grace period not exceeded","Not the driver/keeper"]),
      Pe("facts","Brief statement of facts",true)]},
    /* ── SE ── */
    "sex2_bestrida_faktura":{ic:"🧾",n:"Bestridande av felaktig faktura eller inkassokrav",cat:"sex2_praktisk",tier:"einfach",q:[
      Pe("namn","Ditt fullständiga namn",true),Pe("motpart","Företaget som skickat fakturan/kravet",true),Pe("fakturanr","Faktura- eller ärendenummer",true),
      Pe("grund","Grund för bestridandet",true,["Ingen beställning har gjorts","Varan/tjänsten har inte levererats","Redan betald","Fel belopp","Avtal saknas"]),
      Pe("belopp","Bestridit belopp (kr)",true),Pe("beskrivning","Kort beskrivning av omständigheterna",true)]},
    "sex2_uppsagning_hyresavtal":{ic:"🏠",n:"Uppsägning av hyresavtal (12 kap. JB — tre månader)",cat:"sex2_praktisk",tier:"einfach",q:[
      Pe("hyresgast","Hyresgästens namn",true),Pe("hyresvard","Hyresvärdens namn och adress",true),Pe("lagenhet","Lägenhetens adress och lägenhetsnummer",true),
      Pe("avflytt","Önskat avflyttningsdatum (tre kalendermånaders uppsägningstid)",true),Pe("nycklar","Överlämning av nycklar (hur/när)",false)]},
    "sex2_reklamation_konsumentkop":{ic:"🛒",n:"Reklamation av vara (konsumentköplagen 2022:260)",cat:"sex2_praktisk",tier:"standard",q:[
      Pe("kopare","Köparens namn",true),Pe("saljare","Säljarens namn/butik",true),Pe("vara","Vara och inköpsdatum",true),
      Pe("fel","Felet på varan (beskriv)",true),
      Pe("krav","Ditt krav",true,["Avhjälpande (reparation)","Omleverans","Prisavdrag","Hävning av köpet och återbetalning"]),
      Pe("kvitto","Finns kvitto eller orderbekräftelse?",true,["Ja","Nej, men annan bevisning finns"])]},
    /* ── CH ── */
    "chx2_mietzins_anfechtung":{ic:"🏠",n:"Anfechtung einer Mietzinserhöhung (Art. 270b OR)",cat:"chx2_praktisch",tier:"standard",q:[
      Pe("mieter","Name des Mieters",true),Pe("vermieter","Name und Adresse der Vermieterschaft",true),Pe("wohnung","Adresse des Mietobjekts",true),
      Pe("erhoehung","Mitgeteilte Erhöhung (alt/neu, gültig ab)",true),
      Pe("grund","Anfechtungsgrund",true,["Missbräuchlicher Ertrag","Referenzzinssatz nicht gesunken/berücksichtigt","Formfehler (kein amtliches Formular)","Begründung fehlt oder unzureichend"]),
      Pe("frist_hinweis","Hinweis: Anfechtung innert 30 Tagen bei der Schlichtungsbehörde",false)]},
    "chx2_rechtsvorschlag":{ic:"⚖️",n:"Rechtsvorschlag gegen Betreibung (Art. 74 SchKG)",cat:"chx2_praktisch",tier:"einfach",q:[
      Pe("schuldner","Name des Betriebenen",true),Pe("glaeubiger","Name des Gläubigers",true),Pe("betreibungsnr","Betreibungs-Nr. und Betreibungsamt",true),
      Pe("umfang","Umfang des Rechtsvorschlags",true,["Gesamte Forderung","Teilbetrag"]),
      Pe("grund","Kurze Begründung (freiwillig)",false),
      Pe("frist_hinweis","Hinweis: innert 10 Tagen nach Zustellung des Zahlungsbefehls",false)]},
    "chx2_arbeitszeugnis":{ic:"📄",n:"Einforderung eines Arbeitszeugnisses (Art. 330a OR)",cat:"chx2_praktisch",tier:"einfach",q:[
      Pe("arbeitnehmer","Name des Arbeitnehmers",true),Pe("arbeitgeber","Arbeitgeber (Firma, Adresse)",true),Pe("funktion","Funktion und Beschäftigungsdauer",true),
      Pe("art","Gewünschtes Zeugnis",true,["Vollzeugnis","Arbeitsbestätigung","Zwischenzeugnis"]),
      Pe("frist","Gesetzte Frist",true,["10 Tage","14 Tage","30 Tage"])]},
    /* ── BE ── */
    "bex2_contestation_facture":{ic:"🧾",n:"Contestation de facture ou de mise en demeure",cat:"bex2_pratique",tier:"einfach",q:[
      Pe("nom","Votre nom complet",true),Pe("entreprise","Entreprise émettrice de la facture",true),Pe("reference","Référence de la facture et date",true),
      Pe("motif","Motif de la contestation",true,["Aucune commande passée","Prestation non fournie","Déjà payée","Montant erroné","Clause abusive"]),
      Pe("montant","Montant contesté (€)",true),Pe("faits","Bref exposé des faits",true)]},
    "bex2_renon_bail":{ic:"🏠",n:"Renon du bail par le preneur (préavis de 3 mois)",cat:"bex2_pratique",tier:"einfach",q:[
      Pe("preneur","Nom du preneur (locataire)",true),Pe("bailleur","Nom et adresse du bailleur",true),Pe("logement","Adresse du logement loué",true),
      Pe("region","Région (règles distinctes)",true,["Wallonie","Bruxelles-Capitale","Flandre"]),
      Pe("fin_bail","Fin de bail souhaitée (préavis de 3 mois, indemnité éventuelle les 3 premières années)",true)]},
    "bex2_mise_en_demeure":{ic:"💶",n:"Mise en demeure de payer (créance impayée)",cat:"bex2_pratique",tier:"standard",q:[
      Pe("creancier","Nom du créancier (vous)",true),Pe("debiteur","Nom et adresse du débiteur",true),
      Pe("creance","Origine de la créance (facture, prêt, dommage)",true),Pe("montant","Montant dû (€)",true),
      Pe("echeance","Échéance initiale de paiement",true),Pe("delai","Délai accordé avant poursuites",true,["8 jours","15 jours","30 jours"])]}
  };
  function inject(){
    try{
      if(typeof DOCS==="undefined"||!DOCS) return false;
      if(typeof CATS==="undefined"||!CATS) return false;
      for(var k in E){ if(!DOCS[k]) DOCS[k]=E[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=E[k].tier||"standard"; }
      for(var nc in NEWCATS){ if(!CATS[nc]) CATS[nc]={n:NEWCATS[nc].n,ic:NEWCATS[nc].ic,docs:[],country:NEWCATS[nc].country}; if(!CATS[nc].docs) CATS[nc].docs=[]; }
      for(var k2 in E){ var c2=E[k2].cat; if(CATS[c2]&&CATS[c2].docs&&CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); }
      if(typeof CAT_LABELS!=="undefined"){ for(var cl in NEWCATS) CAT_LABELS[cl]=NEWCATS[cl].n; }
      if(typeof CAT_DOCS!=="undefined"){
        for(var cd in NEWCATS){ if(!CAT_DOCS[cd]) CAT_DOCS[cd]=[]; }
        for(var k3 in E){ var c3=E[k3].cat; if(CAT_DOCS[c3]){ var ex=false; for(var i=0;i<CAT_DOCS[c3].length;i++){ if(CAT_DOCS[c3][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[c3].push({k:k3,ic:E[k3].ic,t:E[k3].n,tier:E[k3].tier||"standard"}); } }
      }
      return !!(DOCS.esx2_reclamacion_aerolinea);
    }catch(e){ return false; }
  }
  var done=false, ticks=0;
  function run(){ if(!done){ if(inject()) done=true; } if(ticks++<40) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}

CHJS;

$src=str_replace($anchor,$anchor.$js,$src);
echo "  ✓ 7 ulkeye 3'er dilekce (21) enjekte edildi\n";

$tmp = tempnam(sys_get_temp_dir(),'dx').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
$bk=@file_put_contents($file.'.bak-dilekcex-'.date('Ymd-His'), @file_get_contents($file));
if ($bk===false) echo "  ⚠ yedek yazilamadi — devam\n";
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI: ".var_export($w,true)."\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_DILEKCE_X')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_DILEKCE_X diskte (".strlen($chk)." bayt)\n";
echo "\n✓ CH_DILEKCE_X uygulandi: ES/FR/IT/GB/SE/CH/BE +3'er dilekce, anahtar sorulu.\n";
