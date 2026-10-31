<?php
/**
 * ChatHelp ops — root-check: Wie wird die Startseite ausgeliefert? (nur lesen)
 * .htaccess-Regeln (Kök/chat/DirectoryIndex), Dateien im Docroot (index.html, home.php, robots.txt, sitemap.xml, favicon.ico),
 * Antwort des Ursprungs (ohne Cloudflare, Loopback mit Host-Header) und über Cloudflare für / , /robots.txt, /sitemap.xml.
 * Gibt keine Schlüssel oder Personendaten aus.
 */
header('Content-Type: text/plain; charset=UTF-8');
@set_time_limit(120);
if(PHP_SAPI!=='cli'){ http_response_code(404); exit; }
$ROOT=dirname(__DIR__);
echo "ChatHelp — root-check (".gmdate('c').")  docroot=".basename($ROOT)."\n\n";
echo "=== Dateien im Docroot ===\n";
foreach(['index.html','home.php','.htaccess','robots.txt','sitemap.xml','favicon.ico','site.webmanifest','manifest.webmanifest','index.php','wp-config.php'] as $f){
    $p=$ROOT.'/'.$f; echo sprintf("  %-22s %s\n",$f,is_file($p) ? filesize($p).' B · '.gmdate('Y-m-d H:i',filemtime($p)).(in_array($f,['robots.txt','sitemap.xml']) ? ' · '.json_encode(mb_substr((string)file_get_contents($p),0,160)) : '') : '—');
}
echo "\n=== .htaccess (Kök-/chat-/Index-Regeln) ===\n";
$ht=(string)@file_get_contents($ROOT.'/.htaccess');
foreach(preg_split('/\R/',$ht) as $i=>$l) if(preg_match('/RewriteEngine|RewriteRule \^\$|home\.php|index\.html|DirectoryIndex|chat\/|ErrorDocument|Header /',$l)) echo sprintf("  %3d: %s\n",$i+1,mb_substr(trim($l),0,140));
if(is_file($ROOT.'/.htaccess.log')){ echo "  --- .htaccess.log (letzte 8) ---\n"; foreach(array_slice(file($ROOT.'/.htaccess.log'),-8) as $l) echo "  ".mb_substr(trim($l),0,160)."\n"; }
echo "\n=== Antworten (Ursprung = Loopback mit Host-Header · Edge = über Cloudflare) ===\n";
function hd(string $url, array $hdr=[]): array {
    $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_NOBODY=>false,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>0,
        CURLOPT_HTTPHEADER=>array_merge(['User-Agent: Mozilla/5.0 (ChatHelp root-check)','Accept: text/html,*/*'],$hdr)]);
    $r=(string)curl_exec($ch); $i=curl_getinfo($ch); $e=curl_error($ch); curl_close($ch); $hs=(int)($i['header_size'] ?? 0); $h=[];
    foreach(explode("\r\n",substr($r,0,$hs)) as $l) if(str_contains($l,':')){ [$k,$v]=explode(':',$l,2); $h[strtolower(trim($k))]=trim($v); }
    return ['code'=>(int)$i['http_code'],'err'=>$e,'h'=>$h,'len'=>strlen($r)-$hs,'body'=>substr($r,$hs,200)];
}
$show=fn(string $label,array $r)=>printf("  %-28s %s%s  len=%d  cc=%s  cf=%s  ct=%s  x-powered=%s%s\n",$label,$r['code'],$r['err'] ? ' ERR '.$r['err'] : '',$r['len'],$r['h']['cache-control'] ?? '-',$r['h']['cf-cache-status'] ?? '-',mb_substr($r['h']['content-type'] ?? '-',0,24),$r['h']['x-powered-by'] ?? '-',isset($r['h']['location']) ? ' → '.$r['h']['location'] : '');
foreach(['/','/?lang=tr','/index.html','/home.php','/robots.txt','/sitemap.xml'] as $p){
    $show('origin https '.$p,hd('https://127.0.0.1'.$p,['Host: chat-help.com']));
    $show('origin http  '.$p,hd('http://127.0.0.1'.$p,['Host: chat-help.com']));
    $show('edge         '.$p,hd('https://chat-help.com'.$p));
}
echo "\n=== Server-Software ===\n  ".php_uname('s').' · PHP '.PHP_VERSION.' · SAPI-Umgebung: '.($_SERVER['SERVER_SOFTWARE'] ?? '-')."\n";
echo "\nBİTTİ (nur gelesen)\n";
