<?php
/**
 * ChatHelp — apply-lang-persist (CH_LANG_PERSIST)
 *  • setLang: dil secilince ch_uilang da guncellenir -> TUM enjekte ceviri
 *    katmanlari (form chrome, jobs, onboarding, i18n) ayni dile gecer.
 *    Deger degistiyse tek seferlik yenileme ile arayuz KOMPLE o dile doner.
 *  • setCountry: elle ulke secimi ch_cc_manual=1 yazar -> geo-detect bir daha
 *    ASLA uzerine yazamaz; hukuk ulkesi kullanici degistirene kadar kalici.
 * KULLANIM: pull-updates.php?files=apply-lang-persist.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-lang-persist BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_LANG_PERSIST') !== false) exit("Zaten ekli (CH_LANG_PERSIST).\n");
file_put_contents($file . '.bak-langpersist-' . date('Ymd-His'), $src);

$bundle = <<<'HTML'
<script id="ch-lang-persist">
/* CH_LANG_PERSIST — dil secimi ch_uilang'a yazilir (tam ceviri); ulke secimi kalici (ch_cc_manual) */
try{(function(){
  function wrapLang(){
    try{
      if(typeof setLang!=="function" || setLang.__persist) return;
      var _s=setLang;
      var w=function(l){
        var r=_s.apply(this,arguments);
        try{
          var old=null; try{ old=localStorage.getItem("ch_uilang"); }catch(e){}
          try{ localStorage.setItem("ch_uilang", l); }catch(e){}
          try{ localStorage.setItem("ch_lm","1"); }catch(e){}
          if(old!==l){ setTimeout(function(){ try{ location.reload(); }catch(e){} }, 350); }
        }catch(e){}
        return r;
      };
      w.__persist=1; setLang=w; if(typeof window!=="undefined") window.setLang=w;
    }catch(e){}
  }
  function wrapCountry(){
    try{
      if(typeof setCountry!=="function" || setCountry.__persist) return;
      var _s=setCountry;
      var w=function(cc){
        var r=_s.apply(this,arguments);
        try{ localStorage.setItem("ch_cc_manual","1"); }catch(e){}
        return r;
      };
      w.__persist=1; setCountry=w; if(typeof window!=="undefined") window.setCountry=w;
    }catch(e){}
  }
  function boot(){ wrapLang(); wrapCountry(); setInterval(function(){ wrapLang(); wrapCountry(); },2000); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",boot); else boot();
})();}catch(e){}
</script>
HTML;

$n = 0;
$src = preg_replace('/<\/body>/', $bundle . "\n</body>", $src, 1, $n);
if (!$n) exit("HATA: </body> bulunamadi — yazilmadi.\n");
file_put_contents($file, $src);
echo "✓ CH_LANG_PERSIST eklendi.\n";
echo "Test: Dil menusunden Türkçe sec -> sayfa yenilenir, TUM arayuz Türkçe olmali.\n";
echo "      Ulke sec -> uygulamayi kapat/ac -> ulke ayni kalmali (geo ezemez).\n";
