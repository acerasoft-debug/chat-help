<?php
/**
 * ChatHelp — apply-vize-ux (CH_VIZE_UX) — Vize Asistanini KONFORLU + ANLASILIR yap.
 *  Sorun: asistan (vz-ov/chVizeAsistan) canli ama zor bulunuyor — nav-trvisa CSS
 *  ile gizli, launcher sadece derindeki bir Schengen paragrafinin ustunde.
 *  Cozum: (1) TR vize panelinin (#pnl-trvisa) EN USTUNE her zaman gorunen, 4
 *  adimi aciklayan buyuk hero launcher; (2) asistan akisinda adim ETIKETLERI
 *  (1·Ulke 2·Amac 3·Tarih 4·Belgeler). Additive, MutationObserver YOK
 *  (freeze-safe), idempotent. Gomulu JS node --check ile dogrulandi.
 * KULLANIM: pull2.php?key=...&files=apply-vize-ux.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-vize-ux BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_VIZE_UX')!==false) exit("Zaten ekli (CH_VIZE_UX).\n");
if (strpos($src,'chVizeAsistan')===false) exit("HATA: Vize Asistani (chVizeAsistan) yok — once apply-vize-asistan deploy edilmeli.\n");

$block = <<<'HTMLBLOCK'
<style id="ch-vize-ux-css">
/* CH_VIZE_UX — TR vize paneli hero launcher + asistan adim etiketleri */
#vz-hero{background:linear-gradient(135deg,rgba(212,168,74,.18),rgba(212,168,74,.05));border:1.5px solid rgba(212,168,74,.45);
  border-radius:18px;padding:18px 18px 16px;margin:0 0 16px;cursor:pointer;transition:transform .12s,border-color .15s}
#vz-hero:hover{transform:translateY(-2px);border-color:rgba(212,168,74,.7)}
#vz-hero .vh-top{display:flex;align-items:center;gap:13px;margin-bottom:13px}
#vz-hero .vh-ic{font-size:34px;line-height:1}
#vz-hero .vh-t{font-size:18px;font-weight:800;color:#f0d488}
#vz-hero .vh-s{font-size:12.5px;color:#c8c8dc;margin-top:3px;line-height:1.4}
#vz-hero .vh-steps{display:flex;flex-wrap:wrap;gap:7px;margin-bottom:14px}
#vz-hero .vh-steps span{font-size:11.5px;font-weight:700;color:#d9c48f;background:rgba(212,168,74,.12);
  border:1px solid rgba(212,168,74,.25);border-radius:100px;padding:5px 11px;white-space:nowrap}
#vz-hero .vh-btn{width:100%;background:linear-gradient(135deg,#d4a84a,#ecc060);color:#07070e;border:none;
  border-radius:12px;padding:13px;font-size:15px;font-weight:800;font-family:inherit;cursor:pointer}
.vz-steplabels{display:flex;gap:6px;margin:-14px 0 18px}
.vz-steplabels span{flex:1;text-align:center;font-size:10.5px;font-weight:800;color:#8e8e9c;
  text-transform:uppercase;letter-spacing:.3px;transition:color .2s}
.vz-steplabels span.on{color:#e8c874}
</style>
<script id="ch-vize-ux-js">
/* CH_VIZE_UX — freeze-safe: gozlemci yok, idempotent, sinirli dongu */
try{(function(){
  var LB=['Ülke','Amaç','Tarih','Belgeler'];
  function heroHtml(){
    return '<div id="vz-hero" role="button" tabindex="0">'
      +'<div class="vh-top"><span class="vh-ic">🛂</span><div>'
      +'<div class="vh-t">Vize Asistanı</div>'
      +'<div class="vh-s">Ülke ve tarihi seçin; gerekli tüm belgeleri hazırlayıp adım adım evrak listenizi çıkaralım.</div>'
      +'</div></div>'
      +'<div class="vh-steps"><span>1 · Ülke</span><span>2 · Amaç</span><span>3 · Tarih</span><span>4 · Belgeler</span></div>'
      +'<button class="vh-btn">Başla →</button></div>';
  }
  function openAsist(){ try{ if(typeof window.chVizeAsistan==='function') window.chVizeAsistan(); }catch(e){} }
  function work(){
    /* (1) TR vize panelinin en ustune hero launcher */
    try{
      var pnl=document.getElementById('pnl-trvisa');
      if(pnl && !document.getElementById('vz-hero')){
        var w=document.createElement('div'); w.innerHTML=heroHtml();
        var node=w.firstChild;
        if(node){
          node.addEventListener('click',openAsist);
          node.addEventListener('keydown',function(ev){ if(ev.key==='Enter'||ev.key===' '){ ev.preventDefault(); openAsist(); } });
          pnl.insertBefore(node, pnl.firstChild);
        }
      }
    }catch(e){}
    /* (2) asistan acikken adim etiketleri (draw() innerHTML'i sildikce yeniden ekle) */
    try{
      var ov=document.getElementById('vz-ov');
      if(ov && ov.classList.contains('on')){
        var bar=ov.querySelector('.vz-steps');
        if(bar && !ov.querySelector('.vz-steplabels')){
          var on=ov.querySelectorAll('.vz-steps .s.on').length;
          var h='<div class="vz-steplabels">';
          for(var i=0;i<4;i++) h+='<span class="'+(i<on?'on':'')+'">'+LB[i]+'</span>';
          h+='</div>';
          bar.insertAdjacentHTML('afterend',h);
        }
      }
    }catch(e){}
  }
  /* SADECE interval — MutationObserver YOK (freeze onlenir) */
  var n=0;(function loop(){ work(); if(n++<5000) setTimeout(loop,550); })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'vux').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-vizeux-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_VIZE_UX')===false || strpos($chk,'vz-hero')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_VIZE_UX diskte (".strlen($chk)." bayt)\n";
echo "\n✓ TR vize paneli ustune hero launcher + asistanda adim etiketleri eklendi.\n";
echo "   Panel: sol menu / ana sayfa karti -> Türkiye Vize -> en ustte 'Vize Asistanı' hero -> Başla.\n";
