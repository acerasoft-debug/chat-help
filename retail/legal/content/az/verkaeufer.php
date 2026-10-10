<?php
/**
 * Satıcı şərtləri — Azərbaycan dili (məlumat xarakterli tərcümə; hüquqi qüvvəyə alman mətni malikdir).
 * Komissiya dərəcələri legal/verkaeufer.php vasitəsilə konfiqurasiyadan gəlir ki, müqavilə mətni
 * ilə faktiki tutulan dərəcə heç vaxt bir-birindən fərqlənməsin.
 */
?>
<nav class="doc__toc">
  <a href="#v1">1. Predmet</a>
  <a href="#v2">2. Satıcı hesabı və qeydiyyat</a>
  <a href="#v3">3. Tacir və ya fərdi şəxs</a>
  <a href="#v4">4. Elanlar və yoxlama</a>
  <a href="#v5">5. Qadağan olunmuş mallar</a>
  <a href="#v6">6. Komissiya</a>
  <a href="#v7">7. Ödəniş və köçürmə</a>
  <a href="#v8">8. Çatdırılma və qaytarma</a>
  <a href="#v9">9. Premium Outlet</a>
  <a href="#v10">10. Digital Services Act üzrə öhdəliklər</a>
  <a href="#v11">11. Məzmuna dair hüquqlar</a>
  <a href="#v12">12. Bloklama və xitam</a>
  <a href="#v13">13. Məsuliyyət və iddialardan azad etmə</a>
  <a href="#v14">14. Yekun müddəalar</a>
</nav>

<h2 id="v1">1. Predmet</h2>
<p>
  1.1 Bu şərtlər <?= h($brand) ?> marketpleysinin operatoru kimi <?= h($co) ?> ilə platforma vasitəsilə
  mal təklif edən şəxslər («Satıcılar») arasındakı münasibətləri tənzimləyir.
</p>
<p>
  1.2 Operator satış məkanını, Stripe vasitəsilə ödənişlərin emalını və sifarişlərin idarə olunmasını
  təmin edir. Təklif olunan mallar üzrə alqı-satqı müqaviləsi yalnız Satıcı ilə alıcı arasında
  bağlanır; Operator onun tərəfi olmur.
</p>
<p>
  1.3 Operator alıcıların ödənişlərini Satıcı adından öhdəliyi icra edən qüvvə ilə qəbul etmək, eləcə
  də alıcıların alqı-satqı müqaviləsinə dair bəyanatlarını (xüsusilə müqavilədən imtina və qüsur
  bildirişlərini) Satıcı adından qəbul edib ötürmək hüququna malikdir.
</p>

<h2 id="v2">2. Satıcı hesabı və qeydiyyat</h2>
<p>
  2.1 Qeydiyyat onlayn aparılır. Məlumatlar doğru, tam və aktual olmalıdır. Dəyişikliklər — xüsusilə
  şirkət adı, ünvan, vergi nömrəsi və ya satıcı növü — gecikmədən yenilənməlidir.
</p>
<p>
  2.2 Giriş məlumatları gizli saxlanmalıdır. Satıcı öz hesabı vasitəsilə edilən hərəkətlərə görə
  təqsiri olduğu həddə məsuliyyət daşıyır.
</p>
<p>
  2.3 İlk elan aktivləşdirilməzdən əvvəl Stripe-da (Stripe Connect) yoxlama tamamlanmalıdır. Yoxlama
  tamamlanmadan heç bir köçürmə mümkün deyil; bu halda elanlar oflayn qalır.
</p>

<h2 id="v3">3. Tacir və ya fərdi şəxs</h2>
<p>
  3.1 Qeydiyyat zamanı Satıcı sahibkar («Tacir») və ya fiziki şəxs («Fərdi satıcı») kimi satış etdiyini
  bildirir. Bu məlumat alıcılara məhsul səhifəsində və sifarişin rəsmiləşdirilməsi zamanı göstərilir və
  hansı istehlakçı hüquqlarının tətbiq olunduğunu müəyyən edir.
</p>
<p>
  3.2 Sistemli, təkrar və mənfəət məqsədi ilə satış edən şəxs — özünü necə qiymətləndirməsindən asılı
  olmayaraq — sahibkarlıq fəaliyyəti göstərir. Düzgün təsnifat və bütün vergi, ticarət və
  kommersiya-hüquqi öhdəliklər Satıcının məsuliyyətindədir.
</p>
<p>
  3.3 Faktiki satış fəaliyyəti sahibkarlıq xarakteri daşıyırsa, Operator əvvəlcədən xəbərdarlıq
  etdikdən sonra hesabı «Tacir» kateqoriyasına keçirmək və ya bloklamaq hüququna malikdir. Xüsusilə
  elanların sayı, dövriyyə və müntəzəmlik nəzərə alınır.
</p>
<p>
  3.4 Tacirlər istehlakçılara qanunla müəyyən edilmiş müqavilədən imtina hüququnu təmin etməyə, qaydasına
  uyğun hesab-faktura verməyə və qüsurlara görə qanuni məsuliyyəti yerinə yetirməyə borcludur.
</p>

<h2 id="v4">4. Elanlar və yoxlama</h2>
<p>
  4.1 Elanlar dəqiq, tam və aktual olmalıdır: marka, model adı, ölçü, vəziyyət, ƏDV daxil qiymət və
  faktiki mövcud olan malın ən azı bir öz, emal olunmamış fotoşəkli.
</p>
<p>
  4.2 İstifadə olunmuş mallarda istifadə izləri təsvir edilməlidir. Çatışmayan və ya şişirdilmiş
  məlumatların nəticələri Satıcının üzərinə düşür.
</p>
<p>
  4.3 Hər yeni və ya dəyişdirilmiş elan aktivləşdirilməzdən əvvəl yoxlanılır. Yoxlama inandırıcılığı,
  şəkil keyfiyyətini və qiyməti əhatə edir; Operator mənşəyi təsdiq edən sənədlər (alış sənədləri)
  tələb edə bilər. Aktivləşdirməyə dair hüquq yoxdur.
</p>
<p>
  4.4 Satıcı alış sənədlərini ən azı elanın müddəti üstəgəl iki il saxlayır və tələb olunduqda beş iş
  günü ərzində təqdim edir.
</p>
<p>
  4.5 Satıcı təklif olunan malların mövcudluğunu təmin edir. Satışdan sonra təkrar çatdırılmama
  Operatora hesabı bloklamaq hüququ verir.
</p>

<h2 id="v5">5. Qadağan olunmuş mallar</h2>
<p>Xüsusilə aşağıdakıları təklif etmək olmaz:</p>
<ul>
  <li>saxtalar, replikalar, «dupe»-lar və markalanması silinmiş və ya dəyişdirilmiş mallar;</li>
  <li>mənşəyi izlənilə bilməyən və ya qanunsuz mənbədən olan mallar;</li>
  <li>ilk dəfə AİZ-dən kənarda dövriyyəyə buraxılmış mallar — əmtəə nişanı sahibi AİZ-də təkrar satışa
      razılıq vermədikdə;</li>
  <li>satış üçün nəzərdə tutulmamış nümunələr («not for resale») və təkrar satış qadağası olan
      işçi malları;</li>
  <li>məhsul təhlükəsizliyi, markalanma və ya tekstil markalanması qaydalarını pozan mallar;</li>
  <li>tələb olunan sənədlər (CITES) olmadan xəz və ekzotik dəri.</li>
</ul>
<p>
  Pozuntular elanın dərhal silinməsinə və bir qayda olaraq satıcı hesabına xitam verilməsinə səbəb olur.
</p>

<h2 id="v6">6. Komissiya</h2>
<p>6.1 Platforma vasitəsilə həyata keçirilən hər satışdan komissiya tutulur:</p>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Satıcı növü</th><th>Komissiya</th><th>Premium Outlet (Vault)</th></tr></thead>
  <tbody>
    <tr><td>Tacir</td><td><?= h($feeB) ?> % + satılan hər məhsul üçün <?= h($fixed) ?></td><td><?= h($feeV) ?> %</td></tr>
    <tr><td>Fərdi satıcı</td><td><?= h($feeP) ?> % + satılan hər məhsul üçün <?= h($fixed) ?></td><td><?= h($feeV) ?> %</td></tr>
  </tbody>
</table></div>
<p>
  6.2 Hesablama bazası Satıcının mallarının ümumi (brutto) satış qiymətidir. Çatdırılma xərcləri
  hesablama bazasına daxil deyil və daşıyıcı qarşısında çatdırılma xərclərini öz üzərinə götürən
  Operatorda qalır.
</p>
<p>
  6.3 Yerləşdirmə, elan və ya aylıq ödənişlər yoxdur.
</p>
<p>
  6.4 Komissiya alıcı ödəniş etdikdə avtomatik tutulur. Əməliyyat tam ləğv edildikdə (müqavilədən
  imtina, ləğv, çatdırılmama) komissiya geri qaytarılır; ləğvə Satıcı səbəb olduqda 6.1-ci bənddəki
  sabit məbləğ istisnadır.
</p>
<p>
  6.5 Komissiyadakı dəyişikliklər ən azı 30 gün əvvəl e-poçtla elan edilir. Satıcı qüvvəyə minməzdən
  əvvəl etiraz etməzsə, yeni komissiya razılaşdırılmış sayılır; 12-ci bənd üzrə xitam hüququ qüvvədə
  qalır.
</p>

<h2 id="v7">7. Ödəniş və köçürmə</h2>
<p>
  7.1 Ödənişlər Stripe vasitəsilə emal olunur. Bunun üçün Satıcı Stripe ilə öz müqaviləsini (Stripe
  Connected Account Agreement) bağlayır və onun şərtlərini qəbul edir.
</p>
<p>
  7.2 Sifarişin tərkibindən asılı olaraq ödəniş ya birbaşa Satıcının hesabına (yönləndirmə ilə ödəniş)
  daxil olur, ya da əvvəlcə platformanın hesabına yığılır və sonra ayrıca köçürmə ilə Satıcıya
  ötürülür. Hər iki halda Satıcı öz mallarının ümumi satış qiymətini komissiya çıxılmaqla alır.
</p>
<p>
  7.3 Bank hesabına köçürmələrin tezliyi Stripe qaydalarına əsaslanır. Operator müştəri vəsaitlərini
  saxlamır və faiz ödəmir.
</p>
<p>
  7.4 Stripe yoxlaması hələ tamamlanmayıbsa, Satıcının payı yoxlama tamamlanana qədər platformanın
  hesabında qalır. Yoxlama 180 gün ərzində tamamlanmazsa, Operator müvafiq sifarişləri ləğv edib
  alıcılara pulu qaytara bilər.
</p>
<p>
  7.5 Satıcıya qarşı əsaslı tələblər olduğu həddə — xüsusilə alıcılara qaytarmalar, çarcbeklər və ya
  5-ci bəndin pozulması ilə bağlı — Operator köçürmələri saxlaya və ya əvəzləşdirə bilər. Saxlama
  əsaslandırılır və tələblərin məbləği ilə məhdudlaşır.
</p>

<h2 id="v8">8. Çatdırılma və qaytarma</h2>
<p>
  8.1 Satıcı malı ödəniş daxil olduqdan sonra iki iş günü ərzində sığortalanmış və izlənilə bilən
  göndərişlə yola salır və göndəriş məlumatlarını gecikmədən daxil edir.
</p>
<p>
  8.2 Satıcı qaytarmaları göstərdiyi ünvanda qəbul edir. Tacirlər müqavilədən imtina halında pulu
  müddətində qaytarır; qaytarılmazsa, Operator alıcıya pulu qaytarmaq və məbləği Satıcıya ediləcək
  köçürmələrdən əvəzləşdirmək hüququna malikdir.
</p>
<p>
  8.3 Fərdi satıcılarda qanunla müəyyən edilmiş müqavilədən imtina hüququ yoxdur. Lakin mal təsvirdən
  əhəmiyyətli dərəcədə fərqlənirsə və ya orijinal deyilsə, Satıcı onu geri götürməyə və pulu
  qaytarmağa borcludur.
</p>

<h2 id="v9">9. Premium Outlet</h2>
<p>
  9.1 Satıcı malları Vault üçün təqdim edə bilər. Başlanğıc qiymət, minimum qiymət və endirim cədvəli
  açılışdan əvvəl razılaşdırılır və sonradan dəyişdirilmir.
</p>
<p>
  9.2 Satıcı satışın başlanğıc və minimum qiymət arasındakı istənilən qiymətə baş tuta biləcəyini və
  lot açıldıqdan sonra lot davam etdiyi müddətdə iştirakdan imtinanın mümkün olmadığını qəbul edir.
</p>
<p>
  9.3 Minimum qiymətdən aşağı heç vaxt düşülmür. Lot müddətin sonuna qədər satılmazsa, bağlanır və
  yenidən adi qaydada təklif oluna bilər.
</p>

<h2 id="v10">10. Digital Services Act üzrə öhdəliklər</h2>
<p>
  10.1 Tacir satıcılar DSA-nın 30-cu maddəsində tələb olunan məlumatları təqdim edir: ad, ünvan,
  telefon nömrəsi, e-poçt ünvanı, ticarət reyestri nömrəsi və ya analoji identifikator, mövcud olduqda
  ƏDV nömrəsi. Operator bu məlumatları ağlabatan vasitələrlə yoxlayır və aydınlaşdırılana qədər elanı
  oflayn edə bilər.
</p>
<p>
  10.2 Satıcı elanlarının məhsul təhlükəsizliyi və markalanma qaydalarına uyğun olduğuna və lazımi
  icazələrə malik olduğuna zəmanət verir.
</p>
<p>
  10.3 Qanunsuz məzmun barədə bildirişlər DSA-nın 16-cı maddəsinə uyğun baxılır. Təsirlənən Satıcılara
  silinmə barədə səbəbləri ilə məlumat verilir və onlar etiraz edə bilərlər
  (<a href="<?= h(vr_url('legal/streitbeilegung.php')) ?>"><?= te('legal_disputes') ?></a>).
</p>

<h2 id="v11">11. Məzmuna dair hüquqlar</h2>
<p>
  11.1 Satıcı Operatora yerləşdirilən mətn və şəkillərdən platformanın fəaliyyəti və tanıdılması üçün
  istifadə etmək, onları emal etmək (kəsmə, rəng korreksiyası, ölçünün dəyişdirilməsi) və çoxaltmaq
  üçün sadə (qeyri-müstəsna), ərazi baxımından məhdudlaşdırılmayan və əvəzsiz hüquq verir.
</p>
<p>
  11.2 İstifadə hüququ başa çatmış satışların sənədləşdirilməsinə xidmət etdiyi həddə elanın sona
  çatmasından sonra da qüvvədə qalır.
</p>
<p>
  11.3 Satıcı yerləşdirdiyi məzmuna dair bütün lazımi hüquqlara malik olduğuna və üçüncü şəxslərin
  hüquqlarını pozmadığına zəmanət verir.
</p>

<h2 id="v12">12. Bloklama və xitam</h2>
<p>
  12.1 Hər tərəf satıcı münasibətinə istənilən vaxt yazılı (mətn) formada 14 gün əvvəl xəbərdarlıq
  etməklə xitam verə bilər. Davam edən sifarişlər tam icra olunmalıdır.
</p>
<p>
  12.2 5-ci bənd pozulduqda, satıcı statusu barədə yanlış məlumat verildikdə, təkrar çatdırılmama
  olduqda və ya saxtakarlığa dair əsaslı şübhə yarandıqda Operator elanları dərhal silə və hesabı
  bloklaya bilər. Bloklama əsaslandırılır.
</p>
<p>
  12.3 Xitamdan sonra çatacaq köçürmələr qaytarma və çarcbek müddətləri bitdikdən sonra, lakin son
  sifarişdən ən geci 90 gün sonra həyata keçirilir.
</p>

<h2 id="v13">13. Məsuliyyət və iddialardan azad etmə</h2>
<p>
  13.1 Operator qəsd və kobud ehtiyatsızlıq, həyata, bədənə və ya sağlamlığa zərər vurulması, eləcə də
  zəmanət verildiyi hallarda məhdudiyyətsiz məsuliyyət daşıyır. Əsas müqavilə öhdəliklərinin yüngül
  ehtiyatsızlıqla pozulması halında məsuliyyət müqavilə üçün tipik olan, əvvəlcədən görülə bilən
  zərərlə məhdudlaşır; qalan hallarda istisna edilir.
</p>
<p>
  13.2 Satıcı bu şərtlərin pozulmasına əsaslanan üçüncü şəxslərin iddialarından — xüsusilə əmtəə
  nişanı, müəllif hüququ, rəqabət hüququ və istehlakçı hüquqlarının müdafiəsi sahəsində — Operatoru
  azad edir. Azad etmə hüquqi müdafiənin ağlabatan xərclərini də əhatə edir.
</p>
<p>
  13.3 Dövriyyə və ya uğur zəmanəti verilmir. Elanların görünməsi, yerləşdirilməsi və sıralanması
  Operator tərəfindən müəyyən edilir.
</p>

<h2 id="v14">14. Yekun müddəalar</h2>
<p>
  14.1 Almaniya hüququ tətbiq olunur. Satıcı tacirdirsə, qanunla icazə verildiyi həddə məhkəmə
  aidiyyəti yeri Operatorun olduğu yerdir.
</p>
<p>
  14.2 Bu şərtlərdəki dəyişikliklər ən azı 30 gün əvvəl e-poçtla bildirilir. Hazırkı versiya:
  <?= h(VR_TERMS_VERSION) ?>.
</p>
<p>
  14.3 Bütün satıcı məsələləri üzrə əlaqə:
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-mail]</em>' ?>.
</p>

<div class="doc__box">
  <strong>Bu tərcümə haqqında</strong>
  <p style="margin-top:8px">
    Bu Azərbaycan dilindəki mətn alman dilindəki Satıcı şərtlərinin məlumat xarakterli tərcüməsidir;
    hüquqi qüvvəyə yalnız alman mətni malikdir və Siz onu qeydiyyat zamanı qəbul edirsiniz.
    Uyğunsuzluq olduqda alman versiyası üstünlük təşkil edir. Bu, hüquqi məsləhət deyil.
  </p>
</div>
