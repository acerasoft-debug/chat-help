<?php
/* Teklif faturasina renk dokumu -- KOMUT SATIRI (chat-help seller-products.yml admin_mode=offer_colours).
   Kullanim: php cli_offer_colours.php <O-ref> "<SKU:Renk=Adet,...|allow_invoiced=1 | clear|allow_invoiced=1>" [true=yaz]
   Yazici vestra_offer_set_invoice_colours() (inc/offers.php). Varsayilan KURU KOSU; kimseye mektup gitmez.
   Betik is akisinda degil burada, cunku seller-products.yml GitHub'in ~512 KB dosya sinirinda (19 Eki 2026). */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors','1'); error_reporting(E_ALL);
$doc = __DIR__;
require_once $doc."/inc/products.php";
require_once $doc."/inc/orders.php";
require_once $doc."/inc/invoice.php";
require_once $doc."/inc/offers.php";
if (!function_exists('vestra_offer_set_invoice_colours')) { fwrite(STDERR, "sunucuda vestra_offer_set_invoice_colours() YOK -- once deploy\n"); exit(1); }

$ref  = preg_replace('/[^A-Za-z0-9_-]/', '', trim((string)($argv[1] ?? '')));
$spec = trim((string)($argv[2] ?? ''));
$APPLY = strtolower(trim((string)($argv[3] ?? ''))) === 'true';
if ($ref === '' || $spec === '') { fwrite(STDERR, "issue_ref ve payload gerekli ('SKU:Renk=Adet,...' ya da 'clear')\n"); exit(1); }
$allowInv = (bool)preg_match('/(^|\|)\s*allow_invoiced\s*=\s*1\s*(\||$)/i', $spec);
$spec = trim((string)preg_replace('/\|?\s*allow_invoiced\s*=\s*1\s*/i', '', $spec), " |");

$row = vestra_offer_row($ref);
if (!$row) { fwrite(STDERR, "teklif bulunamadi: {$ref}\n"); exit(1); }
$split = [];
if (strtolower($spec) !== 'clear') {
  $pos = strpos($spec, ':');
  if ($pos === false) { fwrite(STDERR, "bicim: SKU:Renk=Adet,Renk=Adet\n"); exit(1); }
  $sku = trim(substr($spec, 0, $pos));
  if (strcasecmp($sku, trim((string)($row['sku'] ?? ''))) !== 0) { fwrite(STDERR, "SKU {$sku} bu teklifin SKU'su degil (".($row['sku'] ?? '').")\n"); exit(1); }
  foreach (array_filter(array_map('trim', explode(',', substr($spec, $pos + 1)))) as $seg) {
    if (!preg_match('/^(.+?)\s*=\s*(\d+)$/', $seg, $m)) { fwrite(STDERR, "her renk Renk=Adet olmali: '{$seg}'\n"); exit(1); }
    if (array_key_exists(trim($m[1]), $split)) { fwrite(STDERR, "renk iki kez: {$m[1]}\n"); exit(1); }
    $split[trim($m[1])] = (int)$m[2];
  }
}
$rec = (array)(vestra_read_json('offer_responses.json')[$ref] ?? []);
echo "teklif       : {$ref} · SKU ".($row['sku'] ?? '')." · adet ".(int)($row['qty'] ?? 0)." · durum ".($rec['status'] ?? '-')."\n";
$prev = vestra_offer_invoice_colours($ref, $rec);
echo "mevcut dokum : ".($prev ? implode(', ', array_map(fn($c, $q) => "{$c}×{$q}", array_keys($prev), $prev)) : '(yok)')."\n";

$r = vestra_offer_set_invoice_colours($ref, $split, !$APPLY, $allowInv);
if (!empty($r['error'])) { fwrite(STDERR, "\n".($APPLY ? 'YAZILMADI' : 'ENGEL').": ".$r['error']."\n"); exit(1); }
echo "istenen      : ".($r['colours'] ? implode(', ', array_map(fn($c, $q) => "{$c}×{$q}", array_keys($r['colours']), $r['colours'])) : '(dokum KALDIRILIR)')."\n";
echo "belgede      : ".($r['cells'] ? implode(', ', $r['cells']) : '(renk sutunu bos)')."\n";
echo "kesilmis fatura: ".($r['invoices'] ? implode(', ', $r['invoices']) : 'yok')."\n";
if (!$APPLY) { echo "\nKURU KOSU -- hicbir sey yazilmadi; move_apply=true ile uygulanir.\n"; exit(0); }
echo "\nYAZILDI (yedek: ".basename((string)($r['backup'] ?? '')).")\n";
echo "fatura yukunun gordugu: ".implode(', ', (array)($r['payload_colours'] ?? []))."   (geri okundu)\n";
if (!empty($r['must_redraft'])) {
  echo "*** YAPILACAK: fatura KESILMIS -- AYNI numarayla yeniden cizin (KURAL 5f): site deposundaki\n";
  echo "    send-campaign-preview.yml reply_letter=invoice_draft, spec ref=".$r['redraw_ref']."|apply=true|notify=false\n";
}