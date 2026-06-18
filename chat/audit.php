<?php
/**
 * ChatHelp — TAM DENETİM (audit) — SADECE OKUR, hiçbir şey değiştirmez
 * --------------------------------------------------------------------
 * Tüm bilinen özellikleri/eksikleri tek raporda ✓/✗ olarak listeler
 * ve patcher yazmak için gereken gerçek kod bölgelerini döker.
 *
 * KULLANIM: chat-help.com/chat/audit.php — çıktının TAMAMINI bana yapıştır. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');

$idx  = @file_get_contents(__DIR__ . '/index.php');
$api  = @file_get_contents(__DIR__ . '/api.php');
$auth = @file_get_contents(__DIR__ . '/auth.php');
$fall = @file_get_contents(__DIR__ . '/fall-api.php');
$cfg  = @file_get_contents(__DIR__ . '/config.php');
$db   = @file_get_contents(__DIR__ . '/db.php');

function H($s){ echo "\n" . str_repeat('━', 58) . "\n  $s\n" . str_repeat('━', 58) . "\n"; }
function ck($label, $ok, $note=''){ echo ($ok?"  ✓ ":"  ✗ ").$label.($note?"  ($note)":'')."\n"; }
function has($s,$n){ return $s!==false && strpos($s,$n)!==false; }
function rgx($s,$p){ return $s!==false && preg_match($p,$s); }
function dump($s,$needle,$before,$len,$lbl){
    echo "\n  ▼ $lbl\n";
    if($s===false){echo "    (dosya yok)\n";return;}
    $p=strpos($s,$needle);
    if($p===false){echo "    (BULUNAMADI: '$needle')\n";return;}
    $t=substr($s,max(0,$p-$before),$len);
    foreach(explode("\n",$t) as $ln) echo "    ".$ln."\n";
}

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║         ChatHelp — TAM DENETİM RAPORU                     ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n";
echo "Tarih: ".date('Y-m-d H:i:s')."\n";
echo "Dosya boyutları:\n";
foreach(['index.php'=>$idx,'api.php'=>$api,'auth.php'=>$auth,'fall-api.php'=>$fall,'config.php'=>$cfg,'db.php'=>$db] as $k=>$v)
    echo "  $k = ".($v===false?'YOK':strlen($v).' byte')."\n";

H("1) AI SAĞLAYICI ADI SIZINTISI (kullanıcı görmemeli)");
ck("index: 'DeepSeek' görünür metinde YOK", !rgx($idx,'/>[^<]*DeepSeek|DeepSeek[^<]*</'));
ck("index: 'Claude' görünür metinde YOK",   !rgx($idx,'/>[^<]*Claude|Claude[^<]*</'));
ck("api: 'DeepSeek' kullanıcı metni YOK",    !rgx($api,'/["\'][^"\']*DeepSeek[^"\']*["\']/'));
ck("api: 'Claude' kullanıcı metni YOK",      !rgx($api,'/["\'][^"\']*Claude[^"\']*["\']/'));
ck("fall: sağlayıcı adı temiz",              !has($fall,'DeepSeek') && !rgx($fall,'/Claude[^_]/'));
if(has($idx,'DeepSeek')) dump($idx,'DeepSeek',60,160,"index DeepSeek geçişi");
if(has($idx,'Claude'))   dump($idx,'Claude',60,160,"index Claude geçişi");
if(has($api,'DeepSeek')) dump($api,'DeepSeek',40,140,"api DeepSeek geçişi");

H("2) JSON BOZULMASI / HATA SUSTURMA");
ck("api: error_reporting(0)",        has($api,'error_reporting(0)'));
ck("api: CLAUDE_KEY guard",          has($api,"defined('CLAUDE_KEY')"));
ck("api: DEEPSEEK_KEY guard",        has($api,"defined('DEEPSEEK_KEY')"));
ck("fall: config.php yükleniyor",    has($fall,'config.php'));

H("3) TEMA SİSTEMİ");
ck("Antrazit tema CSS",   has($idx,'data-chtheme="anthracite"'));
ck("Lacivert/Marine tema",has($idx,'data-chtheme="navy"'));
ck("Beyaz/Hell tema",     has($idx,'data-chtheme="white"'));
ck("Tema erken set script",has($idx,'getItem(\'ch_theme\')')||has($idx,"getItem('ch_theme')"));
ck("Design seçici (Konto)",has($idx,'chtheme-btn'));

H("4) KONTO / GİRİŞ");
ck("chFillKontoUser fonksiyonu",  has($idx,'chFillKontoUser'));
ck("window.chFillKontoUser",      has($idx,'window.chFillKontoUser'));
ck("Girişte isim gösterimi",      has($idx,'konto-name')||has($idx,'konto-user-info'));
ck("tb-auth-label (üst bar isim)",has($idx,'tb-auth-label'));
ck("Free plan kaldırılmış",       !rgx($idx,'/Free|Kostenlos/'));

H("5) DİLEKÇE (Antrag) AKIŞI");
ck("showQuestions fonksiyonu",   has($idx,'showQuestions'));
ck("genDoc fonksiyonu",          has($idx,'genDoc'));
ck("Tarih varsayılanı (genDoc)", has($idx,'ans.datum')||has($idx,'!ans.datum'));
ck("Akıllı alanlar (tarih/select)",has($idx,'_isDate')||has($idx,'q.opts'));
ck("Fotoğraf yükleme (soru altı)",has($idx,'chQPhotoChange')||has($idx,'ch-q-photo'));
ck("Foto JS (_chPhotos)",        has($idx,'_chPhotos'));
ck("PDF sanitizer",              has($idx,'chSanitizePDF'));
ck("PDF markasız (temiz)",       !has($idx,'ChatHelp')||!rgx($idx,'/pdf.*ChatHelp/i'));

H("6) DURDUR / İPTAL / GERİ AL");
ck("AbortController (durdur)",   has($idx,'AbortController'));
ck("'Abbrechen'/'Stop' butonu",  has($idx,'Abbrechen')||has($idx,'Stoppen')||has($idx,'chStop'));
ck("Üretim sırasında iptal",     has($idx,'abort()'));

H("7) ÜBERARBEITEN (revize) + KOTA");
ck("chRevise fonksiyonu",        has($idx,'chRevise'));
ck("Revize kotası (Basic3/Pro10)",has($idx,'revise')&&(has($idx,'10')||has($idx,'unbegrenzt')));

H("8) FALL (Vaka)");
ck("Fall paneli",                has($idx,'pnl-fall')||has($idx,'fall_chat'));
ck("Fall solve profil+tarih",    has($idx,'fall_solve')&&has($idx,'profile:'));
ck("Fall foto desteği (index)",  has($idx,'fall_solve')&&has($idx,'photos:'));
ck("fall-api: ton parametresi",  has($fall,"\$body['tone']"));
ck("fall-api: foto (chAI2)",     has($fall,'chAI2')||has($fall,'photos'));
ck("fall-api: kompleks soru sys",has($fall,'Aufenthaltsrecht'));

H("9) TON AYARI (Pro/Elite)");
ck("Ton seçici UI (index)",      has($idx,'tone')||has($idx,'Schreibstil')||has($idx,'Ton'));
ck("fall-api ton map",           has($fall,'formell')&&has($fall,'bestimmt'));

H("10) STRATEGIE (Pro'ya açık?)");
ck("strategie action (api)",     has($api,'strategie'));
ck("strategie sadece elite DEĞİL",!rgx($api,'/strategie.{0,40}elite|elite.{0,40}strategie/'));
if(has($api,'strategie')) dump($api,'strategie',120,360,"api strategie plan kontrolü");
if(has($idx,'Strategie')) dump($idx,'Strategie',100,300,"index Strategie butonu/gating");

H("11) E-POSTA (kayıt + doğrulama)");
ck("auth: register action",      has($auth,"'register'")||has($auth,'register'));
ck("auth: sendMail fonksiyonu",  has($auth,'sendMail'));
ck("auth: doğrulama token",      has($auth,'verify')||has($auth,'token'));
ck("auth: Willkommen/kayıt maili",has($auth,'Willkommen')||has($auth,'bestätigen')||has($auth,'Anmeldung'));
ck("Relay (GoDaddy) kullanılıyor",has($auth,'relay-hosting')||has($db,'relay-hosting')||has($cfg,'relay-hosting'));
ck("Şifre sıfırlama (vergessen)",has($auth,'reset')||has($auth,'vergessen'));
if(has($auth,'register')) dump($auth,'register',40,900,"auth register bloğu");
if(has($auth,'sendMail')) dump($auth,'function sendMail',0,700,"sendMail tanımı");

H("12) FİYATLANDIRMA / STRIPE");
ck("Stripe checkout",            has($idx,'stripe')||has($idx,'checkout'));
ck("Formular Basis price ID",    has($idx,'price_1TgyGd')||has($api,'price_1TgyGd'));
ck("Formular Plus price ID",     has($idx,'price_1TgyKu')||has($api,'price_1TgyKu'));
ck("Eski fiyat (9,99) temiz",    !has($idx,'9,99')&&!has($idx,'9.99'));
ck("Plan: Basic 13,99",          has($idx,'13,99'));
ck("Plan: Pro 29,99",            has($idx,'29,99'));
ck("Plan: Elite 99,99",          has($idx,'99,99'));

H("13) STAATLICHE FORMULARE");
ck("getMonth fonksiyonu",        has($idx,'function getMonth('));
ck("showStaatKat",               has($idx,'showStaatKat'));
ck("STAATLICHE_FORMS verisi",    has($idx,'STAATLICHE_FORMS'));
ck("openStaatForm açık erişim",  has($idx,'window.location.href = fileUrl'));

H("14) PROFİL");
ck("showProfileForm",            has($idx,'showProfileForm'));
ck("Profil alanları f1..f7",     has($idx,'f1')&&has($idx,'f7'));

H("15) LOGO / MARKA");
ck("Logo (SVG/img) üst barda",   has($idx,'<svg')&&(has($idx,'logo')||has($idx,'brand')));
ck("Favicon tanımlı",            has($idx,'favicon')||has($idx,'icon'));

/* ── Ek: ana sohbet bağlam taşıma ── */
H("16) ANA SOHBET BAĞLAM (follow-up)");
ck("Mesaj geçmişi gönderiliyor (messages:)", has($idx,'messages:'));
dump($idx,"action:'chat'",200,500,"ana sohbet gönderimi");

echo "\n".str_repeat('═',58)."\n";
echo "RAPOR BİTTİ. Tümünü kopyalayıp bana gönder.  SİL: rm audit.php\n";
echo str_repeat('═',58)."\n";
