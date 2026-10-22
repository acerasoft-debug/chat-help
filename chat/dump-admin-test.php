<?php
/**
 * ChatHelp — dump-admin-test (SADECE OKUR) — #95 icin admin.php'de:
 *  (1) "test" butonu / test endpoint'i ve KULLANDIGI MODEL adi (gecersiz mi?),
 *  (2) admin SIFRESI nerede sabit-kodlu (config'e tasinacak),
 *  (3) hangi config dosyasi mevcut (config.php / .env / secrets).
 *  Sifre DEGERINI maskeler; sadece konum/uzunluk gosterir. HICBIR SEY YAZMAZ.
 * KULLANIM: pull2.php?key=...&files=dump-admin-test.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$D=__DIR__;
$adm=(string)@file_get_contents("$D/admin.php");
echo "=== dump-admin-test — SADECE OKUR ===\n\n";
if($adm==='') { echo "admin.php YOK veya bos.\n"; }
else {
  echo "[0] admin.php boyutu: ".strlen($adm)." bayt\n\n";

  /* [1] model adlari — claude-* / deepseek* / gpt* / model= */
  echo "[1] Model referanslari (satir baglami):\n";
  $lines=preg_split('/\n/',$adm); $shown=0;
  foreach($lines as $i=>$ln){
    if($shown>=25) break;
    if(preg_match('/model|claude-|deepseek|gpt-|sonnet|opus|haiku|3-5|3\.5|max_tokens|anthropic|api\.php|action=/i',$ln)){
      $t=trim($ln); if(strlen($t)>200)$t=substr($t,0,200).'…';
      echo "  L".($i+1).": ".$t."\n"; $shown++;
    }
  }
  if($shown===0) echo "  (model referansi yok)\n";

  /* [2] 'test' butonu / test fonksiyonu baglami */
  echo "\n[2] 'test' gecen satirlar (buton/fonksiyon/onclick):\n";
  $shown=0;
  foreach($lines as $i=>$ln){
    if($shown>=18) break;
    if(preg_match('/test/i',$ln) && preg_match('/button|onclick|function|fetch|action|<a |model|api/i',$ln)){
      $t=trim($ln); if(strlen($t)>200)$t=substr($t,0,200).'…';
      echo "  L".($i+1).": ".$t."\n"; $shown++;
    }
  }
  if($shown===0) echo "  (test baglami yok)\n";

  /* [3] admin sifresi — sabit-kodlu kontrol (DEGER MASKELI) */
  echo "\n[3] Admin sifre/parola tanimlari (DEGER MASKELI):\n";
  if(preg_match_all('/\$?(admin_?pass\w*|ADMIN_?PASS\w*|passwort|password|pass|sifre|pw|secret|token)\s*[=:]\s*[\'"]([^\'"]*)[\'"]/i',$adm,$mm,PREG_SET_ORDER)){
    foreach($mm as $m){
      $name=$m[1]; $val=$m[2];
      $mask = strlen($val)>0 ? (substr($val,0,1).str_repeat('•',max(1,strlen($val)-2)).substr($val,-1).' (uzunluk '.strlen($val).')') : '(bos)';
      echo "  ".$name." = ".$mask."\n";
    }
  } else echo "  (sabit-kodlu sifre atamasi bulunamadi — belki $_ENV/getenv/hash ile)\n";

  /* [4] auth mekanizmasi — hash_equals / $_POST / getenv / define */
  echo "\n[4] Yetkilendirme mekanizmasi satirlari:\n";
  $shown=0;
  foreach($lines as $i=>$ln){
    if($shown>=14) break;
    if(preg_match('/hash_equals|password_verify|\$_POST|\$_GET|\$_SERVER\[.PHP_AUTH|getenv|define\(|\$_ENV|http_?auth|Authorization/i',$ln)){
      $t=trim($ln); if(strlen($t)>180)$t=substr($t,0,180).'…';
      echo "  L".($i+1).": ".$t."\n"; $shown++;
    }
  }
  if($shown===0) echo "  (belirgin yetki mekanizmasi yok)\n";
}

/* [5] Mevcut config dosyalari */
echo "\n[5] Config dosyalari mevcut mu:\n";
foreach(['config.php','config.inc.php','secrets.php','.env','env.php','settings.php','keys.php'] as $f){
  $p="$D/$f"; echo "  ".str_pad($f,16)." : ".(is_file($p)?("VAR (".filesize($p)." bayt)"):"yok")."\n";
}
/* config.php icinde hangi anahtarlar tanimli (deger maskeli) */
$cfg=(string)@file_get_contents("$D/config.php");
if($cfg!==''){
  echo "\n[6] config.php icindeki define/degisken ADLARI (deger maskeli):\n";
  if(preg_match_all('/define\(\s*[\'"]([A-Z0-9_]+)[\'"]/',$cfg,$dm)){ echo "  define: ".implode(", ",array_unique($dm[1]))."\n"; }
  if(preg_match_all('/\$([a-zA-Z_]\w*)\s*=/',$cfg,$vm)){ echo "  \$var: ".implode(", ",array_slice(array_unique($vm[1]),0,30))."\n"; }
}
echo "\n=== BITTI (salt-okunur) ===\n";
