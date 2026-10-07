<?php
/**
 * Envío y entrega — español (traducción de cortesía).
 * $ship y $countries vienen de legal/versand.php; las etiquetas están aquí.
 */
$zones = [
    'de'    => 'Alemania',
    'eu'    => 'Unión Europea',
    'ch'    => 'Suiza, Liechtenstein, Noruega, Reino Unido',
    'world' => 'Otros destinos',
];
?>
<h2>Gastos de envío y plazos</h2>
<div class="tablewrap" tabindex="0"><table>
  <thead>
    <tr><th>Destino</th><th>Gastos de envío</th><th>Gratis a partir de</th><th>Plazo</th></tr>
  </thead>
  <tbody>
  <?php foreach ($zones as $key => $label):
      $row = (array)($ship[$key] ?? []); ?>
    <tr>
      <td><?= h($label) ?></td>
      <td><?= h(vr_money((int)($row['cents'] ?? 0))) ?></td>
      <td><?= (int)($row['free_over'] ?? 0) > 0 ? h(vr_money((int)$row['free_over'])) : '—' ?></td>
      <td><?= h((string)($row['days'] ?? '')) ?> días laborables</td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<p>
  Todos los importes son precios finales con IVA incluido. Los gastos de envío aplicables a su
  pedido se muestran en la bolsa en cuanto elige el país de entrega, y se indican de nuevo con el
  importe exacto durante el pedido, antes de confirmarlo.
</p>

<h3>Países de la UE a los que enviamos</h3>
<p class="doc__list">
  <?= h(implode(' · ', vr_country_options($countries['eu']))) ?>
</p>

<h3>Otros destinos</h3>
<p class="doc__list">
  <?= h(implode(' · ', vr_country_options($countries['world']))) ?>
</p>
<p>
  Si su país no aparece, escríbanos — muchas cosas pueden resolverse caso por caso.
</p>

<h2>Inicio del envío</h2>
<p>
  El plazo comienza con la autorización del pago. Con tarjeta, Apple Pay y Google Pay suele ser
  inmediata; con adeudo SEPA y Klarna pueden añadirse de uno a tres días hábiles bancarios. Los
  pedidos autorizados antes de las 13:00 salen por lo general el mismo día laborable.
</p>

<h2>Varios vendedores, varios paquetes</h2>
<p>
  <?= h((string)vr_config('brand')) ?> es un marketplace. Si su pedido contiene artículos de
  distintos vendedores, cada vendedor envía por separado. Recibirá entonces varios paquetes y varios
  enlaces de seguimiento — pero paga una sola vez y los gastos de envío se cobran una sola vez.
</p>

<h2>Seguimiento del envío</h2>
<p>
  Cada envío está asegurado y se expide con número de seguimiento. Recibirá el enlace por correo
  electrónico en cuanto el paquete se entregue al transportista. Además, puede consultar el estado
  actual en cualquier momento en <a href="<?= h(vr_url('order.php')) ?>"><?= te('order_status') ?></a>
  con su número de pedido y su dirección de correo electrónico.
</p>

<h2>Taquillas de paquetería y dirección de entrega distinta</h2>
<p>
  Dentro de Alemania entregamos en Packstations de DHL; indique la Packstation junto con su número
  postal como dirección de entrega. Para los envíos internacionales se requiere una dirección postal
  completa.
</p>

<h2>Aduanas e impuestos de importación</h2>
<p>
  Dentro de la UE no se aplican aranceles ni impuestos de importación. En las entregas a Suiza,
  Noruega, el Reino Unido o fuera de Europa pueden aplicarse IVA a la importación, aranceles y tasas
  de gestión del transportista. Corren a cargo del destinatario y no forman parte del precio pagado
  a nosotros.
</p>

<h2>Envíos no entregados</h2>
<p>
  Si un paquete se devuelve al vendedor como no entregable, acordamos con usted un nuevo envío. Se
  aplican nuevos gastos de envío si la no entrega se debe a una dirección de entrega incompleta o
  incorrecta.
</p>

<h2>Daños de transporte</h2>
<p>
  Si un paquete llega visiblemente dañado, acéptelo, documente el daño con fotos y contacte con
  nosotros en un plazo de 14 días. Resolvemos estos casos con independencia del derecho de
  desistimiento — también en el caso de vendedores particulares. Sus derechos legales no se ven
  limitados por ello.
</p>

<p class="doc__related">
  Véase también <a href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('legal_returns') ?></a> y
  <a href="<?= h(vr_url('legal/widerruf.php')) ?>"><?= te('legal_withdrawal') ?></a>.
</p>
