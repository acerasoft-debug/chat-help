<?php
/**
 * ChatHelp — BİRLEŞİK KRALLIK KATALOG GENİŞLETME (+36 belge, 1 yeni kategori: Family)
 * --------------------------------------------------------------------------------
 *  Mevcut 6 GB kategorisine yeni belgeler ekler + "Family" kategorisi açar
 *  (yeni kategori enjeksiyonu apply-turkiye-expand.php ile aynı kanıtlanmış desen).
 *  Öne çıkanlar: separation agreement, cohabitation agreement, child arrangements,
 *  disrepair letter before action (Homes Act 2018), settlement agreement negotiation,
 *  reasonable adjustments (Equality Act 2010), right to erasure (UK GDPR),
 *  vehicle sale agreement, guarantor agreement, school admission appeal, bailiff dispute.
 *  Tüm belgeler tier'lı -> Stripe ödeme otomatik. Belge dili/hukuku CC üzerinden
 *  otomatik (lawSystems['GB'] + doc-lang GB->en zaten api.php'de).
 *
 * KULLANIM: chat-help.com/chat/apply-uk-expand.php -> opcache-reset.php. SİL.
 * GEREKLİ: apply-uk.php önce çalıştırılmış olmalı (CH_GB_MODULE, gb_* kategoriler).
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-uk-expand BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,"CH_GB_EXPAND")!==false) exit("Zaten ekli (CH_GB_EXPAND).\n");
if(strpos($src,"CH_GB_MODULE")===false) exit("CH_GB_MODULE yok — once apply-uk.php calistir.\n");
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-gbexpand-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_GB_EXPAND — Birleşik Krallık katalog genişletme (+36 belge, yeni kategori: Family; deferred yükleme) */
try{(function(){
  function Pe(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}

  var NEWCATS={
    gb_family:{n:"Family",ic:"👨‍👩‍👧"}
  };

  var E={
    /* Family (new category) */
    gb_child_arrangements:{ic:"👨‍👩‍👧",n:"Child arrangements proposal letter",cat:"gb_family",tier:"komplex",q:[Pe("other_parent","Other parent: name and address",true),Pe("children","Children (names and ages)",true),Pe("current","Current arrangements",true),Pe("proposal","Proposed arrangements (living, contact, holidays)",true),Pe("details","Your full name",true)]},
    gb_cms_arrears:{ic:"💷",n:"Child maintenance arrears letter (CMS)",cat:"gb_family",tier:"standard",q:[Pe("recipient","Recipient (other parent or CMS)",true),Pe("reference","CMS case reference (if any)",false),Pe("children","Children the maintenance is for",true),Pe("arrears","Arrears amount (£) and period",true),Pe("request","Your request (payment, schedule, enforcement)",true),Pe("details","Your full name and address",true)]},
    gb_parental_responsibility:{ic:"📜",n:"Parental responsibility agreement request",cat:"gb_family",tier:"standard",q:[Pe("other_parent","Other parent: name and address",true),Pe("child","Child: name and date of birth",true),Pe("relationship","Your relationship to the child",true),Pe("reasons","Why you are seeking parental responsibility",true),Pe("details","Your full name and address",true)]},
    gb_child_travel_consent:{ic:"✈️",n:"Child travel consent letter",cat:"gb_family",tier:"standard",q:[Pe("child","Child: name and date of birth",true),Pe("traveller","Adult travelling with the child",true),Pe("consenting","Consenting parent: name and contact details",true),Pe("destination","Destination country/place",true),Pe("dates","Travel dates",true)]},
    gb_separation_agreement:{ic:"📄",n:"Separation agreement draft",cat:"gb_family",tier:"komplex",q:[Pe("parties","Both parties: full names",true),Pe("separation_date","Date of separation",true),Pe("children","Children (names/ages, or 'none')",true),Pe("finances","Division of property, savings and debts",true),Pe("maintenance","Maintenance arrangements (£, or 'none')",false),Pe("home","What happens to the family home",false)]},
    gb_cohabitation_agreement:{ic:"🏠",n:"Cohabitation agreement",cat:"gb_family",tier:"komplex",q:[Pe("partners","Both partners: full names",true),Pe("address","Shared property address and ownership",true),Pe("contributions","Contributions (mortgage/rent, bills, £ shares)",true),Pe("assets","How assets are divided if you separate",true),Pe("clauses","Special clauses (optional)",false)]},
    gb_change_of_name:{ic:"✍️",n:"Change of name deed request (deed poll)",cat:"gb_family",tier:"standard",q:[Pe("current_name","Current full name",true),Pe("new_name","New full name",true),Pe("dob","Date of birth",true),Pe("reason","Reason (optional)",false),Pe("details","Your address",true)]},

    /* Contracts & Agreements */
    gb_ast_draft:{ic:"🏠",n:"Assured shorthold tenancy agreement draft",cat:"gb_contracts",tier:"komplex",q:[Pe("role","You are the",true,["Landlord","Tenant"]),Pe("other_party","Other party: full name",true),Pe("address","Property address",true),Pe("term","Start date and fixed term",true),Pe("rent","Monthly rent (£) and payment day",true),Pe("deposit","Deposit (£, max 5 weeks' rent, TDP protected)",false)]},
    gb_vehicle_sale:{ic:"🚗",n:"Vehicle sale agreement",cat:"gb_contracts",tier:"standard",q:[Pe("role","You are the",true,["Seller","Buyer"]),Pe("other_party","Other party: name and address",true),Pe("vehicle","Vehicle (make/model/year/registration/VIN)",true),Pe("mileage","Mileage",false),Pe("price","Sale price (£)",true),Pe("handover","Deposit (£) and handover date (optional)",false)]},
    gb_freelance_agreement:{ic:"🧰",n:"Freelance / consultancy agreement",cat:"gb_contracts",tier:"komplex",q:[Pe("role","You are the",true,["Consultant/Freelancer","Client"]),Pe("other_party","Other party: name and address",true),Pe("services","Description of the services/deliverables",true),Pe("fees","Fees (£, day rate or fixed) and payment terms",true),Pe("duration","Duration / notice period",false),Pe("ip","IP and confidentiality terms (optional)",false)]},
    gb_guarantor_agreement:{ic:"🤝",n:"Guarantor agreement",cat:"gb_contracts",tier:"standard",q:[Pe("guarantor","Guarantor: name and address",true),Pe("principal","Tenant/borrower being guaranteed: name",true),Pe("beneficiary","Landlord/lender: name and address",true),Pe("obligation","Obligation guaranteed (e.g. monthly rent £)",true),Pe("limit","Limit of liability / duration (optional)",false)]},
    gb_family_loan:{ic:"💷",n:"Family loan agreement",cat:"gb_contracts",tier:"standard",q:[Pe("lender","Lender: name and address",true),Pe("borrower","Borrower: name and address",true),Pe("relationship","Relationship between the parties",false),Pe("amount","Amount lent (£)",true),Pe("repayment","Repayment plan (instalments, dates)",true),Pe("interest","Interest (%, or 'none')",false)]},
    gb_partnership_agreement:{ic:"🏢",n:"Simple partnership agreement",cat:"gb_contracts",tier:"komplex",q:[Pe("partners","Partners: full names and addresses",true),Pe("business","Business name and activity",true),Pe("capital","Capital contributions (£ each)",true),Pe("shares","Profit/loss shares (%)",true),Pe("decisions","Decision-making and banking arrangements",false),Pe("exit","Leaving/dissolution terms (optional)",false)]},
    gb_house_share_agreement:{ic:"🛏️",n:"House-share agreement (joint occupiers)",cat:"gb_contracts",tier:"standard",q:[Pe("occupiers","All occupiers: full names",true),Pe("address","Property address",true),Pe("split","Rent and bills split (£ per person)",true),Pe("rules","House rules (cleaning, guests, quiet hours)",false),Pe("start","Start date",true)]},
    gb_ip_assignment:{ic:"💡",n:"IP assignment letter",cat:"gb_contracts",tier:"standard",q:[Pe("assignor","Assignor (current owner): name and address",true),Pe("assignee","Assignee (new owner): name and address",true),Pe("work","Work/IP being assigned (description)",true),Pe("consideration","Consideration (£)",true),Pe("effective","Effective date (optional)",false)]},

    /* Housing & Tenancy */
    gb_agent_complaint:{ic:"🏢",n:"Complaint to letting agent (redress scheme)",cat:"gb_housing",tier:"standard",q:[Pe("agent","Letting agent: name and address",true),Pe("address","Property address",true),Pe("complaint","Complaint (fees, service, repairs handling; dates)",true),Pe("resolution","Resolution sought",true),Pe("scheme","Redress scheme if known",false,["The Property Ombudsman","Property Redress Scheme","Not sure"]),Pe("details","Your full name",true)]},
    gb_disrepair_lba:{ic:"⚠️",n:"Disrepair letter before action (Homes Act 2018)",cat:"gb_housing",tier:"komplex",q:[Pe("landlord","Landlord: name and address",true),Pe("address","Property address",true),Pe("defects","Defects and when they were reported",true),Pe("impact","Impact (health, belongings, unfitness for habitation)",true),Pe("deadline","Deadline to respond (usually 20 working days)",false),Pe("details","Your full name",true)]},
    gb_landlord_consent:{ic:"🔑",n:"Request for landlord consent (pets/alterations)",cat:"gb_housing",tier:"einfach",q:[Pe("landlord","Landlord/agent: name and address",true),Pe("address","Property address",true),Pe("request","Consent requested",true,["Keeping a pet","Alterations/decoration","Installing appliances","Other"]),Pe("info","Details (pet type, works planned, reinstatement offer)",true),Pe("details","Your full name",true)]},
    gb_deposit_dispute:{ic:"⚖️",n:"Notice seeking deposit dispute resolution (TDS/DPS)",cat:"gb_housing",tier:"standard",q:[Pe("scheme","Deposit protection scheme",true,["DPS","MyDeposits","TDS","Not sure"]),Pe("landlord","Landlord/agent: name",true),Pe("address","Property address and tenancy dates",true),Pe("amounts","Deposit (£) and amount in dispute (£)",true),Pe("dispute","Deductions disputed and why",true),Pe("details","Your full name",true)]},
    gb_joint_tenancy_change:{ic:"👥",n:"Joint tenancy change request",cat:"gb_housing",tier:"standard",q:[Pe("landlord","Landlord/agent: name and address",true),Pe("address","Property address",true),Pe("tenants","Current joint tenants: names",true),Pe("change","Change requested (remove/add/replace a tenant)",true),Pe("date","Proposed date of the change",false),Pe("details","Your full name",true)]},

    /* Work & Employment */
    gb_written_reasons:{ic:"📄",n:"Request for written reasons for dismissal",cat:"gb_work",tier:"einfach",q:[Pe("employer","Employer: name and address",true),Pe("job","Your job title and employment dates",true),Pe("dismissal","Dismissal date",true),Pe("details","Your full name",true)]},
    gb_disciplinary_appeal:{ic:"⚖️",n:"Appeal against disciplinary outcome",cat:"gb_work",tier:"komplex",q:[Pe("recipient","Recipient (named in the outcome letter / HR)",true),Pe("outcome","Outcome and date it was given",true),Pe("grounds","Grounds of appeal (procedure, evidence, severity)",true),Pe("evidence","New evidence (optional)",false),Pe("sought","Outcome you are seeking",true)]},
    gb_redundancy_response:{ic:"📋",n:"Redundancy consultation response",cat:"gb_work",tier:"komplex",q:[Pe("employer","Employer: name",true),Pe("job","Your job title and start date",true),Pe("proposal","The redundancy proposal (pool, criteria, timeline)",true),Pe("points","Your points (selection criteria, alternatives, suitable roles)",true),Pe("details","Your full name",true)]},
    gb_settlement_negotiation:{ic:"🤝",n:"Settlement agreement negotiation letter",cat:"gb_work",tier:"komplex",q:[Pe("employer","Employer: name and address",true),Pe("job","Your job title and employment dates",true),Pe("background","Background to the dispute/exit",true),Pe("offer","Current offer (£, if any)",false),Pe("counter","Your counter-proposal (£ and terms, e.g. reference)",true),Pe("details","Your full name",true)]},
    gb_reasonable_adjustments:{ic:"♿",n:"Request for reasonable adjustments (Equality Act 2010)",cat:"gb_work",tier:"standard",q:[Pe("employer","Employer/manager: name",true),Pe("job","Your job title",true),Pe("condition","Condition/disability and how it affects your work",true),Pe("adjustments","Adjustments requested (equipment, hours, duties)",true),Pe("details","Your full name",true)]},

    /* Consumer & Disputes */
    gb_erasure_request:{ic:"🗑️",n:"Right to erasure request (UK GDPR)",cat:"gb_consumer",tier:"standard",q:[Pe("organisation","Organisation: name and address",true),Pe("relationship","Your relationship (customer, ex-employee...)",true),Pe("data","Data to be erased (accounts, marketing lists, records)",true),Pe("reason","Reason (no longer necessary, consent withdrawn...)",false),Pe("details","Your full name and details to identify you",true)]},
    gb_cease_desist:{ic:"🚫",n:"Harassment cease-and-desist letter",cat:"gb_consumer",tier:"komplex",q:[Pe("recipient","Recipient: name and address",true),Pe("conduct","The conduct (what, when, how often; dates)",true),Pe("effect","Effect on you",true),Pe("demand","Demand and deadline (stop contact, delete posts...)",true),Pe("details","Your full name",true)]},
    gb_statutory_nuisance:{ic:"🔊",n:"Statutory nuisance complaint to council (noise)",cat:"gb_consumer",tier:"standard",q:[Pe("council","Council: name (environmental health team)",true),Pe("source","Source of the noise (address/business)",true),Pe("nuisance","Description of the noise",true),Pe("times","Times, frequency and diary kept",true),Pe("effect","Effect on your home life (optional)",false),Pe("details","Your name and address",true)]},
    gb_debt_plan:{ic:"💷",n:"Debt repayment plan proposal",cat:"gb_consumer",tier:"standard",q:[Pe("creditor","Creditor: name and address",true),Pe("reference","Account/reference number",true),Pe("debt","Total debt (£)",true),Pe("offer","Monthly offer (£)",true),Pe("budget","Income/expenditure summary (optional)",false),Pe("details","Your full name and address",true)]},
    gb_default_notice_response:{ic:"📨",n:"Default notice response letter",cat:"gb_consumer",tier:"standard",q:[Pe("creditor","Creditor: name and address",true),Pe("agreement","Agreement/account number",true),Pe("notice_date","Date of the default notice",true),Pe("position","Your position",true,["I dispute the debt/amount","I need time to pay","I propose a repayment plan","The notice is defective"]),Pe("proposal","Details of your dispute/proposal (£)",true),Pe("details","Your full name and address",true)]},

    /* Government & Admin */
    gb_school_appeal:{ic:"🎓",n:"School admission appeal",cat:"gb_admin",tier:"komplex",q:[Pe("authority","Admission authority / council",true),Pe("school","School appealed for",true),Pe("child","Child: name and date of birth",true),Pe("reasons","Why this school (siblings, distance, needs, care)",true),Pe("grounds","Grounds (criteria misapplied, prejudice balance)",false),Pe("details","Your name and address",true)]},
    gb_nhs_complaint:{ic:"🏥",n:"NHS / GP complaint letter",cat:"gb_admin",tier:"standard",q:[Pe("provider","GP practice / NHS trust: name and address",true),Pe("events","What happened (dates, staff, treatment)",true),Pe("impact","Impact on you (optional)",false),Pe("outcome","Outcome sought (explanation, apology, change)",true),Pe("details","Patient name, date of birth and address",true)]},
    gb_council_review:{ic:"📋",n:"Housing benefit / council decision review request",cat:"gb_admin",tier:"standard",q:[Pe("council","Council: name and department",true),Pe("decision","Decision, date and reference number",true),Pe("grounds","Why the decision is wrong",true),Pe("evidence","Evidence you can provide (optional)",false),Pe("details","Your name, address and claim reference",true)]},
    gb_bailiff_dispute:{ic:"🚪",n:"Bailiff (enforcement agent) dispute letter",cat:"gb_admin",tier:"komplex",q:[Pe("company","Enforcement company: name and address",true),Pe("reference","Debt reference and original creditor",true),Pe("events","What happened (visits, fees charged £, items taken)",true),Pe("grounds","Grounds (wrong person, vulnerability, excessive fees, debt paid)",true),Pe("details","Your full name and address",true)]},
    gb_blue_badge_support:{ic:"♿",n:"Blue badge application support letter",cat:"gb_admin",tier:"einfach",q:[Pe("council","Council: name",true),Pe("applicant","Applicant: name and date of birth",true),Pe("condition","Condition and how it affects mobility (walking distance)",true),Pe("journeys","Typical journeys affected (optional)",false),Pe("details","Your address and contact details",true)]},
    gb_records_correction:{ic:"🗂️",n:"Electoral register / council records correction request",cat:"gb_admin",tier:"einfach",q:[Pe("council","Council: name (electoral services / relevant team)",true),Pe("record","Record affected",true,["Electoral register","Council tax records","Housing records","Other"]),Pe("wrong","Incorrect information currently held",true),Pe("correct","Correct information",true),Pe("details","Your full name and address",true)]}
  };

  function inject(){
    try{
      if(typeof DOCS==="undefined"||!DOCS) return false;
      if(typeof CATS==="undefined"||!CATS.gb_cancel) return false; /* CH_GB_MODULE yüklenmiş olmalı */
      for(var k in E){ if(!DOCS[k]) DOCS[k]=E[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=E[k].tier||"standard"; }
      for(var nc in NEWCATS){ if(!CATS[nc]) CATS[nc]={n:NEWCATS[nc].n,ic:NEWCATS[nc].ic,docs:[],country:"GB"}; if(!CATS[nc].docs) CATS[nc].docs=[]; }
      for(var k2 in E){ var c2=E[k2].cat; if(CATS[c2]&&CATS[c2].docs&&CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); }
      if(typeof CAT_LABELS!=="undefined"){ for(var cl in NEWCATS) CAT_LABELS[cl]=NEWCATS[cl].n; }
      if(typeof CAT_DOCS!=="undefined"){
        for(var cd in NEWCATS){ if(!CAT_DOCS[cd]) CAT_DOCS[cd]=[]; }
        for(var k3 in E){ var c3=E[k3].cat; if(CAT_DOCS[c3]){ var ex=false; for(var i=0;i<CAT_DOCS[c3].length;i++){ if(CAT_DOCS[c3][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[c3].push({k:k3,ic:E[k3].ic,t:E[k3].n,tier:E[k3].tier||"standard"}); } }
      }
      return (typeof DOCS!=="undefined" && DOCS.gb_separation_agreement)?true:false;
    }catch(e){ return false; }
  }

  var done=false, ticks=0;
  function run(){ if(!done){ if(inject()) done=true; } if(ticks++<40) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}
CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
if($src!==$start){ file_put_contents($file,$src); echo "UK genisletme eklendi (CH_GB_EXPAND) - +36 belge, yeni kategori: Family.\n"; }
else echo "degisiklik yok\n";
echo "\nOzet:\n";
echo "  Family (YENI): child arrangements, CMS arrears, parental responsibility, travel consent, separation agreement, cohabitation, change of name (+7)\n";
echo "  Contracts: AST draft, vehicle sale, freelance, guarantor, family loan, partnership, house-share, IP assignment (+8)\n";
echo "  Housing: letting agent complaint, disrepair LBA (Homes Act 2018), landlord consent, deposit dispute (TDS/DPS), joint tenancy change (+5)\n";
echo "  Work: written reasons, disciplinary appeal, redundancy response, settlement negotiation, reasonable adjustments (+5)\n";
echo "  Consumer: erasure request (UK GDPR), cease-and-desist, statutory nuisance, debt plan, default notice response (+5)\n";
echo "  Admin: school appeal, NHS complaint, council review, bailiff dispute, blue badge, records correction (+6)\n";
echo "\nStripe: tum belgeler tier'li -> odeme otomatik. Belge dili/hukuku CC=GB ile otomatik Ingilizce/Ingiltere-Galler hukuku.\n";
echo "SONRA: opcache-reset.php. SIL: rm apply-uk-expand.php\n";
echo "Test: GB sec -> 'Family' kategorisi gorunmeli; Contracts'ta 'Vehicle sale agreement' gorunmeli.\n";
