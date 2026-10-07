<?php
/**
 * Información sobre el desistimiento + formulario modelo — español (traducción
 * de cortesía). Sigue el modelo oficial de la Directiva 2011/83/UE (anexo I),
 * igual que el original alemán sigue el BGB. Variables: legal/widerruf.php.
 */
?>
<div class="notice">
  <strong>Importante en las compras en el marketplace:</strong> el derecho legal de desistimiento
  solo existe en los contratos entre un consumidor y un <em>empresario</em>. Por tanto, en los
  artículos identificados como «<?= te('seller_private') ?>» en la página del producto
  <strong>no</strong> existe derecho de desistimiento. Esta indicación la ve antes de pagar y debe
  confirmarla expresamente durante el pedido.
</div>

<h2>Derecho de desistimiento</h2>
<p>
  Tiene usted derecho a desistir del presente contrato en un plazo de <?= (int)$wd ?> días sin
  necesidad de justificación.
</p>
<p>
  El plazo de desistimiento expirará a los <?= (int)$wd ?> días del día en que usted o un tercero
  por usted indicado, distinto del transportista, adquiera la posesión material de los bienes.
</p>
<p>
  En el caso de un contrato relativo a múltiples bienes encargados en el mismo pedido y entregados
  por separado, el plazo expirará a los <?= (int)$wd ?> días del día en que usted o un tercero por
  usted indicado, distinto del transportista, adquiera la posesión material del último de esos
  bienes.
</p>
<p>
  Para ejercer el derecho de desistimiento, deberá usted notificarnos a
</p>
<div class="doc__box">
  <?= h($co) ?><br>
  <?= h($addrShown) ?><br>
  <?= $email !== '' ? 'Correo electrónico: <a href="mailto:' . h($email) . '">' . h($email) . '</a>' : 'Correo electrónico: [dirección de correo electrónico]' ?>
</div>
<p>
  su decisión de desistir del contrato a través de una declaración inequívoca (por ejemplo, una
  carta enviada por correo postal o un correo electrónico). Podrá utilizar el modelo de formulario
  de desistimiento que figura a continuación, aunque su uso no es obligatorio.
</p>
<p>
  Si el desistimiento se refiere a un artículo de un vendedor tercero profesional, basta la
  declaración dirigida a nosotros; estamos autorizados a recibirla y la remitimos sin demora.
</p>
<p>
  Para cumplir el plazo de desistimiento, basta con que la comunicación relativa al ejercicio por su
  parte de este derecho sea enviada antes de que venza el plazo correspondiente.
</p>

<h2>Consecuencias del desistimiento</h2>
<p>
  En caso de desistimiento por su parte, le devolveremos todos los pagos recibidos de usted,
  incluidos los gastos de entrega (con la excepción de los gastos adicionales resultantes de la
  elección por su parte de una modalidad de entrega diferente a la modalidad menos costosa de
  entrega ordinaria que ofrezcamos) sin ninguna demora indebida y, en todo caso, a más tardar
  catorce días a partir de la fecha en la que se nos informe de su decisión de desistir del presente
  contrato. Procederemos a efectuar dicho reembolso utilizando el mismo medio de pago empleado por
  usted para la transacción inicial, a no ser que haya usted dispuesto expresamente lo contrario; en
  todo caso, no incurrirá en ningún gasto como consecuencia del reembolso.
</p>
<p>
  Podremos retener el reembolso hasta haber recibido los bienes, o hasta que usted haya presentado
  una prueba de la devolución de los mismos, según qué condición se cumpla primero.
</p>
<p>
  Deberá usted devolvernos o entregarnos directamente los bienes, a nosotros o al vendedor indicado
  en la etiqueta de devolución, sin ninguna demora indebida y, en cualquier caso, a más tardar en el
  plazo de catorce días a partir de la fecha en que nos comunique su decisión de desistimiento del
  contrato. Se considerará cumplido el plazo si efectúa la devolución de los bienes antes de que
  haya concluido dicho plazo.
</p>
<p>
  Asumimos los costes de devolución de los bienes si la devolución se realiza desde Alemania. En las
  devoluciones desde otros países, deberá usted asumir el coste directo de devolución de los bienes.
</p>
<p>
  Solo será usted responsable de la disminución de valor de los bienes resultante de una
  manipulación distinta a la necesaria para establecer la naturaleza, las características y el
  funcionamiento de los bienes. Probarse una prenda está permitido; llevarla puesta, lavarla o
  retirar las etiquetas va más allá.
</p>

<h2>Exclusión del derecho de desistimiento</h2>
<p>El derecho de desistimiento no existe o se extingue en los siguientes contratos:</p>
<ul>
  <li>contratos con vendedores que no son empresarios (vendedores particulares);</li>
  <li>contratos de suministro de bienes confeccionados conforme a las especificaciones del
      consumidor o claramente personalizados;</li>
  <li>contratos de suministro de bienes precintados que no sean aptos para ser devueltos por razones
      de protección de la salud o de higiene y que hayan sido desprecintados tras la entrega (p. ej.
      pendientes, trajes de baño sin precinto higiénico);</li>
  <li>contratos en los que usted actúa como empresario (B2B).</li>
</ul>

<h2>Modelo de formulario de desistimiento</h2>
<div class="doc__box">
  <p><em>(Solo debe cumplimentar y enviar el presente formulario si desea desistir del
  contrato.)</em></p>
  <p>
    A la atención de<br>
    <?= h($co) ?><br>
    <?= h($addrShown) ?><br>
    <?= $email !== '' ? h($email) : '[dirección de correo electrónico]' ?>
  </p>
  <p>
    Por la presente le comunico/comunicamos (*) que desisto de mi/desistimos de nuestro (*) contrato
    de venta del siguiente bien (*):
  </p>
  <p>
    ______________________________________________<br>
    ______________________________________________
  </p>
  <p>
    Número de pedido: ____________________________<br>
    Pedido el (*) / recibido el (*): ____________________________<br>
    Nombre del consumidor o de los consumidores: ____________________________<br>
    Domicilio del consumidor o de los consumidores: ____________________________<br>
    ____________________________
  </p>
  <p>
    Firma del consumidor o de los consumidores <em>(solo si el presente formulario se presenta en
    papel)</em>: ____________________________<br>
    Fecha: ____________________________
  </p>
  <p><em>(*) Táchese lo que no proceda.</em></p>
</div>

<p class="doc__related">
  Más allá del derecho legal de desistimiento, concedemos un derecho de devolución voluntario para
  nuestra propia mercancía — detalles en
  <a href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('legal_returns') ?></a>.
</p>
