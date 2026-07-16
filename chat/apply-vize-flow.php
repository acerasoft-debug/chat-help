<?php
/**
 * ChatHelp — apply-vize-flow (CH_VIZE_FLOW) — Vize Asistani'na TEK DUZEN
 *  akis rehberi: belge adiminin ustune 4 adimlik gorsel serit eklenir:
 *   1️⃣ Belgeleri Hazırla  →  2️⃣ Evrakları Topla  →  3️⃣ Prüfung  →  4️⃣ Randevu
 *  Her adim tiklanabilir: 1 belge listesine, 2 evrak listesine kaydirir,
 *  3 Prüfung kontrolunu baslatir, 4 randevu bolumune goturur (CH_VIZE_RDV).
 *  Kullanici artik ne yapacagini adim adim gorur ("konforlu, tek duzen").
 *  Sadece EKLEMELI DOM enjeksiyonu (mevcut cizim kodu DEGISMEZ). node ✓.
 * KULLANIM: pull2.php?key=...&files=apply-vize-flow.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-vize-flow BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_VIZE_FLOW')!==false) exit("Zaten ekli (CH_VIZE_FLOW).\n");
if (strpos($src,'window.__vzGen=')===false) exit("HATA: Vize Asistani yok.\n");

$block = <<<'HTMLBLOCK'
<style id="ch-vize-flow-css">
#vz-flow{display:flex;gap:6px;flex-wrap:wrap;margin:2px 0 14px;padding:10px;background:rgba(212,168,74,.07);border:1px solid rgba(212,168,74,.22);border-radius:14px}
#vz-flow .st{flex:1;min-width:105px;display:flex;align-items:center;gap:7px;padding:8px 10px;border-radius:10px;cursor:pointer;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);transition:.15s}
#vz-flow .st:hover{border-color:rgba(212,168,74,.45);background:rgba(212,168,74,.10)}
#vz-flow .st .n{width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,#d4a84a,#ecc060);color:#191000;font-size:11.5px;font-weight:800;display:flex;align-items:center;justify-content:center;flex:0 0 auto}
#vz-flow .st .l{font-size:11.5px;font-weight:700;color:#e8e8f4;line-height:1.25}
#vz-flow .ar{align-self:center;color:#8a8ab0;font-size:12px}
@media(max-width:520px){ #vz-flow .ar{display:none} }
</style>
<script id="ch-vize-flow-js">
/* CH_VIZE_FLOW — belge adiminda 4 adimlik akis rehberi */
try{(function(){
  function mk(){
    var w=document.createElement('div'); w.id='vz-flow';
    var steps=[
      ['1','Belgeleri Hazırla',function(ov){ var t=ov.querySelector('.vz-gen'); if(t&&t.scrollIntoView) t.scrollIntoView({behavior:'smooth',block:'start'}); }],
      ['2','Evrakları Topla',function(ov){ var t=ov.querySelector('.vz-cl-grp'); if(t&&t.scrollIntoView) t.scrollIntoView({behavior:'smooth',block:'start'}); }],
      ['3','Prüfung (Son Kontrol)',function(ov){ var b=ov.querySelector('.vz-pruef'); if(b){ if(b.scrollIntoView) b.scrollIntoView({behavior:'smooth',block:'center'}); b.click(); } }],
      ['4','Randevu',function(ov){
        var cands=ov.querySelectorAll('button, a, .vz-btn, .vz-mini, div');
        for(var i=0;i<cands.length;i++){
          var t=(cands[i].textContent||'');
          if(t.indexOf('Randevu')!==-1 && t.length<40){ if(cands[i].scrollIntoView) cands[i].scrollIntoView({behavior:'smooth',block:'center'}); return; }
        }
        var nav=ov.querySelector('.vz-nav'); if(nav&&nav.scrollIntoView) nav.scrollIntoView({behavior:'smooth'});
      }]
    ];
    for(var i=0;i<steps.length;i++){
      (function(s,last){
        var d=document.createElement('div'); d.className='st';
        d.innerHTML='<span class="n">'+s[0]+'</span><span class="l">'+s[1]+'</span>';
        d.onclick=function(){ try{ var ov=document.getElementById('vz-ov'); if(ov) s[2](ov); }catch(e){} };
        w.appendChild(d);
        if(!last){ var a=document.createElement('span'); a.className='ar'; a.textContent='›'; w.appendChild(a); }
      })(steps[i], i===steps.length-1);
    }
    return w;
  }
  function inject(){
    try{
      var ov=document.getElementById('vz-ov'); if(!ov) return;
      if(ov.querySelector('#vz-flow')) return;
      var firstGen=ov.querySelector('.vz-gen'); if(!firstGen) return; /* yalniz belge adiminda */
      var sec=ov.querySelector('.vz-sec-t');
      if(sec && sec.parentNode){ sec.parentNode.insertBefore(mk(), sec); }
    }catch(e){}
  }
  var n=0;(function loop(){ inject(); if(n++<6000) setTimeout(loop,700); })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'vfl').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-vizeflow-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_VIZE_FLOW')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_VIZE_FLOW diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Vize Asistani belge adiminda artik 4 adimlik akis rehberi var:\n";
echo "   1️⃣ Belgeleri Hazırla › 2️⃣ Evrakları Topla › 3️⃣ Prüfung › 4️⃣ Randevu\n";
echo "   Adimlara tiklayinca ilgili bolume gider / kontrolu baslatir.\n";
