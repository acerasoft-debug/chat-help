<?php
/** Statistik: Besucher, Seiten, Artikel, Marken, Herkunft, Geräte, Suchen, Stunden. */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/_nav.php';

vr_admin_require();

$ranges = [1 => 'Heute', 7 => '7 Tage', 30 => '30 Tage', 90 => '90 Tage'];
$days = (int)($_GET['d'] ?? 30);
if (!isset($ranges[$days])) $days = 30;
$st   = vr_stats_range($days);
$prev = null;   // karşılaştırma: önceki aynı uzunluk (yalnız 7/30)
if ($days === 7 || $days === 30) {
    $prev = ['uv' => 0, 'pv' => 0];
    foreach (vr_stats_days($days * 2) as $i => $day) if ($i < $days) { $s = vr_stats_day($day); $prev['uv'] += $s['uv']; $prev['pv'] += $s['pv']; }
}
$trend = fn(int $now, int $p) => $p > 0 ? (int)round(($now - $p) * 100 / $p) : null;

$labels = []; $uv = []; $pv = []; $bots = [];
foreach ($st['days'] as $day => $d) { $labels[] = date('d.m.', strtotime($day)); $uv[$day] = $d['uv']; $pv[$day] = $d['pv']; $bots[$day] = $d['bots']; }

$orders = array_filter(vr_orders(), fn($o) => (string)($o['status'] ?? '') === 'paid' && (int)$o['created_at'] >= time() - $days * 86400);
$revenue = array_sum(array_map(fn($o) => (int)$o['total_cents'], $orders));
$pagesPerVisit = $st['uv'] > 0 ? round($st['pv'] / $st['uv'], 1) : 0;

$pageLabel = function (string $p): string {
    static $m = ['/' => 'Startseite', '/index.php' => 'Startseite', '/shop.php' => 'Shop', '/product.php' => 'Artikelseite', '/outlet.php' => 'Vault', '/brands.php' => 'Häuser',
                 '/cart.php' => 'Warenkorb', '/checkout.php' => 'Kasse', '/wishlist.php' => 'Merkliste', '/app.php' => 'App', '/journal.php' => 'Journal', '/contact.php' => 'Kontakt',
                 '/account/login.php' => 'Login', '/account/register.php' => 'Registrierung', '/account/index.php' => 'Kundenkonto', '/order.php' => 'Bestellstatus', '/sell.php' => 'Verkaufen', '/private.php' => 'Private Clients'];
    return isset($m[$p]) ? h($m[$p]) . ' <small class="amuted">' . h($p) . '</small>' : h($p);
};

vr_admin_head('Statistik', ['sub' => 'Ohne Cookies, ohne Drittanbieter — gemessen auf dem eigenen Server.']);
vr_admin_tabs($ranges, (string)$days, 'stats.php', 'd');
?>
<?php vr_admin_stats([
  'Besucher'       => [number_format($st['uv'], 0, ',', '.'), 'eindeutige Sitzungen', $prev ? $trend($st['uv'], $prev['uv']) : null],
  'Seitenaufrufe'  => [number_format($st['pv'], 0, ',', '.'), $pagesPerVisit . ' je Besuch', $prev ? $trend($st['pv'], $prev['pv']) : null],
  'IP-Adressen'    => [number_format($st['ips'], 0, ',', '.'), number_format($st['bots'], 0, ',', '.') . ' Bot-Aufrufe gefiltert'],
  'Umsatz'         => [vr_money($revenue), count($orders) . ' Bestellungen · ' . ($st['uv'] ? str_replace('.', ',', (string)round(count($orders) * 100 / $st['uv'], 2)) : '0') . ' % Konversion'],
]); ?>

<?php if ($days > 1): ?>
  <?php vr_admin_card_open('Verlauf'); ?>
    <?php vr_admin_chart(['Besucher' => $uv, 'Seitenaufrufe' => $pv, 'Bots' => $bots], $labels, ['h' => 220]); ?>
  <?php vr_admin_card_close(); ?>
<?php endif; ?>

<div class="agrid agrid--2">
  <?php vr_admin_card_open('Seiten'); ?>
    <?php vr_admin_bars($st['p'], 12, null, $pageLabel); ?>
  <?php vr_admin_card_close(); ?>
  <?php vr_admin_card_open('Einstiegsseiten'); ?>
    <?php vr_admin_bars($st['entry'], 12, null, $pageLabel); ?>
  <?php vr_admin_card_close(); ?>
  <?php vr_admin_card_open('Meistgesehene Artikel'); ?>
    <?php vr_admin_bars($st['prod'], 12, fn($k) => vr_url('admin/product.php', ['id' => $k]), function ($k) { $p = vr_product($k); return h($p ? $p['brand'] . ' · ' . vr_card_name($p) : $k); }); ?>
  <?php vr_admin_card_close(); ?>
  <?php vr_admin_card_open('Häuser (Filter im Shop)'); ?>
    <?php vr_admin_bars($st['brand'], 12, fn($k) => vr_url('shop.php', ['brand' => $k]), fn($k) => h($k)); ?>
  <?php vr_admin_card_close(); ?>
  <?php vr_admin_card_open('Länder'); ?>
    <?php vr_admin_bars($st['cc'], 12, fn($k) => vr_url('admin/visitors.php', ['cc' => $k]), fn($k) => vr_admin_flag($k) . ' ' . h($k === '??' ? 'unbekannt' : vr_country_name($k))); ?>
  <?php vr_admin_card_close(); ?>
  <?php vr_admin_card_open('Herkunft (Referrer)'); ?>
    <?php vr_admin_bars($st['ref'], 12, null, fn($k) => h($k)); ?>
  <?php vr_admin_card_close(); ?>
  <?php vr_admin_card_open('Suchbegriffe'); ?>
    <?php vr_admin_bars($st['q'], 12, fn($k) => vr_url('shop.php', ['q' => $k]), fn($k) => h($k)); ?>
  <?php vr_admin_card_close(); ?>
  <?php vr_admin_card_open('Tageszeit (UTC)'); ?>
    <?php vr_admin_hours($st['h']); ?>
  <?php vr_admin_card_close(); ?>
</div>

<div class="agrid agrid--3">
  <?php vr_admin_card_open('Geräte'); ?><?php vr_admin_bars($st['d'], 5, null, fn($k) => h(['phone' => 'Telefon', 'tablet' => 'Tablet', 'desktop' => 'Desktop'][$k] ?? $k)); ?><?php vr_admin_card_close(); ?>
  <?php vr_admin_card_open('Browser'); ?><?php vr_admin_bars($st['b'], 6, null, fn($k) => h($k)); ?><?php vr_admin_card_close(); ?>
  <?php vr_admin_card_open('Betriebssystem'); ?><?php vr_admin_bars($st['o'], 6, null, fn($k) => h($k)); ?><?php vr_admin_card_close(); ?>
</div>
<?php vr_admin_card_open('Sprache der Besucher'); ?><?php vr_admin_bars($st['l'], 10, null, fn($k) => h(strtoupper($k))); ?><?php vr_admin_card_close(); ?>
<?php vr_admin_foot(); ?>
