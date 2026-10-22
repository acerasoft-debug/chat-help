<?php
/** ChatHelp — apply-gb-expand-more (CH_GB_EXPAND_MORE) — GB katalog genisletme
 *  Workflow ile uretildi: +16 belge, 4 kategori. Deferred injection.
 *  KULLANIM: pull-updates.php?files=apply-gb-expand-more.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-gb-expand-more BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_GB_EXPAND_MORE')!==false) exit("Zaten ekli (CH_GB_EXPAND_MORE).\n");
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if (strpos($src,$anchor)===false) exit("HATA: anchor (getDocPrice) yok.\n");
$js = <<<'CHJS'

/* CH_GB_EXPAND_MORE — GB katalog genisletme (workflow, deferred) */
try{(function(){
  function Pe(k,l,r,o){var x={k:k,l:l,r:!!r};if(o)x.opts=o;return x;}
  var NEWCATS={
    "gb_debt":{n:"Debt & Credit",ic:"💷"},
    "gb_probate":{n:"Wills, Probate & Estates",ic:"📜"},
    "gb_court":{n:"Courts & Tribunals",ic:"⚖️"},
    "gb_health":{n:"Health & Social Care",ic:"🏥"}
  };
  var E={
    "gbx_debt_prove":{ic:"📑",n:"Request to prove the debt (Consumer Credit Act 1974, s.77-79)",cat:"gb_debt",tier:"standard",q:[Pe("creditor","Creditor / debt collector: name and address",true),Pe("account_ref","Account or reference number",true),Pe("debt_type","Type of credit agreement",false,["Credit card","Personal loan","Overdraft","Catalogue / store card","Hire purchase","Other"]),Pe("amount","Amount they claim you owe (£)",false),Pe("details","Your full name and address",true)]},
    "gbx_debt_statute_barred":{ic:"⏳",n:"Statute-barred debt letter (Limitation Act 1980)",cat:"gb_debt",tier:"standard",q:[Pe("creditor","Creditor / debt collector: name and address",true),Pe("account_ref","Account or reference number",true),Pe("last_activity","Date of your last payment or last written acknowledgement of the debt",true),Pe("amount","Amount claimed (£)",false),Pe("court_action","Have they threatened or started court action?",false,["No","Threatened only","County Court claim received"]),Pe("details","Your full name and address",true)]},
    "gbx_debt_repayment_offer":{ic:"🤝",n:"Debt repayment offer (pro-rata / affordable payment)",cat:"gb_debt",tier:"standard",q:[Pe("creditor","Creditor / debt collector: name and address",true),Pe("account_ref","Account or reference number",true),Pe("total_owed","Total amount owed (£)",true),Pe("offer","Monthly amount you can realistically afford (£)",true),Pe("budget","Brief summary of your income and essential outgoings",false),Pe("other_debts","Are you making pro-rata offers to other creditors too?",false,["No","Yes"])]},
    "gbx_debt_harassment":{ic:"🚫",n:"Stop debt collection harassment letter",cat:"gb_debt",tier:"einfach",q:[Pe("collector","Debt collector / creditor: name and address",true),Pe("account_ref","Account or reference number",false),Pe("conduct","What they are doing (frequency of calls, doorstep visits, letters, contacting your workplace)",true),Pe("request","Your request (e.g. contact in writing only, stop calling at work)",true),Pe("details","Your full name and address",true)]},
    "gbx_simple_will":{ic:"📜",n:"Simple will (single person)",cat:"gb_probate",tier:"komplex",q:[Pe("testator","Your full name and address",true),Pe("executors","Executor(s): full name(s) and address(es)",true),Pe("residuary","Who inherits your estate (main beneficiaries and their shares)",true),Pe("gifts","Specific gifts of money or items to named people (optional)",false),Pe("guardians","Guardians for any children under 18 (optional)",false),Pe("funeral","Funeral wishes (optional)",false)]},
    "gbx_lpa_financial":{ic:"🧠",n:"Lasting power of attorney instructions — property & financial affairs",cat:"gb_probate",tier:"komplex",q:[Pe("donor","Donor (you): full name, address and date of birth",true),Pe("attorneys","Attorney(s): full name(s) and address(es)",true),Pe("how_act","How the attorneys should make decisions",true,["Jointly (all together)","Jointly and severally (together or independently)","Jointly for some decisions, jointly and severally for others"]),Pe("when_use","When the power can be used",true,["As soon as it is registered","Only when I lose mental capacity"]),Pe("replacement","Replacement attorney(s) (optional)",false),Pe("preferences","Preferences and instructions for your attorneys (optional)",false)]},
    "gbx_executor_renunciation":{ic:"✍️",n:"Executor renunciation letter",cat:"gb_probate",tier:"standard",q:[Pe("deceased","The deceased: full name, last address and date of death",true),Pe("executor","Your full name and address (named executor)",true),Pe("will_date","Date of the will",false),Pe("intermeddled","Have you already dealt with any of the estate (paid bills, collected assets)?",true,["No","Yes"]),Pe("reason","Reason for renouncing (optional)",false)]},
    "gbx_estate_account_request":{ic:"📊",n:"Request for estate accounts (beneficiary to executor)",cat:"gb_probate",tier:"standard",q:[Pe("executor","Executor / administrator: name and address",true),Pe("deceased","The deceased: full name and date of death",true),Pe("interest","Your interest in the estate (beneficiary under the will / next of kin under intestacy)",true),Pe("request","What you are asking for (estate accounts, progress update, expected timescale)",true),Pe("details","Your full name and address",true)]},
    "gbx_particulars_of_claim":{ic:"📝",n:"Particulars of claim (money claim)",cat:"gb_court",tier:"komplex",q:[Pe("defendant","Defendant: full name and address",true),Pe("claim_basis","Basis of the claim",true,["Unpaid invoice / debt","Breach of contract","Goods or services not provided","Faulty goods or services","Damage to property","Other"]),Pe("facts","What happened (dates, the agreement, the breach) — a full account",true),Pe("amount","Amount claimed (£)",true),Pe("interest","Are you claiming interest?",false,["No","Yes — 8% statutory (County Courts Act 1984 s.69)","Yes — contractual rate"]),Pe("claimant","Your full name and address",true)]},
    "gbx_defence_money_claim":{ic:"🛡️",n:"Defence to a money claim (small claims)",cat:"gb_court",tier:"komplex",q:[Pe("claim_ref","Claim number and the court",true),Pe("claimant","Claimant: name",true),Pe("position","Your response to the claim",true,["I dispute the whole claim","I admit part and dispute the rest","I admit I owe money but dispute the amount"]),Pe("grounds","Why you dispute it — respond to each point of the claim",true),Pe("counterclaim","Do you have a counterclaim? (details, or 'none')",false),Pe("details","Your full name and address",true)]},
    "gbx_set_aside_ccj":{ic:"🔄",n:"Application to set aside a County Court Judgment (CCJ)",cat:"gb_court",tier:"komplex",q:[Pe("claim_ref","Claim number, court and date of judgment",true),Pe("claimant","Claimant (who obtained the CCJ): name",true),Pe("reason","Why the judgment should be set aside",true,["I was never served with the claim","I have a real prospect of successfully defending it","There was a procedural error","The debt was already paid before judgment"]),Pe("defence","Summary of your defence / what actually happened",true),Pe("promptness","Explain any delay in applying (why you are applying now)",false),Pe("details","Your full name and address",true)]},
    "gbx_witness_statement":{ic:"🗣️",n:"Witness statement for a hearing",cat:"gb_court",tier:"standard",q:[Pe("case","Case name / number and the court or tribunal",true),Pe("role","Your role in the case",true,["Claimant","Defendant","Applicant","Respondent","Independent witness"]),Pe("facts","The facts you can speak to, set out in date order",true),Pe("documents","Documents you refer to (exhibits) — optional",false),Pe("details","Your full name, address and occupation",true)]},
    "gbx_nhs_complaint":{ic:"🏥",n:"NHS complaint (formal)",cat:"gb_health",tier:"standard",q:[Pe("body","Who you are complaining about (GP practice, hospital trust, ambulance service, etc.)",true),Pe("when","Date(s) of the events",true),Pe("what","What went wrong (treatment, communication, delays, standard of care)",true),Pe("impact","How it affected you or your family",false),Pe("outcome","What you want (apology, explanation, changes, a review)",true),Pe("details","Your full name, address and NHS number (if known)",true)]},
    "gbx_medical_records":{ic:"📁",n:"Access to medical / health records request",cat:"gb_health",tier:"standard",q:[Pe("provider","GP practice / hospital / clinic: name and address",true),Pe("whose","Whose records are being requested",true,["My own (UK GDPR subject access)","My child's (with parental responsibility)","A deceased relative's (Access to Health Records Act 1990)"]),Pe("records","Records requested (period, department, type of records)",true),Pe("purpose","Reason for the request (optional)",false),Pe("details","Your full name, date of birth and details to identify you",true)]},
    "gbx_care_needs_assessment":{ic:"🧑‍🦽",n:"Request a care needs assessment (Care Act 2014)",cat:"gb_health",tier:"standard",q:[Pe("council","Local council (adult social care): name",true),Pe("who","Who the assessment is for",true,["Myself","Someone I care for or represent"]),Pe("needs","The needs and difficulties (daily living, mobility, personal care, safety)",true),Pe("support_now","Support currently in place (optional)",false),Pe("urgency","Is the situation urgent?",false,["No","Yes"]),Pe("details","Name, address and date of birth of the person needing care",true)]},
    "gbx_carers_assessment":{ic:"🤲",n:"Request a carer's assessment (Care Act 2014)",cat:"gb_health",tier:"einfach",q:[Pe("council","Local council (adult social care): name",true),Pe("cared_for","Person you care for: name and their relationship to you",true),Pe("care_provided","Care you provide (tasks and approximate hours per week)",true),Pe("impact","Impact on your own health, work and wellbeing",false),Pe("details","Your full name and address",true)]}
  };
  function inject(){
    try{
      if(typeof DOCS==="undefined"||!DOCS) return false;
      if(typeof CATS==="undefined"||!CATS) return false;
      for(var k in E){ if(!DOCS[k]) DOCS[k]=E[k]; if(typeof DOC_TIER!=="undefined") DOC_TIER[k]=E[k].tier||"standard"; }
      for(var nc in NEWCATS){ if(!CATS[nc]) CATS[nc]={n:NEWCATS[nc].n,ic:NEWCATS[nc].ic,docs:[],country:"GB"}; if(!CATS[nc].docs) CATS[nc].docs=[]; }
      for(var k2 in E){ var c2=E[k2].cat; if(CATS[c2]&&CATS[c2].docs&&CATS[c2].docs.indexOf(k2)<0) CATS[c2].docs.push(k2); }
      if(typeof CAT_LABELS!=="undefined"){ for(var cl in NEWCATS) CAT_LABELS[cl]=NEWCATS[cl].n; }
      if(typeof CAT_DOCS!=="undefined"){
        for(var cd in NEWCATS){ if(!CAT_DOCS[cd]) CAT_DOCS[cd]=[]; }
        for(var k3 in E){ var c3=E[k3].cat; if(CAT_DOCS[c3]){ var ex=false; for(var i=0;i<CAT_DOCS[c3].length;i++){ if(CAT_DOCS[c3][i].k===k3){ex=true;break;} } if(!ex) CAT_DOCS[c3].push({k:k3,ic:E[k3].ic,t:E[k3].n,tier:E[k3].tier||"standard"}); } }
      }
      return !!(DOCS.gbx_debt_prove);
    }catch(e){ return false; }
  }
  var done=false, ticks=0;
  function run(){ if(!done){ if(inject()) done=true; } if(ticks++<40) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}

CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
$tmp = tempnam(sys_get_temp_dir(),'gb').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-gbmore-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "  ✓ GB: +16 belge, 4 kategori eklendi\n\n✓ CH_GB_EXPAND_MORE uygulandi.\n";
