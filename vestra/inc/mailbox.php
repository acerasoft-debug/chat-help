<?php
/**
 * VESTRA — support@vestrasales.com'dan kampanya gönderimi: panel isteği kuyruğu + günlük tavan.
 *
 * 10 Eki 2026'dan beri gönderimi SUNUCUNUN KENDİ posta servisi yapar (cron_mailbox.php, 10 dk'da bir panel
 * isteğini alır; GitHub koşucusu yalnız yedek). Panel isteği data/mailbox_runs.json'a "requested" yazar;
 * gönderim bitince sonucu buraya yazar. Günlük tavan varsayılan 500 (GoDaddy hesap sınırı); panel de, cron da
 * bugün gidenleri sayar; tavan dolunca kimseye gitmez. Satıcıların sunucu yolu inc/seller_outbox.php'de.
 */
require_once __DIR__.'/leads.php';
require_once __DIR__.'/notify.php';

/* 10 Eki 2026 (operatör: "tavan 50 kişiyi kaldır, tavan 500 kişi kendi sunucum ile"). GoDaddy cPanel barındırma
   aktarıcısının sınırı e-posta hesabı başına günde ~500'dür; tavan tam bu sınıra ayarlı — sipariş/fatura Brevo'dan
   gittiği için o paya dokunmaz, ama support@'tan elle yazılan mektuplar da aynı sınıra sayılır. */
const VESTRA_MAILBOX_DEFAULT_CAP = 600;   // 25 Eki 2026 (operatör: "her gün 600 email"); önce 500

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

/** GoDaddy cPanel aktarıcısı: hesap geneli SAATTE 500 (uapi maximum_emails_per_hour, diag-mail-limits ölçümü). Partiler
 *  mektuplar arası 8–11 sn bekler (saatte ≤ 450) ve aynı anda tek parti açıktır -- saat sınırı aşılmaz.
 *  25 Eki 2026 (operatör: "her gün 600 email + 150 Brevo"): günlük üst sınır 500 → 600. */
const VESTRA_MAILBOX_MAX_CAP = 600;

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
const VESTRA_MAILBOX_AUTO_DEFAULTS = ['auto_send' => true, 'auto_campaign' => 'edit', 'auto_limit' => 500, 'auto_pool' => 'web'];   // 10 Eki: "bir seferde 500 kişi"

function vestra_mailbox_auto(): array {
  $c = vestra_mailbox_cfg(); $d = VESTRA_MAILBOX_AUTO_DEFAULTS;
  return ['auto_send' => array_key_exists('auto_send', $c) ? (bool)$c['auto_send'] : $d['auto_send'],
          'auto_campaign' => preg_match('/^[A-Za-z0-9:_-]{1,40}$/', (string)($c['auto_campaign'] ?? '')) && $c['auto_campaign'] !== 'deals' ? (string)$c['auto_campaign'] : $d['auto_campaign'],
          'auto_limit' => max(1, min(VESTRA_MAILBOX_MAX_CAP, (int)($c['auto_limit'] ?? $d['auto_limit']))),
          'auto_pool' => in_array($c['auto_pool'] ?? '', ['web', 'all'], true) ? (string)$c['auto_pool'] : $d['auto_pool']];
}

function vestra_mailbox_auto_save(bool $on, string $campaign, int $limit, string $pool): array {
  $f = vestra_mailbox_file('mailbox.json'); $cur = vestra_mailbox_cfg();
  $cur['auto_send'] = $on;
  if (preg_match('/^[A-Za-z0-9:_-]{1,40}$/', $campaign) && $campaign !== 'deals') $cur['auto_campaign'] = $campaign;   // indirim mektubu ilk mektup olamaz
  $cur['auto_limit'] = max(1, min(VESTRA_MAILBOX_MAX_CAP, $limit));
  $cur['auto_pool'] = $pool === 'all' ? 'all' : 'web';
  file_put_contents($f, json_encode($cur, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), LOCK_EX); @chmod($f, 0600);
  return vestra_mailbox_auto();
}

/** Arama bitince çağrılır: otomatik moddaysa gönderim isteği yazar. [ok, mesaj]. Kampanya silinmişse
 *  standart davete düşer (Claude kampanyası listeden çıkmış olabilir). */
function vestra_mailbox_auto_request(?array $campaignKeys = null, string $finderReqId = '', ?int $now = null): array {
  $a = vestra_mailbox_auto();
  /* Panelden başlatılan aramada seçilen kampanya (kayıtta 'send_campaign') genel moddan önce gelir:
     'none' = gönderme; geçerli bir kampanya = o kampanyayla gönder (manuel modda bile); boş = genel mod. */
  if ($finderReqId !== '' && function_exists('vestra_finder_runs')) {
    foreach (vestra_finder_runs() as $r) {
      if ((string)($r['id'] ?? '') !== $finderReqId) continue;
      $sc = (string)($r['send_campaign'] ?? '');
      if ($sc === 'none') return [false, 'Bu arama "gönderme, sadece bul" ile başlatıldı — bulunanlar listede bekliyor.'];
      if ($sc !== '') {
        $camp = ($campaignKeys !== null && !in_array($sc, $campaignKeys, true)) || $sc === 'deals' ? 'edit' : $sc;
        $lim = min($a['auto_limit'], vestra_mailbox_left_today() - vestra_mailbox_deals_reserve($now));   // 17 Eki 2026: indirim payı burada da ayrılır
        if ($lim <= 0) return [false, 'Bugünkü kalan pay eski müşterilere indirim mektubuna ayrıldı — bulunanlar sırada, pay açılınca (en geç 23:45) gider.'];
        return vestra_mailbox_request('send', $lim, $camp, '', $campaignKeys, 'web');
      }
      break;
    }
  }
  if (!$a['auto_send']) return [false, 'Otomatik gönderim KAPALI (manuel mod) — bulunanlar listede bekliyor, panelden gönderin.'];
  $camp = ($campaignKeys !== null && !in_array($a['auto_campaign'], $campaignKeys, true)) || $a['auto_campaign'] === 'deals' ? 'edit' : $a['auto_campaign'];
  /* 17 Eki 2026: bugünkü indirim payı (en çok 300) yeni bulunanlara harcanmaz — kalanlar bir sonraki boşlukta / gece gider. */
  $lim = min($a['auto_limit'], vestra_mailbox_left_today() - vestra_mailbox_deals_reserve($now));
  if ($lim <= 0) return [false, 'Bugünkü kalan pay eski müşterilere indirim mektubuna ayrıldı — bulunanlar sırada, pay açılınca (en geç 23:45) gider.'];
  return vestra_mailbox_request('send', $lim, $camp, '', $campaignKeys, $a['auto_pool']);
}

/** Bugün (sunucu günü) posta kutusundan gönderilmiş lead sayısı. */
function vestra_mailbox_sent_today(?array $leads = null): int {
  $today = date('Y-m-d'); $n = 0;
  foreach ($leads ?? vestra_leads() as $l)
    if ((string)($l['contact_via'] ?? '') === 'mailbox' && str_starts_with((string)($l['last_contacted_at'] ?? ''), $today)) $n++;
  /* Satıcıların sunucu yolu da support@ hesabından gider; GoDaddy'nin günlük 500'ü hesap başına (10 Eki 2026). */
  if ($leads === null) $n += vestra_mailbox_seller_server_today() + vestra_mailbox_member_server_today();
  return $n;
}

/** Bugün KAYITLI hesaplara sunucudan giden kampanya mektubu (data/member_outbox.json, 22 Eki 2026: indirim kampanyası
 *  önce kayıtlı alıcılara). Aynı support@ hesabından çıktığı için GoDaddy'nin günlük 500'üne sayılır. */
function vestra_mailbox_member_server_today(): int {
  $f = vestra_mailbox_file('member_outbox.json');
  $d = is_readable($f) ? json_decode((string)file_get_contents($f), true) : [];
  $today = date('Y-m-d'); $n = 0;
  foreach ((array)$d as $it)
    if (is_array($it) && ($it['status'] ?? '') === 'sent' && str_starts_with((string)($it['at'] ?? ''), $today)) $n++;
  return $n;
}

/** Bugün satıcılar adına VESTRA sunucusundan giden mektup (data/seller_outbox.json, via=server, sent). */
function vestra_mailbox_seller_server_today(): int {
  $f = vestra_mailbox_file('seller_outbox.json');
  $d = is_readable($f) ? json_decode((string)file_get_contents($f), true) : [];
  $today = date('Y-m-d'); $n = 0;
  foreach ((array)$d as $it)
    if (is_array($it) && ($it['via'] ?? '') === 'server' && ($it['status'] ?? '') === 'sent' && str_starts_with((string)($it['done_at'] ?? ''), $today)) $n++;
  return $n;
}

/* ── Spam önlemleri (10 Eki 2026, operatör: "spama düşmemesi için önlem al") ─────────────────────────────
   1) ISINMA: yeni bir gönderen alan adı ilk günden 500 soğuk e-posta atarsa Gmail/Outlook onu toplu gönderici
      sayıp spama atar ve itibar haftalarca düzelmez. Günlük sayı ilk gönderim gününden itibaren kademeli artar,
      11. günden sonra tam tavan. Panelden kapatılabilir (önerilmez).
   2) GERİ DÖNME FRENİ: son 3 günde gönderilenlerin %8'inden fazlası geri döndüyse gönderim durur — yüksek
      geri dönme oranı spam klasörünün bir numaralı sebebi; liste temizlenmeden devam edilmez.
   3) Aynı dükkânın iki adresine aynı partide yazılmaz; partide aynı alan adına arka arkaya gönderilmez.
   4) Her mektupta şirketin posta adresi (CAN-SPAM / AB) — kampanya metninde yoksa eklenir. */
const VESTRA_MAILBOX_WARMUP = [1 => 50, 2 => 75, 3 => 100, 4 => 150, 5 => 200, 6 => 250, 7 => 300, 8 => 350, 9 => 400, 10 => 450];
const VESTRA_MAILBOX_BOUNCE_MAX = 0.08;
/* 11 Eki 2026: pencere 14 → 3 gün. Geri dönen adresler listeden zaten düşüyor; 14 günlük pencerede eski bir partinin
   (8 Eki, eski OSM listesi) geri dönmeleri freni iki hafta kilitliyordu — gönderim olmadan oran hiç düşmezdi. */
const VESTRA_MAILBOX_BOUNCE_DAYS = 3;
const VESTRA_MAILBOX_BOUNCE_MIN_SENT = 30;
const VESTRA_MAIL_POSTAL = 'Acerasoft LLC · 8 The Green, Suite B, Dover, DE 19901, USA';

/** Isınma durumu: ['on','day','limit','start','full_on']. Başlangıç = ilk gerçek kampanya gönderimi günü. */
function vestra_mailbox_warmup(?array $leads = null): array {
  $cfg = vestra_mailbox_cfg();
  $on = !array_key_exists('warmup', $cfg) || !empty($cfg['warmup']);
  $start = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($cfg['warmup_start'] ?? '')) ? (string)$cfg['warmup_start'] : '';
  if ($start === '') {
    foreach (vestra_mailbox_runs() as $r)
      if (($r['mode'] ?? '') === 'send' && (int)($r['sent'] ?? 0) > 0 && ($d = substr((string)($r['requested_at'] ?? ''), 0, 10)) !== '' && ($start === '' || $d < $start)) $start = $d;
    foreach ($leads ?? vestra_leads() as $l)
      if ((string)($l['contact_via'] ?? '') === 'mailbox' && ($d = substr((string)($l['last_contacted_at'] ?? ''), 0, 10)) !== '' && ($start === '' || $d < $start)) $start = $d;
  }
  $today = date('Y-m-d'); if ($start === '' || $start > $today) $start = $today;
  $day = (int)floor((strtotime($today) - strtotime($start)) / 86400) + 1;
  $limit = VESTRA_MAILBOX_WARMUP[$day] ?? VESTRA_MAILBOX_MAX_CAP;
  return ['on' => $on, 'day' => $day, 'limit' => $limit, 'start' => $start,
          'full_on' => date('Y-m-d', strtotime($start) + count(VESTRA_MAILBOX_WARMUP) * 86400)];
}

/** İlk gerçek gönderimde başlangıcı sabitle (eski kayıtlardan çıkarılan tarih de yazılır). */
function vestra_mailbox_warmup_mark(): void {
  $cfg = vestra_mailbox_cfg();
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($cfg['warmup_start'] ?? ''))) return;
  $cfg['warmup_start'] = vestra_mailbox_warmup()['start'];
  $f = vestra_mailbox_file('mailbox.json');
  file_put_contents($f, json_encode($cfg, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), LOCK_EX); @chmod($f, 0600);
}

function vestra_mailbox_warmup_set(bool $on): void {
  $cfg = vestra_mailbox_cfg(); $cfg['warmup'] = $on;
  $f = vestra_mailbox_file('mailbox.json');
  file_put_contents($f, json_encode($cfg, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), LOCK_EX); @chmod($f, 0600);
}

/** Bugün geçerli tavan: panel tavanı, ısınma açıksa ısınma sınırıyla. */
function vestra_mailbox_cap_today(?array $leads = null): int {
  $w = vestra_mailbox_warmup($leads);
  return $w['on'] ? min(vestra_mailbox_daily_cap(), $w['limit']) : vestra_mailbox_daily_cap();
}

/** Son VESTRA_MAILBOX_BOUNCE_DAYS gün: ['sent','bounced','rate','stop']. Gönderilmeden atlanan ölü alan adları sayılmaz. */
function vestra_mailbox_bounce_stats(?array $leads = null): array {
  /* "Liste temizlendi, devam et" (operatör onayı): o andan ÖNCEKİ gönderimler sayılmaz — geri dönenler zaten listeden
     düştü; fren bundan sonraki gönderimlerin oranına bakar. 11 Eki 2026: tarayıcı Çöp kutusundaki 42 eski bildirimi
     (8–10 Eki gönderimleri) bir kerede işaretleyince oran %15'e çıktı ve fren temizlenmiş liste yüzünden 3 gün kilitlendi. */
  $since = max(time() - VESTRA_MAILBOX_BOUNCE_DAYS * 86400, (int)strtotime((string)(vestra_mailbox_cfg()['bounce_reset_at'] ?? ''))); $s = 0; $b = 0;
  foreach ($leads ?? vestra_leads() as $l) {
    if (!in_array((string)($l['contact_via'] ?? ''), ['mailbox', 'seller_server'], true)) continue;
    if ((int)strtotime((string)($l['last_contacted_at'] ?? '')) < $since) continue;
    $s++;
    if (($l['status'] ?? '') === 'bounced' && !str_contains((string)($l['bounce_reason'] ?? ''), 'gonderilmedi')) $b++;
  }
  $rate = $s ? $b / $s : 0.0;
  return ['sent' => $s, 'bounced' => $b, 'rate' => $rate, 'stop' => $s >= VESTRA_MAILBOX_BOUNCE_MIN_SENT && $rate > VESTRA_MAILBOX_BOUNCE_MAX];
}

/** Operatör: geri dönenler listeden düştü, frenin sayacını sıfırla. */
function vestra_mailbox_bounce_reset(): void {
  $cfg = vestra_mailbox_cfg(); $cfg['bounce_reset_at'] = date('c');
  $f = vestra_mailbox_file('mailbox.json');
  file_put_contents($f, json_encode($cfg, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), LOCK_EX); @chmod($f, 0600);
}

/** Partide aynı alan adı arka arkaya gelmesin (Gmail/Outlook aynı gönderenden ardışık yağmuru sever değil). */
function vestra_mailbox_interleave(array $items): array {
  $by = [];
  foreach ($items as $it) $by[strtolower(substr((string)strrchr((string)($it['email'] ?? ''), '@'), 1))][] = $it;
  /* Her adımda en çok kalanı olan ve bir önceki mektuptan FARKLI alan adını seç (açgözlü; kaçınılmazsa yan yana). */
  $out = []; $last = null;
  while ($by) {
    $pick = null;
    foreach ($by as $k => $list) if ($k !== $last && ($pick === null || count($list) > count($by[$pick]))) $pick = $k;
    if ($pick === null) $pick = (string)array_key_first($by);
    $out[] = array_shift($by[$pick]); $last = $pick;
    if (!$by[$pick]) unset($by[$pick]);
  }
  return $out;
}

/** Ücretsiz posta sağlayıcıları: aynı alan adı = aynı dükkân DEĞİL. */
function vestra_mailbox_free_domain(string $d): bool {
  return (bool)preg_match('/^(gmail|googlemail|hotmail|outlook|live|msn|yahoo|ymail|icloud|me|mac|aol|gmx|web|t-online|orange|wanadoo|free|laposte|sfr|libero|virgilio|alice|tiscali|hotmail\.\w+|yahoo\.\w+|outlook\.\w+|mail|yandex|protonmail|proton|seznam|wp|o2|onet|interia|telenet|skynet|ziggo|kpnmail|bluewin|freenet|arcor)\.[a-z.]+$/i', $d);
}

/**
 * Otomatik modda bulunanlar KESİNTİSİZ gitsin (11 Eki 2026, operatör: "günde 500'e kadar butik bulsun ve göndersin").
 * Arama turunun kendi gönderim isteği, bir önceki gönderim sürerken "sırada istek var" diye düşüyordu; cron her 10 dk'da
 * buna bakar: mod OTOMATİK, açık istek yok, bugünkü hak (ısınma dahil) var, gönderilmemiş yeni butik var ve son istek
 * 20 dk'dan eski ise ayarlı kampanyayla istek açar. Manuel modda hiçbir şey yapmaz. Döner: açılan isteğin mesajı | ''.
 */
function vestra_mailbox_auto_catchup(?int $now = null): string {
  $a = vestra_mailbox_auto();
  if (!$a['auto_send']) return '';
  $now = $now ?? time(); $last = 0;
  foreach (vestra_mailbox_runs() as $r) {
    if (vestra_mailbox_is_open($r)) return '';
    $last = max($last, (int)strtotime((string)($r['requested_at'] ?? '')));
  }
  if ($now - $last < 20 * 60) return '';
  $left = vestra_mailbox_left_today() - vestra_mailbox_deals_reserve($now);   // 17 Eki 2026: indirim payı ayrılır
  if ($left <= 0 || vestra_mailbox_bounce_stats()['stop']) return '';
  require_once __DIR__.'/finder.php';
  if (!vestra_finder_send_targets(1, $a['auto_pool'] === 'all' ? 'all' : 'web')) return '';
  [$ok, $msg] = vestra_mailbox_request('send', min($a['auto_limit'], $left), $a['auto_campaign'], '', array_keys(vestra_finder_campaigns()), $a['auto_pool']);
  return $ok ? $msg : '';
}

function vestra_mailbox_left_today(?array $leads = null): int {
  return max(0, vestra_mailbox_cap_today($leads) - vestra_mailbox_sent_today($leads));
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
  if (!in_array($mode, ['test', 'send', 'followup'], true)) return [false, 'Geçersiz kip.'];
  if ($mode === 'followup') $campaign = 'followup';   // kampanya lead başına seçilir (vestra_mailbox_followup_targets)
  elseif ($mode === 'send' && $campaign === 'deals') return [false, 'İndirim mektubu yalnız daha önce yazılmış müşterilere gider ("size daha önce yazmıştık") — ilk mektup olarak seçilemez.'];
  elseif (!preg_match('/^[A-Za-z0-9:_-]{1,40}$/', $campaign) || ($campaignKeys !== null && !in_array($campaign, $campaignKeys, true))) return [false, 'Kampanya bulunamadı.'];
  $testTo = strtolower(trim($testTo));
  if ($mode === 'test' && (!filter_var($testTo, FILTER_VALIDATE_EMAIL) || !preg_match('/^[a-z0-9@._+-]+$/', $testTo))) return [false, 'Geçerli bir test adresi yazın.'];
  $runs = vestra_mailbox_runs();
  foreach ($runs as $r) if (vestra_mailbox_is_open($r)) return [false, 'Sırada ya da gönderilmekte olan bir istek var — bitince yenisini verin.'];
  if ($mode !== 'test') {
    $left = vestra_mailbox_left_today();
    if ($left <= 0) return [false, 'Bugünkü tavan ('.vestra_mailbox_cap_today().(vestra_mailbox_warmup()['on'] && vestra_mailbox_cap_today() < vestra_mailbox_daily_cap() ? ', ısınma' : '').') doldu — kalanlar yarın.'];
    if (($bs = vestra_mailbox_bounce_stats())['stop']) return [false, sprintf('Gönderim durduruldu: son '.VESTRA_MAILBOX_BOUNCE_DAYS.' günde gönderilenlerin %%%d\'i geri döndü (%d/%d). Spam klasörüne düşmemek için önce listeyi temizleyin.', (int)round($bs['rate'] * 100), $bs['bounced'], $bs['sent'])];
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
    : '✓ '.($mode === 'followup' ? 'Takip (indirim) isteği' : 'Gönderim isteği').' sıraya alındı: '.$limit.' müşteri. En geç 10 dakika içinde başlar; e-postalar arasında '.VESTRA_MAILBOX_PAUSE_MIN.'–'.VESTRA_MAILBOX_PAUSE_MAX.' sn beklenir.'];
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
    foreach (['sent', 'bounced', 'failed', 'deferred'] as $k) $runs[$i][$k] = (int)($s[$k] ?? 0);
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
function vestra_mailbox_batch(int $limit, string $campKey, array $ids = [], string $pool = 'web', array $o = []): array {
  require_once __DIR__.'/finder.php';
  $camps = vestra_finder_campaigns();
  if (!isset($camps[$campKey])) return ['ok' => false, 'error' => 'campaign', 'items' => []];
  $followup = !empty($o['followup']);   // gece takip turu: kampanya lead başına seçilir (vestra_mailbox_followup_targets)
  if (!$followup && $campKey === 'deals') return ['ok' => false, 'error' => 'campaign', 'items' => []];   // indirim mektubu yalnız takip
  $builder = $camps[$campKey][2];
  $limit = max(1, min(VESTRA_MAILBOX_MAX_CAP, $limit));   // 10 Eki: UI 500'e kadar izin verir; 200'lük kırpma istekle çelişiyordu
  $left = vestra_mailbox_left_today();
  if ($left <= 0) return ['ok' => true, 'from' => 'VESTRA', 'campaign' => $campKey, 'items' => [], 'note' => 'gunluk tavan doldu ('.vestra_mailbox_cap_today().')'];
  if (($bs = vestra_mailbox_bounce_stats())['stop']) return ['ok' => true, 'from' => 'VESTRA', 'campaign' => $campKey, 'items' => [],
    'note' => sprintf('durduruldu: son 3 gunde geri donme %%%d (%d/%d) — liste temizlenmeli', (int)round($bs['rate'] * 100), $bs['bounced'], $bs['sent'])];
  $limit = min($limit, $left);
  $ids = array_values(array_filter(array_map('trim', $ids)));
  if ($followup) {
    $targets = vestra_mailbox_followup_targets($limit, null, $o['now'] ?? null);
  } elseif ($ids) {
    $want = array_flip($ids); $targets = []; $seen = [];
    foreach (vestra_leads() as $i => $l) {
      if (!isset($want[(string)($l['id'] ?? '')]) || count($targets) >= $limit) continue;
      $st = (string)($l['status'] ?? 'new');
      if ($st === 'unsubscribed' || $st === 'bounced' || !empty($l['unsubscribed'])) continue;
      if (trim((string)($l['last_contacted_at'] ?? '')) !== '' && (string)($l['contact_via'] ?? '') !== 'lemlist') continue;
      $e = vestra_email_clean((string)($l['email'] ?? ''));
      if (!filter_var($e, FILTER_VALIDATE_EMAIL) || isset($seen[$e])) continue;
      if (vestra_email_is_junk($e) || vestra_lead_looks_dead($l) || vestra_lead_off_target($l) || vestra_lead_send_paused($l)) continue;
      if (function_exists('vestra_lead_is_blocked') && vestra_lead_is_blocked($l)) continue;
      if (vestra_lead_source_guessed($l)) continue;   // 15 Eki 2026: tahmin listeleri (%49 geri donus) gonderime girmez
      $seen[$e] = true; $targets[$i] = $l;
    }
  } else {
    /* DKIM yokken DMARC'ı katı uygulayan alıcılar (Microsoft 365 / Outlook / Orange) atlanır ve yerlerine başkaları
       alınır — yoksa her parti aynı bekletilen adreslerle dolardı. DKIM ✓ olunca sıradaki ilk partide giderler. */
    $strictSkip = !($o['dkim_ok'] ?? (vestra_dkim_dns_status() === 'ok'));
    $targets = vestra_finder_send_targets($strictSkip ? min(5000, $limit * 3 + 50) : $limit, $pool === 'all' ? 'all' : 'web');
    if ($strictSkip) {
      $keep = [];
      foreach ($targets as $i => $l) {
        if (count($keep) >= $limit) break;
        if (vestra_mailbox_strict_receiver(vestra_email_clean((string)($l['email'] ?? '')), $o['mx'] ?? null)) continue;
        $keep[$i] = $l;
      }
      $targets = $keep;
    }
  }
  $items = []; $fromName = 'VESTRA'; $seenAddr = []; $seenShop = [];
  foreach ($targets as $l) {
    $email = vestra_email_clean((string)($l['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($seenAddr[$email])) continue;
    $seenAddr[$email] = true;
    $dom = substr((string)strrchr($email, '@'), 1);
    if (!vestra_mailbox_free_domain($dom)) { if (isset($seenShop[$dom])) continue; $seenShop[$dom] = true; }   // aynı dükkâna bir mektup
    $fuCamp = $followup ? (string)($l['_followup'] ?? '') : '';
    if ($followup && !isset($camps[$fuCamp])) continue;
    [$subject, $body, $opts, $from] = $followup ? ($camps[$fuCamp][2])($l) : $builder($l);
    if (trim((string)$subject) === '' || trim((string)$body) === '') continue;   // 17 Eki 2026: boş mektup (ör. indirim bu arada bitti) gitmez
    $body = vestra_mailbox_with_postal((string)$body);
    $subject = vestra_mailbox_subject_law((string)$subject, $l);
    if ($from !== '' && !$followup) $fromName = $from;
    $token = (string)($l['unsub_token'] ?? '');
    $items[] = [
      'leadId' => (string)($l['id'] ?? ''), 'email' => $email, 'company' => (string)($l['company'] ?? ''),
      'lang' => vestra_finder_lead_lang($l), 'subject' => $subject,
      'html' => vestra_html_email($body, '', (array)$opts), 'text' => vestra_mail_text_part($body, (array)$opts),
      'listUnsub' => $token !== '' ? 'https://vestrasales.com/lead-unsubscribe?token='.rawurlencode($token) : 'https://vestrasales.com/lead-unsubscribe',
      'followup' => $fuCamp, 'from' => $from !== '' ? $from : 'VESTRA',
    ];
  }
  return ['ok' => true, 'from' => $fromName, 'campaign' => $followup ? 'followup' : $campKey, 'items' => vestra_mailbox_interleave($items)];
}

/** G. Kore (14 Eki 2026, operatör "G. Kore'yi de ara"): Bilgi ve İletişim Ağı Yasası md. 50 — reklam e-postasının konusu
 *  "(광고)" ile başlamalı. Ülke alanı ya da .kr alan adı (e-posta / site) Kore ise önek eklenir. */
function vestra_mailbox_subject_law(string $subject, array $l): string {
  $c = strtolower(trim((string)($l['country'] ?? '')));
  $kr = in_array($c, ['south korea', 'korea', 'republic of korea', 'korea, republic of', 'kr', '대한민국', '한국'], true)
     || (bool)preg_match('/\.kr$/', strtolower((string)substr((string)strrchr((string)($l['email'] ?? ''), '@'), 1)))
     || (bool)preg_match('#^(?:https?://)?[^/]+\.kr(?:[/:]|$)#i', trim((string)($l['website'] ?? '')));
  return ($kr && !str_starts_with($subject, '(광고)')) ? '(광고) '.$subject : $subject;
}

/** Kampanya metninde şirketin posta adresi yoksa sona ekler (CAN-SPAM / AB ticari ileti kuralı). */
function vestra_mailbox_with_postal(string $body): string {
  return str_contains($body, '8 The Green') ? $body : rtrim($body)."\n".VESTRA_MAIL_POSTAL;
}

/* ── Gece takip turu (13 Eki 2026, operatör: "her gün gece 11.45'te ne kadar açık varsa — Brevo'nun 300'ünden ve
   kendi sunucumun 500'ünden arta kalan e-postalar — listedeki en az e-posta alan butiklere gönderilebilir").
   Gün boyunca yeni bulunanlar gider; gün sonunda tavandan ARTAN pay boşa gitmesin diye daha önce yazılmış ama EN AZ
   mektup almış butiklere, henüz almadıkları bir kampanya gider (takip mektubu). Kurallar:
   • yalnız 'contacted' (yanıt veren, kayıt olan, reddeden, geri dönen, çıkan almaz); satıcı müşterisi ve pazaryeri
     satıcısı almaz; aynı kara listeler (vestra_lead_is_blocked, ölü/hedef dışı) uygulanır;
   • son mektubundan (hangi yoldan olursa olsun: ilk mektup, Brevo Winter/wave3/teklif, önceki takip) en az
     VESTRA_MAILBOX_FOLLOWUP_MIN_DAYS gün geçmiş olmalı — Brevo'nun gece turu da aynı dükkâna aynı gece yazmasın;
   • sıra: en az mektup alan önce, eşitlikte en uzun süredir yazılmamış önce; kampanya VESTRA_MAILBOX_FOLLOWUP_ORDER'dan
     o dükkânın almadığı ilki; hepsini almışsa atlanır;
   • gönderim bugünkü tavana sayılır (contact_via=mailbox, last_contacted_at bugün) — tavan aşılmaz;
   • Avrupa saatiyle 23:45'ten sonra günde bir kez (cron_mailbox.php her 10 dk çağırır; mailbox.json followup_day). */
const VESTRA_MAILBOX_FOLLOWUP_MIN_DAYS = 5;
/* 17 Eki 2026 (operatör: "önceden gönderdiğimiz ilk müşterilere tekrar indirimli ürünleri gönder"): takip mektubu artık
   yalnız İNDİRİM mektubu (inc/campaign_deals.php). Eski sıra (polos → edit → lesgarage → standard) kalktı: polo
   promosyonu sabit eski fiyat yazıyordu, edit / lesgarage ilk mektubun kendisi. İndirimli ürün yoksa 'deals' kampanya
   listesinde olmaz ve takip alıcısı çıkmaz (boş mektup gitmez). */
const VESTRA_MAILBOX_FOLLOWUP_ORDER = ['deals'];   // ardından marka dalgaları (vestra_deals_waves, kampanya listesindeki sırayla)
/* 17 Eki 2026 (operatör: "noreply@g-star.com buna neden e-mail gönderiyorsun? distribütör ve büyük zincir butiklere ya da
   marketlere göndermemen gerektiğini söylemiştim"): takip havuzunun %95'i Tem/Ağu OSM listesindendi — bugünkü bulucu
   süzgecinden (sitede birden çok tanınan marka) hiç geçmemiş kayıtlar; ilk 93 alıcının ~20'si marka mağazası / zincir /
   moda dışıydı. Takip artık yalnız sitesinde bizim sattığımız EN AZ BİR premium marka görülmüş dükkâna (premium_brands;
   Brevo'nun ikinci mektubu da aynı şartla gidiyor, min_brands=1). */
const VESTRA_MAILBOX_FOLLOWUP_MIN_BRANDS = 1;
/* Bulucudan GEÇMEMİŞ kaynaklar (Tem/Ağu OSM, listeler) için bugünkü bulucu kuralı: EN AZ İKİ premium marka. Tek marka
   kanıtı eski taramada gözlükçüyü (Fielmann: Dsquared2 gözlük), kulüp mağazasını, markanın kendi dükkânını geçiriyordu. */
const VESTRA_MAILBOX_FOLLOWUP_MIN_BRANDS_UNSCREENED = 2;
/* Operatörün 13 Eki 2026'da Brevo ikinci mektubu için ELLE okuyup ayıkladığı adlar (chat-help brevo-daily.yml skip_names ile
   aynı liste; firma adında küçük harfli alt dize). Sunucunun indirim takibi bu listeyi uygulamıyordu: 17 Eki havuzunun ilk
   sıralarında Fielmann, Capel, Scalpers, Kicks, Hatstore, Joseph, Mackintosh… vardı. */
const VESTRA_CAMPAIGN_SKIP_NAMES = ['fielmann', 'bstn', 'cncpts', 'altermundi', 'capel', 'kaotiko', 'oktshirt', 'team store', 'targetcenter',
  'racing club', 'strasbourg', 'loretxiki', 'fantasía kids', 'lola jiménez', 'mandarina', 'black in black', 'piluka', 'glamcult', 'mackintosh',
  'joseph', 'lyle & scott', 'kicks', 'hatstore', 'kids brand store', 'limoneti', 'lapin house', 'klamöttchen', 'petit ilu', 'w&g', 'shi cashmere',
  'kindermode', 'kids', 'scalpers', 'pedro del hierro', 'claudia sträter', 'soccerfanshop', 'lesage bridal', 'ashley stewart', 'bealls',
  'tuckernuck', '260 sample sale', 'stofshop', 'atlas stoked', 'milk', 'golf club'];
function vestra_campaign_name_skipped(array $l): bool {
  $n = mb_strtolower((string)($l['company'] ?? ''));
  if ($n === '') return false;
  foreach (VESTRA_CAMPAIGN_SKIP_NAMES as $k) if (str_contains($n, $k)) return true;
  return false;
}
/* 17 Eki 2026 (operatör, G-Star olayından sonra havuz denetimi: "ikinci el / vintage ve gelinlikçileri de çıkar"): indirim
   takibi toptan alıcı olmayan ikinci el / vintage / konsinye ve gelinlik dükkânlarına gitmez (ad, site, e-posta alan adı). */
function vestra_mailbox_deals_excluded(array $l): bool {
  $em = strtolower((string)($l['email'] ?? '')); $ed = ($p = strrpos($em, '@')) !== false ? substr($em, $p + 1) : '';
  $hay = mb_strtolower((string)($l['company'] ?? '').' '.(string)($l['website'] ?? '').' '.$ed);
  /* 18 Eki 2026 denetimi (191 indirim mektubunda Relove, Designer Exchange, Once More Luxury, bir kuyumcu ve bir at
     malzemecisi vardı; 13'ünün sitesi bir şube sayfasıydı): ikinci elin başka adları, kuyumcu / binicilik, şube sayfası. */
  if (function_exists('vestra_lead_is_branch_page') && vestra_lead_is_branch_page((string)($l['website'] ?? ''))) return true;
  return (bool)preg_match('/second ?hand|secondhand|2nd ?hand|seconde ?main|tweede ?hands|segunda ?mano|second ?mano|gebraucht|usato|resale|re-?sale|vintage|consign|d[ée]p[ôo]t[- ]vente|brocante|thrift|re-?love|pre-?owned|pre-?loved|designer ?exchange|\bexchange\b|once ?more|rewear|kringloop|bridal|bride|braut|mari[ée]e|mariage|sposa|novia|bruid|wedding|hochzeit|br[uú]dkjole|brudklänning|juwel|jewel|gioiell|joaill|joyer|saddler|equestrian|reitsport/u', $hay);
}

/** Takip (indirim) için marka kanıtı yeterli mi? Bulucunun kendi kayıtları ('web-search') 1, eski kaynaklar 2 marka. */
function vestra_mailbox_brand_evidence_ok(array $l): bool {
  $n = count(array_filter((array)($l['premium_brands'] ?? [])));
  return $n >= (strtolower((string)($l['source'] ?? '')) === 'web-search' ? VESTRA_MAILBOX_FOLLOWUP_MIN_BRANDS : VESTRA_MAILBOX_FOLLOWUP_MIN_BRANDS_UNSCREENED);
}
const VESTRA_MAILBOX_FOLLOWUP_AT = '23:45';
const VESTRA_MAILBOX_FOLLOWUP_TZ = 'Europe/Berlin';

/** Bir dükkâna bugüne dek giden mektuplar: ['count', 'last' (unix), 'camps' => [kampanya anahtarları]].
 *  İlk mektup (last_contacted_at): 10 Eki'den sonra sunucudan gidenler 'edit' (otomatik kampanya), öncekiler ve
 *  Brevo'dan gidenler 'lesgarage' sayılır. Brevo damgaları (last_newcollection_at, last_wave3_at, offer_*_at) ve
 *  takip kayıtları (followups[]) da sayılır. */
function vestra_lead_letters(array $l): array {
  $camps = []; $last = 0; $n = 0;
  $ts = static fn($v): int => (int)(strtotime(trim((string)$v)) ?: 0);
  $first = $ts($l['first_contacted_at'] ?? ($l['last_contacted_at'] ?? ''));
  if ($first > 0) {
    $n++; $last = max($last, $first);
    $camps[] = ((string)($l['contact_via'] ?? '') === 'mailbox' && $first >= strtotime('2026-10-10')) ? 'edit' : 'lesgarage';
  }
  foreach (['last_newcollection_at' => 'brevo:winter', 'last_wave3_at' => 'brevo:wave3'] as $k => $c)
    if (($t = $ts($l[$k] ?? '')) > 0) { $n++; $last = max($last, $t); $camps[] = $c; }
  foreach ($l as $k => $v)
    if (is_string($k) && preg_match('/^offer_[a-z0-9]{3,20}_at$/', $k) && ($t = $ts($v)) > 0) { $n++; $last = max($last, $t); $camps[] = 'brevo:'.$k; }
  foreach ((array)($l['followups'] ?? []) as $f) {
    if (!is_array($f)) continue;
    $n++; $last = max($last, $ts($f['at'] ?? '')); $camps[] = (string)($f['campaign'] ?? '');
  }
  $last = max($last, $ts($l['last_contacted_at'] ?? ''));
  return ['count' => $n, 'last' => $last, 'camps' => $camps];
}

/* ── Günlük indirim partisi (17 Eki 2026, operatör: "her gün kendi serverda 500 e-mail ve yeni müşteriler bulunsun; önce
   önceden gönderdiğimiz ilk müşterilere tekrar indirimli ürünleri gönder 300 e-mail … hak kalırsa 23.45'te tamamlanmalı").
   Sunucunun günlük 500'ü şöyle bölünür:
   • Avrupa saatiyle 08:30'dan itibaren ÖNCE en çok VESTRA_MAILBOX_DEALS_DAILY (300) indirim mektubu eski müşterilere —
     takip makinesiyle (vestra_mailbox_followup_targets: yalnız 'contacted', 5 gün dinlenme, kara listeler, tahmin
     listeleri / durdurulan ülkeler / hedef dışı yok, en az mektup alan önce) ve gönderimden hemen önce yeniden kontrolle;
   • kalan pay (≥ 200) yeni bulunan butiklere; 23:45'e kadar yeni butik gönderimi bugünkü indirim payına DOKUNMAZ
     (vestra_mailbox_deals_reserve) — bulunanlar sırada bekler;
   • 23:45'ten sonra (sunucu günü bitene dek) bugünkü tavandan ne kaldıysa: önce bekleyen yeni butikler, sonra gece turu
     (vestra_mailbox_nightly) onu da indirim mektubuyla doldurur.
   Gün = sunucu günü (tavanın günü, vestra_mailbox_sent_today). mailbox.json: deals=false kapatır; otomatik gönderim
   durdurulmuşsa (auto_send=false, mailbox-pause.yml) indirim partisi de durur. Bir günde en çok VESTRA_MAILBOX_DEALS_TRIES
   istek (ertelenen / bu arada yazılan kayıtlar yüzünden eksik kalırsa yeniden dener, sonsuza dek değil). */
const VESTRA_MAILBOX_DEALS_DAILY = 300;
/* GÜNLÜK DOLDURMA (24 Eki 2026, operatör: "email kampanyalarına her gün otomatik gitmeli, kotayı doldur"): sabah pay 300
   (yeni butiklere yer kalsın); Avrupa saatiyle VESTRA_MAILBOX_DEALS_TOPUP_AT'ten sonra pay günün sunucu tavanına çıkar --
   yeni butiklerin kullanmadığı hak aynı gün indirim / marka dalgası mektuplarıyla dolar (vestra_mailbox_deals_quota_at). */
const VESTRA_MAILBOX_DEALS_TOPUP_AT = '16:00';
const VESTRA_MAILBOX_DEALS_AT = '08:30';
const VESTRA_MAILBOX_DEALS_TRIES = 3;

/** Sunucu gününün (tavan günü) Avrupa saatiyle $hm anı (unix). */
function vestra_mailbox_at(string $hm, ?int $now = null): int {
  $now = $now ?? time();
  return (new DateTimeImmutable(date('Y-m-d', $now).' '.$hm, new DateTimeZone(VESTRA_MAILBOX_FOLLOWUP_TZ)))->getTimestamp();
}

/** Bugün (sunucu günü) giden takip mektubu sayısı; $camp verilirse yalnız o kampanya. */
function vestra_mailbox_followups_today(string $camp = '', ?array $leads = null, ?int $now = null): int {
  $day = date('Y-m-d', $now ?? time()); $n = 0;
  foreach ($leads ?? vestra_leads() as $l)
    foreach ((array)($l['followups'] ?? []) as $f)
      if (is_array($f) && ($camp === '' || (string)($f['campaign'] ?? '') === $camp) && date('Y-m-d', (int)strtotime((string)($f['at'] ?? ''))) === $day) $n++;
  return $n;
}

/** Bugünkü indirim payı: tam tavanda (500) 300; tavan düşükse (ısınma / panel) aynı oranda (%60). */
function vestra_mailbox_deals_quota(?array $leads = null): int {
  /* TEK GÜNLÜK PAY (22 Eki 2026, operatör: "kendi server'dan 500 tane gönderelim"): mailbox.json'da deals_quota +
     deals_quota_day (sunucu günü) varsa o gün pay odur -- yine günün sunucu tavanını aşamaz. Ertesi gün kendiliğinden
     varsayılana (tam tavanda 300) döner; kalıcı bir ayar değil. */
  $c = vestra_mailbox_cfg();
  if ((string)($c['deals_quota_day'] ?? '') === date('Y-m-d') && (int)($c['deals_quota'] ?? 0) > 0)
    return min((int)$c['deals_quota'], vestra_mailbox_cap_today($leads));
  return min(VESTRA_MAILBOX_DEALS_DAILY, intdiv(vestra_mailbox_cap_today($leads) * 3, 5));   // tavanın %60'ı, en çok 300 (25 Eki: üst sınır 600 olunca oran sabit kaldı)
}

/** Saate göre pay: doldurma saatinden sonra günün tavanı (operatörün o güne özel payı daha büyükse o). */
function vestra_mailbox_deals_quota_at(?int $now = null): int {
  $now = $now ?? time(); $q = vestra_mailbox_deals_quota();
  return $now >= vestra_mailbox_at(VESTRA_MAILBOX_DEALS_TOPUP_AT, $now) ? max($q, vestra_mailbox_cap_today()) : $q;
}

function vestra_mailbox_deals_on(): bool {
  $c = vestra_mailbox_cfg();
  return vestra_mailbox_auto()['auto_send'] && (!array_key_exists('deals', $c) || !empty($c['deals']));
}

/** Bugünkü indirim payından henüz gitmemiş kısım — yeni butik gönderimi bunu bırakır. 23:45'ten sonra, indirim partisi
 *  kapalıyken, o gün aday kalmadıysa ya da deneme hakkı bittiyse 0. */
function vestra_mailbox_deals_reserve(?int $now = null): int {
  $now = $now ?? time();
  if (!vestra_mailbox_deals_on() || $now >= vestra_mailbox_at(VESTRA_MAILBOX_FOLLOWUP_AT, $now)) return 0;
  $cfg = vestra_mailbox_cfg(); $day = date('Y-m-d', $now);
  if ((string)($cfg['deals_empty'] ?? '') === $day) return 0;
  if ((string)($cfg['deals_day'] ?? '') === $day && (int)($cfg['deals_tries'] ?? 0) >= VESTRA_MAILBOX_DEALS_TRIES) return 0;
  return max(0, vestra_mailbox_deals_quota() - vestra_mailbox_followups_today('', null, $now));   // yalnız sabah payı ayrılır; doldurma yeni butikleri bekletmez
}

/** Bugünün Brevo olay eşitlemesi (inc/ops_tick.php, 08:00) yapıldı mı? Brevo anahtarı yoksa beklenecek bir şey yok.
 *  İndirim partisi eşitlemeyi bekler (Brevo'nun ölü bildiği adrese sunucu yazmasın); Brevo gün boyu cevap vermezse
 *  12:00'den sonra yine çıkar (günün payı kaybolmasın). */
function vestra_mailbox_brevo_synced(int $now, ?bool $hasKey = null): bool {
  if (!($hasKey ?? (function_exists('vestra_cfg') && (string)vestra_cfg('mail_api_key', '') !== ''))) return true;
  $dt = (new DateTimeImmutable('@'.$now))->setTimezone(new DateTimeZone(VESTRA_MAILBOX_FOLLOWUP_TZ));
  $st = vestra_read_json('ops_tick.json');
  return (string)($st['brevo_sync_am'] ?? '') === $dt->format('Y-m-d') || $dt->format('H:i') >= '12:00';
}

/** Sabah indirim partisi: 08:30–23:45 Avrupa arası, açık istek yokken, bugünkü indirim payı dolmadıysa takip isteği açar
 *  (en çok pay − bugün giden; pay tam tavanda 300). Döner: açılan isteğin mesajı | ''. */
function vestra_mailbox_deals_daily(?int $now = null): string {
  $now = $now ?? time();
  if (!vestra_mailbox_deals_on()) return '';
  if ($now < vestra_mailbox_at(VESTRA_MAILBOX_DEALS_AT, $now) || $now >= vestra_mailbox_at(VESTRA_MAILBOX_FOLLOWUP_AT, $now)) return '';
  if (!vestra_mailbox_brevo_synced($now)) return '';   // 17 Eki 2026: önce Brevo geri dönüşleri listeye işlensin
  $cfg = vestra_mailbox_cfg(); $day = date('Y-m-d', $now);
  if ((string)($cfg['deals_empty'] ?? '') === $day) return '';
  $tries = (string)($cfg['deals_day'] ?? '') === $day ? (int)($cfg['deals_tries'] ?? 0) : 0;
  if ($tries >= VESTRA_MAILBOX_DEALS_TRIES) return '';
  foreach (vestra_mailbox_runs() as $r) if (vestra_mailbox_is_open($r)) return '';
  $want = min(vestra_mailbox_deals_quota_at($now) - vestra_mailbox_followups_today('', null, $now), vestra_mailbox_left_today());
  if ($want <= 0 || vestra_mailbox_bounce_stats()['stop']) return '';
  $save = static function (array $set): void {
    $c = vestra_mailbox_cfg(); foreach ($set as $k => $v) $c[$k] = $v;
    $f = vestra_mailbox_file('mailbox.json');
    file_put_contents($f, json_encode($c, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), LOCK_EX); @chmod($f, 0600);
  };
  if (!vestra_mailbox_followup_targets(1, null, $now)) { $save(['deals_empty' => $day]); return ''; }
  [$ok, $msg] = vestra_mailbox_request('followup', $want, 'followup', '');
  if (!$ok) return '';
  $save(['deals_day' => $day, 'deals_tries' => $tries + 1, 'deals_note' => mb_substr($msg, 0, 120)]);
  return $msg;
}

/** Dükkânın henüz almadığı ilk takip kampanyası; hepsini almışsa ''. */
function vestra_mailbox_followup_next(array $had, ?array $camps = null): string {
  $order = VESTRA_MAILBOX_FOLLOWUP_ORDER;
  if ($camps !== null && function_exists('vestra_deals_is_wave_key')) foreach (array_keys($camps) as $k) if (vestra_deals_is_wave_key((string)$k)) $order[] = (string)$k;   // 24 Eki 2026: marka dalgaları
  foreach ($order as $k) if (!in_array($k, $had, true) && ($camps === null || isset($camps[$k]))) return $k;
  return '';
}

/** Dükkân alan adı (17 Eki 2026 incelemesi): aynı dükkânın iki adresi (info@ + sales@) ayrı dükkân sayılmasın.
 *  Serbest posta sağlayıcıları (gmail, wp.pl, gmx…) dükkân DEĞİL — orada '' döner, birleştirme adrese göre kalır. */
function vestra_mailbox_shop_domain(string $email): string {
  $d = strtolower(substr((string)strrchr($email, '@'), 1));
  if ($d === '' || vestra_mailbox_free_domain($d) || (function_exists('vestra_email_is_shared_provider') && vestra_email_is_shared_provider($d))) return '';
  return $d;
}

/** Kayıtlı hesaplar: ['mail' => [adres => tür], 'sellerDom' => [alan adı => true]]. Kayıtlı alıcıya "ücretsiz kayıt olun",
 *  satıcıya "başka satıcıların indirimleri" yazılmaz (operatör, 10 Eyl 2026: "satıcılara gönderme"). Dosya değişince tazelenir. */
function vestra_mailbox_account_index(): array {
  static $memo = null, $key = '';
  $f = defined('VESTRA_ACCOUNTS') ? VESTRA_ACCOUNTS : vestra_data_dir().'/accounts.json';
  clearstatcache(true, $f);
  $k = $f.'|'.(int)@filemtime($f).'|'.(int)@filesize($f);
  if ($memo !== null && $key === $k) return $memo;
  $out = ['mail' => [], 'sellerDom' => []];
  foreach ((array)(json_decode((string)@file_get_contents($f), true) ?: []) as $a) {
    if (!is_array($a)) continue;
    $e = vestra_email_clean((string)($a['email'] ?? '')); if ($e === '') continue;
    $out['mail'][$e] = (string)($a['type'] ?? '');
    if (($a['type'] ?? '') === 'seller' && ($d = vestra_mailbox_shop_domain($e)) !== '') $out['sellerDom'][$d] = true;
  }
  $key = $k;
  return $memo = $out;
}

/** Bütün kayıtlar üzerinden ADRES ve DÜKKÂN düzeyinde mektup özeti (17 Eki 2026 incelemesi). Eskiden dinlenme adrese,
 *  "hangi kampanyayı aldı" kayda göreydi ve dükkân yalnız aynı parti içinde tekilleşiyordu: aynı dükkânın sales@'i bir sonraki
 *  denemede ikinci indirim mektubunu, aynı adresin ikinci kaydı 6 gün sonra aynı mektubu yeniden alıyordu; bir kaydı
 *  abonelikten çıkmış adres, kopya kaydı üzerinden yine yazılıyordu. Döner:
 *  ['addr' => [adres => ['last','camps']], 'dom' => [alan adı => ['last','camps']], 'stop' => [adres => true], 'stopDom' => [alan adı => true]]
 *  stop = herhangi bir kaydı çıkmış / geri dönmüş / yanıt vermiş / reddetmiş / müşteri olmuş adres; stopDom = dükkânın HERHANGİ
 *  bir adresi çıkmış / yanıt vermiş / reddetmiş / müşteri olmuş (geri dönen tek bir kutu dükkânı durdurmaz). */
function vestra_mailbox_letter_index(array $leads): array {
  $ix = ['addr' => [], 'dom' => [], 'stop' => [], 'stopDom' => []];
  foreach ($leads as $l) {
    $e = vestra_email_clean((string)($l['email'] ?? '')); if ($e === '') continue;
    $st = (string)($l['status'] ?? '');
    if (in_array($st, ['unsubscribed', 'bounced', 'replied', 'declined', 'converted'], true) || !empty($l['unsubscribed'])) $ix['stop'][$e] = true;
    if ((in_array($st, ['unsubscribed', 'replied', 'declined', 'converted'], true) || !empty($l['unsubscribed'])) && ($sd = vestra_mailbox_shop_domain($e)) !== '') $ix['stopDom'][$sd] = true;
    $lt = vestra_lead_letters($l);
    $ix['addr'][$e] = ['last' => max($ix['addr'][$e]['last'] ?? 0, $lt['last']), 'camps' => array_merge($ix['addr'][$e]['camps'] ?? [], $lt['camps'])];
    if (($d = vestra_mailbox_shop_domain($e)) !== '')
      $ix['dom'][$d] = ['last' => max($ix['dom'][$d]['last'] ?? 0, $lt['last']), 'camps' => array_merge($ix['dom'][$d]['camps'] ?? [], $lt['camps'])];
  }
  return $ix;
}

/** Adres ya da dükkânı bir daha yazılmamalı mı (çıkmış / yanıt vermiş / reddetmiş / müşteri; adreste geri dönmüş de)? */
function vestra_mailbox_addr_stopped(string $email, array $ix): bool {
  $e = vestra_email_clean($email); $d = vestra_mailbox_shop_domain($e);
  return isset($ix['stop'][$e]) || ($d !== '' && isset($ix['stopDom'][$d]));
}

/** Bir adrese / dükkâna en son ne zaman yazıldı ve hangi kampanyalar gitti (adres + dükkân birleşik). */
function vestra_mailbox_letters_for(string $email, array $ix): array {
  $e = vestra_email_clean($email); $d = vestra_mailbox_shop_domain($e);
  $last = (int)($ix['addr'][$e]['last'] ?? 0); $camps = (array)($ix['addr'][$e]['camps'] ?? []);
  if ($d !== '') { $last = max($last, (int)($ix['dom'][$d]['last'] ?? 0)); $camps = array_merge($camps, (array)($ix['dom'][$d]['camps'] ?? [])); }
  return ['last' => $last, 'camps' => array_values(array_unique($camps)), 'dom' => $d];
}

/** Takip alıcıları (Avrupa önce, sonra en az mektup alan), her lead'e '_followup' => kampanya anahtarı eklenmiş.
 *  Dükkân başına (alan adı; serbest posta sağlayıcısında adres başına) EN ÇOK BİR alıcı. */
function vestra_mailbox_followup_targets(int $limit, ?array $leads = null, ?int $now = null): array {
  require_once __DIR__.'/finder.php';
  if (!function_exists('vestra_country_in_europe')) require_once __DIR__.'/orders.php';
  $now = $now ?? time(); $leads = $leads ?? vestra_leads(); $camps = vestra_finder_campaigns();
  $cut = $now - VESTRA_MAILBOX_FOLLOWUP_MIN_DAYS * 86400;
  $ix = vestra_mailbox_letter_index($leads); $acc = vestra_mailbox_account_index();
  $cand = [];
  foreach ($leads as $i => $l) {
    if (trim((string)($l['last_contacted_at'] ?? '')) === '') continue;              // hiç yazılmamış: normal sırada gider
    if ((string)($l['status'] ?? '') !== 'contacted' || !empty($l['unsubscribed'])) continue;
    if ((string)($l['contact_via'] ?? '') === 'lemlist') continue;                   // 8 Eki lemlist dışa aktarımı: hiç gönderilmedi
    if ((string)($l['owner_uid'] ?? '') !== '') continue;
    if (in_array(strtolower((string)($l['source'] ?? '')), VESTRA_FINDER_NOT_BUYER_SOURCES, true)) continue;
    $e = vestra_email_clean((string)($l['email'] ?? ''));
    if (!filter_var($e, FILTER_VALIDATE_EMAIL) || vestra_email_is_junk($e) || vestra_mailbox_addr_stopped($e, $ix)) continue;
    if ((function_exists('vestra_lead_is_blocked') && vestra_lead_is_blocked($l)) || vestra_lead_looks_dead($l) || vestra_lead_off_target($l)) continue;
    if (vestra_lead_send_paused($l)) continue;   // 14 Eki 2026: Almanya şimdilik durduruldu
    if (vestra_lead_source_guessed($l)) continue;   // 15 Eki 2026: tahmin listelerine takip de yok
    if (!vestra_mailbox_brand_evidence_ok($l) || vestra_campaign_name_skipped($l) || vestra_mailbox_deals_excluded($l)) continue;   // 17 Eki 2026: marka kanıtı şart; ayıklanan adlar, ikinci el / gelinlik yok
    $lf = vestra_mailbox_letters_for($e, $ix);
    if (isset($acc['mail'][$e]) || ($lf['dom'] !== '' && isset($acc['sellerDom'][$lf['dom']]))) continue;   // kayıtlı alıcı / satıcı
    $lt = vestra_lead_letters($l);
    if ($lt['last'] <= 0 || $lf['last'] > $cut) continue;
    $next = vestra_mailbox_followup_next($lf['camps'], $camps);
    if ($next === '') continue;
    $cand[] = ['i' => $i, 'n' => $lt['count'], 'last' => $lt['last'], 'camp' => $next, 'email' => $e, 'dom' => $lf['dom'], 'eu' => vestra_country_in_europe((string)($l['country'] ?? '')) ? 0 : 1];
  }
  /* 17 Eki 2026 (operatör: "daha çok Avrupa'dan"): Avrupa'daki dükkânlar önce, sonra en az mektup alan, en uzun süredir yazılmamış. */
  usort($cand, static fn($a, $b) => [$a['eu'], $a['n'], $a['last'], $a['i']] <=> [$b['eu'], $b['n'], $b['last'], $b['i']]);
  $out = []; $seen = []; $seenDom = [];
  foreach ($cand as $c) {
    if (count($out) >= $limit) break;
    if (isset($seen[$c['email']]) || ($c['dom'] !== '' && isset($seenDom[$c['dom']]))) continue;
    $seen[$c['email']] = true; if ($c['dom'] !== '') $seenDom[$c['dom']] = true;
    $l = $leads[$c['i']]; $l['_followup'] = $c['camp']; $out[$c['i']] = $l;
  }
  return $out;
}

/** Her gönderimden hemen önce: kayıt hâlâ var, çıkmamış, hedef içinde ve ülkesi durdurulmamış mı? (TEST adresi hariç)
 *  17 Eki 2026: adres düzeyinde de — aynı adresin başka bir kaydı çıkmış / geri dönmüş / yanıt vermişse, ya da adres kayıtlı
 *  bir hesabınsa (satıcıda alan adı da) gönderilmez. */
function vestra_mailbox_still_sendable(string $leadId, string $email, ?int $now = null): bool {
  if ($leadId === '' || $leadId === 'TEST') return true;
  require_once __DIR__.'/finder.php';
  $leads = vestra_leads(); $hit = null; $e = vestra_email_clean($email); $d = vestra_mailbox_shop_domain($e);
  $cut = ($now ?? time()) - VESTRA_MAILBOX_FOLLOWUP_MIN_DAYS * 86400;
  foreach ($leads as $l) {
    if ((string)($l['id'] ?? '') === $leadId) { $hit = $l; continue; }
    $le = vestra_email_clean((string)($l['email'] ?? ''));
    $same = $e !== '' && $le === $e; $sib = !$same && $d !== '' && str_ends_with($le, '@'.$d);   // $d serbest sağlayıcı değil
    if (!$same && !$sib) continue;
    $st = (string)($l['status'] ?? '');
    if (in_array($st, ['unsubscribed', 'replied', 'declined', 'converted'], true) || !empty($l['unsubscribed']) || ($same && $st === 'bounced')) return false;
    /* aynı adresin / dükkânın BAŞKA kaydına son 5 günde yazıldıysa (sunucu ya da Brevo) bu mektup da bekler — ilk mektup dahil */
    if (vestra_lead_letters($l)['last'] > $cut) return false;
  }
  if ($hit !== null) { $hs = (string)($hit['status'] ?? ''); if ($hs === 'unsubscribed' || $hs === 'bounced' || !empty($hit['unsubscribed'])) return false; }
  if ($e !== '') {
    $acc = vestra_mailbox_account_index();
    if (isset($acc['mail'][$e]) || ($d !== '' && isset($acc['sellerDom'][$d]))) return false;
  }
  if ($hit === null) return true;
  if (vestra_lead_off_target($hit) || vestra_lead_send_paused($hit) || vestra_lead_source_guessed($hit)) return false;
  return !(function_exists('vestra_lead_is_blocked') && vestra_lead_is_blocked($hit));
}

/** Takip gönderiminden hemen önce: kayıt hâlâ takip alabilir mi? Durum + dinlenme ADRES ve DÜKKÂN genelinde (bu arada
 *  aynı dükkânın başka adresine — sunucudan ya da Brevo'dan — yazıldıysa atlanır). */
function vestra_mailbox_followup_still_ok(string $leadId, ?int $now = null): bool {
  $now = $now ?? time(); $leads = vestra_leads();
  foreach ($leads as $l) {
    if ((string)($l['id'] ?? '') !== $leadId) continue;
    if ((string)($l['status'] ?? '') !== 'contacted' || !empty($l['unsubscribed'])) return false;
    if (!vestra_mailbox_brand_evidence_ok($l) || vestra_campaign_name_skipped($l) || vestra_mailbox_deals_excluded($l)) return false;
    if ((function_exists('vestra_lead_is_blocked') && vestra_lead_is_blocked($l)) || vestra_lead_off_target($l) || vestra_email_is_junk((string)($l['email'] ?? ''))) return false;
    $ix = vestra_mailbox_letter_index($leads); $e = vestra_email_clean((string)($l['email'] ?? ''));
    if (vestra_mailbox_addr_stopped($e, $ix)) return false;
    return max(vestra_lead_letters($l)['last'], vestra_mailbox_letters_for($e, $ix)['last']) <= $now - VESTRA_MAILBOX_FOLLOWUP_MIN_DAYS * 86400;
  }
  return false;
}

/** Gece turu: Avrupa saatiyle 23:45'ten sonra, günde bir kez, bugünkü tavandan kalan kadar takip isteği açar.
 *  mailbox.json: followup=false kapatır; followup_day o günün işlendiğini tutar. Döner: açılan isteğin mesajı | ''. */
function vestra_mailbox_nightly(?int $now = null): string {
  $cfg = vestra_mailbox_cfg();
  if (array_key_exists('followup', $cfg) && !$cfg['followup']) return '';
  $now = $now ?? time();
  /* 17 Eki 2026: pencere Avrupa 23:45'ten SUNUCU GÜNÜNÜN sonuna dek (tavan sunucu gününe sayılır; sunucu UTC'de, yani
     yazın 23:45–02:00, kışın 23:45–01:00 Avrupa). Eskiden yalnız 23:45–24:00'tü: o çeyrekte süren bir gönderim varsa
     günün artan payı hiç doldurulmuyordu. */
  if ($now < vestra_mailbox_at(VESTRA_MAILBOX_FOLLOWUP_AT, $now)) return '';
  $day = date('Y-m-d', $now);
  if ((string)($cfg['followup_day'] ?? '') === $day) return '';
  foreach (vestra_mailbox_runs() as $r) if (vestra_mailbox_is_open($r)) return '';   // süren gönderim bitince (gün işaretlenmez)
  $mark = static function (string $note) use ($cfg, $day): void {
    $cfg['followup_day'] = $day; $cfg['followup_note'] = mb_substr($note, 0, 120);
    $f = vestra_mailbox_file('mailbox.json');
    file_put_contents($f, json_encode($cfg, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), LOCK_EX); @chmod($f, 0600);
  };
  $left = vestra_mailbox_left_today();
  if ($left <= 0) { $mark('tavan dolu'); return ''; }
  if (vestra_mailbox_bounce_stats()['stop']) { $mark('fren'); return ''; }
  if (!vestra_mailbox_followup_targets(1, null, $now)) { $mark('takip alicisi yok'); return ''; }
  [$ok, $msg] = vestra_mailbox_request('followup', $left, 'followup', '');
  if (!$ok) return '';
  $mark($msg);
  return $msg;
}

/** Test: örnek dükkânla tek mektup, kayda dokunmaz. */
function vestra_mailbox_sample(string $campKey, string $to): array {
  require_once __DIR__.'/finder.php';
  $camps = vestra_finder_campaigns(); $to = strtolower(trim($to));
  if (!isset($camps[$campKey]) || !filter_var($to, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'items' => []];
  $lead = ['id' => 'TEST', 'company' => 'Boutique Example', 'country' => 'France', 'email' => $to, 'contact_name' => '', 'unsub_token' => ''];
  [$subject, $body, $opts, $from] = ($camps[$campKey][2])($lead);
  $body = vestra_mailbox_with_postal((string)$body);
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
    if ($em !== '' && (string)($r['followup'] ?? '') === '') $byEmail[$em] = $r;   // takip yalnız kayda göre: aynı adresin ikinci kaydı sayılmasın
  }
  $c = ['sent' => 0, 'bounced' => 0, 'unsub' => 0];
  if (!$byId && !$byEmail) return $c;
  /* 17 Eki 2026: kilit altında, taze listeye (Brevo partisi / geri dönme taraması aynı anda yazıyor). */
  vestra_leads_update(static function (array &$leads) use ($byId, $byEmail, &$c) {
  foreach ($leads as &$l) {
    $id = (string)($l['id'] ?? ''); $em = vestra_email_clean((string)($l['email'] ?? ''));
    $r = $byId[$id] ?? ($em !== '' ? ($byEmail[$em] ?? null) : null);
    if (!$r) continue;
    $st = (string)($r['status'] ?? '');
    if ($st === 'sent') {
      $fu = (string)($r['followup'] ?? '');
      if ($fu === '' && trim((string)($l['last_contacted_at'] ?? '')) !== '' && (string)($l['contact_via'] ?? '') !== 'lemlist') continue; // iki kez damgalama
      if ($fu !== '') {   // takip mektubu: ilk mektubun tarihi korunur, takip kaydı eklenir
        if (trim((string)($l['first_contacted_at'] ?? '')) === '') $l['first_contacted_at'] = (string)($l['last_contacted_at'] ?? '');
        $fl = is_array($l['followups'] ?? null) ? $l['followups'] : [];
        $fl[] = ['at' => date('c'), 'campaign' => $fu, 'lang' => (string)($r['lang'] ?? '')];
        $l['followups'] = array_slice($fl, -12);
      }
      $l['last_contacted_at'] = date('c'); $l['contact_via'] = 'mailbox';
      if (($l['status'] ?? 'new') === 'new') $l['status'] = 'contacted';
      $l['last_campaign'] = 'mailbox/'.(string)($r['lang'] ?? '');
      if (($mid = (string)($r['messageId'] ?? '')) !== '') $l['last_message_id'] = $mid;
      $note = trim((string)($l['notes'] ?? ''));
      $l['notes'] = trim($note.($note !== '' ? ' · ' : '').($fu !== '' ? 'takip/'.$fu : 'mailbox('.(string)($r['lang'] ?? '').')').' '.date('Y-m-d'));
      $c['sent']++;
    } elseif ($st === 'bounced') {
      $l['status'] = 'bounced'; $l['bounce_reason'] = 'mailbox: '.substr((string)($r['reason'] ?? 'rejected'), 0, 120); $l['bounced_at'] = date('c'); $c['bounced']++;
    } elseif ($st === 'unsub') {
      $l['status'] = 'unsubscribed'; $c['unsub']++;
    }
  }
  unset($l);
  return ($c['sent'] || $c['bounced'] || $c['unsub']) ? true : false;
  });
  return $c;
}

/**
 * Alan adının e-posta alacak sunucusu var mı: 'ok' | 'dead' | 'unknown'.
 * dead = alan adı yok (NXDOMAIN), hiç MX/A yok ya da null MX (RFC 7505) → mektup GÖNDERİLMEZ (8 Eki: xoe-shop.de
 * "DNSNULL" ile geri döndü; 12'lik partide 7 alan adı böyleydi). unknown = sorgu başarısız/zaman aşımı → GÖNDERİLİR:
 * geçici bir DNS aksaklığı müşteriyi kalıcı "geri döndü" damgalamasın. $dig(type, domain): dig çıktısı | null.
 */
/**
 * DKIM yokken DMARC'ı katı uygulayan alıcı mı? (11 Eki 2026 — support@ kutusundaki geri dönmeler ölçüldü:
 * Microsoft 365 / Outlook ve Orange "550 5.7.509 … does not pass DMARC verification and has a DMARC policy of reject"
 * ile reddetti. GoDaddy'nin giden aktarıcısı bazı turlarda SPF'te olmayan bir IP'den çıkıyor (93.188.3.35 spf=fail,
 * 92.204.71.189 spf=pass) ve DKIM imzası yokken DMARC p=reject mektubu öldürüyor.) DKIM DNS'te yayımlanana dek bu
 * alıcılara GÖNDERİLMEZ — sırada bekler, DKIM ✓ olunca kendiliğinden gider. $mxLookup testte sahte.
 */
function vestra_mailbox_strict_receiver(string $email, ?callable $mxLookup = null): bool {
  static $cache = [];
  $dom = strtolower(substr((string)strrchr($email, '@'), 1));
  if ($dom === '') return false;
  if (preg_match('/^(hotmail|outlook|live|msn|windowslive|passport)\.[a-z.]+$|^(orange|wanadoo)\.fr$/', $dom)) return true;
  if ($mxLookup === null && isset($cache[$dom])) return $cache[$dom];
  $mxLookup = $mxLookup ?? static function (string $d): array {
    $r = @dns_get_record($d, DNS_MX); return is_array($r) ? array_map(fn($x) => strtolower((string)($x['target'] ?? '')), $r) : [];
  };
  $hit = false;
  foreach ($mxLookup($dom) as $h) if (preg_match('/(\.|^)(mail\.protection\.outlook\.com|olc\.protection\.outlook\.com|outlook\.com|orange\.fr|wanadoo\.fr)$/', rtrim($h, '.'))) { $hit = true; break; }
  return $cache[$dom] = $hit;
}

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

/* Mektuplar arası bekleme (sn). 10 Eki 2026 (operatör: "bir seferde 500 kişiye gidecek şekilde ayarla"):
   eski 25–55 sn ile 500 mektup ~5,5 saat sürüyordu. cPanel hesabın saatlik sınırı 500 (diag-mail-limits);
   8–11 sn (ort. 9,5) ≈ saatte 380 → 500 mektup ~80 dk'da biter, sınırın altında kalır; satıcıların sunucu
   yolu (seller_outbox, turda 40) aynı hesaptan gittiğinde bile 500/saat aşılmaz. */
const VESTRA_MAILBOX_PAUSE_MIN = 8;
const VESTRA_MAILBOX_PAUSE_MAX = 11;

/**
 * Bir isteği (panel / elle) sunucuda baştan sona çalıştırır: liste → MX kontrolü → gönder → HER mektuptan
 * hemen sonra damgala (iş yarıda kesilse de ikinci kez gitmez) → 8–11 sn ara. 3 ardışık hatada durur.
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
    $b = vestra_mailbox_sample((string)($req['campaign'] ?? 'edit'), (string)($req['test_to'] ?? ''));
    if (!$b['items']) { $sum['note'] = 'test adresi ya da kampanya gecersiz'; $sum['error'] = $sum['note']; return $sum; }
    [$ok, $mid, $why] = vestra_mailbox_send_local($b['items'][0], (string)$b['from'], $mail);
    $sum[$ok ? 'sent' : 'failed'] = 1; $sum['note'] = $ok ? 'test gonderildi' : 'test gonderilemedi: '.$why;
    $log(($ok ? '+ TEST ' : 'x TEST ').vestra_mailbox_mask((string)$b['items'][0]['email']).' '.$mid);
    return $sum;
  }
  $ids = is_array($req['ids'] ?? null) ? $req['ids'] : array_filter(explode(',', (string)($req['ids'] ?? '')));
  $bo = array_intersect_key($o, ['dkim_ok' => 1, 'mx' => 1, 'now' => 1]);
  if ($mode === 'followup') { $bo['followup'] = true; $ids = []; }
  $b = vestra_mailbox_batch((int)($req['limit'] ?? 25), $mode === 'followup' ? 'edit' : (string)($req['campaign'] ?? 'edit'), $ids, $mode === 'followup' ? 'all' : (string)($req['pool'] ?? 'web'), $bo);
  if (!$b['ok']) { $sum['note'] = 'kampanya bulunamadi'; $sum['error'] = $sum['note']; return $sum; }
  if (!$b['items']) { $sum['note'] = (string)($b['note'] ?? 'gonderilecek uygun musteri yok'); return $sum; }
  $n = count($b['items']); $failRow = 0; $sentAny = false; $needPause = false;
  $dkimOk = isset($o['dkim_ok']) ? (bool)$o['dkim_ok'] : vestra_dkim_dns_status() === 'ok';
  foreach ($b['items'] as $k => $it) {
    $dom = substr((string)strrchr((string)$it['email'], '@'), 1);
    if ($dns($dom) === 'dead') {
      vestra_mailbox_stamp([['leadId' => $it['leadId'], 'email' => $it['email'], 'status' => 'bounced', 'reason' => 'alan adinin e-posta sunucusu yok (gonderilmedi)']]);
      $sum['bounced']++; $log(sprintf('- [%d/%d] %s atlandi: alan adinin e-posta sunucusu yok', $k + 1, $n, vestra_mailbox_mask($it['email'])));
      continue;
    }
    if (!$dkimOk && vestra_mailbox_strict_receiver((string)$it['email'], $o['mx'] ?? null)) {
      $sum['deferred'] = ($sum['deferred'] ?? 0) + 1;
      $log(sprintf('~ [%d/%d] %s bekletildi: DKIM yok, alici DMARC reddediyor (Microsoft/Orange)', $k + 1, $n, vestra_mailbox_mask($it['email'])));
      continue;
    }
    /* 17 Eki 2026: mektuplar arası bekleme KONTROLLERDEN ÖNCE — eskiden kontrol, sonra 8–11 sn bekleme, sonra gönderimdi:
       o bekleme sırasında Brevo aynı dükkâna yazarsa görülmüyordu. Son gönderimden beri bir kez beklenir. */
    if ($needPause) { $sleep(random_int(VESTRA_MAILBOX_PAUSE_MIN, VESTRA_MAILBOX_PAUSE_MAX)); $needPause = false; }
    /* Takip mektubu: gönderimden HEMEN önce kayıt yeniden okunur — parti kurulduktan sonra (aynı gece) Brevo
       taraması ya da başka bir yol bu dükkâna yazdıysa ya da dükkân çıktıysa / geri döndüyse atlanır (14 Eki 2026). */
    /* Son kontrol (14 Eki 2026, RTL olayı): parti kurulduktan sonra hariç tutulan / durdurulan ülkedeki kayda gitmez. */
    if (!vestra_mailbox_still_sendable((string)$it['leadId'], (string)$it['email'])) {
      $sum['deferred'] = ($sum['deferred'] ?? 0) + 1;
      $log(sprintf('~ [%d/%d] %s atlandi: hariç tutuldu / ülke durduruldu', $k + 1, $n, vestra_mailbox_mask((string)$it['email'])));
      continue;
    }
    if (($it['followup'] ?? '') !== '' && !vestra_mailbox_followup_still_ok((string)$it['leadId'], $o['now'] ?? null)) {
      $sum['deferred'] = ($sum['deferred'] ?? 0) + 1;
      $log(sprintf('~ [%d/%d] %s atlandi: bu arada yazilmis / cikmis', $k + 1, $n, vestra_mailbox_mask((string)$it['email'])));
      continue;
    }
    [$ok, $mid, $why] = vestra_mailbox_send_local($it, (string)(($it['followup'] ?? '') !== '' ? ($it['from'] ?? $b['from']) : $b['from']), $mail);
    if ($ok) {
      vestra_mailbox_stamp([['leadId' => $it['leadId'], 'email' => $it['email'], 'status' => 'sent', 'lang' => $it['lang'], 'messageId' => $mid, 'followup' => (string)($it['followup'] ?? '')]]);
      if (!$sentAny) vestra_mailbox_warmup_mark();
      $sum['sent']++; $failRow = 0; $sentAny = true; $needPause = true;
      $log(sprintf('+ [%d/%d] %s [%s]%s', $k + 1, $n, vestra_mailbox_mask($it['email']), $it['lang'], ($it['followup'] ?? '') !== '' ? ' takip/'.$it['followup'] : ''));
    } else {
      $sum['failed']++; $failRow++; $needPause = true;
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
  $next = null; foreach ((defined('VESTRA_FINDER_SLOTS') ? VESTRA_FINDER_SLOTS : [[1, 20], [4, 20], [7, 20], [10, 20], [13, 20], [16, 20], [19, 20], [22, 20]]) as [$H, $M]) { $t = gmmktime($H, $M, 0, (int)gmdate('n'), (int)gmdate('j'), (int)gmdate('Y')); if ($t <= $now) $t += 86400; $next = $next === null ? $t : min($next, $t); }
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
               : 'GÖNDERİLİYOR — '.min($done, $total).' / '.$total.' gitti'.($lastAt ? ' · son e-posta '.$hm($lastAt) : '').' · e-postalar arası '.VESTRA_MAILBOX_PAUSE_MIN.'-'.VESTRA_MAILBOX_PAUSE_MAX.' sn'];
  } elseif ($open) {
    $send = ['state' => 'queued', 'text' => 'Gönderim SIRADA — '.(($open['mode'] ?? '') === 'test' ? 'test e-postası' : (int)$open['limit'].' müşteri').', '.$hm($open['requested_at'] ?? '').'\'de istendi, 10 dk içinde başlar.'];
  } else {
    $lastR = null; foreach (array_reverse(vestra_mailbox_runs()) as $r) if (($r['mode'] ?? '') === 'send' && ($r['status'] ?? '') === 'done') { $lastR = $r; break; }
    $send = ['state' => 'idle', 'text' => 'Şu an gönderim yok · bugün '.$today.' e-posta gitti, kalan hak '.vestra_mailbox_left_today()
      .($lastR ? ' · son gönderim '.date('d.m H:i', (int)strtotime((string)($lastR['finished_at'] ?? $lastR['requested_at'] ?? ''))).' → '.(int)($lastR['sent'] ?? 0).' gitti' : '')];
  }
  return ['search' => $search, 'send' => $send, 'at' => date('H:i:s')];
}
