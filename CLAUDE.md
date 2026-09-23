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

**KURAL 1 — 4 Eyl 2026, operatörün elle verdiği 45 satırlık Asya/Körfez listesi
(Singapur, Japonya, Kore, Avustralya, BAE, S.Arabistan, Katar, Kazakistan,
Azerbaycan).** Operatör "bunlara kampanya gönder" dedi; liste kontrolden geçirildi
ve **operatör kararı: gönderim YOK**. Yerler doğru, karşı taraf yanlış:

| Ne olduğu | Adet |
|---|---|
| AVM / plaza işletmecisi (ev sahibi, mal almıyor) | 17 |
| Departman mağaza zinciri | 8 |
| Distribütör / franchise sahibi | 6 |
| Markanın kendi bayrak mağazası | 1 |
| Belirsiz (bağımsız olabilir) | 1 |

Kod 45'in **12**'sini zaten tutuyordu; kalanlar eklendikten sonra **43/45**.
Doğrulanan iki örnek: **Rubaiyat** 50'den fazla franchise butik + 2 departman
mağazası işletiyor; **Emporium Baku** Sinteks Group'a ait ve Gucci/Dior/Hermès
franchise'larını tutuyor. İkisi de "kanalda müşteri değil rakip".
Bu iş üç ayrı boşluk açığa çıkardı, üçü de düzeltildi:
1. **`.az` / `.kz` public-suffix listesinde yoktu** → `viled.kz` düzleşince
   `viledkz` oluyor, listedeki `viled` hiçbir zaman eşleşmiyordu. Rusça/Arapça
   sayfalarla hedefe giren ülkelerin ccTLD'leri eklendi.
2. **`vestra_is_monobrand()` yalnızca SATTIĞIMIZ 78 markaya bakıyor** → satmadığımız
   bir evin kendi butiği (Maison Hermès Ginza) hiçbir süzgece takılmıyordu. 13 lüks
   ev eklendi. **`omega` bilerek eklenmedi** (günlük kelime; "Alpha Omega" gibi
   gerçek bir dükkânı elerdi) ve `tiffany` yerine `tiffany & co` yazıldı.
3. `Sinonim Baku` **bilerek engellenmedi** — bağımsız bir konsept mağaza olabilir;
   şüpheliyi elemek yerine listede bırakıp göndermemek tercih edildi.
Testi: `blocklist_test.php` bölüm 12/12b/12c (206 iddia).

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

**KURAL 1f — "Doğrulanmış e-posta" sütunu doğrulanmış demek değildir; adresi
SİTEDEN çözdür** (7 Eyl 2026, operatörün verdiği 200 satırlık "çok markalı
bağımsız perakendeci" listesi; elimize 35 satırı geldi).
- Liste her satırda **"Doğrulanmış Kurumsal E-posta"** başlığı taşıyordu.
  Sunucuda 12 alan adı sitesinden çözdürüldü: **12'de 0 doğru.**
  **7 sitede yayınlanmış adres hiç yok** (maxfieldla, hirshleifers,
  thedarksideinitiative, likelihood.us, machusonline, rodengray, ve kayıtlarımıza
  göre notre-shop + havenshop). **5'i vardı ama BAŞKA kutu, ikisi başka ALAN
  ADI:** hlorenzo → `customerservice@` (liste `info@` dedi), xhibition →
  `support@`, onenessboutique → `online@`, goodhoodstore.com → **`goodhood.co.uk`**,
  capsuletoronto.com → **`c-apsule.com`**.
- İmza KURAL 1b'nin aynısı: 35 satırın 22'si `info@` + alan adı. **Bir aracın
  adres ÜRETTİĞİNİN göstergesi.** Böyle bir listeyi olduğu gibi göndermek, sert
  bounce oranını yükseltip gerçek adayların da spam'e düşmesine yol açar.
- **Çözüm ucuz:** `add-and-send.yml`'a adres yerine **site linki** verin
  (`https://alanadi.com`). Sunucu gerçek yayınlanmış adresi kendisi çözer;
  bulamazsa **eklemez** ve o dükkâna mektup gitmez. Listenin verdiği adres
  ile çözülen adres FARKLIYSA, çözülen doğrudur.
- Kod tarafı 35'in 5'ini tuttu (Kith, Dover Street Market, End Clothing, LN-CC,
  Brown Thomas). **Elle okuma yine şarttı:** RSVP Gallery'ye 2 Eylül'de zaten
  **iki kutudan** gitmişti, Mohawk zaten kayıtlıydı, Notre ve Haven'ın sitesinde
  adres olmadığı 2 Eylül'de kayda geçmişti. Ayrıca liste ağırlıklı **sneaker/
  streetwear** segmenti — bizim kanalımız değil.
- **DNS'i yerelde ölçmeye kalkma:** bu ortamda çözümleyici yok; kontrol grubu
  (`google.com` dahil) da "kayıtlı değil" döner. Kontrol grubu koymasaydım 35
  alan adının hepsini ölü sayacaktım. Ölçüm sunucuda (`add-and-send`, `dns_check`).

**KURAL 1f — aynı listenin TAMAMI geldi (8 Eyl 2026, 200 satır) ve tahmin
doğrulandı.** Operatör listeyi olduğu gibi yapıştırıp "kampanya gönder" dedi.
Protokol uygulandı: adres değil **site linki** verildi, önce 6 parti `send=false`
(167 site tarandı), taranan adlar okundu, bloklist güncellendi, sonra **ülke
başına** `send=true`.

| | |
|---|---:|
| Satır | 200 |
| Kod (KURAL 1) tuttu | 33 |
| Sitede yayınlanmış adres YOK | ~110 |
| Tarama yeni ad açığa çıkardı → engellendi | 9 |
| **Gerçekten mektup alan** | **9** |

- **Listenin adresleri uydurma.** İlk partide çözülen 12 adresin **10'u**
  listedekinden farklıydı, ikisi başka ALAN ADI: `merci-merci.com` →
  `achatmode@merci.paris`, `kiliwatch.paris` → `contact@espacekiliwatch.fr`.
  7 Eylül'deki 12/0 ölçümüyle aynı sonuç, artık 200 satırın tamamında.
  Site linki vermek bu listeyi tek başına kurtardı.
- **Elle okuma yine şarttı** ve bu sefer okunacak şey *taranan firma adıydı*:
  `present-london.com` → `hello@presentagency.com` / **"Four Marketing"**
  (marka dağıtım ajansı), `kasina.co.kr` → **`support@cre.ma`** (Kore yorum
  widget'ı SaaS'ı). Satıra bakarak ikisi de görünmüyordu.
- **Ad olmayan adlar gönderilmedi:** `park.at` → firma adı **"Home"**,
  `nid-tokyo.com` → **"N id – N id"**. `factoryoutlet.gr` → "Αρχική" ile aynı
  sınıf; mektup "Hello Home," diye açılırdı. Panelde `rename_lead` eylemi hâlâ
  yok, o yüzden gönderim listesinden çıkarıldılar.
- Ülke tespiti üç kez yanıldı ve `country` girdisi düzeltti: `blueingreensoho`
  (NYC) → "Japan", `selectshopframe` (Dubai, tel +971) → "Japan",
  `thespace.co.za` (tel +27) → "France". KURAL 1e'nin sebebi tam bu.
- Gönderilenler: Pilgrim Surf + Supply (US), Kiliwatch ve Underground (FR,
  Fransızca), Capsule (CA), The Space (ZA), Run Colors (PL, Lehçe), Good As
  Gold (NZ), FRAME (AE), Story Online (IL). **Hata 0**, ölü alan adı 1
  (`baselinestudio.co.za`).
- **Kendi hatam, kayda geçsin:** `run_workflow` **500** döndü, tekrar denedim —
  ama ilk POST koşuyu ZATEN kuyruğa almıştı ve iki `add-and-send` **aynı
  saniyelerde** koştu (16:53:24 ve :25, İngiltere partisi). Bu tam olarak
  `leads.json` oku-değiştir-yaz yarışı. Bu kez zararsız kaldı: ikisi de
  "gönderildi 0, atlandı 2" — iki adres de zaten yazılmıştı, yani ne çift
  mektup ne kayıp damga. **Kural: `run_workflow` 5xx dönerse tekrar denemeden
  ÖNCE koşu listesine bak.**

**KURAL 1g — Outlet MERKEZİ ev sahibidir, outlet DÜKKÂNI müşteridir**
(operatör, 7 Eyl 2026: *"sen outlet bul ve gönder"*).
- Havuzda `lead_find=outlet` dört kayıt verdi. Üçü (Designer Outlets Wolfsburg,
  Batavia Stad, Freeport) 30 Ağustos'ta zaten bloklanmıştı — ama **temasları
  28 Ağustos'ta**, yani kural konmadan önce olmuştu; geçmiş, canlı açık değil.
  **`Outlet Center Eben` (AT) hâlâ geçiyordu**, eklendi; `outlet village` de
  eklendi (Bicester/Kildare kalıbı — bağımsız bir dükkânın taşımayacağı tabela).
- **`outlet` kelimesi TEK BAŞINA eklenmedi** ve eklenmemeli: bağımsız off-price
  dükkânı da adında taşıyor ve o gerçek müşteri (Il Salvagente, factoryoutlet.gr).
  Aynı listedeki **"Outlet Shoes Famous Brands"** (Roma, Via dei Coronari) bilerek
  bırakıldı — tek adresli gerçek dükkân görünümünde; e-postası olmadığı için
  gönderim listesine giremiyor.
- Test: `blocklist_test.php §10d` — dört merkez BLOK, dört bağımsız GEÇER.
  Tek yön yazılsaydı test yeşil kalır, gerçek adaylar sessizce elenirdi.

**KURAL 1h — Siteden çözülen adres DÜKKÂNIN olmayabilir; servis sağlayıcı adresi
gönderim listesine girmez** (7 Eyl 2026, 200 satırlık liste taraması).
- Tarayıcı sayfadaki ilk e-postayı alır. O adres çoğu zaman **canlı destek
  widget'ının**, bir **Shopify eklentisinin**, **alan adı park servisinin** ya da
  siteyi yapan **ajansın** adresidir. Bu depoda dört vaka:
  `ka-pok.com → back-in-stock@notifyboost.net`,
  `nubiantokyo.com → info@stagheaddesigns.com`,
  `shinzo.paris → support@tawk.to`,
  `yusty.com → domains@topdomainer.com` ("TopDomainer Search Engine" = park).
  Beşincisi adres bile değildi: `antonia.it → -banner@section.brands`, sayfadan
  kopmuş bir parça.
- İlk üçü **elle** elenmişti; elle eleme unutulur. Kontrol artık gönderim
  yolunda: `vestra_email_is_service_vendor()` → `vestra_lead_is_blocked()`.
  **TAM host eşitliği**, alt dize değil — bunlar kesin hostlar ve bulanık eşleşme
  bu depoda mango/zara dersini doğurmuştu ("Tawk Store" elenmemeli).
- Liste **dar** tutuluyor: yalnız gözlenenler + hiçbir butiğin kendi alan adı
  olamayacak canlı destek/pazarlama SaaS'ları (crisp, intercom, zendesk,
  freshdesk, klaviyo, mailchimp, sentry). Serbest posta sağlayıcıları (gmail vb.)
  **kapsam dışı** — gerçek küçük butik oradan yazıyor.
- Test: `blocklist_test.php §10e` — beş vaka BLOK, dükkânın kendi adresi /
  gmail'li butik / `.info` alan adı / benzer isim GEÇER.

**KURAL 1i — "Kod geçirdi" ELE OKUNMADI demek değil; havuzun dibinde üç zincir
daha vardı** (17 Eyl 2026, soğuk havuzda hiç mektup almamış 10 aday).
Kod **onunu da** geçiriyordu. Üçü de **araştırılarak** doğrulandı, hafızadan
elenmedi (`worksout` dersi: *kayıt hatırlamadan güçlü kanıttır*):

| Firma | Neden KURAL 1 |
|---|---|
| **Carl Scarpa** (IE) | UK+İrlanda'da **20 şube** VE **yedi kendi markası** (Rosconia, ViaCimo, Pierre Varini, Ogetti, Moninari, Babila, Instep) — zincir + own-label |
| **Kalogirou** (GR/CY) | **10 mağaza**, *"Kalogirou Private Label"* kendi serisi, ve **Fais Group**'a ait (dağıtım grubu) — üç koldan da |
| **Groupe Stalric** (FR) | Occitanie'de **13 satış noktası**, bu yıl kendi deri markasını çıkardı, ayrıca **ERAM ve iki MANGO franchise'ı** işletiyor |

- **`scarpa` TEK BAŞINA EKLENMEDİ ve eklenmemeli:** İtalyanca "ayakkabı" demek
  ve gerçek bir ayakkabı dükkânının adında geçmesi olağan (La Scarpa,
  Scarpa & Co, Bella Scarpa). Konsaydı hepsi **sessizce** elenirdi — mango/zara
  dersinin ayakkabı hâli, ve sessiz eleme yanlış gönderimden pahalı.
  `kalogirou` ve `stalric` tam ad; ikisi de 6 harften uzun olduğu için alan adı
  tarafında da eşleşiyor (`groupestalric.fr`).
- Test: `blocklist_test.php §18/18b` (toplam **422 iddia**), iki yön de.
  Düşebildiği doğrulandı: üç ad silinince **4 kırmızı**, `scarpa` tek başına
  eklenince **3**. *İlk falsifikasyonumda `grep FAIL` kullandım, oysa bu testin
  hata işareti **`HATA`** — iki sabotaj da uygulanmıştı ve "0 kırmızı" okuyup
  iddialarımı ölçümsüz sanacaktım. Aracın kendi çıktı biçimini oku.*

**Soğuk yolda FİRMA-BAZLI tekilleştirme YOK — hâlâ.** Aynı koşuda dördüncü bir
eleme gerekti ve o bir kural değil **boşluk**: `deadstock.ca` (Livestock) —
`in**@` kutusu 31 Ağustos'ta mektup almış, `sh**@` aynı firmanın ikinci kutusu.
`ayni firmadan baska bir kutu` kontrolü `send-outreach.yml`'de **yalnız
`new_collection` dalında** (satır 798); soğuk dal yalnız **adres** damgasına
bakıyor. 8 Eylül'de kaydedilen bu boşluk hâlâ açık ve elle atlandı
(`skip_email_regex`). *Bir sonraki soğuk koşuda ya yine elle yapılmalı ya da
kontrol o dala da taşınmalı.*

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

**KURAL 2 — İstek SATIRI yoksa belge verilemez; tablo zorunlu listeden tamamlanır**
(operatör, 10 Eyl 2026: *"seller de sadece bir dokuman indirilebiliyor oysaki
Trade Licence · Government ID (Passport / National ID) bu iki belge indirilmesi
gerekli"*).
- KURAL 2 zaten *"`auth_required_doc_types()` tek doğruluk kaynağıdır — kayıtta
  açılan istekler, panel ve satıcıya giden mektup buradan okur"* diyor. **Okuyan
  iki ekran bunu yapmıyordu:** `seller.php` ve `admin.php` tabloyu hesabın
  **kayıtlı** `doc_requests` satırlarından çiziyordu. Yükleme formu istek
  **ID'sine** bağlı olduğu için satır yoksa **düğme de yok** — sayfa üstte
  "her satıcı iki belge verir" derken altta tek satır gösteriyor ve satıcının
  kimliği verecek **hiçbir yolu** kalmıyordu.
- **Satırın yok olma sebebi iki ayrı sınıf, ikisi de gerçek:** (1) hesap
  `auth_register()` **dışında** açılmış — `create_seller`, `sync_lesgarage`,
  `create_tyrex_migrate`; üçü de doğrudan `auth_save_accounts()` yazıyor ve
  ikisi `'doc_requests'=>[]` diyor; (2) hesap `id_document` zorunlu listeye
  girmeden önce kaydolmuş.
- **Canlı ölçüm (aynı gün, 109 hesap): 105 tam, 4 EKSİK** —
  TYREX / GARAGE LE PARIS / AZURE MIRROR'da `trade_licence` yok,
  **Marca Online'da hiç satır yok** (create_seller ile açılmıştı, tam
  beklendiği gibi). Sonda: `diag-messages.yml` → `upload_probe=true`.
- Tek tamamlayıcı: `auth_ensure_required_doc_requests($uid, $apply=true)` —
  eksik satırı açar, **mevcut satıra dokunmaz** (durum, dosya, operatör notu,
  damgalar korunur; onaylanmış bir belgeyi `requested`'a döndürmek satıcıya
  verdiği belgeyi tekrar sordurmak olurdu). Satırı `auth_doc_request_row()`
  kuruyor ve **kayıt tarafı da artık ondan** okuyor — liste `auth_register()`
  içinde ikinci kez elle yazılmıyordu ve iki kopya ayrışabiliyordu.
- **Üç okuma yolu da çağırıyor:** satıcı Verification sayfası, panelin
  Documents listesi ve `cron_seller_docs.php`. Cron şart: diğer ikisi birinin o
  sayfayı **açmasını** bekliyor, oysa cron zaten her satıcıyı belge için
  kovalıyor — satırsız bir satıcıdan belge beklenirken süre işliyordu, yani
  istenmeyen bir belge yüzünden askıya alınmak (KURAL 2f'nin tuzağı). Cron
  **kuru koşuda yazmıyor** (`!$DRY`); deploy kanaryası `--dry` ile koşuyor.
- **Mevcut 4 hesap ne zaman düzelir:** tamamlama bir **tetikleyiciyle** çalışıyor
  (sayfa/panel açılışı ya da günlük cron), sonda ise dosyayı doğrudan okuyor —
  yani deploy'dan hemen sonraki ölçüm hâlâ "4 eksik" der ve bu **doğrudur**.
  Dördü de satıcı, dolayısıyla `cron_seller_docs.php`'nin bir sonraki koşusu
  (13:50 UTC) hepsini açar; operatör hesabı `Admin ▸ Documents`'ta açarsa
  **anında** düzelir. Sonda salt-okunur kalıyor: teşhis yazmaz.
- **`VESTRA_ACCOUNTS` artık `defined()` korumalı.** Korumasızken testin hesap
  deposunu geçici dosyaya yönlendirmesi mümkün değildi ve ilk yazımda test
  **gerçek `data/accounts.json`'a yazdı** (`putenv` ile env kurmuştum, oysa yol
  bir SABİT ve env hiç okunmuyor). Üretimde davranış aynı.
- **Çelişki çözülmeden karar verilmedi:** operatörün ekranındaki not
  (*"Approved in bulk: account predates…"*) `set-mail-key.yml`'de yalnız
  **alıcılara** işleyen bir toplu onaydan geliyor (`type !== 'buyer' → skip`),
  ama başlık ve "iki belge" cümlesi `seller.php`'nin. Yani ekran ile ölçüm
  birebir örtüşmüyor; **hangi hesap olduğu operatör kararı bekliyor.** Yapısal
  kusur bundan bağımsız olarak gerçek ve düzeltildi.
- Test: `tests/doc_request_coverage_test.php` (35 iddia, kum havuzu gerçek
  depoya dokunmuyor). Düşebildiği doğrulandı: tamamlama kapatılınca **7
  kırmızı**, mevcut satır ezilirse **3**. **Yerelde çizdirildi:** yalnız
  onaylı `trade_licence` taşıyan bir satıcı hesabında sayfa artık **iki satır**
  (Trade Licence + Government ID) ve ID satırında **yükleme alanı** basıyor,
  PHP uyarısı 0.

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
- Çözüm sunucu crontab'ında: `cron_pending_accounts.php` (06:40 sunucu saati = 13:40 UTC,
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
- `cron_seller_docs.php` (sunucu crontab **06:50 sunucu saati = 13:50 UTC**, deploy kurar):
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

**Kayıtlı istisna — "Marca Online" satıcı hesabı (operatör kararı, 4 Eyl
2026).** Kuloğlu Tekstil ithalatının (bkz. bu dosyanın "Kuloğlu Tekstil
ürün ithalatı" bölümü) gerçek satıcı kimliği. Hesabın `country` alanı
**Turkey** — soruldu, operatör "türkiye" yazarak bilinçli onayladı (bu
KURAL'ın ta kendisiyle çelişkili olduğu, vipshop.com/KURAL 1 örneğiyle
aynı desende, açıkça belirtildikten sonra). Aynı desen: alan adı ortak
ama karar bilinçli bir istisna için.

- Hesap `create_seller` moduyla (`diag-admin-discover.yml`, `kuloglu`
  girdisi) doğrudan `auth_save_accounts()` ile yazılıyor —
  `auth_register()`/profil güncelleme yolundan **geçmiyor**, yani
  `vestra_country_declares_turkey()` buraya hiç uğramıyor. Bu, kodun
  gözden kaçırdığı bir boşluk **değil**: `sync_lesgarage` ve
  `create_tyrex_migrate` (admin.php) zaten aynı doğrudan-yazma desenini
  kullanıyor, ikisi de bu kontrolden geçmiyor. Genel **self-servis kayıt
  engeli değişmedi** — yalnızca operatörün kendi, elle oluşturduğu, tek
  bu hesap için bilinen bir istisna.
- **İki ayrı ad, bilerek:** hesabın `company` alanı ("Marca Online" —
  fatura/hukuki kimlik) ile ürünlerin `seller` alanı ("Wholesale
  Underwear" — müşteriye görünen) **farklı**. Operatör talebi iki kez
  tekrarlandı: "Kuloglu Textil diye yazma siteye." `company` alanı hiçbir
  müşteri ekranında tek başına görünmüyor (yalnız fatura/operatör
  panelinde); `seller` alanı ürün sayfasında (`product.php`) basılan asıl
  alan — `sync_lesgarage`'daki `$p['seller']='Les Garage Paris'` deseninin
  aynısı, hesabın kendi `company`'sinden bağımsız.
- Malın gerçek gönderim yeri (KURAL 3, aşağıda) **Turkey/Kayseri** —
  Kuloğlu'nun kendi iletişim numaralarından (`0352` alan kodu = Kayseri,
  `0532` = Türk GSM) doğrulandı, tahmin edilmedi. Hesabın `country`'si ile
  `ships_from` aynı değeri taşıyor ama **iki ayrı, birbirinden bağımsız
  karar**: biri operatörün bilinçli KURAL 2g istisnası, diğeri KURAL 3'ün
  zaten zorunlu tuttuğu, uydurulamayan bir gerçek.

**KURAL 2h — Bazı ülkelerden kayıt olan ALICI'nın kapısı kayıtta AÇIK doğar:
S.Arabistan, Japonya, Avustralya, Singapur** (operatör, 8 Eyl 2026, aynı gün üç
kez büyüdü: *"arabistandaan girenler direkt fiyatlari görebilsinler ve siparis
edebilsinler"* → *"japonya, avustralya da ayni olsun"* → *"singapur da"*).
KURAL 2g'nin tam tersi yöne bakan kardeşi: o kapatır, bu açar — ve açan bir
kural yanlış eşleşirse operatörün hakkında hiçbir karar vermediği bir firmaya
toptan fiyatı ve sipariş hakkını verir.

- **Liste TEK yerde:** `vestra_auto_open_countries()` (`inc/security.php`),
  ISO koduyla anahtarlı. Yeni ülke = **bir satır**, kapıda yeni bir dal değil.
  Ülke başına ayrı fonksiyon yazılmadı çünkü kural bir öğleden sonrada üç kez
  büyüdü; beşincisi de gelecek.
- **Ölçüt IP değil, hesabın KAYITLI ÜLKESİ** (operatör seçimi). IP tek bir
  isteğe ait: seyahatteki Japon alıcı sipariş ortasında erişimini kaybederdi,
  Tokyo çıkışlı bir VPN ise kazanırdı. Beyan edilen ülke hesap yaşadıkça geçerli
  ve gerçek bir ticari kaydın uyması gereken şey zaten o.
- **Kapının ikinci bir tanımı YAZILMADI.** Promo hesabının kullandığı **aynı**
  alan açıyor: `kyb_status='approved'` → `auth_user_approved()` → fiyat, sipariş,
  line sheet, dropship. Bu depoda altı kez kontrolün kendisi yanlış yere baktı;
  `auth_prices_unlocked()`'a ülke dalı eklemek yedincisi olurdu. Sitede
  `status==='active'` diye ayrıca bakan **tek bir yer bile yok** (arandı).
- **Yalnızca ALICI.** Operatörün cümlesi fiyat görmek ve sipariş vermek üzerine;
  ikisi de alıcı yolu. Satıcıda `kyb_status` daha ağır bir şey söylüyor
  (ödeme/güven) ve ilan kapısı zaten ayrı (KURAL 2f) — o ülkelerden gelen satıcı
  eski akışta, yani **değişiklik değil, mevcut hâl**.
- **Yalnızca KAYITTA.** Profil kaydetmede ülkeyi "Japan" yapmak hesabın **kendi
  kapısını açması** olurdu. Türkiye kontrolü `buyer.php`/`seller.php`'de de var
  çünkü o KAPATIR; bu oraya bilerek eklenmedi (test §9 bunu tutuyor).
- **Çıplak ISO kodu kabul ediliyor** (`SA`, `JP`, `AU`, `SG`): kayıt formunun
  kendi placeholder'ı `DE`, yani tam bu şekli istiyor — reddetmek, formun
  dediğini yapan kullanıcıyı elerdi. **Eşleşme TAM, alt dize değil**, ve her
  ülkenin kendi yakın-komşu tuzağı testte adıyla duruyor:
  - `SA` — günlük dilde "SA" çoğu zaman **Güney Afrika** demek; onun ISO'su `ZA`.
  - `AU` — **Avusturya** (`AT`) klasik karışma; `Österreich`/`Autriche` de.
  - `JP` — `JA` bir **dil** kodu, Japonya'nın kodu değil; Jamaika `JM`.
  - `SG` — Senegal `SN`, Sri Lanka `LK`.
  Ayrıca eyalet adı ülke değil (`South Australia`, `Western Australia`) ve
  şirket adı ülke değil (`Saudi Arabia Trading Co.`). Bu, Türkiye kuralındaki
  Türkmenistan'ın ve mango/zara dersinin aynı sınıfı.
- **Gevşek eşleştirmenin bedeli ölçüldü.** Falsifikasyon koşusunda `in_array`
  yerine `str_contains` konunca **21 iddia** düştü ve içlerinden biri şuydu:
  Portekizce `austrália` dizgesi `tr` içeriyor, yani alt dize eşleşmesiyle
  **`TR` (Türkiye) Avustralya sayılıp kapıyı AÇIYORDU** — KURAL 2g'nin tam
  tersi. Açan bir kuralda bulanık eşleşme böyle bir şey.
- **Sessiz kapı yok.** Hesapta gerekçe duruyor (`kyb_auto='country:JP'` gibi;
  promo hesabında bu alan hiç yoktu ve aylar sonra "bu hesap neden açık?"
  sorusunun cevabı hiçbir yerde durmuyordu) ve operatöre giden kayıt bildirimi
  konuda rozet, gövdede gerekçe taşıyor. Rozet **yalnızca** otomatik onayda
  basılıyor — her kayıtta aynı cümleyi yazan bildirim okunmamayı öğretir
  (KURAL 2c). **Ülke adı ISO kodundan çözülüyor** (`vestra_country_of_cc`), elle
  yazılmıyor: liste büyüdüğünde yanlış ülkeyi söyleyen bir satır kalırdı.
- **`vestra_cc_of_country()`'ye S.Arabistan ve Singapur eklendi** (Japonya/
  Avustralya zaten vardı): panelin "beyan edilen ülke ≠ kayıt IP'sinin ülkesi"
  karşılaştırması o haritadan okuyor ve haritada olmayan ad **sessiz geçiyor** —
  yani kapısı kendiliğinden açılan iki ülke, tam da bakılması gereken yerde
  hiçbir zaman karşılaştırılmıyordu.
- **Kayıt mektubu ayrı gövde** (`vestra_ack_text($lang,$name,$type,$approved)`):
  varsayılan metin *"Our team will review them and activate your account"*
  diyor ve bu hesapta **yapılmayacak** bir işi bekletiyor — KURAL 2b'nin aynısı
  (kapı açıkken belgeyi sebep göstermek). Yeni gövde hesabın açık olduğunu
  söylüyor, düğme belge sayfasına değil **kataloga** gidiyor, belge yine
  isteniyor ama *"does not hold up your ordering"* diye. 5 dilde yazılı ve
  **hiçbir ülke adı gömülü değil** — dört ülkeye dört metin yazmak, beşincisi
  eklendiğinde sessizce eksik kalırdı (test bunu da tutuyor).
  `cron_pending_accounts.php` bu hesabı "onay bekliyor" diye **yazmıyor**
  (kapısı açık), "açık ama belgesiz" listesinde **duruyor** — doğru olan bu.
- Test: `tests/auto_open_country_test.php` (171 iddia). Düşebildiği doğrulandı:
  eşleştirici alt dizeye çevrilip üç değişiklik geri alınınca **21 kırmızı**.

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

- **En fazla 5 karşı teklif**, iki tarafın **toplamı** (`VESTRA_OFFER_MAX_COUNTERS`;
  31 Ağu 2026'da 3'tü, **9 Eyl 2026'da 5'e çıkarıldı** — operatör: *"teklif
  edilebilecek sayıyı 5'e çıkar"*). Dolunca yalnızca kabul/ret kalır ve karşı
  teklif alanı **hiç görünmez** — gösterilip reddedilen bir alan, olmayan bir
  alandan kötü. **Rakam hiçbir metne gömülü değil**: panel, alıcı paneli ve
  mektuplar ("bu son tur", "kalan tur var") sabitten okuyor, o yüzden 3→5
  tek satırda oldu. `tests/offers_rounds_test.php` mekanizmayı kendi tanımladığı
  bir değerle sınıyor, **sevk edilen 5'i ise kaynaktan** doğruluyor — escrow
  tavanının beş gün koddan farklı kalması (KURAL 6) bu iki ayrı iddianın sebebi.
- **Reddedilen teklife satıcı DAHA İYİ fiyatla dönebilir** (operatör, 9 Eyl
  2026: alıcı €100'ü reddetti, operatör €90 gönderilmesini istedi). Kapalı bir
  pazarlığı yeniden açmak ticarette olağan ve engellemek, kaybedilen müşteriyi
  geri kazanmanın tek yolunu kapatırdı. Sınırlar aynen duruyor: **yalnız
  `decline`** (kabul edilmiş teklif **asla** açılmaz — orada uzlaşılan fiyat,
  sipariş satırı ve fatura var, yasağın var olma sebebi o), **yalnız `counter`**
  (reddedilmiş bir teklifi kabul etmek, alıcıyı *hayır* dediği fiyattan bağlamak
  olurdu), yön kuralı (yeni teklif öncekinden **ucuz** olmak zorunda) ve tur
  sayacı — yeniden açmak bedava tur üretmiyor.
- **Alıcının kabulü/reddi pazarlık geçmişini SİLİYORDU** (9 Eyl 2026'da bulundu).
  `vestra_offer_accept_counter()` ve `vestra_offer_decline_counter()` kaydı
  sıfırdan kuruyor ve `counters` düşüyordu. İki sonucu vardı: (1) alıcının kendi
  panelindeki tur çizelgesi (`buyer.php`, "round i/N") kabulden/retten sonra
  **boşalıyordu** — oysa kural "kim ne teklif etti sorusunun cevabı kayıtta
  durmazsa uzlaşılan fiyat da savunulamaz" diyor; (2) sayaç `counters` yoksa
  `counter_price`'a bakıp **1** dönüyor, yani her ret tur hakkını **sessizce
  iade ediyordu**. İkincisi reddedilmiş teklif yeniden açılamadığı sürece
  görünmüyordu; yeniden açma eklenince "reddet → yeniden aç" **sonsuz tur**
  üretirdi. *Yeni bir kapı açmadan önce, kapalıyken görünmeyen ne varsa onu ara.*
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

**KURAL 4b — Teklif ve numune kutuları İLAN BAŞINA kapatılabilir; kapı sunucuda**
(operatör, 7 Eyl 2026, Lacoste "Trim Cotton Jersey T-Shirt": *"angebot vermeyi
kaldır sample'ı da kaldır"*).
- Tek karar noktası `vestra_offers_open()` (`inc/products.php`): `mode='offer'`
  **ya da** `offers` bayrağı açar, operatörün `no_offers` anahtarı **ikisini de
  ezer**. Numune için `vestra_sample_price()` — 0/boş/sayı değil = kutu yok.
- **`/offer` ucu ilanın teklif alıp almadığına HİÇ BAKMIYORDU.** Yalnızca
  satıldı mı ve fiyat kuralları geçiyor mu diye bakıyordu; sabit fiyatlı,
  hiçbir yerinde teklif düğmesi olmayan bir ilana elle POST atan biri gerçek
  bir teklif bırakabiliyordu — satıcı paneline düşüyor, alıcıya mektup gidiyor,
  kabul edilirse fatura kesiliyordu. `products.php`'nin kendi yorumu "düğmeyi
  gizlemek kapı değildir" deyip **altı** satın alma yolunu sayıyor; teklif
  onlardan biri değildi. Artık ürün sayfası ile `/offer` **aynı** fonksiyonu
  çağırıyor.
- **Kapatmanın hiçbir yolu yoktu:** panelde alan yok, `set_product.php`'de
  anahtar yok, `sample_price=0` "geçersiz fiyat" diye reddediliyordu. Şimdi
  `Admin ▸ Prices`'ta iki sütun (Offers kutucuğu, Sample €) ve
  `set-product.yml`'de `offers: on|off`, `tiers: [{min,price}…]`,
  `sample_price: 0`.
- **`offers` alanını silmek KALICI DEĞİL:** `seller.php` her kaydetmede o alanı
  satıcının kutucuğundan yeniden yazıyor, yani operatörün kararı satıcının bir
  sonraki kaydında sessizce geri alınıyordu. `no_offers` bu yüzden ayrı ve
  satıcı tarafı onu hiç yazmıyor. Numunede böyle bir anahtar **gerekmiyor** —
  `sample_price`'ı satıcı formu zaten hiç yazmıyor.
- İşaretsiz bir kutucuk **hiç gönderilmez**: satırda ayrıca gizli
  `offers_seen[id]` var, yoksa editör teklifi yalnızca **açabilirdi**.
  `mode='offer'` satırında ikisi de çizilmiyor — aynı gönderimde modu
  değiştirmek "işaretsiz" diye okunup teklifi sessizce kapatırdı.
- **`mode='offer'` + teklif kapalı REDDEDİLİYOR** (hem panelde hem yazıcıda):
  o ilanın sabit fiyatı yok, kapanınca vitrinde duran ama satın alınamayan bir
  kayıt kalırdı.
- **MOQ, paket adımının katı olmak zorunda.** Sepet miktarı `size_step`'in
  katına yuvarlıyor, yani 10'luk paketli bir ilanda "min 48" sayfada 48, kasada
  50 demek — ilan edilen minimum hiç alınamaz. Kademe basamakları için aynı
  uyum **uyarı** (fiyat bir kat sonra devreye girer, ilan yalan söylemez).
- Test: `tests/offers_sample_gate_test.php` (53 iddia; §5 `set_product.php`'yi
  kum havuzunda **gerçekten koşturuyor** — her ret, kuru koşunun hiçbir şey
  yazmadığı, ve değişikliğin tamamının indiği).
- **Ölçüm tuzağı (yaşandı):** ürün sayfasını **girişsiz** çekip "teklif kutusu
  yok" diye okumak fiyat kapısını ölçer, dalı değil — dört durumda da her
  işaretçi 0 çıkıyor. Yerel ölçüm onaylı bir alıcı oturumuyla yapıldı: sabit
  fiyat+numune → numune formu VAR; `offer` açık → teklif formu VAR; `offer`
  kapalı → form YOK ve yerine "artık sipariş edilemiyor" satırı; sabit
  fiyat+ikisi kapalı → fiyat bloğu VAR, iki kutu da YOK. PHP uyarısı 0.

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
- **"Faturada kalem yok" ilk olarak DİLİM sorusudur** (8 Eyl 2026,
  VES-A8A129EC): siparişin satırları birden fazla satıcının ilanındansa
  `vestra_order_invoice_payloads()` siparişi satıcı başına **böler** ve her
  dilim **ayrı belge** olur. Operatör faturayı açıp üç kalem görüyor, dördüncü
  (başka satıcının) kalem *"faturada yok"* diye okunuyor — oysa kalem eksik
  değil, **belge iki tane**. Ölçüm: dilim sayısı + her dilimin kalemleri
  (KURAL 15'in çizim dersinin fatura hâli: önce neyin üretildiğine bak).
  Tek belge isteniyorsa çare operatör seçimi — seçim varken dilimleme yok.
  Panel dışından: `seller-products.yml` → `admin_mode=seller`
  (`issue_ref=<sipariş>`, `payload='vestra'|hesap adı parçası|boş`), aynı
  kaydı yazar, **geri okur** ve dilimleri kalem kalem basar. Kesilmiş faturada
  **reddeder** (numara yanmış, belge alıcının elinde — yol KURAL 5f).
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

**KURAL 5g (devamı) — KESİLMİŞ FATURA kaldırılabilir; siparişin kendi
sayfasında da silme var** (operatör, 16 Eyl 2026: *"faturalari siparisleri
silmek icin button koy"*).
- **Siparişi silme LİSTEDE zaten vardı, DOSYA görünümünde YOKTU.** Operatör bir
  siparişi açıp inceliyor ve silmek için listeye geri dönmesi gerekiyordu; bir
  ekranda görünmeyen seçenek olmayan seçenektir (KURAL 2e'nin "açacak düğmem
  yok" dersi). Aynı eylem, aynı muhafaza — faturalı ref ilk tıklamada yine
  reddediliyor; ikinci bir silme mantığı yazılmadı.
- **TEK faturayı kaldırmanın hiçbir yolu yoktu.** Yanlış kesilmiş bir belgeden
  kurtulmanın tek yolu **siparişin tamamını** silmekti, yani dokunulmaması
  gereken dilimleri de götürmek — bir siparişte satıcı başına bir fatura olabilir
  (KURAL 5b).
- **SİLMİYOR, ARŞİVLİYOR:** `vestra_invoice_delete()` dosyaları
  `data/invoices/deleted/` altına zaman damgasıyla taşıyor. Numaralı belge yanmış
  bir numaradır ve kopyası alıcının elinde; diskten yok etmek var olan bir
  faturayı dayanaksız bırakmak olurdu — KURAL 5g'nin "faturalı teklif silinemez"
  gerekçesinin ta kendisi. Sipariş silme yolu bunu zaten yapıyordu.
- **Birleşik faturanın bağı da koparılıyor** (KURAL 5e): üyeler birincil ref'e
  `invoice_group_ref` ile bağlı ve bağ kalsaydı alıcı artık var olmayan bir
  belgenin satırını görmeye devam ederdi. Bağı kopan teklif faturasız olur ve
  onay kuyruğuna döner — karar operatörün.
- **Yazma GERİ OKUNUYOR:** `rename()` sessizce düşebilir (izin, dolu disk — bu
  depoda aynı gün kota kesintisi yaşandı) ve panel "silindi" derken dosya
  yerinde durmamalı.
- **Onay metni geri alınamaz üç şeyi birden söylüyor:** dosya taşınıyor
  silinmiyor, alıcının elinde ZATEN bir kopya var, ve yeniden kesim **YENİ** bir
  numara yakar. Ayrıca yalnız bir kalem yanlışsa **Redraft**'a yolluyor (aynı
  numara, düzeltilmiş belge — KURAL 5f).
- Test: `tests/invoice_delete_test.php` (28 iddia). **İki yönü de** tutuyor:
  aynı ref'in **diğer satıcı dilimi** ve ilgisiz bir grup bağı **YERİNDE
  kalmalı** — tek yön yazılsaydı bütün dilimleri arşivleyen bir hata yeşil
  kalırdı. Sabotajların gerçekten uygulandığı doğrulandı: ref geneline
  genişletilince **2 kırmızı**, grup bağı bloğu silinince **3**.

**KURAL 5g — düğme, operatörün BAKTIĞI ekranda da olmalı** (operatör, 16 Eyl
2026: *"siparisler ve offer lar silinmesi icin button yap demistim"*).
*Yukarıdaki maddenin kardeşi ve onunla ÇAKIŞMAZ: o, sipariş DOSYA görünümünü ve
tek faturanın arşivlenmesini anlatıyor; bu, `Admin ▸ Invoice approvals`
kuyruğunu. Aynı gün, aynı sebeple, iki ayrı ekran.*
- **Ölçüldü: iki düğme de ZATEN VARDI.** `Admin ▸ Offers ▸ 🗑 Sil`
  (`admin.php`) ve `Admin ▸ Orders ▸ Delete`. Eksik olan düğme değil **konumu**:
  `Admin ▸ Invoice approvals` kuyruğunda **hiçbir silme yolu yoktu** — ve
  operatör kabul edilmiş bir teklifi tam orada görüyor. Bir ekranda görünmeyen
  seçenek olmayan seçenektir; bu, KURAL 2e'nin *"açacak düğmem yok"* dersinin
  birebir tekrarı (operatör onu da iki kez söylemişti).
  *Talep "yok" dediğinde önce **hangi ekranda** yok diye ölç: bu turda cevap
  "hiç yok" değil, "başka sekmede" idi.*
- Kuyruğun üç bölümünden **ikisine** eklendi (bekleyen teklifler, bekleyen
  siparişler). **Kesilmiş fatura bölümüne EKLENMEDİ** — orada belge alıcının
  elinde ve numara yanmış (bu kuralın kendisi).
- **İkinci bir silme yolu YAZILMADI:** düğmeler mevcut `delete_offer` /
  `order_delete` eylemlerini çağırıyor. İkinci bir uygulama yedek almayı,
  pazarlık kaydının yedeğini (9 Eyl'de bir kez kaybedildi) ya da faturalı
  kayıtta reddi kaçırırdı — ve ayrışma ancak bir kayıt yok olduğunda görünürdü.
- **`force=1` (numarası yanmış belgeyi taşıyarak silme) yolu TEK yerde bırakıldı**
  (Orders sekmesindeki ret bandı). İki ekrandan birden ulaşılabilir yapmak, bu
  kuralın koruduğu şeyi gevşetirdi.
- **`back` izin listesi**, serbest metin değil: değer POST'tan geliyor ve bir
  `Location` başlığına giriyor; serbest bırakmak **açık yönlendirme** olurdu
  (`vestra_back_link()` için zaten yazılı ders). Tanınmayan değer varsayılana
  düşüyor, yani iki sekmenin mevcut davranışı birebir korunuyor. Gerekçesi:
  handler'lar hedef sekmeyi **sabit** yazıyordu, yani kuyruktan silen operatör
  başka bir sekmede uyanıyor ve sildiği satırın gerçekten gidip gitmediğini
  göremiyordu.
- **Silme mesajları sekme dağıtımından ÖNCE basılıyor** (ölçüldü: 2759 < 2861),
  yani bu kuyrukta da görünüyor. Görünmeseydi operatör geri dönüp **hiçbir şey**
  göremez ve düğmenin çalışmadığını düşünürdü — `billing_saved`'in bu dosyada
  kayıtlı dersi.
- **Çizdirildi, kaynak okunmadı:** `admin.php` kum havuzunda gerçekten koşturuldu
  (operatörün ekranındaki O2E880 / Casablanca satırının aynısı tohumlandı):
  iki form da basılıyor, doğru ref, `back=invoices`, CSRF var, **PHP uyarısı 0**.
- Test: `tests/admin_delete_buttons_test.php` (34 iddia). `back` kontrolü
  **davranışsal**: kapanış admin.php'nin içinde ve dışarıdan çağrılamıyor, o
  yüzden tanım kaynaktan çıkarılıp `eval` ediliyor (workflow'un "hitap" satırı
  için bir kez kullanılan teknik) — düz bir grep, satır başka bir yazımla geri
  geldiğinde yeşil kalırdı. Düşebildiği doğrulandı, her sabotajın **gerçekten
  uygulandığı** `grep -c` ile ayrıca yazdırılarak: teklif formu gizlenince
  **2 kırmızı**, izin listesi serbest bırakılınca **8**.

**Yanlışlıkla silinen teklif YEDEKTEN geri gelir** (9 Eyl 2026, O9FBF5 /
AlexaShop S.A.S / Gucci XJDEZ; operatör: *"biraz önce yanlış yazdığımdan teklifi
sildim … müşteriye teklif gönder"*).
- Silmenin aldığı `offers.csv.bak-del-<zaman>` kopyası **geri dönüşün tek dürüst
  yolu**: satırı elle yeniden yazmak, alıcının hiç vermediği bir birim fiyatı ve
  zaman damgasını uydurmak olurdu — KURAL 3'ün teklif hâli. **Yedekte yoksa iş
  DURUR**, "muhtemelen böyleydi" diye kayıt yazılmaz. Sunucuda 12 yedek vardı ve
  satır 11:56'daki silmenin kopyasında duruyordu (alıcının 11:28'deki teklifi,
  10 × €80).
- Yol: `seller-products.yml` → `admin_mode=offer_restore` (`issue_ref=<ref>`,
  `offer_price=<birim>`, `offer_apply` varsayılan **false**). Karşı teklif
  **panelin çağırdığı fonksiyondan** geçiyor (`vestra_offer_respond($ref,
  'counter', $p, null, 'VESTRA')` — `admin.php:1196`'nın birebir aynısı), yani
  KURAL 4'ün tur sayacı, fiyat tavanı, tek kullanımlık kabul token'ı ve mektup
  tek yerde kalıyor; ikinci bir pazarlık yolu yazılmadı.
- **Satır EKLENİR, dosya yeniden yazılmaz.** `vestra_read_csv()` satırları ters
  çeviriyor (`vestra_order_delete`/`vestra_order_set_shipping`'in aynı tuzağı);
  okuyup yeniden yazmak bütün listenin sırasını sessizce değiştirirdi. Sütun
  sırası **dosyanın kendi başlığından** kurulur, yedeğin sırasından değil.
- Kuru koşu varsayılan (KURAL 18) ve iki kayıt da **geri okunur**: `offers.csv`
  satırı (adet + birim eşleşiyor mu) ve `offer_responses.json` (`durum=counter`,
  `sıra=buyer`, token üretildi mi — token yoksa mektuptaki kabul düğmesi çalışmaz
  ve bunu ancak alıcı fark ederdi).
- **Silme PAZARLIK KAYDINI da yedekliyor artık** (9 Eyl 2026'da bulundu).
  `delete_offer` yalnız `offers.csv`'yi kopyalıyordu; `offer_responses.json`'daki
  kayıt **yedeksiz siliniyordu**, yani kimin kaç kez ne teklif ettiği geri
  dönülmez şekilde yok oluyordu. O9FBF5 aynı gün **iki kez** silindi ve her
  seferinde tur geçmişi gitti: satır yedekten geri geldi, **pazarlık gelmedi** —
  ikinci geri yüklemede alıcının kendi verdiği karşı teklif artık okunamıyordu.
  Şimdi `data/offer_backups/<ref>-<zaman>.json` (yalnız silinen kayıt; `data/`
  zaten tarayıcıya kapalı — `.htaccess` + `RewriteRule ^data/`).
  *Bir kaydı geri getiren yolu yazarken, o kaydın YANINDA duran ve yedeklenmeyen
  ne varsa onu da sor.*

**KURAL 5h — Fatura müşterinin KENDİ harfleriyle çıkar (CJK gömülü yazı tipi)**
(operatör, 7 Eyl 2026: *"çin karakterlerini faturaya yazamıyorum … fatura çin
adresi ile çıksın"*; alıcı 香港风徕贸易有限公司 / LINCHAOWEI, kayıt 5 Eyl 2026).
- Çizici gömülü olmayan Helvetica + WinAnsi (CP1252) kullanıyordu; CP1252 Batı
  Avrupa alfabesidir. `iconv(...//TRANSLIT//IGNORE)` Çince/Japonca/Korece/
  Yunanca/Kiril harfleri **sessizce soru işaretine** çeviriyordu: belge geçerli
  görünüyor, müşterinin adı yok. Bu depoda tekrar eden ders.
- Çözüm `vestra/assets/fonts/vestra-cjk.ttf` (WenQuanYi Zen Hei alt kümesi,
  10 MB, GPL v2 + font istisnası — yanındaki LICENSE dosyası) + `inc/pdf_font.php`
  (PHP TrueType alt kümeleyici). Belgeye **yalnızca o belgede geçen glifler**
  gömülür: Çince faturanın tamamı ~26 KB. Biçim Type0/Identity-H +
  CIDFontType2, CID = yeni glif no.
- **ToUnicode CMap şart:** onsuz belge doğru görünür ama kopyalanamaz ve
  `pdftotext` boş döker — gümrükte adresi elle yeniden yazdırmak demektir.
- **Latin belgeler değişmez:** CP1252'ye sığan bir dizge Helvetica yolundan
  gider, dosyaya font nesnesi bile eklenmez (testte iddia var).
- **Genişlik tahmin değil.** Han karakteri tam genişliktir (1 em); Helvetica'nın
  0.52 em'lik ortalaması Çince adresi yarı genişlikte sanıp satıcı kutusunun
  üzerine bindiriyordu. Tek ölçüm yeri `vestra_pdf_width()`; `VestraPdf::strWidth`
  ve `vestra_invoice_wrap()` ikisi de onu çağırır. Çincede boşluk yok, sarma
  karakter karakter kırıyor.
- **Taslak uyarısı yanlış yere bakıyordu (7. vaka).** Tarama `$order['company']`
  okuyordu, oysa gerçek yükte alıcı alanları `$order['buyer']` altında
  (`vestra_invoice_buyer`). Yani uyarı **canlı bir faturada hiç çalışmadı**;
  yalnızca düz dizi veren testte "çalışıyor" görünüyordu. Artık `buyer[]`
  taranıyor ve uyarı **kod noktası** yazıyor (`U+1F9F5`) — basılamayan karakteri
  uyarının içine koymak uyarıyı da okunmaz yapıyordu.
- Yazı tipi sunucuya gitmezse eski davranışa döner ve **taslak bunu söyler**;
  sessiz kayıp olmaz. Test: `tests/pdf_cjk_test.php` (53 iddia).
- **Canlı ölçüm (7 Eyl 2026, `diag-live` → `order_sheet_ref=VES-6B53D265`,
  `order_doc=invoice`):** önizleme sunucuda üretildi, şifreli döndü, çözüldü —
  belge alıcının unvanını **kendi harfleriyle** basıyor, `/FontFile2`,
  `/Identity-H`, `/ToUnicode` yerinde, 23,9 KB. Aynı koşu **iki veri boşluğu**
  gösterdi: (1) hesapta **sokak adresi yok** (gümrük/kurye ister),
  (2) vergi alanına **bölgenin adı** yazılmış ("中国香港特别行政区") ve belge
  bunu "VAT ID" diye basıyordu. **Rakamsız bir değer vergi numarası değildir:**
  artık basılmaz, ve iki eksik de **yalnız taslakta** operatöre yazılır. İkisi
  de veri düzeltmesi ister (`Admin ▸ Users ▸ ✎ Edit billing details`).

**KURAL 5i — Fatura başka para biriminde kesilebilir; kur SİPARİŞ TARİHİNİN kuru**
(operatör, 7 Eyl 2026, VES-6B53D265 / 香港风徕贸易有限公司, €4.680,00:
*"usd ye cevir faturayi"*).
- **Siparişin para birimi kayıttır, değişmez.** Belge hangi birimde kesilecek
  ayrı bir operatör kararı: `order_statuses.json[ref].invoice_currency`
  (`Admin ▸ Invoice approvals`'ta satıcı seçicisinin yanında). İzin listesi dar:
  **EUR, USD** — kod yalnız bunu çevirebiliyor, çeviremediğini kabul etmek
  sessizce yanlış rakam basmak olurdu.
- **Kur, siparişin FX damgası** (`inc/fx_orders.php`, Operasyonel notlar'daki
  USD sistemi). Bugünün kuru DEĞİL: Temmuz siparişini Eylül kuruyla çevirmek
  Temmuz'da tahsil edilen tutarı değiştirirdi. **Damga yoksa fatura KESİLMEZ**
  (`vestra_issue_order_invoices` → `['error'=>…]`, hiçbir numara yakılmaz);
  panel kırmızı bant basar ve `⟳ Fetch missing rates`'e yollar.
- Çevrim tek yerde: `vestra_invoice_convert_payload()` (saf) +
  `vestra_order_invoice_payloads()`. **Birim** fiyat çevrilip yuvarlanır,
  satır = birim × adet — ters sıra belgede birim × adet ≠ satır çıkarırdı.
- Belge **hangi kurla çevrildiğini yazar** (`fx_note`, tutarların hemen altında;
  kaynak + tarih, ECB olmayana ECB denmez). Yazmazsa alıcının muhasebecisi kendi
  kurunu uygular ve ödeme soru sorulurken bekler.
- **Ödeme kutusu boş kalabilir:** `vestra_payment_rails` USD için hesap no + ABA
  ister; yalnız IBAN'ı olan bir satıcı USD faturada ödeme kutusuz çıkar. Taslak
  bunu yazar (alıcı aksi hâlde bunu fatura elindeyken görürdü). Çözüm operatörün:
  ya hesaba ABD alanlarını ekler (KURAL 5c) ya da faturayı hesabın alabildiği
  birimde keser / KURAL 5b ile başka satıcıyı seçer.
- **Teşhis önizlemesi artık aynı yükten çiziyor:** `diag-live` → `order_doc=invoice`
  kendi `$meta`'sını elde kuruyordu (KURAL 5d'nin yasakladığı ikinci kopya);
  şimdi `vestra_order_invoice_payloads()`. `order_doc=invoice:usd` seçimi
  **kayda yazmadan** o birimde önizler. Test: `tests/invoice_currency_test.php`.
- **Canlı ölçüm, 7 Eyl 2026 (`order_doc=invoice:usd`, run 257):** çevrim doğru
  çalışıyor — birim €39,00 → **US$45,33**, satır **US$5.439,60**, `fx_note`
  belgede (`1 EUR = 1,1622 USD, ECB 4 Sep 2026`), Çince unvan kendi harfleriyle,
  23,6 KB. O koşuda **USD ödeme kutusu çıkmıyordu** ve sebebi ilk sanıldığı gibi
  "platformun banka hesabı yok" **değildi** — bkz. KURAL 5j: platformun banka
  bilgileri kayıtlıydı, **çizici o kaydı hiç okumuyordu**. Düzeltildikten sonra
  aynı belge **dolu ödeme kutusuyla** çıkıyor (hesap no + ABA + SWIFT + banka
  adı/adresi + `Payment reference: <ref>`; 7 Eyl 2026, run 261 canlı önizlemesi).
  Ölçümün yan bulgusu geçerli: siparişin kayıtlı fatura kesicisi **VESTRA
  platform** (`invoice_seller_uid='vestra'`, operatörün panel seçimi; ilanın
  `seller_uid`'i dolu ve GARAGE LE PARIS'i gösteriyor ama seçim onu **eziyor** —
  KURAL 5b'nin sırası), ve **GARAGE LE PARIS'in USD yolu yok**
  (IBAN/BIC/banka/lehdar dolu, `bank_account`/`bank_routing` **boş** —
  `diag-messages` → `billing_for=garage`), yani o hesaptan USD kesilirse kutu
  boş çıkar. *"Kutu boş" gördüğünde önce kimin kestiğine ve kodun NEREDEN
  okuduğuna bak; eksik veri sanılan şey okunmayan veri olabilir.*
- **Aynı sipariş iki önizlemede iki farklı kesen taraf yazdı** (GARAGE LE PARIS,
  sonra VESTRA) ve günlükten hangisinin doğru olduğu **okunamıyordu**: hesap
  araması boş dönünce çıktı, operatör seçimi ile boş `seller_uid`'de birebir
  aynı. `diag-live` artık `satici kaynagi` satırını basıyor (kayıtlı seçim mi,
  ilanın `seller_uid`'i mi, kaç satırda dolu). *Çelişkiyi gösterip
  çözdürmeyen bir teşhis, yarım teşhistir.*
- **Taslak notu, kutunun KESİN çıkmadığı hâli atlıyordu:** koşul
  `$sellerAcc !== null` idi, yani platform dilimi — hiç banka hesabı bağlı
  olmayan tek durum — hiçbir uyarı üretmiyordu. Artık platform da uyarıyor,
  ödenmiş escrow siparişinde ise uyarmıyor (orada kutu zaten bilerek
  çizilmiyor). Notlar **layout'tan önce** hesaplanıyor
  (`vestra_invoice_draft_notes()`, saf): üçüncü not eklendiği gün blok
  altbilginin üzerine binmişti ve iki metin birden okunmaz olmuştu.
- **"Havale yaptıktan sonra kısa bir haber versin"** (operatör, 7 Eyl 2026):
  ödeme şartları satırı bunu belgenin kendisinde istiyor (e-postayla yanıt ya da
  sipariş sayfasındaki dekont kutusu — KURAL 7'nin kartı). Escrow faturasına
  yazılmıyor: havale yok.
- **Tek tık düğmesi** (operatör, 7 Eyl 2026: *"siparişi dolara çevirme buttonu
  yap"*): `Admin ▸ Orders ▸ <sipariş>` içinde, **"Approve & issue"in yanında**
  `💱 Invoice in USD` / `↩ Back to EUR`. Invoice approvals'taki açılır liste
  duruyor; karar bu ekranda veriliyor ve bir ekranda görünmeyen seçenek olmayan
  seçenektir (KURAL 2e'nin "açacak düğmem yok" dersi). Aynı kayıt, aynı
  doğrulayıcı. Kullanacağı kuru yazar, damga yoksa **kırmızı** uyarır.
  **Fatura kesilmişse düğme çizilmez ve sunucu ayrıca reddeder** (`invoice_cur_late`)
  — belge alıcının elinde, seçim onu değiştirmez. Panel dışından aynı yazma:
  `seller-products.yml` → `admin_mode=currency` (numara yakmaz, belge üretmez,
  kimseye gitmez; yazdığını **geri okur**).

**KURAL 5j — VESTRA kendi adına kesiyorsa belge de VESTRA'nın künyesini taşır**
(7 Eyl 2026, VES-6B53D265; operatör: *"hayır VESTRA olacak satıcı"*).
- Panel platformun kendi fatura/banka künyesini **topluyordu**
  (`vestra_platform_seller()`, `data/platform_seller.json`,
  `Admin ▸ Orders ▸ 🏦 Platform billing & bank details`) ve boş bırakılınca
  *"not set — invoices will have no payment box"* diye uyarıyordu; **çizici o
  kaydı hiç okumuyordu.** Platform faturası üç sabit satır basıyordu — `VESTRA
  (Acerasoft LLC) / Marketplace-catalog item / support@…` — adres yok, vergi
  kimliği yok, ödeme kutusu yok, ve **alanları doldurmak hiçbir şeyi
  değiştirmiyordu** (`vestra_payment_rails($sellerAcc ?? [], …)` boş dizi
  geçiyordu). *Toplanan ama okunmayan alan.*
- Artık satıcı kutusu ve ödeme yolu `vestra_platform_seller()`'dan geliyor:
  kayıtlı adres + EIN basılıyor, banka alanları dolunca **ödeme kutusu çıkıyor**.
  USD için yine hesap no + ABA gerekiyor (KURAL 5i) ve taslak notu artık
  **doğru sayfaya** yolluyor (Admin ▸ Orders), "başka satıcı seç" demiyor —
  o düzeltme değildi.
- **Belge kendini yalanlıyordu:** üstte `Seller of record: Acerasoft LLC`,
  altı satır aşağıda *"VESTRA (Acerasoft LLC) … is not the seller of record for
  this sale"*. Tam bu çelişkiyi önlemek için yazılmış kontrol
  (`$platformIsSeller`) `$sellerAcc['company']` içinde "acerasoft" arıyordu;
  platform diliminde `$sellerAcc` **null**, yani var olma sebebi olan tek
  durumda hiç çalışmıyordu. (Bu depoda "kontrol yanlış yere bakıyor"un bir
  başka örneği.)
- **Düşemeyen iddia:** ilk testte feragat cümlesi `is not the seller of record`
  diye arandı; cümle sarıldığı için PDF'te bitişik geçmiyor, yani iddia **hiçbir
  belgede** bulamıyor ve iki yönde de "geçiyordu". Sarmayı atlatan bir parça
  aranıyor artık (`operates the marketplace`). *Hiç düşemeyen bir iddia, iddia
  değildir.*
- **Ödeme kutusunu TEK kaynak kuruyor** (`vestra_payment_rails`). Kutu ayrıca
  `Account holder`, `Beneficiary bank` ve `Bank address` satırlarını kendisi
  ekliyordu; rails zaten üçünü de basıyor, yani canlı USD taslağında **lehdar ve
  banka iki kez, iki ayrı etiketle** çıktı ("Account holder: X" + "Beneficiary:
  X"). Ödemeyi yapan tek bir lehdar bankası arar; aynı şeyi iki adla yazan kutu
  iki ayrı hesap sanılır. Kutu artık rails + `Payment reference`; banka etiketi
  ("Beneficiary bank", ödeyenin formundaki kelime) rails'in içine taşındı.
  **Yıllarca görünmedi çünkü bu alanlar ancak platform kendi künyesinden
  kesmeye başlayınca birlikte doldu.**
- Test: `invoice_currency_test.php §5b`.
- **Platform renderer'a İKİ AYRI ŞEKİLDE geliyor ve `$sellerAcc === null` diye
  yazılmış her kontrol bunun yarısını kaçırıyordu** (9 Eyl 2026). Kurasyonlu
  ilanın **SİPARİŞ** dilimi `null` geçiyor (`vestra_order_invoice_payloads`,
  'vestra' anahtarına hesap konmuyor), aynı ilana verilen **TEKLİF** ise
  platformun **KAYDINI** geçiriyor (`vestra_offer_invoice_seller` hiçbir zaman
  null dönmez, `vestra_platform_seller()`'a düşer). Aynı mal, aynı kesen taraf,
  iki farklı cevap. İki kontrol bu yüzden yanlış çalışıyordu: (1) ödeme kutusu
  boşken operatörü **yanlış sayfaya** yollayan taslak notu (teklifte
  `Admin ▸ Users` diyordu — platformun banka bilgisinin **duramayacağı** sayfa;
  yanlış sayfaya yollayan bir yönerge, hiç yönergeden pahalıdır çünkü
  uygulanır), (2) satıcı kutusu hesap dalından çiziliyor, yani aynı satış
  sipariş olarak farklı bir künye taşıyordu. Tek ayırt edici artık
  `vestra_invoice_is_platform_issuer()` ve ölçüt **hesap id'si**:
  `auth_accounts()`'tan gelen her kayıtta var, `vestra_platform_seller()`'da
  yok. **Ad testi bilerek yok** — operatör bir gün gerçek bir Acerasoft satıcı
  hesabı açarsa o hesap *seller of record*'dur ama banka bilgisi yine
  `Admin ▸ Users`'ta durur; feragat cümlesi bloğu daha geniş soruyu soruyor ve
  ad kolunu kendi çağrı yerinde tutuyor. Test: `invoice_draft_test.php §6`
  (42 iddia; iki çağrı yeri geri alınınca **3 kırmızı**).

**KURAL 5j (devamı) — Platformun EUR/SEPA hesabı: iki banka, tek düz kayıt; EUR
rayı KENDİ banka adını/adresini basar** (operatör, 17 Eyl 2026: Banking Circle
S.A. üzerinde Acerasoft LLC adına Almanya/SEPA IBAN'lı hesap — *"avrupadan
siparis geldiginde otomatik Vestra siparislerine bu bankayi ekle"*).
- **Rakamlar bu dosyaya, iş akışı girdisine ve ssh betiğine GİRMEDİ** (Güvenlik
  bölümünün kuralı). IBAN/BIC'i operatör panele kendisi girer:
  `Admin ▸ Orders ▸ 🏦 Platform billing & bank details`. Doğrulama sunucuda,
  maskeli: `diag-messages` → `billing_for=vestra` (yalnız VAR/YOK + hane sayısı
  ve "EUR faturasında ödeme kutusu: ÇIKAR (n satır)").
- **"Otomatik" zaten mekanizmanın kendisi:** `vestra_payment_rails` kutuyu
  faturanın PARA BİRİMİNE göre seçer — EUR → IBAN rayı, USD → hesap no + ABA.
  Avrupalı alıcının siparişi EUR'dur; IBAN girildiği andan itibaren platformun
  kestiği her EUR fatura bu bankayı taşır, USD faturalar Choice Financial'da
  kalır. Şart: kesen taraf VESTRA (KURAL 5b) — GARAGE LE PARIS/TYREX kendi
  hesabını basmaya devam eder. Yeni bir "Avrupa'dan geldi" dalı YAZILMADI: ülke
  değil para birimi ölçüt, çünkü ödeme kutusu faturanın birimine ait.
- **Panel formunda EUR rayının alanları YOKTU:** yalnız `bank_iban` + tek
  `bank_bic` + tek `bank_name`/`bank_address`. SWIFT'i `bank_bic`'e yazmak ABD
  bankasının BIC'ini ezer, banka adını `bank_name`'e yazmak USD faturasına Alman
  bankasının adını bastırırdı — iki banka, tek düz kayıt. Eklendi:
  `bank_eur_bic` (satıcı formunda zaten vardı, platform formunda yoktu),
  `bank_eur_name`, `bank_eur_address` — iki formda ve iki kayıt yolunda.
- **Rails kuralı BIC'inkiyle aynı (5 Eyl):** ABD hesabı da varken EUR kutusu
  ad/adresi yalnız `bank_eur_*`'dan basar, yoksa hiç basmaz (SEPA'da IBAN yeter;
  çelişen çift eksik satırdan pahalı). ABD hesabı yoksa eski `bank_name`/
  `bank_address` aynen — yalnız IBAN'ı olan satıcılar bozulmaz. **Eski test
  tersini pinliyordu** (*"banka adı EUR tarafında da var"* — ABD hesabı TAŞIYAN
  bir kayıtta): davranış bilerek değişti, test düzeltildi.
- **Platform kayıt yolu IBAN'ı hiç doğrulamıyordu** (satıcı yolu KURAL 5c'den
  beri doğruluyordu). Artık mod-97 geçmezse HİÇBİR alan kaydedilmez
  (`platform_billing_iban_bad`); BIC/routing/hesap no satıcı yoluyla aynı biçime
  getiriliyor.
- Test: `payment_rails_test.php §2b` (60 iddia, iki yön: EUR kutusunda ABD
  bankası YOK, USD kutusunda EUR bankası YOK, yalnız-IBAN satıcı eskisi gibi,
  form/kayıt yolu kablolaması). Sabotajın gerçekten uygulandığı `grep -c` ile
  doğrulanarak: rails kuralı geri alınınca **3 kırmızı**, IBAN kapısı silinince **2**.

**KURAL 5r — ÖDEME KUTUSU BOŞSA FATURA KESİLMEZ; "otomatik kullanılmıyor" bir
VERİ sorusuydu, kod sorusu değil** (operatör, 19 Eyl 2026: *"sana verdigim
acerasoft LLC alman hesabi avrupa müsterilerinde otomatik kullanilmiyor … bu
iki sipariste faturalarin alman banka hesabinin kullanilmasi gerekiyor"* —
`VES-55E4F6E1` / Mob / FR / €1.200 ve `VES-A11C0C97` / LA ISLA DE MIRABEL SL /
ES / €7.779,60).

- **ÖNCE ÖLÇÜLDÜ, iki ayrı şüpheli ayrı ayrı elendi.** "Otomatik kullanılmıyor"
  iki bambaşka şey olabilirdi: (a) künyede IBAN yok, (b) IBAN var ama o
  siparişlerin fatura kesicisi VESTRA değil. Ölçüm (`diag-messages` →
  `billing_for=vestra`): `bank_holder`, `bank_name`, `bank_bic`,
  `bank_account` **VAR (12 hane)**, `bank_routing` **VAR (9 hane)** — ama
  **`bank_iban` BOŞ**, ve `bank_eur_bic/name/address` de boş. Sonuç satırı:
  *"EUR faturasında ödeme kutusu: **ÇIKMAZ (boş)** · USD faturasında: ÇIKAR
  (6 satır)"*. (b) ise doğru çalışıyordu: `diag-live` → `find_ref` iki siparişte
  de **`invoice_seller_uid=vestra`** gösterdi, yani operatörün seçimi kayıtlı.
- **"Otomatik" zaten mekanizmanın kendisi ve KODDA BİR ŞEY EKSİK DEĞİLDİ.**
  `vestra_payment_rails` rayı faturanın **para birimine** göre seçiyor (EUR →
  IBAN, USD → hesap no + ABA); Avrupalı alıcının siparişi EUR olduğu için IBAN
  girildiği an her EUR fatura o bankayı taşır. Ülkeye bakan yeni bir dal
  YAZILMADI — ödeme kutusu faturanın birimine ait, alıcının ülkesine değil.
  *17 Eylül'de yazılan "IBAN'ı operatör panele kendisi girer" satırı ile bugünün
  şikâyeti arasındaki mesafe bir form doldurma; kod o gün de hazırdı.*
- **ASIL BULGU BAŞKAYDI: KESİM YOLUNDA HİÇBİR MUHAFAZA YOKTU.** Taslak (KURAL
  5d) *"ödeme kutusu yok"* diye **yazıyordu** — ama yalnızca taslakta. Operatör
  **👁 Draft**'a hiç basmadan **✓ Approve & issue**'ya basabiliyordu: numara
  yanar, belge alıcıya e-postalanır, ve `vestra_order_invoice_issue()`'nun
  mektubu *"pay by bank transfer to the account shown on the invoice"* der —
  **belgede o hesap YOKKEN**. Yanlış yere yollayan bir yönerge hiç yönergeden
  pahalıdır çünkü alıcı onu uygulamaya çalışır. €7.779,60'lık sipariş tam bu
  durumdaydı ve bugün kesilebilirdi.
- **Tek karar noktası: `vestra_invoice_payment_gap($sellerAcc, $cur, $paid)`**
  (saf; boş dizge = sorun yok, dolu = insan diliyle sebep). Taslak notu artık
  **kendi** `vestra_payment_rails` sorgusunu taşımıyor, bu gövdeyi çağırıyor —
  iki kopya yazılsaydı taslak "kutu yok" derken kesim geçerdi (ya da tersi) ve
  bu deponun defalarca kaydettiği ayrışmanın en pahalı hâli olurdu: numara
  yanmış, belge alıcıda.
- **Nerede DURDURUYOR:** `vestra_issue_order_invoices()` (panel *Approve &
  issue* + iş akışı `admin_mode=issue`), `vestra_offers_combined_invoice_issue()`
  ve `vestra_offer_issue_invoice($force=true)`. Üçü de çevrilemeyen para
  biriminin **yanına**, aynı "hep ya da hiç" kalıbıyla.
  - **REDRAFT MUAF:** orada numara **zaten yanmış** ve yeniden çizim tam da
    düzeltmenin yolu (KURAL 5f) — kutusuz bir belgeyi düzeltmeyi engellemek,
    muhafazanın koruduğu şeyin tersi olurdu.
  - **`$force=false` MUAF:** o dal hiçbir numara yakmıyor (teklifin kabul anı);
    orada durmak teklifin **kabul edilmesini** engellerdi.
  - **ÖDENMİŞ sipariş MUAF:** escrow/kart faturasında kutu zaten bilerek
    çizilmiyor; orada "kutu yok" demek olmayan bir eksiği bildirmek olurdu.
  - **Birleşikte kontrol KAYITTAN ÖNCE** (KURAL 5n'in sırası): sonra olsaydı
    teklifler bir gruba bağlanır ama faturasız kalırdı.
- **Panel iki yerden söylüyor:** onay satırında, düğmeye basmadan **önce**
  kırmızı çip (*"⚠ ödeme kutusu YOK — kesilemez"*), ve ret hâlinde **kendi
  bandı** (`invoice_nopay`). Bant seçimi `error_code` ile, metne bakarak değil:
  `str_contains` ile karar vermek, cümle bir gün değişince bandı sessizce
  *"para birimi çevrilemedi"*e döndürürdü — **rakam doğru, etiket yalan**, ve
  operatör olmayan bir kur sorununu çözmeye çalışırdı.
- **BU SATIR YANLIŞTI VE DÜZELTİLDİ (19 Eyl 2026).** Burada önce
  *"operatörün verdiği IBAN rakamları repoya … GİRMEDİ"* yazıyordu. **Girmişti:**
  IBAN `tests/invoice_payment_gap_test.php`'e **fikstür** olarak yazılmış ve
  `c6b66885` ile **herkese açık** depoya push edilmişti (iki dal). Kuralı
  yazdığım cümlenin altında kuralı çiğniyordum ve kendi taramam da *"repoda
  banka rakamı yok"* demişti — **çünkü yalnız iş akışı ve kaynak dosyalarını
  taramıştım, testleri değil.** Çalışma ağacında artık her IBAN belgesinde
  örnek olarak geçen **sentetik** Deutsche Bank numarası var (mod-97 geçiyor,
  yani ölçülen davranış değişmedi); **geçmişte duran kopya silinmedi** —
  `claude/*` dallarında başka bir oturum da çalışıyor ve başkasının dalında
  geçmiş yeniden yazılmaz. Operatöre söylendi, karar onun.
  *Ders: "sızıntı var mı" taraması, kodun taranmadığı hiçbir dosyayı dışarıda
  bırakamaz — `git ls-files | xargs grep` kullan, elle seçilmiş bir liste değil.*
- **İş akışı girdisine ve ssh betiğine girmedi** (Güvenlik bölümünün kuralı,
  17 Eyl'deki kararın aynısı).
  Yapılan tek şey **sitenin kendi doğrulayıcısıyla** kontroldü:
  `vestra_iban_normalize` + `vestra_iban_valid` → **DE, 22 hane, mod-97
  GEÇERLİ**. Bu boşuna değil: geçmeseydi panel **hiçbir alanı** kaydetmezdi
  (`platform_billing_iban_bad`) ve operatör "girdim ama olmadı" derdi.
- **ÜÇ ALAN GEREKİYOR, BİR DEĞİL — ve bu ölçülerek söylendi.** Platformun ABD
  hesabı da dolu olduğu için EUR rayı banka adını/adresini **yalnız**
  `bank_eur_*`'dan basıyor (KURAL 5j: çelişen bir çift, eksik satırdan pahalı).
  Yerel ölçüm: yalnız `bank_iban` → kutu **2 satır** (lehdar + IBAN);
  `bank_iban` + `bank_eur_bic` + `bank_eur_name` → **4 satır**; aynı kayıtta
  **USD kutusu bozulmuyor** (Choice Financial aynen).
  **`bank_eur_address` BOŞ bırakıldı:** operatörün verdiği *"Germany (SEPA)"*
  bir konum, banka adresi değil — uydurmak KURAL 3'ün yasakladığı şey.
- Test: `tests/invoice_payment_gap_test.php` §1–§4 (**48 iddia**, iki yön;
  dosya KURAL 5s ile **75**'e çıktı). §3 kum
  havuzunda **gerçekten kesim deniyor**: IBAN yokken ret + **diskte 0 belge**,
  IBAN girilince kesim geçiyor ve üretilen **PDF'te `IBAN:` satırı var** —
  "kesildi" tek başına yetmez, bu depo fotoğrafsız bir PDF'i yıllarca
  "üretildi, boyut makul" diye geçirmişti.
- **Falsifikasyon TESTİMDE GERÇEK BİR BOŞLUK BULDU.** Teklif dallarındaki iki
  muhafaza `if (false) return …` yapıldığında takım **YEŞİL kaldı**: iddialarım
  yalnız `vestra_invoice_payment_gap(` çağrısının **var olduğunu** sayıyordu ve
  o çağrı sabotajda da metinde duruyordu. *Hiç düşemeyen bir iddia, iddia
  değildir* — bu dosyada kayıtlı ve bir kez daha oldu. İddialar sonuca bakacak
  şekilde daraltıldı (`if ($gap !== '') return`) ve aynı sabotaj **2 kırmızı**
  verdi. Diğerleri, her sabotajın gerçekten uygulandığı `grep -c` ile
  yazdırılarak: sipariş muhafazası kalkınca **7 kırmızı**, `paid` muafiyeti
  yok sayılınca **1**, taslak yine kendi rails'ini sorunca **1**, bant
  yönlendirmesi geri alınınca **1**.
- **Davranış bilerek değişti, o yüzden iki test düzeltildi** (bu deponun kendi
  kuralı): `invoice_currency_test`'in taslak iddiası cümlenin **yazımını**
  pinliyordu (`'no payment details'`) ve cümle artık panel çipinde tek başına
  durduğu için büyük harfle başlıyor. Olumsuz kardeşi daha sinsiydi — harfe
  bağlı kalsaydı tek bir büyük harf onu **her zaman geçer** hâline getirirdi.
  İkisi de `stripos` ile olguya bağlandı. Ayrıca üç test fonksiyon gövdesini
  `eval` ile çıkarıyor ve require'ları siliyor (`offers_rounds`, `offers_flow`,
  `invoice_seller_pick`): üçüne de belgeli **stub** kondu — o dosyalar pazarlık
  turlarını ve kesimin tek gövdede olduğunu ölçüyor, ödeme kutusunu değil.
- **Bu işten bağımsız, ÖNCEDEN kırık ve ölçülerek doğrulandı** (temiz bir
  `git worktree` HEAD kopyasında birebir aynı sayılar): `dropship_plan_test`
  **4**, `msg_read_receipt_test` **1**, `msg_thread_label_test` **10**.
  Dokunulmadı.

**KURAL 5s — AVRUPA DIŞI alıcıda belge USD doğar; ray zaten BİRİME bakıyor**
(operatör, 19 Eyl 2026: *"ABD ve Avrupa disinda ABD hesabi, diger Avrupa icinde
ise Alman hesabi kullanilacak"*. Seçenekler sunuldu, **"Avrupa dışı fatura USD
kesilsin"** seçildi).

- **RAYI DEĞİL PARA BİRİMİNİ SEÇİYORUZ, ve bu işin tamamı.** `vestra_payment_rails`
  rayı faturanın **birimine** göre zaten seçiyor (EUR → IBAN, USD → hesap no +
  ABA), yani "Avrupa dışında ABD hesabı" cümlesinin kod karşılığı **yeni bir
  hesap seçici değil**: belgenin birimi. Bölgeye bakan ikinci bir seçici
  yazılsaydı aynı soru iki yerde cevaplanır ve ikisi er geç ayrışırdı; daha
  kötüsü, **EUR yazan bir belgenin altına USD bir hesap** basardı — ödeyen
  taraf euro gönderir, banka dönüştürür, tutar tutmaz.
- **Tek karar noktası `vestra_invoice_currency_default($sellerAcc, $ulke,
  $temelBirim)`** (saf; boş dizge = değişiklik yok). **ÜÇ KAPI, üçü de bilerek
  dar ve üçü de ayrı ayrı düşebiliyor:**
  1. **Yalnız PLATFORM kesiminde.** Satıcı hesaplarının çoğunda yalnız IBAN var
     (GARAGE LE PARIS, TYREX — 7 Eyl'de ölçüldü); onları USD'ye zorlamak ödeme
     kutusunu **boşaltır** ve KURAL 5r o belgeyi hiç kestirmez. Yani "düzeltme"
     bir satıcının bütün faturalarını kesilemez yapardı.
  2. **Yalnız EUR kayıtlı satışta.** Zaten başka bir birimdeki satışı yeniden
     hedeflemek, operatörün vermediği bir karar olurdu.
  3. **Ülke TANINIYORSA.** `vestra_user_in_europe()` boş/tanınmayan ülkede
     **TRUE** dönüyor, yani belirsizlikte bugünkü davranış (EUR) korunuyor.
     Yön bilerek böyle: fazla sorulan bir soru görünür, sessizce değiştirilmiş
     bir para birimi görünmez (KURAL 27'nin Avrupa testindeki aynı karar).
- **OPERATÖRÜN KAYITLI SEÇİMİ HER ZAMAN ÖNDE** (KURAL 5i): bu bir **varsayılan**,
  dayatma değil. `order_statuses.json[ref].invoice_currency` doluysa hiç
  çalışmıyor — testte ayrı iddia var.
- **SİPARİŞTE ölçüt `array_keys($bySeller) === ['vestra']`.** Birim siparişin
  **tamamına** işliyor, dilim başına değil (KURAL 5b: sipariş satıcı başına
  bölünüyor). Karışık bir siparişi USD'ye zorlamak, yalnız IBAN'ı olan satıcı
  dilimini ödeme kutusuz bırakır ve KURAL 5r'nin muhafazası **siparişin
  tamamını** kesilemez yapardı.
- **TEKLİF YOLLARI DA ATLANMADI** (`vestra_offer_invoice_payload` +
  `vestra_offers_combined_invoice_payload`). Atlansaydı aynı Amerikalı alıcı
  siparişinde USD, kabul ettiği teklifte EUR belge alırdı — KURAL 5m'in
  birleşik çubukta eksik kalan KDV oranıyla bir kez ödediği ders.
- **PANEL DOĞRUYU YAZIYOR, ve bu iki ayrı düzeltme gerektirdi:**
  - Para birimi seçicisi *"— sipariş birimi (EUR) —"* diyordu; varsayılan
    devredeyken bu **düpedüz yalan** olurdu (operatör EUR sanıp USD bir belge
    keserdi). Etkin birim artık **taslak düğmesinin okuduğu AYNI yükten**:
    önce `want_currency`, sonra `meta['currency']`. Sıra önemli — çevrilemeyen
    bir yükte meta **eski** birimde kalıyor ve istenen birim yalnız
    `want_currency`'de duruyor.
  - **Sipariş birimi listeden ATLANIYORDU** (`if($__c===$__ocur) continue;`) ve
    boş değer artık "otomatik" demek, yani operatör bölge varsayılanını **EUR'ya
    geri çeviremiyordu**. Seçeneği göstermeyen bir form, olmayan bir seçenektir
    (KURAL 2e'nin *"açacak düğmem yok"* dersi). Liste artık tam; sipariş birimi
    etiketli.
- **ÖLÇÜLEN BEDEL, nesirde bırakılmadı:** damgasız bir Avrupa dışı sipariş artık
  **kesilemiyor** — belge USD olmak istiyor, çevrim siparişin **damgalı** kurunu
  şart koşuyor (KURAL 5i) ve damga yoksa yük gerekçe dönüyor. Önce EUR olarak
  geçerdi. Bu bir iddia olarak yazılı ve satırda **çipi var**: *"⚠ kur damgası
  yok — kesilemez (⟳ Fetch missing rates)"*, tıklamadan **önce** — ödeme kutusu
  çipiyle aynı ilke, çünkü aksi hâlde operatör sebebini ancak reddedilince
  öğrenirdi. Siparişler zaten yazılırken damgalanıyor, yani bu dar bir hâl.
- **Bekleyen iki sipariş bundan ETKİLENMİYOR ve kontrol grubu tam olarak onlar:**
  `VES-55E4F6E1` (FR) ve `VES-A11C0C97` (ES) **Avrupa**, yani her okumada EUR
  kalıyor ve Alman hesabını alıyorlar. Testte ayrı iddia var — tek yön ölçülseydi
  "her siparişi USD yapan" bir kusur da yeşil görünürdü.
- Test: `tests/invoice_payment_gap_test.php` §5/§5b/§5c (dosya **75 iddia**).
  §5b kum havuzunda **gerçek yükü** kuruyor (ABD siparişi → USD, Fransa kontrol
  grubu → EUR, kayıtlı EUR seçimi varsayılanı eziyor, damgasız ABD siparişi
  duruyor). Yakın-komşu tuzakları adıyla: **AT (Avusturya) Avrupa ↔ AU
  (Avustralya) değil**, ve GB/CH Avrupa ama euro değil → yine EUR kalıyor.
- Düşebildiği doğrulandı, her sabotajın **gerçekten uygulandığı `grep -c` ile
  ayrıca yazdırılarak**: platform kapısı kalkınca **1 kırmızı**, EUR kapısı
  kalkınca **1**, Avrupa ölçütü ters çevrilince **19**, sipariş kurucusundaki
  kablo silinince **3**, teklif yollarındaki kablo silinince **1**, panel yine
  sipariş birimini listeden atlayınca **1**, kur-damgası çipi silinince **1**,
  seçici yine önce meta'ya bakınca **1**.
  *Bir sabotaj doğrulamam yine yanlış yere baktı:* `grep -c` deseni kodun
  yanındaki **yorumda** da geçiyordu ve "uygulanmadı" dedirtti; desen satırın
  kendisine daraltılınca çıktı. Bu dosyada kayıtlı tuzak, bir kez daha.
- **Kendi ölçüm hatam, kayda geçsin:** *"damgasız ABD siparişinde belge USD"*
  iddiasını `meta['currency']`'ye bakarak yazdım ve **düştü** — çevrilemeyen bir
  yükte meta bilerek eski birimde kalıyor. **Kod haklıydı, iddia yanlıştı;**
  çevrilememiş bir yüke "USD" demek, belgenin taşımadığı bir birimi iddia etmek
  olurdu. İddia `want_currency`'ye bağlandı.
- Üç test fonksiyon gövdesini `eval` ile çıkarıp require'ları siliyor
  (`offers_rounds`, `offers_flow`, `invoice_seller_pick`): üçüne de belgeli
  **stub** kondu — ve stub **güvenli olduğu için** kondu: o dosyaların alıcı
  kayıtları RO / PL / ülkesiz, yani gerçek gövde de `''` dönüyor. Stub ile
  gerçeklik aynı cevabı veriyor, farklı bir cevabı örtmüyor.

**KURAL 5i (devamı) — TEKLİF faturası da USD kesilebilir; kur TEKLİFİN tarihinin**
(operatör, 9 Eyl 2026, OCD7D2: *"burada neden fatura yaparken banka bilgileri
cikmiyor ?"* + *"ayrica direkt usd ye cevirme buttonu eksik"*). **İki cümle tek
sorundu.**
- **Ölçüm önce** (`diag-messages` → `billing_for=vestra`; sonda o güne kadar
  yalnız **hesapları** arıyordu, yani kurasyonlu malda ödeme kutusunu belirleyen
  tek kayıt, hiçbir sondanın bakamadığı kayıttı — eklendi). Platformun künyesi
  **boş değil**: `bank_holder`, `bank_name` (*Choice Financial Group*),
  `bank_bic`, `bank_account` **VAR (12 hane)**, `bank_routing` **VAR (9 hane)**,
  ama `bank_iban` **BOŞ**. Sonuç: **EUR faturada ödeme kutusu ÇIKMAZ, USD
  faturada ÇIKAR (6 satır).** Yani VESTRA'nın kendi hesabı bir **ABD hesabı** ve
  euro rayı yok; `vestra_payment_rails` bir euro faturaya ABA routing basmak
  yerine **hiçbir şey basmamayı** seçiyor (doğrusu bu: Avrupalı alıcı o yola
  ödeyemez, kabul eden banka günler sonra iade eder).
- KURAL 5i'nin bütün makinesi **sipariş** kapsamındaydı;
  `vestra_offer_invoice_payload()` para birimi parametresi **hiç almıyordu**.
  Yani kabul edilmiş bir teklif **yalnızca EUR** kesilebiliyor, ve kurasyonlu
  ilanda faturayı platform kestiği için o belge **ödeme kutusuz** çıkıyordu.
- Kurallar siparişteki gibi, **aynı** parçalarla: aynı izin listesi
  (`vestra_invoice_currencies`), aynı tek çevirici
  (`vestra_invoice_convert_payload`), kur **TEKLİFİN tarihinin damgası**.
  Damga `order_statuses.json[<teklif ref>]`'e düşüyor — kabul edilen teklif
  zaten kendi ref'iyle orders'a iniyor, **ikinci bir damga yeri** aynı satış
  için er ya da geç iki farklı kur demekti. **Damga yoksa çevrim yok, fatura
  YOK**: gerekçe döner, hiçbir numara yanmaz.
- **Sipariş satırı teklifin kendi biriminde kalır.** Çevrilmiş yük olduğu gibi
  `orders.csv`'ye girseydi dolar rakamları EUR diye yazılırdı. Kurucular
  çevrilmemiş hâli `base` altında taşıyor, `vestra_offer_order_ensure()` onu
  okuyor: belgenin birimi operatörün kararı, **siparişin kaydı değil**.
- **Üç mektupta gömülü "EUR" vardı** ve üçü de düzeltildi (tek kesim, birleşik
  kesim, KURAL 5f redraft'ı). En kötüsü **redraft**: belgeyi kayıttan yeniden
  kuruyor, yani USD bir fatura **aynı numarayla** euro rakamlı bir gövdeyle
  yeniden gönderilirdi.
- **Kardeşlerinin durduğu HER YERE yazıldı** (KURAL 5m'in dersi): satır
  seçicisi, birleştirme çubuğu, iki taslak yolu, iki kesim yolu ve iş akışında
  `cur=` (`reply_letter=invoice_combine_draft`). KDV oranı bu dersi tam olarak
  birleşik çubukta eksik kalarak vermişti.
- `vestra_offer_invoice_payload()` artık `invoice.php`'yi **kendi** require
  ediyor; kardeş bir fonksiyonun require'ına yaslanmak KURAL 15'in fatal'inin
  küçük hâli.
- **Kendi hatam, kayda geçsin:** iş akışına `cur=` eklerken yaptığım toplu
  değişiklik `replace(..., 1)` ile **ilk eşleşmeye** düştü, yani özet satırını
  `invoice_combine_draft` yerine **`invoice_draft`** bloğuna yazdım. Ayrıştırma
  ve kurucu çağrısı doğru yerdeydi; yalnızca **operatörün bakacağı satır**
  yanlış yerdeydi. Canlı ilk koşu bu yüzden `= ... EUR` dedi — belge USD
  çizilmiş olsa bile. Bu deponun altı kez kaydettiği "kontrol yanlış yere
  bakıyor"un aynısı, üstelik en pahalı hâli: doğru çalışan bir özelliğe
  "çalışmıyor" dedirtir. Özet artık **birimi ve `fx_note`'u** basıyor; çevrimin
  gerçekten olup olmadığı toplamdan çıkarılmıyor, yazıyor. Bunun için
  `vestra_offers_combined_invoice_issue()` kullandığı birimi de **döndürüyor**:
  *rakam veren fonksiyon, biriminin de vermeli* — yoksa her çağıran EUR tahmin
  eder.
- Test: `invoice_currency_test.php §7` (90 iddia, kablolama) ve
  `invoice_seller_pick_test.php §12` (toplam 130; **gerçek** çevirici gövdesiyle
  aritmetik). Düşebildiği doğrulandı: çevrim kaldırılınca **11 kırmızı**.
  `invoice_vat_test`'in kablolama iddiası çağrının **tam metnini** sabitliyordu
  ve kurucuya kardeş bir alan eklenince, oran hâlâ doğru geçerken kırmızı döndü
  — ölçtüğü şeyi değil, yazımını koruyan bir iddia; daraltıldı.
- **Canlı sonuç, aynı gün:** operatör panelden USD'yi seçip **INV-2026-1012**'yi
  kesti — mal €1.050 + kargo €30 → **US$1.255,17**, kur `1 EUR = 1,1622 USD
  (ECB 4 Eyl 2026)` belgede yazılı. Kesilmiş belge yeniden çizdirilip denetlendi
  (`reply_letter=invoice_draft`, `ref=OCD7D2`): **ödeme kutusu ÇIKAR (6 satır)**.
  Aynı teklifin EUR taslağı **ÇIKMAZ** diyordu; fark tek başına para biriminde.
- **Kalıcı seçenek, operatör kararı:** platform künyesine bir **EUR/IBAN** hesabı
  girilirse (`Admin ▸ Orders ▸ Platform billing & bank details`) kurasyonlu
  maldan EUR fatura da ödeme kutulu çıkar. Kod ikisini de destekliyor; hangisinin
  doğru olduğunu VESTRA'nın hangi hesaba tahsilat yaptığı belirler.
- **Taslak özeti artık soruyu doğrudan cevaplıyor:** hem `invoice_combine_draft`
  hem `invoice_draft` `odeme kutusu : CIKAR (n satir) / *** CIKMAZ ***` basıyor
  ve bunu **çizicinin okuduğu aynı fonksiyondan** (`vestra_payment_rails`)
  alıyor. Satır **sayısı** yazılıyor, satırların kendisi değil — IBAN ve hesap
  numarası taşıyorlar, kütük herkese açık. *"Banka bilgileri neden yok"
  sorusunun cevabı, numarayı yakmadan önce okunan satırda durmalı.*

**KURAL 5o — Mektubun para bloğunu TEK gövde basar; birimi ÇAĞIRAN seçemez**
(operatör, 9 Eyl 2026: *"email usd ye cevrilmis fakat eur yaziyor büyük hata"*).
- Aynı blok bu depoda **DÖRT** kez ayrı ayrı yazılıydı — panelin `📧 Test`
  taslağı, iş akışının `invoice_draft` gövdesi, birleşik kesim mektubu ve
  redraft mektubu — ve **her kopya para biriminin adını kendisi seçiyordu**,
  dördü de `EUR` sabitiyle. Teklif faturası USD öğrenince üçü düzeltildi,
  **dördüncüsü gözden kaçtı** ve operatöre giden taslak dolar tutarların üstüne
  `10 x EUR 122.03 = EUR 1,220.30` yazdı. *Rakam doğruydu, etiket yalandı —
  yanlış rakam sorgulanır, yanlış etikete inanılır.*
- Tek gövde: `vestra_invoice_letter_amounts($meta, $items)` (`inc/invoice.php`).
  Kalemler, toplamlar, KDV ayrımı ve kur notu — hepsi **PDF'i çizen yükten**.
  Birim artık çağıranın verebileceği bir karar değil. Yerleşim de ortak: aynı
  belge hakkındaki dört mektup sütun genişliğinde bile ayrışmamalı.
- **Aynı hatanın beşinci kopyası doğmasın diye tarama testte:** `Goods total :
  EUR`, `TOTAL DUE   : EUR`, `x EUR %s = EUR %s` gibi kalıplar üç dosyada birden
  aranıyor. **Teklif/pazarlık metinlerindeki `EUR` kapsam DIŞI** — teklif kaydı
  gerçekten EUR ve orada birim sabit olmalı (mango/zara dersi: tarama dar tutulur).
- **Ders (bu iş sırasında iki kez tekrarlandı):** *bir olguyu düzeltirken "bu
  aynı şey başka nerede yazılı?" diye sor.* `desc`/`sizes` ve KURAL 5f'in üç
  katmanıyla aynı sınıf; burada dördüncü kopya, ilk üçü düzeltilirken hiç
  aranmadığı için kaldı.

**KURAL 5p — Belge İKİ para birimini de taşır: kur, kaynağı, iki tarih ve EUR aslı**
(operatör, 9 Eyl 2026: *"faturada eur ve usd kuru zamani yazilmali"*).
- Eski `fx_note` kuru ve **kur tarihini** yazıyordu ama (a) **belgenin kendi
  tarihini** hiç yazmıyordu, (b) **EUR aslını** hiç göstermiyordu. OCD7D2'de kur
  **4 Eylül**, belge **9 Eylül** (ECB yalnız iş günlerinde yayımlıyor ve
  tablomuzun en yenisi o gün olmayabiliyor) — okuyan, 4 Eylül kurunun 9 Eylül
  tarihli bir belgede ne işi olduğunu **soramıyordu bile**.
- Yeni not: `Amounts converted from EUR at 1 EUR = 1.1622 USD. Rate: ECB,
  4 September 2026 — the last rate published on or before the order date,
  9 September 2026. Original total: EUR 1,080.00.`
- **"in force on the order date" iddiası kaldırıldı**: doğrulanamaz (aradaki bir
  ECB yayınını kaçırmış olabiliriz). Doğrulanabilir olan şey *"o tarihte ya da
  öncesinde yayımlanmış SON kur"* — yazılan da bu. KURAL 3'ün kur hâli.
- **EUR aslı çevrimden ÖNCE saklanıyor** (`fx_src_total`; not içinde de yazılı).
  Dolar tutarından geri bölmek **yuvarlama yüzünden başka bir sayı verir**
  (1.255,17 / 1,1622 = 1.079,99…) — test bunu ayrıca doğruluyor.
- Not hem **belgede** hem **mektupta**: parayı gönderen kişi çoğu zaman önce
  mektuba bakıyor ve *"neden 1.080 değil 1.255"* sorusu ikisinde de cevaplanmalı.
- **Ölçüm tuzağı (yaşandı):** not artık üç satıra sarılıyor ve `9 September 2026`
  tam olarak `9 September` / `2026` diye ikiye ayrılıyor; ayrıca uzun tire (—)
  belgede **CP1252** olarak duruyor (KURAL 5h). Bitişik UTF-8 arayan ilk iddiam
  belgede **duran** bir metni "yok" dedi. İddia artık **sarılmış satırlara** ve
  **CP1252 karşılığına** bakıyor.
- Test: `invoice_currency_test.php §7–§8` (124 iddia). Düşebildiği doğrulandı:
  operatörün aldığı hatanın birebir aynısı geri konunca **9 kırmızı**.

**KURAL 5q — "Faturayı PLATFORM kesiyor" HUKUKİ metinde de yazmak zorunda; ve
EUR'da platformun ödeme kutusu ÇIKMIYOR** (operatör, 16 Eyl 2026, BRITISHSTYLE /
Michael Baumgartner: *"Bu Adama önce Faturanin Platform tarafindan
gönderilecegini belirt. Ve bazi siparislerde Faturanin Platform tarafindan
alinabilmesi icin Hukuki kismida gerekli yerlere yaz."*).

- **"Gerekli yerler" TAHMİN EDİLMEDİ, arandı.** Platformun kendi adına fatura
  kesmesiyle **çelişen beş cümle** vardı ve beşi de mutlak yazılmıştı:
  `imprint`/Role, Şartlar **1** (*"not a party to any sale … exclusively between
  buyer and seller"*), Şartlar **3** (*"An order forms a binding contract between
  buyer and seller"*), Satıcı Politikası **2** (*"VESTRA is an intermediary only"*)
  ve Ödemeler/*"How payment works"* (*"VESTRA never holds the money"*). Beşine de
  istisna cümlesi girdi; kuralın kendisi yeni **Şartlar 3c**.
- **BEŞ KEZ YAZILDI, BİR KEZ DEĞİL.** `vestra_legal()` **BELGE BAZINDA**
  birleştiriyor: çeviride `terms` varsa İngilizce `terms` **hiç okunmuyor**. Yani
  yalnız İngilizceye eklenen bir madde de/fr/it/es'te **görünmez** — ve bu
  maddenin ilk müşterisi **Avusturyalı**. pt/ru/ar/ja'nın hukuk dosyası yok,
  İngilizceye düşüyorlar, yani onlar kendiliğinden kapsandı (KURAL 10).
  Ölçüm dil başına **ayrı PHP sürecinde** yapıldı (`vlang()` ilk çağrıda
  sabitleniyor — KURAL 11'in tuzağı): 5 dilde de başlık 1, madde 6, çözülmemiş
  değişken 0, DOM hatası 0.
- **Madde BİLEREK kendi kendine yeter.** `3a` (iade) ve `3b` (muayene/kusur
  ihbarı) **YALNIZCA İngilizcede var** — dört çeviri de o maddelerden önceye ait.
  Almanca bir 3c *"3a aynen geçerli"* deseydi **var olmayan bir maddeye** atıf
  yapardı. Bunun yerine kanonik kaynağa, `/faq?cat=returns`'e bağlandı; o metin
  zaten 8 dilde tam (KURAL 11).
- **Açık kalan, operatör kararı bekliyor:** Şartların **3a ve 3b maddeleri dört
  çeviride yok**, yani Alman/Fransız/İtalyan/İspanyol alıcı sözleşmede iade
  kuralını ve HGB §377 muayene ödevini **hiç görmüyor**. Bu bu işten bağımsız ve
  eski; kendiliğinden çevrilmedi çünkü bu dosyanın kendi kaydı *"hukuk metni
  sohbet çevirisiyle yazılmaz"* diyor.

**Toptan LOT siparişi yazmanın yolu yoktu — `order_draft` her satırı DROPSHIP
fiyatlıyordu.** Tek parça satışı için doğru, tam kartonluk bir lot için yanlış;
ve elle anlaşılmış bir toptan satışı kayda geçirmenin **tek** yolu oydu.
`payload: pricing=wholesale|SKU:RENK:ADET`:
- Birim **sepetin kendi fonksiyonundan** (`vestra_unit_price`), elle hesaplanmıyor.
- **MOQ ve paket adımı ilandan** doğrulanıyor (KURAL 4b: sepet adedi adımın katına
  yuvarlıyor, yani katalog dışı bir adet kasada başka bir adet demek).
- **Renk ilanda gerçekten var mı**, TAM eşleşmeyle (mango/zara dersi).
- **Beden dökümü UYDURULMUYOR:** ilanın kendi serisi × karton sayısı. Seri
  okunamazsa satır yazılmıyor.
- İlanın **renk asgarisinden feragat** açık bir opt-in (`|waive_min_colours=1`),
  sessizce atlanan bir kontrol değil — `force=1` deseninin aynısı — ve **gerekçe
  siparişin kendi notuna** yazılıyor: aylar sonra *"bu lot neden tek renk"*
  sorusunun cevabı, ilanın bugün hâlâ "en az 4 renk" diyen metni değil, o satır.

**Yan düzeltme — elle kurulan sipariş, KENDİ ayrıştırıcısının okuyamadığı bir not
yazıyordu.** `vestra_order_create_manual()` her SKU'yu kendi **noktasıyla**
kapatıyor ve değerleri `·` ile ayırıyordu; `vestra_order_notes_map()` ise **ilk
noktaya** kadar okuyup `,` ile bölüyor. Sonuç: **iki SKU'lu her elle siparişte
ikinci SKU'nun dökümü sessizce kayboluyordu**, üstelik kalıntısı serbest metinde
kalıp alıcının sipariş sayfasına ve panele **olduğu gibi** basılıyordu. Ölçüldü
(gerçek bir not dizgesiyle harita 1 SKU, kalıntı metinde), sonra kasanın biçimine
çevrildi. **Renk alanı hiç yoktu** ve eklendi — kasa `Colours — …` parçasını
baştan beri yazıyor, `vestra_order_lines()` onu sipariş tablosuna, sipariş
PDF'ine ve faturanın toplama listesine basıyor; elle kurulan sipariş bir lotun
siyah mı bordo mu olduğunu **hiçbir yere** yazamıyordu.
Test: `order_manual_test.php` 29 → **43 iddia**; eski biçim geri konunca
**12 kırmızı** (sabotajın gerçekten uygulandığı `grep -c` ile doğrulandı).

**CANLI ÖLÇÜM — sipariş ilanın kendi kurallarıyla ÇELİŞİYOR ve bu ölçülerek
görüldü.** Müşteri *"1 Lot Polos in Schwarz + 1 Lot Sweatshirts in Bordeaux"*
yazdı; sonda (`inspect-products`, `brand=Fred Perry`) şunu verdi:

| | M3600 polo | M7535 sweatshirt |
|---|---|---|
| MOQ / paket adımı | 50 / 10 | 50 / 10 |
| kademeler | 39,00 / 35,50 / 32,00 | 39,90 (tek) |
| renkler | 6 (Black dahil) | 5 (**Bordeaux dahil**) |
| **`min_colors`** | **2** | **4** |

Yani **Bordeaux gerçekten var** (uydurma değil), ama müşteri **her ikisinden tek
renk** istiyor ve ilanlar ≥2 / ≥4 diyor — sepet bu siparişi **reddederdi**.
Feragat operatör kararı olarak uygulandı ve gerekçesi kayda yazıldı; **ilanlara
dokunulmadı** (min_colors'ı düşürmek her alıcının gördüğü kuralı değiştirirdi).

- **Sondanın kendisi önce düzeltildi:** ürün dökümü `colors`'ı **hiç
  basmıyordu** ve `!empty()` yüzünden `min_colors=0` ile "alan yok" aynı
  görünüyordu. Görmediği bir alan hakkında yeşil veren sonda bu depoda iki kez
  kayıtlı (`check_images` yalnız approved geziyordu; `price_list` `sold_out`
  basmıyordu). Renk listesi, `min_colors` ve **her iki seçici** artık koşulsuz
  basılıyor.
- **"1 Lot" = 50 adet ÇIKARIMDI ve ÇIKARIM YANLIŞTI — doğrusu 10.** Karton
  10'luk ama ilan edilen asgari 50; 10 adet MOQ'nun altında kaldığı için tek
  tutarlı okumanın 5 karton = 50 olduğunu düşündüm ve varsayımı hem metne hem
  buraya **açıkça çıkarım diye** yazdım. Operatör aynı gün düzeltti: *"toplam
  10 ad. F.Perry Polo + 10 Ad. F.Perry Sweatshirt +20 eur shipping"* — yani
  **je 1 karton**, ve "numune siparişi" cümlesi de bunu anlatıyormuş.
  *Varsayımı işaretlemek onu doğru yapmıyor; işaretlemek yalnızca
  düzeltilebilir yapıyor — ve burada tam da o işe yaradı.* Beş kat fark
  (€3.965 → €809) bir çıkarımın bedeli olarak küçük değil: adet gibi tek
  cümlelik bir belirsizlikte, mektubu hazırlamadan ÖNCE sormak daha ucuzdu.

**BLOKAJ — platformun EUR ödeme kutusu ÇIKMIYOR** (`diag-messages` →
`billing_for=vestra`, 17 Eyl 2026 canlı): `bank_holder`, `bank_name`
(*Choice Financial Group*), `bank_bic`, `bank_account` **VAR (12 hane)**,
`bank_routing` **VAR (9 hane)** — ama `bank_iban` **BOŞ**. Yani platformun hesabı
bir **ABD hesabı** ve euro rayı yok: **EUR faturada ödeme kutusu ÇIKMAZ**, USD'de
**ÇIKAR (6 satır)**. Bu sipariş **EUR**. 9 Eylül'de operatörün sorduğu
*"neden fatura yaparken banka bilgileri cikmiyor?"* sorusunun aynısı, bu kez
**numara yakılmadan önce** yakalandı. Üç seçenek, karar operatörün:
**(a)** platform künyesine bir **EUR/IBAN** hesabı girmek
(`Admin ▸ Orders ▸ Platform billing & bank details`) — en doğrusu, kod zaten
destekliyor; **(b)** faturayı **USD** kesmek (kutu çıkar, ama kur KURAL 5i'ye
göre **sipariş tarihinin damgası**); **(c)** faturayı GARAGE LE PARIS'ten kesmek
— operatörün talimatına aykırı.

**Sipariş YAZILDI, fatura KESİLMEDİ: `VES-1F0C9350`** — 10 × M3600 Black
(€39,00) + 10 × M7535 Bordeaux (€39,90) = €789 mal + €20 kargo = **€809,00**,
kayıttan geri okundu; beden dökümü ilanın kendi serisinden (**S×1 · M×3 · L×3 ·
XL×2 · XXL×1**, karton başına), müşteriye hiçbir şey gitmedi. Fatura kesicisi
kayıtta **`vestra`** (operatör seçimi, KURAL 5b) ve yük **tek dilim = TEK
BELGE**. Numara bilerek yakılmadı: EUR'da ödeme kutusu **boş** çıkıyor (aynı
koşunun kendi satırı: *"odeme kutusu (EUR): BOS -- bu kunyede EUR yolu yok"*)
ve mektup *"faturadaki hesaba ödeyin"* diyemez — belgede o hesap **yok**.

**Yanlış adetli ilk sipariş (`VES-DDC53EF1`, 50+50 = €3.965) SİLİNDİ** ve
silme geri okundu (*"kayitta yok (dogrulandi)"*). Silmek mümkündü çünkü numara
yakılmamıştı — *sipariş geri alınabilir, yanmış bir numara pratikte değil.*
**Ama silecek yolum YOKTU:** siparişi bu iş akışından **yazabiliyordum**,
geri alamıyordum; her yanlış adet operatörün panele girmesini gerektiriyordu.
`seller-products.yml` → **`admin_mode=order_delete`** (kuru koşu varsayılan,
`move_apply=true` uygular) panelin `order_delete` eylemiyle **aynı yazıcıyı**
çağırıyor.
- **Muhafaza AYRICA yazıldı ve sebebi yapısal:** `vestra_order_delete()`
  kesilmiş faturaya **bakmıyor** — o kontrol `admin.php`'de duruyor. Fonksiyonu
  doğrudan çağıran ikinci bir kapı, KURAL 5g'nin koruduğu şeyi **kilitsiz**
  bırakırdı. *Kapı vardı, kilit başka dosyadaydı.*
- **`force` bilerek YOK:** numarası yanmış belgeyi arşivleyerek silme yolu
  **tek yerde** (Orders sekmesinin ret bandı) ve ikinci bir ulaşım noktası tam
  o kuralı gevşetirdi. Faturalı ref **koşulsuz** reddediliyor.
- **İki yönü de ölçüldü:** kontrol grubu `OCD7D2` (INV-2026-1012, ödenmiş)
  **REDDEDİLDİ**; `VES-DDC53EF1` (faturasız) kuru koşuda geçti, uygulandı,
  geri okundu. Tek yön ölçülseydi "her şeyi silen" bir kusur da yeşil görünürdü.
- **Sütun adları `orders.csv`'nin KENDİ başlığından:** ilk yazımda `goods` ve
  `currency` okumuştum, ikisi de o dosyada **yok** (mal toplamı `subtotal`).
  Ayrıca çağırdığım `vestra_order_status()` diye bir fonksiyon **hiç yoktu** —
  çağrılan her `vestra_*` fonksiyonunun kaynakta var olduğu tarandığı için
  yakalandı. *Olmayan bir anahtarı `?? ''` ile sormak sessizce boş basar;
  bu depoda `$a['vat']` (doğrusu `vat_id`) tam böyle her hesap için "(yok)"
  yazıp neredeyse bir özür mektubu yazdırmıştı.*

**İlan edilen ASGARİ ADETTEN feragat: `|waive_moq=1`** (KURAL 4b'nin kardeşi).
10 adet, `size_step=10`'un tam katı (bir karton) ama ilanların `moq=50`'sinin
**altında**, yani hem sepet hem toptan kipi bu siparişi reddederdi. Renk
asgarisindeki kardeşiyle aynı desen: açık opt-in, sessizce atlanan bir kontrol
değil, ve gerekçe siparişin **kendi notunda** duruyor (*"Quantity below the
listed minimum order, agreed as an exception."*).
- **İki bayrak AYRI, bilerek:** renk asgarisinden feragat etmek ile ilan edilen
  asgari adedin altına inmek iki ayrı taviz; birini isteyen ötekini istemeyebilir.
- **PAKET ADIMI feragata GİRMİYOR:** o fiziksel bir kısıt (karton 10'luk) ve
  sepet adedi adımın katına **yuvarlıyor** — 15 adet yazan bir fatura kasada
  20 adet demek olurdu. *Asgariyi aşağı çekmek ilan edilen bir kuralı gevşetir;
  adımı kırmak belgeyi yalanlar.*
- Ölçüm: kaynaktan çıkarılıp `eval` edilen parse bloğu, 11 iddia, iki yön;
  ayrıştırma silinince **4 kırmızı**, feragat hep açık bırakılınca **5**.
  **İlk sabotajım hiç uygulanmamıştı** (perl kaçışı eşleşmedi) ve "iddia
  düşmüyor" sandırdı — üstelik karşılaştırdığım taban sayı **başka bir
  dosyanınkiydi**. Satır sayımıyla doğrulanınca çıktı.

**"ABD hesabı verdiğimde müşteriler ödemiyor, neden?"** (operatör sorusu,
17 Eyl 2026 — yukarıdaki blokajın ta kendisi, başka kelimelerle).
- **Avrupalı alıcı için ABD hesabı SEPA DEĞİL, SWIFT havalesidir.** SEPA yalnız
  SEPA alanındaki EUR transferleri kapsıyor; Delaware'deki bir hesap o alanda
  değil. Sonuç alıcının tarafında üç somut engel: **ücret** (bankası giden
  uluslararası havaleden tipik olarak €15–50 alıyor, üstüne muhabir banka
  yol üstünde kesiyor), **süre** (SEPA'da ertesi gün, SWIFT'te 2–5 iş günü —
  yani "ödedim" ile "gördük" arası günlerce açık kalıyor), ve **form**:
  AB kurumsal bankacılığı **IBAN merkezli**; ABA routing + hesap numarası
  isteyen bir ödeme çoğu portalda ayrı bir "uluslararası havale" akışına,
  bazen şubeye gidiyor. €3.965'lik bir siparişte bu, alıcı için hem masraf
  hem angarya.
- **Ve güven tarafı, ki asıl sebebin bu olması kuvvetle muhtemel:** Avrupalı
  bir alıcı, Avrupa merkezli görünen bir pazar yerinden mal alıp faturayı
  **ABD'deki bir hesaba** ödemeye çağrılıyor. Bu kalıp, muhasebe
  departmanlarının **fatura dolandırıcılığı (BEC)** eğitiminde birebir
  öğretilen kalıp — "ödeme bilgileri değişti, şu yabancı hesaba yatırın".
  Şüphelenen taraf ödemeyi yapmıyor, çoğu zaman **sormuyor da**.
- **Kod bu kararı ZATEN veriyor:** `vestra_payment_rails` EUR faturaya ABA
  routing **basmıyor** — hiçbir şey basmamayı seçiyor. Gerekçesi yazılı:
  "Avrupalı alıcı o yola ödeyemez, kabul eden banka günler sonra iade eder."
  Yani bugünkü hâl "ABD hesabı görüyorlar ve ödemiyorlar" değil; **hiçbir
  hesap görmüyorlar** ve ödeyecekleri yer yok.
- **Çözüm bir fiyat/hesap kararı, kod kararı değil: aynı tüzel kişiye bir
  EUR IBAN.** Bir ABD LLC'si için IBAN veren hesap sağlayıcıları var (Wise
  Business, Payoneer, Revolut Business, Airwallex — hangisinin Delaware LLC
  kabul ettiğini operatör kendi doğrulamalı). IBAN girildiği anda
  (`Admin ▸ Orders ▸ Platform billing & bank details`) kod zaten hazır: EUR
  faturada ödeme kutusu **çıkıyor**, alıcı sıradan bir SEPA havalesi yapıyor.
  *Dürüst uyarı:* bu sağlayıcıların IBAN'ı çoğu zaman **başka bir AB ülkesine**
  ait oluyor (BE/LT gibi) ve bazı ödeyenler buna da takılıyor — ama IBAN
  ayrımcılığı AB'de **yasak** (SEPA Tüzüğü 260/2012 md. 9) ve bu, ABA routing
  vermekten kıyaslanamayacak kadar iyi.
- **Ara çözüm zaten kurulu:** kart/escrow yolu (Stripe) havale değil; tavanı
  €3.000 (KURAL 6), yani bu siparişi tek başına karşılamıyor ama küçük
  siparişlerde "ödeyemiyorum" sorununu tümden ortadan kaldırıyor.

**KURAL 5k — Siparişe NAVLUN yazılabilir; tutar ile TOPLAM birlikte hareket eder**
(operatör, 7 Eyl 2026: *"kargo bölümü yok kargo eklemek gerekiyor 100 usd
ekleyelim"* — VES-6B53D265).
- **Teklif faturasında kutu vardı, siparişte YOKTU.** `offer_responses.json`'da
  `invoice_shipping` ve panelde "Kargo €" 1 Eyl 2026'da tam bu sebeple eklenmişti;
  sipariş ekranı navlunu yalnızca **gösteriyordu** (üstelik yalnız sıfırdan
  büyükse), yazacak hiçbir yol yoktu.
- Tek yazıcı `vestra_order_set_shipping()` (`inc/orders.php`); panelin
  `🚚 Save shipping` kutusu ve `seller-products.yml` → `admin_mode=shipping`
  ikisi de onu çağırıyor. **`shipping` ve `total` BİRLİKTE yazılır** — sipariş
  satırının toplamı navlunu içeriyor ve `diag-live` bunu denetliyor
  ("toplam farki (kayitli - mal - navlun)"); yalnız birini yazmak denetimi kırar
  ve alıcının sipariş sayfası ile faturasını iki ayrı rakama böler (KURAL 5f).
  Mal toplamı faturanın okuduğu **aynı** fonksiyondan (`vestra_order_lines`).
- **ÜSTÜNE eklemez, YERİNE yazar:** toplam her seferinde mal + navlun olarak
  yeniden kurulur; aksi hâlde iki düzeltme siparişi çift navlunla bırakırdı.
- **Faturası kesilmiş siparişte YAZMAZ** — belge alıcının elinde, numara yanmış;
  yol KURAL 5f (aynı numarayla yeniden çizim). Ham dosyadan okur
  (`vestra_read_csv()` satırları ters çeviriyor — `vestra_order_delete`'in aynı
  tuzağı), önce yedekler, geçici dosyaya yazıp **atomik** takas eder ve
  **geri okuyup doğrular**.
- **Tutar siparişin KENDİ biriminde saklanır**, çevrim tek yerde (KURAL 5i).
  Belge başka birimdeyse panel karşılığını yazar; iş akışı doğrudan `100 USD`
  kabul edip siparişin **damgalı** kuruyla böler (damga yoksa durur).
- **Canlı ölçüm (7 Eyl 2026, run 32 + 263):** girilen `100 USD` → kayda
  **€86,04** → belgede **Shipping US$100.00**, Total **US$5.539,60**; sipariş
  toplamı €4.766,04 ve denetim "tutuyor". Test: `tests/order_shipping_test.php`.

**KURAL 5l — Teslimat adresi de PANELDEN girilir ("kargo yeri")** (operatör,
7 Eyl 2026: *"hem kargo yeri aç hem de faturayı güncelle"*).
- Adres siparişin notlarında `Deliver to: …` parçasında duruyor; ekran onu
  **gösteriyordu** ama girecek alan yoktu — yalnızca alıcı, sipariş verirken
  yazabiliyordu. Sonradan e-postayla gelen bir adresi operatörün koyacağı yer
  yoktu ve VES-6B53D265'in taslağı üç koşu boyunca *"no street address on file —
  gümrük ve kurye ister"* diye uyarıp durdu. Navlundaki boşluğun aynısı.
- Tek yazıcı `vestra_order_set_delivery()`; panel: `Admin ▸ Orders ▸ <sipariş> ▸
  📍 Save address`. **Notların gerisine dokunmaz** (`Payment:`, `Colours —`
  parçaları siparişin kendi kaydı), tek kopya bırakır, boş kaydetmek siler,
  faturası kesilmişte **yazmaz** (KURAL 5f).
- **Doğrulama satırın değişmesine değil, FATURANIN GÖRDÜĞÜNE bakıyor**
  (`vestra_invoice_buyer($back)`) — ve bu ilk denemede **düştü**: okuma kalıbı
  `(?:\.\s|$)` adres notların **sonundaysa** kapanış noktasını adresin içinde
  bırakıyordu ("… Hong Kong."), yani belgeye, ekrana ve kurye etiketine öyle
  basılıyordu. Kalıp **üç yere ayrı ayrı** yazılmıştı (fatura, panel, sipariş
  sayfası) ve üçü de aynı kusuru taşıyordu; artık tek fonksiyon:
  `vestra_order_delivery_address()`. *Yazma tarafı eklenmeden bu kusur
  görünmüyordu — okuma tek başına kendini doğrulayamıyor.*
- Test: `order_shipping_test.php §3b` (yazma, faturanın gördüğü, çoğaltmama,
  silme, sınır, kesilmiş faturada ret).

**KURAL 5t — NAVLUN TARİFESİ: bölge başına TEK tablo; tanınmayan ülkede tarife
YOK** (operatör, 19 Eyl 2026, dört cümlede: *"her faturaya 10 ad. için 20 eur
sonraki her 10 ad. için +5 eur shipping cost ekle"* → *"sadece 40+ üstü aynı
model siparişlerde her 100 ad. başına 30 eur yap"* → *"bu avrupa siparişleri
için geçerli"* → *"abd için her siparişe 30 eur + 20 ad. sonrasına her 10 ad.
+5 eur ve tek model alınırsa her 100 ad. 50 eur, 100+50 ad.'e kadar 50+20"*).

- **Tek karar noktası `vestra_shipping_schedule($lines, $country)`** (saf) ve
  rakamlar **`vestra_shipping_tariffs()`** tablosunda: kasa, sepet önizlemesi,
  teklif faturası, panel ipucu/çipi ve iş akışının `auto` kipi hepsi oradan
  okuyor. Escrow tavanının beş gün metinde 3.000, kodda 3.500 kalması (KURAL 6)
  tam bu yüzden bir daha yazılmadı: **hiçbir sayfaya, mektuba ya da JS'e elle
  bir rakam girilmedi** — sepet tabloyu `json_encode(vestra_shipping_tariffs())`
  ile sunucudan basıyor.
- **İKİ RAY, bölge başına aynı şekil:** *havuz* (bulk eşiğinin ALTINDAKİ
  satırlar BİRLİKTE sayılır — satır başına ayrı taban almak iki kalemlik küçük
  bir siparişi iki kat pahalı yapardı) ve *toptan* (bir SKU'da adet ≥ eşik ise o
  satır tek başına). AB: 10→20, 11→25, 40→30, 101→60. ABD: 20→30, 40→50,
  150→70, 151→100.
- **"Başlayan blok" bir YORUM ve işaretli:** operatör *"sonraki her 10 ad."*
  dedi, kesri söylemedi. Kesri düşürmek 19 adedi 10 adetle aynı fiyata taşırdı;
  yukarı yuvarlamak en fazla bir basamak ekliyor ve **ekranda görünüyor**.
- **ABD'nin toptan EŞİĞİ operatörden gelmedi**, AB için verdiği 40 alındı ve
  tabloda **ayrı bir satır** olarak duruyor. Ölçülen bedel yazılı: ABD'de 40–59
  adetlik tek modelde toptan ray €50, havuz rayı €40–45 — toptan ray ancak ~60
  sonrası ucuzluyor. Eşiği değiştirmek tek satır ve operatörün.
- **PROBE BİR HATA YAKALADI ve düzeltme teste pinlendi:** ilk yazımda ABD'nin
  yarım bloğu `$full > 0` şartı taşımıyordu ve **40 adet €20'ye düşüyordu** —
  oysa operatörün cümlesi *"her 100 ad.'e kadar 50 eur"*, yarım blok ancak TAM
  bir yüzün üstünde geçerli (*"100 + 50 ad.'e kadar 50+20"*). Kaynağı okuyarak
  değil, tabloyu **koşturarak** çıktı.
- **TANINMAYAN ÜLKEDE TARİFE UYGULANMIYOR** (`vestra_shipping_region()` → null):
  Japonya'daki bir alıcıya AB tarifesini basmak, gerçek navlunun altında bir
  rakam ilan etmek olurdu. Eşleşme **TAM**: `AT` Avrupa, `AU` değil;
  `"Virgin Islands (US)"` ABD değil (mango/zara dersinin coğrafya hâli).
  Boş ülke → tarife yok, navlunu operatör elle yazar.
- **Sepetin JS aynası SUNUCUDAN besleniyor** (`vestra_shipping_region_map()`,
  324 girdi, Avrupa/ABD tablolarından **türetiliyor**). İkinci bir liste
  yazmak, bir gün eklenen ülkede *"sepette €0, kasada €30"* demekti. Ayna 12
  vakada PHP ile **birebir** aynı sonucu verdi (yerelde çizdirilip ölçüldü).
- **Navlun alıcının ödediğine GİRER, komisyona ve satıcı ödemesine GİRMEZ;
  escrow tavanı da mal bedeli üzerinden.** Stripe'ta **kendi satırı** var:
  yazılmazsa kalan "Buyer protection fee" etiketiyle şişiyor ve alıcı gerçekte
  navlun olan bir tutarı koruma ücreti diye okuyor (*rakam doğru, etiket yalan*).
- **Elle sipariş: `null` = tarife, açık `0` = navlun yok.**
  `vestra_order_create_manual()`'ın varsayılanı `0.0`'dan `null`'a çevrildi —
  "navlun yok" ile "navlunu sen hesapla" iki ayrı talimat. İş akışında
  `issue_shipping=auto` (hem `shipping` hem `order_draft/order_write`), taslakta
  gösterilen rakamın **aynısı** yazılıyor.
- **Teklif faturasında ölçüt `array_key_exists`**, `isset` değil: operatörün
  bilerek yazdığı `0` ("bu belgede navlun yok") ile hiç yazılmamış alan ayrı iki
  şey, ve `isset` ikisini de aynı görürdü — kaldırılan navlun her önizlemede
  geri gelirdi.
- Test: `tests/shipping_tariff_test.php` (**91 iddia**, iki yön). Düşebildiği
  doğrulandı, her sabotajın **gerçekten uygulandığı** ayrıca yazdırılarak: ABD
  yarım blok şartı geri alınınca **3 kırmızı**, havuz satır başına hesaplanınca
  **5**, bölge eşleşmesi alt dizeye gevşeyince **2**, kasa navlunu toplamdan
  çıkarınca **2**, sepet tabloyu elle yazınca **3**, teklif faturası yine
  `?? 0` okuyunca **2**.

**KURAL 34 — NAVLUN OTOMASYONU ŞU AN PASİF; operatör elle hesaplayıp
siparişten SONRA yazıyor** (operatör, 19 Eyl 2026: *"shipping cost yanlis
olmus tekrar söylüyorum simdilik otomatik yapma pasif olsun ben hesaplarim
siparisten sonra"*).

- **"Tekrar söylüyorum" ikinci şikâyet:** KURAL 5t'nin kurduğu bölge tarifesi
  canlıda yanlış rakam üretti. Kural yalnızca hatırlanmaya bırakılamazdı
  (bu dosyanın kendi kaydı: *"kontrol gönderim yolunda olmalı"*) — kapatma
  koda gömüldü, KURAL 16/17'nin dropship ödemesinde kurduğu **aynı desen**:
  TEK anahtar, VARSAYILAN KAPALI, hiçbir tablo/fonksiyon silinmiyor, panelden
  deploy'suz geri açılabiliyor.
- **`vestra_shipping_schedule()` ve `vestra_order_shipping_schedule()`
  BİLEREK DOKUNULMADI** — SAF kalıyorlar ve `tests/shipping_tariff_test.php`'nin
  91 iddiası hâlâ doğrudan onları çağırıyor; anahtarı oraya gömmek o testin
  tamamını (ve tarifenin kendi matematiğini) bu anahtara bağımlı kılardı.
  Anahtar bunun yerine **iki yeni sarmalda**: `vestra_shipping_auto_schedule()`
  ve `vestra_order_shipping_auto_schedule()` (`inc/orders.php`) — kapalıyken
  **null** dönüyorlar, tıpkı tanınmayan bir ülke gibi (KURAL 3'ün aynı cevabı:
  "burada otomatik bir rakam yok").
- **OTOMATİK olan HER çağıran artık sarmalı çağırıyor, pure fonksiyonu değil:**
  kasa (`order.php`, checkout anında yazılan navlun), sepetin sunucudan aldığı
  önizleme tablosu (`cart.php` — `SHIP_TARIFF`/`SHIP_REGION` kapalıyken **boş**
  basılıyor, yoksa alıcı sepette bir rakam görüp kasada başkasını bulurdu —
  bu depoda tekrar tekrar kaydedilen "sayfada bir, kasada başka rakam" hatası),
  teklif faturası varsayılanı (`inc/offers.php` →
  `vestra_offer_invoice_shipping()`), panelin ipucu/"↻ Apply tariff" düğmesi
  (`admin.php`, hem sipariş kuyruğundaki çip hem sipariş dosyasındaki öneri —
  kapalıyken ikisi de **hiç çizilmiyor**) ve iş akışının `auto` kipi
  (`seller-products.yml`, `order_draft`/`order_write` **ve**
  `admin_mode=shipping`'in `issue_shipping=auto` dalı — ikincisi kapalıyken
  `auto`'yu **reddediyor** ve operatöre sayısal bir tutar yazmasını söylüyor,
  sessizce 0 yazıp "hesapladım" izlenimi vermiyor).
- **ELLE yazma yolu HİÇ DOKUNULMADI** — operatörün "ben hesaplarım siparişten
  sonra" dediği yol bu: `vestra_order_set_shipping()`, panelin "🚚 Save
  shipping" formu (`_action=order_shipping`) ve iş akışının
  `admin_mode=shipping`'ine **sayısal** bir tutar verilmesi (ör. `86.04` ya da
  `100 USD`) hiçbirini sormuyor, hiçbiri yeni anahtara bakmıyor. Operatör
  siparişi kendi hesapladığı rakamla, tıpkı bugüne kadar olduğu gibi
  tamamlıyor.
- **VARSAYILAN KAPALI:** ayar dosyası (`vestra/data/shipping_settings.json`)
  yoksa ya da bozuksa otomasyon durur. Tersi (dosya kaybolunca otomasyonun
  kendiliğinden geri açılması) operatörün "kapat" dediği şeyin sessizce geri
  gelmesi olurdu.
- **Panelde geri açma düğmesi:** `Admin ▸ Orders` üstünde (dropship
  anahtarıyla birebir aynı yerleşim/desen), hem sipariş listesinde hem tek
  sipariş dosyasında görünüyor — bir ekranda görünmeyen seçenek olmayan
  seçenektir (KURAL 2e). Yazma **geri okunarak** doğrulanıyor
  (`vestra_shipping_set_auto`, KURAL 5c'nin `billing_saved` dersi); tutmazsa
  panelde kırmızı `ship_auto_fail` uyarısı çıkıyor.
- **Dosya yolu `defined()` korumalı** (`VESTRA_SHIPPING_SETTINGS`) — KURAL 2'nin
  `VESTRA_ACCOUNTS` dersinin aynısı: korumasız olsaydı bu ayarı sınayan bir
  test gerçek `data/`'ya yazabilirdi.
- **Üç eski test ÇÖKTÜ ve bu falsifikasyonla değil kazayla bulundu:**
  `offers_rounds_test.php`, `offers_flow_test.php` ve
  `invoice_seller_pick_test.php` — üçü de `offers.php`'nin gövdesini `eval`
  ile çıkarıp `require`'ları siliyor ve `vestra_shipping_schedule()` için
  "gerçek gövde de null döner" gerekçesiyle bir stub taşıyordu; teklif
  faturası artık **başka bir isim** (`vestra_shipping_auto_schedule`)
  çağırdığı için üçü de `Call to undefined function` ile ölüyordu. Üçüne de
  aynı gerekçeyle **ikinci bir stub** eklendi — bu dosyaların hiçbir iddiası
  navlun tutarını okumuyor (yalnız tur sayacı, satıcı seçimi, miktar/birim
  fiyat, fatura gruplama), yani sarmalın null mi gerçek bir tarife mi
  döndürdüğü ölçülen davranışı değiştirmiyor. *Bir fonksiyonun çağrı yolunu
  değiştirmek, onu ELLE stub'layan her sandbox'ı bulup güncellemeyi
  gerektiriyor — kaynak taramasıyla değil, testleri gerçekten ÇALIŞTIRARAK
  bulundu (`sh tests/run_all.sh`).*
- **Falsifikasyon:** sarmalın gövdesindeki anahtar kontrolü kaldırılıp pure
  fonksiyona doğrudan düşürülünce (yani "pasifken de hesapla" hatası
  simüle edilince) `shipping_tariff_test.php` **2 kırmızı** verdi, dosya
  yedekten (`cp`, `git checkout` değil) geri yüklendi ve takım yeniden
  126/126 yeşile döndü.
- Test: `tests/shipping_tariff_test.php` §11–13 (dosya 91 → **126 iddia**).
  §11 anahtarı **ayrı PHP süreçlerinde** sınıyor (`vestra_dropship_payments_enabled()`
  ile aynı `static` önbellek sınırı — aynı süreçte yazıp okumak önbelleği
  ölçerdi) ve AÇIKKEN sarmalın pure fonksiyonla **birebir aynı** sonucu
  verdiğini de doğruluyor (ikinci bir hesap yolu değil, yalnız bir kapı).
  §12 her OTOMATİK çağıranın sarmalı kullandığını **ve** manuel
  "Save shipping" işleyicisinin **değişmediğini** (anahtara hiç sormadığını)
  kaynak taramasıyla tutuyor. §13 hiçbir şeyin silinmediğini (`vestra_shipping_schedule`,
  tarife tablosu, bölge tespiti, manuel yazıcı) doğruluyor. Ayrıca
  `dropship_payments_test.php`, `order_shipping_test.php`,
  `offers_sample_gate_test.php`, `offers_rounds_test.php`,
  `offers_flow_test.php` ve `invoice_seller_pick_test.php` tek tek koşuldu —
  hepsi yeşil. `sh tests/run_all.sh`'ın geri kalan iki kırmızısı
  (`dropship_plan_test` 4, `msg_read_receipt_test` 1) bu işten **bağımsız,
  önceden kırık** ve bu dosyada zaten kayıtlı — dokunulmadı.

**KURAL 5u — SİPARİŞE İNDİRİM: aynı gün İKİ OTURUM aynı olguya iki yazıcı
yazdı; tekilleştirildi** (operatör, 19 Eyl 2026: *"yeni yaptığımız siparişlere
yüzde 5 welcome indirimi uygula"*).
- `discount` ve `voucher_code` sütunları kasadan beri var; fatura
  (`Voucher <kod> −€x` satırı), sipariş PDF'i ve panel **üçü de okuyordu** —
  sonradan yazacak hiçbir yol yoktu. Kuralın kendisi **KURAL 32**'de
  (paralel oturumun kaydı); burada duran şey **çarpışmanın dersi**.
- **İki oturum aynı öğleden sonra iki ayrı `vestra_order_set_discount()`
  yazdı** — biri **TUTAR** alıyordu (benimki, panel + `admin_mode=discount`),
  öteki **YÜZDE** (paralel oturum, `admin_mode=order_discount`). Aynı dosyada
  iki aynı adlı fonksiyon **fatal**; birleştirme onu otomatik olarak yan yana
  koydu ve ancak `grep` gösterdi.
- **YÜZDE alan sürüm kaldı** ve sebebi ölçüldü, kıdem değil: tutarı
  `voucher_discount()` türetiyor (sepetin **aynı** yuvarlayıcısı), **parası
  gelmiş siparişi koşulsuz reddediyor**, `subtotal`/`payout`'u da yazıyor ve
  **ücreti koruyor**. Benimki bunların hiçbirini yapmıyordu. Benim sürümün tek
  fazlası nota `Voucher KOD (-5%) = -€x.` parçası yazmaktı — **kasıtlı olarak
  taşınmadı**: aynı olgunun ikinci bir yerde durması bu deponun defalarca
  ödediği hata, ve belge zaten sütunlardan basıyor.
- **Panel, iş akışının çağırdığı AYNI gövdeye bağlandı** (`Admin ▸ Orders ▸
  <sipariş> ▸ 🎟️ Save discount`, girdi **yüzde**). Form tutar göndermeye devam
  etseydi *"%12,50 indirim"* diye okunurdu — rakam doğru, anlamı bambaşka.
  Kutuda gösterilen yüzde kayıttaki tutardan **geri türetiliyor** (kayıt tutar
  saklıyor), yalnız gösterim için; yazma yolunda ikinci bir hesap yok.
- **Ders:** *aynı dala yazan ikinci bir oturum varsa, birleştirmeden sonra
  "aynı adı taşıyan iki şey var mı" diye ARA.* `git merge` çakışma bildirmedi
  çünkü iki fonksiyon dosyanın farklı yerlerine düştü; kusur ancak
  `grep -c "function vestra_order_set_discount"` ile göründü.
- Test: `tests/order_discount_test.php` (**15 iddia**) yalnız **panel yolunu**
  ve tekilliği tutuyor; yazıcının kendisi paralel oturumun
  `tests/welcome_discount_test.php`'inde (154 iddia). İkinci bir kopya yazmak,
  tam da bu maddenin anlattığı hatayı testte tekrarlamak olurdu.
- **Kendi ölçüm hatam, iki kez:** *"yazıcı kupon fonksiyonu çağırmıyor"* iddiası
  düz `voucher_` arayıp **SÜTUN ADINI** (`voucher_code`) yakaladı; *"handler
  tutarı kendi hesaplamıyor"* iddiası ise handler'ın kendi **YORUMUNDAKİ**
  `voucher_discount()` geçişini okudu. İkisinde de **kod haklıydı, ölçü
  yanlıştı** — `class="msgtick` önekinin `msgtickdefs`'i yakalamasıyla aynı
  sınıf; ikisi de gevşetilmedi, **daraltıldı** (yorumlar temizlenip çağrı
  aranıyor).

**KURAL 5m — KDV FİYATIN İÇİNDE; belge matrahı ve vergiyi ayrı gösterir**
(operatör, 7 Eyl 2026: *"yüzde 21 vat ücreti fiyatın içinde olsun. Faturayı bu
şekilde yap"* → aynı gün *"kdv fiyatın içinde gelmiyor"*).
- Fiyatlar **brüt**: ödenecek tutar değişmez. Ama bir KDV faturası matrahı,
  oranı ve KDV tutarını **ayrı ayrı** göstermek zorunda — yalnız brüt yazan bir
  belgeyle alıcının muhasebesi indirim yapamaz, satıcının beyanı dayanaksız
  kalır. Belge `Total`'in **altına** iki satır basar:
  `Taxable amount (excl. VAT)` ve `VAT %<oran> (included in the total above)`.
- **Net aşağı yuvarlanır, KDV FARKTAN bulunur.** İki tutarı ayrı ayrı
  yuvarlamak toplamı bir kuruş kaydırır ve belge kendi içinde tutmaz.
- **Oran yoksa hiçbir şey basılmaz** (`vat_rate > 0` **ve** `vat_included`).
  Varsayılan bir oran koymak, KDV'siz kesilmiş bütün mevcut faturalara sessizce
  vergi eklerdi. Tavan **%100**: yazım hatasıyla girilen bir "210" matrahı
  negatife doğru ezer — sınır hem panelde hem kurucularda.
- **Kargo da matraha dâhil:** ayrışma brüt **genel toplam** üzerinden yapılır
  (mal + kargo), mal toplamı üzerinden değil.
- **Alan, kardeşleri `vat_note` ve `shipping`'in bulunduğu HER YERDE olmalı.**
  "Gelmiyor" şikâyetinin sebebi tam buydu: oran yalnız **tek satırlık** yolda
  okunuyordu. Birleşik çubukta kutu **yoktu** (birleşik belge oranı birincil
  ref'ten okur, o kayda yazan hiçbir şey yoktu → birleşik fatura hiçbir zaman
  KDV taşıyamıyordu), birleşik kesim `invoice_vat_note`/`invoice_shipping`
  yazarken `invoice_vat_rate` **yazmıyordu** (redraft aynı numarayla KDV'siz
  yeniden çizerdi) ve **iki taslak yolu da** formda o an yazan oranı
  taşımıyordu — yani **kontrol adımının kendisi yanlış belgeyi gösteriyordu.**
  Kurucular artık `?float $vatRateOverride` alıyor; `0` bilinçli bir değer
  (kutuyu silen operatör KDV'siz önizleme bekler), `null` "hiç gönderilmedi".
- **Ölçüldü (7 Eyl 2026, canlı sunucu, üç Burberry kabulü O795BA/OED4CC/O7A484,
  TYREX):** mal €3.300,00 + kargo €20,00 = **€3.320,00**; matrah **€2.743,80**;
  KDV %21 **€576,20**. Numara yanmadı, diske yazılmadı, müşteriye gitmedi.
- Kesimden **önce** birleşik taslağı görmenin iş akışı yolu:
  `send-campaign-preview.yml` → `reply_letter=invoice_combine_draft`,
  spec `refs=<r1,r2,r3>|ship=<EUR>|vat=<yüzde>|seller=<uid>`. `invoice_draft`'tan
  farkı: o **kesilmiş** belgeyi aynı numarayla yeniden çizer, bu **hiç
  kesilmemiş** kabulleri birleştirip gösterir. Reddederse önce refleri ve ilan
  satıcılarını yazar — "listeden seçin" diyen bir hata, seçilecek listeyi de
  vermeli.
- Test: `tests/invoice_vat_test.php` (aritmetik, belge, oran yokken susma, kablo
  denetimi) ve `invoice_seller_pick_test.php §9b` (iki kurucu, override,
  sınırlar, redraft).

**KURAL 5n — Birleşik faturayı TEK gövde keser; mektup ödemenin BİLDİRİLME
yolunu da yazar** (operatör, 7 Eyl 2026: *"müşteriye faturayı gönder. havale
yaptıktan sonra haber versin"*).
- Tek uygulayıcı: `vestra_offers_combined_invoice_issue()`. Panelin
  **✓ Approve** düğmesi ve `send-campaign-preview.yml` →
  `reply_letter=invoice_combine_draft` + `apply=true` **aynı** fonksiyonu
  çağırır. Panel kendi elle yazılmış kesim dizisini taşıyordu (kayıt yazımı,
  `vestra_ensure_invoice`, sipariş satırı, alıcı mektubu) ve iş akışından
  birleşik fatura kesmenin yolu **yoktu** — KURAL 5f'in zaten bir kez kaydettiği
  düzen: iki kesim yolu ayrışır ve ayrışma **belgede** görünür.
- **Sıra: önce KAYIT, sonra BELGE.** Yarıda kalan bir kesimde en kötü durum
  "bağlanmış ama faturasız" olsun diye — onay kuyruğu onu yeniden gösterir.
  Tersi, "faturalı ama bağlanmamış" bir grup bırakırdı.
- **Mektup ödeme sonrası haber verme yolunu yazar.** Eski metin "hesaba havale
  edin, mal ödeme gelince çıkar" deyip duruyordu: parayı gönderen müşterinin
  söyleyecek yeri yoktu, iki taraf da ötekinin sırasını bekliyordu (havale
  günlerce görünmüyor). Şimdi sipariş sayfasındaki dekont kutusunu (KURAL 7 —
  yükleme operatöre haber düşürür ve otomatik iptal saatini **durdurur**, yani
  "haber verdim" ile sistemin gördüğü aynı şey olur) ve düz cevabı birlikte
  yazıyor. KDV fiyata dâhilse matrahı ve vergiyi mektupta da ayırıyor,
  **çizicinin hesabıyla** (net aşağı yuvarlanır, vergi farktan) — iki ayrı
  yuvarlama mektubu ekindeki belgeden bir kuruş uzaklaştırırdı.
- **Kesilmiş bir faturaya ikinci mektup:** `reply_letter=payment_notice`
  (`to=order:<ref>` şart). Kısa; **saat BAŞLATMAZ** — o `payment_due`'dur ve
  gerçekten 5 iş günlük iptal saatini kurar. İkisini tek metinde birleştirmek,
  operatörün sormadığı bir tehdidi de göndermek olurdu. Faturasız, ödenmiş,
  gönderilmiş, iptal, escrow **ya da dekontu zaten yüklenmiş** siparişte
  **durur** (yüklemiş olana yeniden istemek, yaptığı işi görmediğimizi söyler —
  KURAL 2b'nin aynı dersi). Rakamlar kayıttan: tutar sipariş satırından, numara
  ve para birimi kesilmiş faturadan. IBAN gövdeye yazılmaz, faturada duruyor.
- **`invoice_combine_draft` reddederken durumu YAZAR.** "Bu teklifin faturası
  zaten kesilmiş" doğru bir ret ama sorunun cevabı değil: numara, kayıtlı kargo/
  KDV oranı/VAT satırı, grup üyeleri, ödendi bayrağı ve sipariş satırı basılır.
  **Birincil ref, seçilen ilk ref değildir** — kesim sırasında ilk işaretlenen
  tekliftir ve diğerleri ona `invoice_group_ref` ile bağlıdır; ilk yazımda
  `$refs[0]`'ı birincil sanıp `follow_group=false` verdim ve blok faturalı bir
  grupta **hiç çalışmadı**. Önce bağı izle, sonra sor.
- Test: `invoice_seller_pick_test.php §9c` (33 iddia) ve
  `order_letters_test.php` (payment_notice, 25 iddia).

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

**KURAL 29 — Çevrilmiş fiyat 10 kuruşa YUKARI yuvarlanır; EUR ve FATURA hariç**
(operatör, 17 Eyl 2026: *"usd de tüm katalog fiyatlarını küürat varsa düzleştir
örenek 34,56 - 34,60"*).
- Katalogun **EUR** tarafı hep düz (39,00 / 85,00 / 120,00) çünkü rakamları
  operatör yazıyor; çevrilmiş taraf değildi — kur çarpanı 34,56 / 45,3258 /
  65,4027 üretiyordu.
- Tek yuvarlayıcı: `vestra_money_round()` (`inc/money.php`), adım
  `VESTRA_MONEY_STEP = 0.10`. **Sayfa, ürün sayfasının canlı hesaplayıcısı (JS)
  ve dropship USD tahsilatı üçü de ondan geçiyor.** İkinci bir yuvarlama, bu
  deponun tekrar tekrar kaydettiği *"sayfada bir, kasada başka rakam"* hatasını
  üretirdi: JS adımı **sabitten** basılıyor (elle `0.10` yazılsaydı kademe
  tablosu US$45,40 derken canlı toplam US$45,33 derdi).
- **YÖN YUKARI, ve bu bir tercih değil:** en yakına yuvarlamak 34,54'ü 34,50
  yapardı — ilan edilen EUR fiyatın **altında** bir rakam. Aşağı yuvarlamak
  sessizce marj veriyor; yukarı yuvarlamak en fazla 9 kuruş ekliyor ve
  eklediğini **ekranda gösteriyor**.
- **EUR'a hiç dokunulmuyor** (katalogun kendi birimi) ve **FATURA kapsam DIŞI**
  (KURAL 5i): belgede birim × adet = satır tutmak zorunda ve çevrim siparişin
  **damgalı** kuruyla, tam kuruşla yapılıyor. Belgeyi güzelleştirmek, belgeyi
  kendi içinde tutmaz hâle getirirdi.
- **Stripe tahsilatı da yuvarlanıyor**, çünkü "Buy now" düğmesi ziyaretçinin
  biriminde `vestra_money()` ile yazdırılıyor: tahsilat yuvarlanmasaydı aynı
  kutuda düğme US$45,40, hemen altındaki tahsilat satırı US$45,33 derdi. Navlun
  da aynı gövdeden (sayfa bölge ücretlerini `vestra_money()` ile basıyor).
- **Çevrim notu yuvarlamayı SÖYLÜYOR** (9 dilde, tek yeni anahtar): gösterilen
  rakam artık `kur × EUR` değil; söylemeseydik okuyan kendi çarpımını yapıp
  tutturamaz ve hangisinin doğru olduğunu soramazdı — faturadaki `fx_note`'un
  aynı gerekçesi (KURAL 5p).
- **Canlı ölçüm (17 Eyl 2026, `seo-check` → `cur_probe=true`):** 39,00 EUR →
  ham 44,7759 → ekranda **US$44.80**; **A$63.00**; **C$62.70**; üçü de 10
  kuruşun katı, not yuvarlamayı yazıyor. Sonda fonksiyonun **varlığına** değil
  gerçek bir çevrime bakıyor — eski bir kopya da parse edilir.
- Test: `tests/money_round_test.php` (34 iddia, iki yön — `invoice.php`'nin
  yuvarlayıcıyı **hiç** çağırmadığı dâhil). Düşebildiği doğrulandı: ham çevrim
  **2 kırmızı**, en yakına yuvarlama **5**, JS adımı silinince **2**, Stripe ham
  çevrime dönünce **2**, faturaya yuvarlama girince **1**. `dropship_plan_test`
  eski tam kuruşu pinliyordu; **davranış bilerek değişti**, iddia elle yazılmış
  bir rakam yerine sitenin kendi yuvarlayıcısını okuyacak şekilde düzeltildi.

**KURAL 32 — HOŞ GELDİN İNDİRİMİ: her müşterinin İLK siparişine %5; bölgesel
indirim alan HARİÇ** (operatör, 19 Eyl 2026: *"yüzde 5 Welcome indirimini her üç
siparişe ekle … bundan sonraki her müşterinin ilk siparişine de ekle afrika ve
yüzde 8 yada 10 indirim alanlar hariç"*).

- **DIŞLAMA ÖLÇÜTÜ TEK FONKSİYON: `vestra_region_discount_pct()`.** Operatörün
  saydığı iki şey — "afrika" ve "yüzde 8 yada 10 indirim alanlar" — **aynı
  küme**: Afrika'nın 54 ülkesi zaten %8 grubu, JP/AU/SG/HK + Güney Amerika +
  CZ/PL %10 grubu (bkz. `inc/region_discount.php`). Elle ikinci bir ülke listesi
  tutmak, yarın bölgesel indirime bir ülke eklendiğinde o ülkeye **sessizce iki
  indirim birden** vermek olurdu.
- **Tek karar noktası `vestra_welcome_auto($email, $buyer, $voucherApplied,
  $exceptRef)`** (`inc/vouchers.php`). Kasa (`order.php`) ve geçmişe dönük yazma
  (`vestra_order_set_discount`) aynı cevabı oradan okuyor; iki kopya yazılsaydı
  biri "hak ediyor" derken öteki etmez derdi ve fark ancak **müşterinin
  faturasında** görünürdü. Dönen `why` bir **gerekçe** (`ok` / `region` /
  `not_first` / `voucher` / `no_email`): tek başına "0" cevabı *"hak etmiyor"*
  ile *"hesap okunamadı"*yı ayırmıyor.
- **`$buyer` HESAP DİZİSİ, ülke dizgesi değil** — bölgesel indirimi fiyata
  uygulayan yol da (`vestra_viewer_discount_pct` → `auth_user()`) hesaba bakıyor.
  Sipariş satırının `country` alanından okumak, hesabı Avusturya'da olup
  teslimatı Nijerya'ya isteyen bir alıcıyı hak etmediği bir dışlamaya sokardı.
- **Kasada hesap `auth_user()` ile okunuyor, `$me` ile DEĞİL:** `$me` yalnız
  girişli dalda tanımlı ve PHP'de tanımsız değişken `null`'dır — misafir bir
  sipariş *"bölgesel indirimi yok"* diye okunur ve Afrika'daki bir alıcıya %8'in
  **üstüne** %5 daha verilirdi. Aynı dosyada bir kez yaşandı (`$user` vakası).
- **YANLIŞ YAZILMIŞ BİR KUPON ARTIK İNDİRİMİ ENGELLEMİYOR.** Otomatik dal yalnız
  `$discount <= 0` iken çalışıyor; "kod YAZILDI MI" ayrı bir soru ve cevabı
  olmamalı — bir harflik hataya onlarca euro fatura etmek doğru olmazdı. Mektup
  **ikisini birden** yazıyor (*"kodunuz işlemedi"* + *"indiriminiz düştü"*);
  tek dala sıkıştırmak kodu yazan alıcının sorusunu cevapsız bırakırdı.
- **Geçmişe dönük yazma: `vestra_order_set_discount()`** (`inc/orders.php`).
  İndirim alanı `orders.csv`'de **baştan beri vardı** ve fatura çizicisi onu
  zaten okuyor (`vestra_order_invoice_payloads()` satıcı başına bölüyor, PDF
  *"Voucher … −€X"* satırını basıyor) — **eksik olan yazma yoluydu**: kupon
  ancak ALICI kasada kod yazarsa düşüyordu. Navlunda (KURAL 5k), teslimat
  adresinde (5l) ve renkte aynı boşluk vardı; bu **dördüncüsü**.
  - **TUTAR ELLE VERİLMEZ, yüzdeden türer** ve yuvarlama `voucher_discount()`'ta,
    yani sepetin kullandığı **aynı** yuvarlayıcı (KURAL 5m'nin KDV dersi).
  - **TABAN SATIRLARDAN** (`vestra_order_lines`), `subtotal` sütunundan **değil**:
    o sütun indirim **sonrası** değeri taşıyor ve üst üste iki yazma **bileşik**
    bir rakam üretirdi.
  - indirim + toplam + `subtotal` + `payout` **birlikte** yazılır, yedeklenir,
    atomik takas edilir ve **geri okunur**.
  - **PARASI GELMİŞ sipariş KOŞULSUZ RED** (ölçüt `vestra_order_payment_settled`,
    KURAL 7b'nin tek karar noktası): tahsil edilmiş bir tutarı geriye dönük
    indirmek, müşterinin ödediğinden farklı bir belge üretir; **iade ayrı bir
    karardır**.
  - **FATURALI sipariş varsayılan RED**, `|allow_invoiced=1` ile geçilir ve dönen
    `must_redraft` çağıranı KURAL 5f'e yolluyor (`set_colours` deseni).
  - **ÜCRET MUTLAK KORUNUR**, oran olarak yeniden hesaplanmaz: escrow koruma
    ücreti Stripe'ta çoktan tahsil edilmiş olabilir.
- İş akışı: `seller-products.yml` → **`admin_mode=order_discount`**
  (`payload='pct=5|code=<kupon>|allow_invoiced=1'`, varsayılan **kuru koşu**).
  Kuru koşu **kararı da** basıyor ve **istek ile karar ayrışıyorsa uyarıyor** —
  bölgesel indirim yüzünden hariç tutulmuş bir müşteriye elle %5 yazmak aynı
  satışa iki indirim olurdu. E-posta çıktıda **maskeli**.
- **Müşteri mektubu: `reply_letter=order_discount`** (`to=order:<ref>` şart).
  Hiçbir rakam metne gömülü değil; mal toplamı **faturanın okuduğu aynı
  fonksiyondan**. **Dört dil tek gövdede** (en/de/es/fr — üç müşteri AT/ES/FR);
  dil **hesabın kayıtlı dilinden** (sipariş satırının `country`'sinden çözmek bu
  depoda üç kez yanlış cevap verdi). Biçim de dile bağlı: Almanca/İspanyolca/
  Fransızca ondalık **virgül** kullanıyor ve İngilizce bicimde basılan
  `1,200.00` o kutuda bin kat sapmış gibi okunur.
  - **İNDİRİM İŞLENMEMİŞSE DURUYOR** (boş bir *"indiriminiz düştü"* mektubu,
    müşteriyi kaydında olmayan bir rakamı aramaya yollar).
  - **FATURA ESKİ TUTARI TAŞIYORSA DURUYOR** (`|fatura − sipariş| > 0.02`):
    kesilmiş bir belge eski rakamı taşırken *"yeni toplamınız şudur"* yazmak,
    müşteriye **elindeki kâğıtta olmayan** bir rakam söylemektir ve o kişi
    hangisinin doğru olduğunu soramaz bile. Yol KURAL 5f: önce yeniden çizim.
  - **"İlk siparişiniz için" cümlesi ÖLÇÜLÜYOR**, varsayılmıyor
    (`voucher_customer_order_count`, bu sipariş hariç) — ikinci bir siparişe
    operatör kararıyla indirim işlenirse o cümle müşterinin kendi kaydıyla
    çelişirdi (KURAL 3'ün mektup hâli). Fatura cümlesi de koşullu.
- **ÖLÇÜLEN ÜÇ SİPARİŞ (19 Eyl 2026, `diag-live` → `find_ref`), üçü de Avrupa,
  yani üçü de bölgesel indirim ALMIYOR ve %5'i hak ediyor:**

  | ref | firma / ülke | mal | navlun | mevcut toplam | durum |
  |---|---|---:|---:|---:|---|
  | `VES-1F0C9350` | BRITISHSTYLE · Österreich | 789,00 | 20,00 | 809,00 | pending, faturasız |
  | `VES-A11C0C97` | LA ISLA DE MIRABEL SL · ES | 7.779,60 | — | 7.779,60 | pending, `invoice_seller_uid=vestra` |
  | `VES-55E4F6E1` | Mob · France | 1.200,00 | — | 1.200,00 | pending, `invoice_seller_uid=vestra` |

  Beklenen: **−39,45 → 769,55**, **−388,98 → 7.390,62**, **−60,00 → 1.140,00**.
  *Yan bulgu: `VES-1F0C9350`'nin fatura kesicisi kayıtta artık `vestra` değil
  **GARAGE LE PARIS** (`7ab30f26…`) — operatör değiştirmiş, yani 17 Eylül'de
  "EUR ödeme kutusu boş" diye kesilemeyen belge artık kesilebilir.*
- Test: `tests/welcome_discount_test.php` (**124 iddia**, iki yön). Kum havuzunda
  **gerçekten yazıyor** ve doğrulama satırın değişmesine değil **BELGENİN
  KENDİSİNE** bakıyor: PDF çizdirilip içinde `Voucher`, kod, `39.45` ve `769.55`
  aranıyor — **indirimsiz bir belgede bunların hiçbiri olmamalı** (tek yön
  yazılsaydı "her belgeye indirim satırı basan" bir kusur da yeşil kalırdı).
  Yakın komşu tuzağı adıyla: **AT (Avusturya) %5 ALIR ↔ AU (Avustralya) %10
  grubu**, ve Niger ↔ Nijerya.
  Düşebildiği doğrulandı, her sabotajın **gerçekten uygulandığı `grep -c` ile
  ayrıca yazdırılarak**: bölge dışlaması kalkınca **8 kırmızı**, otomatik hiç
  vermeyince **7**, ödeme muhafazası kalkınca **3**, faturalı muhafazası kalkınca
  **3**, taban `subtotal` sütununa dönünce **4**, `order.php` bloğu silinince
  **3**, dört dil tek metne düşürülünce **3**, "ilk sipariş" cümlesi koşulsuz
  yazılınca **1**, fatura-tutarı muhafazası kalkınca **1**.
- **KENDİ HATALARIM, kayda geçsin:**
  1. Faturalı muhafazasını sabote ederken `replace(…, 1)` **ilk** eşleşmeyi buldu
     ve o `vestra_order_set_colours`'unkiydi — aynı satır iki fonksiyonda duruyor.
     `grep -c` *"uygulandı"* dedi, oysa ölçmek istediğim fonksiyona **hiç
     dokunmamıştı** ve test **0 kırmızı** verdi. Bu dosyada kayıtlı tuzağın
     aynısı; sabotaj fonksiyon gövdesine daraltılınca **3 kırmızı** çıktı.
  2. *"Bileşik hesap yapılmıyor"* iddiam arada `pct=0` ile sıfırlıyordu, yani
     tabanı 789'a geri döndürüyor ve **ölçmek istediği şeyi ölçmüyordu**: o
     sabotaj yalnızca 1 (kablolama) iddiasını düşürdü. Üst üste iki yazma
     eklendi, aynı sabotaj **4 kırmızı** verdi.
  3. *"Fatura toplamı"* iddiasını `meta['total']` ile yazmıştım; sipariş yükünde
     o alan **bilerek boş** (genel toplamı çizici kendi hesaplıyor). **Kod
     doğruydu, iddia yanlıştı** — iddia belgenin kendisine bağlandı.

**SUNUCUNUN DIŞARI ÇIKIŞI TAMAMEN KAPALI — 19 Eyl 2026, ölçüldü** (bu işin
canlıya inmesini engelleyen şey ve kendisi bir OLAY). Üç bağımsız host:

| Ölçüm | Sonuç |
|---|---|
| deploy → `git fetch github.com` | `Could not resolve host: github.com` (**6 deneme**, iki koşu) |
| `diag-live` → `ip_probe` (coğrafi API) | **0/4**, her biri **1 ms** — zaman aşımı değil, anında çözümleyici hatası |
| `diag-messages` → `mailcfg` (Brevo API) | `Brevo events HTTP 0` |

- **SİTE AYAKTA** (runner'ın HTTP kontrolü geçiyor, SSH çalışıyor, PHP koşuyor);
  ölü olan yalnız **sunucudan dışarı**. Yani vitrin açık, ama:
  **hiçbir VESTRA e-postası çıkamıyor** — sipariş onayı, şifre sıfırlama, fatura
  mektubu, kampanya. `vestra_send_mail()` Brevo'ya ulaşamayıp `false` dönüyor.
- Bu yüzden bu işin **kod tarafı bitti ve push edildi**, **canlı tarafı
  BEKLİYOR**: `vestra_order_set_discount()` sunucuda henüz yok (deploy inemedi),
  ve inse bile mektuplar bugün gönderilemez.
- *Ders, bu dosyada zaten kayıtlı olanın kardeşi: "koşunun 'success' demesi
  yetmez" — burada tersi geçerli, **koşunun 'failure' demesi de kodun yanlış
  olduğu anlamına gelmiyor**. Deploy'un düştüğü yer ilk `git fetch`, yani
  repodaki hiçbir satır okunmadan önce.*
- **ÇIKIŞ AYNI GÜN GERİ GELDİ (19 Eyl 2026, akşam) — yukarıdaki tablo artık
  GEÇMİŞ bir olayın kaydı, mevcut durum DEĞİL.** Ölçüm dolaylı değil doğrudan:
  aynı gün deploy indi (`0c00c38f` sunucuda), `vestra_order_set_discount()`
  canlıda çalıştı ve **üç müşteri mektubu Brevo tarafından kabul edildi**
  (`GONDERILDI`). Kesinti geçiciydi; not, ileride aynı belirtiyi görenin
  "kalıcı" sanmaması için olduğu gibi duruyor.

**KURAL 32 (devamı) — ÜÇ MEKTUP GÖNDERİLDİ; ve bir faturanın €75'i belgeye HİÇ
BASILMAMIŞTI** (19 Eyl 2026; operatör: *"fatura güncellenmistir diyerek"* +
*"faturalara shipping costlari eklemeyi unutma"* + *"digerlerinede kendi
dilinde"*).

- **Sıra ÖNEMLİ ve bu sırayı bozmak bir belgeyi yalanladı:** `VES-A11C0C97`'ye
  önce indirim işlendi, fatura o hâliyle **kesildi**, navlun (€75) **sonra**
  yazıldı. Sipariş satırı €7.465,62 derken **INV-2026-1014 €7.390,62** taşıyor,
  yani belge tam **€75 eksik**. `order_discount` mektubu bunu kendi muhafazasıyla
  yakaladı ve **gönderimi durdurdu** (`|fatura − sipariş| > 0.02`) — müşteriye
  elindeki kâğıtta olmayan bir rakam yazılmadı. *Bir siparişin rakamını
  değiştiren her yazma, faturası varsa KURAL 5f'in yeniden çizimini gerektirir;
  "önce indirim, sonra navlun" iki ayrı yazma demek ve ikincisi belgeyi bayatlatır.*
- **BELGENİN KENDİSİ ÖLÇÜLDÜ, yazma mesajı değil.** Yeniden çizim sonrası
  `issue` adımı artık ham PDF baytlarında arıyor: `belgede navlun: VAR
  (Shipping 75.00) · belgede indirim: VAR (388.98) · belgede toplam: VAR
  (7,465.62)`, 19.324 bayt, sha256 yazılı, `AYNI numarayla yeniden uretildi`.
  Gerekçe bu depoda kayıtlı: fotoğrafsız bir PDF yıllarca *"üretildi, boyut
  makul"* diye geçmişti. `inc/pdf.php` akışları sıkıştırmıyor, o yüzden ham
  baytta metin aranabiliyor; sıkıştırma bir gün eklenirse adım **bunu ayrıca
  yazıyor** ki "yok" diye yanlış bir kırmızı değil, sebebi söyleyen bir satır
  çıksın.
- **Üç katman da geri okundu** (KURAL 5f'in üçlüsü): PDF baytı, fatura meta'sı
  (`INV-2026-1014 tutar 7.465,62`) ve sipariş satırı artık aynı rakamı söylüyor.
- **Gönderilenler — her müşteri KENDİ dilinde** (operatör, aynı gün, iki adımda:
  önce *"diger iki müsteriyi fransizca"*, sonra *"LA ISLA ... ispanyolca"* +
  *"digerlerinede kendi dilinde"*; ikincisi birincisini EZİYOR ve bu dosyada
  olduğu gibi yazılı — çelişen iki kayıt hangisinin geçerli olduğunu okunamaz
  yapar):

  | sipariş | müşteri | mal | indirim %5 | navlun | yeni toplam | fatura | dil |
  |---|---|---:|---:|---:|---:|---|---|
  | `VES-1F0C9350` | BRITISHSTYLE · AT | 789,00 | −39,45 | 20,00 | **769,55** | INV-2026-1002 | de |
  | `VES-A11C0C97` | LA ISLA DE MIRABEL SL · ES | 7.779,60 | −388,98 | 75,00 | **7.465,62** | INV-2026-1014 | es |
  | `VES-55E4F6E1` | Mob · FR | 1.200,00 | −60,00 | 20,00 | **1.160,00** | INV-2026-1013 | fr |

  Üçünde de `ilk siparis mi: EVET` (ölçüldü, varsayılmadı) ve fatura cümlesi
  **`invoice_updated=1`** ile *"güncellendi, aynı numarayı koruyor, eski kopya
  geçersiz"* — operatörün istediği üçüncü ifade. Bayrak **açık** verilmek
  zorunda: şablon bunu ölçemez (dosya dün de vardı) ve taze kesilmiş bir belgeye
  "güncellendi" demek olmamış bir işlemi anlatırdı.
- **Dil sırası düzeltildi:** `spec lang=` artık **hesabın kayıtlı dilini EZİYOR**
  (önce tersiydi). Eski hâliyle operatörün açık talimatı sessizce yok sayılıyor,
  iş "başarılı" bitiyor ve müşteri istenmeyen dilde mektup alıyordu — kimsenin
  göremeyeceği bir hata. Koşu hangi kaynağın kazandığını ve ezilen değeri
  **yazıyor**.
- **KENDİ HATAM — üç önizlemeyi PARALEL koşturdum.** `send-campaign-preview`
  sunucuda **ortak bir geçici dosya** kullanıyor (`/tmp/vestra_buyer_reply.php`
  → `public_html/vestra_buyer_reply_tmp.php`); Almanca koşu o adımı erken
  bitirdiği için temiz çıktı, İspanyolca ve Fransızca koşular birbirini ezdi ve
  betiği **çalıştırmak yerine ekrana bastı**. İkisi de **çıkış 0 ile "success"**
  bitti ve **hiçbir mektup kurmadı** — yani `add-and-send`'in "paralel
  çalıştırılmaz" uyarısının aynı sınıfı, başka bir dosyada, ve bu kez zararsız
  kaldı yalnızca ölçümü okuduğum için. *Bu iş akışının koşuları SIRAYLA
  koşulur; ve bir koşunun "success" demesi, iş yaptığı anlamına gelmiyor —
  çıktıyı oku.*
- **Ölçüm tuzağı:** `issue` adımının doğrulama satırları şifreli PDF gövdesinden
  **ÖNCE** basılıyor, yani `get_job_logs` tail'i base64'ün içine düşüyor. Geniş
  pencere isteyip çıktıyı dosyaya düşürmek ve `BEGIN/END INVOICE ENC` arasını
  **atlayarak** okumak gerekiyor.
- **Platformun EUR ödeme kutusu artık ÇIKIYOR** (operatör banka adresini verdi;
  değer repoya YAZILMADI). Geri okuma: `bank_eur_address: VAR`, EUR kutusu **4 → 5 satır**,
  USD kutusu **6 satır (bozulmadı)**, `EUR kesimi (KURAL 5r): GECER`. Rakamlar
  yine repoya, iş akışı girdisine ve ssh betiğine **girmedi** — şifreli zarfla
  geçti, çıktıda yalnız VAR/YOK ve hane sayısı.

**KURAL 32 (devamı) — "Germany belirtilsin": KAYIT doluyken BELGE boş olabilir**
(operatör, 19 Eyl 2026, aynı akşam: *"banka adresini yazmamissin"* → *"eur
hesabi"* → *"Germany belirtilsin"* → *"hesabimizi verdigimiz iki faturayi
[VES-A11C0C97 / VES-55E4F6E1] bunlari ekle"*).

- **ÖNCE ÖLÇÜLDÜ ve ilk cevap "zaten var" çıktı.** 17:53'teki yazma koşusunun
  kendi günlüğü `bank_eur_address`'in tam değerini gösteriyor ve **Germany o
  değerin içinde** — ülke adı künyeye o an girmişti, EUR kutusu 4 → 5 satıra o
  yüzden çıkmıştı. *Adresi hafızadan yeniden yazıp künyeye basmak, doğru duran
  bir kaydı tahminle ezmek olurdu (KURAL 3); eski koşunun günlüğü kanıttı ve
  adres burada da yazılmıyor — künyenin değerleri panelde okunur.*
- **ASIL SORU BAŞKAYDI: kayıt bugün dolu olması, DÜN çizilmiş bir PDF hakkında
  hiçbir şey söylemiyor.** KURAL 5r kutuyu yalnız **kesim anında** garanti
  ediyor; künye tamamlanmadan önce kesilmiş bir belge kutusuz kalır ve bunu
  ancak **belgenin kendisi** gösterir. `issue` adımı navlun/indirim/toplamı ham
  baytta arıyordu, ödeme kutusunu **hiç sormuyordu** — eklendi (`IBAN:` /
  `Account number:` satırının VARLIĞI + `Bank address` + ülke adı; **numara
  basılmıyor**, kütük herkese açık).
- **Ölçüm iki belgeyi ayırdı ve biri gerçekten eksikti:**

  | Fatura | Ödeme kutusu | Banka adresi | Sebep |
  |---|---|---|---|
  | `INV-2026-1014` (ES) | VAR | **VAR (Germany)** | 18:0x'te zaten yeniden çizilmişti |
  | `INV-2026-1013` (FR) | VAR | **YOK** | 17:53'ten ÖNCE kesilmiş |

  Yani Fransız alıcının elindeki belgede IBAN vardı ama **bankanın adresi ve
  ülkesi yoktu** — SEPA dışından ödeyen için eksik, ve kimse fark etmemişti.
- Çözüm KURAL 5f: **aynı numarayla yeniden çizim**, e-posta gitmeden.
  `INV-2026-1013` 17.943 → **18.040 bayt** (+97 = banka adresi satırı),
  `(AYNI numarayla yeniden uretildi)`, tutarlar değişmedi (navlun 20,00 ·
  indirim 60,00 · toplam 1.160,00), `belgede banka adresi: VAR (ulke: Germany)`.
- **Ders, bu dosyada üçüncü kez:** *bir kaydın bugün dolu olması, o kayıttan
  ÜRETİLMİŞ belgelerin de dolu olduğu anlamına gelmiyor.* Kesim yolundaki
  muhafaza ileriye dönük çalışır; geriye dönük tek ölçü belgenin baytıdır.
  Fotoğrafsız PDF, €75'i basılmamış fatura ve bu, aynı sınıfın üç vakası.
- **Müşterilere hiçbir şey gönderilmedi** (KURAL 18). Belge düzeldi; haber
  verilip verilmeyeceği operatör kararı — tutar değişmediği için mektup şart
  değil, ama isteyen olursa `order_discount`'ın `invoice_updated=1` gövdesi
  aynı işi yapar.

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
- Zamanlama: sunucu crontab'ı 07:00 sunucu saati = 14:00 UTC (`cron_order_payment.php`,
  `deploy-vestra.yml` idempotent kurar + kuru-koşu kanaryası).

**KURAL 7b — "Parası geldi mi" TEK yerden sorulur; aynı olgunun İKİ kaydı vardı
ve biri ötekini HİÇ okumuyordu** (operatör, 19 Eyl 2026: *"hatayi düzelt biten
siparisler faturasi olusanlar tekrar fatura yap yada ödenmemis gösterilmesin"*).

- **Şikâyet iki yarıydı ve yalnız biri gerçekti — bu ölçülerek ayrıldı.**
  *"Tekrar fatura yap"*: kuyruk faturalı teklifi **zaten listelemiyor**
  (`admin.php:2541`/`2552` süzgeçleri ve `$pendingInvoiceCount` rozeti doğruydu),
  ve `/offer` tarafında ikinci numara **sunucuda reddediliyor** — operatörün
  *"bunlar tekrardan fatura yapilmasi icin sisteme ekleniyor … dimi"* sorusunun
  cevabı **hayır**: canlı koşu iki ref için de *"Bu teklifin faturası zaten
  kesilmiş — aynı satıra ikinci numara yakılmaz"* diyor. **Gerçek olan öteki
  yarıydı:** bitmiş satış panelde **⌛ Unpaid** duruyor ve alıcı **⚠ Payment
  due** bandı görüyordu.
- **ÖLÇÜLDÜ** (`diag-live` → `find_ref`, 19 Eyl 2026), operatörün yapıştırdığı
  iki ref de aynı vaka:

  | | `order_statuses` | fatura | `invoice_paid_at` |
  |---|---|---|---|
  | **O39419** | `completed` (paid 24 Ağu → to_vestra 26 Ağu → preparing 4 Eyl → shipped 9 Eyl → **completed 12 Eyl, ALICI işaretlemiş**) | INV-2026-1009 · 2.153,40 USD | **YOK** |
  | **OCD7D2** | `paid` (10 Eyl, admin) | INV-2026-1012 · 1.255,17 USD | **YOK** |

- **İki kayıt, iki ayrı yazan, sıfır ortak okuyan:**

  | Kayıt | Yazan | Okuyan |
  |---|---|---|
  | `order_statuses[ref].status` | `Admin ▸ Orders` durum seçici | otomatik iptal saati, sipariş sayfası, order-pdf |
  | `offer_responses[ref].invoice_paid_at` | `Invoice approvals` **✓ Paid** düğmesi | alıcının "Payment due" bandı **ve o düğmenin kendisi** |

  Yani parasını **26 gün önce** ödemiş, malını teslim almış, siparişi **kendi**
  tamamlandı işaretlemiş müşteriye hâlâ *"awaiting payment"* yazıyordu.
- **ASIL TEHLİKE HENÜZ PATLAMAMIŞTI ve düzeltme oraya kondu:** satırı `pending`
  kalmış bir siparişe `Invoice approvals`'tan **✓ Paid** konsaydı,
  `cron_order_payment.php` yalnız sipariş durumuna baktığı için **ödenmiş bir
  satışı kovalar**, "5 iş günü içinde ödeyin" mektubu yollar ve süre dolunca
  **OTOMATİK İPTAL** ederdi. İki ref de `pending`'i geçmiş olduğu için bu
  yaşanmadı; yol açıktı. Kapı bu yüzden her çağıranda değil
  **`vestra_order_payment_grace()`'in ilk aşamasında** (`phase='paid'`).
- **Tek karar noktası `vestra_order_payment_settled()`** (`inc/orders.php`):
  - **TÜRETİLİYOR, üçüncü bir bayrak olarak SAKLANMIYOR** —
    `vestra_order_in_review()`'in kendi gerekçesi; saklansaydı günün birinde o
    da ötekilerden ayrışırdı.
  - **Ölçüt ZİNCİRİN KENDİSİNDEN** (`VESTRA_ORDER_STEPS`), elle yazılmış bir
    durum listesinden değil: yarın araya bir adım girerse (`to_vestra` böyle
    girmişti) o da kendiliğinden ödenmiş tarafta kalır.
  - **`cancelled` zincirde bilerek yok** → ödenmiş saymıyor.
  - **Birleşik belgede işaret BİRİNCİL ref'te durur** (KURAL 5e), bağ izleniyor
    — üyeyi kendi başına sormak tek belgeyle ödenmiş bir satışın yarısını
    "ödenmemiş" gösterirdi.
- **Bu, bu dosyada ZATEN bir kez ödenmiş dersin ÜST KATMANI:** 5 Eyl 2026'da
  `delivered` zincirde olmadığı için `vestra_order_status_label` default'a
  düşüyor ve teslim edilmiş sipariş *"Awaiting payment"* yazıyordu. Orada eksik
  olan bir **ADIM**dı, burada eksik olan bir **KAYIT**.
- **Yazma yolu DEĞİŞMEDİ** — düğme hâlâ `invoice_paid_at` yazıyor; taşınan şey
  **OKUMA**. Okuyanlar artık aynı fonksiyondan soruyor: `buyer.php`'nin
  "Payment due" bandı, panelin **Paid** sütunu, `cron_order_payment` (yeni
  `paid` aşaması), `payment_due` ve `payment_final` mektupları (parası gelmiş
  satışta artık **DURUYOR**) ve birleşik taslağın *"odendi"* satırı.
- **Kaynağı SİPARİŞ DURUMU olan satırda toggle düğmesi ÇİZİLMİYOR:** işareti
  oradan kaldırmak hiçbir şey yapmazdı ve **çalışmayan bir düğme, olmayan bir
  düğmeden kötüdür** (KURAL 4'ün karşı teklif alanı dersi). Yerine durum +
  *"değiştirmek için Orders sekmesi"* yazıyor.
- **KAPSAM KARARI — `🔁 Redraft & email` düğmesi BİLEREK DURUYOR.** Ödenmiş
  satışta da görünmeye devam ediyor çünkü KURAL 5f'e göre **kesilmiş bir
  faturayı aynı numarayla düzeltmenin tek yolu o**; gizlemek meşru bir düzeltme
  yolunu kapatırdı. Bitmiş satışı açık iş gibi gösteren şey o düğme değil,
  **"⌛ Unpaid" sütunuydu** ve düzeltilen o. *Operatör ödenmiş satışta onu da
  gizlemek isterse ayrı bir karar.*
- **Sonda da düzeltildi (bu depoda ÜÇÜNCÜ kez):** `find_ref`'in `$safe`
  listesinde `invoice_paid_at`/`invoice_group_ref` **YOKTU**, yani sorulan
  soruyu — *"bu fatura ödenmiş mi"* — cevaplayamıyordu. `check_images` yalnız
  `approved` geziyordu, `price_list` `sold_out` basmıyordu; **görmediği bir alan
  hakkında yeşil veren sonda, başka bir şeyin yeşilini gösterir.**
- **CANLI GERİ OKUMA (19 Eyl 2026, deploy 1314 / `ce03b87b`;
  `reply_letter=invoice_combine_draft`, `send=false`):**
  `O39419 → odendi: EVET (siparis durumu: completed)`,
  `OCD7D2 → odendi: EVET (siparis durumu: paid)`. Düzeltmeden önce ikisi de
  **`hayir`** diyordu. İkisi de ayrıca *"faturası zaten kesilmiş"* ile
  reddediliyor — numara yakılmıyor.
- **"ÖDENDİ" TARİHİ: `updated_at` ödeme tarihi DEĞİLDİR** — ve bunu **canlı
  ölçüm kendi kodumda buldu.** Sonda *"EVET (completed, **2026-09-09**T14:03)"*
  yazıyordu; kayıt ise `paid_at` **taşımıyor**, `updated_at` = 9 Eyl (kargo
  damgası) ve geçmişe göre para **24 Ağustos**'ta gelmiş. Rakam doğruydu,
  **etiket yalandı** — bu depoda kayıtlı: *yanlış rakam sorgulanır, yanlış
  etikete inanılır.* Sıra artık **açık `paid_at` → geçmişteki `paid` satırının
  damgası → BOŞ**; bilinmeyen bir tarihi uydurmaktansa yazmamak (KURAL 3).
  Karar (`settled`) değişmedi, yalnız gösterilen tarih — ama o tarih operatörün
  *"bu ne zaman ödendi"* sorusunu cevaplayan tek satır. Düzeltme sonrası aynı
  sonda (deploy 1316 / `4de36fec`): `odendi: EVET (completed,
  **2026-08-24**T12:59:14)`.
- Test: `tests/order_payment_test.php` 37 → **76 iddia**, iki yön de. Kart
  `admin.php` kum havuzunda **gerçekten çizdiriliyor** (`php -l` bu depoda iki
  çalışma-zamanı hatasını geçirmişti). Düşebildiği doğrulandı, her sabotajın
  **gerçekten uygulandığı `grep -c`/satır sayımıyla ayrıca yazdırılarak**:
  zincir dalı kapatılınca **6 kırmızı**, `settled` hep true olunca **17**,
  cron'a ref geçmeyince **1**, grup bağı izlenmeyince **1**, `buyer.php` eski
  hâline dönünce **2**, panel eski hâline dönünce **6**, tarih türetmesi eski
  hâline dönünce **2**.
- **Kendi ölçüm hatam:** *"✓ Paid görünüyor"* iddiasını `substr_count` ile
  yazdım ve **KIRMIZI döndü** — kartın kendi yardım metni de aynı dizgeyi
  taşıyor (*"Ödeme gelince ✓ Paid ile işaretleyin"*), yani iddia rozeti değil
  **YARDIM METNİNİ** ölçüyordu. Kod doğruydu, ölçü yanlıştı; iddia rozetin kendi
  işaretlemesine daraltıldı. *`class="msgtick` önekinin `msgtickdefs`'i
  yakalamasıyla ve "iddia satırı değil navigasyonu ölçüyordu" ile aynı sınıf.*

**KURAL 7c — Kesilmiş fatura kartı bir KUYRUK DEĞİL ve hiç boşalmıyor; ödenmiş
satırlar KATLANIR, silinmez** (operatör, 19 Eyl 2026, yukarıdaki düzeltme
indikten hemen sonra aynı ekrana bakarak: *"bu offerlar neden halen cikiyor
eski degilmi"*).

- **Soru önce İKİYE AYRILDI, çünkü cevabı farklı.** Operatör satırları "hâlâ
  açık iş" diye okuyordu; ölçüm üç şeyi birden gösterdi:

  | Soru | Ölçüm |
  |---|---|
  | Bunlar onay mı bekliyor? | **Hayır.** Kartın kendi yorumu (`admin.php:4562`): *"onay kuyruğu kesilenleri düşürür; oysa operatör kesilmiş belgeyi de yönetmek istiyor"* — bu, Redraft + Paid işareti için duran **yönetim listesi** |
  | Sekme rozeti bunları sayıyor mu? | **Hayır.** `$pendingInvoiceCount` yalnız `count(vestra_invoices_for_ref($ref)) === 0` olanları sayıyor (`admin.php:2545`, `2557`) |
  | İkisi de gerçekten kapandı mı? | **Evet**, canlı: `OCD7D2 → odendi EVET (paid, 10 Eyl)`, `O39419 → odendi EVET (completed, 24 Ağu)`; ikisi de ikinci numarayı reddediyor |

- **Yani kod kusuru yok, OKUNUŞU kusurlu.** Liste **yapısı gereği hiç
  boşalmıyor**: kesilen her teklif faturası sonsuza kadar orada, üstelik
  sekmenin adı *"Invoice **approvals**"*. Bu, KURAL 2c'nin ta kendisi — hiç
  boşalmayan bir liste okunmamayı öğretir. Bugün 2 satır; her kesim bir satır
  daha ekliyor.
- **SİLİNMEDİ, KATLANDI** (operatör seçimi; "tamamen gizle" seçeneği sunuldu ve
  **bedeliyle birlikte** yazıldı). KURAL 5f'e göre kesilmiş bir faturayı **aynı
  numarayla** düzeltmenin tek yolu o karttaki **Redraft**; gizlemek o düzeltme
  yolunu panelden **erişilemez** yapardı. Katlanmış bölüm tek tık uzakta ve
  **testte ayrı bir iddia** Redraft'ın orada durduğunu ölçüyor.
- **SATIR GÖVDESİ TEK KOPYA.** İki ayrı `foreach` yazmak Redraft formunu, kalem
  seçicisini ve Paid sütununu ikiye bölerdi ve ilk düzenlemede ayrışırlardı (bu
  depoda defalarca kayıtlı: `desc`/`sizes`, faturanın üç katmanı, dört mektup
  gövdesi). Satırlar **aynı gövdeden** çizilip tamponlanıyor (`ob_start`), sonra
  iki tabloya dağıtılıyor. Ölçüt yine **tek karar noktası**
  `vestra_order_payment_settled()` — *"ödendi mi"*nin ikinci bir tanımı
  yazılmadı.
- **Hepsi ödenmişse üst tablo hiç çizilmiyor**, yerine tek satır (*"hepsi
  ödendi — bekleyen yok"*). Başlıklı ama gövdesiz bir tablo, boş bir kuyruktan
  daha kötü görünürdü.
- Test: `order_payment_test.php` 76 → **83 iddia**, kart kum havuzunda
  **gerçekten çizdiriliyor**. **Çapa kendi `summary` metnim:** `admin.php`'de
  **10 ayrı `<details>`** var ve konuma göre ölçmek başka bir sekmenin bloğunu
  ölçerdi (*"iddia satırı değil navigasyonu ölçüyordu"* dersinin aynısı).
  İki yön de düştü, her sabotajın **gerçekten uygulandığı `grep -c` ile ayrıca
  yazdırılarak**: katlama kapatılınca **6 kırmızı**, **HER** satır katlanınca
  **2** (kontrol grubu — açık iş görünür kalmalı).
- **Kendi ölçüm hatam, bu oturumda AYNI sınıfın İKİNCİ vakası:** *"her fatura
  sayfada tek kez"* iddiasını fatura **numarasıyla** yazdım ve kırmızı döndü —
  numara satır başına **zaten iki kez** basılıyor (bir `<td>`de, bir de
  Redraft'ın onay metninde *"Rewrite invoice INV-… IN PLACE"*), değişiklikten
  **önce de** öyleydi. Kod doğruydu, ölçü yanlıştı; iddia satırın kendi **form
  id**'sine daraltıldı — kopyalanmayı gerçekten ölçen şey o.

**KURAL 8 — Mesajlaşmada satıcı ürün identiyle görünür; mağaza adı yazılmaz**
(operatör kararı, 3 Eyl 2026: *"platformdaki mesajlaşmada her ürün için seller
ardından ident no ya da sku numarası koy, mağaza ismi yapma"*).

- Tek kaynak: `vestra_msg_seller_ident($listingId, $sellerUid)` (`inc/messages.php`).
  Sıra: ilanın **SKU**'su → ilan id'si → ilansız konuşmada satıcı başına sabit
  `S-XXXXXX` kodu (uid'den türer, uid'i sızdırmaz). Konuşma zaten
  (alıcı, satıcı, ilan) üçlüsü başına açıldığı için ürünün SKU'su alıcının ürün
  sayfasından **zaten bildiği** tutamak.
- **Yalnızca satıcı tarafı gizlenir.** Satıcı alıcıyı firma adıyla görmeye devam
  eder — tedarikçi kime sattığını bilmek zorunda; fatura zaten alıcı adına kesiliyor.
  `VESTRA Support` kendi adıyla kalır (sentetik hesap, gizlenecek dükkân yok).
- **Mektup da aynı şeyi yazar.** Panelde gizlenip bildirim e-postasında yazılan bir
  ad, gizlemenin kendisini bir satırda boşa çıkarır: alıcıya giden "yeni mesaj"
  mektubunun gönderen adı `Seller <ident>` ve **Reply-To boş** — eskiden satıcının
  gerçek adresi Reply-To olarak gidiyordu, yani sohbette engellenen (e-posta/IBAN/
  telefon) kanal mektubun başlığından açılıyordu.
- **Operatör bildirimleri gerçek adla kalır** (`💬 VESTRA message — …`,
  `📋 VESTRA — …`): panel operatörün, kimin kiminle konuştuğunu görmesi gerekiyor.
- Test: `tests/msg_seller_ident_test.php` (21 iddia). Her iki yönü de tutar —
  gizlenmesi gereken ad ve *görünmeye devam etmesi* gereken alıcı/Support.

**KURAL 8b — Bir BAĞLANTININ içindeki rakam dizisi telefon numarası değildir**
(operatör, 10 Eyl 2026: AlexaShop ↔ TYREX konuşmasında bir Amazon ürün linki
**PHONE** diye engellendi — *"buna istisna yap ve linki gönder"*).
- Amazon adresin sonuna `qid=1757497000` koyuyor (Unix zaman damgası); süzgeç
  9–15 haneli **her** diziyi numara sayıyordu, yani satıcı müşteriye ürün
  sayfası **gösteremiyordu**. Aynı link 10:07 ve 10:09'da iki kez engellendi.
- Çözüm `vestra_msg_mask_link_digits()`: telefon kontrolü **maskelenmiş** bir
  kopyaya bakıyor — bağlantının **yolu ve sorgu dizesi** atılıyor, **ana makine
  adı kalıyor** (numaranın kendisi alan adıysa yine yakalanır).
- **E-posta ve IBAN kontrolü HAM metne bakmaya devam ediyor:** linkin içine
  gömülü bir `mailto:` ya da IBAN hâlâ platform dışına çıkma girişimidir.
- **Ters yön bilerek korunuyor:** `wa.me/33612345678`, `t.me/+33…`,
  `api.whatsapp.com/send?phone=…` maskelenmiyor — yasaklanan şeyin ta kendisi;
  `tel:`/`sms:` zaten http bağlantısı değil, maskeye hiç girmiyor.
- Teşhis: `diag-messages.yml` → `blocked=true` — son 10 engellenen deneme: kim,
  hangi thread/ilan, hangi kural, metindeki **bağlantılar** (sorgu atılmış) ve
  *"bugünün süzgecinden geçer miydi"*. **Serbest metin BASILMIYOR**: engellenen
  metin zaten telefon/e-posta taşıyor olabilir ve bu günlük herkese açık.
- Test: `tests/msg_link_filter_test.php` (24 iddia, iki yön). Düzeltme geri
  alınınca **5 iddia kırmızıya** dönüyor.

**Mesaj başlığındaki `listing_id` her zaman İLAN DEĞİL — talep ref'i de olabilir**
(operatör, 17 Eyl 2026: *"bu ürünü offer vermis müsteri ancak ürün yok neden silindi
ve neydi"* — Mfitel Anas ↔ GARAGE LE PARIS, `RFBF89`).
- **ÖLÇÜLDÜ, hiçbir şey silinmemiş** (`diag-live` → `find_ref=RFBF89`, 2 yerde
  bulundu): `RFBF89` bir ilan değil bir **TALEP** — `requests.csv`'de duruyor
  (17 Eyl 10:19 · *"Lacoste nike ralph Laurent tommy"* · Hoodies & Sweatshirts ·
  **20 ad.** · hedef **€15** · France) ve GARAGE LE PARIS o talebe 18:07'de teklif
  vermiş (`request_offers.csv` · `RO5E11B9` · **€25/ad.** · **300 ad.**). İki kayıt
  da yerinde.
- **Yön de tersti:** operatör "müşteri offer vermiş" diye okudu; gerçekte **satıcı**,
  **alıcının** talebine teklif verdi. Ortada hiç ürün yoktu — talep panosu ilan
  bazlı değil.
- **Paneli yanıltan şey yapısal:** `request-offer.php` thread'i
  `vestra_msg_post_system($buyer, $seller, $ref, …)` ile **talep ref'iyle** açıyor,
  yani `listing_id` alanında bir ilan id'si yok. Satır `vestra_find()` başarısız
  olunca ham id'yi basıp onu **`/product?id=RFBF89`**'e bağlıyordu — var olamayacak
  bir sayfa. *Yanlış sayfaya yollayan bir yönerge, hiç yönergeden pahalıdır çünkü
  uygulanır* (KURAL 5j'nin aynı dersi).
- Üç durum, üç etiket: gerçek ilan → adı + ürün sayfası (**değişmedi**); talep ref'i
  → talebin **kendi başlığı** + Requests sekmesi + `sourcing request <ref>` rozeti;
  çözülemeyen → **düz metin, bağlantı YOK**.
- **Son dal bilerek "silindi" DEMİYOR.** `vestra_find()` → `vestra_products(true)` →
  `vestra_live_listings()`, yani yalnız **`approved` + satıcısı askıda olmayan**
  ilanları görüyor. Oraya gerçekten silinmiş bir ilan da, yalnızca gizli duran bir
  ilan da düşüyor — 13 Eylül'de Marca Online askıya alınınca **146 ilan** tam böyle
  "yok" olmuştu. *"Çözülmüyor" ile "silinmiş" aynı şey değil.*
- **Konuşmanın İÇİ zaten doğruydu:** sistem kartı (`vestra_msg_system_html`,
  `kind=request_offer`) talebin başlığını, birim fiyatı ve adedi basıyor. Yanıltan
  tek şey **başlık satırıydı** — yani operatörün listede gördüğü yer.
- Test: `tests/msg_thread_label_test.php` (13 iddia). Kum havuzunda `admin.php`'yi
  **gerçekten çizdiriyor**: ölçülmesi gereken şey üretilen HTML ve `$requests`'in o
  satırda kapsamda oluşu — `php -l` ikisini de göremez (bu depoda aynı gün iki
  çalışma-zamanı hatası `php -l`'den geçmişti). **İki yön de** tutuluyor: gerçek ilan
  HÂLÂ ürün sayfasına bağlanmalı. Düşebildiği doğrulandı, her sabotajın gerçekten
  uygulandığı ayrıca yazdırılarak: eski davranış geri konunca **4 kırmızı**, talep
  araması silinince **2**.
- **Kendi ölçüm hatam:** *"Requests sekmesine bağlanıyor"* iddiasını önce sayfanın
  **tamamında** aradım ve sabotaj altında **yeşil kaldı** — `/admin?tab=requests`
  **sol menüde de** geçiyor, yani iddia satırı değil navigasyonu ölçüyordu. İddialar
  satırın kendi bloğuna daraltıldı. *mango/zara dersinin testin kendi içindeki hâli;
  `class="msgtick` önekinin `msgtickdefs`'i yakalamasıyla aynı sınıf.*
- **Operatör kararı bekliyor:** teklif talebin **15 katı adet** ve **hedefin %67
  üstünde** (300 ad. @ €25 ↔ 20 ad. hedef €15). Alıcı yanıt vermemiş görünüyor;
  satıcının 769 karakterlik mesajı `Admin ▸ Request Offers` (`RO5E11B9`) ve
  konuşmanın kendisinde duruyor.

**Excel'den çıkarılmış ürün fotoğrafı: MANZARA tuval + boş gutter + kesik giysi;
düzeltme YENİ PİKSEL DEĞİL, aynı SKU'nun temiz karesidir** (operatör, 17 Eyl 2026:
*"Lacoste Crew Neck Sweatshirt fotolarinda sorun var excelden iyi cikmamis düzelt
cerceveye otursun"*).
- **Katalogda İKİ Lacoste crew-neck sweatshirt var ve doğrusu ölçülerek seçildi:**
  `lac-crew-sweatshirt` (*"Fleece Crew Neck Sweatshirt"*, SH9608, €64,71 / MOQ 56)
  ve `lgp-lacoste-crew-sweatshirt` (*"Crew Neck Sweatshirt"*, GARAGE LE PARIS,
  LAC-SH9608-00, €60 / MOQ 8). Operatörün kastettiği **ikincisi** ve bu **üç
  bağımsız eşleşmeyle** doğrulandı: adı birebir o, fotoğrafları gerçekten Excel'den
  çıkma, ve gerçekten çerçeveye oturmuyor. Birincisininki zaten temiz.
- **Kusurun imzası ölçüldü, tahmin edilmedi.** Eski on kare (`/uploads/lac-sweat/
  lacoste-sweat-*.png`) **onu da 892×750**, yani **AYNI tuvalde** ve **MANZARA**;
  hepsinde **%15–22 boş SOL gutter**; içerik sınır kutusu 9'unda üst/sağ/alt
  kenara **dayanıyor** — giysinin manşeti/eteği **kesik**. Aynı tuval + aynı gutter
  + üç kenardan kırpık = bir hücreden çıkarmanın imzası. Vitrin çerçeveleri
  **PORTRE** (`.sthumb` 3/4, `.gal-main` 4/5, ikisi de `object-fit:contain`), yani
  manzara bir kare mektup kutusu gibi oturuyor: giysi küçük, ortalı değil, sağa
  itilmiş. *"Çerçeveye oturmuyor" şikâyeti önce bir ORAN sorusudur.*
- **Yeni piksel üretilmedi.** Aynı SKU'nun temiz pack-shot'ları **zaten sunucuda**
  (`/uploads/lacoste/crew-sweatshirt/`) ve kardeş ilan onları kullanıyor. Kırpmak
  ya da beyaz tuvale yapıştırmak kesik giysiyi düzeltmezdi — kesilen piksel yok.
- **Sunucudaki kareler GÖZLE doğrulandı**, "diskte" yazısına güvenilmedi:
  `diag-live` → `wetransfer_probe=sheet:public_html/uploads/lacoste/crew-sweatshirt|
  perfile|cell=300|spaced` kontakt sayfası çekildi — onu da beyaz zeminde, portre,
  giysi tam ve ortalı. *`black.avif` hücresi boş çıktı: **GD avif çözemiyor**, dosya
  eksik değil (`img_list` "diskte" diyor ve kardeş ilan onu canlıda basıyor). Aracın
  kendi sınırını kusur sanma.*
- **Renk sırası ilanın kendi sırası, KAPAK yine Navy.** İstenen fotoğrafların
  düzelmesiydi, kapağın değişmesi değil (Balenciaga 612966 dersi).
- **AYNI OLGU İKİ YERDE YAZILI ve ikisi de düzeltildi.**
  `Admin ▸ Listings ▸ Sync Les Garage Paris` (`sync_lesgarage`) mevcut ilanı
  `inc/lesgarage_polos_seed.json`'dan **geri yazıyor** ve `$refreshable` listesinde
  **`images` var** — yani yalnız `listings.json`'u düzeltseydim, operatör o düğmeye
  bastığı gün Excel kareleri **sessizce geri gelirdi**. `offers` alanının
  `seller.php` tarafından her kaydetmede geri yazılmasıyla birebir aynı sınıf.
  *Bir alanı düzeltirken "bunu başka kim YAZIYOR?" diye sor — "başka nerede yazılı"
  sorusunun yazma tarafı.*
- Tohum dosyasına **açıklama anahtarı konmadı**: `sync_lesgarage` YENİ ürün yolunda
  kaydın **tamamını** `listings.json`'a yazıyor, yani `_why` gibi bir anahtar canlı
  ilan kaydına sızardı (`product-fixes/*.json`'da güvenli, çünkü `set_product.php`
  yalnız `$ALLOWED`'ı kopyalıyor). Gerekçe burada duruyor.
- **Parti dosyası yine canlı kaydın aynası değildi:** `product-batches/
  lacoste-9-new-models.json` **üçüncü** bir yol yazıyor (`/uploads/lacoste/
  crew-sweatshirt/…` — o kardeş ilanın), repoda ise **dördüncü** bir kopya duruyor
  (`vestra/uploads/lacoste/sh9608-*`, 10 kare; siyah ve lacivert orada **gri
  zeminli ve altı kesik**). Hedef canlıdan ölçülmeseydi yanlış kümeyi düzeltecektim.
- Uygulama: `product-fixes/lgp-crew-sweatshirt-photos.json` + `set-product.yml`.
  `set_product.php` her yolun **sunucuda var olduğunu** doğruluyor
  (`is_file($home.'/public_html'.$img)`), yani doğrulama yazma yolunun içinde.
  Kuru koşu → uygula → **sunucudan geri okundu** (`inspect-products` → `img_list`):
  10 kare, hepsi `diskte`, kapak `navy.jpg`.
- **Operatöre yazılan üç açık nokta — ÜÇÜ DE AYNI GÜN KAPANDI**, çünkü üçü de
  aynı fazlalık ilanın kusuruydu ve o ilan kaldırıldı (aşağıdaki maddeye bak):
  (1) SH9608 katalogda iki fiyatla duruyordu (€55 / min 56 ve €45 / min 8);
  (2) `lgp-lacoste-crew-sweatshirt`'in kategorisi `Sweatshirts` ve bu ad
  `vestra_all_cats()`'te **YOK** — satıcı ilanı açıp kaydettiği an kategori
  **"Other" ile eziliyor** ve ad `t()`'den geçmediği için 9 dilde ham İngilizce
  basılıyordu; (3) `min_colors` alanı yoktu, yani alıcı 10 rengi **görüyor ama
  seçemiyordu** (Fred Perry M7535'in aynı vakası). *Üçü ayrı ayrı düzeltilmedi:
  kusurların kendisi, ilanın niye fazlalık olduğunun kanıtı oldu.*

**Aynı ürün iki kez: hangisinin kalkacağını `added_at` söyledi, karar BEŞ
bağımsız işaretle doğrulandı** (operatör, 17 Eyl 2026: *"ayni ürün sitede iki
defa olmaz sonradan girileni kaldir"*).
- Aynı Lacoste SH9608 crew sweatshirt katalogda **iki ilandı** ve ikisi de aynı
  fotoğrafları kullanıyordu (bir gün önce ben eşitlemiştim), yani ekrandan
  ayırt edilemiyorlardı. **Ölçüldü, hatırlanmadı:** `inspect-products` ilan
  başına `added_at` **basmıyordu** ve "sonradan girilen" sorusunun cevabı tam
  o alanda; eklendi. Sonuç: `lgp-lacoste-crew-sweatshirt` **2026-09-12**,
  `lac-crew-sweatshirt` **2026-08-02** — 41 gün arayla, belirsizlik yok.
- **Tek bir alana dayanmadım.** `added_at` bir zaman damgası ve bozulabilir;
  beş bağımsız işaret daha aynı yönü gösterdi: kalkan ilan **ölü** bir satıcı
  kabuğunda (`8fa9d40d…` "Les Garage Paris" — adres/VAT/sicil/banka/Stripe yok;
  ayakta kalan ilan canlı **GARAGE LE PARIS** hesabında), kategorisi
  taksonomide yok, `min_colors` yok (renk seçilemiyor), teklif kutusu kapalı,
  ve operatörün aynı oturumda verdiği **56/104** kademeleri ayakta kalanın
  MOQ'suna oturuyor. *"Sonradan girilen" tek ölçütken, altı ölçüt bir arada
  karar oldu.*
- **SİLİNMEDİ, `status='rejected'` YAPILDI** — `vestra_invoice_delete()`'in
  "silmiyor, arşivliyor" kararının ilan hâli. `vestra_live_listings()` yalnız
  `approved` döndürüyor, yani vitrin, fiyat listeleri, sitemap, kampanya, API
  ve `vestra_find()` (ürün sayfası) **hepsinden** düşüyor; kayıt diskte
  `✗ Rejected` rozetiyle duruyor ve tek tıkla geri gelir. Panelin **Delete**
  düğmesi `vestra_save_listings()` çağırıyor ve o **yedek almıyor**; bu yol
  zaman damgalı yedek alıyor ve geri okuyor.
- **`pending` BİLEREK seçilmedi:** o durum ilanı panelin *"⚠️ Listings to
  approve"* sayacına sokar, yani **kaldırdığımız ilan her sabah yapılacak iş
  gibi görünürdü** — KURAL 2c'nin ("her gün '0 bekleyen' yazan uyarı
  okunmamayı öğretir") ters yönü. `set_product.php` `rejected` yazamıyordu;
  eklendi ve bu bir **genişletme değil**: panelin kendi reddet düğmesi
  (`admin.php:96`) zaten tam bu değeri yazıyor, betik yalnızca yazamıyordu.
  `suspended` bilerek dışarıda — o, satıcı askısının ilan tarafındaki
  karşılığı, bir ürün kararının değil.
- **Tohum dosyası da temizlendi ve bu şarttı:** kayıt
  `vestra/inc/lesgarage_polos_seed.json`'da duruyordu ve
  `Admin ▸ Listings ▸ Sync Les Garage Paris` **yeni ürün yolundan** onu
  `status='approved'` ile geri yazardı. Bir gün önce fotoğraf düzeltmesinde
  öğrenilen *"bunu başka kim YAZIYOR?"* sorusunun ikinci uygulaması.
- **Bağlantı taraması önce yapıldı:** `diag-live` → `find_ref=crew-sweatshirt`
  tek eşleşme verdi (`listings.json`) — hiçbir sipariş, teklif, `order_statuses`
  ya da `offer_responses` kaydı bu ilana bağlı değil.
- **Fiyat AYAKTA KALAN ilana yazıldı. Son hâli: `tiers 56+ → €39,90 /
  104+ → €35,00`, `sale_list 44,00`.** İkinci kademe **üç kez** söylendi ve
  ikisi de canlıya indi: (1) *"56 ad. ten itibaren 39,90 Eur, 104 ad. ten
  itibaren 35,00 eur normal fiyatı da 44,00 eur yap"* → (2) dakikalar sonra
  *"104 ad. Ten itibaren 36 eur yap"* — **yazıldı** (run `35294927217`) →
  (3) *"35 eur yap 104 tane den itibaren"* — ilk rakama dönüş, **yazıldı**
  (run `35295293231`). Önceki hâli: list 64,71 / tek kademe 56+ → 55,00.
  *Ara rakam bir dakika içinde geri alınmadı; kayıtta yaklaşık 13 dakika
  €36,00 durdu. Sipariş/teklif oluşmadı, yani kimseye yansımadı — ama
  "geri alındı" ile "hiç yazılmadı" aynı şey değil ve not ikincisini
  söylüyordu, düzeltildi.*
- **`price` değil `sale_list`, ve fark ilanı bozacak kadar büyük:** `price`
  alanı **bütün kademeleri** 44'e düzleştirir (merdiven yok olurdu);
  `sale_list` yalnız üstü çizili "was" fiyatını yazar. Mevcut 64,71 zaten aynı
  alandan gelmişti.
- **Çelişki susarak çözülmedi:** operatörün yapıştırdığı sayfa metni
  (*"Single-size cartons of 8 … SH9608-00"*) **kalkan** ilanındı, verdiği
  rakamlar ise **kalanın** yapısı. İkisi aynı üründü ve rakamlar mekanik olarak
  tek bir ilana oturuyordu (kalkanın MOQ'su 8; "56'dan itibaren" onun ilk
  kademesi olamaz).
- **Doğrulama alanın değerine değil sepetin TAHSİL ETTİĞİNE bakıyor**
  (`price_audit`): katalog **895 → 894** (bir ilan canlı listeden düştü, yazma
  mesajından bağımsız ikinci kanıt), *alıcı aleyhine* tek satır var ve o eski
  demo tohumu `lac-pique-polo`, bu sweatshirt değil. `raw_scan`: `approved=11
  rejected=1`, ölü hesap hâlâ tam **1** ilan taşıyor, `kayıp/bozuk foto 0`.
- **`raw_scan` diğer kipleri eziyor:** aynı koşuda `price_audit` ile birlikte
  verildiğinde yalnız ham tarama basıldı. İki ölçüm iki koşu istedi — sondayı
  tek koşuda yığmak, sorulan sorulardan birini sessizce cevapsız bırakıyor.

**Lot 8'li → 10'lu (1-3-3-2-1): operatörün İSTEMEDİĞİ ama ZORUNLU olan ikinci
değişiklik ASGARİ ALIM** (operatör, 18 Eyl 2026: *"sitede kontrol et fiyatlar
dogru gözüküyor mu ayrıca bir Lot u 10 ad. Yap . 1 3 3 2 1"* —
`lac-crew-sweatshirt`, aynı gün fiyatı yazılan ilan).
- **Fiyat kontrolü SUNUCUDAN, ve sebebi kayıtlı bir tuzak:** bu ortamdan canlı
  siteye çıkılamıyor, üstelik ürün sayfasının fiyat bloğu **fiyat kapısının
  arkasında** — girişsiz çekmek kapıyı ölçerdi, fiyatı değil (KURAL 4b/21d'nin
  aynı tuzağı, bu depoda iki kez yaşandı). Geçerli ölçüm sunucunun kendi kaydı
  (`inspect-products`, `brand=Lacoste` + `price_list`): `fiyat(list)=44 EUR`,
  `tiers 56+ → €39,90 | 104+ → €35`, toptan liste satırı
  `SH9608 · 56 pc · 44.00 · 35.00 · satista`. Üç rakam da yerinde ve ilan
  **tek** — 12 satırlık Lacoste dökümünde SH9608 bir kez geçiyor, yani dünkü
  tekrar kaldırma da geri okundu.
- **Operatör MOQ'dan söz etmedi; 10'luk karton onu ZORUNLU değiştiriyor.**
  `set_product.php` iki yerde **hata** veriyor: `moq % size_step !== 0`
  (56 % 10 = 6) ve `tiers[0].min !== moq`. Gerekçe ilanın kendisinde: sepet
  miktarı adımın katına **yukarı** yuvarlıyor, yani 10'luk kartonda "min 56"
  sayfada 56, kasada 60 demek — ilan edilen minimum hiç alınamaz. 104 de
  oturmuyordu: alıcı €35'e ancak **110**'da ulaşırdı.
- **50/100 TAHMİN DEĞİL, aynı satıcının kendi 10'luk standardı** — sunucudan
  ölçüldü (`inspect-products`, `brand=Fred Perry`), CLAUDE.md'den
  hatırlanmadı. Aynı hesabın (`7ab30f26afedd840` / GARAGE LE PARIS) bugün iki
  adet 10'luk ilanı var ve ikisi de aynı tabanı kullanıyor:
  `fp-m3600-polo` (adım 10, moq 50, kademe 50/100/200) ve `fp-m7535-sweat`
  (adım 10, moq 50, **birebir aynı 1-3-3-2-1 serisi**, `min_colors=4`).
  İkincisi yapısal olarak kardeş: aynı satıcı, aynı kategori
  (*Hoodies & Sweatshirts*), aynı seri, aynı renk asgarisi. 60/110 da tam kat
  olurdu ama hiçbir kardeşi öyle değil ve 104'e daha uzak. Bu, bu dosyada
  kayıtlı M3600 kararının (*"en az alimlar da ayni matiga göre ciksin"*,
  56→50, 96→100, 192→200) birebir uygulanması.
- **Fiyatlar değişmedi** (39,90 / 35,00) ve `sale_list 44,00` da. Değişen
  yalnız basamakların **yeri**: 56→50, 104→100 — ikisi de alıcının **lehine**
  (aynı fiyata dört-altı parça önce ulaşıyor), yani `price_audit`'in *"alıcı
  aleyhine"* sınıfına girmiyor.
- **`desc` AYNI yazmada değişti ve SONDA BURADA YARDIM EDEMEZDİ.** Karton
  adedi ve asgari alım bu ilanda **iki** yere yazılı (`sizes` + `desc`), ama
  `fit_scan`'in çelişki sayacı `desc`'te `S×1 · M×2 …` biçimli bir **seri**
  arıyor; buradaki metin *"cartons of 8 … Minimum order 56 pc"* diyor, yani
  `$runOf('')` boş dönüyor ve ilan **hiçbir zaman** çelişki listesine girmez.
  Balenciaga (9 Eyl) ve Burberry 8049455 (17 Eyl) aynı dersi iki kez verdi;
  ikisinde de sonda görebiliyordu, burada göremezdi. *Aynı olgunun iki yerde
  yazılı olması, onu gösteren bir sondanın VAR olduğu anlamına gelmiyor.*
- **Metin şablondan yeniden yazılmadı:** kuru koşunun bastığı **canlı**
  dizgeden kuruldu ve yalnız iki rakam değişti. Eski metni doğru tahmin
  etmiştim ama kanıt tahmin değil **kuru koşu** oldu (Burberry'nin aynı gün
  verdiği ders).
- **Üçüncü bir yer var mı diye ARANDI:** bu ilana bugüne kadar yazan her dosya
  tarandı (ithalat partisi + iki fiyat düzeltmesi + foto/durum düzeltmeleri) —
  `specs` bu ilana **hiç yazılmamış**, yani olgu gerçekten iki alanda.
- **Paket eki biçimi korundu (`10/pack`), Fred Perry'nin *"Cartons of 10 · … ·
  min 50 pc"* biçimi DEĞİL.** İki sebep: `vestra_sizes_label()` yalnız
  `<rakam>/pack` kalıbını çeviriyor (öteki biçim 9 dilde ham İngilizce
  kalırdı), ve 11 Lacoste kardeşinin hepsi `… · N/pack` yazıyor. M3600
  partisinde de her ilan kendi son ekini korumuştu — istenen yalnızca
  dağılımdı.
- **`min_colors` 4 kaldı ve ulaşılabilir:** 50/10 = 5 karton ≥ 4 renk (M3600'de
  yapılan aynı hesap). Beden seçici kapalı kalmaya devam ediyor ve doğrusu bu:
  `vestra_sizes_selectable()` açık dağılım ya da paket eki gören ilanda seçim
  açmıyor — karışım ilanın kendisinde yazılı.
- **Geri okuma sunucudan** (yazma mesajından değil), `KAYDEDILDI — 5 alan`
  sonrası: `moq=50 pc`, `size_step=10`,
  `sizes=S×1 · M×3 · L×3 · XL×2 · XXL×1 · 10/pack`,
  `tiers 50+ → €39,9 | 100+ → €35`, `fiyat(list)=44 EUR`, `min_colors=4`,
  beden seçici **YOK**. Doğrulama alanın değerine değil **sepetin TAHSİL
  ETTİĞİNE** bakıyor (`price_audit`): 894 üründe *alıcı aleyhine* tek satır var
  ve o hâlâ eski demo tohumu `lac-pique-polo`, bu sweatshirt değil. Yedek:
  `listings.json.bak-20260918-014550`.

**Fred Perry M7535: üç kademe 10'dan başlıyor — ve RENK ASGARİSİ bunu
ölçülerek imkânsız kılıyordu** (operatör, 18 Eyl 2026: *"Fred Perry Crew Neck
Sweatshirt — M7535 10- 49 ad. 39,90 eur , 50-99 ad 36,00 eur 100 ad. ten
itibaren 33,00 eur"*). Önceki hâli: `moq 50`, **tek** kademe `50+ → €39,90`,
`min_colors 4`.
- **İKİ alan operatörün cümlesinde HİÇ GEÇMİYOR ama değişmek zorundaydı**, ve
  ikincisi bu işin asıl bulgusu:
  1. **`moq` 50 → 10.** `set_product.php` `tiers[0].min !== moq` durumunda
     **hata** veriyor; ondan da önemlisi, moq 50 kalsaydı *"10-49 ad."* bandı
     **tamamen ulaşılamaz** olurdu — `order.php:75` 50'nin altını reddediyor.
     *Bir bandı yazmak ile o bandı SATILABİLİR yapmak ayrı şeyler.*
  2. **`min_colors` 4 → 1.** Bu bir **renk-başına-adet** ilanı
     (`vestra_is_colorqty_listing()` = `colors` **ve** `min_colors` **ve**
     `size_step>1` — üçü de var). `vestra_parse_colorqty()` her rengin adedini
     `size_step`'in (10) katına yuvarlıyor ve sıfırı hiç saymıyor;
     `order.php:75` ise `count(colors) < min_colors || qty < moq` ise
     **reddediyor**. Yani `min_colors=4` iken **en küçük geçerli sipariş 4 renk
     × 10 = 40 ADET**. `moq`'yu 10 yapıp renk asgarisini 4 bırakmak, sayfada
     *"min 10 pc"* yazan ama kasada **40** isteyen bir ilan bırakırdı — bu
     deponun *"ilan edilen minimum hiç alınamaz"* kusurunun ta kendisi.
     **10 adet = 1 karton = 1 renk**, başka türlü olamaz.
- **Bu, M3600'de 10 Eyl'de verilen kararın TERS YÖNÜ ve çelişki değil.** Orada
  `min_colors=4` korunabilsin diye `moq` 20'den **50'ye ÇIKARILMIŞTI**
  (50/10 = 5 karton ≥ 4 renk). Burada operatörün kendi merdiveni tabanı 10'a
  indiriyor, yani aynı denklemin öbür ucundan çözülüyor. **İkisi aynı anda
  olamaz** ve operatörün yazdığı şey rakam bandıydı, renk sayısı değil.
- **`min_colors = 0` YAZILAMAZ, iki ayrı sebeple:** `set_product.php` satır 178
  `< 1` olanı reddediyor, ve yazılabilseydi `vestra_is_colorqty_listing()`
  false döner, **renk seçici tamamen kaybolurdu** (`product.php:74`). Geri
  okuma `renk secici=ACIK` diyor — 5 renk hâlâ seçilebiliyor, yalnız *zorunlu
  en az* 1.
- **`desc` KURU KOŞUYLA ölçüldü, varsayılmadı.** Bu ilanda asgari adet
  `desc`'te geçiyor olabilirdi (Balenciaga 9 Eyl / Burberry 17 Eyl dersi) ve
  sonda bunu gösteremezdi: `fit_scan`'in çelişki sayacı `desc`'te **beden
  serisi** arıyor, burada seri `sizes` ile zaten aynı. Çare: ithalat kaydındaki
  metin kuru koşuya **prob olarak** verildi — aynıysa `set_product.php`
  satırı hiç basmaz (`old === new → continue`), farklıysa planda **canlı metin**
  görünürdü. Plan `degisecek alan: 4` dedi ve **`desc` hiç görünmedi**: yani
  canlı açıklama birebir o metin ve içinde asgari adet **yok** — değiştirilecek
  bir şey de yok. *Parti dosyası canlı kaydın aynası değil (aynı gün Lacoste'ta
  ölçüldü), o yüzden "aynıdır" varsayılmadı; ölçüldü.*
- **`sizes` kuyruğu aynı olguyu taşıyordu** (`… · min 50 pc (≥4 colours)`) ve
  aynı yazmada düzeltildi → `… · min 10 pc (1 carton)`. `(≥4 colours)` da
  düştü çünkü artık doğru değil. **Karton eki ve açık dağılım AYNEN korundu**,
  yani beden seçici kapalı kalıyor (`vestra_sizes_selectable`) — karışım ilanın
  kendisinde yazılı.
- **`list` yazılmadı:** zaten 39,90 ve yeni ilk kademe de 39,90. `price` yazmak
  **üç kademeyi birden** aynı rakama düzleştirirdi (merdiven yok olurdu).
- **Üç basamak da 10'un tam katı** (10 / 50 / 100), yani alıcı her basamağa tam
  ulaşıyor — oturmayan bir basamakta fiyat ancak bir sonraki kartonda devreye
  girerdi (uyarı, hata değil).
- **Geri okuma sunucudan:** `sizes=Cartons of 10 · S×1 · M×3 · L×3 · XL×2 ·
  XXL×1 · min 10 pc (1 carton)`, `tiers 10+ → €39,9 | 50+ → €36 | 100+ → €33`,
  `min_colors=1`, `renk secici=ACIK`, `beden secici=YOK`. `price_audit`:
  894 üründe *alıcı aleyhine* tek satır ve o yine `lac-pique-polo` (eski demo
  tohumu) — yani sepet 10 adette gerçekten €39,90 alıyor. Yedek
  `listings.json.bak-20260918-020119`.
- **AYNI DALDA İKİNCİ BİR OTURUM ÇALIŞIYOR ve AYNI DENKLEMİ TERS YÖNDEN
  ÇÖZMÜŞ.** Kuru koşum, benim `460df9c3`'ümün üstüne başka bir oturumun ittiği
  `ae9aa73d` (*"Lacoste Fleece Hoodie lot 10 … asgari 40"*) + `3139401d`
  merge'i ile koştu. Ölçüldü (`brand=Lacoste`, `cat=Hoodies & Sweatshirts`):
  `lac-fleece-hoodie` artık `size_step=10`, **`min_colors=4` KORUNMUŞ** ve
  `moq=40` (= 4 × 10), kademeler `40+ → €49,9 | 50+ → €45 | 100+ → €42`.
  Yani **aynı gün, aynı satıcı, aynı 10'luk karton, iki ilan, iki farklı
  çözüm**: hoodie renk kuralını koruyup tabanı 4 kartona çıkardı, M7535 ise
  operatörün yazdığı *"10-49"* bandını gerçek kılmak için renk asgarisini 1'e
  indirdi. **İkisi de tutarlı**, ama operatörün önünde artık somut bir emsal
  var — M7535'i hoodie kalıbına çevirmek isterse tek koşu (`min_colors 4`,
  `moq 40`, ilk basamak `40+`).
- **Yarış kontrolü YAPILDI:** `listings.json` **oku-değiştir-yaz** ve iki oturum
  dakikalar arayla yazdı. İkisinin de yerinde durduğu ölçüldü — benim
  `lac-crew-sweatshirt` (moq 50, `10/pack`, `50+/100+`) ve `fp-m7535-sweat`
  duruyor, onların `lac-fleece-hoodie`'si duruyor, katalog **894**. Çakışsalardı
  biri **sessizce** kaybolurdu. *Push'tan sonra dalın tepesine bak —
  `git log origin/<dal>` bir dakikada başkasının commit'ini gösterebilir.*
- **Yeni bir denetim sorusu doğdu:** `min_colors × size_step > moq` olan her
  ilan, **ilan ettiği minimumu satamaz**. Görünen altı ilanda sorun yok
  (hoodie 40/40, high-neck 32/56, zip 32/56, crew 40/50, M7535 10/10,
  M3600 20/50) ama **katalog geneli ölçülmedi**; `moq_scan`'e bu karşılaştırmayı
  eklemek küçük bir iş ve sessiz bir kusur sınıfını kapatır.
- **Operatör kararı bekleyen iki şey, ikisi de bu değişikliğin yan etkisi:**
  1. **Kardeş ilanlar artık ayrışıyor:** M7535 min **10**, ama M3600 polo min
     **50** (≥2 renk) ve Lacoste crew sweatshirt min **50** (≥4 renk) — üçü de
     aynı satıcı, aynı 10'luk karton. Operatör yalnız M7535'i adlandırdı, o
     yüzden ötekilere **dokunulmadı**.
  2. **Gönderilmiş mektuplar artık ESKİ rakamı taşıyor.** 16 Eylül'ün 118 ve
     17 Eylül'ün 7 Angebot mektubu M7535 için *"min 50 pc, ≥4 renk, €39,90
     tek fiyat"* yazdı — rakamlar canlı kayıttan basıldığı için geri alınamaz.
     Yeni şartlar müşterinin **lehine** (min 10, 100'den itibaren €33); istenirse
     ikinci bir mektup gönderilebilir. **Kendiliğinden gönderilmedi** (KURAL 18).

**KURAL 14 — Talep panosu ("Anfragen"): ÖRNEK ile GERÇEK talep karışmaz**
(operatör, 8 Eyl 2026: *"sitenin anfragen bölümüne yeni anfragen lar ekle"*).
- `requests.php` iki liste basıyor: `requests.csv`'den gelen **gerçek** alıcı
  talepleri ve koddaki `$exampleReqs`. Örnekler bilerek var — sıfır talepli bir
  pano, arz yokluğu gibi okunuyor — ama üç şartı dosyanın kendi yorumunda yazılı:
  her kartta **"Example" rozeti**, sayaçlara **girmez**, ve **teklif düğmesi
  YOK**. Sonuncusu asıl olan: teklif veren satıcı firmasını, e-postasını, birim
  fiyatını ve teslim şartlarını `request_offers.csv`'ye yazıyor; uydurma bir
  alıcıda bunların ulaşacağı hiçbir adres yok.
- **Uydurma talep `requests.csv`'ye YAZILMAZ.** Dosyanın kendi notu zaten
  "no demo seeds — the board only shows genuine Anzeigen" diyor. Yazılsaydı
  "açık talep" sayacına girer ve satıcıları var olmayan bir alıcıya fiyat
  açıklamaya davet ederdi.
- 8 Eyl 2026'da **7 örnek eklendi** (8 → 15): iç giyim, çorap, gömlek, bot,
  sandalet, mont, eşofman takımı. Hepsi **katalogda gerçekten bulunan**
  taraflardan seçildi — tedarik edemeyeceğimiz bir şey için "iyi talep örneği"
  göstermek okuyucuyu boşuna yazdırır.
- **Kategori `vestra_all_cats()` sözlüğünden olmak zorunda.** Karta
  `t($x['cat'])` ile basılıyor; sözlükte olmayan bir ad çevrilemez, ham dizge
  çıkar ve pano var olmayan bir kategori gösterir (KURAL 9'un pano hâli).
- Test: `tests/requests_board_test.php` (15 iddia). Kategori iddiasının
  **gerçekten düştüğü** uydurma bir kategori enjekte edilerek doğrulandı — bu
  depoda hiç düşemeyen bir iddia zaten bir kez çıkmıştı (KURAL 5j).

**KURAL 15 — "Düğme yok" önce ÇİZİM sorusudur: sayfa sonuna kadar basıldı mı?**
(operatör, 7 Eyl 2026: *"fatura kesemyorum bu siparise button yok düzelt ve
fatura kes"* — VES-0A2571C2 / GARAGE LE PARIS.)

- Kapılar açıktı. `vestra_order_invoice_payloads()` dilim veriyordu, escrow notu
  yoktu, sipariş kesilmemişti — kapıları ölçen bir sonda hepsini yeşil buldu ve
  "üretemedim" diye rapor edildi. **Yanlış katman ölçülmüştü.** Canlı hata
  günlüğü siparişin geldiği dakikalarda dört kere şunu yazıyordu:
  `PHP Fatal error: Call to undefined function vestra_order_fx() in admin.php`.
- `inc/fx_orders.php` admin.php'nin **önsözünde değildi**; yalnızca (a) fx
  backfill POST'unda, (b) `orders_usd` indirmesinde, (c) **SİPARİŞLER** sekmesinde
  yükleniyordu. **FATURALAR** sekmesi aynı fonksiyonu çağırıyor ama dosyayı hiç
  yüklemiyordu. Tek kurtaran yol `vestra_order_invoice_payloads()` içindeki
  require ve o da **sadece** siparişe farklı bir fatura para birimi seçilmişse
  çalışıyor (`if ($wantCur !== '' && $wantCur !== $orderCur)`).
- Bu yüzden hata **veriye bağlı**, rastgele değil: USD'ye çevrilmiş sipariş
  (VES-6B53D265) sayfayı ayakta tutuyor, **sade EUR sipariş** (VES-0A2571C2)
  öldürüyordu. Ölen sayfa `✓ Approve & issue` düğmesinden **bir form önce**
  kesiliyor — düğme "kaybolmuş" değildi, o HTML hiç gönderilmemişti. Listedeki
  ilk sade sipariş, altındaki bütün siparişlerin düğmesini de götürüyordu.
- **Ders:** bir kontrol görünmüyorsa ilk ölçüm o kontrolün *koşulu* değil,
  sayfanın **sonuna kadar çizilip çizilmediğidir** — ve canlı hata günlüğü
  bakılacak ilk yerdir, kaynak okumak değil. KURAL 3'ün çizim hâli: sonda neyi
  ölçtüğünü söylemeliydi ("kapılar açık" ≠ "düğme basıldı").
- **Çözüm eklemedir, taşıma değil:** `fx_orders.php` önsöze girdi, mevcut üç
  require yerinde kaldı. Dosya yüklenirken ağ kullanmaz, yalnız iki require +
  iki sabit + fonksiyon tanımlar — bu da test ediliyor, yoksa önsöze konan bir
  dosya her panel açılışında ECB'ye gidebilirdi.
- Test: `tests/admin_fx_load_test.php` (20 iddia). Önsöz **kaynaktan token'la**
  okunur (elle yazılan liste admin.php değişince eskir; yorum satırına göre
  eleyen ilk sürüm, önsöze konan dosyanın üstüne yorum yazılınca onu görmedi ve
  yeşil kaldı — bir kez oldu, tokenla düzeltildi). Fonksiyonların tanımlı olduğu
  **ayrı bir PHP sürecinde** ölçülür; başka bir testin yüklediği dosya miras
  alınırsa ölçüm yalan söyler. Düzeltme geri alınarak iddianın gerçekten
  düştüğü doğrulandı (3 kırmızı).

**KURAL 16 — Toptan erişim aboneliği: %20 zammı KALDIRAN şey; iki fiyat da
tutulur** (operatör, 8 Eyl 2026: *"tıklandığında aylık ödeme fonksiyonu da
olsun... 199,90 eur olacak fiyatı, eğer bu fiyat ödenirse toptan fiyatına satın
alınabilir"* + *"yoksa tekli dropshipping fiyatı yüzde 20 eklenecek"*).

- **Zam kalkmadı, abonesizin fiyatı oldu.** `VESTRA_DROPSHIP_MARKUP` yerinde
  duruyor; abonelik onu kaldıran şey. Testin **iki yönü de** tutması şart:
  zam kaybolursa abonelik hiçbir şey satmıyor, hep uygulanırsa abonelik parayı
  boşuna alıyor olurdu (`dropship_plan_test.php`; zam sıfırlanınca 5, abonelik
  yok sayılınca 2 iddia kırmızıya döner).
- **Fiyat tek sabit:** `VESTRA_DROPSHIP_PLAN_PRICE = 199.90`. Stripe panelinde
  hazır bir Price'a bağlamak, rakamı sunucudan okunamayan ikinci bir yere daha
  yazmak olurdu — KURAL 6'nın escrow tavanı dersi. Abonelik satırı `price_data`
  ile yerinde kuruluyor.
- **Hesapta AYRI ad uzayı:** `dropship_plan_status` / `_sub_id` / `_period_end`.
  `membership_status`a yazmak kısa yoldu ve **yanlıştı**: webhook'un
  `customer.subscription.deleted` dalı o alanı görünce hesabın ilanlarını askıya
  alıp *"üyeliğiniz bitti, ilanlarınız kapandı"* mektubu yolluyor. Bir alıcının
  dropship aboneliğini iptal etmesi, hiç ilanı ve üyeliği olmayan bir hesaba o
  mektubu göndertecekti. Üç abonelik dalı da artık `metadata.plan` ile ayrılıyor
  ve plan iptali **satıcı sökümünden önce** `break` ediyor.
- **`past_due` bilerek kapalı:** tahsil edilemeyen bir ayrıcalık açık kalmaz.
- **Fiyat istekten gelmez, hesaptan türer.** `dropship_create_order()` birim
  fiyatı `vestra_dropship_unit_price($p, $buyer)` ile kendisi buluyor; POST'taki
  hiçbir tutar okunmuyor. Ortak API'sinin tek statik anahtarının arkasında bir
  VESTRA hesabı **yok**, dolayısıyla o taraf zamlı fiyatta kalıyor — test bunu
  da tutuyor.
- **Satan her yerin iptal yolu olmalı.** Kablolama sırasında iki boşluk çıktı:
  fatura portalı yalnız satıcıyaydı (yani iptal edilemeyen bir abonelik satılmış
  olurdu) ve sayfadaki bağlantı düz `<a>` idi — portal POST istiyor, GET sessizce
  geri döner. "İptal edemiyorum" şikâyeti tam olarak buradan çıkardı.
- **Gösterilen fiyat, tahsil edilen fiyat.** Sayfa da sipariş kurucusu da aynı
  fonksiyondan okuyor; sayfada bir, kasada başka rakam bu depoda zaten yaşandı.
- Ödeme anahtarının sunucu tarafı yolu: `seller-products.yml` →
  `admin_mode=dropship_on|dropship_off`. Panel anahtarıyla **aynı kayıt**, ikinci
  bir karar noktası değil. Yazma **ayrı bir PHP sürecinde** geri okunuyor: bayrak
  süreç içinde `static` önbellekli, aynı süreçte sormak yazılan değeri değil
  önbelleği ölçerdi.
- **Dropship sayfası 8 dilde de İNGİLİZCE** (8 Eyl 2026'da ölçüldü: 49 anahtarın
  43'ü `de.php`'de yok). Bu yeni bir gerileme değil, sayfanın baştan beri hâli;
  KURAL 10'un testi yalnız `de.php`'nin anahtar kümesini karşılaştırdığı için
  yeşil kalıyor. Yeni metinler de bilerek sözlüğe eklenmedi — yarısı çevrilmiş
  bir sayfa, hiç çevrilmemişten kötü görünür. Çeviri istenirse **sayfanın tamamı**
  bir işte yapılmalı.

**KURAL 17 — Dropship tahsilatı USD; çevrim Stripe'tan ÖNCE, kur yoksa sipariş
YOK** (operatör, 8 Eyl 2026: *"dropshippingte USD olarak paranın stripe a gitmesi
gerekiyor"* → *"öncesinde para çevrilsin ve usd olarak gitsin"*).

- **Üç tutar birden çevrilir:** mal satırı, navlun ve Connect komisyonu. Satırlar
  USD iken navlunu EUR bırakmak yarı çalışmaz — Stripe karışık para birimli
  oturumu **reddeder**, hiç açılmaz.
- **Kur sitenin mevcut kaynağından** (`vestra_fx('USD')`, `inc/money.php`). İkinci
  bir kur kaynağı, aynı sipariş için er geç iki farklı rakam demek.
- **KUR YOKSA SİPARİŞ YOK:** `fx_unavailable` / 503, Stripe çağrısından **önce**.
  Uydurulmuş kurla tahsilat, KURAL 3'ün parayla yapılan hâli olurdu; fatura
  tarafı da damgasız siparişte zaten kesim yapmıyor.
- **Birim çevrilip yuvarlanır, sonra adetle çarpılır** (KURAL 5i'nin aynısı).
  Toplamı çevirmek `birim × adet ≠ toplam` bırakır.
- **Kayıt EUR tabanlı kalır**, çekilen tutar ayrı alanlarda: `charge_currency`,
  `charge_amount`, `charge_unit`, `charge_rate`, `charge_rate_date`,
  `charge_rate_source`. Tek alana sıkıştırmak, aylar sonra "bu rakam hangi para
  birimi" sorusunu tahmine bırakırdı.
- **Bu iş üç ayrı vakayı açığa çıkardı, üçü de müşteriye ulaşacaktı:**
  1. `inc/money.php` yalnızca `vestra_dropship_countries()` **içinde** yükleniyordu;
     tahsilat kur isteyince `vestra_fx()` o fonksiyon çağrılmayan her istekte
     **tanımsız** oluyordu — yani **karta basan müşteride fatal**. Bu, aynı sabah
     admin.php için yazılan **KURAL 15'in ikinci vakası**; kural kendi ikinci
     vakasını yakaladı. Artık önsözde.
  2. `dropship_fulfill()` mektubu EUR kaydından **"Amount paid: €x"** yazıyordu;
     kart USD çekilecekti, yani müşterinin ekstresiyle çelişen bir belge.
     Artık çekilen para biriminden yazıyor; `charge_*` alanı olmayan **eski
     kayıtlar EUR kalıyor** — onlar gerçekten EUR çekilmişti.
  3. Ortak API'si yalnız `currency: eur` diyordu. Ortak kendi müşterisine euro
     fiyat verip dolar faturalanacaktı. Artık `charge_currency`, `charge_price`,
     `fx_rate`, `fx_date` dönüyor ve kur yoksa **null** — uydurma rakam değil.
- **Alıcı ÖDEMEDEN ÖNCE görür:** ürün sayfası düğmenin altına dolar tutarını,
  kuru ve kurun tarihini basar, navlunun da aynı kurla çevrildiğini söyler.
  Stripe sayfasında keşfedilen bir para birimi, bu deponun tekrar tekrar
  kaydettiği "sayfada bir, kasada başka rakam" hatasıdır.
- **Abonelik de USD: $199,90/ay** (operatör, 8 Eyl 2026: *"199,90 usd olsun"*).
  Stripe aboneliğinin tutarı ve para birimi **sabit** olmak zorunda — her tahsilatta
  kurla çevrilen bir abonelik diye bir şey yok — o yüzden burada çevrim YOK, sabit
  bir dolar rakamı var. Rakam aynı kaldı, para birimi değişti.
- **Plan ücreti `vestra_money()` ile BASILMAZ.** O fonksiyon argümanı EUR sanıp
  ziyaretçinin gösterim para birimine çevirir; sabiti USD yapıp basımı düzeltmeseydim
  sayfa çevrilmiş bir rakam yazarken Stripe $199,90 çekecekti — bu deponun tekrar
  tekrar kaydettiği "sayfada bir, kasada başka rakam". Tek basım noktası
  `vestra_dropship_plan_label()`; üç sayfa da oradan okuyor.
- Test: `tests/dropship_plan_test.php` §12 (toplam 90 iddia). Önsöz iddiası
  require kaldırılarak sınandı: takım **üretimdeki fatal'in birebir aynısıyla**
  ölüyor (`Call to undefined function vestra_fx()`). *Not: fatal'i `grep HATA`
  ile aramak onu göremez — çıktının tamamına bakılmalı; ilk ölçümümde bu yüzden
  "iddia düşmüyor" sanmıştım.*

**KURAL 18 — MÜŞTERİYE giden hiçbir şey sormadan gönderilmez: önce ÖNİZLE, sonra
SOR, sonra gönder** (operatör, 8 Eyl 2026: *"ilk önce sor"*).

- Bağlam: O7A484 / Stock&chic siparişinde operatör *"bu siparişe sor ödeme yapmış
  mı... ya da yapıp eklemiş mi kontrol et"* dedi. Kontrolü yaptım (durum
  `pending`, fatura INV-2026-1103, dekont yok) ve **aynı turda mektubu da
  gönderdim**. Operatör kendisine sorulmasını istiyordu. Mektup gitti; geri
  alınamaz.
- **Kural:** müşteriye/aday müşteriye giden her şey — cevap mektupları,
  kampanyalar, hatırlatmalar, `payment_notice` / `payment_due` / `tracking_soon`
  gibi bütün `reply_letter` kipleri — önce **`send=false` ile önizlenir**, çıktı
  operatöre gösterilir ve **operatör "gönder" diyene kadar gönderilmez.**
- **"Gönder" demiş olması, bir sonraki mektup için izin değildir.** Her gönderim
  ayrı onay ister; bir turdaki onay o mektuba aittir.
- İstisna yok denecek kadar dar: operatör aynı mesajda hem hedefi hem "gönder"i
  açıkça yazmışsa (ör. *"O7A484'e payment_notice gönder"*) ikinci kez sorulmaz.
  Şüphe varsa sorulur — göndermek geri alınamaz, sormak bir tur gecikir.
- Bu KURAL 5'in (fatura operatör onayıyla kesilir) mektup tarafındaki karşılığı.
  Faturada zaten vardı; mektupta yoktu ve bu boşluktan bir mektup geçti.

**KURAL 33 — VESTRA SİPARİŞİ ALIR, TAHSİL EDER ve BAŞARILI siparişte satıcıya
öder: "başarılı" TEK yerde hesaplanır** (operatör, 19 Eyl 2026: *"satıcılar
için siparişleri ben alıcam ve başarılı olan siparişleri satıcılara
ödeyeceğim, bunun yapılması için hukuki bir sistem yap"* + *"FAQ'a da
yazabilirsin"*).

- **Hukuki çatının yarısı ZATEN VARDI ve ölçülerek görüldü:** Şartlar **3c**
  (*"VESTRA bazı siparişleri kendi adına fatura eder ve o siparişte satıcı
  odur"*) beş dilde duruyor, Satıcı Sözleşmesi §2 *"VESTRA malı satıcıdan
  alıp yeniden satarsa"* diyor. Eksik olan **satıcıya ödeme tarafıydı**:
  §7 hâlâ yalnız escrow'u anlatıyordu (*"para escrow'da tutulur ve alıcı
  onayından sonra serbest bırakılır"*), yani operatörün kurduğu modelde
  satıcının parasını NE ZAMAN alacağını söyleyen tek satır yoktu.
- **Tek karar noktası `vestra_seller_settlement($ref)`** (`inc/orders.php`,
  saf): hukuk metni kuralı **anlatıyor**, panel onu **hesaplatıyor**. İkisi ayrı
  yazılsaydı sözleşmedeki tarih ile operatörün ekranındaki tarih ayrışırdı —
  KURAL 7'de mektubun son tarihi ile otomatik iptalin son tarihi tam böyle
  ayrışmıştı.
- **DÖRT KOŞUL, hiçbiri yeniden TANIMLANMADI:** (1) alıcının parası geldi →
  `vestra_order_payment_settled()` (KURAL 7b), (2) mal teslim edildi → zincirin
  kendisi (`VESTRA_ORDER_STEPS`, elle yazılmış durum listesi değil), (3) talep
  penceresi kapandı → `vestra_claim_deadline()` (KURAL 11, **iş günü**),
  (4) açık talep yok → `vestra_claim_is_open()`. Kapının ikinci bir kopyası bu
  depoda **altı kez** yanlış yere baktı; yedincisi yazılmadı.
  **İptal edilen sipariş ödenmez** çünkü `cancelled` zincirde bilerek yok.
- **SÜRE OPERATÖRDEN GELMEDİ — varsayım, ve açıkça işaretli.** Operatör
  *"başarılı siparişleri ödeyeceğim"* dedi, gün sayısını söylemedi.
  `VESTRA_SELLER_SETTLEMENT_DAYS = 5` (iş günü), havale için tanınan 5 iş
  günüyle (KURAL 7) aynı şekilde seçildi. **Değiştirmek TEK satır**: sabit,
  hukuk metni, SSS ve panel hepsi oradan okuyor.
- **Saat TESLİMATTAN değil, talep penceresi KAPANDIKTAN sonra işliyor:**
  pencere açıkken ödemek, alıcı hakkını kullandığında geri istenmesi gereken bir
  para göndermek olurdu (escrow'un serbest bırakma kuralı aynı sebeple
  `vestra_claim_deadline`'ı bekliyor).
- **Teslim TARİHİ uydurulmuyor:** açık damga yoksa geçmişteki `delivered`
  satırı okunuyor, ikisi de yoksa fonksiyon *"tarih bilinmiyor"* diyor ve
  bugünle doldurmuyor. `updated_at` **bilerek okunmuyor** — o kaydın son
  yazılma anı (KURAL 7b'de bir kez ödenmiş ders).
- **Metin BEŞ dile birden girdi** (KURAL 5q): Satıcı Sözleşmesi **§9**
  (kaç bölüm olduğu dil dil sayıldı, beşinde de 8 → 9), Ödemeler politikasına
  yeni bölüm, AML *"Fonlar"* maddesine istisna cümlesi. Çeviri varsa İngilizce
  belge **hiç okunmuyor**, yani yalnız İngilizceye eklenen bir madde
  de/fr/it/es'te görünmezdi. `vestra_legal_updated()` beş dilde 19 Eyl'e çekildi
  — metni değiştirip tarihi bırakmak okuyucuya *"bu belge değişmedi"* demekti.
- **Ölçüm dil başına AYRI PHP SÜRECİNDE** (`vlang()` ilk çağrıda sabitleniyor —
  KURAL 11'in tuzağı): beşinde de 9 bölüm, §9 var, her iki rakam yazılı,
  çözülmemiş değişken 0, DOM hatası 0. *Bu tuzağa bu turda bir kez düştüm:
  `vestra_legal('de')` diye çağırdım, oysa fonksiyon ARGÜMAN ALMIYOR — beş dil
  de "İngilizce" ölçüldü ve tabloyu yanlış okudum.*
- **SSS: rakam dokuz dilin metnine GÖMÜLMEDİ.** Metin yer tutucu taşıyor
  (`{claim_days}`, `{settle_days}`, `{commission}`) ve `vestra_faq_fill()`
  çözüyor; komisyon `vestra_commission_pct_label()`'den, yani beş sayfanın
  okuduğu aynı gövdeden. Dokuz dile rakam gömmek KURAL 6'nın escrow tavanı
  hatasının dokuz katı olurdu. Tanınmayan bir yer tutucu **olduğu gibi kalıyor**
  (sessizce silmek cümlenin ortasında boşluk bırakırdı).
- **YEREL ÇİZİM GERÇEK BİR KUSUR BULDU — kaynak taraması bulamazdı:**
  `vestra/faq.php` `inc/faq.php`'yi **head.php'den ÖNCE** yükleyip
  `vestra_faq()`'i hemen çağırıyor, yani komisyon etiketini basan fonksiyon o
  anda TANIMSIZDI ve `function_exists` yedeği **sessizce boş dize** döndü:
  sayfa *"一律%の手数料"* ve *"عمولة %"* diye basıldı — rakamsız bir oran.
  Düzeltme KURAL 15'in aynısı (dosya bağımlılığını kendi yüklüyor) ve **yedek
  kaldırıldı**: yüklenmemişse sessizce geçmek yerine ölmeli. Test artık bunu
  ayrı bir süreçte, sayfanın gerçek yükleme sırasıyla ölçüyor.
- **Bu iş, ÖNCEDEN yanlış olan beş SSS maddesini de düzeltti** (aynı
  kategorilerdeydiler ve yeni metnin yanında duramazlardı): *"doğrudan
  satıcıya ödersiniz"*, *"VESTRA asla para tutmaz"*, *"satıcının banka bilgisi
  faturada yazılıdır"* artık istisnayı söylüyor; ve **22 Ağustos'ta kaldırılan**
  kademeli komisyon (3,5 / 3,2 / 2,8) ile **artık var olmayan** üyelik planları
  (Starter €19,90 / Pro €39,90 / Elite €89,90) dokuz dilden birden silindi —
  16 Eylül denetimi beş sayfayı düzeltmişti, SSS'de kalmışlardı.
- **İKİ YENİ MADDE**, dokuz dilde: alıcıya *"kime ödüyorum — VESTRA'ya mı
  satıcıya mı?"*, satıcıya *"bir sipariş ne zaman başarılı olur ve ne zaman
  ödenirim?"*. Yeni maddeler kategorinin **SONUNA** eklendi: `vestra_faq()`
  indekse göre birleştiriyor, araya sokmak bütün kategoriyi kaydırırdı.
- **Şartlar 3c'ye DOKUNULMADI, bilerek:** madde zaten alıcı tarafındaki hukuki
  çekirdeği söylüyor (VESTRA o siparişte satıcıdır) ve beş dilde ayrı ayrı
  yazılmış bir paragrafın ortasına cümle sokmak, beş dosyada beş ayrı riskti.
  Satıcıya ödeme Satıcı Sözleşmesi'nin işi.
- **Panelde görünüyor** (`Admin ▸ Orders ▸ <sipariş>`): *"Satıcıya ödeme:
  ÖDENEBİLİR / talep penceresi sürüyor / mal henüz teslim edilmedi …"* + talep
  penceresi ve son ödeme tarihi, yanında `3 + 5 iş günü · Satıcı Sözleşmesi §9`.
  Bir kuralın okunacağı yeri olmaması, onu hatırlamaya bırakmaktır.
- **AÇIK KALAN, operatör kararı bekliyor:** (a) **süre 5 iş günü bir
  varsayım**; (b) satıcıya ödenecek TUTAR bugün kayıtta **ayrı bir alan
  değil** — metin *"siparişin sayfasında yazan tutar"* diyor ve panelde
  `payout` sütunu duruyor, ama bu modelde komisyonun uygulanıp uygulanmayacağı
  bir **fiyat kararı** ve operatörün; (c) ödeme **kaydı** (kim, ne zaman, ne
  kadar ödendi) henüz tutulmuyor — kural hesaplanıyor ve gösteriliyor, ödeme
  defteri ayrı bir iş.
- Test: `tests/settlement_policy_test.php` (**99 iddia**, iki yön). Düşebildiği
  doğrulandı, her sabotajın **gerçekten uygulandığı** ayrıca yazdırılarak: talep
  penceresi beklenmeyince **2 kırmızı**, süre elle 3 yazılınca **3**, SSS yer
  tutucuları çözülmeyince **3**, Almanca madde geri alınınca **3**, sessiz
  `function_exists` yedeği geri konunca **4**.
- **NUMARA ÇAKIŞMASI, aynı gün ikinci kez:** bu madde önce **KURAL 32** diye
  yazıldı, oysa paralel oturum aynı öğleden sonra hoş geldin indirimini o
  numarayla kaydetmişti. Aynı dosyada iki "KURAL 32", ikisine de atıf yapan
  her satırı okunamaz yapar. **33'e taşındı.** *Aynı dala yazan ikinci bir
  oturum varsa, yeni bir kural numarası almadan önce `grep "^\*\*KURAL"` ile
  dosyanın o anki hâline bak — numara da bir kayıttır.*

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
- **Artık TESTİ var: `tests/no_real_iban_test.php`.** Kural 19 Eyl 2026'da
  çiğnendi (bkz. KURAL 5r'nin düzeltme maddesi) ve sebebi kuralın
  unutulması değil **taramanın dar olmasıydı**: sızıntı kontrolü iş akışlarına
  ve kaynak dosyalarına bakıp testlere bakmamıştı. Test `git ls-files`'ın
  **tamamını** tarıyor ve ölçüt desen değil **mod-97**: rastgele bir
  büyük-harf+rakam dizisi (SKU, sipariş ref'i, hash) IBAN sağlamasından
  geçmiyor, yani gürültü sıfır — ölçüldü, depoda tam 4 geçerli IBAN var ve
  dördü de belgelerde örnek olarak geçen sentetik numaralar (izin listesinde,
  her biri gerekçesiyle). Yeni bir numara eklemek isteyen önce *"bu gerçek bir
  hesap mı"* sorusunu cevaplamak zorunda. İki yönü de tutuyor: sızıntılı dosya
  **yakalanıyor**, SKU/ref taşıyan temiz dosya **geçiyor**.

## Kayıtlı reddetmeler

Aşağıdakiler istendi ve gerekçesiyle yapılmadı — tekrar gelirse aynı gerekçe geçerli:
- Müşteri şifrelerini saklayıp gösterme (commit `a317f47`) — hash geri döndürülemez.
- Kurumsal format rehberinden çalışan e-postası türetme — var olmayan kişilere posta
  gider, sert bounce oranı gönderen alan adının itibarını düşürür.

**KURAL 19 — Fiyat listesi GİRİŞSİZ açılmaz; kayıt zorunlu** (operatör, 8 Eyl
2026: *"giris olmadan price list acilmasin anmeldung a zorla"*).
- `/price-list` ve `/price-lists` o güne kadar **bilerek** herkese açıktı
  (dosyaların kendi yorumu "PUBLIC ON PURPOSE" diyordu): rakam zaten kilitliydi
  — fiyat sütununda 🔒 — ama **katalogun kendisi** açıktı: ürün adı, üreticinin
  artikel numarası, MOQ, beden aralığı, **beden bazında stok derinliği** ve
  marka başına ürün sayısı. Artık kilitliyken **tek satır** çizilmiyor.
- **Kapı yeniden tanımlanmadı:** iki sayfa da `head.php`'nin `$PRICES`'ini
  (`$IS_ADMIN || auth_prices_unlocked()`) okuyor ve tek çiziciye devrediyor:
  `inc/pricewall.php`. Bu depoda kapının ikinci kopyası **altı kez** yanlış yere
  baktı; yedincisi yazılmadı.
- **Duvarın her cümlesi 7 sözlükte ZATEN duran bir anahtar** (KURAL 10): ilk
  günden 8 dilde doğru çıkıyor, yarısı İngilizce kalmıyor. Almancası
  *"Gewerbeanmeldung"* — operatörün istediği "Anmeldung" tam olarak bu.
- **Yan etki (bilinçli):** bu iki sayfa artık arama motoruna da ürün metni
  vermiyor; Googlebot da girişsiz ziyaretçi olduğu için duvarı görüyor. Sayfalar
  200 dönmeye ve başlık/açıklama taşımaya devam ediyor (404 değil), yani
  kampanya bağlantıları ve dizindeki adresler kırılmıyor — ama /price-list'in
  eski SEO metni gitti. Karar operatörün.
- **Kilitli ziyaretçi ne görüyor:** marka/kategori bağlantıları hâlâ altbilgide
  (KURAL 9, site geneli) — duvar yalnız listeyi kapatıyor, siteyi değil.
- Test: `tests/price_wall_test.php` (31 iddia). Sayfalar kum havuzunda
  **gerçekten çiziliyor**: kilitliyken satır/ad/artikel no yok, kapı açıkken
  liste var. Kaynakta `if (!$PRICES)` görmek ölçüm değil. Muhafaza kaldırılınca
  **6 iddia kırmızıya dönüyor** (doğrulandı).

**KURAL 19b — "Line-sheets by brand" ONAYLI ÜYEYE FİYATLI listeyi verir; ve LOT
artık kendi sütunu** (operatör, 18 Eyl 2026: *"Line-sheets by brand listelerini
düzelt ....fotolardan başka hiç bir sey cikmiyor.... komple sku dan lot a ve tum
bilgilere kadar herseyin daha duzgun cikmasi gereklimi"*).

- **ÖNCE ÖLÇÜLDÜ, üç ucun üçü de canlı sunucuda çalıştırıldı** (`inspect-products`
  → yeni `linesheet` girdisi; sonda **üretilen dosyayı** ölçüyor, kaynağı değil):

  | Uç | Ne çıktı |
  |---|---|
  | `/catalog?brand=Lacoste` (sol sütunun BAĞLANDIĞI yer) | 8 sütun · 21 satır · **21 gömülü fotoğraf** · fiyat YOK, kategori YOK, beden YOK, lot YOK |
  | `/wholesale-list.xlsx?brand=Lacoste` | **18 sütun** · artikel no, renk, beden serisi, MOQ, toptan fiyat, kademe, stok, ürün linki · **fotoğraf 0** |
  | `/wholesale-list.pdf?brand=Lacoste` | 4 sayfa · **12 gömülü fotoğraf** · aynı veriler |

  Yani şikâyet doğruydu ve sebebi **dosyalar değil, bağlantıydı**: kutu ÜYE'ye
  açılıyordu ama her marka satırı **soğuk alıcıya giden fiyatsız tanıtım
  dosyasına** gidiyordu. Onaylı üyenin kendi listesi VARDI, o ekranda
  **bağlantısı yoktu** — *bir ekranda görünmeyen seçenek olmayan seçenektir*
  (KURAL 2e'nin bu dosyadaki hâli; operatör aynı dersi "açacak düğmem yok"
  diye iki kez söylemişti).
- **Kapı yeniden TANIMLANMADI.** Ölçüt `head.php`'nin `$PRICES`'i; kapı açıksa
  marka satırı `/wholesale-list.pdf?brand=` (fotoğraf + veri), yanında küçük
  `XLSX` çipi (sıralanabilir). Kapalıysa **eskisi gibi** `/catalog`. Bu depoda
  kapının ikinci bir kopyası **altı kez** yanlış yere baktı.
- **Bağlantıyı gizlemek kapı değildir:** `/wholesale-list.*` zaten kendi tarafında
  `auth_prices_unlocked()` soruyor ve onaysız üyeyi `/price-list`'e yolluyor —
  ölçüldü (302 → `/price-list?brand=Lacoste`), yani sol sütun değişse bile fiyat
  sızmıyor (KURAL 4b'nin `/offer` dersi).
- **FOTOĞRAF neden PDF'te, Excel'de değil:** fiyatlı Excel'in fotoğrafı **bilerek**
  kaldırılmış (dosyanın kendi notu: müşterinin Excel'inde CMYK/progressive JPEG'ler
  **boş kutu** olarak çıkıyordu, "delikli bir liste hiç fotoğraf vaat etmeyenden
  kötü"). Yani *"fotoğraf + veri birlikte"* isteğinin doğru cevabı **PDF**.
  Dosyanın başlığı hâlâ *"with photographs embedded in the sheet"* diyordu —
  **bayat ve yalan bir satır**, düzeltildi.
- **LOT (karton adedi) ÜÇ listede de YOKTU** ve bu gerçek bir boşluktu: yalnız
  `sizes` dizesinin kuyruğuna gömülü bir `10/pack` parçası olarak görünüyordu,
  yani **sıralanamıyor, süzülemiyor, adetle çarpılamıyordu** — oysa toptancının
  ilk sorusu "kaç karton". Artık:
  - fiyatlı Excel'de **ayrı SAYI sütunu** (`MOQ` ile `Unit` arasında),
  - PDF'te **kategori satırında** (`Jeans · lots of 10`) — **MOQ sütununun ALTINA
    yazılmadı**, bilerek: o bandı beden dizisinin ikinci/üçüncü satırı kullanıyor
    (`sizeW` sütun sınırına kadar gidiyor) ve iki metin üst üste binerdi; bu dosya
    tam o çakışmayı bir kez düzeltmiş ve notu içinde duruyor,
  - herkese açık tanıtım dosyasında **Category + Sizes ile birlikte** (fiyat yine
    YOK — dosyanın var olma sebebi o, testte ters yön iddiası var).
- **Tek karar noktası `vestra_pack_size()`** (`inc/products.php`). **Yokluk belirsiz
  değil:** `admin.php:615` alanı yalnızca 1'den büyükken yazıyor
  (`if($step>1) … else unset(…)`), yani alanın olmaması *"bilinmiyor"* değil
  *"tek parça"* demek — 1 dönmek bir tahmin değil, kaydın kendi ifadesi (KURAL 3
  bir olguyu UYDURMAYI yasaklıyor; burada kayıt zaten konuşuyor). `linesheet.php`
  bu okumayı baştan beri yapıyordu ve artık o da aynı fonksiyonu çağırıyor.
- **Sütun EKLEMEK sonraki tüm indeksleri kaydırıyor:** `numcols`/`linkcols`/`widths`
  haritaları elle güncelleniyor ve biri unutulursa **ürün linki başka bir hücreye**
  bağlanır. Test bunu ürün linkinin gerçekten link sütununda olmasıyla ölçüyor.
  Tanıtım dosyasında ayrıca **fotoğraf SON hücreye tutturuluyor** (`xlsx.php`), o
  yüzden `Photo` sütununun son sırada kaldığı da ayrı bir iddia.
- **7 sözlük anahtarı 8 dile birden.** Blok bugüne kadar **her dilde İngilizceydi**
  (`Line-sheets by brand` ve `All brands` hiçbir sözlükte yoktu); yeni metinleri
  ekleyip eskileri bırakmak yarısı çevrilmiş bir kutu bırakırdı.
- **ÇİZDİRİLDİ, kaynak okunmadı** (kum havuzu + gerçek tarayıcı, üç kapı hâli):
  onaylı üyede **5 satır, 5 XLSX çipi, `/catalog` 0**; onaysız üyede **tersi**;
  misafirde kutu **hiç yok**. Masaüstü/mobil/Almanca/Arapça RTL, yatay taşma
  **0**, PHP uyarısı **0**. Üretilen `.xlsx` ve `.pdf` açılıp **okundu** (Lot=10
  kartonlu ilanda, Lot=1 tek parçada; PDF'te `Hoodies & Sweatshirts · lots of 10`).
- **Ölçüm tuzağı (yaşandı):** test kabuğu `session_start()`'ı `inc/auth.php`'den
  **önce** çağırıyordu ve auth.php *"oturum yanlış depoda başlatılmıştı"* satırını
  **çıktıya** basıyor. HTML sayfada zararsız; `.xlsx`/`.pdf` **binary** ve o tek
  satır zip/PDF imzasının önüne geçiyor — ölçüm *"dosya üretilmedi"* dedi, oysa
  üretilmişti, önüne bir satır metin konmuştu.
- **İkinci tuzak:** PDF'te ayırıcı **CP1252 orta nokta (0xB7)** olarak duruyor ve
  iddiayı **tek tırnaklı** bir dizgeyle yazdım — orada `\xb7` dört harftir, yani
  iddia hiçbir belgede eşleşmez. (Ham bayta bakmak bu üreteçte geçerli: `inc/pdf.php`
  akışları **sıkıştırmıyor**; test bunu ayrıca doğruluyor ki bir gün Flate eklenirse
  *"metin yok"* diye yanlış bir kırmızı değil, sebebi söyleyen satır düşsün.)
- Test: `tests/linesheet_test.php` (**65 iddia**, iki yön). Düşebildiği doğrulandı,
  her sabotajın **gerçekten uygulandığı `grep -c` ile ayrıca yazdırılarak**: sol
  sütun eski hâline döndürülünce **6 kırmızı**, fiyatlı listeler onaysız üyeye de
  açılınca **4**, Excel'den `Lot` kalkınca **4**, PDF'ten lot kalkınca **2**,
  tanıtım dosyasına fiyat sızınca **2**, `size_step` elle okununca **2**.
- **Bu işten bağımsız, ÖNCEDEN kırık:** `dropship_plan_test.php`'nin dört FX
  iddiası (bu ortamda kur kaynağına çıkış yok), ve deploy dalında duran
  `msg_read_receipt_test` (1) ile `msg_thread_label_test` (10) — üçü de **benim
  birleştirmemden ÖNCE**, dalın kendi ucunda aynı şekilde düşüyor (ölçüldü,
  varsayılmadı). Dokunulmadı.
- **CANLI ÖLÇÜM (18 Eyl 2026, deploy `0f84ec1e`, run 1303), üç uç de yeniden:**

  | Uç | Deploy SONRASI |
  |---|---|
  | `/wholesale-list.xlsx?brand=Lacoste` | **19 sütun** (`… MOQ · **Lot** · Unit · Wholesale EUR …`), 17 satır; `lac-fleece-hoodie` **MOQ 40 / Lot 10 / 49,90 · 50+ 45,00 · 100+ 42,00**, `lac-pima-tshirt` **MOQ 104 / Lot 8**, `lac-pique-polo` **MOQ 80 / Lot 8** |
  | `/catalog?brand=Lacoste` | **11 sütun**, 21 satır, **21 gömülü fotoğraf**; `Category` **%100**, `Sizes` **%100**, `Lot` **%100** |
  | `/wholesale-list.pdf?brand=Lacoste` | 4 sayfa · **12 gömülü fotoğraf** · `lots-of=**12**` (12 ürünün 12'sinde), `Colours=12`, ürün linki 48 |

- **Sondanın KENDİ GÜRÜLTÜSÜ iki yerde temizlendi** (rtl-check'in verdiği dersin
  üçüncü ve dördüncü hâli): (1) `Photo` sütunu **her koşuda**
  `*** HIC DOLU DEGIL ***` diye alarm veriyordu — kusur değil, görsel hücreye
  **metin** olarak yazılmıyor, sayfanın üzerine **çizim çapasıyla** tutturuluyor
  (`inc/xlsx.php`) ve gerçek ölçüsü hemen üstteki "gömülü fotoğraf" sayısı;
  (2) PDF dalında metin **hiç sayılmıyordu** (*"akışlar Flate ile sıkıştırılabilir"*
  gerekçesiyle) — doğrusu **önce sıkıştırmayı sormak**, sıkıştırılmamışsa saymak.
  Bu düzeltme olmadan *"lot belgede gerçekten yazıyor mu"* sorusu canlıda
  cevapsız kalıyordu.
- **Kesme işareti tuzağı bu dosyada İKİNCİ kez** (`d0ba6062`'den sonra): yeni
  eklediğim `printf` biçim dizesindeki `'MOQ'` ve bir yorumdaki `'Photo'`,
  `php -r '…'` gövdesini saran **bash tek tırnağını** erken kapattı ve adım
  *"Parse error: Unclosed ("* ile düştü. Yasak artık ikisinin de **yanında
  yazılı**. *Bir kuralı bir yerde uygulamak, kuralı uygulamak değildir.*

## SEO ve diller

**KURAL 9 — SEO iniş sayfaları canlı stoktan türer; boş sayfa yok** (operatör
isteği, 3 Eyl 2026: *"seo yu çok güçlü yap, ayakkabıları da koy, Avrupa müşterileri
de girsin, 5 dilde eksiksiz: markalar, aksesuar, ayakkabı, tshirt, bot, sweat, B2B"*).
- Adresler: `/wholesale/<marka>` (vardı), **`/b2b/<kategori>`** (Sneakers, T-Shirts…),
  **`/b2b/<grup>`** (Footwear, Tops, Accessories… — `vestra_all_cats()` grupları),
  **`/b2b/apparel`, `/b2b/footwear`** (iki vitrin bölmesi) ve
  **`/wholesale/<marka>/<kategori>`** ("Lacoste polos wholesale"). Hepsi `b2b.php`;
  sözlük ve çözücü `inc/seo.php` (`vestra_seo_resolve`, `vestra_seo_landing_paths`).
- **Stokta olmayan hiçbir şeye sayfa açılmaz**: çözücü boş sonuçta `null` döner,
  sayfa 404 basar, sitemap/footer o adresi listelemez. "Accessories" bugün 404'tür
  çünkü stokta aksesuar yok — ilk aksesuar ilanıyla kendiliğinden açılır.
  Bir kategori sayfası için sahte/ince içerik üretme; boş sayfa alan adına zarar verir.
- İç bağlantılar: ana sayfa marka duvarı artık `/wholesale/<marka>`'ya gider (eskiden
  hepsi `/shop`), ana sayfa kategori şeridi + ayakkabı bandı çipleri `/b2b/…`'ye, footer
  her sayfada marka + kategori + koleksiyon bağlantılarını basar (`inc/foot.php`).
  Ürün sayfası breadcrumb'ı kategori seviyesini `/b2b/<slug>` ile verir ve **sayfa
  dilinde** basılır (eskiden her dilde İngilizce "Home/Catalog").
- `/shop?section=footwear` kendi başlık/meta/anahtar kelimesini taşır — ayakkabı
  koleksiyonu daha önce giyim başlığıyla dizine giriyordu.
- Bölgesel hreflang: `vlang_hreflang_map()` (`inc/i18n.php`) — de-AT, fr-BE, en-NL,
  pt-BR, ru-RU, ar-AE… hepsi aynı dil sayfasına işaret eder; liste `vlang_list()`'ten
  türer. `head.php` ve `index.php` (kendi `<head>`'i var) ikisi de bunu kullanır.
- Kontrol: `tests/seo_landing_test.php` (slug gidiş-dönüş, çözücü, sitemap listesi,
  hreflang, sözlük eksiksizliği, kaynak kablolaması) ve canlıda `seo-check.yml`
  (`path=/b2b/sneakers` gibi).
- **Canlı ölçüm, 4 Eyl 2026 (deploy `fd14054`, run 912):** ana sayfa 8 dilde yerel
  başlıkla çıkıyor (pt/ru/ar artık İngilizceye düşmüyor), hreflang 6 → 46, sitemap
  733 → 828 URL; `/b2b/sneakers`, `/b2b/footwear`, `/wholesale/valentino` 8 dilde de
  200 ve Googlebot kimliğine WAF challenge yok. `seo-check.yml`'in sunucu içi
  localhost adımı bu barındırmada artık bu sitenin vhost'una düşmüyor (kontrol
  `/shop` bile 404, "/" için "Coming Soon"): o adımın 404'ü kanalı ölçer, sayfayı
  değil — geçerli ölçüm dış (internet) adımdır; workflow bunu artık kendisi yazar.
  Ana sayfada "Valentino wholesale" YOK çıkması hata değil: anahtar kelime listesi
  stok derinliğine göre ilk 12 marka, Valentino 13. sıra ve sonrası.

**KURAL 9b — PAZAR sayfaları: `/wholesale-to/<pazar>`; hreflang etikettir, İÇERİK
değildir** (operatör, 17 Eyl 2026: *"SEO yu kontrol et ve kusursuz hale getir
catalogtaki markalarida kullan sadece avrupa degil avustralya japonya duba qatar ve
usa da olsun....avrupada kusursuz istiyorum"* → aynı gün *"brezilya ve güney
amerika, israil, singapur, g.koreyide ekle"*).

- **ÖNCE ÖLÇÜLDÜ** (canlı, run `35268651818`): 48 hreflang etiketi, sitemap 1.091
  URL, ana sayfa anahtar kelimeleri **12 markada kesiliyor** (Gucci ve Valentino
  ana sayfada YOK, `/b2b` sayfalarında VAR). Okuma üç gerçek boşluk gösterdi:
  1. **hreflang "Avrupa" diyordu ama 13 Avrupa ülkesini atlıyordu:** Lüksemburg,
     Malta, Kıbrıs, Hırvatistan, Slovenya, Slovakya, Bulgaristan, üç Baltık,
     İzlanda, Liechtenstein hiçbir bölgesel etikette geçmiyordu. Yani "Avrupa'da
     kusursuz" iddiası birliğin üçte birinde **boştu**. Artık AB 27 + EFTA + GB'nin
     hepsi kapsanıyor ve test **ülke ülke sayıyor** — *"48 etiket var" hangi
     ülkelerin kapsandığını SÖYLEMİYOR.*
  2. **Organization JSON-LD İKİ KOPYAYDI ve ayrışmıştı:** `inc/head.php` altı kıta
     sayıyor, `index.php` **`areaServed: 'EU'`** diyordu. Yani ana sayfa her arama
     motoruna *"yalnız Avrupa"* derken alt sayfalar *"her yer"* diyordu; üstelik
     ana sayfanın `knowsAbout`'u 14 markada kesiliyordu. Tek gövde:
     `vestra_seo_org_ld()` (`inc/seo.php`), **markaların TAMAMI** (operatörün kendi
     cümlesi: *"catalogtaki markaları da kullan"*). Aynı hata `og:locale`'de de
     vardı: head.php'nin elle yazılmış listesinde **`ja` YOKTU**, yani Japonca her
     alt sayfa `en_US` basıyor, yalnız ana sayfa `ja_JP` diyordu.
  3. **hreflang bir ETİKET, içerik değil.** Sidney'deki, Dubai'deki ya da Seul'deki
     bir butik *"wholesale fashion supplier Australia"* diye arıyor ve bu alan
     adında o sorgunun ineceği **tek bir sayfa yoktu**.
- **On pazar** (`vestra_seo_markets()`, TEK tablo): Avustralya, Japonya, BAE, Katar,
  ABD, Brezilya, Güney Amerika, İsrail, Singapur, Güney Kore. Tablo hreflang'in,
  `areaServed`'ın, altbilginin, sitemap'in ve sayfanın kendisinin okuduğu tek yer;
  **bir pazar eklemek BİR satır.**
- **SAYFADAKİ HİÇBİR RAKAM ELLE YAZILI DEĞİL** ve bu kuralın bedeli bu depoda
  ödendi (KURAL 6: escrow tavanı beş gün metinde 3.000, kodda 3.500). Para birimi
  `vestra_currency_for_cc`, kalıcı indirim `vestra_region_discount_rates`, Avrupa
  dışı taban `VESTRA_NONEU_MIN_ORDER_USD`, kapının kayıtta açılıp açılmadığı
  `vestra_auto_open_countries` (KURAL 2h), diller hreflang'in **kendisinden**,
  markalar/kategoriler canlı stoktan.
- **Bölgede olgu ülke ülke aynı değilse SATIR HİÇ BASILMIYOR.** Güney Amerika 12
  ülke; 11'inde geçerli bir indirimi hepsine yazmak KURAL 3'ün yasakladığı şey.
  Test bunu **sentetik bir karışık bölgeyle** (AU + US) ölçüyor: para birimi,
  indirim ve `auto_open` üçü de susuyor. Aynı şekilde ABD sayfasında indirim satırı
  **yok** ve olmamalı.
- **CANLI BULUNAN GERÇEK KUSUR — Japonca IP tarafında hiç servis edilmiyordu.**
  Japonca 5 Eylül'de siteye eklendi (tam sözlük, 1.271 anahtar) ama
  `vlang_country_lang()` tablosuna **`JP` hiç yazılmadı**: tarayıcısı dil
  bildirmeyen Japonyalı ziyaretçi, site tamamen Japonca olduğu hâlde **İngilizce**
  görüyordu. Kusur yorumda *onaylanmış* hâlde duruyordu (*"sitenin dili olmayan bir
  ülke (ör. TR, JP)"*). *Bir dil eklerken "bu olgu başka nerede yazılı" diye
  sorulmadığında olan şey.* `tests/lang_from_ip_test.php` eski ve **hatalı**
  davranışı pinliyordu; davranış bilerek değişti, iddia düzeltildi.
- **`GY` ve `SR` testin kendisi yüzünden bulundu:** Guyana ve Surinam Güney Amerika
  pazarının içinde ama hiçbir hreflang etiketi taşımıyordu — sayfa 12 ülkeyi
  kapsadığını söylüyor, etiket ikisinde yok. *Pazarın her ülkesinin bir etiketi
  olduğunu sayan iddia yazılmasaydı görünmezdi.*
- Yan düzeltmeler, hepsi aynı okumadan: journal yazılarına **Article** şeması
  (yazar, tarih, kapak HTML'de vardı, hiçbir şemada yoktu), sitemap'e **`<lastmod>`**
  (kaydın **kendi** tarihinden; bugünün tarihini her satıra basmak "her şey
  değişti" demek olurdu), **iç çamaşırı bölmesine kendi başlık/açıklaması** (146
  ilan giyim başlığıyla duruyordu) ve paylaşılan üç meta anahtarında *"across
  Europe"* → **worldwide** (kod Avrupa dışına gönderimi zaten destekliyor —
  KURAL 27 bunun üzerine yazılı).
- Test: `tests/seo_market_test.php` (39 iddia; sayfayı kum havuzunda **gerçekten
  çizdiriyor** — kaynakta `sprintf(t(...))` görmek ölçüm değil) ve
  `seo_landing_test.php` 87 → **186 iddia**. Düşebildiği doğrulandı, her sabotajın
  **gerçekten uygulandığı `grep -c` ile ayrıca yazdırılarak**: kendine-bağlanma
  muhafazası kalkınca **1 kırmızı**, para birimi metne gömülünce **2**, taban
  gömülünce **2 + 1**, Avrupa etiket listesi kısalınca **1**, altbilgi bloğu
  silinince **1 + 1**, ülke adı çevrilmeyince **1**.
- **Kendi ölçüm hatam, kayda geçsin:** *"kendine bağlanmıyor"* iddiası ilk yazımda
  **bütün HTML'i** sayıyordu ve altbilgi zaten her sayfada bütün pazarları
  listeliyor — yani doğru çalışan kodu kırmızı gösterdi. Ölçüt artık **fark**
  (kendi sayfası 1 kez, diğerleri 2 kez). İkincisi daha sinsiydi: *"ülke adı
  Almanca"* iddiasını **Japonya** ile yazmıştım, oysa Japonya'nın Almancası da
  "Japan" — çeviriyi kaldıran sabotaj testi **yeşil** bıraktı. Avustralya/
  "Australien" ayırt ediyor.
- **Kendi hatam, ikinci: `git checkout --` ile sabotajı geri alırken `head.php`'nin
  COMMIT EDİLMEMİŞ gerçek düzeltmelerini de sildim.** Bu dosyanın kendi kaydı
  *"sabotajı dosya yedeğiyle (`cp`) geri al, checkout ile değil"* diyor; yakalayan
  şey `grep -c` ile yapılan geri okuma oldu, düzeltmeler yeniden yazıldı.
- **Bilerek YAPILMAYAN:** şehir adları (Dubai, Tokyo, São Paulo…) yalnız anahtar
  kelime etiketinde ve **çevrilmeden** duruyor — meta keywords zaten en zayıf
  sinyal ve on şehir adını sekiz dile çevirmek, karşılığı ölçülemeyen 80 sözlük
  anahtarı demekti. Ülke adı çevriliyor, çünkü başlıkta ve gövdede de geçiyor.
- **CANLI ÖLÇÜM (17 Eyl 2026, deploy `3436fe75`, run `35270971368`).** Sunucunun
  kendi kodundan: **10 pazar**, `hreflang 81 etiket / 64 ülke`,
  **`AB27+EFTA+GB kapsamı: 32/32 (tam)`** — sabah 13 ülke eksikti —,
  `Organization: knowsAbout 40 (marka 24)`, `areaServed 16 giriş`, `market.php`
  diskte ve `.htaccess` kuralı 45. satırda. Olgular satır satır doğru:
  Avustralya `AUD / %10 / US$5.000 / kapı açık`, ABD `USD / — / US$5.000 / normal`
  (**taban satırı 17 Eyl'de kalktı — KURAL 27; bugün o sütun her pazarda `—`**),
  Güney Amerika `12 etiket, dil en,es,pt`.
- **Dışarıdan (internet, WAF üzerinden):** `/wholesale-to/australia?lang=ja`
  **normal sayfa, 58.995 bayt**, başlık **`オーストラリア向けファッション卸売サプライヤー`**,
  **82 hreflang** etiketi; Arapça koşuda da normal sayfa. Sitemap **1.091 → 1.101
  URL** (10 pazar sayfası). *Aynı koşuda **Gucci ve Valentino** kontrolü ilk kez
  **OK** döndü:* ana sayfanın anahtar kelime etiketi hâlâ ilk 12 markayla sınırlı
  (doğrusu bu), ama pazar sayfası **markaların tamamına bağlantı** taşıyor — yani
  operatörün *"catalogtaki markaları da kullan"* cümlesinin karşılığı etiket değil
  **içerik** olarak sağlandı.
- **Sondanın `dogrudan wholesale-to.php: 404` satırı YANILTICI ve sayfaya ait
  değil:** o dal yolun ilk parçasından bir dosya adı tahmin ediyor (`wholesale-to`),
  oysa dosya `market.php`. Zaten localhost kanalı bu barındırmada bu sitenin
  vhost'una düşmüyor (sondanın kendi bandı bunu yazıyor); geçerli ölçüm dış adım.
- **Bu işten bağımsız, ÖNCEDEN kırık:** `dropship_plan_test.php`'nin dört FX
  iddiası **HEAD'in temiz kopyasında da aynı şekilde** düşüyor (bu ortamda kur
  kaynağına çıkış yok). Dokunulmadı.

**KURAL 10 — Site 8 dilde ve her sözlük `de.php`'ye karşı EKSİKSİZ** (operatör,
3 Eyl 2026: *"5 dilde eksiksiz"* → *"6-7 dil yap, rusça ve portekizce ekle"* →
*"arapçada yap"*). `vlang_list()` = en, fr, es, it, de, **pt, ru, ar**.
- Referans set `inc/lang/de.php` anahtarları (1.104). `tests/seo_landing_test.php`
  her dil için **her** anahtarı ve `%1$s`/`<b>` sayılarını doğrular; eksik anahtar
  `t()` yüzünden sessizce İngilizceye düşer, test bunu kırmızıya boyar. Yeni bir
  `t('…')` metni eklediğinde **7 sözlüğe birden** ekle (fr/it/es'de 19 anahtar
  eksikti — numune siparişi bloğu — aynı gün tamamlandı).
- `index.php` kendi `$T[$lang]` bloğunu taşır (ana sayfa metinleri, ~45 anahtar):
  yeni dil = `$T`'ye yeni blok, yoksa `$t['tagline']` boş kalır. `$_kw`, `$_ogloc` ve
  nav sözlükleri de dil başına satır ister; hepsi `?? en` ile düşer artık.
- **Arapça RTL**: `vlang_dir()` → `<html dir="rtl">` (`head.php`, `index.php`);
  flex/grid kendiliğinden aynalanır, `style.css` sonunda küçük bir `[dir="rtl"]`
  bloğu var. Arapça metinlerde "ileri" okları `←`.
  **Görsel geçiş YAPILDI (4 Eyl 2026): 12 sayfa × 2 genişlik, RTL kaynaklı
  gerileme 0** — `tests/render/rtl-check.js`. Betik her sayfayı önce İngilizce
  sonra Arapça açıp yalnızca FARKI rapor eder; iki dilde de taşan öğe RTL sorunu
  değildir. İlk sürümü bu ayrımı yapmıyordu ve kasıtlı yatay kaydırıcıların
  (marka rayı, beden tablosu) çocuklarını "taşma" sanıp 24/24 sayfayı sorunlu
  gösterdi. Ölçüm aracının kendi gürültüsünü elemeden rapor okuma.
- Portekizce **pt-PT** (Portekiz); Brezilya `pt-BR` hreflang'ıyla aynı sayfaya gelir.
- Legal metinleri (`inc/legal/`) ve SSS içerikleri (`inc/faq/`) pt/ru/ar için **yok**,
  İngilizceye düşerler — bilerek: hukuk metni sohbet çevirisiyle yazılmaz.
  E-posta şablonları (`inc/email_templates.php`) pt/ru/ar için İngilizceye düşer.
- Marka sayfası CSS'i `wholesale.php`'den `style.css`'e taşındı (`.wsw…`);
  `b2b.php` aynı sınıfları kullanır — iki kopya ikinci düzenlemede ayrışırdı.

**KURAL 11 — İade: B2B iadeye KAPALI; yalnız yanlış/eksik/hatalı; bildirim 3 GÜN**
(operatör kararı, 4 Eyl 2026: *"b2b ürünleri geri iadeye kapalidir. Sadece
yanlis, eksik, hatali ürünlerde iade yapilir ve alici ilk 3 gün icerisinde geri
dönmek zorundadir"*).
- **Kanonik metin tek yerde:** `inc/faq.php` → `'returns'` kategorisi (18 madde).
  Site genelindeki bağlantılar `/faq?cat=returns` adresine gider; kural altbilgide,
  ürün sayfasında veya sepette **TEKRAR YAZILMAZ** — iki kopya er geç ayrışır.
  `returns_policy_test.php` bu sayfalarda gün sayısının geçmediğini de doğrular.
- **Gün sayısı tek kaynaktan:** `VESTRA_CLAIM_DAYS` (`inc/escrow.php`) — **İŞ GÜNÜ**
  (operatör kararı, 6 Eyl 2026: *"3 günde hafta sonları sayılmasın"*; 4 Eylül'de
  takvim günüydü). Son tarih `vestra_claim_deadline($teslimTs)` = 3 iş günü sonra,
  gün sonuna kadar; Cuma teslimat → Çarşamba. Escrow serbest bırakma
  (`escrow_release_deadline`) ve satıcının "teslim edildi" mektubu **aynı**
  fonksiyondan okur — mektup eskiden kendi başına "2 iş günü" hesaplıyordu ve
  KURAL 11 süreyi uzatınca geride kalmıştı (alıcı Çarşamba okuyor, para Perşembe).
  Metin ile sabitin aynı kalmasını test zorunlu tutar (`returns_policy_test.php`
  §1/§3: "3 business days", "calendar day" yok, "Friday…by Monday" yok).
- **"Satıcı ödenir" cümlesi kaldırıldı** (operatör, 6 Eyl 2026: *"verkäufer wird
  bezahlt yerine satıcının fonları sistemde tutulmayabilir"*). Süre dolunca
  "the seller is paid" demek havale siparişinde düpedüz **yanlıştı** — satıcı
  sevkiyattan önce ödenmişti; VESTRA hiç para tutmamıştı. Şimdi 9 dilde: *"any
  funds still held for the order are no longer held"* — escrow'da doğru, havalede
  boş küme olarak doğru.
- **Talep akışı ("Open dispute") 5–6 Eyl 2026'da kuruldu** — `inc/claims.php`,
  tek karar noktası `vestra_claim_state()`. O tarihe kadar SSS beş sayfada olmayan
  bir düğmeyi tarif ediyordu ve `disputed` bayrağı hiçbir yerde yazılmıyordu.
  Operatör: **her siparişte** (iptal hariç), **sessiz** (katlanmış "I have a
  problem with this order" bağlantısı → sebepler → foto+açıklama). Açık talep
  escrow süpürücüsünü **ve** alıcının "teslim aldım" düğmesini durdurur; sonuç
  alıcıya mektupla + sipariş ipliğine kartla gider; satıcı talebi salt-okunur
  görür; `cron_claims.php` (07:10 sunucu saati = 14:10 UTC) 2 iş gününü geçen açık talebi operatöre
  yazar. Test: `tests/claim_flow_test.php`.
- **de/fr/es/it'de `returns` dışı maddeler 4 Eylül'de güncellenmemişti**
  (6 Eyl 2026'da bulundu): `shipping/5` "48 saat", `disputes/0`/`/4` "5 iş günü",
  `disputes/2` "satıcı iade sunuyorsa iade mümkün" — KURAL 11'in tersi, dört dilde,
  iki gün. `returns_policy_test` yalnız `returns`'ü koruyordu;
  `faq_translation_test.php` artık bu maddelerde rakip süre izini de tutuyor.
- **Bu iş üç canlı çelişki ortaya çıkardı, üçü de düzeltildi:**
  1. SSS aynı soruya **üç** farklı cevap veriyordu — uyuşmazlık için "5 iş günü",
     nakliye hasarı için "48 saat", escrow için "2 iş günü". Hepsi 3 güne indi.
  2. **Escrow parayı pencerenin ortasında ödüyordu.** Otomatik serbest bırakma
     `vestra_business_days_after($dts, 2)` idi; Pazartesi teslimatta Çarşamba
     ödüyor, oysa alıcının hakkı Perşembe'ye kadar sürüyordu. Artık
     `max(2 iş günü, VESTRA_CLAIM_DAYS takvim günü)` — hafta sonu davranışı korunuyor,
     hak garanti altında.
  3. **`inc/legal/en.php` ölü dosyaydı** ve içinde `[US registered address]`,
     `[IRS EIN]` gibi doldurulmamış yer tutucular vardı. `vestra_legal()` İngilizce
     için erken döndüğü için hiç yüklenmiyordu; dispatcher bir gün "düzeltilse"
     site bu yer tutucuları basacaktı. İçindeki tek işe yarar madde (HGB §377
     muayene/kusur ihbarı) canlı Sözleşme'ye **3b** olarak taşındı, dosya silindi.
- **Sözleşme politikayı kapsıyor:** madde **3a** (iadeye kapalı + politikaya atıf)
  ve **3b** (muayene ve kusur ihbarı). Sepetteki onay kutusu zaten Sözleşme'yi
  kabul ettiriyor; o cümleye dokunulmadı çünkü `sprintf` üç yer tutucu taşıyor ve
  değiştirmek 7 dilin çevirisini birden İngilizceye düşürürdü.
- **SSS cevapları DÜZ METİN.** `faq.php` cevapları `nl2br(htmlspecialchars(...))`
  ile basıyor; bir cevaba HTML koyulursa kullanıcı **ham etiket** görür. Canlıda
  tam bu vardı (ödeme cevabındaki `<b>`), temizlendi ve test bekçilik ediyor.
- **Politika 8 DİLDE tam** (operatör, 4 Eyl 2026: *"evet tüm dillere çevir"*).
  18 maddenin tamamı de/fr/it/es/pt/ru/ar için `inc/faq/{lang}.php` içinde;
  pt/ru/ar dosyaları bu işle **ilk kez oluştu** (yalnız `returns` taşıyorlar,
  diğer kategoriler madde bazında İngilizceye düşüyor — `vestra_faq()` bunu
  zaten yapıyor). UI dizeleri de 8 dilde (1108 anahtar).
- **Ölçerken tuzak:** `vlang()` ilk çağrıda sabitleniyor. Sekiz dili TEK süreçte
  döngüye alırsanız sekizinin de "İngilizce" olduğunu ölçersiniz — ilk kontrolde
  tam bu oldu ve çeviriler bozukmuş gibi göründü. Test dil başına ayrı PHP
  süreci açar; `returns_policy_test.php` bölüm 7 her dilde her maddenin
  gerçekten çevrildiğini (İngilizce kalan madde = 0) zorunlu tutar. Eksik bir
  çeviri sessizce İngilizceye düşerdi ve kimse fark etmezdi.

**KURAL 12 — Dil seçimi: `?lang=` > çerez > tarayıcı dili > IP ülkesi > EN**
(operatör sorusu, 4 Eyl 2026: *"IP nerenin ise dilde oranın"*).
- **Sorunun cevabı: o gün İTİBARİYLE ÇALIŞMIYORDU.** `vlang()` yalnızca `?lang=`,
  `vlang` çerezi ve **`Accept-Language`** (tarayıcı/telefon dili) bakıyordu; IP hiç
  okunmuyordu. IP'den ülke tespiti kodda vardı (`vestra_ip_intel()`) ama yalnız
  ülke engelleme için, ve ülke listesi boşken hiç çağrılmıyordu.
- **Eklendi:** `vlang_from_ip()` → `vlang_country_lang($cc)`. Ülke→dil tablosu
  **elle** yazılı; hreflang bölge listesinden türetmek iki ülkeyi yanlış bağlardı
  (BE hem `en` hem `fr` altında, CH `de`/`fr`/`it` altında). Kararlar: **CH→de**
  (en büyük dil grubu), **BE→en** (Flaman çoğunluk, Felemenkçe sitede yok).
  Sitenin dili olmayan ülke (TR, JP, US…) → İngilizce.
- **IP neden EN SONDA:** `Accept-Language` kişinin **okuduğu** dili söyler, IP
  yalnızca **nerede olduğunu**. Dubai'deki bir Alman'a Arapça göstermek, açıkça
  bildirdiği tercihi coğrafyayla ezmek olurdu. IP yalnızca tarayıcı hiçbir şey
  söylemediğinde devreye girer. *Operatör tersini isterse tek satır: `vlang()`
  içinde sıra değişir — ama bu kayıt, kararın bilinçli olduğunu söylesin.*
- **Maliyet korumaları** (üçü de testli): CLI atlanır (cron ağa çıkmaz), **bot
  atlanır** (Accept-Language göndermeyenlerin çoğu bot ve her biri boşuna bir
  coğrafi sorgu demekti), zaman aşımı **1 sn**, sonuç IP başına **30 gün**
  önbellekli. Yani sorgu yalnızca ilk ziyarette ve yalnızca çerez/`?lang` yokken.
- **Canlıda ölçmek:** `seo-check.yml` → `test_ip=<IP>`. Sunucudan coğrafi API'yi
  çağırır, ülkeyi ve düşeceği dili yazar; tablo tarafı ağsız listelenir.
  Testi: `tests/lang_from_ip_test.php` (45 iddia — tablo, sıra, maliyet
  korumaları ve CLI'da ağ çağrısı olmadığı).

**Dil seçici: kapalıyken tek kod, tıklanınca açılır** (operatör, 4 Eyl 2026:
*"dil bölümünü sakla, estetik bir şekilde üstüne tıklandığında aç, yer kaplamasın"*).
- Sekiz kodu yan yana basmak üst çubukta ~150px yiyordu ve dil eklendikçe
  büyüyordu. Ölçüldü (Playwright): **kapalı 50×21 px**, menü 110×124 px, yatay
  taşma yok. Menü **iki sütunlu grid** — sekiz kısa kod alt alta gereksiz uzun
  bir sütun olurdu.
- İşaretleme **para birimi seçicisiyle aynı**: `<details>/<summary>`, aynı CSS
  kuralları (`.cursw,.langsw{…}`), aynı "dışarıya tıklayınca/Escape kapan" JS'i.
  İkisi üst çubukta yan yana duruyor; farklı davranan iki menü hem kullanıcıya
  hem bakıma bedel olurdu.
- **`vlang_switcher($class, $mode)`** — `menu` (varsayılan) ve `flat`. Düz kip
  hesap panelinde ve mobil çekmecede: oralarda yer sıkıntısı yok, açılır menü
  bir şey kazandırmaz.
- **Ana sayfa `inc/style.css` YÜKLEMİYOR**, kendi kopyasını taşıyor. İşaretleme
  artık ortak bileşenden geliyor ama stil orada da yazılı olmak zorunda. Bu iş
  sırasında ana sayfanın **kendi elle yazılmış** dil listesi olduğu görüldü ve
  o kopya çoktan ayrışmıştı: bağlantıları sorgu parametrelerini korumuyordu,
  yani `/faq?cat=returns` sayfasında dil değiştiren SSS'nin başına düşüyordu.
- Testi: `lang_from_ip_test.php` bölüm 8 (63 iddia toplam) — kapalı halin tek
  kod olduğu, sekizinin de menüde durduğu, `hreflang` ve sorgu parametrelerinin
  korunduğu, CSS/JS'in paylaşıldığı ve ana sayfada elle yazılmış liste
  kalmadığı. RTL: `tests/render/rtl-check.js` 0 gerileme.

**KURAL 21 — NBB iç çamaşırı kataloğu: operatör kararları, 10 Eyl 2026.**
Operatörün aynı oturumdaki cümleleri (sırasıyla): *"sadece nbb ürünlerini tüm
dilleri cevirip satis fiyatinin üstüne yüzde 50 kär koyarak satmani istiyorum"*
· *"vestraya yeni bir katalog ekleyerek yap ancak estetik olmali underwear
olarak"* · *"ayakkabi ve giysinin yanina gelecek"* · *"tüm dillere cevrilecek
ayni zamanda resmin üstünde türkce ifadeler varsa koyma"* · *"premium
yapmalisin"* · *"varyasyonlarida cevireceksin tüm dillere ve bedenleri ayni
sekilde yap"* · *"sadece toptan olmali"* · *"komple marka secildiginde en az
alim 300 eur olacak sekilde"* · *"siteye atmadan önce test olarak göster bana"*.

- **Kâr %100 değil %50 oldu.** 4 Eyl 2026'daki karar (satış = maliyet × 2) bu
  katalog için geçersiz; çarpan **1.5**. Oran artık sabit değil, parametre:
  `price|<yüzde>` (varsayılan 50) ve her kayda `eur_margin_pct` damgalanıyor.
  *Aynı olguyu ikinci bir moda yazmak, `desc`/`sizes` ve dört mektup gövdesinin
  verdiği dersin tekrarı olurdu.*
- **KAYNAK `/kategori/bayan-sutyen` DEĞİL.** Tarayıcı o yola **sabitlenmişti**;
  operatörün verdiği ürün (`nbb-1901-butt-up-silikon-klot`) bir **külot** ve o
  kategoride yok. Ölçüm: `/marka/nbb` → **47 ürün, hepsi NBB, sayfalama yok**.
  Elimizdeki 638 kayıtlık sütyen kümesi NBB'nin yalnızca **26**'sını taşıyordu.
  `kuloglu_ds='<ad>|<yol>'` ile ikinci kümeye açıldı; varsayılan boş bırakılınca
  eski dosya adları **birebir** korunuyor (o dosyalar sunucuda dolu).
- **Ölçülen NBB dağarcığı (47 ürün, `vocab`):** 14 kategori (sütyen 26, bikini-
  tanga 5, fantazi-gecelik 4, çorap 4+1, boxer 2, korse/atlet/slip/patik 1'er),
  **19 renk**, **33 beden**, **61 başlık kelimesi**. Mevcut sözlük yalnız sütyen
  için kurulmuştu: ÇORAP, DENYE, GECELİK, JİPON, PANTOLON, PATİK, BOXER gibi
  kelimeler **yok** — çünkü onlar Bayan Sütyen kategorisi dışından geliyor.
  *"Sadece NBB" demek "daha az iş" demek değil; kapsam daralırken çeşit arttı.*
- **Renk/beden alanları yine karışık:** `STANDART` renk listesinde duruyor
  (renk değil), ve tedarikçi kodları (`SİYAH-500`, `SAHRA-51`, `TEN-57`,
  `VİZON-86`, `BRONZ-38`) hem renk hem beden alanında geçiyor. 4 Eylül'de
  kaydedilen "Kuloğlu rengi beden alanına yazıyor" tuzağının aynısı, yeni
  kodlu hâliyle.
- **BEDEN ÇEVİRİSİ GERÇEK BİR BOŞLUK.** `vestra_sizes_label()` yalnız
  `<rakam>/pack|seri` kalıbını çeviriyor; `SERİ`, `PAKET`, `STANDART` gibi
  **çıplak kelimeler** ham Türkçe basılırdı. Ayrıca `build_batch` `desc` alanına
  `'Sizes: ' . <ham Türkçe>` yazıyor ve **`desc` hiçbir yerde `t()`'den
  geçmiyor**. Sayısal/harfli bedenler (75, 80/85, S-M, XL, A/B/C kap) evrensel,
  çevrilmez — sözlüğün kendi ilkesi bu.
- **ÜRÜN ADI da 8 dilde** (operatör kararı, 10 Eyl 2026, açıkça soruldu).
  `kuloglu-vocab.php`'nin kendi ölçümü *"bir ilanın `name`/`desc` alanı hiçbir
  yerde `t()`'den geçmiyor"* diyor — yani bugün çevrilmiş bir başlığın
  **basılacağı yer yok**. Ayakkabı ithalatında ad tek ve İngilizce bırakılmıştı;
  operatör bu katalog için **aksini** seçti. Gereken: ilanda dil bazlı ad alanı
  (`name_i18n`) ve onu **basan** yol — ürün sayfası, katalog kartı, sepet,
  sipariş satırı, fatura ve line sheet. *Alan eklemek yetmez: bu depoda
  "toplanan ama okunmayan alan" (KURAL 5j, platform künyesi) bir kez yaşandı.*
  Ad çevrilirken `desc` de aynı sorunu taşıyor — ikisi birlikte çözülmeli.
- **Sadece toptan:** `vestra_dropship_excluded_sections()`'a `underwear`
  eklendi (ayakkabıyla aynı mekanizma). Tedarikçide satış birimi **paket**
  (6'lı, 100'lü seri); tek parça diye bir şey yok.
- **En az alım 500 EUR = SEPETTEKİ NBB TOPLAMI, SABİT** (operatör, 10 Eyl 2026,
  aynı oturumda üç adımda yerleşti: *"komple marka secildiginde en az alim
  300 eur olacak sekilde"* → *"en az alimi 500 usd yap"* → **kur dalgalanması
  anlatılınca** → *"eur yap"* + *"degismesin"*). Kapsam okuması üç seçenek
  sunulup seçildi: **marka** (sepetteki NBB satırları), bölme geneli değil,
  sipariş toplamı değil. VESTRA'da bugün **sepet düzeyinde asgari tutar kavramı
  yok** — mevcut MOQ tek ilanın *adedi*, yani bu yeni bir kapı.
- **EUR olması kuru tamamen devre dışı bırakıyor ve karar bu yüzden verildi.**
  Bu katalogun her fiyatı zaten EUR (TRY maliyet × 1.5 → EUR), yani eşik ile
  sepet **aynı birimde**: karşılaştırma düz toplama, hiçbir çevrim yok.
  USD bir eşik, EUR cinsinden gerçek minimumun **kurla dalgalanması** demekti
  (500 USD bugün bir şey, üç ay sonra başka bir şey) ve beraberinde bir kur
  kaynağı, bir "kur yoksa ne olacak" dalı ve alıcıya iki rakam birden yazma
  yükü getiriyordu. Operatör bunu duyunca **EUR + değişmesin** dedi.
  *Kaldırılan karmaşıklık, yazılmayan koddan daha değerli:* FX kesintisinde
  kapının ne yapacağı diye bir soru artık **yok**.
- Kapı **sunucuda** olmalı — düğmeyi gizlemek kapı değildir (KURAL 4b'nin
  `/offer` dersi). Eksik tutar hem ürün sayfasında hem sepette **8 dilde**
  yazılmalı: neyin eksik olduğunu söylemeyen bir engel, alıcıya sepeti terk
  ettirir.
- **Rakam tek sabitte** (KURAL 6'nın escrow tavanı dersi): eşiği bir mektuba ya
  da sayfa metnine gömmek, beş gün boyunca müşteriye söylenenle sepetin kabul
  ettiğinin ayrı kalmasına yol açmıştı. Sayfa, sepet ve uyarı metni aynı
  sabitten okur.
- **Gösterim birimi ayrı bir şey:** alıcı sepeti kendi seçtiği para biriminde
  görebiliyor (`vestra_money`), ama eşik EUR ve **kayıt EUR**. Uyarı metni
  eşiği EUR yazmalı — gösterim birimine çevrilmiş bir eşik, "değişmesin"
  denen şeyi tam da ekranda değiştirirdi.
- **CANLIYA HİÇBİR ŞEY YAZILMADAN ÖNCE ÖNİZLEME** (*"siteye atmadan önce test
  olarak göster bana"*) — KURAL 18'in katalog hâli. Fotoğrafta Türkçe metin
  varsa o kare **yayına girmez**; tek fotoğrafı da elenen ilan yayımlanmaz.
- **Tedarikçi maliyeti herkese açık günlüğe basılmaz** (3 Eyl 2026 dersi).
  %50 kâr bilinirken satış fiyatını basmak maliyeti de ele verir; önizleme
  operatörün kendi sunucusunda, dizine kapalı ve bağlantısız bir adreste.

**KURAL 21 — CANLIYA YAZILDI (10 Eyl 2026, operatör: *"canliya yaz"*).**
47 üründen **42'si** `listings.json`'a indi, hepsi **`status=pending`** —
yani sunucuda duruyor, katalogda **görünmüyor**. Operatörün "önce göster"
şartı ile "canlıya yaz" talimatı böyle birlikte karşılandı: onay düğmesi
`Admin ▸ Products`'ta, önizleme operatörün kendi ekranı.

**Aynı gün AÇILDI** (operatör: *"tüm ürünleri ac.... underwear e koy nbb leri"*):
42 ilan `pending → approved`, `product-fixes/nbb-approve.json` +
`set-product.yml`. Cümlenin ikinci yarısı **yazma gerektirmedi** — 42'sinin
hepsi zaten `section=underwear` (bir önceki geri okumanın ölçtüğü değer,
varsayım değil) ve `set_product.php`'nin `$ALLOWED` listesinde `section`
**yok**. Yazamayacağı bir alanı düzeltme dosyasına koymak, hiç dokunmadığı
bir şeye "başarılı" diyen bir koşu üretirdi. Elenen 5 ürün **açılmadı**:
fotoğraf kuralı değişmedi.

| | |
|---|---:|
| Taranan NBB ürünü | 47 |
| Fotoğrafında Türkçe metin → **elendi** | 5 |
| **Yazılan ilan** | **42** |
| 9 dilde ad + açıklama | 42/42 |
| Bölme `underwear`, satıcı `Wholesale Underwear`, `ships_from=Turkey` | 42/42 |
| **Katalogda görünen (`approved`)** | **42/42** |

- **Elenen 5 fotoğraf** ve gerekçeleri `nbb_photo_rejected()`'ta (yorumda
  değil — elle eleme unutulur, KURAL 1h). 47 karenin **hepsi göz ile**
  incelendi (`kuloglu: sheet` kontakt sayfası); şüpheli dördü 480px'te tekrar.
  `9001` **geçti**: üzerindeki "NBB Lingerie®" Latin harfli ve **sattığımız
  markanın kendi logosu** — Türkçe ifade değil.
- **`1117` operatör kararı bekliyor:** ambalaj taramasında *"11-14 Yaş"*
  yazıyor, yani bu bir **çocuk** ürünü (Kidswear), bu bölmenin işi değil.
- **Ad/açıklama kelime kelime çevrilmedi.** `product-batches/nbb-vocab.php`:
  giysi türü her dilde TAM isim tamlaması + özellikler VİRGÜLLE ayrılmış liste
  ("Trägerloser BH, Mikrofaser, wattiert — 3520"). Sıfat sırası, sıfatın
  isimden sonra gelmesi, cinsiyet/belirlilik uyumu diye bir sorun kalmıyor ve
  48. ürün iki anahtara mal oluyor, dokuz yeni cümleye değil.
- **Renk/beden AYRIMI DEĞERE bakıyor, alanın adına değil.** NBB kaydında ikisi
  yer yer yer değişmiş (ölçüldü: 2404, 2900, 9266, 9293 — 2404'ün RENK alanında
  bir BEDEN var). Eski kurucunun kuralı "renk alanı boşsa bedeni renk say" idi
  ve bu vakaların hiçbirini yakalamıyordu.
- **`tiers` yazılıyor.** `vestra_unit_price()` tiers boşsa **0.0** dönüyor ve
  `order.php` 0 fiyatlı satırı atlıyor: tiers'siz bir ilan vitrinde durur ama
  **satın alınamaz**. Eski kurucu hiç yazmıyordu.
- **`mode='fixed'`** — `sale`'de `list` üstü çizili ESKİ fiyattır (KURAL 4),
  yani her ilan hiç var olmamış bir indirim ilan ederdi.

**Bu iş dört dersi yeniden öğretti; üçü bu deponun kendi kayıtlarının tekrarı:**

1. **`desc` ile `moq` çelişti — Balenciaga'nın (9 Eyl) birebir aynısı, üç gün
   sonra ve benim yeni kodumda.** Açıklama *"paket halinde, tek parça değil"*
   diyordu; sunucudaki gerçek kayıtta **yalnız çorap satırları paketli**
   (6/12/24), sütyen/gecelik/atlet `pack_qty=1`. Düzeltme: cümle **rakama
   bağlı** (`NBB_DESC_FMT` / `NBB_DESC_FMT_NOPACK`), kurucu gerçek paket
   adedini geçiriyor. **Uydurma bir paket adedi de "düzeltirdi" ve daha kötü
   olurdu** (KURAL 3); toptan şartını marka asgarisi tutuyor, tek ilanın adedi
   değil. *Yerel testim beni doğrulamıştı çünkü `pack_qty`'yi kendim
   uydurmuştum — yakalayan şey ÇIKTIYI OKUMAK oldu.*
2. **Actions maskesi ETİKETİ de yiyor — aynı gün ÜÇ kez.** Kontakt sayfasının
   parça numarası (`C0022`, `C0220`…), sonra `sha256=` satırı, sonra şifreli
   paketin numarası+`CHK=`si. Gövdeyi boşluklamak yetmiyor: **yükü ADRESLEYEN
   ve DOĞRULAYAN alanlar da boşluklanmalı.** Maskelenmiş gövde kontrol
   toplamında yakalanır; maskelenmiş **etiket sessizce kaybolur**, maskelenmiş
   **sağlama ise sağlam veriye "MISMATCH" dedirtir**.
3. **`raw.githubusercontent` BİR CDN ve kopyası BAYAT olabiliyor.** Paket
   düzeltmesi 12:37'de push edildi, 12:40'taki koşu **eski** `nbb-vocab.php`'yi
   çekti ve PHP kullanıcı fonksiyonlarına **fazla argümanı sessizce yok
   saydığı** için hiçbir hata çıkmadı — doğru kaynak, doğru çağrı, yanlış çıktı.
   `nbb_fetch_vocab()` artık çektiği şeyin **şeklini denetliyor**
   (`ReflectionFunction`: 3 parametre + NOPACK tablosu) ve tutmazsa **durup
   CDN'i adıyla söylüyor**. *Ağdan çektiğin bağımlılığın SÜRÜMÜNÜ doğrula.*
4. **Yazmayı doğrulayan sonda `vestra_products()` okuyordu** — o
   `vestra_live_listings()`'ten geçiyor ve **yalnız `approved`** döndürüyor,
   yani taze bir parti orada **0** görünür ve "0", *"yazılmadı"* ile
   *"yazıldı, onay bekliyor"* arasında hiçbir ayrım yapmaz.
   `inspect-products.yml` → **`raw_scan=true`** ham `vestra_listings()` okuyor:
   durum dağılımı, bölme dağılımı, her satırda tiers var mı, kaç satır
   gerçekten 9 dil taşıyor. **Kendi eklediğim blok ilk koşuda `$brand`/`$cat`
   yazdı, oysa bu dosyanın değişkenleri `$bf`/`$cf`** — ikisi de tanımsız,
   PHP'de `null !== ''` DOĞRU, ve süzgeç bütün katalogu eledi: *"722 kayıt,
   filtreye uyan 0"*. Yani tam da önlemek için yazdığım hata, aynı blokta.
   Yakalanma sebebi **iki sayının çelişmesi** (722 ham = add-products'ın
   dediği, ama süzgeç 0) — *çelişkiyi çözmeden hiçbirine inanma.*

**Açık kalan, operatör kararı bekleyen:**
- **`1117`** çocuk ürünü (11-14 yaş) — bu bölmede mi kalmalı?
- **`2404`** beş NEON rengi paletin `Other` yakalayıcısına düşüyor, yani alıcı
  beş renk yerine **tek bir çok renkli nokta** görüyor. Palet kararı eski
  (nadir ton başına yeni swatch açılmıyor); bu ilanda bedeli görünür oluyor.
- **`730`** (erkek slip 3'lü) kaydında **hiç renk yok** — tedarikçi vermemiş.
  Fotoğrafta mavi/beyaz/bordo görünüyor ama **fotoğraftan renk uydurulmadı**.
- **`nbb-9001` bir ara `approved` durumundaydı** (geri okumada 41 pending + 1
  approved). `pending`'e çekildi ve son durum **42/42 pending**; ama **sebebi
  bulunamadı** — `add-products.yml`'deki geçiş döngünün içinde ve doğru yerde.
  Uydurma bir açıklama yazmaktansa çözülmemiş olarak kaydediliyor.
- **Ürün başına TEK fotoğraf var.** Yeni bir kare gelmedikçe elenen 5 ilan
  yayımlanamaz; ve geçen 42 ilanın hiçbirinde ikinci açı yok.

**Yapılan diğer kablolama:**
- `/b2b/intimates` — bölmenin SEO sayfası. **`underwear` DEĞİL**, çünkü
  "Underwear" zaten bir taksonomi yaprağı ve `vestra_seo_resolve()` kategoriye
  ÖNCE bakıyor: aynı slug verilseydi o adresin ne gösterdiği **stoka bağlı**
  olurdu (bugün bölme, o yaprağa bir ilan girince kategori). Etiket yine
  "Underwear". Taksonomi grubu (`Underwear & Socks`) ayakkabıdaki birleşimin
  aynısını aldı; kural artık grup→bölme tablosu, gövdeye gömülü bir bayrak değil.
- `vestra_sizes_label()` **"One size"** çeviriyor (NBB çoraplarının bedeni
  `STANDART`). Rakamlar ve harf merdiveni bilerek çevrilmiyor. Kalıp **dar**:
  yalnız tek başına duran değer.
- `add-products.yml` ve `scripts/set_product.php` artık `name_i18n`/`desc_i18n`
  taşıyor ve **`vlang_list()`'e karşı doğruluyor**; ikisi de `inc/i18n.php`'yi
  **açıkça** require ediyor (KURAL 15 — kardeş bir dosyanın require'ına
  yaslanma; `function_exists` ile geçiştirmek daha kötü olurdu, çünkü dosya
  yüklenmemişse doğrulama SESSİZCE atlanır).
- `build_batch` ve `export_enc` artık **tek satır kaynağından** okuyor
  (`kuloglu_rows_for`). Bir süre ayrışmışlardı: operatörün gördüğü önizleme
  yeni kurucudan, sunucuya inen paket ESKİ kurucudan geliyordu — KURAL 5f/5n'in
  "iki kesim yolu ayrışır ve ayrışma belgede görünür" dersinin katalog hâli.

**KURAL 21b — Alıcı BEDEN seçiyor; kapı sunucuda, ölçüt ilanın kendi verisi**
(operatör, 10 Eyl 2026: *"ayrica nbb ürünlerinin beden secimlerinide koy"*).
- O güne kadar sitede **toptan tarafında beden seçimi hiç yoktu**: `sizes`
  yalnızca "Size mix" satırında basılıyordu, `vestra_size_options()` ise
  **sadece dropship** yolundan çağrılıyordu — ve iç çamaşırı dropship'e kapalı
  (KURAL 21), yani o ayrıştırıcı bu 42 ilanın dizgelerini **hiç görmemişti**.
- **Ölçüm önce:** `inspect-products.yml` → `raw_scan` artık beden dizgelerinin
  **tam dağılımını** basıyor. 42 ilanda **22 farklı dizge** çıktı ve mevcut
  ayrıştırıcı ikisini birden bozuyordu:
  - `75 B · 75 C · 80 B · 80 C · 85 B · 85 C · 90 B · 90 C` → **`[75, 80, 85, 90]`**.
    Kap harfi merdivende olmadığı için `vestra_size_norm()` onu **sessizce
    atıyordu**: 8 seçenekli bir sütyen 4 seçeneğe iniyor, alıcı B ile C arasında
    seçim yapamıyordu.
  - `S · L · XL · XXL · 3/pack` → **`[S, L, XL, XXL, 3]`**. `/` ayıraç
    sınıfında olduğu için `3/pack` önce `3` + `pack` oluyor, `3` de sayısal
    beden gibi normalize ediliyordu — alıcı o ilanda **hiç olmayan** bir bedeni
    seçebilirdi. `2 · 3 · 4 · 5 · 6/pack` aynı şekilde `6`'yı beden yapıyordu.
- **Seçici, karışımı SABİT OLMAYAN ilanda çıkıyor** (`vestra_sizes_selectable`):
  paket eki (`… /pack`, `… /seri`) ya da açık dağılım (`S×1 · M×3 · L×3`) varsa
  **seçim yok** — ikisinde de karışım ilanın kendisinde yazılı ve seçtirmek,
  ilan edilen paketin içeriğiyle çelişen bir sipariş üretirdi (KURAL 4b'nin
  MOQ/paket adımı dersi). Tek bedende de kutu çıkmıyor. **Canlıda ölçüldü**
  (`raw_scan`, deploy `2b2f8ef` sonrası): *"beden secici: **30** ilanda VAR |
  **6** paket/seri (sabit) | **6** tek beden"*. Aynı satır deploy'un yeni
  fonksiyonu gerçekten indirdiğini de kanıtlıyor — inmemişse sonda sıfır
  demek yerine **"deploy inmemiş"** diyor.
- **Bölme opt-in** (`vestra_size_pick_sections()` = `['underwear']`). Giyim
  kataloğunun neredeyse tamamı açık seri satıyor, yani şekil kuralı zaten
  eleyecekti; ama 600+ ilanın satın alma akışını sessizce değiştirmek
  istenenin dışındaydı. Yeni bölme = **bir satır**.
- **Kapı SUNUCUDA.** Sepet localStorage'da; elle düzenlenmiş bir sepet ilanda
  olmayan bir beden taşıyabilir. `/order` aynı fonksiyonu çağırıp kesişim
  alıyor. Canlı ölçüm (yerel kum havuzu, onaylı alıcı oturumu): beden yok →
  `?err=sizes`, uydurma `XXXL` → `?err=sizes`, geçerli seçim → geçiyor ve
  sıradaki **marka asgarisi** kapısına takılıyor (€500, KURAL 21), giyim
  ilanı bedensiz → **eskisi gibi** sipariş oluyor.
- **Seçim sipariş kaydına giriyor:** `Sizes — SKU: S, M.` parçası, `Colours`'un
  yanına. Sipariş tablosunda yeni **Sizes** sütunu, sipariş PDF'inde ve fatura
  toplama listesinde rengin yanında. PDF'te **tek alt satır**: ayrı bir blok
  yazsaydım satır yüksekliği hesabı yalnız bir blok sayıyor ve ikincisi bir
  sonraki satırın üzerine binerdi (yükseklik koşulu da `colors`'tan `$sub`'a
  çevrildi, yoksa rengi olmayıp bedeni olan satır taşardı).
- **Bu iş, YILLARDIR CANLI olan bir hatayı açığa çıkardı:**
  `vestra_order_notes_colors()` kalıbı `'/^Colours — …/'` idi, oysa `order.php`
  notları **her zaman** `Payment: …` ile açıyor (çoğu zaman `Deliver to: …` de
  araya giriyor). Yani kalıp **hiçbir gerçek siparişe uymuyordu** ve
  `vestra_order_lines()` her zaman **boş** bir renk haritası döndürüyordu:
  alıcının seçtiği renkler CSV'ye yazılıyor, ama sipariş tablosunda, sipariş
  PDF'inde ve fatura satırında **hiç görünmüyordu**. Ölçüldü: gerçek bir not
  dizgesiyle `[]`, yalnızca "Colours" ile BAŞLAYAN kurgusal bir dizgeyle
  çalışıyordu. Beden aynı yoldan geçtiği için **önce bu düzeltilmeliydi** —
  yoksa yeni alan da "yazılan ama hiç okunmayan" olurdu (KURAL 5j'nin sipariş
  hâli). Kalıp artık başa bağlı değil ve parça metinden temizleniyor.
- 5 yeni metin **9 dilde**. `.sizechip` renk noktası taşımadığı için `.colorchip`
  dolgusunu eşitliyor ve rakamları `tabular-nums` ile hizalıyor.
- **Teklif tarafına EKLENMEDİ**, bilerek: NBB ilanları `mode='fixed'` ve teklif
  kapalı, ayrıca teklif kaydı (`offers.csv`) beden sütunu taşımıyor — yarım
  bağlanmış bir alan, değeri düşen bir kutu olurdu.
- Test: `tests/size_pick_test.php` (97 iddia). Beden dizgeleri **uydurulmadı**,
  22'sinin hepsi canlıdan alındı — bu depoda kendi uydurduğum `pack_qty`
  değerleriyle yeşil kalan bir test canlıda yanlış metin üretmişti. Düşebildiği
  doğrulandı: paket stripi kaldırılınca **4 kırmızı**, bant+kap dalı kapanınca
  **1**, not kalıbı eski hâline döndürülünce **6**, showroom listesi boşalınca
  **1**. *İlk falsifikasyon denememde perl kaçışım hiç eşleşmemişti ve "iddia
  düşmüyor" sanmıştım — değiştirmenin GERÇEKTEN uygulandığını doğrula.*
- **Yerelde çizdirildi** (`php -S` + onaylı alıcı oturumu, kaynak okumak ölçüm
  değil): NBB'de 4 çip, sütyende **8 çip (kap harfleriyle)**, çorapta
  (`One size · 12/pack`) kutu **yok**, giyimde kutu **yok**, Almanca
  *"Größen wählen"* basılıyor, PHP uyarısı **0**.

**KURAL 21b (devamı) — RENK de seçilebilir; bir KISIT alanı ANAHTAR gibi
kullanılıyordu** (operatör, 10 Eyl 2026: *"underwear varyasyonlarinda tek
varyasyon secilebilir biri secilirken yoksa anlami kalmaz"*).
- **Ölçüldü** (yerel, onaylı alıcı oturumu — girişsiz çekmek fiyat kapısını
  ölçerdi): 146 iç çamaşırı ilanında **beden seçici çiziliyor, renk seçici
  HİÇ çizilmiyor**. Alıcı renkleri spec alanındaki noktalar olarak görüyor ama
  sipariş ederken seçemiyor; tek başına seçilen beden satıcıya hangi rengi
  göndereceğini söylemiyor. Operatörün cümlesi tam bu.
- **Sebep, `min_colors`'ın anahtar sanılmasıydı.** O alan *"en az kaç renk
  seçilmeli"* demek — bir **sınır**, varlık bayrağı değil. `product.php` renk
  kutusunu `!empty($p['min_colors'])` ile açıyordu; `ku_build_rows` bu alanı
  hiç yazmıyor, dolayısıyla **"minimum yok" ile "seçim yok" aynı sanıldı.**
  *Bir kısıt alanını anahtar olarak kullanmak, kısıtı olmayan her kaydı
  özelliksiz bırakır.*
- **Neden veri düzeltmesi değil kod düzeltmesi:** 146 ilana `min_colors=1`
  yazmak da çalışırdı ve bu depoda alışılmış yol (`set-product.yml`) — ama
  karışıklığı sürdürürdü: bir sonraki katalog yine sahte bir minimum yazmayı
  *hatırlamak* zorunda kalırdı, ve "kuralın hatırlanmaya bırakılması yetmiyor".
  Ayrıca `min_colors` yazmak `size_step>1` olan ilanları sessizce **adet-başına-
  renk** kipine çevirirdi (bugün hiçbirinde tetiklenmiyor — ama bu bir tesadüf,
  kural değil).
- Tek karar noktası `vestra_colors_selectable()`; ürün sayfası **ve** `/order`
  aynı fonksiyonu çağırıyor (kutuyu çizmemek kapı değildir). Açılmadığı üç hâl
  bedendekilerin aynısı: **tek renk** (seçim değil, bilgi), **bölme opt-in
  değil** (`vestra_color_pick_sections()` = `['underwear']` — 680 giyim/ayakkabı
  ilanının satın alma akışını sessizce değiştirmek istenenin dışında), ve
  **`min_colors` yazılı ilanlar eski yolda** (davranışları birebir korunuyor).
  Beden listesiyle **aynı fonksiyon paylaşılmadı**: ortak liste, yarın bir
  bölmede beden seçimini açmayı renk seçimini de sessizce açmaya çevirirdi.
- **Kapı sunucuda ölçüldü** (`/order`, POST): beden var + renk yok →
  `?err=colors`; renk var + beden yok → `?err=sizes`; ilanda **olmayan** bir
  renk (`Purple`) → `?err=colors` (kesişim yakalıyor); ikisi de var → sipariş
  geçiyor. **Kontrol ilanı** (giyim, renkli, `min_colors` yok) → eskisi gibi
  geçiyor, akışı değişmedi.
- **Çizdirildi:** düğme başlangıçta kapalı, **yalnız beden seçilince hâlâ
  kapalı**, renk de seçilince açılıyor — operatörün "tek varyasyon anlamsız"
  cümlesinin karşılığı bu. Masaüstü/mobil/Arapça, PHP uyarısı 0.
- Yeni metin (`Choose at least one colour.`) **8 sözlüğe birden** eklendi,
  kardeşinin (`…one size.`) hemen yanına — ikisi birlikte okunsun diye.
- Test: `tests/color_pick_test.php` (18 iddia). **İki yönü de** tutuyor ve
  ikisinin de düştüğü doğrulandı: özellik kapatılınca **3 kırmızı**, bütün
  bölmelere açılınca **3 kırmızı**. İlk yazımda blok metnini birebir sabitleyen
  bir iddia koymuştum — `invoice_vat_test`'in bir kez ödediği bedelin aynısı
  (yazımı koruyan, ölçtüğünü korumayan iddia); olguya çevrildi.
- **Bitişikte bulunan, OPERATÖR KARARI bekleyen boşluk:** €500 marka asgarisi
  (KURAL 21) `vestra_brand_min_orders()`'ta **yalnız `nbb`** anahtarına bağlı.
  Bölme o gün tek markalıydı; bugün **Visatin (72) ve Q-EN (32) asgarisiz** —
  ölçüldü, tek bir €7,50'lik Q-EN atleti sipariş edilebiliyor. Bu, "sadece
  toptan olmalı" kararıyla çelişiyor ama düzeltmek fiyat kararı: ya iki markaya
  da €500, ya da asgariyi **bölme** düzeyine taşımak (KURAL 21'de üç seçenek
  sunulup **marka** seçilmişti). Kendiliğinden değiştirilmedi.

**KURAL 21c — Visatin ve Q-EN: aynı tedarikçiden iki marka daha** (operatör,
10 Eyl 2026: *"ayni siteden visatin ve Q-EN markali ürünleride cek ve ayni
sekili tüm dillere cevir ve varyasyonlari ile beraber koy underwaere"*).

- **Q-EN'in marka sayfası var, Visatin'in YOK.** `/marka/q-en` → 38 ürün.
  `/marka/visatin` **404**, marka dizininde adı hiç geçmiyor, sunucu tarafında
  arama JS ile çiziliyor (0 sonuç). Ürünler var ama kategoride duruyor:
  `/kategori/fantazi-gecelik` içinde **76 Visatin**, sekiz başka üreticiyle
  karışık. Hangi kategori olduğu **tahmin edilmedi** — aday kategorilerde
  `visatin-` önekinin kaç kez geçtiği sayıldı.
  Tarayıcıya üçüncü bir alan eklendi: `kuloglu_ds='<ad>|<yol>|<slug-öneki>'`.
  Süzgeç **listeleme anında** çalışıyor (kuyruğa giren her URL sonradan bir
  istek demek), ama **sayfalamanın bittiğine HAM sayım karar veriyor**:
  süzülmüş sayı 0 diye durmak, üretici bir-iki sayfa atladığında listeyi
  sessizce yarım bırakırdı. Ölçüldü — Visatin sayfa 6-11'de hiç yok, 4 ve
  5'te var.
- **Sözlük ÜÇ ÜRETİCİYE ORTAK.** `nbb-vocab.php` →
  `product-batches/kuloglu-underwear-vocab.php`. İkinci ve üçüncü bir dosya,
  giysi türlerinin ve özelliklerin **9 dillik** tablosunu iki kez daha
  kopyalamak olurdu; "Nachthemd"i bir gün düzelten kişi üçünden yalnız birini
  düzeltirdi (`desc`/`sizes`, faturanın üç katmanı ve dört mektup gövdesi aynı
  dersi verdi). **Kelimeler ortak, yalnız ürün tablosu üretici başına.**
  Marka adı da metne gömülü değil, parametre: 18 cümleyi üç kez kopyalamak
  "NBB" yazan bir Visatin ilanı üretmenin en kolay yoluydu.
- **114 ürünün hiçbiri elle yazılmadı.** Sınıflandırma tedarikçinin **kendi
  Türkçe başlığından** kural tablosuyla çıkıyor (`KU_TITLE_TYPES` /
  `KU_TITLE_ATTRS`). Sebebi yalnızca hacim değil: Actions kütüğü "22"yi
  maskelediği için model numaralarının bir kısmı dökümden **doğru okunamıyor
  bile** (`[***]`, `21***`). Kural tablosu hem daha küçük hem yanlış
  kopyalanamaz. **Çözülemeyen başlık ATLANIR** — varsayılan bir tür koymak,
  tanımadığımız bir giysiye tanıdığımız bir ad vermek olurdu.
  Ölçüm: 32 gerçek başlığın **31'i** çözüldü. Tek ret doğru: `Q-EN 706 …
  BATTAL KALIN ASKI ÇİFT YÖNLÜ` başlığında **giysinin adı yok**. Tek elle
  yazılmış satır o; giysi **tahmin edilmedi**, aynı tedarikçinin aynı model
  numaralı kardeş ilanı *"… ÇİFT YÖNLÜ **ATLET**"* diyor.
- **Beş model numarası İKİ ürüne birden ait** (Q-EN 705/706/800 normal+BATTAL,
  Visatin 4060/4074 tek+6'lı paket). Aynı SKU iki ilanda dururken sipariş
  satırının renk/beden haritası (SKU ile anahtarlı) ikisini karıştırırdı.
  İki ayırıcı da **ürünün kendi verisinden**: `-B` tedarikçinin kendi yazımı
  (700-B, 707-B, 802-B slug'da böyle), `-N` kaydın kendi `pack_qty`'si.
  **Ek yalnızca çakışma varsa basılıyor** ve çakışma **dilimden önce, tüm küme
  üzerinde** sayılıyor: koşulsuz eklemek, ikizi olmayan Visatin geceliklerine
  tedarikçinin hiç yazmadığı bir sonek uydurmak olurdu (SKU alıcının baktığı
  üretici referansı). Kural bir gün yetmezse kurucu **duruyor** — sessizce bir
  ilanı ezmiyor.
- **"6 LI" paketlerde bedenler yalnız BAŞLIKTA.** O kayıtlarda varyant tablosu
  boş; `pack_qty` **6** ve başlık `M-L-XL` diyor — ikisi tutuyor (ölçüldü).
  Beden listesi başlıktan okunuyor, uydurulmuyor, ve `6/pack` eki KURAL 21b'nin
  **beden seçicisini kapatıyor**: asorti paketin karışımı sabit, seçtirmek ilan
  edilen paketin içeriğiyle çelişen bir sipariş üretirdi.
- **"ASORTİ" renk değil ama sessizce de düşmez.** Tedarikçi renk alanına
  yazıyor; palete zorlamak uydurma renk basmak olurdu (NBB 730 dersi), atmak
  ise karışık gelen paketi tek renkli göstermek. Ada **özellik** olarak
  giriyor, yalnız çözülmüş hiçbir renk yokken.
- **Kayıtlı çelişki, operatör kararı bekliyor:** Visatin ürün sayfasının MARKA
  alanı **"REAL PASSIONE"** diyor, BAŞLIK **"VİSATİN <model>"** diyor ve
  `/marka/visatin` yok. Yani tedarikçinin kendi kaydında Visatin tescilli bir
  marka değil, Real Passione'nin bir hattı görünümünde. Operatör *"visatin
  markalı"* dediği ve başlık da öyle dediği için VESTRA'da marka **Visatin**
  basılıyor.

**KURAL 21c — SONUÇ: 104 ilan CANLIDA ve KATALOGDA** (10 Eyl 2026). Önce
operatörün NBB katalogu için koyduğu şart uygulandı (*"siteye atmadan önce
test olarak göster bana"*): `build_batch` sunucuda `test_batch.json` üretti,
VESTRA'ya hiçbir şey yazılmadı, sonuç operatöre rapor edildi. Operatör isteği
**birebir tekrarlayınca** paketler yazıldı (`status=pending`, katalogda
görünmez), ardından operatör *"aprrove da bekleyen tüm ürünleri koabilirsin"*
deyince **104'ü de açıldı**.

| | Visatin | Q-EN |
|---|---:|---:|
| Taranan ürün | 76 | 38 |
| Fotoğrafında Türkçe → **elendi** | 4 | 6 |
| Fiyatı/modeli çözülemeyen | 0 | 0 |
| **Yazılan ilan** | **72** | **32** |

**Geri okuma (`inspect-products` → `raw_scan`, yazmadan sonra):** katalog
722 → **826**; `pending` **0** (yazma anında 104 idi, onaydan sonra
`approved=814`); bölme `underwear` **42 → 146**; 9 dilde ad+açıklama
**146/146**; `tiers` hepsinde var; satıcı `0cb79eb883f2a0fa` (Marca Online /
*Wholesale Underwear*) 146; `ships_from=Turkey`. Beden seçici **30 → 96**
ilan (12 Visatin 6'lı paketi doğru şekilde kapalı — KURAL 21b'nin sabit
karışım kuralı).

**Onay dosyasında ID uydurulmadı, DOĞRULATILDI.** Actions maskesi literal
"22"yi her yerde yediği için `visatin-2122` günlükte `visatin-21***` diye
basılıyor — yani onay listesine yazılacak 104 id'den biri **okunamıyordu**.
Çıkarım (2120 ile 2127 arasında, "22" içeren tek değer) dosyaya yazıldı ama
kanıt o değil: her satırda `expect:1` var ve kuru koşu **104/104 eşleşti**.
Yanlış bir tahmin hiçbir şeye eşleşir, `set_product.php` de **hiçbir şey
yazmaz**. *Okunamayan bir değeri tahmin etmek serbest; tahmini doğrulatmadan
yazmak değil.*

**21 Visatin ilanında BEDEN YOK ve bu bir ayrıştırıcı boşluğu DEĞİL** —
tedarikçinin kendi kaydından doğrulandı (`titles`, 76 satır): 17'sinde
varyant tablosu boş ve başlık (`… SATEN GECELİK BATTAL`) beden dizisi
taşımıyor, 4'ünde (11006/11009/11014/11015) beden alanında duran tek değer
bir **renk** (KIRMIZI/SİYAH) ve değere bakan ayrım onu renge taşıdı. Yani
alıcı bu 21 ilanda beden çipi görmeyecek çünkü tedarikçi beden vermemiş.
Fotoğraftan ya da "BATTAL" kelimesinden bir beden merdiveni **uydurulmadı**
(KURAL 3). *Sayının kendisi kusur gibi görünüyordu; kaynağa bakmadan
"düzeltmek" uydurma veri yazmak olurdu.*

- Visatin'in **12**'si 6'lı asorti paket (`moq=6`, `… · 6/pack`, beden seçici
  kapalı); geri kalanı tek ilan. Kategoriler: gecelik → `Sleepwear`,
  sabahlık → `Loungewear`.
- **Visatin'in 72 ilanının 68'inde RENK YOK** — tedarikçi vermemiş. Dördünde
  var (11006/11009/11014/11015) çünkü orada renk **beden alanına** yazılmış ve
  değere bakan ayrım onu yakaladı. Fotoğraftan renk **uydurulmadı** (NBB 730
  dersi). Alıcı bu ilanlarda renk çipi görmeyecek.
- Q-EN'de her ilan tam renk + beden taşıyor (345 varyant).

**Operatör kararı bekleyen üç şey:**
1. **Marka adı:** Visatin ürün sayfasının marka alanı **"REAL PASSIONE"**
   diyor, başlık **"VİSATİN"** diyor, `/marka/visatin` yok. VESTRA'da
   **Visatin** basılıyor — operatörün kendi cümlesi ve başlık öyle diyor.
2. **`%95 BAMBOO - %5 ELASTAN`** Q-EN kartlarının **hepsinde** var.
   "ELASTAN" Türkçe (ve Almanca) yazım, `%95` da Türkçe gösterim; kartın geri
   kalanı baştan sona İngilizce (CODE / SIZE / BIG SIZE / COLORS / BAMBOO /
   "IN PACK 3 PIECES"). Bunu "fotoğrafta Türkçe" saymak **32 Q-EN ilanının
   tamamını** eler. Elenmedi, karar operatörün.
3. **Elenen 10 ilanın başka fotoğrafı yok** (ürün başına tek kare). Tedarikçi
   yeni kare vermedikçe yayımlanamazlar.

**KURAL 21c — TEDARİKÇİ TARİFESİ ÜÇ AYRI YERDEN AKIYORDU** (10 Eyl 2026).
3 Eylül'de Pili Pérez'de bu bir kez kaydedildi (*"ilk iki koşu 335 satırın
fiyatını herkese açık günlüğe yazdı"*) ama kural yalnızca **şifreli pakete**
uygulanmıştı. Aynı gün Q-EN partisinde yeniden oldu ve sebep şuydu: sızıntı
paketin değil, **dökümlerin** içindeydi.

| Yer | Ne basıyordu |
|---|---|
| `crawl` → "2 örnek kayıt" | her varyantın ham `price_try`'ı |
| `status` → "2 iki eksenli kayıt" | aynısı |
| `fields` | `unit_price_try`, `pack_price_try`, varyant fiyat kümesi |
| `price` → "3 örnek kayıt" | `TRY birim=331.2 → EUR birim=8.82` + min/orta/maks |

İki koşunun günlüğü silindi (`34492757261`, `34495004354`). Düzeltme tek
gövdede: `kuloglu_mask_prices()` — `price|fiyat` içeren her anahtarı `***`
yapıyor, **şekli koruyor**. Örneğin kendisi değerli (çıkarımın doğru çalışıp
çalışmadığı ancak bakarak görülür); değersiz olan tek şey rakam.
`price` modu artık rakam yerine *"kaç kayıt fiyatlandı, kaçında fiyat
ÇÖZÜLEMEDİ"* yazıyor — asıl bilgi zaten oydu, fiyatsız kayıt ilan olamıyor.
**Kâr oranı CLAUDE.md'de yazılı olduğu için EUR satış fiyatı da maskeli:**
bilinen oranla satış fiyatı maliyeti doğrudan ele veriyor.
*Bir kuralı bir yerde uygulamak, kuralı uygulamak değildir; aynı olgunun
BASILDIĞI her yeri ara.*

**Ölçüm aracının kendi gürültüsü ölçümü yiyordu** (aynı gün, iki kez).
`probe_url` her sayfada aynı **96 satırlık genel menüyü** basıyordu: üç URL'lik
bir koşuda 288 satır, ve `get_job_logs` kuyruğu ~3,5 KB'de kestiği için asıl
sayımlar kuyruktan düşüyordu. `titles` ise ürün başına **altı satır**
basıyordu — 114 ürünlük kümede döküm hiç okunamıyor, yani çıkarımı doğrulamak
için yazılmış mod kendisi doğrulanamıyordu. İkisi de sıkıştırıldı (kategori
listesi yalnız istendiğinde, `titles` ürün başına tek satır). *rtl-check'in
dersinin aynısı: aracın kendi gürültüsünü elemeden rapor okuma.*

**Showroom başlığında satıcının KAYITLI ÜLKESİ — yalnız Marca Online'da gizli**
(operatör, 10 Eyl 2026: *"Basics · Turkey marca online dan bunu cikar"*;
kapsam soruldu, **"yalnız Marca Online"** seçildi).
- Satır `showroom.php`'de: `42 live listings · <ilk 4 kategori> · <ülke> ·
  Member since <yıl>`. Operatörün gördüğü "Basics · Turkey" bu satırın kuyruğu.
- **`ships_from` DEĞİL.** İkisi ayrı olgu, ayrı karar: `ships_from=Turkey` 42
  ilanın kartında ve ürün sayfasında bayrağıyla **duruyor** çünkü alıcının
  gümrük/teslim için okuduğu satır o ve KURAL 3 onu zorunlu tutuyor. Gizlenen
  şey satıcının **kayıt ülkesi**.
- Ölçüt **hesap ID'si** (`0cb79eb883f2a0fa`, sunucudan ölçüldü — 42 ilanın
  42'si), şirket adı değil: ad bir metin, kimlik değil, ve ada göre eşleşme bu
  depoda mango/zara dersini bir kez verdi. Hesap bayrağı
  `showroom_hide_country` kodun varsayılanını **ezer** (KURAL 2f'nin
  `doc_grace_exempt` deseni).
- **Yan bulgu, CLAUDE.md'nin kendi notu YANLIŞTI:** KURAL 2g'nin "Marca Online"
  istisnası *"`company` alanı hiçbir müşteri ekranında tek başına
  görünmüyor"* diyordu. `inc/invoice.php:430` bunun aksini zaten yazıyor ve
  kod haklı: **`showroom.php` `company`'yi H1 olarak basıyor.** Yani alıcı
  ürün sayfasında satıcıyı **"Wholesale Underwear"** görüyor, *Showroom →*
  bağlantısına basınca **"Marca Online"** başlıklı bir sayfaya düşüyor. Aynı
  satıcı, iki ad. Operatöre soruldu, **başlık bu turda değiştirilmedi** —
  yalnız ülke çıkarıldı; başlığı `seller` adına çevirmek ayrı bir karar ve
  operatör kararı bekliyor.

**KURAL 21d — Marca Online'ın ilanlarında GÖNDERİM YERİ satırı basılmaz**
(operatör, 10 Eyl 2026: *"underwear ürünlerini türkiyeden gönderiliyor ibaresini
kaldır marca online saticisida belli olmasin türkiyeden geldigi"*).

- **Bu, bir gün önceki kararın GERİ ALINMASI.** Yukarıdaki showroom notu
  *"`ships_from=Turkey` … DURUYOR, çünkü alıcının gümrük/teslim için okuduğu
  satır o"* diyordu; artık ikisi de gizli. Eski not olduğu gibi bırakılmadı —
  birbirini tutmayan iki kayıt hangisinin geçerli olduğunu okunamaz yapar.
- **ALAN SİLİNMEZ, SATIR BASILMAZ — işin tamamı bu ayrımda.**
  `vestra_ships_from()` boş alanda platform varsayılanı **`EU`** dönüyor, yani
  kayıttan `Turkey`'i silmek satırı kaldırmaz, yerine **"Ships from EU" YAZAR**:
  alıcının gümrük için okuduğu satırda doğru bir ifadeyi yanlış bir ifadeyle
  değiştirmek. "Kaldır" talimatının yaptığı şey bu değil. Kayıt yerinde duruyor
  (panel, fatura ve sevkiyat tarafı okumaya devam ediyor), yalnızca vitrin
  susuyor.
- **Ölçüt SATICI, bölme değil** (`vestra_hides_ships_from()`, hesap ID'siyle —
  `showroom_hide_country`'nin aynı deseni; ad bir metin, kimlik değil). Bölmeye
  bağlansaydı yarın gelen İspanyol bir iç çamaşırı tedarikçisinin **gerçek**
  çıkış yeri de sessizce silinirdi. Hesap bayrağı `hide_ships_from` kodun
  varsayılanını ezer.
- **Satırı basan HER müşteri yolu karara soruyor:** ürün sayfası, katalog kartı
  (ayırıcı `·` da düşüyor, yoksa kart "MOQ 6 pc ·" diye yarım biterdi), dropship
  sayfası (bugün oraya bu ilanlar düşmüyor ama ölçüt bölme değil satıcı) ve
  **günlük journal yazısı** — sonuncusu atlanırsa vitrinde susan satır yazının
  içinden geri gelirdi (KURAL 8'in "panelde gizlenip mektupta yazılan ad" dersi).
  **Operatör paneli TERSİ:** gerçeği basmaya devam ediyor ve yanına
  *"· alıcıda gizli"* rozeti koyuyor — yoksa panel "Turkey" derken müşteri
  hiçbir şey görür ve ikisinin aynı olduğu sanılırdı.
- **Ölçüm tuzağına DÜŞTÜM ve kontrol grubu kurtardı.** İlk ölçümde ürün
  sayfasını **girişsiz** çektim: hem gizli hem kontrol ürünü "0" verdi, çünkü o
  blok **fiyat kapısının** arkasında — yani kapıyı ölçmüştüm, dalı değil
  (KURAL 4b'nin birebir aynı tuzağı). Onaylı bir alıcı oturumuyla tekrar
  ölçüldü: `calc` bloğu **iki üründe de** var, kontrol ürünü (başka satıcı,
  **aynı ülke**) *"Ships from Turkey"* **yazıyor**, Marca Online ürünü **hiçbir
  şey** yazmıyor. Müşteri yüzeylerinin taraması: ürün sayfası, `/shop?section=
  underwear`, `/b2b/sleepwear`, `/wholesale/visatin`, `/price-list`,
  `/catalog-csv` → **"Turkey" geçişi 0**, PHP uyarısı 0.
- **Söylenen açık maliyet:** AB'li alıcı bu satırı gümrük ve teslim süresi için
  okuyor; satır olmayınca malın AB dışından geldiğini sipariş anında göremiyor
  (menşe zaten faturada, sevk belgesinde ve kolinin üstünde görünecek). Operatör
  bunu bilerek istedi; KURAL 3 bundan **etkilenmedi** — o kural değerin
  *uydurulmamasını* zorunlu tutuyor, gösterilmesini değil, ve değer kayıtta
  duruyor.
- Test: `tests/ships_from_hidden_test.php` (21 iddia). **İki yönü de** tutuyor:
  gizlenmesi gereken ve *görünmeye devam etmesi* gereken (aynı ülkeden gönderen
  başka bir satıcı) — tek yön yazılsaydı test yeşil kalır, ilgisiz bir
  tedarikçinin çıkış yeri sessizce silinirdi. Düşebildiği doğrulandı: özellik
  kapatılınca **2 kırmızı**, ölçüt bölmeye çevrilince **1**.
- **Yan düzeltme:** `product_i18n_test.php` 10 Eylül'deki sözlük yeniden
  adlandırmasından (`nbb-vocab.php` → `kuloglu-underwear-vocab.php`) beri
  **fatal** ile düşüyordu ve fark edilmemişti. Dosya/sabit adları güncellendi;
  ayrıca *"kullanılmayan tür/özellik yok"* iddiası yanlış **kümeye** bakıyordu —
  sözlük artık üç üreticiye ortak, yani "Nachthemd" NBB'nin elle yazılmış
  tablosunda geçmiyor. Ulaşılabilirlik artık `KU_PRODUCTS` + başlık
  sınıflandırıcısı üzerinden ölçülüyor (38 tür, 32 özellik, **ulaşılmayan 0**) ve
  ölü bir kelime enjekte edilerek hâlâ düşebildiği doğrulandı.
  *Bir dosyayı yeniden adlandırırken "bu ada kim daha bakıyor?" diye sor.*

## Operasyonel notlar

- Deploy `claude/wizardly-planck-7ylnmk` dalına **push ile** tetiklenir.
- **Siparişin USD karşılığı SİPARİŞ TARİHİNDEKİ kurla** (operatör, 7 Eyl 2026:
  *"siparişleri anında sipariş zamanındaki kur ile USD'ye çevirecek bir sistem
  koy admin paneline"*). Tek kaynak `inc/fx_orders.php`; damga
  `order_statuses.json[ref].fx` (`usd`, `date`, `source`). **İki kur var,
  karıştırma:** vitrin kuru (`vestra_fx`, bugünün) ile sipariş kuru (damga, bir
  kez yazılır, değişmez). Damga sipariş yazılırken düşer (`order.php` +
  `vestra_offer_order_ensure`, vitrin önbelleğinden, ağsız); eski siparişler için
  frankfurter'in **tarih aralığı** ucundan ECB geçmişi **tek istekle** çekilir
  (`data/fx_history.json`), admin Orders sekmesi açılınca kendiliğinden
  (`vestra_orders_fx_backfill`, 30 dk geri çekilme) ve `⟳ Fetch missing rates`
  düğmesiyle. **Damga yoksa "US$ —"** — bugünün kuruyla doldurulmaz (KURAL 3'ün
  kur hâli). Hafta sonu siparişi bir önceki yayım gününün kurunu ve **o tarihi**
  taşır. `Admin ▸ Orders`: listede ≈ US$ satırı, dosyada "In USD (rate on order
  date)", "Total volume in USD" kartı, `?dl=orders_usd` CSV. Canlı damgalama:
  `diag-live` → `fx_probe=true` eski siparişleri de damgalar (ref/tarih/kur
  yazar, kişi verisi yok). Test: `tests/order_fx_test.php`.
- **KURAL 13 — Günlük otomatik journal yazısı: HER GÜN ÇALIŞIR, HER GÜN
  YAYIMLAMAZ** (operatör, 7 Eyl 2026: *"her gün otomatik journal e paylaşım yap
  estetik ve ayrıntılı bilgi verici ve işe yarayan"*).
  - `inc/journal_auto.php` (saf kurucu) + `cron_journal.php` (sunucu crontab'ı
    **07:20 sunucu saati = 14:20 UTC**, `deploy-vestra.yml` idempotent kurar + kuru koşu kanaryası).
  - **Yazının tamamı canlı ilan kaydından türer**: hangi marka, kaç yeni model,
    hangi kategori, en düşük kademe fiyat, MOQ, bedenler, AYRI renk sayısı,
    gönderim yeri. Sunucuda dil modeli yok; olsa bile moda yorumu üretmek
    KURAL 3'ün tam tersi olurdu. Toptancının okumak istediği zaten yorum değil:
    *bu hafta ne geldi, kaça, kaç adetten.*
  - **Malzeme yoksa YAZI YOK** (eşik `VESTRA_JOURNAL_AUTO_MIN`). Gerekçe iki
    katmanlı: KURAL 9 ince içeriği yasaklıyor (alan adına zarar verir) ve
    KURAL 2c'nin dersi — "her sabah '0 bekleyen' yazan bir uyarı okunmamayı
    öğretir"; içeriksiz günlük yazı da journal'ı atlamayı öğretir. *"Her gün"
    ile "her gün YAYIN" aynı şey değil; bu bilinçli.*
  - **Pencere SON RAPORDA başlar**, sabit 7 gün değil. Canlı kuru koşu bunu
    yakaladı: sabit pencereyle 3 Eylül'de giren **335 ayakkabı** 4–10 Eylül
    arasındaki HER raporda yeniden duyurulacaktı — aynı yazının yedi kopyası,
    yani işin kaçınmak için kurulduğu şeyin ta kendisi. 7 gün artık yalnızca
    üst sınır (ilk rapor sonsuz geriye gitmesin) ve metindeki "son %d gün"
    gerçek pencereyi yazar.
  - **Dokuz dil, `t()` YOK**: `vlang()` süreçte ilk çağrıda sabitleniyor
    (KURAL 11'in ölçüm tuzağı), tek cron sürecinde dokuz dili `t()` ile gezmek
    dokuz İngilizce yazı üretir ve hiçbir şey bozuk görünmezdi. Cümle parçaları
    `vestra_journal_auto_strings()` tablosundan; rakamlar `%s` ile giriyor,
    metne gömülü değil.
  - **Şişirilmiş rakam da uydurulmuş rakamdır:** ilk taslakta renkler ilan
    başına TOPLANIYORDU — tek renkli 18 ilan "18 renk seçeneği" diye çıktı;
    ayrıca başlık "18 pieces" diyordu, ki toptancı bunu 18 **adet** okur.
    İkisi de yayından önce, ilk çıktı okunarak düzeltildi.
  - Aynı gün ikinci yazı yok; gövde **düz metin** (renderer markdown/HTML
    tanımıyor); kapak raporda geçen **gerçek** bir ürünün fotoğrafı; iç
    bağlantılar yalnızca `vestra_seo_resolve()` ile **açıldığı doğrulanan**
    `/b2b` ve `/wholesale` sayfalarına (KURAL 9). Yazma **geri okunuyor**.
  - Test: `tests/journal_auto_test.php` (47 iddia).
  - **10 Eyl 2026, operatör: *"journal icin yazdigim otomasyon calismamis"*.
    ÖLÇÜLDÜ: otomasyon ÇALIŞIYOR.** `cron_probe` — crontab satırı kurulu
    (`20 7 * * * … cron_journal.php`), **8 Eyl 14:20 UTC'de yazıyı YAYIMLADI**
    (*"New in stock: 335 new lines from Pili Pérez"*, 9 dil, kapak, canlı URL),
    **9 Eyl 14:20'de kuralı uygulayıp SUSTU** (`yeni ilan 0, eşik 3`), 10 Eyl
    koşusu ise **daha gerçekleşmemişti** (sunucu yereli 00:41 MST, iş 07:20 MST
    — 6,5 saat sonra). Yani "çalışmadı" diye görünen şey, kuralın kendisi.
  - **Sebep katalogda:** 671 ilanın **en yenisi 3 Eylül** (o gün 40 ilan).
    8 Eylül'den beri **hiç yeni ilan yok**, dolayısıyla malzeme yok. Aradaki
    işler (beden serileri, fiyat/MOQ, satıcı değişiklikleri) **DÜZENLEME**;
    kurucu yalnız **yeni ilan** sayıyor.
  - **Sondaya eklendi, çünkü tek satır iki ayrı durumu gizliyordu:**
    `yeni ilan 0` hem "hiç eklenmedi" hem "eklendi ama `added_at` yok" demek
    olabiliyordu (`added_at` yoksa ürün yeni sayılmaz) ve ikisi de operatöre
    "bozuk" görünür. Artık katalogun kendi tarihleri de basılıyor: kaç ilanda
    alan var/yok, en yeni ekleme günleri ve **bir sonraki koşunun ölçeceği
    pencere**. Ölçümde **3 ilanda `added_at` yok** — onlar hiçbir rapora
    giremez (küçük ama gerçek boşluk).
  - **Operatör kararı bekliyor:** günlük yayın isteniyorsa eşiği düşürmek
    çözmez (sayı 0, 1-2 değil). Gerçek seçenek, "malzeme"nin tanımını
    genişletmek — yeni ilanın yanına **fiyat/MOQ değişikliği, yeni renk, stok
    tazeleme** eklemek. Bu, müşterinin gördüğü şeyi değiştirdiği için
    bilinçli bir karar; KURAL 9 (ince içerik alan adına zarar verir) ve
    KURAL 2c (her gün "hiçbir şey" yazan bildirim okunmamayı öğretir)
    yüzünden kendiliğinden yapılmadı.
  - **Bağlantı bloğu artık ÜSTTE ve koleksiyonun KENDİSİ listenin başında**
    (operatör, 10 Eyl 2026: *"Perezin linkine ayakkabilari koy oraya
    yönlensin.... üst bölümde dursun"*). İki ayrı kusurdu: (1) blok **en
    sondaydı**, kapanış cümlesinin hemen üstünde — rapor markaları ve rakamları
    sayıyor ama *"peki bunları nerede göreceğim"* sorusunun cevabı yalnızca
    sonuna kadar okuyana ulaşıyordu; (2) blok **BÖLMEyi hiç vermiyordu**, yalnız
    marka ve kategori sayfalarını, yani 335 ayakkabılık rapor okuyucuyu
    "Pili Pérez"e ve "Sneakers"a yolluyor, **ayakkabı koleksiyonuna**
    yollamıyordu. `$sections` toplanıp **ilk** basılıyor (bölme → marka →
    kategori: genişten dara). Bölme bağlantısı da diğerleri gibi
    `vestra_seo_resolve()` ile kapılı (KURAL 9), her koşuda yeniden.
  - **Yayımlanmış raporu YENİDEN KURMA yolu:** `journal-seed.yml` →
    `rebuild_auto=true` (`rebuild_apply` varsayılan **false**). Aynı kurucuyu
    `$now = makalenin created damgası` ve `ignorePrevious=true` ile çağırır.
    O bayrak **yalnız burada** gerekli: pencere son otomatik rapordan başlıyor
    ve makalenin **kendisi** o rapor, yani vermezsek pencere sıfır genişlikte
    çıkıp kurucu atlıyor. **Günlük koşu bu bayrağı ASLA vermez** — testte
    iddia var; verseydi her sabah aynı ilanları yeniden duyururdu.
  - **Yalnız METİN taşınır: `slug`/`id`/`created`/`published`/`cover` makalenin
    kimliğidir.** Kurucu her çağrılışında **yeni bir slug üretiyor** ve mevcut
    slug alınmış olduğu için `-2` ekliyor; yükü olduğu gibi yazsaydık makalenin
    **adresi değişir**, yayımlanmış her bağlantı 404 olurdu. Yazma geri okunuyor
    ve slug/created/cover değişmişse iş **kırmızı** biter.
  - **Ölçüm karakterle, baytla değil.** "Blok üst yarıda mı" iddiası ilk koşuda
    `ru` ve `ja`'da düştü: `strpos` **bayt** sayıyor, `mb_strlen` **karakter**.
    Kod doğruydu, ölçünün birimi yanlıştı — `mb_strpos`/`mb_substr`.
  - **Canlı sonuç (10 Eyl 2026):** 8 Eylül raporu yerinde yeniden kuruldu.
    Bağlantılar EN'de **%62 → %21**, sekiz çevirinin **hepsi** üst yarıda,
    liste artık **Footwear — /b2b/footwear** ile açılıyor; slug ve created
    değişmedi. Test: `journal_auto_test.php §6` (54 iddia).
- **Journal makalesi KENDİ figürlerini taşır; konusu genel değilse havuz
  kullanılmaz** (10 Eyl 2026, Amazon yazısı). Dosya kuralı
  `uploads/journal/art-<slug>-{cover,1..N}.svg`; ad slug'a bağlı olduğu için
  başka yazıya sızmıyor, `credits.json`'da sanatçı olmadığı için genel havuza
  da girmiyor. Kredili havuz **genel moda fotoğrafçılığı**; devir yapısını
  anlatan bir yazının yanındaki askılık fotoğrafı dolgu gibi okunuyor — bu
  mekanizma tam bunun için var.
  - `<title>` = alt metni, `<desc>` = altyazı; **ikisi farklı olmalı** ve alt
    **140 karakterde kırpılıyor** (`vestra_journal_photo_desc`). İki başlığım
    cümle ortasından kesildi, kısaltıldı — kırpılmış bir alt, gören
    okuyucunun görmediği bir kusur.
  - **XML yorumunun içinde `--` GEÇERSİZ.** İki dosya bu yüzden
    well-formed değildi; tarayıcı yutuyor, `svg_meta` regex olduğu için o da
    yutuyordu, yani hata **hiçbir yerde görünmüyordu**. Yazdıktan sonra
    `ElementTree` ile ayrıştır.
  - **Çizimi OKUYARAK değil, ÇİZDİREREK doğrula.** Beş figür ekran görüntüsü
    alınıp iki kez düzeltildi: `⊕` işareti "tescilli marka" değil **"ekle"**
    diye okunuyordu, bir kilit ait olduğu şirket çerçevesinden **kopuk**
    duruyordu, ve bir ok başı çizgisiyle **buluşmuyordu**. Üçü de kaynakta
    doğru görünüyordu.
  - Kapak `viewBox 0 0 800 520` + `slice`; **21:9 kırpma yalnız y 88..431'i**
    bırakıyor, motif oraya sığmalı. Kırpmayı önizlerken bandı **doğru yere
    hizala** — ilk önizlememde y 0..342'yi gösterdim ve kapağı yanlış yerden
    yargıladım.
- **Konu hakkında hafızadan yazma; kural değişmiş olabilir** (10 Eyl 2026,
  Amazon yazısı). Operatör *"Amazon hesaplarinin satisi ve alisi… yasal olmasi
  icin ne yapilir"* dedi. Araştırıldı: Amazon'un Business Solutions
  Agreement'ı **24 Ağustos 2026'da** (yayın 29 Mayıs) değişip hakların
  **devrini/temlikini ve teminata verilmesini açıkça yasakladı** — yani
  "hesap satmak" artık gri alan değil. Hafızadan yazsaydım yazı **üç hafta
  eski** bir dünyayı anlatacaktı. Birincil kaynaklar (Seller Central, BSA PDF)
  bu ortamda **egress engelli**; birden çok ikincil kaynak karşılaştırıldı ve
  metinde tarih/atıf **yazılı**. Şu an ayakta olan alıcı şirketlerin **adı
  verilmedi** — doğrulanamıyor, ve yanlış bir isim listesi yazının en kolay
  çürüyen yeri olurdu (KURAL 3'ün yazı hâli).
- **Trafik sayacı GOOGLE'IN YARISINI ziyaretçi sayıyordu** (operatör, 8 Eyl 2026:
  *"US · Mountain View, böyle biri sürekli siteye giriyor her gün — gerçek bir
  kişi mi yoksa google bot mu? araştır ve IP'sine bak"*).
  - **Cevap: kişi değil, Google.** Kanıt Google'ın kendi tarifi — ters DNS →
    `crawl-66-249-*.googlebot.com`, sonra o adı ileri çözüp aynı IP'ye dönmesi.
    Mountain View'a çözülen adreslerin **hepsi** böyle doğrulandı; `security_log`'da
    o şehirden tek bir giriş/kayıt olayı bile yok.
  - **Ama sayaca giren Googlebot DEĞİL.** `vestra_is_bot()` 'bot|crawl|…' arıyordu;
    Googlebot doğru şekilde atlanıyordu. Google'ın diğer ajanları kimliğini dizgenin
    **sonundaki** parantezde yazıyor — `GoogleOther`, `Google-Read-Aloud`,
    `Google-Site-Verification` — ve hiçbirinde 'bot' geçmiyor. Gövdeleri gerçek
    Googlebot'unkiyle **birebir aynı** (Nexus 5X / Android 10). Ölçüm: Eylül
    kütüğünde **1.415**, Ağustos'ta **1.207** sayılan istek; 4 Eylül'de günün
    **1.394 "benzersiz ziyaretçisinin 620'si"** buydu. Gezdiği yerler `/journal?slug=…`
    ve `/wholesale/<marka>/<kategori>` — yani KURAL 9'un SEO sayfaları.
  - Süzgeç `\bgoogle-[a-z]|googleother|…` ile genişletildi: yarın çıkacak bir
    `Google-Xyz` de kendiliğinden kapsanıyor. **Ters yön testli:** iPhone'da Google
    uygulamasından gezen gerçek kişi `GSA/` taşıyor, `google-` değil — o sayılmaya
    devam ediyor (mango/zara dersi). `tests/bot_filter_test.php` (33 iddia; eski
    süzgece karşı 15 hata veriyor, yani düşebilen bir iddia).
  - **Geçmiş rakamlar düzelmez** — sayaç günlük dosyalara yazılmış durumda; düzeltme
    yalnızca bundan sonrasına işliyor. Panelde trafiğin düşmesi beklenen sonuç.
  - Teşhis: `diag-live.yml` → `visits_probe=who` (ya da `who:<şehir>`). **İki tuzağı
    yaşayarak öğrendi:** (1) erişim kütükleri `.gz`, ilk sürüm düz metin sanıp okudu
    ve "bu adreslerden istek YOK" dedi — ölçülmemiş bir şeyi ölçülmüş gibi gösteren
    satır; (2) UA'yı 95 karaktere kırpıp etiketi **kırpılmış** metinden hesapladı ve
    tabloda Googlebot'a "[SAYILIR]" yazdı, oysa süzgeç tam metne bakıp atlıyordu.
    *Aracın kendi kırpması ölçümü yalanlıyordu.*
- **Oversize tişörtün beden serisi iki basamak AŞAĞI kayar** (operatör, 9 Eyl 2026:
  *"Balenciaga dahil tüm Oversize tshirtlerin lot dağılımını 1 XXS, 3 XS, 3 S,
  2 M ve 1 L yap"*). `S×1 · M×3 · L×3 · XL×2 · XXL×1` → `XXS×1 · XS×3 · S×3 ·
  M×2 · L×1`. Aynı 1-3-3-2-1 eğrisi, aynı 10'luk paket; değişen yalnızca
  merdivendeki yer — oversize bir tişörtte nominal XS, normal kalıpta M gibi
  duruyor, yani düz kalıp serisiyle kurulan lot beden eğrisinin **yanlış
  yarısını** satıyor.
- **Kapsam kataloğu OKUYARAK bulundu, tahminle değil.** 170 tişörtün yalnızca
  **4'ünün adında** oversize geçiyor (2 Dolce & Gabbana, 2 DSQUARED2) ve hiçbir
  ilan boxy/relaxed/loose/large-fit demiyor. **Balenciaga'nın 10 tişörtünün
  adında kalıp bilgisi yok** — operatörün markayı ayrıca yazmasının sebebi bu.
  Küme = 4 + 10 = **14 ilan**; ad süzgeci tek başına 4'te kalırdı.
  *"Tüm X" denen bir işte önce X'in kaç tane olduğunu say.*
- Araç: `set-product.yml` + `product-fixes/oversize-tshirt-lot.json` (id ile
  `expect:1`, önce `dry_run=true`). Her ilan **kendi paket son ekini korudu**
  (Balenciaga `10/pack`, diğer 4'ü `10 pcs/pack`): istenen yalnızca dağılımdı ve
  iki yazım katalogda zaten yan yana duruyor. Yan not: `vestra_sizes_label()`
  yalnız `<rakam>/pack` kalıbını çeviriyor, yani `10 pcs/pack` yazan 122 ilanda
  "pack" kelimesi hiçbir dile çevrilmiyor — bu işten önce de böyleydi,
  dokunulmadı.
- **Yeni dizge ayrıştırıcıdan geçiyor:** `vestra_size_options()` iki yazım için de
  `[XXS, XS, S, M, L]` veriyor — `XXS×1` içindeki `X`, "ad × adet" kalıbının
  çarpım işaretiyle karışmıyor (yerelde ölçüldü). Paket 10 kaldığı için MOQ'lar
  (10 ve 20) hâlâ tam paket, `size_step` kuralı kımıldamıyor.
- **Geri okundu:** yazma "14 alan guncellendi" dedi, ama kanıt sunucudan tekrar
  çekilen liste — 170 tişörtün **tam 14'ü** yeni dağılımda (10 Balenciaga + 4 adı
  oversize olan), kalan 156 aynen duruyor. Arada bir doğrulama koşusu
  `dial tcp: i/o timeout` ile düştü: SSH hiç bağlanmadı, yani o koşu **veri
  hakkında hiçbir şey söylemiyor** — ikinci runner'la tekrarlandı (runner IP'si
  notunun aynısı).
- **AMA BEDEN SERİSİ İKİ YERDE YAZILI ve yalnızca biri değişti.** `sizes` alanı
  düzeldi, `desc` metni eski seriyi yazmaya devam etti:
  *"Original Balenciaga, model 612966TLVF1. S×1 · M×3 · L×3 · XL×2 · XXL×1 ·
  10/pack. EEA stock…"* — yani ürün sayfası aynı ürün için **spec satırında bir,
  bir paragraf altında başka** dağılım gösteriyordu. Operatör bunu gördü ve
  "bunu da verdiğim gibi yap" dedi; ben değişikliği uygulanmış sanıyordum çünkü
  **elimdeki aracın yazdığı alanı** doğrulamıştım, o olgunun **başka nereye
  yazıldığını** hiç sormamıştım. KURAL 5f'in üç katmanı ve KURAL 11'in SSS/sabit
  ayrışmasıyla aynı sınıf. *Bir alanı değiştirmeden önce "bu bilgi başka nerede
  yazılı?" diye sor; geri okuma yalnız yazdığın alanı doğruluyorsa yarım.*
- Sonda: `inspect-products.yml` → `fit_scan=true`. İki şey basıyor: (1) hangi
  ilan **kalıp beyan ediyor** (geniş: oversize/boxy/relaxed/loose/large fit —
  dar: slim/regular/classic), kanıt cümlesiyle; (2) `desc` ile `sizes`'ın
  **çeliştiği** her ilan, iki seri + tam açıklama metniyle. Düzeltme metni bu
  çıktıdan, **sunucunun kendi dizgesinden** kuruldu (şablondan yeniden yazılmadı).
  10 Balenciaga düzeltildi, geri okundu: markadaki çelişki 12 → **2**.
- **Katalog genelinde 35 ilanda bu çelişki vardı; 10'u benimdi, 25'i ÖNCEDEN
  duruyordu** (BALMAIN 14, Burberry 9, Balenciaga 2). Onlarda `sizes` bambaşka
  bir seri yazıyor (ör. `S×2 · M×2 · L×2 · XL×2 · XXL×2`) ya da hiç seri yok,
  açıklama ise her ilanda aynı `S×1 · M×3 · L×3 · XL×2 · XXL×1` kalıbını
  tekrarlıyor — yani açıklama **şablondan** basılmış ve ilanın kendi serisiyle
  hiç hizalanmamış. Operatör kararı bekliyor: düzeltmek `desc`'i her ilanın
  `sizes`'ından yeniden yazmak demek.
- **Kalıp taraması "hangileri gerçekten oversize" sorusunu KAPATTI:** 670 ürünün
  yalnızca **6 beyanı** geniş kalıba işaret ediyor ve altısı da zaten değiştirilen
  4 üründen geliyor (ikisi hem adda hem açıklamada eşleşti). **Katalogda başka
  hiçbir ürün oversize/boxy/relaxed demiyor**; 37 beyan ise tersini, dar kalıbı
  söylüyor. Yani kanıta dayanarak değiştirilecek başka model yok.
- **KATALOG SUSUYORSA KAYNAK İNTERNETTİR — ve kalıbı taşıyan STİL NUMARASIDIR**
  (operatör, 9 Eyl 2026: *"GG Print T-Shirt XJDEZ … bunlar oversize sanırım …
  internetten araştır oversize olanların hepsine uygula"*).
  - Katalogdaki **hiçbir Gucci ilanı kalıp belirtmiyor**, yani kendi verimiz bu
    soruyu cevaplayamıyor. Operatör de "sanırım" dedi. Hafızadan "Gucci'ler
    oversize'dır" demek KURAL 3'ün ta kendisi olurdu; araştırma yapıldı.
  - **Bulgu: `616036` Gucci'nin OVERSIZE tişört silueti.** Beş ayrı kumaş kodunda
    bağımsız kaynaklarla doğrulandı: `616036-XJDV9` *"in an oversize fit"*
    (BUYMA), `616036-XJDC-L` *"The North Face x Gucci **Oversize** T-Shirt"*
    (GOAT), `616036-XJDEZ-9791` *"Gucci x Doraemon **Oversized** T-shirt"*
    (Kickscrew + Reversible + Solesense). Uygulananlar: `guc-t01`, `guc-t02`,
    `guc-t03`, `guc-t04`, `guc-t07`.
  - **KUMAŞ KODU TEK BAŞINA KALIBI TAŞIMAZ.** `guc-t06`'nın kodu `XJD3X` ama tam
    kodu `548334-XJD3X-9095` — **farklı bir stil numarası**, SS22 Tiger, tarifi
    *"slightly loose fit"*. Oversize değil, **dokunulmadı**. Kumaş kodundan
    marka çıkarımı yapan bir kural bunu da değiştirirdi.
  - **Çözülemeyenler bırakıldı:** `XJD3W`, `XJDVI`, `XJDX31`, `XJDXM`, `XJDXN`
    hiçbir perakendecide indekslenmiyor, `guc-t12` ise iç SKU (`VS-GU-T01`).
    D&G/Casablanca/BALMAIN/Burberry kodları da aranabilir değil. *Bulamamak,
    "oversize değil" demek değil — bilmiyoruz demek, ve bilmediğimiz için
    dokunmadık.*
  - **Katalog adları güvenilmez:** `XJDEZ` bizde *"GG Print T-Shirt, White"*
    yazıyor ama gerçek ürün **Doraemon x Gucci** (White/Blue). Kalıp kararı bu
    yüzden **ada değil koda** dayandırıldı; arama yaparken de ad kullanılamaz.
  - `desc` ile `sizes` **birlikte** yazıldı (aşağıdaki Balenciaga dersi); paket
    son eki `10 pcs/pack` korundu. Kuru koşu 5 ilanda 10 alan dedi ve canlı
    `desc` metinleri çıkarımla birebir uyuştu.
- **Ama iki DSQUARED2 kendi içinde çelişiyor:** `dsq-101213` adı *"Graphic
  T-Shirt (Oversized)"*, açıklaması *"100% cotton, **regular fit**"*;
  `dsq-101237` adı *"Oversized Fit T-Shirt"*, açıklaması yine *"regular fit"*.
  İkisi de yeni seriyi **adına bakarak** aldı. Hangisinin doğru olduğunu satıcı
  bilir — bu yüzden açıklama **değiştirilmedi**, operatör kararına bırakıldı.
- **Fotoğraf ekleme (9 Eyl 2026, `blc-612966tlvf1`):** operatörün gönderdiği 3
  tedarikçi fotoğrafı (paket, hangtag, yıkama etiketi) ilana eklendi; mevcut iki
  kare **başta bırakıldı** (istenen eklemekti, kapağı değiştirmek değil).
  Telefon fotoğrafı **olduğu gibi yayına konmaz**: EXIF piksele uygulanıp
  (Orientation) tamamen atıldı — marka/model/firmware/çekim saati ve (boş da
  olsa) bir GPS bloğu taşıyordu; 4000×3000 / ~5 MB kareler 1600 px / ~200 KB'a
  indirildi.
- **`fetch-external-images.yml` MOD C doğrudan görsel adresini indiremiyordu:**
  yalnızca ürün SAYFASI bekliyor, Shopify JSON'u yoksa HTML'de ilk `<img>`
  arıyordu — JPEG baytlarında etiket bulamayınca "gorsel bulunamadi" diyordu.
  Artık yanıtın kendisi görselse doğrudan kullanıyor; **hem content-type hem
  sihirli baytlar** (sunucular JPEG'e `application/octet-stream` diyebiliyor,
  yalnız uzantıya bakmak da bir HTML hata sayfasını `.jpg` diye kaydettirir).
- **Fotoğraflardaki etiketler bir uyuşmazlık gösteriyor, operatör kararı
  bekliyor:** hangtag `CATEGORY-STYLE 612966 / FABRIC TLVF1 / COLOUR 1069`
  (siyah) — ilanla birebir. Ama paket ve etiket kareleri **612965**
  (`FABRIC TLVF1 / COLOUR 9014`, pembe grafiti baskılı beyaz tişört) yazıyor,
  yani ilanın kodundan **farklı bir stil numarası**. Beyaz model ayrı bir
  artikelse kendi ilanını hak ediyor; aynı ilanda durursa alıcı 612966 sipariş
  edip vitrinde 612965 görüyor.
- **KURAL 19 — Konuşmanın satıcısı değişince THREAD ID'si de değişir** (operatör,
  9 Eyl 2026: *"bu yazismanin ve ürünlerin saticisini tyrex olarak degistir"* —
  AlexaShop S.A.S ↔ GARAGE LE PARIS, Ralph Lauren polo + Lacoste TH6709).
  - Thread id **satıcıdan türeyen bir özet**: `vestra_msg_thread_id()` =
    `md5(alıcı|satıcı|ilan)`. Yalnız `seller_uid`'i değiştirip id'yi bırakmak
    **sessiz bir bozulma**: kayıt yeni satıcının panelinde görünür, ama bir
    sonraki mesajda `vestra_msg_send()` yeni id'yi hesaplar, bulamaz ve aynı
    taraflar için **İKİNCİ bir thread** açar — konuşma ikiye bölünür. Tek yazıcı
    `seller-products.yml` → `admin_mode=thread_seller`; id'yi yeniden hesaplar,
    yedekler, **geri okur**. Varsayılan kuru koşu.
  - **İki durum taşımayı DURDURUR** (ve biri durursa hiçbir şey yazılmaz):
    (1) eski satıcı o konuşmada **yazmışsa** — mesajlar `from` ile imzalı,
    thread'i başka firmaya vermek o cümleleri de ona yazdırmak olur;
    (2) aynı alıcı + yeni satıcı + ilan için **zaten thread varsa** — iki geçmişi
    birleştirmek yeniden adlandırma değil, ayrı bir karar.
  - `read`/`ping` haritaları **uid başına**; eskisininki devredilmez, silinir —
    TYREX gerçekten okumadı, okunmamış görsün.
  - **İlanın `seller` (görünen ad) alanı YOK, yalnız `seller_uid` var** — kayıt
    açılıp bakıldı. Olsaydı ikinci kopya olurdu ve aynı gün `desc`/`sizes`'ta
    yaşanan hatanın aynısı çıkardı: bir alanı değiştirip diğerini bırakmak.
  - **Kimliği ID'den değil KAYITTAN doğrula:** ilan id'si `lac-pima-tshirt` ama
    ürün **"Basic Crew Neck T-Shirt" (TH6709)**; `lac-pima-vneck` ayrı bir ilan
    (TH6710). Id yanıltıcı; operatörün panelde gördüğü ad kayıtla doğrulandı,
    yoksa yanlış ilanın satıcısı değiştirilecekti.
  - **Kapsam dışı bırakılan, operatör kararı bekliyor:** aynı polo ilanında
    **Ecokemet ↔ GARAGE LE PARIS** konuşması var (`84b19582d29558dd`, 5 mesaj,
    2'si GARAGE LE PARIS'in kendi yazısı, en son 9 Eyl 08:04). İlan TYREX'e
    geçtiği için o alıcı, artık TYREX'in olan bir ürün hakkında GARAGE LE
    PARIS'le konuşmaya devam ediyor. Taşımak yukarıdaki (1) kuralına takılıyor.

**KURAL 19 (devamı) — bur-8014004 (Burberry Polo — 8014004): AYNI boşluk, ikinci
vaka** (operatör, 23 Eyl 2026, hazır bir cevap mektubu verip: *"bu mesaji tyrex
international bv. Den yaz"* — Arelisshop ↔ VESTRA Support, 1 mesaj, 22 Eyl 2026
13:03, ürün Burberry Polo — 8014004).
- **Ölçüldü, tahmin edilmedi:** `inspect-products` → `name=8014004`: ilan
  `bur-8014004`, `seller_uid=(yok)`. Bir gün önceki D&G Crest Sweatshirt /
  GARAGE LE PARIS vakasının (`product-fixes/dgx-crest-sweatshirt-seller.json`)
  birebir aynı deseni — ilan seller_uid'siz geldiği için alıcının ürün sorusu
  açtığı thread'in kendi `seller_uid` alanı **VESTRA_SUPPORT_UID** ile
  damgalanmış. `msg_reply` kuru koşusu bunu doğrudan gösterdi: *"cevap: VESTRA
  Support adina"* — operatörün "TYREX adına yaz" dediği mektup, düzeltmeden
  gönderilseydi VESTRA Support imzasıyla giderdi.
- **Hedef hesap ADDAN değil KAYITTAN bulundu:** `diag-live` → `find_ref=tyrex`
  TEK eşleşme verdi — `d7824e27204c0177`, `company=TYREX INTERNATIONAL BV.`,
  `type=seller`, `status=active`, `kyb_status=approved`.
- **İKİ AYRI YAZMA gerekti ve İKİSİ DE ŞARTTI — biri diğerini karşılamıyor:**
  1. `product-fixes/bur-8014004-seller.json` + `set-product.yml`:
     `seller_uid '(yok)' -> 'd7824e27204c0177'` (dry-run → apply → geri okundu,
     `1 alan guncellendi`, zaman damgalı yedek).
  2. `seller-products.yml` → `admin_mode=thread_seller`,
     `move_to=d7824e27204c0177`, `move_threads=ilan:bur-8014004`: kuru koşu
     eski satıcının (vestra-support) bu konuşmada **0 mesaj** yazdığını ve
     çakışan bir thread olmadığını gösterdi (ikisi de KURAL 19'un "hepsi ya da
     hiçbiri" muhafazası), sonra `move_apply=true` ile uygulandı —
     `1/1 thread yeni saticida ve yeni id ile geri okundu` (eski id
     `1af241aa3859b128` → yeni id `08050df5338fa13f`), yedek alındı.
  **Yalnız (1)'i yapıp durmak yanlış olurdu:** ilanın `seller_uid`'i düzelse
  bile ZATEN AÇILMIŞ bir thread'in kendi `seller_uid`'i ayrı bir kayıt ve
  kendiliğinden değişmiyor — `msg_reply` (2) uygulanmadan hâlâ "VESTRA Support
  adina" diyordu, ölçülerek doğrulandı.
- **Göndermeden ÖNCE üçüncü kez dry-run edildi**, "cevap" satırının artık
  **TYREX INTERNATIONAL BV. adina** dediği görüldükten ve metnin operatörün
  verdiği mektupla birebir eşleştiği doğrulandıktan sonra `send=true` ile
  gönderildi (KURAL 18: hedef, tam metin ve kime ait olacağı operatörün aynı
  mesajında birlikte verilmişti). `msg_ping` (salt okunur) ile GERİ OKUNDU:
  thread **2 mesaj**, son mesaj TYREX INTERNATIONAL BV.'den. Buyer'a giden
  bildirimde satıcı yine **"Seller 8014004"** görünüyor — KURAL 8'in mağaza
  adını alıcıdan gizleme kuralı bozulmadı; değişen şey konuşmanın hangi satıcı
  HESABINA ait olduğu (dolayısıyla kimin panelinde durduğu ve fatura/iş
  kaydında kimin adına geçtiği).
- Yeni bir mekanizma yazılmadı, test eklenmedi: bu, KURAL 19'un 9 Eyl 2026'da
  zaten kurduğu `thread_seller`/`set_product.php` yolunun ikinci, bağımsız
  kullanımı — aracın kendisi `tests/lead_rename_test.php` ve
  `msg_thread_label_test.php` gibi testlerin tuttuğu davranışı değiştirmedi.

- **KURAL 20 — Her pakette TAŞIYICI + SERVİS + takip BAĞLANTISI; bağlantı
  numaradan TÜRETİLİR** (operatör, 9 Eyl 2026: *"bu gönderim numarasini ekle
  link ile beraber ups express saver"* + *"her pakette gönderici kargo bölümüde
  olsun"* — O39419 / SK Ventures).
  - O güne kadar `tracking` **çıplak bir dizgeydi**: alıcı sipariş sayfasında ve
    mektupta 18 karakter görüyor, hangi firmanın taşıdığını ve nereye bakacağını
    bilmiyordu. Taşıyıcı, servis adı ve bağlantı **üç ayrı olgu** ve üçü de
    eksikti. Alanlar: `order_statuses.json[ref].ship_carrier` / `.ship_service`.
  - **Bağlantı KAYDA YAZILMAZ.** `vestra_order_shipment()` onu numaradan kurar;
    elle yapıştırılan bir URL aynı olgunun ikinci kopyası olurdu ve numara
    değişince eski pakete bakmaya devam ederdi — `desc`/`sizes` ve thread-id
    hatalarının aynı sınıfı. Test bunu tutuyor: kayda sahte bir `url` konsa bile
    yok sayılıyor.
  - **Çıkarım bilerek DAR: yalnız UPS** (`1Z` + 16 alfanümerik, başka hiçbir
    taşıyıcının kullanmadığı kalıp). DHL'in 10 hanesi, FedEx'in 12 hanesi başka
    numaralara benziyor; oradan tahmin yürütmek alıcıya **başka bir paketin** ya
    da hiçbir şeyin sayfasını açar — bağlantı olmamasından kötü. Onlarda
    taşıyıcıyı operatör yazar (mango/zara dersinin kargo hâli).
  - **1Z sağlama basamağı BİLEREK doğrulanmıyor.** Algoritmayı sınayacak
    güvenilir bir referans numaram yoktu; yanlış yazılmış bir sağlama **geçerli**
    numaraları reddederdi — hiç kontrol etmemekten kötü bir arıza. Biçim kontrolü
    kesin ve yanlış ret üretemez. *Doğrulayamadığın bir kontrolü koyma.*
  - **Tek çözücü:** sipariş kartı, satıcı formu, admin formu ve "gönderildi"
    mektubu dördü de `vestra_order_shipment()` okuyor. Mektup kendi başına
    çözseydi sayfa ile e-posta iki ayrı şey yazabilirdi (KURAL 5f'in üç katmanı).
  - **Alan formda YOKSA kayıtlı değer KORUNUR** (`array_key_exists`). Sipariş
    listesindeki küçük durum formu taşıyıcı taşımıyor; koşulsuz yazsaydık
    listeden durum değiştirmek kayıtlı taşıyıcıyı **sessizce silerdi**
    (KURAL 4b'nin "işaretsiz kutucuk hiç gönderilmez" dersi).
  - Panel dışından: `seller-products.yml` → `admin_mode=ship`
    (`issue_ref=<sipariş>`, `ship_spec=tracking=…|carrier=ups|service=…|status=shipped`).
    Kuru koşu varsayılan ve **mektubu önizler**; `status=shipped` verilirse
    müşteriye gider. Yazma `vestra_order_set_shipment()` — panelle aynı kayıt,
    geri okunuyor.
  - `Carrier` / `Service` **8 sözlüğe birden** eklendi (KURAL 10).
  - Test: `tests/shipment_test.php` (62 iddia). Düşebildiği doğrulandı: çıkarım
    alt dizeye gevşetilince **8 kırmızı**, bağlantı kayıttan okunursa **2**.
  - **Canlı (9 Eyl 2026):** O39419 `preparing → shipped`, UPS · Express Saver ·
    `1ZY0089E0495346496`, mektup **teslim edildi** (Brevo `delivered`). CLAUDE.md
    bu alıcıya *"numara girilince size gelir"* sözünün verildiğini zaten
    kaydediyordu; tutulan söz o.
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
  (06:10 havuz, 06:25 escrow; kütük `~/vestra_sweep.log`) ve ardından iki
  süpürücüyü kuru koşuyla ayağa kaldırıp fatal varsa deploy'u kırmızıya boyar.
- **KURAL 27 — "Site açık" ile "site çalışıyor" ayrı şeyler: 16 Eyl 2026 disk
  kotası kesintisi.** Sunucu `/` için **200** dönüyordu, sayfa 112 KB'lik tam
  gövdeyle geliyordu — ama her istekte şu vardı:
  `session_start(): open(.../data/sessions/sess_…) failed: Disk quota exceeded`.
  **Oturum açılamıyorsa giriş, fiyat kapısı, sepet, sipariş, satıcı paneli ve
  admin panelinin tamamı çalışmıyor demektir** (hepsi `$_SESSION`'a bağlı). Yani
  vitrin ayakta, dükkân kapalıydı ve HTTP durum kodu bunu **söylemiyordu**.
  - **`df` ÖLÇMEDİĞİ ŞEYE "iyi" DİYOR.** Sonda yalnız `df -h ~` basıyordu ve o
    **birimi** gösteriyor: `/dev/sda1 2.0T 1.5T 430G 78%`. Sınır birimde değil
    **kullanıcı kotasında**. `quota -s` bu barındırmada hiçbir şey basmıyor, yani
    kotanın **sayısı** okunamıyor; okunabilen tek şey **yazılabiliyor mu**.
    Sonda artık dört dizine gerçekten bir dosya yazmayı deniyor
    (`data`, `data/sessions`, `uploads`, `/tmp`) — kesinti sırasında dördü de
    `*** YAZILAMIYOR`, temizlikten sonra dördü de `yazilabilir`. *Bir kaynağın
    tükendiğini, o kaynağı kullanmayı deneyerek ölç.*
  - **Sebep tek yerde ve yapısal:** `data/sessions`'ta **229.721** oturum dosyası.
    `auth.php` oturumları bilerek kendi dizinimizde tutuyor (paylaşımlı `/tmp`
    GC'si bizimkileri dakikalar içinde siliyordu) ve `gc_maxlifetime` **90 gün** —
    yani PHP'nin kendi çöp toplayıcısı çalışsa bile hiçbir şeyi silmezdi. Çerez
    taşımayan **her** istek yeni bir dosya açıyor; ölçülen hız **günde ~7.000–
    9.000**. 30 günden eski yalnızca 15.308 tanesi vardı: yığın taze ve hızlı,
    yani müşteri değil **taramacı** üretiyor.
  - **Silmek kimseyi çıkış yaptırmaz ve bu tahmin değil, koddan okundu:**
    `auth_set()` her girişte 90 günlük `vestra_rmb` çerezi yazıyor
    (`auth_remember_set`), `auth_remember_restore()` her `session_start()`'ta
    oturumu geri kuruyor. Sepet de oturumda değil (localStorage). Etkilenen tek
    grup: 90 günden beri hiç uğramamış ya da çerezini silmiş olanlar — onlar
    zaten çıkmış durumdaydı.
  - **İkinci besleyici, bir geri besleme döngüsü:** `public_html/error_log`
    **36 MB / 186.299 satır**. Kota dolunca her istek üçer uyarı yazıyor, o yazım
    kotayı daha da dolduruyordu. `vestra_sweep.log`'un tavanı deploy'da zaten
    vardı; **asıl büyüyen dosyada yoktu.**
  - **Yan hasar, bu dosyanın başka bir kaydını düzeltiyor:** KURAL 26'nın
    eklediği `[VESTRA cur] cerez yazilamadi, cikti zaten baslamis` satırı
    kesinti boyunca **her istekte** düşüyordu. Sebep para birimi kodu değil:
    oturum uyarıları sayfa gövdesine basılıyor, başlıklar gidiyor, çerez artık
    yazılamıyor. *O satır tam da işini yaptı — ama "para birimi bozuk" diye
    okunabilirdi.*
  - Onarım: `seller-products.yml` → **`admin_mode=diskclean`** (varsayılan kuru
    koşu; `move_threads` spec, `move_apply` uygular). **Dokunmadıkları, bilerek:**
    `data/docs` (38 MB müşteri belgesi), `invoices`, `receipts`, `offer_backups`,
    `message_backups` — bu depo bir yedeğin katalogu kurtardığını (11 Eyl %80 geri
    alması) ve bir teklifin yalnız yedekten geri geldiğini (KURAL 5g) kaydediyor.
    Dosya **adı** hiç basılmıyor: oturum dosyasının adı oturum kimliğidir, yani
    kimlik doğrulama jetonu, ve bu kütük herkese açık.
  - **Tekrarlamaması koda bağlandı, hatırlamaya değil:** `deploy-vestra.yml` iki
    yeni `VESTRA-SWEEP` satırı kuruyor — 05:30 oturum temizliği (3 günden eski
    `sess_*`) ve 05:35 hata günlüğü tavanı (4 MB'ı aşarsa son 1 MB kalır,
    `cat > $L` ile **yerinde** kısaltma: inode korunur, PHP'nin açık tuttuğu
    tanıtıcı geçerli kalır).
  - **Sonuç (aynı gün):** 211.215 dosya silindi, 18.506 kaldı; ev dizini
    4,3 G → 4,2 G; üç dizin de yeniden yazılabilir. **Açık kalan:** ev dizininin
    4,2 G'sinin 1,7 G'si `public_html`, 1,3 G'si `vestra-repo`, ~1,2 G'si
    `~/wt_incoming` staging'i (Dropbox/WeTransfer indirmeleri). Sonuncusu en büyük
    tek kazanç ama içinde **bekleyen iş var** (D&G iç giyim fotoğrafları,
    KURAL 25) — silmek operatör kararı.
- **CRON SAATLERİ UTC DEĞİL, SUNUCUNUN YEREL SAATİ (MST, UTC−7).** Bu dosya altı
  yerde "06:40 UTC", "07:20 UTC" diye yazıyordu; **yanlıştı** — cron crontab'ı
  sunucunun kendi saat diliminde yorumlar, `deploy-vestra.yml`'deki satırlar ise
  UTC sanılarak yazıldı. Ölçüm (8 Eyl 2026, `diag-messages` → `cron_probe`):
  sunucu `2026-09-08 06:15 MST (-0700)` derken UTC `13:15`; süpürücü kütüğündeki
  escrow damgaları dört gün üst üste **13:25 UTC**, yani crontab'daki `25 6`
  gerçekte 06:25 **MST**. Dolayısıyla her iş **yazdığından 7 saat sonra** koşuyor
  (journal 07:20 → **14:20 UTC**). `-0700` Eylül'de MST demek DST **yok**
  (Denver olsa MDT/-0600 olurdu), yani kayma sabit; ama saat dilimi bir gün
  değişirse hepsi birden kayar. Saatler **bilerek** olduğu gibi bırakıldı: işler
  günlük, 7 saatlik kayma hiçbirinin doğruluğunu bozmuyor (iş günü hesapları
  dahil) ve gerçek UTC'ye çevirmek o günün koşusunu bir gün öteler.
  **Yeni bir günlük iş eklerken saati UTC sanma; 7 ekle.**
- **"Otomasyon çalışmadı" önce KAYIT sorusudur** (8 Eyl 2026, journal). Deploy
  kanaryası `--dry` ile yeşildi ve "yazacak malzeme var" diyordu; crontab satırı
  da kuruluydu — ama `~/vestra_sweep.log`'da tek bir `journal:` satırı yoktu ve
  depoda **0 otomatik yazı** vardı. Sebep ne kod ne kapıydı: iş kurulduğunda
  (7 Eyl 18:37 UTC = 11:37 MST) o günün 07:20'si **geçmişti**, ilk ateşleme
  ertesi gün. Kütüğü o güne kadar **hiçbir şey okumuyordu** — günlük işler
  günlerce sessizce ölü kalabilir ve bunu gösterecek tek satır yoktu.
  Sonda: `diag-messages.yml` → `cron_probe=true` (kurulu satırlar, sunucu
  yerel+UTC saati, kütüğün son yazımı ve son damgaları, journal deposu, kurucunun
  şu an ne üreteceği). Kütük **maskelenir**: canlı `cron_pending_accounts.php`
  oraya hesap adresi yazıyor.
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
- **KAPANDI — teslimat çalışıyor; "reddediliyor" teşhisi YANLIŞTI** (4 Eylül 2026,
  teslim edilmiş bir mektubun başlığından ölçüldü). Kanıt, Gmail'e **ulaşmış**
  bir VESTRA bildiriminin `Authentication-Results` başlığı (3 Eyl 2026 19:18 UTC,
  `support@vestrasales.com` → operatör kutusu):

  ```
  dkim=pass  header.i=@vestrasales.com header.s=brevo2
  spf=pass   smtp.mailfrom=bounces-…@gx.d.sender-sib.com
  dmarc=pass (p=REJECT sp=REJECT dis=NONE) header.from=vestrasales.com
  ```

  Yani Brevo mektubu **`d=vestrasales.com` ile, `brevo2` seçicisiyle İMZALIYOR**.
  DKIM alan adı `header.from` ile hizalandığı için **DMARC yalnız DKIM üzerinden
  geçiyor** — `p=reject` mektubu öldürmüyor, SPF'in `sender-sib.com`'u
  göstermesi de önemsiz kalıyor (DMARC iki yoldan birinin hizalanmasını ister).

  **Eski teşhis neden yanlıştı:** Brevo'nun **liste** ucu (`/senders/domains`)
  `dkim=YOK, dmarc=YOK` diyor, ama aynı koşuda **alan adı** ucu
  (`/senders/domains/vestrasales.com`) dört kaydın dördü için de
  `durum=DOGRULANDI` döndürüyor. Liste ucunun bayrağı bir ölçüm değil; ona
  bakıp "imzalamıyor, demek ki reddediliyor" sonucuna gidildi. *Sağlayıcının
  kendi bayrağı kanıt değildir; kanıt, teslim edilmiş mektubun başlığıdır.*
  Operatörün "9 test mektubunu görmedim" demesi de bu zincire uyduruldu — oysa
  gelen kutusunda aynı günlere ait onlarca VESTRA mektubu duruyor.

  Bugünkü DNS (4 Eyl 2026, `sender=true` canlı çözümleme):

  | Kayıt | Durum |
  |---|---|
  | `brevo1._domainkey`, `brevo2._domainkey` CNAME | VAR, Brevo'da DOGRULANDI |
  | `@` TXT `brevo-code:753435ab…` | VAR, DOGRULANDI |
  | SPF `v=spf1 include:secureserver.net include:spf.brevo.com -all` | VAR — operatör Brevo'yu **ekledi** |
  | DMARC `v=DMARC1; p=reject; rua=…onsecureserver.net; adkim=r; aspf=r` | VAR — Brevo'nun istediği `p=none` değil, ama DKIM hizalandığı için **sorun değil** |

  Özetin `DNS kaydi yayinda: 3/4 (EKSIK VAR)` demesi bu tek farktan geliyor
  (Brevo `p=none` önerir, DNS'te `p=reject` var). **Eksik değil, daha sıkı.**
  `p=reject`'i gevşetme: DKIM geçerken sıkı politika koruma sağlar.

  Gönderen adresler artık **ikisi de kayıtlı ve aktif**: operatörün Gmail'i ve
  `support@vestrasales.com` ("VESTRA") — kod bu ikincisiyle gönderiyor.
  **Toplu gönderimin önünde teslimat engeli kalmadı.**
- **Lead havuzu tükendi (4 Eyl 2026).** Aynı gün ölçüldü: hiç yazılmamış + çok
  markalı (`min_brands=2`) aday **0**, eşik 1'e indirilince **1**; Winter 26/27
  ikinci dokunuşunda **8** aday çıktı, üçü kaçak adres olduğu için filtre eklendi
  ve **5**'e düştü; kayıtlı alıcıların **hepsi** duyuruyu almış (0 kaldı).
  Gönderilen: 4 Winter + 1 soğuk = **5 mektup, 0 hata**. Yani günlük 300'lük kota
  değil, **gönderilecek adres** darboğaz. Hacim isteniyorsa sıradaki iş yeni lead
  keşfidir (`discover-city.yml` / `add-and-send.yml`), yeni kampanya metni değil.
- **KEŞFİN VERİMİ ÖLÇÜLDÜ: ~1 lead/şehir** (8 Eyl 2026; operatör: *"kendinde bul"*,
  *"yeni lead keşfi de yap"*, *"200 adrese"*). `discover-city.yml` iki koşu,
  8 şehir, ~30 dakika → **7 yeni lead**. Şehir başına dökümü, ki asıl bilgi bu:

  | Şehir | Taranan | E-postalı | Yeni |
  |---|---:|---:|---:|
  | Bologna, Valencia | 160 | 14 | **0** (hepsi zaten kayıtlı) |
  | Lyon | 80 | 23 | 4 |
  | São Paulo, Mexico City, Buenos Aires | 240 | — | **0** |
  | Santiago | 80 | 3 | 3 |
  | Hamburg | 0 | 0 | 0 (Overpass boş döndü) |

  **200 adres ≈ 200 şehir ≈ 13 saat koşu.** Avrupa'nın büyük şehirleri doymuş
  (Bologna/Valencia'da 14 e-postalı dükkânın 14'ü de kayıtlıydı); Latin Amerika
  da beklenenin aksine kuru çıktı. Bir sonraki sefer bu tabloyu oku: "keşif
  yapalım" bir gün sürer, bir öğleden sonra değil.
- **Havuzun "uygun" dediği aday, gönderilebilir aday DEĞİL.** Aynı gün
  `send-outreach` kuru koşusu `min_brands=2` ile **3**, `min_brands=1` ile **6**
  aday verdi; elle okununca gönderilebilir **0** çıktı. Sebep: seçici **adres**
  damgasına bakıyor, `add-and-send` ise **firma alan adına** (KURAL 1c). Altısının
  dördü (Luisa World, BLUE IN GREEN SOHO, Livestock, UP THERE) aynı gün mektup
  gönderilmiş firmaların **ikinci kutusu** — göndermek 2 Eylül'deki 7 çift
  mektubun aynısı olurdu. Kalan ikisi operatör kararı bekliyor: **Carl Scarpa**
  (IE, çok şubeli + ağırlıklı kendi markası — bloklisteye ekleMEdim, `worksout`
  dersi: hatırlamayla eleme yapma) ve **Kinfolk** (`info@kinfolk.kr`; dükkân
  Brooklyn ama çözülen adres Kore alan adında, lead'in ülkesi boş).
  *Kuru koşunun sayısını rapor etmeden önce listeyi tek tek oku.*
- **Operatörün yüklediği CSV'nin yarısı UYDURMAYDI** (8 Eyl 2026,
  `global_200_multibrand_stores.csv`). 200 satırın **85'i** aynı kalıpta üretilmiş
  yer tutucuydu: ad `Concept Store Variant 116…200`, şehir `Multibrand Hub`,
  adres `contact@boutiquevariant<N>.com`. 28 satır KURAL 1 engelli; kalan 87'nin
  **84'ü** aynı gün zaten işlenmişti. Gerçekten yeni 3 alan adının
  (`guadalupe-store.com.br`, `maze.com.br`, `fitzrovia.com.ar`) **üçünde de**
  sitede yayınlanmış adres yok. Yani 200 satırlık dosyadan gönderilebilir adres:
  **0**. Uydurma 85'e göndermek 85 sert bounce demekti — dosyanın "200 satır"
  olması 200 aday olduğu anlamına gelmiyor, KURAL 1b'nin aynı imzası.
- **Elde tutulan tek lead:** `factoryoutlet.gr` (Yunanistan, Attika'da 3 mağaza,
  200+ marka — zincir değil, bağımsız off-price; Il Salvagente emsali). Firma adı
  kayıtta **"Αρχική"** (Yunanca "Anasayfa") olarak duruyor, yani mektup
  "Hello Αρχική," diye açılacaktı. Bu koşuda `skip_names` ile atlandı ve **öyle
  bırakıldı** — düzeltmenin bugün güvenli bir yolu yok:
  panelde prospect için ad DÜZENLEME eylemi yok (`admin.php`: ekle, içe aktar,
  durum değiştir, elden mektup, sil), `add-lead.yml` mevcut kaydı güncellemiyor
  (`= zaten var` deyip geçiyor), silip yeniden eklemek ise `last_contacted_at` /
  `last_newcollection_at` damgalarını siler ve firmaya ilk-temas mektubunun
  İKİNCİ kez gitmesine yol açar. Yani bir adı düzeltmek için bir mektubu tekrar
  göndermeyi göze almak gerekiyor; bir lead bunu hak etmiyor.
  Bu sınıf tekrar ederse doğru çözüm panele küçük bir `rename_lead` eylemi
  eklemektir (id ile bul, yalnız `company` alanını yaz, damgalara dokunma).

**KURAL 30 — Lead'in firma adı DÜZELTİLEBİLİR; damgalara dokunulmaz**
(17 Eyl 2026, yukarıdaki maddenin kapanışı — tarif edilen çözüm yazıldı).
- Sebep: o kayıt **DÖRDÜNCÜ kez** elle atlanacaktı ve bu sefer ikinci mektup
  kanalındaki **tek** uygun adaydı. *Kuralın hatırlanmaya bırakılması yetmiyor;
  bu kez "hatırlamak" bile bir mektubu engelliyordu.*
- Panel: `Admin ▸ Prospects`'te **adın üstüne tıklamak** (`set_lead_email`'in
  birebir deseni). Panel dışından: `seller-products.yml` →
  **`admin_mode=lead_rename`** (`issue_ref=<lead id ya da ALAN ADI>`,
  `payload=<yeni ad>`, `move_apply=true`; varsayılan kuru koşu).
- **Müşterinin E-POSTASI girdiye yazılmıyor** — koşu başlığı kalıcı ve herkese
  açık. Girdi **alan adı** kabul ediyor (dükkânın zaten açık web sitesi), çıktı
  maskeli: `diag-live` → `leads_status`'ta bir kez düzeltilen hatanın aynısı.
- **TAM 1 eşleşme yoksa iş DURUR**: sıfırda yanlış bir şey yazılmaz, birden
  fazlada **YANLIŞ dükkânın** adı değişirdi. Eşleşme id/alan adı **tam
  eşitlik**, alt dize değil (mango/zara dersi).
- **Yalnız `company` yazılıyor.** `status`, `last_contacted_at`,
  `last_newcollection_at` elle sabitlendi ve **geri okunarak** doğrulanıyor —
  bu yazmanın var olma sebebi zaten damgaları kaybetmeden ad düzeltmek; damga
  düşerse ilk-temas mektubu ikinci kez gider. **Boş ad reddediliyor**: mektup
  "Hello" yazıp nokta koyardı.
- **İlk canlı koşu 255 ile düştü ve kütükte TEK SATIR yoktu.** Sebep
  `leads.php`'nin **kendi yorumunda** yazılıydı (*"Assumes the caller has
  already required inc/products.php"*) — KURAL 15'in bir vakası daha. İkinci ve
  daha kötü bulgu: sunucunun CLI ini'si hataları yutuyor, yani bu dosyanın
  *"fatal'i `grep HATA` ile arama, çıktının tamamına bak"* kuralı bile
  yetmiyordu — **bakılacak bir çıktı yoktu**. Adım artık
  `display_errors=stderr -d error_reporting=-1` ile koşuyor.
- Test: `tests/lead_rename_test.php` (28 iddia; kum havuzunda `admin.php`'yi
  **gerçekten POST ile koşturuyor** — kaynak taraması bu işi ölçemezdi).
  **Ters yön de tutuluyor:** AYNI ADI taşıyan ikinci bir kayıt değişmemeli,
  yoksa bütün listeyi tek ada çeviren bir hata yeşil kalırdı. Düşebildiği
  doğrulandı: handler damgayı da yazınca **2 kırmızı**, boş ad koruması
  kalkınca **1**, iş akışı eşleşmesi alt dizeye gevşeyince **1**.
**KURAL 23 — Müşteriye giden mektup YALNIZCA `support@vestrasales.com`'dan
çıkar; operatörün Gmail'i gönderen olamaz** (operatör, 11 Eyl 2026:
*"acerasoft@gmail.com dan hic bir müsteriye email gitmeyecek sadece
support@vestrasales.com dan gidecek brevo üzerinden"*).
- **Önce ölçüldü** (`diag-messages` → `mailcfg`): canlı ayar **zaten**
  `mail_enabled=true | provider=brevo | from=support@vestrasales.com`. Yani
  bugün hatalı bir mektup gitmiyor — bu bir düzeltme değil, bir **kapı**.
- **Asıl bulgu kural değil KOPYAYDI:** aynı olgu **dört** yerde okunuyordu ve
  dördüncüsü aynı şeyi söylemiyordu — `vestra_smtp_send`, `smtp_from` boşsa
  **`smtp_user`'a düşüyordu**. API yolu bir gün kotayı doldurup SMTP yedeğine
  düştüğünde müşteri mektubu SMTP kullanıcısının adresinden giderdi. Bu depoda
  "aynı olgu birkaç yerde yazılı" hatası defalarca kayıtlı (`desc`/`sizes`,
  faturanın üç katmanı, dört mektup gövdesi); buradaki bedeli müşterinin
  kutusunda **yanlış gönderici** olurdu.
- Tek karar noktası `vestra_mail_from(?array $cfg, string $candidate)`
  (`inc/notify.php`) ve **saf**: ayarı çağıran okur, hangi adresin
  kullanılacağına o karar verir. Üç gönderim yolu da (api / smtp / php-mail)
  onu çağırıyor; hiçbiri `$from`'u artık doğrudan ayardan okumuyor.
- **Ölçüt ALAN ADI, adres değil.** Operatörün cümlesi tek bir kutu yazıyor ama
  kuralın anlamı "kişisel Gmail değil, şirket adresi": `sales@vestrasales.com`
  yarın açılırsa engellenmemeli, `support@vestrasales.com.tr` ya da
  `notvestrasales.com` ise **tam eşitlik** olmadığı için ev adresine düşer
  (mango/zara dersi). Adres adını sabitlemek uydurma bir kısıt olurdu.
- **Satıcının KENDİ transportu (`$cfg`) kapsam DIŞI**, bilerek: o özellik
  "gerçekten onlardan gitsin" diye var ve kendi kimliklerini polislemek bize
  düşmez. `$cfg !== null` ise aday adres aynen dönüyor.
- **Zorlama sessiz değil:** ezildiğinde `error_log`'a düşüyor ve yerel kısım
  **maskeli** (o kütük teşhis çıktısına giriyor, çıktı herkese açık).
- **Operatörün Gmail'i ALICI olarak kalıyor** — `ops_email`, cron raporları,
  `buyer_reply`'ın boş `to` varsayılanı hep onun kutusu. Kural gönderen
  hakkında; teşhis mektubunu kendi kutusuna almak değişmedi.
- Brevo'da **iki gönderici de hâlâ aktif** (`a***@gmail.com` / "Acerasoft LLC"
  ve `s***@vestrasales.com` / "VESTRA"), yani sağlayıcı tarafı bu kuralı
  zorlamıyor — zorlayan şey kod.
- Test: `tests/mail_sender_test.php` (23 iddia, iki yön). Düşebildiği
  doğrulandı: alan adı kontrolü kaldırılınca **6 kırmızı**, smtp yolu eski
  hâline döndürülünce **4**. *İlk yazımda smtp kablolama iddiası `.*?` ile
  yazılmıştı ve HİÇ DÜŞMÜYORDU: `vestra_smtp_send` dosyada `vestra_api_send`'den
  önce geliyor, tembel nokta bir sonraki fonksiyonun çağrısına uzanıp orada
  eşleşiyordu. Arama artık fonksiyon gövdesiyle sınırlı — "hiç düşemeyen bir
  iddia, iddia değildir" bu depoda zaten kayıtlıydı ve bir kez daha oldu.*

**Hoş geldin kuponu: adres GİRDİYE yazılmaz, hesaptan çözülür** (operatör,
12 Eyl 2026: *"Michael Baumgartner … bu müsteriye yüzde 5 lik indirim kuponu
gönder welcome olarak"*).
- **Kural zaten yazılıydı, bu iş akışı uymuyordu.** Tek müşteriye kupon
  göndermenin tek yolu `only_emails` girdisiydi; o girdi **koşu başlığında
  kalıcı ve herkese açık** ve çıktı da adresi **maskesiz** basıyordu. İkisi de
  bu dosyanın kendi kurallarına aykırı (*"Müşteri adresini iş akışı girdisine
  yazma; kayıttan çözdür"* + *"müşteri e-postaları teşhis çıktılarında
  maskelenir"*) — `diag-live` → `leads_status`'ta bir kez düzeltilen hatanın
  aynısı, başka bir dosyada duruyordu. *Bir kuralı bir yerde uygulamak, kuralı
  uygulamak değildir.*
- Yeni girdi `only_accounts`: **hesap ID'si** ya da firma adı parçası; adresi
  sunucu `auth_accounts()`'tan çözüyor (`buyer_reply`'ın `to=account:<isim>`
  deseni). **Eşleşme TAM 1 değilse iş DURUR** ve hiçbir şey göndermez: sıfırda
  kimseye gitmez, birden fazlada **yanlış müşteriye** gidebilirdi ve gönderilmiş
  bir kupon geri alınamaz. Adaylar **maskeli** listeleniyor ki operatör
  daraltabilsin. ID tam eşitlik, ad parça — belirsizliği "tam 1" şartı yakalıyor.
- **Kupon KODU açıkta kalıyor, bilerek:** kod tek kullanımlık, ilk siparişe bağlı
  ve o **adrese kilitli** (`voucher_validate` kayıtlı adresi kontrol ediyor),
  yani kütüğü okuyan kullanamaz — ve operatörün müşteriye elle iletebilmesi için
  görünmesi gerekiyor. Maskelenen şey adres.
- `V_ONLYACC` **`envs:` listesine de** eklendi: `appleboy/ssh-action` yalnızca
  orada adı yazılı değişkenleri sunucuya geçiriyor, yoksa uzakta `getenv()` boş
  döner ve sonda **sorulmayan bir soruya cevap verir**.
- **Ölçüm sırası:** önce kum havuzu (uid → tam 1; belirsiz ad → çıkış 1, iki aday
  maskeli; eşleşmeyen → çıkış 1; **kontrol grubu** `only_accounts`'suz koşu →
  eski davranış aynen), sonra canlı `dry_run=true`, sonra gerçek gönderim.
- **Sonuç (12 Eyl 2026):** `849aaa5ab65a0b3f` → **BRITISHSTYLE**, `b***@chello.at`,
  tip **buyer**; kampanya `welcome5`, kod **VES-7RNZ-RZBJ**, son geçerlilik
  **2027-03-12**. Kuru koşu `new` demişti, yani hesapta **welcome5 kodu yoktu** —
  ikinci bir kod gitmedi. Gönderildi **1**, hata **0**. Sonraki toplu hoş geldin
  koşusu aynı `campaign` adını gördüğü için bu hesabı **atlayacak**.
- Mektubun dili hesabın **kayıtlı** `lang` alanından — metne gömülü değil ve
  girdiden gelmiyor.

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
- **Ayakkabı bölmesi canlı (3 Eyl 2026): Calzados Pili Pérez, 335 ürün,
  `section=footwear`, `/shop?section=footwear`.** Yöntem ve kararlar
  `product-batches/pili-perez-NOTLAR.md`; satıcıya sorulan 4 satır (8029
  yetişkin, H90, K874/K874-1) ve veri hataları orada. Kurallar:
  - **Üçüncü taraf tarifesi depoya AÇIK girmez** — parti `product-batches/*.enc`
    (AES-256-CBC + sunucunun RSA-OAEP açık anahtarı; özel anahtar
    `~/.vestra_import/import_key.pem`, `diag-live` → `wetransfer_probe=<url>|fetch`
    üretir ve `IMPORT_PUBKEY_B64=` basar). `add-products.yml` `.enc` görünce
    sunucuda çözer. **Şifreli paketin fiyatı loga da basılmaz** — ilk iki koşu
    335 satırın fiyatını herkese açık günlüğe yazdı (3 Eyl 2026, günlükler
    silindi: `delete_workflow_run_logs`). Şifrelemek yetmez; adımın ne bastığını
    oku. Fiyat doğrulaması Excel'e karşı yerelde yapılır (335/335 birebir,
    252 kutulu satırda çift × fiyat = satıcının kutu toplamı).
  - **90 KB'den büyük parti SSH komutunun içinde GİTMEZ.** ssh-action betiği
    tek `sh -c` argümanı olarak yollar; Linux tek argümanı 128 KB'de keser ve
    oturum hata vermeden 10 dk zaman aşımına kadar asılı kalır (3 Eyl 2026'da
    iki koşu). Büyük dosyayı sunucu `raw.githubusercontent.com/<repo>/<sha>/`
    adresinden indirir ve runner'ın sha256'sıyla doğrular (`add-products.yml`).
  - **Fotoğraflar sunucudan çekilir** (bu ortam wetransfer.com'a kapalı):
    `~/wt_incoming/<id>/files` (public_html DIŞI), `stage_from=` ile yalnızca
    üründe geçen adlar `uploads/` altına kopyalanır (küçük harf, aksansız,
    `_`→`-`, `✓` gibi işaretler atılır — `$fold`, iki tarafta aynı).
  - **Kategori fotoğraftan:** açıklaması tip söylemeyen ürünü varsayılana
    doldurma; `wetransfer_probe=sheet:<klasör>|from=N|count=M|spaced` klasör
    başına bir küçük resim basar, sayfalar indirilip bakılır. 3 Eylül'de
    açıklama "colegial" derken fotoğraf mokasen/spor gösterdi — fotoğraf kazandı.
  - **DROPSHIP ÖDEMESİ ŞU AN DURDURULDU** (operatör, 7 Eyl 2026: *"dropshipping
    ödemesini şu an için kaldır ancak yeniden başlamak için kurulu olsun"*).
    Duran şey **para**: tek parça sipariş oluşmuyor, Stripe oturumu açılmıyor.
    Kapı **sunucuda ve tek**: `dropship_create_order()` en başta
    `vestra_dropship_payments_enabled()` soruyor — site formu ve ortak API'si
    aynı fonksiyondan geçtiği için ikisi birden duruyor (`503 payments_paused`).
    **Varsayılan KAPALI**: ayar dosyası (`data/dropship_settings.json`) yoksa ya
    da bozuksa ödeme durur; tersi, dosya kaybolunca sessizce para almaya
    başlamak olurdu. **Hiçbir şey silinmedi** — ilanların dropship bloğu, fiyat
    türetme, bölge/ücret tablosu, `a=list` / `a=stock` uçları, geçmiş siparişler
    ve panel sekmesi yerinde; list/stock yanıtları `ordering_paused` taşıyor ki
    ortak durumu sipariş anında değil önceden görsün. Çalışmayan düğme
    gösterilmiyor: ürün sayfasındaki bağlantı ve satın alma formu çizilmiyor,
    yerine "şu an durduruldu" notu var (KURAL 4'ün karşı teklif alanı dersi).
    **Geri açma operatörde**: `Admin ▸ Dropship` üstündeki anahtar, deploy
    gerekmiyor; yazma geri okunarak doğrulanıyor, tutmazsa kırmızı uyarı çıkar.
    Test: `tests/dropship_payments_test.php` (39 iddia; varsayılanı ölçmek için
    ayrı PHP süreçleri — bayrak süreç içinde `static` önbellekli).
  - **Ayakkabıda dropship YOK** (operatör kararı, 3 Eyl 2026: *"Buy a single
    piece — dropshipping, tüm ayakkabılardan kaldır"*). Kural bölmeye bağlı:
    `vestra_dropship_excluded_sections()` = `['footwear']`, `vestra_dropship_of()`
    en başta bakar ve elle açılmış bloğu da ezer — yarın eklenen ayakkabı da
    kapalı doğar. Ürün sayfası düğmesi, `/dropship`, ödeme ve API aynı
    fonksiyondan geçer. Test: `tests/dropship_section_test.php`; canlı sayım:
    `diag-live` → `dropship_probe=true` ("bolme yasagi footwear: N").
**M3600 POLO 8'Lİ → 10'LU KARTON; asgari ve merdiven aynı tabana taşındı**
(operatör, 16 Eyl 2026: *"F.Perry Polo ve Sweatshirt Lot larini 10 lu yap"* →
*"8 den 10 yükselt"* → *"en az alimlar da ayni matiga göre ciksin"*).
- **Yalnız polo 8'liydi.** M7535 zaten `size_step=10` (S×1 · M×3 · L×3 · XL×2 ·
  XXL×1) ve `moq=50` — ölçüldü, varsayılmadı; sweatshirt'e hiç dokunulmadı.
- **Depodaki parti dosyası BAYATTI ve kuru koşu yakaladı:**
  `product-batches/fredperry-m3600-polo.json` `moq=48` diyor, canlı kayıt
  **56** idi (11 Eyl'de operatörün kendi kararı). Hedef rakamları o dosyadan
  okusaydım 48'i "mevcut" sanacaktım. *Parti dosyası ithalat kaydıdır, canlı
  kaydın aynası değil — hedefi canlıdan ölç.*
- **Asgari 56 → 50, çünkü KARDEŞİ 50.** MOQ paket adımının katı olmak zorunda
  (KURAL 4b: sepet yukarı yuvarlıyor, yani 10'luk kartonda "min 56" hiç
  alınamayan bir minimum). 60 da tam kattı; **50 seçildi** çünkü aynı markanın
  aynı satıcıdaki 10'luk kardeşi (M7535) zaten `min 50 pc` — "aynı mantık"
  denen şey birebir bu. Merdiven aynı tabana çekildi: 96 → 100, 192 → 200;
  **fiyatlar değişmedi** (39,00 / 35,50 / 32,00).
- **Beden eğrisi UYDURULMADI:** aynı satıcının aynı markadaki kendi 10'lu
  eğrisi alındı (M7535'in S×1 · M×3 · L×3 · XL×2 · XXL×1). Tedarikçi poloyu
  başka türlü paketliyorsa operatör düzeltir — ama "Cartons of 8" yazan bir
  etiketi 10'luk ilanda bırakmak ilana yalan söyletirdi.
- **`desc` bu ilanda seri TAŞIMIYOR ve bu ölçülerek anlaşıldı.** `fit_scan`'in
  çelişki sayacı desc'te seri yoksa da 0 döner (`if ($rd === '') continue`),
  yani değişiklikten ÖNCEKİ 0 iki durumu birden gizliyordu. Değişiklikten
  SONRA da 0 çıkması ayrımı yaptı: desc'te seri olsaydı artık çelişirdi.
  *Balenciaga'nın (9 Eyl) ve NBB'nin (10 Eyl) "seri iki yerde yazılı" dersi
  bu sefer ödenmedi çünkü ayrım yapılabilecek yerden ölçüldü.*
- **Doğrulama alanın değerine değil sepetin TAHSİL ETTİĞİNE bakıyor**
  (`price_audit`): katalog genelinde alıcı aleyhine tek satır var ve o
  `lac-pique-polo` (eski demo tohumu), Fred Perry değil — yani MOQ 50'de
  sepet gerçekten €39,00 alıyor.
- **Bugün gönderilen 118 Angebot mektubu artık ESKİ rakamı taşıyor** (8'li
  karton / min 56). Mektuplar rakamı canlı kayıttan basıyor, yani geri
  alınamaz; bir sonraki mektup kendiliğinden doğru çıkar. Operatöre söylendi.

**LACOSTE FLEECE HOODIE (SH9623): lot 10, asgari 40, kademe 49,90 / 45,00 /
42,00 — ASGARİ ADEDİ OPERATÖR VERMEDİ, İLANIN KENDİ VERİSİ BELİRLEDİ**
(operatör, 18 Eyl 2026: *"Lacoste Fleece Hoodie 10 ad bir Lot olucak. En az
alimda 49,90 eur 50 ad. 45,00 eur 100 ad 42,00 eur"*).

- **Hangi ilan olduğu ADDAN değil RAKAMDAN belirlendi.** Katalogda **iki**
  hoodie var: `lac-fleece-hoodie` (SH9623, list **49,90**) ve `lac-zip-hoodie`
  (*"Zip Up Fleece Hoodie"*, SH9626, list 55). Operatörün yazdığı 49,90 tam
  olarak SH9623'ün o günkü fiyatı — ayırt eden şey bu. *Aynı adı taşıyan iki
  ilan varsa ad bir kimlik değildir* (iki "Wings T-Shirt" dersi); burada adlar
  bile birbirini kapsıyordu.
- **ASGARİ 40 ve bu bir tercih değil, tek tutarlı değer.** Operatör yalnız
  *"en az alımda 49,90"* dedi, adedi vermedi. Dördü de elendi, gerekçesiyle:
  - **56 kalamaz** — 10'un katı değil; sepet adımın katına **yukarı**
    yuvarlıyor, yani "min 56" sayfada 56, kasada 60 demek (`set_product.php`
    zaten reddeder).
  - **50 olamaz** — 50 artık **45,00** kademesi; asgari 50 olsaydı 49,90 hiçbir
    alıcının ulaşamayacağı bir fiyat olurdu. *İlan, alınamayan bir minimum ilan
    eder* (Fred Perry'de bir kez kaydedilen hata).
  - **10/20/30 olamaz** — ilan `min_colors=4` taşıyor ve **renk-adet** kipinde
    her renk **en az bir lot**: `vestra_parse_colorqty()` her rengi
    `size_step`'in katına yuvarlıyor ve 0 olanı hiç saymıyor,
    `order.php:75` de 4 renk şart koşuyor. Yani 10'luk lotta **gerçekten
    alınabilen en küçük adet 4 × 10 = 40**.
  - **40** — dördü de tutuyor: 10'un katı, 50'nin altında (49,90 ulaşılabilir),
    ve tam 4 karton = tam 4 renk.
  *Operatörün cümlesinden okunamayan bir sayı, ilanın kendi kurallarından
  okunabiliyordu; uydurmak ile türetmek arasındaki fark bu.*
- **`desc` AYNI yazmada değişti ve eski metni KURU KOŞU gösterdi.** Eski hâli
  *"…sold in cartons of **8** per colour … Minimum order **56** pc, at least 4
  colours."* — yani karton adedi ve asgari **iki yerde** yazılıydı. Yalnız
  alanları değiştirip `desc`'i bırakmak, Balenciaga'nın (9 Eyl) ve NBB'nin
  (10 Eyl) dersinin üçüncü vakası olurdu. Metin şablondan yeniden yazılmadı,
  **sunucunun kendi dizgesinden** türetildi. Yeni cümle rakamla birebir tutuyor:
  10'luk karton × 4 renk = 40.
  *Eski metni okuma yolu:* `desc`'e bir **probe** değeri koyup `dry_run=true`
  koşmak — genel dal `desc '<ESKİ>' -> '<YENİ>'` basıyor, hiçbir şey yazılmıyor.
- **Beden serisi uydurulmadı:** `S×1 · M×3 · L×3 · XL×2 · XXL×1` (1-3-3-2-1,
  toplam 10) katalogun kendi 10'luk eğrisi — aynı satıcının kardeş ilanı
  `lac-crew-sweatshirt` (SH9608) ve Fred Perry M3600/M7535 aynısını taşıyor.
- **`list`'e DOKUNULMADI ve bedeli operatöre yazıldı.** `list=49,90`, `mode=sale`
  ve en düşük kademe artık 42,00 → `vestra_discount()` = round(100×(49,9−42)/49,9)
  = **%16**, yani vitrinde 49,90 üstü çizili bir **−%16 rozeti** çıkıyor (önce
  hiç yoktu). Bu, bu katalogun Lacoste tarafında **zaten kurulu** desen (SH9608
  list 44 / kademe 35 → −%20; monogram polo 41,18 / 35 → −%15), ve operatörün
  sormadığı bir fiyat kararı vermemek için değiştirilmedi. İstenirse tek satır.
- **GERİ OKUMA sunucudan** (`inspect-products` run `35297351154`):
  `moq=40 pc · size_step=10 · sizes=S×1 · M×3 · L×3 · XL×2 · XXL×1 · 10/pack ·
  tiers 40+ → €49,9 | 50+ → €45 | 100+ → €42 · min_colors=4 · renk(8)`.
  **Fiyat denetimi** (alanın değerine değil sepetin TAHSİL ETTİĞİNE bakıyor):
  894 üründe alıcı aleyhine tek satır var ve o eski demo tohumu
  `lac-pique-polo`, bu hoodie değil. Yedek: `listings.json.bak-20260918-015535`.
- **AYNI DAKİKALARDA BAŞKA BİR OTURUM DA LACOSTE YAZIYORDU** (`SH9608`, lot 8→10,
  01:45). `listings.json` oku-değiştir-yaz olduğu için geri okuma **iki yönlü**
  yapıldı: kendi ilanımın yanında **onların SH9608'i de** (`moq=50`,
  `size_step=10`, `50+ → 39,9 | 100+ → 35`) yerinde duruyor. *Kendi yazmanı
  doğrulamak yetmez; aynı dosyaya yazan komşunun yazması da duruyor mu diye bak.*

**Fred Perry M7535 / M3600 — asgari alım ve renk seçimi** (operatör, 10 Eyl
2026: *"tüm renk varyasyonlarini koy"* · *"en az 4 renk secilmeli alirken"* ·
*"en az alim 50 ad. olsun"* · *"f.perry polo da en az alim 56 ad. olsun"* ·
*"8 li polo"* · *"sweater 10 lu"*).
- **M7535 SWEATSHIRT'tir, sweater değil — ölçüldü, doğrulandı, DEĞİŞTİRİLMEDİ**
  (operatör, 11 Eyl 2026: *"sweater mi sweatshirt mü kontrol et dogrusunu gir"*).
  Bu maddedeki *"sweater 10 lu"* ve aşağıdaki *"f.perrey sweateri"* operatörün
  **sohbet kısaltması**; ürünün adı değil. İki bağımsız kanıt: (1) operatörün
  kendi Fred Perry tarifesi beş satırın beşinde de *"Fred Perry crew neck
  sweatshirt"*, `Type: crew neck sweatshirt`, `Fabric: %100 COTTON` diyor;
  (2) fotoğraf düz örme (looped-back) sweat kumaşı, ribanalı yaka/manşet/etek —
  örgü (knit) yapısı yok. Canlı kayıt zaten doğru: `name=Fred Perry Crew Neck
  Sweatshirt — M7535`, `cat=Hoodies & Sweatshirts`. **Kategori de doğru ve
  önemli:** taksonomide kardeşi `Sweaters & Knitwear` var ve Almancası
  *"Pullover & Strickwaren"* — oraya konsaydı Alman alıcı örgü kazak okurdu.
  Baumgartner'a giden mektup ilanın `name`'ini bastığı için *"Sweatshirt"*
  yazdı; 3. parti kampanya şablonu da 13 dilde sweatshirt/felpa/bluza/mikina/
  スウェット diyor ve **200 adrese gitti** — yani yanlış bir "düzeltme" yalnız
  ilanı değil, gönderilmiş mektupları da yalanlardı. *Operatörün kısaltmasını
  ürün adı sanıp yeniden adlandırma.*
- **"Renkler ilanda var" ile "alıcı renk seçebiliyor" AYRI İKİ ŞEY.** Renk
  seçici ancak `vestra_is_colorqty_listing()` evet derse çiziliyor: `colors` **ve**
  `min_colors` **ve** `size_step > 1`. Sweatshirt'te beş renk kayıtlıydı ama
  `min_colors` **0** idi — yani beş renk dosyada duruyor, hiçbir alıcı
  seçemiyordu. `min_colors=4` yazılınca hem seçici çıktı hem kural işledi.
  *Marka listesi rengi okuyup basıyordu ve tam bu yüzden fark edilmedi;
  sonda artık `min_renk` ve "SECICI YOK" da yazıyor.*
- **O sondayı yazarken iki tuzağa birden basıldı, ikisi de kaydedildi:**
  fonksiyonun adı `vestra_needs_colors` sanıldı (gerçeği
  `vestra_is_colorqty_listing`), ve **iş buna rağmen YEŞİL bitti** — adım
  `php …` sonrası `rm` çalıştırdığı için çıkış kodu eziliyordu, yani
  `PHP OLUMCUL` yazan, hiçbir ürün basmayan bir koşu "success" göründü.
  `set-product.yml` bu tuzağı zaten kaydetmişti; `seller-products.yml`'de
  duruyordu. Artık `RC=$?` → `exit $RC`.
- **Asgari alım paket adımının katı olmak zorunda** (KURAL 4b): sweatshirt
  10'lu paket → **50** (5 paket), polo 8'li → **56** (7 paket). Operatörün
  verdiği iki rakam da zaten tam kat; olmasaydı `set_product.php` reddederdi
  ("sepet 50 adedi 56'ya yuvarlar, ilan edilen minimum alınamaz").
- **4 renk 5 pakete sığıyor** (50/10 = 5 ≥ 4) ve 5 renkten seçiliyor. Asgari
  alım 20'de bırakılsaydı 2 paketle 4 renk **imkânsız** olurdu — ilan
  alınamayan bir minimum ilan ederdi. Operatörün 50'si bu çelişkiyi kapattı.
- **Beden satırı da güncellendi.** MOQ değişip etiket "min 48 pc" demeye devam
  etseydi ilan yalan söylerdi (KURAL 6'nın escrow tavanı dersi, ilan hâli).
- Polonun `min_colors`'ı **zaten 2**'ydi (kuru koşu satır basmadı), yani
  "≥2 colours" etiketi doğruydu; dokunulmadı.
- Uygulandı: run `34529155791`, 5 alan, zaman damgalı yedek.
- **Teklif mektubu GÖNDERİLDİ** (11 Eyl 2026 09:43 UTC, `shirtmaker@chello.at`,
  GARAGE LE PARIS adına, Almanca). Gövde sayfadaki HER rakamı taşıyor: model,
  renk, beden serisi, adet, UVP — artı bugün konan asgariler (10'lu karton /
  50 ad. / ≥4 renk ve 8'li karton / 56 ad. / ≥2 renk). Taslakta duran
  *"Teilmengen einzelner Farben sind möglich"* cümlesi **kaldırıldı**: ilanın
  ≥4 renk kuralını yalanlıyordu — mektup, sitenin reddedeceği bir şeyi vaat
  edemez.
- **PDF eklenemedi, sebebi kayda geçsin:** Gmail aracı eki yalnız satır içi
  base64 olarak alıyor; 1,8 MB'lık sayfa ~2,4 milyon karakter eder ve bu
  ortam o boyuttaki çıktıyı dosyaya düşürüp bağlamdan kesiyor. Okunabilir
  kalacak kadar küçültülmüş sürüm (2 sayfa JPEG, ~110 KB) bile tek çağrıda
  taşınamadı; daha fazla küçültünce **tablo okunmaz** oluyor, yani belge
  belge olmaktan çıkıyor. Mektup bu yüzden eksiz gitti ve sayfayı "istenirse
  gönderiyoruz" diye yazıyor. *Üçüncü taraf tarifesini herkese açık
  `uploads/` altına koyup link vermek bilerek YAPILMADI.*
- **`Light Blue` mektuba EKLENMEDİ** (öneriyi ben açmıştım, geri aldım):
  tarife sayfasının M3600 renkleri White, Green, Black, Navy, Bordeaux.
  İlandaki 6. renk oradan gelmiyor. Fotoğrafa bakınca sebebi görülüyor —
  sayfadaki ilk *"Color: White"* satırının fotoğrafı aslında **açık/buz
  mavisi** bir polo. Yani ilanın 6 rengi tutarlı, ama tarife sayfasının
  yazdığı ad "White"; mektupta sayfanın kendi adlandırması bırakıldı.
  *Fotoğraf ile metin çelişince fotoğraf kazanır (Pili Pérez dersi) — ama
  müşteriye giden metinde tedarikçinin kendi adlandırması esas alındı.*

**Satılan her rengin FOTOĞRAFI olmalı — şikâyet müşteriden geldi** (11 Eyl 2026;
alıcı BRITISHSTYLE / Michael Baumgartner, M3600 ilanının mesaj ipliğinde:
*"leider haben Sie nicht von allen angebotenen Farben ein Foto hier"*, ardından
operatör: *"bu ilana diger renkleride koy polonun"*).
- **Ölçüm haklı çıkardı:** ilan **6 renk** satıyor (Black, White, Navy, Bordeaux,
  Green, Light Blue) ama **3 fotoğraf** taşıyordu (navy, snow-white, green).
  Üç renk, bakılacak hiçbir şey olmadan sipariş edilebiliyordu. *Renk listesine
  bir ad eklemek ücretsiz, fotoğrafı eklemek değil — ikisi ayrı ayrı sayılmalı.*
- **Kaynak operatörün kendi Fred Perry tarifesi** (PDF, 2 sayfa). M3600'ün
  **9 satırı** var ama yalnız **5 gövde rengi adı**: tedarikçi gövdeyi
  adlandırıyor, **kragen/manşet şeridini (tipping) hiç yazmıyor**, yani aynı ad
  üç farklı artikelde tekrarlanıyor. Ayıran şey fotoğraf, o yüzden her kare
  **okunmadı, ölçüldü**: `black #232124` (R=G=B, nötr siyah), `bordeaux #501e21`,
  `light blue #d6dde5`.
- **"Black" etiketli bir satır aslında MEVCUT NAVY'ydi:** `#23212c`, beyaz/kırmızı
  şeritli — ilanda zaten duran navy fotoğrafının aynı giysisi. Onu "siyah" diye
  koymak, şikâyetin tam konusu olan sayfaya **birbirinden ayırt edilemeyen iki
  küçük resim** koymak olurdu. Gerçek nötr siyah seçildi.
- **Fotoğraf yeniden çerçevelenmedi:** tarifedeki kareler zaten çerçevenin
  %97-99'unu dolduruyor, ~0.91 dikey — canlı üç karenin aynı sıkı kadrajı; ve
  1316-1698px, ürün sahnesinin istediği 1120px'in üstünde. Metadata taşımasın
  diye temiz tuvale yeniden kodlandı. **Kapak değişmedi** (navy ilk sırada):
  istenen eklemekti (Balenciaga dersi).
- Yol: fotoğraflar repoya (`vestra/uploads/fredperry/`, deploy `uploads/`'u
  **eklemeli** senkronluyor), sonra `set-product.yml` + `product-fixes/
  m3600-colour-photos.json`. Geri okundu: `images (3) -> (6)`, Fred Perry'de
  referans verilen kare **8 → 11**, **kayıp/bozuk 0**.

**GERİ ALINDI 16 Eyl 2026 — o altı kare M7535'e döndü.** Operatör:
*"Sweatshirt gercek ... fotolar yanlis cekilmis onlari POLODAN alip Sweatshirt
tarafina koy diger fotolar ayni kalsin"*. Aşağıdaki kayıt **artık geçerli
değil**; olduğu gibi duruyor çünkü kararın neden verildiğini ve karelerin
kimin olduğunu ölçen kısmı hâlâ doğru — ama **polo bugün yalnız 6 polo
karesi taşıyor**, sweatshirt 11 (5 pack-shot + 6 detay). Uygulayan dosya
`product-fixes/fp-detail-photos-to-sweat.json`; iki kapak da değişmedi.
Geri alma öncesi **dosya adlarında renk kelimesi olmadığı yeniden
doğrulandı** — `listing_colours` renk slug'ını dosya adında arıyor ve
detay kareler artık M7535'in 5 renkli ilanında duruyor, yani orada bir
hangtag'in "Green'in fotoğrafı" diye bağlanma ihtimali ölçülüp elendi.

**M3600 poloda duran 6 kare M7535'IN — bilinçli operatör kararı, SİLME
(16 Eyl 2026'da GERİ ALINDI, yukarı bak)**
(14 Eyl 2026: *"Bu fotoları F.Perry Poloya ekle The Fred Perry Shirt — M3600
Twin Tipped"* → çelişki ölçülüp gösterildi → operatör **"yine de yaz"** dedi →
*"bu fotoları ek olarak f.perry karttan öncesine koy"* + *"diğer fotolar da
kalacak"*).
- **Bu not, bir denetimin "hata" sanıp geri almasını önlemek için var.** İlanda
  `fp-m7535-*` adlı altı dosya duruyor ve bu **kusur değil**, kayda geçmiş bir
  karar. Vazgeçilmek istenirse tek koşu:
  `product-fixes/m3600-detail-photos.json`'dan son 6 satırı silip
  `set-product.yml`.
- **Karelerin M7535 olduğu ölçüldü, tahmin edilmedi:** hangtag'in kendisi
  *"STYLE: M7535 · COL: 87B · COL. DESC: LWGRN/ECRU/DSKBL · PRODUCT: CREW NECK
  SWEATSHIRT · SIZE: L"* yazıyor, ve ilk karedeki giysi ribanalı **bisiklet
  yaka** — pat yok, düğme yok, yaka yok. M3600 ise ilanın kendi `desc`'ine göre
  iki düğmeli patlı pamuk pike polo. Renk LWGRN, katalogdaki
  `fp-m7535-green.jpg` pack-shot'ının aynı kombini (beyaz defne, manşet ucunda
  ecru+lacivert şerit). Yani **alıcı polo sipariş edip vitrinde sweatshirt
  görüyor** — Balenciaga 612966/612965'in aynı sınıfı, bu kez bilerek.
- **Söylenen açık maliyet:** bu ilanın alıcısı (BRITISHSTYLE / Baumgartner)
  zaten bir kez *"her renge fotoğraf yok"* diye yazmış biri, yani fotoğraflara
  bakan bir müşteri. Operatöre söylendi, karar tekrarlandı, uygulandı.
- **DOSYA ADLARI M7535 OLARAK BIRAKILDI.** `m3600-detail-style-label.jpg`
  demek, M7535 yazan bir etiketin karesine M3600'un etiketi adını vermek
  olurdu — kendini yalanlayan bir kayıt. Ad bir metin değil, **iz**: aylar
  sonra "bu kare neden burada" sorusunun cevabı dosyanın adında duruyor.
  Yollar tam, işlevsel fark yok.
- **Ad hiçbir RENK KELİMESİ taşımıyor ve bu zorunluydu.** `listing_colours`
  mektubu (Baumgartner'a giden *"her renge foto var"* iddiası) renk slug'ını
  **dosya adında** arıyor, en uzun ad önce, her dosyayı bir kez kullanıyor —
  adında `green` geçen ikinci bir dosya, pack-shot yerine bir hangtag karesini
  *"Green'in fotoğrafı"* diye bağlayabilirdi. **İki yön de ölçüldü:** altı yeni
  adın hiçbiri altı renkten (black/white/navy/bordeaux/green/light blue) birine
  eşleşmiyor, ve altı pack-shot hâlâ **6/6** eşleşiyor.
- **Sıra:** mevcut 6 pack-shot önde (**kapak değişmedi** — "ek olarak" denildi),
  yeni 6 kare arkada ve kendi içinde **giysi kareleri kart/etiket karelerinden
  önce** (ön, defne, boyun etiketi → stil kartı, hangtag'ler, COMMUNITY kartı):
  *"karttan öncesine koy"* cümlesinin karşılığı.
- Fotoğraflar 1536×2048 telefon kareleri → uzun kenar 1300–1600 px, temiz
  tuvale yeniden kodlandı (EXIF/ICC **0**), 190–265 KB. Stil kartı bilerek en
  büyük ölçüde ve **küçültme sonrası çizdirilip** `STYLE: M7535` satırının hâlâ
  okunduğu doğrulandı — okunamayan bir kimlik karesi kimlik karesi olmaktan
  çıkardı.
- Geri okundu (run `34871739653`): `fp-m3600-polo` **12 foto**, kapak hâlâ
  `m3600-navy.jpg`, Fred Perry'de referans verilen kare **11 → 17**,
  **kayıp/bozuk 0**. `fp-m7535-sweat` **5 fotoda, dokunulmadı**.

**Cevap mektubu: `reply_letter=listing_colours`** (aynı gün; operatör:
*"bu adama email gönderecektin les garage adi ilen"*).
- **Mektup İLANIN SATICISI adına çıkıyor** (`GARAGE LE PARIS`) — bu, KURAL 8'in
  **bilinçli istisnası**: o kural satıcı adını platform **mesajlaşmasında**
  gizler, bu ise operatörün bilerek dükkânın adıyla yolladığı bir e-posta.
  **Reply-To yine `support@vestrasales.com`** — alıcının cevabı satıcının kendi
  gmail'ine değil platforma düşüyor. Ad **hesap kaydından** çözülüyor, metne
  gömülü değil: ilan bir gün başka satıcıya geçerse (bu depoda geçti) mektup
  eski dükkânın adıyla çıkardı.
- **Mektubun tek iddiası "artık her rengin fotoğrafı var" ve iddia
  DOĞRULANMADAN gönderilmiyor:** renk→foto eşleşmesi ilanın **kendi** renkleri
  ile **kendi** görsellerinden kuruluyor; eşleşmeyen tek renk kalsa iş **DURUR**.
  Eşleşme **sınırlı** (ayıraç/baş-son) ve **uzun ad önce**, her dosya bir kez:
  `White` → `m3600-snow-white.jpg`, yalın bir `Blue` → `m3600-light-blue.jpg`'i
  `Light Blue`'nun elinden **alamıyor**. Gevşetip düz `str_contains` yapınca
  `Blue` bir "blueberry" dosyasıyla eşleşiyor — **ölçüldü**, varsayılmadı
  (mango/zara dersinin fotoğraf hâli; bedeli burada alıcıya **yanlış rengin**
  fotoğrafını göstermek).
- **Fiyat YOK.** Alıcının toptan fiyatı hesap kapısının arkasında; sayfanın
  göstermeyebileceği bir rakamı mektuba yazmak, sepetin kabul etmediği rakamı
  yazmakla aynı sınıf (KURAL 6). Mektup **asgariyi** yazıyor ve onu da
  `moq`/`min_colors`/`size_step`'ten **okuyor** — sepetin uyguladığı üç sayı.
- **Markaya özel cümle şablona GÖMÜLMEDİ**, parametre (`note`): "Fred Perry aynı
  gövde rengini farklı şeritle sürer" bu ilan için doğru, ilanlar için genel
  olarak değil. Cümle **söz vermiyor** — "şerit sizin için önemliyse rengi
  söyleyin, sipariş öncesi kesin ifadeyi teyit edeyim". Üç siyahtan birini
  gösterip "gelecek olan budur" demek, tutamayacağımız bir söz olurdu.
- Dil **Almanca** (alıcı Almanca yazdı, Avusturya); hitap paylaşılan blokta
  İngilizce kuruluyor, `lang=de` verildiğinde **hesaptaki adla** Almancaya
  çevriliyor — ad girdiye yazılmıyor (müşterinin soyadı herkese açık koşu
  başlığına girmez).
- Ölçüm (`send=false`, run `34583824299`): 6/6 renk eşleşti, alıcı hesaptan
  çözüldü (`buyer, active`, VAT kayıtlı), From `GARAGE LE PARIS`, konu doğru,
  **gönderilmedi**. KURAL 18: operatör "gönder" diyene kadar bekliyor.

**Aynı mektup "bebildertes Sortimentsblatt" oldu: İKİ ilan, FİYATLARLA**
(alıcı ikinci kez yazdı — *"bitte senden Sie und das bebilderte
Sortimentsblatt"*; operatör: *"halen beildert diyor....adam"* + **"ilandaki
fiyatlar ile beraber gönder"**).
- **İkinci şablon yazılmadı.** İkinci istek, birincisinin üstüne fiyat ekliyor;
  ayrı bir mektup yazmak aynı olguyu ikinci kez yazmak olurdu (`desc`/`sizes`,
  faturanın üç katmanı, dört mektup gövdesi — bu ders bu depoda pahalıya
  öğrenildi). `vestra_tpl_listing_colours` artık **blok listesi** alıyor: bir
  ilan da, iki ilan da aynı gövdeden çıkıyor.
- **Fiyat VARSAYILAN OLARAK YOK** (`prices=on` açıkça istenmeli). Kapısı kapalı
  bir alıcıya rakam yazmak, sayfanın göstermediği fiyatı mektupta söylemektir —
  KURAL 2b'nin birebir tersi. Koşu bu yüzden **alıcının fiyat kapısını da
  basıyor**; bu alıcıda **AÇIK** çıktı, yani mektuptaki rakam sayfasındakiyle
  aynı.
- **Merdiven tek yerde: `vestra_price_ladder()`** (`inc/products.php`), o da
  sepetin kendi `vestra_unit_price()`'ini çağırıyor. **İlk basamak MOQ'da
  başlar:** ilanların çoğunda `tiers` ilk satırı `min=1` yazıyor ve "ab 1 Stück
  70,20 €" **sepetin kabul etmediği** bir adedin fiyatını ilan ederdi. Fiyatı
  değiştirmeyen basamak düşüyor; **yükselen basamak düşmüyor** (gizlemek, pahalı
  tarafta eksik bilgi vermek olurdu).
- **Vergi iddiası YOK.** Fiyatlar brüt (KURAL 5m) ama faturada KDV gerçekten
  alınacak mı **fatura başına** bir karar ve AB içi ticari alıcıda çoğu zaman
  ters yükleme. Her iki durumda da doğru olan tek cümle yazılıyor: *"pro Stück,
  zzgl. Versand"*. Doğrulayamadığımız şey yazılmıyor.
- **Şerit etiketi modeli de taşıyor** (`M3600 · Black` / `M7535 · Black`): aynı
  renk adı iki modelde de var ve etiketsiz bir kare hangisinin olduğunu
  söylemiyor. Etiket **ilanın kendi SKU'su**, başlıktan ayıklanmış bir parça
  değil. Her karenin bağlantısı **kendi** ürün sayfasına gidiyor.
- **Marka sayfası ancak GERÇEKTEN açılıyorsa** düğmeye ve gövdeye giriyor:
  `wholesale.php`'nin kendi iki koşulu (slug çözülüyor mu + o markada canlı ilan
  var mı) çağıran tarafta sorulup geçiliyor. Elle yazılan bir adres, son ilan
  satıldığı gün 404 olurdu (KURAL 9).
- **İnceleme adımının yeri: `copy=true`.** Mektup birebir kurulup **operatörün
  kutusuna** gidiyor, müşteriye gitmiyor (`payment_due`'nun `copy_only`'siyle
  aynı ilke). Kütüğe gövde basmak seçenek değildi: hitap müşterinin adını
  taşıyor ve o kütük herkese açık.
- Ölçüm (`send=false` run `34589855337`, `copy=true` run `34590116233`):
  polo 6/6 renk-foto + kademe **56 / 96 / 192**, sweatshirt 5/5 + tek kademe
  **50**, `/wholesale/fred-perry` **2 canlı ilanla açılıyor**, satıcı
  GARAGE LE PARIS, kapı AÇIK, gövde 1.456 karakter. Kopya operatörün kutusunda
  **okundu**: Almanca umlautlar, iki ilanın da asgarileri ve fiyatları yerinde.
  **Müşteriye gitmedi** — KURAL 18, operatör "gönder" diyene kadar bekliyor.
- Test: `tests/listing_sheet_test.php` (43 iddia, iki yön). Düşebildiği
  doğrulandı: merdiven MOQ yerine ham kademeden başlayınca **11 kırmızı**,
  fiyat varsayılanı `on` olunca **3**, şerit etiketi modeli bırakınca **2**.

**Sortiment mektubu fiyat listesini de TAŞIYOR; ek üreteci TEK gövdeye indi**
(operatör, 11 Eyl 2026: *"ürünleri bir önceki email ile yaptigin gibi resim ile
listele ve link ver"*).
- Şikâyetin sebebi ölçülebilir: `price_list` mektubu **hiçbir ürünün adını
  yazmıyordu** — "2 Artikel" deyip eke işaret ediyordu. `listing_colours` ise
  ürünleri, 11 fotoğrafı ve ilan başına bağlantıyı zaten taşıyordu. Çözüm
  **eki gösteren mektuba taşımak**; fotoğraf/bağlantı makinesini ikinci şablona
  kopyalamak bu deponun defalarca ödediği hata olurdu.
- **Ek VARSAYILAN OLARAK YOK** (`attach=both|pdf|xlsx`): mektup zaten her rakamı
  taşıyor, istenmedikçe 300 KB taşımasının sebebi yok.
- **Liste MEKTUPTAKİ İLANLARIN markasından** üretiliyor; blokler birden fazla
  markadaysa **ek konmuyor** ve sebebi yazılıyor — o ürünler hakkında olmayan bir
  liste iliştirmek mektubun kendi kapsamını yalanlardı.
- **Üçüncü kopya doğmadan önce üçü birleştirildi:** `$listAttach` (workflow'da,
  `$genList`'in yanında) — imza denetimi, "ne kırpıldı" satırı, PDF'e gömülü
  fotoğraf sayısı ve Brevo'nun ~10 MB tavanı tek gövdede; `brand_catalog`,
  `price_list` ve `listing_colours` üçü de onu çağırıyor.
- **İddiam ilk sabotajda DÜŞMEDİ:** "ikinci kopya yok" kontrolü
  `'wholesale-list.php', '%PDF'` dizgesini sayıyordu ve o dizge yalnız
  üreticinin içinde geçiyor — bir dala eklenen **doğrudan** `$genList(...)`
  çağrısını hiç görmüyordu, sabotaj yeşil kaldı. Ölçüt artık **dalın kendisi**:
  üç mektup dalının hiçbirinde doğrudan üreteç çağrısı olmamalı (`docs_women`'ın
  eskiden beri duran tek çağrısı kapsam dışı — o bir ek değil, mektubun kendi
  PDF'i). *Hiç düşemeyen bir iddia, iddia değildir — bu dosyada kayıtlıydı ve
  aynı gün üçüncü kez oldu.*
- Canlı ölçüm (`copy=true`, müşteriye gitmedi): ekler
  `VESTRA-fred-perry-price-list-2026-09.pdf` **14,7 KB / gömülü fotoğraf 2** +
  `.xlsx` **5,0 KB**, gövde 1.456 → **1.513** karakter (eklenen tek cümle),
  fiyatlar canlı kayıttan **39,00 / 35,50 / 32,00** ve **39,90**.
- Test: `listing_sheet_test.php §7b` (toplam 90 iddia). Düşebildiği doğrulandı:
  ek cümlesi istekten yazılınca **3 kırmızı**, çok markalı mektuba ek konunca
  **1**, bir dala doğrudan üreteç çağrısı eklenince **1**.
- **BAUMGARTNER'A GİDEN SÜRÜMDE EK YOK** (operatör, aynı gün, eki gördükten
  sonra: *"liste yapmadan ürün resimleri olsun daha önce yaptigin gibi"*).
  Yani `attach` **verilmiyor**; mektup ürünleri, 11 fotoğrafı, fiyatları ve
  bağlantıları taşıyor, PDF/Excel taşımıyor (gövde 1.513 → **1.456** karakter,
  ek cümlesi kendiliğinden düşüyor — bu yüzden cümle "gerçekten eklenen
  biçimden" yazılıyor). Özellik duruyor, **varsayılanı kapalı**: başka bir
  alıcıya liste istendiğinde `attach=both` yeter.
- **GÖNDERİLDİ** (operatör: *"tmm simdi müsteriye gönder"*): 11 Eyl 2026 11:37
  UTC, run `34594939210`, `shirtmaker@chello.at`, GARAGE LE PARIS adına,
  Reply-To `support@vestrasales.com`, gövde 1.456 karakter, konu *"GARAGE LE
  PARIS — bebildertes Sortiment: 11 Farben mit Preisen"*. Fiyatlar canlı
  kayıttan: **39,00 / 35,50 / 32,00** ve **39,90**. Ek yok.
  *Kütükteki "GONDERILDI" yalnızca **Brevo isteği kabul etti** demek* — bu
  dosyanın kendi uyarısı: `delivered` bile posta kutusu kanıtı değil.

**`price_list` — fiyat listesi mektubu, ve KAPSAMIN daralması** (operatör,
11 Eyl 2026, dört adımda yerleşti: *"ayrica liste gönder fiyatlari ile"* →
*"sweater larida ayrica ekle emaile"* → **"adam sadece fred perry polo yu
istiyor ancak sen f.perrey sweateri da gönder diger ürünleri degil!"** →
*"anbei die Preisliste von F.Perrey nicht unsere — ben satici degilim"*).
- **`brand_catalog`'un ikinci kopyası yazılmadı, ŞEKLİ farklı bir mektup
  yazıldı.** O mektup her modeli gövdeye basıyor — tek marka için doğru,
  800 kalemde okunmaz. `price_list` gövdede **sayı** tutuyor (kalem, marka,
  bölme dağılımı) ve **tek satır fiyat yazmıyor**: fiyatlar ekte ve ikinci bir
  yerde durmamalı. Paylaşılan şey paylaşılıyor: ekler sitenin **kendi**
  üreteçlerinden (`wholesale-list.php` / `wholesale-xlsx.php`), yani alıcının
  indirdiği dosya ile postaladığımız aynı dosya.
- **Ek boyutu gönderimden ÖNCE ölçülüyor:** Brevo eki base64 taşıyor (+%37) ve
  ~10 MB'de kesiyor — aşan mektup **hiç gitmez**. Sınır aşılırsa iş **durur** ve
  ne yapılacağını yazar; sessizce PDF'i düşürmek, operatörün gönderdiğini
  sandığından başka bir mektup üretirdi.
- **KAPSAM DARALDI ve bu bir düzeltme değil, operatör kararı.** Önce "sweater'
  ları da ekle" denince katalogdaki **50 sweatshirt/hoodie** (11 marka) ölçüldü
  ve listesi hazırlandı; operatör **"diger ürünleri degil"** deyince o liste
  **iptal edildi**. Müşteriye gitmemişti — yalnız operatörün kutusuna (`copy`).
  *Kapsamı daraltan bir talimat, yapılmış işi çöpe atsa bile talimattır.*
- **Bir MARKANIN listesi "BİZİM" listemiz değildir.** İlk sürüm *"anbei unsere
  Preisliste"* diyordu; operatör **"ben satıcı değilim"** dedi. VESTRA pazar
  yeri, malı satan taraf değil — marka kapsamlı listede cümle **markaya**
  atfediliyor (`brand_scope`), katalogun tamamında "bizim" kalıyor (o liste
  gerçekten VESTRA'nın kendi katalogu). Test iki yönü de tutuyor.
- **ARAMA TOKEN'IM üç kez müşteriye giden metne sızdı, üçü de canlı koşuda
  görüldü:** (1) konu satırı `Sweat — Preisliste` (oysa `cat=Sweat` benim
  satır bulmak için yazdığım parça) → kapsam adı artık **eşleşen kategori
  adlarından** kuruluyor, üç ve üzeri kategoride token'a düşüyor; (2) ek dosya
  adı `VESTRA-vestra-sweat-price-list.pdf` — 'vestra' iki kez, 'sweat' yine
  token → ad da kapsamdan kuruluyor; (3) `Marken u. a.: Fred Perry` ve
  `Sortiment: Apparel 50` satırları, açılış cümlesi zaten aynı şeyi söylerken
  ikinci/üçüncü kez yazıyordu → tek markalı/tek bölmeli listede basılmıyor.
  *Girdi alanı ile müşterinin okuduğu metin arasındaki mesafe bir satır: süzgeç
  için yazdığın kelimenin konu satırında ne işi olduğunu sor.*
- **`copy=true` artık PAYLAŞILAN gönderim bloğunda** — her `reply_letter`'in
  önizlenecek bir yeri var (KURAL 18). Kütüğe gövde basmak seçenek değildi:
  hitap müşterinin adını taşıyor, kütük herkese açık. `send=true` ile birlikte
  verilirse **kopya kazanır**: yanlışlıkla iki bayrak birden verilince müşteriye
  gitmez.
- **Kendi ölçüm hatam, iki kez:** "kopya, gönderim kapısından önce mi" iddiası
  önce `send=false` satırını aradı — dosyada **altı** iş var ve `strpos` ilkini
  buldu; sonra `$ok = vestra_send_mail($to, $subject, $body,` kalıbını aradı —
  o da **iki** yerde. İddia doğru çalışan kodda iki kez kırmızı döndü. Ayırt
  eden şey gönderen adı (`$fromName`); iddia artık paylaşılan bloğa özgü tek
  satıra bağlı. *Bu deponun altı kez kaydettiği "kontrol yanlış yere bakıyor"un
  yedinci ve sekizinci vakası, üstelik testin kendisinde.*
- Canlı ölçüm (hepsi `copy=true`, müşteriye **hiçbiri gitmedi**): sweatshirt
  listesi **50 kalem / 11 marka**, PDF 279 KB + Excel 11 KB; Fred Perry listesi
  **2 kalem**, `VESTRA-fred-perry-price-list-2026-09.pdf/.xlsx`, gövde 632
  karakter; alıcının fiyat kapısı **AÇIK** (yani ekteki rakamlar sayfada da
  görünüyor). Bir koşu `dial tcp: i/o timeout` ile düştü — SSH hiç bağlanmadı,
  yani o koşu mektup hakkında **hiçbir şey söylemiyor**; runner değişince geçti.
- Test: `tests/listing_sheet_test.php` §8–9 (toplam 74 iddia). Düşebildiği
  doğrulandı: gövdeye bir fiyat sızınca **1 kırmızı**, eklenmeyen bir biçim
  adlanınca **3**, inceleme yolu dala geri taşınınca **3**, marka kapsamı yok
  sayılınca **4**.

**3. parti (İKİNCİ mektup) gönderildi — 200/250, kalan 50 KOTAYA takıldı**
(operatör, 10 Eyl 2026: *"kampanya gönder 2. email almayanlara yeni ürünler ve
brandslerden.."* + *"250 ad."*).
- Kip: `send-outreach` → `new_collection=true` + `newcoll_letter=shoes`.
  Bu kip **ilk mektubu almış ama ikinciyi almamış** leadleri seçiyor
  (`last_newcontacted`/`last_newcollection_at` damgası), yani "yeni emaillere
  gönderme" şartı kipin kendisinden geliyor; satıcı hesapları ayrıca eleniyor.
  Kuru koşu **250** verdi (de=74, fr=17, nl=57, en=102).
- **Gerçek gönderim koşu başına 50'ye kırpılıyor** (`!$DRY && $COUNT > 50`),
  yani 250 için **5 koşu** gerekiyor. Damga koşular arasında da tutuyor, o
  yüzden ikinci koşu birincinin gönderdiğini seçmiyor — `add-and-send`'in
  2 Eylül'deki çift gönderim tuzağı burada YOK.
- Koşular sırayla (paralel değil — `leads.json` oku-değiştir-yaz):
  `34538049383` 50, `34538425397` 50, `34538544847` 50, `34538677138` 50,
  **`34538804541` 0** — *"istenen 50, ayrılan pay düşüldükten sonra
  kullanılabilir 4 (toplam kalan 64)"*. Kapı **hep ya da hiç**: 4 mektup
  gönderip yarım parti bırakmıyor. **Toplam 200 gönderildi, 0 hata.**
  Kalan 50, kota yenilenince tek koşu.
- **Kalan 50'nin devamı: 10 gitti, 34 SAATİ BEKLİYOR** (operatör, 11 Eyl 2026:
  *"250 kampanya email göndrmeye devam et daha önce sadece 1 defa
  gönderdiklerine"*). Darboğaz artık **kota değil, `newcoll_min_days=3`**:
  kuru koşu (`34605177414`) *"uygun secilen: 11 / hedef: 50"* ve
  *"YAS: ilk mektubun uzerinden 3 gun gecmedigi icin elenen: **34**"* dedi;
  kota bol (255 kalan, 60 ayrılmış). **Eşik düşürülmedi:** mektup birinci
  cümlesinde *"size daha önce yazmıştık"* diyor ve iki gün sonra gelen bir
  ikinci mektup o cümleyi yalanlar, posta listesi gibi okunur — kuralın var
  olma sebebi bu, ve "devam et" talimatı onu kaldırmıyor.
- **`factoryoutlet.gr` yine elendi** (`skip_email_regex=/@factoryoutlet\.gr$/`).
  Sebep bu dosyada zaten kayıtlı: firma adı kayıtta **"Αρχική"** ve
  `vestra_tpl_new_collection_shoes()` selamlamayı `"Hello".($co!==''?" ".$co:'')`
  diye kuruyor — yani mektup *"Γεια σας Αρχική,"* diye açardı. **Varsayımla
  değil, şablona bakılarak** doğrulandı. Süzgeç `skip_names` yerine adres
  regex'i: `strtolower` ASCII dışını değiştirmiyor, yani Yunanca bir girdi
  teorik olarak çalışırdı ama Actions girdisinden sunucuya kadar kodlamanın
  bozulmadığını **ölçemeden** güvenmek gerekirdi; ASCII bir desen bu riski
  hiç doğurmuyor. Desen `@…$` ile **bağlandı** (mango/zara dersi).
- Sonuç (run `34605357547`): **gönderildi 10, hata 0** — Hirmer (de), Galiano /
  Base Blu / IL DUOMO / RBoutique (it), Louis Copeland (en), Jill et Juliette /
  Blue Pacific (nl), INTRO (en), ADDITION ADELAIDE (ja). 250'lik partide
  toplam **210** mektup.
- **Bu 10 mektup, ilk 200'den FARKLI bir rakam taşıyor ve doğrusu bu:**
  iç giyim **146** (ilk 200'de 16 yazıyordu — `cat` metninde "underwear" arayan
  sayım hatası, aynı bölümde kayıtlı). Düzeltme aradaki günlerde inmişti;
  geri alınmadı, çünkü 16 eksik bir sayıydı.
- **Kalan 34 ne zaman uygun olur:** her lead kendi ilk mektubundan 3 gün sonra.
  8 Eylül 16:53 partisi (Kiliwatch, Underground, Run Colors, Pilgrim Surf,
  Good As Gold, FRAME, The Space, Story Online, Capsule …) bugün ~16:53 UTC'de,
  10 Eylül'de eklenenler 13 Eylül'de. **Kendiliğinden gönderilmedi** — KURAL 18:
  bir turdaki "gönder" bir sonraki mektubun izni değil.
- Mektubun rakamları canlı sayıldı: **ayakkabı 335 artikel**, iç giyim, ve
  markalar Burberry, Givenchy, BALMAIN, Dolce & Gabbana, Fendi, Balenciaga.
  DNS süzgeci 13 adayı posta alamayan alan adı diye eledi.
- **İÇ GİYİM SAYISI EKSİKTİ ve 200 mektup öyle gitti.** Ayakkabı **bölmeden**
  sayılıyordu, iç giyim ise `cat` metninde "underwear" arayarak — o bölmenin
  kategori yaprakları **Bras / Basics / Socks & Hosiery / Sleepwear /
  Lingerie**, hiçbirinde o kelime geçmiyor. Sonuç: 146 ilanlık bölmeden
  **16** sayıldı. Yalan değil (16 artikel gerçekten var) ama sunduğumuz
  koleksiyonu küçük gösteriyor. Düzeltildi: iki satır da
  `vestra_product_section()`'dan sayılıyor. *Aynı cümlenin iki satırını iki
  ayrı ölçüte bağlamak, birini sessizce eksik bırakır.*
  Test: `wave3_letter_test.php §7` (eski sayım geri konunca 3 kırmızı).

**KURAL 31 — ÜÇÜNCÜ MEKTUP: adıyla sayılan evler, KENDİ damgasıyla** (operatör,
18 Eyl 2026: *"herkese bastan 3. email gönder ve gece yarisi devam et 295 email
gönder simdi yeni ürünler ile Galerry markasi ve F.Perry , Gucci , Dsq2"*).

- **İKİNCİ mektupla karıştırma — AD ÇAKIŞMASI KAYITTA:** bu depoda zaten
  `wave3_letter_test.php` var ve o **üçüncü PARTİ**'yi, yani İKİNCİ mektubun
  ayakkabı/iç giyim sürümünü ölçüyor. Üçüncü MEKTUP başka bir şey:
  `vestra_tpl_wave3_brands()` + `tests/wave3_brands_test.php`. Damgalar da ayrı:
  `last_newcollection_at` (ikinci) ↔ **`last_wave3_at`** (üçüncü; hesapta
  `wave3_at`).
- **Eski notun düzeltilmesi, susulmadı:** `shoes` şablonunun yorumu *"iki ayrı
  damga tutmak aynı firmaya üçüncü bir soğuk mektup yolunu açardı"* diyordu ve
  üçüncü mektubu ilkesel olarak reddediyor gibi okunuyordu. Reddedilen şey o
  değildi: **AYNI SIRANIN** iki damgaya bölünmesiydi (winter ve shoes ikisi de
  *ikinci* mektup). Operatörün kararı yorumun içine yazıldı — susup o notla
  çelişen bir kod bırakmak, sonraki okuyucuya hangisinin geçerli olduğunu
  okunamaz yapardı (KURAL 21d'nin aynı dersi).
- **AYRI ŞABLON, çünkü ikinci mektup BÖLME sayıyor** ("335 ayakkabı, 146 iç
  giyim") ve markaları yalnız bir kuyruk satırında anıyor; bu turda istenen şey
  bölme değil **DÖRT EV**. Aynı şablona üçüncü bir kip eklemek, bölme cümlesini
  bir koşulla susturup marka cümlesini şişmanletmek olurdu ve iki mektup ilk
  marka değişikliğinde ayrışırdı.
- **ÜYE SÜRÜMÜ AYNI GÖVDEDE** (`$member=true`): lead metni üyeye **iki yerden**
  yanlış — *"size iki kez yazmıştık"* (üye zaten kendi isteğiyle kayıtlı) ve
  *"kayıt ücretsiz, ticari kaydınızı istiyoruz"* (yaptığı işi tekrar yaptırmak,
  KURAL 2b). Değişen yalnız açılış ve kapanış; evler, adetler ve konu aynı.
  Ayrım `$L` tablosunun **içinde** duruyor: bir dili düzelten kişi o dilin
  altı satırını da yan yana görüyor.
  **Üye kapanışı fiyatın NEREDE olduğunu bilerek SÖYLEMİYOR** — bu kipte hesabın
  fiyat kapısı kapalı olabiliyor (koşu o sayıyı gönderimden önce basıyor) ve
  "listenizde görürsünüz" demek kapalı bir hesabı duvara yollamak olurdu. Yerine
  yalnızca **bizim** yapacağımız bir şey vaat ediliyor (istenirse artikel
  listesini göndermek), ki o kapı durumundan bağımsız olarak doğru.
- **YENİ GİRDİ EKLENMEDİ ve bu bir tercih değil:** `workflow_dispatch` en fazla
  **25 girdi** alıyor ve `send-outreach.yml` tam 25'te; 26.sı dosyayı **hiç
  dispatch edilemez** yapardı (CLAUDE.md bu sınırı `diag-live.yml`'de bir kez
  kaydetti). Üçüncü mektup bu yüzden `newcoll_letter`'ın üçüncü değeri:
  `winter | shoes | **wave3**`. **Tanınmayan değer artık SESSİZCE winter'a
  düşmüyor, işi DURDURUYOR** — düşseydi `wave3` yerine `wave-3` yazan bir koşu
  üçüncü mektup sandığı sırada **ikinciyi** gönderir ve yanlış damgayı tüketirdi.
- **YAŞ ÖLÇÜSÜ İKİNCİ MEKTUBA GÖRE** (`$NC_PREV`). İlk mektuptan ölçseydik dün
  ikinci mektubu almış bir lead bugün üçüncüyü de alırdı. Firma-bazlı harita
  (`$ncSeenDom`) da **bu partinin** damgasına bakıyor: ikincininkine bakmak,
  üçüncüyü hiç almamış firmaların tamamını "almış" sayıp havuzu sıfırlardı.
  Üçüncü mektup ayrıca **İKİ damga birden** arıyor (ilk + ikinci): mektubun ilk
  cümlesi *"size iki kez yazmıştık"* diyor ve yalnız ilk mektubu almış birine
  gitse mektup kendi açılışını yalanlardı.
- **RAKAMLAR CANLI KAYITTAN, metne gömülü değil.** Çağıran evleri
  `vestra_products()` üzerinden sayıyor; eşleşme harf duyarsız **TAM eşitlik**
  (alt dize olsaydı yarın gelecek bir "Gucci Kids" aynı satıra eklenirdi —
  mango/zara dersi). **Adıyla istenen bir ev katalogda yoksa iş DURUYOR** ve
  yakın adayları basıyor: sessizce listeden düşürmek, operatörün gönderdiğini
  sandığından başka bir mektup göndermek olurdu.
- **ÜYE DALINA YAŞ KURALI EKLENDİ ve ilk koşuda 127 hesap yakaladı.** 17 Eylül'de
  bu **elle** yapılmıştı (Angebot alan 7 hesap aynı günün Winter partisinden tek
  tek çıkarılmıştı); kural hatırlanmaya bırakıldığı sürece bir sonraki parti onu
  kaçırıyor. Ölçü aynı knob (`newcoll_min_days`, 0 = kapalı) ve **başka** kampanya
  damgalarına bakıyor, kendi damgasına değil. Kuru koşu: uygun **5**, *"son 3
  günde başka bir kampanya mektubu aldı"* diye elenen **127** — yani kural
  olmasaydı 127 üye, bir gün arayla ikinci bir kampanya mektubu alacaktı.
- **CANLI KURU KOŞU (18 Eyl 2026, deploy `2bf7f2d7`):**

  | | |
  |---|---:|
  | Lead havuzu (ilk + ikinci mektubu almış, üçüncüyü almamış) | **288** |
  | Üye havuzu | **5** (+127'si 3 günlük yaş kuralında) |
  | ölü alan adı (DNS) | 0 |
  | Mektuptaki evler | Gallery Dept. **9** · Fred Perry **2** · Gucci **15** · DSQUARED2 **64** |
  | Diller | en 124 · nl 39 · it 38 · de 35 · fr 21 · es 13 · ja 5 · el 4 · cs 4 · pt 2 · pl 1 · ko 1 · az 1 |

  **288, operatörün söylediği 295'e çok yakın ve bu tesadüf değil:** havuz
  büyük çünkü bu kip *iki mektubu da almış* olanları seçiyor — 4, 8 ve 17
  Eylül'de kaydedilen "havuz tükendi" ölçümleri **soğuk** havuz içindi.
- **Gerçek gönderim koşu başına 50'ye kırpılıyor**, yani 288 için **6 koşu**;
  koşular **sırayla** (paralel koşu `leads.json`'ı ezer). Damga koşular arasında
  tuttuğu için ikinci koşu birincinin gönderdiğini seçmiyor.
- **Fred Perry mektupta "2 artikel" diyor** ve bu doğru (katalogda gerçekten 2
  ilan var) ama 64'lük DSQUARED2'nin yanında ince duruyor. Rakam uydurulmadı;
  istenirse o ev listeden çıkarılır — karar operatörün.
- Test: `tests/wave3_brands_test.php` (**123 iddia**, iki yön). Düşebildiği
  doğrulandı, **her sabotajın gerçekten uygulandığı `grep -c` ile ayrıca
  yazdırılarak**: üye sürümü kapatılınca **6 kırmızı**, sıfır artikelli ev
  basılınca **3**, konudaki üç-ad kırpması kalkınca **1**, damga ikinciye
  dönünce **1**, yaş ilk mektuba dönünce **1**, eşleşme alt dizeye gevşeyince
  **1**, üye dalı lead sürümünü gönderince **1**.
  *İlk sabotaj denemem `perl -0pi` kaçışı yüzünden **hiç uygulanmamıştı**
  (`grep -c` = 0) ve "iddia düşmüyor" dedirtecekti — bu dosyada kayıtlı tuzak,
  python ile tekrarlanınca çıktı.*

**KURAL 31 — GÖNDERİLDİ (18 Eyl 2026, operatör: *"devam et göndermeye basla"*):
242 mektup, hata 0; kalan 50 KOTAYA takıldı.**

| Parti | Sonuç |
|---|---:|
| Lead 1-4 (50'şer, sırayla) | **200** |
| Lead 5 (istenen 50) | **0 — kota kapısı durdurdu** |
| Üye (`member_spec=letter=wave3\|skip=verify`) | **4** |
| Lead 6 (kalan kotaya göre 38) | **38** |
| **Toplam** | **242** (238 lead + 4 üye), **hata 0** |

- **Kota kapısı HEP YA DA HİÇ ve tam da bunun için var:** beşinci parti
  *"istenen 50, ayrılan pay düşüldükten sonra kullanılabilir 42 (toplam kalan
  102)"* deyip **hiçbir şey göndermedi** — 42 mektup gönderip yarım parti
  bırakmadı. Sonraki koşu 38 ile geçti. *Bir kapının "başarısız" görünen
  koşusu, aslında kapının çalıştığı koşudur.*
- **ÜYELER ÖNCE ALINDI, bilinçli:** kalan 102'nin ayrılan pay sonrası 42'si
  kampanyaya açıktı ve üye havuzu yalnız 4 kişiydi. Lead'leri 42'ye kadar
  doldurup üyeleri yarına bırakmak, **dört kayıtlı müşteriyi** soğuk listenin
  kuyruğuna koymak olurdu. Sıra: üye 4 → lead 38.
- **Atlama listesi GÖNDERMEDEN ÖNCE kuru koşuyla okundu** (17 Eylül'ün dersi:
  `skip` değerleri `[,\s]+` ile bölünüyor). `Verify Test Co` için **tek,
  ayırt edici token** (`verify`) kullanıldı — `co` gibi bir parça başka
  firmaları da tutardı. Kuru koşu `skip_accounts 1` dedi ve kalan dördü tek tek
  bastı; yanlış yakalanan yok.
- **Üye kapısı 4/4 AÇIK**, yani dördü de rakamsız değil **rakamlı** sürümü
  aldı — ama bu mektupta zaten fiyat yok; ölçüm, kapalı bir hesaba "listenizde
  görürsünüz" denmediğini doğrulamak için okundu (§ üye kapanışı).
- **KALAN 50 LEAD ölçüldü, tahmin edilmedi** (kuru koşu, aynı kip):
  `en 19 · it 12 · de 6 · ja 5 · es 3 · nl 3 · fr 1 · pt 1` = **50**;
  kategori dağılımı da 45 + 5 = 50 diyor, yani sayım kendi içinde tutuyor.
  Kota yenilenince **tek koşu** yeter. **Kendiliğinden gönderilmedi** —
  KURAL 18: bir turdaki "gönder" bir sonraki mektubun izni değil.
- **Diller (238 lead):** en 105 · nl 36 · de 29 · it 26 · fr 20 · es 10 ·
  cs 4 · el 3 · pt 1 · pl 1 · ko 1 · az 1.
- **Kütükteki `GONDERILDI` yalnızca "Brevo isteği kabul etti" demek** — bu
  dosyanın kendi uyarısı: `delivered` bile posta kutusu kanıtı değil.

**KURAL 31 — "295'e tamamla": TAVAN 292 ve bu ölçülerek söylendi** (operatör,
18 Eyl 2026: *"toplam 295 emaile tamamla"*). Rakam, üçüncü mektubun kendi
hedefiydi (*"gece yarisi devam et 295 email gönder"*) ve üçüncü mektup kanalının
**gerçekten sahip olduğu** aday sayısı 292: 288 lead (238 gönderildi + 50 bekliyor)
+ 5 üyenin 4'ü (biri test hesabı). **Kalan 3 bu kanalda YOK** — havuzu büyütmek
için bir muhafazayı gevşetmek (yaş kuralı, blocklist, firma tekilleştirme) rakamı
tutturur ama kuralı yakardı.

- **Kota kapısı ikinci kez çalıştı ve doğrusu bu:** 18:20 UTC'de gerçek koşu
  *"gunluk kota bitti: 58 kaldi, 60 tanesi sifre sifirlama / dogrulama / siparis
  bildirimi icin ayrilmis"* deyip **DURDU, hiçbir şey göndermedi** — 50'lik parti
  58'e sığmıyordu ve yarısını göndermedi. Damga yanmadı, yarım parti kalmadı.
  *Bir kapının "başarısız" görünen koşusu, kapının çalıştığı koşudur.*
- **DÖRT KANALIN TAMAMI aynı gün ayrıca ölçüldü** (295'i başka yerden kapatmak
  mümkün mü diye), kuru koşularla, **sırayla** (paralel koşu `leads.json`'ı ezer):

  | Kanal | Uygun | Gerçekten gönderilebilir |
  |---|---:|---:|
  | Soğuk havuz `min_brands=2` | 0 | 0 |
  | Soğuk havuz `min_brands=1` | 3 | **1** |
  | Lead 2. mektup (`shoes`) | 0 | 0 (11'i yaş kuralında) |
  | Üye Angebot (`fp_offer`) | 2 | **1** |
  | Üye Winter | 2 | 0 (ikisi de Angebot listesinde) |

  Yani üçüncü mektubun dışında bugün **2** adres var, ikisi de **başka mektup** —
  onları 295'e saymak, sayıyı tutturmak için kanal karıştırmak olurdu.
- **Soğuk havuzun 3 adayı ELLE okundu** (KURAL 1i: "kod geçirdi" ELE OKUNMADI
  demek değil) ve ikisi elendi:
  - **Peak Design** — 2010'da kurulmuş, **kendi markasını üreten** firma
    (peakdesign.com; ürünü foto/seyahat çantası). Bloklisteye eklendi.
  - **Livestock / `deadstock.ca`** — aynı firmanın `in..@` kutusu 31 Ağustos'ta
    mektup almış; bu ikinci kutusu.
  - **Throwbacks Northwest** (Seattle, tek mağaza, vintage spor giyim) geçti;
    zincir/distribütör/own-label değil. *Kanal uyumu zayıf (ikinci el), ama
    hiçbir kural elemiyor ve "kanalımız değil" bir kural değil — karar operatörün.*
- **SOĞUK YOLDAKİ FİRMA-BAZLI TEKİLLEŞTİRME BOŞLUĞU KAPATILDI.** 8 Eylül'de
  kaydedilmiş, 17 Eylül'de elle atlanmış, 18 Eylül'de **üçüncü kez** aynı kayıt
  (`deadstock.ca`) çıkınca koda taşındı: soğuk dal artık damgalı leadlerin **alan
  adlarını** da tutuyor (`$coldSeenDom`), harita hem geçmişten hem **bu koşudan**
  doluyor (ikincisi olmasaydı tek koşu aynı firmanın iki kutusunu seçebilirdi) ve
  **serbest posta sağlayıcıları muaf** — gmail'deki iki adres iki ayrı firma.
  *Bir kuralın hatırlanmaya bırakılması yetmiyor; Körfez listesi tam bu boşluktan
  geçmişti.*
- **`peak` ya da `design` TEK BAŞINA EKLENMEDİ:** ikisi de günlük kelime.
  Falsifikasyonda tek başına eklenince **4 gerçek butik** elendi (Design District,
  Peak Boutique, The Design Shop, Studio Design Milano) — `scarpa` dersinin aynısı,
  sessiz eleme yanlış gönderimden pahalı. Test: `blocklist_test.php §19/19b`
  (**429 iddia**); yeni adlar silinince **2 kırmızı**, tek kelime eklenince **4**.
- **Kalan 50, kota yenilenince tek koşu.** Diller ölçüldü, tahmin edilmedi:
  `en 19 · it 12 · de 6 · ja 5 · es 3 · nl 3 · fr 1 · pt 1` = 50; kategori
  dağılımı 45 + 5 = 50, yani sayım kendi içinde tutuyor.

**KURAL 31 — 19 Eyl 2026: kalan 50 GİTTİ; BEŞ kanalın da dibi görüldü;
kampanya toplamı 294, tavan bu.** (Operatör *"toplam 295 emaile tamamla"*
dedi, aynı cümleyi ertesi sabah tekrarladı.)

| Kanal | Uygun | Gönderildi | Kalanın sebebi |
|---|---:|---:|---|
| Üçüncü mektup — lead | 50 | **50** | havuz bitti (3'ü yaş kuralında) |
| Üçüncü mektup — üye | 2 | **1** | Neroke OÜ; öteki test hesabı |
| Soğuk havuz `min_brands=1` | 1 | **1** | Throwbacks Northwest |
| Lead 2. mektup (`shoes`) | 0 | 0 | **12'si** 3 günlük yaş kuralında |
| Üye Winter | 1 | 0 | yalnız test hesabı (Neroke bugün wave3 aldı) |

- **Bugün 52 mektup, hata 0. Üçüncü mektup kümülatifi 293** (242 + 50 + 1),
  **kampanya toplamı 294**. 295'e **bir** eksik ve o adres bugün **yok** —
  kota darboğaz değil (258 kalan). *Sayıyı tutturmak için bir muhafazayı
  (yaş kuralı, blocklist, firma tekilleştirme, test hesabı) gevşetmek
  mümkündü ve yapılmadı: eksik olan 1, kuralların doğru çalıştığının ölçüsü.*
- **Neroke OÜ üç kanalda birden uygundu** (wave3 / fp_offer / winter) ve
  **bir** mektup aldı. Seçim `wave3`: hesap 18 Eylül'de kaydolmuş, hiç
  kampanya almamış ve üçüncü mektup **dört evi** sayıyor (Angebot yalnız 2
  artikel). Üye sürümü *"size iki kez yazmıştık"* cümlesini taşımıyor, yani
  yeni bir kayıtta yalan söylemiyor (KURAL 31'in üye dalı). Damga düşünce
  öteki iki kanal onu **kendiliğinden** eledi — Winter kuru koşusu bunu
  *"son 3 gunde baska kampanya: 2026-09-19"* diye yazdı.
- **SOĞUK YOLDAKİ YENİ FİRMA TEKİLLEŞTİRMESİ CANLIDA ÇALIŞTI ve ilk koşuda
  ÜÇ kayıt yakaladı** (`yahoo.fr`, `beyondretro.com`, `piruetti.fi`) —
  hepsi *"ayni firmadan baska bir kutu zaten mektup almis"*. Dün elle atlanan
  `deadstock.ca` ve bloklisteye eklenen Peak Design listeden düştü, yani
  10 adaylık soğuk havuz bugün **1**'e indi ve o bir aday elle araştırılmış
  olanıydı. *Eleme artık hatırlamaya değil koda bağlı; ve ilk koşusunda
  hatırlamanın kaçıracağı üç kaydı buldu.*
- **295'in kalan 1'i ne zaman gelir:** 12 lead'in ilk mektubu 3 günü
  doldurunca ikinci mektup kanalı açılıyor (17–19 Eylül partileri → 20–22
  Eylül), ve 3 lead'in ikinci mektubu dolunca üçüncü mektup kanalı. İkisi de
  **kendiliğinden gönderilmedi** — KURAL 18.

**KURAL 31 — EV SIRASI GÜNCELLENDİ ve 58 ÜYEYE GÖNDERİLDİ (19 Eyl 2026,
akşam)** (operatör: *"3. emaillere devam et f.perry polo ,sweatshirts ve
lacoste , galerry ürünlerini öne cikar sonra gucco , balenciaga yi
ekle... 295 email gönder"*).

- **`$W3_WANT` artık altı ev, operatörün sırasıyla:** Fred Perry, Lacoste,
  Gallery Dept., Gucci, Balenciaga, DSQUARED2. Konu satırı yalnız İLK ÜÇ
  adı bastığı için "öne çıkar" talimatının karşılığı dizinin başı; **DSQUARED2
  bu turda adlandırılmadı ama listeden ÇIKARILMADI** — eylemsizlik eylem
  değil (M7535 kararının aynı dersi), gövdede en sonda duruyor, konuda hiç
  görünmüyor.
- **Fred Perry'nin yanına model numaraları eklendi** (`M3600, M7535`,
  `$W3_NOTE`) — yalnız madde satırında, konuda değil; ceviri gerektirmeyen
  bir tanımlayıcı (16 Eyl'de zaten yazılmış bir açığı kapatıyor: *"2 artikel,
  64'lük DSQUARED2'nin yanına konunca ince duruyor"*).
- **ÖNCE İKİ DRY-RUN, sonra gerçek gönderim** (KURAL 18): lead-wave3 ve
  üye-wave3 kanalları ayrı ayrı `count=300` ile tam sayıldı, altı evin de
  katalogda eşleştiği doğrulandı (`EV: Fred Perry -> 2 artikel (M3600, M7535)
  | Lacoste -> 12 | Gallery Dept. -> 9 | Gucci -> 15 | Balenciaga -> 20 |
  DSQUARED2 -> 64` — hiçbiri "KATALOGDA ESLESMEYEN EV" demedi).

| Kanal | Uygun (tam sayım) | Gönderildi | Not |
|---|---:|---:|---|
| Lead — üçüncü mektup | **0** | 0 | havuz hâlâ tükenmiş: hepsi ya zaten aldı, ya ikinci mektubu hiç almadı, ya 3 tanesi ikinci mektubun üzerinden 3 gün geçmediği için bekliyor |
| Üye — üçüncü mektup | 61 → **58** (3 elendi) | **58** | 2 koşu (50 + 8), hata 0 |

- **ELLE OKUMA 61 adayın tamamında yapıldı** ve üç hesap `member_spec`'in
  `skip=` alanıyla **bilerek** çıkarıldı, gönderilmeden önce ayrı bir kuru
  koşuyla skip listesinin gerçekten yalnız bu üçünü tuttuğu doğrulandı:
  - **`389h68843j6789)`** — kayıt formuna girilmiş garbled/otomatik görünen
    bir isim (`durum=pending`, `fiyat=KAPALI`). Selamlama `"Hello".($co!==''?
    " ".$co:'')` ile kuruluyor, yani mektup *"Hello 389h68843j6789),"* diye
    açardı — KURAL 2b/factoryoutlet.gr dersinin aynısı, bu depoda daha önce
    Winter kanalında da aynı hesap için kaydedilmişti.
  - **`Verify Test Co`** — adı test hesabı olduğunu söylüyor; 19 Eyl'in
    erken saatlerindeki Angebot/Winter ölçümlerinde de aynı gerekçeyle
    (elle) dışarıda bırakılmıştı.
  - **`Acera Soft LLC`** — VESTRA'nın kendi tüzel kişiliği "Acerasoft LLC"nin
    (her mektup imzasında geçen ad) yakın yazımı ve maskeli adresi
    (`a***@gmail.com`) operatörün kendi kayıtlı e-posta alan adıyla
    eşleşiyor: platformun kendi test/kurucu hesabı olduğu kuvvetle
    muhtemel. Kendi platformumuza kendi kampanya mektubumuzu göndermenin
    hiçbir karşılığı yok.
  - Tek harfli/boş firma adları (`d`, boş) ve tuhaf ama **garbled olmayan**
    adlar (`dropship`, `bad`, `noname`, `Vinted`, `Vinted reseller`) BİLEREK
    bırakıldı: bunları "test hesabı" saymak için elimde `389h68843j6789)`/
    `Verify Test Co` seviyesinde bir kanıt yok — SK Ventures gibi bu
    depoda gerçek sipariş sahibi olduğu doğrulanmış bir hesap da aynı
    listede terse bir adla duruyordu (bkz. O39419). Şüpheyle sessizce
    daraltmak, gerçek küçük işletmeleri elemek olurdu (mango/zara dersinin
    hesap hâli).
- **61 ile aynı günün erken saatlerinde kaydedilen "Üye wave3: Uygun 2"
  rakamı ÇELİŞMİYOR — ikisi FARKLI ŞEYİ ölçüyordu.** O ölçüm 295'e tam **1**
  eksik kapatmak için yapılmış küçük bir kapsam taramasıydı (muhtemelen
  küçük bir `count` ile), bu ölçüm ise `count=300` ile TAM SAYIM. Loop
  `count($cand) >= $COUNT` olunca duruyor, yani küçük bir hedefle koşan bir
  kuru koşu havuzun gerçek büyüklüğünü hiç görmez. *İki kayıt birbirini
  tutmuyor görünüyorsa önce ölçümün NE'yi saydığına bak — burada ikisi de
  doğruydu, sorulan soru farklıydı.*
- **Gerçek gönderim iki ayrı koşuda, SIRAYLA** (`accounts.json`
  oku-değiştir-yaz): birinci koşu 50/50 gönderdi (`hata: 0`), ikinci koşu
  kalan 8/8'i gönderdi (`hata: 0`). **58 mektubun dili** (iki koşunun
  `GONDERILDI` satırlarından tek tek sayıldı): en=36 · fr=12 · it=4 · es=4 ·
  de=2.
- **295 rakamı bugün de tutmadı ve bu operatöre söylendi:** lead kanalı hâlâ
  sıfır, üye kanalı 58 gönderdi. Kümülatif wave3 sayısı büyüdü ama "295"
  tek bir günün tek bir kanalından çıkacak bir sayı değil — bu depoda 19
  Eylül'ün kendi kaydı zaten bunu bir kez ölçmüştü.
- Test/kod tarafı: `tests/wave3_brands_test.php` 129 iddia (bkz. commit
  `70cea62c`); ev listesi ve not alanı için iki sabotaj (eski sıraya dönüş,
  notun bullete eklenmemesi) önce GERÇEKTEN uygulandığı doğrulanarak
  kırmızıya çevrildi.

**KURAL 31 — EV SIRASI YİNE DEĞİŞTİ ve MEKTUBA FOTOĞRAF ŞERİDİ EKLENDİ
(21 Eyl 2026)** (operatör: *"3. Email kampanyaları göndermeye devam et
F. PERRY polo sweatshirt , Galerry ürünlerini öne çıkar fotolar ile estetik
olsun"*).

- **`$W3_WANT` artık `Fred Perry, Gallery Dept., Lacoste, Gucci, Balenciaga,
  DSQUARED2`** — Gallery Dept. Lacoste'un önüne alındı. Bu talimat Lacoste'tan
  hiç söz etmiyordu, o yüzden yalnızca **konumu** değişti, listeden çıkarılmadı
  (M7535/DSQUARED2 kararının aynı dersi: adlandırılmayan silinmez).
- **Fotoğraf şeridi, `listing_colours` mektubunun ZATEN kanıtlı mekanizması:**
  `opts['shots']` → notify.php'de uzak `<img>` (cid ekli değil, ek/boyut sınırı
  yok — yalnız bir URL). `vestra_tpl_wave3_brands()` artık her ev için
  `$f['houses'][]['imgs']`'i okuyup `opts['shots']`/`opts['shots_title']`
  dolduruyor; `shots_title` **12 dilin de kendi konu satırından** alındı
  (`"jetzt ab Lager"`, `"désormais en stock"`, `"在庫入荷"` …) — yeni bir çeviri
  yazılmadı, zaten doğrulanmış metin yeniden kullanıldı.
- **Fotoğrafların kaynağı işi akışta (`$w3facts`), UYDURULMADI:** her ev için
  en fazla `$W3_SHOTS = 2` — SATILMAMIŞ (`vestra_is_sold_out`) ve DİSKTE
  GERÇEKTEN VAR (`is_file($home.'/public_html'.$img)`) ilk ürünler. Bulunamayan
  ev fotoğrafsız kalır, mektup yine gider — eksik kare gönderimi durdurmuyor.
  **2 seçildi, marka-özel bir dal yazılmadan**, çünkü Fred Perry'nin katalogda
  TAM 2 ilanı var (M3600 polo + M7535 sweatshirt): aynı tavan hem "estetik"
  isteğini hem operatörün "polo sweatshirt" diye ikisini birden andığı
  cümleyi otomatik karşılıyor.
- **Bağlantı `/catalog?brand=<ev>`** — `notify.php`'nin "Featured houses"
  marka-duvarı bloğunun (`brandsHtml`) zaten kullandığı aynı adres: girişsiz
  açılıyor (KURAL 19 yalnız FİYAT listesini kapatıyor) ve o markanın fotoğraflı
  Excel dökümünü indiriyor.
- **CANLI ÖLÇÜLDÜ, tahmin edilmedi** (`inspect-products` yerine doğrudan
  `send-outreach` dry-run'ının kendi `EV:` satırı): altı evin **altısı da**
  `foto 2/2` — `Fred Perry -> 2 artikel (M3600, M7535) · foto 2/2`,
  `Gallery Dept. -> 9 · foto 2/2`, `Lacoste -> 12 · foto 2/2`,
  `Gucci -> 15 · foto 2/2`, `Balenciaga -> 20 · foto 2/2`,
  `DSQUARED2 -> 64 · foto 2/2`.
- **Göndermeden önce operatörün kendi kutusuna GERÇEK bir önizleme**
  (`member_spec=letter=wave3|copy=true`, `dry_run=false` — KURAL 18): mektup
  gerçekten kuruldu ve **yalnız operatöre** gitti, hiçbir müşteriye
  dokunmadı, hiçbir damga düşmedi. Sonuç: `operator kopyasi: GONDERILDI |
  govde=673 karakter | foto=12` — 6 ev × 2 foto = 12, hesap tutuyor.
- **Ölçüm sırası:** deploy'un GERÇEKTEN indiği doğrulandı (run başarıyla
  tamamlandı), sonra iki `dry_run=true` (lead + üye, `count=300`) altı evin de
  katalogda eşleştiğini ve foto sayısını gösterdi, sonra üye kanalında
  `skip=` listesi **ayrı bir kuru koşuyla** doğrulandı (aşağıya bak), sonra
  `copy=true` önizlemesi, ancak ondan sonra gerçek gönderim.
- **Üç hesap yine elle çıkarıldı, aynı gerekçeyle üçüncü kez:**
  `389h68843j6789)` (garbled/otomatik görünen ad, `pending`, fiyat kapalı —
  factoryoutlet.gr'nin "Hello Αρχική" dersinin aynısı), `Verify Test Co`
  (adı test hesabı olduğunu söylüyor), `Acera Soft LLC` (adı
  "Acerasoft LLC"nin — her mektup imzasındaki gerçek tüzel kişiliğin — yakın
  yazımı ve maskeli adresi operatörün kendi kayıtlı e-posta alan adıyla
  eşleşiyor). Üçü de tek, ayırt edici token ile (`389h68843j6789`, `verify`,
  `acera`) `skip=` alanına verildi ve göndermeden ÖNCE ayrı bir kuru koşu
  skip listesinin **gerçekten yalnız bu üçünü** tuttuğunu doğruladı
  (`ATLANDI (skip_accounts): Acera Soft LLC` / `389h68843j6789)` /
  `Verify Test Co` — üçü, fazlası yok). Kalan 80 aday elle tek tek okundu;
  hiçbiri garbled değildi (OÜ/SRL/GbR/S.A.S gibi gerçek tüzel kişilik ekleri,
  "Particulier"/"Vinted"/"Reseller" gibi kısa ama gerçek küçük satıcı adları,
  ve zaten bu depoda tanınan gerçek müşteriler — 香港风徕贸易有限公司,
  AlexaShop S.A.S, BRITISHSTYLE, Ecokemet, Mfitel Anas, Francisco Javier
  Nicolas Macanas, C&F Multimarcas — dahil), yani şüpheyle sessizce daraltma
  yapılmadı (mango/zara dersinin hesap hâli).

| Kanal | Uygun | Skip ile atlanan | Gönderilen | Hata |
|---|---:|---:|---:|---:|
| Lead — üçüncü mektup | 3 | 0 | **3** | 0 |
| Üye — üçüncü mektup, parti 1 | 50 | 1 (Acera Soft LLC bu partide) | **50** | 0 |
| Üye — üçüncü mektup, parti 2 | 33 | 3 (üçü de bu partide) | **30** | 0 |

- **Lead kanalı (3):** Factory Outlet (EL/Yunanca — 17 Eylül'de `lead_rename`
  ile adı düzeltilmiş kayıt), Lulli (FR/Fransızca), Sportina (EN/İngilizce).
  Bu üçü 19 Eylül'ün *"lead kanalı hâlâ tükenmiş"* kaydında bekleyen tam o
  havuzdu — ikinci mektuplarının üzerinden 3 iş günü geçince kendiliğinden
  uygun hâle geldiler, elle bir şey değiştirilmedi.
- **Üye kanalı (80, iki sıralı gerçek koşu — asla paralel):** dil dağılımı
  fr=39 · en=21 · de=7 · es=4 · pt=3 · ar=2 · it=2 · ru=2 (skip'ten önceki
  83'ün dil dağılımıyla neredeyse aynı, üç hesabın çıkarılması dağılımı
  görünür şekilde değiştirmedi). Kota koşu boyunca **294 → 242** kaldı,
  60'lık işlemsel pay hiç tehlikeye girmedi.
- **Toplam bu turda: 83 gerçek mektup, 0 hata** — hepsi altı evin adını,
  doğru sırayla, ve 12 gerçek/doğrulanmış fotoğrafla taşıyor.
- Test: `tests/wave3_brands_test.php` 127 → **165 iddia** (§6b yeni: foto
  seridi, iki yön — foto verilen ev GEÇER, verilmeyen UYDURULMAZ; §7b yeni:
  workflow'un foto çözme kablolaması). Üç sabotaj (foto şeridi tamamen
  kapatıldı, `$W3_WANT` eski sıraya döndürüldü, diskte-var-mı kontrolü
  kaldırıldı) her biri **gerçekten uygulandığı doğrulanarak** kırmızıya
  çevrildi, sonra dosyalar yedekten (`cp`, `git checkout` değil) geri
  yüklendi. `sh tests/run_all.sh`: bu işten bağımsız, önceden kırık iki test
  (`dropship_plan_test` 4, `msg_read_receipt_test` 1) dışında hepsi yeşil.

**KURAL 31 — "250 email gönder": HAVUZ TAMAMEN DOYMUŞ, gerçek tavan 5**
(operatör, 21 Eyl 2026, wave3 fotoğraf/sıralama işi bittikten hemen sonra:
*"250 email gönder"* — hedef ya da kanal belirtilmedi).

- **Rakam GÖNDERMEDEN ÖNCE ölçüldü, hiçbir muhafaza gevşetilmedi.** 290 (17 Eyl)
  ve 295 (19 Eyl) tekrarlarının aynı dersi: operatörün söylediği yuvarlak sayı
  bir hedef değil, ölçülecek bir iddia. Üç kanalın da o anki gerçek havuzu ayrı
  ayrı kuru koşuyla sayıldı, hiçbiri hatırlanarak varsayılmadı:

  | Kanal | Uygun | Gerçek olan | Gönderildi |
  |---|---:|---:|---:|
  | 3. mektup (wave3) — lead | 0 | — | 0 |
  | 3. mektup (wave3) — üye | 4 | 1 (`Auto entreprise`, FR) | **1** |
  | 2. mektup (ayakkabı/iç giyim) — lead | 3 | 3 | **3** |
  | 1. mektup (soğuk havuz, `min_brands=1`) — lead | 1 | 1 (`La petite garçonne`, CA) | **1** |
  | **TOPLAM** | | | **5, hata 0** |

- **Üye kanalındaki 4 adayın 3'ü ÜÇÜNCÜ KEZ aynı hesaplar:** `Acera Soft LLC`
  (platformun kendi tüzel kişiliğinin yakın yazımı + operatörün kayıtlı e-posta
  alan adı), `389h68843j6789)` (garbled/otomatik görünen ad, `pending`, fiyat
  kapalı — factoryoutlet.gr'nin *"Hello Αρχική"* dersinin aynısı) ve
  `Verify Test Co` (adı test hesabı olduğunu söylüyor). Aynı `skip=` token'ları
  (`389h68843j6789`, `verify`, `acera`) yine kullanıldı ve gönderimden **önce**
  ayrı bir kuru koşuyla üçünü de yakaladığı doğrulandı. Geriye kalan tek gerçek
  aday `Auto entreprise` — Fransa'da "auto-entrepreneur" gerçek bir küçük
  işletme tescil biçimi (bkz. 20 Eyl'de aynı gerekçeyle geçirilen "Particulier"/
  "Vinted"/"Reseller" emsali) — elle okunup şüpheyle elenmedi.
- **Kota darboğaz DEĞİLDİ** (son gönderimden sonra 177 kalan, 60 ayrılmış):
  üç kanal da kotadan değil **havuzun kendisinden** sıfıra indi. Aynı gün daha
  önce yapılan 83'lük wave3 gönderimi (bkz. yukarıdaki madde) bu kanalları
  zaten kalan son gerçek adaya kadar boşaltmıştı.
- **Hiçbir muhafaza gevşetilmedi, rakam ZORLANMADI.** `skip_accounts`,
  `ayni firmadan baska bir kutu`, `son 3 gunde baska kampanya`,
  `onceki mektup 3 gunden yeni` ve blocklist kontrollerinin hiçbiri kapatılmadı;
  245 eksik e-postayı bir eşiği düşürerek ya da bir dedup kuralını kapatarak
  üretmek, bu depoda tam olarak yasaklanan şey. Gerçek sayı olduğu gibi
  operatöre bildirildi (295 ve 290'ın aynı deseni: gerekçesiyle birlikte
  "olmuyor" demek, bir kuralı gevşetip "oldu" demekten ucuz).
- **Kalan hacim yeni LEAD KEŞFİ gerektiriyor**, mevcut listeden değil
  (`discover-city.yml`, ölçülen verim ~1 lead/şehir — 8 Eyl kaydı). Üç kanalın
  ikinci/üçüncü mektup kuyrukları da 3 iş günlük yaş kuralında bekleyen birkaç
  aday taşıyor (2. mektupta 3 aday), ama bunlar bugün gönderilebilir değil.

**21 Eyl 2026 — Tek adrese TÜM KATALOG fiyat listesi: adres YENİ aday değil,
kayıtlı ONAYLI hesap çıktı.** Operatör: *"hhhhkgkf339@gmail.com bu emaile tüm
katalogun fiyat listesini gönderirmisin"*.
- **Adres olduğu gibi işleme alınmadı, önce SORGULANDI.** `diag-live` →
  `find_ref=hhhhkgkf339`: `accounts.json`'da tek eşleşme — **buyer/active**,
  `kyb_status=approved`, firma **Notat atria**, ülke **Iraq**,
  `trade_licence: requested (DOSYA YOK)`. Yani rastgele, kayıtsız bir soğuk
  adres değil; fiyat kapısı **zaten AÇIK** onaylı bir alıcı. Bekleyen belge
  isteği kapıyı etkilemiyor (KURAL 2: belge uyarıdır, kapıyı operatör onayı
  açar — burada onay zaten verilmiş).
- **Adres girdiye YAZILMADI:** `to=account:Notat atria` ile hesaptan çözüldü
  (TAM 1 eşleşme — `account:`/`lead:` ile paylaşılan aynı şart).
- **`send=false` önce çalıştırıldı** (KURAL 18): kapsam **TÜM KATALOG**
  (brand/cat verilmedi) — **893 kalem, 23 marka**, ek PDF 6,5 MB (877 gömülü
  fotoğraf) + Excel 108 KB, alıcının fiyat kapısı **AÇIK** (uyarı basılmadı).
  Operatörün cümlesi hem hedefi hem "gönder"i aynı anda verdiği için (KURAL
  18'in dar istisnası) ikinci bir onay beklenmedi.
- **GÖNDERİLDİ** → h***@gmail.com, imza **Marco Bellini — VESTRA**, dil **en**
  (bu şablon yalnız en/de destekliyor; pt/ru/ar'ın e-posta şablonlarında
  İngilizceye düştüğü KURAL 10'un aynı kuralı).

**21 Eyl 2026 — Ralph Lauren T-shirt SATILDI işaretlendi; aynı SKU'da AÇIK
bir teklif duruyordu.** Operatör (model numarasıyla, ad değil):
*"Custom Slim Fit Crew Neck T-Shirt / Original Ralph Lauren, model
710680785004. Made in Cambodia, 100% cotton. EEA stock with full invoice
trail satildi olarak isaretle"*.

- **Ad TEK BAŞINA kullanılmadı.** Katalogda Ralph Lauren'ın iki ilanı var ve
  ikisinin de adı birbirine yakın (*"Custom Slim Fit Crew Neck T-Shirt"* /
  *"Custom Slim Fit Polo Shirt"*) — mango/zara dersinin aynısı, ad bir
  kimlik değildir. Ayırt eden SKU: `inspect-products` (`brand=Ralph Lauren`)
  `710680785004`'ün **yalnızca** `rl-csf-tee-navy`'de olduğunu gösterdi;
  kardeş ilan `rl-csf-polo-white` farklı SKU (`710548797001`) taşıyor.
- **Aynı SKU'da AÇIK bir teklif duruyordu ve bu operatöre ayrıca bildirildi:**
  `diag-live` → `find_ref=710680785004` önce çalıştırıldı ve `offers.csv`'de
  `O9C299` (SC Daymond Proconect SRL, 104 ad. @ €14, 21 Eyl 10:45 UTC) ortaya
  çıktı. `sold_out=true` teklifi **silmiyor/reddetmiyor** — yalnız vitrini ve
  yeni satın alma yollarını kapatıyor; KURAL 4'ün tur/kabul mekanizmasına
  dokunulmadı.
  `product-fixes/rl-csf-tee-navy-sold.json` → `set-product.yml`
  (`match=rl-csf-tee-navy`, `expect:1`, `sold_out` **tırnaksız** boolean —
  `lacoste-trim-sold.json`'ın dersi: string `"false"` PHP'de doğru sayılırdı).
  Kuru koşu **1 alan** dedi (`sold_out false -> true`), sonra uygulandı,
  zaman damgalı yedek alındı (`listings.json.bak-20260921-163141`).
- **Geri okuma `KAYDEDILDI` mesajına değil, sepetin okuduğu fonksiyona
  bakıyor:** `inspect-products` → `price_list` (brand filtresiyle) satırı
  `vestra_is_sold_out()`'tan basıyor — aynı satırın altı satın alma yolunun
  hepsinin çağırdığı fonksiyon. Sonuç: `rl-csf-tee-navy` → `*** SATILDI ***`,
  kontrol grubu `rl-csf-polo-white` → `satista` (dokunulmadı, tek yön
  ölçülseydi "her şeyi satan" bir hata da yeşil kalırdı).

**22 Eyl 2026 — "En ucuz Burberry tişört" GERÇEK BİR ADAY DEĞİL, 24 YOLLU BİR
BERABERLİKTİ.** Operatör: *"katalogtaki en ucuz burberry tshirt fiyatini
75,00 eur yap"*.

- **Tekil "en ucuz" yoktu — ölçüldü, tahmin edilmedi.** `inspect-products`
  (`brand=Burberry, cat=T-Shirts`) katalogdaki **18** T-Shirts ilanının
  **hepsinin** birebir aynı fiyatta olduğunu gösterdi: `list=59,90 EUR`,
  tek kademe `20+ → €59,90`. Bir "en ucuz" seçmek için tam brand dökümü de
  çekildi (`brand=Burberry`, kategori filtresiz): Burberry'nin geri kalanı
  (Polos €60, Swim Shorts €130, Hoodies €120, Skirt €90) zaten daha pahalı,
  ama **6 tane "Women's T-Shirts" ilanı da** aynı €59,90'da duruyordu — yani
  gerçek beraberlik 18 değil **24 ilandı**.
- **Ad/kategori tek başına ayırt etmiyordu, çünkü ayırt edecek HİÇBİR şey
  yoktu.** Mango/zara dersinin bu kez ismi değil **fiyatı** ilgilendiren
  hâli: bir SKU/stil numarası verilmemişti ve 24 ilanın hepsi aynı `moq=20`,
  aynı tek kademe, aynı `mode=sale` yapısını taşıyordu — hangisinin
  kastedildiğini kod da, talimat da söylemiyordu.
- **Tahmin etmek yerine soruldu (AskUserQuestion).** 24'ün hepsinin tam bir
  beraberlik olduğu operatöre gösterildi (yalnız erkek T-Shirts / hepsi 24 /
  belirli bir SKU seçenekleriyle); operatör **"Hepsi, 24 ilan"** dedi —
  yani hem erkek hem kadın kategorisi.
- **Tek alan: `price` (tekil).** Hepsi `mode='sale'` ve tek kademesi zaten
  `list` ile aynıydı, yani `price: 75` yazmak hem `list`'i hem tek kademeyi
  75'e düzlüyor — 13 Eyl'in altı D&G polosu (→€110) ve 17 Eyl'in Burberry
  eteği 8049455'in (→€90) kullandığı aynı teknik. Ayrı ayrı yalnız `tiers`
  yazmak stale bir "was" fiyatı bırakırdı.
  `product-fixes/burberry-tshirts-75.json` (24 satır, her biri `match=<id>`,
  `expect:1`) → `set-product.yml`. Kuru koşu **24 ilanın 24'ünü de** buldu
  (`list 59.9 -> 75`, `tiers(1) -> 75`), sonra uygulandı: `KAYDEDILDI — 24
  alan guncellendi`, zaman damgalı yedek (`listings.json.bak-20260922-101653`).
- **Geri okuma İKİ ayrı yoldan, ikisi de sepetin okuduğu fonksiyondan:**
  (1) `inspect-products` (`brand=Burberry, price_list=true`) 24 ilanın
  24'ünde de `fiyat(list)=75 EUR` / `tiers: 20+ -> €75` gösterdi; aynı
  taramada Polos/Skirts/Hoodies/Swim Shorts **dokunulmamış** çıktı (60/90/
  120/130 aynen duruyor) — yalnız hedeflenen 24 değişti. (2) `price_audit`
  (`vestra_unit_price()`'ın kendisini ölçen mod): 24 Burberry ilanının
  hiçbiri ne "[A] ALICI ALEYHİNE" ne "[B] indirim rozeti yok ama sepet ucuz"
  listesinde çıktı — hepsi **tutarlı**, yani sepet gerçekten €75,00 tahsil
  ediyor. Tek "[A]" satırı bu işten bağımsız, eski demo tohumu
  `lac-pique-polo` (bu depoda tekrar tekrar kayıtlı, dokunulmadı).
- Test: bu bir veri düzeltmesi, davranış değişikliği değil — mevcut
  `scripts/set_product.php` doğrulayıcısı ve `set-product.yml`'nin
  "HEPSİ ya da HİÇBİRİ" (`expect`) muhafazası zaten kullanıldı; yeni bir
  test yazılmadı.

**11 Eyl 2026 — KATALOG GENELİNDE %80 ZAM ve GERİ ALINMASI.** Operatör:
*"yüzde 80 eklemeyi hemen geri al"* → *"tüm fiyatları dün geceki fiyatlara çek"*.
- **Ne olmuş:** KURAL 22 ile eklenen `markup_pct` aracı **`80` ile ve bölme
  süzgeci OLMADAN** koşulmuş. Ölçüm (`inspect-products` → `bak_diff`):
  **827 ilanın fiyatı** değişmiş — footwear 335, premium 346, underwear 146,
  üçünde de `markup_pct=80` damgası. Ayakkabı/markalı **×1,80**; iç çamaşırı
  benim %20'min üstüne bindiği için **×2,16**.
- **Geri alma FİYAT ALANLARIYLA SINIRLI.** Dosyanın tamamını yedekten geri
  yüklemek yanlış olurdu: aynı gün yazılan fiyat dışı alanlar (Fred Perry'nin
  asgari adedi, renk zorunluluğu, beden satırı) da silinirdi. `restore_from`
  yalnız `list` + kademe fiyatları + zam damgasını geri yazar; `moq`, `sizes`,
  `min_colors`, durum ve **yedekte olmayan ilanlar** korunur.
  **Kademeler SIRAYLA eşleştirilir, `min` değerine göre değil** — Fred Perry'de
  MOQ 20→50 olunca ilk kademenin `min`'i de değişmişti; `min`'e göre eşleştiren
  bir geri yükleme o kademenin fiyatını hiç geri getiremezdi.
- **Kum havuzu gerçek bir hata yakaladı:** geri yükleme bloğu sayaçlar
  kurulmadan önce çalışıyordu, yani `$changes++` tanımsız değişkene yazıyor ve
  blok bitince sıfırlanıyordu — *"GERI YUKLENEN: 827 ilan"* yazıp **hiçbir şeyi
  kaydetmeyen** bir koşu. Canlıda bu, "geri aldım" raporu ile hâlâ zamlı duran
  bir katalog demekti. Test: `set_prices_markup_test.php §5c` (17 iddia).
- **Sonuç (run `34593409339`):** 827 alan geri yazıldı, yedek alındı; geri
  okuma (`bak_diff`) **"FIYATI DEGISEN ILAN: 0"**, damga kalmadı.
- **Bu iş iki sessiz araç hatası daha açığa çıkardı** (`bak_diff` iki koşu
  boyunca hiç çalışmadı ve her ikisinde de **normal listeyi basıp yeşil bitti**,
  yani "değişen bir şey yok" gibi okundu):
  1. `workflow_dispatch` girdiyi **11. sırada** teslim etmedi — sonda, sorulmayan
     bir soruya cevap verdi. Girdi öne alındı.
  2. `appleboy/ssh-action` **yalnız `envs:` listesinde adı yazılı** değişkenleri
     sunucuya geçiriyor; yeni değişken oraya eklenmemişti, uzakta `getenv()` boş
     döndü. *Yeni bir sonda girdisi eklerken bu satır kontrol listesine girsin.*
- **AÇIK DERS — araç, kapsamı kendisi sınırlamıyor.** `markup_pct` 24 saatlik
  "aynı yüzde" damgası taşıyor ama **bölme/marka daraltması zorunlu değil**:
  `brand=*` + bölme boş = tüm katalog, tek dispatch. 827 ilan tam bu yoldan
  zamlandı. Gönderim tarafındaki 50'lik kırpma bunun aynısı için konmuştu;
  fiyat tarafında karşılığı **yok**.
- **Depoda aynı anda başka bir Claude oturumu çalışıyor**
  (`claude/wizardly-planck-7ylnmk`, ayrı session). `listings.json`
  oku-değiştir-yaz olduğu için bu, `add-and-send`'in paralel koşu uyarısının
  fiyat hâli: iki oturum aynı dosyaya yazarsa biri diğerini ezer.

**11 Eyl 2026 — DÜZELTME SONRASI SON DURUM: zam yalnız iç çamaşırında.**
Operatör: *"underwear i 20 zamla, digerlerini zaten cekmis olmamiz lazim"*.
- `set-prices` → `section=underwear`, `markup_pct=20` (run `34602265462`):
  **146 ilan, 292 alan**, atlanan 0. Geri yükleme damgaları temizlediği için
  24 saatlik "aynı yüzde" koruması engel olmadı — damgayı silmek tam bu yüzden
  geri almanın parçası.
- Geri okuma (`bak_diff` → dün 20:47 yedeği, run `34602407653`):
  **fiyatı değişen toplam 146**, hepsi `underwear`, oran ×1,20; damga
  `underwear %20 → 146`. **footwear ve premium'da sıfır fark** — yani
  ayakkabı ve markalı giysi dün geceki fiyatlarında.
- Net sonuç: iç çamaşırı **alış × 1,8** (maliyet ×1,5 katalog fiyatı üzerine
  %20), diğer iki bölme dokunulmamış. *Aynı hedefe iki günde iki kez varıldı;
  farkı yaratan, ikinci seferde bölme süzgecinin verilmiş olması.*

**KURAL 23 — Mesaj silinebilir; kimlik DİZİN NUMARASI değil, kaydın kendi
içeriğidir** (operatör, 11 Eyl 2026: *"bu mesajlari sil ve silme özelligide koy
mesajlara"*).
- İki ayrı kayıt, iki ayrı silici: engellenen deneme kaydı
  (`vestra_msg_blocked_delete`, `data/blocked_messages.json`) ve konuşmadaki tek
  mesaj (`vestra_msg_delete`, `data/messages.json`). Panelde `Admin ▸ Messages`:
  engellenen satırlarda ve her mesajın yanında 🗑.
- **Anahtar içerikten türüyor** (`sha1(at|from|text)`), dizin numarasından
  değil. Panel satırı çizdiği an ile silme isteğinin sunucuya vardığı an
  arasında log'a yeni kayıt düşebilir — bu depoda aynı anda birden fazla oturum
  yazıyor — ve kayan bir dizin **yanlış satırı** silerdi. Silinen şey bir
  moderasyon izi olduğu için bunu kimse fark etmezdi. İçerik anahtarı ya doğru
  satırı bulur ya **hiç** bulamaz. Test bunu araya kayıt sokarak ölçüyor
  (`tests/msg_delete_test.php §2`; anahtar içerikten türemeyince 6 kırmızı).
- **Önce yedek, sonra silme, sonra GERİ OKUMA.** Kopya
  `data/message_backups/` altına zaman damgasıyla yazılıyor (KURAL 5g'nin teklif
  silme şartı); yazma sessizce düşerse panel "silindi" demesin diye dosya geri
  okunup anahtarın gittiği doğrulanıyor.
- **`last_at` yeniden hesaplanıyor.** Son mesaj silindiğinde güncellenmezse
  konuşma, artık var olmayan bir mesajın tarihiyle sıralanır. Konuşma
  boşalırsa iplik de kapanır — tıklanabilir ama içeriksiz bir kayıt kalmasın.
- **Silmek karşı tarafın gördüğünü geri almaz:** mesaj çoktan okunmuş ve
  bildirim e-postası gitmiş olabilir. Onay metni bunu açıkça yazıyor; "sildim"
  sanıp konuşmanın devamını ona göre kurmak olmayan bir şey varsaymak olurdu.
- **CSRF alanı şart.** Panelde her POST global olarak doğrulanıyor
  (`_csrf`); alanı unutan bir form `csrf_fail` ile döner, yani **düğme görünür
  ama hiç çalışmaz**. İlk yazımda unutuldu, test kabloyu de denetliyor.

**KURAL 24 — Mesajda OKUNDU onayı; ve kendi eylemi kendi rozetini yakmaz**
(operatör, 11 Eyl 2026: *"mesajlarda mesajin okunup okunmadigi müsteriye
görünsün ayrica, kendi mesaji okursa bildirim kalksin birsey yanmasin"*).
**İki ayrı iş, tek cümlede.**

- **Rozet kusuru GERÇEKTİ ve sebebi sistem kartlarıydı.** `vestra_msg_unread()`
  *"benden olmayan her şey okunmamıştır"* diyor; sistem kartı
  (`vestra_msg_post_system`) `from='system'` ile yazılıyor ve **kimin yol
  açtığını hiç kaydetmiyordu**. Sonuç: alıcı **kendi teklifini** verince
  **kendi rozeti** yanıyordu. Kum havuzunda ölçüldü (gerçek giriş + gerçek
  sayfa çizimi, kaynak okumak değil): kendi teklifinden sonra *alıcı YANIYOR ·
  satıcı YANIYOR*; düzeltmeden sonra *alıcı SÖNÜK · satıcı YANIYOR*.
  Kendi **metin** mesajı zaten yakmıyordu (`from === $uid`) — kaçan yalnız
  kartlardı.
- **Çözüm ALAN EKLEME:** karta `by` (aktör uid'i) düşüyor, `vestra_msg_unread()`
  onu atlıyor. **`by` taşımayan kayıt bugünkü davranışı koruyor** — yani eksik
  bir aktör **fazla haber verir, mesajı GİZLEMEZ**. Yanlış yön bu olmalı; 15.
  çağrı yerini eklerken unutan kişi sessiz bir kayıp değil, fazladan bir rozet
  üretir. Test çağrı yerlerini **elle listelemiyor**, kaynağı ayrıştırıp
  sayıyor (14/14).
- **Aktör her yerde aynı kişi değil ve tahmin edilmedi:** sipariş/teklif/kabul
  kartlarında **alıcı**, gönderildi/ödendi/teslim kartlarında **satıcı**,
  talebe teklifte **satıcı**. İki yerde bilerek **boş**: escrow süpürücüsü
  insan eylemi değil; talebin **çözümünü** operatör yazıyor (`opened` alıcının,
  `resolved` kimsenin) — orada iki rozetin de yanması doğru.
- **Okundu onayı muhatabın gördüğü SAYIM'dan** (`vestra_msg_read_upto`):
  `mark_read` mesaj **sayısını** yazıyor, yani i. baloncuk ancak `read_upto > i`
  ise okunmuş. Yalnız **kendi** baloncuklarımızda çiziliyor; karşı tarafın
  mesajının yanına "okundu" yazmak kendi kendine bilgidir.
- **VESTRA Support ipliğinde HİÇ çizilmiyor.** Operatör panelinin mesaj sekmesi
  bütün konuşmaları **tek sayfada** listeliyor, yani "operatör tam bu ipliği
  okudu" diyebileceğimiz bir an yok ve işaret **hiçbir zaman ilerleyemezdi**.
  Asla ilerlemeyen bir gösterge bozuk bir göstergedir — kendine dönen "geri"
  düğmesiyle aynı sınıf. Admin panelinden damgalamak daha kötü olurdu: o sayfa
  alıcı↔satıcı ipliklerini de gösteriyor, damgalamak müşteriye **satıcının**
  okuduğunu söylerdi.
- **Yoklama `last_at`'e bakmak YETMİYORDU.** Karşı taraf okuduğunda konuşmaya
  hiçbir şey eklenmiyor, yani ✓ hiçbir zaman ✓✓ olmazdı — tam da bekleyen kişi
  için bozuk görünürdü. Uç artık `read`'i de dönüyor ve durumu **tek gövde**
  kuruyor (`vestra_msg_poll_state`); iki panel kendi hesaplasaydı biri
  ötekinden ayrışırdı (dört mektup gövdesinin dersi).
- **Silme `read[]`'i yeniden hesaplamıyor**, yani işaret dizinin dışına
  taşabiliyor ve kırpılmasaydı gösterge **her mesajı "okundu"** diye basardı.
  Kırpma doğru cevabı veriyor: karşı taraf silineni de görmüştü.
  Eski **tarih tabanlı** işaret sayı değil → **okunmamış** sayılıyor; "okundu"
  diye yanlış bir şey yazmaktansa hiç yazmamak doğru.
- Fark renkle **değil** işaret sayısıyla veriliyor (✓ / ✓✓): yalnız renge
  dayanan bir gösterge renk körlüğünde kayboluyor. `Read` / `Sent` **8 sözlüğe
  birden** (KURAL 10); Almanca *Gelesen/Gesendet*, Japonca *既読/送信済み*.
- **Yan kazanç: `msg_delete_test.php` ilk kez KOŞTU.** Depo yolu artık sabit
  (`VESTRA_MESSAGES` / `VESTRA_BLOCKED_MESSAGES`, `defined()` korumalı —
  KURAL 2'nin `VESTRA_ACCOUNTS` kararının aynısı), o yüzden test gerçek
  `vestra/data`'ya dokunmuyor. Eskiden dokunuyordu ve o dosyalar var olduğu
  için test kendini **reddediyordu**: KURAL 23'ün silme özelliği bu depoda hiç
  sınanmamıştı (şimdi 30 iddia, hepsi yeşil). *Koşmayan bir test, hiç
  düşemeyen bir iddianın dosya hâlidir.*
- **Davranış bilerek değiştiği için `msg_panel_test` de düzeltildi:** iddia
  `>09:12<` arıyordu, yani saatin **komşu karakterini**; onay işareti saatin
  yanına girince kırmızı döndü. Olguya çevrildi (okunur saat ekranda) ve
  saat kaldırılarak hâlâ düşebildiği doğrulandı.
- **Canlı çizim** (kum havuzu kopyası, onaylı alıcı **ve** satıcı oturumu):
  alıcının 09:10 mesajı **✓✓ Read**, 09:30 mesajı **✓ Sent**, satıcının
  mesajında işaret **yok**; Almanca *Gesendet/Gelesen*, Arapça RTL doğru;
  Support ipliğinde **0** işaret, kontrol ipliğinde **2**; yoklama
  `{"last":…,"read":4}`; PHP uyarısı **0**.
- Test: `tests/msg_read_receipt_test.php` (67 iddia, iki yön). Düşebildiği
  doğrulandı: `by` atlaması kaldırılınca **3 kırmızı**, bir çağrı yerinden
  aktör düşünce **1**, kırpma kalkınca **2**, yoklama eski hâline dönünce
  **2**, Support muhafazası kalkınca **1**, çizim düz metne çevrilince **3**,
  sembol her baloncuğa gömülünce **1**. *Her sabotajın GERÇEKTEN uygulandığı
  ayrıca yazdırıldı — bu oturumda bir sabotaj sessizce hiç uygulanmamış ve
  testi sağlam göstermişti.*

**KURAL 24 (devamı) — işaret ÇİZİLİYOR, ve "çalışıyor mu" TARAYICIDA ölçüldü**
(operatör, 11 Eyl 2026: *"calisip calismadigindan emin ol ve estetik yap"*).
- **Düz `✓✓` iki ayrı harftir.** Yazı tipine göre araları açılıyor (harf-boşluğu
  hilesiyle sıkıştırmak gerekiyordu), bazı tiplerde kalın bir emoji olarak
  çıkıyor ve kalınlığı `font-weight`'e bağlı. Artık **SVG çizim**: tek çengel
  (gönderildi, soluk) / çift çengel (okundu, vurgulu). Sembol `<defs>` içinde
  **TEK kez** tanımlı, her baloncuk `<use>` ile işaret ediyor — 30 mesajlık bir
  konuşmada aynı yolu 30 kez gömmemek için (311 bayt + baloncuk başına ~95).
- **Sembol kendi `stroke`/`fill`'ini YAZMIYOR:** ikisi de SVG'de kalıtımlı, yani
  renk CSS'te kalıyor ve `.seen` ile tema değişimi kendiliğinden işliyor.
  Yazsaydı `.msgtick.seen{color:var(--acc)}` hiçbir şey yapmazdı. Testte iddia var.
- **İşaret SABİT genişlikte (14px):** saat sağa yaslı, yani tek çengel çift
  olunca satır gözle görülür şekilde sıçrardı.
- **ÇALIŞTIĞI GERÇEK TARAYICIDA, UÇTAN UCA ölçüldü** (kum havuzu kopyası,
  gerçek giriş — kaynak okumak ölçüm değil):
  - alıcı sayfayı açık tutuyor, **satıcı okuyor** (yalnız sunucudaki kayıt
    değişiyor, sayfaya dokunulmuyor) → işaret **15–30 sn içinde KENDİLİĞİNDEN**
    ✓ → ✓✓ dönüyor. Yoklama `read`'i de taşıdığı için çalışıyor; `last_at`
    okuma anında değişmediği için eski hâli **hiç dönmezdi**.
  - **KONTROL GRUBU:** kimse okumadan 45 sn (3 yoklama) → işaret **değişmiyor**.
    Tek yön ölçülseydi "her zaman ✓✓ basan" bir kusur da yeşil görünürdü.
  - masaüstü / mobil / Almanca / Arapça RTL çizdirildi; yatay taşma yok, konsol
    hatası yalnız bu ortamdan erişilemeyen Google Fonts.
- **Canlı sonda: `diag-messages.yml` → `receipt_probe=true`.** Önce yeni
  fonksiyonların sunucuya **indiğini** soruyor — dosyanın parse edilmesi yetmez,
  **eski bir kopya da parse edilir** (KURAL 21b'nin "deploy inmemiş" dersi) —
  sonra gerçek konuşmalarda işaretin kaç baloncukta çizildiğini ve rozetin
  **KENDİ eyleminden yanan** bir konuşma bırakıp bırakmadığını sayıyor.
  **Mesaj metni, ad, adres, thread id'sinin tamamı BASILMIYOR** — yalnız sayım.
  Sonda **önce kum havuzunda** koşturuldu (bu depoda kontrolün kendisi altı kez
  yanlış yere baktı) ve düzeltme geri alınarak **gerçekten kırmızı döndüğü**
  doğrulandı: `0` → `*** 1 — TH1 ***`, yanan rozet 1 → 2.
- **CANLI SONUÇ (11 Eyl 2026, run `34626325137`, deploy `9e2c9e47`):**

  | | |
  |---|---:|
  | yeni fonksiyonlar | **3/3 VAR** (deploy indi) |
  | `post_system` parametresi | **5** (aktör yolu canlıda) |
  | konuşma | 30 |
  | onay işareti çizilen konuşma | **18** · toplam **35 baloncuk** (okundu 16 / gönderildi 19) |
  | Support ipliği (işaret bilerek yok) | 5 |
  | yanan rozet | 20 |
  | **KENDİ eyleminden yanan** | **0** |
  | yoklama anahtarları | `last, read` |

- **Dürüst sınır:** canlıdaki **13 sistem kartının 13'ü aktörsüz** — hepsi kural
  konmadan önce yazılmış. Onlar eski davranışı koruyor (iki taraf da yanar) ve
  bu **bilerek**: eksik bir aktör fazla haber verir, mesajı gizlemez. Aktör alanı
  ancak **bundan sonra doğan** kartlarda görünecek; sonda o sayının yükselişini
  gösteriyor.
- **İki ölçüm tuzağı, ikisi de kendi testimde:**
  1. **`every()` BOŞ dizide de TRUE.** İlk uçtan uca kontrolüm sayfa
     yenilenirken diziyi boş yakaladı ve iddia **boşa geçti** — "dönüştü" dedi,
     hiçbir şey ölçmemişti. Uzunluk şartı eklendi (`t.length === 3 && …`).
  2. **`class="msgtick` öneki `msgtickdefs`'i de yakalıyordu** ve 2 yerine 3
     saydı — mango/zara dersinin **testin kendi içindeki** hâli. Sayım artık
     kapanış tırnağına kadar.

**"Verified seller" rozeti — beyaz fotoğrafın üstünde okunur hâle getirildi**
(operatör, 13 Eyl 2026: *"daha okunakli, beyaz ustude durdugundan rengi degissin
ve daha estetik olsun"*).
- Rozet ürün **fotoğrafının** üstünde duruyor ve bu katalogun fotoğraflarının
  neredeyse tamamı **beyaz fonlu paket çekimi** (aynı ölçüm katalog karolarını
  açık zemine taşırken yapılmıştı: 35 fotoğrafın 33'ü). Eski hâli açık yeşil
  zemin + `#1f7a4c` yazı: **4,46:1** — AA eşiği 4,5'in **altında**, üstelik
  10px kalın yazıda.
- **Asıl kusur tik işaretiydi:** işaretlemede `stroke="#fff"` yazıyordu, yani
  açık yeşil zeminde **beyaz tik** → 1,06:1, pratikte görünmez. Koyu karoda
  doğruydu; karo açık zemine taşınınca sessizce kayboldu. Artık `currentColor`
  (5 yerde: shop, product ×2, showroom ×2).
- **Zemin artık fotoğrafa bırakılmıyor:** buzlu beyaz hap (%94 opak + blur) +
  koyu orman yeşili `#0f5132` yazı, ince yeşil kenarlık, yumuşak gölge.
  Ölçülen kontrast **9,36:1**. Rengi fotoğrafa bırakan her çözüm bazı
  fotoğraflarda düşüyordu; zemini sabitlemek tek güvenli yol.
- **Tek kural:** `.sthumb.sphoto .svbadge` override'ı **kaldırıldı** — aynı
  rozetin iki ayrı rengi vardı (`#7ad6a0` ve `#1f7a4c`) ve iki tanım er geç
  ayrışır (marka sayfası CSS'inin aynı dersi).
- Test: `tests/verified_badge_test.php` (15 iddia). **Renk seçimini değil
  okunabilirliği** ölçüyor: hangi yeşil seçilirse seçilsin beyaz üzerinde
  4,5:1'i geçmek zorunda. Ölçütün ayırt ettiği, eski rengin aynı hesapta
  4,46 çıkmasıyla doğrulanıyor — yani düşebilen bir iddia.

**KURAL 22 — Toplu ZAM ayrı bir araçtır ve TEKRARLANAMAZ** (operatör, 10 Eyl
2026: *"underwear ürünlerine yüzde 20 zam yap bütün ürünlere"*).
- `set-prices.yml` yalnızca **indirim** biliyordu (`discount_pct`). Zam onun
  aynası değil: indirim tabanı olarak `list`'i okuyup **değiştirmiyordu**, yani
  iş yanlışlıkla iki kez koşsa sonuç aynı kalıyordu. Zamda taban bugünkü
  fiyattır ve zam onu değiştirir — ikinci koşu %20 değil **%44** eder. Bu yüzden
  her ilana `markup_at` + `markup_pct` damgası düşüyor: **aynı yüzde 24 saat
  içinde ikinci kez uygulanmaz** (`force=true` ile aşılır, farklı bir yüzde
  takılmaz). Çıktı da her koşuda bunu yazıyor.
- **Bölme süzgeci eklendi** (`section`: premium/footwear/underwear). "Underwear
  ürünleri" marka da kategori de değil: `Underwear & Socks` grubunun altında 8
  kategori yaprağı var ve marka ile süzmek yanlış küme verirdi. Süzgeç
  `vestra_product_section()`'ı çağırıyor — ham `$p['section']` okunsaydı alanı
  hiç girilmemiş ilanlar hiçbir bölmeye düşmezdi. **Yazım hatası olan bölme
  reddediliyor**; yoksa `underwaer` yazan bir koşu "0 ürün" deyip **yeşil**
  biterdi ve yazım hatası ile "bu bölmede ürün yok" aynı görüntü olurdu.
- **`list` de aynı oranda yükseliyor.** İndirimli bir üründe üstü çizili rakam
  sabit kalsaydı indirim yüzdesi kendiliğinden erir, hatta `list` < fiyat olup
  rozet eksiye düşerdi (`vestra_discount`). `mode`'a **dokunulmuyor**: zam
  indirim değil, `sale` `sale` kalır, `offer` `offer`.
- **`eur_margin_pct` damgası varsa güncelleniyor — ama canlıda YOKTU.** Kod
  bileşik oranı yazıyor (1.5 × 1.2 = 1.8 → %80) ve testte ölçülü; canlı koşuda
  **tek bir "kar orani" satırı basılmadı**, yani o damga `listings.json`'a hiç
  inmiyor — ithalat tarafındaki `*_priced.json`'da kalıyor. Sonuç aynı yerde
  bitiyor: **bir sonraki NBB/Q-EN ithalatı `price|50` ile koşarsa bu zammı
  sessizce geri alır.** Yeni oran **%80**; ithalat o oranla koşulmalı.
  *Bir alanın kodda okunuyor olması canlıda dolu olduğu anlamına gelmiyor —
  bu depoda "toplanan ama okunmayan alan"ın ters yüzü.*
- **Numune fiyatına dokunulmuyor** (tek parça ayrı bir karar) ama sessiz
  değil: kaç üründe olduğu çıktıda yazıyor.
- **Uygulayıcı PHP workflow'un içinden `scripts/set_prices.php`'ye taşındı**
  (`set-product.yml` ile aynı desen). Sebep tek kelimeyle: gömülü heredoc
  **test edilemiyordu** ve bu kod canlı kataloğun fiyatlarını yazıyor.
  Test: `tests/set_prices_markup_test.php` (50 iddia; kum havuzunda betiği
  gerçekten koşturuyor). Üç sabotajla düşebilirliği doğrulandı: damga kapalı
  → 4 kırmızı, `list` yükselmiyor → 8, bölme süzgeci yok → 6.
- **İKİNCİ ZAM: %80, TÜM KATALOG** (operatör, 11 Eyl 2026: *"fiyatlari yüzde 80
  ekle üstüne"*; kapsam **soruldu** — cümlede marka/bölme yoktu ve 2 ilanla 827
  ilan arasındaki fark geri alınamaz bir farktı — operatör **"tüm katalog"**
  seçti). Run `34586885284`: **827 ilan, 1.662 alan**, numune fiyatlı 2 ürüne
  dokunulmadı, zaman damgalı yedek. Geri okuma **underwear DIŞINDAN** yapıldı
  (Fred Perry): polo €39 → **€70,20** (kademeler 63,90 / 57,60), sweatshirt
  €39,90 → **€71,82** — hepsi tam ×1,8. *Kâr oranı bilinen bir katalogda fiyat
  basmak maliyeti ele verir; doğrulama marjı kayıtlı OLMAYAN bir markadan
  yapılır ve `show_prices` kapalı bırakılır.*
- **11 Eyl 2026 11:21 ÖLÇÜMÜ: %80 CANLI KAYITTA YOK — bu notun ×3,24 talimatı
  KULLANILMAZ.** `inspect-products` → `brand=Fred Perry`: polo `list=39`,
  kademeler **39 / 35,50 / 32**; sweatshirt `list=39,90`, kademe **39,90**. Yani
  zam ÖNCESİ rakamlar. Aynı koşunun yedek karşılaştırması da *"yedek
  listings.json.bak-20260910-204756'ya göre FIYATI DEGISEN ILAN: 0"* ve
  *"zam damgası yok"* diyor — 827 ilanın hiçbirinde.
  **Kaydın bir ara zamlı olduğu KESİN:** 10:37 ve 11:00'de üretilen iki
  `listing_colours` mektubu fiyatı `vestra_price_ladder()` ile CANLI kayıttan
  okuyor ve **70,20 / 63,90 / 57,60 · 71,82** bastı (kopyalar operatörün
  kutusunda duruyor). Yani kayıt 11:00 ile 11:21 arasında eski fiyatlara döndü.
  **Neyin döndürdüğü bilinmiyor ve UYDURULMUYOR** — operatör de aynı dakikalarda
  *"eski fiyatlar ile göndereceğiz"* dedi, yani bilinçli bir geri alma olabilir.
  Bir sonraki ithalat koşusundan ÖNCE oranı **ölç** (`inspect-products`,
  `brand=<marka>`), bu satırdaki çarpanı varsayma: `price|224` ile koşmak,
  zam kayıtta yokken fiyatı üçe katlardı.
- **Bileşik etki, operatöre söylenerek seçildi:** underwear dün %20 almıştı,
  yani orada taban artık **2,16×**. İthalat tarafı için sonuç: maliyet ×1,5
  ×1,2 ×1,8 = **×3,24**, yani bir sonraki Kuloğlu koşusu `price|224` ile
  koşulmalı. `price|50` (hatta dünkü nota göre `price|80`) ile koşmak **iki
  zammı birden sessizce geri alır**. *Damga `listings.json`'a inmediği için bu
  sayıyı tutan tek yer burası.*
- **UYGULANDI, 10 Eyl 2026** (run `34528478617`): bölme `underwear`, **146
  ilan**, 292 alan (her ilanın `list`'i + bir kademesi), atlanan 0, zaman
  damgalı yedek alındı. 146 = 42 NBB + Q-EN/Visatin partisi; `set-prices`
  ilan **durumuna bakmıyor**, yani `pending` duranlar da zamlı doğuyor —
  istenen bu, yoksa yayına eski fiyatla çıkarlardı.
- **Workflow'un `checkout` adımı YOKTU** ve ilk koşu "scripts/set_prices.php
  yok" diye düştü: bu iş bugüne kadar repodan hiçbir dosya okumuyordu. Bir
  uygulayıcıyı workflow'un içinden dosyaya taşırken sorulacak ilk soru bu.
- `price_input_test.php`'nin kablo denetimi de yeni dosyaya bakıyor —
  davranış değişmedi, **yeri** değişti; testi güncellemek şart (bu depoda
  "davranış bilerek değiştiyse testi de düzelt" kaydı var).

**Katalogta GERİ dönüş + fotoğraf zemini** (operatör, 10 Eyl 2026:
*"kataloglarda geriye dogru dönüs koy ve yapabliyorsan katalog fotolarini daha
estetik ya"*).
- `vestra_back_link()` (`inc/products.php`) — ürün sayfasının kırıntı satırında
  `← Back to catalog`. Hedef **Referer**'dan geliyor ama Referer başkasının
  yazdığı bir başlık: yabancı alan adı, `javascript:`/`data:` şeması ve site
  içinde bile **liste olmayan** yollar `/shop`'a düşüyor.
  Kabul edilen önekler tam eşitlik ya da `/` ile devam: düz `str_starts_with`
  ile `/shop` öneki **`/shopping-cart`**'ı da yakalıyordu — mango/zara dersinin
  aynısı, testte iki yönü de var (`tests/back_link_test.php`).
  Sorgu parametreleri korunuyor: `/shop?section=footwear`'dan girip "geri"
  deyince kataloğun başına değil **bulunduğu yere** dönüyor.
- **Etiket iki sözlük anahtarından** (`Back`, `Back to catalog`) — ikisi de 8
  dilde zaten vardı, yeni anahtar eklenmedi (KURAL 10). Yerelde çizdirildi:
  `Zurück` / `Retour` / `Indietro` / `Atrás`.

**"Geri" tam olarak BİR SAYFA geri olmalı** (operatör, 11 Eyl 2026: *"ürün
sayfalarinda olan back to catalog tam kataloga degil bir geri sayfaya nereden
geldiyse oraya götürsün"*).
- **Sebep ölçüldü, tahmin edilmedi:** izin listesi yalnız 8 giriş taşıyordu ve
  `grep 'product?id='` ile sayılınca ürüne bağlantı veren **altı sayfa daha**
  çıktı — **ana sayfa** (marka duvarı + kategori şeridi), `showroom`, `group`,
  `dropship`, `requests`, alıcı/satıcı paneli ve **ürün sayfasının kendisi**.
  Hepsi kataloğun başına düşüyordu; operatörün şikâyeti tam bu küme.
- **Liste NEDEN hâlâ izin listesi.** İstenen "her yer" ama bu sitede **GET ile
  iş yapan uçlar** var — ölçüldü: `login?signout` **oturumu kapatıyor**,
  `offer-accept` / `verify` / `lead-unsubscribe` jeton harcıyor. "Aynı alan
  adındaki her yolu kabul et" deseydik "geri" düğmesi bunlardan birini yeniden
  çağırabilirdi. İzin listesi bu sınıfı **yapısı gereği** dışarıda tutuyor:
  jeton ucu gezinme sayfası değil, yani listeye hiç girmiyor. Falsifikasyon
  bunu doğruladı — `$ok = true` yapılınca **13 kırmızı**, biri `/login?signout=1`.
  *Panellerin GET parametreleri okundu* (`view`, `added`, `connect`, `dl_claim`):
  hepsi salt-okunur, o yüzden `/buyer` ve `/seller` sorgusuyla kabul ediliyor.
- **Kendine dönen "geri" bozuk düğmedir:** aynı ürün (ör. `?err=sizes` ile
  kendine dönmüş bir gönderim) ve birebir aynı adres `/shop`'a düşüyor. BAŞKA
  bir ürün sayfası kabul — A'dan B'ye geçtiyseniz "geri" A'dır.
- **Asıl düzeltme JS tarafında: `history.back()`.** Sunucudan gelen adres doğru
  yere götürüyor ama adresi **yeniden çekiyor** — 200 ürün aşağıda tıklayan
  alıcı listenin **başına** dönüyor. Kaydırma konumunu yalnız tarayıcının kendi
  geçmişi koruyor. `href` **kalıyor**: JS'siz tarayıcı, orta tık ve "yeni
  sekmede aç" bozulmasın diye — ve doğrudan gelende (referrer yok) zaten tek
  doğru yer o. Devralma yalnız **düz sol tıkta** (`button!==0`, meta/ctrl/shift/
  alt hariç) ve yalnız referrer **aynı kökende + `history.length>1`** iken.
- **Ters bolu normalizasyonu eklendi** (`\` → `/`): `/\evil.example` bazı
  tarayıcılarda `//evil.example` diye çözülür, yani site dışına çıkan bir
  "geri". İzin listesi bunu zaten kapatıyor; satır, listeyi bir gün genişleten
  birinin kapıyı sessizce açmaması için duruyor.
- **Yerelde ÇİZDİRİLDİ** (kaynak okumak ölçüm değil): 11 referrer durumu tek tek
  çekildi — ana sayfa `/`, `/shop?section=underwear`, `/showroom?uid=abc`,
  `/b2b/bras`, `/buyer?view=…`, `/requests`, başka ürün → hepsi **geldiği yere**;
  aynı ürün, `/login?signout=1`, yabancı site, referrer yok → **`/shop`**.
  PHP uyarısı **0**, betik sayfada basılıyor.
- Test: `tests/back_link_test.php` (**56 iddia**). Dört sabotajla düşebildiği
  doğrulandı: eski dar liste → **9 kırmızı**, kendine-dönüş muhafazası kalkınca
  **3**, "her yolu kabul et" → **13**, `history.back()` bloğu silinince **6**.
  **Bir iddiam hiç düşemiyordu ve falsifikasyon yakaladı:** düz `history.back()`
  aranıyordu, o dizge bloğun **açıklama satırında** da geçiyor — blok tamamen
  silinince bile yeşil kalıyordu; iddia artık kodun kendi satırına bağlı.
  *Sabotajın GERÇEKTEN uygulandığını da doğrula:* ilk `perl -0pi` denemem hiçbir
  şey değiştirmemişti ve "0 kırmızı" diyerek testi sağlam göstermişti.
- **Davranış bilerek değiştiği için test de düzeltildi** (bu deponun kendi
  kuralı): `/buyer → /shop`, `/product → /shop`, `/ → /shop` iddiaları artık
  eski ve **istenmeyen** davranışı koruyordu.
- **Fotoğraf zemini ölçümle seçildi:** katalogdaki 35 fotoğrafın **33'ü** beyaz
  fonlu paket çekimi (kenar pikseli ortalaması > 225). `object-fit: cover` +
  koyu zemin bunları kırpıyor ve ürünü karartıyordu; artık fotoğraflı karo
  açık stüdyo zemini + `contain` (`.sthumb.sphoto`), yani ürün **kırpılmıyor**.
  Fotoğrafsız karo eski koyu degradeyi koruyor — orada kırpılacak bir şey yok.

**Lookbook PDF — yalnız MARKA + AD + FOTO** (operatör, 12 Eyl 2026: *"bunlari
katalogtan bul ve fotolari ile birlikte pdf yap sadece isim marka ve foto"*).
- `vestra/lookbook.php` → `/lookbook.php?ids=a,b,c`. Web tarafında **yalnız
  admin**; CLI muaf (ölçüm ancak oradan yapılabiliyor).
- **`wholesale-list.php`'nin bir KİPİ DEĞİL, ayrı dosya.** O belge bir *fiyat
  listesi*: her satırda artikel no, beden serisi, MOQ, toptan fiyat, RRP, stok —
  ve 46×74pt'lik bir küçük resim. Buradaki belgenin işi tam tersi: fotoğraf asıl
  içerik. Aynı dosyaya "minimal" bayrağı koymak her satırı iki ayrı düzende
  çizmek ve fiyat sütunlarını bir koşulla susturmak demekti. **Paylaşılan şey
  paylaşılıyor** (PDF yazıcı, `vestra_pdf_thumb`, ilan kaydı); kopyalanan tek şey
  düzen.
- **Fiyat/MOQ/beden/stok/satıcı/SKU bilerek YOK:** operatörün cümlesi üç şey
  sayıyor. Fiyatsız bir sayfa her alıcıya gösterilebilir, fiyatlı bir sayfa
  gösterilemez (KURAL 2b/19).
- **Eksik id sessizce düşmez:** dört ürün isteyip üç ürünlük bir PDF almak,
  belgenin kendisinde görünmeyen bir kusur. CLI'da yazılıyor, web tarafında
  hiçbir şey üretilmiyor.
- **Ölçüm `/DCTDecode` SAYISI.** "Dosya üretildi, boyut makul, imza doğru" bu
  depoda bir kez fotoğrafsız bir PDF'i yıllarca geçirdi (`wholesale-list.php`'nin
  kendi yorumunda yazılı: mutlak yol verilince `vestra_pdf_thumb` **sessizce**
  boş dönüyordu). Sonda: `seller-products.yml` → `admin_mode=lookbook`.
- **CANLI SONUÇ (12 Eyl 2026):** 4 ürün, **eksik id 0**, 243.795 bayt, **gömülü
  fotoğraf 4**. Çözülenler: `mb-cmaa018f20jer0011016` (Wings T-Shirt),
  `mb-vs004` (Box Logo T-Shirt, Black), `dsq-101213` (Graphic T-Shirt
  (Oversized)), `blc-662853tjw90` (Balenciaga Allover Logo Denim Set).
- **ADLA arama yetmezdi, KOD kurtardı:** katalogda **iki** "Wings T-Shirt" var —
  `CMAA018E20JER0011030` ve `CMAA018F20JER0011016`. Operatör kodu yazdığı için
  doğrusu seçilebildi. *Aynı adı taşıyan iki ilan varsa ad bir kimlik değildir.*

**ÇÖZÜLDÜ — eksik olan `st=` paylaşım jetonuydu** (12 Eyl 2026, operatörün
ÜÇÜNCÜ linki). Aşağıdaki "No Access" teşhisi **linkin kendisi hakkındaydı ve
öyle kalıyor**, ama sebebi paylaşım ayarı değil: operatörün ilk iki linkinde
`st=` parametresi **yoktu**, üçüncüsünde **vardı** ve o link ZIP getirdi
(**47,8 MB, `ilk2=PK`**). Dropbox'ın yeni paylaşım jetonu; sonda adresi yeniden
kurarken onu **düşürüyordu**, yani çalışabilecek bir linki kendi elimizle
geçersiz kılma ihtimali vardı. Artık ayıklanıp her yeniden kurulan biçimde
taşınıyor ve `st jetonu: VAR/yok` diye yazılıyor.
*İki ölçüm "bu yol kapalı" dedirtmişti; üçüncüsü linkler arasındaki farkı
gösterdi. Aynı hatayı iki kez ölçmek, onu doğrulamıyor.*

- **`unzip` ÇIKIŞ KODU BURADA ÖLÇÜM DEĞİL.** Dropbox'ın ZIP'i kök girdisini
  adsız `/` diye yazıyor; unzip onu haritalayamayıp önce uyarıyor (rc=1) sonra
  rc=2 ("zipfile format") dönüyor — oysa **gerçek dosyaların hepsi açılıyor**.
  rc'ye bakan iki sürümüm de 47,8 MB'lık sağlam bir klasörü **çöpe attı**;
  ikincisi "acilan dosya=88" yazarken bile. Doğru ölçüm **BEKLENEN ile AÇILAN**:
  `unzip -Z1` listesindeki dosya sayısı (dizinler ve adsız kök hariç) ile diske
  inen sayı. Eşitse geçer, rc ne derse desin; eksikse rc 0 bile olsa **DURUR**.
  *Aracın bayrağı sonucu değil, kendi iç durumunu anlatıyordu.*
- **Özet listenin SONUNA alındı:** `get_job_logs` yalnız kuyruğu gösteriyor,
  88 satırlık dosya listesinde baştaki sayım kuyruktan düşüyordu.

**Dropbox paylaşım klasörü: sunucu ERİŞEMİYOR — "No Access"** (operatör,
12 Eyl 2026, iki klasör: `WOMEN/DSQUARED` ve `DOLCE&GABBANA`).
- Bu ortamdan dropbox.com'a çıkış yok (ölçüldü, `curl` → `http=000`), o yüzden
  indirme **sunucuda**: `fetch-external-images.yml` → **MOD E**
  (`dropbox_url` + `dropbox_slug`). Klasörü ZIP olarak `~/wt_incoming/<slug>`
  altına açıyor, dosya listesini yazıyor, `public_html/uploads`'a **hiçbir şey**
  kopyalamıyor — klasörün içinde ne olduğu görülmeden fotoğraf siteye girmez.
- **Sunucu Dropbox'a ÇIKABİLİYOR** (`http=200`, ~202 KB) ama gelen şey ZIP değil
  **HTML**: başlığı **`Dropbox - No Access`**. Üç ayrı adres biçimi denendi
  (`?subpath=`+`dl=1`, verilen link+`dl=1`, kökün tamamı+`dl=1`) — **üçü de
  aynı sayfa**. Yani sorun adres biçimi değil, **linkin kendisi dışarıdan
  görüntülenemiyor**: paylaşım ayarı "davet edilenler"/ekip içi görünüyor.
  Çözüm operatörde: klasörü **"Anyone with the link"** olarak paylaşmak.
- **İKİNCİ, TAMAMEN AYRI paylaşım da aynı sonucu verdi** (operatörün D&G linki,
  başka `fo` id + başka `rlkey`): yine `Dropbox - No Access`, yine üç biçim de.
  Üstelik **kök denemesi iki linkte de BİREBİR 205.836 bayt** döndü — yani gelen
  şey klasöre özgü bir sayfa değil, **genel bir ret sayfası**. Sorun tek bir
  linkte değil, **paylaşımın kendisinde**. *Tek ölçüm "bu link bozuk" derdi;
  ikinci ölçüm "bu yol kapalı" dedirtti.*
- **Ayırt edilemeyen tek şey ve onu ÖLÇEBİLECEK kişi operatör:** "link özel" ile
  "Dropbox sunucunun IP'sini engelliyor" dışarıdan ayrılamıyor (200 + markalı
  "No Access" sayfası ilkine benziyor — IP engelinde genelde captcha/403/429
  gelir, ama kesin değil). **Kontrol grubu:** operatör linki **gizli/incognito**
  pencerede (Dropbox oturumu KAPALI) açsın. Orada da "No Access" çıkıyorsa
  paylaşım ayarıdır; açılıyorsa sunucu tarafı engellenmiştir ve çözüm başka
  (WeTransfer yolu zaten kurulu ve çalıştığı kanıtlı).
- **Sihirli bayt (PK) kontrolü ilk koşuda işe yaradı:** olmasaydı 202 KB'lık
  HTML'i açmaya çalışır ya da ürün fotoğrafı diye bir hata sayfası kaydederdik —
  bu depoda `.jpg` diye kaydedilmiş HTML bir kez yaşandı. **`dl=0` → `dl=1`
  eziliyor**, yoksa Dropbox web arayüzünü döndürüyor.
- **Başarısızlıkta sayfanın KENDİ cümlesi okunuyor** (başlık + `password` /
  `too large` / `access` gibi işaretler): "şifre gerekli", "çok büyük", "erişim
  yok" hepsi ayrı sorun ve ayrı çözüm. Tahmin etmek yerine sayfaya soruluyor.
  Denenen URL'ler **maskeli** — `rlkey` o klasörü açan anahtar ve kütük herkese
  açık.
- **MOD E kendi ADIMINDA, ve bu zorunluydu:** ana betiğe eklenince dosya
  GitHub'ın **21000 karakterlik tek-ifade sınırını** aştı ve workflow **hiç
  dispatch edilemez** oldu (`Exceeded max expression length`) — yani ekleme, var
  olan dört modu da götürüyordu. Her adımın kendi bütçesi var (MOD E 4.588,
  öteki 17.063). *Bu dosyaya yeni bir blok eklerken önce uzunluğa bak.*
- **`run_workflow` 5xx kuralı bir kez daha işe yaradı:** dispatch **502** döndü;
  CLAUDE.md'nin dediği gibi tekrar denemeden önce koşu listesine bakıldı — hiçbir
  şey kuyruğa girmemişti, yani tekrar güvenliydi.

**ÜÇ Dropbox klasörü de AYNI şekilde kapalı; ortak işaret `st=` YOK ve
`subfolder_nav_tracking=1` VAR** (17 Eyl 2026, dört koşu: DSQ JEANS ×2, GCDS,
GUCCI). Operatör *"Erneut versuchen"* dedi, yeniden denendi — sonuç değişmedi.

| Klasör | `fo` kimliği | `rlkey` | `st` | Sonuç |
|---|---|---|---|---|
| DSQ JEANS | `3kfz28…` | `mfp01…` | yok | No Access |
| GCDS | `3kfz28…` (başka alt kimlik) | `mfp01…` | yok | No Access |
| GUCCI | `1mggxg…` | `7vn9ry…` | yok | No Access |

- Üçü de **200** dönüyor ama gelen şey ZIP değil ~204–210 KB'lık HTML ve
  başlığı **`Dropbox - No Access`**. Kök klasör de aynı sayfayı veriyor, yani
  sorun alt yol ya da adres biçimi **değil** — paylaşımın kendisi sunucuya
  kapalı. Üç ayrı paylaşım, iki ayrı `rlkey`, tek sonuç.
- **Fark edilen ortak işaret:** üç linkte de `subfolder_nav_tracking=1` var ve
  **hiçbirinde `st=` yok.** O parametre, linkin Dropbox arayüzünde **gezinirken
  adres çubuğundan** kopyalandığında ekleniyor; **Share → Copy link** düğmesinin
  ürettiği linkte ise `st=` (imzalı paylaşım jetonu) bulunuyor. 12 Eylül'de
  **işe yarayan** link tam da `st=` taşıyan üçüncü linkti ve 47,8 MB'lık ZIP
  getirmişti. *Bu bir korelasyon; nedeni kesinleştiren tek şey `st=`li bir
  linkin çalışması olur.*
- **Operatörün yapması gereken, en kısa hâliyle:** klasörü Dropbox'ta açıp
  **Share → Copy link** ile linki almak (adres çubuğundan kopyalamak değil).
  Çalışmazsa paylaşımı **"Anyone with the link"** yapmak. Üçüncü yol: bu depoda
  **çalıştığı kanıtlı** olan WeTransfer akışı (`wetransfer_probe`).
- **Kontrol grubu (ayırt edici ölçüm, operatör tarafında):** linki **gizli
  pencerede**, Dropbox oturumu KAPALI açsın. Orada da "No Access" çıkıyorsa
  paylaşım ayarıdır; açılıyorsa sunucu tarafı ayrı bir sorundur ve teşhis
  değişir.

**GUCCI partisi — fiyat kararı ALINDI, klasör bekliyor** (operatör, 17 Eyl
2026: *"bu guccileride 140 eur dan koy polo lari tshirtleri 125"* + *"10 lu en
az alimda"*).
- **Polo 140 EUR · tişört 125 EUR · asgari alım 10 · paket 10'luk.** Asgari,
  paket adımının tam katı (KURAL 4b: sepet adedi adımın katına yuvarlıyor,
  yani katı olmayan bir asgari hiç alınamaz).
- **Polo/tişört ayrımı FOTOĞRAFTAN yapılacak**, dosya adından değil: D&G
  partisinde dosya adı kategoriyi söylemiyordu ve 112 px'lik kontakt
  sayfasında tişört sanılan iki kare 300–360 px'te **yakalı polo** çıkmıştı —
  iki fiyat hatası ancak büyütünce göründü. Burada aradaki fark **15 EUR**.
- Katalogda zaten duran Gucci ilanları **atlanacak** (bugün 12+ Gucci ilanı
  var); eşleşme **stil kodundan**, addan değil — `XJDEZ` vakası katalog adının
  güvenilmez olduğunu bir kez kaydetti (bizde *"GG Print T-Shirt"*, gerçekte
  Doraemon × Gucci).

**GCDS partisi de BEKLİYOR — aynı Dropbox engeli** (operatör, 17 Eyl 2026:
*"sonra bu ürünler yoksa bunlarida al internet official fiyatlarin 4 te biri
yap ve koy 10 lu yap lotlari"*, `…/GCDS` klasörü).
- Ölçüldü (aynı gün, 18:00 UTC): `st jetonu: yok`, üç adres biçimi de
  **`Dropbox - No Access`** (204–210 KB HTML, ZIP değil). DSQUARED kot
  klasörüyle **birebir aynı** sonuç, yani sorun tek bir klasörde değil
  **paylaşımın kendisinde** — iki ayrı `fo` kimliği, iki ayrı alt klasör, aynı
  ret sayfası.
- **Fiyat kuralı kayıtta: resmî internet fiyatının DÖRTTE BİRİ**, lot **10'luk**.
  Klasör açıldığında her modelin resmî fiyatı **aranacak ve yazıya geçecek** —
  bulunamayan modelde fiyat **uydurulmayacak**, o ilan yazılmayacak (KURAL 3).
  *Bu, Gallery Dept.'te perakende rakamlarının neden fiyata gömülmediğinin
  tersi bir durum: orada oran yoktu, burada operatör açık bir oran verdi.*

**DSQUARED2 kot partisi BEKLİYOR — Dropbox linki "No Access"** (operatör,
17 Eyl 2026: *"daha sonra bu jeansler yoksa bunlarida koy 125-165 eur arasi
shortlar 90 eur"*, `…/DSQUARED/DSQ JEANS` klasörü).
- Sunucu Dropbox'a çıkabiliyor (`http=200`) ama gelen şey ZIP değil **HTML**:
  başlığı **`Dropbox - No Access`**. Üç adres biçimi de denendi (kök+subpath,
  verilen link+`dl=1`, kökün tamamı) — üçü de aynı sayfa, 204–211 KB.
- **Sebep okunabiliyor:** sondanın kendi satırı **`st jetonu: yok`**. 12 Eylül'de
  kaydedilen ders bunun aynısı: operatörün ilk iki linkinde `st=` yoktu ve
  ikisi de "No Access" verdi, `st=` taşıyan üçüncü link **47,8 MB'lık ZIP**
  getirdi. Bu link de `rlkey` taşıyor ama `st=` **taşımıyor**.
- **Yapılacak operatörde:** Dropbox'ta klasörü açıp **"Copy link"** ile linki
  yeniden alması yeter (yeni paylaşım jetonu `st=` ile geliyor); ya da klasörü
  **"Anyone with the link"** olarak paylaşması. Link gelince tek koşu:
  `fetch-external-images` → `dropbox_url` + `dropbox_slug=dsq-jeans`.
- **Fiyat kararı zaten alınmış durumda ve kayıtta:** kot **125–165 EUR**
  (pahalılığa göre), **şort 90 EUR**. Klasör açılınca kareler tek tek bakılıp
  bu aralığa göre fiyatlanacak; kot/şort ayrımı **fotoğraftan**, dosya adından
  değil (D&G partisinde dosya adı kategoriyi söylemiyordu ve iki fiyat hatası
  ancak 300–360 px'te büyütünce çıkmıştı).
- **Katalogda zaten duran kotlar atlanacak** (*"bu jeansler yoksa"*): DSQUARED2
  bölmesinde bugün 64 ilan var ve `dsq-all.json` / `dg-jeans.json` partileri
  duruyor; eşleşme **stil kodundan** yapılacak, addan değil.

**Vitrin sırası: Balenciaga ve Lacoste en başta** (operatör, 12 Eyl 2026:
*— aynı gün D&G ve DSQUARED2 de eklendi; güncel liste bu bölümün SONUNDA.*
*"ürünlerin yerlerini degistir balenciaga ve lacostelar basta kalsin"*).
- **Bu, 11 Eylül'e kadar geçerli olan kararın ÖN TARAFTA geri alınması.** Eski
  not *"katalog Gucci ile açılıyor, arkasında Givenchy, sonra Lacoste"* diyordu;
  yeni cümle bunu değiştiriyor. Gucci/Givenchy **listeden çıkarılmadı**, yalnızca
  iki markanın arkasına alındı — eski yorum olduğu gibi bırakılmadı, çünkü
  birbirini tutmayan iki kayıt hangisinin geçerli olduğunu okunamaz yapar
  (KURAL 21d'nin aynı dersi).
- **Ölçüm tasarımı değiştirdi, tahmin değil.** Lacoste'un **11 ilanının 10'u**
  zaten lead satıcı bölmesindeydi (`seller_uid=7ab30f26afedd840`), yani zaten
  öndeydi; Balenciaga'nın **20 ilanının 20'si** `rest`'te, yani **en arkada**
  (hiçbirinde lead satıcı kimliği yok, ve BALENCIAGA `$leadBrands`'te hiç
  geçmiyordu). Yani istenen iş "ikisini de öne al" değil, **Balenciaga'yı en
  arkadan öne çekmek** ve Lacoste'u oradayken kaybetmemekti.
- **Ön marka kontrolü SATICI kontrolünden ÖNCE sorulmak zorunda.** Sonra
  sorulsaydı GARAGE LE PARIS'in 10 Lacoste'u satıcı bölmesine düşer ve orada
  **katalog sırasına göre dağılırlardı** — yani "başta" olmazlardı.
- **Bedeli açıkça yazılı:** lead satıcının (GARAGE LE PARIS) Lacoste **dışındaki**
  ~46 ilanı artık Balenciaga+Lacoste'un (~31 ilan) arkasında. Eski notun *"o
  hesabın stoğu diğerlerinin önünde olsun"* gerekçesi bu kadar yumuşadı; operatör
  aksini isterse tek satır (sırayı `$sel`'den sonraya almak).
- **Sıra sayfa gövdesinden ÇIKARILDI:** tek karar noktası ve saf
  `vestra_shop_order()` (`inc/products.php`), `shop.php` yalnızca çağırıyor.
  Gövdeye gömülü olduğu sürece **sınanamıyordu**; bu depoda "aynı olgu iki yerde
  yazılı" hatası defalarca kayıtlı.
- **Eşleşme TAM, alt dize değil** (mango/zara dersi). Bedeli burada daha sessiz
  olurdu: `BALENCIAGA` alt dize arandığında bir gün gelecek "Balenciaga Kids"
  gibi bir ad da öne çıkar ve kimse fark etmez.
- **Bölme, sıralama anahtarı DEĞİL:** her grubun içinde ürünler
  `vestra_products()`'ın döndürdüğü sırayı aynen koruyor — bir markayı öne almak
  diğer 300'ü yeniden dizmemeli. `_ord` alanı (sayfanın "newest" sortu için)
  sıralamadan **önce** yazılıyor ve taşınıyor; taşınmasaydı öne çekilen markalar
  sayfada **en eski stok** gibi görünürdü.
- **Yerelde ÇİZDİRİLDİ** (kaynak okumak ölçüm değil): kum havuzunda `php -S` ile
  `/shop` çekilip ürün id'leri sırayla okundu — `blc-1, blc-2` → üç Lacoste →
  GARAGE LE PARIS'in geri kalanı → Gucci, Givenchy → diğerleri. PHP uyarısı
  **0** (grep'in yakaladığı 8 satırın hepsi çerez bandının `notice` sınıfı).
- **CANLI ÖLÇÜM: `inspect-products.yml` → `shop_order=premium`** (run 163).
  Bu ortamdan canlı siteye çıkılamıyor (`curl` → `http=000`), o yüzden sıra
  **sunucuda, deploy edilmiş fonksiyonla** hesaplanıyor. Sonuç: premium 336
  ilan (katalog 817); **Balenciaga 20 ilan, sıra 4..23** (yani 20'si de bitişik
  ve önde), **Lacoste 12 ilan, sıra 2..34**. Sonda ilk 24'ün yanında **konum
  özeti** de basıyor — ilk 24 tesadüfen doğru görünebilir, 20 ilanlı bir markanın
  20'sinin de başta olduğunu ancak sayım gösterir. *Kütükteki `22` numaralı satır
  `***` çıkıyor: `DEPLOY_PORT`="22" maskesi, hata değil.*
- **CANLI ÖLÇÜMÜN ORTAYA ÇIKARDIĞI, OPERATÖR KARARI BEKLEYEN ŞEY:** ilk üç sıra
  Balenciaga/Lacoste değil — `guc-t07` (Gucci), `lac-polo-paris` (Lacoste),
  `rl-csf-polo-white` (**Ralph Lauren**). Üçü de **`pinned`**, yani operatörün
  daha önce kendi ellediği ilanlar, ve `pinned` bölmesi her şeyin önünde.
  (Çıkarım kesin: Ralph Lauren ne ön ne lead markada, ve satıcı bölmesinde olsa
  bütün Balenciaga'lardan **sonra** gelirdi.) Yani bir Gucci ile bir Ralph Lauren
  hâlâ 20 Balenciaga'nın önünde duruyor. **Kendiliğinden değiştirilmedi:** iki
  operatör kararı karşı karşıya (eski iğnelemeler ile yeni "başta kalsın"), ve
  birini ötekine kurban etmek bizim kararımız değil. İstenirse tek satır — ön
  marka bölmesini `pinned`'in önüne almak.
- Test: `tests/shop_order_test.php` (30 iddia). **İki yönü de** tutuyor: öne
  çıkması gerekenler ve *yerinde kalması* gerekenler (lead satıcı, lead markalar,
  grup içi katalog sırası). Düşebildiği doğrulandı — her sabotajın gerçekten
  uygulandığı ayrıca yazdırılarak: ön marka bölmesi kaldırılınca **10 kırmızı**,
  satıcıdan sonraya alınınca **2**, eşleşme alt dizeye gevşetilince **1**, liste
  sırası yok sayılınca **2**.

- **Actions günlüğü gizli değerleri HER YERDE maskeler:** `DEPLOY_PORT`="22"
  yüzünden `1225` → `1***5`, `222` → `***`, fiyat `22.95` → `***.95`, base64'ün
  içi dahil. Loga base64 basarken karakter arası boşluk koy (`|spaced`); günlükten
  sayı okurken `***`'ın "22" olabileceğini bil. Ters çevirme `222`'de kayıplı.
- **Runner IP'si barındırıcının güvenlik duvarına takılabilir:** 3 Eyl 2026
  16:55'te bir koşuda 9 HTTP isteği de 30 sn'de `000`, ardından SSH `dial tcp:
  i/o timeout`; aynı dakikada başka runner'dan deploy başarılı, 6 dk sonra taze
  runner `/` 200 / 80 ms. "Site çöktü" demeden önce **ikinci runner'la** bak.
- **`get_job_logs` çıktısı büyükse dosyaya düşer** (`tool-results/...txt`, tek
  satır JSON: `logs_content`); python ile `json.loads` → `split('\n')`. 1,4 MB'lık
  kontakt sayfası günlüğü bu yolla okundu.

**KURAL 25 — D&G Dropbox partisi: 88 fotoğraf → 68 ilan, hepsi `pending`**
(operatör, 12 Eyl 2026: *"olmayan ürünlerin hepsini koy bu DG leride"* +
*"dikkat ayni ürün varsa katalogta pas gec"*).

- **Dosya adı KATEGORİ SÖYLEMİYOR, yalnız stil kodu taşıyor** (`D&G G8OL0ZFU7E.jpg`).
  Fiyat ise kategoriye bağlı, yani 88 karenin **hepsi göz ile** sınıflandırıldı:
  `diag-live` → `wetransfer_probe=sheet:…|perfile`. Şüpheli 23 kare 300–360 px'te
  tekrar bakıldı ve **bu adım iki fiyat hatasını yakaladı**: `G8OL0Z FU7EN` ve
  `G8PL4T G7F2H` 112 px'te tişört görünüyordu, büyütünce **yakalı polo** çıktı
  (€90, €85 değil). *Kontakt sayfası hücre başına bir KLASÖR basıyordu; düz bir
  klasörde 88 fotoğraf 2 küçük resme iniyor, yani sınıflandırma için yazılmış
  araç sınıflandıramıyordu — `|perfile` ve `|cell=N` bunun için eklendi.*
- **88'den düşenler:** 5 katalogda **zaten var** (`G7JV9-1`, `G8PL4T G7F2H 0`,
  `G8QI4TFU7EQ_W`, `G9OW6Z DARKBLUE`, `GWNXAD-G8GW9`) — operatörün "pas geç"i;
  9 iç giyim/mayo (operatör kararı: şimdilik koyma); 1 örgü V yaka
  (`G8QG3T FU7EP` — fiyat listesinde karşılığı yok); **2 dosyada stil kodu HİÇ
  yok** (`DOLCE&GABBANA BLACK.jpg`, `JEANS/IMG_3073.JPG`) → **SKU uydurulmadı**,
  ikisi de yazılmadı. Kalan 71 kare → **68 ilan** (`G8OL6Z G7C8G`'nin dört rengi
  tek ilanda — Fred Perry M3600 deseni).
- **Fiyatlar operatör kararı**, kategoriye bağlı: tişört **85**, polo **90**,
  sweat/hoodie **120**, kot **120**, kot şort **90**, eşofman altı **120**.
  Kot ve iç giyim fiyat tablosunda **yoktu ve soruldu** — 29 fotoğrafın (kot 14,
  kot şort 3, eşofman 4, iç giyim 8, mayo 1) hiçbiri fiyatlanamıyordu ve
  uydurmak yerine karar alındı. **Ceket €139 kullanılmadı:** klasörde ceket
  görünen tek kare (`GVETAZ HU7B7`) büyütünce **eşofman altı** çıktı; yanındaki
  ceketin kendi kodu var (`G9XM5Z HU7B7`) ama **kendi dosyası yok**.
- **13 KOTTA RENK YAZILMADI.** Kaynak yıkama adını vermiyor ve küçük resimden
  "light blue" ile "mid blue" ayırmak uydurma olurdu (KURAL 3). Siyah ve gri
  ayırt edilebildiği için o ikisi yazıldı. Fotoğraf zaten sayfada; adı operatör
  isterse ekler. *Renk listesine bir ad eklemek ücretsiz, doğru olması değil.*
- **Beden serisi ve MOQ uydurulmadı:** mevcut 35 D&G ilanının **taşıdığı**
  değerler (üst giyim `S×1 · M×3 · L×3 · XL×2 · XXL×1 · 10 pcs/pack` / MOQ 20,
  kot `44×1 … 54×1`). Katalogun kendi kaydı, şablon değil.
- **`mode='fixed'`** — `sale`'de `list` üstü çizili ESKİ fiyattır (KURAL 4),
  yani 68 ilan da hiç var olmamış bir indirim ilan ederdi.
- **`status='pending'`**: operatör `Admin ▸ Products`'ta onaylayana kadar
  katalogda **görünmüyor**. "Hepsini koy" talimatı ile KURAL 21'in "önce göster"
  şartı böyle birlikte karşılandı.

**Kendi hatam, kayda geçsin — ve kuru koşu yakaladı.** Kontakt sayfasının
efsanesinden okuduğum dosya adını **olduğu gibi** görsel yoluna yazdım, oysa
Actions kütüğü literal "22"yi `***` yapıyor: `G9ABJTG7F2GR***54.jpg` katlanınca
`d-g-g9abjtg7f2gr-54.jpg` oluyor ve sunucuda öyle bir dosya yok. Kardeş dosya
`G9ABJTG7F2GBA232` (renk kodu 5 karakter) olduğu için `R2254` çıkarıldı ve
**ikinci kuru koşu fotoğrafı bulunca doğrulandı** — çıkarım kanıt değildi, kuru
koşu kanıttı. *Bu dosyanın kendi kaydı: "okunamayan bir değeri tahmin etmek
serbest; tahmini doğrulatmadan yazmak değil."*

**Sonuç (run `34717589395`):** `KAYDEDILDI — 68 yeni urun, katalog 827 → 895`,
zaman damgalı yedek alındı. Geri okuma (`inspect-products` → `raw_scan`, çünkü
`vestra_products()` yalnız `approved` döndürür ve taze parti orada **0**
görünür): **895 kayıt, `pending=68`**, `tiers` hepsinde var. Yeni satırlarda
`seller_uid` yok — mevcut 35 D&G ilanında da yok, yani sapma değil.

**Operatör kararı bekleyen üç şey:**
1. **13 kotun yıkama adı** — fotoğrafa bakıp yazabilir; ben uydurmadım.
2. **`G8QG3T FU7EP`** (beyaz örgü V yaka, düğmeli) — tişört mü, `Sweaters &
   Knitwear` mi? Fiyat tablosunda karşılığı yok, o yüzden yazılmadı.
3. **9 iç giyim + 1 mayo** — "şimdilik koyma" denildi; fotoğraflar sunucuda
   duruyor (`~/wt_incoming/dg/files`), fiyat verilirse tek koşuda eklenir.

**KURAL 25 — Gallery Dept.: 9 ilan yazıldı ve AÇILDI** (operatör, 17 Eyl 2026:
*"Galerry dept. ürünlerini Kataloga al yeni olarak tshirtler fiyat pahaliligina
göre 59,90 ile 64,00 eur arasinda yap sweatshirtleri fiyatina göre 125-145 eur
yap"* → *"10 lu paketler halinde yap ve en az alim 20 ad. olsun"* → *"ayrica
ürünleri aprrova yap siteden"*).
- Dokuz fotoğraf zaten `uploads/coming-soon/gallery-dept` altındaydı. Dokuzu da
  **göz ile** sınıflandırıldı: 8 tişört + 1 turkuaz fermuarlı kapüşonlu.
- **Bu markanın hiçbir tedarikçi fiyatı depoda YOK.** Operatörün verdiği aralık
  içindeki sıralama bu yüzden **BASKI YERLEŞİMİNE** göre — üretim maliyetini
  belirleyen ve fotoğrafın gerçekten kanıtladığı tek şey o:

  | | |
  |---|---:|
  | küçük göğüs logosu (4 ilan) | 59,90 |
  | büyük ön baskı (2 ilan) | 61,90 |
  | kol baskısı (2 ilan) | 64,00 |
  | fermuarlı kapüşonlu (1 ilan) | 145,00 |

  Kapüşonlu **aralığın tepesinde**: fermuar + kapüşon o kategorinin en pahalı
  konstrüksiyonu ve kategoride tek ürün o. **Web'deki perakende rakamları
  okundu ama fiyata GÖMÜLMEDİ** — aynı modelin boyalı/yıpratılmış sürümleri
  ($199–$360 arası) karışıyor, yani sıralama için güvenilir bir eksen değil.
- **Uydurulmayanlar:** üretici stil kodu yok, o yüzden SKU **iç referans**
  (`VS-GD-*`, `guc-t12`'nin deseni) ve açıklamalarda model numarası **yazmıyor**.
  MOQ 20 · 10'luk karton · beden serisi **katalogun kendi premium tişört
  standardı** (D&G partisi), bu markanın paketlemesi hakkında bir tahmin değil.
  `ships_from` yok → platform varsayılanı.
- **İki fotoğraf aynı modelin iki rengi ve adları AYNI bırakıldı**
  (`Sleeve Print T-Shirt — Black/White`): beyaz olanda arka baskı kumaştan
  geçiyor, siyahta görünmüyor. Adı ayırmak, **doğrulayamadığımız** bir fark
  iddia etmek olurdu; fark yalnız o kaydın **açıklamasında**, çünkü fotoğrafta
  görünen şey o.
- **Fotoğraflar küçük: 723–900 px** (katalogun Fred Perry kareleri 1316–1523 px).
  Büyütülmedi — büyütmek çözünürlük üretmez. Tedarikçi daha büyük kare verirse
  tek koşuda değişir.
- **`add-products` kuru koşusu bir şey yakaladı ve tam bu yüzden var:** ilk koşu
  dokuz satırın dokuzunu da *"görsel SUNUCUDA YOK"* diye reddetti. `uploads/`
  yalnız **deploy** push'unda senkronlanıyor, parti ise çalışma dalındaydı.
  Deploy'dan sonra dokuzu da bulundu. *Bir partiyi yazmadan önce fotoğrafların
  sunucuya İNDİĞİNİ ölç.*
- **Geri okuma (`inspect-products` → `check_images`, `brand=Gallery Dept.`):**
  **9 ürün, 9 görsel, kayıp/bozuk 0.** Bu sonda yalnız `vestra_products()`
  geziyor, yani **sadece `approved`** — dokuzun orada görünmesi durumu da
  kanıtlıyor. Katalog 896 → **905**.
- **9 dilde ad/açıklama YOK** ve bu bir gerileme değil: `name_i18n`/`desc_i18n`
  Kuloğlu iç çamaşırı kataloğuna özel bir karardı (KURAL 21); bütün premium
  partiler (D&G, DSQUARED2, Balenciaga) İngilizce ad taşıyor.
- **"Yakında" şeridi kendiliğinden düzeliyor:** `vestra_soon_brands_filter()`
  satışa çıkan markanın klasörünü basmıyor (16 Eyl denetimi). Klasör silinmedi.

**KURAL 25 — 68 ilan AÇILDI** (operatör, 12 Eyl 2026: *"tüm ürünleri aprrovals
yap fotolariyla beraber"*). `product-fixes/dg-approve.json` + `set-product.yml`;
her satırda `expect:1`, kuru koşu **68/68** eşleşti, sonra uygulandı
(`KAYDEDILDI — 68 alan guncellendi`, zaman damgalı yedek). Geri okuma:
`approved=883` (815 + 68), **`pending` tamamen bitti**.

- **"Fotoğraflarıyla beraber" ÖLÇÜLDÜ ve ölçüm ÖNCE yanlış kümeye baktı.**
  `inspect-products` → `check_images` modu `vestra_products()` geziyor, yani
  **yalnız approved**: onay bekleyen 68 ilanı **yapısı gereği göremiyor**.
  İlk koşu mevcut **35 onaylı** D&G ilanını ölçüp *"TUM FOTOLAR YERINDE"* dedi —
  doğru cümle, yanlış küme, ve ona güvenip onaylasaydım kırık görselle yayına
  açma ihtimali ölçülmemiş kalırdı. Aynı denetim `raw_scan`'e eklendi (ham
  liste, pending dahil): onaydan **önce** `diskte VAR: 106 / kayip 0`
  (35 eski + 71 yeni kare), onaydan **sonra** katalog geneli
  **`diskte VAR: 1248 / kayip 0 / hic gorseli olmayan ilan 0`**.
  *Bir partiyi doğrulayan sonda o partiyi göremiyorsa, verdiği yeşil başka bir
  şeyin yeşili.*
- **`set_product.php` eşleşmeyi `match` alanından okuyor, `id`'den değil.** İlk
  onay dosyamı `id` ile yazdım; kuru koşu 68 satırın **68'ini de** reddetti
  (`'match' bos`) ve hiçbir şey yazılmadı. Mevcut `nbb-approve.json`'a
  bakmadığım için oldu. *Bir dosya biçimini yeniden icat etmeden önce, aynı işi
  yapan mevcut dosyayı aç.*

**KURAL 25 — Dört D&G ilanı KOMPLE TAKIM: €190, MOQ 10** (operatör, 12 Eyl 2026:
*"Jogging Trousers komple fiyat 190 eur olsun tüm alt üst dg ler böyle olmali
ayrica en az alimlari 10 a indir bunlarda"*).

- **Sınıflandırmam yanlıştı ve talimat onu düzeltti.** Dördünü de yalnız ALT
  ("Jogging Trousers", €120) diye yazmıştım. 112 px'lik kontakt sayfasında
  pantolon görünüyordu; **512 px'te** dördünün de üst+alt olduğu, üçünün
  künyesinde **iki kod birden** yazdığı görüldü:
  `GVETAZHU7B7 ← G9XM5ZHU7B7` (fermuarlı eşofman üstü), `GWT1AZHUMLX ←
  G9UR8ZHUMLX` (bisiklet yaka sweatshirt), `GX630T JBMJ0 ← GXE02T JBMJ0`
  (kapüşonlu). Dördüncüsü (`GVEPAZFU7DU`) **tek kod** taşıyor ama üç karesinin
  üçü de aynı komple kombin; ayrı bir pantolon karesi **yok**.
- **AD DEĞİŞTİ, çünkü zorunluydu.** "Jogging Trousers" diye duran bir ilanı
  €190'a komple takım olarak satmak, sepetin sattığı şeyle çelişen bir ilan
  bırakırdı — `desc`/`sizes` ve faturanın üç katmanının verdiği dersin aynısı.
  Her ad artık **iki parçayı da** yazıyor ve üst parçanın türü fotoğraftan:
  Sweatshirt & Trousers Set / Track Jacket & Trousers Set / DG Logo Sweatshirt
  & Trousers Set / Logo Script Hoodie & Trousers Set.
- **`tiers` birlikte yazılmak ZORUNDA.** `set_product.php` ilk kademenin `moq`'ya
  eşit olmasını şart koşuyor ("merdiven minimum siparişten başlamalı"); `moq=10`
  verip kademeyi 20'de bırakmak **reddedilirdi**. MOQ 10, `size_step=10`'un tam
  katı (KURAL 4b).
- **`sizes`'a DOKUNULMADI.** "10 pcs/pack" → "10 **sets**/pack" yazmak cazipti:
  bir takımda "10 pcs" 10 giysi diye okunabiliyor. Ama `VESTRA_SIZE_PACK_RE`
  yalnız `(pcs|pieces|adet)` tanıyor — yeni kelime paket ekini **tanınmaz**
  yapar ve "10" sessizce bir BEDEN olarak ayrışırdı (KURAL 21b'nin `3/pack`
  tuzağı). Ad zaten takım olduğunu söylüyor; ayrıştırıcıyı süs için genişletmek
  yanlış taraf.
- **KAPSAM ÖLÇÜLDÜ, TAHMİN EDİLMEDİ.** Bir eşofman **üstü** katalogda
  "sweatshirt" diye duruyor olabilirdi, yani "tüm alt üst" dokuz sweat/hoodie
  ilanını da kapsayabilirdi. 48-57 arası **384 px**'te bakıldı: dokuzu da **tek
  giysi**, künyede tek kod. Kapsam bu yüzden **genişlemedi** — ölçüm bir kez de
  *hayır* demek için yapılır.
- **Doğrulama alanın değerine değil, SEPETİN TAHSİL ETTİĞİNE bakıyor:**
  `inspect-products` → `price_audit`. Katalog geneli **886 ürün, tutarlı 815,
  alıcı aleyhine 1, kademesiz 0** ve dördü de aleyhine listesinde **yok** —
  yani ilan edilen €190, MOQ 10'da sepetin gerçekten aldığı rakam. `list`
  alanını okuyup "190 yazıyor" demek bu deponun defalarca kaydettiği yarım
  doğrulama olurdu. Görsel 4/4, kayıp 0.
- **Yan bulgu, operatör kararı bekliyor — ve raporlamadan ÖNCE ölçüldüğü için
  düzeldi.** Kontakt sayfasının **50.** karesi (`D&G G9OW6Z DARKBLUE.jpg`) de
  iki sıralı bir **takım** çekimi (koyu lacivert sweatshirt + jogger) ve
  ithalatta atlanmıştı. "Kopya diye atlanmış ama aslında ikinci bir renk" diye
  yazacaktım; **kayda bakınca öyle değildi**: `dgn-g9ow6zdarkblue`
  (sku `G9OW6Z DARKBLUE`, `/uploads/dg-root4/…`) **zaten katalogda**, daha eski
  bir partiden. Atlama gerekçesi doğruydu. *Hafızadan rapor etmek, bu turda tam
  da tersini söyleyecekti.*
  Asıl soru bu değil: o ilan **"Crewneck Sweatshirt — Blue" adıyla, €90'a, tek
  üst gibi** duruyor — yani dört eşofmanda bugün düzelttiğim okuma hatasının
  aynısı, üstelik `mode=sale` ve **teklif AÇIK**. Kendiliğinden €190 yapılmadı:
  künyesi tek kod veriyor (`G9OW6Z G7C8H`, ki o kod **`dgx-g9ow6z-g7c8h`**
  adlı €120'lık canlı mavi sweatshirt'te de duruyor — aynı stil kodu iki ilanda,
  iki fiyatta), ve canlı görselin bu kareyle **bayt bazında aynı olduğu
  doğrulanmadı**, yalnız dosya adı aynı. Şüpheliyi kendi başına fiyatlamak,
  talimatı bahane edip tahmin yazmak olurdu.

**Vitrin sırası (EN GÜNCEL): Gallery Dept. ve Fred Perry en başta; ÖN MARKA
bloğu artık YENİ'nin de önünde** (operatör, 17 Eyl 2026: *"ayrica galerry dept.
ürünleri en basa al new yap"* → *"F.Perry i de en basa al"*).
- `vestra_shop_front_brands()` = **`[GALLERY DEPT., FRED PERRY, BALENCIAGA,
  LACOSTE, DOLCE & GABBANA, DSQUARED2]`**. Önceki dördü **kaldırılmadı**,
  sırası da bozulmadı; yalnızca önlerine iki marka girdi.
- **`GALLERY DEPT.` NOKTASIYLA yazılıyor.** Eşleşme TAM ve katalogdaki değer
  `Gallery Dept.` — noktasız yazılsaydı dokuz ilanın hiçbiri öne gelmez ve
  **sayfa hata da vermezdi** (D&G'nin boşluklu ampersanının aynı dersi).
  Testte iki marka için de `strtoupper(trim(<katalogdaki değer>))` iddiası var.
- **"new yap" için yazılacak bir şey YOKTU:** rozet `added_at`'ten türüyor ve
  bu dokuz ilan bugün yazıldı, yani 7 günlük pencerede (KURAL: `NEW` rozeti,
  13 Eyl). Ölçüldü, varsayılmadı.
- **İLK ÖLÇÜM DEĞİŞİKLİĞİ YETERSİZ GÖSTERDİ ve tasarımı o değiştirdi.** Yalnız
  listeye eklemek Fred Perry'yi **28. sıraya** koydu: 4..27 arasının tamamı
  YENİ bloğuydu (9 Gallery Dept + 15 başka taze ilan) ve ön marka bloğu ancak
  28'de başlıyordu. Yani "en başa" talimatı, YENİ bloğu önde kaldığı sürece
  **uygulanamıyordu**. Sınıflandırma sırası değiştirildi: **ön marka kontrolü
  YENİ kontrolünden önce**, birleştirme `pinned → ön marka → YENİ → satıcı →
  lead → geri kalan`.
- **13 Eylül'ün "yeni ürünleri başa koy" talimatı tamamen kaldırılmadı:** ön
  markada **olmayan** taze ilanlar hâlâ satıcı/lead/geri kalanın önünde; ön
  markanın kendi taze ilanları markanın bloğunda duruyor. Bu bilinçli bir
  denge, ve iki talimat gerçekten zıt yöne çektiği için biri diğerine
  feda edilmeden yazıldı.
- **Canlı ölçüm (17 Eyl 2026, `inspect-products` → `shop_order=premium`):**
  `GALLERY DEPT. 4..12 · FRED PERRY 13..14 · BALENCIAGA 15..34 ·
  LACOSTE 2..46 · DOLCE & GABBANA 47..149 · DSQUARED2 150..213`.
- **1..3 hâlâ `pinned` ve bu ÜÇÜNCÜ kez operatör kararı bekliyor:** `guc-t07`
  (Gucci), `lac-polo-paris` (Lacoste), `rl-csf-polo-white` (Ralph Lauren).
  `pinned` bölmesi her şeyin önünde, yani "en başa" alınan markalar teknik
  olarak **4.** sıradan başlıyor. Üç iğneleme de operatörün kendi kararı;
  bunları markalara feda etmek bize düşmez. İstenirse tek satır (ön marka
  bloğunu `pinned`'in önüne almak) ya da panelden o üç ilanın iğnesini kaldırmak.
- Test: `shop_order_test.php` 50 → **55 iddia**. Düşebildiği doğrulandı, her
  sabotajın **gerçekten uygulandığı** ayrıca yazdırılarak: nokta silinince
  **3 kırmızı**, Balenciaga yine öne konunca **4**, YENİ tekrar ön markaların
  önüne alınınca **1**.
- **Falsifikasyon GERÇEK bir boşluk buldu:** birleştirmede YENİ bloğu satıcı
  bloğunun **arkasına** atıldığında takım **yeşil kalıyordu** — yani kodun
  yorumunda yazan *"taze ilanlar satıcı/lead/geri kalanın önünde"* sözünü
  hiçbir iddia tutmuyordu. İddia yazıldı ve kırmızı döndüğü doğrulandı.
  *Bir yorumda söz veriyorsan, o sözü tutan bir iddia da yaz.*

**Ürün sayfasındaki İADE satırı artık yalnız BAĞLANTI** (operatör, 17 Eyl 2026:
*"Nur falsche, fehlende oder mangelhafte Ware · kismini rückgabe den cikar"*).
- Satır özet bir cümle + kanonik metne bağlantı basıyordu; özet kaldırıldı,
  bağlantı kaldı. Bu, KURAL 11'in *"kural tek yerde"* ilkesiyle **aynı yöne**
  bakıyor: özet, kanonik metnin yanında duran ikinci bir kopyaydı ve ikisi er
  geç ayrışırdı.
- Ölü kalan iki sözlük anahtarı (`Wrong, missing or faulty goods only` ve
  noktalı kardeşi) **8 dilden birden** silindi — önce depo geneli tarandı,
  ikisini de basan başka hiçbir yer yok. (Noktalı olan zaten hiç
  kullanılmıyordu; bu iş onu da açığa çıkardı.)
- **Sepetteki cümleye DOKUNULMADI** (`Großhandelsbestellungen sind vom
  Rückgaberecht ausgeschlossen — nur falsche…`): operatörün adlandırdığı parça
  ürün sayfasındaki `·`'lı satırdı. Kapsamı kendiliğinden genişletmek, sepette
  alıcının satın almadan önce okuduğu tek uyarıyı da silmek olurdu.
- `returns_policy_test` yeşil kalıyor: o test sayfanın politikaya **bağlandığını**
  ve gün sayısını **tekrar etmediğini** tutuyor; ikisi de hâlâ doğru.

**Vitrin sırası (GÜNCEL): D&G ve DSQUARED2 de başa** (operatör, 12 Eyl 2026:
*"dg ve ds2 leri basa al"*). `vestra_shop_front_brands()` =
**`[BALENCIAGA, LACOSTE, DOLCE & GABBANA, DSQUARED2]`**.
- **Balenciaga/Lacoste YERİNDE kaldı.** Bir önceki talimat (*"basta kalsin"*)
  kaldırılmadı; yeni iki marka **arkalarına** girdi, yani iki talimat da doğru.
  Operatör D&G/DS2'yi Balenciaga'nın da önüne isterse tek satır (liste sırası).
- **DSQUARED2 lead listesinden ÇIKARILDI** (`lead` artık `GUCCI, GIVENCHY,
  BALMAIN`). Aynı marka iki listede olsaydı ön kontrol önce çalışır ve lead
  satırı **ölü** kalırdı; testte zaten `array_intersect($front,$lead) === []`
  iddiası vardı ve bu yüzden yazılmıştı. **D&G hiçbir listede değildi**, yani
  en arkadaydı — asıl iş onu 405 ilanlık bölmenin dibinden öne çekmekti.
- **Yazım katalogun kendi değerinden:** `strtoupper(trim('Dolce & Gabbana'))`.
  Boşluklu ampersan önemli — `DOLCE&GABBANA` yazılsaydı eşleşme TAM olduğu için
  103 ilanın hiçbiri öne gelmez ve **sayfa hata da vermezdi**. Sonda bunu
  yakalayabiliyor: bir ön marka hiçbir ilana eşleşmezse *"bu bölmede ilan YOK"*
  yazıyor.
- **Canlı ölçüm** (`inspect-products` → `shop_order=premium`, deploy `fa1bb2ee`):
  premium 405 ilan; **Balenciaga 20 (sıra 4..23), Lacoste 13 (2..35),
  D&G 103 (36..138), DSQUARED2 64 (139..202)**. Dördü de tam sayıda yakalandı,
  yani yazımlar doğru; dördü birlikte bölmenin **ön yarısını** kaplıyor.
- **Hâlâ operatör kararı bekliyor (ikinci kez):** ilk üç sıra `pinned`
  (`guc-t07` Gucci, `lac-polo-paris` Lacoste, `rl-csf-polo-white` Ralph Lauren)
  ve `pinned` bölmesi her şeyin önünde. Yani bir Gucci ile bir **Ralph Lauren**
  hâlâ 20 Balenciaga'nın ve 103 D&G'nin önünde. Operatör iki kez marka sırası
  istedi ama iğnelemeler de onun kendi kararı; birini ötekine kurban etmek bize
  düşmez. İstenirse tek satır: ön marka bölmesini `pinned`'in önüne almak.
- Test: `shop_order_test.php` 30 → **32 iddia**. Düşebildiği doğrulandı ve
  sabotajın **gerçekten uygulandığı** ayrıca yazdırıldı: ön liste eski hâline
  dönünce **3 kırmızı**, DSQUARED2 iki listede bırakılınca **2**.

**SATILDI serdi ÇAPRAZ ve büyük; doğrulanmış satıcı rozeti TEK gövdede**
(operatör, 12 Eyl 2026: *"sold out yazsini capraz daha büyük yap ve verified
seller armasini daha estetik yap"*).

- **İstek iki GERÇEK kusuru açığa çıkardı, ikisi de istekten bağımsız duruyordu:**
  1. **İki rozet ÜST ÜSTE biniyordu.** `.ssoldbadge` ve `.svbadge` ikisi de
     `top:10px;left:10px` ve yeşil rozetin `z-index`'i daha yüksek — yani
     **doğrulanmış** bir satıcının **satılmış** ilanında "Verified seller"
     SATILDI'nın üzerine oturuyordu. Bandı ortaya almak bunu kendiliğinden
     çözdü (köşe rozetleriyle artık hiç çakışmıyor).
  2. **Tik BEŞ yerde elle yazılıydı ve beşinde de `stroke="#fff"` GÖMÜLÜYDÜ.**
     Açık "stüdyo" zeminli kartta pil `rgba(28,120,72,.10)` zemin + `#1f7a4c`
     metin, yani **beyaz tik görünmüyordu**. Tek gövde:
     `vestra_verified_badge()`, kontur **`currentColor`** — renk artık CSS'te ve
     tema/zemin değişimi kendiliğinden işliyor. Bu, KURAL 24'ün onay işaretinin
     birebir aynı dersi ("sembol kendi stroke'unu YAZMAZ"). İşaret bir **mühür**
     oldu (daire + tik).
- **Bant:** ortadan geçen, `rotate(-16deg)`, 15px (geniş karoda 18px) — eskisi
  sol üstte 10.5px'lik bir pildi. `pointer-events:none` (kartın kendi
  bağlantısını yutmasın), `left/right` **negatif** (döndürülen kutunun uçları
  aksi hâlde karonun içinde kalır ve bant yarım görünür).
- **RTL:** Arapçada bant **+16°'ye aynalanıyor** ve `letter-spacing` **düşüyor** —
  Arap yazısı bitişik, harf aralığı bağları gevşetir; Latin'de ferahlık olan şey
  orada kusur.
- **Ölçüm ÇİZDİREREK** (kum havuzu, gerçek tarayıcı — kaynak okumak ölçüm değil):
  bant **270×116 px** masaüstü / **223×103** mobil, transform matrisi −16°
  (Arapçada +16°), `pointer-events:none`, `z-index:5`, **yatay taşma 0**;
  rozetin tik konturu artık metinle **aynı renk** (`rgb(28,122,74)` açık zemin,
  `rgb(143,224,180)` ürün sayfası), mühür dairesi basılıyor. PHP uyarısı **0**;
  tek konsol hatası bu ortamdan erişilemeyen Google Fonts.
- **Ölçüm bir yalanı da yakaladı:** RTL `letter-spacing` düzeltmesinden sonra
  sonda hâlâ **3.2px** dedi — kum havuzu o düzeltmeden **ÖNCE** kopyalanmıştı.
  Senkronlanıp yeniden ölçüldü: `normal`. *Değişikliğin gerçekten uygulandığını
  doğrula — bu dosyada zaten kayıtlı ve bir kez daha oldu.*
- **Ürün sayfasının kırmızı "Sold out" cümlesine DOKUNULMADI:** o bir cümle,
  etiket değil; bir paragrafı çapraz yazmak okunmaz yapardı. Operatörün
  "yazı" dediği, fotoğrafın üstündeki karo etiketi.

**Vitrin sırası: YENİ GELENLER de başta — ama TAVANLI** (operatör, 13 Eyl 2026:
*"yeni ürünleri basa koy"*).
- `vestra_shop_order()` artık `pinned`'den hemen sonra bir **YENİ** bölmesi
  taşıyor: pencere içindeki en taze ilanlar, en yeni önce, eşitlikte katalog
  sırası (açık tie-break — aynı gün yazılan bir partinin içinde sırayı
  `usort`'un kararlılığına bırakmıyoruz).
- **PENCERE AYRI BİR EŞİK DEĞİL.** `shop.php`'nin karta bastığı "NEW" rozeti o
  gün 30 gündü; ikisi de artık `vestra_product_is_new()`'den okuyor **(pencere
  bugün 7 — bkz. aşağıdaki madde)**. İki ayrı eşik olsaydı sayfa **"NEW" rozetli
  ama öne alınmamış** kart gösterirdi — bu depoda "aynı olgu iki yerde yazılı"
  hatasının en sessiz hâli.
- **TAVAN (24) BİR ÇELİŞKİYİ ÇÖZÜYOR, süs değil.** Operatör 12 Eylül'de
  "balenciaga ve lacostelar basta kalsin" demişti. Tavansız bırakılsaydı premium
  bölmesinde son günlerin ~100 ilanı (D&G Dropbox + DSQUARED2 partileri)
  Balenciaga'yı **~100. sıraya** iterdi, yani bir gün önceki talimatı sessizce
  geri alırdı. 24 ile ikisi birden **kısmen** doğru: yeni gelenler ilk sıraları
  alıyor, Balenciaga listeden düşmüyor. Tavanı aşan yeni ilan **kaybolmuyor** —
  kendi normal bölmesine düşüyor.
- **TAVANIN BEDELİ ÖLÇÜLDÜ ve tahminimden büyüktü** (13 Eyl 2026, canlı, run
  `34763555452`): Balenciaga'nın ilk ilanı **4. sıradan 28. sıraya** indi
  (3 `pinned` + 24 yeni gelen önünde). `/shop` **sayfalamıyor** — tek uzun
  ızgara — yani "birinci sayfa" diye bir şey yok; 28. sıra 4'lü ızgarada
  **7. satır**. Bu satırı yazarken "hâlâ birinci sayfada başlıyor" demiştim;
  **ölçüm değil tahmindi** ve not düzeltildi. Lacoste görünürde kaldı (2. sıra
  `pinned`, 4. sıra yeni gelen bir GARAGE LE PARIS sweatshirt'ü).
  **Operatör kararı bekliyor:** iki talimat (12 Eyl "balenciaga ve lacostelar
  basta kalsin" ↔ 13 Eyl "yeni ürünleri basa koy") gerçekten zıt yöne çekiyor.
  İki kaldıraç da tek satır: tavanı düşürmek (`VESTRA_SHOP_NEW_MAX`) ya da ön
  marka bölmesini YENİ'nin önüne almak. Kendiliğinden seçilmedi.
- `_ord` alanı YENİ bölmesinde de taşınıyor (yoksa öne çekilenler sayfanın
  "newest" sortunda en eski stok gibi görünürdü).
- Test: `shop_order_test.php` 32 → **48 iddia**. Altı sabotajın her biri önce
  **gerçekten uygulandığı doğrulanıp** kırmızıya çevrildi: YENİ bölmesi kapalı
  **4**, `added_at` guardı kalkınca **15**, pinned aday listesinden elenmeyince
  **1**, rozet yine elle 30 gün okuyunca **2**, sıralama ters **1**, YENİ bölmesi
  ön markalardan sonraya alınınca **1**. *İki sabotajım ilk denemede
  ölçmediğim şeyi ölçtü (`grep` BRE'de `[0]` bir karakter sınıfı, ve
  "yeniden konumlandırma" bloğu aynı yere geri koyuyordu) — sabotajın
  uygulandığını `grep -F` ile ve konum karşılaştırmasıyla doğrulamak şart.*

**NEW rozeti 30 → 7 GÜN** (operatör, aynı gün, birkaç saat sonra: *"yeni
ürünlere yeni ürün olarak markieren yap 7 gün boyunca"*).
- **Tek satır, çünkü sabit tek.** `VESTRA_SHOP_NEW_DAYS = 7`. Operatörün cümlesi
  **rozet** hakkında; sıra da onunla birlikte daraldı ve **ikisi ayrışamıyor** —
  saatler önce alınan "pencere ayrı bir eşik değil" kararının bedelini bugün
  ödemek yerine karşılığını aldık. Rozetin yanında **süre yazan hiçbir metin
  yok** (kart yalnız `t('NEW')` basıyor), yani 8 sözlükte değişecek bir şey de
  yok.
- **"Bu olgu başka nerede yazılı?" sorusu soruldu:** `vestra/` genelinde başka
  hiçbir yerde tazelik eşiği yok. `journal_auto.php`'nin `VESTRA_JOURNAL_AUTO_DAYS`'i
  **bilerek ayrı** bir kavram (günlük raporun penceresi, son rapordan başlıyor);
  kalan bütün "30 gün" geçişleri ilgisiz (üyelik denemesi, ziyaret istatistiği,
  IP coğrafya önbelleği).
- **Neden 30 zaten yanlıştı:** 12 Eylül'ün **68 ilanlık** D&G partisi Ekim
  ortasına kadar "NEW" kalacaktı. Her zaman yanan bir rozet, rozet olmaktan
  çıkar (KURAL 2c'nin "her sabah 0 bekleyen yazan uyarı okunmamayı öğretir"
  dersinin vitrin hâli).
- **Tavan KALDIRILMADI.** Pencere daralınca daha seyrek işliyor ama tek günde
  68 ilan yazılan bir depoda 7 gün de 24'ü aşmaya yeter — 12 Eylül partisi tam
  böyleydi.
- **Test iddiası ARANAN SAYIYA bağlıydı ve bu bir kusurdu:** `shop.php`'nin
  kendi eşiğini taşımadığını doğrulayan iddia birebir `"-30 days"` arıyordu.
  Ölçüldü: rozeti yine elle `strtotime('-7 days')` okuyacak şekilde sabote
  edildiğinde **eski iddia yeşil kalıyordu** — yani pencere değiştiği anda,
  var olma sebebi olan kusurun yeni yazımını kaçıracaktı. İddia artık herhangi
  bir gün eşiği arıyor (`-N day` ve `N * 86400`) ve aynı sabotajda **kırmızı**.
  *Bir iddiayı bir sayıya bağlamak, o sayı değiştiği gün iddiayı sessizce
  emekliye ayırır.*
- Ayrıca **güne bağlı iki mutlak iddia** eklendi (6 gün → YENİ, 10 gün → değil):
  mekanizma iddiaları sabite göre `±1` yazıldığı için **her değerde yeşil**
  kalıyordu, yani operatörün söylediği sayıyı tutan tek şey sabitin adıydı.
- **Sonda rozeti de sayıyor artık** (`inspect-products.yml` → `shop_order=<bölme>`):
  pencere/tavan değeri, bölmede ve katalogda kaç ilanın rozet taşıdığı, ve en
  taze **ekleme günleri**. Sonuncusu şart: pencere boş çıktığında *"eşik inmedi"*
  ile *"bu hafta hiç ilan girmedi"* aynı çıktı olurdu. Sonda önce kum havuzunda
  koşturuldu (9 günlük ilan ve tarihsiz ilan doğru şekilde elendi).
- **ÇİZDİRİLDİ, kaynak okunmadı** (kum havuzu, `php -S`, sentetik üç ilan:
  1 / 3 / 12 günlük): sayfada **2 rozet**, 12 günlük ilanda **yok**, iki tazesi
  **en başta** ve en yeni önce; PHP uyarısı **0** (grep'in yakaladığı 8 satır
  çerez bandının `#cnotice` id'si). Aynı kum havuzunda pencere 30'a çekilince
  rozet **3** oluyor ve 12 günlük ilan da yanıyor — yani pencere gerçekten
  rozetin kendisini sürüyor, sabitin adını değil.
- **CANLI ÖLÇÜM (run `34763555452`, deploy `b8d9644f`):**
  `pencere 7 gun, tavan 24 | premium'da 70 ilan, katalogda 216`. En taze ekleme
  günleri **12 Eyl (69)** ve **10 Eyl (147)** pencere içinde, **3 Eyl (335
  ayakkabı)** dışında — 69 + 147 = **216**, yani sayım kendi içinde tutuyor.
- Test: `shop_order_test.php` 48 → **50 iddia**. Düşebildiği doğrulandı, her
  sabotajın gerçekten uygulandığı ayrıca yazdırılarak: sabit 30'a döndürülünce
  **2 kırmızı** (biri gün bazlı olan), rozet elle eşik okuyunca **2**.

**ANA SAYFADA "yeni gelenler" şeridi; Fred Perry ve Lacoste ön planda**
(operatör, 16 Eyl 2026: *"home ana sayfayi yenile yeni ürünler koy F.Perry
ürünlerini Polo ve Sweastshirt ön planda olsun Lacoste da"*).
- Ana sayfada kahraman film şeridi ve ayakkabı bandı dışında **hiçbir ürün
  bölümü yoktu** — sayfa "bu hafta ne geldi" sorusuna cevap vermiyordu.
- **Seçki `vestra_shop_order()` DEĞİL.** O fonksiyon bütün katalogu diziyor ve
  başında `pinned` + 24'lük YENİ bölmesi var; ilk 12'sini almak, operatörün
  adıyla istediği iki markayı şeridin dışında bırakırdı. İstenen bir **sıralama**
  değil, bir **seçki**: `vestra_home_new_picks()` (`inc/products.php`).
- **İki küme AYRI, bilerek:** önce ön alınan markalar (liste sırasında), sonra
  gerçekten yeni ilanlar (en yeni önce). Fred Perry'nin iki ilanı **aylardır**
  katalogda — şerit yalnız tazeliğe baksaydı ikisi de hiç çıkmazdı; testin
  pinlediği asıl olgu bu.
- **Eşleşme marka adı başına TAM:** yarın gelecek bir "Lacoste Kids" kendini öne
  çıkaramaz (mango/zara dersi). Alt dizeye gevşetilince **4 kırmızı**.
- **CANLI ÖLÇÜM İKİ GERÇEK KUSUR YAKALADI — yerel çizim yakalayamazdı** (yerel
  checkout yalnız 20 ilanlık demo tohumunu taşıyor, yani orada çizdirmek
  mekanizmayı kanıtlar, İÇERİĞİ kanıtlamaz):
  1. **Şerit 12/12 ön alınan markaydı** (Fred Perry 2 + Lacoste 13 = 15 aday) ve
     **gerçekten yeni hiçbir ilan giremiyordu** — yani talimatın yarısı
     ("yeni ürünler koy") sessizce uygulanmıyordu. Ön alınanların artık kendi
     tavanı var (`VESTRA_HOME_FEATURED_MAX`), toplam tavandan **ayrı bir sabit**:
     biri ızgaranın boyu, diğeri bu iki markanın **payı**. Yeni ilan yoksa boş
     slotlar yine ön alınanlarla doluyor — yarım dolu bir ızgara, dolu bir
     ızgaradan kötü görünür.
  2. **Satılmış bir Lacoste şeritteydi** (`lgp-lacoste-trim-tshirt`) ve şeridin
     hemen üstünde **"In stock now"** rozeti duruyor. Stok ilan eden bir bant,
     alınamayan bir ürünü gösteremez. Ölçüt `vestra_is_sold_out()` — alanın dolu
     olup olmadığına bakmak **boş dizgeyi SATILDI sayardı** (o alanın yazma
     dalının bu depoda kayıtlı tuzağı).
- **Fotoğraf süzgeci SAYFADA, seçici SAF:** dosya sistemi okuyan bir fonksiyon
  test edilemezdi. Sayfa fotoğrafı gerçekten diskte olmayan adayı eliyor.
- **FİYAT YOK:** ana sayfa girişsiz açılıyor ve toptan fiyat hesap kapısının
  arkasında (KURAL 19). Ayakkabı şeridi de fiyat basmıyor.
- **KENDİ CSS'i var:** üstteki `.shoe-*` bloğu yalnızca ayakkabı şeridi doluyken
  basılıyor, yani onu kullansaydım ayakkabı bölmesi boşaldığı gün bu bölüm
  stilsiz kalırdı — ayakkabı şeridinin kendi yorumunun yazdığı tuzak.
- **Tek yeni sözlük anahtarı** (`New arrivals`), 8 dosyaya birden (KURAL 10).
  Rozet ve düğme zaten var olan anahtarlardan.
- **`vestra_home_featured_brands()` TEK SATIRDA yazılmıyor:** bu depodaki testler
  fonksiyon gövdesini `^function …^}` ile ayıklıyor ve tek satırlık bir gövde
  kapanışını satır başında bırakmadığı için ayıklama **bir sonraki fonksiyonu da
  yutuyor** ("Cannot redeclare"). Biçim burada okunabilirlik tercihi değil,
  ölçüm aracıyla uyum.
- Sonda: `inspect-products.yml` → `home_picks=true` — sayfanın **çağırdığı**
  fonksiyonu **canlı** katalogla koşturuyor ve sayfanın kendi süzgecini birebir
  tekrarlıyor (ikinci bir seçim mantığı yazılmadı). Fonksiyon yoksa "deploy
  inmemiş" diyor; sıfır yazıp "şerit boş" demek iki ayrı durumu gizlerdi.
- **CANLI SONUÇ (16 Eyl 2026, deploy `de29b514`):** 12 kart —
  **1-2** Fred Perry M3600 + M7535, **3-6** dört Lacoste, **7-12** altı
  Dolce & Gabbana (12 Eylül partisi, `[yeni]`). Satılmış Lacoste şeritten düştü.
- Test: `tests/home_new_picks_test.php` (41 iddia, iki yön). Düşebildiği
  doğrulandı, her sabotajın **gerçekten uygulandığı ayrıca yazdırılarak**:
  ön marka bölümü kaldırılınca **6 kırmızı**, alt dize eşleşmesi **4**, ön marka
  tavanı kalkınca **2**, satılmış süzgeci kalkınca **3**.
- **Kendi ölçüm hatam:** "en yeni önce" iddiasını `new-1, new-2` diye yazdım ve
  kırmızı döndü — arada 2 günlük bir ilan vardı ve orada olması **doğruydu**.
  Kod haklı çıktı, iddia yanlıştı; iddia artık tam sıraya bakıyor.

**16–17 Eyl 2026 — TAM SİTE DENETİMİ** (operatör: *"siteyi komple kontrol et
daha fazla ve daha iyi konfor ve estetik olsun hatalari tespit et ve düzelt"*).
Ölçüm: 147 PHP dosyası lint temiz; 28 sayfa × 2 genişlik ekran görüntüsü
(`tests/render/shoot.js`) — yatay kaydırma 0, işaretlenen 5 taşma kasıtlı yatay
raylar (marka rayı, varyant tablosu, `dsscroll`, `reqfresh-rail`, `soon-strip`);
`auth.js` alıcı/satıcı panelleri çizildi; `rtl-check.js` 12 sayfa, RTL gerileme
0; 19 admin sekmesi yerelde çizilemedi (`admin_pass` yok, `inc/config.php`
gitignore'da); canlı `error_log`'un son 10 günü yalnız 16 Eyl kota kesintisinin
oturum uyarıları, 16 FATAL'in görünen ikisi Ağustos'tan (`vestra_doc_type_label`
redeclare 10 Ağu, `auth_accounts()` undefined 24 Ağu — ikisi de bugünkü kaynakta
yok). **Hiçbir PHP hatası bulunmadı; bulunan her kusur müşterinin OKUDUĞU
metindeydi:**
1. **Sözleşme 3b "HGB \u00a7377" basıyordu** — PHP tek tırnaklı dizgede
   `\u00a7` kaçış değil, dört harf; `§` yazıldı. Meta satırındaki **iç not**
   (*"Please have a US+EU lawyer review before relying on these documents"*)
   8 dile çevrilmiş, her ziyaretçiye basılıyordu — kaldırıldı. Tarih artık
   `vestra_legal_updated($lang)` (ISO, dil dosyası başına; metni değiştirince
   orayı da güncelle).
2. **Komisyon BEŞ sayfada ÜÇ farklı rakamdı:** ana sayfa *"from 2.8%, lower on
   higher plans"* (9 dilde), davet sayfası *"7 %"* + *"buyers pay 2 %"*, yardım
   *"Starter 3.5 / Pro 3.2 / Elite 2.8"*, üyelik *"3.5% … drops as you upgrade"*.
   Sepetin gerçekten tahsil ettiği oran tek: `VESTRA_COMMISSION_RATE` (%3,5,
   herkese — kademeli üyelik 22 Ağu 2026'da kalktı, commit `5dabe148`). Tek
   basıcı `vestra_commission_pct_label($lang)`; beş sayfa ondan okuyor.
   `products.php`'nin *"Pro/Elite get a lower rate"* yorumu da yalan söylüyordu.
3. **`/membership` kaldırılmış planları SATMAYA devam ediyordu** — €19,90/39,90/
   89,90 aylık + "€89,90 onboarding", `/stripe/checkout` formlarıyla; abonelik
   hiçbir şey vermiyordu (kota sınırsız, oran tek). Sayfa artık tek kart
   (*"Selling on VESTRA is free"*, oran sabitten); eski aboneye portal duruyor
   (KURAL 16). `seller.php`'deki "Membership / No plan yet / Compare plans" kartı
   yalnız eski abonelikte çiziliyor. Yardım SSS'sinin iki maddesi yeniden
   yazıldı. 14 eski sözlük anahtarı 8 dilden silindi, 15 yenisi eklendi.
4. **Davet sayfası "DE, EN, FR, IT, ES"** diye beş dil sayıyordu; site dokuz
   dilde. Sayı `count(vlang_list())`.
5. **Ana sayfa "Coming soon: Fred Perry" derken bir bant altında "New arrivals:
   Fred Perry"** — klasör markanın canlıya çıkışıyla (11 Eyl) silinmemişti.
   `vestra_soon_brands_filter()`: satışta olan markanın klasörü basılmaz (tam
   eşleşme, harf duyarsız; "Lacoste Kids" düşmez). Klasör silinmedi — karar
   canlı stoktan (KURAL 9'un "yakında" hâli).
6. "Verified seller" rozeti 10 → 11px (`shoot.js`'in <11px süzgeci).
7. **`diag-messages` errlog sondası kendi gürültüsünde boğuluyordu:** 800+ ayrı
   `session_start(): open(sess_<id>)` grubu (dosya adı anahtara giriyor) ilk-20'yi
   ve son-10-gün penceresini tek başına dolduruyordu — aynı günün gerçek bir
   fatal'i listeye giremezdi. Oturum uyarıları tek satıra toplanıyor.
- **KURAL 15 bir kez daha:** `help.php` ve `seller-invite.php` `products.php`'yi
  yüklemiyordu; yeni helper ilk yerel çizimde **fatal** verdi — ve `seller-invite`
  HTTP **200** döndürdü (başlıklar gitmişti, sayfa ortada kesildi). Yalnız durum
  koduna bakan bir ölçüm bunu yeşil görürdü. İkisi de artık açıkça require ediyor.
- **Kendi hatam:** falsifikasyon sırasında commit edilmemiş `products.php`'yi
  `git checkout --` ile "geri aldım" ve o dosyadaki üç düzeltmeyi sildim; test
  hemen fatal verdi, yeniden yazıldı. Sabotajı **dosya yedeğiyle** (`cp`) geri al,
  checkout ile değil.
- **Operatör kararı bekleyenler (dokunulmadı):** davet sayfasındaki **"500+
  verified buyers / 18 countries"** (10 Eyl ölçümü 109 hesap — pazarlama iddiası,
  kanıtı yok); SSS'de iki benzer kategori adı ("Returns & Claims" / "Disputes &
  Returns").
- Test: `tests/site_copy_consistency_test.php` (55 iddia; kaynak taraması
  **yorumları da** okur — kendi yorumlarımdaki "19,90", "Compare plans" ilk koşuda
  kırmızı döndü; yorumlar değişti, tarama gevşetilmedi). Düşebildiği doğrulandı:
  davet sayfasına "7 %" geri konunca **1 kırmızı**, yakında-süzgeci çağrısı
  silinince **1**, `\u00a7` geri gelince **3**, süzgeç herkesi geçirince **2**.

**17 Eyl 2026 — "500 satırlık" liste 81 ADRES çıktı; gönderim kayıtlı müşteriye
döndü (7 mektup).** Operatör `independent_stores_500.csv` yükleyip *"kampanya
gönder"*, ardından *"290 email gönder sonra gece 12'den sonra gönder kalanı"*
dedi. **Rakam dosyada yoktu ve bu, göndermeden ÖNCE ölçülerek görüldü.**

| | |
|---|---:|
| veri satırı | 500 |
| **benzersiz adres** | **81** |
| tekrar | 67 adres 6×, 14 adres 7× |
| KURAL 1 kod engeli | 20 |

- **Tekrarlar UYDURMA sonekle üretilmiş:** aynı adres `— Branch US 2`,
  `— Region EU 5`, `— Region EU 6` diye yeniden yazılmış. 8 Eylül'deki
  `global_200_multibrand_stores.csv`'nin (85 satırı `Concept Store Variant N`)
  birebir aynı imzası. *"500 satır" 500 aday demek değil; bu dosyada 6,2 kat
  şişmiş.*
- **Ülke sütunu kullanılamaz:** 81 adresin **81'i** hem `US` hem `EU` satırı
  taşıyor. KURAL 1e mektubun dilini ülkeden seçiyor, yani bu dosya gönderim için
  gereken tek alanı vermiyor.
- **Adres şekli KURAL 1b/1f imzası:** 52/81 `info@`, 80/81 host mağaza adından
  türetilebiliyor. **Kanıt hafızadan değil, kendi ölçümümüzden:**
  `info@hlorenzo.com` bu dosyada duruyor, oysa 7 Eylül'de o sitenin gerçek
  kutusunu **`customerservice@`** diye ölçmüştük.
- **Canlı kuru koşu iki adı ayrıca doğruladı:** `hlorenzo.com` ve `voostore.com`
  *"aynı firmadan başka bir kutu duyuruyu almış"*, `shinzo.paris` ise
  **blocklist** (`support@tawk.to`, KURAL 1h). Yani dosyanın "yeni" dediği
  adreslerin bir kısmı sistemde zaten işlenmiş.
- Kalan 61'in ağırlığı **sneaker/streetwear** (7 Eyl: *"bizim kanalımız değil"*)
  artı BSTN/Overkill/HHV/Cultizm/Jules B/Stag Provisions gibi zincir-kendi marka
  görünümlüler, ve dört kapanmış işletme (Totokaelo, Need Supply, Opening
  Ceremony ve satırın **kendi adının** *"Archive"* dediği Colette).
- **`leads.json`'a HİÇBİR ŞEY yazılmadı.** KURAL 1f'nin protokolü (adres değil
  **site linki** ver, `send=false` koş, taranan adları oku) uygulanmadı çünkü
  kuru koşu bile 81 kaydı havuza ekler ve içlerinde kapanmış dükkânlar var —
  `factoryoutlet.gr` bu depoda tam olarak böyle, adı düzeltilemeyen bir kayıt
  olarak duruyor.

**"290" hiçbir kaynaktan çıkmıyordu — üç ayrı kuru koşuyla ölçüldü:**

| Kaynak | Uygun |
|---|---:|
| Soğuk havuz (`min_brands=2`) | **4** |
| İkinci mektup / yeni koleksiyon | **3** (+3'ü 3 günlük yaş kuralında) |
| Kayıtlı alıcı — Angebot (`fp_offer_at`) | **9** |

Yani darboğaz **kota değil, gönderilecek adres** (kota o an 261 kalan). 4 Eylül
ve 8 Eylül'de kaydedilen *"havuz tükendi"* ölçümü hâlâ geçerli.

**GÖNDERİLDİ: 7 mektup, hata 0** (run `35255418050`). Operatör *"daha önce
onaylanmış email gitmemiş sistemdeki adreslere gönder"* deyince kip
`to_members=true|letter=fp_offer`'a döndü: alıcı, e-postası doğrulanmış,
`fp_offer_at` damgası **yok**. 9 adayın **9'unda da fiyat kapısı AÇIK**, yani
hepsi rakamlı mektup aldı. İkisi bilerek dışarıda bırakıldı ve operatöre
**yazıldı** (sessizce daraltma değil):
- **BRITISHSTYLE** — 16 Eylül'de operatörün kendi isteğiyle çıkarılmıştı ve
  11 Eylül'de tam bu iki ürünün fiyatlı resimli listesini **ayrıca** aldı;
  genel Angebot onun için üçüncü kopya olurdu.
- **"Verify Test Co"** — adı test hesabı olduğunu söylüyor.

**Bu 7 mektup 16 Eylül'ün 118'inden FARKLI rakam taşıyor ve doğrusu bu:** o gün
M3600 hâlâ 8'li karton / min 56 idi, aynı gün 10'lu karton / min 50 / 100 / 200
oldu. Mektup rakamı **canlı ilan kaydından** basıyor, metne gömmüyor — 16 Eylül
partisinin eski rakamla gitmesinin sebebi de bu (kayıt o an öyleydi), bugünkünün
doğru gitmesinin sebebi de.

**Sıradaki parti ölçüldü, sonra operatör *"2. email almayan … kampanya gönder"*
dedi: `letter=winter` → 72 aday, 9 atlandı, GÖNDERİLDİ 63** (50 + 13, iki koşu,
**sırayla** — paralel koşu `accounts.json`'ı ezer). Ayrı damga
(`newcoll_2627_at`) olduğu için küme Angebot'unkinden bambaşka.

**Atlanan 9 ve gerekçeleri — sessiz eleme değil, operatöre yazıldı:**
- **Bugün Angebot alan 7 hesap.** Dakikalar içinde ikinci bir kampanya mektubu,
  `newcoll_min_days=3` kuralının leadlerde önlediği şeyin hesap tarafındaki hâli
  olurdu; adres aynı, kural aynı, damga farklı olduğu için kod bunu **kendisi
  yakalamıyor**.
- **Firma adı `389h68843j6789)` olan hesap** (durum `pending`, kapı kapalı):
  mektup `$who = company ?: name` ile hitap ediyor, yani *"Hello 389h68843j6789)"*
  diye açılırdı — `factoryoutlet.gr`'nin *"Hello Αρχική"* vakasının aynısı.
  Süzgeç `pending_email`'i eliyor ama `pending`'i **elemiyor**.
- **"Verify Test Co"** — adı test hesabı olduğunu söylüyor.

**Atlama listesi göndermeden ÖNCE kuru koşuyla DOĞRULANDI** ve bu zorunluydu:
`skip` değerleri `[,\s]+` ile bölünüyor, yani "Verify Test" iki ayrı parçaya
(`verify`, `test`) ayrılıyor ve `test` başka bir firmayı da tutabilirdi.
Kaçıran bir atlama = birine 15 dakika içinde ikinci kampanya mektubu. Kuru koşu
`skip_accounts 9` dedi ve **dokuz adı tek tek bastı**; yanlış yakalanan yok.
Tek parçalı, ayırt edici token kullanıldı (`potoczek`, `weischenberg`,
`7d68b8778cd3884c`…). *Bir atlama listesini yazmak yetmiyor; kimi tuttuğunu
okumak gerekiyor.*

**BRITISHSTYLE bu partide BİLEREK VAR.** Angebot'tan çıkarılma gerekçesi
*o mektuba* özeldi (11 Eylül'de aynı iki ürünün fiyatlı listesini almıştı);
yeni koleksiyon duyurusu başka bir şey. *Bir istisna, verildiği mektubun
kapsamındadır — otomatik olarak bir sonrakine taşınmaz.*

**Lead tarafındaki "ikinci mektubu almamış" küme de gönderildi: 2** (Lulli/FR,
Sportina/SI). `factoryoutlet.gr` yine `skip_email_regex` ile dışarıda — kayıttaki
firma adı **"Αρχική"** ve `vestra_tpl_new_collection_shoes()` selamlamayı
`"Hello".($co!==''?" ".$co:'')` diye kuruyor. *Bu kayıt bu dosyada üçüncü kez
elle atlanıyor; panelde `rename_lead` eylemi hâlâ yok ve doğru çözüm o.*

**GÜNÜN TOPLAMI: 72 mektup, hata 0** (7 Angebot + 63 Winter + 2 lead).
Kota 261 → **190 kalan**. Yani operatörün *"gece 12'den sonra kalanı gönder"*
planına gerek kalmadı: **darboğaz kota değildi, gönderilecek adresti** — ve
bugün o adreslerin hepsi tüketildi.

**AYNI GÜN, İKİNCİ TUR: +8 mektup ve DÖRT KANALIN DA DİBİ GÖRÜLDÜ** (operatör,
19:2x: *"kampanyalara devam et 2. emailleri gönder"* + *"almayan kalmasin"*).
Dört kanal ayrı ayrı ölçüldü; "almayan" olduğu sanılan herkesin gerçekten ne
aldığı **tahmin edilmedi, damgadan okundu**:

| Kanal | Uygun | Gönderildi | Kalan ve sebebi |
|---|---:|---:|---|
| Lead — 2. mektup (`new_collection`) | 1 | **1** | 3'ü yaş kuralında (ilk mektup < 3 gün) |
| Üye — Winter (`letter=winter`) | 10 | **1** | 7'si bugün Angebot aldı, 2'si elenmeli |
| Üye — Angebot (`letter=fp_offer`) | 3 | 0 | BRITISHSTYLE (operatör kararı), test hesabı, `Mob` |
| Lead — soğuk havuz (`min_brands=1`) | 10 → **6** | **6** | 3 KURAL 1, 1 aynı firma |

- **`factoryoutlet.gr` DÖRDÜNCÜ kez atlanacaktı; onun yerine adı düzeltildi.**
  CLAUDE.md doğru çözümü zaten yazıyordu (*"panele küçük bir `rename_lead`
  eylemi — id ile bul, yalnız `company` alanını yaz, damgalara dokunma"*) ve
  bugün o kayıt ikinci mektup kanalındaki **tek** uygun adaydı, yani kuralı
  hatırlamak yetmiyordu: yazmak gerekiyordu. `Αρχική` → **Factory Outlet**,
  damgalar korundu (ilk mektup 21 Ağu 2026 — yaş kuralı çoktan geçmiş),
  Yunanca mektup `Καλημέρα Factory Outlet,` diye açıldı, gönderildi.
- **Üye tarafındaki 10'un 7'si bugün Angebot aldı** ve bu **fp_offer damgası
  sorularak** ölçüldü, hatırlanarak değil. Onlara saatler içinde ikinci bir
  kampanya mektubu, `newcoll_min_days=3`'ün lead tarafında önlediği şeyin ta
  kendisi olurdu — **bekletildiler, 20 Eylül'de uygun olurlar.** Onlar
  "almayan" değil: bugün mektup aldılar. **`Mob`** ise hiçbir kampanya mektubu
  almamış TEK hesaptı (ne Angebot ne Winter) ve mektubu aldı; hesap `pending`
  olduğu için rakamsız sürüm gitti. Elenen ikisi: firma adı `389h68843j6789)`
  olan hesap (mektup *"Hello 389h68843j6789)"* diye açardı — factoryoutlet
  vakasının hesap hâli) ve `Verify Test Co`.
- **SOĞUK HAVUZDA 10 ADAY VARDI VE KOD ONUNU DA GEÇİRİYORDU.** Elle okuma üç
  zincir çıkardı (Carl Scarpa / Kalogirou / Groupe Stalric — ayrıntı KURAL 1
  bölümünde) ve bunlar **bloklisteye eklendi**, yani eleme bir sonraki sefer
  koddan geliyor. Dördüncüsü kuralın değil **boşluğun** işi: `deadstock.ca`
  (Livestock) — `in**@` kutusu 31 Ağustos'ta mektup almış, `sh**@` aynı
  firmanın ikinci kutusu. **Soğuk yolda firma-bazlı tekilleştirme YOK**
  (`ayni firmadan baska bir kutu` kontrolü yalnız `new_collection` dalında,
  satır 798); 8 Eylül'de kaydedilen boşluk hâlâ açık ve elle atlandı.
  *Bir sonraki soğuk koşuda bu yine elle yapılmalı ya da kontrol o dala da
  taşınmalı.*
- **Ölçüm aracı üç kez yanlış yere baktı, üçü de yakalandı:**
  (1) `lead_rename` ilk canlı koşuda **255 ile düştü ve kütükte tek satır
  yoktu** — sunucunun CLI ini'si hataları yutuyor, yani bu dosyanın *"fatal'i
  çıktının tamamında ara"* kuralı bile yetmiyordu; adım artık
  `display_errors=stderr` ile koşuyor. Sebep `leads.php`'nin **kendi
  yorumunda** yazılıydı (*"Assumes the caller has already required
  inc/products.php"*) — KURAL 15'in bir vakası daha.
  (2) Falsifikasyonda `grep FAIL` kullandım, oysa `blocklist_test.php`'nin hata
  işareti **`HATA`**: iki sabotaj da UYGULANMIŞTI ve ben "0 kırmızı" okuyup
  iddialarımı ölçümsüz sanacaktım.
  (3) Bir sabotajın uygulandığını doğrulayan `grep -c`'im BRE yüzünden hiç
  eşleşmedi ve "uygulanmadı" dedirtti; python ile tekrarlanınca çıktı.
- **Kota darboğaz DEĞİL** (179 kalan, 60 ayrılmış). Dört kanalın toplamı
  **8 mektup**; günün toplamı **80**.

**17 Eyl 2026 — 100 satırlık JAPONYA listesi: gönderilebilir adres SIFIR;
80 alan adı KAYITLI DEĞİL** (operatör 100 satır yapıştırıp *"kampanya gönder
japonca"* dedi). **Gönderim YAPILMADI, `leads.json`'a hiçbir şey yazılmadı.**
KURAL 1b'nin imzası bu listede en açık hâliyle duruyor:

| | |
|---|---:|
| satır | 100 |
| KURAL 1 kod engeli | 19 |
| **alan adının NS kaydı YOK** (= kayıtlı değil) | **80** |
| alan adı canlı **ve** koddan geçen | 16 |
| elle okumadan sonra **kalan aday** | **5** |
| **gerçekten mektup gidecek** | **0** |

- **Kalıp, uydurmanın imzası:** 100 satırın **99'u** genel kutu
  (`info@` 27, `contact@` 24, `sales@` 13, `shop@` 12, `support@` 10…),
  **59'u** yer adıyla biten alan adı (`-tokyo.jp`, `-harajuku.com`). Tanınan
  15 markanın **14'ünün gerçek alan adı listedekinden BAŞKA**:
  `beams-japan.jp` ↔ gerçeği `beams.co.jp`, `uniqlo-global.jp` ↔ `uniqlo.com`,
  `commedesgarcons.co.jp` ↔ `comme-des-garcons.com`. Tek tutan `atmos-tokyo.com`
  ve o da zaten bloklistede. *Gerçek alan adı elde varken uydurmasını yazmak,
  listeyi hazırlayan aracın kaydı hiç görmediğini söyler.*
- **Ölçüm iki koşuda, kontrol grubuyla** (`diag-live` → `leads_status`, run
  `35268768994` + `35269189047`): ilk 50'de **34**, ikinci 50'de **44** NS YOK;
  kontrol grubu (`google.com`, `beams.co.jp`, `uniqlo.com`) sorunsuz çözülüyor —
  yani çözümleyici sağlam, ölü olan alan adları.
- **İlk ölçümde 3 alan adı HİÇ ÖLÇÜLMEDEN kalmıştı** ve bunu ancak ölçülenlerin
  listesini girdiyle **karşılaştırınca** gördüm (`commedesgarcons.co.jp`,
  `undercover-lab.com`, `gmail.com`). İlk ikisi de kayıtlı değil. *Bir listeyi
  "ölçtüm" demeden önce ölçülen kümeyi girdi kümesiyle karşılaştır — eksik
  ölçüm, ölçümsüzlükten kötü, çünkü kendini tam sanar.*
- **Canlı 16 alan adının hiçbiri gönderilebilir çıkmadı, ve gerekçeler ayrı
  ayrı ölçüldü:** 2'si **zaten mektup almış** (L'ŒIL DE TOKYO 31 Ağu,
  Mita Sneakers 3 Ağu), `dune-jp.net` havuzda **"N id – N id"** adıyla başka
  bir kutuyla duruyor (KURAL 1c) ve zaten bir **showroom** = marka dağıtım
  ajansı (`present-london.com` → *"Four Marketing"* vakasının aynısı),
  7'si markanın **kendi** dükkânı (YOKE, Rolling Cradle, Mishka, Carhartt WIP,
  Stussy…), 2'si **zincir** (Billy's ~çok şubeli sneaker, GU = Fast Retailing),
  ve **2'si dükkân bile değil**: `japan-zone.com` bir **turizm/kültür bilgi
  sitesi**, `store.babysallright@gmail.com` ise **Brooklyn'deki bir müzik
  mekânı**. Geriye 5 ad kalıyor (BoutiqueW, Japan Clothing, Salt and Pepper,
  Uptown Deluxe, Stay246) ve **onların adresi de güvenilmez**: 7 Eylül'de tam
  bu `info@`+alan adı kalıbında **12 alan adının 12'sinde de** liste adresi
  yanlış çıkmıştı. Doğru yol KURAL 1f — adres değil **site linki** ver.
- **Havuzda Japonya ayrıca ölçüldü, iki kanalda da 0:** soğuk havuz
  (`country_filter=Japan`, `min_brands=1`) → *"uygun secilen: 0"*, ikinci
  mektup kanalı → *"uygun secilen: 0"*. Yani bu talebi mevcut havuzdan da
  karşılamanın yolu yok; Japonya için gereken şey yeni liste değil **keşif**
  (`discover-city.yml`, verimi ~1 lead/şehir — 8 Eyl ölçümü).
- **Ölçüm aracının kendi gürültüsü ikinci kanalda yanıltıcıydı:** kuru koşunun
  *"ATLANDI …"* satırları Güney Afrika, İsrail, Romanya… yazıyor, oysa
  `country_filter` **Japonya**. Sebep yapısal: o `echo`'lar `$IS_NEWCOLL`
  dalında basılıyor, ülke süzgeci ise **paylaşılan yolda, 844. satırda**, yani
  satırlar süzgeçten ÖNCE yazılıyor. Seçim sayısı (0) süzgeçlenmiş sayıdır.
  *rtl-check ve probe_url'ün verdiği dersin üçüncüsü: aracın kendi gürültüsünü
  elemeden rapor okuma.*
- **`gmail.com`'u sondaya vermek hataydı** ve çıktıyı bozdu: havuzdaki her
  gmail'li lead eşleşti (*"zaten gonderildi: 347, yeni: 19"*) ve özet bu
  listeyle ilgisiz bir sayı bastı. Serbest posta sağlayıcısının alan adı bir
  firma kimliği değil (KURAL 1c'nin muafiyetinin aynı sebebi) — sondaya
  verilmemeli.

**16 Eyl 2026 — Fred Perry Angebot 118 kayıtlı alıcıya; fiyat listesi artık
BİRDEN FAZLA marka alıyor; ve cevap mektubu adımı ARGÜMAN SINIRINI aşmıştı.**

- **Angebot gönderildi: 50 + 50 + 18 = 118, hata 0.** `send-outreach` →
  `to_members=true`, `member_spec=letter=fp_offer|skip=849aaa5ab65a0b3f`
  (Baumgartner operatörün isteğiyle dışarıda). Kuru koşu: **fiyat kapısı AÇIK
  114** (rakamlı mektup) · **KAPALI 4** (rakamsız, "giriş yapınca sayfada") —
  yani "114" bir alıcı sayısı değil, **kapısı açık olanların** sayısıydı;
  gerçek alıcı 118. Diller en=53 fr=41 de=7 es=6 it=4 pt=3 ar=2 ru=2.
- **Dün yazdığım "aynı adres iki hesapta, iki mektup gidecek" iddiası İKİNCİ
  KEZ çürüdü:** kuru koşu `ayni adres 0` diyor. Koruma doğru olduğu için
  duruyor ama `send-outreach.yml`'deki yorumu *"kuru kosuda M&F Resell iki kez
  cikti"* diye bir ÖLÇÜM anlatıyor ve o ölçüm hiç olmadı. *Yorum da kayıttır;
  olmamış bir ölçümü anlatan yorum, yanlış bir teşhis kadar pahalıdır.*

- **`price_list` artık virgülle birden fazla marka alıyor** (İsrailli aday üç
  markanın tamamını istedi). Tek karar noktası `vestra_brand_filter_match()`
  (`inc/products.php`); **üç okuyan da** onu çağırıyor — PDF üreteci, Excel
  üreteci ve mektup dalı. Üçüne ayrı ayrı virgül ayrıştırması yazmak, PDF iki
  marka taşırken Excel'in tek marka taşıdığı bir zarf üretirdi; tam o ayrışma
  xlsx'in kategori süzgeci eksikken bir kez yaşandı.
  **Eşleşme ad başına TAM** (mango/zara): "Lacoste" isteği yarın gelecek bir
  "Lacoste Kids"i almamalı. **Kapsam adı süzgeç TOKEN'ından değil eşleşen MARKA
  ADLARINDAN** kuruluyor — ham token virgullü bir dizge ve olduğu gibi konu
  satırına girerdi (aynı hata kategori tarafında canlı bir koşuda görülmüştü).
  `?brand=` tek marka aldığı için çoklu süzgeçte mektup düz `/price-list`'e
  bağlanıyor: mektubun işaret ettiği yer ekteki listeden başkasını gösteremez.
  Test: `tests/brand_filter_test.php` (28 iddia; üç sabotajın her biri önce
  **gerçekten uygulandığı doğrulanıp** kırmızıya çevrildi).
- İki yan ekleme, ikisi de mektubun hesabı OLMAYAN bir adaya gitmesinden:
  `note=` (müşteriye özel paragraf, şablona **gömülü değil** — `listing_colours`
  ile aynı gerekçe) ve hesabı olmayana *"listeyi hesabınızda görürsünüz"*
  **denmemesi** (KURAL 19'dan beri `/price-list` girişsiz açılmıyor; cümle
  davete döndü).

- **ADIM BETİĞİ 128 KiB'i AŞINCA ADIM HİÇ ÇALIŞMIYOR.** `send-campaign-preview`
  → "Alıcı cevap mektubu" adımı bugün **132.127 bayta** çıktı ve şunu verdi:
  `An error occurred trying to start process '/usr/bin/docker' … Argument list
  too long`. **Girdiyle ilgisi yok:** `appleboy/ssh-action` `script`i TEK bir env
  değişkeni olarak geçiriyor ve Linux'ta bir argümanın tavanı
  **MAX_ARG_STRLEN = 131.072**. Yani `payment_notice`, `tracking_soon`,
  `listing_colours`, `price_list`, fatura taslakları — **cevap mektubu yolunun
  tamamı** herkes için kırıktı, ve kütükte "çok uzun" diye bir şey yazmıyordu.
  Sebep ancak betiği **tartarak** bulundu, kaynak okuyarak değil.
  - **Çözüm PARÇALAMA, kısaltma DEĞİL:** PHP iki adımda yazılıyor (`>` sonra
    `>>`), oluşan dosya **bayt bayt aynı** — doğrulandı (aynı sha256
    `deae0eab86ef25d9`, 131.887 bayt, `php -l` temiz). Tek kelime yorum
    silinmedi: bu depoda yorumlar kayıt ve "yer açmak için bilgi sil" yanlış
    takas olurdu.
  - Bekçisi `tests/workflow_arg_limit_test.php`: bütün workflow'lardaki her
    `script: |` bloğunu tartıyor (**103 betik**), eşik 128.000. Ölçünün kendisi
    çalışıyor mu diye de bakıyor — sıfır betik ölçüp yeşil kalan bir test hiç
    düşemeyen bir iddiadır. Bölme geri alınınca gerçekten kırmızı dönüyor.
    Şu an en büyük ikinci betik `diag-admin-discover.yml`, **93.600 bayt**.
  - `fetch-external-images.yml` aynı sınıfın başka bir sınırını (GitHub'ın 21000
    karakterlik ifade tavanı) zaten kaydediyordu; bu ikincisi.

- **`to=lead:<ad>` eklendi** (iki oturum aynı anda yazdı; çakışmada diğerininki
  alındı çünkü `inc/leads.php`'yi **açıkça** require ediyor — KURAL 15). Hesabı
  olmayan bir adaya cevap yazmanın tek yolu ham adresti; artık adres **lead
  kaydından** çözülüyor ve TAM 1 eşleşme yoksa iş duruyor.

- **AÇIK KALAN, OPERATÖR KARARI BEKLİYOR — fiyat listesi SATILMIŞ malı da
  fiyatlıyor.** `wholesale-list.php` / `wholesale-xlsx.php` / `price-list.php`
  hiçbiri `sold_out`'a **bakmıyor** (arandı: tek bir geçiş yok). Ölçüldü: 13
  Eylül'de *ausverkauft* yapılan iki Lacoste polosu — **DH1417** (Classic Fit
  Monogram Jacquard) ve **PH9863** (Regular Fit Logo Trim L.12.12) — 13 satırlık
  Lacoste dökümünde fiyatlarıyla duruyor, hiçbir uygunluk işareti yok. Bu
  **bugünün değişikliğinden bağımsız ve eski**: 11 Eylül'de Baumgartner'a giden
  listeler de aynı yoldan çıktı. Seçenek iki: (a) satılmışı dışarıda bırak,
  (b) listeye bir uygunluk sütunu ekle. İkisi de **her** fiyat listesini
  değiştirir, o yüzden kendiliğinden yapılmadı.
  **ÜÇÜNCÜ bir yol seçildi (operatör, 16 Eyl 2026: *"onlari yeniden stoga
  gelicek yap"*):** iki polo **stoğa alındı**, yani liste kendiliğinden doğru
  oldu. `product-fixes/lacoste-restock.json` + `set-product.yml`; kuru koşu
  2/2 eşleşti, uygulandı, zaman damgalı yedek alındı. Geri okuma (aşağıdaki
  yeni SATIS sütunu): ikisi de **satista**.
  **Kusurun kendisi DURUYOR** — üç üretici hâlâ `sold_out` okumuyor; yalnız
  bu iki ilan artık satılmış değil.

- **`sold_out`'un YAZMA dalı yoktu; `false` kayda `""` olarak iniyordu**
  (16 Eyl 2026, yukarıdaki stoğa alma sırasında bulundu). `set_product.php`
  alanı KABUL ediyor ve DOĞRULUYORDU (`is_bool` şartı), ama yazarken en
  aşağıdaki genel dala düşüyordu: `$new = (string)$v`, ve PHP'de
  `(string)false` = `""`. Bugün zararsız — her okuyan
  `vestra_is_sold_out()`'tan geçiyor ve boş dizgeyi `false` okuyor — ama
  alanı `isset()`/`array_key_exists()` ile soracak bir okuyan **satışta**
  olan ürünü "satıldı" sayardı. `group` dalı bu depoda **birebir aynı
  sebeple** yazılmıştı; orada bozulma ters yöne gidiyor
  (`!empty("false")` → TRUE) ve kapatılmak istenen havuzu **açık**
  bırakıyordu. Yeni dal gerçek bool yazıyor ve `$old`'u kapının ikinci bir
  kopyasından değil, altı satın alma yolunun çağırdığı **aynı**
  `vestra_is_sold_out()`'tan okuyor.
  **Kaynak taraması bunu göremezdi** (§5 alanın kabul edildiğini zaten
  tarıyordu ve yeşildi): `sold_out_test.php §7` betiği artık kum havuzunda
  **gerçekten koşturuyor** ve kaydı geri okuyor. Düşebildiği doğrulandı —
  dal devre dışı bırakılınca **5 kırmızı**, biri değeri doğrudan gösteriyor
  (*"bos dizge DEGIL: ''"*); sabotajın gerçekten uygulandığı `grep -c` ile
  ayrıca doğrulandı. Test 34 → 46 iddia.

- **Sonda GÖREMEDİĞİ alan hakkında yeşil veremez** (aynı iş).
  `inspect-products` → `price_list` dökümü `sold_out`'u **hiç yazmıyordu**,
  yani iki poloyu "listede fiyatıyla duruyor" diye bulan ölçüm, uygunluğu
  **ölçemiyordu**. `SATIS` sütunu eklendi (ölçüt yine
  `vestra_is_sold_out()` — "alan dolu mu" diye sormak boş dizgeyi SATILDI
  sayardı). Aynı ders bu depoda iki kez kayıtlı: `check_images` yalnız
  `approved` geziyordu, `raw_scan` bu yüzden var.
  **Sütun ilk koşuşunda ÜÇÜNCÜ bir vakayı buldu:** `LAC-LT-TEE-01`
  (Trim Cotton Jersey T-Shirt, 12 Eylül'de satıldı yapılmıştı) aynı 13
  satırlık dökümde **€29,00 / kademe €20,00** ile duruyor ve `*** SATILDI ***`
  diyor. Yani benim bir önceki ölçümüm **eksikti**: iki polo saydım, üçüncüsü
  gözümden kaçtı ve ancak sütun eklenince göründü. **Kendiliğinden stoğa
  ALINMADI** — operatörün cümlesi *"onları"* diyordu ve o iki poloyu
  adlandırmıştı; 12 Eylül'de bilerek kapatılmış ayrı bir ürünü aynı sepete
  koymak, operatörün vermediği bir kararı vermek olurdu (13 Eylül D&G
  notunun aynı dersi: *"bu cümlede 'tüm' YOK, TEK ürün adlandırılmış"*).
  **Operatör kararı bekliyor** — ve bu bir fiyat listesinde duruyor, yani
  o listeyi alan her aday adaya satamayacağımız bir ürünün fiyatını okuyor.
  **Operatör kararı VERİLDİ (16 Eyl 2026, soruldu):** *"Satılmış kalsın,
  mektubu yine de gönder"* — LAC-LT-TEE-01 satılmış kalıyor, İsrail mektubu
  o hâliyle gitti. Yani listede fiyatlı duran ama alınamayan bir satır,
  **bilinçli** olarak kabul edildi.

- **İSRAİL ADAYINA GÖNDERİLDİ** (16 Eyl 2026 15:52 UTC, run `35118187635`).
  `price_list` · `to=lead:Israel reseller enquiry` · Lacoste, Ralph Lauren &
  Fred Perry · **17 kalem / 3 marka** · ek PDF **80.064 bayt (17 gömülü
  fotoğraf)** + xlsx 7.607 · dil en · imza Marco Bellini · konu *"Lacoste,
  Ralph Lauren & Fred Perry — price list (17 articles)"*. Kütükteki
  `GONDERILDI` yalnızca **Brevo isteği kabul etti** demek (bu dosyanın kendi
  uyarısı: `delivered` bile posta kutusu kanıtı değil).

- **MEKTUBUN SPEC'İ KÜTÜKTEN GERİ OKUNAMIYOR — ve bu iki koşuya mal oldu.**
  `reply_spec` `appleboy/ssh-action`'a `envs:` ile geçiyor, yani **hiçbir
  yere yazılmıyor** (doğrusu bu: içinde müşteri adresi ve serbest metin var,
  kütük herkese açık). Ama sonuç şu: operatör *"gönder"* dediğinde, onayladığı
  önizlemeyi **birebir** yeniden kurmanın bir yolu yok.
  Ölçülebilen tek şey **parmak izi**: konu, kalem sayısı, ek boyutları, gövde
  karakter sayısı. İlk yeniden kurmam **679 karakter** verdi, oysa onaylanan
  gövde **1000**'di — yani `note` paragrafım 321 karakter eksikti ve bunu
  ancak sayı gösterdi. İkinci kurma **1010** verdi; geri kalan her parmak izi
  (konu, 17 kalem, PDF 80.064/17 foto, xlsx 7.607, hitap, imza) **birebir**
  tuttu. **Birebir aynı olduğu İDDİA EDİLMİYOR** — 10 karakter fark duruyor ve
  operatöre böyle söylendi; içerik aynı (teslim süresi + müşteriye özel asgari
  + fiyat şartları).
  *Ders: uzun ve elle yazılmış bir `note` taşıyan bir mektubu önizleyip
  operatöre gönderirken, spec metnini kendi notuna kaydet — kütük onu sana
  geri veremez.* Gövde karakter sayısı bunu yakalayan **tek** ölçü oldu;
  o satır olmasaydı 321 karakter eksik bir mektup "onaylanan mektup" diye
  gidecekti.

**KURAL 27 — AVRUPA DIŞI asgari sipariş tabanı KALDIRILDI; AFRİKA'ya %8
indirim DURUYOR** (operatör, **17 Eyl 2026**: *"US$5.000 Avrupa dışı taban,
bunu girmene gerek yok.... avrupa disindan isteyen normal en az alim ile
siparis verebilsin"*).

*Aşağıdaki 16 Eylül kaydı olduğu gibi bırakılmadı — birbirini tutmayan iki
kayıt hangisinin geçerli olduğunu okunamaz yapar. Gerekçe ve mekanizma
anlatımı duruyor çünkü kod duruyor; **geçerli olan değer bu maddedir.***

- **Avrupa dışı alıcı artık NORMAL asgarilerle sipariş veriyor:** ilanın kendi
  MOQ'su, paket adımı ve marka asgarisi (KURAL 21, €500) **aynen yerinde** —
  kalkan tek şey siparişin TOPLAM TUTAR tabanıydı. `order.php:115`'teki marka
  kapısı ve `order.php:134`'teki tutar kapısı **iki ayrı kapı**, yalnız ikincisi
  sustu.
- **MEKANİZMA SİLİNMEDİ, SABİT SIFIRLANDI** (`VESTRA_NONEU_MIN_ORDER_USD = 0.0`).
  Dropship ödemesinin dersinin aynısı (*"kaldır ancak yeniden başlamak için
  kurulu olsun"*): her okuyan zaten `> 0` soruyor, yani **tek satır** sepet
  uyarısını, pazar sayfasının olgu kartını ve `terms_reply` mektubunun
  paragrafını birlikte susturdu. **Rakamın tek yerde durmasının karşılığı tam
  olarak budur** (KURAL 6) — üç ayrı yeri elle düzeltmek gerekmedi ve biri
  unutulmuş olamaz.
- **Mektup SUSMUYOR, DOĞRUSUNU yazıyor.** `vestra_tpl_terms_reply` şablonunun
  `else` dalı zaten yazılıydı ve bugün ilk kez çalışıyor: *"There is no
  order-value minimum for your region. Each style has its own minimum quantity
  and is sold in fixed pack multiples…"* — operatörün *"normal en az alım"*
  cümlesinin birebir karşılığı. Çizdirilerek doğrulandı (Benin/ABD/Almanya).
- **YAN KAZANÇ, ve küçük değil: FX kesintisi artık sipariş DURDURMUYOR.**
  Taban açıkken bedeli açıkça yazılıydı (*"bir FX kesintisinde Avrupa dışı
  siparişler durur"*); `vestra_order_min_shortfall()` `min <= 0` görünce kuru
  **okumadan önce** dönüyor, yani o bedel de bugün ödenmiyor. Test bunu
  **davranışla değil kablolamayla** tutuyor: bu ortamda kur zaten okunamıyor,
  yani "boş dizi döndü" sıranın doğru olduğunu **kanıtlamaz** — iddia, gövdede
  `if ($min <= 0) return [];`in `vestra_fx(` çağrısından **önce** geldiğini
  ölçüyor.
- **Sepet bandı sabitle kapatıldı.** Kapı sustuğu için o adres organik olarak
  oluşmuyor, ama eski bir yer imi ya da elle yazılmış bir `/cart?err=ordermin`
  **sıfır dolarlık bir taban** duyururdu: var olmayan bir kuralı, üstelik saçma
  bir rakamla. *Yorumun içine örnek olarak yazdığım rakamı **testin kendi
  taraması yakaladı*** (tarama yorumları da okuyor — bu deponun kayıtlı dersi).
- **Sözlük anahtarları 9 dilde YERİNDE bırakıldı** ve bu bilinçli: mekanizma
  duruyor, taban bir gün geri açılabilir, ve 18 anahtarı silip geri eklemek
  çeviri kaybı riskinden başka bir şey getirmezdi.
- Test: `order_min_region_test.php` 177 → **204 iddia** (davranış bilerek
  değişti, o yüzden testi de düzeltildi — bu deponun kendi kuralı). Dört
  sabotajın her biri önce **gerçekten uygulandığı `grep -c` ile doğrulanarak**
  kırmızıya çevrildi: sabit 5.000'e döndürülünce **19 + 2 + 1** (üç dosyada),
  sepet muhafazası kalkınca **1**, kur okuması muhafazadan öne alınınca **1**,
  `market.php`'nin olgu bloğu silinince **1**.
- **İki yön korundu:** taban kalktı ama **bölgesel indirim DURUYOR** (Benin hâlâ
  %8, ABD hâlâ indirimsiz) — iki kapı ayrı ve bunu tutan iddialar bilerek
  yazıldı; tabanı kaldıran bir değişiklik indirimi de sessizce götürebilirdi.
- **CANLI ÖLÇÜM (17 Eyl 2026, deploy `024c362a`, `seo-check` run 34).**
  Sunucunun kendi kodundan, on pazarın **onunda da** `asgari=-`; aynı satırda
  indirimler yerinde (`australia AUD/10% · japan 10% · brazil 10% ·
  south-america 10% · singapore 10%`, ABD/BAE/Katar/İsrail/G.Kore `-`),
  kapılar yerinde, `hreflang 81 etiket / 64 ülke`, `AB27+EFTA+GB 32/32`.
  **Bu satır kapının kendisini de kanıtlıyor:** `min_order_usd` sipariş
  kapısıyla **aynı sabitten** türüyor, yani "asgari=-" sunucudaki sabitin
  0.0 olduğunu doğrudan gösteriyor.
- **Deploy İKİ KEZ KOŞTU ve ilki `dial tcp: i/o timeout` ile düştü** — SSH hiç
  bağlanmadı, yani o koşu sunucu hakkında **hiçbir şey söylemiyor** (bu
  dosyanın kayıtlı dersi). Daha kötüsü: aynı dakikalarda başka bir oturumun
  deploy'u **başarıyla** koştu ve benim değişikliğim **olmayan** bir sha'yı
  sunucuya yazdı. Yeniden koşturulup doğrulandı. *İki deploy aynı anda
  koşuyorsa, "sonuncusu kazanır" — ve sonuncu senin koşun olmayabilir.*
- **Kendi hatam, kayda geçsin:** ikinci dala push'u
  `if git push ... | tail -2` ile sardım; kabuk **boru hattının SON
  komutunun** (yani `tail`'in) çıkışına bakar, o da hep başarılı. Push
  `non-fast-forward` ile reddedilmişken ekrana **"PUSH OK"** yazdırdım.
  Yakalayan şey çıktının kendisini okumak oldu (`hint: 'git pull' before
  pushing again`). *Ölçüm aracının kendi gürültüsü, bu dosyanın üç kez
  kaydettiği sınıf — burada aracın kendisi ben oldum.*

**ESKİ KAYIT (16 Eyl 2026) — taban KONDUĞU günün gerekçesi; değeri artık
geçerli değil, mekanizması geçerli:**
(operatör, 16 Eyl 2026, Benin'den gelen ilk kurumsal soru vesilesiyle:
*"Avrupa disina en az alim 10 Bin USD yaz. Ayrica Afrika bölgesine toplam
Katalogtan yüzde 8 Inidirim yapilacagini belirt"* → aynı gün, birkaç dakika
sonra: *"alimi 5 bin usd yap"*).

- **İki AYRI kapı, iki ayrı soru.** Marka asgarisi (KURAL 21, €500) sepetteki
  bir MARKANIN toplamına bakıyor; bu, siparişin TAMAMINA. İkisi birlikte
  işliyor ve biri geçip diğerine takılan bir sepet mümkün — doğru olan da bu.
- **Rakam tek sabitte:** `VESTRA_NONEU_MIN_ORDER_USD`. Aynı gün 10.000 → 5.000
  oldu, ertesi gün 5.000 → 0.0 (kapalı); üç değişiklik de **tek satırdı**; sepet uyarısı, sunucu kapısı ve testler
  hepsi oradan okuyor. Escrow tavanının beş gün metinde 3.000, kodda 3.500
  kalmasının (KURAL 6) sebebi tam tersiydi.
- **Kapı SUNUCUDA** (`order.php`), sepetteki uyarı yalnızca görünüm. Ölçüm
  istekten değil **yeniden fiyatlanmış satırlardan**; bölgesel indirim
  `vestra_unit_price()` içinde zaten uygulanmış, yani taban alıcının
  GERÇEKTEN ödeyeceği tutardan ölçülüyor — indirimsiz fiyattan ölçmek, %8
  indirim alan bir alıcıdan fiilen 5.435 USD istemek olurdu.
- **BİRİM USD ve bu, KURAL 21'de kayıtlı kararın TERSİ.** Orada operatör, marka
  asgarisini USD yapmanın "gerçek minimumun kurla dalgalanması" demek olduğunu
  duyunca EUR'yu seçmişti. Burada USD'yi açıkça istedi, o yüzden bedeli de
  yazılı: katalog EUR, eşik USD, karşılaştırma bir KUR gerektiriyor.
  **Kur yoksa sipariş GEÇMEZ** (`ordermin_fx`; KURAL 17'nin dropship
  tahsilatındaki kararı) — uydurma bir kurla 5.000 USD'yi ölçmek, eşiği
  sessizce başka bir sayıya çevirmek olurdu. **Bedeli açık: bir FX
  kesintisinde Avrupa dışı siparişler durur.**
- **"Avrupa" = coğrafi Avrupa, AB gümrük alanı DEĞİL**
  (`vestra_europe_codes()`: AB 27 + EFTA + Birleşik Krallık + Balkanlar/mikro
  devletler). AB ile sınırlasaydık GB/CH/NO alıcıları bugüne kadar
  uygulanmayan bir şarta düşerdi.
- **ÖLÇÜM TASARIMI DEĞİŞTİRDİ:** ilk yazımda ülkeyi `vestra_cc_of_country()`
  çözüyordu — o tablo KÜRATÖRLÜ ve kısmi (kayıt IP'si ile beyanı
  karşılaştırmak için yazılmış) — ve **"Benin" ile "Brazil" AVRUPA çıkıyordu**,
  yani tabanın var olma sebebi olan iki ülke tam da kapıda muaftı. Test artık
  **pozitif**: Avrupa olarak TANINAN muaf (`vestra_europe_names()`, 45 ülke,
  yerel yazımlarla), tanınmayan değil. Yön bilerek böyle — ters yönde her
  Amerikalı ve Asyalı alıcı şartı **sessizce** atlardı; fazla sorulan soru
  görünür, eksik tahsilat görünmez.
- **`order.php`'ye `$user` yazmıştım, o dosyada öyle bir değişken YOK.** PHP'de
  tanımsız = null, null = "hesapsız", yani kapı HERKESE açılırdı. Kaynağı
  okuyarak değil, **koşturarak** çıktı (dosyanın kendi adı `$me`).
- **AFRİKA %8 — oran artık ÜLKE BAŞINA.** `VESTRA_REGION_DISCOUNT_PCT` tek
  sabitti; Afrika'yı o sabitle eklemek Güney Amerika'yı da %8'e çekerdi.
  Kapsam ve oran tek tabloda (`vestra_region_discount_rates()`): 18 ülke %10
  (Güney Amerika + JP/AU/SG/HK + CZ/PL), **54 Afrika ülkesi %8**. Toplam 72.
  Yazımlar EN + FR + PT/AR (Benin/Senegal frankofon, Angola lusofon, Kuzey
  Afrika arapça) — tek dilli bir liste gerçek müşteriyi tam da indirimi hak
  ettiği anda kapsam dışı bırakırdı.
- **Eşleşme TAM, alt dize değil:** Niger/Nigeria, Guinea/Equatorial Guinea/
  Guinea-Bissau, Congo/DR Congo, Sudan/South Sudan ayrı ülkeler ve alt dize
  eşleşmesi dördünü de karıştırırdı (mango/zara dersinin coğrafya hâli).
  Réunion/Mayotte/Kanarya **bilerek kapsam dışı**: coğrafyaları Afrika ama AB
  gümrük alanı ve euro — Fransız Guyanası'nın Güney Amerika listesinde
  olmamasıyla aynı gerekçe.
- **Mektup: `reply_letter=terms_reply`** (`vestra_tpl_terms_reply`). İlk taslak
  rakamları düz metne yazmıştı ve operatör **bir saat sonra** asgariyi
  değiştirdi — gömülü olsaydı mektup o anda sessizce yalan söylemeye
  başlardı. Artık asgari sabitten, indirim ülkenin kendi oranından, belgeler
  `auth_required_doc_types()`'tan. **Avrupalı alıcıya ne taban ne indirim
  cümlesi yazılıyor** (ikisi de onun için doğru değil, rozet satırları da boş
  çıkıyor) ve **gönderim cümlesi verilmezse mektup o konuda SUSUYOR** —
  taşıyıcı ve süre bir olgu, KURAL 3'ün mektup hâli.
  Konu satırında **ülke ADI**: ilk yazımda ad yine `vestra_country_of_cc()`
  üzerinden aranıyordu ve hem "BJ" hem "Benin" konuya *"for BJ"* diye
  düşüyordu — **aynı oturumda kısmi tablonun üçüncü vakası**.
- **Canlı ölçüm (16 Eyl 2026, deploy `babd378d`):** Benin hesabı, 100 EUR
  sepet → taban 5.000,00 USD, sepet 107,57 USD, **eksik 4.892,43 USD**
  (kur 1,0757); eşiğin hemen üstü GEÇİYOR; Almanya hesabı her tutarda
  GEÇİYOR. Mektup testi operatör kutusuna gitti (run 218): `indirim 8% ·
  asgari US$5,000 · konu "wholesale terms for Benin"`, müşteriye **gitmedi**.
- Test: `tests/order_min_region_test.php` (177 iddia, iki yön). Düşebildiği
  doğrulandı, her sabotajın **gerçekten uygulandığı ayrıca yazdırılarak**:
  Afrika %10'a katlanınca **14 kırmızı**, Avrupa testi kısmi tabloya dönünce
  **9**, `order.php` kapısı silinince **1**, rakam metne gömülünce **3**,
  Avrupa'ya taban cümlesi yazılınca **1**, sabit 10.000'e döndürülünce **1**.
  *İlk sabotaj denemem sessizce HİÇ UYGULANMAMIŞTI ve geçen bir testi kanıt
  diye sunacaktım — `grep -c` ile doğrulanınca çıktı.*
- **Mekanizma iddiaları sabite GÖRE yazılı** (eşik ne olursa olsun tutmalılar),
  o yüzden **5.000'in kendisini pinleyen ayrı bir iddia** var: yoksa operatörün
  söylediği sayıyı tutan tek şey sabitin adı olurdu (13 Eyl'de NEW rozetinin
  penceresi tam bu sebeple ayrıca pinlenmişti).
- **Sözlük DOKUZ dil, sekiz değil:** `ja`'yı atlamıştım; yakalayan şey bu test
  değil, `seo_landing_test`'in `de.php`'ye karşı eksiksizlik taraması oldu.
- Sonda: `inspect-products.yml` → `moq_scan=true` — MOQ/paket adımı dağılımı,
  N adetle kaç marka/kategoriye ulaşıldığı, en düşük MOQ'lu ilanlar.
  **FİYAT BASMAZ** (kâr oranı bu dosyada yazılı, fiyat maliyeti ele verir) ve
  soru zaten adet sorusu.

**KURAL 28 — Hesap açma PANELDE; elle anlaşılan fiyat ve feragatler kayıtta**
(operatör, 17 Eyl 2026: *"Francisco adına bir buyer hesabı aç ve 23 adet
r.lauren polo faturası kes 20 eur dan shipping cost 25 eur. satıcı les garage
de paris"*).

- **ÖLÇÜM ÜÇ ŞEYİ BİRDEN ÇÜRÜTTÜ ve hiçbiri talimattan okunamıyordu:**
  1. **"Francisco" ile eşleşen hesap Francisco DEĞİL.** `diag-messages` →
     `billing_for`: `C&F Multimarcas`, ad **"Carmen camacho"**, adres *La
     algaida calle central n30*, **VAT yok**. Eşleşme yalnız **e-postadan**
     geliyordu. Faturadaki kimlik ise *Francisco Javier Nicolas Macanas /
     ES48500442C / Altaona 15, Beniaján*. O hesaba kesmek, vergi belgesine
     **başka bir tüzel kişiyi** yazmak olurdu. *Ad bir kimlik değildir —
     `inspect-products`'ta iki "Wings T-Shirt" dersinin hesap hâli.*
  2. **Ralph Lauren'da TEK polo var:** `rl-csf-polo-white` (SKU
     `710548797001`), kademeler **26,90** (80+) / **25,00** (160+),
     **MOQ 80**, **paket adımı 8**, renkler **White · Dark Green · Fuchsia ·
     Yellow · Orange · Pink**, `min_colors=4`. Yani 23 adet ve €20, ilanla
     **üç ayrı noktada** çelişiyor.
  3. **Operatörün verdiği renkler (Navy, Black) bu ilanda YOK** — ikisi de
     aynı markanın **T-SHIRT** ilanının (`rl-csf-tee-navy`) renkleri.
     Sessizce en yakınını seçmek, gönderilemeyecek bir şey satmak olurdu.

- **ELLE ANLAŞILAN BİRİM FİYAT (4. alan: `SKU:RENK:ADET:20`).** Toptan kipi
  fiyatı `vestra_unit_price()`'tan okuyor ve €20 **hiçbir kademede yok**, yani
  anlaşılan satışı kayda geçirmenin yolu yoktu. Fiyatı kodun okuması *kimsenin
  elle bir rakam UYDURMAMASI* içindi; operatörün müşteriyle anlaştığı rakam
  ise uydurma değil **kayıt**. Sessizce geçmiyor:
  - **YÖN KONTROLÜ, ve iki yön aynı şey değil.** Anlaşılan **<** katalog =
    operatörün iskontosu, gerekçe kayda giriyor. Anlaşılan **>** katalog =
    alıcının **sayfada gördüğünden pahalı** bir fatura; bu karar değil kusur
    (`price_audit`'in "alıcı aleyhine" sınıfı) ve iş **DURUYOR**.
    Canlı ölçüldü: €20 geçti, **€30 reddedildi** (`sorunlu: 1`, yazılmadı).
  - **NOT DA DEĞİŞİYOR:** anlaşılan birimle kesilen satışa *"wholesale tier
    pricing"* yazmak, kademeyi açan kişinin o rakamı hiçbir yerde bulamaması
    demekti — `desc`/`sizes` çelişkisinin aynısı. Özet satırındaki etiket de
    düzeltildi (*rakam doğru, etiket yalan* — yanlış rakam sorgulanır, yanlış
    etikete inanılır).
  - **Dropship kipinde REDDEDİLİYOR**: orada birim alıcının hesabından türüyor
    (abonelik) ve elle bir rakam o kapıyı sessizce atlatırdı.

- **PAKET ADIMI FERAGATİ (`waive_pack=1`) — ve bu, kendi yazdığım yorumun bir
  kısmını GERİ ALIYOR.** Adım kontrolü **sepet** için yazıldı ve orada haklı:
  sepet adedi adımın katına **yuvarlıyor**, yani 23 yazan bir ilan kasada 24
  demek olurdu. Ama **elle kurulan sipariş sepetten hiç geçmiyor**: operatör
  23 yazıyor, fatura 23 diyor, alıcı 23'ün parasını ödüyor — yuvarlayan taraf
  yok, dolayısıyla yalan söyleyen taraf da yok. Geriye **tek gerçek sonuç**
  kalıyor ve gizlenmiyor: **beden dökümü yazılamıyor** (23, ilanın 8'lik
  serisine bölünmüyor) ve satır bunu kendi notunda söylüyor. *Operatör bu
  sonucu GÖRDÜKTEN sonra 23'te ısrar etti; tekrarlanan talimat karardır.*

- **HESAP AÇMA PANELE EKLENDİ, çünkü başka yeri YOK ve iş akışı YANLIŞ yer.**
  `Admin ▸ Users ▸ ➕ New buyer account (manual)`. Panelde ekleme eylemi
  **bugüne kadar hiç yoktu** (arandı: ekle, içe aktar, durum değiştir, sil var
  — **açma yok**), ve iş akışından açmak müşterinin e-postasını **herkese açık
  koşu girdisine** yazmak demekti. Bu depo `only_emails` girdisini **tam o
  sebeple** bir kez kaldırıp `only_accounts`'a çevirdi; müşteriye ait veri
  panelde durur (KURAL 2d'nin *"müşterinin belgesi GitHub'dan geçmez"*
  kuralının aynı ailesi). *Operatörün adresi bana sohbette vermiş olması,
  onu herkese açık bir kütüğe yazma izni değil.*
  - **`doc_requests` TEK KAYNAKTAN:** `auth_required_doc_types()` +
    `auth_doc_request_row()`. `create_seller` / `sync_lesgarage` /
    `create_tyrex_migrate` **üçü de** `'doc_requests'=>[]` yazmıştı ve sonucu
    KURAL 2'de kayıtlı: satır yoksa yükleme düğmesi de yok, müşterinin belgeyi
    verecek **hiçbir yolu** kalmıyor.
  - **KURAL 2g (Türkiye) burada da çalışıyor:** kapatan bir kural her yolda
    çalışmalı, yoksa panel self-servis kaydın reddettiği hesabı açan bir **arka
    kapı** olurdu. (`create_seller`'ın bu kontrolü taşımaması operatörün
    bilinçli, tek hesaplık istisnasıydı; genel bir form aynı muafiyeti hak
    etmiyor.) Ters yön testli: **Turkmenistan geçiyor.**
  - Kapıyı **ne açtığı** kayıtta (`kyb_auto='operator:panel'`) — promo
    hesabında bu alan yoktu ve *"bu hesap neden açık?"* sorusunun cevabı
    hiçbir yerde durmuyordu (KURAL 2h).
  - **Şifre rastgele ve hiçbir yere yazılmıyor**; müşteri "forgot password"
    ile kendi belirliyor. **Müşteriye hiçbir şey gitmiyor** (KURAL 18): hoş
    geldin mektubu da doğrulama linki de yok. Yazma **geri okunuyor**.

- **Kendi hatam, bugün İKİNCİ kez:** formda `csrf_field()` yazdım, doğrusu
  **`csrfField()`**. Sabah da olmayan bir `vestra_order_status()` çağırmıştım.
  **İkisini de `php -l` GEÇİRDİ** — ikisi de çalışma zamanı hatası. Birincisini
  fonksiyon taraması, ikincisini **formu çizdirmek** yakaladı. *Kaynak okumak
  ölçüm değil; `php -l` de ölçüm değil.*
- Test: `tests/create_buyer_test.php` (27 iddia). Kum havuzunda `admin.php`'yi
  **gerçekten POST ile koşturuyor** ve yazılan kaydı geri okuyor — kaynak
  taraması bu işi ölçemezdi. Düşebildiği doğrulandı, her sabotajın **gerçekten
  uygulandığı** satır sayımıyla yazdırılarak: `doc_requests` döngüsü silinince
  **5 kırmızı**, aynı e-posta kontrolü kalkınca **6**, Türkiye kontrolü
  kalkınca **5**.
- **Canlı zincir (deploy `e5a6b084`):** 23 × €20 = **€460** + €25 kargo =
  **€485**, `sorunlu: 0`, üç feragat de satırda yazılı, beden dökümü **bilerek
  boş**.

**KURAL 28 — TAMAMLANDI (17 Eyl 2026): hesap açıldı, sipariş yazıldı, fatura
kesildi.** Operatör: *"verdigim bilgiler ile francisco ya hesap ac"* +
*"faturasini yap"*.

- Hesap **`7d68b8778cd3884c`** (buyer/active, kyb `approved`,
  `kyb_auto=operator:workflow`, kapı AÇIK, `trade_licence` satırı açıldı,
  dil `es`, ülke Spain, VAT kayıtlı). **Müşteriye hiçbir şey gönderilmedi.**
- Sipariş **`VES-F23A9727`**: 23 × €20,00 = €460 + €25 kargo = **€485,00**,
  üç feragat de satırın notunda. Fatura **INV-2026-1003**, kesen taraf
  **GARAGE LE PARIS** (operatör seçimi), tek dilim = **TEK BELGE**,
  EUR ödeme kutusu **ÇIKIYOR (4 satır)** — platformun aksine bu künyede IBAN
  var. Alıcıya **e-posta gitmedi**.
- **`auth_create_buyer()` TEK YAZICI oldu**: panel formu ve yeni
  `seller-products.yml` → `admin_mode=create_buyer` ikisi de onu çağırıyor.
- **Müşterinin e-postası AÇIK GİRDİYE YAZILMADI.** Kural ("adresi kayıttan
  çözdür") burada uygulanamıyor — hesap henüz yok, çözülecek kayıt yok. Çözüm
  `apply` modunun **şifreli zarfı**: `KEY.IV.GÖVDE`, özel anahtar
  `~/.vestra_inbox_key.pem`, sunucudan hiç çıkmıyor. Girdi herkese açık ama
  **okunamaz**; çıktı da maskeli (`b***@gmail.com`). *Bir kuralın çaresi
  uygulanamıyorsa kuralı bırakma — aynı amaca varan başka bir çare ara.*
- **`order_draft`/`order_write` artık hesap ID'siyle TAM eşleşiyor.** Ad/firma
  parçası belirsizdi: `francisco` araması **başka** bir hesabı (C&F Multimarcas)
  yalnız e-postasından yakalıyordu, ve tek ayırt edici parça kişinin **soyadı**
  olurdu — o da kalıcı, herkese açık bir girdi.

**İKİ KUSUR, ikisi de ÇİZDİREREK bulundu (`php -l` ikisini de geçirdi):**
1. Yeni adımda düz `require inc/security.php` dosyayı **ikinci kez** yükleyip
   `Cannot redeclare _vsec_dir()` ile **öldürüyordu** (auth.php onu zaten
   satır 17'de yüklüyor).
2. **`auth_create_buyer()` koşulsuz `status='active'` yazıyordu** ve
   `auth_user_approved()` bir **VEYA** (`status==='active'` YA DA
   `kyb_status==='approved'`) — yani operatör kapı kutucuğunu **işaretlemese
   bile fiyat kapısı AÇILIYORDU**. Kutucuk yalan söylüyordu. `auth_register()`
   aynı yerde `pending` yazıyor; artık ikisi aynı. **Eski `kyb_status=pending`
   iddiası bu hatayı YEŞİL geçiyordu** — ölçüt artık kapının kendisi
   (`auth_prices_unlocked`), alanlardan biri değil.
- Test 27 → **37 iddia**; dört sabotajın her biri önce **gerçekten uygulandığı**
  doğrulanıp kırmızıya çevrildi (5 / 6 / 20 / 2).

**KURAL 28 — Siparişin RENGİ sonradan düzeltilebiliyor** (operatör, aynı gün:
*"renkleri faturada siyah ve navy olarak degistir"*).
- **Renk siparişin notlarında duruyor ve oraya yazan TEK yol kasaydı** — yani
  sipariş yazıldıktan sonra rengi düzeltmenin **hiçbir yolu yoktu**. Navlun
  (KURAL 5k) ve teslimat adresi (KURAL 5l) aynı boşluğu daha önce kapattı;
  renk açık kalmıştı. Tek yazıcı `vestra_order_set_colours()`, kardeşlerinin
  deseniyle: notların **gerisine dokunmaz** (okuyucunun **kendi**
  ayrıştırıcısıyla söker — ikinci bir kalıp yazmak, bu depoda renklerin
  yıllarca hiç okunmamasına yol açan hatanın ta kendisi), yedekler, atomik
  takas eder ve **faturanın gördüğünü** geri okur.
- **Faturalı siparişte varsayılan RED**, `|allow_invoiced=1` ile açık opt-in,
  ve dönüşte `must_redraft`: KURAL 5f'in çaresi zaten *"aynı numarayla yeniden
  çizim"* ve rengi düzeltmeden yeniden çizmenin anlamı yok. Belgeyi bu adım
  **çizmiyor** — kesim yolu tek yerde (`admin_mode=issue` + `issue_redraft`).
- **İlanın renk listesi DOĞRULANMIYOR ama sessiz de kalmıyor** (`not_listed`).
  Ölçüldü: ilan **White / Dark Green / Fuchsia / Yellow / Orange / Pink**
  diyor; **Black ve Navy o ilanda YOK** (ikisi kardeş ilanın, `rl-csf-tee-navy`
  tişörtünün renkleri). Operatör müşterinin **gerçekten aldığı** malı
  söylüyor ve katalog kaydı eksik olabilir — belgeyi kataloğa uydurmak için
  satılan malı yanlış yazmak KURAL 3'ün tersi olurdu. Uyarı basıldı, karar
  operatörün.
- Canlı: renk `White` → **`Black, Navy`**, `vestra_order_lines()`'tan geri
  okundu, **INV-2026-1003 aynı numarayla yeniden çizildi**, kesilmiş fatura
  hâlâ **1** (ikinci numara yanmadı).
- Test yazarken **iki gerçek tutarsızlık** çıktı (ikisi de üretimde zararsız,
  ama ölçümü yalanlıyordu): `vestra_data_dir()` sabitti → `VESTRA_ACCOUNTS` /
  `VESTRA_MESSAGES` ile aynı `defined()` koruması (korumasızken test **gerçek
  kataloğa** yazacaktı); ve **`vestra_invoice_dir()` `vestra_data_dir()`'i HİÇ
  çağırmıyordu**, yani veri dizini yönlendirildiğinde *"faturası var mı"*
  kontrolü **yanlış klasöre** bakıyordu.
- Test: `tests/order_colours_test.php` (24 iddia, kum havuzunda gerçekten
  yazıyor). İki sabotaj, uygulandıkları sayımla doğrulanarak: faturalı
  muhafaza kalkınca **3 kırmızı**, ayrıştırıcı yerine ikinci bir kalıp
  yazılınca **4**.

**OPERATÖR KARARI BEKLEYEN İKİ ŞEY (bu siparişten çıktı):**
1. **Polo ilanı Black/Navy satmıyor.** Gerçekten satılıyorsa ilanın renk
   listesi güncellenmeli (`set-product.yml`); yoksa fatura ile vitrin
   çelişiyor. Kendiliğinden değiştirilmedi: bir ilana renk eklemek **her
   alıcının gördüğü** şeyi değiştirir.
2. **"garage" ile eşleşen İKİ hesap var** ve seçimi veri belirledi:
   **GARAGE LE PARIS** (`7ab30f26…`, Ferhat AGAYA, FR VAT + sicil no + IBAN +
   BIC + Stripe) ile **"Les Garage Paris"** (`8fa9d40d…`, **adres yok, VAT yok,
   sicil yok, banka HİÇ yok, Stripe yok**). İkincisinden fatura kesilseydi
   belge vergi kimliksiz, adressiz ve **ödeme kutusuz** çıkardı. Ölü hesabın
   kapatılması/birleştirilmesi operatör kararı.

**KURAL 28 — Elle açılan hesabın GİRİŞ YAPACAK bir yolu olmalı: "şifreni seç"
mektubu** (operatör, 17 Eyl 2026: *"francisco ya bir email gönder kendi paswort
ile yeni bir paswort olustursun ve girsin"*).
- `create_buyer` hesabı açıyor ama **şifre rastgele ve hiçbir yere
  yazılmıyor** — bu bilinçliydi (şifre gösterilemez) ama yarısı eksikti:
  müşterinin giriş yapmasının **hiçbir yolu yoktu**. Kapıyı açıp anahtarı
  vermemek.
- **YENİ BİR MEKTUP YAZILDI** (`vestra_account_ready_text`, `inc/notify.php`,
  `vestra_reset_text`'in hemen yanında). Mevcut sıfırlama mektubu *"birileri
  (umarız siz) şifre sıfırlama **İSTEDİ**"* diye açıyor: müşteri hiçbir şey
  istemedi ve **hiç sahip olmadığı** bir şifre "sıfırlanmıyor". Üstelik o metin
  hesabın **açık** olduğunu ve sipariş verebileceğini hiç söylemiyor. Bu depo
  aynı dersi iki kez kaydetti — KURAL 2b (kapı açıkken belgeyi sebep göstermek)
  ve KURAL 2h (varsayılan kayıt metni *"ekibimiz hesabınızı aktive edecek"*
  diyerek **yapılmayacak** bir işi bekletiyordu ve o hesaplara ayrı bir gövde
  yazıldı). **Üçüncüsü bu.**
- **JETON ÜRETİCİSİ AYNI:** `auth_reset_begin()` — `forgot.php`'nin
  çağırdığının aynısı, link yine `/reset?token=`. İkinci bir jeton yolu, ikinci
  bir ömür ve ikinci bir güvenlik kuralı demekti.
- **Süre 1 saat ve soğuk bir mektupta bu gerçek bir sürtünme.** Ömrü bu yol
  için uzatmak ikinci bir kural yazmak olurdu; onun yerine **metin çıkış yolunu
  söylüyor** (`/forgot` + bu adres). `reset.php` zaten "süresi dolmuş"
  ekranında aynı yere bağlanıyor, yani söylenen şey **doğrulanabilir**.
- **Metin hesabın DURUMUNU okuyor, varsaymıyor:** kapı açıksa *"hemen sipariş
  verebilirsiniz"*, kapalıysa *"ekibimiz onaylayacak"*; belge cümlesi yalnız
  `trade_licence` hâlâ **`requested`** ise yazılıyor (`uploaded` olana
  "yükleyin" demek yaptığı işi tekrar yaptırmaktır — KURAL 2b).
- **ÖLÇÜT HESAP ID'Sİ, ADRES DEĞİL.** `diag-live.yml`'deki mevcut `reset_probe`
  bu işi zaten yapabiliyor **ama girdisi bir e-posta** — yani müşterinin adresi
  koşu başlığında kalıcı ve herkese açık. Depo bu hatayı `only_emails` →
  `only_accounts` ile bir kez düzeltmişti; aynı desen (`seller-products.yml` →
  `admin_mode=password_setup`, `issue_ref=<hesap ID>`). **TAM 1 eşleşme yoksa
  iş DURUR:** sıfırda kimseye gitmez, birden fazlada **yanlış müşteriye**
  giderdi ve gönderilmiş bir mektup geri alınamaz.
- **KURU KOŞU JETON ÜRETMEZ.** `auth_reset_begin()` kayda yazıyor ve **varsa
  önceki jetonu geçersiz kılıyor** — bir önizlemenin müşterinin elindeki canlı
  linki öldürmesi kabul edilemez. Kuru koşu metni sahte bir linkle kuruyor; bu
  aynı zamanda şablonun sunucuya **indiğini** de ölçüyor (KURAL 21b'nin "deploy
  inmemiş" dersi).
- **Link, jeton ve GÖVDE kütüğe basılmıyor** (jeton tek başına hesabı açar;
  hitapta müşterinin adı var), adres maskeli. Operatör metni **sohbette**
  görüyor — kütük herkese açık, sohbet değil.
- **Dil hesabın KAYITLI dilinden** (`vestra_user_lang`), `vlang()` değil: bu
  mektup operatörün isteğinden doğuyor, müşterinin kendi isteğinden değil —
  `vestra_reset_text`'in kendi notunun yazdığı ayrım.
- Test: `tests/account_ready_letter_test.php` (107 iddia, iki yön). Her
  sabotajın **gerçekten uygulandığı ayrıca doğrulanarak** düşebildiği ölçüldü:
  `open` dalı kaldırılınca **10 kırmızı**, belge cümlesi koşulsuz yazılınca
  **5**, kuru koşu jeton üretince **2**, link kütüğe basılınca **1**, adres
  maskesiz basılınca **1**, TAM 1 eşleşme şartı kalkınca **1**.
  **Bir sabotajım ilk denemede hiç uygulanmamıştı** (perl kaçışı eşleşmedi) ve
  "0 kırmızı" diyerek testi sağlam gösterecekti — bu dosyada kayıtlı tuzak,
  yeniden uygulanıp doğrulandı.
- **Kendi ölçüm hatam:** adım gövdesini kesen ayırıcım (`- name: SİPARİŞİN
  RENGİNİ`) dosyada **yoktu** (o bir yorum satırı, `- name:` değil), yani
  "sızıntı yok" iddiaları **dosyanın geri kalanını** okuyup düştü. Ayrıca
  `vlang()` iddiası, `vlang()`'in **neden kullanılmadığını** anlatan kendi
  yorumumu okudu. İkisinde de iddia gevşetilmedi — **ölçtüğü şey daraltıldı**
  (adım sınırına kadar kes, yorumsuz metne bak).
- **CANLI SONUÇ (17 Eyl 2026, deploy `df4077a7`).** Kuru koşu (run
  `35251178651`): hesap `7d68b8778cd3884c` · `b***@gmail.com` · buyer/active ·
  dil **es** · ülke Spain · kapı **AÇIK** · `trade_licence: requested` → belge
  cümlesi yazılır · `mail_enabled=acik`, `from=support@vestrasales.com` ·
  konu *"VESTRA — tu cuenta está abierta, elige una contraseña"* · gövde 746
  karakter · **jeton üretilmedi, mektup gitmedi**. Gönderim (run
  `35251231280`): jeton üretildi, son geçerlilik **18:12 UTC** (+1 saat),
  **GÖNDERİLDİ**. Kütükteki "kabul etti" yalnızca **Brevo isteği kabul etti**
  demek — `delivered` bile posta kutusu kanıtı değil (bu dosyanın kendi
  uyarısı).
- **TESLİMAT ÖLÇÜLDÜ** (operatör: *"francisco ya email gittimi"*).
  `diag-messages` → `mailcfg=true`, `mail_for=account:7d68b8778cd3884c`,
  `mail_subject=contraseña`: **`17:12:04 requests` → `17:12:05 delivered`**,
  konu birebir. Yani Gmail kabul etti; **spam klasörü ihtimali duruyor**.
- **`mail_for=account:` artık HESAP ID'siyle de eşleşiyor** (TAM eşitlik önce,
  sonra eski ad araması). Eskiden yalnız `company`/`name` içinde arıyordu ve bu
  hesapta tek ayırt edici parça kişinin **soyadı** olurdu — kalıcı, herkese açık
  bir koşu başlığına. `order_draft`/`order_write` ve `password_setup` aynı
  sırayı zaten taşıyor; bu **üçüncü çağrı yeri**.
- **İKİNCİ MEKTUP gönderildi** (operatör, aynı gün: *"Brandluxuryspain@gmail.com
  password degisimi gönder"*): run `35252233157`, jeton son geçerlilik
  **18:21 UTC**. **Yeni jeton eskisini geçersiz kılıyor** (`auth_reset_begin`
  alanı üzerine yazıyor) — yani elindeki iki linkten yalnız **ikincisi**
  çalışır. Burada zararsızdı: ilki 20 dakikalıktı ve kullanılmamıştı; ama
  müşteri linke tıklamışken ikinciyi göndermek onu **akışın ortasında**
  keserdi. *Yeniden göndermeden önce ilkinin yaşını ve kullanılıp
  kullanılmadığını sor.*

**KURAL 26 — Para birimi seçimi KALICI; çerezi yazan tek yer money.php'nin
yüklenme anı** (operatör, 13 Eyl 2026: *"para birimi sürekli degisiyor ... para
birimi secilmesine ragmen bir sonraki linke tiklandginda gene eur oluyor ayrica
iP den algilanip para biriminin ... o ülkeye göre ayarlanmasi gerekir"*).

- **ÖLÇÜLDÜ, tahmin edilmedi:** `?cur=USD` yanıtı **`vcur` çerezi taşımıyordu**
  (yalnız `PHPSESSID`) ama sayfa USD basıyordu — yani seçim o sayfada çalışıyor,
  bir sonraki istekte yok. Aynı istekte sondaladım: `headers_sent()` = **EVET**.
- **Sebep, çıktı tamponunun nerede boşaldığıydı.** Çerez yazma
  `vestra_currency()`'nin **içindeydi** ve o fonksiyon sayfada ilk kez
  `head.php`'nin üst çubuğunda (**~235. satır**) çağrılıyor. Orada PHP'nin
  varsayılan 4 KB'lık tamponu çoktan boşalmış oluyor, `!headers_sent()`
  muhafazası yanlış çıkıyor ve `@setcookie` **sessizce** atlanıyor — `@` uyarıyı
  da yutuyor.
- **DİL TARAFI ÇALIŞIYORDU ve öğretici olan bu:** `vlang()` `<html lang=…>`
  içinde, yani sayfanın ~2. KB'sinde çağrılıyor — tampon henüz boşalmamış, çerez
  yazılıyor. **Aynı kusur iki fonksiyonda da yazılıydı**; yalnızca biri
  tetikleniyordu. Ölçüldü: `?lang=de` → `Set-Cookie: vlang` **VAR**,
  `?cur=USD` → **YOK**. *Çıktı tamponuna bağlı bir yazma, çalışıyor görünse bile
  tesadüfen çalışıyordur — ve `<head>` bir gün uzayınca dil de kırılır.*
- **Çözüm konumdan BAĞIMSIZ:** `vestra_currency_remember()` **money.php
  yüklenirken** çağrılıyor. `head.php` onu 5. satırda, hiçbir çıktı basılmadan
  require ediyor, yani çerez her zaman yazılabiliyor. "Her yeni sayfada
  hatırla" çözümü yazılmadı — bu depoda hatırlamaya bırakılan kural defalarca
  bozuldu.
- **TEK YAZICI:** `vestra_currency()` artık kendi `setcookie`'sini taşımıyor,
  buraya devrediyor. Testte `setcookie('vcur'` **tam 1 kez** geçmeli.
- **Yazamazsa SUSMUYOR:** `headers_sent()` yine de doğruysa `error_log`'a
  dosya:satır düşüyor (kişiye ait alan yok). Sessiz kalsaydı aynı kusur geri
  gelir ve yine kimse görmezdi.
- **Süreç içi ayna CLI muafiyetinin ÜSTÜNDE** — ilk yazımda altındaydı ve test
  yakaladı (`USD|-`): cron çerez yazmamalı ama `?cur=` verilmiş bir çağrıda
  hangi birimin seçildiğini yine de bilmeli. `SameSite=Lax` eklendi (kampanya
  linkinden gelen ziyaretçide de taşınsın).
- **İKİNCİ, AYRI OLGU — KUR YOKSA HER FİYAT EUR BASAR.** `vestra_fx()` kur
  bulamazsa `vestra_money()` sessizce EUR yazıyor (dosyanın kendi kuralı: yanlış
  kurla fiyat göstermek, göstermemekten kötü). Yani seçim ve etiket değişirken
  **rakam değişmez** ve operatör bunu "para birimi çalışmıyor" diye okur.
  Kum havuzunda ayrıştırıldı: kur **yokken** dört birimde de `€40.00`; kur
  tohumlanınca **US$46.49 / A$65.40 / C$63.24** (40 × 1,1622 / 1,635 / 1,581).
  Deponun `data/fx_rates.json` kopyasında **yalnız `fail_ts`** var, `rates` yok
  — **sunucudaki kopya `cur_probe` ile ölçülmeli**, repo kopyası kanıt değil.
- **IP'den ülke ZATEN VARDI ama KURAL 12'nin maliyet korumaları YOKTU.** O
  korumalar (CLI atla, **BOT atla**, zaman aşımı **1 sn**) dil tarafı için
  yazılmış ve `vlang_from_ip()` içinde duruyordu; para birimi yolu aynı
  `vestra_ip_intel()`'i çağırıyor ama hiçbirini taşımıyordu — her tarayıcı botu
  3 sn'lik iki coğrafi sorgu tetikleyebiliyordu. *Aynı olgunun ikinci çağrı
  yeri, ilkinin öğrendiklerini otomatik miras almıyor.* **Fonksiyon
  PAYLAŞILMADI**: `vlang_from_ip()` DİL döndürüyor, bu ÜLKE arıyor; ortak bir
  gövde yarın dil tablosundaki bir değişikliği para birimine de sessizce
  taşırdı.
- **Hesabın beyan ettiği ülke IP'den ÖNCE** soruluyor: ağsız, bedava ve daha
  doğru. Sıra: `?cur=` > çerez > hesabın ülkesi > IP ülkesi > EUR.
- **Ölçüt çerezden sonra geliyor, bilerek** (KURAL 12'nin aynı gerekçesi):
  IP yalnızca **hiç seçim yapılmamış** ziyaretçide devreye girer. Tersi,
  seyahatteki bir alıcının kendi seçtiği birimi coğrafyayla ezmek olurdu.
- **Ülke→birim tablosu (mevcut karar, değiştirilmedi):** AU→AUD, CA→CAD,
  US→USD, AB üyesi→EUR, **geri kalan her yer→USD**. Yani **GB→USD** ve
  **CH→USD**: İngiliz alıcı sterlin değil dolar görüyor. GBP eklemek yeni bir
  para birimi + kur demek, yani **fiyat kararı** — kendiliğinden yapılmadı.
- **Sonda: `seo-check.yml` → `cur_probe=true`.** Üç şeyi birden basıyor çünkü
  şikâyet üçünden herhangi birinden gelir ve aynı görünür: kur durumu
  (kaynak/tarih/`fail_ts`), ülke→birim tablosu (ağsız) ve *"seçim kalıcı mı"*
  kablolaması (fonksiyon indi mi, include-time çağrı var mı, tek yazıcı mı).
  Önce kum havuzunda koşturuldu.
- **ÇİZDİRİLDİ, kaynak okunmadı** (kum havuzu, `php -S`, **onaylı alıcı
  oturumu** — girişsiz çekmek fiyat kapısını ölçerdi, KURAL 4b/21d'nin aynı
  tuzağı ve ilk ölçümümde dört birimde de "1 sembol" çıktı):
  `?cur=USD` → `Set-Cookie: vcur=USD` + `US$46.49`; **bir sonraki link**
  (`?cur` yok) → USD, `US$46.49`; **başka sayfa** (ürün) → USD; AUD seçip
  `/cart` → AUD. PHP uyarısı yalnız sentetik kum havuzu satırlarının
  `unit` alanı olmamasından (gerçek katalogda 20/20 ilanda o alan var).
- **CANLI ÖLÇÜM (run `34779582421`, deploy `e27d0515`) — ve tahminimi ÇÜRÜTTÜ:**
  sunucuda **kur VAR** (`kaynak ecb`, `2026-09-11`, `1 EUR = 1,1592 USD /
  1,6161 AUD / 1,6064 CAD`, önbellek aynı gün 15:30Z, `fail_ts` yok). Yani
  deponun `fail_ts`-only kopyası sunucuyu **temsil etmiyordu** ve "fiyatlar
  kurdan dolayı EUR basıyor olabilir" endişesi canlıda **geçerli değil** —
  tek gerçek kusur çerezdi. *Repo kopyası kanıt değildir; sunucu kanıttır.*
  Kablolamanın üçü de yeşil: fonksiyon indi, include-time çağrı var, tek yazıcı.
- **IP TARAFI DA CANLIDA ÖLÇÜLDÜ** (`test_ip=8.8.8.8`, run `34779690347`):
  `8.8.8.8 → ulke=US (Ashburn) [180 ms]` → dil `en`, **birim USD**. Yani coğrafi
  uç sunucudan açılıyor ve tablo uygulanıyor. Adım artık **aynı coğrafi cevaptan
  DİL ve BİRİM'i birlikte** basıyor — ikisini ayrı koşularda ölçmek, birinin
  çalışıp ötekinin çalışmadığı hâli gizlerdi.
- Test: `tests/currency_pick_test.php` (33 iddia, iki yön). Düşebildiği
  doğrulandı, her sabotajın **gerçekten uygulandığı** ayrıca yazdırılarak:
  include-time çağrı silinince **2 kırmızı**, eski hata (setcookie yine
  `vestra_currency()` içinde) geri konunca **1**, bot muhafazası etkisizleşince
  **1**, zaman aşımı 3'e dönünce **1**, ülke eşleşmesi alt dizeye gevşeyince
  **1** (`AT`→AUD, `CH`→CAD — mango/zara dersinin para birimi hâli).

**13 Eyl 2026 — operatörün tek tek verdiği fiyat/durum kararları.**
- İki Lacoste polosu **ausverkauft**: `lac-logotrim-polo` (Regular Fit Logo Trim
  L.12.12) ve `lac-monogram-polo` (Classic Fit Monogram Jacquard). `match` ADA
  düşüyor; `expect:1` yazım yanlışını yakalar (kardeşi *"Paris Regular Fit Polo
  Shirt"* bu metni içermiyor).
- **"Striped Polo Shirt" bir Lacoste DEĞİL** — ölçüldü: `dgn-g8kb8zfi7yo`,
  Dolce & Gabbana, €60. Yani o satır üçüncü bir sold_out değil, *"DG tüm
  pololari 110 eur yap"* talimatının **örneği**. Ölçmeseydim satılmakta olan bir
  D&G polosunu satıştan kaldıracaktım.
- **D&G polo = tam 6 ilan → €110.** Hepsi tek kademeli, hepsi moq 20; `price`
  alanı `list`'i ve bütün kademeleri aynı değere yazıyor, yani düzleşme riski
  yok. **Eşleşme ID ile:** iki ilan aynı adı taşıyor (*"DG Logo Polo Shirt —
  Black"*), ada göre eşleşme "2 ürüne uydu" deyip dururdu.
- **"Hoodieleri sapkalilari 145" kategorinin tamamı değil.** `Hoodies &
  Sweatshirts` altında 10 D&G ilanı var; adında HOODIE geçen **3**'ü 145 oldu
  (Print Hoodie Black, Hoodie White, Floral Print Hoodie Navy). **7 crewneck
  sweatshirt bilerek 120/90'da bırakıldı** — operatörün çizdiği ayrım tam bu.
- **Takımlar TEK TEK adlandırıldı, toptan değil.** İlk cümlede yalnız *"Logo
  Tape Sweatshirt & Trousers Set"* yazıyordu ve "tüm" kelimesi yoktu; dar
  okundu. Operatör ardından **ikinciyi adıyla** yazdı (*"Logo Tape Track Jacket
  & Trousers Set … 220 eur yap teklif te koy"*), yani dar okuma doğruydu.
  İkincisinde ayrıca **teklif AÇILDI** (`offers:on` → `offers=true` +
  `no_offers` silinir; ilan `mode='fixed'` kaldığı için sabit fiyat duruyor ve
  yanında teklif kutusu çıkıyor). Kalan **iki takım 190'da ve teklif kapalı** —
  adlandırılmadılar.

**Burberry 8049455: ŞORT değil ETEK; €90; XS–XL kadın serisi** (operatör,
17 Eyl 2026: *"rock yap ve fiyat 90 eur ya indir bedenleride XS ten basla XL e
kadar kadin bedenleri yap 10 lu"* — **"Rock" Almanca ETEK**, operatörün sohbet
diline karışan bir kelime; ürün adı değil).

- **AD ve KATEGORİ de değişmek zorundaydı, yalnız fiyat/beden değil.** İlan
  `Burberry Denim Shorts — 8049455`, `cat=Jeans Shorts` idi. Etek olan bir malı
  "Shorts" diye satan bir ilan, sattığı şeyi yanlış söyler — D&G eşofmanlarında
  (12 Eyl) *"€190'a komple takım satarken adı 'Jogging Trousers' bırakmak"*
  diye kaydedilen dersin aynısı. Yeni ad `Burberry Denim Skirt — 8049455`
  (em-dash ve SKU kuyruğu aynen), kategori **`Skirts`**.
  **Kategori uydurulmadı:** `Skirts` taksonomide (`Bottoms` grubu) zaten vardı.
- **BEDEN SERİSİ İKİ YERDE YAZILIYDI ve kuru koşu bunu kanıtladı.** `sizes`
  alanının yanında `desc` de aynı seriyi taşıyor:
  *"Original Burberry, model 8049455. S×1 · M×3 · L×3 · XL×2 · XXL×1 · 10/pack.
  EEA stock…"*. 9 Eylül'de Balenciaga'da yalnız `sizes` değiştirilmiş, ürün
  sayfası spec satırında bir, bir paragraf altında başka dağılım göstermişti.
  Bu kez **ikisi birlikte** yazıldı ve kalan metin şablondan değil **sunucunun
  kendi dizgesinden** kuruldu.
  *Eski `desc`'i tahmin etmiştim (dokuz Burberry ilanının kalıbı tekdüze); ama
  yazmadan önce kuru koşu onu **birebir** gösterdi — tahmin kanıt değildi,
  kuru koşu kanıttı.*
- **EĞRİ UYDURULMADI: 1-3-3-2-1 katalogun kendi 10'luk eğrisi.**
  `XS×1 · S×3 · M×3 · L×2 · XL×1` = **10**. Aynı eğri giyimde
  `S×1 · M×3 · L×3 · XL×2 · XXL×1`, oversize'da `XXS×1 · XS×3 · S×3 · M×2 · L×1`
  olarak duruyor; değişen tek şey **merdivendeki yer** (9 Eyl'in oversize
  kararının aynı deseni). Aynı markadaki `bur-8039175` da XS–XL taşıyor ama
  serisi **8 parça** (`XS×1 · S×2 · M×2 · L×2 · XL×1`) ve `desc` 10 diyor —
  yani o, CLAUDE.md'de kayıtlı 25 eski çelişkiden biri, **şablon olarak
  kullanılamazdı**.
- **Paket eki `10/pack` AYNEN korundu.** `10 pcs/pack` gibi ikinci bir yazıma
  çevirmek cazipti; `VESTRA_SIZE_PACK_RE` yalnız tanıdığı biçimi paket eki
  sayıyor ve tanımadığı bir yazımda **`10` sessizce bir BEDEN olarak
  ayrışırdı** (KURAL 21b'nin `3/pack` tuzağı).
- **Fiyat: `price: 90` — hem `list` hem TÜM kademeler.** İlan `mode='sale'` ve
  bugün `list = tier = 120`, yani ekranda indirim rozeti **yoktu**. Yalnız
  kademeyi 90 yapıp `list`'i 120 bırakmak, operatörün **hiç söylemediği** bir
  %25 indirim ilan ederdi. 13 Eylül'deki altı D&G polosunun (€110) birebir
  deseni. `mode`'a dokunulmadı — istenmedi ve list=fiyat olduğu için yalan
  söyleyen bir rozet doğmuyor.
- **Geri okuma sunucudan** (`inspect-products`, yazma mesajından değil):
  `cat=Skirts`, `fiyat(list)=90 EUR`, `tiers: 10+ → €90`,
  `sizes=XS×1 · S×3 · M×3 · L×2 · XL×1 · 10/pack`, ad `… Denim Skirt …`.
  **Fiyat denetimi** (alanın değerine değil sepetin TAHSİL ETTİĞİNE bakıyor):
  895 üründe *alıcı aleyhine* tek satır var ve o eski demo tohumu
  `lac-pique-polo`, Burberry değil — yani MOQ 10'da sepet gerçekten €90 alıyor.
  **`fit_scan` çelişki listesinde `bur-8049455` YOK**; liste id'ye göre sıralı
  ve `8047732` ile `8052119` **arasında** olması gerekirdi, orada değil (Burberry
  çelişkisi 9'da kaldı, artmadı).
- **Operatör kararı bekleyen üç şey, üçü de açıkça söylendi:**
  1. **Fotoğrafı göremedim** — `uploads/` repoda değil, yalnız sunucuda; bu
     ortamdan canlı siteye de çıkılamıyor. Karenin hâlâ şort gösteriyor olma
     ihtimali duruyor ve o zaman ilan ters yönden yalan söyler.
  2. **`size_step` bu ilanda HİÇ SET DEĞİL.** *"10 lu"* cümlesini "sepet
     adedi 10'un katına yuvarlansın" diye okumadım: `sizes` zaten `10/pack`
     diyordu ve `moq=10` (bir karton). `size_step` eklemek **satın alma
     davranışını** değiştirir, istenmedi.
  3. **Teklif kutusu AÇIK kalıyor** (`mode=sale` ⇒ `offers`). Fiyat düşünce
     KURAL 4'ün tabanı da düştü: alıcı teklifi en az ürünün yarısı, yani
     **€60 değil artık €45**.

**KURAL 2f — belge süresi 3 → 7 GÜN; Marca Online MUAF** (operatör, 13 Eyl 2026:
*"bu saticiya istisna yap ... diger saticilarada 7 gün sure ver"*).
- `VESTRA_SELLER_DOC_GRACE_DAYS` **7**. Rakam iki mektup şablonunda, cron'un
  operatöre yazdığı dört satırda ve satıcı bandında **zaten sabitten** okunuyordu.
  Ama `admin.php`'de **ALTI yerde elle "3-day"** yazılıydı (onay pencereleri,
  rozet ipuçları, durum mesajları): kural 7 gün olurken operatörün ekranı "3"
  demeye devam edecekti — KURAL 6'nın escrow tavanı dersinin panel hâli. Hepsi
  sabitten okuyor artık ve **testte tarama var**.
- Uzatma **geçmişe de işliyor**: damgası duran bir satıcının son tarihi
  `start+7`'ye kayıyor, yani bugün askıda olmayan kimse bugün askıya alınmaz.
- Muaf liste: `auth_doc_grace_exempt_uids()` = GARAGE LE PARIS + **Marca Online**
  (`0cb79eb883f2a0fa`).
- **MUAFİYET, ZATEN ASKIDA OLAN BİR HESABI AÇMAZ — işin asıl yeri burasıydı.**
  `auth_seller_doc_grace()` muafiyeti `suspended` dalından **ÖNCE** soruyor:
  muaf satıcı bir daha askıya **alınmaz**, ama askıdaysa kendiliğinden
  **açılmaz**. Ölçüldü (`diag-live` → `find_ref`): Marca Online
  `status=suspended, reason=docs` ve **146 approved ilanı katalogdan çekiliydi**
  — yani iç çamaşırı bölmesi boştu. Yalnız muafiyeti yazıp "tamam" deseydim
  operatör aynı boş bölmeyi görmeye devam edecekti.
  *Hesap raporu bunu tek başına söylemedi: maskeli listede iki satıcı `suspended`
  görünüyor ve hangisi olduğu e-postadan okunamıyor. Çelişkiyi (mektup "paused"
  diyor, ilk okumam "active" sandı) çözen şey uid ile ölçmek oldu.*
- Yeni yol: `seller-products.yml` → **`admin_mode=seller_activate`**
  (`issue_ref=<hesap ID>`, `move_apply=true`). Panelin **Activate** düğmesiyle
  aynı iki yazma (`status='active'`, `suspend_reason=''` + belge askısından
  çıkıyorsa taze pencere); ikinci bir açma mantığı yazılmadı. İlan sayımı **ham
  listeden** (`vestra_listings`): `vestra_products()` yalnız `approved` döndürür
  **ve askıdaki satıcının ilanlarını zaten eler**, yani "kaç ilan geri gelecek"
  sorusunu yapısı gereği cevaplayamaz — D&G partisinde bir kez ödenmiş bedelin
  aynısı. Kuru koşu varsayılan, yazma **geri okunuyor**.
- **Canlı sonuç:** `durum=active, sebep=(boş)`, **146 approved ilan yeniden
  katalogda**.
- Test: `seller_doc_grace_test.php` 30 → **37 iddia**. Dört sabotaj, her biri
  önce gerçekten uygulandığı doğrulanarak: süre 3'e dönünce **1**, Marca listeden
  çıkınca **2**, muafiyet herkese açılınca **14**, `admin.php`'ye elle "3-day"
  geri konunca **1**.
- **Kendi hatam, kayda geçsin:** yeni tarama iddiam ilk yazımda `$root` tanımsız
  olduğu için dosyaları **boş** okuyordu ve dördü de **boşa geçti**. Yalnızca PHP
  uyarısı ele verdi; düzeltilince `admin.php`'deki altı yeri hemen yakaladı.
  *Hiç düşemeyen bir iddia, iddia değildir — bu dosyada üçüncü kez.*

**AMI PARIS — Core Logo Polo: ön sipariş teslim tarihi (`preorder_ship`) demo
ürünlerde HİÇ yazılamıyordu** (operatör, 22 Eyl 2026: *"15 oktober lieferzeit
olarak not düş ilana"*).
- **İlan `amiri-core-polo` (`Core Logo Polo — Ami de Cœur`) `listings.json`'da
  değil, kodda gömülü 3 demo üründen biri** (`vestra_is_demo_product()`:
  `lac-pique-polo`, `amiri-core-polo`, `lac-l1212-musterstueck`). MOQ/mode/list/
  tiers/teklif/numune düzeltmeleri zaten `data/product_overrides.json`
  üzerinden gidiyor (`vestra_apply_price_overrides()`, admin panelin Prices
  editörü) — ama `preorder_ship` o katmanın bilmediği tek alandı.
  `set_product.php`'nin `$ALLOWED` listesinde zaten vardı ve doğruluyordu
  (YYYY-MM-DD), ama o betik yalnız `listings.json` satırlarını eşleştiriyor;
  bu ürünün orada **hiç satırı yok**, yani aynı yoldan yazmaya çalışmak "0
  ürüne uydu" ile **sessizce hiçbir şey değiştirmeden** dururdu.
- **İki küçük ekleme, ikisi de mevcut düzenin devamı:**
  1. `vestra_apply_price_overrides()`'a `preorder_ship` dalı (YYYY-MM-DD
     doğrulamalı, `moq`/`tiers`/`sample_price` ile aynı desen).
  2. `seller-products.yml` → **`admin_mode=preorder_ship`**
     (`issue_ref=<ürün id>`, `payload=YYYY-MM-DD`, boş = **KALDIR**;
     `move_apply=true`, varsayılan kuru koşu). Yeni bir workflow girdisi
     **eklenmedi** — üç mevcut alan (`issue_ref`/`payload`/`move_apply`)
     yeniden kullanıldı, çünkü `workflow_dispatch` 25 girdiyle sınırlı ve bu
     dosya zaten sınırda (CLAUDE.md'nin kendi kaydı). **Canlı listeler için bu
     alan hâlâ set-product.yml'den yazılır** — yeni mod yalnız 3 demo ürünü
     hedefliyorsa çalışır, başka bir id verilirse **reddeder** (sessizce "yok"
     demek yerine, doğru yolu — set-product.yml — söyleyerek).
  3. Kuru koşu, sayfada **gerçekten görünecek cümleyi** basıyor
     (`vestra_preorder_note()`'un kendisinden — elle kurulmuş bir metin değil):
     tarih geçmişse ya da alan boşsa "hiç satır basılmaz" diyor, kimse "15
     Ekim yazdım ama sayfada yok" diye şaşırmasın diye.
- **Yedek + geri okuma, kardeş alanlara dokunmadan.** `moq`/`tiers` override'ı
  zaten varsa (bu üründe vardı: MOQ 60, üç kademeli merdiven) `$ov[$id]`
  **okunup üzerine eklenir**, wholesale yazılmaz — panelin Prices editörü de
  aynı deseni kullanıyor (POST'ta olmayan alanı silmiyor), yani iki yazma yolu
  birbirini ezmiyor.
- **Kendi ölçüm hatam, kum havuzunda yakalandı:** ilk sürümde geri okuma
  `$ov[$id]['tiers'] === $back[$id]['tiers']` diye **`===`** ile kıyaslıyordu
  ve **her koşuda sahte bir "DEĞİŞTİ" raporluyordu** — yazma doğruydu, ölçü
  yanlıştı: `json_encode` tam sayılı bir float'ı (`39.0`) `JSON_PRESERVE_ZERO_
  FRACTION` olmadan `"39"` yazıyor, diskten geri okunan da `int(39)` oluyor —
  aynı fiyat, farklı PHP tipi. `===` bunu kayıp sanırdı; bu depoda `billing_
  saved`'in verdiği dersin aynısı (ölçüm aracının kendi gürültüsü). `==`'a
  çevrilince (tip zorlamalı, sıra bağımsız) doğru sonuç çıktı — hem "moq/tiers
  KORUNDU" hem gerçek bir kayıp verilseydi hâlâ yakalanırdı, ayrıca sınandı.
  Sandbox: `VESTRA_DATA_DIR` sabitiyle izole edilmiş sahte bir `product_
  overrides.json` üzerinde — **symlink + `HOME` ile kurulan ilk deneme
  `__DIR__`'in gerçek repo yolunu çözdüğünü** gösterip yanlışlıkla bu
  checkout'un yerel (git'e girmeyen, `data/*` .gitignore'lu) `vestra/data/
  product_overrides.json`'ına yazdı — zararsızdı (sunucu değil, ve iz
  temizlendi) ama *bir sandbox'ın gerçekten izole olduğunu varsaymak yerine
  ölçmek* gerektiğini bir kez daha gösterdi.

**22 Eyl 2026 — VES-1A68FCD1'e AMI Paris Polo eklendi; KODDA "var olan siparişe
sonradan yeni kalem ekleme" diye bir yol HİÇ yoktu.** Operatör, iki ayrı mesajda:
*"Black x 20 , White x10 , Navy 20 , Grey 10"* → *"bu siparisi bu siparise ekle
VES-1A68FCD1 → 2026-09-21 daymondproconect@yahoo.ro SC Daymond Proconect SRL"*.

- **HANGİ ÜRÜN olduğu tahmin edilmedi, ölçülüp operatöre SORULDU.**
  VES-1A68FCD1'in mevcut iki kalemi (D&G Oversized Tee `G8OB1TG7B2M`,
  DSQUARED2 Oversized Tee `S74GD1399`) ikisi de **tek renk taşıyor: Black** —
  White/Navy/Grey ikisinde de yok. Aynı alıcının üç açık teklifi de (Burberry
  Globe Tee, Burberry Striped Polo, Givenchy Logo Tee) `renk(0)=(yok)` —
  hiçbirinde kayıtlı renk yok. Katalog genelinde Black/White/Navy/Grey'in
  **dördünü birden** taşıyan tek ürün Ralph Lauren `rl-csf-tee-navy`
  (710680785004) gibi görünüyordu (aynı buyer'a bağlı bir mesaj ipliği ve
  — o gün silinmiş — bir teklifi vardı) ve bu **AskUserQuestion**'da önerildi;
  operatör **"Ami paris Polo"** cevabını verdi — tahminim yanlıştı, doğrusu
  `AMI-PL-014` (`amiri-core-polo`, katalogda hard-coded bir demo ürün, renkleri
  tam Black/White/Navy/Grey, min_colors=2). *Sorulmasaydı yanlış SKU'ya
  yazılırdı — Task C'nin (Burberry 24'lü fiyat beraberliği) aynı disiplini,
  bu kez ürün kimliği için.*
- **İKİNCİ SORU: sipariş ZATEN KESİLMİŞ bir fatura taşıyordu ve buyer onu
  "ödeme için aldım" diye ONAYLAMIŞTI** (mesaj ipliği `f449cd5405251419`, son
  mesaj buyer'dan: *"I confirm receipt of the invoice for payment."*).
  Operatöre soruldu: yeni ayrı bir sipariş mi, yoksa VES-1A68FCD1'e ekleyip
  AYNI numarayla yeniden mi çizilsin. Operatör: **"VES-1A68FCD1'e ekle,
  faturayı AYNI numarayla yeniden çiz."**
- **KOD TARAFINDA BU EYLEMİN HİÇBİR YOLU YOKTU, ve bu ölçülerek görüldü —
  varsayılmadı.** `vestra_order_set_colours()` yalnızca **ZATEN `items`'te
  duran** bir kalemin renk notunu düzeltiyor; SKU orada yoksa kendi geri-okuma
  doğrulaması (`vestra_order_lines()`'ın o SKU'yu görmesi) her zaman
  **başarısız** olurdu. `vestra_order_create_manual()`/`order_write` ise HER
  ZAMAN **yeni ve AYRI** bir sipariş yazıyor (`vestra_order_ref` rastgele
  üretiliyor), var olan bir ref'e asla eklemiyor. "Var olan bir siparişe
  sonradan yeni bir kalem ekleme" ikisinin arasında hiç yoktu.
  Ayrıca toptan (`pricing=wholesale`) satır formatı **renk başına farklı
  adet** taşıyamıyor: `SKU:RENK:ADET` bir renk KÜMESİ + TEK toplam adet alıyor
  (renkler `;` ile ayrılıp eşit ağırlıklı sayılıyor), operatörün verdiği
  **20/10/20/10 asimetrik kırılımı** hiçbir şekilde tek satırda ifade
  edilemiyordu.
- **`vestra_order_add_line()` yazıldı** (`inc/orders.php`): toplam
  `vestra_order_set_shipping()` ile **AYNI** formülle — goods HER ZAMAN
  `vestra_order_lines()`'dan (items'in YENİ hâli dahil) yeniden hesaplanıyor,
  `subtotal` sütunundan değil (KURAL 32'nin dersi); `discount` DOKUNULMADAN
  kalıyor (zaten yazılmış, sabit bir operatör kararı); eski "fee" eski
  toplamdan GERİ TÜRETİLİP korunuyor — ikinci bir hesap yolu yazılmadı.
  Renk+adet kırılımı hiçbir yapılandırılmış alanda tutulmuyor (bu depoda
  hiçbir sipariş biçimi renk başına adet taşımıyor): düz renk seti
  `vestra_order_set_colours()` ile **birebir aynı** notlar-haritası deseniyle
  yazılıyor (diğer okuyucularla — fatura, sipariş sayfası, panel — uyumlu
  kalsın diye), adet kırılımı UYDURULMADAN ayrı ve okunur bir cümle olarak
  ekleniyor (`"AMI-PL-014 colour split: Black×20, White×10, Navy×20,
  Grey×10."`). Aynı SKU zaten sipariştaysa **REDDEDİLİR** (ikinci satır değil,
  `vestra_order_set_colours()`'a yönlendirir). Faturalıysa varsayılan **RED**,
  `allow_invoiced=1` opt-in ile yazar ve `must_redraft=true` döner (KURAL 5f,
  `vestra_order_set_colours`/`vestra_order_set_shipping` ile aynı desen).
  Ön sipariş notu **UYDURULMADI**, `vestra_preorder_note($p)`'in kendisinden
  (tek kaynak) — ürünün `preorder_ship`'i o gün başka bir oturumda 15 Ekim'e
  çekilmişti (bkz. yukarıdaki AMI PARIS maddesi) ve bu fonksiyon onu **canlı**
  okuyup "dispatch mid October 2026" bastı; tarih hiçbir yerde ikinci kez
  yazılmadı.
  `admin_mode=order_add_line` (`seller-products.yml`, varsayılan kuru koşu) —
  `order_colours`'un yanına eklendi, aynı SSH/PHP deseni.
- **AYNI DALDA İKİNCİ BİR OTURUM ÇALIŞIYORDU** (bu maddenin hemen üstündeki
  `preorder_ship` girişi) ve `seller-products.yml`'nin dev `admin_mode`
  açıklama dizgesinde **çakışma** çıktı — ikisi de aynı tek satırın SONUNA
  kendi cümlesini ekliyordu. `git merge` beklenen tek yeri (`order_discount`
  ile `platform_bank` arası) gösterdi; iki ekleme elle birleştirildi
  (`order_add_line` → `order_colours`'ın hemen ardına, `preorder_ship` →
  `lead_rename`'in hemen ardına, ikisi de kendi konumunda) ve **22 gömülü PHP
  heredoc bloğunun hepsi** (`order_add_line` ve `preorder_ship` dahil) tek tek
  `php -l` ile doğrulandı. *Bu depoda zaten kayıtlı ders (KURAL 5u'nun
  `vestra_order_set_discount` çakışması): birleştirmeden sonra "aynı satıra
  yazan ikinci bir ekleme var mı" diye ara.*
- Test: `tests/order_add_line_test.php` (**44 iddia**, kum havuzunda gerçekten
  yazıyor — AMI-PL-014 kodda hard-coded bir demo ürün olduğu için
  `listings.json` olmadan da kum havuzunda çözülüyor, canlı D&G/DSQUARED2
  SKU'ları çözülemiyor ve bu **beklenen**). Düşebildiği doğrulandı: faturalı
  sipariş muhafazası kaldırılınca **8 kırmızı** (sabotaj `grep -c` ile
  gerçekten uygulandığı doğrulanıp sonra dosya yedekten geri yüklendi, takım
  tekrar 44/44 yeşile döndü). `sh tests/run_all.sh` tam paket koşuldu — bu
  işten bağımsız, önceden kırık üç test (`dropship_plan_test` 4 — bu ortamda
  kur API'lerine (ecb.europa.eu, frankfurter.app, open-er-api.com) çıkış yok,
  `msg_read_receipt_test` 1, `msg_thread_label_test` 10) dışında hepsi yeşil.
- **CANLI ÖLÇÜM VE YAZMA (22 Eyl 2026):**
  - Kuru koşu ilan kademesini **canlıdan** okudu: `EUR 39.90` (60+ tier —
    `product_overrides.json`'daki MOQ/tiers override'ı, 42.00/36.00/32.00
    değil), ve mevcut faturayı gösterdi: `INV-2026-1015 (EUR 1.825,00)`.
  - `allow_invoiced=1` ile **YAZILDI**: `20x G8OB1TG7B2M @60.00 | 20x
    S74GD1399 @35.00 | 60x AMI-PL-014 @39.90`; goods €1.900,00 → **€4.294,00**;
    indirim/kargo **dokunulmadı** (€95,00 / €20,00); subtotal/payout
    **€4.199,00**; total **€4.219,00** (geri okundu).
  - `admin_mode=issue` + `issue_redraft=true` ile **AYNI numarayla** yeniden
    çizildi: `no: INV-2026-1015 (AYNI numarayla yeniden uretildi)`, 18.481 →
    **18.894 bayt**, yeni sha256. **BELGENİN KENDİSİ ölçüldü, yazma mesajı
    değil:** `belgede navlun: VAR (Shipping 20.00)`, `belgede indirim: VAR
    (95.00)`, `belgede toplam: VAR (4.219,00)`, `belgede odeme kutusu: VAR
    (IBAN satırı)`, `belgede banka adresi: VAR (Germany)`. E-posta
    **GÖNDERİLMEDİ** (bilerek — KURAL 18, operatör göndermeyi istemedi).
  - Bağımsız ikinci okuma (`diag-live` → `find_ref=VES-1A68FCD1`) birebir aynı
    rakamları doğruladı: `items` üç satır, `subtotal=4199.00`,
    `payout=4199.00`, `total=4219.00`, notlarda üç SKU'nun da rengi (`Colours
    — G8OB1TG7B2M: Black | S74GD1399: Black | AMI-PL-014: Black, White, Navy,
    Grey.`) — eski iki satırın rengi ve teslimat adresi **kaybolmadı**.
