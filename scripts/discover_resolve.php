<?php
/**
 * KESIF ADAYLARINI SUZ + YAYINLANMIS E-POSTA VAR MI OLC (GitHub makinesinde).
 *
 * discover_overture.py'nin cikardigi adaylar icin, SITENIN KENDI kodundan:
 *   - vestra_lead_is_blocked()         KURAL 1 (zincir / distributor / kendi markasi)
 *   - vestra_name_is_parked_domain()   KURAL 1d (park / satilik alan adi)
 *   - vestra_scrape_email()            sitede YAYINLANMIS adres var mi (KURAL 1f)
 *   - vestra_email_is_service_vendor() KURAL 1h (widget / eklenti / ajans adresi)
 *
 * E-POSTA ADRESI HICBIR YERE YAZILMAZ -- ne dosyaya ne kutuge. Bu olcum yalniz
 * "adres var mi, dukkanin kendi alan adinda mi" sorusunu cevapliyor; adresi
 * GONDERIM aninda sunucu kendisi cozer (add-and-send, KURAL 1f). Depo ve
 * Actions gunlugu herkese acik.
 *
 * "Site acilmadi" ile "sitede adres yok" AYRI sonuclar: ilki olu alan adi ya da
 * GitHub makinesinin IP'sini engelleyen bir WAF olabilir (ayni site VESTRA
 * sunucusundan acilabilir), ikincisi bizim hattimizin kesin cevabi. Ikisini ayni
 * kovaya koymak, sunucudan denenebilecek dukkanlari sessizce silerdi.
 *
 * Kullanim: php scripts/discover_resolve.php <cands.json> <out.json> <isci_no> <isci_sayisi> <butce_sn>
 * Her isci i % N == isci_no adaylari isler (paralel kosu icin).
 * Test dosyasi bu dosyayi require eder; ana blok yalniz dogrudan calistirilinca kosar.
 */

/* Kok alan adi: discover_overture.py site_identity() ile AYNI kural (co.uk gibi iki
   parcali uzantilar). Test, iki dilin ayni orneklerde ayni kimligi verdigini olcuyor. */
function discover_site_root(string $host): string {
    $h = strtolower(trim($host));
    $h = (string)preg_replace('#^[a-z]+://#', '', $h);
    $h = (string)preg_replace('/[\/?#:].*$/', '', $h);
    $h = (string)preg_replace('/^www\./', '', $h);
    $p = explode('.', $h);
    if (count($p) < 2) return '';
    if (count($p) >= 3 && in_array($p[count($p) - 2], ['co','com','org','net','ac','gov'], true) && strlen((string)end($p)) === 2) {
        return implode('.', array_slice($p, -3));
    }
    return implode('.', array_slice($p, -2));
}

/* Adresin alan adi dukkanin site kimliginde mi? $site Python'un kimligi: kok alan
   adi ya da (wixsite gibi platformlarda) TAM host. */
function discover_email_on_site(string $emailDomain, string $site): bool {
    $d = strtolower(trim($emailDomain)); $s = strtolower(trim($site));
    if ($d === '' || $s === '') return false;
    return $d === $s || str_ends_with($d, '.'.$s) || discover_site_root($d) === $s;
}

/* Tek aday -> ['why','email','kind']. Karar SAF: agdan okuyan iki fonksiyon disaridan
   verilir ($reach: site aciliyor mu, $scrape: yayinlanmis adres), test sahtesini verir.
   'email' yalniz 'VAR' / 'yok' olabilir -- adresin kendisi bu fonksiyondan cikmaz. */
function discover_resolve_one(array $c, callable $scrape, callable $reach): array {
    $r = ['why' => '', 'email' => 'yok', 'kind' => ''];
    $name = (string)($c['name'] ?? ''); $web = (string)($c['website'] ?? ''); $site = (string)($c['site'] ?? '');
    if (vestra_lead_is_blocked(['company' => $name, 'website' => $web])) { $r['why'] = 'KURAL 1 (ad / site)'; return $r; }
    if (vestra_name_is_parked_domain($name)) { $r['why'] = 'PARK ALAN ADI'; return $r; }
    if (!$reach($web)) { $r['why'] = 'SITE ACILMADI (olu alan adi ya da bot engeli)'; return $r; }
    $em = strtolower(trim((string)$scrape($web)));
    if ($em === '') { $r['why'] = 'SITEDE ADRES YOK'; return $r; }
    if (vestra_email_is_service_vendor($em)) { $r['why'] = 'SERVIS SAGLAYICI ADRESI (KURAL 1h)'; return $r; }
    if (vestra_lead_is_blocked(['company' => $name, 'website' => $web, 'email' => $em])) { $r['why'] = 'KURAL 1 (adres)'; return $r; }
    $r['email'] = 'VAR';
    $dom = substr($em, (int)strrpos($em, '@') + 1);
    $r['kind'] = vestra_email_is_shared_provider($em) ? 'ortak (gmail vb.)'
               : (discover_email_on_site($dom, $site) ? 'kendi alan adi' : 'BASKA ALAN ADI');
    return $r;
}

/* Site aciliyor mu -- vestra_scrape_email()'in denedigi AYNI uc taban, ayni zaman
   asimi. Once sorulur: olu sitede kaziyici ayni uc denemeyi bir kez daha yapmasin. */
function discover_site_reachable(string $website): bool {
    $d = vestra_domain_of($website);
    if ($d === '') return false;
    foreach (['https://'.$d, 'https://www.'.$d, 'http://'.$d] as $b) {
        if (vestra_http_get($b.'/', 8) !== '') return true;
    }
    return false;
}

if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['argv'][0] ?? '')) === __FILE__) {
    @set_time_limit(0);
    ini_set('display_errors', 'stderr');
    error_reporting(E_ALL & ~E_DEPRECATED);
    [$_, $in, $out, $idx, $of, $budget] = array_pad($argv, 6, '');
    $idx = (int)$idx; $of = max(1, (int)$of); $budget = max(30, (int)$budget);
    if (!is_readable($in) || $out === '') { fwrite(STDERR, "kullanim: php discover_resolve.php <cands.json> <out.json> <i> <N> <butce>\n"); exit(2); }
    require_once dirname(__DIR__).'/vestra/inc/notify.php';

    $cands = json_decode((string)file_get_contents($in), true) ?: [];
    $start = time();
    $res = [];
    foreach ($cands as $i => $c) {
        if ($i % $of !== $idx) continue;
        if (time() - $start > $budget) { $res[] = ['i' => $i, 'why' => 'SURE DOLDU (taranmadi)', 'email' => 'yok', 'kind' => '']; continue; }
        $res[] = ['i' => $i] + discover_resolve_one((array)$c, 'vestra_scrape_email', 'discover_site_reachable');
    }
    file_put_contents($out, json_encode($res, JSON_UNESCAPED_UNICODE));
    fwrite(STDERR, "isci {$idx}/{$of}: ".count($res)." aday, ".(time() - $start)." sn\n");
}
