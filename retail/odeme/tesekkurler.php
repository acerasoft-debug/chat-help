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
require_once __DIR__ . '/../inc/oxapay.php';

$id  = trim((string)($_GET['order'] ?? ''));
$tok = trim((string)($_GET['t'] ?? ''));

$order = ($id !== '' && $tok !== '') ? vr_order_get($id, $tok) : null;
if ($order !== null) {
    // OxaPay'den dönüş: webhook henüz gelmediyse (gecikme/engel) durumu şimdi
    // sor — müşteri sipariş sayfasını doğru durumla ("ödendi") görsün. İlk
    // soruda eşik yok; tekrar yüklemelerde 5 sn (bağlantıyla API dövülemesin).
    vr_oxapay_sync($order, 5);
    vr_redirect('order.php', ['id' => $id, 'token' => $tok, 'paid' => 1]);
}
// Token yoksa/eşleşmezse sipariş arama ekranına.
vr_redirect('order.php');
