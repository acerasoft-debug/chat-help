<?php
/**
 * VESTRA — mobile bottom tab bar.
 *
 * The site is already installable (manifest + service worker + install prompt), but
 * once installed it still navigated like a website: everything lived behind a burger
 * menu. A fixed bottom bar is the single strongest "this is an app" signal on a
 * phone, so the four or five places a buyer actually goes are always one thumb-reach
 * away.
 *
 * Self-contained on purpose — it carries its own CSS because index.php does not load
 * inc/style.css, so anything that depends on the shared sheet would silently render
 * unstyled on the homepage. Include it just before the footer on any page.
 *
 * Hidden above 820px: on a laptop the top nav already does this job, and two
 * navigations competing for the same links is worse than one.
 */
if (!defined('VESTRA_TABBAR_RENDERED')) {
    define('VESTRA_TABBAR_RENDERED', 1);

    /* Defensive: index.php starts a session but never loads auth.php, so neither the
       helper nor $AUTH_USER is guaranteed to exist here. */
    $__tbUser = function_exists('auth_user') ? auth_user() : null;
    $__tbPanel = $__tbUser
        ? ((($__tbUser['type'] ?? '') === 'seller') ? '/seller' : '/buyer')
        : '/login';

    $__tbPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $__tbPath = rtrim(preg_replace('/\.php$/', '', $__tbPath), '/');
    if ($__tbPath === '') $__tbPath = '/';

    /* [href, label, active-match, svg path markup] */
    $__tbItems = [
        ['/',         t('Home'),     ['/'],                  '<path d="M4 11.5 12 4l8 7.5"/><path d="M6 10.5V20h12v-9.5"/>'],
        ['/shop',     t('Catalog'),  ['/shop','/product'],   '<path d="M5 7h14l-1 12H6L5 7z"/><path d="M9 7a3 3 0 0 1 6 0"/>'],
        ['/requests', t('Requests'), ['/requests'],          '<path d="M5 4h11l3 3v13H5z"/><path d="M8.5 11h7M8.5 15h4.5"/>'],
        ['/cart',     t('Cart'),     ['/cart','/checkout'],  '<circle cx="9.5" cy="19" r="1.4"/><circle cx="17" cy="19" r="1.4"/><path d="M3 4h2l2.2 10.5h10.3L20 7.5H6"/>'],
        [$__tbPanel,  $__tbUser ? t('Account') : t('Sign in'), ['/seller','/buyer','/login','/register'],
                                                             '<circle cx="12" cy="8.5" r="3.5"/><path d="M5 20c1.2-3.6 4-5.2 7-5.2s5.8 1.6 7 5.2"/>'],
    ];
    ?>
<style>
/* App chrome. Only the bar itself is mobile-only; the input/tap tuning below is
   harmless on desktop and stops a phone browser from zooming a form field or
   flashing a grey box on every tap. */
html{-webkit-text-size-adjust:100%;text-size-adjust:100%}
body{-webkit-tap-highlight-color:transparent;overscroll-behavior-y:contain}
@media(max-width:820px){
  /* Keep the last section clear of the bar, including the home-indicator strip. */
  body{padding-bottom:calc(66px + env(safe-area-inset-bottom,0px))}
  .vtabbar{position:fixed;left:0;right:0;bottom:0;z-index:60;display:grid;
    grid-template-columns:repeat(5,1fr);align-items:stretch;
    padding-bottom:env(safe-area-inset-bottom,0px);
    background:rgba(14,14,17,.86);
    -webkit-backdrop-filter:blur(18px) saturate(1.3);backdrop-filter:blur(18px) saturate(1.3);
    border-top:1px solid rgba(255,255,255,.09);
    box-shadow:0 -8px 28px -14px rgba(0,0,0,.75)}
  .vtab{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;
    min-height:56px;padding:9px 4px 8px;text-decoration:none;color:rgba(244,241,234,.56);
    position:relative;background:none;border:0;font:inherit;
    transition:color .3s cubic-bezier(.16,.66,.25,1)}
  .vtab svg{width:22px;height:22px;stroke:currentColor;fill:none;
    stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round;
    transition:transform .35s cubic-bezier(.16,.66,.25,1)}
  .vtab span{font-size:9.5px;letter-spacing:.055em;font-weight:600;
    max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .vtab.on{color:#c9a86a}
  .vtab.on svg{transform:translateY(-1px)}
  /* Active marker sits on the top edge, matching the gold hairline used on the
     brand rail and the catalogue cards rather than inventing a third idiom. */
  .vtab.on::before{content:'';position:absolute;top:-1px;left:24%;right:24%;height:2px;
    background:#c9a86a;border-radius:0 0 2px 2px}
  .vtab:active svg{transform:scale(.9)}
  .vtab-badge{position:absolute;top:5px;left:calc(50% + 6px);min-width:15px;height:15px;
    padding:0 4px;border-radius:999px;background:#c9a86a;color:#1a1408;
    font-size:9px;font-weight:800;line-height:15px;text-align:center;
    font-variant-numeric:tabular-nums}
}
@media(min-width:821px){.vtabbar{display:none}}
@media(prefers-reduced-motion:reduce){.vtab,.vtab svg{transition:none}}
/* Cerez bildirimi (#cnotice, foot.php ve index.php) satir ici bottom:14px tasiyor
   ve cubugun USTUNE biniyordu: kullanici bandi kapatana kadar Katalog/Sepet/Hesap
   dokunulamiyordu (2 Eyl 2026 olcumu: bildirim 699-830px, cubuk 780-844px).
   !important sart -- satir ici stil baska turlu ezilmiyor. Burada duruyor cunku
   cubugu her sayfa bu dosyadan aliyor, index.php'nin kendi altbilgisi dahil. */
@media(max-width:820px){#cnotice{bottom:calc(74px + env(safe-area-inset-bottom,0px)) !important}}
</style>
<nav class="vtabbar" aria-label="<?= htmlspecialchars(t('Main')) ?>">
  <?php foreach ($__tbItems as [$href, $label, $match, $icon]):
      $on = in_array($__tbPath, $match, true);
  ?>
  <a class="vtab<?= $on ? ' on' : '' ?>" href="<?= htmlspecialchars($href) ?>"<?= $on ? ' aria-current="page"' : '' ?>>
    <svg viewBox="0 0 24 24" aria-hidden="true"><?= $icon ?></svg>
    <span><?= htmlspecialchars($label) ?></span>
    <?php if ($href === '/cart'): ?><span class="vtab-badge" id="vtabCart" style="display:none">0</span><?php endif; ?>
  </a>
  <?php endforeach; ?>
</nav>
<script>
/* Mirror whatever already maintains the header cart badge, rather than re-deriving
   the count here and risking the two disagreeing. */
(function(){
  var src=document.getElementById('cartCount'), dst=document.getElementById('vtabCart');
  if(!dst) return;
  function sync(){
    if(!src){ dst.style.display='none'; return; }
    var n=parseInt(src.textContent,10)||0;
    dst.textContent=n;
    dst.style.display=(n>0 && src.style.display!=='none')?'':'none';
  }
  sync();
  if(src && window.MutationObserver){
    new MutationObserver(sync).observe(src,{childList:true,characterData:true,subtree:true,attributes:true,attributeFilter:['style']});
  }
})();
</script>
<?php } ?>
