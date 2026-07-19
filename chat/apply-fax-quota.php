<?php
/**
 * ChatHelp — apply-fax-quota (CH_FAX_QUOTA) — paketlere aylik UCRETSIZ fax kotasi:
 *  Basic 5 · Pro 25 · Elite 100 · Free 0. Kota bitince 1,99€ (paketsiz 2,50€).
 *  [1] window.chFaxQuota() = {plan,limit,used,remaining,free}. Aylik sayac
 *      localStorage ch_fax_used (ay degisince sifirlanir).
 *  [2] Gercek fax gonderiminde (fetch send_fax basarili) sayac artar.
 *  [3] Konto'ya yerellestirilmis "Fax-Kontingent: u/l" bolumu (6 dil).
 *  Additive </body> scripti.
 * KULLANIM: pull2.php?key=...&files=apply-fax-quota.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fax-quota BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_FAX_QUOTA')!==false) exit("Zaten ekli (CH_FAX_QUOTA).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-fax-quota-js">
/* CH_FAX_QUOTA — aylik ucretsiz fax kotasi + Konto gosterimi (yerellestirilmis) */
try{(function(){
  var QUOTA={free:0, basic:5, pro:25, elite:100};
  function plan(){
    try{ var a=[localStorage.getItem('ch_plan'), (JSON.parse(localStorage.getItem('ch_user')||'{}')||{}).plan, (window.P&&window.P.plan)];
      var p=(a.filter(Boolean).join(' ')||'').toLowerCase();
      if(p.indexOf('elite')>=0) return 'elite';
      if(p.indexOf('pro')>=0) return 'pro';
      if(p.indexOf('basic')>=0) return 'basic';
      return 'free';
    }catch(e){ return 'free'; }
  }
  function ym(){ var d=new Date(); return d.getFullYear()+'-'+(d.getMonth()+1); }
  function used(){ try{ var o=JSON.parse(localStorage.getItem('ch_fax_used')||'{}'); return (o.m===ym())?(o.n||0):0; }catch(e){ return 0; } }
  function inc(){ try{ localStorage.setItem('ch_fax_used',JSON.stringify({m:ym(),n:used()+1})); }catch(e){} }
  function quota(){ var pl=plan(), l=QUOTA[pl]||0, u=used(); return {plan:pl,limit:l,used:u,remaining:Math.max(0,l-u),free:(l-u)>0}; }
  window.chFaxQuota=quota;

  /* [2] gercek fax gonderiminde say */
  if(!window.__chFaxCountWrap && window.fetch){
    window.__chFaxCountWrap=1;
    var _f=window.fetch;
    window.fetch=function(){
      var a=arguments;
      try{
        var url=(a[0]&&a[0].url)||a[0]||''; var body=(a[1]&&a[1].body)||'';
        var isFax=(String(url).indexOf('postversand')>=0 || String(body).indexOf('send_fax')>=0) && String(body).indexOf('send_fax')>=0;
        if(isFax){ return _f.apply(this,a).then(function(r){ try{ if(r&&r.ok) inc(); }catch(e){} return r; }); }
      }catch(e){}
      return _f.apply(this,a);
    };
  }

  /* [3] Konto gosterimi */
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  var T={
    de:{t:'📠 Fax-Kontingent', s:'{u} von {l} Gratis-Faxen diesen Monat · Rest: {r}', none:'Fax ist in Basic/Pro/Elite enthalten — mit monatlichem Gratis-Kontingent.', over:'Gratis-Kontingent aufgebraucht — weitere Faxe je 1,99 €.'},
    tr:{t:'📠 Faks Kotası', s:'Bu ay {u}/{l} ücretsiz faks · Kalan: {r}', none:'Faks Basic/Pro/Elite pakete dahildir — aylık ücretsiz kota ile.', over:'Ücretsiz kota bitti — ek faks 1,99 €.'},
    en:{t:'📠 Fax Quota', s:'{u} of {l} free faxes this month · Left: {r}', none:'Fax is included in Basic/Pro/Elite — with a monthly free quota.', over:'Free quota used up — extra faxes 1.99 € each.'},
    fr:{t:'📠 Quota de fax', s:'{u} sur {l} fax gratuits ce mois · Reste : {r}', none:'Le fax est inclus dans Basic/Pro/Elite — quota mensuel gratuit.', over:'Quota gratuit épuisé — fax supplémentaires 1,99 € pièce.'},
    es:{t:'📠 Cuota de fax', s:'{u} de {l} faxes gratis este mes · Restan: {r}', none:'El fax está incluido en Basic/Pro/Elite — con cuota mensual gratis.', over:'Cuota gratis agotada — faxes adicionales 1,99 € cada uno.'},
    it:{t:'📠 Quota fax', s:'{u} di {l} fax gratuiti questo mese · Restano: {r}', none:'Il fax è incluso in Basic/Pro/Elite — con quota mensile gratuita.', over:'Quota gratuita esaurita — fax extra 1,99 € ciascuno.'}
  };
  function inject(){
    try{
      var host=document.querySelector('#pnl-konto .pnl-scroll'); if(!host) return;
      var q=quota(), t=T[lang()]||T.de;
      var sec=document.getElementById('ch-faxquota-sec');
      var bodyHtml;
      if(q.limit<=0){ bodyHtml='<div style="font-size:12.5px;color:var(--t3,#9fb4d8);line-height:1.5">'+t.none+'</div>'; }
      else {
        var line=t.s.replace('{u}',q.used).replace('{l}',q.limit).replace('{r}',q.remaining);
        var pct=Math.min(100,Math.round(q.used/q.limit*100));
        bodyHtml='<div style="font-size:13px;color:#fff;font-weight:600">'+line+'</div>'
          +'<div style="height:7px;border-radius:100px;background:rgba(255,255,255,.08);margin:9px 0 4px;overflow:hidden"><div style="height:100%;width:'+pct+'%;background:linear-gradient(90deg,#e8c877,#d4a84a)"></div></div>'
          +(q.remaining<=0?'<div style="font-size:11.5px;color:#e0a0a0;margin-top:4px">'+t.over+'</div>':'');
      }
      var inner='<div class="ksec-hdr"><svg viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="1.8" width="14" height="14"><path d="M6 9V2h12v7M6 18h12v4H6zM6 14h.01M10 14h8"/></svg><div class="ksec-hdr-t">'+t.t+'</div></div><div class="ksec-body">'+bodyHtml+'</div>';
      if(!sec){ sec=document.createElement('div'); sec.id='ch-faxquota-sec'; sec.className='ksec'; sec.innerHTML=inner;
        var uinfo=document.getElementById('konto-user-info');
        if(uinfo&&uinfo.parentNode) uinfo.parentNode.insertBefore(sec, uinfo.nextSibling); else host.appendChild(sec);
      } else if(sec.getAttribute('data-sig')!==q.plan+q.used){ sec.innerHTML=inner; }
      sec.setAttribute('data-sig',q.plan+q.used);
    }catch(e){}
  }
  try{ setInterval(inject,1200); }catch(e){}
})();}catch(e){}
</script>
HTMLBLOCK;

$pos=strripos($src,'</body>');
if($pos===false) exit("</body> bulunamadi\n");
$new=substr($src,0,$pos).$block."\n".substr($src,$pos);
$tmp=tempnam(sys_get_temp_dir(),'fq').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-faxquota-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_FAX_QUOTA')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ CH_FAX_QUOTA eklendi (".strlen($chk)." B)\n";
echo "✓ Kota: Basic 5 · Pro 25 · Elite 100 · aylik sayac + Konto gosterimi (6 dil).\n";
