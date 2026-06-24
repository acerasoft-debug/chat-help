# App-Icon & Splash (resources/)

Buraya iki kaynak görsel koy, gerisi otomatik üretilir:

| Dosya | Boyut | Açıklama |
|---|---|---|
| `icon.png`   | **1024×1024** | App ikonu (kenar boşluksuz, kare). Saydam zemin OLMASIN (Play için dolu zemin). |
| `splash.png` | **2732×2732** | Açılış ekranı (logo ortada, koyu zemin `#15151b`). |

Sonra `mobile/` içinde:

```bash
npx @capacitor/assets generate --android
```

Bu komut tüm Android ikon/splash boyutlarını (mdpi…xxxhdpi, adaptive icon, splash) otomatik üretir
ve `android/app/src/main/res/` altına yerleştirir.

İpucu: Logon yoksa, geçici olarak ⚖️ + "ChatHelp" yazılı koyu/altın bir kare (1024×1024) bile yeterli;
sonra gerçek logoyla değiştirip komutu tekrar çalıştırırsın.
