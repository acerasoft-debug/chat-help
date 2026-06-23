<?php
/**
 * VESTRA — Verified B2B Fashion Wholesale · Phase 0 landing + waitlist
 * --------------------------------------------------------------------
 * Tek dosya, sade PHP. Hosteurope dâhil her paylaşımlı hostingte çalışır.
 * Değiştirmek için sadece aşağıdaki birkaç satırı düzenle:
 */
$BRAND    = 'VESTRA';                                  // ← marka adı (placeholder — değiştir)
$TAGLINE  = 'Europe\'s verified B2B fashion wholesale'; // ← alt başlık
$CONTACT  = 'hello@vestra.example';                    // ← iletişim e-postan
$COMPANY  = '[Company Name LLC · US] — Impressum / legal info pending'; // ← şirket bilgin (şeffaflık!)
$ACCENT   = '#c9a86a';

$joined = isset($_GET['joined']);
$error  = isset($_GET['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($BRAND) ?> — <?= htmlspecialchars($TAGLINE) ?></title>
<meta name="description" content="A verified, authenticity-first B2B wholesale marketplace for branded and textile fashion. Join the founding waitlist.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#0e0e11; --bg2:#15151a; --ink:#f4f1ea; --mut:#9a988f;
    --acc:<?= htmlspecialchars($ACCENT) ?>; --line:rgba(255,255,255,.08);
  }
  *{box-sizing:border-box}
  html,body{margin:0;background:var(--bg);color:var(--ink);
    font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
    line-height:1.6;-webkit-font-smoothing:antialiased}
  a{color:inherit;text-decoration:none}
  .wrap{max-width:1080px;margin:0 auto;padding:0 24px}
  h1,h2,h3{font-family:'Playfair Display',Georgia,serif;font-weight:700;line-height:1.12;letter-spacing:-.5px}
  .acc{color:var(--acc)}

  /* nav */
  header{position:sticky;top:0;z-index:10;background:rgba(14,14,17,.72);
    backdrop-filter:blur(10px);border-bottom:1px solid var(--line)}
  nav{display:flex;align-items:center;justify-content:space-between;height:64px}
  .logo{font-family:'Playfair Display',serif;font-size:24px;font-weight:700;letter-spacing:1px}
  .logo b{color:var(--acc)}
  .nav-cta{font-size:14px;border:1px solid var(--line);padding:9px 16px;border-radius:999px;
    transition:.2s}
  .nav-cta:hover{border-color:var(--acc);color:var(--acc)}

  /* hero */
  .hero{padding:88px 0 64px;text-align:center;position:relative;overflow:hidden}
  .hero:before{content:"";position:absolute;inset:-40% 0 auto 0;height:520px;
    background:radial-gradient(60% 60% at 50% 0,rgba(201,168,106,.16),transparent 70%);pointer-events:none}
  .eyebrow{font-size:13px;letter-spacing:3px;text-transform:uppercase;color:var(--acc);margin-bottom:20px}
  .hero h1{font-size:clamp(34px,6vw,60px);margin:0 0 18px}
  .hero p{font-size:clamp(16px,2.4vw,20px);color:var(--mut);max-width:620px;margin:0 auto 34px}
  .btns{display:flex;gap:14px;justify-content:center;flex-wrap:wrap}
  .btn{padding:15px 28px;border-radius:999px;font-weight:600;font-size:15px;cursor:pointer;
    border:1px solid var(--acc);transition:.2s;display:inline-block}
  .btn-p{background:var(--acc);color:#1a1408}
  .btn-p:hover{filter:brightness(1.08);transform:translateY(-1px)}
  .btn-o{background:transparent;color:var(--ink)}
  .btn-o:hover{background:rgba(201,168,106,.12);transform:translateY(-1px)}

  /* pillars */
  .pillars{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin:72px 0}
  .card{background:var(--bg2);border:1px solid var(--line);border-radius:16px;padding:28px}
  .card .ic{font-size:22px;width:46px;height:46px;display:grid;place-items:center;
    border-radius:12px;background:rgba(201,168,106,.12);margin-bottom:16px}
  .card h3{font-size:19px;margin:0 0 8px}
  .card p{color:var(--mut);font-size:15px;margin:0}

  /* steps */
  .sec-title{text-align:center;margin:0 0 8px;font-size:clamp(26px,4vw,38px)}
  .sec-sub{text-align:center;color:var(--mut);margin:0 auto 44px;max-width:520px}
  .steps{display:grid;grid-template-columns:repeat(3,1fr);gap:24px}
  .step .n{font-family:'Playfair Display',serif;font-size:40px;color:var(--acc);opacity:.5}
  .step h3{font-size:18px;margin:6px 0 6px}
  .step p{color:var(--mut);font-size:15px;margin:0}

  /* join */
  .join{margin:84px 0;background:linear-gradient(180deg,var(--bg2),#101015);
    border:1px solid var(--line);border-radius:24px;padding:48px;max-width:720px;margin-left:auto;margin-right:auto}
  .toggle{display:flex;background:#0c0c0f;border:1px solid var(--line);border-radius:999px;
    padding:5px;margin:0 auto 26px;width:fit-content}
  .toggle button{border:0;background:transparent;color:var(--mut);padding:10px 22px;border-radius:999px;
    cursor:pointer;font:inherit;font-weight:600;font-size:14px;transition:.2s}
  .toggle button.on{background:var(--acc);color:#1a1408}
  form{display:grid;gap:14px}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
  label{font-size:13px;color:var(--mut);display:block;margin-bottom:6px}
  input,textarea,select{width:100%;background:#0c0c0f;border:1px solid var(--line);
    border-radius:10px;padding:13px 14px;color:var(--ink);font:inherit;font-size:15px}
  input:focus,textarea:focus,select:focus{outline:0;border-color:var(--acc)}
  .hp{position:absolute;left:-9999px}
  .submit{margin-top:6px}
  .note{font-size:13px;color:var(--mut);text-align:center;margin-top:14px}
  .banner{padding:14px 18px;border-radius:12px;margin-bottom:22px;font-size:15px;text-align:center}
  .ok{background:rgba(80,200,120,.12);border:1px solid rgba(80,200,120,.4);color:#9fe3b4}
  .bad{background:rgba(230,90,90,.12);border:1px solid rgba(230,90,90,.4);color:#f0a8a8}

  footer{border-top:1px solid var(--line);padding:40px 0;color:var(--mut);font-size:13px}
  .foot{display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap}

  @media(max-width:760px){
    .pillars,.steps{grid-template-columns:1fr}
    .row{grid-template-columns:1fr}
    .join{padding:30px 22px}
    .hero{padding:60px 0 40px}
  }
</style>
</head>
<body>
<header>
  <div class="wrap"><nav>
    <div class="logo"><?= htmlspecialchars($BRAND) ?><b>.</b></div>
    <a href="#join" class="nav-cta">Join the waitlist</a>
  </nav></div>
</header>

<section class="hero">
  <div class="wrap">
    <div class="eyebrow">Invitation-only · Launching soon</div>
    <h1>The <span class="acc">verified</span> way to trade<br>branded fashion, wholesale.</h1>
    <p>A B2B marketplace where every seller is KYC-checked, every order is escrow-protected,
       and every product is traceable. Built authenticity-first — not as an afterthought.</p>
    <div class="btns">
      <a class="btn btn-p" href="#join" onclick="setType('seller')">Join as a Seller</a>
      <a class="btn btn-o" href="#join" onclick="setType('buyer')">Join as a Buyer</a>
    </div>
  </div>
</section>

<div class="wrap">
  <section class="pillars">
    <div class="card">
      <div class="ic">🔐</div>
      <h3>Verified sellers only</h3>
      <p>Business KYC on every seller — VAT ID, registration, identity. No anonymous listings, no guesswork.</p>
    </div>
    <div class="card">
      <div class="ic">🛡️</div>
      <h3>Escrow &amp; authenticity</h3>
      <p>Funds are released only after the order is received and confirmed. Counterfeit &amp; grey-market screening built in.</p>
    </div>
    <div class="card">
      <div class="ic">🪪</div>
      <h3>Digital Product Passport</h3>
      <p>Every item carries a traceable provenance record — ready for the EU's incoming DPP rules, today.</p>
    </div>
  </section>

  <section>
    <h2 class="sec-title">How it works</h2>
    <p class="sec-sub">Trust, by design — for both sides of the trade.</p>
    <div class="steps">
      <div class="step"><div class="n">01</div><h3>Get verified</h3>
        <p>Sellers and buyers complete a quick business verification. Approved members see live wholesale pricing.</p></div>
      <div class="step"><div class="n">02</div><h3>Source &amp; order</h3>
        <p>Browse verified listings — branded &amp; textile basics. Order with escrow protection and clear terms.</p></div>
      <div class="step"><div class="n">03</div><h3>Trade with confidence</h3>
        <p>Authenticity checks, provenance records and a fair dispute process protect every transaction.</p></div>
    </div>
  </section>

  <section class="join" id="join">
    <?php if ($joined): ?>
      <div class="banner ok">✓ You're on the list. We'll be in touch as we open the founding cohort.</div>
    <?php elseif ($error): ?>
      <div class="banner bad">Please enter at least your name and a valid email.</div>
    <?php endif; ?>

    <h2 class="sec-title" style="font-size:30px">Join the founding waitlist</h2>
    <p class="sec-sub">Be among the first verified members. No payment, no commitment.</p>

    <div class="toggle">
      <button type="button" id="t-seller" class="on" onclick="setType('seller')">I'm a Seller</button>
      <button type="button" id="t-buyer" onclick="setType('buyer')">I'm a Buyer</button>
    </div>

    <form action="join.php" method="post">
      <input type="hidden" name="type" id="type" value="seller">
      <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off">
      <div class="row">
        <div><label>Full name *</label><input name="name" required></div>
        <div><label>Company</label><input name="company"></div>
      </div>
      <div class="row">
        <div><label>Work email *</label><input type="email" name="email" required></div>
        <div><label>Country</label><input name="country"></div>
      </div>
      <div><label id="msglabel">What do you sell? (brands, categories)</label>
        <textarea name="message" rows="3"></textarea></div>
      <button class="btn btn-p submit" type="submit">Request early access</button>
      <p class="note">By joining you agree to be contacted about the launch. We never share your data.</p>
    </form>
  </section>
</div>

<footer>
  <div class="wrap foot">
    <div><b style="color:var(--ink)"><?= htmlspecialchars($BRAND) ?>.</b> — <?= htmlspecialchars($TAGLINE) ?></div>
    <div><?= htmlspecialchars($COMPANY) ?> · <a href="mailto:<?= htmlspecialchars($CONTACT) ?>" class="acc"><?= htmlspecialchars($CONTACT) ?></a></div>
  </div>
</footer>

<script>
  function setType(t){
    document.getElementById('type').value = t;
    document.getElementById('t-seller').classList.toggle('on', t==='seller');
    document.getElementById('t-buyer').classList.toggle('on', t==='buyer');
    document.getElementById('msglabel').textContent =
      t==='seller' ? 'What do you sell? (brands, categories)' : 'What are you looking to source?';
  }
</script>
</body>
</html>
