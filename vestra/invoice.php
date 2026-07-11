<?php
/**
 * VESTRA — stream a generated invoice PDF.
 * Access: the buyer who placed the order/offer, the seller who issued the
 * invoice, or an authenticated admin session. Never publicly reachable —
 * data/invoices/ itself is also web-blocked, this is the only legal path in.
 */
require __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/auth.php';
require_once __DIR__.'/inc/invoice.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$ref       = preg_replace('/[^A-Za-z0-9_-]/', '', $_GET['ref'] ?? '');
$sellerKey = preg_replace('/[^A-Za-z0-9_-]/', '', $_GET['seller'] ?? '');
if ($ref === '' || $sellerKey === '') { http_response_code(404); exit('Not found'); }

$path = vestra_invoice_file($ref, $sellerKey);
if (!is_file($path)) { http_response_code(404); exit('Not found'); }

$me = auth_user();
$allowed = false;
if ($me && ($me['type'] ?? '') === 'seller' && $me['id'] === $sellerKey) $allowed = true;
if ($me && ($me['type'] ?? '') === 'buyer') {
    foreach (vestra_read_csv('orders.csv') as $row) {
        if (($row['ref'] ?? '') === $ref && strtolower($row['email'] ?? '') === strtolower($me['email'] ?? '')) { $allowed = true; break; }
    }
    if (!$allowed) {
        foreach (vestra_read_csv('offers.csv') as $row) {
            if (($row['ref'] ?? '') === $ref && strtolower($row['email'] ?? '') === strtolower($me['email'] ?? '')) { $allowed = true; break; }
        }
    }
    if (!$allowed) {
        // Sourcing-request invoices are keyed by the accepted request-offer's own ref; the
        // buyer's email lives on the parent request row, not on the offer row itself.
        foreach (vestra_read_csv('request_offers.csv') as $row) {
            if (($row['ref'] ?? '') !== $ref) continue;
            foreach (vestra_read_csv('requests.csv') as $req) {
                if (($req['ref'] ?? '') === ($row['request_ref'] ?? '') && strtolower($req['email'] ?? '') === strtolower($me['email'] ?? '')) { $allowed = true; break 2; }
            }
        }
    }
}
if (!empty($_SESSION['vadmin'])) $allowed = true;
if (!$allowed) { http_response_code(403); exit('Forbidden'); }

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="'.$ref.'-invoice.pdf"');
header('Content-Length: '.filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
