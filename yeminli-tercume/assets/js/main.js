/* MÜHÜR — language switching, nav, reveal, quote form */
(function () {
  "use strict";

  var DICT = window.MUHUR_I18N || {};
  var SUPPORTED = ["tr", "en", "de", "fr", "es", "it"];
  var current = "tr";

  function detectLang() {
    var saved = null;
    try { saved = localStorage.getItem("muhur-lang"); } catch (e) { /* storage may be blocked */ }
    if (saved && SUPPORTED.indexOf(saved) !== -1) return saved;
    var nav = (navigator.language || "tr").slice(0, 2).toLowerCase();
    return SUPPORTED.indexOf(nav) !== -1 ? nav : "tr";
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

    document.querySelectorAll(".lang-switch button").forEach(function (btn) {
      btn.classList.toggle("is-active", btn.getAttribute("data-lang") === lang);
    });

    try { localStorage.setItem("muhur-lang", lang); } catch (e) { /* ignore */ }
  }

  document.querySelectorAll(".lang-switch button").forEach(function (btn) {
    btn.addEventListener("click", function () {
      applyLang(btn.getAttribute("data-lang"));
    });
  });

  /* mobile nav */
  var toggle = document.getElementById("navToggle");
  var nav = document.getElementById("siteNav");
  if (toggle && nav) {
    toggle.addEventListener("click", function () {
      var open = nav.classList.toggle("is-open");
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
    });
    nav.addEventListener("click", function (e) {
      if (e.target.tagName === "A") {
        nav.classList.remove("is-open");
        toggle.setAttribute("aria-expanded", "false");
      }
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

  /* quote form → opens the visitor's e-mail app with a prefilled message */
  var form = document.getElementById("quoteForm");
  if (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var dict = DICT[current] || DICT.tr;
      var data = new FormData(form);
      var body =
        dict["form.name"] + ": " + (data.get("name") || "") + "\n" +
        dict["form.email"] + ": " + (data.get("email") || "") + "\n" +
        dict["form.country"] + ": " + (data.get("country") || "") + "\n" +
        dict["form.doc"] + ": " + (data.get("doctype") || "") + "\n\n" +
        (data.get("message") || "");
      var subject = dict["form.subject"] + " — " + (data.get("name") || "");
      window.location.href = "mailto:info@muhurtercume.com" +
        "?subject=" + encodeURIComponent(subject) +
        "&body=" + encodeURIComponent(body);
    });
  }

  applyLang(detectLang());
})();
