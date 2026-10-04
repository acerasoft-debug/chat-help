<?php
/**
 * ChatHelp — apply-uxfix5 — Lansman sabahi 5 UX istegi (6 edit):
 *  [1] CH_PDFDIRECT : 'Als PDF speichern' artik gonderim modalini ACMAZ —
 *      dogrudan profesyonel PDF uretir (Senden akisi degismez).
 *  [2] CH_SENDNOPRICE: 'Unterschreiben & an Empfänger senden' butonunda fiyat
 *      GORUNMEZ (fiyat, yontem seciminde gorunmeye devam eder).
 *  [3] CH_IBANFIX   : #140 IBAN bug'inin GERCEK nedeni — fillRecip() tarih/Betreff
 *      bulamayinca ALICI ADRESINI metindeki ILK ____ bosluguna yaziyordu
 *      ("IBAN: Ornek str. 3, 89567 Sontheim"). Bu fallback tamamen kaldirildi:
 *      bosluklara ASLA adres yazilmaz, kullanici doldurmazsa BOS (____) kalir.
 *  [4] CH_FILLSKIP  : 'Trotzdem drucken/fortfahren' atlama butonu artik HER
 *      akista var (gonderimde de) — bosluklar istenirse bos birakilabilir.
 *  [5] CH_GAPBOX    : etiketSIZ ____ bosluklari da artik KUTU ile sorulur —
 *      her boslugun ONCESINDEKI metin etiket olarak gosterilir; bos birakilirsa
 *      belgede ____ olarak kalir.
 *  [6] CH_GAPAPPLY  : applyFill() '#i' kutularini konum-bazli, sondan-basa
 *      guvenli sekilde uygular (indeks kaymasi olmaz).
 *  Her bolum BAGIMSIZ sayac-korumali; eslesmeyen atlanir ve raporlanir.
 * KULLANIM: pull2.php?key=...&files=apply-uxfix5.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-uxfix5 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");

$ok=0;

/* ── [1] CH_PDFDIRECT ── */
if(strpos($src,'CH_PDFDIRECT')===false){
  $o1="if(_tk3&&_up3!=='free'){makePDF(doc,d.n,CC);return;}";
  $n1="if(_tk3&&_up3!=='free'){window.__pdfConfirmed=true;window.__addr3OK=true;window.__addrOK=true;setTimeout(function(){try{window.__addr3OK=false;window.__addrOK=false;}catch(e){}},2500);makePDF(doc,d.n,CC);return;}/*CH_PDFDIRECT*/";
  $c=substr_count($src,$o1);
  if($c===1){ $src=str_replace($o1,$n1,$src); echo "  ✓ [1] CH_PDFDIRECT — PDF butonu artik modal acmadan dogrudan PDF\n"; $ok++; }
  else echo "  ✗ [1] CH_PDFDIRECT anchor ($c, 1 beklenir) — atlandi\n";
} else { echo "  = [1] zaten ekli\n"; $ok++; }

/* ── [2] CH_SENDNOPRICE ── */
if(strpos($src,'CH_SENDNOPRICE')===false){
  $o2="'<button id=\"ai-send\">'+T('send')+' — '+priceStr()+'</button>'";
  $n2="'<button id=\"ai-send\">'+T('send')+'</button>'/*CH_SENDNOPRICE*/";
  $c=substr_count($src,$o2);
  if($c>=1 && $c<=4){ $src=str_replace($o2,$n2,$src); echo "  ✓ [2] CH_SENDNOPRICE — gonderim butonundan fiyat kaldirildi ($c kopya)\n"; $ok++; }
  else echo "  ✗ [2] CH_SENDNOPRICE anchor ($c, 1-4 beklenir) — atlandi\n";
} else { echo "  = [2] zaten ekli\n"; $ok++; }

/* ── [3] CH_IBANFIX ── */
if(strpos($src,'CH_IBANFIX')===false){
  $o3=<<<'ANC'
 if(/_{4,}/.test(text)){ var adr=rec.split('\n').slice(1).join(', ')||rec; return text.replace(/_{4,}/, adr); } return text;
ANC;
  $n3=" return text;/*CH_IBANFIX bosluklara asla adres yazilmaz*/";
  $c=substr_count($src,$o3);
  if($c>=1 && $c<=3){ $src=str_replace($o3,$n3,$src); echo "  ✓ [3] CH_IBANFIX — ____ bosluklarina adres yazma fallback'i kaldirildi ($c)\n"; $ok++; }
  else echo "  ✗ [3] CH_IBANFIX anchor ($c, 1-3 beklenir) — atlandi\n";
} else { echo "  = [3] zaten ekli\n"; $ok++; }

/* ── [4] CH_FILLSKIP ── */
if(strpos($src,'CH_FILLSKIP')===false){
  $o4="+(!hard?'<button id=\"ai-fill-skip\" class=\"jb-mbtn\">'+bt2+'</button>':'')+";
  $n4="+('<button id=\"ai-fill-skip\" class=\"jb-mbtn\">'+bt2+'</button>')+/*CH_FILLSKIP*/";
  $c=substr_count($src,$o4);
  if($c===1){ $src=str_replace($o4,$n4,$src); echo "  ✓ [4] CH_FILLSKIP — atlama butonu her akista gorunur\n"; $ok++; }
  else echo "  ✗ [4] CH_FILLSKIP anchor ($c, 1 beklenir) — atlandi\n";
} else { echo "  = [4] zaten ekli\n"; $ok++; }

/* ── [5] CH_GAPBOX ── */
if(strpos($src,'CH_GAPBOX')===false){
  $o5=<<<'ANC'
      if(f==='…'){ h+='<div class="aik-hint" style="color:#ffcf7a;font-size:11px;margin:2px 0">'+gap+'</div>'; return; }
ANC;
  $n5=<<<'ANC'
      if(f==='…'){/*CH_GAPBOX*/ try{ var _t=bodyVal(); var _LAB=/(IBAN|BIC|Kundennummer|Kundennr|Vertragsnummer|Vertragsnr|Aktenzeichen|Rechnungsnummer|Kontonummer|Mitgliedsnummer|Betrag|Datum|Az|Tarih|Numara)\s*[:\-]?\s*$/; var _re=/_{4,}/g,_mm,_gi=0; while((_mm=_re.exec(_t))&&_gi<6){ var _cx=_t.slice(Math.max(0,_mm.index-42),_mm.index).split('\n').pop(); if(_LAB.test(_cx)) continue; var _lb=String(_cx||'').trim(); if(_lb.length>38)_lb='…'+_lb.slice(-36); if(!_lb)_lb='Feld '+(_gi+1); h+='<div style="margin:4px 0"><label style="font-size:11px;color:#ffd9a0">'+esc(_lb)+' ____</label><input class="ai-in" data-mf="#'+_gi+'" style="margin-top:2px"></div>'; _gi++; } }catch(e){} return; }
ANC;
  $c=substr_count($src,$o5);
  if($c===1){ $src=str_replace($o5,$n5,$src); echo "  ✓ [5] CH_GAPBOX — etiketsiz ____ bosluklari da kutuyla soruluyor\n"; $ok++; }
  else echo "  ✗ [5] CH_GAPBOX anchor ($c, 1 beklenir) — atlandi\n";
} else { echo "  = [5] zaten ekli\n"; $ok++; }

/* ── [6] CH_GAPAPPLY ── */
if(strpos($src,'CH_GAPAPPLY')===false){
  $o6=<<<'ANC'
  function applyFill(){
    var b=document.getElementById('ai-body'); if(!b) return;
    var t=String(b.value||'');
    try{ Array.prototype.forEach.call(document.querySelectorAll('#ai-fillp [data-mf]'),function(inp){
      var k=inp.getAttribute('data-mf'); var v=String(inp.value||'').trim(); if(!v||k==='…') return;
      if(k.charAt(0)==='['){ while(t.indexOf(k)!==-1) t=t.replace(k,v); return; }
      var re=new RegExp('('+k.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+'\\s*[:\\-]?\\s*)(\\.{3,}|_{3,}|x{3,}|X{3,}|-{3,}|—{2,})');
      t=t.replace(re,'$1'+v);
    }); }catch(e){}
    b.value=t;
  }
ANC;
  $n6=<<<'ANC'
  function applyFill(){/*CH_GAPAPPLY*/
    var b=document.getElementById('ai-body'); if(!b) return;
    var t=String(b.value||'');
    try{ Array.prototype.forEach.call(document.querySelectorAll('#ai-fillp [data-mf]'),function(inp){
      var k=inp.getAttribute('data-mf'); var v=String(inp.value||'').trim(); if(!v||k==='…'||k.charAt(0)==='#') return;
      if(k.charAt(0)==='['){ while(t.indexOf(k)!==-1) t=t.replace(k,v); return; }
      var re=new RegExp('('+k.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+'\\s*[:\\-]?\\s*)(\\.{3,}|_{3,}|x{3,}|X{3,}|-{3,}|—{2,})');
      t=t.replace(re,'$1'+v);
    }); }catch(e){}
    try{
      var LAB=/(IBAN|BIC|Kundennummer|Kundennr|Vertragsnummer|Vertragsnr|Aktenzeichen|Rechnungsnummer|Kontonummer|Mitgliedsnummer|Betrag|Datum|Az|Tarih|Numara)\s*[:\-]?\s*$/;
      var gaps=[]; var re2=/_{4,}/g, mm;
      while((mm=re2.exec(t))){ var cx=t.slice(Math.max(0,mm.index-42),mm.index).split('\n').pop(); if(LAB.test(cx)) continue; gaps.push({i:mm.index,l:mm[0].length}); }
      var fills=[];
      Array.prototype.forEach.call(document.querySelectorAll('#ai-fillp [data-mf]'),function(inp){
        var k=inp.getAttribute('data-mf'); if(k.charAt(0)!=='#') return;
        var v=String(inp.value||'').trim(); if(!v) return;
        var gi=parseInt(k.slice(1),10); if(gaps[gi]) fills.push({g:gaps[gi],v:v});
      });
      fills.sort(function(a,b){ return b.g.i-a.g.i; });
      fills.forEach(function(f){ t=t.slice(0,f.g.i)+f.v+t.slice(f.g.i+f.g.l); });
    }catch(e){}
    b.value=t;
  }
ANC;
  $c=substr_count($src,$o6);
  if($c===1){ $src=str_replace($o6,$n6,$src); echo "  ✓ [6] CH_GAPAPPLY — kutu degerleri konum-bazli guvenli uygulanir\n"; $ok++; }
  else echo "  ✗ [6] CH_GAPAPPLY anchor ($c, 1 beklenir) — atlandi\n";
} else { echo "  = [6] zaten ekli\n"; $ok++; }

if($ok===0){ echo "\nHICBIR bolum uygulanamadi — HICBIR sey degistirilmedi.\n"; exit; }

$tmp=tempnam(sys_get_temp_dir(),'ux5').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-uxfix5-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
echo "\n  Sonuc: $ok/6 bolum tamam (".strlen($chk)." B)\n";
echo "  Test-1: Belge -> 'Als PDF speichern' -> modal ACILMADAN dogrudan PDF inmeli.\n";
echo "  Test-2: Senden -> 'Unterschreiben & an Empfänger senden' butonunda fiyat YOK.\n";
echo "  Test-3: IBAN/BIC bosluklu belge -> Senden -> IBAN'a ADRES YAZILMAMALI;\n";
echo "          bosluk kutulari cikar, bos birakilirsa ____ olarak kalir.\n";
echo "  Test-4: Kostenlos selbst ausdrucken -> imzasiz da calisir, kutular atlanabilir.\n";
