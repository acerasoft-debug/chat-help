<?php
/**
 * ChatHelp — apply-fall-save (CH_FALL_SAVE)
 *  Fall/derin-analiz belgeleri de ARTIK Meine Antraege'ye kaydedilir.
 *  fall-api.php solve kuyrugunda, belge uretilince api.php'nin STORE_DIR
 *  bicimiyle (data/sid_id.json, 24h TTL, Behalten uyumlu) diske yazilir.
 *  Kayit chAI ciktisidir (gercek ad/adres profBlock'ta — mevcut Fall modeli
 *  zaten sunucuda birlestiriyor; davranis degismez).
 * KULLANIM: pull-updates.php?files=apply-fall-save.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-fall-save BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/fall-api.php';
$src = @file_get_contents($file);
if ($src===false) exit("fall-api.php okunamadi\n");
if (strpos($src,'CH_FALL_SAVE')!==false) exit("Zaten ekli (CH_FALL_SAVE).\n");

$o = "    \$document = chAI2(\$sys, \$usr, 4000, array_values(\$photos));\n"
   . "    if (\$document) fall_log(\$user['id'], \$title);";
$n = "    \$document = chAI2(\$sys, \$usr, 4000, array_values(\$photos));\n"
   . "    if (\$document) fall_log(\$user['id'], \$title);\n"
   . "    if (\$document) { /*CH_FALL_SAVE — Meine Antraege kaydi*/\n"
   . "        \$fsid = preg_replace('/[^a-f0-9]/','', \$_COOKIE['ch_sid'] ?? '');\n"
   . "        \$fdir = __DIR__ . '/data/';\n"
   . "        if (\$fsid && is_dir(\$fdir)) {\n"
   . "            \$fid = bin2hex(random_bytes(8));\n"
   . "            @file_put_contents(\$fdir.\$fsid.'_'.\$fid.'.json', json_encode([\n"
   . "                'id'=>\$fid,'sid'=>\$fsid,'docType'=>'fall_doc',\n"
   . "                'docName'=>mb_substr(htmlspecialchars(trim((string)\$title) !== '' ? \$title : 'Fall-Dokument'),0,80),\n"
   . "                'document'=>\$document,'research'=>'',\n"
   . "                'country'=>strtoupper(preg_replace('/[^A-Za-z]/','',(string)(\$body['country'] ?? 'DE'))) ?: 'DE',\n"
   . "                'created'=>time(),'keep'=>false,\n"
   . "                'preview'=>mb_substr(strip_tags(\$document),0,200),\n"
   . "            ], JSON_UNESCAPED_UNICODE));\n"
   . "        }\n"
   . "    }";
$c=0; $src=str_replace($o,$n,$src,$c);
if ($c!==1) exit("HATA: solve kuyruk anchor eslesmedi ($c) — fall-api.php DEGISTIRILMEDI.\n");
echo "  ✓ Fall belgesi kaydi eklendi\n";

$tmp = tempnam(sys_get_temp_dir(),'fs').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — fall-api.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-fallsave-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_FALL_SAVE uygulandi. Fall belgeleri artik Meine Antraege'de gorunur (Behalten ile kalici).\n";
