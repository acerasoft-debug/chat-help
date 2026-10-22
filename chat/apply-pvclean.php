<?php
/**
 * ChatHelp — apply-pvclean — pv.php'yi SIFIRDAN, saglam ve KENDINI TESHIS EDEN
 *  halde yazar. (Bagimsiz dosya — index.php'ye ve baska hicbir seye dokunmaz.)
 *
 * NEDEN: App WebView'de konsol yok, alert cikmiyor. Bir JS hatasi olursa
 * tamamen gorunmez kaliyor ve disaridan sadece "tuslar calismiyor" olarak
 * yasaniyor. Tahminle ilerlemek yerine hatayi EKRANDA gosteriyoruz.
 *
 * YENILIKLER:
 *  · window.onerror -> sayfanin ustunde KIRMIZI seritte hata metni
 *  · Dugmeler inline onclick yerine addEventListener ile baglanir
 *    (tanimsiz fonksiyon / kacis sorunu ihtimali biter)
 *  · Her dugme basildiginda durum satirina ne oldugunu YAZAR
 *    ('Drucken denendi', 'PDF denendi', 'Gonderiliyor…') — sessiz kalmaz
 *  · Tek script blogu, tek kapanis
 *  · Gorunum aynen korunur: lacivert bar, altin Drucken, A4 beyaz kagit
 *
 * KULLANIM: /chat/pull2.php?key=...&files=apply-pvclean.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
echo "apply-pvclean BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$dir=__DIR__; $f=$dir.'/pv.php';
$old=(string)@file_get_contents($f);
echo "mevcut pv.php: ".($old===''?'YOK':strlen($old).' B')."\n";

$pv = <<<'PHP'
<?php
/* ChatHelp — pv.php (CH_PV3) — belge goruntuleyici: Drucken / PDF / E-Mail.
   Kendini teshis eder: JS hatasi olursa ekranda kirmizi seritte gorunur. */
error_reporting(E_ERROR|E_PARSE);
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');

$id=preg_replace('/[^a-f0-9]/','',(string)($_GET['id'] ?? ''));
$file=__DIR__.'/pv/'.$id.'.txt';
if($id===''||!is_file($file)){
  http_response_code(404);
  echo '<meta charset="utf-8"><p style="font:16px system-ui;padding:24px">Dokument nicht gefunden / Belge bulunamadı.</p>';
  exit;
}
$raw=(string)@file_get_contents($file);
$sep=strpos($raw,"\n\x00\n");
$title=$sep!==false?substr($raw,0,$sep):'ChatHelp-Dokument';
$text =$sep!==false?substr($raw,$sep+3):$raw;
$E=function($s){ return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); };

/* satirlari isaretle: tarih sagda, Betreff kalin */
$out=''; $subjDone=false; $i=0;
foreach(explode("\n",str_replace("\r","",$text)) as $ln){
  $u=trim($ln); $i++;
  if($u===''){ $out.='<div class="sp"></div>'; continue; }
  if(!$subjDone && preg_match('/^(Betreff|Betrifft|Konu|Subject|Objet)\s*:/iu',$u)){
    $out.='<p class="subj">'.$E($u).'</p>'; $subjDone=true; continue;
  }
  if($i<9 && mb_strlen($u,'UTF-8')<52 && preg_match('/\d{1,2}[.\/]\s?\d{1,2}[.\/]\s?\d{2,4}/u',$u)
     && !preg_match('/[a-zäöüçşğ]{6,}.*[a-zäöüçşğ]{6,}/iu',$u)){
    $out.='<p class="dt">'.$E($u).'</p>'; continue;
  }
  $out.='<p>'.$E($u).'</p>';
}
?><!doctype html><html><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo $E($title); ?></title>
<style>
:root{--navy:#0e1734;--gold:#cc9e3d;--ink:#21242c}
*{box-sizing:border-box}
body{margin:0;background:#e9ecf3;font-family:Georgia,'Times New Roman',serif;color:var(--ink)}
#err{display:none;background:#7f1d1d;color:#fff;font:600 12.5px/1.45 system-ui;padding:10px 14px;white-space:pre-wrap;word-break:break-word}
.bar{position:sticky;top:0;background:var(--navy);color:#f0e6c8;padding:11px 14px;display:flex;gap:8px;align-items:center;
 font-family:system-ui,-apple-system,'Segoe UI',sans-serif;box-shadow:0 2px 12px rgba(0,0,0,.25);z-index:5;flex-wrap:wrap}
.bar b{font-size:15px;flex:1;min-width:110px;font-weight:800;letter-spacing:.3px}
.bar button{font:700 13px system-ui;padding:10px 13px;border-radius:9px;border:1px solid rgba(212,168,74,.6);
 background:#182248;color:#f0e6c8;cursor:pointer;-webkit-tap-highlight-color:transparent}
.bar button.pri{background:var(--gold);color:#1a1405;border-color:var(--gold);font-weight:800}
#st{width:100%;color:#9fb0d8;font:12px system-ui;padding-top:2px}
#mr{display:none;gap:8px;padding:10px 14px;background:#132048;align-items:center;flex-wrap:wrap;
 font-family:system-ui,sans-serif;border-top:1px solid rgba(212,168,74,.3)}
#mr input{flex:1;min-width:170px;padding:11px 12px;border-radius:9px;border:1px solid rgba(212,168,74,.45);
 background:#0b1330;color:#f0e6c8;font:15px system-ui}
#mr button{font:800 13px system-ui;padding:11px 15px;border-radius:9px;border:0;background:var(--gold);color:#1a1405}
.sheet{max-width:820px;margin:18px auto 44px;background:#fff;padding:42px 44px 60px;
 box-shadow:0 10px 34px rgba(14,23,52,.18);border-radius:3px}
.rule{border-top:1.2px solid var(--navy);position:relative;margin-bottom:26px}
.rule:after{content:"";position:absolute;left:0;top:-2.4px;width:54px;height:2.4px;background:var(--gold)}
.sheet p{margin:0 0 2px;font-size:15.5px;line-height:1.74;white-space:pre-wrap;word-wrap:break-word}
.sheet .sp{height:10px}
.sheet .dt{text-align:right}
.sheet .subj{font-weight:700;font-size:16.5px;margin:14px 0 12px}
@media print{ .bar,#mr,#err{display:none!important} body{background:#fff}
  .sheet{max-width:none;margin:0;padding:0;box-shadow:none;border-radius:0} @page{size:A4;margin:2.2cm 2cm} }
@media(max-width:640px){.sheet{margin:12px;padding:26px 22px 42px}}
</style></head><body>
<div id="err"></div>
<div class="bar">
  <b><?php echo $E($title); ?></b>
  <button id="b-print" class="pri">🖨 Drucken</button>
  <button id="b-pdf">⬇️ PDF</button>
  <button id="b-mail">📧 E-Mail</button>
  <div id="st"></div>
</div>
<div id="mr">
  <input id="m-to" type="email" placeholder="E-Mail-Adresse" autocomplete="email">
  <button id="m-send">Senden</button>
</div>
<div class="sheet"><div class="rule"></div><?php echo $out; ?></div>
<form id="pf" method="POST" action="dl.php" style="display:none">
  <input type="hidden" name="html" id="pfh">
  <input type="hidden" name="name" value="<?php echo $E($title); ?>">
  <input type="hidden" name="fmt" value="pdf">
</form>
<script>
/* ── gorunur hata seridi: WebView'de konsol yok, hata sessiz kalmasin ── */
window.onerror = function(m, src, ln, col){
  try{
    var e = document.getElementById('err');
    e.style.display = 'block';
    e.textContent = 'JS-Fehler: ' + m + '  (Zeile ' + ln + ':' + col + ')';
  }catch(_){}
  return false;
};
(function(){
  var TXT = <?php echo json_encode($text, JSON_UNESCAPED_UNICODE); ?>;
  var TTL = <?php echo json_encode($title, JSON_UNESCAPED_UNICODE); ?>;
  var st  = document.getElementById('st');
  function say(s){ try{ st.textContent = s; }catch(_){} }
  function esc(s){ return String(s).replace(/[&<>]/g, function(c){
    return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c]; }); }

  document.getElementById('b-print').addEventListener('click', function(){
    say('Drucken …');
    try{ window.print(); say('Druckdialog angefordert. Erscheint nichts, bitte 📧 E-Mail nutzen.'); }
    catch(e){ say('Drucken nicht möglich — bitte 📧 E-Mail nutzen.'); }
  });

  document.getElementById('b-pdf').addEventListener('click', function(){
    say('PDF …');
    try{
      document.getElementById('pfh').value =
        '<!doctype html><html><head><meta charset="utf-8"></head>'
        + '<body style="font-family:Georgia,serif;font-size:12pt;line-height:1.6;white-space:pre-wrap;margin:2.2cm">'
        + esc(TXT) + '</body></html>';
      document.getElementById('pf').submit();
      say('PDF angefordert. Kein Download? Dann 📧 E-Mail nutzen.');
    }catch(e){ say('PDF nicht möglich — bitte 📧 E-Mail nutzen.'); }
  });

  var mr = document.getElementById('mr');
  document.getElementById('b-mail').addEventListener('click', function(){
    mr.style.display = (mr.style.display === 'flex' ? 'none' : 'flex');
    if(mr.style.display === 'flex'){ say('E-Mail-Adresse eingeben, dann Senden.');
      try{ document.getElementById('m-to').focus(); }catch(_){} }
    else say('');
  });

  document.getElementById('m-send').addEventListener('click', function(){
    var to = (document.getElementById('m-to').value || '').trim();
    if(!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(to)){ say('Bitte gültige E-Mail eingeben.'); return; }
    say('Wird gesendet …');
    try{
      var fd = new FormData();
      fd.append('to', to); fd.append('text', TXT); fd.append('title', TTL);
      fetch('pvmail.php', { method:'POST', body: fd })
        .then(function(r){ return r.text(); })
        .then(function(t){
          var j = null; try{ j = JSON.parse(t); }catch(_){}
          if(j && j.ok) say('✓ Gesendet an ' + to);
          else say('✗ Fehler: ' + (j ? (j.msg || 'unbekannt') : t.slice(0,120)));
        })
        .catch(function(e){ say('✗ Netzwerkfehler: ' + e); });
    }catch(e){ say('✗ Fehler: ' + e); }
  });

  say('Bereit.');
  setTimeout(function(){ try{ window.print(); }catch(_){} }, 800);
})();
</script>
</body></html>
PHP;

$tmp=tempnam(sys_get_temp_dir(),'pvc').'.php'; file_put_contents($tmp,$pv);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0) exit("\n✗ LINT HATASI — pv.php DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n");

/* script acilis/kapanis dengesi */
$op=preg_match_all('/<script\b/i',$pv); $cl=substr_count($pv,'</script>');
echo "script acilis=$op kapanis=$cl\n";
if($op!==$cl) exit("✗ script dengesi bozuk — DEGISIKLIK YOK\n");
if(strpos($pv,'<\\/script>')!==false) exit("✗ bozuk kapanis var — DEGISIKLIK YOK\n");

if($old!=='') @file_put_contents($f.'.bak-pvclean-'.date('Ymd-His'),$old);
$w=@file_put_contents($f,$pv);
if($w===false||$w<strlen($pv)) exit("\n✗ YAZMA HATASI.\n");
@copy($f,$f.'.GOOD');
if(!is_dir($dir.'/pv')) @mkdir($dir.'/pv',0755,true);
if(function_exists('opcache_reset')) @opcache_reset();

echo "\n✓ pv.php yeniden yazildi (".strlen($pv)." B)\n";
echo "  pvmail.php: ".(is_file($dir.'/pvmail.php')?'VAR':'YOK')
    ."   pdflib.php: ".(is_file($dir.'/pdflib.php')?'VAR':'YOK')."\n";
echo "\nTEST (App): belge -> 🖨 Drucken -> acilan sayfada\n";
echo "  · Ustte KIRMIZI serit varsa: JS hatasi — o metni bana yaz\n";
echo "  · Barin altinda durum satiri her tusta ne oldugunu yazar ('Bereit.' ile baslar)\n";
echo "  · 📧 E-Mail -> adres -> Senden -> '✓ Gesendet an ...' veya '✗ Fehler: ...'\n";
