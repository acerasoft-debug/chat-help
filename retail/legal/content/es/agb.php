<?php
/**
 * Condiciones generales de venta — español (traducción de cortesía; el texto
 * alemán es el vinculante). Variables: legal/agb.php. La numeración sigue el
 * original párrafo a párrafo para que las remisiones («cláusula 10.3») coincidan.
 */
?>
<nav class="doc__toc">
  <a href="#s1">1. Ámbito de aplicación y partes contratantes</a>
  <a href="#s2">2. Papel de la plataforma</a>
  <a href="#s3">3. Celebración del contrato</a>
  <a href="#s4">4. Precios y gastos de envío</a>
  <a href="#s5">5. Pago</a>
  <a href="#s6">6. Entrega</a>
  <a href="#s7">7. Reserva de dominio</a>
  <a href="#s8">8. Desistimiento y devolución voluntaria</a>
  <a href="#s9">9. Garantía legal</a>
  <a href="#s10">10. Compras a vendedores particulares</a>
  <a href="#s11">11. Premium Outlet (Vault)</a>
  <a href="#s12">12. Autenticidad y procedencia</a>
  <a href="#s13">13. Responsabilidad</a>
  <a href="#s14">14. Vales de descuento</a>
  <a href="#s15">15. Protección de datos</a>
  <a href="#s16">16. Disposiciones finales</a>
</nav>

<h2 id="s1">1. Ámbito de aplicación y partes contratantes</h2>
<p>
  1.1 Las presentes condiciones generales de venta se aplican a todos los pedidos realizados a
  través de <?= h($brand) ?> (<a href="<?= h(vr_origin()) ?>"><?= h(vr_origin()) ?></a>).
  El operador de la plataforma es <?= h($co) ?> (en adelante, «el Operador», «nosotros»).
</p>
<p>
  1.2 En la plataforma se ofrecen tres tipos de ofertas, cada una identificada en la página del
  producto:
</p>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Indicación</th><th>Vendedor</th><th>Su parte contratante en la compraventa</th></tr></thead>
  <tbody>
    <tr><td>Stock <?= h($brand) ?></td><td>el propio Operador</td><td><?= h($co) ?></td></tr>
    <tr><td>Comerciante</td><td>vendedor tercero profesional</td><td>el comerciante correspondiente</td></tr>
    <tr><td>Vendedor particular</td><td>persona privada</td><td>la persona privada correspondiente</td></tr>
  </tbody>
</table></div>
<p>
  1.3 En las ofertas de vendedores terceros, el contrato de compraventa se celebra exclusivamente
  entre usted y el vendedor correspondiente. El Operador no es parte del contrato de compraventa.
  Para el uso de la plataforma en sí — procesamiento del pago, resumen de pedidos, intermediación —
  rigen estas condiciones entre usted y el Operador.
</p>
<p>
  1.4 Es consumidor toda persona física que celebra un negocio jurídico con fines que, en su mayor
  parte, no pueden atribuirse a su actividad comercial ni profesional independiente (§ 13 BGB,
  Código Civil alemán). Las condiciones divergentes del cliente no forman parte del contrato, salvo
  que aceptemos expresamente su validez por escrito.
</p>

<h2 id="s2">2. Papel de la plataforma</h2>
<p>
  2.1 El Operador pone a disposición la infraestructura técnica, comprueba la plausibilidad y los
  justificantes de procedencia de las ofertas de vendedores terceros antes de su publicación,
  procesa el pago a través del proveedor de servicios de pago Stripe y transfiere al vendedor su
  parte una vez deducida la comisión.
</p>
<p>
  2.2 El Operador está autorizado por los vendedores terceros para recibir los pagos del comprador
  con efecto liberatorio. Su obligación de pago frente al vendedor queda cumplida con el pago
  correcto a través de la plataforma.
</p>
<p>
  2.3 Las declaraciones relativas al contrato de compraventa — en particular desistimiento,
  comunicación de defectos y resolución — pueden dirigirse válidamente a
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '[correo electrónico]' ?>.
  Las remitimos sin demora al vendedor afectado y ayudamos en la tramitación.
</p>

<h2 id="s3">3. Celebración del contrato</h2>
<p>
  3.1 La presentación de los productos en la plataforma no constituye una oferta jurídicamente
  vinculante, sino una invitación a realizar un pedido.
</p>
<p>
  3.2 Al pulsar el botón de pedido («<?= te('checkout_go') ?>» y el posterior pago en Stripe),
  usted emite una oferta vinculante de compra de los artículos de su bolsa. Antes puede revisar y
  corregir sus datos en la página de pago.
</p>
<p>
  3.3 Confirmamos la recepción de su pedido de inmediato por correo electrónico. Esta confirmación
  de recepción no constituye todavía la aceptación. El contrato de compraventa se celebra cuando
  nosotros o el vendedor declaramos la aceptación o enviamos la mercancía — a más tardar con la
  confirmación del pedido, si esta declara expresamente la aceptación.
</p>
<p>
  3.4 Si el contrato no llega a celebrarse, por ejemplo porque el artículo ya no está disponible
  tras el pedido, le informamos sin demora y reembolsamos íntegramente los pagos ya realizados.
</p>
<p>
  3.5 El texto del contrato se almacena y se le remite con la confirmación del pedido en forma de
  texto (correo electrónico), junto con estas condiciones y la información sobre el desistimiento.
</p>

<h2 id="s4">4. Precios y gastos de envío</h2>
<p>
  4.1 Todos los precios indicados son precios finales en euros e incluyen el IVA legal, actualmente
  del <?= h($vat) ?> %, siempre que el vendedor correspondiente esté sujeto a IVA. En el caso de
  vendedores particulares no se desglosa IVA (§ 19 UStG, Ley alemana del IVA, o venta por un no
  empresario).
</p>
<p>
  4.2 A los precios de los artículos se añaden los gastos de envío. Se muestran por separado y con
  el importe exacto en la página de pago antes de realizar el pedido. Detalles en
  <a href="<?= h(vr_url('legal/versand.php')) ?>"><?= te('legal_shipping') ?></a>.
</p>
<p>
  4.3 Si su pedido contiene artículos de varios vendedores, los gastos de envío se cobran una sola
  vez; los envíos pueden llegarle por separado.
</p>
<p>
  4.4 En las entregas a países fuera de la UE pueden aplicarse adicionalmente aranceles, IVA a la
  importación y tasas de gestión, a cargo del destinatario.
</p>

<h2 id="s5">5. Pago</h2>
<p>
  5.1 El pago se realiza a través del proveedor de servicios de pago Stripe (Stripe Payments Europe,
  Limited, Dublín, Irlanda). Los métodos de pago disponibles se muestran durante el pedido; puede
  consultar un resumen en <a href="<?= h(vr_url('legal/zahlung.php')) ?>"><?= te('legal_payment') ?></a>.
</p>
<p>
  5.2 El precio de compra es exigible con la celebración del contrato. En los métodos de pago con
  liquidación diferida (p. ej. adeudo SEPA, Klarna) realizamos el envío tras la autorización del
  pago por el proveedor de servicios de pago.
</p>
<p>
  5.3 Los datos de pago, en particular los de la tarjeta, son tratados exclusivamente por el
  proveedor de servicios de pago. El Operador no recibe ni almacena datos completos de tarjeta.
</p>
<p>
  5.4 En caso de devoluciones de cargo imputables a usted, tenemos derecho a facturarle los costes
  que se nos originen, si ha provocado la devolución de forma culpable.
</p>

<h2 id="s6">6. Entrega</h2>
<p>
  6.1 La entrega se realiza en la dirección de entrega indicada por usted. Los plazos y zonas de
  entrega figuran en <a href="<?= h(vr_url('legal/versand.php')) ?>"><?= te('legal_shipping') ?></a>
  y comienzan con la autorización del pago.
</p>
<p>
  6.2 Los artículos de vendedores terceros los envía el vendedor correspondiente. Recibirá un
  seguimiento propio por cada envío.
</p>
<p>
  6.3 Si, excepcionalmente, un artículo no puede entregarse pese a mostrarse como disponible en la
  plataforma, le informamos sin demora y reembolsamos íntegramente el importe pagado. No existe
  derecho a la entrega posterior de un artículo comparable, ya que muchas piezas son únicas.
</p>
<p>
  6.4 Para los consumidores, el riesgo de pérdida y deterioro fortuitos solo se transmite con la
  entrega de la mercancía, aunque el envío lo realice un transportista (§ 475 apdo. 2 BGB).
</p>

<h2 id="s7">7. Reserva de dominio</h2>
<p>
  La mercancía sigue siendo propiedad del vendedor correspondiente hasta su pago íntegro.
</p>

<h2 id="s8">8. Desistimiento y devolución voluntaria</h2>
<p>
  8.1 Los consumidores disponen de un derecho legal de desistimiento de <?= (int)$wd ?> días en los
  contratos con vendedores profesionales. La información completa, con el formulario modelo de
  desistimiento, se encuentra en
  <a href="<?= h(vr_url('legal/widerruf.php')) ?>"><?= te('legal_withdrawal') ?></a>.
</p>
<p>
  8.2 Más allá del derecho legal de desistimiento, concedemos para nuestra propia mercancía un
  derecho de devolución voluntario de <?= (int)$days ?> días desde la recepción. Condición: el
  artículo está sin usar, sin daños y con todas las etiquetas originales. El derecho de devolución
  voluntario no limita sus derechos legales. Los gastos de devolución dentro de la ampliación
  voluntaria corren a cargo del comprador; detalles en
  <a href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('legal_returns') ?></a>.
</p>
<p>
  8.3 En las compras a vendedores particulares no existe derecho legal de desistimiento (véase la
  cláusula 10).
</p>

<h2 id="s9">9. Garantía legal</h2>
<p>
  9.1 En el caso de vendedores profesionales rige la garantía legal conforme a los §§ 434 y
  siguientes BGB. Para los consumidores, el plazo de prescripción es de dos años desde la recepción
  de la mercancía.
</p>
<p>
  9.2 En la mercancía usada, el plazo de prescripción frente a consumidores puede reducirse a un
  año si así se acordó expresa y separadamente antes de la celebración del contrato. En su caso, la
  indicación correspondiente aparece en la página del producto.
</p>
<p>
  9.3 Las señales de uso mencionadas en la descripción del artículo no constituyen un defecto. Las
  diferencias de color debidas a la reproducción en pantalla no constituyen un defecto.
</p>
<p>
  9.4 Comuníquenos los daños de transporte en un plazo de 14 días con fotos. Tramitamos estos casos
  con independencia de la cuestión del derecho de desistimiento, también en el caso de vendedores
  particulares.
</p>

<h2 id="s10">10. Compras a vendedores particulares</h2>
<p>
  10.1 Las ofertas de personas privadas están identificadas como «<?= te('seller_private') ?>» en
  la página del producto, en la bolsa y durante el pedido. Antes de finalizar el pedido debe
  confirmar expresamente las consecuencias especiales.
</p>
<p>
  10.2 Como el vendedor no es empresario, no existe derecho legal de desistimiento. El vendedor
  particular puede excluir o limitar válidamente la garantía legal; dicha exclusión no se aplica en
  caso de dolo o de declaraciones deliberadamente falsas.
</p>
<p>
  10.3 Con independencia de ello: si la mercancía entregada se aparta sustancialmente de la
  descripción o no es original, reembolsamos el precio de compra íntegro, gastos de envío
  incluidos. En estos casos retenemos o reclamamos la parte del vendedor.
</p>
<p>
  10.4 Los vendedores particulares que en realidad venden de forma planificada, reiterada y con
  ánimo de lucro actúan profesionalmente. Si lo constatamos, reclasificamos o bloqueamos la cuenta;
  en tal caso, a los contratos ya celebrados se les aplican los derechos frente a empresarios.
</p>

<h2 id="s11">11. Premium Outlet (Vault)</h2>
<p>
  11.1 En la sección «Premium Outlet» (Vault) cada pieza se ofrece como lote individual con un
  precio de apertura, un precio mínimo y un plan de rebaja publicado. El precio baja en
  <?= (int)$steps ?> escalones iguales, uno cada <?= (int)$hours ?> horas, hasta el precio mínimo.
</p>
<p>
  11.2 El plan se fija al abrir el lote y no se modifica después. El precio no puede subir. El
  cálculo se realiza en el servidor a partir del plan y es idéntico para todos los visitantes; no
  existe una fijación de precios personalizada.
</p>
<p>
  11.3 Es determinante el precio mostrado en el momento de añadir el lote a la bolsa, que se le
  reserva durante el proceso de pedido (20 minutos). Al expirar la reserva, el lote vuelve a
  liberarse.
</p>
<p>
  11.4 Cada lote existe una sola vez. Termina con la primera compra efectiva. No existe derecho a
  adquirir un lote en un escalón posterior y más bajo.
</p>
<p>
  11.5 En las rebajas de precio indicamos, conforme al § 11 PAngV (Reglamento alemán de indicación
  de precios), el precio más bajo de los últimos 30 días. Como el precio del Vault solo baja, se
  trata del precio vigente inmediatamente antes del escalón actual. En el primer escalón no hay
  rebaja y no se anuncia ningún precio de referencia.
</p>
<p>
  11.6 Sus derechos legales, en particular el desistimiento y la garantía legal, rigen sin cambios
  en el Vault. La cláusula 10 sigue siendo aplicable a los vendedores particulares.
</p>
<p>
  11.7 Los miembros con registro de correo electrónico confirmado acceden a los nuevos lotes antes
  de su apertura general. La membresía es gratuita y revocable en cualquier momento; no existe
  derecho al acceso anticipado.
</p>
<p>
  11.8 Una alerta de precio y guardar una pieza en favoritos <strong>no</strong> la reservan ni
  otorgan derecho de adquisición preferente. La notificación se envía una sola vez y sin garantía de
  entrega ni de momento; lo único determinante es la disponibilidad en el momento del pedido.
</p>

<h2 id="s12">12. Autenticidad y procedencia</h2>
<p>
  12.1 Solo se ofrece mercancía original. Los vendedores están obligados a conservar los
  justificantes de compra de su mercancía y a presentárnoslos a petición.
</p>
<p>
  12.2 Si tras la compra resulta que un artículo no es original, reembolsamos el precio de compra
  íntegro más los gastos de envío y asumimos los costes de la devolución. Este derecho existe con
  independencia del tipo de vendedor.
</p>
<p>
  12.3 Los derechos de la cláusula 12.2 presuponen que nos facilite el acceso a la mercancía y a su
  reclamación en un plazo de 30 días desde la recepción y nos permita examinarla.
</p>

<h2 id="s13">13. Responsabilidad</h2>
<p>
  13.1 Respondemos sin limitación en caso de dolo y negligencia grave, por daños a la vida, la
  integridad física y la salud, conforme a la Ley alemana de responsabilidad por productos y en el
  alcance de una garantía asumida por nosotros.
</p>
<p>
  13.2 En caso de incumplimiento por negligencia leve de una obligación contractual esencial, la
  responsabilidad se limita al daño previsible y típico del contrato. En lo demás, la responsabilidad
  queda excluida.
</p>
<p>
  13.3 No respondemos de los incumplimientos del contrato de compraventa por parte de vendedores
  terceros; a este respecto, nuestra responsabilidad se rige por las normas sobre servicios de
  alojamiento de datos (art. 6 del Reglamento de Servicios Digitales, DSA). Las cláusulas 10.3 y
  12.2 no se ven afectadas.
</p>
<p>
  13.4 No se garantiza la disponibilidad ininterrumpida de la plataforma.
</p>

<h2 id="s14">14. Vales de descuento</h2>
<p>
  14.1 Los vales promocionales (vales no adquiridos a título oneroso, sino emitidos en el marco de
  una acción publicitaria) solo pueden canjearse en el periodo indicado y una sola vez. Tras su
  caducidad queda excluido el canje; no se concede prórroga.
</p>
<p>
  14.2 El valor del vale se descuenta del valor de la mercancía, no de los gastos de envío. Queda
  excluido el pago en efectivo, el devengo de intereses o el abono de un posible saldo restante.
</p>
<p>
  14.3 Si un vale está vinculado a una dirección de correo electrónico o está expresamente
  identificado como vale de bienvenida o de primer pedido, solo puede canjearlo el titular de dicha
  dirección y únicamente para el primer pedido pagado. Queda excluida la cesión a terceros o la
  reventa.
</p>
<p>
  14.4 Varios vales no son acumulables, salvo que las condiciones del vale correspondiente dispongan
  otra cosa. Rige un importe mínimo de pedido si se indica junto al vale; es determinante el valor
  de la mercancía sin gastos de envío.
</p>
<p>
  14.5 Si desiste de un pedido total o parcialmente, reembolsamos el importe efectivamente pagado.
  Un vale promocional canjeado no se reactiva; no existe derecho a la emisión de un nuevo vale.
</p>
<p>
  14.6 En caso de sospecha fundada de uso abusivo — en particular la creación de varias cuentas para
  utilizar repetidamente vales de primer pedido — podemos bloquear vales concretos.
</p>

<h2 id="s15">15. Protección de datos</h2>
<p>
  Encontrará información sobre el tratamiento de sus datos personales en la
  <a href="<?= h(vr_url('legal/datenschutz.php')) ?>"><?= te('legal_privacy') ?></a>.
  Para tramitar su pedido transmitimos al vendedor correspondiente los datos necesarios para el
  envío y la facturación.
</p>

<h2 id="s16">16. Disposiciones finales</h2>
<p>
  16.1 Se aplica el Derecho de la República Federal de Alemania. Para los consumidores con
  residencia en otro Estado, las normas imperativas de protección de los consumidores de su Estado
  de residencia no se ven afectadas (art. 6 apdo. 2 del Reglamento Roma I).
</p>
<p>
  16.2 El lugar de cumplimiento y el fuero se rigen por las disposiciones legales. Para los
  consumidores rigen los fueros legales.
</p>
<p>
  16.3 Si alguna disposición de estas condiciones fuera ineficaz, la validez de las restantes no se
  verá afectada. La disposición ineficaz se sustituye por la regulación legal.
</p>
<p>
  16.4 Nos reservamos el derecho a modificar estas condiciones con efectos para el futuro. A los
  contratos ya celebrados se les aplica la versión consultable en el momento de su celebración; la
  versión aceptada se guarda con su pedido (versión actual: <?= h(VR_TERMS_VERSION) ?>).
</p>

<div class="doc__box">
  <strong>Nota sobre esta traducción</strong>
  <p style="margin-top:8px">
    Este texto en español es una traducción de cortesía de las condiciones generales de venta
    alemanas, las únicas jurídicamente vinculantes. Le permite leer aquello que acepta; en caso de
    discrepancia prevalece la versión alemana. No constituye asesoramiento jurídico.
  </p>
</div>
