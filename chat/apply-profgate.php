<?php
/**
 * ChatHelp — apply-profgate — 'Bitte vervollständigen Sie zuerst Ihr Profil'
 *  uyarisi KAYITLI ADRESI OLAN kullanicilarda da cikiyordu (2 duzeltme):
 *  KOK NEDEN: CH_PROFILE_REQ kapisi SADECE bellek-ici P objesine bakiyor.
 *  P yukleme/senkron gecikmesinde anlik bos olunca, adres DISKTE kayitli olsa
 *  bile uretim engelleniyordu.
 *  [1] CH_PROFGATE2: kapi artik 3 kaynaga bakar — P -> ch_prof_v3 -> aktif
 *      coklu-profil slotu. Kayit bulursa P'yi KENDISI onarir ve uretime izin
 *      verir. Sadece GERCEKTEN hic kayit yoksa engeller.
 *  [2] CH_PROFGATE_FORM: engelleyince Konto'ya atmak yerine DOGRUDAN profil
 *      formunu acar — kullanici doldurunca dilekce akisi kaldigi yerden
 *      otomatik devam eder (adres bir kez girilir, kalici kaydedilir).
 * KULLANIM: pull2.php?key=...&files=apply-profgate.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-profgate BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");

$ok=0;

/* ── [1] CH_PROFGATE2 ── */
if(strpos($src,'CH_PROFGATE2')===false){
  $o1=<<<'ANC'
  function profOk(){
    try{
      var p=(typeof P!=="undefined"&&P)?P:{};
      var name=((p.f1||"")+"").trim() && ((p.f2||"")+"").trim();
      var addr=((p.f3||"")+"").trim() || ((p.f4||"")+"").trim();
      return !!(name && addr);
    }catch(e){ return true; } /* P okunamazsa engelleme */
  }
ANC;
  $n1=<<<'ANC'
  function profOk(){/*CH_PROFGATE2 — P bos olsa bile diskteki kayda bak + P'yi onar*/
    function full(p){ try{ if(!p) return false; var nm=((p.f1||"")+"").trim() && ((p.f2||"")+"").trim(); var ad=((p.f3||"")+"").trim() || ((p.f4||"")+"").trim(); return !!(nm && ad); }catch(e){ return false; } }
    function heal(d){ try{ if(typeof P!=="undefined"&&P){ ['f1','f2','f3','f4','f5','f6','f7'].forEach(function(k){ if(d[k] && !((P[k]||"")+"").trim()) P[k]=d[k]; }); } else if(typeof window!=="undefined"){ window.P=d; } }catch(e){} }
    try{
      var p=(typeof P!=="undefined"&&P)?P:{};
      if(full(p)) return true;
      /* kaynak 2: ch_prof_v3 */
      try{ var v3=JSON.parse(localStorage.getItem('ch_prof_v3')||'null'); if(full(v3)){ heal(v3); return true; } }catch(e2){}
      /* kaynak 3: aktif coklu-profil slotu */
      try{ var pa=JSON.parse(localStorage.getItem('ch_profiles')||'null'); if(Array.isArray(pa)&&pa.length){ var ai=parseInt(localStorage.getItem('ch_prof_active')||'0',10); if(isNaN(ai)||ai<0||ai>=pa.length)ai=0; var d=pa[ai]&&pa[ai].d; if(full(d)){ heal(d); try{ localStorage.setItem('ch_prof_v3',JSON.stringify(d)); }catch(e4){} return true; } } }catch(e3){}
      return false;
    }catch(e){ return true; } /* okunamazsa engelleme */
  }
ANC;
  $c=substr_count($src,$o1);
  if($c===1){ $src=str_replace($o1,$n1,$src); echo "  ✓ [1] CH_PROFGATE2 — kapi 3 kaynakli, kayitli adres varsa engellemez + P'yi onarir\n"; $ok++; }
  else echo "  ✗ [1] anchor ($c, 1 beklenir) — atlandi\n";
} else { echo "  = [1] zaten ekli\n"; $ok++; }

/* ── [2] CH_PROFGATE_FORM ── */
if(strpos($src,'CH_PROFGATE_FORM')===false){
  $o2=<<<'ANC'
  function block(){
    try{ if(typeof addMsg==="function") addMsg("ai", MSG[UIL()]||MSG.en); }catch(e){}
    try{ if(typeof gP==="function") gP("konto"); }catch(e){}
  }
ANC;
  $n2=<<<'ANC'
  function block(){/*CH_PROFGATE_FORM — Konto yerine dogrudan profil formu, akis devam eder*/
    try{ if(typeof addMsg==="function") addMsg("ai", MSG[UIL()]||MSG.en); }catch(e){}
    try{ if(typeof showProfileForm==="function"){ try{ if(typeof gP==="function") gP("chat"); }catch(eg){} showProfileForm((typeof CD!=="undefined"&&CD)||null); return; } }catch(e){}
    try{ if(typeof gP==="function") gP("konto"); }catch(e){}
  }
ANC;
  $c=substr_count($src,$o2);
  if($c===1){ $src=str_replace($o2,$n2,$src); echo "  ✓ [2] CH_PROFGATE_FORM — engelde dogrudan profil formu acilir, doldurunca akis surer\n"; $ok++; }
  else echo "  ✗ [2] anchor ($c, 1 beklenir) — atlandi\n";
} else { echo "  = [2] zaten ekli\n"; $ok++; }

if($ok===0){ echo "\nHICBIR bolum uygulanamadi — HICBIR sey degistirilmedi.\n"; exit; }

$tmp=tempnam(sys_get_temp_dir(),'pg').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-profgate-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
echo "\n  Sonuc: $ok/2 bolum tamam (".strlen($chk)." B)\n";
echo "  Test: adresi kayitli kullanici -> belge uret -> uyari CIKMAMALI, direkt sorulara\n";
echo "  gecmeli. Profili gercekten bos kullanici -> form acilir -> doldur -> akis DEVAM eder.\n";
