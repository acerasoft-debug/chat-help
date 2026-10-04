<?php
/**
 * ChatHelp — apply-fallpro — Meine Fälle premium tur (3 edit):
 *  [1] CH_FALLNEW: '+ Neuer Fall' prompt() kullaniyordu — App WebView'de prompt()
 *      CALISMAZ (buton sessizce oluyordu). Yeni: premium acilis penceresi + konuya
 *      bagli 3 EK soru (Konu/Hedef · Frist/tarih · Gegenseite) — cevaplar dosyanin
 *      ilk mesaji olarak kaydedilir, KI-analiz cok daha isabetli baslar.
 *  [2] CH_FALLDEL: silme onayi confirm() idi (ayni WebView sorunu) — premium
 *      onay penceresi ile degistirildi.
 *  [3] CH_FALLPDF: Fall icindeki 'PDF' butonu artik gonderim modalini acmadan
 *      DOGRUDAN PDF indirir (tum PDF butonlariyla tutarli).
 * KULLANIM: pull2.php?key=...&files=apply-fallpro.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fallpro BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");

$ok=0;

/* ── [1] CH_FALLNEW ── */
if(strpos($src,'CH_FALLNEW')===false){
  $o1=<<<'ANC'
  window.fallNew=function(){
    const t=prompt('Titel des Falls (z.B. „Mieterhöhung Widerspruch"):');
    if(!t||!t.trim())return;
    const all=falls(); const f={id:'f'+Date.now(),title:t.trim(),messages:[],document:'',createdAt:Date.now()};
    all.push(f); saveFalls(all); cur=f.id; render();
  };
ANC;
  $n1=<<<'ANC'
  window.fallNew=function(){/*CH_FALLNEW — premium acilis + konu sorulari (prompt() App'te calismiyordu)*/
    var L=(function(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } })();
    var TT={
      de:{t:'⚖️ Neuen Fall eröffnen',s:'Ein paar Angaben — die KI-Analyse wird dadurch deutlich präziser.',l1:'Titel des Falls *',p1:'z. B. Mieterhöhung Widerspruch',l2:'Worum geht es? Was möchten Sie erreichen?',p2:'kurz beschreiben (optional)',l3:'Gibt es eine Frist / ein wichtiges Datum?',p3:'z. B. 15.08.2026 (optional)',l4:'Gegenseite (Person/Firma/Behörde)',p4:'z. B. Vodafone GmbH (optional)',go:'Fall eröffnen',c:'Abbrechen'},
      tr:{t:'⚖️ Yeni dosya aç',s:'Birkaç bilgi — yapay zekâ analizi çok daha isabetli olur.',l1:'Dosya başlığı *',p1:'örn. Kira zammına itiraz',l2:'Konu ne? Hedefiniz ne?',p2:'kısaca yazın (opsiyonel)',l3:'Bir süre / önemli tarih var mı?',p3:'örn. 15.08.2026 (opsiyonel)',l4:'Karşı taraf (kişi/şirket/kurum)',p4:'örn. Vodafone GmbH (opsiyonel)',go:'Dosyayı aç',c:'Vazgeç'},
      en:{t:'⚖️ Open new case',s:'A few details make the AI analysis much more precise.',l1:'Case title *',p1:'e.g. Rent increase objection',l2:'What is it about? What do you want to achieve?',p2:'briefly (optional)',l3:'Any deadline / key date?',p3:'e.g. 2026-08-15 (optional)',l4:'Opposing party',p4:'e.g. Vodafone GmbH (optional)',go:'Open case',c:'Cancel'}
    }; var T2=TT[L]||TT.de;
    function fld(l,id,ph,ta){ return '<div style="margin:9px 0"><label style="font-size:11.5px;color:#ffd9a0;font-weight:600">'+l+'</label>'+(ta?'<textarea id="'+id+'" rows="2"':'<input id="'+id+'"')+' placeholder="'+ph+'" style="width:100%;box-sizing:border-box;margin-top:4px;background:#101016;border:1px solid rgba(255,255,255,.14);border-radius:11px;color:#fff;padding:10px 12px;font-size:13px;font-family:inherit"'+(ta?'></textarea>':'>')+'</div>'; }
    var ov=document.createElement('div'); ov.style.cssText='position:fixed;inset:0;background:rgba(8,8,14,.72);backdrop-filter:blur(4px);z-index:99998;display:flex;align-items:center;justify-content:center;padding:18px;overflow:auto';
    var bx=document.createElement('div'); bx.style.cssText='width:100%;max-width:470px;background:linear-gradient(165deg,#16161c,#1b1b23);border:1px solid rgba(212,168,74,.35);border-radius:18px;padding:20px;box-shadow:0 18px 60px rgba(0,0,0,.6)';
    bx.innerHTML='<div style="font-size:16px;font-weight:800;color:#fff;margin-bottom:4px">'+T2.t+'</div>'
      +'<div style="font-size:12px;color:#9aa0bd;margin-bottom:6px;line-height:1.5">'+T2.s+'</div>'
      +fld(T2.l1,'chfn-t',T2.p1,false)+fld(T2.l2,'chfn-z',T2.p2,true)+fld(T2.l3,'chfn-f',T2.p3,false)+fld(T2.l4,'chfn-g',T2.p4,false)
      +'<div style="display:flex;gap:9px;margin-top:12px"><button id="chfn-go" style="flex:1;background:linear-gradient(135deg,#e8c877,#d4a84a);color:#17171b;font-weight:800;border:0;border-radius:12px;padding:12px;font-size:13.5px;cursor:pointer">'+T2.go+'</button>'
      +'<button id="chfn-c" style="background:transparent;border:1px solid rgba(255,255,255,.16);color:#c8c8d8;border-radius:12px;padding:12px 16px;font-size:13px;cursor:pointer">'+T2.c+'</button></div>';
    ov.appendChild(bx); document.body.appendChild(ov);
    ov.addEventListener('click',function(e){ if(e.target===ov) ov.remove(); });
    document.getElementById('chfn-c').onclick=function(){ ov.remove(); };
    document.getElementById('chfn-go').onclick=function(){
      var t=String((document.getElementById('chfn-t')||{}).value||'').trim();
      if(!t){ var te=document.getElementById('chfn-t'); if(te){ te.style.borderColor='#e05050'; te.focus(); } return; }
      var z=String((document.getElementById('chfn-z')||{}).value||'').trim();
      var fr=String((document.getElementById('chfn-f')||{}).value||'').trim();
      var g=String((document.getElementById('chfn-g')||{}).value||'').trim();
      var all=falls(); var f={id:'f'+Date.now(),title:t,messages:[],document:'',createdAt:Date.now()};
      var parts=[]; if(z)parts.push((L==='tr'?'Konu/Hedef: ':'Worum es geht / Ziel: ')+z); if(fr)parts.push((L==='tr'?'Süre/Tarih: ':'Frist/Datum: ')+fr); if(g)parts.push((L==='tr'?'Karşı taraf: ':'Gegenseite: ')+g);
      if(parts.length) f.messages.push({role:'user',content:parts.join('\n')});
      all.push(f); saveFalls(all); cur=f.id; ov.remove(); render();
    };
    try{ document.getElementById('chfn-t').focus(); }catch(e){}
  };
ANC;
  $c=substr_count($src,$o1);
  if($c===1){ $src=str_replace($o1,$n1,$src); echo "  ✓ [1] CH_FALLNEW — premium acilis penceresi + 3 konu sorusu (prompt() gitti)\n"; $ok++; }
  else echo "  ✗ [1] CH_FALLNEW anchor ($c, 1 beklenir) — atlandi\n";
} else { echo "  = [1] zaten ekli\n"; $ok++; }

/* ── [2] CH_FALLDEL ── */
if(strpos($src,'CH_FALLDEL')===false){
  $o2="  window.fallDelete=function(id){ if(!confirm('Diesen Fall löschen?'))return; saveFalls(falls().filter(f=>f.id!==id)); if(cur===id)cur=null; render(); };";
  $n2=<<<'ANC'
  window.fallDelete=function(id){/*CH_FALLDEL — confirm() yerine premium onay*/
    var L=(function(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } })();
    var TX={de:['Diesen Fall löschen?','Löschen','Abbrechen'],tr:['Bu dosya silinsin mi?','Sil','Vazgeç'],en:['Delete this case?','Delete','Cancel']}[L]||['Diesen Fall löschen?','Löschen','Abbrechen'];
    var ov=document.createElement('div'); ov.style.cssText='position:fixed;inset:0;background:rgba(8,8,14,.72);z-index:99998;display:flex;align-items:center;justify-content:center;padding:18px';
    var bx=document.createElement('div'); bx.style.cssText='width:100%;max-width:340px;background:linear-gradient(165deg,#16161c,#1b1b23);border:1px solid rgba(224,80,80,.4);border-radius:16px;padding:20px;text-align:center';
    bx.innerHTML='<div style="font-size:14px;font-weight:700;color:#fff;margin-bottom:14px">🗑️ '+TX[0]+'</div><div style="display:flex;gap:9px"><button id="chfd-y" style="flex:1;background:#e05050;color:#fff;font-weight:800;border:0;border-radius:11px;padding:11px;cursor:pointer">'+TX[1]+'</button><button id="chfd-n" style="flex:1;background:transparent;border:1px solid rgba(255,255,255,.16);color:#c8c8d8;border-radius:11px;padding:11px;cursor:pointer">'+TX[2]+'</button></div>';
    ov.appendChild(bx); document.body.appendChild(ov);
    ov.addEventListener('click',function(e){ if(e.target===ov) ov.remove(); });
    document.getElementById('chfd-n').onclick=function(){ ov.remove(); };
    document.getElementById('chfd-y').onclick=function(){ ov.remove(); saveFalls(falls().filter(function(f){ return f.id!==id; })); if(cur===id)cur=null; render(); };
  };
ANC;
  $c=substr_count($src,$o2);
  if($c===1){ $src=str_replace($o2,$n2,$src); echo "  ✓ [2] CH_FALLDEL — premium silme onayi\n"; $ok++; }
  else echo "  ✗ [2] CH_FALLDEL anchor ($c, 1 beklenir) — atlandi\n";
} else { echo "  = [2] zaten ekli\n"; $ok++; }

/* ── [3] CH_FALLPDF ── */
if(strpos($src,'CH_FALLPDF')===false){
  $o3="  window.fallPDF=function(){ const f=getCur(); if(f&&f.document&&typeof makePDF==='function')makePDF(f.document,f.title,(window.CC||'DE')); else alert('PDF nicht verfügbar.'); };";
  $n3="  window.fallPDF=function(){/*CH_FALLPDF direct*/ const f=getCur(); if(f&&f.document&&typeof makePDF==='function'){ window.__pdfConfirmed=true;window.__addr3OK=true;window.__addrOK=true;setTimeout(function(){try{window.__addr3OK=false;window.__addrOK=false;}catch(e0){}},2500); makePDF(f.document,f.title,(window.CC||'DE')); } else alert('PDF nicht verfügbar.'); };";
  $c=substr_count($src,$o3);
  if($c===1){ $src=str_replace($o3,$n3,$src); echo "  ✓ [3] CH_FALLPDF — Fall PDF butonu dogrudan PDF\n"; $ok++; }
  else echo "  ✗ [3] CH_FALLPDF anchor ($c, 1 beklenir) — atlandi\n";
} else { echo "  = [3] zaten ekli\n"; $ok++; }

if($ok===0){ echo "\nHICBIR bolum uygulanamadi — HICBIR sey degistirilmedi.\n"; exit; }

$tmp=tempnam(sys_get_temp_dir(),'fp2').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-fallpro-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
echo "\n  Sonuc: $ok/3 bolum tamam (".strlen($chk)." B)\n";
echo "  Test: Meine Fälle -> '+ Neuer Fall' -> premium pencere: baslik + 3 konu sorusu;\n";
echo "  cevaplar dosyanin ilk mesaji olur -> 'Fall lösen' cok daha isabetli belge uretir.\n";
echo "  Fall'daki PDF butonu modalsiz dogrudan indirir; silme premium onayla.\n";
