<?php
/**
 * Admin ▸ Müşteriler sekmesinin GÖRÜNÜM düzeni (9 Eki 2026, operatör: "user ve email gönderim sayfasını gözden
 * geçir, hataları düzelt, sade yap … sistem kalsın").
 *
 * admin.php bu sekmenin bölümlerini hesaplama sırası HİÇ değişmeden çıktı tamponuna yakalar ($__pb[...]); burada
 * yalnızca hangi sırayla görüneceği kurulur:  özet → 📮 kampanya gönder → 🌐 müşteri bul → 👥 müşteri listesi →
 * ⚙️ diğer araçlar (kapalı). Hiçbir form, eylem ya da script kaldırılmadı — eskiden sayfada olan her şey
 * "Diğer araçlar"ın içinde. Bu sekmedeki scriptler yalnız fonksiyon tanımı ya da DOMContentLoaded olduğu için
 * sıralama değişikliği onları bozmaz.
 *
 * Bu dosya admin.php'nin kapsamında require edilir (değişkenler oradan gelir).
 */
if (!isset($__pb) || !is_array($__pb)) return;
$__ld      = is_array($leads ?? null) ? $leads : [];
$__bounced = count(array_filter($__ld, fn($l) => ($l['status'] ?? '') === 'bounced'));
$__ready   = isset($fsTargetsWeb) ? count($fsTargetsWeb) : (isset($fsTargetsAll) ? count($fsTargetsAll) : 0);   // yeni bulunan, doğrulanmış
/* Bir eylemden sonra (Brevo / Claude / lemlist / SMTP / şablon / teklif) "Diğer araçlar" açık gelsin: sayfa o
   bölüme döner ve kapalı bir kutunun içinde kaybolmaz. */
$__openMore = !empty($fsFlash) || !empty($acFlash) || (($_GET['mailfor'] ?? '') !== '')
           || in_array((string)($_GET['msg'] ?? ''), ['lead_tpl_ok', 'quote_sent', 'quote_failed', 'quote_invalid', 'quote_unsub', 'email_saved', 'test_ok', 'test_fail', 'test_invalid',
              'ai_saved', 'finder_saved', 'google_saved', 'google_cleared'], true);
$__openAdd  = in_array((string)($_GET['msg'] ?? ''), ['lead_added', 'lead_dupe', 'lead_invalid', 'lead_import'], true);
?>
<style>
  .pxhead{display:flex;gap:10px;flex-wrap:wrap;margin:0 0 6px}
  .pxchip{flex:1;min-width:170px;border:1px solid var(--line);border-radius:11px;padding:10px 13px;background:var(--bg2)}
  .pxchip b{display:block;font-size:20px;line-height:1.2}
  .pxchip span{font-size:11.5px;color:var(--mut)}
  .pxchip.ok{border-color:rgba(31,157,99,.45)} .pxchip.ok b{color:#1f9d63}
  .pxchip.warn b{color:#a9781a}
  .pxlead{margin:0 0 16px;max-width:820px}
  details.pxmore{margin:0 0 20px}
  details.pxmore>summary{cursor:pointer;list-style:none;padding:13px 16px;font-weight:700;font-size:14px}
  details.pxmore>summary::-webkit-details-marker{display:none}
  details.pxmore>summary:before{content:'▸ ';color:var(--mut)} details.pxmore[open]>summary:before{content:'▾ '}
  details.pxmore>summary .ahint{font-weight:400}
  .pxsec{border-top:1px solid var(--line);padding-top:14px;margin-top:14px}
  .pxsec:first-child{border-top:0;margin-top:0;padding-top:0}
  .pxinner .acard{box-shadow:none}
</style>

<?php /* 10 Eki 2026 (operatör: "şu anda gönderiyor mu, arıyor mu bilmiyorum, görünmüyor"): canlı durum şeridi. */
  require_once __DIR__.'/mailbox.php'; $__live = vestra_live_status();
  $__lc = ['running' => ['#1f9d63', 'rgba(31,157,99,.08)', 'rgba(31,157,99,.45)'], 'queued' => ['#a9781a', 'rgba(169,127,44,.08)', 'rgba(169,127,44,.45)'], 'idle' => ['var(--mut)', 'var(--bg2)', 'var(--line)']]; ?>
<style>
  .pxlive{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:0 0 12px}
  @media(max-width:760px){.pxlive{grid-template-columns:1fr}}
  .pxlive .it{border:1.5px solid;border-radius:12px;padding:10px 14px;font-size:13px;line-height:1.45}
  .pxlive .it b{display:block;font-size:11px;letter-spacing:.12em;text-transform:uppercase;margin-bottom:3px}
  .pxlive .dot{display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:6px;vertical-align:middle;background:currentColor}
  .pxlive .running .dot{animation:pxpulse 1.2s infinite}
  @keyframes pxpulse{0%{opacity:1}50%{opacity:.25}100%{opacity:1}}
  .pxlive .bar{height:5px;border-radius:5px;background:rgba(31,157,99,.18);margin-top:7px;overflow:hidden}
  .pxlive .bar i{display:block;height:100%;background:#1f9d63}
</style>
<div class="pxlive" id="pxlive">
  <?php foreach (['search' => '🔎 Müşteri arama', 'send' => '📮 E-posta gönderimi'] as $__k => $__t): $__s = $__live[$__k]; [$__c, $__bg, $__bd] = $__lc[$__s['state']] ?? $__lc['idle']; ?>
  <div class="it <?= $__s['state'] ?>" data-k="<?= $__k ?>" style="color:<?= $__c ?>;background:<?= $__bg ?>;border-color:<?= $__bd ?>">
    <b><span class="dot"></span><?= $__t ?></b><span class="tx" style="color:var(--ink)"><?= htmlspecialchars($__s['text']) ?></span>
    <div class="bar"<?= ($__k === 'send' && $__s['state'] === 'running' && !empty($__s['total'])) ? '' : ' style="display:none"' ?>><i style="width:<?= !empty($__s['total']) ? (int)round(100 * $__s['done'] / max(1, $__s['total'])) : 0 ?>%"></i></div>
  </div>
  <?php endforeach; ?>
</div>
<script>
/* Şerit 30 sn'de bir kendini yeniler (sayfa yenilenmez; formlar bozulmaz). */
(function(){ var C=<?= json_encode($__lc) ?>;
  function upd(){ if(document.hidden) return; fetch('/admin?live=1',{credentials:'same-origin',cache:'no-store'}).then(function(r){return r.json();}).then(function(d){
    ['search','send'].forEach(function(k){ var s=d[k], el=document.querySelector('#pxlive [data-k="'+k+'"]'); if(!s||!el) return; var c=C[s.state]||C.idle;
      el.className='it '+s.state; el.style.color=c[0]; el.style.background=c[1]; el.style.borderColor=c[2]; el.querySelector('.tx').textContent=s.text;
      var bar=el.querySelector('.bar'); if(bar){ var on=(k==='send'&&s.state==='running'&&s.total); bar.style.display=on?'':'none'; if(on) bar.firstElementChild.style.width=Math.round(100*s.done/Math.max(1,s.total))+'%'; } });
  }).catch(function(){}); }
  setInterval(upd, 30000);
})();
</script>

<div class="pxhead">
  <div class="pxchip"><b><?= count($__ld) ?></b><span>müşteri kaydı</span></div>
  <div class="pxchip<?= $__ready ? ' ok' : '' ?>"><b><?= $__ready ?></b><span>gönderilmeye hazır (yeni bulunan, doğrulanmış)</span></div>
  <div class="pxchip"><b><?= (int)($mbToday ?? 0) ?> / <?= (int)($mbCap ?? 0) ?></b><span>bugün gönderilen / günlük tavan</span></div>
  <div class="pxchip<?= $__bounced ? ' warn' : '' ?>"><b><?= $__bounced ?></b><span>adresi geçersiz (bir daha yazılmaz)</span></div>
</div>
<p class="ahint pxlead">Butikleri <b>bul</b> → <b>support@vestrasales.com</b>'dan kendi sunucumuzla <b>gönder</b> → listeden <b>takip et</b>. Sipariş ve fatura e-postaları ayrı yoldan (Brevo) gitmeye devam eder; kampanyalar onların kotasına dokunmaz. Her e-postada tek tık abonelikten çıkma var; çıkan, geri dönen ve kapalı alan adları bir daha hiçbir yoldan yazılmaz.</p>

<?= $__pb['D'] ?? '' ?>

<?= ($__pb['A'] ?? '').($__pb['G'] ?? '').($__pb['H'] ?? '') ?>

<?php /* 10 Eki 2026 (operatör: "müşteriler çok uzun, onları kapat"): müşteri listesi katlı; bir liste eylemi
         (sil, yeniden adlandır, durum, tek gönderim…) sonrasında ya da #leadlist bağlantısıyla açık gelir. */
  $__openList = in_array((string)($_GET['msg'] ?? ''), ['lead_bulk_deleted', 'lead_deleted', 'lead_email_ok', 'lead_name_empty', 'lead_notfound',
    'lead_renamed', 'lead_sent', 'lead_status_ok', 'letter_empty', 'letter_nolead', 'letter_quota', 'letter_dead', 'letter_sent', 'letter_failed', 'finder_ok', 'finder_none'], true); ?>
<details class="acard pxmore" id="leadlist"<?= $__openList ? ' open' : '' ?>>
  <summary>👥 Müşteri listesi (<?= count($__ld) ?>) <span class="ahint">· tıklayınca açılır — arama, durum, tek tek gönderim, silme</span></summary>
  <div class="acard-body pxinner"><?= $__pb['TABLE'] ?? '' ?></div>
</details>

<?php /* 10 Eki 2026 (operatör: "gönderilen raporlar çok yer tutuyor, başka bir alana topla"): gönderim geçmişi,
         aramaların sonuçları (eklenenler + seçerek gönder) ve durum sayıları tek, katlı bir alanda. */ ?>
<details class="acard pxmore" id="reports">
  <summary>📊 Raporlar <span class="ahint">· son gönderimler · aramaların sonuçları ve eklenen müşteriler · durum sayıları</span></summary>
  <div class="acard-body pxinner">
    <?= $__pb['STATS'] ?? '' ?>
    <?= $__pb['REPORTS_MB'] ?? '' ?>
    <?php if (($__pb['REPORTS_FR'] ?? '') !== ''): ?><div style="font-weight:700;font-size:13px;margin:0 0 6px">🌐 Son aramalar ve eklenen müşteriler</div><?= $__pb['REPORTS_FR'] ?><?php endif; ?>
  </div>
</details>
<details class="acard pxmore" id="addprospect"<?= $__openAdd ? ' open' : '' ?>>
  <summary>➕ Müşteri ekle / CSV içe aktar <span class="ahint">· elle tek müşteri ya da liste</span></summary>
  <div class="acard-body pxinner"><?= $__pb['ADDIMP'] ?? '' ?></div>
</details>

<details class="acard pxmore" id="moretools"<?= $__openMore ? ' open' : '' ?>>
  <summary>⚙️ Diğer araçlar <span class="ahint">· Claude ile kampanya yaz · gönderim listesi önizleme · arama anahtarları (Google, Hunter) · AI kişiselleştirme · sipariş/fatura e-posta ayarı · şablon ve önizleme · ürün teklifi</span></summary>
  <div class="acard-body pxinner">
    <div class="pxsec"><?= $__pb['F'] ?? '' ?></div>
    <div class="pxsec"><?= ($__pb['B'] ?? '').($__pb['C'] ?? '').($__pb['E'] ?? '') ?></div>
    <div class="pxsec"><?= ($__pb['AUTO'] ?? '').($__pb['OSM'] ?? '').($__pb['AIP'] ?? '') ?></div>
    <div class="pxsec"><?= ($__pb['SMTP'] ?? '').($__pb['TPL'] ?? '').($__pb['PREV'] ?? '').($__pb['OFFER'] ?? '') ?></div>
  </div>
</details>

<?= $__pb['HIDDEN'] ?? '' ?>
<script>
/* Bağlantı ya da yönlendirme kapalı bir kutunun içindeki bir bölümü gösteriyorsa (#aicamp, #findersend …) kutuyu aç. */
(function(){
  function openFor(h){ if(!h) return; var el=document.getElementById(h.replace(/^#/,'')); if(!el) return;
    var d=el.closest('details.pxmore'); if(d && !d.open){ d.open=true; } setTimeout(function(){ el.scrollIntoView({block:'start'}); },30); }
  document.addEventListener('DOMContentLoaded',function(){ openFor(location.hash);
    document.querySelectorAll('a[href^="#"]').forEach(function(a){ a.addEventListener('click',function(){ openFor(a.getAttribute('href')); }); }); });
  window.addEventListener('hashchange',function(){ openFor(location.hash); });
})();
</script>
