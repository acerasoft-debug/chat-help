<?php
/**
 * Panelin ortak çerçevesi. Panel operatöre özel olduğu için metinler ALMANCA:
 * CLI araçları ve hukuk metinleri de Almanca, tek dil tutarlı kalsın. Vitrinin
 * on dili müşteri tarafı içindir.
 */

declare(strict_types=1);

function vr_admin_head(string $title): void
{
    vr_layout_start(['title' => 'Verwaltung — ' . $title, 'robots' => 'noindex,nofollow']);
    $items = [
        'index.php'     => 'Übersicht',
        'orders.php'    => 'Bestellungen',
        'listings.php'  => 'Angebote',
        'vault.php'     => 'Vault',
        'sellers.php'   => 'Verkäufer',
        'customers.php' => 'Kunden',
        'members.php'   => 'Newsletter',
        'system.php'    => 'System',
    ];
    $cur = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
?>
<section class="sec">
  <div class="wrap">
    <h1 class="sechead__t" style="margin-bottom:28px"><?= h($title) ?></h1>
    <div class="dash">
      <nav class="dashnav" aria-label="Verwaltung">
        <?php foreach ($items as $file => $label): ?>
          <a class="<?= $cur === $file ? 'is-on' : '' ?>" href="<?= h(vr_url('admin/' . $file)) ?>"><?= h($label) ?></a>
        <?php endforeach; ?>
        <a href="<?= h(vr_url('admin/logout.php')) ?>" style="margin-top:12px;color:var(--muted-2)">Abmelden</a>
      </nav>
      <div>
<?php
}

function vr_admin_foot(): void
{
?>
      </div>
    </div>
  </div>
</section>
<?php
    vr_layout_end();
}

/** Küçük sayı kutuları. $rows: [etiket => değer] */
function vr_admin_stats(array $rows): void
{
    echo '<div class="stats">';
    foreach ($rows as $label => $val) {
        echo '<div><b>' . h((string)$val) . '</b><span>' . h((string)$label) . '</span></div>';
    }
    echo '</div>';
}
