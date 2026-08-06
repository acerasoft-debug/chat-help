<?php
/** Gutschein-Codes: anlegen, ansehen, abschalten. */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/../inc/vouchers.php';
require_once __DIR__ . '/_nav.php';

vr_admin_require();

$msg = ''; $err = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();
    $act = (string)($_POST['action'] ?? '');

    if ($act === 'toggle') {
        vr_voucher_set_active((string)($_POST['code'] ?? ''), ($_POST['to'] ?? '') === '1');
        $msg = 'Status geändert.';
    } elseif ($act === 'create') {
        $type = ($_POST['type'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent';
        $raw  = (float)str_replace(',', '.', (string)($_POST['value'] ?? '0'));
        $until = trim((string)($_POST['until'] ?? ''));

        [$ok, $res] = vr_voucher_create([
            'code'            => (string)($_POST['code'] ?? ''),
            'type'            => $type,
            // Prozent kommt als 5 oder 7,5 herein und wird in Basispunkte
            // umgerechnet; ein fester Betrag in Cent.
            'value'           => $type === 'fixed' ? (int)round($raw * 100) : (int)round($raw * 100),
            'min_order_cents' => (int)round((float)str_replace(',', '.', (string)($_POST['min'] ?? '0')) * 100),
            'valid_until'     => $until !== '' ? (int)strtotime($until . ' 23:59:59') : 0,
            'max_uses'        => (int)($_POST['max_uses'] ?? 0),
            'email'           => (string)($_POST['email'] ?? ''),
            'first_order_only' => !empty($_POST['first_only']),
            'note'            => (string)($_POST['note'] ?? ''),
        ]);
        if ($ok) $msg = 'Code angelegt: ' . $res; else $err = (string)$res;
    }
}

$rows = vr_vouchers();
usort($rows, fn($a, $b) => (int)($b['created_at'] ?? 0) <=> (int)($a['created_at'] ?? 0));

vr_admin_head('Gutscheine');
?>
<?php if ($msg !== ''): ?><div class="flash flash--ok" style="margin-bottom:18px"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err !== ''): ?><div class="flash flash--err" style="margin-bottom:18px"><?= h($err) ?></div><?php endif; ?>

<?php
$active = array_filter($rows, fn($v) => !empty($v['active']));
$used   = array_sum(array_map(fn($v) => (int)($v['uses'] ?? 0), $rows));
vr_admin_stats([
  'Codes aktiv'   => count($active),
  'Codes gesamt'  => count($rows),
  'Einlösungen'   => $used,
  'Willkommen'    => (int)vr_config('welcome_discount_bps', 0) > 0
                     ? rtrim(rtrim(number_format((int)vr_config('welcome_discount_bps') / 100, 2, ',', '.'), '0'), ',') . ' %'
                     : 'aus',
]);
?>

<p style="font-size:12.5px;color:var(--muted);margin:18px 0">
  Der Nachlass geht von unserer Provision ab, nicht vom Verkäuferanteil. Ein Code,
  dessen Nachlass die Provision im Warenkorb übersteigt, wird an der Kasse abgelehnt
  statt still gekürzt. Gezählt wird erst bei Zahlungseingang.
</p>

<h2 class="sechead__t" style="font-size:20px;margin:30px 0 12px">Neuer Code</h2>
<form class="form" method="post">
  <?= vr_csrf_field() ?>
  <input type="hidden" name="action" value="create">
  <div class="field"><label for="code">Code (leer = zufällig)</label>
    <input id="code" name="code" type="text" placeholder="SOMMER25" style="text-transform:uppercase"></div>
  <div class="field"><label for="type">Art</label>
    <select id="type" name="type" style="padding:9px 12px;border:1px solid var(--bone-3);background:var(--bone);font:inherit;font-size:13.5px">
      <option value="percent">Prozent</option>
      <option value="fixed">Fester Betrag (EUR)</option>
    </select></div>
  <div class="field"><label for="value">Wert (5 = 5 % bzw. 5,00 €)</label>
    <input id="value" name="value" type="text" inputmode="decimal" required placeholder="5"></div>
  <div class="field"><label for="min">Mindestbestellwert EUR (0 = keiner)</label>
    <input id="min" name="min" type="text" inputmode="decimal" value="0"></div>
  <div class="field"><label for="until">Gültig bis (JJJJ-MM-TT, leer = unbefristet)</label>
    <input id="until" name="until" type="date"></div>
  <div class="field"><label for="max_uses">Maximale Einlösungen (0 = unbegrenzt)</label>
    <input id="max_uses" name="max_uses" type="number" min="0" value="0"></div>
  <div class="field"><label for="email">Nur für E-Mail (leer = für alle)</label>
    <input id="email" name="email" type="email"></div>
  <label class="consent"><input type="checkbox" name="first_only" value="1">
    <span>Nur für die erste Bestellung dieser Adresse</span></label>
  <div class="field"><label for="note">Notiz</label>
    <input id="note" name="note" type="text"></div>
  <div class="field"><button class="btn btn--brass" type="submit"><span>Code anlegen</span></button></div>
</form>

<h2 class="sechead__t" style="font-size:20px;margin:34px 0 12px">Codes</h2>
<?php if (!$rows): ?>
  <p style="color:var(--muted);font-size:14px">Noch kein Code angelegt.</p>
<?php else: ?>
  <div class="tablewrap" tabindex="0">
    <table class="table">
      <thead><tr><th>Code</th><th>Wert</th><th>Bedingung</th><th>Gültig bis</th>
                 <th>Eingelöst</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $v):
        $cond = [];
        if ((int)($v['min_order_cents'] ?? 0) > 0) $cond[] = 'ab ' . vr_money((int)$v['min_order_cents']);
        if (($v['email'] ?? '') !== '')            $cond[] = (string)$v['email'];
        if (!empty($v['first_order_only']))        $cond[] = 'Erstbestellung';
      ?>
        <tr>
          <td style="font-variant-numeric:tabular-nums"><?= h((string)$v['code']) ?>
            <?php if (($v['kind'] ?? '') === 'welcome'): ?>
              <span class="pill">Willkommen</span><?php endif; ?></td>
          <td><?= h(vr_voucher_label($v)) ?></td>
          <td style="font-size:12.5px;color:var(--muted)"><?= h($cond ? implode(' · ', $cond) : '—') ?></td>
          <td><?= (int)($v['valid_until'] ?? 0) > 0 ? h(vr_date((int)$v['valid_until'])) : '—' ?></td>
          <td><?= (int)($v['uses'] ?? 0) ?><?= (int)($v['max_uses'] ?? 0) > 0 ? ' / ' . (int)$v['max_uses'] : '' ?></td>
          <td><?= !empty($v['active']) ? 'aktiv' : 'aus' ?></td>
          <td>
            <form method="post">
              <?= vr_csrf_field() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="code" value="<?= h((string)$v['code']) ?>">
              <input type="hidden" name="to" value="<?= !empty($v['active']) ? '0' : '1' ?>">
              <button class="link" type="submit" style="border:0;background:none;cursor:pointer;padding:0">
                <?= !empty($v['active']) ? 'Abschalten' : 'Einschalten' ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php vr_admin_foot(); ?>
