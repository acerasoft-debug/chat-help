<?php
/**
 * ChatHelp — apply-intro-send (CH_INTRO_SEND) — intro/hero'yu "olustur VE gonder"
 *  yonune cevir (artik gonderiyoruz).
 *   [1] Statik hero alt satirini (#wlc-p) gonderim-odakli metinle DEGISTIR
 *       (kaynak degisir -> savas yok). Almanca varsayilan.
 *   [2] Kucuk, PERPETUAL-OLMAYAN script: yuklemede + 3 gecikmeli kontrolde
 *       #wlc-p'yi kullanicinin diline gore ayarla (de/tr/en/fr/es/it). Interval
 *       yok -> churn yok; ayrica ayni yazma stabilize2 ile no-op.
 * KULLANIM: pull2.php?key=...&files=apply-intro-send.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-intro-send BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_INTRO_SEND')!==false) exit("Zaten ekli (CH_INTRO_SEND).\n");

/* [1] statik alt satiri degistir */
$old='<p class="wlc-sub" id="wlc-p">Professionelle Rechtsdokumente in Sekunden.</p>';
$new='<p class="wlc-sub" id="wlc-p">Rechtsdokumente in Sekunden erstellen — und direkt versenden: Post, Einschreiben oder Fax.</p>';
$c1=substr_count($src,$old);
if($c1===1){ $src=str_replace($old,$new,$src); echo "  ✓ [1] Hero alt satiri gonderim-odakli (DE varsayilan)\n"; }
else { echo "  ! [1] hero alt satir anchor ($c1) — atlandi (script yine de dil ayarlar)\n"; }

/* [2] dile gore ayarla (perpetual degil) */
$block = <<<'HTMLBLOCK'
<script id="ch-intro-send-js">
/* CH_INTRO_SEND — hero alt satirini dile gore gonderim-odakli yap (churn yok) */
try{(function(){
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2);}catch(e){return 'de';} }
  var S={
    de:'Rechtsdokumente in Sekunden erstellen — und direkt versenden: Post, Einschreiben oder Fax.',
    tr:'Saniyeler içinde resmi belge oluştur — ve doğrudan gönder: Posta, İadeli Taahhütlü veya Faks.',
    en:'Create legal documents in seconds — and send them directly: post, registered mail or fax.',
    fr:'Créez des documents juridiques en secondes — et envoyez-les directement : courrier, recommandé ou fax.',
    es:'Crea documentos legales en segundos — y envíalos directamente: correo, certificado o fax.',
    it:'Crea documenti legali in secondi — e inviali subito: posta, raccomandata o fax.'
  };
  function set(){ try{ var p=document.getElementById('wlc-p'); if(p){ var t=S[lang()]||S.de; if(p.textContent!==t) p.textContent=t; } }catch(e){} }
  set(); [400,1200,2500].forEach(function(ms){ setTimeout(set,ms); });
  try{ window.addEventListener('storage',function(e){ if(e&&e.key==='ch_uilang') set(); }); }catch(e){}
})();}catch(e){}
</script>
HTMLBLOCK;
$pos=strripos($src,'</body>');
if($pos===false) exit("</body> yok\n");
$src=substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp=tempnam(sys_get_temp_dir(),'is').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-introsend-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_INTRO_SEND')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ [2] Dile gore gonderim-odakli alt satir (6 dil)\n";
echo "\n  ✓ CH_INTRO_SEND diskte (".strlen($chk)." B). Hard-refresh.\n";
echo "SIRADA: 'PDF erstellen & senden' butonu + Fax'i ana gonderim yapma (ayri adim).\n";
