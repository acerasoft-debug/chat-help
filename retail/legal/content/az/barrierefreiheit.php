<?php
/**
 * Əlçatanlıq bəyanatı (BFSG) — Azərbaycan dili (məlumat xarakterli tərcümə).
 * Dəyişənlər: legal/barrierefreiheit.php.
 */
?>
<h2>Məqsədimiz</h2>
<p>
  Bu mağazanın hər kəs üçün istifadəyə yararlı olmasını istəyirik — klaviatura ilə, ekran oxuyucu
  ilə, böyüdülmüş şriftlə, azaldılmış hərəkətlə. Əsas Almaniyanın əlçatanlığın gücləndirilməsi
  qanununun (BFSG) tələbləri və WCAG 2.1 AA səviyyəsinə istinad edən EN 301 549 standartıdır.
</p>

<h2>İcra vəziyyəti</h2>
<p>
  Qiymətləndirməmizə görə bu veb sayt WCAG 2.1 AA səviyyəsinə <strong>böyük ölçüdə
  uyğundur</strong>. Qiymətləndirmə xarici auditə deyil, daxili yoxlamaya əsaslanır.
</p>

<h3>Nə həyata keçirilib</h3>
<ul>
  <li><strong>JavaScript olmadan istifadə:</strong> naviqasiya, filtrlər, səbət, kassa və satıcı
      bölməsi sırf HTML formalarla tam işləyir. JavaScript yalnız rahatlıq verir (geri sayım, say
      dəyişdirici, yumşaq görünmələr).</li>
  <li><strong>Klaviatura ilə idarəetmə:</strong> bütün interaktiv elementlər görünən fokus
      çərçivəsi ilə əlçatandır; səhifənin əvvəlində «Məzmuna keç» linki var.</li>
  <li><strong>Kontrastlar:</strong> əsas səhifələrdəki hər mətn düyünü avtomatik ölçülüb (faktiki
      göstərilən rəng faktiki göstərilən fona qarşı, şəffaflıqlar daxil). Hamısı ən azı 4,5:1-ə,
      böyük şrift ən azı 3:1-ə çatır. Qaranlıq Vault bölməsinin öz boz dəyərləri var və vurğu
      rəngləri açıq fonda daha tünd mətn variantına malikdir.</li>
  <li><strong>Struktur:</strong> hər səhifədə bir H1, məntiqi başlıq səviyyələri, oriyentirlər
      (<code>header</code>, <code>nav</code>, <code>main</code>, <code>footer</code>), etiketlənmiş
      forma sahələri, əlaqələndirilmiş xəta mesajları.</li>
  <li><strong>Şəkillər:</strong> məhsul şəkilləri marka və məhsul adından alternativ mətn daşıyır;
      sırf dekorativ qrafika köməkçi texnologiyalardan gizlədilib.</li>
  <li><strong>Hərəkət:</strong> «Hərəkəti azalt» sistem parametri aktiv olduqda sürüşən yazı,
      görünmələr və keçidlər söndürülür.</li>
  <li><strong>Yaxınlaşdırma və kiçik ekranlar:</strong> tərtibat səhifə məzmununun üfüqi sürüşməsi
      olmadan 400 % yaxınlaşdırmaya qədər istifadəyə yararlı qalır.</li>
  <li><strong>Dil:</strong> səhifə dili HTML-də işarələnib və dil seçici ilə dəyişdirilə bilər (on
      dil).</li>
  <li><strong>Şəkil qalereyası:</strong> hər məhsul şəkli şəkil faylına adi linkdir. JavaScript
      olmadan birbaşa açılır; JavaScript ilə Esc ilə bağlanan və ox düymələri ilə vərəqlənən
      lightbox-da.</li>
  <li><strong>Axtarış təklifləri:</strong> ox düymələri və Enter ilə idarə olunur, Esc ilə bağlanır.
      Axtarış sahəsinin özü təkliflər olmadan da adi forma kimi işləyir.</li>
  <li><strong>İstək siyahısı:</strong> forma kimi həyata keçirilib; vəziyyət <code>aria-pressed</code>-də
      durur və JavaScript olmadan da düzgün saxlanılır.</li>
</ul>

<h3>Məlum məhdudiyyətlər</h3>
<ul>
  <li><strong>Üçüncü tərəf satıcıların məhsul şəkilləri:</strong> alternativ mətnlər marka və addan
      avtomatik yaradılır. Motivi ətraflı təsvir etmir — məhsul barədə suallarınız olarsa onu
      e-poçtla məmnuniyyətlə təsvir edirik.</li>
  <li><strong>Ödəniş səhifəsi:</strong> ödəniş Stripe-da aparılır. Bu səhifələrin əlçatanlığına
      görə Stripe məsuliyyət daşıyır; uyğunluq çatışmazlığı barədə məlumatımız yoxdur, lakin özümüz
      yoxlamamışıq.</li>
  <li><strong>Vault geri sayımı:</strong> qalan vaxt hər saniyə yenilənir. Müəyyənedici qiymət
      yanında mətn kimi durur və yalnız səhifə yükləndikdən sonra dəyişir, beləliklə ekran oxuyucu
      istifadəsi canlı dəyişikliklərlə pozulmur.</li>
  <li><strong>PDF sənədləri:</strong> fakturalar və qaytarma etiketləri qismən satıcılar və
      daşıyıcılar tərəfindən yaradılır və teqlənməmiş ola bilər. Tələb əsasında məzmunu əlçatan
      formada təqdim edirik.</li>
</ul>

<h2>Rəy və əlaqə</h2>
<p>
  Maneə ilə qarşılaşsanız, «Əlçatanlıq» mövzusu ilə <?= $mail ?> ünvanına yazın və mümkünsə səhifəni
  və köməkçi texnologiyanızı qeyd edin. Bir iş günü ərzində cavab veririk və aradan qaldırma tarixi
  bildiririk. Məlumat başqa formada lazımdırsa — daha böyük şrift, sadə mətn, telefonla oxunma —
  sadəcə deyin; pulsuz təqdim edirik.
</p>

<h2>İcra proseduru</h2>
<p>
  Cavabımız kömək etməzsə, federal torpaqların məhsul və xidmətlərin əlçatanlığı üzrə bazar nəzarəti
  orqanına (MDBD) müraciət edə bilərsiniz. Bu, BFSG-yə əsasən xidmətlərin əlçatanlığı barədə
  şikayətlər üzrə səlahiyyətli orqandır:
  <a href="https://www.marktueberwachungsstelle.de" rel="noopener">marktueberwachungsstelle.de</a>.
</p>

<h2>Bu bəyanatın hazırlanması</h2>
<p>
  Bu bəyanat <?= h(vr_date(strtotime('2026-08-01'))) ?> tarixində daxili özünüqiymətləndirmə
  əsasında hazırlanıb: klaviatura naviqasiyası, real brauzerdə bütün mətn düyünlərinin
  avtomatlaşdırılmış kontrast ölçümü, JavaScript söndürülmüş test, başlıq strukturu və forma
  etiketlərinin yoxlanması. Xarici audit aparılmayıb. Mağaza əhəmiyyətli dərəcədə dəyişdikdə
  bəyanatı yeniləyirik.
</p>
