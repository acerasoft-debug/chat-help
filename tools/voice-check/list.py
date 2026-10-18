"""Alle aktuellen Piper-Stimmen (rhasspy/piper-voices) für die Zielsprachen mit Lizenz + Sprecherzahl; dazu weitere türkische Quellen."""
import json, urllib.request
HF = 'https://huggingface.co/rhasspy/piper-voices/resolve/main/'
v = json.load(urllib.request.urlopen(HF + 'voices.json'))
for k, m in sorted(v.items()):
    if m['language']['family'] not in ('de', 'fr', 'es', 'tr', 'az', 'en', 'it', 'nl', 'ru', 'sv', 'no', 'fi', 'ja'): continue
    base = next(p for p in m['files'] if p.endswith('.onnx')).rsplit('/', 1)[0]
    lic = ''
    try:
        card = urllib.request.urlopen(HF + base + '/MODEL_CARD', timeout=20).read().decode('utf-8', 'replace')
        lic = ' | '.join(l.strip('* ').strip() for l in card.splitlines() if 'icen' in l.lower())[:90]
    except Exception as e: lic = 'card? %s' % e
    print('%-44s spk=%-4d %s' % (k, m['num_speakers'], lic), flush=True)
for repo in ['dcx514ai/piper_tts_turkish_xlow', 'dcx514ai/piper_tts_turkish_high', '99eren99/piper-turkish-tts', 'multilingual-tts/VITS-OpenBible-Turkish', 'speaches-ai/piper-tr_TR-fahrettin-medium']:
    try:
        a = json.load(urllib.request.urlopen('https://huggingface.co/api/models/' + repo, timeout=20))
        print('REPO', repo, a.get('gated'), [t for t in a.get('tags', []) if 'licen' in t], [s['rfilename'] for s in a.get('siblings', [])][:12])
    except Exception as e: print('REPO', repo, 'FEHLER', e)
