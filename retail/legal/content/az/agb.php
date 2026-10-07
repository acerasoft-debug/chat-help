<?php
/**
 * Satış şərtləri — Azərbaycan dili (məlumat xarakterli tərcümə; hüquqi qüvvəyə
 * malik mətn almancadır). Dəyişənlər: legal/agb.php. Nömrələmə orijinalı abzas-abzas
 * izləyir ki, istinadlar («bənd 10.3») üst-üstə düşsün.
 */
?>
<nav class="doc__toc">
  <a href="#s1">1. Tətbiq dairəsi və müqavilə tərəfləri</a>
  <a href="#s2">2. Platformanın rolu</a>
  <a href="#s3">3. Müqavilənin bağlanması</a>
  <a href="#s4">4. Qiymətlər və çatdırılma xərcləri</a>
  <a href="#s5">5. Ödəniş</a>
  <a href="#s6">6. Çatdırılma</a>
  <a href="#s7">7. Mülkiyyət hüququnun saxlanılması</a>
  <a href="#s8">8. Müqavilədən imtina və könüllü qaytarma</a>
  <a href="#s9">9. Qüsurlara görə məsuliyyət</a>
  <a href="#s10">10. Fərdi satıcılardan alışlar</a>
  <a href="#s11">11. Premium Outlet (Vault)</a>
  <a href="#s12">12. Orijinallıq və mənşə</a>
  <a href="#s13">13. Məsuliyyət</a>
  <a href="#s14">14. Endirim kuponları</a>
  <a href="#s15">15. Məlumatların qorunması</a>
  <a href="#s16">16. Yekun müddəalar</a>
</nav>

<h2 id="s1">1. Tətbiq dairəsi və müqavilə tərəfləri</h2>
<p>
  1.1 Bu satış şərtləri <?= h($brand) ?> (<a href="<?= h(vr_origin()) ?>"><?= h(vr_origin()) ?></a>)
  vasitəsilə verilən bütün sifarişlərə şamil edilir. Platformanın operatoru <?= h($co) ?>-dir
  (bundan sonra «Operator», «biz»).
</p>
<p>
  1.2 Platformada hər biri məhsul səhifəsində işarələnmiş üç növ təklif mövcuddur:
</p>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>İşarə</th><th>Satıcı</th><th>Alqı-satqı müqaviləsi üzrə tərəfiniz</th></tr></thead>
  <tbody>
    <tr><td><?= h($brand) ?> anbarı</td><td>Operatorun özü</td><td><?= h($co) ?></td></tr>
    <tr><td>Tacir</td><td>kommersiya üçüncü tərəf satıcısı</td><td>müvafiq tacir</td></tr>
    <tr><td>Fərdi satıcı</td><td>fiziki şəxs</td><td>müvafiq fiziki şəxs</td></tr>
  </tbody>
</table></div>
<p>
  1.3 Üçüncü tərəf satıcıların təkliflərində alqı-satqı müqaviləsi yalnız sizinlə müvafiq satıcı
  arasında bağlanır. Operator alqı-satqı müqaviləsinin tərəfi olmur. Platformanın özünün
  istifadəsi — ödənişin icrası, sifariş icmalı, vasitəçilik — üçün bu şərtlər sizinlə Operator
  arasında qüvvədədir.
</p>
<p>
  1.4 İstehlakçı — əsasən öz sahibkarlıq və ya müstəqil peşə fəaliyyətinə aid olmayan məqsədlərlə
  hüquqi əqd bağlayan hər bir fiziki şəxsdir (§ 13 BGB, Almaniya Mülki Məcəlləsi). Müştərinin fərqli
  şərtləri, biz onlara mətn formasında açıq razılıq vermədikcə, müqavilənin tərkib hissəsi olmur.
</p>

<h2 id="s2">2. Platformanın rolu</h2>
<p>
  2.1 Operator texniki infrastrukturu təqdim edir, üçüncü tərəf satıcıların təkliflərini dərcdən
  əvvəl inandırıcılıq və mənşə sübutları baxımından yoxlayır, ödənişi ödəniş xidməti təminatçısı
  Stripe vasitəsilə icra edir və komissiya çıxılmaqla satıcı payını satıcıya köçürür.
</p>
<p>
  2.2 Operator üçüncü tərəf satıcılar tərəfindən alıcının ödənişlərini öhdəlikdən azad edən qüvvə
  ilə qəbul etməyə səlahiyyətləndirilib. Platforma vasitəsilə ödəniş uğurla başa çatdıqda satıcı
  qarşısında ödəniş öhdəliyiniz yerinə yetirilmiş sayılır.
</p>
<p>
  2.3 Alqı-satqı müqaviləsinə dair bəyanatlar — xüsusən imtina, qüsur barədə bildiriş və
  müqavilənin ləğvi — etibarlı şəkildə
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '[e-poçt]' ?>
  ünvanına göndərilə bilər. Biz onları dərhal müvafiq satıcıya ötürür və həllində dəstək oluruq.
</p>

<h2 id="s3">3. Müqavilənin bağlanması</h2>
<p>
  3.1 Malların platformada təqdim edilməsi hüquqi cəhətdən məcburi təklif deyil, sifariş verməyə
  dəvətdir.
</p>
<p>
  3.2 Sifariş düyməsini («<?= te('checkout_go') ?>» və ardınca Stripe-da ödəniş) basmaqla siz
  səbətinizdəki malları almaq üçün məcburi təklif verirsiniz. Bundan əvvəl ödəniş səhifəsində
  məlumatlarınızı yoxlaya və düzəldə bilərsiniz.
</p>
<p>
  3.3 Sifarişinizin alınmasını dərhal e-poçtla təsdiq edirik. Bu alınma təsdiqi hələ qəbul demək
  deyil. Alqı-satqı müqaviləsi biz və ya satıcı qəbulu bəyan etdikdə yaxud malı göndərdikdə — ən
  geci, qəbulu açıq şəkildə bəyan edirsə, sifariş təsdiqi ilə bağlanır.
</p>
<p>
  3.4 Müqavilə bağlanmazsa, məsələn mal sifarişdən sonra artıq mövcud olmadığı üçün, sizi dərhal
  məlumatlandırır və artıq edilmiş ödənişləri tam geri qaytarırıq.
</p>
<p>
  3.5 Müqavilə mətni saxlanılır və sifariş təsdiqi ilə birlikdə mətn formasında (e-poçt), bu
  şərtlər və imtina hüququ barədə məlumat daxil olmaqla, sizə göndərilir.
</p>

<h2 id="s4">4. Qiymətlər və çatdırılma xərcləri</h2>
<p>
  4.1 Göstərilən bütün qiymətlər avro ilə son qiymətlərdir və müvafiq satıcı ƏDV ödəyicisidirsə,
  hazırda <?= h($vat) ?> % olan qanuni ƏDV-ni ehtiva edir. Fərdi satıcılarda ƏDV göstərilmir (§ 19
  UStG, Almaniya ƏDV Qanunu, yaxud qeyri-sahibkar tərəfindən satış).
</p>
<p>
  4.2 Mal qiymətlərinə əlavə olaraq çatdırılma xərcləri tutulur. Bunlar sifariş verilməzdən əvvəl
  ödəniş səhifəsində ayrıca və dəqiq məbləğlə göstərilir. Təfərrüatlar:
  <a href="<?= h(vr_url('legal/versand.php')) ?>"><?= te('legal_shipping') ?></a>.
</p>
<p>
  4.3 Sifarişinizdə bir neçə satıcının malları varsa, çatdırılma xərcləri yalnız bir dəfə hesablanır;
  göndərişlər ayrı-ayrı çata bilər.
</p>
<p>
  4.4 Aİ-dən kənar ölkələrə çatdırılmalarda əlavə olaraq gömrük rüsumları, idxal ƏDV-si və emal
  haqları tətbiq oluna bilər; bunları alan tərəf ödəyir.
</p>

<h2 id="s5">5. Ödəniş</h2>
<p>
  5.1 Ödəniş ödəniş xidməti təminatçısı Stripe (Stripe Payments Europe, Limited, Dublin, İrlandiya)
  vasitəsilə həyata keçirilir. Mövcud ödəniş üsulları sifariş zamanı göstərilir; icmal üçün bax:
  <a href="<?= h(vr_url('legal/zahlung.php')) ?>"><?= te('legal_payment') ?></a>.
</p>
<p>
  5.2 Alış qiyməti müqavilə bağlanan andan ödənilməlidir. Gecikmiş hesablaşmalı ödəniş
  üsullarında (məs. SEPA birbaşa debet, Klarna) ödəniş xidməti təminatçısı ödənişi təsdiqlədikdən
  sonra göndəririk.
</p>
<p>
  5.3 Ödəniş məlumatları, xüsusən kart məlumatları, yalnız ödəniş xidməti təminatçısı tərəfindən
  emal olunur. Operator tam kart məlumatlarını almır və saxlamır.
</p>
<p>
  5.4 Məsuliyyəti sizə aid olan geri çağırmalarda (chargeback) bu səbəbdən yaranan xərcləri, geri
  çağırmaya təqsirli şəkildə səbəb olmusunuzsa, sizdən tələb etmək hüququmuz var.
</p>

<h2 id="s6">6. Çatdırılma</h2>
<p>
  6.1 Çatdırılma göstərdiyiniz çatdırılma ünvanına həyata keçirilir. Çatdırılma müddətləri və
  təyinat əraziləri <a href="<?= h(vr_url('legal/versand.php')) ?>"><?= te('legal_shipping') ?></a>
  bölməsində göstərilib və ödənişin təsdiqi ilə başlayır.
</p>
<p>
  6.2 Üçüncü tərəf satıcıların mallarını müvafiq satıcı göndərir. Hər göndəriş üçün ayrıca izləmə
  alırsınız.
</p>
<p>
  6.3 Mal platformada mövcud kimi göstərilsə də istisna hallarda çatdırıla bilmirsə, sizi dərhal
  məlumatlandırır və ödənilmiş məbləği tam geri qaytarırıq. Çox vaxt tək nüsxələr söhbət getdiyi
  üçün müqayisə edilə bilən malın sonradan çatdırılmasına hüquq yoxdur.
</p>
<p>
  6.4 İstehlakçılar üçün təsadüfi məhvolma və təsadüfi pisləşmə riski, göndəriş daşıyıcı vasitəsilə
  həyata keçirilsə belə, yalnız malın sizə təhvil verilməsi ilə keçir (§ 475 bənd 2 BGB).
</p>

<h2 id="s7">7. Mülkiyyət hüququnun saxlanılması</h2>
<p>
  Mal tam ödənilənədək müvafiq satıcının mülkiyyətində qalır.
</p>

<h2 id="s8">8. Müqavilədən imtina və könüllü qaytarma</h2>
<p>
  8.1 İstehlakçıların kommersiya satıcıları ilə müqavilələrdə <?= (int)$wd ?> günlük qanuni imtina
  hüququ var. Nümunəvi imtina forması ilə birlikdə tam məlumat:
  <a href="<?= h(vr_url('legal/widerruf.php')) ?>"><?= te('legal_withdrawal') ?></a>.
</p>
<p>
  8.2 Qanuni imtina hüququndan əlavə, öz mallarımız üçün alındığı gündən <?= (int)$days ?> günlük
  könüllü qaytarma hüququ veririk. Şərt: mal geyinilməmiş, zədəsiz və bütün orijinal etiketləri ilə
  olmalıdır. Könüllü qaytarma hüququ qanuni hüquqlarınızı məhdudlaşdırmır. Könüllü uzadılma
  çərçivəsində geri göndərmə xərclərini alıcı ödəyir; təfərrüatlar:
  <a href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('legal_returns') ?></a>.
</p>
<p>
  8.3 Fərdi satıcılardan alışlarda qanuni imtina hüququ yoxdur (bax: bənd 10).
</p>

<h2 id="s9">9. Qüsurlara görə məsuliyyət</h2>
<p>
  9.1 Kommersiya satıcılarında §§ 434 və s. BGB üzrə qanuni qüsur məsuliyyəti qüvvədədir.
  İstehlakçılar üçün iddia müddəti malın alınmasından etibarən iki ildir.
</p>
<p>
  9.2 İstifadə olunmuş mallarda istehlakçılara qarşı iddia müddəti, müqavilə bağlanmazdan əvvəl
  açıq və ayrıca razılaşdırılıbsa, bir ilə qədər qısaldıla bilər. Belə qeyd lazım gəldikdə məhsul
  səhifəsində göstərilir.
</p>
<p>
  9.3 Mal təsvirində qeyd olunan istifadə izləri qüsur deyil. Ekran təsvirindən irəli gələn rəng
  fərqləri qüsur deyil.
</p>
<p>
  9.4 Nəqliyyat zədələrini zəhmət olmasa 14 gün ərzində fotolarla bizə bildirin. Bu halları imtina
  hüququ məsələsindən asılı olmayaraq, fərdi satıcılarda da həll edirik.
</p>

<h2 id="s10">10. Fərdi satıcılardan alışlar</h2>
<p>
  10.1 Fiziki şəxslərin təklifləri məhsul səhifəsində, səbətdə və sifariş zamanı
  «<?= te('seller_private') ?>» kimi işarələnib. Sifarişi tamamlamazdan əvvəl xüsusi nəticələri
  açıq şəkildə təsdiqləməlisiniz.
</p>
<p>
  10.2 Satıcı sahibkar olmadığı üçün qanuni imtina hüququ yoxdur. Qanuni qüsur məsuliyyəti fərdi
  satıcı tərəfindən etibarlı şəkildə istisna edilə və ya məhdudlaşdırıla bilər; belə istisna
  qəsdən aldatma və ya bilərəkdən yanlış məlumat hallarına şamil edilmir.
</p>
<p>
  10.3 Bundan asılı olmayaraq: çatdırılan mal təsvirdən əhəmiyyətli dərəcədə fərqlənirsə və ya
  orijinal deyilsə, çatdırılma xərcləri daxil olmaqla alış qiymətini tam geri qaytarırıq. Bu
  hallarda satıcı payını saxlayır və ya geri tələb edirik.
</p>
<p>
  10.4 Əslində planlı, təkrar və mənfəət məqsədi ilə satan fərdi satıcılar kommersiya fəaliyyəti
  göstərir. Bunu müəyyən etsək, hesabı yenidən təsnif edir və ya bloklayırıq; bu halda artıq
  bağlanmış müqavilələrə sahibkarlara qarşı hüquqlar tətbiq olunur.
</p>

<h2 id="s11">11. Premium Outlet (Vault)</h2>
<p>
  11.1 «Premium Outlet» (Vault) bölməsində hər əşya açılış qiyməti, minimum qiymət və dərc edilmiş
  endirim planı ilə ayrıca lot kimi təklif olunur. Qiymət <?= (int)$steps ?> bərabər pillə ilə, hər
  <?= (int)$hours ?> saatdan bir bir pillə, minimum qiymətə qədər düşür.
</p>
<p>
  11.2 Plan lotun açılışında müəyyən edilir və sonra dəyişdirilmir. Qiymət arta bilməz. Hesablama
  server tərəfində plan əsasında aparılır və bütün ziyarətçilər üçün eynidir; fərdiləşdirilmiş
  qiymətqoyma yoxdur.
</p>
<p>
  11.3 Müəyyənedici qiymət səbətə əlavə edildiyi anda göstərilən və sifariş müddəti ərzində (20
  dəqiqə) sizin üçün rezerv edilən qiymətdir. Rezerv müddəti bitdikdə lot yenidən açılır.
</p>
<p>
  11.4 Hər lot yalnız bir dəfə mövcuddur. İlk etibarlı alışla başa çatır. Lotu sonrakı, daha aşağı
  pillədə almaq hüququ yoxdur.
</p>
<p>
  11.5 Qiymət endirimlərində § 11 PAngV-yə (Almaniya Qiymət Göstərmə Qaydaları) uyğun son 30 günün
  ən aşağı qiymətini göstəririk. Vault qiyməti yalnız düşdüyü üçün bu, cari pillədən bilavasitə
  əvvəl qüvvədə olan qiymətdir. İlk pillədə qiymət endirimi yoxdur; orada istinad qiyməti reklam
  edilmir.
</p>
<p>
  11.6 Qanuni hüquqlarınız, xüsusən imtina və qüsur məsuliyyəti, Vault-da dəyişməz qüvvədədir. Bənd
  10 fərdi satıcılara tətbiq olunmaqda davam edir.
</p>
<p>
  11.7 E-poçt qeydiyyatı təsdiqlənmiş üzvlər yeni lotlara ümumi açılışdan əvvəl giriş əldə edir.
  Üzvlük pulsuzdur və istənilən vaxt ləğv edilə bilər; əvvəlcədən girişə hüquq yoxdur.
</p>
<p>
  11.8 Qurulmuş qiymət xəbərdarlığı və əşyanın istək siyahısına əlavə edilməsi onu rezerv
  <strong>etmir</strong> və üstünlük hüququ yaratmır. Bildiriş bir dəfə, çatdırılma və ya vaxt
  zəmanəti olmadan göndərilir; yalnız sifariş anındakı mövcudluq həlledicidir.
</p>

<h2 id="s12">12. Orijinallıq və mənşə</h2>
<p>
  12.1 Yalnız orijinal mallar təklif olunur. Satıcılar mallarının alış sənədlərini saxlamağa və
  tələbimizlə bizə təqdim etməyə borcludur.
</p>
<p>
  12.2 Alışdan sonra malın orijinal olmadığı aşkar olunarsa, çatdırılma xərcləri ilə birlikdə alış
  qiymətini tam geri qaytarır və geri göndərmə xərclərini öz üzərimizə götürürük. Bu hüquq satıcı
  növündən asılı olmayaraq mövcuddur.
</p>
<p>
  12.3 Bənd 12.2 üzrə tələblər malı və iddianızı alındıqdan sonra 30 gün ərzində bizə təqdim
  etməyinizi və yoxlamağa imkan verməyinizi tələb edir.
</p>

<h2 id="s13">13. Məsuliyyət</h2>
<p>
  13.1 Qəsd və kobud ehtiyatsızlığa, həyata, bədənə və sağlamlığa zərərə görə, Almaniya Məhsul
  Məsuliyyəti Qanununun müddəalarına əsasən və verdiyimiz zəmanət həcmində məhdudiyyətsiz
  məsuliyyət daşıyırıq.
</p>
<p>
  13.2 Əsas müqavilə öhdəliyinin yüngül ehtiyatsızlıqla pozulmasında məsuliyyət müqavilə üçün
  tipik, qabaqcadan görünə bilən zərərlə məhdudlaşır. Digər hallarda məsuliyyət istisna edilir.
</p>
<p>
  13.3 Üçüncü tərəf satıcıların alqı-satqı müqaviləsi üzrə öhdəlik pozuntularına görə məsuliyyət
  daşımırıq; bu baxımdan məsuliyyətimiz hostinq xidmətləri haqqında müddəalarla (Rəqəmsal Xidmətlər
  Aktının 6-cı maddəsi) tənzimlənir. Bəndlər 10.3 və 12.2 qüvvədə qalır.
</p>
<p>
  13.4 Platformanın fasiləsiz əlçatanlığına zəmanət verilmir.
</p>

<h2 id="s14">14. Endirim kuponları</h2>
<p>
  14.1 Aksiya kuponları (satın alınmayan, reklam aksiyası çərçivəsində verilən kuponlar) yalnız
  göstərilən müddətdə və yalnız bir dəfə istifadə oluna bilər. Müddət bitdikdən sonra istifadə
  istisna edilir; uzadılma yoxdur.
</p>
<p>
  14.2 Kupon dəyəri mal dəyərinə hesablanır, çatdırılma xərclərinə yox. Nağd ödəniş, faiz və ya
  qalıq dəyərin hesaba köçürülməsi istisna edilir.
</p>
<p>
  14.3 Kupon e-poçt ünvanına bağlıdırsa və ya açıq şəkildə xoş gəldin yaxud ilk sifariş kuponu kimi
  işarələnibsə, onu yalnız həmin ünvanın sahibi və yalnız ilk ödənilmiş sifariş üçün istifadə edə
  bilər. Üçüncü şəxslərə ötürülmə və ya yenidən satış istisna edilir.
</p>
<p>
  14.4 Müvafiq kuponun şərtlərində başqa cür nəzərdə tutulmayıbsa, bir neçə kupon birləşdirilə
  bilməz. Kuponla göstərilibsə minimum sifariş dəyəri tətbiq olunur; çatdırılma xərcləri olmadan
  mal dəyəri həlledicidir.
</p>
<p>
  14.5 Sifarişdən tam və ya qismən imtina etsəniz, faktiki ödənilmiş məbləği geri qaytarırıq.
  İstifadə olunmuş aksiya kuponu bərpa olunmur; yeni kupon verilməsinə hüquq yoxdur.
</p>
<p>
  14.6 Sui-istifadə şübhəsi əsaslı olduqda — xüsusən ilk sifariş kuponlarını təkrar istifadə üçün
  bir neçə hesab yaradıldıqda — ayrı-ayrı kuponları bloklaya bilərik.
</p>

<h2 id="s15">15. Məlumatların qorunması</h2>
<p>
  Şəxsi məlumatlarınızın emalı barədə məlumat
  <a href="<?= h(vr_url('legal/datenschutz.php')) ?>"><?= te('legal_privacy') ?></a> bölməsindədir.
  Sifarişinizin icrası üçün çatdırılma və faktura üçün zəruri məlumatları müvafiq satıcıya
  ötürürük.
</p>

<h2 id="s16">16. Yekun müddəalar</h2>
<p>
  16.1 Almaniya Federativ Respublikasının hüququ tətbiq olunur. Başqa dövlətdə yaşayan istehlakçılar
  üçün yaşadıqları dövlətin məcburi istehlakçı müdafiəsi normaları qüvvədə qalır (Roma I
  Reqlamentinin 6-cı maddəsinin 2-ci bəndi).
</p>
<p>
  16.2 İcra yeri və məhkəmə aidiyyəti qanunvericiliklə müəyyən edilir. İstehlakçılar üçün qanuni
  məhkəmə aidiyyəti tətbiq olunur.
</p>
<p>
  16.3 Bu şərtlərin ayrı-ayrı müddəaları etibarsız olarsa, qalan müddəaların etibarlılığına
  toxunulmur. Etibarsız müddəanın yerini qanuni tənzimləmə tutur.
</p>
<p>
  16.4 Bu şərtləri gələcəyə təsirlə dəyişdirmək hüququmuzu özümüzdə saxlayırıq. Artıq bağlanmış
  müqavilələr üçün bağlanma anında əlçatan olan versiya qüvvədədir; qəbul edilmiş versiya
  sifarişinizlə birlikdə saxlanılır (cari versiya: <?= h(VR_TERMS_VERSION) ?>).
</p>

<div class="doc__box">
  <strong>Bu tərcümə haqqında qeyd</strong>
  <p style="margin-top:8px">
    Bu Azərbaycan dilindəki mətn yeganə hüquqi qüvvəyə malik olan alman dilindəki satış şərtlərinin
    məlumat xarakterli tərcüməsidir. Nəyə razılıq verdiyinizi oxumağınız üçündür; fərqlilik olduqda
    alman versiyası üstünlük təşkil edir. Hüquqi məsləhət deyil.
  </p>
</div>
