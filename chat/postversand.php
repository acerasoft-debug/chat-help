<?php
/**
 * ChatHelp — postversand.php — hibrit posta (LetterXpress) gonderim ucu.
 *  Dilekce PDF'ini Almanya icinde mektuba cevirip alicinin (kullanicinin
 *  kendi) adresine gonderir. Kimlik bilgileri config.php'den okunur:
 *    define('LETTERXPRESS_USER', '...');
 *    define('LETTERXPRESS_APIKEY', '...');
 *    define('LETTERXPRESS_MODE', 'test'); // 'test' veya 'live'
 *  Bunlar tanimli degilse endpoint "kurulum gerekli" doner (site bozulmaz).
 *  action=status : servis bagli mi?
 *  action=send   : {pdf_base64, color?, duplex?} -> LetterXpress setJob
 *  Not: fiziki gonderim yalniz odeme dogrulandiktan sonra cagrilmalidir.
 */
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if (($_SERVER['REQUEST_METHOD'] ?? '')==='OPTIONS') { http_response_code(204); exit; }
error_reporting(E_ERROR | E_PARSE);

@include_once __DIR__.'/config.php';

function out($a){ echo json_encode($a); exit; }

$configured = defined('LETTERXPRESS_USER') && defined('LETTERXPRESS_APIKEY')
  && constant('LETTERXPRESS_USER') && constant('LETTERXPRESS_APIKEY');
$mode = defined('LETTERXPRESS_MODE') ? constant('LETTERXPRESS_MODE') : 'test';

$action = $_GET['action'] ?? 'status';

if ($action==='status') {
  out(['ok'=>true,'configured'=>$configured,'mode'=>$mode,
       'provider'=>'LetterXpress',
       'message'=>$configured ? 'Postversand aktiv.' : 'Postversand noch nicht eingerichtet (API-Zugang fehlt).']);
}

if ($action==='send') {
  if (!$configured) out(['error'=>'not_configured','message'=>'Postversand ist noch nicht eingerichtet.']);
  $raw = file_get_contents('php://input');
  $b = json_decode($raw, true);
  if (!is_array($b)) out(['error'=>'bad_request']);
  $pdf = (string)($b['pdf_base64'] ?? '');
  $pdf = preg_replace('#^data:application/pdf;base64,#','',$pdf);
  if (strlen($pdf) < 100) out(['error'=>'no_pdf','message'=>'PDF fehlt oder ist zu klein.']);

  $color  = !empty($b['color']) ? '4' : '1';       // 1=s/w, 4=farbig
  $duplex = !empty($b['duplex']) ? 'duplex' : 'simplex';
  $ship   = 'national';

  $payload = [
    'auth' => [
      'username' => constant('LETTERXPRESS_USER'),
      'apikey'   => constant('LETTERXPRESS_APIKEY'),
      'mode'     => ($mode==='live' ? 'live' : 'test'),
    ],
    'letter' => [
      'base64_file'     => $pdf,
      'base64_checksum' => md5($pdf),
      'specification'   => [
        'color' => $color,
        'mode'  => $duplex,
        'ship'  => $ship,
      ],
    ],
  ];

  $ch = curl_init('https://api.letterxpress.de/v1/printjobs/setjob');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT => 45,
  ]);
  $res  = curl_exec($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);

  if ($res===false) out(['error'=>'network','http'=>$code,'detail'=>$err]);
  $j = json_decode($res, true);
  if (!is_array($j)) out(['error'=>'bad_response','http'=>$code,'raw'=>substr((string)$res,0,300)]);

  // LetterXpress: status 200 + message. Basari/hata alanlarini oldugu gibi ilet.
  out(['ok'=>($code>=200 && $code<300),'http'=>$code,'result'=>$j,'mode'=>$mode]);
}

out(['error'=>'unknown_action']);
