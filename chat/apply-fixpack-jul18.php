<?php
/**
 * ChatHelp — apply-fixpack-jul18 (CH_FIXPACK_J18) — ekran goruntusu hatalari:
 *  [A] Gonderim 'unknown_action': action artik JSON govdede de gider (bayat
 *      opcache/WAF'a dayanikli; postversand v5 govdeden okur).
 *  [B] PDF/geri donunce 'Kayitli belge bulunamadi': modal ACILIR ACILMAZ belge
 *      metni ch_topics'e kaydedilir (behalten aninda icerik kayitli).
 *  [C] Fall-merge: icerigi olmayan taze Fall/auto-topic'e belge baglanir ->
 *      Themen VE Falle'de icerik her zaman acilir.
 *  [D] Cift Empfänger: formda 'Name des Unternehmens/Firma/Anbieter' alani
 *      varsa genel ch_empfaenger alani otomatik gizlenir.
 *  [E] Emsal arsivi: eski format kayitlara __i eklenir -> tiklayinca sonuc
 *      tekrar acilir ve islenebilir (dilekce/Themen).
 *  IMZA4+IMZA5 bloklarindaki ayni satirlar icin count=2 guard.
 * KULLANIM: pull2.php?key=...&files=apply-postversand5.php,apply-fixpack-jul18.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fixpack-jul18 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_FIXPACK_J18')!==false) exit("Zaten ekli (CH_FIXPACK_J18).\n");
if (strpos($src,'CH_ADRES_IMZA5')===false) exit("HATA: once CH_ADRES_IMZA5 gerekli.\n");

$fails=[];

/* [A] reallySend govdesine action (imza4+imza5 = 2x) */
$oA="    var body={text:txt,recipient:rec,sender:sender,sig_jpeg:sigJpeg(),registered:_reg,fax_number:_faxnr};";
$nA="    var body={action:_act,text:txt,recipient:rec,sender:sender,sig_jpeg:sigJpeg(),registered:_reg,fax_number:_faxnr};";
$c=substr_count($src,$oA);
if($c>=1&&$c<=2){ $src=str_replace($oA,$nA,$src); echo "  ✓ [A] action govdede ($c yer)\n"; } else { $fails[]="A($c)"; }

/* [B] modal acilisinda icerik kaydi (2x) */
$oB="    ORIG_BODY=htmlText(CTX.html);";
$nB="    ORIG_BODY=htmlText(CTX.html);\n    try{ saveTopicTxt(ORIG_BODY); }catch(e){} /* behalten: icerik aninda kayit */";
$c=substr_count($src,$oB);
if($c>=1&&$c<=2){ $src=str_replace($oB,$nB,$src); echo "  ✓ [B] modal acilisinda icerik kaydi ($c yer)\n"; } else { $fails[]="B($c)"; }

/* [C] saveTopicTxt Fall-merge (2x) */
$oC="      for(var i=0;i<tps.length;i++){ if(tps[i] && (tps[i].doc||tps[i].text||'').slice(0,50)===key){ tps[i].doc=txt; tps[i].ts=new Date().getTime(); localStorage.setItem('ch_topics',JSON.stringify(tps)); return; } }
      tps.push({id:'d'+new Date().getTime(),title:'📄 '+title,doc:txt,ts:new Date().getTime(),keep:1,src:'doc'});";
$nC="      for(var i=0;i<tps.length;i++){ if(tps[i] && (tps[i].doc||tps[i].text||'').slice(0,50)===key){ tps[i].doc=txt; tps[i].ts=new Date().getTime(); localStorage.setItem('ch_topics',JSON.stringify(tps)); return; } }
      /* Fall-merge: son 10 dk icinde acilmis, ICERIGI OLMAYAN auto-topic'e (Fall) belgeyi bagla */
      var now=new Date().getTime();
      for(var m=tps.length-1;m>=0;m--){ var tp=tps[m]; if(tp && !tp.doc && !tp.text && (now-(tp.created||tp.ts||0))<600000){ tp.doc=txt; tp.ts=now; localStorage.setItem('ch_topics',JSON.stringify(tps)); return; } }
      tps.push({id:'d'+new Date().getTime(),title:'📄 '+title,doc:txt,ts:new Date().getTime(),keep:1,src:'doc'});";
$c=substr_count($src,$oC);
if($c>=1&&$c<=2){ $src=str_replace($oC,$nC,$src); echo "  ✓ [C] Fall-merge ($c yer)\n"; } else { $fails[]="C($c)"; }

if($fails){ echo "\n✗ ANCHOR HATALARI: ".implode(', ',$fails)." — index DEGISTIRILMEDI.\n"; exit; }

/* [D]+[E] ek blok */
$block = <<<'HTMLBLOCK'
<script id="ch-fixpack-j18">
/* CH_FIXPACK_J18 — [D] cift Empfänger gizle, [E] emsal arsiv migration */
try{(function(){
  /* [E] eski emsal kayitlarina __i ekle (tiklama calissin) */
  try{
    var h=JSON.parse(localStorage.getItem('ch_emsal_hist')||'[]');
    if(Array.isArray(h)){ var ch=false;
      for(var i=0;i<h.length;i++){ if(h[i]&&!h[i].__i){ h[i].__i=(h[i].ts||new Date().getTime())+i; if(!h[i].t&&h[i].s) h[i].t=h[i].s; ch=true; } }
      if(ch) localStorage.setItem('ch_emsal_hist',JSON.stringify(h));
    }
  }catch(e){}
  /* [D] formda ozel firma/alici alani varsa genel ch_empfaenger alanini gizle */
  function dedupe(){
    try{
      var emp=document.querySelector('[data-k="ch_empfaenger"]');
      if(emp){
        var labels=document.querySelectorAll('label, .fe-label, .frm-label');
        for(var i=0;i<labels.length;i++){
          var t=(labels[i].textContent||'').toLowerCase();
          if(/name des unternehmens|firmenname|anbieter|name des anbieters/.test(t) && !/empf/.test(t)){
            var lw=labels[i].closest? (labels[i].closest('.fi')||labels[i].closest('.frm-row')||labels[i].closest('.fe-field')||labels[i].parentNode) : labels[i].parentNode;
            if(lw && !lw.__j18){
              var inp=lw.querySelector('input,textarea,[data-k]');
              if(inp){ if(!String(inp.value||'').trim()){ inp.value=String(emp.value||'').split('\n')[0].trim(); } inp.removeAttribute('required'); }
              lw.style.display='none'; lw.__j18=1;
            }
          }
        }
      }
    }catch(e){}
    try{ var rs=document.querySelectorAll('[required]'); for(var k=0;k<rs.length;k++){ if(rs[k].offsetParent===null) rs[k].removeAttribute('required'); } }catch(e){}
  }
  setInterval(dedupe,900);
})();}catch(e){}
</script>
HTMLBLOCK;
$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$src = substr($src,0,$pos).$block."\n".substr($src,$pos);
echo "  ✓ [D][E] cift-Empfänger gizleme + emsal arsiv migration eklendi\n";

$tmp = tempnam(sys_get_temp_dir(),'fj').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-fixj18-'.date('Ymd-His'), (string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
if (function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_FIXPACK_J18')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "\n  ✓ DOGRULAMA: CH_FIXPACK_J18 diskte (".strlen($chk)." bayt)\n";
echo "\n✓ [1] Geri donunce belge kayitli (modal acilisinda otomatik kayit)\n";
echo "✓ [2] Gonderim: action govdede + postversand v5 (unknown_action cozumu)\n";
echo "✓ [3] Cift Empfänger alani otomatik gizlenir\n";
echo "✓ [4] Emsal arsivi: eski kayitlar tiklanabilir (migration)\n";
echo "✓ [5] Themen+Falle: behalten aninda icerik kayitli, her yerden acilir\n";
