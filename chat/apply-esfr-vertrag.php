<?php
/**
 * ChatHelp — apply-esfr-vertrag (CH_ESFR_VERTRAG) — ES (Código Civil / ET) ve
 *  FR (Code civil / Code du travail) sozlesme katalogunu genislet.
 *  Su an ES: divorcio+alquiler (2), FR: divorce+bail (2). Bu script her birine
 *  +6 sozlesme ekler (form=XSETS + PDF metni=NCX). Enjeksiyon: CH_VST_X
 *  callback'indeki `var xBak=null,xMode=null;` anchor'i ONUNE — orada XSETS.ES,
 *  XSETS.FR, NCX, ucStep, ekES, ekFR, V hepsi scope'ta. Tek str_replace,
 *  count-guard. Gomulu JS node --check ile dogrulandi.
 * KULLANIM: pull2.php?key=...&files=apply-esfr-vertrag.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-esfr-vertrag BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_ESFR_VERTRAG')!==false) exit("Zaten ekli (CH_ESFR_VERTRAG).\n");
if (strpos($src,'CH_VST_X')===false) exit("HATA: CH_VST_X yok — once apply-vst-x deploy edilmeli.\n");

$inj = <<<'CHJS'
/* ══ CH_ESFR_VERTRAG — ES/FR sozlesme katalogu genislemesi ══ */
    try{
      /* ── ES formlari (XSETS.ES'e ekle) ── */
      var _esAdd={
        "vtr_es_compraventa":{ic:"🛒",n:"Contrato de compraventa",sn:"Compraventa de bien mueble — art. 1445 y ss. del Código Civil",price:"3,99 €",pgs:4,steps:[
          {t:"Partes",f:[["vendedor","Vendedor (nombre, DNI/NIE, domicilio)","","ta"],["comprador","Comprador (nombre, DNI/NIE, domicilio)","","ta"]]},
          {t:"Objeto y precio",f:[["objeto","Objeto (descripción; si vehículo: marca, modelo, matrícula, bastidor, km)","","ta"],["estado","Estado / vicios conocidos","El vendedor manifiesta no conocer vicios ocultos."],["precio","Precio (€)"],["pago","Forma de pago","al contado, en el momento de la entrega"],["garantia","Garantía","venta entre particulares sin garantía, salvo dolo (art. 1484 CC)"]]},
          {t:"Entrega y firma",f:[["entrega","Entrega (lugar y fecha)"],["riesgo","Transmisión del riesgo","La propiedad y el riesgo pasan al comprador con la entrega (art. 1462 CC)."],["ort","Lugar"],["datum","Fecha"]]},
          ucStep("Estipulaciones propias (opcional)","Cláusula — título","Cláusula — texto")]},
        "vtr_es_trabajo":{ic:"💼",n:"Contrato de trabajo indefinido",sn:"Estatuto de los Trabajadores (RDL 2/2015)",price:"4,99 €",pgs:5,steps:[
          {t:"Partes",f:[["empresa","Empresa (razón social, CIF, domicilio)","","ta"],["trabajador","Trabajador (nombre, DNI/NIE)","","ta"]]},
          {t:"Puesto y retribución",f:[["puesto","Puesto de trabajo / grupo profesional"],["centro","Centro de trabajo"],["inicio","Fecha de inicio"],["convenio","Convenio colectivo aplicable"],["salario","Salario bruto anual (€)"],["pagas","Pagas","distribuido en 12 o 14 pagas según convenio"],["prueba","Periodo de prueba","según art. 14 ET y convenio"]]},
          {t:"Jornada y vacaciones",f:[["jornada","Jornada","40 horas semanales de promedio en cómputo anual"],["horario","Horario / distribución","según necesidades del servicio"],["vacaciones","Vacaciones","30 días naturales al año (art. 38 ET)"],["confidencialidad","Confidencialidad","El trabajador guardará secreto sobre la información de la empresa durante y después de la relación laboral.","ta"]]},
          {t:"Firma",f:[["ort","Lugar"],["datum","Fecha"]]},
          ucStep("Estipulaciones propias (opcional)","Cláusula — título","Cláusula — texto")]},
        "vtr_es_prestamo":{ic:"💶",n:"Contrato de préstamo (mutuo)",sn:"Préstamo de dinero — art. 1740 y ss. del Código Civil",price:"3,99 €",pgs:4,steps:[
          {t:"Partes",f:[["prestamista","Prestamista (nombre, DNI, domicilio)","","ta"],["prestatario","Prestatario (nombre, DNI, domicilio)","","ta"]]},
          {t:"Préstamo e intereses",f:[["importe","Importe del préstamo (€, en cifra y letra)","","ta"],["entrega","Entrega","mediante transferencia en la fecha de firma"],["interes","Interés","sin interés / anual ____ %"],["devolucion","Devolución (plazo o cuotas)","","ta"]]},
          {t:"Mora y firma",f:[["mora","Mora","En caso de impago se devengará el interés legal del dinero."],["vencimiento","Vencimiento anticipado","El impago de dos cuotas faculta al prestamista a exigir la totalidad pendiente."],["ort","Lugar"],["datum","Fecha"]]},
          ucStep("Estipulaciones propias (opcional)","Cláusula — título","Cláusula — texto")]},
        "vtr_es_servicios":{ic:"🔧",n:"Contrato de prestación de servicios",sn:"Arrendamiento de servicios — art. 1544 del Código Civil",price:"4,99 €",pgs:4,steps:[
          {t:"Partes",f:[["cliente","Cliente (nombre/razón social, NIF)","","ta"],["prestador","Prestador del servicio (nombre, NIF)","","ta"]]},
          {t:"Servicio y precio",f:[["servicio","Descripción del servicio / alcance","","ta"],["plazo","Plazo de ejecución"],["precio","Precio (€) + IVA","más IVA cuando proceda"],["pago","Forma de pago","50 % a la firma, 50 % a la entrega"],["independencia","Naturaleza","Relación mercantil; el prestador actúa con plena independencia, sin vínculo laboral."]]},
          {t:"Responsabilidad y firma",f:[["propiedad","Propiedad del resultado","Los entregables corresponden al cliente una vez satisfecho el precio."],["confid","Confidencialidad","Ambas partes mantendrán la confidencialidad de la información intercambiada."],["ort","Lugar"],["datum","Fecha"]]},
          ucStep("Estipulaciones propias (opcional)","Cláusula — título","Cláusula — texto")]},
        "vtr_es_confidencialidad":{ic:"🔒",n:"Acuerdo de confidencialidad (NDA)",sn:"Confidencialidad recíproca",price:"4,99 €",pgs:4,steps:[
          {t:"Partes",f:[["p1","Parte 1 (nombre/razón social, NIF)","","ta"],["p2","Parte 2 (nombre/razón social, NIF)","","ta"]]},
          {t:"Objeto",f:[["finalidad","Finalidad de la colaboración","","ta"],["info","Información confidencial","Toda información técnica, comercial y personal intercambiada, oral, escrita o electrónica, tiene carácter confidencial."],["excepciones","Excepciones","Queda excluida la información de dominio público o lícitamente obtenida por otras vías."]]},
          {t:"Obligación y duración",f:[["obligacion","Obligación","Las partes usarán la información solo para la finalidad y no la revelarán a terceros."],["duracion","Duración","La confidencialidad subsiste ____ años tras el fin de la colaboración."],["penalizacion","Penalización (opcional)","El incumplimiento facultará a reclamar los daños y perjuicios causados."],["ort","Lugar"],["datum","Fecha"]]},
          ucStep("Estipulaciones propias (opcional)","Cláusula — título","Cláusula — texto")]},
        "vtr_es_comodato":{ic:"🔑",n:"Contrato de comodato (préstamo de uso)",sn:"Cesión gratuita de uso — art. 1740 y 1741 del Código Civil",price:"3,99 €",pgs:3,steps:[
          {t:"Partes y objeto",f:[["comodante","Comodante (nombre, domicilio)","","ta"],["comodatario","Comodatario (nombre, domicilio)","","ta"],["cosa","Cosa prestada (descripción, estado)","","ta"]]},
          {t:"Uso y devolución",f:[["uso","Uso","El comodatario usará la cosa solo conforme a su destino y con diligencia."],["plazo","Plazo / fecha de devolución"],["gastos","Gastos","Los gastos ordinarios de conservación corren a cargo del comodatario."],["devolucion","Devolución","La cosa se devolverá en el estado inicial, salvo el desgaste por el uso normal."],["ort","Lugar"],["datum","Fecha"]]},
          ucStep("Estipulaciones propias (opcional)","Cláusula — título","Cláusula — texto")]}
      };
      /* ── FR formlari (XSETS.FR'e ekle) ── */
      var _frAdd={
        "vtr_fr_vente":{ic:"🛒",n:"Contrat de vente (entre particuliers)",sn:"Vente d'un bien meuble — art. 1582 et s. du Code civil",price:"3,99 €",pgs:4,steps:[
          {t:"Parties",f:[["vendeur","Vendeur (nom, adresse)","","ta"],["acheteur","Acheteur (nom, adresse)","","ta"]]},
          {t:"Objet et prix",f:[["objet","Objet (description; si véhicule: marque, type, immatriculation, n° de série, km)","","ta"],["etat","État / vices connus","Le vendeur déclare ne pas avoir connaissance de vices cachés."],["prix","Prix (€)"],["paiement","Paiement","comptant, à la remise du bien"],["garantie","Garantie","vente entre particuliers sans garantie, sauf dol (art. 1641 C.civ.)"]]},
          {t:"Remise et signature",f:[["remise","Remise (lieu et date)"],["transfert","Transfert des risques","La propriété et les risques passent à l'acheteur à la remise (art. 1583 C.civ.)."],["ort","Fait à"],["datum","Le"]]},
          ucStep("Stipulations propres (optionnel)","Clause — titre","Clause — texte")]},
        "vtr_fr_travail":{ic:"💼",n:"Contrat de travail à durée indéterminée (CDI)",sn:"Code du travail",price:"4,99 €",pgs:5,steps:[
          {t:"Parties",f:[["employeur","Employeur (raison sociale, SIRET, adresse)","","ta"],["salarie","Salarié (nom, adresse)","","ta"]]},
          {t:"Poste et rémunération",f:[["poste","Poste / qualification"],["lieu","Lieu de travail"],["debut","Date d'embauche"],["convention","Convention collective applicable"],["salaire","Rémunération brute mensuelle (€)"],["paiement","Versement","mensuel, à terme échu"],["essai","Période d'essai","selon la convention collective (art. L1221-19 s.)"]]},
          {t:"Durée du travail et congés",f:[["duree","Durée du travail","35 heures hebdomadaires"],["horaires","Horaires / répartition","selon l'organisation du service"],["conges","Congés payés","2,5 jours ouvrables par mois travaillé (art. L3141-3)"],["confidentialite","Confidentialité","Le salarié respecte le secret des affaires pendant et après le contrat.","ta"]]},
          {t:"Signature",f:[["ort","Fait à"],["datum","Le"]]},
          ucStep("Stipulations propres (optionnel)","Clause — titre","Clause — texte")]},
        "vtr_fr_pret":{ic:"💶",n:"Contrat de prêt d'argent",sn:"Prêt de consommation — art. 1892 et s. du Code civil",price:"3,99 €",pgs:4,steps:[
          {t:"Parties",f:[["preteur","Prêteur (nom, adresse)","","ta"],["emprunteur","Emprunteur (nom, adresse)","","ta"]]},
          {t:"Prêt et intérêts",f:[["montant","Montant du prêt (€, en chiffres et en lettres)","","ta"],["versement","Versement","par virement à la date de signature"],["interet","Intérêts","sans intérêt / annuel ____ %"],["remboursement","Remboursement (échéance ou mensualités)","","ta"]]},
          {t:"Retard et signature",f:[["retard","Retard","En cas de retard, l'intérêt légal est dû de plein droit."],["decheance","Déchéance du terme","Le défaut de paiement de deux échéances rend exigible la totalité restante."],["ort","Fait à"],["datum","Le"]]},
          ucStep("Stipulations propres (optionnel)","Clause — titre","Clause — texte")]},
        "vtr_fr_prestation":{ic:"🔧",n:"Contrat de prestation de services",sn:"Louage d'ouvrage — art. 1710 du Code civil",price:"4,99 €",pgs:4,steps:[
          {t:"Parties",f:[["client","Client (nom/raison sociale, SIRET)","","ta"],["prestataire","Prestataire (nom, SIRET)","","ta"]]},
          {t:"Prestation et prix",f:[["prestation","Description de la prestation / périmètre","","ta"],["delai","Délai d'exécution"],["prix","Prix (€) HT","TVA en sus le cas échéant"],["paiement","Modalités de paiement","30 % à la commande, 70 % à la livraison"],["independance","Nature","Relation commerciale; le prestataire agit en toute indépendance, hors lien de subordination."]]},
          {t:"Responsabilité et signature",f:[["propriete","Propriété des livrables","Les livrables reviennent au client après paiement intégral."],["confid","Confidentialité","Chaque partie préserve la confidentialité des informations échangées."],["ort","Fait à"],["datum","Le"]]},
          ucStep("Stipulations propres (optionnel)","Clause — titre","Clause — texte")]},
        "vtr_fr_confidentialite":{ic:"🔒",n:"Accord de confidentialité (NDA)",sn:"Confidentialité réciproque",price:"4,99 €",pgs:4,steps:[
          {t:"Parties",f:[["p1","Partie 1 (nom/raison sociale)","","ta"],["p2","Partie 2 (nom/raison sociale)","","ta"]]},
          {t:"Objet",f:[["finalite","Finalité de la collaboration","","ta"],["info","Informations confidentielles","Toute information technique, commerciale et personnelle échangée, orale, écrite ou électronique, est confidentielle."],["exceptions","Exceptions","Sont exclues les informations publiques ou licitement obtenues par ailleurs."]]},
          {t:"Obligation et durée",f:[["obligation","Obligation","Les parties n'utilisent les informations que pour la finalité et ne les divulguent pas à des tiers."],["duree","Durée","La confidentialité subsiste ____ ans après la fin de la collaboration."],["penalite","Clause pénale (optionnel)","Toute violation ouvre droit à réparation du préjudice subi."],["ort","Fait à"],["datum","Le"]]},
          ucStep("Stipulations propres (optionnel)","Clause — titre","Clause — texte")]},
        "vtr_fr_pret_usage":{ic:"🔑",n:"Prêt à usage (commodat)",sn:"Mise à disposition gratuite — art. 1875 et s. du Code civil",price:"3,99 €",pgs:3,steps:[
          {t:"Parties et objet",f:[["preteur","Prêteur (nom, adresse)","","ta"],["emprunteur","Emprunteur (nom, adresse)","","ta"],["chose","Chose prêtée (description, état)","","ta"]]},
          {t:"Usage et restitution",f:[["usage","Usage","L'emprunteur use de la chose conformément à sa destination et avec soin (art. 1880 C.civ.)."],["duree","Durée / date de restitution"],["frais","Frais","Les frais ordinaires d'entretien incombent à l'emprunteur."],["restitution","Restitution","La chose est restituée en l'état, hors usure normale."],["ort","Fait à"],["datum","Le"]]},
          ucStep("Stipulations propres (optionnel)","Clause — titre","Clause — texte")]}
      };
      if(typeof XSETS!=="undefined"&&XSETS){
        XSETS.ES=XSETS.ES||{}; for(var _ke in _esAdd){ XSETS.ES[_ke]=_esAdd[_ke]; }
        XSETS.FR=XSETS.FR||{}; for(var _kf in _frAdd){ XSETS.FR[_kf]=_frAdd[_kf]; }
      }
      /* ── PDF metinleri (NCX) ── */
      if(typeof NCX!=="undefined"&&NCX){
        var _ekES=(typeof ekES==="function")?ekES:function(d){return ekX(d,"No existen otras estipulaciones particulares.");};
        var _ekFR=(typeof ekFR==="function")?ekFR:function(d){return ekX(d,"Aucune autre stipulation particulière.");};
        NCX["vtr_es_compraventa"]={n:"CONTRATO DE COMPRAVENTA",sn:"art. 1445 y ss. del Código Civil",A:"Vendedor",B:"Comprador",cl:[
          {no:"Cláusula 1",t:"Partes",b:function(d){return "De una parte, "+V(d.vendedor)+" (vendedor); de otra, "+V(d.comprador)+" (comprador). Ambas partes se reconocen capacidad para contratar.";}},
          {no:"Cláusula 2",t:"Objeto",b:function(d){return "El vendedor vende al comprador: "+V(d.objeto)+". Estado: "+V(d.estado)+".";}},
          {no:"Cláusula 3",t:"Precio y pago",b:function(d){return "Precio: "+V(d.precio)+". Pago: "+V(d.pago)+".";}},
          {no:"Cláusula 4",t:"Entrega y garantía",b:function(d){return "Entrega: "+V(d.entrega)+". "+V(d.riesgo)+" Garantía: "+V(d.garantia)+".";}},
          {no:"Cláusula 5",t:"Estipulaciones particulares",b:_ekES}]};
        NCX["vtr_es_trabajo"]={n:"CONTRATO DE TRABAJO INDEFINIDO",sn:"Estatuto de los Trabajadores (RDL 2/2015)",A:"Empresa",B:"Trabajador",cl:[
          {no:"Cláusula 1",t:"Partes",b:function(d){return V(d.empresa)+" (empresa) y "+V(d.trabajador)+" (trabajador) suscriben el presente contrato de trabajo por tiempo indefinido.";}},
          {no:"Cláusula 2",t:"Puesto y jornada",b:function(d){return "Puesto: "+V(d.puesto)+". Centro: "+V(d.centro)+". Inicio: "+V(d.inicio)+". Convenio: "+V(d.convenio)+". Jornada: "+V(d.jornada)+". Horario: "+V(d.horario)+".";}},
          {no:"Cláusula 3",t:"Retribución y periodo de prueba",b:function(d){return "Salario bruto anual: "+V(d.salario)+", "+V(d.pagas)+". Periodo de prueba: "+V(d.prueba)+".";}},
          {no:"Cláusula 4",t:"Vacaciones y confidencialidad",b:function(d){return "Vacaciones: "+V(d.vacaciones)+". "+V(d.confidencialidad)+"";}},
          {no:"Cláusula 5",t:"Estipulaciones particulares",b:_ekES}]};
        NCX["vtr_es_prestamo"]={n:"CONTRATO DE PRÉSTAMO",sn:"art. 1740 y ss. del Código Civil",A:"Prestamista",B:"Prestatario",cl:[
          {no:"Cláusula 1",t:"Partes y objeto",b:function(d){return V(d.prestamista)+" entrega en préstamo a "+V(d.prestatario)+" la suma de "+V(d.importe)+". Entrega: "+V(d.entrega)+".";}},
          {no:"Cláusula 2",t:"Intereses y devolución",b:function(d){return "Interés: "+V(d.interes)+". Devolución: "+V(d.devolucion)+".";}},
          {no:"Cláusula 3",t:"Mora",b:function(d){return V(d.mora)+" "+V(d.vencimiento)+"";}},
          {no:"Cláusula 4",t:"Estipulaciones particulares",b:_ekES}]};
        NCX["vtr_es_servicios"]={n:"CONTRATO DE PRESTACIÓN DE SERVICIOS",sn:"art. 1544 del Código Civil",A:"Cliente",B:"Prestador",cl:[
          {no:"Cláusula 1",t:"Partes",b:function(d){return V(d.cliente)+" (cliente) y "+V(d.prestador)+" (prestador). "+V(d.independencia)+"";}},
          {no:"Cláusula 2",t:"Objeto y plazo",b:function(d){return "Servicio: "+V(d.servicio)+". Plazo: "+V(d.plazo)+".";}},
          {no:"Cláusula 3",t:"Precio y pago",b:function(d){return "Precio: "+V(d.precio)+". Pago: "+V(d.pago)+".";}},
          {no:"Cláusula 4",t:"Propiedad y confidencialidad",b:function(d){return V(d.propiedad)+" "+V(d.confid)+"";}},
          {no:"Cláusula 5",t:"Estipulaciones particulares",b:_ekES}]};
        NCX["vtr_es_confidencialidad"]={n:"ACUERDO DE CONFIDENCIALIDAD",sn:"confidencialidad recíproca",A:"Parte 1",B:"Parte 2",cl:[
          {no:"Cláusula 1",t:"Partes y finalidad",b:function(d){return V(d.p1)+" y "+V(d.p2)+" colaboran con la finalidad de: "+V(d.finalidad)+".";}},
          {no:"Cláusula 2",t:"Información confidencial",b:function(d){return V(d.info)+" "+V(d.excepciones)+"";}},
          {no:"Cláusula 3",t:"Obligación y duración",b:function(d){return V(d.obligacion)+" "+V(d.duracion)+" "+V(d.penalizacion)+"";}},
          {no:"Cláusula 4",t:"Estipulaciones particulares",b:_ekES}]};
        NCX["vtr_es_comodato"]={n:"CONTRATO DE COMODATO",sn:"art. 1740 y 1741 del Código Civil",A:"Comodante",B:"Comodatario",cl:[
          {no:"Cláusula 1",t:"Partes y cosa",b:function(d){return V(d.comodante)+" cede gratuitamente a "+V(d.comodatario)+" el uso de: "+V(d.cosa)+".";}},
          {no:"Cláusula 2",t:"Uso, gastos y plazo",b:function(d){return V(d.uso)+" Gastos: "+V(d.gastos)+". Plazo: "+V(d.plazo)+".";}},
          {no:"Cláusula 3",t:"Devolución",b:function(d){return V(d.devolucion)+"";}},
          {no:"Cláusula 4",t:"Estipulaciones particulares",b:_ekES}]};
        NCX["vtr_fr_vente"]={n:"CONTRAT DE VENTE",sn:"art. 1582 et s. du Code civil",A:"Vendeur",B:"Acheteur",cl:[
          {no:"Article 1",t:"Parties",b:function(d){return "Entre "+V(d.vendeur)+" (vendeur) et "+V(d.acheteur)+" (acheteur), il est convenu ce qui suit.";}},
          {no:"Article 2",t:"Objet",b:function(d){return "Le vendeur vend à l'acheteur: "+V(d.objet)+". État: "+V(d.etat)+".";}},
          {no:"Article 3",t:"Prix et paiement",b:function(d){return "Prix: "+V(d.prix)+". Paiement: "+V(d.paiement)+".";}},
          {no:"Article 4",t:"Remise et garantie",b:function(d){return "Remise: "+V(d.remise)+". "+V(d.transfert)+" Garantie: "+V(d.garantie)+".";}},
          {no:"Article 5",t:"Stipulations particulières",b:_ekFR}]};
        NCX["vtr_fr_travail"]={n:"CONTRAT DE TRAVAIL À DURÉE INDÉTERMINÉE",sn:"Code du travail",A:"Employeur",B:"Salarié",cl:[
          {no:"Article 1",t:"Parties",b:function(d){return V(d.employeur)+" (employeur) engage "+V(d.salarie)+" (salarié) en contrat à durée indéterminée.";}},
          {no:"Article 2",t:"Poste et durée du travail",b:function(d){return "Poste: "+V(d.poste)+". Lieu: "+V(d.lieu)+". Embauche: "+V(d.debut)+". Convention: "+V(d.convention)+". Durée: "+V(d.duree)+". Horaires: "+V(d.horaires)+".";}},
          {no:"Article 3",t:"Rémunération et essai",b:function(d){return "Rémunération: "+V(d.salaire)+", "+V(d.paiement)+". Période d'essai: "+V(d.essai)+".";}},
          {no:"Article 4",t:"Congés et confidentialité",b:function(d){return "Congés payés: "+V(d.conges)+". "+V(d.confidentialite)+"";}},
          {no:"Article 5",t:"Stipulations particulières",b:_ekFR}]};
        NCX["vtr_fr_pret"]={n:"CONTRAT DE PRÊT D'ARGENT",sn:"art. 1892 et s. du Code civil",A:"Prêteur",B:"Emprunteur",cl:[
          {no:"Article 1",t:"Parties et objet",b:function(d){return V(d.preteur)+" prête à "+V(d.emprunteur)+" la somme de "+V(d.montant)+". Versement: "+V(d.versement)+".";}},
          {no:"Article 2",t:"Intérêts et remboursement",b:function(d){return "Intérêts: "+V(d.interet)+". Remboursement: "+V(d.remboursement)+".";}},
          {no:"Article 3",t:"Retard",b:function(d){return V(d.retard)+" "+V(d.decheance)+"";}},
          {no:"Article 4",t:"Stipulations particulières",b:_ekFR}]};
        NCX["vtr_fr_prestation"]={n:"CONTRAT DE PRESTATION DE SERVICES",sn:"art. 1710 du Code civil",A:"Client",B:"Prestataire",cl:[
          {no:"Article 1",t:"Parties",b:function(d){return V(d.client)+" (client) et "+V(d.prestataire)+" (prestataire). "+V(d.independance)+"";}},
          {no:"Article 2",t:"Objet et délai",b:function(d){return "Prestation: "+V(d.prestation)+". Délai: "+V(d.delai)+".";}},
          {no:"Article 3",t:"Prix et paiement",b:function(d){return "Prix: "+V(d.prix)+". Paiement: "+V(d.paiement)+".";}},
          {no:"Article 4",t:"Propriété et confidentialité",b:function(d){return V(d.propriete)+" "+V(d.confid)+"";}},
          {no:"Article 5",t:"Stipulations particulières",b:_ekFR}]};
        NCX["vtr_fr_confidentialite"]={n:"ACCORD DE CONFIDENTIALITÉ",sn:"confidentialité réciproque",A:"Partie 1",B:"Partie 2",cl:[
          {no:"Article 1",t:"Parties et finalité",b:function(d){return V(d.p1)+" et "+V(d.p2)+" collaborent aux fins de: "+V(d.finalite)+".";}},
          {no:"Article 2",t:"Informations confidentielles",b:function(d){return V(d.info)+" "+V(d.exceptions)+"";}},
          {no:"Article 3",t:"Obligation et durée",b:function(d){return V(d.obligation)+" "+V(d.duree)+" "+V(d.penalite)+"";}},
          {no:"Article 4",t:"Stipulations particulières",b:_ekFR}]};
        NCX["vtr_fr_pret_usage"]={n:"PRÊT À USAGE (COMMODAT)",sn:"art. 1875 et s. du Code civil",A:"Prêteur",B:"Emprunteur",cl:[
          {no:"Article 1",t:"Parties et chose",b:function(d){return V(d.preteur)+" met gratuitement à disposition de "+V(d.emprunteur)+" la chose suivante: "+V(d.chose)+".";}},
          {no:"Article 2",t:"Usage, frais et durée",b:function(d){return V(d.usage)+" Frais: "+V(d.frais)+". Durée: "+V(d.duree)+".";}},
          {no:"Article 3",t:"Restitution",b:function(d){return V(d.restitution)+"";}},
          {no:"Article 4",t:"Stipulations particulières",b:_ekFR}]};
      }
    }catch(_esfrE){}
    /*CH_ESFR_VERTRAG*/
CHJS;

$anchor = 'var xBak=null,xMode=null;';
$c = substr_count($src,$anchor);
if ($c!==1) exit("HATA: xBak anchor $c kez bulundu (1 bekleniyordu). index DEGISTIRILMEDI.\n");
$new = str_replace($anchor, $inj.$anchor, $src);

$tmp = tempnam(sys_get_temp_dir(),'ef').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-esfr-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_ESFR_VERTRAG')===false || strpos($chk,'vtr_es_compraventa')===false || strpos($chk,'vtr_fr_travail')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_ESFR_VERTRAG + ES/FR sozlesmeleri diskte (".strlen($chk)." bayt)\n";
echo "\n✓ ES: +6 (compraventa, trabajo, préstamo, servicios, NDA, comodato); FR: +6 (vente, travail, prêt, prestation, NDA, prêt à usage).\n";
echo "  Her biri form (XSETS) + PDF metni (NCX) ile; ilgili hukukta modulde otomatik listelenir.\n";
