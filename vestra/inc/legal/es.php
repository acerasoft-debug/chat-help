<?php
/* TALEP PENCERESI ve SATICIYA ODEME SURESI sabitleri escrow.php'de; bu dosya
   vestra_legal() icinden require ediliyor ve kardes bir dosyanin require'ina
   yaslanmak KURAL 15'in fatal'i. Rakam METNE GOMULMUYOR (KURAL 6). */
require_once __DIR__.'/../escrow.php';
$co = 'Acerasoft LLC';
$claimDays = (int)VESTRA_CLAIM_DAYS;
$setDays   = (int)VESTRA_SELLER_SETTLEMENT_DAYS;
return [
  'imprint'   => ['title'=>"Aviso legal / Información legal", 'html'=>"
    <p>Información conforme a las normas aplicables de comercio electrónico e información al consumidor.</p>
    <h3>Operador</h3>
    <ul><li><b>Empresa:</b> Acerasoft LLC</li>
    <li><b>Forma jurídica:</b> Sociedad de responsabilidad limitada de EE. UU. (State of Delaware)</li>
    <li><b>Domicilio registrado:</b> 8 The Green, Suite B, Dover, Delaware 19901, USA</li>
    <li><b>Representada por:</b> Management</li>
    <li><b>Contacto:</b> <a href='mailto:legal@vestrasales.com'>legal@vestrasales.com</a> · <a href='mailto:support@vestrasales.com'>support@vestrasales.com</a></li></ul>
    <h3>Función</h3>
    <p>VESTRA opera un mercado mayorista B2B en línea y actúa <b>únicamente como intermediario y plataforma técnica</b>.
    No es parte de los contratos de compraventa celebrados entre vendedores y compradores y no adquiere la titularidad de las mercancías.
    <b>Excepción:</b> en los pedidos que Acerasoft LLC factura en su propio nombre, contrata con el comprador en calidad de vendedor; cuál de los dos casos
    concurre resulta de la factura de cada pedido (Condiciones del servicio, sección 3c).</p>
    <h3>Resolución de litigios en línea</h3>
    <p>La plataforma de resolución de litigios en línea de la EU está disponible en ec.europa.eu/consumers/odr. VESTRA presta servicio a empresas (B2B).</p>
"],
  'terms'     => ['title'=>"Condiciones del servicio", 'html'=>"
    <p><b>Operador:</b> Acerasoft LLC, 8 The Green, Suite B, Dover, Delaware 19901, USA (&ldquo;VESTRA&rdquo;, &ldquo;nosotros&rdquo;). <b>Vigencia:</b> 26 de junio de 2026.
    Al crear una cuenta, unirse a la lista de espera, publicar, realizar pedidos o utilizar de otro modo VESTRA, usted confirma que es una
    empresa que actúa con carácter comercial y acepta estas Condiciones.</p>
    <h3>1. Qué es VESTRA</h3><p>VESTRA es un mercado mayorista B2B que conecta a vendedores y compradores empresariales verificados.
    VESTRA es <b>únicamente un intermediario y una plataforma técnica</b>. <b>No es parte</b> de ninguna venta; no posee,
    conserva, inspecciona, almacena, envía ni adquiere la titularidad de las mercancías, y no custodia fondos (lo hace un proveedor licenciado externo de depósito en garantía/pagos). Los contratos de compraventa se celebran <b>exclusivamente entre el comprador y el vendedor</b>.
    Esta sección se aplica sin perjuicio de la sección 3c: en los pedidos que Acerasoft LLC factura en su propio nombre, ella misma es el vendedor y el
    contrato se celebra con ella.</p>
    <h3>2. Solo usuarios empresariales (sin consumidores)</h3><p>VESTRA es estrictamente para empresas (B2B); no está dirigido a
    consumidores y los derechos de desistimiento del consumidor no son de aplicación. Debe completar la verificación (KYB/KYC) antes de operar y
    garantiza que toda la información que proporciona es exacta y se mantiene actualizada.</p>
    <h3>2a. Pedidos de dropshipping</h3><p>Los pedidos de <b>dropshipping</b> por unidad los realiza un socio comercial verificado <b>para su reventa al cliente propio de dicho socio</b>. El socio es el comprador y el vendedor frente a su cliente; facilita la dirección de entrega, el color y la talla en el pago. <b>No se celebra contrato de compraventa entre VESTRA y el cliente final del socio</b>, y esta sección no abre la plataforma a consumidores. El precio de dropshipping es el precio mayorista más un margen de gestión, más la tarifa de envío de la zona de destino indicada en el pago. No se lleva control de existencias por unidad: la disponibilidad se confirma con el vendedor tras el pedido y, de no poder atenderse, se reembolsa íntegramente. <b>Los aranceles, impuestos de importación y gastos de despacho aduanero en el país de destino no están incluidos en el precio ni en la tarifa de envío</b> y se abonan en la entrega. Son responsabilidad del socio comercial que realiza el pedido, quien puede liquidarlos directamente o hacer que los liquide su propio cliente. Las mercancías de origen preferencial de la UE pueden acogerse a arancel cero en Japón conforme al Acuerdo de Asociación Económica UE&ndash;Japón cuando la expedición va acompañada de una declaración de origen; esto no cubre el impuesto sobre el consumo ni las tasas de despacho del transportista.</p>
    <h3>3. Publicaciones, pedidos y entrega</h3><p>Los vendedores son <b>los únicos responsables</b> de sus publicaciones y de la
    legalidad, seguridad, conformidad, etiquetado, descripción, fijación de precios, autenticidad, entrega, garantías e impuestos de sus
    mercancías. Un pedido constituye un contrato vinculante entre el comprador y el vendedor; VESTRA no es responsable del cumplimiento de ninguna de las partes.
    En los pedidos que Acerasoft LLC factura en su propio nombre se aplica en su lugar la sección 3c.</p>
    <h3>3c. Pedidos facturados por VESTRA</h3><p>En algunos pedidos VESTRA emite la factura <b>en su propio nombre</b>. No es el caso general y no se
    deduce de la p&aacute;gina de la publicaci&oacute;n: <b>la factura de un pedido indica qui&eacute;n es el vendedor a efectos legales</b>, y es ese documento
    el que prevalece. Cuando una factura designa a Acerasoft LLC como vendedor, las secciones 1 y 3 (VESTRA &uacute;nicamente como intermediario, contrato entre
    comprador y vendedor) <b>no se aplican a ese pedido</b>; en su lugar se aplica lo siguiente:</p>
    <ul>
    <li><b>Contrato.</b> El contrato de compraventa de ese pedido se celebra entre el comprador y Acerasoft LLC, que contrata en su propio nombre y por cuenta
    propia &mdash; ya procedan las mercanc&iacute;as del stock propio de VESTRA o hayan sido adquiridas a un vendedor proveedor para su reventa al comprador.</li>
    <li><b>Factura e impuestos.</b> La factura lleva los datos de empresa, el domicilio social y los identificadores fiscales propios de Acerasoft LLC e indica
    el tratamiento de IVA aplicado a esa entrega; cuando una entrega transfronteriza entre empresas se acoge a la inversi&oacute;n del sujeto pasivo, la factura
    lo hace constar y no se repercute IVA. Si los importes se convirtieron a otra divisa, la factura indica el tipo de cambio, su fuente y la fecha de su
    publicaci&oacute;n.</li>
    <li><b>Pago.</b> El pago se realiza a la cuenta bancaria indicada en esa factura y <b>no</b> se mantiene en dep&oacute;sito en garant&iacute;a; las
    disposiciones sobre dep&oacute;sito en garant&iacute;a de la pol&iacute;tica &laquo;Pagos, dep&oacute;sito en garant&iacute;a y reembolsos&raquo; no se
    aplican a un pedido de este tipo. Las condiciones de pago y, en su caso, el plazo tras el cual un pedido impagado se cancela constan en la factura.</li>
    <li><b>Devoluciones, defectos y deber de examen.</b> La <a href='/faq?cat=returns'>pol&iacute;tica de devoluciones y reclamaciones</a> y el deber de examen y
    denuncia de defectos se aplican sin cambios, siendo Acerasoft LLC la contraparte del comprador para ese pedido. Los derechos del comprador no se ven
    reducidos por el hecho de que el vendedor sea VESTRA y no un vendedor del mercado.</li>
    <li><b>Vendedor proveedor.</b> Cuando las mercanc&iacute;as se hayan comprado para su reventa, las garant&iacute;as del vendedor proveedor conforme a la
    Pol&iacute;tica del vendedor &mdash; autenticidad, derecho a vender, conformidad, seguridad y exactitud de las declaraciones &mdash; se otorgan a
    Acerasoft LLC y, en la medida en que la ley lo permita, se trasladan al comprador.</li>
    <li><b>Facturaci&oacute;n por cuenta de un vendedor.</b> Previo acuerdo con un vendedor, VESTRA puede en su lugar emitir una factura <b>en nombre y por
    cuenta</b> de ese vendedor (autofacturaci&oacute;n). Dicha factura lleva la identidad y los identificadores fiscales del <b>vendedor</b>; el vendedor sigue
    siendo el vendedor a efectos legales y las secciones 1 y 3 se aplican sin cambios.</li>
    </ul>
    <h3>4. Pagos, depósito en garantía y comisiones</h3><p>Los pagos son procesados y mantenidos en depósito en garantía por un proveedor licenciado externo y
    se liberan según las condiciones acordadas (p. ej., confirmación del comprador / entrega verificada). VESTRA cobra una comisión de plataforma (una
    comisión del vendedor más una tarifa de protección al comprador) o cuotas de membresía; las tarifas del proveedor se aplican según se facturen. Las tarifas se muestran
    antes del pago y no son reembolsables, salvo cuando la ley lo exija.</p>
    <h3>5. Autenticidad y propiedad intelectual</h3><p>Solo pueden publicarse mercancías auténticas y lícitas que el vendedor tenga derecho a vender.
    Las mercancías falsificadas, réplicas y de mercado gris no verificadas están prohibidas; operamos un proceso de notificación y retirada
    (véase la Política de PI y antifalsificación).</p>
    <h3>6. Conducta prohibida</h3><p>Ninguna actividad ilegal, fraude, declaración falsa, infracción de PI, elusión de la
    verificación/depósito en garantía/comisiones, scraping o solicitación fuera de la plataforma para evadir comisiones o protecciones. Usted es responsable de toda la
    actividad realizada en su cuenta y de mantener seguras sus credenciales.</p>
    <h3>7. Exención de garantías</h3><p>La plataforma se proporciona <b>&ldquo;tal cual&rdquo; y &ldquo;según disponibilidad&rdquo;</b>,
    sin garantías de ningún tipo, expresas o implícitas, incluidas las de comerciabilidad, idoneidad para un fin determinado,
    no infracción, exactitud o funcionamiento ininterrumpido/sin errores. VESTRA <b>no garantiza ni asegura</b> ningún vendedor,
    comprador, publicación, mercancía, descripción, autenticidad, cantidad, calidad o entrega, el resultado de ninguna transacción ni ningún
    proveedor externo.</p>
    <h3>8. Limitación de responsabilidad</h3><p>En la máxima medida permitida por la legislación aplicable: (a) VESTRA <b>no es responsable</b>
    de las mercancías, publicaciones, actos u omisiones de compradores, vendedores o terceros, de la falta de entrega, defectos o autenticidad,
    ni de ninguna disputa entre usuarios; (b) VESTRA no es responsable de ningún <b>daño indirecto, incidental, especial, consecuente,
    ejemplar o punitivo</b>, ni de la pérdida de beneficios, ingresos, negocio, fondo de comercio o datos; y (c) la
    <b>responsabilidad total agregada</b> de VESTRA derivada de o relacionada con la plataforma o estas Condiciones no excederá el mayor de
    las tarifas de la plataforma efectivamente pagadas por usted a VESTRA en los <b>three (3) months</b> anteriores al hecho que dé lugar a la reclamación,
    o <b>EUR 100</b>. Nada excluye la responsabilidad que no pueda limitarse por ley (p. ej., fraude, negligencia grave o muerte/lesiones
    personales causadas por nuestra negligencia); sus derechos imperativos no se ven afectados.</p>
    <h3>9. Indemnización</h3><p>Usted acepta <b>indemnizar, defender y eximir de responsabilidad</b> a Acerasoft LLC, sus filiales, directivos,
    miembros y personal frente a cualquier reclamación, demanda, pérdida, responsabilidad, multa, sanción, daño y costa legal razonable
    derivada de o relacionada con su uso de la plataforma, sus mercancías, publicaciones o contenido, sus transacciones, su incumplimiento
    de estas Condiciones o de cualquier ley, o su infracción de cualquier derecho de terceros.</p>
    <h3>10. Suspensión y terminación</h3><p>Podemos suspender, restringir o cancelar el acceso en cualquier momento, con o sin
    aviso, por incumplimiento, sospecha de fraude, motivos legales/de riesgo, impago o infracción reiterada. Las disposiciones que por su
    naturaleza deban subsistir (incluidas las secciones 7–9 y 11–13) sobreviven a la terminación.</p>
    <h3>11. Licencia de contenido y fuerza mayor</h3><p>Usted otorga a VESTRA una licencia no exclusiva para alojar y mostrar sus
    publicaciones y contenido con el fin de operar la plataforma, y garantiza que posee los derechos para hacerlo. VESTRA no es
    responsable de ningún incumplimiento o retraso causado por hechos ajenos a su control razonable (fuerza mayor).</p>
    <h3>12. Modificaciones</h3><p>Podemos actualizar estas Condiciones; la versión vigente se publica aquí con su fecha de vigencia.
    El uso continuado tras los cambios constituye su aceptación.</p>
    <h3>13. Legislación aplicable y litigios</h3><p>Estas Condiciones se rigen por las leyes del <b>State of Delaware, USA</b>,
    sin atender a las normas de conflicto de leyes. Sujeto a la legislación imperativa, los tribunales situados en Delaware tendrán jurisdicción;
    las partes podrán acordar resolver los litigios B2B mediante arbitraje vinculante. Las <b>disposiciones imperativas</b> de la legislación local del usuario
    y la plataforma de resolución de litigios en línea de la EU (ec.europa.eu/consumers/odr) siguen estando disponibles cuando corresponda.</p>
    <h3>14. Disposiciones varias</h3><p>Si alguna disposición resulta inaplicable, el resto permanece en vigor (divisibilidad). Estas Condiciones
    constituyen el acuerdo completo sobre su objeto. Podemos ceder estas Condiciones en relación con una fusión, adquisición o
    venta de activos; usted no podrá cederlas sin nuestro consentimiento. Que no exijamos el cumplimiento de una disposición no constituye una renuncia.</p>
    <p class='muted'>La aceptación se registra en el momento del registro (fecha, versión, idioma). Contacto: <a href='mailto:legal@vestrasales.com'>legal@vestrasales.com</a></p>
"],
  'privacy'   => ['title'=>"Política de privacidad", 'html'=>"
    <p><b>Responsable del tratamiento:</b> Acerasoft LLC, 8 The Green, Suite B, Dover, Delaware 19901, USA. <b>Contacto:</b> privacy@vestrasales.com. <b>Vigencia:</b> 26 de junio de 2026.</p>
    <h3>1. Datos que recopilamos</h3><p>Datos de cuenta y verificación (nombre, datos de la empresa, NIF/número de IVA, identidad del titular real
    y documentos de domicilio), datos transaccionales (pedidos, publicaciones, comunicaciones) y datos técnicos/de registro.</p>
    <h3>2. Por qué (finalidades y bases jurídicas)</h3><ul>
    <li>Prestar y proteger el mercado — contrato.</li>
    <li>Verificación de identidad, prevención del fraude, obligaciones de AML/PI — obligación legal / interés legítimo.</li>
    <li>Procesar pagos/depósito en garantía — contrato (compartido con el proveedor licenciado).</li></ul>
    <h3>3. Comunicación de datos</h3><p>Con el proveedor licenciado de pagos/depósito en garantía, proveedores de KYC, la contraparte de una transacción,
    proveedores de servicios y autoridades cuando lo exija la ley. No vendemos datos personales. <b>Los datos de identidad de los vendedores no se divulgan a terceros sin orden judicial o solicitud de autoridad competente</b> (RGPD Art. 6).</p>
    <h3>4. Transferencias internacionales</h3><p>Cuando los datos salgan del EEA, se aplican garantías adecuadas (p. ej., SCCs).</p>
    <h3>5. Conservación</h3><p>Solo durante el tiempo necesario y para cumplir los requisitos legales de conservación.</p>
    <h3>6. Sus derechos</h3><p>Acceso, rectificación, supresión, limitación, portabilidad, oposición y reclamación ante una
    autoridad de control.</p>
"],
  'seller'    => ['title'=>"Acuerdo del vendedor", 'html'=>"
    <p>Entre Acerasoft LLC y el vendedor empresarial registrado. <b>Vigencia:</b> 26 de junio de 2026.</p>
    <h3>1. Verificación</h3><p>Proporcionar y mantener actualizados el registro mercantil, el NIF/número de IVA y la identidad del titular real.</p>
    <h3>2. Vendedor responsable</h3><p>El vendedor es el vendedor legal de sus mercancías y es el único responsable de la conformidad, seguridad,
    entrega, garantías e impuestos. VESTRA es únicamente un intermediario y no es parte de la venta.
    <b>Cuando Acerasoft LLC compra mercancías al vendedor para revenderlas</b>, Acerasoft LLC es el vendedor frente a ese comprador y factura en su propio
    nombre (Condiciones del servicio, sección 3c); las garantías del vendedor que figuran a continuación se otorgan entonces a Acerasoft LLC. Previo acuerdo
    con un vendedor, VESTRA también puede emitir facturas en nombre y por cuenta de ese vendedor; en tal caso el vendedor sigue siendo el vendedor a efectos
    legales.</p>
    <h3>3. Autenticidad y derecho a vender</h3><p>Para cada artículo, el vendedor garantiza que las mercancías son <b>auténticas</b> y que está
    <b>autorizado/facultado para venderlas</b> en el mercado de destino (incluido el agotamiento de la marca en el EEA cuando corresponda),
    y proporcionará prueba de autenticidad/procedencia a solicitud.</p>
    <h3>4. Indemnización y responsabilidad</h3><p>El vendedor indemniza y exime a Acerasoft LLC de cualquier reclamación, pérdida, multa
    o costa derivada de sus mercancías, publicaciones, incumplimiento de garantías o infracción de PI, y es responsable ante los compradores por sus mercancías;
    la responsabilidad de VESTRA está limitada según lo establecido en las Condiciones del servicio.</p>
    <h3>5. Notificación y retirada</h3><p>El vendedor cumplirá la Política de PI y antifalsificación, responderá a las notificaciones y
    aceptará la retirada de publicaciones en tanto se resuelve el asunto.</p>
    <h3>6. Pedidos, depósito en garantía y pagos</h3><p>Los fondos se mantienen en depósito en garantía y se liberan tras la confirmación del comprador / entrega
    verificada, menos la comisión de VESTRA.</p>
    <h3>7. Amonestaciones y suspensión</h3><p>La falsificación, la infracción de PI, las reclamaciones válidas reiteradas o el fraude conllevan retirada,
    amonestaciones y suspensión. La falsificación/fraude manifiestos pueden causar la suspensión inmediata.</p>
    <h3>8. Inspección de mercancías y notificación de defectos (HGB §377)</h3><p>Los compradores deben inspeccionar las mercancías recibidas inmediatamente tras la entrega. <b>Los defectos evidentes, faltantes o entregas incorrectas deben notificarse por escrito en un plazo de 48 horas</b> desde la recepción. Los defectos ocultos deben notificarse en cuanto se descubran. La falta de notificación oportuna implica la aceptación de la mercancía y la pérdida de derechos de garantía.</p>

    <h3>9. Pedidos facturados por {$co} en su propio nombre — precio de compra y liquidación</h3>
    <p>Esta sección se aplica <b>únicamente</b> a los pedidos que {$co} factura en su propio nombre (Términos del servicio, sección 3c). En esos pedidos {$co} <b>compra la mercancía al vendedor y la revende</b>: la contraparte del vendedor es {$co}, no el comprador; el §2 de este contrato se aplica en consecuencia.</p>
    <ul>
    <li><b>Precio de compra.</b> El precio y la cantidad confirmados para el pedido en el panel del vendedor, más los gastos de envío acordados, menos cualquier comisión de plataforma aplicable a ese pedido. La página del pedido indica el importe a pagar; no se practica ninguna otra deducción sin acuerdo escrito del vendedor.</li>
    <li><b>Facturación.</b> El vendedor factura ese importe a {$co} (inversión del sujeto pasivo o exportación, cuando proceda). Cada parte sigue siendo responsable de sus propios impuestos y declaraciones.</li>
    <li><b>Cuándo un pedido es &laquo;exitoso&raquo;.</b> Deben cumplirse todas las condiciones siguientes: (a) el pago del comprador se ha recibido íntegramente y está disponible; (b) la mercancía se ha entregado al comprador; (c) el plazo de reclamación del comprador — {$claimDays} días hábiles desde la entrega, véase la <a href=\"/faq?cat=returns\">política de devoluciones y reclamaciones</a> — ha vencido sin reclamación abierta; y (d) no hay ningún contracargo, anulación o reembolso pendiente.</li>
    <li><b>Liquidación.</b> {$co} paga el precio de compra en un plazo de <b>{$setDays} días hábiles</b> desde que el pedido pasa a ser exitoso, mediante transferencia a la cuenta bancaria registrada en el perfil verificado del vendedor y titularidad del propio vendedor. Mantener esos datos actualizados corresponde al vendedor; no se realizan pagos a terceros.</li>
    <li><b>Pedidos no exitosos.</b> Los pedidos cancelados, no pagados por el comprador y reembolsados al comprador no generan liquidación. Cuando una reclamación se estima en parte, la liquidación se reduce en el importe abonado al comprador. {$co} puede compensar los importes ya pagados, y lo debido conforme al §4, con liquidaciones posteriores.</li>
    <li><b>Propiedad y riesgo.</b> La propiedad de la mercancía pasa a {$co} en el momento de su entrega al transportista para el comprador, y de ahí al comprador según lo indicado en la factura de {$co}. El riesgo sigue las condiciones de entrega de dicha factura.</li>
    <li><b>Las garantías del vendedor no cambian.</b> Las garantías y la indemnidad de los §3 y §4 se otorgan a {$co} para estos pedidos y no se ven afectadas. Un defecto que el comprador acredite frente a {$co} puede trasladarse al vendedor en los mismos términos.</li>
    </ul>"],
  'ip'        => ['title'=>"PI y antifalsificación / Notificación y retirada", 'html'=>"
    <h3>Tolerancia cero</h3><p>Las mercancías falsificadas, réplicas, de marca no autorizada y de mercado gris no verificadas, así como cualquier
    publicación que infrinja la PI, están prohibidas.</p>
    <h3>Denunciar una infracción</h3><p>Envíe una notificación a <a href='mailto:ip@vestrasales.com'>ip@vestrasales.com</a> con:
    el derecho invocado (p. ej., número de marca) y la prueba de titularidad; la(s) URL(s) exacta(s) de la publicación; el motivo por el que
    infringe; y una declaración de buena fe con datos de contacto. Una sola notificación puede enumerar varias URL.</p>
    <h3>Nuestro proceso</h3><ol><li>Acusar recibo.</li><li>Evaluar; retirar/desactivar con prontitud los casos fundamentados o manifiestos.</li>
    <li>Notificar al vendedor con el motivo.</li><li>Permitir una contranotificación con pruebas (autenticidad/autorización/prueba de EEA).</li>
    <li>Restablecer solo con prueba suficiente; en caso de duda, la publicación permanece retirada.</li></ol>
    <h3>Infractores reincidentes y permanencia retirada</h3><p>Se aplica un sistema de amonestaciones; las reclamaciones válidas reiteradas conllevan suspensión.
    Para los artículos infractores identificados, adoptamos medidas razonables para evitar su nueva publicación (permanencia retirada).</p>
    <h3>Titulares de derechos de confianza</h3><p>Los titulares de marcas pueden solicitar un canal prioritario; mantenemos un proceso de contranotificación
    justo para los vendedores (conforme a las obligaciones de la DSA de la EU). Las notificaciones abusivas pueden ser restringidas.</p>
    <h3>Protección de datos de los vendedores</h3><p>Los datos de identidad de los vendedores registrados no se divulgan a titulares de derechos ni a terceros <b>sin orden judicial o solicitud de autoridad competente</b>. Los titulares de derechos que deseen obtener datos de identidad de vendedores deben acudir a la vía judicial (RGPD Art. 6).</p>
"],
  'aml'       => ['title'=>"Política de AML / KYC", 'html'=>"
    <h3>Finalidad</h3><p>Prevenir el blanqueo de capitales, la evasión de sanciones, el fraude y la financiación del terrorismo, y verificar a los usuarios empresariales.</p>
    <h3>Verificación (KYB/KYC)</h3><p>Antes de operar verificamos: el registro mercantil; el identificador fiscal/de IVA;
    la identidad y el domicilio de los titulares reales últimos (&ge;25%); el domicilio social.</p>
    <h3>Cribado de sanciones</h3><p>Los usuarios y los titulares reales se cotejan con las listas aplicables (OFAC, EU, UN).
    No incorporamos usuarios en jurisdicciones prohibidas/sancionadas.</p>
    <h3>Fondos</h3><p>El cobro, el depósito en garantía y la liquidación los realiza un proveedor licenciado de pagos/depósito en garantía; VESTRA no
    conserva ni transmite fondos de los usuarios. <b>Excepción:</b> en los pedidos que {$co} factura en su propio nombre (Términos del servicio, sección 3c) el comprador paga en la cuenta propia de {$co}, y {$co} paga al vendedor suministrador por un pedido exitoso. Esos pagos se realizan únicamente a una cuenta bancaria a nombre del vendedor verificado; {$co} no paga a terceros, no realiza pagos hacia o desde jurisdicciones sancionadas y solo reembolsa a la cuenta de origen.</p>
    <h3>Supervisión y registros</h3><p>Supervisamos patrones sospechosos y conservamos los registros de verificación y transacciones
    durante el periodo legalmente exigido.</p>
"],
  'payments'  => ['title'=>"Pagos, depósito en garantía y reembolsos", 'html'=>"
    <p><b>Estado actual:</b> los pagos se realizan temporalmente <b>por factura</b> — el comprador recibe una factura proforma y paga por
    transferencia bancaria; la mercancía se envía tras el pago. El pago con depósito en garantía/tarjeta descrito a continuación queda suspendido hasta nuevo aviso.</p>
    <h3>Cómo funciona el pago</h3><p>Los compradores pagan a través del proveedor licenciado de depósito en garantía (transferencia bancaria SEPA para B2B en la EU; tarjetas disponibles).
    Los fondos se <b>mantienen en depósito en garantía</b> — VESTRA nunca conserva el dinero.
    <b>Los pedidos que Acerasoft LLC factura en su propio nombre son una excepción</b> (Condiciones del servicio, sección 3c): se pagan por transferencia a la
    cuenta bancaria indicada en la factura, no en depósito en garantía, y las reglas de depósito de esta página &mdash; liberación, liberación automática
    y reembolso desde el depósito &mdash; no les son aplicables. La <a href='/faq?cat=returns'>política de devoluciones y reclamaciones</a> se les aplica sin
    cambios.</p>
    <h3>Liberación del depósito en garantía</h3><p>Los fondos se liberan tras la confirmación del comprador, la entrega verificada o el vencimiento de un plazo de liberación automática
    acordado si no se plantea ninguna disputa. El proveedor desembolsa el pago al vendedor + la comisión de VESTRA.</p>
    <h3>Pedidos facturados por VESTRA en su propio nombre — cómo se paga al vendedor</h3>
    <p>En estos pedidos (Términos del servicio, sección 3c) {$co} es el vendedor a efectos legales: cobra el pago del comprador en su propia cuenta y <b>compra la mercancía al vendedor suministrador</b>. Ese vendedor cobra por un <b>pedido exitoso</b> — pago del comprador recibido y disponible, mercancía entregada, plazo de reclamación de {$claimDays} días hábiles vencido sin reclamación abierta y ningún contracargo o reembolso pendiente — en un plazo de <b>{$setDays} días hábiles</b> desde que se cumplen esas condiciones, en una cuenta de su titularidad. Los pedidos cancelados, impagados y reembolsados no se liquidan; una reclamación estimada en parte reduce la liquidación en el importe abonado al comprador. Las condiciones completas están en el <a href=\"/legal?doc=seller\">contrato de vendedor</a>, sección 9. <b>Para el comprador no cambia nada:</b> el plazo de reclamación, la <a href=\"/faq?cat=returns\">política de devoluciones y reclamaciones</a> y el derecho al reembolso siguen siendo los mismos; en estos pedidos el comprador los ejerce frente a {$co}.</p>
    <h3>Comisiones</h3><p>VESTRA cobra una comisión de plataforma por pedido — una comisión del vendedor más una pequeña tarifa de protección al comprador — o una cuota de membresía; las tarifas del proveedor según se facturen. Los importes exactos se muestran antes del pago.</p>
    <h3>Reembolsos y disputas</h3><p>Durante una disputa, los fondos permanecen en depósito en garantía. Si se resuelve a favor del comprador (falta de entrega,
    materialmente no conforme con lo descrito, falsificación probada), los fondos en depósito se reembolsan antes de la liberación.</p>
    <h3>Contracargos</h3><p>Los pagos SEPA no están sujetos a contracargos de tarjeta; los pagos con tarjeta siguen el proceso del proveedor.</p>
"],
  'prohibited'=> ['title'=>"Artículos prohibidos y restringidos", 'html'=>"
    <h3>Prohibidos</h3><ul>
    <li>Mercancías falsificadas, réplicas o imitaciones.</li>
    <li>Mercancías de marca no autorizada, o mercancías que el vendedor no esté facultado para vender en el mercado de destino (incl. importaciones
    de mercado gris / paralelas no verificadas sin prueba de agotamiento o autorización en el EEA).</li>
    <li>Mercancías robadas, de contrabando o de origen ilegal; cualquier artículo que infrinja la PI.</li>
    <li>Artículos ilegales, armas, drogas, productos peligrosos/retirados.</li></ul>
    <h3>Restringidos (condiciones/pruebas)</h3><ul>
    <li>Mercancías de marca — requieren verificación y, a solicitud, prueba de autenticidad/autorización/procedencia.</li>
    <li>Las categorías con normas de seguridad/etiquetado (p. ej., etiquetado de composición de fibras textiles, GPSR de la EU) deben cumplirlas.</li></ul>
    <h3>Aplicación</h3><p>Las infracciones conllevan retirada, amonestaciones y suspensión, y pueden notificarse a los titulares de derechos y a las
    autoridades. Falsificación/fraude manifiestos → suspensión inmediata.</p>
"],
];
