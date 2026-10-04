<?php
/* dump-profil2 (READ-ONLY) — Konto Profil blogu HTML+doldurma + aktif Absender-
   Profil (ch_profiles/ch_prof_active) alan eslemesi (f1..f7) + CH_PROFILES.
   KULLANIM: pull2.php?key=...&files=dump-profil2.php */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "dump-profil2 (READ-ONLY)\n\n";
$s=@file_get_contents(__DIR__.'/index.php');
if($s===false) exit("okunamadi\n");
function W($s,$p,$b,$a){ return trim(preg_replace('/\s+/',' ',substr($s,max(0,$p-$b),$b+$a))); }

echo "=== [1] Konto Profil ksec HTML (Name/E-Mail/Adresse/Land + IDs) ===\n";
/* editProfile civari geriye statik blok */
$p=strpos($s,'editProfile()');
if($p!==false){ $ks=strrpos(substr($s,0,$p),'ksec-hdr'); echo substr($s,max(0,$ks-40), 1200)."\n"; }

echo "\n=== [2] Profil blogunu dolduran fonksiyon (prof() -> display) ===\n";
foreach(['function editProfile','function fillKonto','konto-prof','function drawProfil','function showProfil','function renderKonto'] as $k){
  $p=strpos($s,$k); if($p!==false) echo "  '".$k."' [".$p."] ".W($s,$p,0,200)."\n";
}
/* prof() f1..f7 nasil display'e yaziliyor? id ler */
echo "\n=== [2b] profil display ID/alan yazimlari ===\n";
if(preg_match_all('/getElementById\([\'"]([a-z0-9\-]*prof[a-z0-9\-]*)[\'"]\)/i',$s,$m)){ echo "  prof id: ".implode(', ',array_unique($m[1]))."\n"; }
if(preg_match_all('/(kprof-\w+|profil-\w+|konto-prof\w*)/i',$s,$m)){ echo "  eslesme: ".implode(', ',array_slice(array_unique($m[1]),0,20))."\n"; }

echo "\n=== [3] CH_PROFILES: ch_profiles yapisi + aktif secim + F alanlari ===\n";
$p=strpos($s,'id="ch-profiles-js"');
if($p!==false){ echo substr($s,$p,1700)."\n"; }

echo "\n=== [4] F alan eslemesi ipuclari (f1..f7 label) ===\n";
if(preg_match_all('/f([1-7])\s*[:=][^,;}]{0,40}/i',$s,$m,PREG_SET_ORDER)){ $i=0; foreach($m as $x){ if($i++>=14)break; echo "  ".trim($x[0])."\n"; } }
echo "\n=== [5] prof() TAM ===\n";
$p=strpos($s,'function prof('); if($p!==false) echo "  ".W($s,$p,0,320)."\n";
echo "\nBITTI.\n";
