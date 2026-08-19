<?php
/**
 * ChatHelp — apply-email (CH_EMAIL_DOC) — belgeyi otomatik KURUMSAL HTML e-posta.
 *  (a) auth.php: 'email_doc' action + doEmailDoc() — requireAuth (spam-relay
 *      engeli), belgeyi kurumsal HTML olarak sendMail ile gonderir.
 *  (b) index.php: sonuc kartindaki E-Mail butonu mailto yerine chEmailDoc'u cagirir
 *      (adresi profilden on-doldurur, otomatik gonderir). Sadece ekleme.
 * KULLANIM: pull2.php?key=...&files=apply-email.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-email BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$ok=true; $steps=[];

/* ── (a) auth.php ── */
$authF=__DIR__.'/auth.php'; $auth=@file_get_contents($authF);
if($auth===false){ exit("auth.php okunamadi\n"); }
if(strpos($auth,'CH_EMAIL_DOC')!==false){ echo "  • auth zaten uygulanmis\n"; }
else{
  $rAnchor="=> doBillingPortal(),";
  if(strpos($auth,$rAnchor)===false){ exit("HATA: router anchor (doBillingPortal) yok — once billing-portal uygulanmali.\n"); }
  $auth=str_replace($rAnchor, $rAnchor."\n    'email_doc'      => doEmailDoc(\$body),", $auth);
  $fn = <<<'PHPFN'

/* CH_EMAIL_DOC — Dokument als formelle HTML-E-Mail senden */
function doEmailDoc(array $b): void {
    $u = requireAuth();
    $to   = strtolower(trim((string)($b['email'] ?? '')));
    $doc  = (string)($b['document'] ?? '');
    $name = mb_substr(trim((string)($b['docName'] ?? 'Dokument')),0,120);
    if(!filter_var($to, FILTER_VALIDATE_EMAIL)) respond(['success'=>false,'message'=>'Ungültige E-Mail'], 400);
    if($doc==='' || mb_strlen($doc)>80000) respond(['success'=>false,'message'=>'Ungültiges Dokument'], 400);
    $safe = nl2br(htmlspecialchars($doc, ENT_QUOTES, 'UTF-8'));
    $html = '<div style="font-family:Georgia,\'Times New Roman\',serif;max-width:680px;margin:0 auto;padding:26px 24px;color:#1a1a22;background:#ffffff">'
          . '<div style="height:4px;background:#d4a84a;margin-bottom:20px"></div>'
          . '<div style="font-size:14px;line-height:1.75">'.$safe.'</div>'
          . '<hr style="border:none;border-top:1px solid #e0e0e6;margin:22px 0 10px">'
          . '<div style="font-size:11px;color:#9a9aa8">Erstellt mit ChatHelp · chat-help.com</div></div>';
    try { sendMail($to, $name.' — ChatHelp', $html); }
    catch (Throwable $e) { respond(['success'=>false,'message'=>'Versand fehlgeschlagen'], 500); }
    respond(['success'=>true]);
}
PHPFN;
  $close=strrpos($auth,'?>');
  if($close!==false && trim(substr($auth,$close+2))==='') $auth=substr($auth,0,$close).$fn."\n?>".substr($auth,$close+2);
  else $auth=rtrim($auth)."\n".$fn."\n";
  $auth.="\n/* CH_EMAIL_DOC applied */\n";
  $tmp=tempnam(sys_get_temp_dir(),'em').'.php'; file_put_contents($tmp,$auth);
  $lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
  if($rc!==0){ echo "  ✗ (a) LINT hatasi — auth dokunulmadi:\n     ".implode("\n     ",$lo)."\n"; $ok=false; }
  else{
    @file_put_contents($authF.'.bak-email-'.date('Ymd-His'),(string)@file_get_contents($authF));
    if(@file_put_contents($authF,$auth)!==false && strpos((string)@file_get_contents($authF),'function doEmailDoc')!==false){ echo "  ✓ (a) auth.php: email_doc endpoint eklendi\n"; }
    else { echo "  ✗ (a) yazma hatasi\n"; $ok=false; }
  }
}

/* ── (b) index.php ── */
$idxF=__DIR__.'/index.php'; $idx=@file_get_contents($idxF);
if($idx===false){ exit("index.php okunamadi\n"); }
if(strpos($idx,'CH_EMAIL_DOC_UI')!==false){ echo "  • index zaten uygulanmis\n"; }
else{
  $emOld="em.onclick=()=>{const e=P.f7||prompt('E-Mail:');if(e)window.open('mailto:'+e+'?subject='+encodeURIComponent('Ihr Dokument')+'&body='+encodeURIComponent(doc));};";
  $emNew="em.onclick=()=>{ if(window.chEmailDoc){ window.chEmailDoc(doc,(typeof d!=='undefined'&&d&&d.n)?d.n:'Dokument'); return; } const e=P.f7||prompt('E-Mail:');if(e)window.open('mailto:'+e+'?subject='+encodeURIComponent('Ihr Dokument')+'&body='+encodeURIComponent(doc)); };/*CH_EMAIL_DOC_UI*/";
  $c=substr_count($idx,$emOld);
  $block = <<<'HTMLBLOCK'
<script id="ch-email-doc">
try{(function(){
  window.chEmailDoc=function(doc,name){
    try{
      var l='de'; try{ l=(localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){}
      var ASK={de:'An welche E-Mail-Adresse senden?',tr:'Hangi e-posta adresine gönderilsin?',en:'Send to which e-mail address?',fr:'Envoyer à quelle adresse e-mail ?',es:'¿A qué correo enviar?'};
      var OK={de:'✓ Als E-Mail gesendet an: ',tr:'✓ E-posta gönderildi: ',en:'✓ Sent to: ',fr:'✓ Envoyé à : ',es:'✓ Enviado a: '};
      var FAIL={de:'E-Mail konnte nicht gesendet werden.',tr:'E-posta gönderilemedi.',en:'Could not send e-mail.',fr:'Échec de l’envoi.',es:'No se pudo enviar.'};
      var LOGIN={de:'Bitte melden Sie sich an, um per E-Mail zu senden.',tr:'E-posta göndermek için lütfen giriş yapın.',en:'Please log in to send by e-mail.'};
      var tk=null; try{ tk=localStorage.getItem('ch_token'); }catch(e){}
      if(!tk){ alert(LOGIN[l]||LOGIN.de); try{ if(typeof gP==='function') gP('konto'); }catch(e){} return; }
      var pre=''; try{ var u=JSON.parse(localStorage.getItem('ch_user')||'{}'); pre=(window.P&&P.f7)||u.email||''; }catch(e){}
      var to=prompt(ASK[l]||ASK.de, pre); if(!to) return;
      var hd={'Content-Type':'application/json','Authorization':'Bearer '+tk};
      fetch('auth.php?action=email_doc',{method:'POST',headers:hd,body:JSON.stringify({email:to,docName:name||'Dokument',document:String(doc||'')})})
        .then(function(r){ return r.json(); })
        .then(function(j){ alert(j&&j.success ? ((OK[l]||OK.de)+to) : ((j&&j.message)||(FAIL[l]||FAIL.de))); })
        .catch(function(){ alert(FAIL[l]||FAIL.de); });
    }catch(e){}
  };
})();}catch(e){}
</script>
HTMLBLOCK;
  if($c===1){
    $idx=str_replace($emOld,$emNew,$idx);
    $pos=strripos($idx,'</body>');
    if($pos===false){ echo "  ✗ (b) </body> yok\n"; $ok=false; }
    else{
      $idx=substr($idx,0,$pos).$block."\n".substr($idx,$pos);
      $tmp=tempnam(sys_get_temp_dir(),'ei').'.php'; file_put_contents($tmp,$idx);
      $lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
      if($rc!==0){ echo "  ✗ (b) LINT hatasi — index dokunulmadi:\n     ".implode("\n     ",$lo)."\n"; $ok=false; }
      else{
        @file_put_contents($idxF.'.bak-email-'.date('Ymd-His'),(string)@file_get_contents($idxF));
        if(@file_put_contents($idxF,$idx)!==false && strpos((string)@file_get_contents($idxF),'CH_EMAIL_DOC_UI')!==false){ echo "  ✓ (b) index.php: E-Mail butonu -> chEmailDoc (otomatik gonderim)\n"; }
        else { echo "  ✗ (b) yazma hatasi\n"; $ok=false; }
      }
    }
  } else { echo "  ⚠ (b) em.onclick anchor $c kez (1 bekleniyordu) — index atlandi\n"; $ok=false; }
}

echo "\n".($ok?"✓ Email otomatik gonderim hazir (kurumsal HTML e-posta)":"⚠ Bazi adimlar atlandi")."\n";
