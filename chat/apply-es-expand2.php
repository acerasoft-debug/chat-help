<?php
/**
 * ChatHelp — apply-es-expand2 (CH_ES_EXPAND2) — İspanya katalog genişletme #2
 *  Yeni kategoriler: "Herencias y Sucesiones" (es_herencias) + "Denuncias y
 *  Penal" (es_penal). Ayrica es_trabajo/es_vivienda/es_consumo/es_admin/
 *  es_contratos'a ekler — toplam 20 yeni belge. İspanyol hukukuna göre (CC,
 *  ET, LAU, LPH, Ley 39/2015, LECrim). ES expansion #1 ile ayni deferred
 *  injection. Mevcut es_ anahtarlariyla cakismaz.
 * KULLANIM: pull-updates.php?files=apply-es-expand2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-es-expand2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_ES_EXPAND2')!==false) exit("Zaten ekli (CH_ES_EXPAND2).\n");
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if (strpos($src,$anchor)===false) exit("HATA: anchor (getDocPrice) yok.\n");

$js = <<<'CHJS'

/* CH_ES_EXPAND2 — İspanya katalog genişletme #2 (+20 belge, 2 yeni kategori, deferred) */
try{(function(){
  function Pe(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var NEWCATS={
    es_herencias:{n:"Herencias y Sucesiones",ic:"📜"},
    es_penal:{n:"Denuncias y Penal",ic:"🚔"}
  };

  var E={
    /* Herencias y Sucesiones (nueva) */
    es_aceptacion_herencia:{ic:"📜",n:"Aceptación de herencia a beneficio de inventario",cat:"es_herencias",tier:"komplex",q:[Pe("causante","Causante (fallecido): nombre y fecha de defunción",true),Pe("heredero","Heredero: nombre y DNI/NIE",true),Pe("relacion","Parentesco/título sucesorio (testamento o ley)",true),Pe("bienes","Bienes y deudas conocidos (resumen)",false),Pe("nota","NOTA: la aceptación formal se realiza ante notario",false)]},
    es_renuncia_herencia:{ic:"🚫",n:"Renuncia de herencia",cat:"es_herencias",tier:"standard",q:[Pe("causante","Causante: nombre y fecha de defunción",true),Pe("renunciante","Renunciante: nombre y DNI/NIE",true),Pe("motivo","Motivo (opcional)",false),Pe("nota","NOTA: la renuncia debe formalizarse ante notario",false)]},
    es_testamento_borrador:{ic:"✍️",n:"Borrador de testamento (para notario)",cat:"es_herencias",tier:"komplex",q:[Pe("testador","Testador: nombre, DNI/NIE y estado civil",true),Pe("herederos","Herederos y legatarios (nombre y parte/legado)",true),Pe("legados","Legados específicos (opcional)",false),Pe("albacea","Albacea (opcional)",false),Pe("nota","NOTA: sólo es válido otorgado ante notario",false)]},
    es_declaracion_herederos:{ic:"🧾",n:"Solicitud de declaración de herederos (abintestato)",cat:"es_herencias",tier:"komplex",q:[Pe("causante","Causante: nombre, DNI y fecha de defunción",true),Pe("solicitante","Solicitante: nombre y DNI/NIE",true),Pe("parentesco","Parentesco con el causante",true),Pe("otros","Otros posibles herederos (nombres)",false)]},
    es_particion_herencia:{ic:"📊",n:"Cuaderno particional (borrador de reparto)",cat:"es_herencias",tier:"komplex",q:[Pe("causante","Causante: nombre",true),Pe("herederos","Herederos (nombre y cuota)",true),Pe("inventario","Inventario de bienes y valor (resumen)",true),Pe("reparto","Propuesta de adjudicación",true)]},

    /* Denuncias y Penal (nueva) */
    es_denuncia_penal:{ic:"🚔",n:"Denuncia penal",cat:"es_penal",tier:"komplex",q:[Pe("hechos","Hechos denunciados (qué, cuándo, dónde)",true),Pe("denunciado","Persona denunciada (si se conoce)",false),Pe("dano","Daño o perjuicio sufrido",true),Pe("pruebas","Pruebas o testigos disponibles",false),Pe("datos","Sus nombre, apellidos, DNI/NIE y domicilio",true)]},
    es_denuncia_estafa:{ic:"💻",n:"Denuncia por estafa online",cat:"es_penal",tier:"komplex",q:[Pe("plataforma","Plataforma/medio (web, app, red social)",true),Pe("hechos","Descripción de la estafa (cronología)",true),Pe("importe","Importe defraudado (€)",true),Pe("datos_pago","Datos del pago (cuenta, bizum, tarjeta)",false),Pe("pruebas","Capturas/pruebas disponibles",false),Pe("datos","Sus nombre, apellidos y DNI/NIE",true)]},
    es_orden_proteccion:{ic:"🛡️",n:"Solicitud de orden de protección",cat:"es_penal",tier:"komplex",q:[Pe("agresor","Persona denunciada: nombre y relación",true),Pe("hechos","Hechos y situación de riesgo",true),Pe("medidas","Medidas solicitadas (alejamiento, no comunicación…)",true),Pe("datos","Sus nombre, apellidos y DNI/NIE",true)]},
    es_copia_atestado:{ic:"📁",n:"Solicitud de copia de denuncia/atestado",cat:"es_penal",tier:"einfach",q:[Pe("organo","Comisaría/juzgado y nº de atestado o diligencias",true),Pe("fecha","Fecha de la denuncia",true),Pe("datos","Sus nombre, apellidos y DNI/NIE",true)]},

    /* Trabajo */
    es_finiquito:{ic:"🧾",n:"Reclamación de finiquito",cat:"es_trabajo",tier:"standard",q:[Pe("empresa","Empresa: nombre y CIF",true),Pe("fin","Fecha de fin de contrato",true),Pe("conceptos","Conceptos reclamados (salario, vacaciones, parte proporcional)",true),Pe("importe","Importe reclamado (€)",true),Pe("datos","Sus nombre, apellidos y DNI/NIE",true)]},
    es_dimision:{ic:"📝",n:"Carta de baja voluntaria (dimisión)",cat:"es_trabajo",tier:"einfach",q:[Pe("empresa","Empresa: nombre",true),Pe("puesto","Su puesto",true),Pe("ultimo_dia","Último día de trabajo (respetando preaviso)",true),Pe("datos","Sus nombre y apellidos",true)]},
    es_certificado_empresa:{ic:"📄",n:"Solicitud de certificado de empresa (SEPE)",cat:"es_trabajo",tier:"einfach",q:[Pe("empresa","Empresa: nombre y CIF",true),Pe("fin","Fecha de fin de la relación laboral",true),Pe("motivo","Motivo (tramitar prestación por desempleo)",false),Pe("datos","Sus nombre, apellidos y DNI/NIE",true)]},

    /* Vivienda */
    es_reclamacion_comunidad:{ic:"🏢",n:"Reclamación a la comunidad de propietarios",cat:"es_vivienda",tier:"standard",q:[Pe("comunidad","Comunidad y administrador",true),Pe("asunto","Asunto (derrama, humedad, ruido, cuentas…)",true),Pe("peticion","Su petición",true),Pe("datos","Sus nombre, apellidos y nº de vivienda",true)]},
    es_impugnacion_junta:{ic:"⚖️",n:"Impugnación de acuerdo de junta de propietarios",cat:"es_vivienda",tier:"komplex",q:[Pe("comunidad","Comunidad de propietarios",true),Pe("acuerdo","Acuerdo impugnado y fecha de la junta",true),Pe("motivo","Motivo (contrario a ley/estatutos, abusivo…)",true),Pe("datos","Sus nombre, apellidos y nº de vivienda",true)]},

    /* Consumo */
    es_reclamacion_aerolinea:{ic:"✈️",n:"Reclamación a aerolínea (retraso/cancelación)",cat:"es_consumo",tier:"standard",q:[Pe("aerolinea","Aerolínea",true),Pe("vuelo","Nº de vuelo, ruta y fecha",true),Pe("incidencia","Incidencia",true,["Cancelación","Retraso >3h","Overbooking","Equipaje"]),Pe("gastos","Gastos ocasionados / compensación (€)",false),Pe("datos","Sus nombre, apellidos y localizador",true)]},
    es_hoja_reclamaciones:{ic:"📋",n:"Hoja de reclamaciones (consumo)",cat:"es_consumo",tier:"einfach",q:[Pe("empresa","Establecimiento/empresa y dirección",true),Pe("hechos","Hechos reclamados",true),Pe("peticion","Su petición (devolución, reparación…)",true),Pe("datos","Sus nombre, apellidos y DNI/NIE",true)]},

    /* Administración */
    es_recurso_alzada:{ic:"⬆️",n:"Recurso de alzada",cat:"es_admin",tier:"komplex",q:[Pe("organo","Órgano que dictó el acto y superior jerárquico",true),Pe("acto","Acto recurrido (nº expediente y fecha de notificación)",true),Pe("motivos","Motivos del recurso",true),Pe("peticion","Su petición",true),Pe("datos","Sus nombre, apellidos y DNI/NIE",true)]},
    es_silencio_administrativo:{ic:"⏳",n:"Escrito por silencio administrativo",cat:"es_admin",tier:"standard",q:[Pe("organismo","Organismo",true),Pe("solicitud","Solicitud presentada (fecha y nº de registro)",true),Pe("plazo","Plazo transcurrido sin respuesta",true),Pe("peticion","Su petición (resolución expresa)",true),Pe("datos","Sus nombre, apellidos y DNI/NIE",true)]},

    /* Contratos */
    es_contrato_prestamo:{ic:"💶",n:"Contrato de préstamo entre particulares",cat:"es_contratos",tier:"standard",q:[Pe("prestamista","Prestamista: nombre y DNI/NIE",true),Pe("prestatario","Prestatario: nombre y DNI/NIE",true),Pe("importe","Importe prestado (€)",true),Pe("interes","Interés (%) — si no hay, indique 'sin interés'",false),Pe("devolucion","Forma y plazo de devolución",true)]},
    es_reconocimiento_deuda:{ic:"🖋️",n:"Reconocimiento de deuda",cat:"es_contratos",tier:"standard",q:[Pe("deudor","Deudor: nombre y DNI/NIE",true),Pe("acreedor","Acreedor: nombre y DNI/NIE",true),Pe("importe","Importe reconocido (€)",true),Pe("origen","Origen de la deuda",true),Pe("pago","Forma y plazo de pago acordado",true)]}
  };

  function inject(){
    try{
      if(typeof DOCS==="undefined"||!DOCS) return false;
      if(typeof CATS==="undefined"||!CATS.es_bajas) return false;
      for(var k in E){ if(!DOCS[k]) DOCS[k]=E[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=E[k].tier||"standard"; }
      for(var nc in NEWCATS){ if(!CATS[nc]) CATS[nc]={n:NEWCATS[nc].n,ic:NEWCATS[nc].ic,docs:[],country:"ES"}; if(!CATS[nc].docs) CATS[nc].docs=[]; }
      for(var k2 in E){ var c2=E[k2].cat; if(CATS[c2]&&CATS[c2].docs&&CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); }
      if(typeof CAT_LABELS!=="undefined"){ for(var cl in NEWCATS) CAT_LABELS[cl]=NEWCATS[cl].n; }
      if(typeof CAT_DOCS!=="undefined"){
        for(var cd in NEWCATS){ if(!CAT_DOCS[cd]) CAT_DOCS[cd]=[]; }
        for(var k3 in E){ var c3=E[k3].cat; if(CAT_DOCS[c3]){ var ex=false; for(var i=0;i<CAT_DOCS[c3].length;i++){ if(CAT_DOCS[c3][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[c3].push({k:k3,ic:E[k3].ic,t:E[k3].n,tier:E[k3].tier||"standard"}); } }
      }
      return !!(DOCS.es_denuncia_penal);
    }catch(e){ return false; }
  }
  var done=false, ticks=0;
  function run(){ if(!done){ if(inject()) done=true; } if(ticks++<40) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}
CHJS;

$src=str_replace($anchor,$anchor.$js,$src);

$tmp = tempnam(sys_get_temp_dir(),'e2').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-esexp2-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "  ✓ 20 yeni İspanyol belgesi + 'Herencias' ve 'Denuncias y Penal' kategorileri eklendi\n";
echo "\n✓ CH_ES_EXPAND2 uygulandi. İspanya kataloğu daha da genişletildi.\n";
