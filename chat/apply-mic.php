<?php
/**
 * ChatHelp — apply-mic (CH_MIC_FIX) — mikrofon (sesli giris) rahat + cok dilli.
 *  KOK NEDEN: startVoice icinde 'r.lang=UL===...' — UL tanimsizsa ReferenceError
 *  ile mic hic baslamiyor; hatalar sessizce yutuluyor; sadece 4 dil.
 *  COZUM: global startVoice'i override eder (buton onclick="startVoice()" yeni
 *  surumu cagirir). Dil ch_uilang'dan 12 dile eslenir; interim (canli) yazi;
 *  toggle (tekrar bas -> durdur); net hata mesajlari; SpeechRecognition yoksa
 *  klavye mikrofonuna yonlendirir. Metni input'a yazar (kullanici gozden gecirip
 *  gonderir). Mevcut kod degismez.
 * KULLANIM: pull2.php?key=...&files=apply-mic.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-mic BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_MIC_FIX')!==false) exit("Zaten ekli (CH_MIC_FIX).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-mic-fix">
/* CH_MIC_FIX — cok dilli, rahat sesli giris (startVoice override) */
try{(function(){
  function uil(){ try{ return (localStorage.getItem('ch_uilang')||(typeof UL!=='undefined'&&UL?UL:'de')||'de').slice(0,2).toLowerCase(); }catch(e){ return 'de'; } }
  function voiceLang(){
    var M={de:'de-DE',tr:'tr-TR',en:'en-US',fr:'fr-FR',es:'es-ES',it:'it-IT',nl:'nl-NL',pl:'pl-PL',pt:'pt-PT',sv:'sv-SE',ar:'ar-SA',ru:'ru-RU'};
    return M[uil()]||'de-DE';
  }
  function T(k){
    var l=uil();
    var D={
      nosupport:{de:'Sprachaufnahme wird in diesem Browser nicht unterstützt. Bitte tippen Sie auf das Mikrofon Ihrer Tastatur.',tr:'Bu tarayıcı sesli girişi desteklemiyor. Lütfen klavyenizdeki mikrofona dokunun.',en:'Voice input is not supported here. Please use your keyboard microphone.',fr:'La saisie vocale n’est pas prise en charge. Utilisez le micro de votre clavier.',es:'La entrada de voz no es compatible. Use el micrófono del teclado.'},
      denied:{de:'Bitte erlauben Sie den Mikrofon-Zugriff in den Browser-Einstellungen.',tr:'Lütfen tarayıcı ayarlarından mikrofon iznini verin.',en:'Please allow microphone access in your browser settings.',fr:'Autorisez l’accès au micro dans les réglages.',es:'Permita el acceso al micrófono en la configuración.'},
      nomic:{de:'Kein Mikrofon gefunden.',tr:'Mikrofon bulunamadı.',en:'No microphone found.',fr:'Aucun micro trouvé.',es:'No se encontró micrófono.'}
    };
    return (D[k]&&(D[k][l]||D[k].de))||'';
  }
  window.startVoice=function(){
    try{
      var pb=document.getElementById('pb');
      var inp=document.getElementById('inp');
      var Rec=window.SpeechRecognition||window.webkitSpeechRecognition;
      if(!Rec){ if(inp){ inp.focus(); } alert(T('nosupport')); return; }
      /* toggle: dinliyorsa durdur */
      if(window.__chRec){ try{ window.__chRec.stop(); }catch(e){} window.__chRec=null; if(pb){pb.style.color='';pb.style.animation='';} return; }
      var r=new Rec();
      try{ r.lang=voiceLang(); }catch(e){}
      r.interimResults=true; r.continuous=false; r.maxAlternatives=1;
      var base=(inp&&inp.value)?inp.value.replace(/\s+$/,''):'';
      var got='';
      if(pb){ pb.style.color='var(--rd,#e05050)'; pb.style.animation='pulse 1s infinite'; }
      r.onresult=function(e){
        var interim='',fin='';
        for(var i=e.resultIndex;i<e.results.length;i++){ var tr=e.results[i][0].transcript; if(e.results[i].isFinal) fin+=tr; else interim+=tr; }
        if(fin) got+=fin;
        if(inp){ inp.value=(base?base+' ':'')+(got+interim).replace(/\s+/g,' ').trim(); try{ if(typeof onInp==='function') onInp(inp); }catch(_){}; try{ inp.style.height='auto'; inp.style.height=Math.min(inp.scrollHeight,160)+'px'; }catch(_){}; }
      };
      r.onerror=function(ev){
        if(pb){ pb.style.color=''; pb.style.animation=''; } window.__chRec=null;
        var er=(ev&&ev.error)||'';
        if(er==='not-allowed'||er==='service-not-allowed') alert(T('denied'));
        else if(er==='audio-capture') alert(T('nomic'));
        /* no-speech / aborted: sessiz */
      };
      r.onend=function(){ if(pb){ pb.style.color=''; pb.style.animation=''; } window.__chRec=null; if(inp) try{ inp.focus(); }catch(_){}; };
      window.__chRec=r;
      r.start();
    }catch(e){
      try{ var pb2=document.getElementById('pb'); if(pb2){ pb2.style.color=''; pb2.style.animation=''; } }catch(_){}
      window.__chRec=null;
    }
  };
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'mc').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
$bk=@file_put_contents($file.'.bak-mic-'.date('Ymd-His'), $src);
if ($bk===false) echo "  ⚠ yedek yazilamadi — devam\n";
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_MIC_FIX')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_MIC_FIX diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Mikrofon: 12 dil (ch_uilang), canli yazi, toggle, net hata mesajlari,\n";
echo "   SpeechRecognition yoksa klavye mikrofonuna yonlendirme.\n";
