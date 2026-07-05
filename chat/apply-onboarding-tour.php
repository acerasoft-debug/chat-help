<?php
/**
 * ChatHelp — apply-onboarding-tour (CH_ONBOARD_TOUR)
 *  Ilk ziyarette 3 adimlik kisa bir tanitim modali gosterir:
 *   1) Derdinizi anlatin — yapay zeka anlar
 *   2) Dogru hukuk sistemi otomatik uygulanir (ulkenize gore)
 *   3) PDF'inizi alin, imzalayin, gonderin
 *  Sadece bir kez gosterilir (localStorage "ch_onboard_seen").
 *  Konto'dan tekrar izlenebilir kucuk bir "Turu tekrar goster" linki de
 *  ekler.
 * KULLANIM: pull-updates.php?files=apply-onboarding-tour.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-onboarding-tour BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_ONBOARD_TOUR') !== false) exit("Zaten ekli (CH_ONBOARD_TOUR).\n");
file_put_contents($file . '.bak-onboardtour-' . date('Ymd-His'), $src);

$bundle = <<<'OBHTML'
<style id="ch-onboard-tour-css">
.ch-ob-ov{position:fixed;inset:0;z-index:9000;background:rgba(10,10,14,.72);display:flex;align-items:center;justify-content:center;padding:16px}
.ch-ob-card{max-width:420px;width:100%;background:var(--s1,#1d1d24);border:1px solid rgba(212,168,74,.3);border-radius:20px;padding:30px 26px 22px;box-shadow:0 30px 70px rgba(0,0,0,.5);text-align:center}
.ch-ob-ic{font-size:44px;margin-bottom:16px}
.ch-ob-t{font-size:19px;font-weight:800;color:#fff;margin-bottom:10px;font-family:Georgia,ui-serif,serif}
.ch-ob-s{font-size:13.5px;color:var(--t3,#a8a8d0);line-height:1.6;margin-bottom:22px}
.ch-ob-dots{display:flex;gap:7px;justify-content:center;margin-bottom:20px}
.ch-ob-dot{width:7px;height:7px;border-radius:50%;background:rgba(212,168,74,.25);transition:background .2s}
.ch-ob-dot.on{background:var(--gold,#d4a84a)}
.ch-ob-row{display:flex;gap:10px}
.ch-ob-skip{flex:0;padding:12px 14px;border-radius:11px;border:1px solid var(--bdr,rgba(255,255,255,.14));background:transparent;color:var(--t3,#a8a8d0);font-size:13px;font-weight:700;cursor:pointer;font-family:inherit}
.ch-ob-next{flex:1;padding:12px;border-radius:11px;border:none;background:linear-gradient(135deg,#d4a84a,#f0c860);color:#1a1400;font-size:14px;font-weight:800;cursor:pointer}
</style>
<script id="ch-onboard-tour">
/* CH_ONBOARD_TOUR */
try{(function(){
  function UIL(){ try{ return (window.chUIL?window.chUIL():(localStorage.getItem("ch_uilang")||"de")); }catch(e){ return "de"; } }
  function esc(s){ return String(s==null?"":s).replace(/[&<>"]/g,function(c){return{"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];}); }
  var STEPS={
    de:[
      {ic:"💬",t:"Erzählen Sie, was los ist",s:"Kein Formular, kein Fachjargon nötig — schreiben Sie einfach in eigenen Worten, was passiert ist."},
      {ic:"⚖️",t:"Das richtige Recht — automatisch",s:"ChatHelp erkennt Ihr Land und wendet die passenden Gesetze an, von Deutschland bis in viele weitere Länder."},
      {ic:"📄",t:"PDF erhalten, unterschreiben, senden",s:"Ihr fertiges Schreiben liegt in Sekunden vor — Sie drucken, unterschreiben und schicken es ab."}
    ],
    tr:[
      {ic:"💬",t:"Derdinizi anlatın",s:"Form doldurmaya, hukuk terimi bilmeye gerek yok — olanı kendi kelimelerinizle yazın, yeter."},
      {ic:"⚖️",t:"Doğru hukuk — otomatik",s:"ChatHelp ülkenizi tanır ve o ülkenin kanunlarını uygular — Almanya dahil birçok ülke için."},
      {ic:"📄",t:"PDF'inizi alın, imzalayın, gönderin",s:"Hazır belgeniz saniyeler içinde önünüzde — yazdırın, imzalayın, gönderin."}
    ],
    en:[
      {ic:"💬",t:"Tell us what happened",s:"No forms, no legal jargon — just describe it in your own words."},
      {ic:"⚖️",t:"The right law — automatically",s:"ChatHelp detects your country and applies the matching legal system."},
      {ic:"📄",t:"Get your PDF, sign, send it",s:"Your finished letter is ready in seconds — print, sign and send."}
    ]
  };
  function steps(){ return STEPS[UIL()]||STEPS.en; }
  var i=0;
  function render(){
    var st=steps(), s=st[i];
    var ov=document.getElementById("ch-ob-ov");
    var isLast=i===st.length-1;
    var nextLabel=isLast?(UIL()==="tr"?"Başlayalım →":(UIL()==="de"?"Los geht's →":"Let's start →")):(UIL()==="tr"?"İleri →":(UIL()==="de"?"Weiter →":"Next →"));
    var skipLabel=UIL()==="tr"?"Atla":(UIL()==="de"?"Überspringen":"Skip");
    ov.querySelector(".ch-ob-card").innerHTML=
      '<div class="ch-ob-ic">'+s.ic+'</div><div class="ch-ob-t">'+esc(s.t)+'</div><div class="ch-ob-s">'+esc(s.s)+'</div>'
      +'<div class="ch-ob-dots">'+st.map(function(_,k){return '<div class="ch-ob-dot'+(k===i?" on":"")+'"></div>';}).join("")+'</div>'
      +'<div class="ch-ob-row">'+(isLast?"":'<button type="button" class="ch-ob-skip" id="ch-ob-skip">'+esc(skipLabel)+'</button>')
      +'<button type="button" class="ch-ob-next" id="ch-ob-next">'+esc(nextLabel)+'</button></div>';
    var skip=document.getElementById("ch-ob-skip"); if(skip) skip.addEventListener("click",close);
    document.getElementById("ch-ob-next").addEventListener("click",function(){
      if(isLast){ close(); return; }
      i++; render();
    });
  }
  function close(){
    try{ localStorage.setItem("ch_onboard_seen","1"); }catch(e){}
    var ov=document.getElementById("ch-ob-ov"); if(ov) ov.remove();
  }
  function open(){
    i=0;
    if(document.getElementById("ch-ob-ov")) return;
    var ov=document.createElement("div"); ov.id="ch-ob-ov"; ov.className="ch-ob-ov";
    ov.innerHTML='<div class="ch-ob-card"></div>';
    document.body.appendChild(ov);
    render();
  }
  window.chOpenTour=open;
  function maybeShow(){
    try{ if(!localStorage.getItem("ch_onboard_seen")) open(); }catch(e){}
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",function(){ setTimeout(maybeShow,600); });
  else setTimeout(maybeShow,600);

  /* Konto: "Turu tekrar goster" linki */
  function addReplayLink(){
    try{
      var host=document.querySelector("#pnl-konto .pnl-scroll")||document.getElementById("pnl-konto");
      if(!host||document.getElementById("ch-ob-replay")) return;
      var l=UIL()==="tr"?"↻ Tanıtım turunu tekrar göster":(UIL()==="de"?"↻ Einführung erneut ansehen":"↻ Replay intro tour");
      var a=document.createElement("div"); a.id="ch-ob-replay";
      a.style.cssText="margin:10px 0;font-size:12.5px;color:var(--gold,#d4a84a);cursor:pointer;text-align:center";
      a.textContent=l;
      a.addEventListener("click",function(){ open(); });
      host.appendChild(a);
    }catch(e){}
  }
  setInterval(addReplayLink,1500);
})();}catch(e){}
</script>
OBHTML;

$p = strrpos($src, '</body>');
if ($p === false) exit("HATA: </body> bulunamadi — yazilmadi.\n");
$src = substr($src, 0, $p) . $bundle . "\n" . substr($src, $p);

$tmp = tempnam(sys_get_temp_dir(), 'idx') . '.php';
file_put_contents($tmp, $src);
$lo = []; $rc = 0;
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc);
@unlink($tmp);
if ($rc !== 0) { echo "LINT HATASI — index.php degistirilmedi:\n  " . implode("\n  ", $lo) . "\n"; exit; }
file_put_contents($file, $src);
echo "✓ CH_ONBOARD_TOUR uygulandi. Ilk ziyarette 3 adimlik tur, Konto'da tekrar izleme linki.\n";
