<?php
/**
 * ChatHelp — apply-vize-pruef (CH_VIZE_PRUF) — Vize Asistani "Hazirla" duzeni
 *  + son kontrol (Prüfung). 3 parca (hepsi EKLEMELI, dump-vzgen-full ile
 *  dogrulanmis akisin uzerine sarmalama — mevcut kod DEGISMEZ):
 *  [1] KISISEL BILGI FORMU: Ilk "Hazirla"da kisa form (ad soyad, dogum,
 *      pasaport, meslek, isveren/isyeri, adres) — BIR KEZ sorulur,
 *      localStorage'a kaydedilir. Belge uretim istegi giderken fetch
 *      yakalanir ve bu bilgiler prompt'a eklenir -> belgeler artik yer
 *      tutucusuz, kisisellesmis cikar ("duzen yok" sorununun cozumu).
 *  [2] HAZIRLANAN BELGE TAKIBI: hangi belgeler hazirlandiysa isaretlenir.
 *  [3] 🧪 PRÜFUNG: belge listesi adiminda "Bitti"nin yanina yeni buton —
 *      hazirlanan belgeler + ekranda gorunen evrak listesi Claude'a
 *      gonderilir, eksikler/uyarilar/hazirlik yuzdesi kisa rapor halinde
 *      sik bir panelde gosterilir.
 *  node --check ✓ + 4 senaryoluk fonksiyonel harness ✓.
 * KULLANIM: pull2.php?key=...&files=apply-vize-pruef.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-vize-pruef BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_VIZE_PRUF')!==false) exit("Zaten ekli (CH_VIZE_PRUF).\n");
if (strpos($src,'window.__vzGen=')===false) exit("HATA: Vize Asistani (__vzGen) yok.\n");

$block = <<<'HTMLBLOCK'
<style id="ch-vize-pruef-css">
/* CH_VIZE_PRUF — kisisel bilgi formu + Prüfung paneli */
#vzp-ov,#vzq-ov{position:fixed;inset:0;z-index:2147483000;background:rgba(4,4,12,.78);backdrop-filter:blur(5px);display:none;align-items:center;justify-content:center;padding:18px;font-family:'Inter',system-ui,sans-serif}
#vzp-ov.on,#vzq-ov.on{display:flex}
#vzp-card,#vzq-card{width:100%;max-width:430px;max-height:86vh;overflow-y:auto;background:linear-gradient(180deg,#14142e,#0c0c1c);border:1px solid rgba(212,168,74,.4);border-radius:18px;padding:22px 20px;color:#f2f2fb;box-shadow:0 24px 70px rgba(0,0,0,.55)}
.vzp-t{font-size:16.5px;font-weight:800;margin-bottom:4px}
.vzp-s{font-size:12px;color:#9c9cc4;margin-bottom:14px;line-height:1.5}
.vzp-l{display:block;font-size:11px;color:#a8a8d0;text-transform:uppercase;letter-spacing:.5px;margin:10px 0 4px}
.vzp-i{width:100%;background:#101024;border:1.5px solid rgba(255,255,255,.12);border-radius:10px;padding:10px 12px;color:#fff;font-size:14px;outline:none;font-family:inherit}
.vzp-i:focus{border-color:rgba(212,168,74,.55)}
.vzp-nav{display:flex;gap:9px;margin-top:16px}
.vzp-btn{flex:1;padding:12px;border-radius:11px;border:none;cursor:pointer;font-family:inherit;font-size:13.5px;font-weight:800;background:linear-gradient(135deg,#d4a84a,#ecc060);color:#191000}
.vzp-btn.ghost{background:transparent;border:1.5px solid rgba(255,255,255,.18);color:#c8c8e8}
#vzq-card .bd{font-size:13.5px;line-height:1.65;white-space:pre-wrap;color:#e6e6f6;margin-top:10px}
</style>
<script id="ch-vize-pruef-js">
/* CH_VIZE_PRUF — Hazirla oncesi kisisel form + belge takibi + Prüfung */
try{(function(){
  var LSK='ch_vz_profil';
  var CAP={url:null,country:null};
  var PREP={};
  function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
  function getProf(){ try{ var p=JSON.parse(localStorage.getItem(LSK)||'null'); return (p&&p.ad)?p:null; }catch(e){ return null; } }
  function saveProf(p){ try{ localStorage.setItem(LSK,JSON.stringify(p)); }catch(e){} }
  var FLD=[['ad','Ad Soyad'],['dogum','Doğum tarihi (GG.AA.YYYY)'],['pasaport','Pasaport numarası'],['meslek','Meslek / Unvan'],['isveren','İşveren veya işyeri (kendi işinizse firma adı)'],['adres','Adres (şehir, ülke)']];
  function showForm(cb){
    var ov=document.getElementById('vzp-ov');
    if(!ov){ ov=document.createElement('div'); ov.id='vzp-ov'; document.body.appendChild(ov); }
    var p=null; try{ p=JSON.parse(localStorage.getItem(LSK)||'null')||{}; }catch(e){ p={}; }
    var h='<div id="vzp-card"><div class="vzp-t">🪪 Kişisel Bilgiler</div><div class="vzp-s">Belgeleriniz bu bilgilerle kişiselleştirilir — bir kez girin, kaydedilir. (İsterseniz atlayabilirsiniz; belgelerde boş alan kalır.)</div>';
    for(var i=0;i<FLD.length;i++){ h+='<label class="vzp-l">'+esc(FLD[i][1])+'</label><input class="vzp-i" id="vzp-'+FLD[i][0]+'" value="'+esc(p[FLD[i][0]]||'')+'">'; }
    h+='<div class="vzp-nav"><button class="vzp-btn ghost" id="vzp-skip">Atla</button><button class="vzp-btn" id="vzp-save">Kaydet &amp; Hazırla</button></div></div>';
    ov.innerHTML=h; ov.classList.add('on');
    document.getElementById('vzp-skip').onclick=function(){ ov.classList.remove('on'); try{ sessionStorage.setItem('ch_vzp_skip','1'); }catch(e){} cb(); };
    document.getElementById('vzp-save').onclick=function(){
      var np={}; for(var i=0;i<FLD.length;i++){ var el=document.getElementById('vzp-'+FLD[i][0]); np[FLD[i][0]]=el?String(el.value||'').trim():''; }
      saveProf(np); ov.classList.remove('on'); cb();
    };
  }
  function skipped(){ try{ return sessionStorage.getItem('ch_vzp_skip')==='1'; }catch(e){ return false; } }
  function profText(){
    var p=getProf(); if(!p) return '';
    var L=[];
    if(p.ad)L.push('Ad Soyad: '+p.ad); if(p.dogum)L.push('Doğum tarihi: '+p.dogum);
    if(p.pasaport)L.push('Pasaport No: '+p.pasaport); if(p.meslek)L.push('Meslek/Unvan: '+p.meslek);
    if(p.isveren)L.push('İşveren/İşyeri: '+p.isveren); if(p.adres)L.push('Adres: '+p.adres);
    if(!L.length) return '';
    return '\n\nBaşvuru sahibinin kişisel bilgileri (belgede doğru yerlere işle, köşeli parantezli yer tutucu BIRAKMA):\n- '+L.join('\n- ');
  }
  /* fetch yakalayici: yalniz __vzGen cagrisi sirasinda aktif */
  var inVz=false;
  (function(){
    var _f=window.fetch;
    window.fetch=function(u,opt){
      try{
        if(inVz && typeof u==='string' && u.indexOf('action=aichat')!==-1 && opt && opt.body){
          var b=JSON.parse(opt.body);
          CAP.url=u; if(b.country) CAP.country=b.country;
          var pt=profText();
          if(pt && b.message){ b.message=b.message+pt; opt.body=JSON.stringify(b); }
        }
      }catch(e){}
      return _f.apply(this,arguments);
    };
  })();
  /* __vzGen sarmala: form kapisi + belge takibi */
  function wrap(){
    try{
      if(typeof window.__vzGen!=='function' || window.__vzGen.__pruf) return false;
      var _o=window.__vzGen;
      var w=function(gen,btn){
        var self=this,args=arguments;
        var lbl=gen;
        try{
          var host=(btn&&btn.closest)?btn.closest('.vz-gen,.vz-cl-it'):null;
          if(host){ var nEl=host.querySelector('.n,.tx'); if(nEl&&nEl.textContent) lbl=nEl.textContent.replace(/\s+/g,' ').trim(); }
        }catch(e){}
        var go=function(){
          PREP[gen]=lbl;
          inVz=true;
          try{ _o.apply(self,args); } finally{ setTimeout(function(){ inVz=false; },0); }
        };
        if(!getProf() && !skipped()){ showForm(go); } else go();
      };
      w.__pruf=1; window.__vzGen=w;
      return true;
    }catch(e){ return false; }
  }
  /* Prüfung paneli */
  function showPruef(txt){
    var ov=document.getElementById('vzq-ov');
    if(!ov){ ov=document.createElement('div'); ov.id='vzq-ov'; document.body.appendChild(ov); }
    ov.innerHTML='<div id="vzq-card"><div class="vzp-t">🧪 Prüfung — Son Kontrol</div><div class="bd">'+esc(txt)+'</div>'
      +'<div class="vzp-nav"><button class="vzp-btn" id="vzq-ok">Tamam</button></div></div>';
    ov.classList.add('on');
    document.getElementById('vzq-ok').onclick=function(){ ov.classList.remove('on'); };
  }
  function checklistLines(){
    var out=[];
    try{
      document.querySelectorAll('#vz-ov .vz-cl-grp').forEach(function(g){
        var gt=g.querySelector('.gt'); var gn=(gt&&gt.textContent)?gt.textContent.trim():'';
        g.querySelectorAll('.vz-cl-it').forEach(function(it){
          var tx=it.querySelector('.tx'); var bd=it.querySelector('.vz-badge');
          if(tx) out.push((gn?gn+' — ':'')+tx.textContent.replace(/\s+/g,' ').trim()+(bd?' ['+bd.textContent.trim()+']':''));
        });
      });
    }catch(e){}
    return out;
  }
  function runPruef(btn){
    var docs=[]; for(var k in PREP) docs.push(PREP[k]);
    var cl=checklistLines();
    var msg='Bir vize başvurusu için SON KONTROL (Prüfung) yap. Türkçe, kısa ve madde madde yanıt ver.\n\n'
      +'ChatHelp ile hazırlanan belgeler:\n'+(docs.length?('- '+docs.join('\n- ')):'(henüz hiç belge hazırlanmadı)')
      +'\n\nBaşvurunun evrak listesi:\n'+(cl.length?('- '+cl.join('\n- ')):'(liste okunamadı)')
      +'\n\nGörev:\n1) Hazırlanması gerekip henüz hazırlanmamış ChatHelp belgelerini listele.\n2) Resmi kaynaktan alınacak evraklar için kısa hatırlatma ver.\n3) Bu başvuru türünde sık yapılan en önemli 3 hatayı uyar.\n4) Sonunda "Hazırlık durumu: %.." satırı ver. Toplam 15 satırı geçme.';
    var url=CAP.url||'api.php?action=aichat';
    var old=btn.textContent; btn.textContent='⏳ Kontrol ediliyor…'; btn.disabled=true;
    fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({message:msg,history:[],provider:'claude',lang:'tr',country:CAP.country||'DE'})})
     .then(function(r){ return r.json(); })
     .then(function(j){
        btn.textContent=old; btn.disabled=false;
        showPruef((j&&j.reply)?j.reply:'Kontrol alınamadı, lütfen tekrar deneyin.');
     })
     .catch(function(){ btn.textContent=old; btn.disabled=false; showPruef('Bağlantı hatası — internetinizi kontrol edip tekrar deneyin.'); });
  }
  function injectBtn(){
    try{
      var ov=document.getElementById('vz-ov'); if(!ov) return;
      var nav=ov.querySelector('.vz-nav'); if(!nav) return;
      if(nav.querySelector('.vz-pruef')) return;
      if(!ov.querySelector('.vz-gen')) return; /* yalniz belge-listesi adiminda */
      var b=document.createElement('button');
      b.className='vz-btn vz-pruef'; b.textContent='🧪 Prüfung';
      b.style.background='linear-gradient(135deg,#2c7a4b,#42df94)'; b.style.color='#04160c';
      b.onclick=function(){ runPruef(b); };
      nav.insertBefore(b, nav.lastElementChild);
    }catch(e){}
  }
  var n=0;(function loop(){ wrap(); injectBtn(); if(n++<6000) setTimeout(loop,700); })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'vp').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-vizepruf-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_VIZE_PRUF')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_VIZE_PRUF diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Vize Asistani yeni duzen:\n";
echo "   🪪 Ilk 'Hazirla'da kisa kisisel-bilgi formu (bir kez, kaydedilir) —\n";
echo "      belgeler artik ad/pasaport/meslek bilgileriyle kisisellesmis cikar.\n";
echo "   🧪 Belge listesi adiminda 'Prüfung' butonu — hazirlanan belgeler +\n";
echo "      evrak listesi denetlenir, eksik/uyari/hazirlik yuzdesi raporlanir.\n";
