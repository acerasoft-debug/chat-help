<?php
/**
 * ChatHelp — İTALYA MODÜLÜ (~39 belge, 6 kategori, İtalyanca) — CH_TR_MODULE ile aynı kanıtlanmış desen
 * -------------------------------------------------------------------------------------------------------
 *  • Kategoriler country:'IT' etiketli -> genelleştirilmiş ülke filtresi (CH_TR_MODULE) otomatik çalışır
 *  • Welcome grid kartları data-cccountry='IT' -> kart görünürlüğü otomatik
 *  • Dil: setCountry('IT') zaten UL='it' yapıyor (CC_DATA.IT.l) — ek dil kodu gerekmez
 *  • Belge dili/hukuku: api.php'de lawSystems['IT'] + doc-lang IT->it (apply-lawsys-all.php ile)
 *
 * KULLANIM: pull-updates.php?files=apply-italia.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-italia BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,"CH_IT_MODULE")!==false) exit("Zaten ekli (CH_IT_MODULE).\n");
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-it-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_IT_MODULE — İtalya hukuku modülü (deferred; DOCS/CATS hazır olunca yüklenir) */
try{(function(){
  function Pq(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var XC={
    it_disdette:{n:"Disdette e Recessi",ic:"📄"},
    it_casa:{n:"Casa e Affitto",ic:"🏠"},
    it_lavoro:{n:"Lavoro",ic:"💼"},
    it_consumo:{n:"Consumatori e Controversie",ic:"🛒"},
    it_contratti:{n:"Contratti",ic:"📝"},
    it_pubblica:{n:"Pubblica Amministrazione",ic:"🏛️"}
  };
  var X_ORDER=["it_disdette","it_casa","it_lavoro","it_consumo","it_contratti","it_pubblica"];
  var CCODE="IT", DOCWORD="documenti";

  var D={
    /* Disdette e Recessi */
    it_disdetta_utenze:{ic:"⚡",n:"Disdetta utenze (luce/gas)",cat:"it_disdette",tier:"einfach",q:[Pq("fornitore","Fornitore: nome e indirizzo",true),Pq("codice_cliente","Codice cliente / POD-PDR",true),Pq("indirizzo","Indirizzo della fornitura",true),Pq("data","Data di disdetta desiderata",true),Pq("dati","Suoi nome, cognome e codice fiscale",true)]},
    it_disdetta_telefonia:{ic:"📱",n:"Disdetta telefonia / internet",cat:"it_disdette",tier:"einfach",q:[Pq("operatore","Operatore: nome",true),Pq("codice_cliente","Codice cliente / numero linea",true),Pq("vincolo","Ha un vincolo contrattuale?",false,["No","Sì","Non lo so"]),Pq("data","Data di disdetta desiderata",false),Pq("dati","Suoi nome, cognome e codice fiscale",true)]},
    it_disdetta_palestra:{ic:"🏋️",n:"Disdetta palestra / circolo sportivo",cat:"it_disdette",tier:"einfach",q:[Pq("centro","Centro: nome e indirizzo",true),Pq("num_tessera","Numero tessera",false),Pq("data","Data di disdetta",true),Pq("motivo","Motivo (opzionale)",false),Pq("dati","Suoi nome e cognome",true)]},
    it_disdetta_assicurazione:{ic:"🛡️",n:"Disdetta polizza assicurativa",cat:"it_disdette",tier:"einfach",q:[Pq("compagnia","Compagnia: nome e indirizzo",true),Pq("num_polizza","Numero di polizza",true),Pq("tipo","Tipo di assicurazione",true,["Auto","Casa","Salute","Vita","Altro"]),Pq("scadenza","Data di scadenza (rispettare il preavviso)",true),Pq("dati","Suoi nome, cognome e codice fiscale",true)]},
    it_chiusura_conto:{ic:"🏦",n:"Chiusura conto corrente",cat:"it_disdette",tier:"einfach",q:[Pq("banca","Banca: nome e filiale",true),Pq("iban","IBAN del conto",true),Pq("trasferimento","IBAN per trasferire il saldo (opzionale)",false),Pq("dati","Suoi nome, cognome e codice fiscale",true)]},
    it_recesso_abbonamento:{ic:"📺",n:"Recesso da abbonamento digitale",cat:"it_disdette",tier:"einfach",q:[Pq("piattaforma","Piattaforma (Netflix, Spotify…)",true),Pq("account","Email dell'account",true),Pq("data","Data di recesso (opzionale)",false),Pq("dati","Suoi nome e cognome",true)]},

    /* Casa e Affitto */
    it_disdetta_locazione:{ic:"🏠",n:"Disdetta contratto di locazione (inquilino)",cat:"it_casa",tier:"einfach",q:[Pq("locatore","Locatore/agenzia: nome e indirizzo",true),Pq("indirizzo","Indirizzo dell'immobile",true),Pq("data_rilascio","Data di rilascio (preavviso 6 mesi, contratto 4+4)",true),Pq("motivo","Gravi motivi (se recesso anticipato, opzionale)",false),Pq("dati","Suoi nome, cognome e codice fiscale",true)]},
    it_deposito_cauzionale:{ic:"💶",n:"Richiesta restituzione deposito cauzionale",cat:"it_casa",tier:"standard",q:[Pq("locatore","Locatore: nome e indirizzo",true),Pq("indirizzo","Indirizzo dell'immobile",true),Pq("data_consegna","Data di riconsegna delle chiavi",true),Pq("importo","Importo del deposito (€)",true),Pq("iban","IBAN per la restituzione",false),Pq("dati","Suoi nome e cognome",true)]},
    it_guasti_locatore:{ic:"🔧",n:"Segnalazione guasti al locatore",cat:"it_casa",tier:"standard",q:[Pq("locatore","Locatore: nome e indirizzo",true),Pq("indirizzo","Indirizzo dell'immobile",true),Pq("problema","Descrizione del guasto/difetto",true),Pq("urgenza","È urgente?",false,["Sì","No"]),Pq("dati","Suoi nome e cognome",true)]},
    it_aumento_canone:{ic:"📈",n:"Opposizione ad aumento del canone",cat:"it_casa",tier:"standard",q:[Pq("locatore","Locatore: nome e indirizzo",true),Pq("indirizzo","Indirizzo dell'immobile",true),Pq("canone_attuale","Canone attuale (€)",true),Pq("canone_nuovo","Canone richiesto (€)",true),Pq("motivi","Motivi della sua opposizione",true)]},
    it_contratto_locazione:{ic:"📄",n:"Contratto di locazione abitativa",cat:"it_casa",tier:"komplex",q:[Pq("ruolo","Lei è",true,["Locatore","Inquilino"]),Pq("controparte","Controparte: nome e codice fiscale",true),Pq("indirizzo","Indirizzo dell'immobile",true),Pq("superficie","Superficie (m²) e numero locali",false),Pq("inizio","Data di inizio",true),Pq("canone","Canone mensile (€)",true),Pq("deposito","Deposito cauzionale (€, massimo 3 mensilità)",false),Pq("spese","Spese incluse (condominio, utenze…)",false)]},
    it_reclamo_condominio:{ic:"🏢",n:"Reclamo al condominio / amministratore",cat:"it_casa",tier:"standard",q:[Pq("amministratore","Amministratore/condominio: nome",true),Pq("oggetto","Oggetto (rumori, spese, lavori, parti comuni…)",true),Pq("esposizione","Esposizione dei fatti",true),Pq("richiesta","La sua richiesta",true),Pq("dati","Suoi nome e interno/scala",true)]},

    /* Lavoro */
    it_dimissioni:{ic:"👋",n:"Lettera di dimissioni",cat:"it_lavoro",tier:"einfach",q:[Pq("azienda","Azienda: nome e indirizzo",true),Pq("posizione","La sua posizione",true),Pq("ultimo_giorno","Ultimo giorno di lavoro (rispettare il preavviso CCNL)",true),Pq("dati","Suoi nome, cognome e codice fiscale",true)]},
    it_ferie:{ic:"🏖️",n:"Richiesta ferie",cat:"it_lavoro",tier:"einfach",q:[Pq("azienda","Azienda/responsabile",true),Pq("dal","Dal (data)",true),Pq("al","Al (data)",true),Pq("dati","Suoi nome e posizione",true)]},
    it_straordinari:{ic:"⏰",n:"Rivendicazione straordinari non pagati",cat:"it_lavoro",tier:"standard",q:[Pq("azienda","Azienda: nome e indirizzo",true),Pq("posizione","La sua posizione",true),Pq("periodo","Periodo rivendicato",true),Pq("ore","Ore stimate",true),Pq("dati","Suoi nome e cognome",true)]},
    it_smart_working:{ic:"🏡",n:"Richiesta smart working",cat:"it_lavoro",tier:"einfach",q:[Pq("azienda","Azienda/responsabile",true),Pq("posizione","La sua posizione",true),Pq("regime","Regime desiderato (giorni/settimana)",true),Pq("motivo","Motivo (opzionale)",false)]},
    it_contestazione_disciplinare:{ic:"⚖️",n:"Contestazione provvedimento disciplinare",cat:"it_lavoro",tier:"komplex",q:[Pq("azienda","Azienda: nome e indirizzo",true),Pq("provvedimento","Provvedimento ricevuto (data e contenuto)",true),Pq("fatti","Fatti contestati dall'azienda",true),Pq("giustificazioni","Le sue giustificazioni/difese",true),Pq("dati","Suoi nome e posizione",true)]},
    it_certificato_lavoro:{ic:"📊",n:"Richiesta certificato di lavoro",cat:"it_lavoro",tier:"einfach",q:[Pq("azienda","Azienda/ufficio del personale",true),Pq("periodo","Periodo di impiego e posizione",true),Pq("scopo","Scopo del certificato (opzionale)",false),Pq("dati","Suoi nome, cognome e codice fiscale",true)]},
    it_congedo_parentale:{ic:"👶",n:"Richiesta congedo parentale",cat:"it_lavoro",tier:"standard",q:[Pq("azienda","Azienda/ufficio del personale",true),Pq("figlio","Figlio/a (data di nascita)",true),Pq("periodo","Periodo richiesto",true),Pq("dati","Suoi nome e posizione",true)]},

    /* Consumatori e Controversie */
    it_recesso_14:{ic:"🔄",n:"Recesso entro 14 giorni (Codice del Consumo)",cat:"it_consumo",tier:"einfach",q:[Pq("venditore","Venditore: nome e indirizzo",true),Pq("ordine","Numero d'ordine",true),Pq("data","Data di acquisto/consegna",true),Pq("prodotti","Prodotti interessati",true),Pq("iban","IBAN per il rimborso (opzionale)",false)]},
    it_garanzia:{ic:"🔧",n:"Reclamo per prodotto difettoso (garanzia legale 2 anni)",cat:"it_consumo",tier:"standard",q:[Pq("venditore","Venditore: nome e indirizzo",true),Pq("prodotto","Prodotto (marca/modello)",true),Pq("data_acquisto","Data di acquisto",true),Pq("difetto","Difetto riscontrato",true),Pq("richiesta","La sua richiesta",true,["Riparazione","Sostituzione","Riduzione del prezzo","Risoluzione/rimborso"])]},
    it_compagnia_aerea:{ic:"✈️",n:"Reclamo a compagnia aerea (EU 261/2004)",cat:"it_consumo",tier:"standard",q:[Pq("compagnia","Compagnia aerea",true),Pq("volo","Numero volo e data",true),Pq("problema","Problema",true,["Ritardo","Cancellazione","Overbooking","Bagaglio"]),Pq("richiesta","La sua richiesta (compensazione EU 261/2004…)",true),Pq("dati","Suoi nome e cognome",true)]},
    it_diffida:{ic:"📨",n:"Diffida / messa in mora",cat:"it_consumo",tier:"standard",q:[Pq("destinatario","Destinatario: nome e indirizzo",true),Pq("fatti","Fatti (cosa è successo, quando)",true),Pq("richiesta","Richiesta e termine (es. 15 giorni)",true),Pq("importo","Importo dovuto (€, opzionale)",false),Pq("dati","Suoi nome, cognome e indirizzo",true)]},
    it_addebito_bancario:{ic:"💳",n:"Contestazione addebito bancario",cat:"it_consumo",tier:"standard",q:[Pq("banca","Banca: nome e filiale",true),Pq("addebito","Addebito contestato (data, importo €, descrizione)",true),Pq("motivo","Motivo della contestazione",true),Pq("dati","Suoi nome e IBAN",true)]},
    it_reclamo_telefonia:{ic:"📶",n:"Reclamo a operatore telefonico (AGCOM)",cat:"it_consumo",tier:"standard",q:[Pq("operatore","Operatore",true),Pq("codice_cliente","Codice cliente",true),Pq("problema","Problema (fatturazione, vincoli, servizio)",true),Pq("richiesta","La sua richiesta",true),Pq("dati","Suoi nome e codice fiscale",true)]},

    /* Contratti */
    it_riconoscimento_debito:{ic:"💶",n:"Riconoscimento di debito",cat:"it_contratti",tier:"standard",q:[Pq("creditore","Creditore: nome e indirizzo",true),Pq("debitore","Debitore: nome e indirizzo",true),Pq("importo","Importo (€, in cifre e in lettere)",true),Pq("termini","Termini/modalità di restituzione",true),Pq("interessi","Con interessi?",false,["Senza interessi","Con interessi"])]},
    it_compravendita:{ic:"🛒",n:"Contratto di compravendita tra privati",cat:"it_contratti",tier:"einfach",q:[Pq("ruolo","Lei è",true,["Venditore","Acquirente"]),Pq("controparte","Controparte: nome e codice fiscale",true),Pq("oggetto","Oggetto della vendita",true),Pq("prezzo","Prezzo (€)",true),Pq("consegna","Data di consegna",false)]},
    it_comodato:{ic:"🤝",n:"Contratto di comodato d'uso",cat:"it_contratti",tier:"standard",q:[Pq("comodante","Comodante: nome e codice fiscale",true),Pq("comodatario","Comodatario: nome e codice fiscale",true),Pq("bene","Bene concesso in comodato",true),Pq("durata","Durata (opzionale)",false),Pq("condizioni","Condizioni particolari (opzionale)",false)]},
    it_delega:{ic:"📝",n:"Delega / procura semplice",cat:"it_contratti",tier:"einfach",q:[Pq("delegato","Persona delegata: nome e codice fiscale/documento",true),Pq("oggetto","Oggetto della delega",true),Pq("validita","Validità (opzionale)",false),Pq("dati","Suoi (delegante) nome e codice fiscale",true)]},
    it_nda:{ic:"🔒",n:"Accordo di riservatezza (NDA)",cat:"it_contratti",tier:"standard",q:[Pq("controparte","Controparte: nome e indirizzo",true),Pq("oggetto","Oggetto della collaborazione",true),Pq("ambito","Ambito delle informazioni riservate",true),Pq("durata","Durata (opzionale)",false)]},
    it_prestazione_opera:{ic:"🧰",n:"Contratto di prestazione d'opera",cat:"it_contratti",tier:"komplex",q:[Pq("ruolo","Lei è",true,["Prestatore","Committente"]),Pq("controparte","Controparte: nome e codice fiscale/P.IVA",true),Pq("opera","Descrizione dell'opera/servizio",true),Pq("compenso","Compenso e modalità di pagamento (€)",true),Pq("termini","Termini/durata",false),Pq("clausole","Clausole speciali (opzionale)",false)]},
    it_prestito:{ic:"💰",n:"Contratto di prestito tra privati",cat:"it_contratti",tier:"standard",q:[Pq("mutuante","Mutuante: nome e codice fiscale",true),Pq("mutuatario","Mutuatario: nome e codice fiscale",true),Pq("importo","Importo prestato (€)",true),Pq("restituzione","Piano di restituzione",true),Pq("interessi","Interessi (%, opzionale)",false)]},

    /* Pubblica Amministrazione */
    it_autocertificazione:{ic:"📋",n:"Autocertificazione (DPR 445/2000)",cat:"it_pubblica",tier:"einfach",q:[Pq("oggetto","Cosa autocertificare",true,["Residenza","Stato di famiglia","Nascita","Titolo di studio","Altro"]),Pq("uso","Amministrazione/uso di destinazione",true),Pq("dati","Suoi nome, cognome, luogo e data di nascita, codice fiscale",true)]},
    it_ricorso_multa:{ic:"🚗",n:"Ricorso contro multa (Giudice di Pace/Prefetto)",cat:"it_pubblica",tier:"standard",q:[Pq("autorita","Autorità",true,["Giudice di Pace","Prefetto"]),Pq("verbale","Numero del verbale",true),Pq("data","Data della violazione contestata",true),Pq("targa","Targa del veicolo",false),Pq("motivi","Motivi del ricorso",true),Pq("dati","Suoi nome, cognome e codice fiscale",true)]},
    it_agenzia_entrate:{ic:"🧾",n:"Istanza in autotutela all'Agenzia delle Entrate",cat:"it_pubblica",tier:"komplex",q:[Pq("ufficio","Ufficio dell'Agenzia delle Entrate",true),Pq("riferimento","Numero atto/avviso di riferimento",true),Pq("atto","Atto contestato (avviso, cartella, sanzione…)",true),Pq("motivi","Motivi dell'istanza",true),Pq("dati","Suoi nome, cognome e codice fiscale",true)]},
    it_rateizzazione:{ic:"📅",n:"Richiesta rateizzazione cartella esattoriale",cat:"it_pubblica",tier:"standard",q:[Pq("ufficio","Agenzia delle Entrate-Riscossione (sede)",true),Pq("cartella","Cartella (numero e importo €)",true),Pq("situazione","Situazione economica",true),Pq("rate","Numero di rate proposte (opzionale)",false),Pq("dati","Suoi nome e codice fiscale",true)]},
    it_inps:{ic:"🏥",n:"Ricorso / istanza all'INPS",cat:"it_pubblica",tier:"standard",q:[Pq("sede","Sede INPS",true),Pq("oggetto","Oggetto (pensione, prestazione, contributi…)",true),Pq("esposizione","Esposizione dei fatti",true),Pq("richiesta","La sua richiesta",true),Pq("dati","Suoi nome, codice fiscale e matricola INPS (opzionale)",true)]},
    it_accesso_atti:{ic:"📂",n:"Richiesta di accesso agli atti (L. 241/1990)",cat:"it_pubblica",tier:"standard",q:[Pq("amministrazione","Amministrazione destinataria",true),Pq("documenti","Documenti richiesti",true),Pq("interesse","Interesse/motivazione della richiesta",true),Pq("modalita","Modalità",false,["Visione","Copia semplice","Copia conforme"]),Pq("dati","Suoi nome, cognome e codice fiscale",true)]},
    it_cambio_residenza:{ic:"🏠",n:"Dichiarazione di cambio di residenza",cat:"it_pubblica",tier:"einfach",q:[Pq("comune","Comune di destinazione",true),Pq("indirizzo","Nuovo indirizzo",true),Pq("nucleo","Componenti del nucleo familiare (opzionale)",false),Pq("dati","Suoi nome, cognome e codice fiscale",true)]}
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
      return (typeof DOCS!=="undefined" && DOCS.it_diffida)?true:false;
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
if($src!==$start){ file_put_contents($file,$src); echo "Italya modulu eklendi (CH_IT_MODULE) - ~39 belge, 6 kategori, Italyanca.\n"; }
else echo "degisiklik yok\n";
echo "\nNOT: Belge dili/hukuku icin apply-lawsys-all.php de calistirilmali (lawSystems['IT'] + doc-lang).\n";
echo "SONRA: opcache-reset (pull-updates otomatik yapar). SIL: rm apply-italia.php\n";
echo "Test: Ayarlar -> Italia sec -> kategoriler Italyanca -> 'Diffida / messa in mora' uret.\n";
