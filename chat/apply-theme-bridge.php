<?php
/**
 * ChatHelp — apply-theme-bridge (CH_THEME_BRIDGE) — "lacivert disinda diger
 *  renkler calismiyor" duzeltmesi. KOK NEDEN: sitede IKI tema sistemi var ve
 *  ayni localStorage 'ch_theme' anahtarini FARKLI degerlerle yaziyor:
 *  yeni sistem (data-chtheme + chApplyTheme) 'anthracite'/'navy'/'white';
 *  eski Konto paneli (chSetTheme) 'anth' gibi kisaltmalar. Eski panelden
 *  secim yapilinca deger yeni sistemin CSS bloklariyla ESLESMEZ -> tema
 *  degismez, sayfa varsayilan koyu/lacivertte kalir.
 *  DUZELTME (2 adim, ekleme agirlikli):
 *   [1] Tema-init'e deger NORMALIZASYONU: kayitli 'anth'->'anthracite',
 *       'dark'/'lacivert'->'navy', 'weiss'/'light'->'white' cevrilir
 *       (count-guard'li str_replace, CH_THEME_AUTO metni birebir bilinir).
 *   [2] </body> oncesine kopru script'i: eski chSetTheme cagrildiginde
 *       deger normalize edilip chApplyTheme'e de iletilir -> eski panel
 *       de artik yeni sistemi dogru surer. Sadece sarmalama, eski davranis
 *       bozulmaz.
 * KULLANIM: pull2.php?key=...&files=apply-theme-bridge.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-theme-bridge BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_THEME_BRIDGE')!==false) exit("Zaten ekli (CH_THEME_BRIDGE).\n");

$fail=[];

/* ── [1] tema-init'e deger normalizasyonu ── */
$o1 = <<<'ANC'
  var t=null; try{ t=localStorage.getItem('ch_theme'); }catch(e){}
  if(!t){ try{ t=(window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches)?'white':'navy'; }catch(e){ t='navy'; } }
ANC;
$n1 = <<<'ANC'
  var t=null; try{ t=localStorage.getItem('ch_theme'); }catch(e){}
  /*CH_THEME_BRIDGE*/ try{ var _tm={anth:'anthracite',dark:'navy',lacivert:'navy',weiss:'white',light:'white'}; if(t&&_tm[t]){ t=_tm[t]; try{ localStorage.setItem('ch_theme',t); }catch(e){} } }catch(e){}
  if(!t){ try{ t=(window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches)?'white':'navy'; }catch(e){ t='navy'; } }
ANC;
$c1 = substr_count($src,$o1);
if ($c1!==1) { echo "  ✗ [1] tema-init anchor ($c1)\n"; $fail[]='1'; }
else { $src = str_replace($o1,$n1,$src); echo "  ✓ [1] Kayitli tema degeri normalize ediliyor (anth->anthracite vb.)\n"; }

/* ── [2] eski chSetTheme koprusu ── */
$block = <<<'HTMLBLOCK'
<script id="ch-theme-bridge-js">
/* CH_THEME_BRIDGE — eski tema paneli (chSetTheme) yeni sistemi (chApplyTheme) de sursun */
try{(function(){
  var MAP={anth:'anthracite',anthracite:'anthracite',navy:'navy',dark:'navy',lacivert:'navy',white:'white',weiss:'white',light:'white'};
  function wrap(){
    try{
      if(typeof window.chSetTheme!=='function' || window.chSetTheme.__bridge) return false;
      var _o=window.chSetTheme;
      var w=function(v){
        var r; try{ r=_o.apply(this,arguments); }catch(e){}
        try{
          var t=MAP[String(v||'').toLowerCase()];
          if(t && typeof window.chApplyTheme==='function') window.chApplyTheme(t);
        }catch(e){}
        return r;
      };
      w.__bridge=1; window.chSetTheme=w;
      return true;
    }catch(e){ return false; }
  }
  var n=0;(function loop(){ if(!wrap() && n++<120) setTimeout(loop,500); })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) { echo "  ✗ [2] </body> yok\n"; $fail[]='2'; }
else { $src = substr($src,0,$pos).$block."\n".substr($src,$pos); echo "  ✓ [2] Eski panel koprusu eklendi (chSetTheme -> chApplyTheme)\n"; }

if ($fail) { echo "\nHATA: eslesmeyen adimlar: ".implode(', ',$fail)." — index DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(),'tb').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-thbridge-'.date('Ymd-His'), (string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_THEME_BRIDGE')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "\n  ✓ DOGRULAMA: CH_THEME_BRIDGE diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Tema secimi artik HER panelden dogru calisir:\n";
echo "   Antrasit / Lacivert / Beyaz — hangisini secersen aninda uygulanir ve kalici olur.\n";
