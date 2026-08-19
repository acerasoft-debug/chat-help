<?php
/**
 * ChatHelp — apply-identity-bind (CH_IDENTITY_BIND) — KRITIK #89 cozumu.
 *  SORUN: profil (ch_prof_v3 = ad/adres) hesaba bagli degil; ayni cihazda owner
 *  (Acerasoft) girdiyse yeni kullanici onun profilini/planini miras aliyor.
 *  COZUM (EKLEMELI, sadece client): giris yapan user id, ch_bound_uid'e baglanir.
 *   - user id DEGISIRSE -> onceki kullanicinin yerel profili/kredisi TEMIZLENIR.
 *   - ilk baglamada stored profil adi ile hesap adi uyusmuyorsa TEMIZLENIR.
 *  Boylece hicbir kullanici baskasinin kimligini/planini gormez. Backend zaten temiz.
 * KULLANIM: pull2.php?key=...&files=apply-identity-bind.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-identity-bind BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_IDENTITY_BIND')!==false) exit("Zaten ekli (CH_IDENTITY_BIND).\n");

$block = <<<'HTMLBLOCK'
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
      ['ch_prof_v3','ch_prof_active','ch_credits','CH_FREE_AVAIL','ch_lastdoc'].forEach(function(k){ try{ localStorage.removeItem(k); }catch(e){} });
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
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'ib').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-identbind-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_IDENTITY_BIND')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_IDENTITY_BIND diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Profil/plan artik hesaba bagli; kullanici degisince onceki profil temizlenir.\n";
echo "   #89: yeni kullanici Acerasoft/Elite kimligini artik miras almaz.\n";
