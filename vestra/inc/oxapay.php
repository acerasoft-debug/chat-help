<?php
/**
 * VESTRA — OxaPay kripto ödeme (USDT, BTC, ETH…) + yaptırım engeli + tedarikçiye iletme kuyruğu.
 *
 * Operatör, 14 Eki 2026: banka havalesinin zor olduğu ülkeler (Güney Amerika, Türkiye,
 * Orta Doğu, Asya) için havaleye EK bir seçenek. Kredi kartı YOK (OxaPay desteklemiyor);
 * mevcut banka ve escrow akışına dokunulmuyor.
 *
 * Bu proje Laravel değil, düz PHP: "config/oxapay.php" = public_html'in ÜSTÜNDEKİ .env
 * (inc/env.php okuyor); "Order modeli" = orders.csv satırı + order_statuses.json kaydı;
 * "queue job" = data/supplier_queue.json, cron_mailbox.php her 10 dk işler; "DB transaction"
 * = data/oxapay.lock üzerinde flock. OxaPay aynı bildirimi 5 kez yollayabilir: her adım
 * idempotent, tedarikçiye TEK kez gider.
 *
 * ANAHTAR .env'DEN BAŞKA HİÇBİR YERDE DURMAZ: koda, loga, sohbete yazılmaz. Anahtar boşken
 * (oxapay_enabled() false) kasa kripto seçeneğini hiç çizmez ve /orders/{ref}/pay/crypto
 * 'not_configured' ile reddeder — kod yayında olsa bile müşteri bir şey görmez.
 *
 * .env anahtarları:
 *   OXAPAY_MERCHANT_API_KEY   (boş = kapalı)
 *   OXAPAY_SANDBOX=true       OXAPAY_LIFETIME=60   OXAPAY_FEE_PAID_BY_PAYER=1
 *   OXAPAY_UNDERPAID_COVERAGE=1   OXAPAY_TO_CURRENCY=USDT
 *   SUPPLIER_FORWARD_MODE=email|api   SUPPLIER_ORDER_EMAIL   SUPPLIER_API_URL   SUPPLIER_API_KEY
 *   SANCTIONED_COUNTRIES=RU,BY,IR,KP,SY,CU   (boş = bu varsayılan)
 *   SANCTIONED_REGIONS  (isteğe bağlı EK bölge/şehir adları, virgülle; varsayılan liste kalır)
 */
require_once __DIR__.'/env.php';

const VESTRA_OXAPAY_API          = 'https://api.oxapay.com/v1/payment/invoice';
const VESTRA_SANCTIONED_DEFAULT  = ['RU', 'BY', 'IR', 'KP', 'SY', 'CU'];
const VESTRA_SUPPLIER_TRIES      = 5;
const VESTRA_SUPPLIER_BACKOFF    = [60, 300, 900, 3600];
const VESTRA_OXAPAY_LOCK         = 'oxapay.lock';
const VESTRA_OXAPAY_INDEX        = 'oxapay_index.json';     // track_id → ref
const VESTRA_SUPPLIER_QUEUE      = 'supplier_queue.json';   // ref → iş

/* ── Ayar ─────────────────────────────────────────────────────────────────── */

function oxapay_env(string $k, string $def = ''): string {
    $v = getenv($k);
    if ($v === false || $v === '') $v = (string)($_ENV[$k] ?? $def);
    return trim($v);
}

function oxapay_cfg(): array {
    $lt = (int)oxapay_env('OXAPAY_LIFETIME', '60'); if ($lt <= 0) $lt = 60;
    return [
        'key'                 => oxapay_env('OXAPAY_MERCHANT_API_KEY'),
        'sandbox'             => in_array(strtolower(oxapay_env('OXAPAY_SANDBOX', 'true')), ['1', 'true', 'yes', 'on'], true),
        'lifetime'            => max(15, min(2880, $lt)),
        'fee_paid_by_payer'   => oxapay_env('OXAPAY_FEE_PAID_BY_PAYER', '1') === '0' ? 0 : 1,
        'under_paid_coverage' => (float)oxapay_env('OXAPAY_UNDERPAID_COVERAGE', '1'),
        'to_currency'         => strtoupper(oxapay_env('OXAPAY_TO_CURRENCY', 'USDT')) ?: 'USDT',
        'supplier_mode'       => strtolower(oxapay_env('SUPPLIER_FORWARD_MODE', 'email')) === 'api' ? 'api' : 'email',
        'supplier_email'      => oxapay_env('SUPPLIER_ORDER_EMAIL'),
        'supplier_api_url'    => oxapay_env('SUPPLIER_API_URL'),
        'supplier_api_key'    => oxapay_env('SUPPLIER_API_KEY'),
        /* SANDBOX'TA GERÇEK MEKTUP/API YOK (operatör, 14 Eki 2026): tedarikçi/satıcı iletimi ve
           müşteri bildirimleri bu adrese "[TEST]" başlığıyla gider; adres boşsa yalnız loglanır.
           Adres .env'de (OXAPAY_SANDBOX_TEST_EMAIL), kodda değil. */
        'test_email'          => oxapay_env('OXAPAY_SANDBOX_TEST_EMAIL'),
    ];
}

/** Bu sipariş sandbox faturasıyla mı açıldı? Kayıttaki damga esas; damga yoksa .env'deki kip. */
function oxapay_is_sandbox_order(array $entry, ?array $cfg = null): bool {
    if (array_key_exists('oxapay_sandbox', $entry)) return (bool)$entry['oxapay_sandbox'];
    return (bool)(($cfg ?? oxapay_cfg())['sandbox']);
}

/** Kripto seçeneği sunulabilir mi: anahtar .env'de dolu ve curl var. */
function oxapay_enabled(): bool {
    return oxapay_cfg()['key'] !== '' && function_exists('curl_init');
}

function oxapay_base_url(): string {
    $u = oxapay_env('VESTRA_BASE_URL', 'https://vestrasales.com');
    return rtrim($u, '/');
}

/* ── Yaptırım engeli ──────────────────────────────────────────────────────────
 * Teslimat ülkesi VEYA fatura ülkesi listedeyse, YA DA adres Kırım / Donetsk / Luhansk /
 * Zaporijya / Herson bölgesindeyse kripto seçeneği hiç çizilmez ve /pay/crypto reddeder.
 * Karar SUNUCUDA (KURAL 4b): kasadaki gizleme yalnız görünüm. */

function vestra_sanctioned_countries(): array {
    $raw = oxapay_env('SANCTIONED_COUNTRIES');
    if ($raw === '') return VESTRA_SANCTIONED_DEFAULT;
    $out = [];
    foreach (preg_split('/[,;\s]+/', strtoupper($raw)) ?: [] as $c) {
        if (preg_match('/^[A-Z]{2}$/', $c)) $out[] = $c;
    }
    return array_values(array_unique($out));
}

/** Ülke adı takma adları (küçük harf, aksansız). Kod listesi .env'den; ad listesi burada. */
function vestra_sanction_aliases(): array {
    return [
        'RU' => ['russia', 'russian federation', 'rossiya', 'rossija', 'rusya', 'russland', 'russie', 'rusia', 'россия', 'российская федерация', 'росія', 'روسيا'],
        'BY' => ['belarus', 'byelorussia', 'belorussia', 'republic of belarus', 'weissrussland', 'bielorussie', 'bielorrusia', 'bielorussia', 'беларусь', 'белоруссия', 'білорусь'],
        'IR' => ['iran', 'islamic republic of iran', 'ir iran', 'ایران', 'إيران'],
        'KP' => ['north korea', 'korea north', 'dprk', 'dpr korea', 'democratic peoples republic of korea', 'korea dpr', 'kuzey kore', 'nordkorea', 'coree du nord', 'corea del norte', 'coreia do norte', 'корейская народно демократическая республика', 'кндр', '조선민주주의인민공화국', '북한'],
        'SY' => ['syria', 'syrian arab republic', 'syrien', 'syrie', 'siria', 'suriye', 'سوريا', 'سورية', 'сирия'],
        'CU' => ['cuba', 'kuba', 'kuba', 'куба', 'كوبا'],
        /* Operatör .env'e başka kod eklerse ad eşlemesi vestra_cc_of_country()'den (security.php)
           gelir; burada yalnız varsayılan altı ülkenin yazımları var. */
    ];
}

/** UA'nın işgal altındaki bölgeleri + başlıca şehirleri (adres metninde kelime olarak aranır). */
function vestra_sanction_regions(): array {
    $base = ['crimea', 'krym', 'krim', 'crimee', 'крым', 'крим', 'sevastopol', 'севастополь', 'simferopol', 'симферополь', 'yalta', 'ялта', 'kerch', 'керчь',
             'donetsk', 'donezk', 'донецк', 'донецьк', 'mariupol', 'мариуполь', 'маріуполь', 'horlivka', 'gorlovka', 'makiivka', 'makeyevka',
             'luhansk', 'lugansk', 'луганск', 'луганськ', 'alchevsk', 'sievierodonetsk', 'severodonetsk',
             'zaporizhzhia', 'zaporizhia', 'zaporozhye', 'zaporozhe', 'zaporijya', 'zaporijia', 'saporischschja', 'запорожье', 'запоріжжя', 'melitopol', 'мелитополь', 'мелітополь', 'berdyansk', 'berdiansk', 'бердянск', 'енергодар', 'energodar', 'enerhodar',
             'kherson', 'cherson', 'херсон', 'nova kakhovka', 'новая каховка', 'нова каховка', 'henichesk', 'геническ'];
    $raw = oxapay_env('SANCTIONED_REGIONS');
    if ($raw !== '') foreach (preg_split('/[,;]+/', $raw) ?: [] as $r) { $r = vestra_sanction_fold($r); if ($r !== '') $base[] = $r; }
    return array_values(array_unique($base));
}

/** Küçük harf, Latin aksanları düşmüş, noktalama → boşluk, tek boşluk. */
function vestra_sanction_fold(string $s): string {
    $s = mb_strtolower(trim($s));
    $s = strtr($s, ['ü' => 'u', 'ö' => 'o', 'ä' => 'a', 'ß' => 'ss', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'á' => 'a', 'à' => 'a', 'í' => 'i', 'ı' => 'i', 'ó' => 'o', 'ú' => 'u', 'ç' => 'c', 'ş' => 's', 'ğ' => 'g', 'ñ' => 'n', 'ё' => 'е', 'ї' => 'і', '’' => '', "'" => '']);
    $s = (string)preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s);
    return trim((string)preg_replace('/\s+/u', ' ', $s));
}

/** Serbest ülke metni → ISO2 ('' = tanınmadı). Çıplak kod, takma ad, sonda "(RU)" biçimi. */
function vestra_sanction_cc(string $raw): string {
    $raw = trim($raw);
    if ($raw === '') return '';
    if (preg_match('/^[A-Za-z]{2}$/', $raw)) return strtoupper($raw);
    if (preg_match('/\(([A-Za-z]{2})\)\s*$/', $raw, $m)) return strtoupper($m[1]);
    $f = vestra_sanction_fold($raw);
    foreach (vestra_sanction_aliases() as $cc => $names) if (in_array($f, $names, true)) return $cc;
    if (function_exists('vestra_cc_of_country')) { $cc = vestra_cc_of_country($raw); if ($cc !== '') return $cc; }
    return '';
}

/**
 * Metin (adres satırı) içinde yaptırımlı ülke adı ya da bölge geçiyor mu — kelime olarak.
 * @return string  'country:RU' | 'region:crimea' | ''
 */
function vestra_sanction_scan(string $text): string {
    $f = vestra_sanction_fold($text);
    if ($f === '') return '';
    $hit = fn(string $needle) => (bool)preg_match('/(?<![\p{L}\p{N}])'.preg_quote($needle, '/').'(?![\p{L}\p{N}])/u', $f);
    $list = vestra_sanctioned_countries();
    foreach (vestra_sanction_aliases() as $cc => $names) {
        if (!in_array($cc, $list, true)) continue;
        foreach ($names as $n) if ($hit($n)) return 'country:'.$cc;
    }
    foreach (vestra_sanction_regions() as $r) if ($hit($r)) return 'region:'.$r;
    return '';
}

/**
 * Kripto için engel nedeni. Fatura ülkesi, teslimat ülkesi, teslimat ve fatura adres metinleri.
 * @return string '' = serbest, aksi halde 'country:XX' | 'region:…'
 */
function vestra_crypto_block_reason(string $billCountry, string $shipCountry = '', string $shipAddress = '', string $billAddress = ''): string {
    $list = vestra_sanctioned_countries();
    foreach ([$billCountry, $shipCountry] as $c) {
        $cc = vestra_sanction_cc($c);
        if ($cc !== '' && in_array($cc, $list, true)) return 'country:'.$cc;
    }
    foreach ([$shipAddress, $billAddress, $shipCountry, $billCountry] as $txt) {
        $r = vestra_sanction_scan($txt);
        if ($r !== '') return $r;
    }
    return '';
}

/** Kayıtlı sipariş için engel: satırın ülkesi + notlardaki "Deliver to:" + hesabın fatura adresi. */
function vestra_order_crypto_block(array $row): string {
    $ship = ''; $bill = '';
    if (function_exists('vestra_order_delivery_address')) $ship = vestra_order_delivery_address((string)($row['notes'] ?? ''));
    if (function_exists('vestra_order_buyer_account') && function_exists('vestra_account_billing_line')) {
        $acc = vestra_order_buyer_account($row);
        if ($acc) { $bill = vestra_account_billing_line($acc).' '.(string)($acc['country'] ?? ''); }
    }
    return vestra_crypto_block_reason((string)($row['country'] ?? ''), '', $ship, $bill);
}

/** Kasa JS'i için: yaptırımlı ad/bölge kalıbı (yalnız görünüm; karar sunucuda). */
function vestra_sanction_js_pattern(): string {
    $parts = [];
    $list = vestra_sanctioned_countries();
    foreach (vestra_sanction_aliases() as $cc => $names) if (in_array($cc, $list, true)) foreach ($names as $n) $parts[] = preg_quote($n, '/');
    foreach (vestra_sanction_regions() as $r) $parts[] = preg_quote($r, '/');
    foreach ($list as $cc) $parts[] = '\('.$cc.'\)';
    return '(?<![\p{L}\p{N}])(?:'.implode('|', $parts).')(?![\p{L}\p{N}])';
}

/* ── Kilit + dosyalar ─────────────────────────────────────────────────────── */

function oxapay_lock(): mixed {
    $h = @fopen(vestra_data_dir().'/'.VESTRA_OXAPAY_LOCK, 'c');
    if ($h) flock($h, LOCK_EX);
    return $h;
}
function oxapay_unlock(mixed $h): void { if ($h) { flock($h, LOCK_UN); fclose($h); } }

function oxapay_ref_for_track(string $track): string {
    $track = trim($track);
    if ($track === '') return '';
    $idx = vestra_read_json(VESTRA_OXAPAY_INDEX);
    if (isset($idx[$track])) return (string)$idx[$track];
    foreach (vestra_read_json('order_statuses.json') as $ref => $e) {
        if ((string)(($e ?? [])['oxapay_track_id'] ?? '') === $track) return (string)$ref;
    }
    return '';
}

function oxapay_order_row(string $ref): ?array {
    foreach (vestra_read_csv('orders.csv') as $r) if (($r['ref'] ?? '') === $ref) return $r;
    return null;
}

/* ── Fatura (invoice) oluşturma ───────────────────────────────────────────── */

/** @return array{0:int,1:?array}  [HTTP kodu, çözülmüş JSON] */
function oxapay_http(string $url, array $body, array $headers): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $raw = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($raw === false) { $err = curl_error($ch); curl_close($ch); return [0, ['error' => ['message' => $err]]]; }
    curl_close($ch);
    return [$code, is_string($raw) ? json_decode($raw, true) : null];
}

/**
 * OxaPay faturası oluşturur, siparişe kaydeder (payment_method=oxapay, payment_status=pending,
 * oxapay_track_id) ve payment_url döner. Ödenmiş siparişte reddeder.
 * $opt['http'] : fn(url, body, headers) => [code, json]  (test enjekte eder)
 * @return array{ok:bool,track_id:string,payment_url:string,error:string,body?:array}
 */
function oxapay_create_invoice(array $row, array $opt = []): array {
    $cfg = $opt['cfg'] ?? oxapay_cfg();
    $ref = (string)($row['ref'] ?? '');
    $out = ['ok' => false, 'track_id' => '', 'payment_url' => '', 'error' => ''];
    if ($ref === '') { $out['error'] = 'no_ref'; return $out; }
    if ($cfg['key'] === '') { $out['error'] = 'not_configured'; return $out; }
    $amount = round((float)($row['total'] ?? 0), 2);
    if ($amount <= 0) { $out['error'] = 'amount'; return $out; }
    if (function_exists('vestra_order_payment_settled') && vestra_order_payment_settled($ref)['settled']) { $out['error'] = 'already_paid'; return $out; }
    if (($reason = vestra_order_crypto_block($row)) !== '') { $out['error'] = 'sanctioned'; $out['reason'] = $reason; return $out; }

    $base = oxapay_base_url();
    $body = [
        'amount'              => $amount,
        'currency'            => 'EUR',
        'lifetime'            => (int)$cfg['lifetime'],
        'fee_paid_by_payer'   => (int)$cfg['fee_paid_by_payer'],
        'under_paid_coverage' => (float)$cfg['under_paid_coverage'],
        'to_currency'         => (string)$cfg['to_currency'],
        'callback_url'        => $base.'/oxapay/webhook',
        'return_url'          => $base.'/orders/'.rawurlencode($ref).'/payment-return',
        'email'               => (string)($row['email'] ?? ''),
        'order_id'            => $ref,
        'description'         => 'VESTRA order '.$ref.(($row['company'] ?? '') !== '' ? ' — '.mb_substr((string)$row['company'], 0, 60) : ''),
        'thanks_message'      => 'Thank you — VESTRA has received your payment for order '.$ref.'.',
        'sandbox'             => (bool)$cfg['sandbox'],
    ];
    $out['body'] = $body;
    $http = $opt['http'] ?? 'oxapay_http';
    [$code, $json] = $http(VESTRA_OXAPAY_API, $body, ['merchant_api_key: '.$cfg['key'], 'Content-Type: application/json', 'Accept: application/json']);
    $track = trim((string)($json['data']['track_id'] ?? ''));
    $url   = trim((string)($json['data']['payment_url'] ?? ''));
    if ($code !== 200 || $track === '' || $url === '' || !preg_match('#^https://#i', $url)) {
        $msg = (string)($json['error']['message'] ?? ($json['message'] ?? ''));
        $out['error'] = 'api:'.$code.($msg !== '' ? ':'.mb_substr($msg, 0, 120) : '');
        error_log('[VESTRA OxaPay] invoice create failed '.$ref.' HTTP '.$code.' '.$msg);
        return $out;
    }
    $h = oxapay_lock();
    $all = vestra_read_json('order_statuses.json');
    $e = (array)($all[$ref] ?? ['status' => 'pending']);
    $e['payment_method']  = 'oxapay';
    if (($e['payment_status'] ?? '') !== 'paid') $e['payment_status'] = 'pending';
    $e['oxapay_track_id'] = $track;
    $e['oxapay_payment_url'] = $url;
    $e['oxapay_sandbox']  = (bool)$cfg['sandbox'];
    $e['oxapay_created_at'] = date('c');
    $e['updated_at'] = date('c');
    $all[$ref] = $e;
    vestra_write_json('order_statuses.json', $all);
    $idx = vestra_read_json(VESTRA_OXAPAY_INDEX); $idx[$track] = $ref; vestra_write_json(VESTRA_OXAPAY_INDEX, $idx);
    oxapay_unlock($h);
    $out['ok'] = true; $out['track_id'] = $track; $out['payment_url'] = $url;
    return $out;
}

/* ── Webhook ──────────────────────────────────────────────────────────────── */

/** HMAC başlığı == hash_hmac('sha512', ham gövde, merchant key); hash_equals ile. */
function oxapay_verify_webhook(string $rawBody, string $hmacHeader, string $key = ''): bool {
    if ($key === '') $key = oxapay_cfg()['key'];
    $hmacHeader = strtolower(trim($hmacHeader));
    if ($key === '' || $hmacHeader === '' || $rawBody === '') return false;
    return hash_equals(hash_hmac('sha512', $rawBody, $key), $hmacHeader);
}

/**
 * Doğrulanmış webhook yükünü işler. Her zaman ['ok'=>true, 'action'=>…] döner (uç 200 'ok' basar).
 * Paying/Confirming → payment_status=confirming; Paid (henüz değilse) → paid, paid_at, sipariş
 * durumu 'paid' (ileri aşamayı geri almaz), payload, tedarikçi kuyruğu; Expired/Failed → durum.
 * Yan etkiler (mektuplar) kilit bırakıldıktan SONRA (afterCommit).
 * $opt['now'] (int), $opt['mail'] (fn(to,subj,body)) — test enjekte eder.
 */
function oxapay_handle_webhook(array $p, array $opt = []): array {
    $type = strtolower(trim((string)($p['type'] ?? '')));
    if (!in_array($type, ['invoice', 'white_label'], true)) return ['ok' => true, 'action' => 'ignored_type'];
    $track = trim((string)($p['track_id'] ?? ''));
    $ref   = oxapay_ref_for_track($track);
    if ($ref === '') return ['ok' => true, 'action' => 'unknown_track'];
    $now = (int)($opt['now'] ?? time());
    $status = strtolower(trim((string)($p['status'] ?? '')));

    $h = oxapay_lock();
    $all = vestra_read_json('order_statuses.json');
    $e = (array)($all[$ref] ?? ['status' => 'pending']);
    if ((string)($e['oxapay_track_id'] ?? '') !== $track) { oxapay_unlock($h); return ['ok' => true, 'action' => 'track_mismatch']; }
    $action = 'noted';
    $wasPaid = ($e['payment_status'] ?? '') === 'paid';
    $e['oxapay_last_status'] = $status;
    $e['oxapay_last_webhook_at'] = date('c', $now);
    switch ($status) {
        case 'paying': case 'confirming':
            if (!$wasPaid) { $e['payment_status'] = 'confirming'; $action = 'confirming'; }
            break;
        case 'paid':
            if ($wasPaid) { $action = 'already_paid'; break; }
            $e['payment_status'] = 'paid';
            $e['paid_at'] = date('c', $now);
            $e['oxapay_payload'] = oxapay_trim_payload($p);
            $iNow = array_search((string)($e['status'] ?? 'pending'), VESTRA_ORDER_STEPS, true);
            $iPaid = array_search('paid', VESTRA_ORDER_STEPS, true);
            if ($iNow === false || $iNow < $iPaid) {
                $e['status'] = 'paid';
                $e['history'][] = vestra_order_history_entry('paid', 'oxapay', 'crypto '.(string)($p['pay_currency'] ?? $p['currency'] ?? ''));
            }
            $e['fulfillment_status'] = $e['fulfillment_status'] ?? 'pending';
            $action = 'paid';
            break;
        case 'expired': case 'failed': case 'refunded':
            if (!$wasPaid) { $e['payment_status'] = $status; $action = $status; }
            break;
    }
    $e['updated_at'] = date('c', $now);
    $all[$ref] = $e;
    vestra_write_json('order_statuses.json', $all);
    if ($action === 'paid') vestra_supplier_enqueue($ref, $now);   // aynı kilit altında: ikinci 'paid' asla ikinci iş açmaz
    oxapay_unlock($h);

    if ($action === 'paid') { try { oxapay_after_paid($ref, $opt); } catch (\Throwable $x) { error_log('[VESTRA OxaPay] after-paid '.$ref.': '.$x->getMessage()); } }
    return ['ok' => true, 'action' => $action, 'ref' => $ref];
}

/** Webhook yükünden saklanacak kısım (anahtar/uzun alan yok). */
function oxapay_trim_payload(array $p): array {
    $keep = ['track_id', 'type', 'status', 'amount', 'currency', 'value', 'pay_currency', 'pay_amount', 'paid_amount', 'network', 'fee', 'rate', 'txs', 'date', 'order_id', 'sandbox'];
    $out = [];
    foreach ($keep as $k) if (array_key_exists($k, $p)) $out[$k] = is_array($p[$k]) ? array_slice($p[$k], 0, 10) : mb_substr((string)$p[$k], 0, 200);
    return $out;
}

/** Ödeme geldi: alıcıya "alındı", satıcıya "gönder", operatöre bildirim, askı kalkar, komisyon. */
function oxapay_after_paid(string $ref, array $opt = []): void {
    $row = oxapay_order_row($ref);
    if (!$row) return;
    $mail = $opt['mail'] ?? (function_exists('vestra_send_mail') ? 'vestra_send_mail' : null);
    $who = ($row['name'] ?? '') ?: (($row['company'] ?? '') ?: 'there');
    $cfg = $opt['cfg'] ?? oxapay_cfg();
    $entry = (array)((vestra_read_json('order_statuses.json'))[$ref] ?? []);
    if (oxapay_is_sandbox_order($entry, $cfg)) {
        /* SANDBOX: müşteriye, satıcıya, operatöre HİÇBİR gerçek bildirim yok; komisyon çekilmez,
           askı açılmaz. Test adresi varsa tek özet mektup "[TEST]" ile, yoksa yalnız log. */
        $msg = "[TEST] sandbox payment marked paid for order {$ref} (".(string)($row['company'] ?? '')."), €".number_format((float)($row['total'] ?? 0), 2)."\nNo buyer/seller/admin notification was sent, no commission charged.";
        error_log('[VESTRA OxaPay] '.$msg);
        if ($mail && $cfg['test_email'] !== '') $mail($cfg['test_email'], "[TEST] VESTRA sandbox — crypto payment received for {$ref}", $msg."\n\n— VESTRA sandbox");
        return;
    }
    if ($mail && !empty($row['email'])) {
        $mail($row['email'], "VESTRA — payment received for order {$ref}",
            "Hello {$who},\n\nYour crypto payment for order {$ref} has been confirmed. The seller is preparing your shipment — you'll get another email with tracking once it ships.\n\nTrack your order: https://vestrasales.com/buyer?tab=orders\n\n— VESTRA · vestrasales.com");
    }
    if ($mail && function_exists('vestra_order_lines') && function_exists('auth_accounts')) {
        $done = [];
        foreach ((vestra_order_lines($row))['lines'] as $l) {
            $sid = (string)($l['seller_uid'] ?? '');
            if ($sid === '' || in_array($sid, $done, true)) continue;
            $done[] = $sid;
            foreach (auth_accounts() as $acc) {
                if (($acc['id'] ?? '') !== $sid || empty($acc['email'])) continue;
                $mail($acc['email'], "VESTRA — order {$ref} is paid, please ship",
                    "Hello ".(($acc['name'] ?? '') ?: (($acc['company'] ?? '') ?: 'there')).",\n\nOrder {$ref} has been paid (crypto, via VESTRA). Please ship the goods and mark the order as shipped in your panel (with tracking if available):\nhttps://vestrasales.com/seller?tab=orders\n\n— VESTRA · vestrasales.com");
                break;
            }
        }
    }
    if (empty($opt['quiet'])) {
        if (function_exists('vestra_notify')) { try { vestra_notify("Crypto payment received — order {$ref}", "Order {$ref} ({$row['company']}) paid via OxaPay: €".number_format((float)$row['total'], 2)."\nhttps://vestrasales.com/admin?tab=orders"); } catch (\Throwable $x) {} }
        if (function_exists('vestra_charge_order_commission') && function_exists('vestra_order_lines')) { try { vestra_charge_order_commission($ref, vestra_order_lines($row)['lines']); } catch (\Throwable $x) { error_log('[VESTRA OxaPay] commission '.$ref.': '.$x->getMessage()); } }
        if (is_readable(__DIR__.'/unpaid_hold.php')) { require_once __DIR__.'/unpaid_hold.php'; if (function_exists('vestra_unpaid_hold_reopen_for_ref')) { try { vestra_unpaid_hold_reopen_for_ref($ref); } catch (\Throwable $x) {} } }
    }
}

/* ── Tedarikçiye iletme kuyruğu (ForwardOrderToSupplier) ──────────────────── */

function vestra_supplier_enqueue(string $ref, ?int $now = null): void {
    $now ??= time();
    $q = vestra_read_json(VESTRA_SUPPLIER_QUEUE);
    if (isset($q[$ref])) return;   // idempotent: iş zaten var (bekliyor, bitti ya da tükendi)
    $q[$ref] = ['ref' => $ref, 'created_at' => date('c', $now), 'attempts' => 0, 'next_at' => $now, 'done_at' => '', 'failed_at' => '', 'last_error' => ''];
    vestra_write_json(VESTRA_SUPPLIER_QUEUE, $q);
}

/** Tedarikçi yükü: referans VESTRA-{ref}, teslimat adresi, kalemler (supplier_sku/sku, ad, beden, adet). */
function vestra_supplier_payload(array $row): array {
    $ref = (string)($row['ref'] ?? '');
    $ship = ['line' => '', 'country' => (string)($row['country'] ?? '')];
    if (function_exists('vestra_order_ship_to')) { try { $ship = vestra_order_ship_to($row); } catch (\Throwable $x) {} }
    $items = [];
    $ld = function_exists('vestra_order_lines') ? vestra_order_lines($row) : ['lines' => []];
    foreach ($ld['lines'] as $l) {
        $lst = function_exists('vestra_listing_by_sku') ? vestra_listing_by_sku((string)$l['sku']) : null;
        $items[] = [
            'supplier_sku' => (string)(($lst['supplier_sku'] ?? '') ?: $l['sku']),
            'sku'          => (string)$l['sku'],
            'name'         => trim((string)($l['brand'] ?? '').' '.(string)($l['name'] ?? '')),
            'size'         => implode(', ', (array)($l['sizes'] ?? [])),
            'colors'       => implode(', ', (array)($l['colors'] ?? [])),
            'qty'          => (int)$l['qty'],
        ];
    }
    return [
        'reference'        => 'VESTRA-'.$ref,
        'order_ref'        => $ref,
        'placed_at'        => (string)($row['timestamp'] ?? ''),
        'buyer_company'    => (string)($row['company'] ?? ''),
        'buyer_contact'    => (string)($row['name'] ?? ''),
        'buyer_phone'      => (string)($row['phone'] ?? ''),
        'delivery_address' => (string)($ship['line'] ?? ''),
        'delivery_country' => (string)($ship['country'] ?? ''),
        'items'            => $items,
        'notes'            => (string)($ld['notes'] ?? ''),
    ];
}

function vestra_supplier_mail_body(array $pl): string {
    $b = "New paid order to fulfil — {$pl['reference']}\n\n";
    $b .= "Deliver to:\n  ".($pl['delivery_address'] !== '' ? $pl['delivery_address'] : '(address on file — see VESTRA panel)')."\n";
    $b .= "Buyer: {$pl['buyer_company']}".($pl['buyer_contact'] !== '' ? " — {$pl['buyer_contact']}" : '').($pl['buyer_phone'] !== '' ? " · {$pl['buyer_phone']}" : '')."\n\nItems:\n";
    foreach ($pl['items'] as $it) {
        $b .= "  {$it['qty']} × {$it['supplier_sku']}".($it['supplier_sku'] !== $it['sku'] ? " (VESTRA SKU {$it['sku']})" : '')." — {$it['name']}"
            .($it['size'] !== '' ? " · sizes: {$it['size']}" : '').($it['colors'] !== '' ? " · colours: {$it['colors']}" : '')."\n";
    }
    if ($pl['notes'] !== '') $b .= "\nNotes: {$pl['notes']}\n";
    $b .= "\nOrder ref: {$pl['order_ref']} · placed {$pl['placed_at']}\n— VESTRA · vestrasales.com";
    return $b;
}

/**
 * Kuyruğu işler (cron, her 10 dk). En çok 5 deneme, bekleme 60/300/900/3600 sn.
 * supplier_forwarded_at doluysa iş kapanır. Mod api: Bearer ile POST, 4xx/5xx = hata (yeniden dener);
 * mod email: SUPPLIER_ORDER_EMAIL doluysa oraya, boşsa her satıcıya kendi satırları.
 * $opt: now, http (fn(url, body, headers) => [code, json]), mail (fn(to, subj, body) => bool), log (fn(string)).
 * @return array{sent:int,retried:int,failed:int,skipped:int}
 */
function vestra_supplier_forward_run(array $opt = []): array {
    $now  = (int)($opt['now'] ?? time());
    $cfg  = $opt['cfg'] ?? oxapay_cfg();
    $http = $opt['http'] ?? 'oxapay_http';
    $mail = $opt['mail'] ?? (function_exists('vestra_send_mail') ? 'vestra_send_mail' : null);
    $log  = $opt['log'] ?? static function (string $m): void {};
    $sum  = ['sent' => 0, 'retried' => 0, 'failed' => 0, 'skipped' => 0];
    $q = vestra_read_json(VESTRA_SUPPLIER_QUEUE);
    if (!$q) return $sum;
    $touched = [];
    foreach ($q as $ref => $job) {
        if (!empty($job['done_at']) || !empty($job['failed_at'])) continue;
        if ((int)($job['next_at'] ?? 0) > $now) continue;
        $st = (array)((vestra_read_json('order_statuses.json'))[$ref] ?? []);
        $touched[] = $ref;
        if (!empty($st['supplier_forwarded_at'])) { $q[$ref]['done_at'] = date('c', $now); $sum['skipped']++; continue; }
        $err = '';
        try {
            $row = oxapay_order_row((string)$ref);
            if (!$row) throw new \RuntimeException('order row not found');
            $pl = vestra_supplier_payload($row);
            if (!$pl['items']) throw new \RuntimeException('no items');
            if (oxapay_is_sandbox_order($st, $cfg)) {
                /* SANDBOX: tedarikçiye/satıcıya GERÇEK e-posta ya da API isteği YOK. Test adresi
                   varsa yük oraya "[TEST]" ile, yoksa yalnız log; iş yine 'iletildi' kapanır. */
                if ($mail && $cfg['test_email'] !== '') {
                    if (!$mail($cfg['test_email'], "[TEST] VESTRA sandbox — order to fulfil {$pl['reference']}", "[TEST] Sandbox order — nothing was sent to a supplier or seller.\n\n".vestra_supplier_mail_body($pl))) throw new \RuntimeException('test mail failed');
                    $via = 'sandbox-test-mail';
                } else { $via = 'sandbox-log'; }
                $log("[supplier] {$ref} SANDBOX — gercek iletim yok ({$via}); yuk: ".json_encode(['reference' => $pl['reference'], 'items' => count($pl['items'])]));
            } elseif ($cfg['supplier_mode'] === 'api') {
                if ($cfg['supplier_api_url'] === '') throw new \RuntimeException('SUPPLIER_API_URL empty');
                [$code, $json] = $http($cfg['supplier_api_url'], $pl, ['Authorization: Bearer '.$cfg['supplier_api_key'], 'Content-Type: application/json', 'Accept: application/json']);
                if ($code < 200 || $code >= 300) throw new \RuntimeException('supplier api HTTP '.$code);
                $via = 'api';
            } else {
                if (!$mail) throw new \RuntimeException('no mailer');
                $targets = [];
                if ($cfg['supplier_email'] !== '') $targets[$cfg['supplier_email']] = $pl;
                else {
                    /* Satıcı başına kendi satırları (order.php'nin satıcı bildirimiyle aynı ilke:
                       iki satıcılı sepette biri ötekinin kalemini görmez). */
                    $ld = function_exists('vestra_order_lines') ? vestra_order_lines($row)['lines'] : [];
                    $accs = function_exists('auth_accounts') ? auth_accounts() : [];
                    foreach ($ld as $i => $l) {
                        $sid = (string)($l['seller_uid'] ?? '');
                        $to = '';
                        foreach ($accs as $a) if (($a['id'] ?? '') === $sid && !empty($a['email'])) { $to = (string)$a['email']; break; }
                        if ($to === '') continue;
                        if (!isset($targets[$to])) { $targets[$to] = $pl; $targets[$to]['items'] = []; }
                        $targets[$to]['items'][] = $pl['items'][$i];
                    }
                }
                if (!$targets) throw new \RuntimeException('no supplier address (SUPPLIER_ORDER_EMAIL empty, no seller email)');
                foreach ($targets as $to => $tpl) {
                    if (!$mail($to, "VESTRA — order to fulfil {$tpl['reference']}", vestra_supplier_mail_body($tpl))) throw new \RuntimeException('mail failed');
                }
                $via = 'email';
            }
        } catch (\Throwable $x) { $err = $x->getMessage(); }

        if ($err === '') {
            $h = oxapay_lock();
            $all = vestra_read_json('order_statuses.json');
            $e = (array)($all[$ref] ?? ['status' => 'paid']);
            $e['supplier_forwarded_at'] = date('c', $now);
            $e['fulfillment_status'] = 'sent_to_supplier';
            $e['supplier_forward_via'] = $via;
            $e['history'][] = vestra_order_history_entry('sent_to_supplier', 'system', 'forwarded via '.$via);
            $all[$ref] = $e; vestra_write_json('order_statuses.json', $all);
            oxapay_unlock($h);
            $q[$ref]['done_at'] = date('c', $now); $q[$ref]['attempts'] = (int)$job['attempts'] + 1; $q[$ref]['last_error'] = '';
            $sum['sent']++; $log("[supplier] {$ref} iletildi ({$via})");
        } else {
            $n = (int)($job['attempts'] ?? 0) + 1;
            $q[$ref]['attempts'] = $n; $q[$ref]['last_error'] = mb_substr($err, 0, 200);
            if ($n >= VESTRA_SUPPLIER_TRIES) {
                $q[$ref]['failed_at'] = date('c', $now); $sum['failed']++;
                $log("[supplier] {$ref} VAZGECILDI ({$n} deneme): {$err}");
                if (empty($opt['quiet']) && function_exists('vestra_notify')) { try { vestra_notify("Supplier forward FAILED — order {$ref}", "Order {$ref} could not be forwarded to the supplier after {$n} attempts.\nLast error: {$err}\n\nhttps://vestrasales.com/admin?tab=orders"); } catch (\Throwable $x) {} }
            } else {
                $wait = VESTRA_SUPPLIER_BACKOFF[min($n - 1, count(VESTRA_SUPPLIER_BACKOFF) - 1)];
                $q[$ref]['next_at'] = $now + $wait; $sum['retried']++;
                $log("[supplier] {$ref} deneme {$n} basarisiz ({$err}) — {$wait} sn sonra");
            }
        }
    }
    if ($touched) {
        /* Kilit altında TAZE oku ve yalnız dokunulan işleri yaz: işlem sürerken webhook'un
           açtığı yeni bir iş, bayat kopyayla ezilmesin. */
        $h = oxapay_lock();
        $fresh = vestra_read_json(VESTRA_SUPPLIER_QUEUE);
        foreach ($touched as $ref) $fresh[$ref] = $q[$ref];
        vestra_write_json(VESTRA_SUPPLIER_QUEUE, $fresh);
        oxapay_unlock($h);
    }
    return $sum;
}
