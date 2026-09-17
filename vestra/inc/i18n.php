<?php
/**
 * VESTRA — lightweight i18n.
 * Language is chosen via ?lang=xx and remembered in the `vlang` cookie.
 * t("English string") returns the translation for the active language,
 * falling back to the English string itself when no translation exists.
 * Dictionaries live in inc/lang/{de,fr,it,es}.php  (English = the keys).
 */
if(!function_exists('vlang')){

/* Languages the SITE serves, in menu order. NL/CS/PL/EL were removed at the
   operator's decision: visitors from those countries now get English. PT came back
   and RU were added on 3 Sep 2026 (operator: "6-7 dil yap — rusca ve portekizce
   ekle"), then AR the same day ("arapcada yap"), so the site serves eight. Order is
   deliberate — EN first, the newest last. Arabic is the one right-to-left language:
   vlang_dir() gives the <html dir> value and the layout mirrors through flex/grid. Dropping a code here is enough; t() falls back to the English key, so a
   stale dictionary file can never surface a half translated page. Completeness of
   every dictionary against inc/lang/de.php is enforced by tests/seo_landing_test.php. */
function vlang_list(){ return ['en'=>'EN','fr'=>'FR','es'=>'ES','it'=>'IT','de'=>'DE','pt'=>'PT','ru'=>'RU','ar'=>'AR','ja'=>'JA']; }

/* Writing direction of the active language, for <html dir="…">. Only Arabic is RTL. */
function vlang_dir(){ return vlang() === 'ar' ? 'rtl' : 'ltr'; }

/* hreflang codes the pages announce => site language that serves them. Regional
   variants (de-AT, fr-BE, en-NL …) all point at the SAME language page: hreflang is a
   targeting hint, not a promise of separate content, and it is how a Belgian boutique
   searching in French or a Dutch one searching in English gets told this page is for
   them. The English variants cover the European markets whose own languages were
   dropped (NL, CS, PL, EL — those visitors already get English). Derived from
   vlang_list(): a language removed there disappears from here on its own.

   17 Eyl 2026 (operator: "sadece avrupa degil avustralya japonya dubai qatar ve usa da
   olsun... avrupada kusursuz istiyorum"). Iki sey degisti:
     1. AVRUPA TAM: vestra_europe_codes()'taki AB 27 + EFTA + Birlesik Krallik'in HER
        ulkesi en az bir bolgesel etikette geciyor. Eskiden Luksemburg, Malta, Kibris,
        Hirvatistan, Slovenya, Slovakya, Bulgaristan, Baltiklar, Izlanda ve
        Liechtenstein hicbir etikette yoktu -- yani "Avrupa'da kusursuz" iddiasi 13
        ulkede bostu. tests/seo_landing_test.php bunu artik ulke ulke sayiyor.
     2. HEDEF PAZARLAR: en-US, en-AU, en-AE, en-QA (ve en-SA/en-SG -- KURAL 2h'nin
        kapisi kendiliginden acilan ulkeleri), sonra en-IL, en-KR, es-<Guney Amerika>.
        Korfez'de ticari arama buyuk olcude INGILIZCE yapiliyor; ar-AE/ar-QA zaten
        vardi, Ingilizce varyant eksikti. ja-JP tek basina kaliyor (Japonca baska
        pazarin dili degil); en-JP BILEREK yok -- x-default zaten Ingilizce sayfayi
        isaret ediyor. Pazar listesinin kendisi inc/seo.php'de (vestra_seo_markets);
        test, oradaki her ulkenin burada bir etiketi oldugunu sayiyor.
   Bir ulkenin BIRDEN FAZLA dilde gecmesi dogru ve bilerek (BE: en+fr, CH: de+fr+it,
   LU: fr+de): hreflang "bu dili okuyan, bu ulkedeki kisi" demek, ulke->dil tablosu
   degil. O tablo ayri ve elle yazili (vlang_country_lang). */
function vlang_hreflang_map(){
  $regions = [
    'en' => ['GB','IE','NL','BE','DK','SE','FI','NO','PL','CZ','GR','HU','RO',
             /* Avrupa'nin geri kalani: dili sitede olmayan AB/EFTA ulkeleri Ingilizce sayfaya. */
             'LU','MT','CY','HR','SI','SK','BG','LT','LV','EE','IS',
             /* Hedef pazarlar (17 Eyl 2026): ABD, Avustralya, Korfez; ayni gun eklenen
                Israil, Singapur ve Guney Kore (operator: "israil, singapur, g.koreyi de
                ekle"). Ibranice/Korece sitede yok -- oralarda ticari arama Ingilizce. */
             'US','AU','AE','QA','SA','SG','IL','KR',
             /* Guney Amerika'nin Ispanyolca/Portekizce OLMAYAN ikisi: Guyana
                (Ingilizce) ve Surinam (Felemenkce -- sitede yok, Ingilizceye
                duser). Testi yazmasaydim atlanacaklardi: /wholesale-to/south-america
                12 ulkeyi kapsadigini soyluyor ama ikisinde hicbir etiket yoktu. */
             'GY','SR'],
    'de' => ['DE','AT','CH','LI','LU'],
    'fr' => ['FR','BE','CH','LU','MC'],
    'it' => ['IT','CH','SM'],
    /* Guney Amerika'nin Ispanyolca konusan ulkeleri (operator, 17 Eyl 2026: "brezilya ve
       guney amerika ... ekle"); Brezilya pt-BR olarak zaten vardi. Liste
       vestra_south_america_codes() eksi Brezilya/Guyana/Surinam (Portekizce/Ingilizce/
       Felemenkce) -- elle yazili, cunku o fonksiyon burada yuklu degil ve i18n.php
       products.php'den ONCE yukleniyor. */
    'es' => ['ES','AR','BO','CL','CO','EC','PY','PE','UY','VE'],
    'pt' => ['PT','BR'],
    'ru' => ['RU','BY','KZ'],
    'ar' => ['AE','SA','QA','KW','BH','OM','EG','JO','MA'],
    /* 5 Eyl 2026: Japonca eklendi (operator: "Japon dilini tum site icin uygula").
       Bolge listesi yalniz JP -- ja-JP. Japonca baska bir ulkenin resmi dili
       degil, yani hreflang'i baska pazarlara uzatmak yanlis sinyal olurdu. */
    'ja' => ['JP'],
  ];
  $map = [];
  foreach (array_keys(vlang_list()) as $l) {
    $map[$l] = $l;
    foreach ($regions[$l] ?? [] as $r) $map[$l.'-'.$r] = $l;
  }
  return $map;
}

/* Open Graph yerel ayari (og:locale). TEK KAYNAK: head.php ve index.php ikisi de
   buradan okuyor. 17 Eyl 2026'ya kadar iki ayri kopya vardi ve head.php'ninkinde
   'ja' YOKTU -- Japonca her alt sayfa og:locale=en_US basiyordu, yalniz ana sayfa
   ja_JP diyordu. Ayni olgunun ikinci kopyasi, bu depoda defalarca kayitli hata.
   ar_AR: Facebook'un kendi yerel ayar listesindeki Arapca kodu (ulke koduyla degil). */
function vlang_og_locale(string $l): string {
  static $m = ['en'=>'en_US','fr'=>'fr_FR','es'=>'es_ES','it'=>'it_IT','de'=>'de_DE',
               'pt'=>'pt_PT','ru'=>'ru_RU','ar'=>'ar_AR','ja'=>'ja_JP'];
  return $m[$l] ?? 'en_US';
}

/* Dilin KENDI adiyla yazimi (endonym): "Deutsch", "日本語". Pazar sayfasindaki
   "VESTRA su dillerde" cumlesi bunu basiyor -- dokuz dil adini dokuz dile cevirmek
   81 sozluk anahtari demekti ve ziyaretci kendi dilini zaten kendi adiyla tanir.
   vlang_list()'ten turemeyen bir dil buraya eklenmeden liste eksik kalmasin diye
   bilinmeyen kod BUYUK HARFLI kodun kendisini doner (bos degil). */
function vlang_native_name(string $l): string {
  static $m = ['en'=>'English','fr'=>'Français','es'=>'Español','it'=>'Italiano','de'=>'Deutsch',
               'pt'=>'Português','ru'=>'Русский','ar'=>'العربية','ja'=>'日本語'];
  return $m[$l] ?? strtoupper($l);
}

/* Best match for the visitor's device/browser language (phone language travels
 * in the Accept-Language header). Maps regional tags (de-AT, fr-CA…) to our base
 * languages and respects q-weights. Returns null if nothing matches. */
function vlang_detect(){
  $langs=vlang_list();
  $h=$_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
  if($h==='') return null;
  $best=null; $bestQ=-1;
  foreach(explode(',',$h) as $part){
    $part=trim($part); if($part==='') continue;
    $q=1.0;
    if(preg_match('/;\s*q=([0-9.]+)/',$part,$m)) $q=(float)$m[1];
    $code=strtolower(trim(preg_replace('/;.*$/','',$part)));   // "de-at" → de-at
    $base=substr($code,0,2);                                    // → "de"
    if(isset($langs[$base]) && $q>$bestQ){ $best=$base; $bestQ=$q; }
  }
  return $best;
}

/* Ulke kodu -> sitenin dili. Tablo bilerek ELLE yaziliyor: hreflang bolge
   listesinden turetmek iki ulkeyi yanlis dile baglardi -- Belcika hem 'en' hem
   'fr' altinda, Isvicre 'de'/'fr'/'it' altinda geciyor ve turetme sirayla ilk
   geleni secerdi. Kararlar:
     CH -> de  (en buyuk dil grubu, ~%62)
     BE -> en  (Flaman cogunluk; Felemenkce sitede yok, Fransizca dayatmak
                nufusun buyuk yarisina yabanci bir dil olurdu)
   Rusca konusulan eski SSCB pazarlari ve Arapca konusulan ulkeler, sitenin o
   dilleri servis ettigi icin listeye alindi; sitenin dili olmayan bir ulke
   (or. TR, KR) null doner ve ziyaretci Ingilizce goruyor.

   JP 17 Eyl 2026'da EKLENDI ve eksikligi gercek bir kusurdu: Japonca 5 Eylul'de
   siteye eklendi (1271 anahtar, tam sozluk) ama bu tablo guncellenmedi -- yani
   tarayicisi dil bildirmeyen Japonyali bir ziyaretci, site tamamen Japonca
   oldugu halde Ingilizce goruyordu. Yorumun kendisi de "JP null doner" diye
   yaziyordu, yani kusur yorumda ONAYLANMIS halde duruyordu. Bir dil eklerken
   "bu olgu baska nerede yazili" diye sorulmadiginda olan sey. */
function vlang_country_lang(string $cc): ?string {
  static $map = [
    'DE'=>'de','AT'=>'de','CH'=>'de','LI'=>'de',
    'FR'=>'fr','LU'=>'fr','MC'=>'fr','BE'=>'en',
    'ES'=>'es','MX'=>'es','AR'=>'es','CL'=>'es','CO'=>'es','PE'=>'es','UY'=>'es',
    'IT'=>'it','SM'=>'it',
    'JP'=>'ja',
    'PT'=>'pt','BR'=>'pt','AO'=>'pt','MZ'=>'pt',
    'RU'=>'ru','BY'=>'ru','KZ'=>'ru','KG'=>'ru','UZ'=>'ru','AM'=>'ru','AZ'=>'ru','GE'=>'ru','MD'=>'ru',
    'AE'=>'ar','SA'=>'ar','QA'=>'ar','KW'=>'ar','BH'=>'ar','OM'=>'ar','EG'=>'ar','JO'=>'ar',
    'MA'=>'ar','TN'=>'ar','DZ'=>'ar','LB'=>'ar','IQ'=>'ar','LY'=>'ar','YE'=>'ar','PS'=>'ar','SD'=>'ar',
  ];
  $cc = strtoupper(trim($cc));
  if ($cc === '' || !isset($map[$cc])) return null;
  return isset(vlang_list()[$map[$cc]]) ? $map[$cc] : null;   // dil listeden cikarsa kendiliginden duser
}

/* Ziyaretcinin ULKESINDEN dil tahmini -- yalnizca tarayici hicbir dil
   soylemediginde. Sira bilerek boyle: Accept-Language kisinin OKUDUGU dili
   bildirir, IP yalnizca nerede oldugunu. Dubai'deki bir Alman'a Arapca
   gostermek, acikca bildirdigi tercihi cografyayla ezmek olurdu. Tarayici
   susuyorsa elde baska ipucu yoktur ve ulke en iyi tahmindir.
   Maliyet: yalnizca ilk ziyarette, cerez ve ?lang yokken. Sonuc
   vestra_ip_intel() icinde IP basina 30 gun onbelleklenir, zaman asimi 1 sn.
   BOTLAR ATLANIR: Accept-Language gondermeyenlerin cogu tarayici degil, ve her
   biri bosuna bir cografi API sorgusu demek olurdu. CLI de atlanir -- cron
   betikleri asla aga cikmasin. */
function vlang_from_ip(){
  if (PHP_SAPI === 'cli') return null;
  $sec = __DIR__.'/security.php';
  if (!function_exists('vestra_ip_intel') && is_readable($sec)) require_once $sec;
  if (!function_exists('vestra_ip_intel') || !function_exists('vestra_is_bot')) return null;
  if (vestra_is_bot((string)($_SERVER['HTTP_USER_AGENT'] ?? ''))) return null;
  $ip = function_exists('vestra_client_ip')
      ? vestra_client_ip()
      : (string)($_SERVER['REMOTE_ADDR'] ?? '');
  if ($ip === '') return null;
  return vlang_country_lang((string)(vestra_ip_intel($ip, 1)['cc'] ?? ''));
}

function vlang(){
  static $l=null; if($l!==null) return $l;
  $langs=vlang_list(); $l='en';
  if(isset($_GET['lang']) && isset($langs[$_GET['lang']])){
    /* explicit choice — remember it for a year */
    $l=$_GET['lang'];
    if(!headers_sent()) @setcookie('vlang',$l,time()+31536000,'/');
    $_COOKIE['vlang']=$l;
  } elseif(isset($_COOKIE['vlang']) && isset($langs[$_COOKIE['vlang']])){
    /* returning visitor — honour their saved choice */
    $l=$_COOKIE['vlang'];
  } else {
    /* first visit — device/browser language first, then the visitor's country */
    $d=vlang_detect();
    if($d===null) $d=vlang_from_ip();
    if($d!==null){
      $l=$d;
      if(!headers_sent()) @setcookie('vlang',$l,time()+31536000,'/');
      $_COOKIE['vlang']=$l;
    }
  }
  return $l;
}

function vtrans(){
  static $d=null; if($d!==null) return $d;
  $d=[]; $l=vlang();
  if($l!=='en'){ $f=__DIR__.'/lang/'.$l.'.php'; if(is_readable($f)){ $x=require $f; if(is_array($x)) $d=$x; } }
  return $d;
}

/* translate (with English fallback) */
function t($s){ $d=vtrans(); return isset($d[$s]) ? $d[$s] : $s; }

/* Dil secici. Her baglanti ziyaretciyi AYNI sayfada tutar ve mevcut sorgu
 * parametrelerini korur (or. ?doc=imprint), yalnizca ?lang= degisir.
 *
 * Iki bicim var:
 *   'menu' (varsayilan) — kapali dururken tek bir kod ("EN") + ok; tiklaninca
 *      acilan panel. Sekiz dilin sekizini de yan yana basmak ust cubukta ciddi
 *      yer kapliyordu ve dil sayisi arttikca daha da buyuyecekti.
 *   'flat' — hepsi yan yana. Hesap panelindeki dil secici bunu kullaniyor:
 *      orasi bir AYAR bolumu, yer sikintisi yok ve secenekleri gormek iyi.
 *
 * Isaretleme para birimi secicisiyle (vestra_cur_switcher) bilerek AYNI:
 * <details>/<summary>, ayni sinif duzeni, ayni CSS kurallari, ayni "disariya
 * tiklayinca kapan" JS'i. Ikisi ust cubukta yan yana duruyor; farkli davranan
 * iki menu olmasi kullaniciya da, bakim yapana da bedel olurdu. */
function vlang_switcher($class='langsw', $mode='menu'){
  $cur=vlang();
  $langs=vlang_list();
  $path=parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
  $base=array_filter($_GET, function($k){ return $k!=='lang'; }, ARRAY_FILTER_USE_KEY);

  $links='';
  foreach($langs as $code=>$label){
    $qs=http_build_query($base+['lang'=>$code]);
    $href=htmlspecialchars($path.'?'.$qs, ENT_QUOTES);
    $links.='<a class="lsw'.($code===$cur?' on':'').'" href="'.$href.'" hreflang="'.$code.'">'.$label.'</a>';
  }
  if($mode!=='menu') return '<div class="'.$class.'">'.$links.'</div>';

  $curLabel=$langs[$cur] ?? strtoupper($cur);
  /* Baslikta hangi dilde oldugumuz yaziyor; ekran okuyucu icin de ad veriliyor
     cunku "EN" tek basina bir dugmenin ne ise yaradigini soylemiyor. */
  return '<details class="'.$class.'">'
       . '<summary aria-label="'.htmlspecialchars(t('Language'), ENT_QUOTES).'"'
       . ' title="'.htmlspecialchars(t('Language'), ENT_QUOTES).'">'
       . '<span class="lswcur">'.htmlspecialchars($curLabel).'</span>'
       . '<svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
       . ' stroke-width="3" stroke-linecap="round"><path d="M6 9l6 6 6-6"/></svg></summary>'
       . '<div class="lswmenu">'.$links.'</div></details>';
}

vlang(); // resolve language + set cookie before any output
}

/* The RECIPIENT's language for an outbound email — never the current request's vlang(),
 * which is whoever is browsing right now (could be a different buyer/seller, or the admin
 * triggering a KYB approval). Accounts created before the 'lang' field existed fall back to
 * English rather than silently erroring. */
function vestra_user_lang(?array $acc): string {
  $l = strtolower(substr((string)($acc['lang'] ?? ''), 0, 2));
  return isset(vlang_list()[$l]) ? $l : 'en';
}

/* GIRIS YAPMIS KULLANICIYA "KAYIT OL" GOSTERME.
   Kural basit ama sayfa sayfa uygulanamiyor: her yeni pazarlama sayfasi kendi
   "Register free" dugmesini ekliyor, ve yazan kisi o an misafiri dusunuyor. Iki
   kez duzeltildi, iki kez geri geldi -- cunku duzeltme her seferinde O SAYFAYA
   yazildi, kurala degil. Artik tek fonksiyon: uye ise kendi paneline, degilse
   kayda goturur. Yeni bir sayfa bunu cagirdigi surece kural kendiliginden gecerli.

   NEDEN BURADA (head.php'de degil): once head.php'ye yazilmisti, ama index.php
   kendi <head>'ini basiyor ve head.php'yi hic dahil etmiyor -- ana sayfa bu
   fonksiyonu cagirinca "undefined function" ile OLDU, sayfa marka duvarindan
   itibaren kesildi (71.634 bayt yerine 49.012). i18n.php'yi HER sayfa yukluyor
   (head.php dahil), yani kural artik erisilemedigi icin kirilamaz.

   $type: 'buyer' | 'seller' | '' -> /register?type=... (misafir icin).
   Dondurulen sey tam bir <a> etiketi; cagiran yer sadece etiketi ve sinifi verir. */
function vestra_join_cta(string $guestLabel, string $class = 'btn btn-p', string $type = '', string $style = ''): string {
    /* Kullaniciyi UC kaynaktan cozuyoruz, cunku her sayfa ayni kurulmuyor:
       head.php $AUTH_USER'i global yapar; index.php'nin elinde sadece oturum var.
       Tek kaynaga guvenmek, bu kuralin daha once kacirdigi durumun ta kendisiydi. */
    $u = $GLOBALS['AUTH_USER'] ?? null;
    if ($u === null && function_exists('auth_user')) $u = auth_user();
    $signedIn = is_array($u) || !empty($_SESSION['uid']);
    $st = $style !== '' ? ' style="'.$style.'"' : '';
    if ($signedIn) {
        $utype = is_array($u) ? (string)($u['type'] ?? '') : (string)($_SESSION['utype'] ?? '');
        $href  = $utype === 'seller' ? '/seller' : '/buyer';
        $label = function_exists('t') ? t('Open my dashboard') : 'Open my dashboard';
    } else {
        $href  = '/register'.($type !== '' ? '?type='.rawurlencode($type) : '');
        $label = $guestLabel;
    }
    return '<a class="'.htmlspecialchars($class, ENT_QUOTES).'" href="'.htmlspecialchars($href, ENT_QUOTES).'"'.$st.'>'
         . htmlspecialchars($label) . '</a>';
}
