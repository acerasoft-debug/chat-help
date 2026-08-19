<?php
/**
 * ChatHelp — apply-fax-ux (CH_FAX_UX) — fax gonderim deneyimi:
 *  [1] Ulkeye gore alan kodu ON-DOLU (+49/+90/+1...) — fax secilince #ai-faxnr
 *      bos ise otomatik doldurulur (AI bulursa tam numara yine ustune yazar).
 *  [2] Numara-bulma IPUCU (Impressum/website/mektup basligi).
 *  [3] Gonderimden ONCE canli ONIZLEME: hangi numaraya, gonderim raporu dahil.
 *  Additive </body> scripti (kirilgan anchor yok).
 * KULLANIM: pull2.php?key=...&files=apply-fax-ux.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fax-ux BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_FAX_UX')!==false) exit("Zaten ekli (CH_FAX_UX).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-fax-ux-js">
/* CH_FAX_UX — +49 on-dolu + numara-bulma ipucu + gonderim onizleme */
try{(function(){
  var CC={DE:'+49',AT:'+43',CH:'+41',TR:'+90',FR:'+33',IT:'+39',ES:'+34',NL:'+31',BE:'+32',PL:'+48',GB:'+44',US:'+1',RU:'+7',LU:'+352',SE:'+46',PT:'+351'};
  function legal(){ try{ return String(window.SERVER_LEGAL||localStorage.getItem('ch_law')||localStorage.getItem('ch_legal')||localStorage.getItem('ch_cc')||'DE').toUpperCase().slice(0,2); }catch(e){ return 'DE'; } }
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  var T={
    de:{help:'Faxnummer unbekannt? Im Impressum / auf der Website des Empfängers oder im Briefkopf suchen.', prev:'Wird gefaxt an', rep:'inkl. Sendebericht'},
    tr:{help:'Faks numarası bilinmiyor mu? Alıcının Impressum / web sitesinde ya da mektup başlığında ara.', prev:'Şuraya fakslanacak', rep:'gönderim raporu dahil'},
    en:{help:"Fax number unknown? Check the recipient's imprint / website or the letter header.", prev:'Will be faxed to', rep:'incl. transmission report'},
    fr:{help:"Numéro de fax inconnu ? Voir les mentions légales / le site du destinataire.", prev:'Sera faxé à', rep:'avec accusé de transmission'},
    es:{help:'¿Número de fax desconocido? Consulte el aviso legal / la web del destinatario.', prev:'Se enviará por fax a', rep:'con informe de envío'},
    it:{help:'Numero di fax sconosciuto? Controlla le note legali / il sito del destinatario.', prev:'Verrà inviato via fax a', rep:'con rapporto di trasmissione'}
  };
  function recName(){
    try{ var r=document.getElementById('ai-recipient'); if(r&&r.value) return (r.value.split('\n')[0]||'').trim(); }catch(e){}
    return '';
  }
  function tick(){
    try{
      var wrap=document.getElementById('ai-faxwrap'); var inp=document.getElementById('ai-faxnr');
      if(!wrap||!inp) return;
      var vis = getComputedStyle(wrap).display!=='none';
      if(vis){
        var t=T[lang()]||T.de;
        /* [1] on-dolu (bir kez, acilista) */
        if(!inp.getAttribute('data-ccp')){
          inp.setAttribute('data-ccp','1');
          if(!String(inp.value||'').trim()){ inp.value=(CC[legal()]||'+49')+' '; try{ inp.focus(); inp.setSelectionRange(inp.value.length,inp.value.length); }catch(e){} }
        }
        /* [2] ipucu */
        if(!document.getElementById('ai-faxhelp')){
          var h=document.createElement('div'); h.id='ai-faxhelp';
          h.style.cssText='font-size:11px;color:#9aa0bd;margin-top:6px;line-height:1.45';
          h.innerHTML='💡 '+t.help; wrap.appendChild(h);
        }
        /* [3] onizleme */
        var pv=document.getElementById('ai-faxprev');
        if(!pv){ pv=document.createElement('div'); pv.id='ai-faxprev';
          pv.style.cssText='font-size:12px;color:#e8c874;background:rgba(212,168,74,.08);border:1px solid rgba(212,168,74,.25);border-radius:9px;padding:8px 11px;margin-top:9px;line-height:1.5';
          wrap.appendChild(pv);
        }
        var num=String(inp.value||'').trim(); var rn=recName();
        pv.innerHTML='📠 <b>'+t.prev+':</b> '+(rn?('<span style="color:#fff">'+rn.replace(/</g,'&lt;')+'</span> · '):'')+'<span style="color:#fff">'+(num||'—')+'</span> <span style="color:#9aa0bd">('+t.rep+')</span>';
      } else {
        inp.removeAttribute('data-ccp');
      }
    }catch(e){}
  }
  try{ setInterval(tick,600); }catch(e){}
})();}catch(e){}
</script>
HTMLBLOCK;

$pos=strripos($src,'</body>');
if($pos===false) exit("</body> bulunamadi\n");
$new=substr($src,0,$pos).$block."\n".substr($src,$pos);
$tmp=tempnam(sys_get_temp_dir(),'fx').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-faxux-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_FAX_UX')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ CH_FAX_UX eklendi (".strlen($chk)." B)\n";
echo "✓ Fax secilince: +".($x=1)."… ulke kodu on-dolu (+49/+90/+1) + numara-bulma ipucu + canli onizleme.\n";
