<?php
/**
 * Tek sipariş: her ayrıntı — satırlar, müşteri, teslimat, ödeme kimlikleri,
 * siparişi veren tarayıcı (IP, ülke, cihaz), zaman çizelgesi, satıcı payı,
 * kargo kaydı, not, iptal, onay e-postasını yeniden gönderme.
 *
 * PARA HAREKETİ YOK: iade Stripe panelinden. Burada yalnız durum ve kayıt.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/view.php';
require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/../inc/mail.php';
require_once __DIR__ . '/../inc/customers.php';
require_once __DIR__ . '/_nav.php';

$who = vr_admin_require();

$o = vr_order_get((string)($_GET['id'] ?? ''));
// Bekleyen kripto siparişi: OxaPay'e sor (webhook gelmemişse bile doğru durum).
if ($o !== null && (string)($o['payment_method'] ?? '') === 'crypto' && (string)($o['status'] ?? '') === 'pending') {
    require_once __DIR__ . '/../inc/oxapay.php';
    $o = vr_oxapay_sync($o, 10) ?? $o;
}
if ($o === null) { http_response_code(404); vr_admin_head('Nicht gefunden'); echo '<p>Bestellung nicht gefunden.</p>'; vr_admin_foot(); exit; }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    vr_csrf_check();
    $act = (string)($_POST['action'] ?? '');
    $hist = (array)($o['history'] ?? []);
    $push = function (string $what, string $note = '') use (&$hist, $who) { $hist[] = ['at' => time(), 'who' => $who, 'what' => $what, 'note' => $note]; };
    if ($act === 'ship') {
        $carrier = mb_substr(trim((string)($_POST['carrier'] ?? '')), 0, 60);
        $tracking = mb_substr(trim((string)($_POST['tracking'] ?? '')), 0, 120);
        $push('shipped', trim($carrier . ' ' . $tracking));
        vr_order_update((string)$o['id'], ['shipped_at' => time(), 'carrier' => $carrier, 'tracking' => $tracking, 'history' => $hist]);
        vr_admin_audit('order_ship', ['order' => $o['number'], 'tracking' => $tracking]);
        vr_flash('Als versendet vermerkt.', 'ok');
    } elseif ($act === 'unship') {
        $push('unshipped');
        vr_order_update((string)$o['id'], ['shipped_at' => 0, 'carrier' => '', 'tracking' => '', 'history' => $hist]);
        vr_admin_audit('order_unship', ['order' => $o['number']]);
        vr_flash('Versandvermerk entfernt.', 'ok');
    } elseif ($act === 'note') {
        vr_order_update((string)$o['id'], ['admin_note' => mb_substr(trim((string)($_POST['note'] ?? '')), 0, 4000)]);
        vr_flash('Notiz gespeichert.', 'ok');
    } elseif ($act === 'cancel' && (string)$o['status'] !== 'cancelled') {
        // Stok geri gelir; ödeme alındıysa iade Stripe'tan yapılır (burada yalnız durum).
        if ((string)$o['status'] === 'paid') vr_stock_restore((array)$o['lines']);
        $push('cancelled', mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 300));
        vr_order_update((string)$o['id'], ['status' => 'cancelled', 'cancelled_at' => time(), 'cancel_reason' => mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 300), 'history' => $hist]);
        vr_admin_audit('order_cancel', ['order' => $o['number']]);
        vr_flash('Bestellung storniert. Eine Erstattung läuft über das Stripe-Dashboard.', 'ok');
    } elseif ($act === 'refunded' && (string)$o['status'] === 'paid') {
        $push('refunded', mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 300));
        vr_stock_restore((array)$o['lines']);
        vr_order_update((string)$o['id'], ['status' => 'refunded', 'refunded_at' => time(), 'history' => $hist]);
        vr_admin_audit('order_refunded', ['order' => $o['number']]);
        vr_flash('Als erstattet vermerkt (Buchung). Die Rückzahlung selbst erfolgt bei Stripe.', 'ok');
    } elseif ($act === 'resend' && (string)$o['status'] === 'paid') {
        try { vr_mail_order_buyer($o); $push('mail_resent'); vr_order_update((string)$o['id'], ['history' => $hist]); vr_flash('Bestellbestätigung erneut gesendet.', 'ok'); }
        catch (Throwable $e) { vr_flash('Versand fehlgeschlagen: ' . $e->getMessage(), 'err'); }
        vr_admin_audit('order_resend_mail', ['order' => $o['number']]);
    }
    vr_redirect('admin/order.php', ['id' => $o['id']]);
}

$addr = (array)($o['customer']['address'] ?? []);
$cl   = (array)($o['client'] ?? []);
$cust = vr_admin_customer_by_mail((string)($o['customer']['email'] ?? ''));
$others = array_values(array_filter(vr_orders(), fn($x) => $x['id'] !== $o['id'] && strtolower((string)($x['customer']['email'] ?? '')) === strtolower((string)($o['customer']['email'] ?? '')) && (string)($o['customer']['email'] ?? '') !== ''));
[$dev, $br, $os] = vr_stats_ua((string)($cl['ua'] ?? ''));

$isCrypto = vr_admin_paymethod($o) === 'crypto';
$ox       = (array)($o['oxapay'] ?? []);
$timeline = [['at' => (int)$o['created_at'], 'what' => 'Bestellung angelegt', 'on' => true]];
if (!empty($o['paid_at'])) $timeline[] = ['at' => (int)$o['paid_at'], 'what' => 'Zahlung eingegangen (' . ($isCrypto ? 'Krypto · OxaPay' : 'Karte · Stripe') . ')', 'on' => true];
if (!empty($o['failed_at']) && in_array((string)$o['status'], ['failed', 'cancelled'], true)) {
    $why = ['oxapay_expired' => 'Krypto-Rechnung abgelaufen', 'oxapay_failed' => 'Krypto-Zahlung fehlgeschlagen'][(string)($o['failed_reason'] ?? '')] ?? (string)($o['failed_reason'] ?? '');
    $timeline[] = ['at' => (int)$o['failed_at'], 'what' => 'Nicht bezahlt' . ($why !== '' ? ' — ' . $why : ''), 'on' => true];
}
foreach ((array)($o['history'] ?? []) as $hx) {
    $label = ['shipped' => 'Versendet', 'unshipped' => 'Versandvermerk entfernt', 'cancelled' => 'Storniert', 'refunded' => 'Erstattet', 'mail_resent' => 'Bestätigung erneut gesendet'][$hx['what']] ?? $hx['what'];
    $timeline[] = ['at' => (int)$hx['at'], 'what' => $label . (($hx['note'] ?? '') !== '' ? ' — ' . $hx['note'] : '') . ' · ' . $hx['who'], 'on' => true];
}
if (!empty($o['shipped_at']) && !array_filter((array)($o['history'] ?? []), fn($h) => $h['what'] === 'shipped')) $timeline[] = ['at' => (int)$o['shipped_at'], 'what' => 'Versendet', 'on' => true];
usort($timeline, fn($a, $b) => $a['at'] <=> $b['at']);

$actions = '';
if ((string)$o['status'] === 'paid') $actions .= vr_admin_button('resend', [], 'Bestätigung erneut senden', 'btn btn--ghost btn--sm', 'Bestellbestätigung an den Kunden erneut senden?');
vr_admin_head('Bestellung ' . $o['number'], ['sub' => vr_date((int)$o['created_at'], true) . ' · ' . ($o['customer']['email'] ?? ''), 'back' => [vr_url('admin/orders.php'), 'Bestellungen'], 'actions' => $actions, 'nav' => 'orders.php']);
?>
<?php vr_admin_stats([
  'Status'    => [strip_tags(vr_admin_order_pill($o)), $isCrypto ? 'Krypto · OxaPay' : 'Karte · Stripe'],
  'Summe'     => [vr_money((int)$o['total_cents']), 'davon MwSt. ' . vr_money((int)($o['vat_cents'] ?? 0))],
  'Provision' => [vr_money((int)($o['commission_cents'] ?? 0)), in_array((string)($o['payout_mode'] ?? ''), ['', 'none'], true) ? 'Eigenware — keine Auszahlung' : (string)$o['payout_mode']],
  'Land'      => [(string)($o['ship_country'] ?? '—'), vr_country_name((string)($o['ship_country'] ?? ''))],
]); ?>

<div class="agrid agrid--21">
  <div class="agrid">
    <?php vr_admin_card_open('Artikel', '', 'acard--pad0'); ?>
      <div class="tablewrap"><table class="table"><tbody>
      <?php foreach ((array)$o['lines'] as $l): $p = ($l['pid'] ?? '') !== '' ? vr_product((string)$l['pid']) : null; ?>
        <tr>
          <td style="width:52px"><?php if ($p): ?><img class="athumb" src="<?= h(vr_img(vr_card_image($p), 180)) ?>" alt=""><?php endif; ?></td>
          <td><?php if ($p): ?><a href="<?= h(vr_url('admin/product.php', ['id' => $p['id']])) ?>"><?= h((string)($l['brand'] ?? '') . ' ' . (string)($l['name'] ?? '')) ?></a><?php else: ?><?= h((string)($l['brand'] ?? '') . ' ' . (string)($l['name'] ?? '')) ?><?php endif; ?>
            <br><span class="asmall"><?= h(implode(' · ', array_filter([(string)($l['colour'] ?? ''), ($l['size'] ?? '') !== '' ? 'Größe ' . $l['size'] : '', (string)($l['pid'] ?? ''), !empty($l['vault']) ? 'Vault' : '', ($l['seller_type'] ?? '') === 'private' ? 'Privatverkauf' : '']))) ?></span></td>
          <td class="anum"><?= (int)($l['qty'] ?? 1) ?> × <?= h(vr_money((int)($l['unit_cents'] ?? 0))) ?></td>
        </tr>
      <?php endforeach; ?>
      <tr><td></td><td>Versand<?= ($o['ship_label'] ?? '') !== '' ? ' · ' . h((string)$o['ship_label']) : '' ?></td><td class="anum"><?= h(vr_money((int)$o['ship_cents'])) ?></td></tr>
      <?php if ((int)($o['discount_cents'] ?? 0) > 0): ?><tr><td></td><td>Gutschein <?= h((string)($o['voucher_code'] ?? '')) ?></td><td class="anum abad">−<?= h(vr_money((int)$o['discount_cents'])) ?></td></tr><?php endif; ?>
      <tr><td></td><td><b>Gesamt</b></td><td class="anum"><b><?= h(vr_money((int)$o['total_cents'])) ?></b></td></tr>
      </tbody></table></div>
    <?php vr_admin_card_close(); ?>

    <?php vr_admin_card_open('Kunde', $cust ? '<a href="' . h(vr_url('admin/customer.php', ['id' => $cust['id']])) . '">Konto öffnen →</a>' : vr_admin_pill('Gastbestellung', 'muted')); ?>
      <div class="agrid agrid--2">
        <dl class="adl">
          <dt>Name</dt><dd><?= h((string)($o['customer']['name'] ?? '—')) ?></dd>
          <dt>E-Mail</dt><dd><a href="mailto:<?= h((string)($o['customer']['email'] ?? '')) ?>"><?= h((string)($o['customer']['email'] ?? '—')) ?></a></dd>
          <dt>Sprache</dt><dd><?= h(strtoupper((string)($o['lang'] ?? ''))) ?></dd>
          <dt>Weitere Bestellungen</dt><dd><?php if ($others): foreach ($others as $x): ?><a href="<?= h(vr_url('admin/order.php', ['id' => $x['id']])) ?>"><?= h($x['number']) ?></a> <?= vr_admin_order_pill($x) ?><br><?php endforeach; else: ?>keine<?php endif; ?></dd>
        </dl>
        <dl class="adl">
          <dt>Lieferadresse</dt><dd><?php if (array_filter($addr)): ?>
            <?= h(trim((string)($addr['line1'] ?? ''))) ?><?= trim((string)($addr['line2'] ?? '')) !== '' ? '<br>' . h(trim((string)$addr['line2'])) : '' ?><br>
            <?= h(trim(((string)($addr['postal_code'] ?? '')) . ' ' . ((string)($addr['city'] ?? '')))) ?><br>
            <?= h(vr_country_name((string)($addr['country'] ?? $o['ship_country'] ?? ''))) ?>
          <?php else: ?><span class="asmall"><?= $isCrypto ? 'nicht erfasst' : 'noch nicht erfasst (kommt mit der Zahlung von Stripe)' ?></span><?php endif; ?></dd>
        </dl>
      </div>
    <?php vr_admin_card_close(); ?>

    <?php vr_admin_card_open('Browser & Herkunft der Bestellung'); ?>
      <dl class="adl">
        <dt>IP-Adresse</dt><dd><?php $ip = (string)($cl['ip'] ?? ''); echo $ip !== '' ? '<a class="amono" href="' . h(vr_url('admin/visitors.php', ['ip' => $ip])) . '">' . h($ip) . '</a> ' . vr_admin_flag((string)($cl['cc'] ?? '')) : '— (ältere Bestellung)'; ?></dd>
        <dt>Gerät</dt><dd><?= h($cl ? $dev . ' · ' . $br . ' · ' . $os : '—') ?></dd>
        <dt>Kennung</dt><dd class="amono asmall"><?= h((string)($cl['ua'] ?? '—')) ?></dd>
        <?php if (($cl['cc'] ?? '') !== '' && strtoupper((string)$cl['cc']) !== strtoupper((string)($o['ship_country'] ?? ''))): ?>
          <dt>Hinweis</dt><dd class="abad">Lieferland (<?= h((string)$o['ship_country']) ?>) und IP-Land (<?= h((string)$cl['cc']) ?>) weichen ab.</dd>
        <?php endif; ?>
      </dl>
    <?php vr_admin_card_close(); ?>

    <?php if (!empty($o['by_seller'])): ?>
      <?php vr_admin_card_open('Auszahlung · ' . (string)($o['payout_mode'] ?? ''), '', 'acard--pad0'); ?>
        <?php if (!empty($o['payout_pending'])): ?><div class="flash flash--err" style="margin:12px 18px">Auszahlung offen: Der Verkäufer war bei Zahlungseingang noch nicht bei Stripe freigeschaltet.</div><?php endif; ?>
        <div class="tablewrap"><table class="table"><thead><tr><th>Verkäufer</th><th class="anum">Anteil</th><th class="anum">Provision</th><th>Transfer</th></tr></thead><tbody>
        <?php foreach ((array)$o['by_seller'] as $sid => $s): ?>
          <tr><td><?= h((string)($s['label'] ?? $sid)) ?></td><td class="anum"><?= h(vr_money((int)($s['gross_cents'] ?? 0))) ?></td><td class="anum"><?= h(vr_money((int)($s['fee_cents'] ?? 0))) ?></td><td class="amono asmall"><?= h((string)(($o['transfers'][$sid] ?? '') ?: '—')) ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
      <?php vr_admin_card_close(); ?>
    <?php endif; ?>
  </div>

  <div class="agrid">
    <?php vr_admin_card_open('Verlauf'); ?>
      <ul class="atl"><?php foreach ($timeline as $t): ?><li class="is-on"><?= h($t['what']) ?><small><?= h(vr_date((int)$t['at'], true)) ?></small></li><?php endforeach; ?></ul>
    <?php vr_admin_card_close(); ?>

    <?php vr_admin_card_open('Versand'); ?>
      <?php if (!empty($o['shipped_at'])): ?>
        <p style="margin:0 0 12px;font-size:13.5px">Versendet am <?= h(vr_date((int)$o['shipped_at'], true)) ?><?= ($o['carrier'] ?? '') !== '' ? ' · ' . h((string)$o['carrier']) : '' ?><?= ($o['tracking'] ?? '') !== '' ? '<br><span class="amono">' . h((string)$o['tracking']) . '</span>' : '' ?></p>
        <?= vr_admin_button('unship', [], 'Vermerk entfernen', 'btn btn--ghost btn--sm', 'Versandvermerk entfernen?') ?>
      <?php elseif ((string)$o['status'] === 'paid'): ?>
        <form class="form" method="post"><?= vr_csrf_field() ?><input type="hidden" name="action" value="ship">
          <?= vr_admin_field('Versanddienst', '<input name="carrier" type="text" placeholder="DHL, UPS, GLS …" list="carriers"><datalist id="carriers"><option>DHL</option><option>DHL Express</option><option>UPS</option><option>GLS</option><option>DPD</option><option>Hermes</option><option>FedEx</option></datalist>') ?>
          <?= vr_admin_field('Sendungsnummer', '<input name="tracking" type="text">') ?>
          <button class="btn btn--sm" type="submit"><span>Als versendet vermerken</span></button>
        </form>
      <?php else: ?><p class="asmall">Erst nach Zahlungseingang.</p><?php endif; ?>
    <?php vr_admin_card_close(); ?>

    <?php if ($isCrypto): ?>
      <?php
        // OxaPay durum dizgeleri (küçük harf) → Almanca etiket + ton.
        $oxs = strtolower((string)($ox['status'] ?? ''));
        [$oxLabel, $oxTone] = [
            'new' => ['Rechnung erstellt', 'muted'], 'waiting' => ['wartet auf Zahlung', 'muted'], 'paying' => ['Zahlung unterwegs', 'warn'],
            'confirming' => ['wird bestätigt', 'warn'], 'paid' => ['bezahlt', 'ok'], 'expired' => ['abgelaufen', 'bad'], 'failed' => ['fehlgeschlagen', 'bad'],
            'refunding' => ['Erstattung läuft', 'warn'], 'refunded' => ['erstattet', 'warn'],
        ][$oxs] ?? [$oxs !== '' ? $oxs : '—', 'muted'];
        $oxAt = (int)($ox['synced_at'] ?? 0) ?: (int)($ox['updated_at'] ?? 0);
      ?>
      <?php vr_admin_card_open('Zahlung · Krypto (OxaPay)', vr_admin_paym($o)); ?>
        <dl class="adl">
          <dt>OxaPay-Status</dt><dd><?= vr_admin_pill($oxLabel, $oxTone) ?></dd>
          <dt>Track-ID</dt><dd class="amono asmall"><?= h((string)($ox['track_id'] ?? '') ?: '—') ?></dd>
          <dt>Bezahlt mit</dt><dd class="amono asmall"><?= h((string)($ox['paid_crypto'] ?? '') ?: '—') ?></dd>
          <dt>Bezahlt</dt><dd><?= !empty($o['paid_at']) ? h(vr_date((int)$o['paid_at'], true)) : '—' ?></dd>
          <dt>Zuletzt geprüft</dt><dd class="asmall"><?= $oxAt ? h(vr_admin_ago($oxAt)) : '—' ?></dd>
        </dl>
        <p class="anote" style="margin-top:10px">Coin, Netz und Transaktion stehen im OxaPay-Panel unter dieser Track-ID. Offene Rechnungen werden automatisch bei OxaPay abgefragt.</p>
      <?php vr_admin_card_close(); ?>
    <?php else: ?>
      <?php vr_admin_card_open('Zahlung · Karte (Stripe)', vr_admin_paym($o)); ?>
        <dl class="adl">
          <dt>Session</dt><dd class="amono asmall"><?= h((string)($o['stripe']['session_id'] ?? '') ?: '—') ?></dd>
          <dt>PaymentIntent</dt><dd class="amono asmall"><?php $pi = (string)($o['stripe']['payment_intent'] ?? ''); echo $pi !== '' ? '<a href="https://dashboard.stripe.com/payments/' . h($pi) . '" target="_blank" rel="noopener">' . h($pi) . '</a>' : '—'; ?></dd>
          <dt>Charge</dt><dd class="amono asmall"><?= h((string)($o['stripe']['charge_id'] ?? '') ?: '—') ?></dd>
          <dt>Bezahlt</dt><dd><?= !empty($o['paid_at']) ? h(vr_date((int)$o['paid_at'], true)) : '—' ?></dd>
        </dl>
      <?php vr_admin_card_close(); ?>
    <?php endif; ?>

    <?php vr_admin_card_open('Interne Notiz'); ?>
      <form class="form" method="post"><?= vr_csrf_field() ?><input type="hidden" name="action" value="note">
        <div class="field"><textarea name="note" rows="4" placeholder="Nur hier sichtbar"><?= h((string)($o['admin_note'] ?? '')) ?></textarea></div>
        <button class="btn btn--ghost btn--sm" type="submit"><span>Speichern</span></button>
      </form>
    <?php vr_admin_card_close(); ?>

    <?php if (in_array((string)$o['status'], ['paid', 'pending'], true)): ?>
      <?php vr_admin_card_open('Stornieren / Erstattung buchen', '', 'adanger'); ?>
        <p class="anote">Storno setzt den Status und gibt den Bestand frei. Geld wird hier nicht bewegt — die Rückzahlung läuft im <?= $isCrypto ? 'OxaPay-Panel' : 'Stripe-Dashboard' ?>.</p>
        <form class="form" method="post" data-confirm="Bestellung <?= h($o['number']) ?> wirklich stornieren?" style="margin-top:10px"><?= vr_csrf_field() ?>
          <div class="field"><input name="reason" type="text" placeholder="Grund (für den Verlauf)"></div>
          <div class="afilter">
            <button class="btn btn--danger btn--sm" type="submit" name="action" value="cancel"><span>Stornieren</span></button>
            <?php if ((string)$o['status'] === 'paid'): ?><button class="btn btn--ghost btn--sm" type="submit" name="action" value="refunded"><span>Als erstattet buchen</span></button><?php endif; ?>
          </div>
        </form>
      <?php vr_admin_card_close(); ?>
    <?php endif; ?>
  </div>
</div>
<?php vr_admin_foot(); ?>
