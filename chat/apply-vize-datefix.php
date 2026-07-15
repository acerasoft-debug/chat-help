<?php
/**
 * ChatHelp — apply-vize-datefix (CH_VIZE_DATEFIX) — Vize Asistani tarih adimi:
 *  gidis/donus (#vz-from/#vz-to) "girilemiyor". Sebep: overlay icinde native
 *  date input tiklaninca takvimi acmiyor / picker ikonu koyu temada gorunmuyor.
 *  Cozum: (CSS) picker ikonu gorunur + tiklanabilir, mobilde alt alta, alanlar
 *  z-index'li; (JS) tiklayinca showPicker() ile takvimi ZORLA ac, readonly/
 *  disabled temizle, min=bugun. Additive, gozlemci YOK (freeze-safe). node ✓.
 * KULLANIM: pull2.php?key=...&files=apply-vize-datefix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-vize-datefix BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_VIZE_DATEFIX')!==false) exit("Zaten ekli (CH_VIZE_DATEFIX).\n");
if (strpos($src,'vz-from')===false) exit("HATA: Vize Asistani tarih alani (vz-from) yok.\n");

$block = <<<'HTMLBLOCK'
<style id="ch-vize-datefix-css">
/* CH_VIZE_DATEFIX — tarih alanlari her tarayicida tiklanabilir/gorunur */
#vz-ov .vz-field{position:relative;z-index:2}
#vz-ov input.vz-in[type="date"]{
  color-scheme:dark;-webkit-appearance:none;appearance:none;
  min-height:52px;line-height:1.25;cursor:pointer;position:relative;z-index:2;
  color:#fff;background:#12122a}
#vz-ov input.vz-in[type="date"]::-webkit-calendar-picker-indicator{
  filter:invert(1);opacity:.95;cursor:pointer;padding:6px;margin-left:4px}
#vz-ov input.vz-in[type="date"]::-webkit-date-and-time-value{text-align:left;color:#fff}
@media(max-width:440px){ #vz-ov .vz-2col{flex-direction:column;gap:0} }
</style>
<script id="ch-vize-datefix-js">
/* CH_VIZE_DATEFIX — freeze-safe: gozlemci yok, alanlari bir kez telle */
try{(function(){
  function today(){ try{ var d=new Date(); var m=('0'+(d.getMonth()+1)).slice(-2), day=('0'+d.getDate()).slice(-2); return d.getFullYear()+'-'+m+'-'+day; }catch(e){ return ''; } }
  function wire(inp){
    if(!inp || inp.__dfx) return; inp.__dfx=1;
    try{ inp.removeAttribute('readonly'); }catch(e){}
    try{ inp.removeAttribute('disabled'); }catch(e){}
    try{ inp.style.pointerEvents='auto'; }catch(e){}
    /* tiklayinca takvimi ZORLA ac (Chrome99+/Safari16+) — kullanici jesti */
    try{ inp.addEventListener('click',function(){ try{ if(typeof inp.showPicker==='function') inp.showPicker(); }catch(e){} }); }catch(e){}
    try{ inp.addEventListener('focus',function(){ try{ if(typeof inp.showPicker==='function') inp.showPicker(); }catch(e){} }); }catch(e){}
  }
  function work(){
    try{
      var ov=document.getElementById('vz-ov');
      if(!ov || !ov.classList.contains('on')) return;
      var f=document.getElementById('vz-from'), t=document.getElementById('vz-to');
      if(f){ if(!f.getAttribute('min')) f.setAttribute('min',today()); wire(f); }
      if(t){ if(!t.getAttribute('min')) t.setAttribute('min',today()); wire(t); }
    }catch(e){}
  }
  var n=0;(function loop(){ work(); if(n++<5000) setTimeout(loop,500); })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'df').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-vizedate-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_VIZE_DATEFIX')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_VIZE_DATEFIX diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Tarih alanlari: tiklayinca takvim acilir (showPicker), ikon gorunur, mobilde alt alta.\n";
