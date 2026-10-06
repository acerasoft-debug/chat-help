<?php
/**
 * VESTRA — is ortagi API anahtarlari.
 *
 * NEDEN TEK ORTAK ANAHTAR YETMIYOR
 * Dropship ucu tek bir statik anahtarla calisiyor (.env'deki DROPSHIP_API_KEY) ve
 * tek bir entegrasyon icin bu yeterliydi. Katalog ucu ayni sekilde kurulamaz,
 * cunku o ucun tasidigi sey TOPTAN FIYAT -- ticari belge kapisinin korudugu sey.
 * Ortak bir anahtarla:
 *   - bir ortagin erisimini kesmek digerlerini de keserdi,
 *   - anahtar bir yere kopyalandiginda kimden sizdigi bilinemezdi,
 *   - "kim ne zaman ceki̇yor" sorusu cevapsiz kalirdi.
 * O yuzden her ortagin kendi anahtari var: ayri ayri verilip ayri ayri iptal
 * ediliyor ve her biri son kullanim zamanini tasiyor.
 *
 * ANAHTAR DISKTE OZETLENMIS DURUYOR, DUZ METIN DEGIL
 * Dosya sizarsa anahtarlar kullanilamasin diye. Duz metin yalnizca uretildigi
 * anda bir kez gosteriliyor -- kaybedilirse yenisi verilir, eskisi okunamaz.
 *
 * Neden password_hash() degil de sha256: parolalar dusuk entropili ve sozluk
 * saldirisina aciktir, bu yuzden bcrypt'in yavasligi bir OZELLIKTIR. Buradaki
 * anahtar 192 bitlik rastgele bir dizge; tahmin edilecek bir sozlugu yok. Her
 * API istegine bcrypt koymak, korumadigi bir seye karsilik her cagriya yuz
 * milisaniye eklerdi. Karsilastirma yine hash_equals ile, zamanlama sizintisina
 * kapali.
 */

require_once __DIR__.'/security.php';   // _vsec_read / _vsec_write (data/ web'e kapali)

const VESTRA_API_KEY_PREFIX = 'vsk_';

function vestra_api_keys(): array {
    $a = _vsec_read('api_keys.json');
    return isset($a['keys']) && is_array($a['keys']) ? $a['keys'] : [];
}
function vestra_api_keys_save(array $keys): void {
    _vsec_write('api_keys.json', ['keys' => array_values($keys)]);
}

/**
 * Yeni anahtar uret. Duz metin SADECE bu donusun icinde var; hicbir yere yazilmiyor.
 * @return array{record: array, secret: string}
 */
function vestra_api_key_issue(string $label, string $account = ''): array {
    $secret = VESTRA_API_KEY_PREFIX.bin2hex(random_bytes(24));
    $rec = [
        'id'         => 'ak_'.bin2hex(random_bytes(5)),
        'label'      => ($l = trim($label)) !== '' ? mb_substr($l, 0, 60) : 'partner',
        /* Panelde ve kayitlarda anahtari TANIMAK icin: basi ve sonu. Ortadaki
           kisim yok, yani ipucu tek basina kullanilamaz. */
        'hint'       => mb_substr($secret, 0, 12).'…'.mb_substr($secret, -4),
        'hash'       => hash('sha256', $secret),
        'account'    => mb_strtolower(trim($account)),
        'created_at' => date('c'),
        'last_used'  => '',
        'calls'      => 0,
        'revoked_at' => '',
    ];
    $all = vestra_api_keys();
    $all[] = $rec;
    vestra_api_keys_save($all);
    return ['record' => $rec, 'secret' => $secret];
}

/** Anahtari dogrula. Gecerliyse kaydi, degilse null doner. */
function vestra_api_key_verify(string $key): ?array {
    $key = trim($key);
    if ($key === '' || strncmp($key, VESTRA_API_KEY_PREFIX, strlen(VESTRA_API_KEY_PREFIX)) !== 0) return null;
    $want = hash('sha256', $key);
    foreach (vestra_api_keys() as $k) {
        if (!empty($k['revoked_at'])) continue;
        if (hash_equals((string)($k['hash'] ?? ''), $want)) return $k;
    }
    return null;
}

/**
 * "Bu ortak hala cekiyor mu, ne siklikta?" sorusunu cevaplayabilmek icin isaretle.
 *
 * Her istekte dosyayi yaziyor ve bu BILEREK boyle. Once yazmayi dakikada bire
 * dusuren bir esik koymustum; ise yaramiyordu, cunku arada gecen cagrilari
 * saymak icin onlari yine diske yazmak gerekiyordu -- yani esik hicbir yazmayi
 * engellemeden yalnizca karmasiklik ekliyordu. Ustelik gereksizdi: bu dosya
 * birkac kilobayt, ayni istek zaten 300 KB'lik katalogu okuyor. Bu yazmanin
 * maliyeti olcum hatasi kadar.
 */
function vestra_api_key_touch(string $id): void {
    $all = vestra_api_keys();
    foreach ($all as &$k) {
        if (($k['id'] ?? '') !== $id) continue;
        $k['calls']     = (int)($k['calls'] ?? 0) + 1;
        $k['last_used'] = date('c');
        unset($k);
        vestra_api_keys_save($all);
        return;
    }
}

function vestra_api_key_revoke(string $id): bool {
    $all = vestra_api_keys(); $hit = false;
    foreach ($all as &$k) {
        if (($k['id'] ?? '') === $id && empty($k['revoked_at'])) { $k['revoked_at'] = date('c'); $hit = true; }
    }
    unset($k);
    if ($hit) vestra_api_keys_save($all);
    return $hit;
}

/** Panelde gosterim icin: ozet ASLA disari cikmaz. */
function vestra_api_keys_public(): array {
    $out = [];
    foreach (vestra_api_keys() as $k) {
        unset($k['hash']);
        $out[] = $k;
    }
    usort($out, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    return $out;
}
