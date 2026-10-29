/* ChatHelp Studio — Betreiber-Bereich (admin-lib, nur CH_ADMIN_EMAILS): Übersicht (Umsatz, MRR, Konten, KI-/Stimm-Kosten, Nutzung, Systemzustand),
   Konten (Suche, Filter, Sortierung, Detail mit Aktionen: Tokens gutschreiben/abbuchen, Agent freischalten/verlängern/beenden, Notiz), Protokoll, Einstellungen (#admSet).
   Texte nur für den Betreiber: eigenes kleines Wörterbuch (de · tr · en). Nutzt die Studio-Globals api, esc, msg, go, ME, PLANS, I18N, loadAdmin. */
(function(){
  'use strict';
  const L={
    de:{tag:'Nur für dich sichtbar',new:'neu',title:'Betreiber-Bereich',sub:'Umsatz, Konten, Kosten und Systemzustand von ChatHelp – live.',ov:'Übersicht',acc:'Konten',log:'Protokoll',set:'Einstellungen',days:'{n} Tage',
      mrr:'Monatsumsatz (MRR)',mrrS:'ARR {a} · Ø {p} je zahlendem Konto',pay:'Zahlungseingänge',payS:'{n} Zahlungen im Zeitraum',payNone:'erfasst ab jetzt (Stripe)',paying:'Zahlende Konten',payingS:'{t} in Probezeit · {l} Lizenzen',newAcc:'Neue Konten',newS:'{t} Konten gesamt',active:'Aktive Konten',activeS:'mit Nutzung im Zeitraum',ai:'KI-Kosten',aiS:'{c} ct je Antwort · Cache {p}',
      chart:'Verlauf',m_signups:'Anmeldungen',m_answers:'Antworten',m_calls:'Anrufe',m_ai_eur:'KI-Kosten (€)',m_payments_cents:'Zahlungen (€)',noData:'Noch keine Daten im Zeitraum.',
      health:'Systemzustand',k_ai:'KI (Hauptanbieter)',k_ai_backup:'KI-Ersatz',k_voice_premium:'Premium-Stimmen',k_voice_standard:'Standard-Stimmen',k_stt:'Spracherkennung',k_phone:'Telefonie',k_payments:'Zahlungen',k_payments_webhook:'Zahlungs-Webhook',k_mail:'E-Mail',k_push:'Push',k_maps:'Karten/Orte',
      issues:'Auffälligkeiten',i_low:'Wenig Guthaben bei aktivem Paket',i_onb:'Einrichtung offen seit > 2 Tagen',i_cbf:'Rückrufe fehlgeschlagen (7 T.)',i_stuck:'Anrufe ohne Ende (> 2 Std.)',i_pd:'Zahlungen überfällig',i_qa:'Kontingent-Warnungen (7 T.)',i_num:'Aktive Nummern',allOk:'Alles in Ordnung.',
      rev:'Umsatz',byKind:'nach Paketart',kind_agent:'Monats-Agenten',kind_abo:'Abos',kind_betrieb:'Betrieb nach Kauf',kind_byok:'Eigener KI-Schlüssel',kind_buy:'Lizenzen',topPlans:'Top-Pakete',
      cost:'Kosten',aiCh:'KI je Kanal',tts:'Stimme (Zeichen diesen Monat)',stt:'Spracherkennung',sttMin:'{m} Min.',margin:'KI-Kosten ≈ {p} des MRR',
      use:'Nutzung der Plattform',ch_phone:'Telefon',ch_whatsapp:'WhatsApp',ch_web:'Website',ch_studio:'Studio-Tests',ch_intern:'Intern (Assistent, Training)',calls:'Anrufe',min:'{m} Min.',webChats:'Website-Chats',tokSold:'Tokens verkauft',tokFree:'Tokens gratis (Start, Kulanz)',tokUsed:'Tokens verbraucht',
      a_order:'Bestellungen',a_appt:'Termine',a_ride:'Fahrten',a_hotel:'Zimmerbuchungen',a_hotel_request:'Gästewünsche',a_callback:'Rückrufe',
      search:'E-Mail oder Konto-Nr. suchen …',s_created:'Neueste zuerst',s_last:'Zuletzt aktiv',s_balance:'Guthaben',s_usage:'Nutzung (30 T.)',s_email:'E-Mail A–Z',s_revenue:'Umsatz',
      f_all:'Alle',f_paying:'Zahlend',f_trial:'Ohne Abo',f_active:'Aktiv (30 T.)',f_inactive:'Inaktiv',f_low:'Wenig Guthaben',f_onboarding:'Einrichtung offen',f_admin_credit:'Mit Gutschrift',
      th_acc:'Konto',th_plans:'Pakete',th_bal:'Guthaben',th_mrr:'MRR',th_use:'Antworten 30 T.',th_last:'Zuletzt aktiv',th_created:'Seit',more:'Weitere {n} laden',none:'Keine Konten gefunden.',never:'nie',
      close:'Schließen',loading:'Wird geladen …',err:'Fehler',bal:'Guthaben',last:'Zuletzt aktiv',since:'Kunde seit',keys:'API-Schlüssel',byok:'Eigener KI-Schlüssel',adminB:'Betreiber',
      actions:'Aktionen',tokens:'Tokens',amount:'Menge',reason:'Grund (Pflicht)',credit:'Gutschreiben',debit:'Abbuchen',grant:'Agent freischalten',grantDays:'Tage',grantBtn:'Freischalten',note:'Interne Notiz',noteSave:'Notiz speichern',
      plans:'Pakete',extend:'+30 Tage',cancel:'Beenden',paid:'bezahlt',free:'ohne Zahlung',until:'bis {d}',st_active:'aktiv',st_trialing:'Probezeit',st_past_due:'überfällig',st_canceled:'beendet',st_expired:'abgelaufen',st_unpaid:'unbezahlt',st_incomplete_expired:'abgebrochen',
      cfg:'Agenten & Kanäle',widget:'Website-Widget',phoneOn:'Telefon',kb:'Wissen: {n} Zeichen',nums:'Nummern',onbs:'Einrichtung',st_open:'offen',st_done:'fertig',usage30:'Nutzung (30 Tage)',aiCost:'KI-Kosten je Monat',ledger:'Token-Buch (ohne Einzelantworten)',ledger30:'Summe je Art (30 T.)',payments:'Zahlungen',logT:'Protokoll',profile:'Profil',
      confirmDebit:'{n} Tokens von {e} abbuchen?',confirmCancel:'Freischaltung „{p}“ beenden?',done:'Gespeichert.',th_when:'Zeit',th_who:'Von',th_action:'Aktion',th_detail:'Details',ac_credit:'Gutschrift',ac_debit:'Abbuchung',ac_extend:'Verlängert',ac_grant:'Freigeschaltet',ac_cancel:'Beendet',ac_note:'Notiz',logNone:'Noch keine Aktionen.',
      setMoved:'Annahmen, Telefon-Grenzen, Spracherkennung und Paketpreise',updated:'Stand {t}',privacy:'Gesprächsinhalte siehst du hier bewusst nicht – nur Kennzahlen.',
      funnel:'Schnellstart – wo Kunden aussteigen ({n} T.)',fn_step:'Schritt',fn_view:'erreicht',fn_done:'erledigt',fn_last:'hier aufgehört',fn_help:'Hilfe',fnNone:'Noch keine Schnellstarts im Zeitraum.',
      fs_link:'Link',fs_draft:'Entwurf',fs_sector:'Branche',fs_voice:'Stimme',fs_try:'Testgespräch',fs_plan:'Paket',fs_number:'Nummer',fs_live:'Live'},
    tr:{tag:'Sadece sen görürsün',new:'yeni',title:'Yönetim Paneli',sub:'ChatHelp’in geliri, hesapları, maliyetleri ve sistem durumu – canlı.',ov:'Genel bakış',acc:'Hesaplar',log:'Kayıtlar',set:'Ayarlar',days:'{n} gün',
      mrr:'Aylık gelir (MRR)',mrrS:'Yıllık {a} · ödeyen hesap başı Ø {p}',pay:'Gelen ödemeler',payS:'Dönemde {n} ödeme',payNone:'bundan sonra kaydediliyor (Stripe)',paying:'Ödeyen hesaplar',payingS:'{t} deneme sürecinde · {l} lisans',newAcc:'Yeni hesaplar',newS:'toplam {t} hesap',active:'Aktif hesaplar',activeS:'dönemde kullanım olan',ai:'Yapay zekâ maliyeti',aiS:'yanıt başı {c} sent · önbellek {p}',
      chart:'Gelişim',m_signups:'Kayıtlar',m_answers:'Yanıtlar',m_calls:'Aramalar',m_ai_eur:'YZ maliyeti (€)',m_payments_cents:'Ödemeler (€)',noData:'Bu dönemde henüz veri yok.',
      health:'Sistem durumu',k_ai:'Yapay zekâ (ana)',k_ai_backup:'Yapay zekâ yedeği',k_voice_premium:'Premium sesler',k_voice_standard:'Standart sesler',k_stt:'Konuşma tanıma',k_phone:'Telefon',k_payments:'Ödemeler',k_payments_webhook:'Ödeme webhook’u',k_mail:'E-posta',k_push:'Bildirim',k_maps:'Harita/yerler',
      issues:'Dikkat edilecekler',i_low:'Aktif pakette düşük bakiye',i_onb:'2 günden uzun açık kurulum',i_cbf:'Başarısız geri aramalar (7 g.)',i_stuck:'Bitmeyen aramalar (> 2 saat)',i_pd:'Gecikmiş ödemeler',i_qa:'Kota uyarıları (7 g.)',i_num:'Aktif numaralar',allOk:'Her şey yolunda.',
      rev:'Gelir',byKind:'paket türüne göre',kind_agent:'Aylık ajanlar',kind_abo:'Abonelikler',kind_betrieb:'Satın alma sonrası işletim',kind_byok:'Kendi YZ anahtarı',kind_buy:'Lisanslar',topPlans:'En çok kazandıran paketler',
      cost:'Maliyetler',aiCh:'Kanala göre YZ',tts:'Ses (bu ay karakter)',stt:'Konuşma tanıma',sttMin:'{m} dk.',margin:'YZ maliyeti ≈ MRR’nin {p}’i',
      use:'Platform kullanımı',ch_phone:'Telefon',ch_whatsapp:'WhatsApp',ch_web:'Web sitesi',ch_studio:'Studio testleri',ch_intern:'Dahili (asistan, eğitim)',calls:'Aramalar',min:'{m} dk.',webChats:'Web sohbetleri',tokSold:'Satılan token',tokFree:'Ücretsiz token (başlangıç, jest)',tokUsed:'Harcanan token',
      a_order:'Siparişler',a_appt:'Randevular',a_ride:'Yolculuklar',a_hotel:'Oda rezervasyonları',a_hotel_request:'Misafir istekleri',a_callback:'Geri aramalar',
      search:'E-posta veya hesap no. ara …',s_created:'En yeniler',s_last:'Son aktif',s_balance:'Bakiye',s_usage:'Kullanım (30 g.)',s_email:'E-posta A–Z',s_revenue:'Gelir',
      f_all:'Tümü',f_paying:'Ödeyen',f_trial:'Aboneliksiz',f_active:'Aktif (30 g.)',f_inactive:'Pasif',f_low:'Düşük bakiye',f_onboarding:'Kurulum açık',f_admin_credit:'Jest yapılmış',
      th_acc:'Hesap',th_plans:'Paketler',th_bal:'Bakiye',th_mrr:'MRR',th_use:'Yanıt 30 g.',th_last:'Son aktif',th_created:'Kayıt',more:'{n} hesap daha yükle',none:'Hesap bulunamadı.',never:'hiç',
      close:'Kapat',loading:'Yükleniyor …',err:'Hata',bal:'Bakiye',last:'Son aktif',since:'Müşteri olduğu tarih',keys:'API anahtarları',byok:'Kendi YZ anahtarı',adminB:'Yönetici',
      actions:'İşlemler',tokens:'Token',amount:'Miktar',reason:'Sebep (zorunlu)',credit:'Token ekle',debit:'Token düş',grant:'Ajan aç',grantDays:'Gün',grantBtn:'Aç',note:'İç not',noteSave:'Notu kaydet',
      plans:'Paketler',extend:'+30 gün',cancel:'Sonlandır',paid:'ödemeli',free:'ödemesiz',until:'{d} tarihine kadar',st_active:'aktif',st_trialing:'deneme',st_past_due:'gecikmiş',st_canceled:'sonlandı',st_expired:'süresi doldu',st_unpaid:'ödenmedi',st_incomplete_expired:'iptal',
      cfg:'Ajanlar & kanallar',widget:'Web widget’ı',phoneOn:'Telefon',kb:'Bilgi: {n} karakter',nums:'Numaralar',onbs:'Kurulum',st_open:'açık',st_done:'tamam',usage30:'Kullanım (30 gün)',aiCost:'Aylık YZ maliyeti',ledger:'Token defteri (tek tek yanıtlar hariç)',ledger30:'Türe göre toplam (30 g.)',payments:'Ödemeler',logT:'Kayıtlar',profile:'Profil',
      confirmDebit:'{e} hesabından {n} token düşülsün mü?',confirmCancel:'“{p}” erişimi sonlandırılsın mı?',done:'Kaydedildi.',th_when:'Zaman',th_who:'Kim',th_action:'İşlem',th_detail:'Ayrıntı',ac_credit:'Token eklendi',ac_debit:'Token düşüldü',ac_extend:'Uzatıldı',ac_grant:'Açıldı',ac_cancel:'Sonlandırıldı',ac_note:'Not',logNone:'Henüz işlem yok.',
      setMoved:'Varsayımlar, telefon sınırları, konuşma tanıma ve paket fiyatları',updated:'Güncelleme {t}',privacy:'Görüşme içeriklerini burada bilerek göremezsin – sadece rakamlar.',
      funnel:'Hızlı kurulum – müşteriler nerede bırakıyor ({n} g.)',fn_step:'Adım',fn_view:'ulaşan',fn_done:'tamamlayan',fn_last:'burada kalan',fn_help:'Yardım',fnNone:'Bu dönemde henüz hızlı kurulum yok.',
      fs_link:'Bağlantı',fs_draft:'Taslak',fs_sector:'Sektör',fs_voice:'Ses',fs_try:'Test sohbeti',fs_plan:'Paket',fs_number:'Numara',fs_live:'Yayında'},
    en:{tag:'Visible only to you',new:'new',title:'Operator area',sub:'Revenue, accounts, costs and system health of ChatHelp – live.',ov:'Overview',acc:'Accounts',log:'Log',set:'Settings',days:'{n} days',
      mrr:'Monthly revenue (MRR)',mrrS:'ARR {a} · avg {p} per paying account',pay:'Payments received',payS:'{n} payments in period',payNone:'recorded from now on (Stripe)',paying:'Paying accounts',payingS:'{t} in trial · {l} licences',newAcc:'New accounts',newS:'{t} accounts total',active:'Active accounts',activeS:'with usage in period',ai:'AI cost',aiS:'{c} ct per answer · cache {p}',
      chart:'Trend',m_signups:'Sign-ups',m_answers:'Answers',m_calls:'Calls',m_ai_eur:'AI cost (€)',m_payments_cents:'Payments (€)',noData:'No data in this period yet.',
      health:'System health',k_ai:'AI (primary)',k_ai_backup:'AI fallback',k_voice_premium:'Premium voices',k_voice_standard:'Standard voices',k_stt:'Speech recognition',k_phone:'Telephony',k_payments:'Payments',k_payments_webhook:'Payment webhook',k_mail:'E-mail',k_push:'Push',k_maps:'Maps/places',
      issues:'Needs attention',i_low:'Low balance on active plan',i_onb:'Setup open > 2 days',i_cbf:'Failed callbacks (7 d)',i_stuck:'Calls without end (> 2 h)',i_pd:'Payments past due',i_qa:'Quota warnings (7 d)',i_num:'Active numbers',allOk:'All good.',
      rev:'Revenue',byKind:'by plan type',kind_agent:'Monthly agents',kind_abo:'Subscriptions',kind_betrieb:'Operation after purchase',kind_byok:'Own AI key',kind_buy:'Licences',topPlans:'Top plans',
      cost:'Costs',aiCh:'AI by channel',tts:'Voice (characters this month)',stt:'Speech recognition',sttMin:'{m} min',margin:'AI cost ≈ {p} of MRR',
      use:'Platform usage',ch_phone:'Phone',ch_whatsapp:'WhatsApp',ch_web:'Website',ch_studio:'Studio tests',ch_intern:'Internal (assistant, training)',calls:'Calls',min:'{m} min',webChats:'Website chats',tokSold:'Tokens sold',tokFree:'Free tokens (start, goodwill)',tokUsed:'Tokens used',
      a_order:'Orders',a_appt:'Appointments',a_ride:'Rides',a_hotel:'Room bookings',a_hotel_request:'Guest requests',a_callback:'Callbacks',
      search:'Search e-mail or account no. …',s_created:'Newest first',s_last:'Last active',s_balance:'Balance',s_usage:'Usage (30 d)',s_email:'E-mail A–Z',s_revenue:'Revenue',
      f_all:'All',f_paying:'Paying',f_trial:'No subscription',f_active:'Active (30 d)',f_inactive:'Inactive',f_low:'Low balance',f_onboarding:'Setup open',f_admin_credit:'With goodwill credit',
      th_acc:'Account',th_plans:'Plans',th_bal:'Balance',th_mrr:'MRR',th_use:'Answers 30 d',th_last:'Last active',th_created:'Since',more:'Load {n} more',none:'No accounts found.',never:'never',
      close:'Close',loading:'Loading …',err:'Error',bal:'Balance',last:'Last active',since:'Customer since',keys:'API keys',byok:'Own AI key',adminB:'Operator',
      actions:'Actions',tokens:'Tokens',amount:'Amount',reason:'Reason (required)',credit:'Credit',debit:'Debit',grant:'Unlock agent',grantDays:'Days',grantBtn:'Unlock',note:'Internal note',noteSave:'Save note',
      plans:'Plans',extend:'+30 days',cancel:'End',paid:'paid',free:'without payment',until:'until {d}',st_active:'active',st_trialing:'trial',st_past_due:'past due',st_canceled:'ended',st_expired:'expired',st_unpaid:'unpaid',st_incomplete_expired:'aborted',
      cfg:'Agents & channels',widget:'Website widget',phoneOn:'Phone',kb:'Knowledge: {n} characters',nums:'Numbers',onbs:'Setup',st_open:'open',st_done:'done',usage30:'Usage (30 days)',aiCost:'AI cost per month',ledger:'Token ledger (without single answers)',ledger30:'Total by type (30 d)',payments:'Payments',logT:'Log',profile:'Profile',
      confirmDebit:'Debit {n} tokens from {e}?',confirmCancel:'End access “{p}”?',done:'Saved.',th_when:'Time',th_who:'By',th_action:'Action',th_detail:'Details',ac_credit:'Credit',ac_debit:'Debit',ac_extend:'Extended',ac_grant:'Unlocked',ac_cancel:'Ended',ac_note:'Note',logNone:'No actions yet.',
      setMoved:'Assumptions, phone limits, speech recognition and plan prices',updated:'As of {t}',privacy:'You deliberately don’t see conversation content here – figures only.',
      funnel:'Quick start – where customers drop off ({n} d)',fn_step:'Step',fn_view:'reached',fn_done:'completed',fn_last:'stopped here',fn_help:'Help',fnNone:'No quick starts in this period yet.',
      fs_link:'Link',fs_draft:'Draft',fs_sector:'Sector',fs_voice:'Voice',fs_try:'Test chat',fs_plan:'Plan',fs_number:'Number',fs_live:'Live'}
  };
  const D=L[I18N.lang]||(['az'].includes(I18N.lang)?L.tr:L.en);
  const T=(k,v)=>{let s=D[k]!=null?D[k]:(L.de[k]!=null?L.de[k]:k);if(v)s=s.replace(/\{(\w+)\}/g,(m,n)=>v[n]!=null?v[n]:m);return s;};
  const g=id=>document.getElementById(id);const h=v=>esc(v==null?'':String(v));
  const N=n=>I18N.num(n||0);const EUR=(c,cur)=>I18N.num((c||0)/100,{style:'currency',currency:cur||'EUR',maximumFractionDigits:(c||0)%100?2:0});const PCT=x=>I18N.num(x||0,{style:'percent',maximumFractionDigits:0});
  const DT=iso=>iso?I18N.date(iso,{day:'2-digit',month:'2-digit',year:'2-digit'}):'–';const DTT=iso=>iso?I18N.dt(iso,{dateStyle:'short',timeStyle:'short'}):'–';
  const visible=()=>{const el=g('panel-admin');return !!el&&!el.closest('.hidden')&&!document.hidden;};
  const planName=id=>{const p=(typeof PLANS!=='undefined'&&PLANS||[]).find(x=>x.id===id);return p?p.name:id;};
  const S={sec:'ov',days:30,metric:'answers',ov:null,q:'',filter:'all',sort:'created',off:0,rows:[],seq:0,timer:null,qt:null,cur:null};
  function rel(iso){if(!iso)return T('never');const d=(Date.now()-new Date(iso).getTime())/1000;if(d<3600)return Math.max(1,Math.round(d/60))+' min';if(d<86400)return Math.round(d/3600)+' h';if(d<86400*45)return Math.round(d/86400)+' d';return DT(iso);}
  function delta(v,p){if(!v&&!p)return '';if(!p)return '';const r=(v-p)/p;return '<span class="ad-delta '+(Math.abs(r)<0.005?'':r>0?'up':'down')+'">'+(r>=0?'▲':'▼')+' '+PCT(Math.abs(r))+'</span>';}
  function texts(){g('adTag').textContent=T('tag');g('adTitle').textContent=T('title');g('adSub').textContent=T('sub');
    [['adNavOv','ov'],['adNavAcc','acc'],['adNavLog','log'],['adNavSet','set']].forEach(([id,k])=>g(id).textContent=T(k));
    g('adDays').querySelectorAll('button').forEach(b=>{b.textContent=T('days',{n:b.dataset.d});});
    g('adChT').textContent=T('chart');g('adHealthT').textContent=T('health');g('adRevT').textContent=T('rev');g('adCostT').textContent=T('cost');g('adUseT').textContent=T('use');
    g('adMetric').innerHTML=['signups','answers','calls','ai_eur','payments_cents'].map(m=>'<button type="button" data-m="'+m+'" aria-pressed="'+(m===S.metric)+'">'+h(T('m_'+m))+'</button>').join('');
    g('adQ').placeholder=T('search');g('adSort').innerHTML=['created','last','balance','usage','revenue','email'].map(s=>'<option value="'+s+'">'+h(T('s_'+s))+'</option>').join('');}

  /* ---------- Übersicht ---------- */
  function kpi(l,v,s,d){return '<div class="ad-kpi"><div class="l">'+h(l)+'</div><div class="v">'+v+(d||'')+'</div><div class="s">'+s+'</div></div>';}
  function nice(m){if(m<=4)return 4;const p=Math.pow(10,Math.floor(Math.log10(m))),f=m/p;return (f<=1?1:f<=2?2:f<=2.5?2.5:f<=5?5:10)*p;}
  function chart(){const o=S.ov;if(!o)return;const m=S.metric,ser=o.series,el=g('adChart');const val=d=>m==='payments_cents'?(d[m]||0)/100:(d[m]||0);
    const vs=ser.map(val);if(!vs.some(x=>x>0)){el.innerHTML='<div class="ad-empty">'+h(T('noData'))+'</div>';return;}
    const fmt=v=>m==='ai_eur'||m==='payments_cents'?I18N.num(v,{style:'currency',currency:'EUR',maximumFractionDigits:v<10?2:0}):N(v);
    const mx=nice(Math.max(...vs)),W=720,H=220,pl=46,pr=8,pt=10,pb=24,iw=W-pl-pr,ih=H-pt-pb,n=ser.length,band=iw/n,bw=Math.max(2,Math.min(band*.64,24));
    let s='<svg viewBox="0 0 '+W+' '+H+'" role="img" aria-label="'+h(T('m_'+m))+'">';
    for(let i=0;i<=4;i++){const v=mx*i/4,y=pt+ih-ih*i/4;s+='<line class="gl" x1="'+pl+'" x2="'+(W-pr)+'" y1="'+y+'" y2="'+y+'"/><text class="ax" x="'+(pl-6)+'" y="'+(y+3.5)+'" text-anchor="end">'+h(fmt(v))+'</text>';}
    const every=Math.max(1,Math.ceil(n/7));
    ser.forEach((d,i)=>{const v=vs[i],x0=pl+i*band,hh=v/mx*ih,x=x0+(band-bw)/2,y=pt+ih-hh,r=Math.min(4,bw/2,hh);
      s+='<rect class="band" data-i="'+i+'" x="'+x0+'" y="'+pt+'" width="'+band+'" height="'+ih+'" fill="transparent"/>';
      if(hh>0)s+='<path d="M'+x+' '+(pt+ih)+'V'+(y+r)+'Q'+x+' '+y+' '+(x+r)+' '+y+'H'+(x+bw-r)+'Q'+(x+bw)+' '+y+' '+(x+bw)+' '+(y+r)+'V'+(pt+ih)+'Z" fill="var(--c-one)"/>';
      if(i%every===0)s+='<text class="ax" x="'+(x0+band/2)+'" y="'+(H-7)+'" text-anchor="middle">'+h(I18N.date(new Date(d.d+'T12:00'),{day:'2-digit',month:'2-digit'}))+'</text>';});
    s+='</svg><div class="ad-tip" hidden></div>';el.innerHTML=s;const tip=el.querySelector('.ad-tip'),svg=el.querySelector('svg');
    el.querySelectorAll('.band').forEach(b=>b.addEventListener('mouseenter',()=>{const i=+b.dataset.i;el.querySelectorAll('.band').forEach(x=>x.classList.toggle('on',x===b));
      tip.innerHTML='<b>'+h(I18N.date(new Date(ser[i].d+'T12:00'),{weekday:'short',day:'2-digit',month:'2-digit'}))+'</b> · '+h(fmt(vs[i]));const r=svg.getBoundingClientRect(),sc=r.width/W;
      tip.style.left=Math.max(60,Math.min(r.width-60,(pl+(i+.5)*band)*sc))+'px';tip.style.top=Math.max(30,(pt+ih-vs[i]/mx*ih)*sc-6)+'px';tip.hidden=false;}));
    svg.addEventListener('mouseleave',()=>{tip.hidden=true;});}
  function renderOv(o){S.ov=o;const a=o.accounts,r=o.revenue,c=o.costs,u=o.usage;
    const eur=(r.payments||[]).find(x=>x.currency==='EUR'),eurP=(r.payments_prev||[]).find(x=>x.currency==='EUR');const other=(r.payments||[]).filter(x=>x.currency!=='EUR');
    const payV=eur?EUR(eur.cents):EUR(0);const payS=(r.payments||[]).length?h(T('payS',{n:N((r.payments||[]).reduce((s,x)=>s+x.n,0))}))+(other.length?' · '+other.map(x=>h(EUR(x.cents,x.currency))).join(' · '):''):h(T('payNone'));
    g('adKpis').innerHTML=kpi(T('mrr'),h(EUR(r.mrr_cents)),h(T('mrrS',{a:EUR(r.arr_cents),p:EUR(r.arpa_cents)})))+
      kpi(T('pay'),h(payV),payS,delta(eur?eur.cents:0,eurP?eurP.cents:0))+
      kpi(T('paying'),N(a.paying),h(T('payingS',{t:N(a.trialing),l:N(a.licenses)})))+
      kpi(T('newAcc'),N(a.new),h(T('newS',{t:N(a.total)})),delta(a.new,a.new_prev))+
      kpi(T('active'),N(a.active),h(T('activeS')),delta(a.active,a.active_prev))+
      kpi(T('ai'),c.ai?h(I18N.num(c.ai.eur,{style:'currency',currency:'EUR',maximumFractionDigits:2})):'–',c.ai?h(T('aiS',{c:I18N.num(c.ai.cent_per_answer||0,{maximumFractionDigits:2}),p:PCT(c.ai.cache_share)})):'',c.ai&&c.ai_prev_eur!=null?delta(c.ai.eur,c.ai_prev_eur):'');
    chart();
    /* Systemzustand */
    const K=o.keys||{},I=o.issues||{};let hs='<ul class="ad-list">'+Object.keys(K).map(k=>'<li><span><span class="ad-dot" style="background:'+(K[k]?'var(--ok)':'var(--rose)')+'"></span>'+h(T('k_'+k))+'</span><b>'+(K[k]?'✓':'✗')+'</b></li>').join('')+'</ul>';
    const iss=[['i_onb',I.onboarding_stale],['i_cbf',I.callbacks_failed],['i_stuck',I.calls_stuck],['i_pd',I.past_due],['i_qa',I.quota_alerts]].filter(x=>x[1]>0);
    hs+='<h4 style="margin:14px 0 6px;font:600 13.5px var(--sans)">'+h(T('issues'))+'</h4>';
    if(!iss.length&&!(I.low_balance||[]).length)hs+='<div class="small" style="color:var(--ok)">✓ '+h(T('allOk'))+'</div>';
    hs+='<ul class="ad-list">'+iss.map(([k,n])=>'<li><span>'+h(T(k))+'</span><span class="ad-pill rose">'+N(n)+'</span></li>').join('')+'<li><span>'+h(T('i_num'))+'</span><b>'+N(I.numbers_active)+'</b></li></ul>';
    if((I.low_balance||[]).length)hs+='<div class="small" style="margin:10px 0 4px;font-weight:600">'+h(T('i_low'))+'</div><ul class="ad-list">'+I.low_balance.map(x=>'<li><button type="button" class="btn s" data-acc="'+x.id+'" style="min-height:36px;max-width:70%;overflow:hidden;text-overflow:ellipsis">'+h(x.email)+'</button><b>'+N(x.balance)+'</b></li>').join('')+'</ul>';
    hs+='<div class="small mute" style="margin-top:10px">PHP '+h(o.system.php)+' · DB '+h(String(o.system.db).split('-')[0])+' · '+h(T('updated',{t:I18N.time(new Date(o.generated_at))}))+'</div>';
    g('adHealth').innerHTML=hs;
    /* Schnellstart-Trichter (quickstart-lib qs_funnel): je Schritt erreicht / erledigt / hier aufgehört */
    const F=o.funnel||null;
    if(F){const steps=['link','draft','sector','voice','try','plan','number','live'],mx=Math.max(1,...steps.map(s=>(F[s]||{}).view||0)),any=steps.some(s=>(F[s]||{}).view);
      g('adHealth').insertAdjacentHTML('beforeend','<h4 style="margin:14px 0 6px;font:600 13.5px var(--sans)">'+h(T('funnel',{n:S.days}))+'</h4>'+(any?'<table class="ad-t" id="adFunnel"><thead><tr><th>'+h(T('fn_step'))+'</th><th class="n">'+h(T('fn_view'))+'</th><th class="n">'+h(T('fn_done'))+'</th><th class="n">'+h(T('fn_last'))+'</th><th class="n">'+h(T('fn_help'))+'</th></tr></thead><tbody>'
        +steps.map(s=>{const x=F[s]||{};return '<tr><td>'+h(T('fs_'+s))+'<div class="ad-bar"><div style="width:'+((x.view||0)/mx*100).toFixed(1)+'%"></div></div></td><td class="n">'+N(x.view)+'</td><td class="n">'+N(x.done)+'</td><td class="n"'+(x.last?' style="color:var(--rose);font-weight:700"':'')+'>'+N(x.last)+'</td><td class="n">'+N(x.help)+'</td></tr>';}).join('')+'</tbody></table>':'<div class="ad-empty" id="adFunnel">'+h(T('fnNone'))+'</div>'));}
    /* Umsatz */
    const bk=(r.by_kind||[]).slice().sort((x,y)=>y.mrr_cents-x.mrr_cents),mxk=Math.max(1,...bk.map(x=>x.mrr_cents));
    g('adRev').innerHTML='<div class="small mute" style="margin-bottom:6px">'+h(T('byKind'))+'</div>'+(bk.length?'<ul class="ad-list">'+bk.map(x=>'<li style="display:block"><div style="display:flex;justify-content:space-between"><span>'+h(T('kind_'+x.kind))+' <span class="mute">· '+N(x.n)+'</span></span><b>'+h(EUR(x.mrr_cents))+'</b></div><div class="ad-bar"><div style="width:'+(x.mrr_cents/mxk*100).toFixed(1)+'%"></div></div></li>').join('')+'</ul>':'<div class="ad-empty">–</div>')+
      '<div class="small" style="margin:12px 0 4px;font-weight:600">'+h(T('topPlans'))+'</div>'+((r.top_plans||[]).length?'<table class="ad-t"><tbody>'+r.top_plans.map(p=>'<tr><td>'+h(planName(p.plan))+'</td><td class="n">'+N(p.n)+'×</td><td class="n">'+h(EUR(p.mrr_cents))+'</td></tr>').join('')+'</tbody></table>':'<div class="ad-empty">–</div>');
    /* Kosten */
    let cs='';if(c.ai&&c.ai.channels&&c.ai.channels.length)cs+='<div class="small mute">'+h(T('aiCh'))+'</div><table class="ad-t"><tbody>'+c.ai.channels.map(x=>'<tr><td>'+h(_tx(x.label))+'</td><td class="n">'+N(x.answers)+'</td><td class="n">'+h(I18N.num(x.eur,{style:'currency',currency:'EUR',maximumFractionDigits:2}))+'</td></tr>').join('')+'</tbody></table>';
    if(c.margin_hint!=null)cs+='<div class="small" style="margin-top:6px;color:'+(c.margin_hint<0.6?'var(--rose)':'var(--ok)')+'">'+h(T('margin',{p:PCT(1-c.margin_hint)}))+'</div>';
    if((c.tts_month||[]).length)cs+='<div class="small mute" style="margin-top:12px">'+h(T('tts'))+'</div><ul class="ad-list">'+c.tts_month.map(x=>{const p=x.budget?Math.min(100,x.chars/x.budget*100):0;return '<li style="display:block"><div style="display:flex;justify-content:space-between"><span>'+h(x.provider)+'</span><b>'+N(x.chars)+(x.budget?' / '+N(x.budget):'')+'</b></div>'+(x.budget?'<div class="ad-bar"><div style="width:'+p.toFixed(1)+'%;background:'+(p>=90?'var(--rose)':p>=70?'var(--gold)':'var(--c-one)')+'"></div></div>':'')+'</li>';}).join('')+'</ul>';
    if((c.stt||[]).length)cs+='<div class="small mute" style="margin-top:12px">'+h(T('stt'))+'</div><ul class="ad-list">'+c.stt.map(x=>'<li><span>'+h(x.provider)+' · '+h(T('sttMin',{m:I18N.num(x.minutes,{maximumFractionDigits:1})}))+'</span><b>'+(x.usd!=null?h(I18N.num(x.usd,{style:'currency',currency:'USD'})):'')+'</b></li>').join('')+'</ul>';
    g('adCost').innerHTML=cs||'<div class="ad-empty">–</div>';
    /* Nutzung */
    const an=u.answers||{},anP=u.answers_prev||{};
    g('adUse').innerHTML='<ul class="ad-list">'+['phone','whatsapp','web','studio','intern'].map(k=>'<li><span>'+h(T('ch_'+k))+'</span><span><b>'+N(an[k])+'</b>'+delta(an[k],anP[k])+'</span></li>').join('')+
      '<li><span>'+h(T('calls'))+'</span><span><b>'+N(u.calls.n)+'</b> <span class="mute">· '+h(T('min',{m:N(u.calls.min)}))+'</span>'+delta(u.calls.n,u.calls.prev)+'</span></li><li><span>'+h(T('webChats'))+'</span><b>'+N(u.web_chats)+'</b></li></ul>'+
      '<ul class="ad-list" style="margin-top:8px">'+(u.actions||[]).filter(x=>x.n||x.prev).map(x=>'<li><span>'+h(T('a_'+x.kind))+'</span><span><b>'+N(x.n)+'</b>'+delta(x.n,x.prev)+'</span></li>').join('')+'</ul>'+
      '<ul class="ad-list" style="margin-top:8px"><li><span>'+h(T('tokSold'))+'</span><b>'+N(u.tokens.sold)+'</b></li><li><span>'+h(T('tokFree'))+'</span><b>'+N(u.tokens.free)+'</b></li><li><span>'+h(T('tokUsed'))+'</span><b>'+N(u.tokens.used)+'</b></li></ul>';}
  function loadOv(silent){const my=++S.seq;g('adDays').querySelectorAll('button').forEach(b=>b.setAttribute('aria-pressed',String(+b.dataset.d===S.days)));
    return api('/admin/overview?days='+S.days).then(o=>{if(my===S.seq)renderOv(o);}).catch(e=>{if(!silent)g('adKpis').innerHTML='<div class="msg err" style="grid-column:1/-1">'+h(_te(e))+'</div>';});}

  /* ---------- Konten ---------- */
  function stPill(st){const tone={active:'ok',trialing:'gold',past_due:'rose'}[st]||'';return '<span class="ad-pill '+tone+'">'+h(T('st_'+st))+'</span>';}
  function accRow(x){return '<tr class="ad-acc" data-acc="'+x.id+'" tabindex="0"><td><b style="overflow-wrap:anywhere">'+h(x.email)+'</b>'+(x.admin?' <span class="ad-pill indigo">'+h(T('adminB'))+'</span>':'')+'<div class="small mute">#'+x.id+(x.company?' · '+h(x.company):'')+(x.country?' · '+h(x.country):'')+(x.onboarding&&x.onboarding.open?' · <span class="ad-pill gold">'+h(T('f_onboarding'))+'</span>':'')+'</div></td>'+
    '<td class="hm">'+(x.plans.length?x.plans.map(p=>'<span class="ad-pill'+(p.paid?' ok':'')+'" title="'+h(T('st_'+p.status))+'">'+h(planName(p.id))+'</span>').join(''):'<span class="mute">–</span>')+'</td>'+
    '<td class="n"'+(x.plans.length&&x.balance<200?' style="color:var(--rose)"':'')+'>'+N(x.balance)+'</td><td class="n hm">'+(x.mrr_cents?h(EUR(x.mrr_cents)):'–')+'</td><td class="n">'+N(x.use30)+'</td><td class="n hm">'+h(rel(x.last_active))+'</td><td class="n hm">'+h(DT(x.created_at))+'</td></tr>';}
  function loadAcc(reset){if(reset){S.off=0;S.rows=[];}const my=++S.seq;
    if(reset)g('adAccT').innerHTML='<tbody><tr><td><div class="ad-skel"></div><div class="ad-skel" style="width:60%"></div></td></tr></tbody>';
    return api('/admin/accounts?q='+encodeURIComponent(S.q)+'&filter='+S.filter+'&sort='+S.sort+'&limit=50&offset='+S.off).then(j=>{if(my!==S.seq)return;S.rows=S.rows.concat(j.items);
      g('adFilters').innerHTML=['all','paying','trial','active','inactive','low','onboarding','admin_credit'].map(f=>'<button type="button" data-f="'+f+'" aria-pressed="'+(f===S.filter)+'">'+h(T('f_'+f))+'<span>'+N((j.counts||{})[f])+'</span></button>').join('');
      g('adAccT').innerHTML='<thead><tr><th>'+h(T('th_acc'))+'</th><th class="hm">'+h(T('th_plans'))+'</th><th class="n">'+h(T('th_bal'))+'</th><th class="n hm">'+h(T('th_mrr'))+'</th><th class="n">'+h(T('th_use'))+'</th><th class="n hm">'+h(T('th_last'))+'</th><th class="n hm">'+h(T('th_created'))+'</th></tr></thead><tbody>'+
        (S.rows.length?S.rows.map(accRow).join(''):'<tr><td colspan="7" class="ad-empty">'+h(T('none'))+'</td></tr>')+'</tbody>';
      const m=g('adMore');m.hidden=!j.more;m.textContent=T('more',{n:N(j.total-S.rows.length)});}).catch(e=>{g('adAccT').innerHTML='<tbody><tr><td><div class="msg err">'+h(_te(e))+'</div></td></tr></tbody>';});}

  /* ---------- Konto-Detail ---------- */
  let lastFocus=null;
  function openAcc(id,btn){lastFocus=btn||document.activeElement;S.cur=id;const dr=g('adDrawer'),pn=g('adDet');dr.hidden=false;document.body.style.overflow='hidden';
    pn.innerHTML='<div class="hd"><h3 id="adDetT">'+h(T('loading'))+'</h3><button type="button" class="x" data-close="1" aria-label="'+h(T('close'))+'">✕</button></div><div class="ad-skel"></div><div class="ad-skel" style="width:70%"></div>';pn.querySelector('.x').focus();
    return api('/admin/account?id='+id).then(d=>renderAcc(d)).catch(e=>{pn.innerHTML='<div class="hd"><h3 id="adDetT">'+h(T('err'))+'</h3><button type="button" class="x" data-close="1">✕</button></div><div class="msg err">'+h(_te(e))+'</div>';});}
  function renderAcc(d){const a=d.account,u=d.usage30,pn=g('adDet');
    const sec=(t,b)=>'<div class="sec"><h4>'+h(t)+'</h4>'+b+'</div>';
    let o='<div class="hd"><div style="min-width:0"><h3 id="adDetT">'+h(a.email)+'</h3><div class="small mute" style="margin-top:4px">#'+a.id+' · '+h(T('since'))+' '+h(DT(a.created_at))+(a.admin?' · '+h(T('adminB')):'')+(a.byok?' · '+h(T('byok')):'')+'</div></div><button type="button" class="x" data-close="1" aria-label="'+h(T('close'))+'">✕</button></div>';
    o+='<div class="kv"><div><b>'+N(a.balance)+'</b><span>'+h(T('bal'))+'</span></div><div><b>'+N(u.answers.phone+u.answers.whatsapp+u.answers.web)+'</b><span>'+h(T('m_answers'))+' · '+h(T('days',{n:30}))+'</span></div><div><b>'+N(u.calls)+'</b><span>'+h(T('calls'))+' · '+h(T('min',{m:N(u.call_min)}))+'</span></div><div><b>'+h(rel(a.last_active))+'</b><span>'+h(T('last'))+'</span></div></div>';
    o+=sec(T('actions'),'<div class="grid2"><label>'+h(T('amount'))+' ('+h(T('tokens'))+')<input type="number" id="adAmt" min="1" max="1000000" step="1" value="500"></label><label>'+h(T('reason'))+'<input type="text" id="adNoteA" maxlength="300"></label></div>'+
      '<div class="btns"><button class="btn p" type="button" data-act="credit">'+h(T('credit'))+'</button><button class="btn" type="button" data-act="debit">'+h(T('debit'))+'</button></div>'+
      '<div class="grid2" style="margin-top:12px"><label>'+h(T('grant'))+'<select id="adGrant">'+(d.grantable||[]).map(p=>'<option value="'+h(p.id)+'">'+h(planName(p.id)||p.name)+'</option>').join('')+'</select></label><label>'+h(T('grantDays'))+'<input type="number" id="adGrantD" min="1" max="366" value="14"></label></div>'+
      '<div class="btns"><button class="btn" type="button" data-act="grant">'+h(T('grantBtn'))+'</button></div>'+
      '<label style="margin-top:12px">'+h(T('note'))+'<textarea id="adNote" maxlength="4000">'+h(d.note)+'</textarea></label><div class="btns"><button class="btn" type="button" data-act="note">'+h(T('noteSave'))+'</button></div><div id="adActMsg"></div>');
    o+=sec(T('plans'),d.plans.length?'<table class="ad-t"><tbody>'+d.plans.map(p=>'<tr><td><b>'+h(planName(p.id)||p.name)+'</b><div class="small mute">'+h(p.paid?T('paid'):T('free'))+(p.period_end?' · '+h(T('until',{d:DT(p.period_end)})):'')+(p.month_cents?' · '+h(EUR(p.month_cents))+'/M':'')+'</div></td><td>'+stPill(p.status)+'</td><td class="n">'+
      (!p.paid?'<button class="btn s" type="button" data-act="extend" data-row="'+p.row+'" style="min-height:36px">'+h(T('extend'))+'</button> '+(['active','trialing'].includes(p.status)?'<button class="btn s" type="button" data-act="cancel" data-row="'+p.row+'" data-name="'+h(planName(p.id))+'" style="min-height:36px">'+h(T('cancel'))+'</button>':''):'')+'</td></tr>').join('')+'</tbody></table>':'<div class="mute small">–</div>');
    o+=sec(T('cfg'),d.configs.length?'<ul class="ad-list">'+d.configs.map(c=>'<li style="display:block"><b>'+h(planName(c.plan))+'</b>'+(c.company?' · '+h(c.company):'')+'<div class="small mute">'+h(T('widget'))+': '+(c.widget_on?'✓'+(c.widget_domain?' ('+h(c.widget_domain)+')':''):'–')+' · '+h(T('phoneOn'))+': '+(c.phone_on?'✓ '+h(c.phone_number):'–')+' · '+h(T('kb',{n:N(c.kb_chars)}))+' · '+h(String(c.langs||c.lang).toUpperCase())+'</div></li>').join('')+'</ul>':'<div class="mute small">–</div>');
    if(d.numbers.length||d.onboardings.length)o+='<div class="grid2">'+(d.numbers.length?sec(T('nums'),'<ul class="ad-list">'+d.numbers.map(n=>'<li><span>'+h(n.e164)+' · '+h(n.country)+'</span>'+stPill(n.status==='active'?'active':'canceled')+'</li>').join('')+'</ul>'):'')+(d.onboardings.length?sec(T('onbs'),'<ul class="ad-list">'+d.onboardings.map(n=>'<li><span>'+h(planName(n.plan))+' · '+h(DT(n.created_at))+'</span><span class="ad-pill '+(n.status==='done'?'ok':'gold')+'">'+h(T('st_'+n.status))+'</span></li>').join('')+'</ul>'):'')+'</div>';
    const ua=u.answers,acts=Object.entries(u.actions||{}).filter(([k,v])=>v.n>0);
    o+=sec(T('usage30'),'<ul class="ad-list">'+['phone','whatsapp','web','studio','intern'].filter(k=>ua[k]).map(k=>'<li><span>'+h(T('ch_'+k))+'</span><b>'+N(ua[k])+'</b></li>').join('')+acts.map(([k,v])=>'<li><span>'+h(T('a_'+k))+'</span><b>'+N(v.n)+(v.cents?' · '+h(EUR(v.cents)):'')+'</b></li>').join('')+'<li><span>'+h(T('tokUsed'))+'</span><b>'+N(u.tokens)+'</b></li></ul>');
    if(d.ai_cost.length)o+=sec(T('aiCost'),'<table class="ad-t"><tbody>'+d.ai_cost.map(x=>'<tr><td>'+h(x.ym)+(x.byok?' · '+h(T('byok')):'')+'</td><td class="n">'+N(x.calls)+'</td><td class="n">'+h(I18N.num(x.eur,{style:'currency',currency:'EUR'}))+'</td></tr>').join('')+'</tbody></table>');
    o+=sec(T('ledger'),(d.ledger_30d.length?'<div class="small mute" style="margin-bottom:4px">'+h(T('ledger30'))+': '+d.ledger_30d.map(x=>h(x.reason)+' '+(x.tokens>0?'+':'')+N(x.tokens)).join(' · ')+'</div>':'')+
      (d.ledger.length?'<table class="ad-t"><tbody>'+d.ledger.map(x=>'<tr><td>'+h(DTT(x.created_at))+'</td><td>'+h(x.reason)+'</td><td class="n" style="color:'+(x.delta>0?'var(--ok)':'var(--ink)')+'">'+(x.delta>0?'+':'')+N(x.delta)+'</td></tr>').join('')+'</tbody></table>':'<div class="mute small">–</div>'));
    if(d.payments.length)o+=sec(T('payments'),'<table class="ad-t"><tbody>'+d.payments.map(x=>'<tr><td>'+h(DTT(x.created_at))+'</td><td>'+h(x.kind)+(x.plan?' · '+h(planName(x.plan)):'')+'</td><td class="n">'+h(EUR(x.cents,x.currency))+'</td></tr>').join('')+'</tbody></table>');
    if(d.profile)o+=sec(T('profile'),'<div class="small">'+[d.profile.company,d.profile.city,d.profile.country,d.profile.sectors,d.profile.size].filter(Boolean).map(h).join(' · ')+'</div>');
    o+=sec(T('logT'),d.log.length?'<ul class="ad-list">'+d.log.map(x=>'<li style="display:block"><b>'+h(T('ac_'+x.action))+'</b> · '+h(x.detail)+'<div class="small mute">'+h(DTT(x.created_at))+' · '+h(x.by)+'</div></li>').join('')+'</ul>':'<div class="mute small">'+h(T('logNone'))+'</div>');
    o+='<p class="small mute" style="margin-top:12px">🔒 '+h(T('privacy'))+'</p>';
    pn.innerHTML=o;const x=pn.querySelector('.x');if(x)x.focus();
    pn.querySelectorAll('[data-act]').forEach(b=>b.addEventListener('click',()=>act(b,d)));}
  function act(b,d){const k=b.dataset.act,id=d.account.id,body={id,action:k};
    if(k==='credit'||k==='debit'){body.amount=+g('adAmt').value;body.note=g('adNoteA').value.trim();if(k==='debit'&&!confirm(T('confirmDebit',{n:N(body.amount),e:d.account.email})))return;}
    if(k==='grant'){body.plan=g('adGrant').value;body.days=+g('adGrantD').value;body.note=g('adNoteA').value.trim();}
    if(k==='extend'){body.plan_row=+b.dataset.row;body.days=30;}
    if(k==='cancel'){body.plan_row=+b.dataset.row;if(!confirm(T('confirmCancel',{p:b.dataset.name||''})))return;}
    if(k==='note')body.note=g('adNote').value;
    b.disabled=true;api('/admin/action',{method:'POST',body:JSON.stringify(body)}).then(()=>api('/admin/account?id='+id)).then(nd=>{renderAcc(nd);msg(g('adActMsg'),h(T('done')),'ok');if(S.sec==='acc')loadAcc(true);})
      .catch(e=>{b.disabled=false;msg(g('adActMsg'),h(_te(e)),'err');});}
  function closeAcc(){const dr=g('adDrawer');if(dr.hidden)return;dr.hidden=true;document.body.style.overflow='';S.cur=null;if(lastFocus&&lastFocus.focus)try{lastFocus.focus();}catch(e){}}

  /* ---------- Protokoll + Einstellungen ---------- */
  function loadLog(){g('adLogT').innerHTML='<tbody><tr><td><div class="ad-skel"></div></td></tr></tbody>';
    api('/admin/log').then(j=>{g('adLogT').innerHTML='<thead><tr><th>'+h(T('th_when'))+'</th><th>'+h(T('th_acc'))+'</th><th>'+h(T('th_action'))+'</th><th class="hm">'+h(T('th_detail'))+'</th><th class="hm">'+h(T('th_who'))+'</th></tr></thead><tbody>'+
      (j.items.length?j.items.map(x=>'<tr class="ad-acc" data-acc="'+x.account_id+'"><td>'+h(DTT(x.created_at))+'</td><td style="overflow-wrap:anywhere">'+h(x.account)+'</td><td>'+h(T('ac_'+x.action))+'</td><td class="hm">'+h(x.detail)+'</td><td class="hm">'+h(x.by)+'</td></tr>').join(''):'<tr><td colspan="5" class="ad-empty">'+h(T('logNone'))+'</td></tr>')+'</tbody>';})
      .catch(e=>{g('adLogT').innerHTML='<tbody><tr><td><div class="msg err">'+h(_te(e))+'</div></td></tr></tbody>';});}
  function placeSettings(){const c=document.getElementById('admSet'),host=g('adSet');if(!c||!host)return;if(c.parentNode!==host){host.appendChild(c);c.style.marginTop='0';}
    if(typeof loadAdmin==='function')loadAdmin();}
  function section(s){S.sec=s;g('adNav').querySelectorAll('button').forEach(b=>b.setAttribute('aria-selected',String(b.dataset.s===s)));
    g('adOv').hidden=s!=='ov';g('adAcc').hidden=s!=='acc';g('adLog').hidden=s!=='log';g('adSet').hidden=s!=='set';g('adDays').style.visibility=s==='ov'?'visible':'hidden';
    if(s==='ov')loadOv();if(s==='acc')loadAcc(true);if(s==='log')loadLog();if(s==='set')placeSettings();}
  function bind(){const root=g('adRoot');
    g('adNav').addEventListener('click',e=>{const b=e.target.closest('button[data-s]');if(b)section(b.dataset.s);});
    g('adDays').addEventListener('click',e=>{const b=e.target.closest('button[data-d]');if(!b)return;S.days=+b.dataset.d;loadOv();});
    g('adMetric').addEventListener('click',e=>{const b=e.target.closest('button[data-m]');if(!b)return;S.metric=b.dataset.m;g('adMetric').querySelectorAll('button').forEach(x=>x.setAttribute('aria-pressed',String(x===b)));chart();});
    g('adQ').addEventListener('input',e=>{clearTimeout(S.qt);S.qt=setTimeout(()=>{S.q=e.target.value.trim();loadAcc(true);},300);});
    g('adSort').addEventListener('change',e=>{S.sort=e.target.value;loadAcc(true);});
    g('adFilters').addEventListener('click',e=>{const b=e.target.closest('button[data-f]');if(!b)return;S.filter=b.dataset.f;loadAcc(true);});
    g('adMore').addEventListener('click',()=>{S.off=S.rows.length;loadAcc(false);});
    root.addEventListener('click',e=>{const c=e.target.closest('[data-close]');if(c){closeAcc();return;}const a=e.target.closest('[data-acc]');if(a&&!e.target.closest('#adDet')){openAcc(+a.dataset.acc,a);}});
    root.addEventListener('keydown',e=>{if(e.key==='Enter'){const a=e.target.closest&&e.target.closest('tr[data-acc]');if(a)openAcc(+a.dataset.acc,a);}});
    document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!g('adDrawer').hidden)closeAcc();});
    let rt=null;window.addEventListener('resize',()=>{clearTimeout(rt);rt=setTimeout(()=>{if(visible()&&S.sec==='ov')chart();},200);});}
  window.STUDIO_PANELS=window.STUDIO_PANELS||{};
  window.STUDIO_PANELS.admin={init(){texts();bind();section('ov');if(!S.timer)S.timer=setInterval(()=>{if(visible()&&S.sec==='ov'&&g('adDrawer').hidden)loadOv(true);},120000);},
    show(){if(S.sec==='ov')loadOv(true);else if(S.sec==='acc')loadAcc(true);}};
})();
