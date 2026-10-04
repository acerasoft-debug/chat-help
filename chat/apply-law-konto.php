<?php
/**
 * ChatHelp — apply-law-konto (CH_LAW_KONTO) — hukuk sistemi SADECE Konto'dan
 *  Kullanicilar dil ile hukuk ulkesini karistiriyor. Bu yama:
 *  [1] Hukuk-ulkesi karolari (.co / setCountry) Konto DISINDA her yerde gizlenir
 *      (#pnl-konto icindekiler aynen kalir/calisir).
 *  [2] Ust bar bayragi (tb-flag-emoji/tb-country-name) ve sidebar hukuk gostergesi
 *      (sb-clang) tiklaninca eski secici yerine KONTO paneli acilir.
 *  [3] Konto'daki "Gecerli hukuk" satirinin (#k-law-row) altina, CC_DATA'dan
 *      dinamik bayrakli SECICI eklenir (yalniz Konto'da .co karosu yoksa) —
 *      secim mevcut setCountry akisiyla yapilir (kalicilik kilidi dahil).
 *  DIL seciciye (setLang / fl-*) DOKUNULMAZ — dil bolumu ayni kalir.
 * KULLANIM: pull2.php?key=...&files=apply-law-konto.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-law-konto BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_LAW_KONTO')!==false) exit("Zaten ekli (CH_LAW_KONTO).\n");
if (strpos($src,'ch-konto-law')===false) exit("HATA: Konto hukuk satiri (CH_KONTO_LAW) yok.\n");

$bundle = <<<'HTML'
<style id="ch-law-konto-css">
/* CH_LAW_KONTO — hukuk-ulke karolari yalniz Konto'da */
.co{display:none !important}
#pnl-konto .co{display:revert !important}
#ch-klaw-pick{display:flex;flex-wrap:wrap;gap:7px;margin-top:8px}
#ch-klaw-pick .klp{display:inline-flex;align-items:center;gap:6px;padding:7px 11px;border-radius:100px;border:1px solid rgba(255,255,255,.16);background:var(--s2,#1b1d27);color:var(--t2,#e8e8f0);font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;transition:border-color .15s}
#ch-klaw-pick .klp:hover{border-color:var(--gold,#d4a84a)}
#ch-klaw-pick .klp.on{border-color:var(--gold,#d4a84a);background:rgba(212,168,74,.14);color:var(--gold,#e9c46a)}
#ch-klaw-hint{font-size:11px;color:var(--t4,#9a9aae);margin-top:6px}
</style>
<script id="ch-law-konto-js">
/* CH_LAW_KONTO — hukuk secimi Konto'ya tasindi; dil secici aynen durur */
try{(function(){
  function UIL(){ try{ return (window.chUIL?window.chUIL():(localStorage.getItem("ch_uilang")||"de")); }catch(e){ return "de"; } }
  var T={
    de:{pick:"Recht / Land ändern",hint:"Die Sprache der Oberfläche ändern Sie weiterhin über die Sprachauswahl — hier wählen Sie nur das RECHTSSYSTEM Ihrer Dokumente."},
    tr:{pick:"Hukuk sistemini değiştir",hint:"Arayüz dilini dil seçiciden değiştirmeye devam edersiniz — burada yalnız belgelerinizin HUKUK sistemini seçersiniz."},
    en:{pick:"Change law / country",hint:"You still change the interface language via the language selector — here you only choose the LEGAL SYSTEM for your documents."},
    fr:{pick:"Changer de droit / pays",hint:"La langue de l'interface se change toujours via le sélecteur de langue — ici vous choisissez uniquement le SYSTÈME JURIDIQUE de vos documents."},
    es:{pick:"Cambiar derecho / país",hint:"El idioma de la interfaz se cambia con el selector de idioma — aquí solo elige el SISTEMA JURÍDICO de sus documentos."},
    it:{pick:"Cambia diritto / paese",hint:"La lingua dell'interfaccia si cambia dal selettore lingua — qui scegli solo il SISTEMA GIURIDICO dei tuoi documenti."}
  };
  function t(){ return T[UIL()]||T.en; }
  function ccNow(){ try{ return (typeof CC!=="undefined"&&CC)?String(CC):"DE"; }catch(e){ return "DE"; } }

  /* [2] bayrak/gosterge tiklamalari -> Konto (eski secici yerine) */
  function redirect(id){
    try{
      var el=document.getElementById(id); if(!el) return;
      var btn=el.closest("[onclick]")||el.parentNode||el;
      if(btn._chLawK) return; btn._chLawK=1;
      btn.addEventListener("click",function(ev){
        try{ ev.stopImmediatePropagation(); ev.preventDefault(); }catch(e){}
        try{ gP("konto"); }catch(e){}
      }, true);
    }catch(e){}
  }

  /* [3] Konto'da dinamik hukuk secici (CC_DATA'dan) */
  function buildPicker(){
    try{
      var row=document.getElementById("k-law-row"); if(!row) return;
      if(document.querySelector("#pnl-konto .co")) return; /* eski karolar zaten Konto'daysa yenisi gereksiz */
      var pick=document.getElementById("ch-klaw-pick");
      if(!pick){
        pick=document.createElement("div"); pick.id="ch-klaw-pick"; pick.setAttribute("data-noi18n","1");
        var hint=document.createElement("div"); hint.id="ch-klaw-hint"; hint.setAttribute("data-noi18n","1");
        row.appendChild(pick); row.appendChild(hint);
      }
      var hintEl=document.getElementById("ch-klaw-hint");
      if(hintEl && hintEl.textContent!==t().hint) hintEl.textContent=t().hint;
      if(typeof CC_DATA==="undefined"||!CC_DATA) return;
      var cur=ccNow();
      /* chipleri kur/tazele */
      if(!pick._built){
        pick._built=1;
        for(var k in CC_DATA){
          (function(cc){
            var c=CC_DATA[cc]; if(!c) return;
            var b=document.createElement("button"); b.type="button"; b.className="klp"; b.setAttribute("data-klp",cc);
            b.innerHTML=(c.f||"")+" <span>"+(c.n||cc)+"</span>";
            b.addEventListener("click",function(){ try{ setCountry(cc); }catch(e){} });
            pick.appendChild(b);
          })(k);
        }
      }
      pick.querySelectorAll(".klp").forEach(function(b){
        b.classList.toggle("on", b.getAttribute("data-klp")===cur);
      });
    }catch(e){}
  }

  function tick(){
    redirect("tb-flag-emoji"); redirect("tb-country-name"); redirect("sb-clang");
    buildPicker();
  }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",tick); else tick();
  setInterval(tick, 1500);
})();}catch(e){}
</script>
HTML;

$pos = strrpos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$src = substr($src,0,$pos).$bundle."\n".substr($src,$pos);
echo "  ✓ [1] .co hukuk karolari Konto disinda gizlendi\n";
echo "  ✓ [2] ust bar bayragi + sb-clang tiklamasi Konto'ya yonlendirildi\n";
echo "  ✓ [3] Konto'ya CC_DATA'dan dinamik hukuk secici + aciklama eklendi\n";
echo "  ✓ dil secicilere dokunulmadi\n";

$tmp = tempnam(sys_get_temp_dir(),'lk').'.php';
file_put_contents($tmp,$src);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index degistirilmedi:\n  ".implode("\n  ",$lo)."\n"; exit; }
$bk=@file_put_contents($file.'.bak-lawkonto-'.date('Ymd-His'), @file_get_contents($file));
if ($bk===false) echo "  ⚠ yedek yazilamadi — devam\n";
$w=@file_put_contents($file,$src);
if ($w===false || $w<strlen($src)) { echo "\n✗ YAZMA HATASI: ".var_export($w,true)."\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_LAW_KONTO')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_LAW_KONTO diskte (".strlen($chk)." bayt)\n";
echo "\n✓ CH_LAW_KONTO uygulandi. Hukuk sistemi artik yalniz Konto'dan degistirilir;\n";
echo "   dil bolumu her yerde ayni kalir.\n";
