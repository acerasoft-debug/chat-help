/* MAXSALES — vitrin etkileşimleri
   ------------------------------------------------------------------
   Kural: JavaScript kapalıyken de site TAM çalışır. Buradaki her şey
   üstüne eklenen cila — gezinme, filtre, sepet ve ödeme saf HTML form
   gönderimiyle işliyor. Bu yüzden tek dosya, çerçeve yok, izleme yok.
*/
(function () {
  'use strict';

  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------------------------------------------- sticky başlık gölgesi */
  var header = document.querySelector('[data-header]');
  if (header) {
    var onScroll = function () {
      header.classList.toggle('is-stuck', window.scrollY > 8);
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  /* ------------------------------------------------------- mobil çekmece */
  var drawer = document.querySelector('[data-drawer]');
  if (drawer) {
    var opener = document.querySelector('[data-drawer-open]');
    var lastFocus = null;

    var setDrawer = function (open) {
      drawer.hidden = !open;
      if (opener) opener.setAttribute('aria-expanded', open ? 'true' : 'false');
      document.body.style.overflow = open ? 'hidden' : '';
      if (open) {
        lastFocus = document.activeElement;
        var first = drawer.querySelector('a, button');
        if (first) first.focus();
      } else if (lastFocus) {
        lastFocus.focus();
      }
    };

    if (opener) opener.addEventListener('click', function () { setDrawer(true); });
    drawer.addEventListener('click', function (e) {
      // Panelin dışına ya da kapatma düğmesine tıklandıysa kapat.
      if (e.target === drawer || e.target.closest('[data-drawer-close]')) setDrawer(false);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !drawer.hidden) setDrawer(false);
    });
  }

  /* ------------------------------------------------------ scroll ile beliriş */
  var reveals = document.querySelectorAll('[data-reveal]');
  if (!reveals.length) { /* yok */ }
  else if (reduced || !('IntersectionObserver' in window)) {
    reveals.forEach(function (el) { el.classList.add('is-in'); });
  } else {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        // Aynı sıradaki kartlar hafif kademeli girsin — sıra hissi verir.
        var sibs = Array.prototype.indexOf.call(en.target.parentNode.children, en.target);
        en.target.style.transitionDelay = Math.min(sibs % 4, 3) * 70 + 'ms';
        en.target.classList.add('is-in');
        io.unobserve(en.target);
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });
    reveals.forEach(function (el) { io.observe(el); });

    // Emniyet zamanlayicisi: gozlemci herhangi bir nedenle tetiklenmezse
    // (ekran goruntusu araclari, arka plan sekmesi, garip kaydirma kaplari)
    // icerik gizli kalmasin -- 2 saniye sonra ne kaldiysa gorunur yapilir.
    window.setTimeout(function () {
      reveals.forEach(function (el) { el.classList.add('is-in'); });
    }, 2000);
  }

  /* --------------------------------------------------- Vault geri sayımları
     Sunucudan gelen mutlak zaman damgasına göre sayar. Süre bittiğinde
     fiyatı tarayıcıda HESAPLAMIYORUZ — sayfayı bir kez tazeliyoruz, yeni
     fiyatı yine sunucu söylüyor. Tek doğruluk kaynağı sunucu kalsın.        */
  var cds = document.querySelectorAll('[data-countdown]');
  if (cds.length) {
    var pad = function (n) { return (n < 10 ? '0' : '') + n; };
    var reloadAt = 0;

    var tick = function () {
      var now = Date.now() / 1000;
      cds.forEach(function (el) {
        var target = parseInt(el.getAttribute('data-countdown'), 10) || 0;
        var d = Math.max(0, Math.floor(target - now));

        if (d === 0) {
          el.textContent = '00:00:00';
          // Aynı saniyede onlarca yeniden yükleme olmasın: bir kez planla.
          if (!reloadAt) {
            reloadAt = 1;
            setTimeout(function () { window.location.reload(); }, 2500);
          }
          return;
        }
        var days = Math.floor(d / 86400);
        var h = Math.floor((d % 86400) / 3600);
        var m = Math.floor((d % 3600) / 60);
        var s = d % 60;
        el.textContent = (days > 0 ? days + 'd ' : '') + pad(h) + ':' + pad(m) + ':' + pad(s);
      });
    };
    tick();
    setInterval(tick, 1000);
  }

  /* -------------------------------------------------------- adet artır/azalt */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-qty]');
    if (!btn) return;
    e.preventDefault();

    var form = btn.closest('form');
    var input = form && form.querySelector('input[name="qty"]');
    if (!input) return;

    var step = btn.getAttribute('data-qty') === 'up' ? 1 : -1;
    var min = parseInt(input.getAttribute('min') || '0', 10);
    var max = parseInt(input.getAttribute('max') || '99', 10);
    var next = Math.min(max, Math.max(min, (parseInt(input.value, 10) || 1) + step));

    input.value = next;
    form.requestSubmit ? form.requestSubmit() : form.submit();
  });

  /* ------------------------------------------------- beden seçilmeden ekleme */
  document.addEventListener('submit', function (e) {
    var form = e.target.closest('[data-needs-size]');
    if (!form) return;
    if (form.querySelector('input[name="size"]:checked')) return;

    e.preventDefault();
    var box = form.querySelector('[data-size-error]');
    if (box) {
      box.hidden = false;
      box.setAttribute('role', 'alert');
    }
    var first = form.querySelector('.sizes input:not(:disabled)');
    if (first) first.focus();
  });

  /* ------------------------------------------- filtre seçimini anında uygula */
  document.addEventListener('change', function (e) {
    var sel = e.target.closest('[data-autosubmit]');
    if (!sel) return;
    var form = sel.closest('form');
    if (form) form.requestSubmit ? form.requestSubmit() : form.submit();
  });

  /* ---------------------------------------- ürün görselleri: ilk açılış efekti */
  document.querySelectorAll('.pdp__shot img, .card__media img').forEach(function (img) {
    if (img.complete) return;
    img.style.opacity = '0';
    img.addEventListener('load', function () {
      img.style.transition = 'opacity .6s ease';
      img.style.opacity = '1';
    }, { once: true });
    // Yükleme hata verirse görsel gizli kalmasın.
    img.addEventListener('error', function () { img.style.opacity = '1'; }, { once: true });
  });
})();
