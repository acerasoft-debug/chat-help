<?php
/**
 * Bekleyen kripto siparişlerini OxaPay ile eşitle (webhook yedeği) — CLI
 *   php tools/oxapay-sync.php [--root=DIR] [--min-age=120]
 *
 * Durumu "pending" olan, track_id'li kripto siparişlerini OxaPay'e sorar ve
 * webhook ile AYNI yoldan uygular (vr_oxapay_apply): paid → ödendi (stok, los,
 * kupon, satıcı payı, mailler), expired → failed. Webhook zaten geldiyse
 * sipariş pending değildir ve atlanır; idempotent.
 *
 * Cron örneği (sunucuda, 10 dakikada bir; "*" + "/" yazımı PHP yorumunu
 * kapatacağı için dakikalar açık yazıldı):
 *   0,10,20,30,40,50 * * * * php ~/maxsales_de/tools/oxapay-sync.php >> ~/data/oxapay-sync.log 2>&1
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root   = dirname(__DIR__);
$minAge = 120;                                         // kasadan henüz çıkmış siparişe sorma
foreach ($argv as $a) {
    if (str_starts_with($a, '--root='))    $root   = substr($a, 7);
    if (str_starts_with($a, '--min-age=')) $minAge = max(0, (int)substr($a, 10));
}
require_once $root . '/inc/orders.php';
require_once $root . '/inc/oxapay.php';

if (!vr_oxapay_ready()) { echo gmdate('c'), " oxapay: anahtar yapılandırılmamış\n"; exit(0); }

$checked = 0;
$changed = [];
foreach (vr_orders() as $o) {
    if (!is_array($o)) continue;
    if ((string)($o['payment_method'] ?? '') !== 'crypto' || (string)($o['status'] ?? '') !== 'pending') continue;
    if ((string)($o['oxapay']['track_id'] ?? '') === '') continue;
    if (time() - (int)($o['created_at'] ?? 0) < $minAge) continue;
    $checked++;
    $u = vr_oxapay_sync($o, 0);
    if ($u !== null && (string)($u['status'] ?? '') !== 'pending') {
        $changed[] = (string)($u['number'] ?? $u['id']) . ' → ' . (string)$u['status'];
    }
}
echo gmdate('c'), " kontrol edilen: $checked · değişen: ", count($changed),
     ($changed ? "\n  " . implode("\n  ", $changed) : ''), "\n";
