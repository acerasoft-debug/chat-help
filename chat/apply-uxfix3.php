<?php
/**
 * ChatHelp — apply-uxfix3 (CH_UXFIX3) — 4 ekran-goruntusu tespitli duzeltme
 *  [1] Foto analizi: AI'nin "zusammenfassung" (ne yapilmali) aciklamasi artik
 *      GORUNUYOR, acik bir "Nasil ilerlemek istersiniz?" sorusu ekleniyor,
 *      docType taninmadiginda YANLIS/ALAKASIZ "Widerspruch/Widerruf" butonlari
 *      artik cikmiyor (bos birakiliyor — kullanici yazarak devam eder).
 *      EN ONEMLISI: analiz sonucu artik ch_chat_hist'e (sessionStorage) TEK
 *      SATIR baglam olarak yaziliyor — bu yuzden takip mesajlari (“nereye
 *      göndermem gerekiyor” gibi) artik belgeyi HATIRLIYOR (chAiChatTurn'un
 *      okudugu getHist() ayni storage key'i paylasiyor).
 *  [2] "Gezielte Fragen · klares Ziel" kutulu USP karti (.wlc-10x) kaldirilir;
 *      kisaltilmis hali "Ihr Recht. Ihr Schutz." altindaki alt basliga (#wlc-p)
 *      6 dilde birlestirilir.
 *  [3] Zen modu artik GERCEKTEN tertemiz: #wlc (dump-domstruct ile kanitlandi —
 *      hero+10x-karti+foto-CTA+Kategorien hepsi #wlc'nin ICINDE), #tb (ust bar)
 *      ve #ch-hub-fab (yuzen Module pili) de gizlenir; sadece mesajlar (#mi) ve
 *      yazma alani (#ia) kalir.
 *  [4] 3 yuzen widget'a (zen dugmesi, ust-sag dil/hukuk bayragi #tb-flag, Module
 *      pili) kalici "✕" kaldirma rozeti eklenir (localStorage, tiklaninca sonsuza
 *      kadar gizlenir; widget'in kendi tiklama davranisina dokunmaz).
 * KULLANIM: pull2.php?key=...&files=apply-uxfix3.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-uxfix3 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_UXFIX3')!==false) exit("Zaten ekli (CH_UXFIX3).\n");

$fail=[];

/* ── [1a] alts fallback: yanlis/alakasiz genel butonlar kaldirilir ── */
$o1 = <<<'EOT'
  (alts[docType]||['widerspruch','widerruf']).slice(0,2).forEach(k=>{if(DOCS[k]&&k!==docType)opts.push({k,t:DOCS[k].n,p:false});});
  if(!opts.length)opts.push({k:'widerspruch',t:'Widerspruch einlegen',p:true});
EOT;
$n1 = <<<'EOT'
  (alts[docType]||[]).slice(0,2).forEach(k=>{if(DOCS[k]&&k!==docType)opts.push({k,t:DOCS[k].n,p:false});}); /*CH_UXFIX3*/
EOT;
$c=substr_count($src,$o1);
if($c!==1){ echo "  ✗ [1a] alts anchor ($c)\n"; $fail[]='1a'; }
else { $src=str_replace($o1,$n1,$src,$c); echo "  ✓ [1a] yaniltici genel oneri butonlari kaldirildi\n"; }

/* ── [1b] zusammenfassung goster + acik soru + baglami hist'e yaz ── */
$o2 = <<<'EOT'
  html+='<div style="font-size:10.5px;color:#404060;margin-top:8px">Automatische Erkennung \u2014 keine rechtliche Bewertung.</div>';

  addMsg('ai',html);
});
EOT;
$n2 = <<<'EOT'
  if(r.zusammenfassung){ /*CH_UXFIX3*/
    html+=`<div style="font-size:12.5px;color:var(--t2);line-height:1.55;margin:2px 0 10px;padding:10px 12px;background:rgba(255,255,255,.03);border-radius:10px">${r.zusammenfassung}</div>`;
  }
  var _uxq={de:"Wie möchten Sie vorgehen?",en:"How would you like to proceed?",tr:"Nasıl ilerlemek istersiniz?",fr:"Comment souhaitez-vous procéder ?",es:"¿Cómo desea proceder?",it:"Come desidera procedere?"};
  html+=`<div style="font-size:12.5px;color:#fff;font-weight:600;margin-bottom:8px">${_uxq[UL]||_uxq.en}</div>`;
  html+='<div style="font-size:10.5px;color:#404060;margin-top:8px">Automatische Erkennung \u2014 keine rechtliche Bewertung.</div>';

  addMsg('ai',html);
  try{
    var _sum=(r.erkannt||'')+(r.zusammenfassung?' — '+r.zusammenfassung:'');
    var _parts=[]; if(window._lastOcrData.behoerde)_parts.push('Absender: '+window._lastOcrData.behoerde);
    if(window._lastOcrData.datum)_parts.push('Datum: '+window._lastOcrData.datum);
    if(window._lastOcrData.az)_parts.push('Aktenzeichen: '+window._lastOcrData.az);
    if(window._lastOcrData.frist)_parts.push('Frist: '+window._lastOcrData.frist);
    var _ctx='[Analysiertes Dokument] '+_sum+(_parts.length?' ('+_parts.join(', ')+')':'');
    var _hk='ch_chat_hist', _hs=[]; try{ _hs=JSON.parse(sessionStorage.getItem(_hk)||'[]'); }catch(e2){}
    _hs=_hs.concat([{role:'ai',content:_ctx}]);
    if(_hs.length>40) _hs=_hs.slice(-40);
    sessionStorage.setItem(_hk, JSON.stringify(_hs));
  }catch(e3){}
});
EOT;
$c=substr_count($src,$o2);
if($c!==1){ echo "  ✗ [1b] card-end anchor ($c)\n"; $fail[]='1b'; }
else { $src=str_replace($o2,$n2,$src,$c); echo "  ✓ [1b] zusammenfassung + acik soru gosteriliyor; baglam ch_chat_hist'e yaziliyor\n"; }

if ($fail) { echo "\nHATA: eslesmeyen adimlar: ".implode(', ',$fail)." — index DEGISTIRILMEDI.\n"; exit; }

/* ── [2]+[3]+[4] additive bundle (anchor gerektirmez, guvenli) ── */
$bundle = <<<'HTML'
<style id="ch-uxfix3-css">
/* CH_UXFIX3 */
/* [2] USP karti kalkar, alt baslikla birlesir */
.wlc-10x{display:none !important}
/* [3] zen: GERCEKTEN tertemiz — #wlc dump-domstruct ile kanitlandi (hero+10x+scan+kategorien hepsi icinde) */
body.ch-zen #wlc,body.ch-zen #tb,body.ch-zen #ch-hub-fab{display:none !important}
/* [4] widget kaldirma rozeti */
.ch-wx{position:fixed;width:19px;height:19px;border-radius:50%;background:#1b1b22;border:1.5px solid rgba(255,255,255,.3);
  color:#e8e8f0;font-size:10px;display:none;align-items:center;justify-content:center;cursor:pointer;z-index:99999;
  -webkit-tap-highlight-color:transparent;box-shadow:0 2px 8px rgba(0,0,0,.4);font-family:inherit}
.ch-wx:hover{border-color:rgba(255,90,90,.7);color:#ff9090}
</style>
<script id="ch-uxfix3-js">
/* CH_UXFIX3 — [2] baslik birlestirme + [4] widget X-kaldirma */
try{(function(){
  function UIL(){ try{ return (window.chUIL?window.chUIL():null)||localStorage.getItem("ch_uilang")||(typeof UL!=="undefined"?UL:"de"); }catch(e){ return "de"; } }
  var MERGE={
    de:"Professionelle Rechtsdokumente in Sekunden — präzisere Ergebnisse als Standard-KI.",
    en:"Professional legal documents in seconds — more precise than standard AI.",
    tr:"Saniyeler içinde profesyonel hukuki belgeler — standart yapay zekadan daha hassas.",
    fr:"Documents juridiques professionnels en quelques secondes — plus précis que l'IA standard.",
    es:"Documentos jurídicos profesionales en segundos — más precisos que la IA estándar.",
    it:"Documenti legali professionali in pochi secondi — più precisi dell'IA standard."
  };
  function mergeHeadline(){
    try{
      var p=document.getElementById("wlc-p"); if(!p) return;
      var t=MERGE[UIL()]||MERGE.en;
      if(p.textContent!==t) p.textContent=t;
    }catch(e){}
  }

  /* [4] widget X-kaldirma: DOM disina bagimsiz rozet (capture-listener catismasini onler) */
  function flags(){ try{ return JSON.parse(localStorage.getItem("ch_widget_hide")||"{}"); }catch(e){ return {}; } }
  function setFlag(key){ var f=flags(); f[key]=1; try{ localStorage.setItem("ch_widget_hide", JSON.stringify(f)); }catch(e){} }
  function isHidden(key){ return !!flags()[key]; }
  var WIDGETS=[{key:"zen",sel:"#ch-zen-btn"},{key:"flag",sel:"#tb-flag"},{key:"hub",sel:"#ch-hub-fab"}];
  function ensureX(key,sel){
    var id="chwx-"+key, x=document.getElementById(id);
    if(!x){
      x=document.createElement("div"); x.id=id; x.className="ch-wx"; x.textContent="✕"; x.setAttribute("data-noi18n","1");
      x.addEventListener("click",function(ev){
        try{ ev.stopPropagation(); ev.preventDefault(); }catch(e){}
        setFlag(key);
        try{ var el=document.querySelector(sel); if(el) el.style.display="none"; }catch(e){}
        x.style.display="none";
      });
      document.body.appendChild(x);
    }
    return x;
  }
  function widgetTick(){
    WIDGETS.forEach(function(w){
      var el=document.querySelector(w.sel);
      var x=ensureX(w.key,w.sel);
      if(isHidden(w.key)){ if(el) el.style.display="none"; x.style.display="none"; return; }
      if(!el){ x.style.display="none"; return; }
      var r=el.getBoundingClientRect();
      if(!r || (r.width===0 && r.height===0) || getComputedStyle(el).display==="none"){ x.style.display="none"; return; }
      x.style.display="flex";
      x.style.left=(r.right-8)+"px";
      x.style.top=(r.top-8)+"px";
    });
  }

  function tick(){ mergeHeadline(); widgetTick(); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",tick); else tick();
  setInterval(tick,1000);
})();}catch(e){}
</script>
HTML;

$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$src = substr($src,0,$pos).$bundle."\n".substr($src,$pos);
echo "  ✓ [2] USP karti kalkti; 'Ihr Recht. Ihr Schutz.' alt basligiyla birlesti (6 dil)\n";
echo "  ✓ [3] zen: #wlc + #tb + #ch-hub-fab de gizleniyor — gercekten sadece sohbet\n";
echo "  ✓ [4] zen/dil-bayragi/Module widget'larina kalici ✕ kaldirma rozeti eklendi\n";

$tmp = tempnam(sys_get_temp_dir(),'ux').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
$bk=@file_put_contents($file.'.bak-uxfix3-'.date('Ymd-His'), @file_get_contents($file));
if ($bk===false) echo "  ⚠ yedek yazilamadi — devam\n";
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI: ".var_export($w,true)."\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_UXFIX3')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_UXFIX3 diskte (".strlen($chk)." bayt)\n";
echo "\n✓ CH_UXFIX3 uygulandi (4 madde).\n";
