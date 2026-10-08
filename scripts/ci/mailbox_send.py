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
import argparse, json, os, random, smtplib, ssl, subprocess, sys, time
from email.message import EmailMessage
from email.utils import formataddr, formatdate, make_msgid

DOMAIN = 'vestrasales.com'


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
    ap.add_argument('--min-gap', type=float, default=25.0)
    ap.add_argument('--max-gap', type=float, default=55.0)
    a = ap.parse_args()

    if a.check:
        dns_check()
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

    results, consecutive_fail = [], 0
    for n, it in enumerate(items, 1):
        to = it['email']
        msg = build(it, from_name, sender, to)
        r = {'leadId': it.get('leadId', ''), 'email': to, 'lang': it.get('lang', ''), 'messageId': msg['Message-ID']}
        try:
            refused = s.send_message(msg)
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
