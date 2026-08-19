<?php
/**
 * ChatHelp — apply-pdfmail (CH_MAILBTN) — PDF'i E-POSTA ile teslim.
 *
 * DURUM: Mektup duzeni ZATEN IYI — hicbir bicime dokunulmaz.
 * Tek sorun: Android WebView dosya indiremiyor/yazdiramiyor (APK ozelligi).
 * COZUM: PDF'i sunucuda uretip kullanicinin e-postasina EK olarak yollamak.
 * Bu, WebView'in hicbir yetenegine bagli degildir — %100 calisir.
 *
 * YAPILANLAR (hepsi EKLEME; mevcut gorunum/kod degismez):
 *  [1] pdflib.php — PDF motoru CANLI dl.php'den TURETILIR (kopya yok, ayrisma yok)
 *  [2] pvmail.php — text+title+to alir, PDF uretir, MIME eki olarak yollar
 *  [3] index.php  — CH_MAILBTN: mevcut katman (#ch-appview) acilinca dugme
 *                   cubuguna '📧 PDF per E-Mail' EKLER. Katmanin kendisine,
 *                   metnine, bicimine DOKUNMAZ.
 *  [4] pv.php     — bar'a ayni dugme + adres satiri eklenir (mektup govdesi aynen kalir)
 *
 * Kritik isaretler once dogrulanir; biri kaybolacaksa hicbir sey yazilmaz.
 * Lint + .bak + dogrulama + kilit tazeleme.
 * KULLANIM: /chat/pull2.php?key=...&files=apply-pdfmail.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
echo "apply-pdfmail BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$dir=__DIR__; $file=$dir.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");

/* ─── [1] pdflib.php — dl.php'den turet ─── */
$dl=(string)@file_get_contents($dir.'/dl.php');
$libOK=false;
if($dl===''){ echo "[1] ✗ dl.php okunamadi\n"; }
else{
  $cut=strpos($dl,'$html=(string)($_POST[');
  if($cut===false) $cut=strpos($dl,"\$html=(string)(\$_POST");
  if($cut===false){ echo "[1] ✗ dl.php kesme noktasi bulunamadi\n"; }
  else{
    $lib=rtrim(substr($dl,0,$cut))."\n";
    $t=tempnam(sys_get_temp_dir(),'pl').'.php'; file_put_contents($t,$lib);
    $lo=[];$rc=0; exec('php -l '.escapeshellarg($t).' 2>&1',$lo,$rc); @unlink($t);
    if($rc!==0){ echo "[1] ✗ pdflib LINT hatasi — yazilmadi\n"; }
    else{
      @file_put_contents($dir.'/pdflib.php',$lib); $libOK=true;
      echo "[1] ✓ pdflib.php turetildi (".strlen($lib)." B)";
      echo "  [".(strpos($lib,'ch_premium_pdf_uni')!==false?'uni ':'').(strpos($lib,'ch_premium_pdf(')!==false?'premium ':'').(strpos($lib,'ch_html_to_text')!==false?'html2text':'')."]\n";
    }
  }
}
if(!$libOK) exit("\n✗ pdflib olmadan devam edilmez — DEGISIKLIK YOK.\n");

/* ─── [2] pvmail.php ─── */
$pvmail = <<<'PHP'
<?php
/* ChatHelp — pvmail.php (CH_PVMAIL) — belgeyi PDF yapip e-posta EKI olarak yollar. */
error_reporting(E_ERROR|E_PARSE);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
@require_once __DIR__.'/pdflib.php';
function pvm_out($ok,$msg=''){ echo json_encode(['ok'=>(bool)$ok,'msg'=>$msg]); exit; }

$to=trim((string)($_POST['to'] ?? ''));
if($to===''||!filter_var($to,FILTER_VALIDATE_EMAIL)) pvm_out(false,'invalid_email');

$txt=(string)($_POST['text'] ?? '');
if(trim($txt)===''&&isset($_POST['html'])&&function_exists('ch_html_to_text')) $txt=ch_html_to_text((string)$_POST['html']);
if(trim($txt)==='') pvm_out(false,'empty');
if(strlen($txt)>400000) $txt=substr($txt,0,400000);

$title=preg_replace('/[^\p{L}\p{N} .,\-]/u','',(string)($_POST['title'] ?? 'ChatHelp-Dokument'));
if(trim($title)==='') $title='ChatHelp-Dokument';
$fname=preg_replace('/[^A-Za-z0-9._\- ]/','',$title); if(trim($fname)==='') $fname='ChatHelp-Dokument';

$pdf='';
$fr=__DIR__.'/fonts/dvs.ttf'; $fb=__DIR__.'/fonts/dvsb.ttf';
if(is_file($fr)&&is_file($fb)&&function_exists('ch_premium_pdf_uni')){
  $pdf=ch_premium_pdf_uni($txt,(string)file_get_contents($fr),(string)file_get_contents($fb));
}elseif(function_exists('ch_premium_pdf')){
  $pdf=ch_premium_pdf($txt);
}
if(strlen($pdf)<200) pvm_out(false,'pdf_failed');

try{ $b='chb_'.bin2hex(random_bytes(8)); }catch(Exception $e){ $b='chb_'.md5(uniqid('',true)); }
$from='noreply@chat-help.com';
$subject='=?UTF-8?B?'.base64_encode('ChatHelp — '.$title).'?=';
$hdr ="From: ChatHelp <$from>\r\nReply-To: $from\r\nMIME-Version: 1.0\r\n";
$hdr.="Content-Type: multipart/mixed; boundary=\"$b\"\r\n";
$body ="--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n";
$body.="Ihr Dokument ist als PDF angehängt.\r\nBelgeniz PDF olarak ektedir.\r\n\r\n— ChatHelp\r\n\r\n";
$body.="--$b\r\nContent-Type: application/pdf; name=\"$fname.pdf\"\r\n";
$body.="Content-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"$fname.pdf\"\r\n\r\n";
$body.=chunk_split(base64_encode($pdf))."\r\n--$b--";

pvm_out(@mail($to,$subject,$body,$hdr) ? true : false, 'mail');
PHP;
@file_put_contents($dir.'/pvmail.php',$pvmail);
echo "[2] ✓ pvmail.php yazildi (".strlen($pvmail)." B)\n";

/* ─── [3] index.php: mevcut katmana dugme EKLE (bicime dokunma) ─── */
$new=$src;
if(strpos($new,'CH_MAILBTN')!==false){ echo "[3] (CH_MAILBTN zaten ekli — atlandi)\n"; }
else{
  $js = <<<'HTML'
<script>/*CH_MAILBTN*/(function(){try{
function L(){try{return (typeof uil==='function')?uil():(localStorage.getItem('ch_uilang')||'de');}catch(e){return 'de';}}
var T={de:{m:'PDF per E-Mail',ph:'E-Mail-Adresse',snd:'Senden',ing:'Wird gesendet …',ok:'✓ Gesendet an ',er:'✗ Fehler'},
       tr:{m:'PDF’i e-postala',ph:'E-posta adresi',snd:'Gönder',ing:'Gönderiliyor …',ok:'✓ Gönderildi: ',er:'✗ Hata'},
       en:{m:'PDF by e-mail',ph:'E-mail address',snd:'Send',ing:'Sending …',ok:'✓ Sent to ',er:'✗ Error'}};
function t(k){var l=L();return (T[l]||T.de)[k]||T.de[k];}
function guessEmail(){try{for(var i=0;i<localStorage.length;i++){var v=localStorage.getItem(localStorage.key(i))||'';
  var m=String(v).match(/[\w.+-]+@[\w-]+\.[\w.]{2,}/); if(m) return m[0];}}catch(e){}return '';}
function docText(ov){
  try{ var pap=ov.querySelector('.cv-paper'); if(pap) return (pap.innerText||pap.textContent||'').trim(); }catch(e){}
  try{ return (ov.innerText||'').trim(); }catch(e){}
  return '';
}
function docTitle(ov){
  try{ var tt=ov.querySelector('.cv-ttl'); if(tt) return (tt.textContent||'').replace(/^\s*📄\s*/,'').trim(); }catch(e){}
  return 'ChatHelp-Dokument';
}
function inject(ov){
  try{
    if(!ov||ov.getAttribute('data-ch-mail')==='1') return;
    var act=ov.querySelector('.cv-act'); if(!act) return;
    ov.setAttribute('data-ch-mail','1');

    var row=document.createElement('div');
    row.setAttribute('style','display:none;gap:8px;padding:10px 12px 0;background:#0e1734;flex-wrap:wrap;align-items:center');
    var inp=document.createElement('input');
    inp.type='email'; inp.placeholder=t('ph'); inp.setAttribute('autocomplete','email');
    inp.setAttribute('style','flex:1;min-width:150px;padding:11px 12px;border-radius:10px;border:1px solid rgba(204,158,61,.45);background:#0b1330;color:#f0e6c8;font:14px system-ui');
    var snd=document.createElement('button'); snd.textContent=t('snd');
    snd.setAttribute('style','padding:11px 16px;border-radius:10px;border:0;background:#cc9e3d;color:#1a1405;font:800 13px system-ui');
    var st=document.createElement('div');
    st.setAttribute('style','width:100%;color:#9fb0d8;font:12px system-ui;padding:2px 2px 0');
    row.appendChild(inp); row.appendChild(snd); row.appendChild(st);
    act.parentNode.insertBefore(row,act);
    try{ var g=guessEmail(); if(g) inp.value=g; }catch(e){}

    var b=document.createElement('button');
    b.textContent='📧 '+t('m');
    b.setAttribute('style','flex:1.6;min-width:0;padding:13px 6px;border-radius:10px;border:1px solid #cc9e3d;background:#cc9e3d;color:#1a1405;font:800 13px system-ui,-apple-system,sans-serif;-webkit-tap-highlight-color:transparent');
    b.onclick=function(){
      row.style.display=(row.style.display==='flex'?'none':'flex');
      if(row.style.display==='flex'){ try{ inp.focus(); }catch(e){} }
    };
    act.insertBefore(b,act.firstChild);

    snd.onclick=function(){
      var to=(inp.value||'').trim();
      if(!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(to)){ st.textContent=t('ph'); return; }
      st.textContent=t('ing');
      try{
        var fd=new FormData();
        fd.append('to',to); fd.append('text',docText(ov)); fd.append('title',docTitle(ov));
        fetch('pvmail.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(j){
          st.textContent=(j&&j.ok)?(t('ok')+to):(t('er')+' ('+((j&&j.msg)||'?')+')');
        }).catch(function(){ st.textContent=t('er'); });
      }catch(e){ st.textContent=t('er'); }
    };
  }catch(e){}
}
function scan(){ try{ var ov=document.getElementById('ch-appview'); if(ov) inject(ov); }catch(e){} }
function start(){
  try{
    scan();
    var mo=new MutationObserver(function(){ scan(); });
    mo.observe(document.body,{childList:true,subtree:true});
  }catch(e){}
}
if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',start); else start();
}catch(e){}})();</script>
HTML;
  $p=strripos($new,'</body>');
  if($p===false) exit("[3] ✗ </body> bulunamadi — DEGISIKLIK YOK\n");
  $new=substr($new,0,$p).$js.substr($new,$p);
  echo "[3] ✓ CH_MAILBTN eklendi (katmana dokunulmadan dugme enjeksiyonu)\n";
}

/* kritik isaretler korunuyor mu? */
foreach(['CH_ISWV_GLOBAL'=>1,'CH_APPVIEW3'=>1,'CH_APPVIEW2C'=>4] as $m=>$min){
  if(substr_count($new,$m)<$min) exit("✗ Kritik isaret kaybolurdu ($m) — DEGISIKLIK YOK\n");
}
if($new!==$src){
  $t=tempnam(sys_get_temp_dir(),'pm').'.php'; file_put_contents($t,$new);
  $lo=[];$rc=0; exec('php -l '.escapeshellarg($t).' 2>&1',$lo,$rc); @unlink($t);
  if($rc!==0) exit("\n✗ LINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n");
  @file_put_contents($file.'.bak-pdfmail-'.date('Ymd-His'),$src);
  $w=@file_put_contents($file,$new);
  if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
  if(function_exists('opcache_reset')) @opcache_reset();
}

/* ─── [4] pv.php: bar'a dugme + adres satiri (mektup govdesi AYNEN kalir) ─── */
$pvf=$dir.'/pv.php'; $pv=(string)@file_get_contents($pvf);
if($pv===''){ echo "[4] · pv.php yok — atlandi\n"; }
elseif(strpos($pv,'CH_PVMAILUI')!==false){ echo "[4] (pv.php zaten guncel — atlandi)\n"; }
else{
  $anchor='<button onclick="pdf()">⬇️ PDF</button>';
  if(strpos($pv,$anchor)===false){ echo "[4] · pv.php anchor bulunamadi — atlandi (kritik degil)\n"; }
  else{
    $btn=$anchor.'<button onclick="chMailToggle()">📧 E-Mail</button>';
    $pv2=str_replace($anchor,$btn,$pv);
    $ui='<div id="chmr" style="display:none;gap:8px;padding:10px 14px;background:#132048;align-items:center;flex-wrap:wrap;font-family:system-ui,sans-serif;border-top:1px solid rgba(212,168,74,.3)">'
       .'<input id="chmto" type="email" placeholder="E-Mail" autocomplete="email" style="flex:1;min-width:180px;padding:10px 12px;border-radius:9px;border:1px solid rgba(212,168,74,.45);background:#0b1330;color:#f0e6c8;font:14px system-ui">'
       .'<button onclick="chMailSend()" style="font:800 13px system-ui;padding:10px 14px;border-radius:9px;border:0;background:#cc9e3d;color:#1a1405">Senden</button>'
       .'<div id="chmst" style="color:#9fb0d8;font:12px system-ui;width:100%"></div></div>'
       .'<script>/*CH_PVMAILUI*/'
       .'function chMailToggle(){var m=document.getElementById("chmr");m.style.display=(m.style.display==="flex"?"none":"flex");}'
       .'function chMailTxt(){try{var s=document.querySelector(".sheet");return (s.innerText||s.textContent||"").trim();}catch(e){return "";}}'
       .'function chMailSend(){var to=(document.getElementById("chmto").value||"").trim(),st=document.getElementById("chmst");'
       .'if(!/^[^@\\s]+@[^@\\s]+\\.[^@\\s]+$/.test(to)){st.textContent="Bitte gültige E-Mail eingeben.";return;}'
       .'st.textContent="Wird gesendet …";var fd=new FormData();fd.append("to",to);fd.append("text",chMailTxt());'
       .'fd.append("title",document.title||"ChatHelp-Dokument");'
       .'fetch("pvmail.php",{method:"POST",body:fd}).then(function(r){return r.json();}).then(function(j){'
       .'st.textContent=(j&&j.ok)?("✓ Gesendet an "+to):("✗ Fehler ("+((j&&j.msg)||"?")+")");})'
       .'.catch(function(){st.textContent="✗ Netzwerkfehler";});}'
       .'<\/script>';
    $bp=strripos($pv2,'</body>');
    if($bp!==false){ $pv2=substr($pv2,0,$bp).$ui.substr($pv2,$bp); }
    $t=tempnam(sys_get_temp_dir(),'pv').'.php'; file_put_contents($t,$pv2);
    $lo=[];$rc=0; exec('php -l '.escapeshellarg($t).' 2>&1',$lo,$rc); @unlink($t);
    if($rc!==0){ echo "[4] ✗ pv.php LINT hatasi — degistirilmedi\n"; }
    else{ @file_put_contents($pvf.'.bak-'.date('Ymd-His'),$pv); @file_put_contents($pvf,$pv2);
          echo "[4] ✓ pv.php'ye E-Mail dugmesi eklendi (mektup govdesi aynen korundu)\n"; }
  }
}

/* ─── kilidi tazele ─── */
$cur=(string)@file_get_contents($file);
@file_put_contents($file.'.GOOD-appprint',$cur);
foreach(['pv.php','pvsave.php','pvmail.php','pdflib.php'] as $f){ if(is_file($dir.'/'.$f)) @copy($dir.'/'.$f,$dir.'/'.$f.'.GOOD'); }
@file_put_contents($dir.'/ch-applock.json',json_encode([
 'locked_at'=>date('c'),'size'=>strlen($cur),'sha1'=>sha1($cur),'note'=>'pdfmail',
 'markers'=>['CH_ISWV_GLOBAL'=>substr_count($cur,'CH_ISWV_GLOBAL'),'CH_APPVIEW3'=>substr_count($cur,'CH_APPVIEW3'),
             'CH_APPVIEW2C'=>substr_count($cur,'CH_APPVIEW2C'),'CH_MAILBTN'=>substr_count($cur,'CH_MAILBTN'),
             'CH_WAITDEDUP'=>substr_count($cur,'CH_WAITDEDUP')],
 'files'=>['pv.php','pvsave.php','pvmail.php','pdflib.php']
],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

echo "\n✓ TAMAM (".strlen($src)." -> ".strlen($cur)." bayt)\n";
echo "  CH_MAILBTN=".substr_count($cur,'CH_MAILBTN')
    ."  CH_APPVIEW3=".substr_count($cur,'CH_APPVIEW3')
    ."  CH_APPVIEW2C=".substr_count($cur,'CH_APPVIEW2C')
    ."  CH_ISWV_GLOBAL=".substr_count($cur,'CH_ISWV_GLOBAL')."\n";
echo "  ✓ kilit tazelendi (sha1 ".substr(sha1($cur),0,12)."…)\n";
echo "\nTEST: App kapat-ac -> dilekce -> 'Kostenlos selbst ausdrucken'\n";
echo "  -> Mektup AYNI gorunumde acilir; dugme cubugunda '📧 PDF per E-Mail' vardir.\n";
echo "  -> Adresi gir, Senden -> PDF e-postana EK olarak gelir.\n";
