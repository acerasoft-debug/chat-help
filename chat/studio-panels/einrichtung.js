/* ChatHelp Studio — Panel „Einrichtung“ (onboarding-lib): eine Frage pro Bildschirm, Fortschrittsring, Zurück/Weiter/Überspringen,
   Speichern je Antwort (POST /v1/onboarding/answer), Enter = weiter, Auswahl-Chips, Zeitbereiche, wiederholbare Zeilen, Website-Import über das
   Training (train/import-url → train/job → train/apply), Abschluss „Alles bereit“ mit Checkliste und Schnellaktionen.
   Nutzt die Studio-Globals api, esc, go, openAgent, KEY. */
(function () {
  'use strict';
  var PANEL = 'panel-einrichtung';
  var S = null, MODE = 'load', CUR = null, SHOWALL = false, DRAFT = {}, BUSY = false, DIR = 'anim', APPLIED = null, IMPORT = null, firstLoad = true, bound = false;
  var g = function (id) { return document.getElementById(id); };
  var h = function (v) { return esc(v == null ? '' : String(v)); };
  var WD = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'], WD_ORDER = [1, 2, 3, 4, 5, 6, 0];
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
    edit: '<path d="M4.5 19.5l1-4L16 5a2 2 0 0 1 3 3L8.5 18.5z"/>'
  };
  var key = function () { try { return KEY; } catch (e) { return ''; } };
  var visible = function () { var el = g(PANEL); return !!el && !el.closest('.hidden'); };

  /* ---------------------------------------------------------------- Daten */
  function load(q) {
    var qs = q && q.id ? '?id=' + encodeURIComponent(q.id) : (q && q.plan ? '?plan=' + encodeURIComponent(q.plan) : '');
    return api('/onboarding/state' + qs).then(function (j) {
      var changed = !S || !S.onboarding || S.onboarding.id !== j.onboarding.id;
      S = j; if (changed) { DRAFT = {}; CUR = null; APPLIED = null; SHOWALL = false; MODE = j.onboarding.status === 'done' ? 'done' : 'intro'; }
      render();
    }).catch(function (e) {
      S = null; MODE = 'empty';
      var list = (e.data && e.data.list) || [];
      g('onbCard').innerHTML = '<div class="onb-empty"><div class="hero" style="padding:8px 0 0;text-align:center"><div class="ic" style="margin:0 auto">' + SV(IC.spark, 28) + '</div><h2 style="font-size:28px">Noch nichts einzurichten</h2><p style="margin:12px auto 0">' + h(e.message || 'Keine Einrichtung gefunden.') + '</p></div>'
        + '<div class="row" style="justify-content:center;margin-top:20px"><button class="btn p" type="button" onclick="go(\'agents\')">Agenten ansehen</button></div></div>';
      renderSwitch(list);
    });
  }
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
    return '<div class="onb-ring" role="img" aria-label="' + p + ' Prozent erledigt"><svg width="58" height="58" viewBox="0 0 58 58"><circle class="bg" cx="29" cy="29" r="24"/><circle class="fg" cx="29" cy="29" r="24" stroke-dasharray="' + C.toFixed(1) + '" stroke-dashoffset="' + off.toFixed(1) + '"/></svg><b>' + p + '%</b></div>'; }
  function renderSwitch(list) {
    var el = g('onbSwitch'); if (!el) return; list = list || (S && S.list) || [];
    if (list.length < 2) { el.classList.add('hidden'); el.innerHTML = ''; return; }
    el.classList.remove('hidden');
    el.innerHTML = list.map(function (o) { return '<button type="button" role="tab" data-id="' + o.id + '" class="' + (S && S.onboarding && S.onboarding.id === o.id ? 'on' : '') + '" aria-selected="' + (S && S.onboarding && S.onboarding.id === o.id) + '"><span class="st' + (o.status === 'done' ? ' done' : '') + '"></span>' + h(o.base_name || o.plan_name) + '</button>'; }).join('');
    el.querySelectorAll('button').forEach(function (b) { b.onclick = function () { load({ id: +b.dataset.id }); }; });
  }
  function render() {
    renderSwitch(); var kb = g('onbKbd'); if (kb) kb.classList.toggle('hidden', MODE !== 'step');
    if (MODE === 'intro') return renderIntro();
    if (MODE === 'done') return renderDone();
    if (MODE === 'finishing') return renderFinishing();
    return renderStep();
  }
  function renderIntro() {
    var o = S.onboarding, q = queue(), known = knownCount(), who = o.private ? 'Assistent' : 'Agent';
    var groups = []; q.forEach(function (s) { if (groups.indexOf(s.group) < 0) groups.push(s.group); });
    var mins = Math.max(2, Math.round(q.length * 0.4));
    g('onbCard').innerHTML = '<div class="hero"><div class="ic">' + SV(IC.spark, 28) + '</div>'
      + '<h2>' + (o.status === 'done' ? 'Einrichtung bearbeiten' : 'Willkommen! In ' + mins + ' Minuten ist dein ' + who + ' startklar.') + '</h2>'
      + '<p>' + h(o.base_name) + ' – wir stellen dir ' + q.length + ' kurze Fragen und übernehmen alles direkt in deine Einstellungen. Du kannst jederzeit unterbrechen; jede Antwort ist sofort gespeichert.</p>'
      + '<ul class="ilist">' + groups.slice(0, 5).map(function (gr, i) { return '<li><i>' + (i + 1) + '</i><span><b style="color:var(--ink)">' + h(gr) + '</b> · ' + h(introItems(q, gr)) + '</span></li>'; }).join('') + '</ul>'
      + (known ? '<div class="note">✓ ' + known + (known === 1 ? ' Angabe haben' : ' Angaben haben') + ' wir schon aus deinen Einstellungen übernommen – die fragen wir nicht noch einmal.</div>' : '')
      + '</div><div class="onb-foot"><button class="skip" type="button" id="onbAll">' + (SHOWALL ? 'Nur offene Fragen' : 'Alle Fragen anzeigen') + '</button><span class="sp"></span><button class="btn p" type="button" id="onbGo">' + (progress().done ? 'Weitermachen' : 'Los geht’s') + ' →</button></div>';
    g('onbGo').onclick = function () { var qq = queue(); var first = qq.filter(function (s) { return !s.answered; })[0] || qq[0]; if (!first) return finish(); CUR = first.id; MODE = 'step'; DIR = 'anim'; render(); };
    g('onbAll').onclick = function () { SHOWALL = !SHOWALL; render(); };
    if (!(window.matchMedia && matchMedia('(pointer:coarse)').matches)) setTimeout(function () { var b = g('onbGo'); if (b && visible()) b.focus({ preventScroll: true }); }, 30);
  }
  function introItems(q, gr) {
    var its = q.filter(function (s) { return s.group === gr; });
    if (its.length === 1 && its[0].options && shortTitle(its[0]) === gr) return its[0].options.map(function (o) { return o.l; }).join(', ');
    return its.map(function (s) { return shortTitle(s); }).slice(0, 3).join(', ');
  }
  function shortTitle(s) { return ({ company: 'Name', website: 'Website', address: 'Adresse', phone_public: 'Telefon', hours: 'Zeiten', channels: 'Kanäle', phone_mode: 'Nummer', twilio: 'Twilio', managed_phone: 'Wunschnummer', phone_persona: 'Stimme', phone_greeting: 'Begrüßung',
    wa_provider: 'Anbieter', wa_meta: 'Meta-Daten', wa_twilio: 'Twilio-Nummer', services: 'Leistungen', appt_buffer: 'Puffer', menu: 'Karte', order_modes: 'Lieferung/Abholung', zones: 'Liefergebiete', order_fees: 'Mindestbestellwert', payments: 'Zahlarten',
    tariff: 'Tarif', ride_area: 'Gebiet', stands: 'Taxistände', drivers: 'Fahrer', ride_payments: 'Zahlarten', rooms: 'Zimmer', hotel_times: 'Check-in', call_window: 'Rückrufzeiten', cb_mode: 'Wer ruft an', focus: 'Angebot',
    langs: 'Sprachen', anrede: 'Du/Sie', tone: 'Ton', never: 'Tabus', faq: 'FAQ', p_name: 'Name', p_focus: 'Anliegen', p_context: 'Über dich' })[s.id] || s.title; }

  /* ---------------------------------------------------------------- Frage */
  function renderStep() {
    var q = queue(), st = stepById(CUR); if (!st || q.indexOf(st) < 0) { st = q.filter(function (s) { return !s.answered; })[0] || q[0]; if (!st) return finish(); CUR = st.id; }
    var i = q.indexOf(st), p = progress(), last = i === q.length - 1;
    g('onbCard').innerHTML = '<div class="onb-head">' + ring(p.pct) + '<div class="onb-meta"><div class="k">' + h(st.group) + '</div><div class="n">Frage ' + (i + 1) + ' von ' + q.length + ' · ' + h(S.onboarding.base_name) + '</div></div><div class="onb-saved" id="onbSaved" aria-live="polite">✓ gespeichert</div></div>'
      + '<div class="onb-body ' + DIR + '" id="onbBody" data-step="' + h(st.id) + '"><h2 class="q" id="onbQ">' + h(st.title) + '</h2>' + (st.sub ? '<p class="sub">' + h(st.sub) + '</p>' : '') + '<div class="onb-in" role="group" aria-labelledby="onbQ">' + input(st) + '</div></div>'
      + '<div class="onb-err" id="onbErr" role="alert"></div>'
      + '<div class="onb-foot"><button class="btn" type="button" id="onbBack" aria-label="Zurück">←<span class="hide-s"> Zurück</span></button><span class="sp"></span><button class="skip" type="button" id="onbSkip">Überspringen</button><button class="btn p" type="button" id="onbNext">' + (last ? 'Fertig ✓' : 'Weiter →') + '</button></div>';
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
    }).catch(function (e) { BUSY = false; if (nb) nb.disabled = false; err(e.message || 'Konnte nicht gespeichert werden.'); });
  }

  /* ---------------------------------------------------------------- Eingaben */
  function cents(v) { if (v == null || v === '') return ''; var n = Number(v); if (!isFinite(n)) return String(v); return (n / 100).toFixed(2).replace('.', ','); }
  function input(st) {
    var v = val(st), ph = st.ph ? ' placeholder="' + h(st.ph) + '"' : '';
    switch (st.type) {
      case 'text': return '<input class="big" type="' + (st.input === 'tel' ? 'tel' : 'text') + '" id="onbV" value="' + h(v || '') + '"' + ph + ' maxlength="' + (st.max || 300) + '"' + (st.auto_complete ? ' autocomplete="' + h(st.auto_complete) + '"' : '') + (st.input === 'tel' ? ' inputmode="tel"' : '') + '>';
      case 'textarea': return '<textarea id="onbV"' + ph + ' maxlength="' + (st.max || 2000) + '">' + h(v || '') + '</textarea><div class="mute small" style="margin-top:6px">Strg/⌘ + Enter = weiter</div>';
      case 'url_import': return '<div class="imp"><input class="big" type="url" id="onbV" inputmode="url" autocomplete="url" value="' + h(v || '') + '"' + ph + '><button class="btn" type="button" id="onbImp">Einlesen</button></div><div class="impst" id="onbImpSt" aria-live="polite"></div>';
      case 'choice': return '<div class="opts' + (st.options.length > 3 && !st.options.some(function (o) { return o.d; }) ? ' two' : '') + '" role="radiogroup">' + st.options.map(function (o) { return '<button type="button" role="radio" class="opt' + (String(v) === o.v ? ' on' : '') + '" aria-checked="' + (String(v) === o.v) + '" data-v="' + h(o.v) + '"><span class="bx"></span><span><b>' + h(o.l) + '</b>' + (o.d ? '<span class="d">' + h(o.d) + '</span>' : '') + '</span></button>'; }).join('') + '</div>';
      case 'multi': return multiInput(st, Array.isArray(v) ? v : []);
      case 'hours': return hoursInput(st, v || {});
      case 'rows': return rowsInput(st, Array.isArray(v) ? v : []);
      case 'fields': return '<div class="fields">' + st.fields.map(function (f) { return '<div class="f' + (f.t === 'text' && (f.max || 0) > 100 ? ' full' : '') + '"><label for="onbF_' + f.k + '">' + h(f.l) + (f.req ? '' : '') + '</label>' + fieldInput(f, (v || {})[f.k], 'onbF_' + f.k) + '</div>'; }).join('') + '</div>';
      case 'langs': return langsInput(v || { default: 'de', allowed: [] });
    }
    return '';
  }
  function fieldInput(f, v, id) {
    var ph = f.ph ? ' placeholder="' + h(f.ph) + '"' : '', idA = id ? ' id="' + id + '"' : ' aria-label="' + h(f.l) + '"', d = ' data-k="' + h(f.k) + '" data-t="' + h(f.t) + '"';
    if (f.t === 'select') return '<select' + idA + d + '>' + (f.opts || []).map(function (o) { return '<option value="' + h(o.v) + '"' + (String(v) === o.v ? ' selected' : '') + '>' + h(o.l) + '</option>'; }).join('') + '</select>';
    if (f.t === 'time') return '<input type="time"' + idA + d + ' value="' + h(v || '') + '"' + ph + '>';
    if (f.t === 'textarea') return '<textarea' + idA + d + ph + ' maxlength="' + (f.max || 2000) + '">' + h(v || '') + '</textarea>';
    if (f.t === 'password') { var masked = typeof v === 'string' && v.indexOf('••••') === 0; return '<input type="password" autocomplete="off"' + idA + d + ' value=""' + (masked ? ' placeholder="hinterlegt (' + h(v) + ') – leer lassen = behalten"' : ph) + '>'; }
    var val0 = f.t === 'money' ? cents(v) : (v == null ? '' : String(v)), mode = f.t === 'money' || f.t === 'pct' ? ' inputmode="decimal"' : (f.t === 'int' ? ' inputmode="numeric"' : (f.t === 'tel' ? ' inputmode="tel"' : ''));
    var inp = '<input type="' + (f.t === 'tel' ? 'tel' : 'text') + '"' + idA + d + mode + ' value="' + h(val0) + '"' + ph + (f.max ? ' maxlength="' + f.max + '"' : '') + '>';
    return f.suffix ? '<div class="suf">' + inp + '<em>' + h(f.suffix) + '</em></div>' : inp;
  }
  function multiInput(st, v) {
    var rich = st.options.some(function (o) { return o.d; }), opts = st.options.map(function (o) { return o.v; });
    var own = v.filter(function (x) { return opts.indexOf(x) < 0; });
    if (rich) return '<div class="opts">' + st.options.map(function (o) { var on = v.indexOf(o.v) >= 0; return '<button type="button" role="checkbox" class="opt multi' + (on ? ' on' : '') + '" aria-checked="' + on + '" data-v="' + h(o.v) + '"><span class="bx"></span><span><b>' + h(o.l) + '</b>' + (o.d ? '<span class="d">' + h(o.d) + '</span>' : '') + '</span></button>'; }).join('') + '</div>';
    return '<div class="chipset" id="onbChips">' + st.options.map(function (o) { var on = v.indexOf(o.v) >= 0; return '<button type="button" role="checkbox" class="c' + (on ? ' on' : '') + '" aria-checked="' + on + '" data-v="' + h(o.v) + '">' + (on ? '✓ ' : '') + h(o.l) + '</button>'; }).join('')
      + own.map(function (x) { return '<button type="button" role="checkbox" class="c on own" aria-checked="true" data-v="' + h(x) + '">✓ ' + h(x) + '</button>'; }).join('') + '</div>'
      + (st.free ? '<div class="free"><input type="text" id="onbFree" maxlength="160" placeholder="' + h(st.free_ph || 'Eigener Eintrag') + '"><button class="btn" type="button" id="onbFreeAdd">Hinzufügen</button></div>' : '');
  }
  function hoursInput(st, v) {
    var presets = [['Mo–Fr 9–18', { 1: [['09:00', '18:00']], 2: [['09:00', '18:00']], 3: [['09:00', '18:00']], 4: [['09:00', '18:00']], 5: [['09:00', '18:00']] }],
      ['Mo–Sa 9–20', { 1: [['09:00', '20:00']], 2: [['09:00', '20:00']], 3: [['09:00', '20:00']], 4: [['09:00', '20:00']], 5: [['09:00', '20:00']], 6: [['09:00', '20:00']] }],
      ['Täglich 11–22', { 0: [['11:00', '22:00']], 1: [['11:00', '22:00']], 2: [['11:00', '22:00']], 3: [['11:00', '22:00']], 4: [['11:00', '22:00']], 5: [['11:00', '22:00']], 6: [['11:00', '22:00']] }]];
    if (!st.same_day) presets.push(['Rund um die Uhr', { 0: [['00:00', '24:00']], 1: [['00:00', '24:00']], 2: [['00:00', '24:00']], 3: [['00:00', '24:00']], 4: [['00:00', '24:00']], 5: [['00:00', '24:00']], 6: [['00:00', '24:00']] }]);
    hoursInput.P = presets;
    return '<div class="chipset" style="margin-bottom:12px">' + presets.map(function (p, i) { return '<button type="button" class="c" data-preset="' + i + '">' + h(p[0]) + '</button>'; }).join('') + '<button type="button" class="c add" data-copy="1">Montag auf Di–Fr übertragen</button></div>'
      + '<div class="hours" id="onbHours">' + WD_ORDER.map(function (d) { return hourRow(d, v[String(d)] || []); }).join('') + '</div>';
  }
  function hourRow(d, rows) {
    var open = rows.length > 0;
    return '<div class="hrow" data-d="' + d + '"><button type="button" class="hday' + (open ? ' on' : '') + '" aria-pressed="' + open + '" aria-label="' + WD[d] + ' ' + (open ? 'geöffnet' : 'geschlossen') + '">' + WD[d] + '</button><div class="hr">'
      + (open ? rows.map(function (r, i) { return '<div class="hiv"><input type="time" value="' + h(r[0]) + '" aria-label="' + WD[d] + ' von"><i>–</i><input type="time" value="' + h(r[1] === '24:00' ? '23:59' : r[1]) + '" data-end24="' + (r[1] === '24:00' ? 1 : 0) + '" aria-label="' + WD[d] + ' bis">' + (i > 0 ? '<button type="button" class="hx" data-rm="' + i + '" aria-label="Zeitraum entfernen">×</button>' : '<span></span>') + '</div>'; }).join('')
        + (rows.length < 2 ? '<button type="button" class="hadd">＋ zweiter Zeitraum (z. B. nach der Mittagspause)</button>' : '') : '<div class="hclosed">geschlossen</div>') + '</div></div>';
  }
  function hoursCollect() {
    var o = {}; document.querySelectorAll('#onbHours .hrow').forEach(function (r) { var d = r.dataset.d, rows = [];
      r.querySelectorAll('.hiv').forEach(function (iv) { var t = iv.querySelectorAll('input[type=time]'); var a = t[0].value, b = t[1].value; if (b === '23:59' && t[1].dataset.end24 === '1') b = '24:00'; if (a && b) rows.push([a, b]); });
      o[d] = rows; }); return o;
  }
  function rowsInput(st, v) {
    var list = v.length ? v : (st.min ? [{}] : []);
    return '<div class="rows" id="onbRows">' + list.map(function (r) { return rowHtml(st, r); }).join('') + '</div><button type="button" class="addrow" id="onbAdd">＋ ' + h(st.add || 'Hinzufügen') + '</button>'
      + (st.presets && st.presets.length ? '<div class="presets"><span class="lbl" style="margin-top:4px">Schnell hinzufügen</span><div class="chipset">' + st.presets.map(function (p, i) { return '<button type="button" class="c" data-pre="' + i + '">＋ ' + h(presetLabel(st, p)) + '</button>'; }).join('') + '</div></div>' : '');
  }
  function presetLabel(st, p) { var parts = [p.name]; if (p.duration_min) parts.push(p.duration_min + ' Min.'); if (p.capacity) parts.push(p.capacity + ' Pers.'); if (p.price_cents != null) parts.push(p.price_cents ? cents(p.price_cents) + ' €' : 'auf Anfrage'); return parts.join(' · '); }
  function rowHtml(st, r) {
    return '<div class="rw"><div class="g">' + st.cols.map(function (c) { var w = Math.min(5, c.w || 1); return '<div class="f w' + w + '"><label>' + h(c.l) + '</label>' + fieldInput(c, r[c.k] != null ? r[c.k] : '', '') + '</div>'; }).join('') + '</div><button type="button" class="del" aria-label="Zeile entfernen">×</button></div>';
  }
  function rowsCollect(st) {
    var out = []; document.querySelectorAll('#onbRows .rw').forEach(function (rw) { var o = {}, any = false; rw.querySelectorAll('[data-k]').forEach(function (x) { o[x.dataset.k] = x.value; if (x.value.trim() !== '') any = true; }); if (any) out.push(o); });
    return out;
  }
  function langsInput(v) {
    var L = (S && S.langs) || [], al = v.allowed && v.allowed.length ? v.allowed : L.map(function (x) { return x.v; });
    return '<span class="lbl" style="margin-top:0">Standardsprache</span><div class="chipset" id="onbLangDef" role="radiogroup">' + L.map(function (l) { var on = v.default === l.v; return '<button type="button" role="radio" class="c' + (on ? ' on' : '') + '" aria-checked="' + on + '" data-v="' + l.v + '">' + h(l.l) + '</button>'; }).join('') + '</div>'
      + '<span class="lbl">Kunden dürfen außerdem schreiben und sprechen in</span><div class="chipset" id="onbLangAl">' + L.map(function (l) { var on = al.indexOf(l.v) >= 0; return '<button type="button" role="checkbox" class="c' + (on ? ' on' : '') + '" aria-checked="' + on + '" data-v="' + l.v + '">' + (on ? '✓ ' : '') + h(l.l) + '</button>'; }).join('') + '</div>'
      + '<div class="row" style="margin-top:10px;gap:6px"><button type="button" class="btn s" data-all="1">Alle Sprachen</button><button type="button" class="btn s" data-all="0">Nur Standardsprache</button></div>';
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
    if (st.type === 'hours') {
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
    if (st.type === 'rows') {
      var rows = g('onbRows');
      var bindDel = function () { rows.querySelectorAll('.del').forEach(function (b) { b.onclick = function () { b.closest('.rw').remove(); }; }); };
      bindDel();
      g('onbAdd').onclick = function () { rows.insertAdjacentHTML('beforeend', rowHtml(st, {})); bindDel(); var f = rows.lastElementChild.querySelector('input,textarea'); if (f) f.focus(); };
      body.querySelectorAll('[data-pre]').forEach(function (b) { b.onclick = function () { var p = st.presets[+b.dataset.pre];
        var empty = Array.prototype.filter.call(rows.querySelectorAll('.rw'), function (rw) { return !Array.prototype.some.call(rw.querySelectorAll('[data-k]'), function (x) { return x.value.trim() !== ''; }); })[0];
        if (empty) empty.outerHTML = rowHtml(st, p); else rows.insertAdjacentHTML('beforeend', rowHtml(st, p)); bindDel(); b.disabled = true; b.style.opacity = '.45'; }; });
    }
    if (st.type === 'langs') {
      var def = g('onbLangDef'), al = g('onbLangAl');
      def.querySelectorAll('.c').forEach(function (b) { b.onclick = function () { def.querySelectorAll('.c').forEach(function (x) { toggleChip(x, x === b); }); var m = al.querySelector('[data-v="' + b.dataset.v + '"]'); if (m) toggleChip(m, true); }; });
      al.querySelectorAll('.c').forEach(function (b) { b.onclick = function () { var d = def.querySelector('.c.on'); if (d && d.dataset.v === b.dataset.v) return; toggleChip(b, !b.classList.contains('on')); }; });
      body.querySelectorAll('[data-all]').forEach(function (b) { b.onclick = function () { var d = def.querySelector('.c.on'); al.querySelectorAll('.c').forEach(function (x) { toggleChip(x, b.dataset.all === '1' || (d && x.dataset.v === d.dataset.v)); }); }; });
    }
    if (st.type === 'url_import') { g('onbImp').onclick = function () { var u = g('onbV').value.trim(); if (!u) { err('Bitte die Adresse deiner Website eingeben, z. B. www.dein-betrieb.de'); return; }
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
    IMPORT = { url: url, status: 'running', progress: 'Website wird gelesen …', plan: S.onboarding.base }; renderImport();
    trainPost('/train/import-url', { url: url }).then(onJob).catch(function (e) { IMPORT.status = 'error'; IMPORT.message = e.message || 'Import nicht möglich.'; renderImport(); });
  }
  function onJob(j) {
    if (!IMPORT) return; IMPORT.job = j.job || IMPORT.job; IMPORT.progress = j.progress || IMPORT.progress;
    if (j.status === 'running') { renderImport(); setTimeout(function () { if (!IMPORT || IMPORT.status !== 'running') return; trainPost('/train/job', { id: IMPORT.job }).then(onJob).catch(function () { setTimeout(function () { if (IMPORT && IMPORT.status === 'running') onJob({ status: 'running' }); }, 4000); }); }, 1500); return; }
    if (j.status === 'done' && j.result && j.result.preview) {
      IMPORT.progress = 'Wird in die Wissensbasis übernommen …'; renderImport();
      trainPost('/train/apply', { text: j.result.preview, mode: 'append', job: IMPORT.job, title: j.result.title || IMPORT.url }).then(function (r) { IMPORT.status = 'done'; IMPORT.chars = r.added_chars || 0; renderImport(); })
        .catch(function (e) { IMPORT.status = 'error'; IMPORT.message = e.message; renderImport(); });
      return;
    }
    IMPORT.status = 'error'; IMPORT.message = j.message || 'Die Website konnte nicht eingelesen werden.'; renderImport();
  }
  function renderImport() {
    var el = g('onbImpSt'); if (!el || !IMPORT) return;
    el.className = 'impst on ' + (IMPORT.status === 'running' ? 'run' : IMPORT.status === 'done' ? 'ok' : 'err');
    el.innerHTML = IMPORT.status === 'running' ? '<b>Wir lesen deine Website …</b> ' + h(IMPORT.progress || '') + '<div class="bar"><i></i></div><div class="small" style="margin-top:6px;opacity:.8">Du kannst schon weitermachen – das läuft im Hintergrund.</div>'
      : IMPORT.status === 'done' ? '✓ <b>Eingelesen:</b> ' + Number(IMPORT.chars || 0).toLocaleString('de-DE') + ' Zeichen Wissen von ' + h(IMPORT.url) + ' sind jetzt in der Wissensbasis.'
      : h(IMPORT.message || 'Import nicht möglich.') + ' <span class="small">Kein Problem – du kannst weitermachen und später unter Training erneut einlesen.</span>';
  }

  /* ---------------------------------------------------------------- Abschluss */
  function renderFinishing() {
    g('onbCard').innerHTML = '<div class="hero" style="text-align:center;padding:48px 26px"><div class="ic" style="margin:0 auto">' + SV(IC.spark, 28) + '</div><h2 style="font-size:28px">Wir richten alles ein …</h2><p style="margin:12px auto 0">Leistungen, Zeiten, Kanäle und Regeln werden in deine Module übernommen.</p><div class="bar" style="max-width:240px;margin:22px auto 0;background:var(--goldl)"><i style="background:var(--gold)"></i></div></div>';
  }
  function finish() {
    MODE = 'finishing'; render();
    api('/onboarding/finish', { method: 'POST', body: JSON.stringify({ id: S.onboarding.id }) }).then(function (j) { S = j; APPLIED = j.applied || []; MODE = 'done'; DIR = 'anim'; render(); try { if (typeof refreshMe === 'function') refreshMe(); } catch (e) {} })
      .catch(function (e) { MODE = 'step'; render(); err(e.message || 'Abschluss fehlgeschlagen – bitte erneut versuchen.'); });
  }
  function renderDone() {
    var o = S.onboarding, C = S.checklist || [], L = S.links || {}, nd = C.filter(function (c) { return c.status === 'done'; }).length, nt = C.filter(function (c) { return c.status !== 'info'; }).length, who = o.private ? 'Assistent' : 'Agent';
    var hasPhone = C.some(function (c) { return c.key === 'phone'; }), hasWa = C.some(function (c) { return c.key === 'whatsapp'; });
    var ok = (APPLIED || []).filter(function (a) { return a.ok; }), bad = (APPLIED || []).filter(function (a) { return !a.ok; });
    var acts = [], num = 0, LINKS = { appt: 'Buchungsseite öffnen', order: 'Küchen-Board öffnen', hotel: 'Rezeptions-Board öffnen', ride: 'Taxi-Board öffnen', callback: 'Rückruf-Formular öffnen' };
    if (L.widget_snippet && C.some(function (c) { return c.key === 'widget'; })) acts.push('<button type="button" class="act p" data-a="copy">' + SV(IC.code) + '<span>Widget-Code kopieren</span></button>');
    if (hasPhone) acts.push('<button type="button" class="act" data-a="phone">' + SV(IC.phone) + '<span>Telefonnummer</span></button>');
    if (hasWa) acts.push('<button type="button" class="act" data-a="whatsapp">' + SV(IC.wa) + '<span>WhatsApp verbinden</span></button>');
    acts.push('<a class="act" href="/chat/app.php" data-a="app">' + SV(IC.app) + '<span>App installieren</span></a>');
    acts.push('<button type="button" class="act" data-a="test">' + SV(IC.chat) + '<span>Test-Chat starten</span></button>');
    g('onbCard').innerHTML = '<div class="onb-head">' + ring(nt ? Math.round(100 * nd / nt) : 100) + '<div class="onb-meta"><div class="k">Einrichtung abgeschlossen</div><div class="n">' + nd + ' von ' + nt + ' Schritten erledigt · ' + h(o.base_name) + '</div></div></div>'
      + '<div class="hero ' + DIR + '" style="padding-top:18px"><h2>Alles bereit.</h2><p>Dein ' + who + ' kennt jetzt deinen Betrieb' + (ok.length ? ' – übernommen: ' + h(ok.map(function (a) { return a.label; }).join(', ')) : '') + '. Die persönliche Anleitung liegt in deinem Postfach.</p>'
      + (bad.length ? '<div class="msg err">' + h(bad.map(function (a) { return a.label + ': ' + a.detail; }).join(' · ')) + '</div>' : '') + '</div>'
      + '<div class="acts">' + acts.join('') + '</div>'
      + '<div class="checks">' + C.map(function (c) { if (c.status !== 'done') num++; return '<div class="ck ' + c.status + '"><span class="m">' + (c.status === 'done' ? '✓' : num) + '</span><div class="t"><b>' + h(c.label) + (c.status === 'done' ? '<span class="pill2">erledigt</span>' : c.status === 'todo' ? '<span class="pill2">offen</span>' : '') + '</b>'
        + (c.detail ? '<p>' + h(c.detail) + '</p>' : '') + (c.steps && c.steps.length && c.status !== 'done' ? '<ol>' + c.steps.map(function (s) { return '<li>' + h(s) + '</li>'; }).join('') + '</ol>' : '')
        + (c.code ? '<div class="snip">' + h(c.code) + '</div>' : '') + (c.url && LINKS[c.key] ? '<a class="cklink" href="' + h(c.url) + '" target="_blank" rel="noopener">' + LINKS[c.key] + ' →</a>' : '') + '</div></div>'; }).join('') + '</div>'
      + '<div class="onb-foot donefoot"><button class="btn" type="button" id="onbEdit">' + SV(IC.edit, 16) + ' Antworten ändern</button><span class="sp"></span><button class="btn" type="button" id="onbMail">' + SV(IC.mail, 16) + ' Anleitung senden</button></div><div id="onbMsg" style="padding:0 26px 18px"></div>';
    g('onbCard').querySelectorAll('[data-a]').forEach(function (b) { b.addEventListener('click', function (e) { var a = b.dataset.a;
      if (a === 'copy') { e.preventDefault(); copy(L.widget_snippet, b); }
      if (a === 'phone') { e.preventDefault(); go('phone'); }
      if (a === 'whatsapp') { e.preventDefault(); go('whatsapp'); }
      if (a === 'test') { e.preventDefault(); if (typeof openAgent === 'function') openAgent(L.test_plan || o.base); else location.href = L.studio_test; }
    }); });
    g('onbEdit').onclick = function () { SHOWALL = true; MODE = 'step'; CUR = (queue()[0] || {}).id; DIR = 'anim'; render(); };
    g('onbMail').onclick = function () { var b = g('onbMail'); b.disabled = true; api('/onboarding/resend-guide', { method: 'POST', body: JSON.stringify({ id: o.id }) }).then(function (j) { g('onbMsg').innerHTML = '<div class="msg ok">' + h(j.message || 'Gesendet.') + '</div>'; }).catch(function (e) { g('onbMsg').innerHTML = '<div class="msg err">' + h(e.message) + '</div>'; }).then(function () { b.disabled = false; }); };
  }
  function copy(t, b) {
    var done = function () { var s = b.querySelector('span'); var old = s.textContent; s.textContent = 'Kopiert ✓'; setTimeout(function () { s.textContent = old; }, 1800); };
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(t).then(done, function () { fallback(); }); else fallback();
    function fallback() { var ta = document.createElement('textarea'); ta.value = t; ta.style.position = 'fixed'; ta.style.opacity = '0'; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); done(); } catch (e) {} ta.remove(); }
  }

  /* ---------------------------------------------------------------- Panel */
  function start() {
    if (!key()) return;
    var w = ''; try { w = new URLSearchParams(location.search).get('welcome') || ''; } catch (e) {}
    var q = firstLoad && w ? { plan: w } : (S && S.onboarding ? { id: S.onboarding.id } : null); firstLoad = false;
    if (S && MODE === 'step') return;   // mitten in einer Frage nicht neu laden
    load(q);
  }
  window.STUDIO_PANELS = window.STUDIO_PANELS || {};
  window.STUDIO_PANELS.einrichtung = { init: function () { if (!bound) bound = true; start(); }, show: function () { start(); } };
})();
