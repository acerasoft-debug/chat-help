<?php
/** ChatHelp — apply-it-expand-more (CH_IT_EXPAND_MORE) — IT katalog genisletme
 *  Workflow ile uretildi: +16 belge, 4 kategori. Deferred injection.
 *  KULLANIM: pull-updates.php?files=apply-it-expand-more.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-it-expand-more BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_IT_EXPAND_MORE')!==false) exit("Zaten ekli (CH_IT_EXPAND_MORE).\n");
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if (strpos($src,$anchor)===false) exit("HATA: anchor (getDocPrice) yok.\n");
$js = <<<'CHJS'

/* CH_IT_EXPAND_MORE — IT katalog genisletme (workflow, deferred) */
try{(function(){
  function Pe(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var NEWCATS={
    "it_famiglia":{n:"Famiglia",ic:"👨‍👩‍👧"},
    "it_debiti":{n:"Debiti e Crediti",ic:"💰"},
    "it_privacy":{n:"Privacy e Dati Personali",ic:"🔐"},
    "it_eredita":{n:"Successioni ed Eredità",ic:"📜"}
  };
  var E={
    "itx_separazione_consensuale":{ic:"📝",n:"Accordo di separazione consensuale",cat:"it_famiglia",tier:"komplex",q:[Pe("coniuge","Dati dell'altro coniuge (nome, cognome, codice fiscale)",true),Pe("matrimonio","Data e luogo di celebrazione del matrimonio",true),Pe("regime","Regime patrimoniale",true,["Comunione dei beni","Separazione dei beni"]),Pe("figli","Figli minori o non autosufficienti (nomi ed età)",false),Pe("accordi","Condizioni della separazione (assegno di mantenimento, assegnazione della casa coniugale, affidamento dei figli)",true),Pe("dati","I Suoi dati (nome, cognome, codice fiscale, residenza)",true)]},
    "itx_assegno_mantenimento":{ic:"💶",n:"Richiesta di assegno di mantenimento",cat:"it_famiglia",tier:"standard",q:[Pe("destinatario","Destinatario (ex coniuge / altro genitore): nome e indirizzo",true),Pe("beneficiari","A favore di chi (Lei stesso/a e/o figli, con età)",true),Pe("importo","Importo mensile richiesto (€)",true),Pe("situazione","Situazione economica e spese da coprire",true),Pe("dati","I Suoi dati (nome, cognome, codice fiscale)",true)]},
    "itx_riconoscimento_figlio":{ic:"👶",n:"Dichiarazione di riconoscimento di figlio",cat:"it_famiglia",tier:"standard",q:[Pe("figlio","Dati del figlio/a (nome, data e luogo di nascita)",true),Pe("altro_genitore","Altro genitore (nome e cognome)",false),Pe("ufficio","Comune / ufficiale di stato civile presso cui rendere la dichiarazione",true),Pe("cognome","Cognome che si intende attribuire al figlio/a",false),Pe("dati","I Suoi dati (nome, cognome, codice fiscale)",true)]},
    "itx_amministrazione_sostegno":{ic:"🧑‍⚖️",n:"Ricorso per amministratore di sostegno",cat:"it_famiglia",tier:"komplex",q:[Pe("beneficiario","Beneficiario (persona da assistere): nome, età e residenza",true),Pe("relazione","Suo rapporto con il beneficiario",true,["Coniuge","Figlio/a","Genitore","Fratello/Sorella","Altro parente","Altro"]),Pe("infermita","Infermità o menomazione (descrizione della condizione)",true),Pe("esigenze","Atti ed esigenze per cui serve assistenza (gestione conto, cure, contratti…)",true),Pe("proposto","Amministratore di sostegno proposto (nome, se noto)",false),Pe("tribunale","Giudice tutelare presso il Tribunale del luogo di residenza o domicilio del beneficiario",true)]},
    "itx_sollecito_pagamento":{ic:"📨",n:"Sollecito di pagamento",cat:"it_debiti",tier:"einfach",q:[Pe("debitore","Debitore: nome e indirizzo",true),Pe("causale","Causale del credito (fattura, prestazione, prestito…)",true),Pe("importo","Importo dovuto (€)",true),Pe("termini","Scadenza originaria e nuovo termine concesso",true),Pe("dati","I Suoi dati (nome/ragione sociale e recapiti)",true)]},
    "itx_piano_rientro":{ic:"📅",n:"Proposta di piano di rientro del debito",cat:"it_debiti",tier:"standard",q:[Pe("creditore","Creditore: nome e indirizzo",true),Pe("debito","Importo complessivo del debito (€)",true),Pe("causale","Origine del debito",true),Pe("proposta","Proposta di rateizzazione (importo rata e numero di rate)",true),Pe("decorrenza","Data di decorrenza proposta",false),Pe("dati","I Suoi dati (nome, cognome, codice fiscale)",true)]},
    "itx_opposizione_decreto_ingiuntivo":{ic:"⚖️",n:"Opposizione a decreto ingiuntivo",cat:"it_debiti",tier:"komplex",q:[Pe("decreto","Decreto ingiuntivo (numero, data e tribunale)",true),Pe("notifica","Data di notifica del decreto",true),Pe("creditore","Creditore opposto (ricorrente): nome e indirizzo",true),Pe("importo","Importo ingiunto (€)",true),Pe("motivi","Motivi dell'opposizione (contestazioni sul credito)",true),Pe("dati","I Suoi dati (nome, cognome, codice fiscale)",true)]},
    "itx_saldo_stralcio":{ic:"🤝",n:"Proposta di saldo e stralcio",cat:"it_debiti",tier:"standard",q:[Pe("creditore","Creditore / società di recupero: nome e indirizzo",true),Pe("pratica","Numero pratica o riferimento",false),Pe("debito","Debito residuo comunicato (€)",true),Pe("offerta","Importo offerto a saldo e stralcio (€)",true),Pe("modalita","Modalità di pagamento offerta",false,["Unica soluzione","Rateale"]),Pe("dati","I Suoi dati (nome, cognome, codice fiscale)",true)]},
    "itx_gdpr_accesso":{ic:"📂",n:"Richiesta di accesso ai dati personali (art. 15 GDPR)",cat:"it_privacy",tier:"einfach",q:[Pe("titolare","Titolare del trattamento (azienda/ente): nome e indirizzo",true),Pe("rapporto","Suo rapporto con il titolare (cliente, ex dipendente, utente…)",false),Pe("dati_richiesti","Dati o informazioni richieste (se vuoto, si intendono tutti)",false),Pe("dati","I Suoi dati identificativi (nome, cognome, recapiti)",true)]},
    "itx_gdpr_cancellazione":{ic:"🗑️",n:"Richiesta di cancellazione dei dati / diritto all'oblio (art. 17 GDPR)",cat:"it_privacy",tier:"standard",q:[Pe("titolare","Titolare del trattamento: nome e indirizzo",true),Pe("dati_cancellare","Dati di cui si chiede la cancellazione",true),Pe("motivo","Motivo della richiesta",true,["Consenso revocato","Dati non più necessari","Trattamento illecito","Opposizione al trattamento","Altro"]),Pe("riferimento","Riferimento del rapporto o dell'account",false),Pe("dati","I Suoi dati identificativi (nome, cognome, recapiti)",true)]},
    "itx_gdpr_opposizione_marketing":{ic:"📵",n:"Opposizione al trattamento per marketing (art. 21 GDPR)",cat:"it_privacy",tier:"einfach",q:[Pe("titolare","Titolare del trattamento (azienda): nome e indirizzo",true),Pe("canali","Canali da bloccare",true,["Email","SMS","Telefono","Posta cartacea","Tutti"]),Pe("riferimento","Email / numero / riferimento associato",false),Pe("dati","I Suoi dati identificativi (nome, cognome)",true)]},
    "itx_gdpr_reclamo_garante":{ic:"🏛️",n:"Reclamo al Garante per la protezione dei dati personali",cat:"it_privacy",tier:"komplex",q:[Pe("titolare","Titolare / soggetto segnalato: nome e indirizzo",true),Pe("violazione","Violazione lamentata (descrizione dei fatti)",true),Pe("diritti","Diritti violati o richieste rimaste senza riscontro",true),Pe("pregressa","Ha già contattato il titolare?",true,["Sì, senza riscontro","Sì, con riscontro insoddisfacente","No"]),Pe("documenti","Documenti o prove che allega",false),Pe("dati","I Suoi dati (nome, cognome, codice fiscale, recapiti)",true)]},
    "itx_rinuncia_eredita":{ic:"🚫",n:"Dichiarazione di rinuncia all'eredità",cat:"it_eredita",tier:"standard",q:[Pe("defunto","Dati del defunto (nome, data di morte, ultima residenza)",true),Pe("relazione","Suo rapporto di parentela con il defunto",true),Pe("beni","Eredità/beni a cui si rinuncia (descrizione, se nota)",false),Pe("sede","Tribunale o notaio competente (luogo di apertura della successione)",true),Pe("dati","I Suoi dati (nome, cognome, codice fiscale)",true)]},
    "itx_accettazione_beneficio":{ic:"📋",n:"Accettazione dell'eredità con beneficio d'inventario",cat:"it_eredita",tier:"komplex",q:[Pe("defunto","Dati del defunto (nome, data di morte, ultima residenza)",true),Pe("relazione","Suo titolo di chiamata all'eredità (grado di parentela)",true),Pe("attivo","Beni noti dell'asse ereditario (attivo)",false),Pe("passivo","Debiti noti del defunto (passivo)",false),Pe("sede","Tribunale o notaio competente",true),Pe("dati","I Suoi dati (nome, cognome, codice fiscale)",true)]},
    "itx_atto_notorio_successione":{ic:"🖋️",n:"Dichiarazione sostitutiva di atto di notorietà per successione",cat:"it_eredita",tier:"standard",q:[Pe("defunto","Dati del defunto (nome, codice fiscale, data e luogo di morte, ultima residenza)",true),Pe("eredi","Eredi (nomi, codici fiscali e grado di parentela con il defunto)",true),Pe("testamento","Esistenza di testamento",true,["Sì, testamento pubblicato","No, successione legittima","Non so"]),Pe("destinatario","Ente/soggetto a cui presentare la dichiarazione (banca, INPS, Poste, PA…)",true),Pe("finalita","Finalità della dichiarazione (svincolo conto, riscossione pensione/ratei, voltura…)",false),Pe("dati","I Suoi dati (nome, cognome, codice fiscale, qualità di erede)",true)]},
    "itx_divisione_eredita":{ic:"📐",n:"Richiesta di divisione dell'eredità",cat:"it_eredita",tier:"komplex",q:[Pe("defunto","Dati del defunto e data di apertura della successione",true),Pe("coeredi","Coeredi (nomi e quote spettanti)",true),Pe("beni","Beni da dividere (descrizione)",true),Pe("proposta","Proposta di divisione o assegnazione dei beni",true),Pe("dati","I Suoi dati (nome, cognome, codice fiscale)",true)]}
  };
  function inject(){
    try{
      if(typeof DOCS==="undefined"||!DOCS) return false;
      if(typeof CATS==="undefined"||!CATS) return false;
      for(var k in E){ if(!DOCS[k]) DOCS[k]=E[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=E[k].tier||"standard"; }
      for(var nc in NEWCATS){ if(!CATS[nc]) CATS[nc]={n:NEWCATS[nc].n,ic:NEWCATS[nc].ic,docs:[],country:"IT"}; if(!CATS[nc].docs) CATS[nc].docs=[]; }
      for(var k2 in E){ var c2=E[k2].cat; if(CATS[c2]&&CATS[c2].docs&&CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); }
      if(typeof CAT_LABELS!=="undefined"){ for(var cl in NEWCATS) CAT_LABELS[cl]=NEWCATS[cl].n; }
      if(typeof CAT_DOCS!=="undefined"){
        for(var cd in NEWCATS){ if(!CAT_DOCS[cd]) CAT_DOCS[cd]=[]; }
        for(var k3 in E){ var c3=E[k3].cat; if(CAT_DOCS[c3]){ var ex=false; for(var i=0;i<CAT_DOCS[c3].length;i++){ if(CAT_DOCS[c3][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[c3].push({k:k3,ic:E[k3].ic,t:E[k3].n,tier:E[k3].tier||"standard"}); } }
      }
      return !!(DOCS.itx_separazione_consensuale);
    }catch(e){ return false; }
  }
  var done=false, ticks=0;
  function run(){ if(!done){ if(inject()) done=true; } if(ticks++<40) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}

CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
$tmp = tempnam(sys_get_temp_dir(),'it').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-itmore-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "  ✓ IT: +16 belge, 4 kategori eklendi\n\n✓ CH_IT_EXPAND_MORE uygulandi.\n";
