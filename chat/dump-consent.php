<?php
/**
 * ChatHelp - dump-consent (READ-ONLY) - Meta Pixel kurulumu oncesi durum tespiti.
 *  [1] Consent/Cookie banner var mi (DSGVO onayi)?
 *  [2] Halihazirda pixel/analytics var mi (fbq/gtag/gtm)?
 *  [3] <head> ve script enjeksiyon noktalari
 *  [4] Kayit basarisi (CompleteRegistration icin) + Stripe odeme basarisi (Purchase icin) noktalari
 * KULLANIM: /chat/pull2.php?key=...&files=dump-consent.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$src=@file_get_contents(__DIR__.'/index.php');
if($src===false){ echo "index.php YOK\n"; return; }
echo "== dump-consent (".number_format(strlen($src))." B) ==\n\n";

echo "=== [1] Consent / Cookie banner (DSGVO) ===\n";
foreach(array('Consent','consent','Cookie','cookie','DSGVO','Einwilligung','Datenschutz','cookiebanner','cookie-banner','cmp','usercentrics','cookiebot','borlabs','klaro','ch_consent','ch_cookie') as $pat){
  $c=substr_count($src,$pat);
  if($c>0) echo "  ".$pat.": ".$c." kez\n";
}
echo "  -- ilk cookie/consent baglami --\n";
foreach(array('Cookie','Consent','Datenschutz') as $pat){
  $q=strpos($src,$pat);
  if($q!==false){ echo "  [".$pat." @".$q."]: ...".str_replace(array("\n","\r"),' ',substr($src,($q-40>0?$q-40:0),160))."...\n"; }
}

echo "\n=== [2] Mevcut pixel/analytics ===\n";
foreach(array('fbq','facebook','connect.facebook','Meta Pixel','gtag','googletagmanager','google-analytics','gtm.js','_fbp','dataLayer') as $pat){
  echo "  ".$pat.": ".substr_count($src,$pat)." kez\n";
}

echo "\n=== [3] <head> ve enjeksiyon noktalari ===\n";
$h=strpos($src,'<head');
echo "  <head @".($h!==false?$h:'yok')."\n";
$he=strpos($src,'</head>');
echo "  </head> @".($he!==false?$he:'yok')."\n";
if($h!==false){ echo "  head ilk 300: ...".str_replace(array("\n","\r"),' ',substr($src,$h,300))."...\n"; }
echo "  <body ilk occurrence @".strpos($src,'<body').", son </body> @".strrpos($src,'</body>')."\n";

echo "\n=== [4] Event noktalari ===\n";
echo "  -- kayit / register / signup (CompleteRegistration) --\n";
foreach(array('register','signup','sign_up','registrieren','createAccount','ch_register','action=register','regOk','onRegister') as $pat){
  $q=strpos($src,$pat);
  if($q!==false) echo "  [".$pat." @".$q."]: ...".str_replace(array("\n","\r"),' ',substr($src,($q-30>0?$q-30:0),130))."...\n";
}
echo "  -- odeme basarisi (Purchase) --\n";
foreach(array("mode==='payment'","d.paid","addCredit(1)","stripe-checkout.php","session_id","checkout.session") as $pat){
  $q=strpos($src,$pat);
  if($q!==false) echo "  [".$pat." @".$q."]: ...".str_replace(array("\n","\r"),' ',substr($src,($q-40>0?$q-40:0),150))."...\n";
}
echo "\n== BITTI ==\n";
