<?php
/**
 * VESTRA — live voucher check for the cart (JSON).
 *
 * Preview only. The cart shows the buyer what a code is worth before they commit, but the
 * number that ends up on the invoice is recomputed from scratch in order.php: this endpoint
 * never writes anything and its answer is not carried into the order. A client that lies
 * about the discount changes nothing.
 *
 * Requires a signed-in account for two reasons: campaign codes are bound to an account's
 * e-mail so an anonymous check could not resolve them anyway, and an open endpoint that
 * says yes/no to a guessed code is a code-guessing oracle. Attempts are also capped per
 * session so a signed-in account cannot grind through the code space either.
 */
require_once __DIR__.'/inc/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/inc/i18n.php';
require_once __DIR__.'/inc/vouchers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$reply = function (array $d) { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; };

$u = auth_user();
if (!$u) $reply(['ok' => false, 'msg' => t('Please sign in to use a voucher code.')]);

/* Guessing budget. Legitimate use is one or two tries; anything past this in a single
   session is someone walking the code space, so stop answering rather than slow down. */
$_SESSION['voucher_tries'] = (int)($_SESSION['voucher_tries'] ?? 0) + 1;
if ($_SESSION['voucher_tries'] > 25) $reply(['ok' => false, 'msg' => t('Too many attempts. Please try again later.')]);

$code     = (string)($_POST['code'] ?? $_GET['code'] ?? '');
$subtotal = (float)($_POST['subtotal'] ?? $_GET['subtotal'] ?? 0);
if (trim($code) === '') $reply(['ok' => false, 'msg' => '']);

$v = voucher_validate($code, (string)($u['email'] ?? ''), $subtotal);
if (is_string($v)) $reply(['ok' => false, 'msg' => voucher_error_text($v)]);

$discount = voucher_discount($v, $subtotal);
$reply([
    'ok'       => true,
    'code'     => $v['code'],
    'label'    => voucher_label($v),
    'discount' => $discount,
    'msg'      => sprintf(t('Voucher %s applied — %s off.'), $v['code'], eur($discount)),
]);
