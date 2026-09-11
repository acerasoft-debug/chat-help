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
- Test: `tests/msg_read_receipt_test.php` (57 iddia, iki yön). Düşebildiği
  doğrulandı: `by` atlaması kaldırılınca **3 kırmızı**, bir çağrı yerinden
  aktör düşünce **1**, kırpma kalkınca **2**, yoklama eski hâline dönünce
  **2**, Support muhafazası kalkınca **1**. *Her sabotajın GERÇEKTEN
  uygulandığı ayrıca yazdırıldı — bu oturumda bir sabotaj sessizce hiç
  uygulanmamış ve testi sağlam göstermişti.*

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
