<?php
/**
 * Besucher & IP-Adressen: Live-Ansicht, IP-Liste, Sperrliste, Verlauf einer Adresse.
 * Rohdaten liegen VR_STATS_KEEP_DAYS Tage vor (danach nur noch Tagessummen ohne IP).
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/../inc/customers.php';
require_once __DIR__ . '/_nav.php';

$who = vr_admin_require();

// Üst çubuktaki canlı sayaç (json=1) ve canlı sayfanın 10 sn'lik akışı
// (json=live: tablo satırları + harita, sunucuda çizilmiş ve kaçışlanmış HTML).
if (($_GET['json'] ?? '') !== '') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $live = vr_stats_live(300);
    $out  = ['n' => count($live)];
    if ($_GET['json'] === 'live') {
        $out['rows'] = vr_admin_live_rows($live);
        $out['side'] = vr_admin_live_side($live);
        ob_start(); vr_admin_worldmap(vr_admin_live_cc($live), fn($k) => vr_url('admin/visitors.php', ['cc' => $k]), 'online');
        $out['map']  = (string)ob_get_clean();
        $out['at']   = date('H:i:s');
    }
    echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();
    $ip = trim((string)($_POST['ip'] ?? ''));
    if (($_POST['action'] ?? '') === 'block' && $ip !== '') {
        vr_ip_block($ip, (string)($_POST['note'] ?? ''), $who);
        vr_admin_audit('ip_block', ['ip' => $ip, 'note' => (string)($_POST['note'] ?? '')]);
        vr_flash('Adresse gesperrt: ' . $ip, 'ok');
    } elseif (($_POST['action'] ?? '') === 'unblock' && $ip !== '') {
        vr_ip_unblock($ip);
        vr_admin_audit('ip_unblock', ['ip' => $ip]);
        vr_flash('Sperre aufgehoben: ' . $ip, 'ok');
    }
    vr_redirect('admin/visitors.php', array_filter(['f' => (string)($_GET['f'] ?? ''), 'ip' => (string)($_GET['ip'] ?? '')]));
}

$f    = (string)($_GET['f'] ?? 'list');
$ipQ  = trim((string)($_GET['ip'] ?? ''));
$cc   = strtoupper(trim((string)($_GET['cc'] ?? '')));
$days = (int)($_GET['d'] ?? 7);
if (!in_array($days, [1, 7, 30, 60], true)) $days = 7;
$q    = trim((string)($_GET['q'] ?? ''));
$blocked = vr_ip_blocklist();
$blockedMap = array_column($blocked, null, 'ip');

// ---------------------------------------------------------------- tek IP
if ($ipQ !== '') {
    $trail = vr_stats_trail($ipQ, '', VR_STATS_KEEP_DAYS, 1500);
    $cust = [];
    foreach ($trail as $r) if (($r['u'] ?? '') !== '') $cust[$r['u']] = 1;
    foreach (vr_customers() as $c) if (($c['last_ip'] ?? '') === $ipQ || ($c['created_ip'] ?? '') === $ipQ) $cust[$c['id']] = 1;
    $ordersIp = array_values(array_filter(vr_orders(), fn($o) => (string)($o['client']['ip'] ?? '') === $ipQ));
    $first = $trail ? end($trail)['t'] : 0; $last = $trail ? $trail[0]['t'] : 0;
    $pages = []; $sids = []; $uas = [];
    foreach ($trail as $r) { $pages[$r['p']] = ($pages[$r['p']] ?? 0) + 1; if ($r['s']) $sids[$r['s']] = 1; $uas[$r['ua'] ?? ''] = 1; }
    $ccIp = $trail ? (string)$trail[0]['cc'] : vr_geoip_lookup($ipQ);
    $isBlocked = isset($blockedMap[$ipQ]);
    $act = $isBlocked
        ? vr_admin_button('unblock', ['ip' => $ipQ], 'Sperre aufheben', 'btn btn--ghost btn--sm')
        : '<form method="post" class="ainline afilter" data-confirm="IP-Adresse ' . h($ipQ) . ' sperren? Der Shop antwortet dann mit 403.">' . vr_csrf_field() . '<input type="hidden" name="action" value="block"><input type="hidden" name="ip" value="' . h($ipQ) . '"><input type="text" name="note" placeholder="Grund (optional)"><button class="btn btn--danger btn--sm" type="submit"><span>Sperren</span></button></form>';
    vr_admin_head($ipQ, ['sub' => 'IP-Adresse · ' . ($ccIp !== '' ? vr_country_name($ccIp) : 'Land unbekannt'), 'back' => [vr_url('admin/visitors.php'), 'Besucher & IPs'], 'actions' => $act, 'nav' => 'visitors.php']);
    vr_admin_stats([
        'Land'        => [$ccIp !== '' ? $ccIp : '—', $ccIp !== '' ? vr_country_name($ccIp) : ''],
        'Seitenaufrufe' => [count($trail), count($sids) . ' Sitzung(en)'],
        'Zuerst'      => [$first ? vr_date($first, true) : '—', ''],
        'Zuletzt'     => [$last ? vr_admin_ago($last) : '—', $last ? vr_date($last, true) : ''],
    ]);
    echo '<div class="agrid agrid--12">';
    vr_admin_card_open('Status');
    echo '<dl class="adl"><dt>Sperre</dt><dd>' . ($isBlocked ? '<span class="abad">gesperrt</span> · ' . h((string)($blockedMap[$ipQ]['note'] ?? '')) . ' · ' . h(vr_date((int)$blockedMap[$ipQ]['at'])) : 'nicht gesperrt') . '</dd>';
    echo '<dt>Kundenkonten</dt><dd>';
    if ($cust) { foreach (array_keys($cust) as $cid) { $c = vr_customer($cid); echo $c ? '<a href="' . h(vr_url('admin/customer.php', ['id' => $cid])) . '">' . h($c['email']) . '</a><br>' : h($cid) . '<br>'; } } else echo '—';
    echo '</dd><dt>Bestellungen</dt><dd>';
    if ($ordersIp) { foreach ($ordersIp as $o) echo '<a href="' . h(vr_url('admin/order.php', ['id' => $o['id']])) . '">' . h($o['number']) . '</a> · ' . h(vr_money((int)$o['total_cents'])) . ' · ' . vr_admin_order_pill($o) . '<br>'; } else echo '—';
    echo '</dd><dt>Browser</dt><dd>';
    foreach (array_keys($uas) as $ua) echo '<div class="amono asmall" style="margin:0 0 8px">' . h($ua !== '' ? $ua : '(leer)') . '</div>';
    echo '</dd></dl>';
    vr_admin_card_close();
    vr_admin_card_open('Seiten');
    vr_admin_bars($pages, 12, null, fn($k) => h($k));
    vr_admin_card_close();
    echo '</div>';
    vr_admin_card_open('Verlauf (neueste zuerst)', count($trail) . ' Einträge', 'acard--pad0');
    echo '<div class="tablewrap"><table class="table"><thead><tr><th>Zeit</th><th>Seite</th><th>Detail</th><th>Herkunft</th><th>Gerät</th><th>Sprache</th><th>Bot</th></tr></thead><tbody>';
    foreach (array_slice($trail, 0, 400) as $r) {
        $det = implode(' · ', array_map(fn($k, $v) => $k . '=' . $v, array_keys($r['q'] ?? []), $r['q'] ?? []));
        echo '<tr' . (!empty($r['bot']) ? ' class="is-dim"' : '') . '><td class="amono">' . h(vr_date((int)$r['t'], true)) . ':' . date('s', (int)$r['t']) . '</td><td>' . h($r['p']) . '</td><td class="asmall">' . h($det) . '</td><td class="asmall">' . h($r['r'] ?: '—') . '</td><td class="asmall">' . h($r['d'] . ' · ' . $r['b'] . ' · ' . $r['o']) . '</td><td>' . h(strtoupper($r['l'])) . '</td><td>' . (!empty($r['bot']) ? 'ja' : '') . '</td></tr>';
    }
    echo '</tbody></table></div>';
    vr_admin_card_close();
    vr_admin_foot();
    exit;
}

// ----------------------------------------------------------- listeler
$tabs = ['live' => 'Gerade online', 'list' => 'IP-Adressen', 'blocked' => ['Gesperrt', count($blocked)], 'bots' => 'Bots'];
if (!isset($tabs[$f])) $f = 'list';
$csv = '<a class="btn btn--ghost btn--sm" href="' . h(vr_url('admin/visitors.php', ['f' => $f, 'd' => $days, 'export' => 'csv'])) . '"><span>CSV</span></a>';

if ($f === 'live') {
    $live = vr_stats_live(300);
    vr_admin_head('Gerade online', ['sub' => 'Besucher der letzten 5 Minuten · aktualisiert sich alle 10 Sekunden', 'nav' => 'visitors.php']);
    // Canlı akış: admin.js 10 sn'de bir json=live çeker, tabloyu ve haritayı yeniler.
    echo '<div data-adm-livefeed="10" data-src="' . h(vr_url('admin/visitors.php', ['f' => 'live', 'json' => 'live'])) . '" hidden></div>';
    vr_admin_tabs($tabs, $f, 'visitors.php');
    echo '<div class="agrid agrid--21">';
    vr_admin_card_open('Woher gerade', '<span class="alive-state"><i class="alive-dot"></i> live · <span data-live-at>' . h(date('H:i:s')) . '</span></span>');
    echo '<div data-live-map>';
    vr_admin_worldmap(vr_admin_live_cc($live), fn($k) => vr_url('admin/visitors.php', ['cc' => $k]), 'online');
    echo '</div>';
    vr_admin_card_close();
    vr_admin_card_open('Jetzt im Shop');
    echo '<div data-live-side>' . vr_admin_live_side($live) . '</div>';
    vr_admin_card_close();
    echo '</div>';
    vr_admin_card_open('', '', 'acard--pad0');
    echo '<div class="tablewrap"><table class="table"><thead><tr><th>IP</th><th>Land</th><th>Aktuelle Seite</th><th class="anum">Seiten</th><th>Seit</th><th>Zuletzt</th><th>Gerät</th><th>Kunde</th></tr></thead><tbody data-live-rows>';
    echo vr_admin_live_rows($live);
    echo '</tbody></table></div>';
    vr_admin_card_close();
    vr_admin_foot();
    exit;
}

if ($f === 'blocked') {
    vr_admin_head('Gesperrte IP-Adressen', ['sub' => 'Gesperrte Adressen erhalten im Shop „403 Zugriff gesperrt“. Präfix mit Punkt am Ende sperrt ein Netz (z. B. 203.0.113.).', 'nav' => 'visitors.php']);
    vr_admin_tabs($tabs, $f, 'visitors.php');
    vr_admin_card_open('Adresse sperren');
    echo '<form method="post" class="afilter">' . vr_csrf_field() . '<input type="hidden" name="action" value="block"><input type="text" name="ip" placeholder="IP oder Präfix" required class="amono"><input type="text" name="note" placeholder="Grund"><button class="btn btn--sm" type="submit"><span>Sperren</span></button></form>';
    vr_admin_card_close();
    vr_admin_card_open('', '', 'acard--pad0');
    echo '<div class="tablewrap"><table class="table"><thead><tr><th>Adresse</th><th>Grund</th><th>Gesperrt am</th><th>Von</th><th></th></tr></thead><tbody>';
    foreach ($blocked as $b) echo '<tr><td><a class="amono" href="' . h(vr_url('admin/visitors.php', ['ip' => $b['ip']])) . '">' . h($b['ip']) . '</a></td><td>' . h((string)$b['note']) . '</td><td>' . h(vr_date((int)$b['at'], true)) . '</td><td>' . h((string)$b['by']) . '</td><td>' . vr_admin_button('unblock', ['ip' => $b['ip']], 'Aufheben', 'link') . '</td></tr>';
    if (!$blocked) echo '<tr><td colspan="5" class="asmall">Keine Sperren.</td></tr>';
    echo '</tbody></table></div>';
    vr_admin_card_close();
    vr_admin_foot();
    exit;
}

$ips = vr_stats_ips($days, $f === 'bots');
if ($f === 'bots') $ips = array_filter($ips, fn($x) => $x['bot'] > 0);
if ($cc !== '') $ips = array_filter($ips, fn($x) => strtoupper((string)$x['cc']) === $cc);
if ($q !== '') $ips = array_filter($ips, fn($x) => str_contains($x['ip'], $q) || str_contains(strtolower($x['ua']), strtolower($q)));
$sort = (string)($_GET['s'] ?? 'last');
if ($sort === 'hits') uasort($ips, fn($a, $b) => $b['n'] <=> $a['n']);

if (($_GET['export'] ?? '') === 'csv') {
    vr_admin_csv('besucher-ips', ['ip', 'land', 'zuerst', 'zuletzt', 'aufrufe', 'seiten', 'sitzungen', 'geraet', 'browser', 'system', 'bot_aufrufe', 'gesperrt'],
        array_map(fn($x) => [$x['ip'], $x['cc'], date('Y-m-d H:i', $x['first']), date('Y-m-d H:i', $x['last']), $x['n'], $x['pages'], $x['sids'], $x['d'], $x['b'], $x['o'], $x['bot'], isset($blockedMap[$x['ip']]) ? 'ja' : ''], array_values($ips)));
}

[$page, $pag] = vr_admin_paginate(array_values($ips), 60);
$range = vr_stats_range($days);
vr_admin_head($f === 'bots' ? 'Bots' : 'Besucher & IP-Adressen', ['sub' => count($ips) . ' Adressen in den letzten ' . $days . ' Tagen' . ($cc !== '' ? ' · ' . vr_country_name($cc) : ''), 'actions' => $csv, 'nav' => 'visitors.php']);
vr_admin_tabs($tabs, $f, 'visitors.php');
if ($f !== 'bots') {
    // Nereden geliyorlar: IP → ülke (yerel tablo). Ülkeye tıklayınca liste o ülkeye süzülür.
    echo '<div class="agrid agrid--21">';
    vr_admin_card_open('Herkunft · ' . ($days === 1 ? 'heute' : $days . ' Tage'), $cc !== '' ? '<a href="' . h(vr_url('admin/visitors.php', ['d' => $days])) . '">alle Länder</a>' : '');
    vr_admin_worldmap($range['cc'], fn($k) => vr_url('admin/visitors.php', ['d' => $days, 'cc' => $k]), 'Aufrufe');
    vr_admin_card_close();
    vr_admin_card_open('Länder');
    vr_admin_bars($range['cc'], 8, fn($k) => vr_url('admin/visitors.php', ['d' => $days, 'cc' => $k]), fn($k) => vr_admin_flag($k) . ' ' . h($k === '??' ? 'unbekannt' : vr_country_name($k)));
    vr_admin_card_close();
    echo '</div>';
}
?>
<form class="afilter" method="get">
  <input type="hidden" name="f" value="<?= h($f) ?>">
  <input type="search" name="q" value="<?= h($q) ?>" placeholder="IP oder Browser-Kennung">
  <select name="d"><?php foreach ([1 => 'Heute', 7 => '7 Tage', 30 => '30 Tage', 60 => '60 Tage'] as $k => $l): ?><option value="<?= $k ?>"<?= $days === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
  <select name="cc"><option value="">Alle Länder</option><?php foreach (array_keys($range['cc']) as $c): if ($c === '??') continue; ?><option value="<?= h($c) ?>"<?= $cc === $c ? ' selected' : '' ?>><?= h($c . ' · ' . vr_country_name($c)) ?></option><?php endforeach; ?></select>
  <select name="s"><option value="last"<?= $sort === 'last' ? ' selected' : '' ?>>Zuletzt gesehen</option><option value="hits"<?= $sort === 'hits' ? ' selected' : '' ?>>Meiste Aufrufe</option></select>
  <button class="btn btn--ghost btn--sm" type="submit"><span>Filtern</span></button>
</form>
<?php vr_admin_card_open('', '', 'acard--pad0'); ?>
<div class="tablewrap"><table class="table">
  <thead><tr><th>IP</th><th>Land</th><th>Zuletzt</th><th>Zuerst</th><th class="anum">Aufrufe</th><th class="anum">Seiten</th><th class="anum">Besuche</th><th>Gerät</th><th>Letzte Seite</th><th>Kunde</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($page as $x): ?>
    <tr<?= isset($blockedMap[$x['ip']]) ? ' class="is-dim"' : '' ?>>
      <td><a class="amono" href="<?= h(vr_url('admin/visitors.php', ['ip' => $x['ip']])) ?>"><?= h($x['ip']) ?></a><?= isset($blockedMap[$x['ip']]) ? ' ' . vr_admin_pill('gesperrt', 'bad') : '' ?></td>
      <td><?= vr_admin_flag((string)$x['cc']) ?></td>
      <td><?= h(vr_admin_ago((int)$x['last'])) ?></td>
      <td class="asmall"><?= h(vr_date((int)$x['first'])) ?></td>
      <td class="anum"><?= (int)$x['n'] ?></td>
      <td class="anum"><?= (int)$x['pages'] ?></td>
      <td class="anum"><?= (int)$x['sids'] ?></td>
      <td class="asmall"><?= h($x['d'] . ' · ' . $x['b'] . ' · ' . $x['o']) ?></td>
      <td class="asmall"><?= h($x['p']) ?></td>
      <td><?php foreach ($x['uids'] as $uid): $c = vr_customer($uid); if ($c): ?><a href="<?= h(vr_url('admin/customer.php', ['id' => $uid])) ?>"><?= h($c['email']) ?></a><?php endif; endforeach; ?></td>
      <td><?= isset($blockedMap[$x['ip']]) ? vr_admin_button('unblock', ['ip' => $x['ip']], 'Freigeben', 'link') : vr_admin_button('block', ['ip' => $x['ip'], 'note' => 'aus Besucherliste'], 'Sperren', 'link', 'IP-Adresse ' . $x['ip'] . ' sperren?') ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$page): ?><tr><td colspan="11" class="asmall">Keine Adressen im Zeitraum.</td></tr><?php endif; ?>
  </tbody></table></div>
<?php vr_admin_card_close(); echo $pag; ?>
<?php vr_admin_foot(); ?>
