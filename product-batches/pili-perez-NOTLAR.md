# Calzados Pili Pérez — ayakkabı kataloğu içe aktarma notları

Durum: **fotoğraflar bekleniyor.** İçe aktarma başlamadı.

Bu dosya fiyat İÇERMEZ ve içermemeli. Bu depo herkese açık; Pili Pérez'in toptan
tarifesi üçüncü tarafa ait ticari sır ve sitede zaten yalnızca doğrulanmış
alıcılara gösteriliyor. Depoya konsaydı herkese açılırdı ve git geçmişinden
silinemezdi. Burada yalnızca YÖNTEM ve KARARLAR duruyor; ham dosya gerekince
satıcının e-postasından yeniden alınır.

## Kaynak

- `Catalogo_Pili_Perez_para_Vestra.xlsx`, tek sayfa `Catálogo Vestra`, 339 satır.
- Jose Illán'ın (Calzados Pili Pérez) e-postasıyla geldi, 23 Ağustos 2026.
- Sütunlar: Código · Descripción · Materiales · Tallas · Precio mayorista ·
  Pedido mínimo · Fotografías
- Aynı e-postada: *"en otro email le enviaremos las fotografías por wetransfer"*
  — fotoğrafları SATICI gönderiyor, bizim istememize gerek yok.

## Doğrulanmış olgu: fiyat çift başına

Kutulu 252 satırın 252'sinde `kutudaki çift sayısı × Precio mayorista` değeri,
satıcının kendi yazdığı `Total caja` rakamına birebir eşit — sıfır sapma. Yani
`Precio mayorista` ÇİFT başına fiyattır, `Pedido mínimo` ise kutudaki çift
sayısıdır. Bu bir varsayım değil, ölçüldü.

Eşleme: `list` = çift fiyatı, `moq` = kutudaki çift, `unit` = `pair`,
`tiers` = `[{min: moq, price: list}]`.

## Kararlar

1. **Fiyat Excel'den alınır, siteden değil.** piliperezalpormayor.com bir toptan
   sitesi; fiyatları üyelik arkasında olabilir ve başka bir müşteri kademesine
   ait olabilir. Excel satıcının VESTRA için yetkilendirdiği tarifedir.
2. **Fiyata komisyon EKLENMEZ.** Sistem 3,5%'i `commission.php` üzerinden
   satıcının kartından çekiyor. Listeye de eklemek toplamı ~%7 yapardı ve
   22 Ağustos'ta satıcıya İspanyolca yazdığımız *"Se cobra únicamente el 3,5 %"*
   cümlesini yalanlardı. Operatör kararı: liste = Excel fiyatı, olduğu gibi.
3. **Kutu toplamı satıcının yazdığı gibi taşınır.** `desc` alanına
   `Pedido mínimo: 1 caja (N pares) · Total caja: <satıcının yazdığı tutar>`
   biçiminde, Excel'deki metin kırpılmadan
   giriyor. Toptancının sorduğu asıl sayı "bir kutu kaça" olduğu için birim
   fiyatı gösterip bunu düşürmek bilgi kaybıydı.
4. **Bölme:** `section: footwear` (vitrindeki Ayakkabı sekmesi).

## Kategori: 183 hazır, 153 fotoğraf bekliyor

`cat` değeri Footwear grubunun yaprağı olmalı: Sneakers/Boots/Sandals/Heels/
Flats/Loafers/Slippers. `cat: "Footwear"` GEÇERSİZ — o bir grup adı.

İspanyolca açıklamadan çıkarılan tip (öncelik sırasıyla):

| ipucu | kategori |
|---|---|
| `zapatilla` + `casa` | Slippers |
| `botin`, `bota` | Boots |
| `nautico` | Loafers |
| `bailarina`, `mercedita` | Flats |
| `deportiv`, `respetuos`, `barefoot` | Sneakers |
| `colegial` | Flats |

Sıra önemli: *"Deportivo infantil respetuoso/barefoot"* iki ipucu birden taşıyor,
`deportivo` kazanır.

**153 üründe hiçbir ipucu yok** — açıklamaları yalnızca `Modelo 1012 (26-42)`.
Bunlar varsayılan bir kategoriye DOLDURULMADI. Doldurmak "başarılı" görünen ama
ürünleri sessizce yanlış sekmeye koyan bir sonuç üretirdi. Fotoğraflar gelince
kontakt sayfası çıkarılıp bakılarak sınıflandırılacak (hero filmi için kullanılan
yöntemin aynısı). Katalogda sandalet yok — `Sandals`/`Heels` beklenmiyor.

## Fotoğraf eşleştirme — gelince yapılacak

Eşleme Excel'in `Fotografías` sütununda hazır: her satır kendi dosya adlarını
listeliyor. 589 referans, 554 benzersiz dosya. Tuzaklar ölçüldü:

- Uzantı karışık: `.jpg` 462, `.jpeg` 104, `.png` 23 — "hepsi jpg" varsayan bir
  eşleştirici %21'ini kaçırır.
- 589 referansın 550'si karışık büyük/küçük harf; sunucu harf duyarlı.
- 4 dosya adında aksanlı karakter var; ZIP açılırken bozulabilir.
- Boşluk içeren dosya adı yok.
- **35 dosya adı birden fazla üründe geçiyor** (ör. `899-VARIOS.jpg` gibi
  "çeşitli renkler" çekimi kasıtlı olabilir; `8038-*` grubu şüpheli).
  Fotoğraflar gelince bakılıp karar verilecek.

Yapılacak: isim isim mutabakat (küçük harf + aksan sadeleştirme), sonra rapor —
kaç referans dosyasını buldu, kaçı bulamadı, ZIP'te olup Excel'de geçmeyen ne var.

`add-products.yml` görselin sunucuda GERÇEKTEN var olduğunu doğruluyor ve yoksa
ürünü eklemiyor; yani kırık görselli ürün girmesi mümkün değil.

## Satıcıya sorulacak üç şey

1. **`8029` kodu iki farklı üründe.** Biri *Modelo 8029 (26-42)*, Piel, yetişkin
   bedenleri; diğeri *Modelo 8029 (21-29)*, Piel, çocuk bedenleri — ve fiyatları
   yaklaşık üç kat farklı. Sipariş geldiğinde hangisi olduğu belirsiz kalacağı
   için İKİSİ DE dışarıda bırakıldı.
2. **`H90`** — `Fotografías` alanı tamamen boş. WeTransfer'de de karşılığı
   olmayabilir.
3. **Menşei (origin).** Excel'de yok. Faturada ve gümrük belgesinde görünen bir
   alan olduğu için TAHMİN EDİLMEDİ.

## Fiyat listesi nereye konacak (açık karar)

`add-products.yml` ürün dosyasını REPODAN okuyor. Bu katalog için iki seçenek:

- Depoya koymak (mevcut pratik) — ama tarife herkese açılır.
- Aracı, dosyayı sunucudan (`~/import/`, public_html DIŞI) okuyacak hale
  getirmek — küçük bir değişiklik, tarife depoya hiç girmez.

Fotoğraflar gelmeden içe aktarma çalışamayacağı için bu karar ertelendi.
