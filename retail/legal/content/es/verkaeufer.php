<?php
/**
 * Condiciones para vendedores — español (traducción de cortesía; el texto alemán es el vinculante).
 * Los tipos de comisión proceden de la configuración a través de legal/verkaeufer.php, de modo que
 * el texto del contrato y el tipo facturado nunca puedan divergir.
 */
?>
<nav class="doc__toc">
  <a href="#v1">1. Objeto</a>
  <a href="#v2">2. Cuenta de vendedor y registro</a>
  <a href="#v3">3. Profesional o particular</a>
  <a href="#v4">4. Ofertas y revisión</a>
  <a href="#v5">5. Mercancía prohibida</a>
  <a href="#v6">6. Comisión</a>
  <a href="#v7">7. Pago y liquidación</a>
  <a href="#v8">8. Envío y devoluciones</a>
  <a href="#v9">9. Premium Outlet</a>
  <a href="#v10">10. Obligaciones derivadas del Digital Services Act</a>
  <a href="#v11">11. Derechos sobre los contenidos</a>
  <a href="#v12">12. Bloqueo y resolución</a>
  <a href="#v13">13. Responsabilidad e indemnidad</a>
  <a href="#v14">14. Disposiciones finales</a>
</nav>

<h2 id="v1">1. Objeto</h2>
<p>
  1.1 Las presentes condiciones regulan la relación entre <?= h($co) ?>, como operador del mercado
  en línea <?= h($brand) ?>, y las personas que ofrecen mercancía a través de la plataforma
  («Vendedores»).
</p>
<p>
  1.2 El Operador pone a disposición el espacio de venta, el procesamiento de pagos a través de Stripe
  y la gestión de pedidos. El contrato de compraventa sobre la mercancía ofrecida se celebra
  exclusivamente entre el Vendedor y el comprador; el Operador no es parte del contrato.
</p>
<p>
  1.3 El Operador está facultado para recibir los pagos de los compradores en nombre del Vendedor con
  efecto liberatorio, así como para recibir y remitir al Vendedor las declaraciones de los compradores
  relativas al contrato de compraventa (en particular el desistimiento y las comunicaciones de
  defectos).
</p>

<h2 id="v2">2. Cuenta de vendedor y registro</h2>
<p>
  2.1 El registro se realiza en línea. Los datos deben ser veraces, completos y actuales. Las
  modificaciones — en particular de la razón social, la dirección, el número de identificación fiscal
  o el tipo de vendedor — deben actualizarse sin demora.
</p>
<p>
  2.2 Los datos de acceso deben mantenerse en secreto. El Vendedor responde de las operaciones
  realizadas a través de su cuenta en la medida en que le sea imputable una culpa.
</p>
<p>
  2.3 Antes de la primera publicación de una oferta debe haberse completado la verificación en Stripe
  (Stripe Connect). Sin verificación completada no puede efectuarse ninguna liquidación; en tal caso,
  las ofertas permanecen fuera de línea.
</p>

<h2 id="v3">3. Profesional o particular</h2>
<p>
  3.1 En el registro debe indicarse si se vende como empresario («Comerciante») o como persona
  privada («Vendedor particular»). Esta indicación se muestra a los compradores en la página del
  producto y durante el proceso de pedido, y determina qué derechos de los consumidores son
  aplicables.
</p>
<p>
  3.2 Quien vende de forma planificada, reiterada y con ánimo de lucro actúa profesionalmente — con
  independencia de su propia valoración. La calificación correcta, así como todas las obligaciones
  fiscales, mercantiles y de derecho de la actividad comercial, son responsabilidad del Vendedor.
</p>
<p>
  3.3 El Operador está facultado, previo aviso, para reclasificar una cuenta como «Comerciante» o
  para bloquearla si la actividad de venta real tiene carácter profesional. Se tienen en cuenta en
  particular el número de ofertas, el volumen de ventas y la regularidad.
</p>
<p>
  3.4 Los Comerciantes están obligados a conceder a los consumidores el derecho legal de
  desistimiento, a emitir facturas en debida forma y a cumplir la garantía legal por defectos.
</p>

<h2 id="v4">4. Ofertas y revisión</h2>
<p>
  4.1 Las ofertas deben ser exactas, completas y actuales: marca, denominación del modelo, talla,
  estado, precio con IVA incluido y al menos una imagen propia y sin retocar de la mercancía
  realmente disponible.
</p>
<p>
  4.2 En la mercancía de segunda mano deben describirse las señales de uso. Las indicaciones
  omitidas o embellecidas corren a cargo del Vendedor.
</p>
<p>
  4.3 Toda oferta nueva o modificada se revisa antes de su publicación. La revisión comprende la
  plausibilidad, la calidad de las imágenes y el precio; el Operador puede exigir justificantes de
  procedencia (justificantes de compra). No existe derecho a la publicación.
</p>
<p>
  4.4 El Vendedor conserva los justificantes de compra al menos durante la vigencia de la oferta más
  dos años y los presenta, a petición, en un plazo de cinco días laborables.
</p>
<p>
  4.5 El Vendedor garantiza que la mercancía ofrecida está disponible. La falta de entrega reiterada
  tras la venta faculta al Operador para bloquear la cuenta.
</p>

<h2 id="v5">5. Mercancía prohibida</h2>
<p>No pueden ofrecerse, en particular:</p>
<ul>
  <li>falsificaciones, réplicas, «dupes» y mercancía con marcas identificativas eliminadas o
      alteradas;</li>
  <li>mercancía sin procedencia acreditable o de origen ilícito;</li>
  <li>mercancía comercializada por primera vez fuera del EEE, salvo que el titular de la marca haya
      consentido su reventa en el EEE;</li>
  <li>muestras no autorizadas para la venta («not for resale») y mercancía para empleados sujeta a
      prohibición de reventa;</li>
  <li>mercancía que infrinja la normativa sobre seguridad de los productos, etiquetado o etiquetado
      textil;</li>
  <li>pieles y cueros exóticos sin los certificados exigidos (CITES).</li>
</ul>
<p>
  Las infracciones conllevan la retirada inmediata de la oferta y, por regla general, la resolución
  de la cuenta de vendedor.
</p>

<h2 id="v6">6. Comisión</h2>
<p>6.1 Por cada venta intermediada a través de la plataforma se devenga una comisión:</p>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Tipo de vendedor</th><th>Comisión</th><th>Premium Outlet (Vault)</th></tr></thead>
  <tbody>
    <tr><td>Comerciante</td><td><?= h($feeB) ?> % + <?= h($fixed) ?> por artículo vendido</td><td><?= h($feeV) ?> %</td></tr>
    <tr><td>Vendedor particular</td><td><?= h($feeP) ?> % + <?= h($fixed) ?> por artículo vendido</td><td><?= h($feeV) ?> %</td></tr>
  </tbody>
</table></div>
<p>
  6.2 La base de cálculo es el precio de venta bruto de los artículos del Vendedor. Los gastos de
  envío no forman parte de la base de cálculo y quedan en poder del Operador, que asume los gastos de
  envío frente al transportista.
</p>
<p>
  6.3 No se cobran tarifas de inserción, de publicación ni cuotas mensuales.
</p>
<p>
  6.4 La comisión se retiene automáticamente en el momento del pago del comprador. En caso de
  reversión completa (desistimiento, resolución, falta de entrega) se reembolsa la comisión, con
  excepción del importe fijo previsto en la cláusula 6.1 cuando la reversión haya sido causada por
  el Vendedor.
</p>
<p>
  6.5 Las modificaciones de la comisión se anuncian por correo electrónico con al menos 30 días de
  antelación. Si el Vendedor no se opone antes de su entrada en vigor, la nueva comisión se considera
  acordada; el derecho de resolución previsto en la cláusula 12 no se ve afectado.
</p>

<h2 id="v7">7. Pago y liquidación</h2>
<p>
  7.1 El procesamiento de pagos se realiza a través de Stripe. A tal efecto, el Vendedor celebra un
  acuerdo propio con Stripe (Stripe Connected Account Agreement) y acepta sus condiciones.
</p>
<p>
  7.2 Según la composición del pedido, el pago se abona directamente en la cuenta del Vendedor (pago
  con transferencia directa) o el importe se recauda primero en la cuenta de la plataforma y se
  transfiere a continuación al Vendedor mediante una transferencia separada. En ambos casos, el
  Vendedor recibe el precio de venta bruto de sus artículos una vez deducida la comisión.
</p>
<p>
  7.3 La periodicidad de las liquidaciones a la cuenta bancaria se rige por las normas de Stripe. El
  Operador no custodia fondos de clientes ni debe intereses.
</p>
<p>
  7.4 Si la verificación de Stripe aún no se ha completado, la parte del Vendedor permanece en la
  cuenta de la plataforma hasta que se complete la verificación. Si la verificación no se completa en
  un plazo de 180 días, el Operador puede cancelar los pedidos afectados y reembolsar a los
  compradores.
</p>
<p>
  7.5 El Operador puede retener o compensar liquidaciones en la medida en que existan créditos
  fundados frente al Vendedor — en particular por reembolsos a compradores, devoluciones de cargo
  (chargebacks) o infracciones de la cláusula 5. La retención se motiva y se limita al importe de
  los créditos.
</p>

<h2 id="v8">8. Envío y devoluciones</h2>
<p>
  8.1 El Vendedor realiza el envío en un plazo de dos días laborables desde la recepción del pago,
  asegurado y con seguimiento, y registra los datos del envío sin demora.
</p>
<p>
  8.2 El Vendedor acepta las devoluciones en la dirección indicada por él. Los Comerciantes
  reembolsan los desistimientos dentro del plazo; si no se efectúa el reembolso, el Operador está
  facultado para reembolsar al comprador y compensar el importe con las liquidaciones del Vendedor.
</p>
<p>
  8.3 En el caso de los Vendedores particulares no existe derecho legal de desistimiento. No
  obstante, si la mercancía se aparta sustancialmente de la descripción o no es original, el Vendedor
  está obligado a aceptar su devolución y a reembolsar.
</p>

<h2 id="v9">9. Premium Outlet</h2>
<p>
  9.1 El Vendedor puede poner mercancía a disposición del Vault. El precio de apertura, el precio
  mínimo y el plan de escalones se acuerdan antes de la apertura y no se modifican después.
</p>
<p>
  9.2 El Vendedor reconoce que la venta puede celebrarse a cualquier precio comprendido entre el
  precio de apertura y el precio mínimo, y que no es posible revocar la participación una vez abierto
  el lote, mientras el lote esté en curso.
</p>
<p>
  9.3 El precio mínimo nunca se rebasa a la baja. Si un lote no se vende hasta su término, se cierra
  y puede volver a ofrecerse por la vía ordinaria.
</p>

<h2 id="v10">10. Obligaciones derivadas del Digital Services Act</h2>
<p>
  10.1 Los Vendedores profesionales facilitan los datos exigidos por el art. 30 del DSA: nombre,
  dirección, número de teléfono, dirección de correo electrónico, número de inscripción en el
  registro mercantil o identificador equiparable y número de identificación a efectos del IVA, en su
  caso. El Operador comprueba estos datos con medios razonables y puede retirar la oferta de la
  publicación hasta su aclaración.
</p>
<p>
  10.2 El Vendedor garantiza que sus ofertas cumplen la normativa sobre seguridad de los productos y
  etiquetado y que dispone de las autorizaciones necesarias.
</p>
<p>
  10.3 Las notificaciones de contenidos ilícitos se tramitan conforme al art. 16 del DSA. Los
  Vendedores afectados son informados de las retiradas con indicación de los motivos y pueden
  oponerse (<a href="<?= h(vr_url('legal/streitbeilegung.php')) ?>"><?= te('legal_disputes') ?></a>).
</p>

<h2 id="v11">11. Derechos sobre los contenidos</h2>
<p>
  11.1 El Vendedor concede al Operador un derecho simple, sin limitación territorial y gratuito a
  utilizar, editar (recorte, ajuste de color, redimensionado) y reproducir los textos e imágenes
  publicados para la explotación y la promoción de la plataforma.
</p>
<p>
  11.2 El derecho de uso subsiste más allá del fin de la oferta en la medida en que sirva para
  documentar las ventas concluidas.
</p>
<p>
  11.3 El Vendedor garantiza que dispone de todos los derechos necesarios sobre los contenidos
  publicados y que no vulnera derechos de terceros.
</p>

<h2 id="v12">12. Bloqueo y resolución</h2>
<p>
  12.1 Ambas partes pueden resolver la relación de vendedor en cualquier momento, en forma de texto,
  con un preaviso de 14 días. Los pedidos en curso deben tramitarse íntegramente.
</p>
<p>
  12.2 El Operador puede retirar ofertas de inmediato y bloquear la cuenta en caso de infracción de
  la cláusula 5, de datos falsos sobre la condición de vendedor, de falta de entrega reiterada o de
  sospecha fundada de falsificación. El bloqueo se motiva.
</p>
<p>
  12.3 Tras la terminación, las liquidaciones pendientes se ordenan una vez vencidos los plazos de
  devolución y de devolución de cargo, a más tardar 90 días después del último pedido.
</p>

<h2 id="v13">13. Responsabilidad e indemnidad</h2>
<p>
  13.1 El Operador responde sin limitación en caso de dolo y negligencia grave, por daños a la vida,
  la integridad física y la salud, así como cuando haya asumido una garantía. En caso de
  incumplimiento por negligencia leve de obligaciones contractuales esenciales, la responsabilidad se
  limita al daño previsible y típico del contrato; en lo demás queda excluida.
</p>
<p>
  13.2 El Vendedor mantendrá indemne al Operador frente a las reclamaciones de terceros basadas en
  una infracción de las presentes condiciones — en particular en materia de marcas, derechos de autor
  o competencia, así como por infracciones de la normativa de protección de los consumidores. La
  indemnidad comprende los costes razonables de defensa jurídica.
</p>
<p>
  13.3 No se asume ninguna garantía de volumen de ventas ni de resultados. La visibilidad, la
  posición y la ordenación de las ofertas las determina el Operador.
</p>

<h2 id="v14">14. Disposiciones finales</h2>
<p>
  14.1 Se aplica el Derecho alemán. Si el Vendedor es empresario, el fuero competente es el del
  domicilio social del Operador, en la medida en que la ley lo permita.
</p>
<p>
  14.2 Las modificaciones de las presentes condiciones se comunican por correo electrónico con al
  menos 30 días de antelación. Versión vigente: <?= h(VR_TERMS_VERSION) ?>.
</p>
<p>
  14.3 Contacto para todos los asuntos relativos a vendedores:
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-mail]</em>' ?>.
</p>

<div class="doc__box">
  <strong>Nota sobre esta traducción</strong>
  <p style="margin-top:8px">
    Este texto en español es una traducción de cortesía de las Condiciones para vendedores alemanas,
    las únicas jurídicamente vinculantes y que usted acepta en el momento del registro. En caso de
    discrepancia prevalece la versión alemana. No constituye asesoramiento jurídico.
  </p>
</div>
