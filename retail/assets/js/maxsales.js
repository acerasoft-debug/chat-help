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

  /* --------------------------------------------- hareket dili: beliriş
     boot.js html'e "rv" koydu; CSS bu öğeleri gizli tutuyor. Görünüme girenler
     okuma sırasıyla (yukarıdan aşağı, soldan sağa) 70 ms arayla açılıyor.
     Liste maxsales.css'teki "HAREKET DİLİ" seçicisiyle AYNI olmalı.         */
  var rootEl = document.documentElement;
  if (rootEl.classList.contains('rv')) {
    rootEl.classList.add('rv-live');
    var rvSel = 'main .sechead,main .chap,main .card,main .lot,main .jcard,main .panel,main .pdp__shot,main .pdp__head,main .notice,main .faq__q,.foot__top>*';
    var rvEls = document.querySelectorAll(rvSel);
    var rvShow = function (el, i) {
      el.style.setProperty('--rd', Math.min(i, 7) * 70 + 'ms');
      el.classList.add('rv-ing', 'is-in');
      window.setTimeout(function () { el.classList.remove('rv-ing'); el.style.removeProperty('--rd'); }, 1400 + Math.min(i, 7) * 70);
    };
    if (!('IntersectionObserver' in window)) {
      rootEl.classList.remove('rv');
    } else {
      var rvIo = new IntersectionObserver(function (entries) {
        var hits = entries.filter(function (en) { return en.isIntersecting; }).map(function (en) { return en.target; });
        hits.sort(function (a, b) {
          var ra = a.getBoundingClientRect(), rb = b.getBoundingClientRect();
          return (Math.round(ra.top / 40) - Math.round(rb.top / 40)) || (ra.left - rb.left);
        });
        hits.forEach(function (el, i) { rvShow(el, i); rvIo.unobserve(el); });
      }, { rootMargin: '0px 0px -6% 0px', threshold: 0.04 });
      rvEls.forEach(function (el) { rvIo.observe(el); });
    }
  }

  /* Sayaç değişince küçük atım (CSS .is-bump). */
  var bumpObs = window.MutationObserver && new MutationObserver(function (muts) {
    muts.forEach(function (m) {
      var el = m.target.nodeType === 3 ? m.target.parentNode : m.target;
      if (!el || !el.classList || !/bag__n|tabbar__n/.test(el.className)) return;
      el.classList.remove('is-bump'); void el.offsetWidth; el.classList.add('is-bump');
    });
  });
  if (bumpObs) document.querySelectorAll('.tools,.tabbar').forEach(function (n) {
    bumpObs.observe(n, { subtree: true, childList: true, characterData: true });
  });

  /* ------------------------------------------------------ sahne: rakamlar
     Ölçü şeridindeki rakamlar şerit sahneye girdiği anda sıfırdan sayar
     (CSS açılışı: şerit +1,55 sn'de geliyor). Sunucu asıl sayıyı zaten
     yazdı; JS yoksa ya da hareket azaltılmışsa o olduğu gibi kalır.         */
  var ups = document.querySelectorAll('.stage [data-countup]');
  if (ups.length && !reduced && window.requestAnimationFrame) {
    var late = document.documentElement.classList.contains('sv-intro-seq') ? 1050 : 0;
    ups.forEach(function (el) {
      var to = parseInt(el.getAttribute('data-countup'), 10) || 0;
      if (to < 2) return;
      el.textContent = '0';
      setTimeout(function () {
        var t0 = 0, dur = 1700;
        var step = function (ts) {
          if (!t0) t0 = ts;
          var k = Math.min(1, (ts - t0) / dur);
          var e = k === 1 ? 1 : 1 - Math.pow(2, -10 * k);   // easeOutExpo
          el.textContent = String(Math.round(to * e));
          if (k < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
      }, 1650 + late);
    });
  }

  /* ------------------------------------------------------ sahne: derinlik
     Masaüstünde imleç sahnede gezinirken kapak tersine, metin aynı yöne çok
     az kayar (CSS --mx/--my). Dokunmatikte ve hareket azaltılmışsa yok.      */
  var stageEl = document.querySelector('.stage');
  if (stageEl && !reduced && window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine) and (min-width: 901px)').matches) {
    var raf = 0, mx = 0, my = 0;
    stageEl.addEventListener('mousemove', function (ev) {
      var r = stageEl.getBoundingClientRect();
      mx = ((ev.clientX - r.left) / r.width) * 2 - 1;
      my = ((ev.clientY - r.top) / r.height) * 2 - 1;
      if (raf) return;
      raf = requestAnimationFrame(function () {
        raf = 0;
        stageEl.style.setProperty('--mx', mx.toFixed(3));
        stageEl.style.setProperty('--my', my.toFixed(3));
      });
    });
    stageEl.addEventListener('mouseleave', function () {
      stageEl.style.setProperty('--mx', '0');
      stageEl.style.setProperty('--my', '0');
    });
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

  /* ---------------------------------------------------------- hesap formları
     Şifre göster/gizle, yeni şifrede güç çubuğu (uzunluk + karakter çeşidi;
     sunucu yine yalnız en az 10 karakter şartı arıyor) ve "fatura adresi
     teslimatla aynı" anahtarı. */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-pw-toggle]');
    if (!b) return;
    var inp = document.getElementById(b.getAttribute('aria-controls'));
    if (!inp) return;
    var show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    b.textContent = b.getAttribute(show ? 'data-pw-hide' : 'data-pw-show');
  });
  document.querySelectorAll('[data-pw-meter]').forEach(function (inp) {
    var field = inp.closest('.field');
    var bar = field && field.querySelector('.pwmeter');
    var txt = field && field.querySelector('.pwmeter__t');
    if (!bar || !txt) return;
    var rate = function () {
      var v = inp.value, kinds = 0;
      [/[a-z]/, /[A-Z]/, /[0-9]/, /[^A-Za-z0-9]/].forEach(function (r) { if (r.test(v)) kinds++; });
      var l = v.length < 10 ? 0 : (v.length >= 14 && kinds >= 3 ? 3 : (kinds >= 2 ? 2 : 1));
      bar.setAttribute('data-l', l);
      bar.firstElementChild.style.width = (v.length === 0 ? 0 : [18, 40, 70, 100][l]) + '%';
      txt.textContent = txt.getAttribute('data-l' + l);
    };
    inp.addEventListener('input', rate);
  });
  document.querySelectorAll('[data-toggle-target]').forEach(function (cb) {
    var tgt = document.querySelector(cb.getAttribute('data-toggle-target'));
    if (!tgt) return;
    cb.addEventListener('change', function () { tgt.hidden = cb.checked; });
  });

  /* ------------------------------------------------- 48 saatlik fırsat sayacı
     Bitiş anı sunucudan (data-deal-end, Unix saniye). Değişen rakam kısa bir
     kayışla yenileniyor. Süre dolunca sayfa bir kez tazeleniyor: indirim de
     bölüm de sunucuda kalkıyor, tarayıcıda fiyat hesaplanmıyor. */
  var deal = document.querySelector('[data-deal-end]');
  if (deal) {
    var dealEnd = parseInt(deal.getAttribute('data-deal-end'), 10) || 0;
    var cells = {};
    deal.querySelectorAll('[data-u]').forEach(function (b) { cells[b.getAttribute('data-u')] = b; });
    var dealDone = false;
    var dealTick = function () {
      var d = Math.max(0, Math.floor(dealEnd - Date.now() / 1000));
      var v = { d: Math.floor(d / 86400), h: Math.floor((d % 86400) / 3600), m: Math.floor((d % 3600) / 60), s: d % 60 };
      Object.keys(v).forEach(function (k) {
        var el = cells[k]; if (!el) return;
        var txt = (v[k] < 10 ? '0' : '') + v[k];
        if (el.textContent === txt) return;
        el.textContent = txt;
        if (!reduced) { el.classList.remove('is-tick'); void el.offsetWidth; el.classList.add('is-tick'); }
      });
      if (d === 0 && !dealDone) {
        dealDone = true;
        deal.classList.add('is-over');
        setTimeout(function () { window.location.reload(); }, 3000);
      }
    };
    dealTick();
    setInterval(dealTick, 1000);
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
    /* Birden çok rengi olan üründe renk de seçilmeli — sunucu da reddediyor
       (vr_cart_colour), burada yalnızca sayfa yenilenmeden söylüyoruz. */
    if (form.querySelector('input[name="colour"][type="radio"]') &&
        !form.querySelector('input[name="colour"]:checked')) {
      e.preventDefault();
      var cbox = form.querySelector('[data-colour-error]');
      if (cbox) { cbox.hidden = false; cbox.setAttribute('role', 'alert'); }
      var cfirst = form.querySelector('.cchips input');
      if (cfirst) cfirst.focus();
      return;
    }
    /* Tek bedenli üründe beden GİZLİ input olarak geliyor (value="ONE") ve
       gizli input asla :checked olmaz. Bu yüzden aşağıdaki kontrol tek bedenli
       her üründe formu blokluyordu — JS açıkken çanta, kemer ve tek parça
       ürünler sepete hiç eklenemiyordu. Önce gerçekten seçilecek bir beden
       var mı ona bakıyoruz. */
    if (!form.querySelector('input[name="size"][type="radio"]')) return;
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

  /* --------------------------------------------------------- sahne kampanya filmi
     Video etiketinde bilerek autoplay YOK: oynatmayı burası başlatıyor, böylece
     JS kapalıyken hareket hiç doğmuyor ve duraklatma düğmesinin çalışmadığı bir
     durum kalmıyor (WCAG 2.2.2). Kare geçişli CSS filmini duraklatmak için JS
     gerekmiyor, onu işaret kutusu tek başına hallediyor. */
  /* Karo filmleri: ekrana girince oynat, çıkınca durdur. Ana sayfada üç
     video var; hepsini birden çalıştırmak mobilde işlemciyi boşuna yakıyor
     ve pil tüketiyor. Sahne filmi bu kuralın dışında — o zaten ilk ekranda. */
  /* Film oynatılmalı mı?
     Sahnedeki film 3,1 MB, iki bant filmi 2 MB'ın üzerinde. Masaüstünde bu
     bedel makul; telefonda değil. Ölçtük: ana sayfa 390 px genişlikte 3,66 MB
     iniyordu ve bunun 3,2 MB'ı filmdi — geri kalan bütün sayfa 0,4 MB.
     Dar ekranda, veri tasarrufu açıkken ya da yavaş bağlantıda film hiç
     indirilmiyor; yerinde filmin kendi ilk karesi (gerçek ürün fotoğrafı)
     duruyor. Kullanıcı bir şey kaybetmiyor, sayfa açılıyor. */
  function vrPlayFilms() {
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return false;
    if (window.matchMedia && window.matchMedia('(max-width: 900px)').matches) return false;
    var c = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
    if (c) {
      if (c.saveData) return false;
      if (/2g|slow-2g|3g/.test(String(c.effectiveType || ''))) return false;
    }
    return true;
  }
  var vrFilms = vrPlayFilms();

  var bandVids = document.querySelectorAll('.etile__vid[data-autoplay]');
  if (bandVids.length && window.IntersectionObserver && vrFilms) {
    {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
          if (e.isIntersecting) {
            var pr = e.target.play();
            if (pr && pr.catch) pr.catch(function () {});
          } else {
            e.target.pause();
          }
        });
      }, { threshold: 0.25 });
      bandVids.forEach(function (v) { io.observe(v); });
    }
  }

  /* Telefonda dikey film: 0,8 MB, yani yatay filmin dörtte biri. Dar
     ekranda yalnız o oynar; veri tasarrufu, yavaş bağlantı ve hareket
     azaltma tercihi yine durdurur (afiş kalır). */
  var stageVid = document.querySelector('.stage__vid[data-autoplay]');
  var portraitVid = document.querySelector('.stage__vid[data-autoplay-portrait]');
  if (portraitVid && window.matchMedia && window.matchMedia('(max-width: 900px)').matches) {
    var pc = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
    var pOk = !window.matchMedia('(prefers-reduced-motion: reduce)').matches &&
              !(pc && (pc.saveData || /2g|slow-2g/.test(String(pc.effectiveType || ''))));
    stageVid = pOk ? portraitVid : null;
    vrFilms = pOk;
  }
  if (stageVid) {
    var stageBox = document.getElementById('stage-pause');
    var stagePlay = function () {
      var pr = stageVid.play();
      if (pr && pr.catch) pr.catch(function () { /* tarayıcı reddederse poster kalır */ });
    };
    if (vrFilms) stagePlay();
    if (stageBox) {
      stageBox.addEventListener('change', function () {
        if (stageBox.checked) stageVid.pause(); else stagePlay();
      });
    }
  }

  /* ------------------------------------------- kartta imleci izleyen ışık
     Yalnız fareli cihazda ve yalnız üzerinde durulan kartta: konum CSS
     değişkeni olarak yazılıyor, ışığı .fr::after çiziyor. */
  if (!reduced && window.matchMedia('(hover:hover)').matches) {
    document.addEventListener('pointermove', function (e) {
      var m = e.target.closest && e.target.closest('.card__media');
      if (!m) return;
      var r = m.getBoundingClientRect();
      m.style.setProperty('--mx', ((e.clientX - r.left) / r.width * 100).toFixed(1) + '%');
      m.style.setProperty('--my', ((e.clientY - r.top) / r.height * 100).toFixed(1) + '%');
    }, { passive: true });
  }

  /* ---------------------------------------- ürün görselleri: ilk açılış efekti
     .card__alt hariç: onun görünürlüğünü CSS hover'ı yönetiyor, buradaki satır
     içi opacity:1 o kuralı ezip ikinci kareyi kalıcı olarak açıyordu. */
  /* Yüklenene kadar çerçevede ince bir ışıltı (CSS .fr.is-loading). */
  document.querySelectorAll('.pdp__shot img, .card__media img:not(.card__alt), .cpic img, .swatch img').forEach(function (img) {
    if (img.complete) return;
    var fr = img.closest('.fr');
    if (fr && !reduced) fr.classList.add('is-loading');
    img.style.opacity = '0';
    var done = function () {
      if (fr) fr.classList.remove('is-loading');
      img.style.transition = 'opacity .6s ease, transform 1s cubic-bezier(.22,.61,.36,1)';
      img.style.opacity = '1';
    };
    img.addEventListener('load', done, { once: true });
    // Yükleme hata verirse görsel gizli kalmasın.
    img.addEventListener('error', done, { once: true });
  });

  /* ------------------------------------------------------ galeri büyüteci
     Her görsel zaten kendi dosyasına giden bir bağlantı; JS kapalıyken
     tarayıcı görseli tam boy açar. Burada o bağlantıyı yakalayıp katmanda
     gösteriyoruz — klavyeyle (Esc, ←, →) de gezilebiliyor. */
  var allZoom = Array.prototype.slice.call(document.querySelectorAll('[data-zoom]'));
  var zoomLinks = allZoom;
  if (zoomLinks.length) {
    var box = null, idx = 0, opener = null;

    var lbUnzoom = null;
    var render = function () {
      if (lbUnzoom) lbUnzoom();          // kare değişince büyütme sıfırlanır
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
      if (lbUnzoom) lbUnzoom();
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

      /* Görüntüleyicide de büyütme: tıkla 1:1 ol, sürükleyerek gez.
         Hover büyütmesi yalnızca gerçek imleçte çalışıyor; dokunmatikte
         büyütmenin tek yolu burası. Ölçek yine dosyanın DOĞAL boyutuyla
         sınırlı — üstüne çıkmak bulanıklık üretmekten başka bir şey yapmaz,
         o yüzden kazanç yoksa tıklama hiçbir şey yapmıyor ve imleç
         değişmiyor. */
      var im = box.querySelector('img');
      var zoomed = false, drag = null;
      var canZoom = function () {
        return im.naturalWidth > im.clientWidth * 1.25;
      };
      var unzoom = function () {
        zoomed = false; drag = null;
        im.classList.remove('is-zoom');
        im.style.transform = '';
        box.classList.remove('is-zoom');
      };
      lbUnzoom = unzoom;
      im.addEventListener('click', function (e) {
        e.stopPropagation();
        if (!zoomed) {
          if (!canZoom()) return;
          zoomed = true;
          im.classList.add('is-zoom');
          box.classList.add('is-zoom');
          panTo(e);
        } else { unzoom(); }
      });
      var panTo = function (e) {
        if (!zoomed) return;
        var r = im.getBoundingClientRect();
        var px = Math.min(1, Math.max(0, (e.clientX - r.left) / r.width));
        var py = Math.min(1, Math.max(0, (e.clientY - r.top) / r.height));
        var sx = Math.max(0, im.naturalWidth - box.clientWidth) * (px - 0.5);
        var sy = Math.max(0, im.naturalHeight - box.clientHeight) * (py - 0.5);
        // -50%,-50% ortalama; ikinci translate imlece göre kaydırma.
        im.style.transform = 'translate(-50%,-50%) translate(' + (-sx) + 'px,' + (-sy) + 'px)';
      };
      im.addEventListener('mousemove', function (e) { if (zoomed && !drag) panTo(e); });
      im.addEventListener('pointerdown', function (e) { if (zoomed) { drag = e; im.setPointerCapture(e.pointerId); } });
      im.addEventListener('pointermove', function (e) { if (zoomed && drag) panTo(e); });
      im.addEventListener('pointerup', function () { drag = null; });
    };

    allZoom.forEach(function (link) {
      link.addEventListener('click', function (e) {
        // Yeni sekmede açmak isteyeni engellemeyelim.
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
        e.preventDefault();
        if (!box) build();
        // Yalnız görünen galeri (çok renkli üründe seçili rengin kareleri).
        zoomLinks = allZoom.filter(function (l) { return !l.closest('[hidden]'); });
        idx = Math.max(0, zoomLinks.indexOf(link)); opener = link;
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

  /* ----------------------------------------------------- tam ekran arama
     Başlıktaki ikon ve uygulamanın "Ara" sekmesi açar (data-search-open).
     Öneriler masaüstü kutusuyla aynı uç noktadan; metin textContent ile
     basılıyor, sunucudan gelen ad HTML olarak yorumlanmasın.             */
  var qs = document.querySelector('[data-qsheet]');
  if (qs) {
    var qIn = qs.querySelector('[data-qsheet-input]');
    var qRes = qs.querySelector('[data-qsheet-res]');
    var qBrands = qs.querySelector('[data-qsheet-brands]');
    var qForm = qs.querySelector('form');
    var qLast = null, qTimer = null, qSeq = 0;
    var qOpen = function (open) {
      if (open) {
        qLast = document.activeElement;
        qs.hidden = false; qs.classList.remove('is-closing'); qs.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        window.setTimeout(function () { qIn.focus(); }, 30);
      } else if (!qs.hidden) {
        qs.classList.remove('is-open');
        var done = function () { qs.hidden = true; qs.classList.remove('is-closing'); };
        if (reduced) done(); else { qs.classList.add('is-closing'); window.setTimeout(done, 300); }
        document.body.style.overflow = '';
        if (qLast && qLast.focus) qLast.focus();
      }
    };
    document.querySelectorAll('[data-search-open]').forEach(function (a) {
      a.addEventListener('click', function (e) { e.preventDefault(); qOpen(true); });
    });
    qs.addEventListener('click', function (e) {
      if (e.target === qs || e.target.closest('[data-qsheet-close]')) qOpen(false);
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') qOpen(false); });
    var qRun = function () {
      var q = qIn.value.trim(), my = ++qSeq;
      if (q.length < 2) { qRes.textContent = ''; if (qBrands) qBrands.hidden = false; return; }
      qs.classList.add('qsheet--busy');
      fetch(qForm.getAttribute('action').split('?')[0].replace(/shop\.php$/, 'api/suggest.php') + '?q=' + encodeURIComponent(q),
            { credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) {
          if (my !== qSeq) return;
          qs.classList.remove('qsheet--busy');
          qRes.textContent = '';
          ((d && d.items) || []).forEach(function (it, i) {
            var a = document.createElement('a'); a.href = it.url; a.style.setProperty('--i', i);
            var im = document.createElement('img'); im.src = it.img; im.alt = ''; im.loading = 'lazy';
            var tx = document.createElement('span');
            var b = document.createElement('span'); b.className = 'suggest__b'; b.textContent = it.brand;
            var n = document.createElement('span'); n.className = 'suggest__t'; n.textContent = it.name;
            tx.appendChild(b); tx.appendChild(n);
            var pr = document.createElement('span'); pr.className = 'suggest__p'; pr.textContent = it.price;
            a.appendChild(im); a.appendChild(tx); a.appendChild(pr); qRes.appendChild(a);
          });
          var all = document.createElement('a');
          all.className = 'qsheet__all'; all.href = qForm.getAttribute('action').split('?')[0] + '?q=' + encodeURIComponent(q);
          all.style.setProperty('--i', qRes.children.length);
          var lbl = document.createElement('span'); lbl.textContent = '“' + q + '”';
          all.appendChild(lbl); all.insertAdjacentHTML('beforeend', '<span aria-hidden="true">&rarr;</span>');
          qRes.appendChild(all);
          if (qBrands) qBrands.hidden = qRes.children.length > 1;
        }).catch(function () { qs.classList.remove('qsheet--busy'); });
    };
    qIn.addEventListener('input', function () { window.clearTimeout(qTimer); qTimer = window.setTimeout(qRun, 200); });
  }

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

  /* --------------------------------------------------------- okuma çizgisi
     Başlığın alt kenarındaki saç teli, sayfanın ne kadarının okunduğunu
     gösteriyor. Değer bir CSS değişkenine yazılıyor; çizginin kendisi CSS'te
     (bkz. .site::after), yani JS kapalıysa çizgi sıfırda kalıyor.

     rAF ile kısılıyor: scroll olayı saniyede yüzlerce kez tetikleniyor ve her
     birinde style yazmak kaydırmayı tutukluyordu. */
  var head = document.querySelector('.site');
  if (head) {
    var lastPct = -1, praf = null;
    var setProgress = function () {
      praf = null;
      var max = document.documentElement.scrollHeight - window.innerHeight;
      var pct = max > 0 ? Math.min(100, Math.max(0, (window.scrollY / max) * 100)) : 0;
      var r = Math.round(pct * 10) / 10;
      if (r === lastPct) return;
      lastPct = r;
      head.style.setProperty('--vr-progress', r + '%');
    };
    setProgress();
    window.addEventListener('scroll', function () {
      if (praf) return;
      praf = requestAnimationFrame(setProgress);
    }, { passive: true });
    window.addEventListener('resize', setProgress, { passive: true });
  }

  /* ------------------------------------------------- fotoğrafta 1:1 büyütme
     Ürün sayfasında imleç kadrajın üstünde gezerken dosyanın GERÇEK
     pikselleri gösteriliyor: arka plan katmanı orijinali 1:1 basıyor, imleç
     yüzdesi de arka plan konumunu sürüyor. Yani büyütme "yakınlaştırma
     taklidi" değil, dokumaya bakmak.

     KAPI: dosya ekrandaki boyutundan anlamlı ölçüde büyük DEĞİLSE büyütme hiç
     açılmıyor. Katalogdaki 2056 karenin 941'i 800 pikselden dar; onlarda
     büyütmek bulanıklık üretmekten başka bir şey yapmaz. Yanlış bir vaat
     vermemek için imleç de değişmiyor, katman da kurulmuyor.

     Yalnızca gerçek imleçte (pointer:fine): dokunmatikte hover diye bir şey
     yok, orada tam ekran görüntüleyici zaten var. */
  var fine = window.matchMedia('(pointer:fine)').matches;
  if (fine && !reduced) {
    document.querySelectorAll('.pdp__shot[data-zoom]').forEach(function (shot) {
      var full = shot.getAttribute('href') || '';
      if (!full) return;
      var layer = null, nat = null, probing = false;

      var probe = function () {
        if (probing || nat) return;
        probing = true;
        var im = new Image();
        im.onload = function () {
          nat = { w: im.naturalWidth, h: im.naturalHeight };
          var r = shot.getBoundingClientRect();
          // Kazanç yoksa hiç kurmuyoruz: %25'ten az fark gözle görülmüyor.
          if (nat.w < r.width * 1.25) { nat = false; return; }
          layer = document.createElement('span');
          layer.className = 'pdp__mag';
          layer.style.backgroundImage = 'url("' + full + '")';
          layer.style.backgroundSize = nat.w + 'px ' + nat.h + 'px';
          shot.appendChild(layer);
          shot.classList.add('can-mag');
        };
        im.src = full;
      };

      shot.addEventListener('mouseenter', probe);
      shot.addEventListener('mousemove', function (e) {
        if (!layer || !nat) return;
        var r = shot.getBoundingClientRect();
        var px = Math.min(1, Math.max(0, (e.clientX - r.left) / r.width));
        var py = Math.min(1, Math.max(0, (e.clientY - r.top) / r.height));
        // Kaydırılabilir alan: doğal boyut eksi görünen kutu.
        layer.style.backgroundPosition =
          (-px * Math.max(0, nat.w - r.width)) + 'px ' + (-py * Math.max(0, nat.h - r.height)) + 'px';
        shot.classList.add('is-mag');
      });
      shot.addEventListener('mouseleave', function () { shot.classList.remove('is-mag'); });
    });
  }
})();

/* Hukuki tablolar: dar ekranda her satır etiketli bir blok olarak dizilir
   (CSS: .doc td::before). Etiketi başlık hücresinden alıyoruz ki 10 dilin
   içerik dosyalarına tek tek data-label yazmak gerekmesin. */
(function () {
  document.querySelectorAll('.doc table').forEach(function (t) {
    var heads = Array.prototype.map.call(t.querySelectorAll('thead th'), function (th) {
      return th.textContent.trim();
    });
    if (!heads.length) return;
    t.classList.add('is-labelled');
    t.querySelectorAll('tbody tr').forEach(function (tr) {
      Array.prototype.forEach.call(tr.children, function (td, i) {
        if (heads[i]) td.setAttribute('data-label', heads[i]);
      });
    });
  });
})();

/* Uygulama: servis çalışanı, "Yükle" düğmesi, cihaza göre kurulum yolu.
   Uygulama sayfası (app.php) JS'siz de eksiksiz: iki platformun adımları
   her zaman yazılı. Buradaki her şey yalnızca o cihazı öne çıkarıyor. */
(function () {
  var root = document.documentElement;
  var ua = navigator.userAgent || '';
  var standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) ||
                   window.navigator.standalone === true || /SarvestoApp\//.test(ua);
  var isIOS = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var isAndroid = /Android/i.test(ua);
  root.setAttribute('data-platform', standalone ? 'app' : isIOS ? 'ios' : isAndroid ? 'android' : 'desktop');

  // Servis çalışanı: manifestin bulunduğu klasörde (alt klasör kurulumunda da doğru kapsam).
  var man = document.querySelector('link[rel="manifest"]');
  if ('serviceWorker' in navigator && man && window.isSecureContext) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(new URL('sw.js', man.href).pathname).catch(function () {});
    });
  }

  // Android/Chrome: tarayıcının kendi kurulum penceresi.
  var deferred = null;
  var buttons = document.querySelectorAll('[data-app-install]');
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferred = e;
    buttons.forEach(function (b) { b.hidden = false; });
    root.classList.add('can-install');
  });
  buttons.forEach(function (b) {
    b.addEventListener('click', function () {
      if (!deferred) return;
      deferred.prompt();
      deferred.userChoice.finally(function () { deferred = null; });
    });
  });
  window.addEventListener('appinstalled', function () {
    buttons.forEach(function (b) { b.hidden = true; });
    root.setAttribute('data-platform', 'app');
  });
})();

/* Bildirimler (Web Push). Kutu [data-push] sunucuda basılıyor (vr_push_box);
   burada cihazın durumuna göre doğru hâli gösteriyoruz:
     off       → "Bildirimleri aç"          on      → "Açık ✓ · kapat"
     denied    → tarayıcı ayarı ipucu        ios     → önce ana ekrana ekle
     unsupported → kutu gizli (ör. Android APK'nın WebView'ı)
   İzin penceresi YALNIZCA düğmeye basınca açılır — sayfa açılışında asla. */
(function () {
  var boxes = Array.prototype.slice.call(document.querySelectorAll('[data-push]'));
  var prompt = document.querySelector('[data-push-prompt]');
  if (!boxes.length) return;
  var ua = navigator.userAgent || '';
  var isIOS = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone === true;
  var supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window && window.isSecureContext;
  var cfg = boxes[0].dataset;

  function set(s) {
    boxes.forEach(function (b) { b.setAttribute('data-state', s); });
    document.documentElement.setAttribute('data-push', s);
    if (prompt) {
      var later = 0;
      try { later = +localStorage.getItem('sv-push-later') || 0; } catch (e) {}
      prompt.hidden = !(s === 'off' && standalone && Date.now() - later > 14 * 864e5);
    }
  }
  function msg(t) { boxes.forEach(function (b) { var m = b.querySelector('[data-push-msg]'); if (m) m.textContent = t || ''; }); }
  function key(b64) {
    var s = (b64 + '===='.slice((b64.length + 3) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    var raw = atob(s), out = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
    return out;
  }
  function post(data) {
    var f = new FormData();
    f.append('_csrf', cfg.csrf);
    Object.keys(data).forEach(function (k) { f.append(k, data[k]); });
    return fetch(cfg.endpoint, { method: 'POST', body: f, credentials: 'same-origin' })
      .then(function (r) { if (!r.ok) throw new Error('http ' + r.status); return r.json(); });
  }

  // Mağaza uygulamasının içindeki web görünümü (SarvestoApp) push desteklemiyor:
  // orada kutu hiç görünmez; "ana ekrana ekle" ipucu yalnız Safari'deki iPhone'a.
  if (!supported) { set(isIOS && !standalone && !/SarvestoApp\//.test(ua) ? 'ios' : 'unsupported'); return; }
  if (Notification.permission === 'denied') { set('denied'); }

  navigator.serviceWorker.ready.then(function (reg) {
    return reg.pushManager.getSubscription().then(function (sub) {
      if (sub) {
        set('on');
        // Dil ve "görüldü" bilgisini tazele (sessiz; hata önemsiz).
        post({ action: 'subscribe', sub: JSON.stringify(sub) }).catch(function () {});
      } else if (Notification.permission !== 'denied') {
        set('off');
      }

      document.addEventListener('click', function (e) {
        var on = e.target.closest('[data-push-on]'), off = e.target.closest('[data-push-off]'),
            later = e.target.closest('[data-push-later]');
        if (later) {
          try { localStorage.setItem('sv-push-later', String(Date.now())); } catch (x) {}
          if (prompt) prompt.hidden = true;
          return;
        }
        if (on) {
          e.preventDefault(); msg(''); set('busy');
          // Hangi adimin dustugu sunucuya yazilir (asama + hata adi; kisisel veri yok).
          var stage = 'permission', host = '';
          Notification.requestPermission().then(function (p) {
            if (p !== 'granted') { set(p === 'denied' ? 'denied' : 'off'); return; }
            stage = 'subscribe';
            return reg.pushManager.getSubscription().then(function (old) {
              // Eski (baska anahtarla acilmis) abonelik varsa once kapat: aksi halde
              // subscribe() "InvalidStateError" verir.
              return old ? old.unsubscribe().catch(function () {}) : null;
            }).then(function () {
              return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key(cfg.key) });
            }).then(function (s) {
              stage = 'save';
              try { host = new URL(s.endpoint).hostname; } catch (x) {}
              return post({ action: 'subscribe', sub: JSON.stringify(s) });
            }).then(function () { set('on'); });
          }).catch(function (err) {
            set('off'); msg(cfg.err);
            var f = new FormData();
            f.append('action', 'fail'); f.append('stage', stage); f.append('host', host);
            f.append('err', String((err && (err.name + ': ' + err.message)) || err).slice(0, 90));
            fetch(cfg.endpoint, { method: 'POST', body: f, credentials: 'same-origin' }).catch(function () {});
          });
        }
        if (off) {
          e.preventDefault(); set('busy');
          reg.pushManager.getSubscription().then(function (s) {
            if (!s) return;
            var ep = s.endpoint;
            return s.unsubscribe().then(function () { return post({ action: 'unsubscribe', endpoint: ep }); });
          }).then(function () { set('off'); }).catch(function () { set('on'); msg(cfg.err); });
        }
      });
    });
  }).catch(function () { set('unsupported'); });
})();

/* Telefonda uygulama şeridi: tarayıcıdan gelen ziyaretçiye bir kez, altta,
   kapatılabilir. Uygulamanın içinde, uygulama sayfasında ve kasada yok. */
(function () {
  var bar = document.querySelector('[data-appbar]');
  if (!bar) return;
  var p = document.documentElement.getAttribute('data-platform');
  if (p !== 'ios' && p !== 'android') return;
  var shut = 0;
  try { shut = +localStorage.getItem('sv-appbar') || 0; } catch (e) {}
  if (Date.now() - shut < 30 * 864e5) return;
  bar.querySelector('[data-appbar-go]').setAttribute('href', bar.dataset.href + (p === 'ios' ? '#ios' : '#android'));
  bar.hidden = false;
  document.documentElement.classList.add('has-appbar');
  bar.addEventListener('click', function (e) {
    if (e.target.closest('[data-appbar-x]')) {
      try { localStorage.setItem('sv-appbar', String(Date.now())); } catch (x) {}
      bar.hidden = true; document.documentElement.classList.remove('has-appbar');
    }
    // Android Chrome: tarayıcının kendi "yükle" penceresi varsa doğrudan onu aç
    var go = e.target.closest('[data-appbar-go]');
    var install = document.querySelector('[data-app-install]');
    if (go && p === 'android' && install && !install.hidden) { e.preventDefault(); install.click(); }
  });
})();

/* Görsel yüklenemezse iki kez daha dene (kısa, rastgele aralıkla).
   Paylaşımlı sunucu yoğunken küçük kopyayı üreten istek 503/508 dönebiliyor;
   bir saniye sonra aynı istek genellikle geçiyor. Kopyalar hazır olduğunda
   (düz dosya) bu yol neredeyse hiç kullanılmaz. */
document.addEventListener('error', function (e) {
  var img = e.target;
  if (!img || img.tagName !== 'IMG') return;
  var n = +(img.getAttribute('data-retry') || 0);
  var src = img.currentSrc || img.src;
  if (n >= 2 || !src || src.indexOf('/uploads/') < 0 && src.indexOf('img.php') < 0) return;
  img.setAttribute('data-retry', String(n + 1));
  setTimeout(function () {
    img.removeAttribute('srcset');
    img.src = src.replace(/([?&])r=\d+&?/, '$1').replace(/[?&]$/, '') + (src.indexOf('?') < 0 ? '?' : '&') + 'r=' + (n + 1);
  }, 900 + Math.random() * 1600 * (n + 1));
}, true);

/* Renk → galeri: tek üründe çok renk varsa (product.php, vr_colour_sets)
   seçilen rengin galerisi görünür, diğerleri gizlenir. Sepete giden değer
   radyo düğmesinin kendisi; burada yalnızca görüntü. */
(function () {
  var radios = document.querySelectorAll('input[name="colour"][data-cset]');
  if (!radios.length) return;
  var galleries = document.querySelectorAll('[data-gallery][data-cset]');
  var now = document.querySelector('[data-cset-now]');
  var reducedC = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  function show(i, animate) {
    galleries.forEach(function (g) {
      var on = g.getAttribute('data-cset') === String(i);
      g.hidden = !on;
      g.classList.remove('is-swap');
      if (on && animate && !reducedC) { void g.offsetWidth; g.classList.add('is-swap'); }
    });
    var r = document.querySelector('input[name="colour"][data-cset="' + i + '"]');
    if (now && r) {
      now.textContent = r.getAttribute('data-cset-label') || '';
      now.classList.remove('is-swap');
      if (animate && !reducedC) { void now.offsetWidth; now.classList.add('is-swap'); }
    }
    // Telefonda galeri üstte: yeni rengin ilk karesi görünsün.
    var g = document.querySelector('[data-gallery][data-cset="' + i + '"]');
    if (g && window.matchMedia && window.matchMedia('(max-width: 940px)').matches) {
      var top = g.getBoundingClientRect().top;
      if (top < 0) window.scrollBy({ top: top - 80, behavior: 'smooth' });
    }
  }
  radios.forEach(function (r) {
    r.addEventListener('change', function () { if (r.checked) show(r.getAttribute('data-cset'), true); });
  });
  var on = document.querySelector('input[name="colour"][data-cset]:checked');
  if (on) show(on.getAttribute('data-cset'));
})();

/* Ürün kartı → ürün sayfası: kartın fotoğrafı yeni sayfanın ilk karesine
   dönüşür (CSS "hero", sayfalar arası View Transitions). Ad yalnız tıklanan
   karta verilir; sayfada aynı addan iki tane olursa tarayıcı geçişi tümden
   atlıyor, onun için önce hepsi temizleniyor. Ürün sayfasının kendi ilk
   karesi html.vt-card varken adını bırakıyor (sayfanın altındaki "bunlar da
   hoşunuza gidebilir" kartına dokunulduğunda). */
(function () {
  if (!('onpageswap' in window) && !document.startViewTransition) return;
  var root = document.documentElement, hero = null;
  function clear() {
    if (hero) hero.style.viewTransitionName = '';
    hero = null; root.classList.remove('vt-card');
  }
  document.addEventListener('click', function (e) {
    if (e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
    var a = e.target.closest('a.card__media, .card a, .housecard a');
    clear();
    if (!a) return;
    var card = a.closest('.card, .housecard');
    var media = card && card.querySelector('.card__media, .housecard__shot');
    if (!media) return;
    media.style.viewTransitionName = 'hero';
    hero = media; root.classList.add('vt-card');
  }, true);
  window.addEventListener('pageswap', function (e) {
    if (!hero || !e.viewTransition) return;
    var to = e.activation && e.activation.entry && e.activation.entry.url;
    var href = (hero.closest('a') || hero.querySelector('a') || {}).href || (hero.closest('.card, .housecard').querySelector('a') || {}).href;
    if (!to || !href || to.split('#')[0] !== href.split('#')[0]) clear();
  });
})();

/* Uygulamada alt sekme çubuğu: etkin çizgi bir önceki sekmeden kayar;
   aşağı kaydırınca başlık yukarı çekilir, yukarı kaydırınca geri gelir. */
(function () {
  var root = document.documentElement;
  var bar = document.querySelector('[data-tabbar]');
  if (!bar || root.getAttribute('data-platform') !== 'app') return;
  var cur = bar.hasAttribute('data-none') ? -1 : +getComputedStyle(bar).getPropertyValue('--i') || 0;
  var prev = -1;
  try { prev = +(sessionStorage.getItem('sv-tab') || -1); sessionStorage.setItem('sv-tab', String(cur)); } catch (e) {}
  if (cur >= 0 && prev >= 0 && prev !== cur) {
    var ind = bar.querySelector('.tabbar__ind');
    ind.style.transition = 'none';
    bar.style.setProperty('--i', String(prev));
    void ind.offsetWidth;
    ind.style.transition = '';
    requestAnimationFrame(function () { bar.style.setProperty('--i', String(cur)); });
  }
  var head = document.querySelector('.site');
  if (!head) return;
  var last = window.scrollY, ticking = false;
  window.addEventListener('scroll', function () {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(function () {
      ticking = false;
      var y = window.scrollY, d = y - last;
      if (Math.abs(d) < 6) return;
      head.classList.toggle('is-tucked', d > 0 && y > 140 && !document.querySelector('[data-drawer]:not([hidden])'));
      last = y;
    });
  }, { passive: true });
})();

/* Kasa — ödeme yöntemi geçişi (kart ⇄ kripto). Satır içi script CSP'ye
   takıldığı için burada; sayfada ilgili alanlar yoksa hiçbir şey yapmaz. */
(function () {
  var radios = document.querySelectorAll('[data-paym]');
  var fields = document.querySelector('[data-crypto-fields]');
  var cardOnly = document.querySelector('[data-card-only]');
  var btn = document.querySelector('[data-paybtn]');
  if (!fields && !btn) return;

  function sync() {
    var checked = document.querySelector('[data-paym]:checked');
    // Tek yöntem varsa radio yok: alanların zaten görünür olduğu hâli koru.
    var isCrypto = checked ? checked.value === 'crypto' : (fields && !fields.hidden);
    if (fields) {
      fields.hidden = !isCrypto;
      fields.querySelectorAll('[data-crypto-req]').forEach(function (i) { i.required = isCrypto; });
    }
    if (cardOnly) cardOnly.hidden = isCrypto;
    if (btn) {
      var span = btn.querySelector('span');
      var lbl = btn.getAttribute(isCrypto ? 'data-label-crypto' : 'data-label-card');
      if (span && lbl) span.textContent = lbl;
    }
  }
  radios.forEach(function (r) { r.addEventListener('change', sync); });
  // Tek yöntem (yalnız kripto) hâlinde alanlar baştan zorunlu olsun.
  if (!radios.length && fields) {
    fields.hidden = false;
    fields.querySelectorAll('[data-crypto-req]').forEach(function (i) { i.required = true; });
  } else {
    sync();
  }
})();
