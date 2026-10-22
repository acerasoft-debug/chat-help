<?php
/**
 * ChatHelp — apply-itgb-vertrag (CH_ITGB_VERTRAG) — IT (Codice civile) ve
 *  GB (England & Wales) sozlesme katalogunu genislet.
 *  Su an IT: separazione+locazione (2), GB: separation+ast (2). Bu script her
 *  birine +6 sozlesme ekler (form=XSETS + PDF metni=NCX). Enjeksiyon: CH_VST_X
 *  callback'indeki `var xBak=null,xMode=null;` anchor'i ONUNE — orada XSETS.IT,
 *  XSETS.GB, NCX, ucStep, ekIT, ekGB, V hepsi scope'ta. Tek str_replace,
 *  count-guard. Gomulu JS node --check ile dogrulandi.
 * KULLANIM: pull2.php?key=...&files=apply-itgb-vertrag.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-itgb-vertrag BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_ITGB_VERTRAG')!==false) exit("Zaten ekli (CH_ITGB_VERTRAG).\n");
if (strpos($src,'CH_VST_X')===false) exit("HATA: CH_VST_X yok — once apply-vst-x deploy edilmeli.\n");

$inj = <<<'CHJS'
/* ══ CH_ITGB_VERTRAG — IT/GB sozlesme katalogu genislemesi ══ */
    try{
      /* ── IT formlari (XSETS.IT'e ekle) ── */
      var _itAdd={
        "vtr_it_vendita":{ic:"🛒",n:"Contratto di compravendita",sn:"Vendita di bene mobile — art. 1470 e ss. c.c.",price:"3,99 €",pgs:4,steps:[
          {t:"Parti",f:[["venditore","Venditore (nome, C.F., domicilio)","","ta"],["compratore","Compratore (nome, C.F., domicilio)","","ta"]]},
          {t:"Oggetto e prezzo",f:[["oggetto","Oggetto (descrizione; se veicolo: marca, tipo, targa, telaio, km)","","ta"],["stato","Stato / vizi noti","Il venditore dichiara di non essere a conoscenza di vizi occulti."],["prezzo","Prezzo (€)"],["pagamento","Pagamento","in contanti, alla consegna"],["garanzia","Garanzia","vendita tra privati senza garanzia, salvo dolo (art. 1490 c.c.)"]]},
          {t:"Consegna e firma",f:[["consegna","Consegna (luogo e data)"],["rischio","Passaggio del rischio","La proprietà e il rischio passano al compratore con la consegna (art. 1465 c.c.)."],["ort","Luogo"],["datum","Data"]]},
          ucStep("Pattuizioni proprie (facoltativo)","Clausola — titolo","Clausola — testo")]},
        "vtr_it_lavoro":{ic:"💼",n:"Contratto di lavoro subordinato",sn:"a tempo indeterminato — Codice civile / CCNL",price:"4,99 €",pgs:5,steps:[
          {t:"Parti",f:[["datore","Datore di lavoro (ragione sociale, P.IVA, sede)","","ta"],["lavoratore","Lavoratore (nome, C.F.)","","ta"]]},
          {t:"Mansione e retribuzione",f:[["mansione","Mansione / livello di inquadramento"],["sede","Sede di lavoro"],["inizio","Data di assunzione"],["ccnl","CCNL applicabile"],["retribuzione","Retribuzione lorda mensile (€)"],["mensilita","Mensilità","13 o 14 secondo CCNL"],["prova","Periodo di prova","secondo CCNL (art. 2096 c.c.)"]]},
          {t:"Orario e ferie",f:[["orario","Orario di lavoro","40 ore settimanali"],["distribuzione","Distribuzione","secondo le esigenze aziendali"],["ferie","Ferie","secondo CCNL, minimo 4 settimane (art. 10 D.Lgs. 66/2003)"],["riservatezza","Riservatezza","Il lavoratore mantiene il segreto sulle informazioni aziendali durante e dopo il rapporto.","ta"]]},
          {t:"Firma",f:[["ort","Luogo"],["datum","Data"]]},
          ucStep("Pattuizioni proprie (facoltativo)","Clausola — titolo","Clausola — testo")]},
        "vtr_it_mutuo":{ic:"💶",n:"Contratto di mutuo (prestito)",sn:"Prestito di denaro — art. 1813 e ss. c.c.",price:"3,99 €",pgs:4,steps:[
          {t:"Parti",f:[["mutuante","Mutuante (nome, C.F., domicilio)","","ta"],["mutuatario","Mutuatario (nome, C.F., domicilio)","","ta"]]},
          {t:"Somma e interessi",f:[["importo","Importo del mutuo (€, in cifre e lettere)","","ta"],["erogazione","Erogazione","mediante bonifico alla data di firma"],["interessi","Interessi","senza interessi / annuo ____ %"],["restituzione","Restituzione (termine o rate)","","ta"]]},
          {t:"Mora e firma",f:[["mora","Mora","In caso di ritardo sono dovuti gli interessi legali."],["decadenza","Decadenza dal termine","Il mancato pagamento di due rate rende esigibile l'intero residuo."],["ort","Luogo"],["datum","Data"]]},
          ucStep("Pattuizioni proprie (facoltativo)","Clausola — titolo","Clausola — testo")]},
        "vtr_it_prestazione":{ic:"🔧",n:"Contratto di prestazione d'opera",sn:"Lavoro autonomo — art. 2222 e ss. c.c.",price:"4,99 €",pgs:4,steps:[
          {t:"Parti",f:[["committente","Committente (nome/ragione sociale, P.IVA)","","ta"],["prestatore","Prestatore d'opera (nome, P.IVA)","","ta"]]},
          {t:"Prestazione e compenso",f:[["prestazione","Descrizione della prestazione / ambito","","ta"],["termine","Termine di esecuzione"],["compenso","Compenso (€) + IVA","oltre IVA ove dovuta"],["pagamento","Modalità di pagamento","30% all'ordine, 70% alla consegna"],["autonomia","Natura","Rapporto autonomo; il prestatore opera senza vincolo di subordinazione."]]},
          {t:"Responsabilità e firma",f:[["proprieta","Proprietà del risultato","Gli elaborati spettano al committente dopo il saldo integrale."],["riserv","Riservatezza","Le parti mantengono riservate le informazioni scambiate."],["ort","Luogo"],["datum","Data"]]},
          ucStep("Pattuizioni proprie (facoltativo)","Clausola — titolo","Clausola — testo")]},
        "vtr_it_riservatezza":{ic:"🔒",n:"Accordo di riservatezza (NDA)",sn:"Riservatezza reciproca",price:"4,99 €",pgs:4,steps:[
          {t:"Parti",f:[["p1","Parte 1 (nome/ragione sociale, P.IVA)","","ta"],["p2","Parte 2 (nome/ragione sociale, P.IVA)","","ta"]]},
          {t:"Oggetto",f:[["finalita","Finalità della collaborazione","","ta"],["info","Informazioni riservate","Ogni informazione tecnica, commerciale e personale scambiata, orale, scritta o elettronica, è riservata."],["eccezioni","Eccezioni","Sono escluse le informazioni di dominio pubblico o lecitamente ottenute altrimenti."]]},
          {t:"Obbligo e durata",f:[["obbligo","Obbligo","Le parti usano le informazioni solo per la finalità e non le divulgano a terzi."],["durata","Durata","La riservatezza permane ____ anni dopo la fine della collaborazione."],["penale","Penale (facoltativo)","La violazione obbliga al risarcimento del danno subito."],["ort","Luogo"],["datum","Data"]]},
          ucStep("Pattuizioni proprie (facoltativo)","Clausola — titolo","Clausola — testo")]},
        "vtr_it_comodato":{ic:"🔑",n:"Contratto di comodato",sn:"Concessione gratuita in uso — art. 1803 e ss. c.c.",price:"3,99 €",pgs:3,steps:[
          {t:"Parti e bene",f:[["comodante","Comodante (nome, domicilio)","","ta"],["comodatario","Comodatario (nome, domicilio)","","ta"],["bene","Bene concesso (descrizione, stato)","","ta"]]},
          {t:"Uso e restituzione",f:[["uso","Uso","Il comodatario usa il bene secondo la sua destinazione e con la diligenza del buon padre di famiglia (art. 1804 c.c.)."],["durata","Durata / data di restituzione"],["spese","Spese","Le spese ordinarie di conservazione sono a carico del comodatario."],["restituzione","Restituzione","Il bene è restituito nello stato iniziale, salvo il normale deterioramento d'uso."],["ort","Luogo"],["datum","Data"]]},
          ucStep("Pattuizioni proprie (facoltativo)","Clausola — titolo","Clausola — testo")]}
      };
      /* ── GB formlari (XSETS.GB'ye ekle) ── */
      var _gbAdd={
        "vtr_gb_sale":{ic:"🛒",n:"Sale Agreement (private goods)",sn:"Sale of Goods Act 1979 — England & Wales",price:"3,99 €",pgs:4,steps:[
          {t:"Parties",f:[["seller","Seller (name, address)","","ta"],["buyer","Buyer (name, address)","","ta"]]},
          {t:"Goods and price",f:[["goods","Goods (description; if vehicle: make, model, registration, VIN, mileage)","","ta"],["condition","Condition / known faults","The seller is not aware of any hidden defects."],["price","Price (£)"],["payment","Payment","in full on delivery"],["title","Title & warranty","Sold as seen between private parties; the seller warrants good title (s.12 SGA 1979)."]]},
          {t:"Delivery and signature",f:[["delivery","Delivery (place and date)"],["risk","Passing of risk","Title and risk pass to the buyer on delivery."],["ort","Signed at"],["datum","Date"]]},
          ucStep("Special terms (optional)","Term — title","Term — text")]},
        "vtr_gb_employment":{ic:"💼",n:"Employment Contract (permanent)",sn:"Employment Rights Act 1996 — England & Wales",price:"4,99 €",pgs:5,steps:[
          {t:"Parties",f:[["employer","Employer (name, address)","","ta"],["employee","Employee (name, address)","","ta"]]},
          {t:"Role and pay",f:[["role","Job title / role"],["location","Place of work"],["start","Start date"],["salary","Annual gross salary (£)"],["payment","Payment","monthly in arrears"],["hours","Hours","full-time, as required by the role"],["probation","Probation","as stated (typically 3–6 months)"]]},
          {t:"Holiday and confidentiality",f:[["holiday","Holiday","28 days including bank holidays (Working Time Regulations 1998)"],["notice","Notice period","as per statute and length of service (s.86 ERA 1996)"],["confidentiality","Confidentiality","The employee keeps the employer's confidential information secret during and after employment.","ta"]]},
          {t:"Signature",f:[["ort","Signed at"],["datum","Date"]]},
          ucStep("Special terms (optional)","Term — title","Term — text")]},
        "vtr_gb_loan":{ic:"💶",n:"Loan Agreement (money)",sn:"Simple loan between parties — England & Wales",price:"3,99 €",pgs:4,steps:[
          {t:"Parties",f:[["lender","Lender (name, address)","","ta"],["borrower","Borrower (name, address)","","ta"]]},
          {t:"Loan and interest",f:[["amount","Loan amount (£, in figures and words)","","ta"],["advance","Advance","by bank transfer on the date of this agreement"],["interest","Interest","interest-free / ____ % per year"],["repayment","Repayment (date or instalments)","","ta"]]},
          {t:"Default and signature",f:[["default","Default","On late payment, statutory interest is payable."],["acceleration","Acceleration","Failure to pay two instalments makes the whole balance due."],["ort","Signed at"],["datum","Date"]]},
          ucStep("Special terms (optional)","Term — title","Term — text")]},
        "vtr_gb_services":{ic:"🔧",n:"Services Agreement",sn:"Supply of Goods and Services Act 1982 — England & Wales",price:"4,99 €",pgs:4,steps:[
          {t:"Parties",f:[["client","Client (name/company, no.)","","ta"],["provider","Service provider (name/company, no.)","","ta"]]},
          {t:"Services and fee",f:[["services","Description of services / scope","","ta"],["timeline","Timeline"],["fee","Fee (£) + VAT","plus VAT where applicable"],["payment","Payment terms","30% on order, 70% on completion"],["status","Status","Commercial relationship; the provider acts independently, not as an employee."]]},
          {t:"IP and signature",f:[["ip","Ownership of deliverables","Deliverables pass to the client on full payment."],["confid","Confidentiality","Each party keeps the exchanged information confidential."],["ort","Signed at"],["datum","Date"]]},
          ucStep("Special terms (optional)","Term — title","Term — text")]},
        "vtr_gb_nda":{ic:"🔒",n:"Non-Disclosure Agreement (NDA)",sn:"Mutual confidentiality — England & Wales",price:"4,99 €",pgs:4,steps:[
          {t:"Parties",f:[["p1","Party 1 (name/company)","","ta"],["p2","Party 2 (name/company)","","ta"]]},
          {t:"Purpose",f:[["purpose","Purpose of the collaboration","","ta"],["info","Confidential information","All technical, commercial and personal information exchanged, whether oral, written or electronic, is confidential."],["exceptions","Exceptions","Information in the public domain or lawfully obtained elsewhere is excluded."]]},
          {t:"Obligation and term",f:[["obligation","Obligation","The parties use the information only for the purpose and do not disclose it to third parties."],["term","Term","Confidentiality survives ____ years after the collaboration ends."],["remedy","Remedy","Breach entitles the innocent party to damages and injunctive relief."],["ort","Signed at"],["datum","Date"]]},
          ucStep("Special terms (optional)","Term — title","Term — text")]},
        "vtr_gb_loan_use":{ic:"🔑",n:"Loan for Use Agreement",sn:"Gratuitous bailment / loan of a chattel — England & Wales",price:"3,99 €",pgs:3,steps:[
          {t:"Parties and item",f:[["lender","Lender (name, address)","","ta"],["borrower","Borrower (name, address)","","ta"],["item","Item lent (description, condition)","","ta"]]},
          {t:"Use and return",f:[["use","Use","The borrower uses the item only for its intended purpose and with reasonable care."],["term","Term / return date"],["costs","Costs","Ordinary upkeep is borne by the borrower."],["return","Return","The item is returned in its original condition, fair wear and tear excepted."],["ort","Signed at"],["datum","Date"]]},
          ucStep("Special terms (optional)","Term — title","Term — text")]}
      };
      if(typeof XSETS!=="undefined"&&XSETS){
        XSETS.IT=XSETS.IT||{}; for(var _ki in _itAdd){ XSETS.IT[_ki]=_itAdd[_ki]; }
        XSETS.GB=XSETS.GB||{}; for(var _kg in _gbAdd){ XSETS.GB[_kg]=_gbAdd[_kg]; }
      }
      /* ── PDF metinleri (NCX) ── */
      if(typeof NCX!=="undefined"&&NCX){
        var _ekIT=(typeof ekIT==="function")?ekIT:function(d){return ekX(d,"Non sussistono ulteriori pattuizioni particolari.");};
        var _ekGB=(typeof ekGB==="function")?ekGB:function(d){return ekX(d,"No further special terms are agreed.");};
        NCX["vtr_it_vendita"]={n:"CONTRATTO DI COMPRAVENDITA",sn:"art. 1470 e ss. c.c.",A:"Venditore",B:"Compratore",cl:[
          {no:"Art. 1",t:"Parti",b:function(d){return "Tra "+V(d.venditore)+" (venditore) e "+V(d.compratore)+" (compratore) si conviene quanto segue.";}},
          {no:"Art. 2",t:"Oggetto",b:function(d){return "Il venditore vende al compratore: "+V(d.oggetto)+". Stato: "+V(d.stato)+".";}},
          {no:"Art. 3",t:"Prezzo e pagamento",b:function(d){return "Prezzo: "+V(d.prezzo)+". Pagamento: "+V(d.pagamento)+".";}},
          {no:"Art. 4",t:"Consegna e garanzia",b:function(d){return "Consegna: "+V(d.consegna)+". "+V(d.rischio)+" Garanzia: "+V(d.garanzia)+".";}},
          {no:"Art. 5",t:"Pattuizioni particolari",b:_ekIT}]};
        NCX["vtr_it_lavoro"]={n:"CONTRATTO DI LAVORO SUBORDINATO",sn:"a tempo indeterminato — Codice civile / CCNL",A:"Datore di lavoro",B:"Lavoratore",cl:[
          {no:"Art. 1",t:"Parti",b:function(d){return V(d.datore)+" (datore di lavoro) assume "+V(d.lavoratore)+" (lavoratore) a tempo indeterminato.";}},
          {no:"Art. 2",t:"Mansione e orario",b:function(d){return "Mansione: "+V(d.mansione)+". Sede: "+V(d.sede)+". Assunzione: "+V(d.inizio)+". CCNL: "+V(d.ccnl)+". Orario: "+V(d.orario)+". Distribuzione: "+V(d.distribuzione)+".";}},
          {no:"Art. 3",t:"Retribuzione e prova",b:function(d){return "Retribuzione lorda mensile: "+V(d.retribuzione)+", "+V(d.mensilita)+". Periodo di prova: "+V(d.prova)+".";}},
          {no:"Art. 4",t:"Ferie e riservatezza",b:function(d){return "Ferie: "+V(d.ferie)+". "+V(d.riservatezza)+"";}},
          {no:"Art. 5",t:"Pattuizioni particolari",b:_ekIT}]};
        NCX["vtr_it_mutuo"]={n:"CONTRATTO DI MUTUO",sn:"art. 1813 e ss. c.c.",A:"Mutuante",B:"Mutuatario",cl:[
          {no:"Art. 1",t:"Parti e oggetto",b:function(d){return V(d.mutuante)+" concede in mutuo a "+V(d.mutuatario)+" la somma di "+V(d.importo)+". Erogazione: "+V(d.erogazione)+".";}},
          {no:"Art. 2",t:"Interessi e restituzione",b:function(d){return "Interessi: "+V(d.interessi)+". Restituzione: "+V(d.restituzione)+".";}},
          {no:"Art. 3",t:"Mora",b:function(d){return V(d.mora)+" "+V(d.decadenza)+"";}},
          {no:"Art. 4",t:"Pattuizioni particolari",b:_ekIT}]};
        NCX["vtr_it_prestazione"]={n:"CONTRATTO DI PRESTAZIONE D'OPERA",sn:"art. 2222 e ss. c.c.",A:"Committente",B:"Prestatore",cl:[
          {no:"Art. 1",t:"Parti",b:function(d){return V(d.committente)+" (committente) e "+V(d.prestatore)+" (prestatore). "+V(d.autonomia)+"";}},
          {no:"Art. 2",t:"Prestazione e termine",b:function(d){return "Prestazione: "+V(d.prestazione)+". Termine: "+V(d.termine)+".";}},
          {no:"Art. 3",t:"Compenso e pagamento",b:function(d){return "Compenso: "+V(d.compenso)+". Pagamento: "+V(d.pagamento)+".";}},
          {no:"Art. 4",t:"Proprietà e riservatezza",b:function(d){return V(d.proprieta)+" "+V(d.riserv)+"";}},
          {no:"Art. 5",t:"Pattuizioni particolari",b:_ekIT}]};
        NCX["vtr_it_riservatezza"]={n:"ACCORDO DI RISERVATEZZA",sn:"riservatezza reciproca",A:"Parte 1",B:"Parte 2",cl:[
          {no:"Art. 1",t:"Parti e finalità",b:function(d){return V(d.p1)+" e "+V(d.p2)+" collaborano per: "+V(d.finalita)+".";}},
          {no:"Art. 2",t:"Informazioni riservate",b:function(d){return V(d.info)+" "+V(d.eccezioni)+"";}},
          {no:"Art. 3",t:"Obbligo e durata",b:function(d){return V(d.obbligo)+" "+V(d.durata)+" "+V(d.penale)+"";}},
          {no:"Art. 4",t:"Pattuizioni particolari",b:_ekIT}]};
        NCX["vtr_it_comodato"]={n:"CONTRATTO DI COMODATO",sn:"art. 1803 e ss. c.c.",A:"Comodante",B:"Comodatario",cl:[
          {no:"Art. 1",t:"Parti e bene",b:function(d){return V(d.comodante)+" concede gratuitamente in uso a "+V(d.comodatario)+" il seguente bene: "+V(d.bene)+".";}},
          {no:"Art. 2",t:"Uso, spese e durata",b:function(d){return V(d.uso)+" Spese: "+V(d.spese)+". Durata: "+V(d.durata)+".";}},
          {no:"Art. 3",t:"Restituzione",b:function(d){return V(d.restituzione)+"";}},
          {no:"Art. 4",t:"Pattuizioni particolari",b:_ekIT}]};
        NCX["vtr_gb_sale"]={n:"SALE AGREEMENT",sn:"Sale of Goods Act 1979",A:"Seller",B:"Buyer",cl:[
          {no:"Clause 1",t:"Parties",b:function(d){return "Between "+V(d.seller)+" (seller) and "+V(d.buyer)+" (buyer), it is agreed as follows.";}},
          {no:"Clause 2",t:"Goods",b:function(d){return "The seller sells to the buyer: "+V(d.goods)+". Condition: "+V(d.condition)+".";}},
          {no:"Clause 3",t:"Price and payment",b:function(d){return "Price: "+V(d.price)+". Payment: "+V(d.payment)+".";}},
          {no:"Clause 4",t:"Delivery and title",b:function(d){return "Delivery: "+V(d.delivery)+". "+V(d.risk)+" "+V(d.title)+"";}},
          {no:"Clause 5",t:"Special terms",b:_ekGB}]};
        NCX["vtr_gb_employment"]={n:"EMPLOYMENT CONTRACT",sn:"Employment Rights Act 1996",A:"Employer",B:"Employee",cl:[
          {no:"Clause 1",t:"Parties",b:function(d){return V(d.employer)+" (employer) employs "+V(d.employee)+" (employee) on a permanent basis.";}},
          {no:"Clause 2",t:"Role and hours",b:function(d){return "Role: "+V(d.role)+". Place of work: "+V(d.location)+". Start: "+V(d.start)+". Hours: "+V(d.hours)+". Probation: "+V(d.probation)+".";}},
          {no:"Clause 3",t:"Pay and notice",b:function(d){return "Salary: "+V(d.salary)+", "+V(d.payment)+". Notice: "+V(d.notice)+".";}},
          {no:"Clause 4",t:"Holiday and confidentiality",b:function(d){return "Holiday: "+V(d.holiday)+". "+V(d.confidentiality)+"";}},
          {no:"Clause 5",t:"Special terms",b:_ekGB}]};
        NCX["vtr_gb_loan"]={n:"LOAN AGREEMENT",sn:"loan of money between parties",A:"Lender",B:"Borrower",cl:[
          {no:"Clause 1",t:"Parties and advance",b:function(d){return V(d.lender)+" lends to "+V(d.borrower)+" the sum of "+V(d.amount)+". Advance: "+V(d.advance)+".";}},
          {no:"Clause 2",t:"Interest and repayment",b:function(d){return "Interest: "+V(d.interest)+". Repayment: "+V(d.repayment)+".";}},
          {no:"Clause 3",t:"Default",b:function(d){return V(d.default)+" "+V(d.acceleration)+"";}},
          {no:"Clause 4",t:"Special terms",b:_ekGB}]};
        NCX["vtr_gb_services"]={n:"SERVICES AGREEMENT",sn:"Supply of Goods and Services Act 1982",A:"Client",B:"Provider",cl:[
          {no:"Clause 1",t:"Parties",b:function(d){return V(d.client)+" (client) and "+V(d.provider)+" (provider). "+V(d.status)+"";}},
          {no:"Clause 2",t:"Services and timeline",b:function(d){return "Services: "+V(d.services)+". Timeline: "+V(d.timeline)+".";}},
          {no:"Clause 3",t:"Fee and payment",b:function(d){return "Fee: "+V(d.fee)+". Payment: "+V(d.payment)+".";}},
          {no:"Clause 4",t:"Intellectual property and confidentiality",b:function(d){return V(d.ip)+" "+V(d.confid)+"";}},
          {no:"Clause 5",t:"Special terms",b:_ekGB}]};
        NCX["vtr_gb_nda"]={n:"NON-DISCLOSURE AGREEMENT",sn:"mutual confidentiality",A:"Party 1",B:"Party 2",cl:[
          {no:"Clause 1",t:"Parties and purpose",b:function(d){return V(d.p1)+" and "+V(d.p2)+" collaborate for the purpose of: "+V(d.purpose)+".";}},
          {no:"Clause 2",t:"Confidential information",b:function(d){return V(d.info)+" "+V(d.exceptions)+"";}},
          {no:"Clause 3",t:"Obligation and term",b:function(d){return V(d.obligation)+" "+V(d.term)+" "+V(d.remedy)+"";}},
          {no:"Clause 4",t:"Special terms",b:_ekGB}]};
        NCX["vtr_gb_loan_use"]={n:"LOAN FOR USE AGREEMENT",sn:"gratuitous bailment of a chattel",A:"Lender",B:"Borrower",cl:[
          {no:"Clause 1",t:"Parties and item",b:function(d){return V(d.lender)+" lends to "+V(d.borrower)+" free of charge the following item: "+V(d.item)+".";}},
          {no:"Clause 2",t:"Use, costs and term",b:function(d){return V(d.use)+" Costs: "+V(d.costs)+". Term: "+V(d.term)+".";}},
          {no:"Clause 3",t:"Return",b:function(d){return V(d.return)+"";}},
          {no:"Clause 4",t:"Special terms",b:_ekGB}]};
      }
    }catch(_itgbE){}
    /*CH_ITGB_VERTRAG*/
CHJS;

$anchor = 'var xBak=null,xMode=null;';
$c = substr_count($src,$anchor);
if ($c!==1) exit("HATA: xBak anchor $c kez bulundu (1 bekleniyordu). index DEGISTIRILMEDI.\n");
$new = str_replace($anchor, $inj.$anchor, $src);

$tmp = tempnam(sys_get_temp_dir(),'ig').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-itgb-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_ITGB_VERTRAG')===false || strpos($chk,'vtr_it_vendita')===false || strpos($chk,'vtr_gb_employment')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_ITGB_VERTRAG + IT/GB sozlesmeleri diskte (".strlen($chk)." bayt)\n";
echo "\n✓ IT: +6 (vendita, lavoro, mutuo, prestazione, NDA, comodato); GB: +6 (sale, employment, loan, services, NDA, loan-for-use).\n";
echo "  Her biri form (XSETS) + PDF metni (NCX) ile; ilgili hukukta modulde otomatik listelenir.\n";
