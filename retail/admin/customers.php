<?php
/**
 * Kundenkonten.
 *
 * Bilerek AZ veri: liste yalnızca e-posta, doğrulama durumu, kayıt tarihi ve
 * sipariş sayısını gösteriyor. Adres ve sipariş içeriği burada değil — onlar
 * ilgili siparişin sayfasında zaten var, iki yerde göstermek gereksiz.
 * Veri minimizasyonu (DSGVO Art. 5) yönetim ekranı için de geçerli.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/_nav.php';

vr_admin_require();

$msg = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();
    // Silme talebi yazılı da gelebilir (DSGVO Art. 17); operatör buradan uygular.
    if (($_POST['action'] ?? '') === 'delete' && ($_POST['id'] ?? '') !== '') {
        $msg = vr_customer_delete((string)$_POST['id'])
             ? 'Konto gelöscht. Bestellungen bleiben aus steuerrechtlichen Gründen bestehen.'
             : 'Konto nicht gefunden.';
    }
}

$rows = vr_customers();
usort($rows, fn($a, $b) => (int)($b['created_at'] ?? 0) <=> (int)($a['created_at'] ?? 0));

$byMail = [];
foreach (vr_orders() as $o) {
    $e = strtolower((string)($o['customer']['email'] ?? ''));
    if ($e !== '' && (string)($o['status'] ?? '') === 'paid') $byMail[$e] = ($byMail[$e] ?? 0) + 1;
}

vr_admin_head('Kunden');
?>
<?php if ($msg !== ''): ?><div class="flash flash--ok" style="margin-bottom:18px"><?= h($msg) ?></div><?php endif; ?>

<p style="font-size:12.5px;color:var(--muted);margin-bottom:16px">
  <?= count($rows) ?> Konto/Konten. Ein Konto ist freiwillig — die meisten Bestellungen laufen als Gast.
</p>

<?php if (!$rows): ?>
  <p style="color:var(--muted);font-size:14px">Noch keine Konten.</p>
<?php else: ?>
  <div class="tablewrap" tabindex="0">
    <table class="table">
      <thead><tr><th>E-Mail</th><th>Name</th><th>Bestätigt</th><th>Bestellungen</th><th>Seit</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $c): ?>
        <tr>
          <td><?= h((string)$c['email']) ?></td>
          <td><?= h((string)($c['name'] ?? '—')) ?></td>
          <td><?= !empty($c['verified']) ? 'ja' : 'nein' ?></td>
          <td><?= (int)($byMail[strtolower((string)$c['email'])] ?? 0) ?></td>
          <td><?= h(vr_date((int)($c['created_at'] ?? 0))) ?></td>
          <td>
            <form method="post" onsubmit="return confirm('Konto endgültig löschen?')">
              <?= vr_csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= h((string)$c['id']) ?>">
              <button class="link" type="submit" style="border:0;background:none;cursor:pointer;padding:0">Löschen</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php vr_admin_foot(); ?>
