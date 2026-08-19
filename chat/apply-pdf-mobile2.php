<?php
/**
 * ChatHelp — apply-pdf-mobile2 (CH_PDF_MOBILE2)
 *  chPrintDoc override v2. Sorunlar: (a) mobilde auto-print tetiklenmeyince
 *  "sadece doc" gorunup gercek PDF cikmiyordu; (b) sigdirma scripti silindigi
 *  + .a4 min-height:1123px oldugu icin 1-2 fazla bos sayfa cikiyordu.
 *  COZUM: yeni sekmenin ustune GORUNUR "Als PDF speichern" butonu + auto-print;
 *  sigdirma scriptini KORUR + min-height:auto → kurumsal tek-sayfa. Eski
 *  ch-pdf-mobile blogunu cikarir, v2'yi </body> oncesine koyar. Sadece override.
 * KULLANIM: pull2.php?key=...&files=apply-pdf-mobile2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-pdf-mobile2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_PDF_MOBILE2')!==false) exit("Zaten ekli (CH_PDF_MOBILE2).\n");

/* ── eski ch-pdf-mobile (v1) blogunu cikar ── */
$startTag='<script id="ch-pdf-mobile">';
$sp=strpos($src,$startTag);
if($sp!==false){
    $close='</scr'.'ipt>';
    $ep=strpos($src,$close,$sp);
    if($ep!==false){
        $end=$ep+strlen($close);
        /* varsa hemen sonrasindaki tek newline'i da al */
        if(substr($src,$end,1)==="\n") $end++;
        $src=substr($src,0,$sp).substr($src,$end);
        echo "  ✓ eski ch-pdf-mobile (v1) blogu cikarildi\n";
    } else echo "  ⚠ v1 kapanis etiketi bulunamadi — v1 birakildi (v2 sonra tanimlanip kazanir)\n";
} else echo "  (v1 blogu yok — temiz kurulum)\n";

$block = <<<'HTMLBLOCK'
<script id="ch-pdf-mobile2">
/* CH_PDF_MOBILE2 — chPrintDoc mobil-guvenli v2: gorunur PDF butonu + tek-sayfa kurumsal */
try{(function(){
  function isMobile(){ try{ return /Android|iPhone|iPad|iPod|Mobile|Silk|Kindle|Opera Mini/i.test(navigator.userAgent||''); }catch(e){ return false; } }
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  var SAVE={de:'📄 Als PDF speichern',tr:'📄 PDF olarak kaydet',en:'📄 Save as PDF',fr:'📄 Enregistrer en PDF',es:'📄 Guardar como PDF',it:'📄 Salva come PDF',nl:'📄 Opslaan als PDF'};
  var CLOSE={de:'Schließen',tr:'Kapat',en:'Close',fr:'Fermer',es:'Cerrar',it:'Chiudi',nl:'Sluiten'};
  function harden(html){
    var css='<style>@page{size:A4;margin:0}@media print{html,body{margin:0!important;padding:0!important}#ch-pbar{display:none!important}}.a4{min-height:auto!important}</style>';
    return /<\/head>/i.test(html) ? html.replace(/<\/head>/i, css+'</head>') : css+html;
  }
  function barHtml(){
    var s=(SAVE[lang()]||SAVE.de);
    return '<div id="ch-pbar" style="position:sticky;top:0;z-index:99;display:flex;padding:8px;background:#0d0d1c">'
      +'<button onclick="try{window.focus();window.print();}catch(e){}" style="flex:1;background:linear-gradient(135deg,#d4a84a,#ecc060);color:#07070e;border:none;border-radius:10px;padding:14px;font-weight:800;font-size:15px;font-family:sans-serif;cursor:pointer">'+s+'</button></div>';
  }
  function toTab(H){
    var withBar = /<body[^>]*>/i.test(H) ? H.replace(/(<body[^>]*>)/i, '$1'+barHtml()) : barHtml()+H;
    var ap='<scr'+'ipt>window.onload=function(){setTimeout(function(){try{window.focus();window.print();}catch(e){}},500);};<\/scr'+'ipt>';
    return withBar+ap;
  }
  function overlay(html){
    try{
      var old=document.getElementById('ch-pdf-ov'); if(old) old.remove();
      var ov=document.createElement('div'); ov.id='ch-pdf-ov';
      ov.style.cssText='position:fixed;inset:0;z-index:2147483600;background:#2a2a2a;display:flex;flex-direction:column';
      var bar=document.createElement('div'); bar.style.cssText='display:flex;gap:8px;padding:10px;background:#0d0d1c';
      var bp=document.createElement('button'); bp.textContent=(SAVE[lang()]||SAVE.de);
      bp.style.cssText='flex:1;background:linear-gradient(135deg,#d4a84a,#ecc060);color:#07070e;border:none;border-radius:10px;padding:13px;font-weight:800;font-size:15px;font-family:sans-serif';
      var bc=document.createElement('button'); bc.textContent=(CLOSE[lang()]||CLOSE.de);
      bc.style.cssText='background:#1a1a30;color:#c0c0e0;border:1px solid rgba(255,255,255,.15);border-radius:10px;padding:13px 18px;font-size:14px;font-family:sans-serif';
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

$tmp = tempnam(sys_get_temp_dir(),'pm2').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }

$bk=@file_put_contents($file.'.bak-pdfmobile2-'.date('Ymd-His'), $src);
if ($bk===false) echo "  ⚠ yedek yazilamadi — devam\n";
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_PDF_MOBILE2')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_PDF_MOBILE2 diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Mobilde: yeni sekme + ustte gorunur 'Als PDF speichern' + auto-print.\n";
echo "   Kurumsal A4/serif korunur, min-height:auto + sigdirma → tek sayfa.\n";
