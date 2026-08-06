<?php
/** Satıcılar: Stripe durumu, askıya alma, yeniden açma. */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/../inc/listings.php';
require_once __DIR__ . '/_nav.php';

vr_admin_require();

$msg = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();
    $id = (string)($_POST['id'] ?? '');
    $to = ($_POST['action'] ?? '') === 'suspend' ? 'suspended' : 'active';
    if ($id !== '' && vr_seller_update($id, ['status' => $to])) {
        $msg = $to === 'suspended' ? 'Verkäufer gesperrt.' : 'Verkäufer wieder aktiv.';
    }
}

$rows = vr_sellers();
usort($rows, fn($a, $b) => (int)($b['created_at'] ?? 0) <=> (int)($a['created_at'] ?? 0));

vr_admin_head('Verkäufer');
?>
<?php if ($msg !== ''): ?><div class="flash flash--ok" style="margin-bottom:18px"><?= h($msg) ?></div><?php endif; ?>

<?php if (!$rows): ?>
  <p style="color:var(--muted);font-size:14px">Noch keine Verkäufer registriert.</p>
<?php else: ?>
  <div class="tablewrap" tabindex="0">
    <table class="table">
      <thead><tr><th>Verkäufer</th><th>Typ</th><th>Stripe</th><th>Angebote</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $s):
        $stats = vr_seller_stats((string)$s['id']);
        $ready = !empty($s['charges_enabled']);
        $acct  = (string)($s['stripe_account_id'] ?? '');
      ?>
        <tr>
          <td><?= h(vr_seller_display($s)) ?><br>
              <span style="color:var(--muted);font-size:12px"><?= h((string)$s['email']) ?></span></td>
          <td><?= ($s['seller_type'] ?? '') === 'private' ? 'Privat' : 'Händler' ?></td>
          <td><?= $acct === '' ? '—' : ($ready ? 'freigeschaltet' : 'in Prüfung') ?></td>
          <td><?= (int)($stats['live'] ?? 0) ?> live / <?= (int)($stats['total'] ?? 0) ?></td>
          <td><?= (string)($s['status'] ?? 'active') === 'active' ? 'aktiv' : 'gesperrt' ?></td>
          <td>
            <form method="post">
              <?= vr_csrf_field() ?>
              <input type="hidden" name="id" value="<?= h((string)$s['id']) ?>">
              <?php $sus = (string)($s['status'] ?? 'active') === 'active'; ?>
              <button class="link" type="submit" name="action" value="<?= $sus ? 'suspend' : 'activate' ?>"
                      style="border:0;background:none;cursor:pointer;padding:0">
                <?= $sus ? 'Sperren' : 'Freigeben' ?>
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p style="font-size:12.5px;color:var(--muted);margin-top:14px">
    Gesperrte Verkäufer können sich nicht anmelden und ihre Angebote verschwinden aus dem Schaufenster.
    Bestehende Bestellungen bleiben unberührt.
  </p>
<?php endif; ?>
<?php vr_admin_foot(); ?>
