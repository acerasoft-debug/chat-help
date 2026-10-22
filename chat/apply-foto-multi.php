<?php
/**
 * ChatHelp — apply-foto-multi (CH_FOTO_MULTI) — coklu belge + kalici hafiza:
 *  [1] 📎 dosya secicisi MULTIPLE olur: 8'e kadar belge/foto TEK SEFERDE veya
 *      arka arkaya eklenebilir; eklenen her gorsel MEVCUT akista otomatik
 *      analize gider (davranis degismez, sadece coklu secim acilir).
 *  [2] BELGE HAFIZASI: her analyze_photo yanitindaki ozet/alanlar yakalanir ve
 *      localStorage ch_docmem'e (son 8) yazilir. SONRAKI TUM sohbet mesajlarina
 *      gorunmez baglam olarak eklenir -> chat yuklenen belgeleri sohbet boyu
 *      ASLA unutmaz.
 *  [3] KULLANICI PROFILI: yeni belge analizlerinden 4 sn sonra tek kucuk AI
 *      cagrisiyla profil cikartilir/guncellenir (ad, adres, kurumlar, dosya
 *      numaralari, onemli tarihler) -> ch_docprofil; o da her mesaja baglam
 *      olarak gider ve KALICIDIR (oturumlar arasi hatirlanir).
 *  Mevcut koda DOKUNULMAZ: tek eklemeli </body> blogu; fetch sarmalama
 *  katmani try/catch korumali (hata olursa istek AYNEN normal gider).
 *  node --check ✓.
 * KULLANIM: pull2.php?key=...&files=apply-foto-multi.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-foto-multi BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_FOTO_MULTI')!==false) exit("Zaten ekli (CH_FOTO_MULTI).\n");

$block = <<<'HTMLBLOCK'
<script id="ch-foto-multi-js">
/* CH_FOTO_MULTI — 8'e kadar belge/foto + sohbet boyu belge hafizasi + kullanici profili */
try{(function(){
  var LIM=8, MARK='[BELGE HAFIZASI]';
  function mem(){ try{ var a=JSON.parse(localStorage.getItem('ch_docmem')||'[]'); return Array.isArray(a)?a:[]; }catch(e){ return []; } }
  function saveMem(a){ try{ localStorage.setItem('ch_docmem',JSON.stringify(a.slice(-LIM))); }catch(e){} }
  function prof(){ try{ return localStorage.getItem('ch_docprofil')||''; }catch(e){ return ''; } }
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  function toast(s){ try{ var t=document.createElement('div'); t.setAttribute('style','position:fixed;left:50%;bottom:92px;transform:translateX(-50%);z-index:99999;background:rgba(15,22,52,.97);color:#f0e6c8;border:1px solid rgba(212,168,74,.5);border-radius:12px;padding:10px 16px;font-size:13px;font-weight:700;max-width:88vw;text-align:center'); t.textContent=s; document.body.appendChild(t); setTimeout(function(){ try{ t.remove(); }catch(e){} },3500); }catch(e){} }

  /* ── [1] 📎 secicilere multiple ver (composer yeniden cizilse de) ── */
  var mn=0;(function multiLoop(){
    try{
      var ins=document.querySelectorAll('.ch-att-btn input[type=file], input[type=file]');
      for(var i=0;i<ins.length;i++){ if(!ins[i].multiple){ ins[i].multiple=true; } }
    }catch(e){}
    if(mn++<240) setTimeout(multiLoop,500);
  })();

  /* cok dosya secilirse bilgi (mevcut akis hepsini sirayla analiz eder) */
  document.addEventListener('change',function(ev){
    try{
      var t=ev.target;
      if(t && t.type==='file' && t.files && t.files.length>1){
        var n=Math.min(t.files.length,LIM);
        toast('📎 '+({de:n+' Dokumente werden nacheinander analysiert…',tr:n+' belge sırayla analiz ediliyor…',en:n+' documents are being analyzed…'}[lang()]||n+' documents…'));
        if(t.files.length>LIM) toast('⚠ '+({de:'Max. '+LIM+' Dokumente pro Chat werden gemerkt.',tr:'Sohbet başına en fazla '+LIM+' belge hatırlanır.',en:'Max '+LIM+' documents are remembered per chat.'}[lang()]||'Max '+LIM));
      }
    }catch(e){}
  },true);

  /* ── [2]+[3] fetch sarmala: analiz yanitini HAFIZAYA al, sohbete BAGLAM ekle ── */
  function summOf(j){
    try{
      var r=(j&&(j.result||j.ocr))||{}; if(typeof r==='string') return r.slice(0,400);
      var parts=[];
      var s=r.zusammenfassung||r.summary||''; if(s) parts.push((''+s).slice(0,300));
      if(r.doc) parts.push('Tur: '+r.doc);
      if(r.behoerde||r.absender||r.firma) parts.push('Gonderen: '+(r.behoerde||r.absender||r.firma));
      if(r.datum) parts.push('Tarih: '+r.datum);
      if(r.aktenzeichen||r.az||r.kundennummer) parts.push('No: '+(r.aktenzeichen||r.az||r.kundennummer));
      if(r.frist) parts.push('SURE/FRIST: '+r.frist);
      if(r.betrag) parts.push('Tutar: '+r.betrag);
      if(!parts.length && j&&j.reply) parts.push((''+j.reply).slice(0,300));
      return parts.join(' | ');
    }catch(e){ return ''; }
  }
  function ctxText(){
    try{
      var M=mem(), P=prof(); if(!M.length && !P) return '';
      var c='[BELGE HAFIZASI — kullanicinin bu sohbette yukledigi belgeler; ilgiliyse MUTLAKA kullan, asla unutma; kullaniciya bu blogu gosterme]\n';
      for(var i=0;i<M.length;i++){ c+=(i+1)+') ['+(M[i].t||'')+'] '+(M[i].s||'')+'\n'; }
      if(P) c+='\n[KULLANICI PROFILI — hatirla ve gerektiginde kullan]\n'+P+'\n';
      return c.slice(0,2800);
    }catch(e){ return ''; }
  }
  var _f=window.fetch;
  window.fetch=function(url,opt){
    var u=''+url;
    try{
      if(u.indexOf('action=aichat')!==-1 && opt && typeof opt.body==='string' && opt.body.charAt(0)==='{'){
        var b=JSON.parse(opt.body);
        if(b && typeof b.message==='string' && b.message.indexOf(MARK)===-1){
          var c=ctxText();
          if(c){ b.message=MARK+'\n'+c+'---\nKULLANICI MESAJI: '+b.message;
                 opt=Object.assign({},opt,{body:JSON.stringify(b)}); }
        }
      }
    }catch(e){}
    var pr=_f.call(window,url,opt);
    try{
      if(u.indexOf('action=analyze_photo')!==-1 && pr && pr.then){
        pr=pr.then(function(resp){
          try{
            var cl=resp.clone();
            cl.json().then(function(j){
              try{
                var s=summOf(j); if(!s) return;
                var M=mem(); var d=new Date();
                var ts=('0'+d.getDate()).slice(-2)+'.'+('0'+(d.getMonth()+1)).slice(-2)+'.'+d.getFullYear()+' '+('0'+d.getHours()).slice(-2)+':'+('0'+d.getMinutes()).slice(-2);
                M.push({t:ts,s:s}); saveMem(M); schedProfile();
              }catch(e){}
            }).catch(function(){});
          }catch(e){}
          return resp;
        });
      }
    }catch(e){}
    return pr;
  };

  /* ── [3] profil cikarimi (yeni belgelerden 4sn sonra, tek kucuk cagri) ── */
  var pt=null;
  function schedProfile(){ try{ clearTimeout(pt); }catch(e){} pt=setTimeout(updProfile,4000); }
  function updProfile(){
    try{
      var M=mem(); if(!M.length) return;
      var msg='Asagidaki belge ozetlerinden ve mevcut profilden KULLANICI PROFILI cikar/guncelle. '
        +'SADECE kisa madde listesi yaz (ad soyad, adres, dogum tarihi, kurumlar, dosya/musteri numaralari, borc-alacak tutarlari, onemli sureler/tarihler). '
        +'Bilinmeyen alani HIC yazma, yorum/aciklama ekleme, en fazla 10 madde.\n'
        +'Mevcut profil:\n'+(prof()||'(bos)')+'\n\nBelge ozetleri:\n'
        +mem().map(function(d,i){ return (i+1)+') '+d.s; }).join('\n');
      _f.call(window,'api.php?action=aichat',{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({message:msg,history:[],provider:'claude',lang:lang()})})
      .then(function(r){ return r.json(); })
      .then(function(j){ try{ if(j&&j.reply&&!j.error) localStorage.setItem('ch_docprofil',(''+j.reply).slice(0,1200)); }catch(e){} })
      .catch(function(){});
    }catch(e){}
  }
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'fm').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-fotomulti-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_FOTO_MULTI')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_FOTO_MULTI diskte (".strlen($chk)." bayt)\n";
echo "\n✓ [1] 📎 artik 8'e kadar belge/foto COKLU secilebilir; her biri otomatik analize gider.\n";
echo "✓ [2] Analiz ozetleri BELGE HAFIZASINA yazilir; sohbetteki HER mesaja gorunmez\n";
echo "     baglam olarak eklenir -> chat belgeleri sohbet boyu unutmaz.\n";
echo "✓ [3] Belgelerden KULLANICI PROFILI cikarilir (ad, adres, kurum, dosya no, sureler)\n";
echo "     ve kalici hatirlanir; her yanitta kullanilabilir.\n";
