<?php
/**
 * ChatHelp — apply-cache-buster (CH_CACHEBUST)
 *  "Eski site surekli cikmasin" — surum-tabanli OTOMATIK guncelleme.
 *  index.php'ye bir bekci enjekte edilir:
 *   1) Varsa eski Service Worker'lari ve tum Cache Storage'i temizler.
 *   2) ver.php'den CANLI surumu (index.php filemtime) rastgele-query + no-store
 *      ile ceker; yuklu HTML'in surumu (PHP ile gomulu filemtime) canlidan
 *      ESKIYSE, sayfayi ?_ch=<surum> ile BIR KEZ taze surume yeniler
 *      (edge/CDN cache anahtarini da degistirir). Sonsuz dongu korumali.
 *  ver.php dosyasinin da yuklu olmasi gerekir (ayni pull ile gonderin).
 * KULLANIM: pull-updates.php?files=ver.php,apply-cache-buster.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-cache-buster BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_CACHEBUST')!==false) exit("Zaten ekli (CH_CACHEBUST).\n");

if (!file_exists(__DIR__.'/ver.php')) echo "  ⚠ UYARI: ver.php bulunamadi — ayni pull ile gonderin (files=ver.php,apply-cache-buster.php)\n";

/* index.php gövdesi PHP-passthrough oldugundan <?php ... ?> serve aninda calisir.
   BUILD sayisal degilse (PHP degerlendirmediyse) bekci devre disi kalir — guvenli. */
$bundle = "<script id=\"ch-cachebust-js\">\n"
 . "/* CH_CACHEBUST — surum-tabanli otomatik guncelleme */\n"
 . "try{(function(){\n"
 . "  var BUILD=(\"<?php echo @filemtime(__FILE__); ?>\"||\"\").trim();\n"
 . "  /* eski Service Worker + Cache Storage temizligi (varsa) */\n"
 . "  try{ if(navigator.serviceWorker&&navigator.serviceWorker.getRegistrations){ navigator.serviceWorker.getRegistrations().then(function(rs){ rs.forEach(function(r){ try{r.unregister();}catch(e){} }); }); } }catch(e){}\n"
 . "  try{ if(window.caches&&caches.keys){ caches.keys().then(function(ks){ ks.forEach(function(k){ try{caches.delete(k);}catch(e){} }); }); } }catch(e){}\n"
 . "  /* surum kontrolu */\n"
 . "  try{\n"
 . "    if(!/^\\d+$/.test(BUILD)) return; /* PHP degerlendirmediyse: dokunma */\n"
 . "    fetch('ver.php?_='+Date.now(),{cache:'no-store'}).then(function(r){return r.text();}).then(function(live){\n"
 . "      live=(live||'').trim();\n"
 . "      if(!/^\\d+$/.test(live)||live==='0') return;\n"
 . "      if(live===BUILD){ try{ sessionStorage.removeItem('ch_vr'); }catch(e){} return; }\n"
 . "      var already=''; try{ already=sessionStorage.getItem('ch_vr')||''; }catch(e){}\n"
 . "      if(already===live) return; /* bu surum icin zaten yenilendi -> dongu yok */\n"
 . "      try{ sessionStorage.setItem('ch_vr',live); }catch(e){}\n"
 . "      try{\n"
 . "        var u=new URL(location.href); u.searchParams.set('_ch',live);\n"
 . "        location.replace(u.toString());\n"
 . "      }catch(e){ try{ location.reload(); }catch(e2){} }\n"
 . "    }).catch(function(){});\n"
 . "  }catch(e){}\n"
 . "})();}catch(e){}\n"
 . "</script>";

$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi.\n");
$src = substr($src,0,$pos).$bundle."\n".substr($src,$pos);
echo "  ✓ surum bekcisi enjekte edildi (SW/cache temizligi + otomatik yenileme)\n";

$tmp = tempnam(sys_get_temp_dir(),'cb').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-cachebust-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_CACHEBUST uygulandi. Eski surumdeki kullanicilar artik otomatik taze surume yenilenir.\n";
