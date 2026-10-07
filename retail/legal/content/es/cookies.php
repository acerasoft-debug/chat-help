<?php
/**
 * Política de cookies — español (traducción de cortesía). Sin banner, porque no hay seguimiento.
 */
?>
<div class="doc__box">
  <strong>Por qué no ve aquí un banner de cookies</strong>
  <p style="margin-top:8px">
    Un banner solo es necesario cuando se instalan cookies que van más allá de lo técnicamente
    necesario — analítica, publicidad, seguimiento. No utilizamos nada de eso. Para las cookies
    puramente funcionales, el § 25 apdo. 2 n.º 2 TDDDG permite su instalación sin consentimiento.
    Por eso: sin banner, sin botón «Aceptar todo», sin servicio de consentimiento.
  </p>
</div>

<h2>Lista completa</h2>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Nombre</th><th>Tipo</th><th>Finalidad</th><th>Conservación</th></tr></thead>
  <tbody>
    <tr>
      <td><code><?= h((string)vr_config('session_name', 'maxsales_retail')) ?></code></td>
      <td>cookie de sesión</td>
      <td>Mantiene unida su sesión: contenido de la bolsa, reservas en el Vault, acceso de vendedor
          y el token de seguridad contra la falsificación de formularios (CSRF). Solo contiene un
          identificador aleatorio, ningún contenido personal.</td>
      <td>hasta el fin de la sesión del navegador</td>
    </tr>
    <tr>
      <td><code>vr_lang</code></td>
      <td>funcional</td>
      <td>Recuerda el idioma elegido para que no tenga que seleccionarlo en cada clic. Contenido: un
          código de idioma como <code>es</code>.</td>
      <td>180 días</td>
    </tr>
    <tr>
      <td><code>vr_member</code></td>
      <td>funcional</td>
      <td>Solo se instala cuando ha confirmado por correo la membresía del Vault, y desbloquea el
          acceso anticipado a nuevos lotes. Contiene una fecha de caducidad, un hash truncado de su
          correo electrónico y una firma — no su dirección en claro.</td>
      <td>1 año</td>
    </tr>
    <tr>
      <td><code>vr_wish</code></td>
      <td>funcional</td>
      <td>Sus favoritos. Contenido: una lista de identificadores de artículos, nada más. No se
          comunica al servidor salvo que usted mismo abra la página de favoritos.</td>
      <td>180 días</td>
    </tr>
    <tr>
      <td><code>vr_seen</code></td>
      <td>funcional</td>
      <td>Los últimos artículos que ha visto, para que vuelva a encontrarlos. Igualmente solo
          identificadores de artículos.</td>
      <td>30 días</td>
    </tr>
  </tbody>
</table></div>

<h2>Cookies de Stripe</h2>
<p>
  Al pagar, pasa a una página de Stripe. Allí Stripe instala sus propias cookies, necesarias para el
  procesamiento del pago y la prevención del fraude. Esto ocurre en el dominio de Stripe y está
  sujeto a la <a href="https://stripe.com/privacy" rel="noopener">política de privacidad de Stripe</a>.
  En nuestras propias páginas no se integra ningún script de Stripe.
</p>

<h2>Sin almacenamiento local, sin huellas digitales</h2>
<p>
  No utilizamos ni <code>localStorage</code> ni <code>sessionStorage</code>, ni píxeles, ni técnicas
  de fingerprinting, ni reconocimiento entre dispositivos. Todas las fuentes, estilos, scripts e
  imágenes están en nuestro propio servidor; al cargar la página no se establece ninguna conexión con
  terceros.
</p>

<h2>Borrar o bloquear cookies</h2>
<p>
  Puede borrar o bloquear las cookies en cualquier momento en la configuración de su navegador. Si
  bloquea la cookie de sesión, la bolsa, la caja y el acceso de vendedor dejan de funcionar — falta
  el hilo técnico que conecta sus pasos. El idioma y el acceso al Vault pueden bloquearse sin
  problema; entonces volvemos a preguntar el idioma y el acceso anticipado no está disponible.
</p>
<p class="doc__related">
  En detalle sobre el tratamiento de datos:
  <a href="<?= h(vr_url('legal/datenschutz.php')) ?>"><?= te('legal_privacy') ?></a>.
</p>
