<?php
/**
 * VESTRA — FAQ data.
 * English source in vestra_faq_en().
 * Dispatcher vestra_faq() loads inc/faq/{lang}.php and falls back per-item to English.
 */
require_once __DIR__.'/i18n.php';

function vestra_faq(){
  $en = vestra_faq_en();
  $l  = function_exists('vlang') ? vlang() : 'en';
  if($l==='en') return $en;
  $f = __DIR__.'/faq/'.$l.'.php';
  if(is_readable($f)){
    $tr = require $f;
    if(is_array($tr)){
      $out=[];
      foreach($en as $k=>$cat){
        $items=[];
        foreach($cat['items'] as $i=>$item){
          $items[]=[
            'q'=>(isset($tr[$k]['items'][$i]['q'])&&$tr[$k]['items'][$i]['q']!=='')?$tr[$k]['items'][$i]['q']:$item['q'],
            'a'=>(isset($tr[$k]['items'][$i]['a'])&&$tr[$k]['items'][$i]['a']!=='')?$tr[$k]['items'][$i]['a']:$item['a'],
          ];
        }
        $out[$k]=[
          'title'=>(isset($tr[$k]['title'])&&$tr[$k]['title']!=='')?$tr[$k]['title']:$cat['title'],
          'items'=>$items,
        ];
      }
      return $out;
    }
  }
  return $en;
}

function vestra_faq_en(){
  return [

  'about'=>['title'=>'About VESTRA','items'=>[
    ['q'=>'What is VESTRA?',
     'a'=>'VESTRA is a B2B wholesale marketplace connecting verified business sellers and buyers of fashion and lifestyle goods. It is an intermediary and technical platform only — it does not own, hold, or ship goods and is not a party to any sale between buyers and sellers.'],
    ['q'=>'Is VESTRA for businesses or consumers?',
     'a'=>'VESTRA is strictly for businesses (B2B). Only registered and KYB-verified companies may transact on the platform. Consumer-protection withdrawal rights do not apply. If you are shopping as a private individual, VESTRA is not the right platform for you.'],
    ['q'=>'Which countries does VESTRA serve?',
     'a'=>'VESTRA is open to verified businesses worldwide. However, we do not onboard users located in jurisdictions subject to international sanctions (OFAC, EU, UN). EEA-stock products are particularly suited to buyers within Europe.'],
    ['q'=>'Who operates VESTRA?',
     'a'=>'VESTRA is operated by Acerasoft LLC, a US Limited Liability Company registered in the State of Delaware (8 The Green, Suite B, Dover, Delaware 19901, USA). Contact: legal@vestrasales.com.'],
    ['q'=>'What makes VESTRA different from other wholesale platforms?',
     'a'=>'VESTRA combines KYC-verified sellers, documented invoice-based payment with automatic PDF invoices, Group Buying (pool with other buyers to unlock volume prices), and a Sourcing Requests board — all in one secure B2B environment designed for fashion wholesale.'],
  ]],

  'registration'=>['title'=>'Registration & Account','items'=>[
    ['q'=>'How do I register on VESTRA?',
     'a'=>'Click "Sign in" and complete the registration form with your company name, registration number, VAT/tax ID, and contact details. After submitting, our team will initiate the KYB verification process.'],
    ['q'=>'Is registration free?',
     'a'=>'Creating an account is free, and buying carries no platform fees. Sellers need an active membership plan (30-day free trial) to publish listings — see the Membership page for plans and pricing.'],
    ['q'=>'How long does account approval take?',
     'a'=>'Standard KYB verification typically completes within 1–3 business days, depending on how quickly documents are provided and whether additional checks are required.'],
    ['q'=>'Can I browse the catalog without registering?',
     'a'=>'Yes — you can browse the catalog and view product listings without registering. Pricing and ordering require a verified buyer account.'],
    ['q'=>'Can I register as both a buyer and a seller?',
     'a'=>'Yes. One account can hold both buyer and seller roles. Each role goes through its own verification step (KYB for buyers, additional listing permissions for sellers).'],
    ['q'=>'What if I represent a business from outside the EU?',
     'a'=>'Non-EU businesses are welcome, provided they are not in a sanctioned jurisdiction. You will need to provide the equivalent of company registration documents and beneficial-owner identity proof from your country.'],
  ]],

  'kyb'=>['title'=>'Verification (KYB / KYC)','items'=>[
    ['q'=>'What is KYB / KYC and why is it required?',
     'a'=>'KYB (Know Your Business) and KYC (Know Your Customer) are identity verification processes required by anti-money-laundering (AML) regulations and our own platform rules. They ensure that every party on VESTRA is a legitimate, identifiable business, which protects all users.'],
    ['q'=>'Which documents do I need for verification?',
     'a'=>'Typically: (1) Company registration certificate or extract; (2) VAT / tax identification number; (3) Proof of registered address; (4) Identity documents for Ultimate Beneficial Owners (≥ 25% ownership). Exact requirements may vary by country.'],
    ['q'=>'What are Ultimate Beneficial Owners (UBOs)?',
     'a'=>'A UBO is any individual who ultimately owns or controls 25% or more of the company. EU AML regulations require us to identify and verify all UBOs before allowing transactions.'],
    ['q'=>'Is my identity and company data safe?',
     'a'=>'Yes. All verification documents are handled by a specialist KYC/AML provider and are encrypted in transit and at rest. Data is retained only for the legally required period and is never sold to third parties. See our Privacy Policy for full details.'],
    ['q'=>'My verification was rejected — what should I do?',
     'a'=>'You will receive an email explaining the reason. Common causes are: missing or expired documents, unclear scans, or UBO information not matching public records. Reply with corrected documents and we will re-review within 1–2 business days.'],
    ['q'=>'Can I transact while my verification is pending?',
     'a'=>'No. Both buyers and sellers must complete verification before placing or receiving orders. You can continue browsing and preparing your listing or wish list in the meantime.'],
  ]],

  'ordering'=>['title'=>'Ordering, MOQ & Size Assortments','items'=>[
    ['q'=>'What is an MOQ?',
     'a'=>'MOQ stands for Minimum Order Quantity — the smallest number of units a seller is willing to ship in one order. On VESTRA, MOQs typically represent one complete size assortment pack.'],
    ['q'=>'Why is there a minimum order quantity?',
     'a'=>'MOQs reflect the wholesale nature of B2B trade. Sellers prepare goods in pre-packed assortments aligned to retail sell-through patterns. Meeting the MOQ is required for the seller to generate a profit-viable shipment.'],
    ['q'=>'What is a "size assortment pack"?',
     'a'=>'A size assortment pack is a pre-set bundle of pieces across different sizes that forms the minimum orderable unit. For most brands on VESTRA the standard pack is 10 pieces: S×1 + M×3 + L×3 + XL×2 + XXL×1.'],
    ['q'=>'What does "S×1 · M×3 · L×3 · XL×2 · XXL×1" mean?',
     'a'=>'It means the minimum order contains 10 pieces distributed across sizes: 1 piece in size S, 3 in M, 3 in L, 2 in XL and 1 in XXL. This ratio matches typical European retail sell-through data. For Lacoste and Ralph Lauren, the pack is 8 pieces: S×1 + M×2 + L×2 + XL×2 + XXL×1.'],
    ['q'=>'Can I order a single size only?',
     'a'=>'No. Products sold via size assortment packs must be ordered as complete packs. If you need single-size bulk, post a Sourcing Request on our Requests board and sellers will respond with offers.'],
    ['q'=>'Can I order more than the MOQ?',
     'a'=>'Yes — you can order any multiple of the pack size. Ordering more packs typically unlocks a lower unit price via tiered pricing (see the price table on each product page).'],
    ['q'=>'How do tiered prices work?',
     'a'=>'Tiered pricing rewards higher volume with a lower unit price. For example, a product might be €55/pc at 10 pcs, €48/pc at 40 pcs, and €42.50/pc at 60 pcs. The price displayed updates automatically as you adjust the quantity.'],
    ['q'=>'Can I mix different products in one order?',
     'a'=>'Yes. Add multiple products to your cart and they will be bundled into one order. Each product must still meet its own MOQ. A combined cart may attract a single logistics quote from the seller.'],
    ['q'=>'I want a product not listed — can I request it?',
     'a'=>'Yes. Use the Sourcing Requests board to post what you need (brand, quantity, target price, delivery country). Verified sellers browse open requests and submit offers directly to you.'],
  ]],

  'payment'=>['title'=>'Payment & Escrow','items'=>[
    ['q'=>'How do I pay right now?',
     'a'=>'Payments are currently invoice-based. After your order is confirmed you receive a proforma invoice and pay by bank transfer; goods ship after the invoice is paid. The escrow/card checkout described below is temporarily suspended and will return at a later stage.'],
    ['q'=>'How does payment work on VESTRA?',
     'a'=>'At checkout you receive an automatic PDF invoice per seller, including the seller\'s bank details. You pay by bank transfer (SEPA within the EU) directly to the seller; goods ship as soon as the payment arrives. Every step is documented in your account.'],
    ['q'=>'What is escrow and why does VESTRA use it?',
     'a'=>'Escrow means a neutral third party holds the payment until agreed conditions are met (delivery confirmed, inspection window passed). It protects buyers against non-delivery and sellers against non-payment — neither party can disappear with the money.'],
    ['q'=>'Who holds the escrow funds?',
     'a'=>'A licensed, regulated third-party payment and escrow provider holds all funds. VESTRA never holds or transmits user money.'],
    ['q'=>'When are funds released to the seller?',
     'a'=>'Funds release when: (a) you confirm receipt of goods, or (b) automatically once your claim window has run, if no problem has been reported. To pause release, report the problem to support@vestrasales.com before the window ends — reported orders stay held until resolved.'],
    ['q'=>'What payment methods are accepted?',
     'a'=>'Bank transfer against the seller\'s invoice — SEPA within the EU, SWIFT internationally. Card and other online payment methods are temporarily suspended. Cryptocurrency and cheques are not accepted.'],
    ['q'=>'In which currency do I pay?',
     'a'=>'All prices on VESTRA are in Euros (EUR). SEPA transfers are made in EUR. Card transactions may attract a conversion fee if your card is in a different currency.'],
    ['q'=>'Is there a buyer-protection fee?',
     'a'=>'Yes — escrow (secure card) orders carry a 3.8% buyer-protection fee. It holds your payment safely until you confirm delivery and funds a full refund if the deal falls through, and it is shown clearly at checkout before you pay. Orders paid by bank transfer against an invoice have no buyer fee.'],
    ['q'=>'Is my payment information safe?',
     'a'=>'You pay by bank transfer from your own bank — VESTRA never collects, sees, or stores your card numbers or online-banking credentials. The seller\'s bank details are printed on the invoice itself.'],
  ]],

  'shipping'=>['title'=>'Shipping & Delivery','items'=>[
    ['q'=>'Does VESTRA arrange shipping?',
     'a'=>'VESTRA is a marketplace platform and does not itself arrange, book, or manage logistics. Shipping terms are agreed directly between buyer and seller at the time of order.'],
    ['q'=>'Who arranges shipping and pays for it?',
     'a'=>'Shipping is either arranged by the seller (DDP — Delivered Duty Paid) or by the buyer (EXW / FOB). The shipping arrangement and cost are stated on each listing and confirmed in the order. If nothing is stated, clarify with the seller before ordering.'],
    ['q'=>'How long does delivery take?',
     'a'=>'Delivery times vary by seller location, transport mode, and customs processing. Typical times within Europe are 3–7 business days. Cross-border shipments with customs may take 1–3 weeks. Always confirm estimated delivery with the seller before ordering.'],
    ['q'=>'How do I track my order?',
     'a'=>'The seller must provide tracking information within the agreed dispatch window. Tracking details are shared via the order thread in your account dashboard. If tracking is not provided on time, open a query through the platform.'],
    ['q'=>'Who is responsible for import duties and taxes?',
     'a'=>'Import duties, VAT, and customs fees are the buyer\'s responsibility unless the listing explicitly states DDP (Delivered Duty Paid). EU intra-community shipments between VAT-registered businesses are typically zero-rated (reverse charge).'],
    ['q'=>'What if goods arrive damaged in transit?',
     'a'=>'Document the damage with photographs immediately upon delivery, before signing the carrier\'s receipt. File the claim within 3 days of delivery. Do not return goods without VESTRA\'s written confirmation — unauthorised returns complicate the resolution.'],
  ]],

  /* IADE POLITIKASI — operator karari, 4 Eyl 2026:
     "b2b urunleri geri iadeye kapalidir. Sadece yanlis, eksik, hatali urunlerde
      iade yapilir ve alici ilk 3 gun icerisinde geri donmek zorundadir."
     Kanonik metin BURADA duruyor; site genelindeki baglantilar /faq?cat=returns
     adresine isaret ediyor, kurallar hicbir yerde TEKRAR YAZILMIYOR (iki kopya
     er gec birbirinden ayrilir). Gun sayisi tek bir yerden gelir:
     VESTRA_CLAIM_DAYS (inc/escrow.php) ve tests/returns_policy_test.php ikisinin
     ayni kalmasini zorunlu tutar. */
  'returns'=>['title'=>'Returns & Claims','items'=>[
    ['q'=>'Can I return goods because I changed my mind?',
     'a'=>'No. Wholesale (B2B) orders on VESTRA are closed to returns. A confirmed order is a firm commercial commitment between two businesses, and neither VESTRA nor the seller accepts goods back because you over-ordered, the season moved on, the goods did not sell, or your own customer returned them to you. This is the normal rule in wholesale and it is what allows trade prices to be quoted at all.'],
    ['q'=>'So when is a return or a claim accepted?',
     'a'=>'In three cases, and only these three: the goods are WRONG (a different article, model, colour or size than the order), the delivery is INCOMPLETE (fewer pieces than invoiced, or a missing box), or the goods are FAULTY (a manufacturing defect, damage, or a material difference from the listing that was not disclosed). Counterfeit goods are treated separately and more seriously — see the Authenticity section.'],
    ['q'=>'How long do I have to report it?',
     'a'=>'Three days. You must notify us within 3 days of delivery. This is the single deadline that matters: after it passes the delivery counts as accepted and the seller is paid. There is no extension for holidays, staff absence, or unopened cartons — if you cannot inspect a delivery within three days, arrange for someone who can.'],
    ['q'=>'How exactly are the 3 days counted?',
     'a'=>'Calendar days, not business days, starting from the delivery date recorded by the carrier or confirmed in your order. A delivery on Friday must be reported by Monday. The clock stops the moment your claim is submitted in the platform — not when the seller replies to it.'],
    ['q'=>'How do I file a claim?',
     'a'=>'Open the order in your account dashboard and click "Open dispute", or write to support@vestrasales.com quoting the order reference. Do it inside the platform where possible: a claim raised only by telephone or in a private e-mail thread with the seller leaves no record, and without a record there is nothing to enforce.'],
    ['q'=>'What do I have to send with the claim?',
     'a'=>'The order reference; how many pieces are affected and which articles; photographs of the goods showing the problem; a photograph of the shipping label and the outer carton; and for a shortage, a photograph of the packing list. Send this in one message. An incomplete claim still counts as filed on the day you sent it, but it cannot be decided until the evidence is complete, so send everything at once.'],
    ['q'=>'Should I ship the goods back straight away?',
     'a'=>'No — never return anything without written authorisation. A claim is not a return. If a return is agreed you receive a written authorisation naming the address and the pieces to send back. Goods shipped back without it may be refused, may be lost, and weaken your own claim because nobody can verify what was in the box.'],
    ['q'=>'What condition must returned goods be in?',
     'a'=>'Exactly as delivered: unused, unworn, unwashed, with all original tags, labels and packaging, and complete. Do not remove security tags, do not price-label them for your own shop, and do not split assortment packs. Faulty pieces are of course returned in the condition they arrived in — the point is that nothing may be added to the damage after delivery.'],
    ['q'=>'Who pays the return shipping?',
     'a'=>'The seller, when the claim is upheld — wrong, missing or faulty goods are the seller\'s cost, including collection where a carrier pickup is arranged. If the claim is not upheld, the goods stay with you and any shipping you paid is not reimbursed. Do not send anything freight-collect without written agreement; unauthorised charges are refused.'],
    ['q'=>'What happens after I file?',
     'a'=>'VESTRA reviews the claim within 2 business days and asks the seller for their account. Most cases are decided on the photographs alone. If the two sides describe the goods differently we may ask for further images, a sample to be sent, or an independent inspection. You are told the outcome in the order thread, so the whole file stays in one place.'],
    ['q'=>'What are the possible outcomes?',
     'a'=>'A replacement delivery, a partial credit where you keep the goods (common for a small defect rate in a large batch), or a refund of the affected pieces against their return. VESTRA decides between them with the seller and tells you which applies. A refund covers the affected pieces and their share of the freight — not your onward costs, lost sales, or your own customer\'s claim against you.'],
    ['q'=>'What if the delivery is short?',
     'a'=>'Count the cartons against the delivery note before you sign for them, and write any shortage on the carrier\'s receipt at that moment. A shortage discovered after a clean signature is much harder to prove, though it is still claimable inside the 3 days if the carton weights or the seal tell the story. Photograph sealed cartons before opening them.'],
    ['q'=>'What if the goods are damaged in transit?',
     'a'=>'Note the damage on the carrier\'s receipt before signing, photograph the packaging before you open it, and file the claim inside the same 3 days. Transit damage is a carrier matter and the carrier\'s own deadlines can be shorter than ours, so the note at the door is what protects the claim. Do not discard the packaging until the case is closed.'],
    ['q'=>'Which goods can never be returned?',
     'a'=>'Made-to-order and customised production, goods altered or labelled by you after delivery, and anything sold as final-sale clearance where the listing says so. Sample orders are also final: they exist so you can judge the goods before committing to a wholesale quantity, which is exactly the decision a return would otherwise cover.'],
    ['q'=>'Does filing a claim stop my payment going to the seller?',
     'a'=>'Yes. Funds are not released while a claim is open, and the automatic release is set so that it cannot happen before your 3 days have run. This is the practical reason the deadline is short: your money is held for exactly as long as your right to complain lasts.'],
    ['q'=>'What if the seller refuses to accept a valid claim?',
     'a'=>'VESTRA decides the case, not the seller. Where a claim is upheld the refund or replacement is enforced from the escrowed funds; a seller who will not comply loses their verified status, is suspended, and is removed from the platform. You do not have to negotiate this yourself.'],
    ['q'=>'Do I have a statutory right of withdrawal, like a consumer?',
     'a'=>'No. VESTRA is strictly business-to-business and only KYB-verified companies can transact here. The 14-day distance-selling withdrawal right applies to consumers buying for private use and does not apply to a trade purchase for resale. The rules on this page are what governs your order, together with the Terms of Service.'],
    ['q'=>'What is the shortest possible summary?',
     'a'=>'No returns for change of mind. Wrong, missing or faulty goods only. Tell us within 3 days of delivery, with photographs, through the platform. Do not ship anything back until we authorise it in writing.'],
  ]],

  'disputes'=>['title'=>'Disputes & Returns','items'=>[
    ['q'=>'What if the goods do not match the listing description?',
     'a'=>'If goods are materially different from the listing (wrong model, quantity, colour, or undisclosed defects), open a dispute in your account dashboard within 3 days of delivery — see Returns & Claims for what to send. Include photographs and a written description. Hold any further payments to the seller while the review runs.'],
    ['q'=>'How do I open a dispute?',
     'a'=>'Go to your order in the account dashboard, click "Open dispute", select the reason (non-delivery, not-as-described, quality issue, counterfeit), attach evidence, and submit. You will receive a reference number. Our team reviews within 2 business days.'],
    ['q'=>'Can I return goods?',
     'a'=>'Wholesale orders are closed to returns: there is no return for change of mind, and no statutory withdrawal right on a B2B purchase. Goods that are wrong, missing or faulty are claimable within 3 days of delivery. The full rules, the evidence to send and who pays the return freight are set out under Returns & Claims.'],
    ['q'=>'What if I receive counterfeit goods?',
     'a'=>'This is treated as the most serious category of dispute. Document the goods thoroughly and do not make further payments. VESTRA will initiate an IP investigation, liaise with the rights holder if available, and — if counterfeiting is confirmed — enforce a full refund from the seller and suspend them permanently.'],
    ['q'=>'How long do I have to raise a dispute?',
     'a'=>'Within 3 days of delivery (or, for non-delivery, by the agreed delivery deadline). After this window the delivery is treated as accepted and the seller is paid. The same 3 days apply to every claim reason — wrong, missing, faulty or not-as-described.'],
    ['q'=>'What happens if a dispute is resolved in my favour?',
     'a'=>'The seller must refund you in full (or partially, as agreed) — VESTRA enforces the outcome: sellers who fail to comply are struck, suspended, and lose their verified status.'],
  ]],

  'selling'=>['title'=>'Selling on VESTRA','items'=>[
    ['q'=>'How do I become a seller on VESTRA?',
     'a'=>'Register an account, complete KYB verification, and apply for seller access from your account dashboard. You will need to provide business registration details, tax/VAT ID, proof of your right to sell the goods (authorisation letters for branded items), and UBO identity documents.'],
    ['q'=>'What commission does VESTRA charge sellers?',
     'a'=>'VESTRA charges a commission on each order\'s goods value that depends on your plan — 3.5% on Starter, 3.2% on Pro, 2.8% on Elite. It\'s collected automatically from the card you add in your seller profile the moment the buyer\'s payment is confirmed — no invoicing, no manual transfers, and it never changes what the buyer pays.'],
    ['q'=>'How and when do I receive payment?',
     'a'=>'Directly and before shipping: the buyer pays your invoice by bank transfer straight to the bank account you set in your seller profile — VESTRA is never in that payment chain. Ship as soon as it arrives. Separately, your plan\'s commission (see above) is charged to your commission card once the order is marked paid.'],
    ['q'=>'Can I set my own prices?',
     'a'=>'Yes. You set your unit prices per tier freely. You are also free to set your own MOQ, tier breakpoints, and shipping terms. VESTRA does not dictate pricing but reserves the right to remove listings with prices it deems misleading or non-market.'],
    ['q'=>'What products can I list?',
     'a'=>'Fashion and lifestyle goods (clothing, accessories, footwear, bags, etc.) that are legal, genuine, and that you are entitled to sell in the destination market. Counterfeit, replica, stolen, or illegally sourced goods are strictly prohibited. See the Prohibited & Restricted Items policy for the full list.'],
    ['q'=>'What documents do I need to list branded goods?',
     'a'=>'For branded goods you must be able to provide, on request: proof of purchase (invoice from an authorised source), authenticity certificate, trademark exhaustion proof (for EEA parallel imports), or an authorisation letter from the brand. Listings without documentation available may be removed.'],
    ['q'=>'What is a "strike" and what happens if I receive one?',
     'a'=>'A strike is issued when a valid complaint is upheld against you — for IP infringement, counterfeit goods, misrepresentation, or repeated non-delivery. Two strikes trigger a temporary suspension for review. Three strikes, or a single instance of confirmed counterfeit/fraud, result in permanent suspension.'],
    ['q'=>'Can I also list on other wholesale platforms?',
     'a'=>'Yes. VESTRA does not require exclusivity. You are free to sell the same goods on other platforms provided you comply with VESTRA\'s rules for the listings published here.'],
  ]],

  'authenticity'=>['title'=>'Authenticity & Intellectual Property','items'=>[
    ['q'=>'How does VESTRA verify product authenticity?',
     'a'=>'Sellers must provide proof of authenticity and right-to-sell documentation during listing. Verified listings are marked accordingly. Buyers may request documentation at any time. On receipt of goods, buyers can open an IP / counterfeit dispute if items appear fraudulent.'],
    ['q'=>'What happens if someone lists counterfeit goods?',
     'a'=>'Confirmed counterfeiting results in immediate listing removal, seller suspension, an enforced refund to the buyer, and referral to the relevant rights holder and/or authorities. We operate a zero-tolerance policy.'],
    ['q'=>'How do I report an infringing or counterfeit listing?',
     'a'=>'Email ip@vestrasales.com with: the right you rely on (trademark number, etc.) and proof of ownership; the exact listing URL(s); the reason it infringes; a good-faith statement and your contact details. We acknowledge all notices and assess within 2 business days.'],
    ['q'=>'Are grey-market or parallel imports allowed?',
     'a'=>'Only if the seller can prove EEA trademark exhaustion (i.e., the goods were first placed on the EEA market by the rights holder or with their consent). Sellers must provide this proof on request. Unverified grey-market goods are treated as prohibited items.'],
    ['q'=>'I am a brand owner — how do I protect my IP on VESTRA?',
     'a'=>'Contact ip@vestrasales.com to register as a Trusted Rights Holder. You will receive a dedicated notice-and-takedown channel. We also implement reasonable stay-down measures for confirmed infringing items to prevent re-listing. Abusive notices that target legitimate sellers may result in your channel access being restricted.'],
  ]],

  'fees'=>['title'=>'Fees & Pricing','items'=>[
    ['q'=>'Is it free to use VESTRA as a buyer?',
     'a'=>'Yes — browsing, registering, and ordering are completely free for buyers. You pay only the goods total on the seller\'s invoice.'],
    ['q'=>'What is the seller commission?',
     'a'=>'VESTRA charges sellers a commission on each paid order\'s goods value, charged automatically to the card on file — 3.5% on Starter, 3.2% on Pro, 2.8% on Elite. This is separate from — and in addition to — the monthly membership plan.'],
    ['q'=>'Are there any membership or subscription fees?',
     'a'=>'For buyers: never. For sellers: publishing listings requires an active membership plan (Starter €19.90 — 10 listings/month; Pro €39.90 — 100 listings/month; Elite €89.90 — unlimited listings; all after a 30-day free trial). Plans are shown on the Membership page.'],
    ['q'=>'Are platform fees refundable?',
     'a'=>'Membership fees are non-refundable except where required by law — you can cancel anytime and keep access until the end of the paid period. Goods payments go directly to the seller; refunds for goods are handled through the order dispute process.'],
  ]],

  'account'=>['title'=>'Account & Security','items'=>[
    ['q'=>'How do I update my company details?',
     'a'=>'Log in to your account and go to Company Settings. Material changes (registered address, UBO changes, VAT number) require re-verification. Update details promptly — providing outdated information may suspend your ability to transact.'],
    ['q'=>'What if I forget my password or lose account access?',
     'a'=>'Use the "Forgot password" link on the sign-in page. If you have lost access to your registered email, contact support@vestrasales.com with proof of identity and company ownership.'],
    ['q'=>'Can I delete my account?',
     'a'=>'Yes. Contact legal@vestrasales.com to request account deletion. Note that records relating to completed transactions must be retained for the legally required period (typically 5–10 years depending on jurisdiction) and cannot be deleted from our compliance archive.'],
    ['q'=>'How does VESTRA protect my personal data?',
     'a'=>'VESTRA collects only the data needed to operate the marketplace and comply with AML/KYC obligations. Data is encrypted in transit (TLS) and at rest. We do not sell personal data to third parties. For your full rights (access, rectification, erasure, portability), see our Privacy Policy or email privacy@vestrasales.com.'],
    ['q'=>'Where is my data stored?',
     'a'=>'Platform and user data is stored on EU-based servers. Where data is transferred outside the EEA (e.g., to our US-based parent entity or KYC providers), appropriate safeguards such as Standard Contractual Clauses (SCCs) are in place.'],
  ]],

  ]; // end return
}
