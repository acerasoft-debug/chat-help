<?php
/**
 * ChatHelp — apply-lang-leak (CH_LANG_LEAK)
 *  "Almanca secilmedikce hicbir yerde Almanca cikmasin" sizintilarini kapatir:
 *   [1] Serbest metin alani ("Weitere Angaben in eigenen Worten") SABIT
 *       Almancaydi; i18n cevirisini altina gold span olarak EKLIYORDU ->
 *       Almanca + ceviri ikisi birden gorunuyordu. Artik alan dogrudan
 *       ARAYUZ DILINDE uretilir (12 dil), i18n'e dokundurulmaz (data-i18n-done)
 *       -> yalnizca secili dil gorunur, Almanca sizmaz.
 *   [2] Ulke izgarasi (.co) vurgusu secili hukuk ulkesiyle (CC) senkron
 *       degildi (Turkiye secili ama Almanya vurgulu kaliyordu). Artik her
 *       an .co vurgusu = CC.
 * KULLANIM: pull-updates.php?files=apply-lang-leak.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-lang-leak BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_LANG_LEAK')!==false) exit("Zaten ekli (CH_LANG_LEAK).\n");

$fail=[];

/* ── [1] serbest metin alani -> native dil ── */
$o1 = "    var w=document.createElement('div'); w.className='fi ch-freitext';\n"
    . "    w.innerHTML='<label data-de=\"Weitere Angaben in eigenen Worten (optional)\">Weitere Angaben in eigenen Worten <span style=\"color:var(--t4);font-size:9px\">(optional)</span></label>'\n"
    . "      +'<textarea data-k=\"_freitext\" rows=\"3\" placeholder=\"Beschreiben Sie Ihre Situation — auch in Ihrer Sprache.\"></textarea>';\n"
    . "    last.parentNode.insertBefore(w, last.nextSibling);\n"
    . "    if(lang!=='de') w.querySelector('label').removeAttribute('data-i18n-done');";
$n1 = "    var _FL={de:[\"Weitere Angaben in eigenen Worten\",\"Beschreiben Sie Ihre Situation.\"],tr:[\"Kendi kelimelerinizle ek açıklama\",\"Durumunuzu kendi dilinizde anlatın.\"],en:[\"Additional details in your own words\",\"Describe your situation in your own language.\"],es:[\"Información adicional en sus propias palabras\",\"Describa su situación en su idioma.\"],fr:[\"Informations complémentaires (vos propres mots)\",\"Décrivez votre situation dans votre langue.\"],it:[\"Ulteriori dettagli con parole tue\",\"Descrivi la tua situazione nella tua lingua.\"],nl:[\"Aanvullende informatie in eigen woorden\",\"Beschrijf uw situatie in uw eigen taal.\"],pl:[\"Dodatkowe informacje własnymi słowami\",\"Opisz swoją sytuację w swoim języku.\"],sv:[\"Ytterligare uppgifter med egna ord\",\"Beskriv din situation på ditt språk.\"],pt:[\"Informação adicional por palavras suas\",\"Descreva a sua situação no seu idioma.\"],ar:[\"تفاصيل إضافية بكلماتك\",\"صف وضعك بلغتك.\"],ru:[\"Дополнительно своими словами\",\"Опишите ситуацию на вашем языке.\"]}; /*CH_LANG_LEAK*/\n"
    . "    var _fl=_FL[lang]||_FL.en;\n"
    . "    var w=document.createElement('div'); w.className='fi ch-freitext';\n"
    . "    w.innerHTML='<label data-i18n-done=\"1\">'+_fl[0]+' <span style=\"color:var(--t4);font-size:9px\">(optional)</span></label>'\n"
    . "      +'<textarea data-k=\"_freitext\" rows=\"3\" placeholder=\"'+_fl[1]+'\"></textarea>';\n"
    . "    last.parentNode.insertBefore(w, last.nextSibling);";
$c=0; $src=str_replace($o1,$n1,$src,$c);
echo ($c===1?"  ✓ [1] serbest metin alani artik native dilde (Almanca sizmaz)\n":"  ✗ [1] freitext anchor ($c)\n"); if($c!==1)$fail[]=1;

/* ── [2] ulke izgarasi vurgusu = CC ── */
$bundle = <<<'HTML'
<script id="ch-cogrid-js">
/* CH_LANG_LEAK — ulke izgarasi (.co) vurgusu her an secili hukuk ulkesiyle (CC) senkron */
try{(function(){
  function sync(){
    try{
      var cc=null; try{ cc=(typeof CC!=="undefined"&&CC)?CC:null; }catch(e){}
      if(!cc){ try{ cc=(JSON.parse(localStorage.getItem("ch_pref")||"{}")||{}).cc; }catch(e){} }
      if(!cc) return;
      document.querySelectorAll(".co[data-cc]").forEach(function(el){
        el.classList.toggle("on", el.getAttribute("data-cc")===cc);
      });
    }catch(e){}
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",sync); else sync();
  setInterval(sync, 1000);
})();}catch(e){}
</script>
HTML;
$pos = strrpos($src,'</body>');
if ($pos===false) { echo "  ✗ [2] </body> yok\n"; $fail[]=2; }
else { $src = substr($src,0,$pos).$bundle."\n".substr($src,$pos); echo "  ✓ [2] ulke izgarasi vurgusu CC'ye senkronlandi\n"; }

if ($fail) { echo "\nHATA: adimlar eslesmedi: ".implode(', ',$fail)." — index.php DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(),'ll').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-langleak-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_LANG_LEAK uygulandi. Almanca artik yalnizca dil=de iken gorunur; ulke izgarasi CC ile senkron.\n";
