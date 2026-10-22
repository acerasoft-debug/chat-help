<?php
/**
 * ChatHelp — apply-vst-custom (CH_VST_CUSTOM)
 *  Kullanici Vertrag Studio'da KENDI klozunu/maddesini serbest metin olarak
 *  girip sozlesmeye ekleyebilir. Onceden tanimli opsiyonel klozlara (VOPT)
 *  EK olarak calisir.
 *   [1] Alan render'ina textarea ("ta") tipi eklenir (uzun kloz metni icin).
 *   [2] Input handler textarea[data-f]'i de dinler (yoksa yazilan kaydedilmezdi).
 *   [3] HER sozlesmeye "Eigene Klauseln (optional)" adimi: 3 x (baslik + metin).
 *   [4] optPages custom klozlari ANLAGE 1'e "§ Z.." olarak ekler; artik yalniz
 *       custom kloz varken de Anlage uretilir; VOPT'ta olmayan sozlesmelerde de
 *       calisir.
 * KULLANIM: pull-updates.php?files=apply-vst-custom.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-vst-custom BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_VST_CUSTOM')!==false) exit("Zaten ekli (CH_VST_CUSTOM).\n");
if (strpos($src,'CH_VST_OPTS')===false) exit("HATA: once apply-vst-options.php gerekli (CH_VST_OPTS yok).\n");

$fail=[];

/* ── [1] render: textarea ("ta") destegi ── */
$o1 = <<<'JS'
        return '<div class="vst-fl">'+esc(f[1])+'</div><input class="vst-in" data-f="'+f[0]+'" value="'+esc(d[f[0]]||"")+'" placeholder="'+esc(f[2]||"")+'">';
      }).join("")
JS;
$n1 = <<<'JS'
        if(f[3]==="ta"){ return '<div class="vst-fl">'+esc(f[1])+'</div><textarea class="vst-in vst-ta" data-f="'+f[0]+'" rows="4" placeholder="'+esc(f[2]||"")+'">'+esc(d[f[0]]||"")+'</textarea>'; } /*CH_VST_CUSTOM*/
        return '<div class="vst-fl">'+esc(f[1])+'</div><input class="vst-in" data-f="'+f[0]+'" value="'+esc(d[f[0]]||"")+'" placeholder="'+esc(f[2]||"")+'">';
      }).join("")
JS;
$c=0; $src=str_replace($o1,$n1,$src,$c);
echo ($c===1?"  ✓ [1] textarea tipi eklendi\n":"  ✗ [1] render anchor ($c)\n"); if($c!==1)$fail[]=1;

/* ── [2] handler: textarea[data-f] de dinlensin ── */
$o2 = "    p.querySelectorAll(\"input[data-f]\").forEach(function(el){\n"
    . "      var ev=el.type===\"checkbox\"?\"change\":\"input\";";
$n2 = "    p.querySelectorAll(\"input[data-f],textarea[data-f]\").forEach(function(el){ /*CH_VST_CUSTOM*/\n"
    . "      var ev=el.type===\"checkbox\"?\"change\":\"input\";";
$c=0; $src=str_replace($o2,$n2,$src,$c);
echo ($c===1?"  ✓ [2] textarea handler baglandi\n":"  ✗ [2] handler anchor ($c)\n"); if($c!==1)$fail[]=2;

/* ── [3] her sozlesmeye "Eigene Klauseln" adimi ── */
$o3 = "      CT[k].pgs=CT[k].steps.length;\n"
    . "    });\n"
    . "  }catch(e){} })();";
$n3 = "      CT[k].pgs=CT[k].steps.length;\n"
    . "    });\n"
    . "    Object.keys(CT).forEach(function(k){ /*CH_VST_CUSTOM — eigene Klauseln*/\n"
    . "      if(!CT[k]||CT[k].__uc) return; CT[k].__uc=1;\n"
    . "      CT[k].steps.push({t:\"Eigene Klauseln (optional) — Ihr eigener Wortlaut\",f:[\n"
    . "        [\"uc_t1\",\"Eigene Klausel 1 — Überschrift\",\"z. B. Sondervereinbarung\"],\n"
    . "        [\"uc_b1\",\"Eigene Klausel 1 — Wortlaut\",\"Geben Sie hier Ihren eigenen Klauseltext ein …\",\"ta\"],\n"
    . "        [\"uc_t2\",\"Eigene Klausel 2 — Überschrift (optional)\",\"\"],\n"
    . "        [\"uc_b2\",\"Eigene Klausel 2 — Wortlaut (optional)\",\"\",\"ta\"],\n"
    . "        [\"uc_t3\",\"Eigene Klausel 3 — Überschrift (optional)\",\"\"],\n"
    . "        [\"uc_b3\",\"Eigene Klausel 3 — Wortlaut (optional)\",\"\",\"ta\"]\n"
    . "      ]});\n"
    . "      CT[k].pgs=CT[k].steps.length;\n"
    . "    });\n"
    . "  }catch(e){} })();";
$c=0; $src=str_replace($o3,$n3,$src,$c);
echo ($c===1?"  ✓ [3] 'Eigene Klauseln' adimi her sozlesmeye eklendi\n":"  ✗ [3] adim-enjeksiyon anchor ($c)\n"); if($c!==1)$fail[]=3;

/* ── [4] optPages: custom klozlari da ekle ── */
$o4 = <<<'JS'
  function optPages(key,d){
    var m=VOPT[key]; if(!m||!CT[key]) return [];
    var sel=m.c.filter(function(o){ return (d[o[0]]||"")==="JA"; });
    if(!sel.length) return [];
    var b=head("ANLAGE 1","Zusatzvereinbarungen · "+CT[key].n);
    b+='<div style="font-size:11px;color:#555;margin:-8px 0 16px">Die folgenden von den Parteien gewählten Zusatzklauseln sind Bestandteil des Vertrages. Leerstellen (____) sind vor Unterzeichnung handschriftlich zu ergänzen.</div>';
    sel.forEach(function(o,i){ b+=par("§ Z"+(i+1),o[1],o[3]); });
    b+=sig2(d,m.p[0],m.p[1],"____________","____________");
    b+='<div class="vfoot"><span>'+esc(CT[key].n)+'</span><span>Anlage 1</span></div>';
    return ['<div class="vsA4">'+b+'</div>'];
  }
JS;
$n4 = <<<'JS'
  function optPages(key,d){ /*CH_VST_CUSTOM — VOPT + eigene Klauseln*/
    if(!CT[key]) return [];
    var m=VOPT[key];
    var sel=(m?m.c.filter(function(o){ return (d[o[0]]||"")==="JA"; }):[]);
    var uc=[];
    [["uc_t1","uc_b1"],["uc_t2","uc_b2"],["uc_t3","uc_b3"]].forEach(function(p){
      var t=(""+(d[p[0]]||"")).trim(), bd=(""+(d[p[1]]||"")).trim();
      if(bd) uc.push([t||"Eigene Vereinbarung", bd]);
    });
    if(!sel.length && !uc.length) return [];
    var parties=(m&&m.p)?m.p:["Partei 1","Partei 2"];
    var b=head("ANLAGE 1","Zusatzvereinbarungen · "+CT[key].n);
    b+='<div style="font-size:11px;color:#555;margin:-8px 0 16px">Die folgenden von den Parteien gewählten Zusatzklauseln sind Bestandteil des Vertrages. Leerstellen (____) sind vor Unterzeichnung handschriftlich zu ergänzen.</div>';
    var idx=0;
    sel.forEach(function(o){ idx++; b+=par("§ Z"+idx,o[1],o[3]); });
    uc.forEach(function(o){ idx++; b+=par("§ Z"+idx,o[0],o[1]); });
    b+=sig2(d,parties[0],parties[1],"____________","____________");
    b+='<div class="vfoot"><span>'+esc(CT[key].n)+'</span><span>Anlage 1</span></div>';
    return ['<div class="vsA4">'+b+'</div>'];
  }
JS;
$c=0; $src=str_replace($o4,$n4,$src,$c);
echo ($c===1?"  ✓ [4] optPages custom klozlari da uretiyor\n":"  ✗ [4] optPages anchor ($c)\n"); if($c!==1)$fail[]=4;

/* ── textarea stil (kucuk) ── */
$style = "<style id=\"ch-vst-custom-css\">/*CH_VST_CUSTOM*/ .vst-ta{min-height:88px;resize:vertical;line-height:1.5;font-family:inherit;padding:10px 12px}</style>";
$pos = strrpos($src,'</body>');
if ($pos!==false){ $src = substr($src,0,$pos).$style."\n".substr($src,$pos); }

if ($fail) { echo "\nHATA: adimlar eslesmedi: ".implode(', ',$fail)." — index.php DEGISTIRILMEDI.\n"; exit; }

$tmp = tempnam(sys_get_temp_dir(),'vc').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index.php degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
file_put_contents($file.'.bak-vstcustom-'.date('Ymd-His'), @file_get_contents($file));
file_put_contents($file,$src);
echo "\n✓ CH_VST_CUSTOM uygulandi. Her sozlesmede 'Eigene Klauseln' adimi; kullanici kendi\n";
echo "   maddesini yazar, ANLAGE 1'e § Z.. olarak eklenir (onizleme + PDF).\n";
