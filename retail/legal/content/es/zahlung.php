<?php
/**
 * Métodos de pago — español (traducción de cortesía). $mode: legal/zahlung.php.
 */
?>
<h2>Cómo funciona el pago</h2>
<p>
  Añade artículos a la bolsa, elige el país de entrega en la caja y confirma las condiciones
  generales y la información sobre el desistimiento. Para pagar le redirigimos a nuestro proveedor
  de servicios de pago Stripe. Allí introduce sus datos de pago y completa el pago; después vuelve a
  la confirmación del pedido.
</p>
<p>
  <strong>Los datos de su tarjeta nunca llegan a nuestros servidores.</strong> De Stripe recibimos
  únicamente si el pago se ha realizado correctamente, el importe, el método de pago de forma
  general y los datos necesarios para el envío y la factura.
</p>

<h2>Métodos de pago disponibles</h2>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Método de pago</th><th>Cargo</th><th>Nota</th></tr></thead>
  <tbody>
    <tr><td>Visa, Mastercard, American Express</td><td>inmediato</td>
        <td>puede requerirse la confirmación 3-D Secure de su banco (SCA)</td></tr>
    <tr><td>Apple Pay</td><td>inmediato</td><td>en dispositivos Apple con Safari</td></tr>
    <tr><td>Google Pay</td><td>inmediato</td><td>en Chrome y en Android</td></tr>
    <tr><td>Klarna</td><td>según la opción elegida</td>
        <td>pago por factura o a plazos; la contraparte de la financiación es Klarna</td></tr>
    <tr><td>Adeudo directo SEPA</td><td>1–3 días hábiles bancarios</td>
        <td>envío tras la autorización del pago</td></tr>
  </tbody>
</table></div>
<p class="doc__related">
  Los métodos de pago que se muestran realmente dependen del país de entrega, el importe y el
  dispositivo — Stripe solo muestra lo que puede utilizarse para su pedido. Ninguno de los métodos
  ofrecidos le supone costes adicionales.
</p>

<?php if ($mode !== 'live'): ?>
  <div class="notice notice--demo">
    <strong>Esta instalación no funciona actualmente en modo real.</strong>
    No pueden realizarse pagos reales
    (<?= $mode === 'test' ? 'modo de prueba de Stripe activo' : 'no hay claves de Stripe configuradas' ?>).
  </div>
<?php endif; ?>

<h2>Exigibilidad</h2>
<p>
  El precio de compra es exigible con la celebración del contrato. En los métodos de pago con
  liquidación diferida reservamos sus artículos y enviamos tras la autorización del pago.
</p>

<h2>Moneda e impuestos</h2>
<p>
  Todos los precios se indican en euros e incluyen el IVA legal del
  <?= h((int)vr_config('vat_rate_bps', 1900) / 100) ?> %, siempre que el vendedor correspondiente
  esté sujeto a IVA. En los artículos de vendedores particulares no se desglosa IVA. Si su banco
  liquida en otra moneda, puede cobrarle una comisión por cambio — sobre la que no tenemos ninguna
  influencia.
</p>

<h2>Factura</h2>
<p>
  Recibe la factura con la mercancía o por correo electrónico. En los artículos de vendedores
  terceros profesionales, la factura la emite el comerciante correspondiente; en nuestra propia
  mercancía, <?= h((string)(vr_config('company')['legal_name'] ?? '')) ?>. Los vendedores
  particulares no emiten facturas con IVA desglosado.
</p>

<h2>Reembolsos</h2>
<p>
  Los reembolsos se realizan siempre al mismo medio de pago con el que pagó. Con Klarna, el
  reembolso se gestiona a través de su cuenta Klarna; con SEPA, a la cuenta cargada. Iniciamos la
  tramitación tras la recepción y comprobación de la devolución; según el método de pago pueden
  pasar algunos días laborables hasta el abono en su banco.
</p>

<h2>Pago fallido</h2>
<p>
  Si un pago es rechazado, no se celebra ningún contrato y no se carga nada. Su bolsa se conserva
  para que pueda intentarlo de nuevo o con otro método de pago. La causa más frecuente es una
  confirmación 3-D Secure no completada.
</p>

<h2>Seguridad del pago</h2>
<p>
  La conexión está cifrada de extremo a extremo mediante TLS. Stripe está certificado como proveedor
  de servicios de pago PCI DSS nivel 1 y autorizado en Europa como entidad de pago (Stripe Payments
  Europe, Limited, Dublín). Para prevenir el fraude, Stripe comprueba las transacciones de forma
  automatizada; no almacenamos números de tarjeta.
</p>
<p class="doc__related">
  Detalles sobre el tratamiento de datos: <a href="<?= h(vr_url('legal/datenschutz.php')) ?>"><?= te('legal_privacy') ?></a>.
</p>
