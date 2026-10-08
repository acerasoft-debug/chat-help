/* MÜHÜR — language switching, nav, reveal, quote form */
(function () {
  "use strict";

  document.documentElement.classList.add("js");

  var DICT = window.MUHUR_I18N || {};
  var SUPPORTED = ["tr", "en", "de", "fr", "es", "it"];
  var current = "tr";
  var langHooks = []; /* re-labels JS-built UI after a language switch */

  function detectLang() {
    var saved = null;
    try { saved = localStorage.getItem("muhur-lang"); } catch (e) { /* storage may be blocked */ }
    if (saved && SUPPORTED.indexOf(saved) !== -1) return saved;
    /* crawlers index the Turkish source, not their own UI language */
    if (/bot|crawl|spider|slurp|lighthouse|inspectiontool/i.test(navigator.userAgent || "")) return "tr";
    var prefs = (navigator.languages && navigator.languages.length) ? navigator.languages : [navigator.language || "tr"];
    for (var i = 0; i < prefs.length; i++) {
      var code = String(prefs[i]).slice(0, 2).toLowerCase();
      if (SUPPORTED.indexOf(code) !== -1) return code;
    }
    return "tr";
  }

  function applyLang(lang) {
    var dict = DICT[lang];
    if (!dict) return;
    current = lang;

    document.documentElement.lang = lang;
    var titleKey = document.documentElement.getAttribute("data-title-key") || "meta.title";
    if (dict[titleKey]) document.title = dict[titleKey];

    document.querySelectorAll("[data-i18n]").forEach(function (el) {
      var key = el.getAttribute("data-i18n");
      if (dict[key] !== undefined) el.textContent = dict[key];
    });

    document.querySelectorAll("[data-i18n-html]").forEach(function (el) {
      var key = el.getAttribute("data-i18n-html");
      if (dict[key] !== undefined) el.innerHTML = dict[key];
    });

    /* "placeholder:form.docPh" style attribute bindings */
    document.querySelectorAll("[data-i18n-attr]").forEach(function (el) {
      var parts = el.getAttribute("data-i18n-attr").split(":");
      if (parts.length === 2 && dict[parts[1]] !== undefined) {
        el.setAttribute(parts[0], dict[parts[1]]);
      }
    });

    var activeName = "";
    document.querySelectorAll(".lang-switch button").forEach(function (btn) {
      var on = btn.getAttribute("data-lang") === lang;
      btn.classList.toggle("is-active", on);
      btn.setAttribute("aria-pressed", on ? "true" : "false");
      if (on) activeName = btn.getAttribute("title") || "";
    });
    if (langToggle) {
      var code = lang.toUpperCase();
      var now = langToggle.querySelector(".lang-now");
      if (now) now.textContent = code;
      langToggle.setAttribute("aria-label", (dict["a11y.langs"] || "Language") + ": " + code + (activeName ? ", " + activeName : ""));
    }

    try { localStorage.setItem("muhur-lang", lang); } catch (e) { /* ignore */ }

    langHooks.forEach(function (fn) { fn(); });
    fitHeader();
  }

  /* header: show the full nav only while it fits on one row; otherwise
     collapse it behind the menu button (labels differ per language) */
  function fitHeader() {
    var h = document.querySelector(".site-header");
    var inner = document.querySelector(".header-inner");
    if (!h || !inner) return;
    h.classList.remove("is-compact");
    var over = inner.scrollWidth > inner.clientWidth + 1;
    if (!over && nav) {
      /* overflow into the side padding doesn't scroll, but still breaks the content edge */
      var edge = inner.getBoundingClientRect().right - (parseFloat(getComputedStyle(inner).paddingRight) || 0);
      over = nav.getBoundingClientRect().right > edge + 1;
    }
    h.classList.toggle("is-compact", over || window.innerWidth <= 960);
    if (h.classList.contains("is-compact")) closeLangMenu(false);
    if (!h.classList.contains("is-compact") && nav && toggle) {
      nav.classList.remove("is-open");
      toggle.setAttribute("aria-expanded", "false");
    }
  }

  var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* desktop language menu: a disclosure button over the six-language group */
  var langToggle = document.getElementById("langToggle");
  var langMenu = langToggle ? langToggle.parentNode : null;

  function langMenuOpen() { return !!langMenu && langMenu.classList.contains("is-open"); }

  function closeLangMenu(returnFocus) {
    if (!langMenuOpen()) return;
    langMenu.classList.remove("is-open");
    langToggle.setAttribute("aria-expanded", "false");
    if (returnFocus) langToggle.focus();
  }

  if (langToggle && langMenu) {
    langToggle.addEventListener("click", function () {
      var open = langMenu.classList.toggle("is-open");
      langToggle.setAttribute("aria-expanded", open ? "true" : "false");
    });
    document.addEventListener("click", function (e) {
      if (langMenuOpen() && !langMenu.contains(e.target)) closeLangMenu(false);
    });
    langMenu.addEventListener("focusout", function (e) {
      if (langMenuOpen() && e.relatedTarget && !langMenu.contains(e.relatedTarget)) closeLangMenu(false);
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && langMenuOpen()) closeLangMenu(true);
    });
    /* arrow keys walk the list */
    langMenu.addEventListener("keydown", function (e) {
      if (!langMenuOpen()) return;
      var items = Array.prototype.slice.call(langMenu.querySelectorAll(".lang-switch button"));
      var i = items.indexOf(document.activeElement);
      var next = e.key === "ArrowDown" ? (i + 1) % items.length :
                 e.key === "ArrowUp" ? (i <= 0 ? items.length - 1 : i - 1) :
                 e.key === "Home" ? 0 : e.key === "End" ? items.length - 1 : -1;
      if (next === -1) return;
      e.preventDefault();
      items[next].focus();
    });
  }

  document.querySelectorAll(".lang-switch button").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var lang = btn.getAttribute("data-lang");
      closeLangMenu(true);
      if (reduceMotion) { applyLang(lang); return; }
      /* soft crossfade: dim, swap strings, lift */
      document.documentElement.classList.add("lang-fading");
      setTimeout(function () {
        applyLang(lang);
        document.documentElement.classList.remove("lang-fading");
      }, 160);
    });
  });

  /* mobile nav */
  var toggle = document.getElementById("navToggle");
  var nav = document.getElementById("siteNav");

  function closeNav(returnFocus) {
    nav.classList.remove("is-open");
    toggle.setAttribute("aria-expanded", "false");
    if (returnFocus) toggle.focus();
  }

  if (toggle && nav) {
    toggle.addEventListener("click", function () {
      var open = nav.classList.toggle("is-open");
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
      if (open) {
        var first = nav.querySelector("a");
        if (first) first.focus();
      }
    });
    nav.addEventListener("click", function (e) {
      if (e.target.tagName === "A") closeNav(false);
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && nav.classList.contains("is-open")) closeNav(true);
    });
    /* close once keyboard focus moves on past the menu */
    nav.addEventListener("focusout", function (e) {
      var to = e.relatedTarget;
      if (nav.classList.contains("is-open") && to && !nav.contains(to) && to !== toggle) closeNav(false);
    });
  }

  var fitQueued = false;
  window.addEventListener("resize", function () {
    if (fitQueued) return;
    fitQueued = true;
    requestAnimationFrame(function () { fitQueued = false; fitHeader(); });
  });
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(fitHeader);

  /* language ribbon: a real pause control (hover alone leaves out keyboard and touch) */
  var ribbon = document.querySelector(".lang-ribbon");
  var ribbonBtn = document.querySelector(".ribbon-toggle");
  if (ribbon && ribbonBtn) {
    ribbonBtn.addEventListener("click", function () {
      var paused = ribbon.classList.toggle("is-paused");
      ribbonBtn.setAttribute("aria-pressed", paused ? "true" : "false");
    });
  }

  /* scroll reveal */
  var sections = document.querySelectorAll(".section .frame > *, .hero-copy > *");
  if ("IntersectionObserver" in window &&
      !window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
          io.unobserve(entry.target);
        }
      });
    }, { rootMargin: "0px 0px -8% 0px" });
    sections.forEach(function (el) {
      /* elements already on screen stay visible — only below-fold content animates in */
      var rect = el.getBoundingClientRect();
      if (rect.top < window.innerHeight && rect.bottom > 0) return;
      el.classList.add("reveal");
      io.observe(el);
    });
  }

  /* quote form — document uploads + submit.
     With data-endpoint set on the form (e.g. a Formspree/Web3Forms URL) files are
     POSTed for real; without it the form falls back to the visitor's e-mail app. */
  var form = document.getElementById("quoteForm");
  if (form) {
    var MAX_TOTAL_BYTES = 15 * 1024 * 1024;
    var endpoint = form.getAttribute("data-endpoint");
    var fileInput = document.getElementById("fileInput");
    var dropZone = document.getElementById("dropZone");
    var fileListEl = document.getElementById("fileList");
    var statusEl = document.getElementById("formStatus");
    var noteEl = document.getElementById("formNote");
    var serviceSel = form.elements.service;
    var submitBtn = form.querySelector("[type=submit]");
    var selectedFiles = [];

    if (endpoint && noteEl) noteEl.hidden = true;

    /* ?service=corp|visa preselects the service line (links from the other pages) */
    var svc = null;
    try { svc = new URLSearchParams(window.location.search).get("service"); } catch (e) { /* old browser */ }
    if (svc && serviceSel) {
      for (var o = 0; o < serviceSel.options.length; o++) {
        if (serviceSel.options[o].value === svc) { serviceSel.selectedIndex = o; break; }
      }
    }

    function dict() { return DICT[current] || DICT.tr; }

    function removeLabel(f) { return (dict()["form.fileRemove"] || "Remove") + ": " + f.name; }

    function fmtSize(b) {
      return b < 1048576 ? Math.max(1, Math.round(b / 1024)) + " KB" : (b / 1048576).toFixed(1) + " MB";
    }

    function totalBytes() {
      return selectedFiles.reduce(function (sum, f) { return sum + f.size; }, 0);
    }

    function showStatus(key, cls) {
      if (!statusEl) return;
      statusEl.textContent = dict()[key] || "";
      statusEl.className = "form-status " + cls;
    }

    function renderFiles() {
      fileListEl.textContent = "";
      selectedFiles.forEach(function (f, i) {
        var li = document.createElement("li");
        var name = document.createElement("span");
        name.className = "file-name";
        name.textContent = f.name;
        var size = document.createElement("span");
        size.className = "file-size";
        size.textContent = fmtSize(f.size);
        var rm = document.createElement("button");
        rm.type = "button";
        rm.className = "file-remove";
        rm.textContent = "×";
        rm.setAttribute("aria-label", removeLabel(f));
        rm.addEventListener("click", function () {
          selectedFiles.splice(i, 1);
          renderFiles();
          /* keep focus in the list instead of dropping it to <body> */
          var btns = fileListEl.querySelectorAll(".file-remove");
          (btns[Math.min(i, btns.length - 1)] || fileInput).focus();
        });
        li.append(name, size, rm);
        fileListEl.appendChild(li);
      });
      if (statusEl && totalBytes() > MAX_TOTAL_BYTES) showStatus("form.tooBig", "err");
      else if (statusEl) statusEl.textContent = "";
    }

    langHooks.push(function () {
      var btns = fileListEl.querySelectorAll(".file-remove");
      for (var b = 0; b < btns.length; b++) {
        if (selectedFiles[b]) btns[b].setAttribute("aria-label", removeLabel(selectedFiles[b]));
      }
    });

    function addFiles(list) {
      for (var i = 0; i < list.length; i++) selectedFiles.push(list[i]);
      renderFiles();
    }

    if (fileInput && dropZone) {
      fileInput.addEventListener("change", function () {
        addFiles(fileInput.files);
        fileInput.value = "";
      });
      ["dragover", "dragenter"].forEach(function (ev) {
        dropZone.addEventListener(ev, function (e) {
          e.preventDefault();
          dropZone.classList.add("is-drag");
        });
      });
      ["dragleave", "drop"].forEach(function (ev) {
        dropZone.addEventListener(ev, function (e) {
          e.preventDefault();
          /* crossing the zone's own icon/text fires dragleave too — ignore that */
          if (e.type === "dragleave" && dropZone.contains(e.relatedTarget)) return;
          dropZone.classList.remove("is-drag");
        });
      });
      dropZone.addEventListener("drop", function (e) {
        if (e.dataTransfer && e.dataTransfer.files) addFiles(e.dataTransfer.files);
      });
      /* a file dropped beside the zone must not navigate away and lose the form */
      ["dragover", "drop"].forEach(function (ev) {
        window.addEventListener(ev, function (e) {
          var dt = e.dataTransfer;
          var isFile = dt && Array.prototype.indexOf.call(dt.types || [], "Files") !== -1;
          if (!isFile || dropZone.contains(e.target)) return;
          e.preventDefault();
          if (ev === "dragover") dt.dropEffect = "none";
        });
      });
    }

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var d = dict();
      var data = new FormData(form);

      if (endpoint) {
        if (totalBytes() > MAX_TOTAL_BYTES) {
          showStatus("form.tooBig", "err");
          return;
        }
        data.delete("files");
        selectedFiles.forEach(function (f) { data.append("files", f, f.name); });
        showStatus("form.sending", "ok");
        /* one request per click — a double click must not upload everything twice */
        if (submitBtn) { submitBtn.disabled = true; submitBtn.setAttribute("aria-busy", "true"); }
        fetch(endpoint, { method: "POST", body: data, headers: { Accept: "application/json" } })
          .then(function (res) {
            if (!res.ok) throw new Error(res.status);
            form.reset();
            selectedFiles = [];
            renderFiles();
            showStatus("form.sent", "ok");
          })
          .catch(function () { showStatus("form.sendError", "err"); })
          .then(function () {
            if (submitBtn) { submitBtn.disabled = false; submitBtn.removeAttribute("aria-busy"); }
          });
        return;
      }

      var serviceText = serviceSel && serviceSel.selectedIndex > -1 ? serviceSel.options[serviceSel.selectedIndex].text : "";
      var phone = String(data.get("phone") || "").trim();
      var body =
        d["form.service"] + ": " + serviceText + "\n" +
        d["form.name"] + ": " + (data.get("name") || "") + "\n" +
        d["form.email"] + ": " + (data.get("email") || "") + "\n" +
        (phone ? (d["form.phone"] || "").replace(/\s*\(.*\)\s*$/, "") + ": " + phone + "\n" : "") +
        d["form.country"] + ": " + (data.get("country") || "") + "\n" +
        d["form.doc"] + ": " + (data.get("doctype") || "") + "\n\n" +
        (data.get("message") || "");
      if (selectedFiles.length) {
        body += "\n\n" + d["form.files"] + ": " + selectedFiles.map(function (f) {
          return f.name + " (" + fmtSize(f.size) + ")";
        }).join(", ");
      }
      var subject = d["form.subject"] + " — " + serviceText + " — " + (data.get("name") || "");
      window.location.href = "mailto:info@muhurtercume.com" +
        "?subject=" + encodeURIComponent(subject) +
        "&body=" + encodeURIComponent(body);
    });
  }

  /* header condenses once the page has scrolled */
  var header = document.querySelector(".site-header");
  if (header) {
    var wa = document.querySelector(".wa-float");
    var heroEl = document.querySelector(".hero");
    var onScroll = function () {
      header.classList.toggle("is-scrolled", window.scrollY > 8);
      /* the hero already carries a WhatsApp button — float in only after it */
      if (wa) wa.classList.toggle("is-shown", window.scrollY > ((heroEl && heroEl.offsetHeight) || 0) - 200);
    };
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
  }

  /* stat numbers count up the first time they scroll into view */
  var counters = document.querySelectorAll("[data-count]");
  if (counters.length && !reduceMotion && "IntersectionObserver" in window) {
    var cio = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        cio.unobserve(entry.target);
        var el = entry.target;
        var target = parseInt(el.getAttribute("data-count"), 10) || 0;
        var start = null, dur = 1100;
        var step = function (ts) {
          if (start === null) start = ts;
          var t = Math.min(1, (ts - start) / dur);
          var eased = 1 - Math.pow(1 - t, 3);
          el.textContent = String(Math.round(target * eased));
          if (t < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
      });
    }, { threshold: .6 });
    counters.forEach(function (el) { cio.observe(el); });
  }

  applyLang(detectLang());
})();
