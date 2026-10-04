<?php
/**
 * ChatHelp — apply-pdf-premium (CH_PDF_PREMIUM) — PDF: kurumsal sablon + akilli sayfalama
 *  Istekler:
 *   • "daha kurumsal ve estetik": hukuk yazismasina uygun SERIF tipografi
 *     (Noto Serif/Georgia), iki yana yakli metin + dil-dogru heceleme
 *     (hyphens + html lang), her sayfada ince cizgili zarif alt bilgi
 *     (belge adi · tarih), dengeli kenar boslukları.
 *   • "tum dillerin alfabesi": genis font zinciri (Latin-ext TR/PL, Kiril,
 *     Yunanca, Arapca Noto Naskh) + dogru lang/dir nitelikleri (RTL dahil).
 *   • "bir satir icin ikinci sayfa acma; en az ~4.5 satir olmali, yoksa
 *     onceki sayfaya sigdir": iki katmanli cozum —
 *       (a) CSS widows:5/orphans:4 -> devam sayfasi ACILIRSA en az 5 satir
 *           tasir, geride en az 4 satir kalir (tek satirlik sayfa IMKANSIZ);
 *       (b) akilli sigdirma: kuyruk 8 satirdan kisaysa icerik %10'a kadar
 *           inceltilerek (zoom>=0.90) fazladan sayfa TAMAMEN kaldirilir.
 *  makePDF imzasi yine AYNI kalir — 6 cagiran yerin hicbiri degismez.
 * KULLANIM: pull-updates.php?files=apply-pdf-premium.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-pdf-premium BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_PDF_PREMIUM')!==false) exit("Zaten ekli (CH_PDF_PREMIUM).\n");
if (strpos($src,'CH_MAKEPDF_UNICODE')===false) exit("HATA: CH_MAKEPDF_UNICODE yok (once apply-makepdf-unicode-fix gerekli).\n");

$old = <<<'OLD'
function makePDF(doc,name,cc){ /*CH_MAKEPDF_UNICODE — jsPDF (Latin-1 only) terk edildi; tarayici Unicode print motoru kullanilir*/
  doc=(typeof chSanitizePDF==='function')?chSanitizePDF(doc):doc;
  function _mpEsc(s){ return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
  var _mpRtl=false; try{ var _mpL=(typeof uil==='function')?uil():(localStorage.getItem('ch_uilang')||'de'); _mpRtl=(_mpL==='ar'); }catch(e){}
  var _mpHtml='<!doctype html><html'+(_mpRtl?' dir="rtl" lang="ar"':'')+'><head><meta charset="utf-8"><title>'+_mpEsc(name||'ChatHelp')+'</title>'
    +'<style>@page{size:A4;margin:0}html,body{margin:0;padding:0}*{box-sizing:border-box}'
    +'.a4{width:794px;min-height:1123px;padding:78px 76px 90px;background:#fff;color:#1e1e26;'
    +'font-family:\'Inter\',\'Segoe UI\',\'Noto Sans\',\'Noto Naskh Arabic\',\'Noto Sans Arabic\',Tahoma,Arial,sans-serif;'
    +'font-size:12.5px;line-height:1.85;white-space:pre-wrap;overflow-wrap:anywhere;word-break:break-word;'
    +'hyphens:auto;'+(_mpRtl?'text-align:right;direction:rtl':'text-align:left')+'}'
    +'</style></head><body><div class="a4">'+_mpEsc(doc)+'</div></body></html>';
  try{ if(window.chPrintDoc){ window.chPrintDoc(_mpHtml); return; } }catch(e){}
  try{ var _mpW=window.open('','_blank'); if(!_mpW){ alert('Popup engellendi'); return; } _mpW.document.open(); _mpW.document.write(_mpHtml+'<scr'+'ipt>window.onload=function(){setTimeout(function(){window.print();},350);}<\/scr'+'ipt>'); _mpW.document.close(); }catch(e){}
}
OLD;

$new = <<<'NEW'
function makePDF(doc,name,cc){ /*CH_MAKEPDF_UNICODE + CH_PDF_PREMIUM — kurumsal serif sablon, tum alfabeler, akilli sayfa doldurma*/
  doc=(typeof chSanitizePDF==='function')?chSanitizePDF(doc):doc;
  function _mpEsc(s){ return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
  var _mpL='de'; try{ _mpL=(typeof uil==='function')?uil():(localStorage.getItem('ch_uilang')||'de'); }catch(e){}
  var _mpRtl=(_mpL==='ar');
  var _mpDate=''; try{ _mpDate=new Date().toLocaleDateString(_mpRtl?'ar':( _mpL==='de'?'de-DE':_mpL )); }catch(e){ try{ _mpDate=new Date().toLocaleDateString(); }catch(e2){} }
  var _mpTitle=_mpEsc(name||'Dokument');
  var _mpHtml='<!doctype html><html lang="'+_mpEsc(_mpL)+'"'+(_mpRtl?' dir="rtl"':'')+'><head><meta charset="utf-8"><title>'+_mpTitle+'</title>'
    +'<style>@page{size:A4;margin:0}html,body{margin:0;padding:0}*{box-sizing:border-box}'
    +'.a4{width:794px;min-height:1123px;padding:86px 82px 100px;background:#fff;color:#16161d;'
    +'font-family:\'Noto Serif\',Georgia,\'Times New Roman\',\'Noto Naskh Arabic\',\'Noto Sans Arabic\',\'Noto Sans\',\'Segoe UI\',serif;'
    +'font-size:12.5px;line-height:1.66;white-space:pre-wrap;overflow-wrap:anywhere;word-break:break-word;'
    +'hyphens:auto;orphans:4;widows:5;'
    +(_mpRtl?'text-align:right;direction:rtl':'text-align:justify')+'}'
    +'.mp-foot{position:fixed;left:82px;right:82px;bottom:32px;border-top:1px solid #d6d6df;padding-top:7px;'
    +'font-family:\'Noto Sans\',\'Segoe UI\',Arial,sans-serif;font-size:8.5px;letter-spacing:.5px;color:#9a9aa8;'
    +'display:flex;justify-content:space-between;gap:12px}'
    +'.mp-foot span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:70%}'
    +'@media screen{.mp-foot{display:none}}'
    +'</style></head><body>'
    +'<div class="a4" id="mp-a4">'+_mpEsc(doc)+'</div>'
    +'<div class="mp-foot"><span>'+_mpTitle+'</span><span>'+_mpEsc(_mpDate)+'</span></div>'
    +'<scr'+'ipt>/*akilli sigdirma: kuyruk <8 satirsa fazladan sayfayi kaldir (zoom>=0.90)*/'
    +'try{(function(){var d=document.getElementById("mp-a4");if(!d)return;var PH=1123,LH=21;'
    +'var H=d.scrollHeight,n=Math.ceil(H/PH);if(n<2)return;var tail=H-(n-1)*PH;'
    +'if(tail>0&&tail<8*LH){var z=((n-1)*PH-10)/H;if(z>=0.90){d.style.zoom=z;}}'
    +'})();}catch(e){}<\/scr'+'ipt>'
    +'</body></html>';
  try{ if(window.chPrintDoc){ window.chPrintDoc(_mpHtml); return; } }catch(e){}
  try{ var _mpW=window.open('','_blank'); if(!_mpW){ alert('Popup engellendi'); return; } _mpW.document.open(); _mpW.document.write(_mpHtml+'<scr'+'ipt>window.onload=function(){setTimeout(function(){window.print();},350);}<\/scr'+'ipt>'); _mpW.document.close(); }catch(e){}
}
NEW;

$c=0; $src=str_replace($old,$new,$src,$c);
if ($c!==1) { echo "HATA: makePDF (unicode surumu) anchor bulunamadi (x$c) — index.php DEGISTIRILMEDI.\n"; exit; }
echo "  ✓ [1] kurumsal serif tipografi + iki yana yasli metin + dil-dogru heceleme\n";
echo "  ✓ [2] her sayfada zarif alt bilgi: belge adi · tarih (ince cizgiyle)\n";
echo "  ✓ [3] widows:5/orphans:4 — tek satirlik devam sayfasi IMKANSIZ (en az 5 satir tasinir)\n";
echo "  ✓ [4] akilli sigdirma — kisa kuyruk (%10'a kadar) onceki sayfalara sigdirilir\n";
echo "  ✓ [5] tum alfabeler: TR/PL Latin-ext, Kiril, Yunanca, Arapca (RTL) + lang/dir\n";

$tmp = tempnam(sys_get_temp_dir(),'pp').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-pdfprem-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_PDF_PREMIUM uygulandi. Uygulamanin TUM PDF cikislari (sablon, Fall, gecmis,\n";
echo "   sohbet) artik kurumsal gorunumde ve akilli sayfalamayla uretiliyor.\n";
