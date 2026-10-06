/* Arapca RTL, INGILIZCE ile KARSILASTIRMALI. Tek basina "su oge tasiyor" demek
   yaniltir: yatay kayan raylarin (brandrail, tablo) cocuklari her dilde tasar.
   Anlamli olan FARK: yalnizca Arapcada tasan oge = RTL kaynakli gerileme. */
const { chromium, devices } = require('playwright');
const BASE='http://127.0.0.1:8085';
const PAGES=[['home','/'],['shop','/shop'],['b2b','/b2b/t-shirts'],['wholesale','/wholesale/dsquared2'],
             ['product','/product?id=lac-pique-polo'],['groups','/groups'],['faq','/faq'],['login','/login'],
             ['register','/register'],['journal','/journal'],['help','/help'],['404','/yok-boyle-sayfa']];
const VIEWS={mobile:{...devices['iPhone 13'],viewport:{width:390,height:844}},desktop:{viewport:{width:1366,height:768}}};
const probe = async (page, url) => {
  await page.goto(url,{waitUntil:'domcontentloaded'}).catch(()=>{});
  return page.evaluate(() => {
    const out=[];
    document.querySelectorAll('*').forEach(el=>{
      const b=el.getBoundingClientRect(); if(!b.width) return;
      if(b.right>window.innerWidth+2||b.left<-2){
        /* Kasitli yatay kaydiricinin icindeki oge tasma sayilmaz. */
        let p=el.parentElement, inScroller=false;
        while(p&&p!==document.body){ const ov=getComputedStyle(p).overflowX;
          if(ov==='auto'||ov==='scroll'){inScroller=true;break;} p=p.parentElement; }
        if(inScroller) return;
        out.push(el.tagName.toLowerCase()+(typeof el.className==='string'&&el.className.trim()?'.'+el.className.trim().split(/\s+/)[0]:''));
      }
    });
    return { over:[...new Set(out)].slice(0,5), pageScroll: document.documentElement.scrollWidth>window.innerWidth+2,
             dir:document.documentElement.getAttribute('dir') };
  }).catch(()=>({over:[],pageScroll:false,dir:'?'}));
};
(async()=>{
  const browser=await chromium.launch({executablePath:process.env.CHROME});
  let regress=0;
  for(const [name,path] of PAGES) for(const [vn,opts] of Object.entries(VIEWS)){
    const ctx=await browser.newContext(opts); const page=await ctx.newPage();
    await page.route('**/*',r=>{const u=r.request().url();return (u.startsWith(BASE)||u.startsWith('data:'))?r.continue():r.abort();});
    const sep=path.includes('?')?'&':'?';
    const en=await probe(page,`${BASE}${path}`);
    const ar=await probe(page,`${BASE}${path}${sep}lang=ar`);
    const only=ar.over.filter(x=>!en.over.includes(x));           // yalnizca Arapcada tasan
    const scrollNew = ar.pageScroll && !en.pageScroll;
    const bad = only.length||scrollNew||ar.dir!=='rtl';
    if(bad) regress++;
    console.log(`${bad?'GERILEME':'   ok   '} ${name.padEnd(10)} ${vn.padEnd(8)} dir=${ar.dir}`+
      ` | sayfa-kaymasi en=${en.pageScroll?'VAR':'yok'} ar=${ar.pageScroll?'VAR':'yok'}`+
      `${only.length?' | YALNIZCA ar tasiyor: '+only.join(', '):''}`+
      `${en.over.length?' | (her iki dilde: '+en.over.slice(0,2).join(', ')+')':''}`);
    await ctx.close();
  }
  console.log(`\nRTL kaynakli gerileme: ${regress}`);
  await browser.close();
})();
