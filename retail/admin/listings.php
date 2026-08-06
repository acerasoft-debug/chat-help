<?php
/** Satıcı ilanlarının denetimi — tools/moderate.php ile aynı mantık, web'de. */

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
    $approve = ($_POST['action'] ?? '') === 'approve';
    $note = mb_substr(trim((string)($_POST['note'] ?? '')), 0, 500);
    if ($id !== '' && vr_listing_moderate($id, $approve, $note)) {
        $msg = $approve ? 'Angebot freigegeben.' : 'Angebot abgelehnt.';
    } else {
        $msg = 'Angebot nicht gefunden.';
    }
}

$f = (string)($_GET['f'] ?? 'review');
$rows = array_values(array_filter(vr_listings_all(),
    fn($r) => $f === 'all' || (string)($r['status'] ?? 'review') === $f));
usort($rows, fn($a, $b) => (int)($b['created_at'] ?? 0) <=> (int)($a['created_at'] ?? 0));

$tabs = ['review' => 'Zu prüfen', 'approved' => 'Freigegeben', 'rejected' => 'Abgelehnt',
         'paused' => 'Pausiert', 'all' => 'Alle'];

vr_admin_head('Angebote');
?>
<?php if ($msg !== ''): ?><div class="flash flash--ok" style="margin-bottom:18px"><?= h($msg) ?></div><?php endif; ?>

<nav class="sizetabs" style="margin-bottom:18px">
  <?php foreach ($tabs as $k => $label): ?>
    <a class="<?= $f === $k ? 'is-on' : '' ?>" href="<?= h(vr_url('admin/listings.php', ['f' => $k])) ?>"><?= h($label) ?></a>
  <?php endforeach; ?>
</nav>

<?php if (!$rows): ?>
  <p style="color:var(--muted);font-size:14px">Nichts in dieser Ansicht.</p>
<?php else: ?>
  <?php foreach ($rows as $r): $s = vr_seller((string)($r['seller_uid'] ?? '')); ?>
    <div class="panel" style="margin-bottom:22px">
      <div class="panel__h">
        <h2 style="font-size:15px;font-family:var(--serif);text-transform:none;letter-spacing:0;color:var(--ink)">
          <?= h((string)($r['brand'] ?? '')) ?> — <?= h((string)($r['name'] ?? '')) ?>
        </h2>
        <?= vr_listing_status_pill((string)($r['status'] ?? 'review')) ?>
      </div>
      <div class="dl">
        <dt>Verkäufer</dt><dd><?= h(vr_seller_display($s)) ?>
          <?php if ($s && ($s['seller_type'] ?? '') === 'private'): ?>
            <span class="pill">Privat</span><?php endif; ?></dd>
        <dt>Preis</dt><dd><?= h(vr_money((int)round((float)($r['retail_price'] ?? 0) * 100))) ?></dd>
        <dt>Größen</dt><dd><?= h((string)($r['sizes'] ?? '—')) ?></dd>
        <dt>Zustand</dt><dd><?= h((string)($r['condition'] ?? '—')) ?></dd>
        <?php if (($r['moderation_note'] ?? '') !== ''): ?>
          <dt>Notiz</dt><dd><?= h((string)$r['moderation_note']) ?></dd>
        <?php endif; ?>
      </div>
      <?php if (!empty($r['images'])): ?>
        <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
          <?php foreach (array_slice((array)$r['images'], 0, 4) as $img): ?>
            <img src="<?= h((string)$img) ?>" alt="" width="90" height="112"
                 style="width:90px;height:112px;object-fit:cover;border:1px solid var(--bone-3)">
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ((string)($r['status'] ?? '') === 'review'): ?>
        <form method="post" style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
          <?= vr_csrf_field() ?>
          <input type="hidden" name="id" value="<?= h((string)$r['id']) ?>">
          <input type="text" name="note" placeholder="Grund (bei Ablehnung)"
                 style="flex:1;min-width:200px;padding:8px 10px;border:1px solid var(--bone-3);background:var(--bone);font:inherit;font-size:13px">
          <button class="btn btn--brass btn--sm" type="submit" name="action" value="approve"><span>Freigeben</span></button>
          <button class="btn btn--ghost btn--sm" type="submit" name="action" value="reject"
                  style="border-color:var(--ember-text);color:var(--ember-text)"><span>Ablehnen</span></button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
<?php vr_admin_foot(); ?>
