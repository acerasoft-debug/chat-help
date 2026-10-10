<?php
/* VESTRA — kendi posta kutusundan (support@vestrasales.com) itibarlı, kotasız gönderim
 * köprüsü. Sunucu giden SMTP'yi kapatıyor; bu yüzden GÖNDERİMİ GitHub koşucusu yapar,
 * SEÇİM ve METİN'i (Brevo ile birebir aynı HTML) burada sunucu üretir, SONUÇ'u yine burada
 * damgalar. Böylece mektup alan adının KENDİ sağlayıcısından (DKIM imzalı) gider = itibarlı.
 *
 * Alt komutlar (CLI):
 *   list <N> <campaignKey>   seçilen, temiz, yazılmamış leadleri + hazır konu/HTML/metin
 *                            olarak JSON basar (stdout). Hiçbir şey YAZMAZ.
 *   stamp                    stdin'den {results:[{leadId,email,status,messageId,lang}]} okur,
 *                            leads.json'u günceller (contacted/bounced/unsubscribed).
 *   sethost <host> <port>    bulunan SMTP sunucusunu data/mailbox.json'a kaydeder (panel +
 *                            sonraki koşular için); e-posta GÖNDERMEZ.
 *
 * Seçim KURALI vestra_finder_send_targets('all') ile birebir: abonelikten çıkan, geçersiz,
 * daha önce yazılmış, çöp ve "satılık/park" adresler zaten elenir (inc/finder.php). Her
 * gönderilen lead contact_via='mailbox' damgalanır → aynı adrese ikinci kez gitmez. */
ini_set('display_errors', '1'); error_reporting(E_ALL);
$home = getenv('HOME');
require       $home.'/public_html/inc/products.php';
require_once  $home.'/public_html/inc/leads.php';
require_once  $home.'/public_html/inc/notify.php';
require_once  $home.'/public_html/inc/finder.php';
if (is_readable($home.'/public_html/inc/mailbox.php')) require_once $home.'/public_html/inc/mailbox.php';

$cmd = $argv[1] ?? '';

if ($cmd === 'list') {
  $limit = max(1, min(200, (int)($argv[2] ?? 30)));
  $campKey = (string)($argv[3] ?? 'lesgarage');
  $camps = vestra_finder_campaigns();
  if (!isset($camps[$campKey])) { fwrite(STDERR, "bilinmeyen kampanya: {$campKey}\n"); echo json_encode(['ok'=>false,'error'=>'campaign']); exit(1); }
  $builder = $camps[$campKey][2];
  /* Günlük tavan (inc/mailbox.php, varsayılan 50): elle, panelden ya da kuyruktan — hepsi sayılır. */
  if (function_exists('vestra_mailbox_left_today')) {
    $left = vestra_mailbox_left_today();
    if ($left <= 0) { echo json_encode(['ok'=>true, 'from'=>'VESTRA', 'sender_email'=>vestra_mail_house_address(), 'campaign'=>$campKey, 'count'=>0, 'items'=>[], 'note'=>'gunluk tavan doldu ('.vestra_mailbox_daily_cap().')']); exit(0); }
    $limit = min($limit, $left);
  }
  $ids = array_values(array_filter(array_map('trim', explode(',', (string)($argv[4] ?? '')))));
  if ($ids) {
    /* Elle verilen lead ID'leri (8 Eki: lemlist CSV'si 116 kişiyi "lemlist'e verildi" damgaladı ama
       lemlist'e HİÇ yüklenmedi — kimseye gitmedi). Yalnız damgası 'lemlist' ya da boş olanlar alınır;
       gerçekten yazılmış, çıkmış, geri dönmüş ya da ölü lead ALINMAZ. */
    $want = array_flip($ids); $targets = []; $seen = [];
    foreach (vestra_leads() as $i => $l) {
      if (!isset($want[(string)($l['id'] ?? '')]) || count($targets) >= $limit) continue;
      $st = (string)($l['status'] ?? 'new');
      if ($st === 'unsubscribed' || $st === 'bounced' || !empty($l['unsubscribed'])) continue;
      if (trim((string)($l['last_contacted_at'] ?? '')) !== '' && (string)($l['contact_via'] ?? '') !== 'lemlist') continue;
      $e = strtolower(trim((string)($l['email'] ?? '')));
      if (!filter_var($e, FILTER_VALIDATE_EMAIL) || isset($seen[$e])) continue;
      if (function_exists('vestra_email_is_junk') && vestra_email_is_junk($e)) continue;
      if (function_exists('vestra_lead_looks_dead') && vestra_lead_looks_dead($l)) continue;
      $seen[$e] = true; $targets[$i] = $l;
    }
  } else {
    $targets = vestra_finder_send_targets($limit, 'all');
  }
  $items = []; $fromName = 'VESTRA'; $seenAddr = [];
  foreach ($targets as $l) {
    $email = function_exists('vestra_email_clean') ? vestra_email_clean((string)($l['email'] ?? '')) : strtolower(trim((string)($l['email'] ?? '')));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($seenAddr[$email])) continue;
    $seenAddr[$email] = true;
    [$subject, $body, $opts, $from] = $builder($l);
    if ($from !== '') $fromName = $from;
    $token = (string)($l['unsub_token'] ?? '');
    $unsubUrl = $token !== '' ? 'https://vestrasales.com/lead-unsubscribe?token='.rawurlencode($token) : 'https://vestrasales.com/lead-unsubscribe';
    $items[] = [
      'leadId'   => (string)($l['id'] ?? ''),
      'email'    => $email,
      'company'  => (string)($l['company'] ?? ''),
      'lang'     => vestra_finder_lead_lang($l),
      'subject'  => $subject,
      'html'     => vestra_html_email($body, '', (array)$opts),
      'text'     => vestra_mail_text_part($body, (array)$opts),
      'listUnsub'=> $unsubUrl,
    ];
  }
  echo json_encode(['ok'=>true, 'from'=>$fromName, 'sender_email'=>vestra_mail_house_address(), 'campaign'=>$campKey, 'count'=>count($items), 'items'=>$items], JSON_UNESCAPED_UNICODE);
  exit(0);
}

if ($cmd === 'sample') {
  /* Test için örnek dükkân: gerçek bir müşteri gerekmez, kayda dokunmaz. */
  $campKey = (string)($argv[2] ?? 'lesgarage'); $to = strtolower(trim((string)($argv[3] ?? '')));
  $camps = vestra_finder_campaigns();
  if (!isset($camps[$campKey]) || !filter_var($to, FILTER_VALIDATE_EMAIL)) { echo json_encode(['ok'=>false]); exit(1); }
  $lead = ['id'=>'TEST', 'company'=>'Boutique Example', 'country'=>'France', 'email'=>$to, 'contact_name'=>'', 'unsub_token'=>''];
  [$subject, $body, $opts, $from] = ($camps[$campKey][2])($lead);
  echo json_encode(['ok'=>true, 'from'=>$from !== '' ? $from : 'VESTRA', 'sender_email'=>vestra_mail_house_address(), 'campaign'=>$campKey, 'count'=>1,
    'items'=>[['leadId'=>'TEST', 'email'=>$to, 'company'=>'Boutique Example', 'lang'=>vestra_finder_lead_lang($lead), 'subject'=>$subject,
      'html'=>vestra_html_email($body, '', (array)$opts), 'text'=>vestra_mail_text_part($body, (array)$opts), 'listUnsub'=>'https://vestrasales.com/lead-unsubscribe']]], JSON_UNESCAPED_UNICODE);
  exit(0);
}

if ($cmd === 'stamp') {
  $raw = stream_get_contents(STDIN);
  $in = json_decode((string)$raw, true);
  if (!is_array($in) || !isset($in['results']) || !is_array($in['results'])) { fwrite(STDERR, "gecersiz girdi\n"); exit(1); }
  $byId = []; $byEmail = [];
  foreach ($in['results'] as $r) {
    $id = (string)($r['leadId'] ?? ''); $em = strtolower(trim((string)($r['email'] ?? '')));
    if ($id !== '') $byId[$id] = $r; if ($em !== '') $byEmail[$em] = $r;
  }
  $leads = vestra_leads(); $sent = 0; $bounced = 0; $unsub = 0;
  foreach ($leads as &$l) {
    $id = (string)($l['id'] ?? ''); $em = strtolower(trim((string)($l['email'] ?? '')));
    $r = $byId[$id] ?? ($em !== '' ? ($byEmail[$em] ?? null) : null);
    if (!$r) continue;
    $st = (string)($r['status'] ?? '');
    if ($st === 'sent') {
      if (trim((string)($l['last_contacted_at'] ?? '')) !== '' && (string)($l['contact_via'] ?? '') !== 'lemlist') continue; // iki kez damgalama
      $l['last_contacted_at'] = date('c');
      $l['contact_via'] = 'mailbox';
      if (($l['status'] ?? 'new') === 'new') $l['status'] = 'contacted';
      $l['last_campaign'] = 'mailbox/'.(string)($r['lang'] ?? '');
      if (($mid = (string)($r['messageId'] ?? '')) !== '') $l['last_message_id'] = $mid;
      $note = trim((string)($l['notes'] ?? ''));
      $l['notes'] = trim($note.($note !== '' ? ' · ' : '').'mailbox('.(string)($r['lang'] ?? '').') '.date('Y-m-d'));
      $sent++;
    } elseif ($st === 'bounced') {
      $l['status'] = 'bounced'; $l['bounce_reason'] = 'mailbox: '.substr((string)($r['reason'] ?? 'rejected'), 0, 120); $l['bounced_at'] = date('c'); $bounced++;
    } elseif ($st === 'unsub') {
      $l['status'] = 'unsubscribed'; $unsub++;
    }
  }
  unset($l);
  if ($sent || $bounced || $unsub) vestra_save_leads($leads);
  echo json_encode(['ok'=>true, 'stamped_sent'=>$sent, 'bounced'=>$bounced, 'unsub'=>$unsub]);
  exit(0);
}

if ($cmd === 'fixemails') {
  /* Bozuk kayıtlı adresleri onarır (8 Eki: "%20elodie@eloab.fr"). Bozuk adrese posta kutusundan ya da
     lemlist damgasıyla "gönderilmiş" görünen lead aslında hiç ulaşılmamıştır → yeniden sıraya girer.
     Brevo'dan gönderilmiş (contact_via boş) olana dokunulmaz; temiz adres başka bir lead'deyse onarılmaz. */
  if (!function_exists('vestra_email_clean')) { echo json_encode(['ok'=>false,'error'=>'eski sunucu kodu']); exit(0); }
  $leads = vestra_leads(); $have = []; $fixed = 0; $requeued = 0;
  foreach ($leads as $l) $have[strtolower(trim((string)($l['email'] ?? '')))] = true;
  foreach ($leads as &$l) {
    $old = (string)($l['email'] ?? ''); $new = vestra_email_clean($old);
    if ($old === '' || $new === strtolower(trim($old)) || !filter_var($new, FILTER_VALIDATE_EMAIL) || isset($have[$new])) continue;
    $l['email'] = $new; $have[$new] = true; $fixed++;
    $via = (string)($l['contact_via'] ?? '');
    if (($via === 'mailbox' || $via === 'lemlist') && ($l['status'] ?? '') !== 'unsubscribed') {
      $l['last_contacted_at'] = ''; $l['contact_via'] = ''; $l['status'] = 'new'; $requeued++;
      $note = trim((string)($l['notes'] ?? '')); $l['notes'] = trim($note.($note !== '' ? ' · ' : '').'adres duzeltildi '.date('Y-m-d'));
    }
  }
  unset($l);
  if ($fixed) vestra_save_leads($leads);
  echo json_encode(['ok'=>true, 'fixed'=>$fixed, 'requeued'=>$requeued]);
  exit(0);
}

if ($cmd === 'release') {
  /* lemlist CSV'sinin "verildi" damgasını (lemlist'e hiç yüklenmedi) verilen ID'lerde geri alır. */
  if (!function_exists('vestra_mailbox_release_lemlist')) { echo json_encode(['ok'=>false,'error'=>'eski sunucu kodu']); exit(1); }
  $ids = array_filter(explode(',', (string)($argv[2] ?? '')), fn($x) => preg_match('/^[A-Za-z0-9]{2,40}$/', $x));
  echo json_encode(['ok'=>true, 'released'=>vestra_mailbox_release_lemlist($ids)]);
  exit(0);
}

if ($cmd === 'request') {
  /* Sunucunun kendi posta servisi için istek (cron_mailbox.php 10 dk içinde alır) — paneldeki düğmeyle aynı yol.
     request <mode> <limit> <campaign> [test_to] [pool: web|all] */
  if (!function_exists('vestra_mailbox_request')) { echo json_encode(['ok'=>false,'error'=>'eski sunucu kodu']); exit(1); }
  [$ok, $msg] = vestra_mailbox_request((string)($argv[2] ?? ''), (int)($argv[3] ?? 25), (string)($argv[4] ?? 'lesgarage'), (string)($argv[5] ?? ''), array_keys(vestra_finder_campaigns()), (string)($argv[6] ?? 'web'));
  echo json_encode(['ok'=>$ok, 'msg'=>$msg], JSON_UNESCAPED_UNICODE);
  exit($ok ? 0 : 1);
}

if ($cmd === 'auto') {
  /* 10 Eki 2026: arama bitince çağrılır. Paneldeki "Gönderim modu" OTOMATİK ise ayarlı kampanya/sayı/havuzla
     istek yazar; MANUEL ise hiçbir şey göndermez (bulunanlar listede bekler). */
  if (!function_exists('vestra_mailbox_auto_request')) { echo json_encode(['ok'=>false,'msg'=>'eski sunucu kodu — otomatik gönderim yok']); exit(0); }
  [$ok, $msg] = vestra_mailbox_auto_request(array_keys(vestra_finder_campaigns()));
  echo json_encode(['ok'=>$ok, 'msg'=>$msg], JSON_UNESCAPED_UNICODE);
  exit(0);
}

if ($cmd === 'status') {
  if (!function_exists('vestra_mailbox_runs')) { echo json_encode(['ok'=>false]); exit(1); }
  $runs = array_slice(array_reverse(vestra_mailbox_runs()), 0, 5);
  foreach ($runs as &$r) unset($r['test_to']);
  echo json_encode(['ok'=>true, 'today'=>vestra_mailbox_sent_today(), 'cap'=>vestra_mailbox_daily_cap(), 'runs'=>$runs], JSON_UNESCAPED_UNICODE);
  exit(0);
}

if ($cmd === 'take') {
  /* Panel isteği (inc/mailbox.php): en eskisini "running" yapıp basar; yoksa {}. */
  $r = function_exists('vestra_mailbox_take') ? vestra_mailbox_take() : null;
  echo json_encode($r ?: new stdClass, JSON_UNESCAPED_UNICODE);
  exit(0);
}

if ($cmd === 'finish') {
  $id = (string)($argv[2] ?? '');
  if (!preg_match('/^MB[0-9a-f]{12,20}$/i', $id) || !function_exists('vestra_mailbox_finish')) { fwrite(STDERR, "gecersiz istek\n"); exit(1); }
  $in = json_decode((string)stream_get_contents(STDIN), true);
  if (!is_array($in) || !isset($in['results'])) { vestra_mailbox_finish($id, ['error' => 'gonderim tamamlanmadi (giris ya da baglanti hatasi — GitHub kaydina bakin)']); echo json_encode(['ok'=>true,'status'=>'failed']); exit(0); }
  $c = ['sent'=>0, 'bounced'=>0, 'failed'=>0];
  foreach ((array)$in['results'] as $r) { $st = (string)($r['status'] ?? ''); if (isset($c[$st])) $c[$st]++; }
  if (!empty($in['test'])) $c['note'] = 'test gonderildi';
  vestra_mailbox_finish($id, $c);
  echo json_encode(['ok'=>true] + $c);
  exit(0);
}

if ($cmd === 'sethost') {
  $host = trim((string)($argv[2] ?? '')); $port = (int)($argv[3] ?? 587);
  if ($host === '') { fwrite(STDERR, "host bos\n"); exit(1); }
  $dir = vestra_data_dir();
  $f = $dir.'/mailbox.json';
  $cur = is_readable($f) ? json_decode((string)file_get_contents($f), true) : [];
  if (!is_array($cur)) $cur = [];
  $cur['smtp_host'] = $host; $cur['smtp_port'] = $port > 0 ? $port : 587;
  $cur['sender_email'] = vestra_mail_house_address(); $cur['verified_at'] = date('c');
  file_put_contents($f, json_encode($cur, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)); @chmod($f, 0600);
  echo json_encode(['ok'=>true, 'host'=>$host, 'port'=>$cur['smtp_port']]);
  exit(0);
}

fwrite(STDERR, "kullanim: php vestra-mailbox-queue.php list <N> <campaign> [ids] | sample <campaign> <to> | stamp | take | finish <id> | fixemails | release <ids> | request <mode> <limit> <campaign> [to] | status | sethost <host> <port>\n");
exit(1);
