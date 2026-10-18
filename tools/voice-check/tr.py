"""Türkische Kandidaten: Tonhöhe + SHA-256 (für voice-fetch), Quelle je Modell."""
import hashlib, json, os, statistics, sys, urllib.request, wave
import parselmouth
from piper import PiperVoice, SynthesisConfig
OUT = sys.argv[1] if len(sys.argv) > 1 else 'out'; os.makedirs(OUT, exist_ok=True)
TEXT = 'Merhaba, sizi duymak çok güzel. Randevunuzu perşembe saat dokuz buçuğa kaydettim.'
DS = 'https://huggingface.co/diffusionstudio/piper-voices/resolve/main/tr/tr_TR/'
C = [('tr_TR-fahrettin-medium', DS + 'fahrettin/medium/tr_TR-fahrettin-medium.onnx', DS + 'fahrettin/medium/tr_TR-fahrettin-medium.onnx.json'),
     ('tr_TR-fettah-medium', DS + 'fettah/medium/tr_TR-fettah-medium.onnx', DS + 'fettah/medium/tr_TR-fettah-medium.onnx.json')]
api = json.load(urllib.request.urlopen('https://huggingface.co/api/models/dcx514ai/piper_tts_turkish_high'))
print('dcx514ai high:', [s['rfilename'] for s in api.get('siblings', [])], [t for t in api.get('tags', []) if 'licen' in t])
sib = [s['rfilename'] for s in api.get('siblings', [])]
js = next((f for f in sib if f.endswith('.json') and 'config' in f.lower() or f.endswith('.onnx.json')), None)
if js and 'last.onnx' in sib:
    B = 'https://huggingface.co/dcx514ai/piper_tts_turkish_high/resolve/main/'
    C.append(('tr_TR-dcx514ai-high', B + 'last.onnx', B + js))
try:
    print('README dcx:', urllib.request.urlopen('https://huggingface.co/dcx514ai/piper_tts_turkish_high/raw/main/README.md').read().decode()[:600].replace('\n', ' | '))
except Exception as e: print('README dcx fehlt', e)
for key, uo, uj in C:
    try:
        mp, cp = '/tmp/' + key + '.onnx', '/tmp/' + key + '.onnx.json'
        urllib.request.urlretrieve(uo, mp); urllib.request.urlretrieve(uj, cp)
        v = PiperVoice.load(mp, config_path=cp)
        wp = os.path.join(OUT, key + '.wav')
        with wave.open(wp, 'wb') as wf: v.synthesize_wav(TEXT, wf, syn_config=SynthesisConfig())
        snd = parselmouth.Sound(wp); vals = [x for x in snd.to_pitch(0.01, 60, 420).selected_array['frequency'] if x > 0]
        sha = lambda p: hashlib.sha256(open(p, 'rb').read()).hexdigest()
        print('%-28s f0=%3d Hz  %.1f/%.1f s  onnx=%s %d  json=%s' % (key, statistics.median(vals), snd.duration, len(vals) * .01, sha(mp), os.path.getsize(mp), sha(cp)), flush=True)
        print('   espeak:', json.load(open(cp)).get('espeak'), 'speakers:', json.load(open(cp)).get('num_speakers'))
    except Exception as e: print('FEHLER', key, e)
