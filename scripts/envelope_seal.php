<?php
/**
 * SIFRELI ZARF MUHURLE -- sunucudaki ~/.vestra_inbox_key.pem'in public key'iyle.
 *
 * Neden: bu depo ve Actions gunlugu herkese acik; is akisi GIRDISI de oyle
 * (kosu basliginda kalici). Bir musteri adresi ya da kisiye ait baska bir veri
 * girdiye DUZ yazilirsa yayinlanmis olur. Zarf okunamaz; ozel anahtar
 * sunucudan hic cikmiyor.
 *
 * Bicim -- su uc yer AYNI bicimi aciyor, biri degisirse hepsi degismeli:
 *   seller-products.yml     create_buyer / platform_bank (payload=)
 *   send-campaign-preview.yml  reply_spec to=enc:<zarf>
 *
 *   KEY.IV.GOVDE
 *   KEY   = RSA-OAEP(public key, 32 baytlik AES anahtari)
 *   IV    = 16 bayt
 *   GOVDE = AES-256-CBC(JSON)
 *   Her parca base64 ve RAKAMLAR '!$%&#,;?@~' ile degistirilmis: kisa sayisal
 *   bir secret (DEPLOY_PORT="22") Actions gunlugunde *** ile maskeleniyor ve
 *   rastgele base64'un icinde gecince zarfi bozuyordu.
 *
 * Kullanim:
 *   php scripts/envelope_seal.php <public key dosyasi> < govde.json
 *   Public key dosyasi PEM olabilir ya da keygen'in bastigi base64 (tek satir).
 *   Public key: seller-products.yml -> admin_mode=keygen ("PUBKEY <base64>").
 *   Keygen satirinda "***" gorunuyorsa o "22"dir (DEPLOY_PORT maskesi) --
 *   geri koyun; yanlis bir anahtarla muhurlenen zarfi sunucu "anahtar
 *   acilamadi" diye reddeder, sessizce yanlis bir sey acmaz.
 *
 * Muhurlenen metni (adres vb.) bu depoya, bir dosyaya ya da kutuge YAZMAYIN;
 * govdeyi stdin'den verin.
 */

function vestra_envelope_seal(string $pubPem, array $data): string {
    $pub = openssl_pkey_get_public($pubPem);
    if (!$pub) throw new RuntimeException('public key okunamadi');
    $aes  = random_bytes(32);
    $iv   = random_bytes(16);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $body = openssl_encrypt((string)$json, 'aes-256-cbc', $aes, OPENSSL_RAW_DATA, $iv);
    $key  = '';
    if ($body === false || !openssl_public_encrypt($aes, $key, $pub, OPENSSL_PKCS1_OAEP_PADDING)) {
        throw new RuntimeException('sifreleme basarisiz');
    }
    $enc = fn(string $b): string => strtr(base64_encode($b), '0123456789', '!$%&#,;?@~');
    return $enc($key).'.'.$enc($iv).'.'.$enc($body);
}

/* Keygen'in bastigi base64'u ya da duz PEM'i PEM'e cevirir. */
function vestra_envelope_pubkey(string $raw): string {
    $raw = trim($raw);
    if (str_contains($raw, 'BEGIN PUBLIC KEY')) return $raw;
    if (str_starts_with($raw, 'PUBKEY ')) $raw = trim(substr($raw, 7));
    $pem = base64_decode($raw, true);
    return $pem === false ? '' : $pem;
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    $f = (string)($argv[1] ?? '');
    if ($f === '' || !is_file($f)) { fwrite(STDERR, "kullanim: php scripts/envelope_seal.php <public key dosyasi> < govde.json\n"); exit(1); }
    $pem = vestra_envelope_pubkey((string)file_get_contents($f));
    if ($pem === '' || !openssl_pkey_get_public($pem)) { fwrite(STDERR, "public key okunamadi\n"); exit(1); }
    $in = json_decode((string)stream_get_contents(STDIN), true);
    if (!is_array($in)) { fwrite(STDERR, "govde JSON nesnesi olmali (stdin)\n"); exit(1); }
    echo vestra_envelope_seal($pem, $in), "\n";
}
