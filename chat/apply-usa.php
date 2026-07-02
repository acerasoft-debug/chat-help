<?php
/**
 * ChatHelp — USA MODULE (~39 docs, 6 categories, English, US law — state-aware) — same proven pattern as CH_TR_MODULE
 * -------------------------------------------------------------------------------------------------------------------
 *  • Categories tagged country:'US' -> generalized country filter (CH_TR_MODULE) works automatically
 *  • Welcome grid cards data-cccountry='US' -> card visibility automatic
 *  • Language: setCountry('US') already sets UL='en' (CC_DATA.US.l) — no extra language code needed
 *  • Doc language/law: api.php lawSystems['US'] + doc-lang US->en (via apply-lawsys-all.php)
 *  • US law varies by state: every legally significant doc asks for the US state
 *
 * USAGE: pull-updates.php?files=apply-usa.php&run=1. DELETE afterwards.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-usa BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,"CH_US_MODULE")!==false) exit("Zaten ekli (CH_US_MODULE).\n");
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-us-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_US_MODULE — US law module (deferred; loads once DOCS/CATS are ready) */
try{(function(){
  function Pq(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var XC={
    us_cancel:{n:"Cancellations",ic:"📄"},
    us_housing:{n:"Housing & Tenancy",ic:"🏠"},
    us_work:{n:"Work & Employment",ic:"💼"},
    us_consumer:{n:"Consumer & Disputes",ic:"🛒"},
    us_contracts:{n:"Contracts & Agreements",ic:"📝"},
    us_gov:{n:"Government & Agencies",ic:"🏛️"}
  };
  var X_ORDER=["us_cancel","us_housing","us_work","us_consumer","us_contracts","us_gov"];
  var CCODE="US", DOCWORD="documents";

  var D={
    /* Cancellations */
    us_cancel_gym:{ic:"🏋️",n:"Gym membership cancellation",cat:"us_cancel",tier:"einfach",q:[Pq("gym","Gym: name and address",true),Pq("member_no","Membership number",false),Pq("date","Desired cancellation date",true),Pq("reason","Reason (moving, medical — may waive fees, optional)",false),Pq("state","State (US state, e.g. California)",true),Pq("name","Your full name and address",true)]},
    us_cancel_subscription:{ic:"📺",n:"Subscription cancellation (streaming/app)",cat:"us_cancel",tier:"einfach",q:[Pq("service","Service (Netflix, Spotify…)",true),Pq("account","Account email",true),Pq("date","Cancellation date (optional)",false),Pq("name","Your full name",true)]},
    us_cancel_insurance:{ic:"🛡️",n:"Insurance policy cancellation",cat:"us_cancel",tier:"einfach",q:[Pq("insurer","Insurer: name and address",true),Pq("policy_no","Policy number",true),Pq("type","Type of insurance",true,["Auto","Home/Renters","Health","Life","Other"]),Pq("date","Cancellation effective date",true),Pq("refund","Request refund of unused premium?",false,["Yes","No"]),Pq("name","Your full name and address",true)]},
    us_cancel_timeshare:{ic:"🏝️",n:"Timeshare cancellation (rescission)",cat:"us_cancel",tier:"standard",q:[Pq("company","Timeshare company: name and address",true),Pq("contract_no","Contract number and purchase date",true),Pq("state","State (US state, e.g. California)",true),Pq("amount","Purchase price / deposit paid ($)",false),Pq("name","Your full name and address",true)]},
    us_cancel_internet:{ic:"📶",n:"Cable / internet service cancellation",cat:"us_cancel",tier:"einfach",q:[Pq("provider","Provider: name",true),Pq("account_no","Account number",true),Pq("date","Desired cancellation date",true),Pq("equipment","Equipment to return (modem, box — optional)",false),Pq("name","Your full name and service address",true)]},
    us_close_bank:{ic:"🏦",n:"Bank account closure request",cat:"us_cancel",tier:"einfach",q:[Pq("bank","Bank: name and branch",true),Pq("account_no","Account number",true),Pq("transfer","Account for remaining balance (optional)",false),Pq("name","Your full name and address",true)]},

    /* Housing & Tenancy */
    us_lease_termination:{ic:"🏠",n:"Lease termination notice (30-day)",cat:"us_housing",tier:"einfach",q:[Pq("landlord","Landlord/property manager: name and address",true),Pq("address","Rental property address",true),Pq("move_out","Move-out date (30-day notice)",true),Pq("state","State (US state, e.g. California)",true),Pq("name","Your full name",true)]},
    us_deposit_demand:{ic:"💵",n:"Security deposit demand letter",cat:"us_housing",tier:"standard",q:[Pq("landlord","Landlord: name and address",true),Pq("address","Rental property address",true),Pq("move_out","Move-out date (keys returned)",true),Pq("amount","Deposit amount ($)",true),Pq("state","State (US state, e.g. California)",true),Pq("name","Your full name and forwarding address",true)]},
    us_repair_request:{ic:"🔧",n:"Repair request to landlord (habitability)",cat:"us_housing",tier:"standard",q:[Pq("landlord","Landlord/property manager: name and address",true),Pq("address","Rental property address",true),Pq("problem","Description of the problem/defect",true),Pq("urgent","Is it urgent (no heat, leak, mold)?",false,["Yes","No"]),Pq("state","State (US state, e.g. California)",true),Pq("name","Your full name",true)]},
    us_roommate_agreement:{ic:"🤝",n:"Roommate agreement",cat:"us_housing",tier:"standard",q:[Pq("roommates","Roommates: full names",true),Pq("address","Address of the shared home",true),Pq("rent_split","Rent and utilities split (who pays what, $)",true),Pq("deposit","Security deposit shares ($, optional)",false),Pq("rules","House rules (guests, chores, quiet hours — optional)",false),Pq("state","State (US state, e.g. California)",true)]},
    us_sublease_request:{ic:"🔑",n:"Sublease permission request",cat:"us_housing",tier:"standard",q:[Pq("landlord","Landlord: name and address",true),Pq("address","Rental property address",true),Pq("period","Sublease period (from — to)",true),Pq("subtenant","Proposed subtenant: name (optional)",false),Pq("reason","Reason (optional)",false),Pq("state","State (US state, e.g. California)",true),Pq("name","Your full name",true)]},
    us_rent_increase:{ic:"📈",n:"Response to rent increase",cat:"us_housing",tier:"standard",q:[Pq("landlord","Landlord: name and address",true),Pq("address","Rental property address",true),Pq("current_rent","Current rent ($)",true),Pq("new_rent","Proposed rent ($)",true),Pq("arguments","Your objections / counter-proposal",true),Pq("state","State (US state, e.g. California)",true)]},
    us_noise_complaint:{ic:"🔊",n:"Noise complaint (landlord/HOA)",cat:"us_housing",tier:"standard",q:[Pq("recipient","Recipient (landlord/HOA/neighbor)",true),Pq("problem","Description of the disturbance",true),Pq("times","Frequency and times",false),Pq("request","What you are asking for",true),Pq("state","State (US state, e.g. California)",true),Pq("name","Your name and unit",true)]},

    /* Work & Employment */
    us_resignation:{ic:"👋",n:"Resignation letter (two weeks notice)",cat:"us_work",tier:"einfach",q:[Pq("employer","Employer: company name and address",true),Pq("position","Your job title",true),Pq("last_day","Last day of work (two weeks notice)",true),Pq("state","State (US state, e.g. California)",true),Pq("name","Your full name",true)]},
    us_final_paycheck:{ic:"🧾",n:"Final paycheck demand letter",cat:"us_work",tier:"standard",q:[Pq("employer","Employer: company name and address",true),Pq("position","Your job title and employment dates",true),Pq("owed","Amounts owed (wages, unused PTO, expenses)",true),Pq("amount","Estimated total ($, optional)",false),Pq("state","State (US state, e.g. California)",true),Pq("name","Your full name and address",true)]},
    us_unemployment_appeal:{ic:"📋",n:"Unemployment benefits appeal",cat:"us_work",tier:"komplex",q:[Pq("agency","State unemployment agency / office",true),Pq("claim_no","Claim / determination number",true),Pq("decision","Decision being appealed and its date",true),Pq("arguments","Why the decision is wrong (facts)",true),Pq("state","State (US state, e.g. California)",true),Pq("name","Your full name, address and SSN last 4 (optional)",true)]},
    us_reference_request:{ic:"📇",n:"Reference request (former employer)",cat:"us_work",tier:"einfach",q:[Pq("employer","Former employer / manager: name",true),Pq("position","Your job title and employment dates",true),Pq("purpose","Purpose (new job, housing — optional)",false),Pq("name","Your full name and contact info",true)]},
    us_remote_work:{ic:"🏡",n:"Remote work request",cat:"us_work",tier:"einfach",q:[Pq("employer","Employer / manager",true),Pq("position","Your job title",true),Pq("schedule","Desired arrangement (days/week)",true),Pq("reason","Reason (optional)",false)]},
    us_overtime_claim:{ic:"⏰",n:"Unpaid wages / overtime demand (FLSA)",cat:"us_work",tier:"standard",q:[Pq("employer","Employer: company name and address",true),Pq("position","Your job title",true),Pq("period","Pay period(s) in dispute",true),Pq("hours","Unpaid hours / overtime claimed",true),Pq("state","State (US state, e.g. California)",true),Pq("name","Your full name",true)]},

    /* Consumer & Disputes */
    us_demand_letter:{ic:"⚖️",n:"Small-claims demand letter",cat:"us_consumer",tier:"komplex",q:[Pq("recipient","Recipient: name and address",true),Pq("facts","What happened (dates, agreement, breach)",true),Pq("amount","Amount demanded ($)",true),Pq("deadline","Payment deadline (e.g. 14 days)",false),Pq("state","State (US state, e.g. California)",true),Pq("name","Your full name and address",true)]},
    us_debt_validation:{ic:"💳",n:"Debt validation letter (FDCPA)",cat:"us_consumer",tier:"standard",q:[Pq("collector","Debt collector: name and address",true),Pq("account_no","Account / reference number from the notice",true),Pq("amount","Amount claimed ($)",false),Pq("name","Your full name and address",true)]},
    us_credit_dispute:{ic:"📊",n:"Credit report dispute (FCRA)",cat:"us_consumer",tier:"standard",q:[Pq("bureau","Credit bureau",true,["Equifax","Experian","TransUnion","Other"]),Pq("item","Disputed item (creditor, account number)",true),Pq("reason","Why it is inaccurate",true),Pq("name","Your full name, address and DOB",true)]},
    us_warranty_claim:{ic:"🔧",n:"Warranty claim letter",cat:"us_consumer",tier:"standard",q:[Pq("seller","Seller/manufacturer: name and address",true),Pq("product","Product (brand/model)",true),Pq("purchase_date","Purchase date and price ($)",true),Pq("defect","Defect discovered",true),Pq("request","Your request",true,["Repair","Replacement","Refund"])]},
    us_chargeback:{ic:"💰",n:"Chargeback dispute letter",cat:"us_consumer",tier:"standard",q:[Pq("bank","Card issuer: bank name",true),Pq("card","Card last 4 digits",true),Pq("charge","Disputed charge (merchant, date, amount $)",true),Pq("reason","Reason for the dispute",true),Pq("name","Your full name and address",true)]},
    us_cease_desist:{ic:"🚫",n:"Cease-and-desist letter (harassment)",cat:"us_consumer",tier:"komplex",q:[Pq("recipient","Recipient: name and address",true),Pq("conduct","Conduct to stop (dates, description)",true),Pq("demand","Your demand and consequences",true),Pq("state","State (US state, e.g. California)",true),Pq("name","Your full name",true)]},
    us_insurance_claim:{ic:"🛡️",n:"Insurance claim letter",cat:"us_consumer",tier:"standard",q:[Pq("insurer","Insurer: name and address",true),Pq("policy_no","Policy number",true),Pq("incident","Incident (date, what happened)",true),Pq("amount","Amount claimed ($, optional)",false),Pq("name","Your full name and address",true)]},
    us_refund_request:{ic:"🔄",n:"Refund / return request letter",cat:"us_consumer",tier:"einfach",q:[Pq("seller","Seller: name and address",true),Pq("order_no","Order / receipt number",true),Pq("purchase_date","Purchase date",true),Pq("items","Item(s) and price ($)",true),Pq("reason","Reason for return/refund",true),Pq("name","Your full name",true)]},

    /* Contracts & Agreements */
    us_promissory_note:{ic:"📜",n:"Promissory note",cat:"us_contracts",tier:"standard",q:[Pq("lender","Lender: name and address",true),Pq("borrower","Borrower: name and address",true),Pq("amount","Principal amount ($)",true),Pq("repayment","Repayment terms (schedule, due date)",true),Pq("interest","Interest rate (% per year, optional)",false),Pq("state","State (US state, e.g. California)",true)]},
    us_bill_of_sale:{ic:"🛒",n:"Bill of sale",cat:"us_contracts",tier:"einfach",q:[Pq("role","You are the",true,["Seller","Buyer"]),Pq("other_party","Other party: name and address",true),Pq("item","Item sold (car: make/model/VIN, etc.)",true),Pq("price","Price ($)",true),Pq("condition","Sold as-is / condition notes (optional)",false),Pq("state","State (US state, e.g. California)",true)]},
    us_nda:{ic:"🔒",n:"Non-disclosure agreement (NDA)",cat:"us_contracts",tier:"standard",q:[Pq("other_party","Other party: name and address",true),Pq("purpose","Purpose of the disclosure/collaboration",true),Pq("scope","Scope of confidential information",true),Pq("duration","Duration (optional)",false),Pq("state","State (US state, e.g. California)",true)]},
    us_service_agreement:{ic:"🧰",n:"Service agreement",cat:"us_contracts",tier:"komplex",q:[Pq("role","You are the",true,["Service provider","Client"]),Pq("other_party","Other party: name and address",true),Pq("service","Description of the services",true),Pq("payment","Price and payment terms ($)",true),Pq("term","Term / timeline (optional)",false),Pq("clauses","Special clauses (optional)",false),Pq("state","State (US state, e.g. California)",true)]},
    us_power_of_attorney:{ic:"📝",n:"Simple power of attorney",cat:"us_contracts",tier:"standard",q:[Pq("agent","Agent (person authorized): name and address",true),Pq("powers","Powers granted (what they may do)",true),Pq("duration","Effective period (optional)",false),Pq("state","State (US state, e.g. California)",true),Pq("name","Your (principal) full name and address",true)]},
    us_loan_agreement:{ic:"🤝",n:"Personal loan agreement",cat:"us_contracts",tier:"standard",q:[Pq("lender","Lender: name and address",true),Pq("borrower","Borrower: name and address",true),Pq("amount","Loan amount ($)",true),Pq("repayment","Repayment plan (installments, dates)",true),Pq("interest","Interest rate (%, optional)",false),Pq("state","State (US state, e.g. California)",true)]},

    /* Government & Agencies */
    us_foia:{ic:"📂",n:"FOIA request (public records)",cat:"us_gov",tier:"standard",q:[Pq("agency","Agency: name and address",true),Pq("records","Records requested (be specific: dates, topic)",true),Pq("format","Preferred format (email/paper, optional)",false),Pq("name","Your full name and address",true)]},
    us_irs_abatement:{ic:"🧾",n:"IRS penalty abatement request",cat:"us_gov",tier:"komplex",q:[Pq("notice","IRS notice number and date",true),Pq("tax_year","Tax year and penalty amount ($)",true),Pq("reason","Reasonable cause (illness, disaster, first-time)",true),Pq("name","Your full name, address and SSN/EIN last 4",true)]},
    us_dmv:{ic:"🚗",n:"DMV correspondence (records/appeal)",cat:"us_gov",tier:"standard",q:[Pq("dmv","DMV office (city)",true),Pq("subject","Subject",true,["Record correction","License issue","Registration issue","Hearing request","Other"]),Pq("facts","Facts and your request",true),Pq("state","State (US state, e.g. California)",true),Pq("name","Your full name, address and license number (optional)",true)]},
    us_ag_complaint:{ic:"🏛️",n:"Complaint to state attorney general (consumer protection)",cat:"us_gov",tier:"standard",q:[Pq("business","Business complained about: name and address",true),Pq("facts","What happened (dates, amounts $)",true),Pq("resolution","Resolution you seek",true),Pq("state","State (US state, e.g. California)",true),Pq("name","Your full name and address",true)]},
    us_parking_appeal:{ic:"🅿️",n:"Parking ticket appeal",cat:"us_gov",tier:"einfach",q:[Pq("citation","Citation number",true),Pq("date","Date and location of the ticket",true),Pq("plate","License plate (optional)",false),Pq("grounds","Grounds for the appeal",true),Pq("name","Your full name and address",true)]},
    us_usps_complaint:{ic:"📮",n:"USPS mail complaint (lost/delayed)",cat:"us_gov",tier:"einfach",q:[Pq("issue","Issue",true,["Lost mail/package","Delayed delivery","Damaged package","Delivery problems","Other"]),Pq("tracking","Tracking number (optional)",false),Pq("facts","Details (dates, addresses)",true),Pq("name","Your full name and address",true)]}
  };

  function inject(){
    try{
      if(typeof DOCS==="undefined"||!DOCS) return false;
      for(var k in D){ if(!DOCS[k]) DOCS[k]=D[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=D[k].tier||"standard"; }
      if(typeof CATS!=="undefined"&&CATS){
        for(var ck in XC){ if(!CATS[ck]) CATS[ck]={n:XC[ck].n,ic:XC[ck].ic,docs:[],country:CCODE}; if(!CATS[ck].docs) CATS[ck].docs=[]; }
        for(var k2 in D){ var c2=D[k2].cat; if(CATS[c2]&&CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); }
      }
      if(typeof CAT_LABELS!=="undefined"){ for(var cl in XC) CAT_LABELS[cl]=XC[cl].n; }
      if(typeof CAT_DOCS!=="undefined"){
        for(var cd in XC){ if(!CAT_DOCS[cd]) CAT_DOCS[cd]=[]; }
        for(var k3 in D){ var c3=D[k3].cat; if(CAT_DOCS[c3]){ var ex=false; for(var i=0;i<CAT_DOCS[c3].length;i++){ if(CAT_DOCS[c3][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[c3].push({k:k3,ic:D[k3].ic,t:D[k3].n,tier:D[k3].tier||"standard"}); } }
      }
      return (typeof DOCS!=="undefined" && DOCS.us_demand_letter)?true:false;
    }catch(e){ return false; }
  }

  /* Ülke filtresi (CH_TR_MODULE kuruyor; yoksa aynı korumalı sarmalayıcı burada da kurulur) */
  function installMultiCountryFilter(){
    try{
      if(typeof showAllCats!=="function" || showAllCats.__multiCountry) return;
      var _sac=showAllCats;
      var w=function(){
        try{
          if(typeof CATS==="undefined") return _sac.apply(this,arguments);
          var cur=(typeof CC!=="undefined"?CC:"DE");
          var hid={};
          for(var k in CATS){
            var cc=(CATS[k]&&CATS[k].country)||"DE";
            if(cc!==cur){ hid[k]=CATS[k]; delete CATS[k]; }
          }
          try{ return _sac.apply(this,arguments); } finally { for(var h in hid) CATS[h]=hid[h]; }
        }catch(e){ return _sac.apply(this,arguments); }
      };
      w.__multiCountry=1; showAllCats=w; if(typeof window!=="undefined") window.showAllCats=w;
    }catch(e){}
  }

  /* Welcome grid: bu ülkenin kartları (data-cccountry) + görünürlük */
  function xGrid(){
    try{
      var grid=document.querySelector(".wcat-grid"); if(!grid) return;
      var cur=(typeof CC!=="undefined"?CC:"DE");
      if(cur===CCODE){
        X_ORDER.forEach(function(ck){
          if(grid.querySelector('[data-cccountry="'+CCODE+'"][data-ccat="'+ck+'"]')) return;
          var c=XC[ck]; var el=document.createElement("div"); el.className="wcat";
          el.setAttribute("data-cccountry",CCODE); el.setAttribute("data-ccat",ck);
          el.onclick=function(){ try{ if(typeof showCat==="function") showCat(ck); }catch(e){} };
          var n=(typeof CATS!=="undefined"&&CATS[ck]&&CATS[ck].docs)?CATS[ck].docs.length:0;
          el.innerHTML='<div class="wcat-ic">'+c.ic+'</div><div class="wcat-t">'+c.n+'</div><div class="wcat-s">'+n+' '+DOCWORD+'</div>';
          grid.appendChild(el);
        });
      }
      grid.querySelectorAll(".wcat").forEach(function(el){
        var cc=el.getAttribute("data-cccountry")||(el.getAttribute("data-frcat")?"FR":(el.getAttribute("data-trcat")?"TR":"DE"));
        el.style.display=(cc===cur)?"":"none";
      });
    }catch(e){}
  }

  function installCountryHook(){
    try{
      if(typeof setCountry!=="function" || setCountry["__m"+CCODE]) return;
      var _sc=setCountry;
      var s=function(cc){ var r; try{ r=_sc.apply(this,arguments); }catch(e){} try{ xGrid(); }catch(e){} return r; };
      s["__m"+CCODE]=1; setCountry=s; if(typeof window!=="undefined") window.setCountry=s;
    }catch(e){}
  }

  var done=false, ticks=0;
  function run(){ if(!done){ if(inject()) done=true; } installMultiCountryFilter(); installCountryHook(); try{ xGrid(); }catch(e){} if(ticks++<40) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}
CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
if($src!==$start){ file_put_contents($file,$src); echo "USA modulu eklendi (CH_US_MODULE) - ~39 belge, 6 kategori, Ingilizce (eyalet duyarli).\n"; }
else echo "degisiklik yok\n";
echo "\nNOT: Belge dili/hukuku icin apply-lawsys-all.php de calistirilmali (lawSystems['US'] + doc-lang).\n";
echo "SONRA: opcache-reset (pull-updates otomatik yapar). SIL: rm apply-usa.php\n";
echo "Test: Settings -> USA sec -> kategoriler Ingilizce -> 'Small-claims demand letter' uret (State sorusu gelmeli).\n";
