<?php
/**
 * ChatHelp — apply-fax-aifind (CH_FAX_AIFIND) — fax numarasi bulmada AI yardimi:
 *  #ai-faxwrap icine "🔍 Faxnummer per KI finden" butonu. Alici adini (ai-recipient
 *  ilk satir) alir, api.php?action=aichat'e OFFIZIELLE Faxnummer sorgusu atar,
 *  bulursa #ai-faxnr'a yazar; emin degilse elle-giris ipucu gosterir. 6 dil.
 * KULLANIM: pull2.php?key=...&files=apply-fax-aifind.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fax-aifind BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_FAX_AIFIND')!==false) exit("Zaten ekli (CH_FAX_AIFIND).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-fax-aifind-js">
/* CH_FAX_AIFIND — AI ile fax numarasi bulma butonu */
try{(function(){
  function API(){ try{ return (typeof window.API!=='undefined'&&window.API)?window.API:'api.php'; }catch(e){ return 'api.php'; } }
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  var T={
    de:{b:'🔍 Faxnummer per KI finden', wait:'🔍 Suche…', need:'Bitte zuerst den Empfänger (Name) eingeben.', ok:'✓ Gefunden — bitte prüfen.', no:'KI ist nicht sicher — bitte Nummer manuell eingeben (Impressum/Website).'},
    tr:{b:'🔍 Faks numarasını AI ile bul', wait:'🔍 Aranıyor…', need:'Önce alıcıyı (isim) gir.', ok:'✓ Bulundu — lütfen kontrol et.', no:'AI emin değil — numarayı elle gir (Impressum/web sitesi).'},
    en:{b:'🔍 Find fax number with AI', wait:'🔍 Searching…', need:'Please enter the recipient (name) first.', ok:'✓ Found — please verify.', no:'AI is not sure — please enter the number manually (imprint/website).'},
    fr:{b:'🔍 Trouver le fax avec IA', wait:'🔍 Recherche…', need:"Saisissez d'abord le destinataire.", ok:'✓ Trouvé — à vérifier.', no:"IA incertaine — saisissez le numéro manuellement."},
    es:{b:'🔍 Buscar fax con IA', wait:'🔍 Buscando…', need:'Primero introduzca el destinatario.', ok:'✓ Encontrado — verifíquelo.', no:'La IA no está segura — introduzca el número manualmente.'},
    it:{b:'🔍 Trova fax con IA', wait:'🔍 Ricerca…', need:'Inserisci prima il destinatario.', ok:'✓ Trovato — verifica.', no:"L'IA non è sicura — inserisci il numero manualmente."}
  };
  function recName(){ try{ var r=document.getElementById('ai-recipient'); if(r&&r.value) return (r.value.split('\n')[0]||'').trim(); }catch(e){} return ''; }
  function pickNum(txt){ txt=String(txt||''); if(txt.toUpperCase().indexOf('UNBEKANNT')>=0) return ''; var m=txt.match(/\+?\d[\d\s\/().\-]{6,}\d/); return m?m[0].replace(/\s{2,}/g,' ').trim():''; }
  function run(btn,stat){
    var t=T[lang()]||T.de; var nm=recName();
    if(!nm){ stat.textContent=t.need; stat.style.color='#e0a0a0'; return; }
    var old=btn.textContent; btn.textContent=t.wait; btn.disabled=true; stat.textContent='';
    var msg='Nenne die OFFIZIELLE Faxnummer von "'+nm+'" NUR wenn du sie ABSOLUT SICHER kennst (Behörde/bekannte Firma). Bist du nicht sicher, antworte NUR mit dem Wort: UNBEKANNT. Erfinde KEINE Nummer. Nur die Nummer im Format +49..., sonst UNBEKANNT.';
    fetch(API()+'?action=aichat',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({message:msg})})
      .then(function(r){ return r.json().catch(function(){ return {}; }); })
      .then(function(d){
        var txt = (d&&(d.reply||d.answer||d.text||d.message||d.content||d.output))||(typeof d==='string'?d:'');
        var num=pickNum(txt);
        var inp=document.getElementById('ai-faxnr');
        if(num && inp){ inp.value=num; stat.textContent=t.ok; stat.style.color='#8fd0a0'; }
        else { stat.textContent=t.no; stat.style.color='#e0c090'; }
      })
      .catch(function(){ stat.textContent=(T[lang()]||T.de).no; stat.style.color='#e0c090'; })
      .then(function(){ btn.textContent=old; btn.disabled=false; });
  }
  function tick(){
    try{
      var wrap=document.getElementById('ai-faxwrap'); if(!wrap) return;
      if(getComputedStyle(wrap).display==='none') return;
      if(document.getElementById('ch-faxai-btn')) return;
      var t=T[lang()]||T.de;
      var b=document.createElement('button'); b.id='ch-faxai-btn'; b.type='button'; b.textContent=t.b;
      b.style.cssText='display:block;width:100%;margin-top:8px;padding:9px 12px;border-radius:10px;border:1px solid rgba(212,168,74,.45);background:rgba(212,168,74,.1);color:#f0d493;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit';
      var s=document.createElement('div'); s.id='ch-faxai-stat'; s.style.cssText='font-size:11.5px;margin-top:6px;line-height:1.4';
      b.addEventListener('click',function(){ run(b,s); });
      wrap.appendChild(b); wrap.appendChild(s);
    }catch(e){}
  }
  try{ setInterval(tick,600); }catch(e){}
})();}catch(e){}
</script>
HTMLBLOCK;

$pos=strripos($src,'</body>');
if($pos===false) exit("</body> bulunamadi\n");
$new=substr($src,0,$pos).$block."\n".substr($src,$pos);
$tmp=tempnam(sys_get_temp_dir(),'fa').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-faxai-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_FAX_AIFIND')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ CH_FAX_AIFIND eklendi (".strlen($chk)." B)\n";
echo "✓ Fax alaninda '🔍 Faxnummer per KI finden' butonu — alici adindan AI numara bulur (6 dil).\n";
