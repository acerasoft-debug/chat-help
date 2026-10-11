"""Salt-okunur: support@ kutusundaki teslim hatası bildirimlerinin BİÇİMİNİ gösterir (11 Eki 2026, operatör:
"neden böyle hata e-mailleri geliyor" — "Message Delivery Failure - Mail Delivery System").
Hiçbir mektubu taşımaz, işaretlemez, silmez (BODY.PEEK, readonly select). Depo herkese açık: adresler maskeli,
gövdeden yalnız hata satırları (ilk 160 karakter) basılır."""
import email, imaplib, os, re, ssl, sys, time
from email.header import decode_header, make_header

ADDR = re.compile(r'([A-Za-z0-9._%+\-])[A-Za-z0-9._%+\-]*@([A-Za-z0-9.\-]+\.[A-Za-z]{2,})')
def mask(s): return ADDR.sub(lambda m: m.group(1) + '***@' + m.group(2), s)
HINT = re.compile(r'(?i)delivery status notification|failed permanently|undeliver|returned to sender|could not be delivered|'
                  r'delivery (has )?failed|failure notice|mail delivery (system|subsystem)|mailer-daemon|multipart/report|message delivery failure|'
                  r'nicht zustellbar|non remis|non recapitato|no se pudo entregar')
ERRLINE = re.compile(r'(?i)(5\.\d\.\d|4\.\d\.\d|\b5\d\d\b|\b4\d\d\b|user unknown|does not exist|no such user|mailbox (full|unavailable|not found)|'
                     r'rejected|blocked|spam|policy|reputation|dkim|spf|dmarc|quota|recipient|address|final-recipient|action:|status:|diagnostic-code)')

user, pw, days = 'support@vestrasales.com', os.environ['MAILBOX_PASS'], int(os.environ.get('DAYS', '7'))
since = time.strftime('%d-%b-%Y', time.gmtime(time.time() - days * 86400))
for host in [h for h in os.environ.get('HOSTS', 'imap.secureserver.net imap.titan.email').split() if h]:
    print(f'\n######## {host}')
    try:
        m = imaplib.IMAP4_SSL(host, 993, ssl_context=ssl.create_default_context(), timeout=30); m.login(user, pw)
    except Exception as e:
        print(f'  giris olmadi: {type(e).__name__}: {str(e)[:120]}'); continue
    typ, data = m.list(); boxes = []
    for line in data or []:
        t = line.decode('utf-8', 'replace') if isinstance(line, bytes) else str(line)
        mm = re.search(r'"([^"]+)"\s*$', t) or re.search(r'\s(\S+)\s*$', t)
        if mm: boxes.append(mm.group(1))
    print('  klasorler:', ', '.join(boxes))
    shown = 0; reasons = {}
    for box in boxes:
        if m.select(f'"{box}"', readonly=True)[0] != 'OK': continue
        typ, d = m.uid('SEARCH', None, f'(SINCE {since})'); uids = (d[0] or b'').split()
        hits = 0
        for uid in uids[-600:]:
            typ, fd = m.uid('FETCH', uid, '(BODY.PEEK[])')
            raw = fd[0][1] if fd and isinstance(fd[0], tuple) else b''
            head = raw[:30000].decode('utf-8', 'replace')
            if not HINT.search(head): continue
            hits += 1
            msg = email.message_from_bytes(raw)
            subj = str(make_header(decode_header(msg.get('Subject', ''))))
            body = ''
            for p in msg.walk():
                if p.get_content_maintype() in ('text', 'message'):
                    try: body += (p.get_payload(decode=True) or b'').decode(p.get_content_charset() or 'utf-8', 'replace') + '\n'
                    except Exception: body += str(p.get_payload()) + '\n'
            errs = [mask(l.strip())[:160] for l in body.splitlines() if ERRLINE.search(l) and l.strip()][:8]
            key = re.sub(r'\d', '#', ' | '.join(e for e in errs if re.search(r'(?i)5\.\d|55\d|rejected|unknown|exist|spam|policy|dkim|blocked|quota', e))[:120])
            reasons[key] = reasons.get(key, 0) + 1
            if shown < 6:
                shown += 1
                print(f'\n  --- [{box}] {msg.get("Date","")[:31]} · From: {mask(msg.get("From",""))} · Konu: {mask(subj)[:90]}')
                print('      parcalar:', ', '.join(p.get_content_type() for p in msg.walk()))
                for e in errs: print('      >', e)
        print(f'  [{box}] son {days} gun: {len(uids)} mektup, {hits} hata bildirimi')
    print('\n  == hata turleri (sayi) ==')
    for k, v in sorted(reasons.items(), key=lambda x: -x[1])[:15]: print(f'  {v:4d} × {k or "(hata satiri bulunamadi)"}')
    m.logout()
