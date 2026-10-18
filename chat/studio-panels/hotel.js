/* ChatHelp Studio — Panel „Hotel-Rezeption“: Einstellungen, Zimmerkategorien, Kennzahlen, Anfragen, Buchungen, Gästewünsche,
   Verfügbarkeit & manuelle Buchung, Rezeptions-Board. Nutzt die Studio-Globals api(), msg(), esc(), eurIn(), eurOut(), active(). */
(function(){
  'use strict';
  var PANEL='panel-hotel', TODAY='', DEMO_ROOMS=[], KINDS={}, timer=null, booking=null, busy=false, again=false;
  function el(id){ return document.getElementById(id); }
  function h(s){ return esc(s==null ? '' : s); }
  function visible(){ var p=el(PANEL); return !!p && !p.closest('.hidden'); }
  function errText(e){ var d=(e && e.data) || {}; return I18N.lang==='de' ? (d.hint || d.message || (e && e.message) || 'Fehler') : _te(e,_tx(d.hint)!==d.hint ? _tx(d.hint) : ''); }
  /* Statuswerte kommen als Code + deutsches Label: Übersetzung über den Code, sonst Quelltext-Wörterbuch */
  function lb(k, fb){ return I18N.has(k) ? _t(k) : _tx(fb); }
  function dS(ymd, fb){ if(I18N.lang==='de' || !ymd) return fb; return I18N.date(ymd+'T12:00:00',{weekday:'short',day:'numeric',month:'short'}); }
  function ymd(d){ return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); }
  function addDays(s,n){ var p=s.split('-'); var d=new Date(+p[0],+p[1]-1,+p[2]); d.setDate(d.getDate()+n); return ymd(d); }
  function today(){ return TODAY || ymd(new Date()); }
  function nights(n){ return n===1 ? _t('htj.night1') : _t('htj.nightN',{n:n}); }
  function guests(n){ return n===1 ? _t('htj.guest1') : _t('htj.guestN',{n:n}); }
  var STAG={requested:'rose',confirmed:'ok',checked_in:'teal',checked_out:'',cancelled:'',declined:'',no_show:'rose'};
  var KTAG={wakeup:'gold',taxi:'indigo',other:'rose',maintenance:'rose',late_checkout:'teal'};
  var ICON={housekeeping:'🧹',amenities:'🧺',roomservice:'🍽',wakeup:'⏰',maintenance:'🔧',taxi:'🚕',late_checkout:'🕐',info:'ℹ️',other:'💬'};

  /* ---------- Einstellungen ---------- */
  function fill(j){
    var s=j.settings||{}; KINDS=j.kinds||{}; DEMO_ROOMS=j.demo_rooms||[];
    el('htOn').checked=!!s.hotel_on; el('htName').value=s.name||''; el('htAddress').value=s.address||''; el('htPhone').value=s.phone||''; el('htNotify').value=s.notify_email||'';
    var tz=el('htTz'); if(s.tz && ![].slice.call(tz.options).some(function(o){ return o.value===s.tz; })){ var o=document.createElement('option'); o.textContent=s.tz; tz.appendChild(o); } tz.value=s.tz||'Europe/Berlin';
    el('htIn').value=s.checkin_from||'15:00'; el('htOut').value=s.checkout_until||'11:00'; el('htAuto').checked=!!s.auto_confirm;
    el('htBfMode').value=s.breakfast_mode||'none'; el('htBf').value=eurOut(s.breakfast_cents||0); el('htTax').value=String(s.city_tax_pct||0).replace('.',',');
    el('htMin').value=s.min_nights||1; el('htMax').value=s.max_nights||30; el('htCancel').value=s.cancel_hours!=null ? s.cancel_hours : 48; el('htPolicies').value=s.policies||'';
    bfUI(); el('htRooms').innerHTML=''; (j.rooms||[]).forEach(roomRow); if(!(j.rooms||[]).length) roomRow({});
    el('htRqKind').innerHTML=Object.keys(KINDS).map(function(k){ return '<option value="'+h(k)+'">'+h((ICON[k]||'')+' '+lb('htj.k.'+k,KINDS[k]))+'</option>'; }).join('');
    links(j.links||{}); stats(j.stats||{});
  }
  function bfUI(){ var m=el('htBfMode').value; el('htBf').disabled=m!=='optional'; el('htBf').style.opacity=m==='optional' ? '1' : '.5'; }
  function links(l){ var u=l.board||''; el('htBoard').textContent=u || _t('od.linkSave'); el('htBoardOpen').href=u || '#'; }
  function stats(st){
    if(st.today) TODAY=st.today;
    el('htKA').textContent=st.arrivals_today!=null ? st.arrivals_today : '–'; el('htKD').textContent=st.departures_today!=null ? st.departures_today : '–';
    el('htKH').textContent=st.in_house!=null ? st.in_house : '–'; el('htKR').textContent=st.open_requests!=null ? st.open_requests : '–'; el('htKP').textContent=st.pending_requests!=null ? st.pending_requests : '–';
    var t=el('htPendTag'); t.classList.toggle('hidden',!st.pending_requests); t.textContent=_t('htj.nOpen',{n:st.pending_requests||0});
    if(st.today){ var p=st.today.split('-'); el('htToday').textContent=I18N.date(new Date(+p[0],+p[1]-1,+p[2]),{weekday:'long',day:'numeric',month:'long'}); }
  }
  function roomRow(r){
    var d=document.createElement('div'); d.className='call'; d.style.marginTop='10px';
    d.innerHTML='<input type="hidden" class="rId" value="'+(+r.id||0)+'">'
      +'<div class="row" style="gap:8px"><input type="text" class="rName" placeholder="'+h(_t('htj.rNamePh'))+'" value="'+h(r.name)+'" style="flex:1;min-width:180px"><button class="btn s" type="button" title="'+h(_t('htj.rDel'))+'">✕</button></div>'
      +'<div class="grid g4" style="gap:8px"><div><label>'+_t('htj.cap')+'</label><input type="text" class="rCap" inputmode="numeric" value="'+(+r.capacity||2)+'"></div><div><label>'+_t('htj.units')+'</label><input type="text" class="rUnits" inputmode="numeric" value="'+(+r.units||1)+'"></div>'
      +'<div><label>'+_t('htj.perNight')+'</label><input type="text" class="rPrice" inputmode="decimal" value="'+eurOut(r.price_cents||0)+'"></div><div><label>'+_t('htj.we')+'</label><input type="text" class="rWe" inputmode="decimal" placeholder="'+h(_t('htj.asNormal'))+'" value="'+(r.weekend_cents ? eurOut(r.weekend_cents) : '')+'"></div></div>'
      +'<div class="grid g2" style="gap:8px"><div><label>'+_t('od.desc')+'</label><input type="text" class="rDesc" placeholder="'+h(_t('htj.descPh'))+'" value="'+h(r.description)+'"></div><div><label>'+_t('htj.am')+'</label><input type="text" class="rAm" placeholder="'+h(_t('htj.amPh'))+'" value="'+h(r.amenities)+'"></div></div>';
    d.querySelector('button').onclick=function(){ if(+r.id && !confirm(_t('htj.rDelQ',{n:d.querySelector('.rName').value.trim() || r.name || ''}))) return; d.remove(); };
    el('htRooms').appendChild(d);
  }
  function collect(){
    var rooms=[].slice.call(document.querySelectorAll('#htRooms .call')).map(function(r){
      var we=r.querySelector('.rWe').value.trim();
      return {id:+r.querySelector('.rId').value||0,name:r.querySelector('.rName').value.trim(),description:r.querySelector('.rDesc').value.trim(),amenities:r.querySelector('.rAm').value.trim(),
        capacity:Math.max(1,parseInt(r.querySelector('.rCap').value,10)||1),units:Math.max(1,parseInt(r.querySelector('.rUnits').value,10)||1),price_cents:eurIn(r.querySelector('.rPrice').value),weekend_cents:we ? eurIn(we) : null};
    }).filter(function(r){ return r.name; });
    return {hotel_on:el('htOn').checked ? 1 : 0,name:el('htName').value.trim(),address:el('htAddress').value.trim(),phone:el('htPhone').value.trim(),notify_email:el('htNotify').value.trim(),tz:el('htTz').value,
      checkin_from:el('htIn').value||'15:00',checkout_until:el('htOut').value||'11:00',auto_confirm:el('htAuto').checked ? 1 : 0,breakfast_mode:el('htBfMode').value,breakfast_cents:eurIn(el('htBf').value),
      city_tax_pct:parseFloat(String(el('htTax').value||'0').replace(',','.'))||0,min_nights:parseInt(el('htMin').value,10)||1,max_nights:parseInt(el('htMax').value,10)||30,
      cancel_hours:Math.max(0,parseInt(el('htCancel').value,10)||0),policies:el('htPolicies').value,rooms:rooms};
  }
  function save(){
    var d=collect(), m=el('htMsg');
    if(d.hotel_on && !d.rooms.length){ msg(m,_t('htj.needRoom'),'err'); return; }
    if(d.max_nights<d.min_nights){ msg(m,_t('htj.maxMin'),'err'); return; }
    el('htSave').disabled=true; msg(m,_t('c.saving'),'info');
    api('/hotel/settings',{method:'POST',body:JSON.stringify(d)}).then(function(j){ fill(j); msg(m,_t('c.saved')+' '+(j.settings && j.settings.hotel_on ? _t('htj.onTxt') : _t('htj.offTxt')),'ok'); refresh(); })
      .catch(function(e){ msg(m,h(errText(e)),'err'); }).then(function(){ el('htSave').disabled=false; });
  }

  /* ---------- Buchungen ---------- */
  function bookingLine(b, withActs){
    var t=today(), acts='';
    if(withActs){
      if(b.status==='requested') acts='<button class="btn s p" data-ht="confirm" data-id="'+b.id+'" type="button">'+_t('htj.confirm')+'</button><button class="btn s" data-ht="decline" data-id="'+b.id+'" type="button">'+_t('htj.decline')+'</button>';
      else if(b.status==='confirmed' && b.checkin<=t) acts='<input type="text" class="htRn" data-id="'+b.id+'" placeholder="'+h(_t('htj.roomNoPh'))+'" style="width:86px;padding:7px 10px"><button class="btn s p" data-ht="checkin" data-id="'+b.id+'" type="button">Check-in</button>'+(b.checkin<t ? '<button class="btn s" data-ht="noshow" data-id="'+b.id+'" type="button">'+_t('htj.noshow')+'</button>' : '')+'<button class="btn s ghost" data-ht="cancel" data-id="'+b.id+'" type="button">'+_t('htj.storno')+'</button>';
      else if(b.status==='confirmed') acts='<button class="btn s ghost" data-ht="cancel" data-id="'+b.id+'" type="button">'+_t('htj.storno')+'</button>';
      else if(b.status==='checked_in') acts='<button class="btn s p" data-ht="checkout" data-id="'+b.id+'" type="button">Check-out</button>';
    }
    return '<div class="call"><div class="row" style="justify-content:space-between;gap:10px;align-items:flex-start"><div style="flex:1;min-width:200px">'
      +'<b>'+h(b.name)+'</b> <span class="tag '+(STAG[b.status]||'')+'">'+h(lb('htj.st.'+b.status,b.status_label))+(b.room_no && (b.status==='checked_in'||b.status==='checked_out') ? ' · '+h(_t('htj.roomShort',{n:b.room_no})) : '')+'</span>'
      +'<div style="margin-top:4px">'+h(dS(b.checkin,b.checkin_short))+' → '+h(dS(b.checkout,b.checkout_short))+' · '+nights(b.nights)+' · '+h(b.room)+' · '+guests(b.guests)+(b.breakfast ? ' · '+_t('htj.bf') : '')+' · <b>'+h(I18N.fx(b.total))+'</b></div>'
      +(b.note ? '<div class="mute">📝 '+h(b.note)+'</div>' : '')
      +'<div class="mute">'+(b.phone ? h(b.phone)+' · ' : '')+(b.email ? h(b.email)+' · ' : '')+h(_t('c.code',{c:b.code}))+' · '+h(b.channel)+' · <a href="'+h(b.stay_url)+'" target="_blank" rel="noopener">'+_t('htj.guestPage')+'</a></div>'
      +'</div>'+(acts ? '<div class="row" style="gap:6px">'+acts+'</div>' : '')+'</div></div>';
  }
  function loadPending(){
    var t=today();
    return api('/hotel/bookings?status=requested&from='+t+'&to='+addDays(t,730)).then(function(j){
      var list=(j.bookings||[]).filter(function(b){ return b.checkout>t; });
      el('htPending').innerHTML=list.length ? list.map(function(b){ return bookingLine(b,true); }).join('') : '<div class="mute">'+_t('htj.noPend')+'</div>';
    }).catch(function(e){ el('htPending').innerHTML='<span class="mute">'+h(errText(e))+'</span>'; });
  }
  function loadUpcoming(){
    var t=today(), all=el('htUpAll').checked;
    return api('/hotel/bookings?from='+t+'&to='+addDays(t,14)).then(function(j){
      var list=(j.bookings||[]).filter(function(b){ return all || ['requested','confirmed','checked_in'].indexOf(b.status)>=0; });
      if(document.activeElement && document.activeElement.classList.contains('htRn')) return;   // Eingabe der Zimmernummer nicht überschreiben
      el('htUpcoming').innerHTML=list.length ? list.map(function(b){ return bookingLine(b,true); }).join('') : '<div class="mute">'+_t('htj.noUp')+'</div>';
    }).catch(function(e){ el('htUpcoming').innerHTML='<span class="mute">'+h(errText(e))+'</span>'; });
  }
  function bookingAction(btn){
    var id=+btn.dataset.id, act=btn.dataset.ht, body={id:id,action:act}, m=el('htUpMsg');
    if(act==='checkin'){ var inp=btn.parentNode.querySelector('.htRn'); var no=inp ? inp.value.trim() : ''; if(!no){ if(inp) inp.focus(); msg(m,_t('htj.needNo'),'err'); return; } body.room_no=no; }
    var ask={decline:_t('htj.declineQ'),cancel:_t('htj.cancelQ'),noshow:_t('htj.noshowQ')}[act];
    if(ask && !confirm(ask)) return;
    btn.disabled=true;
    api('/hotel/booking',{method:'POST',body:JSON.stringify(body)}).then(function(j){
      var lbl={confirm:_t('htj.a.confirm'),decline:_t('htj.a.decline'),checkin:_t('htj.a.checkin'),checkout:_t('htj.a.checkout'),cancel:_t('htj.a.cancel'),noshow:_t('htj.a.noshow')}[act];
      msg(m,h(String(lbl).replace('{c}',j.booking ? j.booking.code : '')),'ok'); refresh();
    }).catch(function(e){ msg(m,h(errText(e)),'err'); btn.disabled=false; });
  }

  /* ---------- Gästewünsche ---------- */
  function reqLine(r){
    var acts='';
    if(r.status==='open') acts='<button class="btn s" data-hr="in_progress" data-id="'+r.id+'" type="button">'+_t('htj.take')+'</button><button class="btn s p" data-hr="done" data-id="'+r.id+'" type="button">'+_t('htj.doneBtn')+'</button>';
    else if(r.status==='in_progress') acts='<button class="btn s p" data-hr="done" data-id="'+r.id+'" type="button">'+_t('htj.doneBtn')+'</button><button class="btn s ghost" data-hr="open" data-id="'+r.id+'" type="button" title="'+h(_t('htj.backOpen'))+'">↺</button>';
    else acts='<button class="btn s ghost" data-hr="open" data-id="'+r.id+'" type="button">'+_t('htj.reopen')+'</button>';
    if(r.status==='open' || r.status==='in_progress') acts+='<button class="btn s ghost" data-hr="cancelled" data-id="'+r.id+'" type="button" title="'+h(_t('od.a.cancel'))+'">✕</button>';
    return '<div class="call"'+(r.status==='done'||r.status==='cancelled' ? ' style="opacity:.6"' : '')+'><div class="row" style="justify-content:space-between;gap:10px;align-items:flex-start"><div style="flex:1;min-width:200px">'
      +'<span class="tag '+(KTAG[r.kind]||'')+'">'+h((ICON[r.kind]||'')+' '+lb('htj.k.'+r.kind,r.kind_label))+'</span> '+(r.room_no ? '<b>'+h(_t('htj.room',{n:r.room_no}))+'</b>' : '')+(r.due_human ? ' <span class="mute">· ⏱ '+h(I18N.lang!=='de' && r.due_iso ? I18N.dt(r.due_iso,{weekday:'short',day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'}) : r.due_human)+'</span>' : '')
      +(String(r.details).trim().toLowerCase()!==String(r.kind_label).toLowerCase() ? '<div style="margin-top:5px;font-size:14px">'+h(r.details)+'</div>' : '')
      +'<div class="mute">'+h(lb('htj.rq.'+r.status,r.status_label))+' · '+(r.guest_name ? h(r.guest_name)+' · ' : '')+(r.contact ? h(r.contact)+' · ' : '')+h(r.channel)+' · '+h(I18N.lang!=='de' && r.created_iso ? I18N.dt(r.created_iso,{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'}) : r.created_day+' '+_t('od.at',{t:r.created}))+(r.ride_url ? ' · <a href="'+h(r.ride_url)+'" target="_blank" rel="noopener">'+h(_t('htj.ride',{r:r.ref}))+'</a>' : '')+'</div>'
      +'</div><div class="row" style="gap:6px">'+acts+'</div></div></div>';
  }
  function loadReqs(){
    return api('/hotel/requests?scope='+encodeURIComponent(el('htReqScope').value)).then(function(j){
      var list=j.requests||[]; el('htReqs').innerHTML=list.length ? list.map(reqLine).join('') : '<div class="mute">'+_t('htj.noReq')+'</div>';
    }).catch(function(e){ el('htReqs').innerHTML='<span class="mute">'+h(errText(e))+'</span>'; });
  }
  function reqStatus(btn){
    btn.disabled=true;
    api('/hotel/request',{method:'POST',body:JSON.stringify({id:+btn.dataset.id,status:btn.dataset.hr})}).then(function(){ refresh(); }).catch(function(e){ alert(errText(e)); btn.disabled=false; });
  }
  function reqAdd(){
    var m=el('htRqMsg'), d={room_no:el('htRqRoom').value.trim(),kind:el('htRqKind').value,details:el('htRqDet').value.trim(),due_time:el('htRqDue').value.trim(),guest_name:el('htRqName').value.trim()};
    if(!d.details && d.kind!=='wakeup'){ msg(m,_t('htj.descNeed'),'err'); return; }
    api('/hotel/request/new',{method:'POST',body:JSON.stringify(d)}).then(function(j){
      msg(m,h(I18N.lang==='de' ? (j.message||'Angelegt ✓') : _t('htj.created')),'ok'); ['htRqRoom','htRqDet','htRqDue','htRqName'].forEach(function(i){ el(i).value=''; }); refresh();
    }).catch(function(e){ msg(m,h(errText(e)),'err'); });
  }

  /* ---------- Verfügbarkeit & manuelle Buchung ---------- */
  function avail(){
    var ci=el('htAvIn').value, co=el('htAvOut').value, g=el('htAvG').value, out=el('htAvRes');
    if(!ci || !co){ out.innerHTML='<span class="mute">'+_t('htj.pickDates')+'</span>'; return; }
    out.textContent=_t('od.checking'); el('htBkBox').classList.add('hidden');
    api('/hotel/availability?checkin='+encodeURIComponent(ci)+'&checkout='+encodeURIComponent(co)+'&guests='+encodeURIComponent(g)).then(function(j){
      var head='<div class="mute" style="margin-bottom:6px">'+h(dS(j.checkin,j.checkin_human))+' → '+h(dS(j.checkout,j.checkout_human))+' · '+nights(j.nights)+' · '+guests(j.guests)+'</div>';
      if(!(j.options||[]).length){ out.innerHTML=head+'<div class="msg err">'+h(I18N.lang==='de' ? (j.hint||'Nichts frei.') : (_tx(j.hint)!==j.hint && j.hint ? _tx(j.hint) : _t('htj.nothing')))+'</div>'+(j.sold_out && j.sold_out.length ? '<div class="mute">'+h(_t('htj.soldOut',{l:j.sold_out.join(', ')}))+'</div>' : ''); return; }
      out.innerHTML=head+j.options.map(function(o,i){
        var nl=(o.nightly||[]).map(function(n){ return h(I18N.lang!=='de' && n.date ? dS(n.date,n.label) : n.label)+' '+h(I18N.fx(n.price)); }).join(' · ');
        return '<div class="call"><div class="row" style="justify-content:space-between;gap:10px"><div style="flex:1;min-width:180px"><b>'+h(o.room)+'</b> <span class="tag '+(o.last_units ? 'rose' : 'ok')+'">'+h(o.last_units ? _t('htj.lastFree',{n:o.free_units}) : _t('htj.free',{n:o.free_units}))+'</span>'
          +'<div class="mute">'+h(_t('htj.capAvg',{c:o.capacity,a:I18N.fx(o.avg_night)}))+(o.city_tax_cents ? ' · '+h(_t('htj.cityTax',{p:I18N.eur(o.city_tax_cents/100)})) : '')+'</div><div class="mute" style="font-size:12px">'+nl+'</div></div>'
          +'<div style="text-align:right"><div style="font:800 18px var(--sans)">'+h(I18N.fx(o.total))+'</div>'+(o.with_breakfast_total ? '<div class="mute">'+h(_t('htj.withBf',{p:I18N.fx(o.with_breakfast_total)}))+'</div>' : '')+'<button class="btn s p" data-hb="'+i+'" type="button" style="margin-top:6px">'+_t('htj.book')+'</button></div></div></div>';
      }).join('')+(j.sold_out && j.sold_out.length ? '<div class="mute" style="margin-top:6px">'+h(_t('htj.soldOut',{l:j.sold_out.join(', ')}))+'</div>' : '');
      booking={res:j};
    }).catch(function(e){ out.innerHTML='<div class="msg err">'+h(errText(e))+'</div>'; });
  }
  function openBook(i){
    if(!booking || !booking.res) return; var o=booking.res.options[i], j=booking.res; booking.opt=o;
    el('htBkTitle').textContent=_t('htj.bkTitle',{r:o.room,a:dS(j.checkin,j.checkin_human),b:dS(j.checkout,j.checkout_human)});
    var bf=j.breakfast_mode==='optional' && o.with_breakfast_total; el('htBkBfRow').classList.toggle('hidden',!bf); el('htBkBf').checked=false;
    el('htBkBfTxt').textContent=bf ? _t('htj.bfTxt',{p:I18N.fx(j.breakfast_per_person_night),t:I18N.fx(o.with_breakfast_total)}) : '';
    msg(el('htBkMsg'),'',''); el('htBkBox').classList.remove('hidden'); el('htBkName').focus();
  }
  function doBook(){
    if(!booking || !booking.opt) return; var j=booking.res, m=el('htBkMsg');
    var d={room_id:booking.opt.room_id,checkin:j.checkin,checkout:j.checkout,guests:j.guests,name:el('htBkName').value.trim(),email:el('htBkMail').value.trim(),phone:el('htBkPhone').value.trim(),note:el('htBkNote').value.trim(),breakfast:el('htBkBf').checked};
    if(!d.name){ msg(m,_t('htj.needName'),'err'); return; }
    if(!d.email && !d.phone){ msg(m,_t('htj.needContact'),'err'); return; }
    el('htBkGo').disabled=true;
    api('/hotel/book',{method:'POST',body:JSON.stringify(d)}).then(function(r){
      var b=r.booking||{};
      msg(m,_t('htj.booked',{c:'<b>'+h(b.code)+'</b>'})+' · '+h(I18N.fx(b.total))+(d.email ? ' · '+h(_t('htj.confTo',{e:d.email})) : '')+' · <a href="'+h(b.stay_url)+'" target="_blank" rel="noopener">'+_t('htj.guestPage')+'</a>','ok');
      ['htBkName','htBkMail','htBkPhone','htBkNote'].forEach(function(i){ el(i).value=''; }); booking.opt=null; refresh();
    }).catch(function(e){ var a=(e.data && e.data.alternatives) || []; msg(m,h(errText(e))+(a.length ? '<br>'+h(_t('htj.alts'))+' '+a.map(function(x){ return h(x.room)+' ('+h(I18N.fx(x.total))+')'; }).join(', ') : ''),'err'); })
      .then(function(){ el('htBkGo').disabled=false; });
  }

  /* ---------- Board-Link ---------- */
  function copy(){
    var u=el('htBoard').textContent, m=el('htBoardMsg'); if(!/^https?:/.test(u)) return;
    var ok=function(){ msg(m,_t('c.linkCopiedOk'),'ok'); setTimeout(function(){ msg(m,'',''); },2500); };
    if(navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(u).then(ok).catch(function(){ prompt(_t('c.copyLinkP'),u); });
    else prompt(_t('c.copyLinkP'),u);
  }
  function newToken(){
    if(!confirm(_t('htj.newTokQ'))) return;
    api('/hotel/settings',{method:'POST',body:JSON.stringify({new_board_token:1})}).then(function(j){ links(j.links||{}); msg(el('htBoardMsg'),_t('htj.newTok'),'ok'); })
      .catch(function(e){ msg(el('htBoardMsg'),h(errText(e)),'err'); });
  }

  /* ---------- Live-Daten ---------- */
  function refresh(){   // läuft bereits eine Aktualisierung, wird danach genau einmal nachgeladen
    if(busy){ again=true; return Promise.resolve(); } busy=true;
    var done=function(){ busy=false; if(again){ again=false; refresh(); } };
    return api('/hotel/settings').then(function(j){ stats(j.stats||{}); links(j.links||{}); })
      .catch(function(){}).then(function(){ return Promise.all([loadPending(),loadUpcoming(),loadReqs()]); }).then(done,done);
  }
  function plan(){ try{ var on=typeof active==='function' && (active('agent-hotel') || active('buy-hotel')); el('htActive').classList.toggle('hidden',!on); el('htRent').classList.toggle('hidden',!!on); }catch(e){} }
  function wire(){
    el('htSave').onclick=save; el('htAddRoom').onclick=function(){ roomRow({}); };
    el('htDemoRooms').onclick=function(){ if([].slice.call(document.querySelectorAll('#htRooms .rName')).some(function(i){ return i.value.trim(); }) && !confirm(_t('htj.demoQ'))) return; el('htRooms').innerHTML=''; DEMO_ROOMS.forEach(roomRow); };
    el('htBfMode').onchange=bfUI; el('htReload').onclick=refresh; el('htUpAll').onchange=loadUpcoming; el('htReqScope').onchange=loadReqs; el('htRqAdd').onclick=reqAdd;
    el('htAvGo').onclick=avail; el('htBkGo').onclick=doBook; el('htBkX').onclick=function(){ el('htBkBox').classList.add('hidden'); };
    el('htAvIn').onchange=function(){ if(el('htAvIn').value && (!el('htAvOut').value || el('htAvOut').value<=el('htAvIn').value)) el('htAvOut').value=addDays(el('htAvIn').value,1); };
    el('htBoardCopy').onclick=copy; el('htBoardNew').onclick=newToken;
    el('htBoardOpen').onclick=function(e){ if(el('htBoardOpen').getAttribute('href')==='#'){ e.preventDefault(); msg(el('htBoardMsg'),_t('c.saveFirst'),'info'); } };
    el(PANEL).addEventListener('click',function(e){
      var b=e.target.closest('button[data-ht]'); if(b){ bookingAction(b); return; }
      var r=e.target.closest('button[data-hr]'); if(r){ reqStatus(r); return; }
      var k=e.target.closest('button[data-hb]'); if(k){ openBook(+k.dataset.hb); }
    });
    el(PANEL).addEventListener('keydown',function(e){ if(e.key==='Enter' && e.target.classList.contains('htRn')){ var c=e.target.parentNode.querySelector('[data-ht="checkin"]'); if(c) c.click(); } });
  }
  
  
  
  window.STUDIO_PANELS=window.STUDIO_PANELS||{};
  window.STUDIO_PANELS['hotel']={
    init:function(){
      wire(); plan();
      var t=ymd(new Date()); el('htAvIn').value=addDays(t,7); el('htAvOut').value=addDays(t,9);
      api('/hotel/settings').then(function(j){ fill(j); return refresh(); }).catch(function(e){ msg(el('htMsg'),h(errText(e)),'err'); });
      if(!timer) timer=setInterval(function(){ if(visible() && !document.hidden) refresh(); },15000);
    },
    show:function(){ plan(); refresh(); }
  };
})();
