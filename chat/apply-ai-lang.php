<?php
/**
 * ChatHelp — AI'ı aktif arayüz dilinde konuştur (sohbet soruları/cevapları)
 * -------------------------------------------------------------------------
 *  Backend zaten sohbet sorularını/cevaplarını $lang ile üretiyor; ama frontend
 *  api.php'ye çoğu zaman lang='de' gönderiyordu. Bu katman, TÜM api.php POST
 *  isteklerine aktif arayüz dilini (ch_uilang / UL) otomatik enjekte eder.
 *  -> 🇫🇷 seçili / Fransa IP iken AI Fransızca sorar & cevaplar (8 dilde çalışır).
 *  Risksiz: yalnız api.php'ye giden JSON gövdesine 'lang' ekler; başka şeye dokunmaz.
 *
 * KULLANIM: chat-help.com/chat/apply-ai-lang.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
@ini_set('display_errors','1'); error_reporting(E_ALL);
echo "ChatHelp — apply-ai-lang BAŞLADI ✓ (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src = file_get_contents($file);
$start = $src;

if (strpos($src, 'ch-ai-lang') !== false) { exit("Zaten ekli (ch-ai-lang). Değişiklik yok.\n"); }
file_put_contents($file . '.bak-ailang-' . date('Ymd-His'), $src);

$bundle = <<<'HTML'
<script id="ch-ai-lang">
(function(){
  if(window.__chAiLang) return; window.__chAiLang=1;
  var SUP={de:1,tr:1,ru:1,ar:1,en:1,fr:1,es:1,it:1,pl:1};
  function getLang(){
    var s=null; try{ s=localStorage.getItem('ch_uilang'); }catch(e){}
    if(s && SUP[s]) return s;
    try{ if(window.UL && SUP[window.UL]) return window.UL; }catch(e){}
    return 'de';
  }
  var _fetch=window.fetch;
  if(typeof _fetch!=='function') return;
  window.fetch=function(input,init){
    try{
      var url=(typeof input==='string')?input:((input&&input.url)||'');
      if(url.indexOf('api.php')!==-1 && init && typeof init.body==='string'){
        var o=null; try{ o=JSON.parse(init.body); }catch(e){ o=null; }
        if(o && typeof o==='object' && !Array.isArray(o)){
          o.lang=getLang();
          init=Object.assign({},init,{body:JSON.stringify(o)});
        }
      }
    }catch(e){}
    return _fetch.call(this,input,init);
  };
})();
</script>
HTML;

$n=0;
$src = preg_replace('/<\/body>/', $bundle."\n</body>", $src, 1, $n);

if ($n>0 && $src!==$start) {
    file_put_contents($file, $src);
    echo "✓ AI dil enjeksiyonu eklendi (ch-ai-lang).  → </body> öncesi\n";
    echo "  • Tüm api.php POST'larına aktif arayüz dili eklenir.\n";
    echo "  • 🇫🇷 seçili / Fransa IP -> AI Fransızca sorar & cevaplar.\n";
} else {
    echo "✗ </body> bulunamadı veya değişiklik olmadı (n=$n).\n";
}

echo "\nSONRA: opcache-reset.php. SİL: rm apply-ai-lang.php\n";
echo "Test: 🇫🇷 seç -> ana ekranda derdini yaz (örn. 'Mon propriétaire augmente le loyer') -> AI Fransızca sorular sormalı.\n";
