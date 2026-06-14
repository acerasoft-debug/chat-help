// ============================================================
// MONDIMART - Premium FAQ Sayfası
// Kullanım: SHOPIFY_TOKEN='...' node mondimart-faq.mjs
// ============================================================

const SHOPIFY_TOKEN = process.env.SHOPIFY_TOKEN;
const SHOP = 'pftzey-y0.myshopify.com';
const BASE = `https://${SHOP}/admin/api/2024-04`;
const H = { 'X-Shopify-Access-Token': SHOPIFY_TOKEN, 'Content-Type': 'application/json' };

if (!SHOPIFY_TOKEN) { console.error('SHOPIFY_TOKEN gerekli'); process.exit(1); }

const faqHTML = `
<style>
  *,*::before,*::after{box-sizing:border-box}
  .faq-wrap{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#1a1a1a;max-width:860px;margin:0 auto;padding:0 16px 60px}

  /* ── HERO ── */
  .faq-hero{
    position:relative;overflow:hidden;
    background:linear-gradient(135deg,#0d3b0d 0%,#1a5c1a 40%,#2e8b2e 75%,#52c052 100%);
    color:#fff;text-align:center;padding:64px 32px 56px;border-radius:24px;margin-bottom:48px;
    box-shadow:0 20px 60px rgba(30,100,30,.35);
  }
  .faq-hero::before{
    content:'';position:absolute;inset:0;
    background:radial-gradient(ellipse 80% 60% at 50% 0%,rgba(255,255,255,.08),transparent);
    pointer-events:none;
  }
  .faq-hero-badge{
    display:inline-block;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);
    border-radius:50px;padding:6px 18px;font-size:.8rem;font-weight:600;letter-spacing:.08em;
    text-transform:uppercase;margin-bottom:18px;backdrop-filter:blur(4px);
  }
  .faq-hero h1{font-size:2.4rem;font-weight:900;margin:0 0 14px;letter-spacing:-.8px;line-height:1.1}
  .faq-hero p{font-size:1.05rem;opacity:.8;margin:0 auto;max-width:520px;line-height:1.6}
  .faq-hero-stats{
    display:flex;justify-content:center;gap:32px;margin-top:32px;padding-top:28px;
    border-top:1px solid rgba(255,255,255,.15);flex-wrap:wrap;
  }
  .faq-hero-stat{text-align:center}
  .faq-hero-stat strong{display:block;font-size:1.6rem;font-weight:800;letter-spacing:-.5px}
  .faq-hero-stat span{font-size:.78rem;opacity:.7;text-transform:uppercase;letter-spacing:.06em}

  /* ── NAV TABS ── */
  .faq-nav{
    display:flex;gap:8px;margin-bottom:36px;flex-wrap:wrap;
    position:sticky;top:0;z-index:10;
    background:rgba(255,255,255,.95);backdrop-filter:blur(8px);
    padding:12px 0 10px;border-bottom:1px solid #e8f0e8;
  }
  .faq-nav-btn{
    display:inline-flex;align-items:center;gap:6px;
    background:#f4f9f4;border:1.5px solid #d0e8d0;border-radius:50px;
    padding:8px 18px;font-size:.85rem;font-weight:600;color:#2d6a2d;
    cursor:pointer;transition:all .2s;white-space:nowrap;
  }
  .faq-nav-btn:hover,.faq-nav-btn.active{
    background:#2d6a2d;color:#fff;border-color:#2d6a2d;
    box-shadow:0 4px 14px rgba(45,106,45,.3);
  }

  /* ── SECTION ── */
  .faq-section{margin-bottom:44px;scroll-margin-top:80px}
  .faq-section-header{
    display:flex;align-items:center;gap:12px;margin-bottom:20px;
  }
  .faq-section-icon{
    width:44px;height:44px;border-radius:12px;
    background:linear-gradient(135deg,#e8f5e8,#c8e8c8);
    display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;
    box-shadow:0 2px 8px rgba(45,106,45,.12);
  }
  .faq-section-title{font-size:1.2rem;font-weight:800;color:#0d3b0d;letter-spacing:-.2px}
  .faq-section-subtitle{font-size:.82rem;color:#888;margin-top:1px}

  /* ── ACCORDION ── */
  .faq-item{
    background:#fff;border:1.5px solid #e0edd0;border-radius:14px;margin-bottom:10px;
    overflow:hidden;transition:box-shadow .25s,border-color .25s;
  }
  .faq-item:hover{border-color:#b0d4b0;box-shadow:0 4px 20px rgba(30,90,30,.08)}
  .faq-item.open{border-color:#4caf50;box-shadow:0 6px 24px rgba(30,90,30,.12)}
  .faq-question{
    padding:18px 22px;cursor:pointer;display:flex;justify-content:space-between;
    align-items:center;gap:16px;user-select:none;
  }
  .faq-q-text{font-weight:700;font-size:.96rem;color:#1a3a1a;line-height:1.4}
  .faq-q-icon{
    width:28px;height:28px;border-radius:50%;background:#f0f9f0;border:1.5px solid #c8e8c8;
    display:flex;align-items:center;justify-content:center;flex-shrink:0;
    font-size:1rem;color:#4caf50;font-weight:300;transition:all .25s;line-height:1;
  }
  .faq-item.open .faq-q-icon{background:#4caf50;color:#fff;border-color:#4caf50;transform:rotate(45deg)}
  .faq-answer{
    display:none;padding:0 22px 20px;font-size:.92rem;color:#444;
    line-height:1.75;border-top:1px solid #e8f2e8;
  }
  .faq-item.open .faq-answer{display:block}
  .faq-answer p{margin:.75em 0}
  .faq-answer p:first-child{margin-top:14px}
  .faq-answer ul,.faq-answer ol{margin:.6em 0;padding-left:1.4em}
  .faq-answer li{margin:.35em 0}
  .faq-answer strong{color:#1a3a1a}

  /* ── DELIVERY TABLE ── */
  .delivery-table{width:100%;border-collapse:separate;border-spacing:0;margin:16px 0 12px;font-size:.9rem}
  .delivery-table thead tr th{
    background:linear-gradient(135deg,#1a5c1a,#2d8b2d);color:#fff;
    padding:12px 16px;text-align:left;font-weight:700;font-size:.82rem;
    letter-spacing:.04em;text-transform:uppercase;
  }
  .delivery-table thead tr th:first-child{border-radius:10px 0 0 0}
  .delivery-table thead tr th:last-child{border-radius:0 10px 0 0}
  .delivery-table tbody tr{border-bottom:1px solid #e8f0e8;transition:background .15s}
  .delivery-table tbody tr:hover{background:#f8fdf8}
  .delivery-table tbody tr:last-child td{border-bottom:none}
  .delivery-table td{padding:13px 16px;border-bottom:1px solid #eef4ee;vertical-align:middle}
  .delivery-table td:first-child{font-weight:600;color:#1a3a1a}
  .dt-price{font-weight:700;color:#2d6a2d}
  .dt-free{font-weight:700;color:#e65100}
  .dt-badge{
    display:inline-flex;align-items:center;gap:4px;
    background:#e3f2fd;color:#1565c0;border:1px solid #bbdefb;
    border-radius:20px;padding:2px 9px;font-size:.75rem;font-weight:600;white-space:nowrap;
  }
  .dt-badge.green{background:#e8f5e9;color:#2e7d32;border-color:#a5d6a7}

  /* ── INFO CARDS ── */
  .info-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:14px 0}
  .info-card{
    background:linear-gradient(135deg,#f8fdf8,#f0f9f0);
    border:1.5px solid #d0e8d0;border-radius:12px;padding:16px;text-align:center;
  }
  .info-card .ic-icon{font-size:1.8rem;margin-bottom:8px}
  .info-card .ic-label{font-size:.78rem;color:#888;text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px}
  .info-card .ic-value{font-size:1.05rem;font-weight:800;color:#1a3a1a}
  .info-card .ic-sub{font-size:.78rem;color:#666;margin-top:2px}

  /* ── CALLOUT BOXES ── */
  .callout{border-radius:12px;padding:14px 18px;margin:14px 0;font-size:.9rem;line-height:1.65;display:flex;gap:12px;align-items:flex-start}
  .callout-icon{font-size:1.3rem;flex-shrink:0;margin-top:1px}
  .callout.blue{background:#e8f4fd;border:1.5px solid #b3d9f5;color:#1a4a7a}
  .callout.green{background:#e8f5e9;border:1.5px solid #a5d6a7;color:#1b5e20}
  .callout.amber{background:#fff8e1;border:1.5px solid #ffe082;color:#5d4037}
  .callout.red{background:#fce4ec;border:1.5px solid #f48fb1;color:#880e4f}

  /* ── STEP FLOW ── */
  .steps{margin:14px 0;counter-reset:step}
  .step{display:flex;gap:14px;margin-bottom:16px;align-items:flex-start}
  .step-num{
    width:32px;height:32px;border-radius:50%;flex-shrink:0;
    background:linear-gradient(135deg,#2d6a2d,#4caf50);color:#fff;
    display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.9rem;
    box-shadow:0 3px 10px rgba(45,106,45,.25);
  }
  .step-body{padding-top:5px;font-size:.92rem;line-height:1.6}
  .step-body strong{display:block;color:#1a3a1a;font-weight:700;margin-bottom:2px}

  /* ── FREE SHIPPING BANNER ── */
  .free-banner{
    background:linear-gradient(135deg,#1b5e20,#2e7d32);color:#fff;
    border-radius:14px;padding:18px 22px;display:flex;align-items:center;
    gap:16px;margin:16px 0;box-shadow:0 6px 20px rgba(27,94,32,.25);
  }
  .free-banner-icon{font-size:2rem;flex-shrink:0}
  .free-banner-text strong{display:block;font-size:1.05rem;font-weight:800;letter-spacing:-.2px}
  .free-banner-text span{font-size:.85rem;opacity:.85}

  /* ── COLD CHAIN VISUAL ── */
  .cold-flow{display:flex;align-items:center;gap:0;margin:16px 0;flex-wrap:wrap}
  .cold-step{
    flex:1;min-width:120px;background:#e3f2fd;border:1.5px solid #b3d9f5;
    border-radius:10px;padding:12px 10px;text-align:center;position:relative;
  }
  .cold-step .cs-icon{font-size:1.5rem;margin-bottom:5px}
  .cold-step .cs-label{font-size:.75rem;font-weight:700;color:#1565c0;line-height:1.3}
  .cold-arrow{color:#90caf9;font-size:1.4rem;padding:0 6px;flex-shrink:0}

  /* ── PAYMENT ICONS ── */
  .payment-methods{display:flex;gap:10px;flex-wrap:wrap;margin:12px 0}
  .pm{
    display:inline-flex;align-items:center;gap:7px;
    background:#f8f8f8;border:1.5px solid #e0e0e0;border-radius:8px;
    padding:8px 14px;font-size:.85rem;font-weight:600;color:#333;
  }

  /* ── HALAL CERT ── */
  .halal-box{
    background:linear-gradient(135deg,#e8f5e9,#f1f8e9);
    border:2px solid #66bb6a;border-radius:14px;padding:18px 20px;margin:14px 0;
    display:flex;gap:16px;align-items:flex-start;
  }
  .halal-badge{
    width:52px;height:52px;border-radius:50%;
    background:linear-gradient(135deg,#2e7d32,#4caf50);
    display:flex;align-items:center;justify-content:center;
    font-size:1.5rem;flex-shrink:0;
    box-shadow:0 4px 14px rgba(46,125,50,.3);
  }
  .halal-text strong{font-size:1rem;color:#1b5e20;display:block;margin-bottom:5px}
  .halal-text p{font-size:.88rem;color:#444;line-height:1.6;margin:0}

  /* ── CONTACT SECTION ── */
  .contact-section{
    background:linear-gradient(135deg,#0d3b0d,#1a5c1a);
    border-radius:20px;padding:40px 32px;text-align:center;margin-top:48px;
    box-shadow:0 16px 48px rgba(13,59,13,.3);position:relative;overflow:hidden;
  }
  .contact-section::before{
    content:'';position:absolute;inset:0;
    background:radial-gradient(ellipse 70% 50% at 50% 0%,rgba(255,255,255,.07),transparent);
  }
  .contact-section h2{color:#fff;font-size:1.7rem;font-weight:800;margin:0 0 10px;letter-spacing:-.4px}
  .contact-section p{color:rgba(255,255,255,.75);font-size:.95rem;margin:0 0 28px;line-height:1.6}
  .contact-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:28px}
  .contact-card{
    background:rgba(255,255,255,.1);backdrop-filter:blur(8px);
    border:1px solid rgba(255,255,255,.2);border-radius:14px;padding:18px;
  }
  .contact-card .cc-icon{font-size:1.6rem;margin-bottom:8px}
  .contact-card .cc-label{font-size:.78rem;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px}
  .contact-card .cc-value{font-size:.92rem;font-weight:600;color:#fff}
  .contact-btn{
    display:inline-flex;align-items:center;gap:8px;
    background:#fff;color:#1a5c1a;padding:14px 32px;border-radius:50px;
    font-weight:800;font-size:.95rem;text-decoration:none;
    box-shadow:0 6px 20px rgba(0,0,0,.2);transition:transform .2s,box-shadow .2s;
  }
  .contact-btn:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(0,0,0,.25)}

  @media(max-width:600px){
    .faq-hero h1{font-size:1.7rem}
    .faq-hero-stats{gap:20px}
    .faq-nav{gap:6px}
    .cold-flow{gap:6px}
    .cold-arrow{display:none}
  }
</style>

<div class="faq-wrap">

<!-- HERO -->
<div class="faq-hero">
  <div class="faq-hero-badge">Centre d'aide Mondimart</div>
  <h1>Questions Fréquentes</h1>
  <p>Trouvez rapidement les réponses à toutes vos questions sur nos produits, livraisons et services.</p>
  <div class="faq-hero-stats">
    <div class="faq-hero-stat"><strong>+3 400</strong><span>Produits</span></div>
    <div class="faq-hero-stat"><strong>24h</strong><span>Livraison FR</span></div>
    <div class="faq-hero-stat"><strong>149€</strong><span>Livraison offerte</span></div>
    <div class="faq-hero-stat"><strong>100%</strong><span>Halal certifié</span></div>
  </div>
</div>

<!-- NAV -->
<div class="faq-nav" id="faq-nav">
  <button class="faq-nav-btn active" onclick="scrollTo('livraison')">🚚 Livraison</button>
  <button class="faq-nav-btn" onclick="scrollTo('coldchain')">❄️ Chaîne du froid</button>
  <button class="faq-nav-btn" onclick="scrollTo('commandes')">📦 Commandes</button>
  <button class="faq-nav-btn" onclick="scrollTo('paiement')">💳 Paiement</button>
  <button class="faq-nav-btn" onclick="scrollTo('produits')">🛍️ Produits</button>
  <button class="faq-nav-btn" onclick="scrollTo('retours')">↩️ Retours</button>
  <button class="faq-nav-btn" onclick="scrollTo('compte')">👤 Mon compte</button>
</div>

<!-- ── SECTION 1 : LIVRAISON ── -->
<div class="faq-section" id="livraison">
  <div class="faq-section-header">
    <div class="faq-section-icon">🚚</div>
    <div>
      <div class="faq-section-title">Livraison & Délais</div>
      <div class="faq-section-subtitle">Modes d'expédition, délais et tarifs</div>
    </div>
  </div>

  <div class="faq-item open">
    <div class="faq-question"><span class="faq-q-text">Quels sont les délais et tarifs de livraison ?</span><span class="faq-q-icon">+</span></div>
    <div class="faq-answer">
      <table class="delivery-table">
        <thead><tr>
          <th>Destination</th>
          <th>Type</th>
          <th>Délai</th>
          <th>Tarif</th>
        </tr></thead>
        <tbody>
          <tr>
            <td>🇫🇷 France métropolitaine</td>
            <td>Standard</td>
            <td>⚡ 24h après expédition</td>
            <td><span class="dt-price">8,00 €</span></td>
          </tr>
          <tr>
            <td>🇫🇷 France métropolitaine</td>
            <td><span class="dt-badge">❄️ Chaîne du froid</span></td>
            <td>⚡ 24h après expédition</td>
            <td><span class="dt-price">18,00 €</span></td>
          </tr>
          <tr>
            <td>🇧🇪 Belgique / 🇱🇺 Luxembourg</td>
            <td>Standard</td>
            <td>⏱ 24–48h</td>
            <td><span class="dt-price">18,00 €</span></td>
          </tr>
          <tr>
            <td>🇧🇪 Belgique / 🇱🇺 Luxembourg</td>
            <td><span class="dt-badge">❄️ Chaîne du froid</span></td>
            <td>⏱ 24–48h</td>
            <td><span class="dt-price">24,00 €</span></td>
          </tr>
          <tr>
            <td>🇪🇸 Espagne</td>
            <td>Standard</td>
            <td>⏱ 2–4 jours</td>
            <td><span class="dt-price">18,00 €</span></td>
          </tr>
          <tr>
            <td>🇪🇸 Espagne</td>
            <td><span class="dt-badge">❄️ Chaîne du froid</span></td>
            <td>⏱ 24–48h</td>
            <td><span class="dt-price">24,00 €</span></td>
          </tr>
          <tr>
            <td>🌍 Reste de l'Europe (DE, NL, IT, PT…)</td>
            <td>Standard</td>
            <td>⏱ 2–4 jours</td>
            <td><span class="dt-price">18,00 €</span></td>
          </tr>
          <tr>
            <td colspan="3" style="font-weight:700;color:#1b5e20">🎁 Livraison offerte dès 149€ d'achat (tous pays, tous types)</td>
            <td><span class="dt-free">GRATUIT</span></td>
          </tr>
        </tbody>
      </table>
      <div class="callout green"><span class="callout-icon">💡</span><div>Les délais indiqués correspondent au délai de transport après expédition. Ajoutez <strong>1 jour ouvré de préparation</strong> à votre commande. Les commandes passées avant 12h sont expédiées le jour même (hors week-end).</div></div>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question"><span class="faq-q-text">À quelle heure dois-je commander pour une livraison le lendemain ?</span><span class="faq-q-icon">+</span></div>
    <div class="faq-answer">
      <p>Pour bénéficier d'une livraison le lendemain en France, votre commande doit être validée <strong>avant 12h00 (midi)</strong> du lundi au vendredi.</p>
      <div class="info-cards">
        <div class="info-card"><div class="ic-icon">🌅</div><div class="ic-label">Commande avant</div><div class="ic-value">12h00</div><div class="ic-sub">Expédition le jour même</div></div>
        <div class="info-card"><div class="ic-icon">🌇</div><div class="ic-label">Commande après</div><div class="ic-value">12h00</div><div class="ic-sub">Expédition le lendemain matin</div></div>
        <div class="info-card"><div class="ic-icon">📅</div><div class="ic-label">Week-end</div><div class="ic-value">Lundi</div><div class="ic-sub">Expédition le lundi</div></div>
      </div>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question"><span class="faq-q-text">Comment suivre ma commande en temps réel ?</span><span class="faq-q-icon">+</span></div>
    <div class="faq-answer">
      <div class="steps">
        <div class="step"><div class="step-num">1</div><div class="step-body"><strong>E-mail de confirmation</strong>Reçu immédiatement après votre commande avec le récapitulatif complet.</div></div>
        <div class="step"><div class="step-num">2</div><div class="step-body"><strong>E-mail d'expédition</strong>Envoyé dès que votre colis quitte notre entrepôt, avec votre numéro de suivi.</div></div>
        <div class="step"><div class="step-num">3</div><div class="step-body"><strong>Suivi en temps réel</strong>Cliquez sur le lien de suivi pour voir la position exacte de votre colis sur le site du transporteur.</div></div>
        <div class="step"><div class="step-num">4</div><div class="step-body"><strong>Notification de livraison</strong>Un SMS ou e-mail vous avertit le jour de la livraison avec le créneau horaire estimé.</div></div>
      </div>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question"><span class="faq-q-text">Livrez-vous en point relais ou en consigne ?</span><span class="faq-q-icon">+</span></div>
    <div class="faq-answer">
      <p>La livraison en point relais est disponible pour les <strong>commandes standards uniquement</strong> (épicerie sèche, conserves, boissons…).</p>
      <div class="callout amber"><span class="callout-icon">⚠️</span><div>Les <strong>commandes en chaîne du froid</strong> (viandes fraîches, produits frais) <strong>ne peuvent pas être livrées en point relais</strong>. Elles nécessitent impérativement une livraison à domicile avec signature pour garantir la chaîne du froid.</div></div>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question"><span class="faq-q-text">Que se passe-t-il si je suis absent lors de la livraison ?</span><span class="faq-q-icon">+</span></div>
    <div class="faq-answer">
      <p>En cas d'absence :</p>
      <ul>
        <li><strong>Colis standard :</strong> le livreur laisse un avis de passage et le colis est déposé dans votre boîte aux lettres ou chez un voisin. Vous recevez un avis de passage pour récupérer votre colis en point relais sous 10 jours.</li>
        <li><strong>Chaîne du froid ❄️ :</strong> une deuxième tentative de livraison est effectuée. Si vous êtes absent une seconde fois, le colis est <strong>retourné à l'entrepôt</strong>. Les produits frais ne pouvant être stockés en point relais, un remboursement ou réexpédition est proposé.</li>
      </ul>
      <div class="callout blue"><span class="callout-icon">💡</span><div>Nous recommandons de choisir une adresse de livraison où vous êtes <strong>certain d'être présent</strong> pour les commandes en chaîne du froid.</div></div>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question"><span class="faq-q-text">Comment obtenir la livraison gratuite ?</span><span class="faq-q-icon">+</span></div>
    <div class="faq-answer">
      <div class="free-banner">
        <div class="free-banner-icon">🎁</div>
        <div class="free-banner-text">
          <strong>Livraison 100% gratuite dès 149€ d'achat</strong>
          <span>Valable pour tous les pays, tous les types de livraison — même la chaîne du froid ❄️</span>
        </div>
      </div>
      <p>Le montant restant pour débloquer la livraison gratuite est affiché en temps réel dans votre panier avec une barre de progression. Plus votre panier grossit, plus vous vous rapprochez de la livraison offerte !</p>
    </div>
  </div>
</div>

<!-- ── SECTION 2 : CHAÎNE DU FROID ── -->
<div class="faq-section" id="coldchain">
  <div class="faq-section-header">
    <div class="faq-section-icon">❄️</div>
    <div>
      <div class="faq-section-title">Livraison Chaîne du Froid</div>
      <div class="faq-section-subtitle">Transport réfrigéré pour viandes et produits frais</div>
    </div>
  </div>

  <div class="faq-item open">
    <div class="faq-question"><span class="faq-q-text">Qu'est-ce que la livraison en chaîne du froid ?</span><span class="faq-q-icon">+</span></div>
    <div class="faq-answer">
      <p>La <strong>chaîne du froid</strong> est un système de transport réfrigéré qui maintient vos produits à une température contrôlée (entre 0°C et 4°C) depuis notre entrepôt jusqu'à votre porte, sans aucune rupture.</p>
      <div class="cold-flow">
        <div class="cold-step"><div class="cs-icon">🏭</div><div class="cs-label">Entrepôt réfrigéré</div></div>
        <div class="cold-arrow">→</div>
        <div class="cold-step"><div class="cs-icon">📦</div><div class="cs-label">Emballage isotherme + pains de glace</div></div>
        <div class="cold-arrow">→</div>
        <div class="cold-step"><div class="cs-icon">🚐</div><div class="cs-label">Transporteur spécialisé réfrigéré</div></div>
        <div class="cold-arrow">→</div>
        <div class="cold-step"><div class="cs-icon">🏠</div><div class="cs-label">Livraison à domicile</div></div>
      </div>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question"><span class="faq-q-text">Quels produits sont expédiés en chaîne du froid ?</span><span class="faq-q-icon">+</span></div>
    <div class="faq-answer">
      <p>La chaîne du froid est obligatoire pour :</p>
      <ul>
        <li>🥩 <strong>Viandes halal fraîches</strong> — agneau, veau, bœuf, poulet, volaille, dinde</li>
        <li>🥓 <strong>Charcuterie halal</strong> — bacon de dinde, délice de poulet, saucisses fumées</li>
        <li>🥗 <strong>Mezze & spécialités fraîches</strong> — baba ganoush, haydari, fava, houmous, börek</li>
        <li>🧀 <strong>Fromages frais</strong> et produits laitiers frais</li>
        <li>🌿 <strong>Produits frais</strong> signalés par le badge ❄️ sur la fiche produit</li>
      </ul>
      <div class="callout blue"><span class="callout-icon">ℹ️</span><div>Si votre panier contient au moins un produit en chaîne du froid, <strong>l'ensemble de la commande est expédiée en chaîne du froid</strong>. C'est pourquoi nous recommandons de regrouper vos achats frais dans une seule commande.</div></div>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question"><span class="faq-q-text">La chaîne du froid garantit-elle la fraîcheur des produits à l'arrivée ?</span><span class="faq-q-icon">+</span></div>
    <div class="faq-answer">
      <p>Oui, absolument. Votre commande est préparée dans notre chambre froide, emballée avec des <strong>pains de glace réutilisables</strong> dans une boîte isotherme alimentaire certifiée. Ce système maintient vos produits à température idéale pendant <strong>48h minimum</strong>, au-delà des délais de livraison.</p>
      <div class="callout green"><span class="callout-icon">✅</span><div>À réception, vos produits doivent être froids au toucher. Si ce n'est pas le cas, photographiez immédiatement les produits et contactez-nous pour un remboursement ou remplacement sans discussion.</div></div>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question"><span class="faq-q-text">Que faire dès réception de ma commande fraîche ?</span><span class="faq-q-icon">+</span></div>
    <div class="faq-answer">
      <div class="steps">
        <div class="step"><div class="step-num">1</div><div class="step-body"><strong>Vérifier la température</strong>Les produits doivent être froids. Si le colis est chaud ou tiède, ne consommez pas les produits et contactez-nous immédiatement.</div></div>
        <div class="step"><div class="step-num">2</div><div class="step-body"><strong>Mettre au réfrigérateur</strong>Réfrigérez immédiatement tous les produits frais dès l'ouverture du colis.</div></div>
        <div class="step"><div class="step-num">3</div><div class="step-body"><strong>Congélation possible</strong>Les viandes fraîches peuvent être congelées directement si vous ne les consommez pas dans les jours suivants. Respectez les DLC indiquées.</div></div>
      </div>
    </div>
  </div>
</div>

<!-- ── SECTION 3 : COMMANDES ── -->
<div class="faq-section" id="commandes">
  <div class="faq-section-header">
    <div class="faq-section-icon">📦</div>
    <div>
      <div class="faq-section-title">Commandes</div>
      <div class="faq-section-subtitle">Passer, modifier ou annuler une commande</div>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question"><span class="faq-q-text">Comment passer une commande sur Mondimart ?</span><span class="faq-q-icon">+</span></div>
    <div class="faq-answer">
      <div class="steps">
        <div class="step"><div class="step-num">1</div><div class="step-body"><strong>Parcourez notre catalogue</strong>Naviguez par catégorie ou utilisez la barre de recherche pour trouver vos produits préférés.</div></div>
        <div class="step"><div class="step-num">2</div><div class="step-body"><strong>Ajoutez au panier</strong>Cliquez sur "Ajouter au panier". Le récapitulatif s'affiche dans le tiroir de panier à droite.</div></div>
        <div class="step"><div class="step-num">3</div><div class="step-body"><strong>Validez votre panier</strong>Vérifiez vos articles, quantités et le mode de livraison estimé.</div></div>
        <div class="step"><div class="step-num">4</div><div class="step-body"><strong>Checkout sécurisé</strong>Renseignez votre adresse, choisissez le mode de livraison et payez en toute sécurité.</div></div>
        <div class="step"><div class="step-num">5</div><div class="step-body"><strong>Confirmation immédiate</strong>Vous recevez un e-mail de confirmation avec le détail de votre commande.</div></div>
      </div>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question"><span class="faq-q-text">Puis-je modifier ma commande après validation ?</span><span class="faq-q-icon">+</span></div>
    <div class="faq-answer">
      <p>Vous pouvez modifier votre commande <strong>dans les 2 heures suivant la confirmation</strong>, avant le début de la préparation.</p>
      <p>Modifications possibles : quantité, adresse de livraison, suppression d'articles.</p>
      <div class="callout amber"><span class="callout-icon">⚠️</span><div>Passé ce délai, la commande est en cours de préparation et ne peut plus être modifiée. Contactez-nous le plus rapidement possible via WhatsApp pour maximiser vos chances.</div></div>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question"><span class="faq-q-text">Puis-je annuler ma commande ?</span><span class="faq-q-icon">+</span></div>
    <div class="faq-answer">
      <p>L'annulation est possible <strong>avant l'expédition</strong> (généralement dans les 2 heures suivant la commande). Le remboursement intégral est effectué sous 3 à 5 jours ouvrés selon votre banque.</p>
      <div class="callout red"><span class="callout-icon">🚫</span><div>Les commandes contenant des <strong>produits en chaîne du froid</strong> ne peuvent pas être annulées une fois la préparation lancée, car les produits frais sont préparés spécifiquement pour votre commande.</div></div>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question"><span class="faq-q-text">Un produit de ma commande est manquant ou incorrect. Que faire ?</span><span class="faq-q-icon">+</span></div>
    <div class="faq-answer">
      <div class="steps">
        <div class="step"><div class="step-num">1</div><div class="step-body"><strong>Photographiez</strong>Prenez des photos du colis à l'ouverture, des étiquettes et des produits reçus.</div></div>
        <div class="step"><div class="step-num">2</div><div class="step-body"><strong>Contactez-nous sous 48h</strong>Envoyez-nous les photos et votre numéro de commande par e-mail ou WhatsApp.</div></div>
        <div class="step"><div class="step-num">3</div><div class="step-body"><strong>Résolution rapide</strong>Nous procédons au remboursement du produit manquant ou à une réexpédition selon votre préférence, sous 24h.</div></div>
      </div>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question"><span class="faq-q-text">Est-il possible de commander en gros ou pour un restaurant ?</span><span class="faq-q-icon">+</span></div>
    <div class="faq-answer">
      <p>Oui ! Mondimart propose des tarifs professionnels pour les restaurants, épiceries, boucheries et revendeurs. Contactez notre équipe commerciale pour obtenir un devis personnalisé avec des tarifs dégressifs selon les volumes.</p>
      <div class="callout green"><span class="callout-icon">🏢</span><div>Pour toute commande professionnelle ou devis grossiste, écrivez-nous à <strong>pro@mondimart.fr</strong> ou via le formulaire de contact.</div></div>
    </div>
  </div>
</div>

<!-- ── SECTION 4 : PAIEMENT ── -->
<div class="faq-section" id="paiement">
  <div class="faq-section-header">
    <div class="faq-section-icon">💳</div>
    <div>
      <div class="faq-section-title">Paiement & Sécurité</div>
      <div class="faq-section-subtitle">Moyens de paiement acceptés et sécurité</div>
    </div>
  </div>

  <div class="faq-item open">
    <div class="faq-question"><span class="faq-q-text">Quels moyens de paiement acceptez-vous ?</span><span class="faq-q-icon">+</span></div>
    <div class="faq-answer">
      <p>Nous acceptons tous les principaux moyens de paiement :</p>
      <div class="payment-methods">
        <span class="pm">💳 Visa / Mastercard</span>
        <span class="pm">💳 American Express</span>
        <span class="pm">🍎 Apple Pay</span>
        <span class="pm">🤖 Google Pay</span>
        <span class="pm">🅿️ PayPal</span>
        <span class="pm">🏦 Virement bancaire</span>
      </div>
      <div class="callout green"><span class="callout-icon">🔒</span><div>Tous les paiements sont <strong>100% sécurisés</strong> via Shopify Payments (certifié PCI-DSS Level 1) et chiffrés en SSL 256 bits. Vos données bancaires ne sont jamais stockées sur nos serveurs.</div></div>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question"><span class="faq-q-text">Mes données bancaires sont-elles en sécurité ?</span><span class="faq-q-icon">+</span></div>
    <div class="faq-answer">
      <p>Absolument. Mondimart utilise la plateforme <strong>Shopify</strong>, certifiée <strong>PCI-DSS Level 1</strong> — le plus haut niveau de sécurité pour les transactions en ligne. Vos données bancaires sont chiffrées et ne transitent jamais par nos serveurs.</p>
      <p>Nous ne stockons aucune information de carte bancaire. Chaque paiement est traité directement par notre prestataire de paiement agréé.</p>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question"><span class="faq-q-text">Quand est-je débité ?</span><span class="faq-q-icon">+</span></div>
    <div class="faq-answer">
      <p>Votre carte est débitée <strong>immédiatement lors de la validation de votre commande</strong>. Vous recevez une confirmation de paiement par e-mail instantanément.</p>
      <p>En cas d'annulation de commande, le remboursement apparaît sur votre relevé bancaire sous <strong>3 à 5 jours ouvrés</strong> selon votre banque.</p>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question"><span class="faq-q-text">Puis-je payer en plusieurs fois ?</span><span class="faq-q-icon">+</span></div>
    <div class="faq-answer">
      <p>Le paiement en plusieurs fois n'est pas disponible pour le moment. Cependant, nous travaillons à l'intégration de cette option pour les commandes supérieures à un certain montant.</p>
      <p>Pensez à cumuler vos achats pour atteindre le seuil de livraison gratuite à 149€ 🎁</p>
    </div>
  </div>
</div>

<!-- ── SECTION 5 : PRODUITS ── -->
<div class="faq-section" id="produits">
  <div class="faq-section-header">
    <div class="faq-section-icon">🛍️</div>
    <div>
      <div class="faq-section-title">Produits & Qualité</div>
      <div class="faq-section-subtitle">Origine, certification halal et fraîcheur</div>
    </div>
  </div>

  <div class="faq-item open">
    <div class="faq-question"><span class="faq-q-text">Vos viandes sont-elles vraiment certifiées halal ?</span><span class="faq-q-icon">+</span></div>
    <div class="faq-answer">
      <div class="halal-box">
        <div class="halal-badge">☪️</div>
        <div class="halal-text">
          <strong>Certification Halal garantie</strong>
          <p>Toutes nos viandes portant le badge <strong>Halal Certifié</strong> proviennent d'abattoirs agréés et contrôlés par des organismes de certification halal reconnus en France et en Europe. Les certificats sont disponibles sur demande.</p>
        </div>
      </div>
      <p>Nos produits halal respectent strictement les normes d'abattage islamique (zabiha) : absence d'étourdissement électrique pour certaines références, saignée complète, absence de contamination avec des produits non-halal.</p>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question"><span class="faq-q-text">D'où viennent vos produits ?</span><span class="faq-q-icon">+</span></div>
    <div class="faq-answer">
      <p>Mondimart propose des produits authentiques soigneusement sélectionnés auprès de producteurs et distributeurs de confiance :</p>
      <ul>
        <li>🇹🇷 <strong>Cuisine turque</strong> — Ülker, Eti, Bingo, Torku, Baltat, Bebeto, Bizim, Eker, Tamek…</li>
        <li>🇲🇦🇩🇿🇹🇳 <strong>Cuisine maghrébine</strong> — produits authentiques d'Algérie, du Maroc et de Tunisie</li>
        <li>🇮🇹 <strong>Cuisine italienne</strong> — Barilla, Mutti, Ferrero, Lavazza, Balconi…</li>
        <li>🌏 <strong>Cuisine asiatique</strong> — Ayam Brand, Kikkoman, Yeo's, Lee Kum Kee…</li>
        <li>🥩 <strong>Viandes halal</strong> — agneau, veau, volaille issus d'élevages contrôlés</li>
        <li>🍳 <strong>Karaca</strong> — marque de vaisselle et ustensiles de cuisine premium</li>
      </ul>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question"><span class="faq-q-text">Les dates de péremption sont-elles respectées ?</span><span class="faq-q-icon">+</span></div>
    <div class="faq-answer">
      <p>Oui, nous garantissons que tous les produits envoyés ont une <strong>DLC (Date Limite de Consommation) ou DLUO</strong> suffisamment éloignée pour vous laisser le temps de les consommer.</p>
      <ul>
        <li><strong>Produits frais (chaîne du froid) :</strong> minimum 5 jours de DLC restants à réception</li>
        <li><strong>Épicerie sèche :</strong> minimum 3 mois de DLUO restants</li>
      </ul>
      <p>Si vous recevez un produit proche de la date de péremption, contactez-nous immédiatement pour un remplacement.</p>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question"><span class="faq-q-text">Proposez-vous des produits bio, sans gluten ou vegan ?</span><span class="faq-q-icon">+</span></div>
    <div class="faq-answer">
      <p>Oui ! Vous trouverez des produits adaptés à tous les régimes alimentaires dans notre boutique :</p>
      <ul>
        <li>🌿 <strong>Bio certifié</strong> — labellisés Agriculture Biologique</li>
        <li>🌾 <strong>Sans gluten</strong> — adaptés aux personnes intolérantes ou cœliaques</li>
        <li>🥦 <strong>Vegan</strong> — sans aucun produit d'origine animale</li>
      </ul>
      <p>Utilisez les filtres de collection dans notre boutique pour retrouver facilement ces produits.</p>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question"><span class="faq-q-text">Un produit que je cherche n'est pas disponible. Que faire ?</span><span class="faq-q-icon">+</span></div>
    <div class="faq-answer">
      <p>Notre catalogue compte plus de 3 400 références et s'enrichit régulièrement. Si un produit spécifique n'est pas disponible :</p>
      <ul>
        <li>Utilisez le bouton <strong>"Être notifié de la disponibilité"</strong> sur la fiche produit (si disponible)</li>
        <li>Contactez-nous en nous indiquant le nom du produit — nous ferons notre possible pour l'ajouter à notre assortiment</li>
      </ul>
    </div>
  </div>
</div>

<!-- ── SECTION 6 : RETOURS ── -->
<div class="faq-section" id="retours">
  <div class="faq-section-header">
    <div class="faq-section-icon">↩️</div>
    <div>
      <div class="faq-section-title">Retours & Remboursements</div>
      <div class="faq-section-subtitle">Politique de retour et procédure de réclamation</div>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question"><span class="faq-q-text">Quelle est votre politique de retour ?</span><span class="faq-q-icon">+</span></div>
    <div class="faq-answer">
      <p>Nous acceptons les retours dans les <strong>14 jours suivant la réception</strong> pour les produits non ouverts et non périssables (épicerie sèche, vaisselle Karaca, produits d'entretien…).</p>
      <div class="callout red"><span class="callout-icon">🚫</span><div><strong>Produits non retournables :</strong> Les produits alimentaires frais (chaîne du froid), les produits ouverts et les produits personnalisés ne peuvent pas être retournés pour des raisons d'hygiène et de sécurité alimentaire.</div></div>
      <p>Les frais de retour sont à la charge du client, sauf en cas d'erreur de notre part ou de produit défectueux.</p>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question"><span class="faq-q-text">Mon colis est arrivé endommagé. Que faire ?</span><span class="faq-q-icon">+</span></div>
    <div class="faq-answer">
      <div class="steps">
        <div class="step"><div class="step-num">1</div><div class="step-body"><strong>À la livraison</strong>En présence du livreur, notez les dommages sur le bon de livraison et refusez le colis si l'état est vraiment problématique.</div></div>
        <div class="step"><div class="step-num">2</div><div class="step-body"><strong>Photographiez tout</strong>Le colis fermé, ouvert, les produits endommagés, les étiquettes et le bon de livraison.</div></div>
        <div class="step"><div class="step-num">3</div><div class="step-body"><strong>Contactez-nous dans les 48h</strong>Envoyez-nous les photos avec votre numéro de commande.</div></div>
        <div class="step"><div class="step-num">4</div><div class="step-body"><strong>Remboursement ou renvoi</strong>Nous traitons votre réclamation sous 24h et procédons au remboursement ou à la réexpédition selon votre choix.</div></div>
      </div>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question"><span class="faq-q-text">Combien de temps prend le remboursement ?</span><span class="faq-q-icon">+</span></div>
    <div class="faq-answer">
      <p>Une fois votre réclamation acceptée, le remboursement est initié sous <strong>24 à 48h ouvrées</strong>. Le délai d'apparition sur votre relevé bancaire dépend ensuite de votre banque :</p>
      <ul>
        <li>Carte bancaire : <strong>3 à 5 jours ouvrés</strong></li>
        <li>PayPal : <strong>1 à 3 jours ouvrés</strong></li>
        <li>Virement : <strong>2 à 4 jours ouvrés</strong></li>
      </ul>
    </div>
  </div>
</div>

<!-- ── SECTION 7 : COMPTE ── -->
<div class="faq-section" id="compte">
  <div class="faq-section-header">
    <div class="faq-section-icon">👤</div>
    <div>
      <div class="faq-section-title">Mon Compte</div>
      <div class="faq-section-subtitle">Gestion de votre compte client</div>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question"><span class="faq-q-text">Est-il obligatoire de créer un compte pour commander ?</span><span class="faq-q-icon">+</span></div>
    <div class="faq-answer">
      <p>Non, vous pouvez commander en tant qu'invité sans créer de compte. Cependant, créer un compte vous permet de :</p>
      <ul>
        <li>📋 Consulter l'historique de toutes vos commandes</li>
        <li>🔄 Renouveler facilement vos commandes précédentes</li>
        <li>📍 Sauvegarder plusieurs adresses de livraison</li>
        <li>🏷️ Accéder aux offres exclusives réservées aux membres</li>
        <li>⚡ Commander plus rapidement sans ressaisir vos informations</li>
      </ul>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question"><span class="faq-q-text">J'ai oublié mon mot de passe. Comment le réinitialiser ?</span><span class="faq-q-icon">+</span></div>
    <div class="faq-answer">
      <div class="steps">
        <div class="step"><div class="step-num">1</div><div class="step-body"><strong>Cliquez sur "Se connecter"</strong>En haut à droite de la page d'accueil.</div></div>
        <div class="step"><div class="step-num">2</div><div class="step-body"><strong>"Mot de passe oublié ?"</strong>Cliquez sur ce lien sous le formulaire de connexion.</div></div>
        <div class="step"><div class="step-num">3</div><div class="step-body"><strong>E-mail de réinitialisation</strong>Saisissez votre adresse e-mail — vous recevrez un lien valable 24h pour créer un nouveau mot de passe.</div></div>
      </div>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question"><span class="faq-q-text">Comment modifier mon adresse ou mes informations personnelles ?</span><span class="faq-q-icon">+</span></div>
    <div class="faq-answer">
      <p>Connectez-vous à votre compte, puis accédez à la section <strong>"Mon profil"</strong> ou <strong>"Adresses"</strong> pour modifier vos informations personnelles, adresses de livraison et préférences de contact.</p>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question"><span class="faq-q-text">Comment supprimer mon compte ?</span><span class="faq-q-icon">+</span></div>
    <div class="faq-answer">
      <p>Pour supprimer votre compte et toutes vos données personnelles (conformément au RGPD), contactez-nous par e-mail en indiquant votre adresse e-mail associée au compte. Nous procéderons à la suppression dans les <strong>30 jours</strong> suivant votre demande.</p>
    </div>
  </div>
</div>

<!-- CONTACT SECTION -->
<div class="contact-section">
  <h2>💬 Vous n'avez pas trouvé votre réponse ?</h2>
  <p>Notre équipe est à votre disposition du <strong>lundi au samedi, de 9h à 18h</strong>.<br>Nous répondons généralement dans l'heure pendant les heures d'ouverture.</p>
  <div class="contact-grid">
    <div class="contact-card">
      <div class="cc-icon">📧</div>
      <div class="cc-label">E-mail</div>
      <div class="cc-value">contact@mondimart.fr</div>
    </div>
    <div class="contact-card">
      <div class="cc-icon">💬</div>
      <div class="cc-label">WhatsApp</div>
      <div class="cc-value">Réponse en moins d'1h</div>
    </div>
    <div class="contact-card">
      <div class="cc-icon">⏰</div>
      <div class="cc-label">Horaires</div>
      <div class="cc-value">Lun–Sam 9h–18h</div>
    </div>
  </div>
  <a href="/pages/contact" class="contact-btn">✉️ Nous contacter</a>
</div>

</div>

<script>
(function(){
  // Accordion
  document.querySelectorAll('.faq-question').forEach(function(q){
    q.addEventListener('click',function(){
      var item=this.parentElement;
      var open=item.classList.contains('open');
      document.querySelectorAll('.faq-item').forEach(function(i){i.classList.remove('open')});
      if(!open) item.classList.add('open');
    });
  });
  // Nav scroll
  window.scrollTo=function(id){
    var el=document.getElementById(id);
    if(el) el.scrollIntoView({behavior:'smooth',block:'start'});
    document.querySelectorAll('.faq-nav-btn').forEach(function(b){b.classList.remove('active')});
    event.currentTarget.classList.add('active');
  };
  // Highlight nav on scroll
  var sections=document.querySelectorAll('.faq-section');
  var btns=document.querySelectorAll('.faq-nav-btn');
  window.addEventListener('scroll',function(){
    var pos=window.scrollY+120;
    sections.forEach(function(s,i){
      if(s.offsetTop<=pos&&(s.offsetTop+s.offsetHeight)>pos){
        btns.forEach(function(b){b.classList.remove('active')});
        if(btns[i]) btns[i].classList.add('active');
      }
    });
  });
})();
</script>
`;

const checkRes = await fetch(`${BASE}/pages.json?limit=250`, { headers: H });
const checkData = await checkRes.json();
const existing = checkData.pages?.find(p =>
  p.handle === 'faq' || p.title.toLowerCase().includes('faq')
);

let result;
if (existing) {
  console.log(`Mevcut FAQ güncelleniyor (ID: ${existing.id})...`);
  const r = await fetch(`${BASE}/pages/${existing.id}.json`, {
    method: 'PUT', headers: H,
    body: JSON.stringify({ page: { id: existing.id, title: 'FAQ — Questions Fréquentes', body_html: faqHTML, published: true } })
  });
  result = await r.json();
} else {
  console.log('Yeni FAQ sayfası oluşturuluyor...');
  const r = await fetch(`${BASE}/pages.json`, {
    method: 'POST', headers: H,
    body: JSON.stringify({ page: { title: 'FAQ — Questions Fréquentes', handle: 'faq', body_html: faqHTML, published: true } })
  });
  result = await r.json();
}

if (result.page) {
  console.log(`\n✅ FAQ sayfası yayında!`);
  console.log(`   URL: https://pftzey-y0.myshopify.com/pages/${result.page.handle}`);
  console.log(`   Admin: https://pftzey-y0.myshopify.com/admin/pages/${result.page.id}`);
} else {
  console.error('❌ Hata:', JSON.stringify(result, null, 2));
}
