#!/usr/bin/env node
/**
 * ChatHelp — Günlük sağlık kontrolü (GitHub Actions agent'ı)
 * - Önemli URL'leri kontrol eder (durum kodu + gecikme)
 * - Markdown rapor: konsol + GitHub job özeti ($GITHUB_STEP_SUMMARY)
 * - SMTP gizli anahtarları varsa günlük rapor e-postası gönderir (nodemailer)
 * - Kritik bir şey down ise exit 1 -> GitHub repo sahibine otomatik bildirim
 *
 * ENV (opsiyonel e-posta): SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, REPORT_TO, REPORT_FROM
 */

const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17 Safari/605.1.15';

const TARGETS = [
  { name: 'Startseite',     url: 'https://chat-help.com/' },
  { name: 'Chat-App',       url: 'https://chat-help.com/chat/' },
  { name: 'API (api.php)',  url: 'https://chat-help.com/chat/api.php', method: 'POST', body: '{"action":"ping"}' },
];

async function check(t) {
  const start = Date.now();
  try {
    const res = await fetch(t.url, {
      method: t.method || 'GET',
      headers: { 'User-Agent': UA, 'Accept': 'text/html,application/json', 'Content-Type': 'application/json' },
      body: t.body,
      redirect: 'manual',
      signal: AbortSignal.timeout(20000),
    });
    const ms = Date.now() - start;
    // 5xx veya ağ hatası = kritik. 4xx = uyarı (sunucu ayakta). 2xx/3xx = ok.
    const critical = res.status >= 500;
    return { ...t, code: res.status, ms, ok: !critical, warn: res.status >= 400 && res.status < 500 };
  } catch (e) {
    return { ...t, code: 0, ms: Date.now() - start, ok: false, warn: false, err: String(e.message || e) };
  }
}

function icon(r) { return r.ok ? (r.warn ? '🟡' : '🟢') : '🔴'; }

const results = await Promise.all(TARGETS.map(check));
const anyDown = results.some(r => !r.ok);

const lines = [];
lines.push(`# ChatHelp — Sağlık Raporu`);
lines.push(`_${new Date().toISOString().replace('T', ' ').slice(0, 16)} UTC_`);
lines.push('');
lines.push('| Durum | Hedef | Kod | Süre |');
lines.push('|:---:|---|:---:|---:|');
for (const r of results) {
  lines.push(`| ${icon(r)} | ${r.name} | ${r.code || (r.err ? 'ERR' : '-')} | ${r.ms} ms |`);
}
lines.push('');
lines.push(anyDown ? '**⚠️ Dikkat: en az bir servis erişilemez (5xx/timeout).**' : '**✅ Tüm servisler ayakta.**');
for (const r of results) if (r.err) lines.push(`- \`${r.name}\`: ${r.err}`);

const report = lines.join('\n');
console.log(report);

// GitHub job özeti
if (process.env.GITHUB_STEP_SUMMARY) {
  const { appendFileSync } = await import('node:fs');
  appendFileSync(process.env.GITHUB_STEP_SUMMARY, report + '\n');
}

// Opsiyonel e-posta raporu
if (process.env.SMTP_HOST && process.env.SMTP_USER && process.env.REPORT_TO) {
  try {
    const nm = await import('nodemailer');
    const port = Number(process.env.SMTP_PORT || 587);
    const transport = nm.default.createTransport({
      host: process.env.SMTP_HOST, port, secure: port === 465,
      auth: { user: process.env.SMTP_USER, pass: process.env.SMTP_PASS },
    });
    const html = report.replace(/\n/g, '<br>').replace(/\|/g, ' ');
    await transport.sendMail({
      from: `ChatHelp Agent <${process.env.REPORT_FROM || process.env.SMTP_USER}>`,
      to: process.env.REPORT_TO,
      subject: `ChatHelp Status ${anyDown ? '🔴 PROBLEM' : '🟢 OK'} — ${new Date().toISOString().slice(0, 10)}`,
      html,
    });
    console.log('E-posta raporu gönderildi ->', process.env.REPORT_TO);
  } catch (e) {
    console.log('E-posta gönderilemedi (atlanıyor):', String(e.message || e));
  }
}

process.exit(anyDown ? 1 : 0);
