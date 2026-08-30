<?php
/**
 * ChatHelp — ÜLKE/HUKUK TESPİT TEŞHİS (salt-okunur)
 * =================================================
 * Türkiye'den girince neden Alman hukuku çıkıyor — sebebini gösterir.
 * Hiçbir dosyayı değiştirmez.
 *
 * KULLANIM: html/chat/ -> https://chat-help.com/chat/diag-geo.php
 *   (Türkiye'den / Türk VPN ile aç ki gerçek tespit görünsün.)
 * Çıktının tamamını yapıştır. Sonra SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
@set_time_limit(30);
echo "ChatHelp — Ülke/Hukuk Teşhis  (".date('c').")\n=======================================\n\n";

/* 1) Bu ziyaretçi için sunucu ne görüyor? */
echo "=== 1) İstek başlıkları (sunucu ziyaretçiyi nereden görüyor) ===\n";
foreach(['HTTP_CF_IPCOUNTRY','HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $k)
    echo "  ".str_pad($k,26)." = ".($_SERVER[$k] ?? '(yok)')."\n";

function vip(){ foreach(['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $k){ if(!empty($_SERVER[$k])){ $ip=trim(explode(',',$_SERVER[$k])[0]); if(filter_var($ip,FILTER_VALIDATE_IP)) return $ip; } } return ''; }
$ip=vip(); echo "  Seçilen ziyaretçi IP  = ".($ip?:'(boş)')."\n";

echo "\n=== 2) Canlı ip-api tespiti (geo.php ile aynı servis) ===\n";
if($ip){
    $ch=curl_init('http://ip-api.com/json/'.urlencode($ip).'?fields=status,country,countryCode,query');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>4]);
    $r=curl_exec($ch); $err=curl_error($ch); curl_close($ch);
    echo "  yanıt: ".($r?:'(boş)  hata: '.$err)."\n";
} else echo "  IP yok — tespit yapılamaz\n";
$cf = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? '';
echo "  >> Cloudflare ülke başlığı: ".($cf?:'(YOK — Cloudflare geçmiyor olabilir!)')."\n";

/* 3) geo.php gerçek çıktısı */
echo "\n=== 3) geo.php çıktısı (dahili çağrı) ===\n";
$geoOut = @file_get_contents('http://127.0.0.1'.dirname($_SERVER['REQUEST_URI'] ?? '/chat/').'/geo.php');
if($geoOut===false){ $geoOut=@file_get_contents((isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['SCRIPT_NAME']).'/geo.php'); }
echo "  ".($geoOut!==false ? trim($geoOut) : '(geo.php dahili çağrı başarısız — elle aç: /chat/geo.php)')."\n";

/* 4) index.php: setCountry + SUPPORTED + ülke->kategori/hukuk mantığı */
echo "\n=== 4) index.php ülke/hukuk mantığı ===\n";
$f=__DIR__.'/index.php';
if(!file_exists($f)){ echo "  index.php YOK\n"; exit; }
$s=file_get_contents($f); echo "  boyut: ".strlen($s)." bayt\n";
echo "  CH_GEO_MULTI ekli mi: ".(strpos($s,'CH_GEO_MULTI')!==false?'EVET':'HAYIR')."\n";
echo "  'ch_geo_multi' (tek-seferlik bayrak) geçişi: ".substr_count($s,'ch_geo_multi')."\n";
echo "  'ch_lm' (elle dil kilidi) geçişi: ".substr_count($s,'ch_lm')."\n";
echo "  SUPPORTED geçişi: ".substr_count($s,'SUPPORTED')."\n";

function dumpFn($s,$needle,$label,$before=40,$max=2600){
    $p=strpos($s,$needle);
    if($p===false){ echo "\n  [$label] bulunamadı ($needle)\n"; return; }
    // brace-match
    $i=strpos($s,'{',$p); if($i===false){ echo "\n  [$label] gövde yok\n"; return; }
    $d=0;$j=$i; do{ if($s[$j]==='{')$d++; elseif($s[$j]==='}')$d--; $j++; }while($d>0 && $j<strlen($s) && ($j-$i)<$max);
    echo "\n  --- $label ---\n".preg_replace('/^/m','  | ',substr($s,max(0,$p-$before),min($max,$j-max(0,$p-$before))))."\n";
}
dumpFn($s,'function setCountry','setCountry()');
// SUPPORTED tanımı
if(preg_match('/SUPPORTED\s*=\s*\[[^\]]*\]/',$s,$m)) echo "\n  SUPPORTED = ".$m[0]."\n";
// CH_GEO_MULTI bloğu
$p=strpos($s,'CH_GEO_MULTI');
if($p!==false) echo "\n  --- CH_GEO_MULTI bloğu ---\n".preg_replace('/^/m','  | ',substr($s,$p-30,900))."\n";

/* 5) ülkeye göre kategori/hukuk kataloğu var mı? (TR vs DE) */
echo "\n=== 5) Ülke bazlı kategori/katalog izleri ===\n";
foreach(['TR','FR','DE'] as $cc){
    $n = substr_count($s,"'$cc'") + substr_count($s,"\"$cc\"");
    echo "  '$cc' geçiş sayısı: $n\n";
}
foreach(['CATS','CATALOG','categories','lawByCountry','LAW_','byCountry','COUNTRY_CATS','docsByCC'] as $kw){
    $n=substr_count($s,$kw); if($n) echo "  '$kw' geçişi: $n\n";
}
echo "\n=== BİTTİ — tüm çıktıyı yapıştır, sonra diag-geo.php'yi SİL ===\n";
