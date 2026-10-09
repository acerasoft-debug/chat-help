<?php
/**
 * VESTRA — support@vestrasales.com posta kutusundan kotasız gönderim: panel isteği kuyruğu
 * ve günlük tavan (operatör, 8 Eki 2026: "adminde tetikleyici olsun").
 *
 * Mektubu sunucu GÖNDEREMEZ (giden SMTP kapalı); GitHub koşucusu gönderir
 * (.github/workflows/mailbox-send.yml, scripts/server/vestra-mailbox-queue.php). Panelde
 * GitHub anahtarı yok; bu yüzden "Müşteri bul" düğmesinin yolu izlenir: panel isteği
 * data/mailbox_runs.json'a "requested" yazar, mailbox-queue.yml 10 dakikada bir alır ve
 * gönderimi başlatır, gönderim bitince sonucu buraya yazar.
 *
 * Günlük tavan (varsayılan 50): itibar için. Panel de, kuyruk da, elle başlatılan gönderim de
 * bugün posta kutusundan gidenleri sayar; tavan dolunca kimseye gitmez.
 */
require_once __DIR__.'/leads.php';
require_once __DIR__.'/notify.php';

const VESTRA_MAILBOX_DEFAULT_CAP = 50;

function vestra_mailbox_file(string $name): string { return vestra_data_dir().'/'.$name; }

/** Doğrulanmış SMTP sunucusu (sethost yazar): ['smtp_host','smtp_port','verified_at',...] */
function vestra_mailbox_cfg(): array {
  $f = vestra_mailbox_file('mailbox.json');
  $d = is_readable($f) ? json_decode((string)file_get_contents($f), true) : [];
  return is_array($d) ? $d : [];
}

function vestra_mailbox_daily_cap(): int {
  $c = (int)(vestra_mailbox_cfg()['daily_cap'] ?? VESTRA_MAILBOX_DEFAULT_CAP);
  return max(1, min(200, $c ?: VESTRA_MAILBOX_DEFAULT_CAP));
}

/** Bugün (sunucu günü) posta kutusundan gönderilmiş lead sayısı. */
function vestra_mailbox_sent_today(?array $leads = null): int {
  $today = date('Y-m-d'); $n = 0;
  foreach ($leads ?? vestra_leads() as $l)
    if ((string)($l['contact_via'] ?? '') === 'mailbox' && str_starts_with((string)($l['last_contacted_at'] ?? ''), $today)) $n++;
  return $n;
}

function vestra_mailbox_left_today(?array $leads = null): int {
  return max(0, vestra_mailbox_daily_cap() - vestra_mailbox_sent_today($leads));
}

function vestra_mailbox_runs(): array {
  $f = vestra_mailbox_file('mailbox_runs.json');
  $d = is_readable($f) ? json_decode((string)file_get_contents($f), true) : [];
  return is_array($d) ? array_values($d) : [];
}

function vestra_mailbox_runs_save(array $runs): void {
  $f = vestra_mailbox_file('mailbox_runs.json');
  file_put_contents($f, json_encode(array_slice(array_values($runs), -30), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), LOCK_EX);
  @chmod($f, 0600);
}

/** 2 saatten uzun "requested/running" kalan istek takılmıştır (iş iptal edildi vb.) — yenisini engellemez. */
function vestra_mailbox_is_open(array $r): bool {
  $st = (string)($r['status'] ?? '');
  if ($st !== 'requested' && $st !== 'running') return false;
  $t = strtotime((string)($r['started_at'] ?? $r['requested_at'] ?? '')) ?: 0;
  return $t > time() - 7200;
}

/**
 * Panel isteği. [ok, mesaj]. Aynı anda tek istek; gönderim tavanı bugünkü kalanla sınırlı.
 * $mode: 'test' (tek örnek, $testTo'ya) | 'send'.
 */
function vestra_mailbox_request(string $mode, int $limit, string $campaign, string $testTo, ?array $campaignKeys = null): array {
  if (!in_array($mode, ['test', 'send'], true)) return [false, 'Geçersiz kip.'];
  if (!preg_match('/^[A-Za-z0-9:_-]{1,40}$/', $campaign) || ($campaignKeys !== null && !in_array($campaign, $campaignKeys, true))) return [false, 'Kampanya bulunamadı.'];
  $testTo = strtolower(trim($testTo));
  if ($mode === 'test' && (!filter_var($testTo, FILTER_VALIDATE_EMAIL) || !preg_match('/^[a-z0-9@._+-]+$/', $testTo))) return [false, 'Geçerli bir test adresi yazın.'];
  $runs = vestra_mailbox_runs();
  foreach ($runs as $r) if (vestra_mailbox_is_open($r)) return [false, 'Sırada ya da gönderilmekte olan bir istek var — bitince yenisini verin.'];
  if ($mode === 'send') {
    $left = vestra_mailbox_left_today();
    if ($left <= 0) return [false, 'Bugünkü tavan ('.vestra_mailbox_daily_cap().') doldu — kalanlar yarın.'];
    $limit = max(1, min($limit, $left));
  } else $limit = 1;
  $id = 'MB'.date('ymdHis').substr(bin2hex(random_bytes(2)), 0, 4);
  $runs[] = ['id' => $id, 'mode' => $mode, 'limit' => $limit, 'campaign' => $campaign, 'test_to' => $mode === 'test' ? $testTo : '',
             'status' => 'requested', 'requested_at' => date('c')];
  vestra_mailbox_runs_save($runs);
  return [true, $mode === 'test'
    ? '✓ Test isteği sıraya alındı → '.$testTo.'. En geç 10 dakika içinde gider.'
    : '✓ Gönderim isteği sıraya alındı: '.$limit.' müşteri. En geç 10 dakika içinde başlar; e-postalar arasında 25–55 sn beklenir.'];
}

/** Kuyruk (GitHub): en eski bekleyen isteği alır ve "running" yapar. Yoksa null. */
function vestra_mailbox_take(): ?array {
  $runs = vestra_mailbox_runs();
  foreach ($runs as $i => $r) {
    if (($r['status'] ?? '') !== 'requested') continue;
    if (!vestra_mailbox_is_open($r)) { $runs[$i]['status'] = 'stale'; continue; }
    $runs[$i]['status'] = 'running'; $runs[$i]['started_at'] = date('c');
    vestra_mailbox_runs_save($runs);
    return $runs[$i];
  }
  vestra_mailbox_runs_save($runs);
  return null;
}

/** Gönderim bitti: sonucu isteğe yazar. $s: ['sent','bounced','failed','note'] */
function vestra_mailbox_finish(string $id, array $s): bool {
  $runs = vestra_mailbox_runs(); $hit = false;
  foreach ($runs as $i => $r) {
    if (($r['id'] ?? '') !== $id) continue;
    if (in_array((string)($r['status'] ?? ''), ['done', 'failed'], true)) return false; // ilk sonuç korunur
    $runs[$i]['status'] = !empty($s['error']) ? 'failed' : 'done';
    $runs[$i]['finished_at'] = date('c');
    foreach (['sent', 'bounced', 'failed'] as $k) $runs[$i][$k] = (int)($s[$k] ?? 0);
    $runs[$i]['note'] = mb_substr((string)($s['note'] ?? $s['error'] ?? ''), 0, 200);
    $hit = true;
  }
  if ($hit) vestra_mailbox_runs_save($runs);
  return $hit;
}

/* ───────────────────────── Sunucunun kendi posta servisinden gönderim (9 Eki 2026) ─────────────────────────
 * Operatör: "kendi serverımızdan, hata vermeden, sorunsuz". Ölçüm (9 Eki 01:35 UTC, local_mail_probe):
 * sunucunun dış SMTP'si kapalı ama kendi posta servisi (Exim, localhost:25) mektubu GoDaddy'nin barındırma
 * aktarıcısından geçirip Gmail'e GELEN KUTUSU olarak ulaştırdı: SPF=pass, DMARC=pass (p=reject). Şifre
 * gerekmez. Bu yüzden gönderimi artık sunucu yapar (cron_mailbox.php, 10 dk'da bir panel isteğini alır).
 * Seçim / damgalama / MX kontrolü burada TEK yerde: GitHub yedek yolu da (scripts/server/vestra-mailbox-queue.php)
 * aynı fonksiyonları çağırır. */

/** Gönderim listesi: ['ok','from','campaign','items'=>[...],'note']. $ids verilirse yalnız onlar
 *  (damgası boş ya da yalnız 'lemlist' olanlar — lemlist'e hiç yüklenmeyen 8 Eki dışa aktarımı). Günlük tavan uygulanır. */
function vestra_mailbox_batch(int $limit, string $campKey, array $ids = []): array {
  require_once __DIR__.'/finder.php';
  $camps = vestra_finder_campaigns();
  if (!isset($camps[$campKey])) return ['ok' => false, 'error' => 'campaign', 'items' => []];
  $builder = $camps[$campKey][2];
  $limit = max(1, min(200, $limit));
  $left = vestra_mailbox_left_today();
  if ($left <= 0) return ['ok' => true, 'from' => 'VESTRA', 'campaign' => $campKey, 'items' => [], 'note' => 'gunluk tavan doldu ('.vestra_mailbox_daily_cap().')'];
  $limit = min($limit, $left);
  $ids = array_values(array_filter(array_map('trim', $ids)));
  if ($ids) {
    $want = array_flip($ids); $targets = []; $seen = [];
    foreach (vestra_leads() as $i => $l) {
      if (!isset($want[(string)($l['id'] ?? '')]) || count($targets) >= $limit) continue;
      $st = (string)($l['status'] ?? 'new');
      if ($st === 'unsubscribed' || $st === 'bounced' || !empty($l['unsubscribed'])) continue;
      if (trim((string)($l['last_contacted_at'] ?? '')) !== '' && (string)($l['contact_via'] ?? '') !== 'lemlist') continue;
      $e = vestra_email_clean((string)($l['email'] ?? ''));
      if (!filter_var($e, FILTER_VALIDATE_EMAIL) || isset($seen[$e])) continue;
      if (vestra_email_is_junk($e) || vestra_lead_looks_dead($l)) continue;
      $seen[$e] = true; $targets[$i] = $l;
    }
  } else {
    $targets = vestra_finder_send_targets($limit, 'all');
  }
  $items = []; $fromName = 'VESTRA'; $seenAddr = [];
  foreach ($targets as $l) {
    $email = vestra_email_clean((string)($l['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($seenAddr[$email])) continue;
    $seenAddr[$email] = true;
    [$subject, $body, $opts, $from] = $builder($l);
    if ($from !== '') $fromName = $from;
    $token = (string)($l['unsub_token'] ?? '');
    $items[] = [
      'leadId' => (string)($l['id'] ?? ''), 'email' => $email, 'company' => (string)($l['company'] ?? ''),
      'lang' => vestra_finder_lead_lang($l), 'subject' => $subject,
      'html' => vestra_html_email($body, '', (array)$opts), 'text' => vestra_mail_text_part($body, (array)$opts),
      'listUnsub' => $token !== '' ? 'https://vestrasales.com/lead-unsubscribe?token='.rawurlencode($token) : 'https://vestrasales.com/lead-unsubscribe',
    ];
  }
  return ['ok' => true, 'from' => $fromName, 'campaign' => $campKey, 'items' => $items];
}

/** Test: örnek dükkânla tek mektup, kayda dokunmaz. */
function vestra_mailbox_sample(string $campKey, string $to): array {
  require_once __DIR__.'/finder.php';
  $camps = vestra_finder_campaigns(); $to = strtolower(trim($to));
  if (!isset($camps[$campKey]) || !filter_var($to, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'items' => []];
  $lead = ['id' => 'TEST', 'company' => 'Boutique Example', 'country' => 'France', 'email' => $to, 'contact_name' => '', 'unsub_token' => ''];
  [$subject, $body, $opts, $from] = ($camps[$campKey][2])($lead);
  return ['ok' => true, 'from' => $from !== '' ? $from : 'VESTRA', 'campaign' => $campKey, 'items' => [[
    'leadId' => 'TEST', 'email' => $to, 'company' => 'Boutique Example', 'lang' => vestra_finder_lead_lang($lead), 'subject' => '[TEST] '.$subject,
    'html' => vestra_html_email($body, '', (array)$opts), 'text' => vestra_mail_text_part($body, (array)$opts), 'listUnsub' => 'https://vestrasales.com/lead-unsubscribe']]];
}

/** Sonuçları lead kaydına işler: sent → contacted (contact_via=mailbox), bounced, unsub. Sayıları döndürür. */
function vestra_mailbox_stamp(array $results): array {
  $byId = []; $byEmail = [];
  foreach ($results as $r) {
    $id = (string)($r['leadId'] ?? ''); $em = strtolower(trim((string)($r['email'] ?? '')));
    if ($id !== '') $byId[$id] = $r;
    if ($em !== '') $byEmail[$em] = $r;
  }
  $c = ['sent' => 0, 'bounced' => 0, 'unsub' => 0];
  if (!$byId && !$byEmail) return $c;
  $leads = vestra_leads();
  foreach ($leads as &$l) {
    $id = (string)($l['id'] ?? ''); $em = vestra_email_clean((string)($l['email'] ?? ''));
    $r = $byId[$id] ?? ($em !== '' ? ($byEmail[$em] ?? null) : null);
    if (!$r) continue;
    $st = (string)($r['status'] ?? '');
    if ($st === 'sent') {
      if (trim((string)($l['last_contacted_at'] ?? '')) !== '' && (string)($l['contact_via'] ?? '') !== 'lemlist') continue; // iki kez damgalama
      $l['last_contacted_at'] = date('c'); $l['contact_via'] = 'mailbox';
      if (($l['status'] ?? 'new') === 'new') $l['status'] = 'contacted';
      $l['last_campaign'] = 'mailbox/'.(string)($r['lang'] ?? '');
      if (($mid = (string)($r['messageId'] ?? '')) !== '') $l['last_message_id'] = $mid;
      $note = trim((string)($l['notes'] ?? ''));
      $l['notes'] = trim($note.($note !== '' ? ' · ' : '').'mailbox('.(string)($r['lang'] ?? '').') '.date('Y-m-d'));
      $c['sent']++;
    } elseif ($st === 'bounced') {
      $l['status'] = 'bounced'; $l['bounce_reason'] = 'mailbox: '.substr((string)($r['reason'] ?? 'rejected'), 0, 120); $l['bounced_at'] = date('c'); $c['bounced']++;
    } elseif ($st === 'unsub') {
      $l['status'] = 'unsubscribed'; $c['unsub']++;
    }
  }
  unset($l);
  if ($c['sent'] || $c['bounced'] || $c['unsub']) vestra_save_leads($leads);
  return $c;
}

/**
 * Alan adının e-posta alacak sunucusu var mı: 'ok' | 'dead' | 'unknown'.
 * dead = alan adı yok (NXDOMAIN), hiç MX/A yok ya da null MX (RFC 7505) → mektup GÖNDERİLMEZ (8 Eki: xoe-shop.de
 * "DNSNULL" ile geri döndü; 12'lik partide 7 alan adı böyleydi). unknown = sorgu başarısız/zaman aşımı → GÖNDERİLİR:
 * geçici bir DNS aksaklığı müşteriyi kalıcı "geri döndü" damgalamasın. $dig(type, domain): dig çıktısı | null.
 */
function vestra_mailbox_dns_status(string $domain, ?callable $dig = null): string {
  static $cache = [];
  $d = strtolower(trim($domain, ". \t"));
  if ($d === '' || !preg_match('/^[a-z0-9.-]+$/', $d)) return 'dead';
  if ($dig === null && isset($cache[$d])) return $cache[$d];
  $dig = $dig ?? static function (string $type, string $dom): ?string {
    if (!function_exists('shell_exec')) return null;
    $o = @shell_exec('dig +time=4 +tries=2 +noall +comments +answer '.escapeshellarg($type).' '.escapeshellarg($dom).' 2>/dev/null');
    return (is_string($o) && str_contains($o, 'status:')) ? $o : null;
  };
  $q = static function (string $type) use ($dig, $d): ?array {
    $o = $dig($type, $d);
    if ($o === null) return null;
    $st = preg_match('/status:\s*([A-Z]+)/', $o, $m) ? $m[1] : 'FAIL';
    $ans = [];
    foreach (preg_split('/\R/', $o) as $ln) {
      $ln = trim($ln); if ($ln === '' || $ln[0] === ';') continue;
      $p = preg_split('/\s+/', $ln);
      if (count($p) >= 5 && strtoupper($p[3]) === $type) $ans[] = $p;
    }
    return [$st, $ans];
  };
  $mx = $q('MX');
  if ($mx === null) {
    // dig yok: PHP'nin kendi çözücüsü (false = sorgu başarısız → bilinmiyor)
    $r = @dns_get_record($d, DNS_MX);
    if ($r === false) $res = 'unknown';
    elseif ($r && array_filter($r, fn($x) => trim((string)($x['target'] ?? ''), '.') !== '')) $res = 'ok';
    elseif ($r) $res = 'dead';
    else { $a = @dns_get_record($d, DNS_A); $res = $a === false ? 'unknown' : ($a ? 'ok' : 'dead'); }
  } else {
    [$st, $ans] = $mx;
    if ($st === 'NOERROR' && $ans && !array_filter($ans, fn($p) => end($p) !== '.')) $res = 'dead';      // null MX
    elseif ($st === 'NOERROR' && array_filter($ans, fn($p) => end($p) !== '.')) $res = 'ok';
    elseif ($st === 'NXDOMAIN') $res = 'dead';
    elseif ($st === 'NOERROR') { $a = $q('A'); $res = $a === null ? 'unknown' : (($a[0] === 'NOERROR' && $a[1]) ? 'ok' : (in_array($a[0], ['NOERROR', 'NXDOMAIN'], true) ? 'dead' : 'unknown')); }
    else $res = 'unknown';
  }
  return $cache[$d] = $res;
}

/** Tek mektubu sunucunun posta servisiyle (mail() → Exim → GoDaddy aktarıcısı) support@'tan gönderir.
 *  [ok, Message-ID, neden]. $mail: testte sahte mail(). */
function vestra_mailbox_send_local(array $it, string $fromName, ?callable $mail = null): array {
  $mail = $mail ?? 'mail';
  $from = vestra_mail_house_address();
  $to = strtolower(trim((string)($it['email'] ?? '')));
  if (!filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n,;]/', $to)) return [false, '', 'gecersiz adres'];
  $mid = '<'.date('YmdHis').'.'.bin2hex(random_bytes(6)).'@vestrasales.com>';
  $bnd = '=_vestra_'.bin2hex(random_bytes(8));
  $unsub = (string)($it['listUnsub'] ?? '') ?: 'https://vestrasales.com/lead-unsubscribe';
  if (preg_match('/[\r\n<>]/', $unsub)) $unsub = 'https://vestrasales.com/lead-unsubscribe';
  $name = mb_encode_mimeheader(str_replace(["\r", "\n", '"'], '', $fromName ?: 'VESTRA'), 'UTF-8', 'Q');
  $headers = implode("\r\n", [
    "From: {$name} <{$from}>", "Reply-To: {$from}", "Message-ID: {$mid}", 'Date: '.date('r'),
    "List-Unsubscribe: <{$unsub}>, <mailto:{$from}?subject=unsubscribe>", 'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
    'MIME-Version: 1.0', "Content-Type: multipart/alternative; boundary=\"{$bnd}\"",
  ]);
  $body = "--{$bnd}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode((string)($it['text'] ?? '')))
        ."--{$bnd}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode((string)($it['html'] ?? '')))
        ."--{$bnd}--\r\n";
  $subject = mb_encode_mimeheader(str_replace(["\r", "\n"], ' ', (string)($it['subject'] ?? '')), 'UTF-8', 'B', "\r\n");
  $ok = (bool)$mail($to, $subject, $body, $headers, '-f '.$from);
  return [$ok, $mid, $ok ? '' : 'posta servisi kabul etmedi'];
}

/** Maskeli adres (kütük için). */
function vestra_mailbox_mask(string $e): string {
  [$u, $d] = array_pad(explode('@', $e, 2), 2, '');
  return ($u !== '' ? mb_substr($u, 0, 1).'***' : '***').'@'.$d;
}

/**
 * Bir isteği (panel / elle) sunucuda baştan sona çalıştırır: liste → MX kontrolü → gönder → HER mektuptan
 * hemen sonra damgala (iş yarıda kesilse de ikinci kez gitmez) → 25–55 sn ara. 3 ardışık hatada durur.
 * $o: 'mail' (sahte mail()), 'sleep', 'dns' (domain → durum), 'log'. Döner: ['sent','bounced','failed','note'].
 */
function vestra_mailbox_run(array $req, array $o = []): array {
  $sleep = $o['sleep'] ?? static function (int $s): void { sleep($s); };
  $dns = $o['dns'] ?? 'vestra_mailbox_dns_status';
  $log = $o['log'] ?? static function (string $m): void { echo $m, "\n"; };
  $mail = $o['mail'] ?? null;
  $mode = (string)($req['mode'] ?? '');
  $sum = ['sent' => 0, 'bounced' => 0, 'failed' => 0, 'note' => ''];
  if ($mode === 'test') {
    $b = vestra_mailbox_sample((string)($req['campaign'] ?? 'lesgarage'), (string)($req['test_to'] ?? ''));
    if (!$b['items']) { $sum['note'] = 'test adresi ya da kampanya gecersiz'; $sum['error'] = $sum['note']; return $sum; }
    [$ok, $mid, $why] = vestra_mailbox_send_local($b['items'][0], (string)$b['from'], $mail);
    $sum[$ok ? 'sent' : 'failed'] = 1; $sum['note'] = $ok ? 'test gonderildi' : 'test gonderilemedi: '.$why;
    $log(($ok ? '+ TEST ' : 'x TEST ').vestra_mailbox_mask((string)$b['items'][0]['email']).' '.$mid);
    return $sum;
  }
  $ids = is_array($req['ids'] ?? null) ? $req['ids'] : array_filter(explode(',', (string)($req['ids'] ?? '')));
  $b = vestra_mailbox_batch((int)($req['limit'] ?? 25), (string)($req['campaign'] ?? 'lesgarage'), $ids);
  if (!$b['ok']) { $sum['note'] = 'kampanya bulunamadi'; $sum['error'] = $sum['note']; return $sum; }
  if (!$b['items']) { $sum['note'] = (string)($b['note'] ?? 'gonderilecek uygun musteri yok'); return $sum; }
  $n = count($b['items']); $failRow = 0; $sentAny = false;
  foreach ($b['items'] as $k => $it) {
    $dom = substr((string)strrchr((string)$it['email'], '@'), 1);
    if ($dns($dom) === 'dead') {
      vestra_mailbox_stamp([['leadId' => $it['leadId'], 'email' => $it['email'], 'status' => 'bounced', 'reason' => 'alan adinin e-posta sunucusu yok (gonderilmedi)']]);
      $sum['bounced']++; $log(sprintf('- [%d/%d] %s atlandi: alan adinin e-posta sunucusu yok', $k + 1, $n, vestra_mailbox_mask($it['email'])));
      continue;
    }
    if ($sentAny) $sleep(random_int(25, 55));
    [$ok, $mid, $why] = vestra_mailbox_send_local($it, (string)$b['from'], $mail);
    if ($ok) {
      vestra_mailbox_stamp([['leadId' => $it['leadId'], 'email' => $it['email'], 'status' => 'sent', 'lang' => $it['lang'], 'messageId' => $mid]]);
      $sum['sent']++; $failRow = 0; $sentAny = true;
      $log(sprintf('+ [%d/%d] %s [%s]', $k + 1, $n, vestra_mailbox_mask($it['email']), $it['lang']));
    } else {
      $sum['failed']++; $failRow++;
      $log(sprintf('x [%d/%d] %s — %s', $k + 1, $n, vestra_mailbox_mask($it['email']), $why));
      if ($failRow >= 3) { $sum['note'] = '3 ardisik hata — durduruldu, kalanlar sonraki istekte'; break; }
    }
  }
  return $sum;
}

/** 8 Eki lemlist CSV'si lead'leri "lemlist'e verildi" damgaladı ama lemlist'e HİÇ yüklenmedi. Verilen
 *  ID'lerden yalnız damgası 'lemlist' olanları normal sıraya geri alır (gerçekten yazılmış, çıkmış,
 *  geri dönmüş olana dokunmaz). Kaç lead'in geri alındığını döndürür. */
function vestra_mailbox_release_lemlist(array $ids): int {
  $want = array_flip(array_filter(array_map('trim', $ids))); if (!$want) return 0;
  $leads = vestra_leads(); $n = 0;
  foreach ($leads as &$l) {
    if (!isset($want[(string)($l['id'] ?? '')]) || (string)($l['contact_via'] ?? '') !== 'lemlist') continue;
    if (in_array((string)($l['status'] ?? ''), ['unsubscribed', 'bounced'], true)) continue;
    $l['last_contacted_at'] = ''; $l['contact_via'] = ''; $l['status'] = 'new'; $n++;
  }
  unset($l);
  if ($n) vestra_save_leads($leads);
  return $n;
}
