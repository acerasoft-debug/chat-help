<?php
/**
 * ChatHelp — dump13 (SADECE OKUR): KAPSAMLI kontrol
 * Verträge scope + e-posta(Anmeldung) + behalten/depolama + plan/kota + ton + textarea + doc-email
 * KULLANIM: curl -s -A "Mozilla/5.0" "https://chat-help.com/chat/dump13.php"
 */
header('Content-Type: text/plain; charset=UTF-8');
$idx=@file_get_contents(__DIR__.'/index.php');
$api=@file_get_contents(__DIR__.'/api.php');
$auth=@file_get_contents(__DIR__.'/auth.php');
function has($s,$n){return $s!==false&&strpos($s,$n)!==false;}
function R($s,$needle,$b,$l,$lbl){echo "\n──── $lbl ────\n";if($s===false){echo "(yok)\n";return;}$p=strpos($s,$needle);if($p===false){echo "(BULUNAMADI: '$needle')\n";return;}echo substr($s,max(0,$p-$b),$l)."\n";}

echo "############ A) VERTRÄGE ############\n";
foreach(['ch-vertraege','ch-vertraege2','ch-vtr-home','ch-cat-toggle'] as $m) echo (has($idx,$m)?"  ✓ ":"  ✗ ").$m."\n";
echo "  vertraege:{ literal -> ".(has($idx,'vertraege:{')?'VAR':'yok')."\n";
$pd=strpos($idx,'const DOCS=');
echo "\n--- const DOCS bağlamı (200 char önce) ---\n".($pd!==false?substr($idx,max(0,$pd-200),230):'(yok)')."\n";
echo "\n--- const DOCS başı (anchor) ---\n".($pd!==false?substr($idx,$pd,90):'')."\n";

echo "\n\n############ B) E-POSTA (Anmeldung) ############\n";
echo "  relay aktif (auth.php) -> ".(has($auth,'relay-hosting.secureserver.net')?'✓':'✗')."\n";
R($auth,'function sendVerificationEmail',0,1000,'sendVerificationEmail');
R($auth,'sendVerificationEmail(',0,160,'register içinde çağrı');

echo "\n\n############ C) BEHALTEN / DEPOLAMA ############\n";
R($api,'keepdoc',-40,140,'api match: keepdoc route');
R($api,'function doKeep',0,1200,'doKeep/doKeepdoc gövde');
R($api,'user_documents',-100,300,'user_documents tablosu');
R($idx,'behalten',-200,300,'index behalten butonu');
R($idx,'keepdoc',-150,350,'index keepdoc çağrısı');
R($idx,"action=history",-80,200,'history (kayıtlı dilekçeler)');

echo "\n\n############ D) PLAN/KOTA (paket kullanımı) ############\n";
R($idx,'Monthly quota check',-400,700,'genDoc plan+kota mantığı');

echo "\n\n############ E) TON (tüm dilekçeler) ############\n";
echo "  genDoc tone gönderiyor -> ".(has($idx,"tone:ans._tone")?'✓':'✗')."\n";
R($idx,'_tone',-150,260,'ton nereden geliyor (_tone)');
R($idx,'ch-tone-bar',-80,200,'mevcut ton seçici (mega)');

echo "\n\n############ F) TEXTAREA (kutu büyüsün) ############\n";
R($idx,'function onInp',0,260,'onInp (mevcut auto-grow)');
R($idx,"q.t==='textarea'",-60,360,'soru textarea üretimi');

echo "\n\n############ G) DOC -> E-POSTA endpoint ############\n";
echo "  mail-doc/send-doc endpoint -> ".(has($idx,'send-doc')||has($api,'send_doc')?'VAR':'yok (eklenecek)')."\n";
R($idx,'makePDF(doc',-300,200,'doc üretim sonrası (email butonu yeri)');

echo "\n=== BİTTİ. SİL: rm dump13.php ===\n";
