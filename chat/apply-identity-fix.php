<?php
/**
 * ChatHelp — apply-identity-fix (CH_IDENTITY_FIX) — Absender-Profile kimlik + UI.
 *  (1) Aktif profil ADI -> gonderen f1/f2 (d.f1/f2 bos veya hesap-adiysa) + P'ye
 *      uygula. "Acerasoft LLC" yerine profil adi (Melissa/Serdar) kullanilir;
 *      belgeler ve Profil karti bu adi gosterir.
 *  (2) Willkommen "Daten eingeben" prompt'u: GIRIS YAPMIS kullanicilarda gizle
 *      (sadece yeni/girissiz kullanicilara).
 *  (3) "💎 Pläne & Preise" butonu -> window.chOpenPlans() (tek-sayfa plan).
 *  (4) Kategorien "anzeigen/ausblenden" toggle'i CANLI grid uzerinde calissin.
 *  Saf EKLEMELI JS. Gomulu JS node --check ile dogrulandi.
 * KULLANIM: pull2.php?key=...&files=apply-identity-fix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-identity-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_IDENTITY_FIX')!==false) exit("Zaten ekli (CH_IDENTITY_FIX).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-identity-fix">
/* CH_IDENTITY_FIX — profil kimlik senk + Willkommen gate + Pläne + cat-toggle */
try{(function(){
  var LSK="ch_profiles", LSA="ch_prof_active", F=["f1","f2","f3","f4","f5","f6","f7"];
  function accName(){ try{ return (JSON.parse(localStorage.getItem('ch_user')||'{}').name||'').trim(); }catch(e){ return ''; } }
  function loggedIn(){ try{ return !!localStorage.getItem('ch_token'); }catch(e){ return false; } }
  function loadP(){ try{ return JSON.parse(localStorage.getItem(LSK)||'null'); }catch(e){ return null; } }
  function saveP(a){ try{ localStorage.setItem(LSK,JSON.stringify(a)); }catch(e){} }
  function actIdx(a){ var n=parseInt(localStorage.getItem(LSA)||'0',10); if(isNaN(n)||n<0||n>=a.length) n=0; return n; }
  function realName(nm){ return nm && !/^(Profil|Profile|Free|Kein|Default|Konto)\s*\d*$/i.test(String(nm).trim()); }
  function isDefaultSender(f1,f2){
    var full=((f1||'')+' '+(f2||'')).trim(); var acc=accName();
    return !((''+(f1||'')).trim()) || (!!acc && full===acc);
  }
  /* ── (1) profil adi -> gonderen f1/f2 + P ── */
  function syncIdentity(){
    try{
      var a=loadP(); if(!a||!a.length) return;
      var i=actIdx(a); var p=a[i]; if(!p) return; p.d=p.d||{};
      if(realName(p.name) && isDefaultSender(p.d.f1,p.d.f2)){
        var parts=String(p.name).trim().split(/\s+/);
        p.d.f1=parts[0]||''; p.d.f2=parts.slice(1).join(' ');
        saveP(a);
      }
      if(window.P){
        if(p.d.f1){ P.f1=p.d.f1; } if(p.d.f2!=null){ P.f2=p.d.f2; }
        for(var k=2;k<F.length;k++){ var f=F[k]; if(p.d[f]!=null && p.d[f]!=='') P[f]=p.d[f]; }
        try{ var v3={}; F.forEach(function(x){ v3[x]=P[x]||''; }); localStorage.setItem('ch_prof_v3',JSON.stringify(v3)); }catch(e){}
      }
      /* Konto Profil karti adi */
      try{ var kn=document.getElementById('k-name'); if(kn && window.P){ var full=((P.f1||'')+' '+(P.f2||'')).trim(); if(full) kn.textContent=full; } }catch(e){}
    }catch(e){}
  }
  /* ── (2) Willkommen prompt: giris yapmislarda gizle ── */
  var WRE=/Bitte geben Sie zuerst Ihre Daten|auf genau diesen Namen erstellt|önce.*verilerinizi|bu isme (hazırlanır|göre)|enter your details first|in exactly this name|Saisissez d'abord vos données/i;
  function killWelcome(){
    try{
      if(!loggedIn()) return;
      document.querySelectorAll('.msg,.bbl,.ch-i18n,[class*="wlc"]').forEach(function(el){
        try{ if(WRE.test(el.textContent||'')){ var m=(el.closest?el.closest('.msg'):null)||el; m.style.display='none'; try{m.remove();}catch(_){} } }catch(e){}
      });
    }catch(e){}
  }
  /* ── (3) 💎 Pläne & Preise -> chOpenPlans ── */
  function wirePlan(){
    try{
      document.querySelectorAll('div,button,a').forEach(function(el){
        if(el.__chplan || (el.children&&el.children.length>4)) return;
        var t=(el.textContent||'');
        if(t.indexOf('💎')>=0 && /Pl[aä]n|Preise|Fiyat|Pricing|Prezzi|Precios|Tarif/i.test(t) && t.length<44){
          el.__chplan=1; el.style.cursor='pointer';
          el.addEventListener('click',function(ev){ try{ if(typeof chOpenPlans==='function'){ ev.stopImmediatePropagation(); ev.preventDefault(); chOpenPlans(); } }catch(e){} }, true);
        }
      });
    }catch(e){}
  }
  /* ── (4) Kategorien toggle -> CANLI grid ── */
  function grid(){ return document.querySelector('.wcat-grid')||document.querySelector('.cgrid'); }
  function wireToggle(){
    try{
      document.querySelectorAll('.ch-cat-tg button, button, span, div').forEach(function(el){
        if(el.__chtg || (el.children&&el.children.length)) return;
        var t=(el.textContent||'').trim().toLowerCase();
        if(t.length<=14 && /(anzeigen|ausblenden|einblenden)\s*[▲▼]?/.test(t)){
          el.__chtg=1; el.style.cursor='pointer';
          var g0=grid(); if(g0){ el.textContent=(g0.style.display==='none')?'anzeigen ▼':'ausblenden ▲'; }
          el.addEventListener('click',function(ev){
            try{
              ev.stopImmediatePropagation();
              var g=grid(); if(!g) return;
              var hidden=(g.style.display==='none');
              g.style.display=hidden?'':'none';
              document.querySelectorAll('.ch-cat-tg button').forEach(function(b){ b.textContent=hidden?'ausblenden ▲':'anzeigen ▼'; });
              el.textContent=hidden?'ausblenden ▲':'anzeigen ▼';
            }catch(e){}
          }, true);
        }
      });
    }catch(e){}
  }
  function tick(){ syncIdentity(); killWelcome(); wirePlan(); wireToggle(); }
  var n=0;(function loop(){ tick(); if(n++<300) setTimeout(loop,700); })();
  try{ if(window.MutationObserver){ new MutationObserver(function(){ killWelcome(); wirePlan(); }).observe(document.body,{childList:true,subtree:true}); } }catch(e){}
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'id').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-identityfix-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_IDENTITY_FIX')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_IDENTITY_FIX diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Profil adi->gonderen senk, Willkommen(giriste) gizli, Pläne->chOpenPlans, cat-toggle canli.\n";
