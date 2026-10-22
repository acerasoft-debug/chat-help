<?php
/**
 * ChatHelp — apply-sendok (CH_SENDOK) — basarili gonderimden sonra PREMIUM onay ekrani
 *  (Fax/Einschreiben/Brief'e gore baslik, kullanicinin dilinde) + gonderim sirasinda
 *  animasyonlu haken/loader. window.chSendSuccess() + window.chSendLoading() global;
 *  reallySend/doHome basari-alert'i bunlarla degistirilir (UI-only, gonderim mantigi ayni).
 * KULLANIM: pull2.php?key=...&files=apply-sendok.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-sendok BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_SENDOK')!==false) exit("Zaten ekli (CH_SENDOK).\n");

/* ── [1] global overlay (style + script) ── */
$block = <<<'HTMLBLOCK'
<style id="ch-sendok-css">
#ch-sok-ov,#ch-sld-ov{position:fixed;inset:0;z-index:100000;display:flex;flex-direction:column;align-items:center;justify-content:center;
  background:radial-gradient(120% 90% at 50% 35%,#1a1620 0%,#0b0910 70%,#07060c 100%);
  font-family:-apple-system,'Segoe UI',Inter,Roboto,Arial,sans-serif;padding:26px 20px;text-align:center;opacity:0;animation:chsokIn .28s ease forwards}
@keyframes chsokIn{to{opacity:1}}
.ch-sok-circ{width:132px;height:132px;border-radius:50%;background:radial-gradient(circle at 50% 40%,#f3d27a,#d4a84a 70%);
  display:flex;align-items:center;justify-content:center;box-shadow:0 0 60px rgba(212,168,74,.45),0 14px 40px rgba(0,0,0,.5);margin-bottom:26px;animation:chsokPop .5s cubic-bezier(.2,1.3,.4,1) both}
@keyframes chsokPop{0%{transform:scale(.4);opacity:0}60%{transform:scale(1.08)}100%{transform:scale(1);opacity:1}}
.ch-sok-circ svg{width:64px;height:64px}
.ch-sok-circ svg path{stroke:#141018;stroke-width:7;stroke-linecap:round;stroke-linejoin:round;fill:none;
  stroke-dasharray:60;stroke-dashoffset:60;animation:chsokDraw .5s .28s ease forwards}
@keyframes chsokDraw{to{stroke-dashoffset:0}}
.ch-sok-t{font-size:26px;font-weight:800;color:#fff;letter-spacing:-.3px;margin-bottom:8px;max-width:340px;line-height:1.18}
.ch-sok-s{font-size:14px;color:#a49bb0;margin-bottom:26px;max-width:320px;line-height:1.5}
.ch-sok-card{width:100%;max-width:380px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.09);border-radius:20px;padding:8px 20px}
.ch-sok-row{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:15px 0;border-bottom:1px solid rgba(255,255,255,.07)}
.ch-sok-row:last-child{border-bottom:none}
.ch-sok-k{font-size:13.5px;color:#8b8394;font-weight:600}
.ch-sok-v{font-size:14px;color:#f2eef8;font-weight:700;text-align:right}
.ch-sok-v.gold{color:#e6b955}
.ch-sok-foot{margin-top:26px;font-size:12.5px;color:#8b8394}
.ch-sok-foot b{color:#d4a84a}
.ch-sok-hint{margin-top:18px;font-size:11px;color:#6b6474}
/* loader */
.ch-sld-ring{width:120px;height:120px;position:relative;margin-bottom:24px}
.ch-sld-ring svg{width:120px;height:120px;transform:rotate(-90deg)}
.ch-sld-ring circle{fill:none;stroke-width:6}
.ch-sld-ring .bg{stroke:rgba(212,168,74,.16)}
.ch-sld-ring .fg{stroke:url(#chsldg);stroke-linecap:round;stroke-dasharray:200;stroke-dashoffset:150;animation:chsldSpin 1s linear infinite}
@keyframes chsldSpin{to{transform:rotate(360deg)}}
.ch-sld-ring .fg{transform-origin:50% 50%;animation:chsldSpin 1s linear infinite}
.ch-sld-chk{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:40px;color:#d4a84a;opacity:.9;animation:chsldPulse 1.2s ease-in-out infinite}
@keyframes chsldPulse{0%,100%{opacity:.45;transform:scale(.92)}50%{opacity:1;transform:scale(1)}}
.ch-sld-t{font-size:16px;font-weight:800;color:#fff}
.ch-sld-s{font-size:12.5px;color:#9a92a6;margin-top:6px}
@media (prefers-reduced-motion: reduce){
  #ch-sok-ov,#ch-sld-ov,.ch-sok-circ,.ch-sok-circ svg path,.ch-sld-ring .fg,.ch-sld-chk{animation:none!important;opacity:1!important;stroke-dashoffset:0!important}
}
</style>
<script id="ch-sendok-js">
/* CH_SENDOK — premium gonderim-basari ekrani + loader (cok dilli) */
try{(function(){
  function L(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2);}catch(e){ return 'de'; } }
  function pick(m){ var l=L(); return m[l]||m.de; }
  function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
  function pages(txt){ try{ var t=String(txt||''); if(!t) return 1; var ln=0; t.split('\n').forEach(function(x){ ln+=Math.max(1,Math.ceil(x.length/90)); }); return Math.max(1,Math.ceil((ln+8)/42)); }catch(e){ return 1; } }
  function dstr(){ try{ var d=new Date(); function z(n){return('0'+n).slice(-2);} return z(d.getDate())+'.'+z(d.getMonth()+1)+'.'+d.getFullYear()+' · '+z(d.getHours())+':'+z(d.getMinutes()); }catch(e){ return ''; } }

  window.chSendLoading=function(on){
    try{
      var ov=document.getElementById('ch-sld-ov');
      if(!on){ if(ov) ov.remove(); return; }
      if(ov) return;
      var T={de:['Wird gesendet…','Zustellung wird vorbereitet'],tr:['Gönderiliyor…','İletim hazırlanıyor'],en:['Sending…','Preparing delivery'],fr:['Envoi…','Préparation de l’envoi'],es:['Enviando…','Preparando el envío'],it:['Invio…','Preparazione invio']};
      var tt=T[L()]||T.de;
      ov=document.createElement('div'); ov.id='ch-sld-ov';
      ov.innerHTML='<div class="ch-sld-ring"><svg viewBox="0 0 120 120"><defs><linearGradient id="chsldg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#d4a84a"/><stop offset="1" stop-color="#f3d27a"/></linearGradient></defs><circle class="bg" cx="60" cy="60" r="52"/><circle class="fg" cx="60" cy="60" r="52"/></svg><div class="ch-sld-chk">✓</div></div><div class="ch-sld-t">'+esc(tt[0])+'</div><div class="ch-sld-s">'+esc(tt[1])+'</div>';
      document.body.appendChild(ov);
      try{ clearTimeout(window.__chSldTo); window.__chSldTo=setTimeout(function(){ try{ var x=document.getElementById('ch-sld-ov'); if(x)x.remove(); }catch(e){} },30000); }catch(e){}
    }catch(e){}
  };

  window.chSendSuccess=function(o){
    try{
      o=o||{}; try{ window.chSendLoading(false); }catch(e){}
      var act=o.act||'send_letter', reg=o.reg||'';
      var method = act==='send_fax'?'fax':(reg?'einschr':'brief');
      var TT={
        fax:{de:'Fax erfolgreich gesendet',tr:'Faks başarıyla gönderildi',en:'Fax sent successfully',fr:'Fax envoyé avec succès',es:'Fax enviado correctamente',it:'Fax inviato con successo',nl:'Fax succesvol verzonden',pl:'Faks wysłany pomyślnie',pt:'Fax enviado com sucesso'},
        einschr:{de:'Einschreiben versendet',tr:'Taahhütlü gönderildi',en:'Registered letter sent',fr:'Recommandé envoyé',es:'Carta certificada enviada',it:'Raccomandata inviata',nl:'Aangetekend verzonden',pl:'List polecony wysłany',pt:'Carta registada enviada'},
        brief:{de:'Brief versendet',tr:'Mektup gönderildi',en:'Letter sent',fr:'Courrier envoyé',es:'Carta enviada',it:'Lettera inviata',nl:'Brief verzonden',pl:'List wysłany',pt:'Carta enviada'}
      };
      var SUB={de:'Dein Dokument wurde offiziell zugestellt.',tr:'Belgen resmî olarak iletildi.',en:'Your document was officially delivered.',fr:'Votre document a été officiellement remis.',es:'Tu documento fue entregado oficialmente.',it:'Il tuo documento è stato consegnato ufficialmente.',nl:'Je document is officieel bezorgd.',pl:'Twój dokument został oficjalnie doręczony.',pt:'O teu documento foi entregue oficialmente.'};
      var EMP={de:'Empfänger',tr:'Alıcı',en:'Recipient',fr:'Destinataire',es:'Destinatario',it:'Destinatario',nl:'Ontvanger',pl:'Odbiorca',pt:'Destinatário'};
      var DAT={de:'Datum',tr:'Tarih',en:'Date',fr:'Date',es:'Fecha',it:'Data',nl:'Datum',pl:'Data',pt:'Data'};
      var SEI={de:'Seiten',tr:'Sayfa',en:'Pages',fr:'Pages',es:'Páginas',it:'Pagine',nl:"Pagina's",pl:'Strony',pt:'Páginas'};
      var STA={de:'Status',tr:'Durum',en:'Status',fr:'Statut',es:'Estado',it:'Stato',nl:'Status',pl:'Status',pt:'Estado'};
      var ZU={de:'Zugestellt',tr:'İletildi',en:'Delivered',fr:'Remis',es:'Entregado',it:'Consegnato',nl:'Bezorgd',pl:'Doręczono',pt:'Entregue'};
      var FT={de:'Versendet mit',tr:'Gönderildi:',en:'Sent with',fr:'Envoyé avec',es:'Enviado con',it:'Inviato con',nl:'Verzonden met',pl:'Wysłano przez',pt:'Enviado com'};
      var HT={de:'Tippen zum Schließen',tr:'Kapatmak için dokun',en:'Tap to close',fr:'Toucher pour fermer',es:'Toca para cerrar',it:'Tocca per chiudere',nl:'Tik om te sluiten',pl:'Dotknij, aby zamknąć',pt:'Toque para fechar'};
      function P(m){ return esc(m[L()]||m.de); }
      var rec=String(o.rec||'').split('\n')[0].trim();
      var np=o.pages||pages(o.txt);
      var old=document.getElementById('ch-sok-ov'); if(old) old.remove();
      var ov=document.createElement('div'); ov.id='ch-sok-ov';
      var rows='';
      if(rec) rows+='<div class="ch-sok-row"><span class="ch-sok-k">'+P(EMP)+'</span><span class="ch-sok-v">'+esc(rec)+'</span></div>';
      rows+='<div class="ch-sok-row"><span class="ch-sok-k">'+P(DAT)+'</span><span class="ch-sok-v">'+esc(dstr())+'</span></div>';
      rows+='<div class="ch-sok-row"><span class="ch-sok-k">'+P(SEI)+'</span><span class="ch-sok-v">'+np+' '+P(SEI)+'</span></div>';
      rows+='<div class="ch-sok-row"><span class="ch-sok-k">'+P(STA)+'</span><span class="ch-sok-v gold">'+P(ZU)+' ✓</span></div>';
      ov.innerHTML='<div class="ch-sok-circ"><svg viewBox="0 0 52 52"><path d="M14 27l8 8 16-18"/></svg></div>'
        +'<div class="ch-sok-t">'+P(TT[method])+'</div>'
        +'<div class="ch-sok-s">'+P(SUB)+'</div>'
        +'<div class="ch-sok-card">'+rows+'</div>'
        +'<div class="ch-sok-foot">'+P(FT)+' <b>chat-help.com</b></div>'
        +'<div class="ch-sok-hint">'+P(HT)+'</div>';
      ov.addEventListener('click',function(){ try{ ov.remove(); }catch(e){} });
      document.body.appendChild(ov);
      try{ clearTimeout(window.__chSokTo); window.__chSokTo=setTimeout(function(){ try{ var x=document.getElementById('ch-sok-ov'); if(x)x.remove(); }catch(e){} },9000); }catch(e){}
    }catch(e){ try{ alert('✓'); }catch(e2){} }
  };
})();}catch(e){}
</script>
HTMLBLOCK;
$pos=strripos($src,'</body>');
if($pos===false) exit("</body> yok\n");
$src=substr($src,0,$pos).$block."\n".substr($src,$pos);
echo "  ✓ overlay (chSendSuccess + chSendLoading) eklendi\n";

/* ── [2] reallySend/doHome: loader + basari ekrani (str_replace) ── */
$cA=0; $src=str_replace(
  "btn.textContent=T('sending'); btn.disabled=true;",
  "btn.textContent=T('sending'); btn.disabled=true; try{window.chSendLoading&&window.chSendLoading(true);}catch(e){}",
  $src,$cA);
echo "  ".($cA>0?"✓":"=")." [A] loader-goster ($cA)\n";

$cB=0; $src=str_replace(
  "if(j&&j.ok){ close(); try{ alert(T('sentok')); }catch(e){} }",
  "if(j&&j.ok){ close(); try{ window.chSendSuccess&&window.chSendSuccess({act:(typeof _act!=='undefined'?_act:'send_letter'),reg:(typeof _reg!=='undefined'?_reg:''),rec:(typeof rec!=='undefined'?rec:(typeof own!=='undefined'?own:'')),txt:(typeof txt!=='undefined'?txt:'')}); }catch(e){ try{alert(T('sentok'));}catch(e2){} } }",
  $src,$cB);
echo "  ".($cB>0?"✓":"✗")." [B] basari-ekrani ($cB)\n";

$cC=0; $src=str_replace(
  "btn.disabled=false;",
  "btn.disabled=false; try{window.chSendLoading&&window.chSendLoading(false);}catch(e){}",
  $src,$cC);
echo "  ".($cC>0?"✓":"=")." [C] loader-gizle ($cC)\n";

if($cB<1){ echo "\nHATA: [B] basari-satiri bulunamadi — DEGISTIRILMEDI (overlay eklendi ama baglanmadi).\n"; }

$tmp=tempnam(sys_get_temp_dir(),'so').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-sendok-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_SENDOK')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_SENDOK diskte (".strlen($chk)." B)\n";
echo "  Test: belge -> Senden -> Fax/Einschreiben sec -> gonderim sirasinda donen haken;\n";
echo "        basarili olunca kullanicinin dilinde premium 'Gesendet' ekrani + detay karti.\n";
