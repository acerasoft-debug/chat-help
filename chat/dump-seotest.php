<?php
/** ChatHelp — dump-seotest — SEO CANLI dogrulama: homepage render edilen <head>'te
 *  SEO etiketleri var mi + cok dilli calisiyor mu + robots.txt/sitemap.xml servis
 *  ediliyor mu. TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
function get($url,$al=null){
  $ch=curl_init($url);
  $h=['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36'];
  if($al) $h[]='Accept-Language: '.$al;
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$h,CURLOPT_TIMEOUT=>20,
    CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>false,CURLOPT_FOLLOWLOCATION=>true]);
  $b=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); $e=curl_error($ch); curl_close($ch);
  return ['code'=>$code,'body'=>(string)$b,'err'=>$e];
}
function head($html){ $p=stripos($html,'</head>'); return $p!==false?substr($html,0,$p):$html; }
function has($h,$n){ return strpos($h,$n)!==false?"✓":"✗"; }
function grab($h,$re){ return preg_match($re,$h,$m)?trim($m[1]):'(YOK)'; }

$base='https://chat-help.com';
echo "════ [1] Homepage <head> — SEO etiketleri ════\n";
$r=get($base.'/chat/');
echo "  HTTP ".$r['code']." ".($r['err']?"curl-err: ".$r['err']:"")."\n";
$hd=head($r['body']);
echo "  <title>: ".grab($hd,'~<title[^>]*>(.*?)</title>~is')."\n";
echo "  description: ".has($hd,'name="description"')."  keywords: ".has($hd,'name="keywords"')."  robots: ".has($hd,'name="robots"')."\n";
echo "  canonical: ".has($hd,'rel="canonical"')."  og:title: ".has($hd,'og:title')."  twitter:card: ".has($hd,'twitter:card')."\n";
echo "  JSON-LD: ".has($hd,'application/ld+json')."  CH_SEO marker: ".has($hd,'CH_SEO')."\n";
echo "  desc icerik: ".grab($hd,'~name="description"\s+content="([^"]{0,90})~i')."…\n";

echo "\n════ [2] Cok dilli test (Accept-Language: tr) ════\n";
$rt=get($base.'/chat/','tr-TR,tr;q=0.9');
$ht=head($rt['body']);
echo "  HTTP ".$rt['code']."  <title>: ".grab($ht,'~<title[^>]*>(.*?)</title>~is')."\n";

echo "\n════ [3] robots.txt / sitemap.xml servis ediliyor mu ════\n";
$rr=get($base.'/robots.txt');
echo "  robots.txt: HTTP ".$rr['code']."  ilk satir: ".trim(strtok($rr['body'],"\n"))."\n";
echo "  sitemap ref: ".(strpos($rr['body'],'sitemap.xml')!==false?"✓ var":"✗ yok")."\n";
$sm=get($base.'/sitemap.xml');
echo "  sitemap.xml: HTTP ".$sm['code']."  urlset: ".(strpos($sm['body'],'<urlset')!==false?"✓":"✗")."  <loc> sayisi: ".substr_count($sm['body'],'<loc>')."\n";

echo "\n════ [4] JSON-LD gecerlilik ════\n";
if(preg_match('~<script type="application/ld\+json">(.*?)</script>~s',$hd,$m)){
  $j=json_decode($m[1],true);
  echo "  JSON-LD parse: ".($j!==null?"✓ gecerli":"✗ HATALI")."\n";
  if($j&&isset($j['@graph'])){ $types=array_map(function($n){return $n['@type']??'?';},$j['@graph']); echo "  @graph tipleri: ".implode(', ',$types)."\n"; }
} else echo "  (JSON-LD bulunamadi)\n";

echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
