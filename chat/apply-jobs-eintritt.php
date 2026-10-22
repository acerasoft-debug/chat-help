<?php
/**
 * ChatHelp — apply-jobs-eintritt (CH_JOBS_EINTRITT)
 *  Job finden modalina "Ab wann verfügbar? (Eintrittstermin)" + opsiyonel
 *  "Gehaltsvorstellung" alanlari; doCoverLetter bunlari mektuba isler
 *  (baslangic tarihi verilmisse sona yakin net soylenir; maas SADECE
 *  verilmisse gecer). #30'un son eksik parcasi.
 * KULLANIM: pull-updates.php?files=apply-jobs-eintritt.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-jobs-eintritt BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$apiF = __DIR__.'/api.php';
$idxF = __DIR__.'/index.php';
$api = @file_get_contents($apiF);
$idx = @file_get_contents($idxF);
if ($api===false) exit("api.php okunamadi\n");
if ($idx===false) exit("index.php okunamadi\n");
if (strpos($api,'CH_JOBS_EINTRITT')!==false || strpos($idx,'CH_JOBS_EINTRITT')!==false) exit("Zaten ekli (CH_JOBS_EINTRITT).\n");

$fail=[];

/* ── [1] doCoverLetter: alanlari oku ── */
$o1 = "\$cvText  = trim((string)(\$b['cv'] ?? ''));";
$n1 = "\$cvText  = trim((string)(\$b['cv'] ?? ''));\n"
    . "    \$eintritt = trim((string)(\$b['eintritt'] ?? '')); /*CH_JOBS_EINTRITT*/\n"
    . "    \$gehalt   = trim((string)(\$b['gehalt'] ?? ''));";
$c=0; $api=str_replace($o1,$n1,$api,$c);
echo ($c===1?"  ✓ [1] doCoverLetter alanlari okur\n":"  ✗ [1] cvText anchor ($c)\n"); if($c!==1)$fail[]=1;

/* ── [2] sys: baslangic tarihi talimati ── */
$o2 = "Never invent qualifications the applicant did not state.";
$n2 = "Never invent qualifications the applicant did not state. If a start date (Eintrittstermin) is given, state it clearly near the end of the letter; mention a salary expectation ONLY if one is given.";
$c=0; $api=str_replace($o2,$n2,$api,$c);
echo ($c===1?"  ✓ [2] sys Eintrittstermin talimati\n":"  ✗ [2] sys anchor ($c)\n"); if($c!==1)$fail[]=2;

/* ── [3] usr: verileri prompta ekle ── */
$o3 = "\\nADDRESS: {\$addr}\\n\\nAPPLICANT CV / BACKGROUND (use ONLY these real facts, do not invent):\\n";
$n3 = "\\nADDRESS: {\$addr}\".(\$eintritt!==''?\"\\nAVAILABLE FROM (Eintrittstermin): {\$eintritt}\":\"\").(\$gehalt!==''?\"\\nSALARY EXPECTATION: {\$gehalt}\":\"\").\"\\n\\nAPPLICANT CV / BACKGROUND (use ONLY these real facts, do not invent):\\n";
$c=0; $api=str_replace($o3,$n3,$api,$c);
echo ($c===1?"  ✓ [3] usr prompt Eintrittstermin/Gehalt\n":"  ✗ [3] usr anchor ($c)\n"); if($c!==1)$fail[]=3;

/* ── [4] genLetter payload ── */
$o4 = "body:JSON.stringify({job:job,profile:(window.P||{}),lang:lang,cv:cv})";
$n4 = "body:JSON.stringify({job:job,profile:(window.P||{}),lang:lang,cv:cv,eintritt:((document.getElementById(\"jb-eintritt\")||{}).value||\"\"),gehalt:((document.getElementById(\"jb-gehalt\")||{}).value||\"\")})";
$c=0; $idx=str_replace($o4,$n4,$idx,$c);
echo ($c===1?"  ✓ [4] genLetter payload alanlari tasir\n":"  ✗ [4] payload anchor ($c)\n"); if($c!==1)$fail[]=4;

/* ── [5] jobs paneline alanlari enjekte et (script) ── */
$bundle = <<<'HTML'
<script id="ch-jobs-eintritt-js">
/* CH_JOBS_EINTRITT — jobs modalina Eintrittstermin + Gehalt alanlari */
try{(function(){
  var PH={
    de:["Ab wann verfügbar? (z. B. ab sofort / 01.09.)","Gehaltsvorstellung (optional)"],
    tr:["Ne zaman başlayabilirsiniz? (örn. hemen)","Maaş beklentisi (opsiyonel)"],
    en:["Available from (e.g. immediately / 01.09.)","Salary expectation (optional)"],
    fr:["Disponible à partir de (p. ex. immédiatement)","Prétentions salariales (facultatif)"],
    es:["Disponible desde (p. ej. de inmediato)","Expectativa salarial (opcional)"],
    it:["Disponibile da (ad es. subito)","Aspettativa salariale (facoltativo)"]
  };
  function uil(){ try{ return localStorage.getItem("ch_uilang")||"de"; }catch(e){ return "de"; } }
  function inject(){
    try{
      var cv=document.getElementById("jb-cv");
      if(!cv||document.getElementById("jb-eintritt")) return;
      var ph=PH[uil()]||PH.de;
      var w=document.createElement("div");
      w.style.cssText="display:flex;gap:8px;margin-top:8px";
      var i1=document.createElement("input"); i1.id="jb-eintritt"; i1.placeholder=ph[0];
      var i2=document.createElement("input"); i2.id="jb-gehalt"; i2.placeholder=ph[1];
      [i1,i2].forEach(function(i){ i.className=cv.className||""; i.style.cssText="flex:1;min-width:0;height:auto"; });
      w.appendChild(i1); w.appendChild(i2);
      cv.parentNode.insertBefore(w, cv.nextSibling);
    }catch(e){}
  }
  inject(); setInterval(inject,1200);
})();}catch(e){}
</script>
HTML;
$pos = strrpos($idx,'</body>');
if ($pos===false) { echo "  ✗ [5] </body> yok\n"; $fail[]=5; }
else { $idx = substr($idx,0,$pos).$bundle."\n".substr($idx,$pos); echo "  ✓ [5] alan enjeksiyonu eklendi\n"; }

if ($fail) { echo "\nHATA: adimlar eslesmedi: ".implode(', ',$fail)." — HICBIR dosya degistirilmedi.\n"; exit; }

foreach ([[$apiF,$api],[$idxF,$idx]] as $pair) {
    $tmp = tempnam(sys_get_temp_dir(),'je').'.php';
    file_put_contents($tmp,$pair[1]);
    $lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
    if ($rc!==0) { echo "\nLINT HATASI (".basename($pair[0]).") — HICBIR dosya degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
}
file_put_contents($apiF.'.bak-jbeintritt-'.date('Ymd-His'), @file_get_contents($apiF));
file_put_contents($idxF.'.bak-jbeintritt-'.date('Ymd-His'), @file_get_contents($idxF));
file_put_contents($apiF,$api);
file_put_contents($idxF,$idx);
echo "\n✓ CH_JOBS_EINTRITT uygulandi. Bewerbung mektubu artik baslangic tarihini (ve verilmisse maasi) icerir.\n";
