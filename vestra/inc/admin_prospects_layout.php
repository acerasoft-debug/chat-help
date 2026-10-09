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
$__ready   = isset($fsTargetsAll) ? count($fsTargetsAll) : 0;
/* Bir eylemden sonra (Brevo / Claude / lemlist / SMTP / şablon / teklif) "Diğer araçlar" açık gelsin: sayfa o
   bölüme döner ve kapalı bir kutunun içinde kaybolmaz. */
$__openMore = !empty($fsFlash) || !empty($acFlash) || (($_GET['mailfor'] ?? '') !== '')
           || in_array((string)($_GET['msg'] ?? ''), ['lead_tpl_ok', 'quote_sent', 'quote_failed', 'quote_invalid', 'quote_unsub', 'email_saved', 'test_ok', 'test_fail', 'test_invalid',
              'ai_saved', 'finder_saved', 'finder_ok', 'finder_none', 'google_saved', 'google_cleared'], true);
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

<div class="pxhead">
  <div class="pxchip"><b><?= count($__ld) ?></b><span>müşteri kaydı</span></div>
  <div class="pxchip<?= $__ready ? ' ok' : '' ?>"><b><?= $__ready ?></b><span>gönderilmeye hazır (yazılmamış, temiz)</span></div>
  <div class="pxchip"><b><?= (int)($mbToday ?? 0) ?> / <?= (int)($mbCap ?? 0) ?></b><span>bugün gönderilen / günlük tavan</span></div>
  <div class="pxchip<?= $__bounced ? ' warn' : '' ?>"><b><?= $__bounced ?></b><span>adresi geçersiz (bir daha yazılmaz)</span></div>
</div>
<p class="ahint pxlead">Butikleri <b>bul</b> → <b>support@vestrasales.com</b>'dan kendi sunucumuzla <b>gönder</b> → listeden <b>takip et</b>. Sipariş ve fatura e-postaları ayrı yoldan (Brevo) gitmeye devam eder; kampanyalar onların kotasına dokunmaz. Her e-postada tek tık abonelikten çıkma var; çıkan, geri dönen ve kapalı alan adları bir daha hiçbir yoldan yazılmaz.</p>

<?= $__pb['D'] ?? '' ?>

<?= ($__pb['A'] ?? '').($__pb['G'] ?? '').($__pb['H'] ?? '') ?>

<?= $__pb['STATS'] ?? '' ?>
<?= $__pb['TABLE'] ?? '' ?>
<details class="acard pxmore" id="addprospect"<?= $__openAdd ? ' open' : '' ?>>
  <summary>➕ Müşteri ekle / CSV içe aktar <span class="ahint">· elle tek müşteri ya da liste</span></summary>
  <div class="acard-body pxinner"><?= $__pb['ADDIMP'] ?? '' ?></div>
</details>

<details class="acard pxmore" id="moretools"<?= $__openMore ? ' open' : '' ?>>
  <summary>⚙️ Diğer araçlar <span class="ahint">· Claude ile kampanya yaz · Brevo / lemlist ile gönder (eski yollar) · otomatik ve OpenStreetMap araması · AI kişiselleştirme · gönderim ayarı · şablon ve önizleme · ürün teklifi</span></summary>
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
