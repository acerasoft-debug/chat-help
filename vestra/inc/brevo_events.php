<?php
/**
 * Brevo olaylarını müşteri listesine işler (17 Eki 2026 incelemesi, operatör: "hataları en aza indir, hata e-maili gelmesin").
 *
 * Sunucu artık her gün eski müşterilere indirim mektubu yolluyor ve bu müşterilerin bir kısmına ilk/ikinci mektup Brevo'dan
 * gitmişti. Brevo'nun hard bounce / geçersiz / engelli / spam şikâyeti / abonelikten çıkma kayıtları leads.json'a
 * KENDİLİĞİNDEN hiç yazılmıyordu (purge-bounced.yml elle ve kuru koşu varsayılanlıydı; saatlik tarama yalnız support@
 * kutusuna gelen bildirimleri okuyor, Brevo'nunkiler oraya gelmez) — sunucu Brevo'nun ölü bildiği adrese yazıp
 * support@'a yeni bir hata bildirimi getiriyordu. Artık günde iki kez (inc/ops_tick.php: 08:00 indirim partisinden
 * önce, 20:30 gece turundan önce) son günlerin olayları çekilir; ilk koşu 90 gün geriye bakar:
 *   hardBounces / invalid / blocked → status=bounced (bir daha hiçbir yoldan yazılmaz)
 *   spam / unsubscribed             → status=unsubscribed (+ unsubscribed=true)
 * Zaten çıkmış kayda dokunulmaz; yazım kilit altında (vestra_leads_update). Brevo anahtarı yoksa hiçbir şey yapmaz.
 * Adres basılmaz (kütük herkese açık iş akışlarına da düşebilir) — yalnız sayılar.
 */
require_once __DIR__.'/notify.php';
require_once __DIR__.'/leads.php';

const VESTRA_BREVO_SYNC_EVENTS = ['hardBounces' => 'bounced', 'invalid' => 'bounced', 'blocked' => 'bounced', 'spam' => 'unsubscribed', 'unsubscribed' => 'unsubscribed'];
const VESTRA_BREVO_SYNC_PAGE = 2500;

/** Bir olay türünün adresleri: [adres => neden] ya da null (anahtar yok / API hatası). $http(url): [kod, gövde] (test). */
function vestra_brevo_events(string $event, int $days, ?callable $http = null): ?array {
  $key = (string)vestra_cfg('mail_api_key', '');
  if ($http === null && $key === '') return null;
  $http = $http ?? static function (string $url) use ($key): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_HTTPHEADER => ['api-key: '.$key, 'Accept: application/json']]);
    $raw = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return [$code, is_string($raw) ? $raw : ''];
  };
  $days = max(1, min(90, $days)); $out = []; $offset = 0;
  for ($page = 0; $page < 40; $page++) {
    [$code, $raw] = $http('https://api.brevo.com/v3/smtp/statistics/events?event='.rawurlencode($event).'&days='.$days.'&limit='.VESTRA_BREVO_SYNC_PAGE.'&offset='.$offset.'&sort=desc');
    if ($code < 200 || $code >= 300) return $page === 0 ? null : $out;
    $ev = json_decode((string)$raw, true);
    $ev = is_array($ev) && is_array($ev['events'] ?? null) ? $ev['events'] : [];
    foreach ($ev as $e) {
      $m = vestra_email_clean((string)($e['email'] ?? ''));
      if ($m !== '' && !isset($out[$m])) $out[$m] = mb_substr(trim((string)($e['reason'] ?? '')), 0, 90);
    }
    if (count($ev) < VESTRA_BREVO_SYNC_PAGE) break;
    $offset += VESTRA_BREVO_SYNC_PAGE;
  }
  return $out;
}

/** Son $days günün Brevo olaylarını leads.json'a işler. Döner: ['ok', 'events', 'bounced', 'unsub', 'note']. */
function vestra_brevo_bounce_sync(int $days, ?callable $http = null): array {
  $hits = [];
  foreach (VESTRA_BREVO_SYNC_EVENTS as $ev => $st) {
    $r = vestra_brevo_events($ev, $days, $http);
    if ($r === null) return ['ok' => false, 'events' => 0, 'bounced' => 0, 'unsub' => 0, 'note' => $ev.' okunamadi'];
    foreach ($r as $m => $why) if (!isset($hits[$m]) || $st === 'unsubscribed') $hits[$m] = [$st, $ev.($why !== '' ? ': '.$why : '')];
  }
  $c = ['ok' => true, 'events' => count($hits), 'bounced' => 0, 'unsub' => 0, 'note' => ''];
  if (!$hits) return $c;
  vestra_leads_update(static function (array &$L) use ($hits, &$c) {
    $changed = false;
    foreach ($L as &$l) {
      $m = vestra_email_clean((string)($l['email'] ?? ''));
      if ($m === '' || !isset($hits[$m])) continue;
      [$st, $why] = $hits[$m]; $cur = (string)($l['status'] ?? '');
      if ($cur === 'unsubscribed' || ($cur === 'bounced' && $st === 'bounced')) continue;
      if ($st === 'unsubscribed') { $l['status'] = 'unsubscribed'; $l['unsubscribed'] = true; $c['unsub']++; }
      else { $l['status'] = 'bounced'; $l['bounce_reason'] = 'brevo '.mb_substr($why, 0, 110); $l['bounced_at'] = date('c'); $c['bounced']++; }
      $note = trim((string)($l['notes'] ?? ''));
      $l['notes'] = trim($note.($note !== '' ? ' · ' : '').'brevo-'.($st === 'unsubscribed' ? 'cikis' : 'geri-donus').' '.date('Y-m-d'));
      $changed = true;
    }
    unset($l);
    return $changed;
  });
  return $c;
}
