#!/usr/bin/env python3
"""
KESIF RAPORU: Overture adaylari + runner olcumu -> ulke basina okunabilir liste.

Kovalar, sessiz kova YOK:
  HAZIR          site adla uyumlu + sitede yayinlanmis adres VAR (kendi alan adi ya da
                 gmail vb. ortak saglayici) + KURAL 1 temiz. add-and-send'e verilecek
                 site linkleri buradan.
  ELLE DOGRULA   site adla UYUSMUYOR (marka / rehber sitesi olabilir) ya da adres BASKA
                 bir alan adinda (ajans / eklenti olabilir, KURAL 1h) -- insan bakmadan
                 gonderim listesine girmez.
  SITE ACILMADI  GitHub makinesinden acilmadi: olu alan adi YA DA bot engeli. Ayni site
                 VESTRA sunucusundan acilabilir, o yuzden sayiya gomulmez, host olarak
                 listelenir.
  ELENEN         KURAL 1, park, servis adresi, sitede adres yok, sure doldu -- sebebiyle.

E-POSTA ADRESI BASILMAZ (runner olcumu zaten yazmiyor). Basilan her sey dukkanin
herkese acik bilgisi: ad, sehir, site alan adi.

MEKTUP ONERISI kategoriden: ayakkabi dukkani -> letter=footwear (ayakkabi bolmesi);
aksesuar / canta / sapka / butik -> letter=designer. Katalogda aksesuar YOK (KURAL 9:
/b2b/accessories 404), yani bir canta dukkanina ayakkabi mektubu "size uygun urunumuz
var" diye yanlis bir vaatle acilirdi. Oneri; karar operatorun.

add-and-send partileri <=20 link: sunucu link basina ana sayfa + iletisim/kunye
sayfalarini cekiyor ve 45 dakikalik tavan ~15-25 linkte doluyor (add-and-send.yml).
add-and-send'in send girdisi VARSAYILAN true -- ilk kosu ACIKCA send=false.
"""
import json, sys, collections

BATCH = 20
SHOE_CATS = {"shoe_store"}


def letter_for(category: str) -> str:
    return "footwear" if category in SHOE_CATS else "designer"


def bucket(c: dict) -> str:
    why = c.get("why") or ""
    if why.startswith("SITE ACILMADI"):
        return "unreachable"
    if why:
        return "gone"
    if c.get("email") != "VAR":
        return "gone"
    if not c.get("site_matches_name") or c.get("kind") == "BASKA ALAN ADI":
        return "review"
    return "ready"


def main(argv):
    if len(argv) < 3:
        print("kullanim: discover_report.py cands.json dropped.json res_*.json", file=sys.stderr)
        return 2
    cands = json.load(open(argv[0]))
    dropped = json.load(open(argv[1]))
    res = {}
    for f in argv[2:]:
        for r in json.load(open(f)):
            res[int(r["i"])] = r
    for i, c in enumerate(cands):
        r = res.get(i, {"why": "OLCULMEDI (isci bitmedi)", "email": "yok", "kind": ""})
        c["why"], c["email"], c["kind"] = r.get("why", ""), r.get("email", "yok"), r.get("kind", "")

    by_cc = collections.defaultdict(list)
    for c in cands:
        by_cc[c["cc"]].append(c)
    drop_cc = collections.defaultdict(collections.Counter)
    for d in dropped:
        drop_cc[d["cc"]][d["why"].split(" (")[0]] += 1

    tot = collections.Counter()
    for cc in sorted(set(by_cc) | set(drop_cc)):
        lst = by_cc.get(cc, [])
        name = (lst[0]["country"] if lst else cc)
        groups = collections.defaultdict(list)
        gone = collections.Counter()
        for c in lst:
            b = bucket(c)
            groups[b].append(c)
            if b == "gone":
                gone[(c["why"] or "sitede yayinlanmis adres yok").split(" (")[0]] += 1
        ready, review, unreach = groups["ready"], groups["review"], groups["unreachable"]
        tot["hazir"] += len(ready); tot["elle"] += len(review); tot["acilmadi"] += len(unreach); tot["aday"] += len(lst)
        print(f"\n===== {cc} ({name}) =====")
        pre = drop_cc.get(cc, {})
        print("Overture elemesi: " + (", ".join(f"{k} {v}" for k, v in pre.most_common()) or "-"))
        print(f"aday {len(lst)} -> HAZIR {len(ready)} | ELLE DOGRULA {len(review)} | SITE ACILMADI {len(unreach)}"
              " | olcumde elenen: " + (", ".join(f"{k} {v}" for k, v in gone.most_common()) or "-"))
        if ready:
            print("HAZIR:")
            for n, c in enumerate(ready, 1):
                sub = f" [{c['branches']} kapi]" if c.get("branches", 1) > 1 else ""
                print(f"  {n:>3}. {c['name'][:42]} | {c['city'][:22]} | {c['site']} | {c['category']} | {c['kind']}{sub}")
        if review:
            print("ELLE DOGRULA (otomatik listeye GIRMEDI):")
            for n, c in enumerate(review, 1):
                why = "site adla uyusmuyor" if not c.get("site_matches_name") else "adres baska alan adinda"
                print(f"  {n:>3}. {c['name'][:42]} | {c['city'][:22]} | {c['site']} | {why}")
        if unreach:
            print("SITE ACILMADI (GitHub'dan; sunucudan denenebilir -- otomatik listeye GIRMEDI):")
            hosts = [c["site"] for c in unreach]
            for b in range(0, len(hosts), 8):
                print("    " + " ".join(hosts[b:b + 8]))
        by_letter = collections.defaultdict(list)
        for c in ready:
            by_letter[letter_for(c.get("category", ""))].append("https://" + c["site"])
        for letter in sorted(by_letter):
            links = by_letter[letter]
            print(f"add-and-send (country={name}, letter={letter}, ONCE send=false -- varsayilan true):")
            for b in range(0, len(links), BATCH):
                print(f"  parti {b // BATCH + 1}: " + " ".join(links[b:b + BATCH]))
    print(f"\nTOPLAM: aday {tot['aday']} | HAZIR {tot['hazir']} | ELLE DOGRULA {tot['elle']} | SITE ACILMADI {tot['acilmadi']}")
    print("(hicbir sey yazilmadi, kimseye gonderilmedi -- gonderim add-and-send ile, once send=false)")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
