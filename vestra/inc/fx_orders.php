<?php
/**
 * VESTRA — siparişin USD karşılığı, SİPARİŞ TARİHİNDEKİ kurla (operatör isteği,
 * 7 Eyl 2026: "siparişleri anında sipariş zamanındaki kur ile USD'ye çevirecek
 * bir sistem koy admin paneline").
 *
 * İKİ AYRI KUR VARDIR ve karıştırılmaz:
 *   - Vitrin kuru (inc/money.php, vestra_fx): BUGÜNÜN kuru, ziyaretçiye fiyat
 *     göstermek için. Her gün değişir.
 *   - Sipariş kuru (bu dosya): siparişin VERİLDİĞİ günün kuru, kayda bir kez
 *     yazılır ve bir daha değişmez. Muhasebe bunu okur; bugünkü kurla çevrilen
 *     bir Ağustos siparişi, o gün alınan paranın karşılığı değildir.
 *
 * TEK KAYNAK: order_statuses.json[ref]['fx'] = ['usd'=>1.0842, 'date'=>'Y-m-d',
 * 'source'=>'ecb|market|manual', 'stamped_at'=>...]. Panel, dosya, CSV ve
 * toplamlar bu damgayı okur; damga yoksa "—" basılır, TAHMİN EDİLMEZ (KURAL 3'ün
 * kur hali: boş alanı bugünün kuruyla doldurmak, yasaklanan şeyin ta kendisi).
 *
 * Damga nasıl düşer:
 *   1) Sipariş yazılırken (order.php, vestra_offer_order_ensure) o anki vitrin
 *      kuruyla — ağa çıkmaz, money.php'nin zaten günlük yenilediği önbellekten.
 *   2) Damgasız eski siparişler için: frankfurter'in TARİH ARALIĞI ucu
 *      (ECB referans kurları) TEK istekle çekilir, data/fx_history.json'a
 *      yazılır, her sipariş kendi tarihinin kuruyla damgalanır. Hafta sonu /
 *      tatil günü ECB kur yayımlamaz; o gün yürürlükte olan kur bir önceki
 *      yayım günününküdür ve damga o tarihi yazar — sipariş tarihini değil.
 *      Bu geri doldurma admin sipariş sekmesi açılınca kendiliğinden bir kez
 *      denenir ("anında"); uç kapalıysa yarım saat tekrar denenmez (money.php
 *      ile aynı geri çekilme ilkesi) ve düğme de var.
 */
require_once __DIR__.'/money.php';     // _vestra_fx_get, _vsec_read/_vsec_write, vestra_fx_state
require_once __DIR__.'/products.php';  // vestra_read_json / vestra_write_json

const VESTRA_FX_HISTORY_FILE = 'fx_history.json';
/* ECB kur yayımlamayan gün (hafta sonu, tatil) için en fazla bu kadar gün
   geriye bakılır. 5, Noel–Yılbaşı gibi en uzun ECB tatil köprüsünü karşılar. */
const VESTRA_FX_LOOKBACK_DAYS = 5;

/** Kayıtlı damga, yoksa null. Ağa çıkmaz. */
function vestra_order_fx(string $ref): ?array {
    $all = vestra_read_json('order_statuses.json');
    $fx = $all[$ref]['fx'] ?? null;
    if (!is_array($fx) || (float)($fx['usd'] ?? 0) <= 0) return null;
    return $fx;
}

/** EUR tutar × damgalı kur, 2 hane. Damga yoksa null — asla bugünün kuru değil. */
function vestra_order_usd(array $orderRow, ?array $fx = null): ?float {
    $fx = $fx ?? vestra_order_fx((string)($orderRow['ref'] ?? ''));
    if ($fx === null) return null;
    $eur = (float)str_replace(',', '.', (string)($orderRow['total'] ?? ''));
    if ($eur <= 0) return null;
    return round($eur * (float)$fx['usd'], 2);
}

function vestra_usd(float $v): string { return 'US$'.number_format($v, 2); }

/** Kur kaynağı etiketi. ECB olmayan kura "ECB" demek uydurma kurun kibarcası
 *  (money.php'nin kendi kuralı); etiket damgadan gelir. */
function vestra_fx_source_label(string $src): string {
    return match ($src) { 'ecb' => 'ECB', 'market' => 'market rate', 'manual' => 'manual rate', default => 'rate' };
}

/**
 * Geçmiş tablosunda bir tarihin kuru: o gün yoksa en fazla VESTRA_FX_LOOKBACK_DAYS
 * geriye. ['usd'=>x, 'date'=>'yayım günü'] ya da null. SAF: ağ yok, disk yok —
 * test edilebilir olsun diye tablo parametre.
 */
function vestra_fx_history_lookup(array $hist, string $date): ?array {
    $ts = strtotime($date);
    if (!$ts) return null;
    for ($i = 0; $i <= VESTRA_FX_LOOKBACK_DAYS; $i++) {
        $d = date('Y-m-d', $ts - $i * 86400);
        if (!empty($hist[$d]) && (float)$hist[$d] > 0) return ['usd' => (float)$hist[$d], 'date' => $d];
    }
    return null;
}

/** frankfurter aralık ucu: tek istekte bir tarih aralığının ECB EUR→USD kurları. */
function vestra_fx_history_url(string $from, string $to): string {
    return 'https://api.frankfurter.app/'.$from.'..'.$to.'?from=EUR&to=USD';
}

/**
 * Aralığı çekip geçmiş tablosuna ekler. Başarısızlıkta fail_ts yazar (30 dk geri
 * çekilme). Hafta sonu için aralığın başı LOOKBACK kadar öne alınır ki
 * Pazartesi'ye kadar bakılınca Cuma'nın kuru tabloda olsun.
 */
function vestra_fx_history_fetch(string $from, string $to): bool {
    $hist = _vsec_read(VESTRA_FX_HISTORY_FILE);
    if ((time() - (int)($hist['_fail_ts'] ?? 0)) < 1800) return false;
    $fromTs = strtotime($from) - VESTRA_FX_LOOKBACK_DAYS * 86400;
    $raw = _vestra_fx_get(vestra_fx_history_url(date('Y-m-d', $fromTs), $to));
    $d = $raw !== null ? json_decode($raw, true) : null;
    if (!is_array($d) || empty($d['rates']) || !is_array($d['rates'])) {
        $hist['_fail_ts'] = time();
        _vsec_write(VESTRA_FX_HISTORY_FILE, $hist);
        return false;
    }
    foreach ($d['rates'] as $day => $r) {
        if (!empty($r['USD']) && (float)$r['USD'] > 0) $hist[(string)$day] = round((float)$r['USD'], 6);
    }
    unset($hist['_fail_ts']);
    $hist['_fetched_at'] = date('c');
    _vsec_write(VESTRA_FX_HISTORY_FILE, $hist);
    return true;
}

/**
 * Damgayı yaz (idempotent: varsa dokunmaz). Sipariş "şimdi" yazılıyorsa
 * $liveNow=true: vitrin önbelleğindeki günün kuru kullanılır, ağa çıkılmaz.
 * Değilse geçmiş tablosundan sipariş tarihine bakılır. Kur bulunamazsa null döner
 * ve HİÇBİR ŞEY yazılmaz — sonraki geri doldurma dener.
 */
function vestra_order_fx_stamp(string $ref, string $orderTs, bool $liveNow = false): ?array {
    $ref = trim($ref);
    if ($ref === '') return null;
    $have = vestra_order_fx($ref);
    if ($have) return $have;

    $fx = null;
    if ($liveNow) {
        $st = vestra_fx_state();
        $usd = (float)($st['rates']['USD'] ?? 0);
        if ($usd > 0) $fx = ['usd' => round($usd, 6), 'date' => (string)($st['date'] ?: date('Y-m-d')),
                             'source' => (string)($st['source'] ?: 'ecb')];
    }
    if ($fx === null) {
        $date = substr((string)$orderTs, 0, 10);
        $hit = vestra_fx_history_lookup(_vsec_read(VESTRA_FX_HISTORY_FILE), $date);
        if ($hit) $fx = ['usd' => $hit['usd'], 'date' => $hit['date'], 'source' => 'ecb'];
    }
    if ($fx === null) return null;

    $fx['stamped_at'] = date('c');
    $all = vestra_read_json('order_statuses.json');
    $entry = $all[$ref] ?? ['status' => 'pending'];
    $entry['fx'] = $fx;
    $all[$ref] = $entry;
    vestra_write_json('order_statuses.json', $all);
    return $fx;
}

/**
 * Damgasız siparişleri toplu damgala. Önce geçmiş tablosuna bakar; eksik tarih
 * varsa aralığı TEK istekle çeker ($fetch=true), sonra yeniden dener.
 * ['missing'=>n, 'stamped'=>n, 'fetched'=>bool, 'still_missing'=>n]
 */
function vestra_orders_fx_backfill(array $orders, bool $fetch = true): array {
    $todo = [];
    foreach ($orders as $o) {
        $ref = (string)($o['ref'] ?? ''); $ts = (string)($o['timestamp'] ?? '');
        if ($ref === '' || strlen($ts) < 10) continue;
        if (vestra_order_fx($ref) === null) $todo[$ref] = substr($ts, 0, 10);
    }
    $out = ['missing' => count($todo), 'stamped' => 0, 'fetched' => false, 'still_missing' => 0];
    if (!$todo) return $out;

    $stampAll = function () use (&$todo, &$out) {
        foreach ($todo as $ref => $date) {
            if (vestra_order_fx_stamp($ref, $date) !== null) { $out['stamped']++; unset($todo[$ref]); }
        }
    };
    $stampAll();
    if ($todo && $fetch) {
        $dates = array_values($todo); sort($dates);
        $out['fetched'] = vestra_fx_history_fetch($dates[0], min(end($dates), date('Y-m-d')));
        if ($out['fetched']) $stampAll();
    }
    $out['still_missing'] = count($todo);
    return $out;
}

/** Listeler için: [ref => fx] tek okumada. */
function vestra_orders_fx_map(array $orders): array {
    $all = vestra_read_json('order_statuses.json');
    $m = [];
    foreach ($orders as $o) {
        $ref = (string)($o['ref'] ?? '');
        $fx = $all[$ref]['fx'] ?? null;
        if (is_array($fx) && (float)($fx['usd'] ?? 0) > 0) $m[$ref] = $fx;
    }
    return $m;
}

/** Kısa kur notu: "@1.0842 · ECB 4 Sep 2026". */
function vestra_order_fx_note(array $fx): string {
    $d = strtotime((string)($fx['date'] ?? ''));
    return '@'.number_format((float)$fx['usd'], 4).' · '.vestra_fx_source_label((string)($fx['source'] ?? ''))
         .($d ? ' '.date('j M Y', $d) : '');
}
