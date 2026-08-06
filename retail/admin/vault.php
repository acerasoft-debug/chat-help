<?php
/**
 * Vault losları: listele, aç, kapat.
 *
 * Planı DEĞİŞTİREN bir alan bilerek yok — açılış, taban, kademe ve süre bir
 * kez yazılır. Sahte indirim tam olarak sonradan tabanı yükseltmekle doğar.
 * Yanlış açılan los kapatılır, yenisi açılır; eskisi kayıtta kalır.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/_nav.php';

vr_admin_require();

$msg = ''; $err = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();
    $act = (string)($_POST['action'] ?? '');

    if ($act === 'open') {
        $eur = static fn(string $s): int => (int)round((float)str_replace([' ', ','], ['', '.'], $s) * 100);
        [$ok, $res] = vr_vault_open(
            (string)($_POST['product_id'] ?? ''),
            $eur((string)($_POST['open'] ?? '')),
            $eur((string)($_POST['floor'] ?? '')),
            [
                'steps'      => max(1, (int)($_POST['steps'] ?? 6)),
                'step_hours' => max(0.5, (float)($_POST['hours'] ?? 24)),
                'rrp_cents'  => $eur((string)($_POST['rrp'] ?? '0')),
            ]
        );
        if ($ok) $msg = 'Los eröffnet: ' . $res; else $err = (string)$res;
    } elseif ($act === 'close') {
        $lotId = (string)($_POST['lot_id'] ?? '');
        $done = false;
        vr_store_mutate('retail-vault.json', static function ($d) use ($lotId, &$done) {
            if (!is_array($d['lots'] ?? null) || !$d['lots']) $d['lots'] = vr_vault_all();
            foreach ($d['lots'] as $i => $l) {
                if ((string)($l['lot_id'] ?? '') !== $lotId) continue;
                if ((string)($l['status'] ?? '') === 'sold') return null;
                $d['lots'][$i]['status'] = 'closed';
                $done = true;
                return $d;
            }
            return null;
        }, ['lots' => []]);
        $msg = $done ? 'Los geschlossen.' : 'Los nicht gefunden oder bereits verkauft.';
    }
}

$lots = vr_vault_lots(['include_sold' => true, 'include_scheduled' => true]);
$inVault = vr_vault_product_ids();
$free = array_values(array_filter(vr_catalog(), fn($p) => !isset($inVault[$p['id']]) && (int)$p['stock'] > 0));

vr_admin_head('Vault');
?>
<?php if ($msg !== ''): ?><div class="flash flash--ok" style="margin-bottom:18px"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err !== ''): ?><div class="flash flash--err" style="margin-bottom:18px"><?= h($err) ?></div><?php endif; ?>

<h2 class="sechead__t" style="font-size:20px;margin-bottom:12px">Laufende Lose</h2>
<?php if (!$lots): ?>
  <p style="color:var(--muted);font-size:14px">Kein Los eröffnet.</p>
<?php else: ?>
  <div class="tablewrap" tabindex="0">
    <table class="table">
      <thead><tr><th>Artikel</th><th>Stufe</th><th>Preis</th><th>Boden</th><th>Nächster Schritt</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($lots as $v): $st = $v['state']; ?>
        <tr>
          <td><?= h($v['product']['brand'] . ' ' . vr_card_name($v['product'])) ?></td>
          <td><?= (int)$st['step'] ?> / <?= (int)$st['steps'] ?></td>
          <td><?= h(vr_money((int)$st['cents'])) ?></td>
          <td><?= h(vr_money((int)$st['floor_cents'])) ?></td>
          <td><?= $st['next_at'] ? h(vr_date((int)$st['next_at'], true)) : 'Boden erreicht' ?></td>
          <td><?= $v['sold'] ? 'verkauft' : ($st['phase'] === 'scheduled' ? 'geplant' : 'offen') ?></td>
          <td>
            <?php if (!$v['sold']): ?>
              <form method="post" onsubmit="return confirm('Los schließen?')">
                <?= vr_csrf_field() ?>
                <input type="hidden" name="action" value="close">
                <input type="hidden" name="lot_id" value="<?= h((string)$v['lot_id']) ?>">
                <button class="link" type="submit" style="border:0;background:none;cursor:pointer;padding:0">Schließen</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<hr class="rule" style="margin:34px 0 26px">

<h2 class="sechead__t" style="font-size:20px;margin-bottom:6px">Neues Los eröffnen</h2>
<p style="font-size:12.5px;color:var(--muted);margin-bottom:18px">
  Der Plan wird einmal geschrieben und ist danach unveränderlich. Ein falsch
  eröffnetes Los wird geschlossen, nicht korrigiert.
</p>

<form class="form" method="post">
  <?= vr_csrf_field() ?>
  <input type="hidden" name="action" value="open">
  <div class="field">
    <label for="product_id">Artikel</label>
    <select id="product_id" name="product_id" required
            style="padding:9px 12px;border:1px solid var(--bone-3);background:var(--bone);font:inherit;font-size:13.5px">
      <?php foreach (array_slice($free, 0, 300) as $p): ?>
        <option value="<?= h((string)$p['id']) ?>">
          <?= h($p['brand'] . ' — ' . vr_card_name($p) . ' (' . vr_money((int)$p['price_cents']) . ')') ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field"><label for="open">Eröffnungspreis (EUR)</label>
    <input id="open" name="open" type="text" inputmode="decimal" required placeholder="389,00"></div>
  <div class="field"><label for="floor">Bodenpreis (EUR)</label>
    <input id="floor" name="floor" type="text" inputmode="decimal" required placeholder="149,00"></div>
  <div class="field"><label for="steps">Stufen</label>
    <input id="steps" name="steps" type="number" min="1" max="30" value="<?= (int)vr_config('vault_steps', 6) ?>"></div>
  <div class="field"><label for="hours">Stunden je Stufe</label>
    <input id="hours" name="hours" type="number" min="0.5" step="0.5" value="<?= h((string)vr_config('vault_step_hours', 24)) ?>"></div>
  <div class="field"><label for="rrp">UVP (EUR, optional)</label>
    <input id="rrp" name="rrp" type="text" inputmode="decimal" placeholder="590,00"></div>
  <div class="field"><button class="btn btn--brass" type="submit"><span>Los eröffnen</span></button></div>
</form>
<?php vr_admin_foot(); ?>
