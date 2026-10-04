<?php
/**
 * ChatHelp — apply-fax-plans (CH_FAX_PLANS) — paket/fiyat sayfasindaki (#pnl-plans)
 *  her plan kartina, kullanicinin diline gore "X kostenlose Faxe / Monat" satiri:
 *  Basic 5 · Pro 25 · Elite 100. Additive: kart render olunca .chp-period altina
 *  altin bir satir eklenir (6 dil). Kirilgan anchor yok.
 * KULLANIM: pull2.php?key=...&files=apply-fax-plans.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fax-plans BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_FAX_PLANS')!==false) exit("Zaten ekli (CH_FAX_PLANS).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-fax-plans-js">
/* CH_FAX_PLANS — paket kartlarina yerellestirilmis ucretsiz-fax satiri */
try{(function(){
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  var T={
    de:function(n){ return n+' Gratis-Faxe / Monat'; },
    tr:function(n){ return 'Aylık '+n+' ücretsiz faks'; },
    en:function(n){ return n+' free faxes / month'; },
    fr:function(n){ return n+' fax gratuits / mois'; },
    es:function(n){ return n+' faxes gratis / mes'; },
    it:function(n){ return n+' fax gratuiti / mese'; }
  };
  function fmt(n){ var f=T[lang()]||T.de; return f(n); }
  function tick(){
    try{
      var pnl=document.getElementById('pnl-plans'); if(!pnl) return;
      var pers=pnl.querySelectorAll('.chp-period');
      Array.prototype.forEach.call(pers,function(per){
        var card=per.parentNode; if(!card) return;
        if(card.querySelector('.ch-fax-plan')) return;
        var nmEl=card.querySelector('.chp-name'); var t=nmEl?String(nmEl.textContent||'').toLowerCase():'';
        var n = (t.indexOf('elite')>=0)?100 : ((t.indexOf('pro')>=0)?25 : ((t.indexOf('basic')>=0)?5:0));
        if(n<=0) return;
        var line=document.createElement('div'); line.className='ch-fax-plan';
        line.style.cssText='margin:9px 0 2px;padding:6px 11px;border-radius:9px;background:linear-gradient(135deg,rgba(212,168,74,.16),rgba(212,168,74,.04));border:1px solid rgba(212,168,74,.32);color:#f0d493;font-size:12px;font-weight:800;text-align:center';
        line.textContent='📠 '+fmt(n);
        per.parentNode.insertBefore(line, per.nextSibling);
      });
    }catch(e){}
  }
  try{ setInterval(tick,800); }catch(e){}
})();}catch(e){}
</script>
HTMLBLOCK;

$pos=strripos($src,'</body>');
if($pos===false) exit("</body> bulunamadi\n");
$new=substr($src,0,$pos).$block."\n".substr($src,$pos);
$tmp=tempnam(sys_get_temp_dir(),'fp').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-faxplans-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_FAX_PLANS')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ CH_FAX_PLANS eklendi (".strlen($chk)." B)\n";
echo "✓ Paket kartlari: Basic 5 · Pro 25 · Elite 100 ucretsiz fax/ay (kullanicinin dilinde).\n";
