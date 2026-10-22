<?php
/** ChatHelp — dump-chat-selftest (SADECE OKUR + uctan uca sohbet testi)
 *  Kullanici adimina gerek OLMADAN ana sohbeti sunucu icinden GERCEK olarak
 *  test eder (sohbet giris gerektirmez):
 *   [1] Turkce kisa mesaj -> ilk tur (askfirst kapisi: soru donmeli)
 *   [2] Turkce detayli mesaj -> tam akis (cevap/[[DOC]] donmeli)
 *   [3] Ispanyolca mesaj -> Ispanyolca cevap donmeli (dil testi)
 *  Her testte: HTTP kodu + yanit suresi + ham JSON (ilk 600 bayt).
 *  Ardindan chat-debug.log gosterilir (log noktalari da dogrulanir).
 *  Ciktinin TAMAMINI gonder. SIL: rm dump-chat-selftest.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
@set_time_limit(300);

function chatTest($label, $payload) {
    $t0 = microtime(true);
    $ch = curl_init('https://chat-help.com/chat/api.php?action=aichat');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $dt = round(microtime(true)-$t0, 1);
    echo "── $label ──\n";
    echo "HTTP: $code · sure: {$dt}s".($err?" · cURL: $err":"")."\n";
    $j = json_decode((string)$res, true);
    if (is_array($j) && isset($j['reply'])) {
        echo "reply uzunlugu: ".mb_strlen($j['reply'])."\n";
        echo "reply (ilk 500): ".mb_substr($j['reply'],0,500)."\n";
    } else {
        echo "ham yanit (ilk 600): ".substr((string)$res,0,600)."\n";
    }
    echo "\n";
}

echo "════════ UCTAN UCA SOHBET TESTLERI (gercek AI cagrilari — 1-2 dk surebilir) ════════\n\n";

chatTest("[1] TURKCE KISA (askfirst kapisi: SORU donmeli, [[DOC]] DONMEMELI)", [
    'message' => 'kira sözleşmemi feshetmek istiyorum',
    'history' => [], 'provider' => 'deepseek', 'lang' => 'tr', 'country' => 'TR', 'profile' => [],
]);

chatTest("[2] TURKCE DETAYLI (tam akis: anlamli cevap donmeli)", [
    'message' => 'Ev sahibim Ahmet Yılmaz\'a (Örnek Mah. Test Sok. No:5 İstanbul) 01.09.2026 tarihinde çıkmak üzere kira fesih bildirimi göndermek istiyorum. Sözleşmem 01.09.2024 başlangıçlı. Lütfen fesih dilekçesini hazırla.',
    'history' => [], 'provider' => 'deepseek', 'lang' => 'tr', 'country' => 'TR', 'profile' => [],
]);

chatTest("[3] ISPANYOLCA (cevap ISPANYOLCA olmali, Almanca OLMAMALI)", [
    'message' => 'Quiero cancelar mi contrato de gimnasio, ¿qué debo hacer?',
    'history' => [], 'provider' => 'deepseek', 'lang' => 'es', 'country' => 'ES', 'profile' => [],
]);

echo "════════ chat-debug.log ════════\n\n";
$lf = __DIR__.'/data/chat-debug.log';
echo file_exists($lf) ? file_get_contents($lf) : "(log yok — CH_CHAT_DBG calismiyor olabilir!)";
echo "\n\n════════ BITTI. Ciktinin TAMAMINI gonder. SIL: rm dump-chat-selftest.php ════════\n";
