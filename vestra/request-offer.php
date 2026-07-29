<?php
/**
 * VESTRA — seller offer on a sourcing request.
 * Linked from requests.php "Make an offer" buttons.
 */
require_once __DIR__.'/inc/i18n.php';
require_once __DIR__.'/inc/auth.php';
require_once __DIR__.'/inc/notify.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['uid'])) { header('Location: /login?back='.urlencode($_SERVER['REQUEST_URI'])); exit; }

$ref  = trim($_GET['ref'] ?? '');
$seed = !empty($_GET['seed']);
$err  = '';
$ok   = false;
$req  = null; // the sourcing request this offer is for

/* ── Load the sourcing request ── */
if ($seed) {
    // Seed/demo requests — no buyer email stored, just ref as id
    require_once __DIR__.'/inc/products.php';
    foreach (vestra_requests() as $r) {
        if ((string)($r['id'] ?? '') === $ref) { $req = $r; break; }
    }
} else {
    $f = __DIR__.'/data/requests.csv';
    if (is_readable($f) && ($h = @fopen($f, 'r'))) {
        $head = fgetcsv($h, null, ',', '"', '\\');
        while (($row = fgetcsv($h, null, ',', '"', '\\')) !== false) {
            if ($head && count($row) === count($head)) {
                $r = array_combine($head, $row);
                if (($r['ref'] ?? '') === $ref) { $req = $r; break; }
            }
        }
        fclose($h);
    }
}

if (!$req) { header('Location: /requests'); exit; }

/* ── Pre-fill seller info from session ── */
$u = auth_user();

/* ── Handle POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company  = trim($_POST['company']  ?? '');
    $email    = trim($_POST['email']    ?? '');
    $price    = (float)($_POST['price'] ?? 0);
    $qty      = trim($_POST['qty']      ?? '');
    $delivery = trim($_POST['delivery'] ?? '');
    $message  = trim($_POST['message']  ?? '');

    if (!empty($_POST['website'])) { header('Location: /requests'); exit; } // honeypot

    if ($company === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $price <= 0) {
        $err = t('Please fill in all required fields.');
    } else {
        $oref = 'RO'.strtoupper(substr(md5($email.$ref.microtime(false)), 0, 6));
        $one  = fn($s) => trim(preg_replace('/\s+/', ' ', str_replace(["\r","\n"], ' ', (string)$s)));

        // Save to data/request_offers.csv
        $dir  = __DIR__.'/data';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $file = $dir.'/request_offers.csv';
        $new  = !file_exists($file);
        if ($fh = @fopen($file, 'a')) {
            if ($new) fputcsv($fh, ['timestamp','ref','request_ref','seller_company','seller_email','price','qty','delivery','message'],',','"','\\');
            fputcsv($fh, [date('c'), $oref, $ref, $one($company), $one($email), $price, $one($qty), $one($delivery), $one($message)],',','"','\\');
            fclose($fh);
        }

        $title = $req['title'] ?? $req['id'] ?? $ref;

        // Notify admin
        vestra_notify(
            "Seller offer {$oref} on request #{$ref}",
            "New seller offer on VESTRA sourcing request:\n\n".
            "Request: #{$ref} — {$title}\n".
            "Seller: {$company} <{$email}>\n".
            "Price offered: €{$price}\n".
            "Quantity: {$qty}\n".
            "Delivery: {$delivery}\n".
            "Message: {$message}\n\n".
            "Ref: {$oref}\n\n".
            "Admin: https://vestrasales.com/admin",
            $email
        );

        /* Mirror the offer into an on-platform message thread when both sides have real
           VESTRA accounts — keeps the negotiation on-platform instead of a raw mailto. */
        $buyerEmail = $req['email'] ?? '';
        $buyerAcc = $buyerEmail ? auth_find($buyerEmail) : null;
        $sellerAcc = auth_find($email);
        if ($buyerAcc && ($buyerAcc['type']??'')==='buyer') {
            require_once __DIR__.'/inc/push.php';
            vestra_push_send($buyerAcc['id'], 'VESTRA — offer on your request 📥',
                mb_substr($title,0,60).' — €'.$price.($qty!==''?' · '.$qty:''), '/requests');
        }
        if ($buyerAcc && ($buyerAcc['type']??'')==='buyer' && $sellerAcc && ($sellerAcc['type']??'')==='seller') {
            require_once __DIR__.'/inc/messages.php';
            vestra_msg_post_system($buyerAcc['id'], $sellerAcc['id'], $ref, [
                'kind'=>'request_offer', 'ref'=>$oref, 'request_ref'=>$ref, 'product'=>$title,
                'qty'=>$qty, 'unit_price'=>$price,
            ]);
        }

        // Notify buyer if we have their email (real requests only) — never reveals the
        // seller's raw email; response happens on-platform (Messages / Accept button).
        if ($buyerEmail && filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) {
            vestra_send_mail($buyerEmail,
                "VESTRA — you received an offer on your request #{$ref}",
                "Hello,\n\nA verified seller has made an offer on your sourcing request:\n\n".
                "Your request: {$title}\n".
                "Seller company: {$company}\n".
                "Price offered: €{$price}/pc\n".
                "Quantity available: {$qty}\n".
                "Delivery: {$delivery}\n".
                ($message ? "Message: {$message}\n" : '').
                "\nRef: {$oref}\n\n".
                "View and respond on VESTRA:\nhttps://vestrasales.com/buyer?tab=requests\n\n".
                "— VESTRA · vestrasales.com"
            );
        }

        // Confirmation to seller
        vestra_send_mail($email,
            "VESTRA — your offer {$oref} has been submitted",
            "Hello {$company},\n\nYour offer on sourcing request #{$ref} has been submitted.\n\n".
            "Request: {$title}\n".
            "Your offer: €{$price}/pc / {$qty}\n".
            "Ref: {$oref}\n\n".
            "VESTRA will forward your offer to the buyer. You will hear back if they accept.\n\n".
            "— VESTRA · vestrasales.com"
        );

        $ok = true;
    }
}

$PAGE = t('Make an offer'); $NAV = ''; require __DIR__.'/inc/head.php';
$title = htmlspecialchars($req['title'] ?? $req['id'] ?? $ref);
?>
<div class="wrap" style="max-width:640px">
  <div class="crumbs"><a href="/">Home</a> · <a href="/requests"><?= t('Sourcing board') ?></a> · <?= t('Make an offer') ?></div>

  <?php if ($ok): ?>
    <div style="text-align:center;padding:60px 20px">
      <div style="font-size:52px;margin-bottom:16px">✓</div>
      <h2 style="margin:0 0 10px"><?= t('Offer submitted') ?></h2>
      <p style="color:var(--mut);margin:0 0 24px"><?= t('Your offer has been forwarded to the buyer. Check your email for confirmation.') ?></p>
      <a class="btn btn-p" href="/requests"><?= t('Back to sourcing board') ?></a>
    </div>
  <?php else: ?>
    <h1 style="margin:28px 0 6px"><?= t('Make an offer') ?></h1>
    <p style="color:var(--mut);margin:0 0 24px"><?= t('Responding to request') ?> <b>#<?= htmlspecialchars($ref) ?></b>: <?= $title ?></p>

    <?php if ($err): ?>
      <div class="banner" style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.35);color:var(--bad);margin-bottom:16px"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <form method="post" action="" style="display:flex;flex-direction:column;gap:14px">
      <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px">

      <div class="frow" style="margin-bottom:0">
        <div class="authfield">
          <label><?= t('Company name') ?> *</label>
          <input name="company" required placeholder="Your Company GmbH" value="<?= htmlspecialchars($u['company'] ?? '') ?>">
        </div>
        <div class="authfield">
          <label><?= t('Your email') ?> *</label>
          <input type="email" name="email" required placeholder="name@company.com" value="<?= htmlspecialchars($u['email'] ?? '') ?>">
        </div>
      </div>

      <div class="frow" style="margin-bottom:0">
        <div class="authfield">
          <label><?= t('Price per unit (€)') ?> *</label>
          <input type="number" name="price" step="0.01" min="0.01" required placeholder="18.50">
        </div>
        <div class="authfield">
          <label><?= t('Quantity available') ?></label>
          <input name="qty" placeholder="<?= htmlspecialchars(t('e.g. 500 pc')) ?>">
        </div>
      </div>

      <div class="authfield">
        <label><?= t('Delivery time') ?></label>
        <input name="delivery" placeholder="<?= htmlspecialchars(t('e.g. 5–7 business days, ex works Germany')) ?>">
      </div>

      <div class="authfield">
        <label><?= t('Message') ?></label>
        <textarea name="message" rows="3" placeholder="<?= htmlspecialchars(t('Details about the products, MOQ, samples, etc.')) ?>"></textarea>
      </div>

      <div class="banner info" style="font-size:13px"><?= t('Your offer will be forwarded to the buyer. VESTRA will also receive a copy.') ?></div>

      <button class="btn btn-p" type="submit" style="justify-content:center"><?= t('Submit offer') ?></button>
    </form>
  <?php endif; ?>
</div>
<?php require __DIR__.'/inc/foot.php';
