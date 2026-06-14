// ============================================================
// MONDIMART - FAQ Sayfası Oluştur
// Kullanım: SHOPIFY_TOKEN='...' node mondimart-faq.mjs
// ============================================================

const SHOPIFY_TOKEN = process.env.SHOPIFY_TOKEN;
const SHOP = 'pftzey-y0.myshopify.com';
const BASE = `https://${SHOP}/admin/api/2024-04`;
const H = { 'X-Shopify-Access-Token': SHOPIFY_TOKEN, 'Content-Type': 'application/json' };

if (!SHOPIFY_TOKEN) { console.error('SHOPIFY_TOKEN gerekli'); process.exit(1); }

const faqHTML = `
<style>
  .faq-hero {
    background: linear-gradient(135deg, #1a4a1a 0%, #2d7a2d 60%, #4caf50 100%);
    color: #fff;
    text-align: center;
    padding: 52px 24px 44px;
    border-radius: 16px;
    margin-bottom: 40px;
  }
  .faq-hero h1 {
    font-size: 2rem;
    font-weight: 800;
    margin: 0 0 10px;
    letter-spacing: -.5px;
  }
  .faq-hero p {
    font-size: 1.05rem;
    opacity: .85;
    margin: 0;
  }
  .faq-section {
    margin-bottom: 36px;
  }
  .faq-section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1.15rem;
    font-weight: 700;
    color: #1a4a1a;
    border-bottom: 2px solid #e0f0e0;
    padding-bottom: 10px;
    margin-bottom: 18px;
  }
  .faq-item {
    background: #f8fdf8;
    border: 1px solid #d8ead8;
    border-radius: 12px;
    margin-bottom: 12px;
    overflow: hidden;
    transition: box-shadow .2s;
  }
  .faq-item:hover {
    box-shadow: 0 4px 16px rgba(44,107,44,.10);
  }
  .faq-question {
    font-weight: 600;
    font-size: .97rem;
    color: #1a4a1a;
    padding: 16px 20px;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    user-select: none;
  }
  .faq-question::after {
    content: '+';
    font-size: 1.4rem;
    font-weight: 300;
    color: #4caf50;
    flex-shrink: 0;
    transition: transform .25s;
    line-height: 1;
  }
  .faq-item.open .faq-question::after {
    transform: rotate(45deg);
  }
  .faq-answer {
    display: none;
    padding: 0 20px 18px;
    font-size: .93rem;
    color: #444;
    line-height: 1.7;
    border-top: 1px solid #e0ead0;
  }
  .faq-item.open .faq-answer {
    display: block;
  }
  .delivery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 14px;
    margin: 14px 0 6px;
  }
  .delivery-card {
    background: #fff;
    border: 1.5px solid #d0e8d0;
    border-radius: 10px;
    padding: 14px 16px;
    text-align: center;
  }
  .delivery-card .flag { font-size: 1.6rem; margin-bottom: 5px; }
  .delivery-card .country { font-weight: 700; font-size: .92rem; color: #1a4a1a; }
  .delivery-card .time { font-size: .88rem; color: #555; margin: 3px 0; }
  .delivery-card .price { font-weight: 700; color: #2d7a2d; font-size: .95rem; }
  .cold-badge {
    display: inline-flex; align-items: center; gap: 5px;
    background: #e3f2fd; color: #1565c0;
    border: 1px solid #b3d9f5; border-radius: 20px;
    padding: 3px 10px; font-size: .82rem; font-weight: 600;
    margin-top: 6px;
  }
  .free-badge {
    display: inline-block;
    background: #e8f5e9; color: #2e7d32;
    border: 1px solid #a5d6a7; border-radius: 20px;
    padding: 4px 14px; font-size: .88rem; font-weight: 700;
    margin-top: 10px;
  }
  .info-box {
    background: #fff8e1;
    border-left: 4px solid #ffc107;
    border-radius: 0 10px 10px 0;
    padding: 12px 16px;
    font-size: .9rem;
    color: #555;
    margin-top: 10px;
  }
  .contact-box {
    background: linear-gradient(135deg, #e8f5e9, #f1f8e9);
    border: 1.5px solid #a5d6a7;
    border-radius: 14px;
    padding: 24px;
    text-align: center;
    margin-top: 36px;
  }
  .contact-box h3 { color: #1a4a1a; margin: 0 0 8px; }
  .contact-box p { color: #555; font-size: .93rem; margin: 0 0 14px; }
  .contact-btn {
    display: inline-block;
    background: #2d7a2d; color: #fff;
    padding: 11px 28px; border-radius: 8px;
    font-weight: 700; font-size: .95rem;
    text-decoration: none;
  }
</style>

<div class="faq-hero">
  <h1>❓ Questions Fréquentes</h1>
  <p>Tout ce que vous devez savoir sur Mondimart et vos commandes</p>
</div>

<!-- LIVRAISON -->
<div class="faq-section">
  <div class="faq-section-title">🚚 Livraison & Délais</div>

  <div class="faq-item open">
    <div class="faq-question">Comment sont expédiées mes commandes ?</div>
    <div class="faq-answer">
      <p>Toutes vos commandes sont préparées avec soin depuis notre entrepôt et expédiées selon le type de produits :</p>
      <ul>
        <li><strong>Produits standards</strong> (épicerie sèche, conserves, boissons…) : expédition en colissimo ou équivalent.</li>
        <li><strong>Produits en chaîne du froid</strong> ❄️ (viandes halal, produits frais) : expédition via transporteur spécialisé avec emballage isotherme et pains de glace pour garantir la fraîcheur.</li>
      </ul>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question">Quels sont les délais de livraison ?</div>
    <div class="faq-answer">
      <div class="delivery-grid">
        <div class="delivery-card">
          <div class="flag">🇫🇷</div>
          <div class="country">France</div>
          <div class="time">⏱ 24h après expédition</div>
          <div class="price">🚚 8€ | ❄️ 18€</div>
        </div>
        <div class="delivery-card">
          <div class="flag">🇧🇪🇱🇺</div>
          <div class="country">Belgique / Luxembourg</div>
          <div class="time">⏱ 24–48h (standard)<br>⏱ 24–48h ❄️ chaîne du froid</div>
          <div class="price">🚚 18€ | ❄️ 24€</div>
        </div>
        <div class="delivery-card">
          <div class="flag">🇪🇸</div>
          <div class="country">Espagne</div>
          <div class="time">⏱ 2–4 jours (standard)<br>⏱ 24–48h ❄️ chaîne du froid</div>
          <div class="price">🚚 18€ | ❄️ 24€</div>
        </div>
        <div class="delivery-card">
          <div class="flag">🌍</div>
          <div class="country">Reste de l'Europe</div>
          <div class="time">⏱ 2–4 jours</div>
          <div class="price">🚚 18€</div>
        </div>
      </div>
      <div class="free-badge">🎁 Livraison GRATUITE dès 149€ d'achat</div>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question">Qu'est-ce que la livraison en chaîne du froid ❄️ ?</div>
    <div class="faq-answer">
      <p>La chaîne du froid est un mode de transport spécialisé qui maintient vos produits à température contrôlée tout au long de l'acheminement. Elle est obligatoire pour :</p>
      <ul>
        <li>🥩 Les viandes halal fraîches (agneau, veau, volaille, bœuf)</li>
        <li>🧀 Les produits laitiers frais</li>
        <li>🥗 Les mezze et spécialités fraîches</li>
      </ul>
      <p>Vos produits sont emballés avec des pains de glace dans des colis isothermes garantissant la fraîcheur pendant 24 à 48h de transport.</p>
      <div class="info-box">
        ℹ️ Si votre panier contient au moins un produit en chaîne du froid, <strong>toute la commande est expédiée en chaîne du froid</strong> pour garantir la qualité de l'ensemble.
      </div>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question">Puis-je suivre ma commande ?</div>
    <div class="faq-answer">
      <p>Oui ! Dès que votre commande est expédiée, vous recevez un e-mail avec votre numéro de suivi. Vous pouvez alors suivre votre colis en temps réel sur le site du transporteur.</p>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question">Comment obtenir la livraison gratuite ?</div>
    <div class="faq-answer">
      <p>La livraison est <strong>offerte dès 149€ d'achat</strong> — pour tous les pays et pour toutes les commandes, y compris les commandes en chaîne du froid ❄️.</p>
      <p>Le montant restant pour atteindre la livraison gratuite est affiché directement dans votre panier.</p>
      <div class="free-badge">🎁 Livraison GRATUITE dès 149€</div>
    </div>
  </div>
</div>

<!-- COMMANDES -->
<div class="faq-section">
  <div class="faq-section-title">📦 Commandes & Paiement</div>

  <div class="faq-item">
    <div class="faq-question">Quels moyens de paiement acceptez-vous ?</div>
    <div class="faq-answer">
      <p>Nous acceptons les principaux moyens de paiement :</p>
      <ul>
        <li>💳 Carte bancaire (Visa, Mastercard, American Express)</li>
        <li>🍎 Apple Pay / Google Pay</li>
        <li>🅿️ PayPal</li>
      </ul>
      <p>Tous les paiements sont sécurisés et cryptés (SSL).</p>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question">Puis-je modifier ou annuler ma commande ?</div>
    <div class="faq-answer">
      <p>Vous pouvez modifier ou annuler votre commande <strong>dans les 2 heures suivant la validation</strong>, avant préparation. Contactez-nous immédiatement par e-mail ou WhatsApp si vous souhaitez effectuer un changement.</p>
      <p>Une fois la commande en cours de préparation, il n'est plus possible de la modifier.</p>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question">Mes informations personnelles sont-elles sécurisées ?</div>
    <div class="faq-answer">
      <p>Oui, absolument. Mondimart utilise la plateforme Shopify qui est certifiée PCI-DSS Level 1 (le plus haut niveau de sécurité pour les paiements en ligne). Vos données ne sont jamais revendues à des tiers.</p>
    </div>
  </div>
</div>

<!-- PRODUITS -->
<div class="faq-section">
  <div class="faq-section-title">🛍️ Produits & Qualité</div>

  <div class="faq-item">
    <div class="faq-question">Vos viandes sont-elles certifiées halal ?</div>
    <div class="faq-answer">
      <p>Oui ! Toutes nos viandes portant le label <strong>Halal Certifié</strong> sont issues d'abattoirs agréés et contrôlés par des organismes de certification reconnus. Le certificat halal est disponible sur demande pour chaque produit.</p>
      <div class="cold-badge">❄️ Expédition chaîne du froid garantie</div>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question">D'où viennent vos produits ?</div>
    <div class="faq-answer">
      <p>Mondimart est une épicerie mondiale proposant des produits authentiques de différentes origines :</p>
      <ul>
        <li>🇹🇷 Cuisine turque (Ülker, Eti, Bingo, Torku, Baltat…)</li>
        <li>🇲🇦🇩🇿🇹🇳 Cuisine maghrébine</li>
        <li>🇮🇹 Cuisine italienne (Barilla, Mutti…)</li>
        <li>🌏 Cuisine asiatique</li>
        <li>🌍 Et bien d'autres spécialités du monde entier</li>
      </ul>
    </div>
  </div>

  <div class="faq-item">
    <div class="faq-question">Que faire si un produit est manquant ou endommagé ?</div>
    <div class="faq-answer">
      <p>En cas de colis endommagé, produit manquant ou non-conforme :</p>
      <ol>
        <li>Prenez des photos du colis et des produits concernés</li>
        <li>Contactez-nous dans les <strong>48h</strong> suivant la réception</li>
        <li>Nous vous remboursons ou réexpédions le produit sans frais</li>
      </ol>
    </div>
  </div>
</div>

<!-- CONTACT -->
<div class="contact-box">
  <h3>💬 Vous n'avez pas trouvé votre réponse ?</h3>
  <p>Notre équipe est disponible du lundi au samedi, de 9h à 18h.</p>
  <a href="/pages/contact" class="contact-btn">Nous contacter →</a>
</div>

<script>
  document.querySelectorAll('.faq-question').forEach(function(q) {
    q.addEventListener('click', function() {
      var item = this.parentElement;
      var wasOpen = item.classList.contains('open');
      document.querySelectorAll('.faq-item').forEach(function(i) { i.classList.remove('open'); });
      if (!wasOpen) item.classList.add('open');
    });
  });
</script>
`;

// Mevcut FAQ sayfasını kontrol et
const checkRes = await fetch(`${BASE}/pages.json?title=FAQ&limit=5`, { headers: H });
const checkData = await checkRes.json();
const existing = checkData.pages?.find(p =>
  p.title.toLowerCase().includes('faq') || p.handle === 'faq'
);

let result;
if (existing) {
  console.log(`Mevcut FAQ sayfası güncelleniyor (ID: ${existing.id})...`);
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
  console.log(`\n✅ FAQ sayfası hazır!`);
  console.log(`   Başlık: ${result.page.title}`);
  console.log(`   URL: https://pftzey-y0.myshopify.com/pages/${result.page.handle}`);
  console.log(`   Admin: https://pftzey-y0.myshopify.com/admin/pages/${result.page.id}`);
} else {
  console.error('❌ Hata:', JSON.stringify(result));
}
