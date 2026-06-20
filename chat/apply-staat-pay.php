<?php
/**
 * ChatHelp — Staatliche Formulare gerçek ödeme (form-engine.js)
 *  • exportFormPDF: free kullanıcı Guthaben varsa PDF üretir (kredi tüketir)
 *  • showPaywall: "Portal'a git" yerine GERÇEK ödeme — 1,99€ tek + 13,99 abonelik (Stripe)
 * Ödeme sonrası /chat/?payment=success -> einzelkauf 1 kredi verir -> formu tekrar aç, PDF iner.
 * KULLANIM: chat-help.com/chat/apply-staat-pay.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/form-engine.js';
if (!file_exists($file)) { exit("HATA: form-engine.js yok.\n"); }
$src=file_get_contents($file); $start=$src;
file_put_contents($file . '.bak-staatpay-' . date('Ymd-His'), $src);
$rep=[];
function rp(&$src,$label,$old,$new){ global $rep; $n=substr_count($src,$old); if($n>0)$src=str_replace($old,$new,$src); $rep[]=[$label,$n]; }

if (strpos($src, 'cps-single') !== false) { exit("Zaten ekli (cps-single).\n"); }

/* ── #1 exportFormPDF: Guthaben (kredi) onuru ── */
rp($src, '#1 Guthaben kontrolü (exportFormPDF)',
"    if(!logged || plan === 'free'){\n      const msg = logged",
"    if(!logged || plan === 'free'){\n      var _cr=parseInt(localStorage.getItem('ch_credits')||'0',10)||0;\n      if(_cr>0){ try{localStorage.setItem('ch_credits',String(_cr-1));}catch(e){} _generatePDF(title, filename); return; }\n      const msg = logged");

/* ── #2 Paywall buton HTML: gerçek ödeme seçenekleri ── */
$oldBtn = '        <button type="button" class="ch-paywall-go"
          style="padding:13px;background:linear-gradient(135deg,#d4a84a,#c09030);border:none;border-radius:12px;color:#fff;font-size:14px;font-weight:700;cursor:pointer">
          📱 Zum ChatHelp Portal
        </button>';
$newBtn = '        <button type="button" class="cps-single"
          style="display:flex;align-items:center;gap:12px;padding:14px;background:rgba(212,168,74,.1);border:1.5px solid rgba(212,168,74,.35);border-radius:13px;color:#fff;cursor:pointer">
          <span style="font-size:21px">📄</span>
          <span style="flex:1;text-align:left"><span style="display:block;font-size:14px;font-weight:700">Dieses Formular</span><span style="font-size:11px;color:#9090a8">Einmalig · sofort als PDF</span></span>
          <span style="font-size:16px;font-weight:800;color:#d4a84a">1,99 €</span>
        </button>
        <div style="display:flex;align-items:center;gap:8px;margin:2px 0"><div style="flex:1;height:1px;background:#1e1e35"></div><span style="font-size:10px;color:#404060">ODER</span><div style="flex:1;height:1px;background:#1e1e35"></div></div>
        <button type="button" class="cps-abo"
          style="display:flex;align-items:center;gap:12px;padding:13px;background:rgba(255,255,255,.04);border:1px solid #23233f;border-radius:13px;color:#fff;cursor:pointer">
          <span style="font-size:19px">⭐</span>
          <span style="flex:1;text-align:left"><span style="display:block;font-size:13.5px;font-weight:700">Basic-Abo</span><span style="font-size:11px;color:#9090a8">Unbegrenzt Formulare</span></span>
          <span style="font-size:13px;font-weight:800;color:#6094ff">13,99 €/Mo</span>
        </button>';
rp($src, '#2 Paywall ödeme butonları', $oldBtn, $newBtn);

/* ── #3 Buton handler: gerçek Stripe checkout ── */
$oldH = "    overlay.querySelector('.ch-paywall-go').addEventListener('click', function(){
      window.open('https://chat-help.com/chat/', '_blank', 'noopener');
      close();
    });";
$newH = "    async function cpsCheckout(type, plan){
      try{
        var email=''; try{ email=(JSON.parse(localStorage.getItem('ch_user')||'{}').email)||''; }catch(e){}
        var sid=(document.cookie.match(/ch_sid=([^;]+)/)||[])[1] || Math.random().toString(36).slice(2);
        var res=await fetch('https://chat-help.com/chat/stripe-checkout.php',{method:'POST',headers:{'Content-Type':'application/json'},
          body:JSON.stringify({type:type, plan:plan, doc_type:'staatliche', session_id:sid, email:email})});
        var d=await res.json();
        if(d.url){ window.location.href=d.url; } else { alert('Fehler: '+(d.error||'Unbekannt')); }
      }catch(e){ alert('Verbindungsfehler. Bitte erneut versuchen.'); }
    }
    overlay.querySelector('.cps-single').addEventListener('click', function(){ cpsCheckout('payment','doc_einfach'); });
    overlay.querySelector('.cps-abo').addEventListener('click', function(){ cpsCheckout('subscription','basic'); });";
rp($src, '#3 Stripe checkout handler', $oldH, $newH);

$changed=($src!==$start);
if($changed) file_put_contents($file,$src);
echo "ChatHelp — Staatliche ödeme raporu\n==================================\n\n";
foreach($rep as [$l,$n]) echo ($n>0?"  ✓ ":"  ✗ ").$l."  →  $n\n";
echo "\n".($changed?"DURUM: form-engine.js güncellendi. ✅\n":"DURUM: Değişiklik yok.\n");
echo "\nSONRA: opcache-reset.php (JS cache için tarayıcıda Ctrl+Shift+R). SİL: rm apply-staat-pay.php\n";
echo "Test: staatliche form -> PDF indir -> 1,99€/Abo paywall -> öde -> /chat/ kredi -> formu tekrar aç -> PDF iner.\n";
