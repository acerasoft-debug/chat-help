<?php
/**
 * ChatHelp — apply-cv-photocrop (CH_CV_PHOTOCROP)
 *  CV Studio'nun foto yukleme alanina vesikalik/pasaport-fotosu kirpma araci
 *  ekler: kullanici herhangi bir foto secince, dogrudan kullanmak yerine
 *  surukle+yakinlastir ile sabit dikey oranda (35:45 — Alman biyometrik
 *  vesikalik standardi) kirpip onaylayabildigi bir modal acilir. Onaydan
 *  sonra canvas ile 420x540px cikti uretilip DATA.photo'ya yazilir.
 *  CT/openCropModal CV Studio'nun kendi kapali (closure) scope'unda
 *  oldugu icin zaten deploy edilmis ch-cv-studio script blogunu byte-tam
 *  str_replace ile cerrahi olarak genisletir.
 * KULLANIM: pull-updates.php?files=apply-cv-photocrop.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-cv-photocrop BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__ . '/index.php';
$src  = file_get_contents($file);
if ($src === false) exit("index.php okunamadi\n");
if (strpos($src, 'CH_CV_PHOTOCROP') !== false) exit("Zaten ekli (CH_CV_PHOTOCROP).\n");
if (strpos($src, 'CH_CV_STUDIO') === false) exit("HATA: CV Studio (CH_CV_STUDIO) henuz kurulu degil.\n");
file_put_contents($file . '.bak-cvcrop-' . date('Ymd-His'), $src);

$old = <<<'OLD'
    var fi=document.getElementById("cvs-file");
    if(fi) fi.addEventListener("change",function(){
      var f=fi.files&&fi.files[0]; if(!f) return;
      var rd=new FileReader();
      rd.onload=function(){ DATA.photo=String(rd.result); saveData(); var im=document.getElementById("cvs-phimg"); if(im)im.src=DATA.photo; schedPrev(); };
      rd.readAsDataURL(f);
    });
OLD;

$new = <<<'NEW'
    function openCropModal(dataUrl){
      var img=new Image();
      img.onload=function(){
        var VW=280, VH=360, OUTW=420, OUTH=540;
        var m=document.createElement("div"); m.className="jb-modal"; m.id="cvs-cropmodal";
        m.innerHTML='<div class="jb-sheet" style="max-width:340px">'
          +'<div class="jb-sheet-h"><b>📷 Vesikalık Foto — Kırp &amp; Ayarla</b></div>'
          +'<div class="jb-sheet-b" style="white-space:normal">'
          +'<div id="cvs-cropview" style="position:relative;width:'+VW+'px;height:'+VH+'px;margin:0 auto;overflow:hidden;border-radius:10px;border:2px solid var(--gold,#d4a84a);background:#111;touch-action:none;cursor:grab">'
          +'<img id="cvs-cropimg" style="position:absolute;left:0;top:0;transform-origin:0 0;user-select:none;pointer-events:none">'
          +'</div>'
          +'<input type="range" id="cvs-cropzoom" min="100" max="300" value="100" style="width:100%;margin-top:14px">'
          +'<div style="font-size:11px;color:var(--t3,#a8a8d0);text-align:center;margin-top:6px">Sürükle: konumla · Kaydırıcı: yakınlaştır — pasaport/vesikalık orana otomatik kırpılır</div>'
          +'</div>'
          +'<div class="jb-sheet-f"><button class="jb-mbtn" id="cvs-cropcancel">Abbrechen</button>'
          +'<button class="jb-mbtn p" id="cvs-cropok">✓ Übernehmen</button></div>'
          +'</div>';
        document.body.appendChild(m);
        var view=document.getElementById("cvs-cropview"), imEl=document.getElementById("cvs-cropimg"), zoom=document.getElementById("cvs-cropzoom");
        imEl.src=dataUrl;
        var natW=img.naturalWidth, natH=img.naturalHeight;
        var baseScale=Math.max(VW/natW, VH/natH);
        var scale=baseScale, tx=(VW-natW*scale)/2, ty=(VH-natH*scale)/2;
        function clamp(){
          var w=natW*scale, h=natH*scale;
          if(tx>0) tx=0; if(ty>0) ty=0;
          if(tx<VW-w) tx=VW-w; if(ty<VH-h) ty=VH-h;
        }
        function apply(){ imEl.style.width=natW+"px"; imEl.style.height=natH+"px"; imEl.style.transform="translate("+tx+"px,"+ty+"px) scale("+scale+")"; }
        clamp(); apply();
        zoom.addEventListener("input",function(){
          var f2=+zoom.value/100, newScale=baseScale*f2;
          var cx=VW/2, cy=VH/2, relX=(cx-tx)/scale, relY=(cy-ty)/scale;
          scale=newScale; tx=cx-relX*scale; ty=cy-relY*scale;
          clamp(); apply();
        });
        var dragging=false, sx=0, sy=0, stx=0, sty=0;
        view.addEventListener("pointerdown",function(e){ dragging=true; sx=e.clientX; sy=e.clientY; stx=tx; sty=ty; try{view.setPointerCapture(e.pointerId);}catch(er){} view.style.cursor="grabbing"; });
        view.addEventListener("pointermove",function(e){ if(!dragging) return; tx=stx+(e.clientX-sx); ty=sty+(e.clientY-sy); clamp(); apply(); });
        view.addEventListener("pointerup",function(){ dragging=false; view.style.cursor="grab"; });
        view.addEventListener("pointercancel",function(){ dragging=false; });
        document.getElementById("cvs-cropcancel").addEventListener("click",function(){ m.remove(); });
        document.getElementById("cvs-cropok").addEventListener("click",function(){
          var cvs=document.createElement("canvas"); cvs.width=OUTW; cvs.height=OUTH;
          var ctx=cvs.getContext("2d");
          var f3=OUTW/VW, outScale=scale*f3, outTx=tx*f3, outTy=ty*f3;
          ctx.fillStyle="#fff"; ctx.fillRect(0,0,OUTW,OUTH);
          ctx.drawImage(img, outTx, outTy, natW*outScale, natH*outScale);
          DATA.photo=cvs.toDataURL("image/jpeg",0.92);
          saveData();
          var pi=document.getElementById("cvs-phimg"); if(pi) pi.src=DATA.photo;
          schedPrev();
          m.remove();
        });
      };
      img.src=dataUrl;
    }
    var fi=document.getElementById("cvs-file");
    if(fi) fi.addEventListener("change",function(){
      var f=fi.files&&fi.files[0]; if(!f) return;
      var rd=new FileReader();
      rd.onload=function(){ openCropModal(String(rd.result)); };
      rd.readAsDataURL(f);
    });
NEW;

$n = 0;
$src = str_replace($old, $new, $src, $n);
if (!$n) exit("HATA: foto-yukleme ekleme noktasi bulunamadi — dosya degistirilmedi.\n");

$src = str_replace('<script id="ch-cv-studio">', "<script id=\"ch-cv-studio\">\n/* CH_CV_PHOTOCROP */", $src, $n2);

$tmp = tempnam(sys_get_temp_dir(), 'idx') . '.php';
file_put_contents($tmp, $src);
$lo = []; $rc = 0;
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lo, $rc);
@unlink($tmp);
if ($rc !== 0) { echo "LINT HATASI — index.php degistirilmedi:\n  " . implode("\n  ", $lo) . "\n"; exit; }
file_put_contents($file, $src);
echo "✓ CH_CV_PHOTOCROP uygulandi ($n nokta).\n";
echo "Test: Bewerbung & CV -> sablon sec -> foto yukle -> vesikalik kirpma penceresi acilmali (surukle + yakinlastir), Übernehmen -> onizlemede kirpilmis foto gorunmeli.\n";
