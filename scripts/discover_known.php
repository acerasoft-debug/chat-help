<?php
/**
 * SUNUCUDA calisir (discover-shops.yml, ssh ile stdin'den): kayitli lead'lerin ve
 * hesaplarin SITE hostlarini ve e-posta ALAN ADLARINI satir satir basar -- kesif
 * zaten yazdigimiz ya da musterimiz olan firmayi yeniden aday gostermesin.
 *
 * Yalniz host / @alanadi basilir; kisi, adres, firma adi YOK. Cikti kutuge
 * yazilmaz (runner'da dosyaya yonlendirilir). Site kimligi kurali TEK yerde:
 * scripts/discover_overture.py site_identity() -- burada ham host veriliyor.
 *
 * SERBEST POSTA SAGLAYICISI BASILMAZ (vestra_email_is_shared_provider, KURAL 38'in
 * tek kaynagi): "@orange.fr" bir firma degil. Basilsaydi kesif "monsite.orange.fr"
 * ya da "x.free.fr" gibi kisisel sayfali dukkanlari "ZATEN KAYITLI" diye SESSIZCE
 * elerdi -- 26 Eylul'de firma tekillestirmesinin wanadoo/hotmail'deki dukkanlari
 * eledigi hatanin kesif hali.
 *
 * Salt okunur: hicbir dosyaya yazmaz. Kum havuzunda HOME ile yonlendirilebilir (test).
 */
$home = (string)getenv('HOME');
require_once $home.'/public_html/inc/notify.php';
$seen = [];
$emit = function (string $h) use (&$seen) {
    $h = strtolower(trim($h));
    if ($h === '' || $h === '@' || isset($seen[$h])) return;
    $seen[$h] = true;
    echo $h, "\n";
};
$host = function (string $url): string {
    $u = preg_replace('#^[a-z]+://#i', '', trim($url));
    return (string)preg_replace('/[\/?#:].*$/', '', (string)$u);
};
foreach (['leads.json', 'accounts.json'] as $f) {
    $p = $home.'/public_html/data/'.$f;
    if (!is_readable($p)) continue;
    $rows = json_decode((string)file_get_contents($p), true);
    if (!is_array($rows)) continue;
    foreach ($rows as $r) {
        if (!is_array($r)) continue;
        foreach (['website', 'web', 'site'] as $k) if (!empty($r[$k]) && is_string($r[$k])) $emit($host($r[$k]));
        $e = (string)($r['email'] ?? '');
        if (($at = strrpos($e, '@')) === false) continue;
        $dom = substr($e, $at + 1);
        if ($dom === '' || vestra_email_is_shared_provider($dom)) continue;
        $emit('@'.$dom);
    }
}
