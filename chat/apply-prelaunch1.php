<?php
/**
 * ChatHelp — apply-prelaunch1 — LANSMAN ONCESI 4'lu duzeltme paketi:
 *  [1] CH_RECFIX: 'Faxnummer per KI finden' + fax onizleme "once Empfänger gir"
 *      diyordu — Empfänger baska yerde YAZILI oldugu halde. recName() SADECE
 *      #ai-recipient'a bakiyordu; simdi 4 kaynak dener: #ai-recipient ->
 *      recVal() -> ai-box icindeki empfänger alanlari -> son belge cevaplari
 *      (_lastGenAns). Iki kopya da (CH_FAX_UX + CH_FAX_AIFIND) duzeltilir.
 *  [2] CH_TELFIX: chat-belge doldurmada '[TELEFON]':P.f5 yaziyordu — f5 DOGUM
 *      TARIHI, telefon f6! Belgelere telefon yerine dogum tarihi basiliyordu.
 *  [3] CH_MOBDL: mobil TARAYICIDA (App disinda) 'PDF' artik yeni sekme acmiyor —
 *      ayni sayfada kalarak dl.php'den dogrudan indirme (kullanici istegi:
 *      "bir diger sayfaya gitmeden"). Masaustu: yazdirma diyalogu (degismez).
 *  [4] CH_SENDCSS: gonderim (Senden/Fax) modali premium cila — altin vurgulu
 *      secim kartlari, secili durumda altin cerceve, premium buton/giris stilleri.
 *      SADECE CSS (davranis degismez, hicbir sey oynamaz/zıplamaz).
 *  Her bolum BAGIMSIZ; eslesmeyen atlanir ve raporlanir.
 * KULLANIM: pull2.php?key=...&files=apply-prelaunch1.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-prelaunch1 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");

$ok=0;

/* ── [1] CH_RECFIX ── */
if(strpos($src,'CH_RECFIX')===false){
  $oldRN='function recName(){ try{ var r=document.getElementById(\'ai-recipient\'); if(r&&r.value) return (r.value.split(\'\n\')[0]||\'\').trim(); }catch(e){} return \'\'; }';
  $newRN='function recName(){/*CH_RECFIX*/ var _v=\'\'; try{ var r=document.getElementById(\'ai-recipient\'); if(r&&r.value&&r.value.trim()) _v=r.value; }catch(e){} try{ if(!_v&&typeof recVal===\'function\'){ var q=recVal(); if(q&&String(q).trim()) _v=String(q); } }catch(e){} try{ if(!_v){ var bx=document.getElementById(\'ai-box\'); if(bx){ var els=bx.querySelectorAll(\'textarea,input[type=text],input:not([type])\'); for(var i=0;i<els.length;i++){ var idp=(els[i].id||\'\')+\' \'+(els[i].placeholder||\'\'); if(/rec|empf|adressat|alici/i.test(idp)&&els[i].value&&els[i].value.trim()){ _v=els[i].value; break; } } } } }catch(e){} try{ if(!_v&&window._lastGenAns&&window._lastGenAns.ans){ var a=window._lastGenAns.ans,ks=Object.keys(a); for(var j=0;j<ks.length;j++){ if(/empf|recip|adressat|behoerd|alici|an_/i.test(ks[j])&&a[ks[j]]&&String(a[ks[j]]).trim()){ _v=String(a[ks[j]]); break; } } } }catch(e){} return _v?(_v.split(\'\n\')[0]||\'\').trim():\'\'; }';
  /* cok-satirli varyant (CH_FAX_UX kopyasi) */
  $oldRN2="function recName(){\n    try{ var r=document.getElementById('ai-recipient'); if(r&&r.value) return (r.value.split('\n')[0]||'').trim(); }catch(e){}\n    return '';\n  }";
  $c=substr_count($src,$oldRN);
  $c2=substr_count($src,$oldRN2);
  $tot=0;
  if($c>=1 && $c<=3){ $src=str_replace($oldRN,$newRN,$src); $tot+=$c; }
  if($c2>=1 && $c2<=3){ $src=str_replace($oldRN2,$newRN,$src); $tot+=$c2; }
  if($tot>=1){ echo "  ✓ [1] CH_RECFIX — recName() $tot kopyada 4-kaynakli yapildi (Empfänger bug'i)\n"; $ok++; }
  else echo "  ✗ [1] CH_RECFIX anchor (tek-satir:$c cok-satir:$c2) — atlandi\n";
} else { echo "  = [1] CH_RECFIX zaten ekli\n"; $ok++; }

/* ── [2] CH_TELFIX ── */
$t=0;
$pairs=[
  ["'[TELEFON]':P.f5||''","'[TELEFON]':P.f6||''"],
  ['"[TELEFON]":P2.f5','"[TELEFON]":P2.f6'],
  ["'[TELEFON]':P2.f5","'[TELEFON]':P2.f6"],
];
foreach($pairs as $pr){
  $c=substr_count($src,$pr[0]);
  if($c>=1){ $src=str_replace($pr[0],$pr[1],$src); $t+=$c; }
}
if($t>=1){ echo "  ✓ [2] CH_TELFIX — $t yerde [TELEFON] artik f6 (telefon), f5 (dogum tarihi) DEGIL\n"; $ok++; }
else echo "  = [2] CH_TELFIX — f5-telefon eslesmesi bulunamadi (zaten dogru olabilir)\n";

/* ── [3] CH_MOBDL ── */
if(strpos($src,'CH_MOBDL')===false){
  $oldM="      if(isMobile()){\n"
       ."        var w=null; try{ w=window.open('','_blank'); }catch(e){ w=null; }\n"
       ."        if(w&&w.document){ try{ w.document.open(); w.document.write(toTab(H)); w.document.close(); return; }catch(e){} }\n"
       ."        overlay(String(html)); return;\n"
       ."      }\n";
  $newM="      if(isMobile()){/*CH_MOBDL*/\n"
       ."        try{\n"
       ."          var _fm=document.createElement('form'); _fm.method='POST'; _fm.action='dl.php'; _fm.target='_self'; _fm.style.display='none';\n"
       ."          var _ia=document.createElement('input'); _ia.name='html'; _ia.value=H; _fm.appendChild(_ia);\n"
       ."          var _ib=document.createElement('input'); _ib.name='name'; _ib.value='ChatHelp-Dokument.pdf'; _fm.appendChild(_ib);\n"
       ."          document.body.appendChild(_fm); _fm.submit();\n"
       ."          setTimeout(function(){ try{ document.body.removeChild(_fm); }catch(e9d){} },1500);\n"
       ."          return;\n"
       ."        }catch(eMd){}\n"
       ."        var w=null; try{ w=window.open('','_blank'); }catch(e){ w=null; }\n"
       ."        if(w&&w.document){ try{ w.document.open(); w.document.write(toTab(H)); w.document.close(); return; }catch(e){} }\n"
       ."        overlay(String(html)); return;\n"
       ."      }\n";
  $c=substr_count($src,$oldM);
  if($c===1){ $src=str_replace($oldM,$newM,$src); echo "  ✓ [3] CH_MOBDL — mobil tarayicida PDF: ayni sayfada dogrudan indirme\n"; $ok++; }
  else echo "  ✗ [3] CH_MOBDL anchor ($c, 1 beklenir) — atlandi\n";
} else { echo "  = [3] CH_MOBDL zaten ekli\n"; $ok++; }

/* ── [4] CH_SENDCSS ── */
if(strpos($src,'CH_SENDCSS')===false){
  $css = <<<'CSSBLOCK'
<style id="ch-sendcss">
/* CH_SENDCSS — gonderim modali premium cila (sadece gorunum, davranis ayni) */
#ai-box label:has(input[name="ai-vsa"]){background:rgba(255,255,255,.035)!important;border:1px solid rgba(255,255,255,.09)!important;border-radius:12px!important;padding:11px 12px!important;margin:5px 0!important;font-size:12.5px!important;transition:border-color .15s ease,background .15s ease}
#ai-box label:has(input[name="ai-vsa"]:checked){border-color:rgba(212,168,74,.65)!important;background:rgba(212,168,74,.09)!important;box-shadow:0 0 0 1px rgba(212,168,74,.25) inset}
#ai-box input[name="ai-vsa"]{accent-color:#d4a84a}
#ai-faxwrap{background:rgba(212,168,74,.05)!important;border:1px solid rgba(212,168,74,.22)!important;border-radius:12px!important;padding:12px!important;margin-top:8px!important}
#ai-faxnr{background:#101016!important;border:1px solid rgba(255,255,255,.14)!important;border-radius:10px!important;color:#fff!important;padding:10px 12px!important;font-size:14px!important;letter-spacing:.4px}
#ai-faxnr:focus{border-color:rgba(212,168,74,.6)!important;outline:none!important}
#ch-faxai-btn{background:linear-gradient(135deg,rgba(212,168,74,.16),rgba(212,168,74,.07))!important;border:1px solid rgba(212,168,74,.5)!important;border-radius:10px!important;transition:background .15s}
#ai-send{background:linear-gradient(135deg,#e8c877,#d4a84a)!important;color:#17171b!important;font-weight:800!important;border:0!important;border-radius:12px!important;box-shadow:0 6px 18px rgba(212,168,74,.25)!important}
#ai-back,#ai-cancel{background:transparent!important;border:1px solid rgba(255,255,255,.16)!important;color:#c8c8d8!important;border-radius:12px!important}
.ch-faxprim-opt{outline:none!important}
.ch-faxprim-badge{box-shadow:0 2px 8px rgba(212,168,74,.35)}
</style>
CSSBLOCK;
  $pos=strripos($src,'</body>');
  if($pos!==false){ $src=substr($src,0,$pos).$css."\n".substr($src,$pos); echo "  ✓ [4] CH_SENDCSS — gonderim modali premium CSS eklendi\n"; $ok++; }
  else echo "  ✗ [4] CH_SENDCSS — </body> yok, atlandi\n";
} else { echo "  = [4] CH_SENDCSS zaten ekli\n"; $ok++; }

if($ok===0){ echo "\nHICBIR bolum uygulanamadi — HICBIR sey degistirilmedi.\n"; exit; }

$tmp=tempnam(sys_get_temp_dir(),'pl1').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-prelaunch1-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
echo "\n  Sonuc: $ok/4 bolum tamam (".strlen($chk)." B)\n";
echo "  Test-1: Fax sec -> Empfänger belge akisinda yaziliysa 'KI ile bul' artik calisir.\n";
echo "  Test-2: Chat'ten belge uret -> [TELEFON] artik telefon numarasi (dogum tarihi degil).\n";
echo "  Test-3: MOBIL tarayicida PDF -> sayfa degismeden dogrudan indirme.\n";
echo "  Test-4: Senden modali -> premium altin kartli gorunum.\n";
