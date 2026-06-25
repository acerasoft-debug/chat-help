<footer>
  <div class="wrap foot">
    <div><b style="color:var(--ink)">VESTRA</b> — verified B2B fashion wholesale
      <div style="margin-top:5px;opacity:.8">[acerasoft LLC · US]</div></div>
    <div style="display:flex;gap:20px;flex-wrap:wrap">
      <a href="shop.php">Catalog</a>
      <a href="legal.php?doc=terms">Terms</a>
      <a href="legal.php?doc=privacy">Privacy</a>
      <a href="legal.php?doc=imprint">Imprint</a>
      <a href="index.php#join" class="acc">Join the waitlist</a>
    </div>
  </div>
</footer>
<script>
/* VESTRA cart — localStorage based (demo; Phase 1 moves to server + escrow) */
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
