// ============================================================
// MONDIMART THEME JS — Premium
// ============================================================
document.addEventListener('DOMContentLoaded', () => {

  // ── MOBILE MENU ──
  const toggle = document.getElementById('menu-toggle');
  const mobileMenu = document.getElementById('mobile-menu');
  const mobileOverlay = document.getElementById('mobile-overlay');

  function openMobileMenu() {
    toggle?.classList.add('open');
    mobileMenu?.classList.add('open');
    mobileOverlay?.style.setProperty('display', 'block');
    requestAnimationFrame(() => mobileOverlay?.classList.add('open'));
    document.body.style.overflow = 'hidden';
  }
  function closeMobileMenu() {
    toggle?.classList.remove('open');
    mobileMenu?.classList.remove('open');
    mobileOverlay?.classList.remove('open');
    setTimeout(() => { if (mobileOverlay) mobileOverlay.style.display = 'none'; }, 300);
    document.body.style.overflow = '';
  }

  toggle?.addEventListener('click', () => {
    mobileMenu?.classList.contains('open') ? closeMobileMenu() : openMobileMenu();
  });
  mobileOverlay?.addEventListener('click', closeMobileMenu);
  document.getElementById('mm-close')?.addEventListener('click', closeMobileMenu);

  // Mobile submenu accordion
  document.querySelectorAll('.mm-item[data-sub]').forEach(item => {
    item.addEventListener('click', () => {
      const subId = item.dataset.sub;
      const sub = document.getElementById(subId);
      const isOpen = item.classList.contains('expanded');
      document.querySelectorAll('.mm-item.expanded').forEach(i => {
        i.classList.remove('expanded');
        const s = document.getElementById(i.dataset.sub);
        if (s) s.classList.remove('open');
      });
      if (!isOpen) {
        item.classList.add('expanded');
        sub?.classList.add('open');
      }
    });
  });

  // Back button handler — 2 levels
  document.querySelectorAll('.mm-sub-back').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      // If inside a level-2 sub, go back to level-1
      const parentSub = btn.closest('.mm-sub-l2');
      if (parentSub) {
        parentSub.classList.remove('open');
        return;
      }
      // Otherwise close all and go back to main
      document.querySelectorAll('.mm-item.expanded').forEach(i => {
        i.classList.remove('expanded');
        const s = document.getElementById(i.dataset.sub);
        if (s) s.classList.remove('open');
      });
      document.querySelectorAll('.mm-sub-l2.open').forEach(s => s.classList.remove('open'));
    });
  });

  // Level-2 sub items
  document.querySelectorAll('.mm-sub-item[data-sub2]').forEach(item => {
    item.addEventListener('click', (e) => {
      e.stopPropagation();
      const sub2Id = item.dataset.sub2;
      const sub2 = document.getElementById(sub2Id);
      document.querySelectorAll('.mm-sub-l2.open').forEach(s => s.classList.remove('open'));
      sub2?.classList.add('open');
    });
  });

  // ── CART DRAWER ──
  const cartOverlay = document.getElementById('cart-overlay');
  const cartDrawer = document.getElementById('cart-drawer');

  function openCart() {
    cartOverlay?.classList.add('open');
    cartDrawer?.classList.add('open');
    document.body.style.overflow = 'hidden';
    refreshCart();
  }
  function closeCart() {
    cartOverlay?.classList.remove('open');
    cartDrawer?.classList.remove('open');
    document.body.style.overflow = '';
  }

  // Expose globally so cart-drawer.liquid inline handlers can call them
  window.closeCartDrawer = closeCart;
  window.openCartDrawer  = openCart;

  cartOverlay?.addEventListener('click', closeCart);
  document.querySelectorAll('[data-open-cart]').forEach(b => b.addEventListener('click', e => { e.preventDefault(); openCart(); }));
  document.querySelectorAll('[data-close-cart]').forEach(b => b.addEventListener('click', closeCart));

  // ── CART API ──
  function updateCartCount(count) {
    document.querySelectorAll('.cart-count').forEach(el => { el.textContent = count; el.style.display = count > 0 ? 'flex' : 'none'; });
    document.querySelectorAll('.cart-sticky-count').forEach(el => el.textContent = count);
  }

  function refreshCart() {
    fetch('/cart.js').then(r => r.json()).then(cart => {
      updateCartCount(cart.item_count);
      const container = document.getElementById('cart-items-container');
      if (!container) return;
      if (cart.item_count === 0) {
        container.innerHTML = `<div style="text-align:center;padding:48px 20px;color:var(--tl)"><div style="font-size:52px;margin-bottom:14px">🛒</div><p style="font-size:15px;font-weight:600;color:var(--tm);margin-bottom:6px">Panier vide</p><p style="font-size:13px;margin-bottom:18px">Découvrez nos produits du monde</p><a href="/collections/all" style="display:inline-flex;background:var(--gl);color:#fff;padding:12px 22px;border-radius:10px;font-size:14px;font-weight:600;text-decoration:none">Explorer →</a></div>`;
        updateCartFooter(cart);
        return;
      }
      container.innerHTML = cart.items.map((item, i) => `
        <div class="cart-item">
          <a href="${item.url}" onclick="window.closeCartDrawer&&window.closeCartDrawer()" class="cart-item-img" style="text-decoration:none;display:block;cursor:pointer;">
            ${item.image ? `<img src="${item.image}" alt="${item.title}" loading="lazy">` : '<div style="width:100%;height:100%;background:var(--cr);display:flex;align-items:center;justify-content:center;font-size:28px">🛒</div>'}
          </a>
          <div class="cart-item-info">
            <a href="${item.url}" onclick="window.closeCartDrawer&&window.closeCartDrawer()" style="text-decoration:none;">
              <div class="cart-item-name" style="color:inherit;">${item.product_title}</div>
            </a>
            ${(item.variant_title && item.variant_title !== "Default Title") ? `<div class="cart-item-variant">${item.variant_title}</div>` : ''}
            <div class="cart-item-price">${formatMoney(item.final_line_price)}</div>
            <div class="cart-qty">
              <button class="qty-btn" onclick="window.changeQty(${i + 1}, -1)">−</button>
              <span>${item.quantity}</span>
              <button class="qty-btn" onclick="window.changeQty(${i + 1}, 1)">+</button>
              <button class="qty-btn" onclick="window.removeCartItem(${i + 1})" style="margin-left:4px;color:#c0392b;font-size:16px;opacity:.7" aria-label="Supprimer">🗑</button>
            </div>
          </div>
        </div>`).join('');
      updateCartFooter(cart);
    });
  }

  function updateCartFooter(cart) {
    const threshold = 4900;
    // Header count
    const hdr = document.querySelector('.cart-header h3');
    if (hdr) hdr.innerHTML = `Mon Panier <span style="opacity:.6;font-weight:400">(${cart.item_count})</span>`;
    // Subtotal rows
    const subtotalRows = document.querySelectorAll('.cart-subtotal');
    if (subtotalRows[0]) {
      const v = subtotalRows[0].querySelector('span:last-child');
      if (v) v.textContent = formatMoney(cart.items_subtotal_price || cart.total_price);
    }
    if (subtotalRows[1]) {
      const v = subtotalRows[1].querySelector('span:last-child');
      if (v) v.style.color = 'var(--gl)';
      if (v) v.textContent = cart.total_price >= threshold ? 'Gratuite 🎁' : 'Calculée ensuite';
    }
    // Total
    const totalEl = document.querySelector('.cart-total span:last-child');
    if (totalEl) totalEl.textContent = formatMoney(cart.total_price);
    // Free shipping progress bar
    const progressBar = document.querySelector('.cart-progress-inner');
    if (progressBar) {
      const pct = Math.min(100, Math.round(cart.total_price * 100 / threshold));
      progressBar.style.width = pct + '%';
    }
    // Progress text
    const progressText = document.getElementById('cart-progress-text');
    if (progressText) {
      if (cart.total_price >= threshold) {
        progressText.innerHTML = '🎁 Livraison gratuite débloquée !';
      } else {
        const rem = formatMoney(threshold - cart.total_price);
        progressText.innerHTML = `Plus que <strong>${rem}</strong> pour la livraison gratuite`;
      }
    }
  }

  window.refreshCart = refreshCart;

  window.changeQty = function(line, delta) {
    var btns = document.querySelectorAll('.qty-btn');
    btns.forEach(function(b){ b.disabled = true; b.style.opacity = '.5'; });
    fetch('/cart.js').then(function(r){ return r.json(); }).then(function(cart) {
      var item = cart.items[line - 1];
      if (!item) return;
      var newQty = Math.max(0, item.quantity + delta);
      fetch('/cart/change.js', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ line: line, quantity: newQty })
      }).then(function(r){ return r.json(); }).then(function() {
        refreshCart();
        btns.forEach(function(b){ b.disabled = false; b.style.opacity = '1'; });
      });
    });
  };

  // Support data-attribute based qty buttons (cart-drawer.liquid pattern)
  document.addEventListener('click', function(e) {
    const btn = e.target.closest('[data-qty-change]');
    if (!btn) return;
    const line = parseInt(btn.dataset.line);
    const delta = parseInt(btn.dataset.qtyChange);
    if (!line || isNaN(delta)) return;
    window.changeQty(line, delta);
  });

  function formatMoney(cents) {
    return (cents / 100).toFixed(2).replace('.', ',') + '€';
  }

  // ── TOAST NOTIFICATION ──
  function showToast(msg, type) {
    let t = document.getElementById('mm-toast');
    if (!t) {
      t = document.createElement('div');
      t.id = 'mm-toast';
      t.style.cssText = 'position:fixed;bottom:90px;left:50%;transform:translateX(-50%) translateY(20px);background:#1A3C1A;color:#fff;padding:12px 22px;border-radius:30px;font-size:14px;font-weight:600;z-index:9999;opacity:0;transition:all .3s;pointer-events:none;white-space:nowrap;box-shadow:0 4px 20px rgba(0,0,0,.25)';
      document.body.appendChild(t);
    }
    t.textContent = msg;
    t.style.background = type === 'error' ? '#c0392b' : '#1A3C1A';
    requestAnimationFrame(() => { t.style.opacity = '1'; t.style.transform = 'translateX(-50%) translateY(0)'; });
    clearTimeout(t._timer);
    t._timer = setTimeout(() => { t.style.opacity = '0'; t.style.transform = 'translateX(-50%) translateY(20px)'; }, 2800);
  }

  // Quick add
  document.addEventListener('click', e => {
    const btn = e.target.closest('[data-quick-add]');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    const variantId = btn.dataset.variantId;
    if (!variantId) return;
    const origInner = btn.innerHTML;
    btn.style.opacity = '.5';
    btn.disabled = true;
    fetch('/cart/add.js', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: variantId, quantity: 1 }) })
      .then(r => r.json())
      .then(item => {
        btn.style.opacity = '1';
        btn.disabled = false;
        showToast('✓ Ajouté au panier', 'success');
        openCart();
      })
      .catch(() => { btn.style.opacity = '1'; btn.disabled = false; showToast('Erreur, réessayez', 'error'); });
  });

  // ── REMOVE FROM CART ──
  window.removeCartItem = function(line) {
    fetch('/cart/change.js', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ line: line, quantity: 0 })
    }).then(r => r.json()).then(() => refreshCart());
  };

  refreshCart();

  // ── COUNTDOWN ──
  const cd = document.getElementById('flash-countdown');
  if (cd) {
    const endStr = cd.dataset.end;
    let endTime;
    if (endStr) {
      endTime = new Date(endStr).getTime();
      // If the configured end is in the past, auto-roll to next midnight
      if (endTime < Date.now()) {
        const midnight = new Date();
        midnight.setHours(23, 59, 59, 0);
        if (midnight.getTime() < Date.now()) midnight.setDate(midnight.getDate() + 1);
        endTime = midnight.getTime();
      }
    } else {
      const midnight = new Date();
      midnight.setHours(23, 59, 59, 0);
      endTime = midnight.getTime();
    }
    const elH = document.getElementById('cd-h');
    const elM = document.getElementById('cd-m');
    const elS = document.getElementById('cd-s');
    const tick = () => {
      const diff = Math.max(0, Math.floor((endTime - Date.now()) / 1000));
      const h = Math.floor(diff / 3600);
      const m = Math.floor((diff % 3600) / 60);
      const s = diff % 60;
      if (elH) elH.textContent = String(h).padStart(2, '0');
      if (elM) elM.textContent = String(m).padStart(2, '0');
      if (elS) elS.textContent = String(s).padStart(2, '0');
    };
    setInterval(tick, 1000);
    tick();
  }

  // ── SCROLL-AWARE HEADER ──
  const siteHeader = document.querySelector('.site-header');
  if (siteHeader) {
    let lastScroll = 0;
    let ticking = false;
    window.addEventListener('scroll', () => {
      if (!ticking) {
        requestAnimationFrame(() => {
          const scroll = window.scrollY;
          if (scroll > 60) {
            siteHeader.classList.add('header-scrolled');
          } else {
            siteHeader.classList.remove('header-scrolled');
            siteHeader.classList.remove('header-hidden');
          }
          if (scroll > lastScroll + 8 && scroll > 200) {
            siteHeader.classList.add('header-hidden');
          } else if (scroll < lastScroll - 5) {
            siteHeader.classList.remove('header-hidden');
          }
          lastScroll = scroll;
          ticking = false;
        });
        ticking = true;
      }
    }, { passive: true });
  }

  // ── MOBILE SEARCH OVERLAY ──
  const searchOverlay = document.getElementById('mm-search-overlay');
  const searchInput = document.getElementById('mm-search-input');
  const searchClose = document.getElementById('mm-search-close');
  const mobileTriggers = document.querySelectorAll('#mobile-search-trigger, #anb-search');

  function openSearch() {
    searchOverlay?.classList.add('open');
    searchOverlay?.removeAttribute('aria-hidden');
    document.body.style.overflow = 'hidden';
    setTimeout(() => searchInput?.focus(), 150);
  }
  function closeSearch() {
    searchOverlay?.classList.remove('open');
    searchOverlay?.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  mobileTriggers.forEach(btn => btn?.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); openSearch(); }));
  searchClose?.addEventListener('click', closeSearch);
  searchOverlay?.addEventListener('click', (e) => { if (e.target === searchOverlay) closeSearch(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeSearch(); });
  /* Delegate: also catch clicks on #anb-search even if DOM not ready at bind time */
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('#anb-search, .anb-search-trigger');
    if (btn) { e.preventDefault(); openSearch(); }
  });

  // Show mobile search trigger — handled via CSS media query now
  // (no JS needed; .mobile-search-trigger shown via @media in premium.css)

  // ── BOTTOM NAV ACTIVE STATE BY URL ──
  const path = window.location.pathname;
  document.querySelectorAll('.app-nav-btn[href]').forEach(btn => {
    const href = btn.getAttribute('href');
    if (href === '/' && path === '/') btn.classList.add('active');
    else if (href !== '/' && path.startsWith(href)) btn.classList.add('active');
  });

  // ── TOUCH RIPPLE ON BUTTONS ──
  document.addEventListener('pointerdown', (e) => {
    const btn = e.target.closest('.quick-add, .flash-add-btn, .checkout-btn, .cart-checkout-btn');
    if (!btn) return;
    const ripple = document.createElement('span');
    const rect = btn.getBoundingClientRect();
    ripple.style.cssText = `position:absolute;border-radius:50%;background:rgba(255,255,255,.4);pointer-events:none;transform:scale(0);animation:ripple .5s ease-out forwards;width:80px;height:80px;left:${e.clientX - rect.left - 40}px;top:${e.clientY - rect.top - 40}px;`;
    btn.style.position = 'relative';
    btn.style.overflow = 'hidden';
    btn.appendChild(ripple);
    setTimeout(() => ripple.remove(), 600);
  });

  // Ripple keyframe injection
  if (!document.getElementById('mm-ripple-style')) {
    const st = document.createElement('style');
    st.id = 'mm-ripple-style';
    st.textContent = '@keyframes ripple{to{transform:scale(3);opacity:0}}';
    document.head.appendChild(st);
  }

  // ── FILTER TABS ──
  document.querySelectorAll('.filter-tab').forEach(tab => {
    tab.addEventListener('click', function() {
      this.closest('.filter-tabs').querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
      this.classList.add('active');
    });
  });

  // ── PRODUCT GALLERY ──
  (function() {
    var imgs = window.__pgImages || [];
    if (!imgs.length) return;

    var cur = 0;
    var pgImg = document.getElementById('pg-img');
    var pgMain = document.getElementById('pg-main');
    var pgThumbs = document.querySelectorAll('.pg-thumb');
    var pgDots = document.querySelectorAll('.pg-dot');

    // Lightbox elements
    var lb = document.getElementById('pg-lb');
    var lbImg = document.getElementById('pg-lb-img');
    var lbCnt = document.getElementById('pg-lb-cnt');
    var lbThs = document.querySelectorAll('.pg-lb-th');

    function setActive(i) {
      cur = (i + imgs.length) % imgs.length;
      // Thumbnails
      pgThumbs.forEach(function(t, idx) { t.classList.toggle('active', idx === cur); });
      pgDots.forEach(function(d, idx) { d.classList.toggle('active', idx === cur); });
      // Main image - fade swap
      if (pgImg) {
        pgImg.style.opacity = '0.5';
        var tmp = new Image();
        tmp.onload = function() { pgImg.src = imgs[cur].mid; pgImg.style.opacity = '1'; };
        tmp.src = imgs[cur].mid;
      }
    }

    // Thumb clicks
    pgThumbs.forEach(function(t, i) {
      t.addEventListener('click', function() { setActive(i); });
      t.addEventListener('mouseenter', function() {
        var p = new Image(); p.src = imgs[i] ? imgs[i].full : '';
      });
    });

    // Dot clicks
    pgDots.forEach(function(d, i) {
      d.addEventListener('click', function() { setActive(i); });
    });

    // Open lightbox on main click
    if (pgMain) {
      pgMain.addEventListener('click', function() { lbOpen(cur); });
    }

    // Lightbox functions
    function lbOpen(i) {
      if (!lb) return;
      cur = (i + imgs.length) % imgs.length;
      lb.classList.add('open');
      document.body.style.overflow = 'hidden';
      lbShow(cur);
    }

    function lbClose() {
      if (!lb) return;
      lb.classList.remove('open');
      document.body.style.overflow = '';
    }

    function lbShow(i) {
      cur = (i + imgs.length) % imgs.length;
      if (!imgs[cur]) return;
      if (lbImg) {
        lbImg.classList.add('fading');
        var full = imgs[cur].full;
        var p = new Image();
        p.onload = function() {
          lbImg.src = full;
          lbImg.classList.remove('fading');
        };
        p.src = full;
      }
      // Lightbox thumbs
      lbThs.forEach(function(t, idx) {
        t.classList.toggle('active', idx === cur);
      });
      // Scroll active thumb into view
      var activeTh = document.querySelector('.pg-lb-th.active');
      if (activeTh) activeTh.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
    }

    // Close button
    var lbX = document.getElementById('pg-lb-x');
    if (lbX) lbX.addEventListener('click', lbClose);

    // No side nav buttons — swipe only
    var lbL = null; var lbR = null;

    // Lightbox thumb clicks
    lbThs.forEach(function(t, i) {
      t.addEventListener('click', function(e) { e.stopPropagation(); lbShow(i); });
    });

    // Click outside to close
    if (lb) lb.addEventListener('click', function(e) { if (e.target === lb) lbClose(); });

    // Keyboard
    document.addEventListener('keydown', function(e) {
      if (!lb || !lb.classList.contains('open')) return;
      if (e.key === 'Escape') lbClose();
      if (e.key === 'ArrowLeft') lbShow(cur - 1);
      if (e.key === 'ArrowRight') lbShow(cur + 1);
    });

    // Touch swipe - lightbox
    var tx0 = 0;
    if (lb) {
      lb.addEventListener('touchstart', function(e) { tx0 = e.touches[0].clientX; }, {passive:true});
      lb.addEventListener('touchend', function(e) {
        var d = tx0 - e.changedTouches[0].clientX;
        if (Math.abs(d) > 40) d > 0 ? lbShow(cur + 1) : lbShow(cur - 1);
      });
    }

    // Touch swipe - main gallery
    var tx1 = 0;
    if (pgMain) {
      pgMain.addEventListener('touchstart', function(e) { tx1 = e.touches[0].clientX; }, {passive:true});
      pgMain.addEventListener('touchend', function(e) {
        var d = tx1 - e.changedTouches[0].clientX;
        if (Math.abs(d) > 40) d > 0 ? setActive(cur + 1) : setActive(cur - 1);
      });
    }

  })();

    // ── PRODUCT TABS ──
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      const panel = this.dataset.tab;
      this.closest('.product-tabs').querySelectorAll('.tab-btn, .tab-content').forEach(el => el.classList.remove('active'));
      this.classList.add('active');
      document.getElementById(panel)?.classList.add('active');
    });
  });

  // ── VARIANT SELECTION ──
  document.querySelectorAll('.variant-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      if (this.classList.contains('sold-out')) return;
      this.closest('.variant-grid').querySelectorAll('.variant-btn').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
    });
  });

  // ── QTY SELECTOR ──
  document.querySelectorAll('.qty-btn-lg').forEach(btn => {
    btn.addEventListener('click', function() {
      const input = this.closest('.qty-selector')?.querySelector('.qty-input');
      if (!input) return;
      const val = parseInt(input.value) || 1;
      const delta = this.dataset.delta === '-1' ? -1 : 1;
      input.value = Math.max(1, val + delta);
    });
  });

  // ── LANGUAGE SWITCHER ──
  window.toggleLangDropdown = function() {
    const dd = document.getElementById('lang-dropdown');
    dd?.classList.toggle('open');
    // close on outside click
    if (dd?.classList.contains('open')) {
      setTimeout(() => {
        document.addEventListener('click', function handler(e) {
          if (!e.target.closest('#lang-switcher-desktop')) {
            dd.classList.remove('open');
            document.removeEventListener('click', handler);
          }
        });
      }, 10);
    }
  };

  window.switchLang = function(lang, flag, code) {
    // Update visual immediately
    const flagEl = document.getElementById('current-flag');
    const codeEl = document.getElementById('current-lang');
    if (flagEl) flagEl.textContent = flag;
    if (codeEl) codeEl.textContent = code;
    document.getElementById('lang-dropdown')?.classList.remove('open');
    document.querySelectorAll('.lang-option').forEach(o => o.classList.remove('active'));
    document.querySelectorAll('.mm-lang').forEach(o => { o.classList.toggle('active', o.textContent.trim() === code); });
    // Submit localization form (Shopify Markets)
    const form = document.getElementById('lang-form-localization');
    if (form) {
      form.querySelector('[name="locale_code"]').value = lang;
      form.querySelector('[name="return_to"]').value = window.location.pathname + window.location.search;
      form.submit();
    }
  };

  // ── STAR PICKER ──
  const stars = document.querySelectorAll('.star-pick');
  let currentRating = 0;
  stars.forEach((star, i) => {
    star.addEventListener('mouseover', () => stars.forEach((s, j) => s.classList.toggle('lit', j <= i)));
    star.addEventListener('mouseout', () => stars.forEach((s, j) => s.classList.toggle('lit', j < currentRating)));
    star.addEventListener('click', () => {
      currentRating = i + 1;
      stars.forEach((s, j) => s.classList.toggle('lit', j < currentRating));
      document.getElementById('review-rating-input').value = currentRating;
    });
  });

  // ── REVIEW MODAL ──
  window.openReviewModal = function() {
    document.getElementById('review-modal')?.classList.add('open');
    document.body.style.overflow = 'hidden';
  };
  window.closeReviewModal = function() {
    document.getElementById('review-modal')?.classList.remove('open');
    document.body.style.overflow = '';
  };
  document.getElementById('review-modal')?.addEventListener('click', e => { if (e.target === e.currentTarget) closeReviewModal(); });

  // ── PHOTO UPLOAD ──
  const photoInput = document.getElementById('review-photos');
  const photoPreviews = document.getElementById('photo-previews');
  const photoUploadArea = document.querySelector('.photo-upload-area');

  photoUploadArea?.addEventListener('click', () => photoInput?.click());
  photoUploadArea?.addEventListener('dragover', e => { e.preventDefault(); photoUploadArea.style.borderColor = 'var(--gl)'; });
  photoUploadArea?.addEventListener('dragleave', () => { photoUploadArea.style.borderColor = ''; });
  photoUploadArea?.addEventListener('drop', e => { e.preventDefault(); photoUploadArea.style.borderColor = ''; handleFiles(e.dataTransfer.files); });
  photoInput?.addEventListener('change', e => handleFiles(e.target.files));

  function handleFiles(files) {
    Array.from(files).slice(0, 5).forEach(file => {
      if (!file.type.startsWith('image/')) return;
      const reader = new FileReader();
      reader.onload = e => {
        const item = document.createElement('div');
        item.className = 'photo-preview-item';
        item.innerHTML = `<img src="${e.target.result}" alt="Preview"><button class="photo-remove" onclick="this.parentElement.remove()" type="button">×</button>`;
        photoPreviews?.appendChild(item);
      };
      reader.readAsDataURL(file);
    });
  }

  // ── SUBMIT REVIEW ──
  document.getElementById('review-form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const name = document.getElementById('review-name').value;
    const title = document.getElementById('review-title').value;
    const text = document.getElementById('review-text').value;
    const rating = document.getElementById('review-rating-input').value;

    if (!rating || rating === '0') { alert('Veuillez sélectionner une note en étoiles.'); return; }

    const photos = Array.from(document.querySelectorAll('#photo-previews img')).map(img => img.src);
    const stars = '★'.repeat(parseInt(rating)) + '☆'.repeat(5 - parseInt(rating));
    const date = new Date().toLocaleDateString('fr-FR', { year: 'numeric', month: 'long', day: 'numeric' });
    const initials = name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);

    const newCard = document.createElement('div');
    newCard.className = 'review-card';
    newCard.innerHTML = `
      <div class="review-card-header">
        <div class="review-avatar">${initials}</div>
        <div class="review-meta">
          <div class="review-name">${escHtml(name)} <span class="review-verified">✓ Vérifié</span></div>
          <div class="review-date">${date}</div>
        </div>
        <div class="review-stars">${stars}</div>
      </div>
      <div class="review-title">${escHtml(title)}</div>
      <div class="review-text">${escHtml(text)}</div>
      ${photos.length ? `<div class="review-photos">${photos.map(src => `<div class="review-photo"><img src="${src}" alt="Photo avis"></div>`).join('')}</div>` : ''}
    `;
    document.getElementById('reviews-container')?.prepend(newCard);
    closeReviewModal();
    this.reset();
    document.querySelectorAll('.star-pick').forEach(s => s.classList.remove('lit'));
    currentRating = 0;
    document.getElementById('photo-previews').innerHTML = '';
    document.getElementById('review-rating-input').value = '0';
    document.getElementById('reviews-anchor')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
  }

});

// Sync app bottom nav cart count
function syncAppNavCount(count) {
  document.querySelectorAll('.app-nav-cart-count').forEach(el => {
    el.textContent = count;
    el.style.display = count > 0 ? 'flex' : 'none';
  });
}

// Override refreshCart to also sync app nav
const _origRefresh = window._refreshCartFn;

// Patch updateCartCount
const origUpdate = window.updateCartCount;
window.updateCartCount = function(count) {
  document.querySelectorAll('.cart-count').forEach(el => {
    el.textContent = count;
    el.style.display = count > 0 ? 'flex' : 'none';
  });
  document.querySelectorAll('.cart-sticky-count').forEach(el => el.textContent = count);
  syncAppNavCount(count);
};

// Active nav item based on URL
document.addEventListener('DOMContentLoaded', () => {
  const path = window.location.pathname;
  document.querySelectorAll('.app-nav-btn[href]').forEach(btn => {
    const href = btn.getAttribute('href');
    if (href === '/' && path === '/') btn.classList.add('active');
    else if (href !== '/' && path.startsWith(href)) btn.classList.add('active');
    else btn.classList.remove('active');
  });
});
