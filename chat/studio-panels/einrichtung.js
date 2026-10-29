/* ChatHelp Studio — Panel „Einrichtung“ (onboarding-lib): eine Frage pro Bildschirm, Fortschrittsring, Zurück/Weiter/Überspringen,
   Speichern je Antwort (POST /v1/onboarding/answer), Enter = weiter, Auswahl-Chips, Zeitbereiche, wiederholbare Zeilen, Website-Import über das
   Training (train/import-url → train/job → train/apply), Abschluss „Alles bereit“ mit Checkliste und Schnellaktionen.
   Modus „qs“ = Schnellstart (quickstart-lib, Standard für neue Einrichtungen): Link → KI-Entwurf bestätigen (oder Branche + 3 Fragen) → Stimme + Begrüßung
   → im Browser mit dem Agenten sprechen → Paket → Nummer + Umleitungscodes → Testanruf → „Dein Agent ist live“ (letzte Vorgänge). Hilfe-Knopf, Ereignisse je Schritt.
   Nutzt die Studio-Globals api, API, esc, go, openAgent, KEY, PLANS, ME, optLabel, countryName. */
(function () {
  'use strict';
  var PANEL = 'panel-einrichtung';
  var S = null, MODE = 'load', CUR = null, SHOWALL = false, DRAFT = {}, BUSY = false, DIR = 'anim', APPLIED = null, IMPORT = null, firstLoad = true, bound = false, QS = null;
  var g = function (id) { return document.getElementById(id); };
  var h = function (v) { return esc(v == null ? '' : String(v)); };
  /* Texte vom Server (Fragen, Optionen, Checkliste) kommen auf Deutsch: _tx übersetzt sie über das Quelltext-Wörterbuch (src.* / srx.*) */
  var X = function (v) { return h(v == null ? '' : _tx(String(v))); };
  var WD = [0, 1, 2, 3, 4, 5, 6].map(function (i) { return I18N.lang === 'de' ? ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'][i] : I18N.wd(i, 'short'); }), WD_ORDER = [1, 2, 3, 4, 5, 6, 0];
  var SV = function (p, s) { return '<svg width="' + (s || 18) + '" height="' + (s || 18) + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + p + '</svg>'; };
  var IC = {
    spark: '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/>',
    check: '<path d="M5 12.5l4.2 4.2L19 7"/>',
    code: '<path d="M8.5 8L4.5 12l4 4M15.5 8l4 4-4 4M13.5 5.5l-3 13"/>',
    phone: '<path d="M6.6 4h2.8l1.5 4-2 1.3a10.5 10.5 0 0 0 5.8 5.8l1.3-2 4 1.5v2.8a1.6 1.6 0 0 1-1.7 1.6A15.8 15.8 0 0 1 5 5.7 1.6 1.6 0 0 1 6.6 4z"/>',
    wa: '<path d="M4.5 19.5l1.2-3.7A8 8 0 1 1 8.3 18.4z"/><path d="M9.2 9.3c.3 2.3 2.2 4.3 4.6 4.8l1-1.2 1.8.8-.4 1.6c-3.6.2-7-3.1-7-6.8l1.6-.4.8 1.8z"/>',
    app: '<rect x="7" y="2.8" width="10" height="18.4" rx="2.4"/><path d="M11 18h2"/>',
    chat: '<path d="M5 18.5V7a2.5 2.5 0 0 1 2.5-2.5h9A2.5 2.5 0 0 1 19 7v6.5a2.5 2.5 0 0 1-2.5 2.5H9z"/><path d="M9 9.5h6M9 12.5h4"/>',
    mail: '<rect x="3.5" y="5.5" width="17" height="13" rx="2.2"/><path d="M4 7l8 6 8-6"/>',
    edit: '<path d="M4.5 19.5l1-4L16 5a2 2 0 0 1 3 3L8.5 18.5z"/>',
    globe: '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
    maps: '<path d="M12 21s-6-5.3-6-10a6 6 0 0 1 12 0c0 4.7-6 10-6 10z"/><circle cx="12" cy="11" r="2.2"/>',
    instagram: '<rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="3.8"/><circle cx="17.2" cy="6.8" r=".8"/>',
    play: '<path d="M8 5.5v13l10-6.5z"/>',
    mic: '<rect x="9" y="3.5" width="6" height="11" rx="3"/><path d="M5.5 11.5a6.5 6.5 0 0 0 13 0M12 18v3"/>',
    send: '<path d="M4 12l16-7-5 7 5 7z"/>',
    sound: '<path d="M4 9.5v5h3.5L12 18.5v-13L7.5 9.5z"/><path d="M15.5 9a4 4 0 0 1 0 6M18 6.5a7.5 7.5 0 0 1 0 11"/>',
    bolt: '<path d="M13 3L5 13.5h6L10 21l9-11h-6z"/>'
  };
  var key = function () { try { return KEY; } catch (e) { return ''; } };
  var visible = function () { var el = g(PANEL); return !!el && !el.closest('.hidden'); };

  /* ---------------------------------------------------------------- Daten */
  function load(q) {
    var qs = q && q.id ? '?id=' + encodeURIComponent(q.id) : (q && q.plan ? '?plan=' + encodeURIComponent(q.plan) : '');
    return api('/onboarding/state' + qs).then(function (j) {
      var changed = !S || !S.onboarding || S.onboarding.id !== j.onboarding.id;
      S = j; if (changed) { DRAFT = {}; CUR = null; APPLIED = null; SHOWALL = false; QS = null; MODE = pickMode(); }
      render();
    }).catch(function (e) {
      S = null; MODE = 'empty';
      var list = (e.data && e.data.list) || [], ch = (e.data && e.data.choices) || [];
      if (ch.length) { renderChoices(ch); renderSwitch(list); return; }
      g('onbCard').innerHTML = '<div class="onb-empty"><div class="hero" style="padding:8px 0 0;text-align:center"><div class="ic" style="margin:0 auto">' + SV(IC.spark, 28) + '</div><h2 style="font-size:28px">' + _t('onbj.nothing') + '</h2><p style="margin:12px auto 0">' + h(e.message || _t('onbj.notFound')) + '</p></div>'
        + '<div class="row" style="justify-content:center;margin-top:20px"><button class="btn p" type="button" onclick="go(\'agents\')">' + _t('sj.agents.view') + '</button></div></div>';
      renderSwitch(list);
    });
  }
  /** Noch nichts gebucht: Agent wählen (Empfehlung aus dem Profil zuerst) – die Einrichtung startet sofort, auch in der Testphase. */
  function plN(c) { return I18N.has('pl.' + c.id + '.n') ? _t('pl.' + c.id + '.n') : _tx(c.name); }
  function plD(c) { return I18N.has('pl.' + c.id + '.d') ? _t('pl.' + c.id + '.d') : _tx(c.desc); }
  function renderChoices(ch) {
    g('onbCard').innerHTML = '<div class="onb-empty onb-pick"><div class="hero" style="padding:8px 0 0;text-align:center"><div class="ic" style="margin:0 auto">' + SV(IC.spark, 28) + '</div><h2 style="font-size:28px">' + _t('onbj.pickT') + '</h2><p style="margin:12px auto 0">' + _t('onbj.pickS') + '</p></div>'
      + '<div class="pick">' + ch.map(function (c, i) { return '<button type="button" class="pk' + (i === 0 ? ' best' : '') + '" data-plan="' + h(c.id) + '">' + (i === 0 ? '<span class="pill2">' + _t('onbj.pickBest') + '</span>' : '') + '<b>' + h(plN(c)) + '</b><span>' + h(plD(c)) + '</span><i>' + _t('onbj.pickGo') + ' →</i></button>'; }).join('') + '</div>'
      + '<div class="row" style="justify-content:center;margin-top:16px"><button class="btn ghost" type="button" onclick="go(\'agents\')">' + _t('sj.agents.view') + '</button></div></div>';
    g('onbCard').querySelectorAll('[data-plan]').forEach(function (b) { b.onclick = function () { b.disabled = true; load({ plan: b.dataset.plan }); }; });
  }
  /** Schnellstart, wenn er begonnen wurde oder noch nichts beantwortet ist; sonst die ausführliche Einrichtung dort, wo sie steht. */
  function pickMode() { var o = S.onboarding; if (o.qs_step) return 'qs'; if (o.status === 'done') return 'done'; if (o.private) return 'intro'; return steps().some(function (s) { return s.answered; }) ? 'intro' : 'qs'; }
  function steps() { return (S && S.steps) || []; }
  function stepById(id) { var s = steps(); for (var i = 0; i < s.length; i++) if (s[i].id === id) return s[i]; return null; }
  function val(st) { return Object.prototype.hasOwnProperty.call(DRAFT, st.id) ? DRAFT[st.id] : st.value; }
  function vals() { var o = {}; steps().forEach(function (s) { o[s.id] = val(s); }); return o; }
  function isVisible(st, v) {
    var w = st.when || {}; for (var k in w) { if (!Object.prototype.hasOwnProperty.call(w, k)) continue; var x = v[k]; var have = Array.isArray(x) ? x.map(String) : (x == null || x === '' ? [] : [String(x)]);
      if (!have.some(function (y) { return w[k].indexOf(y) >= 0; })) return false; }
    return true;
  }
  function queue() { var v = vals(); return steps().filter(function (s) { return isVisible(s, v) && (SHOWALL || !s.known || s.answered); }); }
  function allVisible() { var v = vals(); return steps().filter(function (s) { return isVisible(s, v); }); }
  function progress() { var all = allVisible(), d = all.filter(function (s) { return s.answered || s.known; }).length; return { done: d, total: all.length, pct: all.length ? Math.round(100 * d / all.length) : 100 }; }
  function knownCount() { return allVisible().filter(function (s) { return s.known && !s.answered; }).length; }

  /* ---------------------------------------------------------------- Rahmen */
  function ring(p) { var C = 2 * Math.PI * 24, off = C * (1 - Math.max(0, Math.min(100, p)) / 100);
    return '<div class="onb-ring" role="img" aria-label="' + h(_t('onbj.pct',{p:p})) + '"><svg width="58" height="58" viewBox="0 0 58 58"><circle class="bg" cx="29" cy="29" r="24"/><circle class="fg" cx="29" cy="29" r="24" stroke-dasharray="' + C.toFixed(1) + '" stroke-dashoffset="' + off.toFixed(1) + '"/></svg><b>' + p + '%</b></div>'; }
  function renderSwitch(list) {
    var el = g('onbSwitch'); if (!el) return; list = list || (S && S.list) || [];
    if (list.length < 2) { el.classList.add('hidden'); el.innerHTML = ''; return; }
    el.classList.remove('hidden');
    el.innerHTML = list.map(function (o) { return '<button type="button" role="tab" data-id="' + o.id + '" class="' + (S && S.onboarding && S.onboarding.id === o.id ? 'on' : '') + '" aria-selected="' + (S && S.onboarding && S.onboarding.id === o.id) + '"><span class="st' + (o.status === 'done' ? ' done' : '') + '"></span>' + h(o.base_name || o.plan_name) + '</button>'; }).join('');
    el.querySelectorAll('button').forEach(function (b) { b.onclick = function () { load({ id: +b.dataset.id }); }; });
  }
  function render() {
    renderSwitch(); var kb = g('onbKbd'); if (kb) kb.classList.toggle('hidden', MODE !== 'step');
    if (MODE !== 'qs') qsStopPoll();
    if (MODE === 'qs') return renderQs();
    if (MODE === 'intro') return renderIntro();
    if (MODE === 'done') return renderDone();
    if (MODE === 'finishing') return renderFinishing();
    return renderStep();
  }
  function renderIntro() {
    var o = S.onboarding, q = queue(), known = knownCount(), priv = !!o.private;
    var groups = []; q.forEach(function (s) { if (groups.indexOf(s.group) < 0) groups.push(s.group); });
    var mins = Math.max(2, Math.round(q.length * 0.4));
    g('onbCard').innerHTML = '<div class="hero"><div class="ic">' + SV(IC.spark, 28) + '</div>'
      + '<h2>' + (o.status === 'done' ? _t('onbj.edit') : (priv ? _t('onbj.welcomeP',{n:mins}) : _t('onbj.welcomeA',{n:mins}))) + '</h2>'
      + '<p>' + h(_t('onbj.intro',{name:o.base_name,n:q.length})) + '</p>'
      + '<ul class="ilist">' + groups.slice(0, 5).map(function (gr, i) { return '<li><i>' + (i + 1) + '</i><span><b style="color:var(--ink)">' + X(gr) + '</b> · ' + h(introItems(q, gr)) + '</span></li>'; }).join('') + '</ul>'
      + (known ? '<div class="note">✓ ' + h(known === 1 ? _t('onbj.known1') : _t('onbj.knownN',{n:known})) + '</div>' : '')
      + '</div><div class="onb-foot"><button class="skip" type="button" id="onbAll">' + (SHOWALL ? _t('onbj.onlyOpen') : _t('onbj.showAll')) + '</button>' + (o.status !== 'done' ? '<button class="skip" type="button" id="onbQs">⚡ ' + _t('onbq.name') + '</button>' : '') + '<span class="sp"></span><button class="btn p" type="button" id="onbGo">' + (progress().done ? _t('onbj.cont') : _t('onbj.go')) + ' →</button></div>';
    g('onbGo').onclick = function () { var qq = queue(); var first = qq.filter(function (s) { return !s.answered; })[0] || qq[0]; if (!first) return finish(); CUR = first.id; MODE = 'step'; DIR = 'anim'; render(); };
    g('onbAll').onclick = function () { SHOWALL = !SHOWALL; render(); };
    var qb = g('onbQs'); if (qb) qb.onclick = function () { MODE = 'qs'; QS = null; render(); };
    if (!(window.matchMedia && matchMedia('(pointer:coarse)').matches)) setTimeout(function () { var b = g('onbGo'); if (b && visible()) b.focus({ preventScroll: true }); }, 30);
  }
  function introItems(q, gr) {
    var its = q.filter(function (s) { return s.group === gr; });
    if (its.length === 1 && its[0].options && shortTitle(its[0]) === gr) return its[0].options.map(function (o) { return _tx(o.l); }).join(', ');
    return its.map(function (s) { return shortTitle(s); }).slice(0, 3).join(', ');
  }
  function shortTitle(s) { var k = 'onbj.st.' + s.id; return I18N.has(k) ? _t(k) : _tx(s.title); }
  

  /* ---------------------------------------------------------------- Frage */
  function renderStep() {
    var q = queue(), st = stepById(CUR); if (!st || q.indexOf(st) < 0) { st = q.filter(function (s) { return !s.answered; })[0] || q[0]; if (!st) return finish(); CUR = st.id; }
    var i = q.indexOf(st), p = progress(), last = i === q.length - 1;
    g('onbCard').innerHTML = '<div class="onb-head">' + ring(p.pct) + '<div class="onb-meta"><div class="k">' + X(st.group) + '</div><div class="n">' + h(_t('onbj.qOf',{i:i + 1,n:q.length})) + ' · ' + h(S.onboarding.base_name) + '</div></div><div class="onb-saved" id="onbSaved" aria-live="polite">' + _t('onbj.saved') + '</div></div>'
      + '<div class="onb-body ' + DIR + '" id="onbBody" data-step="' + h(st.id) + '"><h2 class="q" id="onbQ">' + X(st.title) + '</h2>' + (st.sub ? '<p class="sub">' + X(st.sub) + '</p>' : '') + '<div class="onb-in" role="group" aria-labelledby="onbQ">' + input(st) + '</div></div>'
      + '<div class="onb-err" id="onbErr" role="alert"></div>'
      + '<div class="onb-foot"><button class="btn" type="button" id="onbBack" aria-label="' + h(_t('onbj.back')) + '">←<span class="hide-s"> ' + _t('onbj.back') + '</span></button><span class="sp"></span><button class="skip" type="button" id="onbSkip">' + _t('onbj.skip') + '</button><button class="btn p" type="button" id="onbNext">' + (last ? _t('onbj.finish') : _t('onbj.next')) + '</button></div>';
    bindStep(st);
    g('onbBack').onclick = function () { DRAFT[st.id] = collect(st); var qq = queue(), j = qq.indexOf(st); if (j <= 0) { MODE = 'intro'; render(); return; } CUR = qq[j - 1].id; DIR = 'back'; render(); };
    g('onbSkip').onclick = function () { save(st, null); };
    g('onbNext').onclick = function () { save(st, collect(st)); };
    var coarse = window.matchMedia && matchMedia('(pointer:coarse)').matches;
    var f = g('onbBody').querySelector(coarse ? 'input[type=text]:not([data-k]),input[type=url],input[type=tel]:not([data-k])' : 'input:not([type=hidden]):not([type=time]),textarea,select,.opt,.c,.hday'); setTimeout(function () { if (f && visible() && (!coarse || st.type === 'text' || st.type === 'url_import')) try { f.focus({ preventScroll: true }); } catch (e) {} }, 40);
  }
  function err(m) { var e = g('onbErr'); if (!e) return; e.textContent = m || ''; e.classList.toggle('on', !!m); }
  function flashSaved() { var s = g('onbSaved'); if (!s) return; s.classList.add('on'); setTimeout(function () { s.classList.remove('on'); }, 1400); }
  function save(st, value) {
    if (BUSY) return; BUSY = true; err(''); var nb = g('onbNext'); if (nb) nb.disabled = true;
    api('/onboarding/answer', { method: 'POST', body: JSON.stringify({ id: S.onboarding.id, step: st.id, value: value }) }).then(function (j) {
      st.value = j.value != null ? j.value : st.value; st.answered = true; st.skipped = !!j.skipped; delete DRAFT[st.id]; flashSaved();
      if (st.type === 'url_import' && j.value && (!IMPORT || IMPORT.url !== j.value || IMPORT.status === 'error')) startImport(j.value);
      var q = queue(), i = q.indexOf(st), nx = q[i + 1];
      BUSY = false;
      if (!nx) return finish();
      CUR = nx.id; DIR = 'anim'; render();
    }).catch(function (e) { BUSY = false; if (nb) nb.disabled = false; err(e.message || _t('onbj.saveErr')); });
  }

  /* ---------------------------------------------------------------- Eingaben */
  function cents(v) { if (v == null || v === '') return ''; var n = Number(v); if (!isFinite(n)) return String(v); return (n / 100).toFixed(2).replace('.', I18N.dec === '.' ? '.' : ','); }
  function input(st) {
    var v = val(st), ph = st.ph ? ' placeholder="' + X(st.ph) + '"' : '';
    switch (st.type) {
      case 'text': return '<input class="big" type="' + (st.input === 'tel' ? 'tel' : 'text') + '" id="onbV" value="' + h(v || '') + '"' + ph + ' maxlength="' + (st.max || 300) + '"' + (st.auto_complete ? ' autocomplete="' + h(st.auto_complete) + '"' : '') + (st.input === 'tel' ? ' inputmode="tel"' : '') + '>';
      case 'textarea': return '<textarea id="onbV"' + ph + ' maxlength="' + (st.max || 2000) + '">' + h(v || '') + '</textarea><div class="mute small" style="margin-top:6px">' + _t('onbj.ctrlEnter') + '</div>';
      case 'url_import': return '<div class="imp"><input class="big" type="url" id="onbV" inputmode="url" autocomplete="url" value="' + h(v || '') + '"' + ph + '><button class="btn" type="button" id="onbImp">' + _t('onbj.import') + '</button></div><div class="impst" id="onbImpSt" aria-live="polite"></div>';
      case 'choice': return '<div class="opts' + (st.options.length > 3 && !st.options.some(function (o) { return o.d; }) ? ' two' : '') + '" role="radiogroup">' + st.options.map(function (o) { return '<button type="button" role="radio" class="opt' + (String(v) === o.v ? ' on' : '') + '" aria-checked="' + (String(v) === o.v) + '" data-v="' + h(o.v) + '"><span class="bx"></span><span><b>' + X(o.l) + '</b>' + (o.d ? '<span class="d">' + X(o.d) + '</span>' : '') + '</span></button>'; }).join('') + '</div>';
      case 'multi': return multiInput(st, Array.isArray(v) ? v : []);
      case 'hours': return hoursInput(st, v || {});
      case 'rows': return rowsInput(st, Array.isArray(v) ? v : []);
      case 'fields': return '<div class="fields">' + st.fields.map(function (f) { return '<div class="f' + (f.t === 'text' && (f.max || 0) > 100 ? ' full' : '') + '"><label for="onbF_' + f.k + '">' + X(f.l) + (f.req ? '' : '') + '</label>' + fieldInput(f, (v || {})[f.k], 'onbF_' + f.k) + '</div>'; }).join('') + '</div>';
      case 'langs': return langsInput(v || { default: 'de', allowed: [] });
    }
    return '';
  }
  function fieldInput(f, v, id) {
    var ph = f.ph ? ' placeholder="' + X(f.ph) + '"' : '', idA = id ? ' id="' + id + '"' : ' aria-label="' + X(f.l) + '"', d = ' data-k="' + h(f.k) + '" data-t="' + h(f.t) + '"';
    if (f.t === 'select') return '<select' + idA + d + '>' + (f.opts || []).map(function (o) { return '<option value="' + h(o.v) + '"' + (String(v) === o.v ? ' selected' : '') + '>' + X(o.l) + '</option>'; }).join('') + '</select>';
    if (f.t === 'time') return '<input type="time"' + idA + d + ' value="' + h(v || '') + '"' + ph + '>';
    if (f.t === 'textarea') return '<textarea' + idA + d + ph + ' maxlength="' + (f.max || 2000) + '">' + h(v || '') + '</textarea>';
    if (f.t === 'password') { var masked = typeof v === 'string' && v.indexOf('••••') === 0; return '<input type="password" autocomplete="off"' + idA + d + ' value=""' + (masked ? ' placeholder="' + h(_t('onbj.keep',{v:v})) + '"' : ph) + '>'; }
    var val0 = f.t === 'money' ? cents(v) : (v == null ? '' : String(v)), mode = f.t === 'money' || f.t === 'pct' ? ' inputmode="decimal"' : (f.t === 'int' ? ' inputmode="numeric"' : (f.t === 'tel' ? ' inputmode="tel"' : ''));
    var inp = '<input type="' + (f.t === 'tel' ? 'tel' : 'text') + '"' + idA + d + mode + ' value="' + h(val0) + '"' + ph + (f.max ? ' maxlength="' + f.max + '"' : '') + '>';
    return f.suffix ? '<div class="suf">' + inp + '<em>' + X(f.suffix) + '</em></div>' : inp;
  }
  function multiInput(st, v) {
    var rich = st.options.some(function (o) { return o.d; }), opts = st.options.map(function (o) { return o.v; });
    var own = v.filter(function (x) { return opts.indexOf(x) < 0; });
    if (rich) return '<div class="opts">' + st.options.map(function (o) { var on = v.indexOf(o.v) >= 0; return '<button type="button" role="checkbox" class="opt multi' + (on ? ' on' : '') + '" aria-checked="' + on + '" data-v="' + h(o.v) + '"><span class="bx"></span><span><b>' + X(o.l) + '</b>' + (o.d ? '<span class="d">' + X(o.d) + '</span>' : '') + '</span></button>'; }).join('') + '</div>';
    return '<div class="chipset" id="onbChips">' + st.options.map(function (o) { var on = v.indexOf(o.v) >= 0; return '<button type="button" role="checkbox" class="c' + (on ? ' on' : '') + '" aria-checked="' + on + '" data-v="' + h(o.v) + '">' + (on ? '✓ ' : '') + X(o.l) + '</button>'; }).join('')
      + own.map(function (x) { return '<button type="button" role="checkbox" class="c on own" aria-checked="true" data-v="' + h(x) + '">✓ ' + h(x) + '</button>'; }).join('') + '</div>'
      + (st.free ? '<div class="free"><input type="text" id="onbFree" maxlength="160" placeholder="' + (st.free_ph ? X(st.free_ph) : h(_t('onbj.own'))) + '"><button class="btn" type="button" id="onbFreeAdd">' + _t('onbj.add') + '</button></div>' : '');
  }
  function hoursInput(st, v) {
    var presets = [[_t('onbj.pre1'), { 1: [['09:00', '18:00']], 2: [['09:00', '18:00']], 3: [['09:00', '18:00']], 4: [['09:00', '18:00']], 5: [['09:00', '18:00']] }],
      [_t('onbj.pre2'), { 1: [['09:00', '20:00']], 2: [['09:00', '20:00']], 3: [['09:00', '20:00']], 4: [['09:00', '20:00']], 5: [['09:00', '20:00']], 6: [['09:00', '20:00']] }],
      [_t('onbj.pre3'), { 0: [['11:00', '22:00']], 1: [['11:00', '22:00']], 2: [['11:00', '22:00']], 3: [['11:00', '22:00']], 4: [['11:00', '22:00']], 5: [['11:00', '22:00']], 6: [['11:00', '22:00']] }]];
    if (!st.same_day) presets.push([_t('onbj.pre4'), { 0: [['00:00', '24:00']], 1: [['00:00', '24:00']], 2: [['00:00', '24:00']], 3: [['00:00', '24:00']], 4: [['00:00', '24:00']], 5: [['00:00', '24:00']], 6: [['00:00', '24:00']] }]);
    hoursInput.P = presets;
    return '<div class="chipset" style="margin-bottom:12px">' + presets.map(function (p, i) { return '<button type="button" class="c" data-preset="' + i + '">' + h(p[0]) + '</button>'; }).join('') + '<button type="button" class="c add" data-copy="1">' + _t('onbj.copyMon') + '</button></div>'
      + '<div class="hours" id="onbHours">' + WD_ORDER.map(function (d) { return hourRow(d, v[String(d)] || []); }).join('') + '</div>';
  }
  function hourRow(d, rows) {
    var open = rows.length > 0;
    return '<div class="hrow" data-d="' + d + '"><button type="button" class="hday' + (open ? ' on' : '') + '" aria-pressed="' + open + '" aria-label="' + h(WD[d] + ' ' + (open ? _t('hr.open') : _t('onbj.closed'))) + '">' + h(WD[d]) + '</button><div class="hr">'
      + (open ? rows.map(function (r, i) { return '<div class="hiv"><input type="time" value="' + h(r[0]) + '" aria-label="' + h(_t('onbj.from',{d:WD[d]})) + '"><i>–</i><input type="time" value="' + h(r[1] === '24:00' ? '23:59' : r[1]) + '" data-end24="' + (r[1] === '24:00' ? 1 : 0) + '" aria-label="' + h(_t('onbj.to',{d:WD[d]})) + '">' + (i > 0 ? '<button type="button" class="hx" data-rm="' + i + '" aria-label="' + h(_t('hr.del')) + '">×</button>' : '<span></span>') + '</div>'; }).join('')
        + (rows.length < 2 ? '<button type="button" class="hadd">' + _t('onbj.second') + '</button>' : '') : '<div class="hclosed">' + _t('onbj.closed') + '</div>') + '</div></div>';
  }
  function hoursCollect() {
    var o = {}; document.querySelectorAll('#onbHours .hrow').forEach(function (r) { var d = r.dataset.d, rows = [];
      r.querySelectorAll('.hiv').forEach(function (iv) { var t = iv.querySelectorAll('input[type=time]'); var a = t[0].value, b = t[1].value; if (b === '23:59' && t[1].dataset.end24 === '1') b = '24:00'; if (a && b) rows.push([a, b]); });
      o[d] = rows; }); return o;
  }
  function rowsInput(st, v, id) {
    id = id || 'onbRows'; var list = v.length ? v : (st.min ? [{}] : []);
    return '<div class="rows" id="' + id + '">' + list.map(function (r) { return rowHtml(st, r); }).join('') + '</div><button type="button" class="addrow" id="' + id + 'Add">＋ ' + (st.add ? X(st.add) : _t('onbj.add')) + '</button>'
      + (st.presets && st.presets.length ? '<div class="presets"><span class="lbl" style="margin-top:4px">' + _t('onbj.quick') + '</span><div class="chipset">' + st.presets.map(function (p, i) { return '<button type="button" class="c" data-pre="' + i + '" data-for="' + id + '">＋ ' + h(presetLabel(st, p)) + '</button>'; }).join('') + '</div></div>' : '');
  }
  function presetLabel(st, p) { var parts = [_tx(p.name)]; if (p.duration_min) parts.push(_t('onbj.minP',{n:p.duration_min})); if (p.capacity) parts.push(_t('onbj.pers',{n:p.capacity})); if (p.price_cents != null) parts.push(p.price_cents ? I18N.eur(p.price_cents / 100) : _t('onbj.onReq')); return parts.join(' · '); }
  function rowHtml(st, r) {
    return '<div class="rw"><div class="g">' + st.cols.map(function (c) { var w = Math.min(5, c.w || 1); return '<div class="f w' + w + '"><label>' + X(c.l) + '</label>' + fieldInput(c, r[c.k] != null ? r[c.k] : '', '') + '</div>'; }).join('') + '</div><button type="button" class="del" aria-label="' + h(_t('onbj.rmRow')) + '">×</button></div>';
  }
  function rowsCollect(st, id) {
    var out = []; document.querySelectorAll('#' + (id || 'onbRows') + ' .rw').forEach(function (rw) { var o = {}, any = false; rw.querySelectorAll('[data-k]').forEach(function (x) { o[x.dataset.k] = x.value; if (x.value.trim() !== '') any = true; }); if (any) out.push(o); });
    return out;
  }
  function bindRows(st, id, body) {
    id = id || 'onbRows'; var rows = g(id);
    var bindDel = function () { rows.querySelectorAll('.del').forEach(function (b) { b.onclick = function () { b.closest('.rw').remove(); }; }); };
    bindDel();
    g(id + 'Add').onclick = function () { rows.insertAdjacentHTML('beforeend', rowHtml(st, {})); bindDel(); var f = rows.lastElementChild.querySelector('input,textarea'); if (f) f.focus(); };
    body.querySelectorAll('[data-pre][data-for="' + id + '"]').forEach(function (b) { b.onclick = function () { var p0 = st.presets[+b.dataset.pre], p = {}; Object.keys(p0).forEach(function (k) { p[k] = typeof p0[k] === 'string' ? _tx(p0[k]) : p0[k]; });   /* Vorschlag in der Oberflächensprache eintragen */
      var empty = Array.prototype.filter.call(rows.querySelectorAll('.rw'), function (rw) { return !Array.prototype.some.call(rw.querySelectorAll('[data-k]'), function (x) { return x.value.trim() !== ''; }); })[0];
      if (empty) empty.outerHTML = rowHtml(st, p); else rows.insertAdjacentHTML('beforeend', rowHtml(st, p)); bindDel(); b.disabled = true; b.style.opacity = '.45'; }; });
  }
  function bindHours(body) {
    var host = g('onbHours');
    var rebind = function () {
      host.querySelectorAll('.hrow').forEach(function (r) { var d = +r.dataset.d;
        r.querySelector('.hday').onclick = function () { var cur = hoursCollect()[d] || []; r.outerHTML = hourRow(d, cur.length ? [] : [['09:00', '18:00']]); rebind(); };
        var ad = r.querySelector('.hadd'); if (ad) ad.onclick = function () { var cur = hoursCollect()[d] || []; cur.push(['15:00', '19:00']); r.outerHTML = hourRow(d, cur); rebind(); };
        r.querySelectorAll('[data-rm]').forEach(function (x) { x.onclick = function () { var cur = hoursCollect()[d] || []; cur.splice(+x.dataset.rm, 1); r.outerHTML = hourRow(d, cur); rebind(); }; });
        r.querySelectorAll('input[type=time]').forEach(function (t) { t.addEventListener('change', function () { t.dataset.end24 = '0'; }); });
      });
    };
    rebind();
    body.querySelectorAll('[data-preset]').forEach(function (b) { b.onclick = function () { var p = hoursInput.P[+b.dataset.preset][1]; host.innerHTML = WD_ORDER.map(function (d) { return hourRow(d, p[d] || []); }).join(''); rebind(); }; });
    var cp = body.querySelector('[data-copy]'); if (cp) cp.onclick = function () { var cur = hoursCollect(); var mo = cur[1] || []; host.innerHTML = WD_ORDER.map(function (d) { return hourRow(d, d >= 1 && d <= 5 ? mo.map(function (x) { return x.slice(); }) : (cur[d] || [])); }).join(''); rebind(); };
  }
  function langsInput(v) {
    var L = (S && S.langs) || [], al = v.allowed && v.allowed.length ? v.allowed : L.map(function (x) { return x.v; });
    return '<span class="lbl" style="margin-top:0">' + _t('onbj.defLang') + '</span><div class="chipset" id="onbLangDef" role="radiogroup">' + L.map(function (l) { var on = v.default === l.v; return '<button type="button" role="radio" class="c' + (on ? ' on' : '') + '" aria-checked="' + on + '" data-v="' + l.v + '">' + h(l.l) + '</button>'; }).join('') + '</div>'
      + '<span class="lbl">' + _t('onbj.alsoLang') + '</span><div class="chipset" id="onbLangAl">' + L.map(function (l) { var on = al.indexOf(l.v) >= 0; return '<button type="button" role="checkbox" class="c' + (on ? ' on' : '') + '" aria-checked="' + on + '" data-v="' + l.v + '">' + (on ? '✓ ' : '') + h(l.l) + '</button>'; }).join('') + '</div>'
      + '<div class="row" style="margin-top:10px;gap:6px"><button type="button" class="btn s" data-all="1">' + _t('onbj.allLangs') + '</button><button type="button" class="btn s" data-all="0">' + _t('onbj.onlyDef') + '</button></div>';
  }
  function toggleChip(b, on) { b.classList.toggle('on', on); b.setAttribute('aria-checked', on ? 'true' : 'false'); if (b.classList.contains('c')) b.textContent = (on ? '✓ ' : '') + b.textContent.replace(/^✓ /, ''); }
  function bindStep(st) {
    var body = g('onbBody');
    if (st.type === 'choice') body.querySelectorAll('.opt').forEach(function (b) { b.onclick = function () { body.querySelectorAll('.opt').forEach(function (x) { toggleChip(x, x === b); }); DRAFT[st.id] = b.dataset.v; if (st.auto) setTimeout(function () { if (CUR === st.id) save(st, b.dataset.v); }, 230); }; });
    if (st.type === 'multi') {
      body.querySelectorAll('.opt,.c').forEach(function (b) { b.onclick = function () { var on = !b.classList.contains('on'); if (b.classList.contains('own') && !on) { b.remove(); return; } toggleChip(b, on); }; });
      var fa = g('onbFreeAdd'), fi = g('onbFree');
      if (fa && fi) { var add = function () { var t = fi.value.trim(); if (!t) return; var c = document.createElement('button'); c.type = 'button'; c.className = 'c on own'; c.dataset.v = t; c.setAttribute('role', 'checkbox'); c.setAttribute('aria-checked', 'true'); c.textContent = '✓ ' + t; c.onclick = function () { c.remove(); }; g('onbChips').appendChild(c); fi.value = ''; fi.focus(); };
        fa.onclick = add; fi.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); e.stopPropagation(); if (fi.value.trim()) add(); else save(st, collect(st)); } }); }
    }
    if (st.type === 'hours') bindHours(body);
    if (st.type === 'rows') bindRows(st, 'onbRows', body);
    if (st.type === 'langs') {
      var def = g('onbLangDef'), al = g('onbLangAl');
      def.querySelectorAll('.c').forEach(function (b) { b.onclick = function () { def.querySelectorAll('.c').forEach(function (x) { toggleChip(x, x === b); }); var m = al.querySelector('[data-v="' + b.dataset.v + '"]'); if (m) toggleChip(m, true); }; });
      al.querySelectorAll('.c').forEach(function (b) { b.onclick = function () { var d = def.querySelector('.c.on'); if (d && d.dataset.v === b.dataset.v) return; toggleChip(b, !b.classList.contains('on')); }; });
      body.querySelectorAll('[data-all]').forEach(function (b) { b.onclick = function () { var d = def.querySelector('.c.on'); al.querySelectorAll('.c').forEach(function (x) { toggleChip(x, b.dataset.all === '1' || (d && x.dataset.v === d.dataset.v)); }); }; });
    }
    if (st.type === 'url_import') { g('onbImp').onclick = function () { var u = g('onbV').value.trim(); if (!u) { err(_t('onbj.urlNeed')); return; }
      api('/onboarding/answer', { method: 'POST', body: JSON.stringify({ id: S.onboarding.id, step: st.id, value: u }) }).then(function (j) { st.value = j.value; st.answered = true; g('onbV').value = j.value; flashSaved(); err(''); startImport(j.value); }).catch(function (e) { err(e.message); }); };
      renderImport(); }
    /* Enter = weiter (Textfelder: Strg/⌘ + Enter) */
    g('onbCard').onkeydown = function (e) {
      if (e.key !== 'Enter' || e.isComposing || MODE !== 'step') return; var t = e.target;
      if (t && t.id === 'onbFree') return;
      if (t && t.tagName === 'TEXTAREA' && !(e.ctrlKey || e.metaKey)) return;
      if (t && t.tagName === 'BUTTON' && t.id !== 'onbNext' && !(t.classList.contains('opt') || t.classList.contains('c') || t.classList.contains('hday'))) return;
      if (t && (t.classList.contains('opt') || t.classList.contains('c') || t.classList.contains('hday')) && st.type !== 'choice') { e.preventDefault(); save(st, collect(st)); return; }
      e.preventDefault(); if (t && t.classList && t.classList.contains('opt') && st.type === 'choice') { t.click(); return; } save(st, collect(st));
    };
  }
  function collect(st) {
    var body = g('onbBody'); if (!body) return val(st);
    switch (st.type) {
      case 'text': case 'textarea': case 'url_import': return g('onbV').value;
      case 'choice': var c = body.querySelector('.opt.on'); return c ? c.dataset.v : (DRAFT[st.id] != null ? DRAFT[st.id] : null);
      case 'multi': return Array.prototype.map.call(body.querySelectorAll('.opt.on,.c.on'), function (b) { return b.dataset.v; });
      case 'hours': return hoursCollect();
      case 'rows': return rowsCollect(st);
      case 'fields': var o = {}; body.querySelectorAll('[data-k]').forEach(function (x) { o[x.dataset.k] = x.value; }); return o;
      case 'langs': var d = body.querySelector('#onbLangDef .c.on'); return { default: d ? d.dataset.v : 'de', allowed: Array.prototype.map.call(body.querySelectorAll('#onbLangAl .c.on'), function (b) { return b.dataset.v; }) };
    }
    return null;
  }

  /* ---------------------------------------------------------------- Website-Import (Training) */
  function trainPost(path, body) { return api(path, { method: 'POST', body: JSON.stringify(Object.assign({ plan: S.onboarding.base }, body || {})) }); }
  function startImport(url) {
    IMPORT = { url: url, status: 'running', progress: _t('onbj.reading'), plan: S.onboarding.base }; renderImport();
    trainPost('/train/import-url', { url: url }).then(onJob).catch(function (e) { IMPORT.status = 'error'; IMPORT.message = e.message || _t('onbj.impFail'); renderImport(); });
  }
  function onJob(j) {
    if (!IMPORT) return; IMPORT.job = j.job || IMPORT.job; IMPORT.progress = j.progress || IMPORT.progress;
    if (j.status === 'running') { renderImport(); setTimeout(function () { if (!IMPORT || IMPORT.status !== 'running') return; trainPost('/train/job', { id: IMPORT.job }).then(onJob).catch(function () { setTimeout(function () { if (IMPORT && IMPORT.status === 'running') onJob({ status: 'running' }); }, 4000); }); }, 1500); return; }
    if (j.status === 'done' && j.result && j.result.preview) {
      IMPORT.progress = _t('onbj.applying'); renderImport();
      trainPost('/train/apply', { text: j.result.preview, mode: 'append', job: IMPORT.job, title: j.result.title || IMPORT.url }).then(function (r) { IMPORT.status = 'done'; IMPORT.chars = r.added_chars || 0; renderImport(); })
        .catch(function (e) { IMPORT.status = 'error'; IMPORT.message = e.message; renderImport(); });
      return;
    }
    IMPORT.status = 'error'; IMPORT.message = (j.message && _tx(j.message)) || _t('onbj.impFail2'); renderImport();
  }
  function renderImport() {
    var el = g('onbImpSt'); if (!el || !IMPORT) return;
    el.className = 'impst on ' + (IMPORT.status === 'running' ? 'run' : IMPORT.status === 'done' ? 'ok' : 'err');
    el.innerHTML = IMPORT.status === 'running' ? '<b>' + _t('onbj.weRead') + '</b> ' + X(IMPORT.progress || '') + '<div class="bar"><i></i></div><div class="small" style="margin-top:6px;opacity:.8">' + _t('onbj.bg') + '</div>'
      : IMPORT.status === 'done' ? '✓ <b>' + _t('onbj.imported') + '</b> ' + h(_t('onbj.impDone',{n:I18N.num(IMPORT.chars || 0),url:IMPORT.url}))
      : h(IMPORT.message || _t('onbj.impFail')) + ' <span class="small">' + _t('onbj.noProb') + '</span>';
  }

  /* ---------------------------------------------------------------- Abschluss */
  function renderFinishing() {
    g('onbCard').innerHTML = '<div class="hero" style="text-align:center;padding:48px 26px"><div class="ic" style="margin:0 auto">' + SV(IC.spark, 28) + '</div><h2 style="font-size:28px">' + _t('onbj.setting') + '</h2><p style="margin:12px auto 0">' + _t('onbj.settingTxt') + '</p><div class="bar" style="max-width:240px;margin:22px auto 0;background:var(--goldl)"><i style="background:var(--gold)"></i></div></div>';
  }
  function finish() {
    MODE = 'finishing'; render();
    api('/onboarding/finish', { method: 'POST', body: JSON.stringify({ id: S.onboarding.id }) }).then(function (j) { S = j; APPLIED = j.applied || []; MODE = 'done'; DIR = 'anim'; render(); try { if (typeof refreshMe === 'function') refreshMe(); } catch (e) {} })
      .catch(function (e) { MODE = 'step'; render(); err(e.message || _t('onbj.finFail')); });
  }
  function renderDone() {
    var o = S.onboarding, C = S.checklist || [], L = S.links || {}, nd = C.filter(function (c) { return c.status === 'done'; }).length, nt = C.filter(function (c) { return c.status !== 'info'; }).length, priv = !!o.private;
    var hasPhone = C.some(function (c) { return c.key === 'phone'; }), hasWa = C.some(function (c) { return c.key === 'whatsapp'; });
    var ok = (APPLIED || []).filter(function (a) { return a.ok; }), bad = (APPLIED || []).filter(function (a) { return !a.ok; });
    var acts = [], num = 0, LINKS = { appt: _t('onbj.lk.appt'), order: _t('onbj.lk.order'), hotel: _t('onbj.lk.hotel'), ride: _t('onbj.lk.ride'), callback: _t('onbj.lk.cb') };
    if (L.widget_snippet && C.some(function (c) { return c.key === 'widget'; })) acts.push('<button type="button" class="act p" data-a="copy">' + SV(IC.code) + '<span>' + _t('onbj.a.copy') + '</span></button>');
    if (hasPhone) acts.push('<button type="button" class="act" data-a="phone">' + SV(IC.phone) + '<span>' + _t('onbj.a.phone') + '</span></button>');
    if (hasWa) acts.push('<button type="button" class="act" data-a="whatsapp">' + SV(IC.wa) + '<span>' + _t('onbj.a.wa') + '</span></button>');
    acts.push('<a class="act" href="/chat/app.php" data-a="app">' + SV(IC.app) + '<span>' + _t('onbj.a.app') + '</span></a>');
    acts.unshift('<button type="button" class="act' + (o.trial ? ' p' : '') + '" data-a="test">' + SV(IC.chat) + '<span>' + _t('onbj.a.test') + '</span></button>', '<button type="button" class="act" data-a="train">' + SV(IC.edit) + '<span>' + _t('onbj.a.train') + '</span></button>');   // zuerst mit dem Agenten sprechen und ihn trainieren
    if (o.trial && o.checkout) acts.push('<a class="act p" href="' + h(o.checkout) + '" target="_blank" rel="noopener" data-a="live">' + SV(IC.spark) + '<span>' + _t('onbj.a.live') + '</span></a>');   // Testphase: Live schalten (Zahlung) ganz am Ende
    g('onbCard').innerHTML = '<div class="onb-head">' + ring(nt ? Math.round(100 * nd / nt) : 100) + '<div class="onb-meta"><div class="k">' + _t('onbj.doneK') + '</div><div class="n">' + h(_t('onbj.stepsDone',{d:nd,n:nt})) + ' · ' + h(o.base_name) + '</div></div></div>'
      + '<div class="hero ' + DIR + '" style="padding-top:18px"><h2>' + _t('onbj.ready') + '</h2><p>' + h(ok.length ? (priv ? _t('onbj.knowsPx',{x:ok.map(function (a) { return _tx(a.label); }).join(', ')}) : _t('onbj.knowsAx',{x:ok.map(function (a) { return _tx(a.label); }).join(', ')})) : (priv ? _t('onbj.knowsP') : _t('onbj.knowsA'))) + '</p>'
      + (bad.length ? '<div class="msg err">' + h(bad.map(function (a) { return _tx(a.label) + ': ' + _tx(a.detail); }).join(' · ')) + '</div>' : '') + '</div>'
      + '<div class="acts">' + acts.join('') + '</div>'
      + '<div class="checks">' + C.map(function (c) { if (c.status !== 'done') num++; return '<div class="ck ' + c.status + '"><span class="m">' + (c.status === 'done' ? '✓' : num) + '</span><div class="t"><b>' + X(c.label) + (c.status === 'done' ? '<span class="pill2">' + _t('onbj.done') + '</span>' : c.status === 'todo' ? '<span class="pill2">' + _t('onbj.open') + '</span>' : '') + '</b>'
        + (c.detail ? '<p>' + X(c.detail) + '</p>' : '') + (c.steps && c.steps.length && c.status !== 'done' ? '<ol>' + c.steps.map(function (s) { return '<li>' + X(s) + '</li>'; }).join('') + '</ol>' : '')
        + (c.codes && c.codes.length ? '<div class="fwd">' + c.codes.map(function (x) { return '<a href="' + h(x.tel) + '"' + (x.key === 'off' ? ' class="off"' : '') + '><code>' + h(x.code) + '</code><span>' + X(x.label) + '</span></a>'; }).join('') + '</div>' : '')
        + (c.code ? '<div class="snip">' + h(c.code) + '</div>' : '') + (c.url && LINKS[c.key] ? '<a class="cklink" href="' + h(c.url) + '" target="_blank" rel="noopener">' + LINKS[c.key] + ' →</a>' : '') + '</div></div>'; }).join('') + '</div>'
      + '<div class="onb-foot donefoot"><button class="btn" type="button" id="onbEdit">' + SV(IC.edit, 16) + ' ' + _t('onbj.change') + '</button><span class="sp"></span><button class="btn" type="button" id="onbMail">' + SV(IC.mail, 16) + ' ' + _t('onbj.send') + '</button></div><div id="onbMsg" style="padding:0 26px 18px"></div>';
    g('onbCard').querySelectorAll('[data-a]').forEach(function (b) { b.addEventListener('click', function (e) { var a = b.dataset.a;
      if (a === 'copy') { e.preventDefault(); copy(L.widget_snippet, b); }
      if (a === 'phone') { e.preventDefault(); go('phone'); }
      if (a === 'whatsapp') { e.preventDefault(); go('whatsapp'); }
      if (a === 'test') { e.preventDefault(); if (typeof openAgent === 'function') openAgent(L.test_plan || o.base); else location.href = L.studio_test; }
      if (a === 'train') { e.preventDefault(); if (document.querySelector('#tabs .tab[data-v="training"]') || document.getElementById('panel-training')) go('training'); else if (typeof openAgent === 'function') openAgent(L.test_plan || o.base); }
    }); });
    g('onbEdit').onclick = function () { SHOWALL = true; MODE = 'step'; CUR = (queue()[0] || {}).id; DIR = 'anim'; render(); };
    g('onbMail').onclick = function () { var b = g('onbMail'); b.disabled = true; api('/onboarding/resend-guide', { method: 'POST', body: JSON.stringify({ id: o.id }) }).then(function (j) { g('onbMsg').innerHTML = '<div class="msg ok">' + h((j.message && _tx(j.message)) || _t('onbj.sent')) + '</div>'; }).catch(function (e) { g('onbMsg').innerHTML = '<div class="msg err">' + h(e.message) + '</div>'; }).then(function () { b.disabled = false; }); };
  }
  function copy(t, b) {
    var done = function () { var s = b.querySelector('span'); var old = s.textContent; s.textContent = _t('c.copied'); setTimeout(function () { s.textContent = old; }, 1800); };
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(t).then(done, function () { fallback(); }); else fallback();
    function fallback() { var ta = document.createElement('textarea'); ta.value = t; ta.style.position = 'fixed'; ta.style.opacity = '0'; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); done(); } catch (e) {} ta.remove(); }
  }

  /* ---------------------------------------------------------------- Schnellstart (quickstart-lib, Modus „qs“) */
  var QS_ORDER = ['link', 'draft', 'sector', 'voice', 'try', 'plan', 'number', 'live'], QS_SHOW = ['link', 'draft', 'voice', 'try', 'plan', 'number', 'live'];
  var NUM_CC = ['DE', 'AT', 'CH', 'NL', 'BE', 'FR', 'ES', 'IT', 'GB', 'SE', 'NO', 'FI', 'DK', 'PL', 'US', 'CA'];
  var LANGTAG = { de: 'de-DE', en: 'en-GB', fr: 'fr-FR', es: 'es-ES', tr: 'tr-TR', az: 'az-AZ', ru: 'ru-RU', it: 'it-IT', nl: 'nl-NL', sv: 'sv-SE', no: 'nb-NO', fi: 'fi-FI', ja: 'ja-JP' };
  var AU = { a: null, url: null, gen: 0, btn: null }, POLL = null;
  function qsLoad() { return api('/onboarding/qs?id=' + S.onboarding.id).then(function (j) { if (!QS) QS = { step: '', chat: [], seen: {}, draft: null, speak: true }; QS.d = j.qs; QS.sectors = j.sectors || []; return j; }); }
  /** Schritte, die es für diese Einrichtung nicht gibt: Branche nur ohne Link, Stimme/Nummer nur mit Telefon, Paket nur in der Testphase */
  function qsSkip(s) { var d = QS.d; return s === 'sector' || (s === 'voice' && !d.phone) || (s === 'plan' && d.active) || (s === 'number' && !d.phone); }
  function qsNext(st) { for (var k = QS_ORDER.indexOf(st) + 1; k < QS_ORDER.length; k++) if (!qsSkip(QS_ORDER[k])) return QS_ORDER[k]; return 'live'; }
  function qsPrev(st) { if (st === 'sector') return 'link'; for (var k = QS_ORDER.indexOf(st) - 1; k >= 0; k--) if (!qsSkip(QS_ORDER[k]) && (QS_ORDER[k] !== 'draft' || QS.draft)) return QS_ORDER[k]; return 'link'; }
  function qsEv(step, ev) { try { api('/onboarding/event', { method: 'POST', body: JSON.stringify({ id: S.onboarding.id, step: step, ev: ev }) }).catch(function () {}); } catch (e) { /* egal */ } }
  function qsSetStep(step) { api('/onboarding/qs', { method: 'POST', body: JSON.stringify({ id: S.onboarding.id, step: step }) }).then(function (j) { if (j.qs) QS.d = j.qs; }).catch(function () {}); }
  /** Schritt wechseln (back/skip werden gezählt; „done“ zählt der Server bei Import/Entwurf/Branche/Stimme/Nummer, das Studio bei Test und Paket) */
  function qsGo(step, opts) {
    opts = opts || {}; qsStopAudio(); if (QS.step && QS.step !== step && (opts.back || opts.skip)) qsEv(QS.step, opts.back ? 'back' : 'skip');
    if (step === 'live' && S.onboarding.status !== 'done') return qsFinish();
    QS.step = step; if (opts.server !== false) qsSetStep(step); DIR = opts.back ? 'back' : 'anim'; renderQs();
  }
  /** Zum Schluss alles übernehmen (onboarding/finish: Module, Wissensbasis, Anleitung per Mail) und „live“ zeigen */
  function qsFinish() {
    var back = QS.step; MODE = 'finishing'; render();
    api('/onboarding/finish', { method: 'POST', body: JSON.stringify({ id: S.onboarding.id }) }).then(function (j) { S = j; APPLIED = j.applied || []; MODE = 'qs'; try { if (typeof refreshMe === 'function') refreshMe(); } catch (e) { /* egal */ }
      return qsLoad().then(function () { QS.step = 'live'; qsSetStep('live'); DIR = 'anim'; renderQs(); }); })
      .catch(function (e) { MODE = 'qs'; QS.step = back || 'try'; renderQs(); err(e.message || _t('onbj.finFail')); qsEv(back || 'live', 'error'); });
  }
  /** Stand nachladen (Panel wieder sichtbar, Rückkehr von der Kasse): Paket aktiv → weiter zur Nummer; Nummer/live neu zeichnen */
  function qsRefresh() { qsLoad().then(function () { if (QS.step === 'plan' && QS.d.active) { qsGo(qsNext('plan')); return; } if (QS.step === 'live' || QS.step === 'number') renderQs(); }).catch(function () {}); }
  function qsStopPoll() { if (POLL) { clearInterval(POLL); POLL = null; } }
  /** Paket-Schritt: auf die Zahlung warten · live-Schritt: auf den ersten Anruf warten */
  function qsStartPoll() {
    qsStopPoll(); POLL = setInterval(function () {
      if (!visible() || MODE !== 'qs' || !QS || (QS.step !== 'live' && QS.step !== 'plan')) { qsStopPoll(); return; }
      api('/onboarding/qs?id=' + S.onboarding.id).then(function (j) { var was = QS.d.live; QS.d = j.qs; if (QS.step === 'plan' && j.qs.active) qsGo(qsNext('plan')); else if (QS.step === 'live' && !was && j.qs.live) renderQs(); }).catch(function () {});
    }, 6000);
  }
  function renderQs() {
    if (!QS || !QS.d) { g('onbCard').innerHTML = '<div class="onb-empty mute">' + _t('onb.einrichtung_wird_geladen') + '</div>'; qsLoad().then(function () { qsResume(); renderQs(); }).catch(function () { MODE = S.onboarding.status === 'done' ? 'done' : 'intro'; render(); }); return; }
    var st = QS.step; if (!QS.seen[st]) { QS.seen[st] = 1; qsEv(st, 'view'); }
    if (st !== 'live' && st !== 'plan') qsStopPoll();
    ({ link: qsLink, draft: qsDraft, sector: qsSector, voice: qsVoice, try: qsTry, plan: qsPlan, number: qsNumber, live: qsLive })[st]();
  }
  /** Beim Öffnen: gespeicherten Schritt aufnehmen, Übersprungenes und Erledigtes auslassen */
  function qsResume() {
    var d = QS.d, st = d.started ? d.step : 'link';
    if (S.onboarding.status === 'done') st = 'live';
    if (st === 'draft' && !QS.draft) st = d.draft_at ? 'voice' : 'link';
    if (st === 'sector' && d.sector) st = 'voice';
    while (st !== 'live' && qsSkip(st)) st = qsNext(st);
    QS.step = st;
  }
  function toggleChipPlain(b, on) { b.classList.toggle('on', on); b.setAttribute('aria-checked', on ? 'true' : 'false'); }
  /* ---- Rahmen: Ring, „Schritt i von n“, Hilfe (Assistent, E-Mail, ausführliche Einrichtung) ---- */
  function qsFrame(st, title, sub, body, foot) {
    var vis = QS_SHOW.filter(function (s) { return !qsSkip(s); }), i = Math.max(0, vis.indexOf(st === 'sector' ? 'draft' : st)), n = vis.length, pct = Math.round(100 * i / (n - 1));
    g('onbCard').innerHTML = '<div class="onb-head">' + ring(pct) + '<div class="onb-meta"><div class="k">⚡ ' + _t('onbq.name') + '</div><div class="n">' + h(_t('onbq.stepOf', { i: i + 1, n: n })) + ' · ' + h(S.onboarding.base_name) + '</div></div><button type="button" class="qs-help" id="qsHelp" aria-expanded="false">' + _t('onbq.help') + '</button></div>'
      + '<div class="qs-helpbox hidden" id="qsHelpBox" role="dialog" aria-label="' + h(_t('onbq.help.t')) + '"><b>' + _t('onbq.help.t') + '</b>'
      + '<button type="button" data-h="ai"><span>' + _t('onbq.help.ai') + '</span><small>' + _t('onbq.help.aiD') + '</small></button>'
      + '<a href="mailto:support@chat-help.com?subject=' + encodeURIComponent('ChatHelp ' + _t('onbq.name') + ' – ' + S.onboarding.base_name) + '" data-h="mail"><span>' + _t('onbq.help.mail') + '</span><small>' + _t('onbq.help.mailD') + '</small></a>'
      + '<button type="button" data-h="full"><span>' + _t('onbq.help.full') + '</span><small>' + _t('onbq.help.fullD') + '</small></button></div>'
      + '<div class="onb-body ' + DIR + '" id="onbBody" data-step="qs-' + h(st) + '"><h2 class="q">' + title + '</h2>' + (sub ? '<p class="sub">' + sub + '</p>' : '') + '<div class="onb-in qs-in">' + body + '</div></div>'
      + '<div class="onb-err" id="onbErr" role="alert"></div>' + (foot ? '<div class="onb-foot">' + foot + '</div>' : '');
    var hb = g('qsHelp'), box = g('qsHelpBox');
    hb.onclick = function () { var hidden = box.classList.toggle('hidden'); hb.setAttribute('aria-expanded', hidden ? 'false' : 'true'); if (!hidden) qsEv(st, 'help'); };
    box.querySelectorAll('[data-h]').forEach(function (b) { b.addEventListener('click', function (e) { var k = b.dataset.h; if (k === 'ai') { e.preventDefault(); go('assistant'); } if (k === 'full') { e.preventDefault(); qsStopAudio(); qsStopPoll(); MODE = 'intro'; SHOWALL = true; render(); } }); });
  }
  function qsFoot(nextLabel, extra, noBack) { return (noBack ? '' : '<button class="btn" type="button" id="qsBack" aria-label="' + h(_t('onbj.back')) + '">←<span class="hide-s"> ' + _t('onbj.back') + '</span></button>') + '<span class="sp"></span>' + (extra || '') + (nextLabel ? '<button class="btn p" type="button" id="qsNext">' + nextLabel + ' →</button>' : ''); }
  function qsBindBack(st) { var b = g('qsBack'); if (b) b.onclick = function () { qsGo(qsPrev(st), { back: true }); }; }
  function qsBusy(on) { BUSY = on; var b = g('qsNext'); if (b) b.disabled = on; if (on) err(''); }
  /* ---- Ton: Server-Stimme des Agenten (onboarding/speak) bzw. Hörprobe einer Stimme (voices/sample), sonst Browserstimme ---- */
  function qsReq(path, body) {
    return fetch(API + path + '?_=' + Date.now(), { method: 'POST', cache: 'no-store', body: JSON.stringify(body), headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + key() } })
      .then(function (r) { var ct = r.headers.get('Content-Type') || ''; if (r.ok && ct.indexOf('audio') === 0) return r.blob(); return r.json().catch(function () { return {}; }).then(function (j) { if (!r.ok) { var e = new Error(_te({ data: j }, j.error || ('HTTP ' + r.status))); e.data = j; e.status = r.status; throw e; } return j; }); });
  }
  function qsStopAudio() {
    AU.gen++; try { if (AU.a) { AU.a.pause(); AU.a.removeAttribute('src'); AU.a.load(); } } catch (e) { /* egal */ }
    if (AU.url) { try { URL.revokeObjectURL(AU.url); } catch (e) { /* egal */ } AU.url = null; }
    try { if (window.speechSynthesis) speechSynthesis.cancel(); } catch (e) { /* egal */ }
    if (AU.btn) { AU.btn.classList.remove('playing'); AU.btn = null; }
  }
  function qsSay(text, btn, sampleId) {
    if (btn && AU.btn === btn) { qsStopAudio(); return; }
    qsStopAudio(); var gen = AU.gen, clean = String(text || '').replace(/\[(WEITERLEITEN|ENDE)\]/g, '').replace(/\s*…\s*/g, ', ').trim().slice(0, 400); if (!clean && !sampleId) return;
    if (btn) { AU.btn = btn; btn.classList.add('playing'); }
    var done = function () { if (gen !== AU.gen) return; if (AU.btn) { AU.btn.classList.remove('playing'); AU.btn = null; } };
    var say = function () { if (!window.speechSynthesis || !clean) { done(); return; } try { var u = new SpeechSynthesisUtterance(clean); u.lang = LANGTAG[QS.d.lang] || 'de-DE'; u.onend = u.onerror = function () { if (gen === AU.gen) done(); }; speechSynthesis.speak(u); } catch (e) { done(); } };
    (sampleId ? qsReq('/voices/sample', { id: sampleId }) : qsReq('/onboarding/speak', { id: S.onboarding.id, text: clean })).then(function (b) {
      if (gen !== AU.gen) return; if (!(b instanceof Blob)) throw new Error('audio');
      if (!AU.a) { AU.a = new Audio(); AU.a.preload = 'auto'; } AU.url = URL.createObjectURL(b); AU.a.src = AU.url; AU.a.onended = AU.a.onerror = function () { if (gen === AU.gen) done(); };
      var p = AU.a.play(); if (p && p.catch) p.catch(function () { if (gen === AU.gen) done(); });
    }).catch(function () { if (gen === AU.gen) say(); });
  }
  /* ---- 1 · Link ---- */
  function qsLink() {
    var ws = steps().filter(function (s) { return s.type === 'url_import'; })[0], cur = QS.url || (ws ? String(val(ws) || '') : '');
    qsFrame('link', _t('onbq.link.t'), _t('onbq.link.s'),
      '<div class="imp"><input class="big" type="url" id="qsUrl" inputmode="url" autocomplete="url" value="' + h(cur) + '" placeholder="' + h(_t('onbq.link.ph')) + '"><button class="btn p" type="button" id="qsImp">' + _t('onbq.link.go') + '</button></div>'
      + '<div class="impst" id="qsImpSt" aria-live="polite"></div>'
      + '<div class="qs-src">' + ['web', 'maps', 'instagram'].map(function (k) { return '<span>' + SV(IC[k === 'web' ? 'globe' : k], 16) + ' ' + _t('onbq.src.' + k + '.n') + '</span>'; }).join('') + '</div>',
      qsFoot(null, '<button class="skip" type="button" id="qsNone">' + _t('onbq.link.none') + ' →</button>', true));
    var run = function () {
      var u = g('qsUrl').value.trim(); if (!u) { err(_t('onbq.link.need')); return; } if (BUSY) return; qsBusy(true); QS.url = u; var b = g('qsImp'); b.disabled = true;
      var el = g('qsImpSt'); el.className = 'impst on run'; el.innerHTML = '<b>' + _t('onbj.weRead') + '</b> ' + _t('onbq.link.wait') + '<div class="bar"><i></i></div>';
      api('/onboarding/import', { method: 'POST', body: JSON.stringify({ id: S.onboarding.id, url: u, lang: I18N.lang }) }).then(function (j) { qsBusy(false); QS.draft = j.draft || {}; QS.kind = j.kind || 'web'; QS.thin = !!j.thin; QS.d = j.qs || QS.d; QS.step = 'draft'; DIR = 'anim'; renderQs(); })
        .catch(function (e) { qsBusy(false); b.disabled = false; el.className = 'impst on err'; el.innerHTML = h(e.message || _t('onbq.link.fail')) + ' <button type="button" class="qs-alt" id="qsAlt">' + _t('onbq.link.alt') + '</button>'; g('qsAlt').onclick = function () { qsGo('sector', { skip: true }); }; qsEv('link', 'error'); });
    };
    g('qsImp').onclick = run; g('qsUrl').onkeydown = function (e) { if (e.key === 'Enter') { e.preventDefault(); run(); } };
    g('qsNone').onclick = function () { qsGo('sector', { skip: true }); };
    if (!(window.matchMedia && matchMedia('(pointer:coarse)').matches)) setTimeout(function () { var f = g('qsUrl'); if (f && visible()) f.focus({ preventScroll: true }); }, 40);
  }
  /* ---- 2a · Entwurf bestätigen (Felder, Öffnungszeiten, Leistungen/Karte, häufige Fragen – dieselben Eingaben wie die ausführliche Einrichtung) ---- */
  function qsDraft() {
    var d = QS.draft || {}, sst = stepById('services') || stepById('menu'), fst = stepById('faq'), hst = stepById('hours');
    var F = [['company', 'text', 'organization'], ['address', 'text', 'street-address'], ['phone', 'tel', 'tel'], ['email', 'email', 'email'], ['website', 'url', 'url']];
    qsFrame('draft', _t('onbq.draft.t'), _t('onbq.draft.s', { src: _t('onbq.src.' + (QS.kind || 'web')) }) + (QS.thin ? ' ' + _t('onbq.draft.thin') : ''),
      '<div class="fields">' + F.map(function (f) { return '<div class="f"><label for="qsF_' + f[0] + '">' + _t('onbq.f.' + f[0]) + '</label><input type="' + f[1] + '" id="qsF_' + f[0] + '" data-q="' + f[0] + '" value="' + h(d[f[0]] || '') + '" autocomplete="' + f[2] + '"' + (f[1] === 'tel' ? ' inputmode="tel"' : '') + ' maxlength="300"></div>'; }).join('')
      + '<div class="f full"><label for="qsF_about">' + _t('onbq.f.about') + '</label><textarea id="qsF_about" data-q="about" maxlength="600">' + h(d.about || '') + '</textarea></div></div>'
      + (hst ? '<span class="lbl">' + _t('onbq.hours') + '</span>' + hoursInput({ same_day: false }, d.hours || {}) : '')
      + (sst ? '<span class="lbl">' + X(sst.title) + '</span>' + rowsInput(sst, Array.isArray(d[sst.id]) ? d[sst.id] : [], 'qsRowsMain') : '')
      + (fst ? '<span class="lbl">' + _t('onbq.faq') + '</span>' + rowsInput(fst, (Array.isArray(d.faq) ? d.faq : []).map(function (r) { return { question: r.question || r.q || '', answer: r.answer || r.a || '' }; }), 'qsRowsFaq') : ''),
      qsFoot(_t('onbq.draft.ok')));
    var body = g('onbBody'); if (hst) bindHours(body); if (sst) bindRows(sst, 'qsRowsMain', body); if (fst) bindRows(fst, 'qsRowsFaq', body);
    qsBindBack('draft');
    g('qsNext').onclick = function () {
      if (BUSY) return; qsBusy(true);
      var o = {}; body.querySelectorAll('[data-q]').forEach(function (x) { o[x.dataset.q] = x.value.trim(); });
      if (hst) o.hours = hoursCollect(); if (sst) o[sst.id] = qsRows(sst, 'qsRowsMain'); if (fst) o.faq = qsRows(fst, 'qsRowsFaq').map(function (r) { return { q: r.question, a: r.answer }; });
      api('/onboarding/draft', { method: 'POST', body: JSON.stringify({ id: S.onboarding.id, draft: o }) }).then(function (j) { QS.d = j.qs || QS.d; return api('/onboarding/state?id=' + S.onboarding.id).then(function (s) { S = s; }).catch(function () {}).then(function () { qsBusy(false); QS.step = qsNext('draft'); DIR = 'anim'; renderQs(); }); })
        .catch(function (e) { qsBusy(false); err(e.message || _t('onbj.saveErr')); });
    };
  }
  /** Zeilen für den Entwurf: Preis in Euro als Zahl, Dauer als Zahl */
  function qsRows(st, id) {
    return rowsCollect(st, id).map(function (r) { var o = {}; st.cols.forEach(function (c) { var v = r[c.k];
      if (c.t === 'money') { var s = String(v || ''); if (s.indexOf(',') >= 0) s = s.replace(/\./g, '').replace(',', '.'); var n = parseFloat(s); o.price = isFinite(n) ? n : 0; }
      else if (c.t === 'int') o[c.k] = parseInt(v, 10) || 0; else o[c.k] = v; }); return o; });
  }
  /* ---- 2b · Ohne Link: Branche + drei Fragen ---- */
  function qsSector() {
    var secs = QS.sectors || [], cur = QS.sector || QS.d.sector || '';
    var lab = function (s) { return typeof optLabel === 'function' ? optLabel('sectors', s.id, s.label) : _tx(s.label); }, S0 = secs.filter(function (s) { return s.id === cur; })[0];
    var pre = function (id) { var st = stepById(id); return st ? String(val(st) || '') : ''; };
    qsFrame('sector', _t('onbq.sector.t'), _t('onbq.sector.s'),
      '<div class="chipset qs-secs" role="radiogroup">' + secs.map(function (s) { return '<button type="button" role="radio" class="c' + (s.id === cur ? ' on' : '') + '" aria-checked="' + (s.id === cur) + '" data-sec="' + h(s.id) + '"><span aria-hidden="true">' + h(s.icon) + '</span> ' + h(lab(s)) + '</button>'; }).join('') + '</div>'
      + '<div class="qs-secq' + (cur ? '' : ' hidden') + '" id="qsSecQ"><span class="lbl" id="qsSecL">' + h(_t('onbq.sector.q', { s: S0 ? lab(S0) : '' })) + '</span>'
      + '<div class="fields"><div class="f"><label for="qsS_company">' + _t('onbq.f.company') + '</label><input type="text" id="qsS_company" value="' + h(pre('company')) + '" autocomplete="organization" maxlength="120"></div>'
      + '<div class="f"><label for="qsS_phone">' + _t('onbq.f.phone') + '</label><input type="tel" id="qsS_phone" inputmode="tel" value="' + h(pre('phone_public')) + '" autocomplete="tel" maxlength="40"></div>'
      + '<div class="f full"><label for="qsS_address">' + _t('onbq.f.address') + '</label><input type="text" id="qsS_address" value="' + h(pre('address')) + '" autocomplete="street-address" maxlength="200"></div></div>'
      + '<span class="lbl">' + _t('onbq.sector.hours') + '</span><div class="chipset" id="qsHp" role="radiogroup">' + [['mo-fr', 'onbq.hp.mofr'], ['mo-sa', 'onbq.hp.mosa'], ['gastro', 'onbq.hp.gastro'], ['24-7', 'onbq.hp.247']].map(function (p) { return '<button type="button" role="radio" class="c' + (p[0] === 'mo-fr' ? ' on' : '') + '" aria-checked="' + (p[0] === 'mo-fr') + '" data-hp="' + p[0] + '">' + _t(p[1]) + '</button>'; }).join('') + '</div></div>',
      qsFoot(_t('onbq.next')));
    var body = g('onbBody');
    body.querySelectorAll('[data-sec]').forEach(function (b) { b.onclick = function () { body.querySelectorAll('[data-sec]').forEach(function (x) { toggleChipPlain(x, x === b); }); QS.sector = b.dataset.sec; err(''); var s = secs.filter(function (x) { return x.id === QS.sector; })[0]; g('qsSecQ').classList.remove('hidden'); g('qsSecL').textContent = _t('onbq.sector.q', { s: s ? lab(s) : '' }); var f = g('qsS_company'); if (f && !f.value && !(window.matchMedia && matchMedia('(pointer:coarse)').matches)) f.focus(); }; });
    body.querySelectorAll('[data-hp]').forEach(function (b) { b.onclick = function () { body.querySelectorAll('[data-hp]').forEach(function (x) { toggleChipPlain(x, x === b); }); }; });
    qsBindBack('sector');
    g('qsNext').onclick = function () {
      if (!QS.sector) { err(_t('onbq.sector.need')); return; } if (BUSY) return; qsBusy(true); var hp = body.querySelector('[data-hp].on');
      api('/onboarding/sector', { method: 'POST', body: JSON.stringify({ id: S.onboarding.id, sector: QS.sector, company: g('qsS_company').value.trim(), address: g('qsS_address').value.trim(), phone: g('qsS_phone').value.trim(), hours: hp ? hp.dataset.hp : '' }) })
        .then(function (j) { QS.d = j.qs || QS.d; var oid = j.onboarding && j.onboarding.id; return api('/onboarding/state' + (oid ? '?id=' + oid : '')).then(function (s) { S = s; }).catch(function () {}).then(function () { qsBusy(false); QS.step = qsNext('sector'); DIR = 'anim'; renderQs(); }); })
        .catch(function (e) { qsBusy(false); err(e.message || _t('onbj.saveErr')); });
    };
  }
  /* ---- 3 · Stimme (drei je Sprache, Hörprobe) + Begrüßung (Vorschläge, Vorhören) ---- */
  function qsVoice() {
    var d = QS.d, V = d.voices || [], cur = d.voice_id || '', gr = d.greeting || '', G = d.greetings || [];
    var gl = function (x) { return _t(x === 'm' ? 'onbq.voice.m' : 'onbq.voice.f'); }, dsc = function (e) { return (e.desc || []).map(function (t) { return I18N.has('vc.d.' + t) ? _t('vc.d.' + t) : t; }).join(' · '); };
    qsFrame('voice', _t('onbq.voice.t'), _t('onbq.voice.s'),
      (V.length ? '<div class="qs-voices" role="radiogroup">' + V.map(function (e) { var on = e.id === cur; return '<div class="qs-voice' + (on ? ' on' : '') + '" data-v="' + h(e.id) + '"><button type="button" class="pl" data-play="' + h(e.id) + '" aria-label="' + h(_t('onbq.voice.play') + ': ' + e.label) + '">' + SV(IC.play, 18) + '</button><button type="button" role="radio" aria-checked="' + on + '" class="pick" data-pick="' + h(e.id) + '"><b>' + h(e.label) + '</b><span>' + h(gl(e.gender)) + (dsc(e) ? ' · ' + h(dsc(e)) : '') + '</span><i>' + _t('onbq.voice.chosen') + '</i></button></div>'; }).join('') + '</div>'
        : '<div class="note" style="margin-top:0">' + _t('onbq.voice.none') + '</div><div class="chipset" id="qsGen" role="radiogroup" style="margin-top:12px">' + ['f', 'm'].map(function (x) { return '<button type="button" role="radio" class="c' + (d.gender === x ? ' on' : '') + '" aria-checked="' + (d.gender === x) + '" data-g="' + x + '">' + gl(x) + '</button>'; }).join('') + '</div>')
      + '<span class="lbl">' + _t('onbq.greet.t') + '</span><p class="mute small" style="margin:0 0 8px">' + _t('onbq.greet.s') + '</p>'
      + '<div class="chipset" id="qsGrs">' + G.map(function (t, i) { return '<button type="button" class="c" data-gr="' + i + '">' + h(t.length > 52 ? t.slice(0, 50) + '…' : t) + '</button>'; }).join('') + '</div>'
      + '<div class="qs-greet"><textarea id="qsGreet" maxlength="400" placeholder="' + h(G[0] || '') + '">' + h(gr) + '</textarea><button type="button" class="btn" id="qsHear">' + SV(IC.sound, 16) + ' ' + _t('onbq.greet.hear') + '</button></div>',
      qsFoot(_t('onbq.next')));
    var body = g('onbBody');
    body.querySelectorAll('[data-play]').forEach(function (b) { b.onclick = function () { qsSay(G[0] || gr, b, b.dataset.play); }; });
    body.querySelectorAll('[data-pick]').forEach(function (b) { b.onclick = function () { var id = b.dataset.pick; QS.d.voice_id = id; body.querySelectorAll('.qs-voice').forEach(function (x) { var on = x.dataset.v === id; x.classList.toggle('on', on); x.querySelector('.pick').setAttribute('aria-checked', on ? 'true' : 'false'); });
      api('/onboarding/greeting', { method: 'POST', body: JSON.stringify({ id: S.onboarding.id, voice_id: id }) }).then(function (j) { if (j.qs) { QS.d = j.qs; QS.d.voice_id = id; } }).catch(function (e) { err(e.message); }); }; });
    body.querySelectorAll('[data-g]').forEach(function (b) { b.onclick = function () { body.querySelectorAll('[data-g]').forEach(function (x) { toggleChipPlain(x, x === b); }); QS.d.gender = b.dataset.g; api('/onboarding/answer', { method: 'POST', body: JSON.stringify({ id: S.onboarding.id, step: 'phone_persona', value: b.dataset.g }) }).catch(function () {}); }; });
    body.querySelectorAll('[data-gr]').forEach(function (b) { b.onclick = function () { g('qsGreet').value = G[+b.dataset.gr]; body.querySelectorAll('[data-gr]').forEach(function (x) { toggleChipPlain(x, x === b); }); }; });
    g('qsHear').onclick = function () { var t = g('qsGreet').value.trim() || g('qsGreet').placeholder; if (t) qsSay(t, g('qsHear')); };
    qsBindBack('voice');
    g('qsNext').onclick = function () {
      if (BUSY) return; qsBusy(true); var t = g('qsGreet').value.trim();
      api('/onboarding/greeting', { method: 'POST', body: JSON.stringify({ id: S.onboarding.id, text: t, voice_id: QS.d.voice_id || '' }) }).then(function (j) { qsBusy(false); QS.d = j.qs || QS.d; QS.chat = []; QS.step = qsNext('voice'); DIR = 'anim'; renderQs(); })
        .catch(function (e) { qsBusy(false); err(e.message || _t('onbj.saveErr')); });
    };
  }
  /* ---- 4 · Im Browser mit dem eigenen Agenten sprechen (POST /agent, vor der Zahlung über das Testguthaben) ---- */
  function qsTry() {
    var d = QS.d, SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!QS.chat.length && d.phone && d.greeting) QS.chat.push({ role: 'assistant', content: d.greeting });
    qsFrame('try', _t('onbq.try.t'), _t('onbq.try.s'),
      '<div class="qs-chat"><div class="ms" id="qsMs" aria-live="polite">' + (QS.chat.length ? QS.chat.map(qsMsgHtml).join('') : '<div class="m sys">' + _t('onbq.try.empty') + '</div>') + '</div>'
      + '<div class="chipset qs-chips" id="qsChips">' + [1, 2, 3, 4].map(function (i) { return '<button type="button" class="c" data-q="' + i + '">' + _t('onbq.try.q' + i) + '</button>'; }).join('') + '</div>'
      + '<form class="in" id="qsForm"><input type="text" id="qsIn" autocomplete="off" maxlength="600" placeholder="' + h(_t('onbq.try.ph')) + '">' + (SR ? '<button type="button" class="ib" id="qsMic" aria-label="' + h(_t('onbq.try.mic')) + '">' + SV(IC.mic, 18) + '</button>' : '') + '<button type="submit" class="ib p" id="qsSend" aria-label="' + h(_t('onbq.try.send')) + '">' + SV(IC.send, 18) + '</button></form>'
      + '<label class="qs-tgl"><input type="checkbox" id="qsSpk"' + (QS.speak && d.phone ? ' checked' : '') + '> ' + _t('onbq.try.speak') + '</label></div>',
      qsFoot(_t('onbq.try.ok')));
    var ms = g('qsMs'); ms.scrollTop = ms.scrollHeight;
    var send = function (text) {
      text = (text || g('qsIn').value).trim(); if (!text || QS.busy) return; g('qsIn').value = '';
      if (!QS.chat.length) ms.innerHTML = ''; QS.chat.push({ role: 'user', content: text }); ms.insertAdjacentHTML('beforeend', qsMsgHtml(QS.chat[QS.chat.length - 1]) + '<div class="m a wait" id="qsWait">…</div>'); ms.scrollTop = ms.scrollHeight; QS.busy = true; g('qsSend').disabled = true;
      api('/agent', { method: 'POST', body: JSON.stringify({ plan: S.onboarding.base, messages: QS.chat.slice(-16), lang: d.lang, channel: d.phone ? 'phone' : 'chat' }) }).then(function (j) {
        var a = String(j.result || '').replace(/\[(WEITERLEITEN|ENDE)\]/g, '').trim(); QS.chat.push({ role: 'assistant', content: a }); var w = g('qsWait'); if (w) w.outerHTML = qsMsgHtml(QS.chat[QS.chat.length - 1]); ms.scrollTop = ms.scrollHeight;
        try { if (typeof refreshMe === 'function') refreshMe(); } catch (e) { /* egal */ } var sp = g('qsSpk'); if (sp && sp.checked) qsSay(a, null);
      }).catch(function (e) { var w = g('qsWait'); if (w) w.outerHTML = '<div class="m sys err">' + h(e.status === 402 ? _t('onbq.try.noCredit') : e.message) + (e.status === 402 && d.checkout ? ' <a href="' + h(d.checkout) + '" target="_blank" rel="noopener">' + _t('onbq.plan.go') + ' →</a>' : '') + '</div>'; qsEv('try', 'error'); })
        .then(function () { QS.busy = false; var sb = g('qsSend'); if (sb) sb.disabled = false; var inp = g('qsIn'); if (inp && !(window.matchMedia && matchMedia('(pointer:coarse)').matches)) inp.focus(); });
    };
    g('qsForm').onsubmit = function (e) { e.preventDefault(); send(); };
    g('qsChips').querySelectorAll('[data-q]').forEach(function (b) { b.onclick = function () { send(b.textContent); }; });
    ms.addEventListener('click', function (e) { var b = e.target.closest('[data-say]'); if (b && QS.chat[+b.dataset.say]) qsSay(QS.chat[+b.dataset.say].content, b); });
    var spk = g('qsSpk'); if (spk) spk.onchange = function () { QS.speak = spk.checked; if (!spk.checked) qsStopAudio(); };
    var mic = g('qsMic'); if (mic) mic.onclick = function () { try { qsStopAudio(); var r = new SR(); r.lang = LANGTAG[d.lang] || 'de-DE'; r.interimResults = false; mic.classList.add('rec'); r.onresult = function (ev) { mic.classList.remove('rec'); send(ev.results[0][0].transcript); }; r.onerror = r.onend = function () { mic.classList.remove('rec'); }; r.start(); } catch (e) { mic.classList.remove('rec'); } };
    qsBindBack('try');
    g('qsNext').onclick = function () { qsEv('try', 'done'); qsGo(qsNext('try')); };
  }
  function qsMsgHtml(m) { var i = QS.chat.indexOf(m); return '<div class="m ' + (m.role === 'user' ? 'u' : 'a') + '">' + h(m.content) + (m.role === 'assistant' ? '<button type="button" class="say" data-say="' + i + '" aria-label="' + h(_t('onbq.greet.hear')) + '">' + SV(IC.sound, 14) + '</button>' : '') + '</div>'; }
  /* ---- 5 · Paket (Testphase): ein Bildschirm, Kasse mit Konto-E-Mail, danach geht es automatisch weiter ---- */
  function qsPlan() {
    var d = QS.d, o = S.onboarding, P = null; try { P = (PLANS || []).filter(function (p) { return p.id === o.plan || p.id === o.base; })[0] || null; } catch (e) { /* kein PLANS */ }
    var chTxt = ((P && P.channels) || []).map(function (c) { return I18N.has('onbq.ch.' + c) ? _t('onbq.ch.' + c) : c; }).join(' · ');
    var tiers = P && P.tiers && P.tiers.length ? P.tiers : null, price = P && P.eur_month ? I18N.eur(P.eur_month, P.eur_month % 1 ? 2 : 0) : '';
    var ck = function (pid) { var u = d.checkout || ('/chat/tokens-checkout.php?plan=' + encodeURIComponent(o.plan)); return pid ? u.replace(/plan=[^&]*/, 'plan=' + encodeURIComponent(pid)) : u; };
    qsFrame('plan', _t('onbq.plan.t'), _t('onbq.plan.s'),
      '<div class="qs-plan"><div class="ic">' + SV(IC.spark, 22) + '</div><div class="t"><b>' + h(o.plan_name || o.base_name) + '</b>' + (chTxt ? '<span>' + h(chTxt) + '</span>' : '') + '</div>' + (price && !tiers ? '<div class="pr"><b>' + h(price) + '</b><span>' + _t('onbq.plan.month') + '</span></div>' : '') + '</div>'
      + (tiers ? '<span class="lbl">' + _t('onbq.plan.tiers') + '</span><div class="qs-tiers">' + tiers.map(function (t) { return '<a class="tier' + (t.key === 'm' ? ' best' : '') + '" href="' + h(ck(t.id)) + '" target="_blank" rel="noopener" data-ck="1"><b>' + h(_t('tier.' + t.key)) + '</b><span>' + h(I18N.eur(t.eur_month, 0)) + ' ' + _t('onbq.plan.month') + '</span><small>' + h(_t('tier.time', { h: I18N.num(t.time.hours, { maximumFractionDigits: 1 }), c: I18N.num(t.time.calls) })) + '</small></a>'; }).join('') + '</div>' : '')
      + '<p class="mute small" style="margin:14px 0 0">' + _t('onbq.plan.after') + '</p>',
      qsFoot(null, '<button class="skip" type="button" id="qsLater">' + _t('onbq.plan.later') + '</button>' + (tiers ? '' : '<a class="btn p" id="qsPay" href="' + h(ck()) + '" target="_blank" rel="noopener" data-ck="1">' + _t('onbq.plan.go') + ' →</a>')));
    g('onbCard').querySelectorAll('[data-ck]').forEach(function (a) { a.addEventListener('click', function () { qsEv('plan', 'done'); qsStartPoll(); }); });
    qsBindBack('plan');
    g('qsLater').onclick = function () { qsGo(qsNext('plan'), { skip: true }); };
  }
  /* ---- 6 · Nummer: Land + Wunschvorwahl, danach Umleitungscodes als tel:-Links (vor der Zahlung vorgemerkt) ---- */
  function qsNumber() {
    var d = QS.d, pend = d.number_pending && d.number_pending.cc ? d.number_pending : null;
    if (d.number) { qsFrame('number', _t('onbq.num.t'), _t('onbq.num.have'), qsNumberHtml(d), qsFoot(_t('onbq.next'))); qsBindBack('number'); g('qsNext').onclick = function () { qsGo('live'); }; return; }
    var ps = stepById('phone_public'), ph = ps ? String(val(ps) || '') : '', area = (ph.replace(/[^\d+]/g, '').replace(/^\+\d{2}/, '0').match(/^0(\d{2,3})/) || [])[1] || '';   // Wunschvorwahl aus der Betriebsnummer (2–3 Ziffern genügen für die Suche)
    var cn = function (cc) { try { return typeof countryName === 'function' ? countryName(cc) : cc; } catch (e) { return cc; } };
    qsFrame('number', _t('onbq.num.t'), _t('onbq.num.s'),
      (pend ? '<div class="note" style="margin:0 0 14px">' + (d.active ? _t('onbq.num.soon') : _t('onbq.num.pending')) + '</div>' : '')
      + '<div class="fields"><div class="f"><label for="qsCc">' + _t('onbq.num.country') + '</label><select id="qsCc">' + NUM_CC.map(function (cc) { return '<option value="' + cc + '"' + (cc === (pend ? pend.cc : d.country) ? ' selected' : '') + '>' + h(cn(cc)) + '</option>'; }).join('') + '</select></div>'
      + '<div class="f"><label for="qsArea">' + _t('onbq.num.area') + '</label><input type="text" id="qsArea" inputmode="numeric" maxlength="6" value="' + h(pend ? (pend.area || '') : area) + '" placeholder="' + h(_t('onbq.num.areaPh')) + '"></div></div>',
      qsFoot(pend ? _t('onbq.next') : _t('onbq.num.go'), '<button class="skip" type="button" id="qsNoNum">' + _t('onbq.num.skip') + '</button>'));
    qsBindBack('number');
    g('qsNoNum').onclick = function () { qsGo('live', { skip: true }); };
    g('qsNext').onclick = function () {
      if (BUSY) return; qsBusy(true);
      api('/onboarding/number', { method: 'POST', body: JSON.stringify({ id: S.onboarding.id, area: g('qsArea').value.trim(), cc: g('qsCc').value }) }).then(function (j) { qsBusy(false); QS.d = j.qs || QS.d; if (QS.d.number) { DIR = 'anim'; renderQs(); } else qsGo('live', { server: false }); })
        .catch(function (e) { qsBusy(false); err(e.message || _t('onbj.saveErr')); });
    };
  }
  function qsNumberHtml(d) {
    var n = d.number;
    return '<div class="qs-num"><b>' + h(n.display) + '</b><a href="tel:' + h(n.e164) + '" class="btn s">' + SV(IC.phone, 14) + ' ' + _t('onbq.num.call') + '</a></div>'
      + (n.codes && n.codes.length ? '<p class="sub" style="margin-top:14px">' + _t('onbq.num.fwd') + '</p><div class="fwd">' + n.codes.map(function (x) { return '<a href="' + h(x.tel) + '"' + (x.key === 'off' ? ' class="off"' : '') + '><code>' + h(x.code) + '</code><span>' + X(x.label) + '</span></a>'; }).join('') + '</div>' : '');
  }
  /* ---- 7 · Live: Testanruf abwarten (Abfrage alle 6 s), „Dein Agent ist live“, letzte Vorgänge, Schnellaktionen ---- */
  function qsLive() {
    var d = QS.d, o = S.onboarding, L = S.links || {}, C = S.checklist || [], live = !!d.live, hasNum = !!d.number;
    var title = live ? '✅ ' + _t('onbq.live.t') : (hasNum ? _t('onbq.live.test.t') : _t('onbq.live.ready.t'));
    var sub = live ? _t('onbq.live.s') : (hasNum ? h(_t('onbq.live.test.s', { n: d.number.display })) : (d.phone && !d.active ? _t('onbq.live.ready.s') : _t('onbq.live.ready.s2')));
    var acts = [];
    if (L.widget_snippet) acts.push('<button type="button" class="act" data-a="copy">' + SV(IC.code) + '<span>' + _t('onbj.a.copy') + '</span></button>');
    acts.push('<button type="button" class="act" data-a="test">' + SV(IC.chat) + '<span>' + _t('onbj.a.test') + '</span></button>');
    if (d.phone) acts.push('<button type="button" class="act" data-a="phone">' + SV(IC.phone) + '<span>' + _t('onbj.a.phone') + '</span></button>');
    if (C.some(function (c) { return c.key === 'whatsapp'; })) acts.push('<button type="button" class="act" data-a="whatsapp">' + SV(IC.wa) + '<span>' + _t('onbj.a.wa') + '</span></button>');
    acts.push('<button type="button" class="act" data-a="dash">' + SV(IC.bolt) + '<span>' + _t('onbq.live.dash') + '</span></button>', '<a class="act" href="/chat/app.php" data-a="app">' + SV(IC.app) + '<span>' + _t('onbj.a.app') + '</span></a>');
    if (o.trial && d.checkout) acts.unshift('<a class="act p" href="' + h(d.checkout) + '" target="_blank" rel="noopener" data-a="live">' + SV(IC.spark) + '<span>' + _t('onbj.a.live') + '</span></a>');
    qsFrame('live', title, sub,
      (hasNum && !live ? qsNumberHtml(d) + '<div class="impst on run" style="margin-top:14px"><b>' + _t('onbq.live.wait') + '</b><div class="bar"><i></i></div></div>' : '')
      + (live && hasNum ? '<div class="qs-num"><b>' + h(d.number.display) + '</b><span class="pill2 ok">' + h(_t('onbq.live.calls', { n: I18N.num(d.calls) })) + '</span></div>' : '')
      + '<div class="acts" style="padding:' + (hasNum ? 16 : 0) + 'px 0 0">' + acts.join('') + '</div>'
      + '<span class="lbl">' + _t('onbq.live.recent') + '</span>' + (d.recent && d.recent.length ? '<ul class="qs-recent">' + d.recent.map(qsRecentHtml).join('') + '</ul>' : '<p class="mute small" style="margin:0">' + _t('onbq.live.none') + '</p>'),
      '<button class="btn" type="button" id="onbEdit">' + SV(IC.edit, 16) + ' ' + _t('onbq.live.all') + '</button><span class="sp"></span><button class="btn" type="button" id="onbMail">' + SV(IC.mail, 16) + ' ' + _t('onbj.send') + '</button>');
    g('onbCard').insertAdjacentHTML('beforeend', '<div id="onbMsg" style="padding:0 26px 18px"></div>');
    g('onbCard').querySelectorAll('[data-a]').forEach(function (b) { b.addEventListener('click', function (e) { var a = b.dataset.a;
      if (a === 'copy') { e.preventDefault(); copy(L.widget_snippet, b); }
      if (a === 'phone') { e.preventDefault(); go('phone'); }
      if (a === 'whatsapp') { e.preventDefault(); go('whatsapp'); }
      if (a === 'dash') { e.preventDefault(); go('dash'); }
      if (a === 'test') { e.preventDefault(); if (typeof openAgent === 'function') openAgent(L.test_plan || o.base); else location.href = L.studio_test; }
    }); });
    g('onbEdit').onclick = function () { qsStopPoll(); qsStopAudio(); SHOWALL = true; MODE = 'step'; CUR = (queue()[0] || {}).id; DIR = 'anim'; render(); };
    g('onbMail').onclick = function () { var b = g('onbMail'); b.disabled = true; api('/onboarding/resend-guide', { method: 'POST', body: JSON.stringify({ id: o.id }) }).then(function (j) { g('onbMsg').innerHTML = '<div class="msg ok">' + h((j.message && _tx(j.message)) || _t('onbj.sent')) + '</div>'; }).catch(function (e) { g('onbMsg').innerHTML = '<div class="msg err">' + h(e.message) + '</div>'; }).then(function () { b.disabled = false; }); };
    if (hasNum && !live) qsStartPoll(); else qsStopPoll();
  }
  /** Letzte Vorgänge (dash-lib, sprachneutral) – Titel mit den Wörterbuchschlüsseln der Übersicht */
  function qsRecentHtml(it) {
    var t = '', s = '', who = [it.name, it.phone_display].filter(Boolean).join(' · '), J = function (a) { return a.filter(Boolean).join(' · '); };
    switch (it.kind) {
      case 'call': t = _t(it.direction === 'out' ? 'dsj.ttl.callOut' : 'dsj.ttl.call'); s = who; break;
      case 'whatsapp': t = _t('dsj.ttl.whatsapp'); s = it.text ? '„' + it.text + '“' : who; break;
      case 'web': t = _t('dsj.kind.web'); s = it.text ? '„' + it.text + '“' : ''; break;
      case 'order': t = _t('dsj.ttl.order', { c: it.code }); s = J([it.items, who]); break;
      case 'appt': t = _t('dsj.ttl.appt', { c: it.code }); s = J([it.service, who]); break;
      case 'ride': t = _t('dsj.ttl.ride', { c: it.code }); s = J([it.pickup, who]); break;
      case 'hotel': t = _t('dsj.ttl.hotel', { c: it.code }); s = who; break;
      case 'callback': t = _t('dsj.ttl.callback', { c: it.code }); s = J([it.topic, who]); break;
      default: t = it.kind; s = who;
    }
    var when = ''; try { when = I18N.dt(new Date(it.at), { dateStyle: 'short', timeStyle: 'short' }); } catch (e) { /* egal */ }
    return '<li><span class="k ' + h(it.kind) + '">' + SV(it.kind === 'call' ? IC.phone : (it.kind === 'whatsapp' ? IC.wa : IC.chat), 16) + '</span><span class="t"><b>' + h(t) + '</b>' + (s ? '<span>' + h(s) + '</span>' : '') + '</span><time>' + h(when) + '</time></li>';
  }

  /* ---------------------------------------------------------------- Panel */
  function start() {
    if (!key()) return;
    var w = ''; try { w = new URLSearchParams(location.search).get('welcome') || ''; } catch (e) {}
    var pick = window.ONB_PLAN || ''; window.ONB_PLAN = '';   // aus dem Profil-Assistenten: empfohlener Agent
    var q = pick ? { plan: pick } : (firstLoad && w ? { plan: w } : (S && S.onboarding ? { id: S.onboarding.id } : null)); firstLoad = false;
    if (S && MODE === 'step' && !pick) return;   // mitten in einer Frage nicht neu laden
    if (S && MODE === 'qs' && QS && QS.d && !pick) { qsRefresh(); return; }   // Schnellstart: nur den Stand nachladen (z. B. zurück von der Kasse), Schritt und Gespräch behalten
    load(q);
  }
  window.STUDIO_PANELS = window.STUDIO_PANELS || {};
  window.STUDIO_PANELS.einrichtung = { init: function () { if (!bound) bound = true; start(); }, show: function () { start(); } };
})();
