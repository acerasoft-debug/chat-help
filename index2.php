<?php header('Content-Type: text/html; charset=UTF-8'); ?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Chat-Help — Legal Documents Europe</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js">
// ── LEGAL MODALS ─────────────────────────────────
function oLM(id){document.getElementById(id).classList.add('on');document.body.style.overflow='hidden';}
function cLM(id){document.getElementById(id).classList.remove('on');document.body.style.overflow='';}
document.addEventListener('keydown',e=>{if(e.key==='Escape'){cLM('lm-imp');cLM('lm-dsc');}});

</script>
<style>
:root{
  --bg:#07070e;--s1:#0d0d1c;--s2:#131325;--s3:#191930;--s4:#21213a;
  --gold:#d4a84a;--gold2:#ecc060;--ga:rgba(212,168,74,.1);--gb:rgba(212,168,74,.28);
  --t1:#f8f8ff;--t2:#e0e0ff;--t3:#a8a8d0;--t4:#606095;
  --gr:#42df94;--rd:#e05050;
  --bdr:rgba(255,255,255,.09);
  --r:10px;--r2:14px;--r3:18px;
}
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html,body{height:100%;overflow:hidden;font-family:'Inter',system-ui,sans-serif;background:#07070e!important;color:var(--t1);-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility}
#app{display:flex;height:100dvh;width:100vw}
#sb{width:272px;flex-shrink:0;background:var(--s1);border-right:1px solid var(--bdr);display:flex;flex-direction:column;transition:transform .27s cubic-bezier(.4,0,.2,1);z-index:300}
.sb-brand{padding:20px 16px 0;display:flex;align-items:center;gap:11px;margin-bottom:14px}
.sb-mark{width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,var(--s3),var(--s4));border:1.5px solid var(--gb);display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 2px 8px rgba(212,168,74,.15)}
.sb-mark svg{width:20px;height:20px}
.sb-logo-name{font-size:16px;font-weight:700;color:#fff;letter-spacing:-.4px}
.sb-logo-name em{font-style:normal;color:var(--gold)}
.sb-logo-sub{font-size:9px;color:var(--t4);letter-spacing:.8px;text-transform:uppercase;margin-top:2px}
.sb-country{margin:0 14px 12px;background:linear-gradient(135deg,var(--s3),var(--s4));border:1px solid var(--gb);border-radius:var(--r);padding:10px 12px;display:flex;align-items:center;gap:9px;cursor:pointer;transition:all .18s;box-shadow:0 2px 8px rgba(212,168,74,.08)}
.sb-country:hover{background:var(--s4)}
.sb-cflag{font-size:18px;flex-shrink:0}
.sb-cname{font-size:12px;font-weight:600;color:var(--gold)}
.sb-clang{font-size:10px;color:var(--t4);margin-top:1px}
.sb-country-arrow{margin-left:auto;color:var(--t4);font-size:12px}
.sb-new{margin:0 14px 10px;background:var(--gold);color:#07070e;border:none;border-radius:var(--r);padding:10px 16px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;font-family:inherit;transition:background .15s;width:calc(100% - 28px)}
.sb-new:hover{background:var(--gold2)}
.sb-new svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2.5}
.sb-plan{margin:0 14px 10px;background:var(--s3);border:1px solid var(--gb);border-radius:var(--r);padding:9px 11px;display:flex;align-items:center;gap:9px}
.sb-plan-ic{width:26px;height:26px;border-radius:6px;background:var(--ga);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.sb-plan-ic svg{width:12px;height:12px;stroke:var(--gold);fill:none;stroke-width:2}
.sb-plan-tier{font-size:11.5px;font-weight:600;color:var(--gold)}
.sb-plan-hint{font-size:9px;color:var(--t4);margin-top:1px}
.sb-plan-btn{margin-left:auto;font-size:10px;font-weight:600;background:var(--gold);color:#07070e;border:none;border-radius:5px;padding:4px 9px;cursor:pointer;font-family:inherit}
.sb-nav{padding:0 10px 10px;border-bottom:1px solid var(--bdr)}
.sn{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:var(--r);cursor:pointer;transition:all .13s;border:1px solid transparent;margin-bottom:2px}
.sn:hover{background:var(--s2)}
.sn.on{background:linear-gradient(135deg,var(--ga),rgba(212,168,74,.05));border-color:var(--gb);box-shadow:0 2px 8px rgba(212,168,74,.1)}
.sn svg{width:15px;height:15px;stroke:var(--t3);fill:none;stroke-width:1.8;flex-shrink:0;transition:stroke .13s}
.sn:hover svg,.sn.on svg{stroke:var(--gold)}
.sn-lbl{font-size:13.5px;color:#eaeaff;font-weight:400;transition:color .13s}
.sn:hover .sn-lbl,.sn.on .sn-lbl{color:#fff;font-weight:500}
.sb-scroll{flex:1;overflow-y:auto;padding:6px 10px 80px;min-height:0}
.sb-scroll::-webkit-scrollbar{width:3px}
.sb-scroll::-webkit-scrollbar-thumb{background:linear-gradient(var(--gold),var(--gb));border-radius:2px}
.sb-sec{font-size:10.5px;font-weight:700;color:#a8a8e0;padding:14px 8px 5px;display:flex;align-items:center;gap:6px;text-transform:uppercase;letter-spacing:.6px}
.sb-sec svg{width:11px;height:11px;stroke:var(--t3);fill:none;stroke-width:2;flex-shrink:0}
.si{display:flex;align-items:center;gap:9px;padding:7px 9px;border-radius:var(--r);cursor:pointer;transition:background .12s;margin-bottom:1px}
.si:hover{background:rgba(255,255,255,.055)}.si:hover .si-ic{background:var(--s4)}.si:hover .si-ic svg{stroke:var(--gold)!important}
.si-ic{width:28px;height:28px;border-radius:7px;background:var(--s3);display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:background .12s}
.si-tx{font-size:13px;color:#e0e0ff;flex:1;line-height:1.25}.si-ic svg{display:block;width:14px;height:14px;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;fill:none}
.si:hover .si-tx{color:#fff}
.si-tag{font-size:8px;font-weight:700;padding:1px 5px;border-radius:4px;flex-shrink:0}
.tg-n{background:rgba(66,223,148,.1);color:rgba(66,223,148,.9);border:1px solid rgba(66,223,148,.2)}
.tg-p{background:var(--ga);color:var(--gold);border:1px solid var(--gb)}
.sb-foot{padding:11px 14px;border-top:1px solid var(--bdr);flex-shrink:0}
.sb-foot-txt{font-size:10px;color:var(--t4)}
.sb-badges{display:flex;gap:4px;margin-top:5px;flex-wrap:wrap}
.bdg{font-size:8.5px;font-weight:600;padding:2px 7px;border-radius:4px}
.bdg-g{background:rgba(66,223,148,.1);color:rgba(66,223,148,.95);border:1px solid rgba(66,223,148,.2)}
.bdg-o{background:rgba(212,168,74,.12);color:var(--gold2);border:1px solid var(--gb)}
#mn{flex:1;display:flex;flex-direction:column;overflow:hidden;min-width:0}
#tb{height:54px;padding:0 18px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--bdr);background:var(--s1);flex-shrink:0}
.tb-l{display:flex;align-items:center;gap:11px}
.tb-ham{width:34px;height:34px;background:var(--s2);border:1px solid var(--bdr);border-radius:var(--r);display:none;align-items:center;justify-content:center;cursor:pointer;flex-direction:column;gap:4px;padding:9px}
.tb-ham span{display:block;width:14px;height:1.5px;background:var(--t2);border-radius:1px}
.tb-name{font-size:15px;font-weight:700;color:#fff;letter-spacing:-.3px}
.tb-name em{font-style:normal;color:var(--gold)}
.tb-r{display:flex;align-items:center;gap:7px}
.tb-btn{display:flex;align-items:center;gap:6px;padding:5px 11px;border-radius:var(--r);font-size:11.5px;font-weight:500;cursor:pointer;transition:all .14s;white-space:nowrap;border:1px solid var(--bdr);background:var(--s2);color:#d0d0f8;font-family:inherit}
.tb-btn:hover{border-color:var(--gb);color:var(--gold)}
.tb-btn svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:1.8;flex-shrink:0}
.tb-btn.hi{border-color:var(--gb);color:var(--gold);background:var(--ga)}
.tb-btn span{display:inline}
.tb-flag{font-size:16px;cursor:pointer;padding:4px 8px;border-radius:var(--r);background:var(--s2);border:1px solid var(--bdr);transition:all .14s}
.tb-flag:hover{border-color:var(--gb)}
.tb-online{display:flex;align-items:center;gap:5px;font-size:11px;color:var(--t3);padding:4px 9px;background:var(--s2);border:1px solid var(--bdr);border-radius:6px}
.tb-dot{width:6px;height:6px;border-radius:50%;background:var(--gr);box-shadow:0 0 6px rgba(66,223,148,.5);animation:pulse 2s infinite}@keyframes pulse{0%,100%{box-shadow:0 0 4px rgba(66,223,148,.4)}50%{box-shadow:0 0 10px rgba(66,223,148,.8)}}
.pnl{display:none;flex:1;flex-direction:column;overflow:hidden;min-height:0;background:#07070e}
.pnl.on{display:flex}
#msgs{flex:1;overflow-y:auto;min-height:0;overscroll-behavior:contain;width:100%;overflow-x:hidden;background:#07070e}
#msgs::-webkit-scrollbar{width:4px}
#msgs::-webkit-scrollbar-thumb{background:var(--s4);border-radius:2px}
#mi{max-width:740px;margin:0 auto;padding:28px 20px 16px;width:100%;box-sizing:border-box;background:#07070e}
.wlc{text-align:center;padding:36px 0 22px;max-width:520px;margin:0 auto;width:100%}
.wlc-logo{width:60px;height:60px;background:linear-gradient(135deg,var(--s2),var(--s3));border:1.5px solid var(--gb);border-radius:16px;margin:0 auto 18px;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 20px rgba(212,168,74,.2)}
.wlc-logo svg{width:26px;height:26px}
.wlc h2{font-size:27px;font-weight:800;color:#fff;margin-bottom:10px;letter-spacing:-.6px}
.wlc p{font-size:13.5px;color:#9898c8;line-height:1.7;margin-bottom:20px}
.wgrid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px;text-align:left}
.wc{background:var(--s2);border:1px solid var(--bdr);border-radius:var(--r2);padding:13px;cursor:pointer;transition:all .2s}
.wc:hover{border-color:var(--gb);background:var(--s3);transform:translateY(-3px);box-shadow:0 8px 24px rgba(212,168,74,.12)}
.wc-ic{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,var(--s3),var(--s4));border:1px solid var(--gb);display:flex;align-items:center;justify-content:center;font-size:17px;margin-bottom:10px}
.wc:hover .wc-ic{background:var(--s4)}
.wc-t{font-size:13px;font-weight:600;color:#fff;margin-bottom:2px}
.wc-s{font-size:11px;color:#9898c8}
.wphoto{display:flex;align-items:center;gap:12px;background:linear-gradient(135deg,rgba(212,168,74,.1),rgba(212,168,74,.04));border:1.5px dashed var(--gb);border-radius:var(--r2);padding:14px 18px;cursor:pointer;transition:all .22s;margin-bottom:14px}
.wphoto:hover{background:rgba(212,168,74,.14);transform:translateY(-1px)}
.wphoto-ic{width:40px;height:40px;border-radius:10px;background:rgba(212,168,74,.12);border:1px solid var(--gb);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.wphoto-ic svg{width:19px;height:19px;stroke:var(--gold);fill:none;stroke-width:1.8}
.wphoto-t{font-size:13px;font-weight:600;color:var(--gold);margin-bottom:2px}
.wphoto-s{font-size:11px;color:#9898c8}
.recent-bar{background:var(--s2);border:1px solid var(--bdr);border-radius:var(--r2);padding:12px 14px;margin-bottom:14px;text-align:left}
.recent-bar-t{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--t4);margin-bottom:8px}
.recent-item{display:flex;align-items:center;gap:9px;padding:6px 9px;border-radius:var(--r);cursor:pointer;transition:background .13s}
.recent-item:hover{background:var(--s3)}
.recent-item-ic{font-size:13px;flex-shrink:0}
.recent-item-n{font-size:12px;color:#d0d0f8;flex:1}
.recent-item-d{font-size:10px;color:var(--t4)}
.lang-bar{display:flex;align-items:center;gap:8px;justify-content:center}
.lang-txt{font-size:10px;color:var(--t4)}
.lflags{display:flex;gap:4px}
.lf{font-size:14px;opacity:.2;transition:opacity .2s}
.lf.on{opacity:1}
.msg{display:flex;gap:12px;margin-bottom:22px;animation:fadeUp .22s ease;width:100%}
@keyframes fadeUp{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
.msg.user{flex-direction:row-reverse}
.av{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0}
.msg.ai .av{background:linear-gradient(135deg,var(--s3),var(--s4));border:1.5px solid var(--gb);color:var(--gold);font-weight:800;font-size:10px;letter-spacing:.5px}
.msg.user .av{background:linear-gradient(135deg,var(--gold),var(--gold2));color:#07070e;font-size:12px;font-weight:800}
.mbody{flex:1;min-width:0}
.mname{font-size:9px;font-weight:600;color:var(--t4);margin-bottom:5px;letter-spacing:.5px;text-transform:uppercase}
.msg.user .mname{text-align:right}
.bbl{font-size:14.5px;line-height:1.75;color:#f5f5ff}
.msg.user .bbl{background:var(--s2);border:1px solid var(--bdr);border-radius:var(--r2);padding:10px 14px;display:inline-block;max-width:100%}
.photo-prev{background:var(--s2);border:1px solid var(--bdr);border-radius:var(--r2);overflow:hidden;max-width:240px;margin-top:6px}
.photo-prev img{width:100%;display:block;max-height:160px;object-fit:cover}
.photo-prev-lbl{padding:6px 10px;font-size:10px;color:var(--t3)}
.typing{display:flex;gap:5px;padding:8px 0;align-items:center}
.typing span{width:6px;height:6px;border-radius:50%;background:var(--t4);animation:tDot 1s infinite}
.typing span:nth-child(2){animation-delay:.2s}
.typing span:nth-child(3){animation-delay:.4s}
@keyframes tDot{0%,70%,100%{transform:scale(.5);opacity:.3}35%{transform:scale(1);opacity:1}}
.pbox{background:linear-gradient(135deg,var(--s2),var(--s3));border:1px solid var(--bdr);border-radius:var(--r2);padding:16px;margin-top:8px;box-shadow:0 4px 16px rgba(0,0,0,.3)}
.pbox-hdr{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:var(--gold);opacity:.7;margin-bottom:10px}
.ps{display:flex;align-items:center;gap:10px;padding:7px 9px;border-radius:var(--r);font-size:13px;color:#a0a0d0;transition:all .26s;margin-bottom:3px}
.ps.on{background:var(--ga);border:1px solid var(--gb);color:#fff;font-weight:500}
.ps.done{color:var(--gr)}
.ps-ic{width:22px;height:22px;border-radius:6px;background:var(--s3);display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0}
.ps.on .ps-ic{background:var(--ga)}
.ps.done .ps-ic{background:rgba(66,223,148,.1)}
.cgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:7px;margin-top:10px}
.ccard{background:var(--s2);border:1px solid var(--bdr);border-radius:var(--r2);padding:13px 9px;cursor:pointer;transition:all .18s;text-align:center}
.ccard:hover{border-color:var(--gb);background:var(--s3);transform:translateY(-1px)}
.ccard-ic{font-size:19px;margin-bottom:6px;filter:grayscale(0)}
.ccard-n{font-size:11.5px;font-weight:500;color:#d8d8ff}
.ccard:hover .ccard-n{color:#fff}
.dlist{display:flex;flex-direction:column;gap:3px;margin-top:8px}
.drow{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:var(--r);cursor:pointer;transition:all .14s;border:1px solid transparent;background:var(--s2)}
.drow:hover{border-color:var(--gb);background:var(--s3)}
.drow-ic{width:30px;height:30px;border-radius:8px;background:var(--s3);display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}
.drow-n{font-size:13px;font-weight:600;color:#fff;flex:1}
.drow-s{font-size:10.5px;color:#9898c8;margin-top:1px}
.dprice{font-size:11px;font-weight:600;padding:2px 7px;border-radius:5px;flex-shrink:0}
.dp-free{background:rgba(66,223,148,.08);color:rgba(66,223,148,.9);border:1px solid rgba(66,223,148,.15)}
.dp-pay{background:var(--ga);color:var(--gold);border:1px solid var(--gb)}
.dp-abo{background:rgba(100,136,255,.08);color:rgba(140,160,255,.9);border:1px solid rgba(100,136,255,.15)}
.fbox{background:var(--s2);border:1px solid var(--bdr);border-radius:var(--r3);overflow:hidden;margin-top:10px;width:100%}
.fhdr{padding:14px 16px;border-bottom:1px solid var(--bdr);display:flex;align-items:center;gap:9px;background:rgba(255,255,255,.02)}
.fhdr-ic{width:30px;height:30px;border-radius:8px;background:var(--ga);border:1px solid var(--gb);display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}
.fhdr-t{font-size:13px;font-weight:600;color:#fff}
.fhdr-cc{margin-left:auto;font-size:11px;color:var(--gold);background:var(--ga);border:1px solid var(--gb);padding:2px 8px;border-radius:5px}
.fbody{padding:14px 16px}
.fgrid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px}
.fful{grid-column:1/-1}
.fi{display:flex;flex-direction:column;gap:4px}
.fi label{font-size:10px;font-weight:700;color:#b0b0e0;text-transform:uppercase;letter-spacing:.6px}
.fi input,.fi textarea{background:var(--bg);border:1px solid rgba(255,255,255,.09);border-radius:var(--r);padding:10px 12px;font-size:14px;color:#f8f8ff;outline:none;font-family:inherit;transition:all .18s;width:100%;-webkit-appearance:none}
.fi input:focus,.fi textarea:focus{border-color:rgba(212,168,74,.4);box-shadow:0 0 0 3px rgba(212,168,74,.06)}
.fi input::placeholder,.fi textarea::placeholder{color:#505090}
.fi input[type=date]{color-scheme:dark;cursor:pointer;min-height:44px;font-size:15px;padding:11px 12px}
.fi input[type=date]::-webkit-calendar-picker-indicator{filter:invert(.3) sepia(1) saturate(2) hue-rotate(8deg);cursor:pointer}
.prog-bar{height:3px;background:rgba(255,255,255,.06);border-radius:2px;margin-bottom:14px;overflow:hidden}
.prog-fill{height:100%;background:linear-gradient(90deg,var(--gold),var(--gold2),#f0e080);border-radius:2px;transition:width .4s cubic-bezier(.4,0,.2,1);box-shadow:0 0 8px rgba(212,168,74,.4)}
.dsgvo-n{display:flex;gap:7px;background:rgba(66,223,148,.04);border:1px solid rgba(66,223,148,.1);border-radius:var(--r);padding:9px 11px;font-size:11.5px;color:rgba(66,223,148,.8);margin-bottom:11px;line-height:1.6}
.btn-g{width:100%;background:linear-gradient(135deg,var(--gold),var(--gold2));color:#07070e;border:none;border-radius:var(--r);padding:13px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:all .18s;display:flex;align-items:center;justify-content:center;gap:7px;letter-spacing:.2px;box-shadow:0 4px 14px rgba(212,168,74,.25)}
.btn-g:hover{background:linear-gradient(135deg,var(--gold2),#f0d070);transform:translateY(-1px);box-shadow:0 6px 20px rgba(212,168,74,.35)}
.btn-g:disabled{opacity:.5;cursor:default}
.btn-s{background:var(--s3);color:#d0d0f8;border:1px solid var(--bdr);border-radius:var(--r);padding:9px 14px;font-size:12.5px;font-weight:500;cursor:pointer;font-family:inherit;transition:all .14s}
.btn-s:hover{border-color:var(--gb);color:var(--gold)}
.fubox{background:rgba(100,136,255,.05);border:1px solid rgba(100,136,255,.15);border-radius:var(--r2);padding:14px;margin-top:10px}
.fubox-t{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:rgba(140,170,255,.85);margin-bottom:10px}
.fubox-msg{font-size:12.5px;color:#d0d0ff;margin-bottom:12px;line-height:1.6}
.rbox{background:var(--s2);border:1px solid var(--bdr);border-radius:var(--r3);overflow:hidden;margin-top:10px;box-shadow:0 4px 24px rgba(0,0,0,.3);width:100%}
.rhdr{padding:11px 16px;border-bottom:1px solid var(--bdr);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}
.rtitle{font-size:13px;font-weight:600;color:#fff}
.rcc{font-size:10px;color:var(--gold);background:var(--ga);border:1px solid var(--gb);padding:2px 8px;border-radius:5px}
.racts{display:flex;gap:5px;flex-wrap:wrap}
.act{display:flex;align-items:center;gap:4px;background:var(--s3);border:1px solid var(--bdr);color:#d0d0f8;font-size:11px;font-weight:500;padding:5px 11px;border-radius:var(--r);cursor:pointer;transition:all .14s;white-space:nowrap;font-family:inherit}
.act:hover{border-color:var(--gb);color:var(--gold)}
.act.gold{background:var(--ga);border-color:var(--gb);color:var(--gold)}
.act.kept{border-color:rgba(66,223,148,.2);color:rgba(66,223,148,.85);background:rgba(66,223,148,.05)}
.act svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:1.8;flex-shrink:0}
.rcnt{padding:14px 16px;max-height:280px;overflow-y:auto}
.rcnt::-webkit-scrollbar{width:3px}
.rcnt::-webkit-scrollbar-thumb{background:var(--s4)}
.rpre{font-family:'Courier New',monospace;font-size:11px;line-height:1.9;color:#d8d8ff;white-space:pre-wrap}
.blink{display:inline-block;width:2px;height:1.1em;background:var(--gold);animation:bl .9s step-end infinite;vertical-align:text-bottom}
@keyframes bl{50%{opacity:0}}
.rqual{display:flex;align-items:center;gap:7px;padding:10px 16px;border-top:1px solid var(--bdr);font-size:11px;color:rgba(66,223,148,.85);background:linear-gradient(135deg,rgba(66,223,148,.04),rgba(66,223,148,.01));font-weight:500}
.rns{padding:11px 16px;border-top:1px solid var(--bdr)}
.rns-t{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--gold);margin-bottom:7px}
.rns-item{font-size:12.5px;color:#d0d0f8;padding:4px 0;border-bottom:1px solid var(--bdr)}
.rns-item:last-child{border-bottom:none}
.offbox{background:var(--ga);border:1px solid var(--gb);border-radius:var(--r);padding:11px 13px;margin:0 16px 12px}
.offbox-t{font-size:11.5px;font-weight:600;color:var(--gold);margin-bottom:3px}
.offbox-d{font-size:11px;color:#d0d0f8;margin-bottom:7px;line-height:1.5}
.offbox-a{display:inline-flex;align-items:center;gap:5px;background:var(--gold);color:#07070e;border:none;padding:5px 11px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;text-decoration:none;font-family:inherit}
.keep-row{padding:9px 16px;border-top:1px solid var(--bdr);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}
.keep-row span{font-size:11.5px;color:var(--t3)}
.chips{display:flex;gap:5px;flex-wrap:wrap;padding:0 16px 12px}
.chip{background:var(--s3);border:1px solid var(--bdr);color:#c0c0e8;font-size:12px;padding:7px 14px;border-radius:100px;cursor:pointer;transition:all .18s;font-family:inherit;font-weight:500}
.chip:hover{border-color:var(--gb);color:var(--gold)}
.research-box{background:rgba(100,130,255,.04);border:1px solid rgba(100,130,255,.1);border-radius:var(--r);padding:11px 13px;margin-top:8px}
.research-t{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:rgba(130,160,255,.8);margin-bottom:6px}
.research-item{font-size:12px;color:#d0d0f8;padding:2px 0;line-height:1.55}
.pnl-scroll{flex:1;overflow-y:auto;padding:22px}
.pnl-scroll::-webkit-scrollbar{width:4px}
.pnl-scroll::-webkit-scrollbar-thumb{background:var(--s4);border-radius:2px}
.sec-hdr{max-width:780px;margin:0 auto 18px;display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap}
.sec-title{font-size:20px;font-weight:700;color:#fff;letter-spacing:-.4px}
.sec-sub{font-size:12px;color:var(--t3);margin-top:3px}
.ant-stats{display:flex;gap:8px;max-width:780px;margin:0 auto 16px;flex-wrap:wrap}
.astat{background:var(--s2);border:1px solid var(--bdr);border-radius:var(--r2);padding:13px 15px;flex:1;min-width:90px}
.astat-n{font-size:22px;font-weight:700;color:#fff;line-height:1}
.astat-n.gold{color:var(--gold)}
.astat-n.gr{color:var(--gr)}
.astat-l{font-size:10.5px;color:var(--t3);margin-top:3px}
.ant-list{max-width:780px;margin:0 auto;display:flex;flex-direction:column;gap:8px}
.acard{background:var(--s2);border:1px solid var(--bdr);border-radius:var(--r2);overflow:hidden;transition:border .2s}
.acard:hover{border-color:rgba(255,255,255,.12)}
.acard-hdr{padding:13px 15px;display:flex;align-items:center;gap:11px;border-bottom:1px solid var(--bdr);flex-wrap:wrap}
.acard-ic{width:36px;height:36px;border-radius:9px;background:var(--s3);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.acard-info{flex:1;min-width:100px}
.acard-name{font-size:14px;font-weight:700;color:#fff;letter-spacing:-.2px}
.acard-meta{font-size:10.5px;color:var(--t3);margin-top:2px}
.acard-acts{display:flex;gap:5px;flex-wrap:wrap}
.ab{display:flex;align-items:center;gap:4px;font-size:11px;font-weight:500;padding:5px 10px;border-radius:var(--r);cursor:pointer;transition:all .14s;border:1px solid var(--bdr);background:var(--s3);color:#d0d0f8;font-family:inherit}
.ab svg{width:11px;height:11px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0}
.ab:hover{border-color:var(--gb);color:var(--gold)}
.ab.del:hover{border-color:rgba(224,80,80,.3);color:var(--rd)}
.ab.kept{border-color:rgba(66,223,148,.2);color:rgba(66,223,148,.85);background:rgba(66,223,148,.05)}
.acard-body{padding:11px 15px}
.aprev{font-family:'Courier New',monospace;font-size:11px;line-height:1.7;color:var(--t3);background:var(--bg);border-radius:var(--r);padding:9px 11px;max-height:60px;overflow:hidden;position:relative}
.aprev-fade{position:absolute;bottom:0;left:0;right:0;height:24px;background:linear-gradient(transparent,var(--bg))}
.aexp{display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:500;padding:2px 8px;border-radius:100px;margin-top:9px}
.ae-soon{background:rgba(224,80,80,.08);color:rgba(224,80,80,.8);border:1px solid rgba(224,80,80,.15)}
.ae-kept{background:rgba(66,223,148,.08);color:rgba(66,223,148,.8);border:1px solid rgba(66,223,148,.15)}
.ae-norm{background:var(--ga);color:var(--gold);border:1px solid var(--gb)}
.ant-empty{text-align:center;padding:50px 20px;max-width:340px;margin:0 auto}
.ant-empty-ic{width:56px;height:56px;border-radius:14px;background:var(--s2);border:1px solid var(--bdr);margin:0 auto 14px;display:flex;align-items:center;justify-content:center;opacity:.45}
.ant-empty-ic svg{width:26px;height:26px;stroke:var(--t3);fill:none;stroke-width:1.5}
.ant-empty-t{font-size:15px;font-weight:600;color:#d0d0f8;margin-bottom:5px}
.ant-empty-s{font-size:12.5px;color:var(--t3);line-height:1.6}
.konto-wrap{max-width:580px;margin:0 auto}
.konto-plan{background:linear-gradient(135deg,rgba(212,168,74,.12),rgba(212,168,74,.04));border:1px solid var(--gb);border-radius:var(--r2);padding:22px;margin-bottom:14px;position:relative;overflow:hidden}.konto-plan::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--gold),transparent)}
.kp-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.kp-name{font-size:15px;font-weight:700;color:var(--gold)}
.kp-badge{background:linear-gradient(135deg,var(--gold),var(--gold2));color:#07070e;font-size:9px;font-weight:800;padding:4px 12px;border-radius:100px;letter-spacing:.5px}
.kp-feats{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}
.kp-feat{display:flex;align-items:center;gap:7px;font-size:12.5px;color:#d0d0f8}
.kp-feat svg{width:12px;height:12px;stroke:var(--gr);fill:none;stroke-width:2.5;flex-shrink:0}
.ksec{background:var(--s2);border:1px solid var(--bdr);border-radius:var(--r2);overflow:hidden;margin-bottom:11px}
.ksec-hdr{padding:14px 16px;border-bottom:1px solid var(--bdr);display:flex;align-items:center;gap:9px;background:rgba(255,255,255,.02)}
.ksec-hdr svg{width:14px;height:14px;stroke:var(--gold);fill:none;stroke-width:1.8;flex-shrink:0}
.ksec-hdr-t{font-size:13px;font-weight:700;color:#fff;letter-spacing:-.2px}
.ksec-body{padding:13px 15px}
.krow{display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--bdr)}
.krow:last-child{border-bottom:none}
.krow-l{font-size:13px;color:#d0d0f8}
.krow-r{font-size:13px;font-weight:500;color:#fff}
.krow-r.gold{color:var(--gold)}
.lang-grid{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}
.lang-opt{display:flex;align-items:center;gap:5px;padding:6px 10px;border-radius:var(--r);cursor:pointer;transition:all .14s;border:1px solid var(--bdr);background:var(--s3);font-size:12px;color:#d0d0f8;font-family:inherit}
.lang-opt:hover{border-color:var(--gb);color:var(--gold)}
.lang-opt.on{background:var(--ga);border-color:var(--gb);color:var(--gold);font-weight:600}
.lang-flag{font-size:15px}
.country-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:8px}
.co{display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:var(--r);cursor:pointer;transition:all .14s;border:1px solid var(--bdr);background:var(--s3)}
.co:hover{border-color:var(--gb)}
.co.on{background:var(--ga);border-color:var(--gb)}
.co-flag{font-size:16px;flex-shrink:0}
.co-n{font-size:12px;color:#d0d0f8}
.co.on .co-n{color:var(--gold);font-weight:600}
.kdsgvo{background:linear-gradient(135deg,rgba(66,223,148,.06),rgba(66,223,148,.02));border:1px solid rgba(66,223,148,.18);border-radius:var(--r2);padding:18px;margin-bottom:11px;position:relative;overflow:hidden}
.kdsgvo::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,rgba(66,223,148,.5),transparent)}
.kdsgvo h4{font-size:13.5px;font-weight:700;color:rgba(66,223,148,.95);margin-bottom:12px;display:flex;align-items:center;gap:8px;letter-spacing:.2px}
.kdsgvo h4 svg{width:16px;height:16px;stroke:rgba(66,223,148,.9);fill:none;stroke-width:2;flex-shrink:0}
.kdsgvo-item{display:flex;align-items:flex-start;gap:8px;font-size:12.5px;color:#d0d0f0;padding:5px 0;border-bottom:1px solid rgba(255,255,255,.04);line-height:1.6}
.kdsgvo-item:last-child{border-bottom:none}
.kdsgvo-item svg{width:12px;height:12px;stroke:rgba(66,223,148,.8);fill:none;stroke-width:2.5;flex-shrink:0;margin-top:2px}
.pm{position:fixed;inset:0;background:rgba(0,0,0,.9);z-index:500;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(8px)}
.pm-inner{background:var(--s1);border:1px solid rgba(255,255,255,.08);border-radius:var(--r3);padding:28px;max-width:660px;width:calc(100% - 32px);max-height:92vh;overflow-y:auto;position:relative}
.pm-close{position:absolute;top:16px;right:16px;width:28px;height:28px;background:var(--s3);border:1px solid var(--bdr);border-radius:7px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--t3);font-size:14px;font-family:inherit}
.pm-title{font-size:21px;font-weight:700;color:#fff;margin-bottom:4px;letter-spacing:-.4px}
.pm-sub{font-size:13px;color:var(--t3);margin-bottom:20px}
.pm-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:11px}
.pc{background:var(--s2);border:1px solid var(--bdr);border-radius:var(--r2);padding:20px;position:relative}
.pc.feat{border-color:var(--gold)}
.pc.feat::before{content:'Beliebteste';position:absolute;top:-10px;left:50%;transform:translateX(-50%);background:var(--gold);color:#07070e;font-size:8px;font-weight:700;padding:3px 11px;border-radius:100px;white-space:nowrap;letter-spacing:.5px}
.pc-tier{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--t3);margin-bottom:9px}
.pc-amt{font-size:30px;font-weight:700;color:#fff;line-height:1;margin-bottom:3px}
.pc.feat .pc-amt{color:var(--gold)}
.pc-per{font-size:11px;color:var(--t3);margin-bottom:12px}
.pc-sep{height:1px;background:var(--bdr);margin-bottom:12px}
.pc-feats{display:flex;flex-direction:column;gap:6px;margin-bottom:15px}
.pc-feat{display:flex;align-items:flex-start;gap:6px;font-size:11.5px;color:#c8c8f0}
.pc-feat svg{width:11px;height:11px;stroke:var(--gr);fill:none;stroke-width:2.5;flex-shrink:0;margin-top:2px}
.pc-btn{width:100%;padding:9px;border-radius:var(--r);font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;transition:all .14s;border:1px solid var(--bdr);background:var(--s3);color:#fff}
.pc-btn:hover{border-color:var(--gb);color:var(--gold)}
.pc-btn.g{background:var(--gold);color:#07070e;border-color:var(--gold)}
.pc-btn.g:hover{background:var(--gold2)}
#ia{padding:11px 17px;padding-bottom:max(11px,env(safe-area-inset-bottom));border-top:1px solid var(--bdr);flex-shrink:0;background:var(--s1)}
.ia-inner{max-width:740px;margin:0 auto;position:relative}
#inp{width:100%;background:var(--s2);border:1.5px solid var(--bdr);border-radius:14px;padding:13px 106px 13px 18px;font-size:14px;color:#f8f8ff;outline:none;font-family:inherit;resize:none;max-height:120px;min-height:52px;line-height:1.55;transition:all .18s;-webkit-appearance:none;display:block}
#inp:focus{border-color:rgba(212,168,74,.5);box-shadow:0 0 0 3px rgba(212,168,74,.08)}
#inp::placeholder{color:#5050a0}
.ia-btns{position:absolute;right:8px;bottom:8px;display:flex;gap:5px;align-items:center}
#pb{width:34px;height:34px;background:var(--s3);border:1px solid var(--gb);border-radius:9px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .14s;flex-shrink:0}
#pb:hover{background:var(--ga)}
#pb svg{width:16px;height:16px;stroke:var(--gold);fill:none;stroke-width:1.8}
#sbtn{width:38px;height:38px;background:linear-gradient(135deg,var(--gold),var(--gold2));border:none;border-radius:10px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .14s;flex-shrink:0;box-shadow:0 3px 10px rgba(212,168,74,.3)}
#sbtn:hover{background:var(--gold2);transform:scale(1.06)}
#sbtn:disabled{background:var(--s3);cursor:default;transform:none}
#sbtn svg{width:14px;height:14px;fill:none;stroke:#07070e;stroke-width:2;flex-shrink:0}
#sbtn:disabled svg{stroke:var(--t4)}
.ia-foot{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:6px}
.ia-foot-txt{font-size:10px;color:var(--t4)}
.ia-lfs{display:flex;gap:3px}
.lf2{font-size:12px;opacity:.2;transition:opacity .2s}
.lf2.on{opacity:1}
input[type=file]{display:none}
#ov{display:none;position:fixed;inset:0;background:rgba(0,0,0,.78);z-index:200;backdrop-filter:blur(4px)}
@media(max-width:700px){
  /* Sidebar */
  #sb{position:fixed;left:0;top:0;bottom:0;transform:translateX(-100%);box-shadow:8px 0 32px rgba(0,0,0,.9);width:290px}
  #sb.open{transform:translateX(0)}
  #ov.show{display:block}
  .tb-ham{display:flex}
  
  /* Topbar mobile */
  #tb{height:58px;padding:0 14px}
  .tb-btn span{display:none}
  .tb-btn{padding:8px 10px;min-width:38px;justify-content:center}
  .tb-online{display:none}
  .tb-name{font-size:17px}
  .tb-flag{font-size:18px;padding:5px 9px}
  
  /* Chat area */
  #mi{padding:16px 14px 10px;max-width:100%}
  
  /* Welcome */
  .wlc{padding:24px 0 16px}
  .wlc h2{font-size:22px}
  .wlc p{font-size:13px}
  .wgrid{grid-template-columns:1fr 1fr;gap:7px}
  .wc{padding:12px}
  .wc-ic{width:30px;height:30px}
  .wphoto{padding:12px 14px}
  
  /* Category grid */
  .cgrid{grid-template-columns:1fr 1fr}
  
  /* Forms */
  .fgrid{grid-template-columns:1fr}
  .fful{grid-column:1}
  .fbody{padding:12px 14px}
  .fhdr{padding:12px 14px}
  .fi input,.fi textarea{font-size:16px;padding:12px 14px}
  .fi label{font-size:11px}
  .btn-g{padding:14px;font-size:15px;border-radius:12px}
  
  /* Result */
  .racts,.acard-acts{flex-wrap:wrap}
  .act{flex:1;justify-content:center;padding:8px 10px}
  .rhdr{padding:12px 14px}
  .rcnt{padding:12px 14px;max-height:220px}
  .rns{padding:10px 14px}
  .chips{padding:0 14px 12px;gap:6px}
  .chip{font-size:12.5px;padding:8px 14px}
  .keep-row{padding:10px 14px}
  
  /* Pricing */
  .pm-grid{grid-template-columns:1fr}
  .pm-inner{padding:22px 18px}
  
  /* Input area */
  #ia{padding:10px 12px;padding-bottom:max(10px,env(safe-area-inset-bottom))}
  #inp{font-size:16px;min-height:48px;padding:13px 108px 13px 16px;border-radius:16px}
  .ia-btns{right:9px;bottom:8px}
  #sbtn{width:38px;height:38px;border-radius:10px}
  #pb{width:36px;height:36px;border-radius:9px}
  .ia-foot-txt{display:none}
  .ia-lfs{gap:4px}
  .lf2{font-size:13px}
  
  /* Konto/Settings */
  .country-grid{grid-template-columns:1fr 1fr}
  .lang-grid{gap:7px}
  .lang-opt{padding:8px 12px;font-size:12.5px}
  .konto-wrap{padding:0 4px}
  .pnl-scroll{padding:16px 14px}
  .sec-title{font-size:18px}
  .kdsgvo{padding:14px}
  .kdsgvo-item{font-size:13px}
  
  /* Anträge */
  .acard-hdr{padding:12px 13px;gap:9px}
  .acard-body{padding:10px 13px}
  .acard-name{font-size:13.5px}
  .ant-stats{gap:6px}
  .astat{padding:11px 13px}
  .astat-n{font-size:20px}
  
  /* Messages */
  .msg{gap:9px;margin-bottom:18px}
  .av{width:28px;height:28px;font-size:10px}
  .bbl{font-size:14px}
  .mname{font-size:8.5px}
  
  /* Sidebar mobile premium */
  .sb-brand{padding:18px 14px 0}
  .sb-new{margin:0 12px 10px;padding:11px 14px;font-size:14px}
  .sb-plan{margin:0 12px 10px;padding:10px 11px}
  .sb-nav{padding:0 9px 9px}
  .sn{padding:10px 11px}
  .sn-lbl{font-size:14px}
  .sb-scroll{padding:5px 9px 80px}
  .si{padding:8px 10px}
  .si-tx{font-size:13.5px}
  .sb-sec{font-size:11px;padding:14px 7px 5px}
  .si-ic{width:30px;height:30px}
  
  /* Process box */
  .ps{font-size:13.5px;padding:8px 10px}
  .pbox{padding:14px}
  
  /* Recent bar */
  .recent-bar{padding:11px 13px;margin-bottom:12px}
  .recent-item{padding:7px 10px}
  .recent-item-n{font-size:12.5px}
  
  /* Photo preview */
  .photo-prev{max-width:200px}
  
  /* Upgrade button mobile */
  .sb-plan-btn{font-size:11px;padding:5px 10px}
  .sb-country{margin:0 12px 11px;padding:10px 12px}
}

/* iPhone safe area */
@supports (padding-bottom: env(safe-area-inset-bottom)){
  #ia{padding-bottom:max(12px,env(safe-area-inset-bottom))}
}
@media(min-width:701px){
  #mi{padding-left:24px;padding-right:24px}
  .wlc{max-width:500px}
  .fbox,.rbox,.pbox,.fubox{max-width:740px}
  .msg.user{justify-content:flex-end}
  .msg.user .mbody{max-width:85%}
}

/* ── LEGAL MODALS ────────────────────────────────── */
.lm{position:fixed;inset:0;background:rgba(0,0,0,.92);z-index:600;display:none;align-items:flex-end;justify-content:center;backdrop-filter:blur(8px)}
.lm.on{display:flex}
.lm-box{background:var(--s1);border:1px solid var(--bdr);border-radius:var(--r3) var(--r3) 0 0;width:100%;max-width:760px;max-height:88vh;display:flex;flex-direction:column;animation:slideUp .28s cubic-bezier(.4,0,.2,1)}
@keyframes slideUp{from{transform:translateY(60px);opacity:0}to{transform:translateY(0);opacity:1}}
.lm-hdr{padding:16px 22px;border-bottom:1px solid var(--bdr);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.lm-title{font-size:16px;font-weight:700;color:#fff;letter-spacing:-.3px}
.lm-close{width:30px;height:30px;background:var(--s3);border:1px solid var(--bdr);border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--t3);font-size:14px;font-family:inherit;transition:all .15s}
.lm-close:hover{background:var(--s4);color:#fff}
.lm-body{flex:1;overflow-y:auto;padding:24px 22px;min-height:0}
.lm-body::-webkit-scrollbar{width:4px}
.lm-body::-webkit-scrollbar-thumb{background:var(--s4);border-radius:2px}
.lm-sec{margin-bottom:24px}
.lm-sec-n{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--gold);opacity:.8;margin-bottom:8px}
.lm-sec h2{font-size:16px;font-weight:700;color:#fff;margin-bottom:12px;letter-spacing:-.3px}
.lm-sec h3{font-size:13.5px;font-weight:600;color:var(--t2);margin:14px 0 6px}
.lm-sec p{font-size:13px;color:var(--t2);line-height:1.75;margin-bottom:8px}
.lm-sec ul{list-style:none;padding:0;margin:6px 0 10px}
.lm-sec li{font-size:13px;color:var(--t2);padding:4px 0 4px 18px;position:relative;line-height:1.6}
.lm-sec li::before{content:'';position:absolute;left:0;top:11px;width:5px;height:5px;border-radius:50%;background:var(--gold);opacity:.6}
.lm-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:12px 0}
.lm-card{background:var(--s2);border:1px solid var(--bdr);border-radius:var(--r2);padding:13px 15px}
.lm-card-l{font-size:10px;font-weight:700;color:var(--t4);text-transform:uppercase;letter-spacing:.7px;margin-bottom:5px}
.lm-card-v{font-size:13px;color:var(--t1);font-weight:500;line-height:1.5}
.lm-card-v a{color:var(--gold);text-decoration:none}
.lm-note{background:rgba(66,223,148,.05);border:1px solid rgba(66,223,148,.15);border-radius:var(--r2);padding:13px 15px;margin:12px 0}
.lm-note p{color:rgba(66,223,148,.9);font-size:12.5px;margin:0}
.lm-warn{background:var(--ga);border:1px solid var(--gb);border-radius:var(--r2);padding:13px 15px;margin:12px 0}
.lm-warn p{color:var(--gold2);font-size:12.5px;margin:0}
.lm-table{width:100%;border-collapse:collapse;margin:10px 0;font-size:12.5px}
.lm-table th{text-align:left;padding:8px 12px;color:var(--gold);font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;border-bottom:1px solid var(--bdr)}
.lm-table td{padding:9px 12px;color:var(--t2);border-bottom:1px solid rgba(255,255,255,.04)}
.lm-table tr:last-child td{border-bottom:none}
.lm-table tr:hover td{background:rgba(255,255,255,.02)}
.lm-yes{color:var(--gr)}
.lm-no{color:var(--t4)}
.lm-diff{color:var(--gold)}
.lm-b2b{border-color:rgba(102,136,255,.25);background:linear-gradient(135deg,rgba(102,136,255,.06),var(--s2))}
.lm-b2b-badge{position:absolute;top:-10px;right:16px;background:rgba(102,136,255,.2);color:rgba(160,190,255,.9);border:1px solid rgba(102,136,255,.3);font-size:9px;font-weight:700;padding:2px 9px;border-radius:100px}
@media(max-width:700px){.lm-grid{grid-template-columns:1fr}.lm-box{border-radius:var(--r3) var(--r3) 0 0;max-height:92vh}}

/* ── PREMIUM WELCOME ─────────────────────────────── */
.wlc{text-align:center;padding:24px 0 16px;max-width:560px;margin:0 auto;width:100%}
.wlc-hero{margin-bottom:18px}
.wlc-mark{width:52px;height:52px;background:linear-gradient(135deg,var(--s2),var(--s3));border:1.5px solid var(--gb);border-radius:15px;margin:0 auto 14px;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 20px rgba(212,168,74,.18)}
.wlc h2{font-size:24px;font-weight:800;color:#fff;margin-bottom:8px;letter-spacing:-.5px}
.wlc p{font-size:13px;color:#9090b8;line-height:1.7;margin-bottom:14px}
.wlc-stats{display:flex;align-items:center;justify-content:center;gap:0;background:var(--s2);border:1px solid var(--bdr);border-radius:100px;padding:8px 18px;margin-bottom:0;width:fit-content;margin:0 auto}
.wlc-stat{display:flex;flex-direction:column;align-items:center;padding:0 12px}
.wlc-stat-n{font-size:14px;font-weight:800;color:#fff;line-height:1}
.wlc-stat-l{font-size:9px;color:var(--t4);margin-top:2px;text-transform:uppercase;letter-spacing:.5px}
.wlc-stat-div{width:1px;height:28px;background:var(--bdr)}

/* ── CATEGORY GRID ───────────────────────────────── */
.wcat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:7px;margin:16px 0;text-align:left}
.wcat{background:var(--s2);border:1px solid var(--bdr);border-radius:var(--r2);padding:13px 11px;cursor:pointer;transition:all .18s}
.wcat:hover{border-color:rgba(255,255,255,.15);background:var(--s3);transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.3)}
.wcat-ic{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;margin-bottom:9px}
.wcat-ic svg{width:16px;height:16px}
.wcat-t{font-size:12.5px;font-weight:700;color:#fff;margin-bottom:2px;letter-spacing:-.2px}
.wcat-s{font-size:10.5px;color:var(--t3);line-height:1.4}

/* OLD wgrid — keep for fallback */
.wgrid{display:none}
.wc{display:none}

@media(max-width:700px){
  .wcat-grid{grid-template-columns:repeat(3,1fr);gap:6px}
  .wcat{padding:11px 9px}
  .wcat-ic{width:30px;height:30px;border-radius:8px;margin-bottom:7px}
  .wcat-t{font-size:11.5px}
  .wcat-s{font-size:9.5px}
  .wlc h2{font-size:20px}
  .wlc-stats{padding:7px 14px}
  .wlc-stat{padding:0 8px}
  .wlc-stat-n{font-size:13px}
}
@media(max-width:380px){
  .wcat-grid{grid-template-columns:repeat(2,1fr)}
}
</style>
</head>
<body>
<input type="file" id="pf" accept="image/*,application/pdf">
<div id="ov" onclick="cSB()"></div>

<!-- PRICING -->
<div id="pm" class="pm" style="display:none">
<div class="pm-inner">
  <button class="pm-close" onclick="cPM()">&#x2715;</button>
  <div class="pm-title">Tarife &amp; Preise</div>
  <div class="pm-sub">Professionelle Rechtsdokumente &#8212; f&#252;r ganz Europa</div>
  <div class="pm-grid">
    <div class="pc"><div class="pc-tier">Einzeln</div><div class="pc-amt">ab 2,99&#8364;</div><div class="pc-per">pro Dokument</div><div class="pc-sep"></div>
      <div class="pc-feats"><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>1 Dokument</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>PDF-Download</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Alle Sprachen</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>24h in Meine Antr&#228;ge</div></div>
      <button class="pc-btn" onclick="sPlan('single')">Jetzt erstellen</button></div>
    <div class="pc feat"><div class="pc-tier">Monatsabo</div><div class="pc-amt">9,99&#8364;</div><div class="pc-per">/ Monat &#183; k&#252;ndbar</div><div class="pc-sep"></div>
      <div class="pc-feats"><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Unbegrenzte Dokumente</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Alle EU-L&#228;nder</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Foto-Analyse KI</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Antr&#228;ge dauerhaft speichern</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Follow-up Fragen</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>DeepSeek Recherche</div></div>
      <button class="pc-btn g" onclick="sPlan('monthly')">Jetzt starten</button></div>
    <div class="pc"><div class="pc-tier">Jahresabo</div><div class="pc-amt">79&#8364;</div><div class="pc-per">/ Jahr &#8212; 40% sparen</div><div class="pc-sep"></div>
      <div class="pc-feats"><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Alles aus Monatsabo</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Anwalt-Check 2x / Jahr</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Familien-Profile (3x)</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Dokumenten-Archiv</div><div class="pc-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Priorit&#228;t-Support</div></div>
      <button class="pc-btn" onclick="sPlan('yearly')">Jetzt sparen</button></div>
  </div>
  <div style="text-align:center;font-size:11px;color:var(--t4);margin-top:14px">Alle Preise inkl. MwSt. &#183; Sichere Zahlung via Stripe &#183; Jederzeit k&#252;ndbar</div>
</div></div>

<div id="app">
<div id="sb">
  <div class="sb-brand" onclick="NC()" style="cursor:pointer" title="Ana Sayfa">
    <div class="sb-mark"><svg viewBox="0 0 24 24" fill="none"><path d="M3 19L8 8L14 15L17 11L21 19" stroke="#d4a84a" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="8" cy="8" r="1.8" fill="#d4a84a"/></svg></div>
    <div><div class="sb-logo-name" onclick="NC()" style="cursor:pointer">Chat<em>Help</em></div><div class="sb-logo-sub">Legal Documents Europe</div></div>
  </div>
  <div class="sb-country" onclick="gP('konto')">
    <div class="sb-cflag" id="sb-cflag">&#x1F1E9;&#x1F1EA;</div>
    <div><div class="sb-cname" id="sb-cname">Deutschland</div><div class="sb-clang" id="sb-clang">Deutsch &#183; Deutsches Recht</div></div>
    <div class="sb-country-arrow">&#8250;</div>
  </div>
  <button class="sb-new" onclick="NC()">
    <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Neues Gespr&#228;ch
  </button>
  <div class="sb-plan">
    <div class="sb-plan-ic"><svg viewBox="0 0 24 24"><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/></svg></div>
    <div><div class="sb-plan-tier" id="plan-tier">Free</div><div class="sb-plan-hint">Upgrade f&#252;r unbegrenzt</div></div>
    <button class="sb-plan-btn" onclick="oPM()">Upgrade &#8599;</button>
  </div>
  <div class="sb-nav">
    <div class="sn on" id="nav-chat" onclick="gP('chat')"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span class="sn-lbl">Chat</span></div>
    <div class="sn" id="nav-ant" onclick="gP('ant')"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg><span class="sn-lbl">Meine Antr&#228;ge</span></div>
    <div class="sn" id="nav-konto" onclick="gP('konto')"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span class="sn-lbl">Konto &amp; Einstellungen</span></div>
  </div>
  <div class="sb-scroll">
    <div class="sb-sec"><svg viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>Foto-Analyse</div>
    <div class="si" onclick="document.getElementById('pf').click()"><div class="si-ic">&#x1F4F7;</div><span class="si-tx">Brief / Bescheid analysieren</span><span class="si-tag tg-n">KI</span></div>
    <div class="sb-sec"><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>Beh&#246;rde &amp; &#196;mter</div>
    <div class="si" onclick="qS('widerspruch')"><div class="si-ic">&#x2696;&#xFE0F;</div><span class="si-tx">Widerspruch</span></div>
    <div class="si" onclick="qS('aufenthaltserlaubnis')"><div class="si-ic">&#x1F3DB;&#xFE0F;</div><span class="si-tx">Aufenthaltserlaubnis</span></div>
    <div class="si" onclick="qS('einbuergerung')"><div class="si-ic">&#x1F1E9;&#x1F1EA;</div><span class="si-tx">Einb&#252;rgerung</span></div>
    <div class="si" onclick="qS('akteneinsicht')"><div class="si-ic">&#x1F4C1;</div><span class="si-tx">Akteneinsicht</span></div>
    <div class="sb-sec"><svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>Steuern</div>
    <div class="si" onclick="qS('steuer_an')"><div class="si-ic">&#x1F4BC;</div><span class="si-tx">Steuererkl&#228;rung (AN)</span><span class="si-tag tg-n">NEU</span></div>
    <div class="si" onclick="qS('steuer_selbst')"><div class="si-ic">&#x1F3E2;</div><span class="si-tx">Steuererkl&#228;rung (Selbst.)</span><span class="si-tag tg-n">NEU</span></div>
    <div class="si" onclick="qS('einspruch_steuer')"><div class="si-ic">&#x1F9FE;</div><span class="si-tx">Einspruch Steuerbescheid</span></div>
    <div class="sb-sec"><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg>Miete &amp; Wohnen</div>
    <div class="si" onclick="qS('mietvertrag')"><div class="si-ic">&#x1F3E0;</div><span class="si-tx">Mietvertrag</span></div>
    <div class="si" onclick="qS('kuendigung_mieter')"><div class="si-ic">&#x1F6AA;</div><span class="si-tx">K&#252;ndigung (Mieter)</span></div>
    <div class="si" onclick="qS('kuendigung_vermieter')"><div class="si-ic">&#x1F4CB;</div><span class="si-tx">K&#252;ndigung (Vermieter)</span></div>
    <div class="si" onclick="qS('kuendigung_widerspruch')"><div class="si-ic">&#x1F6E1;&#xFE0F;</div><span class="si-tx">K&#252;ndigung Widerspruch</span></div>
    <div class="si" onclick="qS('maengelanzeige')"><div class="si-ic">&#x1F527;</div><span class="si-tx">M&#228;ngelanzeige</span></div>
    <div class="si" onclick="qS('mietminderung')"><div class="si-ic">&#x1F4C9;</div><span class="si-tx">Mietminderung</span></div>
    <div class="si" onclick="qS('kaution')"><div class="si-ic">&#x1F4B0;</div><span class="si-tx">Kaution R&#252;ckforderung</span></div>
    <div class="sb-sec"><svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>Arbeit &amp; Beruf</div>
    <div class="si" onclick="qS('arbeitsvertrag')"><div class="si-ic">&#x1F4DD;</div><span class="si-tx">Arbeitsvertrag</span></div>
    <div class="si" onclick="qS('arbeit_kuendigung')"><div class="si-ic">&#x1F4E4;</div><span class="si-tx">K&#252;ndigung (Arbeitnehmer)</span></div>
    <div class="si" onclick="qS('arbeit_kuendigung_widerspruch')"><div class="si-ic">&#x2696;&#xFE0F;</div><span class="si-tx">K&#252;ndigung Widerspruch</span></div>
    <div class="si" onclick="qS('abmahnung_widerspruch')"><div class="si-ic">&#x26A0;&#xFE0F;</div><span class="si-tx">Abmahnung Widerspruch</span></div>
    <div class="si" onclick="qS('zeugnis')"><div class="si-ic">&#x1F4DC;</div><span class="si-tx">Zeugnis Antrag</span></div>
    <div class="si" onclick="qS('aufhebungsvertrag')"><div class="si-ic">&#x1F91D;</div><span class="si-tx">Aufhebungsvertrag</span></div>
    <div class="si" onclick="qS('elternzeit')"><div class="si-ic">&#x1F476;</div><span class="si-tx">Elternzeit Antrag</span></div>
    <div class="sb-sec"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Familie &amp; Vorsorge</div>
    <div class="si" onclick="qS('testament')"><div class="si-ic">&#x1F4C3;</div><span class="si-tx">Testament</span></div>
    <div class="si" onclick="qS('vorsorgevollmacht')"><div class="si-ic">&#x1F3E5;</div><span class="si-tx">Vorsorgevollmacht</span></div>
    <div class="si" onclick="qS('vollmacht')"><div class="si-ic">&#x270D;&#xFE0F;</div><span class="si-tx">Vollmacht</span></div>
    <div class="si" onclick="qS('unterhalt')"><div class="si-ic">&#x1F46A;</div><span class="si-tx">Unterhalt</span></div>
    <div class="si" onclick="qS('kindergeld')"><div class="si-ic">&#x1F476;</div><span class="si-tx">Kindergeld KG1</span><span class="si-tag tg-p">PDF</span></div>
    <div class="si" onclick="qS('elterngeld')"><div class="si-ic">&#x1F37C;</div><span class="si-tx">Elterngeld</span><span class="si-tag tg-p">PDF</span></div>
    <div class="si" onclick="qS('pflegegrad')"><div class="si-ic">&#x1FA7A;</div><span class="si-tx">Pflegegrad Antrag</span></div>
    <div class="sb-sec"><svg viewBox="0 0 24 24"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3z"/></svg>Jobcenter &amp; Soziales</div>
    <div class="si" onclick="qS('buergergeld')"><div class="si-ic">&#x1F4BC;</div><span class="si-tx">B&#252;rgergeld Antrag</span><span class="si-tag tg-p">PDF</span></div>
    <div class="si" onclick="qS('weiterbewilligung')"><div class="si-ic">&#x1F504;</div><span class="si-tx">Weiterbewilligung</span></div>
    <div class="si" onclick="qS('sanktion_widerspruch')"><div class="si-ic">&#x1F6AB;</div><span class="si-tx">Sanktion Widerspruch</span></div>
    <div class="si" onclick="qS('wohngeld')"><div class="si-ic">&#x1F3D8;&#xFE0F;</div><span class="si-tx">Wohngeld Antrag</span></div>
    <div class="sb-sec"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>Strafrecht &amp; Polizei</div>
    <div class="si" onclick="qS('strafanzeige')"><div class="si-ic">&#x1F6A8;</div><span class="si-tx">Strafanzeige</span></div>
    <div class="si" onclick="qS('strafanzeige_beleidigung')"><div class="si-ic">&#x1F5E3;&#xFE0F;</div><span class="si-tx">Beleidigung &#167;185</span></div>
    <div class="si" onclick="qS('strafanzeige_betrug')"><div class="si-ic">&#x1F3AD;</div><span class="si-tx">Betrug &#167;263</span></div>
    <div class="si" onclick="qS('stalking')"><div class="si-ic">&#x1F441;&#xFE0F;</div><span class="si-tx">Stalking &#167;238</span></div>
    <div class="si" onclick="qS('koerperverletzung')"><div class="si-ic">&#x1FA79;</div><span class="si-tx">K&#246;rperverletzung &#167;223</span></div>
    <div class="sb-sec"><svg viewBox="0 0 24 24"><polyline points="1,4 1,10 7,10"/><path d="M3.51 15a9 9 0 1 0 .49-3.51"/></svg>Auto &amp; Verkehr</div>
    <div class="si" onclick="qS('kfz_kaufvertrag')"><div class="si-ic">&#x1F697;</div><span class="si-tx">KFZ Kaufvertrag</span></div>
    <div class="si" onclick="qS('kfz_kuendigung')"><div class="si-ic">&#x1F4CB;</div><span class="si-tx">KFZ-Versicherung K&#252;ndigung</span></div>
    <div class="si" onclick="qS('bussgeldbescheid')"><div class="si-ic">&#x1F4F8;</div><span class="si-tx">Bu&#223;geldbescheid Einspruch</span></div>
    <div class="sb-sec"><svg viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg>Verbraucher &amp; Vertr&#228;ge</div>
    <div class="si" onclick="qS('widerruf')"><div class="si-ic">&#x21A9;&#xFE0F;</div><span class="si-tx">Widerruf / R&#252;ckgabe</span></div>
    <div class="si" onclick="qS('mahnschreiben')"><div class="si-ic">&#x1F4EC;</div><span class="si-tx">Mahnschreiben</span></div>
    <div class="si" onclick="qS('fluggastrechte')"><div class="si-ic">&#x2708;&#xFE0F;</div><span class="si-tx">Fluggastrechte &#167;EU261</span></div>
    <div class="si" onclick="qS('inkasso')"><div class="si-ic">&#x1F3E6;</div><span class="si-tx">Inkasso Widerspruch</span></div>
    <div class="si" onclick="qS('abo_kuendigung')"><div class="si-ic">&#x1F4F1;</div><span class="si-tx">Abo-K&#252;ndigung</span></div>
    <div class="si" onclick="qS('werkvertrag')"><div class="si-ic">&#x1F528;</div><span class="si-tx">Werkvertrag</span></div>
    <div class="si" onclick="qS('nda')"><div class="si-ic">&#x1F92B;</div><span class="si-tx">Geheimhaltung NDA</span></div>
    <div class="si" onclick="qS('dsgvo_auskunft')"><div class="si-ic">&#x1F510;</div><span class="si-tx">DSGVO Auskunft</span></div>
    <div class="si" onclick="qS('schufa_widerspruch')"><div class="si-ic">&#x1F4CA;</div><span class="si-tx">Schufa Widerspruch</span></div>
    <div class="si" onclick="qS('energievertrag_kuendigung')"><div class="si-ic">&#x26A1;</div><span class="si-tx">Energie/Internet Kündigung</span></div>
    <div class="si" onclick="qS('reklamation')"><div class="si-ic">&#x1F4E6;</div><span class="si-tx">Reklamation / Mängelrüge</span></div>

    <div class="sb-sec"><svg viewBox="0 0 24 24"><path d="M20 7H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>Einwanderung &amp; Aufenthalt</div>
    <div class="si" onclick="qS('duldung_verlaengerung')"><div class="si-ic">&#x1F4C5;</div><span class="si-tx">Duldung Verlängerung</span><span class="si-tag tg-p">§60a</span></div>
    <div class="si" onclick="qS('niederlassungserlaubnis')"><div class="si-ic">&#x2B50;</div><span class="si-tx">Niederlassungserlaubnis §9</span></div>
    <div class="si" onclick="qS('familiennachzug')"><div class="si-ic">&#x1F46A;</div><span class="si-tx">Familiennachzug §28/29/30</span></div>
    <div class="si" onclick="qS('aufenthaltserlaubnis_verlaengerung')"><div class="si-ic">&#x1F501;</div><span class="si-tx">Aufenthaltserlaubnis verlängern</span></div>
    <div class="si" onclick="qS('abschiebungsschutz')"><div class="si-ic">&#x1F6E1;&#xFE0F;</div><span class="si-tx">Abschiebungsschutz §60</span></div>
    <div class="si" onclick="qS('einbuergerung_antrag')"><div class="si-ic">&#x1F1E9;&#x1F1EA;</div><span class="si-tx">Einbürgerung §10 AufenthG</span></div>
    <div class="si" onclick="qS('reiseausweis_auslaender')"><div class="si-ic">&#x1F4D4;</div><span class="si-tx">Reiseausweis für Ausländer</span></div>

    <div class="sb-sec"><svg viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>Gesundheit &amp; Pflege</div>
    <div class="si" onclick="qS('krankenkasse_widerspruch')"><div class="si-ic">&#x1F3E5;</div><span class="si-tx">Krankenkasse Widerspruch</span></div>
    <div class="si" onclick="qS('pflegegrad_widerspruch')"><div class="si-ic">&#x1F9BA;</div><span class="si-tx">Pflegegrad Widerspruch</span></div>
    <div class="si" onclick="qS('reha_antrag')"><div class="si-ic">&#x1FA7C;</div><span class="si-tx">Reha-Antrag</span></div>
    <div class="si" onclick="qS('behandlungsfehler')"><div class="si-ic">&#x2695;&#xFE0F;</div><span class="si-tx">Behandlungsfehler Anzeige</span></div>
    <div class="si" onclick="qS('krankengeld_antrag')"><div class="si-ic">&#x1F4B6;</div><span class="si-tx">Krankengeld Antrag</span></div>
    <div class="si" onclick="qS('hilfsmittel_antrag')"><div class="si-ic">&#x267F;</div><span class="si-tx">Hilfsmittel Antrag</span></div>

    <div class="sb-sec"><svg viewBox="0 0 24 24"><path d="M22 10v6M2 10l10-7 10 7"/><path d="M6 10v8a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-8"/></svg>Bildung &amp; Schule</div>
    <div class="si" onclick="qS('bafoeg_antrag')"><div class="si-ic">&#x1F393;</div><span class="si-tx">BAföG Antrag</span><span class="si-tag tg-p">PDF</span></div>
    <div class="si" onclick="qS('bafoeg_widerspruch')"><div class="si-ic">&#x1F4DD;</div><span class="si-tx">BAföG Widerspruch</span></div>
    <div class="si" onclick="qS('nachteilsausgleich')"><div class="si-ic">&#x2696;&#xFE0F;</div><span class="si-tx">Nachteilsausgleich Antrag</span></div>
    <div class="si" onclick="qS('pruefungsanfechtung')"><div class="si-ic">&#x274C;</div><span class="si-tx">Prüfungsanfechtung</span></div>
    <div class="si" onclick="qS('schulwechsel')"><div class="si-ic">&#x1F4DA;</div><span class="si-tx">Schulwechsel Antrag</span></div>

    <div class="sb-sec"><svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>Gewerbe &amp; Selbstständige</div>
    <div class="si" onclick="qS('gewerbeanmeldung')"><div class="si-ic">&#x1F3E2;</div><span class="si-tx">Gewerbeanmeldung</span></div>
    <div class="si" onclick="qS('gewerbeabmeldung')"><div class="si-ic">&#x1F6AA;</div><span class="si-tx">Gewerbeabmeldung</span></div>
    <div class="si" onclick="qS('gbr_vertrag')"><div class="si-ic">&#x1F91D;</div><span class="si-tx">GbR Gesellschaftsvertrag</span></div>
    <div class="si" onclick="qS('stundungsantrag_fa')"><div class="si-ic">&#x23F3;</div><span class="si-tx">Stundungsantrag Finanzamt</span></div>
    <div class="si" onclick="qS('zahlungsplan_fa')"><div class="si-ic">&#x1F4C8;</div><span class="si-tx">Ratenzahlung Finanzamt</span></div>
    <div class="si" onclick="qS('freelancer_vertrag')"><div class="si-ic">&#x1F4BB;</div><span class="si-tx">Freelancer Vertrag</span></div>
  </div>
  <div class="sb-foot">
    <div class="sb-foot-txt">chat-help.de &#183; chat-help.com &#183; Legal Documents Europe</div>
    <div class="sb-badges"><span class="bdg bdg-g">✓ DSGVO</span><span class="bdg bdg-o">✓ EU-Server</span><span class="bdg bdg-o">✓ ChatHelp AI</span></div>
    <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap">
      <a href="#" onclick="oLM('lm-imp');return false" style="font-size:10px;color:var(--t4);text-decoration:none">Impressum</a>
      <span style="font-size:10px;color:var(--t4)">·</span>
      <a href="#" onclick="oLM('lm-dsc');return false" style="font-size:10px;color:var(--t4);text-decoration:none">Datenschutz</a>
      <span style="font-size:10px;color:var(--t4)">·</span>
      <a href="mailto:support@chat-help.de" style="font-size:10px;color:var(--t4);text-decoration:none">Kontakt</a>
    </div>
  </div>
</div>

<div id="mn">
  <div id="tb">
    <div class="tb-l">
      <div class="tb-ham" onclick="oSB()"><span></span><span></span><span></span></div>
      <div class="tb-name" onclick="NC()" style="cursor:pointer">Chat<em>Help</em></div>
    </div>
    <div class="tb-r">
      <div class="tb-flag" id="tb-flag" onclick="gP('konto')" title="Land &amp; Sprache">&#x1F1E9;&#x1F1EA;</div>
      <div class="tb-btn" onclick="document.getElementById('pf').click()" title="Brief fotografieren">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
      </div>
      <div class="tb-btn hi" onclick="gP('konto')" title="Konto">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      </div>
      <div class="tb-btn" onclick="gP('ant')" title="Meine Antr&#228;ge">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>
      </div>
      <div class="tb-online"><div class="tb-dot"></div>Online</div>
    </div>
  </div>

  <div class="pnl on" id="pnl-chat" style="overflow:hidden;min-width:0;background:#07070e">
    <div id="msgs"><div id="mi">
      <div class="wlc" id="wlc">
        <div class="wlc-hero">
          <div class="wlc-mark">
            <svg viewBox="0 0 40 40" fill="none" width="26" height="26">
              <path d="M6 32L13 14L22 25L27 18L34 32" stroke="#d4a84a" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/>
              <circle cx="13" cy="14" r="3.5" fill="#d4a84a"/>
              <circle cx="27" cy="18" r="2.2" fill="#ecc060" opacity=".7"/>
            </svg>
          </div>
          <h2 id="wlc-h">Rechtsdokumente f&#252;r Europa</h2>
          <p id="wlc-p">KI erstellt Ihr pers&#246;nliches Dokument &#8212; in Sekunden, in Ihrer Sprache.</p>
          <div class="wlc-stats">
            <div class="wlc-stat"><span class="wlc-stat-n">75+</span><span class="wlc-stat-l">Dokumente</span></div>
            <div class="wlc-stat-div"></div>
            <div class="wlc-stat"><span class="wlc-stat-n">12</span><span class="wlc-stat-l">L&#228;nder</span></div>
            <div class="wlc-stat-div"></div>
            <div class="wlc-stat"><span class="wlc-stat-n">8</span><span class="wlc-stat-l">Sprachen</span></div>
            <div class="wlc-stat-div"></div>
            <div class="wlc-stat"><span class="wlc-stat-n">0</span><span class="wlc-stat-l">Tracking</span></div>
          </div>
        </div>
        <div class="wphoto" onclick="document.getElementById('pf').click()">
          <div class="wphoto-ic"><svg viewBox="0 0 24 24" fill="none" stroke="#d4a84a" stroke-width="1.8" stroke-linecap="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></div>
          <div style="flex:1"><div class="wphoto-t">Brief / Bescheid fotografieren</div><div class="wphoto-s">ChatHelp Vision erkennt Beh&#246;rde, Frist &amp; Aktenzeichen</div></div>
          <span style="font-size:10px;background:var(--ga);border:1px solid var(--gb);color:var(--gold);padding:3px 9px;border-radius:100px;font-weight:700;flex-shrink:0">KI</span>
        </div>
        <div class="wcat-grid">
          <div class="wcat" onclick="showCat('behoerde')">
            <div class="wcat-ic" style="background:rgba(212,168,74,.12)"><svg viewBox="0 0 24 24" fill="none" stroke="#d4a84a" stroke-width="1.8" stroke-linecap="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg></div>
            <div class="wcat-t" id="wc1">Beh&#246;rde</div><div class="wcat-s">Widerspruch &bull; Aufenthalt</div>
          </div>
          <div class="wcat" onclick="showCat('immigration')">
            <div class="wcat-ic" style="background:rgba(66,150,255,.12)"><svg viewBox="0 0 24 24" fill="none" stroke="#6694ff" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg></div>
            <div class="wcat-t">Einwanderung</div><div class="wcat-s">Duldung &bull; Visum &bull; AE</div>
          </div>
          <div class="wcat" onclick="showCat('miete')">
            <div class="wcat-ic" style="background:rgba(66,223,148,.1)"><svg viewBox="0 0 24 24" fill="none" stroke="#42df94" stroke-width="1.8" stroke-linecap="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg></div>
            <div class="wcat-t" id="wc3">Miete</div><div class="wcat-s">Mietvertrag &bull; K&#252;ndigung</div>
          </div>
          <div class="wcat" onclick="showCat('arbeit')">
            <div class="wcat-ic" style="background:rgba(200,130,255,.1)"><svg viewBox="0 0 24 24" fill="none" stroke="#c882ff" stroke-width="1.8" stroke-linecap="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg></div>
            <div class="wcat-t" id="wc4">Arbeit</div><div class="wcat-s">Vertrag &bull; K&#252;ndigung</div>
          </div>
          <div class="wcat" onclick="showCat('steuern')">
            <div class="wcat-ic" style="background:rgba(255,180,50,.1)"><svg viewBox="0 0 24 24" fill="none" stroke="#ffb432" stroke-width="1.8" stroke-linecap="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></div>
            <div class="wcat-t" id="wc2">Steuern</div><div class="wcat-s">Erkl&#228;rung &bull; Einspruch</div>
          </div>
          <div class="wcat" onclick="showCat('soziales')">
            <div class="wcat-ic" style="background:rgba(50,200,200,.1)"><svg viewBox="0 0 24 24" fill="none" stroke="#32c8c8" stroke-width="1.8" stroke-linecap="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/></svg></div>
            <div class="wcat-t">Soziales</div><div class="wcat-s">B&#252;rgergeld &bull; BAf&#246;G</div>
          </div>
          <div class="wcat" onclick="showCat('gesundheit')">
            <div class="wcat-ic" style="background:rgba(255,80,100,.1)"><svg viewBox="0 0 24 24" fill="none" stroke="#ff5064" stroke-width="1.8" stroke-linecap="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
            <div class="wcat-t">Gesundheit</div><div class="wcat-s">Krankenkasse &bull; Pflege</div>
          </div>
          <div class="wcat" onclick="showCat('gewerbe')">
            <div class="wcat-ic" style="background:rgba(255,150,50,.1)"><svg viewBox="0 0 24 24" fill="none" stroke="#ff9632" stroke-width="1.8" stroke-linecap="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><line x1="12" y1="3" x2="12" y2="7"/></svg></div>
            <div class="wcat-t">Gewerbe</div><div class="wcat-s">Anmeldung &bull; GbR &bull; NDA</div>
          </div>
          <div class="wcat" onclick="showCat('strafrecht')">
            <div class="wcat-ic" style="background:rgba(224,80,80,.1)"><svg viewBox="0 0 24 24" fill="none" stroke="#e05050" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
            <div class="wcat-t">Strafrecht</div><div class="wcat-s">Anzeige &bull; Widerspruch</div>
          </div>
        </div>
        <div id="recent-sec" style="display:none"><div class="recent-bar"><div class="recent-bar-t">Zuletzt erstellt</div><div id="recent-list"></div></div></div>
        <div class="lang-bar"><span class="lang-txt">Spracherkennung aktiv:</span><div class="lflags"><span class="lf on" id="lf-de">&#x1F1E9;&#x1F1EA;</span><span class="lf" id="lf-tr">&#x1F1F9;&#x1F1F7;</span><span class="lf" id="lf-ar">&#x1F1F8;&#x1F1E6;</span><span class="lf" id="lf-ru">&#x1F1F7;&#x1F1FA;</span><span class="lf" id="lf-en">&#x1F1EC;&#x1F1E7;</span><span class="lf" id="lf-fr">&#x1F1EB;&#x1F1F7;</span></div></div>
      </div>
    </div></div>
    <div id="ia">
      <div class="ia-inner">
        <textarea id="inp" rows="1" placeholder="Schreiben Sie Ihr Anliegen&#8230;" onkeydown="onKey(event)" oninput="onInp(this)"></textarea>
        <div class="ia-btns">
          <div id="pb" onclick="document.getElementById('pf').click()"><svg viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></div>
          <button id="sbtn" onclick="doSend()"><svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22,2 15,22 11,13 2,9"/></svg></button>
        </div>
      </div>
      <div class="ia-foot"><span class="ia-foot-txt">Daten nur auf Ihrem Server &#183; ChatHelp AI &#183; DSGVO &#183; EU-Server</span><div class="ia-lfs"><span class="lf2 on" id="fl-de">&#x1F1E9;&#x1F1EA;</span><span class="lf2" id="fl-tr">&#x1F1F9;&#x1F1F7;</span><span class="lf2" id="fl-ar">&#x1F1F8;&#x1F1E6;</span><span class="lf2" id="fl-ru">&#x1F1F7;&#x1F1FA;</span><span class="lf2" id="fl-en">&#x1F1EC;&#x1F1E7;</span><span class="lf2" id="fl-fr">&#x1F1EB;&#x1F1F7;</span></div></div>
    </div>
  </div>

  <div class="pnl" id="pnl-ant"><div class="pnl-scroll">
    <div class="sec-hdr"><div><div class="sec-title">Meine Antr&#228;ge</div><div class="sec-sub">Gespeichert auf Ihrem Server &#183; 24h L&#246;schung &#183; "Behalten" f&#252;r dauerhaft</div></div><button class="btn-s" onclick="loadHistory()" style="width:auto;padding:8px 13px;font-size:12px;flex-shrink:0">&#x21BB; Aktualisieren</button></div>
    <div class="ant-stats" id="ant-stats" style="display:none">
      <div class="astat"><div class="astat-n" id="stat-tot">0</div><div class="astat-l">Gesamt</div></div>
      <div class="astat"><div class="astat-n gold" id="stat-kept">0</div><div class="astat-l">Dauerhaft</div></div>
      <div class="astat"><div class="astat-n gr" id="stat-exp">0</div><div class="astat-l">L&#228;uft ab</div></div>
    </div>
    <div class="ant-list" id="ant-list"><div class="ant-empty"><div class="ant-empty-ic"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/></svg></div><div class="ant-empty-t">Noch keine Antr&#228;ge</div><div class="ant-empty-s">Erstellen Sie Ihr erstes Dokument &#8212; es erscheint hier.</div></div></div>
  </div></div>

  <div class="pnl" id="pnl-konto"><div class="pnl-scroll">
    <div class="sec-hdr"><div><div class="sec-title">Konto &amp; Einstellungen</div><div class="sec-sub">Profil, Land, Sprache &amp; Datenschutz</div></div></div>
    <div class="konto-wrap">
      <div class="konto-plan">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
          <div>
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--gold);opacity:.7;margin-bottom:4px">Aktueller Plan</div>
            <div class="kp-name" id="k-plan-name" style="font-size:22px;font-weight:800;letter-spacing:-.5px">Free</div>
          </div>
          <div class="kp-badge" style="font-size:10px;padding:5px 14px;border-radius:100px">AKTIV</div>
        </div>
        <div class="kp-feats">
          <div class="kp-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Alle EU-L&#228;nder &amp; Rechtssysteme</div>
          <div class="kp-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>8 Sprachen automatisch erkannt</div>
          <div class="kp-feat"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>24h Dokumentenspeicher</div>
        </div>
        <div style="background:rgba(212,168,74,.06);border:1px solid rgba(212,168,74,.15);border-radius:var(--r);padding:12px 14px;margin-bottom:14px">
          <div style="font-size:11px;color:var(--gold);font-weight:600;margin-bottom:4px">&#x26A1; Pro freischalten</div>
          <div style="font-size:12px;color:#d0d0f0;line-height:1.5">Unbegrenzte Dokumente &#183; Foto-Analyse &#183; Follow-up &#183; Dauerhaft speichern &#183; Anwalt-Check</div>
        </div>
        <button class="btn-g" onclick="oPM()" style="margin-top:0">&#x2B50; Auf Pro upgraden &#8594;</button>
      </div>
      <div class="ksec">
        <div class="ksec-hdr"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg><div class="ksec-hdr-t">Land &amp; Rechtssystem</div></div>
        <div class="ksec-body"><div class="country-grid" id="cg">
          <div class="co on" data-cc="DE" onclick="setCountry('DE')"><div class="co-flag">&#x1F1E9;&#x1F1EA;</div><div class="co-n">Deutschland</div></div>
          <div class="co" data-cc="AT" onclick="setCountry('AT')"><div class="co-flag">&#x1F1E6;&#x1F1F9;</div><div class="co-n">&#214;sterreich</div></div>
          <div class="co" data-cc="CH" onclick="setCountry('CH')"><div class="co-flag">&#x1F1E8;&#x1F1ED;</div><div class="co-n">Schweiz</div></div>
          <div class="co" data-cc="FR" onclick="setCountry('FR')"><div class="co-flag">&#x1F1EB;&#x1F1F7;</div><div class="co-n">France</div></div>
          <div class="co" data-cc="IT" onclick="setCountry('IT')"><div class="co-flag">&#x1F1EE;&#x1F1F9;</div><div class="co-n">Italia</div></div>
          <div class="co" data-cc="ES" onclick="setCountry('ES')"><div class="co-flag">&#x1F1EA;&#x1F1F8;</div><div class="co-n">Espa&#241;a</div></div>
          <div class="co" data-cc="NL" onclick="setCountry('NL')"><div class="co-flag">&#x1F1F3;&#x1F1F1;</div><div class="co-n">Nederland</div></div>
          <div class="co" data-cc="BE" onclick="setCountry('BE')"><div class="co-flag">&#x1F1E7;&#x1F1EA;</div><div class="co-n">Belgique</div></div>
          <div class="co" data-cc="PL" onclick="setCountry('PL')"><div class="co-flag">&#x1F1F5;&#x1F1F1;</div><div class="co-n">Polska</div></div>
          <div class="co" data-cc="SE" onclick="setCountry('SE')"><div class="co-flag">&#x1F1F8;&#x1F1EA;</div><div class="co-n">Sverige</div></div>
          <div class="co" data-cc="PT" onclick="setCountry('PT')"><div class="co-flag">&#x1F1F5;&#x1F1F9;</div><div class="co-n">Portugal</div></div>
          <div class="co" data-cc="EU" onclick="setCountry('EU')"><div class="co-flag">&#x1F1EA;&#x1F1FA;</div><div class="co-n">Andere EU</div></div>
        </div></div>
      </div>
      <div class="ksec">
        <div class="ksec-hdr"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><div class="ksec-hdr-t">Interface-Sprache</div></div>
        <div class="ksec-body"><div class="lang-grid" id="lg">
          <button class="lang-opt on" data-l="de" onclick="setLang('de')"><span class="lang-flag">&#x1F1E9;&#x1F1EA;</span>Deutsch</button>
          <button class="lang-opt" data-l="en" onclick="setLang('en')"><span class="lang-flag">&#x1F1EC;&#x1F1E7;</span>English</button>
          <button class="lang-opt" data-l="tr" onclick="setLang('tr')"><span class="lang-flag">&#x1F1F9;&#x1F1F7;</span>T&#252;rk&#231;e</button>
          <button class="lang-opt" data-l="ar" onclick="setLang('ar')"><span class="lang-flag">&#x1F1F8;&#x1F1E6;</span>&#x627;&#x644;&#x639;&#x631;&#x628;&#x64A;&#x629;</button>
          <button class="lang-opt" data-l="fr" onclick="setLang('fr')"><span class="lang-flag">&#x1F1EB;&#x1F1F7;</span>Fran&#231;ais</button>
          <button class="lang-opt" data-l="ru" onclick="setLang('ru')"><span class="lang-flag">&#x1F1F7;&#x1F1FA;</span>&#x420;&#x443;&#x441;&#x441;&#x43A;&#x438;&#x439;</button>
          <button class="lang-opt" data-l="it" onclick="setLang('it')"><span class="lang-flag">&#x1F1EE;&#x1F1F9;</span>Italiano</button>
          <button class="lang-opt" data-l="es" onclick="setLang('es')"><span class="lang-flag">&#x1F1EA;&#x1F1F8;</span>Espa&#241;ol</button>
        </div></div>
      </div>
      <div class="ksec">
        <div class="ksec-hdr"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><div class="ksec-hdr-t">Profil</div></div>
        <div class="ksec-body">
          <div class="krow"><span class="krow-l">Name</span><span class="krow-r" id="k-name">&#8212;</span></div>
          <div class="krow"><span class="krow-l">E-Mail</span><span class="krow-r" id="k-email">&#8212;</span></div>
          <div class="krow"><span class="krow-l">Adresse</span><span class="krow-r" id="k-adr">&#8212;</span></div>
          <div class="krow"><span class="krow-l">Land</span><span class="krow-r gold" id="k-cc">&#x1F1E9;&#x1F1EA; Deutschland</span></div>
          <div style="margin-top:12px;display:flex;gap:7px;flex-wrap:wrap">
            <button class="btn-s" style="width:auto;font-size:12px;padding:7px 13px" onclick="editProfile()">&#x270F;&#xFE0F; Bearbeiten</button>
            <button class="btn-s" style="width:auto;font-size:12px;padding:7px 13px;color:var(--rd)" onclick="deleteProfile()">&#x1F5D1;&#xFE0F; Profil l&#246;schen</button>
          </div>
        </div>
      </div>
      <div class="kdsgvo">
        <h4><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>Datenschutz &amp; DSGVO</h4>
        <div class="kdsgvo-item"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>ChatHelp AI verarbeitet NUR anonyme Platzhalter &#8212; kein Name, keine Adresse</div>
        <div class="kdsgvo-item"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Echte Daten werden ausschlie&#223;lich auf Ihrem Server eingesetzt</div>
        <div class="kdsgvo-item"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Alle Dokumente nach 24h automatisch gel&#246;scht (au&#223;er "Behalten")</div>
        <div class="kdsgvo-item"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>Server: Deutschland (EU) &#183; Keine Weitergabe an Dritte</div>
        <div class="kdsgvo-item"><svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>API-Keys nur auf dem Server &#183; Jederzeit l&#246;schbar</div>
      </div>
    </div>
  </div></div>
</div>
</div>

<script>
const API='api.php',CK='ch_conv_v3',PK='ch_pref_v3',FK='ch_prof_v3';
const CC_DATA={
  DE:{n:'Deutschland',f:'\u{1F1E9}\u{1F1EA}',l:'de',law:'BGB, StGB, VwGO'},
  AT:{n:'\u00d6sterreich',f:'\u{1F1E6}\u{1F1F9}',l:'de',law:'ABGB, StGB, VwGVG'},
  CH:{n:'Schweiz',f:'\u{1F1E8}\u{1F1ED}',l:'de',law:'OR, ZGB, StGB'},
  FR:{n:'France',f:'\u{1F1EB}\u{1F1F7}',l:'fr',law:'Code civil, Code du travail'},
  IT:{n:'Italia',f:'\u{1F1EE}\u{1F1F9}',l:'it',law:'Codice civile, Cod. penale'},
  ES:{n:'Espa\u00f1a',f:'\u{1F1EA}\u{1F1F8}',l:'es',law:'C\u00f3digo civil, Cod. penal'},
  NL:{n:'Nederland',f:'\u{1F1F3}\u{1F1F1}',l:'nl',law:'BW, WvSr, Awb'},
  BE:{n:'Belgique',f:'\u{1F1E7}\u{1F1EA}',l:'fr',law:'Code civil belge'},
  PL:{n:'Polska',f:'\u{1F1F5}\u{1F1F1}',l:'pl',law:'KC, KPC'},
  SE:{n:'Sverige',f:'\u{1F1F8}\u{1F1EA}',l:'sv',law:'BrB, JB'},
  PT:{n:'Portugal',f:'\u{1F1F5}\u{1F1F9}',l:'pt',law:'C\u00f3digo civil'},
  EU:{n:'Europe',f:'\u{1F1EA}\u{1F1FA}',l:'en',law:'EU Law'},
};
const UI_TEXTS={
  de:{h:'Rechtsdokumente f\u00fcr Europa',p:'Schreiben Sie Ihr Anliegen \u2014 Sprache wird automatisch erkannt.',c1:'Beh\u00f6rde & Amt',c2:'Steuern',c3:'Miete & Wohnen',c4:'Arbeit & Beruf',you:'Sie',ph:'Schreiben Sie Ihr Anliegen\u2026'},
  en:{h:'Legal Documents for Europe',p:'Describe your issue \u2014 language is detected automatically.',c1:'Authorities',c2:'Taxes',c3:'Rent & Housing',c4:'Work & Career',you:'You',ph:'Describe your concern\u2026'},
  fr:{h:'Documents juridiques pour l\'Europe',p:'\u00c9crivez votre demande \u2014 la langue est d\u00e9tect\u00e9e automatiquement.',c1:'Autorit\u00e9s',c2:'Imp\u00f4ts',c3:'Logement',c4:'Travail',you:'Vous',ph:'D\u00e9crivez votre demande\u2026'},
  tr:{h:'Avrupa i\u00e7in Hukuki Belgeler',p:'Talebinizi yaz\u0131n \u2014 dil otomatik tespit edilir.',c1:'Resmi Kurumlar',c2:'Vergiler',c3:'Kira & Konut',c4:'\u0130\u015f & Kariyer',you:'Siz',ph:'Talebinizi yaz\u0131n\u2026'},
  ar:{h:'\u0627\u0644\u0645\u0633\u062a\u0646\u062f\u0627\u062a \u0627\u0644\u0642\u0627\u0646\u0648\u0646\u064a\u0629 \u0644\u0623\u0648\u0631\u0648\u0628\u0627',p:'\u0627\u0643\u062a\u0628 \u0637\u0644\u0628\u0643 \u2014 \u0633\u064a\u062a\u0645 \u0627\u0643\u062a\u0634\u0627\u0641 \u0627\u0644\u0644\u063a\u0629 \u062a\u0644\u0642\u0627\u0626\u064a\u064b\u0627.',c1:'\u0627\u0644\u0633\u0644\u0637\u0627\u062a',c2:'\u0627\u0644\u0636\u0631\u0627\u0626\u0628',c3:'\u0627\u0644\u0633\u0643\u0646',c4:'\u0627\u0644\u0639\u0645\u0644',you:'\u0623\u0646\u062a',ph:'\u0627\u0643\u062a\u0628 \u0637\u0644\u0628\u0643\u2026'},
  ru:{h:'\u042e\u0440\u0438\u0434\u0438\u0447\u0435\u0441\u043a\u0438\u0435 \u0434\u043e\u043a\u0443\u043c\u0435\u043d\u0442\u044b \u0434\u043b\u044f \u0415\u0432\u0440\u043e\u043f\u044b',p:'\u041e\u043f\u0438\u0448\u0438\u0442\u0435 \u0432\u0430\u0448 \u0432\u043e\u043f\u0440\u043e\u0441 \u2014 \u044f\u0437\u044b\u043a \u043e\u043f\u0440\u0435\u0434\u0435\u043b\u044f\u0435\u0442\u0441\u044f \u0430\u0432\u0442\u043e\u043c\u0430\u0442\u0438\u0447\u0435\u0441\u043a\u0438.',c1:'\u0412\u0435\u0434\u043e\u043c\u0441\u0442\u0432\u0430',c2:'\u041d\u0430\u043b\u043e\u0433\u0438',c3:'\u0410\u0440\u0435\u043d\u0434\u0430',c4:'\u0420\u0430\u0431\u043e\u0442\u0430',you:'\u0412\u044b',ph:'\u041e\u043f\u0438\u0448\u0438\u0442\u0435 \u0432\u0430\u0448 \u0432\u043e\u043f\u0440\u043e\u0441\u2026'},
};
const DOCS={
  widerspruch:{n:'Widerspruch',ic:'\u2696\uFE0F',sub:'Gegen Beh\u00f6rdenbescheid',price:3.99,
    qs:[{k:'datum',t:'date',r:1,l:'Datum des Bescheids'},{k:'beh',t:'text',r:1,l:'Ausstellende Beh\u00f6rde (z.B. ABH Heidenheim)'},{k:'az',t:'text',r:0,l:'Aktenzeichen (optional)'},{k:'begrundung',t:'textarea',r:1,l:'Begr\u00fcndung: Was ist falsch? Argumente?'},{k:'ziel',t:'text',r:0,l:'Was beantragen Sie? (z.B. Aufhebung)'}],
    ns:['\u{1F4CD} Per Einschreiben mit R\u00fcckschein','\u23F0 Frist: 1 Monat ab Bescheiddatum!','\u{1F4CB} Kopie + Sendebeleg aufbewahren','\u2696\uFE0F Bei Ablehnung: Verwaltungsgericht']},
  strafanzeige:{n:'Strafanzeige',ic:'\u{1F6A8}',sub:'Bei der Polizei',price:3.99,
    qs:[{k:'datum',t:'date',r:1,l:'Datum des Vorfalls'},{k:'uhrzeit',t:'text',r:0,l:'Uhrzeit (falls bekannt)'},{k:'ort',t:'text',r:1,l:'Tatort (Adresse/Beschreibung)'},{k:'taeter',t:'text',r:0,l:'T\u00e4ter (falls bekannt)'},{k:'beschreibung',t:'textarea',r:1,l:'Detaillierte Beschreibung: Was ist passiert?'},{k:'zeugen',t:'text',r:0,l:'Zeugen (Namen + Kontakt)'},{k:'beweise',t:'text',r:0,l:'Beweise (Screenshots, Fotos?)'}],
    ns:['\u{1F4CD} Polizeidienststelle oder Online-Wache','\u{1F194} Personalausweis + Beweise mitbringen','\u23F0 Antragsdelikt: 3 Monate Frist','\u{1F4CE} Beweise als Kopie']},
  mietvertrag:{n:'Mietvertrag',ic:'\u{1F3E0}',sub:'Wohnung oder Gewerbe',price:9.99,
    qs:[{k:'vm',t:'text',r:1,l:'Vermieter (Name + Adresse)'},{k:'mi',t:'text',r:1,l:'Mieter (Name + Adresse)'},{k:'adr',t:'text',r:1,l:'Genaue Adresse der Mietwohnung'},{k:'qm',t:'text',r:0,l:'Wohnfl\u00e4che in m\u00b2'},{k:'miete',t:'text',r:1,l:'Kaltmiete (\u20ac/Monat)'},{k:'nk',t:'text',r:0,l:'Nebenkosten-Vorauszahlung (\u20ac/Monat)'},{k:'kaution',t:'text',r:0,l:'Kaution (\u20ac, max. 3 Monatsmieten)'},{k:'beg',t:'date',r:1,l:'Mietbeginn'},{k:'befr',t:'text',r:0,l:'Befristet bis? (leer = unbefristet)'},{k:'besonder',t:'textarea',r:0,l:'Besondere Vereinbarungen?'}],
    ns:['\u270D\uFE0F Beide Parteien unterschreiben','\u{1F4CB} Je ein Original','\u{1F3E6} Kaution auf separates Konto (\u00a7551 BGB)','\u{1F4DD} \u00dcbergabeprotokoll erstellen']},
  kuendigung_mieter:{n:'K\u00fcndigung (Mieter)',ic:'\u{1F6AA}',sub:'Ordentliche K\u00fcndigung',price:2.99,
    qs:[{k:'vm',t:'text',r:1,l:'Vermieter (Name + Adresse)'},{k:'woh',t:'text',r:1,l:'Adresse der Wohnung'},{k:'kdat',t:'date',r:1,l:'Gew\u00fcnschtes K\u00fcndigungsdatum'},{k:'grund',t:'textarea',r:0,l:'Begr\u00fcndung (optional)'}],
    ns:['\u2709\uFE0F Per Einschreiben mit R\u00fcckschein!','\u23F0 3 Monate zum Monatsende','\u{1F4C5} Bis 3. Werktag des Monats','\u{1F511} Schl\u00fcsselr\u00fcckgabe dokumentieren']},
  arbeitsvertrag:{n:'Arbeitsvertrag',ic:'\u{1F4DD}',sub:'Vollzeit, Teilzeit oder Minijob',price:9.99,
    qs:[{k:'ag',t:'text',r:1,l:'Arbeitgeber (Firma + Adresse)'},{k:'an',t:'text',r:1,l:'Arbeitnehmer (Name)'},{k:'pos',t:'text',r:1,l:'Genaue Berufsbezeichnung'},{k:'beg',t:'date',r:1,l:'Arbeitsbeginn'},{k:'geh',t:'text',r:1,l:'Bruttogehalt (\u20ac/Monat)'},{k:'azei',t:'text',r:1,l:'W\u00f6chentliche Arbeitszeit (Std.)'},{k:'url',t:'text',r:0,l:'Urlaubstage/Jahr (mind. 20)'},{k:'probe',t:'text',r:0,l:'Probezeit in Monaten (max. 6)'},{k:'ort',t:'text',r:0,l:'Arbeitsort'},{k:'besonder',t:'textarea',r:0,l:'Besondere Vereinbarungen?'}],
    ns:['\u270D\uFE0F Beide unterschreiben','\u{1F4C5} Sp\u00e4testens am 1. Arbeitstag','\u{1F4CB} Je ein Original','\u{1F50D} MiLoG: Mindestlohn beachten!']},
  testament:{n:'Testament',ic:'\u{1F4C3}',sub:'Eigenh\u00e4ndiges Testament',price:12.99,
    qs:[{k:'erben',t:'textarea',r:1,l:'Wer soll was erben? (Name, Verwandtschaft, %)'},{k:'verm',t:'textarea',r:0,l:'Besondere Verm\u00e4chtnisse (Schmuck an, Auto an...)?'},{k:'tv',t:'text',r:0,l:'Testamentsvollstrecker? (Name + Adresse)'},{k:'beer',t:'textarea',r:0,l:'Bestattungsw\u00fcnsche?'}],
    ns:['\u270D\uFE0F Vollst\u00e4ndig HANDSCHRIFTLICH!','\u{1F4C5} Datum, Ort + voller Name zwingend','\u26A0\uFE0F PC-Testament ist UNG\u00dcLTIG!','\u{1F5C4}\uFE0F Beim Notar oder Amtsgericht hinterlegen']},
  vorsorgevollmacht:{n:'Vorsorgevollmacht',ic:'\u{1F3E5}',sub:'Gesundheit & Finanzen',price:9.99,
    qs:[{k:'bev1',t:'text',r:1,l:'Bevollm\u00e4chtigte Person (Name + Adresse)'},{k:'bev2',t:'text',r:0,l:'Ersatz-Bevollm\u00e4chtigte (falls erste ausf\u00e4llt)'},{k:'gesundheit',t:'text',r:1,l:'Gesundheitliche Entscheidungen? (Ja/Nein)'},{k:'finanzen',t:'text',r:1,l:'Finanzielle Angelegenheiten? (Ja/Nein)'},{k:'einschr',t:'textarea',r:0,l:'Einschr\u00e4nkungen?'}],
    ns:['\u{1F4CB} Notarielle Beglaubigung empfohlen','\u{1F5C4}\uFE0F Beim Betreuungsgericht registrieren','\u{1F464} Bevollm\u00e4chtigte informieren + Original','\u{1F4B3} Original immer zug\u00e4nglich']},
  vollmacht:{n:'Vollmacht',ic:'\u270D\uFE0F',sub:'Allgemeine Vertretung',price:2.99,
    qs:[{k:'bev',t:'text',r:1,l:'Bevollm\u00e4chtigte Person (Name + Adresse)'},{k:'zweck',t:'textarea',r:1,l:'F\u00fcr was gilt die Vollmacht?'},{k:'bis',t:'text',r:0,l:'G\u00fcltig bis (leer = unbefristet)'}],
    ns:['\u270D\uFE0F Original unterschreiben','\u{1F4CB} Kopie aufbewahren']},
  kfz_kaufvertrag:{n:'KFZ Kaufvertrag',ic:'\u{1F697}',sub:'Privater Fahrzeugverkauf',price:4.99,
    qs:[{k:'vk',t:'text',r:1,l:'Verk\u00e4ufer (Name + Adresse)'},{k:'ka',t:'text',r:1,l:'K\u00e4ufer (Name + Adresse)'},{k:'fzg',t:'text',r:1,l:'Fahrzeug: Marke, Modell, Baujahr'},{k:'kz',t:'text',r:1,l:'Kennzeichen'},{k:'km',t:'text',r:1,l:'Kilometerstand'},{k:'preis',t:'text',r:1,l:'Kaufpreis (\u20ac)'},{k:'maengel',t:'textarea',r:1,l:'Bekannte M\u00e4ngel (oder "Keine bekannt")'}],
    ns:['\u270D\uFE0F Beide unterschreiben','\u{1F4CB} Je eine Kopie','\u{1F697} KFZ-Brief \u00fcbergeben','\u26A0\uFE0F "Gekauft wie besehen"']},
  mahnschreiben:{n:'Mahnschreiben',ic:'\u{1F4EC}',sub:'1., 2. oder 3. Mahnung',price:2.99,
    qs:[{k:'schuld',t:'text',r:1,l:'Schuldner (Name + Adresse)'},{k:'betrag',t:'text',r:1,l:'Forderungsbetrag (\u20ac)'},{k:'rdatum',t:'date',r:1,l:'Datum der Rechnung/Leistung'},{k:'frist',t:'date',r:1,l:'Neue Zahlungsfrist bis'},{k:'stufe',t:'text',r:0,l:'Wievielte Mahnung? (1/2/3)'}],
    ns:['\u2709\uFE0F Per Einschreiben','\u{1F4B6} Ab 2. Mahnung: Mahnkosten + Zinsen','\u2696\uFE0F N\u00e4chster Schritt: Mahngericht','\u{1F4CB} Alle Belege aufbewahren']},
  widerruf:{n:'Widerruf / R\u00fcckgabe',ic:'\u21A9\uFE0F',sub:'Online-Kauf, Abo, Vertrag',price:2.99,
    qs:[{k:'haend',t:'text',r:1,l:'H\u00e4ndler/Anbieter (Name + Adresse)'},{k:'kdat',t:'date',r:1,l:'Kauf-/Vertragsdatum'},{k:'produkt',t:'text',r:1,l:'Produkt / Dienstleistung'},{k:'preis',t:'text',r:0,l:'Betrag (\u20ac)'}],
    ns:['\u23F0 Widerrufsfrist: 14 Tage ab Erhalt','\u{1F4E6} Ware zur\u00fcckschicken (Beweis!)','\u{1F4B6} Erstattung innerhalb 14 Tagen','\u{1F4E7} Auch per E-Mail m\u00f6glich']},
  fluggastrechte:{n:'Fluggastrechte \u00a7EU261',ic:'\u2708\uFE0F',sub:'Versp\u00e4tung, Annullierung',price:2.99,
    qs:[{k:'flug',t:'text',r:1,l:'Flugnummer + Datum'},{k:'von',t:'text',r:1,l:'Abflughafen'},{k:'nach',t:'text',r:1,l:'Zielflughafen'},{k:'airline',t:'text',r:1,l:'Fluggesellschaft'},{k:'problem',t:'text',r:1,l:'Versp\u00e4tung (Std.), Annullierung oder \u00dcberbuchung?'},{k:'pers',t:'text',r:1,l:'Anzahl betroffene Personen'}],
    ns:['\u{1F4B6} 250\u20ac (<1500km), 400\u20ac (bis 3500km), 600\u20ac (>3500km)','\u23F0 Verj\u00e4hrungsfrist: 3 Jahre','\u{1F4CD} Zuerst direkt an die Airline','\u{1F517} Bei Ablehnung: s\u00f6p.de']},
  bussgeldbescheid:{n:'Bu\u00dfgeldbescheid Einspruch',ic:'\u{1F4F8}',sub:'Gegen Bu\u00dfgeld',price:3.99,
    qs:[{k:'datum',t:'date',r:1,l:'Datum des Bu\u00dfgeldbescheids'},{k:'beh',t:'text',r:1,l:'Ausstellende Beh\u00f6rde'},{k:'az',t:'text',r:0,l:'Aktenzeichen'},{k:'verstos',t:'text',r:1,l:'Vorgeworfener Versto\u00df'},{k:'betrag',t:'text',r:0,l:'Bu\u00dfgeldbetrag (\u20ac)'},{k:'grund',t:'textarea',r:1,l:'Begr\u00fcndung: Was stimmt nicht?'}],
    ns:['\u23F0 WICHTIG: Einspruchsfrist NUR 2 Wochen!','\u2709\uFE0F Per Einschreiben','\u{1F4A1} Foto-Beweise pr\u00fcfen lassen','\u26A0\uFE0F Nach Einspruch: ggf. Gerichtsverhandlung']},
  steuer_an:{n:'Steuererkl\u00e4rung (AN)',ic:'\u{1F9FE}',sub:'Anlage N + Werbungskosten',price:7.99,
    qs:[{k:'jahr',t:'text',r:1,l:'Steuerjahr (z.B. 2023)'},{k:'job',t:'text',r:1,l:'Beruf'},{k:'brutto',t:'text',r:1,l:'Jahresbruttolohn (\u20ac)'},{k:'km',t:'text',r:0,l:'Entfernung Wohnung-Arbeit (km)'},{k:'homeoffice',t:'text',r:0,l:'Homeoffice-Tage'},{k:'arbeitsmittel',t:'text',r:0,l:'Arbeitsmittel (\u20ac)'},{k:'kinder',t:'text',r:0,l:'Kinder (Anzahl + Geburtsjahre)'},{k:'versich',t:'text',r:0,l:'Sonderausgaben (\u20ac)'}],
    ns:['\u{1F4C5} Abgabefrist: 31. Juli','\u{1F4C5} Mit Steuerberater: 28. Februar','\u{1F4B0} Durchschnitt: ca. 1.095 \u20ac/Jahr','\u{1F4BB} ELSTER: elster.de']},
  steuer_selbst:{n:'Steuererkl\u00e4rung (Selbst.)',ic:'\u{1F3E2}',sub:'Anlage S + E\u00dcR + USt',price:9.99,
    qs:[{k:'jahr',t:'text',r:1,l:'Steuerjahr'},{k:'art',t:'text',r:1,l:'Art der T\u00e4tigkeit'},{k:'einnahmen',t:'text',r:1,l:'Jahreseinnahmen brutto (\u20ac)'},{k:'ausgaben',t:'textarea',r:1,l:'Betriebsausgaben (B\u00fcro, Material, Fahrtkosten...)'},{k:'ust',t:'text',r:1,l:'USt-pflichtig? (Ja/Nein/Kleinunternehmer \u00a719)'}],
    ns:['\u{1F4C5} Mit Steuerberater: 28. Februar','\u{1F4CA} E\u00dcR separat in ELSTER','\u{1F4A1} Kleinunternehmer bis 22.000\u20ac (\u00a719 UStG)','\u26A0\uFE0F Gewerbesteuer ab 24.500\u20ac Gewinn']},
  einspruch_steuer:{n:'Einspruch Steuerbescheid',ic:'\u{1F9FE}',sub:'Gegen Finanzamt',price:4.99,
    qs:[{k:'datum',t:'date',r:1,l:'Datum des Steuerbescheids'},{k:'art',t:'text',r:1,l:'Steuerart (z.B. Einkommensteuer 2023)'},{k:'az',t:'text',r:0,l:'Steuernummer'},{k:'grund',t:'textarea',r:1,l:'Was ist falsch? Begr\u00fcndung'}],
    ns:['\u23F0 Einspruchsfrist: 1 Monat exakt!','\u{1F4CD} An das Finanzamt per Einschreiben','\u{1F4A1} Aussetzung der Vollziehung m\u00f6glich','\u{1F522} Steuernummer immer angeben']},
  kindergeld:{n:'Kindergeld KG1',ic:'\u{1F476}',sub:'Familienkasse',price:0,
    qs:[{k:'kind',t:'text',r:1,l:'Name des Kindes'},{k:'kgeb',t:'date',r:1,l:'Geburtsdatum Kind'},{k:'iban',t:'text',r:1,l:'IBAN f\u00fcr Auszahlung'}],
    ns:['\u{1F4CD} An die Familienkasse','\u{1F4CB} Geburtsurkunde beilegen','\u23F0 R\u00fcckwirkend bis 6 Monate','\u{1F4B6} 2024: 250\u20ac/Monat pro Kind']},
  buergergeld:{n:'B\u00fcrgergeld',ic:'\u{1F4BC}',sub:'Beim Jobcenter',price:0,
    qs:[{k:'pers',t:'text',r:1,l:'Anzahl Personen in der Bedarfsgemeinschaft'},{k:'miete',t:'text',r:1,l:'Kaltmiete (\u20ac/Monat)'},{k:'nk',t:'text',r:0,l:'Nebenkosten (\u20ac/Monat)'},{k:'eink',t:'text',r:1,l:'Monatliches Einkommen (\u20ac, 0 wenn keines)'}],
    ns:['\u{1F4CD} Pers\u00f6nlich beim Jobcenter','\u{1F4CB} Kontoausz\u00fcge 3 Monate + Ausweis','\u23F0 Antrag gilt ab Antragsdatum','\u{1F4B6} 2024: 563\u20ac/Monat (Alleinstehende)']},
  // ── EINWANDERUNG ─────────────────────────────────
  duldung_verlaengerung:{n:'Duldung Verlängerung',ic:'📅',sub:'§60a AufenthG',price:3.99,
    qs:[{k:'ablauf',t:'date',r:1,l:'Ablaufdatum der aktuellen Duldung'},{k:'grund',t:'textarea',r:1,l:'Grund der Duldung (z.B. laufendes Verfahren, Krankheit)'},{k:'verfahren',t:'text',r:0,l:'Laufendes Gerichtsverfahren? (Az., Gericht)'},{k:'beh',t:'text',r:1,l:'Zuständige Ausländerbehörde'},{k:'kinder',t:'text',r:0,l:'Minderjährige Kinder in Deutschland?'},{k:'arbeit',t:'text',r:0,l:'Beschäftigung oder Ausbildung?'}],
    ns:['⏰ Antrag mind. 6 Wochen vor Ablauf stellen','📍 An die zuständige ABH persönlich oder Einschreiben','📋 Alle Nachweise beilegen (Verfahren, Schule, Arbeit)','⚖️ Bei Ablehnung: Eilantrag VG möglich']},
  niederlassungserlaubnis:{n:'Niederlassungserlaubnis §9',ic:'⭐',sub:'Unbefristeter Aufenthalt',price:4.99,
    qs:[{k:'seit',t:'text',r:1,l:'In Deutschland seit (Jahr)'},{k:'aufenthalt_art',t:'text',r:1,l:'Aktuelle Aufenthaltserlaubnis (Art + §)'},{k:'arbeit',t:'text',r:1,l:'Beschäftigung / Einkommen (ca. €/Monat)'},{k:'deutsch',t:'text',r:1,l:'Deutschkenntnisse (B1/B2/C1?)'},{k:'rente',t:'text',r:0,l:'Rentenversicherungsjahre eingezahlt?'},{k:'vorstrafen',t:'text',r:1,l:'Vorstrafen? (Ja/Nein)'}],
    ns:['📋 Erforderlich: 5 Jahre Aufenthalt, B1 Deutsch, Lebensunterhalt gesichert','💶 Gebühr: ca. 113 € bei der ABH','🔍 DRV-Kontoauszug anfordern für Rentennachweis','⚖️ Bei Ablehnung: Widerspruch 1 Monat']},
  familiennachzug:{n:'Familiennachzug',ic:'👨‍👩‍👧',sub:'§28/§29/§30/§32 AufenthG',price:4.99,
    qs:[{k:'beziehung',t:'text',r:1,l:'Beziehung zum Stammberechtigten (Ehegatte/Kind/Elternteil)'},{k:'stamm',t:'text',r:1,l:'Stammberechtigter: Aufenthaltsstatus, seit wann in DE'},{k:'nachziehend',t:'text',r:1,l:'Nachziehende Person: Nationalität, Wohnort aktuell'},{k:'deutsch',t:'text',r:0,l:'Deutschkenntnisse (A1 erforderlich für Ehegatten aus Nicht-EU)'},{k:'unterhalt',t:'text',r:0,l:'Lebensunterhalt gesichert? (€/Monat)'},{k:'wohnraum',t:'text',r:0,l:'Ausreichender Wohnraum vorhanden?'}],
    ns:['🇩🇪 Antrag bei der deutschen Botschaft im Heimatland','🇬🇧 Ehegatten: A1-Deutschkenntnisse vor Einreise (außer EU/EWR)','👶 Minderjährige Kinder: vereinfachtes Verfahren','⏰ Wartezeiten: 6-18 Monate — früh beantragen!']},
  aufenthaltserlaubnis_verlaengerung:{n:'Aufenthaltserlaubnis verlängern',ic:'🔁',sub:'Verlängerungsantrag ABH',price:3.99,
    qs:[{k:'ablauf',t:'date',r:1,l:'Ablaufdatum der aktuellen AE'},{k:'paragraph',t:'text',r:1,l:'Aktueller § der Aufenthaltserlaubnis (z.B. §28, §30)'},{k:'beh',t:'text',r:1,l:'Zuständige Ausländerbehörde'},{k:'grund',t:'textarea',r:1,l:'Grund der Verlängerung / Änderungen'},{k:'arbeit',t:'text',r:0,l:'Aktuelle Beschäftigung / Ausbildung'},{k:'einkommen',t:'text',r:0,l:'Monatliches Nettoeinkommen (€)'}],
    ns:['⏰ Mind. 3 Monate vor Ablauf beantragen!','🎫 Fiktionsbescheinigung beantragen falls AE vor Termin abläuft','💶 Gebühr: 96-113 € je nach Status','📋 Mitbringen: Reisepass, AE, Meldebescheinigung, Einkommensnachweis']},
  abschiebungsschutz:{n:'Abschiebungsschutz §60',ic:'🛡️',sub:'Antrag auf Duldung',price:3.99,
    qs:[{k:'grund',t:'textarea',r:1,l:'Abschiebungshindernis: Was würde im Heimatland passieren?'},{k:'belege',t:'text',r:0,l:'Vorhandene Belege (Arztberichte, Länderberichte)'},{k:'kinder',t:'text',r:0,l:'Minderjährige Kinder in Deutschland?'},{k:'aufenthalt',t:'text',r:0,l:'Wie lange in Deutschland?'}],
    ns:['⚠️ Bei drohendem Abschiebetermin: Eilantrag VG sofort!','🏥 Gesundheitliche Gründe: Ärztliches Attest unverzichtbar','⚖️ §60 Abs. 5 + 7 AufenthG: humanitäre Gründe','🤝 Beratung: Caritas, AWO, Flüchtlingsrat']},
  einbuergerung_antrag:{n:'Einbürgerungsantrag §10',ic:'🇩🇪',sub:'Deutsche Staatsangehörigkeit',price:4.99,
    qs:[{k:'seit',t:'text',r:1,l:'Rechtmäßiger Aufenthalt in Deutschland seit (Jahr)'},{k:'aufenthalt_art',t:'text',r:1,l:'Aktuelle Aufenthaltserlaubnis (§)'},{k:'deutsch',t:'text',r:1,l:'Deutschkenntnisse (mind. B1)'},{k:'arbeit',t:'text',r:1,l:'Erwerbstätigkeit / Einkommensnachweis'},{k:'vorstrafen',t:'text',r:1,l:'Vorstrafen? (Ja/Nein, Art)'},{k:'staatsang',t:'text',r:0,l:'Bisherige Staatsangehörigkeit(en) — Aufgabe erforderlich?'}],
    ns:['📋 Voraussetzung §10: 5 Jahre + Selbstständiger Unterhalt + B1 + keine Vorstrafen','⏰ Seit 2024: Doppelte Staatsangehörigkeit erlaubt!','💶 Gebühr: 255 € (51 € für Kinder)','📍 Antrag bei der Einbürgerungsbehörde des Wohnorts']},
  reiseausweis_auslaender:{n:'Reiseausweis für Ausländer',ic:'📔',sub:'§5 AufenthV',price:2.99,
    qs:[{k:'grund',t:'text',r:1,l:'Warum kein Heimatpass? (Heimatstaat verweigert, Schutzstatus)'},{k:'reisezweck',t:'text',r:0,l:'Geplante Reise / Ziel'},{k:'beh',t:'text',r:1,l:'Zuständige Ausländerbehörde'}],
    ns:['📍 Persönlich bei der ABH beantragen','⏰ Bearbeitungszeit: 4-8 Wochen','💶 Gebühr: ca. 38 €','🌍 Nicht alle Länder akzeptieren diesen Reiseausweis']},
  // ── GESUNDHEIT ────────────────────────────────────
  krankenkasse_widerspruch:{n:'Krankenkasse Widerspruch',ic:'🏥',sub:'Gegen Leistungsablehnung',price:3.99,
    qs:[{k:'datum',t:'date',r:1,l:'Datum des Ablehnungsbescheids'},{k:'kk',t:'text',r:1,l:'Name der Krankenkasse'},{k:'az',t:'text',r:0,l:'Aktenzeichen'},{k:'leistung',t:'text',r:1,l:'Abgelehnte Leistung (z.B. Reha, Hilfsmittel, Behandlung)'},{k:'begruendung',t:'textarea',r:1,l:'Warum ist die Ablehnung falsch? (Diagnose, ärztliche Begründung)'},{k:'arzt',t:'text',r:0,l:'Behandelnder Arzt (Name, Fachrichtung)'}],
    ns:['⏰ Widerspruchsfrist: 1 Monat ab Bescheid','🏥 Ärztliches Attest / Facharztgutachten beilegen','📍 Per Einschreiben an die Krankenkasse','⚖️ Nächster Schritt: Sozialgericht (kostenfrei!)']},
  pflegegrad_widerspruch:{n:'Pflegegrad Widerspruch',ic:'🩺',sub:'Gegen MDK-Bescheid',price:3.99,
    qs:[{k:'datum',t:'date',r:1,l:'Datum des Bescheids'},{k:'kk',t:'text',r:1,l:'Pflegekasse'},{k:'aktuell',t:'text',r:1,l:'Festgestellter Pflegegrad (1-5)'},{k:'beantragt',t:'text',r:1,l:'Beantragter Pflegegrad'},{k:'begruendung',t:'textarea',r:1,l:'Was kann die Person nicht mehr selbst? (Waschen, Anziehen, Kochen)'},{k:'diagnosen',t:'text',r:0,l:'Diagnosen / Erkrankungen'}],
    ns:['⏰ Widerspruchsfrist: 1 Monat','📋 Pflegetagebuch führen — täglich dokumentieren!','🏥 Ärztliche Stellungnahme beifügen','🤝 Pflegestützpunkt kostenlos nutzen']},
  reha_antrag:{n:'Reha-Antrag',ic:'🩼',sub:'Medizinische Rehabilitation',price:3.99,
    qs:[{k:'diagnose',t:'text',r:1,l:'Hauptdiagnose'},{k:'reha_art',t:'text',r:1,l:'Art der Reha (stationär/ambulant, orthopädisch/kardiologisch)'},{k:'arzt',t:'text',r:0,l:'Verordnender Arzt'},{k:'ziel',t:'textarea',r:1,l:'Reha-Ziel: Was soll erreicht werden?'},{k:'arbeit',t:'text',r:0,l:'Beruf / Arbeitsunfähig seit?'}],
    ns:['📍 Antrag an DRV oder Krankenkasse','⏰ Bearbeitungszeit: 4-12 Wochen','💶 Kostenübernahme: GKV oder DRV','🔗 Formulare: DRV G100 oder KK-Eigenformular']},
  behandlungsfehler:{n:'Behandlungsfehler Anzeige',ic:'⚕️',sub:'Ärztlicher Fehler melden',price:4.99,
    qs:[{k:'arzt',t:'text',r:1,l:'Name des Arztes / Krankenhaus'},{k:'datum',t:'date',r:1,l:'Datum der fehlerhaften Behandlung'},{k:'behandlung',t:'text',r:1,l:'Art der Behandlung / Eingriff'},{k:'fehler',t:'textarea',r:1,l:'Was ist falsch gelaufen? Welcher Schaden ist entstanden?'},{k:'folgen',t:'textarea',r:1,l:'Gesundheitliche / wirtschaftliche Folgen'}],
    ns:['⏰ Verjährungsfrist: 3 Jahre ab Kenntnis','📋 Krankenakte sofort anfordern (§630g BGB)','🔍 Gutachterkommission der Ärztekammer — kostenlose Prüfung','💶 Bei Klage: Rechtsschutzversicherung prüfen']},
  krankengeld_antrag:{n:'Krankengeld Antrag',ic:'💊',sub:'Ab dem 43. Krankheitstag',price:2.99,
    qs:[{k:'au_seit',t:'date',r:1,l:'Arbeitsunfähig seit'},{k:'arbeitgeber',t:'text',r:0,l:'Letzter Arbeitgeber / Gehalt'},{k:'kk',t:'text',r:1,l:'Krankenkasse'}],
    ns:['💶 Krankengeld: 70% Brutto, max. 90% Netto','⏰ Ab 43. Krankheitstag — rechtzeitig beantragen!','📋 Nahtlose AU-Bescheinigungen erforderlich','⏳ Max.: 78 Wochen innerhalb von 3 Jahren']},
  hilfsmittel_antrag:{n:'Hilfsmittel Antrag',ic:'♿',sub:'Rollstuhl, Hörgerät, Prothese',price:2.99,
    qs:[{k:'hilfsmittel',t:'text',r:1,l:'Benötigtes Hilfsmittel'},{k:'diagnose',t:'text',r:1,l:'Diagnose / Grund der Notwendigkeit'},{k:'arzt',t:'text',r:0,l:'Verordnender Arzt'}],
    ns:['📋 Ärztliche Verordnung (Rezept) ist Pflicht','💶 Krankenkasse übernimmt in der Regel','⏰ Bei Ablehnung: Widerspruch 1 Monat','🛒 Sanitätshaus kann Antrag unterstützen']},
  // ── BILDUNG ───────────────────────────────────────
  bafoeg_antrag:{n:'BAföG Antrag',ic:'🎓',sub:'Ausbildungsförderung',price:0,
    qs:[{k:'ausbildung',t:'text',r:1,l:'Schule / Hochschule / Ausbildung'},{k:'beginn',t:'date',r:0,l:'Ausbildungsbeginn'},{k:'wohnsituation',t:'text',r:1,l:'Wohnsituation (bei Eltern / eigene Wohnung)'},{k:'einkommen_eltern',t:'text',r:0,l:'Elterliches Einkommen (ca. €/Jahr)'},{k:'eigenes_eink',t:'text',r:0,l:'Eigenes Einkommen (€/Monat)'}],
    ns:['📍 Antrag beim Studentenwerk / Amt für Ausbildungsförderung','📅 Rückwirkend NUR für aktuellen Monat — sofort beantragen!','💶 Bis zu 992 €/Monat (2024)','🔗 Online: bafoeg-digital.de']},
  bafoeg_widerspruch:{n:'BAföG Widerspruch',ic:'📝',sub:'Gegen Ablehnung oder zu geringen Betrag',price:3.99,
    qs:[{k:'datum',t:'date',r:1,l:'Datum des Bescheids'},{k:'az',t:'text',r:0,l:'Aktenzeichen'},{k:'behoerde',t:'text',r:1,l:'Amt für Ausbildungsförderung'},{k:'grund',t:'textarea',r:1,l:'Warum ist die Ablehnung / der Betrag falsch?'}],
    ns:['⏰ Widerspruchsfrist: 1 Monat','📋 Einkommensnachweise nochmals prüfen','📍 Per Einschreiben','🎓 Studentenwerk berät kostenlos']},
  nachteilsausgleich:{n:'Nachteilsausgleich Antrag',ic:'⚖️',sub:'Prüfung / Schule',price:2.99,
    qs:[{k:'schule',t:'text',r:1,l:'Schule / Universität'},{k:'behinderung',t:'text',r:1,l:'Art der Beeinträchtigung (Diagnose)'},{k:'antrag_ziel',t:'textarea',r:1,l:'Was beantragen Sie? (Zeitverlängerung, PC, separater Raum)'},{k:'attest',t:'text',r:0,l:'Ärztliches Attest vorhanden?'}],
    ns:['📋 Attest von Facharzt erforderlich','📅 Rechtzeitig vor Prüfungssession beantragen','⚖️ Rechtsgrundlage: §65 HRG / Schulrecht des Bundeslandes']},
  pruefungsanfechtung:{n:'Prüfungsanfechtung',ic:'❌',sub:'Klausur / Prüfung anfechten',price:3.99,
    qs:[{k:'pruefung',t:'text',r:1,l:'Welche Prüfung? (Fach, Datum, Prüfer)'},{k:'schule',t:'text',r:1,l:'Hochschule / Schule'},{k:'note',t:'text',r:1,l:'Erhaltene Note / Ergebnis'},{k:'grund',t:'textarea',r:1,l:'Anfechtungsgrund (Verfahrensfehler, inhaltlicher Fehler?)'}],
    ns:['⏰ Frist: meist 1 Monat nach Bekanntgabe','📋 Prüfungsprotokoll anfordern!','📖 Prüfungsordnung genau prüfen','🎓 AStA / Studentenwerk für Erstberatung']},
  // ── GEWERBE ───────────────────────────────────────
  gewerbeanmeldung:{n:'Gewerbeanmeldung',ic:'🏢',sub:'Gewerbe anmelden',price:2.99,
    qs:[{k:'gewerbe_art',t:'text',r:1,l:'Art des Gewerbes (genaue Tätigkeitsbeschreibung)'},{k:'rechtsform',t:'text',r:1,l:'Rechtsform (Einzelunternehmen, GbR, UG, GmbH)'},{k:'beginn',t:'date',r:1,l:'Geplantes Startdatum'},{k:'hauptgewerbe',t:'text',r:1,l:'Hauptgewerbe oder Nebengewerbe?'}],
    ns:['📍 Beim Gewerbeamt der Stadt / Gemeinde','💶 Gebühr: ca. 20-65 € je nach Gemeinde','📋 FA meldet sich automatisch für Steuernummer','🏦 Gewerbesteuer ab 24.500 € Jahresgewinn']},
  gewerbeabmeldung:{n:'Gewerbeabmeldung',ic:'🚪',sub:'Gewerbe abmelden',price:1.99,
    qs:[{k:'gewerbe_art',t:'text',r:1,l:'Art des Gewerbes'},{k:'abmeld_datum',t:'date',r:1,l:'Datum der Betriebsaufgabe'},{k:'grund',t:'text',r:0,l:'Grund der Abmeldung'}],
    ns:['📍 Beim Gewerbeamt — kostenlos oder geringe Gebühr','📋 Finanzamt separat informieren','🧾 Steuer-Erklärung für letztes Jahr nicht vergessen']},
  gbr_vertrag:{n:'GbR Gesellschaftsvertrag',ic:'🤝',sub:'Gesellschaft bürgerlichen Rechts',price:12.99,
    qs:[{k:'gesellschafter',t:'textarea',r:1,l:'Gesellschafter: Name + Adresse + Anteil % (alle)'},{k:'zweck',t:'text',r:1,l:'Gesellschaftszweck (Geschäftsbereich)'},{k:'beginn',t:'date',r:1,l:'Gründungsdatum'},{k:'einlagen',t:'text',r:0,l:'Einlagen der Gesellschafter (€ oder Sacheinlagen)'},{k:'gewinn',t:'text',r:0,l:'Gewinnverteilung (nach Anteil / gleich / anders)'}],
    ns:['⚠️ Alle Gesellschafter haften persönlich und unbeschränkt!','📋 Kein Notartermin nötig — aber schriftlich dringend empfohlen','🏦 GbR kann Steuernummer, Gewerbe und Konto haben','🤝 Ab 2 Personen: GbR startet automatisch ohne Vertrag']},
  stundungsantrag_fa:{n:'Stundungsantrag Finanzamt',ic:'⏳',sub:'Zahlungsaufschub beantragen',price:2.99,
    qs:[{k:'finanzamt',t:'text',r:1,l:'Zuständiges Finanzamt'},{k:'steuerart',t:'text',r:1,l:'Steuerart und Zeitraum (z.B. USt Q1/2024)'},{k:'betrag',t:'text',r:1,l:'Zu stundender Betrag (€)'},{k:'bis',t:'date',r:1,l:'Stundung gewünscht bis'},{k:'grund',t:'textarea',r:1,l:'Begründung: Warum Zahlungsschwierigkeiten? (vorübergehend!)'}],
    ns:['📋 Antrag VOR Fälligkeit stellen!','💶 Stundungszinsen: 1,8% p.a. (§238 AO)','⏰ Gleichzeitig: Vollstreckungsaufschub beantragen','📈 Alternative: Ratenzahlungsvereinbarung']},
  zahlungsplan_fa:{n:'Ratenzahlung Finanzamt',ic:'📈',sub:'Steuerrückstand in Raten',price:2.99,
    qs:[{k:'finanzamt',t:'text',r:1,l:'Zuständiges Finanzamt'},{k:'gesamtbetrag',t:'text',r:1,l:'Gesamtrückstand (€)'},{k:'monatsrate',t:'text',r:1,l:'Mögliche Monatsrate (€)'},{k:'grund',t:'textarea',r:1,l:'Begründung: Finanzielle Situation'}],
    ns:['💶 Ratenzinsen: 0,15% pro Monat (§238 AO)','⏰ Sofort beantragen — Vollstreckung stoppen','📋 Einkommensnachweis / Kontoauszüge beilegen','🔗 Online: ELSTER oder persönlich beim FA']},
  freelancer_vertrag:{n:'Freelancer / Dienstvertrag',ic:'💻',sub:'Für Selbstständige und Auftraggeber',price:7.99,
    qs:[{k:'auftraggeber',t:'text',r:1,l:'Auftraggeber (Firma + Adresse)'},{k:'leistung',t:'textarea',r:1,l:'Genaue Leistungsbeschreibung'},{k:'honorar',t:'text',r:1,l:'Honorar (€/Stunde oder Pauschal?)'},{k:'laufzeit',t:'text',r:1,l:'Laufzeit / Projektdauer'},{k:'zahlungsziel',t:'text',r:0,l:'Zahlungsziel (z.B. 14 Tage nach Rechnung)'},{k:'ip',t:'text',r:0,l:'Wer erhält Urheberrechte / Arbeitsergebnisse?'}],
    ns:['⚠️ Scheinselbstständigkeit vermeiden: Merkmale prüfen!','📋 Kein Weisungsrecht des AG, keine festen Arbeitszeiten','💶 Umsatzsteuer auf Rechnung (außer Kleinunternehmer)','🔍 DRV-Statusfeststellung bei Zweifel']},
  // ── MIETE ERWEITERT ───────────────────────────────
  mieterhöhung_widerspruch:{n:'Mieterhöhung Widerspruch',ic:'📈',sub:'§558 BGB',price:3.99,
    qs:[{k:'datum',t:'date',r:1,l:'Datum der Mieterhöhungserklärung'},{k:'aktuelle_miete',t:'text',r:1,l:'Aktuelle Kaltmiete (€)'},{k:'neue_miete',t:'text',r:1,l:'Geforderte neue Kaltmiete (€)'},{k:'vermieter',t:'text',r:1,l:'Vermieter (Name + Adresse)'},{k:'grund',t:'textarea',r:1,l:'Warum ist die Erhöhung falsch? (über Mietspiegel, falsche Berechnung)'}],
    ns:['⏰ 2 Monate Überlegungsfrist — dann Zustimmung oder Widerspruch','📊 Mietspiegel der Stadt prüfen!','🏠 Max. 20% in 3 Jahren (15% in Gebieten mit Mietpreisbremse)','🤝 Mieterverein berät']},
  nebenkostenabrechnung_widerspruch:{n:'Nebenkosten Widerspruch',ic:'💸',sub:'Gegen fehlerhafte NK-Abrechnung',price:2.99,
    qs:[{k:'zeitraum',t:'text',r:1,l:'Abrechnungszeitraum (z.B. 01.01.-31.12.2023)'},{k:'betrag',t:'text',r:1,l:'Nachforderung / Guthaben (€)'},{k:'fehler',t:'textarea',r:1,l:'Fehler: Unbekannte Positionen, falscher Verteilerschlüssel...'},{k:'vermieter',t:'text',r:1,l:'Vermieter (Name + Adresse)'}],
    ns:['📋 Belegeinsicht verlangen! (§259 BGB)','⏰ Vermieter hat 12 Monate nach Jahresende Zeit','🔍 Umlagefähige Kosten: §2 BetrKV prüfen','🤝 Mieterverein prüft kostenlos']},
  // ── ARBEIT ERWEITERT ──────────────────────────────
  gehalterhoehung:{n:'Gehaltserhöhung Antrag',ic:'💰',sub:'Professionelles Schreiben',price:2.99,
    qs:[{k:'arbeitgeber',t:'text',r:1,l:'Arbeitgeber'},{k:'position',t:'text',r:1,l:'Ihre Position'},{k:'aktuell',t:'text',r:1,l:'Aktuelles Gehalt (€ brutto/Monat)'},{k:'wunsch',t:'text',r:1,l:'Gewünschtes Gehalt (€)'},{k:'begruendung',t:'textarea',r:1,l:'Begründung: Leistungen, Erfolge, Marktvergleich'},{k:'seit',t:'text',r:0,l:'Im Unternehmen seit (Jahre)'}],
    ns:['📅 Bestes Timing: nach erfolgreichem Projekt oder Jahresgespräch','🔍 Gehaltsvergleich: stepstone.de, gehaltskompass.de','📝 Schriftlich einreichen — professioneller als mündlich','💶 Realistisch: 5-15% Erhöhung']},
  homeoffice_antrag:{n:'Homeoffice Antrag',ic:'💻',sub:'Antrag auf mobiles Arbeiten',price:2.99,
    qs:[{k:'arbeitgeber',t:'text',r:1,l:'Arbeitgeber'},{k:'position',t:'text',r:1,l:'Ihre Position'},{k:'tage',t:'text',r:1,l:'Gewünschte HO-Tage pro Woche'},{k:'begruendung',t:'textarea',r:1,l:'Begründung (Pendelzeit, Konzentration, Familie)'},{k:'ab',t:'date',r:0,l:'Gewünschtes Startdatum'}],
    ns:['💻 Kein gesetzlicher Anspruch auf HO — aber viele Arbeitgeber genehmigen','🤝 Betriebsvereinbarung prüfen','⏰ Probemonat vorschlagen = leichter durchzusetzen']},
  // ── VERBRAUCHER ERWEITERT ─────────────────────────
  schufa_widerspruch:{n:'Schufa Widerspruch',ic:'📊',sub:'Falscheintrag löschen',price:2.99,
    qs:[{k:'eintrag',t:'textarea',r:1,l:'Was steht in der Schufa? (Welcher Eintrag ist falsch/veraltet?)'},{k:'glaeubiger',t:'text',r:1,l:'Wer hat den Eintrag gemeldet? (Bank, Inkasso...)'},{k:'datum',t:'text',r:0,l:'Datum des Eintrags'},{k:'beweis',t:'text',r:0,l:'Belege vorhanden, dass Eintrag falsch ist?'}],
    ns:['📋 Kostenlose Schufa-Auskunft: schufa.de/meineschufa','⏰ Einträge verfallen nach 3 Jahren nach Begleichung','📍 Widerspruch: SCHUFA Holding AG, Postfach 10 25 66, 44725 Bochum','🔗 Verbraucherberatung: verbraucherzentrale.de']},
  energievertrag_kuendigung:{n:'Energie / Internet Kündigung',ic:'⚡',sub:'Strom, Gas, DSL, Handy',price:1.99,
    qs:[{k:'anbieter',t:'text',r:1,l:'Anbieter (z.B. E.ON, Vodafone, Telekom)'},{k:'vertrag_art',t:'text',r:1,l:'Vertragsart (Strom / Gas / Internet / Handy)'},{k:'kundennr',t:'text',r:0,l:'Kundennummer'},{k:'kuend_datum',t:'date',r:0,l:'Zum nächstmöglichen Termin oder bestimmtes Datum'},{k:'grund',t:'text',r:0,l:'Grund (Umzug, Wechsel, Preiserhöhung — bei Sonderkündigung wichtig!)'}],
    ns:['📋 Immer per Einschreiben!','📧 Manche Anbieter akzeptieren E-Mail (Bestätigung aufheben)','⏰ Sonderkündigungsrecht bei Preiserhöhung: 6 Wochen','💶 Vergleich nach Kündigung: verivox.de, check24.de']},
  reklamation:{n:'Reklamation / Mängelrüge',ic:'📦',sub:'Defektes Produkt zurückgeben',price:1.99,
    qs:[{k:'haendler',t:'text',r:1,l:'Händler / Hersteller (Name + Adresse)'},{k:'produkt',t:'text',r:1,l:'Produkt (Bezeichnung, Modell, Seriennummer)'},{k:'kauf_datum',t:'date',r:1,l:'Kaufdatum'},{k:'mangel',t:'textarea',r:1,l:'Mangel: Was ist kaputt / falsch?'},{k:'wunsch',t:'text',r:1,l:'Reparatur / Ersatz / Rückerstattung?'}],
    ns:['💶 Gesetzliche Gewährleistung: 2 Jahre (§438 BGB)','⏰ Erste 6 Monate: Mangel gilt als von Anfang an vorhanden','📋 Kaufbeleg + Fotos des Mangels aufbewahren','📦 Produkt NUR mit schriftlicher Genehmigung einsenden']},
  // ── AUTO ERWEITERT ────────────────────────────────
  unfallbericht:{n:'Unfallbericht',ic:'🚗',sub:'Für Versicherung & Polizei',price:2.99,
    qs:[{k:'datum',t:'date',r:1,l:'Datum und Uhrzeit des Unfalls'},{k:'ort',t:'text',r:1,l:'Unfallort (genaue Adresse)'},{k:'beteiligte',t:'textarea',r:1,l:'Beteiligte Fahrzeuge (Kennzeichen, Fahrer, Versicherung)'},{k:'hergang',t:'textarea',r:1,l:'Unfallhergang: Wer kam von wo, wie ist es passiert?'},{k:'zeugen',t:'text',r:0,l:'Zeugen (Namen + Kontakt)'},{k:'verletzt',t:'text',r:1,l:'Verletzte Personen? (Ja/Nein)'}],
    ns:['📸 Fotos vor Ort: Kennzeichen, Schäden, Spuren','📋 Europäischer Unfallbericht verwenden','📞 Polizei rufen bei Verletzten oder hohem Schaden','⏰ Versicherung innerhalb 1 Woche informieren']},
  versicherungsschaden:{n:'Versicherungsschaden Meldung',ic:'📋',sub:'Schaden bei Versicherung melden',price:1.99,
    qs:[{k:'versicherung',t:'text',r:1,l:'Versicherungsgesellschaft'},{k:'vertragsnr',t:'text',r:0,l:'Vertragsnummer / Police'},{k:'schaden_datum',t:'date',r:1,l:'Datum des Schadens'},{k:'schaden_art',t:'text',r:1,l:'Art des Schadens (Unfall, Einbruch, Wasserschaden...)'},{k:'schaden_hoehe',t:'text',r:0,l:'Geschätzter Schaden (€)'},{k:'beschreibung',t:'textarea',r:1,l:'Detaillierte Beschreibung'}],
    ns:['⏰ Schadensmeldung sofort — Versicherung hat Fristen!','📸 Alle Schäden fotografieren bevor aufgeräumt wird','📋 Reparaturen NUR nach Genehmigung','📝 Kostenvoranschlag einholen']},
  hausfriedensbruch:{n:'Strafanzeige Hausfriedensbruch',ic:'🚪',sub:'§123 StGB',price:2.99,
    qs:[{k:'datum',t:'date',r:1,l:'Datum des Vorfalls'},{k:'ort',t:'text',r:1,l:'Ort (Ihre Adresse)'},{k:'taeter',t:'text',r:0,l:'Täter (falls bekannt)'},{k:'hergang',t:'textarea',r:1,l:'Was ist passiert? (Eindringen, Verweilen nach Aufforderung...)'},{k:'zeugen',t:'text',r:0,l:'Zeugen'}],
    ns:['📍 Antragsdelikt: Strafantrag innerhalb 3 Monate','📞 Polizei rufen wenn Person noch vor Ort','📸 Kamera-Footage sichern','⚖️ Zivilrechtlich: Unterlassungsklage zusätzlich möglich']},
  sachbeschaedigung:{n:'Strafanzeige Sachbeschädigung',ic:'🔨',sub:'§303 StGB',price:2.99,
    qs:[{k:'datum',t:'date',r:1,l:'Datum der Sachbeschädigung'},{k:'ort',t:'text',r:1,l:'Ort'},{k:'gegenstand',t:'text',r:1,l:'Was wurde beschädigt? (Art, Wert €)'},{k:'taeter',t:'text',r:0,l:'Täter (falls bekannt)'},{k:'hergang',t:'textarea',r:1,l:'Wie ist es passiert?'}],
    ns:['⏰ Strafantrag innerhalb 3 Monate','📸 Schäden sofort fotografieren — vor Reparatur!','💶 Parallel: Versicherungsschaden melden','🧾 Kostenvoranschlag für Reparatur aufheben']},

  _default:{n:'Rechtsdokument',ic:'\u{1F4C4}',sub:'Allgemeines Schreiben',price:2.99,
    qs:[{k:'anliegen',t:'textarea',r:1,l:'Ihr Anliegen (m\u00f6glichst genau)'},{k:'empf',t:'text',r:0,l:'An wen? (Person/Beh\u00f6rde)'},{k:'ziel',t:'text',r:0,l:'Was ist Ihr Ziel?'}],
    ns:['\u{1F4CD} Zust\u00e4ndige Stelle','\u{1F4CB} Kopie aufbewahren','\u2709\uFE0F Per Einschreiben empfohlen']},
};
const CATS={
  behoerde:{n:'Beh\u00f6rde & Amt',ic:'\u{1F3DB}\uFE0F',docs:['widerspruch','aufenthaltserlaubnis','einbuergerung','akteneinsicht']},
  steuern:{n:'Steuern',ic:'\u{1F9FE}',docs:['steuer_an','steuer_selbst','einspruch_steuer','steuer_anlage_v']},
  miete:{n:'Miete & Wohnen',ic:'\u{1F3E0}',docs:['mietvertrag','kuendigung_mieter','kuendigung_vermieter','kuendigung_widerspruch','maengelanzeige','mietminderung','kaution']},
  arbeit:{n:'Arbeit & Beruf',ic:'\u{1F4BC}',docs:['arbeitsvertrag','arbeit_kuendigung','arbeit_kuendigung_widerspruch','abmahnung_widerspruch','zeugnis','aufhebungsvertrag']},
  familie:{n:'Familie & Vorsorge',ic:'\u{1F46A}',docs:['testament','vorsorgevollmacht','vollmacht','unterhalt','kindergeld','elterngeld']},
  strafrecht:{n:'Strafrecht',ic:'\u{1F6A8}',docs:['strafanzeige','strafanzeige_beleidigung','strafanzeige_betrug','stalking','koerperverletzung']},
  auto:{n:'Auto & Verkehr',ic:'\u{1F697}',docs:['kfz_kaufvertrag','kfz_kuendigung','unfallbericht','bussgeldbescheid']},
  verbraucher:{n:'Verbraucher',ic:'\u{1F6D2}',docs:['widerruf','mahnschreiben','fluggastrechte','inkasso','abo_kuendigung']},
};
const OFF={kindergeld:'https://www.arbeitsagentur.de/datei/kg1-antrag_ba013559.pdf',elterngeld:'https://www.elterngeld.net/fileadmin/Downloads/Formulare/antrag_elterngeld.pdf',buergergeld:'https://www.arbeitsagentur.de/datei/arbeitslosengeld-ii-alg-ii_ba014020.pdf'};
const LP={tr:/\b(ben|bir|ve|i\u00e7in|bu|var|yok|istiyorum|laz\u0131m)\b/i,ar:/[\u0600-\u06FF]/,ru:/[\u0400-\u04FF]/,fr:/\b(je|vous|pour|les|que|est|bonjour)\b/i,en:/\b(i|you|the|and|for|have|want)\b/i};

let CC='DE',UL='de',P={},CD=null,busy=false,plan='free',dLang='de';
try{const pr=JSON.parse(localStorage.getItem(PK)||'{}');CC=pr.cc||'DE';UL=pr.lang||'de';plan=pr.plan||'free';P=JSON.parse(localStorage.getItem(FK)||'{}');}catch(e){}

function applyUI(){
  const u=UI_TEXTS[UL]||UI_TEXTS.de;
  ['wlc-h','wlc-p','wc1','wc2','wc3','wc4'].forEach((id,i)=>{
    const el=document.getElementById(id);
    if(el) el.textContent=[u.h,u.p,u.c1,u.c2,u.c3,u.c4][i];
  });
  const inp=document.getElementById('inp');
  if(inp) inp.placeholder=u.ph||'Schreiben Sie Ihr Anliegen\u2026';
  document.body.dir=UL==='ar'?'rtl':'ltr';
  document.querySelectorAll('.lang-opt').forEach(b=>b.classList.toggle('on',b.dataset.l===UL));
}
function applyCC(){
  const c=CC_DATA[CC]||CC_DATA.DE;
  const el=id=>document.getElementById(id);
  if(el('sb-cflag')) el('sb-cflag').textContent=c.f;
  if(el('sb-cname')) el('sb-cname').textContent=c.n;
  if(el('sb-clang')) el('sb-clang').textContent=c.l.toUpperCase()+' \u00b7 '+c.law;
  if(el('tb-flag')) el('tb-flag').textContent=c.f;
  if(el('k-cc')) el('k-cc').textContent=c.f+' '+c.n;
  document.querySelectorAll('.co').forEach(el=>el.classList.toggle('on',el.dataset.cc===CC));
}
function setCountry(cc){CC=cc;const c=CC_DATA[cc]||CC_DATA.DE;if(!localStorage.getItem('ch_lm')){UL=c.l||'de';}savePref();applyUI();applyCC();addMsg('ai','\u2705 Land: '+c.f+' '+c.n);}
function setLang(l){UL=l;localStorage.setItem('ch_lm','1');savePref();applyUI();addMsg('ai','✅ Sprache geändert: '+l.toUpperCase());setTimeout(()=>gP('chat'),300);}
function savePref(){localStorage.setItem(PK,JSON.stringify({cc:CC,lang:UL,plan}));}

async function detectLoc(){
  if(localStorage.getItem('ch_ldi')){applyUI();applyCC();loadRecentDocs();return;}
  try{
    const r=await fetch(API+'?action=detect_location');const d=await r.json();
    if(d.country){CC=d.country;if(!localStorage.getItem('ch_lm'))UL=d.lang||'de';localStorage.setItem('ch_ldi','1');savePref();}
  }catch(e){}
  applyUI();applyCC();loadRecentDocs();
}

function detL(t){if(!t||t.length<3)return UL;if(LP.ar.test(t))return'ar';if(LP.ru.test(t))return'ru';if(LP.tr.test(t))return'tr';if(LP.fr.test(t))return'fr';if(LP.en.test(t))return'en';return'de';}
function setFlag(l){dLang=l;['de','tr','ar','ru','en','fr'].forEach(x=>{document.getElementById('fl-'+x)?.classList.toggle('on',x===l);document.getElementById('lf-'+x)?.classList.toggle('on',x===l);});}
function oSB(){document.getElementById('sb').classList.add('open');document.getElementById('ov').classList.add('show');}
function cSB(){document.getElementById('sb').classList.remove('open');document.getElementById('ov').classList.remove('show');}
function oPM(){document.getElementById('pm').style.display='flex';}
function cPM(){document.getElementById('pm').style.display='none';}
function sPlan(p){plan=p;const t={single:'Einzeln',monthly:'Pro Monat',yearly:'Pro Jahr'};const n=t[p]||'Free';document.getElementById('plan-tier').textContent=n;document.getElementById('k-plan-name').textContent=n;savePref();cPM();addMsg('ai','\u2705 Tarif aktiviert!');}
function gP(n){
  document.querySelectorAll('.sn').forEach(x=>x.classList.remove('on'));
  document.querySelectorAll('.pnl').forEach(x=>x.classList.remove('on'));
  document.getElementById('nav-'+n)?.classList.add('on');
  document.getElementById('pnl-'+n)?.classList.add('on');
  const ia=document.getElementById('ia');if(ia)ia.style.display=n==='chat'?'block':'none';
  if(n==='ant')loadHistory();if(n==='konto')loadKonto();cSB();
}
function onKey(e){if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();doSend();}}
function onInp(el){el.style.height='auto';el.style.height=Math.min(el.scrollHeight,120)+'px';if(el.value.length>3)setFlag(detL(el.value));}
function scrl(){const m=document.getElementById('msgs');setTimeout(()=>m?.scrollTo({top:m.scrollHeight,behavior:'smooth'}),80);}
function sleep(ms){return new Promise(r=>setTimeout(r,ms));}
function saveConv(){
  const mi=document.getElementById('mi');if(!mi)return;
  const msgs=[...mi.querySelectorAll('.msg')].slice(-16).map(m=>({r:m.classList.contains('user')?'u':'a',h:m.querySelector('.bbl')?.innerHTML||''}));
  try{localStorage.setItem(CK,JSON.stringify({msgs,ts:Date.now()}));}catch(e){}
}
function restoreConv(){
  try{
    const s=JSON.parse(localStorage.getItem(CK)||'{}');
    if(!s.msgs||!s.ts||Date.now()-s.ts>86400000)return;
    const mi=document.getElementById('mi');if(!mi||!s.msgs.length)return;
    document.getElementById('wlc')?.remove();
    s.msgs.forEach(m=>{
      const d=document.createElement('div');d.className='msg '+(m.r==='u'?'user':'ai');
      const av=m.r==='u'?(P.f1?P.f1[0].toUpperCase():'U'):'CH';
      d.innerHTML=`<div class="av">${av}</div><div class="mbody"><div class="mname">${m.r==='u'?((UI_TEXTS[UL]||UI_TEXTS.de).you||'Sie'):'Chat-Help'}</div><div class="bbl">${m.h}</div></div>`;
      mi.appendChild(d);
    });scrl();
  }catch(e){}
}
function addMsg(role,content){
  document.getElementById('wlc')?.remove();
  const mi=document.getElementById('mi');const d=document.createElement('div');d.className='msg '+role;
  const u=UI_TEXTS[UL]||UI_TEXTS.de;const av=role==='user'?(P.f1?P.f1[0].toUpperCase():'U'):'CH';
  d.innerHTML=`<div class="av">${av}</div><div class="mbody"><div class="mname">${role==='user'?(u.you||'Sie'):'Chat-Help'}</div><div class="bbl" id="bb${Date.now()}"></div></div>`;
  mi.appendChild(d);const b=d.querySelector('.bbl');
  if(typeof content==='string')b.innerHTML=content.replace(/\n/g,'<br>');else b.appendChild(content);
  scrl();saveConv();return b;
}
function showTyping(){document.getElementById('wlc')?.remove();const mi=document.getElementById('mi');const d=document.createElement('div');d.className='msg ai';d.id='typ';d.innerHTML=`<div class="av">CH</div><div class="mbody"><div class="mname">Chat-Help</div><div class="bbl"><div class="typing"><span></span><span></span><span></span></div></div></div>`;mi.appendChild(d);scrl();}
function remT(){document.getElementById('typ')?.remove();}
function showProc(steps){document.getElementById('wlc')?.remove();const mi=document.getElementById('mi');const d=document.createElement('div');d.className='msg ai';d.id='proc';const h=steps.map((s,i)=>`<div class="ps${i===0?' on':''}" id="ps${i}"><div class="ps-ic">${s.ic}</div>${s.txt}</div>`).join('');d.innerHTML=`<div class="av">CH</div><div class="mbody"><div class="mname">Chat-Help</div><div class="bbl"><div class="pbox"><div class="pbox-hdr">VERARBEITUNG</div>${h}</div></div></div>`;mi.appendChild(d);scrl();}
function updP(n){document.querySelectorAll('#proc .ps').forEach((el,i)=>{el.classList.remove('on','done');if(i<n)el.classList.add('done');else if(i===n)el.classList.add('on');});scrl();}
function remP(){document.getElementById('proc')?.remove();}

document.getElementById('pf').addEventListener('change',async(e)=>{
  const file=e.target.files[0];if(!file)return;e.target.value='';gP('chat');
  const b64=await new Promise(r=>{const fr=new FileReader();fr.onload=()=>r(fr.result.split(',')[1]);fr.readAsDataURL(file);});
  const pv=document.createElement('div');pv.className='photo-prev';
  const img=document.createElement('img');img.src=URL.createObjectURL(file);
  const lbl=document.createElement('div');lbl.className='photo-prev-lbl';lbl.textContent='\u{1F4F7} '+file.name;
  pv.appendChild(img);pv.appendChild(lbl);addMsg('user',pv);cSB();
  busy=true;document.getElementById('sbtn').disabled=true;
  showProc([{ic:'\u{1F441}\uFE0F',txt:'ChatHelp Vision analysiert das Dokument...'},{ic:'\u{1F50D}',txt:'Dokument & Fristen analysieren...'},{ic:'\u{1F4CB}',txt:'Formular vorbereiten...'}]);
  try{
    updP(0);const r=await fetch(API+'?action=analyze_photo',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({b64,mime:file.type})});
    const data=await r.json();updP(1);await sleep(400);updP(2);await sleep(300);remP();
    const rs=data.result||{};const lines=[];
    if(rs.erkannt)lines.push(`<b>\u{1F4CB} Erkannt:</b> ${rs.erkannt}`);
    if(rs.behoerde)lines.push(`<b>\u{1F3DB}\uFE0F Von:</b> ${rs.behoerde}`);
    if(rs.datum)lines.push(`<b>\u{1F4C5} Datum:</b> ${rs.datum}`);
    if(rs.az)lines.push(`<b>\u{1F522} Az.:</b> ${rs.az}`);
    if(rs.betrag)lines.push(`<b>\u{1F4B6} Betrag:</b> ${rs.betrag}`);
    if(rs.frist)lines.push(`<span style="color:var(--rd);font-weight:600"><b>\u23F0 FRIST:</b> ${rs.frist} \u2014 Handlungsbedarf!</span>`);
    if(rs.zusammenfassung)lines.push(`<br><i style="color:var(--t2);font-size:13px">${rs.zusammenfassung}</i>`);
    const box=document.createElement('div');const info=document.createElement('div');
    info.style.cssText='font-size:13.5px;line-height:1.8;margin-bottom:12px';info.innerHTML=lines.join('<br>');box.appendChild(info);
    if(rs.doc&&rs.doc!=='other'&&DOCS[rs.doc]){
      const btn=document.createElement('button');btn.className='btn-g';btn.style.cssText='width:auto;padding:10px 22px';
      btn.innerHTML='\u270D\uFE0F '+DOCS[rs.doc].n+' erstellen \u2192';
      btn.onclick=()=>{btn.disabled=true;CD=rs.doc;if(!P.f1){addMsg('ai','Bitte zuerst Ihre Daten:');showProfileForm(rs.doc);}else showQuestions(rs.doc);};
      box.appendChild(btn);
    }else{const btn=document.createElement('button');btn.className='btn-s';btn.style.cssText='width:auto;padding:9px 15px';btn.textContent='\u{1F4C4} Alle Dokumente';btn.onclick=()=>showAllCats();box.appendChild(btn);}
    addMsg('ai',box);
  }catch(ex){remP();addMsg('ai','\u274C Fehler. Bitte manuell:');showAllCats();}
  busy=false;document.getElementById('sbtn').disabled=false;
});

async function doSend(){
  const inp=document.getElementById('inp');const tx=inp.value.trim();
  if(!tx||busy)return;inp.value='';inp.style.height='auto';
  setFlag(detL(tx));addMsg('user',tx);cSB();
  busy=true;document.getElementById('sbtn').disabled=true;
  showTyping();await sleep(500);remT();
  if(!P.f1){addMsg('ai','Bitte zuerst Ihre pers\u00f6nlichen Daten:');showProfileForm(null);busy=false;document.getElementById('sbtn').disabled=false;return;}
  try{
    const r=await fetch(API+'?action=detect',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({text:tx,lang:dLang,country:CC})});
    const d=await r.json();
    if(d.doc&&d.doc!=='other'&&DOCS[d.doc]){if(d.reply)addMsg('ai',d.reply);CD=d.doc;setTimeout(()=>showQuestions(d.doc),300);}
    else{addMsg('ai',d.reply||'Welches Dokument ben\u00f6tigen Sie?');showAllCats();}
  }catch(e){addMsg('ai','Welches Dokument ben\u00f6tigen Sie?');showAllCats();}
  busy=false;document.getElementById('sbtn').disabled=false;
}
function showCat(k){
  const cat=CATS[k];if(!cat)return;cSB();addMsg('user',cat.n);
  const c=document.createElement('div');
  const p=document.createElement('p');p.style.cssText='font-size:13.5px;color:#d0d0f8;margin-bottom:9px';p.textContent='W\u00e4hlen Sie das Dokument:';c.appendChild(p);
  const list=document.createElement('div');list.className='dlist';
  cat.docs.forEach(dk=>{
    const doc=DOCS[dk];if(!doc)return;const row=document.createElement('div');row.className='drow';
    const pr=doc.price===0?`<span class="dprice dp-free">Kostenlos</span>`:plan==='monthly'||plan==='yearly'?`<span class="dprice dp-abo">Im Abo</span>`:`<span class="dprice dp-pay" style="display:none"></span>`;
    row.innerHTML=`<div class="drow-ic">${doc.ic}</div><div><div class="drow-n">${doc.n}</div><div class="drow-s">${doc.sub}</div></div>${pr}`;
    row.onclick=()=>{document.querySelectorAll('.drow').forEach(x=>x.style.pointerEvents='none');addMsg('user',doc.n);CD=dk;setTimeout(()=>showQuestions(dk),300);};
    list.appendChild(row);
  });c.appendChild(list);addMsg('ai',c);
}
function showAllCats(){
  const g=document.createElement('div');g.className='cgrid';
  Object.entries(CATS).forEach(([k,c])=>{
    const card=document.createElement('div');card.className='ccard';
    card.innerHTML=`<div class="ccard-ic">${c.ic}</div><div class="ccard-n">${c.n}</div>`;
    card.onclick=()=>{document.querySelectorAll('.ccard').forEach(x=>x.style.pointerEvents='none');addMsg('user',c.n);setTimeout(()=>showCat(k),200);};
    g.appendChild(card);
  });addMsg('ai',g);
}
function showQuestions(dk){
  const doc=DOCS[dk]||DOCS._default;const c=CC_DATA[CC]||CC_DATA.DE;
  const box=document.createElement('div');box.className='fbox';
  box.innerHTML=`<div class="fhdr"><div class="fhdr-ic">${doc.ic}</div><div class="fhdr-t">${doc.n}</div><div class="fhdr-cc">${c.f} ${CC}</div></div>`;
  const body=document.createElement('div');body.className='fbody';
  const pb=document.createElement('div');pb.className='prog-bar';const pf=document.createElement('div');pf.className='prog-fill';pf.style.width='10%';pb.appendChild(pf);body.appendChild(pb);
  const ans={};
  (doc.qs||[]).forEach(q=>{
    const w=document.createElement('div');w.className='fi';w.style.marginBottom='11px';
    let inp=q.t==='textarea'?`<textarea data-k="${q.k}" rows="${q.r?3:2}"${q.r?' required':''}></textarea>`:q.t==='date'?`<input type="date" data-k="${q.k}"${q.r?' required':''}>`:`<input type="text" data-k="${q.k}"${q.r?' required':''}">`;
    w.innerHTML=`<label>${q.l}${q.r?' <span style="color:var(--rd)">*</span>':' <span style="color:var(--t4);font-size:9px">(optional)</span>'}</label>${inp}`;
    const ie=w.querySelector('[data-k]');
    if(ie)ie.addEventListener('input',()=>{const filled=[...box.querySelectorAll('[required]')].filter(el=>el.value.trim()).length;const tot=box.querySelectorAll('[required]').length;pf.style.width=Math.max(10,Math.round(filled/Math.max(1,tot)*100))+'%';});
    body.appendChild(w);
  });
  const btn=document.createElement('button');btn.className='btn-g';btn.textContent='\u{1F4C4} Dokument generieren \u2192';
  btn.onclick=async()=>{
    const reqs=[...box.querySelectorAll('[required]')];
    for(const el of reqs){if(!el.value.trim()){el.focus();el.style.borderColor='var(--rd)';setTimeout(()=>el.style.borderColor='',2000);return;}}
    box.querySelectorAll('[data-k]').forEach(el=>ans[el.dataset.k]=el.value);
    btn.disabled=true;btn.textContent='\u23F3 Wird erstellt...';await genDoc(dk,ans);
  };
  body.appendChild(btn);box.appendChild(body);
  addMsg('ai',`Ich erstelle Ihren ${doc.n} f\u00fcr ${c.f} ${c.n}:`);
  addMsg('ai',box);scrl();
}
async function genDoc(dk,ans){
  busy=true;document.getElementById('sbtn').disabled=true;
  const doc=DOCS[dk]||DOCS._default;const c=CC_DATA[CC]||CC_DATA.DE;
  showProc([{ic:'\u{1F510}',txt:`Daten anonymisieren (${c.f} ${c.n})...`},{ic:'\u{1F4DA}',txt:'DeepSeek recherchiert (anonym)...'},{ic:'\u270D\uFE0F',txt:'ChatHelp AI schreibt...'},{ic:'\u{1F512}',txt:'Echte Daten einsetzen...'}]);
  try{
    updP(0);await sleep(200);updP(1);
    const r=await fetch(API+'?action=generate',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({docType:dk,docName:doc.n,answers:ans,profile:P,lang:dLang,country:CC})});
    updP(2);const data=await r.json();updP(3);await sleep(500);remP();
    if(data.error){addMsg('ai','\u274C '+data.error);busy=false;document.getElementById('sbtn').disabled=false;return;}
    const rm={de:'\u2705 Ihr Dokument ist fertig!',tr:'\u2705 Belgeniz haz\u0131r!',ar:'\u2705 \u0645\u0633\u062a\u0646\u062f\u0643 \u062c\u0627\u0647\u0632!',ru:'\u2705 \u0413\u043e\u0442\u043e\u0432\u043e!',en:'\u2705 Your document is ready!',fr:'\u2705 Votre document est pr\u00eat!'};
    addMsg('ai',rm[UL]||'\u2705 Fertig!');showResult(data.document,dk,data.research,data.docId);
    if(plan!=='free'&&data.document.length>200){
      setTimeout(async()=>{try{const fu=await fetch(API+'?action=followup',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({docType:dk,docName:doc.n,answers:ans,draft:data.document,country:CC,lang:dLang})});const fd=await fu.json();if(fd.needs_more&&fd.questions?.length)showFollowup(dk,fd,ans);}catch(e){}},1500);
    }
  }catch(ex){remP();addMsg('ai','\u274C Fehler: '+ex.message);}
  busy=false;document.getElementById('sbtn').disabled=false;
}
function showFollowup(dk,fd,prevAns){
  const box=document.createElement('div');box.className='fubox';
  box.innerHTML=`<div class="fubox-t">\u{1F50D} Erg\u00e4nzende Fragen</div><div class="fubox-msg">${fd.message}</div>`;
  const ans={...prevAns};
  fd.questions.forEach(q=>{
    const w=document.createElement('div');w.className='fi';w.style.marginBottom='10px';
    let inp=q.type==='textarea'?`<textarea data-k="${q.key}" rows="3"></textarea>`:`<input type="${q.type||'text'}" data-k="${q.key}">`;
    w.innerHTML=`<label>${q.label}${q.required?' <span style="color:var(--rd)">*</span>':''}</label>${inp}`;box.appendChild(w);
  });
  const btn=document.createElement('button');btn.className='btn-g';btn.style.marginTop='12px';btn.textContent='Dokument verbessern \u2192';
  btn.onclick=async()=>{box.querySelectorAll('[data-k]').forEach(el=>ans[el.dataset.k]=el.value);btn.disabled=true;btn.textContent='\u23F3';await genDoc(dk,ans);};
  const skip=document.createElement('button');skip.className='btn-s';skip.style.cssText='margin-top:8px;width:100%';skip.textContent='\u00dcberspringen';skip.onclick=()=>box.remove();
  box.appendChild(btn);box.appendChild(skip);addMsg('ai',box);
}
function makePDF(doc,name,cc){
  const{jsPDF}=window.jspdf;const c=CC_DATA[cc||CC]||CC_DATA.DE;
  const pdf=new jsPDF({orientation:'portrait',unit:'mm',format:'a4'});
  pdf.setFillColor(7,7,14);pdf.rect(0,0,210,19,'F');
  pdf.setTextColor(212,168,74);pdf.setFontSize(12);pdf.setFont('helvetica','bold');pdf.text('ChatHelp',14,12);
  pdf.setTextColor(160,160,190);pdf.setFontSize(7.5);pdf.setFont('helvetica','normal');pdf.text('chat-help.de \u00b7 chat-help.com',14,17);
  pdf.setTextColor(120,120,150);pdf.text(name+' \u00b7 '+c.n,200,12,{align:'right'});pdf.text(new Date().toLocaleDateString('de-DE'),200,17,{align:'right'});
  pdf.setFillColor(246,246,252);pdf.rect(0,19,210,7,'F');pdf.setTextColor(110,110,140);pdf.setFontSize(6.5);
  pdf.text('KI-erstelltes Dokument \u2014 keine Rechtsberatung \u2014 bitte von einem Rechtsanwalt pr\u00fcfen | chat-help.de',105,23.5,{align:'center'});
  pdf.setTextColor(18,18,28);pdf.setFontSize(10);pdf.setFont('helvetica','normal');
  const lines=pdf.splitTextToSize(doc,183);let y=33;
  lines.forEach(l=>{if(y>277){pdf.addPage();y=16;}pdf.text(l,13,y);y+=5.5;});
  const pc=pdf.internal.getNumberOfPages();
  for(let pg=1;pg<=pc;pg++){pdf.setPage(pg);pdf.setDrawColor(200,200,220);pdf.line(13,284,197,284);pdf.setFontSize(7);pdf.setTextColor(130,130,160);pdf.text('Chat-Help \u2014 chat-help.de | Kein Rechtsrat | DSGVO | EU-Server | '+c.n,105,289,{align:'center'});pdf.text(`${pg}/${pc}`,197,289,{align:'right'});}
  pdf.save('ChatHelp-'+name.replace(/[\s\/\\:*?"<>|]/g,'-')+'-'+CC+'.pdf');
}
function showResult(doc,dk,research,docId){
  const d=DOCS[dk]||DOCS._default;const c=CC_DATA[CC]||CC_DATA.DE;
  const box=document.createElement('div');box.className='rbox';
  const hdr=document.createElement('div');hdr.className='rhdr';
  hdr.innerHTML=`<div><div class="rtitle">${d.n}</div><div class="rcc">${c.f} ${c.n}</div></div>`;
  const ac=document.createElement('div');ac.className='racts';
  const cp=document.createElement('button');cp.className='act';cp.innerHTML='<svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>Kopieren';cp.onclick=()=>navigator.clipboard.writeText(doc).then(()=>{cp.innerHTML='<svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>\u2705 Kopiert';setTimeout(()=>{cp.innerHTML='<svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>Kopieren';},2000);});
  const em=document.createElement('button');em.className='act';em.innerHTML='<svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>E-Mail';em.onclick=()=>{const e=P.f7||prompt('E-Mail:');if(e)window.open('mailto:'+e+'?subject='+encodeURIComponent('Ihr Dokument')+'&body='+encodeURIComponent(doc));};
  const pd=document.createElement('button');pd.className='act gold';pd.innerHTML='<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="12" y1="18" x2="12" y2="12"/><polyline points="9,15 12,18 15,15"/></svg>PDF';pd.onclick=()=>makePDF(doc,d.n,CC);
  ac.appendChild(cp);ac.appendChild(em);ac.appendChild(pd);hdr.appendChild(ac);
  const cnt=document.createElement('div');cnt.className='rcnt';
  const pre=document.createElement('pre');pre.className='rpre';const pid='rp'+Date.now();pre.id=pid;cnt.appendChild(pre);
  if(research){const rb=document.createElement('div');rb.className='research-box';rb.innerHTML='<div class="research-t">\u{1F4DA} Aktuelle Rechtslage</div>';research.split('\n').filter(l=>l.trim()).slice(0,5).forEach(line=>{const el=document.createElement('div');el.className='research-item';el.textContent=line.replace(/^[-\u2022*]\s*/,'');rb.appendChild(el);});cnt.appendChild(rb);}
  const qual=document.createElement('div');qual.className='rqual';qual.innerHTML='\u{1F512} Pers\u00f6nliche Daten nur auf Ihrem Server \00b7 ChatHelp AI \u00b7 '+c.f+' '+c.law+' \u00b7 DSGVO';
  const ns=document.createElement('div');ns.className='rns';ns.innerHTML='<div class="rns-t">N\u00e4chste Schritte</div>';
  (d.ns||[]).forEach(s=>{const el=document.createElement('div');el.className='rns-item';el.textContent=s;ns.appendChild(el);});
  box.appendChild(hdr);box.appendChild(cnt);box.appendChild(qual);box.appendChild(ns);
  if(OFF[dk]){const ob=document.createElement('div');ob.className='offbox';ob.innerHTML=`<div class="offbox-t">\u{1F4CB} Offizielles Formular verf\u00fcgbar</div><div class="offbox-d">Zus\u00e4tzlich das offizielle Formular herunterladen.</div><a href="${OFF[dk]}" target="_blank" class="offbox-a">\u2B07\uFE0F Offizielles Formular</a>`;box.appendChild(ob);}
  if(docId){const kr=document.createElement('div');kr.className='keep-row';kr.innerHTML='<span>\u23F0 Wird in 24h gel\u00f6scht \u2014 oder:</span>';const kb=document.createElement('button');kb.className='act';kb.innerHTML='<svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17,21 17,13 7,13 7,21"/></svg>Behalten';kb.onclick=async()=>{const r=await fetch(API+'?action=keepdoc',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({docId,keep:true})});const dt=await r.json();if(dt.success){kb.innerHTML='\u2705 Dauerhaft';kb.disabled=true;kb.className='act kept';}};kr.appendChild(kb);box.appendChild(kr);}
  // Payment prompt (show after free use)
  if(plan==='free'&&d.price>0){
    const payRow=document.createElement('div');
    payRow.style.cssText='padding:14px 16px;border-top:1px solid var(--bdr);background:linear-gradient(135deg,rgba(212,168,74,.06),rgba(212,168,74,.02))';
    payRow.innerHTML=`<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
      <div><div style="font-size:13px;font-weight:600;color:#fff;margin-bottom:2px">\u{1F4C4} Dokument entsperren</div><div style="font-size:11.5px;color:var(--t3)">PDF herunterladen &amp; dauerhaft speichern</div></div>
      <div style="display:flex;gap:8px;align-items:center">
        <span style="font-size:18px;font-weight:800;color:var(--gold)">${d.price.toFixed(2).replace('.',',')}\u20ac</span>
        <button class="btn-g" style="width:auto;padding:8px 18px;font-size:13px" onclick="oPM()">Jetzt kaufen</button>
      </div>
    </div>`;
    box.appendChild(payRow);
  }
  const chips=document.createElement('div');chips.className='chips';
  const c1=document.createElement('button');c1.className='chip';c1.textContent='\u270F\uFE0F Neues Dokument';c1.onclick=()=>NC();
  const c2=document.createElement('button');c2.className='chip';c2.textContent='\u{1F4C2} Meine Antr\u00e4ge';c2.onclick=()=>gP('ant');
  const c3=document.createElement('button');c3.className='chip';c3.textContent='\u{1F504} \u00dcberarbeiten';c3.onclick=()=>showQuestions(dk);
  chips.appendChild(c1);chips.appendChild(c2);chips.appendChild(c3);box.appendChild(chips);
  addMsg('ai',box);
  const el=document.getElementById(pid);let i=0;
  function type(){if(i<doc.length){el.innerHTML=doc.substring(0,i+1)+'<span class="blink"></span>';i++;setTimeout(type,i<500?3:7);}else el.textContent=doc;}type();scrl();
}
function showProfileForm(afterDk){
  const form=document.createElement('div');form.className='fbox';
  form.innerHTML='<div class="fhdr"><div class="fhdr-ic">\u{1F464}</div><div class="fhdr-t">Pers\u00f6nliche Daten</div></div>';
  const body=document.createElement('div');body.className='fbody';
  const g=document.createElement('div');g.className='fgrid';
  [{id:'f1',l:'Vorname',t:'text',ph:'Max',f:false,ac:'given-name'},{id:'f2',l:'Nachname',t:'text',ph:'Mustermann',f:false,ac:'family-name'},{id:'f3',l:'Stra\u00dfe & Nr.',t:'text',ph:'Musterstra\u00dfe 1',f:false,ac:'address-line1'},{id:'f4',l:'PLZ & Ort',t:'text',ph:'12345 Berlin',f:false,ac:'address-level2'},{id:'f5',l:'Geburtsdatum',t:'date',ph:'',f:false,ac:'bday'},{id:'f6',l:'Telefon',t:'tel',ph:'+49 123 456789',f:false,ac:'tel'},{id:'f7',l:'E-Mail',t:'email',ph:'ihre@email.de',f:true,ac:'email'}].forEach(f=>{
    const w=document.createElement('div');w.className='fi'+(f.f?' fful':'');
    w.innerHTML=`<label>${f.l}</label><input type="${f.t}" id="${f.id}" placeholder="${f.ph}" autocomplete="${f.ac}" value="${P[f.id]||''}">`;g.appendChild(w);
  });
  body.appendChild(g);
  const dn=document.createElement('div');dn.className='dsgvo-n';dn.innerHTML='\u{1F512} <b>Maximale Datensicherheit:</b> ChatHelp AI nutzt NUR anonyme Platzhalter [VORNAME] etc. Echte Daten bleiben auf Ihrem EU-Server. 24h Auto-L\u00f6schung.';body.appendChild(dn);
  const btn=document.createElement('button');btn.className='btn-g';btn.textContent='Weiter und Dokument erstellen \u2192';
  btn.onclick=()=>{
    const f1=document.getElementById('f1').value.trim(),f2=document.getElementById('f2').value.trim(),f3=document.getElementById('f3').value.trim(),f7=document.getElementById('f7').value.trim();
    if(!f1||!f2||!f3||!f7){alert('Bitte Vorname, Nachname, Adresse und E-Mail.');return;}
    P={f1,f2,f3,f4:document.getElementById('f4').value.trim(),f5:document.getElementById('f5').value,f6:document.getElementById('f6').value.trim(),f7};
    try{localStorage.setItem(FK,JSON.stringify(P));}catch(ex){}
    btn.disabled=true;addMsg('user',f1+' '+f2);
    setTimeout(()=>{addMsg('ai','\u2705 Profil gespeichert!');const dk=afterDk||CD;if(dk)setTimeout(()=>showQuestions(dk),300);else showAllCats();},350);
  };
  body.appendChild(btn);form.appendChild(body);addMsg('ai',form);
}
async function loadHistory(){
  const list=document.getElementById('ant-list');const stats=document.getElementById('ant-stats');
  list.innerHTML='<div style="text-align:center;padding:28px;color:var(--t3);font-size:13px">\u23F3 Laden...</div>';
  try{
    const r=await fetch(API+'?action=history');const data=await r.json();const docs=data.docs||[];
    if(!docs.length){stats.style.display='none';list.innerHTML='<div class="ant-empty"><div class="ant-empty-ic"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/></svg></div><div class="ant-empty-t">Noch keine Antr\u00e4ge</div><div class="ant-empty-s">Erstellen Sie Ihr erstes Dokument \u2014 es erscheint hier.</div></div>';return;}
    const kept=docs.filter(d=>d.keep).length;const soon=docs.filter(d=>!d.keep&&parseInt(d.expiresIn)<3).length;
    document.getElementById('stat-tot').textContent=docs.length;document.getElementById('stat-kept').textContent=kept;document.getElementById('stat-exp').textContent=soon;stats.style.display='flex';list.innerHTML='';
    docs.forEach(d=>{
      const docDef=DOCS[d.docType]||DOCS._default;const cc=CC_DATA[d.country]||CC_DATA.DE;
      const expCls=d.keep?'ae-kept':parseInt(d.expiresIn)<3?'ae-soon':'ae-norm';
      const expTxt=d.keep?'\u2705 Dauerhaft gespeichert':'\u23F0 L\u00e4uft ab in '+d.expiresIn;
      const card=document.createElement('div');card.className='acard';
      const prev=(d.preview||'').replace(/`/g,'\\`').replace(/\n/g,'\\n');
      card.innerHTML=`<div class="acard-hdr"><div class="acard-ic">${docDef.ic}</div><div class="acard-info"><div class="acard-name">${d.docName}</div><div class="acard-meta">${d.created} \u00b7 ${cc.f} ${cc.n}</div></div><div class="acard-acts"><button class="ab" onclick="dlDoc('${d.docName}','${d.country}',\`${prev}\`)"><svg viewBox="0 0 24 24"><line x1="12" y1="18" x2="12" y2="12"/><polyline points="9,15 12,18 15,15"/><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>PDF</button><button class="ab ${d.keep?'kept':''}" onclick="keepDoc('${d.id}',this)">${d.keep?'\u2705 Behalten':'<svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17,21 17,13 7,13 7,21"/></svg>Behalten'}</button><button class="ab del" onclick="delDoc('${d.id}',this)"><svg viewBox="0 0 24 24"><polyline points="3,6 5,6 21,6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>L\u00f6schen</button></div></div><div class="acard-body"><div class="aprev">${d.preview||''}<div class="aprev-fade"></div></div><span class="aexp ${expCls}">${expTxt}</span></div>`;
      list.appendChild(card);
    });loadRecentDocs(docs);
  }catch(ex){list.innerHTML='<div style="text-align:center;padding:28px;color:var(--rd);font-size:13px">\u274C '+ex.message+'</div>';}
}
async function keepDoc(docId,btn){const r=await fetch(API+'?action=keepdoc',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({docId,keep:true})});const d=await r.json();if(d.success){btn.className='ab kept';btn.innerHTML='\u2705 Behalten';btn.disabled=true;setTimeout(loadHistory,500);}}
async function delDoc(docId,btn){if(!confirm('Dokument wirklich l\u00f6schen?'))return;const r=await fetch(API+'?action=delete',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({docId})});const d=await r.json();if(d.success)loadHistory();}
function dlDoc(name,cc,preview){makePDF(preview.replace(/\\n/g,'\n'),name,cc);}
function loadRecentDocs(docs){
  if(!docs){fetch(API+'?action=history').then(r=>r.json()).then(d=>loadRecentDocs(d.docs||[])).catch(()=>{});return;}
  const sec=document.getElementById('recent-sec');const list=document.getElementById('recent-list');
  if(!sec||!list||!docs.length){if(sec)sec.style.display='none';return;}
  sec.style.display='block';list.innerHTML='';
  docs.slice(0,3).forEach(d=>{
    const docDef=DOCS[d.docType]||DOCS._default;const item=document.createElement('div');item.className='recent-item';
    item.innerHTML=`<div class="recent-item-ic">${docDef.ic}</div><div class="recent-item-n">${d.docName}</div><div class="recent-item-d">${d.created}</div>`;
    item.onclick=()=>gP('ant');list.appendChild(item);
  });
}
function loadKonto(){
  document.getElementById('k-name').textContent=P.f1&&P.f2?P.f1+' '+P.f2:'\u2014';
  document.getElementById('k-email').textContent=P.f7||'\u2014';
  document.getElementById('k-adr').textContent=P.f3?(P.f3+(P.f4?' '+P.f4:'')):'—';
  const c=CC_DATA[CC]||CC_DATA.DE;document.getElementById('k-cc').textContent=c.f+' '+c.n;
}
function editProfile(){gP('chat');setTimeout(()=>{addMsg('ai','Profil bearbeiten:');showProfileForm(null);},200);}
function deleteProfile(){if(!confirm('Profil wirklich l\u00f6schen?'))return;P={};try{localStorage.removeItem(FK);}catch(e){}loadKonto();addMsg('ai','\u2705 Profil gel\u00f6scht.');gP('chat');}
function qS(dk){cSB();gP('chat');const doc=DOCS[dk]||DOCS._default;addMsg('user',doc.n);CD=dk;if(!P.f1){setTimeout(()=>{addMsg('ai','Bitte zuerst Ihre Daten:');showProfileForm(dk);},400);}else setTimeout(()=>showQuestions(dk),400);}
function NC(){
  CD=null;busy=false;localStorage.removeItem(CK);
  const u=UI_TEXTS[UL]||UI_TEXTS.de;
  document.getElementById('mi').innerHTML=`<div class="wlc" id="wlc"><div class="wlc-hero"><div class="wlc-mark"><svg viewBox="0 0 40 40" fill="none" width="26" height="26"><path d="M6 32L13 14L22 25L27 18L34 32" stroke="#d4a84a" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="13" cy="14" r="3.5" fill="#d4a84a"/><circle cx="27" cy="18" r="2.2" fill="#ecc060" opacity=".7"/></svg></div><h2 id="wlc-h">${u.h}</h2><p id="wlc-p">${u.p}</p><div class="wlc-stats"><div class="wlc-stat"><span class="wlc-stat-n">75+</span><span class="wlc-stat-l">Dokumente</span></div><div class="wlc-stat-div"></div><div class="wlc-stat"><span class="wlc-stat-n">12</span><span class="wlc-stat-l">L\u00e4nder</span></div><div class="wlc-stat-div"></div><div class="wlc-stat"><span class="wlc-stat-n">8</span><span class="wlc-stat-l">Sprachen</span></div><div class="wlc-stat-div"></div><div class="wlc-stat"><span class="wlc-stat-n">0</span><span class="wlc-stat-l">Tracking</span></div></div></div><div class="wphoto" onclick="document.getElementById('pf').click()"><div class="wphoto-ic"><svg viewBox="0 0 24 24" fill="none" stroke="#d4a84a" stroke-width="1.8" stroke-linecap="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></div><div style="flex:1"><div class="wphoto-t">Brief / Bescheid fotografieren</div><div class="wphoto-s">ChatHelp Vision \u2014 erkennt Beh\u00f6rde, Frist &amp; Aktenzeichen</div></div><span style="font-size:10px;background:var(--ga);border:1px solid var(--gb);color:var(--gold);padding:3px 9px;border-radius:100px;font-weight:700;flex-shrink:0">KI</span></div><div class="wcat-grid"><div class="wcat" onclick="showCat('behoerde')"><div class="wcat-ic" style="background:rgba(212,168,74,.12)"><svg viewBox="0 0 24 24" fill="none" stroke="#d4a84a" stroke-width="1.8" stroke-linecap="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg></div><div class="wcat-t" id="wc1">${u.c1}</div><div class="wcat-s">Widerspruch \u2022 Aufenthalt</div></div><div class="wcat" onclick="showCat('immigration')"><div class="wcat-ic" style="background:rgba(66,150,255,.12)"><svg viewBox="0 0 24 24" fill="none" stroke="#6694ff" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg></div><div class="wcat-t">Einwanderung</div><div class="wcat-s">Duldung \u2022 AE \u2022 Visum</div></div><div class="wcat" onclick="showCat('miete')"><div class="wcat-ic" style="background:rgba(66,223,148,.1)"><svg viewBox="0 0 24 24" fill="none" stroke="#42df94" stroke-width="1.8" stroke-linecap="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg></div><div class="wcat-t" id="wc3">Miete</div><div class="wcat-s">Vertrag \u2022 K\u00fcndigung</div></div><div class="wcat" onclick="showCat('arbeit')"><div class="wcat-ic" style="background:rgba(200,130,255,.1)"><svg viewBox="0 0 24 24" fill="none" stroke="#c882ff" stroke-width="1.8" stroke-linecap="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg></div><div class="wcat-t" id="wc4">Arbeit</div><div class="wcat-s">Vertrag \u2022 K\u00fcndigung</div></div><div class="wcat" onclick="showCat('steuern')"><div class="wcat-ic" style="background:rgba(255,180,50,.1)"><svg viewBox="0 0 24 24" fill="none" stroke="#ffb432" stroke-width="1.8" stroke-linecap="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></div><div class="wcat-t" id="wc2">${u.c2}</div><div class="wcat-s">Erkl\u00e4rung \u2022 Einspruch</div></div><div class="wcat" onclick="showCat('soziales')"><div class="wcat-ic" style="background:rgba(50,200,200,.1)"><svg viewBox="0 0 24 24" fill="none" stroke="#32c8c8" stroke-width="1.8" stroke-linecap="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div><div class="wcat-t">Soziales</div><div class="wcat-s">B\u00fcrgergeld \u2022 BAf\u00f6G</div></div><div class="wcat" onclick="showCat('gesundheit')"><div class="wcat-ic" style="background:rgba(255,80,100,.1)"><svg viewBox="0 0 24 24" fill="none" stroke="#ff5064" stroke-width="1.8" stroke-linecap="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div><div class="wcat-t">Gesundheit</div><div class="wcat-s">Krankenkasse \u2022 Pflege</div></div><div class="wcat" onclick="showCat('gewerbe')"><div class="wcat-ic" style="background:rgba(255,150,50,.1)"><svg viewBox="0 0 24 24" fill="none" stroke="#ff9632" stroke-width="1.8" stroke-linecap="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg></div><div class="wcat-t">Gewerbe</div><div class="wcat-s">Anmeldung \u2022 GbR \u2022 NDA</div></div><div class="wcat" onclick="showCat('strafrecht')"><div class="wcat-ic" style="background:rgba(224,80,80,.1)"><svg viewBox="0 0 24 24" fill="none" stroke="#e05050" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div><div class="wcat-t">Strafrecht</div><div class="wcat-s">Anzeige \u2022 Widerspruch</div></div></div><div id="recent-sec" style="display:none"><div class="recent-bar"><div class="recent-bar-t">Zuletzt erstellt</div><div id="recent-list"></div></div></div><div class="lang-bar"><span class="lang-txt">Spracherkennung aktiv:</span><div class="lflags"><span class="lf on" id="lf-de">\u{1F1E9}\u{1F1EA}</span><span class="lf" id="lf-tr">\u{1F1F9}\u{1F1F7}</span><span class="lf" id="lf-ar">\u{1F1F8}\u{1F1E6}</span><span class="lf" id="lf-ru">\u{1F1F7}\u{1F1FA}</span><span class="lf" id="lf-en">\u{1F1EC}\u{1F1E7}</span><span class="lf" id="lf-fr">\u{1F1EB}\u{1F1F7}</span></div></div></div>`;
  gP('chat');cSB();loadRecentDocs();
}
applyUI();applyCC();restoreConv();detectLoc();
</script>

<!-- IMPRESSUM MODAL -->
<div class="lm" id="lm-imp" onclick="if(event.target===this)cLM('lm-imp')">
<div class="lm-box">
  <div class="lm-hdr">
    <div class="lm-title">&#x1F4CB; Impressum</div>
    <button class="lm-close" onclick="cLM('lm-imp')">&#x2715;</button>
  </div>
  <div class="lm-body">
    <div class="lm-sec">
      <div class="lm-sec-n">&#xa7;5 TMG &mdash; Diensteanbieter</div>
      <div class="lm-grid">
        <div class="lm-card"><div class="lm-card-l">Unternehmen</div><div class="lm-card-v">Acerasoft LLC</div></div>
        <div class="lm-card"><div class="lm-card-l">Rechtsform</div><div class="lm-card-v">LLC &middot; Delaware, USA</div></div>
        <div class="lm-card" style="grid-column:1/-1"><div class="lm-card-l">Adresse</div><div class="lm-card-v">8 The Green, Suite B<br>Dover, Delaware 19901 &middot; USA</div></div>
        <div class="lm-card"><div class="lm-card-l">E-Mail</div><div class="lm-card-v"><a href="mailto:support@chat-help.de">support@chat-help.de</a></div></div>
        <div class="lm-card"><div class="lm-card-l">Steuer-ID (EIN)</div><div class="lm-card-v">61-2070643</div></div>
      </div>
    </div>
    <div class="lm-sec">
      <div class="lm-sec-n">&#xa7;55 Abs. 2 RStV</div>
      <h2>Inhaltlich Verantwortlicher</h2>
      <div class="lm-grid">
        <div class="lm-card" style="grid-column:1/-1"><div class="lm-card-l">Verantwortlich f&uuml;r den Inhalt</div><div class="lm-card-v">Serdar Bilgi&ccedil;</div></div>
        <div class="lm-card"><div class="lm-card-l">E-Mail</div><div class="lm-card-v"><a href="mailto:support@chat-help.de">support@chat-help.de</a></div></div>
        <div class="lm-card"><div class="lm-card-l">Datenschutz</div><div class="lm-card-v"><a href="mailto:support@chat-help.de">support@chat-help.de</a></div></div>
      </div>
    </div>
    <div class="lm-sec">
      <h2>Haftungsausschluss</h2>
      <p>Die durch ChatHelp erstellten Rechtsdokumente sind KI-generierte Vorlagen und stellen <strong>keine Rechtsberatung</strong> im Sinne des RDG dar. Sie ersetzen nicht die Beratung durch einen zugelassenen Rechtsanwalt.</p>
      <div class="lm-warn"><p>&#x26A0;&#xFE0F; KI-erstellte Dokumente sind Vorlagen &mdash; bitte von einem Rechtsanwalt pr&uuml;fen lassen.</p></div>
    </div>
    <div class="lm-sec">
      <h2>KI-Transparenz &mdash; EU AI Act</h2>
      <p>ChatHelp f&auml;llt in die Kategorie <strong>&bdquo;geringes Risiko&ldquo;</strong> gem&auml;&szlig; EU AI Act. Alle Dokumente sind KI-generiert und werden dem Nutzer klar als solche kenntlich gemacht.</p>
    </div>
    <div class="lm-sec">
      <h2>Streitschlichtung (ODR)</h2>
      <p>EU-Streitschlichtungsplattform: <a href="https://ec.europa.eu/consumers/odr" target="_blank" style="color:var(--gold)">ec.europa.eu/consumers/odr</a><br>Wir sind nicht verpflichtet, an Streitschlichtungsverfahren teilzunehmen.</p>
    </div>
  </div>
</div>
</div>

<!-- DATENSCHUTZ MODAL -->
<div class="lm" id="lm-dsc" onclick="if(event.target===this)cLM('lm-dsc')">
<div class="lm-box">
  <div class="lm-hdr">
    <div class="lm-title">&#x1F512; Datenschutzerkl&auml;rung</div>
    <button class="lm-close" onclick="cLM('lm-dsc')">&#x2715;</button>
  </div>
  <div class="lm-body">
    <div class="lm-sec">
      <div class="lm-sec-n">&#xa7;1 &mdash; Verantwortlicher</div>
      <div class="lm-grid">
        <div class="lm-card" style="grid-column:1/-1"><div class="lm-card-l">Betreiber</div><div class="lm-card-v">Acerasoft LLC &middot; 8 The Green, Ste B &middot; Dover, Delaware 19901 &middot; USA</div></div>
        <div class="lm-card"><div class="lm-card-l">Datenschutz</div><div class="lm-card-v"><a href="mailto:support@chat-help.de">support@chat-help.de</a></div></div>
        <div class="lm-card"><div class="lm-card-l">Aufsichtsbeh&ouml;rde</div><div class="lm-card-v">LfDI Baden-W&uuml;rttemberg</div></div>
      </div>
    </div>
    <div class="lm-sec">
      <div class="lm-sec-n">&#xa7;2 &mdash; Unser Kernprinzip</div>
      <div class="lm-note"><p><strong>Privacy by Design:</strong> ChatHelp AI erh&auml;lt NUR anonyme Platzhalter [VORNAME], [ADRESSE] etc. Echte pers&ouml;nliche Daten verlassen niemals Ihren EU-Server. Automatische L&ouml;schung nach 24h.</p></div>
    </div>
    <div class="lm-sec">
      <div class="lm-sec-n">&#xa7;3 &mdash; Wie KI-Verarbeitung funktioniert</div>
      <ol style="list-style:none;padding:0;counter-reset:step">
        <li style="padding:6px 0 6px 32px;position:relative;font-size:13px;color:var(--t2)"><span style="position:absolute;left:0;top:5px;width:22px;height:22px;background:var(--ga);border:1px solid var(--gb);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:var(--gold)">1</span>Nutzer gibt Falldaten ein</li>
        <li style="padding:6px 0 6px 32px;position:relative;font-size:13px;color:var(--t2)"><span style="position:absolute;left:0;top:5px;width:22px;height:22px;background:var(--ga);border:1px solid var(--gb);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:var(--gold)">2</span>Server ersetzt alle Daten durch Platzhalter</li>
        <li style="padding:6px 0 6px 32px;position:relative;font-size:13px;color:var(--t2)"><span style="position:absolute;left:0;top:5px;width:22px;height:22px;background:var(--ga);border:1px solid var(--gb);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:var(--gold)">3</span>KI erh&auml;lt NUR Platzhalter &mdash; kein Name, keine Adresse</li>
        <li style="padding:6px 0 6px 32px;position:relative;font-size:13px;color:var(--t2)"><span style="position:absolute;left:0;top:5px;width:22px;height:22px;background:var(--ga);border:1px solid var(--gb);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:var(--gold)">4</span>Server setzt echte Daten lokal ein &mdash; nie nach au&szlig;en</li>
      </ol>
    </div>
    <div class="lm-sec">
      <div class="lm-sec-n">&#xa7;4 &mdash; Datenspeicherung</div>
      <table class="lm-table">
        <tr><th>Datenart</th><th>Speicher</th><th>L&ouml;schung</th></tr>
        <tr><td>Profildaten</td><td>Ihr Browser (lokal)</td><td class="lm-diff">Jederzeit</td></tr>
        <tr><td>Dokumente</td><td>EU-Server</td><td class="lm-yes">24h automatisch</td></tr>
        <tr><td>Dokumente (Behalten)</td><td>EU-Server</td><td class="lm-diff">Auf Anfrage</td></tr>
        <tr><td>Serverlog / IP</td><td>EU-Server</td><td class="lm-yes">7 Tage anonymisiert</td></tr>
        <tr><td>Zahlungsdaten</td><td>Stripe Europe</td><td class="lm-diff">Steuerrecht (10 J.)</td></tr>
      </table>
    </div>
    <div class="lm-sec">
      <div class="lm-sec-n">&#xa7;5 &mdash; Drittanbieter</div>
      <table class="lm-table">
        <tr><th>Anbieter</th><th>Zweck</th><th>AVV</th></tr>
        <tr><td>Host-Europe GmbH</td><td>Hosting (DE)</td><td class="lm-yes">&#x2713; Abgeschlossen</td></tr>
        <tr><td>Stripe Europe Ltd.</td><td>Zahlungen</td><td class="lm-yes">&#x2713; Standard-DPA</td></tr>
        <tr><td>KI-Dienste</td><td>Dokumenterstellung</td><td class="lm-diff">Nicht n&ouml;tig (anonym)</td></tr>
      </table>
    </div>
    <div class="lm-sec">
      <div class="lm-sec-n">&#xa7;6 &mdash; Ihre Rechte (DSGVO)</div>
      <ul>
        <li><strong>Auskunft</strong> (Art. 15): Einsicht in gespeicherte Daten</li>
        <li><strong>L&ouml;schung</strong> (Art. 17): In der App unter Konto &rarr; Profil l&ouml;schen</li>
        <li><strong>Berichtigung</strong> (Art. 16): Auf Anfrage sofort</li>
        <li><strong>Daten&uuml;bertragbarkeit</strong> (Art. 20): Auf Anfrage</li>
        <li><strong>Beschwerde</strong>: LfDI Baden-W&uuml;rttemberg</li>
      </ul>
    </div>
    <div class="lm-sec">
      <div class="lm-sec-n">&#xa7;7 &mdash; Cookies &amp; Tracking</div>
      <p>ChatHelp verwendet <strong>kein Google Analytics, kein Facebook Pixel, kein Tracking</strong>. Nur technisch notwendige localStorage-Eintr&auml;ge (Sitzung, Einstellungen).</p>
    </div>
    <div class="lm-sec" style="border:1px solid rgba(102,136,255,.25);background:linear-gradient(135deg,rgba(102,136,255,.06),var(--s2));border-radius:var(--r2);padding:16px 18px">
      <div class="lm-sec-n" style="color:rgba(140,170,255,.9)">&#xa7;8 &mdash; B2B &mdash; Kanzleien &amp; Unternehmen</div>
      <h2>F&uuml;r Berufsgeheimnistr&auml;ger</h2>
      <div style="background:rgba(102,136,255,.07);border:1px solid rgba(102,136,255,.2);border-radius:var(--r2);padding:12px 14px;margin-bottom:12px"><p style="color:rgba(160,190,255,.9);font-size:12.5px;margin:0"><strong>&#xa7;203 StGB:</strong> Rechtsanw&auml;lte, Notare, Steuerberater k&ouml;nnen ChatHelp datenschutzkonform nutzen. Auf Anfrage stellen wir eine Geheimhaltungsvereinbarung nach &#xa7;203 Abs. 4 StGB aus.</p></div>
      <table class="lm-table">
        <tr><th>Feature</th><th>Standard</th><th>B2B</th></tr>
        <tr><td>Anonymisierung</td><td class="lm-yes">&#x2713;</td><td class="lm-yes">&#x2713;</td></tr>
        <tr><td>AVV (Art. 28 DSGVO)</td><td class="lm-yes">&#x2713; Host + Stripe</td><td class="lm-yes">&#x2713; Erweitert</td></tr>
        <tr><td>&#xa7;203 StGB Vereinbarung</td><td class="lm-no">&mdash;</td><td class="lm-yes">&#x2713;</td></tr>
        <tr><td>Eigene Subdom&auml;ne</td><td class="lm-no">&mdash;</td><td class="lm-yes">&#x2713;</td></tr>
        <tr><td>Audit-Log</td><td class="lm-no">&mdash;</td><td class="lm-yes">&#x2713;</td></tr>
      </table>
      <a href="mailto:support@chat-help.de?subject=B2B%20Anfrage" style="display:inline-flex;align-items:center;gap:6px;background:rgba(102,136,255,.15);border:1px solid rgba(102,136,255,.3);color:rgba(160,190,255,.95);padding:8px 16px;border-radius:var(--r);font-size:12.5px;font-weight:600;text-decoration:none;margin-top:10px">
        &#x2709; support@chat-help.de
      </a>
    </div>
    <div class="lm-sec" style="margin-top:20px">
      <p style="font-size:12px;color:var(--t4);text-align:center">Stand: Mai 2026 &middot; chat-help.de &middot; chat-help.com</p>
    </div>
  </div>
</div>
</div>

</body>
</html>
