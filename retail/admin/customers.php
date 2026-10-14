<?php
/** Kundenkonten: Suche, Status, Bestellungen, Umsatz, letzte Anmeldung, IP; CSV. */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/../inc/customers.php';
require_once __DIR__ . '/_nav.php';

vr_admin_require();

$q = trim((string)($_GET['q'] ?? ''));
$f = (string)($_GET['f'] ?? 'all');
$rows = vr_customers();
usort($rows, fn($a, $b) => (int)($b['created_at'] ?? 0) <=> (int)($a['created_at'] ?? 0));

$byMail = [];
foreach (vr_orders() as $o) {
    $e = strtolower((string)($o['customer']['email'] ?? ''));
    if ($e === '' || (string)($o['status'] ?? '') !== 'paid') continue;
    $byMail[$e] = ['n' => ($byMail[$e]['n'] ?? 0) + 1, 'c' => ($byMail[$e]['c'] ?? 0) + (int)$o['total_cents'], 'last' => max($byMail[$e]['last'] ?? 0, (int)$o['created_at'])];
}
$members = [];
foreach ((array)vr_store_read('retail-members.json', []) as $m) if (is_array($m) && !empty($m['confirmed_at'])) $members[strtolower((string)$m['email'])] = 1;

$rows = array_values(array_filter($rows, function ($c) use ($q, $f, $byMail) {
    $e = strtolower((string)$c['email']);
    if ($f === 'verified' && empty($c['verified'])) return false;
    if ($f === 'unverified' && !empty($c['verified'])) return false;
    if ($f === 'buyers' && empty($byMail[$e])) return false;
    if ($q !== '') {
        $hay = strtolower($e . ' ' . ($c['name'] ?? '') . ' ' . ($c['phone'] ?? '') . ' ' . ($c['address']['city'] ?? '') . ' ' . ($c['last_ip'] ?? '') . ' ' . ($c['created_ip'] ?? ''));
        if (!str_contains($hay, strtolower($q))) return false;
    }
    return true;
}));

if (($_GET['export'] ?? '') === 'csv') {
    vr_admin_csv('kunden', ['email', 'name', 'telefon', 'bestaetigt', 'sprache', 'strasse', 'plz', 'ort', 'land', 'registriert', 'letzte_anmeldung', 'letzte_ip', 'bestellungen', 'umsatz', 'newsletter'],
        array_map(fn($c) => [$c['email'], $c['name'] ?? '', $c['phone'] ?? '', !empty($c['verified']) ? 'ja' : 'nein', $c['lang'] ?? '', $c['address']['street'] ?? '', $c['address']['zip'] ?? '', $c['address']['city'] ?? '', $c['address']['country'] ?? '',
            date('Y-m-d', (int)$c['created_at']), !empty($c['last_login']) ? date('Y-m-d H:i', (int)$c['last_login']) : '', $c['last_ip'] ?? '', $byMail[strtolower($c['email'])]['n'] ?? 0, number_format(($byMail[strtolower($c['email'])]['c'] ?? 0) / 100, 2, ',', ''), isset($members[strtolower($c['email'])]) ? 'ja' : ''], $rows));
}

$all = vr_customers();
$tabs = ['all' => ['Alle', count($all)], 'verified' => ['Bestätigt', count(array_filter($all, fn($c) => !empty($c['verified'])))], 'unverified' => ['Unbestätigt', count(array_filter($all, fn($c) => empty($c['verified'])))], 'buyers' => ['Mit Bestellung', count(array_filter($all, fn($c) => !empty($byMail[strtolower($c['email'])])))]];
[$page, $pag] = vr_admin_paginate($rows, 50);
vr_admin_head('Kunden', ['sub' => count($rows) . ' Konto/Konten · Gäste bestellen ohne Konto und stehen nur bei den Bestellungen.',
    'actions' => '<a class="btn btn--ghost btn--sm" href="' . h(vr_url('admin/customers.php', ['f' => $f, 'q' => $q, 'export' => 'csv'])) . '"><span>CSV exportieren</span></a>']);
vr_admin_tabs($tabs, $f, 'customers.php', 'f', array_filter(['q' => $q]));
?>
<form class="afilter" method="get"><input type="hidden" name="f" value="<?= h($f) ?>">
  <input type="search" name="q" value="<?= h($q) ?>" placeholder="E-Mail, Name, Ort, Telefon, IP"><button class="btn btn--ghost btn--sm" type="submit"><span>Suchen</span></button></form>
<?php vr_admin_card_open('', '', 'acard--pad0'); ?>
<div class="tablewrap"><table class="table">
  <thead><tr><th>Kunde</th><th>Status</th><th>Ort</th><th class="anum">Bestellungen</th><th class="anum">Umsatz</th><th>Letzte Anmeldung</th><th>IP</th><th>Seit</th></tr></thead>
  <tbody>
  <?php foreach ($page as $c): $e = strtolower((string)$c['email']); $b = $byMail[$e] ?? null; ?>
    <tr>
      <td><a href="<?= h(vr_url('admin/customer.php', ['id' => $c['id']])) ?>"><?= h((string)($c['name'] ?? '') ?: $c['email']) ?></a><br><span class="asmall"><?= h($c['email']) ?></span></td>
      <td><?= !empty($c['verified']) ? vr_admin_pill('bestätigt', 'ok') : vr_admin_pill('unbestätigt', 'warn') ?><?= isset($members[$e]) ? ' ' . vr_admin_pill('Newsletter', 'muted') : '' ?></td>
      <td class="asmall"><?= h(trim((string)($c['address']['zip'] ?? '') . ' ' . (string)($c['address']['city'] ?? ''))) ?> <?= ($c['address']['country'] ?? '') !== '' ? vr_admin_flag((string)$c['address']['country']) : '' ?></td>
      <td class="anum"><?= (int)($b['n'] ?? 0) ?></td>
      <td class="anum"><?= $b ? h(vr_money($b['c'])) : '—' ?></td>
      <td class="asmall"><?= !empty($c['last_login']) ? h(vr_admin_ago((int)$c['last_login'])) : '—' ?></td>
      <td><?= ($c['last_ip'] ?? '') !== '' ? '<a class="amono asmall" href="' . h(vr_url('admin/visitors.php', ['ip' => $c['last_ip']])) . '">' . h((string)$c['last_ip']) . '</a>' : '<span class="asmall">—</span>' ?></td>
      <td class="asmall"><?= h(vr_date((int)($c['created_at'] ?? 0))) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$page): ?><tr><td colspan="8" class="asmall">Keine Konten.</td></tr><?php endif; ?>
  </tbody></table></div>
<?php vr_admin_card_close(); echo $pag; ?>
<?php vr_admin_foot(); ?>
