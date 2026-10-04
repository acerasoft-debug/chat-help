<?php
/**
 * ChatHelp — apply-trvize-pdffix (CH_TRVIZE_PDFFIX) — TR vize PDF hatasi.
 *  KOK NEDEN: TR vize belgesi (chVSF.makePdfHtml) kendi otomatik-yazdirma
 *  <script>'unu icine gomuyor; ama chPrintDoc TUM <script>'leri siliyor ->
 *  belge aciliyor ama yazdirma/PDF HIC tetiklenmiyor ("doc gibi duruyor").
 *  DUZELTME:
 *   [1] chVSF.openForm sarilir -> acik form anahtari window.__vsfCur'a yazilir.
 *   [2] #pnl-vsf ust barina GARANTILI "📄 PDF indir" dugmesi eklenir: belgeyi
 *       chVSF.makePdfHtml(buildPages(k,getD(k))) ile YENIDEN kurar ve YENI
 *       SEKMEDE dogrudan acar (script silinmez -> otomatik yazdirma calisir,
 *       "PDF olarak kaydet" cikar). Masaustunde gizli iframe + print yedegi.
 *   [3] Ayrica chPrintDoc'un TR-vize ciktisina (vfA4) otomatik-yazdirma
 *       enjekte edilir (mevcut buton yolu da calissin).
 *  Tek eklemeli </body> blogu; mevcut ceviri katmani (ai2) korunur. node ✓ + harness ✓.
 * KULLANIM: pull2.php?key=...&files=apply-trvize-pdffix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-trvize-pdffix BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_TRVIZE_PDFFIX')!==false) exit("Zaten ekli (CH_TRVIZE_PDFFIX).\n");
if (strpos($src,'CH_VISA_STUDIO')===false) exit("HATA: CH_VISA_STUDIO yok.\n");

$block = <<<'HTMLBLOCK'
<script id="ch-trvize-pdffix-js">
/* CH_TRVIZE_PDFFIX — TR vize belgeleri garantili PDF (script-silme bypass) */
try{(function(){
  function isMobile(){ try{ return /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent)||window.innerWidth<820; }catch(e){ return false; } }
  function A4(html){
    var h=String(html||'');
    if(h.indexOf('__vsfprint')!==-1) return h;
    var prn='<scr'+'ipt>/*__vsfprint*/window.onload=function(){setTimeout(function(){try{window.focus();window.print();}catch(e){}},450);};<\/scr'+'ipt>';
    if(/<\/body>/i.test(h)) h=h.replace(/<\/body>/i,prn+'</body>');
    else h=h+prn;
    return h;
  }
  /* garantili yazdirma: yeni sekme (script korunur) / masaustu iframe */
  function robustPrint(html){
    var H=A4(html);
    if(isMobile()){
      var w=null; try{ w=window.open('','_blank'); }catch(e){ w=null; }
      if(w&&w.document){ try{ w.document.open(); w.document.write(H); w.document.close(); return true; }catch(e){} }
    }
    try{
      var old=document.getElementById('vsf-print-frame'); if(old) old.remove();
      var f=document.createElement('iframe'); f.id='vsf-print-frame';
      f.setAttribute('style','position:fixed;right:0;bottom:0;width:1px;height:1px;border:0;opacity:0');
      document.body.appendChild(f);
      var d=f.contentWindow.document; d.open(); d.write(H); d.close();
      setTimeout(function(){ try{ f.contentWindow.focus(); f.contentWindow.print(); }catch(e){} },500);
      return true;
    }catch(e){ return false; }
  }
  window.chVsfExportPDF=robustPrint;

  /* [1] acik form anahtarini izle */
  function hookVSF(){
    try{
      if(!window.chVSF || window.chVSF.__pdffix) return;
      if(typeof window.chVSF.openForm==='function'){
        var _o=window.chVSF.openForm;
        window.chVSF.openForm=function(k){ try{ window.__vsfCur=k; }catch(e){} return _o.apply(this,arguments); };
      }
      window.chVSF.__pdffix=1;
    }catch(e){}
  }

  /* [2] ust bara garantili PDF dugmesi */
  function curHtml(){
    try{
      var k=window.__vsfCur; if(!k||!window.chVSF) return '';
      var d=(typeof window.chVSF.getD==='function')?window.chVSF.getD(k):{};
      var pages=window.chVSF.buildPages(k,d)||[];
      if(!pages.length) return '';
      return window.chVSF.makePdfHtml(pages);
    }catch(e){ return ''; }
  }
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  function lbl(){ return {de:'📄 Als PDF speichern',tr:'📄 PDF olarak indir',en:'📄 Save as PDF'}[lang()]||'📄 PDF'; }
  var n=0;(function loop(){
    try{
      hookVSF();
      var pnl=document.getElementById('pnl-vsf');
      if(pnl){
        var bar=pnl.querySelector('.vsf-topbar');
        if(bar && !document.getElementById('vsf-pdf-btn')){
          var b=document.createElement('button'); b.id='vsf-pdf-btn';
          b.setAttribute('style','margin-left:auto;background:linear-gradient(135deg,#d4a84a,#ecc060);color:#191000;border:none;border-radius:9px;padding:8px 14px;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit');
          b.textContent=lbl();
          b.onclick=function(){
            var html=curHtml();
            if(!html){ try{ alert({tr:'Önce bir belge açıp doldurun.',de:'Bitte zuerst ein Dokument öffnen.',en:'Open a document first.'}[lang()]||''); }catch(e){} return; }
            robustPrint(html);
          };
          bar.appendChild(b);
        }
        var pb=document.getElementById('vsf-pdf-btn'); if(pb && pb.textContent!==lbl()) pb.textContent=lbl();
      }
    }catch(e){}
    if(n++<600) setTimeout(loop,700);
  })();

  /* [3] chPrintDoc'tan gecen TR-vize ciktisina otomatik-yazdirma enjekte et
     (mevcut buton yolu da yazdirsin) — outermost sarma */
  var g=0;(function wrapCPD(){
    try{
      if(typeof window.chPrintDoc==='function' && !window.chPrintDoc.__vsfprn){
        var _p=window.chPrintDoc;
        var w=function(html){
          try{
            if(typeof html==='string' && html.indexOf('vfA4')!==-1){
              return robustPrint(html); /* TR-vize belgesi -> garantili yol */
            }
          }catch(e){}
          return _p.apply(this,arguments);
        };
        w.__vsfprn=1;
        /* onceki sarma bayraklarini tasi */
        try{ if(_p.__ai2) w.__ai2=1; if(_p.__fit) w.__fit=1; }catch(e){}
        window.chPrintDoc=w;
      }
    }catch(e){}
    if(g++<400) setTimeout(wrapCPD,500);
  })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'tp').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-trvizepdf-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_TRVIZE_PDFFIX')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_TRVIZE_PDFFIX diskte (".strlen($chk)." bayt)\n";
echo "\n✓ TR vize belgeleri artik GERCEK PDF verir:\n";
echo "   • Ust barda garantili '📄 PDF olarak indir' dugmesi\n";
echo "   • Belge yeni sekmede acilir, otomatik 'PDF olarak kaydet' cikar\n";
echo "     (chPrintDoc script-silme bypass edildi -> yazdirma tetiklenir)\n";
echo "   • Mevcut buton yolu da artik garantili yazdirir (vfA4 tespiti)\n";
