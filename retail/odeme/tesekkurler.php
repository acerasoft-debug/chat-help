<?php
/**
 * OxaPay dönüş adresi → markalı sipariş durumu sayfası.
 * Asıl onay webhook ile gelir; bu sayfa müşteriyi Sarvesto'nun kendi sipariş
 * sayfasına (order.php, id + token) götürür. OxaPay panelinde return:
 * https://sarvesto.com/odeme/tesekkurler.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/orders.php';

$id  = trim((string)($_GET['order'] ?? ''));
$tok = trim((string)($_GET['t'] ?? ''));

if ($id !== '' && $tok !== '' && vr_order_get($id, $tok) !== null) {
    vr_redirect('order.php', ['id' => $id, 'token' => $tok, 'paid' => 1]);
}
// Token yoksa/eşleşmezse sipariş arama ekranına.
vr_redirect('order.php');
