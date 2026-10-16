<?php
/* ChatHelp ana sayfa — PHP sarmalayıcı: platform (Cloudflare kenar) ana sayfayı GÜNLERCE önbelleğe aldığı için
   index.html'i no-store başlıklarıyla servis eder (Recht uygulaması da aynı yöntemi kullanır). Yayın anında görünür. */
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache'); header('Expires: 0');
header('CDN-Cache-Control: no-store'); header('Cloudflare-CDN-Cache-Control: no-store'); header('Surrogate-Control: no-store');
header('Vary: Cookie');
$f = __DIR__.'/index.html';
if(!is_file($f)){ http_response_code(503); exit('index.html fehlt'); }
readfile($f);
