<?php
/**
 * ChatHelp — apply-konto-race-fix (CH_KONTO_RACEFIX) — ACIL: "kullanici
 *  isimleri kaydedilmiyor". Kok neden BULUNDU: backend UCTAN UCA dogrulandi
 *  (gercek HTTP self-test: kaydet->cek round-trip TAM CALISIYOR). Sorun
 *  CH_KONTO_FE'nin (apply-konto-frontend.php) YARIS DURUMU (race condition):
 *   - Her sayfa acilisinda (yeni giris olmasa bile) sunucudan profil cekilip
 *     YEREL (henuz gonderilmemis) degisikliklerin uzerine yazilabiliyordu.
 *   - Kullanici bilgi girip hizlica sekmeyi kapatirsa/gecerse, 1.2sn'lik
 *     gonderim gecikmesi dolmadan veri hic sunucuya ulasmiyordu.
 *  Cozum (3 kucuk, count-guard'li duzeltme — CH_KONTO_FE'nin KENDI orijinal
 *  metnine dayanir, degistirmez sadece iyilestirir):
 *   [1] pullProfileFromServer(token,force) — force=false ise YEREL veri
 *       DOLUYSA sunucu verisiyle EZILMEZ (sadece push ile senkronlanir);
 *       sadece yerel BOS/varsayilansa doldurulur. force=true (gercek yeni
 *       token/giris) hep senkronlar.
 *   [2] Sayfa-acilisi cagrisi force=false ile yapilir; token-degisim (giris)
 *       cagrisi force=true ile yapilir.
 *   [3] Gonderim gecikmesi 1200ms -> 500ms + sekme gizlenince/kapaninca
 *       ANINDA gonderim (visibilitychange + pagehide).
 * KULLANIM: pull2.php?key=...&files=apply-konto-race-fix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-konto-race-fix BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_KONTO_RACEFIX')!==false) exit("Zaten ekli (CH_KONTO_RACEFIX).\n");
if (strpos($src,'CH_KONTO_FE')===false) exit("HATA: CH_KONTO_FE yok — once apply-konto-frontend.php gerekli.\n");

$fail=[];

/* ── [1] pullProfileFromServer: force parametresi + "yerel doluysa ezme" korumasi ── */
$o1 = <<<'ANC'
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
ANC;
$n1 = <<<'ANC'
  function isEmptyProfiles(arr){
    try{
      if(!arr||!arr.length) return true;
      if(arr.length>1) return false;
      var d=arr[0]&&arr[0].d; if(!d) return true;
      var F=['f1','f2','f3','f4','f5','f6'];
      for(var i=0;i<F.length;i++){ if(d[F[i]]) return false; }
      return true;
    }catch(e){ return true; }
  }
  async function pullProfileFromServer(token,force){
    try{
      if(!token||token==='ch_credit_guest') return;
      var r=await fetch(AUTH+'?action=get_profile',{headers:{'Authorization':'Bearer '+token}});
      var d=await r.json();
      if(d&&d.success&&d.profiles&&d.profiles.length){
        var cur=null; try{ cur=JSON.parse(localStorage.getItem('ch_profiles')||'null'); }catch(e){}
        var same = cur && JSON.stringify(cur)===JSON.stringify(d.profiles) && parseInt(localStorage.getItem('ch_prof_active')||'0',10)===(d.active||0);
        if(same) return;
        if(!force && !isEmptyProfiles(cur)){
          /* CH_KONTO_RACEFIX: yerel veri DOLU ve bu zorunlu bir giris degil -> sessizce EZME, sadece push ile senkronla */
          pushProfileToServer();
          return;
        }
        nativeSet('ch_profiles', JSON.stringify(d.profiles));
        nativeSet('ch_prof_active', String(d.active||0));
        setTimeout(function(){ try{ location.reload(); }catch(e){} }, 300);
      } else {
        pushProfileToServer();
      }
    }catch(e){}
  }
ANC;
$c=substr_count($src,$o1);
if($c!==1){ echo "  ✗ [1] pullProfileFromServer anchor ($c)\n"; $fail[]='1'; }
else { $src=str_replace($o1,$n1,$src); echo "  ✓ [1] pullProfileFromServer: 'yerel doluysa ezme' korumasi eklendi\n"; }

/* ── [2a] token-degisim (gercek giris) cagrisi: force=true ── */
$o2 = <<<'ANC'
        if(k==='ch_token'&&v&&v!==_lastTok&&v!=='ch_credit_guest'){
          _lastTok=v; pullProfileFromServer(v);
        }
        if(k==='ch_profiles'||k==='ch_prof_active'){
          clearTimeout(_saveT); _saveT=setTimeout(pushProfileToServer,1200);
        }
ANC;
$n2 = <<<'ANC'
        if(k==='ch_token'&&v&&v!==_lastTok&&v!=='ch_credit_guest'){
          _lastTok=v; pullProfileFromServer(v,true);
        }
        if(k==='ch_profiles'||k==='ch_prof_active'){
          clearTimeout(_saveT); _saveT=setTimeout(pushProfileToServer,500);
        }
ANC;
$c=substr_count($src,$o2);
if($c!==1){ echo "  ✗ [2] token-degisim/debounce anchor ($c)\n"; $fail[]='2'; }
else { $src=str_replace($o2,$n2,$src); echo "  ✓ [2] gercek giris = force=true; gonderim gecikmesi 1200ms -> 500ms\n"; }

/* ── [3] sayfa-acilisi cagrisi: force=false + sekme gizlenince/kapaninca aninda gonder ── */
$o3 = <<<'ANC'
  /* sayfa acilisinda mevcut token varsa bir kez senkronla (baska cihazdan giris) */
  try{
    var _tk0=localStorage.getItem('ch_token');
    if(_tk0&&_tk0!=='ch_credit_guest'){ setTimeout(function(){ pullProfileFromServer(_tk0); }, 800); }
  }catch(e){}
ANC;
$n3 = <<<'ANC'
  /* sayfa acilisinda mevcut token varsa bir kez senkronla (baska cihazdan giris) — force=false: yerel dolu veri ASLA sessizce ezilmez */
  try{
    var _tk0=localStorage.getItem('ch_token');
    if(_tk0&&_tk0!=='ch_credit_guest'){ setTimeout(function(){ pullProfileFromServer(_tk0,false); }, 800); }
  }catch(e){}
  /* CH_KONTO_RACEFIX: sekme gizlenince/sayfadan ayrilinca bekleyen kaydi ANINDA gonder (veri kaybini onler) */
  try{
    function chFlushProfilePush(){ try{ clearTimeout(_saveT); pushProfileToServer(); }catch(e){} }
    document.addEventListener('visibilitychange',function(){ if(document.visibilityState==='hidden') chFlushProfilePush(); });
    window.addEventListener('pagehide', chFlushProfilePush);
  }catch(e){}
ANC;
$c=substr_count($src,$o3);
if($c!==1){ echo "  ✗ [3] sayfa-acilisi cagrisi anchor ($c)\n"; $fail[]='3'; }
else { $src=str_replace($o3,$n3,$src); echo "  ✓ [3] sayfa-acilisi = force=false; sekme-gizlenince/kapaninca aninda gonderim eklendi\n"; }

if ($fail) { echo "\nHATA: eslesmeyen adimlar: ".implode(', ',$fail)." — index DEGISTIRILMEDI.\n"; exit; }

$src .= "\n<!-- CH_KONTO_RACEFIX -->\n";

$tmp = tempnam(sys_get_temp_dir(),'rf').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-racefix-'.date('Ymd-His'), (string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_KONTO_RACEFIX')===false || strpos($chk,'isEmptyProfiles')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_KONTO_RACEFIX + isEmptyProfiles diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Yaris durumu duzeltildi: yerel dolu profil veri asla sessizce ezilmez; gonderim hem hizlandi hem sekme-kapanista garanti altina alindi.\n";
