<?php
/**
 * ChatHelp — apply-foto-anysize (CH_PHOTO_ANYSIZE) — sunucu tarafi "her boyutu
 *  kabul et" guvence agi (api.php doPhoto). Istemci kucultme (CH_FOTO_SHRINK)
 *  ana savunma; bu da:
 *   [A] $b64'ten 'data:...;base64,' onekini + bosluklari temizler, mime normalize
 *       (image/jpg -> image/jpeg).
 *   [B] Resim hala buyukse (GD varsa) sunucuda 1568px'e kucultur (istemci
 *       kucultme calismadiysa — eski tarayici vb.).
 *   [C] Vision API 200 disi/bos donerse artik SESSIZ bos yerine GERCEK hatayi
 *       dondurur (istemci "Analiz basarisiz" yerine sebebi gorur, biz de).
 *  2 count-guard'li str_replace + php -l. api.php yamasi.
 * KULLANIM: pull2.php?key=...&files=apply-foto-anysize.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-foto-anysize BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/api.php';
$src=@file_get_contents($file);
if($src===false) exit("api.php okunamadi\n");
if(strpos($src,'CH_PHOTO_ANYSIZE')!==false) exit("Zaten ekli (CH_PHOTO_ANYSIZE).\n");

$fail=[];

/* [A]+[B] no-image guard'indan sonra normalize + GD kucultme ekle */
$a1 = "    if (!\$b64) { respond(['error'=>'No image']); return; }";
$n1 = $a1."\n".<<<'PHPB'

    /* CH_PHOTO_ANYSIZE — her boyutu kabul et: temizle+normalize (+gerekirse GD kucult) */
    $__ci=strpos($b64,'base64,'); if($__ci!==false) $b64=substr($b64,$__ci+7);
    $b64=preg_replace('/\s+/','',$b64);
    $mime=strtolower(trim($mime)); if($mime==='image/jpg') $mime='image/jpeg';
    if(strpos($mime,'image/')===0 && function_exists('imagecreatefromstring')){
        $__raw=base64_decode($b64,true);
        if($__raw!==false){
            $__im=@imagecreatefromstring($__raw);
            if($__im){
                $__w=imagesx($__im); $__h=imagesy($__im);
                if(strlen($__raw)>4200000 || max($__w,$__h)>1600){
                    $__s=min(1,1568/max($__w,$__h));
                    $__nw=max(1,(int)round($__w*$__s)); $__nh=max(1,(int)round($__h*$__s));
                    $__cv=imagecreatetruecolor($__nw,$__nh);
                    $__wh=imagecolorallocate($__cv,255,255,255); imagefilledrectangle($__cv,0,0,$__nw,$__nh,$__wh);
                    imagecopyresampled($__cv,$__im,0,0,0,0,$__nw,$__nh,$__w,$__h);
                    ob_start(); imagejpeg($__cv,null,82); $__jpg=ob_get_clean(); imagedestroy($__cv);
                    if($__jpg){ $b64=base64_encode($__jpg); $mime='image/jpeg'; }
                }
                imagedestroy($__im);
            }
        }
    }
PHPB;
$c1=substr_count($src,$a1);
if($c1!==1){ echo "  ✗ [A/B] anchor ($c1, 1 beklenir)\n"; $fail[]='AB'; }
else { $src=str_replace($a1,$n1,$src); echo "  ✓ [A/B] normalize + GD kucultme eklendi\n"; }

/* [C] curl sonrasi: 200-disi/bos ise gercek hatayi dondur */
$a2 = "    \$res  = curl_exec(\$ch); curl_close(\$ch);\n    \$data = json_decode(\$res, true);\n    \$text = \$data['content'][0]['text'] ?? '{}';";
$n2 = "    \$res  = curl_exec(\$ch); \$__code=curl_getinfo(\$ch,CURLINFO_HTTP_CODE); \$__cerr=curl_error(\$ch); curl_close(\$ch);\n"
    . "    \$data = json_decode(\$res, true);\n"
    . "    if(\$__code!=200 || !isset(\$data['content'][0]['text'])){ /*CH_PHOTO_ANYSIZE*/\n"
    . "        \$__msg = \$data['error']['message'] ?? (\$__cerr ?: ('HTTP '.\$__code));\n"
    . "        respond(['error'=>'vision_failed','detail'=>\$__msg]); return;\n"
    . "    }\n"
    . "    \$text = \$data['content'][0]['text'] ?? '{}';";
$c2=substr_count($src,$a2);
if($c2!==1){ echo "  ✗ [C] curl anchor ($c2, 1 beklenir)\n"; $fail[]='C'; }
else { $src=str_replace($a2,$n2,$src); echo "  ✓ [C] API hatasi artik bos yerine gercek sebep doner\n"; }

if($fail){ echo "\nHATA: eslesmeyen: ".implode(', ',$fail)." — api.php DEGISTIRILMEDI.\n"; exit; }

$tmp=tempnam(sys_get_temp_dir(),'fa').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "\nLINT HATASI — api.php DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-anysize-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_PHOTO_ANYSIZE')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_PHOTO_ANYSIZE api.php'de (".strlen($chk)." B)\n";
echo "✓ Artik her boyut/format: temizlenir, normalize edilir, gerekirse sunucuda kucultulur.\n";
echo "✓ Hata olursa sessiz bos yerine gercek sebep gelir.\n";
