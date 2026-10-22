<?php
/**
 * ChatHelp — apply-topbar (CH_TOPBAR_MERGE) — ust bar tek hesap butonu.
 *  EKLEMELI </body> script: (1) tb-auth-label = ad (giris yapilmissa ilk ad),
 *  ad yoksa "Konto". (2) ust bardaki "Meine Anträge" ikon butonunu (gP('ant'))
 *  gizler — kaydedilenler zaten Meine Fälle'de. MutationObserver ile etiket
 *  cakismasi/flicker olmadan sabitlenir. Mevcut kod degismez.
 * KULLANIM: pull2.php?key=...&files=apply-topbar.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-topbar BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_TOPBAR_MERGE')!==false) exit("Zaten ekli (CH_TOPBAR_MERGE).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-topbar-merge">
/* CH_TOPBAR_MERGE — tek hesap butonu (ad / "Konto") + Meine Anträge ust bar ikonu gizle */
try{(function(){
  function nameOrKonto(){
    try{ var tk=localStorage.getItem('ch_token'); if(tk){ var u=JSON.parse(localStorage.getItem('ch_user')||'{}'); var nm=(u&&u.name)?String(u.name).trim():''; if(nm) return nm.split(' ')[0]; } }catch(e){}
    return 'Konto';
  }
  function hideAnt(){
    try{
      document.querySelectorAll('.tb-btn[onclick]').forEach(function(b){
        var oc=(b.getAttribute('onclick')||'').replace(/["\s]/g,'');
        if(oc.indexOf("gP('ant')".replace(/["\s]/g,''))>-1 || oc.indexOf('gP(ant)')>-1) b.style.display='none';
      });
    }catch(e){}
  }
  var mo=null, curLbl=null;
  function bind(){
    var lbl=document.getElementById('tb-auth-label');
    if(lbl && lbl!==curLbl){
      curLbl=lbl;
      var fix=function(){ var w=nameOrKonto(); if(lbl.textContent!==w) lbl.textContent=w; };
      fix();
      try{ if(mo) mo.disconnect(); mo=new MutationObserver(fix); mo.observe(lbl,{childList:true,characterData:true,subtree:true}); }catch(e){}
    } else if(lbl){ var w=nameOrKonto(); if(lbl.textContent!==w) lbl.textContent=w; }
    hideAnt();
  }
  var n=0; (function loop(){ bind(); if(n++<160) setTimeout(loop,800); })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'tb').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
$bk=@file_put_contents($file.'.bak-topbar-'.date('Ymd-His'), $src);
if ($bk===false) echo "  ⚠ yedek yazilamadi — devam\n";
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_TOPBAR_MERGE')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_TOPBAR_MERGE diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Ust bar: tek hesap butonu (ad / 'Konto'); Meine Anträge ust bar ikonu gizlendi.\n";
