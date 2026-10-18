/* ChatHelp Studio — Panel „Taxi-Zentrale“ (ride-lib): Zentrale, Tarif, Fahrzeuge, Festpreise, Taxistände, Zahlarten, Fahrer,
   Live-Fahrten mit Zuteilen/Status, Dispositions-Board-Link, Preisrechner und manuelle Fahrt. Nutzt die Studio-Globals api, msg, esc, eurOut, eurS, active. */
(function(){
  'use strict';
  var PANEL='panel-taxi';
  var S=null, LBL={statuses:{},payments:{}}, LINKS={}, LIVE={rides:[],drivers:[]}, timer=null, pendingRender=false, busy=false, lastQuote=null, vseq=0;
  var g=function(id){return document.getElementById(id);};
  var h=function(v){return esc(v==null?'':String(v));};
  var visible=function(){var el=g(PANEL);return !!el&&!el.closest('.hidden');};
  var cents=function(v){var t=String(v==null?'':v).replace(/[\s€]/g,'');if(!t)return 0;if(t.indexOf(',')>=0)t=t.replace(/\./g,'').replace(',','.');var n=parseFloat(t);return isFinite(n)?Math.max(0,Math.round(n*100)):0;};
  var dec=function(v,d){var n=parseFloat(String(v==null?'':v).replace(',','.'));return isFinite(n)?n:d;};
  var int=function(v,d,min,max){var n=parseInt(String(v==null?'':v),10);if(!isFinite(n))n=d;return Math.max(min,Math.min(max,n));};
  var hm=function(v){var m=/^([01]?\d|2[0-3])[:.]([0-5]\d)$/.exec(String(v||'').trim());return m?((m[1].length<2?'0':'')+m[1]+':'+m[2]):null;};
  var dnum=function(n){return String(n).replace('.',I18N.dec==='.'?'.':',');};
  var ST={new:[_t('od.st.new'),'gold'],assigned:[_t('txj.st.assigned'),'indigo'],en_route:[_t('txj.st.en_route'),'indigo'],arrived:[_t('txj.st.arrived'),'teal'],onboard:[_t('txj.st.onboard'),'teal'],completed:[_t('htj.rq.done'),'ok'],cancelled:[_t('od.st.cancelled'),'rose'],no_show:[_t('txj.st.no_show'),'rose']};
  var DS={frei:[_t('txj.d.free'),'ok'],besetzt:[_t('txj.d.busy'),'rose'],pause:[_t('txj.d.pause'),'']}, DNEXT={frei:'besetzt',besetzt:'pause',pause:'frei'};
  var CH={phone:_t('sj.ch.phone').replace(/^\S+\s/,''),whatsapp:'WhatsApp',chat:'Chat',widget:'Website',studio:'Studio',hotel:'Hotel','phone-test':_t('txj.ch.ptest'),demo:'Demo'};
  function copy(text,el,label){var done=function(){msg(el,h(label||_t('c.copiedDot')),'ok');};try{if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(text).then(done,function(){prompt(_t('c.copyLinkP'),text);});return;}}catch(e){}prompt(_t('c.copyLinkP'),text);}
  function line(html){var d=document.createElement('div');d.className='row';d.style.cssText='gap:8px;padding:6px 0;border-bottom:1px solid var(--line)';d.innerHTML=html;return d;}
  function rm(d){var b=d.querySelector('[data-rm]');if(b)b.onclick=function(){d.remove();refreshSelects();};}

  /* ---------- Editor-Zeilen ---------- */
  function addVeh(v){v=v||{};var d=line('<input type="hidden" class="vKey" value="'+h(v.key||'')+'"><input type="text" class="vName" placeholder="'+h(_t('txj.vehPh'))+'" value="'+h(_tx(v.name||''))+'" style="flex:2;min-width:150px">'+
    '<input type="text" class="vSeats" inputmode="numeric" value="'+h(v.seats||4)+'" style="width:58px" title="'+h(_t('txj.seats'))+'"><span class="mute small">'+_t('txj.seats')+'</span><input type="text" class="vFac" inputmode="decimal" value="'+h(dnum(v.factor==null?1:v.factor))+'" style="width:64px" title="'+h(_t('txj.factor'))+'"><span class="mute small">'+_t('txj.xTariff')+'</span><button class="btn s" type="button" data-rm title="'+h(_t('c.remove'))+'">✕</button>');
    d.dataset.vid=v.key||('neu-'+(++vseq));   /* gespeicherte Typen: Schlüssel; neue (noch ohne Schlüssel): eindeutige Zeilen-Id – der Server ordnet sie über den Namen zu */
    rm(d);d.querySelector('.vName').addEventListener('input',refreshSelects);g('txVeh').appendChild(d);}
  function addFix(f){f=f||{};var d=line('<input type="text" class="fName" placeholder="'+h(_t('txj.fixPh'))+'" value="'+h(f.name||'')+'" style="flex:1.2;min-width:130px"><input type="text" class="fMatch" placeholder="flughafen|airport|cgn" value="'+h(f.match||'')+'" style="flex:1.5;min-width:140px" title="'+h(_t('txj.matchT'))+'">'+
    '<input type="text" class="fPrice" inputmode="decimal" value="'+h(f.price_cents?eurOut(f.price_cents):'')+'" placeholder="'+eurOut(3900)+'" style="width:78px" title="'+h(_t('txj.fixPrice'))+'"><span class="mute small">€</span><button class="btn s" type="button" data-rm title="'+h(_t('c.remove'))+'">✕</button>');rm(d);g('txFix').appendChild(d);}
  function addStand(st){st=st||{};var geo=st.name?(st.lat!=null?'<span class="tag ok" title="'+h(st.lat+', '+st.lon)+'">'+_t('txj.placeOk')+'</span>':'<span class="tag rose" title="'+h(_t('txj.notFoundT'))+'">'+_t('txj.notFound')+'</span>'):'<span class="tag">'+_t('txj.new')+'</span>';
    var d=line('<input type="text" class="sName" placeholder="'+h(_t('txj.standPh'))+'" value="'+h(st.name||'')+'" style="flex:1;min-width:120px"><input type="text" class="sAddr" placeholder="'+h(_t('txj.addrPh'))+'" value="'+h(st.address||'')+'" style="flex:2;min-width:170px">'+geo+'<button class="btn s" type="button" data-rm title="'+h(_t('c.remove'))+'">✕</button>');
    rm(d);d.querySelector('.sName').addEventListener('input',refreshSelects);g('txStands').appendChild(d);}
  function vehRows(){var rows=[].slice.call(document.querySelectorAll('#txVeh .row')).map(function(r){var k=r.querySelector('.vKey').value;return {vid:r.dataset.vid||k,key:k,name:r.querySelector('.vName').value.trim()};}).filter(function(v){return v.name;});
    if(!rows.length&&S)rows=(S.vehicles||[]).map(function(v){return {vid:v.key,key:v.key,name:_tx(v.name)};});return rows;}
  /* savedOnly: nur gespeicherte Typen (Preisrechner – der Server kennt neue Typen erst nach dem Speichern) */
  function vehOptions(cur,savedOnly){return vehRows().filter(function(v){return !savedOnly||v.key;}).map(function(v){var val=savedOnly?v.key:v.vid;return '<option value="'+h(val)+'"'+(val===cur?' selected':'')+'>'+h(v.name)+(v.key?'':' ('+_t('txj.new')+')')+'</option>';}).join('');}
  function standOptions(cur){var names=[].slice.call(document.querySelectorAll('#txStands .sName')).map(function(i){return i.value.trim();}).filter(Boolean);
    if(cur&&names.indexOf(cur)<0)names.push(cur);return '<option value="">'+_t('txj.noStand')+'</option>'+names.map(function(n){return '<option'+(n===cur?' selected':'')+'>'+h(n)+'</option>';}).join('');}
  function addDrv(x){x=x||{};var d=document.createElement('div');d.className='call';d.dataset.id=x.id||0;
    var lab=function(t){return '<label style="margin:0 0 4px">'+t+'</label>';};
    var st=DS[x.status]||DS.frei;
    d.innerHTML='<div class="grid g3" style="gap:8px"><div>'+lab(_t('c.name'))+'<input type="text" class="dName" value="'+h(x.name||'')+'" placeholder="'+h(_t('txj.first'))+'" maxlength="80"></div><div>'+lab(_t('txj.mobile'))+'<input type="tel" class="dPhone" value="'+h(x.phone||'')+'" placeholder="+49 170 …" maxlength="40"></div><div>'+lab(_t('txj.veh'))+'<input type="text" class="dVeh" value="'+h(x.vehicle||'')+'" placeholder="'+h(_t('txj.vehDPh'))+'" maxlength="60"></div></div>'+
      '<div class="grid g4" style="gap:8px;margin-top:8px"><div>'+lab(_t('txj.plate'))+'<input type="text" class="dPlate" value="'+h(x.plate||'')+'" placeholder="K-TX 123" maxlength="20"></div><div>'+lab(_t('txj.seats'))+'<input type="text" class="dSeats" inputmode="numeric" value="'+h(x.seats||4)+'"></div><div>'+lab(_t('txj.type'))+'<select class="dType" data-cur="'+h(x.vehicle_key||'')+'"></select></div><div>'+lab(_t('txj.stand'))+'<select class="dStand" data-cur="'+h(x.stand||'')+'"></select></div></div>'+
      '<div class="row" style="justify-content:space-between;margin-top:10px"><span class="row" style="gap:6px">'+(x.id?'<button class="btn s" type="button" data-dst="'+x.id+'" data-st="'+h(x.status||'frei')+'" title="'+h(_t('txj.cycleT'))+'"><span class="tag '+st[1]+'">'+h(st[0])+'</span></button>':'')+
      (x.url?'<a class="btn s" href="'+h(x.url)+'" target="_blank" rel="noopener">'+_t('txj.openApp')+'</a><button class="btn s" type="button" data-copy="'+h(x.url)+'" title="'+h(_t('txj.copyT'))+'">'+_t('txj.link')+'</button>':'<span class="mute small">'+_t('txj.linkAfter')+'</span>')+
      '</span><button class="btn s ghost" type="button" data-rm>'+_t('c.remove')+'</button></div>';
    rm(d);var cp=d.querySelector('[data-copy]');if(cp)cp.onclick=function(){copy(cp.dataset.copy,g('txMsg2'),_t('txj.drvCopied',{n:x.name||''}));};
    var sb=d.querySelector('[data-dst]');if(sb)sb.onclick=function(){driverCycle(+sb.dataset.dst,sb);};
    g('txDrivers').appendChild(d);refreshSelects();}
  function refreshSelects(){document.querySelectorAll('#txDrivers .dType').forEach(function(s){var cur=s.value||s.dataset.cur||'';s.innerHTML=vehOptions(cur);if(cur)s.value=cur;});
    document.querySelectorAll('#txDrivers .dStand').forEach(function(s){var cur=s.value||s.dataset.cur||'';s.innerHTML=standOptions(cur);s.value=cur;});
    var cv=g('txCVeh');if(cv){var c=cv.value;cv.innerHTML='<option value="">'+_t('txj.auto')+'</option>'+vehOptions('',true);cv.value=c;if(cv.value!==c)cv.value='';}}

  /* ---------- Laden / Anzeigen ---------- */
  function fill(j){S=j.settings||{};LBL=j.labels||LBL;LINKS=j.links||{};
    g('txOn').checked=!!S.ride_on;g('txName').value=S.name||'';g('txAddress').value=S.address||'';g('txPhone').value=S.phone||'';g('txNotify').value=S.notify_email||'';
    g('txArea').value=S.area_km||25;g('txEta').value=S.eta_min||8;g('txPre').value=S.prebook_days!=null?S.prebook_days:14;g('txTz').value=S.tz||'Europe/Berlin';g('txPlz').value=(S.area_plz||'').split(',').filter(Boolean).join(', ');
    g('txGeo').innerHTML=S.address?(S.lat!=null?'<span class="tag ok">'+_t('txj.onMap')+'</span> <span class="mute">'+h(S.lat.toFixed(4)+', '+S.lon.toFixed(4))+' · '+h(_t('txj.areaKm',{n:S.area_km}))+'</span>':'<span class="tag rose">'+_t('txj.notFound')+'</span> '+_t('txj.checkAddr')):'';
    var t=S.tariff||{};g('txBase').value=eurOut(t.base_cents);g('txKm1').value=eurOut(t.km1_cents);g('txUpto').value=dnum(t.km1_upto!=null?t.km1_upto:7);g('txKm2').value=eurOut(t.km2_cents);g('txWait').value=eurOut(t.wait_min_cents);
    g('txMin').value=eurOut(t.min_fare_cents);g('txFee').value=eurOut(t.booking_fee_cents);g('txNight').value=t.night_pct||0;g('txNFrom').value=t.night_from||'22:00';g('txNTo').value=t.night_to||'06:00';
    g('txVeh').innerHTML='';(S.vehicles||[]).forEach(addVeh);g('txFix').innerHTML='';(S.fixed_routes||[]).forEach(addFix);g('txStands').innerHTML='';(S.stands||[]).forEach(addStand);
    var pm=String(S.payment_methods||'bar').split(',');document.querySelectorAll('#'+PANEL+' .txPayC').forEach(function(c){c.checked=pm.indexOf(c.value)>=0;});
    g('txDrivers').innerHTML='';(j.drivers||[]).filter(function(d){return d.active!==0;}).forEach(addDrv);if(!(j.drivers||[]).some(function(d){return d.active!==0;}))g('txDrivers').innerHTML='<div class="mute small" data-empty>'+_t('txj.noDrv')+'</div>';
    g('txBoard').textContent=LINKS.board||_t('od.linkSave');g('txBoardOpen').href=LINKS.board||'#';g('txBoardOpen').classList.toggle('hidden',!LINKS.board);
    g('txBPay').innerHTML=String(S.payment_methods||'bar').split(',').map(function(k){return '<option value="'+h(k)+'">'+h(I18N.has('pay.'+k)?_t('pay.'+k):_tx((LBL.payments||{})[k]||k))+'</option>';}).join('');
    refreshSelects();tariffEx();stats(j.stats);}
  function stats(k){if(!k)return;g('txKToday').textContent=de(k.today);g('txKOpen').textContent=de(k.open);g('txKPre').textContent=_t('txj.pre',{n:de(k.prebooked)});g('txKDone').textContent=de(k.completed_today);
    g('txKLost').textContent=_t('txj.lost',{n:de(k.cancelled_today)});g('txKRev').textContent=eurS(k.revenue_today_cents||0);}
  function tariffEx(){var b=cents(g('txBase').value),k1=cents(g('txKm1').value),up=dec(g('txUpto').value,7),k2=cents(g('txKm2').value),mn=cents(g('txMin').value),fee=cents(g('txFee').value),np=int(g('txNight').value,0,0,100);
    var p=function(km,night){var f=b+Math.min(km,up)*k1+Math.max(0,km-up)*k2;if(night)f*=1+np/100;f=Math.max(f,mn)+fee;return Math.round(f/10)*10;};
    g('txTarifEx').innerHTML=_t('txj.ex')+' <b>5 km ≈ '+h(eurS(p(5)))+'</b> · <b>10 km ≈ '+h(eurS(p(10)))+'</b> · <b>25 km ≈ '+h(eurS(p(25)))+'</b>'+(np>0?' · '+h(_t('txj.night',{p:eurS(p(10,true))})):'')+' <span class="mute">'+_t('txj.factorAdd')+'</span>';}
  function load(){return api('/ride/settings').then(function(j){fill(j);return loadRides();}).catch(function(e){msg(g('txMsg'),h(e.message),'err');});}

  /* ---------- Speichern ---------- */
  function payload(){
    var veh=[].slice.call(document.querySelectorAll('#txVeh .row')).map(function(r){return {key:r.querySelector('.vKey').value,name:r.querySelector('.vName').value.trim(),seats:int(r.querySelector('.vSeats').value,4,1,20),factor:dec(r.querySelector('.vFac').value,1)};}).filter(function(v){return v.name;});
    var fix=[].slice.call(document.querySelectorAll('#txFix .row')).map(function(r){return {name:r.querySelector('.fName').value.trim(),match:r.querySelector('.fMatch').value.trim(),price_cents:cents(r.querySelector('.fPrice').value)};}).filter(function(f){return f.name;});
    var stands=[].slice.call(document.querySelectorAll('#txStands .row')).map(function(r){return {name:r.querySelector('.sName').value.trim(),address:r.querySelector('.sAddr').value.trim()};}).filter(function(s){return s.name;});
    var vmap={};vehRows().forEach(function(v){vmap[v.vid]=v;});
    var drivers=[].slice.call(document.querySelectorAll('#txDrivers .call')).map(function(r){var vid=r.querySelector('.dType').value,vt=vmap[vid];
      return {id:+r.dataset.id||0,name:r.querySelector('.dName').value.trim(),phone:r.querySelector('.dPhone').value.trim(),vehicle:r.querySelector('.dVeh').value.trim(),plate:r.querySelector('.dPlate').value.trim(),
      seats:int(r.querySelector('.dSeats').value,4,1,20),vehicle_key:vt?vt.key:vid,vehicle_name:vt?vt.name:'',stand:r.querySelector('.dStand').value};}).filter(function(d){return d.name;});
    return {ride_on:g('txOn').checked?1:0,name:g('txName').value.trim(),address:g('txAddress').value.trim(),phone:g('txPhone').value.trim(),notify_email:g('txNotify').value.trim(),tz:g('txTz').value,
      area_km:int(g('txArea').value,25,1,300),eta_min:int(g('txEta').value,8,1,90),prebook_days:int(g('txPre').value,14,0,90),area_plz:g('txPlz').value,
      tariff:{base_cents:cents(g('txBase').value),km1_cents:cents(g('txKm1').value),km1_upto:dec(g('txUpto').value,7),km2_cents:cents(g('txKm2').value),wait_min_cents:cents(g('txWait').value),min_fare_cents:cents(g('txMin').value),
        booking_fee_cents:cents(g('txFee').value),night_pct:int(g('txNight').value,0,0,100),night_from:hm(g('txNFrom').value)||'22:00',night_to:hm(g('txNTo').value)||'06:00'},
      vehicles:veh,fixed_routes:fix,stands:stands,payment_methods:[].slice.call(document.querySelectorAll('#'+PANEL+' .txPayC:checked')).map(function(c){return c.value;}).join(','),drivers:drivers};}
  function save(btn,out){var p=payload();
    if(!hm(g('txNFrom').value)||!hm(g('txNTo').value)){msg(out,_t('txj.nightFmt'),'err');return;}
    if(!p.vehicles.length){msg(out,_t('txj.needVeh'),'err');return;}
    if(!p.payment_methods){msg(out,_t('txj.needPay'),'err');return;}
    btn.disabled=true;msg(out,_t('txj.saving'),'info');
    api('/ride/settings',{method:'POST',body:JSON.stringify(p)}).then(function(j){fill(j);msg(out,_t('txj.saved')+(j.settings&&j.settings.address&&j.settings.lat==null?' '+_t('txj.addrNF'):''),'ok');loadRides(true);})
      .catch(function(e){msg(out,h(e.message),'err');}).then(function(){btn.disabled=false;});}
  function driverCycle(id,btn){var d=null;(LIVE.drivers||[]).forEach(function(x){if(x.id===id)d=x;});var cur=d?d.status:(btn.dataset.st||'frei');
    btn.disabled=true;api('/ride/driver',{method:'POST',body:JSON.stringify({id:id,status:DNEXT[cur]||'frei'})}).then(function(j){var st=DS[j.driver.status]||DS.frei;btn.innerHTML='<span class="tag '+st[1]+'">'+h(st[0])+'</span>';btn.dataset.st=j.driver.status;if(j.kept_busy)msg(g('txMsg2'),h(j.driver.name+': '+((I18N.lang==='de'&&j.hint)||_t('txj.keptBusy'))),'info');loadRides(true);})
      .catch(function(e){msg(g('txMsg2'),h(e.message),'err');}).then(function(){btn.disabled=false;});}

  /* ---------- Live-Fahrten ---------- */
  function drvName(id){var n='';(LIVE.drivers||[]).forEach(function(d){if(d.id===id)n=d.name;});return n;}
  function drvSelect(r){var order=(r.driver_order&&r.driver_order.length)?r.driver_order:(LIVE.drivers||[]).map(function(d){return d.id;});var unfit=r.unfit_driver_ids||[];
    var opts=order.map(function(id){var d=null;(LIVE.drivers||[]).forEach(function(x){if(x.id===id)d=x;});if(!d)return '';return '<option value="'+d.id+'"'+((r.driver_id||r.suggest_driver_id)===d.id?' selected':'')+'>'+h((r.suggest_driver_id===d.id?'★ ':'')+d.name+' · '+((DS[d.status]||[d.status])[0])+(d.stand?' · '+d.stand:'')+(unfit.indexOf(d.id)>=0?' · '+_t('txj.unfit'):''))+'</option>';}).join('');
    return '<select data-sel="'+r.id+'" style="width:auto;max-width:220px;padding:6px 8px">'+(opts||'<option value="">'+_t('txj.noDrvSel')+'</option>')+'</select>';}
  function acts(r){var b=function(act,label,title){return '<button class="btn s" type="button" data-act="'+act+'" data-id="'+r.id+'"'+(title?' title="'+h(title)+'"':'')+'>'+label+'</button>';};var x=r.can_cancel?b('cancelled',_t('htj.storno')):'';
    if(r.status==='new')return drvSelect(r)+b('assign',_t('txj.assign'))+x;
    if(r.status==='assigned')return b('en_route',_t('txj.a.route'))+b('new','↺',_t('txj.unassign'))+x;
    if(r.status==='en_route')return b('arrived',_t('txj.a.arrived'))+x;
    if(r.status==='arrived')return b('onboard',_t('txj.a.onboard'))+b('no_show',_t('txj.a.noshow'));
    if(r.status==='onboard')return b('completed',_t('txj.a.done'));return '';}
  function rideHtml(r){var st=ST[r.status]||[r.status,''];
    var when=r.asap?'<span class="tag gold">'+_t('txj.asap')+'</span>'+(r.eta_text?' <span class="mute small">'+h(_tx(r.eta_text))+'</span>':''):'<span class="tag indigo">'+h(I18N.lang!=='de'&&r.when_iso?I18N.dt(r.when_iso,{weekday:'short',day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'}):_tx(r.when))+'</span>';
    var info=[_t('txj.pax',{n:r.passengers}),r.vehicle_name].concat(r.extras?[r.extras]:[]).concat(r.luggage?[_t('txj.lug',{l:r.luggage})]:[]).join(' · ');
    return '<div class="call" style="margin-top:8px"><div class="row" style="justify-content:space-between;align-items:flex-start;gap:8px"><span style="flex:1;min-width:220px"><b>'+h(r.code)+'</b> · '+when+' · <b>'+h(r.price_kind==='fixed'?I18N.fx(r.price_text):(r.price_cents!=null?_t('txj.approx',{p:eurS(r.price_cents)}):_t('txj.meter')))+'</b><br>'+
      '<span class="small">📍 '+h(r.pickup)+(r.pickup_checked?'':' <span class="tag rose">'+_t('txj.unchecked')+'</span>')+(r.destination?' → 🏁 '+h(r.destination):'')+(r.distance_text?' <span class="mute">('+h(I18N.fx(r.distance_text))+')</span>':'')+'</span><br>'+
      '<span class="mute small">'+h(info)+'<br>'+h(r.name)+(r.phone?' · '+h(r.phone):'')+' · '+h(CH[r.channel]||r.channel)+' · '+h(_t('od.at',{t:r.created}))+(r.driver?' · '+h(_t('od.driver',{n:r.driver.name}))+(r.driver.plate?' ('+h(r.driver.plate)+')':''):'')+(r.note?' · 📝 '+h(r.note):'')+'</span></span>'+
      '<span class="row" style="gap:6px;justify-content:flex-end"><span class="tag '+st[1]+'">'+h(st[0])+'</span>'+acts(r)+'<a class="btn s ghost" href="'+h(r.track)+'" target="_blank" rel="noopener" title="'+h(_t('txj.trackT'))+'">↗</a></span></div></div>';}
  function renderRides(){var a=document.activeElement;if(a&&a.tagName==='SELECT'&&a.closest&&a.closest('#txList')){pendingRender=true;return;}pendingRender=false;
    var el=g('txList'),R=LIVE.rides||[];if(!R.length){el.innerHTML='<div class="mute small">'+(g('txAll').checked?_t('txj.none7'):_t('txj.noneOpen'))+'</div>';return;}
    el.innerHTML=R.map(rideHtml).join('');}
  function loadRides(quiet){return api('/ride/rides?scope='+(g('txAll').checked?'all':'open')).then(function(j){LIVE=j;stats(j.stats);renderRides();})
    .catch(function(e){if(!quiet)g('txList').textContent=e.message;});}
  function act(e){var b=e.target.closest('[data-act]');if(!b||busy)return;var id=+b.dataset.id,a=b.dataset.act,body={id:id,status:a};
    (LIVE.rides||[]).forEach(function(x){if(x.id===id)body.from=x.status;});   /* zuletzt gesehener Status → Konflikt statt stiller Überschreibung, wenn Board/Fahrer/Kunde schneller war */
    if(a==='assign'){var s=document.querySelector('#txList select[data-sel="'+id+'"]');if(!s||!s.value){alert(_t('txj.drvFirst'));return;}body.status='assigned';body.driver_id=+s.value;}
    if(a==='cancelled'&&!confirm(_t('txj.cancelQ')))return;if(a==='no_show'&&!confirm(_t('txj.noshowQ')))return;if(a==='new'&&!confirm(_t('txj.unassignQ')))return;
    busy=true;b.disabled=true;api('/ride/status',{method:'POST',body:JSON.stringify(body)}).then(function(j){if(j.warnings&&j.warnings.length)alert(j.warnings.map(_tx).join('\n'));return loadRides(true);})
      .catch(function(err){alert((I18N.lang==='de'&&err.data&&err.data.hint)||err.message);b.disabled=false;return loadRides(true);}).then(function(){busy=false;});}

  /* ---------- Preisrechner / manuelle Fahrt ---------- */
  function calcInput(){return {pickup:g('txCPick').value.trim(),destination:g('txCDest').value.trim(),time:g('txCTime').value.trim()||'sofort',vehicle:g('txCVeh').value,passengers:String(int(g('txCPax').value,1,1,16)),extras:g('txCExtras').value.trim()};}
  function quoteHtml(j){var p=j.pickup||{},d=j.destination;var kind=j.price_kind==='fixed'?[_t('txj.fixed'),'gold']:(j.price_kind==='estimate'?[_t('txj.est'),'indigo']:[_t('txj.meter'),'']);
    var rows=[[_t('txj.pick'),(p.display||p.text)+(p.in_area===false?' · '+_t('od.chk.out'):'')+(p.distance_from_zentrale_km!=null?' · '+_t('txj.fromHq',{n:dnum(p.distance_from_zentrale_km)}):'')],[_t('txj.dest'),d?(d.display||d.text):_t('txj.noDest')],[_t('txj.route'),I18N.fx(j.distance_text)||'–'],
      [_t('txj.time'),I18N.lang!=='de'&&j.when_iso?I18N.dt(j.when_iso,{weekday:'long',day:'numeric',month:'long',hour:'2-digit',minute:'2-digit'}):_tx(j.when_long)],[_t('txj.veh'),j.vehicle?j.vehicle.name:''],[_t('txj.nearest'),j.nearest_stand||'–']];
    return '<div class="row" style="gap:6px"><span class="tag '+kind[1]+'">'+kind[0]+'</span>'+(j.night_surcharge?'<span class="tag">'+_t('txj.nightS')+'</span>':'')+'<span class="tag '+(p.found?'ok':'rose')+'">'+(p.found?_t('txj.pickOk'):_t('txj.pickNo'))+'</span>'+(j.route_source==='estimate'?'<span class="tag" title="'+h(_t('txj.estT'))+'">'+_t('txj.estR')+'</span>':'')+'</div>'+
      '<div class="num" style="margin-top:8px">'+h(j.price_cents!=null?eurS(j.price_cents):_t('txj.meter'))+'</div><div class="mute">'+h(I18N.fx(_tx(j.price_text)))+(j.eta_text?' · '+h(_t('txj.wait',{t:_tx(j.eta_text)})):'')+'</div>'+
      '<table style="margin-top:8px"><tbody>'+rows.map(function(r){return '<tr><td class="mute" style="width:120px">'+h(r[0])+'</td><td>'+h(r[1])+'</td></tr>';}).join('')+'</tbody></table>'+
      ((j.notes||[]).length?'<div class="msg info">'+j.notes.map(function(n){return h(_tx(n));}).join('<br>')+'</div>':'');}
  function calc(){var inp=calcInput();if(!inp.pickup){msg(g('txCRes'),_t('txj.needPick'),'err');return;}
    var qs=Object.keys(inp).filter(function(k){return inp[k]!=='';}).map(function(k){return encodeURIComponent(k)+'='+encodeURIComponent(inp[k]);}).join('&');
    g('txCRes').innerHTML='<span class="mute">'+_t('txj.calc')+'</span>';g('txBForce').classList.add('hidden');msg(g('txBMsg'),'');
    api('/ride/estimate?'+qs).then(function(j){lastQuote=j;g('txCRes').innerHTML=quoteHtml(j);g('txBook').classList.remove('hidden');})
      .catch(function(e){var d=e.data||{};msg(g('txCRes'),h((I18N.lang==='de'&&d.hint)||e.message),'err');if(d.error==='outside_area'){g('txBook').classList.remove('hidden');g('txBForce').classList.remove('hidden');}else g('txBook').classList.add('hidden');});}
  function book(force){var inp=calcInput();var body={name:g('txBName').value.trim(),phone:g('txBPhone').value.trim(),pickup:inp.pickup,destination:inp.destination,time:inp.time,passengers:+inp.passengers,vehicle:inp.vehicle,extras:inp.extras,
      luggage:g('txBLug').value.trim(),payment:g('txBPay').value,note:g('txBNote').value.trim()};if(force)body.force=1;
    if(!body.pickup){msg(g('txBMsg'),_t('txj.needPick2'),'err');return;}if(body.name.length<2){msg(g('txBMsg'),_t('txj.needName'),'err');return;}
    var bt=g('txBGo');bt.disabled=true;msg(g('txBMsg'),_t('txj.creating'),'info');
    api('/ride/create',{method:'POST',body:JSON.stringify(body)}).then(function(j){var r=j.ride;msg(g('txBMsg'),(j.duplicate?_t('txj.dup')+' ':_t('txj.created')+' ')+'<b>'+h(r.code)+'</b> · '+h(r.asap?_t('txj.asapEta',{t:_tx(r.eta_text)}):(I18N.lang!=='de'&&r.when_iso?I18N.dt(r.when_iso):r.when_long))+' · '+h(I18N.fx(_tx(r.price_text)))+' · <a href="'+h(r.track)+'" target="_blank" rel="noopener">'+_t('txj.statusPage')+'</a>','ok');
        g('txBName').value='';g('txBPhone').value='';g('txBNote').value='';g('txBLug').value='';g('txBForce').classList.add('hidden');loadRides(true);})
      .catch(function(e){var d=e.data||{};msg(g('txBMsg'),h((I18N.lang==='de'&&d.hint)||e.message),'err');g('txBForce').classList.toggle('hidden',d.error!=='outside_area');}).then(function(){bt.disabled=false;});}

  /* ---------- Verdrahtung ---------- */
  function bind(){
    var r=g('txRent');if(r&&typeof active==='function'&&active('agent-taxi')){r.className='tag ok';r.textContent=_t('txj.active');r.removeAttribute('href');r.removeAttribute('target');}
    g('txAddVeh').onclick=function(){addVeh({});};g('txAddFix').onclick=function(){addFix({});};g('txAddStand').onclick=function(){addStand({});};
    g('txAddDrv').onclick=function(){var e=g('txDrivers').querySelector('[data-empty]');if(e)e.remove();addDrv({});};
    g('txSave').onclick=function(){save(g('txSave'),g('txMsg'));};g('txSave2').onclick=function(){save(g('txSave2'),g('txMsg2'));};
    ['txBase','txKm1','txUpto','txKm2','txMin','txFee','txNight'].forEach(function(id){g(id).addEventListener('input',tariffEx);});
    g('txBoardCopy').onclick=function(){if(LINKS.board)copy(LINKS.board,g('txBoardMsg'),_t('od.boardCopied'));else msg(g('txBoardMsg'),_t('txj.saveFirst'),'info');};
    g('txReload').onclick=function(){loadRides();};g('txAll').onchange=function(){loadRides();};
    g('txList').addEventListener('click',act);g('txList').addEventListener('focusout',function(){setTimeout(function(){if(pendingRender)renderRides();},60);});
    g('txCalc').onclick=calc;['txCPick','txCDest','txCTime'].forEach(function(id){g(id).addEventListener('keydown',function(e){if(e.key==='Enter')calc();});});
    g('txBGo').onclick=function(){book(false);};g('txBForce').onclick=function(){if(confirm(_t('txj.forceQ')))book(true);};}
  window.STUDIO_PANELS=window.STUDIO_PANELS||{};
  window.STUDIO_PANELS.taxi={
    init:function(){bind();load();if(timer)clearInterval(timer);timer=setInterval(function(){if(visible()&&!busy)loadRides(true);},15000);},
    show:function(){if(!S)load();else loadRides(true);}
  };
})();
