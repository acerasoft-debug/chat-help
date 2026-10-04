<?php
/**
 * ChatHelp — İSPANYA KATALOG GENİŞLETME (+36 belge, 1 yeni kategori: Familia)
 * --------------------------------------------------------------------------------
 *  Mevcut 6 ES kategorisine yeni belgeler ekler + "Familia" kategorisi açar
 *  (yeni kategori enjeksiyonu apply-turkiye-expand.php ile aynı kanıtlanmış desen).
 *  Öne çıkanlar: convenio regulador, pensión de alimentos, contrato de arras,
 *  burofax por impago, denuncia Inspección de Trabajo, reclamación Banco de España,
 *  derechos RGPD, recurso de reposición, justicia gratuita.
 *  Tüm belgeler tier'lı -> Stripe ödeme otomatik. Belge dili/hukuku CC üzerinden
 *  otomatik (lawSystems['ES'] + doc-lang zaten api.php'de).
 *
 * KULLANIM: chat-help.com/chat/apply-espana-expand.php -> opcache-reset.php. SİL.
 * GEREKLİ: apply-espana.php önce çalıştırılmış olmalı (CH_ES_MODULE, es_* kategoriler).
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-espana-expand BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,"CH_ES_EXPAND")!==false) exit("Zaten ekli (CH_ES_EXPAND).\n");
if(strpos($src,"CH_ES_MODULE")===false) exit("CH_ES_MODULE yok — once apply-espana.php calistir.\n");
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-esexpand-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_ES_EXPAND — İspanya katalog genişletme (+36 belge, yeni kategori: Familia; deferred yükleme) */
try{(function(){
  function Pe(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}

  var NEWCATS={
    es_familia:{n:"Familia",ic:"👨‍👩‍👧"}
  };

  var E={
    /* Familia (yeni kategori) */
    es_convenio_regulador:{ic:"📜",n:"Convenio regulador (divorcio de mutuo acuerdo)",cat:"es_familia",tier:"komplex",q:[Pe("conyuges","Nombres y apellidos de ambos cónyuges",true),Pe("fecha_matrimonio","Fecha del matrimonio",true),Pe("hijos","Hijos en común (nombre/edad, si no hay: 'ninguno')",true),Pe("custodia","Régimen de custodia acordado (si hay hijos)",false),Pe("pension","Pensión de alimentos/compensatoria (€, si no hay: 'ninguna')",false),Pe("vivienda_bienes","Uso de la vivienda y reparto de bienes (resumen)",true)]},
    es_pension_alimentos:{ic:"💶",n:"Reclamación de pensión de alimentos",cat:"es_familia",tier:"komplex",q:[Pe("obligado","Progenitor obligado al pago: nombre",true),Pe("hijos","Hijos beneficiarios (nombre/edad)",true),Pe("importe_fijado","Pensión fijada (€/mes) y resolución que la fijó",true),Pe("impago","Mensualidades impagadas e importe total adeudado (€)",true),Pe("datos","Sus nombre, apellidos y DNI/NIE",true)]},
    es_modificacion_medidas:{ic:"⚖️",n:"Escrito de modificación de medidas",cat:"es_familia",tier:"komplex",q:[Pe("resolucion","Sentencia/convenio a modificar (fecha y juzgado)",true),Pe("otra_parte","Otra parte: nombre",true),Pe("medida","Medida que desea modificar",true,["Pensión de alimentos","Custodia","Régimen de visitas","Uso de la vivienda","Otra"]),Pe("cambio","Cambio sustancial de circunstancias (motivo)",true),Pe("peticion","Nueva medida solicitada",true)]},
    es_viaje_menor:{ic:"✈️",n:"Autorización de viaje de menor",cat:"es_familia",tier:"standard",q:[Pe("menor","Nombre y fecha de nacimiento del menor",true),Pe("acompanante","Persona con la que viaja el menor: nombre y DNI/NIE",true),Pe("autorizante","Progenitor que autoriza: nombre y DNI/NIE",true),Pe("destino","País/lugar de destino",true),Pe("fechas","Fechas del viaje",true)]},
    es_capitulaciones:{ic:"💍",n:"Capitulaciones matrimoniales (separación de bienes, borrador)",cat:"es_familia",tier:"komplex",q:[Pe("partes","Nombres y apellidos de ambas partes",true),Pe("regimen","Régimen elegido",true,["Separación de bienes","Participación","Gananciales con pactos"]),Pe("bienes","Bienes privativos a reseñar (opcional)",false),Pe("nota","NOTA: para su validez requiere escritura ante notario",false)]},
    es_pareja_hecho:{ic:"🤝",n:"Solicitud de inscripción de pareja de hecho",cat:"es_familia",tier:"standard",q:[Pe("registro","Registro de parejas de hecho (comunidad/ayuntamiento)",true),Pe("miembros","Nombres, apellidos y DNI/NIE de ambos miembros",true),Pe("convivencia","Dirección y tiempo de convivencia",true),Pe("empadronamiento","¿Están empadronados juntos?",false,["Sí","No"])]},
    es_custodia_visitas:{ic:"👨‍👩‍👧",n:"Acuerdo de custodia y régimen de visitas",cat:"es_familia",tier:"komplex",q:[Pe("progenitores","Nombres de ambos progenitores",true),Pe("hijos","Hijos (nombre/edad)",true),Pe("custodia","Tipo de custodia acordada",true,["Compartida","Materna con visitas","Paterna con visitas"]),Pe("calendario","Calendario de estancias/visitas (fines de semana, vacaciones)",true),Pe("gastos","Reparto de gastos ordinarios y extraordinarios",false)]},

    /* Contratos */
    es_arras:{ic:"🏠",n:"Contrato de arras (compraventa de inmueble)",cat:"es_contratos",tier:"komplex",q:[Pe("rol","Usted es",true,["Vendedor","Comprador"]),Pe("otra_parte","Otra parte: nombre y DNI/NIE",true),Pe("inmueble","Inmueble (dirección y referencia catastral si la conoce)",true),Pe("precio","Precio total de compraventa (€)",true),Pe("arras","Importe de las arras (€)",true),Pe("tipo_arras","Tipo de arras",true,["Penitenciales (art. 1454 CC)","Confirmatorias","Penales"]),Pe("plazo","Plazo para otorgar escritura",true)]},
    es_compraventa_vehiculo:{ic:"🚗",n:"Contrato de compraventa de vehículo",cat:"es_contratos",tier:"standard",q:[Pe("rol","Usted es",true,["Vendedor","Comprador"]),Pe("otra_parte","Otra parte: nombre y DNI/NIE",true),Pe("vehiculo","Vehículo (marca/modelo/año/matrícula/bastidor)",true),Pe("km","Kilometraje",false),Pe("precio","Precio (€)",true),Pe("entrega","Fecha de entrega y cambio de titularidad (DGT)",false)]},
    es_alquiler_habitacion:{ic:"🚪",n:"Contrato de alquiler de habitación",cat:"es_contratos",tier:"standard",q:[Pe("rol","Usted es",true,["Arrendador","Inquilino"]),Pe("otra_parte","Otra parte: nombre y DNI/NIE",true),Pe("direccion","Dirección de la vivienda y habitación alquilada",true),Pe("renta","Renta mensual (€)",true),Pe("gastos","Gastos incluidos (luz, agua, internet…)",false),Pe("duracion","Duración e inicio del contrato",true)]},
    es_contrato_obra:{ic:"🔨",n:"Contrato de obra / reforma",cat:"es_contratos",tier:"komplex",q:[Pe("rol","Usted es",true,["Cliente (propietario)","Contratista"]),Pe("otra_parte","Otra parte: nombre y NIF/DNI",true),Pe("obra","Descripción y alcance de la obra",true),Pe("precio","Presupuesto total (€)",true),Pe("pagos","Plan de pagos (anticipos, certificaciones)",false),Pe("plazo","Plazo de ejecución/entrega",true),Pe("penalizacion","¿Penalización por retraso?",false,["Sí","No"])]},
    es_cesion_contrato:{ic:"🔁",n:"Cesión de contrato",cat:"es_contratos",tier:"standard",q:[Pe("cedente","Cedente: nombre y DNI/NIE",true),Pe("cesionario","Cesionario: nombre y DNI/NIE",true),Pe("contrato","Contrato que se cede (objeto, fecha, otra parte)",true),Pe("consentimiento","¿Consta el consentimiento de la otra parte contratante?",true,["Sí","Pendiente"]),Pe("fecha","Fecha de efectos de la cesión",true)]},
    es_comodato:{ic:"🔄",n:"Contrato de comodato (préstamo de uso)",cat:"es_contratos",tier:"einfach",q:[Pe("comodante","Comodante (quien presta): nombre y DNI/NIE",true),Pe("comodatario","Comodatario (quien recibe): nombre y DNI/NIE",true),Pe("bien","Bien prestado (descripción)",true),Pe("duracion","Duración / fecha de devolución",true)]},
    es_servicio_domestico:{ic:"🧹",n:"Contrato de servicio doméstico",cat:"es_contratos",tier:"standard",q:[Pe("empleador","Empleador: nombre y DNI/NIE",true),Pe("empleado","Empleado/a de hogar: nombre y DNI/NIE",true),Pe("tareas","Tareas y lugar de trabajo",true),Pe("jornada","Jornada (horas/semana) y horario",true),Pe("salario","Salario (€/mes o €/hora, mínimo SMI)",true),Pe("inicio","Fecha de inicio y duración",true)]},
    es_aval_personal:{ic:"🖋️",n:"Aval / garantía personal (fianza)",cat:"es_contratos",tier:"standard",q:[Pe("avalista","Avalista: nombre y DNI/NIE",true),Pe("avalado","Avalado (deudor): nombre y DNI/NIE",true),Pe("beneficiario","Beneficiario (acreedor): nombre",true),Pe("obligacion","Obligación garantizada (contrato, importe €)",true),Pe("limite","Límite del aval (€) y duración",false)]},

    /* Vivienda y Alquiler */
    es_burofax_impago:{ic:"📮",n:"Burofax por impago de renta (arrendador)",cat:"es_vivienda",tier:"standard",q:[Pe("inquilino","Inquilino: nombre y dirección",true),Pe("direccion","Dirección de la vivienda arrendada",true),Pe("meses","Mensualidades impagadas",true),Pe("importe","Importe total adeudado (€)",true),Pe("plazo","Plazo concedido para el pago (días)",false),Pe("datos","Sus nombre, apellidos y DNI/NIE",true)]},
    es_requerimiento_desahucio:{ic:"⚠️",n:"Requerimiento previo a desahucio",cat:"es_vivienda",tier:"komplex",q:[Pe("inquilino","Inquilino: nombre y dirección",true),Pe("direccion","Dirección de la vivienda",true),Pe("causa","Causa del requerimiento",true,["Impago de renta","Fin de contrato sin desalojo","Uso indebido","Otra"]),Pe("deuda","Deuda pendiente (€, si aplica)",false),Pe("plazo","Plazo para pagar/desalojar (días)",true),Pe("datos","Sus nombre, apellidos y DNI/NIE",true)]},
    es_notificacion_venta:{ic:"🏷️",n:"Notificación de venta al inquilino (tanteo/retracto)",cat:"es_vivienda",tier:"standard",q:[Pe("inquilino","Inquilino: nombre y dirección",true),Pe("direccion","Dirección de la vivienda en venta",true),Pe("precio","Precio y condiciones de la venta (€)",true),Pe("plazo","Plazo para ejercer el tanteo (30 días naturales, LAU)",false),Pe("datos","Sus (arrendador) nombre y apellidos",true)]},
    es_autorizacion_subarriendo:{ic:"🔑",n:"Autorización de subarriendo",cat:"es_vivienda",tier:"standard",q:[Pe("arrendador","Arrendador: nombre y dirección",true),Pe("direccion","Dirección de la vivienda",true),Pe("alcance","Alcance (parcial: qué parte de la vivienda)",true),Pe("subarrendatario","Subarrendatario previsto (si se conoce)",false),Pe("datos","Sus (inquilino) nombre, apellidos y DNI/NIE",true)]},
    es_resolucion_necesidad:{ic:"🏚️",n:"Resolución de contrato por obras/necesidad (arrendador)",cat:"es_vivienda",tier:"komplex",q:[Pe("inquilino","Inquilino: nombre y dirección",true),Pe("direccion","Dirección de la vivienda",true),Pe("causa","Causa",true,["Necesidad de vivienda para el arrendador/familiar (art. 9.3 LAU)","Obras de rehabilitación","Otra"]),Pe("justificacion","Justificación de la causa",true),Pe("fecha","Fecha prevista de recuperación de la vivienda",true),Pe("datos","Sus nombre, apellidos y DNI/NIE",true)]},

    /* Trabajo y Empleo */
    es_adaptacion_jornada:{ic:"🕒",n:"Solicitud de adaptación de jornada (conciliación)",cat:"es_trabajo",tier:"standard",q:[Pe("empresa","Empresa/RRHH",true),Pe("puesto","Su puesto y jornada actual",true),Pe("adaptacion","Adaptación solicitada (horario, turno, teletrabajo — art. 34.8 ET)",true),Pe("motivo","Motivo de conciliación (hijos, dependientes…)",true),Pe("inicio","Fecha de inicio deseada",false)]},
    es_inspeccion_trabajo:{ic:"🚨",n:"Denuncia a la Inspección de Trabajo",cat:"es_trabajo",tier:"komplex",q:[Pe("empresa","Empresa denunciada: nombre, CIF y dirección",true),Pe("hechos","Hechos denunciados (horas sin pagar, sin alta, seguridad…)",true),Pe("periodo","Periodo en que ocurren los hechos",false),Pe("pruebas","Pruebas disponibles (opcional)",false),Pe("datos","Sus nombre, apellidos y DNI/NIE (la denuncia no es anónima)",true)]},
    es_preaviso_temporal:{ic:"📅",n:"Preaviso de fin de contrato temporal",cat:"es_trabajo",tier:"einfach",q:[Pe("empresa","Empresa: nombre y dirección",true),Pe("contrato","Tipo de contrato y fecha de fin",true),Pe("ultimo_dia","Último día de trabajo",true),Pe("datos","Sus nombre, apellidos y DNI/NIE",true)]},
    es_anticipo_nomina:{ic:"💳",n:"Solicitud de anticipo de nómina",cat:"es_trabajo",tier:"einfach",q:[Pe("empresa","Empresa/RRHH",true),Pe("importe","Importe solicitado (€, a cuenta del trabajo ya realizado)",true),Pe("motivo","Motivo (opcional)",false),Pe("datos","Sus nombre y puesto",true)]},
    es_respuesta_amonestacion:{ic:"📨",n:"Respuesta a carta de amonestación (trabajador)",cat:"es_trabajo",tier:"standard",q:[Pe("empresa","Empresa: nombre y dirección",true),Pe("fecha_carta","Fecha de la amonestación recibida",true),Pe("imputacion","Hechos que se le imputan",true),Pe("descargo","Su versión de los hechos / alegaciones",true),Pe("datos","Sus nombre, apellidos y puesto",true)]},

    /* Consumo y Reclamaciones */
    es_banco_espana:{ic:"🏛️",n:"Reclamación al Banco de España",cat:"es_consumo",tier:"komplex",q:[Pe("entidad","Entidad reclamada: nombre",true),Pe("previa","Reclamación previa al SAC de la entidad (fecha y resultado)",true),Pe("hechos","Hechos reclamados (comisiones, hipoteca, cuenta…)",true),Pe("peticion","Su petición (importe €, rectificación…)",true),Pe("datos","Sus nombre, apellidos y DNI/NIE",true)]},
    es_reclamacion_aseguradora:{ic:"🛡️",n:"Reclamación a la aseguradora (DGSFP)",cat:"es_consumo",tier:"standard",q:[Pe("aseguradora","Aseguradora: nombre",true),Pe("poliza","Nº de póliza y tipo de seguro",true),Pe("hechos","Hechos (siniestro rechazado, demora, subida…)",true),Pe("previa","¿Reclamó ya al servicio de atención al cliente?",true,["Sí, sin respuesta en 1 mes","Sí, respuesta desfavorable","No"]),Pe("peticion","Su petición (importe €, cobertura…)",true),Pe("datos","Sus nombre, apellidos y DNI/NIE",true)]},
    es_devolucion_matricula:{ic:"🎓",n:"Devolución de matrícula de academia/curso",cat:"es_consumo",tier:"standard",q:[Pe("centro","Academia/centro: nombre y dirección",true),Pe("curso","Curso y fecha de matrícula",true),Pe("importe","Importe pagado (€)",true),Pe("motivo","Motivo de la devolución (no iniciado, incumplimiento…)",true),Pe("datos","Sus nombre, apellidos y DNI/NIE",true)]},
    es_cobro_indebido:{ic:"💸",n:"Reclamación por cobro indebido de suscripción",cat:"es_consumo",tier:"standard",q:[Pe("empresa","Empresa: nombre",true),Pe("cargos","Cargos indebidos (fechas e importes €)",true),Pe("motivo","Por qué es indebido (baja tramitada, no contratado…)",true),Pe("iban","IBAN o tarjeta afectada (últimos 4 dígitos)",false),Pe("datos","Sus nombre y apellidos",true)]},
    es_baja_subida_tarifa:{ic:"📈",n:"Carta de baja por subida de tarifa",cat:"es_consumo",tier:"einfach",q:[Pe("empresa","Empresa (operador, plataforma…): nombre",true),Pe("num_cliente","Nº de cliente/contrato",true),Pe("subida","Subida comunicada (tarifa antigua y nueva, €)",true),Pe("datos","Sus nombre, apellidos y DNI/NIE",true)]},

    /* Administración Pública */
    es_rgpd:{ic:"🔒",n:"Ejercicio de derechos RGPD (acceso/supresión)",cat:"es_admin",tier:"standard",q:[Pe("responsable","Responsable del tratamiento (empresa/organismo): nombre y dirección",true),Pe("derecho","Derecho que ejerce",true,["Acceso","Supresión (olvido)","Rectificación","Oposición","Portabilidad","Limitación"]),Pe("relacion","Su relación con el responsable (cliente, exempleado…)",true),Pe("datos","Sus nombre, apellidos y DNI/NIE",true)]},
    es_recurso_reposicion:{ic:"⚖️",n:"Recurso de reposición administrativo",cat:"es_admin",tier:"komplex",q:[Pe("organo","Órgano que dictó el acto",true),Pe("acto","Acto recurrido (nº expediente, fecha de notificación)",true),Pe("motivos","Motivos del recurso (alegaciones)",true),Pe("peticion","Su petición (anulación, modificación…)",true),Pe("datos","Sus nombre, apellidos y DNI/NIE",true)]},
    es_justicia_gratuita:{ic:"🏛️",n:"Solicitud de justicia gratuita",cat:"es_admin",tier:"standard",q:[Pe("colegio","Colegio de Abogados de la provincia",true),Pe("procedimiento","Procedimiento para el que la solicita",true),Pe("situacion","Situación económica (ingresos, unidad familiar)",true),Pe("datos","Sus nombre, apellidos y DNI/NIE",true)]},
    es_reclamacion_previa:{ic:"📋",n:"Reclamación previa a la vía judicial",cat:"es_admin",tier:"komplex",q:[Pe("organismo","Organismo (INSS, SEPE, mutua…)",true),Pe("resolucion","Resolución impugnada (fecha y nº de expediente)",true),Pe("hechos","Hechos y fundamentos de su reclamación",true),Pe("peticion","Su petición",true),Pe("datos","Sus nombre, apellidos, DNI/NIE y domicilio",true)]},
    es_alegaciones_ayuntamiento:{ic:"🏢",n:"Escrito de alegaciones al ayuntamiento",cat:"es_admin",tier:"standard",q:[Pe("ayuntamiento","Ayuntamiento y departamento",true),Pe("expediente","Nº de expediente (multa, licencia, tasa…)",true),Pe("alegaciones","Sus alegaciones",true),Pe("peticion","Su petición",true),Pe("datos","Sus nombre, apellidos y DNI/NIE",true)]},
    es_solicitud_beca:{ic:"🎓",n:"Solicitud de beca / ayuda",cat:"es_admin",tier:"einfach",q:[Pe("organismo","Organismo convocante (Ministerio, comunidad, ayuntamiento)",true),Pe("beca","Beca/ayuda solicitada (convocatoria)",true),Pe("situacion","Situación académica/económica (resumen)",true),Pe("datos","Sus nombre, apellidos y DNI/NIE",true)]}
  };

  function inject(){
    try{
      if(typeof DOCS==="undefined"||!DOCS) return false;
      if(typeof CATS==="undefined"||!CATS.es_bajas) return false; /* CH_ES_MODULE yüklenmiş olmalı */
      for(var k in E){ if(!DOCS[k]) DOCS[k]=E[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=E[k].tier||"standard"; }
      for(var nc in NEWCATS){ if(!CATS[nc]) CATS[nc]={n:NEWCATS[nc].n,ic:NEWCATS[nc].ic,docs:[],country:"ES"}; if(!CATS[nc].docs) CATS[nc].docs=[]; }
      for(var k2 in E){ var c2=E[k2].cat; if(CATS[c2]&&CATS[c2].docs&&CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); }
      if(typeof CAT_LABELS!=="undefined"){ for(var cl in NEWCATS) CAT_LABELS[cl]=NEWCATS[cl].n; }
      if(typeof CAT_DOCS!=="undefined"){
        for(var cd in NEWCATS){ if(!CAT_DOCS[cd]) CAT_DOCS[cd]=[]; }
        for(var k3 in E){ var c3=E[k3].cat; if(CAT_DOCS[c3]){ var ex=false; for(var i=0;i<CAT_DOCS[c3].length;i++){ if(CAT_DOCS[c3][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[c3].push({k:k3,ic:E[k3].ic,t:E[k3].n,tier:E[k3].tier||"standard"}); } }
      }
      return (typeof DOCS!=="undefined" && DOCS.es_convenio_regulador)?true:false;
    }catch(e){ return false; }
  }

  var done=false, ticks=0;
  function run(){ if(!done){ if(inject()) done=true; } if(ticks++<40) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}
CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
if($src!==$start){ file_put_contents($file,$src); echo "Ispanya genisletme eklendi (CH_ES_EXPAND) - +36 belge, yeni kategori: Familia.\n"; }
else echo "degisiklik yok\n";
echo "\nOzet:\n";
echo "  Familia (YENI): convenio regulador, pension de alimentos, modificacion de medidas, viaje de menor, capitulaciones, pareja de hecho, custodia/visitas (+7)\n";
echo "  Contratos: arras, compraventa vehiculo, alquiler habitacion, obra/reforma, cesion, comodato, servicio domestico, aval (+8)\n";
echo "  Vivienda: burofax impago, requerimiento desahucio, notificacion venta (tanteo), autorizacion subarriendo, resolucion obras/necesidad (+5)\n";
echo "  Trabajo: adaptacion jornada, denuncia Inspeccion, preaviso temporal, anticipo nomina, respuesta amonestacion (+5)\n";
echo "  Consumo: Banco de Espana, aseguradora (DGSFP), devolucion matricula, cobro indebido, baja por subida de tarifa (+5)\n";
echo "  Admin: derechos RGPD, recurso reposicion, justicia gratuita, reclamacion previa, alegaciones ayuntamiento, beca/ayuda (+6)\n";
echo "\nStripe: tum belgeler tier'li -> odeme otomatik. Belge dili/hukuku CC=ES ile otomatik Ispanyolca/Ispanyol hukuku.\n";
echo "SONRA: opcache-reset.php. SIL: rm apply-espana-expand.php\n";
echo "Test: ES sec -> 'Familia' kategorisi gorunmeli; Contratos'ta 'Contrato de arras' gorunmeli.\n";
