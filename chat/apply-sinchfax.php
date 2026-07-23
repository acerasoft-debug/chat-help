<?php
/**
 * ChatHelp — apply-sinchfax (CH_SINCHFAX) — Sinch Fax destegi:
 *  [1] postversand.php'ye fax_send_sinch (OAuth2 + fax.api.sinch.com) + saglayici
 *      secimine 'sinch' + fax_configured'a sinch kolu eklenir.
 *  [2] chat/faxsetup.php GUVENLI kurulum sayfasi olusturulur: kullanici Key ID,
 *      Key Secret, Project ID'yi FORMA girer (URL/git/sohbet'te GORUNMEZ) ->
 *      sayfa Sinch OAuth + fax API erisimini DOGRULAR -> basariliysa config.php'ye
 *      yazar (FAX_PROVIDER=sinch). Basarisizsa yazmaz, nedeni gosterir.
 * KULLANIM: pull2.php?key=...&files=apply-sinchfax.php
 *  Sonra: https://chat-help.com/chat/faxsetup.php ac, formu doldur.
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-sinchfax BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$ok=0;

/* ── [1] postversand.php: Sinch adaptor ── */
$pv=__DIR__.'/postversand.php';
$src=@file_get_contents($pv);
if($src===false){ echo "  ✗ postversand.php okunamadi\n"; }
elseif(strpos($src,'CH_SINCHFAX')!==false){ echo "  = [1] zaten ekli\n"; $ok++; }
else{
  /* fax_send router'a sinch ekle */
  $o1="function fax_send(\$pdfBin,\$faxnr){ \$p=fax_provider();\n  if(\$p==='phaxio')   return fax_send_phaxio(\$pdfBin,\$faxnr);";
  $n1="/* CH_SINCHFAX — Sinch Fax: OAuth2 token + fax.api.sinch.com */\n"
     ."function sinch_token(){\n"
     ."  if(!defined('SINCH_KEY_ID')||!defined('SINCH_KEY_SECRET')) return '';\n"
     ."  \$ch=curl_init('https://auth.sinch.com/oauth2/token');\n"
     ."  curl_setopt_array(\$ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,\n"
     ."    CURLOPT_USERPWD=>constant('SINCH_KEY_ID').':'.constant('SINCH_KEY_SECRET'),\n"
     ."    CURLOPT_POSTFIELDS=>'grant_type=client_credentials',\n"
     ."    CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'],CURLOPT_TIMEOUT=>30]);\n"
     ."  \$r=curl_exec(\$ch); curl_close(\$ch); \$j=json_decode((string)\$r,true);\n"
     ."  return is_array(\$j)?(\$j['access_token']??''):'';\n"
     ."}\n"
     ."function fax_send_sinch(\$pdfBin,\$faxnr){\n"
     ."  \$tok=sinch_token(); if(!\$tok) return ['ok'=>false,'http'=>0,'provider'=>'Sinch','err'=>'no_token','result'=>null];\n"
     ."  \$pid=defined('SINCH_PROJECT_ID')?constant('SINCH_PROJECT_ID'):'';\n"
     ."  \$tmp=tempnam(sys_get_temp_dir(),'fax').'.pdf'; file_put_contents(\$tmp,\$pdfBin);\n"
     ."  \$ch=curl_init('https://fax.api.sinch.com/v3/projects/'.rawurlencode(\$pid).'/faxes');\n"
     ."  curl_setopt_array(\$ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,\n"
     ."    CURLOPT_HTTPHEADER=>['Authorization: Bearer '.\$tok],\n"
     ."    CURLOPT_POSTFIELDS=>['to'=>fax_e164(\$faxnr),'file'=>new CURLFile(\$tmp,'application/pdf','dokument.pdf')],\n"
     ."    CURLOPT_TIMEOUT=>90]);\n"
     ."  \$res=curl_exec(\$ch); \$code=(int)curl_getinfo(\$ch,CURLINFO_HTTP_CODE); \$err=curl_error(\$ch); curl_close(\$ch); @unlink(\$tmp);\n"
     ."  \$j=json_decode((string)\$res,true);\n"
     ."  return ['ok'=>(\$code>=200&&\$code<300),'http'=>\$code,'provider'=>'Sinch','err'=>\$err,'result'=>is_array(\$j)?\$j:substr((string)\$res,0,400)];\n"
     ."}\n"
     ."function fax_send(\$pdfBin,\$faxnr){ \$p=fax_provider();\n"
     ."  if(\$p==='sinch')    return fax_send_sinch(\$pdfBin,\$faxnr);\n"
     ."  if(\$p==='phaxio')   return fax_send_phaxio(\$pdfBin,\$faxnr);";
  $c=substr_count($src,$o1);
  if($c===1){
    $src=str_replace($o1,$n1,$src);
    /* fax_configured'a sinch kolu */
    $o2="function fax_configured(){\n  \$p=fax_provider();\n";
    $n2="function fax_configured(){\n  \$p=fax_provider();\n  if(\$p==='sinch') return defined('SINCH_KEY_ID') && defined('SINCH_KEY_SECRET') && defined('SINCH_PROJECT_ID') && constant('SINCH_KEY_ID') && constant('SINCH_KEY_SECRET') && constant('SINCH_PROJECT_ID');\n";
    if(substr_count($src,$o2)===1){ $src=str_replace($o2,$n2,$src); }
    $tmp=tempnam(sys_get_temp_dir(),'pv').'.php'; file_put_contents($tmp,$src);
    $lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
    if($rc!==0){ echo "  ✗ [1] LINT: ".implode(' ',$lo)."\n"; }
    else{ @copy($pv,$pv.'.bak-sinchfax-'.date('Ymd-His')); @file_put_contents($pv,$src); echo "  ✓ [1] postversand.php: Sinch adaptor eklendi\n"; $ok++; }
  } else echo "  ✗ [1] fax_send anchor ($c) — atlandi\n";
}

/* ── [2] faxsetup.php guvenli kurulum sayfasi ── */
$setup = <<<'SETUPPHP'
<?php
/* ChatHelp — faxsetup.php (CH_SINCHFAX) — Sinch Fax guvenli kurulum + dogrulama */
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
error_reporting(E_ERROR | E_PARSE);
function mask($v){ $v=(string)$v; $n=strlen($v); return $n<=6?str_repeat('*',$n):(substr($v,0,3).str_repeat('*',max(0,$n-6)).substr($v,-3)); }
$out=''; $done=false;
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
  $kid=trim((string)($_POST['kid']??'')); $ksec=trim((string)($_POST['ksec']??'')); $pid=trim((string)($_POST['pid']??''));
  if($kid===''||$ksec===''||$pid===''){ $out.='<p class="bad">Tüm alanları doldurun.</p>'; }
  else{
    /* 1) OAuth token */
    $ch=curl_init('https://auth.sinch.com/oauth2/token');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_USERPWD=>$kid.':'.$ksec,
      CURLOPT_POSTFIELDS=>'grant_type=client_credentials',CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'],CURLOPT_TIMEOUT=>30]);
    $r=curl_exec($ch); $tc=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    $tj=json_decode((string)$r,true); $tok=is_array($tj)?($tj['access_token']??''):'';
    if(!$tok){ $out.='<p class="bad">✗ OAuth başarısız (HTTP '.$tc.'). Key ID/Secret yanlış olabilir.<br><small>'.htmlspecialchars(substr((string)$r,0,200)).'</small></p>'; }
    else{
      $out.='<p class="ok">✓ OAuth token alındı — Key ID/Secret GEÇERLİ.</p>';
      /* 2) Fax API erisim testi */
      $fch=curl_init('https://fax.api.sinch.com/v3/projects/'.rawurlencode($pid).'/faxes?pageSize=1');
      curl_setopt_array($fch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$tok],CURLOPT_TIMEOUT=>30]);
      $fr=curl_exec($fch); $fc=(int)curl_getinfo($fch,CURLINFO_HTTP_CODE); curl_close($fch);
      if($fc>=200&&$fc<300){
        $out.='<p class="ok">✓✓ FAX API ERİŞİLEBİLİR — bu hesap faks gönderebilir!</p>';
        /* config.php'ye yaz */
        $cfg=__DIR__.'/config.php'; $c=@file_get_contents($cfg);
        if($c!==false){
          $c=preg_replace("/^\s*define\(\s*'(SINCH_KEY_ID|SINCH_KEY_SECRET|SINCH_PROJECT_ID|FAX_PROVIDER)'.*?\);\s*$/mi",'',$c);
          $ins="\n/* CH_SINCHFAX */\ndefine('FAX_PROVIDER','sinch');\ndefine('SINCH_KEY_ID',".var_export($kid,true).");\ndefine('SINCH_KEY_SECRET',".var_export($ksec,true).");\ndefine('SINCH_PROJECT_ID',".var_export($pid,true).");\n";
          $c=preg_replace('/^(\s*<\?php)/', "$1\n".$ins, $c, 1);
          $tmp=tempnam(sys_get_temp_dir(),'cf').'.php'; file_put_contents($tmp,$c);
          $lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
          if($rc===0){ @copy($cfg,$cfg.'.bak-sinch-'.date('Ymd-His')); @file_put_contents($cfg,$c); if(function_exists('opcache_reset'))@opcache_reset();
            $out.='<p class="ok">✓ config.php güncellendi — FAX_PROVIDER=sinch. Faks AKTİF!</p><p>Test: bir belge üret → Senden → Fax → numara gir → gönder.</p>';
            $out.='<p class="bad">⚠️ Güvenlik: bu anahtarları Sinch panelinden şimdi YENİLE.</p>'; $done=true;
          } else { $out.='<p class="bad">✗ config LINT hatası — yazılmadı.</p>'; }
        } else $out.='<p class="bad">✗ config.php okunamadı.</p>';
      } else {
        $out.='<p class="bad">✗ Fax API erişilemedi (HTTP '.$fc.'). Muhtemel neden: bu Sinch hesabında/projesinde FAX ürünü etkin değil, ya da Project ID yanlış.<br><small>'.htmlspecialchars(substr((string)$fr,0,220)).'</small></p>';
        $out.='<p>Çözüm: Sinch panelinde Fax ürününü etkinleştir / doğru Project ID kullan / Sinch destek.</p>';
      }
    }
  }
}
?>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ChatHelp — Sinch Fax Kurulum</title>
<style>
body{background:#14141a;color:#eee;font-family:system-ui,sans-serif;max-width:520px;margin:0 auto;padding:22px;line-height:1.6}
h2{color:#e8c877}label{display:block;margin:12px 0 4px;font-size:13px;color:#cfe0ff}
input{width:100%;box-sizing:border-box;background:#101016;border:1px solid rgba(255,255,255,.15);border-radius:10px;color:#fff;padding:11px 13px;font-size:14px}
button{width:100%;margin-top:16px;background:linear-gradient(135deg,#3fe08a,#1fa25c);color:#fff;border:0;border-radius:12px;padding:14px;font-size:15px;font-weight:800;cursor:pointer}
.ok{color:#42df94}.bad{color:#ff8080}.card{background:#1c1c24;border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:14px;margin:12px 0}
small{color:#9aa;word-break:break-all}
</style>
<h2>📠 Sinch Fax Kurulum</h2>
<div class="card"><?php echo $out?:'Sinch panelindeki 3 değeri gir. Değerler sunucuya HTTPS ile gider, hiçbir yerde loglanmaz.'; ?></div>
<?php if(!$done): ?>
<form method="POST" autocomplete="off">
  <label>Key ID</label><input name="kid" placeholder="03724cb5-..." required>
  <label>Key Secret</label><input name="ksec" placeholder="5Ogf..." required>
  <label>Project ID</label><input name="pid" placeholder="Sinch Project ID (UUID)" required>
  <button type="submit">Doğrula &amp; Kur</button>
</form>
<p><small>Project ID'yi Sinch panelinde Account/Settings → Projects altında veya adres çubuğunda bulabilirsin.</small></p>
<?php endif; ?>
SETUPPHP;

$dest=__DIR__.'/faxsetup.php';
$tmp=tempnam(sys_get_temp_dir(),'fs').'.php'; file_put_contents($tmp,$setup);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "  ✗ [2] faxsetup.php LINT: ".implode(' ',$lo)."\n"; }
else{ $w=@file_put_contents($dest,$setup); echo $w!==false?"  ✓ [2] faxsetup.php olusturuldu (guvenli kurulum sayfasi)\n":"  ✗ [2] yazilamadi\n"; if($w!==false)$ok++; }

if(function_exists('opcache_reset')) @opcache_reset();
echo "\n  Sonuc: $ok/3\n";
echo "  SIMDI: cihazda su sayfayi ac ve Sinch'ten aldigin 3 degeri gir:\n";
echo "  https://chat-help.com/chat/faxsetup.php\n";
echo "  (Key ID + Key Secret + Project ID) -> Dogrula & Kur.\n";
echo "  Sayfa Sinch'e baglanip faksin bu hesapta calisip calismadigini KESIN soyleyecek.\n";
