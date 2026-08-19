<?php
/**
 * ChatHelp — CACHE HEADER GÜÇLENDİRME (CDN-özel header'lar dahil)
 * ------------------------------------------------------------------------
 *  Kanıt: index.php zaten 'Cache-Control: no-store' gönderiyor (CH_NOCACHE)
 *  ama edge (GoDaddy'nin kendi Cloudflare Enterprise katmanı) bunu görmezden
 *  gelip 'public, max-age=2678400' ile cevap veriyor — yani edge'de bir
 *  "Cache Everything" / Edge-TTL-override kuralı origin header'ını eziyor.
 *
 *  Bu durum SADECE hosting panelinden/destekten değiştirilebilir (bkz.
 *  GoDaddy destek talebi metni). Ancak bazı CDN/Varnish katmanları
 *  Cache-Control yerine/yanında şu header'lara bakar — zararsız, ücretsiz,
 *  ihtimal dahilinde işe yarayabilir:
 *   - Surrogate-Control (Varnish/Fastly/bazı CDN standardı)
 *   - CDN-Cache-Control (genel CDN standardı, RFC taslağı)
 *   - Cloudflare-CDN-Cache-Control (Cloudflare'e ÖZEL, diğerlerinden önce okunur)
 *   - Vary: Cookie (kullanıcıya özel önbellek varyasyonunu zorunlu kılar)
 *
 * KULLANIM: pull-updates.php?files=apply-strong-nocache.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-strong-nocache BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
if(strpos($src,"CH_STRONG_NOCACHE")!==false) exit("Zaten ekli (CH_STRONG_NOCACHE).\n");
$anchor="/*CH_NOCACHE*/";
if(strpos($src,$anchor)===false) exit("anchor (CH_NOCACHE) yok — once apply-nocache.php calistirilmali\n");
file_put_contents($file.".bak-strongnocache-".date("Ymd-His"),$src);

$add = " header('Surrogate-Control: no-store'); header('CDN-Cache-Control: no-store'); header('Cloudflare-CDN-Cache-Control: no-store'); header('Vary: Cookie'); /*CH_STRONG_NOCACHE*/";
$src = str_replace($anchor, $anchor.$add, $src);

$tmp=$file.".tmp-sn"; file_put_contents($tmp,$src);
$out=[]; $code=0; exec("php -l ".escapeshellarg($tmp)." 2>&1",$out,$code); @unlink($tmp);
if($code!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n".implode("\n",$out)."\n"; exit; }

if($src!==$start){ file_put_contents($file,$src); echo "Ek CDN-no-cache header'lari eklendi (CH_STRONG_NOCACHE).\n"; }
else exit("degisiklik yok!\n");
echo "SIL: rm apply-strong-nocache.php\n";
echo "NOT: Bu header'lar origin'den dogru gidiyor olsa da, GoDaddy'nin edge Cache-Everything\n";
echo "     kurali override ediyorsa yine de public/max-age donebilir. Kalici cozum icin\n";
echo "     GoDaddy destegine /chat/ path'i icin cache-bypass talebi gonderilmesi gerekiyor\n";
echo "     (asagidaki hazir metin).\n";
