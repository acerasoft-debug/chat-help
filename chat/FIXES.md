# ChatHelp — index.php Hata Düzeltmeleri

Rapordaki kritik hataların kaynağı tespit edildi. Aşağıdaki 4 düzeltmeyi
`index.php` (prime1.php) içinde uygulayın. Her biri için **ESKİ** kodu bulup
**YENİ** kod ile değiştirin.

---

## 🔴 1. "Staatliche Formulare açılmıyor" — EN KRİTİK

**Sorun:** Tasarım gereği devlet formları herkese açık görüntülenebilmeli
(PDF için plan gerekli). Ama `openStaatForm` giriş yapmamış / free kullanıcıyı
forma sokmadan geri çeviriyor. Bu yüzden formlar "açılmıyor".
PDF kontrolü zaten `form-engine.js` içinde yapılıyor — burada tekrar engellemek
hatalı.

**ESKİ:**
```js
function openStaatForm(fileUrl,tier){
  // Staatliche Formulare: open to all for viewing
  // PDF in the form requires Basic+ plan
  const _tk=localStorage.getItem('ch_token');
  const _lsU=JSON.parse(localStorage.getItem('ch_user')||'{}');
  const _plan=P.plan||_lsU.plan||'free';

  if(!_tk){
    window._pendingForm={fileUrl,tier};
    addMsg('ai','ℹ️ Bitte anmelden → PDF-Download ab Basic-Plan (13,99€/Mo) oder Einzelzahlung.');
    gP('konto');
    return;
  }
  if(_plan==='free'){
    showPaywall('staatlich',{});
    return;
  }
  // Has paid plan — open form (PDF handled in form itself)
  window.location.href=fileUrl;
}
```

**YENİ:**
```js
function openStaatForm(fileUrl,tier){
  // Görüntüleme herkese açık — PDF indirme, formun içindeki
  // form-engine.js (exportFormPDF) tarafından plan kontrolüyle kısıtlanır.
  if(!fileUrl) return;
  window.location.href = fileUrl;
}
```

---

## 🔴 2. "Meine Anträge" boş / çalışmıyor — loadHistory çöküyor

**Sorun:** `loadHistory()` fonksiyonu `#ant-list`, `#ant-stats`,
`#stat-tot/kept/exp` elemanlarını arıyor — ama `#pnl-ant` panelinde sadece
üst bar var, bu elemanlar HİÇ yok. `list.innerHTML` satırı `null` üzerinde
çalışınca **TypeError** fırlatıp panel boş kalıyor.

**ESKİ:**
```html
<div class="pnl" id="pnl-ant">
  <div class="pnl-topbar" style="display:flex;align-items:center;gap:12px;padding:0 16px;height:54px;border-bottom:0.5px solid rgba(255,255,255,.07);flex-shrink:0">
    <button onclick="gP('chat')" style="width:36px;height:36px;background:rgba(255,255,255,.06);border:0.5px solid rgba(255,255,255,.1);border-radius:10px;display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;-webkit-tap-highlight-color:transparent">
      <svg viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.7)" stroke-width="2.2" stroke-linecap="round" width="16" height="16"><polyline points="15,18 9,12 15,6"/></svg>
    </button>
    <div style="font-size:15px;font-weight:700;color:#fff;flex:1">Meine Anträge</div>
    <div id="ant-count" style="font-size:11px;color:#606080;background:rgba(255,255,255,.06);padding:3px 9px;border-radius:100px"></div>
  </div></div>
```

**YENİ:** (panel gövdesi eklendi — `pnl-scroll` + stats + list)
```html
<div class="pnl" id="pnl-ant">
  <div class="pnl-topbar" style="display:flex;align-items:center;gap:12px;padding:0 16px;height:54px;border-bottom:0.5px solid rgba(255,255,255,.07);flex-shrink:0">
    <button onclick="gP('chat')" style="width:36px;height:36px;background:rgba(255,255,255,.06);border:0.5px solid rgba(255,255,255,.1);border-radius:10px;display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;-webkit-tap-highlight-color:transparent">
      <svg viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.7)" stroke-width="2.2" stroke-linecap="round" width="16" height="16"><polyline points="15,18 9,12 15,6"/></svg>
    </button>
    <div style="font-size:15px;font-weight:700;color:#fff;flex:1">Meine Anträge</div>
    <div id="ant-count" style="font-size:11px;color:#606080;background:rgba(255,255,255,.06);padding:3px 9px;border-radius:100px"></div>
  </div>
  <div class="pnl-scroll">
    <div class="ant-stats" id="ant-stats" style="display:none">
      <div class="astat"><div class="astat-n" id="stat-tot">0</div><div class="astat-l">Anträge gesamt</div></div>
      <div class="astat"><div class="astat-n gr" id="stat-kept">0</div><div class="astat-l">Gespeichert</div></div>
      <div class="astat"><div class="astat-n gold" id="stat-exp">0</div><div class="astat-l">Läuft bald ab</div></div>
    </div>
    <div class="ant-list" id="ant-list"></div>
  </div>
</div>
```

---

## 🟡 3. Tarif modalı kapatma butonu çöküyor (console hatası)

**Sorun:** `#pm` modalının class'ı `pm` ama kapatma butonu `.pm-overlay`
arıyor. `closest('.pm-overlay')` → `null` → `.remove()` **TypeError**.

**ESKİ:**
```html
<button class="pm-close" onclick="this.closest('.pm-overlay').remove()">&#x2715;</button>
```

**YENİ:**
```html
<button class="pm-close" onclick="cPM()">&#x2715;</button>
```

---

## 🟡 4. Bozuk Unicode kaçışı (sonuç kartında garip karakter)

**Sorun:** `\00b7` geçerli bir kaçış değil — null karakter + "0b7" basıyor.
Orta nokta (·) için `·` olmalı.

**ESKİ:**
```js
qual.innerHTML='\u{1F512} Persönliche Daten nur auf Ihrem Server \00b7 ChatHelp AI · '+c.f+' '+c.law+' · DSGVO';
```

**YENİ:**
```js
qual.innerHTML='\u{1F512} Persönliche Daten nur auf Ihrem Server · ChatHelp AI · '+c.f+' '+c.law+' · DSGVO';
```

---

## 🔴 5. "Hedefleyici sorular sorulmuyor" — dilekçe formu BOŞ açılıyor

**Sorun:** `showQuestions()` soruları `doc.qs` üzerinden okuyor, ama tüm 121
belge tanımı soruları `q:` anahtarıyla tutuyor (`doc.q`). `doc.qs` her zaman
`undefined` olduğu için `(doc.qs||[])` boş diziye düşüyor → **hiçbir soru
gösterilmiyor**, form sadece ilerleme çubuğu + "Generieren" butonuyla açılıyor.

**ESKİ:**
```js
  (doc.qs||[]).forEach(q=>{
```

**YENİ:**
```js
  (doc.q||doc.qs||[]).forEach(q=>{
```

> Not: Aynı fonksiyondaki soru tiplerine de dikkat — tanımlarda soru metni `l`,
> zorunluluk `r`, seçenekler `opts` olarak geçiyor. `showQuestions` `q.l` ve
> `q.r` kullanıyor (uyumlu). Eğer `opts` (seçim kutusu) desteği de istiyorsan
> ayrıca eklenebilir; şu an opts'lu sorular düz metin input olarak çıkar.

---

## ⚠️ Not: Staatliche Formulare PDF → 1,99€ ödeme akışı

`form-engine.js` içindeki `exportFormPDF`, plansız kullanıcıya kilit gösteriyor
ama "Zum ChatHelp Portal" diyerek sadece portala **yönlendiriyor** — formun
içinden doğrudan 1,99€ Einzelzahlung / paket ödemesi başlatmıyor. Eğer
"PDF'e basınca anında 1,99€ Stripe ödemesi" istiyorsan, form-engine.js
paywall'una `stripe-checkout.php`'ye giden bir buton eklenmeli. Şu anki haliyle
kullanıcı portala dönüp oradan ödüyor.

---

## Ayrıca: form-engine.js (zaten düzeltildi ve commit'lendi)

- jsPDF yüklenmezse çökme → güvenli kontrol eklendi
- Paywall kapatma seçicisi sağlamlaştırıldı (Escape + arka plan tıkı)
- "Portal'a git" artık yeni sekmede açılıyor (form kaybolmuyor)
