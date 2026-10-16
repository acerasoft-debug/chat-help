/* ChatHelp Recht — Skin v1 (JS): yalnız metin/etiket düzenlemeleri, mantığa dokunmaz. */
(function(){
  var LANG=(document.documentElement.lang||'de').slice(0,2);
  var T={
    de:{t:'Warum ChatHelp bessere Schreiben erstellt als Standard-KI',
        pts:['Jedes Dokument wird von zwei unabhängigen KI-Prüfungen gegengelesen','Nur echte Rechtsgrundlagen – jeder § mit Quelle, keine erfundenen Paragrafen','Aktuelle Rechtslage & Fristen für DE · AT · CH · FR · TR','Vielfach gegen Standard-KI getestet, mit den neuesten Spitzenmodellen','Versandfertig: Post, Einschreiben oder Fax – direkt aus ChatHelp']},
    tr:{t:'ChatHelp neden standart yapay zekâdan daha iyi dilekçe yazar',
        pts:['Her belge iki bağımsız yapay zekâ kontrolünden geçer','Yalnız gerçek yasal dayanak – her madde kaynağıyla, uydurma madde yok','Güncel mevzuat ve süreler: DE · AT · CH · FR · TR','Standart yapay zekâlara karşı defalarca test edildi, en güncel üst modellerle','Gönderime hazır: posta, iadeli taahhütlü veya faks']},
    fr:{t:'Pourquoi ChatHelp rédige de meilleurs courriers que les IA standard',
        pts:['Chaque document est relu par deux vérifications IA indépendantes','Uniquement de vraies bases légales – chaque article avec sa source','Droit et délais à jour : DE · AT · CH · FR · TR','Testé à de nombreuses reprises face aux IA standard, avec les meilleurs modèles actuels','Prêt à envoyer : courrier, recommandé ou fax']},
    en:{t:'Why ChatHelp drafts better legal letters than standard AI',
        pts:['Every document is cross-checked by two independent AI reviews','Only real legal bases – every § with its source, no invented paragraphs','Up-to-date law & deadlines for DE · AT · CH · FR · TR','Tested many times against standard AI, built on the latest top models','Ready to send: post, registered mail or fax']},
    ru:{t:'Почему ChatHelp пишет лучшие документы, чем обычный ИИ',
        pts:['Каждый документ проверяют две независимые ИИ-проверки','Только реальные правовые основания – каждый § с источником','Актуальное право и сроки: DE · AT · CH · FR · TR','Многократно протестирован против обычного ИИ, на новейших моделях','Готово к отправке: почта, заказное письмо или факс']}
  };
  var L=T[LANG]||T.de;
  function esc(s){return String(s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
  function apply(){
    try{
      /* 1) "10× besser" bandı → dürüst farklılaştırma */
      document.querySelectorAll('.wlc-10x').forEach(function(b){
        if(b.getAttribute('data-sk')) return; b.setAttribute('data-sk','1');
        var ic=b.querySelector('.wlc-10x-ic'); if(ic) ic.textContent='⚖️';
        var t=b.querySelector('.wlc-10x-t'); if(t) t.textContent=L.t;
        var s=b.querySelector('.wlc-10x-s'); if(s) s.innerHTML=L.pts.map(function(p){return '<span class="sk-pt">'+esc(p)+'</span>';}).join('');
      });
      /* 2) Fiyat penceresindeki USP satırı */
      document.querySelectorAll('.pm-usp').forEach(function(u){ if(!u.getAttribute('data-sk')){ u.setAttribute('data-sk','1'); u.textContent=L.pts[0]+' · '+L.pts[1]; } });
      /* 3) Eski alan adı / etiket düzeltmeleri */
      document.querySelectorAll('.sb-foot-txt').forEach(function(f){ f.textContent=f.textContent.replace('chat-help.de','chat-help.com'); });
      /* 4) Ana platforma dönüş bağlantısı (kenar çubuğu altına, bir kez) */
      var foot=document.querySelector('.sb-foot');
      if(foot && !document.getElementById('sk-home')){
        var a=document.createElement('a'); a.id='sk-home'; a.href='/'; a.textContent={de:'← Alle ChatHelp-Dienste (Agenten, Creator, API)',tr:'← Tüm ChatHelp hizmetleri (Agent, Creator, API)',fr:'← Tous les services ChatHelp',en:'← All ChatHelp services (Agents, Creator, API)',ru:'← Все сервисы ChatHelp'}[LANG]||'← Alle ChatHelp-Dienste';
        a.style.cssText='display:block;margin-top:10px;font-size:11.5px;color:var(--gold);text-decoration:none;font-weight:600';
        foot.appendChild(a);
      }
    }catch(e){}
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',apply); else apply();
  /* Karşılama ekranı yeniden çizildiğinde (Neues Gespräch) tekrar uygula */
  var mo=new MutationObserver(function(){ apply(); });
  try{ mo.observe(document.body,{childList:true,subtree:true}); }catch(e){}
})();
