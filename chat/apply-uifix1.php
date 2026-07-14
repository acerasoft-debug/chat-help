<?php
/**
 * ChatHelp — apply-uifix1 (CH_UIFIX1)
 *  (1) Sonuc kartindan "Meine Anträge" chip'ini kaldirir (kaydedilenler zaten
 *      Meine Fälle'de gorunuyor). c2 olusturulur ama artik EKLENMEZ.
 *  (2) "AKTUELLE RECHTSLAGE" sizintisini keser: doGenerate client'a ic iskeleti
 *      ('NUR BELEGTE NUTZERANGABEN … nicht angegeben') 'research' olarak
 *      donmesin — bos doner (kutu gizlenir). Taslak uretimi (server-ici) aynen kalir.
 *  Iki dosya: index.php (chip) + api.php (research). Her adim count-guard'li.
 * KULLANIM: pull2.php?key=...&files=apply-uifix1.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-uifix1 BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$done=0; $fail=0;

/* ── (1) index.php: Meine Anträge chip'ini ekleme ── */
$idxF=__DIR__.'/index.php'; $idx=@file_get_contents($idxF);
if($idx===false){ echo "  ✗ index.php okunamadi\n"; $fail++; }
elseif(strpos($idx,'CH_UIFIX1_IDX')!==false){ echo "  • index zaten uygulanmis\n"; }
else{
    $old='chips.appendChild(c1);chips.appendChild(c2);chips.appendChild(c3);';
    $new='chips.appendChild(c1);chips.appendChild(c3);/*CH_UIFIX1_IDX Meine Anträge chip kaldirildi*/';
    $c=substr_count($idx,$old);
    if($c===1){
        $idx=str_replace($old,$new,$idx);
        $tmp=tempnam(sys_get_temp_dir(),'u1').'.php'; file_put_contents($tmp,$idx);
        $lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
        if($rc===0){
            @file_put_contents($idxF.'.bak-uifix1-'.date('Ymd-His'),(string)@file_get_contents($idxF));
            if(@file_put_contents($idxF,$idx)!==false && strpos((string)@file_get_contents($idxF),'CH_UIFIX1_IDX')!==false){ echo "  ✓ (1) Meine Anträge chip kaldirildi\n"; $done++; }
            else { echo "  ✗ (1) yazma/dogrulama hatasi\n"; $fail++; }
        } else { echo "  ✗ (1) LINT hatasi — index dokunulmadi:\n     ".implode("\n     ",$lo)."\n"; $fail++; }
    } else { echo "  ⚠ (1) chip anchor $c kez (1 bekleniyordu) — atlandi\n"; $fail++; }
}

/* ── (2) api.php: research sizintisi ── */
$apiF=__DIR__.'/api.php'; $api=@file_get_contents($apiF);
if($api===false){ echo "  ✗ api.php okunamadi\n"; $fail++; }
elseif(strpos($api,'CH_UIFIX1_API')!==false){ echo "  • api zaten uygulanmis\n"; }
else{
    $old="'research'=>\$research])";
    $new="'research'=>'']) /*CH_UIFIX1_API — ic iskele client'a donmesin*/";
    $c=substr_count($api,$old);
    if($c===1){
        $api=str_replace($old,$new,$api);
        $tmp=tempnam(sys_get_temp_dir(),'u2').'.php'; file_put_contents($tmp,$api);
        $lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
        if($rc===0){
            @file_put_contents($apiF.'.bak-uifix1-'.date('Ymd-His'),(string)@file_get_contents($apiF));
            if(@file_put_contents($apiF,$api)!==false && strpos((string)@file_get_contents($apiF),'CH_UIFIX1_API')!==false){ echo "  ✓ (2) research sizintisi kesildi (kutu artik iskele gostermez)\n"; $done++; }
            else { echo "  ✗ (2) yazma/dogrulama hatasi\n"; $fail++; }
        } else { echo "  ✗ (2) LINT hatasi — api dokunulmadi:\n     ".implode("\n     ",$lo)."\n"; $fail++; }
    } else { echo "  ⚠ (2) research anchor $c kez (1 bekleniyordu) — atlandi\n"; $fail++; }
}

echo "\n".($done>0?"✓ $done duzeltme uygulandi":"")."".($fail>0?"  ($fail atlandi/hata)":"")."\n";
echo "Meine Anträge kart chip'i + 'nicht angegeben' iskele sizintisi ele alindi.\n";
