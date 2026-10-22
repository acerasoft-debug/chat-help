<?php
/**
 * ChatHelp — apply-faxerr — gonderim hatalari ARTIK SESSIZ KALMAZ + Sinch takip id'si.
 *
 * KANIT (canli): Sinch'te 0 numara, 0 faks kaydi. Her gonderim 422 ile
 *  reddedilmis ama arayuz yalniz `if(j && j.ok){...}` bakiyor — hata dali YOK.
 *  Bu yuzden kullanici "hata vermedi" saniyor. Ayni sey Brief icin de gecerli
 *  (LetterXpress bakiyesi -1.04 EUR -> is reddedilir, kullanici gormez).
 *
 * [A] index.php  (CH_FAXERR)  — TAMAMEN EKLEME:
 *     fetch sarmalayici; postversand.php'ye giden send_fax / send_letter /
 *     send_text / send yanitlarini izler. ok=false veya error varsa ekranda
 *     GORUNUR kirmizi bildirim cikar (DE/TR/EN), sebebi insan diliyle yazar:
 *       no_from_number    -> faks hesabinda gonderen numara yok
 *       bad_fax_number    -> numara gecersiz (kisa/uzun/gercekci degil)
 *       fax_not_configured, no_text, not_configured, network …
 *     LetterXpress'in kredi/limit mesajlari da (result.message) gosterilir.
 *     Mevcut kodun kendi .then zinciri BOZULMAZ (yalniz r.clone() okunur).
 *
 * [B] postversand.php (CH_FAXMID) — Sinch yanitini ClickSend bicimine sarar
 *     (result.data.messages[0].message_id), boylece arayuzun MEVCUT takip-id
 *     cikarici Sinch'te de calisir -> Meine Sendungen'deki durum/Bericht
 *     butonu gercek sonucu gosterir (fax_status Sinch destegi CH_FAXFROM'da).
 *
 * Iki dosya bagimsiz: biri tutmazsa digeri yine uygulanir. Lint + .bak + kilit.
 * KULLANIM: /chat/pull2.php?key=...&files=apply-faxerr.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
echo "apply-faxerr BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$dir=__DIR__; $okA=false; $okB=false;

/* ══════════ [B] postversand.php — Sinch takip id'si ══════════ */
echo "=== [B] postversand.php (CH_FAXMID) ===\n";
$pf=$dir.'/postversand.php'; $psrc=@file_get_contents($pf);
if($psrc===false||$psrc===''){ echo "  ✗ okunamadi\n"; }
elseif(strpos($psrc,'CH_FAXMID')!==false){ echo "  = zaten ekli\n"; $okB=true; }
elseif(strpos($psrc,'CH_FAXFROM')===false){ echo "  ✗ once apply-faxfrom.php gerekli — atlandi\n"; }
else{
  $A="  out(['ok'=>!empty(\$r['ok']),'http'=>\$r['http']??0,'to'=>\$faxnr,'from'=>\$chFrom,'mid'=>ch_fax_mid(\$r),'pdf_bytes'=>strlen(\$pdfBin),'provider'=>\$r['provider']??fax_provider(),'result'=>\$r['result']??null]); /*CH_FAXFROM*/";
  $B="  /*CH_FAXMID*/ if(fax_provider()==='sinch'){ \$__mid=ch_fax_mid(\$r);\n"
    ."    if(\$__mid!==''){ \$r['result']=['id'=>\$__mid,'data'=>['messages'=>[['message_id'=>\$__mid]]],'sinch'=>\$r['result']??null]; } }\n".$A;
  $c=substr_count($psrc,$A);
  if($c!==1){ echo "  ✗ anchor eslesmesi $c — atlandi (degisiklik yok)\n"; }
  else{
    $pnew=str_replace($A,$B,$psrc);
    $t=tempnam(sys_get_temp_dir(),'fm').'.php'; file_put_contents($t,$pnew);
    $lo=[];$rc=0; exec('php -l '.escapeshellarg($t).' 2>&1',$lo,$rc); @unlink($t);
    if($rc!==0){ echo "  ✗ LINT: ".implode(' ',$lo)." — yazilmadi\n"; }
    else{
      @file_put_contents($pf.'.bak-faxmid-'.date('Ymd-His'),$psrc);
      if(@file_put_contents($pf,$pnew)!==false){ echo "  ✓ eklendi (".strlen($psrc)." -> ".strlen($pnew).")\n"; $okB=true; }
      else echo "  ✗ yazma hatasi\n";
    }
  }
}

/* ══════════ [A] index.php — gorunur hata ══════════ */
echo "\n=== [A] index.php (CH_FAXERR) ===\n";
$xf=$dir.'/index.php'; $src=@file_get_contents($xf);
if($src===false||$src===''){ echo "  ✗ okunamadi\n"; }
elseif(strpos($src,'CH_FAXERR')!==false){ echo "  = zaten ekli\n"; $okA=true; }
else{
  echo "  index.php: ".strlen($src)." B\n";

  $js = <<<'HTML'
<script>/*CH_FAXERR*/(function(){try{
function L(){try{return (localStorage.getItem('ch_uilang')||'de').slice(0,2);}catch(e){return 'de';}}
var M={
 de:{ttl:'Versand fehlgeschlagen',
     no_from_number:'Für das Fax-Konto ist keine Absender-Faxnummer hinterlegt. Der Versand wurde nicht ausgeführt — es wurden keine Kosten verursacht.',
     bad_fax_number:'Die Faxnummer sieht nicht gültig aus. Bitte prüfen Sie die Nummer.',
     fax_not_configured:'Fax-Versand ist derzeit nicht eingerichtet.',
     no_fax_number:'Bitte geben Sie eine Faxnummer ein.',
     no_text:'Das Dokument ist leer.',
     not_configured:'Briefversand ist derzeit nicht eingerichtet.',
     bad_request:'Ungültige Anfrage.',
     network:'Netzwerkfehler beim Anbieter.',
     zu_kurz:'zu kurz',zu_lang:'zu lang',unrealistisch:'unrealistisch',
     generic:'Der Versand konnte nicht abgeschlossen werden.'},
 tr:{ttl:'Gönderim başarısız',
     no_from_number:'Faks hesabında gönderen faks numarası tanımlı değil. Gönderim yapılmadı — ücret oluşmadı.',
     bad_fax_number:'Faks numarası geçerli görünmüyor. Lütfen kontrol edin.',
     fax_not_configured:'Faks gönderimi şu an kurulu değil.',
     no_fax_number:'Lütfen bir faks numarası girin.',
     no_text:'Belge boş.',
     not_configured:'Mektup gönderimi şu an kurulu değil.',
     bad_request:'Geçersiz istek.',
     network:'Sağlayıcıya bağlanırken ağ hatası.',
     zu_kurz:'çok kısa',zu_lang:'çok uzun',unrealistisch:'gerçekçi değil',
     generic:'Gönderim tamamlanamadı.'},
 en:{ttl:'Sending failed',
     no_from_number:'No sender fax number is configured for the fax account. Nothing was sent — no charges occurred.',
     bad_fax_number:'The fax number does not look valid. Please check it.',
     fax_not_configured:'Fax sending is not set up.',
     no_fax_number:'Please enter a fax number.',
     no_text:'The document is empty.',
     not_configured:'Letter sending is not set up.',
     bad_request:'Invalid request.',
     network:'Network error at the provider.',
     zu_kurz:'too short',zu_lang:'too long',unrealistisch:'unrealistic',
     generic:'The send could not be completed.'}};
function T(k){var t=M[L()]||M.de;return t[k]||M.de[k]||k;}

function box(msg){
  try{
    var id='ch-senderr', old=document.getElementById(id);
    if(old&&old.parentNode) old.parentNode.removeChild(old);
    var d=document.createElement('div'); d.id=id;
    d.setAttribute('style','position:fixed;left:12px;right:12px;bottom:18px;z-index:2147483000;'
      +'max-width:520px;margin:0 auto;background:#2b1414;border:1px solid #a33;border-radius:12px;'
      +'padding:14px 16px;color:#ffe9e9;font:14px/1.55 system-ui,-apple-system,sans-serif;'
      +'box-shadow:0 10px 34px rgba(0,0,0,.5)');
    var h=document.createElement('div');
    h.setAttribute('style','font-weight:800;margin-bottom:5px;color:#ff9a9a');
    h.textContent='✗ '+T('ttl');
    var p=document.createElement('div'); p.textContent=msg;
    var x=document.createElement('button');
    x.textContent='✕';
    x.setAttribute('style','position:absolute;top:8px;right:10px;background:none;border:0;'
      +'color:#ffb3b3;font-size:17px;cursor:pointer;line-height:1');
    x.onclick=function(){ try{ d.parentNode.removeChild(d); }catch(e){} };
    d.appendChild(h); d.appendChild(p); d.appendChild(x);
    document.body.appendChild(d);
    setTimeout(function(){ try{ if(d.parentNode) d.parentNode.removeChild(d); }catch(e){} },20000);
  }catch(e){}
}

function explain(j){
  var e=(j&&j.error)?String(j.error):'';
  var m='';
  if(e && (M[L()]||M.de)[e]) m=T(e);
  else if(e) m=T('generic')+' ('+e+')';
  else m=T('generic');
  if(e==='bad_fax_number' && j && j.reason) m+=' — '+T(j.reason);
  if(j && j.hint) m+=' · '+j.hint;
  try{
    var r=j&&j.result;
    if(r && typeof r==='object'){
      var extra=r.message||r.msg||r.error||(r.response_msg||'');
      if(extra && String(extra).toLowerCase()!=='ok') m+=' · '+extra;
    }
  }catch(_){}
  if(j && j.http && Number(j.http)>=400) m+=' [HTTP '+j.http+']';
  return m;
}

var _of=window.fetch;
if(typeof _of==='function'){
  window.fetch=function(input,init){
    var url='';
    try{ url=(typeof input==='string')?input:((input&&input.url)||''); }catch(e){}
    var pr=_of.apply(this,arguments);
    try{
      if(/postversand\.php/.test(url) && /action=send(_fax|_letter|_text)?\b/.test(url) && pr && pr.then){
        pr.then(function(r){
          try{
            r.clone().json().then(function(j){
              try{ if(j && (j.ok===false || j.error)) box(explain(j)); }catch(_){}
            }).catch(function(){});
          }catch(_){}
          return r;
        }).catch(function(){});
      }
    }catch(e){}
    return pr;
  };
}
}catch(e){}})();</script>
HTML;

  $p=strripos($src,'</body>');
  if($p===false){ echo "  ✗ </body> yok — atlandi\n"; }
  else{
    $new=substr($src,0,$p).$js.substr($src,$p);
    $bad='';
    foreach(['CH_ISWV_GLOBAL'=>1,'CH_APPVIEW3'=>1,'CH_APPVIEW2C'=>4,'CH_MAILBTN2'=>1,
             'CH_FILLGAPS'=>1,'CH_NATIVEPDF'=>1,'CH_PDFGOLD'=>1,'CH_FAXERR'=>1] as $m=>$min){
      if(substr_count($new,$m)<$min){ $bad=$m; break; }
    }
    if($bad!==''){ echo "  ✗ kritik isaret eksik olurdu ($bad) — atlandi\n"; }
    else{
      $t=tempnam(sys_get_temp_dir(),'fe').'.php'; file_put_contents($t,$new);
      $lo=[];$rc=0; exec('php -l '.escapeshellarg($t).' 2>&1',$lo,$rc); @unlink($t);
      if($rc!==0){ echo "  ✗ LINT: ".implode(' ',$lo)." — yazilmadi\n"; }
      else{
        @file_put_contents($xf.'.bak-faxerr-'.date('Ymd-His'),$src);
        if(@file_put_contents($xf,$new)!==false){
          echo "  ✓ eklendi (".strlen($src)." -> ".strlen($new).")\n"; $okA=true;
        } else echo "  ✗ yazma hatasi\n";
      }
    }
  }
}

if(function_exists('opcache_reset')) @opcache_reset();

/* ── kilit tazele ── */
if($okA){
  $cur=(string)@file_get_contents($xf);
  @file_put_contents($xf.'.GOOD-appprint',$cur);
  @file_put_contents($dir.'/ch-applock.json',json_encode([
   'locked_at'=>date('c'),'size'=>strlen($cur),'sha1'=>sha1($cur),'note'=>'faxerr',
   'markers'=>['CH_ISWV_GLOBAL'=>substr_count($cur,'CH_ISWV_GLOBAL'),'CH_APPVIEW3'=>substr_count($cur,'CH_APPVIEW3'),
               'CH_APPVIEW2C'=>substr_count($cur,'CH_APPVIEW2C'),'CH_MAILBTN2'=>substr_count($cur,'CH_MAILBTN2'),
               'CH_FILLGAPS'=>substr_count($cur,'CH_FILLGAPS'),'CH_NATIVEPDF'=>substr_count($cur,'CH_NATIVEPDF'),
               'CH_PDFGOLD'=>substr_count($cur,'CH_PDFGOLD'),'CH_FAXERR'=>substr_count($cur,'CH_FAXERR')],
   'files'=>['pv.php','pvsave.php','pvmail.php','pdflib.php','postversand.php']
  ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
  echo "\n  ✓ kilit tazelendi (sha1 ".substr(sha1($cur),0,12)."…)\n";
}

echo "\n=== SONUC: ".($okA?'A ✓':'A ✗')."  ".($okB?'B ✓':'B ✗')."\n";
echo "\nBUNDAN SONRA:\n";
echo "  · Faks/Brief gonderimi basarisiz olursa ekranda KIRMIZI kutu cikar ve\n";
echo "    sebebini yazar. Hicbir gonderim bir daha sessizce kaybolmaz.\n";
echo "  · Sinch'te numara olmadigi icin su an cikacak mesaj:\n";
echo "    \"Für das Fax-Konto ist keine Absender-Faxnummer hinterlegt …\"\n";
echo "  · Brief'te bakiye eksi oldugu icin LetterXpress'in kendi mesaji gorunur.\n";
echo "\nSENIN YAPMAN GEREKEN (kodla cozulemez):\n";
echo "  1) Sinch panelinde FAKS yetenekli bir numara kirala -> faks aninda calisir\n";
echo "     (kod numarayi otomatik bulur, ayar gerekmez).\n";
echo "  2) LetterXpress hesabina kredi yukle (su an -1.04 EUR) -> Brief calisir.\n";
