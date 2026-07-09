<footer>
  <div class="wrap foot">
    <div><b style="color:var(--ink)">VESTRA</b> — <?= t('verified B2B fashion wholesale') ?>
      <div style="margin-top:5px;opacity:.8">acerasoft LLC · US</div></div>
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
</body>
</html>
