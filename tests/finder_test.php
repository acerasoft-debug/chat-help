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
$t('satıcı eylemi izinli eylem listesinde', str_contains($sel, "'seller_find_all','seller_finder_start']"));
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

echo "\n== 7. çizim — admin ve satıcı sayfası kum havuzunda GERÇEKTEN koşuyor ==\n";
/* php -l tanımsız fonksiyonu / değişkeni yakalamaz; bu depoda lint'ten geçen iki
   çağrı-zamanı hatası yaşandı. İki sayfa da tohumlu verilerle çiziliyor. */
$sb = $sand.'/render';
@mkdir($sb, 0777, true);
exec('cp -r '.escapeshellarg($root).' '.escapeshellarg($sb.'/vestra').' 2>&1', $o, $rc);
exec('rm -rf '.escapeshellarg($sb.'/vestra/data')); @mkdir($sb.'/vestra/data', 0777, true);
file_put_contents($sb.'/vestra/inc/config.php', "<?php return ['admin_pass'=>'x','mail_enabled'=>false];\n");
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
$t('admin: anahtar YOKKEN de kart + başlat formu + (isteğe bağlı) anahtar formu', str_contains($ha, 'id="finderweb"') && str_contains($ha, 'value="finder_web_start"') && str_contains($ha, 'value="save_finder_web"') && str_contains($ha, '● Hazır'));
$t('admin: sonuç kaçışlı ve notu görünür', str_contains($ha, 'Maison &lt;b&gt;Probe') && str_contains($ha, 'OPERATOR-NOTU'));
$t('admin: PHP uyarısı yok', !preg_match('/\b(Warning|Fatal error|Deprecated|Notice)\b:/', $ha));
$t('satıcı: sayfa çizildi', strlen($hs) > 10000 && str_contains($hs, 'id="finderweb"'));
$t('satıcı: başlat düğmesi (son arama 24 saatten eski) + Brevo alanı', str_contains($hs, 'value="seller_finder_start"') && str_contains($hs, 'name="mail_api_key"'));
$t('satıcı: yalnızca KENDİ araması, operatör notu YOK', str_contains($hs, 'Ihre letzten Suchen') && !str_contains($hs, 'OPERATOR-NOTU') && !str_contains($hs, 'Maison'));
$t('satıcı (lang=de): anahtar rehberi ve arama kartı ALMANCA', str_contains($hs, 'Ihr Schlüssel – so bekommen Sie ihn') && str_contains($hs, 'Websuche starten') && str_contains($hs, 'Fehlgeschlagen'));
$t('satıcı: PHP uyarısı yok', !preg_match('/\b(Warning|Fatal error|Deprecated|Notice)\b:/', $hs));

exec('rm -rf '.escapeshellarg($sand));
echo "\nTOPLAM: {$ok} gecti, {$bad} kaldi\n";
exit($bad === 0 ? 0 : 1);
