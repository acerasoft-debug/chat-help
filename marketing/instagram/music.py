import numpy as np, wave, struct

SR = 44100
DUR = 44.0
N = int(SR*DUR)
t = np.arange(N)/SR

def f(m): return 440.0*2**((m-69)/12.0)

# progression A - E - F#m - D (warm I-V-vi-IV in A major), 4s per chord
PAD = [[57,61,64],[56,59,64],[54,57,61],[57,62,66]]   # smooth voicings
BASS= [45,40,42,38]                                    # A2 E2 F#2 D2
SEQ = [0,1,2,3,0,1,2,3,0,1,2]                          # 11 chords over 44s
CHORD = 4.0

L = np.zeros(N); R = np.zeros(N)

def env(n, a, r):
    e = np.ones(n)
    ai = int(a*SR); ri = int(r*SR)
    if ai>0: e[:ai] = np.linspace(0,1,ai)
    if ri>0: e[-ri:] = np.linspace(1,0,ri)
    return e

# ---- PAD (sustained chords, soft, slight detune for stereo width) ----
for i,ci in enumerate(SEQ):
    s = int(i*CHORD*SR); e = min(int((i*CHORD+CHORD+0.6)*SR), N)  # overlap for smooth crossfade
    n = e-s
    if n<=0: continue
    tt = np.arange(n)/SR
    en = env(n, 0.6, 0.9)*0.16
    for m in PAD[ci]:
        fr = f(m)
        wave_l = np.sin(2*np.pi*fr*tt) + 0.5*np.sin(2*np.pi*fr*1.004*tt) + 0.22*np.sin(2*np.pi*2*fr*tt)
        wave_r = np.sin(2*np.pi*fr*1.004*tt) + 0.5*np.sin(2*np.pi*fr*tt) + 0.22*np.sin(2*np.pi*2*fr*1.002*tt)
        L[s:e] += en*wave_l; R[s:e] += en*wave_r

# ---- BASS (root, low, soft) ----
for i,ci in enumerate(SEQ):
    s=int(i*CHORD*SR); e=min(int((i*CHORD+CHORD+0.2)*SR),N); n=e-s
    if n<=0: continue
    tt=np.arange(n)/SR; en=env(n,0.15,0.4)*0.30
    fr=f(BASS[ci])
    b=(np.sin(2*np.pi*fr*tt)+0.3*np.sin(2*np.pi*2*fr*tt))*en
    L[s:e]+=b; R[s:e]+=b

# ---- ARP (gentle bell, chord tones one octave up, every 0.5s) ----
step=0.5; alen=0.62
pat=[0,1,2,1]  # index into chord tones, cycling
k=0
tta=np.arange(int(alen*SR))/SR
bell_env=np.exp(-tta/0.19); bell_env[:int(0.005*SR)]*=np.linspace(0,1,int(0.005*SR))
tnow=0.4
while tnow<DUR-0.5:
    ci=SEQ[min(int(tnow//CHORD),len(SEQ)-1)]
    tone=PAD[ci][pat[k%len(pat)]]+12
    fr=f(tone)
    s=int(tnow*SR); e=min(s+len(tta),N); n=e-s
    bell=(np.sin(2*np.pi*fr*tta[:n])+0.4*np.sin(2*np.pi*2*fr*tta[:n])+0.15*np.sin(2*np.pi*3*fr*tta[:n]))*bell_env[:n]*0.15
    # subtle stereo placement alternating
    pan=0.5+0.35*((k%2)*2-1)*0.0  # keep centered-ish
    L[s:e]+=bell*(0.62); R[s:e]+=bell*(0.62)
    k+=1; tnow+=step

# ---- master: soft clip, global fade in/out, normalize ----
mix=np.stack([L,R],axis=1)
mix*= env(N,1.6,2.4).reshape(-1,1)              # gentle intro/outro fade
peak=np.max(np.abs(mix)); mix=mix/max(peak,1e-6)*0.62
mix=np.tanh(mix*1.1)*0.9                          # soft saturation for warmth

# write 16-bit stereo wav
i16=np.clip(mix,-1,1)
i16=(i16*32767).astype('<i2')
with wave.open('/tmp/claude-0/-home-user-chat-help/ba6cebce-fb42-5f6c-bfb9-1e63e508c3b5/scratchpad/render/tts/music.wav','wb') as w:
    w.setnchannels(2); w.setsampwidth(2); w.setframerate(SR)
    w.writeframes(i16.tobytes())
print('music.wav written', mix.shape, 'peak', float(np.max(np.abs(mix))))
