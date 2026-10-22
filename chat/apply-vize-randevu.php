<?php
/**
 * ChatHelp — apply-vize-randevu (CH_VIZE_RDV) — Vize Asistani sonucuna (adim 4)
 *  estetik "📅 Randevu Al" karti: secilen ulkeye gore RESMI randevu portalina
 *  yonlendirir + adim rehberi. Randevu basvuranin kendisi tarafindan alinir
 *  (yasal/uyumlu). Ulke, #vz-ov .vz-h basligindan okunur; checklist (.vz-cl-grp)
 *  gorununce enjekte edilir. Additive, MutationObserver YOK (freeze-safe),
 *  idempotent. Gomulu JS node --check ✓.
 * KULLANIM: pull2.php?key=...&files=apply-vize-randevu.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-vize-randevu BASLADI OK (PHP ".PHP_VERSION.")\n\n";

$file = __DIR__.'/index.php';
$src = @file_get_contents($file);
if ($src===false) exit("index.php okunamadi\n");
if (strpos($src,'CH_VIZE_RDV')!==false) exit("Zaten ekli (CH_VIZE_RDV).\n");
if (strpos($src,'chVizeAsistan')===false) exit("HATA: Vize Asistani yok — once apply-vize-asistan/vize-ux gerekli.\n");

$block = <<<'HTMLBLOCK'
<style id="ch-vize-rdv-css">
/* CH_VIZE_RDV — Vize Asistani 'Randevu Al' karti */
#vz-randevu{margin-top:6px}
.vz-rdv-btn{display:flex;align-items:center;gap:12px;text-decoration:none;
  background:linear-gradient(135deg,#d4a84a,#ecc060);color:#07070e;border-radius:14px;
  padding:14px 16px;margin:8px 0 10px;font-weight:800;box-shadow:0 6px 20px rgba(212,168,74,.28)}
.vz-rdv-btn:hover{filter:brightness(1.05)}
.vz-rdv-ic{font-size:22px;line-height:1}
.vz-rdv-t{flex:1;display:flex;flex-direction:column;min-width:0}
.vz-rdv-t .a{font-size:14.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.vz-rdv-t .b{font-size:11.5px;font-weight:700;opacity:.82;margin-top:2px}
.vz-rdv-go{font-size:18px}
.vz-rdv-steps{font-size:11.5px;color:#9a9ab0;line-height:1.7;background:#12122a;
  border:1px solid rgba(255,255,255,.07);border-radius:11px;padding:10px 12px}
.vz-rdv-note{font-size:11px;color:#8e8e9c;margin-top:8px;line-height:1.5}
</style>
<script id="ch-vize-rdv-js">
/* CH_VIZE_RDV — freeze-safe: gozlemci yok, idempotent, kisa dongu */
try{(function(){
  /* Ulke (basliktaki TR ad) -> resmi randevu portali. Kokler tercih (canli/kararli). */
  var PORTALS=[
    {m:'Almanya',    n:'iData — Almanya vizesi',            u:'https://www.idata.com.tr'},
    {m:'Fransa',     n:'France-Visas (resmi)',              u:'https://france-visas.gouv.fr'},
    {m:'İtalya',     n:'iData — İtalya vizesi',             u:'https://www.idata.com.tr'},
    {m:'İspanya',    n:'BLS Spain Visa',                    u:'https://www.blsspainvisa.com'},
    {m:'Hollanda',   n:'VFS Global — Hollanda',             u:'https://www.vfsglobal.com'},
    {m:'Yunanistan', n:'Yunanistan Konsolosluğu / VFS',     u:'https://www.vfsglobal.com'},
    {m:'ABD',        n:'U.S. Travel Docs (resmi)',          u:'https://www.ustraveldocs.com/tr/'},
    {m:'İngiltere',  n:'gov.uk — UK Visa (resmi)',          u:'https://www.gov.uk/apply-uk-visa'},
    {m:'Kanada',     n:'IRCC — Kanada (resmi)',             u:'https://www.canada.ca/en/services/immigration-citizenship.html'},
    {m:'Avustralya', n:'ImmiAccount (resmi)',               u:'https://immi.homeaffairs.gov.au'},
    {m:'Schengen',   n:'Resmi Schengen randevu portalı',    u:'https://www.vfsglobal.com', note:1}
  ];
  function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
  function match(name){
    for(var i=0;i<PORTALS.length;i++){ if(name.indexOf(PORTALS[i].m)>=0) return PORTALS[i]; }
    return null;
  }
  function html(p){
    var extra = p.note ? '<div class="vz-rdv-note">ℹ️ Genel Schengen için önce hedef ülkeyi seçerseniz doğrudan o ülkenin resmi portalına yönlendiririz.</div>' : '';
    return '<div class="vz-sec-t">📅 Randevu Al</div>'
      +'<div class="vz-sub" style="margin-top:-4px">Belgeleriniz hazır olduğunda randevunuzu <b>resmi portaldan</b> alın. Randevu, başvuranın kendisi tarafından alınır.</div>'
      +'<a class="vz-rdv-btn" href="'+esc(p.u)+'" target="_blank" rel="noopener noreferrer">'
      +'<span class="vz-rdv-ic">📅</span>'
      +'<span class="vz-rdv-t"><span class="a">'+esc(p.n)+'</span><span class="b">Resmi randevu portalını açar</span></span>'
      +'<span class="vz-rdv-go">↗</span></a>'
      +'<div class="vz-rdv-steps">1️⃣ Hesap/başvuru oluştur &nbsp;·&nbsp; 2️⃣ Formu doldur &nbsp;·&nbsp; 3️⃣ Ücreti öde &nbsp;·&nbsp; 4️⃣ Uygun randevu saatini seç &nbsp;·&nbsp; 5️⃣ Belgelerinizle randevuya gidin</div>'
      +extra
      +'<div class="vz-rdv-note">⚠️ ChatHelp randevu almaz; sizi yalnızca <b>resmi</b> sayfaya yönlendirir. Portal adresini teyit edin.</div>';
  }
  function work(){
    try{
      var ov=document.getElementById('vz-ov');
      if(!ov || !ov.classList.contains('on')){ return; }
      var body=ov.querySelector('.vz-body'); if(!body) return;
      var isResult = !!ov.querySelector('.vz-cl-grp'); /* checklist => adim 4 */
      var old=ov.querySelector('#vz-randevu');
      if(!isResult){ if(old) old.parentNode.removeChild(old); return; }
      if(old) return;                                  /* zaten var */
      var h=ov.querySelector('.vz-h'); var name=(h&&h.textContent)||'';
      var p=match(name); if(!p) return;
      var sec=document.createElement('div'); sec.id='vz-randevu'; sec.innerHTML=html(p);
      var nav=body.querySelector('.vz-nav');           /* 'Bitti' satiri ustune */
      if(nav && nav.parentNode===body) body.insertBefore(sec, nav); else body.appendChild(sec);
    }catch(e){}
  }
  var n=0;(function loop(){ work(); if(n++<5000) setTimeout(loop,550); })();
})();}catch(e){}
</script>
HTMLBLOCK;

$pos = strripos($src,'</body>');
if ($pos===false) exit("HATA: </body> bulunamadi — index DEGISTIRILMEDI.\n");
$new = substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp = tempnam(sys_get_temp_dir(),'rd').'.php';
file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if ($rc!==0) { echo "\nLINT HATASI — index DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }
@file_put_contents($file.'.bak-vizerdv-'.date('Ymd-His'), $src);
$w=@file_put_contents($file,$new);
if ($w===false || $w<strlen($new)) { echo "\n✗ YAZMA HATASI.\n"; exit; }
$chk=(string)@file_get_contents($file);
if (strpos($chk,'CH_VIZE_RDV')===false || strpos($chk,'vz-rdv-btn')===false) { echo "\n✗ DOGRULAMA BASARISIZ.\n"; exit; }
echo "  ✓ DOGRULAMA: CH_VIZE_RDV diskte (".strlen($chk)." bayt)\n";
echo "\n✓ Vize Asistani sonucuna estetik '📅 Randevu Al' karti eklendi (ulkeye gore resmi portal + adim rehberi).\n";
