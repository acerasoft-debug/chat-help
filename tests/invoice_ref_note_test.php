<?php
/* Fatura mektuplarında havale açıklaması notu (operatör, 26 Eki 2026: "ödemenin doğru şekilde
   eşleştirilmesi için banka transferi açıklamasında sadece fatura numarasının belirtildiğinden
   emin olun -- kullanıcıların kendi dilinde, fazla korkutmadan, diğer textin altına, estetik").
   Not ALICININ dilinde, gövdenin ALTINDA, uyarı rengi taşımadan; PDF'in "Payment reference"
   satırı ve mektup cümleleri artık sipariş referansı değil FATURA NUMARASI diyor (çelişki yok). */
$root = dirname(__DIR__);
$ok=0;$bad=0;$t=function($n,$c)use(&$ok,&$bad){ if($c){$ok++;echo "  ok   $n\n";}else{$bad++;echo "  HATA $n\n";} };
require $root.'/vestra/inc/notify.php';

$no = 'INV-2026-1042';
foreach (['en','de','fr','it','es','pt','ru','ar','ja'] as $l) {
  $n = vestra_invoice_ref_note($l, $no);
  $t("$l: başlık + metin, numara içeride, yer tutucu kalmadı", ($n['lang'] ?? '') === $l && ($n['title'] ?? '') !== ''
     && str_contains((string)$n['text'], $no) && !str_contains((string)$n['text'], '{no}'));
}
$t('dil İngilizceden farklı (de)', vestra_invoice_ref_note('de', $no)['text'] !== vestra_invoice_ref_note('en', $no)['text']);
$t('tanınmayan dil -> en', vestra_invoice_ref_note('xx', $no)['lang'] === 'en' && vestra_invoice_ref_note('', $no)['lang'] === 'en');
$t('Arapça sağdan sola', vestra_invoice_ref_note('ar', $no)['dir'] === 'rtl' && vestra_invoice_ref_note('it', $no)['dir'] === 'ltr');
$t('numara yoksa not yok', vestra_invoice_ref_note('it', '') === [] && vestra_invoice_ref_note('it', []) === []);
$t('birden çok belge " / " ile', str_contains(vestra_invoice_ref_note('en', ['INV-1', 'INV-2'])['text'], 'INV-1 / INV-2'));

$body = "Hello,\n\nYour invoice is ready.\n\n— VESTRA";
$opts = ['note' => vestra_invoice_ref_note('it', $no)];
$h = vestra_html_email($body, '', $opts);
$p1 = strpos($h, 'Your invoice is ready'); $p2 = strpos($h, 'Causale del bonifico'); $p3 = strpos($h, 'VESTRASALES');
$t('HTML: not gövdenin ALTINDA, alt bilginin üstünde', $p1 !== false && $p2 > $p1 && $p3 > $p2);
$t('HTML: numara kalın', str_contains($h, '<b style="color:#14110c;white-space:nowrap">'.$no.'</b>'));
$box = vestra_email_note_html($opts);
$t('HTML: sakin renkler (krem zemin, altın kenar), uyarı rengi yok', str_contains($box, '#faf8f3') && str_contains($box, '#c9a86a')
   && !preg_match('/#(e0533a|c0392b|b4451f|ff0000|d9534f)|\bred\b/i', $box));
$t('HTML: notsuz mektup değişmedi', !str_contains(vestra_html_email($body, '', []), '#c9a86a;border-radius:8px'));
$tx = vestra_mail_text_part($body, $opts);
$t('düz metin: not gövdenin altında', strpos($tx, 'Causale del bonifico') > strpos($tx, 'Your invoice is ready') && str_contains($tx, $no));

$r = fn($p) => (string)file_get_contents($root.'/vestra/'.$p);
$inv = $r('inc/invoice.php'); $off = $r('inc/offers.php'); $tpl = $r('inc/email_templates.php');
$t('sipariş faturası mektubu: not alıcının dilinde + fatura no cümlesi', str_contains($inv, '$note = vestra_invoice_ref_note_for($em, $nos)')
   && str_contains($inv, 'quoting only the invoice number as the reference') && !str_contains($inv, 'quoting {$ref} as the reference'));
$t('PDF: ödeme referansı fatura numarası (taslakta sipariş ref)', str_contains($inv, "\$payRef = trim(\$invoiceNo) !== '' ? trim(\$invoiceNo) : trim((string)(\$order['ref'] ?? ''));"));
$t('teklif faturaları: 3 yolda not', substr_count($off, '$note = vestra_invoice_ref_note_for(') === 3);
$t('teklif faturaları: sipariş/teklif ref istenmiyor', !str_contains($off, 'quoting reference {$primary}') && !str_contains($off, "quoting reference \".\$p['meta']['ref']"));
$t('şablonlar: 4 dönüşte not', substr_count($tpl, "\$opts['note'] = vestra_invoice_ref_note(") === 4);
$t('şablonlar: banka cümleleri fatura numarası (4 dil)', substr_count($tpl, '{$invoiceNo} als Verwendungszweck') === 1
   && str_contains($tpl, 'quoting only the invoice number {$invoiceNo}.') && str_contains($tpl, 'indicando únicamente el número de factura {$invoiceNo}'));
$wf = (string)file_get_contents($root.'/.github/workflows/send-campaign-preview.yml');
$t('iş akışı: not son gönderimden ÖNCE alıcının diliyle', strpos($wf, 'vestra_invoice_ref_note_for((string)$to') < strpos($wf, "if (strtolower(\$E('copy')) === 'true') {\n              \$cpTo"));
echo "\n{$ok} gecti, {$bad} KALDI\n"; exit($bad ? 1 : 0);
