<?php
/** Bestellungen: Filter, Suche, Zeitraum, Summen, CSV-Export. */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/_nav.php';

vr_admin_require();

$f    = (string)($_GET['f'] ?? 'all');
$q    = trim((string)($_GET['q'] ?? ''));
$cc   = strtoupper(trim((string)($_GET['cc'] ?? '')));
$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));
$fromTs = $from !== '' ? (strtotime($from . ' 00:00:00') ?: 0) : 0;
$toTs   = $to !== '' ? (strtotime($to . ' 23:59:59') ?: PHP_INT_MAX) : PHP_INT_MAX;

// Kripto (OxaPay) yedeği: webhook gelmemiş bekleyen faturaları liste açılırken
// OxaPay'e sor — en çok 5 dakikada bir, 2 dk'dan eski olanlar; cron gerekmez.
require_once __DIR__ . '/../inc/oxapay.php';
vr_oxapay_sweep(300, 120);

$all = vr_orders();
usort($all, fn($a, $b) => (int)($b['created_at'] ?? 0) <=> (int)($a['created_at'] ?? 0));
$count = fn(string $k) => count(array_filter($all, fn($o) => vr_admin_order_match($o, $k)));

function vr_admin_order_match(array $o, string $f): bool
{
    $st = (string)($o['status'] ?? '');
    return match ($f) {
        'open'      => $st === 'paid' && empty($o['shipped_at']),
        'shipped'   => $st === 'paid' && !empty($o['shipped_at']),
        'paid'      => $st === 'paid',
        'pending'   => $st === 'pending',
        'cancelled' => in_array($st, ['cancelled', 'failed', 'refunded'], true),
        'payout'    => !empty($o['payout_pending']),
        default     => true,
    };
}

$rows = array_values(array_filter($all, function ($o) use ($f, $q, $cc, $fromTs, $toTs) {
    if (!vr_admin_order_match($o, $f)) return false;
    $t = (int)($o['created_at'] ?? 0);
    if ($t < $fromTs || $t > $toTs) return false;
    if ($cc !== '' && strtoupper((string)($o['ship_country'] ?? '')) !== $cc) return false;
    if ($q !== '') {
        $hay = strtolower(($o['number'] ?? '') . ' ' . ($o['customer']['email'] ?? '') . ' ' . ($o['customer']['name'] ?? '') . ' ' . ($o['tracking'] ?? '') . ' ' . ($o['client']['ip'] ?? '') . ' ' . ($o['stripe']['payment_intent'] ?? ''));
        foreach ((array)($o['lines'] ?? []) as $l) $hay .= ' ' . strtolower((string)($l['name'] ?? '') . ' ' . (string)($l['pid'] ?? ''));
        if (!str_contains($hay, strtolower($q))) return false;
    }
    return true;
}));

if (($_GET['export'] ?? '') === 'csv') {
    vr_admin_csv('bestellungen', ['nummer', 'datum', 'status', 'versendet', 'kunde', 'email', 'land', 'artikel', 'zwischensumme', 'versand', 'rabatt', 'gesamt', 'mwst', 'provision', 'gutschein', 'ip', 'tracking'],
        array_map(fn($o) => [
            $o['number'], date('Y-m-d H:i', (int)$o['created_at']), $o['status'], !empty($o['shipped_at']) ? date('Y-m-d', (int)$o['shipped_at']) : '',
            (string)($o['customer']['name'] ?? ''), (string)($o['customer']['email'] ?? ''), (string)($o['ship_country'] ?? ''),
            implode(' | ', array_map(fn($l) => ($l['qty'] ?? 1) . '× ' . ($l['brand'] ?? '') . ' ' . ($l['name'] ?? '') . ' ' . ($l['size'] ?? ''), (array)$o['lines'])),
            number_format((int)$o['subtotal_cents'] / 100, 2, ',', ''), number_format((int)$o['ship_cents'] / 100, 2, ',', ''), number_format((int)($o['discount_cents'] ?? 0) / 100, 2, ',', ''),
            number_format((int)$o['total_cents'] / 100, 2, ',', ''), number_format((int)($o['vat_cents'] ?? 0) / 100, 2, ',', ''), number_format((int)($o['commission_cents'] ?? 0) / 100, 2, ',', ''),
            (string)($o['voucher_code'] ?? ''), (string)($o['client']['ip'] ?? ''), (string)($o['tracking'] ?? ''),
        ], $rows));
}

$sumTotal = 0; $sumPaid = 0;
foreach ($rows as $o) { $sumTotal += (int)$o['total_cents']; if ((string)$o['status'] === 'paid') $sumPaid += (int)$o['total_cents']; }
$tabs = ['all' => ['Alle', count($all)], 'open' => ['Offen', $count('open')], 'shipped' => ['Versendet', $count('shipped')], 'paid' => ['Bezahlt', $count('paid')],
         'pending' => ['Unbezahlt', $count('pending')], 'cancelled' => ['Storniert', $count('cancelled')], 'payout' => ['Auszahlung offen', $count('payout')]];
$keep = array_filter(['q' => $q, 'cc' => $cc, 'from' => $from, 'to' => $to]);
[$page, $pag] = vr_admin_paginate($rows, 50);
$countries = [];
foreach ($all as $o) $countries[strtoupper((string)($o['ship_country'] ?? ''))] = 1;

vr_admin_head('Bestellungen', ['sub' => count($rows) . ' Bestellung(en) · Summe ' . vr_money($sumTotal) . ' · davon bezahlt ' . vr_money($sumPaid),
    'actions' => '<a class="btn btn--ghost btn--sm" href="' . h(vr_url('admin/orders.php', $keep + ['f' => $f, 'export' => 'csv'])) . '"><span>CSV exportieren</span></a>']);
vr_admin_tabs($tabs, $f, 'orders.php', 'f', $keep);
?>
<form class="afilter" method="get">
  <input type="hidden" name="f" value="<?= h($f) ?>">
  <input type="search" name="q" value="<?= h($q) ?>" placeholder="Nummer, Name, E-Mail, Artikel, IP, Tracking">
  <input type="date" name="from" value="<?= h($from) ?>"> <span class="asmall">bis</span> <input type="date" name="to" value="<?= h($to) ?>">
  <select name="cc"><option value="">Alle Länder</option><?php foreach (array_keys($countries) as $c): if ($c === '') continue; ?><option value="<?= h($c) ?>"<?= $cc === $c ? ' selected' : '' ?>><?= h($c . ' · ' . vr_country_name($c)) ?></option><?php endforeach; ?></select>
  <button class="btn btn--ghost btn--sm" type="submit"><span>Filtern</span></button>
  <?php if ($keep): ?><a class="asmall" href="<?= h(vr_url('admin/orders.php', ['f' => $f])) ?>">zurücksetzen</a><?php endif; ?>
</form>
<?php vr_admin_card_open('', '', 'acard--pad0'); ?>
<div class="tablewrap"><table class="table">
  <thead><tr><th>Nummer</th><th>Datum</th><th>Kunde</th><th>Land</th><th>Artikel</th><th>Status</th><th>Versand</th><th class="anum">Summe</th><th>IP</th></tr></thead>
  <tbody>
  <?php foreach ($page as $o): $n = array_sum(array_map(fn($l) => (int)($l['qty'] ?? 1), (array)$o['lines'])); ?>
    <tr<?= in_array((string)$o['status'], ['pending', 'failed', 'cancelled'], true) ? ' class="is-dim"' : '' ?>>
      <td><a href="<?= h(vr_url('admin/order.php', ['id' => $o['id']])) ?>"><?= h((string)$o['number']) ?></a></td>
      <td class="asmall"><?= h(vr_date((int)$o['created_at'], true)) ?></td>
      <td><?= h((string)($o['customer']['name'] ?? '')) ?><br><span class="asmall"><?= h((string)($o['customer']['email'] ?? '—')) ?></span></td>
      <td><?= vr_admin_flag((string)($o['ship_country'] ?? '')) ?></td>
      <td class="asmall"><?= $n ?> Art. · <?= h(mb_strimwidth(implode(', ', array_map(fn($l) => (string)($l['brand'] ?? ''), (array)$o['lines'])), 0, 40, '…')) ?></td>
      <td><?= vr_admin_order_pill($o) ?></td>
      <td class="asmall"><?= !empty($o['shipped_at']) ? h(vr_date((int)$o['shipped_at'])) . (($o['carrier'] ?? '') !== '' ? '<br>' . h((string)$o['carrier']) : '') : '—' ?></td>
      <td class="anum"><?= h(vr_money((int)$o['total_cents'])) ?></td>
      <td><?php $ip = (string)($o['client']['ip'] ?? ''); echo $ip !== '' ? '<a class="amono asmall" href="' . h(vr_url('admin/visitors.php', ['ip' => $ip])) . '">' . h($ip) . '</a>' : '<span class="asmall">—</span>'; ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$page): ?><tr><td colspan="9" class="asmall">Nichts gefunden.</td></tr><?php endif; ?>
  </tbody></table></div>
<?php vr_admin_card_close(); echo $pag; ?>
<?php vr_admin_foot(); ?>
