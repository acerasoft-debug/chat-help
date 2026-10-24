<?php
/* LACOSTE DALGASI (24 Eki 2026, operatör: "bugün kalan gönderimi kullan 435 email gönder lacoste indirimi").
 * Ölçülen: tek marka seçkisi yalnız indirimdeki Lacoste'lar; mektup konu/başlık Lacoste, fiyat yok; takip sırası
 * deals → lacoste yalnız VESTRA_DEALS_LACOSTE_UNTIL gününe kadar; sonra eskisi gibi (indirimi almışa sıradaki yok).
 * Kum havuzu: VESTRA_DATA_DIR geçici dizin. */
$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; } };
$root = dirname(__DIR__).'/vestra';
$sand = sys_get_temp_dir().'/vestra-lac-'.bin2hex(random_bytes(4)); mkdir($sand.'/data', 0777, true);
define('VESTRA_DATA_DIR', $sand.'/data'); define('VESTRA_FINDER_ON', true);
require $root.'/inc/products.php'; require_once $root.'/inc/finder.php'; require_once $root.'/inc/mailbox.php'; require_once $root.'/inc/campaign_deals.php';
vestra_write_json('listings.json', [
  ['id' => 'D1', 'brand' => 'DSQUARED2', 'name' => 'Logo T-Shirt', 'mode' => 'sale', 'list' => 50, 'tiers' => [['min' => 5, 'price' => 29]], 'images' => ['/uploads/d1.jpg'], 'status' => 'approved'],
  ['id' => 'L1', 'brand' => 'Lacoste', 'name' => 'Fleece Hoodie', 'mode' => 'sale', 'list' => 100, 'tiers' => [['min' => 3, 'price' => 70]], 'images' => ['/uploads/l1.jpg'], 'status' => 'approved'],
  ['id' => 'L2', 'brand' => 'LACOSTE', 'name' => 'Crew Sweatshirt', 'mode' => 'sale', 'list' => 100, 'tiers' => [['min' => 3, 'price' => 68]], 'images' => ['/uploads/l2.jpg'], 'status' => 'approved'],
  ['id' => 'L3', 'brand' => 'Lacoste', 'name' => 'Polo Basic', 'mode' => 'sale', 'list' => 40, 'tiers' => [['min' => 3, 'price' => 39]], 'images' => ['/uploads/l3.jpg'], 'status' => 'approved'],
  ['id' => 'L4', 'brand' => 'Lacoste', 'name' => 'Pending', 'mode' => 'sale', 'list' => 100, 'tiers' => [['min' => 3, 'price' => 50]], 'images' => ['/uploads/l4.jpg'], 'status' => 'pending'],
]);
$p = vestra_deals_pick_brand('Lacoste');
$t('seçki: yalnız onaylı, ≥%10 indirimli Lacoste (büyük/küçük harf), yüksek indirim önce', array_column($p['items'], 'id') === ['L2', 'L1'] && $p['max'] === 32 && count($p['brands']) === 1);
[$s, $b, $o] = vestra_campaign_deals('Boutique Une', 'en', $p);
$t('konu ve başlık yalnız Lacoste, en yüksek indirim', str_contains($s, 'Lacoste') && str_contains($s, '−32%') && !str_contains($s, 'DSQUARED2') && str_contains($o['hero']['title'], 'Lacoste'));
$t('fiyat yok (KURAL 19), tek marka giriş cümlesi, çıkış bağlantısı', !preg_match('/€|EUR|68[.,]|70[.,]/', $b.$o['html']) && !str_contains($b, 'several houses') && str_contains($b, 'lead-unsubscribe'));
$today = strtotime(VESTRA_DEALS_LACOSTE_UNTIL.' 12:00:00'); $after = $today + 86400;
$t('takip sırası: deals almışa bugün lacoste', vestra_deals_lacoste_on($today) === true && vestra_mailbox_followup_next(['edit', 'deals'], ['deals' => 1, 'lacoste' => 1]) === (vestra_deals_lacoste_on() ? 'lacoste' : ''));
$t('deals almamışa önce deals; lacoste almışa sıradaki yok', vestra_mailbox_followup_next(['edit'], ['deals' => 1, 'lacoste' => 1]) === 'deals' && vestra_mailbox_followup_next(['deals', 'lacoste'], ['deals' => 1, 'lacoste' => 1]) === '');
$t('ertesi gün dalga kapalı', vestra_deals_lacoste_on($after) === false);
$t('kampanya listesinde lacoste yalnız dalga günü', isset(vestra_finder_campaigns()['lacoste']) === vestra_deals_lacoste_on());
echo "\n{$ok} ok, {$fail} hata\n"; exit($fail ? 1 : 0);
