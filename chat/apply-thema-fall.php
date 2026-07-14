<?php
/**
 * ChatHelp — apply-thema-fall (CH_THEMA_FALL) — Meine Themen -> Meine Fälle aktar.
 *  (a) index.php: Meine Themen (loadHistory) listesindeki her belgeye Löschen'in
 *      yanina "→ Fall" butonu ekler.
 *  (b) EKLEMELI script: window.chThemaToFall(id,btn) — belgeyi history'den bulup
 *      Meine Fälle'ye (localStorage ch_falls_v1) yeni bir Fall olarak ekler
 *      (baslik + belge metni). Ayni konu iki kez eklenmez.
 * KULLANIM: pull2.php?key=...&files=apply-thema-fall.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-thema-fall BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_THEMA_FALL')!==false) exit("Zaten ekli (CH_THEMA_FALL).\n");

/* (a) render'a buton ekle */
$old = '<button class="ab del" onclick="delDoc(\'${d.id}\',this)">';
$new = '<button class="ab" onclick="chThemaToFall(\'${d.id}\',this)" title="Meine Fälle">⚖️ Fall</button>'.$old;
$c = substr_count($src,$old);
if ($c!==1) exit("HATA: delDoc buton anchor $c kez (1 bekleniyordu) — index DEGISTIRILMEDI.\n");
$src = str_replace($old,$new,$src);
echo "  ✓ (a) Meine Themen listesine '→ Fall' butonu eklendi\n";

/* (b) script */
$block = <<<'HTMLBLOCK'
<script id="ch-thema-fall">
/* CH_THEMA_FALL — Meine Themen -> Meine Fälle aktarma */
try{(function(){
  var FK='ch_falls_v1';
  function API(){ return (typeof window.API!=='undefined'&&window.API)?window.API:'api.php'; }
  function lang(){ try{ return (localStorage.getItem('ch_uilang')||'de').slice(0,2); }catch(e){ return 'de'; } }
  function L(o){ return o[lang()]||o.de; }
  var MSG={
    moved:{de:'Thema nach „Meine Fälle" verschoben.',tr:'Konu "Meine Fälle"ye aktarıldı.',en:'Topic moved to "Meine Fälle".',fr:'Sujet déplacé vers « Meine Fälle ».',es:'Tema movido a "Meine Fälle".'},
    already:{de:'Dieses Thema ist bereits in „Meine Fälle".',tr:'Bu konu zaten "Meine Fälle"de.',en:'This topic is already in "Meine Fälle".',fr:'Déjà dans « Meine Fälle ».',es:'Ya está en "Meine Fälle".'},
    fail:{de:'Konnte nicht übertragen werden.',tr:'Aktarılamadı.',en:'Could not transfer.',fr:'Transfert impossible.',es:'No se pudo transferir.'}
  };
  window.chThemaToFall=function(id,btn){
    try{
      fetch(API()+'?action=history').then(function(r){return r.json();}).then(function(data){
        var docs=(data&&data.docs)||[], d=null;
        for(var i=0;i<docs.length;i++){ if(String(docs[i].id)===String(id)){ d=docs[i]; break; } }
        if(!d){ alert(L(MSG.fail)); return; }
        var all=[]; try{ all=JSON.parse(localStorage.getItem(FK)||'[]'); if(!Array.isArray(all)) all=[]; }catch(e){}
        for(var j=0;j<all.length;j++){ if(all[j] && all[j].__fromThema===String(id)){ alert(L(MSG.already)); return; } }
        var title='';
        try{ title = d.docName || (window.DOCS&&DOCS[d.docType]&&DOCS[d.docType].n) || 'Thema'; }catch(e){ title='Thema'; }
        var body = d.document || d.preview || '';
        all.push({ id:'f'+Date.now(), title:String(title).slice(0,80), messages:[], document:body, createdAt:Date.now(), __fromThema:String(id) });
        try{ localStorage.setItem(FK, JSON.stringify(all)); }catch(e){ alert(L(MSG.fail)); return; }
        if(btn){ try{ btn.textContent='✓'; btn.disabled=true; btn.style.opacity='.6'; }catch(e){} }
        alert(L(MSG.moved));
      }).catch(function(){ alert(L(MSG.fail)); });
    }catch(e){ alert(L(MSG.fail)); }
  };
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> yok.\n");
$new2 = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'tf').'.php';
file_put_contents($tmp,$new2);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
$bk=@file_put_contents($file.'.bak-themafall-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new2);
if ($w===false || $w<strlen($new2)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_THEMA_FALL')===false || strpos($chk,'chThemaToFall')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ (b) chThemaToFall eklendi\n";
echo "  ✓ DOGRULAMA: CH_THEMA_FALL diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Meine Themen'deki her konu 'Fall' butonuyla Meine Fälle'ye aktarilabilir.\n";
