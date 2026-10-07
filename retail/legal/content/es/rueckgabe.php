<?php
/**
 * Devoluciones — español (traducción de cortesía). Variables: legal/rueckgabe.php.
 */
?>
<h2>De un vistazo</h2>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Vendedor</th><th>Plazo</th><th>Gastos de devolución</th></tr></thead>
  <tbody>
    <tr><td>Stock <?= h((string)vr_config('brand')) ?></td>
        <td><?= (int)$days ?> días (<?= (int)$wd ?> legales + ampliación voluntaria)</td>
        <td>gratis desde Alemania dentro del plazo legal</td></tr>
    <tr><td>Comerciante</td>
        <td><?= (int)$wd ?> días de desistimiento legal; muchos comerciantes conceden más</td>
        <td>gratis desde Alemania dentro del plazo legal</td></tr>
    <tr><td>Vendedor particular</td>
        <td>sin derecho legal de desistimiento</td>
        <td>devolución solo si el artículo no coincide con la descripción</td></tr>
  </tbody>
</table></div>

<h2>Cómo devolver</h2>
<ol>
  <li>Escriba a
      <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[correo electrónico]</em>' ?>
      indicando su número de pedido y los artículos que desea devolver.</li>
  <li>En el plazo de un día laborable recibirá una etiqueta de devolución y la dirección de
      devolución del vendedor correspondiente.</li>
  <li>Embale la mercancía, a ser posible en la caja original, adjunte el albarán y entregue el
      paquete.</li>
  <li>Tras la recepción y comprobación, reembolsamos al mismo medio de pago — a más tardar 14 días
      después de recibir su declaración de desistimiento, en cuanto la mercancía esté con nosotros o
      usted haya acreditado el envío.</li>
</ol>
<p>
  También aceptamos devoluciones sin aviso previo; en ese caso la tramitación tarda más, porque la
  asignación se hace manualmente.
</p>

<h2>Estado de la devolución</h2>
<p>
  Probarse la prenda está expresamente permitido — para eso existe el derecho de devolución. Le
  rogamos que devuelva los artículos sin usar, sin lavar, sin perfumar y con todas las etiquetas
  originales. Por una pérdida de valor debida a un uso que vaya más allá, podemos exigir una
  compensación; la calculamos de forma transparente y nos ponemos en contacto con usted antes.
</p>

<h2>Lo que no puede devolverse</h2>
<ul>
  <li>trajes de baño y pendientes sin precinto higiénico intacto;</li>
  <li>artículos ajustados individualmente o confeccionados según sus indicaciones;</li>
  <li>artículos de vendedores particulares, siempre que coincidan con la descripción.</li>
</ul>

<h2>Cambio</h2>
<p>
  No es posible un cambio directo, porque la mayoría de los artículos son piezas únicas o tallas
  sueltas. Devuelva el artículo y vuelva a pedir la talla adecuada — si sigue disponible. Si le
  interesa una talla concreta, escríbanos; le diremos si cabe esperar reposición.
</p>

<h2>Artículo defectuoso o no conforme a la descripción</h2>
<p>
  En ese caso no se aplica la devolución, sino la garantía legal — con mejores derechos para usted.
  Contacte con nosotros con fotos; la devolución es entonces siempre gratuita, también en el caso de
  vendedores particulares y también una vez vencido el plazo de devolución, dentro de la
  prescripción legal.
</p>

<h2>Mercancía no original</h2>
<p>
  Si un artículo resultara no ser original, reembolsamos el precio de compra íntegro, gastos de
  envío incluidos, y asumimos la devolución — con independencia del vendedor que lo haya ofrecido.
  El vendedor afectado es retirado de la plataforma.
</p>

<h2>Premium Outlet</h2>
<p>
  Los precios rebajados no cambian nada en sus derechos: en las compras del Vault rigen los mismos
  plazos indicados arriba. Solo se mantiene la excepción de los vendedores particulares.
</p>

<p class="doc__related">
  El texto legal con el formulario modelo de desistimiento:
  <a href="<?= h(vr_url('legal/widerruf.php')) ?>"><?= te('legal_withdrawal') ?></a>.
</p>
