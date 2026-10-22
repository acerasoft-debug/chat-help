<?php
/** ChatHelp — apply-us-expand-more (CH_US_EXPAND_MORE) — US katalog genisletme
 *  Workflow ile uretildi: +16 belge, 4 kategori. Deferred injection.
 *  KULLANIM: pull-updates.php?files=apply-us-expand-more.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-us-expand-more BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_US_EXPAND_MORE')!==false) exit("Zaten ekli (CH_US_EXPAND_MORE).\n");
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if (strpos($src,$anchor)===false) exit("HATA: anchor (getDocPrice) yok.\n");
$js = <<<'CHJS'

/* CH_US_EXPAND_MORE — US katalog genisletme (workflow, deferred) */
try{(function(){
  function Pe(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var NEWCATS={
    "us_estate":{n:"Estate & End-of-Life",ic:"📜"},
    "us_debt":{n:"Debt & Credit",ic:"💳"},
    "us_court":{n:"Small Claims & Court",ic:"⚖️"},
    "us_neighbors":{n:"Neighbors & Community",ic:"🏡"}
  };
  var E={
    "usx_last_will":{ic:"📜",n:"Last will and testament (draft)",cat:"us_estate",tier:"komplex",q:[Pe("testator","Your full name and address (the testator)",true),Pe("executor","Executor (person who will carry out the will): name and address",true),Pe("beneficiaries","Beneficiaries and what each should receive",true),Pe("guardian","Guardian for any minor children (optional)",false),Pe("state","State (US state, e.g. California)",true),Pe("note","NOTE: a will generally must be signed before two witnesses (and notarized in some states) to be valid",false)]},
    "usx_living_will":{ic:"🕊️",n:"Advance directive / living will",cat:"us_estate",tier:"komplex",q:[Pe("name","Your full name and address",true),Pe("treatment","Life-sustaining treatment preference if you are terminally ill or permanently unconscious",true,["Prolong life by all available means","Withhold or withdraw life support","Comfort care only","Let my healthcare agent decide"]),Pe("specifics","Specific wishes (resuscitation, feeding tube, ventilator, pain relief)",true),Pe("agent","Healthcare agent who may enforce these wishes: name and phone (optional)",false),Pe("state","State (US state, e.g. California)",true),Pe("note","NOTE: most states require this document to be witnessed or notarized to take effect",false)]},
    "usx_healthcare_poa":{ic:"🩺",n:"Healthcare power of attorney (medical proxy)",cat:"us_estate",tier:"standard",q:[Pe("principal","Your full name and address (the principal)",true),Pe("agent","Healthcare agent: name, relationship and phone",true),Pe("alternate","Alternate agent if the first is unavailable (optional)",false),Pe("powers","Decisions the agent may make, and any limits you want to set",true),Pe("state","State (US state, e.g. California)",true)]},
    "usx_hipaa_authorization":{ic:"🔓",n:"HIPAA medical records release authorization",cat:"us_estate",tier:"einfach",q:[Pe("patient","Patient: full name and date of birth",true),Pe("provider","Provider or facility that holds the records",true),Pe("recipient","Person or entity allowed to receive the records",true),Pe("scope","Records and date range to release",true),Pe("purpose","Purpose of the release (optional)",false),Pe("expiration","Expiration date of this authorization (optional)",false)]},
    "usx_debt_settlement_offer":{ic:"🤝",n:"Debt settlement offer letter",cat:"us_debt",tier:"standard",q:[Pe("creditor","Creditor or collection agency: name and address",true),Pe("account_no","Account / reference number",true),Pe("balance","Total balance claimed ($)",true),Pe("offer","Lump-sum settlement amount you are offering ($)",true),Pe("condition","Conditions (delete from credit report, written payoff confirmation - optional)",false),Pe("name","Your full name and address",true)]},
    "usx_hardship_letter":{ic:"📉",n:"Financial hardship letter",cat:"us_debt",tier:"standard",q:[Pe("lender","Lender or servicer: name and address",true),Pe("account_no","Account / loan number",true),Pe("cause","Cause of the hardship",true,["Job loss","Reduced income","Medical bills / illness","Divorce or separation","Death in the family","Other"]),Pe("relief","Relief you are requesting",true,["Forbearance (pause payments)","Lower monthly payment","Loan modification","Payment deferral","Interest reduction"]),Pe("explanation","Brief explanation of your situation and what you can afford",true),Pe("name","Your full name and address",true)]},
    "usx_goodwill_deletion":{ic:"✨",n:"Goodwill deletion request (late payment removal)",cat:"us_debt",tier:"einfach",q:[Pe("creditor","Creditor or lender: name and address",true),Pe("account_no","Account number",true),Pe("mark","Negative mark to remove (type and date, e.g. 30-day late in March)",true),Pe("reason","Reason / context (otherwise good history, one-time hardship)",true),Pe("name","Your full name and address",true)]},
    "usx_wage_garnishment_exemption":{ic:"🛡️",n:"Wage garnishment exemption claim",cat:"us_debt",tier:"komplex",q:[Pe("court_case","Court, case number and creditor's name",true),Pe("employer","Your employer (the garnishee)",true),Pe("grounds","Exemption you are claiming",true,["Head of household / family support","Income below the legal minimum","Protected benefits (Social Security, disability, veterans)","Already at the maximum allowed garnishment","Other"]),Pe("finances","Your income, dependents and essential monthly expenses (brief)",true),Pe("state","State (US state, e.g. California)",true),Pe("name","Your full name and address",true)]},
    "usx_small_claims_complaint":{ic:"⚖️",n:"Small claims complaint (preparation)",cat:"us_court",tier:"komplex",q:[Pe("defendant","Defendant: name and address (the person or business you are suing)",true),Pe("basis","What happened and why they owe you (dates, agreement, breach)",true),Pe("amount","Amount you are claiming ($)",true),Pe("court","Court / county where you will file",true),Pe("state","State (US state, e.g. California)",true),Pe("name","Your full name and address",true)]},
    "usx_answer_to_complaint":{ic:"📑",n:"Answer to a civil complaint",cat:"us_court",tier:"komplex",q:[Pe("court_case","Court, case number and plaintiff's name",true),Pe("deadline","Date you were served and your deadline to respond",true),Pe("response","Your response to the allegations (which you admit, deny, or lack knowledge of)",true),Pe("defenses","Defenses or counterclaims you want to raise (optional)",false),Pe("state","State (US state, e.g. California)",true),Pe("name","Your full name and address",true)]},
    "usx_continuance_request":{ic:"🗓️",n:"Request to postpone a hearing (continuance)",cat:"us_court",tier:"einfach",q:[Pe("court_case","Court and case number",true),Pe("hearing_date","Current hearing or trial date",true),Pe("reason","Reason you need to postpone",true),Pe("new_date","New date you are requesting (optional)",false),Pe("opposing","Have you told the other party?",false,["Yes, they agree","Yes, they object","No, not yet"]),Pe("name","Your full name",true)]},
    "usx_fee_waiver":{ic:"💸",n:"Court fee waiver request (in forma pauperis)",cat:"us_court",tier:"standard",q:[Pe("court_case","Court and case number (or write 'new case')",true),Pe("income","Your monthly income ($)",true),Pe("household","Household size / number of dependents",true),Pe("reason","Why you cannot afford the fees (expenses, situation)",true),Pe("state","State (US state, e.g. California)",true),Pe("name","Your full name and address",true)]},
    "usx_boundary_dispute":{ic:"📐",n:"Property boundary / encroachment letter",cat:"us_neighbors",tier:"standard",q:[Pe("neighbor","Neighbor: name and address",true),Pe("property","Your property address",true),Pe("encroachment","The encroachment (fence, structure, tree, driveway - description)",true),Pe("request","What you are asking for (remove it, order a survey, meet to discuss)",true),Pe("survey","Has a boundary survey been done?",false,["Yes","No","Not sure"]),Pe("state","State (US state, e.g. California)",true)]},
    "usx_shared_fence_cost":{ic:"🪵",n:"Shared fence cost-sharing demand",cat:"us_neighbors",tier:"standard",q:[Pe("neighbor","Adjoining neighbor: name and address",true),Pe("fence","Fence location (the shared property line)",true),Pe("work","Repair or replacement needed",true),Pe("cost","Total estimated cost and the share you request ($)",true),Pe("notice","Have you discussed it with the neighbor already?",false,["Yes","No"]),Pe("state","State (US state, e.g. California)",true)]},
    "usx_nuisance_complaint":{ic:"🔇",n:"Private nuisance complaint (noise/odor/light)",cat:"us_neighbors",tier:"standard",q:[Pe("neighbor","Neighbor: name and address",true),Pe("nuisance","The nuisance (noise, odor, smoke, light - description)",true),Pe("frequency","How often and at what times it happens",true),Pe("impact","How it affects the use of your home",true),Pe("request","What you are asking them to do",true),Pe("state","State (US state, e.g. California)",true)]},
    "usx_dog_complaint":{ic:"🐕",n:"Nuisance / dangerous dog complaint letter",cat:"us_neighbors",tier:"einfach",q:[Pe("owner","Dog owner: name and address",true),Pe("incidents","Incidents (dates and what happened)",true),Pe("harm","Any injuries or property damage (optional)",false),Pe("request","What you are asking for (leash, secure containment, report to animal control)",true),Pe("state","State (US state, e.g. California)",true),Pe("name","Your full name",true)]}
  };
  function inject(){
    try{
      if(typeof DOCS==="undefined"||!DOCS) return false;
      if(typeof CATS==="undefined"||!CATS) return false;
      for(var k in E){ if(!DOCS[k]) DOCS[k]=E[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=E[k].tier||"standard"; }
      for(var nc in NEWCATS){ if(!CATS[nc]) CATS[nc]={n:NEWCATS[nc].n,ic:NEWCATS[nc].ic,docs:[],country:"US"}; if(!CATS[nc].docs) CATS[nc].docs=[]; }
      for(var k2 in E){ var c2=E[k2].cat; if(CATS[c2]&&CATS[c2].docs&&CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); }
      if(typeof CAT_LABELS!=="undefined"){ for(var cl in NEWCATS) CAT_LABELS[cl]=NEWCATS[cl].n; }
      if(typeof CAT_DOCS!=="undefined"){
        for(var cd in NEWCATS){ if(!CAT_DOCS[cd]) CAT_DOCS[cd]=[]; }
        for(var k3 in E){ var c3=E[k3].cat; if(CAT_DOCS[c3]){ var ex=false; for(var i=0;i<CAT_DOCS[c3].length;i++){ if(CAT_DOCS[c3][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[c3].push({k:k3,ic:E[k3].ic,t:E[k3].n,tier:E[k3].tier||"standard"}); } }
      }
      return !!(DOCS.usx_last_will);
    }catch(e){ return false; }
  }
  var done=false, ticks=0;
  function run(){ if(!done){ if(inject()) done=true; } if(ticks++<40) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}

CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
$tmp = tempnam(sys_get_temp_dir(),'us').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-usmore-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "  ✓ US: +16 belge, 4 kategori eklendi\n\n✓ CH_US_EXPAND_MORE uygulandi.\n";
