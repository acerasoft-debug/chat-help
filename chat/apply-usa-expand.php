<?php
/**
 * ChatHelp — USA KATALOG GENISLETME (+36 docs, 1 yeni kategori: Family)
 * --------------------------------------------------------------------------------
 *  Mevcut 6 US kategorisine yeni belgeler ekler + "Family" kategorisi acar
 *  (yeni kategori enjeksiyonu apply-turkiye-expand.php ile ayni kanitlanmis desen).
 *  One cikanlar: parenting plan, prenup taslagi, residential lease, contractor
 *  agreement, wage claim, ADA accommodation, medical bill dispute, FDCPA cease,
 *  IRS installment, USCIS case inquiry. Mektup ve sozlesme agirlikli.
 *  Tum belgeler tier'li -> Stripe odeme otomatik. US hukuku eyalete gore degisir:
 *  hukuken onemli her belge eyalet (State) sorusu icerir.
 *
 * KULLANIM: chat-help.com/chat/apply-usa-expand.php -> opcache-reset.php. SIL.
 * GEREKLI: apply-usa.php once calistirilmis olmali (CH_US_MODULE, us_* kategoriler).
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-usa-expand BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,"CH_US_EXPAND")!==false) exit("Zaten ekli (CH_US_EXPAND).\n");
if(strpos($src,"CH_US_MODULE")===false) exit("CH_US_MODULE yok - once apply-usa.php calistir.\n");
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-usexpand-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_US_EXPAND — USA catalog expansion (+36 docs, new category: Family; deferred load) */
try{(function(){
  function Pq(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}

  var NEWCATS={
    us_family:{n:"Family",ic:"👨‍👩‍👧"}
  };

  var E={
    /* Family (new category) */
    us_child_support_demand:{ic:"💵",n:"Child support demand letter",cat:"us_family",tier:"standard",q:[Pq("parent","Other parent: name and address",true),Pq("children","Child(ren): names and ages",true),Pq("amount","Monthly support owed/requested ($)",true),Pq("arrears","Past-due amount ($, optional)",false),Pq("state","State (US state, e.g. California)",true),Pq("name","Your full name and address",true)]},
    us_custody_proposal:{ic:"🤝",n:"Custody / visitation proposal letter",cat:"us_family",tier:"komplex",q:[Pq("parent","Other parent: name and address",true),Pq("children","Child(ren): names and ages",true),Pq("current","Current custody/visitation arrangement",true),Pq("proposal","Proposed schedule (weekdays, weekends, holidays)",true),Pq("state","State (US state, e.g. California)",true),Pq("name","Your full name",true)]},
    us_parenting_plan:{ic:"📋",n:"Parenting plan draft",cat:"us_family",tier:"komplex",q:[Pq("parents","Both parents: full names",true),Pq("children","Child(ren): names and ages",true),Pq("schedule","Physical custody / weekly schedule",true),Pq("holidays","Holidays and vacations split (optional)",false),Pq("decisions","Decision-making (education, health, religion)",true),Pq("state","State (US state, e.g. California)",true)]},
    us_child_travel_consent:{ic:"✈️",n:"Child travel consent form",cat:"us_family",tier:"standard",q:[Pq("child","Child: full name and date of birth",true),Pq("traveler","Accompanying adult: name and relationship",true),Pq("consenting","Consenting parent: full name and address",true),Pq("destination","Destination (country/state)",true),Pq("dates","Travel dates (from - to)",true),Pq("state","State (US state, e.g. California)",true)]},
    us_separation_agreement:{ic:"📜",n:"Separation agreement draft",cat:"us_family",tier:"komplex",q:[Pq("spouses","Spouses: full names",true),Pq("separation_date","Date of separation",true),Pq("children","Child(ren): names and ages (or 'none')",true),Pq("property","Division of property and debts",true),Pq("support","Spousal/child support terms (optional)",false),Pq("state","State (US state, e.g. California)",true)]},
    us_prenup:{ic:"💍",n:"Prenuptial agreement draft",cat:"us_family",tier:"komplex",q:[Pq("parties","Both parties: full names",true),Pq("wedding_date","Planned wedding date",true),Pq("assets","Separate assets and debts of each party",true),Pq("terms","Key terms (property, spousal support waiver)",true),Pq("state","State (US state, e.g. California)",true),Pq("note","NOTE: independent attorney review for each party is strongly recommended",false)]},
    us_name_change_petition:{ic:"✍️",n:"Name change petition preparation letter",cat:"us_family",tier:"standard",q:[Pq("current_name","Current full legal name",true),Pq("new_name","Requested new name",true),Pq("reason","Reason for the change",true),Pq("court","County court to file in (county/city, optional)",false),Pq("state","State (US state, e.g. California)",true)]},

    /* Contracts & Agreements */
    us_residential_lease:{ic:"🏠",n:"Residential lease agreement draft",cat:"us_contracts",tier:"komplex",q:[Pq("role","You are the",true,["Landlord","Tenant"]),Pq("other_party","Other party: name and address",true),Pq("address","Rental property address",true),Pq("rent","Monthly rent and security deposit ($)",true),Pq("term","Lease term (start date, length)",true),Pq("state","State (US state, e.g. California)",true)]},
    us_vehicle_purchase:{ic:"🚗",n:"Vehicle purchase agreement",cat:"us_contracts",tier:"standard",q:[Pq("role","You are the",true,["Seller","Buyer"]),Pq("other_party","Other party: name and address",true),Pq("vehicle","Vehicle (make/model/year/VIN/mileage)",true),Pq("price","Purchase price ($)",true),Pq("payment","Payment terms (deposit, delivery date - optional)",false),Pq("state","State (US state, e.g. California)",true)]},
    us_contractor_agreement:{ic:"🧰",n:"Independent contractor agreement",cat:"us_contracts",tier:"komplex",q:[Pq("role","You are the",true,["Hiring party","Contractor"]),Pq("other_party","Other party: name and address",true),Pq("work","Scope of work / deliverables",true),Pq("payment","Compensation and payment schedule ($)",true),Pq("term","Timeline / deadline (optional)",false),Pq("state","State (US state, e.g. California)",true)]},
    us_sublease_agreement:{ic:"🔑",n:"Sublease agreement",cat:"us_contracts",tier:"standard",q:[Pq("sublandlord","Sublandlord (original tenant): name",true),Pq("subtenant","Subtenant: name and address",true),Pq("address","Property address",true),Pq("rent","Monthly rent and deposit ($)",true),Pq("period","Sublease period (from - to)",true),Pq("state","State (US state, e.g. California)",true)]},
    us_secured_loan:{ic:"💰",n:"Loan agreement with collateral",cat:"us_contracts",tier:"komplex",q:[Pq("lender","Lender: name and address",true),Pq("borrower","Borrower: name and address",true),Pq("amount","Loan amount ($)",true),Pq("collateral","Collateral (item securing the loan)",true),Pq("repayment","Repayment terms (schedule, interest %, optional)",true),Pq("state","State (US state, e.g. California)",true)]},
    us_partnership_agreement:{ic:"🤝",n:"Simple partnership agreement",cat:"us_contracts",tier:"komplex",q:[Pq("partners","Partners: names and addresses",true),Pq("business","Business name and purpose",true),Pq("contributions","Capital contributions ($ each)",true),Pq("split","Profit/loss split and management roles",true),Pq("state","State (US state, e.g. California)",true)]},
    us_personal_guarantee:{ic:"🖊️",n:"Personal guarantee",cat:"us_contracts",tier:"standard",q:[Pq("guarantor","Guarantor: name and address",true),Pq("creditor","Creditor / obligee: name and address",true),Pq("obligation","Obligation guaranteed (loan, lease - amount $)",true),Pq("limit","Guarantee limit ($, optional)",false),Pq("state","State (US state, e.g. California)",true)]},
    us_media_release:{ic:"📸",n:"Photo / media release form",cat:"us_contracts",tier:"einfach",q:[Pq("subject","Person granting the release: name",true),Pq("producer","Party receiving the rights (photographer/company)",true),Pq("usage","Permitted use (marketing, social media, print)",true),Pq("compensation","Compensation ($ or 'none', optional)",false),Pq("state","State (US state, e.g. California)",true)]},

    /* Housing & Tenancy */
    us_entry_violation:{ic:"🚪",n:"Landlord entry violation notice",cat:"us_housing",tier:"standard",q:[Pq("landlord","Landlord/property manager: name and address",true),Pq("address","Rental property address",true),Pq("incidents","Unauthorized entries (dates, circumstances)",true),Pq("request","What you demand (proper notice going forward)",true),Pq("state","State (US state, e.g. California)",true),Pq("name","Your full name",true)]},
    us_deposit_itemization:{ic:"🧾",n:"Security deposit itemization dispute",cat:"us_housing",tier:"standard",q:[Pq("landlord","Landlord: name and address",true),Pq("address","Rental property address",true),Pq("deductions","Disputed deductions (item, amount $)",true),Pq("arguments","Why the deductions are improper",true),Pq("state","State (US state, e.g. California)",true),Pq("name","Your full name and forwarding address",true)]},
    us_hoa_dispute:{ic:"🏘️",n:"HOA dispute letter",cat:"us_housing",tier:"standard",q:[Pq("hoa","HOA / management company: name and address",true),Pq("property","Your property address/unit",true),Pq("issue","The dispute (fine, rule, maintenance - facts and dates)",true),Pq("request","Resolution you seek",true),Pq("state","State (US state, e.g. California)",true),Pq("name","Your full name",true)]},
    us_mold_complaint:{ic:"🦠",n:"Mold / habitability complaint escalation",cat:"us_housing",tier:"komplex",q:[Pq("landlord","Landlord/property manager: name and address",true),Pq("address","Rental property address",true),Pq("problem","Mold/habitability issue and health effects",true),Pq("prior","Prior complaints (dates, landlord responses)",true),Pq("state","State (US state, e.g. California)",true),Pq("name","Your full name",true)]},
    us_mtm_rent_increase:{ic:"📈",n:"Month-to-month rent increase response",cat:"us_housing",tier:"standard",q:[Pq("landlord","Landlord: name and address",true),Pq("address","Rental property address",true),Pq("notice","Increase notice (date received, new rent $)",true),Pq("current_rent","Current rent ($)",true),Pq("position","Your response (object, negotiate, request more time)",true),Pq("state","State (US state, e.g. California)",true)]},

    /* Work & Employment */
    us_wage_claim:{ic:"🏛️",n:"Wage claim to state labor department",cat:"us_work",tier:"komplex",q:[Pq("agency","State labor department / office (if known, optional)",false),Pq("employer","Employer: company name and address",true),Pq("period","Employment dates and pay period(s) in dispute",true),Pq("owed","Wages owed (hours, rate, total $)",true),Pq("state","State (US state, e.g. California)",true),Pq("name","Your full name and address",true)]},
    us_noncompete_pushback:{ic:"🚫",n:"Non-compete pushback letter",cat:"us_work",tier:"komplex",q:[Pq("employer","Employer (or former employer): name and address",true),Pq("clause","The non-compete terms (scope, duration, area)",true),Pq("situation","Your situation (new job offer, role)",true),Pq("arguments","Why it is unenforceable/overbroad (optional)",false),Pq("state","State (US state, e.g. California)",true),Pq("name","Your full name",true)]},
    us_employment_verification:{ic:"📇",n:"Employment verification request",cat:"us_work",tier:"einfach",q:[Pq("employer","Employer / HR: name and address",true),Pq("position","Your job title and employment dates",true),Pq("details","Details to confirm (salary, dates, title)",true),Pq("recipient","Where to send it (lender, landlord - optional)",false),Pq("name","Your full name and contact info",true)]},
    us_ada_accommodation:{ic:"♿",n:"Workplace accommodation request (ADA)",cat:"us_work",tier:"standard",q:[Pq("employer","Employer / HR: name",true),Pq("position","Your job title",true),Pq("condition","Medical condition / limitation (share only what you wish)",true),Pq("accommodation","Accommodation requested",true),Pq("state","State (US state, e.g. California)",true),Pq("name","Your full name",true)]},
    us_severance_negotiation:{ic:"🤝",n:"Severance negotiation letter",cat:"us_work",tier:"komplex",q:[Pq("employer","Employer: company name and address",true),Pq("position","Your job title and tenure",true),Pq("offer","Severance offered (weeks, $, terms)",true),Pq("counter","Your counter-proposal and leverage (facts)",true),Pq("state","State (US state, e.g. California)",true),Pq("name","Your full name",true)]},

    /* Consumer & Disputes */
    us_medical_bill_dispute:{ic:"🏥",n:"Medical bill dispute / negotiation letter",cat:"us_consumer",tier:"standard",q:[Pq("provider","Provider / billing office: name and address",true),Pq("account_no","Account / invoice number",true),Pq("charges","Disputed charges (date of service, amount $)",true),Pq("reason","Why (billing error, surprise bill, hardship - what you propose)",true),Pq("state","State (US state, e.g. California)",true),Pq("name","Your full name and address",true)]},
    us_insurance_appeal:{ic:"🛡️",n:"Insurance claim appeal",cat:"us_consumer",tier:"komplex",q:[Pq("insurer","Insurer: name and address",true),Pq("policy_no","Policy and claim number",true),Pq("denial","Denial (date, stated reason)",true),Pq("arguments","Why the denial is wrong (facts, policy coverage)",true),Pq("state","State (US state, e.g. California)",true),Pq("name","Your full name and address",true)]},
    us_identity_theft:{ic:"🪪",n:"Identity theft affidavit cover letter (FTC)",cat:"us_consumer",tier:"standard",q:[Pq("recipient","Recipient (creditor/bureau): name and address",true),Pq("fraud","Fraudulent accounts/charges (creditor, amount $)",true),Pq("report","FTC report / police report number (optional)",false),Pq("name","Your full name and address",true)]},
    us_fdcpa_cease:{ic:"✋",n:"Debt collector cease-communication letter (FDCPA)",cat:"us_consumer",tier:"standard",q:[Pq("collector","Debt collector: name and address",true),Pq("account_no","Account / reference number from their notices",true),Pq("scope","Stop all contact or limit it?",true,["Stop all communication","Contact in writing only","Stop workplace calls only"]),Pq("name","Your full name and address",true)]},
    us_towing_dispute:{ic:"🚙",n:"Wrongful towing dispute",cat:"us_consumer",tier:"standard",q:[Pq("tow_company","Towing company: name and address",true),Pq("incident","Tow (date, location, where the car was parked)",true),Pq("fees","Fees paid/demanded ($)",true),Pq("grounds","Why the tow was improper (signage, authorization)",true),Pq("state","State (US state, e.g. California)",true),Pq("name","Your full name and address",true)]},
    us_ag_escalation:{ic:"🏛️",n:"Gym / timeshare escalation to state attorney general",cat:"us_consumer",tier:"komplex",q:[Pq("business","Business: name and address",true),Pq("contract","Contract (type, date, amount $)",true),Pq("history","What happened and prior cancellation attempts",true),Pq("resolution","Resolution you seek",true),Pq("state","State (US state, e.g. California)",true),Pq("name","Your full name and address",true)]},

    /* Government & Agencies */
    us_irs_installment:{ic:"🧾",n:"IRS installment agreement request",cat:"us_gov",tier:"standard",q:[Pq("tax_year","Tax year(s) and amount owed ($)",true),Pq("payment","Monthly payment you can afford ($)",true),Pq("notice","IRS notice number (optional)",false),Pq("name","Your full name, address and SSN/EIN last 4",true)]},
    us_dmv_hearing:{ic:"🚗",n:"DMV administrative hearing request",cat:"us_gov",tier:"komplex",q:[Pq("dmv","DMV office (city)",true),Pq("action","Action contested (suspension, revocation - notice date)",true),Pq("grounds","Grounds for the hearing / your side of the facts",true),Pq("license","Driver's license number (optional)",false),Pq("state","State (US state, e.g. California)",true),Pq("name","Your full name and address",true)]},
    us_school_appeal:{ic:"🎓",n:"School district appeal letter",cat:"us_gov",tier:"standard",q:[Pq("district","School district / office: name",true),Pq("student","Student: name and school",true),Pq("decision","Decision appealed (enrollment, discipline, IEP - date)",true),Pq("arguments","Why it should be reversed",true),Pq("state","State (US state, e.g. California)",true),Pq("name","Your full name and relationship (parent/guardian)",true)]},
    us_state_records:{ic:"📂",n:"Public records request (state-level)",cat:"us_gov",tier:"standard",q:[Pq("agency","State/local agency: name and address",true),Pq("records","Records requested (be specific: dates, topic)",true),Pq("format","Preferred format (email/paper, optional)",false),Pq("state","State (US state, e.g. California)",true),Pq("name","Your full name and address",true)]},
    us_uscis_inquiry:{ic:"🛂",n:"USCIS case inquiry letter",cat:"us_gov",tier:"standard",q:[Pq("receipt_no","Receipt number (e.g. IOE0123456789)",true),Pq("case_type","Case type (I-485, N-400, I-130...)",true),Pq("filed","Filing date and service center",true),Pq("issue","Issue (outside normal processing time, missing notice...)",true),Pq("name","Your full name, address and A-number (optional)",true)]}
  };

  function inject(){
    try{
      if(typeof DOCS==="undefined"||!DOCS) return false;
      if(typeof CATS==="undefined"||!CATS.us_cancel) return false; /* CH_US_MODULE yuklenmis olmali */
      for(var k in E){ if(!DOCS[k]) DOCS[k]=E[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=E[k].tier||"standard"; }
      for(var nc in NEWCATS){ if(!CATS[nc]) CATS[nc]={n:NEWCATS[nc].n,ic:NEWCATS[nc].ic,docs:[],country:"US"}; if(!CATS[nc].docs) CATS[nc].docs=[]; }
      for(var k2 in E){ var c2=E[k2].cat; if(CATS[c2]&&CATS[c2].docs&&CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); }
      if(typeof CAT_LABELS!=="undefined"){ for(var cl in NEWCATS) CAT_LABELS[cl]=NEWCATS[cl].n; }
      if(typeof CAT_DOCS!=="undefined"){
        for(var cd in NEWCATS){ if(!CAT_DOCS[cd]) CAT_DOCS[cd]=[]; }
        for(var k3 in E){ var c3=E[k3].cat; if(CAT_DOCS[c3]){ var ex=false; for(var i=0;i<CAT_DOCS[c3].length;i++){ if(CAT_DOCS[c3][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[c3].push({k:k3,ic:E[k3].ic,t:E[k3].n,tier:E[k3].tier||"standard"}); } }
      }
      return (typeof DOCS!=="undefined" && DOCS.us_parenting_plan)?true:false;
    }catch(e){ return false; }
  }

  var done=false, ticks=0;
  function run(){ if(!done){ if(inject()) done=true; } if(ticks++<40) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}
CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
if($src!==$start){ file_put_contents($file,$src); echo "USA genisletme eklendi (CH_US_EXPAND) - +36 belge, yeni kategori: Family.\n"; }
else echo "degisiklik yok\n";
echo "\nOzet:\n";
echo "  Family (YENI): child support demand, custody proposal, parenting plan, child travel consent, separation agreement, prenup draft, name change petition (+7)\n";
echo "  Contracts: residential lease, vehicle purchase, independent contractor, sublease agreement, secured loan, partnership, personal guarantee, media release (+8)\n";
echo "  Housing: entry violation notice, deposit itemization dispute, HOA dispute, mold escalation, month-to-month rent increase response (+5)\n";
echo "  Work: wage claim (state labor dept), non-compete pushback, employment verification, ADA accommodation, severance negotiation (+5)\n";
echo "  Consumer: medical bill dispute, insurance claim appeal, identity theft (FTC), FDCPA cease-communication, wrongful towing, gym/timeshare AG escalation (+6)\n";
echo "  Gov: IRS installment agreement, DMV hearing request, school district appeal, state public records request, USCIS case inquiry (+5)\n";
echo "\nStripe: tum belgeler tier'li -> odeme otomatik. Hukuken onemli her belge State sorusu icerir (US hukuku eyalete gore degisir).\n";
echo "SONRA: opcache-reset.php. SIL: rm apply-usa-expand.php\n";
echo "Test: US sec -> 'Family' kategorisi gorunmeli; Contracts icinde 'Residential lease agreement draft' gorunmeli.\n";
