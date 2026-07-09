<?php
/** ChatHelp — dump-fall-logtest (SADECE OKUR + log mekanizmasi testi)
 *  Log hala bos — uc ihtimali ayirir:
 *   [1] data/ dizini yazilabilir mi? (dogrudan test yazimi)
 *   [2] fall-api.php'ye SUNUCU ICINDEN istek atinca [A] REQ satiri olusuyor mu?
 *       (olusuyorsa log mekanizmasi CALISIYOR demektir)
 *   [3] Log icerigi — [2]'nin REQ satiri var ama kullanicinin denemesinden
 *       satir yoksa: KULLANICININ ISTEGI SUNUCUYA HIC ULASMIYOR (frontend/JS
 *       sorunu) — "sonuc cikmiyor"un sebebi budur.
 *  Ciktinin TAMAMINI gonder. SIL: rm dump-fall-logtest.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
@set_time_limit(60);
$D = __DIR__;
$lf = $D.'/data/fall-debug.log';

echo "════════ [1] data/ YAZMA TESTI ════════\n\n";
echo "data/ dizini var mi: ".(is_dir($D.'/data')?"EVET":"HAYIR — SORUN BU OLABILIR")."\n";
echo "data/ yazilabilir mi: ".(is_writable($D.'/data')?"EVET":"HAYIR — SORUN BU")."\n";
$w = @file_put_contents($lf, date('H:i:s')."|LOGTEST|dogrudan-yazim-testi\n", FILE_APPEND);
echo "dogrudan log yazimi: ".($w!==false?"BASARILI ($w bayt)":"BASARISIZ — izin sorunu")."\n";

echo "\n════════ [2] IC ISTEK -> [A] REQ SATIRI TESTI ════════\n\n";
$ch = curl_init('https://chat-help.com/chat/fall-api.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode(['action'=>'fall_quota']),
    CURLOPT_SSL_VERIFYPEER => false,
]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "ic istek HTTP kodu: $code (401 normal — token yok)\n";
echo "yanit: ".substr((string)$res,0,200)."\n";

echo "\n════════ [3] GUNCEL LOG ICERIGI ════════\n\n";
clearstatcache();
if (file_exists($lf)) {
    $c = file_get_contents($lf);
    echo "boyut: ".strlen($c)." bayt\n\n".$c."\n";
    echo "\nDEGERLENDIRME:\n";
    $hasReq = strpos($c,'|REQ|')!==false;
    echo $hasReq
      ? "  ✓ REQ satiri VAR -> log mekanizmasi CALISIYOR.\n    Kullanicinin denemesinden satir yoksa: istek sunucuya HIC ULASMIYOR (frontend sorunu).\n"
      : "  ✗ Ic istek attigim halde REQ satiri YOK -> [A] log noktasi calismiyor (beklenmedik).\n";
} else {
    echo "LOG DOSYASI HALA YOK — dogrudan yazim da basarisizsa izin sorunu kesinlesir.\n";
}

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-fall-logtest.php ════════\n";
