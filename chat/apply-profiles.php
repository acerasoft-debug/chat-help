<?php
/**
 * ChatHelp — apply-profiles (CH_PROFILES) — Task #29: plan bazli profiller
 *  Absender-Profile: Free/Basic 1 · Pro 5 · Elite sinirsiz + SERBEST AD.
 *  Mimarisi: mevcut hicbir akisa dokunmaz — P global objesinin ustunde
 *  localStorage katmani (ch_profiles + ch_prof_active).
 *   • Aktif profil P'ye uygulanir -> tum belgelerde gonderen olur
 *     (doGenerate profile:P, fall profBlock, [[DOC]] restore, CV autofill).
 *   • Editor nerede olursa olsun P'yi degistirir; katman P'yi 1.5sn'de bir
 *     aktif slota yansitir (cift yonlu senkron).
 *   • Konto paneline profil cipleri: sec / + Neu (plan kapili) /
 *     yeniden adlandir (yalniz Elite) / sil.
 *   • Mevcut veriler "Profil 1" olarak tasinir — veri kaybi yok.
 * KULLANIM: pull-updates.php?files=apply-profiles.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-profiles BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_PROFILES')!==false) exit("Zaten ekli (CH_PROFILES).\n");

$bundle = <<<'HTML'
<script id="ch-profiles-js">
/* CH_PROFILES — plan bazli Absender-Profile (Free/Basic 1 · Pro 5 · Elite sinirsiz+ad) */
try{(function(){
  var LSK="ch_profiles", LSA="ch_prof_active";
  var F=["f1","f2","f3","f4","f5","f6","f7"];
  function uil(){ try{ return localStorage.getItem("ch_uilang")||"de"; }catch(e){ return "de"; } }
  function plan(){ try{ return (window.P&&P.plan)||JSON.parse(localStorage.getItem("ch_user")||"{}").plan||"free"; }catch(e){ return "free"; } }
  function maxProf(){ var pl=plan(); if(pl==="elite") return 99; if(pl==="pro") return 5; return 1; }
  function load(){ try{ return JSON.parse(localStorage.getItem(LSK)||"null"); }catch(e){ return null; } }
  function save(a){ try{ localStorage.setItem(LSK,JSON.stringify(a)); }catch(e){} }
  function act(){ var n=parseInt(localStorage.getItem(LSA)||"0",10); return isNaN(n)||n<0?0:n; }
  function setAct(i){ try{ localStorage.setItem(LSA,String(i)); }catch(e){} }
  function snapP(){ var o={}; F.forEach(function(k){ o[k]=(window.P&&P[k])||""; }); return o; }
  function applyP(d){ try{ if(!window.P) return; F.forEach(function(k){ P[k]=(d&&d[k])||""; }); localStorage.setItem("ch_prof_v3",JSON.stringify(snapP())); }catch(e){} }
  function hasAddr(d){ return d&&(d.f3||d.f4||d.f5); }
  function ensure(){ var a=load(); if(!a||!a.length){ a=[{name:"Profil 1",d:snapP()}]; save(a); setAct(0); } return a; }

  /* aktif slot <- P (editor nerede olursa olsun degisiklik yakalanir) */
  setInterval(function(){ try{
    var a=ensure(), i=Math.min(act(),a.length-1);
    var s=snapP();
    if(JSON.stringify(s)!==JSON.stringify(a[i].d)){ a[i].d=s; save(a); }
  }catch(e){} },1500);

  /* yeniden yuklemede aktif profili P'ye uygula (login P'yi gec doldurabilir) */
  function boot(){ try{
    var a=load(); if(!a||!a.length) return;
    var i=Math.min(act(),a.length-1);
    if(hasAddr(a[i].d)||i>0) applyP(a[i].d);
  }catch(e){} }
  setTimeout(boot,1200); setTimeout(boot,3200);

  /* ── Konto UI ── */
  var TXT={
    de:{t:"Absender-Profile",s:"Aktives Profil wird in allen Dokumenten als Absender verwendet.",add:"+ Neu",lock:"Mehr Profile: Pro (5) · Elite (unbegrenzt)",ren:"Neuer Name:",del:"Profil löschen?"},
    tr:{t:"Gönderen Profilleri",s:"Aktif profil tüm belgelerde gönderen olarak kullanılır.",add:"+ Yeni",lock:"Daha fazla profil: Pro (5) · Elite (sınırsız)",ren:"Yeni ad:",del:"Profil silinsin mi?"},
    en:{t:"Sender profiles",s:"The active profile is used as sender in all documents.",add:"+ New",lock:"More profiles: Pro (5) · Elite (unlimited)",ren:"New name:",del:"Delete profile?"}
  };
  function L(){ return TXT[uil()]||TXT.de; }
  var lastSig="";
  function sig(a,i){ return JSON.stringify([a.map(function(p){return p.name;}),i,plan()]); }
  function render(){
    try{
      var pk=document.getElementById("pnl-konto"); if(!pk) return;
      var a=ensure(), i=Math.min(act(),a.length-1), mx=maxProf(), l=L(), el=plan()==="elite";
      var sg=sig(a,i); if(sg===lastSig && document.getElementById("ch-profiles-box")) return; lastSig=sg;
      var box=document.getElementById("ch-profiles-box");
      if(!box){
        box=document.createElement("div"); box.id="ch-profiles-box";
        box.style.cssText="margin:12px 14px;padding:12px 14px;border:1px solid rgba(212,168,74,.22);border-radius:13px;background:rgba(212,168,74,.05)";
        var ref=pk.querySelector(".pnl-back");
        if(ref&&ref.nextSibling) pk.insertBefore(box,ref.nextSibling);
        else pk.insertBefore(box,pk.firstChild);
      }
      var h='<div style="font-size:12.5px;font-weight:700;color:#fff;margin-bottom:2px">👤 '+l.t+'</div>'
          +'<div style="font-size:10.5px;color:var(--t3,#99a);margin-bottom:9px">'+l.s+'</div>'
          +'<div style="display:flex;flex-wrap:wrap;gap:7px">';
      a.forEach(function(p,j){
        var on=j===i;
        h+='<span data-pj="'+j+'" style="display:inline-flex;align-items:center;gap:6px;padding:6px 11px;border-radius:100px;cursor:pointer;font-size:11.5px;'
          +(on?'background:rgba(212,168,74,.18);border:1px solid #d4a84a;color:#fff;font-weight:700':'background:transparent;border:1px solid rgba(212,168,74,.25);color:var(--t2,#ccc)')+'">'
          +(on?"✓ ":"")+String(p.name).replace(/[<>&"]/g,"")
          +(el?'<b data-ren="'+j+'" style="opacity:.75;cursor:pointer">✏️</b>':'')
          +(j>0?'<b data-del="'+j+'" style="opacity:.6;cursor:pointer">✕</b>':'')
          +'</span>';
      });
      var full=a.length>=mx;
      h+='<span id="ch-prof-add" title="'+(full?l.lock:"")+'" style="display:inline-flex;align-items:center;padding:6px 11px;border-radius:100px;font-size:11.5px;border:1px dashed rgba(212,168,74,.45);color:'+(full?"var(--t3,#888);opacity:.55;cursor:default":"#d4a84a;cursor:pointer")+'">'+l.add+'</span>';
      h+='</div>'+(full&&mx<99?'<div style="font-size:10px;color:var(--t3,#888);margin-top:7px">🔒 '+l.lock+'</div>':'');
      box.innerHTML=h;
      box.querySelectorAll("[data-pj]").forEach(function(c){
        c.addEventListener("click",function(ev){
          if(ev.target&&(ev.target.getAttribute("data-ren")||ev.target.getAttribute("data-del"))) return;
          var j=+c.getAttribute("data-pj"); var aa=ensure(); var ii=Math.min(act(),aa.length-1);
          if(j===ii) return;
          aa[ii].d=snapP(); save(aa); setAct(j); applyP(aa[j].d); lastSig=""; render();
        });
      });
      box.querySelectorAll("[data-ren]").forEach(function(b){
        b.addEventListener("click",function(ev){ ev.stopPropagation();
          var j=+b.getAttribute("data-ren"); var aa=ensure();
          var nm=prompt(L().ren, aa[j].name); if(!nm) return;
          aa[j].name=nm.slice(0,24); save(aa); lastSig=""; render();
        });
      });
      box.querySelectorAll("[data-del]").forEach(function(b){
        b.addEventListener("click",function(ev){ ev.stopPropagation();
          var j=+b.getAttribute("data-del"); if(!confirm(L().del)) return;
          var aa=ensure(); aa.splice(j,1); save(aa);
          var ii=act(); if(ii>=aa.length){ setAct(aa.length-1); applyP(aa[aa.length-1].d); }
          else if(ii===j){ setAct(0); applyP(aa[0].d); }
          lastSig=""; render();
        });
      });
      var ad=document.getElementById("ch-prof-add");
      if(ad&&!full) ad.addEventListener("click",function(){
        var aa=ensure(); if(aa.length>=maxProf()) return;
        var cur=snapP();
        aa.push({name:"Profil "+(aa.length+1),d:{f1:cur.f1,f2:cur.f2,f3:"",f4:"",f5:"",f6:"",f7:cur.f7}});
        save(aa); var ii=Math.min(act(),aa.length-2); aa[ii].d=snapP(); save(aa);
        setAct(aa.length-1); applyP(aa[aa.length-1].d); lastSig=""; render();
      });
    }catch(e){}
  }
  setInterval(render,1500);
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",render); else render();
})();}catch(e){}
</script>
HTML;

$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi.\n");
$src = substr($src,0,$pos).$bundle."\n".substr($src,$pos);
echo "  ✓ profil katmani + Konto UI eklendi\n";

$tmp = tempnam(sys_get_temp_dir(),'pr').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-profiles-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_PROFILES uygulandi. Konto'da profil cipleri: Free/Basic 1 · Pro 5 · Elite sinirsiz + serbest ad.\n";
