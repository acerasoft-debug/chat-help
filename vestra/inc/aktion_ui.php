<?php
/* AKTION gorunumu (14 Eki 2026): geri sayim + ana sayfa bandi + urun sayfasi kutusu.
   Fiyat mantigi inc/products.php'de (vestra_aktion*); burasi yalniz cizim. Tek JS,
   sayfada bir kez basilir; her .akcd[data-until] kendi saatini sayar, biten sayac
   "Offer ended" yazar ve kart kendini soldurur (sunucu zaten eski fiyata donmustur,
   yeniden yuklemede teklif hic gorunmez). */
require_once __DIR__.'/money.php';   // vestra_money: ana sayfa head.php'yi yuklemiyor
/** KURAL 19: toptan fiyat (EUR) yalniz giris yapmis + onayli aliciya; admin de gorur.
    Misafir yalniz -%X, lot/adet ve geri sayimi gorur ("Nur fur Mitglieder"). */
function vestra_aktion_can_price(): bool {
    if (!empty($_SESSION['vadmin'])) return true;
    if (!function_exists('auth_prices_unlocked') || !function_exists('auth_user')) return false;
    return auth_prices_unlocked(auth_user());
}
function vestra_aktion_countdown(int $until, string $cls = ''): string {
    $L = ['d' => t('days'), 'h' => t('hours'), 'm' => t('min'), 's' => t('sec')];
    $h = '<span class="akcd '.htmlspecialchars($cls).'" data-until="'.($until * 1000).'" data-ended="'.htmlspecialchars(t('Offer ended')).'" role="timer" aria-live="off">';
    foreach ($L as $k => $lbl) $h .= '<span class="akcd-u"><b data-u="'.$k.'">--</b><i>'.htmlspecialchars($lbl).'</i></span>';
    return $h.'</span>';
}
function vestra_aktion_css(): string {
    static $done = false; if ($done) return ''; $done = true;
    return '<style>
.akcd{display:inline-flex;gap:10px;align-items:flex-start;font-variant-numeric:tabular-nums}
.akcd-u{display:flex;flex-direction:column;align-items:center;min-width:44px}
.akcd-u b{font-family:"Playfair Display",Georgia,serif;font-weight:600;font-size:26px;line-height:1;color:var(--ink)}
.akcd-u i{font-style:normal;font-size:9.5px;letter-spacing:.16em;text-transform:uppercase;color:var(--mut);margin-top:5px}
.akcd.sm .akcd-u{min-width:30px}.akcd.sm .akcd-u b{font-size:16px}.akcd.sm .akcd-u i{font-size:8px;letter-spacing:.1em;margin-top:3px}
.akcd.ended .akcd-u{display:none}.akcd.ended::after{content:attr(data-ended);font-size:12px;color:var(--mut)}
/* ana sayfa bandi */
.aktion{padding:40px 0 8px}
.aktion .wrap{max-width:1180px}
.ak-head{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:18px}
.ak-head h2{margin:0;font-size:clamp(24px,3vw,32px)}
.ak-head .eyebrow{display:inline-flex;align-items:center;gap:8px;font-size:11px;font-weight:700;letter-spacing:.22em;text-transform:uppercase;color:#b4451f;margin-bottom:8px}
.ak-head .eyebrow::before{content:"";width:7px;height:7px;border-radius:50%;background:#b4451f;animation:akpulse 1.6s ease-in-out infinite}
@keyframes akpulse{0%,100%{opacity:.35}50%{opacity:1}}
@media(prefers-reduced-motion:reduce){.ak-head .eyebrow::before{animation:none}}
.ak-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
.ak-card{display:grid;grid-template-columns:150px 1fr;gap:16px;background:#fff;border:1px solid #e6e0d4;border-radius:16px;padding:14px;color:inherit;text-decoration:none;
  box-shadow:0 14px 34px -24px rgba(0,0,0,.35);transition:transform .35s var(--ease),box-shadow .35s var(--ease),border-color .35s var(--ease)}
.ak-card:hover{transform:translateY(-3px);border-color:rgba(169,127,44,.5);box-shadow:0 22px 44px -24px rgba(60,50,30,.4)}
.ak-ph{aspect-ratio:3/4;border-radius:10px;background:#f6f4ef;overflow:hidden;display:flex;align-items:center;justify-content:center}
.ak-ph img{width:100%;height:100%;object-fit:contain;padding:6px}
.ak-tx{display:flex;flex-direction:column;gap:6px;min-width:0}
.ak-brand{font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:var(--acc);font-weight:700}
.ak-name{font-weight:600;font-size:15px;line-height:1.3;margin:0}
.ak-price{display:flex;align-items:baseline;gap:8px;flex-wrap:wrap;margin-top:2px}
.ak-price .now{font-family:"Playfair Display",Georgia,serif;font-size:28px;font-weight:600;color:var(--ink);line-height:1}
.ak-price .was{font-size:13px;color:var(--mut);text-decoration:line-through}
.ak-price .lock{font-size:13px;font-weight:600;color:var(--mut);letter-spacing:.04em}
.ak-price .pct{font-size:11px;font-weight:700;color:#fff;background:#b4451f;border-radius:999px;padding:3px 8px}
.ak-min{font-size:12px;color:var(--mut)}
.ak-cd{margin-top:auto;padding-top:8px;border-top:1px dashed #e6e0d4}
.ak-cd .akcd{display:flex;justify-content:space-between;gap:4px;width:100%}.ak-cd .akcd-u{min-width:0;flex:1}.ak-cd .akcd-u b{font-size:22px}
.ak-cd small{display:block;font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:var(--mut);margin-bottom:4px}
.ak-card.ended{opacity:.55}
@media(max-width:1000px){.ak-grid{grid-template-columns:1fr 1fr}}
@media(max-width:640px){.ak-grid{grid-template-columns:1fr}.ak-card{grid-template-columns:110px 1fr;padding:12px}.ak-price .now{font-size:24px}.aktion{padding:30px 0 4px}}
/* urun sayfasi kutusu */
.ak-box{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;border:1px solid #e9c9b8;background:linear-gradient(135deg,#fff7f2,#fffdf9);border-radius:14px;padding:14px 18px;margin:0 0 16px}
.ak-box .ak-l{display:flex;flex-direction:column;gap:3px}
.ak-box .ak-t{font-size:11px;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#b4451f}
.ak-box .ak-p{font-size:15px}
.ak-box .ak-p b{font-family:"Playfair Display",Georgia,serif;font-size:24px;font-weight:600}
.ak-box .ak-r{display:flex;flex-direction:column;align-items:flex-end;gap:3px}
.ak-box .ak-r small{font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:var(--mut)}
[dir="rtl"] .ak-box .ak-r{align-items:flex-start}
.tiers tr.ak-row td{background:#fff7f2;font-weight:700}
.tiers tr.ak-row td:first-child::before{content:"★ ";color:#b4451f}
/* kilit paneli (misafir urun sayfasi) */
.ak-lock{display:flex;flex-direction:column;align-items:center;gap:8px;margin:-6px 0 18px;padding:12px 14px;border-radius:12px;background:#fff7f2;border:1px solid #e9c9b8}
.ak-lock .ak-t{font-size:11px;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#b4451f}
.ak-lock .ak-t b{margin-left:6px;background:#b4451f;color:#fff;border-radius:999px;padding:2px 8px;letter-spacing:0}
.ak-lock .ak-min{font-size:12px;color:var(--mut)}
.ak-lock .akcd-u b{font-size:20px}.ak-lock .akcd-u{min-width:38px}
/* katalog karti rozeti */
.smodetag.aktion{background:#b4451f;color:#fff;display:inline-flex;align-items:center;gap:6px}
.smodetag.aktion .akcd{gap:3px}.smodetag.aktion .akcd-u{min-width:auto;flex-direction:row;gap:1px}.smodetag.aktion .akcd-u b{font:inherit;color:#fff;font-weight:700}.smodetag.aktion .akcd-u i{display:none}
.smodetag.aktion .akcd-u+.akcd-u::before{content:":";color:#fff;opacity:.8}
.smodetag.aktion .akcd.ended::after{color:#fff}
</style>';
}
function vestra_aktion_js(): string {
    static $done = false; if ($done) return ''; $done = true;
    return '<script>(function(){var els=document.querySelectorAll(".akcd[data-until]");if(!els.length)return;
function tick(){var now=Date.now();els.forEach(function(el){var s=Math.max(0,Math.floor((parseInt(el.dataset.until,10)-now)/1000));
var v={d:Math.floor(s/86400),h:Math.floor(s%86400/3600),m:Math.floor(s%3600/60),s:s%60};
el.querySelectorAll("b[data-u]").forEach(function(b){var k=b.dataset.u;b.textContent=k==="d"?String(v[k]):String(v[k]).padStart(2,"0");});
if(s<=0&&!el.classList.contains("ended")){el.classList.add("ended");var c=el.closest(".ak-card,.ak-box");if(c)c.classList.add("ended");}});}
tick();setInterval(tick,1000);})();</script>';
}
/** Ana sayfa bandi: aktif teklifler yoksa BOS dize (bolum hic basilmaz). */
function vestra_aktion_band(?int $now = null): string {
    $items = vestra_aktion_products($now); if (!$items) return '';
    $pr = vestra_aktion_can_price();
    $h = vestra_aktion_css().'<section class="aktion reveal" id="aktion"><div class="wrap"><div class="ak-head"><div>'
       . '<div class="eyebrow">'.htmlspecialchars(t('Limited offer')).'</div><h2>'.htmlspecialchars(t('Three days only')).'</h2></div>'
       . '<a class="more" href="/shop">'.htmlspecialchars(t('Browse the catalog')).' &rarr;</a></div><div class="ak-grid">';
    foreach (array_slice($items, 0, 6) as $p) {
        $a = vestra_aktion($p, $now); $im = !empty($p['images'][0]) ? (string)$p['images'][0] : (function_exists('vestra_primary_image') ? (string)vestra_primary_image($p) : '');
        $h .= '<a class="ak-card" href="/product?id='.urlencode((string)$p['id']).'">'
            . '<div class="ak-ph">'.($im ? '<img src="'.htmlspecialchars($im).'" alt="'.htmlspecialchars(vestra_product_title($p)).'" loading="lazy">' : vestra_brand_card((string)($p['brand'] ?? ''))).'</div>'
            . '<div class="ak-tx"><span class="ak-brand">'.htmlspecialchars((string)($p['brand'] ?? '')).'</span><p class="ak-name">'.htmlspecialchars(vestra_product_name($p)).'</p>'
            . '<div class="ak-price">'.($pr ? '<span class="now">'.vestra_money($a['price']).'</span>'.($a['was'] > 0 ? '<span class="was">'.vestra_money($a['was']).'</span>' : '') : '<span class="lock">'.htmlspecialchars(t('Members only')).'</span>').($a['was'] > 0 ? '<span class="pct">&minus;'.$a['pct'].'%</span>' : '').'</div>'
            . '<span class="ak-min">'.htmlspecialchars(sprintf(t('from %d pc'), $a['min'])).(!empty($p['size_step']) ? ' &middot; '.htmlspecialchars(sprintf(t('lots of %d'), (int)$p['size_step'])) : '').'</span>'
            . '<div class="ak-cd"><small>'.htmlspecialchars(t('Ends in')).'</small>'.vestra_aktion_countdown($a['until']).'</div></div></a>';
    }
    return $h.'</div></div></section>'.vestra_aktion_js();
}
/** Urun sayfasi kutusu (fiyat tablosunun ustune). */
function vestra_aktion_box(array $p, ?int $now = null): string {
    $a = vestra_aktion($p, $now); if (!$a) return '';
    $pr = vestra_aktion_can_price(); $pct = $a['was'] > 0 ? ' <b style="font-size:13px;color:#b4451f">&minus;'.$a['pct'].'%</b>' : '';
    $line = $pr ? '<b>'.vestra_money($a['price']).'</b> / '.htmlspecialchars((string)($p['unit'] ?? 'pc')).' '.htmlspecialchars(sprintf(t('from %d pc'), $a['min'])).($a['was'] > 0 ? ' &middot; <s>'.vestra_money($a['was']).'</s>' : '').$pct
                : '<b>'.htmlspecialchars(t('Members only')).'</b>'.$pct.' &middot; '.htmlspecialchars(sprintf(t('from %d pc'), $a['min'])).(!empty($p['size_step']) ? ' &middot; '.htmlspecialchars(sprintf(t('lots of %d'), (int)$p['size_step'])) : '');
    return vestra_aktion_css().'<div class="ak-box"><div class="ak-l"><span class="ak-t">'.htmlspecialchars(t('Limited offer')).'</span>'
         . '<span class="ak-p">'.$line.'</span>'
         . '<span style="font-size:12px;color:var(--mut)">'.htmlspecialchars(sprintf(t('Offer ends %s'), date('j M Y, H:i', $a['until']).' UTC')).'</span></div>'
         . '<div class="ak-r"><small>'.htmlspecialchars(t('Ends in')).'</small>'.vestra_aktion_countdown($a['until']).'</div></div>'.vestra_aktion_js();
}
/** Katalog karti rozeti: -%X + kucuk sayac. */
function vestra_aktion_badge(array $p, ?int $now = null): string {
    $a = vestra_aktion($p, $now); if (!$a) return '';
    return '<span class="smodetag sale aktion">&minus;'.$a['pct'].'% '.vestra_aktion_countdown($a['until'], 'sm').'</span>';
}
/** Misafir urun sayfasi: kilit panelinin ustune kucuk teklif duyurusu (EUR yok). */
function vestra_aktion_lock_teaser(array $p, ?int $now = null): string {
    $a = vestra_aktion($p, $now); if (!$a) return '';
    return vestra_aktion_css().'<div class="ak-lock"><span class="ak-t">'.htmlspecialchars(t('Limited offer')).($a['was'] > 0 ? '<b>&minus;'.$a['pct'].'%</b>' : '').'</span>'
         . '<span class="ak-min">'.htmlspecialchars(sprintf(t('from %d pc'), $a['min'])).(!empty($p['size_step']) ? ' &middot; '.htmlspecialchars(sprintf(t('lots of %d'), (int)$p['size_step'])) : '').' &middot; '.htmlspecialchars(t('Members only')).'</span>'
         . vestra_aktion_countdown($a['until']).'</div>'.vestra_aktion_js();
}
