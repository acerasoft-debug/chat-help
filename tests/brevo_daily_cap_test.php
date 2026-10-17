<?php
/* Brevo partisi (send-outreach.yml, lead kipi) — 17 Eki 2026, operatör: "Brevo'dan günde 150, hak kalırsa 23.45'te
 * tamamlansın … hataları en aza indir". İş akışının GERÇEK PHP'si kum havuzunda koşar (gönderim sahte, ağsız):
 *   1. günlük sınır (member_spec daily=N): bugün Brevo'dan giden sayılır, parti kalanla kırpılır, doluysa
 *      "BITTI. gonderildi: 0" (gece taraması orada durur);
 *   2. TAZE KAYIT: koşu sürerken başka bir işin yazdığı "geri döndü" işareti ve sunucu damgası SİLİNMEZ
 *      (eskiden sonda bütün eski dizi geri yazılıyordu); bu arada geri dönen / sunucudan yazılan kayda gitmez;
 *   3. kota partiden azsa parti küçülür (bütün parti reddedilmez). */
$ok = 0; $bad = 0;
$t = function (string $n, bool $c) use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? "  ok   " : "  FAIL ").$n."\n"; };
$root = dirname(__DIR__).'/vestra';
$wf = (string)file_get_contents(dirname(__DIR__).'/.github/workflows/send-outreach.yml');

echo "== 1. betik + kablolama ==\n";
$t('günlük sınır member_spec daily= anahtarından (yeni girdi yok: 25 girdi sınırı)', str_contains($wf, "\$DAILY_MAX = max(0, (int)(\$SPEC['daily'] ?? 0));"));
$t('sonda toplu geri yazma yok', !str_contains($wf, 'if (!$DRY && $sent > 0) vestra_save_leads($leads);'));
$t('kota yetmezse parti küçülür', str_contains($wf, 'parti kalan kotaya gore kucultuldu'));
if (!preg_match("/<<'PHPEOF'\n(.*?)\n\s*PHPEOF\n/s", $wf, $m)) { $t('betik çıkarılabildi', false); echo "\nTOPLAM: {$ok} gecti, {$bad} kaldi\n"; exit(1); }
$lines = explode("\n", $m[1]); $ind = null;
foreach ($lines as $ln) { if (trim($ln) === '') continue; $w = strlen($ln) - strlen(ltrim($ln, ' ')); $ind = $ind === null ? $w : min($ind, $w); }
$php = implode("\n", array_map(fn($ln) => substr($ln, (int)$ind), $lines));
/* Gönderim sahte: vestra_send_mail → vestra_test_send_mail (kayıt tutar; 1. gönderimde "başka bir iş" leads.json'a yazar). */
$php = str_replace('vestra_send_mail(', 'vestra_test_send_mail(', $php);
/* Kota sahte (TEST_CREDITS): partiden az kota kalınca partinin küçüldüğü dal da koşsun. */
$php = str_replace(['vestra_mail_bulk_allowed(', 'vestra_mail_credits_left()'], ['vestra_test_bulk_allowed(', 'vestra_test_credits_left()'], $php);

$sb = sys_get_temp_dir().'/vestra_bdc_'.bin2hex(random_bytes(4)); $ph = $sb.'/public_html';
@mkdir($ph.'/data', 0777, true);
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/inc', FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
foreach ($rii as $f) { $dst = $ph.'/inc/'.substr($f->getPathname(), strlen($root.'/inc/')); if ($f->isDir()) @mkdir($dst, 0777, true); else { @mkdir(dirname($dst), 0777, true); copy($f->getPathname(), $dst); } }
file_put_contents($sb.'/hook.php', '<?php
function vestra_test_send_mail($to, $subject, $body, $replyTo = "", $from = "", $cfg = null, $hero = "", array $opts = []) {
  static $n = 0; $n++;
  file_put_contents(getenv("HOME")."/sent.log", $to."\n", FILE_APPEND);
  if ($n === 1 && getenv("SIM_RACE") === "1") {   /* koşu sürerken başka işler yazıyor */
    $f = getenv("HOME")."/public_html/data/leads.json"; $L = json_decode((string)file_get_contents($f), true);
    foreach ($L as &$l) {
      if ($l["id"] === "L2") { $l["status"] = "bounced"; $l["bounce_reason"] = "mailbox: 550 user unknown"; $l["bounced_at"] = date("c"); }
      if ($l["id"] === "L3") { $l["last_contacted_at"] = date("c", time() + 5); $l["contact_via"] = "mailbox"; $l["followups"] = [["at" => date("c"), "campaign" => "deals"]]; }
      if ($l["id"] === "L9") { $l["status"] = "bounced"; $l["bounce_reason"] = "mailbox: 550 no such user"; }
    }
    unset($l); $L[] = ["id" => "NEW1", "company" => "Found Meanwhile", "email" => "info@found.example", "status" => "new", "last_contacted_at" => ""];
    file_put_contents($f, json_encode($L));
  }
  return true;
}
function vestra_test_credits_left(): ?int { $c = getenv("TEST_CREDITS"); return ($c === false || $c === "") ? null : (int)$c; }
function vestra_test_bulk_allowed(int $need, int $reserve = 60): array {
  $left = vestra_test_credits_left();
  if ($left === null) return [true, "kalan kota okunamadi -- gonderim yine de deneniyor"];
  $usable = $left - $reserve;
  if ($usable <= 0) return [false, "gunluk kota bitti"];
  if ($need > $usable) return [false, "istenen {$need}, kullanilabilir {$usable}"];
  return [true, "kota tamam: {$left} kalan, {$reserve} ayrilmis, bu parti {$need}"];
}');
$ago = fn(int $d) => date('c', time() - $d * 86400);
$base = ['country' => 'France', 'status' => 'contacted', 'source' => 'web-search', 'contact_via' => 'mailbox', 'owner_uid' => '', 'website' => ''];
$leads = [['id' => 'L0', 'company' => 'Déjà Brevo', 'email' => 'info@deja.example', 'last_contacted_at' => $ago(9), 'last_newcollection_at' => date('c'), 'unsub_token' => 't0'] + $base];
for ($i = 1; $i <= 6; $i++) $leads[] = ['id' => 'L'.$i, 'company' => 'Boutique '.$i, 'email' => 'info@boutique'.$i.'.example', 'last_contacted_at' => $ago(10 + $i), 'unsub_token' => 't'.$i, 'notes' => 'ilk'] + $base;
$leads[] = ['id' => 'L9', 'company' => 'Not Picked', 'email' => 'info@notpicked.example', 'last_contacted_at' => $ago(1), 'unsub_token' => 't9'] + $base;
$leads[] = ['id' => 'LB', 'company' => 'Bounced Before', 'email' => 'info@bounced.example', 'last_contacted_at' => $ago(30), 'status' => 'bounced', 'bounce_reason' => 'mailbox: 550', 'unsub_token' => 'tb'] + $base;
$leads[] = ['id' => 'LG', 'company' => 'Guessed List', 'email' => 'info@guessed.example', 'last_contacted_at' => $ago(30), 'source' => 'manual', 'unsub_token' => 'tg'] + array_diff_key($base, ['source' => 1]);
$leads[] = ['id' => 'LW', 'company' => 'Mode Grosshandel GmbH', 'email' => 'info@grosshandel.example', 'last_contacted_at' => $ago(30), 'unsub_token' => 'tw'] + $base;
/* 17 Eki 2026 incelemesi: yanıt veren / lemlist / kopya kaydı çıkmış adres / aynı dükkânın başka adresine dün yazılmış */
$leads[] = ['id' => 'LR', 'company' => 'Replied Shop', 'email' => 'info@replied.example', 'last_contacted_at' => $ago(30), 'status' => 'replied', 'unsub_token' => 'tr'] + $base;
$leads[] = ['id' => 'LL', 'company' => 'Lemlist Only', 'email' => 'info@lemlist-only.example', 'last_contacted_at' => $ago(30), 'contact_via' => 'lemlist', 'unsub_token' => 'tl'] + $base;
$leads[] = ['id' => 'LU1', 'company' => 'Dup Unsub', 'email' => 'info@dup.example', 'last_contacted_at' => $ago(30), 'status' => 'unsubscribed', 'unsub_token' => 'tu1'] + $base;
$leads[] = ['id' => 'LU2', 'company' => 'Dup Unsub 2', 'email' => 'INFO@dup.example ', 'last_contacted_at' => $ago(30), 'unsub_token' => 'tu2'] + $base;
$leads[] = ['id' => 'LS1', 'company' => 'Sibling Shop', 'email' => 'info@sibling.example', 'last_contacted_at' => $ago(1), 'followups' => [['at' => $ago(1), 'campaign' => 'deals']], 'unsub_token' => 'ts1'] + $base;
$leads[] = ['id' => 'LS2', 'company' => 'Sibling Shop', 'email' => 'sales@sibling.example', 'last_contacted_at' => $ago(30), 'unsub_token' => 'ts2'] + $base;
$leads[] = ['id' => 'LD1', 'company' => 'Declined Shop', 'email' => 'info@stopshop.example', 'last_contacted_at' => $ago(30), 'status' => 'declined', 'unsub_token' => 'td1'] + $base;
$leads[] = ['id' => 'LD2', 'company' => 'Declined Shop', 'email' => 'sales@stopshop.example', 'last_contacted_at' => $ago(30), 'unsub_token' => 'td2'] + $base;
$leads[] = ['id' => 'LA', 'company' => 'Registered Buyer', 'email' => 'Buyer@Registered.example', 'last_contacted_at' => $ago(30), 'unsub_token' => 'ta'] + $base;
file_put_contents($ph.'/data/accounts.json', json_encode([['email' => 'buyer@registered.example', 'type' => 'buyer']]));
file_put_contents($ph.'/data/leads.json', json_encode($leads));
file_put_contents($sb.'/run.php', $php);
$run = function (array $env) use ($sb, $ph): string {
  @unlink($sb.'/sent.log');
  $e = 'HOME='.escapeshellarg($sb).' SEND_COUNT=50 SEND_DRY=false SEND_NEWCOLL=true SEND_NC_LETTER=winter SEND_MIN_BRANDS=0 SEND_NC_MINDAYS=2 SEND_DNS=false SEND_RESERVE=0';
  foreach ($env as $k => $v) $e .= ' '.$k.'='.escapeshellarg($v);
  return (string)shell_exec('cd '.escapeshellarg($ph).' && env '.$e.' php -d auto_prepend_file='.escapeshellarg($sb.'/hook.php').' '.escapeshellarg($sb.'/run.php').' 2>&1');
};
$sent = fn() => array_values(array_filter(explode("\n", (string)@file_get_contents($sb.'/sent.log'))));
$byId = fn() => array_column(json_decode((string)file_get_contents($ph.'/data/leads.json'), true), null, 'id');

echo "\n== 2. günlük sınır + taze kayıt (koşu sürerken başka işler yazıyor) ==\n";
$o1 = $run(['SEND_MSPEC' => 'daily=4', 'SIM_RACE' => '1']);
$t('koşu hatasız', str_contains($o1, 'BITTI. gonderildi:') && !preg_match('/Fatal|Warning|Deprecated|Parse error/i', $o1));
$t('sınır: bugün 1 gitmiş → bu partiye 3', str_contains($o1, 'gunluk Brevo siniri: 4 | bugun giden: 1 | bu partiye kalan: 3'));
$s1 = $sent();
$t('ilki gitti; bu arada geri dönen (L2) ve sunucudan yazılan (L3) atlandı', $s1 === ['info@boutique6.example'] || (count($s1) === 1 && !in_array('info@boutique2.example', $s1, true) && !in_array('info@boutique3.example', $s1, true)));
$t('kütükte atlama nedenleri', str_contains($o1, 'ATLANDI (bu arada geri dondu / cikti / kayit oldu)') && str_contains($o1, 'ATLANDI (bu arada sunucudan / baska kanaldan yazildi)'));
$L = $byId();
$picked1 = array_values(array_filter(array_keys($L), fn($id) => str_starts_with((string)($L[$id]['last_newcollection_at'] ?? ''), date('Y-m-d')) && $id !== 'L0'));
$t('gönderilen kayda damga düştü (last_newcollection_at bugün), notu eklendi', count($picked1) === 1 && str_contains((string)$L[$picked1[0]]['notes'], 'ilk · winter2627('));
$t('başka işin yazdığı "geri döndü" işaretleri SİLİNMEDİ (L2, L9)', $L['L2']['status'] === 'bounced' && $L['L9']['status'] === 'bounced' && str_contains((string)$L['L9']['bounce_reason'], 'no such user'));
$t('sunucu damgası korundu (L3 takip kaydı), bu arada bulunan yeni kayıt kaybolmadı', isset($L['L3']['followups'][0]) && isset($L['NEW1']));

$o2 = $run(['SEND_MSPEC' => 'daily=4']);
$s2 = $sent();
$t('ikinci parti: bugün 2 → kalan 2, iki tane gitti (geri dönen / bugün yazılan yine yok)', str_contains($o2, 'bugun giden: 2 | bu partiye kalan: 2') && count($s2) === 2 && !str_contains($o2, 'ATLANDI (bu arada')
   && !array_intersect($s2, ['info@boutique2.example', 'info@boutique3.example', 'info@notpicked.example', $s1[0] ?? '']));
$o3 = $run(['SEND_MSPEC' => 'daily=4']);
$t('sınır doldu: 0 gönderir ve "BITTI. gonderildi: 0" yazar (gece taraması durur)', $sent() === [] && str_contains($o3, 'Bugunku Brevo siniri (4) doldu') && str_contains($o3, 'BITTI. gonderildi: 0'));
$o4 = $run(['SEND_MSPEC' => '']);
$all = array_merge($s1, $s2, $sent());
$t('yanıt veren, lemlist, kopya kaydı çıkmış adres ve dükkânına dün yazılmış kardeş adres HİÇ almadı', !array_intersect($all, ['info@replied.example', 'info@lemlist-only.example', 'info@dup.example', 'sales@sibling.example', 'info@sibling.example']));
$t('reddetmiş dükkânın öteki adresi ve kayıtlı alıcı HİÇ almadı', !array_intersect(array_map('strtolower', $all), ['sales@stopshop.example', 'info@stopshop.example', 'buyer@registered.example']));
$t('geri dönmüş adrese, tahmin listesine ve toptancıya HİÇ gitmedi; kütükte kalite satırı', !array_intersect($all, ['info@bounced.example', 'info@guessed.example', 'info@grosshandel.example'])
   && preg_match('/KALITE: geri donmus adres [1-9]\d* \| tahmin listesi \/ hedef disi \/ durdurulan ulke \/ olu [1-9]/', $o4));
$t('sınır verilmezse eski davranış (sınırsız): kalan uygun kayıt gider', count($sent()) >= 1 && !str_contains($o4, 'gunluk Brevo siniri'));

echo "\n== 3. kota partiden azsa parti küçülür (işlemsel pay korunur) ==\n";
$q = []; for ($i = 1; $i <= 4; $i++) $q[] = ['id' => 'Q'.$i, 'company' => 'Quota '.$i, 'email' => 'info@quota'.$i.'.example', 'last_contacted_at' => $ago(20 + $i), 'unsub_token' => 'q'.$i] + $base;
file_put_contents($ph.'/data/leads.json', json_encode($q));
$o5 = $run(['SEND_MSPEC' => 'daily=150', 'TEST_CREDITS' => '7', 'SEND_RESERVE' => '5']);
$t('kalan 7, ayrılan 5 → 4 kişilik parti 2\'ye küçüldü, yalnız 2 gitti ve damgalandı', str_contains($o5, 'parti kalan kotaya gore kucultuldu') && count($sent()) === 2
   && count(array_filter($byId(), fn($l) => trim((string)($l['last_newcollection_at'] ?? '')) !== '')) === 2);
$o6 = $run(['SEND_MSPEC' => 'daily=150', 'TEST_CREDITS' => '5', 'SEND_RESERVE' => '5']);
$t('kota ayrılan paya inmişse hiç gönderilmez, "BITTI. gonderildi: 0" yazar', $sent() === [] && str_contains($o6, 'DURDURULDU. Hicbir e-posta gonderilmedi.') && str_contains($o6, 'BITTI. gonderildi: 0'));
$t('üye kipi de taze listeye yazar (toplu geri yazma yok)', !str_contains($wf, 'vestra_save_leads($leads2)') && str_contains($wf, '$accFresh = auth_accounts();'));
exec('rm -rf '.escapeshellarg($sb));
echo "\nTOPLAM: {$ok} gecti, {$bad} kaldi\n";
exit($bad ? 1 : 0);
