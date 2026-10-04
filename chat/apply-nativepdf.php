<?php
/**
 * ChatHelp — apply-nativepdf (CH_NATIVEPDF) — App'te PDF'i NATIVE koprusuyle indir.
 *
 * KANIT (AAB v6/1.5 dex icinden dogrulandi):
 *   ChelpNative · chSavePdf · addJavascriptInterface · MainActivity$ChelpBridge
 * Yani uygulamada PDF'i Indirilenler'e yazip acan native kopru ZATEN VAR.
 *
 * Dun gece bu kopruyu kullanan kod (CH_APPPRINT) "hicbir sey olmuyor" diye geri
 * alinmisti; sebebi kopru degildi — isWV bozuk oldugu icin o dal HIC calismiyordu.
 * isWV duzeldi (CH_ISWV_GLOBAL), kopru artik cagrilabilir. APK beklemeye gerek yok.
 *
 * BU YAMA (tamamen EKLEME):
 *  · Katman (#ch-appview) acilinca '⬇️ PDF' dugmesinin davranisini degistirir:
 *      dl.php -> PDF blob -> base64 -> window.ChelpNative.chSavePdf(...)
 *      -> dosya Indirilenler'e kaydedilir ve otomatik acilir
 *  · Kopru yoksa (web/tarayici) eski davranisa duser — web HIC etkilenmez
 *  · Her adimda gorunur durum yazisi (WebView'de konsol/alert yok)
 *  · '🖨 Drucken' de kopru varsa ayni yolu kullanir: PDF acilinca kullanici
 *    Android PDF goruntuleyicisinden yazdirabilir
 *
 * Kritik isaret dogrulamasi + lint + .bak + kilit tazeleme.
 * KULLANIM: /chat/pull2.php?key=...&files=apply-nativepdf.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
echo "apply-nativepdf BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$dir=__DIR__; $file=$dir.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_NATIVEPDF')!==false) exit("Zaten ekli (CH_NATIVEPDF) — DEGISIKLIK YOK.\n");

$js = <<<'HTML'
<script>/*CH_NATIVEPDF*/(function(){try{
function L(){try{return (typeof uil==='function')?uil():(localStorage.getItem('ch_uilang')||'de');}catch(e){return 'de';}}
var T={de:{w:'PDF wird erstellt …',sv:'✓ Gespeichert in „Downloads“',
           er:'✗ Fehler: ',nb:'Kein Datei-Zugriff — bitte 📧 E-Mail nutzen'},
       tr:{w:'PDF hazırlanıyor …',sv:'✓ İndirilenler klasörüne kaydedildi',
           er:'✗ Hata: ',nb:'Dosya erişimi yok — 📧 e-posta kullanın'},
       en:{w:'Creating PDF …',sv:'✓ Saved to “Downloads”',
           er:'✗ Error: ',nb:'No file access — please use 📧 e-mail'}};
function t(k){var l=L();return (T[l]||T.de)[k]||T.de[k];}

function bridge(){
  try{ if(window.ChelpNative && typeof window.ChelpNative.chSavePdf==='function') return window.ChelpNative; }catch(e){}
  return null;
}
function noteEl(ov){
  var n=ov.querySelector('.cv-note');
  if(!n){ n=document.createElement('div'); n.className='cv-note';
    n.setAttribute('style','color:#9fb0d8;font:12.5px system-ui;text-align:center;padding:7px 12px 0;background:#0e1734');
    var a=ov.querySelector('.cv-act'); if(a&&a.parentNode) a.parentNode.insertBefore(n,a); }
  return n;
}
function say(ov,m){ try{ noteEl(ov).textContent=m; }catch(e){} }
function docText(ov){
  try{ var p=ov.querySelector('.cv-paper'); if(p) return (p.innerText||p.textContent||'').trim(); }catch(e){}
  return '';
}
function docTitle(ov){
  try{ var x=ov.querySelector('.cv-ttl'); if(x) return (x.textContent||'').replace(/^\s*📄\s*/,'').trim(); }catch(e){}
  return 'ChatHelp-Dokument';
}
function esc(s){return String(s==null?'':s).replace(/[&<>]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;'}[c];});}
function letterHTML(txt,ttl){
  return '<!doctype html><html><head><meta charset="utf-8"><title>'+esc(ttl)+'</title></head>'
    +'<body style="font-family:Georgia,serif;font-size:12pt;line-height:1.6;white-space:pre-wrap;margin:2.2cm">'
    +esc(txt)+'</body></html>';
}
function safeName(ttl){
  var s=String(ttl||'ChatHelp-Dokument').replace(/[^\wäöüÄÖÜß .\-]/g,'').trim();
  if(!s) s='ChatHelp-Dokument';
  return s.slice(0,60)+'.pdf';
}

/* dl.php -> PDF blob -> base64 -> native kopru */
function nativeSave(ov){
  var nb=bridge();
  if(!nb){ say(ov,t('nb')); return false; }
  var txt=docText(ov), ttl=docTitle(ov);
  if(!txt){ say(ov,t('er')+'leer'); return true; }
  say(ov,t('w'));
  try{
    var fd=new FormData();
    fd.append('html',letterHTML(txt,ttl)); fd.append('name',ttl); fd.append('fmt','pdf');
    fetch('dl.php',{method:'POST',body:fd})
      .then(function(r){ return r.blob(); })
      .then(function(b){
        var fr=new FileReader();
        fr.onloadend=function(){
          try{
            var s=String(fr.result||'');
            var b64=s.indexOf(',')>=0 ? s.slice(s.indexOf(',')+1) : s;
            nb.chSavePdf(b64, safeName(ttl), 'application/pdf');
            say(ov,t('sv'));
          }catch(e){ say(ov,t('er')+e); }
        };
        fr.onerror=function(){ say(ov,t('er')+'read'); };
        fr.readAsDataURL(b);
      })
      .catch(function(e){ say(ov,t('er')+e); });
  }catch(e){ say(ov,t('er')+e); }
  return true;
}

function rewire(ov){
  try{
    if(!ov||ov.getAttribute('data-ch-npdf')==='1') return;
    var act=ov.querySelector('.cv-act'); if(!act) return;
    if(!bridge()) return;            /* kopru yoksa (web) hic dokunma */
    ov.setAttribute('data-ch-npdf','1');

    var btns=act.querySelectorAll('button');
    for(var i=0;i<btns.length;i++){
      var b=btns[i], tx=(b.textContent||'');
      if(/PDF/i.test(tx) && !/E-?Mail|posta/i.test(tx)){
        var nb=b.cloneNode(true);                     /* eski dinleyicileri at */
        nb.addEventListener('click',function(ev){
          try{ ev.preventDefault(); ev.stopPropagation(); }catch(_){}
          nativeSave(ov);
        });
        b.parentNode.replaceChild(nb,b);
      }
      else if(/Drucken|Yazdır|Print/i.test(tx)){
        var pb=b.cloneNode(true);
        pb.addEventListener('click',function(ev){
          try{ ev.preventDefault(); ev.stopPropagation(); }catch(_){}
          /* PDF olarak kaydet + ac -> kullanici PDF goruntuleyiciden yazdirir */
          nativeSave(ov);
        });
        b.parentNode.replaceChild(pb,b);
      }
    }
    say(ov,'');
  }catch(e){}
}

function scan(){ try{ var ov=document.getElementById('ch-appview'); if(ov) rewire(ov); }catch(e){} }
function start(){ try{ scan();
  var mo=new MutationObserver(function(){ scan(); });
  mo.observe(document.body,{childList:true,subtree:true});
}catch(e){} }
if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',start); else start();
}catch(e){}})();</script>
HTML;

$p=strripos($src,'</body>');
if($p===false) exit("✗ </body> bulunamadi — DEGISIKLIK YOK\n");
$new=substr($src,0,$p).$js.substr($src,$p);

foreach(['CH_ISWV_GLOBAL'=>1,'CH_APPVIEW3'=>1,'CH_APPVIEW2C'=>4,'CH_MAILBTN2'=>1,'CH_FILLGAPS'=>1,'CH_NATIVEPDF'=>1] as $m=>$min){
  if(substr_count($new,$m)<$min) exit("✗ Kritik isaret eksik olurdu ($m) — DEGISIKLIK YOK\n");
}

$tmp=tempnam(sys_get_temp_dir(),'np').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0) exit("\n✗ LINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n");

@file_put_contents($file.'.bak-nativepdf-'.date('Ymd-His'),$src);
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();

$cur=(string)@file_get_contents($file);
@file_put_contents($file.'.GOOD-appprint',$cur);
@file_put_contents($dir.'/ch-applock.json',json_encode([
 'locked_at'=>date('c'),'size'=>strlen($cur),'sha1'=>sha1($cur),'note'=>'nativepdf',
 'markers'=>['CH_ISWV_GLOBAL'=>substr_count($cur,'CH_ISWV_GLOBAL'),'CH_APPVIEW3'=>substr_count($cur,'CH_APPVIEW3'),
             'CH_APPVIEW2C'=>substr_count($cur,'CH_APPVIEW2C'),'CH_MAILBTN2'=>substr_count($cur,'CH_MAILBTN2'),
             'CH_FILLGAPS'=>substr_count($cur,'CH_FILLGAPS'),'CH_NATIVEPDF'=>substr_count($cur,'CH_NATIVEPDF')],
 'files'=>['pv.php','pvsave.php','pvmail.php','pdflib.php']
],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

echo "✓ CH_NATIVEPDF eklendi (".strlen($src)." -> ".strlen($cur)." bayt)\n";
echo "  CH_NATIVEPDF=".substr_count($cur,'CH_NATIVEPDF')
    ."  CH_MAILBTN2=".substr_count($cur,'CH_MAILBTN2')
    ."  CH_APPVIEW3=".substr_count($cur,'CH_APPVIEW3')
    ."  CH_ISWV_GLOBAL=".substr_count($cur,'CH_ISWV_GLOBAL')."\n";
echo "  ✓ kilit tazelendi (sha1 ".substr(sha1($cur),0,12)."…)\n";
echo "\nTEST (App): belge -> '⬇️ PDF' veya '🖨 Drucken'\n";
echo "  -> 'PDF wird erstellt …' sonra '✓ Gespeichert in Downloads'\n";
echo "  -> Dosya Indirilenler'e iner ve otomatik acilir; oradan yazdirabilirsin.\n";
echo "  -> Kopru yoksa (tarayici) hicbir sey degismez — web etkilenmez.\n";
