<?php
/**
 * ChatHelp — apply-pdf-mobile (CH_PDF_MOBILE)
 *  KOK NEDEN: chPrintDoc gizli 1px iframe'den print() cagiriyor — MASAUSTUNDE
 *  calisir ama MOBILDE (Android/Samsung) sessizce hicbir sey yapmaz. Musteri
 *  telefonda oldugu icin "Als PDF speichern" butonu olu gorunuyor.
 *  COZUM: </body> oncesi EKLEMELI override — window.chPrintDoc'u mobil-guvenli
 *  yapar: mobilde GORUNUR yeni sekme + otomatik print (Android'de "PDF kaydet"
 *  dialogu), popup engelliyse tam ekran onizleme + Yazdir butonu. Masaustunde
 *  eski gizli-iframe korunur. Tum PDF yollari (makePDF/CV/Vertrag) chPrintDoc'tan
 *  gectigi icin hepsi birden duzelir. Mevcut kod DEGISMEZ (sadece override).
 * KULLANIM: pull2.php?key=...&files=apply-pdf-mobile.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-pdf-mobile BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_PDF_MOBILE')!==false) exit("Zaten ekli (CH_PDF_MOBILE).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-pdf-mobile">
/* CH_PDF_MOBILE — chPrintDoc mobil-guvenli override (gizli iframe print mobilde sessiz kaliyordu) */
try{(function(){
  var SCRIPT_RE = new RegExp('<scr'+'ipt[\\s\\S]*?<\\/scr'+'ipt>','gi');
  function isMobile(){ try{ return /Android|iPhone|iPad|iPod|Mobile|Silk|Kindle|Opera Mini/i.test(navigator.userAgent||''); }catch(e){ return false; } }
  function overlay(html){
    try{
      var old=document.getElementById('ch-pdf-ov'); if(old) old.remove();
      var t='de'; try{ t=(localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){}
      var L={de:['Drucken / Als PDF speichern','Schließen'],tr:['Yazdır / PDF olarak kaydet','Kapat'],en:['Print / Save as PDF','Close'],fr:['Imprimer / Enregistrer en PDF','Fermer'],es:['Imprimir / Guardar PDF','Cerrar'],it:['Stampa / Salva PDF','Chiudi'],nl:['Afdrukken / PDF opslaan','Sluiten']};
      var lb=L[t]||L.de;
      var ov=document.createElement('div'); ov.id='ch-pdf-ov';
      ov.style.cssText='position:fixed;inset:0;z-index:2147483600;background:#2a2a2a;display:flex;flex-direction:column';
      var bar=document.createElement('div');
      bar.style.cssText='display:flex;gap:8px;padding:10px;background:#0d0d1c;box-shadow:0 2px 12px rgba(0,0,0,.4)';
      var bp=document.createElement('button');
      bp.textContent=lb[0];
      bp.style.cssText='flex:1;background:linear-gradient(135deg,#d4a84a,#ecc060);color:#07070e;border:none;border-radius:10px;padding:13px;font-weight:800;font-size:14px;font-family:inherit;-webkit-tap-highlight-color:transparent';
      var bc=document.createElement('button');
      bc.textContent=lb[1];
      bc.style.cssText='background:#1a1a30;color:#c0c0e0;border:1px solid rgba(255,255,255,.15);border-radius:10px;padding:13px 18px;font-size:14px;font-family:inherit';
      bar.appendChild(bp); bar.appendChild(bc);
      var fr=document.createElement('iframe');
      fr.style.cssText='flex:1;width:100%;border:0;background:#fff';
      ov.appendChild(bar); ov.appendChild(fr);
      document.body.appendChild(ov);
      var d=fr.contentWindow.document; d.open(); d.write(html); d.close();
      bc.onclick=function(){ ov.remove(); };
      bp.onclick=function(){ try{ fr.contentWindow.focus(); fr.contentWindow.print(); }catch(e){ try{ window.print(); }catch(e2){} } };
    }catch(e){}
  }
  window.chPrintDoc=function(html){
    try{
      var clean=String(html).replace(SCRIPT_RE,'');
      if(isMobile()){
        var printJs='<scr'+'ipt>window.onload=function(){setTimeout(function(){try{window.focus();window.print();}catch(e){}},450);};<\/scr'+'ipt>';
        var w=null; try{ w=window.open('','_blank'); }catch(e){ w=null; }
        if(w&&w.document){ try{ w.document.open(); w.document.write(clean+printJs); w.document.close(); return; }catch(e){} }
        overlay(clean); return;
      }
      var oldf=document.getElementById('ch-print-frame'); if(oldf) oldf.remove();
      var f=document.createElement('iframe'); f.id='ch-print-frame';
      f.style.cssText='position:fixed;right:0;bottom:0;width:1px;height:1px;border:0;opacity:0';
      document.body.appendChild(f);
      var dd=f.contentWindow.document; dd.open(); dd.write(clean); dd.close();
      setTimeout(function(){ try{ f.contentWindow.focus(); f.contentWindow.print(); }catch(e){} },500);
    }catch(e){ try{ overlay(String(html).replace(SCRIPT_RE,'')); }catch(e2){} }
  };
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'pm').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }

$bk=@file_put_contents($file.'.bak-pdfmobile-'.date('Ymd-His'), $src);
if ($bk===false) echo "  ⚠ yedek yazilamadi — devam\n";
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_PDF_MOBILE')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_PDF_MOBILE diskte (".strlen($chk)." bayt)\n";
echo "\n✓ chPrintDoc mobil-guvenli. Telefonda 'Als PDF speichern' artik gorunur\n";
echo "   yeni sekme + otomatik yazdirma (Android'de PDF kaydet) acar. Masaustu aynen.\n";
