<?php
/**
 * Qaytarma — Azərbaycan dili (məlumat xarakterli tərcümə). Dəyişənlər: legal/rueckgabe.php.
 */
?>
<h2>Bir baxışda</h2>
<div class="tablewrap" tabindex="0"><table>
  <thead><tr><th>Satıcı</th><th>Müddət</th><th>Geri göndərmə xərcləri</th></tr></thead>
  <tbody>
    <tr><td><?= h((string)vr_config('brand')) ?> anbarı</td>
        <td><?= (int)$days ?> gün (qanuni <?= (int)$wd ?> + könüllü uzadılma)</td>
        <td>qanuni müddət ərzində Almaniyadan pulsuz</td></tr>
    <tr><td>Tacir</td>
        <td><?= (int)$wd ?> gün qanuni imtina; bir çox tacir daha çox verir</td>
        <td>qanuni müddət ərzində Almaniyadan pulsuz</td></tr>
    <tr><td>Fərdi satıcı</td>
        <td>qanuni imtina hüququ yoxdur</td>
        <td>geri alma yalnız təsvirdən fərqlilik olduqda</td></tr>
  </tbody>
</table></div>

<h2>Necə qaytarmalı</h2>
<ol>
  <li>Sifariş nömrəniz və qaytarmaq istədiyiniz mallarla
      <?= $email !== '' ? '<a href="mailto:' . h($email) . '">' . h($email) . '</a>' : '<em>[e-poçt]</em>' ?>
      ünvanına yazın.</li>
  <li>Bir iş günü ərzində qaytarma etiketi və müvafiq satıcının qaytarma ünvanını alırsınız.</li>
  <li>Malı mümkünsə orijinal qutuda qablaşdırın, çatdırılma qaiməsini əlavə edin və bağlamanı
      təhvil verin.</li>
  <li>Alındıqdan və yoxlanıldıqdan sonra eyni ödəniş vasitəsinə geri qaytarırıq — imtina
      bəyanatınızın alınmasından ən geci 14 gün sonra, mal bizdə olan və ya göndərişi sübut etdiyiniz
      kimi.</li>
</ol>
<p>
  Əvvəlcədən xəbər verilmədən qaytarmaları da qəbul edirik; bu halda emal daha uzun çəkir, çünki
  uyğunlaşdırma əl ilə aparılır.
</p>

<h2>Qaytarılan malın vəziyyəti</h2>
<p>
  Geyinib yoxlamaq açıq şəkildə qaydasındadır — qaytarma hüququ məhz bunun üçündür. Zəhmət olmasa
  malları geyinilməmiş, yuyulmamış, ətirsiz və bütün orijinal etiketləri ilə geri göndərin. Bundan
  kənara çıxan istifadə nəticəsində dəyər itkisi üçün kompensasiya tələb edə bilərik; bunu şəffaf
  hesablayır və əvvəlcədən sizinlə əlaqə saxlayırıq.
</p>

<h2>Nə geri alına bilməz</h2>
<ul>
  <li>gigiyena möhürü salamat olmayan çimərlik geyimi və sırğalar;</li>
  <li>fərdi uyğunlaşdırılmış və ya göstərişlərinizə görə hazırlanmış mallar;</li>
  <li>fərdi satıcıların malları — mal təsvirə uyğundursa.</li>
</ul>

<h2>Dəyişdirmə</h2>
<p>
  Birbaşa dəyişdirmə mümkün deyil, çünki malların əksəriyyəti tək nüsxə və ya tək ölçülərdir. Malı
  geri göndərin və uyğun ölçünü yenidən sifariş edin — hələ mövcuddursa. Müəyyən ölçü sizi
  maraqlandırırsa, bizə yazın; əlavə gəlişin gözlənilib-gözlənilmədiyini deyərik.
</p>

<h2>Mal qüsurludur və ya təsvir edildiyi kimi deyil</h2>
<p>
  Bu halda qaytarma deyil, qüsur məsuliyyəti tətbiq olunur — sizin üçün daha güclü hüquqlarla.
  Fotolarla bizimlə əlaqə saxlayın; bu halda qaytarma həmişə pulsuzdur, fərdi satıcılarda da və
  qaytarma müddəti bitdikdən sonra da, qanuni iddia müddəti çərçivəsində.
</p>

<h2>Orijinal olmayan mal</h2>
<p>
  Malın orijinal olmadığı aşkar olunarsa, çatdırılma xərcləri daxil olmaqla alış qiymətini tam geri
  qaytarır və geri göndərməni öz üzərimizə götürürük — hansı satıcının təklif etdiyindən asılı
  olmayaraq. Müvafiq satıcı platformadan çıxarılır.
</p>

<h2>Premium Outlet</h2>
<p>
  Endirimli qiymətlər hüquqlarınızda heç nəyi dəyişmir: Vault alışları üçün yuxarıdakı eyni
  müddətlər qüvvədədir. Yalnız fərdi satıcılar üçün istisna qalır.
</p>

<p class="doc__related">
  Nümunəvi imtina forması ilə hüquqi mətn:
  <a href="<?= h(vr_url('legal/widerruf.php')) ?>"><?= te('legal_withdrawal') ?></a>.
</p>
