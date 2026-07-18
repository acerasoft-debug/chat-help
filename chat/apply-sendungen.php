<?php
/**
 * ChatHelp — apply-sendungen (CH_SENDUNGEN) — "Meine Sendungen" premium sayfa:
 *  TUM gonderiler (Fax/Einschreiben/Brief) kalici kaydedilir; kullanici her zaman
 *  girip Sendebericht (fax) veya Einschreiben-Beleg/Status (LetterXpress) alir.
 *   • fetch sarmasi: postversand send_fax/send_letter basarilarini otomatik
 *     ch_sendungen'e yazar (message_id/job_id ile).
 *   • Sol menude "📨 Meine Sendungen" girisi (premium: Pro/Elite; digerleri upsell).
 *   • Panel: tarih, tur rozeti, alici, durum, fiyat, id/takip + "🔄 Status" (canli
 *     fax_status/letter_status sorgusu) + sil.
 *  Tamamen EKLEMELI. node ✓ + harness ✓.
 * KULLANIM: pull2.php?key=...&files=apply-sendungen.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-sendungen BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_SENDUNGEN')!==false) exit("Zaten ekli (CH_SENDUNGEN).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-sendungen-css">
#snd-ov{position:fixed;inset:0;background:rgba(6,10,24,.74);backdrop-filter:blur(3px);z-index:99997;display:none;align-items:flex-start;justify-content:center;overflow:auto;padding:22px 12px}
#snd-ov.on{display:flex}
#snd-box{background:var(--card,#0f1830);border:1px solid rgba(255,255,255,.12);border-radius:16px;max-width:720px;width:100%;padding:18px 18px 24px;color:#e8eefc;box-shadow:0 20px 60px rgba(0,0,0,.5)}
#snd-box h3{margin:0 0 3px;font-size:17px}
.snd-sub{font-size:12px;color:#9fb4d8;margin-bottom:12px}
.snd-row{border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:10px 12px;margin:8px 0;background:rgba(255,255,255,.02)}
.snd-top{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px}
.snd-badge{font-size:9px;font-weight:800;letter-spacing:.4px;padding:2px 8px;border-radius:100px}
.snd-meta{font-size:11px;color:#9fb4d8;line-height:1.5}
.snd-btn{font-size:11px;padding:4px 10px;border-radius:8px;border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.04);color:#cfe0ff;cursor:pointer}
.snd-btn.p{border-color:rgba(212,168,74,.5);color:#f0d493}
.snd-empty{text-align:center;color:#8a9ec4;font-size:13px;padding:26px 10px}
.snd-up{border:1px solid rgba(212,168,74,.4);border-radius:12px;padding:16px;background:linear-gradient(135deg,rgba(212,168,74,.1),rgba(212,168,74,.02));font-size:13px;line-height:1.6;color:#f0d493}
</style>
<script id="ch-sendungen-js">
/* CH_SENDUNGEN — Meine Sendungen: kalici gonderi kaydi + Bericht/Beleg */
try{(function(){
  function UIL(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
  function API(){ return (typeof window.API!=='undefined'&&window.API)?window.API:'api.php'; }
  function PVURL(){ var a=API(); var i=a.lastIndexOf('/'); return (i>=0?a.slice(0,i+1):'')+'postversand.php'; }
  function planNorm(p){ p=(''+(p||'')).toLowerCase().trim(); if(/elite|unbegrenzt|unlimited|premium\+|max/.test(p)) return 'elite'; if(/\bpro\b|pro[_-]|professional/.test(p)) return 'pro'; if(/basic|basis|starter/.test(p)) return 'basic'; if(/free|gratis|kostenlos/.test(p)) return 'free'; return ''; }
  function plan(){ var cand=[]; try{ cand.push(localStorage.getItem('ch_plan')); }catch(e){} try{ var u=JSON.parse(localStorage.getItem('ch_user')||'{}'); if(u&&u.plan) cand.push(u.plan); }catch(e){} try{ if(window.P&&window.P.plan) cand.push(window.P.plan); }catch(e){} try{ if(typeof window.chPlan==='function') cand.push(window.chPlan()); }catch(e){} var rank={free:0,basic:1,pro:2,elite:3},best='free',bR=0; for(var i=0;i<cand.length;i++){ var n=planNorm(cand[i]); if(!n) continue; if(rank[n]>bR){ bR=rank[n]; best=n; } } return best; }
  function isPrem(){ return /basic|pro|elite/.test(plan()); }
  function list(){ try{ var a=JSON.parse(localStorage.getItem('ch_sendungen')||'[]'); return Array.isArray(a)?a:[]; }catch(e){ return []; } }
  function save(a){ try{ localStorage.setItem('ch_sendungen',JSON.stringify(a.slice(-200))); }catch(e){} }
  function T(k){
    var L={
      ttl:{de:'📨 Meine Sendungen',tr:'📨 Gönderilerim',en:'📨 My shipments'},
      sub:{de:'Alle Fax-/Brief-/Einschreiben-Sendungen — jederzeit Sendebericht & Beleg abrufen.',tr:'Tüm faks/mektup/Einschreiben gönderileriniz — Bericht ve teslim belgesini istediğinizde alın.',en:'All fax/letter/registered shipments — get report & receipt anytime.'},
      empty:{de:'Noch keine Sendungen. Sobald Sie ein Dokument versenden, erscheint es hier.',tr:'Henüz gönderi yok. Bir belge gönderdiğinizde burada görünür.',en:'No shipments yet. Once you send a document it appears here.'},
      status:{de:'🔄 Status',tr:'🔄 Durum',en:'🔄 Status'},
      del:{de:'🗑',tr:'🗑',en:'🗑'},
      to:{de:'An',tr:'Alıcı',en:'To'},price:{de:'Preis',tr:'Ücret',en:'Price'},ref:{de:'Beleg-Nr.',tr:'Belge no',en:'Ref.'},track:{de:'Sendungsnr.',tr:'Takip no',en:'Tracking'},
      up:{de:'📨 „Meine Sendungen" ist ein Premium-Feature (Pro/Elite): dauerhaftes Sende-Archiv mit Sendebericht (Fax) und Einschreiben-Beleg. Jetzt upgraden, um alle Belege jederzeit abzurufen.',tr:'📨 „Gönderilerim" premium bir özelliktir (Pro/Elite): kalıcı gönderi arşivi, faks Bericht ve Einschreiben teslim belgesi. Tüm belgeleri her zaman almak için yükseltin.',en:'📨 “My shipments” is a Premium feature (Pro/Elite): permanent archive with fax report and registered receipt. Upgrade to access all receipts anytime.'},
      querying:{de:'⏳ Wird abgefragt…',tr:'⏳ Sorgulanıyor…',en:'⏳ Querying…'}
    };
    var l=UIL(); return (L[k]&&(L[k][l]||L[k].en))||'';
  }
  function typeInfo(t){
    t=String(t||'');
    if(t.indexOf('fax')===0) return {ic:'📠',lab:'FAX',col:'#42b6df'};
    if(t.indexOf('einschreiben:einwurf')===0) return {ic:'📬',lab:'EINWURF',col:'#42df94'};
    if(t.indexOf('einschreiben')===0) return {ic:'📮',lab:'EINSCHREIBEN',col:'#42df94'};
    return {ic:'✉️',lab:'BRIEF',col:'#9fb4d8'};
  }
  function fmtDate(ts){ try{ var d=new Date(ts); function z(n){return(n<10?'0':'')+n;} return z(d.getDate())+'.'+z(d.getMonth()+1)+'.'+d.getFullYear()+' '+z(d.getHours())+':'+z(d.getMinutes()); }catch(e){ return ''; } }

  /* ── kayit: fetch sarmasi ── */
  window.chRecordSendung=function(o){ try{ var a=list(); o.sid=o.sid||('s'+new Date().getTime()+Math.floor(Math.random()*1000)); o.ts=o.ts||new Date().getTime(); a.push(o); save(a); if(document.getElementById('snd-ov')&&document.getElementById('snd-ov').classList.contains('on')) render(); }catch(e){} };
  var _f=window.fetch;
  if(_f && !_f.__snd){
    var w=function(u,opt){
      var url=(typeof u==='string')?u:((u&&u.url)||'');
      var isSend=/postversand\.php/.test(url) && /action=send_(fax|letter)/.test(url);
      var body=null; if(isSend&&opt&&opt.body){ try{ body=JSON.parse(opt.body); }catch(e){} }
      var pr=_f.apply(this,arguments);
      if(isSend&&pr&&pr.then){ pr.then(function(r){ try{ r.clone().json().then(function(j){ try{
        if(j&&j.ok){
          var isFax=/action=send_fax/.test(url);
          var rec=(body&&(body.recipient||body.fax_number))||''; var res=j.result||{};
          var mid='';
          if(isFax){ try{ mid=(res.data&&res.data.messages&&res.data.messages[0]&&(res.data.messages[0].message_id||res.data.messages[0].messageid))||''; }catch(e){} }
          else { mid=res.job_id||res.id||(res.data&&(res.data.job_id||res.data.id))||(res.letter&&res.letter.id)||''; }
          var price=(res.letter&&res.letter.price)||res.price||(res.data&&res.data.total_price)||'';
          var tp=isFax?'fax':((body&&body.registered)?('einschreiben:'+body.registered):'brief');
          window.chRecordSendung({type:tp,to:String(rec).split('\n')[0].slice(0,60),full:String(rec).slice(0,300),mid:String(mid),price:String(price),status:isFax?'gesendet':'übermittelt',mode:j.mode||''});
        }
      }catch(e){} }); }catch(e){} }); }
      return pr;
    };
    w.__snd=1; window.fetch=w;
  }

  /* ── panel ── */
  function ovEl(){ var o=document.getElementById('snd-ov'); if(!o){ o=document.createElement('div'); o.id='snd-ov'; o.innerHTML='<div id="snd-box"></div>'; document.body.appendChild(o); o.addEventListener('click',function(e){ if(e.target===o) o.classList.remove('on'); }); } return o; }
  function refreshStatus(sid,btn){
    var a=list(); var it=null; for(var i=0;i<a.length;i++) if(a[i].sid===sid) it=a[i]; if(!it||!it.mid){ return; }
    var isFax=String(it.type).indexOf('fax')===0;
    var url=PVURL()+(isFax?('?action=fax_status&mid='+encodeURIComponent(it.mid)):('?action=letter_status&jid='+encodeURIComponent(it.mid)))+'&_cb='+(+new Date());
    var old=btn?btn.textContent:''; if(btn){ btn.textContent=T('querying'); btn.disabled=true; }
    fetch(url).then(function(r){ return r.json(); }).then(function(j){ if(btn){ btn.textContent=old; btn.disabled=false; }
      try{ var a2=list(); for(var i=0;i<a2.length;i++) if(a2[i].sid===sid){ if(j&&j.status) a2[i].status=String(j.status); if(j&&j.tracking) a2[i].track=String(j.tracking); a2[i].checked=+new Date(); } save(a2); render(); }catch(e){}
    }).catch(function(){ if(btn){ btn.textContent=old; btn.disabled=false; } });
  }
  function del(sid){ var a=list().filter(function(x){ return x.sid!==sid; }); save(a); render(); }
  function render(){
    var box=document.getElementById('snd-box'); if(!box) return;
    var h='<h3>'+T('ttl')+'</h3><div class="snd-sub">'+esc(T('sub'))+'</div>';
    if(!isPrem()){ box.innerHTML=h+'<div class="snd-up">'+esc(T('up'))+'</div>'; return; }
    var a=list().slice().reverse();
    if(!a.length){ box.innerHTML=h+'<div class="snd-empty">'+esc(T('empty'))+'</div>'; return; }
    h+=a.map(function(it){
      var ti=typeInfo(it.type);
      var s='<div class="snd-row"><div class="snd-top">'
        +'<span class="snd-badge" style="color:'+ti.col+';border:1px solid '+ti.col+'55">'+ti.ic+' '+ti.lab+'</span>'
        +'<span style="font-size:11px;color:#8a9ec4">'+esc(fmtDate(it.ts))+'</span>'
        +'<span style="flex:1"></span>'
        +'<span class="snd-badge" style="color:#cfe0ff;border:1px solid rgba(255,255,255,.2)">'+esc(it.status||'—')+(it.mode==='test'?' · test':'')+'</span></div>'
        +'<div class="snd-meta">'+esc(T('to'))+': <b>'+esc(it.to||'—')+'</b>'
        +(it.price?' · '+esc(T('price'))+': '+esc(it.price):'')
        +(it.mid?' · '+esc(T('ref'))+': '+esc(it.mid):'')
        +(it.track?'<br>'+esc(T('track'))+': <b style="color:#42df94">'+esc(it.track)+'</b>':'')+'</div>'
        +'<div style="display:flex;gap:6px;margin-top:7px">'
        +(it.mid?'<button class="snd-btn p" data-st="'+esc(it.sid)+'">'+T('status')+'</button>':'')
        +'<button class="snd-btn" data-del="'+esc(it.sid)+'">'+T('del')+'</button></div></div>';
      return s;
    }).join('');
    box.innerHTML=h;
    Array.prototype.forEach.call(box.querySelectorAll('[data-st]'),function(b){ b.onclick=function(){ refreshStatus(b.getAttribute('data-st'),b); }; });
    Array.prototype.forEach.call(box.querySelectorAll('[data-del]'),function(b){ b.onclick=function(){ del(b.getAttribute('data-del')); }; });
  }
  window.chSendungen=function(){ ovEl().classList.add('on'); render(); };

  /* ── sol menu girisi ── */
  var n=0;(function inject(){
    try{
      if(!document.getElementById('nav-sendungen')){
        var sb=document.getElementById('sb');
        if(sb){ var ref=sb.querySelector('[id^="nav-"]');
          if(ref){ var c=ref.cloneNode(false); c.id='nav-sendungen';
            c.textContent='📨 '+({de:'Meine Sendungen',tr:'Gönderilerim',en:'My shipments'}[UIL()]||'My shipments');
            c.onclick=function(){ try{ window.chSendungen(); }catch(e){} };
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
$tmp = tempnam(sys_get_temp_dir(),'sn').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-sendungen-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_SENDUNGEN')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_SENDUNGEN diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Meine Sendungen: her gonderi otomatik kaydedilir; premium sayfada\n";
echo "   Sendebericht (fax) / Einschreiben-Beleg (LetterXpress) her zaman alinir.\n";
