<?php
/** ChatHelp — dump-checkall (SADECE OKUR) — bu oturumdaki TUM isler tek tek
 *  dogrulanir: beklenen markerlar var mi, eski kalintilar gitti mi, kritik
 *  fonksiyon/veri yapilari yerinde mi, PHP sozdizimi saglam mi.
 *  Cikti: her satirda ✓ / ✗ — TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$idx=(string)@file_get_contents("$D/index.php");
$api=(string)@file_get_contents("$D/api.php");
$ok=0;$bad=0;
function chk($cond,$label){ global $ok,$bad; if($cond){$ok++;echo "  ✓ $label\n";} else {$bad++;echo "  ✗ $label  <-- SORUN\n";} }
function cnt($h,$n){ return substr_count($h,$n); }

echo "════ 0) Dosya saglik ════\n";
chk(strlen($idx)>1500000,"index.php okundu (".strlen($idx)." bayt)");
chk(strlen($api)>50000,"api.php okundu (".strlen($api)." bayt)");
$lo=[];$rc=0; exec('php -l '.escapeshellarg("$D/index.php").' 2>&1',$lo,$rc);
chk($rc===0,"index.php PHP lint temiz");
$lo=[];$rc=0; exec('php -l '.escapeshellarg("$D/api.php").' 2>&1',$lo,$rc);
chk($rc===0,"api.php PHP lint temiz");
chk(cnt($idx,'<script')===cnt($idx,'</script>'),"script etiketleri dengeli (".cnt($idx,'<script').")");

echo "\n════ 1) Kart stabilitesi (flicker fix) ════\n";
chk(cnt($idx,'CH_STAB_CALM')===2,"CH_STAB_CALM: 2 gozlemci yamali (".cnt($idx,'CH_STAB_CALM').")");
chk(cnt($idx,'if(++R.n<=3) resettle()')===0,"eski mutasyon->resettle kodu kalmadi");

echo "\n════ 2) Zen: tam temizlik, oturumluk ════\n";
chk(cnt($idx,'CH_ZEN_FULL')===1,"CH_ZEN_FULL css aktif");
chk(cnt($idx,'CH_ZEN_CARDSONLY')===0,"eski kartlar-gizle css kalmadi");
chk(cnt($idx,'CH_ZEN_NOPERSIST')===2,"zen kaliciligi kapali (2 yama)");
chk(cnt($idx,'"Sadece sohbet"')>=1,"dugme basligi 'Sadece sohbet'");
chk(cnt($idx,'"Kartlari gizle/goster"')===0,"eski baslik kalmadi");
chk(strpos($idx,'body.ch-zen #sb')!==false,"zen sidebar'i da gizliyor");

echo "\n════ 3) Menu temizligi (moduller opt-in) ════\n";
chk(cnt($idx,'m[k]===undefined?false')===4,"modOn varsayilani KAPALI (4 kopya) (".cnt($idx,'m[k]===undefined?false').")");
chk(cnt($idx,'m[k]===undefined?true')===0,"eski ACIK varsayilan kalmadi");
chk(strpos($idx,'#nav-trvisa,#nav-vsf,#nav-vdos,#nav-dev,#nav-plans{display:none !important}')!==false,"hub-disi modul navlari kalici gizli");

echo "\n════ 4) Doc-Dev v3 ════\n";
chk(cnt($idx,'CH_DEVFIX2')>=2,"CH_DEVFIX2 bundle yerinde (".cnt($idx,'CH_DEVFIX2').")");
chk(cnt($idx,'id="ch-docdev-js"')===1,"docdev script TEK kopya");
chk(strpos($idx,'typeof plan==="string" && plan && plan!=="free"')!==false,"plan tespiti global degiskenden (Elite fix)");
chk(strpos($idx,'dv-locked')!==false,"paketsiz icin bulanik kilit var");
chk(strpos($idx,'window.stripeCheckout(null,"devdoc",tk[0])')!==false,"kilit butonu tek-belge Stripe akisina bagli");
chk(strpos($idx,'["f1","f2","f3","f4","f5","f7"]')!==false,"profil (ad/tel/mail) belgeye gecer");
chk(strpos($idx,'host.insertBefore(c,hero.nextSibling)')!==false,"Doc-Dev karti hero altina (en uste)");
chk(strpos($idx,'#ch-plans-cta{display:none !important}')!==false,"'Premium entdecken' karti gizli");
chk(strpos($idx,'"einfach"')!==false&&strpos($idx,'"komplex"')!==false,"fiyat katmani anahtarlari (einfach/komplex) mevcut");
chk(cnt($idx,'DOC_PRICES')>=1,"DOC_PRICES tek-belge fiyat haritasi yerinde");

echo "\n════ 5) Sozlesmeler (DE+TR) ════\n";
chk(cnt($idx,'CH_VST_MORE')>=1,"8 Alman sozlesmesi (vst-more) yerinde");
chk(cnt($idx,'ncxBuild')>=2,"ncxBuild motoru yerinde (".cnt($idx,'ncxBuild').")");
chk(cnt($idx,'CH_VST_TR')>=1,"TR sozlesme paketi yerinde");
chk(cnt($idx,'vtr_tr_bosanma')>=2,"Anlasmali Bosanma (CT+NCX) tanimli (".cnt($idx,'vtr_tr_bosanma').")");
chk(cnt($idx,'vtr_tr_kira')>=2 && cnt($idx,'vtr_tr_is')>=2 && cnt($idx,'vtr_tr_arac_satis')>=2 && cnt($idx,'vtr_tr_ibraname')>=2,"diger 4 TR sozlesmesi tanimli");
chk(strpos($idx,'Kendi Maddeleriniz')!==false,"TR sozlesmelerde ozel madde adimi var");
chk(strpos($idx,'data-chcc="TR"')!==false,"TR'de Vertrag Studio gorunurlugu acik");

echo "\n════ 6) Sohbet cipi + PDF yonlendirme ════\n";
chk(cnt($idx,'CH_SUGG_FIX')===1,"cip duzeltmesi yerinde");
chk(strpos($idx,'action=detect')!==false,"cip -> detect -> sablon akisi bagli");
chk(strpos($api,'[[DOC]] format')!==false,"api: acik PDF isteginde sohbet-ici [[DOC]] uretimi");
chk(cnt($api,'CH_AIROUTE')>=1,"api: belge niyetinde Claude yonlendirmesi");

echo "\n════ 7) Plan/kota (onceki fazdan) ════\n";
chk(strpos($idx,'docs:40')!==false&&strpos($idx,'docs:200')!==false&&strpos($idx,'docs:1000')!==false,"kotalar 40/200/1000");
chk(cnt($idx,'CH_TONE_OPEN')>=1,"uslup secici herkese acik");
chk(cnt($idx,'ch-planpage-js')===1,"plan sayfasi (tek kopya)");
chk(cnt($idx,'ch-falltopics-js')===1,"otomatik konu (Fall) sistemi (tek kopya)");

echo "\n════ SONUC ════\n";
echo $bad===0 ? "✅ HER SEY TAMAM — $ok kontrol, 0 sorun.\n"
             : "⚠️ $bad SORUN var ($ok saglam) — yukaridaki ✗ satirlarini gonder.\n";
