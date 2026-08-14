// ChatHelp — Instagram günlük otomatik paylaşım (@chathelpp)
// Instagram Login akışı (graph.instagram.com, IGAA token). GitHub Actions'ta çalışır; sunucu GEREKMEZ.
// Her DİL kendi içeriğini tarihe göre döndürür (jenerik + ülke-özel konular).
// Reel + ÖZEL KAPAK (cover_url) jsDelivr CDN'den; token IG_TOKEN Secret'ından.
import { readFileSync } from 'node:fs';

const TOKEN = process.env.IG_TOKEN;
if (!TOKEN) { console.error('HATA: IG_TOKEN yok (GitHub Secret ekle).'); process.exit(1); }
const API = 'https://graph.instagram.com/v21.0';

// Workflow'daki cron saatleri -> dil. Hepsi Türkiye öğle saatine (TR=UTC+3)
// kümelendi ki paylaşımlar canlı takip edilebilsin. daily-instagram.yml ile BİREBİR aynı olmalı.
const SCHED2LANG = {
  '0 8 * * *':'tr',   // 11:00 TR
  '20 8 * * *':'de',  // 11:20 TR
  '40 8 * * *':'en',  // 11:40 TR
  '0 9 * * *':'fr',   // 12:00 TR
  '20 9 * * *':'es',  // 12:20 TR
  '40 9 * * *':'ru',  // 12:40 TR
};
const ALL = ['de','en','fr','tr','es','ru'];

const content = JSON.parse(readFileSync('marketing/auto-post/content.json','utf8'));
const items = content.items || [];
const THUMB = content.cover_thumb_offset_ms ?? 1600;   // cover_url yoksa: kara ilk kareyi atla

const now = new Date();
const doy = Math.floor((Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate())
   - Date.UTC(now.getUTCFullYear(), 0, 0)) / 86400000);

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

async function publishReel(uid, it) {
  const base  = { media_type: 'REELS', video_url: it.video, caption: it.caption };
  const extra = { ...base };
  if (it.cover) extra.cover_url = it.cover;                      // özel kapak görseli
  else if (THUMB != null) extra.thumb_offset = String(it.thumb_offset ?? THUMB);
  let c;
  try {
    c = await api('POST', `/${uid}/media`, extra);
  } catch (e) {                                                 // kapak/param reddedilirse post yine gitsin
    console.error(`kapak/param reddedildi, sade deniyorum: ${e.message}`);
    c = await api('POST', `/${uid}/media`, base);
  }
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
console.log(`gün=${doy}  diller=${langs.join(',')}  ig_user=${uid}`);
let ok = 0, total = 0;
for (const lang of langs) {
  const li = items.filter(x => x.lang === lang && x.video && x.caption);
  if (!li.length) { console.log(`atlandı: ${lang} içerik yok`); continue; }
  const it = li[doy % li.length];   // bu dilin sıradaki konusu (tarihe göre döner)
  total++;
  try {
    const mid = await publishReel(uid, it);
    console.log(`✓ ${lang} ${it.id} -> media ${mid}`);
    ok++;
  } catch (e) {
    console.error(`✗ ${lang} ${it.id}: ${e.message}`);
  }
}
console.log(`bitti: ${ok}/${total} yayınlandı`);
if (total > 0 && ok === 0) process.exit(1);
