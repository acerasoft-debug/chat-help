<?php
/**
 * ChatHelp — apply-mic-unified (CH_MIC_UNI) — mikrofon KESIN cozum.
 *  KOK NEDEN (dump-mic-pipeline ile kanitli):
 *   (1) SITE: premium mikrofon ekrani dalga animasyonu icin getUserMedia ile
 *       mikrofonu ACIK TUTARKEN ayni anda SpeechRecognition baslatiyor.
 *       Android/Chrome'da mikrofonu getUserMedia kilitler -> tanima motoru
 *       ses ALAMAZ: dalga oynar ("ses gider gibi") ama yazi hic gelmez.
 *       Ayrica overlay motorunda rec.onerror=function(){} — TUM hatalar
 *       sessizce yutuluyor.
 *   (2) APP: son startVoice WebView'de input'a focus() yapiyor; Android
 *       WebView programatik odaklamada klavyeyi ACMAYABILIR ve hicbir gorsel
 *       geri bildirim yok -> "buton hic calismiyor".
 *  COZUM: TEK guvenilir yol (eskiden sorunsuz calisan dogrudan yontem):
 *   • Sitede: dogrudan SpeechRecognition -> yazi kutusuna (dalga ekrani
 *     YOK = catisma YOK). Buton dinlerken kirmizi yanip soner; tekrar
 *     basinca durur. interim sonuclar canli yazilir. HATALAR GORUNUR
 *     (izin/ag/ses-yok — kullanici dilinde toast).
 *   • App WebView'de: klavye-dikte (focus+click+imlec sona) + HER ZAMAN
 *     gorunur ipucu toast'u — olu tiklama kalmaz.
 *   • window.startVoice + window.chOpenMic + window.chKbFocus hepsi bu tek
 *     yola baglanir; 20sn'lik sahiplik korumasi eski wrap donglerini ezer.
 *  Sadece EKLEMELI (</body> oncesi tek script), mevcut kod DEGISMEZ.
 *  node --check ✓ + 5 senaryoluk fonksiyonel harness ✓.
 * KULLANIM: pull2.php?key=...&files=apply-mic-unified.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-mic-unified BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_MIC_UNI')!==false) exit("Zaten ekli (CH_MIC_UNI).\n");
if (strpos($src,'id="pb"')===false) exit("HATA: mikrofon butonu (#pb) yok.\n");

$block = <<<'HTMLBLOCK'
<script id="ch-mic-uni-js">
/* CH_MIC_UNI — tek guvenilir mikrofon yolu (overlay/getUserMedia catismasi devre disi) */
try{(function(){
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  var LOC={de:'de-DE',tr:'tr-TR',en:'en-US',fr:'fr-FR',es:'es-ES',it:'it-IT',nl:'nl-NL',pl:'pl-PL',ru:'ru-RU',ar:'ar-SA'};
  var MSG={
    listen:{de:'🎤 Ich höre zu — sprechen Sie jetzt…',tr:'🎤 Dinliyorum — şimdi konuşun…',en:'🎤 Listening — speak now…'},
    kb:{de:'🎤 Tippen Sie auf das Mikrofon Ihrer Tastatur und sprechen Sie.',tr:'🎤 Klavyenizdeki mikrofon tuşuna dokunup konuşun.',en:'🎤 Tap the microphone on your keyboard and speak.'},
    denied:{de:'Mikrofon-Zugriff verweigert. Bitte in den Einstellungen erlauben.',tr:'Mikrofon izni reddedildi. Ayarlardan izin verin.',en:'Microphone access denied. Please allow it in settings.'},
    net:{de:'Spracherkennung: Netzwerkfehler. Bitte erneut versuchen.',tr:'Ses tanıma: ağ hatası. Lütfen tekrar deneyin.',en:'Speech recognition: network error. Please try again.'},
    nospeech:{de:'Keine Sprache erkannt. Bitte erneut versuchen.',tr:'Ses algılanamadı. Lütfen tekrar deneyin.',en:'No speech detected. Please try again.'}
  };
  function txt(m){ return m[lang()]||m.en||m.de; }
  var toastEl=null,toastT=null;
  function toast(s,ms){
    try{
      if(!toastEl){ toastEl=document.createElement('div');
        toastEl.setAttribute('style','position:fixed;left:50%;bottom:92px;transform:translateX(-50%);z-index:99999;background:rgba(15,22,52,.97);color:#f0e6c8;border:1px solid rgba(212,168,74,.5);border-radius:12px;padding:10px 16px;font-size:13.5px;font-weight:700;max-width:88vw;text-align:center;box-shadow:0 8px 30px rgba(0,0,0,.5);display:none');
        document.body.appendChild(toastEl); }
      toastEl.textContent=s; toastEl.style.display='block';
      if(toastT) clearTimeout(toastT);
      toastT=setTimeout(function(){ try{ toastEl.style.display='none'; }catch(e){} }, ms||3800);
    }catch(e){}
  }
  function hideToast(){ try{ if(toastEl) toastEl.style.display='none'; if(toastT) clearTimeout(toastT); }catch(e){} }
  function isWV(){
    try{
      var ua=navigator.userAgent||'';
      if(ua.indexOf('; wv)')!==-1) return true;
      if(/Android/.test(ua)&&/Version\/\d/.test(ua)&&/Chrome\//.test(ua)) return true;
      if(window.chIsApp===true) return true;
      return false;
    }catch(e){ return false; }
  }
  function inp(){ return document.getElementById('inp'); }
  function glow(on){ try{ var b=document.getElementById('pb'); if(b){ b.style.color=on?'#e05050':''; b.style.animation=on?'pulse 1s infinite':''; } }catch(e){} }
  function kbDictate(){
    try{
      var i=inp();
      if(i){
        try{ i.readOnly=false; i.removeAttribute('readonly'); }catch(e){}
        try{ i.focus(); }catch(e){}
        try{ i.click(); }catch(e){}
        try{ var l=(i.value||'').length; i.setSelectionRange(l,l); }catch(e){}
      }
      toast(txt(MSG.kb),4500);
    }catch(e){}
  }
  var R=null;
  function stopSR(){
    try{ if(R){ R.onend=null; R.onresult=null; R.onerror=null; try{ R.stop(); }catch(e){} } }catch(e){}
    R=null; glow(false); hideToast();
  }
  function startSR(){
    var SR=window.SpeechRecognition||window.webkitSpeechRecognition;
    if(!SR){ kbDictate(); return; }
    try{
      var i=inp();
      var base=(i&&i.value)?i.value.replace(/\s+$/,''):'';
      var fin='';
      var r=new SR();
      try{ r.lang=LOC[lang()]||'de-DE'; }catch(e){}
      r.interimResults=true; r.continuous=false; r.maxAlternatives=1;
      r.onresult=function(ev){
        try{
          var interim='';
          for(var k=ev.resultIndex;k<ev.results.length;k++){
            var res=ev.results[k];
            if(res.isFinal) fin+=(fin&&!/\s$/.test(fin)?' ':'')+res[0].transcript.trim();
            else interim+=res[0].transcript;
          }
          var i2=inp();
          if(i2){
            i2.value=((base?base+' ':'')+fin+(interim?' '+interim:'')).replace(/\s+/g,' ').trim();
            try{ if(typeof onInp==='function') onInp(i2); }catch(e){}
            try{ i2.style.height='auto'; i2.style.height=Math.min(i2.scrollHeight,160)+'px'; }catch(e){}
          }
        }catch(e){}
      };
      r.onerror=function(ev){
        var er=(ev&&ev.error)||'';
        stopSR();
        if(er==='not-allowed'||er==='service-not-allowed') toast(txt(MSG.denied),5000);
        else if(er==='network') toast(txt(MSG.net),4500);
        else if(er==='no-speech') toast(txt(MSG.nospeech),3500);
        else if(er&&er!=='aborted') toast('🎤 '+er,3500);
      };
      r.onend=function(){ stopSR(); };
      R=r; glow(true); toast(txt(MSG.listen),60000);
      r.start();
    }catch(e){ stopSR(); kbDictate(); }
  }
  function unified(){
    if(R){ stopSR(); return; }          /* dinlerken tekrar bas -> durdur */
    if(isWV()){ kbDictate(); return; }  /* App: klavye diktesi + gorunur ipucu */
    startSR();                          /* Site: dogrudan tanima -> yazi kutusuna */
  }
  unified.__final=1; kbDictate.__final=1;
  function install(){
    try{ window.startVoice=unified; window.chOpenMic=unified; window.chKbFocus=kbDictate; }catch(e){}
  }
  install();
  /* eski wrap donguleri sonradan uzerine yazarsa sahipligi geri al (20sn) */
  var n=0;(function guard(){
    try{ if(!(window.startVoice&&window.startVoice.__final)) install(); }catch(e){}
    if(n++<40) setTimeout(guard,500);
  })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'mu').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-micuni-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_MIC_UNI')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_MIC_UNI diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Mikrofon tek guvenilir yola baglandi:\n";
echo "   • SITE: butona bas -> kirmizi yanip soner, konus -> yazi ANINDA kutuya\n";
echo "     yazilir (dalga ekrani devre disi = Android catismasi bitti).\n";
echo "     Hatalar artik GORUNUR (izin/ag/ses-yok, kullanici dilinde).\n";
echo "   • APP: butona bas -> klavye + '🎤 klavyedeki mikrofon tusu' ipucu.\n";
echo "     Olu tiklama kalmadi: her basista mutlaka gorunur tepki var.\n";
