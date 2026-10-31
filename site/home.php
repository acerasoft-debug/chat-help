<?php
/**
 * ChatHelp — Ana sayfa sarmalayıcı: site/index.html'i dinamik (önbelleksiz) sunar ve güvenlik başlıklarını ekler.
 * Host Europe / GoDaddy Managed WordPress: .htaccess içindeki "Header" yönergeleri desteklenmez → başlıklar yalnız PHP ile.
 * Cache-Control tek başına yetmez; Set-Cookie içeren yanıtlar platform- ve kenar önbelleğine (Cloudflare) alınmaz → her yanıtta çerez yenilenir.
 * Content-Security-Policy: satır içi <script> blokları SHA-256 özetiyle (nonce yerine → önbelleğe alınsa da geçerli); satır içi olay
 * işleyicisi (onclick) yok – ana sayfa data-act/data-demo + merkezi dinleyici kullanır. Özetler dosya değişim zamanına göre önbelleklenir.
 */
$f=__DIR__.'/index.html'; $mt=(int)@filemtime($f);
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0');
header('Cloudflare-CDN-Cache-Control: no-store'); header('CDN-Cache-Control: no-store');
header('Pragma: no-cache'); header('Expires: 0'); header('Vary: Cookie');
header('Set-Cookie: chnc=1; Path=/; Max-Age=3600; Secure; HttpOnly; SameSite=Lax', false);
/* Güvenlik başlıkları (chat/pagever.php ch_sec_headers ile aynı değerler; home.php docroot'ta tek başına çalışır) */
header('Strict-Transport-Security: max-age=31536000');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(self), geolocation=(self), payment=(), usb=(), browsing-topics=()');
header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
$cacheF=sys_get_temp_dir().'/ch_home_csp_'.$mt.'_'.(int)@filemtime(__FILE__).'.txt'; $csp=$mt ? (string)@file_get_contents($cacheF) : '';
if($csp===''){
    $html=(string)@file_get_contents($f); $hashes=[];
    if(preg_match_all('/<script(?![^>]*\bsrc=)[^>]*>(.*?)<\/script>/is',$html,$m)) foreach($m[1] as $js) $hashes["'sha256-".base64_encode(hash('sha256',$js,true))."'"]=1;
    $csp="default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self' https://checkout.stripe.com; "
        ."script-src 'self' ".implode(' ',array_keys($hashes))."; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; "
        ."media-src 'self' blob: data:; connect-src 'self'; frame-src 'self'; worker-src 'self' blob:; manifest-src 'self'";
    if($mt) @file_put_contents($cacheF,$csp);
}
header('Content-Security-Policy: '.$csp);
readfile($f);
