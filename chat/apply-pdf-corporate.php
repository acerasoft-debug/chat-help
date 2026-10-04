<?php
/**
 * ChatHelp — apply-pdf-corporate (CH_PDF_CORP) — TÜM PDF çıkışları premium/kurumsal.
 *
 *  SORUN (ekran görüntüsüyle doğrulandı): sohbet/Themen/Fall PDF'i metni tek blok,
 *  satır sonları ve boşluklar silinmiş halde basıyordu ("LLCKiefernweg 2Sontheim…").
 *  KÖK NEDEN: kayıtlı belge tam-metinle (\n'lerle) saklanmıyor -> makePDF'e düz,
 *  satırsız metin geliyor; makePDF pre-wrap kullansa da gösterecek \n yok.
 *
 *  ÇÖZÜM (EKLEMELİ + OVERRIDE — mevcut kod silinmez, kırılgan anchor yok):
 *   1) chStructureLetter(t): metinde satır sonu azsa Alman/AB iş-mektubu kalıplarına
 *      göre satır/paragraf/tarih/selam/başlık/kapanış yeniden kurulur; \n'ler zaten
 *      varsa DOKUNULMAZ (iyi belgeyi bozmaz).
 *   2) chRenderLetterHTML(): premium kurumsal A4 mektup (serif, antetli gönderen
 *      bloğu + ince çizgi, sağ tarih, KALIN Betreff, iki yana yaslı gövde, imza
 *      boşlukları). TR/AR(RTL)/Kiril tam Unicode + taşma-sız korunur.
 *   3) window.makePDF OVERRIDE edilir (imza aynı) -> şablon, Fall, geçmiş, sohbet:
 *      HEPSİ premium. chSanitizePDF + chPrintDoc (App-güvenli) korunur.
 *   4) Sohbet kartı "PDF kaydet" (window.__chDocPdf.print) de aynı motora bağlanır.
 *
 *  Sadece </body> öncesine tek script eklenir; php -l ✓.
 * KULLANIM: pull2.php?key=...&files=apply-pdf-corporate.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-pdf-corporate BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_PDF_CORP')!==false) exit("Zaten ekli (CH_PDF_CORP).\n");
if (strpos($src,'function makePDF(')===false && strpos($src,'makePDF=function')===false)
  exit("HATA: makePDF yok — once PDF modulleri gerekli.\n");

$block = <<<'HTMLBLOCK'
<script id="ch-pdf-corporate">
/* CH_PDF_CORP — tum PDF cikislari premium kurumsal mektup (makePDF override) */
try{(function(){
  function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
  function rtl(){ try{ var l=(typeof uil==='function')?uil():(localStorage.getItem('ch_uilang')||'de'); return l==='ar'; }catch(e){ return false; } }

  /* ---- 1) Duz metni yeniden yapilandir (yalniz \n azsa) ---- */
  function chStructureLetter(t){
    t=String(t==null?'':t).replace(/\r/g,'');
    var nl=(t.match(/\n/g)||[]).length;
    /* GUVENLIK: metinde gercek satir yapisi VARSA (>=2 \n) ASLA yeniden kurma —
       iyi belgeyi (sablon/Fall/modul) bozmamak icin sadece normalize et.
       Yalniz tamamen duz (0-1 \n) metin — yani bozuk kayit — yeniden kurulur. */
    if(nl>=2){
      return t.replace(/[ \t]+\n/g,'\n').replace(/\n{3,}/g,'\n\n').trim();
    }
    /* GUVENLI reconstruction: hicbir KELIME/E-POSTA/SAYI bolunmez.
       Yalniz noktalama-sonrasi bosluk + selam/bolum/kapanis/tarih kirilir. */
    t=t.replace(/\s+/g,' ').trim();
    /* 1) cumle bosluklari: nokta/!/?/:/; sonrasi BUYUK harf -> arasina bosluk
          (tarih guvenli: "15.07.2026" -> nokta sonrasi RAKAM, eslesmez) */
    t=t.replace(/([.!?:;])([A-ZÄÖÜ])/g,'$1 $2');
    /* 2) iletisim etiketleri kendi satirina (kelime bolmez) */
    t=t.replace(/\s*(Telefon|Tel\.?|Mobil|Handy|Fax|E-?Mail)\s*:\s*/gi,'\n$1: ');
    /* 3) tarih satiri: "den TT.MM.JJJJ" -> kendi satirina (e-postaya DOKUNMAZ) */
    t=t.replace(/\s*(\bden\s+\d{1,2}\.\d{1,2}\.\d{2,4})/g,'\n$1\n');
    /* 4) selam */
    t=t.replace(/\s*(Sehr geehrte[rs]?\b[^,]{0,60},|Sayın[^,]{0,60},|Hallo[^,]{0,40},|Guten Tag[^,]{0,40},|Dear\b[^,]{0,60},)/g,'\n\n$1\n');
    /* 5) bolum basliklari */
    t=t.replace(/\s*(Begründung|Sachverhalt|Forderung|Rechtslage|Fazit|Betreff|Anlage[n]?|Bitte beachten Sie|Gerekçe|Talep|Konu)\s*:\s*/g,'\n\n$1:\n');
    /* 6) kapanis */
    t=t.replace(/\s*(Mit freundlichen Grüßen|Mit freundlichem Gruß|Hochachtungsvoll|Freundliche Grüße|Mit besten Grüßen|Saygılarımla|Best regards|Kind regards)\s*/g,'\n\n$1\n');
    /* 7) yaygin paragraf baslangiclari */
    t=t.replace(/\s+(Gegen diese[nrs]?|Ich fordere|Ich behalte mir vor|Ich bitte Sie|Da (?:ein|der|die|das)|Eine Abmahnung|Die Aufnahme|Für eine Rückmeldung|Sollte|Darüber hinaus|Des Weiteren|Ferner|Zudem|Hiermit|Weiterhin)\b/g,'\n\n$1');
    return t.replace(/[ \t]+\n/g,'\n').replace(/\n[ \t]+/g,'\n').replace(/\n{3,}/g,'\n\n').trim();
  }

  /* ---- 2) Premium kurumsal mektup HTML ---- */
  function chRenderLetterHTML(doc,name){
    var isR=rtl();
    var t=chStructureLetter(doc);
    var paras=t.split(/\n{2,}/).map(function(p){return p.replace(/^\n+|\n+$/g,'');}).filter(function(p){return p.trim().length;});
    var greeted=false, closingSeen=false, out='';
    var dateRe=/(,\s*(?:den|am)\s*\d{1,2}\.\d{1,2}\.\d{2,4})\s*$/;
    var greetRe=/^(Sehr geehrte|Sayın|Hallo|Guten Tag|Liebe[rs]?|Dear)\b/;
    var closeRe=/(Mit freundlich|Mit freundlichem|Hochachtungsvoll|Freundliche Grüße|Mit besten Grüßen|Saygılarımla|Best regards|Kind regards)/i;
    var subjRe=/^(Widerspruch|Widerruf|Einspruch|Betreff|Kündigung|Antrag|Beschwerde|Mahnung|Anfrage|Bewerbung|Kündigungswiderspruch)\b/i;
    /* ilk paragraf yalniz GERCEKTEN gonderen/iletisim bilgisiyse antet gibi;
       degilse normal govde (CV/sozlesme gibi mektup-disi belgeleri bozmamak icin) */
    var senderLike=/(\bTelefon\b|\bTel\.|\bE-?Mail\b|@|\b\d{5}\b|\bStr(?:\.|aße)\b|\bWeg\b|\bGmbH\b|\bLLC\b)/i.test(paras[0]||'');
    paras.forEach(function(p,i){
      var lines=p.split('\n');
      var body=lines.map(function(l){return esc(l.trim());}).join('<br>');
      if(i===0 && senderLike){ out+='<div class="sndr">'+body+'</div>'; return; }
      var last=lines[lines.length-1].trim();
      if(dateRe.test(last) && p.length<120){ out+='<p class="dt">'+body+'</p>'; return; }
      if(!greeted && subjRe.test(p.trim()) && p.length<160){ out+='<p class="subj">'+body+'</p>'; return; }
      if(greetRe.test(p.trim())){ greeted=true; out+='<p class="grt">'+body+'</p>'; return; }
      if(closeRe.test(p) && !closingSeen){ closingSeen=true; out+='<p class="clo">'+body+'</p>'; return; }
      out+='<p class="bd'+(closingSeen?' sig':'')+'">'+body+'</p>';
    });
    var css='@page{size:A4;margin:0}html,body{margin:0;padding:0}*{box-sizing:border-box}'
      +'.a4{width:794px;min-height:1123px;padding:92px 82px 96px;background:#fff;color:#1b1b22;'
      +'font-family:\'Georgia\',\'Noto Serif\',\'Times New Roman\',\'Noto Naskh Arabic\',\'Noto Sans Arabic\',\'Segoe UI\',serif;'
      +'font-size:12.8px;line-height:1.72;'+(isR?'direction:rtl;text-align:right':'text-align:left')+'}'
      +'.a4 .sndr{font-size:10.6px;line-height:1.62;color:#5a5a66;letter-spacing:.2px;'
      +'padding-bottom:12px;margin-bottom:30px;border-bottom:1.4px solid #1f3a63}'
      +'.a4 .dt{margin:2px 0 30px;color:#3a3a44;'+(isR?'text-align:left':'text-align:right')+'}'
      +'.a4 .subj{font-weight:700;font-size:13.4px;margin:26px 0 20px;color:#12233f}'
      +'.a4 .grt{margin:0 0 14px}'
      +'.a4 .bd{margin:0 0 12px;text-align:'+(isR?'right':'justify')+';hyphens:auto;overflow-wrap:anywhere;word-break:break-word}'
      +'.a4 .clo{margin:26px 0 2px}'
      +'.a4 .sig{margin:2px 0 0;white-space:pre-line;font-weight:600}';
    return '<!doctype html><html'+(isR?' dir="rtl" lang="ar"':'')+'><head><meta charset="utf-8"><title>'
      +esc(name||'ChatHelp')+'</title><style>'+css+'</style></head><body><div class="a4">'+out+'</div></body></html>';
  }

  /* ---- 3) makePDF OVERRIDE (imza AYNI: doc,name,cc) ---- */
  function corpMakePDF(doc,name,cc){
    try{ doc=(typeof chSanitizePDF==='function')?chSanitizePDF(doc):doc; }catch(e){}
    /* SADECE kayittan eklenen "baslik + tarih" kuyrugunu ayikla (ör. "Abmahnung widersprechen24.7.2026").
       Yalniz doc TAM olarak "<baslik><tarih>" ile bitiyorsa; baska hicbir tarih/metin silinmez. */
    try{
      if(name && String(name).trim().length>=4){
        var nEsc=String(name).trim().replace(/[.*+?^${}()|[\]\\]/g,'\\$&');
        doc=String(doc).replace(new RegExp('\\s*'+nEsc+'\\s*\\d{1,2}[.\\/]\\d{1,2}[.\\/]\\d{2,4}\\s*$'),'');
      }
    }catch(e){}
    var html=chRenderLetterHTML(doc,name);
    try{ if(window.chPrintDoc){ window.chPrintDoc(html); return; } }catch(e){}
    try{ var w=window.open('','_blank'); if(!w){ alert('Popup engellendi'); return; }
      w.document.open(); w.document.write(html+'<scr'+'ipt>window.onload=function(){setTimeout(function(){window.print();},350);}<\/scr'+'ipt>'); w.document.close();
    }catch(e){}
  }

  function install(){
    try{ window.makePDF=corpMakePDF; window.makePDF.__corp=1; }catch(e){}
    try{ window.chStructureLetter=chStructureLetter; window.chRenderLetterHTML=chRenderLetterHTML; }catch(e){}
    /* sohbet karti "PDF kaydet" -> ayni premium motor (placeholder geri-yukleme korunur) */
    try{
      if(window.__chDocPdf){
        window.__chDocPdf.print=function(docText){
          var real=docText; try{ if(window.__chDocPdf.restore) real=window.__chDocPdf.restore(docText); }catch(e){}
          corpMakePDF(real,'Dokument','');
        };
      }
    }catch(e){}
  }
  install();
  /* sonradan yuklenen moduller makePDF'i geri-tanimlarsa sahiplen (20sn) */
  var n=0;(function guard(){ try{ if(!(window.makePDF&&window.makePDF.__corp)) install(); }catch(e){} if(n++<40) setTimeout(guard,500); })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'pc').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-pdfcorp-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
if (function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_PDF_CORP')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_PDF_CORP diskte (".strlen($chk)." bayt)\n";
echo "\n✓ makePDF override edildi -> sablon, Fall, gecmis (Themen), sohbet: HEPSI premium kurumsal mektup.\n";
echo "  ✓ Satirsiz eski kayitlar chStructureLetter ile yeniden yapilandirilir; iyi belgeler korunur.\n";
echo "  ✓ Serif + antet cizgisi + sag tarih + kalin Betreff + yasli govde + imza boslugu.\n";
echo "  ✓ TR/AR(RTL)/Kiril Unicode + tasma-sizlik + App-guvenli chPrintDoc korundu.\n";
