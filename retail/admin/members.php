<?php
/** Newsletter-Abonnenten: Zahlen und CSV-Export. */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/_nav.php';

vr_admin_require();

$rows = vr_store_read('retail-members.json', []);
if (!is_array($rows)) $rows = [];

$confirmed = array_values(array_filter($rows, fn($r) => !empty($r['confirmed_at'])));

// CSV: Doppel-Opt-in bestätigte Adressen. Nur bestätigte — eine unbestätigte
// Adresse darf nicht angeschrieben werden.
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="maxsales-newsletter.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['email', 'lang', 'confirmed_at']);
    foreach ($confirmed as $r) {
        fputcsv($out, [
            (string)($r['email'] ?? ''),
            (string)($r['lang'] ?? ''),
            gmdate('Y-m-d H:i', (int)($r['confirmed_at'] ?? 0)),
        ]);
    }
    fclose($out);
    exit;
}

usort($rows, fn($a, $b) => (int)($b['created_at'] ?? 0) <=> (int)($a['created_at'] ?? 0));

vr_admin_head('Newsletter');
?>
<?php vr_admin_stats([
  'Bestätigt'   => count($confirmed),
  'Unbestätigt' => count($rows) - count($confirmed),
  'Gesamt'      => count($rows),
]); ?>

<p style="margin:22px 0 18px">
  <a class="btn btn--ghost btn--sm" href="<?= h(vr_url('admin/members.php', ['export' => 'csv'])) ?>">
    <span>Bestätigte als CSV</span></a>
</p>
<p style="font-size:12.5px;color:var(--muted);margin-bottom:18px">
  Der Export enthält nur Adressen mit abgeschlossenem Double-Opt-in. Unbestätigte
  Adressen dürfen nicht angeschrieben werden.
</p>

<?php if (!$rows): ?>
  <p style="color:var(--muted);font-size:14px">Noch keine Anmeldungen.</p>
<?php else: ?>
  <div class="tablewrap" tabindex="0">
    <table class="table">
      <thead><tr><th>E-Mail</th><th>Sprache</th><th>Angemeldet</th><th>Bestätigt</th></tr></thead>
      <tbody>
      <?php foreach (array_slice($rows, 0, 300) as $r): ?>
        <tr>
          <td><?= h((string)($r['email'] ?? '')) ?></td>
          <td><?= h(strtoupper((string)($r['lang'] ?? '—'))) ?></td>
          <td><?= h(vr_date((int)($r['created_at'] ?? 0))) ?></td>
          <td><?= !empty($r['confirmed_at']) ? h(vr_date((int)$r['confirmed_at'])) : '—' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php vr_admin_foot(); ?>
