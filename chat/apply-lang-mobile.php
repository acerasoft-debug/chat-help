<?php
/**
 * ChatHelp — Dil seçici: mobil konum + keşfedilebilirlik (additive)
 *  • Mobilde bayrağı header'ın altına al (site butonlarıyla çakışmasın)
 *  • Dropdown oku (▾) ekle -> "değiştirilebilir" olduğu belli olsun
 *  • En üst z-index (her şeyin üstünde görünür)
 * KULLANIM: chat-help.com/chat/apply-lang-mobile.php -> opcache-reset.php. SİL.
 */
header('Content-Type: text/plain; charset=UTF-8');
$file = __DIR__ . '/index.php';
if (!file_exists($file)) { exit("HATA: index.php yok.\n"); }
$src=file_get_contents($file); $start=$src;
file_put_contents($file . '.bak-langmob-' . date('Ymd-His'), $src);
if (strpos($src, 'ch-lang-mobile') !== false) { exit("Zaten ekli.\n"); }

$bundle = <<<'HTML'
<style id="ch-lang-mobile">
#ch-lang-btn{position:fixed!important;z-index:100001!important}
/* "değiştirilebilir" ipucu: küçük ok */
#ch-lang-btn::after{content:'▾';position:absolute;bottom:2px;right:6px;font-size:10px;line-height:1;color:var(--gold,#d4a84a);text-shadow:0 1px 2px rgba(0,0,0,.6)}
#ch-lang-menu{z-index:100002!important}
/* Masaüstü: sağ üst (header'la çakışmaz) */
@media(min-width:761px){ #ch-lang-btn{top:12px!important;right:12px!important} #ch-lang-menu{top:60px!important;right:12px!important} }
/* Mobil: header'ın ALTINA al, çakışmayı önle */
@media(max-width:760px){
  #ch-lang-btn{top:62px!important;right:9px!important;width:40px!important;height:40px!important;box-shadow:0 6px 18px rgba(0,0,0,.5)!important}
  #ch-lang-menu{top:108px!important;right:9px!important;min-width:160px!important}
  #ch-lang-ask{font-size:12.5px!important;padding:10px 12px!important}
}
/* tek seferlik bilgi kartı */
#ch-li-card{position:fixed;inset:0;z-index:100050;background:rgba(0,0,0,.7);display:flex;align-items:center;justify-content:center;padding:18px;animation:chLiIn .25s ease}
@keyframes chLiIn{from{opacity:0}to{opacity:1}}
#ch-li-card .ch-li-box{max-width:380px;width:100%;background:linear-gradient(180deg,var(--s1,#1c1c22),var(--bg,#15151b));border:1px solid rgba(212,168,74,.3);border-radius:20px;padding:24px 22px;text-align:center;box-shadow:0 22px 50px rgba(0,0,0,.6)}
#ch-li-card .ch-li-ic{font-size:30px;margin-bottom:12px}
#ch-li-card .ch-li-tx{font-size:14px;line-height:1.55;color:#e9e9f2;margin-bottom:18px}
#ch-li-card .ch-li-ok{padding:12px 26px;border:none;border-radius:12px;background:linear-gradient(135deg,#d4a84a,#b8862f);color:#1b1b1e;font-size:14px;font-weight:800;cursor:pointer;font-family:inherit}
#ch-li-card .ch-li-ok:hover{filter:brightness(1.07)}
</style>
<script id="ch-lang-mobile-js">
(function(){
  // i18n yüklenmediyse (önbellek) uyarı (konsol)
  setTimeout(function(){ if(!document.getElementById('ch-lang-btn')) console.warn('[ChatHelp] Dil seçici yok — tarayıcı önbelleği? Hard-refresh gerekli.'); }, 2500);

  // ── Tek seferlik bilgi kartı (Almanca dışı dilde, kullanıcının dilinde) ──
  var MSG={
    tr:'Her şeyi <b>kendi dilinizde</b> yazabilirsiniz. Resmî belge <b>Almanca</b> oluşturulur — ana dilinizde açıklamayla.',
    ru:'Вы можете писать <b>на своём языке</b>. Официальный документ создаётся <b>на немецком</b> — с пояснением на вашем языке.',
    ar:'يمكنك الكتابة <b>بلغتك</b>. يتم إنشاء المستند الرسمي <b>بالألمانية</b> — مع شرح بلغتك.',
    en:'You can write <b>in your own language</b>. The official document is created <b>in German</b> — with an explanation in your language.',
    fr:'Vous pouvez écrire <b>dans votre langue</b>. Le document officiel est créé <b>en allemand</b> — avec une explication dans votre langue.',
    es:'Puede escribir <b>en su idioma</b>. El documento oficial se crea <b>en alemán</b> — con una explicación en su idioma.',
    it:'Puoi scrivere <b>nella tua lingua</b>. Il documento ufficiale è <b>in tedesco</b> — con una spiegazione nella tua lingua.'
  };
  function lang(){ try{ var s=localStorage.getItem('ch_uilang'); if(s) return s; }catch(e){} return 'de'; }
  function maybeCard(){
    var l=lang();
    if(l==='de' || !MSG[l]) return;
    try{ if(localStorage.getItem('ch_li_seen')) return; }catch(e){}
    if(document.getElementById('ch-li-card')) return;
    var rtl=(l==='ar');
    var c=document.createElement('div'); c.id='ch-li-card';
    c.innerHTML='<div class="ch-li-box"'+(rtl?' dir="rtl"':'')+'><div class="ch-li-ic">🌍 → 🇩🇪</div>'
      +'<div class="ch-li-tx"'+(rtl?' style="text-align:right"':'')+'>'+MSG[l]+'</div>'
      +'<button class="ch-li-ok">OK ✓</button></div>';
    document.body.appendChild(c);
    function done(){ try{ localStorage.setItem('ch_li_seen','1'); }catch(e){} c.remove(); }
    c.querySelector('.ch-li-ok').onclick=done;
    c.addEventListener('click',function(e){ if(e.target===c) done(); });
  }
  function boot(){ maybeCard(); setInterval(maybeCard,1200); }
  if(document.body) boot(); else document.addEventListener('DOMContentLoaded',boot);
})();
</script>
HTML;

$n=0;
$src=preg_replace('/<\/body>/', $bundle . "\n</body>", $src, 1, $n);
$changed=($src!==$start);
if($changed) file_put_contents($file,$src);
echo "ChatHelp — Dil seçici mobil raporu\n==================================\n\n";
echo ($n>0?"  ✓ Mobil konum/keşif düzeltmesi eklendi  →  $n\n":"  ✗ </body> yok!\n");
echo "\n".($changed?"DURUM: güncellendi. ✅\n":"Değişiklik yok.\n");
echo "SONRA: opcache-reset.php + TELEFONDA önbellek temizle / gizli sekme. SİL: rm apply-lang-mobile.php\n";
