#!/usr/bin/env python3
"""Satıcının KENDİ posta sunucusundan (SMTP) gönderim — .github/workflows/seller-outbox.yml.

stdin: vestra-seller-outbox.php take çıktısı {"items":[...], "creds":{uid:{host,port,user,pass,from}}}
stdout: {"results":[{"id","status":"sent|bounced|failed|auth|retry","reason","message_id"}]}  — şifre YOK.
stderr: maskeli kayıt (alıcı ve satıcı adresleri ilk harf + alan adı).

Depo herkese açık: SMTP girişleri yalnız bu sürecin belleğinde; hiçbir yere yazılmaz, basılmaz.
"""
import json, random, smtplib, ssl, sys, time
from email.message import EmailMessage
from email.utils import formataddr, formatdate, make_msgid

BUDGET_SEC = 8 * 60          # iş 10 dakikada bir koşar
PER_MSG_PAUSE = (3, 8)       # aynı sunucuya art arda mektup arası (sn)


def mask(addr: str) -> str:
    u, _, d = str(addr).partition('@')
    return (u[:1] + '***@' + d) if d else '***'


def scrub(text: str) -> str:
    out = []
    for w in str(text).split():
        out.append(mask(w.strip('<>,;')) if '@' in w else w)
    return ' '.join(out)[:160]


def log(msg: str) -> None:
    print(msg, file=sys.stderr, flush=True)


def connect(c: dict):
    host, port = str(c['host']).strip(), int(c.get('port') or 465)
    ctx = ssl.create_default_context()
    if port == 465:
        s = smtplib.SMTP_SSL(host, port, timeout=30, context=ctx)
    else:
        s = smtplib.SMTP(host, port, timeout=30)
        s.ehlo()
        s.starttls(context=ctx)
        s.ehlo()
    s.login(str(c['user']), str(c['pass']))
    return s


def build(it: dict, c: dict) -> EmailMessage:
    sender = str(c['from'])
    m = EmailMessage()
    m['From'] = formataddr((str(it.get('from_name') or ''), sender))
    m['To'] = str(it['to'])
    m['Reply-To'] = sender
    m['Subject'] = str(it.get('subject') or '')
    m['Date'] = formatdate(localtime=False)
    m['Message-ID'] = make_msgid(domain=sender.rpartition('@')[2] or None)
    if it.get('unsub'):
        m['List-Unsubscribe'] = '<%s>' % it['unsub']
        m['List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click'
    m.set_content(str(it.get('text') or ''))
    if it.get('html'):
        m.add_alternative(str(it['html']), subtype='html')
    return m


def main() -> int:
    raw = sys.stdin.read()
    line = next((l for l in reversed(raw.splitlines()) if l.startswith('{')), '{}')
    data = json.loads(line)
    items, creds = data.get('items') or [], data.get('creds') or {}
    results, t0 = [], time.time()
    by_uid = {}
    for it in items:
        by_uid.setdefault(str(it.get('uid')), []).append(it)
    log(f'kuyruk: {len(items)} mektup, {len(by_uid)} satici')
    for uid, its in by_uid.items():
        c = creds.get(uid)
        if not c:
            results += [{'id': it['id'], 'status': 'failed', 'reason': 'nosetup'} for it in its]
            continue
        who = mask(c.get('from', ''))
        if time.time() - t0 > BUDGET_SEC:
            results += [{'id': it['id'], 'status': 'retry', 'reason': 'sure doldu'} for it in its]
            continue
        try:
            s = connect(c)
        except smtplib.SMTPAuthenticationError as e:
            why = f'{e.smtp_code} {scrub(e.smtp_error.decode("utf-8", "replace") if isinstance(e.smtp_error, bytes) else e.smtp_error)}'
            log(f'  ! {who} @ {c.get("host")}:{c.get("port")} giris reddedildi ({e.smtp_code})')
            results += [{'id': it['id'], 'status': 'auth', 'reason': why} for it in its]
            continue
        except Exception as e:  # ağ / TLS / sunucu adı
            log(f'  ! {who} @ {c.get("host")}:{c.get("port")} baglanamadi: {type(e).__name__}')
            results += [{'id': it['id'], 'status': 'retry', 'reason': f'baglanti: {type(e).__name__}'} for it in its]
            continue
        log(f'  {who} @ {c.get("host")}:{c.get("port")} — giris OK, {len(its)} mektup')
        for k, it in enumerate(its):
            if time.time() - t0 > BUDGET_SEC:
                results.append({'id': it['id'], 'status': 'retry', 'reason': 'sure doldu'})
                continue
            msg = build(it, c)
            try:
                s.send_message(msg)
                results.append({'id': it['id'], 'status': 'sent', 'reason': '', 'message_id': msg['Message-ID']})
                log(f'    + {mask(it["to"])}')
            except smtplib.SMTPRecipientsRefused as e:
                code, txt = next(iter(e.recipients.values()), (0, b''))
                why = f'{code} {scrub(txt.decode("utf-8", "replace") if isinstance(txt, bytes) else txt)}'
                results.append({'id': it['id'], 'status': 'bounced' if int(code) >= 500 else 'retry', 'reason': why})
                log(f'    x {mask(it["to"])} — alici reddi {code}')
            except smtplib.SMTPServerDisconnected:
                results.append({'id': it['id'], 'status': 'retry', 'reason': 'sunucu baglantiyi kesti'})
                log(f'    ~ {mask(it["to"])} — baglanti koptu, kalanlar sonraki kosuda')
                results += [{'id': x['id'], 'status': 'retry', 'reason': 'baglanti koptu'} for x in its[k + 1:]]
                break
            except smtplib.SMTPResponseException as e:
                code = int(e.smtp_code)
                results.append({'id': it['id'], 'status': 'failed' if code >= 500 else 'retry', 'reason': f'{code} {scrub(e.smtp_error)}'})
                log(f'    x {mask(it["to"])} — sunucu {code}')
                if code in (421, 450, 451, 452, 454):          # hız sınırı: bu satıcıyı şimdilik bırak
                    results += [{'id': x['id'], 'status': 'retry', 'reason': f'sunucu {code}'} for x in its[k + 1:]]
                    break
            except Exception as e:
                results.append({'id': it['id'], 'status': 'retry', 'reason': type(e).__name__})
                log(f'    ~ {mask(it["to"])} — {type(e).__name__}')
            if k + 1 < len(its):
                time.sleep(random.uniform(*PER_MSG_PAUSE))
        try:
            s.quit()
        except Exception:
            pass
    print(json.dumps({'results': results}))
    n = {}
    for r in results:
        n[r['status']] = n.get(r['status'], 0) + 1
    log('BITTI: ' + ', '.join(f'{k} {v}' for k, v in sorted(n.items())) if n else 'BITTI: kuyruk bos')
    return 0


if __name__ == '__main__':
    sys.exit(main())
