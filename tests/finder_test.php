<?php
/* "Web'den müşteri bul" paneli (vestra/inc/finder.php) — 8 Eki 2026.
 *
 * Motor GitHub Actions'ta çalışır; panel onu workflow_dispatch ile başlatır ve sonucu
 * data/finder_runs.json'dan okur. Bu test ağsız ölçülebilen her şeyi KUM HAVUZUNDA
 * gerçekten koşturur:
 *   1. girdi temizleme — panel formu GitHub'daki bir işe parametre gönderiyor; beyaz
 *      liste dışı hiçbir şey geçmemeli (dil, ISO kodu, "Ülke|Şehir", alan adı, sayı sınırı)
 *   2. kilit — aynı anda tek arama (workflow concurrency grubu üçüncü isteği SESSİZCE
 *      iptal ediyor); 95 dk'dan eski "çalışıyor" kaydı kilidi tutmamalı
 *   3. satıcı sınırı — satıcı başına 24 saatte bir arama
 *   4. anahtarsız başlatma — GitHub token YOKSA istek ağa çıkmadan SIRAYA girer
 *      (find-customers-queue.yml 10 dk içinde başlatır); kilit ve satıcı sınırı yine işler
 *   5. sonuç HTML'i — firma adı/e-posta dışarıdan gelen metin: kaçışlı basılmalı;
 *      satıcı (İngilizce) operatör notlarını ve GitHub linkini görmemeli
 *   6. panel bağlantısı — admin.php ve seller.php finder.php'yi KENDİSİ yüklüyor,
 *      eylemler CSRF'li formdan geliyor, Brevo rehber linkleri yerinde. */

$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? "  ok   " : "  FAIL ").$n."\n"; };
$root = dirname(__DIR__).'/vestra';

$sand = sys_get_temp_dir().'/vestra_finder_'.bin2hex(random_bytes(4));
@mkdir($sand.'/data', 0775, true);
define('VESTRA_DATA_DIR', $sand.'/data');
define('VESTRA_FINDER_ON', true);   // web araması 8 Eki'de kapatıldı; kapı mantığı yine de sınanır
require $root.'/inc/products.php';
require_once $root.'/inc/finder.php';

echo "== 1. girdi temizleme ==\n";
$c = vestra_finder_clean_input([
  'langs' => 'de, IT ,xx, fr;de', 'countries' => 'it,FR,ITA,1x,es',
  'cities' => "Italy|Milano, France|Paris; bozuk, Spain|Madrid\$(rm -rf), Germany|",
  'extra_queries' => "\"Dsquared2\" boutique\n`whoami` \$HOME",
  'seed_domains' => 'https://shop-ornek.de/kontakt, kotu domain, ornek.it; javascript:alert(1)',
  'queries_per_run' => '9999', 'dry_run' => '1',
]);
$t('diller beyaz listeden, tekrar yok', $c['langs'] === 'de,it,fr');
$t('ülke yalnızca 2 harf ISO', $c['countries'] === 'IT,FR,ES');
$t('şehir "Ülke|Şehir", özel karakter atılır, eksik çift atılır', $c['cities'] === 'Italy|Milano, France|Paris, Spain|Madridrm -rf');
$t('ekstra sorguda satır sonu / ters tırnak / $ yok', !preg_match('/[\r\n`$]/', $c['extra_queries']) && str_contains($c['extra_queries'], 'Dsquared2'));
$t('seed: yalnızca geçerli alan adı, yol atılır', $c['seed_domains'] === 'shop-ornek.de, ornek.it');
$t('sorgu sayısı 5..80', $c['queries_per_run'] === '80' && vestra_finder_clean_input(['queries_per_run' => '1'])['queries_per_run'] === '5');
$t('dry_run string', $c['dry_run'] === 'true' && vestra_finder_clean_input([])['dry_run'] === 'false');
$many = implode(',', array_map(fn($i) => "Italy|City{$i}", range(1, 30)));
$t('şehir en çok 12', count(explode(', ', vestra_finder_clean_input(['cities' => $many])['cities'])) <= 12);

echo "\n== 2. kilit ==\n";
$now = date('c');
vestra_finder_save_runs([['id' => 'FRold', 'owner' => '', 'requested_at' => date('c', time() - 100 * 60), 'dispatched_at' => date('c', time() - 100 * 60), 'status' => 'running']]);
$t('95 dk\'dan eski "running" kilit tutmaz', vestra_finder_active() === null);
vestra_finder_save_runs([['id' => 'FRnow', 'owner' => '', 'requested_at' => $now, 'status' => 'requested'],
                         ['id' => 'FRdone', 'owner' => '', 'requested_at' => date('c', time() - 3600), 'status' => 'done']]);
$t('taze istek kilidi tutar', (vestra_finder_active()['id'] ?? '') === 'FRnow');
vestra_finder_save_runs([['id' => 'FRq7h', 'owner' => '', 'requested_at' => date('c', time() - 7 * 3600), 'status' => 'requested']]);
$t('7 saattir sırada bekleyen kilit tutmaz', vestra_finder_active() === null);
vestra_finder_save_runs([['id' => 'FRnow', 'owner' => '', 'requested_at' => $now, 'status' => 'requested'],
                         ['id' => 'FRdone', 'owner' => '', 'requested_at' => date('c', time() - 3600), 'status' => 'done']]);
$t('bitmiş kayıt aktif değil', !vestra_finder_is_active(['status' => 'done', 'requested_at' => $now]));

echo "\n== 3/4. başlatma kapıları (ağa çıkmadan — token yok) ==\n";
[$ok1, $m1] = vestra_finder_start(['cities' => 'Italy|Milano'], '', 'admin');
$t('çalışan arama varken yenisi reddedilir ve nedenini söyler', !$ok1 && str_contains($m1, 'zaten çalışıyor'));
$t('reddedilen istek kayıt açmaz', count(vestra_finder_runs()) === 2);
vestra_finder_save_runs([['id' => 'FRdone', 'owner' => '', 'requested_at' => date('c', time() - 3600), 'status' => 'done']]);
[$okQ, $mQ, $idQ] = vestra_finder_start(['cities' => 'Italy|Milano', 'countries' => 'it'], 'S1', 'seller');
$rq = vestra_finder_runs('S1')[0] ?? [];
$t('token yoksa istek SIRAYA girer (ağsız)', $okQ && str_contains($mQ, 'sıraya alındı') && ($rq['id'] ?? '') === $idQ);
$t('sıradaki istek: requested, dispatched_at YOK, parametreler temiz', ($rq['status'] ?? '') === 'requested' && empty($rq['dispatched_at']) && ($rq['params']['countries'] ?? '') === 'IT');
$t('sıradaki istek kilidi tutar', (vestra_finder_active()['id'] ?? '') === $idQ);
vestra_finder_save_runs(array_map(fn($r) => ($r['id'] === $idQ ? ['status' => 'done'] + $r : $r), vestra_finder_runs()));
[$okS, $mS] = vestra_finder_start(['cities' => 'Italy|Roma'], 'S1', 'seller');
$t('satıcı 24 saatte bir', !$okS && str_starts_with($mS, 'You can start'));
$t('satıcı kaydı sahibine göre süzülür', count(vestra_finder_runs('S1')) === 1 && count(vestra_finder_runs('S2')) === 0);
$t('her zaman başlatılabilir (anahtar şart değil)', vestra_finder_ready());

echo "\n== 5. sonuç HTML'i ==\n";
$evil = [['id' => 'FRx', 'owner' => 'S1', 'requested_at' => $now, 'status' => 'done', 'queries' => 3, 'candidates' => 9, 'analyzed' => 5, 'added_count' => 1,
          'added' => [['company' => '<script>alert(1)</script>Boutique', 'email' => 'info@x.it', 'country' => 'Italy', 'brands' => ['Dsquared2', 'Balmain']]],
          'notes' => ['Brave anahtarı girilmemiş'], 'run_url' => 'https://github.com/acerasoft-debug/chat-help/actions/runs/1']];
$hA = vestra_finder_runs_html($evil, true, ['S1' => 'Satıcı & Co']);
$t('firma adı kaçışlı', !str_contains($hA, '<script>alert(1)') && str_contains($hA, '&lt;script&gt;'));
$t('admin: sahibi, notu ve linki görür', str_contains($hA, 'Satıcı &amp; Co') && str_contains($hA, 'Brave anahtarı') && str_contains($hA, 'actions/runs/1'));
$hS = vestra_finder_runs_html($evil, false, [], 4, true);
$t('satıcı: İngilizce, not ve GitHub linki YOK', str_contains($hS, 'new customers with a real email') && !str_contains($hS, 'Brave anahtarı') && !str_contains($hS, 'actions/runs/1'));
$t('boş liste mesajı', str_contains(vestra_finder_runs_html([], false, [], 4, true), 'No searches yet'));

echo "\n== 6. panel bağlantısı (kaynak) ==\n";
$adm = (string)file_get_contents($root.'/admin.php'); $sel = (string)file_get_contents($root.'/seller.php');
$t('admin.php finder.php\'yi kendisi yüklüyor', substr_count($adm, "require_once __DIR__.'/inc/finder.php'") >= 2);
$t('seller.php finder.php\'yi kendisi yüklüyor', substr_count($sel, "require_once __DIR__.'/inc/finder.php'") >= 2);
$t('admin: başlat + anahtar formları CSRF\'li', (bool)preg_match("~csrfField\(\) \?><input type=\"hidden\" name=\"_action\" value=\"finder_web_start\"~", $adm)
                                            && (bool)preg_match("~csrfField\(\) \?><input type=\"hidden\" name=\"_action\" value=\"save_finder_web\"~", $adm));
$t('satıcı eylemi izinli eylem listesinde', str_contains($sel, "'seller_find_all','seller_finder_start','seller_ai_generate','seller_ai_save','seller_ai_stop']"));
$t('satıcı Brevo anahtarını kaydedebiliyor (boş = koru, işaret = sil)', str_contains($sel, "'mail_api_key'=>!empty(\$_POST['brevo_clear'])?''"));
foreach (['https://app.brevo.com/settings/keys/api', 'https://help.brevo.com/hc/en-us/articles/209467485', 'https://help.brevo.com/hc/en-us/articles/208836149', 'https://help.brevo.com/hc/en-us/articles/12163873383186'] as $u)
  $t('Brevo rehber linki adminde: '.basename($u), str_contains($adm, $u));
foreach (['https://www.brevo.com/', 'https://app.brevo.com/settings/keys/api', 'https://help.brevo.com/hc/en-us/articles/209467485'] as $u)
  $t('Brevo sade rehber linki satıcıda: '.$u, str_contains($sel, $u));
/* Satıcı rehberi ÇEVRİLİ: her metin t() ile basılıyor ve 8 sözlükte de var. */
foreach (['🔑 Your key — how to get it', 'Open %s and click “Sign up free”. It is free (300 emails per day).', '🌐 Start web search',
          'You do not need any key to search — only to send from your own address.'] as $k) {
  $t('satıcı metni t() ile: '.mb_substr($k, 0, 24), str_contains($sel, "('".$k."')"));
  $miss = [];
  foreach (['de','fr','es','it','pt','ru','ar','ja'] as $L) { $d = require $root."/inc/lang/{$L}.php"; if (!isset($d[$k]) || $d[$k] === '') $miss[] = $L; }
  $t('8 dilde çevirisi var: '.mb_substr($k, 0, 24), !$miss);
}
$t('GitHub token linki adminde', str_contains($adm, 'https://github.com/settings/personal-access-tokens/new'));
$t('anahtar alanları password + autocomplete kapalı', (bool)preg_match('~type="password" name="gh_token"[^>]*autocomplete="new-password"~', $adm) && (bool)preg_match('~type="password" name="brave_key"[^>]*autocomplete="new-password"~', $adm));

echo "\n== 8. kampanya seçimi + gönderim hedefleri (ağsız) ==\n";
vestra_write_json('leads.json', [
  ['id' => 'L1', 'company' => 'Boutique Una', 'email' => 'info@una.example', 'country' => 'Italy', 'source' => 'web-search', 'owner_uid' => '', 'status' => 'new', 'last_contacted_at' => '', 'unsub_token' => 'tok1'],
  ['id' => 'L2', 'company' => 'Maison Deux', 'email' => 'contact@deux.example', 'country' => 'France', 'source' => 'web-search', 'owner_uid' => '', 'status' => 'new', 'last_contacted_at' => date('c'), 'unsub_token' => 'tok2'],
  ['id' => 'L3', 'company' => 'Unsub Shop', 'email' => 'a@tre.example', 'country' => 'Spain', 'source' => 'web-search', 'owner_uid' => '', 'status' => 'unsubscribed', 'last_contacted_at' => '', 'unsub_token' => 'tok3'],
  ['id' => 'L4', 'company' => 'Seller Lead', 'email' => 'b@vier.example', 'country' => 'Germany', 'source' => 'web-search', 'owner_uid' => 'sellX', 'status' => 'new', 'last_contacted_at' => '', 'unsub_token' => 'tok4'],
  ['id' => 'L5', 'company' => 'Manual Lead', 'email' => 'c@funf.example', 'country' => 'Germany', 'source' => 'Manual', 'owner_uid' => '', 'status' => 'new', 'last_contacted_at' => '', 'unsub_token' => 'tok5'],
  ['id' => 'L6', 'company' => 'Kein Email', 'email' => '', 'country' => 'Germany', 'source' => 'web-search', 'owner_uid' => '', 'status' => 'new', 'last_contacted_at' => '', 'unsub_token' => 'tok6'],
  ['id' => 'L7', 'company' => 'Laden Sieben', 'email' => 'INFO@una.example', 'country' => 'Germany', 'source' => 'web-search', 'owner_uid' => '', 'status' => 'new', 'last_contacted_at' => '', 'unsub_token' => 'tok7'],
]);
$tg = vestra_finder_send_targets(100);
$ids = array_column(array_values($tg), 'id');
$t('hedef: yalnızca yazılmamış, abonelikten çıkmamış, admin, web-search, e-postalı', (in_array('L1', $ids, true) || in_array('L7', $ids, true)) && !array_intersect(['L2', 'L3', 'L4', 'L5', 'L6'], $ids));
$t('hedef: aynı e-posta iki kez yok (büyük/küçük harf)', count(array_filter($ids, fn($x) => in_array($x, ['L1', 'L7'], true))) === 1);
$camps = vestra_finder_campaigns();
$t('kampanya listesi: Les Garage + polo + standart', isset($camps['lesgarage'], $camps['polos'], $camps['standard']));
$lead1 = ['id' => 'L1', 'company' => 'Boutique Una', 'email' => 'info@una.example', 'country' => 'Italy', 'unsub_token' => 'tok1', 'contact_name' => ''];
foreach (['lesgarage', 'polos', 'standard'] as $ck) {
  [$cs, $cb] = ($camps[$ck][2])($lead1);
  $t("örnek '{$ck}': konu + metin dolu, çıkış linki bu müşterinin", $cs !== '' && $cb !== '' && str_contains($cb, 'tok1'));
}
$t('dil ülkeden: İtalya→it, Brüksel→fr, Gent→nl, bilinmeyen→en', vestra_finder_lead_lang(['country' => 'Italy']) === 'it'
  && vestra_finder_lead_lang(['country' => 'Belgium', 'company' => 'Shop Bruxelles']) === 'fr' && vestra_finder_lead_lang(['country' => 'Belgium', 'company' => 'Gent']) === 'nl'
  && vestra_finder_lead_lang(['country' => 'Narnia']) === 'en');
$before = (string)file_get_contents(VESTRA_DATA_DIR.'/leads.json');
[$sN, $fN, $lines] = vestra_finder_send('standard', 10, true);
$t('kuru gönderim: gönderilen 0, liste dolu, leads.json DEĞİŞMEDİ', $sN === 0 && $fN === 0 && count($lines) === count($tg) && $before === (string)file_get_contents(VESTRA_DATA_DIR.'/leads.json'));
$t('bilinmeyen kampanya reddedilir', vestra_finder_send('yok', 5, true)[2] === ['Kampanya bulunamadı.']);

echo "\n== 9. Claude kampanya yazarı: sınırlar + kayıtlar (ağsız) ==\n";
require_once $root.'/inc/ai_campaign.php';
$L = vestra_ai_camp_limits();
$t('varsayılan sınırlar 3/gün, 20/ay, 300/ay platform', $L === ['per_day' => 3, 'per_month' => 20, 'platform_month' => 300]);
$t('boş kullanımda kota açık', vestra_ai_camp_quota('sellA')['ok'] && vestra_ai_camp_quota('sellA')['day_left'] === 3);
for ($i = 0; $i < 3; $i++) vestra_ai_camp_count('sellA', 1000, 500);
$qa = vestra_ai_camp_quota('sellA');
$t('3 yazımdan sonra satıcı günlük sınırda', !$qa['ok'] && $qa['why'] === 'day' && $qa['day_left'] === 0 && $qa['month_left'] === 17);
$t('başka satıcı etkilenmez', vestra_ai_camp_quota('sellB')['ok']);
$t('admin kotası platform sınırından', vestra_ai_camp_quota('')['ok'] && vestra_ai_camp_quota('')['month_left'] === 297);
$u = vestra_ai_camp_usage()[date('Y-m')];
$t('token sayacı', $u['in'] === 3000 && $u['out'] === 1500 && $u['calls'] === 3);
$u2 = vestra_ai_camp_usage(); $u2[date('Y-m')]['calls'] = 300; vestra_write_json('ai_campaign_usage.json', $u2);
$t('platform ayı dolunca herkes kapalı', vestra_ai_camp_quota('sellB')['why'] === 'platform' && !vestra_ai_camp_quota('')['ok']);
if (!vestra_ai_camp_on()) $t('anahtar yoksa ağa çıkmadan "nokey"', vestra_ai_camp_generate('sellB', 'X', [['id' => 'p1']], [])[1] === 'nokey');
vestra_write_json('ai_campaigns.json', [
  ['id' => 'ACa', 'owner' => 'sellA', 'created_at' => date('c'), 'lang' => 'en', 'subject' => 'Hello {{company}}', 'body' => 'Dear {{company}}, new stock.', 'active' => false],
  ['id' => 'ACb', 'owner' => 'sellB', 'created_at' => date('c'), 'lang' => 'de', 'subject' => 'B', 'body' => 'B body', 'active' => true],
  ['id' => 'ACadm', 'owner' => '', 'created_at' => date('c'), 'lang' => 'fr', 'subject' => 'Admin {{company}}', 'body' => 'Admin body {{company}}', 'active' => false],
]);
$t('başkasının kampanyasını düzenleyemez', !vestra_ai_camp_save_edit('ACb', 'sellA', 'x', 'y', true) && vestra_ai_camp_active('sellB')['id'] === 'ACb');
$t('kendi kampanyasını etkinleştirir', vestra_ai_camp_save_edit('ACa', 'sellA', 'Hi {{company}}', 'Dear {{company}}, edited.', true) && vestra_ai_camp_active('sellA')['body'] === 'Dear {{company}}, edited.');
[$rs, $rb] = vestra_lead_render_email($lead1, vestra_ai_camp_template(vestra_ai_camp_active('sellA')));
$t('gönderimde {{company}} dolar + çıkış linki eklenir', $rs === 'Hi Boutique Una' && str_contains($rb, 'Dear Boutique Una') && str_contains($rb, 'lead-unsubscribe?token=tok1'));
vestra_ai_camp_deactivate('sellA');
$t('durdurunca standart davet (sellB etkilenmez)', vestra_ai_camp_active('sellA') === null && vestra_ai_camp_active('sellB') !== null);
$camps = vestra_finder_campaigns();
$t('admin Claude kampanyası gönderim listesinde, satıcınınki DEĞİL', isset($camps['ai:ACadm']) && !isset($camps['ai:ACa']) && !isset($camps['ai:ACb']));
[$as] = ($camps['ai:ACadm'][2])($lead1);
$t('admin Claude kampanyası çizilir', $as === 'Admin Boutique Una');
$pl = vestra_ai_camp_product_line(['id' => 'P 1', 'brand' => 'Kenzo', 'name' => 'Tee', 'moq' => 10, 'list' => 12.5], true);
$t('ürün satırı: yalnızca kayıttaki bilgiler + kodlanmış link', str_contains($pl, 'Kenzo — Tee') && str_contains($pl, 'minimum order: 10 pcs') && str_contains($pl, '12.50 EUR') && str_contains($pl, 'id=P%201'));
$t('fiyat istenmezse fiyat yok', !str_contains(vestra_ai_camp_product_line(['id' => 'x', 'list' => 9], false), 'EUR'));
$t('model sabiti', VESTRA_AI_CAMP_MODEL === 'claude-opus-5-5');

echo "\n== 9b. satıcının KENDİ Claude anahtarı (ağsız) ==\n";
$good = 'sk-ant-api03-'.str_repeat('A', 40);
$t('biçim: sk-ant- olmayan reddedilir, ağa çıkmadan', vestra_ai_camp_key_check('xkeysib-123', function () { throw new Exception('ag'); }) === [false, 'format']);
$t('doğrulama 200 → ok', vestra_ai_camp_key_check($good, fn() => 200) === [true, 'ok']);
$t('doğrulama 401 → invalid', vestra_ai_camp_key_check($good, fn() => 401) === [false, 'invalid']);
$t('doğrulama 403 → forbidden', vestra_ai_camp_key_check($good, fn() => 403) === [false, 'forbidden']);
$t('doğrulama 0/500 → unreachable', vestra_ai_camp_key_check($good, fn() => 0) === [false, 'unreachable'] && vestra_ai_camp_key_check($good, fn() => 500)[1] === 'unreachable');
$t('anahtar yokken key_for platformdan (ya da boş)', vestra_ai_camp_key_for('sellC')[1] === (vestra_ai_camp_on() ? 'platform' : ''));
$t('kaydet + geri oku', vestra_ai_camp_seller_key_save('sellC', $good) && vestra_ai_camp_seller_key('sellC') === $good);
$t('dosya 0600', (fileperms(VESTRA_DATA_DIR.'/seller_ai_keys.json') & 0777) === 0600);
$t('key_for: satıcının kendisi önce', vestra_ai_camp_key_for('sellC') === [$good, 'own'] && vestra_ai_camp_on_for('sellC'));
$t('başka satıcı onun anahtarını almaz', vestra_ai_camp_key_for('sellB')[1] !== 'own');
$t('admin ("") satıcı anahtarı kullanmaz', vestra_ai_camp_key_for('')[1] !== 'own');
$qc = vestra_ai_camp_quota('sellC');
$t('kendi anahtarı: platform ayı dolu olsa da kota açık', $qc['ok'] && $qc['own'] === true && $qc['day_left'] === null);
$callsBefore = (int)vestra_ai_camp_usage()[date('Y-m')]['calls'];
vestra_ai_camp_count('sellC', 1200, 700, true);
$um = vestra_ai_camp_usage()[date('Y-m')];
$t('kendi anahtarıyla yazım platform sayacına/maliyetine YAZILMAZ', (int)$um['calls'] === $callsBefore && $um['in'] === 3000 && $um['out'] === 1500);
$t('satıcının kendi sayacı', $um['owners']['sellC']['own_calls'] === 1 && $um['owners']['sellC']['own_in'] === 1200 && $um['own_calls'] === 1);
$t('sil → platforma döner', vestra_ai_camp_seller_key_save('sellC', '') && vestra_ai_camp_seller_key('sellC') === '' && !vestra_ai_camp_quota('sellC')['own']);
$sp = (string)file_get_contents($root.'/seller.php');
$t('satıcı paneli: anahtar formu + rehber linkleri + doğrulama', str_contains($sp, "value=\"seller_ai_key\"") && str_contains($sp, 'console.anthropic.com/settings/keys')
   && str_contains($sp, 'console.anthropic.com/settings/billing') && str_contains($sp, 'vestra_ai_camp_key_check('));
$ap = (string)file_get_contents($root.'/admin.php');
$t('admin: anahtar kaydetmeden önce sınanıyor', str_contains($ap, 'vestra_ai_camp_key_check($k)'));
$langs = ['de', 'fr', 'it', 'es', 'pt', 'ru', 'ar', 'ja']; $miss = [];
foreach ($langs as $lg) { $L2 = require $root.'/inc/lang/'.$lg.'.php'; foreach (['Your own Claude key', 'Save key', 'Open %s and sign up (email or Google).', 'Your Anthropic account has no credit left. Add credit in the Anthropic console (Billing), then try again.'] as $k) if (!isset($L2[$k])) $miss[] = $lg.':'.$k; }
$t('yeni satıcı metinleri 8 dilde', !$miss);
$t('%s yer tutucusu çeviride korunuyor', !array_filter($langs, function ($lg) use ($root) { $L2 = require $root.'/inc/lang/'.$lg.'.php'; return substr_count($L2['Open %s and sign up (email or Google).'], '%s') !== 1 || substr_count($L2['This month: %d campaigns'], '%d') !== 1; }));

$aic = (string)file_get_contents($root.'/inc/ai_campaign.php');
$t('istek: yapılandırılmış çıktı + ret/uzunluk durumu ele alınıyor', str_contains($aic, "'json_schema'") && str_contains($aic, "'refusal'") && str_contains($aic, "'max_tokens'"));

echo "\n== 7. çizim — admin ve satıcı sayfası kum havuzunda GERÇEKTEN koşuyor ==\n";
/* php -l tanımsız fonksiyonu / değişkeni yakalamaz; bu depoda lint'ten geçen iki
   çağrı-zamanı hatası yaşandı. İki sayfa da tohumlu verilerle çiziliyor. */
$sb = $sand.'/render';
@mkdir($sb, 0777, true);
exec('cp -r '.escapeshellarg($root).' '.escapeshellarg($sb.'/vestra').' 2>&1', $o, $rc);
exec('rm -rf '.escapeshellarg($sb.'/vestra/data')); @mkdir($sb.'/vestra/data', 0777, true);
/* Claude anahtarı (sahte) kayıtlı: kartlar formu göstermeli — çizim ağa çıkmaz. */
file_put_contents($sb.'/vestra/inc/config.php', "<?php return ['admin_pass'=>'x','mail_enabled'=>false,'anthropic_key'=>'sk-ant-probe'];\n");
file_put_contents($sb.'/vestra/data/listings.json', json_encode([
  ['id' => 'PRseller1', 'seller_uid' => 'sell0000probe000', 'brand' => 'ProbeBrand', 'name' => 'Seller Polo', 'cat' => 'Polo', 'status' => 'approved'],
  ['id' => 'PRadmin1', 'seller_uid' => 'otherSeller', 'brand' => 'AdminBrand', 'name' => 'Other Jacket', 'cat' => 'Jacket', 'status' => 'approved']]));
file_put_contents($sb.'/vestra/data/ai_campaigns.json', json_encode([
  ['id' => 'ACsel', 'owner' => 'sell0000probe000', 'created_at' => date('c'), 'lang' => 'de', 'subject' => 'SELLER-CAMP {{company}}', 'body' => 'Hallo {{company}}', 'active' => true],
  ['id' => 'ACadmR', 'owner' => '', 'created_at' => date('c'), 'lang' => 'fr', 'subject' => 'ADMIN-CAMP <i>x</i>', 'body' => 'Bonjour {{company}}', 'active' => false]]));
file_put_contents($sb.'/vestra/data/leads.json', json_encode([
  ['id' => 'LDr1', 'company' => 'Render Boutique', 'email' => 'info@render-boutique.example', 'country' => 'France', 'source' => 'web-search', 'owner_uid' => '', 'status' => 'new', 'last_contacted_at' => '', 'unsub_token' => 'tr1']]));
/* Anahtar YOK: kart yine başlat formunu göstermeli (sistem anahtarsız çalışır). */
file_put_contents($sb.'/vestra/data/accounts.json', json_encode([['id' => 'sell0000probe000', 'email' => 's@probe.example', 'type' => 'seller', 'status' => 'active', 'name' => 'Probe', 'company' => 'Probe Seller GmbH']]));
file_put_contents($sb.'/vestra/data/finder_runs.json', json_encode([
  ['id' => 'FRprobeA', 'owner' => '', 'requested_at' => date('c'), 'status' => 'done', 'queries' => 40, 'candidates' => 300, 'analyzed' => 180, 'added_count' => 1,
   'added' => [['company' => 'Maison <b>Probe</b>', 'email' => 'info@probe.example', 'country' => 'France', 'brands' => ['Kenzo', 'Amiri']]], 'notes' => ['OPERATOR-NOTU']],
  ['id' => 'FRprobeS', 'owner' => 'sell0000probe000', 'requested_at' => date('c', time() - 30 * 3600), 'status' => 'failed', 'notes' => ['OPERATOR-NOTU']],
]));
$hdr = "<?php error_reporting(E_ALL); ini_set('display_errors','1'); \$_SERVER['REQUEST_METHOD']='GET'; \$_SERVER['REMOTE_ADDR']='127.0.0.1'; \$_SERVER['HTTP_HOST']='localhost'; session_start();\n";
file_put_contents($sb.'/a.php', $hdr."\$_SESSION['vadmin']=true; \$_GET=['tab'=>'prospects']; \$_SERVER['REQUEST_URI']='/admin?tab=prospects'; ob_start(); include __DIR__.'/vestra/admin.php'; echo ob_get_clean();");
file_put_contents($sb.'/s.php', $hdr."\$_SESSION['member']=true; \$_SESSION['uid']='sell0000probe000'; \$_GET=['tab'=>'find','lang'=>'de']; \$_SERVER['REQUEST_URI']='/seller?tab=find&lang=de'; chdir(__DIR__.'/vestra'); ob_start(); include __DIR__.'/vestra/seller.php'; echo ob_get_clean();");
$ha = (string)shell_exec('cd '.escapeshellarg($sb).' && php a.php 2>/dev/null');
$hs = (string)shell_exec('cd '.escapeshellarg($sb).' && php s.php 2>/dev/null');
$t('admin: sayfa çizildi, panel (giriş formu değil)', strlen($ha) > 20000 && !str_contains($ha, 'name="pass"'));
$t('admin: arama KAPALI — kart duruyor, başlat düğmesi kapalı', str_contains($ha, 'id="finderweb"') && str_contains($ha, '● Kapalı') && str_contains($ha, '⏸ Web araması kapalı') && !str_contains($ha, 'Aramayı başlat</button>'));
$t('admin: sonuç kaçışlı ve notu görünür', str_contains($ha, 'Maison &lt;b&gt;Probe') && str_contains($ha, 'OPERATOR-NOTU'));
$t('admin: PHP uyarısı yok', !preg_match('/\b(Warning|Fatal error|Deprecated|Notice)\b:/', $ha));
$t('satıcı: sayfa çizildi', strlen($hs) > 10000 && str_contains($hs, 'id="finderweb"'));
$t('satıcı: arama KAPALI — başlat düğmesi yok, Brevo alanı var', !str_contains($hs, 'Websuche starten</button>') && str_contains($hs, 'name="mail_api_key"'));
$t('satıcı: yalnızca KENDİ araması, operatör notu YOK', str_contains($hs, 'Ihre letzten Suchen') && !str_contains($hs, 'OPERATOR-NOTU') && !str_contains($hs, 'Maison'));
$t('satıcı (lang=de): anahtar rehberi ve arama kartı ALMANCA', str_contains($hs, 'Ihr Schlüssel – so bekommen Sie ihn') && str_contains($hs, 'Ihre letzten Suchen') && str_contains($hs, 'Fehlgeschlagen'));
$t('admin: gönderim kartı — örnek kampanyalar seçilebilir (Les Garage, polo, standart, Claude)', str_contains($ha, 'id="findersend"') && str_contains($ha, 'value="finder_send_campaign"')
   && str_contains($ha, 'value="lesgarage"') && str_contains($ha, 'value="polos"') && str_contains($ha, 'value="standard"') && str_contains($ha, 'value="ai:ACadmR"'));
$t('admin: Claude başlığı kaçışlı, satıcının kampanyası adminde YOK', str_contains($ha, 'ADMIN-CAMP &lt;i&gt;') && !str_contains($ha, 'SELLER-CAMP'));
$t('admin: Claude kartı — yaz formu, anahtar linki, sınırlar', str_contains($ha, 'id="aicamp"') && str_contains($ha, 'value="ai_camp_generate"') && str_contains($ha, 'https://console.anthropic.com/settings/keys') && str_contains($ha, 'name="ai_camp_per_day"'));
$t('admin: gönderilecek müşteri sayısı / örnek alıcı görünür', str_contains($ha, 'Render Boutique') || str_contains($ha, '1 müşteri'));
$t('satıcı: Claude kartı ALMANCA, yaz formu, yalnızca KENDİ ürünü', str_contains($hs, 'id="aicamp"') && str_contains($hs, 'value="seller_ai_generate"') && str_contains($hs, 'Kampagne mit KI schreiben')
   && str_contains($hs, 'Seller Polo') && !str_contains($hs, 'Other Jacket'));
$t('satıcı: kendi kampanyası "in Verwendung", adminin kampanyası YOK, kota görünür', str_contains($hs, 'in Verwendung') && str_contains($hs, 'SELLER-CAMP') && !str_contains($hs, 'ADMIN-CAMP') && str_contains($hs, 'Heute übrig: 3'));
$t('satıcı: Claude anahtarı sayfada YOK', !str_contains($hs, 'sk-ant-probe') && !str_contains($ha, 'sk-ant-probe'));
$t('satıcı: PHP uyarısı yok', !preg_match('/\b(Warning|Fatal error|Deprecated|Notice)\b:/', $hs));
$t('satıcı: kendi-anahtar bölümü + Almanca rehber (anahtar yokken)', str_contains($hs, 'id="aikey"') && str_contains($hs, 'value="seller_ai_key"') && str_contains($hs, 'Ihr eigener Claude-Schlüssel') && str_contains($hs, 'console.anthropic.com/settings/billing'));
/* Satıcı kendi anahtarını kaydetmiş: durum "kendi anahtarı", kota satırı YOK, anahtarın kendisi sayfada YOK (yalnız son 4 hane). */
$ownKey = 'sk-ant-api03-OWNSELLERKEY'.str_repeat('x', 30).'Z9q7';
file_put_contents($sb.'/vestra/data/seller_ai_keys.json', json_encode(['sell0000probe000' => ['key' => $ownKey, 'saved_at' => date('c'), 'tail' => 'Z9q7']]));
$hs2 = (string)shell_exec('cd '.escapeshellarg($sb).' && php s.php 2>/dev/null');
$t('satıcı (kendi anahtarı): yeşil durum, kota satırı yok, "Diesen Monat"', str_contains($hs2, '● Ihr eigener Claude-Schlüssel') && !str_contains($hs2, 'Heute übrig') && str_contains($hs2, 'Diesen Monat: 0 Kampagnen'));
$t('satıcı (kendi anahtarı): anahtar sayfada YOK, yalnız son 4 hane + Kaldır düğmesi', !str_contains($hs2, 'OWNSELLERKEY') && str_contains($hs2, '…Z9q7') && str_contains($hs2, 'name="ai_key_clear"'));
$t('satıcı (kendi anahtarı): PHP uyarısı yok', !preg_match('/\b(Warning|Fatal error|Deprecated|Notice)\b:/', $hs2));

exec('rm -rf '.escapeshellarg($sand));
echo "\nTOPLAM: {$ok} gecti, {$bad} kaldi\n";
exit($bad === 0 ? 0 : 1);
