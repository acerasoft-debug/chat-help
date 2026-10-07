<?php
/**
 * Aviso legal (§ 5 DDG, § 18 MStV) — español (traducción de cortesía). Variables: legal/impressum.php.
 */
?>
<h2>Proveedor</h2>
<?php vr_company_block(); ?>

<h2>Operador del marketplace</h2>
<p>
  <?= h((string)vr_config('brand')) ?> es un marketplace en línea operado por
  <?= h((string)($c['legal_name'] ?? '')) ?>. A través de la plataforma se ofrecen tanto artículos
  propios del operador como artículos de terceros (comerciantes profesionales y vendedores
  particulares). Quién es su parte contratante en la compraventa se indica en cada página de
  producto y durante el pedido, antes de confirmarlo.
</p>

<h2>Responsable del contenido</h2>
<p>
  Responsable conforme al § 18 apdo. 2 MStV es la persona con poder de representación indicada
  arriba, con la misma dirección.
</p>

<h2>Contacto para consultas de consumidores</h2>
<p>
  Dirija sus consultas sobre pedidos, devoluciones y reclamaciones a
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[dirección de correo electrónico]</em>' ?>.
  Por lo general respondemos en un día laborable. Para preguntas sobre un artículo de un vendedor
  tercero le ponemos en contacto con el vendedor o remitimos su consulta.
</p>

<h2>Resolución de litigios de consumo</h2>
<p>
  No estamos obligados ni dispuestos a participar en procedimientos de resolución de litigios ante
  una entidad de resolución alternativa de conflictos de consumo. Esto no excluye un acuerdo amistoso
  con nosotros — diríjase primero directamente a nosotros. Más información en
  <a href="<?= h(vr_url('legal/streitbeilegung.php')) ?>"><?= te('legal_disputes') ?></a>.
</p>

<h2>Proveedor de servicios de pago</h2>
<p>
  Los pagos se procesan a través de Stripe (Stripe Payments Europe, Limited, 1 Grand Canal Street
  Lower, Grand Canal Dock, Dublín, Irlanda). Los pagos a vendedores terceros se realizan mediante
  Stripe Connect. Los datos de tarjeta se tratan exclusivamente en Stripe y no llegan a nuestros
  sistemas.
</p>

<h2>Responsabilidad por los contenidos</h2>
<p>
  Como prestador de servicios somos responsables de nuestros propios contenidos en estas páginas
  conforme a las leyes generales. Respecto de las ofertas de vendedores terceros no estamos
  obligados a supervisar la información transmitida o almacenada ni a investigar circunstancias que
  indiquen una actividad ilícita (arts. 6 y 8 del Reglamento de Servicios Digitales). En cuanto
  tenemos conocimiento de una infracción concreta, retiramos sin demora el contenido afectado. Las
  notificaciones se dirigen a
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[dirección de correo electrónico]</em>' ?>.
</p>
<p>
  Comprobamos la plausibilidad de las ofertas de vendedores terceros antes de su publicación y
  exigimos pruebas de procedencia. Esta comprobación voluntaria no constituye garantía de la licitud
  o autenticidad de cada oferta individual y deja intacto nuestro privilegio de responsabilidad como
  prestador de servicios de alojamiento.
</p>

<h2>Responsabilidad por los enlaces</h2>
<p>
  Nuestra oferta contiene enlaces a sitios web externos de terceros sobre cuyo contenido no tenemos
  influencia. De dichos contenidos responde siempre el proveedor correspondiente. En el momento de
  enlazar no se apreciaban contenidos ilícitos.
</p>

<h2>Derechos de autor</h2>
<p>
  Los contenidos y obras creados por el operador en estas páginas están protegidos por derechos de
  autor. Las imágenes y descripciones de productos de vendedores terceros las facilitan estos, que
  nos garantizan disponer de los derechos necesarios. Los nombres de marcas y productos son
  propiedad de sus respectivos titulares. Su mención sirve únicamente para describir la mercancía
  ofrecida y no establece relación comercial alguna con los titulares de las marcas.
</p>

<h2>Nota sobre derechos de marca</h2>
<p>
  <?= h((string)vr_config('brand')) ?> no es distribuidor autorizado de las marcas citadas, salvo
  indicación expresa en contrario. La mercancía ofrecida es original y fue comercializada por
  primera vez en el Espacio Económico Europeo; su reventa es por tanto lícita conforme al principio
  de agotamiento del derecho de marca (§ 24 MarkenG, art. 15 RMUE).
</p>
