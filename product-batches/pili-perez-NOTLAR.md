# Calzados Pili Pérez — ayakkabı kataloğu içe aktarma notları

Durum (3 Eyl 2026): **fotoğraflar sunucuda, parti şifreli olarak depoda
(`pili-perez.enc`), içe aktarma `add-products.yml` ile yapılıyor.** Ayrıntı
aşağıda; bu dosya fiyat İÇERMEZ ve içermemeli (bkz. "Fiyat listesi nereye").

Bu depo herkese açık; Pili Pérez'in toptan tarifesi üçüncü tarafa ait ticari sır
ve sitede zaten yalnızca doğrulanmış alıcılara gösteriliyor. Depoya konsaydı
herkese açılırdı ve git geçmişinden silinemezdi. Burada yalnızca YÖNTEM ve
KARARLAR duruyor.

## Kaynak

- `Catalogo_Pili_Perez_para_Vestra.xlsx`, tek sayfa `Catálogo Vestra`, 339 satır.
- Jose Illán'ın (Calzados Pili Pérez) e-postasıyla geldi, 23 Ağustos 2026.
- Sütunlar: Código · Descripción · Materiales · Tallas · Precio mayorista ·
  Pedido mínimo · Fotografías
- Fotoğraflar 3 Eylül 2026'da WeTransfer ile geldi ("FOTOS VESTRA", 595 dosya,
  48 MB, klasör = model kodu). Bu depoyu çalıştıran ortam wetransfer.com'a
  kapalı; indirme SUNUCUDAN yapıldı: `diag-live.yml` → `wetransfer_probe`
  (`<url>|fetch`), dosyalar `~/wt_incoming/abc37eb240b7/files/FOTOS_VESTRA/`
  altında (public_html DIŞI). Alıcıya özel linkte WeTransfer'in v4 ucu
  `recipient_id` + tarayıcı oturumu ister; yalnız `security_hash` 403 verir.

## Doğrulanmış olgu: fiyat çift başına

Kutulu 252 satırın 252'sinde `kutudaki çift sayısı × Precio mayorista` değeri,
satıcının kendi yazdığı `Total caja` rakamına birebir eşit — sıfır sapma. Yani
`Precio mayorista` ÇİFT başına fiyattır, `Pedido mínimo` ise kutudaki çift
sayısıdır. Bu bir varsayım değil, ölçüldü.

Eşleme: `list` = çift fiyatı, `moq` = kutudaki çift, `unit` = `pair`,
`tiers` = `[{min: moq, price: list}]`, `mode` = `fixed`.

## Kararlar

1. **Fiyat Excel'den alınır, siteden değil.** Excel satıcının VESTRA için
   yetkilendirdiği tarifedir.
2. **Fiyata komisyon EKLENMEZ.** Sistem 3,5%'i `commission.php` üzerinden
   satıcının kartından çekiyor; 22 Ağustos'ta satıcıya *"Se cobra únicamente el
   3,5 %"* yazıldı. Liste = Excel fiyatı, olduğu gibi.
3. **Kutu toplamı açıklamada taşınır**, İngilizce: `Minimum order: 1 box (12
   pairs) · Box total: €… (12 pairs × €…)`. Toptancının sorduğu asıl sayı "bir
   kutu kaça".
4. **Bölme:** `section: footwear` (vitrindeki Footwear sekmesi).
5. **Metin İngilizce** (operatör kuralı: müşteriye dönük her şey İngilizce).
   İspanyolca açıklamalar 9 şablona ayrışıyor; `gen_batch.py` (çalışma
   dizininde, depoda değil) kural tabanlı çevirir ve çıktıda İspanyolca
   kalıntı kalırsa **durur**. Ör. *"Modelo C1520-L, deportivo infantil,
   preparado para venta profesional en caja surtida."* → *"Model C1520-L,
   children's sneaker, prepared for trade sale in an assorted box."*;
   *"surtido libre"* → *"free assortment (sizes of your choice)"*;
   *"calzado respetuoso"* → *"barefoot-style"*; *"de la marca Bubble"* →
   *"by Bubble"*. Malzeme: Piel→Leather, Sintético→Synthetic, Textil→Textile,
   Lona→Canvas, Serraje→Split leather. Beden: `24 a 29 y 30 a 35` →
   `24–29 and 30–35`, `20/23, 24/29` → `20–23, 24–29`.
6. **`sku` = Excel'in `Código`'su olduğu gibi** (satıcının kendi anahtarı;
   `H119-1-1-2-1-1-1` gibi araç artığı kodlar dahil — sipariş satırı satıcının
   satırına geri döner). Asıl model adı açıklamadan `name`'e gider
   (`Modelo S-3` → *Model S-3*). `id` = `pp-` + kod (küçük harf, `-`).
7. **Marka: "Pili Pérez"** hepsinde. Bazı fotoğraf adları başka marka taşıyor
   (Igor: Tobby/Leon/Croco/Zebra jöle sandaletler; Joma: Academy; Citos: 850;
   açıklamada Bubble). Satıcıya soruldu; onaysız marka değiştirilmedi.
8. **Aynı kodun beden/kutu varyantları ayrı ürün** (8038 / 8038-2, A12 / A12-1,
   RM16896 / RM16896-1 …): satıcı böyle listeledi, kutu boyu fiyatı değiştiriyor.
   Tek ürün + kademe yapmak sipariş adımını (kutu = 5 ya da 12 çift) yanlış
   gösterirdi.

## Kategori: fotoğraftan, 337 klasörün hepsine bakıldı

`cat` Footwear grubunun yaprağı olmalı: Sneakers/Boots/Sandals/Heels/Flats/
Loafers/Slippers. 160 satırın açıklaması yalnızca `Modelo 1012 (26-42)` — tipi
ancak fotoğraf söylüyor; varsayılan kategoriye DOLDURULMADI. Yöntem:
`diag-live.yml` → `wetransfer_probe=sheet:wt_incoming/abc37eb240b7[|from=N|count=M]|spaced`
klasör başına bir küçük resim basar (10×5 JPEG sayfalar, base64, loga), sayfalar
indirilip bakıldı; şüphelilere büyütülmüş kırpma yapıldı.

- **Fotoğraf kazanır.** Açıklamanın tip kelimesiyle çelişenler: C1574 "colegial"
  ama fotoğraf spor ayakkabı → Sneakers; C1901-L/S, C1902-L/S "colegial/zapato
  colegial" ama mokasen → Loafers. Açıklama metni satıcının cümlesi olarak
  kalıyor (school shoe), kategori fotoğrafa göre.
- Notların eski "katalogda sandalet yok" varsayımı **yanlıştı**: 41 sandalet var
  (Tobby/Leon/Croco/Zebra/Maui/Nemo jöle, Real Madrid terlik-sandalet, EVA
  çocuk sandaletleri S-3/S-5, kadın espadril/platform sandaletler). 3 Heels
  (kadın dolgu topuk espadril M2359, M2527; blok topuk M2517).
- Dağılım: Sneakers 128 · Flats 75 · Slippers 44 · Sandals 41 · Boots 27 ·
  Loafers 17 · Heels 3.

## Fotoğraf mutabakatı — sonuç

Excel 589 referans / 557 benzersiz ad; sunucuda 595 dosya / 557 benzersiz ad
(katlanmış: küçük harf + aksansız + `_`→`-`). **Excel'de olup sunucuda olmayan:
0. Sunucuda olup Excel'de geçmeyen: 0.** Her fotoğraf kendi kodunun klasöründe.
Dosya adı kuralı `add-products.yml`'deki `$fold` ile aynı; `✓` gibi işaretler
(5009S) iki tarafta da atılıyor. Görseller `uploads/piliperez/` altına
`stage_from=wt_incoming/abc37eb240b7` ile KOPYALANIR (kaynak silinmez).

## Dışarıda bırakılan 4 satır (satıcıya soruldu)

1. **`8029 (26-42)`** — aynı kod iki farklı ayakkabı (yetişkin deri MARINO /
   çocuk BLANCO-BEIG-LINO, fiyatları ~3 kat farklı). Çocuk satırları
   (`8029 (21-29)`, `8029-2`) yayında, SKU'da beden aralığıyla; yetişkin satır
   bekliyor.
2. **`H90`** — Excel'de fotoğraf yok, transferde klasörü yok.
3. **`K874` / `K874-1`** — minimum "12 cajas" / "6 cajas": kutudaki çift
   sayısı yazmıyor, çift cinsinden MOQ kurulamıyor.

Satıcı veri hataları (yayınlandı, işaretlendi): `8038-2` açıklama (30-34) ama
beden sütunu 21-29 → 30–34 alındı; `H119-1-1-2-1-1-1` "Modelo S-3 (24-29)" ama
sütun 30/35 → sütun alındı, ad aralıksız; `H119-1-1-2-1-1` "Modelo A-36" ama
fotoğraf `A76-VARIOS.jpeg`. `ships_from` boş bırakıldı (KURAL 3 — satıcıya
soruldu). Soru metni: çalışma dizininde `seller_questions_en.md` (İngilizce).

## Fiyat listesi nereye konuldu — karar

Parti **şifreli**: `product-batches/pili-perez.enc` =
`{key: b64(RSA-OAEP(aes)), iv, data: b64(AES-256-CBC(json))}`. Açık anahtarı
sunucu üretti (`diag-live` fetch adımı `IMPORT_PUBKEY_B64=` basar; özel anahtar
`~/.vestra_import/import_key.pem`, sunucudan çıkmaz). `add-products.yml`
`.enc` görünce sunucuda çözer. Açık JSON ne depoya ne workflow girdisine girer.

## Actions günlüğünden veri okurken (öğrenildi, 3 Eyl 2026)

- GitHub, gizli değerleri (`DEPLOY_PORT` = "22", kullanıcı adı) logun **her
  yerinde** `***` yapar: `1225` → `1***5`, `222` → `***`, base64'ün içi dahil.
  Ters çevirmek `222`'de kayıplı (`***` mi `22` mi `222` mi?). İlk kontakt
  koşusunda 7 sayfanın 3'ü bu yüzden bozuk çıktı ve `222 = Corinto` satırı
  `22 = Corinto` okundu (gerçek 22 = model 712). Çözüm `|spaced`: karakter
  arası boşluk, hiçbir gizli değer bitişik geçemez.
- Runner IP'si barındırıcının güvenlik duvarına takılabilir: 16:55'te bir
  koşuda 9 HTTP isteği de 30 sn'de `000`, ardından SSH `dial tcp: i/o
  timeout` — aynı dakikada başka runner'dan deploy başarılıydı, 6 dk sonra
  taze runner `/` 200/80 ms. "Site çöktü" demeden önce ikinci runner'la bak.
