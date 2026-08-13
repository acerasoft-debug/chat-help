// ChatHelp — Instagram günlük otomatik paylaşım (@chathelpp)
// Instagram Login akışı (graph.instagram.com, token IGAA...). GitHub Actions'ta çalışır.
// Token: process.env.IG_TOKEN (GitHub Secret). Sunucu/cPanel GEREKMEZ.
// Rotasyon tarihe göre deterministik (state dosyası yok): her gün konu ilerler,
// banka bitince başa döner. Reel'ler jsDelivr CDN'den servis edilir (content.json).
import { readFileSync } from 'node:fs';

const TOKEN = process.env.IG_TOKEN;
if (!TOKEN) { console.error('HATA: IG_TOKEN yok (GitHub Secret ekle).'); process.exit(1); }
const API = 'https://graph.instagram.com/v21.0';

// Workflow'daki cron saatleri -> dil (bölge prime-time)
const SCHED2LANG = {
  '0 7 * * *':'de', '0 11 * * *':'fr', '0 12 * * *':'en',
  '0 14 * * *':'ru', '0 15 * * *':'tr', '0 18 * * *':'es',
};
const ALL = ['de','en','fr','tr','es','ru'];

const content = JSON.parse(readFileSync('marketing/auto-post/content.json','utf8'));
const items = content.items || [];
const topics = [...new Set(items.map(i => i.topic))];   // konu sırası (görünme sırası)

// Gün sayısı (UTC) -> bugünkü konu
const now = new Date();
const doy = Math.floor(
  (Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate())
   - Date.UTC(now.getUTCFullYear(), 0, 0)) / 86400000);
const topic = topics[doy % topics.length];

// Hangi dil(ler)? manuel input > cron eşlemesi > hepsi
const langIn = (process.env.LANG_IN || '').trim();
const sched  = (process.env.SCHEDULE || '').trim();
const langs = langIn ? [langIn]
            : (sched && SCHED2LANG[sched]) ? [SCHED2LANG[sched]]
            : ALL;

async function api(method, path, params) {
  const p = new URLSearchParams({ ...params, access_token: TOKEN });
  const res = method === 'GET'
    ? await fetch(`${API}${path}?${p}`)
    : await fetch(`${API}${path}`, { method: 'POST', body: p });
  const j = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(`${res.status} ${JSON.stringify(j)}`);
  return j;
}

async function igUserId() {
  const j = await api('GET', '/me', { fields: 'user_id' });
  return j.user_id || j.id;
}

async function publishReel(uid, videoUrl, caption) {
  const c = await api('POST', `/${uid}/media`, { media_type: 'REELS', video_url: videoUrl, caption });
  const cid = c.id;
  for (let i = 0; i < 30; i++) {
    await new Promise(r => setTimeout(r, 5000));
    const s = await api('GET', `/${cid}`, { fields: 'status_code' });
    if (s.status_code === 'FINISHED') break;
    if (s.status_code === 'ERROR') throw new Error('container ERROR');
    if (i === 29) throw new Error('container timeout (video işlenmedi)');
  }
  const pub = await api('POST', `/${uid}/media_publish`, { creation_id: cid });
  return pub.id;
}

const uid = await igUserId();
console.log(`gün=${doy}  konu=${topic}  diller=${langs.join(',')}  ig_user=${uid}`);
let ok = 0;
for (const lang of langs) {
  const it = items.find(x => x.topic === topic && x.lang === lang);
  if (!it) { console.log(`atlandı: ${topic}/${lang} içerik yok`); continue; }
  try {
    const mid = await publishReel(uid, it.video, it.caption);
    console.log(`✓ YAYIN ${lang} ${it.id} -> media ${mid}`);
    ok++;
  } catch (e) {
    console.error(`✗ HATA ${lang} ${it.id}: ${e.message}`);
  }
}
console.log(`bitti: ${ok}/${langs.length} yayınlandı`);
if (ok === 0) process.exit(1);
