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

/* 10 Eki 2026 (operatör: "tavan 50 kişiyi kaldır, tavan 500 kişi kendi sunucum ile"). GoDaddy cPanel barındırma
   aktarıcısının sınırı e-posta hesabı başına günde ~500'dür; tavan tam bu sınıra ayarlı — sipariş/fatura Brevo'dan
   gittiği için o paya dokunmaz, ama support@'tan elle yazılan mektuplar da aynı sınıra sayılır. */
const VESTRA_MAILBOX_DEFAULT_CAP = 500;

function vestra_mailbox_file(string $name): string { return vestra_data_dir().'/'.$name; }

/** Doğrulanmış SMTP sunucusu (sethost yazar): ['smtp_host','smtp_port','verified_at',...] */
function vestra_mailbox_cfg(): array {
  $f = vestra_mailbox_file('mailbox.json');
  $d = is_readable($f) ? json_decode((string)file_get_contents($f), true) : [];
  return is_array($d) ? $d : [];
}

function vestra_mailbox_daily_cap(): int {
  $cfg = vestra_mailbox_cfg();
  /* 10 Eki öncesi kaydedilmiş tavan (eski 50 varsayılanı döneminden, 'daily_cap_set_at' yok) yok sayılır. */
  $c = (int)(isset($cfg['daily_cap_set_at']) ? ($cfg['daily_cap'] ?? VESTRA_MAILBOX_DEFAULT_CAP) : VESTRA_MAILBOX_DEFAULT_CAP);
  return max(1, min(VESTRA_MAILBOX_MAX_CAP, $c ?: VESTRA_MAILBOX_DEFAULT_CAP));
}

/** GoDaddy cPanel barındırma aktarıcısı: e-posta hesabı başına GÜNDE 500 (ve hesap geneli saatte 500).
 *  Tavan bunun altında tutulur; kalan pay elle yazılan / yanıt mektuplarına kalsın. */
const VESTRA_MAILBOX_MAX_CAP = 500;

function vestra_mailbox_set_cap(int $cap): int {
  $cap = max(1, min(VESTRA_MAILBOX_MAX_CAP, $cap));
  $f = vestra_mailbox_file('mailbox.json');
  $cur = vestra_mailbox_cfg(); $cur['daily_cap'] = $cap; $cur['daily_cap_set_at'] = date('c');
  file_put_contents($f, json_encode($cur, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), LOCK_EX); @chmod($f, 0600);
  return $cap;
}

/* ── Gönderim modu (10 Eki 2026, operatör: "istersem otomatik bulunur bulunmaz gönderim, istersem manuel
   olarak ayarlayabileyim; hata istemiyorum, kendi sunucumdan support@vestrasales.com"). ─────────────────
   auto  = her arama bitince (günde iki tur + panelden başlatılan) yeni bulunanlara kampanya sunucunun
           kendi posta servisinden gider (find-customers.yml → vestra-mailbox-queue.php auto).
   manual= arama yalnız listeye ekler; gönderimi panelden siz başlatırsınız.
   Alıcı havuzu varsayılan 'web': web aramasıyla bulunmuş, sitesinde adres yayınlayan, e-posta sunucusu
   doğrulanmış butikler — eski listeden (OSM/içe aktarma) gönderim 9 Eki'de ölü alan adlarına çarptı. */
const VESTRA_MAILBOX_AUTO_DEFAULTS = ['auto_send' => true, 'auto_campaign' => 'edit', 'auto_limit' => 100, 'auto_pool' => 'web'];

function vestra_mailbox_auto(): array {
  $c = vestra_mailbox_cfg(); $d = VESTRA_MAILBOX_AUTO_DEFAULTS;
  return ['auto_send' => array_key_exists('auto_send', $c) ? (bool)$c['auto_send'] : $d['auto_send'],
          'auto_campaign' => preg_match('/^[A-Za-z0-9:_-]{1,40}$/', (string)($c['auto_campaign'] ?? '')) ? (string)$c['auto_campaign'] : $d['auto_campaign'],
          'auto_limit' => max(1, min(VESTRA_MAILBOX_MAX_CAP, (int)($c['auto_limit'] ?? $d['auto_limit']))),
          'auto_pool' => in_array($c['auto_pool'] ?? '', ['web', 'all'], true) ? (string)$c['auto_pool'] : $d['auto_pool']];
}

function vestra_mailbox_auto_save(bool $on, string $campaign, int $limit, string $pool): array {
  $f = vestra_mailbox_file('mailbox.json'); $cur = vestra_mailbox_cfg();
  $cur['auto_send'] = $on;
  if (preg_match('/^[A-Za-z0-9:_-]{1,40}$/', $campaign)) $cur['auto_campaign'] = $campaign;
  $cur['auto_limit'] = max(1, min(VESTRA_MAILBOX_MAX_CAP, $limit));
  $cur['auto_pool'] = $pool === 'all' ? 'all' : 'web';
  file_put_contents($f, json_encode($cur, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), LOCK_EX); @chmod($f, 0600);
  return vestra_mailbox_auto();
}

/** Arama bitince çağrılır: otomatik moddaysa gönderim isteği yazar. [ok, mesaj]. Kampanya silinmişse
 *  standart davete düşer (Claude kampanyası listeden çıkmış olabilir). */
function vestra_mailbox_auto_request(?array $campaignKeys = null, string $finderReqId = ''): array {
  $a = vestra_mailbox_auto();
  /* Panelden başlatılan aramada seçilen kampanya (kayıtta 'send_campaign') genel moddan önce gelir:
     'none' = gönderme; geçerli bir kampanya = o kampanyayla gönder (manuel modda bile); boş = genel mod. */
  if ($finderReqId !== '' && function_exists('vestra_finder_runs')) {
    foreach (vestra_finder_runs() as $r) {
      if ((string)($r['id'] ?? '') !== $finderReqId) continue;
      $sc = (string)($r['send_campaign'] ?? '');
      if ($sc === 'none') return [false, 'Bu arama "gönderme, sadece bul" ile başlatıldı — bulunanlar listede bekliyor.'];
      if ($sc !== '') {
        $camp = ($campaignKeys !== null && !in_array($sc, $campaignKeys, true)) ? 'edit' : $sc;
        return vestra_mailbox_request('send', $a['auto_limit'], $camp, '', $campaignKeys, 'web');
      }
      break;
    }
  }
  if (!$a['auto_send']) return [false, 'Otomatik gönderim KAPALI (manuel mod) — bulunanlar listede bekliyor, panelden gönderin.'];
  $camp = ($campaignKeys !== null && !in_array($a['auto_campaign'], $campaignKeys, true)) ? 'edit' : $a['auto_campaign'];
  return vestra_mailbox_request('send', $a['auto_limit'], $camp, '', $campaignKeys, $a['auto_pool']);
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
function vestra_mailbox_request(string $mode, int $limit, string $campaign, string $testTo, ?array $campaignKeys = null, string $pool = 'web', array $ids = []): array {
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
  /* Belirli müşteriler (ör. bir aramada bulunanlar — "📮 Bunlara şimdi gönder"): yalnız onlar seçilir. */
  $ids = array_values(array_unique(array_filter(array_map(static fn($x) => preg_replace('/[^A-Za-z0-9_-]/', '', (string)$x), $ids))));
  $runs[] = ['id' => $id, 'mode' => $mode, 'limit' => $limit, 'campaign' => $campaign, 'pool' => $pool === 'all' ? 'all' : 'web', 'ids' => $mode === 'send' ? array_slice($ids, 0, 200) : [], 'test_to' => $mode === 'test' ? $testTo : '',
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
function vestra_mailbox_batch(int $limit, string $campKey, array $ids = [], string $pool = 'web'): array {
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
      if (vestra_email_is_junk($e) || vestra_lead_looks_dead($l) || vestra_lead_off_target($l)) continue;
      $seen[$e] = true; $targets[$i] = $l;
    }
  } else {
    $targets = vestra_finder_send_targets($limit, $pool === 'all' ? 'all' : 'web');
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
function vestra_mailbox_send_local(array $it, string $fromName, ?callable $mail = null, array $o = []): array {
  $mail = $mail ?? 'mail';
  $from = vestra_mail_house_address();
  $to = strtolower(trim((string)($it['email'] ?? '')));
  if (!filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n,;]/', $to)) return [false, '', 'gecersiz adres'];
  $mid = '<'.date('YmdHis').'.'.bin2hex(random_bytes(6)).'@vestrasales.com>';
  $bnd = '=_vestra_'.bin2hex(random_bytes(8));
  $unsub = (string)($it['listUnsub'] ?? '') ?: 'https://vestrasales.com/lead-unsubscribe';
  if (preg_match('/[\r\n<>]/', $unsub)) $unsub = 'https://vestrasales.com/lead-unsubscribe';
  $name = mb_encode_mimeheader(str_replace(["\r", "\n", '"'], '', $fromName ?: 'VESTRA'), 'UTF-8', 'Q');
  /* Satıcı adına gönderim (10 Eki 2026): From yine support@ (SPF/DKIM/DMARC geçsin), görünen ad satıcı,
     Reply-To satıcının kendi adresi — yanıt doğrudan satıcıya gider. */
  $replyTo = strtolower(trim((string)($o['reply_to'] ?? '')));
  if (!filter_var($replyTo, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n,;<>]/', $replyTo)) $replyTo = $from;
  $lines = [
    "From: {$name} <{$from}>", "Reply-To: {$replyTo}", "Message-ID: {$mid}", 'Date: '.date('r'),
    "List-Unsubscribe: <{$unsub}>, <mailto:{$from}?subject=unsubscribe>", 'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
    'MIME-Version: 1.0', "Content-Type: multipart/alternative; boundary=\"{$bnd}\"",
  ];
  $body = "--{$bnd}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode((string)($it['text'] ?? '')))
        ."--{$bnd}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode((string)($it['html'] ?? '')))
        ."--{$bnd}--\r\n";
  $subject = mb_encode_mimeheader(str_replace(["\r", "\n"], ' ', (string)($it['subject'] ?? '')), 'UTF-8', 'B', "\r\n");
  /* DKIM: anahtar varsa imzala (DNS kaydı henüz yoksa alıcı imzayı "doğrulanamadı" sayar, mektup yine gider). */
  static $dkimLive = null;   // DNS kaydı yayımlanıp anahtarla eşleşmeden imza atılmaz (alıcı "anahtar yok" görmesin)
  if (!isset($o['dkim_key']) && $dkimLive === null) $dkimLive = vestra_dkim_dns_status() === 'ok';
  $dkim = (isset($o['dkim_key']) || $dkimLive) ? vestra_dkim_sign($lines, $to, $subject, $body, $o['dkim_key'] ?? null) : '';
  if ($dkim !== '') array_unshift($lines, $dkim);
  $headers = implode("\r\n", $lines);
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
  $b = vestra_mailbox_batch((int)($req['limit'] ?? 25), (string)($req['campaign'] ?? 'lesgarage'), $ids, (string)($req['pool'] ?? 'web'));
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

/* ───────────────────────── DKIM imzası (9 Eki 2026) ─────────────────────────
 * Bu GoDaddy planında cPanel'in DKIM özelliği kapalı ("emailauth" yok); sunucunun posta servisi
 * mektubu imzalamıyor (9 Eki Gmail testi: SPF=pass, DMARC=pass, DKIM yok). Gmail/Yahoo için
 * SPF+DKIM birlikte en iyi sonuç. Bu yüzden anahtar çiftini sunucu kendisi üretir (data/dkim/,
 * 0600, web'den erişilemez), mektubu PHP imzalar (RFC 6376, rsa-sha256, relaxed/relaxed),
 * operatör yalnız TEK bir DNS TXT kaydı ekler: <seçici>._domainkey.vestrasales.com. */

const VESTRA_DKIM_SELECTOR = 'vestra';
const VESTRA_DKIM_DOMAIN   = 'vestrasales.com';

function vestra_dkim_dir(): string { return vestra_data_dir().'/dkim'; }

/** Anahtar çifti: yoksa üretir (2048 bit). ['private'=>PEM,'public_b64'=>...]; üretilemezse null. */
function vestra_dkim_keys(bool $create = true): ?array {
  $dir = vestra_dkim_dir(); $pf = $dir.'/'.VESTRA_DKIM_SELECTOR.'.private.pem'; $pub = $dir.'/'.VESTRA_DKIM_SELECTOR.'.public.txt';
  if (is_readable($pf) && is_readable($pub)) return ['private' => (string)file_get_contents($pf), 'public_b64' => trim((string)file_get_contents($pub))];
  if (!$create || !function_exists('openssl_pkey_new')) return null;
  if (!is_dir($dir)) { @mkdir($dir, 0700, true); @file_put_contents($dir.'/.htaccess', "Require all denied\nDeny from all\n"); }
  $k = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
  if (!$k || !openssl_pkey_export($k, $pem)) return null;
  $det = openssl_pkey_get_details($k);
  $pubPem = (string)($det['key'] ?? '');
  $b64 = preg_replace('/-----[^-]+-----|\s+/', '', $pubPem);
  if ($b64 === '' || file_put_contents($pf, $pem, LOCK_EX) === false) return null;
  @chmod($pf, 0600); file_put_contents($pub, $b64, LOCK_EX); @chmod($pub, 0600);
  return ['private' => $pem, 'public_b64' => $b64];
}

/** DNS'e eklenecek kayıt: [ad, değer]. */
function vestra_dkim_dns_record(): ?array {
  $k = vestra_dkim_keys(true); if (!$k) return null;
  return [VESTRA_DKIM_SELECTOR.'._domainkey.'.VESTRA_DKIM_DOMAIN, 'v=DKIM1; k=rsa; p='.$k['public_b64']];
}

/** DNS'te kayıt yayımlanmış ve bizim anahtarla eşleşiyor mu? 'ok' | 'missing' | 'mismatch' | 'unknown'. */
function vestra_dkim_dns_status(?callable $lookup = null): string {
  $k = vestra_dkim_keys(false); if (!$k) return 'missing';
  $name = VESTRA_DKIM_SELECTOR.'._domainkey.'.VESTRA_DKIM_DOMAIN;
  $lookup = $lookup ?? static function (string $n): ?array { $r = @dns_get_record($n, DNS_TXT); return $r === false ? null : array_map(fn($x) => (string)($x['txt'] ?? ''), $r); };
  $txts = $lookup($name);
  if ($txts === null) return 'unknown';
  foreach ($txts as $t) {
    if (!str_contains($t, 'v=DKIM1')) continue;
    return preg_match('/p=([A-Za-z0-9+\/=]+)/', str_replace([' ', '"'], '', $t), $m) && $m[1] === $k['public_b64'] ? 'ok' : 'mismatch';
  }
  return 'missing';
}

/** Kanonik (relaxed) başlık ve gövde — RFC 6376 §3.4. */
function vestra_dkim_canon_header(string $name, string $value): string {
  $v = preg_replace('/\r?\n[ \t]+/', ' ', $value);          // unfold
  $v = preg_replace('/[ \t]+/', ' ', (string)$v);
  return strtolower(trim($name)).':'.trim((string)$v);
}
function vestra_dkim_canon_body(string $body): string {
  $b = str_replace("\r\n", "\n", $body);
  $b = preg_replace('/[ \t]+/', ' ', $b);
  $b = preg_replace('/ \n/', "\n", (string)$b);
  $b = rtrim((string)$b, "\n");
  return str_replace("\n", "\r\n", $b)."\r\n";
}

/**
 * DKIM-Signature başlığını üretir (imzalanacak başlıklar: from, to, subject, date, message-id,
 * reply-to, list-unsubscribe, mime-version, content-type). $headers: "Ad: değer" satırları (CRLF).
 * Anahtar yoksa '' döner — mektup yine gider, imzasız. */
function vestra_dkim_sign(array $headerLines, string $to, string $subject, string $body, ?string $privatePem = null): string {
  $pem = $privatePem ?? (vestra_dkim_keys(true)['private'] ?? '');
  if ($pem === '' || !function_exists('openssl_sign')) return '';
  $all = [];
  foreach ($headerLines as $ln) { if (preg_match('/^([^:]+):\s*(.*)$/s', $ln, $m)) $all[strtolower(trim($m[1]))] = [$m[1], $m[2]]; }
  $all['to'] = ['To', $to]; $all['subject'] = ['Subject', $subject];
  $order = ['from', 'to', 'subject', 'date', 'message-id', 'reply-to', 'list-unsubscribe', 'list-unsubscribe-post', 'mime-version', 'content-type'];
  $h = []; $canon = '';
  foreach ($order as $n) { if (!isset($all[$n])) continue; $h[] = $n; $canon .= vestra_dkim_canon_header($n, $all[$n][1])."\r\n"; }
  $bh = base64_encode(hash('sha256', vestra_dkim_canon_body($body), true));
  $sig = 'v=1; a=rsa-sha256; c=relaxed/relaxed; d='.VESTRA_DKIM_DOMAIN.'; s='.VESTRA_DKIM_SELECTOR.'; t='.time().'; h='.implode(':', $h).'; bh='.$bh.'; b=';
  $canon .= vestra_dkim_canon_header('DKIM-Signature', $sig);   // b= boş, satır sonu YOK
  $key = openssl_pkey_get_private($pem);
  if (!$key || !openssl_sign($canon, $raw, $key, OPENSSL_ALGO_SHA256)) return '';
  return 'DKIM-Signature: '.$sig.chunk_split(base64_encode($raw), 72, "\r\n\t");
}

/**
 * Müşteri listesindeki "Seçilenlere gönder" / "Tek tek gönder" (platform adına) için tek mektup: kendi sunucumuzun
 * posta servisiyle (operatör, 9 Eki 2026: "sipariş, fatura vs Brevo'dan gitmeye devam etsin, kampanyalar kendi
 * sunucumuzdan olsun"). Günlük tavan, kapalı alan adı kontrolü ve DKIM burada da geçerli.
 * [ok, Message-ID | neden]  neden: 'cap' | 'noemail' | 'deaddomain' | posta servisi mesajı.
 */
function vestra_mailbox_send_lead(array $l, string $subject, string $body, string $fromName = 'VESTRA', string $heroImg = '', array $opts = [], ?callable $mail = null, ?callable $dns = null): array {
  if (vestra_mailbox_left_today() <= 0) return [false, 'cap'];
  $email = vestra_email_clean((string)($l['email'] ?? ''));
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return [false, 'noemail'];
  $dns = $dns ?? 'vestra_mailbox_dns_status';
  if ($dns(substr((string)strrchr($email, '@'), 1)) === 'dead') return [false, 'deaddomain'];
  $tok = (string)($l['unsub_token'] ?? '');
  $item = ['email' => $email, 'subject' => $subject,
           'html' => vestra_html_email($body, $heroImg, $opts), 'text' => vestra_mail_text_part($body, $opts),
           'listUnsub' => $tok !== '' ? 'https://vestrasales.com/lead-unsubscribe?token='.rawurlencode($tok) : 'https://vestrasales.com/lead-unsubscribe'];
  [$ok, $mid, $why] = vestra_mailbox_send_local($item, $fromName !== '' ? $fromName : 'VESTRA', $mail);
  return [$ok, $ok ? $mid : $why];
}

/* ── Canlı durum (10 Eki 2026, operatör: "şu anda gönderiyor mu bilmiyorum, arıyor mu onu da bilmiyorum,
   görünmüyor"). Admin ▸ Müşteriler'in en üstündeki şerit bunu basar ve /admin?live=1 ile 30 sn'de bir yeniler.
   ['search'=>['state'=>'running|queued|idle','text'=>…], 'send'=>['state'=>'running|queued|idle','text'=>…,'done'=>n,'total'=>n]] */
function vestra_live_status(): array {
  require_once __DIR__.'/finder.php';
  $hm = static fn($t) => date('H:i', is_int($t) ? $t : ((int)strtotime((string)$t)));
  /* Arama */
  if (function_exists('vestra_finder_refresh')) @vestra_finder_refresh();
  $runs = function_exists('vestra_finder_runs') ? vestra_finder_runs() : [];
  $act = function_exists('vestra_finder_active') ? vestra_finder_active() : null;
  $now = time();
  $next = null; foreach ([[5, 20], [15, 20]] as [$H, $M]) { $t = gmmktime($H, $M, 0, (int)gmdate('n'), (int)gmdate('j'), (int)gmdate('Y')); if ($t <= $now) $t += 86400; $next = $next === null ? $t : min($next, $t); }
  if ($act) {
    $started = !empty($act['dispatched_at']);
    $search = ['state' => $started ? 'running' : 'queued',
               'text' => $started ? 'Arama ÇALIŞIYOR — '.$hm($act['dispatched_at']).'\'de başladı, genelde 20-40 dk sürer.'
                                  : 'Arama SIRADA — '.$hm($act['requested_at'] ?? '').'\'de istendi.'
                                    .((function_exists('vestra_finder_gh_token') && vestra_finder_gh_token() === '')
                                      ? ((time() - (int)strtotime((string)($act['requested_at'] ?? ''))) > 900
                                          ? ' GitHub\'ın sıra işi gecikiyor (saatler sürebilir) — anında başlaması için 🌐 kartındaki 🔑 GitHub anahtarını ekleyin.'
                                          : ' GitHub\'ın sıra işi alınca başlar (genelde 10-60 dk).')
                                      : ' Sunucu en geç 10 dk içinde başlatır.')];
  } else {
    $last = null; foreach ($runs as $r) if (($r['owner'] ?? '') === '' && in_array($r['status'] ?? '', ['done', 'failed'], true)) { $last = $r; break; }
    $search = ['state' => 'idle', 'text' => 'Şu an arama yok'
      .($last ? ' · son arama '.date('d.m H:i', (int)strtotime((string)($last['finished_at'] ?? $last['requested_at'] ?? ''))).(($last['status'] ?? '') === 'done' ? ' → '.(int)($last['added_count'] ?? 0).' yeni müşteri' : ' → başarısız') : '')
      .' · sonraki otomatik arama '.date('d.m H:i', $next).(function_exists('vestra_finder_ready') && !vestra_finder_ready() ? ' (web araması KAPALI)' : '')];
  }
  /* Gönderim */
  $open = null; foreach (array_reverse(vestra_mailbox_runs()) as $r) if (vestra_mailbox_is_open($r)) { $open = $r; break; }
  $today = vestra_mailbox_sent_today();
  if ($open && ($open['status'] ?? '') === 'running') {
    $since = (int)strtotime((string)($open['started_at'] ?? $open['requested_at'] ?? '')); $done = 0; $lastAt = 0;
    foreach (vestra_leads() as $l) { if (($l['contact_via'] ?? '') !== 'mailbox') continue; $c = (int)strtotime((string)($l['last_contacted_at'] ?? ''));
      if ($c >= $since) { $done++; $lastAt = max($lastAt, $c); } }
    $total = ($open['mode'] ?? '') === 'test' ? 1 : (int)($open['limit'] ?? 0);
    $send = ['state' => 'running', 'done' => min($done, $total), 'total' => $total,
             'text' => ($open['mode'] ?? '') === 'test' ? 'Test e-postası GÖNDERİLİYOR.'
               : 'GÖNDERİLİYOR — '.min($done, $total).' / '.$total.' gitti'.($lastAt ? ' · son e-posta '.$hm($lastAt) : '').' · e-postalar arası 25-55 sn'];
  } elseif ($open) {
    $send = ['state' => 'queued', 'text' => 'Gönderim SIRADA — '.(($open['mode'] ?? '') === 'test' ? 'test e-postası' : (int)$open['limit'].' müşteri').', '.$hm($open['requested_at'] ?? '').'\'de istendi, 10 dk içinde başlar.'];
  } else {
    $lastR = null; foreach (array_reverse(vestra_mailbox_runs()) as $r) if (($r['mode'] ?? '') === 'send' && ($r['status'] ?? '') === 'done') { $lastR = $r; break; }
    $send = ['state' => 'idle', 'text' => 'Şu an gönderim yok · bugün '.$today.' e-posta gitti, kalan hak '.vestra_mailbox_left_today()
      .($lastR ? ' · son gönderim '.date('d.m H:i', (int)strtotime((string)($lastR['finished_at'] ?? $lastR['requested_at'] ?? ''))).' → '.(int)($lastR['sent'] ?? 0).' gitti' : '')];
  }
  return ['search' => $search, 'send' => $send, 'at' => date('H:i:s')];
}
