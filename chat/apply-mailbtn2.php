<?php
/**
 * ChatHelp — apply-mailbtn2 (CH_MAILBTN2) — App katmanindaki e-posta dugmesi v2.
 *
 * KANIT: pvmail.php dogrudan test edildi -> {"ok":true} — sunucu PDF uretiyor
 * ve e-postayi gonderiyor. Yani pdflib/pvmail/mail() SAGLAM. Sorun yalniz
 * App katmanindaki dugme davranisinda.
 *
 * v1'in zayifligi: dugme gizli bir satiri ac/kapa yapiyordu; kullanici tek
 * dokunusta HICBIR tepki gormuyordu (WebView'de konsol/alert de yok).
 *
 * v2:
 *  · Tek dokunusta ANINDA gorunur tepki — durum yazisi katmandaki not
 *    satirina yazilir ('Wird gesendet …' / '✓ Gesendet an …' / '✗ …')
 *  · E-posta adresi profilden/localStorage'dan bulunur; yoksa satir acilir
 *  · window.onerror -> katmanin ustunde kirmizi seritte hata metni
 *    (App'te konsol olmadigi icin hatayi gorebilmenin tek yolu)
 *  · Eski CH_MAILBTN blogu temizlenir (cift dugme olmaz)
 *
 * Kritik isaret dogrulamasi + lint + .bak + kilit tazeleme.
 * KULLANIM: /chat/pull2.php?key=...&files=apply-mailbtn2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
echo "apply-mailbtn2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$dir=__DIR__; $file=$dir.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_MAILBTN2')!==false) exit("Zaten ekli (CH_MAILBTN2) — DEGISIKLIK YOK.\n");

/* eski v1 blogunu kaldir */
$new=preg_replace('/<script>\/\*CH_MAILBTN\*\/.*?<\/script>/s','',$src,-1,$rm);
if($new===null) exit("preg hatasi\n");
echo "eski CH_MAILBTN blogu: ".($rm?"kaldirildi ($rm)":"bulunamadi (sorun degil)")."\n";

$js = <<<'HTML'
<script>/*CH_MAILBTN2*/(function(){try{
function L(){try{return (typeof uil==='function')?uil():(localStorage.getItem('ch_uilang')||'de');}catch(e){return 'de';}}
var T={de:{m:'PDF per E-Mail',ph:'E-Mail-Adresse eingeben',snd:'Senden',ing:'Wird gesendet …',
           ok:'✓ Gesendet an ',er:'✗ Fehler: ',bad:'Bitte gültige E-Mail eingeben.'},
       tr:{m:'PDF’i e-postala',ph:'E-posta adresi girin',snd:'Gönder',ing:'Gönderiliyor …',
           ok:'✓ Gönderildi: ',er:'✗ Hata: ',bad:'Geçerli bir e-posta girin.'},
       en:{m:'PDF by e-mail',ph:'Enter e-mail address',snd:'Send',ing:'Sending …',
           ok:'✓ Sent to ',er:'✗ Error: ',bad:'Please enter a valid e-mail.'}};
function t(k){var l=L();return (T[l]||T.de)[k]||T.de[k];}
function guessEmail(){try{
  for(var i=0;i<localStorage.length;i++){
    var v=localStorage.getItem(localStorage.key(i))||'';
    var m=String(v).match(/[\w.+-]+@[\w-]+\.[\w.]{2,}/); if(m) return m[0];
  }}catch(e){} return '';}

function errBar(ov,msg){
  try{
    var e=ov.querySelector('.cv-err');
    if(!e){ e=document.createElement('div'); e.className='cv-err';
      e.setAttribute('style','background:#7f1d1d;color:#fff;font:600 12px/1.4 system-ui;padding:9px 12px;white-space:pre-wrap;word-break:break-word');
      ov.insertBefore(e,ov.firstChild); }
    e.textContent=msg;
  }catch(_){}
}
function noteEl(ov){
  var n=ov.querySelector('.cv-note');
  if(!n){ n=document.createElement('div'); n.className='cv-note';
    n.setAttribute('style','color:#9fb0d8;font:12.5px system-ui;text-align:center;padding:7px 12px 0;background:#0e1734');
    var act=ov.querySelector('.cv-act'); if(act&&act.parentNode) act.parentNode.insertBefore(n,act); }
  return n;
}
function say(ov,msg){ try{ noteEl(ov).textContent=msg; }catch(_){} }

function docText(ov){
  try{ var p=ov.querySelector('.cv-paper'); if(p) return (p.innerText||p.textContent||'').trim(); }catch(e){}
  try{ return (ov.innerText||'').trim(); }catch(e){}
  return '';
}
function docTitle(ov){
  try{ var x=ov.querySelector('.cv-ttl'); if(x) return (x.textContent||'').replace(/^\s*📄\s*/,'').trim(); }catch(e){}
  return 'ChatHelp-Dokument';
}

function send(ov,to){
  say(ov,t('ing'));
  try{
    var fd=new FormData();
    fd.append('to',to); fd.append('text',docText(ov)); fd.append('title',docTitle(ov));
    fetch('pvmail.php',{method:'POST',body:fd})
      .then(function(r){ return r.text(); })
      .then(function(txt){
        var j=null; try{ j=JSON.parse(txt); }catch(_){}
        if(j&&j.ok){ say(ov,t('ok')+to); try{ localStorage.setItem('ch_mailto',to); }catch(_){} }
        else say(ov,t('er')+(j?(j.msg||'?'):String(txt).slice(0,90)));
      })
      .catch(function(e){ say(ov,t('er')+e); });
  }catch(e){ say(ov,t('er')+e); errBar(ov,'send: '+e); }
}

function askRow(ov,prefill){
  var old=ov.querySelector('.cv-mailrow'); if(old){ try{ old.querySelector('input').focus(); }catch(_){} return; }
  var row=document.createElement('div'); row.className='cv-mailrow';
  row.setAttribute('style','display:flex;gap:8px;padding:9px 12px 0;background:#0e1734;flex-wrap:wrap;align-items:center');
  var inp=document.createElement('input');
  inp.type='email'; inp.value=prefill||''; inp.placeholder=t('ph'); inp.setAttribute('autocomplete','email');
  inp.setAttribute('style','flex:1;min-width:150px;padding:11px 12px;border-radius:10px;border:1px solid rgba(204,158,61,.45);background:#0b1330;color:#f0e6c8;font:15px system-ui');
  var go=document.createElement('button'); go.textContent=t('snd');
  go.setAttribute('style','padding:11px 16px;border-radius:10px;border:0;background:#cc9e3d;color:#1a1405;font:800 13px system-ui');
  go.addEventListener('click',function(){
    var to=(inp.value||'').trim();
    if(!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(to)){ say(ov,t('bad')); return; }
    send(ov,to);
  });
  row.appendChild(inp); row.appendChild(go);
  var n=noteEl(ov); n.parentNode.insertBefore(row,n);
  try{ inp.focus(); }catch(_){}
}

function inject(ov){
  try{
    if(!ov||ov.getAttribute('data-ch-mail2')==='1') return;
    var act=ov.querySelector('.cv-act'); if(!act) return;
    ov.setAttribute('data-ch-mail2','1');
    /* v1 dugmesi kaldiysa temizle */
    try{ var olds=act.querySelectorAll('button');
      for(var i=0;i<olds.length;i++){ if(/PDF per E-Mail|e-postala|PDF by e-mail/i.test(olds[i].textContent||'')) olds[i].remove(); }
    }catch(_){}

    var b=document.createElement('button');
    b.textContent='📧 '+t('m');
    b.setAttribute('style','flex:1.6;min-width:0;padding:13px 6px;border-radius:10px;border:1px solid #cc9e3d;background:#cc9e3d;color:#1a1405;font:800 13px system-ui,-apple-system,sans-serif;-webkit-tap-highlight-color:transparent');
    b.addEventListener('click',function(){
      /* TEK DOKUNUSTA gorunur tepki */
      say(ov,'…');
      var to='';
      try{ to=localStorage.getItem('ch_mailto')||''; }catch(_){}
      if(!to) to=guessEmail();
      if(to && /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(to)) send(ov,to);
      else askRow(ov,'');
    });
    act.insertBefore(b,act.firstChild);
  }catch(e){ try{ errBar(ov,'inject: '+e); }catch(_){} }
}

window.onerror=function(m,s,l,c){ try{
  var ov=document.getElementById('ch-appview'); if(ov) errBar(ov,'JS: '+m+' ('+l+':'+c+')');
}catch(_){} return false; };

function scan(){ try{ var ov=document.getElementById('ch-appview'); if(ov) inject(ov); }catch(e){} }
function start(){ try{ scan();
  var mo=new MutationObserver(function(){ scan(); });
  mo.observe(document.body,{childList:true,subtree:true});
}catch(e){} }
if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',start); else start();
}catch(e){}})();</script>
HTML;

$p=strripos($new,'</body>');
if($p===false) exit("✗ </body> bulunamadi — DEGISIKLIK YOK\n");
$new=substr($new,0,$p).$js.substr($new,$p);

foreach(['CH_ISWV_GLOBAL'=>1,'CH_APPVIEW3'=>1,'CH_APPVIEW2C'=>4,'CH_FILLGAPS'=>1,'CH_MAILBTN2'=>1] as $m=>$min){
  if(substr_count($new,$m)<$min) exit("✗ Kritik isaret eksik olurdu ($m) — DEGISIKLIK YOK\n");
}

$tmp=tempnam(sys_get_temp_dir(),'mb2').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0) exit("\n✗ LINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n");

@file_put_contents($file.'.bak-mailbtn2-'.date('Ymd-His'),$src);
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();

$cur=(string)@file_get_contents($file);
@file_put_contents($file.'.GOOD-appprint',$cur);
@file_put_contents($dir.'/ch-applock.json',json_encode([
 'locked_at'=>date('c'),'size'=>strlen($cur),'sha1'=>sha1($cur),'note'=>'mailbtn2',
 'markers'=>['CH_ISWV_GLOBAL'=>substr_count($cur,'CH_ISWV_GLOBAL'),'CH_APPVIEW3'=>substr_count($cur,'CH_APPVIEW3'),
             'CH_APPVIEW2C'=>substr_count($cur,'CH_APPVIEW2C'),'CH_MAILBTN2'=>substr_count($cur,'CH_MAILBTN2'),
             'CH_FILLGAPS'=>substr_count($cur,'CH_FILLGAPS')],
 'files'=>['pv.php','pvsave.php','pvmail.php','pdflib.php']
],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

echo "\n✓ TAMAM (".strlen($src)." -> ".strlen($cur)." bayt)\n";
echo "  CH_MAILBTN2=".substr_count($cur,'CH_MAILBTN2')
    ."  CH_MAILBTN(eski)=".substr_count($cur,'/*CH_MAILBTN*/')
    ."  CH_APPVIEW3=".substr_count($cur,'CH_APPVIEW3')
    ."  CH_FILLGAPS=".substr_count($cur,'CH_FILLGAPS')."\n";
echo "  ✓ kilit tazelendi (sha1 ".substr(sha1($cur),0,12)."…)\n";
echo "\nTEST (App): belge -> '📧 PDF per E-Mail' TEK dokunus.\n";
echo "  · Dugmelerin ustundeki satirda ANINDA yazi cikar: '…' sonra 'Wird gesendet …'\n";
echo "  · Bitince: '✓ Gesendet an <adres>' veya '✗ Fehler: <sebep>'\n";
echo "  · Adres bilinmiyorsa once giris satiri acilir.\n";
echo "  · JS hatasi olursa katmanin USTUNDE kirmizi seritte gorunur.\n";
