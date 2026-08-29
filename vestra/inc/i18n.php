<?php
/**
 * VESTRA — lightweight i18n.
 * Language is chosen via ?lang=xx and remembered in the `vlang` cookie.
 * t("English string") returns the translation for the active language,
 * falling back to the English string itself when no translation exists.
 * Dictionaries live in inc/lang/{de,fr,it,es}.php  (English = the keys).
 */
if(!function_exists('vlang')){

/* Languages the SITE serves, in menu order. NL/PT/CS/PL/EL were removed at the
   operator's decision: visitors from those countries now get English. Order is
   deliberate — EN first, DE last. Dropping a code here is enough; t() falls back
   to the English key, so a stale dictionary file can never surface a half
   translated page. */
function vlang_list(){ return ['en'=>'EN','fr'=>'FR','es'=>'ES','it'=>'IT','de'=>'DE']; }

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
    /* first visit — auto-pick from the device/browser language, then remember */
    $d=vlang_detect();
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

/* language switcher markup; each link keeps the visitor on the SAME page and
 * preserves existing query params (e.g. ?doc=imprint), only overriding ?lang= */
function vlang_switcher($class='langsw'){
  $cur=vlang();
  $path=parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
  $base=array_filter($_GET, function($k){ return $k!=='lang'; }, ARRAY_FILTER_USE_KEY);
  $out='<div class="'.$class.'">';
  foreach(vlang_list() as $code=>$label){
    $qs=http_build_query($base+['lang'=>$code]);
    $href=htmlspecialchars($path.'?'.$qs, ENT_QUOTES);
    $out.='<a class="lsw'.($code===$cur?' on':'').'" href="'.$href.'" hreflang="'.$code.'">'.$label.'</a>';
  }
  return $out.'</div>';
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
