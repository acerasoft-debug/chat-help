<?php
/**
 * /llms.txt — yapay zeka asistanlari icin site ozeti (llmstxt.org bicimi).
 *
 * NEDEN (operator, 8 Eki 2026: "chatgpt icin guclu yol ekle"): ChatGPT Eylul 2026'da
 * siteye 353 sayfa gonderdi -- Google disinda en buyuk dis kaynak. Asistan bir
 * kullaniciya "Lacoste toptan nereden alinir" sorusuna cevap verirken sayfayi okuyor;
 * bu dosya ona VESTRA'nin NE oldugunu, KIME sattigini ve hangi sayfanin hangi soruya
 * cevap oldugunu tek yerde, duz metinle veriyor.
 *
 * Her satir CANLI veriden ya da kodun kendi sabitinden: marka ve sayilar katalogdan,
 * escrow tavani ve talep suresi sabitlerden. Elle yazilmis rakam yok -- stok degisince
 * dosya da degisir (KURAL 3: bilinmeyen bir sey yazilmaz).
 */
require __DIR__.'/inc/products.php';
require_once __DIR__.'/inc/seo.php';
if (!defined('VESTRA_CLAIM_DAYS')) require_once __DIR__.'/inc/escrow.php';
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: public, max-age=3600');

$host  = 'https://vestrasales.com';
$prods = vestra_products();
$count = []; $moqs = []; $cats = [];
foreach ($prods as $p) {
    $b = trim((string)($p['brand'] ?? ''));
    if ($b !== '') $count[$b] = ($count[$b] ?? 0) + 1;
    if ((int)($p['moq'] ?? 0) > 0) $moqs[] = (int)$p['moq'];
    $c = trim((string)($p['cat'] ?? ''));
    if ($c !== '' && strcasecmp($c, 'Other') !== 0) $cats[$c] = ($cats[$c] ?? 0) + 1;
}
sort($moqs);
$moqMin = $moqs ? $moqs[0] : 0;
$moqMed = $moqs ? $moqs[intdiv(count($moqs), 2)] : 0;
arsort($cats);
$langs = implode(', ', array_map('vlang_native_name', array_keys(vlang_list())));

$o  = "# VESTRA — B2B fashion wholesale marketplace\n\n";
$o .= "> VESTRA (vestrasales.com) is a business-to-business wholesale marketplace. Retailers — boutiques, multi-brand stores, "
    . "outlets and online shops — buy branded apparel, footwear and accessories in wholesale quantities from verified sellers. "
    . "It is not a consumer shop: accounts are for registered businesses.\n\n";
$o .= "Live catalogue: ".count($prods)." wholesale listings from ".count($count)." brands"
    . ($moqMin ? "; minimum order quantities start at {$moqMin} ".($moqMin === 1 ? 'piece' : 'pieces')." (median {$moqMed}) and are stated on every listing" : '')
    . ". Site languages: {$langs}.\n\n";

$o .= "## How buying works\n\n";
$o .= "- Open a free trade account: {$host}/register — company name, country and VAT / trade registration number.\n";
$o .= "- Wholesale prices, ordering and line sheets are shown to approved business accounts.\n";
$o .= "- Each listing states its MOQ, pack size and size run, and available colours.\n";
$o .= "- Payment: bank transfer against an invoice, or card payment held in escrow (orders up to €".number_format((float)VESTRA_ESCROW_MAX, 0, '.', ',')
    . ") and released to the seller after delivery; issues can be reported within ".(int)VESTRA_CLAIM_DAYS." business days of delivery.\n";
$o .= "- Questions and quotes: support@vestrasales.com — or post a sourcing request: {$host}/requests\n\n";

$o .= "## Brands in stock (live)\n\n";
foreach ($count as $b => $n) {
    $o .= "- [{$b} wholesale]({$host}/wholesale/".vestra_brand_slug($b)."): {$n} listing".($n === 1 ? '' : 's')."\n";
}
$o .= "\n## Categories\n\n";
foreach (array_slice($cats, 0, 30, true) as $c => $n) {
    $o .= "- [{$c} wholesale]({$host}/b2b/".vestra_seo_cat_slug($c)."): {$n}\n";
}
$o .= "\n## Wholesale by destination market\n\n";
foreach (vestra_seo_markets() as $slug => $m) {
    $o .= "- [Wholesale to {$m['name']}]({$host}/wholesale-to/{$slug})\n";
}
$o .= "\n## Key pages\n\n";
foreach ([
    ['/shop', 'Full catalogue, filterable by brand, category and size'],
    ['/register', 'Free trade account for retailers and sellers'],
    ['/faq', 'Frequently asked questions: accounts, MOQ, payment, delivery, returns'],
    ['/help', 'How ordering, invoices and escrow work'],
    ['/requests', 'Buyer sourcing requests — ask for a brand or product that is not listed'],
    ['/seller-invite', 'For brands and stock holders who want to sell on VESTRA'],
    ['/journal', 'Market and brand articles'],
] as [$path, $what]) {
    $o .= "- [{$what}]({$host}{$path})\n";
}
$o .= "\n## Optional\n\n- [Sitemap]({$host}/sitemap.xml)\n";
echo $o;
