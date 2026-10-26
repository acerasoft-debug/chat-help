<?php
/* FRED PERRY + LACOSTE KAMPANYASI (26 Eki 2026, operatör: "F.Perry Polo, Sweatshirt ve Lacoste Sweatshirt ve Hoodieler
 * için özel bir kampanya yap 600 kişiye gönder … sunucudan"). Ölçülen: yalnız beş ilan, sırayla; Lacoste −% rozeti,
 * Fred Perry "yeni"; özel konu/başlık/giriş (7 dil), fiyat yok; takipte her şeyden önce; 600 dükkândan sonra ve
 * VESTRA_SPOT_UNTIL'den sonra listeden düşer. Kum havuzu: VESTRA_DATA_DIR geçici dizin. */
$ok = 0; $fail = 0;
$t = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; echo "  ok   $n\n"; } else { $fail++; echo "  HATA $n\n"; } };
$root = dirname(__DIR__).'/vestra';
$sand = sys_get_temp_dir().'/vestra-spot-'.bin2hex(random_bytes(4)); mkdir($sand.'/data', 0777, true);
define('VESTRA_DATA_DIR', $sand.'/data'); define('VESTRA_FINDER_ON', true);
require $root.'/inc/products.php'; require_once $root.'/inc/finder.php'; require_once $root.'/inc/mailbox.php'; require_once $root.'/inc/campaign_deals.php';
$L = fn($id, $b, $n, $list, $pr) => ['id' => $id, 'brand' => $b, 'name' => $n, 'mode' => $list ? 'sale' : 'fixed', 'list' => $list, 'tiers' => [['min' => 3, 'price' => $pr]], 'images' => ['/uploads/'.$id.'.jpg'], 'status' => 'approved'];
vestra_write_json('listings.json', [
  $L('fp-m3600-polo', 'Fred Perry', 'M3600 Twin Tipped Polo', 0, 41), $L('fp-m7535-sweat', 'Fred Perry', 'M7535 Crew Sweatshirt', 0, 52),
  $L('lac-crew-sweatshirt', 'Lacoste', 'SH9608 Crew Sweatshirt', 100, 68), $L('lac-fleece-hoodie', 'Lacoste', 'SH9623 Fleece Hoodie', 100, 70),
  $L('lac-zip-hoodie', 'Lacoste', 'SH9626 Zip Hoodie', 100, 73), $L('csb-tennis-club-icon-white', 'Casablanca', 'Tennis Club Icon T-Shirt — White', 0, 85), $L('d1', 'DSQUARED2', 'Logo T-Shirt', 50, 29),
]);
$p = vestra_spot_pick();
$t('yalnız altı ilan (indirimsiz Casablanca dahil), VESTRA_SPOT_IDS sırasıyla, hepsi öne çıkan', array_column($p['items'], 'id') === VESTRA_SPOT_IDS && count($p['items']) === 6 && count(array_filter($p['items'], fn($r) => !empty($r['featured']))) === 6);
$t('rozet: Lacoste −%, Fred Perry yeni (0); marka satırında yalnız Lacoste', $p['items'][0]['pct'] === 0 && $p['items'][2]['pct'] === 32 && array_keys($p['brands']) === ['Lacoste'] && $p['max'] === 32);
[$s, $b, $o] = vestra_campaign_spot('Boutique Une', 'en', $p);
$t('konu ve başlık kampanyaya özel', $s === 'Fred Perry, Lacoste & Casablanca: polos, sweatshirts, hoodies & tees · VESTRA' && $o['hero']['title'] === 'Fred Perry, Lacoste & Casablanca — polos, sweatshirts, hoodies & tees.' && str_contains($b, 'product?id=csb-tennis-club-icon-white'));
$t('giriş: iki marka + Lacoste indirimi; eski "we wrote to you" cümlesi yok', str_contains($b, 'Fred Perry polos and sweatshirts') && str_contains($b, 'up to 32% off') && !str_contains($b, 'We wrote to you recently')
   && str_contains($o['html'], 'Fred Perry polos and sweatshirts'));
$t('fiyat yok (KURAL 19), ürün bağlantıları, çıkış bağlantısı, DSQUARED2 yok', !preg_match('/€|EUR|41[.,]|52[.,]|68[.,]|70[.,]|73[.,]|85[.,]/', $b.$o['html']) && str_contains($b, 'product?id=lac-zip-hoodie') && str_contains($b, 'lead-unsubscribe') && !str_contains($b.$o['html'], 'DSQUARED2'));
foreach (['de' => 'Hoodies & T-Shirts', 'fr' => 'hoodies et t-shirts', 'it' => 'hoodie e t-shirt', 'es' => 'hoodies y camisetas', 'nl' => 'hoodies & T-shirts', 'pt' => 'hoodies e t-shirts'] as $lg => $needle) {
  [$sx, $bx] = vestra_campaign_spot('', $lg, $p); $t("dil $lg: konu + giriş değişti", str_contains($sx, $needle) && str_contains($bx, vestra_spot_texts($lg)[2]));
}
$camps = vestra_finder_campaigns();
$t('kampanya listesinde fplac', isset($camps[VESTRA_SPOT_KEY]));
$t('takip: her şeyden önce (indirim mektubu almamışa da, almışa da)', vestra_mailbox_followup_next(['edit'], $camps) === 'fplac' && vestra_mailbox_followup_next(['edit', 'deals', 'lacoste'], $camps) === 'fplac'
   && vestra_mailbox_followup_next(['edit', 'fplac'], $camps) === 'deals');
echo "\n== 3 günlük dinlenme (yalnız bu kampanya), Almanya kapalı ==\n";
$ago = fn(int $d) => date('c', time() - $d * 86400);
$fuBase = ['country' => 'France', 'source' => 'web-search', 'owner_uid' => '', 'status' => 'contacted', 'contact_via' => 'mailbox', 'added_at' => $ago(12), 'premium_brands' => ['Lacoste', 'Fred Perry']];
vestra_write_json('leads.json', [
  ['id' => 'R4', 'company' => 'Shop Quatre', 'email' => 'info@shop-quatre.example', 'last_contacted_at' => $ago(4), 'unsub_token' => 'r4'] + $fuBase,
  ['id' => 'R2', 'company' => 'Shop Deux', 'email' => 'info@shop-deux.example', 'last_contacted_at' => $ago(2), 'unsub_token' => 'r2'] + $fuBase,
  ['id' => 'RD', 'company' => 'Laden Vier', 'email' => 'info@laden-vier.example', 'last_contacted_at' => $ago(4), 'unsub_token' => 'rd', 'country' => 'Germany'] + $fuBase,
  ['id' => 'RF', 'company' => 'Shop Fait', 'email' => 'info@shop-fait.example', 'last_contacted_at' => $ago(10), 'followups' => [['at' => $ago(4), 'campaign' => 'fplac']], 'unsub_token' => 'rf'] + $fuBase,
]);
$ft = vestra_mailbox_followup_targets(50);
$t('4 gün önce yazılmış dükkân: bu kampanyaya uygun (3 gün yeter)', isset(array_column($ft, null, 'id')['R4']) && array_column($ft, '_followup', 'id')['R4'] === 'fplac');
$t('2 gün önce yazılmış: uygun değil · Almanya: kapalı · kampanyayı almış (sıradaki deals): 5 gün kuralı', !isset(array_column($ft, null, 'id')['R2']) && !isset(array_column($ft, null, 'id')['RD']) && !isset(array_column($ft, null, 'id')['RF']));
$t('gönderim anı kontrolü: fplac 3 gün, başka kampanya 5 gün', vestra_mailbox_followup_still_ok('R4', null, 'fplac') && !vestra_mailbox_followup_still_ok('R4', null, 'deals') && !vestra_mailbox_followup_still_ok('R2', null, 'fplac'));
$leads = []; for ($i = 0; $i < VESTRA_SPOT_TARGET; $i++) $leads[] = ['id' => 'S'.$i, 'followups' => [['at' => date('c'), 'campaign' => 'fplac']]];
$t('sayaç: 600 dükkân', vestra_spot_sent($leads) === 600 && vestra_spot_sent([['followups' => [['campaign' => 'deals']]]]) === 0);
vestra_write_json('leads.json', $leads);
$t('600 dükkâna gidince listeden düşer', !isset(vestra_finder_campaigns()[VESTRA_SPOT_KEY]));
$t('tarih: son günden sonra kapalı', vestra_spot_on(strtotime(VESTRA_SPOT_UNTIL.' 12:00')) && !vestra_spot_on(strtotime(VESTRA_SPOT_UNTIL.' 12:00') + 86400));
echo "\n{$ok} ok, {$fail} hata\n"; exit($fail ? 1 : 0);
