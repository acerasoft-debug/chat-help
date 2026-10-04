<?php
/**
 * ChatHelp — dump-fake (SALT-OKUR) — "dilekcede UYDURMA veri" sorunu.
 *
 * SIKAYET: Belgede e-posta adresi UYDURULMUS (kullanicinin gercek adresi
 *  Konto'da kayitli), adresler de gercek olmali. Dilekcelerde hicbir uydurma
 *  bilgi olmamali.
 *
 * Bu dump SADECE OKUR, hicbir sey degistirmez. Cevaplamasi gerekenler:
 *  [1] Belge uretiminde AI'ye giden PROMPT nasil kuruluyor (doGenerate)
 *  [2] Kullanicinin GERCEK profili (ad/adres/e-posta) prompt'a giriyor mu,
 *      giriyorsa hangi kaynaktan (oturum / DB / istemciden gelen JSON)
 *  [3] Sunucuda oturumdaki gercek e-posta nerede duruyor (Konto kaydi)
 *  [4] Prompt'ta "uydurma" yasagi var mi ("erfinde", "Platzhalter", "nicht
 *      erfinden") — yoksa AI serbestce Mustermann/@example.com uyduruyordur
 *  [5] Ciktida tipik uydurma izleri (Mustermann, example.com, musterstadt…)
 *  [6] Istemci (index.php) api.php'ye profili gonderiyor mu
 *
 * KULLANIM: /chat/pull2.php?key=...&files=dump-fake.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR|E_PARSE);
$dir=__DIR__;
echo "== dump-fake ==\n\n";

function fx($s){ return preg_replace('/\s+/',' ',$s); }

function hits($label,$s,$keys,$pre=140,$len=340,$max=3){
  echo "\n──── $label ────\n";
  foreach($keys as $k){
    $c=substr_count($s,$k);
    if(!$c) continue;
    echo "  [".str_pad($k,24)."] x$c\n";
    $off=0;$n=0;
    while(($p=strpos($s,$k,$off))!==false && $n<$max){
      echo "     @$p ...".fx(substr($s,max(0,$p-$pre),$len))."...\n";
      $off=$p+strlen($k); $n++;
    }
    echo "\n";
  }
}

/* ─────────── api.php ─────────── */
$af=$dir.'/api.php';
$api=(string)@file_get_contents($af);
if($api===''){ echo "✗ api.php okunamadi\n"; }
else{
  echo "api.php : ".strlen($api)." B  (".date('Y-m-d H:i',filemtime($af)).")\n";

  echo "\n=== [1] doGenerate() — PROMPT kurulumunun basi (ilk 5000 karakter) ===\n";
  $p=strpos($api,'function doGenerate');
  if($p===false) echo "  ✗ doGenerate YOK\n";
  else echo substr($api,$p,5000)."\n";

  echo "\n=== [2] Sistem/rol prompt metinleri ===\n";
  hits('prompt anahtarlari',$api,
    ['$sys','$system','system_prompt','"system"',"'system'",'$prompt .=','$prompt.=','$instr','ANWEISUNG','Regeln:','WICHTIG'],140,420,3);

  echo "\n=== [3] Profil / kimlik verisi prompt'a giriyor mu ===\n";
  hits('profil alanlari',$api,
    ['profile','profil','absender','Absender','$email','E-Mail:','vorname','nachname','strasse','straße','plz','ort','anschrift','sender_'],140,340,3);

  echo "\n=== [4] Oturum / DB'den gercek e-posta ===\n";
  hits('oturum & DB',$api,
    ['$_SESSION','session_start','users','SELECT','email','user_email','account'],120,300,3);

  echo "\n=== [5] UYDURMA YASAGI prompt'ta var mi ===\n";
  foreach(['erfinde','Erfinde','erfunden','nicht erfinden','Platzhalter','platzhalter',
           'keine erfundenen','frei erfunden','ausdenken','fiktiv','Beispieldaten'] as $k){
    printf("  %-20s x%d\n",$k,substr_count($api,$k));
  }

  echo "\n=== [6] Tipik uydurma izleri (kod icinde sabit ornek veri) ===\n";
  foreach(['Mustermann','Musterstadt','Musterstr','example.com','beispiel','@email.de',
           'max.','test@','muster'] as $k){
    $c=substr_count($api,$k);
    printf("  %-20s x%d\n",$k,$c);
    if($c){ $q=strpos($api,$k); echo "        ...".fx(substr($api,max(0,$q-160),360))."...\n"; }
  }

  echo "\n=== [7] CH_DOC_POLISH temizlik blogu (mevcut hal) ===\n";
  $q=strpos($api,'CH_DOC_POLISH');
  echo $q!==false ? substr($api,max(0,$q-400),2600)."\n" : "  (YOK)\n";
}

/* ─────────── index.php ─────────── */
$xf=$dir.'/index.php';
$idx=(string)@file_get_contents($xf);
echo "\n\n================================================\n";
if($idx===''){ echo "✗ index.php okunamadi\n"; }
else{
  echo "index.php : ".strlen($idx)." B  (".date('Y-m-d H:i',filemtime($xf)).")\n";

  echo "\n=== [8] Istemci profili nerede tutuluyor (localStorage anahtarlari) ===\n";
  foreach(['ch_prof_v3','ch_profile','ch_profiles','ch_addr','ch_address','ch_mailto',
           'ch_user','ch_konto','ch_sender','ch_absender','ch_gapvals'] as $k){
    printf("  %-16s x%d\n",$k,substr_count($idx,$k));
  }

  echo "\n=== [9] api.php'ye 'generate' cagrisinda hangi alanlar gonderiliyor ===\n";
  $off=0;$n=0;
  while(($p=strpos($idx,"'generate'",$off))!==false && $n<4){
    echo "  @$p ...".fx(substr($idx,max(0,$p-320),900))."...\n\n";
    $off=$p+10;$n++;
  }
  if(!$n){
    $off=0;
    while(($p=strpos($idx,'"generate"',$off))!==false && $n<4){
      echo "  @$p ...".fx(substr($idx,max(0,$p-320),900))."...\n\n";
      $off=$p+10;$n++;
    }
  }
  if(!$n) echo "  (generate cagrisi bulunamadi)\n";

  echo "\n=== [10] Profilin gonderime eklendigi yer (profile/absender payload) ===\n";
  hits('payload',$idx,['profile:','profil:','absender:','sender:','fd.append(\'profile','fd.append("profile'],140,360,3);

  echo "\n=== [11] Konto e-postasi istemcide nereden okunuyor ===\n";
  $off=0;$n=0;
  while(($p=stripos($idx,'email',$off))!==false && $n<8){
    $seg=fx(substr($idx,max(0,$p-110),240));
    if(stripos($seg,'getItem')!==false || stripos($seg,'session')!==false || stripos($seg,'konto')!==false){
      echo "  @$p ...$seg...\n"; $n++;
    }
    $off=$p+5;
  }
  if(!$n) echo "  (belirgin bir kaynak bulunamadi)\n";
}

/* ─────────── yardimci api'ler ─────────── */
echo "\n\n=== [12] Diger uc noktalar ===\n";
foreach(['intake-api.php','fall-api.php','send-doc.php','postversand.php'] as $f){
  $p=$dir.'/'.$f;
  if(!is_file($p)){ printf("  %-18s (yok)\n",$f); continue; }
  $s=(string)@file_get_contents($p);
  printf("  %-18s %7d B   email:x%d  Mustermann:x%d  erfind:x%d  \$_SESSION:x%d\n",
    $f,strlen($s),substr_count($s,'email'),substr_count($s,'Mustermann'),
    substr_count($s,'erfind'),substr_count($s,'$_SESSION'));
}

echo "\n== BITTI — ciktinin TAMAMINI gonder ==\n";
