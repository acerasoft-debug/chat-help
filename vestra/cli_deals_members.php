<?php
/**
 * İNDİRİM KAMPANYASI → KAYITLI ALICILAR (22 Eki 2026, operatör: "sunucudan yaptığımız indirim kampanyalarını gönder, önce
 * kayıtlılara, İtalyan hariç — büyük müşteri; sonra diğerlerine; sonra rapor ver").
 *
 * vestra_campaign_deals(..., member: true) — kayıtlı müşteri girişi, hesaba giriş düğmesi, cevapla çıkış künyesi — sunucunun
 * posta servisinden (vestra_mailbox_send_local, support@, DKIM) tek tek, 8–11 sn arayla. Seçim: alıcı hesabı (satıcı DEĞİL),
 * askıda / silinmiş / reddedilmiş değil, teslim edilebilir adres, promosyon istemeyen (no_promo) değil, hariç tutulanlar
 * (exclude: firma/ad/e-posta parçası, virgülle) ve operatörün kendi adresi değil, bu kampanyayı daha önce almamış (KEY).
 * Her gönderimden ÖNCE sunucunun günlük kalan hakkına bakar (vestra_mailbox_left_today; bu mektuplar member_outbox.json ile
 * o sayaca girer) -- süren indirim partisiyle birlikte 500'ü aşmaz. Kütüğe adres MASKELİ.
 * Kullanım: php cli_deals_members.php dry|send <limit> <exclude>
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
chdir(__DIR__);
require __DIR__.'/inc/products.php'; require_once __DIR__.'/inc/auth.php'; require_once __DIR__.'/inc/notify.php';
require_once __DIR__.'/inc/mailbox.php'; require_once __DIR__.'/inc/campaign_deals.php'; require_once __DIR__.'/inc/finder.php';
const KEY = 'deals-members-20261022';
$mode = $argv[1] ?? 'dry'; $limit = max(1, (int)($argv[2] ?? 500));
$excl = array_values(array_filter(array_map(fn($x) => mb_strtolower(trim($x)), explode(',', (string)($argv[3] ?? '')))));
$mask = fn(string $e) => preg_replace('/^(.).*(@.*)$/', '$1***$2', $e);
$of = vestra_mailbox_file('member_outbox.json');
$log = is_readable($of) ? (array)json_decode((string)file_get_contents($of), true) : [];
$had = []; foreach ($log as $it) if (is_array($it) && ($it['key'] ?? '') === KEY && ($it['status'] ?? '') === 'sent') $had[strtolower((string)$it['email'])] = true;
$ops = strtolower((string)vestra_cfg('ops_email', 'acerasoft@gmail.com'));
$sel = []; $skip = []; $seen = [];
foreach (auth_accounts() as $a) {
  $e = strtolower(trim((string)($a['email'] ?? ''))); $why = '';
  if (($a['type'] ?? 'buyer') !== 'buyer') $why = 'satici/diger';
  elseif (in_array((string)($a['status'] ?? 'active'), ['suspended', 'deleted', 'rejected', 'banned'], true)) $why = 'askida/kapali';
  elseif ($e === '' || !vestra_email_deliverable($e)) $why = 'adres yok/gecersiz';
  elseif (!empty($a['no_promo'])) $why = 'promosyon istemiyor';
  elseif ($e === $ops || str_ends_with($e, '@vestrasales.com')) $why = 'operator/ic adres';
  elseif (isset($seen[$e])) $why = 'yinelenen adres';
  elseif (isset($had[$e])) $why = 'bu kampanyayi zaten aldi';
  else { $hay = mb_strtolower(implode(' ', [(string)($a['company'] ?? ''), (string)($a['name'] ?? ''), $e]));
         foreach ($excl as $x) if ($x !== '' && str_contains($hay, $x)) { $why = 'haric tutuldu ('.$x.')'; break; } }
  if ($why !== '') { $skip[$why] = ($skip[$why] ?? 0) + 1; continue; }
  $seen[$e] = true; $sel[] = $a;
}
$pick = vestra_deals_pick();
echo "kampanya: ".KEY." · urun: ".count($pick['items'])." · uygun kayitli alici: ".count($sel)." · limit: {$limit}\n";
foreach ($skip as $w => $n) echo "  elendi: {$w} = {$n}\n";
$byLang = []; foreach ($sel as $a) { $lg = vestra_finder_lead_lang(['country' => (string)($a['country'] ?? ''), 'company' => (string)($a['company'] ?? '')]); $byLang[$lg] = ($byLang[$lg] ?? 0) + 1; }
echo "  dil: ".json_encode($byLang)."\n";
echo "  sunucu bugun kalan hak: ".vestra_mailbox_left_today()."\n";
if ($mode !== 'send') { echo "KURU KOSU -- hicbir sey gonderilmedi\n"; exit(0); }
$sent = 0; $fail = 0;
foreach ($sel as $a) {
  if ($sent >= $limit) break;
  if (vestra_mailbox_left_today() <= 0) { echo "DUR: sunucunun bugunku hakki doldu\n"; break; }
  $e = strtolower(trim((string)$a['email']));
  $lg = vestra_finder_lead_lang(['country' => (string)($a['country'] ?? ''), 'company' => (string)($a['company'] ?? '')]);
  [$subj, $body, $opts] = vestra_campaign_deals(trim((string)($a['company'] ?? '')), $lg, $pick, true);
  if ($subj === '') { echo "DUR: indirimli urun yok\n"; break; }
  $it = ['email' => $e, 'subject' => $subj, 'text' => vestra_mail_text_part($body, (array)$opts), 'html' => vestra_html_email($body, '', (array)$opts)];
  [$ok, $mid, $why] = vestra_mailbox_send_local($it, 'VESTRA');
  $log[] = ['key' => KEY, 'email' => $e, 'lang' => $lg, 'status' => $ok ? 'sent' : 'failed', 'at' => date('c'), 'note' => $why];
  file_put_contents($of, json_encode(array_values($log), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), LOCK_EX); @chmod($of, 0600);
  echo ($ok ? '  ok   ' : '  HATA ').$mask($e)." [{$lg}]".($ok ? '' : " ({$why})")."\n";
  $ok ? $sent++ : $fail++;
  sleep(random_int(8, 11));
}
echo "BITTI: gonderildi {$sent} · hata {$fail} · sunucu bugun kalan: ".vestra_mailbox_left_today()."\n";
