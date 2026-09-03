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

**Testi var artık:** `tests/blocklist_test.php` (43 iddia). Her iki yönü de tutar —
engellenmesi gerekenler ve *geçmesi* gereken gerçek butikler. Bloklisteye yeni ad
eklerken testi koş: komşularını yakıp yakmadığını tek gösteren şey o.

**KURAL 1 — elle okuma hâlâ şart.** 1 Eylül 2026'da aynı APAC listesi ikinci kez
yüklendi ve 31 Ağustos'ta elle okunurken **iki zincirin kaçtığı** görüldü:
**Harrolds** (Melbourne/Sydney/Chadstone, ayrıca bazı markaların Avustralya
haklarını tutuyor) ve **Incu** (~9 şube + kendi etiketi). İkisi de 31 Ağustos
16:48'de kampanya aldı (run `33415951830`) — geri alınamadı, bloklisteye eklendi.
Sınırda bırakılanlar, operatör kararı bekliyor: **Restir** (Tokyo; tek mağaza ama
grup ve Japonya'da marka temsilciliği var) ve **Sorry Thanks I Love You**.

## Liste kalitesi — göndermeden önce alan adını DOĞRULA

**KURAL 1b — elle verilen listede adresin var olduğunu varsayma.** 1 Eylül 2026,
73 satırlık APAC listesi: **31 alan adının NS kaydı hiç yok** — yani alan adları
kayıtlı bile değil, o adresler mevcut değil. Hepsi aynı kalıptaydı:
`info@` + mağaza adının bitişik yazılmışı (`info@orchardluxeroom.sg`,
`info@kyotomodestudio.co.jp`, `info@borneoluxury.com.my`). Bu, listeyi hazırlayan
aracın **adres uydurduğunun** imzası — CLAUDE.md'de zaten kayıtlı olan reddin
(kurumsal formattan e-posta türetme) elle gelen hâli.

- Denetim: `checkdnsrr($host,'NS')`. **NS yoksa alan adı kayıtlı değil** — MX
  eksikliğinden daha kesin kanıt; MX yokluğu geçici yapılandırma da olabilir.
  Yanında bir **kontrol grubu** koş (kesin var olan 3-4 alan adı): çözümleyici
  bozuksa her şey "ölü" görünür ve gerçek adayları silersin.
- Neden önemli: sert bounce oranı gönderen alan adının itibarını düşürür ve
  `vestrasales.com`'da **DKIM/DMARC hâlâ yok**. 31 uydurma adrese gönderim,
  gerçek 22 adresin de spam'e düşmesine katkı yapardı.
- Gerçek dükkânın alan adı da ölmüş olabilir: aynı listede Manifesto SG,
  Surrender, Fake Tokyo ve Assin gerçek mağazalar ama alan adları artık kayıtlı
  değil. "Tanıdık isim" adresin çalıştığı anlamına gelmiyor.

**KURAL 1d — park edilmiş / satılık alan adı dükkân değildir.** 1 Eylül 2026'da
`klcollective.com`'a mektup gitti; site taraması firma adını **"HugeDomains"**
diye getirmişti — alan adı satılık, arkasında dükkân yok. İşaret **zaten
elimizdeydi**, kimse bakmıyordu. NS/MX kontrolü bunu yakalayamaz: park
sağlayıcısı alan adını gerçekten kaydeder ve çoğu MX de yayınlar, yani alan adı
"yaşıyor" görünür. Kontrol: `vestra_name_is_parked_domain()`, `add-and-send.yml`
gönderim yolunda. Liste **bilerek dar** — genel bir `domain`/`shop` kelimesi
konsaydı gerçek butikler sessizce elenirdi. İlk yazımda düz `str_contains`
kullandım ve **testin kendisi yakaladı**: `sedo` → "The Sedona Store". Kelime
sınırı şart.

**2 Eyl 2026, 100 satırlık Riviera/Milano/Roma/Barcelona/Madrid listesi** —
listenin kendi notu "e-postaların tümü kurumsal ve aktiftir" diyordu:
**48 alan adının NS kaydı yok** (Cannes 12'de 10, Monako 8'de 6 uydurma),
7 bloklist, 8 elle eleme (kendi markası: Capas Seseña, Lander Urquijo, Eduardo
Rivera, Schostal, Maledetti Toscani, Davide Cenci, Gratacós, Artisanal
Cornucopia). Kalan 37 "canlı" adresin taramasında da tuzak vardı ve
**yalnızca `send=false` ön koşusunda okunduğu için yakalandı:**
`ladressecannes.com` → *emlakçı*, `monaco.mc` → *Monaco Telecom* (ISS),
`velvetmonaco.com` → *"Domain im Kundenauftrag registriert"* (park),
`vergelioshoes.it` → *"Sfera.net Park Page"* (park; gerçek site vergelio.it),
`marguttahome.com` → çözülemedi, ad "home" (tatil evi görünümlü). Park
kalıpları `vestra_name_is_parked_domain()`'e eklendi. **Protokol artık iki
koşu:** önce `send=false` (ekle + tara, ülkeyi düzelt), taranan adları oku,
sonra `send=true`. Ülke `Monaco` → Fransızca (`LANG_MAP` + `.mc`). Sonuç:
100 satırdan **16 mektup** (İtalya 10, İspanya 6; Fransa/Monako 0); 8'i
zaten yazılmıştı, 2'si aynı firmanın ikinci kutusuydu. "Liste doymuş" ölçüsü
(~%15) bir kez daha tuttu.

**Link verilen listede çözülen adresi de OKU (2 Eyl 2026, "Lüks Butik
Leadleri" xlsx, 32 site, e-posta yok).** `add-and-send` siteden adres çözer;
14 sitede yayınlanmış adres **yoktu** (Nugnes, Julian, Sugar, Just One Eye,
Notre, AIDA, Broken Arm, L'Exception, Centre Commercial, Voo, Santa Eulalia,
Holly Golightly, Naked, Vitruta, GR8, Haven) — adres uydurulmaz, o dükkânlara
mektup gitmez. İki sitede çözülen adres **dükkânın değildi:** nubiantokyo.com →
`info@stagheaddesigns.com` (sitenin web ajansı), ka-pok.com →
`back-in-stock@notifyboost.net` (Shopify eklentisi). İkisi de leads.json'da
duruyor; gönderim listesine alınmadı. Ülke tespiti de yanıldı (Des Kohan →
Japan, Mohawk → France; ikisi de Los Angeles) — `send=true` koşusuna
`country` ver. Havuz (yeni koleksiyon) seçiminde de aynı gün dört kuru koşu
gerekti: ilk 100'de B&M, Arnotts, Shaws, Kastner & Öhler, Harry Rosen,
Culture Kings, TSUM, Mytheresa, The RealReal, Stadium Goods, THE OUTNET,
Bicester Village, Estnation, Ron Herman, Shopbop, Moda Operandi... vardı;
hepsi bloklisteye eklendi, `send-outreach` artık park/ele geçirilmiş alan adı
kontrolünü de yapıyor ve yeni koleksiyon kipinde firma başına tek adres seçiyor.
**Gerçek gönderim koşu başına 50'ye kırpılıyor** (`$COUNT > 50 → 50`); 100
için iki koşu gerekir ve ikinci koşu ilkinin gönderdiği firmaları bilmiyordu:
7 firma iki kutudan aynı duyuruyu aldı (dmag.eu, sculpstore.com, luisaworld.com,
abovethecloudsstore.com, hanley.ie, kirnazabete.com, rsvpgallery.com). Geri
alınamadı; harita artık damgalı leadlerin alan adlarıyla **baştan** dolu.

**Aynı gün kaçan bir tane daha:** `keehinghung.com` mektup aldı; taranan ad
*"Official Rolex and Tudor Retailer in Singapore"* — **yetkili marka bayisi**
(üstelik saat, konfeksiyon değil). Bloklisteye eklenmedi, operatör kararı
bekliyor. Tarama adı gönderimden **önce** okunursa bu da yakalanır.

**KURAL 1e — listeyi ÜLKE BAŞINA parti hâlinde gönder.** Mektubun dili lead'in
kayıtlı **ülkesinden** seçiliyor (`add-and-send.yml`, `REGION_LANG`/`LANG_MAP`,
yoksa TLD). Otomatik ülke tespiti yeterince sık yanılıyor: 1 Eylül 2026'da
100 satırlık Avrupa listesi tek partide, `country` **boş** gönderildi ve
sunucudaki kayıtlar yüzünden **Goods Copenhagen İtalyanca**, **Meadow Stockholm
Japonca** mektup aldı (kayıtlı ülkeleri `Italy` ve `Japan` yazıyordu); ayrıca
ülkesi boş olan 8 İtalyan butiği İngilizce aldı. **CSV'de ülke sütunu vardı ve
kullanılmadı** — hata listede değil, gönderimdeydi.
- `country` girdisi verildiğinde **kayıtlı leadlerin ülkesini de düzeltir**, o
  yüzden `send=false` ile çalıştırmak yanlış ülkeleri mektup göndermeden onarır
  (12 kayıt böyle düzeltildi).
- `add-and-send.yml` **paralel çalıştırılmaz** — `leads.json` oku-değiştir-yaz.
  Ülke partilerini sırayla koş.
- **Koşunun "success" demesi yetmez.** Aynı gün bir parti
  `dial tcp: i/o timeout` ile düştü; SSH hiç bağlanmadı, yani düzeltme
  uygulanmadı. Günlükte `ulke duzeltildi` satırını **gör**, sonra "tamam" de.

**KURAL 1c — aynı FİRMAYA ikinci soğuk mektup gitmez.** Tekilleştirme uzun süre
yalnızca **adrese** bakıyordu. 1 Eylül 2026'da ikinci APAC listesinde
`sartorial@marais.com.au` vardı; aynı dükkânın `online@` adresi 31 Ağustos'ta
mektup almıştı. `add-and-send.yml` artık **alan adı** düzeyinde de atlıyor
(`same_firm=skip`, varsayılan; `send` ile kapatılır). **Serbest posta
sağlayıcıları muaf** (gmail, outlook, gmx, qq, naver …) — orada alan adı firma
kimliği taşımaz, muaf tutulmasaydı gmail'deki her yeni butik sessizce elenirdi.

**İkinci APAC listesi (1 Eyl 2026, 76 satır, "unique" adına rağmen):** engelleme
listesi 76'da yalnızca 12'sini yakaladı; elle okununca **13 ad daha** çıktı ve
eklendi — zincir: **United Arrows, Beams, atmos Tokyo, Limited Edt**;
distribütör: **Club 21** (Asya bölge temsilcisi), **The Hour Glass**; kendi
markası: **Paspaley, Lucy Folk, Uma and Leopold, Bamboo Blonde, Real McCoy's,
Kim Soo, Pestle & Mortar**. Ayrıca 16 alan adı kayıtlı değildi ve 3'ü zaten
gönderilmişti: 76 satırdan geriye **32** kaldı. *Liste ne kadar "temizlenmiş"
diye sunulursa sunulsun, elle okuma adımı atlanamıyor.*

**Liste artık DOYMUŞ durumda — yeni liste ≠ yeni müşteri.** 1 Eylül 2026, 100
satırlık küresel liste: 32 KURAL 1 engelli, 9 ölü alan adı, geriye 59 aday.
O 59'dan gerçekten mektup alan **15**. Kalan 44'ü gönderim yolu eledi: ya aynı
adrese daha önce gitmişti ya da aynı firmanın başka kutusuydu. `leads.json`'da
3600+ alan adı var; piyasanın tanınmış çok markalı butikleri büyük ölçüde
işlenmiş. Bir sonraki listeyi değerlendirirken beklenti bu olmalı — "100 satır"
100 yeni aday demek değil, tipik olarak %15 civarı.

**Aynı listeyi ikinci kez göndermeden önce gönderim geçmişine bak.** Bu 73'lük
liste 31 Ağustos'ta tümüyle işlendi: 20 engelli, 31 ölü, **22'sinin hepsine
gönderildi** (run `33415700880` 5 + `33415951830` 16 + Restir daha önce).
Yeniden çalıştırmak sıfır yeni mektup üretirdi. Geçmişi okumanın en ucuz yolu
Actions koşu günlüğü; sunucu tarafında `diag-live.yml` → `leads_status`.

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

**KURAL 2b — "Hesabım aktive edilmedi" diyene, ÖNCE kapıya bak.** 1 Eylül
2026'da Kerim Kuku "hesabım aktive edilmedi, toptan fiyatları göremiyorum" yazdı;
sunucuda `operator_onayi=EVET`, `auth_prices_unlocked=ACIK` idi. Ona yeniden
"tek eksik belgeniz" mektubu göndermek yanlış olurdu: **kapı zaten açıkken belgeyi
sebep göstermek**, müşteriyi yapması gerekmeyen bir işe yollar ve platformun kendi
kaydını okumadığını gösterir. Genellikle sebep, sayfanın **oturum kapalı**
görüntülenmesidir — kademeler yalnızca girişli ve onaylı hesaba basılır.
- Mektup: `send-campaign-preview.yml` → `reply_letter=account_open_l1212`.
  **Kapıyı önce doğrular**; `auth_prices_unlocked` KAPALI ise iş **durur** ve
  hiçbir şey göndermez. "Her şey açık" deyip kilitli sayfaya yollamak, hiç
  yazmamaktan kötü.
- **Rakamlar elle yazılmaz.** Kademe, MOQ, minimum renk, beden aralığı ve renk
  başına artikel numarası **canlı ilan kaydından** basılır. Önceki L1212
  mektubunda sayılar metne gömülüydü; ilan değişince mektup sessizce yalan olur.
- **`Lead time` bilerek basılmıyor** — serbest metin ve bayatlıyor: L1212'de
  1 Eylül 2026'da hâlâ *"Pre-order — in stock from 5 May"* yazıyordu. Geçmiş bir
  tarihi teslim sözü diye basmak, KURAL 3'ün yasakladığı tahminle aynı hata.
  Teslim süresi bağlayıcı teklifte verilir.
- Belge bölümü yalnızca durum **`requested`** ise yazılır; `uploaded` olana
  "yükleyin" demek yaptığı işi tekrar yaptırmaktır (`auth_trade_doc_status`).
- Kupon `voucher_welcome_run` ile **aynı `campaign` adını** kullanır, böylece
  sonraki toplu hoş geldin koşusu aynı hesaba **ikinci bir kod** göndermez.
  Önizlemede kod **yakılmaz**; damga yalnızca gönderim başarılı olunca düşer.

**KURAL 2c — Onay bekleyen hesap SESSİZ kalmaz.** Fiyat kapısını operatör onayı
açıyor, ama kayıt geldiğinde operatöre **hiçbir şey haber vermiyordu**
(`check-registrations.yml` yalnızca elle tetikleniyor). 1 Eylül 2026'da ölçüldü:
**7 hesap** onay bekliyordu ve biri belgesini yükleyip **onayını da almışken**
hâlâ kilitliydi — o alıcı sitede fiyat göremiyor, sepeti onaylayamıyordu.
- Kapalı hesabın göremediği: fiyatlar (`head.php:47`), sipariş (`order.php:24`),
  line sheet PDF/Excel, dropship. Yani hesap "duruyor" ama **hiçbir işe yaramıyor**.
- Çözüm sunucu crontab'ında: `cron_pending_accounts.php` (06:40 UTC,
  `deploy-vestra.yml` idempotent kurar). GitHub'a `schedule` eklemek **çözmez** —
  zamanlanmış işler her zaman varsayılan daldaki sürümü çalıştırır.
- **Bekleyen yoksa mektup gitmez.** Her sabah "0 bekleyen" yazan bir uyarı,
  okunmamayı öğretir.
- Belgesini vermiş olanlar listede **en üste** çıkar: onlar istenen her şeyi
  yapmış ve hâlâ kilitli olanlar.
- Kapı **yeniden tanımlanmaz**, `auth_prices_unlocked()` çağrılır. Bu depoda altı
  kez kontrolün kendisi yanlış yere baktı; kapının ikinci bir kopyası yedincisi olurdu.
- Toplu açma: `send-campaign-preview.yml` → `reply_letter=approve_all`
  (`send=false` kuru listeyi verir). Her hesapta kapıyı **geri okuyup doğrular**.

**KURAL 2d — Belge e-postayla da gelir; operatör panelden hesaba ekler**
(operatör kararı, 2 Eyl 2026: *"evrak girişi email ile girilsin ve trade lisansı
istensin, alıcılarda bu standart uygulama olsun"*).
- Alıcıdan istenen tek belge `trade_licence` (KURAL 2; kayıtta otomatik açılır).
  Kayıt/next-step mektubu (`vestra_ack_text`) ve belge isteği mektubu
  (`vestra_tpl_doc_requested`) artık ikinci yolu söyler: *"bu mektuba dosyayı
  ekleyip yanıtlayın"*. `doc_requested` eskiden "yükleyince fiyatlar hemen açılır"
  diyordu — KURAL 2'ye aykırı yalan, düzeltildi.
- Gelen dosya `Admin ▸ Documents ▸ hesap ▸ 📎 Attach a document received by
  e-mail` ile eklenir → durum `uploaded`, `by/uploaded_by=operator`
  (`auth_attach_doc_for`). **Eklemek onaylamak değil**; onay aynı sayfada ayrı
  düğme. Dosya kuralları kullanıcının yüklemesiyle **birebir** (`auth_store_doc_file`).
- **Müşterinin belgesi GitHub'dan geçmez**: workflow girdisi ve log herkese açık;
  kimlik/ticari belge yalnızca panelden yüklenir. Gmail'den alıp workflow'a
  gömme fikri bu yüzden reddedildi.
- **Yükleme hatası artık sebepli**: `auth_doc_file_check` → `size | type |
  partial | post | server`, panel metni `auth_doc_error_text`, her ret
  `[VESTRA doc]` etiketiyle error_log'a. `post_max_size` aşımı ayrıca yakalanır
  (`vestra_doc_post_overflow`): PHP `$_POST`'u **boş** bırakır, eskiden sayfa
  sessizce yenileniyordu — kullanıcı "hiçbir şey olmuyor" görüyordu. Sınır
  sunucunun ini değerinden okunur (`auth_doc_max_bytes`). Sunucunun php.ini'si
  **32M/32M** (2 Eyl 2026 sondası); `vestra/.user.ini` 32M/48M yazar — ürün
  formu 6 fotoğraf + sheet ile 38 MB'a çıkabiliyor, **bunun altına inme** (ilk
  sürüm 16M/20M yazıp sunucuyu aşağı çekmişti). Tarayıcı büyük fotoğrafı yüklemeden **önce küçültür**
  (`inc/docs.php`). HEIC/HEIF kabul edilir (iPhone). Test:
  `tests/doc_upload_check_test.php`. Teşhis: `diag-messages.yml` →
  `upload_probe=true` (ini, `.user.ini`, `data/docs` yazılabilir mi, error_log).
- 2 Eyl 2026 vakası: Negulescu (alıcı, RO): kapı **açıktı**,
  `trade_licence:requested`, dosya hiç gelmemişti — iki gün "yüklenemedi",
  sonunda e-postayla gönderdi.

**KURAL 2e — "Hesabı aç" düğmesi belgesiz de görünür.** `Admin ▸ Users`
satırında ve `Documents` sayfasında `🔓 Open account`: kapı
(`auth_prices_unlocked`) **kapalı** ve hesap askıda değilse **her zaman** çıkar
(e-postası doğrulanmamışsa onay metni bunu söyler). Eskiden yalnızca
`kyb_status=pending` + doğrulanmış e-postada çıkıyor ve "✓ KYB" yazıyordu;
operatör iki kez "açacak düğmem yok" dedi (1–2 Eyl 2026).

**KURAL 2f — Satıcı: önce ürün, sonra 3 gün belge, gelmezse askı** (operatör
kararı, 2 Eyl 2026: *"ilk ürün eklensin sonrasında satıcıya belgeleri eklemesi
için süre verilsin... 3 gün gibi, eğer yüklemez ise suspend olsun"*).
- İlan ekleme kapısı artık **askı** (`seller-add.php`); KYB onayı şart değil.
  İlan `pending` doğar, operatör onaylamadan yayına çıkmaz.
- Saat `doc_grace_start` damgasıyla başlar: ilk ilanda
  (`vestra_seller_docs_kickoff`) ya da kural yürürlüğe girdiğinde **zaten ilanı
  olan** satıcıyı cron ilk gördüğünde. **İlan tarihinden geriye dönük
  hesaplanmaz** (46 gün önce ilan vermiş 9 satıcıyı ilk sabah uyarmadan askıya
  almak kural değil tuzak olurdu); ilanı olmayan satıcıya saat işlemez. Karar
  tek yerde: `auth_seller_doc_grace()` (saf; `tests/seller_doc_grace_test.php`).
- `cron_seller_docs.php` (sunucu crontab **06:50 UTC**, deploy kurar):
  damgala + "3 gün içinde" mektubu → son 24 saatte hatırlatma → süre dolunca
  `status=suspended, suspend_reason=docs` + mektup. **İlk uyarı gitmeden askı
  yok.** Operatöre yalnızca bir şey olduğu gün yazar: saat başlayan (kim, kaç
  ilan, son tarih), askıya alınan, "askıda + belge geldi". Saat günü yazılıyor
  ki belgesi e-postada zaten duran bir ortak satıcı (GARAGE LE PARIS gibi)
  askıdan önce elle tamamlanabilsin.
- Sondada (2 Eyl 2026) yükleme sunucu tarafında sağlıklıydı: php.ini 32M,
  `data/docs` yazılabilir, dün başka bir hesap yüklemişti. Negulescu'nun
  hatası büyük olasılıkla dosya türü (HEIC) ya da 10 MB üstü fotoğraf — eski
  kod ikisine de aynı genel cümleyi basıyor ve **hiçbir şey loglamıyordu**.
- Askıdaki satıcının ilanları katalogdan çekilir (`vestra_live_listings`; ilan
  durumu değişmez, askı kalkınca kendiliğinden döner). Belge askısındaki satıcı
  **giriş yapabilir** ama yalnızca Verification/Profile'a (`seller.php`);
  operatör askısı (`suspend_reason=operator`) girişi kapatır.
- Kapıyı **operatör açar**: `Activate` / `Open account` → `suspend_reason`
  silinir, belge hâlâ eksikse **taze 3 gün** (`auth_seller_doc_grace_start($uid,
  true)`); eski damga dursaydı ertesi sabah cron yeniden askıya alırdı.
- Mektuplar İngilizce (`vestra_tpl_seller_docs_due` / `_suspended`); e-posta
  yolunu ve girişin çalıştığını söyler.
- **Muaf: GARAGE LE PARIS** (operatör kararı, 2 Eyl 2026: *"Garaj le Paris'i
  muaf tut"*). Kodda varsayılan liste `auth_doc_grace_exempt_uids()`
  (`7ab30f26afedd840`); hesap bayrağı `doc_grace_exempt` (panel:
  `⏸ Exempt from doc deadline` / `▶ Apply doc deadline`, Users satırı ve
  Documents sayfası) kodun varsayılanını **ezer**. Muaf satıcı: saat yok, mektup
  yok, askı yok; belgeleri panelde istenmeye devam eder ve günlük "belgesiz"
  listesinde **MUAF** etiketiyle durur — görünmez olmaz. İlk sabah (3 Eyl 2026)
  saat yalnızca Hindistan'daki 1 ilanlı satıcı için başlar.

**KURAL 2g — Türkiye'den hesap açılmaz (VPN dahil).** Operatör kararı, 3 Eylül
2026: "türkiye ip sini engelle acil" + "VPN ile girse bile kabul etme
türkiyeyi". İki ayrı katman, ayrı ayrı çalışıyor:

- **IP/ülke engeli** (`vestra_country_blocked()`, `inc/security.php`) —
  önceden var olan, operatörün `Admin ▸ Security ▸ 🌍 Ülke engeli` kutusuna
  `TR` yazıp kaydetmesiyle açılan bir özellik (Claude'un admin girişi yok,
  bu düğmeye yalnızca operatör basabilir). Doğrudan Türkiye'den bağlanan bir
  tarayıcıyı siteden keser (`/admin` muaf, izin listesi var, ülke
  çözülemezse engellemez — bkz. `tests/country_block_test.php`). **VPN'i
  yakalamaz** — VPN çıkışı başka ülke gösterirse bu katman görmez.
- **Beyan edilen ülke engeli** (`vestra_country_declares_turkey()`, aynı
  dosya) — VPN'den bağımsız yarı, koddan geliyor. Kayıt formunda
  (`auth_register()`) ve profil güncellemede (`buyer.php`/`seller.php`,
  `_action=profile`) yazılan ülke Türkiye'ye çözülüyorsa hesap açılmaz /
  kaydedilmez — bağlanılan IP ne olursa olsun, çünkü gerçek bir ticari
  kayıt/vergi numarası zaten kendi ülkesini yazmak zorunda. ISO kodu (`TR`),
  İngilizce/Türkçe ad, aksansız yazım (`Turkiye`) hepsi yakalanıyor;
  **Turkmenistan gibi komşu ülke adları kasıtlı olarak tam eşleşme ile
  ayrılıyor** (alt-dize eşleşmesi KURAL 1'in mango/zara dersinin aynısı —
  bkz. `tests/turkey_declare_test.php`).

**Dürüst sınır:** hiç kayıt olmadan, kimliğini hiç beyan etmeden, iyi
gizlenmiş bir VPN ile dolaşan bir ziyaretçiyi IP'den kesin olarak Türk diye
işaretlemek mümkün değil — iki katman birlikte gerçekçi senaryoların büyük
kısmını kapatıyor, "asla giremez" garantisi vermiyor.

3 Eylül 2026 kontrolü (`diag-live.yml` → `find_ref=Turkey`): sunucudaki
hiçbir kayıt dosyasında (accounts.json dahil) "turkey" geçmiyor — yani mevcut
bir Türk müşteri ilişkisini kesme kararı hiç gerekmedi, kural yalnızca YENİ
kayıtlara ve mevcut hesapların ülke değişikliklerine işliyor.

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

**KURAL 5e — Seçilen teklifler tek satıcıdan TEK faturada birleştirilebilir**
(operatör kararı, 1 Eyl 2026: *"ürünler seçilip tek satıcıya tek fatura
kesilebilmeli"*). `Admin ▸ Invoice approvals`: satırlardaki ☑ kutuları +
alttaki birleştirme çubuğu (satıcı + VAT satırı + 👁 Draft + Approve). Kurucu:
`vestra_offers_combined_invoice_payload()` — ya tam yük ya `['error'=>gerekçe]`,
yarım liste sessizce kesilmez. Kurallar: **aynı alıcı** (değilse red), hepsi
`accept` ve **faturasız**, satıcı **tek** (operatör seçimi > bütün ilanlar aynı
satıcıysa o; karışıksa seçim zorunlu). Belge **birincil ref** (ilk seçilen)
adına kesilir; üyeler `offer_responses.json`'da `invoice_group_ref` ile ona
bağlanır ve `vestra_invoices_for_ref()` bu bağı izler — alıcı sayfası, onay
kuyruğu ve teşhis kendiliğinden doğru çalışır (tek atlama; döngü koruması var).
Satır fiyatı = o teklifin **anlaşılan** birimi; satır adına teklif ref'i
eklenir; tarih = kesim günü (farklı günlerde kabul edilmiş tekliflere
içlerinden birinin tarihi verilmez). Test: `invoice_seller_pick_test.php §9`.

**KURAL 5f — Kesilmiş faturadan kalem çıkarılabilir; belge AYNI numarayla
yeniden yazılır** (operatör akışı, 1 Eyl 2026 — Daymond iki kalemi iptal etti).
Tek uygulayıcı: `vestra_offer_invoice_redraft_apply()`; panel (`🔁 Redraft &
email`) ve `send-campaign-preview.yml` (`invoice_draft` + `apply=true`) **aynı**
fonksiyonu çağırır — iki ayrı kesim yolu ayrışır ve ayrışma belgede görünür.
- Üye listesi formdan gelir (`refs[]`). **Birincil ref listede kalmak zorunda:**
  numara ve dosya adı ona bağlı (`vestra_invoice_file`). Çıkarılmak istenirse
  fatura iptal edilip yeniden kesilmeli — açık gerekçeyle reddediliyor.
- Çıkarılan üyenin `invoice_group_ref`'i **silinir**; kalsaydı alıcı faturadan
  çıkardığımız kalemin faturasını görmeye devam ederdi. Teklif faturasız olur ve
  onay kuyruğuna döner.
- **Üç katman birlikte güncellenir** — bu üçü ayrı ayrı yazıldığında üçü de
  kaçırdı: (1) PDF, (2) fatura **meta**'sındaki `total`/`currency` (panel ve
  alıcının fatura satırı buradan okur — belge €3.950 derken ekran €6.300
  diyordu), (3) **sipariş satırı** (`vestra_offer_order_ensure($p, true)`;
  fonksiyon idempotent olduğu için redraft'ta hiç dokunmuyordu, sipariş 6 kalem
  €6.350'de kalmıştı).
- `notify=false`: kayıt düzeltmesi alıcıya mektup göndermeden yapılır. Alıcı
  doğru PDF'i almışken ikinci bir "faturanız hazır", değişmemiş bir belgeyi
  değişmiş gibi gösterir. Mektup **istendiği halde** gitmediyse iş **kırmızı**
  biter.
- `📧 Test` (panel) ve `invoice_draft` (iş akışı): taslağı operatöre e-postalar
  — numara yakmaz, diske yazmaz, müşteriye gitmez.
- **Operatöre giden kopya alıcınınkiyle BİREBİR AYNI** (operatör kararı):
  "[KOPYA]" öneki ve açıklama satırı yok. Müşterinin gördüğü şey görülmeli.
- **Mektuplar İngilizce** (operatör kararı, 1 Eyl 2026: *"sadece ingilizce yap
  ve yazismalarda türkce kullanma"*) — taslak mektupları dahil.
- Test: `invoice_seller_pick_test.php §10b–10d`.

**KURAL 5g — Teklif silinebilir, ama FATURALI teklif silinemez.**
`Admin ▸ Offers ▸ 🗑 Sil`: `offers.csv`'den satırı ve `offer_responses.json`'dan
kaydı çıkarır; dosya **önce zaman damgalı yedeklenir**. Alıcıya bildirim gitmez
(iptali zaten o istedi). Faturası kesilmiş teklifte düğme **hiç görünmez** ve
sunucu tarafında **ayrıca** reddedilir — düğmenin görünmemesi yetki değildir.
Gerekçe: belge alıcının elinde; kaydını silmek var olan bir faturayı dayanaksız
bırakır. Sıra: **önce faturayı düzelt (kalemi çıkar), sonra teklifi sil.**

**KURAL 6 — Kart escrow tavanı €3.000, tek kaynak `VESTRA_ESCROW_MAX`**
(operatör kararı, 2 Eyl 2026: *"escrow 3000'de kalsın"*). 28 Ağustos'ta kod
3.500'e çekilmişti; fiyat listesi sayfaları, Excel ve kampanya mektupları
hep "EUR 3,000" diyordu — müşteriye söylenen ile sepetin kabul ettiği beş gün
ayrı kaldı. `tests/escrow_cap_test.php` escrow geçen her açık metindeki rakamı
sabite karşı tarar. Rakamı metne gömme; mektup şablonu `vestra_tpl_escrow_info`
tavanı ve ücreti parametre alır. Alıcıya gönderim:
`send-campaign-preview.yml` → `reply_letter=escrow_info` + `to=account:<isim>`;
kapı (`auth_prices_unlocked`) kapalıysa iş durur (KURAL 2b). LESSVAT'a 2 Eyl
2026'da gitti. Ölçüm (aynı gün): Stripe anahtarı **LIVE**, webhook tanımlı;
sepette escrow yalnızca ürünün satıcısı Stripe'a bağlıysa (`escrow_ready`) çıkar.

**Para girişi:** `vestra_price_input()`. Ham `(float)` virgüllü ondalıkta
sessizce para kaybettiriyor (`"35,50"` → 35.00). Fiyat okunan **her** yerde bu
kullanılmalı.

**KURAL 7 — Faturası kesilmiş, havale bekleyen siparişe 5 iş günü** (operatör
kararı, 2 Eyl 2026, order OCF7F5 / INV-2026-1001 / Daymond Proconect: *"siparişlerin
ödemesi 5 iş günü içerisinde gelmez ise otomatik kapanacağını söyle, eğer ödeme
yaptıysa havale dekontunu... bir segmente [koysun] ve bize göndersin"*,
sonra: *"İngilizce hazırla şablonu ve havale dekontu için bir segment yap
siparişin oraya"*).

- Tek kaynak: `vestra_order_payment_grace()` + `vestra_order_payment_reminder_send()`
  (`inc/orders.php`). **Hem** `cron_order_payment.php` **hem** operatörün tek
  seferlik mektubu (`buyer_reply` → `payment_due`) **aynı fonksiyonu** çağırır —
  mektubun verdiği son tarih ile otomatik iptalin gerçekten baktığı son tarih
  ayrışmasın diye (bu depoda üç-katman ayrışması KURAL 5f'te, tek kopyalı
  fonksiyon ayrışması bugünkü `order_shipped` düzeltmesinde zaten yaşandı).
  `payment_due` bilerek `buyer_reply`'nin paylaşılan gövde/gönderim kuyruğuna
  **girmiyor**: o kuyruktaki mektuplar durumsuz, bu mektup ise saat **başlatıyor**
  — başlatma ile gönderim aynı işlemde atomik olmalı.
- Kapsam: `status=pending` + fatura **kesilmiş** (`vestra_order_in_review()`
  FALSE — faturasız "inceleme" aşamasındaki taze siparişe dokunulmaz) + **escrow
  DEĞİL** (kart/escrow zaten Stripe üzerinden anında ödeniyor).
- Saat `payment_grace_start` ile başlar (ilk görüldüğünde/mektup gönderilirken),
  5 **iş günü** sonra dolar (`vestra_business_days_after()` — hafta sonu atlar,
  resmi tatil takvimi yok). **İlk mektup gitmeden iptal yok**
  (`payment_reminder_sent_at` boşsa cron mektubu yeniden dener, iptal etmez —
  `cron_seller_docs.php` ile aynı ilke).
- **Dekont yüklenmişse saat DURUR.** `vestra_order_payment_grace()`'in
  `has_receipt` aşaması — bakılmadan otomatik iptal olmaz (KURAL 2f'nin aynı
  dersi: evrak beklerken suspend/iptal yok). Operatör dekontu görüp
  `Admin ▸ Orders` üzerinden durumu **Paid**'e çeker; ayrı bir "onaylandı" alanı
  yok, kapıyı zaten var olan durum seçici açıyor.
- **Segment siparişin sayfasında** (`vestra_render_order_detail()`, alıcı
  görünümü): fatura kesilmiş + ödenmemiş + escrow değilse "💳 Payment" kartı —
  dekont yoksa yükleme formu (`inc/receipts.php`, KYC yüklemesiyle **aynı**
  boyut/uzantı kuralları ve tarayıcı-içi fotoğraf küçültme scripti), varsa
  "alındı, onay bekliyor". Dosya `data/receipts/<ref>/` altında (`.htaccess:
  Deny from all`), kaydı `order_statuses.json[ref].payment_receipt`. Yüklenince
  operatöre haber e-postası gider — "bize gönderin" tam olarak bunu yapıyor.
- **E-posta yolu da açık** (operatör kararı, KURAL 2d'nin aynısı): dekont
  e-postayla gelirse `Admin ▸ Orders` içindeki "📎 Attach (received by e-mail)"
  aynı saklama fonksiyonunu (`vestra_receipt_store`) kullanır.
- Mektup **İngilizce** (operatör: *"İngilizce hazırla şablonu"*), rakamlar
  parametreden basılır — tavan/ücret gibi gömülü metin ayrışması burada da
  aynı hata olurdu. Test: `tests/order_payment_test.php`.
- Zamanlama: sunucu crontab'ı 07:00 UTC (`cron_order_payment.php`,
  `deploy-vestra.yml` idempotent kurar + kuru-koşu kanaryası).

## Güvenlik / gizlilik

- Depo **herkese açık**, Actions logları da açık. Banka hesap/routing numarası, API
  anahtarı, müşteri listesi veya üçüncü taraf fiyat listesi **repoya, workflow
  girdisine veya ssh-action betiğine girmez.**
- Hesap ve müşteri e-postaları teşhis çıktılarında **maskelenir**. Şifre sıfırlama
  token'ı asla yazdırılmaz.
- **Kural workflow GİRDİSİ için de geçerli** — girdi, çalıştırma başlığında kalıcı
  ve herkese açık. 1 Eylül 2026'da `diag-live.yml` → `leads_status` bu kuralı
  çiğniyordu: adres listesini açık girdi olarak alıp log'a **maskesiz** yazıyordu.
  Düzeltildi — artık **alan adı** kabul ediyor (dükkânın zaten açık web sitesi;
  kime yazdığımızı ele vermez), listedeki o alan adına ait tüm kayıtlara açıyor ve
  çıktıda adresleri maskeliyor. Yeni bir sonda yazarken: girdi de çıktı kadar açık.
- **Teşhis adımı "satırı olduğu gibi bas" yapamaz.** 2 Eyl 2026'da
  `diag-live.yml` → `find_ref` bir sipariş satırını herkese açık günlüğe
  **maskesiz** yazdı: alıcının adı, e-postası, telefonu ve teslimat adresi
  (koşu `33675552648`; günlüğü silindi, adım maskelendi — `$show()`). Adımın
  kendi yorumu bile "e-posta dahil basıyor" diyordu ve kimse okumamıştı. Bir
  teşhis adımını çalıştırmadan önce **ne bastığını oku**; yeni adım yazarken
  alanları tek tek düşün: ref/firma/tutar/durum açık, kişiye ait olan maskeli.
- **Müşteri adresini iş akışı girdisine yazma; kayıttan çözdür.** `buyer_reply`
  → `to=account:<isim>` (hesap) ve `to=order:<sipariş ref>` (orders.csv satırı).
  Sipariş/fatura numarasıyla yazan müşteriye cevap `reply_letter=tracking_soon`
  + `to=order:<ref>|inv=<yazdığı fatura no>`; `inv` kayıtla uyuşmazsa iş durur.
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
- **Katalogdan gizli ürün: `unlisted`** (operatör kararı, 2 Eyl 2026 — Musterstück
  `lac-l1212-musterstueck`). `vestra_products()` varsayılan olarak `unlisted` kayıtları
  **atar**; her açık liste (vitrin, fiyat listeleri, katalog dosyaları, sitemap,
  kampanya, API) bu varsayılandan geçer. `vestra_products(true)` **yalnızca**
  `vestra_find()`, sepet escrow haritası, sipariş satırı (SKU) ve admin fiyat
  editörü/teklif seçici için — alıcının elinde id/SKU olan yollar. Açık bir sayfaya
  `true` yazılırsa numune sessizce kataloga döner; `tests/unlisted_product_test.php`
  açık sayfaları kaynak düzeyinde tarar. Ürün sayfası gizli kayda `noindex` basar.
- **Görsel inceleme (mobil + masaüstü):** `tests/render/README.md` — yerel `php -S`
  (`vestra/_router_local.php`, `.htaccess`'in aynası) + Playwright ile her sayfanın
  ekran görüntüsü, yatay taşma ve konsol hatası raporu; `auth.js` yerel hesap açıp
  panel/kapı hâllerini de çeker. 2 Eyl 2026'da bununla bulunanlar: onay bandı
  açık temada okunmuyordu, panel sekmeleri mobilde 4 satırdı, çerez bandı sekme
  çubuğunu örtüyordu, dropshipping sayfası kenar boşluksuzdu.
- **"Gönderildi" mektubu iki yoldan da gider (2 Eyl 2026):** admin panelinden
  girilen takip numarası alıcıya **hiç gitmiyordu** — yalnızca satıcı paneli
  (`seller.php`) mektup yolluyordu; `admin.php` `order_status` → `shipped`
  (ya da gönderilmiş siparişe takip numarası eklenince/değişince) artık aynı
  şablonla yazar: `vestra_tpl_order_shipped` (tek metin, iki yol). Müşteriye
  "numara girilince size gelir" sözü verildiyse (O39419) bunu tutan yer burası.
  Test: `tests/order_letters_test.php`.
- **Canlı PHP hataları:** `diag-messages.yml` → `errlog=true` (mesaj+dosya:satır
  bazında gruplar, son 10 gün ayrı listede, `/tmp/` workflow betikleri hariç).
  2 Eyl 2026'da son 10 günün tüm kayıtları workflow geçici betiklerindendi
  (`vestra_buyer_reply_tmp.php` → `wholesale-xlsx.php` "headers already sent");
  canlı trafikte gerçek hata yoktu.
- **Testler:** `sh tests/run_all.sh` — teklif akışı, tur sınırı, fiyat kuralları,
  para girişi, e-posta doğrulayıcı, JSON-LD görsel çözücü. Teklif akışı bir
  oturumda dört kez değişti; her seferinde önceki davranışı koruyan şey bunlar
  oldu. Davranış bilerek değiştiyse **testi de düzelt** — bir kez eski ve
  HATALI davranışı koruyan bir iddia çıktı (satıcı kendi karşı teklifini kabul
  edip alıcının onaylamadığı fiyattan fatura kesiyordu).
- **Teşhis çıktısına körü körüne güvenme.** Bu depoda **altı** kez, kontrolün
  kendisi yanlış yere bakıyordu: `stripe_secret` uygulamanın okumadığı
  anahtardan, giriş probu hiç giriş olmadan "girişli kullanıcı her sayfayı
  açıyor" diyordu, `vestra_join_cta` fatal'leri eski kayıttı, **1 Eyl 2026'da
  `diag-messages` KESİLMİŞ bir faturaya ısrarla "KESİLMEDİ" dedi**
  (`function_exists('vestra_invoices_for_ref')` her zaman `false` dönüyordu:
  `offers.php` `invoice.php`'yi yalnızca fonksiyon gövdesinde `require` ediyor,
  yani dosya hiç yüklenmiyordu — buna güvenip devam etmek faturalı bir kalemi
  **ikinci kez faturalamak** olurdu), ve **aynı gün `mail_for` tek başına
  verildiğinde Brevo olay bölümü hiç çalışmıyordu** (`mailcfg=true` bloğunun
  içindeydi) — çıktı boş dönüyor ve "bu adrese olay yok" diye okunuyordu.
  **6. vaka, 1 Eyl 2026:** `diag-messages` hesap dökümü `$a['vat']` okuyordu,
  oysa hesabın alanı **`vat_id`** (`auth.php:278`, `register.php`) — yani **her**
  hesap için `vat=(yok)` basıyordu. Buna güvenmek, VAT numarası dosyada **duran**
  bir müşteriye "numaranız kayıtlı değildi, önceki mektubumuz hatalıydı" diye
  **özür** yazdıracaktı. *Doğru bir cümleyi geri almak, hiç yazmamaktan kötü.*
  Yakalanma sebebi: mektup işi metni kurmadan önce kaydı **karşılaştırıyordu**.
  Bir uyarıyı **ya da bir "sorun yok"u** rapor etmeden önce kontrolün
  **gerçekten** kodun okuduğu yere baktığını doğrula. İki kayıt birbirini
  tutmuyorsa (sipariş var ama fatura "yok"; teşhis "VAT yok" ama mektup işi
  "kayıtlı") **önce çelişkiyi çöz**, karar verme.
  **Alan adı yazan bir teşhis satırı, alanı kodda ara.** Yanlış anahtar sessizdir:
  `?? ''` her zaman boş döner, hata vermez.
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
