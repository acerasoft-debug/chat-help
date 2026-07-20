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
     'a'=>'VESTRA is operated by acerasoft LLC, a US Limited Liability Company registered in the State of Delaware (8 The Green, Suite B, Dover, Delaware 19901, USA). Contact: legal@vestrasales.com.'],
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
     'a'=>'<b>Payments are currently invoice-based.</b> After your order is confirmed you receive a proforma invoice and pay by bank transfer; goods ship after the invoice is paid. The escrow/card checkout described below is temporarily suspended and will return at a later stage.'],
    ['q'=>'How does payment work on VESTRA?',
     'a'=>'At checkout you receive an automatic PDF invoice per seller, including the seller\'s bank details. You pay by bank transfer (SEPA within the EU) directly to the seller; goods ship as soon as the payment arrives. Every step is documented in your account.'],
    ['q'=>'What is escrow and why does VESTRA use it?',
     'a'=>'Escrow means a neutral third party holds the payment until agreed conditions are met (delivery confirmed, inspection window passed). It protects buyers against non-delivery and sellers against non-payment — neither party can disappear with the money.'],
    ['q'=>'Who holds the escrow funds?',
     'a'=>'A licensed, regulated third-party payment and escrow provider holds all funds. VESTRA never holds or transmits user money.'],
    ['q'=>'When are funds released to the seller?',
     'a'=>'Funds release when: (a) you confirm receipt of goods, or (b) the agreed auto-release window (typically 5–14 days after confirmed delivery) expires with no dispute raised. If a dispute is opened, funds remain frozen until resolution.'],
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
     'a'=>'Document the damage with photographs immediately upon delivery, before signing the carrier\'s receipt. Open a dispute within 48 hours of delivery. Do not return goods without VESTRA\'s written confirmation — unauthorised returns complicate the resolution.'],
  ]],

  'disputes'=>['title'=>'Disputes & Returns','items'=>[
    ['q'=>'What if the goods do not match the listing description?',
     'a'=>'If goods are materially different from the listing (wrong model, quantity, colour, or undisclosed defects), open a dispute in your account dashboard within 5 business days of confirmed delivery. Include photographs and a written description. Hold any further payments to the seller while the review runs.'],
    ['q'=>'How do I open a dispute?',
     'a'=>'Go to your order in the account dashboard, click "Open dispute", select the reason (non-delivery, not-as-described, quality issue, counterfeit), attach evidence, and submit. You will receive a reference number. Our team reviews within 2 business days.'],
    ['q'=>'Can I return goods?',
     'a'=>'B2B purchases on VESTRA do not carry a statutory right of return (unlike consumer purchases). Returns are possible only when goods are materially not-as-described, proven counterfeit, or the seller explicitly offers returns in their listing. Never ship goods back without written authorisation.'],
    ['q'=>'What if I receive counterfeit goods?',
     'a'=>'This is treated as the most serious category of dispute. Document the goods thoroughly and do not make further payments. VESTRA will initiate an IP investigation, liaise with the rights holder if available, and — if counterfeiting is confirmed — enforce a full refund from the seller and suspend them permanently.'],
    ['q'=>'How long do I have to raise a dispute?',
     'a'=>'You must raise a dispute within 5 business days of the confirmed delivery date (or, for non-delivery, by the agreed delivery deadline). After this window the delivery is treated as accepted and disputes become significantly harder to resolve.'],
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
