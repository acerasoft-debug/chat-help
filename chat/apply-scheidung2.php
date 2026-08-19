<?php
/**
 * ChatHelp — apply-scheidung2 (CH_SCHEID) — Vertrag Studio'ya "Anlaşmalı Boşanma Protokolü"
 *  (TMK m.166/3) MODULU. Opsiyonlu klozlar -> dolu olmayan kloz belgeye GIRMEZ (eklemeli).
 *  Canli yapiya gore DOGRU anchor'lar (dump-vst ile bulundu):
 *   [1] CT kapanisi: benzersiz '      ]}\n  };' (tam 1) -> scheidung girisi.
 *   [2] buildPages sonu: '    }\n    var n=P.length, doc=CT[key].n;' -> scheidung dali.
 *  2 sayac-korumali str_replace (her biri ==1). Eslesme yoksa HICBIR sey degistirilmez.
 * KULLANIM: pull2.php?key=...&files=apply-scheidung2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-scheidung2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_VST')===false) exit("HATA: Vertrag Studio (CH_VST) yok.\n");
if(strpos($src,'CH_SCHEID')!==false) exit("Zaten ekli (CH_SCHEID).\n");

$fail=[];

/* ── [1] CT kataloguna scheidung girisi (benzersiz CT kapanisi) ── */
$oldCT = "      ]}\n  };";
$newCT = "      ]},\n"
  ."    scheidung:{ic:\"⚖️\",n:\"Anlaşmalı Boşanma Protokolü\",sn:\"TMK m.166/3 · anlaşmalı\",price:\"4,99 €\",pgs:3,/*CH_SCHEID*/\n"
  ."      steps:[\n"
  ."        {t:\"1. Sayfa · Taraflar & Evlilik\",f:[\n"
  ."          [\"es1_ad\",\"Eş 1 — Ad Soyad, T.C. Kimlik No\"],[\"es1_adr\",\"Eş 1 — Adres\"],\n"
  ."          [\"es2_ad\",\"Eş 2 — Ad Soyad, T.C. Kimlik No\"],[\"es2_adr\",\"Eş 2 — Adres\"],\n"
  ."          [\"evlilik_tarih\",\"Evlilik Tarihi\"],[\"evlilik_yer\",\"Evlenme Yeri (İl/İlçe)\"],\n"
  ."          [\"mahkeme\",\"Yetkili Aile Mahkemesi (İl)\"]]},\n"
  ."        {t:\"2. Sayfa · Çocuklar, Velayet & Nafaka (opsiyonel)\",f:[\n"
  ."          [\"cocuklar\",\"Ortak Çocuklar (Ad / Doğum tarihi) — yoksa boş bırak\"],\n"
  ."          [\"velayet\",\"Velayet kime bırakılıyor (opsiyonel)\"],\n"
  ."          [\"kisisel_iliski\",\"Kişisel ilişki günleri (opsiyonel)\"],\n"
  ."          [\"istirak_nafaka\",\"İştirak nafakası — aylık ₺ (opsiyonel)\"],\n"
  ."          [\"yoksulluk_nafaka\",\"Yoksulluk nafakası — aylık ₺ (opsiyonel)\"]]},\n"
  ."        {t:\"3. Sayfa · Mal Paylaşımı, Diğer & İmza\",f:[\n"
  ."          [\"mal_paylasim\",\"Mal rejimi tasfiyesi (opsiyonel)\"],\n"
  ."          [\"ziynet\",\"Ziynet / ev eşyası paylaşımı (opsiyonel)\"],\n"
  ."          [\"tazminat\",\"Maddi / manevi tazminat (opsiyonel)\"],\n"
  ."          [\"soyadi\",\"Kadının soyadı düzenlemesi (opsiyonel)\"],\n"
  ."          [\"diger\",\"Diğer anlaşma maddeleri (opsiyonel)\"],\n"
  ."          [\"yer\",\"Düzenlenme Yeri\"],[\"tarih\",\"Tarih\"]]}\n"
  ."      ]}\n  };";
$c1=substr_count($src,$oldCT);
if($c1===1){ $src=str_replace($oldCT,$newCT,$src); echo "  ✓ [1] CT katalogu: scheidung girisi eklendi (17. kart)\n"; }
else { echo "  ✗ [1] CT anchor ($c1, 1 beklenir)\n"; $fail[]=1; }

/* ── [2] buildPages'e scheidung dali (opsiyonlu §§ = eklemeli) ── */
$oldBP = "    }\n    var n=P.length, doc=CT[key].n;";
$newBP = "    } else if(key===\"scheidung\"){/*CH_SCHEID*/\n"
  ."      var m=0; var MZ=function(){ return \"Madde \"+(++m); };\n"
  ."      P.push(head(\"ANLAŞMALI BOŞANMA PROTOKOLÜ\",\"Türk Medeni Kanunu m. 166/3\")\n"
  ."        +par(MZ(),\"Taraflar\",\"İşbu protokol, bir tarafta \"+V(d.es1_ad)+\" (Adres: \"+V(d.es1_adr)+\") ile diğer tarafta \"+V(d.es2_ad)+\" (Adres: \"+V(d.es2_adr)+\") arasında, aşağıdaki şartlarla anlaşmalı boşanma hususunda serbest iradeleriyle düzenlenmiştir.\")\n"
  ."        +par(MZ(),\"Evlilik Birliği\",\"Taraflar \"+V(d.evlilik_tarih)+\" tarihinde \"+V(d.evlilik_yer)+\" yerinde evlenmişlerdir. Evlilik birliği temelinden sarsılmış olup, taraflar Türk Medeni Kanunu'nun 166/3. maddesi uyarınca anlaşmalı olarak boşanmayı kabul ve beyan ederler. İşbu protokol \"+V(d.mahkeme,\"____\")+\" Aile Mahkemesi'ne sunulmak üzere iki nüsha düzenlenmiştir.\"));\n"
  ."      var pg2=\"\";\n"
  ."      if(d.cocuklar){ pg2+=par(MZ(),\"Müşterek Çocuklar ve Velayet\",\"Tarafların müşterek çocuğu/çocukları: \"+V(d.cocuklar)+\".\"+(d.velayet?(\" Çocuğun/çocukların velayeti \"+V(d.velayet)+\" tarafına bırakılmıştır.\"):\" Velayet hususunda taraflar mahkeme takdirine uygun şekilde anlaşmıştır.\")); }\n"
  ."      if(d.kisisel_iliski){ pg2+=par(MZ(),\"Kişisel İlişki\",\"Velayet kendisine bırakılmayan taraf ile çocuk/çocuklar arasında kişisel ilişki şu şekilde düzenlenmiştir: \"+V(d.kisisel_iliski)+\".\"); }\n"
  ."      if(d.istirak_nafaka){ pg2+=par(MZ(),\"İştirak Nafakası\",\"Velayet kendisine bırakılmayan taraf, müşterek çocuk için aylık \"+V(d.istirak_nafaka)+\" tutarında iştirak nafakasını her ayın ilk haftasında ödemeyi kabul eder. Nafaka her yıl ÜFE oranında artırılır (TMK m. 182).\"); }\n"
  ."      if(d.yoksulluk_nafaka){ pg2+=par(MZ(),\"Yoksulluk Nafakası\",\"Taraflardan biri diğerine aylık \"+V(d.yoksulluk_nafaka)+\" tutarında yoksulluk nafakası ödemeyi kabul eder (TMK m. 175).\"); }\n"
  ."      if(!pg2){ pg2=par(MZ(),\"Çocuklar ve Nafaka\",\"Tarafların müşterek çocuğu bulunmamaktadır. Taraflar birbirlerinden iştirak veya yoksulluk nafakası talep etmemektedir.\"); }\n"
  ."      P.push(pg2);\n"
  ."      var pg3=\"\";\n"
  ."      if(d.mal_paylasim){ pg3+=par(MZ(),\"Mal Rejiminin Tasfiyesi\",\"Tarafların mal rejiminin tasfiyesine ilişkin anlaşması: \"+V(d.mal_paylasim)+\". Taraflar bunun dışında birbirlerinden mal rejiminden kaynaklanan başka bir talepte bulunmayacaklardır.\"); }\n"
  ."      if(d.ziynet){ pg3+=par(MZ(),\"Ziynet ve Ev Eşyası\",\"Ziynet eşyaları ve ev eşyasının paylaşımı: \"+V(d.ziynet)+\".\"); }\n"
  ."      if(d.tazminat){ pg3+=par(MZ(),\"Maddi ve Manevi Tazminat\",\"Taraflar tazminat hususunda şu şekilde anlaşmıştır: \"+V(d.tazminat)+\" (TMK m. 174).\"); }\n"
  ."      if(d.soyadi){ pg3+=par(MZ(),\"Kadının Soyadı\",\"Boşanmadan sonra kadının kullanacağı soyadına ilişkin düzenleme: \"+V(d.soyadi)+\" (TMK m. 173).\"); }\n"
  ."      if(d.diger){ pg3+=par(MZ(),\"Diğer Hükümler\",esc(d.diger)); }\n"
  ."      pg3+=par(MZ(),\"Genel Hükümler ve Beyan\",\"Taraflar işbu protokolü serbest iradeleriyle, herhangi bir baskı veya zorlama olmaksızın düzenlemiştir. Protokol, mahkemece onaylanması hâlinde kesin ve bağlayıcıdır. Taraflar boşanmanın malî sonuçları ile çocukların durumu hakkında yukarıdaki düzenlemelerde anlaştıklarını beyan ederler.\")\n"
  ."        +sig2(d,\"Eş 1 (İmza)\",\"Eş 2 (İmza)\",d.yer,d.tarih);\n"
  ."      P.push(pg3);\n"
  ."    }\n    var n=P.length, doc=CT[key].n;";
$c2=substr_count($src,$oldBP);
if($c2===1){ $src=str_replace($oldBP,$newBP,$src); echo "  ✓ [2] buildPages: scheidung dali eklendi (opsiyonlu §§ = eklemeli)\n"; }
else { echo "  ✗ [2] buildPages anchor ($c2, 1 beklenir)\n"; $fail[]=2; }

if($fail){ echo "\nHATA: eslesmeyen adim(lar): ".implode(', ',$fail)." — HICBIR sey degistirilmedi.\n"; exit; }

$tmp=tempnam(sys_get_temp_dir(),'s2').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-scheid2-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_SCHEID')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_SCHEID diskte (".strlen($chk)." B)\n";
echo "  Test: Verträge modulu -> '⚖️ Anlaşmalı Boşanma Protokolü' -> 3 sayfa; opsiyonel bos alanlar belgede CIKMAZ.\n";
