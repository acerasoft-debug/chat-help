<?php
/**
 * ChatHelp — apply-freshtool (CH_FRESH) — kesin teshis + cihaz kurtarma araci:
 *  [1] chat/fresh.php olusturur (YENI dosya adi -> hicbir cache'te yok, her zaman taze):
 *      - Diskteki index.php marker'larini ✓/✗ listeler (sunucunun GERCEK durumu)
 *      - Cihazdaki TUM service worker'lari kaldirir + CacheStorage'i temizler
 *      - Edge testi: /chat/ sayfasini (sorgulu + sorgusuz) taze indirip icinde
 *        CH_SENDEN_BIG var mi OTOMATIK kontrol eder:
 *          YESIL = edge temiz, sorun cihazdaydi ve BU SAYFA ONARDI
 *          KIRMIZI = edge ESKI kopya sunuyor -> GoDaddy 'Flush Cache' SART
 *      - 'ChatHelp'i temiz ac' butonu
 *  [2] chat/.htaccess'e index.php + sw.js icin no-store/Surrogate-Control ekler
 *      (bazi host cache'leri bunlara uyar; mevcut .htaccess yedeklenir).
 * KULLANIM: pull2.php?key=...&files=apply-freshtool.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-freshtool BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$D=__DIR__;
$ok=0;

/* ── [1] fresh.php ── */
$fp = <<<'FRESHPHP'
<?php
/* ChatHelp — fresh.php (CH_FRESH) — teshis + cihaz onarim sayfasi */
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache'); header('Expires: 0'); header('Surrogate-Control: no-store');
$src=(string)@file_get_contents(__DIR__.'/index.php');
$marks=['CH_SENDEN_BIG'=>'Senden yesil/buyuk','CH_FORCEMODAL_BTN'=>'Senden -> gonderim modali','CH_PDFDIRECT'=>'PDF direkt indirme','CH_ADDR_SYNCGUARD'=>'Adres kalicilik','CH_SONSTFIX'=>'Sonstiges kutusu','CH_MICOFF_APP'=>'App mikrofon off','CH_NOCACHE3'=>'Cache-bypass cerezi'];
$sw=(string)@file_get_contents(__DIR__.'/sw.js');
$swv='?'; if(preg_match('/service worker (v\d+)/',$sw,$m)) $swv=$m[1];
?>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ChatHelp Teşhis</title>
<style>
body{background:#14141a;color:#eee;font-family:system-ui,sans-serif;max-width:560px;margin:0 auto;padding:22px;line-height:1.6}
h2{color:#e8c877}.card{background:#1c1c24;border:1px solid rgba(255,255,255,.1);border-radius:14px;padding:16px;margin:12px 0}
.ok{color:#42df94}.bad{color:#ff7070}.wait{color:#e8c877}
button,a.btn{display:block;width:100%;box-sizing:border-box;text-align:center;background:linear-gradient(135deg,#37d17d,#1fa25c);color:#fff;border:0;border-radius:12px;padding:15px;font-size:16px;font-weight:800;margin-top:14px;cursor:pointer;text-decoration:none}
.small{font-size:12px;color:#9aa}
</style>
<h2>🔧 ChatHelp Teşhis &amp; Onarım</h2>

<div class="card"><b>1) Sunucu diski (gerçek durum):</b><br>
<?php foreach($marks as $k=>$d){ $v=strpos($src,$k)!==false; echo '<span class="'.($v?'ok':'bad').'">'.($v?'✓':'✗').' '.htmlspecialchars($d).'</span><br>'; } ?>
<span class="small">index.php: <?php echo number_format(strlen($src)); ?> B · sw.js: <?php echo htmlspecialchars($swv); ?> · saat: <?php echo date('H:i:s'); ?></span>
</div>

<div class="card"><b>2) Cihaz temizliği:</b> <span id="dev" class="wait">çalışıyor…</span></div>
<div class="card"><b>3) Edge/CDN testi:</b><br>
 sorgulu adres: <span id="eq" class="wait">test ediliyor…</span><br>
 normal adres: <span id="ep" class="wait">test ediliyor…</span>
 <div id="verdict" style="margin-top:10px;font-weight:800"></div>
</div>
<a class="btn" href="/chat/?r=<?php echo time(); ?>">🚀 ChatHelp'i TEMİZ AÇ</a>
<p class="small">Bu sayfa: eski service worker'ları kaldırır, tarayıcı önbelleğini siler ve sunucudan gelen sayfanın güncel olup olmadığını ölçer.</p>

<script>
(async function(){
  var dev=document.getElementById('dev');
  try{
    var n=0;
    if('serviceWorker' in navigator){
      var regs=await navigator.serviceWorker.getRegistrations();
      for(var r of regs){ try{ await r.unregister(); n++; }catch(e){} }
    }
    var c=0;
    if(window.caches){ var ks=await caches.keys(); for(var k of ks){ try{ await caches.delete(k); c++; }catch(e){} } }
    dev.textContent='✓ '+n+' service worker kaldırıldı, '+c+' önbellek silindi';
    dev.className='ok';
  }catch(e){ dev.textContent='kısmi: '+e; dev.className='bad'; }

  async function probe(url,el){
    var s=document.getElementById(el);
    try{
      var r=await fetch(url,{cache:'reload',credentials:'omit'});
      var t=await r.text();
      var fresh=t.indexOf('CH_SENDEN_BIG')!==-1;
      s.textContent=fresh?'✓ GÜNCEL':'✗ ESKİ KOPYA';
      s.className=fresh?'ok':'bad';
      return fresh;
    }catch(e){ s.textContent='hata: '+e; s.className='bad'; return null; }
  }
  var f1=await probe('/chat/?probe='+Date.now(),'eq');
  var f2=await probe('/chat/','ep');
  var v=document.getElementById('verdict');
  if(f1&&f2){ v.innerHTML='<span class="ok">✅ HER ŞEY GÜNCEL — sorun cihazdaydı ve ONARILDI. Aşağıdaki butonla aç.</span>'; }
  else if(f1&&!f2){ v.innerHTML='<span class="bad">⚠️ Edge önbelleği NORMAL adreste eski kopya sunuyor → GoDaddy panelinden FLUSH CACHE şart.</span>'; }
  else if(f1===false&&f2===false){ v.innerHTML='<span class="bad">⛔ Edge önbelleği HER adreste eski kopya sunuyor → GoDaddy panelinden FLUSH CACHE şart (kod sunucuda hazır, 1. kutuda ✓ görünüyor).</span>'; }
  else{ v.innerHTML='<span class="wait">test tamamlanamadı — ekran görüntüsü gönderin.</span>'; }
})();
</script>
FRESHPHP;

$tmp=tempnam(sys_get_temp_dir(),'fr').'.php'; file_put_contents($tmp,$fp);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "  ✗ [1] fresh.php LINT: ".implode(' ',$lo)."\n"; }
else{
  $w=@file_put_contents($D.'/fresh.php',$fp);
  if($w!==false){ echo "  ✓ [1] fresh.php olusturuldu (".strlen($fp)." B)\n"; $ok++; }
  else echo "  ✗ [1] fresh.php yazilamadi\n";
}

/* ── [2] .htaccess ── */
$ht=$D.'/.htaccess';
$cur=@file_get_contents($ht); if($cur===false)$cur='';
if(strpos($cur,'CH_EDGEKILL')!==false){ echo "  = [2] .htaccess zaten ekli\n"; $ok++; }
else{
  $add="\n# CH_EDGEKILL — index.php & sw.js asla onbellege alinmasin (edge dahil)\n"
      ."<IfModule mod_headers.c>\n"
      ."<FilesMatch \"^(index\\.php|sw\\.js)$\">\n"
      ."Header always set Cache-Control \"no-store, no-cache, must-revalidate, max-age=0\"\n"
      ."Header always set Surrogate-Control \"no-store\"\n"
      ."Header always set Pragma \"no-cache\"\n"
      ."Header always set Expires \"0\"\n"
      ."</FilesMatch>\n"
      ."</IfModule>\n";
  if($cur!=='') @file_put_contents($ht.'.bak-edgekill-'.date('Ymd-His'),$cur);
  $w=@file_put_contents($ht,$cur.$add);
  if($w!==false){ echo "  ✓ [2] .htaccess: no-store + Surrogate-Control eklendi\n"; $ok++; }
  else echo "  ✗ [2] .htaccess yazilamadi\n";
}

if(function_exists('opcache_reset')) @opcache_reset();
echo "\n  Sonuc: $ok/2\n";
echo "  SIMDI: cihazda su adresi ac ve ekrandakini soyle/ekran goruntusu at:\n";
echo "  https://chat-help.com/chat/fresh.php\n";
