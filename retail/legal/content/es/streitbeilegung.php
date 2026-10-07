<?php
/**
 * Resolución de litigios + punto de contacto DSA + procedimiento de notificación (art. 16 DSA) — español.
 * Variables: legal/streitbeilegung.php.
 */
?>
<h2>Primero la vía directa</h2>
<p>
  La mayoría de los problemas se resuelven con un correo. Escriba a <?= $mail ?> indicando su
  número de pedido y una breve descripción. Por lo general respondemos en un día laborable y, a más
  tardar a los siete días, le comunicamos una decisión o un estado intermedio.
</p>

<h2>Quejas sobre vendedores</h2>
<p>
  En caso de problemas con un vendedor tercero — mercancía no recibida, estado distinto, reembolso
  no efectuado — mediamos y podemos retener la parte del vendedor hasta que se aclare el asunto. Si
  no se llega a una solución, reembolsamos nosotros mismos en los casos previstos en las
  <a href="<?= h(vr_url('legal/agb.php')) ?>#s10">cláusulas 10.3</a> y
  <a href="<?= h(vr_url('legal/agb.php')) ?>#s12">12.2</a> de las condiciones generales.
</p>

<h2>Notificación de contenidos ilícitos (art. 16 DSA)</h2>
<p>
  Cualquier persona puede notificarnos ofertas que considere ilícitas — por ejemplo falsificaciones,
  infracciones de marca o productos no permitidos. Indique, por favor:
</p>
<ul>
  <li>la dirección exacta (URL) de la oferta,</li>
  <li>una motivación de por qué el contenido sería ilícito,</li>
  <li>su nombre y una dirección de correo electrónico (salvo en notificaciones de delitos contra las
      personas),</li>
  <li>una declaración de que sus indicaciones son exactas y completas según su leal saber.</li>
</ul>
<p>
  Dirija las notificaciones a <?= $mail ?> con el asunto «Notificación DSA». Confirmamos la
  recepción de inmediato, decidimos con rapidez y diligencia y le comunicamos la decisión motivada.
  Los titulares de derechos que nos envían reiteradamente notificaciones fundadas se tramitan con
  prioridad (art. 22 DSA).
</p>

<h2>Si retiramos una oferta</h2>
<p>
  Los vendedores afectados son informados de forma motivada de cualquier retirada, bloqueo o
  reducción de visibilidad (art. 17 DSA) y pueden recurrir la decisión por correo electrónico en un
  plazo de 14 días. El recurso lo examina una persona que no participó en la decisión original.
  Comunicamos la decisión sobre el recurso de forma motivada.
</p>
<p>
  En caso de notificaciones manifiestamente infundadas u ofertas reiteradamente ilícitas suspendemos
  la tramitación tras una advertencia previa razonable o bloqueamos la cuenta (art. 23 DSA).
</p>

<h2>Resolución extrajudicial de litigios de consumo</h2>
<p>
  No estamos obligados ni dispuestos a participar en procedimientos de resolución de litigios ante
  una entidad de resolución alternativa de conflictos de consumo conforme a la ley alemana VSBG. Su
  posibilidad de acudir a los tribunales no se ve afectada; tampoco nuestro empeño en aclarar
  primero cada caso de forma directa.
</p>
<p>
  Nota: la antigua plataforma de resolución de litigios en línea (plataforma RLL) de la Comisión
  Europea dejó de funcionar el 20 de julio de 2025. Por ello se ha eliminado el enlace. En casos
  transfronterizos puede dirigirse al Centro Europeo del Consumidor
  (<a href="https://www.evz.de" rel="noopener">evz.de</a>).
</p>

<h2>Fuero y derecho aplicable</h2>
<p>
  Se aplica el Derecho alemán. Para los consumidores rigen los fueros legales; las normas
  imperativas de protección de los consumidores del Estado de residencia no se ven afectadas
  (art. 6 apdo. 2 del Reglamento Roma I).
</p>

<h2>Punto de contacto para autoridades</h2>
<p>
  Para solicitudes de autoridades y tribunales conforme al art. 11 DSA puede contactarnos en
  <?= $mail ?>. Los idiomas de procedimiento son el alemán y el inglés.
</p>
