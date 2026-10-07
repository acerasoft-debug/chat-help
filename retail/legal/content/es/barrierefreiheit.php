<?php
/**
 * Declaración de accesibilidad (BFSG) — español (traducción de cortesía).
 * Variables: legal/barrierefreiheit.php.
 */
?>
<h2>Nuestro compromiso</h2>
<p>
  Queremos que esta tienda sea utilizable por todos — con teclado, con lector de pantalla, con letra
  ampliada, con movimiento reducido. La base son los requisitos de la ley alemana de refuerzo de la
  accesibilidad (BFSG) y la norma EN 301 549, que remite a las WCAG 2.1 nivel AA.
</p>

<h2>Estado de implantación</h2>
<p>
  Según nuestra valoración, este sitio web es <strong>en gran medida conforme</strong> con las WCAG
  2.1 nivel AA. La valoración se basa en una comprobación interna, no en una auditoría externa.
</p>

<h3>Lo que está implantado</h3>
<ul>
  <li><strong>Utilizable sin JavaScript:</strong> navegación, filtros, bolsa, caja y área de
      vendedor funcionan por completo con formularios HTML puros. JavaScript solo aporta comodidad
      (cuenta atrás, selector de cantidad, apariciones suaves).</li>
  <li><strong>Manejo con teclado:</strong> todos los elementos interactivos son alcanzables, con
      indicador de foco visible; un enlace «Saltar al contenido» está al inicio de la página.</li>
  <li><strong>Contrastes:</strong> cada nodo de texto de las páginas principales se midió de forma
      automatizada (color realmente renderizado sobre fondo realmente renderizado, transparencias
      incluidas). Todos alcanzan al menos 4,5:1; la letra grande, al menos 3:1. La zona oscura del
      Vault usa sus propios valores de gris y los colores de acento tienen una variante de texto más
      oscura sobre fondo claro.</li>
  <li><strong>Estructura:</strong> un H1 por página, niveles de encabezado lógicos, puntos de
      referencia (<code>header</code>, <code>nav</code>, <code>main</code>, <code>footer</code>),
      campos de formulario etiquetados, mensajes de error vinculados.</li>
  <li><strong>Imágenes:</strong> las imágenes de producto llevan textos alternativos formados por
      marca y denominación; los gráficos puramente decorativos están ocultos para las ayudas
      técnicas.</li>
  <li><strong>Movimiento:</strong> con el ajuste del sistema «Reducir movimiento» activado, se
      desactivan la marquesina, las apariciones y las transiciones.</li>
  <li><strong>Zoom y pantallas pequeñas:</strong> la maquetación sigue siendo utilizable hasta un
      400 % de zoom sin desplazamiento horizontal del contenido.</li>
  <li><strong>Idioma:</strong> el idioma de la página está marcado en el HTML y puede cambiarse
      mediante el selector (diez idiomas).</li>
  <li><strong>Galería de imágenes:</strong> cada imagen de producto es un enlace normal al archivo de
      imagen. Sin JavaScript se abre directamente; con JavaScript, en un visor que se cierra con Esc
      y se recorre con las flechas.</li>
  <li><strong>Sugerencias de búsqueda:</strong> manejables con flechas e Intro, se cierran con Esc.
      El campo de búsqueda funciona también sin sugerencias como formulario corriente.</li>
  <li><strong>Favoritos:</strong> implementados como formulario; el estado consta en
      <code>aria-pressed</code> y se guarda correctamente también sin JavaScript.</li>
</ul>

<h3>Limitaciones conocidas</h3>
<ul>
  <li><strong>Imágenes de producto de vendedores terceros:</strong> los textos alternativos se
      generan automáticamente a partir de marca y denominación. No describen el motivo en detalle —
      si tiene dudas sobre un artículo, se lo describimos con gusto por correo.</li>
  <li><strong>Página de pago:</strong> el pago se realiza en Stripe. De la accesibilidad de esas
      páginas responde Stripe; no nos constan deficiencias de conformidad, pero no las hemos
      comprobado nosotros mismos.</li>
  <li><strong>Cuenta atrás del Vault:</strong> el tiempo restante se actualiza cada segundo. El
      precio determinante figura como texto al lado y solo cambia tras cargar la página, de modo que
      el uso de lector de pantalla no se ve perturbado por cambios en directo.</li>
  <li><strong>Documentos PDF:</strong> facturas y etiquetas de devolución las generan en parte
      vendedores y transportistas y pueden no estar etiquetadas. Previa solicitud facilitamos el
      contenido en forma accesible.</li>
</ul>

<h2>Comentarios y contacto</h2>
<p>
  Si encuentra una barrera, escriba a <?= $mail ?> con el asunto «Accesibilidad» e indique, si es
  posible, la página y su ayuda técnica. Respondemos en un día laborable y fijamos una fecha para la
  corrección. Si necesita una información en otra forma — letra más grande, texto sencillo, lectura
  por teléfono — basta con decirlo; la facilitamos gratuitamente.
</p>

<h2>Procedimiento de reclamación</h2>
<p>
  Si nuestra respuesta no le ayuda, puede dirigirse a la autoridad de vigilancia del mercado de los
  Länder para la accesibilidad de productos y servicios (MDBD), organismo competente conforme a la
  BFSG para reclamaciones sobre la accesibilidad de servicios:
  <a href="https://www.marktueberwachungsstelle.de" rel="noopener">marktueberwachungsstelle.de</a>.
</p>

<h2>Elaboración de esta declaración</h2>
<p>
  Esta declaración se elaboró el <?= h(vr_date(strtotime('2026-08-01'))) ?> sobre la base de una
  autoevaluación interna: navegación con teclado, medición automatizada del contraste de todos los
  nodos de texto en un navegador real, prueba con JavaScript desactivado, comprobación de la
  estructura de encabezados y de las etiquetas de formulario. No se ha realizado ninguna auditoría
  externa. Actualizamos la declaración cuando la tienda cambia de forma sustancial.
</p>
