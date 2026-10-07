<?php
/**
 * Məxfilik siyasəti (GDPR) — Azərbaycan dili (məlumat xarakterli tərcümə; hüquqi
 * qüvvəyə malik mətn almancadır). Saytın həqiqətən etdiklərinə əsasən yazılıb.
 * Dəyişənlər: legal/datenschutz.php.
 */
?>
<h2>1. Məsul şəxs</h2>
<?php vr_company_block(); ?>
<p>
  Qanuni şərtlər (GDPR mad. 37, § 38 BDSG) mövcud olmadığından məlumatların qorunması üzrə məsul
  təyin edilməyib. Məlumatların qorunması ilə bağlı sorğular üçün
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-poçt]</em>' ?>
  ünvanına müraciət edin.
</p>

<h2>2. Nəyi <em>etmirik</em></h2>
<p>
  Heç bir veb-analitika aləti (nə Google Analytics, nə Matomo), reklam və ya izləmə pikselləri,
  sosial media plaginləri və profilləşdirmə istifadə etmirik. GDPR-in 22-ci maddəsi mənasında
  avtomatlaşdırılmış qərar qəbulu yoxdur. Premium Outlet-dəki qiymətqoyma da fərdiləşdirilməyib:
  qiymətlər sabit, dərc edilmiş plana əsaslanır və bütün ziyarətçilər üçün eynidir.
</p>
<p>
  Bütün şriftlər, stil cədvəlləri, skriptlər və şəkillər öz serverimizdən yüklənir. Xüsusilə Google
  Fonts istifadə edilmir — beləliklə səhifə açılanda IP ünvanınız heç bir üçüncü tərəfə ötürülmür.
</p>

<h2>3. Saytın ziyarəti (server jurnal faylları)</h2>
<p>
  Ziyarət zamanı hostinq provayderimiz texniki cəhətdən zəruri məlumatları emal edir: IP ünvanı,
  tarix və vaxt, sorğulanan resurs, referer, user agent və ötürülən məlumat həcmi. Bu məlumatlar
  səhifənin təqdim edilməsi və hücumların dəf edilməsi üçün zəruridir.
</p>
<ul>
  <li><strong>Məqsəd:</strong> təqdimat, sabitlik, İT təhlükəsizliyi</li>
  <li><strong>Hüquqi əsas:</strong> GDPR mad. 6 bənd 1 (f) (qanuni maraq)</li>
  <li><strong>Saxlama müddəti:</strong> adətən 7–30 gün, sonra avtomatik silinmə</li>
</ul>

<h2>4. Kukilər və lokal saxlama</h2>
<p>
  Yalnız texniki cəhətdən zəruri kukilərdən istifadə edirik. Bunlar üçün § 25 bənd 2 № 2 TDDDG-yə
  əsasən razılıq tələb olunmur — buna görə bizdə kuki banneri görmürsünüz.
</p>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Ad</th><th>Məqsəd</th><th>Müddət</th></tr></thead>
  <tbody>
    <tr><td><code><?= h((string)vr_config('session_name', 'maxsales_retail')) ?></code></td>
        <td>sessiya: səbət, satıcı girişi, CSRF qoruması</td><td>sessiyanın sonu</td></tr>
    <tr><td><code>vr_lang</code></td><td>seçilmiş dil</td><td>180 gün</td></tr>
    <tr><td><code>vr_member</code></td>
        <td>təsdiqlənmiş xəbər bülleteni qeydiyyatından sonra Vault-a erkən giriş (imzalanmış dəyər,
            açıq mətndə e-poçt yoxdur)</td><td>1 il</td></tr>
    <tr><td><code>vr_wish</code></td><td>istək siyahısı — yalnız məhsul identifikatorları</td><td>180 gün</td></tr>
    <tr><td><code>vr_seen</code></td><td>son baxılan məhsullar — yalnız məhsul identifikatorları</td><td>30 gün</td></tr>
  </tbody>
</table></div>
<p>
  Əlavə məlumat: <a href="<?= h(vr_url('legal/cookies.php')) ?>"><?= te('legal_cookies') ?></a>.
</p>

<h2>5. İstək siyahısı və son baxılan məhsullar</h2>
<p>
  İstək siyahısını və «Son baxılanlar»ı <strong>yalnız cihazınızdakı kukidə</strong> saxlayırıq.
  Kuki yalnız məhsul identifikatorlarını ehtiva edir (məs. <code>blm-ah0eg000</code>) — nə ad, nə
  e-poçt ünvanı, nə də sizi tanıdan identifikator. Serverlərimizdə bununla bağlı nə profil yaranır,
  nə də şəxsinizlə əlaqə qurulur.
</p>
<ul>
  <li><strong>Məqsəd:</strong> açıq şəkildə istədiyiniz funksiya</li>
  <li><strong>Hüquqi əsas:</strong> § 25 bənd 2 № 2 TDDDG (istifadəçinin tələb etdiyi xidmət üçün
      texniki cəhətdən zəruri); şəxsi məlumat olduğu qədər GDPR mad. 6 bənd 1 (f)</li>
  <li><strong>Saxlama müddəti:</strong> istək siyahısı 180 gün, son baxılanlar 30 gün — və ya kukiləri
      silənədək</li>
</ul>

<h2>6. Əlaqə forması</h2>
<p>
  Əlaqə formasından istifadə etsəniz, e-poçt ünvanınızı, istəyə bağlı ad və sifariş nömrənizi,
  habelə mesajınızın məzmununu emal edirik. E-poçt göndərişi uğursuz olarsa heç bir sorğu itməsin
  deyə bir nüsxə serverimizdə saxlanılır.
</p>
<ul>
  <li><strong>Məqsəd:</strong> sorğunuzun cavablandırılması</li>
  <li><strong>Hüquqi əsas:</strong> sifarişlə bağlıdırsa GDPR mad. 6 bənd 1 (b), əks halda mad. 6
      bənd 1 (f)</li>
  <li><strong>Saxlama müddəti:</strong> yekun emaladək, sonra ən çox altı ay; sifarişlə bağlı
      olduqda ticarət hüququ müddətləri tətbiq olunur</li>
</ul>
<p>
  Spama qarşı görünməz forma sahəsi və vaxt ölçümündən istifadə edirik. Xarici captcha xidməti
  <em>daxil edilmir</em> — beləliklə üçüncü tərəflərə məlumat ötürülmür.
</p>

<h2>7. Vault-da qiymət xəbərdarlığı</h2>
<p>
  Bir lot üçün qiymət xəbərdarlığı qursanız, e-poçt ünvanınızı, lotu, istədiyiniz qiyməti, vaxtı və
  sübut kimi IP ünvanınızın duzlanmış heş dəyərini saxlayırıq.
</p>
<ul>
  <li><strong>Məqsəd:</strong> tələb etdiyiniz yeganə bildiriş</li>
  <li><strong>Hüquqi əsas:</strong> GDPR mad. 6 bənd 1 (a) (razılıq)</li>
  <li><strong>Saxlama müddəti:</strong> bildiriş göndərilənədək, ən çox 90 gün. Sonra qeyd tamamilə
      silinir.</li>
</ul>
<p>
  <strong>Dəqiq bir</strong> e-poçt göndərilir; sonra xəbərdarlıq istifadə olunmuş sayılır.
  Xatırlatma və ya reklam gəlmir. Hər xəbərdarlıq e-poçtdakı link vasitəsilə dərhal silinə bilər.
</p>

<h2>8. Sifariş və müqavilənin icrası</h2>
<p>
  Sifariş üçün emal etdiklərimiz: ad, çatdırılma və hesab ünvanı, e-poçt ünvanı, sifariş edilən
  mallar, qiymətlər, ödəniş statusu, sifariş nömrəsi, habelə satış şərtləri və imtina məlumatına
  razılığınızın sübutu (vaxt, versiya və IP ünvanınızın duzlanmış heş dəyəri — IP-nin özü
  saxlanılmır).
</p>
<ul>
  <li><strong>Məqsəd:</strong> müqavilənin icrası, göndəriş, faktura, geri qaytarma</li>
  <li><strong>Hüquqi əsas:</strong> GDPR mad. 6 bənd 1 (b); saxlama üçün mad. 6 bənd 1 (c)</li>
  <li><strong>Saxlama müddəti:</strong> sifariş və faktura məlumatları ticarət və vergi hüququ
      saxlama müddətlərinə (§ 147 AO, § 257 HGB) tabedir və müvafiq olaraq saxlanılır, sonra
      silinir.</li>
</ul>
<p>
  <strong>Satıcılara ötürülmə:</strong> üçüncü tərəf satıcıların mallarında müvafiq satıcıya
  göndəriş və faktura üçün zəruri məlumatları ötürürük (ad, çatdırılma ünvanı, sifariş edilən
  mallar, sifariş nömrəsi). Satıcı bu məlumatlar üçün müstəqil məsul şəxsdir. Ödəniş məlumatları və
  digər satıcıların mallarına dair məlumatlar ötürülmür.
</p>

<h2>9. Ödənişin emalı (Stripe)</h2>
<p>
  Ödənişlər Stripe Payments Europe, Limited, 1 Grand Canal Street Lower, Grand Canal Dock, Dublin,
  İrlandiya vasitəsilə emal olunur. Ödəniş məlumatlarınızı birbaşa Stripe-da daxil edirsiniz;
  Stripe-dan yalnız status məlumatı (ödənilib/açıq/uğursuz), məbləğ, ümumi formada ödəniş üsulu, ad,
  e-poçt ünvanı və çatdırılma ünvanı alırıq.
</p>
<ul>
  <li><strong>Hüquqi əsas:</strong> GDPR mad. 6 bənd 1 (b) (müqavilənin icrası)</li>
  <li><strong>Üçüncü ölkəyə ötürülmə:</strong> Stripe məlumatları ABŞ-dakı Stripe, Inc.-ə ötürə
      bilər. Əsas Aİ Komissiyasının standart müqavilə müddəaları və EU-US Data Privacy Framework
      üzrə sertifikatlaşdırmadır.</li>
</ul>
<p>
  Satıcılara ödənişlər üçün Stripe Connect istifadə edirik. Satıcılar bunun üçün Stripe ilə ayrıca
  müqavilə bağlayır; orada toplanan şəxsiyyət sübutlarını (KYC/çirkli pulların yuyulmasına qarşı)
  Stripe müstəqil məsul şəxs kimi emal edir. Biz yalnız <code>charges_enabled</code>,
  <code>payouts_enabled</code> və <code>details_submitted</code> status göstəricilərini alırıq.
</p>
<p>Stripe-ın məxfilik siyasəti: <a href="https://stripe.com/privacy" rel="noopener">stripe.com/privacy</a></p>

<h2>10. E-poçt göndərişi</h2>
<p>
  Əməliyyat e-poçtlarını (sifariş təsdiqi, göndəriş bildirişi, satıcı bildirişi) və xəbər
  bülletenlərini
  <?= h($mail['provider'] === 'brevo' ? 'Brevo (Sendinblue GmbH / Brevo SAS, Paris, Fransa)' : 'öz poçt serverimiz') ?>
  vasitəsilə göndəririk. E-poçt ünvanı, ad və mesajın məzmunu ötürülür.
</p>
<ul>
  <li><strong>Hüquqi əsas:</strong> əməliyyat e-poçtları GDPR mad. 6 bənd 1 (b); xəbər bülleteni
      mad. 6 bənd 1 (a) (razılıq)</li>
  <li><strong>Tapşırıqla emal:</strong> GDPR mad. 28 üzrə müqavilə mövcuddur.</li>
</ul>

<h2>11. Xəbər bülleteni / Vault üzvlüyü</h2>
<p>
  Qeydiyyat ikiqat təsdiq (double opt-in) qaydası ilə aparılır: ünvanı daxil etdikdən sonra təsdiq
  e-poçtu alırsınız; qeydiyyat yalnız linkə kliklədikdən sonra qüvvəyə minir. Sübut kimi qeydiyyat
  vaxtını, təsdiq vaxtını və IP ünvanının duzlanmış heş dəyərini saxlayırıq.
</p>
<p>
  Təsdiqlənməmiş qeydiyyatları 7 gündən sonra avtomatik silirik. Razılığınızı istənilən vaxt geri
  götürə bilərsiniz — hər e-poçtdakı abunəlikdən çıxma linki ilə və ya bizə mesajla. Geri götürmə o
  vaxta qədər aparılmış emalın qanuniliyinə toxunmur.
</p>

<h2>12. Satıcı hesabları</h2>
<p>
  Satıcı hesabı üçün emal etdiklərimiz: ad, varsa şirkət adı, e-poçt ünvanı, ölkə, varsa ƏDV
  nömrəsi, satıcı növü (kommersiya/fərdi), parol (yalnız kriptoqrafik heş kimi, heç vaxt açıq
  mətndə), təkliflər, habelə dövriyyə və ödəniş məlumatları.
</p>
<ul>
  <li><strong>Hüquqi əsas:</strong> GDPR mad. 6 bənd 1 (b); təkliflərin yoxlanması və tacir
      məlumatlarının izlənə bilməsi üçün əlavə olaraq mad. 6 bənd 1 (c) Rəqəmsal Xidmətlər Aktının
      30-cu maddəsi ilə birlikdə.</li>
  <li><strong>Dərc:</strong> kommersiya satıcılarında məhsul səhifəsində ad/şirkət və ölkəni
      göstəririk; bu qanunla tələb olunur. Fərdi satıcılarda yalnız «<?= te('seller_private') ?>»
      statusu göstərilir, tam ad yox.</li>
</ul>

<h2>13. Təhlükəsizlik tədbirləri və jurnallar</h2>
<p>
  Uğursuz giriş cəhdləri, ödəniş xətaları və webhook hadisələri barədə texniki jurnallar aparırıq.
  Onlar vaxt, hadisə növü və texniki identifikatorları ehtiva edir; e-poçt ünvanları qısaldılır.
  Məqsəd sui-istifadə və saxtakarlığın qarşısının alınmasıdır (GDPR mad. 6 bənd 1 (f)), saxlama
  müddəti ən çox 90 gün.
</p>
<p>
  Ötürmə şifrələnib (TLS). Parollar müasir birtərəfli heş üsulu ilə saxlanılır.
</p>

<h2>14. Hüquqlarınız</h2>
<p>İstənilən vaxt aşağıdakı hüquqlarınız var:</p>
<ul>
  <li>haqqınızda saxlanılan məlumatlara çıxış (GDPR mad. 15)</li>
  <li>yanlış məlumatların düzəldilməsi (GDPR mad. 16)</li>
  <li>silinmə (GDPR mad. 17), saxlama öhdəliyi mane olmadıqda</li>
  <li>emalın məhdudlaşdırılması (GDPR mad. 18)</li>
  <li>məlumatların daşınması (GDPR mad. 20)</li>
  <li>qanuni maraqlara əsaslanan emala etiraz (GDPR mad. 21)</li>
  <li>verilmiş razılıqların gələcəyə təsirlə geri götürülməsi (GDPR mad. 7 bənd 3)</li>
</ul>
<p>
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-poçt]</em>' ?>
  ünvanına mesaj kifayətdir. Bundan əlavə, məlumatların qorunması üzrə nəzarət orqanına, məsələn
  adi yaşayış yerinizin orqanına şikayət etmək hüququnuz var.
</p>

<h2>15. Dəyişikliklər</h2>
<p>
  Faktiki emal dəyişdikdə — məsələn yeni xidmət təminatçısı cəlb edildikdə — bu bəyanatı
  uyğunlaşdırırıq. Bu səhifədə dərc edilmiş versiya qüvvədədir.
</p>
