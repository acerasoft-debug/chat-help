<?php
/**
 * ChatHelp — apply-trvize-ai (CH_TRVIZE_AI) — "yari Turkce yari Ingilizce
 *  belge" duzeltmesi. KOK NEDEN (dump-trvize-builder ile kanitli): TR Vize
 *  Modulu'nun mektup belgeleri (cover/sponsor vb.) SABIT Ingilizce kaliba
 *  kullanicinin HAM Turkce yanitlarini yapistiriyor ("working as isveren",
 *  "ties: dukkan, ev") — AI cevirisi yok.
 *  COZUM (anchor'siz, tamamen EKLEMELI DOM son-islemcisi):
 *   • Belge goruntuleyicide bir mektup govdesi (.vf-letter) acildiginda
 *     metin yakalanir, "✨ Belge İngilizceye düzeltiliyor…" gosterilir ve
 *     AI'ya gonderilir: Turkce parcalar cevrilir, dilbilgisi duzeltilir,
 *     AD/PASAPORT/TARIHLER AYNEN korunur, markdown yasak, belgenin tamami
 *     tek dilde (Ingilizce) geri yazilir. Hata olursa orijinal metin kalir.
 *   • App WebView'inde "PDF olarak kaydet" yaninda "📋 Metni Kopyala"
 *     dugmesi belirir (WebView'de yazdirma calismayabilir — metin kopyalanip
 *     her yerde kullanilabilir).
 *  node --check ✓ + harness ✓ (ceviri akisi, koruma, WV kopyalama).
 * KULLANIM: pull2.php?key=...&files=apply-trvize-ai.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-trvize-ai BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_TRVIZE_AI')!==false) exit("Zaten ekli (CH_TRVIZE_AI).\n");
if (strpos($src,'vf-letter')===false) exit("HATA: TR vize goruntuleyici (vf-letter) yok.\n");

$block = <<<'HTMLBLOCK'
<script id="ch-trvize-ai-js">
/* CH_TRVIZE_AI — sablon mektuplari AI ile tek dile (Ingilizce) duzeltilir */
try{(function(){
  var API=function(){ return (typeof window.API!=='undefined'&&window.API)?window.API:'api.php'; };
  function isWV(){
    try{
      var ua=navigator.userAgent||'';
      return ua.indexOf('; wv)')!==-1 || (/Android/.test(ua)&&/Version\/\d/.test(ua)&&/Chrome\//.test(ua)) || window.chIsApp===true;
    }catch(e){ return false; }
  }
  function fixLetter(el){
    if(el.__ai) return; el.__ai=1;
    var orig=(el.textContent||'').trim();
    if(!orig || orig.length<80) return;
    var note=document.createElement('div');
    note.setAttribute('style','font-size:12px;font-weight:700;color:#b07d10;margin-bottom:10px');
    note.textContent='✨ Belge İngilizceye düzeltiliyor…';
    try{ el.parentNode.insertBefore(note,el); el.style.opacity='.45'; }catch(e){}
    var msg='Rewrite the following visa support document ENTIRELY in formal, correct English. '
      +'Translate every Turkish word or fragment into proper English (job titles, property lists like "dukkan, ev" -> "a shop and a house", country/consulate names like "Alman" -> "the Consulate General of Germany"). '
      +'Keep ALL names, passport numbers and dates EXACTLY as given. Keep the letter structure (To/Subject/salutation/paragraphs/closing/signature). '
      +'Fix grammar so every sentence reads naturally. No markdown, no asterisks, no brackets, no explanations. Output ONLY the corrected document text.\n\n---\n'+orig;
    fetch(API()+'?action=aichat',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({message:msg,history:[],provider:'claude',lang:'en',country:'DE'})})
     .then(function(r){ return r.json(); })
     .then(function(j){
        var t=(j&&j.reply)?String(j.reply).replace(/```[a-z]*\n?|```/g,'').replace(/\[\[\/?DOC\]\]/g,'').replace(/\*\*/g,'').trim():'';
        try{ note.remove(); }catch(e){}
        el.style.opacity='';
        if(t && t.length>60){ el.textContent=t; el.__aidone=1; }
     })
     .catch(function(){ try{ note.remove(); }catch(e){} el.style.opacity=''; });
  }
  function addCopyBtn(){
    if(!isWV()) return;
    try{
      document.querySelectorAll('button, .btn, [role="button"]').forEach(function(b){
        var t=(b.textContent||'').trim();
        if(b.__aicp) return;
        if(t.indexOf('PDF olarak kaydet')!==-1 || t.indexOf('Als PDF speichern')!==-1 || t.indexOf('Save as PDF')!==-1){
          b.__aicp=1;
          var c=document.createElement('button');
          c.textContent='📋 Metni Kopyala';
          c.setAttribute('style','display:block;width:100%;margin-top:6px;padding:11px;border-radius:11px;border:1.5px solid rgba(176,125,16,.5);background:transparent;color:inherit;font-weight:800;font-size:13px;cursor:pointer;font-family:inherit');
          c.onclick=function(){
            try{
              var letters=document.querySelectorAll('.vf-letter');
              var txt=''; letters.forEach(function(l){ txt+=(l.textContent||'')+'\n\n'; });
              if(!txt.trim()){ var a4=document.querySelector('.vfA4'); if(a4) txt=a4.textContent||''; }
              if(navigator.clipboard&&navigator.clipboard.writeText) navigator.clipboard.writeText(txt.trim());
              c.textContent='✓ Kopyalandı'; setTimeout(function(){ try{ c.textContent='📋 Metni Kopyala'; }catch(e){} },1600);
            }catch(e){}
          };
          if(b.parentNode) b.parentNode.insertBefore(c,b.nextSibling);
        }
      });
    }catch(e){}
  }
  function tick(){
    try{ document.querySelectorAll('.vf-letter').forEach(fixLetter); }catch(e){}
    addCopyBtn();
  }
  var n=0;(function loop(){ tick(); if(n++<7000) setTimeout(loop,800); })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'ta').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-trvai-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_TRVIZE_AI')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_TRVIZE_AI diskte (".strlen($chk)." bayt)\n";
echo "\n✓ TR Vize sablon mektuplari artik acildiginda otomatik duzeltilir:\n";
echo "   Turkce parcalar Ingilizceye cevrilir, dilbilgisi duzeltilir,\n";
echo "   ad/pasaport/tarihler aynen korunur — belge TAMAMEN tek dilde cikar.\n";
echo "   App'te ayrica '📋 Metni Kopyala' dugmesi eklenir (PDF kaydetme\n";
echo "   WebView'de kisitliysa metin her yerde kullanilabilir).\n";
