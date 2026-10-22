<?php
/**
 * ChatHelp — apply-intro (CH_INTRO) — "So entsteht Ihr Schreiben" giris intro'su.
 *  Ilk girişte BİR KEZ oynar (localStorage ch_intro_story_v1), sonra ana sayfaya geçer.
 *  Tamamen additive + fail-open: enjekte edilen script hata verirse HİÇBİR ŞEY gösterilmez,
 *  site normal calisir. Cinematic tek-tema (lacivert+altin). </body> capasi (son occurrence).
 * KULLANIM: pull2.php?key=...&files=apply-intro.php
 */
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ERROR | E_PARSE);
echo "apply-intro BASLADI OK (PHP ".PHP_VERSION.")\n\n";
$file=__DIR__.'/index.php';
$src=@file_get_contents($file);
if($src===false) exit("index.php okunamadi\n");
if(strpos($src,'CH_INTRO')!==false) exit("Zaten ekli (CH_INTRO). Once apply-introoff calistir.\n");

$block=<<<'BLOCK'
<script>/*CH_INTRO — ChatHelp giris intro'su (bir kez, fail-open)*/(function(){try{
  var KEY='ch_intro_story_v1';
  try{ if(localStorage.getItem(KEY)) return; }catch(e){ return; }
  var seen=false;
  function done(){ if(seen) return; seen=true; try{localStorage.setItem(KEY,'1');}catch(e){}
    var ov=document.getElementById('chiov'); if(ov){ ov.style.opacity='0';
      setTimeout(function(){ try{ov.remove();}catch(_){} },440); } }
  function build(){
    if(document.getElementById('chiov')) return;
    var reduce=false; try{reduce=window.matchMedia('(prefers-reduced-motion:reduce)').matches;}catch(e){}
    var css=""
    +"#chiov{position:fixed;inset:0;z-index:2147483000;display:flex;align-items:center;justify-content:center;padding:22px;overflow:auto;-webkit-overflow-scrolling:touch;opacity:1;transition:opacity .44s ease;background:radial-gradient(120% 90% at 50% -8%,rgba(24,52,96,.55),transparent 60%),rgba(6,13,26,.9);backdrop-filter:blur(9px);-webkit-backdrop-filter:blur(9px);font-family:system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif}"
    +"#chiov *{box-sizing:border-box}"
    +".chiov-p{width:100%;max-width:540px;margin:auto;background:linear-gradient(168deg,#122A4B,#081326);border:1px solid rgba(214,175,106,.3);border-radius:24px;padding:34px 30px 30px;box-shadow:0 40px 90px -40px rgba(0,0,0,.85),0 0 0 1px rgba(255,255,255,.03) inset;color:#EAF0F8;text-align:center}"
    +".chiov-seal{width:60px;height:60px;margin:0 auto 16px;display:block}"
    +".chiov-eb{font-size:.68rem;letter-spacing:.3em;text-transform:uppercase;color:#D6AF6A;font-weight:600;display:inline-flex;align-items:center;gap:.7em}"
    +".chiov-eb:before,.chiov-eb:after{content:'';width:22px;height:1px;background:#D6AF6A;opacity:.55}"
    +".chiov-h{font-family:ui-serif,'Iowan Old Style',Georgia,serif;font-weight:600;font-size:clamp(1.5rem,1.1rem+2vw,2rem);line-height:1.12;letter-spacing:-.01em;margin:.55em 0 .2em;text-wrap:balance}"
    +".chiov-h em{font-style:italic;color:#E4C688}"
    +".chiov-sub{color:#A9BAD2;font-size:.96rem;max-width:40ch;margin:0 auto 22px;line-height:1.55}"
    +".chiov-steps{text-align:left;position:relative;margin:0 0 20px;padding-left:4px}"
    +".chiov-steps:before{content:'';position:absolute;left:20px;top:12px;bottom:22px;width:2px;background:linear-gradient(#D6AF6A,rgba(214,175,106,.12));border-radius:2px;opacity:.5}"
    +".chiov-step{position:relative;display:grid;grid-template-columns:38px 1fr;gap:14px;align-items:start;padding:0 0 16px;opacity:0;transform:translateY(10px);transition:opacity .5s cubic-bezier(.2,.7,.2,1),transform .5s cubic-bezier(.2,.7,.2,1)}"
    +".chiov-step.in{opacity:1;transform:none}"
    +".chiov-nd{width:38px;height:38px;border-radius:50%;background:#0C1C34;border:1.5px solid rgba(214,175,106,.7);display:grid;place-items:center;z-index:1;box-shadow:0 0 0 4px rgba(214,175,106,.1)}"
    +".chiov-nd svg{width:18px;height:18px;stroke:#E4C688;fill:none;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}"
    +".chiov-num{font-family:ui-serif,Georgia,serif;font-size:.66rem;letter-spacing:.16em;color:#D6AF6A;font-weight:600;display:block;margin-bottom:.2em}"
    +".chiov-st{font-family:ui-serif,'Iowan Old Style',Georgia,serif;color:#fff;font-weight:600;font-size:1.02rem;margin:0 0 .1em;line-height:1.2}"
    +".chiov-sd{color:#9FB2CC;font-size:.86rem;margin:0;line-height:1.5}"
    +".chiov-chips{display:flex;flex-wrap:wrap;gap:8px;justify-content:center;margin:0 0 24px}"
    +".chiov-chip{display:inline-flex;align-items:center;gap:6px;font-size:.8rem;color:#EAF0F8;background:rgba(255,255,255,.05);border:1px solid rgba(214,175,106,.28);border-radius:999px;padding:7px 13px}"
    +".chiov-chip svg{width:14px;height:14px;stroke:#E4C688;fill:none;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}"
    +".chiov-cta{display:block;width:100%;border:0;cursor:pointer;font:inherit;font-weight:700;font-size:1.02rem;color:#0B1E38;background:linear-gradient(180deg,#E4C688,#C6A15B);border-radius:999px;padding:15px 22px;letter-spacing:.01em;box-shadow:0 12px 26px -12px rgba(214,175,106,.7);transition:transform .2s,box-shadow .2s}"
    +".chiov-cta:hover{transform:translateY(-2px);box-shadow:0 16px 30px -12px rgba(214,175,106,.85)}"
    +".chiov-skip{background:none;border:0;cursor:pointer;font:inherit;color:#8AA0BE;font-size:.85rem;margin-top:14px;text-decoration:underline;text-underline-offset:3px}"
    +".chiov-skip:hover{color:#C9D6E8}"
    +"@media (max-width:460px){.chiov-p{padding:28px 20px 24px}}";
    var st=document.createElement('style'); st.id='chiov-css'; st.textContent=css; (document.head||document.documentElement).appendChild(st);

    var html=""
    +"<div class='chiov-p' role='dialog' aria-label='So arbeitet ChatHelp'>"
    +"<svg class='chiov-seal' viewBox='0 0 100 100' fill='none' aria-hidden='true'><circle cx='50' cy='50' r='44' stroke='#D6AF6A' stroke-width='1.5' opacity='.55'/><circle cx='50' cy='50' r='35' stroke='#D6AF6A' stroke-width='1' stroke-dasharray='2 4' opacity='.5'/><path d='M35 51l10 10 20-22' stroke='#E4C688' stroke-width='3.6' stroke-linecap='round' stroke-linejoin='round'/></svg>"
    +"<span class='chiov-eb'>So entsteht Ihr Schreiben</span>"
    +"<h2 class='chiov-h'>Recherchiert. Gepr&uuml;ft. <em>Zugestellt.</em></h2>"
    +"<p class='chiov-sub'>Kein Textbaustein. Kein Zufall. ChatHelp recherchiert, pr&uuml;ft, formuliert und versendet &mdash; vollautomatisch und rechtssicher.</p>"
    +"<div class='chiov-steps'>"
    +"<div class='chiov-step'><div class='chiov-nd'><svg viewBox='0 0 24 24'><ellipse cx='12' cy='5' rx='8' ry='3'/><path d='M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5'/><path d='M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6'/></svg></div><div><span class='chiov-num'>01 &middot; Recherche</span><p class='chiov-st'>Geht in die Datenbank</p><p class='chiov-sd'>Durchsucht gepr&uuml;fte Rechtsquellen &amp; Beh&ouml;rdendaten und holt die korrekten, aktuellen Informationen.</p></div></div>"
    +"<div class='chiov-step'><div class='chiov-nd'><svg viewBox='0 0 24 24'><path d='M12 2l7 3v6c0 4.5-3 8-7 10-4-2-7-5.5-7-10V5z'/><path d='M9 12l2 2 4-4.5'/></svg></div><div><span class='chiov-num'>02 &middot; Verifizierung</span><p class='chiov-st'>Und pr&uuml;ft jede Angabe</p><p class='chiov-sd'>Fristen, Paragraphen und Zust&auml;ndigkeiten werden gegengepr&uuml;ft &mdash; erst wenn alles stimmt, geht es weiter.</p></div></div>"
    +"<div class='chiov-step'><div class='chiov-nd'><svg viewBox='0 0 24 24'><path d='M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z'/><path d='M14 3v5h5'/><path d='M9 13h6M9 16.5h4'/></svg></div><div><span class='chiov-num'>03 &middot; Dokument</span><p class='chiov-st'>Ein makelloses Schreiben</p><p class='chiov-sd'>Rechtssicher nach DIN&nbsp;5008 &mdash; pr&auml;zise formuliert, premium gesetzt, unterschriftsreif.</p></div></div>"
    +"<div class='chiov-step'><div class='chiov-nd'><svg viewBox='0 0 24 24'><path d='M22 3L11 14'/><path d='M22 3l-7 19-4-8-8-4z'/></svg></div><div><span class='chiov-num'>04 &middot; Versand</span><p class='chiov-st'>Direkt aus dem System</p><p class='chiov-sd'>Per Fax, Brief oder Einschreiben &mdash; ohne Drucker, ohne Warteschlange, ohne Weg zur Post.</p></div></div>"
    +"</div>"
    +"<div class='chiov-chips'>"
    +"<span class='chiov-chip'><svg viewBox='0 0 24 24'><path d='M6 9V3h12v6'/><rect x='3' y='9' width='18' height='8' rx='2'/><path d='M6 17h12v4H6z'/></svg>Fax</span>"
    +"<span class='chiov-chip'><svg viewBox='0 0 24 24'><rect x='3' y='5' width='18' height='14' rx='2'/><path d='M3 7l9 6 9-6'/></svg>Brief</span>"
    +"<span class='chiov-chip'><svg viewBox='0 0 24 24'><rect x='3' y='5' width='18' height='14' rx='2'/><path d='M3 7l9 6 9-6'/><path d='M8.5 16.5l1.6 1.6 3.4-3.4'/></svg>Einschreiben</span>"
    +"</div>"
    +"<button class='chiov-cta' data-close type='button'>Los geht&rsquo;s &rarr;</button>"
    +"<button class='chiov-skip' data-close type='button'>&Uuml;berspringen</button>"
    +"</div>";
    var ov=document.createElement('div'); ov.id='chiov'; ov.innerHTML=html;
    document.body.appendChild(ov);
    ov.addEventListener('click',function(e){
      if(e.target===ov || (e.target.closest && e.target.closest('[data-close]'))) done();
    });
    document.addEventListener('keydown',function ek(e){ if(e.key==='Escape'){ done(); document.removeEventListener('keydown',ek); } });
    var steps=ov.querySelectorAll('.chiov-step');
    for(var i=0;i<steps.length;i++){
      if(reduce){ steps[i].classList.add('in'); }
      else { (function(el,d){ setTimeout(function(){ el.classList.add('in'); },160+d*150); })(steps[i],i); }
    }
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',build);
  else build();
}catch(e){ try{ var o=document.getElementById('chiov'); if(o)o.remove(); }catch(_){} }})();</script>
BLOCK;

$anchor='</body>';
$pos=strrpos($src,$anchor);
if($pos===false){ echo "  ✗ </body> capasi bulunamadi — DEGISTIRILMEDI.\n"; exit; }
$new=substr($src,0,$pos).$block."\n".substr($src,$pos);

$tmp=tempnam(sys_get_temp_dir(),'in').'.php'; file_put_contents($tmp,$new);
$lo=[];$rc=0; exec('php -l '.escapeshellarg($tmp).' 2>&1',$lo,$rc); @unlink($tmp);
if($rc!==0){ echo "  ✗ LINT HATASI — DEGISTIRILMEDI:\n  ".implode("\n  ",$lo)."\n"; exit; }

@file_put_contents($file.'.bak-intro-'.date('Ymd-His'),$src);
$w=@file_put_contents($file,$new);
if($w===false||$w<strlen($new)) exit("  ✗ YAZMA HATASI.\n");
if(function_exists('opcache_reset')) @opcache_reset();
$chk=(string)@file_get_contents($file);
if(strpos($chk,'CH_INTRO')===false) exit("  ✗ DOGRULAMA BASARISIZ.\n");
echo "  ✓ CH_INTRO enjekte edildi (".strlen($chk)." B)\n";
echo "  · Ilk girişte bir kez oynar, 'Los geht's' / Uberspringen / Esc ile kapanir.\n";
echo "  · localStorage anahtari: ch_intro_story_v1 (temizlersen tekrar gorunur).\n";
echo "  · Kapatmak icin: apply-introoff.php (istersen hazirlarim).\n";
