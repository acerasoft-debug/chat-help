<?php
/**
 * ChatHelp — İSPANYA MODÜLÜ (~41 belge, 6 kategori, İspanyolca) — CH_TR_MODULE ile aynı kanıtlanmış desen
 * -------------------------------------------------------------------------------------------------------
 *  • Kategoriler country:'ES' etiketli -> genelleştirilmiş ülke filtresi (CH_TR_MODULE) otomatik çalışır
 *  • Welcome grid kartları data-cccountry='ES' -> kart görünürlüğü otomatik
 *  • Dil: setCountry('ES') zaten UL='es' yapıyor (CC_DATA.ES.l) — ek dil kodu gerekmez
 *  • Belge dili/hukuku: api.php'de lawSystems['ES'] + doc-lang ES->es (apply-lawsys-all.php ile)
 *
 * KULLANIM: pull-updates.php?files=apply-espana.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-espana BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,"CH_ES_MODULE")!==false) exit("Zaten ekli (CH_ES_MODULE).\n");
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-es-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_ES_MODULE — İspanya hukuku modülü (deferred; DOCS/CATS hazır olunca yüklenir) */
try{(function(){
  function Pq(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var XC={
    es_bajas:{n:"Bajas y Cancelaciones",ic:"📄"},
    es_vivienda:{n:"Vivienda y Alquiler",ic:"🏠"},
    es_trabajo:{n:"Trabajo y Empleo",ic:"💼"},
    es_consumo:{n:"Consumo y Reclamaciones",ic:"🛒"},
    es_contratos:{n:"Contratos",ic:"📝"},
    es_admin:{n:"Administración Pública",ic:"🏛️"}
  };
  var X_ORDER=["es_bajas","es_vivienda","es_trabajo","es_consumo","es_contratos","es_admin"];
  var CCODE="ES", DOCWORD="documentos";

  var D={
    /* Bajas y Cancelaciones */
    es_baja_suministro:{ic:"⚡",n:"Baja de suministro (luz/gas)",cat:"es_bajas",tier:"einfach",q:[Pq("compania","Compañía: nombre y dirección",true),Pq("num_contrato","Nº de contrato / CUPS",true),Pq("direccion","Dirección del suministro",true),Pq("fecha","Fecha de baja deseada",true),Pq("datos","Sus nombre, apellidos y DNI/NIE",true)]},
    es_baja_telefonia:{ic:"📱",n:"Baja de telefonía / internet",cat:"es_bajas",tier:"einfach",q:[Pq("operador","Operador: nombre",true),Pq("num_cliente","Nº de cliente / línea",true),Pq("permanencia","¿Tiene compromiso de permanencia?",false,["No","Sí","No lo sé"]),Pq("fecha","Fecha de baja deseada",false),Pq("datos","Sus nombre, apellidos y DNI/NIE",true)]},
    es_baja_gimnasio:{ic:"🏋️",n:"Baja de gimnasio / club",cat:"es_bajas",tier:"einfach",q:[Pq("centro","Centro: nombre y dirección",true),Pq("num_socio","Nº de socio",false),Pq("fecha","Fecha de baja",true),Pq("motivo","Motivo (opcional)",false),Pq("datos","Sus nombre y apellidos",true)]},
    es_baja_seguro:{ic:"🛡️",n:"Cancelación de póliza de seguro",cat:"es_bajas",tier:"einfach",q:[Pq("aseguradora","Aseguradora: nombre y dirección",true),Pq("num_poliza","Nº de póliza",true),Pq("tipo","Tipo de seguro",true,["Auto","Hogar","Salud","Vida","Otro"]),Pq("vencimiento","Fecha de vencimiento (preaviso 1 mes)",true),Pq("datos","Sus nombre, apellidos y DNI/NIE",true)]},
    es_cierre_cuenta:{ic:"🏦",n:"Cierre de cuenta bancaria",cat:"es_bajas",tier:"einfach",q:[Pq("banco","Banco: nombre y oficina",true),Pq("iban","IBAN de la cuenta",true),Pq("traspaso","IBAN para traspasar el saldo (opcional)",false),Pq("datos","Sus nombre, apellidos y DNI/NIE",true)]},
    es_baja_suscripcion:{ic:"📺",n:"Baja de suscripción digital",cat:"es_bajas",tier:"einfach",q:[Pq("plataforma","Plataforma (Netflix, Spotify…)",true),Pq("cuenta","Correo de la cuenta",true),Pq("fecha","Fecha de baja (opcional)",false),Pq("datos","Sus nombre y apellidos",true)]},

    /* Vivienda y Alquiler */
    es_preaviso_alquiler:{ic:"🏠",n:"Preaviso de fin de alquiler (inquilino)",cat:"es_vivienda",tier:"einfach",q:[Pq("arrendador","Arrendador/agencia: nombre y dirección",true),Pq("direccion","Dirección de la vivienda",true),Pq("fecha_salida","Fecha de salida (preaviso 30 días, LAU)",true),Pq("datos","Sus nombre, apellidos y DNI/NIE",true)]},
    es_fianza:{ic:"💶",n:"Reclamación de devolución de fianza",cat:"es_vivienda",tier:"standard",q:[Pq("arrendador","Arrendador: nombre y dirección",true),Pq("direccion","Dirección de la vivienda",true),Pq("fecha_entrega","Fecha de entrega de llaves",true),Pq("importe","Importe de la fianza (€)",true),Pq("iban","IBAN para la devolución",false),Pq("datos","Sus nombre y apellidos",true)]},
    es_contrato_alquiler:{ic:"📄",n:"Contrato de arrendamiento de vivienda",cat:"es_vivienda",tier:"komplex",q:[Pq("rol","Usted es",true,["Arrendador","Inquilino"]),Pq("otra_parte","Otra parte: nombre y DNI/NIE",true),Pq("direccion","Dirección de la vivienda",true),Pq("superficie","Superficie (m²) y habitaciones",false),Pq("inicio","Fecha de inicio",true),Pq("renta","Renta mensual (€)",true),Pq("fianza","Fianza (€, mínimo 1 mes)",false),Pq("gastos","Gastos incluidos (comunidad, IBI…)",false)]},
    es_desperfectos:{ic:"🔧",n:"Comunicación de desperfectos al arrendador",cat:"es_vivienda",tier:"standard",q:[Pq("arrendador","Arrendador: nombre y dirección",true),Pq("direccion","Dirección de la vivienda",true),Pq("problema","Descripción del desperfecto/avería",true),Pq("urgencia","¿Es urgente?",false,["Sí","No"]),Pq("datos","Sus nombre y apellidos",true)]},
    es_subida_renta:{ic:"📈",n:"Oposición a subida de renta",cat:"es_vivienda",tier:"standard",q:[Pq("arrendador","Arrendador: nombre y dirección",true),Pq("direccion","Dirección de la vivienda",true),Pq("renta_actual","Renta actual (€)",true),Pq("renta_nueva","Renta exigida (€)",true),Pq("motivos","Motivos de su oposición",true)]},
    es_ruidos:{ic:"🔊",n:"Queja por ruidos (comunidad/vecino)",cat:"es_vivienda",tier:"standard",q:[Pq("destinatario","Destinatario (vecino/administrador)",true),Pq("problema","Descripción de las molestias",true),Pq("horarios","Frecuencia y horarios",false),Pq("peticion","Su petición",true),Pq("datos","Sus nombre y piso",true)]},
    es_comunidad:{ic:"🏢",n:"Escrito a la comunidad de propietarios",cat:"es_vivienda",tier:"standard",q:[Pq("comunidad","Comunidad/administrador: nombre",true),Pq("asunto","Asunto (derrama, obras, cuentas…)",true),Pq("exposicion","Exposición de los hechos",true),Pq("peticion","Su petición",true),Pq("datos","Sus nombre y piso/portal",true)]},

    /* Trabajo y Empleo */
    es_dimision:{ic:"👋",n:"Carta de dimisión (baja voluntaria)",cat:"es_trabajo",tier:"einfach",q:[Pq("empresa","Empresa: nombre y dirección",true),Pq("puesto","Su puesto",true),Pq("ultimo_dia","Último día de trabajo (preaviso 15 días)",true),Pq("datos","Sus nombre, apellidos y DNI/NIE",true)]},
    es_finiquito:{ic:"🧾",n:"Reclamación de finiquito / salarios",cat:"es_trabajo",tier:"standard",q:[Pq("empresa","Empresa: nombre y dirección",true),Pq("puesto","Su puesto y fechas del contrato",true),Pq("conceptos","Conceptos reclamados (salario, vacaciones, finiquito)",true),Pq("importe","Importe estimado (€)",false),Pq("datos","Sus nombre y apellidos",true)]},
    es_excedencia:{ic:"🌴",n:"Solicitud de excedencia",cat:"es_trabajo",tier:"standard",q:[Pq("empresa","Empresa/RRHH",true),Pq("puesto","Su puesto y antigüedad",true),Pq("tipo","Tipo de excedencia",true,["Voluntaria","Cuidado de hijos","Cuidado de familiares"]),Pq("periodo","Periodo solicitado",true)]},
    es_horas_extra:{ic:"⏰",n:"Reclamación de horas extraordinarias",cat:"es_trabajo",tier:"standard",q:[Pq("empresa","Empresa: nombre y dirección",true),Pq("puesto","Su puesto",true),Pq("periodo","Periodo reclamado",true),Pq("horas","Horas estimadas",true),Pq("datos","Sus nombre y apellidos",true)]},
    es_acoso:{ic:"🚨",n:"Denuncia de acoso laboral",cat:"es_trabajo",tier:"komplex",q:[Pq("destinatario","Destinatario (empresa/RRHH/Inspección)",true),Pq("hechos","Hechos (fechas, personas, descripción)",true),Pq("testigos","Testigos (opcional)",false),Pq("peticion","Su petición",true),Pq("datos","Sus nombre y puesto",true)]},
    es_teletrabajo:{ic:"🏡",n:"Solicitud de teletrabajo",cat:"es_trabajo",tier:"einfach",q:[Pq("empresa","Empresa/responsable",true),Pq("puesto","Su puesto",true),Pq("regimen","Régimen deseado (días/semana)",true),Pq("motivo","Motivo (opcional)",false)]},
    es_vacaciones:{ic:"🏖️",n:"Solicitud de vacaciones",cat:"es_trabajo",tier:"einfach",q:[Pq("empresa","Empresa/responsable",true),Pq("desde","Desde (fecha)",true),Pq("hasta","Hasta (fecha)",true),Pq("datos","Sus nombre y puesto",true)]},
    es_conciliacion:{ic:"⚖️",n:"Papeleta de conciliación (SMAC)",cat:"es_trabajo",tier:"komplex",q:[Pq("empresa","Empresa demandada: nombre, CIF y dirección",true),Pq("relacion","Su puesto y fechas de la relación laboral",true),Pq("objeto","Objeto (despido, cantidades…)",true),Pq("hechos","Hechos resumidos",true),Pq("peticion","Petición (importe €, readmisión…)",true),Pq("datos","Sus nombre, apellidos, DNI/NIE y domicilio",true)]},

    /* Consumo y Reclamaciones */
    es_hoja_reclamacion:{ic:"📨",n:"Reclamación de consumo (OMIC)",cat:"es_consumo",tier:"standard",q:[Pq("empresa","Empresa reclamada: nombre y dirección",true),Pq("hechos","Hechos (qué pasó, cuándo)",true),Pq("peticion","Su petición",true,["Devolución","Reparación","Sustitución","Compensación"]),Pq("importe","Importe afectado (€, opcional)",false),Pq("datos","Sus nombre, apellidos y DNI/NIE",true)]},
    es_desistimiento:{ic:"🔄",n:"Desistimiento de compra (14 días)",cat:"es_consumo",tier:"einfach",q:[Pq("vendedor","Vendedor: nombre y dirección",true),Pq("pedido","Nº de pedido",true),Pq("fecha","Fecha de compra/recepción",true),Pq("productos","Productos afectados",true),Pq("iban","IBAN para el reembolso (opcional)",false)]},
    es_garantia:{ic:"🔧",n:"Reclamación de garantía legal (3 años)",cat:"es_consumo",tier:"standard",q:[Pq("vendedor","Vendedor: nombre y dirección",true),Pq("producto","Producto (marca/modelo)",true),Pq("fecha_compra","Fecha de compra",true),Pq("defecto","Defecto detectado",true),Pq("peticion","Su petición",true,["Reparación","Sustitución","Rebaja del precio","Devolución"])]},
    es_aerolinea:{ic:"✈️",n:"Reclamación a aerolínea (retraso/cancelación)",cat:"es_consumo",tier:"standard",q:[Pq("aerolinea","Aerolínea",true),Pq("vuelo","Nº de vuelo y fecha",true),Pq("problema","Problema",true,["Retraso","Cancelación","Overbooking","Equipaje"]),Pq("peticion","Su petición (indemnización UE 261/2004…)",true),Pq("datos","Sus nombre y apellidos",true)]},
    es_comisiones:{ic:"💳",n:"Reclamación de comisiones bancarias",cat:"es_consumo",tier:"standard",q:[Pq("banco","Banco: nombre y oficina",true),Pq("comisiones","Comisiones reclamadas (concepto, fechas, importes)",true),Pq("motivo","Motivo de la reclamación",true),Pq("datos","Sus nombre y nº de cuenta",true)]},
    es_telefonia_reclamo:{ic:"📶",n:"Reclamación a operador de telefonía",cat:"es_consumo",tier:"standard",q:[Pq("operador","Operador",true),Pq("num_cliente","Nº de cliente",true),Pq("problema","Problema (facturación, permanencia, servicio)",true),Pq("peticion","Su petición",true),Pq("datos","Sus nombre y DNI/NIE",true)]},

    /* Contratos */
    es_compraventa:{ic:"🛒",n:"Contrato de compraventa entre particulares",cat:"es_contratos",tier:"einfach",q:[Pq("rol","Usted es",true,["Vendedor","Comprador"]),Pq("otra_parte","Otra parte: nombre y DNI/NIE",true),Pq("objeto","Objeto de la venta",true),Pq("precio","Precio (€)",true),Pq("entrega","Fecha de entrega",false)]},
    es_deuda:{ic:"💶",n:"Reconocimiento de deuda",cat:"es_contratos",tier:"standard",q:[Pq("acreedor","Acreedor: nombre y dirección",true),Pq("deudor","Deudor: nombre y dirección",true),Pq("importe","Importe (€, en cifras y letras)",true),Pq("plazo","Plazo/forma de devolución",true),Pq("interes","¿Con intereses?",false,["Sin interés","Con interés"])]},
    es_prestamo:{ic:"🤝",n:"Contrato de préstamo entre particulares",cat:"es_contratos",tier:"standard",q:[Pq("prestamista","Prestamista: nombre y DNI/NIE",true),Pq("prestatario","Prestatario: nombre y DNI/NIE",true),Pq("importe","Importe prestado (€)",true),Pq("devolucion","Plan de devolución",true),Pq("interes","Interés (%, opcional)",false)]},
    es_autorizacion:{ic:"📝",n:"Autorización / poder simple",cat:"es_contratos",tier:"einfach",q:[Pq("autorizado","Persona autorizada: nombre y DNI/NIE",true),Pq("objeto","Para qué se autoriza",true),Pq("vigencia","Vigencia (opcional)",false),Pq("datos","Sus (autorizante) nombre y DNI/NIE",true)]},
    es_servicios:{ic:"🧰",n:"Contrato de prestación de servicios",cat:"es_contratos",tier:"komplex",q:[Pq("rol","Usted es",true,["Prestador","Cliente"]),Pq("otra_parte","Otra parte: nombre y NIF/DNI",true),Pq("servicio","Descripción del servicio",true),Pq("precio","Precio y forma de pago (€)",true),Pq("plazo","Plazo/duración",false),Pq("clausulas","Cláusulas especiales (opcional)",false)]},
    es_confidencialidad:{ic:"🔒",n:"Acuerdo de confidencialidad (NDA)",cat:"es_contratos",tier:"standard",q:[Pq("otra_parte","Otra parte: nombre y dirección",true),Pq("objeto","Objeto de la colaboración",true),Pq("alcance","Alcance de la información confidencial",true),Pq("duracion","Duración (opcional)",false)]},

    /* Administración Pública */
    es_sepe:{ic:"💼",n:"Solicitud / reclamación al SEPE (paro)",cat:"es_admin",tier:"standard",q:[Pq("objeto","Objeto",true,["Solicitud de prestación","Reclamación previa","Información"]),Pq("situacion","Su situación (última empresa, fecha de cese)",true),Pq("datos","Sus nombre, apellidos, DNI/NIE y nº afiliación (opcional)",true)]},
    es_hacienda:{ic:"🧾",n:"Escrito de alegaciones a Hacienda (AEAT)",cat:"es_admin",tier:"komplex",q:[Pq("oficina","Delegación/Administración de la AEAT",true),Pq("referencia","Nº de expediente/referencia",true),Pq("objeto","Acto contra el que alega (liquidación, sanción…)",true),Pq("alegaciones","Sus alegaciones",true),Pq("datos","Sus nombre, apellidos y NIF",true)]},
    es_aplazamiento:{ic:"📅",n:"Solicitud de aplazamiento de deuda tributaria",cat:"es_admin",tier:"standard",q:[Pq("oficina","Oficina de la AEAT",true),Pq("deuda","Deuda (concepto e importe €)",true),Pq("situacion","Situación económica",true),Pq("propuesta","Plan de pagos propuesto",false),Pq("datos","Sus nombre y NIF",true)]},
    es_multa:{ic:"🚗",n:"Recurso de multa de tráfico (DGT)",cat:"es_admin",tier:"standard",q:[Pq("expediente","Nº de expediente",true),Pq("fecha","Fecha de la denuncia",true),Pq("matricula","Matrícula",false),Pq("motivos","Motivos del recurso",true),Pq("datos","Sus nombre, apellidos y DNI/NIE",true)]},
    es_seguridad_social:{ic:"🏥",n:"Escrito a la Seguridad Social (INSS/TGSS)",cat:"es_admin",tier:"standard",q:[Pq("organismo","Organismo (INSS/TGSS) y oficina",true),Pq("objeto","Objeto (pensión, prestación, cotizaciones…)",true),Pq("exposicion","Exposición de los hechos",true),Pq("peticion","Su petición",true),Pq("datos","Sus nombre, DNI/NIE y nº afiliación",true)]},
    es_empadronamiento:{ic:"🏠",n:"Solicitud de empadronamiento / volante",cat:"es_admin",tier:"einfach",q:[Pq("ayuntamiento","Ayuntamiento",true),Pq("direccion","Dirección de residencia",true),Pq("objeto","Objeto",true,["Alta de empadronamiento","Cambio de domicilio","Volante/certificado"]),Pq("datos","Sus nombre, apellidos y DNI/NIE",true)]},
    es_extranjeria:{ic:"🛂",n:"Escrito a Extranjería (TIE/arraigo/renovación)",cat:"es_admin",tier:"komplex",q:[Pq("oficina","Oficina de Extranjería (provincia)",true),Pq("tramite","Trámite",true,["Renovación TIE","Arraigo","Reagrupación","Otro"]),Pq("situacion","Su situación (resumen)",true),Pq("datos","Sus nombre, NIE/pasaporte",true)]},
    es_vida_laboral:{ic:"📊",n:"Solicitud de informe de vida laboral",cat:"es_admin",tier:"einfach",q:[Pq("datos","Sus nombre, apellidos y DNI/NIE",true),Pq("num_ss","Nº de la Seguridad Social (opcional)",false)]}
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
      return (typeof DOCS!=="undefined" && DOCS.es_conciliacion)?true:false;
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
if($src!==$start){ file_put_contents($file,$src); echo "Ispanya modulu eklendi (CH_ES_MODULE) - ~41 belge, 6 kategori, Ispanyolca.\n"; }
else echo "degisiklik yok\n";
echo "\nNOT: Belge dili/hukuku icin apply-lawsys-all.php de calistirilmali (lawSystems['ES'] + doc-lang).\n";
echo "SONRA: opcache-reset (pull-updates otomatik yapar). SIL: rm apply-espana.php\n";
echo "Test: Ayarlar -> Espana sec -> kategoriler Ispanyolca -> 'Papeleta de conciliacion (SMAC)' uret.\n";
