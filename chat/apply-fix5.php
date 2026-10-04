<?php
/**
 * ChatHelp — apply-fix5 (CH_FIX5) — ekran goruntusu hatalari toplu duzeltme
 *  [1] Sozlesmeler CHAT katalogundan cikar — sadece Vertrag-Studio modulunde
 *      (CATS.vertraege enjeksiyonu kaldirilir; 'undefined' etiketi de biter).
 *  [2] [[DOC]] placeholder restore: profil localStorage (ch_prof_v3) fallback
 *      + **markdown** yildizlari temizlenir (kartta ham gorunuyordu).
 *  [3] Chat 'Als PDF speichern' ARTIK GERCEKTEN PDF indirir (jsPDF/makePDF;
 *      mobilde window.open engellendigi icin calismiyordu).
 *  [4] Odeme donusu (?session_id=...): son belge sessionStorage'dan geri gelir.
 *  [5] MENU OYNAMASI KOK NEDEN: enforceOrder dizisi nav-jobs/nav-trvisa/nav-hub
 *      icermiyordu -> her yuklemede kayiyorlardi. Diziye sabit sirayla eklendi.
 *  [6] vtr_ uretimi: kullanicinin sectigi/saydigi Zusatzklausel'ler tam
 *      paragraf olarak sozlesmeye eklenir.
 *  [7] [[DOC]] kart notu duzeltildi (profil bossa yaniltici olmasin).
 *  + TESHIS (sadece okur): $sid turetimi + STORE_DIR (Antraege kaliciligi).
 * KULLANIM: pull-updates.php?files=apply-fix5.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fix5 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$idxF = __DIR__.'/index.php';
$apiF = __DIR__.'/api.php';
$idx = @file_get_contents($idxF);
$api = @file_get_contents($apiF);
if ($idx===false) exit("index.php okunamadi\n");
if ($api===false) exit("api.php okunamadi\n");
if (strpos($idx,'CH_FIX5')!==false) exit("Zaten ekli (CH_FIX5).\n");

/* ── TESHIS: Antraege kaliciligi icin $sid/STORE_DIR (sadece okur) ── */
echo "-- TESHIS: \$sid & STORE_DIR --\n";
if (preg_match('/\$sid\s*=\s*[^;]{0,220};/s', $api, $m)) echo "  sid: ".trim($m[0])."\n"; else echo "  (sid atamasi bulunamadi)\n";
if (preg_match("/define\('STORE_DIR'[^;]*;/", $api, $m)) echo "  ".trim($m[0])."\n";
foreach (['setcookie','ch_sid','session_start','Authorization'] as $k) echo "  '$k' -> ".substr_count($api,$k)." kez\n";
$p = strpos($api,'file_put_contents(STORE_DIR');
if ($p!==false) echo "  kayit yeri: ...".substr($api,max(0,$p-160),340)."...\n";
echo "\n";

$fail = [];

/* ── [1] Sozlesmeler chat katalogundan cikar ── */
$o1 = "if(typeof CATS!=='undefined'){ if(!CATS.vertraege) CATS.vertraege={n:'Verträge',ic:'📜',docs:[]}; for(var k in V){ if(CATS.vertraege.docs.indexOf(k)<0) CATS.vertraege.docs.push(k); } }";
$n1 = "/*CH_FIX5: Verträge nur im Vertrag-Studio-Modul, nicht im Chat-Katalog*/";
$c=0; $idx=str_replace($o1,$n1,$idx,$c);
echo ($c===1?"  ✓ [1] sozlesmeler chat katalogundan cikti\n":"  ✗ [1] CATS.vertraege anchor ($c)\n"); if($c!==1)$fail[]=1;

/* ── [2a] restore(): profil fallback (tum kopyalarda) ── */
$o2 = 'var P2=window.P||{};';
$n2 = 'var P2=window.P||{}; try{ if(!(P2&&(P2.f1||P2.f7))){ P2=JSON.parse(localStorage.getItem("ch_prof_v3")||"{}")||{}; } }catch(e){}';
$c=0; $idx=str_replace($o2,$n2,$idx,$c);
echo ($c>=1?"  ✓ [2a] profil fallback ($c kopya)\n":"  ✗ [2a] P2 anchor yok\n"); if($c<1)$fail[]='2a';

/* ── [2b] restore(): **markdown** temizligi ── */
$o3 = 'Object.keys(map).forEach(function(k){ if(map[k]) s=s.split(k).join(map[k]); });';
$n3 = 's=s.split("**").join(""); Object.keys(map).forEach(function(k){ if(map[k]) s=s.split(k).join(map[k]); });';
$c=0; $idx=str_replace($o3,$n3,$idx,$c);
echo ($c>=1?"  ✓ [2b] markdown yildiz temizligi ($c)\n":"  ✗ [2b] map anchor yok\n"); if($c<1)$fail[]='2b';

/* ── [3] printDoc: once jsPDF ile gercek indirme ── */
$o4 = "function printDoc(docText){\n    var real=restore(docText);";
$n4 = "function printDoc(docText){\n    var real=restore(docText);\n    try{ if(window.jspdf&&typeof makePDF===\"function\"){ makePDF(real,\"ChatHelp-Dokument\",(typeof CC!==\"undefined\"?CC:\"DE\")); return; } }catch(e){} /*CH_FIX5 PDF*/";
$c=0; $idx=str_replace($o4,$n4,$idx,$c);
echo ($c===1?"  ✓ [3] chat PDF gercek indirme\n":"  ✗ [3] printDoc anchor ($c)\n"); if($c!==1)$fail[]=3;

/* ── [5] enforceOrder: eksik moduller sabit siraya ── */
$o6 = "      var seq=[chat,\n"
    . "        document.getElementById(\"nav-verlauf\"),\n"
    . "        document.getElementById(\"nav-cv\"),\n"
    . "        document.getElementById(\"nav-vst\"),\n"
    . "        document.getElementById(\"nav-fris\"),\n"
    . "        document.getElementById(\"nav-rech\"),\n"
    . "        document.getElementById(\"nav-tresor\"),\n"
    . "        document.getElementById(\"nav-briefe\")];";
$n6 = "      var seq=[chat, /*CH_FIX5 tam sira*/\n"
    . "        document.getElementById(\"nav-verlauf\"),\n"
    . "        document.getElementById(\"nav-jobs\"),\n"
    . "        document.getElementById(\"nav-cv\"),\n"
    . "        document.getElementById(\"nav-vst\"),\n"
    . "        document.getElementById(\"nav-trvisa\"),\n"
    . "        document.getElementById(\"nav-fris\"),\n"
    . "        document.getElementById(\"nav-rech\"),\n"
    . "        document.getElementById(\"nav-tresor\"),\n"
    . "        document.getElementById(\"nav-briefe\"),\n"
    . "        document.getElementById(\"nav-hub\")];";
$c=0; $idx=str_replace($o6,$n6,$idx,$c);
echo ($c===1?"  ✓ [5] menu sirasi TAM sabit (jobs/trvisa/hub eklendi)\n":"  ✗ [5] enforceOrder seq anchor ($c)\n"); if($c!==1)$fail[]=5;

/* ── [7] [[DOC]] kart notu (opsiyonel) ── */
$o5 = 'var NOTE={de:"Vorschau mit Ihren echten Daten — Ihr Name/Ihre Adresse wurden lokal eingesetzt und nie an die KI gesendet.",tr:"Gerçek bilgilerinizle önizleme — ad/adresiniz yalnızca cihazınızda eklendi, yapay zekâya hiç gönderilmedi.",en:"Preview with your real data — your name/address were inserted locally and never sent to the AI."};';
$n5 = 'var NOTE={de:"Ihr Name/Ihre Adresse werden nur lokal eingesetzt und nie an die KI gesendet. Fehlen Angaben, füllen Sie Ihr Profil im Konto aus — Platzhalter wie [VORNAME] werden dann automatisch ersetzt.",tr:"Ad/adresiniz yalnızca cihazınızda eklenir, yapay zekâya asla gönderilmez. Bilgi eksikse Konto\'dan profilinizi doldurun — [VORNAME] gibi yer tutucular otomatik dolar.",en:"Your name/address are inserted locally only and never sent to the AI. If something is missing, fill in your profile in Konto — placeholders like [VORNAME] are replaced automatically."};';
$c=0; $idx=str_replace($o5,$n5,$idx,$c);
echo ($c===1?"  ✓ [7] kart notu duzeltildi\n":"  ~ [7] NOTE anchor yok (opsiyonel, atlandi)\n");

/* ── [6] api.php: vtr_ Zusatzklausel talimati ── */
$oB = ". date('Y') . '), inklusive jüngster Reformen.'";
$nB = ". date('Y') . '), inklusive jüngster Reformen. Hat der Nutzer Zusatzklauseln oder Optionen gewählt oder aufgezählt, nimm JEDE davon als eigenen, vollständig und rechtssicher ausformulierten Paragraphen in den Vertrag auf.'";
$c=0; $api=str_replace($oB,$nB,$api,$c);
echo ($c===1?"  ✓ [6] vtr_ secilen zusatz klozlar tam paragraf olur\n":"  ✗ [6] vtr_ anchor ($c)\n"); if($c!==1)$fail[]=6;

/* ── [4] odeme-donusu geri yukleme + vertraege chat tiklama agi (script ekle) ── */
$bundle = <<<'HTML'
<script id="ch-fix5-js">
/* CH_FIX5 — odeme donusu belge geri yukleme + vertraege -> modul yonlendirme */
try{(function(){
  function wrapSR(){
    try{
      if(typeof window.showResult!=="function"||window.showResult._chf5) return;
      var _o=window.showResult;
      window.showResult=function(doc,dk){ try{ sessionStorage.setItem("ch_lastdoc",JSON.stringify({doc:doc,dk:dk||""})); }catch(e){} return _o.apply(this,arguments); };
      window.showResult._chf5=true;
    }catch(e){}
  }
  wrapSR(); var _t=setInterval(wrapSR,700); setTimeout(function(){ clearInterval(_t); },12000);
  function restoreAfterPay(){
    try{
      if((location.search||"").indexOf("session_id=")<0) return;
      var raw=sessionStorage.getItem("ch_lastdoc"); if(!raw) return;
      var d=JSON.parse(raw); if(!d||!d.doc) return;
      setTimeout(function(){ try{ if(typeof showResult==="function") showResult(d.doc,d.dk); }catch(e){} },1400);
    }catch(e){}
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",restoreAfterPay); else restoreAfterPay();
  document.addEventListener("click",function(ev){
    try{
      var el=ev.target&&ev.target.closest?ev.target.closest("[data-cat],[data-c]"):null; if(!el) return;
      if(el.closest("#pnl-vst")) return;
      var key=el.getAttribute("data-cat")||el.getAttribute("data-c");
      if(key==="vertraege"){ ev.preventDefault(); ev.stopPropagation(); if(typeof gP==="function") gP("vst"); }
    }catch(e){}
  },true);
})();}catch(e){}
</script>
HTML;
$pos = strrpos($idx, '</body>');
if ($pos===false) { echo "  ✗ [4] </body> yok\n"; $fail[]=4; }
else { $idx = substr($idx,0,$pos).$bundle."\n".substr($idx,$pos); echo "  ✓ [4] odeme-donusu koruma + tiklama agi eklendi\n"; }

if ($fail) { echo "\nHATA: su adimlar eslesmedi: ".implode(', ',$fail)." — HICBIR dosya degistirilmedi.\n"; exit; }

/* lint + yedek + yaz */
foreach ([[$idxF,$idx],[$apiF,$api]] as $pair) {
    $tmp = tempnam(sys_get_temp_dir(),'f5').'.php';
    file_put_contents($tmp,$pair[1]);
    $lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
    if ($rc!==0) { echo "\nLINT HATASI (".basename($pair[0]).") — HICBIR dosya degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
}
file_put_contents($idxF.'.bak-fix5-'.date('Ymd-His'), @file_get_contents($idxF));
file_put_contents($apiF.'.bak-fix5-'.date('Ymd-His'), @file_get_contents($apiF));
file_put_contents($idxF,$idx);
file_put_contents($apiF,$api);
echo "\n✓ CH_FIX5 uygulandi. Sozlesmeler sadece modulde, chat PDF calisir, placeholder/markdown duzeldi, menu sirasi TAM sabit, odeme donusu belge korunur, vtr_ zusatz klozlar eklenir.\n";
