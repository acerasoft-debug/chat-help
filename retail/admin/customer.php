<?php
/**
 * Ein Kundenkonto — alles: Profil, Adressen, Bestellungen, Merkliste,
 * Newsletter, Anmeldungen, IP-Verlauf, Notiz. Aktionen: E-Mail manuell
 * bestätigen, Bestätigung/Passwort-Link senden, Konto sperren, löschen.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/../inc/customers.php';
require_once __DIR__ . '/../inc/mail.php';
require_once __DIR__ . '/_nav.php';

$who = vr_admin_require();
$c = vr_customer((string)($_GET['id'] ?? ''));
if ($c === null) { http_response_code(404); vr_admin_head('Nicht gefunden'); echo '<p>Konto nicht gefunden.</p>'; vr_admin_foot(); exit; }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();
    $act = (string)($_POST['action'] ?? '');
    if ($act === 'verify') {
        vr_customer_update($c['id'], ['verified' => true, 'verify_hash' => '', 'verify_expires' => 0]);
        vr_admin_audit('customer_verify', ['email' => $c['email']]); vr_flash('E-Mail als bestätigt markiert.', 'ok');
    } elseif ($act === 'reverify') {
        [$ok, $raw] = vr_customer_reverify($c['id']);
        if ($ok) { try { vr_mail_customer_verify($c['email'], $raw); vr_flash('Bestätigungslink gesendet.', 'ok'); } catch (Throwable $e) { vr_flash('Versand fehlgeschlagen.', 'err'); } }
        vr_admin_audit('customer_reverify', ['email' => $c['email']]);
    } elseif ($act === 'reset') {
        [, $raw] = vr_customer_reset_request($c['email']);
        if ($raw !== '') { try { vr_mail_customer_reset($c['email'], $raw); vr_flash('Link zum Zurücksetzen gesendet.', 'ok'); } catch (Throwable $e) { vr_flash('Versand fehlgeschlagen.', 'err'); } }
        vr_admin_audit('customer_reset_link', ['email' => $c['email']]);
    } elseif ($act === 'status') {
        $to = ($_POST['to'] ?? '') === 'suspended' ? 'suspended' : 'active';
        vr_customer_update($c['id'], ['status' => $to]);
        vr_admin_audit('customer_status', ['email' => $c['email'], 'to' => $to]); vr_flash($to === 'suspended' ? 'Konto gesperrt.' : 'Konto freigegeben.', 'ok');
    } elseif ($act === 'note') {
        vr_customer_update($c['id'], ['admin_note' => mb_substr(trim((string)($_POST['note'] ?? '')), 0, 4000)]); vr_flash('Notiz gespeichert.', 'ok');
    } elseif ($act === 'delete') {
        vr_customer_delete($c['id']);
        vr_admin_audit('customer_delete', ['email' => $c['email']]);
        vr_flash('Konto gelöscht. Bestellungen bleiben aus steuerrechtlichen Gründen bestehen.', 'ok');
        vr_redirect('admin/customers.php');
    }
    vr_redirect('admin/customer.php', ['id' => $c['id']]);
}

$orders = array_values(array_filter(vr_orders(), fn($o) => strtolower((string)($o['customer']['email'] ?? '')) === strtolower($c['email'])));
usort($orders, fn($a, $b) => (int)$b['created_at'] <=> (int)$a['created_at']);
$paid = array_filter($orders, fn($o) => (string)$o['status'] === 'paid');
$spent = array_sum(array_map(fn($o) => (int)$o['total_cents'], $paid));
$member = null;
foreach ((array)vr_store_read('retail-members.json', []) as $m) if (is_array($m) && strtolower((string)$m['email']) === strtolower($c['email'])) $member = $m;
$wish = [];
foreach ((array)($c['wish'] ?? []) as $pid) if (($p = vr_product((string)$pid)) !== null) $wish[] = $p;
$trail = vr_stats_trail('', $c['id'], VR_STATS_KEEP_DAYS, 300);
$ipsSeen = [];
foreach ($trail as $r) $ipsSeen[$r['ip']] = ($ipsSeen[$r['ip']] ?? 0) + 1;
foreach ([$c['last_ip'] ?? '', $c['created_ip'] ?? ''] as $ip) if ($ip !== '' && !isset($ipsSeen[$ip])) $ipsSeen[$ip] = 0;
$a = (array)($c['address'] ?? []); $b = (array)($c['billing'] ?? []);
$sus = (string)($c['status'] ?? 'active') !== 'active';

$actions = '';
if (empty($c['verified'])) $actions .= vr_admin_button('verify', [], 'E-Mail manuell bestätigen', 'btn btn--sm', 'E-Mail-Adresse ohne Klick auf den Link als bestätigt markieren? Dadurch wird die Bestellübersicht für dieses Konto geöffnet.') . vr_admin_button('reverify', [], 'Bestätigungslink senden');
$actions .= vr_admin_button('reset', [], 'Passwort-Link senden', 'btn btn--ghost btn--sm', 'Link zum Zurücksetzen des Passworts an ' . $c['email'] . ' senden?');
$actions .= vr_admin_button('status', ['to' => $sus ? 'active' : 'suspended'], $sus ? 'Konto freigeben' : 'Konto sperren', 'btn btn--ghost btn--sm', $sus ? 'Konto wieder freigeben?' : 'Konto sperren? Anmeldung ist dann nicht mehr möglich.');

vr_admin_head((string)($c['name'] ?? '') ?: $c['email'], ['sub' => $c['email'] . ' · Kunde seit ' . vr_date((int)$c['created_at']), 'back' => [vr_url('admin/customers.php'), 'Kunden'], 'actions' => $actions, 'nav' => 'customers.php']);
?>
<?php vr_admin_stats([
  'Status'      => [$sus ? 'gesperrt' : (!empty($c['verified']) ? 'bestätigt' : 'unbestätigt'), $sus ? 'Anmeldung blockiert' : ''],
  'Bestellungen'=> [count($paid), count($orders) - count($paid) . ' unbezahlt/storniert'],
  'Umsatz'      => [vr_money($spent), count($paid) ? 'Ø ' . vr_money(intdiv($spent, count($paid))) : ''],
  'Anmeldungen' => [(int)($c['logins'] ?? 0), !empty($c['last_login']) ? 'zuletzt ' . vr_admin_ago((int)$c['last_login']) : 'noch nie'],
]); ?>
<div class="agrid agrid--2">
  <?php vr_admin_card_open('Profil'); ?>
    <dl class="adl">
      <dt>Name</dt><dd><?= h((string)($c['name'] ?? '—')) ?></dd>
      <dt>E-Mail</dt><dd><a href="mailto:<?= h($c['email']) ?>"><?= h($c['email']) ?></a></dd>
      <dt>Telefon</dt><dd><?= h((string)($c['phone'] ?? '—') ?: '—') ?></dd>
      <dt>Sprache</dt><dd><?= h(strtoupper((string)($c['lang'] ?? '—'))) ?></dd>
      <dt>Newsletter</dt><dd><?= $member ? (!empty($member['confirmed_at']) ? '<span class="aok">bestätigt</span> seit ' . h(vr_date((int)$member['confirmed_at'])) : 'Bestätigung ausstehend') : 'nicht abonniert' ?></dd>
      <dt>Registriert</dt><dd><?= h(vr_date((int)$c['created_at'], true)) ?><?= ($c['created_ip'] ?? '') !== '' ? ' · <a class="amono" href="' . h(vr_url('admin/visitors.php', ['ip' => $c['created_ip']])) . '">' . h((string)$c['created_ip']) . '</a>' : '' ?></dd>
      <dt>Letzte Anmeldung</dt><dd><?= !empty($c['last_login']) ? h(vr_date((int)$c['last_login'], true)) : '—' ?><?= ($c['last_ip'] ?? '') !== '' ? ' · <a class="amono" href="' . h(vr_url('admin/visitors.php', ['ip' => $c['last_ip']])) . '">' . h((string)$c['last_ip']) . '</a>' : '' ?></dd>
      <dt>Gerät</dt><dd class="asmall amono"><?= h((string)($c['last_ua'] ?? '—') ?: '—') ?></dd>
      <dt>Konto-ID</dt><dd class="amono asmall"><?= h($c['id']) ?></dd>
    </dl>
  <?php vr_admin_card_close(); ?>
  <?php vr_admin_card_open('Adressen'); ?>
    <div class="agrid agrid--2">
      <div><p class="asmall" style="margin:0 0 6px;letter-spacing:.14em;text-transform:uppercase">Lieferung</p>
        <?php if (array_filter($a)): ?><p class="addr" style="margin:0"><?= h((string)($a['name'] ?? '')) ?><?= ($a['company'] ?? '') !== '' ? '<br>' . h((string)$a['company']) : '' ?><br><?= h((string)($a['street'] ?? '')) ?><?= ($a['line2'] ?? '') !== '' ? '<br>' . h((string)$a['line2']) : '' ?><br><?= h(trim((string)($a['zip'] ?? '') . ' ' . (string)($a['city'] ?? ''))) ?><br><?= h(vr_country_name((string)($a['country'] ?? ''))) ?></p><?php else: ?><p class="asmall">—</p><?php endif; ?></div>
      <div><p class="asmall" style="margin:0 0 6px;letter-spacing:.14em;text-transform:uppercase">Rechnung</p>
        <?php if (!isset($c['billing_same']) || !empty($c['billing_same'])): ?><p class="asmall">wie Lieferadresse</p>
        <?php elseif (array_filter($b)): ?><p class="addr" style="margin:0"><?= h((string)($b['name'] ?? '')) ?><?= ($b['company'] ?? '') !== '' ? '<br>' . h((string)$b['company']) : '' ?><br><?= h((string)($b['street'] ?? '')) ?><br><?= h(trim((string)($b['zip'] ?? '') . ' ' . (string)($b['city'] ?? ''))) ?><br><?= h(vr_country_name((string)($b['country'] ?? ''))) ?><?= ($b['vat'] ?? '') !== '' ? '<br>USt-IdNr. ' . h((string)$b['vat']) : '' ?></p><?php else: ?><p class="asmall">—</p><?php endif; ?></div>
    </div>
  <?php vr_admin_card_close(); ?>
</div>

<?php vr_admin_card_open('Bestellungen', '', 'acard--pad0'); ?>
  <div class="tablewrap"><table class="table"><thead><tr><th>Nummer</th><th>Datum</th><th>Artikel</th><th>Status</th><th>Zahlung</th><th class="anum">Summe</th><th>IP</th></tr></thead><tbody>
  <?php foreach ($orders as $o): ?>
    <tr><td><a href="<?= h(vr_url('admin/order.php', ['id' => $o['id']])) ?>"><?= h($o['number']) ?></a></td><td class="asmall"><?= h(vr_date((int)$o['created_at'], true)) ?></td>
      <td class="asmall"><?= h(implode(', ', array_map(fn($l) => ($l['qty'] ?? 1) . '× ' . ($l['name'] ?? ''), (array)$o['lines']))) ?></td><td><?= vr_admin_order_pill($o) ?></td><td><?= vr_admin_paym($o) ?></td><td class="anum"><?= h(vr_money((int)$o['total_cents'])) ?></td>
      <td><?= ($o['client']['ip'] ?? '') !== '' ? '<a class="amono asmall" href="' . h(vr_url('admin/visitors.php', ['ip' => $o['client']['ip']])) . '">' . h((string)$o['client']['ip']) . '</a>' : '' ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$orders): ?><tr><td colspan="7" class="asmall">Keine Bestellungen mit dieser E-Mail.</td></tr><?php endif; ?>
  </tbody></table></div>
<?php vr_admin_card_close(); ?>

<div class="agrid agrid--2">
  <?php vr_admin_card_open('Merkliste · ' . count($wish)); ?>
    <?php if ($wish): ?><div class="agal"><?php foreach (array_slice($wish, 0, 12) as $p): ?><figure><a href="<?= h(vr_url('admin/product.php', ['id' => $p['id']])) ?>"><img src="<?= h(vr_img(vr_card_image($p), 300)) ?>" alt=""></a><figcaption><?= h($p['brand'] . ' · ' . vr_card_name($p)) ?></figcaption></figure><?php endforeach; ?></div>
    <?php else: ?><p class="asmall">Leer.</p><?php endif; ?>
  <?php vr_admin_card_close(); ?>
  <?php vr_admin_card_open('IP-Adressen & Aktivität'); ?>
    <?php if ($ipsSeen): ?><ul class="alive"><?php foreach ($ipsSeen as $ip => $n): ?><li><?= vr_admin_flag(vr_geoip_lookup((string)$ip)) ?><span><a class="amono" href="<?= h(vr_url('admin/visitors.php', ['ip' => $ip])) ?>"><?= h((string)$ip) ?></a></span><small><?= (int)$n ?> Aufrufe (eingeloggt)</small></li><?php endforeach; ?></ul><?php else: ?><p class="asmall">Noch keine Aktivität gemessen.</p><?php endif; ?>
    <?php if ($trail): ?><p class="asmall" style="margin:14px 0 6px">Zuletzt besucht (eingeloggt):</p><ul class="atl"><?php foreach (array_slice($trail, 0, 8) as $r): ?><li><?= h($r['p'] . (($r['q']['id'] ?? '') !== '' ? ' · ' . $r['q']['id'] : '')) ?><small><?= h(vr_date((int)$r['t'], true)) ?></small></li><?php endforeach; ?></ul><?php endif; ?>
  <?php vr_admin_card_close(); ?>
</div>

<div class="agrid agrid--2">
  <?php vr_admin_card_open('Interne Notiz'); ?>
    <form class="form" method="post"><?= vr_csrf_field() ?><input type="hidden" name="action" value="note">
      <div class="field"><textarea name="note" rows="4"><?= h((string)($c['admin_note'] ?? '')) ?></textarea></div>
      <button class="btn btn--ghost btn--sm" type="submit"><span>Speichern</span></button></form>
  <?php vr_admin_card_close(); ?>
  <?php vr_admin_card_open('Konto löschen', '', 'adanger'); ?>
    <p class="anote">DSGVO Art. 17. Das Konto wird vollständig gelöscht; Bestellungen bleiben (§147 AO, §257 HGB) und sind danach keinem Konto mehr zugeordnet.</p>
    <div style="margin-top:12px"><?= vr_admin_button('delete', [], 'Konto endgültig löschen', 'btn btn--danger btn--sm', 'Konto ' . $c['email'] . ' endgültig löschen?') ?></div>
  <?php vr_admin_card_close(); ?>
</div>
<?php vr_admin_foot(); ?>
