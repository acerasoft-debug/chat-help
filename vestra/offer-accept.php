<?php
/**
 * VESTRA — alici, saticinin KARSI TEKLIFINI e-postadaki dugmeyle kabul eder.
 *
 * Giris GEREKMEZ: kimlik bilgisi, karsi teklifle birlikte uretilen tek
 * kullanimlik token. Teklifi zaten o adrese gonderdik; oturum sarti koymak
 * kabulu, alicinin sifresini hatirlamasina baglamak olurdu.
 *
 * GET yalnizca ONAY EKRANI gosterir, kabul POST ile olur. Bu sart degil,
 * ZORUNLU: Outlook/Gmail gibi posta guvenlik tarayicilari mektuptaki
 * linkleri onden GET ile cekiyor. Kabul GET'te yapilsaydi, alici mektubu
 * acmadan once teklif kabul edilmis ve fatura kesilmis olurdu.
 * (Ayni gerekce lead-unsubscribe.php'de de yazili.)
 */
require __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/offers.php';

$ref   = trim((string)($_GET['ref']   ?? $_POST['ref']   ?? ''));
$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));

$resp    = null;
$offerRow = null;
$err     = '';
$done    = false;
$agreed  = 0.0;
$qty     = 0;

if ($ref !== '' && $token !== '') {
    $all  = vestra_read_json('offer_responses.json');
    $resp = $all[$ref] ?? null;
    $offerRow = vestra_offer_row($ref);
    $qty  = (int)($offerRow['qty'] ?? 0);
    $agreed = (float)($resp['counter_price'] ?? 0);
}

$declined = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* Kabul VE RED ayni ekrandan. Yalnizca kabul dugmesi koymak, "hayir"
       demek isteyen aliciyi cevapsiz birakirdi -- ve cevapsiz bir pazarlik
       operatorun de bekleyip beklemeyecegini bilmedigi bir pazarliktir. */
    if (($_POST['do'] ?? '') === 'decline') {
        $r = vestra_offer_decline_counter($ref, $token);
        if ($r['ok']) { $declined = true; $agreed = (float)$r['unit']; }
        else          { $err = (string)$r['error']; }
    } else {
        $r = vestra_offer_accept_counter($ref, $token);
        if ($r['ok']) { $done = true; $agreed = (float)$r['unit']; }
        else          { $err = (string)$r['error']; }
    }
} elseif (!$resp || !$offerRow) {
    $err = 'link gecersiz';
} elseif (($resp['status'] ?? '') === 'accept') {
    /* Ayni linke ikinci kez basmak hata degil: alici genelde "gitti mi?"
       diye kontrol ediyor. Ona kabulun DURDUGUNU gostermek dogru cevap. */
    $done = true;
    $agreed = (float)($resp['agreed_unit'] ?? $resp['counter_price'] ?? 0);
} elseif (($resp['status'] ?? '') !== 'counter' || !hash_equals((string)($resp['accept_token'] ?? '_'), $token)) {
    $err = 'link gecersiz ya da artik gecerli degil';
}

$prodName = trim((string)($offerRow['product'] ?? ''));
$PAGE = t('Accept counter offer'); $NAV = ''; require __DIR__.'/inc/head.php';
?>
<div class="wrap" style="max-width:560px">
  <div style="padding:60px 20px">
    <?php if ($declined): ?>
      <div style="text-align:center">
        <div style="font-size:44px;margin-bottom:16px">✗</div>
        <h2 style="margin:0 0 10px"><?= t('Counter offer declined') ?></h2>
        <p style="color:var(--mut)">
          <?= t('Thanks for letting us know — nothing is owed and nothing is reserved. We have told the seller; if they can move on price, you will hear from us.') ?>
        </p>
        <div style="margin-top:22px"><a class="btn btn-o" href="/buyer?tab=offers"><?= t('Open my dashboard') ?></a></div>
      </div>

    <?php elseif ($done): ?>
      <div style="text-align:center">
        <div style="font-size:44px;margin-bottom:16px">✓</div>
        <h2 style="margin:0 0 10px"><?= t('Offer accepted') ?></h2>
        <p style="color:var(--mut);margin:0 0 22px">
          <?= sprintf(t('Agreed at %s per unit. We are preparing your invoice and will e-mail it shortly — the goods are reserved for you in the meantime.'), '<b>'.eur($agreed).'</b>') ?>
        </p>
      </div>
      <div class="panelcard"><div class="panelcard-body" style="padding:4px 4px 10px">
        <table class="ctable"><tbody>
          <tr><td><?= t('Product') ?></td><td class="r"><b><?= htmlspecialchars($prodName) ?></b></td></tr>
          <tr><td><?= t('Reference') ?></td><td class="r"><?= htmlspecialchars($ref) ?></td></tr>
          <?php if ($qty > 0): ?>
          <tr><td><?= t('Quantity') ?></td><td class="r"><?= (int)$qty ?></td></tr>
          <tr><td><?= t('Total') ?></td><td class="r"><b><?= eur($agreed * $qty) ?></b></td></tr>
          <?php endif; ?>
        </tbody></table>
      </div></div>
      <div style="text-align:center;margin-top:22px">
        <a class="btn btn-p" href="/buyer?tab=offers"><?= t('View in my dashboard') ?></a>
      </div>

    <?php elseif ($err !== ''): ?>
      <div style="text-align:center">
        <div style="font-size:44px;margin-bottom:16px">🔍</div>
        <h2 style="margin:0 0 10px"><?= t('This link is no longer valid') ?></h2>
        <p style="color:var(--mut)">
          <?= t('The counter offer may have been withdrawn, replaced by a newer one, or already answered. Your offers are always up to date in your dashboard.') ?>
        </p>
        <div style="margin-top:22px"><a class="btn btn-o" href="/buyer?tab=offers"><?= t('Open my dashboard') ?></a></div>
      </div>

    <?php else: ?>
      <div style="text-align:center;margin-bottom:22px">
        <div style="font-size:44px;margin-bottom:14px">↩</div>
        <h2 style="margin:0 0 8px"><?= t('Accept this counter offer?') ?></h2>
        <p style="color:var(--mut);margin:0">
          <?= t('Accepting agrees the price. We then issue the invoice at this price — payment comes after, from your dashboard.') ?>
        </p>
      </div>
      <div class="panelcard"><div class="panelcard-body" style="padding:4px 4px 10px">
        <table class="ctable"><tbody>
          <tr><td><?= t('Product') ?></td><td class="r"><b><?= htmlspecialchars($prodName) ?></b></td></tr>
          <tr><td><?= t('Reference') ?></td><td class="r"><?= htmlspecialchars($ref) ?></td></tr>
          <tr><td><?= t('Quantity') ?></td><td class="r"><?= (int)$qty ?></td></tr>
          <tr><td><?= t('Your offer') ?></td><td class="r" style="text-decoration:line-through;color:var(--mut)"><?= eur((float)($offerRow['offer_unit'] ?? 0)) ?>/u</td></tr>
          <tr><td><?= t('Counter price') ?></td><td class="r"><b><?= eur($agreed) ?></b>/u</td></tr>
          <tr><td><?= t('Total') ?></td><td class="r"><b><?= eur($agreed * $qty) ?></b></td></tr>
        </tbody></table>
      </div></div>
      <form method="post" style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:22px">
        <input type="hidden" name="ref" value="<?= htmlspecialchars($ref) ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <button class="btn btn-p" type="submit" name="do" value="accept"><?= sprintf(t('Accept %s per unit'), eur($agreed)) ?></button>
        <button class="btn btn-o" type="submit" name="do" value="decline"
          onclick="return confirm('<?= htmlspecialchars(t('Decline this counter offer? The negotiation closes and nothing is reserved.'), ENT_QUOTES) ?>')"><?= t('Decline') ?></button>
      </form>
      <p style="text-align:center;color:var(--mut);font-size:13px;margin-top:16px">
        <?= t('Want to negotiate further instead?') ?> <a href="/buyer?tab=messages" style="color:var(--acc)"><?= t('Message us') ?></a>
      </p>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__.'/inc/foot.php'; ?>
