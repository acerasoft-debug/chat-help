"""Prüft Piper-Stimmen für die Assistenten: Lizenz (MODEL_CARD), mittlere Tonhöhe (F0) und Sprechtempo je Stimme/Sprecher.
Läuft auf einem GitHub-Runner (Hugging Face erreichbar). Ausgabe: Tabelle + Hörproben als Artefakt.
Aufruf: python check.py <ausgabeordner>"""
import json, os, statistics, sys, urllib.request, wave

import parselmouth
from piper import PiperVoice, SynthesisConfig

HF = 'https://huggingface.co/rhasspy/piper-voices/resolve/main/'
OUT = sys.argv[1] if len(sys.argv) > 1 else 'out'
os.makedirs(OUT, exist_ok=True)
TEXT = {
    'de': 'Guten Tag, schön, dass Sie da sind. Ich habe Ihren Termin für Donnerstag um halb zehn eingetragen.',
    'en': 'Good morning, lovely to hear from you. I have booked your appointment for Thursday at half past nine.',
    'fr': 'Bonjour, ravi de vous entendre. J’ai noté votre rendez-vous pour jeudi à neuf heures et demie.',
    'es': 'Buenos días, encantada de saludarle. He reservado su cita para el jueves a las nueve y media.',
    'tr': 'Merhaba, sizi duymak çok güzel. Randevunuzu perşembe saat dokuz buçuğa kaydettim.',
    'ru': 'Добрый день, рада вас слышать. Я записала вас на четверг на половину десятого.',
    'it': 'Buongiorno, che piacere sentirla. Ho fissato il suo appuntamento per giovedì alle nove e mezza.',
    'nl': 'Goedemorgen, fijn dat u belt. Ik heb uw afspraak voor donderdag om half tien genoteerd.',
    'sv': 'God morgon, vad roligt att höra av dig. Jag har bokat din tid på torsdag klockan halv tio.',
    'no': 'God morgen, så hyggelig å høre fra deg. Jeg har booket timen din på torsdag klokken halv ti.',
    'fi': 'Hyvää huomenta, mukava kuulla sinusta. Varasin ajan torstaille puoli kymmeneltä.',
}
# (Stimme, Sprecher – None = alle bzw. einzelner Sprecher, Liste = genau diese, Zahl = so viele gleichmäßig verteilt)
CANDIDATES = [
    # gewählte Stimmen (Hörproben bleiben) + offene Fragen: Türkisch fahrettin/fettah (fehlen in voices.json), Spanisch mls langsam?, es_MX
    ('de_DE-kerstin-low', None), ('de_DE-ramona-low', None), ('de_DE-eva_k-x_low', None), ('de_DE-mls-medium', ['589', '11480', '5675']),
    ('de_DE-thorsten-medium', None), ('de_DE-karlsson-low', None),
    ('fr_FR-siwis-medium', None), ('fr_FR-upmc-medium', ['jessica', 'pierre']), ('fr_FR-mls-medium', ['12899', '13634', '5525', '66', '3182']),
    ('es_ES-sharvard-medium', ['M', 'F']), ('es_ES-davefx-medium', None), ('es_ES-carlfm-x_low', None), ('es_ES-mls_10246-low', None),
    ('es_ES-mls_9972-low', None), ('es_MX-ald-medium', None), ('es_MX-claude-high', None),
    ('tr_TR-fahrettin-medium', None), ('tr_TR-fettah-medium', None), ('tr_TR-dfki-medium', None),
]

def get(url, path):
    if not os.path.exists(path):
        urllib.request.urlretrieve(url, path)
    return path

def smap_known(meta):
    return bool(meta.get('speaker_id_map')) or bool(meta['files'][next(iter(meta['files']))])

def f0_and_rate(wav_path, text):
    snd = parselmouth.Sound(wav_path)
    pitch = snd.to_pitch(time_step=0.01, pitch_floor=60, pitch_ceiling=420)
    vals = [v for v in pitch.selected_array['frequency'] if v > 0]
    f0 = statistics.median(vals) if vals else 0
    voiced = len(vals) * 0.01
    return round(f0), '%.1f/%.1f s' % (snd.duration, voiced)

voices = json.load(urllib.request.urlopen(HF + 'voices.json'))
rows = []
for key, spk in CANDIDATES:
    meta = voices.get(key)
    if not meta:
        fam = key[:2]; loc, name, q = key.split('-')[0], key.split('-')[1], key.split('-')[2]
        meta = {'files': {'%s/%s/%s/%s/%s.onnx' % (fam, loc, name, q, key): {}}, 'language': {'family': fam}, 'speaker_id_map': {}}
        print('nicht in voices.json, versuche Pfad:', key, flush=True)
    onnx = next(p for p in meta['files'] if p.endswith('.onnx'))
    base = os.path.dirname(onnx)
    lang = meta['language']['family']
    card = ''
    try:
        card = urllib.request.urlopen(HF + base + '/MODEL_CARD').read().decode('utf-8', 'replace')
    except Exception as e:  # noqa: BLE001
        card = 'MODEL_CARD fehlt: %s' % e
    lic = ' | '.join(l.strip() for l in card.splitlines() if 'icen' in l.lower() or 'http' in l.lower())[:300]
    mdir = os.path.join('/tmp/models')
    os.makedirs(mdir, exist_ok=True)
    try:
        mp = get(HF + onnx, os.path.join(mdir, key + '.onnx'))
        cp = get(HF + onnx + '.json', mp + '.json')
    except Exception as e:  # noqa: BLE001
        print('FEHLT', key, e, flush=True); continue
    if not smap_known(meta):
        meta['speaker_id_map'] = json.load(open(cp)).get('speaker_id_map') or {}
    v = PiperVoice.load(mp, config_path=cp)
    smap = meta.get('speaker_id_map') or {}
    if not smap:
        spk_list = [(None, None)]
    elif isinstance(spk, list):
        spk_list = [(s, smap[s]) for s in spk if s in smap]
    else:
        names = sorted(smap, key=lambda s: smap[s])
        n = spk or len(names)
        step = max(1, len(names) // n)
        spk_list = [(s, smap[s]) for s in names[::step][:n]]
    for sname, sid in spk_list:
        tag = key + ('#' + sname if sname is not None else '')
        wp = os.path.join(OUT, tag.replace('#', '__') + '.wav')
        with wave.open(wp, 'wb') as wf:
            v.synthesize_wav(TEXT[lang], wf, syn_config=SynthesisConfig(speaker_id=sid))
        f0, rate = f0_and_rate(wp, TEXT[lang])
        rows.append({'voice': key, 'speaker': sname, 'sid': sid, 'lang': lang, 'f0': f0, 'cps': rate, 'size': os.path.getsize(mp), 'license': lic})
        print('%-48s f0=%3d Hz  Dauer/stimmhaft %s' % (tag, f0, rate), flush=True)
        if sname is not None and isinstance(spk, int):
            os.remove(wp)  # viele Sprecher: nur die Tabelle behalten
    print('  Lizenz:', lic or '(keine Angabe)', flush=True)
    os.remove(mp)
json.dump(rows, open(os.path.join(OUT, 'voices.json'), 'w'), ensure_ascii=False, indent=1)
