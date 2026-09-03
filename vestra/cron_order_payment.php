<?php
/**
 * VESTRA — ödeme hatırlatma / otomatik iptal (cron / CLI).
 *
 * Operatör kararı, 2 Eylül 2026 (order INV-2026-1001 / Daymond Proconect
 * vesilesiyle): "siparişlerin ödemesi 5 iş günü içerisinde gelmez ise otomatik
 * kapanacağını söyle, eğer ödeme yaptıysa havale dekontunu... bize göndersin."
 *
 * Kapsam: status='pending' + FATURASI KESİLMİŞ (vestra_order_in_review()
 * FALSE) + escrow DEĞİL (kart/escrow zaten Stripe üzerinden anında ödeniyor;
 * bu kural yalnızca havale bekleyen faturalı siparişler için).
 *
 * Saat, ilk mektup ve karar mantığı TEK yerde: vestra_order_payment_grace() +
 * vestra_order_payment_reminder_send() (inc/orders.php) -- burada yeniden
 * yazılmaz. Aynı gönderici operatörün tek seferlik mektubunda da kullanılıyor
 * (buyer_reply → payment_due), yani mektubun verdiği söz ile saati gerçekten
 * işleten kod AYNI.
 *
 * İLK MEKTUP GİTMEDEN İPTAL YOK (cron_seller_docs.php'yle aynı ilke — bkz.
 * oradaki not). DEKONT YÜKLENMİŞSE SAAT DURUR: operatör bakmadan otomatik
 * iptal olmaz (vestra_order_payment_grace()'in 'has_receipt' aşaması).
 *
 * SESSİZ OLDUĞUNDA OPERATÖRE YAZMAZ (cron_pending_accounts.php'yle aynı ilke).
 *
 * Zamanlama: SUNUCU crontab'i, deploy-vestra.yml idempotent kurar.
 * Kullanım: php cron_order_payment.php [--dry-run]
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/inc/products.php';
require_once __DIR__ . '/inc/orders.php';
require_once __DIR__ . '/inc/invoice.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/notify.php';
require_once __DIR__ . '/inc/email_templates.php';

$DRY = in_array('--dry-run', $argv ?? [], true);
$now = time();

$mask = function (string $e): string {
    $at = strrpos($e, '@'); if ($at === false) return $e;
    $lo = substr($e, 0, $at);
    return mb_substr($lo, 0, 2) . str_repeat('*', max(0, mb_strlen($lo) - 2)) . substr($e, $at);
};

$acts = ['started'=>[], 'retried'=>[], 'cancelled'=>[], 'awaiting_review'=>[], 'clear'=>0, 'notapplicable'=>0];

foreach (vestra_read_csv('orders.csv') as $row) {
    $ref = (string)($row['ref'] ?? ''); if ($ref === '') continue;

    /* Her satirda TAZE oku: bu scriptin kendi yazdiklarini da gorsun (ayni
       kosuda iki ref ustuste yazip birbirini ezmesin -- leads.json'daki
       paralel-yazma dersinin sekansiyel hali). */
    $statusNow = (string)((vestra_read_json('order_statuses.json'))[$ref]['status'] ?? 'pending');
    if ($statusNow !== 'pending') { $acts['notapplicable']++; continue; }
    if (vestra_order_in_review($ref, $statusNow)) { $acts['notapplicable']++; continue; }             // fatura yok
    if (str_contains((string)($row['notes'] ?? ''), 'Secure escrow')) { $acts['notapplicable']++; continue; }

    $entry = (vestra_read_json('order_statuses.json'))[$ref] ?? ['status'=>'pending'];
    $g = vestra_order_payment_grace($entry, $now);
    $invs  = vestra_invoices_for_ref($ref);
    if (!$invs) { $acts['notapplicable']++; continue; }   // guvenlik: fatura yoksa hic dokunma
    $invNo = (string)($invs[0]['no'] ?? '');
    $total = (float)($invs[0]['total'] ?? ($row['total'] ?? 0));
    $cur   = (string)($invs[0]['currency'] ?? 'EUR');

    switch ($g['phase']) {
        case 'has_receipt':
            $acts['awaiting_review'][] = $row;
            break;

        case 'unstamped':
            printf("  BASLAT   %-10s %-30s tutar=%s %s\n", $ref, $mask((string)($row['email'] ?? '')), $cur, number_format($total, 2));
            if (!$DRY) vestra_order_payment_reminder_send($ref, $row, $invNo, $total, $cur);
            $acts['started'][] = $row;
            break;

        case 'running':
            if (empty($g['notice_sent'])) {
                printf("  MEKTUP   %-10s ilk uyari eksikti, yeniden gonderiliyor (%d gun kaldi)\n", $ref, (int)$g['days_left']);
                if (!$DRY) vestra_order_payment_reminder_send($ref, $row, $invNo, $total, $cur);
                $acts['retried'][] = $row;
            } else {
                $acts['clear']++;
            }
            break;

        case 'overdue':
            if (empty($g['notice_sent'])) {
                /* Uyarilmadan iptal yok -- posta hatasiysa yeniden dene, saat zaten isliyor. */
                printf("  MEKTUP   %-10s sure doldu ama ilk uyari HIC gitmemis -> once mektup, iptal yok\n", $ref);
                if (!$DRY) vestra_order_payment_reminder_send($ref, $row, $invNo, $total, $cur);
                $acts['retried'][] = $row;
                break;
            }
            printf("  IPTAL    %-10s %-30s son tarih %s doldu\n", $ref, $mask((string)($row['email'] ?? '')), gmdate('Y-m-d', (int)$g['deadline']));
            if (!$DRY) {
                $all = vestra_read_json('order_statuses.json');   // taze oku, sonra yaz
                $all[$ref] = array_merge($all[$ref] ?? [], ['status'=>'cancelled', 'updated_at'=>date('c')]);
                $all[$ref]['history'][] = vestra_order_history_entry('cancelled', 'system',
                    'Auto-cancelled: no payment within '.VESTRA_ORDER_PAYMENT_GRACE_DAYS.' business days');
                vestra_write_json('order_statuses.json', $all);
                if (!empty($row['email'])) {
                    $who = $row['name'] ?: ($row['company'] ?: 'there');
                    [$csubj, $cbody, $copts] = vestra_tpl_order_stage('en', 'cancelled', $who, $ref);
                    vestra_send_mail($row['email'], $csubj, $cbody, '', '', null, '', $copts);
                }
            }
            $row['_total'] = $total; $row['_currency'] = $cur;
            $acts['cancelled'][] = $row;
            break;
    }
}

printf("siparis: normal=%d | temiz(saat isliyor)=%d | BASLATILDI=%d | hatirlatildi=%d | IPTAL EDILDI=%d | dekont bekliyor=%d%s\n",
       $acts['notapplicable'], $acts['clear'], count($acts['started']), count($acts['retried']),
       count($acts['cancelled']), count($acts['awaiting_review']), $DRY ? '   (KURU KOSU: hicbir sey yazilmadi, gonderilmedi)' : '');

if (!$acts['started'] && !$acts['cancelled'] && !$acts['awaiting_review']) { echo "operatore mektup yok (yeni saat, iptal ya da bekleyen dekont yok).\n"; exit(0); }

$subj = [];
if ($acts['started'])         $subj[] = count($acts['started']).' order(s) given '.VESTRA_ORDER_PAYMENT_GRACE_DAYS.' business days to pay';
if ($acts['cancelled'])       $subj[] = count($acts['cancelled']).' order(s) auto-cancelled — unpaid';
if ($acts['awaiting_review']) $subj[] = count($acts['awaiting_review']).' receipt(s) waiting for your review';
$subject = 'VESTRA — '.implode(' · ', $subj);

$label = fn(array $r) => trim((string)($r['company'] ?? '')) ?: trim((string)($r['name'] ?? '')) ?: (string)($r['ref'] ?? '');

$body = '';
if ($acts['started']) {
    $body .= "These pending orders have an issued invoice but no payment yet. Each buyer has been e-mailed "
           . "the ".VESTRA_ORDER_PAYMENT_GRACE_DAYS."-business-day deadline; if nothing arrives, they cancel automatically.\n\n";
    foreach ($acts['started'] as $r) $body .= sprintf("  %s — %s (%s)\n", (string)($r['ref'] ?? ''), $label($r), (string)($r['email'] ?? ''));
    $body .= "\n";
}
if ($acts['cancelled']) {
    $body .= "These orders received no payment within ".VESTRA_ORDER_PAYMENT_GRACE_DAYS." business days of the reminder and have been CANCELLED automatically. Each buyer has been e-mailed.\n\n";
    foreach ($acts['cancelled'] as $r) $body .= sprintf("  %s — %s · %s %s\n", (string)($r['ref'] ?? ''), $label($r), (string)($r['_currency'] ?? ''), number_format((float)($r['_total'] ?? 0), 2));
    $body .= "\n";
}
if ($acts['awaiting_review']) {
    $body .= "These orders have a bank-transfer receipt on file, not yet confirmed. The auto-cancel clock is PAUSED while it sits unreviewed.\n\n";
    foreach ($acts['awaiting_review'] as $r) $body .= sprintf("  %s — %s\n", (string)($r['ref'] ?? ''), $label($r));
    $body .= "\nOpen the order, check the receipt under Payment receipt, then set status to Paid.\n\n";
}
$body .= "Orders: https://vestrasales.com/admin?tab=orders\n\nThis message is only sent on days when something happened.\n";

$to = (string)vestra_cfg('ops_email', 'acerasoft@gmail.com');
if ($DRY) { echo "\n— DRY RUN — gonderilecek adres: ".$mask($to)."\n--- konu ---\n$subject\n"; exit(0); }
$ok = vestra_send_mail($to, $subject, $body, '', 'VESTRA');
echo $ok ? "operator uyarisi gonderildi -> ".$mask($to)."\n" : "OPERATOR UYARISI GONDERILEMEDI\n";
exit($ok ? 0 : 1);
