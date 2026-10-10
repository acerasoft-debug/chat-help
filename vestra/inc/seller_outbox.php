<?php
/**
 * VESTRA — satıcının KENDİ adresinden, KENDİ posta sunucusundan (SMTP) gönderim kuyruğu.
 *
 * Operatör, 10 Eki 2026: "satıcılarda kendi email adresleri ile gönderebilsin, kendi sunucuları ile".
 * Bu barındırmadan giden SMTP kapalı (8 Eki smtp_probe: Gmail/Outlook/Yahoo/iCloud/Zoho, 587/465 zaman
 * aşımı) — sunucu satıcının posta sunucusuna BAĞLANAMAZ. Bu yüzden:
 *   1. Satıcı panelden gönderir → mektup burada çizilir ve data/seller_outbox.json'a "queued" yazılır.
 *   2. GitHub'daki seller-outbox.yml (10 dakikada bir) SSH ile `take` eder: kuyruktaki mektuplar + her
 *      satıcının SMTP girişi YALNIZ SSH borusundan gelir (loga, dosyaya, depoya yazılmaz), koşucu satıcının
 *      kendi sunucusuna bağlanıp gönderir (scripts/ci/seller_outbox_send.py), sonucu `stamp` ile geri yazar.
 *   3. Gönderilen müşteri "contacted" (contact_via=seller_smtp); alıcı reddi → "bounced"; giriş reddi →
 *      satıcının kartında açık neden.
 * Brevo anahtarı olan satıcı eskisi gibi anında Brevo'dan gönderir (vestra_seller_can_send).
 */
require_once __DIR__.'/leads.php';
require_once __DIR__.'/notify.php';

const VESTRA_SELLER_OUTBOX_DAILY = 300;   // satıcı başına günde (Gmail kişisel hesap sınırı ~500)
/* 10 Eki 2026 (operatör: "herkes kendi e-mailinden server'dan göndersin"): kurulumsuz varsayılan yol = VESTRA'nın
   kendi posta servisi. From yine support@vestrasales.com (Gmail/Outlook alan adlarının DMARC'ı başka sunucudan
   "From: seller@gmail.com" yazılmasına izin vermez — geri döner), görünen ad satıcı ("Firma via VESTRA"), Reply-To
   satıcının kendi adresi: müşteri "yanıtla" deyince satıcıya yazar. cron_mailbox.php 10 dk'da bir gönderir. */
const VESTRA_SELLER_SERVER_DAILY = 100;   // satıcı başına günde, sunucu yolunda (cPanel saatte 500, support@ ile paylaşılır)
const VESTRA_SELLER_SERVER_PER_TICK = 40; // bir cron turunda en çok (10 dk, 8-15 sn ara)

/** Satıcının gönderim yolu: 'brevo' (kendi anahtarı, anında) | 'smtp' (kendi sunucusu) | 'server' (VESTRA sunucusu) | '' (adres yok). */
function vestra_seller_route(array $c, string $accountEmail): string {
  if (vestra_seller_can_send($c)) return 'brevo';
  if (vestra_seller_smtp_ready($c)) return 'smtp';
  return vestra_seller_reply_to($c, $accountEmail) !== '' ? 'server' : '';
}
/** Yanıtların gideceği adres: satıcının kaydettiği gönderen adres, yoksa hesap e-postası. */
function vestra_seller_reply_to(array $c, string $accountEmail): string {
  foreach ([(string)($c['mail_from'] ?? ''), (string)($c['smtp_from'] ?? ''), $accountEmail] as $e) {
    $e = strtolower(trim($e)); if (filter_var($e, FILTER_VALIDATE_EMAIL)) return $e;
  }
  return '';
}

function vestra_seller_smtp_from(array $c): string {
  $f = strtolower(trim((string)($c['smtp_from'] ?? $c['mail_from'] ?? '')));
  return filter_var($f, FILTER_VALIDATE_EMAIL) ? $f : '';
}
/** Satıcının SMTP girişi tam mı (sunucu, şifre, gönderen adres). */
function vestra_seller_smtp_ready(array $c): bool {
  return preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', trim((string)($c['smtp_host'] ?? ''))) === 1
      && (string)($c['smtp_pass'] ?? '') !== '' && vestra_seller_smtp_from($c) !== '';
}

function vestra_seller_outbox_file(): string { return vestra_data_dir().'/seller_outbox.json'; }
function vestra_seller_outbox_all(): array {
  $f = vestra_seller_outbox_file();
  $d = is_readable($f) ? json_decode((string)file_get_contents($f), true) : [];
  return is_array($d) ? array_values($d) : [];
}
function vestra_seller_outbox_save(array $items): void {
  /* Gönderilmiş/başarısız eski kayıtlar budanır; kuyrukta bekleyen hiç silinmez. */
  $keep = []; $cut = time() - 14 * 86400;
  foreach ($items as $it) if (in_array($it['status'] ?? '', ['queued', 'sending'], true) || (int)strtotime((string)($it['queued_at'] ?? '')) > $cut) $keep[] = $it;
  $f = vestra_seller_outbox_file();
  file_put_contents($f, json_encode(array_slice($keep, -5000), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), LOCK_EX);
  @chmod($f, 0600);
}
/** Tek yazar kilidi: panel ekleme, koşucu take/stamp aynı dosyayı yarışmadan günceller. */
function vestra_seller_outbox_locked(callable $fn) {
  $lk = @fopen(vestra_data_dir().'/seller_outbox.lock', 'c');
  if ($lk) flock($lk, LOCK_EX);
  try { return $fn(); } finally { if ($lk) { flock($lk, LOCK_UN); fclose($lk); } }
}

/**
 * Kuyruğa ekler. [ok, kod]: 'queued' | 'dup' (bu müşteri zaten kuyrukta) | 'cap' (günlük sınır) | 'nosetup' | 'badto'.
 * $leadId '' = TEST (kayda dokunmaz).
 */
function vestra_seller_outbox_add(string $uid, string $leadId, string $to, string $subject, string $body, string $fromName, string $heroImg = '', string $unsubUrl = '', string $via = 'smtp', string $replyTo = ''): array {
  $c = vestra_seller_mail($uid);
  $via = $via === 'server' ? 'server' : 'smtp';
  if ($via === 'smtp' && !vestra_seller_smtp_ready($c)) return [false, 'nosetup'];
  $replyTo = strtolower(trim($replyTo));
  if ($via === 'server' && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) return [false, 'nosetup'];
  $to = strtolower(trim($to));
  if (!filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n,;]/', $to)) return [false, 'badto'];
  return vestra_seller_outbox_locked(static function () use ($uid, $leadId, $to, $subject, $body, $fromName, $heroImg, $unsubUrl, $via, $replyTo): array {
    $all = vestra_seller_outbox_all(); $today = date('Y-m-d'); $n = 0; $nSrv = 0;
    foreach ($all as $it) {
      if (($it['uid'] ?? '') !== $uid) continue;
      if ($leadId !== '' && ($it['lead_id'] ?? '') === $leadId && in_array($it['status'] ?? '', ['queued', 'sending'], true)) return [false, 'dup'];
      if (str_starts_with((string)($it['queued_at'] ?? ''), $today)) { $n++; if (($it['via'] ?? 'smtp') === 'server') $nSrv++; }
    }
    if ($n >= VESTRA_SELLER_OUTBOX_DAILY || ($via === 'server' && $nSrv >= VESTRA_SELLER_SERVER_DAILY)) return [false, 'cap'];
    $all[] = ['id' => 'OB'.date('ymdHis').bin2hex(random_bytes(3)), 'uid' => $uid, 'lead_id' => $leadId, 'to' => $to, 'via' => $via,
              'reply_to' => $via === 'server' ? $replyTo : '',
              'subject' => mb_substr(str_replace(["\r", "\n"], ' ', $subject), 0, 250),
              'html' => vestra_html_email($body, $heroImg, []), 'text' => vestra_mail_text_part($body, []),
              'from_name' => mb_substr(str_replace(["\r", "\n", '"'], '', $fromName), 0, 80),
              'unsub' => $unsubUrl, 'status' => 'queued', 'queued_at' => date('c')];
    vestra_seller_outbox_save($all);
    return [true, 'queued'];
  });
}

/**
 * Koşucu için: en çok $max mektup "sending" olur. Döner ['items'=>[...], 'creds'=>[uid=>[host,port,user,pass,from]]].
 * 30 dakikadır "sending" kalan (koşu yarıda kesildi) yeniden alınır. SMTP girişi silinmiş satıcının
 * mektupları "failed: nosetup" olur.
 */
function vestra_seller_outbox_take(int $max): array {
  return vestra_seller_outbox_locked(static function () use ($max): array {
    $all = vestra_seller_outbox_all(); $items = []; $creds = []; $changed = false; $stale = time() - 1800;
    foreach ($all as $i => $it) {
      $st = (string)($it['status'] ?? '');
      if (($it['via'] ?? 'smtp') !== 'smtp') continue;                     // sunucu yolu cron_mailbox.php'de
      if (!($st === 'queued' || ($st === 'sending' && (int)strtotime((string)($it['taken_at'] ?? '')) < $stale))) continue;
      if (count($items) >= $max) break;
      $uid = (string)($it['uid'] ?? '');
      if (!isset($creds[$uid])) {
        $c = vestra_seller_mail($uid);
        $creds[$uid] = vestra_seller_smtp_ready($c) ? ['host' => trim((string)$c['smtp_host']), 'port' => (int)($c['smtp_port'] ?? 465) ?: 465,
          'user' => trim((string)($c['smtp_user'] ?? '')) ?: vestra_seller_smtp_from($c), 'pass' => (string)$c['smtp_pass'], 'from' => vestra_seller_smtp_from($c)] : null;
      }
      if ($creds[$uid] === null) { $all[$i]['status'] = 'failed'; $all[$i]['reason'] = 'nosetup'; $all[$i]['done_at'] = date('c'); $changed = true; continue; }
      $all[$i]['status'] = 'sending'; $all[$i]['taken_at'] = date('c'); $changed = true;
      $items[] = ['id' => $it['id'], 'uid' => $uid, 'to' => $it['to'], 'subject' => $it['subject'], 'html' => $it['html'], 'text' => $it['text'],
                  'from_name' => $it['from_name'], 'unsub' => (string)($it['unsub'] ?? '')];
    }
    if ($changed) vestra_seller_outbox_save($all);
    return ['items' => $items, 'creds' => array_filter($creds)];
  });
}

/**
 * Koşucunun sonucu: [{id, status: sent|failed|bounced|auth, reason, message_id}]. Döner sayılar.
 * sent → müşteri contacted (contact_via=seller_smtp); bounced → müşteri bounced; auth → satıcının
 * kartında "sunucunuz girişi reddetti" (kalan mektupları bir sonraki koşuda yine denenmez — failed).
 */
function vestra_seller_outbox_stamp(array $results): array {
  $by = []; foreach ($results as $r) if (is_array($r) && ($r['id'] ?? '') !== '') $by[(string)$r['id']] = $r;
  $cnt = ['sent' => 0, 'failed' => 0, 'bounced' => 0, 'auth' => 0];
  if (!$by) return $cnt;
  [$leadSt, $authUids, $testUids] = vestra_seller_outbox_locked(static function () use ($by, &$cnt): array {
    $all = vestra_seller_outbox_all(); $leadSt = []; $authUids = []; $testUids = [];
    foreach ($all as $i => $it) {
      $r = $by[(string)($it['id'] ?? '')] ?? null; if (!$r) continue;
      /* Geçici sorun (bağlantı, hız sınırı, süre): mektup kuyruğa döner, en çok 3 deneme. */
      if (($r['status'] ?? '') === 'retry' && (int)($it['attempts'] ?? 0) < 2) {
        $all[$i]['status'] = 'queued'; $all[$i]['attempts'] = (int)($it['attempts'] ?? 0) + 1;
        $all[$i]['reason'] = mb_substr((string)($r['reason'] ?? ''), 0, 160); $cnt['retry'] = ($cnt['retry'] ?? 0) + 1; continue;
      }
      $st = in_array($r['status'] ?? '', ['sent', 'failed', 'bounced', 'auth'], true) ? (string)$r['status'] : 'failed';
      $cnt[$st]++;
      $all[$i]['status'] = $st === 'sent' ? 'sent' : ($st === 'bounced' ? 'bounced' : 'failed');
      $all[$i]['reason'] = mb_substr((string)($r['reason'] ?? ''), 0, 160);
      $all[$i]['done_at'] = date('c');
      if (($r['message_id'] ?? '') !== '') $all[$i]['message_id'] = mb_substr((string)$r['message_id'], 0, 120);
      unset($all[$i]['html'], $all[$i]['text']);           // gönderildi/düştü: gövde artık gerekmez
      if (($it['lead_id'] ?? '') !== '') $leadSt[(string)$it['lead_id']] = [$st, (string)$it['uid'], $all[$i]['reason'], ($it['via'] ?? 'smtp') === 'server' ? 'seller_server' : 'seller_smtp'];
      elseif ($st === 'sent') $testUids[(string)$it['uid']] = (string)$it['to'];
      if ($st === 'auth') $authUids[(string)$it['uid']] = $all[$i]['reason'];
    }
    vestra_seller_outbox_save($all);
    return [$leadSt, $authUids, $testUids];
  });
  if ($leadSt) {
    $leads = vestra_leads(); $ch = false;
    foreach ($leads as &$l) {
      $x = $leadSt[(string)($l['id'] ?? '')] ?? null;
      if (!$x || (string)($l['owner_uid'] ?? '') !== $x[1]) continue;
      if ($x[0] === 'sent') { if (($l['status'] ?? 'new') === 'new') $l['status'] = 'contacted'; $l['last_contacted_at'] = date('c'); $l['contact_via'] = $x[3] ?? 'seller_smtp'; $ch = true; }
      elseif ($x[0] === 'bounced') { $l['status'] = 'bounced'; $l['bounce_reason'] = 'seller smtp: '.$x[2]; $l['bounced_at'] = date('c'); $ch = true; }
    }
    unset($l);
    if ($ch) vestra_save_leads($leads);
  }
  foreach ($authUids + $testUids as $uid => $_) {
    $c = vestra_seller_mail($uid);
    if (isset($authUids[$uid])) { $c['smtp_error'] = $authUids[$uid]; $c['smtp_error_at'] = date('c'); }
    elseif (isset($testUids[$uid])) { $c['last_test_ok_at'] = date('c'); $c['last_test_to'] = $testUids[$uid]; unset($c['smtp_error'], $c['smtp_error_at']); }
    vestra_seller_mail_save($uid, $c);
  }
  return $cnt;
}

/** Satıcının kartı için: ['queued','sent_today','failed_today','last'=>[...]] */
function vestra_seller_outbox_status(string $uid): array {
  $q = 0; $s = 0; $f = 0; $today = date('Y-m-d'); $last = [];
  foreach (vestra_seller_outbox_all() as $it) {
    if (($it['uid'] ?? '') !== $uid) continue;
    $st = (string)($it['status'] ?? '');
    if ($st === 'queued' || $st === 'sending') $q++;
    elseif (str_starts_with((string)($it['done_at'] ?? ''), $today)) { if ($st === 'sent') $s++; else $f++; }
    $last[] = $it;
  }
  return ['queued' => $q, 'sent_today' => $s, 'failed_today' => $f, 'last' => array_slice(array_reverse($last), 0, 8)];
}

/**
 * Sunucu yolu (via=server): cron_mailbox.php her 10 dk'da çağırır. Satıcının mektuplarını VESTRA'nın posta
 * servisinden, görünen ad satıcı + Reply-To satıcının adresiyle gönderir; her mektuptan hemen sonra damgalar.
 * $o: 'mail' (sahte mail()), 'sleep', 'log'. Döner ['sent','failed','skipped'].
 */
function vestra_seller_outbox_run_server(array $o = []): array {
  require_once __DIR__.'/mailbox.php';
  $sleep = $o['sleep'] ?? static function (int $s): void { sleep($s); };
  $log = $o['log'] ?? static function (string $m): void { echo $m, "\n"; };
  $sum = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
  $items = vestra_seller_outbox_locked(static function (): array {
    $all = vestra_seller_outbox_all(); $out = []; $changed = false; $stale = time() - 1800;
    foreach ($all as $i => $it) {
      if (($it['via'] ?? 'smtp') !== 'server') continue;
      $st = (string)($it['status'] ?? '');
      if (!($st === 'queued' || ($st === 'sending' && (int)strtotime((string)($it['taken_at'] ?? '')) < $stale))) continue;
      if (count($out) >= VESTRA_SELLER_SERVER_PER_TICK) break;
      $all[$i]['status'] = 'sending'; $all[$i]['taken_at'] = date('c'); $changed = true; $out[] = $all[$i];
    }
    if ($changed) vestra_seller_outbox_save($all);
    return $out;
  });
  if (!$items) return $sum;
  $dns = $o['dns'] ?? 'vestra_mailbox_dns_status'; $any = false;
  foreach ($items as $it) {
    $dom = substr((string)strrchr((string)$it['to'], '@'), 1);
    if ($dns($dom) === 'dead') {
      vestra_seller_outbox_stamp([['id' => $it['id'], 'status' => 'bounced', 'reason' => 'alan adinin e-posta sunucusu yok']]);
      $sum['skipped']++; $log('  - '.vestra_mailbox_mask((string)$it['to']).' atlandi: alan adinin e-posta sunucusu yok'); continue;
    }
    if ($any) $sleep(random_int(8, 15));
    [$ok, $mid, $why] = vestra_mailbox_send_local(['email' => $it['to'], 'subject' => $it['subject'], 'html' => $it['html'] ?? '', 'text' => $it['text'] ?? '', 'listUnsub' => (string)($it['unsub'] ?? '')],
                                                  (string)($it['from_name'] ?? 'VESTRA'), $o['mail'] ?? null, ['reply_to' => (string)($it['reply_to'] ?? '')]);
    $any = true;
    vestra_seller_outbox_stamp([['id' => $it['id'], 'status' => $ok ? 'sent' : 'failed', 'reason' => $ok ? '' : $why, 'message_id' => $mid]]);
    $sum[$ok ? 'sent' : 'failed']++;
    $log(($ok ? '  + ' : '  x ').vestra_seller_outbox_mask_uid((string)$it['uid']).' → '.vestra_mailbox_mask((string)$it['to']).($ok ? '' : ' — '.$why));
  }
  return $sum;
}
function vestra_seller_outbox_mask_uid(string $uid): string { return mb_substr($uid, 0, 6).'…'; }
