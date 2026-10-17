"""Synthetische Porträts → einheitliche Studio-Porträts (freigestellt, warmer heller Studio-Hintergrund, 640 px WebP).
Aufruf: python3 scripts/avatar_studio.py <eingabe-glob> <ausgabe-ordner>"""
import sys, glob, os
from PIL import Image, ImageFilter, ImageDraw

W = 640
try:
    from rembg import remove, new_session
    SESSION = new_session("u2net_human_seg")
except Exception as e:  # ohne rembg: nur zuschneiden
    print("rembg nicht verfügbar:", e)
    remove = None
    SESSION = None


def backdrop():
    bg = Image.new("RGB", (W, W), (240, 234, 224))
    d = ImageDraw.Draw(bg)
    for r in range(W, 0, -4):  # weicher Studio-Verlauf, hell in der Mitte
        t = r / W
        c = (int(222 + 26 * (1 - t)), int(214 + 26 * (1 - t)), int(200 + 26 * (1 - t)))
        d.ellipse([W / 2 - r, W * 0.42 - r, W / 2 + r, W * 0.42 + r], fill=c)
    return bg.filter(ImageFilter.GaussianBlur(18))


def main(pattern, outdir):
    os.makedirs(outdir, exist_ok=True)
    for f in sorted(glob.glob(pattern)):
        try:
            im = Image.open(f).convert("RGB")
        except Exception:
            continue
        s = min(im.size)
        im = im.crop(((im.width - s) // 2, (im.height - s) // 2, (im.width + s) // 2, (im.height + s) // 2)).resize((W, W), Image.LANCZOS)
        out = im
        if remove is not None:
            try:
                cut = remove(im, session=SESSION)
                bg = backdrop().convert("RGBA")
                bg.alpha_composite(cut)
                out = bg.convert("RGB")
            except Exception as e:
                print("rembg Fehler", f, e)
        base = os.path.splitext(os.path.basename(f))[0]
        out.save(os.path.join(outdir, base + ".webp"), "WEBP", quality=86, method=6)
        im.resize((320, 320), Image.LANCZOS).save(os.path.join(outdir, base + "-orig.webp"), "WEBP", quality=78, method=6)
        print("ok", base)


if __name__ == "__main__":
    main(sys.argv[1], sys.argv[2])
