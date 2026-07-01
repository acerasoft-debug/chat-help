<?php
/**
 * ChatHelp — Ödeme donması düzeltmesi: Stripe'tan geri dönünce (bfcache/geri tuşu)
 * paywall ekranı takılı kalmasın
 * -----------------------------------------------------------------------------
 *  Kök neden: window.location.href=d.url ile Stripe'a tam sayfa yönlendirme yapılıyor.
 *  Kullanıcı ödeme yapmadan geri dönerse (tarayıcı geri tuşu / mobil uygulama geri tuşu),
 *  sayfa çoğu zaman "bfcache"den (dondurulmuş önceki hâli) geri geliyor ve paywall-modal
 *  hâlâ ekranda/tıklanamaz kalabiliyor — bunu tespit edip temizleyen kod yoktu.
 *  Bu yama: 'pageshow' olayını dinler (özellikle event.persisted=true -> bfcache dönüşü),
 *  her durumda paywall-modal'ı kaldırır + bekleyen durumu (_pendingDoc) temizler.
 *
 * KULLANIM: chat-help.com/chat/apply-payment-freeze-fix.php -> opcache-reset.php. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-payment-freeze-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,"CH_PAYMENT_FREEZE_FIX")!==false) exit("Zaten ekli.\n");
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-payfreeze-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_PAYMENT_FREEZE_FIX — Stripe'tan geri dönünce (bfcache/geri tuşu) paywall takılı kalmasın */
try{(function(){
  function cleanupStuckPaywall(){
    try{
      var m = document.getElementById("paywall-modal");
      if(m) m.remove();
      /* Yarım kalmış "beklemede" durumu da temizle -> kullanıcı normal akışa döner */
      if(typeof window!=="undefined") window._pendingDoc = null;
    }catch(e){}
  }
  /* pageshow: her sayfa gösteriminde çalışır; event.persisted=true -> bfcache'den geldi (asıl senaryo) */
  window.addEventListener("pageshow", function(ev){
    cleanupStuckPaywall();
  });
  /* Ekstra güvence: sekme tekrar görünür olduğunda da kontrol et */
  document.addEventListener("visibilitychange", function(){
    if(document.visibilityState === "visible") cleanupStuckPaywall();
  });
})();}catch(e){}
CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
if($src!==$start){ file_put_contents($file,$src); echo "Odeme donmasi duzeltmesi eklendi (CH_PAYMENT_FREEZE_FIX).\n"; }
else echo "degisiklik yok\n";
echo "\nSONRA: opcache-reset.php. SIL: rm apply-payment-freeze-fix.php\n";
echo "Test: bir belge sec -> odeme ekranini ac -> Stripe sayfasina git -> ODEMEDEN geri tusuyla don -> ekran normal/tiklanabilir olmali.\n";
