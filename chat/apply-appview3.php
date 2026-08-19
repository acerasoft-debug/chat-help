<?php
/**
 * ChatHelp — apply-appview3 (CH_APPVIEW3) — PREMIUM kurumsal goruntuleyici
 *  + tarayiciya devir (App'te indirme/yazdirma destegi olmadigi icin).
 *
 * DURUM: CH_APPVIEW2 ile katman aciliyor (ilerleme) ama (a) PDF inmiyor,
 * (b) gorunum kurumsal degil. Android WebView dosya indiremez/yazdiramaz;
 * bunlar APK tarafi ozellikleri. Ama TARAYICI ikisini de yapar.
 *
 * BU YAMA:
 *  [1] pvsave.php  : belgeyi token ile sunucuya kaydeder (24s).
 *  [2] pv.php      : token'i premium DIN-mektup olarak gosterir, acilista
 *                    yazdirma penceresini acar; PDF butonu da var.
 *  [3] window.chAppView'i PREMIUM surumle degistirir:
 *      · A4 beyaz kagit, Georgia serif, antet cizgisi + altin aksan,
 *        sag tarih, kalin Betreff, imza bosluu — PDF ile ayni dil.
 *      · 🖨 Drucken  -> pvsave -> pv.php'yi TARAYICIDA acar (yazdirma calisir)
 *      · ⬇️ PDF      -> fetch/blob/share/form-POST zinciri
 *      · 📋 Kopieren · ✕ Schliessen
 *  Mevcut cagri noktalari (CH_APPVIEW2C) korunur; sadece govde degisir.
 *
 * Geri alma: apply-appview-off.php
 * KULLANIM: /chat/pull2.php?key=...&files=apply-appview3.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
echo "apply-appview3 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$dir=__DIR__;
$file=$dir.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");

/* ── [1] pvsave.php ── */
$pvsave = <<<'PHP'
<?php
/* ChatHelp — pvsave.php (CH_PV) — belgeyi token ile kaydeder (24 saat). */
error_reporting(E_ERROR|E_PARSE);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
$d=__DIR__.'/pv';
if(!is_dir($d)) @mkdir($d,0755,true);
/* eski dosyalari temizle */
foreach((array)@glob($d.'/*.txt') as $f){ if(@filemtime($f) < time()-86400) @unlink($f); }
$t=(string)($_POST['text'] ?? '');
if($t===''){ http_response_code(400); echo json_encode(['ok'=>false]); exit; }
if(strlen($t)>400000){ $t=substr($t,0,400000); }
$title=preg_replace('/[^\p{L}\p{N} .,\-]/u','',(string)($_POST['title'] ?? 'ChatHelp-Dokument'));
if($title==='') $title='ChatHelp-Dokument';
try{ $id=bin2hex(random_bytes(9)); }catch(Exception $e){ $id=md5(uniqid('',true)); }
$ok=@file_put_contents($d.'/'.$id.'.txt', $title."\n\x00\n".$t);
echo json_encode(['ok'=>($ok!==false),'id'=>$id]);
PHP;
echo (@file_put_contents($dir.'/pvsave.php',$pvsave)!==false ? "  ✓ pvsave.php yazildi\n" : "  ✗ pvsave.php yazilamadi\n");

/* ── [2] pv.php ── */
$pv = <<<'PHP'
<?php
/* ChatHelp — pv.php (CH_PV) — kaydedilmis belgeyi premium mektup olarak gosterir
   ve tarayicida yazdirma penceresini acar. App'ten tarayiciya devir hedefi. */
error_reporting(E_ERROR|E_PARSE);
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
$id=preg_replace('/[^a-f0-9]/','',(string)($_GET['id'] ?? ''));
$f=__DIR__.'/pv/'.$id.'.txt';
if($id===''||!is_file($f)){ http_response_code(404); echo '<p style="font:16px system-ui;padding:24px">Dokument nicht gefunden / Belge bulunamadi.</p>'; exit; }
$raw=(string)@file_get_contents($f);
$p=strpos($raw,"\n\x00\n");
$title=$p!==false?substr($raw,0,$p):'ChatHelp-Dokument';
$text=$p!==false?substr($raw,$p+3):$raw;
$E=function($s){ return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); };
/* yapisal tespit: tarih / Betreff */
$lines=explode("\n",str_replace("\r","",$text));
$out=''; $subjDone=false;
foreach($lines as $i=>$ln){
  $u=trim($ln);
  if($u===''){ $out.='<div class="sp"></div>'; continue; }
  if(!$subjDone && preg_match('/^(Betreff|Betrifft|Konu|Subject|Objet)\s*:/iu',$u)){ $out.='<p class="subj">'.$E($u).'</p>'; $subjDone=true; continue; }
  if($i<8 && mb_strlen($u,'UTF-8')<48 && preg_match('/\d{1,2}[.\/]\s?\d{1,2}[.\/]\s?\d{2,4}/u',$u) && !preg_match('/[a-zäöüçşğ]{6,}.*[a-zäöüçşğ]{6,}/iu',$u)){ $out.='<p class="dt">'.$E($u).'</p>'; continue; }
  $out.='<p>'.$E($u).'</p>';
}
?><!doctype html><html><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo $E($title); ?></title>
<style>
:root{--navy:#0e1734;--gold:#cc9e3d;--ink:#21242c}
*{box-sizing:border-box}
body{margin:0;background:#e9ecf3;font-family:Georgia,'Times New Roman',serif;color:var(--ink)}
.bar{position:sticky;top:0;background:var(--navy);color:#f0e6c8;padding:12px 16px;display:flex;gap:10px;align-items:center;
 font-family:system-ui,-apple-system,'Segoe UI',sans-serif;box-shadow:0 2px 12px rgba(0,0,0,.25);z-index:5}
.bar b{font-size:15px;letter-spacing:.3px;flex:1;font-weight:800}
.bar button{font:600 13px system-ui;padding:9px 14px;border-radius:9px;border:1px solid rgba(212,168,74,.6);
 background:#182248;color:#f0e6c8;cursor:pointer}
.bar button.pri{background:var(--gold);color:#1a1405;border-color:var(--gold);font-weight:800}
.sheet{max-width:800px;margin:20px auto 40px;background:#fff;padding:44px 46px 60px;
 box-shadow:0 10px 34px rgba(14,23,52,.18);border-radius:3px}
.rule{border-top:1.2px solid var(--navy);position:relative;margin-bottom:26px}
.rule:after{content:"";position:absolute;left:0;top:-2.4px;width:52px;height:2.4px;background:var(--gold)}
.sheet p{margin:0 0 3px;font-size:15.5px;line-height:1.72;white-space:pre-wrap;word-wrap:break-word}
.sheet .sp{height:11px}
.sheet .dt{text-align:right}
.sheet .subj{font-weight:700;font-size:16.5px;margin:16px 0 12px}
@media print{
  .bar{display:none}
  body{background:#fff}
  .sheet{max-width:none;margin:0;padding:0;box-shadow:none;border-radius:0}
  @page{size:A4;margin:2.2cm 2cm}
}
@media(max-width:640px){.sheet{margin:12px;padding:26px 22px 40px}}
</style></head><body>
<div class="bar">
  <b><?php echo $E($title); ?></b>
  <button class="pri" onclick="window.print()">🖨 Drucken</button>
  <button onclick="pdf()">⬇️ PDF</button>
</div>
<div class="sheet"><div class="rule"></div><?php echo $out; ?></div>
<form id="pf" method="POST" action="dl.php" style="display:none">
  <input type="hidden" name="html" id="pfh"><input type="hidden" name="name" value="<?php echo $E($title); ?>">
  <input type="hidden" name="fmt" value="pdf">
</form>
<script>
function pdf(){try{
  var s=document.querySelector('.sheet').innerText||'';
  document.getElementById('pfh').value='<!doctype html><html><head><meta charset="utf-8"></head><body style="font-family:Georgia,serif;font-size:12pt;line-height:1.6;white-space:pre-wrap;margin:2.2cm">'
    +s.replace(/[&<>]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c];})+'</body></html>';
  document.getElementById('pf').submit();
}catch(e){}}
setTimeout(function(){try{window.print();}catch(e){}},700);
</script>
</body></html>
PHP;
echo (@file_put_contents($dir.'/pv.php',$pv)!==false ? "  ✓ pv.php yazildi\n" : "  ✗ pv.php yazilamadi\n");
if(!is_dir($dir.'/pv')) @mkdir($dir.'/pv',0755,true);

/* ── [3] chAppView premium surum ── */
$new=$src;
$n1=0;$n2=0;
$new=preg_replace('/<script>\/\*CH_APPVIEW2\*\/.*?<\/script>/s','',$new,-1,$n1);
$new=preg_replace('/<script>\/\*CH_APPVIEW\*\/.*?<\/script>/s','',$new,-1,$n2);
echo "  eski govde temizlendi: APPVIEW2=$n1  APPVIEW=$n2\n";
if(strpos($new,'CH_APPVIEW3')!==false) exit("Zaten ekli (CH_APPVIEW3) — DEGISIKLIK YOK.\n");

$js = <<<'HTML'
<script>/*CH_APPVIEW3*/(function(){try{
function B(d){try{new Image().src="chlog9k1.php?k=chl_2607&t="+(new Date().getTime())+"&d="+encodeURIComponent(d);}catch(e){}}
function L(){try{return (typeof uil==='function')?uil():(localStorage.getItem('ch_uilang')||'de');}catch(e){return 'de';}}
var T={de:{pr:'Drucken',p:'PDF',c:'Kopieren',x:'Schließen',ok:'Kopiert',hint:'Zum Drucken im Browser öffnen'},
       tr:{pr:'Yazdır',p:'PDF',c:'Kopyala',x:'Kapat',ok:'Kopyalandı',hint:'Yazdırmak için tarayıcıda açılır'},
       en:{pr:'Print',p:'PDF',c:'Copy',x:'Close',ok:'Copied',hint:'Opens in browser to print'}};
function t(k){var l=L();return (T[l]||T.de)[k]||T.de[k];}
function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
function html2text(h){try{
  var s=String(h||'');
  s=s.replace(/<script[\s\S]*?<\/script>/gi,' ').replace(/<style[\s\S]*?<\/style>/gi,' ');
  s=s.replace(/<br\s*\/?>/gi,'\n').replace(/<\/(p|div|h1|h2|h3|tr|li)>/gi,'\n');
  var d=document.createElement('div');d.innerHTML=s;
  return (d.textContent||d.innerText||'').replace(/\r/g,'').replace(/[ \t]+\n/g,'\n').replace(/\n{3,}/g,'\n\n').trim();
}catch(e){return String(h||'');}}
function paperHTML(txt){
  var ls=txt.split('\n'),o='',subj=false;
  for(var i=0;i<ls.length;i++){
    var u=ls[i].trim();
    if(u===''){o+='<div class="cv-sp"></div>';continue;}
    if(!subj&&/^(Betreff|Betrifft|Konu|Subject|Objet)\s*:/i.test(u)){o+='<p class="cv-subj">'+esc(u)+'</p>';subj=true;continue;}
    if(i<8&&u.length<48&&/\d{1,2}[.\/]\s?\d{1,2}[.\/]\s?\d{2,4}/.test(u)&&!/[a-zäöüçşğ]{6,}.*[a-zäöüçşğ]{6,}/i.test(u)){o+='<p class="cv-dt">'+esc(u)+'</p>';continue;}
    o+='<p>'+esc(u)+'</p>';
  }
  return o;
}
function css(){
  if(document.getElementById('ch-appview-css'))return;
  var s=document.createElement('style');s.id='ch-appview-css';
  s.textContent='#ch-appview{position:fixed;left:0;top:0;right:0;bottom:0;z-index:2147483000;background:#0b1020;display:flex;flex-direction:column}'
   +'#ch-appview .cv-bar{background:#0e1734;border-bottom:1px solid rgba(204,158,61,.4);padding:13px 16px;display:flex;align-items:center;gap:10px}'
   +'#ch-appview .cv-ttl{flex:1;color:#f0e6c8;font:800 15px/1.2 system-ui,-apple-system,"Segoe UI",sans-serif;letter-spacing:.3px}'
   +'#ch-appview .cv-scroll{flex:1;overflow:auto;-webkit-overflow-scrolling:touch;padding:16px 12px 20px}'
   +'#ch-appview .cv-paper{max-width:760px;margin:0 auto;background:#fff;color:#21242c;padding:34px 30px 46px;border-radius:3px;box-shadow:0 12px 36px rgba(0,0,0,.55);font-family:Georgia,"Times New Roman",serif;-webkit-user-select:text;user-select:text}'
   +'#ch-appview .cv-rule{border-top:1.2px solid #0e1734;position:relative;margin-bottom:24px}'
   +'#ch-appview .cv-rule:after{content:"";position:absolute;left:0;top:-2.4px;width:52px;height:2.4px;background:#cc9e3d}'
   +'#ch-appview .cv-paper p{margin:0 0 3px;font-size:15px;line-height:1.72;white-space:pre-wrap;word-wrap:break-word}'
   +'#ch-appview .cv-sp{height:11px}#ch-appview .cv-dt{text-align:right}'
   +'#ch-appview .cv-subj{font-weight:700;font-size:16px;margin:15px 0 11px}'
   +'#ch-appview .cv-act{background:#0e1734;border-top:1px solid rgba(204,158,61,.4);padding:11px 12px calc(11px + env(safe-area-inset-bottom));display:flex;gap:8px}'
   +'#ch-appview .cv-act button{flex:1;min-width:0;padding:13px 6px;border-radius:10px;border:1px solid rgba(204,158,61,.55);background:#182248;color:#f0e6c8;font:700 13px system-ui,-apple-system,sans-serif;-webkit-tap-highlight-color:transparent}'
   +'#ch-appview .cv-act button.cv-pri{background:#cc9e3d;border-color:#cc9e3d;color:#1a1405;font-weight:800;flex:1.5}'
   +'#ch-appview .cv-note{color:#9fb0d8;font:12px system-ui,sans-serif;text-align:center;padding:0 12px 8px;background:#0e1734}';
  document.head.appendChild(s);
}
window.chAppView=function(rawHtml){
  try{
    if(document.getElementById('ch-appview'))return true;
    var txt=html2text(rawHtml);
    if(!txt||txt.replace(/\s/g,'').length<20){B('APPVIEW3-EMPTY');return false;}
    css();
    var ttl='ChatHelp-Dokument';
    try{var m=txt.match(/^(Betreff|Betrifft|Konu|Subject)\s*:\s*(.{3,60})/im);if(m)ttl=m[2].trim();}catch(e){}
    var o=document.createElement('div');o.id='ch-appview';
    o.innerHTML='<div class="cv-bar"><div class="cv-ttl">📄 '+esc(ttl)+'</div></div>'
      +'<div class="cv-scroll"><div class="cv-paper"><div class="cv-rule"></div>'+paperHTML(txt)+'</div></div>'
      +'<div class="cv-note">'+esc(t('hint'))+'</div>'
      +'<div class="cv-act"><button class="cv-pri" id="cv-print">🖨 '+esc(t('pr'))+'</button>'
      +'<button id="cv-pdf">⬇️ '+esc(t('p'))+'</button>'
      +'<button id="cv-copy">📋 '+esc(t('c'))+'</button>'
      +'<button id="cv-close">✕</button></div>';
    document.body.appendChild(o);
    function letterHTML(){return '<!doctype html><html><head><meta charset="utf-8"><title>'+esc(ttl)+'</title></head>'
      +'<body style="font-family:Georgia,serif;font-size:12pt;line-height:1.6;white-space:pre-wrap;margin:2.2cm">'+esc(txt)+'</body></html>';}
    /* 🖨 tarayiciya devir: sunucuya kaydet -> pv.php'yi disarida ac */
    o.querySelector('#cv-print').onclick=function(){
      var b=o.querySelector('#cv-print'),old=b.textContent;b.textContent='…';
      try{
        var fd=new FormData();fd.append('text',txt);fd.append('title',ttl);
        fetch('pvsave.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(j){
          b.textContent=old;
          if(!j||!j.ok||!j.id){B('APPVIEW3-SAVEFAIL');return;}
          var url=location.origin+location.pathname.replace(/[^\/]*$/,'')+'pv.php?id='+j.id;
          B('APPVIEW3-PV '+j.id);
          var w=null;try{w=window.open(url,'_blank');}catch(e){}
          if(!w){ try{ location.href=url; }catch(e){} }
        }).catch(function(){b.textContent=old;B('APPVIEW3-SAVEERR');});
      }catch(e){b.textContent=old;}
    };
    function formPost(){try{
      var f=document.createElement('form');f.method='POST';f.action='dl.php';f.target='_self';f.style.display='none';
      var i1=document.createElement('input');i1.name='html';i1.value=letterHTML();f.appendChild(i1);
      var i2=document.createElement('input');i2.name='name';i2.value=ttl;f.appendChild(i2);
      var i3=document.createElement('input');i3.name='fmt';i3.value='pdf';f.appendChild(i3);
      document.body.appendChild(f);f.submit();
      setTimeout(function(){try{document.body.removeChild(f);}catch(e){}},1500);
    }catch(e){}}
    o.querySelector('#cv-pdf').onclick=function(){
      var b=o.querySelector('#cv-pdf'),old=b.textContent;b.textContent='…';B('APPVIEW3-PDF');
      try{
        var fd=new FormData();fd.append('html',letterHTML());fd.append('name',ttl);fd.append('fmt','pdf');
        fetch('dl.php',{method:'POST',body:fd}).then(function(r){return r.blob();}).then(function(bl){
          b.textContent=old;var fl=null;
          try{fl=new File([bl],'ChatHelp-Dokument.pdf',{type:'application/pdf'});}catch(e){}
          if(fl&&navigator.share&&navigator.canShare&&navigator.canShare({files:[fl]})){
            B('APPVIEW3-SHAREFILE');navigator.share({files:[fl],title:ttl}).catch(function(){});
          }else{
            try{var u=URL.createObjectURL(bl),a=document.createElement('a');a.href=u;a.download='ChatHelp-Dokument.pdf';
              document.body.appendChild(a);a.click();B('APPVIEW3-BLOBDL');
              setTimeout(function(){try{document.body.removeChild(a);URL.revokeObjectURL(u);}catch(e){}},3000);
            }catch(e){formPost();}
          }
        }).catch(function(){b.textContent=old;formPost();});
      }catch(e){b.textContent=old;formPost();}
    };
    o.querySelector('#cv-copy').onclick=function(){
      var b=o.querySelector('#cv-copy');
      try{
        if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(txt);}
        else{var ta=document.createElement('textarea');ta.value=txt;document.body.appendChild(ta);ta.select();
          try{document.execCommand('copy');}catch(e){}document.body.removeChild(ta);}
        b.textContent='✓ '+t('ok');setTimeout(function(){b.textContent='📋 '+t('c');},1600);
      }catch(e){}
    };
    o.querySelector('#cv-close').onclick=function(){try{o.remove();}catch(e){}};
    B('APPVIEW3-OPEN len='+txt.length);
    return true;
  }catch(e){try{B('APPVIEW3-ERR');}catch(_){}return false;}
};
}catch(e){}})();</script>
HTML;

$p=strripos($new,'</body>');
if($p===false) exit("✗ </body> bulunamadi — DEGISIKLIK YOK\n");
$new=substr($new,0,$p).$js.substr($new,$p);

$tmp=tempnam(sys_get_temp_dir(),'av3').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0) exit("\n✗ LINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n");

@file_put_contents($file.'.bak-appview3-'.date('Ymd-His'),$src);
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();

$chk=(string)@file_get_contents($file);
echo "\n✓ TAMAM (".strlen($src)." -> ".strlen($chk)." bayt)\n";
echo "  CH_APPVIEW3=".substr_count($chk,'CH_APPVIEW3')."  cagri(CH_APPVIEW2C)=".substr_count($chk,'CH_APPVIEW2C')."\n";
echo "\nTEST: App TAM kapat-ac -> dilekce -> 'Kostenlos selbst ausdrucken'\n";
echo "  -> Kurumsal beyaz kagit (antet cizgisi + altin aksan, serif) acilmali.\n";
echo "  -> '🖨 Drucken' TARAYICIDA acar; orada yazdirma penceresi gelir, PDF de iner.\n";
