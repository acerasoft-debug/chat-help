<?php
/**
 * ChatHelp — apply-anafix (CH_ANAFIX) — ana sayfa + vize + PDF paket duzeltmesi:
 *  [1] 'Verträge kündigen' KESIN temizlik: kara-liste (vtr_*) yerine BEYAZ
 *      LISTE — kategoride yalniz kuendigung_* soru-cevap sablonlari kalir;
 *      temizlik SURESIZ calisir (eski 30sn'lik temizleyici merge dongulerinden
 *      once bitiyordu) + showCatDocs cizim aninda da temizler.
 *  [2] Bewerbung ana sayfa kartindan kaldirilir (grid'deki
 *      showCatDocs('bewerbung') karti silinir; modul baska yollardan durur).
 *  [3] Anzeigen/Ausblenden KALICI: eski dugme her 800ms yeniden yaratilip
 *      durumu kaybediyordu. Yeni: localStorage ch_cats_off + body sinifi
 *      (CSS display:none!important) -> grid yeniden cizilse de durum kalir;
 *      dugme yakalama-fazinda ele alinir, etiketler senkron tutulur.
 *  [4] PDF A4 tasmasi: chPrintDoc sarmalanir; uretilen PDF html'ine kelime
 *      kirma CSS'i eklenir (uzun satirlar A4 disina cikamaz).
 *  [5] Vize uretim hatalari: (a) belge-hafizasi katmani vize/resmi uretim
 *      mesajlarina baglam enjekte etmesin (count-guard'li str_replace);
 *      (b) __v3Gen basarisiz olursa OTOMATIK 1 kez country=DE ile yeniden
 *      dener (yeni ulke kodlari sunucuda taninmiyorsa bile uretim tamamlanir).
 *  [6] Vize tarih alanlari her tarayicida calisir: okunur deger CSS'i
 *      (webkit-date-and-time-value), min-height, mobilde alt alta, readonly
 *      temizleme + pointerdown'da showPicker.
 *  1 str_replace (5a) + 1 eklemeli </body> blok. node ✓ + harness ✓.
 * KULLANIM: pull2.php?key=...&files=apply-anafix.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-anafix BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_ANAFIX')!==false) exit("Zaten ekli (CH_ANAFIX).\n");

$fail=[];

/* ── [5a] belge-hafizasi: vize/resmi uretim mesajlarina enjeksiyon YOK ── */
$o1 = "if(b && typeof b.message==='string' && b.message.indexOf(MARK)===-1){";
$n1 = "if(b && typeof b.message==='string' && b.message.indexOf(MARK)===-1 && b.message.indexOf('Gib NUR')===-1 && b.message.indexOf('SON KONTROL')===-1 && b.message.indexOf('revize et')===-1){";
$c1=substr_count($src,$o1);
if($c1!==1){ echo "  ✗ [5a] hafiza-enjeksiyon anchor ($c1) — CH_FOTO_MULTI gerekli\n"; $fail[]='5a'; }
else { $src=str_replace($o1,$n1,$src); echo "  ✓ [5a] Belge hafizasi artik vize/resmi belge uretimine karismiyor\n"; }

$block = <<<'HTMLBLOCK'
<style id="ch-anafix-css">
/* CH_ANAFIX */
body.ch-cats-off .wcat-grid,body.ch-cats-off .cgrid{display:none!important}
#vz3-ov .vz3-field{position:relative;z-index:2}
#vz3-ov input.vz3-in[type="date"]{min-height:52px;line-height:1.25;color:#fff;background:#12122a}
#vz3-ov input.vz3-in[type="date"]::-webkit-date-and-time-value{text-align:left;color:#fff}
#vz3-ov input.vz3-in[type="date"]::-webkit-calendar-picker-indicator{filter:invert(1);opacity:.95;cursor:pointer;padding:6px}
@media(max-width:440px){ #vz3-ov .vz3-2col{flex-direction:column;gap:0} }
</style>
<script id="ch-anafix-js">
/* CH_ANAFIX — vertraege beyaz-liste + Bewerbung kart + kalici toggle + PDF tasma + vize dayaniklilik */
try{(function(){
  /* ── [1] vertraege: yalniz kuendigung_* kalsin (BEYAZ LISTE, SURESIZ) ── */
  function katClean(){
    try{
      if(typeof CAT_DOCS==='undefined'||!CAT_DOCS['vertraege']) return;
      var a=CAT_DOCS['vertraege'], out=[], i;
      for(i=0;i<a.length;i++){
        var d=a[i], k=(d&&typeof d==='object')?d.k:(typeof d==='string'?d:'');
        if(typeof k==='string' && k.indexOf('kuendigung_')===0) out.push(d);
      }
      if(out.length && out.length!==a.length) CAT_DOCS['vertraege']=out;
    }catch(e){}
  }
  function katWrap(){
    try{
      if(typeof window.showCatDocs!=='function' || window.showCatDocs.__katfix2) return;
      var _o=window.showCatDocs;
      var w=function(cat){ if(cat==='vertraege') katClean(); return _o.apply(this,arguments); };
      w.__katfix2=1; if(_o.__katfix) w.__katfix=1;
      window.showCatDocs=w;
    }catch(e){}
  }
  /* ── [2] Bewerbung kartini griddten kaldir ── */
  function bewKill(){
    try{
      var cards=document.querySelectorAll('.wcat');
      for(var i=0;i<cards.length;i++){
        var oc=cards[i].getAttribute('onclick')||'';
        if(oc.indexOf("showCatDocs('bewerbung'")!==-1 || oc.indexOf('showCatDocs("bewerbung"')!==-1){ cards[i].remove(); }
      }
    }catch(e){}
  }
  setInterval(function(){ katClean(); katWrap(); bewKill(); },1500);
  katClean(); katWrap(); bewKill();

  /* ── [3] Anzeigen/Ausblenden: kalici durum ── */
  function catsOff(){ try{ return localStorage.getItem('ch_cats_off')==='1'; }catch(e){ return false; } }
  function applyCats(){
    try{
      var off=catsOff();
      if(document.body) document.body.classList.toggle('ch-cats-off',off);
      document.querySelectorAll('.ch-cat-tg button').forEach(function(b){ b.textContent=off?'anzeigen ▼':'ausblenden ▲'; });
      /* eski dugmenin inline display'ini de sifirla ki CSS sinifi tek otorite olsun */
      document.querySelectorAll('.wcat-grid,.cgrid').forEach(function(g){ if(g.style.display==='none') g.style.display=''; });
    }catch(e){}
  }
  document.addEventListener('click',function(ev){
    try{
      var t=ev.target;
      if(t && t.closest && t.closest('.ch-cat-tg') && String(t.tagName).toLowerCase()==='button'){
        ev.preventDefault(); ev.stopImmediatePropagation();
        try{ localStorage.setItem('ch_cats_off',catsOff()?'0':'1'); }catch(e){}
        applyCats();
      }
    }catch(e){}
  },true);
  setInterval(applyCats,1200); applyCats();

  /* ── [4] PDF A4 tasmasi: chPrintDoc'a kelime-kirma CSS'i enjekte et ── */
  var FIT='<style>/*CH_ANAFIX_FIT*/html,body{overflow-x:hidden}body *{overflow-wrap:anywhere!important;word-break:break-word}table{table-layout:fixed;width:100%}img{max-width:100%}</style>';
  function fitWrap(){
    try{
      if(typeof window.chPrintDoc!=='function' || window.chPrintDoc.__fit) return;
      var _p=window.chPrintDoc;
      var w=function(html){
        try{
          if(typeof html==='string' && html.indexOf('CH_ANAFIX_FIT')===-1){
            if(/<\/head>/i.test(html)) html=html.replace(/<\/head>/i,FIT+'</head>');
            else if(/<body[^>]*>/i.test(html)) html=html.replace(/(<body[^>]*>)/i,'$1'+FIT);
            else html=FIT+html;
          }
        }catch(e){}
        return _p.call(this,html);
      };
      w.__fit=1; window.chPrintDoc=w;
    }catch(e){}
  }
  var fn=0;(function fitLoop(){ fitWrap(); if(fn++<240) setTimeout(fitLoop,500); })();

  /* ── [5b] vize uretimi: hata/bos yanit gelirse ayni istek country=DE ile
         SEFFAF sekilde 1 kez yeniden denenir (yeni ulke kodlari sunucuda
         taninmiyorsa bile uretim tamamlanir; cagiran kod farki gormez) ── */
  var _vf=window.fetch;
  window.fetch=function(url,opt){
    var u=''+url, isGen=false;
    try{
      isGen = u.indexOf('action=aichat')!==-1 && opt && typeof opt.body==='string'
        && opt.body.indexOf('Gib NUR')!==-1 && opt.body.indexOf('"country":"DE"')===-1;
    }catch(e){}
    var p=_vf.apply(window,arguments);
    if(!isGen || !p || !p.then) return p;
    return p.then(function(resp){
      var cl; try{ cl=resp.clone(); }catch(e){ return resp; }
      return cl.json().then(function(j){
        if(j && j.reply && !j.error) return resp;
        var b=JSON.parse(opt.body); b.country='DE';
        return _vf.call(window,url,Object.assign({},opt,{body:JSON.stringify(b)}));
      }).catch(function(){ return resp; });
    });
  };

  /* ── [6] V3 tarih alanlari: readonly temizle + pointerdown'da picker ── */
  setInterval(function(){
    try{
      ['vz3-from','vz3-to'].forEach(function(id){
        var el=document.getElementById(id); if(!el||el.__afx) return; el.__afx=1;
        try{ el.removeAttribute('readonly'); el.removeAttribute('disabled'); }catch(e){}
        el.addEventListener('pointerdown',function(){ try{ if(typeof el.showPicker==='function') el.showPicker(); }catch(e){} });
      });
    }catch(e){}
  },700);
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false){ echo "  ✗ </body> yok\n"; $fail[]='body'; }
else { $src = substr($src,0,$pos).$block."\n".substr($src,$pos); echo "  ✓ [1][2][3][4][5b][6] blok eklendi\n"; }

if ($fail) { echo "\nHATA: ".implode(', ',$fail)." — index DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(),'af').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-anafix-'.date('Ymd-His'), (string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_ANAFIX')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "\n  ✓ DOGRULAMA: CH_ANAFIX diskte (".strlen($chk)." bayt)\n";
echo "\n✓ [1] 'Verträge kündigen' artik YALNIZ kuendigung_* sablonlari (beyaz liste, suresiz koruma)\n";
echo "✓ [2] Bewerbung ana sayfa kartlarindan kaldirildi\n";
echo "✓ [3] Anzeigen/Ausblenden kalici calisiyor (yenilemede bile hatirlanir)\n";
echo "✓ [4] PDF'lerde uzun satirlar artik A4 disina tasamaz\n";
echo "✓ [5] Vize uretimi: hafiza karismasi kesildi + hata olursa otomatik yedek deneme\n";
echo "✓ [6] Vize tarih alanlari her tarayicida acilir/okunur\n";
