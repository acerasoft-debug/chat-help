<?php
/**
 * ChatHelp — apply-suggest-btn (CH_SUGGEST_FIX) — [[SUGGEST:doc|cv|vst|jobs]]
 *  ham sizintisini + "baglanti yok" bug'ini duzelt. Mevcut frontend marker'i
 *  sadece siliyordu (satir sonunda) ve buton uretmiyordu; satir ortasinda ise
 *  ham metin siziyordu. EKLEMELI katman: AI mesaj balonlarinda her konumdaki
 *  [[SUGGEST:x]] marker'ini kaldirir ve tiklanabilir premium butona cevirir.
 *   doc  -> AI ile belge olusturmaya devam (mevcut [[DOC]] akisi)
 *   cv   -> gP('cv') | vst -> gP('vst') | jobs -> gP('jobs')
 *  Gomulu JS node --check ile dogrulandi.
 * KULLANIM: pull2.php?key=...&files=apply-suggest-btn.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-suggest-btn BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_SUGGEST_FIX')!==false) exit("Zaten ekli (CH_SUGGEST_FIX).\n");

$block = <<<'HTMLBLOCK'
<style id="ch-suggest-fix-css">
/* CH_SUGGEST_FIX */
.ch-suggest-wrap{margin-top:12px;display:flex;flex-wrap:wrap;gap:8px}
.ch-suggest-btn{display:inline-flex;align-items:center;gap:8px;padding:11px 18px;border-radius:12px;border:1px solid rgba(212,168,74,.42);background:linear-gradient(135deg,rgba(212,168,74,.16),rgba(212,168,74,.05));color:var(--gold,#d4a84a);font-weight:700;font-size:14px;cursor:pointer;font-family:inherit;transition:transform .15s,box-shadow .15s,background .15s,color .15s}
.ch-suggest-btn:hover{background:linear-gradient(135deg,#c49038,#e8b840);color:#1a0e00;transform:translateY(-1px);box-shadow:0 6px 18px rgba(212,168,74,.28)}
</style>
<script id="ch-suggest-fix-js">
/* CH_SUGGEST_FIX — [[SUGGEST:x]] -> tiklanabilir buton */
try{(function(){
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  var LBL={
    doc:{de:"📄 Dokument erstellen",tr:"📄 Belge oluştur",en:"📄 Create document",fr:"📄 Créer le document",es:"📄 Crear documento",it:"📄 Crea documento",ru:"📄 Создать документ",ar:"📄 إنشاء المستند",nl:"📄 Document maken",pl:"📄 Utwórz dokument",pt:"📄 Criar documento",sv:"📄 Skapa dokument"},
    cv:{de:"📄 Lebenslauf erstellen",tr:"📄 CV oluştur",en:"📄 Create CV",fr:"📄 Créer le CV",es:"📄 Crear CV",it:"📄 Crea CV",ru:"📄 Создать резюме",ar:"📄 إنشاء السيرة",nl:"📄 CV maken",pl:"📄 Utwórz CV",pt:"📄 Criar CV",sv:"📄 Skapa CV"},
    vst:{de:"📄 Vertrag erstellen",tr:"📄 Sözleşme oluştur",en:"📄 Create contract",fr:"📄 Créer le contrat",es:"📄 Crear contrato",it:"📄 Crea contratto",ru:"📄 Создать договор",ar:"📄 إنشاء العقد",nl:"📄 Contract maken",pl:"📄 Utwórz umowę",pt:"📄 Criar contrato",sv:"📄 Skapa avtal"},
    jobs:{de:"🔍 Jobs finden",tr:"🔍 İş ara",en:"🔍 Find jobs",fr:"🔍 Trouver un emploi",es:"🔍 Buscar empleo",it:"🔍 Trova lavoro",ru:"🔍 Найти работу",ar:"🔍 البحث عن وظائف",nl:"🔍 Vind banen",pl:"🔍 Znajdź pracę",pt:"🔍 Encontrar empregos",sv:"🔍 Hitta jobb"}
  };
  var DOCMSG={de:"Ja, bitte erstellen Sie das Dokument jetzt mit mir.",tr:"Evet, lütfen belgeyi şimdi benimle birlikte oluşturun.",en:"Yes, please create the document now.",fr:"Oui, veuillez créer le document maintenant.",es:"Sí, por favor cree el documento ahora.",it:"Sì, crea il documento adesso.",ru:"Да, пожалуйста, создайте документ сейчас.",ar:"نعم، من فضلك أنشئ المستند الآن.",nl:"Ja, maak het document nu.",pl:"Tak, proszę utworzyć dokument teraz.",pt:"Sim, crie o documento agora.",sv:"Ja, skapa dokumentet nu."};
  function label(kind){ var m=LBL[kind]||LBL.doc; return m[lang()]||m.de; }
  function act(kind){
    try{
      if(kind==='cv'  && typeof gP==='function'){ gP('cv');  return; }
      if(kind==='vst' && typeof gP==='function'){ gP('vst'); return; }
      if(kind==='jobs'&& typeof gP==='function'){ gP('jobs');return; }
      /* doc: mevcut AI belge akisina devam et */
      var inp=document.getElementById('inp');
      var msg=DOCMSG[lang()]||DOCMSG.de;
      if(inp){ inp.value=msg; try{ if(typeof onInp==='function') onInp(inp); }catch(e){} }
      if(typeof doSend==='function') doSend();
      else if(typeof window.doSend==='function') window.doSend();
    }catch(e){}
  }
  var RE=/\[\[SUGGEST:(doc|cv|vst|jobs)\]\]/;
  var REG=/\[\[SUGGEST:(doc|cv|vst|jobs)\]\]/g;
  function processBbl(bbl){
    try{
      if(!bbl || bbl.nodeType!==1 || bbl.__sug) return;
      var html=bbl.innerHTML;
      if(!html || html.indexOf('[[SUGGEST:')<0) return;
      var m=html.match(RE);
      if(!m) return;
      bbl.__sug=1;
      var kind=m[1];
      bbl.innerHTML = html.replace(REG,'').replace(/(<br\s*\/?>\s*){3,}/gi,'<br><br>');
      var wrap=document.createElement('div'); wrap.className='ch-suggest-wrap';
      var b=document.createElement('button'); b.type='button'; b.className='ch-suggest-btn';
      b.textContent=label(kind);
      b.addEventListener('click',function(){ act(kind); });
      wrap.appendChild(b); bbl.appendChild(wrap);
    }catch(e){}
  }
  function sweep(root){
    try{
      var r=root||document;
      if(r.querySelectorAll) r.querySelectorAll('.bbl').forEach(processBbl);
      if(r.nodeType===1 && r.classList && r.classList.contains('bbl')) processBbl(r);
    }catch(e){}
  }
  sweep(document);
  try{
    if(window.MutationObserver){
      new MutationObserver(function(muts){
        for(var i=0;i<muts.length;i++){
          var a=muts[i].addedNodes; if(!a) continue;
          for(var j=0;j<a.length;j++){ var n=a[j]; if(n && n.nodeType===1) sweep(n); }
        }
      }).observe(document.body,{childList:true,subtree:true});
    }
  }catch(e){}
  /* emniyet: gec render icin kisa suren tarama */
  var n=0;(function loop(){ sweep(document); if(n++<40) setTimeout(loop,800); })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'sg').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-suggestbtn-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_SUGGEST_FIX')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_SUGGEST_FIX diskte (".strlen($chk)." bayt)\n";
echo "\n✓ [[SUGGEST:x]] artik tiklanabilir butona cevriliyor; ham sizinti temizlendi.\n";
