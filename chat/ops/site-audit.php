<?php
/**
 * ChatHelp ops — site-audit: Außensicht der Website vom Server aus (über Cloudflare): Statuscodes, Sicherheits-Header, Komprimierung, Größe, TTFB,
 * SEO-Grundlagen (Title, Description, Canonical, hreflang, Open Graph, JSON-LD, robots.txt, sitemap.xml, Favicon, Manifest), je Sprache.
 * Liest nur; gibt keine Schlüssel aus.
 */
header('Content-Type: text/plain; charset=UTF-8');
@set_time_limit(180);
$BASE='https://chat-help.com';
echo "ChatHelp — site-audit  (".gmdate('c').")\n\n";
function get(string $url, bool $head=false): array {
    $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_NOBODY=>$head,CURLOPT_TIMEOUT=>30,CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_ENCODING=>'',CURLOPT_HTTPHEADER=>['User-Agent: Mozilla/5.0 (ChatHelp site-audit)','Accept: text/html,*/*','Accept-Encoding: br, gzip']]);
    $r=(string)curl_exec($ch); $i=curl_getinfo($ch); curl_close($ch); $hs=(int)($i['header_size'] ?? 0);
    $hdr=[]; foreach(explode("\r\n",substr($r,0,$hs)) as $l) if(str_contains($l,':')){ [$k,$v]=explode(':',$l,2); $hdr[strtolower(trim($k))]=trim($v); }
    return ['code'=>(int)$i['http_code'],'ttfb'=>round(($i['starttransfer_time'] ?? 0)*1000),'total'=>round(($i['total_time'] ?? 0)*1000),'bytes'=>(int)($i['size_download'] ?? 0),'hdr'=>$hdr,'body'=>substr($r,$hs)];
}
echo "=== Seiten (Status · TTFB · Größe · Komprimierung · Cache) ===\n";
$pages=['/','/?lang=tr','/?lang=en','/chat/studio.php','/chat/app.php','/chat/app-download.php','/agb.html','/datenschutz.html','/impressum.html','/chat/book.php?k=demo','/robots.txt','/sitemap.xml','/favicon.ico','/site.webmanifest','/fonts/fonts.css','/fonts/inter-latin.woff2','/chat/widget.js','/chat/tokens-api.php/v1/pricing','/chat/site.php','/chat/tokens-checkout.php'];
$home=null;
foreach($pages as $p){ $r=get($BASE.$p); if($p==='/') $home=$r;
    printf("  %-34s %3d  %5d ms  %7s  enc=%-6s cache=%s%s\n",$p,$r['code'],$r['ttfb'],number_format($r['bytes']/1024,0).' KB',$r['hdr']['content-encoding'] ?? '-',$r['hdr']['cf-cache-status'] ?? '-',isset($r['hdr']['location']) ? ' → '.$r['hdr']['location'] : ''); }
echo "\n=== Sicherheits-Header (Startseite) ===\n";
foreach(['strict-transport-security','content-security-policy','x-robots-tag','x-frame-options','x-content-type-options','referrer-policy','permissions-policy','cross-origin-opener-policy','server','cache-control'] as $h)
    printf("  %-32s %s\n",$h,isset($home['hdr'][$h]) ? mb_substr($home['hdr'][$h],0,140) : '— FEHLT');
echo "\n=== SEO-Grundlagen (Startseite) ===\n";
$b=(string)($home['body'] ?? ''); $m=fn(string $re)=>preg_match($re,$b,$x) ? trim($x[1]) : '';
echo "  <title>: ".mb_substr($m('/<title[^>]*>(.*?)<\/title>/s'),0,120)."\n";
echo "  description: ".mb_substr($m('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)/i'),0,160)."\n";
echo "  canonical: ".($m('/<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']*)/i') ?: '— FEHLT')."\n";
echo "  html lang: ".$m('/<html[^>]+lang=["\']([^"\']*)/i')."\n";
preg_match_all('/hreflang=["\']([^"\']+)/i',$b,$hl); echo "  hreflang: ".(count($hl[1]) ?: '— FEHLT')." (".implode(',',array_slice(array_unique($hl[1]),0,16)).")\n";
foreach(['og:title','og:description','og:image','og:url','twitter:card'] as $p) echo "  $p: ".(mb_substr($m('/<meta[^>]+(?:property|name)=["\']'.preg_quote($p,'/').'["\'][^>]+content=["\']([^"\']*)/i'),0,100) ?: '— FEHLT')."\n";
preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is',$b,$ld); echo "  JSON-LD Blöcke: ".count($ld[1]);
foreach($ld[1] as $j){ $d=json_decode($j,true); echo ' · '.(is_array($d) ? ($d['@type'] ?? (isset($d['@graph']) ? 'graph('.count($d['@graph']).')' : '?')) : 'UNGÜLTIG'); } echo "\n";
echo "  <h1>: ".count(preg_split('/<h1[\s>]/i',$b))-1 ."  <img>: ".substr_count($b,'<img')."  ohne alt: ".preg_match_all('/<img(?![^>]*\balt=)[^>]*>/i',$b)."  <video>: ".substr_count($b,'<video')."  inline <script>: ".preg_match_all('/<script(?![^>]*src=)[^>]*>/i',$b)."  externe Scripts: ".preg_match_all('/<script[^>]+src=/i',$b)."\n";
echo "  <main>: ".substr_count($b,'<main')."  Skip-Link: ".(str_contains($b,'class="skip"') ? 'ja' : '— FEHLT')."  Google Fonts: ".substr_count($b,'fonts.googleapis')." (0 erwartet)  eigene Schriften: ".substr_count($b,'/fonts/')."\n";
echo "  viewport: ".($m('/<meta[^>]+name=["\']viewport["\'][^>]+content=["\']([^"\']*)/i') ?: '— FEHLT')."  theme-color: ".($m('/<meta[^>]+name=["\']theme-color["\'][^>]+content=["\']([^"\']*)/i') ?: '—')."\n";
preg_match_all('/<a[^>]+href=["\'](https?:\/\/[^"\']+)/i',$b,$ext); $hosts=array_count_values(array_map(fn($u)=>parse_url($u,PHP_URL_HOST),$ext[1])); arsort($hosts); echo "  externe Links: ".json_encode(array_slice($hosts,0,8))."\n";
echo "  Anbieternamen im Quelltext: "; foreach(['Twilio','ElevenLabs','Deepgram','Anthropic','Claude','DeepSeek','OpenAI','Azure'] as $v) echo $v.'='.substr_count($b,$v).' '; echo "\n";
echo "\n=== Interne Links der Startseite (Stichprobe, 404?) ===\n";
preg_match_all('/href=["\'](\/[^"\'#?]+)/i',$b,$il); $seen=[]; $bad=0;
foreach(array_slice(array_unique($il[1]),0,40) as $p){ $r=get($BASE.$p,true); if($r['code']>=400){ $bad++; echo "  ✗ $p → {$r['code']}\n"; } }
echo "  geprüft: ".min(40,count(array_unique($il[1])))." · defekt: $bad\n";
echo "\nBİTTİ (nur gelesen)\n";
