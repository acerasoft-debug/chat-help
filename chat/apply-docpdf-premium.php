<?php
/**
 * ChatHelp — apply-docpdf-premium (CH_DOC_PREMIUM)
 *  (a) chPrintDoc'u v3'e yukseltir: KURUMSAL & ESTETIK PDF — ustte altin antet
 *      cizgisi, ince tipografi, zarif kenar bosluklari, tek-sayfa, ince footer.
 *      Mobil yeni-sekme + gorunur "Als PDF speichern" butonu korunur.
 *  (b) Sonuc ekranindaki PDF butonunu BELIRGIN yapar (buyuk, tam-genislik, altin).
 *  Eski ch-pdf-mobile2 blogunu cikarir, v3'u koyar. Sadece override + CSS.
 * KULLANIM: pull2.php?key=...&files=apply-docpdf-premium.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-docpdf-premium BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_DOC_PREMIUM')!==false) exit("Zaten ekli (CH_DOC_PREMIUM).\n");

/* eski ch-pdf-mobile2 (veya mobile) blogunu cikar */
foreach(['<script id="ch-pdf-mobile2">','<script id="ch-pdf-mobile">'] as $st){
    $sp=strpos($src,$st);
    if($sp!==false){ $cl='</scr'.'ipt>'; $ep=strpos($src,$cl,$sp); if($ep!==false){ $end=$ep+strlen($cl); if(substr($src,$end,1)==="\n")$end++; $src=substr($src,0,$sp).substr($src,$end); echo "  ✓ eski $st blogu cikarildi\n"; } }
}

$block = <<<'HTMLBLOCK'
<style id="ch-doc-premium-css">
/* CH_DOC_PREMIUM — sonuc ekraninda PDF butonunu belirginlestir */
.racts{flex-wrap:wrap!important}
.racts .act.gold{order:99;flex:1 1 100%!important;justify-content:center!important;gap:8px;font-size:15px!important;font-weight:800!important;padding:15px 18px!important;margin-top:10px!important;border-radius:13px!important;background:linear-gradient(135deg,#d4a84a,#ecc060)!important;color:#07070e!important;box-shadow:0 8px 22px rgba(212,168,74,.34)!important;letter-spacing:.2px}
.racts .act.gold svg{stroke:#07070e!important;width:19px!important;height:19px!important}
.racts .act.gold::after{content:" · Als PDF speichern"}
</style>
<script id="ch-pdf-mobile3">
/* CH_DOC_PREMIUM — chPrintDoc v3: kurumsal/estetik PDF + mobil-guvenli */
try{(function(){
  function isMobile(){ try{ return /Android|iPhone|iPad|iPod|Mobile|Silk|Kindle|Opera Mini/i.test(navigator.userAgent||''); }catch(e){ return false; } }
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  var SAVE={de:'📄 Als PDF speichern',tr:'📄 PDF olarak kaydet',en:'📄 Save as PDF',fr:'📄 Enregistrer en PDF',es:'📄 Guardar como PDF',it:'📄 Salva come PDF',nl:'📄 Opslaan als PDF'};
  var CLOSE={de:'Schließen',tr:'Kapat',en:'Close',fr:'Fermer',es:'Cerrar',it:'Chiudi',nl:'Sluiten'};
  /* Kurumsal/estetik: ustte altin antet cizgisi, ince tipografi, tek-sayfa, zarif footer */
  var PREMIUM='<style>@page{size:A4;margin:0}@media print{html,body{margin:0!important;padding:0!important}#ch-pbar{display:none!important}}'
    +'body{background:#fff}'
    +'body::before{content:"";position:fixed;top:0;left:0;right:0;height:5px;background:linear-gradient(90deg,#c9a24a,#e6c46e 55%,#c9a24a);z-index:9}'
    +'.a4{min-height:auto!important;padding:92px 86px 104px!important;line-height:1.72!important;color:#15151c!important;font-size:12.6px!important;letter-spacing:.1px}'
    +'.a4>*:first-child{margin-top:0}'
    +'.mp-foot{border-top:1px solid #dcdce4!important;color:#9a9aa8!important;font-size:8.5px!important;letter-spacing:.5px}'
    +'</style>';
  function harden(html){ var H=String(html); return /<\/head>/i.test(H) ? H.replace(/<\/head>/i, PREMIUM+'</head>') : PREMIUM+H; }
  function barHtml(){ var s=(SAVE[lang()]||SAVE.de); return '<div id="ch-pbar" style="position:sticky;top:0;z-index:99;display:flex;padding:8px;background:#0d0d1c">'+'<button onclick="try{window.focus();window.print();}catch(e){}" style="flex:1;background:linear-gradient(135deg,#d4a84a,#ecc060);color:#07070e;border:none;border-radius:10px;padding:14px;font-weight:800;font-size:15px;font-family:sans-serif;cursor:pointer">'+s+'</button></div>'; }
  function toTab(H){ var wb=/<body[^>]*>/i.test(H)?H.replace(/(<body[^>]*>)/i,'$1'+barHtml()):barHtml()+H; var ap='<scr'+'ipt>window.onload=function(){setTimeout(function(){try{window.focus();window.print();}catch(e){}},500);};<\/scr'+'ipt>'; return wb+ap; }
  function overlay(html){
    try{
      var old=document.getElementById('ch-pdf-ov'); if(old) old.remove();
      var ov=document.createElement('div'); ov.id='ch-pdf-ov';
      ov.style.cssText='position:fixed;inset:0;z-index:2147483600;background:#2a2a2a;display:flex;flex-direction:column';
      var bar=document.createElement('div'); bar.style.cssText='display:flex;gap:8px;padding:10px;background:#0d0d1c';
      var bp=document.createElement('button'); bp.textContent=(SAVE[lang()]||SAVE.de);
      bp.style.cssText='flex:1;background:linear-gradient(135deg,#d4a84a,#ecc060);color:#07070e;border:none;border-radius:10px;padding:13px;font-weight:800;font-size:15px;font-family:sans-serif';
      var bc=document.createElement('button'); bc.textContent=(CLOSE[lang()]||CLOSE.de);
      bc.style.cssText='background:#1a1a30;color:#c8c8e0;border:1px solid rgba(255,255,255,.15);border-radius:10px;padding:13px 18px;font-size:14px;font-family:sans-serif';
      bar.appendChild(bp); bar.appendChild(bc);
      var fr=document.createElement('iframe'); fr.style.cssText='flex:1;width:100%;border:0;background:#fff';
      ov.appendChild(bar); ov.appendChild(fr); document.body.appendChild(ov);
      var d=fr.contentWindow.document; d.open(); d.write(harden(html)); d.close();
      bc.onclick=function(){ ov.remove(); };
      bp.onclick=function(){ try{ fr.contentWindow.focus(); fr.contentWindow.print(); }catch(e){ try{ window.print(); }catch(e2){} } };
    }catch(e){}
  }
  window.chPrintDoc=function(html){
    try{
      var H=harden(String(html));
      if(isMobile()){
        var w=null; try{ w=window.open('','_blank'); }catch(e){ w=null; }
        if(w&&w.document){ try{ w.document.open(); w.document.write(toTab(H)); w.document.close(); return; }catch(e){} }
        overlay(String(html)); return;
      }
      var oldf=document.getElementById('ch-print-frame'); if(oldf) oldf.remove();
      var f=document.createElement('iframe'); f.id='ch-print-frame';
      f.style.cssText='position:fixed;right:0;bottom:0;width:1px;height:1px;border:0;opacity:0';
      document.body.appendChild(f);
      var dd=f.contentWindow.document; dd.open(); dd.write(H); dd.close();
      setTimeout(function(){ try{ f.contentWindow.focus(); f.contentWindow.print(); }catch(e){} },500);
    }catch(e){ try{ overlay(String(html)); }catch(e2){} }
  };
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'dp').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
$bk=@file_put_contents($file.'.bak-docpremium-'.date('Ymd-His'), $src);
if ($bk===false) echo "  ⚠ yedek yazilamadi — devam\n";
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_DOC_PREMIUM')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_DOC_PREMIUM diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Kurumsal/estetik PDF (altin antet + ince tipografi + tek sayfa) + BELIRGIN PDF butonu.\n";
