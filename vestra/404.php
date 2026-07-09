<?php
/** VESTRA — branded 404 (wired via ErrorDocument in .htaccess). */
require_once __DIR__.'/inc/products.php';
http_response_code(404);
$PAGE = t('Page not found'); $NAV = '';
require __DIR__.'/inc/head.php';
?>
<div class="wrap" style="text-align:center;padding:90px 20px 110px">
  <div style="font-family:'Playfair Display',serif;font-size:84px;font-weight:700;color:var(--acc);line-height:1">404</div>
  <h1 style="margin:14px 0 10px"><?= t('Page not found') ?></h1>
  <p style="color:var(--mut);max-width:460px;margin:0 auto 26px"><?= t("The page you're looking for doesn't exist or has moved. Browse the catalog or head back home.") ?></p>
  <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
    <a class="btn btn-p" href="/shop"><?= t('Browse catalog') ?></a>
    <a class="btn btn-o" href="/"><?= t('Home') ?></a>
  </div>
</div>
<?php require __DIR__.'/inc/foot.php';
