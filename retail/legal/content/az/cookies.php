<?php
/**
 * Kuki siyasəti — Azərbaycan dili (məlumat xarakterli tərcümə). Banner yoxdur, çünki izləmə yoxdur.
 */
?>
<div class="doc__box">
  <strong>Niyə burada kuki banneri görmürsünüz</strong>
  <p style="margin-top:8px">
    Banner yalnız texniki cəhətdən zəruri olandan kənar kukilər — analitika, reklam, izləmə —
    quraşdırıldıqda lazımdır. Bunların heç birini istifadə etmirik. Sırf funksional kukilər üçün
    § 25 bənd 2 № 2 TDDDG razılıq olmadan quraşdırmağa icazə verir. Buna görə: banner yox, «Hamısını
    qəbul et» düyməsi yox, razılıq xidməti yox.
  </p>
</div>

<h2>Tam siyahı</h2>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Ad</th><th>Növ</th><th>Məqsəd</th><th>Saxlama müddəti</th></tr></thead>
  <tbody>
    <tr>
      <td><code><?= h((string)vr_config('session_name', 'maxsales_retail')) ?></code></td>
      <td>sessiya kukisi</td>
      <td>Sessiyanızı bir yerdə saxlayır: səbətin məzmunu, Vault rezervləri, satıcı girişi və forma
          saxtakarlığına qarşı təhlükəsizlik tokeni (CSRF). Yalnız təsadüfi identifikator ehtiva
          edir, şəxsi məzmun yoxdur.</td>
      <td>brauzer sessiyasının sonunadək</td>
    </tr>
    <tr>
      <td><code>vr_lang</code></td>
      <td>funksional</td>
      <td>Seçilmiş dili yadda saxlayır ki, hər klikdə yenidən seçməyəsiniz. Məzmun: <code>az</code>
          kimi dil kodu.</td>
      <td>180 gün</td>
    </tr>
    <tr>
      <td><code>vr_member</code></td>
      <td>funksional</td>
      <td>Yalnız Vault üzvlüyünü e-poçtla təsdiqlədikdən sonra quraşdırılır və yeni lotlara erkən
          girişi açır. Bitmə tarixi, e-poçt ünvanınızın qısaldılmış heş dəyəri və imza ehtiva edir —
          ünvanınızı açıq mətndə yox.</td>
      <td>1 il</td>
    </tr>
    <tr>
      <td><code>vr_wish</code></td>
      <td>funksional</td>
      <td>İstək siyahınız. Məzmun: məhsul identifikatorlarının siyahısı, başqa heç nə. İstək siyahısı
          səhifəsini özünüz açmadıqca serverə ötürülmür.</td>
      <td>180 gün</td>
    </tr>
    <tr>
      <td><code>vr_seen</code></td>
      <td>funksional</td>
      <td>Son baxdığınız məhsullar — onları yenidən tapasınız deyə. Yenə yalnız məhsul
          identifikatorları.</td>
      <td>30 gün</td>
    </tr>
  </tbody>
</table></div>

<h2>Stripe kukiləri</h2>
<p>
  Ödəniş zamanı Stripe-ın səhifəsinə keçirsiniz. Stripe orada ödənişin emalı və saxtakarlığın
  qarşısının alınması üçün zəruri olan öz kukilərini quraşdırır. Bu, Stripe-ın domenində baş verir və
  <a href="https://stripe.com/privacy" rel="noopener">Stripe-ın məxfilik siyasətinə</a> tabedir. Öz
  səhifələrimizdə Stripe skripti daxil edilmir.
</p>

<h2>Lokal saxlama yox, barmaq izi yox</h2>
<p>
  Nə <code>localStorage</code>, nə <code>sessionStorage</code>, nə piksel, nə fingerprinting
  texnikaları, nə də cihazlararası tanıma istifadə edirik. Bütün şriftlər, stillər, skriptlər və
  şəkillər öz serverimizdədir; səhifə açılanda üçüncü tərəflərlə əlaqə qurulmur.
</p>

<h2>Kukiləri silmək və ya bloklamaq</h2>
<p>
  Kukiləri istənilən vaxt brauzer parametrlərində silə və ya bloklaya bilərsiniz. Sessiya kukisini
  bloklasanız səbət, kassa və satıcı girişi işləmir — addımlarınızı birləşdirən texniki sap çatışmır.
  Dil və Vault girişi problemsiz bloklana bilər; onda dili yenidən soruşuruq və erkən giriş olmur.
</p>
<p class="doc__related">
  Məlumatların emalı barədə ətraflı:
  <a href="<?= h(vr_url('legal/datenschutz.php')) ?>"><?= te('legal_privacy') ?></a>.
</p>
