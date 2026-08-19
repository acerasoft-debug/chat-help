<?php
/**
 * ChatHelp — apply-nocrossuser (CH_NOCROSS) — KRITIK gizlilik: hesap degisiminde
 *  (ch_bound_uid uyusmazligi) purge() calisiyor ama 'ch_profiles' (coklu-profil
 *  dizisi — Pro 5/Elite sinirsiz, isim+email icerir) TEMIZLENMIYORDU. Eski
 *  kullanicinin (ornek: Bulent Duru) kayitli profili cihazda kalip yeni hesaba
 *  (Melissa) sizabiliyordu (belgede yanlis isim/email, adres dogru cikan bug).
 *  COZUM: purge() temizlik listesine 'ch_profiles' eklenir — hesap degisince
 *  TUM coklu-profil verisi silinir, dogru hesap icin sunucudan taze cekilir
 *  (pullProfileFromServer zaten var — veri kaybi yok, sadece cihaz senkron olur).
 *  1 sayac-korumali str_replace. Eslesmezse HICBIR sey degistirilmez.
 * KULLANIM: pull2.php?key=...&files=apply-nocrossuser.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-nocrossuser BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_NOCROSS')!==false) exit("Zaten ekli (CH_NOCROSS).\n");

$fail=[];

/* [1] purge() listesine ch_profiles ekle (gelecekteki hesap-degisimlerini onler) */
$o1 = "['ch_prof_v3','ch_prof_active','ch_credits','CH_FREE_AVAIL','ch_lastdoc'].forEach(function(k){ try{ localStorage.removeItem(k); }catch(e){} });";
$n1 = "['ch_prof_v3','ch_prof_active','ch_credits','CH_FREE_AVAIL','ch_lastdoc','ch_profiles'].forEach(function(k){ try{ localStorage.removeItem(k); }catch(e){} });/*CH_NOCROSS*/";
$c1=substr_count($src,$o1);
if($c1===1){ $src=str_replace($o1,$n1,$src); echo "  ✓ [1] purge() listesine 'ch_profiles' eklendi\n"; }
else { echo "  ✗ [1] anchor ($c1, 1 beklenir)\n"; $fail[]=1; }

/* [2] doLogout() her cikista profil verisini + bound-uid'i sifirlar (mevcut kirli veriyi de temizler) */
$o2 = "function doLogout(){\n  localStorage.removeItem('ch_token');\n  localStorage.removeItem('ch_user');\n  P.plan='free'; P.f1=''; P.f2=''; P.f7='';";
$n2 = "function doLogout(){\n  localStorage.removeItem('ch_token');\n  localStorage.removeItem('ch_user');\n  ['ch_prof_v3','ch_prof_active','ch_credits','CH_FREE_AVAIL','ch_lastdoc','ch_profiles','ch_bound_uid'].forEach(function(k){ try{ localStorage.removeItem(k); }catch(e){} });/*CH_NOCROSS*/\n  P.plan='free'; P.f1=''; P.f2=''; P.f7='';";
$c2=substr_count($src,$o2);
if($c2===1){ $src=str_replace($o2,$n2,$src); echo "  ✓ [2] doLogout() her cikista profil+bound-uid sifirlar (mevcut kirli veri de temizlenir)\n"; }
else { echo "  ✗ [2] anchor ($c2, 1 beklenir)\n"; $fail[]=2; }

if($fail){ echo "\nHATA: eslesmeyen adim(lar): ".implode(', ',$fail)." — HICBIR sey degistirilmedi.\n"; exit; }

$tmp=tempnam(sys_get_temp_dir(),'nc').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-nocross-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_NOCROSS')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_NOCROSS diskte (".strlen($chk)." B)\n";
echo "  ONEMLI ADIM: Melissa'nin cihazinda ZATEN olusmus kirli veriyi silmek icin\n";
echo "  BIR KEZ 'Ausloggen/Cikis Yap' yapip tekrar giris yapmasi yeterli — artik\n";
echo "  doLogout() coklu-profil + bound-uid verisini komple sifirliyor.\n";
echo "  Sonraki HER hesap degisiminde (cikis-giris VEYA farkli hesap girisi) bug\n";
echo "  bir daha OLUSMAZ.\n";
