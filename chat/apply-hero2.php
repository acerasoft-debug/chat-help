<?php
/**
 * ChatHelp — apply-hero2 (CH_HERO2) — hero rotusu:
 *  [1] H1 "Ihr Recht. Ihr Schutz." -> "Ihr gutes Recht. Einfach durchgesetzt." (genel)
 *  [2] ch-howto adimlari: kategori-kutusu DEGIL -> kucuk eyebrow baslik + ①›②›③ zarif akis
 *  [3] adimlar wlc-trust(DSGVO) SONRASINA enjekte -> DSGVO artik adimlarin USTUNDE
 *  3 sayac-korumali str_replace (kendi CH_PLANFAX blogum + statik H1), HEPSI-YA-HIC.
 * KULLANIM: pull2.php?key=...&files=apply-hero2.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-hero2 BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_HERO2')!==false) exit("Zaten ekli (CH_HERO2).\n");
if(strpos($src,'CH_PLANFAX')===false) exit("HATA: CH_PLANFAX yok (once onu kur).\n");

$fail=[];

/* [1] H1 */
$o1='<h1 class="wlc-h1">Ihr Recht.<br><em>Ihr Schutz.</em></h1>';
$n1='<h1 class="wlc-h1">Ihr gutes Recht.<br><em>Einfach durchgesetzt.</em></h1><!--CH_HERO2-->';
$c=substr_count($src,$o1);
if($c===1){ $src=str_replace($o1,$n1,$src); echo "  ✓ [1] H1 degistirildi\n"; } else { echo "  ✗ [1] H1 anchor ($c)\n"; $fail[]=1; }

/* [2] ch-howto CSS -> kcompakt zarif */
$o2 = <<<'OLD'
#ch-howto{max-width:560px;margin:14px auto 4px;display:flex;gap:9px;flex-wrap:wrap;justify-content:center}
#ch-howto .hw{flex:1 1 160px;min-width:150px;background:var(--s1,rgba(255,255,255,.04));border:1px solid var(--bdr,rgba(255,255,255,.1));border-radius:14px;padding:12px 13px;text-align:left}
#ch-howto .hw-i{font-size:20px;margin-bottom:5px}
#ch-howto .hw-t{font-size:13px;font-weight:800;color:var(--t1,#fff);margin-bottom:3px}
#ch-howto .hw-s{font-size:11.5px;color:var(--t3,#a8a8d0);line-height:1.5}
#ch-howto .hw-3{border-color:rgba(212,168,74,.4)}
#ch-howto .hw-3 .hw-t{color:var(--gold,#d4a84a)}
OLD;
$n2 = <<<'NEW'
#ch-howto{max-width:520px;margin:16px auto 4px;text-align:center}
#ch-howto .hw-ey{font-size:10px;font-weight:800;letter-spacing:1.6px;text-transform:uppercase;color:var(--gold,#d4a84a);opacity:.85;margin-bottom:9px}
#ch-howto .hw-row{display:inline-flex;align-items:center;justify-content:center;gap:7px;flex-wrap:wrap}
#ch-howto .hw{display:inline-flex;align-items:center;gap:6px;background:transparent;border:none;padding:1px 2px}
#ch-howto .hw-n{width:18px;height:18px;border-radius:50%;border:1.5px solid rgba(212,168,74,.55);color:var(--gold,#d4a84a);font-size:10.5px;font-weight:800;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0}
#ch-howto .hw-t{font-size:12.5px;font-weight:700;color:var(--t2,#cdc7d6);white-space:nowrap}
#ch-howto .hw-3 .hw-n{background:linear-gradient(135deg,var(--gold,#d4a84a),#ecc060);color:#141018;border-color:transparent}
#ch-howto .hw-3 .hw-t{color:var(--gold,#d4a84a)}
#ch-howto .hw-ar{color:var(--t3,#98909f);font-size:12px;opacity:.7}
NEW;
$c=substr_count($src,$o2);
if($c===1){ $src=str_replace($o2,$n2,$src); echo "  ✓ [2] ch-howto CSS -> kompakt zarif\n"; } else { echo "  ✗ [2] CSS anchor ($c)\n"; $fail[]=2; }

/* [3] ch-howto render + konum (wlc-trust sonrasi = DSGVO ustte) */
$o3 = <<<'OLD'
      var arr=T[UIL()]||T.de;
      var html='';
      for(var i=0;i<arr.length;i++){ var s=arr[i];
        html+='<div class="hw'+(i===2?' hw-3':'')+'"><div class="hw-i">'+s[0]+'</div><div class="hw-t">'+s[1]+'</div><div class="hw-s">'+s[2]+'</div></div>';
      }
      if(!ex){ ex=document.createElement("div"); ex.id="ch-howto"; if(host.parentNode) host.parentNode.insertBefore(ex, host.nextSibling); }
OLD;
$n3 = <<<'NEW'
      var arr=T[UIL()]||T.de;
      var EY={de:"So einfach geht's",tr:"Bu kadar kolay",en:"How it works",fr:"Comment ça marche",es:"Cómo funciona",it:"Come funziona"};
      var html='<div class="hw-ey">'+(EY[UIL()]||EY.de)+'</div><div class="hw-row">';
      for(var i=0;i<arr.length;i++){ var s=arr[i];
        if(i>0) html+='<span class="hw-ar">›</span>';
        html+='<span class="hw'+(i===2?' hw-3':'')+'"><span class="hw-n">'+(i+1)+'</span><span class="hw-t">'+s[1]+'</span></span>';
      }
      html+='</div>';
      var anchor=document.querySelector('.wlc-trust')||host;
      if(!ex){ ex=document.createElement("div"); ex.id="ch-howto"; if(anchor.parentNode) anchor.parentNode.insertBefore(ex, anchor.nextSibling); }
NEW;
$c=substr_count($src,$o3);
if($c===1){ $src=str_replace($o3,$n3,$src); echo "  ✓ [3] ch-howto render + konum (DSGVO ustte)\n"; } else { echo "  ✗ [3] render anchor ($c)\n"; $fail[]=3; }

if($fail){ echo "\nHATA: eslesmeyen: ".implode(', ',$fail)." — HICBIR sey degistirilmedi.\n"; exit; }

$tmp=tempnam(sys_get_temp_dir(),'h2').'.php'; file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-hero2-'.date('Ymd-His'),(string)@file_get_contents($file));
$w=@file_put_contents($file,$src);
if($w===false||$w<strlen($src)) exit("\n✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_HERO2')===false) exit("\n✗ DOGRULAMA BASARISIZ.\n");
echo "\n  ✓ CH_HERO2 diskte (".strlen($chk)." B)\n";
echo "  Hard-refresh: H1 yeni + adimlar kucuk zarif akis (①›②›③) + DSGVO adimlarin ustunde.\n";
