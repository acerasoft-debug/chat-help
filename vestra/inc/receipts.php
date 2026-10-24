<?php
/**
 * VESTRA — payment receipt uploads (bank-transfer proof), one per ORDER rather than
 * per account. This is the segment the operator asked for on 2 Sep 2026, alongside the
 * 5-business-day payment reminder (inc/orders.php): "eğer ödeme yaptıysa havale
 * dekontunu... bir segmente [koysun] ve bize göndersin" — a buyer whose bank transfer
 * is already on its way needs a place to prove it that isn't just "reply to an e-mail
 * and hope".
 *
 * Validation is auth.php's document rules (auth_doc_allowed_ext / auth_doc_max_bytes /
 * auth_doc_file_check) — the SAME "10 MB, PDF/JPG/PNG/WebP/HEIC" limits already taught
 * to buyers via the KYC upload, not a second copy that can drift from the first.
 */
require_once __DIR__.'/auth.php';

define('VESTRA_RECEIPTS_DIR', __DIR__.'/../data/receipts');

function vestra_receipt_dir(string $ref): string {
    $base = VESTRA_RECEIPTS_DIR;
    if (!is_dir($base)) @mkdir($base, 0755, true);
    $htaccess = $base.'/.htaccess';
    if (!is_file($htaccess)) @file_put_contents($htaccess, "Deny from all\n");
    $safeRef = preg_replace('/[^A-Za-z0-9_-]/', '', $ref);
    $dir = $base.'/'.$safeRef;
    if ($safeRef !== '' && !is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

function vestra_receipt_file_path(string $ref, string $filename): string {
    $safeRef = preg_replace('/[^A-Za-z0-9_-]/', '', $ref);
    return VESTRA_RECEIPTS_DIR.'/'.$safeRef.'/'.basename($filename);
}

/**
 * Stores an uploaded file against an order ref and records it on
 * order_statuses.json[$ref]['payment_receipt']. Does NOT touch the order's status —
 * uploading a receipt is the buyer's claim, not our confirmation; the operator still
 * reviews it and marks the order paid through the existing status control.
 * [ok, error, file] — error codes match auth_doc_error_text().
 */
function vestra_receipt_store(string $ref, ?array $file, string $uploadedBy = 'buyer'): array {
    $ref = trim($ref);
    if ($ref === '') return ['ok'=>false, 'error'=>'nofile', 'file'=>''];
    $code = auth_doc_file_check($file);
    if ($code !== '') {
        error_log('[VESTRA receipt] rejected ref='.$ref.' code='.$code
                 .' err='.(int)($file['error'] ?? -1).' size='.(int)($file['size'] ?? 0)
                 .' ext='.strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION)));
        return ['ok'=>false, 'error'=>$code, 'file'=>''];
    }
    $ext   = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    $dir   = vestra_receipt_dir($ref);
    $fname = 'receipt_'.bin2hex(random_bytes(4)).'.'.$ext;
    if (!is_dir($dir) || !is_writable($dir) || !@move_uploaded_file((string)$file['tmp_name'], $dir.'/'.$fname)) {
        error_log('[VESTRA receipt] store FAILED ref='.$ref.' dir='.$dir
                 .' dir_ok='.(is_dir($dir) ? 'yes' : 'NO').' writable='.(is_writable($dir) ? 'yes' : 'NO')
                 .' tmp='.(is_uploaded_file((string)($file['tmp_name'] ?? '')) ? 'ok' : 'NOT-UPLOADED'));
        return ['ok'=>false, 'error'=>'server', 'file'=>''];
    }
    $all = vestra_read_json('order_statuses.json');
    $entry = $all[$ref] ?? ['status'=>'pending'];
    $entry['payment_receipt'] = ['file'=>$fname, 'uploaded_at'=>date('c'), 'uploaded_by'=>$uploadedBy];
    $entry['history'][] = vestra_order_history_entry((string)($entry['status'] ?? 'pending'), $uploadedBy, 'Payment receipt uploaded ('.$fname.')');
    $all[$ref] = $entry;
    vestra_write_json('order_statuses.json', $all);
    return ['ok'=>true, 'error'=>'', 'file'=>$fname];
}

/**
 * Current receipt for an order, or null. Verifies the file is actually ON DISK — the
 * same double-check diag-live's find_ref already applies to KYB documents: a filename
 * recorded with no file behind it is worse than no record, because it reads as proof
 * that was never actually received.
 */
function vestra_order_receipt(string $ref): ?array {
    $all = vestra_read_json('order_statuses.json');
    $r = $all[$ref]['payment_receipt'] ?? null;
    if (!is_array($r) || empty($r['file'])) return null;
    $r['exists'] = is_file(vestra_receipt_file_path($ref, (string)$r['file']));
    return $r;
}

/**
 * DEKONTU GECERSIZ SAY (24 Eki 2026). Operator: "o havale musteriye geri gelecegi icin
 * yeni bir havale ya da elden odeme icin belge yuklemesi gerek" (VES-EC6D6B0D: dekont
 * yuklendi, havale kapali/haczedilmis hesaptan geri dondu). Dekont DURDUKCA siparis
 * 'has_receipt' fazinda kalir: alici sayfasinda "dekont alindi, onay bekleniyor" yazar,
 * yukleme kutusu cikmaz ve odeme saati DURUR -- yani alici yeni belgeyi yukleyemez.
 *
 * Dosya SILINMEZ (iz kalir): kayit payment_receipts_void[]'e tasinir (neden + kim + ne
 * zaman), tarihceye satir duser. $deadlineTs verilirse odeme saati o ana bitecek sekilde
 * yeniden kurulur ve "uyari gitti" sayilir (operatorun mektubu uyaridir): cron ikinci bir
 * "5 is gunu" mektubu YOLLAMAZ, ama son tarih gecerse otomatik iptal eder.
 * Odenmis / iptal / gonderilmis sipariste DURUR. Yazma geri okunur.
 */
function vestra_receipt_void(string $ref, string $reason, string $by = 'operator', ?int $deadlineTs = null, bool $apply = true, bool $unpay = false): array {
    $ref = trim($ref);
    $out = ['ok' => false, 'error' => '', 'file' => '', 'deadline' => null, 'unpaid' => false];
    if ($ref === '') { $out['error'] = 'ref bos'; return $out; }
    $all = vestra_read_json('order_statuses.json');
    $e = (array)($all[$ref] ?? []);
    $r = $e['payment_receipt'] ?? null;
    if (!is_array($r) || empty($r['file'])) { $out['error'] = 'bu sipariste kayitli dekont yok'; return $out; }
    $status = (string)($e['status'] ?? 'pending');
    /* $unpay (24 Eki 2026, VES-B7DDA4D4): siparis panelden 'paid' isaretlenmis ama
       para GELMEMIS (havale kapali hesaptan geri donuyor). Yalniz 'paid' -> 'pending';
       hazirlanmis/gonderilmis siparis bu yoldan geri alinmaz -- mal yola cikmis olabilir. */
    if ($unpay && $status === 'paid') {
        $out['unpaid'] = true;
        $e['history'][] = vestra_order_history_entry('pending', $by, 'Paid mark withdrawn: payment did not arrive ('.mb_substr(trim($reason), 0, 200).')');
        $e['status'] = 'pending';
        unset($e['paid_at']);
        $status = 'pending';
    }
    if ($status !== 'pending') { $out['error'] = "siparis durumu '{$status}' -- yalniz 'pending' sipariste dekont gecersiz sayilir".($status === 'paid' ? " (para gelmediyse unpay=1)" : ''); return $out; }
    if (function_exists('vestra_order_payment_settled') && !empty(vestra_order_payment_settled($ref, $e)['settled'])) {
        $out['error'] = 'siparisin parasi GELMIS gorunuyor -- dekont gecersiz sayilmaz'; return $out;
    }
    $out['file'] = (string)$r['file'];
    $start = null;
    if ($deadlineTs !== null) {
        /* Saat GERIYE dogru kurulur: son tarih = baslangic + N is gunu
           (vestra_order_payment_grace ile ayni hesap, tek kaynak). Son tarihi
           KARSILAYAN en gec baslangic aranir -- soz verilen gunden once bitmesin. */
        if (!function_exists('vestra_business_days_after')) require_once __DIR__.'/escrow.php';
        $n = defined('VESTRA_ORDER_PAYMENT_GRACE_DAYS') ? (int)VESTRA_ORDER_PAYMENT_GRACE_DAYS : 5;
        $start = $deadlineTs - 14 * 86400;
        while (vestra_business_days_after($start, $n) < $deadlineTs) $start += 3600;
        $out['deadline'] = vestra_business_days_after($start, $n);
    }
    if (!$apply) { $out['ok'] = true; return $out; }
    $r['void_at'] = date('c'); $r['void_by'] = $by; $r['void_reason'] = mb_substr(trim($reason), 0, 300);
    $e['payment_receipts_void'] = array_values(array_merge((array)($e['payment_receipts_void'] ?? []), [$r]));
    unset($e['payment_receipt']);
    if ($start !== null) {
        $e['payment_grace_start'] = date('c', $start);
        $e['payment_reminder_sent_at'] = date('c');
    }
    $e['history'][] = vestra_order_history_entry($status, $by, 'Payment receipt voided ('.$out['file'].'): '.$r['void_reason']);
    $e['updated_at'] = date('c');
    $all[$ref] = $e;
    vestra_write_json('order_statuses.json', $all);
    $back = (array)(vestra_read_json('order_statuses.json')[$ref] ?? []);
    if (!empty($back['payment_receipt']) || empty($back['payment_receipts_void'])) { $out['error'] = 'geri okuma tutmadi'; return $out; }
    $out['ok'] = true;
    return $out;
}

/** Buyer-facing upload form — same shape as inc/docs.php's vestra_doc_upload_form(),
 *  keyed by order ref instead of a document-request id, so the two forms share the
 *  same accept list, size limit and client-side photo-shrink script (vestra_doc_upload_js). */
function vestra_receipt_upload_form(string $action, string $ref): string {
    $tr     = fn(string $s) => function_exists('t') ? t($s) : $s;
    $max    = auth_doc_max_bytes();
    $accept = '.'.implode(',.', auth_doc_allowed_ext()).',image/*,application/pdf';
    return '<form method="post" action="'.htmlspecialchars($action).'" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">'
         . '<input type="hidden" name="_action" value="upload_receipt">'
         . '<input type="hidden" name="MAX_FILE_SIZE" value="'.$max.'">'
         . '<input type="hidden" name="ref" value="'.htmlspecialchars($ref).'">'
         . '<input type="file" name="receipt_file" accept="'.htmlspecialchars($accept).'" required data-shrink="1" data-max="'.$max.'" style="font-size:12px;max-width:220px">'
         . '<span class="hint shrinkhint" style="font-size:11px"></span>'
         . '<button class="btn btn-p btn-sm" type="submit">'.htmlspecialchars($tr('Send receipt')).'</button>'
         . '</form>';
}
