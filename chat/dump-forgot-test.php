<?php
/** ChatHelp — dump-forgot-test — CANLI teslim testi (reset e-postasi):
 *  (1) acerasoft@gmail.com hesabi var mi? (2) gercek reset_request endpoint'ini
 *  tetikle -> uretimdeki sendResetEmail/sendMail calisir -> gelen kutusu kontrol.
 *  Sifre DEGISMEZ, sadece link yollanir. TAMAMINI gonder. */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
$EMAIL='acerasoft@gmail.com';

echo "════ [1] hesap var mi? ════\n";
try{
  require_once __DIR__.'/db.php';
  $pdo=db();
  $st=$pdo->prepare('SELECT id,name,email_verified,reset_token,reset_expires FROM users WHERE email=?');
  $st->execute([strtolower($EMAIL)]);
  $u=$st->fetch(PDO::FETCH_ASSOC);
  if($u){ echo "  hesap VAR ✓ (id={$u['id']}, name={$u['name']}, verified={$u['email_verified']})\n";
    echo "  onceki reset_token: ".($u['reset_token']?substr($u['reset_token'],0,8).'… (exp '.$u['reset_expires'].')':'yok')."\n"; }
  else echo "  hesap YOK — bu adrese e-posta gitmez (doResetRequest if(user) guard).\n";
}catch(Throwable $e){ echo "  DB HATA: ".$e->getMessage()."\n"; $u=null; }

echo "\n════ [2] gercek reset_request tetikle (curl -> auth.php) ════\n";
$body=json_encode(['action'=>'reset_request','email'=>$EMAIL]);
$targets=['https://chat-help.com/chat/auth.php','http://127.0.0.1/chat/auth.php'];
$ok=false;
foreach($targets as $url){
  $ch=curl_init($url);
  curl_setopt_array($ch,[
    CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$body,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json','Host: chat-help.com'],
    CURLOPT_USERAGENT=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36',
    CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>25,
    CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_SSL_VERIFYHOST=>false,
    CURLOPT_FOLLOWLOCATION=>true,
  ]);
  $resp=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
  echo "  → $url\n     HTTP $code ".($err?"curl-err: $err":"")."\n     yanit: ".substr((string)$resp,0,300)."\n";
  if($code>=200&&$code<300){ $ok=true; break; }
}

echo "\n════ [3] reset_token yazildi mi? (dogrulama) ════\n";
try{
  if(isset($pdo)){
    $st2=$pdo->prepare('SELECT reset_token,reset_expires FROM users WHERE email=?');
    $st2->execute([strtolower($EMAIL)]);
    $u2=$st2->fetch(PDO::FETCH_ASSOC);
    if($u2 && $u2['reset_token']) echo "  ✓ reset_token yazildi: ".substr($u2['reset_token'],0,8)."… (exp {$u2['reset_expires']}) — endpoint CALISTI\n";
    else echo "  ✗ reset_token yazilmadi — endpoint calismadi ya da hesap yok\n";
  }
}catch(Throwable $e){ echo "  DB HATA2: ".$e->getMessage()."\n"; }

echo "\n════ SONUC ════\n";
echo $ok
  ? "  Endpoint yanit verdi. Gelen kutunu (ve SPAM) kontrol et: 'ChatHelp — Passwort zurücksetzen'.\n  E-posta geldiyse link TAM CALISIYOR. Gelmediyse sorun SADECE SMTP teslimi.\n"
  : "  Endpoint'e ulasilamadi (WAF/403 olabilir). Yine de reset_token yazildiysa backend saglam.\n";
echo "\n════════ BITTI. Ciktinin TAMAMINI gonder. ════════\n";
