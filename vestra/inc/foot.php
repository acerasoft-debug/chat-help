<footer>
  <div class="wrap foot">
    <div><b style="color:var(--ink)">VESTRA</b> — <?= t('verified B2B fashion wholesale') ?>
      <div style="margin-top:5px;opacity:.8">Acerasoft LLC · US</div></div>
    <div style="display:flex;gap:20px;flex-wrap:wrap">
      <a href="/shop"><?= t('Catalog') ?></a>
      <a href="/legal?doc=terms"><?= t('Terms') ?></a>
      <a href="/legal?doc=privacy"><?= t('Privacy') ?></a>
      <a href="/legal?doc=imprint"><?= t('Imprint') ?></a>
      <a href="/faq"><?= t('FAQ') ?></a>
      <a href="/register" class="acc"><?= t('Get started') ?></a>
    </div>
  </div>
</footer>
<div id="cnotice" style="display:none;position:fixed;left:14px;right:14px;bottom:14px;z-index:90;max-width:520px;margin:0 auto;background:var(--card,#17181c);border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:13px 16px;font-size:13px;color:var(--mut,#9a9ba1);box-shadow:0 10px 34px rgba(0,0,0,.45);gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap">
  <span>🍪 <?= t('VESTRA only uses essential cookies (session & language preference) — no tracking, no ads.') ?>
    <a href="/legal?doc=privacy" class="acc" style="margin-left:4px"><?= t('Privacy') ?></a></span>
  <button class="btn btn-p btn-sm" onclick="localStorage.setItem('vcookie_ok','1');document.getElementById('cnotice').style.display='none'">OK</button>
</div>
<script>
if(!localStorage.getItem('vcookie_ok')){ var _cn=document.getElementById('cnotice'); _cn.style.display='flex'; }
</script>
<script>
/* VESTRA cart — localStorage based (demo; Phase 1 moves server-side) */
var VCart = {
  key:'vestra_cart',
  all:function(){ try{ return JSON.parse(localStorage.getItem(this.key))||[] }catch(e){ return [] } },
  save:function(c){ localStorage.setItem(this.key, JSON.stringify(c)); this.refresh(); },
  add:function(item){
    var c=this.all(), i=c.findIndex(function(x){return x.id===item.id});
    if(i>=0){ c[i].qty=item.qty; c[i].unit=item.unit; } else { c.push(item); }
    this.save(c);
  },
  remove:function(id){ this.save(this.all().filter(function(x){return x.id!==id})); },
  clear:function(){ localStorage.removeItem(this.key); this.refresh(); },
  count:function(){ return this.all().reduce(function(n,x){return n+1},0); },
  total:function(){ return this.all().reduce(function(s,x){return s + x.qty*x.unit},0); },
  refresh:function(){
    var b=document.getElementById('cartCount'); if(!b) return;
    var n=this.count(); b.textContent=n; b.style.display = n>0?'grid':'none';
  }
};
document.addEventListener('DOMContentLoaded', function(){ VCart.refresh(); });
</script>
<script>
/* Password show/hide toggle — auto-applied to every type=password field on the page. */
(function(){
  var EYE = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
  var EYE_OFF = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a20.4 20.4 0 0 1 5.06-6.06M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a20.4 20.4 0 0 1-3.22 4.44M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>';
  var LBL_SHOW = <?= json_encode(t('Show password')) ?>, LBL_HIDE = <?= json_encode(t('Hide password')) ?>;
  document.querySelectorAll('input[type="password"]').forEach(function(inp){
    if (inp.dataset.pwToggled) return;
    inp.dataset.pwToggled = '1';
    var wrap = document.createElement('span');
    wrap.className = 'pwwrap';
    inp.parentNode.insertBefore(wrap, inp);
    wrap.appendChild(inp);
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'pwtoggle';
    btn.innerHTML = EYE;
    btn.setAttribute('aria-label', LBL_SHOW);
    wrap.appendChild(btn);
    btn.addEventListener('click', function(){
      var show = inp.type === 'password';
      inp.type = show ? 'text' : 'password';
      btn.innerHTML = show ? EYE_OFF : EYE;
      btn.setAttribute('aria-label', show ? LBL_HIDE : LBL_SHOW);
    });
  });
})();
</script>
<script>
/* Double-submit guard — every form, site-wide. The FIRST submit disables the
   form's submit buttons and shows a spinner (.btn-busy), any further submit of
   the same form is blocked. Prevents "tapped order 5× → 5 orders/invoices".
   Buttons are disabled a tick AFTER submission starts so their name/value still
   posts. Opt out per-form with data-nobusy. */
(function(){
  document.addEventListener('submit', function(e){
    var f = e.target;
    if (!f || f.tagName !== 'FORM' || f.hasAttribute('data-nobusy')) return;
    if (f.dataset.vbusy === '1') { e.preventDefault(); e.stopPropagation(); return; }
    f.dataset.vbusy = '1';
    setTimeout(function(){
      f.querySelectorAll('button[type="submit"],button:not([type]),input[type="submit"]').forEach(function(b){
        b.classList.add('btn-busy'); b.disabled = true;
      });
    }, 0);
  }, true);
  /* Back/forward cache restore (iOS/Android back button): re-arm the forms. */
  window.addEventListener('pageshow', function(){
    document.querySelectorAll('form[data-vbusy]').forEach(function(f){
      f.removeAttribute('data-vbusy');
      f.querySelectorAll('.btn-busy').forEach(function(b){ b.classList.remove('btn-busy'); b.disabled = false; });
    });
  });
})();
</script>
<script>
/* PWA: register the service worker on every page, and expose the push opt-in
   used by the homepage app box (and any future 🔔 buttons). */
if ('serviceWorker' in navigator) { navigator.serviceWorker.register('/sw.js').catch(function(){}); }
window.vestraEnablePush = async function(){
  try{
    if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) return 'unsupported';
    var reg = await navigator.serviceWorker.ready;
    if (await Notification.requestPermission() !== 'granted') return 'denied';
    var vk = (await (await fetch('/push?a=vapid')).json()).publicKey;
    if (!vk) return 'error';
    var pad = '='.repeat((4 - vk.length % 4) % 4);
    var raw = atob((vk + pad).replace(/-/g, '+').replace(/_/g, '/'));
    var key = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) key[i] = raw.charCodeAt(i);
    var sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key });
    var r = await fetch('/push?a=subscribe', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(sub) });
    if (r.status === 401) return 'signin';
    return (await r.json()).ok ? 'ok' : 'error';
  } catch (e) { return 'error'; }
};
</script>
<?php require_once __DIR__.'/tabbar.php'; ?>
</body>
</html>
