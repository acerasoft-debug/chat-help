/* SARVESTO — ilk boyamadan önce çalışan küçük betik (head, eşzamanlı).
   Yalnız iki iş yapar, ikisi de ilk karede belli olmak zorunda:
     1) data-platform: app / ios / android / desktop. Uygulamaya özel görünüm
        (alt sekme çubuğu, gizlenen başlık) sayfa açıldıktan sonra yerine
        oturursa ekran zıplıyor; burada belirlenince ilk karede doğru.
     2) Uygulama açılışı: oturumdaki ilk sayfada açılış ekranındaki SARVESTO
        yazısı harf aralığını açarak dağılır ve sayfa altından belirir.
        Saf CSS animasyonu; sınıf takılı kalsa bile animasyon sonunda katman
        görünmez ve tıklamaları hiçbir zaman engellemez.
   Kalan her şey maxsales.js'te (defer). */
(function () {
  var root = document.documentElement;
  var ua = navigator.userAgent || '';
  var app = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) ||
            window.navigator.standalone === true || /SarvestoApp\//.test(ua);
  var isIOS = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  root.setAttribute('data-platform', app ? 'app' : isIOS ? 'ios' : /Android/i.test(ua) ? 'android' : 'desktop');
  // Hareket dili (maxsales.css "HAREKET DİLİ"): içerik ilk boyamadan gizli
  // başlar, maxsales.js görünüme girdikçe açar. O betik 2,5 sn içinde
  // devreye girmezse (hata, engelleme) gizleme geri alınır.
  if (!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) && 'IntersectionObserver' in window) {
    root.classList.add('rv');
    setTimeout(function () { if (!root.classList.contains('rv-live')) root.classList.remove('rv'); }, 2500);
  }
  if (!app) return;
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  var seen = '1';
  try { seen = sessionStorage.getItem('sv-intro'); sessionStorage.setItem('sv-intro', '1'); } catch (e) {}
  if (seen) return;
  root.classList.add('sv-intro');
  // Kalıcı işaret: ana sayfa sahnesinin açılışı perde inerken boşa oynamasın.
  root.classList.add('sv-intro-seq');
  setTimeout(function () { root.classList.remove('sv-intro'); }, 1900);
})();
