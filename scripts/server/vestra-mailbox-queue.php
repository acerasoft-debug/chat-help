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

$cmd = $argv[1] ?? '';

if ($cmd === 'list') {
  $limit = max(1, min(200, (int)($argv[2] ?? 30)));
  $campKey = (string)($argv[3] ?? 'lesgarage');
  $camps = vestra_finder_campaigns();
  if (!isset($camps[$campKey])) { fwrite(STDERR, "bilinmeyen kampanya: {$campKey}\n"); echo json_encode(['ok'=>false,'error'=>'campaign']); exit(1); }
  $builder = $camps[$campKey][2];
  $targets = vestra_finder_send_targets($limit, 'all');
  $items = []; $fromName = 'VESTRA';
  foreach ($targets as $l) {
    $email = strtolower(trim((string)($l['email'] ?? '')));
    if ($email === '') continue;
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
      if (trim((string)($l['last_contacted_at'] ?? '')) !== '') continue; // iki kez damgalama
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

fwrite(STDERR, "kullanim: php vestra-mailbox-queue.php list <N> <campaign> | stamp | sethost <host> <port>\n");
exit(1);
