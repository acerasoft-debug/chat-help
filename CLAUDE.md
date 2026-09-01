# VESTRA — çalışma kuralları

Bu dosya operatörün (Acerasoft) **ayakta duran** talimatlarını tutar. Sohbet bağlamı
dolup özetlense bile bunlar geçerli kalır. Bir kuralı ancak operatör açıkça
kaldırdığında sil.

## Gönderim / hedefleme

**KURAL 1 — Zincir, distribütör ve kendi markasını satan firmalara kesinlikle
kampanya gönderme.**

> "zincirlere kendi markasını satanlara ya da büyük distribitörlere kesinlikle gönderme"

Kapsam:
- Perakende **zincirleri** ve franchise operatörleri (Alshaya, Apparel Group, BFL,
  Azadea, Landmark, Al Futtaim …)
- **Distribütörler** / trading house'lar — bölgesel marka haklarını zaten onlar tutuyor,
  yani kanalda müşteri değil rakip
- **Kendi markasını satan** firmalar (marka merkezleri, monobrand mağazalar, own-label
  üreticiler)

Bu kural **elle verilen listeler için de geçerlidir.** Operatörün adresi kendi
yapıştırmış olması muafiyet değil — listeyi hazırlayan araştırma çoğu zaman grubun
kendi tanıtım metnini kopyalar, yani "distribütör" kelimesi adresin yanında yazar.

Nerede uygulanıyor (üçü de `vestra_lead_is_blocked()` çağırır):
- `vestra/inc/notify.php` — `vestra_discover_blocklist()`, `vestra_name_is_blocked()`,
  `vestra_domain_is_blocked()`, `vestra_lead_is_blocked()`
- `.github/workflows/add-and-send.yml` — elle verilen adresler
- `.github/workflows/send-outreach.yml` — otomatik parti
- `vestra/admin.php` — panelden gönderim

**Kayıtlı istisna — vipshop.com (operatör kararı, 31 Ağu 2026):** engelleme listesine
**eklenmez**. Kayıt olan hesap **VipShop Singapore Pte. Ltd.**; operatör "farklı ürünler
satıyor" diyerek KURAL 1 kapsamında görmedi. (Çin'deki 唯品会 off-price platformuyla
karıştırma; alan adı ortak ama karar bu Singapur tüzel kişisi için verildi.)

Yeni bir grup/distribütör görürsen adını `vestra_discover_blocklist()`'e ekle.
**Her yeni listeyi göndermeden önce `vestra_lead_is_blocked()`'tan geçir ve sonucu
oku** — liste eksik olabilir. 29 Ağustos 2026'da ABD listesinde Nordstrom Rack
yakalandı ama **Saks OFF 5TH geçti**: listede `nordstrom` vardı, Saks yoktu.
Zincirin indirim (off-price) kolu da zincirdir.

**Ters yön de hata:** eşleştirme kısa adları başka adların *içinde* bulup gerçek
müşteri adaylarını sessizce eliyordu (`mango` → Mangobay Boutique, `zara` →
Zaragoza Moda, `fila` → Filaticcio, `marshalls` → marshallstreet.co.uk). İsim
tarafı artık kelime sınırı, alan adı tarafında 6 harf ve altı yalnızca **tam**
eşleşiyor. Sessiz eleme, yanlış gönderimden pahalı: kimse fark etmiyor. Listeye
kısa/genel bir kelime eklerken bunu düşün.

**Neden koda gömüldü:** 28 Ağustos 2026'da `add-and-send.yml` bu kontrolü çağırmıyordu
(diğer üç yol çağırıyordu). Elle verilen bir Körfez listesi o boşluktan geçti ve
Alshaya, Al Tayer, Apparel Group, BFL Group, Alyasra, Etoile Group, Concept Brands,
Trafalgar ve Gilbert Luxury Brands kampanya aldı. Kuralın hatırlanmaya bırakılması
yetmiyor; kontrol gönderim yolunda olmalı.

## Hesap açma / belge kuralları

**KURAL 2 — Satıcıdan istenen belge: ticari kayıt + kimlik. Başka bir şey yok.**
(operatör kararı, 31 Ağu 2026)

- `auth_required_doc_types()` **tek doğruluk kaynağıdır** — satıcı:
  `trade_licence` + `id_document`, alıcı: `trade_licence`. Kayıtta açılan istekler,
  panel ve satıcıya giden mektup buradan okur. Ayrı ayrı yazıldıklarında kaçırdılar:
  20 Ağustos'ta kaydolan hesapta **beş** istek duruyordu, mektup iki diyordu.
- `company_reg`, `vat_cert`, `auth_letter` **istenmez.** Gerekçeleri `auth.php`'de
  yazılı; özeti: küçük işletmede çoğu zaman **mevcut değil**, vergi numarası kayıt
  formunda zaten alınıyor, ve "sole director iseniz atlayın" denen bir istek istek
  değildir. Tipleri `auth_doc_types()`'ta duruyor — şüpheli bir dosyada operatör
  panelden tek tek yine isteyebilir; kaldırılan şey **zorunluluk**.
- Eski hesaplarda kalan istekler: `Admin ▸ Documents ▸ 🧹 Clean up`
  (`auth_prune_stale_doc_requests`). Yüklenmiş/onaylanmış/reddedilmiş kayıtlara,
  dosyası olan her satıra ve `by=operator` damgalılara **dokunmaz**.
- **Belge kapıyı açmaz, operatör onayı açar** (`auth_prices_unlocked` →
  `auth_user_approved`). Belge **uyarı**dır. Bu yüzden hiçbir metinde "belgesiz hesap
  aktif edilemez" **yazmamalı** — 31 Ağu 2026'da kayıt notundaki o cümle düzeltildi.

**KURAL 3 — Malın nereden gönderildiği tahmin edilmez, yazılır.**

- `vestra_ships_from()` **yalnızca** ilandaki `ships_from` alanını okur; yoksa
  platform varsayılanı `EU`. Bir gün satıcının hesap ülkesinden türetildi ve canlıda
  "Ships from India" yazdı: **kayıt adresi ile malın çıktığı depo aynı şey değil**,
  alıcı bu satırı gümrük ve teslim süresi için okuyor.
- Ürün eklerken **zorunlu alan** (`seller-add.php`, düzenleme formu dahil).
  Admin ilan tablosunda girilmemiş olan **⚠ not set** ile işaretli.
- Bayrak değerden türetilir; çözülemeyen bir ad varsa metin basılır, **yanlış bayrak
  basılmaz**.
- **Zorunluluk yalnızca yeni/düzenlenen ilana işliyor.** 1 Eylül 2026'da sayıldı:
  **345 ilanın 345'inde `ships_from` boş** — yani şu an sitedeki her ürün varsayılan
  `EU` gösteriyor, girilmiş olduğu için değil, hiç girilmediği için. Denetim:
  `diag-messages.yml` → `ships=true` (satıcıya göre sayım). Eksik olanlar:
  platform/demo 265, GARAGE LE PARIS 56, TYREX 23, Erensthrift 1.
- **Fiyat listesi/mektup üretirken tahmin etme.** `brand_catalog` mektubu gönderim
  yerini yalnızca o markanın **tüm** ilanlarında aynı ve dolu ise yazar; değilse
  satırı hiç basmaz ve operatöre uyarı düşer. Boş bir alanı satıcının kayıt
  ülkesiyle doldurmak KURAL 3'ün yasakladığı şeyin ta kendisi.

## Teklif / pazarlık kuralları

**KURAL 4 — Pazarlığın sınırları** (operatör kararı, 31 Ağu 2026). Hepsi tek
doğrulayıcıda: `vestra_offer_price_error()` + `vestra_offer_turn()`.

- **En fazla 3 karşı teklif**, iki tarafın **toplamı** (`VESTRA_OFFER_MAX_COUNTERS`).
  Dolunca yalnızca kabul/ret kalır ve karşı teklif alanı **hiç görünmez** —
  gösterilip reddedilen bir alan, olmayan bir alandan kötü.
- **Alıcı** teklifi ürünün **yarısından az** olamaz; **satıcı** karşı teklifi
  ürünün **normal fiyatından fazla** olamaz.
- **Alıcı her turda yükselmeli, satıcı her turda düşmeli.** Pazarlık daralmak
  zorunda; yoksa taraflar aynı iki rakamı tekrarlayıp tur hakkını harcıyor.
- Referans fiyat `vestra_offer_ref_price()` = **en düşük kademe** (`vestra_from_price`).
  Bilerek `list` değil: o alan `mode='sale'`de üstü çizili **eski** fiyat, tavan
  yapılsa satıcıya hiç satmadığı bir fiyattan hak verirdi. Fiyatı çözülemeyen
  üründe taban/tavan **uygulanmaz** (yön kuralı yine geçerli).
- **Sıra kimdeyse o yanıt verir.** Kendi karşı teklifini kabul eden taraf olamaz —
  eskiden satıcı kendi 12 EUR'luk teklifini "accept" edip alıcının hiç kabul
  etmediği fiyattan fatura kesebiliyordu.
- **Reddedilen her durumda gerekçe kullanıcıya yazılır** (ürün sayfası, alıcının
  kabul ekranı, admin ve satıcı paneli). Geçersiz yanıt **kayıttan önce** durur.

**KURAL 5 — Fatura operatör onayıyla kesilir.** Teklif kabul edilince yalnızca
`pending` döner; PDF ve numara `Admin ▸ Invoice approvals`'tan çıkar. Fatura
**uzlaşılan** fiyattan kesilir (`vestra_offer_agreed_unit`) — karşı teklif
verilmişse o, ilk teklif değil.

**KURAL 5b — Faturayı hangi satıcının keseceğine operatör karar verir**
(operatör kararı, 1 Eyl 2026: *"satıştan sonra hangi fatura hangi satıcıya ait
benim karar vermem gerekiyor"*). Tek doğrulayıcı: `vestra_offer_invoice_seller()`
— sıra **operatör seçimi > ilanın `seller_uid`'i > platform**. Seçim
`Admin ▸ Invoice approvals`'taki açılır listeden gelir, `offer_responses.json`'da
`invoice_seller_uid` olarak saklanır ve panelde **kayıttan önce** doğrulanır
(bulunamayan hesapta fatura **kesilmez**; sessizce Acerasoft LLC'ye düşmek,
operatörün seçmediği tüzel kişiden belge çıkarmak olurdu). `vestra` **açık** bir
seçimdir, ilana geri dönmez.
- **Neden gerekti:** aynı alıcının kabul ettiği teklifler farklı ilanlara
  dağılabiliyor. Daymond dosyasında 6 satır **iki satıcıya** bölünüyordu
  (GARAGE LE PARIS €1.600, TYREX €4.700) ve bir ilanın (`O6404A`) `seller_uid`'i
  **hiç yoktu**.
- **Kesimden sonra değiştirilemez:** dosya adı satıcı anahtarından türüyor
  (`vestra_invoice_file`), yani satıcıyı değiştirmek **ikinci bir numara** yakar.
  Onay kuyruğu zaten yalnızca faturasız teklifleri listeliyor
  (`vestra_invoices_for_ref`).
- **IBAN faturaya sunucudan girer** (`vestra_payment_rails` → seçilen hesabın
  `bank_iban`/`bank_holder`). Numara buraya, workflow girdisine ya da teşhis
  çıktısına **yazılmaz** — teşhiste yalnızca `VAR (n hane)` görünür.
- Test: `tests/invoice_seller_pick_test.php`.

**KURAL 5c — Satıcının fatura ve banka bilgileri panelden düzeltilebilir**
(operatör isteği, 1 Eyl 2026). `Admin ▸ Users ▸ hesap ▸ ✎ Edit billing details`:
kimlik alanları + **bütün banka alanları** (`bank_iban`, `bank_bic`,
`bank_eur_bic`, `bank_name`, `bank_address`, `bank_routing`, `bank_account`,
`bank_acct_type`, `bank_holder`). Hesap satırında **🏦 Bank** bloğu neyin kayıtlı
olduğunu gösterir; boşsa "ödeme kutusu çıkmaz" uyarısı yazar.
- **IBAN kaydedilmeden önce doğrulanır** (`vestra_iban_valid`, mod-97 + ülke
  uzunluğu). Geçersizse **hiçbir alan** kaydedilmez — yarısı kabul edilen bir
  gönderim, operatöre IBAN'ı da girdiğini düşündürürdü. Aynı kontrol satıcı
  panelinde de var (`seller.php`, `?ibanerr=1`). Test: `tests/iban_valid_test.php`.
- **Biçim tek:** `vestra_iban_normalize()` boşluk/tire atar, büyük harfe çeker.
  Eskiden satıcı tarafı boşluğu koruyor, admin tarafı atıyordu — aynı hesap iki
  farklı metin olarak saklanıyordu.
- **Boş alan mevcudu silmez.** Eski bir IBAN'ı kaldırmanın tek yolu
  **"Banka bilgilerini DEĞİŞTİR"** kutusu: önce banka alanlarını siler, sonra
  yazılanları uygular.
- **Kayıt geri okunarak doğrulanır** (`auth_update` void döner). `billing_saved`
  yalnızca sunucudan okunan değer yazılanla aynıysa basılır; tutmazsa
  `billing_failed` **kırmızı** çıkar. Bu satır haritada **eksikti**: form
  kaydediyor, ekranda hiçbir şey yazmıyordu.
- **Giriş e-postası buradan değişmez** — `auth_update` onu kilitli tutuyor
  (`id`, `hash`, `email`, `created`).

**KURAL 5d — Kesimden önce taslak önizleme** (operatör kararı, 1 Eyl 2026:
*"faturayı müşteri hesabına inmeden ve email ile göndermeden kendim kontrol
etmem gerekiyor"*). `Admin ▸ Invoice approvals`'ta **👁 Draft**: kesilecek
belgenin birebiri — tekliflerde formda **seçili duran** satıcıyla (POST; kayıtlı
değil, o anki liste değeri), siparişlerde **satıcı dilimi başına** bir bağlantı
(GET `pv_order`/`pv_seller`). Taslak **numara yakmaz, diske yazmaz, seçimi
kaydetmez, e-posta göndermez, müşteri hesabında görünmez**; üstünde
`DRAFT INVOICE / not assigned yet`, her sayfa dibinde `DRAFT - not an issued
invoice` yazar. Approve'a basılana kadar müşteri tarafında **hiçbir şey oluşmaz**
— kontrol adımı tam olarak bu boşluk. Önizleme ile kesim **aynı yükten aynı
çizim yolundan** çıkar (`vestra_order_invoice_payloads`,
`vestra_offer_invoice_payload($ref, $pickOverride)`, `vestra_render_invoice_pdf`
`draft=true`); ayrışırlarsa operatör bir şey görür, alıcı başkasını alır.
Onay penceresi artık açıkça "This burns the number, stores the PDF and EMAILS
THE BUYER" diyor. Test: `tests/invoice_draft_test.php`.

**Para girişi:** `vestra_price_input()`. Ham `(float)` virgüllü ondalıkta
sessizce para kaybettiriyor (`"35,50"` → 35.00). Fiyat okunan **her** yerde bu
kullanılmalı.

## Güvenlik / gizlilik

- Depo **herkese açık**, Actions logları da açık. Banka hesap/routing numarası, API
  anahtarı, müşteri listesi veya üçüncü taraf fiyat listesi **repoya, workflow
  girdisine veya ssh-action betiğine girmez.**
- Hesap ve müşteri e-postaları teşhis çıktılarında **maskelenir**. Şifre sıfırlama
  token'ı asla yazdırılmaz.
- **API anahtarları sunucudan çıkmaz.** (Bu yüzden `dropship_api_key` yerine sunucu
  tarafında `dropship_probe` bayrağı var.)
- Operatör bir anahtar/parola yapıştırırsa **kullanma** — iptal edip yenisini almasını
  söyle. Sohbette kimlik bilgisi isteme, tekrarlama, saklama.
- Müşteri şifreleri gösterilemez: `password_hash()` tasarımı gereği geri döndürülemez.
- Çalışan e-posta adresi **uydurma** (kurumsal format tahmininden adres üretme).

## Kayıtlı reddetmeler

Aşağıdakiler istendi ve gerekçesiyle yapılmadı — tekrar gelirse aynı gerekçe geçerli:
- Müşteri şifrelerini saklayıp gösterme (commit `a317f47`) — hash geri döndürülemez.
- Kurumsal format rehberinden çalışan e-postası türetme — var olmayan kişilere posta
  gider, sert bounce oranı gönderen alan adının itibarını düşürür.

## Operasyonel notlar

- Deploy `claude/wizardly-planck-7ylnmk` dalına **push ile** tetiklenir.
- **Testler:** `sh tests/run_all.sh` — teklif akışı, tur sınırı, fiyat kuralları,
  para girişi, e-posta doğrulayıcı, JSON-LD görsel çözücü. Teklif akışı bir
  oturumda dört kez değişti; her seferinde önceki davranışı koruyan şey bunlar
  oldu. Davranış bilerek değiştiyse **testi de düzelt** — bir kez eski ve
  HATALI davranışı koruyan bir iddia çıktı (satıcı kendi karşı teklifini kabul
  edip alıcının onaylamadığı fiyattan fatura kesiyordu).
- **Teşhis çıktısına körü körüne güvenme.** Bu depoda üç kez, kontrolün kendisi
  yanlış yere bakıyordu: `stripe_secret` uygulamanın okumadığı anahtardan,
  giriş probu hiç giriş olmadan "girişli kullanıcı her sayfayı açıyor" diyordu,
  ve `vestra_join_cta` fatal'leri eski kayıttı. Bir uyarıyı rapor etmeden önce
  kontrolün **gerçekten** kodun okuduğu yere baktığını doğrula.
- `workflow_dispatch` en fazla **25 girdi** alır; `diag-live.yml` sınırda.
- `get_job_logs` kuyruğu ~55-78 satır gösterir — uzun çıktıyı sıkıştır, yoksa
  başlangıçtaki satırlar kuyruktan düşer.
- Varsayılan dal `claude/charming-franklin-1ynmuj`, çalışma dalı değil. Varsayılan
  dalda olmayan **yeni** bir workflow dosyası **ne dispatch edilebilir ne
  zamanlanır** (31 Ağu 2026: pool-sweep.yml kayıt listesine hiç girmedi, schedule
  hiç ateşlenmedi, dispatch 404 verdi). Mevcut bir workflow'u bu dalda düzenlemek
  yalnızca **dispatch'te** etkilidir; **schedule her zaman varsayılan daldaki
  sürümü çalıştırır.** Günlük işler bu yüzden **sunucu crontab'ında**:
  deploy-vestra.yml her push'ta `VESTRA-SWEEP` etiketli satırları idempotent kurar
  (06:10 havuz, 06:25 escrow, UTC; kütük `~/vestra_sweep.log`) ve ardından iki
  süpürücüyü kuru koşuyla ayağa kaldırıp fatal varsa deploy'u kırmızıya boyar.
- `add-and-send.yml` ve `send-outreach.yml` ikisi de `leads.json`'ı oku-değiştir-yaz
  yapar: **paralel çalıştırma**, biri diğerini ezer ve gönderim kaydı kaybolunca aynı
  adrese ikinci kez e-posta gider.
- E-posta gövdeleri `nl2br(htmlspecialchars(...))` ile basılır — **markdown çalışmaz**
  (journal gövdeleri de aynı).
- **`delivered` ≠ posta kutusunda.** Brevo'nun `delivered`'ı "alıcı sunucu kabul etti"
  demek; Gmail'in **spam klasörüne** koyması da `delivered` sayılır. `opened` de kanıt
  değil — Gmail görselleri kendi vekilinden çeker, sahte açılma üretir. Betiğin
  "GONDERILDI" satırı ise yalnızca "Brevo isteği kabul etti" demek. Üçü de
  "gördü" anlamına gelmez.
- **AÇIK SORUN — `vestrasales.com`'da DKIM yok** (1 Eylül 2026 ölçümü):
  `dogrulanmis=EVET` ama `dkim=YOK`, `brevo_code=YOK`, `dmarc=YOK`. Kimlik
  doğrulaması olmayan alan adından gelen mektup Gmail'de büyük olasılıkla spam'e
  düşer. Operatör 9 test mektubunu görmedi; Brevo dokuzuna da `delivered` yazmıştı.
  Denetim: `diag-messages.yml` → `sender=true`. **Düzeltme DNS tarafında** (Brevo'nun
  verdiği DKIM + doğrulama kayıtları alan adına eklenecek) — koddan çözülmez.
  Müşteriye toplu gönderim yapmadan önce halledilmeli: spam'e düşen her mektup
  gönderen alan adının itibarını daha da düşürüyor.
- Brevo **ücretsiz plan**; `credits` alanı `sendLimit` tipinde (günlük gönderim
  hakkı), 1 Eylül 2026'da **288**. Her test bir hak yiyor.
- Brevo'da kayıtlı **tek gönderen adres operatörün kendi Gmail'i** ("Acerasoft LLC");
  kod ise `mail_from` = `support@vestrasales.com` ile gönderiyor. İkisinin ayrı
  olması DKIM eksikliğiyle birleşince teslimatı zayıflatıyor.
- **Satış persona adları** (operatör kaydı, 31 Ağu 2026): **Marco Bellini** ve
  **Elena Romano** — VESTRA'nın müşteri yazışmalarında kullanılan ekip adları.
  Kerim Kuku / L1212 dosyası: ilk cevap Marco Bellini imzasıyla gitti, devam
  mektubu (Gewerbeanmeldung talebi) Elena Romano imzasıyla. Gönderen adres hep
  support@vestrasales.com; `buyer_reply` işinde imzacıyı `signer` spec alanı
  seçer (varsayılan Marco Bellini), gövde imzası ile From adı aynı kişiden
  türetilir. Aynı alıcıya aynı konuda hangi persona yazdıysa onunla devam et —
  operatör açıkça değiştirmedikçe.
