<?php
/**
 * ChatHelp — BİRLEŞİK KRALLIK MODÜLÜ (~39 belge, 6 kategori, İngilizce, İngiltere & Galler hukuku) — CH_TR_MODULE ile aynı kanıtlanmış desen
 * ------------------------------------------------------------------------------------------------------------------------------------------
 *  • Kategoriler country:'GB' etiketli -> genelleştirilmiş ülke filtresi (CH_TR_MODULE) otomatik çalışır
 *  • Welcome grid kartları data-cccountry='GB' -> kart görünürlüğü otomatik
 *  • Dil: setCountry('GB') zaten UL='en' yapıyor (CC_DATA.GB.l) — ek dil kodu gerekmez
 *  • Belge dili/hukuku: api.php'de lawSystems['GB'] + doc-lang GB->en (apply-lawsys-all.php ile)
 *
 * KULLANIM: pull-updates.php?files=apply-uk.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-uk BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,"CH_GB_MODULE")!==false) exit("Zaten ekli (CH_GB_MODULE).\n");
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-gb-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_GB_MODULE — Birleşik Krallık (İngiltere & Galler) hukuku modülü (deferred; DOCS/CATS hazır olunca yüklenir) */
try{(function(){
  function Pq(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var XC={
    gb_cancel:{n:"Cancellations",ic:"📄"},
    gb_housing:{n:"Housing & Tenancy",ic:"🏠"},
    gb_work:{n:"Work & Employment",ic:"💼"},
    gb_consumer:{n:"Consumer & Disputes",ic:"🛒"},
    gb_contracts:{n:"Contracts & Agreements",ic:"📝"},
    gb_admin:{n:"Government & Admin",ic:"🏛️"}
  };
  var X_ORDER=["gb_cancel","gb_housing","gb_work","gb_consumer","gb_contracts","gb_admin"];
  var CCODE="GB", DOCWORD="documents";

  var D={
    /* Cancellations */
    gb_cancel_subscription:{ic:"📺",n:"Cancel a digital subscription",cat:"gb_cancel",tier:"einfach",q:[Pq("platform","Platform (Netflix, Spotify…)",true),Pq("account","Account email address",true),Pq("date","Cancellation date (optional)",false),Pq("details","Your full name",true)]},
    gb_cancel_gym:{ic:"🏋️",n:"Gym / club membership cancellation",cat:"gb_cancel",tier:"einfach",q:[Pq("gym","Gym/club: name and address",true),Pq("member_no","Membership number (optional)",false),Pq("date","Cancellation date (check notice period)",true),Pq("reason","Reason (optional)",false),Pq("details","Your full name",true)]},
    gb_cancel_insurance:{ic:"🛡️",n:"Insurance policy cancellation",cat:"gb_cancel",tier:"einfach",q:[Pq("insurer","Insurer: name and address",true),Pq("policy_no","Policy number",true),Pq("type","Type of insurance",true,["Car","Home","Health","Life","Travel","Other"]),Pq("date","Cancellation / renewal date",true),Pq("details","Your full name and address",true)]},
    gb_energy_switch:{ic:"⚡",n:"Energy supplier switch / final bill dispute",cat:"gb_cancel",tier:"standard",q:[Pq("supplier","Supplier: name and address",true),Pq("account_no","Account number",true),Pq("address","Supply address",true),Pq("issue","Issue",true,["Switching away","Final bill is wrong","Both"]),Pq("details_issue","Details (meter readings, amounts £)",true),Pq("details","Your full name",true)]},
    gb_cancel_phone:{ic:"📱",n:"Mobile / broadband contract cancellation",cat:"gb_cancel",tier:"einfach",q:[Pq("provider","Provider: name",true),Pq("account_no","Account / phone number",true),Pq("min_term","Are you within a minimum term?",false,["No","Yes","Not sure"]),Pq("date","Cancellation date (optional)",false),Pq("details","Your full name and address",true)]},
    gb_close_account:{ic:"🏦",n:"Bank account closure",cat:"gb_cancel",tier:"einfach",q:[Pq("bank","Bank: name and branch",true),Pq("account","Sort code and account number",true),Pq("transfer","Account for remaining balance (optional)",false),Pq("details","Your full name and address",true)]},

    /* Housing & Tenancy */
    gb_notice_to_quit:{ic:"🏠",n:"Tenant's notice to quit (AST)",cat:"gb_housing",tier:"einfach",q:[Pq("landlord","Landlord/agent: name and address",true),Pq("address","Property address",true),Pq("move_out","Move-out date (at least 1 month's notice, ending on a rent day)",true),Pq("details","Your full name",true)]},
    gb_deposit_return:{ic:"💷",n:"Deposit return request (TDP scheme)",cat:"gb_housing",tier:"standard",q:[Pq("landlord","Landlord/agent: name and address",true),Pq("address","Property address",true),Pq("keys_date","Date keys were returned",true),Pq("amount","Deposit amount (£)",true),Pq("scheme","Deposit protection scheme (if known)",false,["DPS","MyDeposits","TDS","Not sure"]),Pq("bank","Bank details for the refund (optional)",false),Pq("details","Your full name",true)]},
    gb_repair_request:{ic:"🔧",n:"Repair request to landlord (LTA 1985 s.11)",cat:"gb_housing",tier:"standard",q:[Pq("landlord","Landlord/agent: name and address",true),Pq("address","Property address",true),Pq("problem","Description of the disrepair (heating, damp, leaks…)",true),Pq("urgent","Is it urgent?",false,["Yes","No"]),Pq("access","Access times you can offer (optional)",false),Pq("details","Your full name",true)]},
    gb_rent_increase:{ic:"📈",n:"Challenge to rent increase (s.13 notice)",cat:"gb_housing",tier:"standard",q:[Pq("landlord","Landlord: name and address",true),Pq("address","Property address",true),Pq("rent_current","Current rent (£)",true),Pq("rent_new","Proposed rent (£)",true),Pq("grounds","Your grounds (comparable local rents, condition…)",true),Pq("details","Your full name",true)]},
    gb_landlord_certs:{ic:"📋",n:"Request for gas safety / EPC / EICR certificates",cat:"gb_housing",tier:"einfach",q:[Pq("landlord","Landlord/agent: name and address",true),Pq("address","Property address",true),Pq("docs","Documents requested",true,["Gas Safety Record (CP12)","EPC","EICR","How to Rent guide","All of the above"]),Pq("details","Your full name",true)]},
    gb_noise_complaint:{ic:"🔊",n:"Noise complaint (neighbour / council)",cat:"gb_housing",tier:"standard",q:[Pq("recipient","Recipient (neighbour, landlord or council environmental health)",true),Pq("problem","Description of the nuisance",true),Pq("times","Frequency and times",false),Pq("request","Your request",true),Pq("details","Your name and address",true)]},
    gb_tenancy_agreement:{ic:"📄",n:"Assured shorthold tenancy agreement (AST)",cat:"gb_housing",tier:"komplex",q:[Pq("role","You are the",true,["Landlord","Tenant"]),Pq("other_party","Other party: full name",true),Pq("address","Property address",true),Pq("start","Start date and fixed term",true),Pq("rent","Monthly rent (£)",true),Pq("deposit","Deposit (£, max 5 weeks' rent, TDP protected)",false),Pq("bills","Bills included (optional)",false)]},

    /* Work & Employment */
    gb_resignation:{ic:"👋",n:"Resignation letter",cat:"gb_work",tier:"einfach",q:[Pq("employer","Employer: name and address",true),Pq("job","Your job title",true),Pq("last_day","Last working day (check contractual notice period)",true),Pq("details","Your full name",true)]},
    gb_grievance:{ic:"📨",n:"Formal grievance letter",cat:"gb_work",tier:"komplex",q:[Pq("recipient","Recipient (manager/HR)",true),Pq("job","Your job title",true),Pq("facts","The facts (dates, people, events)",true),Pq("steps","Steps already taken (optional)",false),Pq("outcome","Outcome you are seeking",true),Pq("details","Your full name",true)]},
    gb_flexible_working:{ic:"🏡",n:"Statutory flexible working request",cat:"gb_work",tier:"standard",q:[Pq("employer","Employer/manager",true),Pq("job","Your job title and start date",true),Pq("change","Change requested (hours, days, remote working)",true),Pq("start_date","Proposed start date of the change",true),Pq("impact","Impact on the business and how to manage it (optional)",false)]},
    gb_unpaid_wages:{ic:"💷",n:"Unpaid wages demand letter",cat:"gb_work",tier:"standard",q:[Pq("employer","Employer: name and address",true),Pq("job","Your job title and employment dates",true),Pq("owed","Amounts owed (wages, overtime, notice pay)",true),Pq("total","Total estimate (£)",false),Pq("details","Your full name",true)]},
    gb_holiday_pay:{ic:"🏖️",n:"Holiday pay claim",cat:"gb_work",tier:"standard",q:[Pq("employer","Employer: name and address",true),Pq("job","Your job title and employment dates",true),Pq("leave","Accrued but untaken leave (days)",true),Pq("amount","Amount claimed (£, optional)",false),Pq("details","Your full name",true)]},
    gb_acas_ec:{ic:"⚖️",n:"ACAS early conciliation summary",cat:"gb_work",tier:"komplex",q:[Pq("employer","Employer (respondent): name and address",true),Pq("job","Your job title and employment dates",true),Pq("dispute","Nature of the dispute",true,["Unfair dismissal","Unpaid wages","Discrimination","Redundancy","Other"]),Pq("facts","Summary of the facts",true),Pq("outcome","Outcome sought (amount £, reinstatement…)",true),Pq("details","Your full name and address",true)]},
    gb_reference_request:{ic:"📊",n:"Employment reference request",cat:"gb_work",tier:"einfach",q:[Pq("employer","Former employer/HR: name and address",true),Pq("job","Your job title and employment dates",true),Pq("send_to","Who to send the reference to (optional)",false),Pq("details","Your full name",true)]},

    /* Consumer & Disputes */
    gb_faulty_goods:{ic:"🔧",n:"Faulty goods claim (Consumer Rights Act 2015)",cat:"gb_consumer",tier:"standard",q:[Pq("retailer","Retailer: name and address",true),Pq("product","Product (make/model)",true),Pq("purchase","Purchase date and price (£)",true),Pq("fault","Fault description",true),Pq("remedy","Remedy sought",true,["Full refund (within 30 days)","Repair","Replacement","Price reduction"])]},
    gb_cooling_off:{ic:"🔄",n:"14-day cooling-off cancellation (CCRs 2013)",cat:"gb_consumer",tier:"einfach",q:[Pq("seller","Seller: name and address",true),Pq("order_no","Order number",true),Pq("date","Date of order/delivery",true),Pq("items","Items being returned",true),Pq("refund","Refund details (optional)",false)]},
    gb_letter_before_action:{ic:"📨",n:"Letter before action (small claims)",cat:"gb_consumer",tier:"komplex",q:[Pq("opponent","Opponent: name and address",true),Pq("facts","What happened (dates, agreement, breach)",true),Pq("amount","Amount claimed (£)",true),Pq("deadline","Deadline to respond (usually 14 days)",true),Pq("details","Your full name and address",true)]},
    gb_flight_delay:{ic:"✈️",n:"Flight delay compensation (UK261)",cat:"gb_consumer",tier:"standard",q:[Pq("airline","Airline",true),Pq("flight","Flight number and date",true),Pq("route","Route (from - to)",true),Pq("problem","Problem",true,["Delay over 3 hours","Cancellation","Denied boarding","Baggage"]),Pq("claim","Compensation claimed (£220/£350/£520 by distance)",true),Pq("details","Your full name",true)]},
    gb_section75:{ic:"💳",n:"Chargeback / Section 75 claim",cat:"gb_consumer",tier:"standard",q:[Pq("provider","Card provider/bank",true),Pq("transaction","Merchant and transaction (date, amount £)",true),Pq("problem","What went wrong (goods not received, faulty, firm insolvent…)",true),Pq("method","Paid by",true,["Credit card (Section 75, £100-£30,000)","Debit card (chargeback)"]),Pq("details","Your name and card (last 4 digits)",true)]},
    gb_ombudsman:{ic:"📢",n:"Ombudsman complaint",cat:"gb_consumer",tier:"standard",q:[Pq("ombudsman","Ombudsman",true,["Financial Ombudsman Service","Energy Ombudsman","Communications Ombudsman","Housing Ombudsman","Other"]),Pq("company","Company complained about",true),Pq("summary","Summary and date of the company's final response",true),Pq("outcome","Outcome sought",true),Pq("details","Your full name and address",true)]},
    gb_sar:{ic:"🔍",n:"Subject access request (UK GDPR)",cat:"gb_consumer",tier:"standard",q:[Pq("organisation","Organisation: name and address",true),Pq("relationship","Your relationship (customer, employee…)",true),Pq("data","Data requested (period, systems, records)",true),Pq("details","Your full name and details to identify you",true)]},

    /* Contracts & Agreements */
    gb_loan_agreement:{ic:"🤝",n:"Loan agreement between individuals",cat:"gb_contracts",tier:"standard",q:[Pq("lender","Lender: name and address",true),Pq("borrower","Borrower: name and address",true),Pq("amount","Amount lent (£)",true),Pq("repayment","Repayment plan",true),Pq("interest","Interest (%, optional)",false)]},
    gb_iou:{ic:"🧾",n:"Promissory note / IOU",cat:"gb_contracts",tier:"standard",q:[Pq("creditor","Creditor: name and address",true),Pq("debtor","Debtor: name and address",true),Pq("amount","Amount (£, in words and figures)",true),Pq("repayment","Repayment date/terms",true),Pq("interest","With interest?",false,["No interest","With interest"])]},
    gb_bill_of_sale:{ic:"🛒",n:"Bill of sale (private sale)",cat:"gb_contracts",tier:"einfach",q:[Pq("role","You are the",true,["Seller","Buyer"]),Pq("other_party","Other party: name and address",true),Pq("item","Item sold (description, serial number)",true),Pq("price","Price (£)",true),Pq("delivery","Delivery date / sold-as-seen note (optional)",false)]},
    gb_nda:{ic:"🔒",n:"Non-disclosure agreement (NDA)",cat:"gb_contracts",tier:"standard",q:[Pq("other_party","Other party: name and address",true),Pq("purpose","Purpose of the disclosure/collaboration",true),Pq("scope","Scope of the confidential information",true),Pq("duration","Duration (optional)",false)]},
    gb_service_agreement:{ic:"🧰",n:"Service agreement",cat:"gb_contracts",tier:"komplex",q:[Pq("role","You are the",true,["Provider","Client"]),Pq("other_party","Other party: name and address",true),Pq("service","Description of the services",true),Pq("price","Price and payment terms (£)",true),Pq("timeline","Timeline/duration (optional)",false),Pq("clauses","Special clauses (optional)",false)]},
    gb_lodger_agreement:{ic:"🛏️",n:"Lodger agreement (licence)",cat:"gb_contracts",tier:"standard",q:[Pq("role","You are the",true,["Householder","Lodger"]),Pq("other_party","Other party: full name",true),Pq("address","Property address and room",true),Pq("start","Start date",true),Pq("rent","Rent (£ per week/month) and what is included",true),Pq("notice","Notice period (optional)",false)]},

    /* Government & Admin */
    gb_council_tax:{ic:"🏘️",n:"Council tax dispute / discount request",cat:"gb_admin",tier:"standard",q:[Pq("council","Council: name",true),Pq("reference","Account/reference number",true),Pq("issue","Issue",true,["Wrong band/amount","Single person discount","Exemption","Liability dispute"]),Pq("facts","The facts",true),Pq("details","Your name and address",true)]},
    gb_hmrc_appeal:{ic:"🧾",n:"HMRC penalty appeal",cat:"gb_admin",tier:"komplex",q:[Pq("office","HMRC office/department",true),Pq("reference","Reference (UTR / NI number / penalty reference)",true),Pq("subject","What you are appealing (late filing penalty, assessment…)",true),Pq("grounds","Grounds / reasonable excuse",true),Pq("details","Your full name and address",true)]},
    gb_dwp_mr:{ic:"📋",n:"DWP mandatory reconsideration",cat:"gb_admin",tier:"standard",q:[Pq("benefit","Benefit",true,["Universal Credit","PIP","ESA","JSA","Other"]),Pq("decision","Decision date and reference",true),Pq("grounds","Why the decision is wrong",true),Pq("evidence","Evidence you can provide (optional)",false),Pq("details","Your name, address and NI number",true)]},
    gb_dvla:{ic:"🚗",n:"Letter to the DVLA",cat:"gb_admin",tier:"standard",q:[Pq("subject","Subject",true,["Medical condition notification","V5C logbook issue","Licence query","Penalty/fine dispute","Other"]),Pq("vehicle","Vehicle registration / driver number (optional)",false),Pq("facts","The facts and your request",true),Pq("details","Your full name and address",true)]},
    gb_pcn_appeal:{ic:"🅿️",n:"Parking ticket (PCN) appeal",cat:"gb_admin",tier:"standard",q:[Pq("issuer","Issuer (council or private operator)",true),Pq("pcn_no","PCN number",true),Pq("when_where","Date and location",true),Pq("vehicle","Vehicle registration",true),Pq("grounds","Grounds of appeal",true),Pq("details","Your full name and address",true)]},
    gb_mp_letter:{ic:"🏛️",n:"Letter to your MP",cat:"gb_admin",tier:"standard",q:[Pq("mp","MP: name and constituency",true),Pq("issue","The issue",true),Pq("history","What has happened so far (optional)",false),Pq("request","What you want the MP to do",true),Pq("details","Your name and full address (constituency proof)",true)]}
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
      return (typeof DOCS!=="undefined" && DOCS.gb_letter_before_action)?true:false;
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
if($src!==$start){ file_put_contents($file,$src); echo "Birlesik Krallik modulu eklendi (CH_GB_MODULE) - ~39 belge, 6 kategori, Ingilizce (Ingiltere & Galler hukuku).\n"; }
else echo "degisiklik yok\n";
echo "\nNOT: Belge dili/hukuku icin apply-lawsys-all.php de calistirilmali (lawSystems['GB'] + doc-lang).\n";
echo "SONRA: opcache-reset (pull-updates otomatik yapar). SIL: rm apply-uk.php\n";
echo "Test: Ayarlar -> United Kingdom sec -> kategoriler Ingilizce -> 'Letter before action (small claims)' uret.\n";
