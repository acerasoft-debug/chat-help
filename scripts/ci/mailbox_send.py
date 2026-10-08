#!/usr/bin/env python3
"""VESTRA — support@vestrasales.com posta kutusundan itibarli, kotasiz gonderim (GitHub kosucusu).

Sunucumuz giden SMTP'yi kapatiyor; mektubu bu kosucu, alan adinin KENDI saglayicisinin SMTP
sunucusu uzerinden gonderir (saglayici DKIM ile imzalar). Metin ve HTML sunucuda uretilir
(scripts/server/vestra-mailbox-queue.php list) — Brevo'nun gonderdigiyle birebir ayni.

Kipler:
  --check                 YALNIZ herkese acik DNS: MX / SPF / DMARC / DKIM. Sifre kullanilmaz.
  --test-to ADRES         partideki ILK mektubu "[TEST]" onekiyle yalniz ADRES'e gonderir.
  (varsayilan)            partideki mektuplari alicilarina gonderir; sonucu --out'a yazar.

Sifre yalniz TEK bir sunucuda kullanilir: MAILBOX_SMTP_HOST (tahmin YOK). Bos ise durur.
Depo herkese acik: kayitta alici adresleri maskelenir, sifre hic basilmaz.
"""
import argparse, email, imaplib, json, os, random, re, smtplib, ssl, subprocess, sys, time
from email.message import EmailMessage
from email.utils import formataddr, formatdate, make_msgid

DOMAIN = 'vestrasales.com'
PER_SESSION = 20  # bir SMTP oturumunda en cok bu kadar mektup


def mask(addr: str) -> str:
    if '@' not in addr:
        return '***'
    user, dom = addr.split('@', 1)
    return (user[:1] + '***@' + dom) if user else '***@' + dom


def dig(name: str, rtype: str) -> list:
    try:
        out = subprocess.run(['dig', '+short', rtype, name], capture_output=True, text=True, timeout=15).stdout
    except Exception:
        return []
    return [l.strip() for l in out.splitlines() if l.strip()]


def dns_check() -> None:
    mx = dig(DOMAIN, 'MX')
    print('MX    :', ', '.join(mx) or '(yok)')
    spf = [t for t in dig(DOMAIN, 'TXT') if 'v=spf1' in t.lower()]
    print('SPF   :', ' | '.join(spf) or '(YOK — eklenmeli)')
    dmarc = dig('_dmarc.' + DOMAIN, 'TXT')
    print('DMARC :', ' | '.join(dmarc) or '(YOK — eklenmeli)')
    found = []
    for sel in ['default', 'mail', 'dkim', 'titan1', 'k1', 's1', 's2', 'google', 'selector1', 'selector2', 'zoho', 'mx', 'smtp', 'hostinger', 'brevo', 'mail1']:
        rec = dig(f'{sel}._domainkey.{DOMAIN}', 'TXT')
        if rec:
            found.append(sel)
    print('DKIM  :', ('secici: ' + ', '.join(found)) if found else '(yaygin seciciler arasinda bulunamadi — saglayici panelinden kontrol)')
    hosts = ' '.join(mx).lower()
    guess = ''
    for key, host in [('titan.email', 'smtp.titan.email'), ('secureserver.net', 'smtpout.secureserver.net'),
                      ('hostinger', 'smtp.hostinger.com'), ('zoho', 'smtp.zoho.eu'), ('outlook.com', 'smtp.office365.com'),
                      ('google.com', 'smtp.gmail.com'), ('googlemail', 'smtp.gmail.com'), ('ionos', 'smtp.ionos.de'),
                      ('privateemail', 'mail.privateemail.com'), ('mailbox.org', 'smtp.mailbox.org')]:
        if key in hosts:
            guess = host
            break
    print('Saglayicinin belgeli SMTP sunucusu (MX\'ten):', guess or '(MX bilinen bir saglayici degil — sunucu adini lemlist Sending settings\'ten alin)')


_MX_CACHE = {}


def dns_status(domain: str) -> str:
    """'ok' (MX ya da A var) | 'dead' (alan adi yok / kayit yok) | 'unknown' (sorgu basarisiz).
    Zaman asimi ya da SERVFAIL 'dead' SAYILMAZ: yanlislikla 'geri dondu' damgasi kalici olurdu."""
    d = domain.lower().strip('.')
    if d in _MX_CACHE:
        return _MX_CACHE[d]

    def q(rtype):
        try:
            out = subprocess.run(['dig', '+time=4', '+tries=2', '+noall', '+comments', '+answer', rtype, d],
                                 capture_output=True, text=True, timeout=30).stdout
        except Exception:
            return 'FAIL', []
        st = re.search(r'status:\s*([A-Z]+)', out)
        ans = [l.split() for l in out.splitlines() if l and not l.startswith(';')]
        return (st.group(1) if st else 'FAIL'), [a for a in ans if len(a) >= 5 and a[3] == rtype]

    st, mx = q('MX')
    if st == 'NOERROR' and mx and all(a[-1] == '.' for a in mx):
        res = 'dead'  # null MX (RFC 7505): alan adi bilerek e-posta kabul etmez
    elif st == 'NOERROR' and any(a[-1] != '.' for a in mx):
        res = 'ok'
    elif st == 'NXDOMAIN':
        res = 'dead'
    elif st == 'NOERROR':
        st2, a = q('A')
        res = 'ok' if (st2 == 'NOERROR' and a) else ('dead' if st2 in ('NOERROR', 'NXDOMAIN') else 'unknown')
    else:
        res = 'unknown'
    _MX_CACHE[d] = res
    return res


def has_mail_server(domain: str) -> bool:
    return dns_status(domain) != 'dead'


_ADDR = re.compile(r'[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}')


def dsn_failed_recipients(msg, own: str) -> list:
    """Kalici teslim hatasi bildirimindeki (DSN) alicilar. Standart multipart/report
    (Final-Recipient + Action: failed / Status 5.x.x) ya da GoDaddy'nin duz metni
    ("failed permanently: * adres"). Gecici (4.x.x) hatalar ALINMAZ."""
    out = []
    for part in msg.walk():
        if part.get_content_type() == 'message/delivery-status':
            payload = part.get_payload()
            blocks = payload if isinstance(payload, list) else [part]
            for b in blocks:
                txt = b.as_string() if hasattr(b, 'as_string') else str(b)
                rcpt = re.search(r'(?im)^Final-Recipient:\s*[^;]*;\s*(\S+)', txt) or re.search(r'(?im)^Original-Recipient:\s*[^;]*;\s*(\S+)', txt)
                act = re.search(r'(?im)^Action:\s*(\S+)', txt)
                st = re.search(r'(?im)^Status:\s*(\d)\.', txt)
                if rcpt and ((act and act.group(1).lower() == 'failed') or (st and st.group(1) == '5')):
                    out.append(rcpt.group(1).strip('<>'))
    if not out:
        body = ''
        for part in msg.walk():
            if part.get_content_type() == 'text/plain':
                try:
                    body += part.get_payload(decode=True).decode(part.get_content_charset() or 'utf-8', 'replace')
                except Exception:
                    pass
        if re.search(r'(?i)failed permanently|permanent(ly)? (error|failure)|could not be delivered|user unknown|does not exist|DNSNULL|5\.\d\.\d', body):
            m = re.search(r'(?is)(?:recipients?|address(?:es)?)[^:\n]*:\s*(.{0,400})', body)
            zone = m.group(1) if m else body[:600]
            out = _ADDR.findall(zone)
    own = own.lower()
    return sorted({a.lower() for a in out if a.lower() != own and not a.lower().startswith(('mailer-daemon@', 'postmaster@'))})


DSN_HINT = re.compile(r'(?i)delivery status notification|failed permanently|undeliver|returned to sender|could not be delivered|'
                      r'delivery (has )?failed|failure notice|mail delivery (system|subsystem)|mailer-daemon|multipart/report|'
                      r'nicht zustellbar|non remis|non recapitato|no se pudo entregar')


def _folders(m) -> list:
    """INBOX + istenmeyen/spam klasorleri (bildirimler bazen oraya duser)."""
    out = ['INBOX']
    typ, data = m.list()
    for line in data or []:
        t = line.decode('utf-8', 'replace') if isinstance(line, bytes) else str(line)
        mm = re.search(r'"([^"]+)"\s*$', t) or re.search(r'\s(\S+)\s*$', t)
        name = mm.group(1) if mm else ''
        if name and name != 'INBOX' and re.search(r'(?i)junk|spam|bulk', name):
            out.append(name)
    return out


def scan_bounces(user: str, pw: str, host: str, days: int, move_to: str) -> list:
    """support@ kutusundaki (gelen + istenmeyen) kalici teslim hatasi bildirimlerini okur, hatali
    alicilari dondurur ve islenen bildirimleri move_to klasorune tasir. Yalniz bildirimlere dokunur.
    Depo herkese acik: yalniz sayilar ve maskeli alici basilir."""
    ctx = ssl.create_default_context()
    m = imaplib.IMAP4_SSL(host, 993, ssl_context=ctx, timeout=30)
    m.login(user, pw)
    caps = {c.decode().upper() if isinstance(c, bytes) else str(c).upper() for c in (m.capabilities or ())}
    folder = ''
    if move_to:
        for cand in (move_to, 'INBOX.' + move_to):
            m.create(cand)  # varsa NO doner, sorun degil
            if m.select(cand, readonly=True)[0] == 'OK':
                folder = cand
                break
    since = time.strftime('%d-%b-%Y', time.gmtime(time.time() - days * 86400))
    found, scanned, hinted, moved, marked = [], 0, 0, 0, 0
    for box in _folders(m):
        if box == folder or m.select(box)[0] != 'OK':
            continue
        typ, data = m.uid('SEARCH', None, f'(SINCE {since})')
        uids = (data[0] or b'').split()[-400:]
        done = []
        for uid in uids:
            scanned += 1
            typ, fd = m.uid('FETCH', uid, '(BODY.PEEK[])')
            raw = fd[0][1] if fd and isinstance(fd[0], tuple) else b''
            if not DSN_HINT.search(raw[:20000].decode('utf-8', 'replace')):
                continue
            hinted += 1
            rcpts = dsn_failed_recipients(email.message_from_bytes(raw), user)
            if rcpts:
                found += [{'email': a, 'status': 'bounced', 'reason': 'teslim edilemedi (DSN)'} for a in rcpts]
                done.append(uid)
        # YALNIZ bu bildirimler tasinir. Duz EXPUNGE kullanilmaz: kullanicinin "silindi" isaretli baska
        # mektuplarini da kalici silerdi. MOVE (RFC 6851) ya da UIDPLUS'li UID EXPUNGE; ikisi de yoksa
        # bildirim yerinde kalir, yalniz okundu isaretlenir.
        for uid in done:
            if folder and 'MOVE' in caps:
                ok = m.uid('MOVE', uid, folder)[0] == 'OK'
            elif folder and 'UIDPLUS' in caps and m.uid('COPY', uid, folder)[0] == 'OK':
                m.uid('STORE', uid, '+FLAGS', '(\\Deleted)')
                ok = m.uid('EXPUNGE', uid)[0] == 'OK'
            else:
                m.uid('STORE', uid, '+FLAGS', '(\\Seen)')
                ok = False
            moved += 1 if ok else 0
            marked += 0 if ok else 1
    m.logout()
    uniq = {}
    for f in found:
        uniq[f['email']] = f
    print(f'bildirim tarandi: {scanned} mektup · bildirime benzeyen {hinted} · hatali adres {len(uniq)}'
          + (f' · {moved} bildirim "{folder}" klasorune tasindi' if moved else '') + (f' · {marked} okundu isaretlendi' if marked else ''))
    for a in sorted(uniq):
        print('  x', mask(a))
    return list(uniq.values())


def connect(host: str, port: int, user: str, pw: str):
    ctx = ssl.create_default_context()
    if port == 465:
        s = smtplib.SMTP_SSL(host, port, timeout=30, context=ctx)
    else:
        s = smtplib.SMTP(host, port, timeout=30)
        s.ehlo()
        s.starttls(context=ctx)
        s.ehlo()
    s.login(user, pw)
    return s


def build(item: dict, from_name: str, sender: str, to: str, subject_prefix: str = '') -> EmailMessage:
    m = EmailMessage()
    m['From'] = formataddr((from_name, sender))
    m['To'] = to
    m['Subject'] = subject_prefix + item['subject']
    m['Date'] = formatdate(localtime=False)
    m['Message-ID'] = make_msgid(domain=DOMAIN)
    m['Reply-To'] = sender
    unsub = item.get('listUnsub') or f'https://{DOMAIN}/lead-unsubscribe'
    m['List-Unsubscribe'] = f'<{unsub}>, <mailto:{sender}?subject=unsubscribe>'
    m['List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click'
    m.set_content(item.get('text') or '')
    if item.get('html'):
        m.add_alternative(item['html'], subtype='html')
    return m


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument('--batch', default='batch.json')
    ap.add_argument('--out', default='results.json')
    ap.add_argument('--check', action='store_true')
    ap.add_argument('--test-to', default='')
    ap.add_argument('--check-domains', default='')
    ap.add_argument('--scan-bounces', action='store_true')
    ap.add_argument('--bounce-days', type=int, default=7)
    ap.add_argument('--min-gap', type=float, default=25.0)
    ap.add_argument('--max-gap', type=float, default=55.0)
    a = ap.parse_args()

    if a.check:
        dns_check()
        for d in [x.strip() for x in a.check_domains.split(',') if x.strip()]:
            print(f'  {d:32s} {dns_status(d)}')
        return 0

    host = os.environ.get('MAILBOX_SMTP_HOST', '').strip()
    port = int(os.environ.get('MAILBOX_SMTP_PORT', '587') or 587)
    user = os.environ.get('MAILBOX_USER', 'support@' + DOMAIN).strip()
    pw = os.environ.get('MAILBOX_PASS', '')
    if not host:
        print('DUR: MAILBOX_SMTP_HOST bos. Sunucu adi tahmin edilmez; once --check ile bakin ya da lemlist Sending settings\'teki SMTP host\'u girin.')
        return 2
    if not pw:
        print('DUR: MAILBOX_PASS secret\'i yok.')
        return 2

    if a.scan_bounces:
        imap_host = os.environ.get('MAILBOX_IMAP_HOST', '').strip() or 'imap.secureserver.net'
        try:
            res = scan_bounces(user, pw, imap_host, a.bounce_days, 'VESTRA-Bounces')
        except Exception as e:
            print(f'bildirim taranamadi ({imap_host}): {type(e).__name__}: {str(e)[:120]}')
            res = []
        json.dump({'results': res}, open(a.out, 'w'))
        return 0

    batch = json.load(open(a.batch, encoding='utf-8'))
    items = batch.get('items') or []
    from_name = batch.get('from') or 'VESTRA'
    sender = batch.get('sender_email') or user
    if not items:
        print('Gonderilecek uygun musteri yok.')
        json.dump({'results': []}, open(a.out, 'w'))
        return 0

    try:
        s = connect(host, port, user, pw)
    except smtplib.SMTPAuthenticationError as e:
        print(f'GIRIS REDDEDILDI ({host}:{port}): {e.smtp_code} — sifre ya da kullanici adi yanlis.')
        return 3
    except Exception as e:
        print(f'BAGLANTI KURULAMADI ({host}:{port}): {type(e).__name__}: {str(e)[:120]}')
        return 3
    print(f'GIRIS OK: {host}:{port} ({mask(user)})')
    json.dump({'results': [], 'host': host, 'port': port}, open(a.out, 'w'))

    if a.test_to:
        it = items[0]
        msg = build(it, from_name, sender, a.test_to, '[TEST] ')
        s.send_message(msg)
        s.quit()
        print(f'TEST GONDERILDI -> {mask(a.test_to)} | ornek: {it.get("company","")} [{it.get("lang","")}] | Message-ID {msg["Message-ID"]}')
        json.dump({'results': [], 'test': True, 'host': host, 'port': port}, open(a.out, 'w'))
        return 0

    results, consecutive_fail, since_login = [], 0, 0
    for n, it in enumerate(items, 1):
        to = it['email']
        msg = build(it, from_name, sender, to)
        r = {'leadId': it.get('leadId', ''), 'email': to, 'lang': it.get('lang', ''), 'messageId': msg['Message-ID']}
        if dns_status(to.rsplit('@', 1)[-1]) == 'dead':
            r.update(status='bounced', reason='alan adinin e-posta sunucusu yok (DNS) — gonderilmedi')
            results.append(r)
            json.dump({'results': results, 'host': host, 'port': port}, open(a.out, 'w'))
            print(f'  - [{n}/{len(items)}] {mask(to)} atlandi: alan adinin e-posta sunucusu yok')
            continue
        if since_login >= PER_SESSION:
            # GoDaddy tek oturumda ~25 mektuptan sonra 452 "too many messages in a single session" verir
            try:
                s.quit()
            except Exception:
                pass
            s = connect(host, port, user, pw)
            since_login = 0
        try:
            try:
                refused = s.send_message(msg)
            except smtplib.SMTPResponseException as e:
                if not (400 <= e.smtp_code < 500):
                    raise
                # gecici red: oturumu yenile, bir kez daha dene
                try:
                    s.quit()
                except Exception:
                    pass
                time.sleep(20)
                s = connect(host, port, user, pw)
                since_login = 0
                refused = s.send_message(msg)
            since_login += 1
            if refused:
                r.update(status='bounced', reason=str(refused)[:120])
            else:
                r['status'] = 'sent'
            consecutive_fail = 0
        except smtplib.SMTPRecipientsRefused as e:
            r.update(status='bounced', reason=str(e.recipients)[:120])
        except smtplib.SMTPResponseException as e:
            r.update(status='bounced' if 500 <= e.smtp_code < 600 else 'failed', reason=f'{e.smtp_code} {str(e.smtp_error)[:100]}')
            consecutive_fail += 1
        except (smtplib.SMTPServerDisconnected, OSError) as e:
            r.update(status='failed', reason=type(e).__name__)
            consecutive_fail += 1
            try:
                s = connect(host, port, user, pw)
            except Exception:
                results.append(r)
                print(f'  x [{n}] {mask(to)} — baglanti koptu, durduruldu')
                break
        results.append(r)
        # her mektuptan sonra kaydet: is yarida kopsa bile gidenler damgalanir, ikinci kez gitmez
        json.dump({'results': results, 'host': host, 'port': port}, open(a.out, 'w'))
        print(f'  {"+" if r["status"]=="sent" else "x"} [{n}/{len(items)}] {mask(to)} [{r["lang"]}] {r["status"]}{(" — " + r.get("reason","")) if r["status"]!="sent" else ""}')
        if consecutive_fail >= 3:
            print('  || 3 ardisik hata — saglayici sinirlamis olabilir, durduruldu (kalanlar sonraki kosuda).')
            break
        if n < len(items):
            time.sleep(random.uniform(a.min_gap, a.max_gap))
    try:
        s.quit()
    except Exception:
        pass
    json.dump({'results': results, 'host': host, 'port': port}, open(a.out, 'w'))
    sent = sum(1 for r in results if r['status'] == 'sent')
    print(f'BITTI: gonderildi {sent} · geri donen {sum(1 for r in results if r["status"]=="bounced")} · hata {sum(1 for r in results if r["status"]=="failed")}')
    return 0


if __name__ == '__main__':
    sys.exit(main())
