<?php
/**
 * ChatHelp — CEVAP/FOTO GUVENCESI + TEK BEKLEME MESAJI (CH_ANS_FALLBACK)
 * ------------------------------------------------------------------------
 *  Kanit (dump-gen-debug3): backend calisiyor ama genDoc'a BOS answers geliyor
 *  (sadece otomatik datum) ve photo_count=0. Duzeltme — genDoc EN DISTAN sarilir:
 *   1) ans ≤1 anahtarsa DOM'daki form alanlarindan ([data-k]) toplanir (son form
 *      kazanir, bos degerler atlanir; ch_empfaenger dahil).
 *   2) Yine ≤1 anahtar kaldiysa uretim ENGELLENIR + arayuz dilinde "formu doldur"
 *      uyarisi (jenerik/bos belge uretmek yerine) -> sonsuz bos-cagri dongusu de biter.
 *   3) Foto fallback: _chPhotos bos ama _chDocFiles doluysa .data iceren ogeler
 *      _chPhotos'a kopyalanir (backend Vision yolu kanitli calisiyor).
 *   4) Bekleme mesaji: cagridan once TUM eski #ch-wait-msg kaldirilir (yigilma
 *      biter — SADECE 1 gorunur) ve metin ARAYUZ dilinde yazilir (ar/ru dahil).
 *  Sarma sirasi: CH_PROFILE_REQ kurulduktan sonra en dista; __wait/__profreq
 *  bayraklarini tasir (cifte sarma olmaz).
 * KULLANIM: pull-updates.php?files=apply-ans-fallback.php&run=1. SİL.
 */
header("Content-Type: text/plain; charset=UTF-8");
@ini_set("display_errors","1"); error_reporting(E_ALL);
echo "apply-ans-fallback BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__."/index.php";
if(!file_exists($file)) exit("index.php yok\n");
$src=file_get_contents($file); $start=$src;
if(strpos($src,"CH_ANS_FALLBACK")!==false) exit("Zaten ekli (CH_ANS_FALLBACK).\n");
$anchor="function getDocPrice(dk){ return DOC_TIER[dk]||'standard'; }";
if(strpos($src,$anchor)===false) exit("anchor yok\n");
file_put_contents($file.".bak-ansfb-".date("Ymd-His"),$src);

$js=<<<'CHJS'

/* CH_ANS_FALLBACK — bos cevap engeli + DOM'dan cevap/foto toplama + tek bekleme mesaji */
try{(function(){
  function UIL(){ try{ var s=localStorage.getItem("ch_uilang"); if(s) return s; }catch(e){} try{ if(typeof UL!=="undefined"&&UL) return UL; }catch(e){} return "de"; }
  var WAIT2={de:"Bitte warten, Ihr Dokument wird erstellt …",tr:"Lütfen bekleyin, belgeniz hazırlanıyor …",en:"Please wait, your document is being created …",fr:"Veuillez patienter, votre document est en cours de création …",es:"Espere por favor, su documento se está creando …",it:"Attendere, il documento è in fase di creazione …",nl:"Even geduld, uw document wordt aangemaakt …",pl:"Proszę czekać, dokument jest tworzony …",sv:"Vänta, ditt dokument skapas …",pt:"Aguarde, o seu documento está a ser criado …",ru:"Пожалуйста, подождите, ваш документ создаётся …",ar:"يرجى الانتظار، جارٍ إنشاء مستندك …"};
  var FORMMSG={de:"⚠️ Bitte füllen Sie zuerst die Fragen des Dokumentformulars aus — das Schreiben wird aus genau diesen Angaben erstellt. Wählen Sie das Dokument im Menü und beantworten Sie die Felder.",
    tr:"⚠️ Lütfen önce belge formundaki soruları doldurun — dilekçe tam olarak bu bilgilerle hazırlanır. Menüden belgeyi seçip alanları cevaplayın.",
    en:"⚠️ Please fill in the document form questions first — the letter is built from exactly these details. Pick the document from the menu and answer the fields.",
    fr:"⚠️ Veuillez d'abord remplir le formulaire du document — le courrier est rédigé à partir de ces informations.",
    es:"⚠️ Rellene primero las preguntas del formulario — el escrito se redacta con esos datos.",
    it:"⚠️ Compili prima le domande del modulo — il documento viene creato da questi dati.",
    nl:"⚠️ Vul eerst de vragen van het documentformulier in — de brief wordt uit deze gegevens opgesteld.",
    pl:"⚠️ Najpierw wypełnij pytania formularza — pismo powstaje z tych danych.",
    sv:"⚠️ Fyll först i formulärfrågorna — skrivelsen skapas av dessa uppgifter.",
    pt:"⚠️ Preencha primeiro as perguntas do formulário — a carta é criada a partir desses dados.",
    ru:"⚠️ Сначала заполните вопросы формы — документ создаётся именно из этих данных.",
    ar:"⚠️ يرجى أولاً تعبئة أسئلة نموذج المستند — يُنشأ الخطاب من هذه البيانات."};

  function clearWaits(){
    try{
      document.querySelectorAll('#ch-wait-msg, [id="ch-wait-msg"]').forEach(function(wm){
        try{ (wm.closest(".msg")||wm).remove(); }catch(e){ try{ wm.remove(); }catch(e2){} }
      });
    }catch(e){}
  }
  function localizeWait(){
    try{
      var wm=document.getElementById("ch-wait-msg"); if(!wm) return;
      var tx=wm.querySelector("div:last-child"); if(tx) tx.textContent=WAIT2[UIL()]||WAIT2.de;
    }catch(e){}
  }
  function collectFromDOM(){
    var out={};
    try{
      var root=document.getElementById("msgs")||document;
      root.querySelectorAll("[data-k]").forEach(function(el){
        try{
          var k=el.getAttribute("data-k"); if(!k) return;
          var v=(el.value!=null?el.value:"").toString().trim();
          if(v) out[k]=v; /* sonra gelen (en yeni form) ayni anahtari ezer */
        }catch(e){}
      });
    }catch(e){}
    return out;
  }
  function mergePhotos(){
    try{
      var have=window._chPhotos && Object.keys(window._chPhotos).length;
      var files=window._chDocFiles;
      if(!have && files && files.length){
        window._chPhotos=window._chPhotos||{};
        files.forEach(function(o,i){ if(o && o.data) window._chPhotos["fb"+i]=o; });
      }
    }catch(e){}
  }

  function wrap(){
    try{
      if(typeof genDoc!=="function" || genDoc.__ansfb) return false;
      if(!genDoc.__profreq && wrap.__patience>0){ wrap.__patience--; return false; }
      var _g=genDoc;
      var w=async function(dk,ans){
        try{
          ans=(ans&&typeof ans==="object")?ans:{};
          var keys=Object.keys(ans).filter(function(k){ return k!=="datum" && (""+(ans[k]==null?"":ans[k])).trim()!==""; });
          if(keys.length<1){
            var dom=collectFromDOM();
            for(var k in dom){ if(!ans[k] || !(""+ans[k]).trim()) ans[k]=dom[k]; }
            keys=Object.keys(ans).filter(function(k2){ return k2!=="datum" && (""+(ans[k2]==null?"":ans[k2])).trim()!==""; });
          }
          if(keys.length<1){
            clearWaits();
            try{ if(typeof addMsg==="function") addMsg("ai", FORMMSG[UIL()]||FORMMSG.en); }catch(e){}
            return; /* jenerik/bos belge URETME */
          }
          mergePhotos();
        }catch(e){}
        clearWaits(); /* eski yigilmis bekleme mesajlarini sil — yenisi 1 kez gelecek */
        var p=_g.call(this,dk,ans); /* inner senkron kismi bekleme mesajini ekler */
        localizeWait();
        return await p;
      };
      w.__ansfb=1; w.__wait=1; w.__profreq=1;
      genDoc=w; if(typeof window!=="undefined") window.genDoc=w;
      return true;
    }catch(e){ return false; }
  }
  wrap.__patience=30; /* ~15sn: profil sarmalayicisini bekle, sonra yine de sar */
  var t=0; function run(){ if(!wrap() && t++<70) setTimeout(run,500); }
  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",run); else run();
})();}catch(e){}
CHJS;
$src=str_replace($anchor,$anchor.$js,$src);
if($src===$start) exit("degisiklik yok!\n");

$tmp=$file.".tmp-af"; file_put_contents($tmp,$src);
$out=[]; $code=0; exec("php -l ".escapeshellarg($tmp)." 2>&1",$out,$code); @unlink($tmp);
if($code!==0){ echo "✗ LINT HATASI — DEGISTIRILMEDI:\n".implode("\n",$out)."\n"; exit; }
file_put_contents($file,$src);
echo "✓ Cevap/foto guvencesi eklendi (CH_ANS_FALLBACK):\n";
echo "  1) Bos cevapla uretim ENGELLENIR (arayuz dilinde 'formu doldur' uyarisi)\n";
echo "  2) Cevaplar DOM'daki formdan otomatik toplanir (closure kopsa bile)\n";
echo "  3) Foto fallback: _chDocFiles -> _chPhotos\n";
echo "  4) Bekleme mesaji: eski yigilanlar silinir, SADECE 1 tane, ARAYUZ dilinde (ar/ru dahil)\n";
echo "SIL: rm apply-ans-fallback.php\n";
echo "Test: Kundigung ac -> alanlari doldur -> uret -> TEK bekleme mesaji (sectigin arayuz dilinde),\n";
echo "      belgede TUM girdigin degerler; alanlari bos birakip zorla denersen 'formu doldur' uyarisi.\n";
