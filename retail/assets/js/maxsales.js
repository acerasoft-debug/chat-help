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

  /* ------------------------------------------------------ galeri büyüteci
     Her görsel zaten kendi dosyasına giden bir bağlantı; JS kapalıyken
     tarayıcı görseli tam boy açar. Burada o bağlantıyı yakalayıp katmanda
     gösteriyoruz — klavyeyle (Esc, ←, →) de gezilebiliyor. */
  var zoomLinks = Array.prototype.slice.call(document.querySelectorAll('[data-zoom]'));
  if (zoomLinks.length) {
    var box = null, idx = 0, opener = null;

    var render = function () {
      var link = zoomLinks[idx];
      box.querySelector('img').src = link.getAttribute('href');
      box.querySelector('img').alt = (link.querySelector('img') || {}).alt || '';
      var counter = box.querySelector('.lightbox__n');
      counter.textContent = zoomLinks.length > 1 ? (idx + 1) + ' / ' + zoomLinks.length : '';
      box.querySelectorAll('.lightbox__nav').forEach(function (b) {
        b.hidden = zoomLinks.length < 2;
      });
    };

    var close = function () {
      box.hidden = true;
      document.body.style.overflow = '';
      if (opener) opener.focus();
    };

    var build = function () {
      box = document.createElement('div');
      box.className = 'lightbox';
      box.hidden = true;
      box.setAttribute('role', 'dialog');
      box.setAttribute('aria-modal', 'true');
      box.innerHTML =
        '<button class="lightbox__x" type="button" aria-label="Close">&times;</button>' +
        '<button class="lightbox__nav lightbox__nav--prev" type="button" aria-label="Previous">&#8249;</button>' +
        '<img alt="">' +
        '<button class="lightbox__nav lightbox__nav--next" type="button" aria-label="Next">&#8250;</button>' +
        '<span class="lightbox__n"></span>';
      document.body.appendChild(box);

      box.addEventListener('click', function (e) {
        if (e.target === box || e.target.closest('.lightbox__x')) { close(); return; }
        if (e.target.closest('.lightbox__nav--prev')) { idx = (idx - 1 + zoomLinks.length) % zoomLinks.length; render(); }
        if (e.target.closest('.lightbox__nav--next')) { idx = (idx + 1) % zoomLinks.length; render(); }
      });
      document.addEventListener('keydown', function (e) {
        if (box.hidden) return;
        if (e.key === 'Escape') close();
        if (e.key === 'ArrowLeft')  { idx = (idx - 1 + zoomLinks.length) % zoomLinks.length; render(); }
        if (e.key === 'ArrowRight') { idx = (idx + 1) % zoomLinks.length; render(); }
      });
    };

    zoomLinks.forEach(function (link, i) {
      link.addEventListener('click', function (e) {
        // Yeni sekmede açmak isteyeni engellemeyelim.
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
        e.preventDefault();
        if (!box) build();
        idx = i; opener = link;
        render();
        box.hidden = false;
        document.body.style.overflow = 'hidden';
        box.querySelector('.lightbox__x').focus();
      });
    });
  }

  /* -------------------------------------------------- Merkliste: sayfasız
     Form zaten çalışıyor (POST + yönlendirme). JS varsa aynı isteği arka
     planda yollayıp sadece kalbi güncelliyoruz — sayfa sıçramasın. */
  document.addEventListener('submit', function (e) {
    var form = e.target.closest('[data-wish]');
    if (!form || !window.fetch) return;
    e.preventDefault();

    var btn = form.querySelector('.wish');
    fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      headers: { 'X-Requested-With': 'fetch' },
      credentials: 'same-origin'
    }).then(function (r) { return r.ok ? r.json() : null; }).then(function (d) {
      if (!d) { form.submit(); return; }          // beklenmedik cevap → normal gönderim
      btn.classList.toggle('is-on', !!d.saved);
      btn.setAttribute('aria-pressed', d.saved ? 'true' : 'false');
      var label = btn.querySelector('span');
      if (label && d.label) label.textContent = d.label;
      var counter = document.querySelector('.tool--wish .bag__n');
      if (d.count > 0) {
        if (!counter) {
          counter = document.createElement('i');
          counter.className = 'bag__n';
          document.querySelector('.tool--wish').appendChild(counter);
        }
        counter.textContent = d.count;
      } else if (counter) {
        counter.remove();
      }
    }).catch(function () { form.submit(); });
  });

  /* ------------------------------------------------------- arama önerileri */
  var searchForm = document.querySelector('.search');
  var searchInput = searchForm && searchForm.querySelector('input[name="q"]');
  if (searchInput && window.fetch) {
    var panel = document.createElement('div');
    panel.className = 'suggest';
    panel.hidden = true;
    panel.setAttribute('role', 'listbox');
    searchForm.appendChild(panel);

    var timer = null, cur = -1;

    var hide = function () { panel.hidden = true; cur = -1; };

    var run = function () {
      var q = searchInput.value.trim();
      if (q.length < 2) { hide(); return; }

      fetch(searchForm.getAttribute('action').split('?')[0].replace(/shop\.php$/, 'api/suggest.php')
            + '?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) {
          if (!d || !d.items || !d.items.length) { hide(); return; }
          panel.innerHTML = d.items.map(function (it) {
            return '<a href="' + it.url + '" role="option">' +
                   '<img src="' + it.img + '" alt="" loading="lazy">' +
                   '<span><span class="suggest__b">' + it.brand + '</span>' +
                   '<span class="suggest__t">' + it.name + '</span></span>' +
                   '<span class="suggest__p">' + it.price + '</span></a>';
          }).join('');
          panel.hidden = false;
          cur = -1;
        }).catch(hide);
    };

    searchInput.addEventListener('input', function () {
      window.clearTimeout(timer);
      timer = window.setTimeout(run, 220);         // yazarken her tuşta istek atmayalım
    });
    searchInput.addEventListener('keydown', function (e) {
      var items = panel.querySelectorAll('a');
      if (panel.hidden || !items.length) return;
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        items[Math.max(0, cur)].classList.remove('is-cur');
        cur = e.key === 'ArrowDown' ? (cur + 1) % items.length : (cur - 1 + items.length) % items.length;
        items[cur].classList.add('is-cur');
        items[cur].scrollIntoView({ block: 'nearest' });
      } else if (e.key === 'Enter' && cur >= 0) {
        e.preventDefault();
        window.location.href = items[cur].getAttribute('href');
      } else if (e.key === 'Escape') {
        hide();
      }
    });
    document.addEventListener('click', function (e) {
      if (!searchForm.contains(e.target)) hide();
    });
  }
})();
