<?php
/**
 * ChatHelp — apply-fieldlabel-replace (CH_FIELDLABEL_REPLACE)
 *  SORUN: sablon alan basliklari (.fi label) icin eski i18n katmani (ch-
 *  i18n-layer) cevirilen metni orijinal ALMANCA'nin ALTINA span olarak
 *  EKLIYORDU — Almanca hicbir zaman kaybolmuyordu (ekran goruntusunde:
 *  "AKTENZEICHEN..." + altinda "DOSYA NUMARASI..."). Kullanici: hukuk dili
 *  ne olursa olsun (baska dil seçilmedikce) SADECE o dilin gorunmesini istiyor.
 *
 *  COZUM: decorateLabels() etiketin GORUNEN METNINI DEGISTIRIR (append
 *  degil). Guvenlik detaylari:
 *   • deText() zaten Almanca orijinali data-de ozniteliginde KALICI olarak
 *     saklıyor -> DE'ye don uldugunde data-de'den GERI YUKLENIR (kayip yok).
 *   • Zorunlu alan yildizi (*) deText() tarafindan siliniyordu (ceviri
 *     API'sine yildizsiz gonderilsin diye) — bu YENI surumde data-req
 *     bayragiyla ayrica saklanip ceviri metnine GERI EKLENIR (yildiz
 *     kaybolmaz).
 *   • Etiket icindeki BASKA child elementler (orn. "(optional)" span'i)
 *     SADECE text node'lar hedeflendigi icin DOKUNULMAZ, korunur.
 *   • clearTr()/tick() akisi (dil degisince data-i18n-done sifirlanip
 *     yeniden isleniyor) AYNEN KORUNUR — sadece DE dalinda artik "hicbir
 *     sey yapma" yerine "Almanca'ya geri yukle" davranisi eklenir.
 * KULLANIM: pull-updates.php?files=apply-fieldlabel-replace.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fieldlabel-replace BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_FIELDLABEL_REPLACE')!==false) exit("Zaten ekli (CH_FIELDLABEL_REPLACE).\n");

$old = <<<'OLD'
  function deText(l){
    var d=l.getAttribute('data-de'); if(d!==null) return d;
    var t=''; for(var i=0;i<l.childNodes.length;i++){ if(l.childNodes[i].nodeType===3) t+=l.childNodes[i].nodeValue; }
    t=t.replace(/\*/g,'').trim(); l.setAttribute('data-de', t); return t;
  }

  /* ── soru etiketlerini çift dile çevir ── */
  function decorateLabels(){
    var lang=getLang(); if(lang==='de') return;
    var labels=[].slice.call(document.querySelectorAll('.fi label:not([data-i18n-done])'));
    if(!labels.length) return;
    var texts=[], els=[];
    labels.forEach(function(l){ var t=deText(l); l.setAttribute('data-i18n-done',lang); if(t){ texts.push(t); els.push(l); } });
    if(!texts.length) return;
    trBatch(texts, lang, function(mm){
      els.forEach(function(l){ var t=l.getAttribute('data-de'); var tr=mm[t];
        if(tr && tr!==t && !l.querySelector('.ch-i18n-tr')){
          var s=document.createElement('span'); s.className='ch-i18n-tr'+(lang==='ar'?' ch-i18n-rtl':''); s.textContent=tr; l.appendChild(s);
        }
      });
    });
  }
OLD;

$new = <<<'NEW'
  function deText(l){
    var d=l.getAttribute('data-de'); if(d!==null) return d;
    var t=''; for(var i=0;i<l.childNodes.length;i++){ if(l.childNodes[i].nodeType===3) t+=l.childNodes[i].nodeValue; }
    if(/\*/.test(t)) l.setAttribute('data-req','1'); /*CH_FIELDLABEL_REPLACE*/
    t=t.replace(/\*/g,'').trim(); l.setAttribute('data-de', t); return t;
  }
  function setLabelText(l,txt){ /*CH_FIELDLABEL_REPLACE — Almanca'yi DEGISTIRIR, altina eklemez*/
    var full=(l.getAttribute('data-req')==='1')?(txt+' *'):txt;
    var firstText=null;
    for(var i=0;i<l.childNodes.length;i++){
      if(l.childNodes[i].nodeType===3){
        if(!firstText){ firstText=l.childNodes[i]; firstText.nodeValue=full; }
        else { l.removeChild(l.childNodes[i]); i--; }
      }
    }
    if(!firstText){ l.insertBefore(document.createTextNode(full), l.firstChild); }
  }

  /* ── soru etiketlerini AKTIF DILE cevir (Almanca ARTIK KALMAZ) ── */
  function decorateLabels(){
    var lang=getLang();
    var labels=[].slice.call(document.querySelectorAll('.fi label:not([data-i18n-done])'));
    if(!labels.length) return;
    if(lang==='de'){
      labels.forEach(function(l){ var t=deText(l); setLabelText(l,t); l.setAttribute('data-i18n-done','de'); });
      return;
    }
    var texts=[], els=[];
    labels.forEach(function(l){ var t=deText(l); l.setAttribute('data-i18n-done',lang); if(t){ texts.push(t); els.push(l); } });
    if(!texts.length) return;
    trBatch(texts, lang, function(mm){
      els.forEach(function(l){ var t=l.getAttribute('data-de'); var tr=mm[t];
        if(tr && tr!==t){ setLabelText(l,tr); }
      });
    });
  }
NEW;

$c=0; $src=str_replace($old,$new,$src,$c);
if ($c!==1) { echo "HATA: decorateLabels anchor bulunamadi (x$c) — index.php DEGISTIRILMEDI.\n"; exit; }
echo "  ✓ sablon alan basliklari artik cevirinin YERINE geciyor (Almanca alta eklenmiyor)\n";
echo "  ✓ zorunlu alan yildizi (*) ve ek child elementler (orn. '(optional)') korunuyor\n";
echo "  ✓ DE'ye donuldugunde orijinal Almanca guvenle geri yukleniyor\n";

$tmp = tempnam(sys_get_temp_dir(),'fl').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-fieldlabel-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_FIELDLABEL_REPLACE uygulandi. Hukuk dili ne olursa olsun (baska dil\n";
echo "   secilmedikce) SADECE o ulkenin dili gorunuyor — Almanca sizmasi bitti.\n";
