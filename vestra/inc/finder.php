<?php
/**
 * VESTRA — "Web'den müşteri bul": web araması + GERÇEK e-posta ile çok markalı butik bulma.
 *
 * MOTOR NEREDE ÇALIŞIYOR
 * Bu paylaşımlı hosting uzun/paralel işi öldürüyor (SIGKILL, ~25 dk tavan; discover-city
 * ve daily-customers bunun için şehir başına süreç bölmek zorunda kalmıştı). Motor bu
 * yüzden GitHub Actions'ta çalışır: acerasoft-debug/chat-help · find-customers.yml
 * (scripts/find-boutiques.mjs). Panel yalnızca üç şey yapar:
 *   1) isteği workflow_dispatch ile başlatır (vestra_finder_start),
 *   2) durumunu GitHub API'den okur (vestra_finder_refresh),
 *   3) sonucu workflow'un SSH ile yazdığı data/finder_runs.json'dan gösterir.
 * Bulunan leadler data/leads.json'a workflow tarafından eklenir — owner_uid ile, yani
 * satıcının başlattığı arama satıcının listesine düşer.
 *
 * ANAHTARLAR
 * GitHub token ve Brave Search anahtarı data/email_settings.json'da (chmod 600, web'e
 * kapalı, git'e girmez) — Google anahtarı ile aynı yer. Depo HERKESE AÇIK: anahtar asla
 * koda, workflow girdisine ya da Actions loguna yazılmaz. Brave ve Google aramasını
 * runner değil SUNUCU yapar (scripts/server/vestra-search.php), anahtar sunucudan çıkmaz.
 *
 * NEDEN ARAMA MOTORU HTML'İ DEĞİL: 8 Eki 2026 test koşusunda Bing ve DuckDuckGo GitHub
 * sunucularını captcha ile engelledi (14 sorgunun 4'ü çalıştı, 0 lead). Resmi API şart.
 */

require_once __DIR__.'/notify.php';   // vestra_cfg

const VESTRA_FINDER_REPO     = 'acerasoft-debug/chat-help';
const VESTRA_FINDER_WORKFLOW = 'find-customers.yml';
const VESTRA_FINDER_LANGS    = ['de','it','fr','en','nl','es','pt','pl','el','cs','da','sv','nb','fi'];

function vestra_finder_repo(): string {
  $r = trim((string)vestra_cfg('finder_repo', ''));
  return preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $r) ? $r : VESTRA_FINDER_REPO;
}
function vestra_finder_gh_token(): string { return trim((string)vestra_cfg('gh_token', '')); }
function vestra_finder_brave_on(): bool   { return trim((string)vestra_cfg('brave_key', '')) !== ''; }
/** Google Places anahtarı (admin'in "Google ile ara" kartı) — arama vekili onu da kullanır. */
function vestra_finder_google_on(): bool {
  if (!function_exists('vestra_google_key') && is_readable(__DIR__.'/discover_google.php')) require_once __DIR__.'/discover_google.php';
  return function_exists('vestra_google_key') && vestra_google_key() !== '';
}
/** Başlatılabilir mi: HER ZAMAN. Hiçbir anahtar şart değil (operatör, 8 Eki: "sistemi hazır
 *  hale getir"): GitHub token yoksa istek sıraya girer ve find-customers-queue.yml 10 dk
 *  içinde GitHub'ın kendi yetkisiyle başlatır; Brave/Google yoksa aday kaynağı
 *  OpenStreetMap'tir. Anahtarlar yalnızca hızlandırır (token) ve sonucu artırır (Brave). */
function vestra_finder_ready(): bool { return true; }

/** Tek GitHub REST çağrısı. [http_code, decoded_body|null, curl_error] */
function vestra_finder_gh(string $method, string $path, ?array $body = null): array {
  $tok = vestra_finder_gh_token();
  if ($tok === '') return [0, null, 'no-token'];
  $h = ['Accept: application/vnd.github+json', 'Authorization: Bearer '.$tok,
        'X-GitHub-Api-Version: 2022-11-28', 'User-Agent: VestraSales-Finder'];
  $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_CUSTOMREQUEST => $method];
  if ($body !== null) { $h[] = 'Content-Type: application/json'; $o[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
  $o[CURLOPT_HTTPHEADER] = $h;
  $ch = curl_init('https://api.github.com'.$path);
  curl_setopt_array($ch, $o);
  $raw = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch);
  curl_close($ch);
  $d = (is_string($raw) && $raw !== '') ? json_decode($raw, true) : null;
  return [$code, is_array($d) ? $d : null, $err];
}

/* ── kayıtlar ─────────────────────────────────────────────────────────────── */

function vestra_finder_runs(?string $owner = null): array {
  $runs = vestra_read_json('finder_runs.json');
  if ($owner !== null) $runs = array_values(array_filter($runs, fn($r) => (string)($r['owner'] ?? '') === $owner));
  usort($runs, fn($a, $b) => strcmp((string)($b['requested_at'] ?? ''), (string)($a['requested_at'] ?? '')));
  return $runs;
}
function vestra_finder_save_runs(array $runs): void {
  usort($runs, fn($a, $b) => strcmp((string)($b['requested_at'] ?? ''), (string)($a['requested_at'] ?? '')));
  vestra_write_json('finder_runs.json', array_slice(array_values($runs), 0, 60));
}
function vestra_finder_is_active(array $r): bool {
  $st = (string)($r['status'] ?? '');
  if ($st !== 'requested' && $st !== 'running') return false;
  /* Çalışan: 95 dk (workflow'un tavanı 80 dk). Sırada bekleyen (henüz başlatılmamış):
     6 saat — GitHub'ın zamanlanmış işleri yoğun saatlerde gecikebiliyor. Bundan eskisi bir
     yerde takıldı demektir ve yeni aramayı sonsuza dek kilitlememeli. */
  $d = (string)($r['dispatched_at'] ?? '');
  if ($d === '') return (time() - (int)strtotime((string)($r['requested_at'] ?? ''))) < 6 * 3600;
  return (time() - (int)strtotime($d)) < 95 * 60;
}
/** Şu an çalışan (ya da kuyruktaki) arama — herkes için tek: workflow'un concurrency
 *  grubu üçüncü bir isteği sessizce iptal ediyor, iki kişi aynı anda başlatmasın. */
function vestra_finder_active(): ?array {
  foreach (vestra_finder_runs() as $r) if (vestra_finder_is_active($r)) return $r;
  return null;
}

/* ── girdi ────────────────────────────────────────────────────────────────── */

/** Panel formundan gelen alanları workflow girdisine çevirir. Her şey beyaz listeden
 *  geçer: bu değerler GitHub'da çalışan bir işe parametre olarak gidiyor. */
function vestra_finder_clean_input(array $in): array {
  $langs = [];
  foreach (preg_split('/[\s,;]+/', strtolower((string)($in['langs'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) as $l)
    if (in_array($l, VESTRA_FINDER_LANGS, true)) $langs[$l] = true;
  $countries = [];
  foreach (preg_split('/[\s,;]+/', strtoupper((string)($in['countries'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) as $c)
    if (preg_match('/^[A-Z]{2}$/', $c)) $countries[$c] = true;
  $cities = [];
  foreach (preg_split('/[,;\n]+/', (string)($in['cities'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $pc) {
    $pc = trim($pc); if ($pc === '' || !str_contains($pc, '|')) continue;
    [$co, $ci] = array_map('trim', explode('|', $pc, 2));
    $co = preg_replace('/[^\p{L} .\'-]/u', '', $co); $ci = preg_replace('/[^\p{L} .\'-]/u', '', $ci);
    if ($co !== '' && $ci !== '') $cities[] = mb_substr($co, 0, 40).'|'.mb_substr($ci, 0, 40);
    if (count($cities) >= 12) break;
  }
  $extra = trim(preg_replace('/[\r\n"`$\\\\]+/', ' ', (string)($in['extra_queries'] ?? '')));
  $seeds = [];
  foreach (preg_split('/[\s,;]+/', strtolower((string)($in['seed_domains'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) as $d) {
    $d = preg_replace('#^https?://#', '', $d); $d = preg_replace('#[/?\#].*$#', '', $d);
    if (preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,24}$/', $d)) $seeds[$d] = true;
    if (count($seeds) >= 50) break;
  }
  $q = (int)($in['queries_per_run'] ?? 40); $q = max(5, min(80, $q));
  return [
    'langs' => implode(',', array_keys($langs)), 'countries' => implode(',', array_keys($countries)),
    'cities' => implode(', ', $cities), 'extra_queries' => mb_substr($extra, 0, 400),
    'seed_domains' => implode(', ', array_keys($seeds)), 'queries_per_run' => (string)$q,
    'dry_run' => !empty($in['dry_run']) ? 'true' : 'false',
  ];
}

/* ── başlat ───────────────────────────────────────────────────────────────── */

/** [ok(bool), mesaj(string), id(string)] — mesaj her durumda operatöre gösterilecek cümle. */
function vestra_finder_start(array $in, string $owner = '', string $by = 'admin'): array {
  $owner = preg_replace('/[^A-Za-z0-9_-]/', '', $owner);
  if ($a = vestra_finder_active())
    return [false, 'Bir arama zaten çalışıyor ('.(string)($a['id'] ?? '').', '.date('H:i', (int)strtotime((string)($a['requested_at'] ?? 'now'))).' başladı). Bitince tekrar başlatın — genelde 20-40 dakika.', ''];
  if ($owner !== '') {
    foreach (vestra_finder_runs($owner) as $r) {
      if ((time() - (int)strtotime((string)($r['requested_at'] ?? ''))) < 24 * 3600)
        return [false, 'You can start one web search per 24 hours. Your last one: '.date('d M H:i', (int)strtotime((string)$r['requested_at'])).'.', ''];
    }
  }
  $p = vestra_finder_clean_input($in);
  $repo = vestra_finder_repo();
  $id = 'FR'.date('ymdHi').strtoupper(bin2hex(random_bytes(2)));

  /* TOKEN YOK → sıra. find-customers-queue.yml 10 dakikada bir sıradakini alıp başlatır. */
  if (vestra_finder_gh_token() === '') {
    $runs = vestra_finder_runs();
    array_unshift($runs, ['id' => $id, 'owner' => $owner, 'by' => $by, 'requested_at' => date('c'),
                          'status' => 'requested', 'params' => $p]);
    vestra_finder_save_runs($runs);
    return [true, 'Arama sıraya alındı ('.$id.'). 10 dakika içinde başlar, 20-40 dakikada biter; sonuç bu kartta görünecek.', $id];
  }

  /* Workflow default daldan dispatch edilir; dalın adını sabit yazmak yerine GitHub'a sor. */
  [$c0, $repoInfo] = vestra_finder_gh('GET', '/repos/'.$repo);
  if ($c0 === 401) return [false, 'GitHub anahtarı geçersiz ya da süresi dolmuş (HTTP 401). Yeni bir token oluşturup kaydedin.', ''];
  if ($c0 === 404 || $c0 === 403) return [false, 'GitHub anahtarının '.$repo.' deposuna erişimi yok (HTTP '.$c0.'). Token oluştururken "Repository access" kısmında bu depoyu seçin.', ''];
  if ($c0 !== 200) return [false, 'GitHub\'a ulaşılamadı (HTTP '.$c0.'). Birkaç dakika sonra tekrar deneyin.', ''];
  $ref = (string)($repoInfo['default_branch'] ?? 'main');

  $inputs = ['request_id' => $id, 'owner_uid' => $owner] + $p + ['send' => 'false'];
  [$code, $resp] = vestra_finder_gh('POST', '/repos/'.$repo.'/actions/workflows/'.VESTRA_FINDER_WORKFLOW.'/dispatches', ['ref' => $ref, 'inputs' => $inputs]);
  if ($code !== 204 && $code !== 200) {
    $m = (string)($resp['message'] ?? '');
    $why = match (true) {
      $code === 403 => 'Token\'ın "Actions: Read and write" izni yok.',
      $code === 404 => 'find-customers.yml depoda varsayılan dalda ('.$ref.') bulunamadı — workflow henüz o dala alınmamış.',
      $code === 422 => 'Workflow girdileri uyuşmuyor — varsayılan daldaki find-customers.yml eski sürüm olabilir.',
      default       => 'GitHub HTTP '.$code.'.',
    };
    error_log('[VESTRA finder] dispatch HTTP '.$code.' '.mb_substr($m, 0, 200));
    return [false, 'Arama başlatılamadı: '.$why.($m !== '' ? ' ('.mb_substr($m, 0, 120).')' : ''), ''];
  }

  $runs = vestra_finder_runs();
  array_unshift($runs, ['id' => $id, 'owner' => $owner, 'by' => $by, 'requested_at' => date('c'),
                        'status' => 'requested', 'dispatched_at' => date('c'), 'via' => 'token', 'params' => $p]);
  vestra_finder_save_runs($runs);
  return [true, 'Arama başlatıldı ('.$id.'). Sonuç bu kartta görünecek — genelde 20-40 dakika sürer.', $id];
}

/* ── durum ────────────────────────────────────────────────────────────────── */

/** Bekleyen isteklerin durumunu GitHub'dan günceller. Sayfa her açıldığında en fazla
 *  bir API çağrısı (20 sn aralıkla). Sonucu workflow'un kendisi yazar; bu yalnızca
 *  "kuyrukta / çalışıyor / GitHub'da düştü" ayrımını ve linki sağlar. */
function vestra_finder_refresh(): void {
  $runs = vestra_finder_runs();
  $pending = array_filter($runs, fn($r) => in_array((string)($r['status'] ?? ''), ['requested', 'running'], true));
  if (!$pending) return;
  /* Token yoksa GitHub'a soramayız: yalnızca zaman aşımını uygula (sonucu workflow yazar). */
  if (vestra_finder_gh_token() === '') {
    $changed = false;
    foreach ($runs as &$r) {
      $st = (string)($r['status'] ?? ''); $d = (string)($r['dispatched_at'] ?? '');
      if (($st === 'running' || $st === 'requested') && $d !== '' && time() - (int)strtotime($d) > 95 * 60) {
        $r['status'] = 'failed'; $r['finished_at'] = date('c'); $r['notes'] = ['Zaman aşımı (95 dk) — sonuç sunucuya yazılmadı.']; $changed = true;
      }
    }
    unset($r);
    if ($changed) vestra_finder_save_runs($runs);
    return;
  }
  $stamp = vestra_data_dir().'/finder_check.ts';
  if (is_readable($stamp) && time() - (int)@file_get_contents($stamp) < 20) return;
  @file_put_contents($stamp, (string)time());

  [$code, $d] = vestra_finder_gh('GET', '/repos/'.vestra_finder_repo().'/actions/workflows/'.VESTRA_FINDER_WORKFLOW.'/runs?event=workflow_dispatch&per_page=30');
  $gh = ($code === 200) ? (array)($d['workflow_runs'] ?? []) : [];
  $changed = false;
  foreach ($runs as &$r) {
    if (!in_array((string)($r['status'] ?? ''), ['requested', 'running'], true)) continue;
    if (empty($r['dispatched_at'])) continue;                 // sırada, henüz başlatılmadı (queue işi alacak)
    $id = (string)($r['id'] ?? ''); $age = time() - (int)strtotime((string)$r['dispatched_at']);
    $match = null;
    foreach ($gh as $g) if ($id !== '' && str_contains((string)($g['display_title'] ?? $g['name'] ?? ''), $id)) { $match = $g; break; }
    if ($match) {
      $r['run_url'] = (string)($match['html_url'] ?? '');
      $gs = (string)($match['status'] ?? '');
      if ($gs === 'completed') {
        /* Başarılı biten koşunun sonucunu workflow'un son adımı yazar; bu kayıt hâlâ
           "running" ise o adım ulaşamamış demektir. 5 dk tanı, sonra düştü say. */
        $done = strtotime((string)($match['updated_at'] ?? 'now'));
        if (($match['conclusion'] ?? '') !== 'success' || time() - $done > 300) {
          $r['status'] = 'failed'; $r['finished_at'] = date('c', $done);
          $r['notes'] = ['GitHub koşusu "'.(string)($match['conclusion'] ?? '?').'" ile bitti ama sonuç sunucuya yazılamadı — ayrıntı için GitHub linkine bakın.'];
        }
      } elseif ($r['status'] !== 'running') { $r['status'] = 'running'; }
      $changed = true;
    } elseif ($age > 15 * 60 && $code === 200) {
      $r['status'] = 'failed'; $r['finished_at'] = date('c');
      $r['notes'] = ['GitHub bu isteğe ait bir koşu başlatmadı (15 dk). Token izni ya da workflow dalı kontrol edilmeli.'];
      $changed = true;
    } elseif (!vestra_finder_is_active($r)) {
      $r['status'] = 'failed'; $r['finished_at'] = date('c'); $r['notes'] = ['Zaman aşımı (95 dk).']; $changed = true;
    }
  }
  unset($r);
  if ($changed) vestra_finder_save_runs($runs);
}

/* ── görünüm ──────────────────────────────────────────────────────────────── */

/** Admin ve satıcı panelinin ortak sonuç listesi. Yalnızca satır içi stil kullanır ki
 *  iki panelin farklı CSS'inde aynı görünsün. $showOwner: admin tüm kayıtları görür. */
function vestra_finder_runs_html(array $runs, bool $showOwner = false, array $ownerNames = [], int $limit = 6, bool $en = false): string {
  /* Satıcı tarafı (İngilizce anahtar) t() ile satıcının diline çevrilir — inc/lang/*.php. */
  $tt = static fn(string $x): string => function_exists('t') ? (string)t($x) : $x;
  $T = $en
    ? ['none'=>$tt('No searches yet.'),'done'=>$tt('✓ Finished'),'failed'=>$tt('✗ Failed'),'running'=>$tt('⏳ Running'),'queued'=>$tt('⏳ Queued'),'dry'=>$tt(' · trial (not saved)'),
       'stats'=>'<b>'.htmlspecialchars($tt('%d queries · %d sites found · %d checked → %d new customers with a real email'), ENT_QUOTES, 'UTF-8').'</b>','added'=>htmlspecialchars($tt('Added'), ENT_QUOTES, 'UTF-8'),
       'noemail'=>htmlspecialchars($tt('Good fit but no email published (%d) — reach them by phone or contact form'), ENT_QUOTES, 'UTF-8'),'more'=>'GitHub ↗']
    : ['none'=>'Henüz arama yapılmadı.','done'=>'✓ Bitti','failed'=>'✗ Başarısız','running'=>'⏳ Çalışıyor','queued'=>'⏳ Sırada','dry'=>' · deneme (kaydedilmedi)',
       'stats'=>'%d sorgu · %d aday site · %d incelendi → <b>%d gerçek e-postalı yeni müşteri</b>','added'=>'Eklenenler','noemail'=>'Uygun ama sitesinde e-posta yayınlamayan (%d) — telefon/form ile ulaşılabilir','more'=>'GitHub\'da ayrıntı ↗'];
  if ($en) foreach (['none', 'done', 'failed', 'running', 'queued', 'dry'] as $k) $T[$k] = htmlspecialchars($T[$k], ENT_QUOTES, 'UTF-8');
  if (!$runs) return '<div style="background:var(--bg2,#f6f6f4);border-radius:8px;padding:10px 14px;font-size:12.5px;color:var(--mut,#777)">'.$T['none'].'</div>';
  $h = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  $out = '';
  foreach (array_slice($runs, 0, $limit) as $r) {
    $st = (string)($r['status'] ?? '');
    [$col, $lab] = match ($st) {
      'done'      => ['#1f9d63', $T['done']],
      'failed'    => ['#c0392b', $T['failed']],
      'running'   => ['#3366cc', $T['running']],
      default     => ['#a9781a', $T['queued']],
    };
    $when = date('d.m H:i', (int)strtotime((string)($r['requested_at'] ?? 'now')));
    $who  = $showOwner ? (($o = (string)($r['owner'] ?? '')) === '' ? ' · admin' : ' · satıcı: '.$h($ownerNames[$o] ?? $o)) : '';
    $p = (array)($r['params'] ?? []);
    $scope = trim(implode(' · ', array_filter([(string)($p['cities'] ?? ''), (string)($p['countries'] ?? ''), (string)($p['langs'] ?? '')])));
    $out .= '<div style="border:1px solid var(--line,#e5e5e5);border-radius:10px;padding:10px 13px;margin-bottom:8px;font-size:12.5px">';
    $out .= '<div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap"><b style="color:'.$col.'">'.$lab.'</b>'
          . '<span style="color:var(--mut,#777)">'.$h($when).$who.' · '.$h($r['id'] ?? '').(!empty($r['dry']) ? $T['dry'] : '').'</span></div>';
    if ($scope !== '') $out .= '<div style="color:var(--mut,#777);margin-top:2px">'.$h($scope).'</div>';
    if ($st === 'done') {
      $out .= '<div style="margin-top:4px">'.sprintf($T['stats'], (int)($r['queries'] ?? 0), (int)($r['candidates'] ?? 0), (int)($r['analyzed'] ?? 0), (int)($r['added_count'] ?? 0)).'</div>';
      $added = (array)($r['added'] ?? []);
      if ($added) {
        $out .= '<details style="margin-top:6px"'.(count($added) <= 8 ? ' open' : '').'><summary style="cursor:pointer">'.$T['added'].' ('.count($added).')</summary>'
              . '<table style="width:100%;border-collapse:collapse;margin-top:6px;font-size:12px">';
        foreach (array_slice($added, 0, 100) as $a) {
          $out .= '<tr style="border-top:1px solid var(--line,#eee)"><td style="padding:3px 6px 3px 0">'.$h($a['company'] ?? '').'</td>'
                . '<td style="padding:3px 6px">'.$h($a['email'] ?? '').'</td><td style="padding:3px 6px">'.$h($a['country'] ?? '').'</td>'
                . '<td style="padding:3px 0;color:var(--mut,#777)">'.$h(implode(', ', array_slice((array)($a['brands'] ?? []), 0, 4))).'</td></tr>';
        }
        $out .= '</table></details>';
      }
      $nm = (array)($r['no_email'] ?? []);
      if ($nm) {
        $out .= '<details style="margin-top:4px"><summary style="cursor:pointer;color:var(--mut,#777)">'.sprintf($T['noemail'], count($nm)).'</summary><ul style="margin:6px 0 0 18px;padding:0">';
        foreach (array_slice($nm, 0, 40) as $n) $out .= '<li>'.$h($n['company'] ?? '').' — '.$h($n['domain'] ?? '').($n['phone'] ?? '' ? ' · ☎ '.$h($n['phone']) : '').'</li>';
        $out .= '</ul></details>';
      }
    }
    /* Notlar ve GitHub linki operatör içindir (Türkçe, teknik); satıcı görmez. */
    if (!$en) foreach ((array)($r['notes'] ?? []) as $n) $out .= '<div style="color:#a9781a;margin-top:4px">⚠ '.$h($n).'</div>';
    if (!$en && !empty($r['run_url'])) $out .= '<div style="margin-top:4px"><a href="'.$h($r['run_url']).'" target="_blank" rel="noopener" style="color:var(--acc,#8a6420)">'.$T['more'].'</a></div>';
    $out .= '</div>';
  }
  return $out;
}
