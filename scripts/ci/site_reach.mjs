// Salt-okunur: verilen alan adlarını butik bulucunun httpGet'i gibi dener ve hatanın TÜRÜNÜ basar (13 Eki 2026).
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';
const doms = (process.argv[2] || '').split(/[\s,]+/).filter(Boolean);
const why = (e) => { const c = e?.cause || {}; return [e?.name, c.code || '', (c.message || e?.message || '').slice(0, 70)].filter(Boolean).join(' | '); };
const tally = {};
for (const d of doms) {
  const out = [];
  for (const u of [`https://${d}/`, `https://www.${d}/`, `http://${d}/`]) {
    const t0 = Date.now();
    try {
      const r = await fetch(u, { redirect: 'follow', signal: AbortSignal.timeout(25000), headers: { 'user-agent': UA, accept: 'text/html,*/*;q=0.8', 'accept-language': 'it,en;q=0.8' } });
      const txt = await r.text();
      out.push(`${u} → ${r.status} ${(r.headers.get('content-type') || '').slice(0, 20)} ${txt.length}B ${r.headers.get('server') || ''} ${Date.now() - t0}ms${/cf-ray|cloudflare/i.test([...r.headers].join(' ')) ? ' [cloudflare]' : ''}`);
      const k = r.status >= 200 && r.status < 400 ? (txt.length > 300 ? 'OK' : 'OK-ama-boş') : 'HTTP ' + r.status; tally[k] = (tally[k] || 0) + 1;
      break;
    } catch (e) { out.push(`${u} ✗ ${why(e)} ${Date.now() - t0}ms`); }
  }
  if (!out.some(l => l.includes('→'))) { const k = out[0].replace(/^.*✗ /, '').split(' | ').slice(0, 2).join(' | '); tally[k] = (tally[k] || 0) + 1; }
  console.log(d + '\n  ' + out.join('\n  '));
}
console.log('\nÖZET:', JSON.stringify(tally));
