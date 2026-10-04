<?php
/**
 * ChatHelp — apply-chatfix — Chat-PDF + Überarbeiten + Themen sakinlik (3 duzeltme):
 *  [1] CH_REVISE2: 'Überarbeiten' KOKTEN yenilendi — eski surum prompt() kullaniyordu
 *      (App WebView'de calismaz -> sessizce olur) ve genDoc'u yeniden cagiriyordu
 *      (yeni sablon acilmasinin nedeni!). Yeni surum: MEVCUT belge korunur, premium
 *      bir pencere acilir, istenen degisiklik AI'ya 'belgeyi AYNEN koru, SADECE sunu
 *      degistir' talimatiyla TAM metinle gonderilir, sonuc ayni belge karti olarak
 *      gosterilir. ASLA yeni sablon acilmaz. (Meine Themen/Fälle dahil tum cagiranlar
 *      ayni fonksiyonu kullanir.)
 *  [2] CH_CHATPDF_DIRECT: chat'te uretilen belge kartinin PDF butonu (ve ayni kaliba
 *      sahip vize-form PDF'i) artik gonderim modalini ACMADAN dogrudan PDF indirir.
 *  [3] CH_THEMEN_CALM: Meine Themen 1,2sn'lik yenileme dongusu sekme/uygulama arka
 *      plandayken calismaz (oynama/titreme azalir; icerik degismedikce zaten
 *      render edilmiyordu).
 * KULLANIM: pull2.php?key=...&files=apply-chatfix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-chatfix BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");

$ok=0;

/* ── [1] CH_REVISE2 ── */
if(strpos($src,'CH_REVISE2')===false){
  $o1=<<<'ANC'
window.chRevise=function(dk,prevDoc){
  var planLvl=(window.P&&P.plan)||(JSON.parse(localStorage.getItem('ch_user')||'{}').plan)||'free';
  var limits={basic:3,pro:10,elite:99999};
  if(!(planLvl in limits)){ if(typeof showPaywall==='function')showPaywall(dk,{}); return; }
  var key='rev_'+new Date().getFullYear()+'_'+(new Date().getMonth()+1);
  var used=parseInt(localStorage.getItem(key)||'0');
  if(used>=limits[planLvl]){
    addMsg('ai','⚠️ Überarbeitungs-Limit erreicht ('+used+'/'+(limits[planLvl]===99999?'∞':limits[planLvl])+' diesen Monat). Upgrade für mehr Überarbeitungen.');
    return;
  }
  var wunsch=prompt('Was soll geändert oder verbessert werden?');
  if(!wunsch||!wunsch.trim())return;
  localStorage.setItem(key,(used+1).toString());
  var base=(window._lastGenAns&&window._lastGenAns.dk===dk)?Object.assign({},window._lastGenAns.ans):{};
  base['aenderungswunsch']='WICHTIG — Überarbeitung der vorherigen Fassung. Gewünschte Änderung: '+wunsch.trim();
  base['vorherige_fassung']=(prevDoc||'').slice(0,1800);
  addMsg('user','🔄 Überarbeiten: '+wunsch.trim());
  genDoc(dk,base);
};
ANC;
  $n1=<<<'ANC'
window.chRevise=function(dk,prevDoc){/*CH_REVISE2 — mevcut belge korunur, asla yeni sablon acilmaz*/
  var planLvl=(window.P&&P.plan)||(JSON.parse(localStorage.getItem('ch_user')||'{}').plan)||'free';
  var limits={basic:3,pro:10,elite:99999};
  if(!(planLvl in limits)){ if(typeof showPaywall==='function')showPaywall(dk,{}); return; }
  var key='rev_'+new Date().getFullYear()+'_'+(new Date().getMonth()+1);
  var used=parseInt(localStorage.getItem(key)||'0');
  if(used>=limits[planLvl]){
    addMsg('ai','⚠️ Überarbeitungs-Limit erreicht ('+used+'/'+(limits[planLvl]===99999?'∞':limits[planLvl])+' diesen Monat).');
    return;
  }
  prevDoc=String(prevDoc||'');
  if(!prevDoc.trim()){ try{ var _dc=document.querySelectorAll('.ch-doccard-body'); if(_dc.length) prevDoc=_dc[_dc.length-1].textContent||''; }catch(e){} }
  if(!prevDoc.trim() && window._chLastDoc) prevDoc=String(window._chLastDoc);
  if(!prevDoc.trim()){ addMsg('ai','⚠️ Kein Dokument gefunden.'); return; }
  var L=(function(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } })();
  var TT={
    de:{t:'🔄 Dokument überarbeiten',s:'Ihr fertiges Dokument bleibt erhalten — nur die gewünschten Stellen werden geändert.',ph:'Was soll geändert werden? z. B. Frist auf 14 Tage ändern, Absatz zur Kaution ergänzen…',go:'Überarbeiten',c:'Abbrechen',w:'⏳ Wird überarbeitet…'},
    tr:{t:'🔄 Belgeyi düzenle',s:'Hazır belgeniz korunur — sadece istediğiniz yerler değişir.',ph:'Ne değişsin? örn. süreyi 14 güne çıkar, depozito paragrafı ekle…',go:'Düzenle',c:'Vazgeç',w:'⏳ Düzenleniyor…'},
    en:{t:'🔄 Revise document',s:'Your finished document is kept — only the requested parts change.',ph:'What should change?',go:'Revise',c:'Cancel',w:'⏳ Revising…'}
  }; var T2=TT[L]||TT.de;
  var ov=document.createElement('div'); ov.style.cssText='position:fixed;inset:0;background:rgba(8,8,14,.72);backdrop-filter:blur(4px);z-index:99998;display:flex;align-items:center;justify-content:center;padding:18px';
  var bx=document.createElement('div'); bx.style.cssText='width:100%;max-width:480px;background:linear-gradient(165deg,#16161c,#1b1b23);border:1px solid rgba(212,168,74,.35);border-radius:18px;padding:20px;box-shadow:0 18px 60px rgba(0,0,0,.6)';
  bx.innerHTML='<div style="font-size:16px;font-weight:800;color:#fff;margin-bottom:4px">'+T2.t+'</div>'
    +'<div style="font-size:12px;color:#9aa0bd;margin-bottom:12px;line-height:1.5">'+T2.s+'</div>'
    +'<textarea id="ch-rev-in" rows="4" placeholder="'+T2.ph+'" style="width:100%;box-sizing:border-box;background:#101016;border:1px solid rgba(255,255,255,.14);border-radius:12px;color:#fff;padding:11px 13px;font-size:13px;font-family:inherit;resize:vertical"></textarea>'
    +'<div style="display:flex;gap:9px;margin-top:14px"><button id="ch-rev-go" style="flex:1;background:linear-gradient(135deg,#e8c877,#d4a84a);color:#17171b;font-weight:800;border:0;border-radius:12px;padding:12px;font-size:13.5px;cursor:pointer">'+T2.go+'</button>'
    +'<button id="ch-rev-c" style="background:transparent;border:1px solid rgba(255,255,255,.16);color:#c8c8d8;border-radius:12px;padding:12px 16px;font-size:13px;cursor:pointer">'+T2.c+'</button></div>';
  ov.appendChild(bx); document.body.appendChild(ov);
  ov.addEventListener('click',function(e){ if(e.target===ov) ov.remove(); });
  document.getElementById('ch-rev-c').onclick=function(){ ov.remove(); };
  document.getElementById('ch-rev-go').onclick=function(){
    var wunsch=String((document.getElementById('ch-rev-in')||{}).value||'').trim(); if(!wunsch) return;
    var gb=this; gb.disabled=true; gb.textContent=T2.w;
    var API2=(typeof window.API!=='undefined'&&window.API)?window.API:'api.php';
    var msg='Hier ist ein FERTIGES Dokument. Übernimm es WORTGLEICH als Basis und ändere AUSSCHLIESSLICH Folgendes: "'+wunsch+'". Struktur, Absender, Empfänger, Betreff und alle übrigen Formulierungen bleiben UNVERÄNDERT. Gib NUR das vollständige überarbeitete Dokument zurück — keine Erklärungen, kein Markdown.\n\n'+prevDoc.slice(0,12000);
    fetch(API2+'?action=aichat',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({message:msg,history:[],provider:'deepseek',lang:L,country:(window.CC||'DE')})})
     .then(function(r){ return r.json(); })
     .then(function(j){
        var t=String((j&&j.reply)||'').replace(/```[a-z]*\n?|```/g,'').replace(/\[\[\/?DOC\]\]/g,'').trim();
        ov.remove();
        if(t.length<40){ addMsg('ai','⚠️ Überarbeitung fehlgeschlagen — bitte erneut versuchen.'); return; }
        localStorage.setItem(key,(used+1).toString());
        window._chLastDoc=t;
        addMsg('user','🔄 '+wunsch);
        try{ showResult(t,dk); }catch(e){ try{ addMsg('ai',t); }catch(e2){} }
     })
     .catch(function(){ ov.remove(); addMsg('ai','⚠️ Netzwerkfehler — bitte erneut versuchen.'); });
  };
  try{ document.getElementById('ch-rev-in').focus(); }catch(e){}
};
ANC;
  $c=substr_count($src,$o1);
  if($c===1){ $src=str_replace($o1,$n1,$src); echo "  ✓ [1] CH_REVISE2 — Überarbeiten: mevcut belge uzerinde, premium pencere, sablon YOK\n"; $ok++; }
  else echo "  ✗ [1] CH_REVISE2 anchor ($c, 1 beklenir) — atlandi\n";
} else { echo "  = [1] zaten ekli\n"; $ok++; }

/* ── [2] CH_CHATPDF_DIRECT ── */
if(strpos($src,'CH_CHATPDF_DIRECT')===false){
  $o2="    try{ if(window.chPrintDoc){ window.chPrintDoc(html); return; } }catch(e){}";
  $n2="    try{ if(window.chPrintDoc){ /*CH_CHATPDF_DIRECT*/window.__pdfConfirmed=true;window.__addr3OK=true;window.__addrOK=true;setTimeout(function(){try{window.__addr3OK=false;window.__addrOK=false;}catch(e0){}},2500); window.chPrintDoc(html); return; } }catch(e){}";
  $c=substr_count($src,$o2);
  if($c>=1 && $c<=4){ $src=str_replace($o2,$n2,$src); echo "  ✓ [2] CH_CHATPDF_DIRECT — chat belge karti (+ayni kalip) dogrudan PDF ($c yer)\n"; $ok++; }
  else echo "  ✗ [2] CH_CHATPDF_DIRECT anchor ($c, 1-4 beklenir) — atlandi\n";
} else { echo "  = [2] zaten ekli\n"; $ok++; }

/* ── [3] CH_THEMEN_CALM ── */
if(strpos($src,'CH_THEMEN_CALM')===false){
  $o3="setInterval(function(){ chatKill(); renderSec(); hideOldSec(); emsalBridge(); },1200);";
  $n3="setInterval(function(){ try{ if(document.hidden) return; }catch(e0){} chatKill(); renderSec(); hideOldSec(); emsalBridge(); },1200);/*CH_THEMEN_CALM*/";
  $c=substr_count($src,$o3);
  if($c===1){ $src=str_replace($o3,$n3,$src); echo "  ✓ [3] CH_THEMEN_CALM — Themen dongusu arka planda durur\n"; $ok++; }
  else echo "  ✗ [3] CH_THEMEN_CALM anchor ($c, 1 beklenir) — atlandi\n";
} else { echo "  = [3] zaten ekli\n"; $ok++; }

if($ok===0){ echo "\nHICBIR bolum uygulanamadi — HICBIR sey degistirilmedi.\n"; exit; }

$tmp=tempnam(sys_get_temp_dir(),'cf1').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-chatfix-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
echo "\n  Sonuc: $ok/3 bolum tamam (".strlen($chk)." B)\n";
echo "  Test-1: Belge uret -> 'Überarbeiten' -> premium pencere acilir, degisiklik yaz ->\n";
echo "          AYNI belge, SADECE istenen degisiklikle geri gelir (yeni sablon YOK).\n";
echo "  Test-2: Chat'te [[DOC]] belge karti -> PDF butonu -> modalsiz dogrudan PDF.\n";
