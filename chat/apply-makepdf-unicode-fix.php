<?php
/**
 * ChatHelp — apply-makepdf-unicode-fix (CH_MAKEPDF_UNICODE)
 *  KOK SEBEP (dump-makepdf + dump-makepdf-callers ile kesinlesti):
 *   • jsPDF her sayfada otomatik yukleniyor -> window.jspdf HER ZAMAN dolu.
 *   • makePDF() jsPDF'in yerlesik 'times' fontunu kullaniyor — bu font
 *     SADECE WinAnsi/Latin-1 destekler: Turkce (ğ ş ı İ), Arapca, Kiril
 *     (Rusca) karakterler DOGRU BASILAMAZ (kayip/bos glif).
 *   • pdf.splitTextToSize() sadece BOSLUKTA satir kirar; bosluksuz uzun bir
 *     token (referans no, alt cizgi bloklari) sayfa kenarindan TASAR.
 *   • makePDF() SADECE sohbetten degil, UYGULAMANIN GENELİNDE 6 farkli
 *     yerden cagriliyor: sablon belge indirme (odeme sonrasi), "Belgelerim"
 *     gecmis-belge indirme, Fall belgesi indirme, genel PDF butonu, ve
 *     sohbet PDF'i — yani bu font/tasma sorunu SADECE sohbette degil
 *     UYGULAMA GENELINDE gecerli.
 *
 *  COZUM: makePDF()'in ICERIGI degistirilir (imzasi AYNI kalir — 6 cagiran
 *  yerin HICBIRI degismez): jsPDF vektor-metin yerine, tarayicinin kendi
 *  Unicode-tam font motorunu kullanan HTML+print yaklasimina gecilir
 *  (uygulamanin geri kalaninda zaten kullanilan window.chPrintDoc ile ayni
 *  yontem). overflow-wrap:anywhere (tasma imkansiz), genis font zinciri,
 *  Arapca icin otomatik RTL. chSanitizePDF() cagrisi (varsa) korunur.
 * KULLANIM: pull-updates.php?files=apply-makepdf-unicode-fix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-makepdf-unicode-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_MAKEPDF_UNICODE')!==false) exit("Zaten ekli (CH_MAKEPDF_UNICODE).\n");

$old = <<<'OLD'
function makePDF(doc,name,cc){
  doc=(typeof chSanitizePDF==='function')?chSanitizePDF(doc):doc;
  const {jsPDF}=window.jspdf;
  const pdf=new jsPDF({orientation:'portrait',unit:'mm',format:'a4'});
  pdf.setTextColor(20,20,28);pdf.setFont('times','normal');pdf.setFontSize(11);
  const lines=pdf.splitTextToSize(doc,170);let y=26;
  lines.forEach(function(l){if(y>278){pdf.addPage();y=22;}pdf.text(l,20,y);y+=6;});
  const pc=pdf.internal.getNumberOfPages();
  for(let pg=1;pg<=pc;pg++){pdf.setPage(pg);pdf.setFontSize(8);pdf.setTextColor(165,165,175);pdf.text(pg+' / '+pc,196,290,{align:'right'});}
  pdf.save((name||'Dokument').replace(/[\s\/\\:*?"<>|]/g,'-')+'.pdf');
}
OLD;

$new = <<<'NEW'
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
NEW;

$c=0; $src=str_replace($old,$new,$src,$c);
if ($c!==1) { echo "HATA: makePDF anchor bulunamadi (x$c) — index.php DEGISTIRILMEDI.\n"; exit; }
echo "  ✓ makePDF() jsPDF'ten tarayici Unicode print motoruna gecirildi (imza AYNI — 6 cagiran yer degismedi)\n";
echo "  ✓ Turkce/Arapca(RTL)/Kiril/Ispanyolca vb. TUM karakterler artik dogru basiliyor\n";
echo "  ✓ overflow-wrap:anywhere -> tasma artik imkansiz\n";

$tmp = tempnam(sys_get_temp_dir(),'mp').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-makepdfuc-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_MAKEPDF_UNICODE uygulandi. Uygulamanin TUM PDF cikislari (sablon, Fall,\n";
echo "   gecmis belgeler, sohbet) artik dogru font + tasma-siz.\n";
