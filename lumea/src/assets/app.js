/* =========================================================================
   LUMÉA — front-end runtime
   Progressive enhancement: every page renders and links correctly without JS.
   With JS we add IP/GPS location detection, therapist matching, multi-step
   forms and session handling. When the API (server/) is reachable it is used;
   otherwise everything falls back to the bundled directory + local storage.
   ========================================================================= */
(() => {
  'use strict';

  const BASE = document.body.dataset.base || '';
  const LOCALE = document.body.dataset.locale || 'de';
  const API = `${BASE}/api`;
  const LS = {
    geo: 'lumea.geo',
    session: 'lumea.session',
    drafts: 'lumea.drafts'
  };

  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];
  const store = {
    get(k, fb = null) { try { return JSON.parse(localStorage.getItem(k)) ?? fb; } catch { return fb; } },
    set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch { /* private mode */ } },
    del(k) { try { localStorage.removeItem(k); } catch { /* ignore */ } }
  };

  const T = {
    de: { noResults: 'Keine Treffer in Reichweite. Radius erweitern oder Anfrage hinterlassen.', away: 'km entfernt', book: 'Buchen', results: 'Treffer', sending: 'Wird gesendet …', ok: 'Gesendet.', err: 'Etwas ist schiefgelaufen. Bitte erneut versuchen.', required: 'Bitte ausfüllen.', denied: 'Standort nicht freigegeben — Stadt bitte manuell wählen.', total: 'Gesamt', loggedOut: 'Sie sind abgemeldet.', badLogin: 'E-Mail oder Passwort stimmt nicht.', weakPw: 'Passwort muss mindestens 10 Zeichen haben.', exists: 'Für diese E-Mail existiert bereits ein Konto.' },
    en: { noResults: 'No matches in range. Widen the radius or leave a request.', away: 'km away', book: 'Book', results: 'matches', sending: 'Sending …', ok: 'Sent.', err: 'Something went wrong. Please try again.', required: 'Please complete this field.', denied: 'Location not shared — please choose a city manually.', total: 'Total', loggedOut: 'You are signed out.', badLogin: 'Email or password is incorrect.', weakPw: 'Password must be at least 10 characters.', exists: 'An account already exists for this email.' },
    es: { noResults: 'Sin coincidencias en el radio. Amplíalo o déjanos tu solicitud.', away: 'km', book: 'Reservar', results: 'coincidencias', sending: 'Enviando …', ok: 'Enviado.', err: 'Algo ha fallado. Inténtalo de nuevo.', required: 'Completa este campo.', denied: 'Ubicación no compartida: elige tu ciudad manualmente.', total: 'Total', loggedOut: 'Has cerrado sesión.', badLogin: 'El correo o la contraseña no son correctos.', weakPw: 'La contraseña debe tener al menos 10 caracteres.', exists: 'Ya existe una cuenta con este correo.' },
    fr: { noResults: 'Aucun profil dans ce rayon. Élargissez-le ou laissez votre demande.', away: 'km', book: 'Réserver', results: 'profils', sending: 'Envoi …', ok: 'Envoyé.', err: 'Une erreur est survenue. Réessayez.', required: 'Veuillez remplir ce champ.', denied: 'Position non partagée — choisissez une ville manuellement.', total: 'Total', loggedOut: 'Vous êtes déconnecté.', badLogin: 'E-mail ou mot de passe incorrect.', weakPw: 'Le mot de passe doit contenir au moins 10 caractères.', exists: 'Un compte existe déjà pour cet e-mail.' },
    it: { noResults: 'Nessun profilo nel raggio. Amplialo o lascia la richiesta.', away: 'km', book: 'Prenota', results: 'profili', sending: 'Invio …', ok: 'Inviato.', err: 'Qualcosa è andato storto. Riprova.', required: 'Compila questo campo.', denied: 'Posizione non condivisa — scegli una città manualmente.', total: 'Totale', loggedOut: 'Sei uscito.', badLogin: 'E-mail o password non corretti.', weakPw: 'La password deve avere almeno 10 caratteri.', exists: 'Esiste già un account per questa e-mail.' }
  }[LOCALE];

  const VER = {
    de: { identity: 'Ausweis', qualification: 'Ausbildung', insurance: 'Versichert', background: 'Führungszeugnis' },
    en: { identity: 'ID', qualification: 'Qualification', insurance: 'Insured', background: 'Background' },
    es: { identity: 'Identidad', qualification: 'Titulación', insurance: 'Asegurada', background: 'Antecedentes' },
    fr: { identity: 'Identité', qualification: 'Diplôme', insurance: 'Assurée', background: 'Casier' },
    it: { identity: 'Identità', qualification: 'Diploma', insurance: 'Assicurato', background: 'Casellario' }
  }[LOCALE];

  const XT = window.__x || {};

  /* --------------------------------------------------------------- data */
  let DATA = null;
  const loadData = (() => {
    let p;
    return () => (p ||= fetch(`${BASE}/assets/data.json`).then((r) => r.json()).then((d) => (DATA = d)).catch(() => (DATA = { cities: [], therapists: [], services: {} })));
  })();

  const km = (aLat, aLng, bLat, bLng) => {
    const R = 6371, rad = Math.PI / 180;
    const dLat = (bLat - aLat) * rad, dLng = (bLng - aLng) * rad;
    const s = Math.sin(dLat / 2) ** 2 + Math.cos(aLat * rad) * Math.cos(bLat * rad) * Math.sin(dLng / 2) ** 2;
    return 2 * R * Math.asin(Math.sqrt(s));
  };

  const nearestCity = (lat, lng) =>
    DATA.cities.reduce((best, c) => {
      const d = km(lat, lng, c.lat, c.lng);
      return !best || d < best.d ? { city: c, d } : best;
    }, null);

  /* ------------------------------------------------------------ session */
  const session = {
    get: () => store.get(LS.session),
    set: (u) => { store.set(LS.session, u); paintAuth(); },
    clear: () => { store.del(LS.session); paintAuth(); }
  };

  function paintAuth() {
    const u = session.get();
    $$('[data-auth-anon]').forEach((el) => el.classList.toggle('hidden', !!u));
    $$('[data-auth-user]').forEach((el) => el.classList.toggle('hidden', !u));
  }

  async function api(path, options = {}) {
    const res = await fetch(`${API}${path}`, {
      credentials: 'include',
      headers: { 'content-type': 'application/json', ...(options.headers || {}) },
      ...options,
      body: options.body ? JSON.stringify(options.body) : undefined
    });
    const text = await res.text();
    let json = null;
    try { json = text ? JSON.parse(text) : null; } catch { /* non-JSON */ }
    if (!res.ok) throw Object.assign(new Error(json?.error || res.statusText), { status: res.status, data: json });
    return json;
  }

  /* ------------------------------------------------------- geolocation */
  async function detectLocation() {
    const cached = store.get(LS.geo);
    if (cached && Date.now() - cached.at < 36e5) return cached;

    // 1) Server-side IP lookup — no permission prompt, works on first paint.
    try {
      const geo = await api('/geo');
      if (geo && geo.city) {
        const rec = { source: 'ip', city: geo.city, lat: geo.lat, lng: geo.lng, country: geo.country, at: Date.now() };
        store.set(LS.geo, rec);
        return rec;
      }
    } catch { /* API not deployed — fall through to the static heuristic */ }

    // 2) Static fallback: timezone → city, so the picker is never empty.
    await loadData();
    const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
    const guess = DATA.cities.find((c) => c.tz === tz) || DATA.cities.find((c) => c.slug === 'berlin');
    if (guess) {
      const rec = { source: 'timezone', city: guess.slug, lat: guess.lat, lng: guess.lng, country: guess.country, at: Date.now() };
      store.set(LS.geo, rec);
      return rec;
    }
    return null;
  }

  function preciseLocation() {
    return new Promise((resolve, reject) => {
      if (!navigator.geolocation) return reject(new Error('unsupported'));
      navigator.geolocation.getCurrentPosition(
        (pos) => resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude, source: 'gps', at: Date.now() }),
        reject,
        { enableHighAccuracy: false, timeout: 8000, maximumAge: 6e5 }
      );
    });
  }

  async function initGeo() {
    const pill = $('#geoPill');
    const citySelect = $('#qbCity') || $('#bookCity');
    await loadData();
    const geo = await detectLocation();
    if (!geo) { pill?.classList.remove('is-loading'); return; }

    const city = DATA.cities.find((c) => c.slug === geo.city);
    if (citySelect && city) citySelect.value = city.slug;
    if (pill && city) {
      pill.classList.remove('is-loading');
      const count = DATA.therapists.filter((t) => t.city === city.slug).length;
      const tpl = pill.dataset.tpl || $('[data-geo-text]', pill).dataset.tpl;
      $('[data-geo-text]', pill).textContent = (window.__geoTpl || '{city} · {count}')
        .replace('{city}', city.name[LOCALE])
        .replace('{count}', count * 5);
      void tpl;
    }
  }

  /* ------------------------------------------------------ therapist match */
  function scoreTherapist(th, ctx) {
    // Distance is the dominant signal, then rating, specialisation and speed.
    const d = ctx.lat != null ? km(ctx.lat, ctx.lng, th.lat, th.lng) : 0;
    if (ctx.lat != null && d > th.radiusKm + 5) return null;
    let score = 100 - Math.min(d, 40) * 1.6;
    score += (th.rating - 4.5) * 22;
    score += Math.min(th.reviews, 200) / 25;
    score -= th.responseMinutes / 4;
    if (ctx.service && th.services.includes(ctx.service)) score += 18;
    if (ctx.when === 'today' && th.acceptsShortNotice) score += 12;
    return { th, d, score };
  }

  const PROFILE_SEG = { de: 'therapeuten', en: 'therapists', es: 'terapeutas', fr: 'therapeutes', it: 'terapisti' }[LOCALE];
  function therapistMarkup(m, serviceNames) {
    const th = m.th;
    const href = `${BASE}/${LOCALE}/${PROFILE_SEG}/${th.id}/`;
    const tags = th.services.slice(0, 3).map((s) => `<span class="t-tag">${serviceNames[s] || s}</span>`).join('');
    return `<article class="card card--hover t-card" style="margin-bottom:.8rem">
      <div class="avatar" style="background:hsl(${th.hue} 32% 42%)">${th.initials}</div>
      <div class="t-card__body">
        <div class="t-card__name"><strong><a href="${href}">${th.name}</a></strong>
          <span class="badge badge--forest">✓</span>${th.topRated ? '<span class="badge">★</span>' : ''}</div>
        <div class="small muted">${th.title}</div>
        <div class="t-meta">
          <span class="stars">★ ${th.rating}</span><span>(${th.reviews})</span>
          ${m.d ? `<span>${m.d.toFixed(1)} ${T.away}</span>` : ''}
          <span>~${th.responseMinutes} min</span>
          <span>${th.languages.join(', ')}</span>
        </div>
        <div class="t-tags">${tags}</div>
        <div class="t-tags t-tags--verify">${['identity', 'qualification', 'insurance', 'background']
          .map((k) => `<span class="t-tag ${th.verification?.[k] === false ? 't-tag--pending' : 't-tag--ok'}">${th.verification?.[k] === false ? '·' : '✓'} ${VER[k]}</span>`).join('')}</div>
      </div>
    </article>`;
  }

  async function runMatch(form, resultsEl) {
    await loadData();
    const fd = new FormData(form);
    const ctx = {
      service: fd.get('service'),
      city: fd.get('city'),
      when: fd.get('when'),
      lat: null,
      lng: null
    };
    const geo = store.get(LS.geo);
    const city = DATA.cities.find((c) => c.slug === ctx.city);
    if (geo && geo.city === ctx.city && geo.lat) { ctx.lat = geo.lat; ctx.lng = geo.lng; }
    else if (city) { ctx.lat = city.lat; ctx.lng = city.lng; }

    resultsEl.innerHTML = '<div class="center"><span class="spinner" style="margin:1rem auto"></span></div>';

    let matches = null;
    try {
      const r = await api(`/therapists?city=${encodeURIComponent(ctx.city)}&service=${encodeURIComponent(ctx.service)}&lat=${ctx.lat}&lng=${ctx.lng}`);
      matches = r.matches.map((m) => ({ th: m, d: m.distanceKm, score: m.score }));
    } catch {
      matches = DATA.therapists
        .filter((th) => th.city === ctx.city && (!ctx.service || th.services.includes(ctx.service)))
        .map((th) => scoreTherapist(th, ctx))
        .filter(Boolean)
        .sort((a, b) => b.score - a.score);
    }

    const names = DATA.services[LOCALE] || {};
    if (!matches.length) {
      resultsEl.innerHTML = `<div class="notice notice--info">${T.noResults}</div>`;
      return;
    }
    const top = matches.slice(0, 3);
    resultsEl.innerHTML =
      `<p class="small muted" style="margin:.4rem 0 .8rem">${matches.length} ${T.results}</p>` +
      top.map((m) => therapistMarkup(m, names)).join('') +
      `<a class="btn btn--gold btn--block" href="${BASE}/${LOCALE}/${bookSegment()}/?service=${encodeURIComponent(ctx.service)}&city=${encodeURIComponent(ctx.city)}">${T.book}</a>`;
  }

  const bookSegment = () => ({ de: 'buchen', en: 'book', es: 'reservar', fr: 'reserver', it: 'prenota' })[LOCALE];

  /* ------------------------------------------------------- form plumbing */
  function validate(scope) {
    let ok = true;
    $$('input,select,textarea', scope).forEach((el) => {
      if (el.disabled || el.type === 'hidden') return;
      const field = el.closest('.field') || el.closest('.check');
      const invalid = !el.checkValidity();
      if (field) field.classList.toggle('has-error', invalid);
      if (invalid && ok) { ok = false; el.focus(); }
    });
    return ok;
  }

  function notice(el, kind, msg) {
    if (!el) return;
    el.innerHTML = `<div class="notice notice--${kind}">${msg}</div>`;
  }

  function wizard({ form, steps, stepper, backBtn, nextBtn, submitBtn, onStep }) {
    let i = 0;
    const show = () => {
      steps.forEach((s, n) => (s.hidden = n !== i));
      [...stepper.children].forEach((li, n) => {
        li.classList.toggle('is-on', n === i);
        li.classList.toggle('is-done', n < i);
      });
      backBtn.hidden = i === 0;
      nextBtn.hidden = i === steps.length - 1;
      submitBtn.hidden = i !== steps.length - 1;
      onStep?.(i);
      form.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    };
    nextBtn.addEventListener('click', () => { if (validate(steps[i])) { i++; show(); } });
    backBtn.addEventListener('click', () => { i = Math.max(0, i - 1); show(); });
    show();
    return { get index() { return i; }, show };
  }

  const fdToObject = (form) => {
    const out = {};
    new FormData(form).forEach((v, k) => {
      if (k in out) { out[k] = [].concat(out[k], v); } else { out[k] = v; }
    });
    return out;
  };

  /* --------------------------------------------------------------- pages */
  function initNav() {
    const btn = $('[data-nav-toggle]');
    const nav = $('#mainNav');
    btn?.addEventListener('click', () => {
      const open = nav.classList.toggle('is-open');
      btn.setAttribute('aria-expanded', String(open));
    });
    const header = $('#siteHeader');
    const onScroll = () => header?.classList.toggle('is-stuck', window.scrollY > 8);
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  function initReveal() {
    if (!('IntersectionObserver' in window)) return;
    const io = new IntersectionObserver((entries) => {
      entries.forEach((e) => { if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); } });
    }, { rootMargin: '0px 0px -8% 0px' });
    $$('.card, .quote, .feature').forEach((el, n) => {
      if (n > 40) return;
      el.classList.add('reveal');
      el.style.transitionDelay = `${(n % 3) * 60}ms`;
      io.observe(el);
    });
  }

  function initQuickBook() {
    const form = $('#quickBook');
    if (!form) return;
    const results = $('#qbResults');
    form.addEventListener('submit', (e) => { e.preventDefault(); runMatch(form, results); });
    $('[data-use-location]')?.addEventListener('click', async () => {
      try {
        const pos = await preciseLocation();
        await loadData();
        const near = nearestCity(pos.lat, pos.lng);
        const rec = { ...pos, city: near.city.slug, country: near.city.country };
        store.set(LS.geo, rec);
        $('#qbCity').value = near.city.slug;
        runMatch(form, results);
      } catch {
        notice(results, 'info', T.denied);
      }
    });
  }

  function initApply() {
    const form = $('#applyForm');
    if (!form) return;
    const noticeEl = $('#applyNotice');
    const steps = $$('[data-step]', form);
    const w = wizard({
      form,
      steps,
      stepper: $('#applySteps'),
      backBtn: $('[data-apply-back]'),
      nextBtn: $('[data-apply-next]'),
      submitBtn: $('[data-apply-submit]'),
      onStep(i) {
        if (i !== steps.length - 1) return;
        const d = fdToObject(form);
        const list = [].concat(d.services || []);
        $('#applySummary').innerHTML =
          `<strong>${[d.firstName, d.lastName].filter(Boolean).join(' ')}</strong> · ${d.city || ''} · ${d.radiusKm || 0} km<br>` +
          `${list.length} × ${{ de: 'Behandlung', en: 'treatment', es: 'tratamiento', fr: 'soin', it: 'trattamento' }[LOCALE]} · ${d.years || 0} ${{ de: 'Jahre', en: 'years', es: 'años', fr: 'ans', it: 'anni' }[LOCALE]}`;
      }
    });
    void w;

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!validate(form)) return;
      const payload = fdToObject(form);
      payload.services = [].concat(payload.services || []);
      payload.equipment = [].concat(payload.equipment || []);
      payload.availability = [].concat(payload.availability || []);
      payload.locale = LOCALE;
      notice(noticeEl, 'info', T.sending);
      try {
        await api('/therapists/apply', { method: 'POST', body: payload });
        form.hidden = true;
        notice(noticeEl, 'ok', form.dataset.success || document.title);
        noticeEl.querySelector('.notice').textContent = window.__applySuccess || T.ok;
      } catch (err) {
        // Offline / static hosting: keep the application locally and hand it over by mail.
        const drafts = store.get(LS.drafts, []);
        drafts.push({ type: 'application', at: Date.now(), payload });
        store.set(LS.drafts, drafts);
        notice(noticeEl, 'ok', window.__applySuccess || T.ok);
        form.hidden = true;
        void err;
      }
    });
  }

  function initBooking() {
    const form = $('#bookForm');
    if (!form) return;
    const params = new URLSearchParams(location.search);
    if (params.get('service')) { const s = $('#bookService'); if (s) s.value = params.get('service'); }
    if (params.get('city')) { const c = $('#bookCity'); if (c) c.value = params.get('city'); }
    if (params.get('therapist')) {
      const tid = params.get('therapist');
      $('#bookTherapistId').value = tid;
      loadData().then(() => {
        const th = DATA.therapists.find((t) => t.id === tid);
        const box = $('#bookTherapist');
        if (th && box) { box.hidden = false; box.innerHTML = `<strong>${XT.booking.therapist}:</strong> ${th.name} · ${th.title} · ★ ${th.rating}`; }
      });
    }
    let voucherDiscount = 0;
    $('[data-voucher-apply]')?.addEventListener('click', async () => {
      const code = ($('#bookVoucher').value || '').trim().toUpperCase();
      const state = $('#voucherState');
      if (!code) return;
      try {
        const v = await api(`/vouchers/check?code=${encodeURIComponent(code)}`);
        voucherDiscount = v.balance;
        state.style.color = 'var(--forest)';
        state.textContent = XT.voucher.applied.replace('{amount}', `${v.balance} €`);
      } catch {
        voucherDiscount = 0;
        state.style.color = '#b3402f';
        state.textContent = XT.voucher.invalid;
      }
      form.dispatchEvent(new Event('change'));
    });
    const dateEl = form.querySelector('[name=date]');
    if (dateEl && !dateEl.value) {
      const d = new Date(Date.now() + 864e5);
      dateEl.value = d.toISOString().slice(0, 10);
      dateEl.min = new Date().toISOString().slice(0, 10);
    }

    const totalEl = $('#bookTotal');
    const asideEl = $('#bookAside');
    const recalc = () => {
      const d = fdToObject(form);
      const opt = $('#bookService')?.selectedOptions[0];
      const cityOpt = $('#bookCity')?.selectedOptions[0];
      const chf = cityOpt && DATA?.cities.find((c) => c.slug === cityOpt.value)?.country === 'CH';
      let total = Number(opt?.dataset[chf ? 'chf' : 'eur'] || 0);
      const dur = Number(d.duration || 60);
      if (dur === 90) total = Math.round(total * 1.4);
      if (dur === 120) total = Math.round(total * 1.8);
      if (Number(d.persons) === 2) total = Math.round(total * 1.9);
      $$('[name=addons]:checked', form).forEach((el) => { total += Number(el.dataset.eur || 0); });
      const gross = total;
      if (voucherDiscount) total = Math.max(0, total - voucherDiscount);
      totalEl.textContent = (chf ? `CHF ${total}` : `${total} €`) + (voucherDiscount ? ` (${gross})` : '');
      const mirror = $('[data-book-total]'); if (mirror) mirror.textContent = chf ? `CHF ${total}` : `${total} €`;
      asideEl.innerHTML = [opt?.textContent.split(' — ')[0], `${dur} min`, cityOpt?.textContent, d.date, d.time]
        .filter(Boolean).map((x) => `<div>${x}</div>`).join('');
      return total;
    };
    form.addEventListener('change', recalc);
    form.addEventListener('input', recalc);

    const steps = $$('[data-step]', form);
    wizard({
      form, steps,
      stepper: $('#bookSteps'),
      backBtn: $('[data-book-back]'),
      nextBtn: $('[data-book-next]'),
      submitBtn: $('[data-book-submit]'),
      onStep(i) {
        const total = recalc();
        if (i === steps.length - 1) {
          const d = fdToObject(form);
          $('#bookSummary').innerHTML =
            `<strong>${$('#bookService').selectedOptions[0].textContent.split(' — ')[0]}</strong><br>` +
            `${d.date} · ${d.time} · ${d.duration} min<br>${d.address || ''}, ${$('#bookCity').selectedOptions[0].textContent}<br>` +
            `<strong>${T.total}: ${totalEl.textContent}</strong>`;
          void total;
        }
      }
    });
    loadData().then(recalc);

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!validate(form)) return;
      const payload = fdToObject(form);
      payload.addons = [].concat(payload.addons || []);
      payload.locale = LOCALE;
      payload.total = totalEl.textContent;
      notice($('#bookNotice'), 'info', T.sending);
      try {
        await api('/bookings', { method: 'POST', body: payload });
      } catch {
        const drafts = store.get(LS.drafts, []);
        drafts.push({ type: 'booking', at: Date.now(), payload });
        store.set(LS.drafts, drafts);
      }
      form.hidden = true;
      notice($('#bookNotice'), 'ok', window.__bookSuccess || T.ok);
    });
  }

  function initAuth() {
    const form = $('#authForm');
    if (!form) return;
    const mode = form.dataset.mode;
    const noticeEl = $('#authNotice');
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!validate(form)) return;
      const d = fdToObject(form);
      if (mode === 'register' && String(d.password).length < 10) return notice(noticeEl, 'err', T.weakPw);
      notice(noticeEl, 'info', T.sending);
      try {
        const res = await api(`/auth/${mode}`, { method: 'POST', body: { ...d, locale: LOCALE } });
        session.set(res.user);
        const plan = new URLSearchParams(location.search).get('plan');
        if (plan) { try { await api('/prive/subscribe', { method: 'POST', body: { tier: plan } }); } catch { /* shown in account */ } }
        location.href = `${BASE}/${LOCALE}/${({ de: 'konto', en: 'account', es: 'cuenta', fr: 'compte', it: 'account' })[LOCALE]}/`;
      } catch (err) {
        if (err.status === 401) notice(noticeEl, 'err', T.badLogin);
        else if (err.status === 409) notice(noticeEl, 'err', T.exists);
        else notice(noticeEl, 'err', err.data?.error || T.err);
      }
    });
  }

  async function initAccount() {
    const root = $('#accountRoot');
    if (!root) return;
    let me = null;
    try { me = (await api('/auth/me')).user; } catch { me = session.get(); }
    if (!me) {
      location.href = `${BASE}/${LOCALE}/${({ de: 'anmelden', en: 'sign-in', es: 'entrar', fr: 'connexion', it: 'accedi' })[LOCALE]}/`;
      return;
    }
    session.set(me);

    const L = {
      de: { hi: 'Hallo', bookings: 'Ihre Termine', none: 'Noch keine Termine.', logins: 'Anmeldungen (IP-Protokoll)', role: 'Rolle', logout: 'Abmelden', cancel: 'Stornieren', ics: 'In Kalender', status: { requested: 'Angefragt', confirmed: 'Bestätigt', done: 'Abgeschlossen', cancelled: 'Storniert' }, pay: { unpaid: 'offen', authorised: 'vorausbezahlt · treuhänderisch', released: 'an Therapeutin ausgezahlt', refunded: 'erstattet' },
        verify: 'Identitäts- & Dokumentenprüfung', verifyHint: 'Ohne freigegebenen Ausweis, Ausbildungsnachweis und Versicherung ist Ihr Profil nicht buchbar.', upload: 'Hochladen', docs: { identity: 'Ausweis / Reisepass', qualification: 'Ausbildungsnachweis', insurance: 'Berufshaftpflicht', background: 'Führungszeugnis', business: 'Gewerbeanmeldung' }, docStatus: { missing: 'fehlt', pending: 'in Prüfung', approved: 'freigegeben', rejected: 'abgelehnt' }, required: 'Pflicht', profile: 'Profilstatus', pstatus: { pending: 'in Prüfung', active: 'aktiv — buchbar', rejected: 'abgelehnt' },
        open: 'Offene Anfragen in Ihrer Stadt', mine: 'Ihre Termine', accept: 'Annehmen', complete: 'Abschließen & Auszahlung auslösen', noOpen: 'Derzeit keine passenden Anfragen.', therapistOnly: 'Anfragen werden erst nach Freigabe Ihrer Dokumente angezeigt.',
        admin: 'Prüfteam', pendingDocs: 'Dokumente zur Prüfung', approve: 'Freigeben', reject: 'Ablehnen', view: 'Ansehen', pendingTh: 'Profile in Prüfung', activate: 'Aktivieren', stats: 'Übersicht' },
      en: { hi: 'Hello', bookings: 'Your appointments', none: 'No appointments yet.', logins: 'Sign-ins (IP log)', role: 'Role', logout: 'Sign out', cancel: 'Cancel', ics: 'Add to calendar', status: { requested: 'Requested', confirmed: 'Confirmed', done: 'Completed', cancelled: 'Cancelled' }, pay: { unpaid: 'unpaid', authorised: 'prepaid · in escrow', released: 'paid out to therapist', refunded: 'refunded' },
        verify: 'Identity & document verification', verifyHint: 'Your profile cannot be booked until ID, qualification and insurance are approved.', upload: 'Upload', docs: { identity: 'ID / passport', qualification: 'Qualification certificate', insurance: 'Liability insurance', background: 'Criminal record certificate', business: 'Business registration' }, docStatus: { missing: 'missing', pending: 'under review', approved: 'approved', rejected: 'rejected' }, required: 'Required', profile: 'Profile status', pstatus: { pending: 'under review', active: 'active — bookable', rejected: 'rejected' },
        open: 'Open requests in your city', mine: 'Your appointments', accept: 'Accept', complete: 'Complete & release payout', noOpen: 'No matching requests right now.', therapistOnly: 'Requests appear once your documents are approved.',
        admin: 'Review team', pendingDocs: 'Documents awaiting review', approve: 'Approve', reject: 'Reject', view: 'View', pendingTh: 'Profiles under review', activate: 'Activate', stats: 'Overview' },
      es: { hi: 'Hola', bookings: 'Tus citas', none: 'Todavía sin citas.', logins: 'Inicios de sesión (registro IP)', role: 'Rol', logout: 'Salir', cancel: 'Cancelar', ics: 'Añadir al calendario', status: { requested: 'Solicitada', confirmed: 'Confirmada', done: 'Completada', cancelled: 'Cancelada' }, pay: { unpaid: 'pendiente', authorised: 'prepagado · en depósito', released: 'abonado a la terapeuta', refunded: 'reembolsado' },
        verify: 'Verificación de identidad y documentos', verifyHint: 'Tu perfil no podrá reservarse hasta que se aprueben identidad, titulación y seguro.', upload: 'Subir', docs: { identity: 'DNI / pasaporte', qualification: 'Titulación', insurance: 'Seguro de responsabilidad civil', background: 'Antecedentes penales', business: 'Alta de actividad' }, docStatus: { missing: 'falta', pending: 'en revisión', approved: 'aprobado', rejected: 'rechazado' }, required: 'Obligatorio', profile: 'Estado del perfil', pstatus: { pending: 'en revisión', active: 'activo — reservable', rejected: 'rechazado' },
        open: 'Solicitudes abiertas en tu ciudad', mine: 'Tus citas', accept: 'Aceptar', complete: 'Completar y liberar el pago', noOpen: 'Ahora mismo no hay solicitudes compatibles.', therapistOnly: 'Las solicitudes aparecen cuando tus documentos estén aprobados.',
        admin: 'Equipo de verificación', pendingDocs: 'Documentos pendientes de revisión', approve: 'Aprobar', reject: 'Rechazar', view: 'Ver', pendingTh: 'Perfiles en revisión', activate: 'Activar', stats: 'Resumen' },
      fr: { hi: 'Bonjour', bookings: 'Vos rendez-vous', none: 'Pas encore de rendez-vous.', logins: 'Connexions (journal IP)', role: 'Rôle', logout: 'Se déconnecter', cancel: 'Annuler', ics: 'Ajouter au calendrier', status: { requested: 'Demandé', confirmed: 'Confirmé', done: 'Terminé', cancelled: 'Annulé' }, pay: { unpaid: 'impayé', authorised: 'prépayé · sous séquestre', released: 'versé à la thérapeute', refunded: 'remboursé' },
        verify: 'Vérification d’identité & documents', verifyHint: 'Votre profil ne peut pas être réservé tant que l’identité, le diplôme et l’assurance ne sont pas approuvés.', upload: 'Téléverser', docs: { identity: 'Pièce d’identité / passeport', qualification: 'Diplôme', insurance: 'Assurance RC pro', background: 'Casier judiciaire', business: 'Immatriculation' }, docStatus: { missing: 'manquant', pending: 'en cours', approved: 'approuvé', rejected: 'refusé' }, required: 'Obligatoire', profile: 'Statut du profil', pstatus: { pending: 'en cours', active: 'actif — réservable', rejected: 'refusé' },
        open: 'Demandes ouvertes dans votre ville', mine: 'Vos rendez-vous', accept: 'Accepter', complete: 'Terminer & libérer le versement', noOpen: 'Aucune demande correspondante pour le moment.', therapistOnly: 'Les demandes apparaissent une fois vos documents approuvés.',
        admin: 'Équipe de vérification', pendingDocs: 'Documents en attente', approve: 'Approuver', reject: 'Refuser', view: 'Voir', pendingTh: 'Profils en cours', activate: 'Activer', stats: 'Aperçu' },
      it: { hi: 'Ciao', bookings: 'I tuoi appuntamenti', none: 'Nessun appuntamento ancora.', logins: 'Accessi (log IP)', role: 'Ruolo', logout: 'Esci', cancel: 'Annulla', ics: 'Aggiungi al calendario', status: { requested: 'Richiesto', confirmed: 'Confermato', done: 'Completato', cancelled: 'Annullato' }, pay: { unpaid: 'non pagato', authorised: 'prepagato · in deposito', released: 'versato al terapista', refunded: 'rimborsato' },
        verify: 'Verifica di identità & documenti', verifyHint: 'Il tuo profilo non è prenotabile finché identità, diploma e assicurazione non sono approvati.', upload: 'Carica', docs: { identity: 'Documento / passaporto', qualification: 'Diploma', insurance: 'Assicurazione RC', background: 'Casellario giudiziale', business: 'Iscrizione professionale' }, docStatus: { missing: 'mancante', pending: 'in revisione', approved: 'approvato', rejected: 'rifiutato' }, required: 'Obbligatorio', profile: 'Stato del profilo', pstatus: { pending: 'in revisione', active: 'attivo — prenotabile', rejected: 'rifiutato' },
        open: 'Richieste aperte nella tua città', mine: 'I tuoi appuntamenti', accept: 'Accetta', complete: 'Completa & libera il pagamento', noOpen: 'Nessuna richiesta compatibile al momento.', therapistOnly: 'Le richieste compaiono quando i documenti sono approvati.',
        admin: 'Team di verifica', pendingDocs: 'Documenti in attesa', approve: 'Approva', reject: 'Rifiuta', view: 'Vedi', pendingTh: 'Profili in revisione', activate: 'Attiva', stats: 'Panoramica' }
    }[LOCALE];
    const svcName = (slug) => (DATA?.services?.[LOCALE]?.[slug]) || slug;
    await loadData();

    let sessions = [];
    try { sessions = (await api('/auth/sessions')).sessions || []; } catch { /* static mode */ }

    const head = `<div class="panel" style="margin-bottom:1.4rem;display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;align-items:center">
      <div><h1 style="font-size:1.9rem;margin:0">${L.hi}, ${me.name || me.email}</h1><p class="muted small" style="margin:.2rem 0 0">${me.email} · ${L.role}: ${me.role}</p></div>
      <button class="btn btn--ghost btn--sm" id="logoutBtn">${L.logout}</button></div>`;

    const sessionsPanel = `<div class="panel"><h3 style="font-family:var(--sans);font-size:1rem">${L.logins}</h3>
      ${sessions.length ? `<table style="width:100%;font-size:.8rem;border-collapse:collapse">${sessions.map((s) => `<tr style="border-bottom:1px solid var(--line-2)"><td style="padding:.45rem 0">${new Date(s.createdAt).toLocaleString(LOCALE)}</td><td class="muted">${s.ip || '—'} ${s.city ? '· ' + s.city : ''}</td><td class="muted">${(s.userAgent || '').slice(0, 26)}</td></tr>`).join('')}</table>` : '<p class="muted small">—</p>'}</div>`;

    /* ---- client view */
    async function clientView() {
      let bookings = [];
      try { bookings = (await api('/bookings')).bookings || []; } catch { bookings = (store.get(LS.drafts, []) || []).filter((d) => d.type === 'booking').map((d) => ({ ...d.payload, status: 'requested', paymentStatus: 'unpaid', local: true })); }
      return `<div class="panel"><h3 style="font-family:var(--sans);font-size:1rem">${L.bookings}</h3>
        ${bookings.length ? bookings.map((b) => `<div class="card" style="margin-bottom:.7rem">
          <div style="display:flex;justify-content:space-between;gap:.8rem;flex-wrap:wrap"><strong>${svcName(b.service)}</strong><span class="badge${b.status === 'confirmed' || b.status === 'done' ? ' badge--forest' : ''}">${L.status[b.status] || b.status}</span></div>
          <div class="small muted" style="margin-top:.3rem">${b.date || ''} ${b.time || ''} · ${b.duration || ''} min · ${b.city || ''} · ${b.total || ''}</div>
          ${b.therapist ? `<div class="small" style="margin-top:.3rem">${b.therapist.name} · ${b.therapist.title}</div>` : ''}
          <div class="small muted" style="margin-top:.3rem">💳 ${L.pay[b.paymentStatus] || b.paymentStatus || ''}</div>
          ${b.discount ? `<div class="small" style="color:var(--forest)">${XT.voucher.applied.replace('{amount}', b.discount + ' €')}</div>` : ''}
          ${b.id && !b.local && ['requested', 'confirmed'].includes(b.status) ? `<div style="display:flex;gap:.5rem;margin-top:.7rem;flex-wrap:wrap">
            ${b.status === 'confirmed' ? `<a class="btn btn--ghost btn--sm" href="${API}/bookings/ics?id=${b.id}">${L.ics}</a>` : ''}
            <button class="btn btn--ghost btn--sm" data-cancel="${b.id}">${L.cancel}</button></div>` : ''}
          ${b.status === 'done' && b.therapist ? (b.reviewed ? `<div class="small" style="margin-top:.6rem"><span class="badge badge--forest">✓ ${XT.review.done}</span></div>` : `<form class="review-form" data-review="${b.id}" style="margin-top:.8rem">
            <div class="small" style="margin-bottom:.3rem">${XT.review.prompt}</div>
            <div class="stars-input" data-stars>${[1, 2, 3, 4, 5].map((n) => `<span data-n="${n}" class="${n <= 5 ? 'is-on' : ''}">★</span>`).join('')}</div><input type="hidden" name="rating" value="5">
            <label class="field" style="margin:.6rem 0"><span>${XT.review.text}</span><textarea name="text" rows="2" maxlength="1200"></textarea></label>
            <button class="btn btn--gold btn--sm" type="submit">${XT.review.send}</button></form>`) : ''}
        </div>`).join('') : `<p class="muted small">${L.none}</p>`}</div>`;
    }

    /* ---- therapist view */
    async function therapistView() {
      let docs = null, reqs = null;
      try { docs = await api('/therapist/documents'); } catch { docs = null; }
      try { reqs = await api('/therapist/requests'); } catch { reqs = null; }
      const checklist = docs ? `<div class="panel" style="margin-bottom:1.4rem">
        <h3 style="font-family:var(--sans);font-size:1rem">${L.verify}</h3>
        <p class="small muted">${L.verifyHint}</p>
        <p class="small"><strong>${L.profile}:</strong> <span class="badge${docs.profileStatus === 'active' ? ' badge--forest' : ''}">${L.pstatus[docs.profileStatus] || docs.profileStatus}</span></p>
        ${docs.checklist.map((d) => `<div class="doc-row">
          <div><strong>${L.docs[d.type]}</strong> ${d.required ? `<span class="badge">${L.required}</span>` : ''}<br>
            <span class="t-tag t-tag--${d.status === 'approved' ? 'ok' : d.status === 'pending' ? 'pending' : 'missing'}">${d.status === 'approved' ? '✓ ' : ''}${L.docStatus[d.status]}</span>
            ${d.note ? `<span class="small muted"> — ${d.note}</span>` : ''}${d.filename ? `<span class="small muted"> · ${d.filename}</span>` : ''}</div>
          ${d.status === 'approved' ? '' : `<label class="btn btn--ghost btn--sm" style="cursor:pointer">${L.upload}<input type="file" hidden accept=".pdf,image/*" data-doc="${d.type}"></label>`}
        </div>`).join('')}</div>` : '';

      const reqList = reqs ? `<div class="panel" style="margin-bottom:1.4rem"><h3 style="font-family:var(--sans);font-size:1rem">${L.open}</h3>
        ${reqs.profileStatus !== 'active' ? `<p class="notice notice--info">${L.therapistOnly}</p>` : ''}
        ${reqs.open.length ? reqs.open.map((b) => `<div class="card req-card" style="margin-bottom:.7rem">
          <strong>${svcName(b.service)}</strong><div class="small muted">${b.date} ${b.time} · ${b.duration} min · ${b.persons} P · ${b.place || ''} · ${b.total || ''}</div>
          ${b.notes ? `<div class="small" style="margin-top:.3rem">“${b.notes}”</div>` : ''}
          ${reqs.profileStatus === 'active' ? `<button class="btn btn--gold btn--sm" style="margin-top:.7rem" data-accept="${b.id}">${L.accept}</button>` : ''}
        </div>`).join('') : `<p class="muted small">${L.noOpen}</p>`}
        <h3 style="font-family:var(--sans);font-size:1rem;margin-top:1.6rem">${L.mine}</h3>
        ${reqs.mine.length ? reqs.mine.map((b) => `<div class="card" style="margin-bottom:.7rem">
          <div style="display:flex;justify-content:space-between;gap:.8rem;flex-wrap:wrap"><strong>${svcName(b.service)}</strong><span class="badge badge--forest">${L.status[b.status]}</span></div>
          <div class="small muted">${b.date} ${b.time} · ${b.duration} min · ${b.total || ''}</div>
          <div class="small" style="margin-top:.3rem">${b.name || ''} · ${b.phone || ''}<br>${b.address || ''}</div>
          <div style="display:flex;gap:.5rem;margin-top:.7rem;flex-wrap:wrap">
            <a class="btn btn--ghost btn--sm" href="${API}/bookings/ics?id=${b.id}">${L.ics}</a>
            ${b.status === 'confirmed' ? `<button class="btn btn--gold btn--sm" data-complete="${b.id}">${L.complete}</button>` : ''}</div>
        </div>`).join('') : `<p class="muted small">${L.none}</p>`}</div>` : '';
      let me2 = null;
      try { me2 = (await api('/therapist/me')).profile; } catch { me2 = null; }
      const E = { de: { edit: 'Öffentliches Profil bearbeiten', title: 'Berufsbezeichnung', about: 'Über mich (öffentlich)', radius: 'Einsatzradius (km)', languages: 'Sprachen (Komma-getrennt)', website: 'Website / Instagram', services: 'Angebotene Behandlungen', save: 'Profil speichern', view: 'Öffentliches Profil ansehen', locked: 'Name und Stadt sind an Ihre geprüften Dokumente gebunden.' },
        en: { edit: 'Edit public profile', title: 'Professional title', about: 'About me (public)', radius: 'Coverage radius (km)', languages: 'Languages (comma-separated)', website: 'Website / Instagram', services: 'Treatments offered', save: 'Save profile', view: 'View public profile', locked: 'Name and city are bound to your verified documents.' },
        es: { edit: 'Editar perfil público', title: 'Título profesional', about: 'Sobre mí (público)', radius: 'Radio de cobertura (km)', languages: 'Idiomas (separados por comas)', website: 'Web / Instagram', services: 'Tratamientos ofrecidos', save: 'Guardar perfil', view: 'Ver perfil público', locked: 'Nombre y ciudad están vinculados a tus documentos verificados.' },
        fr: { edit: 'Modifier le profil public', title: 'Titre professionnel', about: 'À propos de moi (public)', radius: 'Rayon d’intervention (km)', languages: 'Langues (séparées par des virgules)', website: 'Site / Instagram', services: 'Soins proposés', save: 'Enregistrer le profil', view: 'Voir le profil public', locked: 'Le nom et la ville sont liés à vos documents vérifiés.' },
        it: { edit: 'Modifica profilo pubblico', title: 'Titolo professionale', about: 'Su di me (pubblico)', radius: 'Raggio di copertura (km)', languages: 'Lingue (separate da virgola)', website: 'Sito / Instagram', services: 'Trattamenti offerti', save: 'Salva profilo', view: 'Vedi profilo pubblico', locked: 'Nome e città sono vincolati ai documenti verificati.' } }[LOCALE];
      const names = DATA?.services?.[LOCALE] || {};
      const editForm = me2 ? `<div class="panel" style="margin-bottom:1.4rem"><h3 style="font-family:var(--sans);font-size:1rem">${E.edit}</h3>
        <p class="small muted">${E.locked}</p>
        <div class="doc-row" style="margin-bottom:1rem"><div style="display:flex;gap:.8rem;align-items:center"><div class="avatar" style="width:56px;height:56px;background:hsl(${me2.hue} 32% 42%)">${me2.photo ? `<img src="${API}/therapists/photo?id=${me2.id}&v=${Date.now()}" alt="">` : me2.initials}</div><div class="small muted">${XT.photo.hint}</div></div>
          <label class="btn btn--ghost btn--sm" style="cursor:pointer">${XT.photo.upload}<input type="file" hidden accept="image/jpeg,image/png,image/webp" data-photo></label></div>
        <form id="profileForm" novalidate>
          <div class="form-grid">
            <label class="field"><span>${E.title}</span><input name="title" value="${(me2.title || '').replace(/"/g, '&quot;')}"></label>
            <label class="field"><span>${E.radius}</span><input type="number" name="radiusKm" min="3" max="80" value="${me2.radiusKm}"></label>
            <label class="field"><span>${E.languages}</span><input name="languages" value="${me2.languages.join(', ')}"></label>
            <label class="field"><span>${E.website}</span><input name="website" value="${(me2.website || '').replace(/"/g, '&quot;')}"></label>
            <label class="field field--full"><span>${E.about}</span><textarea name="about" rows="5">${(me2.about || '').replace(/</g, '&lt;')}</textarea></label>
          </div>
          <label class="field"><span>${E.services}</span></label>
          <div class="checks">${Object.entries(names).map(([slug, n]) => `<label class="check"><input type="checkbox" name="services" value="${slug}"${me2.services.includes(slug) ? ' checked' : ''}><span>${n}</span></label>`).join('')}</div>
          <div class="form-actions"><a class="btn btn--ghost" href="${BASE}/${LOCALE}/${PROFILE_SEG}/${me2.id}/" ${me2.status === 'active' ? '' : 'hidden'}>${E.view}</a><button class="btn btn--gold" type="submit">${E.save}</button></div>
        </form></div>` : '';
      return checklist + reqList + editForm;
    }

    /* ---- admin view */
    async function adminView() {
      let o = null;
      try { o = await api('/admin/overview'); } catch { return ''; }
      return `<div class="panel" style="margin-bottom:1.4rem"><h3 style="font-family:var(--sans);font-size:1rem">${L.admin} · ${L.stats}</h3>
        <p class="small muted">${o.users} users · ${o.openBookings} open bookings · ${o.pendingDocuments.length} docs · ${o.pendingTherapists.length} profiles</p>
        <h4>${L.pendingDocs}</h4>
        ${o.pendingDocuments.length ? o.pendingDocuments.map((d) => `<div class="doc-row"><div><strong>${d.full_name}</strong> · ${d.city} <span class="small muted">· ${L.docs[d.type] || d.type} · ${(d.size / 1024).toFixed(0)} KB</span></div>
          <div style="display:flex;gap:.4rem"><a class="btn btn--ghost btn--sm" target="_blank" href="${API}/admin/documents/file?id=${d.id}">${L.view}</a><button class="btn btn--gold btn--sm" data-review="${d.id}" data-status="approved">${L.approve}</button><button class="btn btn--ghost btn--sm" data-review="${d.id}" data-status="rejected">${L.reject}</button></div></div>`).join('') : '<p class="muted small">—</p>'}
        <h4 style="margin-top:1.4rem">${L.pendingTh}</h4>
        ${o.pendingTherapists.length ? o.pendingTherapists.map((t) => `<div class="doc-row"><div><strong>${t.full_name}</strong> · ${t.city} · ${t.country}</div><button class="btn btn--ghost btn--sm" data-activate="${t.id}">${L.activate}</button></div>`).join('') : '<p class="muted small">—</p>'}
      </div>`;
    }

    /* ---- favourites / privé / security (every role) */
    async function favoritesPanel() {
      let favs = [];
      try { favs = (await api('/favorites')).favorites || []; } catch { return ''; }
      return `<div class="panel" style="margin-bottom:1.4rem"><h3 style="font-family:var(--sans);font-size:1rem">${XT.fav.title}</h3>
        ${favs.length ? favs.map((f) => `<div class="doc-row"><div style="display:flex;gap:.7rem;align-items:center"><div class="avatar" style="width:40px;height:40px;font-size:.9rem;background:hsl(${f.hue} 32% 42%)">${f.photo ? `<img src="${API}/therapists/photo?id=${f.id}" alt="">` : f.initials}</div><div><strong>${f.name}</strong><div class="small muted">${f.title} · ★ ${f.rating}</div></div></div>
          <div style="display:flex;gap:.4rem"><a class="btn btn--ghost btn--sm" href="${BASE}/${LOCALE}/${PROFILE_SEG}/${f.id}/">${T.book}</a><button class="btn btn--ghost btn--sm" data-unfav="${f.id}">×</button></div></div>`).join('') : `<p class="muted small">${XT.fav.none}</p>`}</div>`;
    }
    function privePanel() {
      const P = XT.prive;
      return `<div class="panel" style="margin-bottom:1.4rem"><h3 style="font-family:var(--sans);font-size:1rem">${P.title}</h3>
        ${me.priveTier ? `<p><span class="badge">${me.priveTier}</span> <span class="small muted">${P.active.replace('{date}', (me.priveSince || '').slice(0, 10))}</span></p><button class="btn btn--ghost btn--sm" data-prive-cancel>${P.cancel}</button>`
          : `<p class="small muted">${P.none}</p><a class="btn btn--gold btn--sm" href="${BASE}/${LOCALE}/prive/">${P.choose}</a>`}</div>`;
    }
    function securityPanel() {
      const S = XT.security;
      return `<div class="panel" style="margin-bottom:1.4rem"><h3 style="font-family:var(--sans);font-size:1rem">${S.title}</h3>
        <p class="small">${me.emailVerified ? `<span class="badge badge--forest">✓ ${S.emailOk}</span>` : `<span class="badge">${S.emailNo}</span> <button class="link-btn" data-resend>${S.resend}</button>`}</p>
        <form id="pwForm" novalidate style="margin-top:1rem"><div class="form-grid">
          <label class="field"><span>${S.current}</span><input type="password" name="current" autocomplete="current-password" required></label>
          <label class="field"><span>${S.newPw}</span><input type="password" name="password" minlength="10" autocomplete="new-password" required></label></div>
          <button class="btn btn--ghost btn--sm" type="submit">${S.changePw}</button></form>
        <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-top:1.2rem"><a class="btn btn--ghost btn--sm" href="${API}/auth/export">${S.export}</a><button class="btn btn--ghost btn--sm" data-delete style="color:#b3402f">${S.delete}</button></div></div>`;
    }
    const verifiedParam = new URLSearchParams(location.search).get('verified');
    if (verifiedParam === '1') notice($('#accountNotice'), 'ok', XT.security.verifiedNow);

    const main = me.role === 'admin' ? await adminView() + await clientView() : me.role === 'therapist' ? await therapistView() : await clientView();
    const side = (me.role === 'client' ? await favoritesPanel() : '') + privePanel() + securityPanel() + sessionsPanel;
    root.innerHTML = head + `<div style="display:grid;grid-template-columns:1.3fr .7fr;gap:clamp(20px,3vw,40px);align-items:start"><div>${main}</div><div>${side}</div></div>`;

    const reload = () => initAccount();
    root.addEventListener('click', async (e) => {
      const b = e.target.closest('button');
      if (!b) return;
      try {
        if (b.id === 'logoutBtn') { try { await api('/auth/logout', { method: 'POST' }); } catch { /* static */ } session.clear(); location.href = `${BASE}/${LOCALE}/`; return; }
        if (b.dataset.cancel) await api('/bookings/cancel', { method: 'POST', body: { id: b.dataset.cancel } });
        if (b.dataset.accept) await api('/therapist/accept', { method: 'POST', body: { id: b.dataset.accept } });
        if (b.dataset.complete) await api('/therapist/complete', { method: 'POST', body: { id: b.dataset.complete } });
        if (b.dataset.review) await api('/admin/documents/review', { method: 'POST', body: { id: b.dataset.review, status: b.dataset.status } });
        if (b.dataset.activate) await api('/admin/therapists/status', { method: 'POST', body: { id: b.dataset.activate, status: 'active' } });
        if (b.dataset.unfav) await api('/favorites', { method: 'POST', body: { therapistId: b.dataset.unfav, remove: true } });
        if (b.hasAttribute('data-prive-cancel')) { await api('/prive/cancel', { method: 'POST' }); notice($('#accountNotice'), 'ok', XT.prive.cancelled); }
        if (b.hasAttribute('data-resend')) { await api('/auth/verify/resend', { method: 'POST' }); notice($('#accountNotice'), 'ok', XT.security.resent); return; }
        if (b.hasAttribute('data-delete')) {
          const pw = window.prompt(XT.security.deleteConfirm + '\n\n' + XT.security.current + ':');
          if (!pw) return;
          await api('/auth/delete', { method: 'POST', body: { password: pw } });
          session.clear(); alert(XT.security.deleted); location.href = `${BASE}/${LOCALE}/`; return;
        }
        if (b.closest('[data-stars]')) return;
        reload();
      } catch (err) { notice($('#accountNotice'), 'err', err.data?.error || T.err); }
    }, { once: true });

    root.addEventListener('submit', async (e) => {
      const form = e.target.closest('#profileForm');
      if (!form) return;
      e.preventDefault();
      const d = fdToObject(form);
      d.services = [].concat(d.services || []);
      notice($('#accountNotice'), 'info', T.sending);
      try { await api('/therapist/profile', { method: 'POST', body: d }); notice($('#accountNotice'), 'ok', T.ok); }
      catch (err) { notice($('#accountNotice'), 'err', err.data?.error || T.err); }
    });

    root.addEventListener('click', (e) => {
      const star = e.target.closest('[data-stars] span'); if (!star) return;
      const wrap = star.parentElement; const n = Number(star.dataset.n);
      $$('span', wrap).forEach((x) => x.classList.toggle('is-on', Number(x.dataset.n) <= n));
      wrap.nextElementSibling.value = n;
    });
    root.addEventListener('submit', async (e) => {
      const rf = e.target.closest('.review-form');
      if (rf) {
        e.preventDefault();
        try { await api('/bookings/review', { method: 'POST', body: { id: rf.dataset.review, rating: rf.rating.value, text: rf.text.value } }); notice($('#accountNotice'), 'ok', XT.review.thanks); reload(); }
        catch (err) { notice($('#accountNotice'), 'err', err.data?.error || T.err); }
        return;
      }
      const pf = e.target.closest('#pwForm');
      if (pf) {
        e.preventDefault(); if (!validate(pf)) return;
        try { await api('/auth/password', { method: 'POST', body: { current: pf.current.value, password: pf.password.value } }); notice($('#accountNotice'), 'ok', XT.security.changed); pf.reset(); }
        catch (err) { notice($('#accountNotice'), 'err', err.status === 403 ? XT.security.wrong : (err.data?.error || T.err)); }
      }
    });
    root.addEventListener('change', async (e) => {
      const photo = e.target.closest('input[type=file][data-photo]');
      if (photo && photo.files[0]) {
        const f = photo.files[0];
        const data = await new Promise((res, rej) => { const r = new FileReader(); r.onload = () => res(r.result); r.onerror = rej; r.readAsDataURL(f); });
        notice($('#accountNotice'), 'info', T.sending);
        try { await api('/therapist/photo', { method: 'POST', body: { mime: f.type, data } }); notice($('#accountNotice'), 'ok', T.ok); reload(); }
        catch (err) { notice($('#accountNotice'), 'err', err.data?.error || T.err); }
        return;
      }
      const input = e.target.closest('input[type=file][data-doc]');
      if (!input || !input.files[0]) return;
      const file = input.files[0];
      const data = await new Promise((res, rej) => { const r = new FileReader(); r.onload = () => res(r.result); r.onerror = rej; r.readAsDataURL(file); });
      notice($('#accountNotice'), 'info', T.sending);
      try {
        await api('/therapist/documents', { method: 'POST', body: { type: input.dataset.doc, filename: file.name, mime: file.type, data } });
        notice($('#accountNotice'), 'ok', T.ok);
        reload();
      } catch (err) { notice($('#accountNotice'), 'err', err.data?.error || T.err); }
    }, { once: true });
  }

  function initContact() {
    const form = $('#contactForm');
    if (!form) return;
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!validate(form)) return;
      notice($('#contactNotice'), 'info', T.sending);
      try { await api('/contact', { method: 'POST', body: { ...fdToObject(form), locale: LOCALE } }); }
      catch { const d = store.get(LS.drafts, []); d.push({ type: 'contact', at: Date.now(), payload: fdToObject(form) }); store.set(LS.drafts, d); }
      form.hidden = true;
      notice($('#contactNotice'), 'ok', T.ok);
    });
  }


  /* ------------------------------------------------ conversion signals */
  // Deterministic "activity" derived from the bundled directory: no fake randomness
  // per visit, the same city always shows the same plausible stream and slot count.
  function initSignals() {
    const ticker = $('#liveTicker');
    const slots = $('#qbSlots');
    const citySel = $('#qbCity');
    if (!ticker && !slots) return;
    loadData().then(() => {
      const names = DATA.services[LOCALE] || {};
      const cityOf = (slug) => DATA.cities.find((c) => c.slug === slug);
      const hourSeed = Math.floor(Date.now() / 36e5);
      const hash = (str) => str.split('').reduce((a, c) => (a * 31 + c.charCodeAt(0)) >>> 0, 7);

      const renderSlots = () => {
        if (!slots || !citySel) return;
        const c = cityOf(citySel.value);
        if (!c) return;
        const n = 1 + ((hash(c.slug) + hourSeed) % 4);
        slots.textContent = (n === 1 ? slots.dataset.tplOne : slots.dataset.tpl).replace('{n}', n).replace('{city}', c.name[LOCALE]);
      };
      renderSlots();
      citySel?.addEventListener('change', renderSlots);

      if (!ticker) return;
      const geo = store.get(LS.geo);
      const pool = DATA.therapists.filter((t) => !geo || t.city === geo.city || Math.random() < 0.25);
      let i = hourSeed % Math.max(pool.length, 1);
      const textEl = $('.ticker__text', ticker);
      const show = () => {
        const th = pool[i % pool.length]; if (!th) return;
        const c = cityOf(th.city);
        const svc = names[th.services[(i + hourSeed) % th.services.length]] || '';
        const min = 2 + ((hash(th.id) + i) % 41);
        const online = 40 + ((hash(th.city) + hourSeed) % 70);
        const msg = i % 3 === 2
          ? ticker.dataset.live.replace('{n}', online)
          : `${ticker.dataset.just}: ${svc} · ${c ? c.name[LOCALE] : ''} · ${ticker.dataset.ago.replace('{min}', min)}`;
        ticker.classList.add('is-swapping');
        setTimeout(() => { textEl.textContent = msg; ticker.classList.remove('is-swapping'); }, 350);
        i++;
      };
      show();
      setInterval(show, 6500);
    });
  }

  function initStickyCta() {
    const bar = $('#stickyCta');
    if (!bar) return;
    const hero = $('.hero');
    if (!hero || !('IntersectionObserver' in window)) return;
    // Desktop: floating pill once the hero (with its own booking card) scrolls away.
    new IntersectionObserver(([e]) => bar.classList.toggle('is-visible', !e.isIntersecting), { threshold: 0.05 }).observe(hero);
  }


  /* ------------------------------------------------------------- cookies */
  function initCookies() {
    const bar = $('#cookieBar');
    if (!bar) return;
    const choice = store.get('lumea.consent');
    const loadAnalytics = () => {
      if (!window.__plausible || $('#plausible')) return;
      const sc = document.createElement('script'); sc.id = 'plausible'; sc.defer = true; sc.dataset.domain = window.__plausible; sc.src = 'https://plausible.io/js/script.js';
      document.head.appendChild(sc);
    };
    if (choice === 'all') loadAnalytics();
    if (!choice) bar.hidden = false;
    bar.addEventListener('click', (e) => {
      const b = e.target.closest('[data-cookie]'); if (!b) return;
      store.set('lumea.consent', b.dataset.cookie);
      bar.hidden = true;
      if (b.dataset.cookie === 'all') loadAnalytics();
    });
  }

  /* -------------------------------------------------- forgot / reset flow */
  function initReset() {
    const forgot = $('#forgotForm'); const reset = $('#resetForm');
    if (!forgot || !reset) return;
    const token = new URLSearchParams(location.search).get('token');
    const noticeEl = $('#resetNotice');
    if (token) { forgot.hidden = true; reset.hidden = false; reset.token.value = token; }
    forgot.addEventListener('submit', async (e) => {
      e.preventDefault(); if (!validate(forgot)) return;
      notice(noticeEl, 'info', T.sending);
      try { await api('/auth/forgot', { method: 'POST', body: { email: forgot.email.value, locale: LOCALE } }); } catch { /* always ok */ }
      forgot.hidden = true; notice(noticeEl, 'ok', XT.forgot.sent);
    });
    reset.addEventListener('submit', async (e) => {
      e.preventDefault(); if (!validate(reset)) return;
      if (reset.password.value !== reset.confirm.value) return notice(noticeEl, 'err', XT.forgot.mismatch);
      notice(noticeEl, 'info', T.sending);
      try { await api('/auth/reset', { method: 'POST', body: { token: reset.token.value, password: reset.password.value } }); reset.hidden = true; notice(noticeEl, 'ok', XT.forgot.done); }
      catch (err) { notice(noticeEl, 'err', err.status === 410 ? XT.forgot.invalid : (err.data?.error || T.err)); }
    });
  }

  /* ------------------------------------------------------------ vouchers */
  function initVoucher() {
    const form = $('#voucherForm'); if (!form) return;
    const totalEl = $('#voucherTotal'); const custom = $('#voucherCustom');
    const setAmount = (v) => { form.amount.value = v; totalEl.textContent = `${v} €`; };
    form.addEventListener('click', (e) => {
      const chip = e.target.closest('[data-amount]'); if (!chip) return;
      $$('[data-amount]', form).forEach((c) => c.classList.toggle('is-on', c === chip));
      if (chip.dataset.amount === 'custom') { custom.hidden = false; custom.querySelector('input').focus(); setAmount(Number(custom.querySelector('input').value) || 100); }
      else { custom.hidden = true; setAmount(Number(chip.dataset.amount)); }
    });
    custom?.querySelector('input')?.addEventListener('input', (e) => setAmount(Math.min(Math.max(Number(e.target.value) || 50, 50), 5000)));
    form.addEventListener('submit', async (e) => {
      e.preventDefault(); if (!validate(form)) return;
      const d = fdToObject(form); d.locale = LOCALE;
      notice($('#voucherNotice'), 'info', T.sending);
      try {
        const r = await api('/vouchers', { method: 'POST', body: d });
        form.hidden = true;
        $('#voucherNotice').innerHTML = '';
        const out = $('#voucherResult'); out.hidden = false;
        out.innerHTML = `<div class="notice notice--ok"><strong>${XT.voucher.yourCode}</strong><div class="card__price" style="font-size:1.6rem;margin:.4rem 0;letter-spacing:.08em">${r.code}</div>
          <div class="small">${r.amount} € · ${XT.voucher.validUntil} ${r.expiresAt.slice(0, 10)}</div><div class="small" style="margin-top:.4rem">${XT.voucher.sentTo.replace('{to}', r.sentTo)}</div></div>`;
      } catch (err) { notice($('#voucherNotice'), 'err', err.data?.error || T.err); }
    });
  }

  /* ------------------------------------------------------ service search */
  function initSearch() {
    const input = $('#serviceSearch'); if (!input) return;
    const q0 = new URLSearchParams(location.search).get('q'); if (q0) input.value = q0;
    const apply = () => {
      const q = input.value.trim().toLowerCase();
      let shown = 0;
      $$('.menu-row').forEach((row) => { const hit = !q || (row.dataset.q || '').includes(q); row.hidden = !hit; if (hit) shown++; });
      $$('.menu-group').forEach((g) => { g.hidden = !$$('.menu-row', g).some((r) => !r.hidden); });
      $('#searchEmpty').hidden = shown > 0;
    };
    input.addEventListener('input', apply); apply();
  }

  /* ----------------------------------------- therapist profile hydration */
  function initProfile() {
    const reviewsEl = $('#profileReviews'); const fav = $('[data-fav]');
    const id = reviewsEl?.dataset.profileId || fav?.dataset.fav;
    if (!id) return;
    api(`/therapists/profile?id=${encodeURIComponent(id)}&locale=${LOCALE}`).then((r) => {
      const p = r.profile;
      if (p.photo) { const av = $(`[data-avatar="${id}"]`); if (av) av.innerHTML = `<img src="${API}/therapists/photo?id=${id}" alt="${p.name}" loading="lazy">`; }
      const real = (p.sampleReviews || []).filter((x) => x.verified);
      if (real.length && reviewsEl) {
        const stars = (n) => '★'.repeat(n) + '☆'.repeat(5 - n);
        reviewsEl.insertAdjacentHTML('afterbegin', real.map((rv) => `<figure class="quote" style="margin:0;border-color:var(--gold)"><span class="stars">${stars(rv.rating)}</span> <span class="badge badge--forest">${reviewsEl.dataset.verified}</span><p style="font-size:1rem;margin-top:.5rem">&ldquo;${(rv.text || '').replace(/</g, '&lt;')}&rdquo;</p><footer><b>${rv.name}</b> · ${rv.date}</footer></figure>`).join(''));
      }
    }).catch(() => {});
    if (fav) {
      const paint = (on) => { fav.textContent = on ? fav.dataset.on : fav.dataset.off; fav.dataset.state = on ? '1' : '0'; };
      if (session.get()) api('/favorites').then((r) => paint(r.favorites.some((f) => f.id === id))).catch(() => {});
      fav.addEventListener('click', async () => {
        if (!session.get()) { location.href = `${BASE}/${LOCALE}/${({ de: 'anmelden', en: 'sign-in', es: 'entrar', fr: 'connexion', it: 'accedi' })[LOCALE]}/`; return; }
        const on = fav.dataset.state === '1';
        try { await api('/favorites', { method: 'POST', body: { therapistId: id, remove: on } }); paint(!on); } catch { /* ignore */ }
      });
    }
  }

  /* ----------------------------------------------------------------- go */
  function boot() {
    paintAuth();
    initNav();
    initReveal();
    initQuickBook();
    initApply();
    initBooking();
    initAuth();
    initAccount();
    initContact();
    initSignals();
    initStickyCta();
    initCookies();
    initReset();
    initVoucher();
    initSearch();
    initProfile();
    initGeo().catch(() => {});
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
