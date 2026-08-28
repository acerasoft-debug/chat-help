<?php
/**
 * VESTRA — Katalog API dokumantasyonu (herkese acik).
 *
 * Neden acik: bir entegrasyonu YAZAN kisi, anahtari ALAN kisi degildir. Belgeyi
 * anahtarin arkasina koymak, gelistiricinin once satin alma yetkilisinden ekran
 * goruntusu istemesi demek olurdu. Burada hicbir sir yok -- yalnizca alan adlari,
 * hangi ucun ne dondurdugu ve neyin OLMADIGI.
 */
$PAGE = 'Catalogue API';
$NAV  = '';
$META = 'VESTRA catalogue API — JSON product feed for wholesale partners: brands, articles, sizes, MOQ, tiered EUR pricing and image URLs, with per-partner keys.';
require __DIR__.'/inc/head.php';
?>
<style>
.apidoc{max-width:860px;margin:0 auto;padding:0 0 60px}
.apidoc h1{font-size:36px;line-height:1.15;margin:0 0 12px}
.apidoc h2{font-size:21px;margin:38px 0 12px;padding-top:20px;border-top:1px solid var(--line)}
.apidoc h3{font-size:15px;margin:24px 0 8px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;color:var(--acc)}
.apidoc p,.apidoc li{color:var(--mut);line-height:1.7;font-size:14.5px}
.apidoc li{margin-bottom:5px}
.apidoc code{font-size:13px;background:rgba(255,255,255,.06);padding:2px 6px;border-radius:5px;color:var(--ink)}
.apidoc pre{background:rgba(255,255,255,.04);border:1px solid var(--line);border-radius:10px;
  padding:14px 16px;overflow-x:auto;font-size:12.5px;line-height:1.65;color:var(--ink);margin:0 0 16px}
.apidoc pre code{background:none;padding:0}
.apidoc table{width:100%;border-collapse:collapse;font-size:13.5px;margin:0 0 16px}
.apidoc th,.apidoc td{text-align:left;padding:8px 10px;border-bottom:1px solid var(--line);vertical-align:top}
.apidoc th{color:var(--ink);font-weight:600;white-space:nowrap}
.apidoc td:first-child{white-space:nowrap;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12.5px;color:var(--ink)}
.apinote{border:1px solid rgba(169,127,44,.45);background:rgba(169,127,44,.07);
  border-radius:10px;padding:14px 16px;margin:0 0 18px}
.apinote p{margin:0;color:var(--ink);font-size:14px}
.apinote p+p{margin-top:8px}
.apiscroll{overflow-x:auto}
</style>

<div class="wrap apidoc">
  <div class="crumbs" style="margin:26px 0 10px"><a href="/">Home</a> · Catalogue API</div>
  <h1>Catalogue API</h1>
  <p>A read-only JSON feed of the VESTRA wholesale catalogue, for partners who want the
     range inside their own shop or ERP rather than in a spreadsheet. Brands, articles,
     size runs, colours, minimum order quantities, tiered wholesale prices in EUR,
     photograph URLs and a link back to each product page.</p>

  <div class="apinote">
    <p><b>Two things this feed does not carry.</b> Both are stated here rather than
       discovered later, because a missing field found after you have written an importer
       costs more than one found before.</p>
    <p><b>No live stock.</b> Per-unit inventory is not tracked, so every product returns
       <code>"stock": {"tracked": false, "quantity": null}</code>. Do not resell against a
       quantity from this feed — confirm availability before you promise a delivery date.</p>
    <p><b>No EAN / GTIN.</b> Barcode data is not held for this catalogue, so there is no
       barcode field at all. If your marketplace requires an EAN to list, this feed alone
       will not satisfy it.</p>
  </div>

  <h2>Getting a key</h2>
  <p>Keys are issued per partner to verified trade accounts, because the feed carries
     wholesale pricing. Write to <a class="acc" href="mailto:support@vestrasales.com">support@vestrasales.com</a>
     from the address on your account. A key looks like <code>vsk_…</code>. We store only a
     one-way hash of it, so it appears in full exactly once — at the moment it is issued.
     Nobody here can read it back to you afterwards; if it goes missing we revoke it and
     issue another.</p>
  <p>Send it on every request:</p>
  <pre><code>curl -H "Authorization: Bearer vsk_your_key_here" \
  "https://vestrasales.com/api/catalog?a=whoami"</code></pre>
  <p>A missing or revoked key returns <code>401</code> with
     <code>{"ok": false, "error": "unauthorized"}</code>. Revocation takes effect
     immediately.</p>

  <h2>Endpoints</h2>

  <h3>GET /api/catalog?a=products</h3>
  <p>The catalogue, paginated. Parameters, all optional:</p>
  <div class="apiscroll"><table>
    <tr><th>Parameter</th><th>Meaning</th></tr>
    <tr><td>page</td><td>1-based page number. Default <code>1</code>.</td></tr>
    <tr><td>per</td><td>Items per page, 1–200. Default <code>100</code>.</td></tr>
    <tr><td>brand</td><td>Exact brand name, case-insensitive. See <code>a=brands</code>.</td></tr>
    <tr><td>cat</td><td>Exact category name, case-insensitive.</td></tr>
    <tr><td>since</td><td>Any date your language can format, e.g. <code>2026-08-01</code>. Returns articles added on or after it — use it for incremental syncs instead of pulling the whole catalogue each night.</td></tr>
  </table></div>
  <p>The response carries <code>next_page</code> so you do not have to compute it; it is
     <code>null</code> on the last page. Walking until <code>next_page</code> is null is
     the whole of correct pagination here.</p>
  <pre><code>{
  "ok": true,
  "generated_at": "2026-08-27T12:40:11+00:00",
  "page": 1, "per_page": 100, "pages": 4, "total": 344,
  "next_page": 2,
  "items": [ { … } ]
}</code></pre>

  <h3>GET /api/catalog?a=product&amp;id=<i>id</i></h3>
  <p>One article, same shape as an item above, under <code>item</code>.
     Unknown id returns <code>404</code>.</p>

  <h3>GET /api/catalog?a=brands</h3>
  <p>Every brand in the catalogue with a product count — useful for building your own
     filter, and for checking a brand name before passing it to <code>brand=</code>.</p>

  <h3>GET /api/catalog?a=whoami</h3>
  <p>Confirms the key works and reports which partner it belongs to, the field list, and
     the same “not available” notes as above. Start here when wiring up.</p>

  <h2>The product object</h2>
  <div class="apiscroll"><table>
    <tr><th>Field</th><th>Type</th><th>Notes</th></tr>
    <tr><td>id</td><td>string</td><td>Stable VESTRA reference. Use it as your foreign key.</td></tr>
    <tr><td>sku</td><td>string</td><td>Supplier article number. May be empty on older listings.</td></tr>
    <tr><td>brand</td><td>string</td><td></td></tr>
    <tr><td>name</td><td>string</td><td></td></tr>
    <tr><td>category</td><td>string</td><td></td></tr>
    <tr><td>description</td><td>string</td><td>Plain text.</td></tr>
    <tr><td>unit</td><td>string</td><td>Usually <code>pc</code>; some lines are sold in packs or boxes.</td></tr>
    <tr><td>moq</td><td>integer</td><td>Minimum order quantity in <code>unit</code>.</td></tr>
    <tr><td>sizes</td><td>string</td><td>The size run as written on the listing, e.g. <code>S–XXL</code> or <code>44–54</code>. Free text, not a list — it also carries pack rules where a line is sold in size runs.</td></tr>
    <tr><td>colours</td><td>string[]</td><td>May be empty.</td></tr>
    <tr><td>currency</td><td>string</td><td>Always <code>EUR</code>. See below.</td></tr>
    <tr><td>pricing</td><td>string</td><td><code>fixed</code>, <code>sale</code>, or <code>on_request</code>. On <code>on_request</code> lines the tiers are indicative and the real number comes from an offer.</td></tr>
    <tr><td>price_tiers</td><td>object[]</td><td><code>[{"min_qty":20,"price":34.00}, …]</code>, ascending. The price that applies is the one for the highest <code>min_qty</code> your quantity reaches.</td></tr>
    <tr><td>price_from</td><td>number|null</td><td>Convenience: the first tier's price.</td></tr>
    <tr><td>list_price</td><td>number|null</td><td>Pre-discount price where a line is on sale.</td></tr>
    <tr><td>rrp</td><td>number|null</td><td>Brand recommended retail, where known.</td></tr>
    <tr><td>origin</td><td>string</td><td>Provenance as declared by the seller, e.g. <code>EEA stock · proof on request</code>.</td></tr>
    <tr><td>seller</td><td>string</td><td><code>via VESTRA</code> where the seller has chosen not to be named publicly.</td></tr>
    <tr><td>images</td><td>string[]</td><td>Absolute URLs. First image is the primary.</td></tr>
    <tr><td>url</td><td>string</td><td>The product page — trade prices there still require a signed-in trade account.</td></tr>
    <tr><td>added_at</td><td>string</td><td>ISO 8601, or empty on catalogue lines that predate the field.</td></tr>
    <tr><td>stock</td><td>object</td><td><code>{"tracked": false, "quantity": null, "note": "…"}</code>. See the note at the top.</td></tr>
  </table></div>

  <h2>Currency</h2>
  <p>Prices are always EUR here, with no conversion parameter. The website converts the
     shelf price for visitors outside the euro area as a reading convenience, but orders
     are contracted and invoiced in EUR — so a feed that quoted anything else would hand
     you a number your invoice will not match. Convert at your own rate, on your own
     schedule, in your own shop.</p>

  <h2>Rate and caching</h2>
  <p>There is no hard rate limit, and no charge for access. Responses carry
     <code>Cache-Control: private, max-age=60</code>: the catalogue does not change by the
     second, and a nightly full pull plus <code>since=</code> during the day is both
     gentler and more accurate than polling.</p>

  <h2>Ordering: the dropship API</h2>
  <p>The catalogue API above is read-only. Placing orders one piece at a time is a separate
     endpoint, <code>/api/dropship</code>, with its own key. It is open to verified trade
     partners buying <b>for their own customers</b>: you complete checkout and enter your
     customer's delivery address, and no contract of sale arises between VESTRA and that
     end customer.</p>

  <h3>GET /api/dropship?a=list</h3>
  <p>Every article available for single-piece purchase, with its price. Ralph Lauren and
     Lacoste are excluded from dropshipping.</p>

  <h3>GET /api/dropship?a=stock&amp;id=<i>id</i></h3>
  <p>Price, the three shipping zones, and <code>stock_tracked</code> — which is
     <code>false</code> for catalogue articles, for the same reason the catalogue feed
     reports no stock.</p>

  <h3>POST /api/dropship?a=order</h3>
  <pre><code>{ "id": "dsq-101211", "colour": "Black", "size": "M", "qty": 1,
  "country": "JP",
  "reference": "your-order-id",
  "customer_email": "…", "customer_name": "…" }</code></pre>
  <p>Returns <code>{ ok, ref, checkout_url }</code>. Open <code>checkout_url</code> to pay.
     Your own <code>reference</code> is echoed back and shown on the order.</p>

  <div class="apiscroll"><table>
    <tr><th>Field</th><th>Notes</th></tr>
    <tr><td>zone / country</td><td><code>zone</code> is <code>EU</code>, <code>US</code> or <code>JP</code>; or pass <code>country</code> as an ISO-2 code and we map it. Anything else falls back to Europe.</td></tr>
    <tr><td>colour, size</td><td>Free text — what your customer ordered. Size runs in this catalogue are pack rules rather than lists, so there is nothing to pick from.</td></tr>
  </table></div>

  <h3>Price and shipping</h3>
  <p>The dropship price is the wholesale price of the smallest quantity tier <b>plus 20%</b>.
     Shipping is charged once per order by zone:</p>
  <div class="apiscroll"><table>
    <tr><th>Zone</th><th>Rate</th><th>Delivers to</th></tr>
    <tr><td>EU</td><td>€16.00</td><td>the 27 EU member states</td></tr>
    <tr><td>US</td><td>€30.00</td><td>United States</td></tr>
    <tr><td>JP</td><td>€30.00</td><td>Japan</td></tr>
  </table></div>
  <p>The zone is fixed before the payment session opens, and the session then accepts only
     that zone's countries — so the rate charged and the address entered cannot diverge.</p>

  <div class="apinote">
    <p><b>Duties and import taxes are not included</b> in the price or the shipping rate.
       They are payable on delivery and are yours to settle or to pass to your customer.
       Goods of EU preferential origin may attract zero customs duty entering Japan under
       the EU–Japan Economic Partnership Agreement when a statement on origin accompanies
       the consignment — that is the tariff only, and does not cover consumption tax or the
       carrier's clearance fee.</p>
    <p><b>Availability is confirmed after the order</b>, because per-unit stock is not
       tracked. If an article cannot be supplied, the order is refunded in full.</p>
  </div>

  <h2>If you need something else</h2>
  <p>CSV and XLSX exports of the same catalogue are available to signed-in trade accounts
     from the price list — the XLSX carries the photographs embedded next to each row.
     There is no XML feed and no FTP drop.</p>
</div>

<?php require __DIR__.'/inc/foot.php'; ?>
