<?php
/**
 * ChatHelp — ÜLKE/HUKUK OTOMATİK GEÇİŞ DÜZELTME (+ teşhis)
 * =======================================================
 * "Türkiye'den girince Alman hukuku çıkıyor" düzeltmesi.
 *   - CH_GEO_MULTI'deki "sadece bir kez tespit" kilidini (ch_geo_multi) kaldırır
 *     -> her yüklemede yeniden tespit; TR/FR IP'de setCountry otomatik çağrılır.
 *   - Kilit yoksa/CH_GEO_MULTI yoksa bunu da raporlar (o zaman katalog/tespit sorunu).
 *   - Ziyaretçi için CANLI tespiti + setCountry/katalog dökümünü basar (kalan sebep görünür).
 * index.php yedeklenir. Hiçbir hukuki içeriği silmez.
 *
 * KULLANIM: html/chat/ -> Türkiye'den aç:
 *   https://chat-help.com/chat/fix-geo.php
 * Çıktının tamamını yapıştır. Sonra opcache-reset.php aç, fix-geo.php'yi SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
@set_time_limit(40);
echo "ChatHelp — Ülke/Hukuk Düzeltme  (".date('c').")\n=========================================\n\n";

/* ---- 1) Bu ziyaretçi için CANLI tespit ---- */
echo "=== 1) Sunucu bu ziyaretçiyi nereden görüyor? ===\n";
foreach(['HTTP_CF_IPCOUNTRY','HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $k)
    echo "  ".str_pad($k,26)." = ".($_SERVER[$k] ?? '(yok)')."\n";
function vip(){ foreach(['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $k){ if(!empty($_SERVER[$k])){ $ip=trim(explode(',',$_SERVER[$k])[0]); if(filter_var($ip,FILTER_VALIDATE_IP)) return $ip; } } return ''; }
$ip=vip(); echo "  ziyaretçi IP = ".($ip?:'(boş)')."\n";
$cf=$_SERVER['HTTP_CF_IPCOUNTRY']??'';
$detect=$cf;
echo "\n=== 2) ip-api tespiti ===\n";
if($ip){
    $ch=curl_init('http://ip-api.com/json/'.urlencode($ip).'?fields=status,country,countryCode');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>4]); $r=curl_exec($ch); curl_close($ch);
    echo "  ".($r?:'(boş)')."\n"; $d=json_decode($r,true);
    if(!$detect && ($d['countryCode']??'')) $detect=strtoupper($d['countryCode']);
} else echo "  IP yok\n";
echo "  >> TESPİT EDİLEN ÜLKE: ".($detect?:'(YOK)').($cf?'':"   [Cloudflare CF-IPCOUNTRY başlığı GELMİYOR]")."\n";

/* ---- 3) index.php: kilit kaldırma + döküm ---- */
$f=__DIR__.'/index.php';
echo "\n=== 3) index.php düzeltme ===\n";
if(!file_exists($f)){ echo "  index.php YOK — dur, bana haber ver.\n"; exit; }
$s=file_get_contents($f); $orig=$s;
$bak=$f.'.bak-fixgeo-'.date('Ymd-His'); file_put_contents($bak,$s); echo "  yedek: ".basename($bak)."\n";

$hasMulti = strpos($s,'CH_GEO_MULTI')!==false;
echo "  CH_GEO_MULTI ekli: ".($hasMulti?'EVET':'HAYIR')."\n";
$before = substr_count($s,'ch_geo_multi');
echo "  'ch_geo_multi' kilit geçişi (önce): $before\n";

/* "sadece bir kez" kilidini kaldır -> her yüklemede yeniden tespit */
$s=str_replace('if(localStorage.getItem("ch_geo_multi")) return;','/* kilit kaldırıldı: her yüklemede tespit */',$s);
$s=str_replace("localStorage.setItem(\"ch_geo_multi\",\"1\");",'/* kilit yazımı kaldırıldı */',$s);

$fixed = ($s!==$orig);
if($fixed) file_put_contents($f,$s);
echo "  'ch_geo_multi' kilit geçişi (sonra): ".substr_count($s,'ch_geo_multi')."\n";
echo "  >> KİLİT KALDIRMA: ".($fixed?"UYGULANDI ✅ (artık her açılışta ülke yeniden tespit edilir)":"kalıp bulunamadı (aşağıdaki CH_GEO_MULTI dökümüne bak)")."\n";

/* ---- 4) setCountry + SUPPORTED + katalog dökümü (kalan sebep için) ---- */
echo "\n=== 4) setCountry / SUPPORTED / CH_GEO_MULTI (inceleme) ===\n";
function dumpFn($s,$needle,$label,$before=40,$max=2400){
    $p=strpos($s,$needle); if($p===false){ echo "  [$label] yok ($needle)\n"; return; }
    $i=strpos($s,'{',$p); if($i===false){ echo "  [$label] gövde yok\n"; return; }
    $d=0;$j=$i; do{ if($s[$j]==='{')$d++; elseif($s[$j]==='}')$d--; $j++; }while($d>0 && $j<strlen($s) && ($j-$i)<$max);
    echo "  --- $label ---\n".preg_replace('/^/m','  | ',substr($s,max(0,$p-$before),min($max+$before,$j-max(0,$p-$before))))."\n\n";
}
dumpFn($orig,'function setCountry','setCountry()');
if(preg_match('/SUPPORTED\s*=\s*\[[^\]]*\]/',$orig,$m)) echo "  SUPPORTED = ".$m[0]."\n";
$p=strpos($orig,'CH_GEO_MULTI'); if($p!==false) echo "\n  --- CH_GEO_MULTI ---\n".preg_replace('/^/m','  | ',substr($orig,$p-30,760))."\n";

echo "\n=== 5) Ülke bazlı katalog izleri (TR hukuku var mı, yoksa hep Almanca mı?) ===\n";
foreach(['TR','FR','DE'] as $cc){ echo "  '$cc' geçiş: ".(substr_count($orig,"'$cc'")+substr_count($orig,"\"$cc\""))."\n"; }
foreach(['CATS','CATALOG','categories','lawByCountry','byCountry','docsByCC','COUNTRY_CATS'] as $kw){ $n=substr_count($orig,$kw); if($n) echo "  '$kw': $n\n"; }

/* ---- SONUÇ ---- */
echo "\n=== SONUÇ ===\n";
echo "  • Bu ziyaretçi için tespit: ".($detect?:'YOK')."  (TR bekleniyordu)\n";
echo "  • CF-IPCOUNTRY: ".($cf?:'GELMİYOR (Cloudflare proxy kapalı olabilir → tespit ip-api\'ye kalıyor)')."\n";
echo "  • Tek-seferlik kilit: ".($fixed?'kaldırıldı ✅':'bulunamadı')."\n";
echo "  Şimdi: opcache-reset.php aç → GİZLİ pencerede Türkiye'den siteyi aç → Türkçe/Türkiye içeriği gelmeli.\n";
echo "  Hâlâ Alman hukuku çıkıyorsa: yukarıdaki 'tespit' TR değilse (Cloudflare) VEYA katalog TR içermiyorsa —\n";
echo "  çıktıyı yapıştır, tam düzeltmeyi vereyim. fix-geo.php'yi SİL.\n";
