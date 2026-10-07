<?php
/**
 * Çatdırılma — Azərbaycan dili (məlumat xarakterli tərcümə).
 * $ship və $countries legal/versand.php-dən gəlir; etiketlər buradadır.
 */
$zones = [
    'de'    => 'Almaniya',
    'eu'    => 'Avropa İttifaqı',
    'ch'    => 'İsveçrə, Lixtenşteyn, Norveç, Birləşmiş Krallıq',
    'world' => 'Digər istiqamətlər',
];
?>
<h2>Çatdırılma xərcləri və müddətləri</h2>
<div class="tablewrap" tabindex="0"><table>
  <thead>
    <tr><th>İstiqamət</th><th>Çatdırılma</th><th>Pulsuz (bu məbləğdən)</th><th>Müddət</th></tr>
  </thead>
  <tbody>
  <?php foreach ($zones as $key => $label):
      $row = (array)($ship[$key] ?? []); ?>
    <tr>
      <td><?= h($label) ?></td>
      <td><?= h(vr_money((int)($row['cents'] ?? 0))) ?></td>
      <td><?= (int)($row['free_over'] ?? 0) > 0 ? h(vr_money((int)$row['free_over'])) : '—' ?></td>
      <td><?= h((string)($row['days'] ?? '')) ?> iş günü</td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<p>
  Bütün məbləğlər ƏDV daxil son qiymətlərdir. Sifarişinizə aid çatdırılma xərcləri çatdırılma
  ölkəsini seçən kimi səbətdə göstərilir və sifariş verilməzdən əvvəl sifariş zamanı bir daha dəqiq
  məbləğlə qeyd olunur.
</p>

<h3>Çatdırdığımız Aİ ölkələri</h3>
<p class="doc__list">
  <?= h(implode(' · ', vr_country_options($countries['eu']))) ?>
</p>

<h3>Digər istiqamətlər</h3>
<p class="doc__list">
  <?= h(implode(' · ', vr_country_options($countries['world']))) ?>
</p>
<p>
  Ölkəniz siyahıda yoxdursa, bizə yazın — çox şey fərdi həll oluna bilər.
</p>

<h2>Göndərişin başlanması</h2>
<p>
  Müddət ödənişin təsdiqi ilə başlayır. Kart, Apple Pay və Google Pay ilə ödənişdə bu adətən
  dərhal olur; SEPA birbaşa debet və Klarna-da bir–üç bank iş günü əlavə oluna bilər. Saat 13:00-a
  qədər təsdiqlənən sifarişlər adətən həmin iş günü yola düşür.
</p>

<h2>Bir neçə satıcı, bir neçə bağlama</h2>
<p>
  <?= h((string)vr_config('brand')) ?> bazar meydanıdır. Sifarişinizdə müxtəlif satıcıların malları
  varsa, hər satıcı ayrıca göndərir. Bu halda bir neçə bağlama və bir neçə izləmə linki alırsınız —
  lakin yalnız bir dəfə ödəyirsiniz və çatdırılma xərcləri yalnız bir dəfə tutulur.
</p>

<h2>İzləmə</h2>
<p>
  Hər göndəriş sığortalıdır və izləmə nömrəsi ilə göndərilir. Bağlama daşıyıcıya təhvil verilən kimi
  linki e-poçtla alırsınız. Cari vəziyyəti həmçinin istənilən vaxt sifariş nömrəsi və e-poçt
  ünvanı ilə <a href="<?= h(vr_url('order.php')) ?>"><?= te('order_status') ?></a> bölməsindən
  yoxlaya bilərsiniz.
</p>

<h2>Paket terminalları və fərqli çatdırılma ünvanı</h2>
<p>
  Almaniya daxilində DHL Packstation terminallarına çatdırırıq; Packstation-ı poçt nömrənizlə
  birlikdə çatdırılma ünvanı kimi göstərin. Beynəlxalq göndərişlər üçün küçə ünvanı tələb olunur.
</p>

<h2>Gömrük və idxal rüsumları</h2>
<p>
  Aİ daxilində gömrük rüsumu və ya idxal ödənişi yoxdur. İsveçrəyə, Norveçə, Birləşmiş Krallığa və
  ya Avropadan kənara çatdırılmalarda idxal ƏDV-si, gömrük rüsumu və daşıyıcının emal haqları tətbiq
  oluna bilər. Bunları alan tərəf ödəyir və bizə ödənilən qiymətə daxil deyil.
</p>

<h2>Çatdırılmamış göndərişlər</h2>
<p>
  Bağlama çatdırıla bilməyən kimi satıcıya geri qaytarılarsa, sizinlə yenidən göndərişi
  razılaşdırırıq. Çatdırıla bilməmə natamam və ya yanlış çatdırılma ünvanından irəli gəlirsə, yeni
  çatdırılma xərcləri tutulur.
</p>

<h2>Nəqliyyat zədələri</h2>
<p>
  Bağlama görünən zədə ilə gəlirsə, onu qəbul edin, zədəni fotolarla sənədləşdirin və 14 gün
  ərzində bizimlə əlaqə saxlayın. Belə halları imtina hüququndan asılı olmayaraq həll edirik — fərdi
  satıcılarda da. Qanuni hüquqlarınız bununla məhdudlaşdırılmır.
</p>

<p class="doc__related">
  Həmçinin bax: <a href="<?= h(vr_url('legal/rueckgabe.php')) ?>"><?= te('legal_returns') ?></a> və
  <a href="<?= h(vr_url('legal/widerruf.php')) ?>"><?= te('legal_withdrawal') ?></a>.
</p>
