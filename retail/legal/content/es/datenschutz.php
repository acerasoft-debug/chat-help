<?php
/**
 * Política de privacidad (RGPD) — español (traducción de cortesía; el texto
 * alemán es el vinculante). Escrita a partir de lo que el sitio hace realmente.
 * Variables: legal/datenschutz.php.
 */
?>
<h2>1. Responsable</h2>
<?php vr_company_block(); ?>
<p>
  No se ha designado un delegado de protección de datos, al no concurrir los requisitos legales
  (art. 37 RGPD, § 38 BDSG). Para consultas sobre protección de datos diríjase a
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[correo electrónico]</em>' ?>.
</p>

<h2>2. Lo que <em>no</em> hacemos</h2>
<p>
  No utilizamos ninguna herramienta de analítica web (ni Google Analytics ni Matomo), ni píxeles
  publicitarios o de seguimiento, ni plugins de redes sociales, ni elaboración de perfiles. No se
  realizan decisiones automatizadas en el sentido del art. 22 RGPD. La fijación de precios en el
  Premium Outlet tampoco está personalizada: los precios resultan de un plan fijo y publicado y son
  idénticos para todos los visitantes.
</p>
<p>
  Todas las fuentes, hojas de estilo, scripts e imágenes se cargan desde nuestro propio servidor. En
  particular, no se utilizan Google Fonts — así su dirección IP no se transmite a ningún tercero al
  cargar la página.
</p>

<h2>3. Visita al sitio web (archivos de registro del servidor)</h2>
<p>
  Al visitar el sitio, nuestro proveedor de alojamiento trata datos técnicamente necesarios:
  dirección IP, fecha y hora, recurso consultado, referente, agente de usuario y volumen de datos
  transferidos. Estos datos son necesarios para servir la página y defenderse de ataques.
</p>
<ul>
  <li><strong>Finalidad:</strong> prestación, estabilidad, seguridad informática</li>
  <li><strong>Base jurídica:</strong> art. 6.1 f) RGPD (interés legítimo)</li>
  <li><strong>Plazo de conservación:</strong> por lo general 7–30 días, después borrado automático</li>
</ul>

<h2>4. Cookies y almacenamiento local</h2>
<p>
  Utilizamos exclusivamente cookies técnicamente necesarias. Para ellas no se requiere
  consentimiento conforme al § 25 apdo. 2 n.º 2 TDDDG — por eso no ve ningún banner de cookies en
  nuestro sitio.
</p>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Nombre</th><th>Finalidad</th><th>Duración</th></tr></thead>
  <tbody>
    <tr><td><code><?= h((string)vr_config('session_name', 'maxsales_retail')) ?></code></td>
        <td>sesión: bolsa, acceso de vendedor, protección CSRF</td><td>fin de la sesión</td></tr>
    <tr><td><code>vr_lang</code></td><td>idioma elegido</td><td>180 días</td></tr>
    <tr><td><code>vr_member</code></td>
        <td>acceso anticipado al Vault tras suscripción confirmada al boletín (valor firmado, sin
            correo en claro)</td><td>1 año</td></tr>
    <tr><td><code>vr_wish</code></td><td>favoritos — solo identificadores de artículos</td><td>180 días</td></tr>
    <tr><td><code>vr_seen</code></td><td>artículos vistos recientemente — solo identificadores</td><td>30 días</td></tr>
  </tbody>
</table></div>
<p>
  Más información: <a href="<?= h(vr_url('legal/cookies.php')) ?>"><?= te('legal_cookies') ?></a>.
</p>

<h2>5. Favoritos y artículos vistos recientemente</h2>
<p>
  Los favoritos y «Vistos recientemente» se guardan <strong>exclusivamente en una cookie en su
  dispositivo</strong>. La cookie solo contiene identificadores de artículos (p. ej.
  <code>blm-ah0eg000</code>) — ningún nombre, ningún correo electrónico, ningún identificador que le
  haga reconocible. En nuestros servidores no se crea ningún perfil ni vinculación con su persona.
</p>
<ul>
  <li><strong>Finalidad:</strong> la función que usted ha solicitado expresamente</li>
  <li><strong>Base jurídica:</strong> § 25 apdo. 2 n.º 2 TDDDG (técnicamente necesario para el
      servicio solicitado por el usuario); en lo personal, art. 6.1 f) RGPD</li>
  <li><strong>Plazo de conservación:</strong> favoritos 180 días, vistos recientemente 30 días — o
      hasta que borre las cookies</li>
</ul>

<h2>6. Formulario de contacto</h2>
<p>
  Si utiliza el formulario de contacto, tratamos su dirección de correo electrónico, opcionalmente
  nombre y número de pedido, así como el contenido de su mensaje. Se guarda una copia en nuestro
  servidor para que ninguna consulta se pierda si falla el envío del correo.
</p>
<ul>
  <li><strong>Finalidad:</strong> responder a su consulta</li>
  <li><strong>Base jurídica:</strong> art. 6.1 b) RGPD si se refiere a un pedido; en otro caso,
      art. 6.1 f) RGPD</li>
  <li><strong>Plazo de conservación:</strong> hasta la tramitación definitiva, después como máximo
      seis meses; si se refiere a un pedido rigen los plazos mercantiles de conservación</li>
</ul>
<p>
  Contra el spam utilizamos un campo invisible del formulario y una medición de tiempo. <em>No</em>
  se integra ningún servicio externo de captcha — por tanto no se transmiten datos a terceros.
</p>

<h2>7. Alerta de precio en el Vault</h2>
<p>
  Si establece una alerta de precio para un lote, guardamos su dirección de correo electrónico, el
  lote, su precio deseado, el momento y un hash con sal de su dirección IP como prueba.
</p>
<ul>
  <li><strong>Finalidad:</strong> la única notificación que usted ha solicitado</li>
  <li><strong>Base jurídica:</strong> art. 6.1 a) RGPD (consentimiento)</li>
  <li><strong>Plazo de conservación:</strong> hasta el envío de la notificación, como máximo 90
      días. Después el registro se borra por completo.</li>
</ul>
<p>
  Se envía <strong>exactamente un</strong> correo; después la alerta queda agotada. No siguen
  recordatorios ni publicidad. Cada alerta puede borrarse de inmediato mediante el enlace del
  correo.
</p>

<h2>8. Pedido y ejecución del contrato</h2>
<p>
  Para un pedido tratamos: nombre, dirección de entrega y de facturación, correo electrónico,
  artículos pedidos, precios, estado del pago, número de pedido y una prueba de su aceptación de las
  condiciones generales y de la información sobre desistimiento (momento, versión y hash con sal de
  su dirección IP — la IP en sí no se guarda).
</p>
<ul>
  <li><strong>Finalidad:</strong> ejecución del contrato, envío, facturación, reversión</li>
  <li><strong>Base jurídica:</strong> art. 6.1 b) RGPD; para la conservación, art. 6.1 c) RGPD</li>
  <li><strong>Plazo de conservación:</strong> los datos de pedidos y facturas están sujetos a los
      plazos de conservación mercantiles y fiscales (§ 147 AO, § 257 HGB) y se conservan en
      consecuencia; después se borran.</li>
</ul>
<p>
  <strong>Comunicación a vendedores:</strong> en artículos de vendedores terceros transmitimos al
  vendedor correspondiente los datos necesarios para el envío y la facturación (nombre, dirección de
  entrega, artículos pedidos, número de pedido). El vendedor es responsable independiente de esos
  datos. No se transmiten datos de pago ni información sobre artículos de otros vendedores.
</p>

<h2>9. Procesamiento del pago (Stripe)</h2>
<p>
  Los pagos se procesan a través de Stripe Payments Europe, Limited, 1 Grand Canal Street Lower,
  Grand Canal Dock, Dublín, Irlanda. Sus datos de pago los introduce directamente en Stripe; de
  Stripe solo recibimos información de estado (pagado/pendiente/fallido), importe, método de pago de
  forma general, nombre, correo electrónico y dirección de entrega.
</p>
<ul>
  <li><strong>Base jurídica:</strong> art. 6.1 b) RGPD (ejecución del contrato)</li>
  <li><strong>Transferencia a terceros países:</strong> Stripe puede transferir datos a Stripe, Inc.
      en EE. UU., sobre la base de las cláusulas contractuales tipo de la Comisión Europea y de la
      certificación conforme al Marco de Privacidad de Datos UE-EE. UU.</li>
</ul>
<p>
  Para los pagos a vendedores utilizamos Stripe Connect. Los vendedores celebran para ello su propio
  acuerdo con Stripe; las pruebas de identidad allí recabadas (KYC/prevención del blanqueo) las
  trata Stripe como responsable independiente. Nosotros solo recibimos los indicadores de estado
  <code>charges_enabled</code>, <code>payouts_enabled</code> y <code>details_submitted</code>.
</p>
<p>Información de privacidad de Stripe: <a href="https://stripe.com/privacy" rel="noopener">stripe.com/privacy</a></p>

<h2>10. Envío de correos electrónicos</h2>
<p>
  Los correos transaccionales (confirmación de pedido, aviso de envío, notificación al vendedor) y
  el boletín se envían a través de
  <?= h($mail['provider'] === 'brevo' ? 'Brevo (Sendinblue GmbH / Brevo SAS, París, Francia)' : 'nuestro servidor de correo') ?>.
  Se transmiten la dirección de correo, el nombre y el contenido del mensaje.
</p>
<ul>
  <li><strong>Base jurídica:</strong> correos transaccionales art. 6.1 b) RGPD; boletín art. 6.1 a)
      RGPD (consentimiento)</li>
  <li><strong>Encargo del tratamiento:</strong> existe un contrato conforme al art. 28 RGPD.</li>
</ul>

<h2>11. Boletín / membresía del Vault</h2>
<p>
  La suscripción se realiza mediante doble opt-in: tras introducir la dirección recibe un correo de
  confirmación; la suscripción solo surte efecto al hacer clic en el enlace. Como prueba guardamos el
  momento de la suscripción, el momento de la confirmación y un hash con sal de la dirección IP.
</p>
<p>
  Las suscripciones no confirmadas se borran automáticamente a los 7 días. Puede revocar su
  consentimiento en cualquier momento — mediante el enlace de baja incluido en cada correo o
  escribiéndonos. La revocación no afecta a la licitud del tratamiento realizado hasta entonces.
</p>

<h2>12. Cuentas de vendedor</h2>
<p>
  Para una cuenta de vendedor tratamos: nombre, en su caso razón social, correo electrónico, país,
  en su caso NIF-IVA, tipo de vendedor (profesional/particular), contraseña (solo como hash
  criptográfico, nunca en claro), ofertas y datos de ventas y pagos.
</p>
<ul>
  <li><strong>Base jurídica:</strong> art. 6.1 b) RGPD; para la comprobación de las ofertas y la
      trazabilidad de los datos de los comerciantes, además art. 6.1 c) RGPD en relación con el
      art. 30 del Reglamento de Servicios Digitales.</li>
  <li><strong>Publicación:</strong> en los vendedores profesionales mostramos nombre/razón social y
      país en la página del producto; la ley lo exige. En los vendedores particulares solo se muestra
      el estado «<?= te('seller_private') ?>», no el nombre completo.</li>
</ul>

<h2>13. Medidas de seguridad y registros</h2>
<p>
  Llevamos registros técnicos de intentos de acceso fallidos, errores de pago y eventos de webhook.
  Contienen momento, tipo de evento e identificadores técnicos; las direcciones de correo se
  truncan. La finalidad es la prevención de abusos y fraudes (art. 6.1 f) RGPD); conservación máxima
  90 días.
</p>
<p>
  La transmisión está cifrada (TLS). Las contraseñas se guardan con un procedimiento moderno de hash
  unidireccional.
</p>

<h2>14. Sus derechos</h2>
<p>Tiene en todo momento derecho a:</p>
<ul>
  <li>acceso a los datos guardados sobre su persona (art. 15 RGPD)</li>
  <li>rectificación de datos inexactos (art. 16 RGPD)</li>
  <li>supresión (art. 17 RGPD), salvo obligación de conservación en contrario</li>
  <li>limitación del tratamiento (art. 18 RGPD)</li>
  <li>portabilidad de los datos (art. 20 RGPD)</li>
  <li>oposición a tratamientos basados en intereses legítimos (art. 21 RGPD)</li>
  <li>revocación de los consentimientos otorgados, con efectos para el futuro (art. 7.3 RGPD)</li>
</ul>
<p>
  Basta un mensaje a
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[correo electrónico]</em>' ?>.
  Además tiene derecho a presentar una reclamación ante una autoridad de control de protección de
  datos, por ejemplo la de su residencia habitual.
</p>

<h2>15. Cambios</h2>
<p>
  Adaptamos esta declaración cuando cambia el tratamiento real — por ejemplo, al recurrir a un nuevo
  proveedor. Rige la versión publicada en esta página.
</p>
