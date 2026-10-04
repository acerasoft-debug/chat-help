<?php
/**
 * ChatHelp — apply-widecat-es (CH_WIDECAT_ES) — Ispanya OZEL genisletme
 *  ES: ~41 -> ~84 belge (+43) · 5 YENI kategori:
 *   🛂 Extranjeria e Inmigracion (10 belge — arraigo social/laboral/formacion,
 *      TIE yenileme, reagrupacion familiar, nacionalidad, recurso, NIE...)
 *   👪 Familia y Sucesiones (testamento olografo art. 688 CC — protocolizacion
 *      5 anos notu; poder; testamento vital; pension alimentos; viaje menor)
 *   🚗 Coche y Trafico (compraventa + DGT bildirimi, multa alegaciones 20 dias,
 *      siniestro, taller reclamacion, baja temporal)
 *   🏥 Salud y Prestaciones (reclamacion previa INSS 30 dias, dependencia
 *      Ley 39/2006, historia clinica Ley 41/2002...)
 *   💶 Banca y Finanzas (gastos hipoteca, amortizacion anticipada, operacion
 *      no autorizada RDL 19/2018, revolving revision...)
 *  + mevcut kategorilere 12 ek belge (SMAC papeleta, LPAC reposicion,
 *    Ley 19/2013 transparencia, responsabilidad patrimonial, art. 21 LAU...)
 * KULLANIM: pull-updates.php?files=apply-widecat-es.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-widecat-es BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_WIDECAT_ES')!==false) exit("Zaten ekli (CH_WIDECAT_ES).\n");

$fail=[];

/* ═══ [1] ES: kategoriler + X_ORDER ═══ */
$o1 = "    es_admin:{n:\"Administración Pública\",ic:\"🏛️\"}\n  };\n  var X_ORDER=[\"es_bajas\",\"es_vivienda\",\"es_trabajo\",\"es_consumo\",\"es_contratos\",\"es_admin\"];";
$n1 = "    es_admin:{n:\"Administración Pública\",ic:\"🏛️\"},\n"
    . "    es_extr:{n:\"Extranjería e Inmigración\",ic:\"🛂\"},\n"
    . "    es_familia:{n:\"Familia y Sucesiones\",ic:\"👪\"},\n"
    . "    es_auto:{n:\"Coche y Tráfico\",ic:\"🚗\"},\n"
    . "    es_salud:{n:\"Salud y Prestaciones\",ic:\"🏥\"},\n"
    . "    es_banca:{n:\"Banca y Finanzas\",ic:\"💶\"}\n  };\n"
    . "  var X_ORDER=[\"es_bajas\",\"es_vivienda\",\"es_trabajo\",\"es_consumo\",\"es_contratos\",\"es_admin\",\"es_extr\",\"es_familia\",\"es_auto\",\"es_salud\",\"es_banca\"]; /*CH_WIDECAT_ES*/";
$c=0; $src=str_replace($o1,$n1,$src,$c);
echo ($c===1?"  ✓ [1] ES +5 kategori (Extranjería dahil)\n":"  ✗ [1] ES XC anchor ($c)\n"); if($c!==1)$fail[]=1;

/* ═══ [2] ES: +43 belge ═══ */
$esDocs = <<<'JS'
    /* CH_WIDECAT_ES — ampliación especial España */
    es_arraigo_social:{ic:"🛂",n:"Arraigo social: solicitud/escrito",cat:"es_extr",tier:"komplex",q:[Pq("situacion","Su situación (3 años de permanencia continuada en España)",true),Pq("vinculos","Vínculos familiares en España o informe de arraigo",true),Pq("medios","Medios de vida (contrato de trabajo de 1 año u otros)",true),Pq("oficina","Oficina de Extranjería (provincia)",true),Pq("datos","Sus nombre, apellidos, pasaporte/NIE",true)]},
    es_arraigo_laboral:{ic:"💼",n:"Arraigo laboral: solicitud/escrito",cat:"es_extr",tier:"komplex",q:[Pq("permanencia","Permanencia continuada (2 años)",true),Pq("relacion","Relación laboral acreditable (6 meses; sentencia/acta de Inspección)",true),Pq("oficina","Oficina de Extranjería (provincia)",true),Pq("datos","Sus nombre, apellidos, pasaporte/NIE",true)]},
    es_arraigo_formacion:{ic:"🎓",n:"Arraigo para la formación: solicitud",cat:"es_extr",tier:"komplex",q:[Pq("permanencia","Permanencia continuada (2 años)",true),Pq("formacion","Formación a la que se compromete (reglada/certificado profesionalidad)",true),Pq("oficina","Oficina de Extranjería (provincia)",true),Pq("datos","Sus nombre, apellidos, pasaporte/NIE",true)]},
    es_renovacion_tie:{ic:"🪪",n:"Renovación de residencia / TIE",cat:"es_extr",tier:"standard",q:[Pq("tipo","Tipo de autorización a renovar",true,["Residencia temporal","Residencia y trabajo","Residencia de larga duración","Estudiante"]),Pq("caducidad","Fecha de caducidad (plazo: 60 días antes – 90 después)",true),Pq("cambios","Cambios relevantes (trabajo, domicilio, familia)",false),Pq("datos","Sus nombre, apellidos, NIE",true)]},
    es_reagrupacion:{ic:"👨‍👩‍👧",n:"Reagrupación familiar: solicitud/escrito",cat:"es_extr",tier:"komplex",q:[Pq("reagrupado","Familiar a reagrupar (parentesco, país)",true),Pq("residencia","Su autorización (renovada / larga duración)",true),Pq("vivienda","Informe de vivienda adecuada (ayuntamiento/CCAA)",true),Pq("medios","Medios económicos (IPREM según familia)",true),Pq("datos","Sus nombre, apellidos, NIE",true)]},
    es_nacionalidad:{ic:"🇪🇸",n:"Nacionalidad por residencia: escrito/solicitud",cat:"es_extr",tier:"komplex",q:[Pq("anios","Años de residencia legal",true,["10 años (general)","2 años (iberoamericanos y otros)","1 año (casado/a con español/a u otros)"]),Pq("examenes","Exámenes (CCSE y, si procede, DELE A2)",true,["Aprobados","Pendientes"]),Pq("situacion","Situación actual (trabajo, familia)",true),Pq("datos","Sus nombre, apellidos, NIE",true)]},
    es_recurso_extranjeria:{ic:"⚖️",n:"Recurso contra denegación de Extranjería (reposición, 1 mes)",cat:"es_extr",tier:"komplex",q:[Pq("resolucion","Resolución de (fecha, nº expediente)",true),Pq("notificada","Notificada el (plazo: 1 mes)",true),Pq("objeto","Qué se denegó",true),Pq("motivos","Por qué es errónea (con sus palabras)",true),Pq("datos","Sus nombre, apellidos, pasaporte/NIE",true)]},
    es_solicitud_nie:{ic:"🔢",n:"Solicitud de NIE (EX-15): escrito de apoyo",cat:"es_extr",tier:"einfach",q:[Pq("motivo","Motivo (económico, profesional o social) — descríbalo",true),Pq("lugar","Dónde solicita (España / consulado)",true),Pq("datos","Sus nombre, apellidos, pasaporte",true)]},
    es_autorizacion_regreso:{ic:"🛫",n:"Autorización de regreso: solicitud",cat:"es_extr",tier:"einfach",q:[Pq("motivo","Motivo del viaje con TIE en trámite/renovación",true),Pq("fechas","Fechas previstas del viaje",true),Pq("datos","Sus nombre, apellidos, NIE, pasaporte",true)]},
    es_informe_arraigo:{ic:"🏘️",n:"Solicitud de informe de arraigo/vivienda (ayuntamiento)",cat:"es_extr",tier:"einfach",q:[Pq("ayuntamiento","Ayuntamiento / servicios sociales",true),Pq("informe","Informe solicitado",true,["Informe de arraigo social","Informe de vivienda adecuada (reagrupación)"]),Pq("situacion","Su situación (padrón, tiempo, convivencia)",true),Pq("datos","Sus nombre, apellidos, NIE/pasaporte",true)]},
    es_testamento_olografo:{ic:"📜",n:"Testamento ológrafo (borrador, art. 688 CC)",cat:"es_familia",tier:"komplex",q:[Pq("herederos","Herederos y cuotas/bienes (respetar legítimas)",true),Pq("legados","Legados particulares (opcional)",false),Pq("datos","Sus nombre, apellidos, fecha de nacimiento",true),Pq("nota","Nota: válido SOLO escrito íntegramente a mano, con fecha y firma; tras el fallecimiento debe protocolizarse ante notario en 5 años",false)]},
    es_poder_general:{ic:"📝",n:"Poder / autorización general",cat:"es_familia",tier:"einfach",q:[Pq("apoderado","Apoderado: nombre, DNI/NIE, domicilio",true),Pq("ambito","Ámbito",true,["Un asunto concreto","Gestiones administrativas","Gestiones bancarias","General"]),Pq("detalles","Descripción más precisa",true),Pq("vigencia","Vigencia",false,["Indefinida","Hasta fecha"]),Pq("datos","Sus nombre, apellidos, DNI/NIE, domicilio",true),Pq("nota","Nota: para actos ante notario/registro se exige poder notarial",false)]},
    es_testamento_vital:{ic:"🏥",n:"Instrucciones previas / testamento vital",cat:"es_familia",tier:"komplex",q:[Pq("voluntades","Tratamientos que rechaza/acepta",true),Pq("situaciones","Para qué situaciones clínicas",true),Pq("representante","Representante sanitario (recomendado): nombre y contacto",false),Pq("datos","Sus nombre, apellidos, DNI/NIE",true),Pq("nota","Nota: inscribir en el registro de instrucciones previas de su comunidad autónoma",false)]},
    es_pension_alimentos:{ic:"👨‍👧",n:"Requerimiento: pensión de alimentos impagada",cat:"es_familia",tier:"standard",q:[Pq("obligado","Obligado: nombre y domicilio",true),Pq("titulo","Título (sentencia/convenio de fecha)",true),Pq("deuda","Impagos (meses, importe)",true),Pq("plazo","Plazo de pago (p. ej. 10 días; después vía judicial)",true),Pq("datos","Sus nombre, apellidos y domicilio",true)]},
    es_viaje_menor:{ic:"🧳",n:"Autorización de viaje de menor al extranjero",cat:"es_familia",tier:"einfach",q:[Pq("menor","Menor: nombre, fecha de nacimiento, documento",true),Pq("acompanante","Acompañante: nombre, documento",true),Pq("viaje","País y fechas del viaje",true),Pq("datos","Progenitor(es) que autoriza(n): nombre, documento",true),Pq("nota","Nota: se recomienda firma ante Guardia Civil/Policía/notario",false)]},
    es_pareja_hecho:{ic:"💑",n:"Solicitud de inscripción como pareja de hecho",cat:"es_familia",tier:"standard",q:[Pq("registro","Registro de parejas de hecho (comunidad/ayuntamiento)",true),Pq("pareja","Ambos miembros: nombres, documentos",true),Pq("convivencia","Convivencia acreditable (padrón, tiempo)",true),Pq("datos","Domicilio común",true)]},
    es_compraventa_coche:{ic:"🚗",n:"Compraventa de vehículo entre particulares",cat:"es_auto",tier:"standard",q:[Pq("papel","Usted es",true,["Vendedor","Comprador"]),Pq("parte","Otra parte: nombre, DNI/NIE, domicilio",true),Pq("vehiculo","Marca, modelo, matrícula, bastidor (VIN)",true),Pq("km","Año y kilómetros",true),Pq("precio","Precio (€) y forma de pago (ITP a cargo del comprador)",true),Pq("itv","ITV vigente hasta",false),Pq("nota","Nota: vendedor — notifique la venta a la DGT; comprador — cambio de titularidad en 30 días",false)]},
    es_alegaciones_multa:{ic:"🚦",n:"Alegaciones a multa de tráfico (20 días)",cat:"es_auto",tier:"standard",q:[Pq("expediente","Nº de expediente (DGT/ayuntamiento)",true),Pq("notificada","Notificada el (plazo: 20 días naturales)",true),Pq("hecho","Hecho denunciado",true),Pq("alegaciones","Sus alegaciones (con sus palabras)",true),Pq("datos","Sus nombre, apellidos, DNI/NIE",true)]},
    es_parte_siniestro:{ic:"💥",n:"Declaración de siniestro al seguro",cat:"es_auto",tier:"einfach",q:[Pq("aseguradora","Aseguradora: nombre y dirección",true),Pq("poliza","Nº de póliza",true),Pq("siniestro","Siniestro: fecha, lugar, circunstancias",true),Pq("danos","Daños del vehículo",true),Pq("contrario","Contrario (nombre, matrícula, seguro)",false),Pq("datos","Sus nombre, apellidos y domicilio",true)]},
    es_reclamacion_taller:{ic:"🔧",n:"Reclamación a taller (reparación defectuosa)",cat:"es_auto",tier:"standard",q:[Pq("taller","Taller: nombre y dirección",true),Pq("factura","Factura/orden de reparación nº, fecha",true),Pq("problema","Problema (reparación defectuosa, cobro indebido, piezas)",true),Pq("pretension","Su pretensión (garantía de la reparación: 3 meses o 2.000 km)",true),Pq("datos","Sus nombre, apellidos y domicilio",true)]},
    es_baja_temporal_dgt:{ic:"🅿️",n:"Baja temporal del vehículo (DGT)",cat:"es_auto",tier:"einfach",q:[Pq("vehiculo","Matrícula, marca y modelo",true),Pq("motivo","Motivo (no uso, venta pendiente…)",true),Pq("datos","Titular: nombre, apellidos, DNI/NIE",true)]},
    es_reclamacion_previa_inss:{ic:"🏥",n:"Reclamación previa al INSS (30 días)",cat:"es_salud",tier:"komplex",q:[Pq("resolucion","Resolución del INSS de (referencia)",true),Pq("notificada","Notificada el (plazo: 30 días hábiles)",true),Pq("materia","Materia",true,["Incapacidad temporal","Incapacidad permanente","Jubilación","Prestación familiar","Otra"]),Pq("motivos","Por qué es errónea la resolución",true),Pq("datos","Sus nombre, apellidos, DNI/NIE, nº afiliación",true)]},
    es_dependencia:{ic:"🧓",n:"Solicitud de dependencia (Ley 39/2006)",cat:"es_salud",tier:"standard",q:[Pq("persona","Persona dependiente: nombre, fecha de nacimiento, DNI/NIE",true),Pq("situacion","Situación (ayuda que necesita, con qué frecuencia)",true),Pq("comunidad","Comunidad autónoma",true),Pq("datos","Solicitante: nombre y domicilio",true)]},
    es_historia_clinica:{ic:"📂",n:"Acceso a la historia clínica (Ley 41/2002)",cat:"es_salud",tier:"einfach",q:[Pq("centro","Centro sanitario: nombre y dirección",true),Pq("alcance","Qué documentación solicita (períodos, servicios)",true),Pq("forma","Forma de entrega",false,["Copia electrónica","Copia en papel"]),Pq("datos","Sus nombre, apellidos, DNI/NIE, nº de historia (si lo conoce)",true)]},
    es_reintegro_gastos:{ic:"🩺",n:"Reintegro de gastos sanitarios",cat:"es_salud",tier:"standard",q:[Pq("servicio","Servicio de salud / aseguradora",true),Pq("asistencia","Asistencia: centro, fecha, tipo (urgencia vital)",true),Pq("importe","Importe pagado (€, factura adjunta)",true),Pq("iban","Su IBAN",true),Pq("datos","Sus nombre, apellidos, DNI/NIE, tarjeta sanitaria",true)]},
    es_cita_sepe_queja:{ic:"📅",n:"Escrito por citas/retrasos (SEPE u organismo)",cat:"es_salud",tier:"einfach",q:[Pq("organismo","Organismo (SEPE, INSS, Extranjería…)",true),Pq("problema","Problema (imposibilidad de cita, retraso del expediente)",true),Pq("expediente","Nº de expediente (si existe)",false),Pq("solicitud","Qué solicita",true),Pq("datos","Sus nombre, apellidos, DNI/NIE",true)]},
    es_gastos_hipoteca:{ic:"🏦",n:"Reclamación de gastos de formalización de hipoteca",cat:"es_banca",tier:"standard",q:[Pq("banco","Banco: nombre y dirección",true),Pq("hipoteca","Hipoteca (fecha de escritura, notaría)",true),Pq("gastos","Gastos pagados (notaría, registro, gestoría, tasación)",true),Pq("importe","Importe reclamado (€, facturas adjuntas)",true),Pq("datos","Sus nombre, apellidos, DNI/NIE",true)]},
    es_amortizacion_anticipada:{ic:"💳",n:"Amortización anticipada de préstamo",cat:"es_banca",tier:"standard",q:[Pq("banco","Entidad: nombre y dirección",true),Pq("contrato","Contrato nº de fecha",true),Pq("tipo","Amortización",true,["Total","Parcial (importe)"]),Pq("fecha","Fecha deseada",true),Pq("datos","Sus nombre, apellidos, DNI/NIE",true)]},
    es_operacion_no_autorizada:{ic:"🧾",n:"Devolución de operación no autorizada (RDL 19/2018)",cat:"es_banca",tier:"standard",q:[Pq("banco","Banco/emisor: nombre y dirección",true),Pq("operacion","Operación (fecha, importe, beneficiario)",true),Pq("motivo","Motivo",true,["Nunca autorizada","Importe erróneo","Cargo duplicado","Fraude/phishing"]),Pq("bloqueo","¿Tarjeta ya bloqueada?",false,["Sí","No"]),Pq("datos","Sus nombre, apellidos, DNI/NIE",true)]},
    es_transferencia_erronea:{ic:"↩️",n:"Devolución de transferencia errónea",cat:"es_banca",tier:"einfach",q:[Pq("banco","Su banco: nombre",true),Pq("transferencia","Transferencia (fecha, importe, IBAN destino)",true),Pq("error","Error cometido (IBAN equivocado, importe, duplicada)",true),Pq("datos","Sus nombre, apellidos, DNI/NIE",true)]},
    es_revolving:{ic:"📈",n:"Revisión de tarjeta revolving (intereses)",cat:"es_banca",tier:"komplex",q:[Pq("entidad","Entidad: nombre y dirección",true),Pq("tarjeta","Tarjeta/contrato (año de contratación)",true),Pq("tae","TAE aplicada (si la conoce)",false),Pq("solicitud","Solicitud",true,["Recálculo y devolución de intereses","Copia del contrato y extractos","Ambas"]),Pq("datos","Sus nombre, apellidos, DNI/NIE",true)]},
    es_papeleta_smac:{ic:"⚖️",n:"Papeleta de conciliación (SMAC)",cat:"es_trabajo",tier:"komplex",q:[Pq("empresa","Empresa: nombre y domicilio",true),Pq("objeto","Objeto",true,["Despido (plazo: 20 días hábiles)","Reclamación de cantidad","Sanción","Modificación sustancial"]),Pq("hechos","Hechos (con sus palabras, fechas)",true),Pq("pretension","Qué solicita",true),Pq("datos","Sus nombre, apellidos, DNI/NIE, domicilio",true)]},
    es_reclamacion_cantidad:{ic:"💶",n:"Reclamación de cantidad al empresario",cat:"es_trabajo",tier:"standard",q:[Pq("empresa","Empresa: nombre y domicilio",true),Pq("conceptos","Conceptos adeudados (salarios, extras, vacaciones — meses e importes)",true),Pq("plazo","Plazo de pago (p. ej. 10 días; interés del 10% art. 29 ET)",true),Pq("datos","Sus nombre, apellidos y domicilio",true)]},
    es_impugnacion_sancion:{ic:"🚫",n:"Alegaciones contra sanción disciplinaria",cat:"es_trabajo",tier:"standard",q:[Pq("empresa","Empresa: nombre y domicilio",true),Pq("sancion","Sanción comunicada el (motivo alegado)",true),Pq("defensa","Su versión de los hechos",true),Pq("datos","Sus nombre, apellidos",true)]},
    es_certificado_empresa:{ic:"📜",n:"Solicitud de certificado de empresa (SEPE)",cat:"es_trabajo",tier:"einfach",q:[Pq("empresa","Empresa: nombre y domicilio",true),Pq("fin","Fin de la relación laboral (fecha)",true),Pq("urgencia","Para tramitar el paro — envío al SEPE en 10 días",false),Pq("datos","Sus nombre, apellidos, DNI/NIE",true)]},
    es_junta_arbitral:{ic:"🤝",n:"Solicitud de arbitraje de consumo",cat:"es_consumo",tier:"standard",q:[Pq("empresa","Empresa reclamada: nombre y dirección",true),Pq("hechos","Hechos y reclamación previa realizada",true),Pq("pretension","Qué solicita (importe/actuación)",true),Pq("datos","Sus nombre, apellidos, DNI/NIE, domicilio",true)]},
    es_corte_suministro:{ic:"🔌",n:"Reclamación por corte de suministro indebido",cat:"es_consumo",tier:"standard",q:[Pq("compania","Compañía: nombre y dirección",true),Pq("contrato","Nº de contrato / CUPS",true),Pq("corte","Corte (fecha, sin aviso previo/pago al día)",true),Pq("pretension","Reposición inmediata e indemnización",true),Pq("datos","Sus nombre, apellidos y dirección de suministro",true)]},
    es_viaje_combinado:{ic:"🧳",n:"Reclamación de viaje combinado / agencia",cat:"es_consumo",tier:"standard",q:[Pq("agencia","Agencia/organizador: nombre y dirección",true),Pq("viaje","Viaje (destino, fechas, nº de reserva)",true),Pq("incumplimiento","Qué falló (hotel, vuelos, servicios)",true),Pq("pretension","Su pretensión (reducción del precio, indemnización)",true),Pq("datos","Sus nombre, apellidos y domicilio",true)]},
    es_recurso_reposicion:{ic:"⚖️",n:"Recurso de reposición (LPAC, 1 mes)",cat:"es_admin",tier:"komplex",q:[Pq("organo","Órgano que dictó el acto: nombre y dirección",true),Pq("acto","Acto/resolución de (fecha, expediente)",true),Pq("notificado","Notificado el (plazo: 1 mes)",true),Pq("motivos","Por qué es contrario a derecho (con sus palabras)",true),Pq("datos","Sus nombre, apellidos, DNI/NIE, domicilio",true)]},
    es_transparencia:{ic:"🗂️",n:"Solicitud de información pública (Ley 19/2013)",cat:"es_admin",tier:"einfach",q:[Pq("organismo","Organismo: nombre",true),Pq("informacion","Información solicitada (lo más concreta posible)",true),Pq("forma","Forma preferida",false,["Electrónica","Papel"]),Pq("datos","Sus nombre, apellidos, DNI/NIE",true)]},
    es_responsabilidad_patrimonial:{ic:"🏛️",n:"Responsabilidad patrimonial de la Administración (1 año)",cat:"es_admin",tier:"komplex",q:[Pq("administracion","Administración responsable",true),Pq("hecho","Hecho lesivo (fecha, lugar, circunstancias — plazo: 1 año)",true),Pq("dano","Daño sufrido (acreditado, importe)",true),Pq("relacion","Relación causa-efecto (con sus palabras)",true),Pq("datos","Sus nombre, apellidos, DNI/NIE, domicilio",true)]},
    es_reparaciones_lau:{ic:"🔧",n:"Solicitud de reparaciones al arrendador (art. 21 LAU)",cat:"es_vivienda",tier:"einfach",q:[Pq("arrendador","Arrendador: nombre y dirección",true),Pq("vivienda","Dirección de la vivienda",true),Pq("reparacion","Reparación necesaria (habitabilidad)",true),Pq("plazo","Plazo (p. ej. 15 días; urgentes: puede repararlas y repercutir)",true),Pq("datos","Sus nombre y apellidos",true)]},
    es_certificado_corriente:{ic:"🧾",n:"Certificado de estar al corriente de pago (alquiler)",cat:"es_vivienda",tier:"einfach",q:[Pq("arrendador","Arrendador: nombre y dirección",true),Pq("vivienda","Dirección de la vivienda",true),Pq("periodo","Período a certificar",true),Pq("datos","Sus nombre y apellidos",true)]},
JS;
$o2 = "var CCODE=\"ES\", DOCWORD=\"documentos\";\n\n  var D={";
$n2 = "var CCODE=\"ES\", DOCWORD=\"documentos\";\n\n  var D={\n".$esDocs;
$c=0; $src=str_replace($o2,$n2,$src,$c);
echo ($c===1?"  ✓ [2] ES +43 belge\n":"  ✗ [2] ES D anchor ($c)\n"); if($c!==1)$fail[]=2;

if ($fail) { echo "\nHATA: adimlar eslesmedi: ".implode(', ',$fail)." — index.php DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(),'w5').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-widecates-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
$nES = preg_match_all('/\bes_[a-z0-9_]+:\{ic:/', $src, $m);
echo "\n✓ CH_WIDECAT_ES uygulandi. Yeni toplam: ES=$nES belge (+5 kategori, Extranjería dahil).\n";
