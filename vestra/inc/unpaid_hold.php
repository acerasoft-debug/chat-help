<?php
/*
 * ODENMEMIS FATURA ASKISI -- alicinin kendi kendine cikis yolu.
 *
 * Operator, 11 Eki 2026:
 *   "hesabi suspend olan musteriler en az 1 faturasini odeyip hesabini
 *    acabilirler ... digerlerini iptal edebilirler ... kendi dilinde duzgun bir
 *    sekilde hesabina girdiginde aciklayalim"
 *   "hesaplarinda ilk olarak odenmeyen siparislerini gorsunler; bir kac tane
 *    varsa en az birini odemek zorunda olduklari acik dille soylensin"
 *   "digerleri icin iptal butonu ciksin"
 *   "1 siparis olan musteri o siparisini odemek zorunda, iptal et ozelligi cikmaz"
 *
 * KURAL (tek yer, burada):
 *   - Askidaki alici (auth_suspended_for_unpaid) girer ve ILK olarak bu karti gorur.
 *   - Siparislerinden BIRI odendi sayilinca (vestra_order_payment_settled -- panelin,
 *     hatirlatma saatinin ve askiya alma kosusunun AYNI olcusu) hesap kendiliginden
 *     acilir. Cron yok: kontrol alici sayfaya girdiginde ve operator siparisi
 *     "paid" yaptiginda calisir.
 *   - IPTAL yalniz "en az biri odenmek uzere kalir" sarti tutarken: ayni alicinin
 *     odenmemis BASKA acik siparisi varsa ya da bir siparisi zaten odenmisse.
 *     Tek siparisi olan alicida dugme HIC cikmaz ve sunucu da reddeder.
 *   - Iptal edilemeyen: odenmis, dekontu yuklenmis (para yolda olabilir -- once
 *     operator bakar), kart/escrow, 'pending' disinda bir durumdaki siparis.
 *   - Musteriye MEKTUP yok; iptal siparis gecmisine 'buyer' olarak yazilir.
 */
require_once __DIR__.'/products.php';
require_once __DIR__.'/orders.php';
require_once __DIR__.'/invoice.php';
require_once __DIR__.'/auth.php';

/** Alicinin siparisleri, karta hazir: acik (odenmemis, iptal edilmemis) ve odenmis sayisi. */
function vestra_unpaid_hold_state(array $acc): array {
    $email = strtolower(trim((string)($acc['email'] ?? '')));
    $out = ['open' => [], 'paid' => [], 'cancelled' => 0];
    if ($email === '') return $out;
    $all = vestra_read_json('order_statuses.json');
    foreach (vestra_read_csv('orders.csv') as $row) {
        if (strtolower(trim((string)($row['email'] ?? ''))) !== $email) continue;
        $ref = trim((string)($row['ref'] ?? '')); if ($ref === '') continue;
        $e = (array)($all[$ref] ?? ['status' => 'pending']);
        $status = (string)($e['status'] ?? 'pending');
        if (!empty(vestra_order_payment_settled($ref, $e)['settled'])) { $out['paid'][] = $ref; continue; }
        if ($status === 'cancelled') { $out['cancelled']++; continue; }
        $invs = vestra_invoices_for_ref($ref);
        $out['open'][$ref] = [
            'ref' => $ref, 'date' => substr((string)($row['timestamp'] ?? ''), 0, 10), 'status' => $status,
            'receipt' => !empty($e['payment_receipt']),
            'escrow' => str_contains((string)($row['notes'] ?? ''), 'Secure escrow'),
            'invoice' => $invs ? (string)($invs[0]['no'] ?? '') : '',
            'total' => $invs ? (float)($invs[0]['total'] ?? 0) : (float)($row['total'] ?? 0),
            'currency' => $invs ? (strtoupper((string)($invs[0]['currency'] ?? '')) ?: 'EUR') : 'EUR',
        ];
    }
    return $out;
}

/** Bu siparis bu alici tarafindan iptal edilebilir mi? '' = evet, yoksa nedenin anahtari. */
function vestra_unpaid_hold_cancel_block(array $state, string $ref): string {
    $o = $state['open'][$ref] ?? null;
    if (!$o) return 'notfound';
    if ($o['status'] !== 'pending') return 'status';
    if ($o['receipt']) return 'receipt';
    if ($o['escrow']) return 'escrow';
    /* EN AZ BIRI ODENMEK UZERE KALIR. Odenmis siparisi yoksa, bu iptalden sonra
       en az bir acik siparis kalmali -- tek siparisli alicida iptal YOK. */
    if (!$state['paid'] && count($state['open']) < 2) return 'last';
    return '';
}

/** Hesabi odeme gelmisse ac. true = bu cagri acti. */
function vestra_unpaid_hold_try_reopen(array $acc, ?array $state = null): bool {
    if (!auth_suspended_for_unpaid($acc)) return false;
    $state = $state ?? vestra_unpaid_hold_state($acc);
    if (!$state['paid']) return false;
    $kyb = (string)($acc['kyb_status'] ?? '');
    if ($kyb === 'suspended') $kyb = (string)($acc['kyb_status_before_suspend'] ?? '');
    auth_update((string)$acc['id'], ['status' => 'active', 'suspend_reason' => '', 'kyb_status' => $kyb,
        'unsuspended_at' => date('c'), 'unsuspended_by' => 'payment '.$state['paid'][0]]);
    return true;
}

/** Operator bir siparisi odendi yaptiginda: o siparisin alicisi askidaysa ac. */
function vestra_unpaid_hold_reopen_for_ref(string $ref): bool {
    foreach (vestra_read_csv('orders.csv') as $row) {
        if (trim((string)($row['ref'] ?? '')) !== $ref) continue;
        $acc = auth_find((string)($row['email'] ?? ''));
        return $acc ? vestra_unpaid_hold_try_reopen($acc) : false;
    }
    return false;
}

/** Alicinin kendi iptali. ['ok'=>bool,'error'=>anahtar]. */
function vestra_unpaid_hold_cancel(array $acc, string $ref): array {
    $ref = preg_replace('/[^A-Za-z0-9_-]/', '', trim($ref));
    $state = vestra_unpaid_hold_state($acc);
    if (($why = vestra_unpaid_hold_cancel_block($state, $ref)) !== '') return ['ok' => false, 'error' => $why];
    $all = vestra_read_json('order_statuses.json');   // taze oku, sonra yaz
    $all[$ref] = array_merge($all[$ref] ?? [], ['status' => 'cancelled', 'updated_at' => date('c'), 'cancelled_by' => 'buyer']);
    $all[$ref]['history'][] = vestra_order_history_entry('cancelled', 'buyer', 'Cancelled by the buyer from the unpaid-invoices page');
    vestra_write_json('order_statuses.json', $all);
    $back = (string)((vestra_read_json('order_statuses.json'))[$ref]['status'] ?? '');
    return $back === 'cancelled' ? ['ok' => true, 'error' => ''] : ['ok' => false, 'error' => 'write'];
}

/** Para gosterimi: faturanin kendi birimiyle. */
function vestra_unpaid_hold_money(float $v, string $cur): string {
    $sym = $cur === 'USD' ? 'US$ ' : ($cur === 'EUR' ? '€' : $cur.' ');
    return $sym.number_format($v, 2, '.', ',');
}

/** Odenmemis siparisler karti. $hold=true: hesap askida (ilk ekran); false: acik hesap, kalan acik siparisler.
 *  Gorunum style.css'deki .uh-* siniflarinda (panelin kagit/beyaz/altin dili). */
function vestra_unpaid_hold_card(array $acc, array $state, bool $hold): string {
    $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $open = $state['open'];
    $n = count($open);
    if (!$hold && $n === 0) return '';
    /* div, header/section/article DEGIL: style.css'te ciplak `header{}` sitenin sabit
       ust cubugu (koyu, sticky) -- kartin basligi o stili alip okunmaz oluyordu. */
    $o = '<div class="uh-card'.($hold ? ' uh-hold' : '').'" id="unpaid-hold" role="region" aria-labelledby="uh-title">';
    $o .= '<div class="uh-head"><span class="uh-ico" aria-hidden="true">'.($hold ? '⏸' : '🧾').'</span><div>'
        . '<h3 id="uh-title">'.$h($hold ? t('Your account is on hold') : t('Your unpaid orders')).'</h3>'
        . ($hold ? '<p class="uh-sub">'.$h(t('Your account has been paused because the invoices below have not been paid. Nothing is lost — you can reopen it yourself.')).'</p>' : '')
        . '</div></div>';
    if ($hold && $n === 0) {
        $o .= '<p class="uh-callout">'.$h(t('Your unpaid orders have been closed. To reopen your account, please contact us at support@vestrasales.com.')).'</p></div>';
        return $o;
    }
    if ($hold) {
        $o .= '<p class="uh-callout">'.$h($n === 1
              ? t('To reopen your account, you must pay this invoice.')
              : sprintf(t('You have %d unpaid orders. To reopen your account, you must pay at least one of them.'), $n)).'</p>';
        $steps = [
            [t('Pay the invoice'), t('Transfer the amount shown on the invoice, quoting the payment reference printed on it. As soon as the payment is confirmed, your account reopens automatically — you do not need to contact us.')],
            [t('Already paid?'), t('Upload your bank receipt on the order page so we can confirm it faster.')],
        ];
        if ($n > 1) $steps[] = [t('Cancel the others'), t('You can cancel the other orders yourself, free of charge. At least one order must remain and be paid.')];
        $o .= '<ol class="uh-steps">';
        foreach ($steps as $i => [$tt, $tx]) $o .= '<li><span class="uh-num">'.($i + 1).'</span><div><b>'.$h($tt).'</b><span>'.$h($tx).'</span></div></li>';
        $o .= '</ol>';
    }
    $o .= '<div class="uh-list">';
    foreach ($open as $ref => $r) {
        $why = vestra_unpaid_hold_cancel_block($state, $ref);
        $o .= '<div class="uh-row">'
            . '<div class="uh-meta"><span class="uh-ref">'.$h(t('Order')).' #'.$h($ref).'</span>'
            . '<span class="uh-det">'.($r['invoice'] !== '' ? $h(t('Invoice')).' '.$h($r['invoice']) : '').($r['date'] !== '' ? ($r['invoice'] !== '' ? ' · ' : '').$h($r['date']) : '').'</span>'
            . ($r['receipt'] ? '<span class="uh-tag uh-tag-wait">✓ '.$h(t('Receipt received — we are checking it.')).'</span>'
               : ($why === 'last' ? '<span class="uh-tag">'.$h(t('Must be paid')).'</span>' : ''))
            . '</div>'
            . '<div class="uh-amt">'.$h(vestra_unpaid_hold_money($r['total'], $r['currency'])).'</div>'
            . '<div class="uh-act"><a class="btn btn-p btn-sm" href="/buyer?tab=orders&amp;view='.urlencode($ref).'">'.$h(t('View invoice & pay')).'</a>';
        if ($why === '') {
            $o .= '<form method="post" action="/buyer" onsubmit="return confirm('.$h(json_encode(sprintf(t('Cancel order %s? This cannot be undone.'), $ref))).')">'
                . '<input type="hidden" name="_action" value="hold_cancel_order"><input type="hidden" name="ref" value="'.$h($ref).'">'
                . '<button class="btn btn-o btn-sm uh-cancel" type="submit">'.$h(t('Cancel order')).'</button></form>';
        }
        $o .= '</div></div>';
    }
    $o .= '</div>';
    if ($hold) $o .= '<p class="uh-help">'.$h(t('Questions? Write to us at support@vestrasales.com.')).'</p>';
    return $o.'</div>';
}

/** Iptal sonucu bandi (?hold= anahtari). */
function vestra_unpaid_hold_flash(string $key, string $ref = ''): string {
    $msgs = [
        'cancelled' => [true,  sprintf(t('Order %s has been cancelled.'), $ref)],
        'reopened'  => [true,  t('Thank you — your payment has been received and your account is open again.')],
        'last'      => [false, t('This is your last unpaid order: it must be paid to reopen your account, so it cannot be cancelled.')],
        'receipt'   => [false, t('A bank receipt is already on file for this order, so it cannot be cancelled here. Please contact us.')],
    ];
    [$ok, $txt] = $msgs[$key] ?? [false, t('This order cannot be cancelled.')];
    return '<div class="banner'.($ok ? ' ok' : '').'"'.($ok ? '' : ' style="background:rgba(239,154,154,.1);border:1px solid rgba(239,154,154,.35);color:var(--bad);margin-bottom:14px"').'>'
         .($ok ? '✓ ' : '⚠ ').htmlspecialchars($txt).'</div>';
}
