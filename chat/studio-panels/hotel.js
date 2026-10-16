/* ChatHelp Studio — Panel „Hotel-Rezeption“: Einstellungen, Zimmerkategorien, Kennzahlen, Anfragen, Buchungen, Gästewünsche,
   Verfügbarkeit & manuelle Buchung, Rezeptions-Board. Nutzt die Studio-Globals api(), msg(), esc(), eurIn(), eurOut(), active(). */
(function(){
  'use strict';
  var PANEL='panel-hotel', TODAY='', DEMO_ROOMS=[], KINDS={}, timer=null, booking=null, busy=false, again=false;
  function el(id){ return document.getElementById(id); }
  function h(s){ return esc(s==null ? '' : s); }
  function visible(){ var p=el(PANEL); return !!p && !p.closest('.hidden'); }
  function errText(e){ var d=(e && e.data) || {}; return d.hint || d.message || (e && e.message) || 'Fehler'; }
  function ymd(d){ return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); }
  function addDays(s,n){ var p=s.split('-'); var d=new Date(+p[0],+p[1]-1,+p[2]); d.setDate(d.getDate()+n); return ymd(d); }
  function today(){ return TODAY || ymd(new Date()); }
  function nights(n){ return n+' '+(n===1 ? 'Nacht' : 'Nächte'); }
  function guests(n){ return n+' '+(n===1 ? 'Gast' : 'Gäste'); }
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
    el('htRqKind').innerHTML=Object.keys(KINDS).map(function(k){ return '<option value="'+h(k)+'">'+h((ICON[k]||'')+' '+KINDS[k])+'</option>'; }).join('');
    links(j.links||{}); stats(j.stats||{});
  }
  function bfUI(){ var m=el('htBfMode').value; el('htBf').disabled=m!=='optional'; el('htBf').style.opacity=m==='optional' ? '1' : '.5'; }
  function links(l){ var u=l.board||''; el('htBoard').textContent=u || 'Speichern, um den Link zu erhalten.'; el('htBoardOpen').href=u || '#'; }
  function stats(st){
    if(st.today) TODAY=st.today;
    el('htKA').textContent=st.arrivals_today!=null ? st.arrivals_today : '–'; el('htKD').textContent=st.departures_today!=null ? st.departures_today : '–';
    el('htKH').textContent=st.in_house!=null ? st.in_house : '–'; el('htKR').textContent=st.open_requests!=null ? st.open_requests : '–'; el('htKP').textContent=st.pending_requests!=null ? st.pending_requests : '–';
    var t=el('htPendTag'); t.classList.toggle('hidden',!st.pending_requests); t.textContent=(st.pending_requests||0)+' offen';
    if(st.today){ var p=st.today.split('-'); el('htToday').textContent=new Date(+p[0],+p[1]-1,+p[2]).toLocaleDateString('de-DE',{weekday:'long',day:'numeric',month:'long'}); }
  }
  function roomRow(r){
    var d=document.createElement('div'); d.className='call'; d.style.marginTop='10px';
    d.innerHTML='<input type="hidden" class="rId" value="'+(+r.id||0)+'">'
      +'<div class="row" style="gap:8px"><input type="text" class="rName" placeholder="Name, z. B. Doppelzimmer Komfort" value="'+h(r.name)+'" style="flex:1;min-width:180px"><button class="btn s" type="button" title="Kategorie entfernen">✕</button></div>'
      +'<div class="grid g4" style="gap:8px"><div><label>Personen max.</label><input type="text" class="rCap" inputmode="numeric" value="'+(+r.capacity||2)+'"></div><div><label>Anzahl Zimmer</label><input type="text" class="rUnits" inputmode="numeric" value="'+(+r.units||1)+'"></div>'
      +'<div><label>€ pro Nacht</label><input type="text" class="rPrice" inputmode="decimal" value="'+eurOut(r.price_cents||0)+'"></div><div><label>€ Fr + Sa</label><input type="text" class="rWe" inputmode="decimal" placeholder="wie normal" value="'+(r.weekend_cents ? eurOut(r.weekend_cents) : '')+'"></div></div>'
      +'<div class="grid g2" style="gap:8px"><div><label>Beschreibung</label><input type="text" class="rDesc" placeholder="z. B. 24 m², Kingsize-Bett, Alsterblick" value="'+h(r.description)+'"></div><div><label>Ausstattung</label><input type="text" class="rAm" placeholder="WLAN, Minibar, Balkon" value="'+h(r.amenities)+'"></div></div>';
    d.querySelector('button').onclick=function(){ d.remove(); };
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
    if(d.hotel_on && !d.rooms.length){ msg(m,'Bitte mindestens eine Zimmerkategorie anlegen, bevor die Rezeption aktiviert wird.','err'); return; }
    if(d.max_nights<d.min_nights){ msg(m,'Höchstaufenthalt muss mindestens so lang sein wie der Mindestaufenthalt.','err'); return; }
    el('htSave').disabled=true; msg(m,'Speichere …','info');
    api('/hotel/settings',{method:'POST',body:JSON.stringify(d)}).then(function(j){ fill(j); msg(m,'Gespeichert ✓ '+(j.settings && j.settings.hotel_on ? 'Die Rezeption ist aktiv – Ihre Agenten nutzen ab sofort die Hotel-Werkzeuge.' : 'Die Rezeption ist noch nicht aktiv.'),'ok'); refresh(); })
      .catch(function(e){ msg(m,h(errText(e)),'err'); }).then(function(){ el('htSave').disabled=false; });
  }

  /* ---------- Buchungen ---------- */
  function bookingLine(b, withActs){
    var t=today(), acts='';
    if(withActs){
      if(b.status==='requested') acts='<button class="btn s p" data-ht="confirm" data-id="'+b.id+'" type="button">✓ Bestätigen</button><button class="btn s" data-ht="decline" data-id="'+b.id+'" type="button">Ablehnen</button>';
      else if(b.status==='confirmed' && b.checkin<=t) acts='<input type="text" class="htRn" data-id="'+b.id+'" placeholder="Zi.-Nr." style="width:86px;padding:7px 10px"><button class="btn s p" data-ht="checkin" data-id="'+b.id+'" type="button">Check-in</button>'+(b.checkin<t ? '<button class="btn s" data-ht="noshow" data-id="'+b.id+'" type="button">No-Show</button>' : '')+'<button class="btn s ghost" data-ht="cancel" data-id="'+b.id+'" type="button">Storno</button>';
      else if(b.status==='confirmed') acts='<button class="btn s ghost" data-ht="cancel" data-id="'+b.id+'" type="button">Storno</button>';
      else if(b.status==='checked_in') acts='<button class="btn s p" data-ht="checkout" data-id="'+b.id+'" type="button">Check-out</button>';
    }
    return '<div class="call"><div class="row" style="justify-content:space-between;gap:10px;align-items:flex-start"><div style="flex:1;min-width:200px">'
      +'<b>'+h(b.name)+'</b> <span class="tag '+(STAG[b.status]||'')+'">'+h(b.status_label)+(b.room_no && (b.status==='checked_in'||b.status==='checked_out') ? ' · Zi. '+h(b.room_no) : '')+'</span>'
      +'<div style="margin-top:4px">'+h(b.checkin_short)+' → '+h(b.checkout_short)+' · '+nights(b.nights)+' · '+h(b.room)+' · '+guests(b.guests)+(b.breakfast ? ' · Frühstück' : '')+' · <b>'+h(b.total)+'</b></div>'
      +(b.note ? '<div class="mute">📝 '+h(b.note)+'</div>' : '')
      +'<div class="mute">'+(b.phone ? h(b.phone)+' · ' : '')+(b.email ? h(b.email)+' · ' : '')+'Code '+h(b.code)+' · '+h(b.channel)+' · <a href="'+h(b.stay_url)+'" target="_blank" rel="noopener">Gästeseite ↗</a></div>'
      +'</div>'+(acts ? '<div class="row" style="gap:6px">'+acts+'</div>' : '')+'</div></div>';
  }
  function loadPending(){
    var t=today();
    return api('/hotel/bookings?status=requested&from='+t+'&to='+addDays(t,730)).then(function(j){
      var list=(j.bookings||[]).filter(function(b){ return b.checkout>t; });
      el('htPending').innerHTML=list.length ? list.map(function(b){ return bookingLine(b,true); }).join('') : '<div class="mute">Keine offenen Anfragen.</div>';
    }).catch(function(e){ el('htPending').innerHTML='<span class="mute">'+h(errText(e))+'</span>'; });
  }
  function loadUpcoming(){
    var t=today(), all=el('htUpAll').checked;
    return api('/hotel/bookings?from='+t+'&to='+addDays(t,14)).then(function(j){
      var list=(j.bookings||[]).filter(function(b){ return all || ['requested','confirmed','checked_in'].indexOf(b.status)>=0; });
      if(document.activeElement && document.activeElement.classList.contains('htRn')) return;   // Eingabe der Zimmernummer nicht überschreiben
      el('htUpcoming').innerHTML=list.length ? list.map(function(b){ return bookingLine(b,true); }).join('') : '<div class="mute">Keine Buchungen in den nächsten 14 Tagen.</div>';
    }).catch(function(e){ el('htUpcoming').innerHTML='<span class="mute">'+h(errText(e))+'</span>'; });
  }
  function bookingAction(btn){
    var id=+btn.dataset.id, act=btn.dataset.ht, body={id:id,action:act}, m=el('htUpMsg');
    if(act==='checkin'){ var inp=btn.parentNode.querySelector('.htRn'); var no=inp ? inp.value.trim() : ''; if(!no){ if(inp) inp.focus(); msg(m,'Bitte die Zimmernummer eintragen.','err'); return; } body.room_no=no; }
    var ask={decline:'Anfrage wirklich ablehnen? Der Gast wird informiert.',cancel:'Buchung wirklich stornieren? Der Gast wird informiert.',noshow:'Gast als „nicht angereist“ markieren?'}[act];
    if(ask && !confirm(ask)) return;
    btn.disabled=true;
    api('/hotel/booking',{method:'POST',body:JSON.stringify(body)}).then(function(j){
      var lbl={confirm:'bestätigt',decline:'abgelehnt',checkin:'eingecheckt',checkout:'ausgecheckt',cancel:'storniert',noshow:'als No-Show markiert'}[act];
      msg(m,'Buchung '+h(j.booking ? j.booking.code : '')+' '+lbl+' ✓','ok'); refresh();
    }).catch(function(e){ msg(m,h(errText(e)),'err'); btn.disabled=false; });
  }

  /* ---------- Gästewünsche ---------- */
  function reqLine(r){
    var acts='';
    if(r.status==='open') acts='<button class="btn s" data-hr="in_progress" data-id="'+r.id+'" type="button">Übernehmen</button><button class="btn s p" data-hr="done" data-id="'+r.id+'" type="button">✓ Erledigt</button>';
    else if(r.status==='in_progress') acts='<button class="btn s p" data-hr="done" data-id="'+r.id+'" type="button">✓ Erledigt</button><button class="btn s ghost" data-hr="open" data-id="'+r.id+'" type="button" title="zurück auf offen">↺</button>';
    else acts='<button class="btn s ghost" data-hr="open" data-id="'+r.id+'" type="button">Wieder öffnen</button>';
    if(r.status==='open' || r.status==='in_progress') acts+='<button class="btn s ghost" data-hr="cancelled" data-id="'+r.id+'" type="button" title="stornieren">✕</button>';
    return '<div class="call"'+(r.status==='done'||r.status==='cancelled' ? ' style="opacity:.6"' : '')+'><div class="row" style="justify-content:space-between;gap:10px;align-items:flex-start"><div style="flex:1;min-width:200px">'
      +'<span class="tag '+(KTAG[r.kind]||'')+'">'+h((ICON[r.kind]||'')+' '+r.kind_label)+'</span> '+(r.room_no ? '<b>Zimmer '+h(r.room_no)+'</b>' : '')+(r.due_human ? ' <span class="mute">· ⏱ '+h(r.due_human)+'</span>' : '')
      +'<div style="margin-top:5px;font-size:14px">'+h(r.details)+'</div>'
      +'<div class="mute">'+h(r.status_label)+' · '+(r.guest_name ? h(r.guest_name)+' · ' : '')+(r.contact ? h(r.contact)+' · ' : '')+h(r.channel)+' · '+h(r.created_day)+' '+h(r.created)+' Uhr'+(r.ride_url ? ' · <a href="'+h(r.ride_url)+'" target="_blank" rel="noopener">Fahrt '+h(r.ref)+' ↗</a>' : '')+'</div>'
      +'</div><div class="row" style="gap:6px">'+acts+'</div></div></div>';
  }
  function loadReqs(){
    return api('/hotel/requests?scope='+encodeURIComponent(el('htReqScope').value)).then(function(j){
      var list=j.requests||[]; el('htReqs').innerHTML=list.length ? list.map(reqLine).join('') : '<div class="mute">Keine offenen Wünsche – alles erledigt.</div>';
    }).catch(function(e){ el('htReqs').innerHTML='<span class="mute">'+h(errText(e))+'</span>'; });
  }
  function reqStatus(btn){
    btn.disabled=true;
    api('/hotel/request',{method:'POST',body:JSON.stringify({id:+btn.dataset.id,status:btn.dataset.hr})}).then(function(){ refresh(); }).catch(function(e){ alert(errText(e)); btn.disabled=false; });
  }
  function reqAdd(){
    var m=el('htRqMsg'), d={room_no:el('htRqRoom').value.trim(),kind:el('htRqKind').value,details:el('htRqDet').value.trim(),due_time:el('htRqDue').value.trim(),guest_name:el('htRqName').value.trim()};
    if(!d.details && d.kind!=='wakeup'){ msg(m,'Bitte beschreiben, was gewünscht wird.','err'); return; }
    api('/hotel/request/new',{method:'POST',body:JSON.stringify(d)}).then(function(j){
      msg(m,h(j.message||'Angelegt ✓'),'ok'); ['htRqRoom','htRqDet','htRqDue','htRqName'].forEach(function(i){ el(i).value=''; }); refresh();
    }).catch(function(e){ msg(m,h(errText(e)),'err'); });
  }

  /* ---------- Verfügbarkeit & manuelle Buchung ---------- */
  function avail(){
    var ci=el('htAvIn').value, co=el('htAvOut').value, g=el('htAvG').value, out=el('htAvRes');
    if(!ci || !co){ out.innerHTML='<span class="mute">Bitte Anreise und Abreise wählen.</span>'; return; }
    out.textContent='prüfe …'; el('htBkBox').classList.add('hidden');
    api('/hotel/availability?checkin='+encodeURIComponent(ci)+'&checkout='+encodeURIComponent(co)+'&guests='+encodeURIComponent(g)).then(function(j){
      var head='<div class="mute" style="margin-bottom:6px">'+h(j.checkin_human)+' → '+h(j.checkout_human)+' · '+nights(j.nights)+' · '+guests(j.guests)+'</div>';
      if(!(j.options||[]).length){ out.innerHTML=head+'<div class="msg err">'+h(j.hint||'Nichts frei.')+'</div>'+(j.sold_out && j.sold_out.length ? '<div class="mute">Ausgebucht: '+h(j.sold_out.join(', '))+'</div>' : ''); return; }
      out.innerHTML=head+j.options.map(function(o,i){
        var nl=(o.nightly||[]).map(function(n){ return h(n.label)+' '+h(n.price); }).join(' · ');
        return '<div class="call"><div class="row" style="justify-content:space-between;gap:10px"><div style="flex:1;min-width:180px"><b>'+h(o.room)+'</b> <span class="tag '+(o.last_units ? 'rose' : 'ok')+'">'+(o.last_units ? 'nur noch '+o.free_units+' frei' : o.free_units+' frei')+'</span>'
          +'<div class="mute">bis '+o.capacity+' Pers. · Ø '+h(o.avg_night)+' / Nacht'+(o.city_tax_cents ? ' · inkl. City-Tax '+h(eurOut(o.city_tax_cents))+' €' : '')+'</div><div class="mute" style="font-size:12px">'+nl+'</div></div>'
          +'<div style="text-align:right"><div style="font:800 18px var(--sans)">'+h(o.total)+'</div>'+(o.with_breakfast_total ? '<div class="mute">mit Frühstück '+h(o.with_breakfast_total)+'</div>' : '')+'<button class="btn s p" data-hb="'+i+'" type="button" style="margin-top:6px">Buchen</button></div></div></div>';
      }).join('')+(j.sold_out && j.sold_out.length ? '<div class="mute" style="margin-top:6px">Ausgebucht: '+h(j.sold_out.join(', '))+'</div>' : '');
      booking={res:j};
    }).catch(function(e){ out.innerHTML='<div class="msg err">'+h(errText(e))+'</div>'; });
  }
  function openBook(i){
    if(!booking || !booking.res) return; var o=booking.res.options[i], j=booking.res; booking.opt=o;
    el('htBkTitle').textContent='Buchung: '+o.room+' · '+j.checkin_human+' → '+j.checkout_human;
    var bf=j.breakfast_mode==='optional' && o.with_breakfast_total; el('htBkBfRow').classList.toggle('hidden',!bf); el('htBkBf').checked=false;
    el('htBkBfTxt').textContent=bf ? (j.breakfast_per_person_night+' pro Person und Nacht · gesamt dann '+o.with_breakfast_total) : '';
    msg(el('htBkMsg'),'',''); el('htBkBox').classList.remove('hidden'); el('htBkName').focus();
  }
  function doBook(){
    if(!booking || !booking.opt) return; var j=booking.res, m=el('htBkMsg');
    var d={room_id:booking.opt.room_id,checkin:j.checkin,checkout:j.checkout,guests:j.guests,name:el('htBkName').value.trim(),email:el('htBkMail').value.trim(),phone:el('htBkPhone').value.trim(),note:el('htBkNote').value.trim(),breakfast:el('htBkBf').checked};
    if(!d.name){ msg(m,'Bitte den Namen des Gastes eintragen.','err'); return; }
    if(!d.email && !d.phone){ msg(m,'Bitte E-Mail oder Telefon eintragen.','err'); return; }
    el('htBkGo').disabled=true;
    api('/hotel/book',{method:'POST',body:JSON.stringify(d)}).then(function(r){
      var b=r.booking||{};
      msg(m,'Gebucht ✓ Code <b>'+h(b.code)+'</b> · '+h(b.total)+(d.email ? ' · Bestätigung an '+h(d.email) : '')+' · <a href="'+h(b.stay_url)+'" target="_blank" rel="noopener">Gästeseite ↗</a>','ok');
      ['htBkName','htBkMail','htBkPhone','htBkNote'].forEach(function(i){ el(i).value=''; }); booking.opt=null; refresh();
    }).catch(function(e){ var a=(e.data && e.data.alternatives) || []; msg(m,h(errText(e))+(a.length ? '<br>Alternativen: '+a.map(function(x){ return h(x.room)+' ('+h(x.total)+')'; }).join(', ') : ''),'err'); })
      .then(function(){ el('htBkGo').disabled=false; });
  }

  /* ---------- Board-Link ---------- */
  function copy(){
    var u=el('htBoard').textContent, m=el('htBoardMsg'); if(!/^https?:/.test(u)) return;
    var ok=function(){ msg(m,'Link kopiert ✓','ok'); setTimeout(function(){ msg(m,'',''); },2500); };
    if(navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(u).then(ok).catch(function(){ prompt('Link kopieren:',u); });
    else prompt('Link kopieren:',u);
  }
  function newToken(){
    if(!confirm('Neuen Board-Link erzeugen? Der bisherige Link funktioniert danach nicht mehr (z. B. auf dem Tablet neu öffnen).')) return;
    api('/hotel/settings',{method:'POST',body:JSON.stringify({new_board_token:1})}).then(function(j){ links(j.links||{}); msg(el('htBoardMsg'),'Neuer Link erzeugt ✓ Bitte auf dem Tablet neu öffnen.','ok'); })
      .catch(function(e){ msg(el('htBoardMsg'),h(errText(e)),'err'); });
  }

  /* ---------- Live-Daten ---------- */
  function refresh(){   // läuft bereits eine Aktualisierung, wird danach genau einmal nachgeladen
    if(busy){ again=true; return Promise.resolve(); } busy=true;
    var done=function(){ busy=false; if(again){ again=false; refresh(); } };
    return api('/hotel/settings').then(function(j){ stats(j.stats||{}); links(j.links||{}); })
      .catch(function(){}).then(function(){ return Promise.all([loadPending(),loadUpcoming(),loadReqs()]); }).then(done,done);
  }
  function plan(){ try{ var on=typeof active==='function' && active('agent-hotel'); el('htActive').classList.toggle('hidden',!on); el('htRent').classList.toggle('hidden',!!on); }catch(e){} }
  function wire(){
    el('htSave').onclick=save; el('htAddRoom').onclick=function(){ roomRow({}); };
    el('htDemoRooms').onclick=function(){ if([].slice.call(document.querySelectorAll('#htRooms .rName')).some(function(i){ return i.value.trim(); }) && !confirm('Vorhandene Zimmerkategorien durch das Beispiel ersetzen? (Erst mit „Speichern“ übernommen.)')) return; el('htRooms').innerHTML=''; DEMO_ROOMS.forEach(roomRow); };
    el('htBfMode').onchange=bfUI; el('htReload').onclick=refresh; el('htUpAll').onchange=loadUpcoming; el('htReqScope').onchange=loadReqs; el('htRqAdd').onclick=reqAdd;
    el('htAvGo').onclick=avail; el('htBkGo').onclick=doBook; el('htBkX').onclick=function(){ el('htBkBox').classList.add('hidden'); };
    el('htAvIn').onchange=function(){ if(el('htAvIn').value && (!el('htAvOut').value || el('htAvOut').value<=el('htAvIn').value)) el('htAvOut').value=addDays(el('htAvIn').value,1); };
    el('htBoardCopy').onclick=copy; el('htBoardNew').onclick=newToken;
    el('htBoardOpen').onclick=function(e){ if(el('htBoardOpen').getAttribute('href')==='#'){ e.preventDefault(); msg(el('htBoardMsg'),'Bitte zuerst speichern.','info'); } };
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
