<?php
/**
 * İmtina hüququ barədə məlumat + nümunəvi forma — Azərbaycan dili (məlumat
 * xarakterli tərcümə). 2011/83/Aİ Direktivinin rəsmi nümunəsini (I əlavə) izləyir,
 * alman orijinalı BGB-ni izlədiyi kimi. Dəyişənlər: legal/widerruf.php.
 */
?>
<div class="notice">
  <strong>Bazar meydanında alışlar üçün vacib:</strong> qanuni imtina hüququ yalnız istehlakçı ilə
  <em>sahibkar</em> arasındakı müqavilələrdə mövcuddur. Məhsul səhifəsində
  «<?= te('seller_private') ?>» kimi işarələnmiş mallar üçün buna görə imtina hüququ
  <strong>yoxdur</strong>. Bu işarəni ödənişdən əvvəl görürsünüz və sifariş zamanı açıq şəkildə
  təsdiqləməlisiniz.
</div>

<h2>İmtina hüququ</h2>
<p>
  Bu müqavilədən <?= (int)$wd ?> gün ərzində səbəb göstərmədən imtina etmək hüququnuz var.
</p>
<p>
  İmtina müddəti sizin və ya sizin göstərdiyiniz, daşıyıcı olmayan üçüncü şəxsin mallara sahib
  olduğu gündən etibarən <?= (int)$wd ?> gündür.
</p>
<p>
  Vahid sifariş çərçivəsində sifariş etdiyiniz və ayrı-ayrı çatdırılan bir neçə mal haqqında
  müqavilədə imtina müddəti sizin və ya sizin göstərdiyiniz, daşıyıcı olmayan üçüncü şəxsin sonuncu
  mala sahib olduğu gündən etibarən <?= (int)$wd ?> gündür.
</p>
<p>
  İmtina hüququnuzu həyata keçirmək üçün bizi
</p>
<div class="doc__box">
  <?= h($co) ?><br>
  <?= h($addrShown) ?><br>
  <?= $email !== '' ? 'E-poçt: <a href="mailto:' . h($email) . '">' . h($email) . '</a>' : 'E-poçt: [e-poçt ünvanı]' ?>
</div>
<p>
  bu müqavilədən imtina etmək qərarınız barədə birmənalı bəyanatla (məs. poçtla göndərilən məktub
  və ya e-poçt) məlumatlandırmalısınız. Bunun üçün əlavə edilmiş nümunəvi imtina formasından
  istifadə edə bilərsiniz, lakin bu məcburi deyil.
</p>
<p>
  İmtina kommersiya üçüncü tərəf satıcısının malına aiddirsə, bizə ünvanlanmış bəyanat kifayətdir;
  biz onu qəbul etməyə səlahiyyətliyik və dərhal ötürürük.
</p>
<p>
  İmtina müddətinə riayət etmək üçün imtina hüququnun həyata keçirilməsi barədə bildirişi imtina
  müddəti bitməzdən əvvəl göndərməyiniz kifayətdir.
</p>

<h2>İmtinanın nəticələri</h2>
<p>
  Bu müqavilədən imtina etsəniz, sizdən aldığımız bütün ödənişləri, çatdırılma xərcləri daxil
  olmaqla (bizim təklif etdiyimiz ən ucuz standart çatdırılmadan fərqli çatdırılma növü seçməyiniz
  nəticəsində yaranan əlavə xərclər istisna olmaqla), dərhal və ən geci bu müqavilədən imtinanız
  barədə bildirişin bizə çatdığı gündən on dörd gün ərzində geri qaytarmalıyıq. Bu geri qaytarma
  üçün, sizinlə açıq şəkildə başqa cür razılaşdırılmayıbsa, ilkin əməliyyatda istifadə etdiyiniz
  eyni ödəniş vasitəsindən istifadə edirik; heç bir halda bu geri qaytarmaya görə sizdən haqq
  tutulmur.
</p>
<p>
  Malları geri alanadək və ya malları geri göndərdiyinizə dair sübut təqdim edənədək — hansı daha
  əvvəl baş verərsə — geri qaytarmadan imtina edə bilərik.
</p>
<p>
  Malları bizə və ya qaytarma etiketində göstərilən satıcıya bu müqavilədən imtinanız barədə bizi
  məlumatlandırdığınız gündən dərhal və hər halda ən geci on dörd gün ərzində geri göndərməli və ya
  təhvil verməlisiniz. Malları on dörd günlük müddət bitməzdən əvvəl göndərsəniz, müddətə riayət
  edilmiş sayılır.
</p>
<p>
  Qaytarma Almaniyadan həyata keçirilirsə, malların geri göndərilməsi xərclərini biz ödəyirik.
  Digər ölkələrdən qaytarmalarda geri göndərmənin birbaşa xərclərini siz ödəyirsiniz.
</p>
<p>
  Malların mümkün dəyər itkisinə görə yalnız bu itki malların xüsusiyyətlərini, keyfiyyətlərini və
  iş qaydasını yoxlamaq üçün zəruri olmayan davranışdan irəli gəldikdə məsuliyyət daşıyırsınız.
  Geyimi geyinib yoxlamağa icazə verilir; geyinmək, yumaq və ya etiketləri çıxarmaq bundan
  kənara çıxır.
</p>

<h2>İmtina hüququnun istisnası</h2>
<p>Aşağıdakı müqavilələrdə imtina hüququ yoxdur və ya itir:</p>
<ul>
  <li>sahibkar olmayan satıcılarla müqavilələr (fərdi satıcılar);</li>
  <li>müştəri spesifikasiyasına görə hazırlanmış və ya açıq şəkildə şəxsi ehtiyaclara uyğunlaşdırılmış
      malların çatdırılması müqavilələri;</li>
  <li>sağlamlığın qorunması və ya gigiyena səbəbindən qaytarılmağa yararsız olan, çatdırılmadan sonra
      möhürü açılmış möhürlənmiş malların çatdırılması müqavilələri (məs. sırğalar, gigiyena möhürü
      olmayan çimərlik geyimi);</li>
  <li>sahibkar kimi çıxış etdiyiniz müqavilələr (B2B).</li>
</ul>

<h2>Nümunəvi imtina forması</h2>
<div class="doc__box">
  <p><em>(Müqavilədən imtina etmək istəyirsinizsə, zəhmət olmasa bu formanı doldurun və geri
  göndərin.)</em></p>
  <p>
    Kimə<br>
    <?= h($co) ?><br>
    <?= h($addrShown) ?><br>
    <?= $email !== '' ? h($email) : '[e-poçt ünvanı]' ?>
  </p>
  <p>
    Bununla mən/biz (*) mənim/bizim (*) tərəfimizdən bağlanmış aşağıdakı malların (*) alışı
    müqaviləsindən imtina edirəm/edirik (*):
  </p>
  <p>
    ______________________________________________<br>
    ______________________________________________
  </p>
  <p>
    Sifariş nömrəsi: ____________________________<br>
    Sifariş tarixi (*) / alınma tarixi (*): ____________________________<br>
    İstehlakçının(ların) adı: ____________________________<br>
    İstehlakçının(ların) ünvanı: ____________________________<br>
    ____________________________
  </p>
  <p>
    İstehlakçının(ların) imzası <em>(yalnız kağız üzərində bildiriş zamanı)</em>: ____________________________<br>
    Tarix: ____________________________
  </p>
  <p><em>(*) Lazım olmayanın üstündən xətt çəkin.</em></p>
</div>

<p class="doc__related">
  Qanuni imtina hüququndan əlavə, öz mallarımız üçün könüllü qaytarma hüququ veririk — təfərrüatlar:
  <a href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('legal_returns') ?></a>.
</p>
