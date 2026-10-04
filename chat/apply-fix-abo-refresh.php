<?php
/**
 * ChatHelp — apply-fix-abo-refresh (CH_ABO_REFRESH) — "abonelik tanima" KESIN
 *  duzeltme (index.php frontend, kendine yeter — auth.php'ye DOKUNMAZ).
 *  CANLI DURUM (dump-auth-plan ile dogrulandi):
 *   • doMe (auth.php?action=me) plani DB'den TAZE okuyor (dogru) AMA normalize
 *     etmiyor; doLogin plani JWT'ye GOMUYOR -> sonradan odeme yapan kullanicida
 *     ch_user.plan/JWT BAYAT kaliyor; api.php'de sunucu-kota kapisi YOK -> tum
 *     tanima frontend'de (window.P.plan / localStorage ch_user.plan uzerinden,
 *     strict karsilastirmalarla). 'Pro'/'elite_monthly' gibi degerler eslesmiyor.
 *  COZUM: Acilista (ve gorunurluk donunce) ch_token varsa auth.php?action=me
 *   cagrilir -> TAZE plan alinir, client-side NORMALIZE edilir (basic/pro/
 *   elite/free) ve HEM localStorage ch_user.plan HEM window.P.plan'a yazilir.
 *   Boylece tum kapilar (foto-gate, indirme, kota) re-login GEREKMEDEN taze +
 *   kanonik plan gorur. Sadece EKLEMELI. node --check ✓ + harness ✓.
 * KULLANIM: pull2.php?key=...&files=apply-fix-abo-refresh.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fix-abo-refresh BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_ABO_REFRESH')!==false) exit("Zaten ekli (CH_ABO_REFRESH).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-abo-refresh-js">
/* CH_ABO_REFRESH — plani her acilista doMe'dan (taze) cek + normalize + yaz */
try{(function(){
  var API=function(){ return (typeof window.API!=='undefined'&&window.API)?window.API:'auth.php'; };
  function authUrl(){
    /* auth.php dogrudan; API() api.php ise auth.php'ye cevir */
    var a=API(); if(/auth\.php/.test(a)) return a; return 'auth.php';
  }
  function norm(p){
    p=(''+(p||'')).toLowerCase().trim();
    if(!p) return 'free';
    if(/elite|unbegrenzt|unlimited|premium\+|max/.test(p)) return 'elite';
    if(/\bpro\b|pro[_-]|professional/.test(p)) return 'pro';
    if(/basic|basis|starter/.test(p)) return 'basic';
    if(/free|gratis|kostenlos/.test(p)) return 'free';
    return 'free';
  }
  function writePlan(plan){
    try{
      var np=norm(plan);
      try{ var u=JSON.parse(localStorage.getItem('ch_user')||'{}'); u.plan=np; localStorage.setItem('ch_user',JSON.stringify(u)); }catch(e){}
      try{ if(window.P) window.P.plan=np; }catch(e){}
      try{ localStorage.setItem('ch_plan',np); }catch(e){}
      /* plan-bagimli arayuzu yenile (varsa) */
      try{ if(typeof window.chApplyPlan==='function') window.chApplyPlan(np); }catch(e){}
      try{ if(typeof window.refreshPlanUI==='function') window.refreshPlanUI(); }catch(e){}
      return np;
    }catch(e){ return 'free'; }
  }
  var busy=false, lastAt=0;
  function refresh(force){
    try{
      var tk=localStorage.getItem('ch_token'); if(!tk) return;
      var now=+new Date(); if(!force && (now-lastAt)<20000) return; lastAt=now;
      if(busy) return; busy=true;
      fetch(authUrl()+'?action=me',{method:'GET',headers:{'Authorization':'Bearer '+tk}})
       .then(function(r){ return r.json(); })
       .then(function(j){
          busy=false;
          var pl=(j&&j.user&&j.user.plan) || (j&&j.plan);
          if(pl!=null) writePlan(pl);
       })
       .catch(function(){ busy=false; });
    }catch(e){ busy=false; }
  }
  /* acilista + kisa tekrar (P gec yuklenebilir) + gorunurluk donunce */
  refresh(true);
  var n=0;(function loop(){ refresh(false); if(n++<6) setTimeout(loop,3000); })();
  try{ document.addEventListener('visibilitychange',function(){ if(!document.hidden) refresh(true); }); }catch(e){}
  /* odeme donusu / plan sayfasindan gelince de tazele */
  try{ window.addEventListener('focus',function(){ refresh(false); }); }catch(e){}
  window.chAboRefresh=function(){ refresh(true); };
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'ar').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-aborefresh-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_ABO_REFRESH')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_ABO_REFRESH diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Abonelik tanima: plan artik her acilista doMe'dan TAZE cekilir,\n";
echo "   normalize edilir (basic/pro/elite/free) ve ch_user.plan + window.P.plan'a\n";
echo "   yazilir. Odemis kullanici re-login GEREKMEDEN premium taninir;\n";
echo "   'Pro'/'elite_monthly' gibi degerler de dogru esler.\n";
