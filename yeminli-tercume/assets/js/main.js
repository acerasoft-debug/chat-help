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
    var selectedFiles = [];

    if (endpoint && noteEl) noteEl.hidden = true;

    function dict() { return DICT[current] || DICT.tr; }

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
      statusEl.hidden = false;
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
        rm.setAttribute("aria-label", dict()["form.fileRemove"] || "Remove");
        rm.addEventListener("click", function () {
          selectedFiles.splice(i, 1);
          renderFiles();
        });
        li.append(name, size, rm);
        fileListEl.appendChild(li);
      });
      if (statusEl && totalBytes() > MAX_TOTAL_BYTES) showStatus("form.tooBig", "err");
      else if (statusEl) statusEl.hidden = true;
    }

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
          dropZone.classList.remove("is-drag");
        });
      });
      dropZone.addEventListener("drop", function (e) {
        if (e.dataTransfer && e.dataTransfer.files) addFiles(e.dataTransfer.files);
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
        fetch(endpoint, { method: "POST", body: data, headers: { Accept: "application/json" } })
          .then(function (res) {
            if (!res.ok) throw new Error(res.status);
            form.reset();
            selectedFiles = [];
            renderFiles();
            showStatus("form.sent", "ok");
          })
          .catch(function () { showStatus("form.sendError", "err"); });
        return;
      }

      var body =
        d["form.name"] + ": " + (data.get("name") || "") + "\n" +
        d["form.email"] + ": " + (data.get("email") || "") + "\n" +
        d["form.country"] + ": " + (data.get("country") || "") + "\n" +
        d["form.doc"] + ": " + (data.get("doctype") || "") + "\n\n" +
        (data.get("message") || "");
      if (selectedFiles.length) {
        body += "\n\n" + d["form.files"] + ": " + selectedFiles.map(function (f) {
          return f.name + " (" + fmtSize(f.size) + ")";
        }).join(", ");
      }
      var subject = d["form.subject"] + " — " + (data.get("name") || "");
      window.location.href = "mailto:info@muhurtercume.com" +
        "?subject=" + encodeURIComponent(subject) +
        "&body=" + encodeURIComponent(body);
    });
  }

  applyLang(detectLang());
})();
