<?php
/**
 * VESTRA — Claude ile kampanya yazarı (operatör, 8 Eki 2026: "istediği tarza kendi
 * kataloglarından kampanya hazırlat, benim Claude API'yi kullandır ancak limitli yap").
 *
 * Satıcı (ya da admin) katalogdan ürün + tarz + dil seçer; Claude konu + metin yazar;
 * kişi düzenleyip "kullan" der ve tek tek gönderimde o metin gider. Gönderim alt bilgisi
 * (gönderen kimliği + tek tık abonelikten çıkma) her zaman sistem tarafından eklenir —
 * modele yazdırılmaz.
 *
 * ANAHTAR: operatörün Claude anahtarı, data/email_settings.json `anthropic_key`
 * (set-api-keys.yml repodaki ANTHROPIC_API_KEY'den aktarır; chmod 600, web'e kapalı).
 * SATICININ KENDİ ANAHTARI (operatör, 8 Eki 2026: "saticilar kendi Claude API lerini
 * koysunlar"): data/seller_ai_keys.json (chmod 600). Varsa ÖNCE o kullanılır; o
 * çağrılar platform sınırına tabi DEĞİL ve platform maliyetine yazılmaz (ayrı
 * `own_*` sayaçları). Kayıttan önce ücretsiz GET /v1/models ile doğrulanır.
 * İKİ SAĞLAYICI + İLK KAMPANYA ÜCRETSİZ (operatör, 8 Eki 2026: "benim yapay zeka API'mden
 * faydalansınlar, Claude'u da DeepSeek'i de koy ikisini de, ama tek bir kampanya için …
 * sonra kendi API'sini koysun"). Yönlendirme (vestra_ai_camp_route): satıcının kendi
 * Claude'u → satıcının kendi DeepSeek'i (seller_mail.json `ai_key`) → platformun Claude'u →
 * platformun DeepSeek'i (vestra_ai_key). Platform anahtarıyla satıcı başına TOPLAM
 * `ai_camp_free_total` (varsayılan 1) kampanya — data/ai_campaign_free.json.
 * SINIR: satıcı başına toplam ücretsiz kampanya + platform geneli aylık tavan
 * (ai_camp_free_total / ai_camp_platform_month — admin panelinden; aşağıdaki "İLK KAMPANYA ÜCRETSİZ").
 * Her çağrı (HTTP 200 dönen) sayılır ve token kullanımı kaydedilir; admin maliyeti görür.
 *
 * NEDEN HAM HTTP: bu kod tabanında paket yöneticisi yok, deploy kaynağı kopyalıyor ve
 * diğer bütün sağlayıcılar (Brevo, Google, DeepSeek, Stripe) curl ile çağrılıyor.
 */
require_once __DIR__.'/notify.php';   // vestra_cfg

const VESTRA_AI_CAMP_MODEL = 'claude-opus-5-5';
const VESTRA_AI_CAMP_DS_MODEL = 'deepseek-chat';

function vestra_ai_camp_key(): string { return trim((string)vestra_cfg('anthropic_key', '')); }
/** Platformun DeepSeek anahtarı (Müşteriler sekmesindeki AI anahtarı / DEEPSEEK_KEY). */
function vestra_ai_camp_ds_key(): string { return function_exists('vestra_ai_key') ? trim(vestra_ai_key()) : ''; }
/** Platformda yazdırabilecek BİR anahtar var mı (Claude ya da DeepSeek). */
function vestra_ai_camp_on(): bool    { return vestra_ai_camp_key() !== '' || vestra_ai_camp_ds_key() !== ''; }

/* ── satıcının kendi anahtarı ────────────────────────────────────────────── */

function vestra_ai_camp_seller_keys(): array {
  $f = vestra_data_dir().'/seller_ai_keys.json';
  $a = is_readable($f) ? json_decode((string)file_get_contents($f), true) : [];
  return is_array($a) ? $a : [];
}
function vestra_ai_camp_seller_key(string $uid): string {
  if ($uid === '') return '';
  return trim((string)((vestra_ai_camp_seller_keys()[$uid] ?? [])['key'] ?? ''));
}
/** '' = sil. Anahtar ham olarak saklanır (Brevo anahtarlarıyla aynı: chmod 600, data/ web'e kapalı). */
function vestra_ai_camp_seller_key_save(string $uid, string $key): bool {
  if ($uid === '') return false;
  $a = vestra_ai_camp_seller_keys();
  if ($key === '') unset($a[$uid]);
  else $a[$uid] = ['key' => $key, 'saved_at' => date('c'), 'tail' => substr($key, -4)];
  $f = vestra_data_dir().'/seller_ai_keys.json';
  $ok = file_put_contents($f, json_encode($a, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
  @chmod($f, 0600);
  return $ok && vestra_ai_camp_seller_key($uid) === $key;
}

/** Satıcının kendi DeepSeek anahtarı (seller_mail.json `ai_key` — kişiselleştirmeyle ortak). */
function vestra_ai_camp_seller_ds_key(string $uid): string {
  if ($uid === '' || !function_exists('vestra_seller_mail')) return '';
  return trim((string)(vestra_seller_mail($uid)['ai_key'] ?? ''));
}
/**
 * Bu kişi için yazım yolu: ['provider'=>'claude'|'deepseek', 'key'=>…, 'src'=>'own'|'platform'] ya da null.
 * Satıcının kendi anahtarları önce (Claude, sonra DeepSeek); sonra platformunki (Claude, sonra DeepSeek).
 */
function vestra_ai_camp_route(string $owner): ?array {
  if ($owner !== '') {
    if (($k = vestra_ai_camp_seller_key($owner)) !== '')    return ['provider' => 'claude', 'key' => $k, 'src' => 'own'];
    if (($k = vestra_ai_camp_seller_ds_key($owner)) !== '') return ['provider' => 'deepseek', 'key' => $k, 'src' => 'own'];
  }
  if (($k = vestra_ai_camp_key()) !== '')    return ['provider' => 'claude', 'key' => $k, 'src' => 'platform'];
  if (($k = vestra_ai_camp_ds_key()) !== '') return ['provider' => 'deepseek', 'key' => $k, 'src' => 'platform'];
  return null;
}
/** Geriye uyumlu: [anahtar, 'own'|'platform'|'']. */
function vestra_ai_camp_key_for(string $owner): array {
  $r = vestra_ai_camp_route($owner);
  return $r ? [$r['key'], $r['src']] : ['', ''];
}
function vestra_ai_camp_on_for(string $owner): bool { return vestra_ai_camp_route($owner) !== null; }
function vestra_ai_camp_has_own(string $owner): bool { return ($r = vestra_ai_camp_route($owner)) !== null && $r['src'] === 'own'; }

/** DeepSeek anahtarını sınar — GET /models, ücretsiz. [ok, kod] (kodlar Claude sınamasıyla aynı). */
function vestra_ai_camp_ds_key_check(string $key, ?callable $http = null): array {
  $key = trim($key);
  if (!preg_match('/^sk-[A-Za-z0-9_\-]{16,}$/', $key) || str_starts_with($key, 'sk-ant-')) return [false, 'format'];
  $http = $http ?? function (string $k): int {
    $ch = curl_init('https://api.deepseek.com/models');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 10,
      CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$k, 'Accept: application/json']]);
    curl_exec($ch); $c = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return $c;
  };
  $code = (int)$http($key);
  if ($code === 200) return [true, 'ok'];
  if ($code === 401) return [false, 'invalid'];
  if ($code === 403) return [false, 'forbidden'];
  return [false, 'unreachable'];
}

/* ── platform anahtarıyla ücretsiz kampanya (satıcı başına toplam) ─────────── */
function vestra_ai_camp_free_used(string $uid): int { return (int)(vestra_read_json('ai_campaign_free.json')[$uid] ?? 0); }
function vestra_ai_camp_free_take(string $uid): void {
  $a = vestra_read_json('ai_campaign_free.json'); $a[$uid] = (int)($a[$uid] ?? 0) + 1;
  vestra_write_json('ai_campaign_free.json', $a);
}

/**
 * Anahtarı kaydetmeden önce sınar — GET /v1/models: ücretsiz, token harcamaz.
 * [ok, kod]  kod: 'ok' | 'format' | 'invalid' (401) | 'forbidden' (403) | 'unreachable'
 * Not: bakiyesi olmayan anahtar da burada geçer (modeller listelenir); bakiye eksikliği
 * ilk yazımda 'own_credit' olarak söylenir.
 */
function vestra_ai_camp_key_check(string $key, ?callable $http = null): array {
  $key = trim($key);
  if (!preg_match('/^sk-ant-[A-Za-z0-9_\-]{20,}$/', $key)) return [false, 'format'];
  $http = $http ?? function (string $k): int {
    $ch = curl_init('https://api.anthropic.com/v1/models?limit=1');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 10,
      CURLOPT_HTTPHEADER => ['x-api-key: '.$k, 'anthropic-version: 2023-06-01']]);
    curl_exec($ch); $c = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return $c;
  };
  $code = (int)$http($key);
  if ($code === 200) return [true, 'ok'];
  if ($code === 401) return [false, 'invalid'];
  if ($code === 403) return [false, 'forbidden'];
  return [false, 'unreachable'];
}

function vestra_ai_camp_limits(): array {
  $g = fn($k, $d) => max(0, (int)(vestra_cfg($k, $d) ?? $d));
  return ['free_total' => $g('ai_camp_free_total', 1), 'platform_month' => $g('ai_camp_platform_month', 300)];
}

/* ── kullanım / kota ─────────────────────────────────────────────────────── */

function vestra_ai_camp_usage(): array { return vestra_read_json('ai_campaign_usage.json'); }

/** owner '' = admin (platform): yalnız platform aylık tavanı. Satıcı: kendi anahtarı varsa
 *  sınırsız; yoksa platform anahtarıyla TOPLAM `free_total` kampanya (ilk kampanya ücretsiz).
 *  day_left/month_left geriye uyum için: satıcıda ikisi de kalan ücretsiz hak. */
function vestra_ai_camp_quota(string $owner): array {
  if ($owner !== '' && vestra_ai_camp_has_own($owner)) return ['ok' => true, 'day_left' => null, 'month_left' => null, 'free_left' => null, 'why' => '', 'own' => true];
  $L = vestra_ai_camp_limits(); $u = vestra_ai_camp_usage();
  $mon = (array)($u[date('Y-m')] ?? []);
  $platLeft = max(0, $L['platform_month'] - (int)($mon['calls'] ?? 0));
  if ($owner === '') return ['ok' => $platLeft > 0, 'day_left' => $platLeft, 'month_left' => $platLeft, 'free_left' => null, 'why' => $platLeft > 0 ? '' : 'platform', 'own' => false];
  $free = max(0, $L['free_total'] - vestra_ai_camp_free_used($owner));
  $why = $free <= 0 ? 'free' : ($platLeft <= 0 ? 'platform' : '');
  return ['ok' => $why === '', 'day_left' => min($free, $platLeft), 'month_left' => min($free, $platLeft), 'free_left' => $free, 'why' => $why, 'own' => false];
}

function vestra_ai_camp_count(string $owner, int $in, int $out, bool $own = false, string $provider = 'claude'): void {
  $u = vestra_ai_camp_usage(); $m = date('Y-m'); $d = date('Y-m-d'); $k = $owner === '' ? '_admin' : $owner;
  $mon = (array)($u[$m] ?? []);
  if ($own) {
    /* Satıcının kendi anahtarı: platform sayaçlarına (calls/in/out → kota ve maliyet)
       DOKUNMAZ; yalnız satıcıya "bu ay kaç kampanya, kaç token" göstermek için. */
    $o = (array)(($mon['owners'] ?? [])[$k] ?? []);
    $o['own_calls'] = (int)($o['own_calls'] ?? 0) + 1;
    $o['own_in'] = (int)($o['own_in'] ?? 0) + $in; $o['own_out'] = (int)($o['own_out'] ?? 0) + $out;
    $mon['owners'][$k] = $o;
    $mon['own_calls'] = (int)($mon['own_calls'] ?? 0) + 1;
    $u[$m] = $mon; ksort($u); $u = array_slice($u, -6, null, true);
    vestra_write_json('ai_campaign_usage.json', $u);
    return;
  }
  $mon['calls'] = (int)($mon['calls'] ?? 0) + 1;
  /* Maliyet tahmini Claude fiyatıyla yapılıyor: DeepSeek token'ları ayrı sayaçta. */
  if ($provider === 'deepseek') { $mon['ds_calls'] = (int)($mon['ds_calls'] ?? 0) + 1; $mon['ds_in'] = (int)($mon['ds_in'] ?? 0) + $in; $mon['ds_out'] = (int)($mon['ds_out'] ?? 0) + $out; }
  else { $mon['in'] = (int)($mon['in'] ?? 0) + $in; $mon['out'] = (int)($mon['out'] ?? 0) + $out; }
  $o = (array)(($mon['owners'] ?? [])[$k] ?? []);
  $o['calls'] = (int)($o['calls'] ?? 0) + 1;
  $o['days'][$d] = (int)(($o['days'] ?? [])[$d] ?? 0) + 1;
  $mon['owners'][$k] = $o;
  $u[$m] = $mon;
  ksort($u); $u = array_slice($u, -6, null, true);       // son 6 ay
  vestra_write_json('ai_campaign_usage.json', $u);
}

/* ── seçenekler ──────────────────────────────────────────────────────────── */

/** key => [etiket (İngilizce, ekranda t() ile çevrilir), modele giden tarif] */
function vestra_ai_camp_styles(): array {
  return [
    'luxury'   => ['Elegant & luxury', 'Elegant, refined, premium tone. Calm confidence, no hype, no exclamation marks.'],
    'direct'   => ['Short & direct', 'Very short and to the point: what is available, the terms, one clear call to reply. Under 100 words.'],
    'friendly' => ['Friendly & personal', 'Warm, personal, conversational, like a note from a trusted supplier.'],
    'stock'    => ['Stock offer / clearance', 'A concrete stock offer: lead with the selection and the terms, create gentle urgency only from the facts given (limited stock), never invent deadlines.'],
    'story'    => ['Story about the brands', 'Open with a short, factual angle on the brands and why they sell in multi-brand boutiques, then the selection.'],
    'custom'   => ['My own style (describe below)', ''],
  ];
}
/** Örnek tarifler (İngilizce kaynak, ekranda t() ile satıcının dilinde; tıklanınca "Kendi
 *  sözleriniz" alanına yazılır). İlki tek tıkla hazır kampanyanın tarifi. */
function vestra_ai_camp_examples(): array {
  return [
    'Short and elegant, for multi-brand boutiques. Mention that we sell on VESTRA, a verified B2B marketplace.',
    'Friendly first contact: introduce my brand, show my best products and invite them to reply.',
    'Stock offer: highlight the sizes and the minimum order, and ask if they want the full line sheet.',
    'For concept stores: focus on the story of the brands and why they sell well in independent shops.',
  ];
}
function vestra_ai_camp_langs(): array {
  return ['en' => 'English', 'de' => 'Deutsch', 'fr' => 'Français', 'it' => 'Italiano', 'es' => 'Español', 'nl' => 'Nederlands',
          'pt' => 'Português', 'pl' => 'Polski', 'el' => 'Ελληνικά', 'cs' => 'Čeština', 'ru' => 'Русский', 'ar' => 'العربية', 'ja' => '日本語', 'tr' => 'Türkçe'];
}

/* ── kayıtlar ────────────────────────────────────────────────────────────── */

function vestra_ai_camp_all(): array { return vestra_read_json('ai_campaigns.json'); }
function vestra_ai_camp_list(string $owner): array {
  return array_values(array_filter(vestra_ai_camp_all(), fn($c) => (string)($c['owner'] ?? '') === $owner));
}
function vestra_ai_camp_get(string $id, string $owner): ?array {
  foreach (vestra_ai_camp_all() as $c) if (($c['id'] ?? '') === $id && (string)($c['owner'] ?? '') === $owner) return $c;
  return null;
}
/** Kişinin düzenlemesini kaydeder; $activate ise gönderimde bu metin kullanılır. */
function vestra_ai_camp_save_edit(string $id, string $owner, string $subject, string $body, bool $activate): bool {
  $all = vestra_ai_camp_all(); $hit = false;
  foreach ($all as &$c) {
    if (($c['owner'] ?? '') !== $owner) continue;
    if ($activate) $c['active'] = false;
    if (($c['id'] ?? '') === $id) {
      $c['subject'] = mb_substr(trim($subject), 0, 200); $c['body'] = mb_substr(trim($body), 0, 6000);
      $c['edited_at'] = date('c'); if ($activate) $c['active'] = true; $hit = true;
    }
  }
  unset($c);
  if ($hit) vestra_write_json('ai_campaigns.json', $all);
  return $hit;
}
function vestra_ai_camp_deactivate(string $owner): void {
  $all = vestra_ai_camp_all();
  foreach ($all as &$c) if (($c['owner'] ?? '') === $owner) $c['active'] = false;
  unset($c); vestra_write_json('ai_campaigns.json', $all);
}
function vestra_ai_camp_active(string $owner): ?array {
  foreach (vestra_ai_camp_list($owner) as $c) if (!empty($c['active']) && trim((string)($c['body'] ?? '')) !== '') return $c;
  return null;
}
/** vestra_lead_render_email'e verilecek şablon biçimi ({{company}} yer tutucusu korunur). */
function vestra_ai_camp_template(array $c): array {
  return ['subject' => (string)($c['subject'] ?? ''), 'body' => (string)($c['body'] ?? ''), 'img' => ''];
}

/* ── üretim ──────────────────────────────────────────────────────────────── */

/** Ürün satırı: yalnızca kayıttaki gerçek bilgiler. */
function vestra_ai_camp_product_line(array $p, bool $prices): string {
  $parts = [trim((string)($p['brand'] ?? '')), trim((string)($p['name'] ?? ''))];
  $line = '- '.trim(implode(' — ', array_filter($parts)));
  if (($p['cat'] ?? '') !== '') $line .= ' | category: '.$p['cat'];
  if (!empty($p['moq'])) $line .= ' | minimum order: '.(int)$p['moq'].' '.($p['unit'] ?? 'pcs');
  if (($p['sizes'] ?? '') !== '') $line .= ' | sizes: '.mb_substr((string)$p['sizes'], 0, 80);
  if ($prices) {
    $pr = (float)($p['list'] ?? 0);
    if (!empty($p['tiers']) && is_array($p['tiers'])) { $mins = array_filter(array_map(fn($t) => (float)($t['price'] ?? 0), $p['tiers'])); if ($mins) $pr = min($mins); }
    if ($pr > 0) $line .= ' | wholesale price from: '.number_format($pr, 2, '.', '').' EUR';
  }
  $line .= ' | link: https://vestrasales.com/product?id='.rawurlencode((string)($p['id'] ?? ''));
  return $line;
}

/**
 * [ok, kod, kampanya|null]  — kod: 'ok' | 'nokey' | 'quota_day' | 'quota_month' | 'quota_platform'
 *   | 'noproducts' | 'refusal' | 'toolong' | 'api' | 'parse'
 *   | 'own_key' | 'own_credit' | 'own_rate'  (yalnız satıcının kendi anahtarında)
 * $catalog: seçilebilecek ürünler (satıcıda kendi ilanları, adminde onaylı katalog).
 */
function vestra_ai_camp_generate(string $owner, string $senderName, array $catalog, array $in): array {
  $route = vestra_ai_camp_route($owner);
  if ($route === null) return [false, 'nokey', null];
  ['provider' => $prov, 'key' => $key, 'src' => $src] = $route;
  $own = $src === 'own';
  $q = vestra_ai_camp_quota($owner);
  if (!$q['ok']) return [false, 'quota_'.$q['why'], null];

  $want = array_slice(array_map('strval', (array)($in['products'] ?? [])), 0, 12);
  $byId = []; foreach ($catalog as $p) $byId[(string)($p['id'] ?? '')] = $p;
  $sel = []; foreach ($want as $id) if (isset($byId[$id])) $sel[] = $byId[$id];
  if (!$sel) $sel = array_slice(array_values($catalog), 0, 8);
  if (!$sel) return [false, 'noproducts', null];

  $styles = vestra_ai_camp_styles(); $style = isset($styles[$in['style'] ?? '']) ? (string)$in['style'] : 'luxury';
  $langs = vestra_ai_camp_langs(); $lang = isset($langs[$in['lang'] ?? '']) ? (string)$in['lang'] : 'en';
  $custom = mb_substr(trim(preg_replace('/\s+/', ' ', (string)($in['custom'] ?? ''))), 0, 300);
  $offer  = mb_substr(trim(preg_replace('/\s+/', ' ', (string)($in['offer'] ?? ''))), 0, 300);
  $prices = !empty($in['prices']);

  $system = "You write short B2B wholesale emails for VESTRA, a verified B2B marketplace for authentic designer fashion. "
    ."The sender is a wholesale seller writing to independent multi-brand boutiques that may want to stock these products.\n"
    ."Rules:\n"
    ."- Use only the facts given (products, sizes, minimum orders, prices, links, the seller's offer). Never invent stock levels, discounts, deadlines, delivery times, awards or claims.\n"
    ."- Address the shop with the placeholder {{company}} exactly as written (it is replaced with the shop's name). Use no other placeholders.\n"
    ."- Plain text only: no markdown, no bullet symbols other than a simple dash, short paragraphs.\n"
    ."- Include the product links given. End with an invitation to reply and the sender's name.\n"
    ."- Do not add an unsubscribe or legal footer; it is appended automatically.\n"
    ."- The subject line is under 70 characters and contains no emoji.\n"
    ."- Return a JSON object with exactly two string fields: \"subject\" and \"body\".";
  $user = "Language of the email: ".$langs[$lang]."\n"
    ."Style: ".($style === 'custom' ? ($custom !== '' ? $custom : 'Professional and clear.') : $styles[$style][1].($custom !== '' ? ' Also: '.$custom : ''))."\n"
    .($offer !== '' ? "Offer or note from the seller (state it exactly, add nothing): ".$offer."\n" : '')
    ."Sender name: ".($senderName !== '' ? $senderName : 'VESTRA')."\n"
    ."Products:\n".implode("\n", array_map(fn($p) => vestra_ai_camp_product_line($p, $prices), $sel));

  if ($prov === 'deepseek') {
    [$code, $subject, $body, $in_t, $out_t, $why] = vestra_ai_camp_call_deepseek($key, $system, $user);
  } else {
    [$code, $subject, $body, $in_t, $out_t, $why] = vestra_ai_camp_call_claude($key, $system, $user);
  }
  if ($code !== 200) {
    error_log('[VESTRA ai_campaign] '.$prov.($own ? ' own key' : '').' HTTP '.$code.' '.mb_substr($why, 0, 200));
    /* Satıcının kendi anahtarındaki sorun SATICININ düzeltebileceği bir şey: ona söylenir.
       Platform anahtarındaki sorun satıcıya "şu an kullanılamıyor" olarak kalır. */
    if ($own && ($code === 401 || $code === 403)) return [false, 'own_key', null];
    if ($own && ($code === 402 || stripos($why, 'credit balance') !== false || stripos($why, 'insufficient balance') !== false)) return [false, 'own_credit', null];
    if ($own && $code === 429) return [false, 'own_rate', null];
    return [false, 'api', null];
  }
  vestra_ai_camp_count($owner, $in_t, $out_t, $own, $prov);
  if ($why === 'refusal') return [false, 'refusal', null];
  if ($why === 'toolong') return [false, 'toolong', null];
  if ($subject === '' || $body === '') return [false, 'parse', null];
  if (!$own && $owner !== '') vestra_ai_camp_free_take($owner);

  $camp = ['id' => 'AC'.strtoupper(bin2hex(random_bytes(4))), 'owner' => $owner, 'created_at' => date('c'),
           'style' => $style, 'lang' => $lang, 'subject' => mb_substr($subject, 0, 200), 'body' => mb_substr($body, 0, 6000),
           'products' => array_map(fn($p) => (string)($p['id'] ?? ''), $sel), 'active' => false, 'key' => $src, 'provider' => $prov];
  $all = vestra_ai_camp_all();
  array_unshift($all, $camp);
  /* Kişi başına son 20 kampanya. */
  $keep = []; $per = [];
  foreach ($all as $c) { $o = (string)($c['owner'] ?? ''); $per[$o] = ($per[$o] ?? 0) + 1; if ($per[$o] <= 20 || !empty($c['active'])) $keep[] = $c; }
  vestra_write_json('ai_campaigns.json', $keep);
  return [true, 'ok', $camp];
}

/** Claude çağrısı. [httpKodu, konu, metin, giriş, çıkış, neden('refusal'|'toolong'|hata mesajı|'')] */
function vestra_ai_camp_call_claude(string $key, string $system, string $user): array {
  $schema = ['type' => 'object', 'additionalProperties' => false, 'required' => ['subject', 'body'],
             'properties' => ['subject' => ['type' => 'string'], 'body' => ['type' => 'string']]];
  $payload = [
    'model' => VESTRA_AI_CAMP_MODEL,
    'max_tokens' => 4000,
    'system' => $system,
    'messages' => [['role' => 'user', 'content' => $user]],
    /* Kısa pazarlama metni: düşük çaba yeterli ve maliyeti sınırlar. Yapılandırılmış çıktı
       JSON'u garanti eder; ret halinde Anthropic'in önerdiği modele sunucu tarafında geçilir. */
    'output_config' => ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => $schema]],
    'fallbacks' => 'default',
  ];
  $ch = curl_init('https://api.anthropic.com/v1/messages');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 120, CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_HTTPHEADER => ['content-type: application/json', 'x-api-key: '.$key,
                           'anthropic-version: 2023-06-01', 'anthropic-beta: server-side-fallback-2026-07-01'],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
  ]);
  $raw = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  $d = is_string($raw) ? json_decode($raw, true) : null;
  if ($code !== 200 || !is_array($d)) return [$code === 200 ? 0 : $code, '', '', 0, 0, (string)($d['error']['message'] ?? (is_string($raw) ? $raw : ''))];
  $in = (int)($d['usage']['input_tokens'] ?? 0); $out = (int)($d['usage']['output_tokens'] ?? 0);
  $stop = (string)($d['stop_reason'] ?? '');
  if ($stop === 'refusal') return [200, '', '', $in, $out, 'refusal'];
  if ($stop === 'max_tokens') return [200, '', '', $in, $out, 'toolong'];
  $text = '';
  foreach ((array)($d['content'] ?? []) as $b) if (($b['type'] ?? '') === 'text') { $text .= (string)($b['text'] ?? ''); }
  $j = json_decode($text, true);
  return [200, trim((string)($j['subject'] ?? '')), trim((string)($j['body'] ?? '')), $in, $out, ''];

}

/** DeepSeek çağrısı (OpenAI uyumlu, JSON modu). Dönüş biçimi Claude'unkiyle aynı. */
function vestra_ai_camp_call_deepseek(string $key, string $system, string $user): array {
  $url = (string)vestra_cfg('ai_url', 'https://api.deepseek.com/chat/completions');
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 120, CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer '.$key],
    CURLOPT_POSTFIELDS => json_encode(['model' => (string)vestra_cfg('ai_model', VESTRA_AI_CAMP_DS_MODEL), 'max_tokens' => 2500, 'temperature' => 0.7,
      'response_format' => ['type' => 'json_object'],
      'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]]], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
  ]);
  $raw = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  $d = is_string($raw) ? json_decode($raw, true) : null;
  if ($code !== 200 || !is_array($d)) return [$code === 200 ? 0 : $code, '', '', 0, 0, (string)($d['error']['message'] ?? (is_string($raw) ? $raw : ''))];
  $in = (int)($d['usage']['prompt_tokens'] ?? 0); $out = (int)($d['usage']['completion_tokens'] ?? 0);
  if ((string)($d['choices'][0]['finish_reason'] ?? '') === 'length') return [200, '', '', $in, $out, 'toolong'];
  $j = json_decode((string)($d['choices'][0]['message']['content'] ?? ''), true);
  return [200, trim((string)($j['subject'] ?? '')), trim((string)($j['body'] ?? '')), $in, $out, ''];
}
