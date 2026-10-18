"""Sucht Türkisch/Aserbaidschanisch-Stimmen (Piper/VITS-ONNX) mit kommerziell nutzbarer Lizenz auf Hugging Face."""
import json, urllib.request, urllib.parse
def j(u):
    try: return json.load(urllib.request.urlopen(u, timeout=30))
    except Exception as e: return {'_err': str(e)}
def t(u):
    try: return urllib.request.urlopen(u, timeout=30).read().decode('utf-8', 'replace')
    except Exception as e: return 'FEHLER %s' % e
for name in ['fahrettin', 'fettah']:
    base = 'https://huggingface.co/diffusionstudio/piper-voices/resolve/main/tr/tr_TR/%s/medium/' % name
    print('==', name, 'MODEL_CARD:', t(base + 'MODEL_CARD')[:400].replace('\n', ' | '))
    req = urllib.request.Request(base + 'tr_TR-%s-medium.onnx' % name, method='HEAD')
    try: r = urllib.request.urlopen(req, timeout=30); print('   onnx', r.status, r.headers.get('Content-Length'))
    except Exception as e: print('   onnx FEHLER', e)
seen = set()
for q in ['piper tr_TR', 'piper turkish', 'tr_TR onnx', 'turkish tts onnx', 'piper azerbaijani', 'az_AZ', 'azerbaijani tts', 'turkish vits']:
    for m in j('https://huggingface.co/api/models?limit=40&full=true&search=' + urllib.parse.quote(q)) or []:
        if not isinstance(m, dict) or m.get('id') in seen: continue
        seen.add(m.get('id'))
        lic = [x for x in m.get('tags', []) if x.startswith('license:')]
        files = [s.get('rfilename') for s in m.get('siblings', []) if str(s.get('rfilename', '')).endswith('.onnx')]
        print('%-60s %-28s dl=%-6s onnx=%s' % (m.get('id'), ','.join(lic), m.get('downloads'), ','.join(files[:4])))
