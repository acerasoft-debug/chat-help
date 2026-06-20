<?php
/**
 * ChatHelp — Dilekçe/sözleşme e-posta gönderimi (additive)
 *  • Üretilen belgeyi yakalar (generate fetch yanıtından)
 *  • "📧 Per E-Mail senden" premium butonu gösterir -> send-doc.php
 *  • Profil e-postası (P.f7) varsa ona, yoksa sorar
 * KULLANIM: chat-help.com/chat/apply-doc-email.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src = file_get_contents($file); $start=$src;
file_put_contents($file . '.bak-docmail-' . date('Ymd-His'), $src);
if (strpos($src, 'ch-doc-email') !== false) { exit("Zaten ekli.\n"); }

$bundle = <<<'HTML'
<style id="ch-doc-email-css">
#ch-mail-btn{display:none;position:fixed;left:50%;bottom:150px;transform:translateX(-50%);z-index:99998;background:linear-gradient(135deg,#d4a84a,#b8862f);color:#1b1b1e;border:none;border-radius:24px;padding:12px 22px;font-size:14px;font-weight:800;cursor:pointer;box-shadow:0 8px 24px rgba(0,0,0,.4);font-family:inherit;align-items:center;gap:8px}
#ch-mail-btn:hover{filter:brightness(1.06)}
#ch-mail-btn.on{display:flex}
</style>
<script id="ch-doc-email">
(function(){
  'use strict';
  var of=window.fetch.bind(window);
  window.fetch=function(input,init){
    var url=(typeof input==='string')?input:((input&&input.url)||'');
    var p=of(input,init);
    if(/action=generate/.test(url)){
      p.then(function(r){ try{ r.clone().json().then(function(d){
        if(d&&d.document && (''+d.document).length>20){ window._chLastDocText=d.document; setTimeout(showBtn,700); }
      }).catch(function(){}); }catch(e){} });
    }
    return p;
  };

  function btn(){ return document.getElementById('ch-mail-btn'); }
  function showBtn(){
    var b=btn();
    if(!b){ b=document.createElement('button'); b.id='ch-mail-btn'; b.type='button';
      b.innerHTML='📧 Per E-Mail senden'; b.onclick=sendMail; document.body.appendChild(b); }
    b.classList.add('on');
    clearTimeout(b._t); b._t=setTimeout(function(){ b.classList.remove('on'); }, 45000);
  }

  function profileEmail(){
    try{ if(window.P && P.f7) return P.f7; }catch(e){}
    try{ var u=JSON.parse(localStorage.getItem('ch_user')||'{}'); if(u.email) return u.email; }catch(e){}
    return '';
  }

  async function sendMail(){
    var doc=window._chLastDocText||''; if(!doc){ return; }
    var to=profileEmail();
    if(!to){ to=prompt('An welche E-Mail-Adresse soll das Dokument gesendet werden?')||''; }
    if(!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(to)){ if(to)alert('Ungültige E-Mail-Adresse.'); return; }
    var b=btn(); if(b){ b.innerHTML='⏳ Wird gesendet…'; }
    try{
      var r=await of('send-doc.php',{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({to:to,subject:'Ihr Dokument — ChatHelp',doc:doc})});
      var d=await r.json();
      if(d.success){ if(b){ b.innerHTML='✅ Gesendet an '+to; } setTimeout(function(){ if(b)b.classList.remove('on'); },4000); }
      else { if(b){ b.innerHTML='📧 Per E-Mail senden'; } alert('Konnte nicht gesendet werden. Bitte erneut versuchen.'); }
    }catch(e){ if(b){ b.innerHTML='📧 Per E-Mail senden'; } alert('Verbindungsfehler.'); }
  }
  window.chSendDocMail=sendMail;
})();
</script>
HTML;

$n=0;
$src = preg_replace('/<\/body>/', $bundle . "\n</body>", $src, 1, $n);
$changed=($src!==$start);
if($changed) file_put_contents($file,$src);
echo "ChatHelp — Doc e-posta raporu\n=============================\n\n";
echo ($n>0?"  ✓ Doc e-posta katmanı eklendi  →  $n\n":"  ✗ </body> yok!\n");
echo "\n".($changed?"DURUM: güncellendi. ✅\n":"Değişiklik yok.\n");
echo "GEREKLİ: send-doc.php yüklü olmalı.\n";
echo "SONRA: opcache-reset.php. SİL: rm apply-doc-email.php\n";
echo "Test: bir dilekçe oluştur -> altta '📧 Per E-Mail senden' -> profil e-postana gelir (SPAM kontrol).\n";
