<?php
/**
 * Ödəniş üsulları — Azərbaycan dili (məlumat xarakterli tərcümə). $mode: legal/zahlung.php.
 */
?>
<h2>Ödəniş necə baş verir</h2>
<p>
  Malları səbətə əlavə edir, kassada çatdırılma ölkəsini seçir və satış şərtləri ilə imtina hüququ
  məlumatını təsdiqləyirsiniz. Ödəniş üçün sizi ödəniş xidməti təminatçımız Stripe-a yönləndiririk.
  Orada ödəniş məlumatlarınızı daxil edib ödənişi tamamlayırsınız; sonra sifariş təsdiqinə
  qayıdırsınız.
</p>
<p>
  <strong>Kart məlumatlarınız heç vaxt serverlərimizə çatmır.</strong> Stripe-dan yalnız ödənişin
  uğurlu olub-olmadığı, məbləğ, ümumi formada ödəniş üsulu və çatdırılma ilə faktura üçün zəruri
  məlumatları alırıq.
</p>

<h2>Mövcud ödəniş üsulları</h2>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Ödəniş üsulu</th><th>Tutulma</th><th>Qeyd</th></tr></thead>
  <tbody>
    <tr><td>Visa, Mastercard, American Express</td><td>dərhal</td>
        <td>bankınızın 3-D Secure təsdiqi tələb oluna bilər (SCA)</td></tr>
    <tr><td>Apple Pay</td><td>dərhal</td><td>Apple cihazlarında Safari-də</td></tr>
    <tr><td>Google Pay</td><td>dərhal</td><td>Chrome-da və Android-də</td></tr>
    <tr><td>Klarna</td><td>seçilən variantdan asılı olaraq</td>
        <td>faktura ilə və ya hissə-hissə; maliyyələşdirmə müqaviləsi Klarna ilədir</td></tr>
    <tr><td>SEPA birbaşa debet</td><td>1–3 bank iş günü</td>
        <td>ödəniş təsdiqləndikdən sonra göndəriş</td></tr>
  </tbody>
</table></div>
<p class="doc__related">
  Hansı ödəniş üsullarının faktiki göstərilməsi çatdırılma ölkəsindən, məbləğdən və cihazdan
  asılıdır — Stripe yalnız sifarişiniz üçün istifadə oluna biləni göstərir. Təklif olunan ödəniş
  üsullarının heç biri sizin üçün əlavə xərc yaratmır.
</p>

<?php if ($mode !== 'live'): ?>
  <div class="notice notice--demo">
    <strong>Bu quraşdırma hazırda canlı rejimdə işləmir.</strong>
    Real ödənişlər həyata keçirilə bilməz
    (<?= $mode === 'test' ? 'Stripe test rejimi aktivdir' : 'Stripe açarları qurulmayıb' ?>).
  </div>
<?php endif; ?>

<h2>Ödəniş vaxtı</h2>
<p>
  Alış qiyməti müqavilə bağlanan andan ödənilməlidir. Gecikmiş hesablaşmalı ödəniş üsullarında
  mallarınızı rezerv edir və ödəniş təsdiqləndikdən sonra göndəririk.
</p>

<h2>Valyuta və vergi</h2>
<p>
  Bütün qiymətlər avro ilə göstərilib və müvafiq satıcı ƏDV ödəyicisidirsə,
  <?= h((int)vr_config('vat_rate_bps', 1900) / 100) ?> % qanuni ƏDV-ni ehtiva edir. Fərdi
  satıcıların mallarında ƏDV göstərilmir. Bankınız başqa valyutada hesablaşırsa, konvertasiya haqqı
  tuta bilər — buna təsirimiz yoxdur.
</p>

<h2>Faktura</h2>
<p>
  Fakturanı malla birlikdə və ya e-poçtla alırsınız. Kommersiya üçüncü tərəf satıcıların mallarında
  fakturanı müvafiq tacir verir; öz mallarımızda —
  <?= h((string)(vr_config('company')['legal_name'] ?? '')) ?>. Fərdi satıcılar ƏDV göstərilən
  faktura vermir.
</p>

<h2>Geri ödəmələr</h2>
<p>
  Geri ödəmələr həmişə ödədiyiniz eyni ödəniş vasitəsinə edilir. Klarna-da geri ödəmə Klarna
  hesabınız vasitəsilə, SEPA-da isə debet edilmiş hesaba gedir. Emalı qaytarmanın alınması və
  yoxlanmasından sonra başlayırıq; bankınızda hesaba köçürülənədək ödəniş üsulundan asılı olaraq bir
  neçə iş günü keçə bilər.
</p>

<h2>Uğursuz ödəniş</h2>
<p>
  Ödəniş rədd edilərsə, müqavilə bağlanmır və heç nə tutulmur. Səbətiniz saxlanılır ki, yenidən və
  ya başqa ödəniş üsulu ilə cəhd edə biləsiniz. Ən çox rast gəlinən səbəb tamamlanmamış 3-D Secure
  təsdiqidir.
</p>

<h2>Ödəniş təhlükəsizliyi</h2>
<p>
  Bağlantı başdan-başa TLS ilə şifrələnib. Stripe ödəniş xidməti təminatçısı kimi PCI DSS 1-ci
  səviyyə üzrə sertifikatlaşdırılıb və Avropada ödəniş qurumu kimi lisenziyalaşdırılıb (Stripe
  Payments Europe, Limited, Dublin). Saxtakarlığın qarşısını almaq üçün Stripe əməliyyatları
  avtomatik yoxlayır; biz kart nömrələrini saxlamırıq.
</p>
<p class="doc__related">
  Məlumatların emalı barədə təfərrüatlar: <a href="<?= h(vr_url('legal/datenschutz.php')) ?>"><?= te('legal_privacy') ?></a>.
</p>
