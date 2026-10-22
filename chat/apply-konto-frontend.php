<?php
/**
 * ChatHelp — apply-konto-frontend (CH_KONTO_FE) — Konto ADIM 3 (frontend, kesin).
 *  Backend hazir (CH_KONTO_BE: save_profile/get_profile/resend_verification,
 *  CH_KONTO_SUB: e-posta ile Stripe musteri bulma). Bu script SADECE EKLEMELI —
 *  mevcut login/showAuthError fonksiyonlarina DOKUNMAZ, sarmalar:
 *   (A) localStorage.setItem narrow-wrap: 'ch_token' yeni deger alinca (login/
 *       kayit sonrasi VEYA sayfa acilisinda mevcut token ile) sunucudan
 *       get_profile cekilir; farkliysa localStorage guncellenir + sayfa
 *       yenilenir (CH_CC_LOCK'taki kanitlanmis teknik). 'ch_profiles' veya
 *       'ch_prof_active' degisince debounce'li save_profile gonderilir.
 *   (B) showAuthError sarmalanir: backend'in 'zuerst Ihre E-Mail-Adresse'
 *       (email dogrulanmadi) hatasi tespit edilince #auth-error altina
 *       'Bestatigungs-E-Mail erneut senden' butonu eklenir (resend_verification).
 *  MutationObserver YOK (freeze-safe), idempotent, sinirli retry. node ✓.
 * KULLANIM: pull2.php?key=...&files=apply-konto-frontend.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-konto-frontend BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_KONTO_FE')!==false) exit("Zaten ekli (CH_KONTO_FE).\n");
if (strpos($src,'action=save_profile')===false && strpos($src,"'save_profile'")===false) {
  echo "  ⚠ UYARI: save_profile backend markeri gorunmuyor (auth.php ayri dosya, kontrol edilemiyor) — devam.\n";
}

$block = <<<'HTMLBLOCK'
<style id="ch-konto-fe-css">
/* CH_KONTO_FE — dogrulama-tekrar-gonder butonu */
#ch-resend-btn{margin-top:8px;width:100%;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.16);
  color:#fff;border-radius:10px;padding:11px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:.15s}
#ch-resend-btn:hover{background:rgba(255,255,255,.14)}
#ch-resend-btn:disabled{opacity:.6;cursor:default}
</style>
<script id="ch-konto-fe-js">
/* CH_KONTO_FE — profil sunucu-senkronu + dogrulama-tekrar-gonder (EKLEMELI, sarmalayici) */
try{(function(){
  var AUTH='auth.php';

  /* ── (A) profil sunucu senkronu ── */
  function nativeSet(k,v){ try{ Object.getPrototypeOf(localStorage).setItem.call(localStorage,k,v); }catch(e){ try{ localStorage.setItem(k,v); }catch(_e){} } }

  async function pushProfileToServer(){
    try{
      var token=localStorage.getItem('ch_token');
      if(!token||token==='ch_credit_guest') return;
      var profiles=null; try{ profiles=JSON.parse(localStorage.getItem('ch_profiles')||'null'); }catch(e){}
      if(!profiles||!profiles.length) return;
      var active=parseInt(localStorage.getItem('ch_prof_active')||'0',10)||0;
      await fetch(AUTH+'?action=save_profile',{method:'POST',
        headers:{'Content-Type':'application/json','Authorization':'Bearer '+token},
        body:JSON.stringify({profiles:profiles,active:active})});
    }catch(e){}
  }

  async function pullProfileFromServer(token){
    try{
      if(!token||token==='ch_credit_guest') return;
      var r=await fetch(AUTH+'?action=get_profile',{headers:{'Authorization':'Bearer '+token}});
      var d=await r.json();
      if(d&&d.success&&d.profiles&&d.profiles.length){
        var cur=null; try{ cur=JSON.parse(localStorage.getItem('ch_profiles')||'null'); }catch(e){}
        var same = cur && JSON.stringify(cur)===JSON.stringify(d.profiles) && parseInt(localStorage.getItem('ch_prof_active')||'0',10)===(d.active||0);
        if(!same){
          nativeSet('ch_profiles', JSON.stringify(d.profiles));
          nativeSet('ch_prof_active', String(d.active||0));
          setTimeout(function(){ try{ location.reload(); }catch(e){} }, 300);
        }
      } else {
        pushProfileToServer();
      }
    }catch(e){}
  }

  try{
    var _lastTok=null, _saveT=null;
    var _origSet=localStorage.setItem.bind(localStorage);
    localStorage.setItem=function(k,v){
      _origSet(k,v);
      try{
        if(k==='ch_token'&&v&&v!==_lastTok&&v!=='ch_credit_guest'){
          _lastTok=v; pullProfileFromServer(v);
        }
        if(k==='ch_profiles'||k==='ch_prof_active'){
          clearTimeout(_saveT); _saveT=setTimeout(pushProfileToServer,1200);
        }
      }catch(e){}
    };
  }catch(e){}

  /* sayfa acilisinda mevcut token varsa bir kez senkronla (baska cihazdan giris) */
  try{
    var _tk0=localStorage.getItem('ch_token');
    if(_tk0&&_tk0!=='ch_credit_guest'){ setTimeout(function(){ pullProfileFromServer(_tk0); }, 800); }
  }catch(e){}

  /* ── (B) dogrulama-tekrar-gonder butonu (showAuthError sarmalayici) ── */
  function findAuthEmail(){
    try{
      var sels=['#auth-modal input[type="email"]','#auth-modal input[id*="email" i]',
                '#auth-modal input[name*="email" i]','#auth-modal input[placeholder*="mail" i]',
                'input[type="email"]'];
      for(var i=0;i<sels.length;i++){ var el=document.querySelector(sels[i]); if(el&&el.value) return el.value.trim(); }
    }catch(e){}
    return '';
  }
  function wrapShowAuthError(){
    try{
      if(typeof showAuthError!=='function'||showAuthError.__resend) return false;
      var _sae=showAuthError;
      var w=function(msg,isSuccess){
        var r=_sae.apply(this,arguments);
        try{
          var needsVerify=!isSuccess&&typeof msg==='string'&&/zuerst Ihre E-Mail|önce.*e-posta.*doğrula|verify.*email.*first|confirm.*email.*first/i.test(msg);
          var el=document.getElementById('auth-error');
          var old=document.getElementById('ch-resend-btn');
          if(needsVerify&&el){
            if(!old){
              var b=document.createElement('button');
              b.type='button'; b.id='ch-resend-btn';
              b.textContent='🔁 Bestätigungs-E-Mail erneut senden';
              b.addEventListener('click',async function(){
                b.disabled=true; var old2=b.textContent; b.textContent='⏳ …';
                try{
                  var email=findAuthEmail();
                  var rr=await fetch(AUTH+'?action=resend_verification',{method:'POST',
                    headers:{'Content-Type':'application/json'},
                    body:JSON.stringify({email:email||''})});
                  var dd=await rr.json();
                  showAuthError(dd.message||'E-Mail gesendet.',true);
                }catch(e){ showAuthError('Verbindungsfehler.',false); }
                try{ b.disabled=false; b.textContent=old2; }catch(e){}
              });
              if(el.parentNode) el.parentNode.insertBefore(b, el.nextSibling);
            }
          } else if(old&&old.parentNode){ old.parentNode.removeChild(old); }
        }catch(e){}
        return r;
      };
      w.__resend=1; showAuthError=w; if(typeof window!=='undefined') window.showAuthError=w;
      return true;
    }catch(e){ return false; }
  }
  var n=0;(function loop(){ if(!wrapShowAuthError()&&n++<200) setTimeout(loop,300); })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'kf').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-kontofe-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_KONTO_FE')===false || strpos($chk,'pullProfileFromServer')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_KONTO_FE diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Profil sunucu-senkronu + dogrulama-tekrar-gonder aktif.\n";
echo "   Test: 1) Kayit ol -> e-posta dogrula -> giris yap -> profil bilgisi gir -> ~1.2sn sonra sunucuya kaydolur.\n";
echo "         2) Baska cihaz/tarayicida ayni hesapla giris yap -> profil otomatik gelir (sayfa kendini yeniler).\n";
echo "         3) Dogrulanmamis hesapla giris dene -> hata altinda 'tekrar gonder' butonu cikar.\n";
