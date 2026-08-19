<?php
/**
 * ChatHelp — apply-fax-pay (CH_FAX_PAY) — fax over-kota -> ANA odeme (Stripe).
 *  Aylik ucretsiz kota (Basic5/Pro25/Elite100) bitince fax gonderimi yakalanir:
 *   • send_fax istegi kaydedilir (ch_fax_pending_req)
 *   • 1,99€ tek-odeme checkout'u acilir (mevcut stripe-checkout.php, doc_einfach)
 *   • payment=success donusunde kaydedilen fax OTOMATIK gonderilir.
 *  window.chFaxQuota().remaining>0 ise hicbir sey degismez (ucretsiz gider).
 *  Additive fetch-intercept — IMZA5'e dokunmaz.
 * KULLANIM: pull2.php?key=...&files=apply-fax-pay.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fax-pay BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_FAX_PAY')!==false) exit("Zaten ekli (CH_FAX_PAY).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-fax-pay-js">
/* CH_FAX_PAY — fax over-kota -> 1,99€ tek-odeme -> donusunde otomatik gonder */
try{(function(){
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  var T={
    de:{need:'Gratis-Fax-Kontingent aufgebraucht — dieses Fax kostet 1,99 €. Weiter zur Zahlung …', done:'✅ Zahlung erfolgreich — Ihr Fax wird jetzt gesendet.', okq:'📠 Fax gesendet.'},
    tr:{need:'Ücretsiz faks kotası bitti — bu faks 1,99 €. Ödemeye yönlendiriliyorsunuz …', done:'✅ Ödeme başarılı — faksınız şimdi gönderiliyor.', okq:'📠 Faks gönderildi.'},
    en:{need:'Free fax quota used up — this fax costs 1.99 €. Redirecting to payment …', done:'✅ Payment successful — your fax is being sent now.', okq:'📠 Fax sent.'},
    fr:{need:'Quota de fax gratuit épuisé — ce fax coûte 1,99 €. Redirection vers le paiement …', done:'✅ Paiement réussi — votre fax est en cours d’envoi.', okq:'📠 Fax envoyé.'},
    es:{need:'Cuota de fax gratis agotada — este fax cuesta 1,99 €. Redirigiendo al pago …', done:'✅ Pago correcto — su fax se está enviando.', okq:'📠 Fax enviado.'},
    it:{need:'Quota fax gratuita esaurita — questo fax costa 1,99 €. Reindirizzamento al pagamento …', done:'✅ Pagamento riuscito — il fax è in invio.', okq:'📠 Fax inviato.'}
  };
  function toast(m){ try{ var d=document.getElementById('ch-ez-toast'); if(!d){ d=document.createElement('div'); d.id='ch-ez-toast'; d.style.cssText='position:fixed;left:50%;bottom:90px;transform:translateX(-50%);z-index:100002;max-width:90%;background:#16162a;border:1px solid #d4a84a;color:#f0f0f6;padding:13px 18px;border-radius:14px;box-shadow:0 12px 34px rgba(0,0,0,.5);font-size:13.5px;text-align:center'; document.body.appendChild(d); } d.textContent=m; d.style.opacity='1'; clearTimeout(d._t); d._t=setTimeout(function(){ d.style.opacity='0'; },7000); }catch(e){} }
  function startCheckout(){
    var t=T[lang()]||T.de; try{ toast(t.need); }catch(e){}
    fetch('stripe-checkout.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({type:'payment',plan:'doc_einfach'})})
      .then(function(r){ return r.json(); })
      .then(function(d){ if(d&&d.url){ location.href=d.url; } else if(typeof window.stripeCheckout==='function'){ try{ window.stripeCheckout('doc_einfach'); }catch(e){} } })
      .catch(function(){ if(typeof window.stripeCheckout==='function'){ try{ window.stripeCheckout('doc_einfach'); }catch(e){} } });
  }
  /* [1] over-kota fax yakala */
  if(!window.__chFaxPayWrap && window.fetch){
    window.__chFaxPayWrap=1;
    var _f=window.fetch;
    window.fetch=function(){
      var a=arguments;
      try{
        var u=String((a[0]&&a[0].url)||a[0]||''); var body=(a[1]&&a[1].body)||'';
        var isFax = String(body).indexOf('send_fax')>=0 && u.indexOf('postversand')>=0;
        if(isFax && !window.__chFaxPaidOnce){
          var q = window.chFaxQuota ? window.chFaxQuota() : {remaining:1};
          if(q && q.remaining<=0){
            try{ localStorage.setItem('ch_fax_pending_req', JSON.stringify({u:u, body:String(body)})); }catch(e){}
            startCheckout();
            return Promise.resolve(new Response(JSON.stringify({queued_payment:true, status:'payment_required'}), {status:200, headers:{'Content-Type':'application/json'}}));
          }
        }
      }catch(e){}
      return _f.apply(this,a);
    };
  }
  /* [2] odeme donusu -> bekleyen fax otomatik gonder */
  (function(){
    try{
      var p=new URLSearchParams(location.search);
      if(p.get('payment')==='success'){
        var pj=localStorage.getItem('ch_fax_pending_req');
        if(pj){
          localStorage.removeItem('ch_fax_pending_req');
          var req=JSON.parse(pj); var t=T[lang()]||T.de; window.__chFaxPaidOnce=true;
          setTimeout(function(){ toast(t.done); }, 400);
          setTimeout(function(){
            try{
              (window.__chRawFetch||window.fetch)(req.u,{method:'POST',headers:{'Content-Type':'application/json'},body:req.body})
                .then(function(r){ return r.json().catch(function(){ return {}; }); })
                .then(function(d){ try{ toast(t.okq); }catch(e){} })
                .catch(function(){});
            }catch(e){}
          }, 1500);
        }
      }
    }catch(e){}
  })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos=strripos($src,'</body>');
if($pos===false) exit("</body> bulunamadi\n");
$new=substr($src,0,$pos).$block."\n".substr($src,$pos);
$tmp=tempnam(sys_get_temp_dir(),'fp').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-faxpay-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_FAX_PAY')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ CH_FAX_PAY eklendi (".strlen($chk)." B)\n";
echo "✓ Kota bitince fax -> 1,99€ tek-odeme (price_1Tf71jA…einfach) -> donusunde otomatik gonderim (6 dil).\n";
echo "  NOT: gercek para akisi — canli test gerekir (kota=0 iken fax -> odeme -> fax gitmeli).\n";
