<?php
/**
 * Nachrichten: Kontaktformular (retail-messages.json) und Private-Clients-
 * Anfragen (retail-private-requests.json). Erledigt-Markierung, Antwort per
 * Mail-Link, Kunde/Bestellung verknüpft, wenn auffindbar.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/../inc/customers.php';
require_once __DIR__ . '/_nav.php';

$who = vr_admin_require();
$f = (string)($_GET['f'] ?? 'open');
$q = trim((string)($_GET['q'] ?? ''));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();
    $file = ($_POST['src'] ?? '') === 'private' ? 'retail-private-requests.json' : 'retail-messages.json';
    $at = (int)($_POST['at'] ?? 0); $mail = (string)($_POST['email'] ?? '');
    $to = ($_POST['action'] ?? '') === 'done';
    vr_store_mutate($file, function ($all) use ($at, $mail, $to, $who) {
        if (!is_array($all)) return [];
        foreach ($all as &$m) {
            if (is_array($m) && (int)($m['at'] ?? 0) === $at && (string)($m['email'] ?? '') === $mail) {
                if ($to) { $m['done'] = time(); $m['done_by'] = $who; } else { unset($m['done'], $m['done_by']); }
            }
        }
        return $all;
    });
    vr_admin_audit($to ? 'message_done' : 'message_reopen', ['at' => $at]);
    vr_flash($to ? 'Als erledigt markiert.' : 'Wieder geöffnet.', 'ok');
    vr_redirect('admin/messages.php', array_filter(['f' => $f, 'q' => $q]));
}

$subjects = ['order' => 'Bestellung', 'return' => 'Rückgabe', 'product' => 'Artikel', 'press' => 'Presse', 'partner' => 'Partner', 'other' => 'Sonstiges'];
$rows = [];
foreach ((array)vr_store_read('retail-messages.json', []) as $m) if (is_array($m)) $rows[] = $m + ['_src' => 'contact'];
foreach ((array)vr_store_read('retail-private-requests.json', []) as $m) if (is_array($m)) $rows[] = $m + ['_src' => 'private'];
usort($rows, fn($a, $b) => (int)($b['at'] ?? 0) <=> (int)($a['at'] ?? 0));
$all = $rows;
$rows = array_values(array_filter($rows, function ($m) use ($f, $q) {
    if ($f === 'open' && !empty($m['done'])) return false;
    if ($f === 'done' && empty($m['done'])) return false;
    if ($f === 'private' && $m['_src'] !== 'private') return false;
    if ($f === 'contact' && $m['_src'] !== 'contact') return false;
    if ($q !== '' && !str_contains(strtolower(json_encode($m, JSON_UNESCAPED_UNICODE) ?: ''), strtolower($q))) return false;
    return true;
}));
$tabs = ['open' => ['Offen', count(array_filter($all, fn($m) => empty($m['done'])))], 'done' => ['Erledigt', count(array_filter($all, fn($m) => !empty($m['done'])))],
         'contact' => ['Kontakt', count(array_filter($all, fn($m) => $m['_src'] === 'contact'))], 'private' => ['Private Clients', count(array_filter($all, fn($m) => $m['_src'] === 'private'))], 'all' => ['Alle', count($all)]];
$orders = [];
foreach (vr_orders() as $o) $orders[strtolower((string)($o['number'] ?? ''))] = $o;
$byMail = [];
foreach (vr_orders() as $o) $byMail[strtolower((string)($o['customer']['email'] ?? ''))][] = $o;
[$page, $pag] = vr_admin_paginate($rows, 30);

vr_admin_head('Nachrichten', ['sub' => count($rows) . ' Nachricht(en) · Kontaktformular und Private-Clients-Anfragen. Antworten gehen per E-Mail aus dem eigenen Postfach.']);
vr_admin_tabs($tabs, $f, 'messages.php', 'f', array_filter(['q' => $q]));
?>
<form class="afilter" method="get"><input type="hidden" name="f" value="<?= h($f) ?>"><input type="search" name="q" value="<?= h($q) ?>" placeholder="Name, E-Mail, Text, Bestellnummer"><button class="btn btn--ghost btn--sm" type="submit"><span>Suchen</span></button></form>
<div class="agrid">
<?php foreach ($page as $m): $priv = $m['_src'] === 'private'; $mail = (string)($m['email'] ?? ''); $cust = $mail !== '' ? vr_customer_by_email($mail) : null; $ord = $orders[strtolower((string)($m['order'] ?? ''))] ?? null; $n = count($byMail[strtolower($mail)] ?? []);
  $subj = $priv ? 'Private Clients' : ($subjects[(string)($m['subject'] ?? 'other')] ?? 'Sonstiges');
  $reply = 'mailto:' . rawurlencode($mail) . '?subject=' . rawurlencode('Re: ' . $subj . ' · ' . vr_config('brand')) . '&body=' . rawurlencode("\n\n> " . str_replace("\n", "\n> ", (string)($m['message'] ?? '')));
?>
  <?php vr_admin_card_open((string)($m['name'] ?? '') ?: $mail,
      '<span class="asmall">' . h(vr_date((int)$m['at'], true)) . '</span> ' . ($priv ? vr_admin_pill('Private Clients', 'ink') : vr_admin_pill($subj, 'muted')) . ' ' . (!empty($m['done']) ? vr_admin_pill('erledigt', 'ok') : vr_admin_pill('offen', 'warn'))); ?>
    <div class="agrid agrid--21">
      <div>
        <?php if ($priv && ($m['request'] ?? '') !== ''): ?><p style="margin:0 0 8px"><b>Gesucht:</b> <?= h((string)$m['request']) ?></p><?php endif; ?>
        <p class="amsg"><?= nl2br(h((string)($m['message'] ?? ''))) ?></p>
      </div>
      <dl class="adl">
        <dt>E-Mail</dt><dd><a href="<?= h($reply) ?>"><?= h($mail) ?></a></dd>
        <?php if (($m['phone'] ?? '') !== ''): ?><dt>Telefon</dt><dd><a href="tel:<?= h((string)$m['phone']) ?>"><?= h((string)$m['phone']) ?></a></dd><?php endif; ?>
        <?php if (($m['country'] ?? '') !== ''): ?><dt>Land</dt><dd><?= vr_admin_flag((string)$m['country']) ?></dd><?php endif; ?>
        <dt>Sprache</dt><dd><?= h(strtoupper((string)($m['lang'] ?? ''))) ?></dd>
        <?php if (($m['order'] ?? '') !== ''): ?><dt>Bestellung</dt><dd><?= $ord ? '<a href="' . h(vr_url('admin/order.php', ['id' => $ord['id']])) . '">' . h((string)$ord['number']) . '</a> ' . vr_admin_order_pill($ord) : h((string)$m['order']) . ' <span class="asmall">(nicht gefunden)</span>' ?></dd><?php endif; ?>
        <dt>Kunde</dt><dd><?= $cust ? '<a href="' . h(vr_url('admin/customer.php', ['id' => $cust['id']])) . '">Konto öffnen</a>' : '<span class="asmall">kein Konto</span>' ?><?= $n ? ' · <a href="' . h(vr_url('admin/orders.php', ['q' => $mail])) . '">' . $n . ' Bestellung(en)</a>' : '' ?></dd>
        <?php if (!empty($m['done'])): ?><dt>Erledigt</dt><dd><?= h(vr_date((int)$m['done'], true)) ?> · <?= h((string)($m['done_by'] ?? '')) ?></dd><?php endif; ?>
      </dl>
    </div>
    <p class="adm__actions" style="margin:14px 0 0">
      <a class="btn btn--sm" href="<?= h($reply) ?>"><span>Antworten</span></a>
      <?= vr_admin_button(empty($m['done']) ? 'done' : 'reopen', ['src' => $m['_src'], 'at' => (int)$m['at'], 'email' => $mail], empty($m['done']) ? 'Erledigt' : 'Wieder öffnen') ?>
    </p>
  <?php vr_admin_card_close(); ?>
<?php endforeach; ?>
<?php if (!$page): ?><?php vr_admin_card_open(); ?><p class="asmall">Keine Nachrichten.</p><?php vr_admin_card_close(); ?><?php endif; ?>
</div>
<?= $pag ?>
<?php vr_admin_foot(); ?>
