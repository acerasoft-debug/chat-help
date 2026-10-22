<?php
/**
 * ChatHelp — apply-mailpdf — send-doc.php'yi PDF-ekli multipart e-postaya cevirir (FAIL-SAFE).
 *   · Saf-PHP PDF ureticisi (harici lib yok, WinAnsi/Almanca kusursuz).
 *   · PDF uretilirse: multipart/mixed (HTML govde + PDF eki). PDF gonderim BASARISIZ/URETILEMEZ ise
 *     -> otomatik ESKI duz-metin e-postaya duser. E-posta HICBIR kosulda bozulmaz.
 *   · Latin-disi belge (iconv WinAnsi'ye cevrilemezse) -> PDF atlanir, metin gider (garbage yok).
 *   .bak alinir + lint edilir.
 * KULLANIM: /chat/pull2.php?key=...&files=apply-mailpdf.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-mailpdf BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/send-doc.php';
$old=@file_get_contents($file);
if($old===false){ echo "  ⚠ send-doc.php okunamadi — yine de yazilacak.\n"; }

$new = <<<'PHPFILE'
<?php
/**
 * ChatHelp — Üretilen dilekçeyi/sözleşmeyi e-posta ile gönder (GoDaddy relay) + PDF eki
 * POST {to, subject, doc}
 * CH_MAILPDF: PDF üretilebilirse multipart/mixed (HTML + PDF eki); değilse düz-metin (fail-safe).
 */
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
error_reporting(0); @ini_set('display_errors','0');

require_once __DIR__ . '/db.php';

$b   = json_decode(file_get_contents('php://input'), true) ?: [];
$to  = trim($b['to'] ?? '');
$sub = trim($b['subject'] ?? 'Ihr Dokument — ChatHelp');
$doc = (string)($b['doc'] ?? '');

if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { echo json_encode(['error' => 'Ungültige E-Mail-Adresse']); exit; }
if (strlen(trim($doc)) < 20)               { echo json_encode(['error' => 'Kein Dokument vorhanden']); exit; }
if (strlen($doc) > 200000)                  $doc = substr($doc, 0, 200000);

$esc  = htmlspecialchars($doc, ENT_QUOTES, 'UTF-8');
$html = '<div style="font-family:Arial,sans-serif;max-width:640px;margin:0 auto;color:#111">'
      . '<h2 style="color:#b8862f">ChatHelp — Ihr Dokument</h2>'
      . '<p>Ihr mit ChatHelp erstelltes Dokument — im Text unten und als <b>PDF im Anhang</b>:</p>'
      . '<pre style="white-space:pre-wrap;font-family:\'Times New Roman\',Georgia,serif;font-size:14px;line-height:1.55;'
      . 'background:#f7f7fb;border:1px solid #e3e3ee;border-radius:10px;padding:20px;color:#15151f">' . $esc . '</pre>'
      . '<p style="color:#888;font-size:12px;margin-top:16px">KI-gestützt erstellt — bei wichtigen Dokumenten empfehlen wir eine anwaltliche Prüfung.</p>'
      . '<p style="color:#aaa;font-size:11px">chat-help.com</p></div>';

/* ── PDF üret (fail-safe): mümkünse multipart PDF ekli, olmazsa düz-metin ── */
$ok  = false;
$pdf = ch_doc_pdf($doc, $sub);
if ($pdf !== '') { $ok = ch_smtp_send($to, $sub, ch_mime_multipart($to, $sub, $html, $pdf, 'ChatHelp-Dokument.pdf')); }
if (!$ok)        { $ok = ch_smtp_send($to, $sub, ch_mime_html($to, $sub, $html)); }
echo json_encode(['success' => $ok, 'sent_to' => $ok ? $to : null, 'pdf' => ($pdf !== '')]);

/* ============================ MIME gövde kurucular ============================ */
function ch_mail_from(): array {
    $from = defined('SMTP_FROM') ? SMTP_FROM : 'support@chat-help.de';
    $name = defined('SMTP_NAME') ? SMTP_NAME : 'ChatHelp';
    return [$from, $name];
}
function ch_mime_headers(string $to, string $subject, string $extra): string {
    [$from, $name] = ch_mail_from();
    return 'From: ' . $name . ' <' . $from . ">\r\n"
         . 'To: <' . $to . ">\r\n"
         . 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n"
         . "MIME-Version: 1.0\r\n" . $extra;
}
function ch_mime_html(string $to, string $subject, string $html): string {
    return ch_mime_headers($to, $subject, "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n")
         . chunk_split(base64_encode($html));
}
function ch_mime_multipart(string $to, string $subject, string $html, string $pdf, string $fname): string {
    $bnd = 'chp_' . bin2hex(random_bytes(8));
    $body  = ch_mime_headers($to, $subject, "Content-Type: multipart/mixed; boundary=\"$bnd\"\r\n\r\n");
    $body .= "--$bnd\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
           . chunk_split(base64_encode($html)) . "\r\n";
    $body .= "--$bnd\r\nContent-Type: application/pdf; name=\"$fname\"\r\n"
           . "Content-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"$fname\"\r\n\r\n"
           . chunk_split(base64_encode($pdf)) . "\r\n";
    $body .= "--$bnd--\r\n";
    return $body;
}

/* ============================ SMTP taşıma (mevcut mantık, değişmedi) ============================ */
function ch_smtp_send(string $to, string $subject, string $body): bool {
    [$from, $name] = ch_mail_from();
    // 1) GoDaddy relay :25
    $fp = @fsockopen('relay-hosting.secureserver.net', 25, $e, $s, 15);
    if ($fp) {
        stream_set_timeout($fp, 15);
        $read = function() use ($fp) { $d=''; while($l=fgets($fp,515)){ $d.=$l; if(isset($l[3])&&$l[3]===' ')break; } return $d; };
        $cmd  = function($c) use ($fp,$read){ fwrite($fp,$c."\r\n"); return $read(); };
        $read(); $cmd('EHLO chat-help.com');
        $cmd('MAIL FROM:<'.$from.'>'); $cmd('RCPT TO:<'.$to.'>'); $cmd('DATA');
        fwrite($fp, $body."\r\n.\r\n"); $final=$read(); $cmd('QUIT'); fclose($fp);
        if (strpos($final,'250')===0) return true;
    }
    // 2) Host Europe STARTTLS yedeği
    if (defined('SMTP_HOST') && defined('SMTP_USER') && defined('SMTP_PASS')) {
        $fp2 = @fsockopen(SMTP_HOST, defined('SMTP_PORT')?SMTP_PORT:587, $e2, $s2, 20);
        if (!$fp2) return false;
        stream_set_timeout($fp2, 20);
        $read = function() use ($fp2){ $d=''; while($l=fgets($fp2,515)){ $d.=$l; if(isset($l[3])&&$l[3]===' ')break; } return $d; };
        $cmd  = function($c) use ($fp2,$read){ fwrite($fp2,$c."\r\n"); return $read(); };
        $read(); $cmd('EHLO chat-help.com'); $cmd('STARTTLS');
        if (!@stream_socket_enable_crypto($fp2, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { fclose($fp2); return false; }
        $cmd('EHLO chat-help.com'); $cmd('AUTH LOGIN'); $cmd(base64_encode(SMTP_USER)); $cmd(base64_encode(SMTP_PASS));
        $cmd('MAIL FROM:<'.$from.'>'); $cmd('RCPT TO:<'.$to.'>'); $cmd('DATA');
        fwrite($fp2, $body."\r\n.\r\n"); $final=$read(); $cmd('QUIT'); fclose($fp2);
        return strpos($final,'250')===0;
    }
    return false;
}

/* ============================ Saf-PHP PDF (harici lib yok, WinAnsi core font) ============================ */
function ch_doc_pdf(string $text, string $title): string {
    // WinAnsi'ye cevrilemeyen (TR ş/ğ/ı, AR, RU...) -> PDF atla, metin gitsin (garbage yok)
    $enc = @iconv('UTF-8', 'Windows-1252', $text);
    if ($enc === false || $enc === null) return '';
    try {
        $enc = str_replace(["\r\n", "\r"], "\n", $enc);
        // satir sarma (~90 karakter, Helvetica 10.5pt A4)
        $MAX = 90; $lines = [];
        foreach (explode("\n", $enc) as $para) {
            if ($para === '') { $lines[] = ''; continue; }
            while (strlen($para) > $MAX) {
                $slice = substr($para, 0, $MAX);
                $cut = strrpos($slice, ' ');
                if ($cut === false || $cut < 45) $cut = $MAX;
                $lines[] = rtrim(substr($para, 0, $cut));
                $para = ltrim(substr($para, $cut));
            }
            $lines[] = $para;
        }
        if (!$lines) $lines = [''];
        $PER = 50; $pages = array_chunk($lines, $PER);
        // ── PDF nesneleri ──
        $objs = []; // 1-indexed sozde; toplayacagiz
        $fontObj = 0; $catalogObj = 0; $pagesObj = 0;
        // nesne numaralarini planla: 1=catalog 2=pages 3=font, sonra her sayfa icin (page + content)
        $catalogObj = 1; $pagesObj = 2; $fontObj = 3;
        $pageObjNums = []; $contentObjNums = [];
        $n = 4;
        foreach ($pages as $i => $pg) { $pageObjNums[$i] = $n++; $contentObjNums[$i] = $n++; }
        $kids = implode(' ', array_map(fn($x) => "$x 0 R", $pageObjNums));
        $set = [];
        $set[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $set[2] = "<< /Type /Pages /Count " . count($pages) . " /Kids [$kids] >>";
        $set[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        foreach ($pages as $i => $pg) {
            $po = $pageObjNums[$i]; $co = $contentObjNums[$i];
            $set[$po] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] "
                      . "/Resources << /Font << /F1 3 0 R >> >> /Contents $co 0 R >>";
            // icerik akisi
            $stream = "BT /F1 10.5 Tf 14.5 TL 56 786 Td\n";
            foreach ($pg as $ln) {
                $s = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $ln);
                $stream .= "($s) Tj T*\n";
            }
            $stream .= "ET";
            $set[$co] = "<< /Length " . strlen($stream) . " >>\nstream\n$stream\nendstream";
        }
        // ── seri hale getir + xref ──
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = []; $maxObj = max(array_keys($set));
        for ($k = 1; $k <= $maxObj; $k++) {
            $offsets[$k] = strlen($out);
            $out .= "$k 0 obj\n" . $set[$k] . "\nendobj\n";
        }
        $xrefPos = strlen($out);
        $out .= "xref\n0 " . ($maxObj + 1) . "\n";
        $out .= "0000000000 65535 f \n";
        for ($k = 1; $k <= $maxObj; $k++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$k]);
        }
        $out .= "trailer\n<< /Size " . ($maxObj + 1) . " /Root 1 0 R >>\nstartxref\n$xrefPos\n%%EOF";
        // sağlık kontrolü
        if (strpos($out, '%PDF-1.4') !== 0 || strlen($out) < 300) return '';
        return $out;
    } catch (\Throwable $e) { return ''; }
}
PHPFILE;

/* lokal lint */
$tmp=tempnam(sys_get_temp_dir(),'md').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "  ✗ LINT HATASI — send-doc.php YAZILMADI:\n  ".implode("\n  ",$lo)."\n"; exit; }
echo "  ✓ yeni send-doc.php lint temiz\n";

if($old!==false){ @file_put_contents($file.'.bak-mailpdf-'.date('Ymd-His'),$old); echo "  ✓ .bak alindi\n"; }
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("  ✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
echo "\n  ✓ send-doc.php guncellendi (".strlen($new)." B)\n";
echo "  · Almanca/Latin belge: e-postada metin + GERCEK PDF eki.\n";
echo "  · PDF uretilemezse/gonderilemezse: otomatik duz-metin (e-posta asla bozulmaz).\n";
echo "  · Latin-disi (TR/AR/RU) belge: PDF atlanir, metin gider (garbage yok).\n";
