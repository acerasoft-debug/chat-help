<?php
/**
 * Globale Suche (Topbar): Bestellnummer, E-Mail, Name, Artikel, SKU, IP,
 * Gutscheincode, Nachricht. Ein Treffer → direkt dorthin.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/../inc/customers.php';
require_once __DIR__ . '/../inc/vouchers.php';
require_once __DIR__ . '/_nav.php';

vr_admin_require();
$q = trim((string)($_GET['q'] ?? ''));
$lq = strtolower($q);
$hits = ['Bestellungen' => [], 'Kunden' => [], 'Artikel' => [], 'IP-Adressen' => [], 'Gutscheine' => [], 'Nachrichten' => []];

if ($q !== '') {
    if (filter_var($q, FILTER_VALIDATE_IP)) {
        vr_redirect('admin/visitors.php', ['ip' => $q]);
    }
    foreach (vr_orders() as $o) {
        $hay = strtolower(($o['number'] ?? '') . ' ' . ($o['customer']['email'] ?? '') . ' ' . ($o['customer']['name'] ?? '') . ' ' . ($o['tracking'] ?? '') . ' ' . ($o['stripe']['payment_intent'] ?? '') . ' ' . ($o['stripe']['session_id'] ?? '') . ' ' . ($o['id'] ?? ''));
        if (str_contains($hay, $lq)) $hits['Bestellungen'][] = [vr_url('admin/order.php', ['id' => $o['id']]), (string)$o['number'] . ' · ' . vr_money((int)$o['total_cents']), (string)($o['customer']['name'] ?? '') . ' · ' . vr_date((int)$o['created_at'], true), vr_admin_order_pill($o)];
    }
    foreach (vr_customers() as $c) {
        $hay = strtolower(($c['email'] ?? '') . ' ' . ($c['name'] ?? '') . ' ' . ($c['phone'] ?? '') . ' ' . ($c['address']['city'] ?? '') . ' ' . ($c['last_ip'] ?? '') . ' ' . ($c['created_ip'] ?? ''));
        if (str_contains($hay, $lq)) $hits['Kunden'][] = [vr_url('admin/customer.php', ['id' => $c['id']]), (string)($c['name'] ?? '') ?: (string)$c['email'], (string)$c['email'] . ' · seit ' . vr_date((int)$c['created_at']), !empty($c['verified']) ? vr_admin_pill('bestätigt', 'ok') : vr_admin_pill('unbestätigt', 'warn')];
    }
    foreach (vr_catalog() as $p) {
        $hay = strtolower($p['id'] . ' ' . $p['brand'] . ' ' . $p['name'] . ' ' . $p['sku'] . ' ' . vr_model_code($p) . ' ' . $p['cat']);
        if (str_contains($hay, $lq)) $hits['Artikel'][] = [vr_url('admin/product.php', ['id' => $p['id']]), $p['brand'] . ' · ' . vr_card_name($p), $p['id'] . ' · ' . vr_money((int)$p['price_cents']) . ' · Bestand ' . $p['stock'], ''];
        if (count($hits['Artikel']) >= 40) break;
    }
    foreach (vr_category_fix() as $id => $r) {
        if (!empty($r['hidden']) && str_contains(strtolower($id . ' ' . ($r['name'] ?? '')), $lq)) $hits['Artikel'][] = [vr_url('admin/product.php', ['id' => $id]), (string)($r['name'] ?? $id), $id, vr_admin_pill('ausgeblendet', 'muted')];
    }
    foreach (vr_stats_ips(30, true) as $ip => $r) {
        if (str_contains($ip, $lq)) $hits['IP-Adressen'][] = [vr_url('admin/visitors.php', ['ip' => $ip]), $ip, ($r['cc'] ?? '') . ' · ' . (int)($r['n'] ?? 0) . ' Aufrufe · zuletzt ' . vr_admin_ago((int)($r['last'] ?? 0)), vr_ip_blocked($ip) ? vr_admin_pill('gesperrt', 'bad') : ''];
        if (count($hits['IP-Adressen']) >= 30) break;
    }
    foreach (vr_vouchers() as $v) {
        if (str_contains(strtolower(($v['code'] ?? '') . ' ' . ($v['email'] ?? '') . ' ' . ($v['note'] ?? '')), $lq)) $hits['Gutscheine'][] = [vr_url('admin/vouchers.php'), (string)$v['code'], (int)($v['uses'] ?? 0) . ' Einlösungen', !empty($v['active']) ? vr_admin_pill('aktiv', 'ok') : vr_admin_pill('aus', 'muted')];
    }
    foreach (['retail-messages.json' => 'contact', 'retail-private-requests.json' => 'private'] as $file => $src) {
        foreach ((array)vr_store_read($file, []) as $m) {
            if (is_array($m) && str_contains(strtolower(($m['name'] ?? '') . ' ' . ($m['email'] ?? '') . ' ' . ($m['message'] ?? '') . ' ' . ($m['order'] ?? '')), $lq)) $hits['Nachrichten'][] = [vr_url('admin/messages.php', ['f' => 'all', 'q' => $q]), (string)($m['name'] ?? '') ?: (string)($m['email'] ?? ''), mb_strimwidth((string)($m['message'] ?? ''), 0, 90, '…') . ' · ' . vr_date((int)$m['at'], true), empty($m['done']) ? vr_admin_pill('offen', 'warn') : vr_admin_pill('erledigt', 'ok')];
        }
    }
    $total = array_sum(array_map('count', $hits));
    if ($total === 1) { foreach ($hits as $rows) if ($rows) { header('Location: ' . $rows[0][0], true, 303); exit; } }
}

vr_admin_head('Suche', ['sub' => $q === '' ? 'Bestellnummer, E-Mail, Name, Artikel, SKU, Modellcode, IP, Gutschein oder Nachrichtentext.' : array_sum(array_map('count', $hits)) . ' Treffer für „' . $q . '“']);
?>
<form class="afilter" method="get"><input type="search" name="q" value="<?= h($q) ?>" placeholder="Suchen …" autofocus style="min-width:min(100%,420px)"><button class="btn btn--sm" type="submit"><span>Suchen</span></button></form>
<?php if ($q !== ''): ?>
<div class="agrid agrid--2">
<?php foreach ($hits as $sec => $rows): if (!$rows) continue; ?>
  <?php vr_admin_card_open($sec, count($rows) . ' Treffer'); ?>
    <?php foreach (array_slice($rows, 0, 30) as [$url, $t, $sub, $pill]): ?>
      <div class="ahit"><?= vr_aicon('arrow', 16) ?><div><a href="<?= h($url) ?>"><?= h($t) ?></a><small><?= h($sub) ?></small></div><div><?= $pill ?></div></div>
    <?php endforeach; ?>
  <?php vr_admin_card_close(); ?>
<?php endforeach; ?>
<?php if (!array_sum(array_map('count', $hits))): ?><?php vr_admin_card_open(); ?><p class="asmall">Nichts gefunden. Bestellungen lassen sich auch über die Stripe-ID finden, Besucher über die IP.</p><?php vr_admin_card_close(); ?><?php endif; ?>
</div>
<?php endif; ?>
<?php vr_admin_foot(); ?>
