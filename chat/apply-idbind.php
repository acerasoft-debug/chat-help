<?php
/**
 * ChatHelp — apply-idbind (CH_IDBIND2) — kullanici-bazli KALICI adres + izolasyon.
 *  Eski CH_IDENTITY_BIND kullanici degisince profili SILIYORDU (adres kaybi + "surekli
 *  adres soruyor"). Yeni: her kullanicinin adresi ch_acct_byuid[uid] slotunda kalici;
 *  degisimde cikanin adresi arsivlenir, girenin kendi adresi geri yuklenir. Silme YOK.
 * KULLANIM: pull2.php?key=...&files=apply-idbind.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-idbind BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_IDBIND2')!==false) exit("Zaten ekli (CH_IDBIND2).\n");

$o=<<<'OLDB'
<script id="ch-identity-bind">
/* CH_IDENTITY_BIND — profil/plan cakismasi: kullanici degisince yerel profili temizle (#89) */
try{(function(){
  function j(k){ try{ return JSON.parse(localStorage.getItem(k)||'null'); }catch(e){ return null; } }
  function curUid(){
    try{ if(!localStorage.getItem('ch_token')) return null; var u=j('ch_user'); return (u&&u.id!=null)?String(u.id):null; }catch(e){ return null; }
  }
  function firstName(s){ try{ return String(s||'').trim().split(/\s+/)[0].toLowerCase(); }catch(e){ return ''; } }
  function userFirst(){ var u=j('ch_user'); return u?firstName(u.name):''; }
  function profFirst(){ var p=j('ch_prof_v3'); return p?firstName(p.f1):''; }
  function purge(){
    try{
      ['ch_prof_v3','ch_prof_active','ch_credits','CH_FREE_AVAIL','ch_lastdoc','ch_profiles'].forEach(function(k){ try{ localStorage.removeItem(k); }catch(e){} });/*CH_NOCROSS*/
      try{ if(window.P){ P.f3=''; P.f4=''; P.f5=''; P.f6=''; } }catch(e){}
    }catch(e){}
  }
  function tick(){
    try{
      var uid=curUid(); if(uid===null) return; /* cikis yapmis: dokunma */
      var bound=null; try{ bound=localStorage.getItem('ch_bound_uid'); }catch(e){}
      if(bound!==null && bound!==uid){ purge(); try{ localStorage.setItem('ch_bound_uid',uid); }catch(e){} return; }
      if(bound===null){
        var pf=profFirst(), uf=userFirst();
        if(pf && uf && pf!==uf) purge();   /* baska kullanicinin stale profili */
        try{ localStorage.setItem('ch_bound_uid',uid); }catch(e){}
      }
    }catch(e){}
  }
  var n=0; (function loop(){ tick(); if(n++<900) setTimeout(loop,1000); })();
})();}catch(e){}
</script>
OLDB;
$n=<<<'NEWB'
<script id="ch-identity-bind">
/* CH_IDBIND2 — kullanici-bazli KALICI adres + izolasyon (SILME yok; arsivle+geri yukle) */
try{(function(){
  var ACC='ch_acct_byuid';
  function j(k){ try{ return JSON.parse(localStorage.getItem(k)||'null'); }catch(e){ return null; } }
  function jset(k,v){ try{ localStorage.setItem(k,JSON.stringify(v)); }catch(e){} }
  function curUid(){ try{ if(!localStorage.getItem('ch_token')) return null; var u=j('ch_user'); return (u&&u.id!=null)?String(u.id):null; }catch(e){ return null; } }
  function fn(s){ try{ return String(s||'').trim().split(/\s+/)[0].toLowerCase(); }catch(e){ return ''; } }
  function v3(){ return j('ch_prof_v3'); }
  function hasAddr(d){ return !!(d&&(d.f1||d.f3||d.f4||d.f7)); }
  function store(){ return j(ACC)||{}; }
  function apP(d){ try{ if(window.P){ ['f1','f2','f3','f4','f5','f6','f7'].forEach(function(k){ window.P[k]=(d&&d[k])||''; }); } }catch(e){} }
  function archive(uid){ if(!uid) return; var all=store(); var cur=v3(); if(cur||all[uid]){ all[uid]={ v3:cur, profs:j('ch_profiles'), active:(localStorage.getItem('ch_prof_active')||'0') }; jset(ACC,all); } }
  function clearVolatile(){ ['ch_credits','CH_FREE_AVAIL','ch_lastdoc'].forEach(function(k){ try{ localStorage.removeItem(k); }catch(e){} }); }
  function clearProfile(){ ['ch_prof_v3','ch_profiles','ch_prof_active'].forEach(function(k){ try{ localStorage.removeItem(k); }catch(e){} }); try{ if(window.P){ P.f1=P.f2=P.f3=P.f4=P.f5=P.f6=P.f7=''; } }catch(e){} }
  function restore(uid){
    var all=store(), slot=all[uid];
    if(slot && hasAddr(slot.v3)){
      jset('ch_prof_v3',slot.v3); apP(slot.v3);
      if(slot.profs) jset('ch_profiles',slot.profs); else { try{ localStorage.removeItem('ch_profiles'); }catch(e){} }
      try{ localStorage.setItem('ch_prof_active',String(slot.active||'0')); }catch(e){}
      return;
    }
    var cur=v3(); if(cur && hasAddr(cur)){ var pf=fn(cur.f1), uf=fn((j('ch_user')||{}).name); if(pf && uf && pf!==uf){ clearProfile(); } }
  }
  function tick(){
    try{
      var uid=curUid(); if(uid===null) return; /* cikis: dokunma, HICBIR sey silme */
      var bound=null; try{ bound=localStorage.getItem('ch_bound_uid'); }catch(e){}
      if(bound===uid){ archive(uid); return; } /* ayni kullanici: adresi kendi slotuna aynala (kalici) */
      if(bound && bound!==uid){ archive(bound); }  /* cikan kullanicinin adresi KORUNUR */
      clearVolatile();
      restore(uid);
      try{ localStorage.setItem('ch_bound_uid',uid); }catch(e){}
      archive(uid);
    }catch(e){}
  }
  var n=0; (function loop(){ tick(); if(n++<3600) setTimeout(loop,1000); })();
})();}catch(e){}
</script>
NEWB;

$c=substr_count($src,$o);
if($c!==1){ echo "  ✗ anchor ($c, 1 beklenir) — HICBIR sey degistirilmedi.\n"; exit; }
$src=str_replace($o,$n,$src);
echo "  ✓ CH_IDENTITY_BIND -> CH_IDBIND2 (kalici kullanici-bazli adres)\n";

$tmp=tempnam(sys_get_temp_dir(),'ib').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-idbind-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_IDBIND2')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_IDBIND2 diskte (".strlen($chk)." B)\n";
echo "  Artik: adres bir kez kaydedilince o kullanicida KALICI; farkli kullanicilar\n";
echo "  birbirinin adresini gormez/ezmez; her uid kendi slotunda saklanir.\n";
