<?php
/**
 * VESTRA — Help Center / Concierge.
 * Amazon-style self-service structure (search + category tiles + articles),
 * dressed in VESTRA's premium, members-only "concierge" tone. Fully static
 * content wrapped in t() so it localises; a client-side search filters across
 * every article at once.
 */
require_once __DIR__.'/inc/i18n.php';
$PAGE = t('Help & Concierge');
$NAV  = 'help';
$META = t('VESTRA Help & Concierge — support for B2B buyers and sellers on the verified wholesale fashion marketplace: onboarding, KYB business verification, placing orders, invoicing and dispute resolution.');
$q    = trim($_GET['q'] ?? '');

/* Icon helper — tasteful gold line icons, same stroke language as the header mark. */
function help_icon(string $name): string {
  $p = [
    'start'    => '<path d="M12 3l8 4.5v9L12 21l-8-4.5v-9L12 3z"/><path d="M12 12l8-4.5M12 12v9M12 12L4 7.5"/>',
    'buying'   => '<path d="M5 7h14l-1.3 11H6.3L5 7z"/><path d="M9 7a3 3 0 0 1 6 0"/>',
    'payments' => '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18"/>',
    'selling'  => '<path d="M4 12l8-8 8 8"/><path d="M6 10v10h12V10"/><path d="M10 20v-6h4v6"/>',
    'account'  => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
    'trust'    => '<path d="M12 3l7 3v5c0 5-3.5 8-7 10-3.5-2-7-5-7-10V6l7-3z"/><path d="M9 12l2 2 4-4"/>',
    'concierge'=> '<path d="M12 3a7 7 0 0 1 7 7v4"/><path d="M4 14a8 8 0 0 1 16 0"/><rect x="3" y="14" width="4" height="6" rx="1.5"/><rect x="17" y="14" width="4" height="6" rx="1.5"/>',
  ][$name] ?? '';
  return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$p.'</svg>';
}

/* ── Content: Amazon-style categories, VESTRA-accurate articles ──────────── */
$HELP = [
  'start' => [
    'title' => t('Getting started'),
    'sub'   => t('What VESTRA is and how to join.'),
    'items' => [
      [t('What is VESTRA?'), t('VESTRA is a premium B2B wholesale marketplace for verified fashion businesses. Approved brands, distributors and retailers trade authentic branded stock with escrow-protected payments — no consumers, no tyre-kickers.')],
      [t('Who can join?'), t('Registered businesses only: brands, wholesalers, distributors and retailers with a valid company registration and VAT/Tax ID. VESTRA is not a consumer shop.')],
      [t('How do I register?'), t('Create an account, choose whether you buy or sell, and add your company details. You then upload a business document for verification. Once approved, your account is unlocked (Freischaltung).')],
      [t('Why can\'t I see seller names or full product photos yet?'), t('To protect our members, seller identities and full product imagery are visible only to registered and approved businesses. Complete your verification to unlock the full catalogue.')],
    ],
  ],
  'buying' => [
    'title' => t('Buying & orders'),
    'sub'   => t('Place orders, negotiate, track delivery.'),
    'items' => [
      [t('How do I place an order?'), t('Add items to your cart and check out. Your saved company, delivery and billing details prefill automatically — you can edit them per order. Confirm and you are done.')],
      [t('What are MOQ and pack sizes?'), t('Each listing shows its minimum order quantity (MOQ) and, where relevant, a carton or pack step. Quantities snap to these so orders always match how the seller ships.')],
      [t('Can I negotiate the price?'), t('Yes. On offer-enabled listings use "Make an offer" to propose a price and quantity. The seller can accept, counter or decline; when accepted, an invoice is issued automatically.')],
      [t('How do I track my order?'), t('Open your buyer dashboard → Orders. Each order shows a status timeline and tracking number, and you are notified at every step.')],
      [t('What are group buys (Sammelkauf)?'), t('Pool your order with other buyers to reach a volume that unlocks better pricing. Join an open pool from the Group buys page.')],
    ],
  ],
  'payments' => [
    'title' => t('Payments & escrow protection'),
    'sub'   => t('How your money is protected end to end.'),
    'items' => [
      [t('How does escrow protect me?'), t('Your payment is held securely and released to the seller only after you confirm delivery. If something goes wrong before release, VESTRA can refund you in full — your money is never handed over on trust alone.')],
      [t('Which payment methods can I use?'), t('Card payment via Stripe with escrow protection, or bank transfer against an automatic invoice. Choose whichever suits your business.')],
      [t('What fees do buyers pay?'), t('Buyers pay a fixed 3.8% escrow-protection fee on escrow orders. Bank-transfer invoicing is also available. There are no hidden charges.')],
      [t('How do refunds and disputes work?'), t('Before the funds are released you can open a dispute. If it cannot be resolved, VESTRA refunds you in full and the commission is reversed too.')],
      [t('Do I get an invoice?'), t('Yes — every order produces an automatic PDF invoice showing the seller\'s company and bank details, ready for your accounts.')],
    ],
  ],
  'selling' => [
    'title' => t('Selling on VESTRA'),
    'sub'   => t('List stock, get paid, grow.'),
    'items' => [
      [t('How do I become a seller?'), t('Register as a seller, verify your business, and start listing. Our team can also import your catalogue for you from a PDF or price list — just ask your concierge.')],
      [t('How do I get paid?'), t('In Profile → Payouts, connect your bank through Stripe. When a buyer confirms delivery, your payout (minus commission) is transferred to your bank automatically. VESTRA never sees your bank credentials.')],
      [t('What commission does VESTRA charge sellers?'), t('Tiered by membership: Starter 3.5%, Pro 3.2%, Elite 2.8%. The rate is only applied to completed sales.')],
      [t('What are the membership tiers?'), t('Starter, Pro and Elite differ in monthly listing quota and commission rate. A one-time onboarding fee applies. Compare them on the Membership page.')],
      [t('How do I manage my orders?'), t('Your seller dashboard → Orders lets you confirm payment, ship with a tracking number, and mark orders complete. Buyers are notified at each step.')],
    ],
  ],
  'account' => [
    'title' => t('Your account'),
    'sub'   => t('Profile, documents, security, notifications.'),
    'items' => [
      [t('How do I update my company or bank details?'), t('Open your dashboard → Profile. Company, VAT, address and bank details for invoices can all be edited there at any time.')],
      [t('How does verification work?'), t('Upload the document requested in your dashboard. Our team reviews it and unlocks your account. You can see the status of each request in real time.')],
      [t('How do I change or reset my password?'), t('Change it in Profile. If you are locked out, use "Forgot password" on the sign-in page to receive a reset link by email.')],
      [t('How do I turn on notifications?'), t('Enable notifications from the app box on the homepage. You will then be pinged instantly about offers, orders and messages — even when the site is closed.')],
      [t('Can I use VESTRA in my language?'), t('Yes. Switch language any time from the selector in the top bar. VESTRA is available in several European languages.')],
    ],
  ],
  'trust' => [
    'title' => t('Trust & safety'),
    'sub'   => t('Why VESTRA is a safe place to trade.'),
    'items' => [
      [t('Are members verified?'), t('Every business is checked before it receives full access. You only ever trade with verified brands, distributors and retailers.')],
      [t('What is the escrow guarantee?'), t('Money moves only when both sides are protected: the buyer\'s funds are secured up front and released to the seller after delivery is confirmed.')],
      [t('Why should I keep everything on-platform?'), t('Keeping messages and payments on VESTRA is what makes the escrow guarantee possible. For your protection, attempts to share off-platform contact or bank details in chat are blocked.')],
      [t('How do I report a problem?'), t('Contact your VESTRA concierge below. Report anything that looks wrong — a listing, a member or an order — and we will act on it.')],
    ],
  ],
];

require __DIR__.'/inc/head.php';
?>
<style>
.helpwrap{max-width:1080px;margin:0 auto;padding:0 20px 72px}
.help-hero{position:relative;text-align:center;padding:56px 20px 40px;margin-bottom:8px;
  border-bottom:1px solid var(--line)}
.help-hero .eyebrow{font-size:12px;letter-spacing:2.5px;text-transform:uppercase;color:var(--acc);font-weight:600;margin-bottom:14px}
.help-hero h1{font-family:'Playfair Display',serif;font-size:clamp(28px,4vw,42px);font-weight:700;margin:0 0 10px;line-height:1.1}
.help-hero p{color:var(--mut);font-size:16px;margin:0 auto;max-width:560px}
.help-search{margin:26px auto 4px;max-width:600px;position:relative}
.help-search input{width:100%;box-sizing:border-box;padding:16px 18px 16px 50px;font-size:16px;
  background:var(--bg2);border:1px solid var(--line);border-radius:14px;color:var(--ink);transition:.2s}
.help-search input:focus{outline:none;border-color:var(--acc);box-shadow:0 0 0 3px rgba(201,168,106,.15)}
.help-search svg{position:absolute;left:18px;top:50%;transform:translateY(-50%);width:20px;height:20px;color:var(--mut)}

.help-tiles{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin:34px 0 8px}
.help-tile{display:flex;flex-direction:column;gap:10px;padding:22px;border:1px solid var(--line);border-radius:16px;
  background:linear-gradient(180deg,rgba(255,255,255,.02),transparent);text-decoration:none;color:inherit;transition:.22s;cursor:pointer}
.help-tile:hover{border-color:var(--acc);transform:translateY(-3px);box-shadow:0 18px 40px -20px rgba(0,0,0,.6)}
.help-tile .ic{width:42px;height:42px;border-radius:11px;display:flex;align-items:center;justify-content:center;
  background:rgba(201,168,106,.12);color:var(--acc)}
.help-tile .ic svg{width:23px;height:23px}
.help-tile h3{font-size:16px;font-weight:700;margin:2px 0 0}
.help-tile p{font-size:13px;color:var(--mut);margin:0;line-height:1.5}

.help-sections{margin-top:40px;display:flex;flex-direction:column;gap:14px}
.help-cat{scroll-margin-top:80px;border:1px solid var(--line);border-radius:18px;overflow:hidden;background:var(--bg2)}
.help-cat-hd{display:flex;align-items:center;gap:14px;padding:20px 22px;border-bottom:1px solid var(--line)}
.help-cat-hd .ic{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:rgba(201,168,106,.12);color:var(--acc);flex:none}
.help-cat-hd .ic svg{width:21px;height:21px}
.help-cat-hd h2{font-family:'Playfair Display',serif;font-size:20px;font-weight:700;margin:0}
.help-cat-hd span{display:block;font-size:12.5px;color:var(--mut);font-weight:400;font-family:system-ui,sans-serif;margin-top:2px}
.help-articles{padding:6px 10px 12px}
.help-article{border-bottom:1px solid rgba(255,255,255,.05)}
.help-article:last-child{border-bottom:0}
.help-article summary{list-style:none;cursor:pointer;padding:15px 12px;font-size:15px;font-weight:500;
  display:flex;justify-content:space-between;align-items:center;gap:12px;transition:.15s}
.help-article summary::-webkit-details-marker{display:none}
.help-article summary:hover{color:var(--acc)}
.help-article summary::after{content:"+";font-size:20px;color:var(--acc);font-weight:400;flex:none;transition:transform .2s}
.help-article[open] summary::after{content:"–"}
.help-article .ans{padding:0 12px 16px;color:var(--mut);font-size:14px;line-height:1.7;max-width:70ch}
.help-noresult{display:none;text-align:center;color:var(--mut);padding:40px;font-size:15px}

.help-concierge{margin-top:34px;padding:30px;border:1px solid var(--acc);border-radius:18px;
  background:linear-gradient(135deg,rgba(201,168,106,.10),rgba(201,168,106,.02));
  display:flex;align-items:center;gap:22px;flex-wrap:wrap}
.help-concierge .ic{width:56px;height:56px;border-radius:14px;display:flex;align-items:center;justify-content:center;
  background:rgba(201,168,106,.16);color:var(--acc);flex:none}
.help-concierge .ic svg{width:30px;height:30px}
.help-concierge .txt{flex:1;min-width:220px}
.help-concierge h3{font-family:'Playfair Display',serif;font-size:21px;margin:0 0 4px}
.help-concierge p{color:var(--mut);font-size:14px;margin:0;line-height:1.6}
.help-concierge .cta{display:flex;gap:10px;flex-wrap:wrap}

@media(max-width:820px){ .help-tiles{grid-template-columns:1fr 1fr} }
@media(max-width:560px){ .help-tiles{grid-template-columns:1fr} .help-concierge{flex-direction:column;text-align:center;align-items:center} }
</style>

<div class="helpwrap">
  <div class="help-hero">
    <div class="eyebrow"><?= t('VESTRA Concierge') ?></div>
    <h1><?= t('How can we help?') ?></h1>
    <p><?= t('Everything you need to buy, sell and get paid safely on VESTRA — plus a personal concierge for members when you want a human.') ?></p>
    <div class="help-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg>
      <input type="search" id="helpSearch" placeholder="<?= htmlspecialchars(t('Search help — e.g. escrow, payout, MOQ, verification…')) ?>" value="<?= htmlspecialchars($q) ?>" autocomplete="off" aria-label="<?= htmlspecialchars(t('Search help')) ?>">
    </div>
  </div>

  <div class="help-tiles" id="helpTiles">
    <?php foreach ($HELP as $k => $c): ?>
    <a class="help-tile" href="#cat-<?= $k ?>">
      <div class="ic"><?= help_icon($k) ?></div>
      <h3><?= htmlspecialchars($c['title']) ?></h3>
      <p><?= htmlspecialchars($c['sub']) ?></p>
    </a>
    <?php endforeach; ?>
  </div>

  <div class="help-sections" id="helpSections">
    <?php foreach ($HELP as $k => $c): ?>
    <section class="help-cat" id="cat-<?= $k ?>">
      <div class="help-cat-hd">
        <div class="ic"><?= help_icon($k) ?></div>
        <div><h2><?= htmlspecialchars($c['title']) ?></h2><span><?= htmlspecialchars($c['sub']) ?></span></div>
      </div>
      <div class="help-articles">
        <?php foreach ($c['items'] as $it): ?>
        <details class="help-article">
          <summary><?= htmlspecialchars($it[0]) ?></summary>
          <div class="ans"><?= nl2br(htmlspecialchars($it[1])) ?></div>
        </details>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endforeach; ?>
    <div class="help-noresult" id="helpNoResult"><?= t('No articles match your search. Try another word, or contact your concierge below.') ?></div>
  </div>

  <div class="help-concierge">
    <div class="ic"><?= help_icon('concierge') ?></div>
    <div class="txt">
      <h3><?= t('VESTRA Concierge — priority support for members') ?></h3>
      <p><?= t('Members get personal, priority help. Email us and a real person replies within one business day — sourcing, payouts, disputes or anything else.') ?></p>
    </div>
    <div class="cta">
      <a class="btn btn-p" href="mailto:support@vestrasales.com"><?= t('Email the concierge') ?></a>
      <a class="btn btn-o" href="/faq"><?= t('Browse all FAQs') ?></a>
    </div>
  </div>
</div>

<script>
(function(){
  var box=document.getElementById('helpSearch'), sections=document.getElementById('helpSections'),
      tiles=document.getElementById('helpTiles'), none=document.getElementById('helpNoResult');
  if(!box) return;
  function norm(s){return (s||'').toLowerCase();}
  function run(){
    var q=norm(box.value).trim(); var any=false;
    sections.querySelectorAll('.help-cat').forEach(function(cat){
      var shown=0;
      cat.querySelectorAll('.help-article').forEach(function(a){
        var t=norm(a.textContent);
        var hit=(q==='')||t.indexOf(q)>-1;
        a.style.display=hit?'':'none';
        if(hit && q!=='') a.open=true; else if(q==='') a.open=false;
        if(hit) shown++;
      });
      cat.style.display=shown?'':'none';
      if(shown) any=true;
    });
    tiles.style.display=q?'none':'';
    none.style.display=any?'none':'block';
  }
  box.addEventListener('input',run);
  if(box.value) run();
})();
</script>
<?php require __DIR__.'/inc/foot.php';
