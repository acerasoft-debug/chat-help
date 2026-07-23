<?php
/**
 * ChatHelp — apply-fallfix2 — Fall belgesi 'Telefon: 1982-01-03' + eksik soyad (3 duzeltme):
 *  KOK NEDEN: bazi profillerde alanlar kaymis (f6'da dogum tarihi, f2 bos).
 *  [1] CH_FALLPROF (fall-api.php): sunucu artik telefon alanina TARIH gorunumlu
 *      deger ASLA yazmaz (f6 telefon degilse f5'e bakar, o da degilse satiri
 *      TAMAMEN atlar); bos E-Mail satiri da atlanir.
 *  [2] CH_PROFHEAL (index.php): tek seferlik profil onarimi — f6'daki dogum
 *      tarihi f5'e tasinir; soyad bossa ad-alanindan veya ch_user.name'den
 *      tamamlanir; ch_prof_v3 + aktif profil slotu guncellenir.
 *  [3] CH_PROFGATE_FORM (index.php): profil kapisi engelleyince dogrudan profil
 *      formu acilir (canli block() metni artik kesin biliniyor).
 * KULLANIM: pull2.php?key=...&files=apply-fallfix2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fallfix2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$ok=0;

/* ── [1] CH_FALLPROF — fall-api.php ── */
$fa=__DIR__.'/fall-api.php';
$fsrc=@file_get_contents($fa);
if($fsrc===false){ echo "  ✗ [1] fall-api.php okunamadi — atlandi\n"; }
elseif(strpos($fsrc,'CH_FALLPROF')!==false){ echo "  = [1] zaten ekli\n"; $ok++; }
else{
  $o1=<<<'ANC'
    $prof    = $body['profile'] ?? [];
    $datum   = trim($body['datum'] ?? date('d.m.Y'));
    $absName = trim(($prof['f1'] ?? '') . ' ' . ($prof['f2'] ?? ''));
    $absAdr  = trim(($prof['f3'] ?? '') . (($prof['f4'] ?? '') ? ', ' . $prof['f4'] : ''));
    $profBlock = "ABSENDERDATEN (exakt diese Daten im Dokument verwenden, KEINE Platzhalter):\n"
        . "Name: "    . ($absName ?: '[Vorname Nachname]') . "\n"
        . "Adresse: " . ($absAdr  ?: '[Straße, PLZ Ort]')  . "\n"
        . "E-Mail: "  . ($prof['f7'] ?? '') . "\n"
        . "Telefon: " . ($prof['f6'] ?? '') . "\n"
        . "Datum: "   . $datum . "\n\n";
ANC;
  $n1=<<<'ANC'
    $prof    = $body['profile'] ?? [];
    $datum   = trim($body['datum'] ?? date('d.m.Y'));
    /* CH_FALLPROF — telefon alanina tarih yazilmaz; bos alan satirlari atlanir */
    $chIsDate = function($s){ return (bool)preg_match('/^\s*(\d{4}-\d{2}-\d{2}|\d{1,2}\.\d{1,2}\.\d{2,4})\s*$/', (string)$s); };
    $chIsTel  = function($s) use ($chIsDate){ $s=trim((string)$s); if($s===''||$chIsDate($s)) return false; if(!preg_match('/^[+0-9 ()\/\-.]{5,}$/',$s)) return false; return strlen(preg_replace('/\D/','',$s))>=6; };
    $chTel=''; if($chIsTel($prof['f6'] ?? '')) $chTel=trim((string)$prof['f6']); elseif($chIsTel($prof['f5'] ?? '')) $chTel=trim((string)$prof['f5']);
    $absName = trim(($prof['f1'] ?? '') . ' ' . ($prof['f2'] ?? ''));
    $absAdr  = trim(($prof['f3'] ?? '') . (($prof['f4'] ?? '') ? ', ' . $prof['f4'] : ''));
    $profBlock = "ABSENDERDATEN (exakt diese Daten im Dokument verwenden, KEINE Platzhalter; nicht genannte Felder WEGLASSEN):\n"
        . "Name: "    . ($absName ?: '[Vorname Nachname]') . "\n"
        . "Adresse: " . ($absAdr  ?: '[Straße, PLZ Ort]')  . "\n"
        . ((($prof['f7'] ?? '') !== '') ? ("E-Mail: " . $prof['f7'] . "\n") : "")
        . (($chTel !== '') ? ("Telefon: " . $chTel . "\n") : "")
        . "Datum: "   . $datum . "\n\n";
ANC;
  $c=substr_count($fsrc,$o1);
  if($c===1){
    $fsrc=str_replace($o1,$n1,$fsrc);
    $tmp=tempnam(sys_get_temp_dir(),'fa').'.php'; file_put_contents($tmp,$fsrc);
    $lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
    if($rc!==0){ echo "  ✗ [1] LINT — fall-api DEGISTIRILMEDI: ".implode(' ',$lo)."\n"; }
    else{
      @copy($fa,$fa.'.bak-fallprof-'.date('Ymd-His'));
      @file_put_contents($fa,$fsrc);
      echo "  ✓ [1] CH_FALLPROF — fall-api: telefon dogrulama + bos satir atlama\n"; $ok++;
    }
  } else echo "  ✗ [1] anchor ($c, 1 beklenir) — atlandi\n";
}

/* ── index.php bolumleri ── */
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false){ echo "  ✗ index.php okunamadi — [2],[3] atlandi\n"; }
else{
  $mod=false;

  /* [2] CH_PROFHEAL */
  if(strpos($src,'CH_PROFHEAL')===false){
    $block = <<<'HTMLBLOCK'
<script id="ch-profheal-js">
/* CH_PROFHEAL — kaymis profil alanlarini onar (f6'daki dogum tarihi -> f5; soyad tamamla) */
try{(function(){
  function isDate(s){ return /^\s*(\d{4}-\d{2}-\d{2}|\d{1,2}\.\d{1,2}\.\d{2,4})\s*$/.test(String(s||'')); }
  function heal(){
    try{
      var d=null; try{ d=JSON.parse(localStorage.getItem('ch_prof_v3')||'null'); }catch(e){}
      if(!d && window.P && (P.f1||P.f3)) d={f1:P.f1||'',f2:P.f2||'',f3:P.f3||'',f4:P.f4||'',f5:P.f5||'',f6:P.f6||'',f7:P.f7||''};
      if(!d) return;
      var ch=false;
      if(isDate(d.f6)){ if(!d.f5){ d.f5=d.f6; d.f6=''; ch=true; } else { d.f6=''; ch=true; } }
      if(!(d.f2||'').trim()){
        var p1=String(d.f1||'').trim().split(/\s+/);
        if(p1.length>=2){ d.f1=p1[0]; d.f2=p1.slice(1).join(' '); ch=true; }
        else{
          try{ var un=String((JSON.parse(localStorage.getItem('ch_user')||'{}').name)||'').trim();
            var ps=un.split(/\s+/);
            if(ps.length>=2 && (!d.f1 || ps[0].toLowerCase()===String(d.f1).toLowerCase())){ d.f1=d.f1||ps[0]; d.f2=ps.slice(1).join(' '); ch=true; }
          }catch(e){}
        }
      }
      if(!ch) return;
      try{ localStorage.setItem('ch_prof_v3',JSON.stringify(d)); }catch(e){}
      try{ if(window.P){ ['f1','f2','f3','f4','f5','f6','f7'].forEach(function(k){ window.P[k]=d[k]||''; }); } }catch(e){}
      try{ var pa=JSON.parse(localStorage.getItem('ch_profiles')||'null'); if(Array.isArray(pa)&&pa.length){ var ai=parseInt(localStorage.getItem('ch_prof_active')||'0',10); if(isNaN(ai)||ai<0||ai>=pa.length)ai=0; pa[ai].d=d; localStorage.setItem('ch_profiles',JSON.stringify(pa)); } }catch(e){}
    }catch(e){}
  }
  heal(); setTimeout(heal,1500); setTimeout(heal,4000);
})();}catch(e){}
</script>
HTMLBLOCK;
    $pos=strripos($src,'</body>');
    if($pos!==false){ $src=substr($src,0,$pos).$block."\n".substr($src,$pos); echo "  ✓ [2] CH_PROFHEAL — kaymis profil alanlari otomatik onarilir\n"; $ok++; $mod=true; }
    else echo "  ✗ [2] </body> yok — atlandi\n";
  } else { echo "  = [2] zaten ekli\n"; $ok++; }

  /* [3] CH_PROFGATE_FORM — canli block() kesin anchor */
  if(strpos($src,'CH_PROFGATE_FORM')===false){
    $o3="function block(){\n    try{ if(typeof addMsg===\"function\") addMsg(\"ai\", MSG[UIL()]||MSG.en); }catch(e){}\n    /*CH_HOME_NOT_KONTO — profil-zorunlu otomatik Konto kaldirildi*/\n  }";
    $n3="function block(){/*CH_PROFGATE_FORM*/\n    try{ if(typeof addMsg===\"function\") addMsg(\"ai\", MSG[UIL()]||MSG.en); }catch(e){}\n    try{ if(typeof showProfileForm===\"function\"){ try{ if(typeof gP===\"function\") gP(\"chat\"); }catch(eg){} showProfileForm((typeof CD!==\"undefined\"&&CD)||null); } }catch(e){}\n    /*CH_HOME_NOT_KONTO korunur: Konto'ya YONLENDIRME yok*/\n  }";
    $c=substr_count($src,$o3);
    if($c===1){ $src=str_replace($o3,$n3,$src); echo "  ✓ [3] CH_PROFGATE_FORM — engelde dogrudan profil formu\n"; $ok++; $mod=true; }
    else echo "  ✗ [3] anchor ($c, 1 beklenir) — atlandi\n";
  } else { echo "  = [3] zaten ekli\n"; $ok++; }

  if($mod){
    $tmp=tempnam(sys_get_temp_dir(),'ff2').'.php'; file_put_contents($tmp,$src);
    $lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
    if($rc!==0){ echo "LINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
    @file_put_contents($file.'.bak-fallfix2-'.date('Ymd-His'),(string)@file_get_contents($file));
    $w=@file_put_contents($file,$src);
    if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
  }
}

if(function_exists('opcache_reset')) @opcache_reset();
echo "\n  Sonuc: $ok/3 bolum tamam\n";
echo "  Test: Fall lösen -> belge basliginda TAM AD, 'Telefon:' satirinda tarih YOK\n";
echo "  (telefon kayitli degilse satir hic yok). Not: PDF butonu icin cihazin YENI\n";
echo "  kodu almasi gerekir (onbellek konusu hala acik).\n";
