<?php
/**
 * Rekvizitlər (§ 5 DDG, § 18 MStV) — Azərbaycan dili (məlumat xarakterli tərcümə). Dəyişənlər: legal/impressum.php.
 */
?>
<h2>Təminatçı</h2>
<?php vr_company_block(); ?>

<h2>Bazar meydanının operatoru</h2>
<p>
  <?= h((string)vr_config('brand')) ?> — <?= h((string)($c['legal_name'] ?? '')) ?> tərəfindən idarə
  olunan onlayn bazar meydanıdır. Platforma vasitəsilə həm operatorun öz malları, həm də üçüncü
  tərəflərin (kommersiya tacirləri və fərdi satıcılar) malları təklif olunur. Alqı-satqı
  müqaviləsinin tərəfinin kim olduğu hər məhsul səhifəsində və sifariş zamanı, sifariş verilməzdən
  əvvəl göstərilir.
</p>

<h2>Məzmuna görə məsul</h2>
<p>
  § 18 bənd 2 MStV-yə əsasən məsul şəxs yuxarıda göstərilən təmsil səlahiyyətli şəxsdir, ünvan
  yuxarıdakı kimidir.
</p>

<h2>İstehlakçı sorğuları üçün əlaqə</h2>
<p>
  Sifarişlər, qaytarmalar və şikayətlərlə bağlı sorğularınızı
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-poçt ünvanı]</em>' ?>
  ünvanına göndərin. Adətən bir iş günü ərzində cavab veririk. Üçüncü tərəf satıcının malı ilə bağlı
  suallarda sizi müvafiq satıcı ilə əlaqələndirir və ya sorğunuzu ötürürük.
</p>

<h2>İstehlakçı mübahisələrinin həlli</h2>
<p>
  İstehlakçı barışdırma orqanı qarşısında mübahisə həlli prosedurlarında iştirak etməyə nə
  borcluyuq, nə də hazırıq. Bu, bizimlə dostcasına razılaşmanı istisna etmir — zəhmət olmasa əvvəlcə
  birbaşa bizə müraciət edin. Əlavə məlumat:
  <a href="<?= h(vr_url('legal/streitbeilegung.php')) ?>"><?= te('legal_disputes') ?></a>.
</p>

<h2>Ödəniş xidməti təminatçısı</h2>
<p>
  Ödənişlər Stripe (Stripe Payments Europe, Limited, 1 Grand Canal Street Lower, Grand Canal Dock,
  Dublin, İrlandiya) vasitəsilə emal olunur. Üçüncü tərəf satıcılara ödənişlər Stripe Connect
  vasitəsilə həyata keçirilir. Kart məlumatları yalnız Stripe-da emal olunur və sistemlərimizə
  çatmır.
</p>

<h2>Məzmuna görə məsuliyyət</h2>
<p>
  Xidmət təminatçısı kimi bu səhifələrdəki öz məzmunumuza görə ümumi qanunlara əsasən məsuliyyət
  daşıyırıq. Üçüncü tərəf satıcıların təklifləri üçün ötürülmüş və ya saxlanılmış kənar məlumatı
  izləməyə yaxud qanunsuz fəaliyyətə işarə edən halları araşdırmağa borclu deyilik (Rəqəmsal
  Xidmətlər Aktının 6, 8-ci maddələri). Konkret hüquq pozuntusundan xəbərdar olan kimi müvafiq
  məzmunu dərhal silirik. Bildirişləri
  <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-poçt ünvanı]</em>' ?>
  ünvanına göndərin.
</p>
<p>
  Üçüncü tərəf satıcıların təkliflərini dərcdən əvvəl inandırıcılıq baxımından yoxlayır və mənşə
  sübutları tələb edirik. Bu könüllü yoxlama hər bir təklifin qanuniliyinə və ya orijinallığına
  zəmanət vermir və hostinq xidməti təminatçısı kimi məsuliyyət imtiyazımıza toxunmur.
</p>

<h2>Linklərə görə məsuliyyət</h2>
<p>
  Təklifimizdə məzmununa təsir edə bilmədiyimiz üçüncü tərəflərin xarici veb saytlarına linklər var.
  Bu məzmuna görə həmişə müvafiq təminatçı məsuliyyət daşıyır. Link verilən vaxt qanunsuz məzmun
  müəyyən edilməmişdi.
</p>

<h2>Müəllif hüququ</h2>
<p>
  Operatorun bu səhifələrdə yaratdığı məzmun və əsərlər müəllif hüququ ilə qorunur. Üçüncü tərəf
  satıcıların məhsul şəkilləri və təsvirləri onlar tərəfindən təqdim olunur; onlar zəruri hüquqlara
  malik olduqlarına bizə zəmanət verir. Marka və məhsul adları müvafiq hüquq sahiblərinin
  mülkiyyətidir. Onların qeyd edilməsi yalnız təklif olunan malın təsvirinə xidmət edir və marka
  sahibləri ilə ticarət əlaqəsi yaratmır.
</p>

<h2>Ticarət nişanı hüquqları barədə qeyd</h2>
<p>
  <?= h((string)vr_config('brand')) ?>, açıq şəkildə başqa cür göstərilmədikcə, adı çəkilən
  markaların səlahiyyətli dileri deyil. Təklif olunan mallar Avropa İqtisadi Zonası daxilində ilk
  dəfə dövriyyəyə buraxılmış orijinal mallardır; yenidən satış buna görə ticarət nişanı hüququnun
  tükənməsi prinsipinə əsasən icazəlidir (§ 24 MarkenG, EUTMR mad. 15).
</p>
