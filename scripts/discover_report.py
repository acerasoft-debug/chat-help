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

# SOGUK B2B E-POSTA, ulke ulke OZET -- hukuki tavsiye degil (28 Eyl 2026 arastirmasi,
# kaynaklar CLAUDE.md KURAL 40). Rapor gonderim partisini yine basar, karar
# operatorun; ama izin isteyen ulkede partinin USTUNE yazar ki karar aninda gorunsun.
# Tabloda olmayan ulke "ARASTIRILMADI" basar -- sessizce "serbest" sayilmaz.
LEGAL = {
    "DE": ("IZIN SART", "UWG par. 7(2) Nr. 2: isletmeye de onceden acik izin"),
    "AT": ("IZIN SART", "TKG 2021 par. 174: isletmeye de onceden izin"),
    "IT": ("IZIN SART", "Codice Privacy art. 130: tuzel kisi dahil (Garante, 20.09.2012)"),
    "ES": ("IZIN SART", "LSSI art. 21: B2B dahil; eski musteri istisnasi dar"),
    "FR": ("SERBEST (kosullu)", "CNIL: meslegiyle ilgili B2B teklif izinsiz; her mektupta opt-out"),
    "BE": ("KISMEN", "KB 4.4.2003: yalniz tuzel kisinin GENEL adresi (info@, contact@) izinsiz; kisiye ait adres izin ister"),
    "NL": ("KISMEN", "Tw art. 11.7: BV/NV/stichting/vereniging izinsiz + opt-out; eenmanszaak/VOF/CV izin ister"),
    "GB": ("KISMEN", "PECR reg. 22: sirkete (Ltd/PLC/LLP) serbest; sahis isletmesi / ortaklik izin ister"),
}


def legal_for(cc: str):
    return LEGAL.get(cc, ("ARASTIRILMADI", "gondermeden once operator kontrol etmeli"))


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
    # "hedef disi dal" TEK bir sayiya gomulmez: hangi alt kategorinin (mucevher mi,
    # gozluk mu, ortopedi mi) aday yapilmadigi okunabilmeli -- yoksa hiyerarsiden
    # sizan buyuk bir dal ile gercek bir tamirci ayni satirda gorunurdu.
    drop_cat = collections.defaultdict(collections.Counter)
    for d in dropped:
        drop_cc[d["cc"]][d["why"].split(" (")[0]] += 1
        if d["why"].startswith("hedef disi dal"):
            drop_cat[d["cc"]][d.get("category") or "?"] += 1

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
        lg_status, lg_basis = legal_for(cc)
        print(f"soguk B2B e-posta: {lg_status} -- {lg_basis}")
        # Counter, duz {} DEGIL: elenen satiri olmayan bir ulke (kucuk ulke, dar kosu)
        # .most_common() cagrisinda BUTUN raporu dusururdu -- test boyle yakaladi.
        pre = drop_cc.get(cc) or collections.Counter()
        print("Overture elemesi: " + (", ".join(f"{k} {v}" for k, v in pre.most_common()) or "-"))
        if drop_cat.get(cc):
            print("  hedef disi dallar (aday yapilmadi): " + ", ".join(f"{k} {v}" for k, v in drop_cat[cc].most_common()))
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
            if lg_status != "SERBEST (kosullu)":
                print(f"UYARI: {cc} -- soguk B2B e-posta {lg_status} ({lg_basis}). Partiyi gondermek operator karari.")
            print(f"add-and-send (country={name}, letter={letter}, ONCE send=false -- varsayilan true):")
            for b in range(0, len(links), BATCH):
                print(f"  parti {b // BATCH + 1}: " + " ".join(links[b:b + BATCH]))
    print(f"\nTOPLAM: aday {tot['aday']} | HAZIR {tot['hazir']} | ELLE DOGRULA {tot['elle']} | SITE ACILMADI {tot['acilmadi']}")
    print("(hicbir sey yazilmadi, kimseye gonderilmedi -- gonderim add-and-send ile, once send=false)")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
