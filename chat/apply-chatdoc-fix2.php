<?php
/**
 * ChatHelp — apply-chatdoc-fix2 (CH_CHATDOC_FIX2) — PDF hala calismiyor
 *  (Elite hesap, hard-refresh + yeni sohbet sonrasi bile). dump-pdffail ile
 *  kanitlandi: chPlan()/DOC_PRICES/stripeCheckout hepsi dogru tanimli VE
 *  makePDF()'in KENDI popup-engellendi uyarisi var — ama printDoc()'un
 *  makePDF() cevresindeki try/catch HATAYI SESSIZCE YUTUYOR (bos catch),
 *  bu yuzden gercek sebep hic gorunmuyor. Bu yama:
 *  [1] makePDF() hata firlatirsa artik GORUNUR bir alert cikar (gercek
 *      JS hata mesajiyla) — boylece asil sebep nihayet ortaya cikar.
 *  [2] Yedek yolda (window.open basarisiz olursa) da acikca "Popup
 *      engellendi" uyarisi verir — sessiz TypeError yutulmasi yerine.
 * KULLANIM: pull2.php?key=...&files=apply-chatdoc-fix2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-chatdoc-fix2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_CHATDOC_FIX2')!==false) exit("Zaten ekli (CH_CHATDOC_FIX2).\n");
if (strpos($src,'CH_CHATDOC_FIX')===false) exit("HATA: once apply-chatdoc-fix.php gerekli.\n");

$o = <<<'EOT'
  function printDoc(docText){ /*CH_CHATDOC_FIX — plan kontrolu artik card() seviyesinde; jsPDF sarti kaldirildi (makePDF gerektirmiyor)*/
    var real=restore(docText);
    try{ if(typeof makePDF==="function"){ makePDF(real,"ChatHelp-Dokument",(typeof CC!=="undefined"?CC:"DE")); return; } }catch(e){}
    var isRtl=(uil()==="ar");
    var html='<!doctype html><html'+(isRtl?' dir="rtl" lang="ar"':'')+'><head><meta charset="utf-8"><title>ChatHelp</title>'
      +'<style>@page{size:A4;margin:0}html,body{margin:0;padding:0}*{box-sizing:border-box}'
      +'.a4{width:794px;min-height:1123px;padding:78px 76px 90px;background:#fff;color:#1e1e26;'
      +'font-family:\'Inter\',\'Segoe UI\',\'Noto Sans\',\'Noto Naskh Arabic\',\'Noto Sans Arabic\',Tahoma,Arial,sans-serif;'
      +'font-size:12.5px;line-height:1.85;white-space:pre-wrap;overflow-wrap:anywhere;word-break:break-word;'
      +'hyphens:auto;'+(isRtl?'text-align:right;direction:rtl':'text-align:left')+'}'
      +'</style></head><body><div class="a4">'+esc(real)+'</div></body></html>';
    try{ if(window.chPrintDoc){ window.chPrintDoc(html); return; } }catch(e){}
    try{ var w=window.open("","_blank"); w.document.open(); w.document.write(html+'<scr'+'ipt>window.onload=function(){setTimeout(function(){window.print();},350);}<\/scr'+'ipt>'); w.document.close(); }catch(e){}
  }
EOT;
$n = <<<'EOT'
  function printDoc(docText){ /*CH_CHATDOC_FIX2 — hata artik GORUNUR (sessiz yutulmuyor); popup-engellendi acikca bildirilir*/
    var real=restore(docText);
    try{
      if(typeof makePDF==="function"){ makePDF(real,"ChatHelp-Dokument",(typeof CC!=="undefined"?CC:"DE")); return; }
    }catch(_pdfErr){
      try{ alert("PDF-Fehler: "+((_pdfErr&&_pdfErr.message)||_pdfErr)); }catch(e2){}
    }
    var isRtl=(uil()==="ar");
    var html='<!doctype html><html'+(isRtl?' dir="rtl" lang="ar"':'')+'><head><meta charset="utf-8"><title>ChatHelp</title>'
      +'<style>@page{size:A4;margin:0}html,body{margin:0;padding:0}*{box-sizing:border-box}'
      +'.a4{width:794px;min-height:1123px;padding:78px 76px 90px;background:#fff;color:#1e1e26;'
      +'font-family:\'Inter\',\'Segoe UI\',\'Noto Sans\',\'Noto Naskh Arabic\',\'Noto Sans Arabic\',Tahoma,Arial,sans-serif;'
      +'font-size:12.5px;line-height:1.85;white-space:pre-wrap;overflow-wrap:anywhere;word-break:break-word;'
      +'hyphens:auto;'+(isRtl?'text-align:right;direction:rtl':'text-align:left')+'}'
      +'</style></head><body><div class="a4">'+esc(real)+'</div></body></html>';
    try{ if(window.chPrintDoc){ window.chPrintDoc(html); return; } }catch(e){}
    try{
      var w=window.open("","_blank");
      if(!w){ alert("Popup wurde blockiert. Bitte erlauben Sie Popups für chat-help.com und versuchen Sie es erneut."); return; }
      w.document.open(); w.document.write(html+'<scr'+'ipt>window.onload=function(){setTimeout(function(){window.print();},350);}<\/scr'+'ipt>'); w.document.close();
    }catch(_fbErr){ try{ alert("PDF-Fehler (Fallback): "+((_fbErr&&_fbErr.message)||_fbErr)); }catch(e2){} }
  }
EOT;
$c=substr_count($src,$o);
if ($c!==1) { echo "  ✗ printDoc anchor ($c) — index DEGISTIRILMEDI.\n"; exit; }
$src=str_replace($o,$n,$src,$c);
echo "  ✓ printDoc: makePDF hatasi artik gorunur alert ile bildiriliyor\n";
echo "  ✓ yedek yolda popup-engellendi acikca bildiriliyor\n";

$tmp = tempnam(sys_get_temp_dir(),'cd2').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
$bk=@file_put_contents($file.'.bak-chatdocfix2-'.date('Ymd-His'), @file_get_contents($file));
if ($bk===false) echo "  ⚠ yedek yazilamadi — devam\n";
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_CHATDOC_FIX2')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_CHATDOC_FIX2 diskte (".strlen($chk)." bayt)\n";
echo "\n✓ CH_CHATDOC_FIX2 uygulandi. PDF butonuna tekrar basin — artik BIR SEY\n";
echo "   GORUNMELI: ya PDF/yazdirma penceresi, ya da gercek hata mesaji.\n";
echo "   Hata cikarsa TAM METNINI bana gonderin — kesin sebebi orada.\n";
