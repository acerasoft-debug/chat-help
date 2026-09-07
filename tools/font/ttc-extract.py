#!/usr/bin/env python3
"""TTC koleksiyonundan TEK yuzu duz TTF olarak cikarir ve fatura icin gereksiz
tablolari atar.

Neden: depoya konacak yazi tipi her klonda ve her deploy'da tasiniyor. Dikey
metrikler (vmtx/vhea), gomulu bitmap'ler, glif ADLARI (post) ve OpenType
yerlestirme tablolari (GSUB/GPOS/GDEF) bir faturanin cizimine hicbir sey
katmiyor -- alt kume cikarici zaten yalnizca glyf/loca/head/hhea/hmtx/maxp
okuyor, cmap de unicode->glif esleme icin gerekiyor.

Kullanim: ttc-extract.py <girdi.ttc|ttf> <cikti.ttf> [yuz-no]
"""
import struct, sys

KEEP = {b'cmap', b'glyf', b'loca', b'head', b'hhea', b'hmtx', b'maxp',
        b'OS/2', b'cvt ', b'fpgm', b'prep', b'name'}

def main(src, dst, face=0):
    d = open(src, 'rb').read()
    base = 0
    if d[:4] == b'ttcf':
        n = struct.unpack('>I', d[8:12])[0]
        if face >= n: raise SystemExit(f'yuz {face} yok (toplam {n})')
        base = struct.unpack('>%dI' % n, d[12:12 + 4 * n])[face]
    sfnt, numTables = struct.unpack('>IH', d[base:base + 6])
    tabs = []
    for i in range(numTables):
        o = base + 12 + 16 * i
        tag = d[o:o + 4]
        cks, off, ln = struct.unpack('>III', d[o + 4:o + 16])
        if tag in KEEP:
            tabs.append((tag, cks, d[off:off + ln], ln))
    tabs.sort(key=lambda t: t[0])

    n = len(tabs)
    sr = 1
    while sr * 2 <= n: sr *= 2
    searchRange = sr * 16
    entrySelector = sr.bit_length() - 1
    rangeShift = n * 16 - searchRange

    head = struct.pack('>IHHHH', sfnt, n, searchRange, entrySelector, rangeShift)
    off = len(head) + 16 * n
    dirs, body = b'', b''
    for tag, cks, data, ln in tabs:
        pad = (-len(data)) % 4
        dirs += struct.pack('>4sIII', tag, cks, off, ln)
        body += data + b'\0' * pad
        off += len(data) + pad
    out = head + dirs + body
    open(dst, 'wb').write(out)
    print(f'{src} -> {dst}: {len(tabs)} tablo, {len(out)} bayt '
          f'(kaynak {len(d)}), atilan: ' +
          ','.join(sorted({t.decode("latin1").strip() for t in
                           [d[base+12+16*i:base+12+16*i+4] for i in range(numTables)]} -
                          {t.decode("latin1").strip() for t, _, _, _ in tabs})))

if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2], int(sys.argv[3]) if len(sys.argv) > 3 else 0)
