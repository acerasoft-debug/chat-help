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
