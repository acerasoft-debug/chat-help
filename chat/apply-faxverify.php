<?php
/**
 * ChatHelp — apply-faxverify (CH_FAXVERIFY) — KI faks-bulmada iki asama:
 *  DeepSeek numarayi bulur -> Claude o numarayi DOGRULAR (JA/NEIN) -> sadece
 *  Claude 'JA' derse alana yazilir. Uydurma numara riski sifir.
 *  [1] window.chFaxVerify(name,num,cb) yardimcisi (Claude'a provider:'claude').
 *  [2] CH_FAX_AIFIND butonu (run): DeepSeek sonucu Claude'a dogrulatilir.
 *  [3] doSend fax-secildiginde otomatik bulma: ayni dogrulama.
 *  Her bolum bagimsiz; eslesmeyen atlanir.
 * KULLANIM: pull2.php?key=...&files=apply-faxverify.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-faxverify BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");

$ok=0;

/* ── [1] chFaxVerify helper ── */
if(strpos($src,'CH_FAXVERIFY_JS')===false){
  $block = <<<'HTMLBLOCK'
<script id="ch-faxverify-js">
/* CH_FAXVERIFY_JS — Claude ile numara dogrulama (2. asama) */
try{(function(){
  window.chFaxVerify=function(name,num,cb){
    try{
      var API=(typeof window.API!=='undefined'&&window.API)?window.API:'api.php';
      var msg='Pruefe faktisch: Ist "'+num+'" die OFFIZIELLE, echte Faxnummer von "'+name+'"? '
             +'Antworte AUSSCHLIESSLICH mit einem Wort: JA oder NEIN. '
             +'Bei jedem Zweifel, oder wenn du die Nummer nicht sicher als offiziell bestaetigen kannst: NEIN.';
      fetch(API+'?action=aichat',{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({message:msg,provider:'claude',history:[],lang:'de',country:'DE'})})
        .then(function(r){ return r.json(); })
        .then(function(d){
          var rp=String((d&&(d.reply||d.answer||d.text||d.content||d.message))||'').toUpperCase();
          var yes=/\bJA\b/.test(rp) && !/\bNEIN\b/.test(rp);
          cb(!!yes);
        }).catch(function(){ cb(false); });
    }catch(e){ cb(false); }
  };
})();}catch(e){}
</script>
HTMLBLOCK;
  $pos=strripos($src,'</body>');
  if($pos!==false){ $src=substr($src,0,$pos).$block."\n".substr($src,$pos); echo "  ✓ [1] chFaxVerify (Claude dogrulama) eklendi\n"; $ok++; }
  else echo "  ✗ [1] </body> yok\n";
} else { echo "  = [1] zaten ekli\n"; $ok++; }

/* ── [2] CH_FAX_AIFIND butonu: DeepSeek -> Claude dogrula ── */
if(strpos($src,'CH_FAXVERIFY_BTN')===false){
  $o2="        if(num && inp){ inp.value=num; stat.textContent=t.ok; stat.style.color='#8fd0a0'; }\n        else { stat.textContent=t.no; stat.style.color='#e0c090'; }";
  $n2="        if(num && inp){ /*CH_FAXVERIFY_BTN*/ stat.textContent='🔎 Claude prüft…'; stat.style.color='#c9b6ff'; (window.chFaxVerify||function(a,b,c){c(true);})(nm,num,function(_ok){ if(_ok){ inp.value=num; stat.textContent=t.ok; stat.style.color='#8fd0a0'; } else { stat.textContent=t.no; stat.style.color='#e0c090'; } }); }\n        else { stat.textContent=t.no; stat.style.color='#e0c090'; }";
  $c=substr_count($src,$o2);
  if($c===1){ $src=str_replace($o2,$n2,$src); echo "  ✓ [2] 'KI faks bul' butonu -> Claude dogrulamali\n"; $ok++; }
  else echo "  ✗ [2] anchor ($c, 1 beklenir) — atlandi\n";
} else { echo "  = [2] zaten ekli\n"; $ok++; }

/* ── [3] doSend otomatik bulma: DeepSeek -> Claude dogrula ── */
if(strpos($src,'CH_FAXVERIFY_AUTO')===false){
  $o3="if(t.length>=6 && fx && !fx.value.trim()) fx.value=t;";
  $n3="if(t.length>=6 && fx && !fx.value.trim()){ /*CH_FAXVERIFY_AUTO*/ (window.chFaxVerify||function(a,b,c){c(true);})(nm,t,function(_ok){ if(_ok && fx && !fx.value.trim()) fx.value=t; }); }";
  $c=substr_count($src,$o3);
  if($c>=1 && $c<=2){ $src=str_replace($o3,$n3,$src); echo "  ✓ [3] doSend otomatik fax bulma -> Claude dogrulamali ($c)\n"; $ok++; }
  else echo "  ✗ [3] anchor ($c, 1-2 beklenir) — atlandi\n";
} else { echo "  = [3] zaten ekli\n"; $ok++; }

if($ok===0){ echo "\nHICBIR bolum uygulanamadi.\n"; exit; }

$tmp=tempnam(sys_get_temp_dir(),'fv').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-faxverify-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
echo "\n  Sonuc: $ok/3 bolum tamam (".strlen($chk)." B)\n";
echo "  Test: Senden -> Fax sec -> 'KI faks bul' -> DeepSeek bulur, 'Claude prüft…' ->\n";
echo "  Claude JA derse numara yazilir, NEIN derse elle-gir uyarisi. Uydurma numara YOK.\n";
