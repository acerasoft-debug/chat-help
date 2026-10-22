// ChatHelp Form Engine v2 — with plan check for PDF
// Used by all staatliche HTML forms

(function(){
  // ── Plan check ──
  function getUserPlan(){
    try {
      const u = JSON.parse(localStorage.getItem('ch_user') || '{}');
      return u.plan || 'free';
    } catch(e){ return 'free'; }
  }

  function isLoggedIn(){
    return !!localStorage.getItem('ch_token');
  }

  // ── Paywall overlay (shared close handling) ──
  function showPaywall(msg){
    const overlay = document.createElement('div');
    overlay.className = 'ch-paywall-overlay';
    overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.8);display:flex;align-items:flex-end;justify-content:center;padding:20px';
    overlay.innerHTML = `<div style="background:#0d0d1f;border-radius:20px;padding:24px;max-width:400px;width:100%;border:1px solid #2a2a4a;text-align:center">
      <div style="font-size:36px;margin-bottom:12px">🔒</div>
      <div style="font-size:16px;font-weight:700;color:#fff;margin-bottom:8px">PDF freischalten</div>
      <div style="font-size:13px;color:#808090;margin-bottom:20px">${msg}</div>
      <div style="display:flex;flex-direction:column;gap:10px">
        <button type="button" class="ch-paywall-go"
          style="padding:13px;background:linear-gradient(135deg,#d4a84a,#c09030);border:none;border-radius:12px;color:#fff;font-size:14px;font-weight:700;cursor:pointer">
          📱 Zum ChatHelp Portal
        </button>
        <button type="button" class="ch-paywall-cancel"
          style="padding:11px;background:rgba(255,255,255,.06);border:1px solid #2a2a4a;border-radius:12px;color:#808090;font-size:13px;cursor:pointer">
          Abbrechen
        </button>
      </div>
    </div>`;

    function close(){
      overlay.remove();
      document.removeEventListener('keydown', onKey);
    }
    function onKey(e){ if(e.key === 'Escape') close(); }

    // "Portal'a git" — kullanıcının doldurduğu form kaybolmasın diye yeni sekmede aç
    overlay.querySelector('.ch-paywall-go').addEventListener('click', function(){
      window.open('https://chat-help.com/chat/', '_blank', 'noopener');
      close();
    });
    overlay.querySelector('.ch-paywall-cancel').addEventListener('click', close);
    // Arka plana tıklayınca da kapansın
    overlay.addEventListener('click', function(e){ if(e.target === overlay) close(); });
    document.addEventListener('keydown', onKey);

    document.body.appendChild(overlay);
  }

  // ── Formda en az bir alan dolu mu? (boş formdan PDF üretmeyi engeller) ──
  function anyFieldFilled(){
    const sel = '.form-field input, .form-field select, .form-field textarea, .field-group input, .field-group select, .field-group textarea';
    return Array.prototype.some.call(document.querySelectorAll(sel), function(i){
      return i.value && i.value.trim() && i.value !== '— Bundesland wählen —';
    });
  }

  // ── PDF Export with plan check ──
  window.exportFormPDF = async function(title, filename){
    /* CH_STAAT_REQFIX — Pflichtfelder vor PDF prüfen (paywall'dan da önce) */
    if (typeof window.validateForm === 'function' && !window.validateForm()){
      alert('Bitte füllen Sie alle Pflichtfelder aus (rot markiert).');
      return;
    }
    if (!anyFieldFilled()){
      alert('Das Formular ist leer — bitte füllen Sie es zuerst aus.');
      return;
    }

    const plan = getUserPlan();
    const logged = isLoggedIn();

    if(!logged || plan === 'free'){
      const msg = logged
        ? '🔒 PDF-Download erfordert einen Basic-Plan (ab 13,99€/Mo) oder Einzelzahlung.'
        : '🔒 Bitte anmelden — PDF-Download ab Basic-Plan oder 1,99€ Einzelzahlung.';
      showPaywall(msg);
      return;
    }

    // User has plan — generate PDF
    _generatePDF(title, filename);
  };

  function _generatePDF(title, filename){
    // jsPDF UMD globalini güvenli oku — kütüphane yüklenmemişse çökme, uyar
    const jsPDFCtor = (window.jspdf && window.jspdf.jsPDF) || window.jsPDF;
    if(typeof jsPDFCtor !== 'function'){
      alert('PDF-Bibliothek nicht geladen. Bitte Seite neu laden und erneut versuchen.');
      console.error('[ChatHelp] jsPDF konnte nicht geladen werden (window.jspdf fehlt).');
      return;
    }

    const doc = new jsPDFCtor({ orientation: 'portrait', unit: 'mm', format: 'a4' });
    const pageW = 210, margin = 20, lineH = 7;
    let y = 25;

    // Header
    doc.setFillColor(13, 13, 31);
    doc.rect(0, 0, pageW, 18, 'F');
    doc.setTextColor(212, 168, 74);
    doc.setFontSize(11);
    doc.setFont('helvetica', 'bold');
    doc.text('ChatHelp', margin, 12);
    doc.setTextColor(255,255,255);
    doc.setFontSize(9);
    doc.text(title, pageW/2, 12, {align:'center'});
    doc.setTextColor(100,100,120);
    doc.text(new Date().toLocaleDateString('de-DE'), pageW-margin, 12, {align:'right'});

    y = 30;
    doc.setTextColor(30,30,50);
    doc.setFontSize(14);
    doc.setFont('helvetica', 'bold');
    doc.text(title, pageW/2, y, {align:'center'});
    y += 12;

    // Fields
    doc.setFontSize(10);
    document.querySelectorAll('.form-field, .field-group').forEach(ff => {
      const labels = ff.querySelectorAll('label');
      const inputs = ff.querySelectorAll('input, select, textarea');

      labels.forEach((lbl, idx) => {
        const inp = inputs[idx];
        if(!inp || !inp.value.trim() || inp.value === '— Bundesland wählen —') return;

        if(y > 270){
          doc.addPage();
          y = 20;
        }

        // Label
        doc.setFont('helvetica', 'bold');
        doc.setTextColor(80,80,100);
        doc.setFontSize(8);
        doc.text(lbl.textContent.replace(/[*]/g,'').trim() + ':', margin, y);

        // Value
        doc.setFont('helvetica', 'normal');
        doc.setTextColor(20,20,40);
        doc.setFontSize(10);
        const val = inp.value.trim();
        const lines = doc.splitTextToSize(val, pageW - margin*2 - 5);
        lines.forEach((line, li) => {
          if(y > 270){ doc.addPage(); y = 20; }
          doc.text(line, margin + 5, y + 5 + (li * lineH));
        });
        y += 5 + (lines.length * lineH) + 4;

        // Separator
        doc.setDrawColor(220,220,230);
        doc.line(margin, y, pageW-margin, y);
        y += 3;
      });
    });

    // Footer
    const pages = doc.getNumberOfPages();
    for(let i=1;i<=pages;i++){
      doc.setPage(i);
      doc.setFontSize(8);
      doc.setTextColor(150,150,170);
      doc.text('ChatHelp — chat-help.com  |  Seite '+i+'/'+pages, pageW/2, 290, {align:'center'});
    }

    doc.save((filename||'formular') + '_' + new Date().toISOString().slice(0,10) + '.pdf');
  }

  // ── Bundesland auto-fill ──
  window.setBundesland = function(select){
    const bl = select.value;
    document.querySelectorAll('[data-bundesland-fill]').forEach(el=>{
      el.value = bl;
    });
  };

  // ── Form validation ──
  window.validateForm = function(){
    let valid = true;
    let first = null;
    document.querySelectorAll('[required]').forEach(inp=>{
      /* CH_STAAT_REQFIX: gizli/devre dışı alanlar doğrulamaya girmez */
      if(inp.disabled || inp.getClientRects().length === 0){ inp.style.borderColor=''; return; }
      if(!inp.value.trim()){
        inp.style.borderColor = '#e04858';
        valid = false;
        if(!first) first = inp;
      } else {
        inp.style.borderColor = '';
      }
    });
    if(first) first.scrollIntoView({behavior:'smooth', block:'center'});
    return valid;
  };

})();
