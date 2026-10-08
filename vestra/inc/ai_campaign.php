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
 * SINIR: satıcı başına günlük + aylık, ve platform geneli aylık tavan
 * (ai_camp_per_day / ai_camp_per_month / ai_camp_platform_month — admin panelinden).
 * Her çağrı (HTTP 200 dönen) sayılır ve token kullanımı kaydedilir; admin maliyeti görür.
 *
 * NEDEN HAM HTTP: bu kod tabanında paket yöneticisi yok, deploy kaynağı kopyalıyor ve
 * diğer bütün sağlayıcılar (Brevo, Google, DeepSeek, Stripe) curl ile çağrılıyor.
 */
require_once __DIR__.'/notify.php';   // vestra_cfg

const VESTRA_AI_CAMP_MODEL = 'claude-opus-5-5';

function vestra_ai_camp_key(): string { return trim((string)vestra_cfg('anthropic_key', '')); }
function vestra_ai_camp_on(): bool    { return vestra_ai_camp_key() !== ''; }

function vestra_ai_camp_limits(): array {
  $g = fn($k, $d) => max(0, (int)(vestra_cfg($k, $d) ?? $d));
  return ['per_day' => $g('ai_camp_per_day', 3), 'per_month' => $g('ai_camp_per_month', 20), 'platform_month' => $g('ai_camp_platform_month', 300)];
}

/* ── kullanım / kota ─────────────────────────────────────────────────────── */

function vestra_ai_camp_usage(): array { return vestra_read_json('ai_campaign_usage.json'); }

/** owner '' = admin (platform). Admin günlük/aylık sınıra tabi değil, platform tavanına tabi. */
function vestra_ai_camp_quota(string $owner): array {
  $L = vestra_ai_camp_limits(); $u = vestra_ai_camp_usage();
  $m = date('Y-m'); $d = date('Y-m-d');
  $mon = (array)($u[$m] ?? []);
  $platLeft = max(0, $L['platform_month'] - (int)($mon['calls'] ?? 0));
  $o = (array)(($mon['owners'] ?? [])[$owner === '' ? '_admin' : $owner] ?? []);
  $dayLeft = $owner === '' ? $platLeft : max(0, $L['per_day'] - (int)(($o['days'] ?? [])[$d] ?? 0));
  $monLeft = $owner === '' ? $platLeft : max(0, $L['per_month'] - (int)($o['calls'] ?? 0));
  $why = $platLeft <= 0 ? 'platform' : ($monLeft <= 0 ? 'month' : ($dayLeft <= 0 ? 'day' : ''));
  return ['ok' => $why === '', 'day_left' => min($dayLeft, $monLeft, $platLeft), 'month_left' => min($monLeft, $platLeft), 'why' => $why];
}

function vestra_ai_camp_count(string $owner, int $in, int $out): void {
  $u = vestra_ai_camp_usage(); $m = date('Y-m'); $d = date('Y-m-d'); $k = $owner === '' ? '_admin' : $owner;
  $mon = (array)($u[$m] ?? []);
  $mon['calls'] = (int)($mon['calls'] ?? 0) + 1;
  $mon['in'] = (int)($mon['in'] ?? 0) + $in; $mon['out'] = (int)($mon['out'] ?? 0) + $out;
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
 * $catalog: seçilebilecek ürünler (satıcıda kendi ilanları, adminde onaylı katalog).
 */
function vestra_ai_camp_generate(string $owner, string $senderName, array $catalog, array $in): array {
  if (!vestra_ai_camp_on()) return [false, 'nokey', null];
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
    ."- The subject line is under 70 characters and contains no emoji.";
  $user = "Language of the email: ".$langs[$lang]."\n"
    ."Style: ".($style === 'custom' ? ($custom !== '' ? $custom : 'Professional and clear.') : $styles[$style][1].($custom !== '' ? ' Also: '.$custom : ''))."\n"
    .($offer !== '' ? "Offer or note from the seller (state it exactly, add nothing): ".$offer."\n" : '')
    ."Sender name: ".($senderName !== '' ? $senderName : 'VESTRA')."\n"
    ."Products:\n".implode("\n", array_map(fn($p) => vestra_ai_camp_product_line($p, $prices), $sel));

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
    CURLOPT_HTTPHEADER => ['content-type: application/json', 'x-api-key: '.vestra_ai_camp_key(),
                           'anthropic-version: 2023-06-01', 'anthropic-beta: server-side-fallback-2026-07-01'],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
  ]);
  $raw = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  $d = is_string($raw) ? json_decode($raw, true) : null;
  if ($code !== 200 || !is_array($d)) {
    error_log('[VESTRA ai_campaign] HTTP '.$code.' '.mb_substr((string)($d['error']['message'] ?? $raw), 0, 200));
    return [false, 'api', null];
  }
  vestra_ai_camp_count($owner, (int)($d['usage']['input_tokens'] ?? 0), (int)($d['usage']['output_tokens'] ?? 0));
  $stop = (string)($d['stop_reason'] ?? '');
  if ($stop === 'refusal') return [false, 'refusal', null];
  if ($stop === 'max_tokens') return [false, 'toolong', null];
  $text = '';
  foreach ((array)($d['content'] ?? []) as $b) if (($b['type'] ?? '') === 'text') { $text .= (string)($b['text'] ?? ''); }
  $j = json_decode($text, true);
  $subject = trim((string)($j['subject'] ?? '')); $body = trim((string)($j['body'] ?? ''));
  if ($subject === '' || $body === '') return [false, 'parse', null];

  $camp = ['id' => 'AC'.strtoupper(bin2hex(random_bytes(4))), 'owner' => $owner, 'created_at' => date('c'),
           'style' => $style, 'lang' => $lang, 'subject' => mb_substr($subject, 0, 200), 'body' => mb_substr($body, 0, 6000),
           'products' => array_map(fn($p) => (string)($p['id'] ?? ''), $sel), 'active' => false];
  $all = vestra_ai_camp_all();
  array_unshift($all, $camp);
  /* Kişi başına son 20 kampanya. */
  $keep = []; $per = [];
  foreach ($all as $c) { $o = (string)($c['owner'] ?? ''); $per[$o] = ($per[$o] ?? 0) + 1; if ($per[$o] <= 20 || !empty($c['active'])) $keep[] = $c; }
  vestra_write_json('ai_campaigns.json', $keep);
  return [true, 'ok', $camp];
}
