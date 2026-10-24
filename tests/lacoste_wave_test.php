<?php
/* MARKA DALGALARI + GÜNLÜK DOLDURMA (24 Eki 2026, operatör: "435 email gönder lacoste indirimi", "her gün otomatik
 * gitmeli, kotayı doldur"). Ölçülen: tek marka seçkisi; dalgalar (≥%20) ve sırası; mektup konusu tek marka, fiyat yok;
 * 16:00 sonrası pay = tavan; yeni butik payı bozulmaz; Almanya yalnız işaretli gün açılır.
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
$w = vestra_deals_waves();
$t('dalgalar: Lacoste önce (öne çıkan değilse de en yüksek %), DSQUARED2 ≥%20 ayrı dalga', array_keys($w) === ['w:dsquared2', 'lacoste'] || array_keys($w) === ['lacoste', 'w:dsquared2']);
$t('dalga anahtarı: lacoste / w:<marka>', vestra_deals_wave_key('Lacoste') === 'lacoste' && vestra_deals_wave_key('Ralph Lauren') === 'w:ralph-lauren' && vestra_deals_is_wave_key('w:x') && !vestra_deals_is_wave_key('deals'));
$camps = vestra_finder_campaigns();
$t('kampanya listesinde deals + iki dalga', isset($camps['deals'], $camps['lacoste'], $camps['w:dsquared2']));
$t('takip sırası: önce deals, sonra dalgalar listedeki sırayla, hepsini almışa yok', vestra_mailbox_followup_next(['edit'], $camps) === 'deals'
   && vestra_mailbox_followup_next(['edit', 'deals'], $camps) === array_keys($w)[0] && vestra_mailbox_followup_next(array_merge(['deals'], array_keys($w)), $camps) === '');
[$ds] = ($camps['w:dsquared2'][2])(['company' => 'Shop X', 'country' => 'Italy']);
$t('DSQUARED2 dalgası: konu yalnız DSQUARED2', str_contains($ds, 'DSQUARED2') && !str_contains($ds, 'Lacoste'));
echo "\n== günlük doldurma ==\n";
$noon = strtotime(date('Y-m-d').' 10:00:00 Europe/Berlin'); $eve = strtotime(date('Y-m-d').' 17:00:00 Europe/Berlin');
$t('pay: sabah olağan pay (tavanın %60\'ı), 16:00 sonrası günün tavanı', vestra_mailbox_deals_quota_at($noon) === vestra_mailbox_deals_quota() && vestra_mailbox_deals_quota() < vestra_mailbox_cap_today() && vestra_mailbox_deals_quota_at($eve) === vestra_mailbox_cap_today());
$t('yeni butik payı: akşam da yalnız sabah payı ayrılır', vestra_mailbox_deals_reserve($eve) <= vestra_mailbox_deals_quota());
echo "\n== Almanya yalnız bugün ==\n";
$t('varsayılan: Almanya durdurulmuş', in_array('Germany', vestra_send_paused_countries(), true));
$c = vestra_mailbox_cfg(); $c['paused_off_day'] = date('Y-m-d'); file_put_contents(vestra_mailbox_file('mailbox.json'), json_encode($c));
$t('paused_off_day = bugün: durdurma yok', vestra_send_paused_countries() === []);
$c['paused_off_day'] = date('Y-m-d', time() - 86400); file_put_contents(vestra_mailbox_file('mailbox.json'), json_encode($c));
$t('dünün işareti: yine durdurulmuş', in_array('Germany', vestra_send_paused_countries(), true));
echo "\n{$ok} ok, {$fail} hata\n"; exit($fail ? 1 : 0);
