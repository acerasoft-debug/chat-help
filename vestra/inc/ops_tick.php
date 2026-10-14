<?php
/**
 * VESTRA — sunucunun kendi zamanlayıcısından GitHub iş akışlarını başlatma (cron_mailbox.php, 10 dk'da bir).
 *
 * 14 Eki 2026, operatör: "Brevo'da her zaman 100 e-posta hakkı kalsın, 23.45'te kontrol edilsin ve hak kaldıysa
 * devam gönderilsin … halen e-posta hataları geliyor". Ölçüm: GitHub'ın kendi zamanlaması Brevo koşularını atlıyor
 * (14 Eki 09:12 hiç çalışmadı, 13 Eki gece taraması 3 saat geç). Bulucuda olduğu gibi (vestra_finder_server_tick)
 * sunucu, kayıtlı GitHub anahtarıyla iş akışını KENDİSİ başlatır:
 *   - brevo-daily.yml: 09:12 ve 15:12 (Avrupa saati) 50'lik parti, 23:45 gece taraması (kalan hakla 50'lik partiler);
 *     her koşu Brevo hakkından en az 100'ü bırakır (brevo-daily.yml quota_reserve=100).
 *   - mailbox-send.yml bounces: saatte bir — support@ kutusundaki teslim hatası bildirimlerini toplar, adresi
 *     "geri döndü" işaretler (bir daha yazılmaz) ve bildirimi VESTRA-Bounces klasörüne taşır.
 * Her slot Avrupa gününde bir kez (data/ops_tick.json). Anahtar yoksa hiçbir şey yapmaz. GitHub'ın kendi zamanlaması
 * yedek olarak durur; brevo-daily.yml 100 dk içinde başka bir koşu varsa kendini atlar (çift gönderim olmaz).
 */
require_once __DIR__.'/finder.php';

const VESTRA_OPS_TZ = 'Europe/Berlin';
/* slot => [saat:dakika (Avrupa), iş akışı, girdiler, pencere dk] */
const VESTRA_OPS_SLOTS = [
  'brevo_am'    => ['09:12', 'brevo-daily.yml', ['count' => '50', 'dry_run' => 'false', 'sweep' => 'false'], 150],
  'brevo_pm'    => ['15:12', 'brevo-daily.yml', ['count' => '50', 'dry_run' => 'false', 'sweep' => 'false'], 150],
  'brevo_night' => ['23:45', 'brevo-daily.yml', ['count' => '50', 'dry_run' => 'false', 'sweep' => 'true'], 14],
];
const VESTRA_OPS_BOUNCE_EVERY = 3600;

function vestra_ops_state(): array { $d = vestra_read_json('ops_tick.json'); return is_array($d) ? $d : []; }
function vestra_ops_save(array $s): void { vestra_write_json('ops_tick.json', $s); }

/** İş akışını varsayılan daldan başlatır. [ok, açıklama]. $gh testte sahte (method, path, body) → [code, body]. */
function vestra_ops_dispatch(string $wf, array $inputs, ?callable $gh = null): array {
  $gh = $gh ?? 'vestra_finder_gh';
  $repo = vestra_finder_repo();
  [$c0, $info] = $gh('GET', '/repos/'.$repo, null);
  if ($c0 !== 200) return [false, 'GitHub HTTP '.$c0];
  $ref = (string)($info['default_branch'] ?? 'main');
  [$c] = $gh('POST', '/repos/'.$repo.'/actions/workflows/'.$wf.'/dispatches', ['ref' => $ref, 'inputs' => $inputs]);
  return ($c === 204 || $c === 200) ? [true, $wf.' başlatıldı'] : [false, $wf.' GitHub HTTP '.$c];
}

/** Zamanı gelen işleri başlatır. Döner: kayda yazılacak satırlar. */
function vestra_ops_tick(?int $now = null, ?callable $gh = null): array {
  if ($gh === null && vestra_finder_gh_token() === '') return [];
  $now = $now ?? time();
  $dt = (new DateTimeImmutable('@'.$now))->setTimezone(new DateTimeZone(VESTRA_OPS_TZ));
  $day = $dt->format('Y-m-d'); $st = vestra_ops_state(); $out = [];
  foreach (VESTRA_OPS_SLOTS as $key => [$hm, $wf, $inputs, $win]) {
    [$H, $M] = array_map('intval', explode(':', $hm));
    $slot = $dt->setTime($H, $M)->getTimestamp();
    if ($now < $slot || $now > $slot + $win * 60) continue;
    if (($st[$key] ?? '') === $day) continue;
    [$ok, $msg] = vestra_ops_dispatch($wf, $inputs, $gh);
    if ($ok) $st[$key] = $day;
    $out[] = $key.': '.$msg;
  }
  if ($now - (int)($st['bounces_at'] ?? 0) >= VESTRA_OPS_BOUNCE_EVERY) {
    [$ok, $msg] = vestra_ops_dispatch('mailbox-send.yml', ['mode' => 'bounces'], $gh);
    $st['bounces_at'] = $now;   // başarısızlıkta da: GitHub düşükse 10 dk'da bir denemeyelim
    $out[] = 'bounces: '.$msg;
  }
  vestra_ops_save($st);
  return $out;
}
