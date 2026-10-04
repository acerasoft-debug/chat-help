<?php
/**
 * ChatHelp — apply-visa-studio (CH_VISA_STUDIO)
 *  🛂 VIZE FORM STUDYOSU — Vertrag Studio motifi: sayfa sayfa doldur,
 *  canli A4 onizleme, resmi form duzeninde PDF.
 *   • SCHENGEN BASVURU FORMU: AB harmonize form duzeni (Tuzuk 810/2009 eki) —
 *     numarali resmi alan yerlesimi (1-33 + yer/tarih/imza), EN+TR etiketli.
 *   • Niyet dilekcesi (cover letter, EN) · Sponsor beyani · Seyahat plani ·
 *     Davet mektubu — konsolosluk dosyasi belgeleri.
 *   • Profilden otomatik on-dolum (ad/soyad/adres/tel/e-posta).
 *   • Kayit localStorage'da kalir; PDF chDlGate arkasinda (giris+plan sart).
 *   • TR vize paneline giris karti enjekte edilir (Turkiye'de gorunur).
 *  NOT: Verpflichtungserklarung gibi MAKAMIN bastigi belgeler taklit edilmez.
 * KULLANIM: pull-updates.php?files=apply-visa-studio.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-visa-studio BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_VISA_STUDIO')!==false) exit("Zaten ekli (CH_VISA_STUDIO).\n");

$bundle = <<<'HTML'
<style id="ch-vsf-css">
/* CH_VISA_STUDIO */
#pnl-vsf{position:fixed;inset:0;z-index:540;background:var(--bg,#17171d);display:none;flex-direction:column;overflow:hidden}
#pnl-vsf.on{display:flex}
.vsf-topbar{display:flex;align-items:center;gap:10px;padding:12px 16px;border-bottom:1px solid rgba(212,168,74,.18);background:var(--s1,#1d1d26)}
.vsf-back{background:none;border:1px solid rgba(212,168,74,.3);color:#d4a84a;border-radius:9px;width:34px;height:34px;font-size:18px;cursor:pointer}
.vsf-ttl{font-size:14px;font-weight:700;color:#fff}
.vsf-scroll{flex:1;overflow-y:auto;padding:16px}
.vsf-wrap{max-width:1100px;margin:0 auto}
.vsf-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:12px;margin-top:12px}
.vsf-card{border:1px solid rgba(212,168,74,.22);border-radius:13px;padding:14px;cursor:pointer;background:rgba(212,168,74,.04);transition:border-color .15s}
.vsf-card:hover{border-color:#d4a84a}
.vsf-ic{font-size:22px}.vsf-n{font-size:13px;font-weight:700;color:#fff;margin-top:6px}.vsf-s{font-size:11px;color:var(--t3,#99a);margin-top:3px}
.vsf-ed{display:flex;gap:18px;align-items:flex-start;flex-wrap:wrap;margin-top:12px}
.vsf-form{flex:1;min-width:300px;max-width:480px}
.vsf-fl{font-size:11.5px;color:var(--t3,#99a);margin:9px 0 4px;font-weight:600}
.vsf-in,.vsf-sel,.vsf-ta{width:100%;background:rgba(255,255,255,.05);border:1px solid rgba(212,168,74,.22);border-radius:9px;color:#fff;padding:9px 11px;font-size:13px;font-family:inherit;box-sizing:border-box}
.vsf-ta{min-height:64px;resize:vertical}
.vsf-steps{display:flex;align-items:center;gap:5px;margin:10px 0}
.vsf-dot{width:24px;height:24px;border-radius:100px;border:1px solid rgba(212,168,74,.4);display:flex;align-items:center;justify-content:center;font-size:11px;color:var(--t3,#99a)}
.vsf-dot.on{background:#d4a84a;color:#111;font-weight:700}.vsf-dot.ok{border-color:#d4a84a;color:#d4a84a}
.vsf-dash{width:16px;height:1px;background:rgba(212,168,74,.3)}
.vsf-nav{display:flex;gap:10px;margin-top:16px}
.vsf-prevb,.vsf-nextb{border-radius:10px;padding:11px 16px;font-size:13px;cursor:pointer;font-family:inherit}
.vsf-prevb{background:none;border:1px solid rgba(212,168,74,.3);color:#d4a84a}
.vsf-nextb{background:linear-gradient(135deg,#d4a84a,#f0c860);border:none;color:#141414;font-weight:700;flex:1}
.vsf-note{font-size:10.5px;color:var(--t3,#99a);margin-top:10px}
.vsf-prev{flex:1;min-width:300px}
.vsf-prevbox{border:1px solid rgba(212,168,74,.2);border-radius:12px;overflow:auto;background:#26262e;padding:10px}
/* A4 resmi form */
.vfA4{width:794px;min-height:1123px;background:#fff;color:#111;font-family:Arial,Helvetica,sans-serif;padding:40px 46px;box-sizing:border-box;position:relative}
.vfA4 *{margin:0;padding:0;box-sizing:border-box}
.vfh{border-bottom:3px double #333;padding-bottom:10px;margin-bottom:12px}
.vfh-t{font-size:15px;font-weight:800;letter-spacing:1px}
.vfh-s{font-size:10px;color:#444;margin-top:2px}
.vf-photo{position:absolute;top:40px;right:46px;width:94px;height:120px;border:1.5px solid #333;display:flex;align-items:center;justify-content:center;font-size:8.5px;color:#888;text-align:center}
.vfrow{display:flex;border:1px solid #999;border-top:none}
.vfrow:first-of-type,.vfrow.top{border-top:1px solid #999}
.vfc{flex:1;padding:5px 8px;border-left:1px solid #999;min-height:34px}
.vfc:first-child{border-left:none}
.vfl{font-size:8px;color:#555;text-transform:uppercase;letter-spacing:.2px}
.vfl b{color:#333}
.vfv{font-size:11.5px;font-weight:700;margin-top:2px;min-height:13px;word-break:break-word}
.vfsec{font-size:9.5px;font-weight:800;background:#eee;border:1px solid #999;border-bottom:none;padding:4px 8px;margin-top:10px;letter-spacing:.5px}
.vfsig{display:flex;gap:24px;margin-top:26px}
.vfsig>div{flex:1;border-top:1px solid #333;padding-top:5px;font-size:9px;color:#444}
.vffoot{position:absolute;bottom:18px;left:46px;right:46px;font-size:8px;color:#999;display:flex;justify-content:space-between}
.vf-letter{font-family:Georgia,'Times New Roman',serif;font-size:12.5px;line-height:1.8;white-space:pre-wrap}
@media (max-width:800px){ .vsf-prev{min-width:100%} }
</style>
<script id="ch-vsf-js">
/* CH_VISA_STUDIO — vize form studyosu */
try{(function(){
  function esc(s){ return String(s==null?"":s).replace(/[&<>"]/g,function(c){return{"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];}); }
  function uil(){ try{ return localStorage.getItem("ch_uilang")||"tr"; }catch(e){ return "tr"; } }
  function prof(){ try{ if(window.P&&(P.f1||P.f7)) return P; return JSON.parse(localStorage.getItem("ch_prof_v3")||"{}")||{}; }catch(e){ return {}; } }
  function T(k,d){ return v(k)||d||""; }
  var CUR=null, STEP=0;
  function getD(k){ try{ return JSON.parse(localStorage.getItem("ch_vsf_"+k)||"{}")||{}; }catch(e){ return {}; } }
  function setD(k,d){ try{ localStorage.setItem("ch_vsf_"+k, JSON.stringify(d)); }catch(e){} }
  function v(key){ var d=getD(CUR); return d[key]||""; }
  function V2(x,ph){ return x?esc(x):'<span style="color:#bbb">'+(ph||"—")+'</span>'; }

  /* ── form tanimlari ── */
  function F(k,l,ty,opts){ return {k:k,l:l,ty:ty||"in",opts:opts||null}; }
  var VF={
    schengen:{ic:"🛂",n:"Schengen Vize Başvuru Formu",s:"Resmi harmonize form düzeni (810/2009) — doldur, yazdır, imzala",steps:[
      {t:"1/5 · Kimlik Bilgileri",f:[
        F("f1","1. Soyadı / Surname (Familienname)"),F("f2","2. Doğumdaki soyadı / Surname at birth"),
        F("f3","3. Adı / First name(s)"),F("f4","4. Doğum tarihi / Date of birth (GG.AA.YYYY)"),
        F("f5","5. Doğum yeri / Place of birth"),F("f6","6. Doğum ülkesi / Country of birth"),
        F("f7","7. Uyruk / Current nationality"),F("f8","8. Cinsiyet / Sex",null,["Erkek / Male","Kadın / Female"]),
        F("f9","9. Medeni hal / Civil status",null,["Bekâr / Single","Evli / Married","Ayrı / Separated","Boşanmış / Divorced","Dul / Widowed","Diğer / Other"]),
        F("f11","11. T.C. Kimlik No / National identity number")]},
      {t:"2/5 · Pasaport & İletişim",f:[
        F("f12","12. Seyahat belgesi türü / Type of travel document",null,["Umuma mahsus / Ordinary passport","Hizmet / Service passport","Hususi / Special passport","Diplomatik / Diplomatic passport","Diğer / Other"]),
        F("f13","13. Pasaport numarası / Number of travel document"),
        F("f14","14. Veriliş tarihi / Date of issue"),F("f15","15. Geçerlilik / Valid until"),
        F("f16","16. Veren makam / Issued by"),
        F("f19a","19. Ev adresi / Home address","ta"),F("f19b","E-posta / E-mail"),F("f19c","Telefon / Phone"),
        F("f20","20. Uyruk ülkesi dışında ikamet? / Residence in another country",null,["Hayır / No","Evet (izin no + geçerlilik yazın) / Yes"])]},
      {t:"3/5 · Meslek & Seyahat Amacı",f:[
        F("f21","21. Meslek / Current occupation"),
        F("f22","22. İşveren: ad, adres, telefon (öğrenci: okul) / Employer","ta"),
        F("f23","23. Seyahat amacı / Purpose of journey",null,["Turizm / Tourism","Ticari / Business","Aile-arkadaş ziyareti / Visiting family or friends","Kültürel / Cultural","Spor / Sports","Resmi ziyaret / Official visit","Sağlık / Medical reasons","Eğitim / Study","Havalimanı transit / Airport transit","Diğer / Other"]),
        F("f24","24. Amaç ek bilgi / Additional information (opsiyonel)","ta"),
        F("f25","25. Ana varış ülkesi / Member State of main destination"),
        F("f26","26. İlk giriş ülkesi / Member State of first entry"),
        F("f27","27. Giriş sayısı / Number of entries requested",null,["Tek giriş / Single entry","İki giriş / Two entries","Çok giriş / Multiple entries"]),
        F("f27a","Varış tarihi / Intended date of arrival"),F("f27b","Dönüş tarihi / Intended date of departure")]},
      {t:"4/5 · Davet & Masraflar",f:[
        F("f31","31. Davet eden kişi/otel: ad, adres, tel, e-posta / Inviting person or hotel(s)","ta"),
        F("f32","32. Davet eden şirket/kurum (ticari ise) / Inviting company","ta"),
        F("f33","33. Masrafları kim karşılıyor? / Cost of travelling covered by",null,["Başvuran kendisi / by the applicant himself/herself","Sponsor (davet eden) / by a sponsor (host)","Her ikisi / both"]),
        F("f33a","Geçim araçları / Means of support",null,["Nakit / Cash","Kredi kartı / Credit card","Ödenmiş konaklama / Prepaid accommodation","Ödenmiş ulaşım / Prepaid transport","Tümü sponsor tarafından / All expenses covered by sponsor"])]},
      {t:"5/5 · Son Bilgiler",f:[
        F("f28","28. Daha önce parmak izi verdiniz mi? / Fingerprints collected previously",null,["Hayır / No","Evet (tarih yazın) / Yes"]),
        F("f28a","Verildiyse tarih / If yes, date (opsiyonel)"),
        F("f36a","36. Yer / Place"),F("f36b","Tarih / Date")]}
    ]},
    cover:{ic:"✉️",n:"Vize Niyet Dilekçesi (Cover Letter)",s:"Konsolosluğa hitaben — İngilizce çıktı",steps:[
      {t:"1/2 · Başvuru Bilgileri",f:[
        F("c_name","Ad Soyad"),F("c_passport","Pasaport No"),
        F("c_consulate","Konsolosluk (ör. Consulate General of Germany, Istanbul)"),
        F("c_purpose","Seyahat amacı",null,["Tourism","Business","Family visit","Friend visit","Cultural event","Medical"]),
        F("c_dates","Seyahat tarihleri (ör. 10.09.2026 – 24.09.2026)"),F("c_dest","Gidilecek şehir(ler)")]},
      {t:"2/2 · Durum & Bağlar",f:[
        F("c_job","İşiniz/mesleğiniz ve işvereniniz"),
        F("c_ties","Türkiye'ye bağlarınız (iş, aile, mülk — dönüş güvencesi)","ta"),
        F("c_fund","Masrafları nasıl karşılıyorsunuz (maaş, birikim, sponsor)"),
        F("c_extra","Eklemek istedikleriniz (opsiyonel)","ta")]}
    ]},
    sponsor:{ic:"🤝",n:"Sponsor / Masraf Taahhüt Beyanı",s:"Kişisel beyan — resmî Verpflichtungserklärung değildir",steps:[
      {t:"1/1 · Beyan Bilgileri",f:[
        F("s_name","Sponsorun adı soyadı"),F("s_addr","Sponsorun adresi","ta"),
        F("s_rel","Başvurana yakınlığı (ör. babası, kardeşi)"),
        F("s_for","Başvuranın adı soyadı ve pasaport no"),
        F("s_scope","Neleri karşılıyor?",null,["Tüm seyahat ve konaklama masrafları","Konaklama","Seyahat + sağlık sigortası","Tüm masraflar + geçim"]),
        F("s_income","Gelir/dayanak (maaş, işyeri — belge eklenecek)"),
        F("s_dates","Kapsanan tarih aralığı")]}
    ]},
    itinerary:{ic:"🗺️",n:"Seyahat Planı (Itinerary)",s:"Gün gün plan — konsolosluk dosyası için",steps:[
      {t:"1/1 · Plan",f:[
        F("i_name","Ad Soyad"),F("i_dates","Seyahat tarihleri"),
        F("i_flight","Uçuş bilgileri (rezervasyon — gidiş/dönüş)","ta"),
        F("i_hotel","Konaklama (otel adı, adres, tarih aralığı)","ta"),
        F("i_plan","Gün gün plan (her satıra: tarih — şehir — aktivite)","ta")]}
    ]},
    invitation:{ic:"💌",n:"Davet Mektubu (Invitation Letter)",s:"Davet eden doldurur — İngilizce çıktı",steps:[
      {t:"1/1 · Davet Bilgileri",f:[
        F("v_host","Davet edenin adı, adresi, telefonu (yurt dışında)","ta"),
        F("v_status","Davet edenin statüsü (vatandaş/oturum — belge no)"),
        F("v_guest","Davet edilen: ad soyad, doğum tarihi, pasaport no"),
        F("v_rel","Yakınlık (ör. kardeşim, arkadaşım)"),
        F("v_dates","Ziyaret tarihleri"),
        F("v_accom","Konaklama daveti eden yanında mı?",null,["Evet, adresimde kalacak","Hayır, otelde kalacak"])]}
    ]}
  };

  /* ── A4 uretimi ── */
  function rowN(cells){ var h='<div class="vfrow">'; cells.forEach(function(c){ h+='<div class="vfc" style="flex:'+(c[2]||1)+'"><div class="vfl">'+c[0]+'</div><div class="vfv">'+c[1]+'</div></div>'; }); return h+'</div>'; }
  function sec(t){ return '<div class="vfsec">'+t+'</div>'; }
  function pageWrap(inner,foot){ return '<div class="vfA4">'+inner+'<div class="vffoot"><span>'+esc(foot||"ChatHelp")+'</span><span>chat-help.com</span></div></div>'; }

  function buildPages(key){
    var d=getD(key);
    function g(k,ph){ return V2(d[k],ph); }
    if(key==="schengen"){
      var p1=
        '<div class="vf-photo">FOTOĞRAF<br>PHOTO<br>35×45&nbsp;mm</div>'
        +'<div class="vfh" style="padding-right:110px"><div class="vfh-t">APPLICATION FOR SCHENGEN VISA</div><div class="vfh-s">Schengen Vizesi Başvuru Formu · Bu form ücretsizdir / This application form is free</div></div>'
        +sec("PERSONAL DATA / KİŞİSEL BİLGİLER")
        +rowN([["1. Surname (Family name) / Soyadı",g("f1"),2],["2. Surname at birth / Doğumdaki soyadı",g("f2"),2]])
        +rowN([["3. First name(s) / Adı",g("f3"),2],["4. Date of birth / Doğum tarihi",g("f4"),1]])
        +rowN([["5. Place of birth / Doğum yeri",g("f5")],["6. Country of birth / Doğum ülkesi",g("f6")],["7. Current nationality / Uyruk",g("f7")]])
        +rowN([["8. Sex / Cinsiyet",g("f8")],["9. Civil status / Medeni hal",g("f9")],["11. National identity number / T.C. Kimlik No",g("f11")]])
        +sec("TRAVEL DOCUMENT / SEYAHAT BELGESİ")
        +rowN([["12. Type of travel document / Belge türü",g("f12"),2],["13. Number / Numarası",g("f13"),1]])
        +rowN([["14. Date of issue / Veriliş",g("f14")],["15. Valid until / Geçerlilik",g("f15")],["16. Issued by / Veren makam",g("f16")]])
        +sec("CONTACT & RESIDENCE / İLETİŞİM & İKAMET")
        +rowN([["19. Applicant's home address and e-mail / Ev adresi ve e-posta",g("f19a")+(d.f19b?(" · "+esc(d.f19b)):""),3],["Telephone / Telefon",g("f19c"),1]])
        +rowN([["20. Residence in a country other than the country of current nationality / Uyruk ülkesi dışında ikamet",g("f20"),1]])
        +sec("OCCUPATION / MESLEK")
        +rowN([["21. Current occupation / Meslek",g("f21"),1],["22. Employer (address, phone); students: name of school / İşveren",g("f22"),2]]);
      var p2=
        '<div class="vfh"><div class="vfh-t">APPLICATION FOR SCHENGEN VISA — 2</div><div class="vfh-s">'+g("f1")+" "+g("f3")+'</div></div>'
        +sec("JOURNEY / SEYAHAT")
        +rowN([["23. Purpose(s) of the journey / Seyahat amacı",g("f23"),2],["24. Additional information / Ek bilgi",g("f24","—"),2]])
        +rowN([["25. Member State of main destination / Ana varış ülkesi",g("f25")],["26. Member State of first entry / İlk giriş ülkesi",g("f26")]])
        +rowN([["27. Number of entries requested / Giriş sayısı",g("f27")],["Intended date of arrival / Varış",g("f27a")],["Intended date of departure / Dönüş",g("f27b")]])
        +rowN([["28. Fingerprints collected previously for a Schengen visa / Önceden parmak izi",g("f28")+(d.f28a?(" · "+esc(d.f28a)):""),1]])
        +sec("INVITATION & COSTS / DAVET & MASRAFLAR")
        +rowN([["31. Name and address of inviting person(s) / hotel(s) / Davet eden kişi veya otel",g("f31"),1]])
        +rowN([["32. Name and address of inviting company/organisation / Davet eden kurum",g("f32","—"),1]])
        +rowN([["33. Cost of travelling and living covered / Masraflar",g("f33"),1],["Means of support / Geçim araçları",g("f33a"),1]])
        +sec("DECLARATION / BEYAN")
        +'<div style="border:1px solid #999;padding:8px;font-size:8.5px;color:#444;line-height:1.5">I am aware that the visa fee is not refunded if the visa is refused. I declare that to the best of my knowledge all particulars supplied by me are correct and complete. / Vize reddedilirse harcın iade edilmeyeceğini biliyorum. Verdiğim tüm bilgilerin doğru ve eksiksiz olduğunu beyan ederim.</div>'
        +rowN([["36. Place and date / Yer ve tarih",g("f36a")+(d.f36b?(", "+esc(d.f36b)):""),1]])
        +'<div class="vfsig"><div>37. Signature / İmza (for minors, signature of parental authority)</div></div>'
        +'<div style="margin-top:14px;font-size:8px;color:#777">Bu çıktı, AB harmonize Schengen başvuru formu düzenini izler (Tüzük (EC) 810/2009 eki). Konsolosluğunuz kendi güncel formunu isterse alanları birebir aktarabilirsiniz.</div>';
      return [pageWrap(p1,"Schengen Visa Application"),pageWrap(p2,"Schengen Visa Application")];
    }
    if(key==="cover"){
      var body="To: "+T2(d.c_consulate)+"\n\nSubject: Visa Application — "+T2(d.c_name)+" (Passport No: "+T2(d.c_passport)+")\n\nDear Sir or Madam,\n\nI am writing to apply for a Schengen visa for the purpose of "+T2(d.c_purpose).toLowerCase()+". I plan to travel on the following dates: "+T2(d.c_dates)+", visiting "+T2(d.c_dest)+".\n\nI am currently working as "+T2(d.c_job)+". "+(d.c_fund?("The costs of my journey will be covered as follows: "+d.c_fund+". "):"")+(d.c_ties?("I have strong ties to Turkey that ensure my return: "+d.c_ties+". "):"")+(d.c_extra?("\n\n"+d.c_extra):"")+"\n\nAll supporting documents are enclosed with this application. I would be grateful if you would consider my application favourably.\n\nYours faithfully,\n\n\n"+T2(d.c_name);
      return [pageWrap('<div class="vfh"><div class="vfh-t">COVER LETTER — VISA APPLICATION</div><div class="vfh-s">Vize Niyet Dilekçesi</div></div><div class="vf-letter">'+esc(body)+'</div>',"Cover Letter")];
    }
    if(key==="sponsor"){
      var body="DECLARATION OF SPONSORSHIP / SPONSORLUK BEYANI\n\nI, the undersigned "+T2(d.s_name)+", residing at "+T2(d.s_addr)+", hereby declare that I am the "+T2(d.s_rel)+" of "+T2(d.s_for)+".\n\nI undertake to cover the following costs for the above-mentioned applicant for the period "+T2(d.s_dates)+":\n"+T2(d.s_scope)+".\n\nMy financial means: "+T2(d.s_income)+" (supporting documents enclosed).\n\nBu beyan kişisel bir taahhüttür; resmî makam belgesi (ör. Verpflichtungserklärung) yerine geçmez — konsolosluk resmî belge isterse ilgili makamdan alınmalıdır.\n\nPlace / Date: ______________________\n\nSignature: ______________________\n"+T2(d.s_name);
      return [pageWrap('<div class="vfh"><div class="vfh-t">SPONSOR DECLARATION</div><div class="vfh-s">Masraf Taahhüt Beyanı</div></div><div class="vf-letter">'+esc(body)+'</div>',"Sponsor Declaration")];
    }
    if(key==="itinerary"){
      var body="TRAVEL ITINERARY / SEYAHAT PLANI\n\nTraveller: "+T2(d.i_name)+"\nTravel dates: "+T2(d.i_dates)+"\n\nFLIGHTS / UÇUŞLAR:\n"+T2(d.i_flight)+"\n\nACCOMMODATION / KONAKLAMA:\n"+T2(d.i_hotel)+"\n\nDAY-BY-DAY PLAN / GÜN GÜN PLAN:\n"+T2(d.i_plan);
      return [pageWrap('<div class="vfh"><div class="vfh-t">TRAVEL ITINERARY</div><div class="vfh-s">Seyahat Planı</div></div><div class="vf-letter">'+esc(body)+'</div>',"Itinerary")];
    }
    if(key==="invitation"){
      var body="LETTER OF INVITATION / DAVET MEKTUBU\n\nHost / Davet eden:\n"+T2(d.v_host)+"\nStatus / Statü: "+T2(d.v_status)+"\n\nTo whom it may concern,\n\nI hereby invite "+T2(d.v_guest)+", who is my "+T2(d.v_rel)+", to visit me during the following period: "+T2(d.v_dates)+".\n\nAccommodation / Konaklama: "+T2(d.v_accom)+".\n\nI will be pleased to welcome my guest and, where applicable, assist during the stay.\n\nPlace / Date: ______________________\n\nSignature: ______________________";
      return [pageWrap('<div class="vfh"><div class="vfh-t">LETTER OF INVITATION</div><div class="vfh-s">Davet Mektubu</div></div><div class="vf-letter">'+esc(body)+'</div>',"Invitation Letter")];
    }
    return [];
    function T2(x){ return x||"____________"; }
  }

  /* ── panel & sihirbaz ── */
  function panel(){
    var p=document.getElementById("pnl-vsf");
    if(!p){ p=document.createElement("div"); p.id="pnl-vsf"; document.body.appendChild(p); }
    return p;
  }
  function topbar(t){ return '<div class="vsf-topbar"><button class="vsf-back" id="vsf-back">‹</button><div class="vsf-ttl">'+esc(t)+'</div></div>'; }
  function open(){ panel().classList.add("on"); renderCat(); }
  function close(){ panel().classList.remove("on"); }
  window.chOpenVSF=open;

  function renderCat(){
    CUR=null; var p=panel();
    p.innerHTML=topbar("🛂 Vize Formları — Resmi Düzende Doldurun")
     +'<div class="vsf-scroll"><div class="vsf-wrap">'
     +'<div class="vsf-note" style="font-size:12px">Formları kendiniz doldurun — canlı A4 önizleme resmi düzendedir. Yazdırıp imzalayın; randevuda dosyanıza ekleyin.</div>'
     +'<div class="vsf-grid">'+Object.keys(VF).map(function(k){var f=VF[k];
        return '<div class="vsf-card" data-k="'+k+'"><div class="vsf-ic">'+f.ic+'</div><div class="vsf-n">'+esc(f.n)+'</div><div class="vsf-s">'+esc(f.s)+'</div></div>';
      }).join("")+'</div>'
     +'<div class="vsf-note">Makamların bastığı resmî belgeler (ör. Verpflichtungserklärung) burada üretilmez — checklist bölümündeki yönlendirmelere bakın.</div>'
     +'</div></div>';
    document.getElementById("vsf-back").addEventListener("click",close);
    p.querySelectorAll(".vsf-card").forEach(function(c){
      c.addEventListener("click",function(){ CUR=c.getAttribute("data-k"); STEP=0; prefill(); renderStep(); });
    });
  }
  function prefill(){
    try{
      var d=getD(CUR), pr=prof(), ch=false;
      var map=(CUR==="schengen")?{f1:"f2",f3:"f1",f19a:"f3",f19b:"f7",f19c:"f5"}:(CUR==="cover")?{c_name:null}:{};
      if(CUR==="schengen"){
        if(!d.f1&&pr.f2){d.f1=pr.f2;ch=true;} if(!d.f3&&pr.f1){d.f3=pr.f1;ch=true;}
        if(!d.f19a&&(pr.f3||pr.f4)){d.f19a=[pr.f3,pr.f4].filter(Boolean).join(", ");ch=true;}
        if(!d.f19b&&pr.f7){d.f19b=pr.f7;ch=true;} if(!d.f19c&&pr.f5){d.f19c=pr.f5;ch=true;}
      }
      if(CUR==="cover"&&!d.c_name&&(pr.f1||pr.f2)){ d.c_name=[pr.f1,pr.f2].filter(Boolean).join(" "); ch=true; }
      if(CUR==="itinerary"&&!d.i_name&&(pr.f1||pr.f2)){ d.i_name=[pr.f1,pr.f2].filter(Boolean).join(" "); ch=true; }
      if(ch) setD(CUR,d);
    }catch(e){}
  }
  var _vt=null;
  function schedPrev(){ clearTimeout(_vt); _vt=setTimeout(updPrev,240); }
  function updPrev(){
    try{
      var box=document.getElementById("vsf-previn"); if(!box||!CUR) return;
      var pages=buildPages(CUR);
      var show=Math.min(STEP,pages.length-1);
      box.innerHTML=pages[show]||pages[0]||"";
      var w=box.parentNode.clientWidth-20;
      var sc=Math.min(1,w/794);
      var a4=box.querySelector(".vfA4");
      if(a4){ a4.style.transform="scale("+sc+")"; a4.style.transformOrigin="top left"; box.style.height=(1123*sc+10)+"px"; }
    }catch(e){}
  }
  function renderStep(){
    var p=panel(), f=VF[CUR], st=f.steps[STEP], d=getD(CUR);
    var dots=f.steps.map(function(s,i){
      var cls=i===STEP?"on":(i<STEP?"ok":"");
      return '<div class="vsf-dot '+cls+'">'+(i<STEP?"✓":(i+1))+'</div>'+(i<f.steps.length-1?'<div class="vsf-dash"></div>':'');
    }).join("");
    p.innerHTML=topbar(f.n)
     +'<div class="vsf-scroll"><div class="vsf-wrap">'
     +'<div class="vsf-steps">'+dots+'</div>'
     +'<div class="vsf-ed"><div class="vsf-form">'
     +'<div class="vsf-fl" style="font-size:13px;color:#fff">'+esc(st.t)+'</div>'
     +st.f.map(function(fd){
        var val=d[fd.k]||"";
        if(fd.opts){ return '<div class="vsf-fl">'+esc(fd.l)+'</div><select class="vsf-sel" data-f="'+fd.k+'"><option value=""></option>'+fd.opts.map(function(o){return '<option'+(val===o?" selected":"")+'>'+esc(o)+'</option>';}).join("")+'</select>'; }
        if(fd.ty==="ta"){ return '<div class="vsf-fl">'+esc(fd.l)+'</div><textarea class="vsf-ta" data-f="'+fd.k+'">'+esc(val)+'</textarea>'; }
        return '<div class="vsf-fl">'+esc(fd.l)+'</div><input class="vsf-in" data-f="'+fd.k+'" value="'+esc(val)+'">';
      }).join("")
     +'<div class="vsf-nav">'+(STEP>0?'<button class="vsf-prevb" id="vsf-pv">‹ Geri</button>':'')
     +'<button class="vsf-nextb" id="vsf-nx">'+(STEP<f.steps.length-1?"Kaydet & devam ›":"📄 PDF oluştur (yazdır)")+'</button></div>'
     +'<div class="vsf-note">Bilgiler cihazınızda saklanır. PDF için giriş + plan gerekir. Çıktıyı imzalamayı unutmayın.</div>'
     +'</div>'
     +'<div class="vsf-prev"><div class="vsf-prevbox"><div id="vsf-previn"></div></div></div>'
     +'</div></div></div>';
    document.getElementById("vsf-back").addEventListener("click",renderCat);
    p.querySelectorAll("[data-f]").forEach(function(el){
      var ev=(el.tagName==="SELECT")?"change":"input";
      el.addEventListener(ev,function(){ var dd=getD(CUR); dd[el.getAttribute("data-f")]=el.value; setD(CUR,dd); schedPrev(); });
    });
    var pv=document.getElementById("vsf-pv");
    if(pv) pv.addEventListener("click",function(){ STEP--; renderStep(); });
    document.getElementById("vsf-nx").addEventListener("click",function(){
      if(STEP<f.steps.length-1){ STEP++; renderStep(); }
      else { makePdf(); }
    });
    updPrev(); setTimeout(updPrev,300);
  }
  function makePdf(){
    if(window.chDlGate&&!window.chDlGate()) return;
    var pages=buildPages(CUR);
    var css=document.getElementById("ch-vsf-css");
    var html='<!doctype html><html><head><meta charset="utf-8"><title>'+esc(VF[CUR].n)+'</title>'
     +'<style>@page{size:A4;margin:0}html,body{margin:0;padding:0}'+(css?css.textContent:"")+'\n.vfA4{page-break-after:always}.vfA4:last-child{page-break-after:auto}</style></head><body>'
     +pages.join("")
     +'<script>window.onload=function(){setTimeout(function(){window.print();},350);};<\/script></body></html>';
    try{ if(window.chPrintDoc){ window.chPrintDoc(html); return; } }catch(e){}
    var w=window.open("","_blank");
    if(!w){ alert("Popup engellendi"); return; }
    w.document.open(); w.document.write(html); w.document.close();
  }

  /* ── TR vize paneline giris karti ── */
  function inject(){
    try{
      var p=document.getElementById("pnl-trvisa"); if(!p) return;
      var wrap=p.querySelector(".trv-wrap"); if(!wrap) return;
      if(wrap.querySelector("#vsf-entry")) return;
      var e=document.createElement("div"); e.id="vsf-entry";
      e.style.cssText="border:1px solid #d4a84a;border-radius:13px;padding:14px;margin-bottom:14px;cursor:pointer;background:linear-gradient(135deg,rgba(212,168,74,.12),rgba(212,168,74,.03))";
      e.innerHTML='<div style="font-size:14px;font-weight:800;color:#fff">🛂 Vize Formlarını Doldurun</div>'
        +'<div style="font-size:11.5px;color:var(--t3,#99a);margin-top:4px">Schengen başvuru formu (resmî düzen) · niyet dilekçesi · sponsor beyanı · seyahat planı · davet mektubu — sayfa sayfa doldurun, A4 önizleyin, yazdırıp imzalayın.</div>';
      e.addEventListener("click",open);
      wrap.insertBefore(e, wrap.firstChild);
    }catch(e){}
  }
  setInterval(inject, 900);
})();}catch(e){}
</script>
HTML;

$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi.\n");
$src = substr($src,0,$pos).$bundle."\n".substr($src,$pos);
echo "  ✓ Vize Form Studyosu eklendi (5 form, A4 resmi duzen, chDlGate kapili)\n";

$tmp = tempnam(sys_get_temp_dir(),'vs').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-visastudio-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_VISA_STUDIO uygulandi. TR vize panelinde '🛂 Vize Formlarini Doldurun' karti; 5 doldurulabilir resmi form.\n";
