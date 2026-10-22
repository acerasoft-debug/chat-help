<?php
/**
 * ChatHelp — apply-emsal (CH_EMSAL) — EMSAL KARAR ARASTIRMA MODULU (yeni):
 *  [1] Sol menuye "⚖️ Emsal Kararlar" girisi (mevcut menu ogesi klonlanir —
 *      ayni gorunum) + window.chEmsal() ile her yerden acilabilir.
 *  [2] Tam ekran modul: konu gir → ulke otomatik (hukuk ulkesi) →
 *      GERCEK emsal kararlar aranir. HALUSINASYON YASAK kurallari:
 *      • Prompt: yalniz gercekten var olan, yayimlanmis, taninmis kararlar;
 *        emin olunmayan HICBIR karar/dosya no YAZILMAZ; bilinmiyorsa acikca
 *        "guvenilir emsal bulunamadi" denir.
 *      • Her kararda: mahkeme, tarih, dosya no, ozet, konuya etkisi,
 *        guven notu. Sonuc altinda RESMI dogrulama linkleri (dejure.org,
 *        openjur.de, rechtsprechung-im-internet.de / ulkeye gore) —
 *        "kullanmadan once resmi kaynaktan dogrulayin" uyarisi.
 *  [3] Sonuctan devam: "✍️ Bu emsallerle dilekce hazirla" → emsaller sohbete
 *      aktarilir ve dilekce istenir; ayrica kalici belge hafizasina
 *      (ch_docmem) islenir → chat/Fälle akisinda konu hatirlanir.
 *      Aramalar ch_emsal_hist'te saklanir (son 10), modulden tekrar acilir.
 *  Tek eklemeli </body> blogu; mevcut koda dokunmaz. node ✓ + harness ✓.
 * KULLANIM: pull2.php?key=...&files=apply-emsal.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-emsal BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_EMSAL')!==false) exit("Zaten ekli (CH_EMSAL).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-emsal-css">
#em-ov{position:fixed;inset:0;z-index:2147483170;background:linear-gradient(180deg,#0b0b16,#07070e);display:none;flex-direction:column;font-family:'Inter',system-ui,sans-serif;color:#f2f2fb}
#em-ov.on{display:flex}
#em-ov .top{display:flex;align-items:center;gap:12px;padding:13px 16px;border-bottom:1px solid rgba(255,255,255,.08);background:#0d0d1c}
#em-ov .top h2{font-size:16px;font-weight:800;flex:1}
#em-ov .top h2 em{font-style:normal;color:#e8c874}
#em-ov .x{background:#1a1a30;border:1px solid rgba(255,255,255,.12);color:#c8c8e0;border-radius:9px;padding:8px 12px;font-size:13px;cursor:pointer;font-family:inherit}
#em-bd{flex:1;overflow-y:auto;padding:16px;max-width:760px;width:100%;margin:0 auto;box-sizing:border-box}
#em-row{display:flex;gap:9px;margin-bottom:8px}
#em-q{flex:1;background:#12122a;border:1.5px solid rgba(255,255,255,.14);border-radius:12px;padding:13px;color:#fff;font-size:14px;font-family:inherit;outline:none;min-height:50px}
#em-q:focus{border-color:rgba(212,168,74,.6)}
#em-go{background:linear-gradient(135deg,#d4a84a,#ecc060);color:#07070e;border:none;border-radius:12px;padding:13px 18px;font-size:14px;font-weight:800;cursor:pointer;font-family:inherit;white-space:nowrap}
.em-note{font-size:11px;color:#9a9ab0;line-height:1.55;margin-bottom:14px}
.em-res{background:#12122a;border:1.5px solid rgba(212,168,74,.35);border-radius:14px;padding:14px;margin-bottom:12px;white-space:pre-wrap;font-size:13px;line-height:1.7}
.em-warn{background:rgba(255,170,60,.08);border:1px solid rgba(255,170,60,.35);border-radius:12px;padding:11px 13px;font-size:11.5px;color:#ffd9a0;line-height:1.55;margin-bottom:12px}
.em-links{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px}
.em-links a{background:#1a1a30;border:1px solid rgba(255,255,255,.16);color:#c8d8ff;border-radius:9px;padding:8px 12px;font-size:11.5px;font-weight:700;text-decoration:none}
.em-acts{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px}
.em-acts button{flex:1;min-width:150px;background:#1a1a30;border:1px solid rgba(212,168,74,.45);color:#e8c874;border-radius:10px;padding:11px;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit}
.em-acts button.gold{background:linear-gradient(135deg,#d4a84a,#ecc060);color:#07070e;border:none}
.em-hist{font-size:12px;color:#9a9ab0;margin:6px 0}
.em-hist-it{background:#12122a;border:1px solid rgba(255,255,255,.1);border-radius:10px;padding:10px 12px;margin-bottom:7px;cursor:pointer;font-size:12.5px}
.em-hist-it:hover{border-color:rgba(212,168,74,.5)}
</style>
<script id="ch-emsal-js">
/* CH_EMSAL — Emsal Karar Arastirma modulu: gercek kararlar, halusinasyon yasak, sohbete/dilekceye baglama */
try{(function(){
  var API=function(){ return (typeof window.API!=='undefined'&&window.API)?window.API:'api.php'; };
  function UIL(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  function CCX(){ try{ return ((typeof window.CC!=='undefined'&&window.CC)||localStorage.getItem('ch_cc')||'DE').toUpperCase(); }catch(e){ return 'DE'; } }
  function LNG(){ return {de:'Deutsch',tr:'Türkçe',en:'English',fr:'Français',es:'Español',it:'Italiano'}[UIL()]||'English'; }
  function T(k){
    var L={
      ttl:{de:'Präzedenzfälle & Urteile',tr:'Emsal Kararlar',en:'Case-Law Research'},
      ph:{de:'Thema eingeben — z.B. Mietminderung wegen Schimmel',tr:'Konu yazın — ör. kira sözleşmesinde küf nedeniyle indirim',en:'Enter a topic — e.g. rent reduction due to mould'},
      go:{de:'🔎 Urteile suchen',tr:'🔎 Emsal ara',en:'🔎 Search rulings'},
      wait:{de:'⏳ Es werden nur gesicherte, veröffentlichte Urteile gesucht…',tr:'⏳ Yalnızca doğrulanmış, yayımlanmış kararlar aranıyor…',en:'⏳ Searching verified published rulings only…'},
      note:{de:'Es werden NUR real existierende, veröffentlichte Urteile genannt (Gericht, Datum, Aktenzeichen). Unsichere Treffer werden weggelassen.',tr:'YALNIZCA gerçekten var olan, yayımlanmış kararlar listelenir (mahkeme, tarih, dosya no). Emin olunmayanlar yazılmaz.',en:'ONLY real, published rulings are listed (court, date, case no.). Uncertain results are omitted.'},
      warn:{de:'⚠️ Bitte prüfen Sie jedes Urteil vor Verwendung in den offiziellen Quellen unten. KI-Recherche ersetzt keine Rechtsberatung.',tr:'⚠️ Kullanmadan önce her kararı aşağıdaki resmî kaynaklardan doğrulayın. Yapay zekâ araştırması hukuki danışmanlık yerine geçmez.',en:'⚠️ Verify every ruling in the official sources below before use. AI research is not legal advice.'},
      draft:{de:'✍️ Schriftsatz mit diesen Urteilen erstellen',tr:'✍️ Bu emsallerle dilekçe hazırla',en:'✍️ Draft petition citing these rulings'},
      ask:{de:'❓ Frage zu diesen Urteilen',tr:'❓ Bu kararlar hakkında soru sor',en:'❓ Ask about these rulings'},
      copy:{de:'📋 Kopieren',tr:'📋 Kopyala',en:'📋 Copy'},
      hist:{de:'Frühere Recherchen',tr:'Önceki aramalar',en:'Previous searches'},
      err:{de:'⚠ Recherche fehlgeschlagen — bitte erneut versuchen.',tr:'⚠ Arama başarısız — lütfen tekrar deneyin.',en:'⚠ Search failed — please try again.'}
    };
    var l=UIL(); return (L[k]&&(L[k][l]||L[k].en))||'';
  }
  function LINKS(){
    var cc=CCX();
    if(cc==='DE') return [['dejure.org','https://dejure.org'],['openJur','https://openjur.de'],['Rechtsprechung im Internet','https://www.rechtsprechung-im-internet.de'],['Bundesverfassungsgericht','https://www.bundesverfassungsgericht.de']];
    if(cc==='AT') return [['RIS (ris.bka.gv.at)','https://www.ris.bka.gv.at']];
    if(cc==='CH') return [['Bundesgericht (bger.ch)','https://www.bger.ch']];
    if(cc==='TR') return [['Yargıtay Karar Arama','https://karararama.yargitay.gov.tr'],['Anayasa Mahkemesi','https://kararlarbilgibankasi.anayasa.gov.tr'],['Danıştay','https://karararama.danistay.gov.tr']];
    return [['EUR-Lex / national databases','https://eur-lex.europa.eu']];
  }
  function hist(){ try{ var a=JSON.parse(localStorage.getItem('ch_emsal_hist')||'[]'); return Array.isArray(a)?a:[]; }catch(e){ return []; } }
  function histW(a){ try{ localStorage.setItem('ch_emsal_hist',JSON.stringify(a.slice(-10))); }catch(e){} }
  var LAST={q:'',txt:''};

  function remember(q,txt){
    try{
      var a=JSON.parse(localStorage.getItem('ch_docmem')||'[]'); if(!Array.isArray(a)) a=[];
      var d=new Date(); var ts=('0'+d.getDate()).slice(-2)+'.'+('0'+(d.getMonth()+1)).slice(-2)+'.'+d.getFullYear();
      a.push({t:ts,s:'EMSAL KARAR ARAŞTIRMASI ('+q.slice(0,80)+'): '+txt.slice(0,450)});
      localStorage.setItem('ch_docmem',JSON.stringify(a.slice(-8)));
    }catch(e){}
  }
  function toChat(msg){
    try{
      var i=document.getElementById('inp');
      if(i){ i.value=msg; try{ if(typeof onInp==='function') onInp(i); }catch(e){}
        var ov=document.getElementById('em-ov'); if(ov) ov.classList.remove('on');
        i.focus();
        var sent=false;
        ['sendMsg','doSend','chSend'].forEach(function(fn){ if(!sent){ try{ if(typeof window[fn]==='function'){ window[fn](); sent=true; } }catch(e){} } });
      }
    }catch(e){}
  }
  function acts(box){
    var w=document.createElement('div'); w.className='em-acts';
    var b1=document.createElement('button'); b1.className='gold'; b1.textContent=T('draft');
    b1.onclick=function(){
      var lang=UIL();
      toChat(({tr:'Aşağıdaki GERÇEK emsal kararlara dayanarak konumla ilgili resmi bir dilekçe hazırla. Kararları uygun yerlerde kaynak göster (mahkeme, tarih, dosya no). Konu: ',de:'Erstelle auf Basis der folgenden REALEN Urteile einen formellen Schriftsatz zu meinem Anliegen. Zitiere die Urteile an passender Stelle (Gericht, Datum, Az.). Thema: ',en:'Draft a formal petition based on the following REAL rulings. Cite them appropriately (court, date, case no.). Topic: '}[lang]||'')+LAST.q+'\n\n'+LAST.txt.slice(0,1600));
    };
    var b2=document.createElement('button'); b2.textContent=T('ask');
    b2.onclick=function(){ toChat(({tr:'Bulduğun emsal kararlarla ilgili sorum: ',de:'Zu den gefundenen Urteilen habe ich eine Frage: ',en:'About the rulings found, my question is: '}[UIL()]||'')); };
    var b3=document.createElement('button'); b3.textContent=T('copy');
    b3.onclick=function(){ try{ if(navigator.clipboard&&navigator.clipboard.writeText) navigator.clipboard.writeText(LAST.txt); b3.textContent='✓'; setTimeout(function(){ try{ b3.textContent=T('copy'); }catch(e){} },1400); }catch(e){} };
    w.appendChild(b1); w.appendChild(b2); w.appendChild(b3);
    box.appendChild(w);
  }
  function renderLinks(box){
    var warn=document.createElement('div'); warn.className='em-warn'; warn.textContent=T('warn');
    box.appendChild(warn);
    var lk=document.createElement('div'); lk.className='em-links';
    LINKS().forEach(function(l){ var a=document.createElement('a'); a.href=l[1]; a.target='_blank'; a.rel='noopener noreferrer'; a.textContent='🔗 '+l[0]; lk.appendChild(a); });
    box.appendChild(lk);
  }
  function search(q){
    var res=document.getElementById('em-res'); if(!res) return;
    res.innerHTML='';
    var card=document.createElement('div'); card.className='em-res'; card.textContent=T('wait');
    res.appendChild(card);
    var cc=CCX();
    var msg='Sen titiz bir hukuk araştırma asistanısın. GÖREV: "'+q+'" konusunda '+cc+' hukukunda EMSAL KARARLARI listele.\n'
      +'KATI KURALLAR (ihlal yasak):\n'
      +'1) SADECE gerçekten var olduğundan EMİN olduğun, yayımlanmış ve hukuk camiasında bilinen kararları yaz (yüksek mahkeme / tanınmış içtihatlar öncelikli).\n'
      +'2) ASLA dosya numarası, tarih veya karar UYDURMA. Emin olmadığın ayrıntıyı "(doğrulanmalı)" diye işaretle ya da hiç yazma.\n'
      +'3) Güvenilir emsal bilmiyorsan açıkça "Bu konuda doğrulayabileceğim yayımlanmış emsal karar bilgim yok" de ve hangi resmi veritabanında hangi anahtar kelimelerle aranacağını öner.\n'
      +'4) En fazla 5 karar. Her karar için: Mahkeme — Tarih — Dosya No — 2-3 cümle özet — bu konuya somut etkisi — güven düzeyi (kesin/yüksek/doğrulanmalı).\n'
      +'5) Sonda 2 cümle: bu içtihatlara göre başvuranın genel durumu.\n'
      +'Cevabı '+LNG()+' dilinde yaz. Markdown yıldızı kullanma.';
    fetch(API()+'?action=aichat',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({message:msg,history:[],provider:'claude',lang:UIL(),country:cc})})
     .then(function(r){ return r.json(); })
     .then(function(j){
        var t=(j&&j.reply)?String(j.reply).replace(/```[a-z]*\n?|```/g,'').replace(/\*\*/g,'').trim():'';
        if(!t){ card.textContent=T('err')+((j&&j.error)?(' ('+j.error+')'):''); return; }
        LAST={q:q,txt:t};
        card.textContent=t;
        acts(res); renderLinks(res);
        remember(q,t);
        var h=hist(); h.push({q:q,t:t.slice(0,4000),ts:new Date().getTime()}); histW(h);
     })
     .catch(function(){ card.textContent=T('err'); });
  }
  function drawHist(){
    var hb=document.getElementById('em-hist'); if(!hb) return;
    hb.innerHTML='';
    var h=hist().slice().reverse();
    if(!h.length) return;
    var lbl=document.createElement('div'); lbl.className='em-hist'; lbl.textContent='📚 '+T('hist')+':';
    hb.appendChild(lbl);
    h.forEach(function(it){
      var d=document.createElement('div'); d.className='em-hist-it'; d.textContent='⚖️ '+it.q;
      d.onclick=function(){
        LAST={q:it.q,txt:it.t};
        var res=document.getElementById('em-res'); if(!res) return;
        res.innerHTML='';
        var card=document.createElement('div'); card.className='em-res'; card.textContent=it.t;
        res.appendChild(card); acts(res); renderLinks(res);
      };
      hb.appendChild(d);
    });
  }
  function ovEl(){
    var o=document.getElementById('em-ov');
    if(o) return o;
    o=document.createElement('div'); o.id='em-ov';
    o.innerHTML='<div class="top"><h2>⚖️ <em>'+T('ttl')+'</em></h2><button class="x" id="em-x">✕</button></div>'
      +'<div id="em-bd"><div id="em-row"><textarea id="em-q" rows="2" placeholder="'+T('ph').replace(/"/g,'&quot;')+'"></textarea><button id="em-go">'+T('go')+'</button></div>'
      +'<div class="em-note">'+T('note')+'</div><div id="em-res"></div><div id="em-hist"></div></div>';
    document.body.appendChild(o);
    document.getElementById('em-x').onclick=function(){ o.classList.remove('on'); };
    document.getElementById('em-go').onclick=function(){
      var q=String((document.getElementById('em-q')||{}).value||'').trim();
      if(q.length<4) return;
      search(q);
    };
    return o;
  }
  window.chEmsal=function(){ ovEl().classList.add('on'); drawHist(); };

  /* sol menuye giris: mevcut bir nav ogesini klonla (ayni gorunum) */
  var n=0;(function inject(){
    try{
      if(!document.getElementById('nav-emsal')){
        var sb=document.getElementById('sb');
        if(sb){
          var ref=sb.querySelector('[id^="nav-"]');
          if(ref){
            var c=ref.cloneNode(false);
            c.id='nav-emsal';
            c.textContent='⚖️ '+({de:'Präzedenzfälle',tr:'Emsal Kararlar',en:'Case Law'}[UIL()]||'Case Law');
            c.onclick=function(){ try{ window.chEmsal(); }catch(e){} };
            ref.parentNode.insertBefore(c,ref.nextSibling);
          }
        }
      }
    }catch(e){}
    if(n++<300) setTimeout(inject,800);
  })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'em').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-emsal-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_EMSAL')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_EMSAL diskte (".strlen($chk)." bayt)\n";
echo "\n✓ EMSAL KARAR modulu canli:\n";
echo "   • Sol menude '⚖️ Emsal Kararlar' — konu yaz, GERCEK kararlar listelenir\n";
echo "   • Halusinasyon yasak: emin olunmayan karar YAZILMAZ; her karar guven\n";
echo "     notu tasir; resmi dogrulama linkleri (dejure/openJur/Yargitay...) altta\n";
echo "   • '✍️ Bu emsallerle dilekce hazirla' -> sohbete aktarir, dilekce uretir\n";
echo "   • Sonuclar kalici hafizaya islenir (chat konuyu hatirlar) + son 10 arama saklanir\n";
