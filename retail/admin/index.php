<?php
/** Dashboard: Umsatz, Besucher, offene Aufgaben, Live-Besucher, Top-Artikel, letzte Bestellungen. */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/../inc/listings.php';
require_once __DIR__ . '/_nav.php';

vr_admin_require();

$orders = vr_orders();
usort($orders, fn($a, $b) => (int)($b['created_at'] ?? 0) <=> (int)($a['created_at'] ?? 0));
$paid = array_values(array_filter($orders, fn($o) => (string)($o['status'] ?? '') === 'paid'));

$sum = function (array $rows, int $from, int $to = PHP_INT_MAX): array {
    $n = 0; $c = 0;
    foreach ($rows as $o) { $t = (int)($o['created_at'] ?? 0); if ($t >= $from && $t < $to) { $n++; $c += (int)($o['total_cents'] ?? 0); } }
    return [$n, $c];
};
$t0 = strtotime('today');
[$nToday, $cToday] = $sum($paid, $t0);
[$n7, $c7]   = $sum($paid, time() - 7 * 86400);
[$n7p, $c7p] = $sum($paid, time() - 14 * 86400, time() - 7 * 86400);
[$n30, $c30] = $sum($paid, time() - 30 * 86400);
[$n30p, $c30p] = $sum($paid, time() - 60 * 86400, time() - 30 * 86400);
$trend = fn(int $now, int $prev) => $prev > 0 ? (int)round(($now - $prev) * 100 / $prev) : null;

$st  = vr_stats_range(30);
$st7 = vr_stats_range(7);
$live = vr_stats_live(300);
$todayS = vr_stats_day(gmdate('Y-m-d'));
$conv = $st['uv'] > 0 ? round($n30 * 100 / $st['uv'], 2) : 0;

// Günlük seriler (30 gün): ciro ve ziyaretçi
$labels = []; $revSeries = []; $uvSeries = []; $ordSeries = [];
foreach ($st['days'] as $day => $d) {
    $labels[] = date('d.m.', strtotime($day));
    $uvSeries[$day] = $d['uv'];
    $revSeries[$day] = 0; $ordSeries[$day] = 0;
}
foreach ($paid as $o) {
    $day = gmdate('Y-m-d', (int)$o['created_at']);
    if (isset($revSeries[$day])) { $revSeries[$day] += (int)$o['total_cents']; $ordSeries[$day]++; }
}

// Yapılacaklar
$toDo = [];
$openShip = array_values(array_filter($paid, fn($o) => empty($o['shipped_at'])));
if ($openShip) $toDo[] = [count($openShip) . ' bezahlte Bestellung(en) warten auf Versand', vr_url('admin/orders.php', ['f' => 'open'])];
$review = array_values(array_filter(vr_listings_all(), fn($r) => (string)($r['status'] ?? 'review') === 'review'));
if ($review) $toDo[] = [count($review) . ' Angebot(e) warten auf Prüfung', vr_url('admin/listings.php')];
$payout = array_values(array_filter($paid, fn($o) => !empty($o['payout_pending'])));
if ($payout) $toDo[] = [count($payout) . ' Auszahlung(en) offen', vr_url('admin/orders.php', ['f' => 'payout'])];
$msgs = array_filter((array)vr_store_read('retail-messages.json', []), fn($m) => is_array($m) && empty($m['done']));
$priv = array_filter((array)vr_store_read('retail-private-requests.json', []), fn($m) => is_array($m) && empty($m['done']));
if ($msgs || $priv) $toDo[] = [(count($msgs) + count($priv)) . ' unbeantwortete Nachricht(en)', vr_url('admin/messages.php')];
$unver = array_filter(vr_customers(), fn($c) => empty($c['verified']) && (int)($c['created_at'] ?? 0) < time() - 2 * 86400);
if (!vr_stripe_ready()) $toDo[] = ['Stripe ist nicht konfiguriert — keine Zahlung möglich', vr_url('admin/system.php')];
$deal = vr_flash_deal();

// Top ürünler (30 gün görüntülenme) + satış
$soldQty = [];
foreach ($paid as $o) if ((int)$o['created_at'] >= time() - 30 * 86400) foreach ((array)$o['lines'] as $l) $soldQty[(string)($l['pid'] ?? '')] = ($soldQty[(string)($l['pid'] ?? '')] ?? 0) + (int)($l['qty'] ?? 1);
arsort($soldQty);

$wd = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'][(int)date('w')];
$mo = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'][(int)date('n')];
vr_admin_head('Dashboard', ['sub' => $wd . ', ' . date('j') . '. ' . $mo . ' ' . date('Y') . ' · Stand ' . date('H:i')]);
?>
<?php vr_admin_stats([
  'Umsatz heute'    => [vr_money($cToday), $nToday . ' Bestellung(en)'],
  'Umsatz 7 Tage'   => [vr_money($c7), $n7 . ' Bestellung(en)', $trend($c7, $c7p)],
  'Umsatz 30 Tage'  => [vr_money($c30), $n30 . ' Bestellung(en)', $trend($c30, $c30p)],
  'Ø Bestellwert'   => [$n30 ? vr_money(intdiv($c30, $n30)) : '—', '30 Tage'],
]); ?>
<?php vr_admin_stats([
  'Besucher heute'  => [number_format($todayS['uv'], 0, ',', '.'), $todayS['pv'] . ' Seitenaufrufe'],
  'Besucher 7 Tage' => [number_format($st7['uv'], 0, ',', '.'), number_format($st7['pv'], 0, ',', '.') . ' Seitenaufrufe'],
  'Besucher 30 Tage'=> [number_format($st['uv'], 0, ',', '.'), number_format($st['ips'], 0, ',', '.') . ' IP-Adressen'],
  'Konversion 30 T.'=> [str_replace('.', ',', (string)$conv) . ' %', 'Bestellungen je Besucher'],
]); ?>

<div class="agrid agrid--21">
  <?php vr_admin_card_open('Umsatz & Besucher — 30 Tage', '<a href="' . h(vr_url('admin/stats.php')) . '">Statistik →</a>'); ?>
    <?php vr_admin_chart(['Umsatz' => $revSeries], $labels, ['money' => true, 'h' => 190]); ?>
    <?php vr_admin_chart(['Besucher' => $uvSeries, 'Bestellungen' => $ordSeries], $labels, ['h' => 160]); ?>
  <?php vr_admin_card_close(); ?>

  <div class="agrid">
    <?php vr_admin_card_open('Zu erledigen'); ?>
      <?php if (!$toDo): ?><p class="asmall">Alles erledigt. <?= vr_aicon('check', 14) ?></p><?php else: ?>
        <ul class="atl"><?php foreach ($toDo as [$txt, $url]): ?><li class="is-on"><a href="<?= h($url) ?>"><?= h($txt) ?></a></li><?php endforeach; ?></ul>
      <?php endif; ?>
    <?php vr_admin_card_close(); ?>

    <?php vr_admin_card_open('Gerade online · ' . count($live), '<a href="' . h(vr_url('admin/visitors.php', ['f' => 'live'])) . '">alle →</a>'); ?>
      <?php if (!$live): ?><p class="asmall">Niemand in den letzten 5 Minuten.</p><?php else: ?>
        <ul class="alive">
          <?php foreach (array_slice($live, 0, 8, true) as $v): ?>
            <li><?= vr_admin_flag((string)$v['cc']) ?>
                <span><a class="amono" href="<?= h(vr_url('admin/visitors.php', ['ip' => $v['ip']])) ?>"><?= h($v['ip']) ?></a><small><?= h($v['p'] . (($v['q']['id'] ?? '') !== '' ? ' · ' . $v['q']['id'] : (($v['q']['brand'] ?? '') !== '' ? ' · ' . $v['q']['brand'] : ''))) ?></small></span>
                <small><?= h($v['d']) ?> · <?= h(vr_admin_ago((int)$v['last'])) ?></small></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    <?php vr_admin_card_close(); ?>

    <?php if ($deal && ($dp = vr_product($deal['id']))): ?>
      <?php vr_admin_card_open('48h-Aktion läuft', '<a href="' . h(vr_url('admin/deal.php')) . '">bearbeiten →</a>'); ?>
        <p style="margin:0;font-size:13.5px"><b><?= h($dp['brand'] . ' ' . vr_card_name($dp)) ?></b><br>
          <span class="asmall"><?= h(vr_money((int)$dp['price_cents'])) ?> statt <?= h(vr_money((int)$dp['rrp_cents'])) ?> · endet <?= h(vr_date($deal['ends_at'], true)) ?></span></p>
      <?php vr_admin_card_close(); ?>
    <?php endif; ?>
  </div>
</div>

<div class="agrid agrid--3">
  <?php vr_admin_card_open('Meistgesehene Artikel · 30 Tage'); ?>
    <?php vr_admin_bars($st['prod'], 8, fn($k) => vr_url('admin/product.php', ['id' => $k]), function ($k) { $p = vr_product($k); return h($p ? $p['brand'] . ' · ' . vr_card_name($p) : $k); }); ?>
  <?php vr_admin_card_close(); ?>
  <?php vr_admin_card_open('Meistverkauft · 30 Tage'); ?>
    <?php vr_admin_bars($soldQty, 8, fn($k) => vr_url('admin/product.php', ['id' => $k]), function ($k) { $p = vr_product($k); return h($p ? $p['brand'] . ' · ' . vr_card_name($p) : $k); }); ?>
  <?php vr_admin_card_close(); ?>
  <?php vr_admin_card_open('Länder · 30 Tage'); ?>
    <?php vr_admin_bars($st['cc'], 8, fn($k) => vr_url('admin/visitors.php', ['cc' => $k]), fn($k) => vr_admin_flag($k) . ' ' . h($k === '??' ? 'unbekannt' : vr_country_name($k))); ?>
  <?php vr_admin_card_close(); ?>
</div>

<?php vr_admin_card_open('Letzte Bestellungen', '<a href="' . h(vr_url('admin/orders.php')) . '">alle →</a>', 'acard--pad0'); ?>
  <?php if (!$orders): ?><p class="asmall" style="padding:16px 18px">Noch keine Bestellungen.</p><?php else: ?>
  <div class="tablewrap"><table class="table">
    <thead><tr><th>Nummer</th><th>Datum</th><th>Kunde</th><th>Land</th><th>Status</th><th class="anum">Summe</th></tr></thead>
    <tbody>
    <?php foreach (array_slice($orders, 0, 10) as $o): ?>
      <tr>
        <td><a href="<?= h(vr_url('admin/order.php', ['id' => $o['id']])) ?>"><?= h((string)$o['number']) ?></a></td>
        <td><?= h(vr_date((int)$o['created_at'], true)) ?></td>
        <td><?= h((string)($o['customer']['name'] ?? '') ?: (string)($o['customer']['email'] ?? '—')) ?></td>
        <td><?= vr_admin_flag((string)($o['ship_country'] ?? '')) ?></td>
        <td><?= vr_admin_order_pill($o) ?></td>
        <td class="anum"><?= h(vr_money((int)$o['total_cents'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
<?php vr_admin_card_close(); ?>
<?php vr_admin_foot(); ?>
