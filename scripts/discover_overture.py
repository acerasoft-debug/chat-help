#!/usr/bin/env python3
"""
BUTIK AYAKKABI + AKSESUAR KESFI -- Overture Maps Places (ucretsiz, kayitsiz).

Operator, 28 Eyl 2026: "bana workflow kur butik ayakkabi ve aksesuar arayan
distribitor haric fakat bunu ararken ne gibi bir ek eklenti yada uygulama
gerekiyorsa soyle uye olayim".

NEDEN OVERTURE: OSM (discover-city.yml) dukkan basina ~1 yeni lead veriyordu
(8 Eyl olcumu) -- cogu kayitta site yok. Overture Places ayni yerleri Meta +
Microsoft + OSM kaynaklarindan birlestiriyor, websites/emails/phones alanlari
dolu ve bir `brand` alani (Wikidata'ya bagli ZINCIR isareti) tasiyor. Lisans
CDLA-Permissive-2.0: sonucu lead listesinde SAKLAMAK serbest. Google Places'in
kosullari name/website/phone'un kalici saklanmasina izin vermiyor (yalniz
place_id) -- kalici bir lead listesi icin uygun degil. Anahtar, uyelik, ucret YOK:
veri herkese acik S3'te, DuckDB ile dogrudan okunuyor.

KATEGORILER VERIDEN OLCULDU (2026-09-23.1 surumu, taxonomy.primary):
  shoe_store 1513 | fashion_accessories_store 572 | handbag_store (onun alt dali)
  leather_goods_store | hat_store     (ornek: 16 satir grubunun sayimi)
Tahmin edilmedi -- ayni surumun dosyasindan sayildi.

ELEME (hepsi RAPORLANIR, sessiz eleme yok -- mango/zara dersi):
  - brand dolu            -> ZINCIR (Overture'in kendi marka eslemesi)
  - ayni site alan adi >=3 yerde ve >=2 sehirde -> COK SUBELI (KURAL 38 siniri)
  - ayni ad >=3 yerde ve >=2 sehirde            -> COK SUBELI
  - adinda toptan/dagitim kelimesi             -> DAGITICI SINYALI
  - kapali (operating_status != open)          -> KAPALI
  - sitesi yok ya da yalniz sosyal medya       -> SITESIZ (bizim hattimiz siteden
    yayinlanmis adres cozuyor; uydurmuyor)
KURAL 1'in asil kapisi (vestra_lead_is_blocked) bundan SONRA PHP'de, ayni
koddan calisir; bu betik onu kopyalamaz.

Kullanim:
  python3 scripts/discover_overture.py --countries FR,IT --kinds shoes,accessories \
      --out cands.json [--release 2026-09-23.1] [--limit 150]
  --sample-parquet <dosya>  : S3 yerine yerel bir parquet (test icin)
"""
import argparse, json, re, sys, time, urllib.request, collections

S3_BUCKET = "overturemaps-us-west-2"

# Taxonomy degerleri -> tur. Alt dallar hierarchy uzerinden de yakalanir.
KINDS = {
    "shoes":       ["shoe_store"],
    "accessories": ["fashion_accessories_store"],
    "bags":        ["handbag_store", "leather_goods_store"],
    "hats":        ["hat_store"],
    # istege bagli: genel moda butikleri (ayakkabi/aksesuar da satabilir)
    "boutiques":   ["fashion_boutique"],
}
# Hiyerarsinin altinda olup HEDEF OLMAYAN dallar (tibbi / tamir).
EXCLUDE_CATS = {"orthopedic_shoe_store", "shoe_repair"}
# KABUL LISTESI: KINDS'in birlesimi. Sorgu hiyerarsi uzerinden hedef dalin BUTUN
# alt dallarini getiriyor (fashion_accessories_store altinda hat_store ve
# handbag_store olculdu; mucevher, saat ya da gozluk gibi bir dal da orada durabilir
# ve bizim kanalimiz degil). Listede olmayan her dal aday YAPILMAZ ama sessizce de
# kaybolmaz: "hedef disi dal (<kategori>)" diye elenir ve rapor alt kategorileri tek
# tek sayar. Yeni bir dal istenirse KINDS'e bir satir.
ACCEPT_CATS = {c for v in KINDS.values() for c in v}

# Ulke kutulari (boylam/enlem) -- yalnizca satir grubu ELEMEK icin (DuckDB
# istatistik atlamasi). Asil ulke kontrolu adresin kendi ulke kodundan.
BBOX = {
    "AT": (9.53, 46.37, 17.16, 49.02), "BE": (2.54, 49.50, 6.41, 51.51),
    "BG": (22.36, 41.23, 28.61, 44.22), "CH": (5.96, 45.82, 10.49, 47.81),
    "CY": (32.27, 34.56, 34.60, 35.70), "CZ": (12.09, 48.55, 18.86, 51.06),
    "DE": (5.87, 47.27, 15.04, 55.06), "DK": (8.07, 54.56, 15.20, 57.75),
    "EE": (21.76, 57.51, 28.21, 59.68), "ES": (-9.39, 35.95, 4.33, 43.79),
    "FI": (20.55, 59.81, 31.59, 70.09), "FR": (-5.14, 41.33, 9.56, 51.09),
    "GB": (-8.65, 49.86, 1.77, 60.86), "GR": (19.37, 34.80, 29.65, 41.75),
    "HR": (13.49, 42.39, 19.45, 46.55), "HU": (16.11, 45.74, 22.90, 48.59),
    "IE": (-10.48, 51.42, -5.99, 55.39), "IT": (6.63, 35.49, 18.52, 47.09),
    "LT": (20.95, 53.90, 26.84, 56.45), "LU": (5.73, 49.45, 6.53, 50.18),
    "LV": (20.97, 55.67, 28.24, 58.09), "MT": (14.18, 35.79, 14.58, 36.08),
    "NL": (3.36, 50.75, 7.23, 53.56), "NO": (4.64, 57.96, 31.08, 71.19),
    "PL": (14.12, 49.00, 24.15, 54.84), "PT": (-9.53, 36.96, -6.19, 42.15),
    "RO": (20.26, 43.62, 29.69, 48.27), "SE": (11.03, 55.34, 24.17, 69.06),
    "SI": (13.38, 45.42, 16.61, 46.88), "SK": (16.83, 47.73, 22.57, 49.61),
}
COUNTRY_NAME = {
    "AT": "Austria", "BE": "Belgium", "BG": "Bulgaria", "CH": "Switzerland", "CY": "Cyprus",
    "CZ": "Czech Republic", "DE": "Germany", "DK": "Denmark", "EE": "Estonia", "ES": "Spain",
    "FI": "Finland", "FR": "France", "GB": "United Kingdom", "GR": "Greece", "HR": "Croatia",
    "HU": "Hungary", "IE": "Ireland", "IT": "Italy", "LT": "Lithuania", "LU": "Luxembourg",
    "LV": "Latvia", "MT": "Malta", "NL": "Netherlands", "NO": "Norway", "PL": "Poland",
    "PT": "Portugal", "RO": "Romania", "SE": "Sweden", "SI": "Slovenia", "SK": "Slovakia",
}

# Sitesi SOSYAL MEDYA / PLATFORM olan kayit: bizim hat dukkanin KENDI sitesinden
# yayinlanmis adres cozuyor; facebook sayfasindan cozemez. Ayrica bu hostlari
# "ayni alan adi" sayimina sokmak, binlerce ilgisiz dukkani tek zincir sanardi.
SOCIAL_HOSTS = {
    "facebook.com", "fb.com", "fb.me", "instagram.com", "linktr.ee", "twitter.com", "x.com",
    "tiktok.com", "youtube.com", "google.com", "goo.gl", "g.page", "business.site",
    "wa.me", "whatsapp.com", "pinterest.com", "linkedin.com", "tripadvisor.com",
    "yelp.com", "foursquare.com", "booking.com", "maps.app.goo.gl",
}
# REHBER / BELEDIYE / YEREL-TICARET PORTALLARI: dukkanin KENDI sitesi degil. Ornek
# verisinde gorulenler (FR) + her ulkenin buyuk sari sayfalari. Buradan cozulen
# adres portalin adresi olur. Liste DAR tutuluyor (mango/zara dersi): yalniz
# kimsenin kendi dukkan sitesi olamayacak hostlar.
DIRECTORY_HOSTS = {
    "mavillemonshopping.fr", "citymalin.com", "commerces.fr", "site-solocal.com", "solocal.com",
    "pagesjaunes.fr", "gelbeseiten.de", "dasoertliche.de", "11880.com", "paginegialle.it",
    "paginasamarillas.es", "goldenpages.ie", "yell.com", "europages.com", "herold.at",
    "local.ch", "goudengids.nl", "goudengids.be", "pagesdor.be", "infobel.com",
}
# Kullanicinin kendi alt alan adini tasiyan platformlar: kimlik TAM host
# (dukkan.wixsite.com), kok alan adi (wixsite.com) degil. ISS'lerin kisisel sayfa
# alanlari da burada (x.free.fr, monsite.orange.fr, negozio.altervista.org): kok
# alan adi kimlik sayilsaydi, ayni barindiricidaki ILGISIZ dukkanlar tek "zincir"
# (COK SUBELI) gorunur ve ikinci-kapi tekillestirmesi onlari SESSIZCE silerdi.
PLATFORM_SUFFIXES = (
    "wixsite.com", "wix.com", "myshopify.com", "square.site", "webnode.page", "webnode.com",
    "webnode.fr", "webnode.it", "webnode.es", "webnode.cz", "webnode.sk", "webnode.hu",
    "jimdosite.com", "jimdo.com", "jimdofree.com", "weebly.com", "wordpress.com", "blogspot.com",
    "business.site", "ueniweb.com", "godaddysites.com", "site123.me", "strikingly.com", "webador.com",
    "free.fr", "pagesperso-orange.fr", "orange.fr", "wanadoo.fr", "sfr.fr", "altervista.org",
    "over-blog.com", "e-monsite.com", "wifeo.com", "sitew.fr", "sitew.com", "webs.com",
)

# DAGITICI / TOPTANCI sinyali -- AD icinde, kelime siniriyla. Ters yon testli:
# "Grossi Calzature" (soyad) elenmez, "Grosshandel" elenir.
# Tekil + cogul yazilir: kelime siniri "distributori"yi "distributore" ile yakalamaz.
DISTRIBUTOR_WORDS = [
    "wholesale", "wholesaler", "wholesalers", "großhandel", "grosshandel", "grossiste",
    "grossistes", "grossista", "grossisti", "ingrosso", "mayorista", "mayoristas", "groothandel",
    "hurtownia", "velkoobchod", "nagykereskedés", "nagykereskedes", "tukkukauppa", "engros",
    "grossist", "distributor", "distributors", "distribution", "distributore", "distributori",
    "distribuzione", "distribución", "distribucion", "distribuidor", "distribuidora",
    "distributeur", "distributeurs", "import export", "import-export", "b2b", "showroom",
    "agentur", "agenzia", "agency",
]
_DIST_RE = re.compile(r"(?<![\w])(" + "|".join(re.escape(w) for w in DISTRIBUTOR_WORDS) + r")(?![\w])", re.I | re.U)


def host_of(url: str) -> str:
    u = (url or "").strip().lower()
    u = re.sub(r"^[a-z]+://", "", u)
    u = u.split("/")[0].split("?")[0].split("#")[0].split(":")[0]
    if u.startswith("www."):
        u = u[4:]
    return u


def site_identity(url: str) -> str:
    """Dukkanin SITE KIMLIGI: platform alt alan adi TAM host, digerleri kok alan adi.
    Sosyal/haritalar -> '' (site yok say)."""
    h = host_of(url)
    if not h or "." not in h:
        return ""
    for s in SOCIAL_HOSTS | DIRECTORY_HOSTS:
        if h == s or h.endswith("." + s):
            return ""
    for p in PLATFORM_SUFFIXES:
        if h.endswith("." + p):
            return h
    parts = h.split(".")
    # co.uk / com.au / com.br gibi iki parcali uzantilar
    if len(parts) >= 3 and parts[-2] in ("co", "com", "org", "net", "ac", "gov") and len(parts[-1]) == 2:
        return ".".join(parts[-3:])
    return ".".join(parts[-2:])


# AD <-> SITE uyumu. Bir dukkan kendi sitesi yerine SATTIGI MARKANIN sitesini
# yazmis olabilir (ornek verisinde "Le Gal et Cano -> kipling.com",
# "Coccinelle chaussures -> kickers-and-co.com" = marka bayisi). O siteden cozulen
# adres markanin adresi olur (KURAL 1h'nin bu hattaki hali). Eleme DEGIL: bu
# kayitlar otomatik listeye girmez, "ELLE DOGRULA" listesine duser.
GENERIC_WORDS = set("""
chaussures chaussure shoes shoe schuhe schuh schuhhaus schuhmode calzados calzado zapateria zapatos
scarpe calzature sapatos sapataria schoenen schoenmode sko skor kenka kenkä buty obuv cipo cipő
boutique shop store magasin maison moda mode fashion accessoires accessories accesorios accessori
maroquinerie pelletteria marroquineria lederwaren chapellerie hats hat sombrereria cappelli hutmode
la le les el los las il lo gli der die das the and et und di de du des del da van von het en of
""".split())


def _ascii(s: str) -> str:
    import unicodedata
    return "".join(ch for ch in unicodedata.normalize("NFKD", s) if not unicodedata.combining(ch))


def _variants(tok: str) -> set:
    """Alan adinda harf iki turlu yazilir: Müller -> muller VE mueller (ß -> ss)."""
    de = tok.replace("ä", "ae").replace("ö", "oe").replace("ü", "ue").replace("ß", "ss")
    return {re.sub(r"[^a-z0-9]", "", _ascii(v)) for v in (tok, de)} - {""}


def name_matches_site(name: str, site: str) -> bool:
    if not site:
        return False
    # ilk etiket: kok alan adinda dukkanin adi, platformda (dukkan.wixsite.com) alt alan adi
    label = re.sub(r"[^a-z0-9]", "", _ascii(site.split(".")[0].lower()))
    words = norm_name(name).split()
    sig = [w for w in words if len(_ascii(w)) >= 3 and _ascii(w) not in GENERIC_WORDS]
    if sig:
        # AYIRT EDICI kelime varsa O karar verir: "Chaussures Martin" -> chaussures.fr
        # (genel bir alan adi, cogu zaman rehber / pazar yeri) eslesmemeli.
        return any(v in label for w in sig for v in _variants(w))
    # Adin tamami genel kelime ("My Shoes"): alan adinin adin kendisi olmasi yeter.
    joined = "".join(re.sub(r"[^a-z0-9]", "", _ascii(w)) for w in words)
    return len(label) >= 4 and bool(joined) and (label in joined or joined in label)


def norm_name(n: str) -> str:
    n = (n or "").lower()
    n = re.sub(r"[^\w\s]", " ", n, flags=re.U)
    return re.sub(r"\s+", " ", n).strip()


def latest_release() -> str:
    url = f"https://{S3_BUCKET}.s3.amazonaws.com/?list-type=2&prefix=release/&delimiter=/"
    xml = urllib.request.urlopen(url, timeout=60).read().decode()
    rels = sorted(re.findall(r"<Prefix>release/([^/<]+)/</Prefix>", xml))
    if not rels:
        raise SystemExit("Overture surumu bulunamadi")
    return rels[-1]


def _q(s: str) -> str:
    return "'" + s.replace("'", "''") + "'"


def build_sql(source: str, ccs: list, cats: list, use_bbox: bool) -> str:
    """TEK uzak gecis: istenen butun ulkeler bir sorguda. Ulke basina ayri sorgu,
    ayni uzak dosyalari ulke sayisi kadar yeniden tarardi (28 Eyl ilk canli kosusu:
    5 ulke, Overture adimi 13+ dakika). Kutu = ulke kutularinin BIRLESIMI (yalniz
    satir grubu atlamak icin, DuckDB bbox'i parquet okumasina itiyor); asil karar
    adresin KENDI ulke kodu."""
    cat_sql = ",".join(_q(c) for c in cats)
    cc_sql = ",".join(_q(c.upper()) for c in ccs)
    where_bbox = ""
    boxes = [BBOX[c.upper()] for c in ccs if c.upper() in BBOX]
    # Kutusu olmayan tek bir ulke bile varsa kutu HIC uygulanmaz: birlesik kutu o
    # ulkeyi disarida birakir ve satirlari SESSIZCE budanirdi (test: kucuk harfli
    # "fr" boyle kayboluyordu).
    if use_bbox and boxes and len(boxes) == len(ccs):
        x0, y0 = min(b[0] for b in boxes), min(b[1] for b in boxes)
        x1, y1 = max(b[2] for b in boxes), max(b[3] for b in boxes)
        where_bbox = (f"bbox.xmin >= {x0} AND bbox.xmax <= {x1} AND "
                      f"bbox.ymin >= {y0} AND bbox.ymax <= {y1} AND ")
    return f"""
      SELECT id, names.primary AS name, taxonomy.primary AS cat, taxonomy.hierarchy AS hier,
             websites, emails, phones, brand.wikidata AS brand_wd, brand.names.primary AS brand_name,
             addresses[1].locality AS city, upper(addresses[1].country) AS cc, addresses[1].postcode AS postcode,
             confidence, operating_status
      FROM {source}
      WHERE {where_bbox}
            (taxonomy.primary IN ({cat_sql}) OR list_has_any(taxonomy.hierarchy, [{cat_sql}]))
            AND upper(addresses[1].country) IN ({cc_sql})
    """


def query_rows(con, sql: str):
    return con.execute(sql).fetchall(), [d[0] for d in con.description]


def category_mix(rows: list) -> str:
    """Ulke basina taxonomy.primary dagilimi. Sorgu hiyerarsi uzerinden alt dallari
    da topluyor; bir ulkenin sayisi digerlerinden sapinca (28 Eyl ilk kosusu: IT
    32.176, FR 9.013) sebebi TAHMIN edilmesin, basilsin: hedef disi bir alt dal mi
    sizdi, yoksa o ulkede gercekten bu kadar dukkan mi var."""
    c = collections.Counter((r.get("cat") or "?") for r in rows)
    return ", ".join(f"{k} {v}" for k, v in c.most_common()) or "-"


def load_known(path: str) -> set:
    """Sunucunun lead + hesap kayitlarindaki siteler ve e-posta alan adlari -> site kimligi.
    Kural TEK yerde (site_identity): sunucu ham host basar, kimligi burada kurulur."""
    out = set()
    for line in open(path, encoding="utf-8", errors="ignore"):
        h = line.strip().lstrip("@")
        if h:
            sid = site_identity(h)
            if sid:
                out.add(sid)
    return out


def classify(rows_by_cc: dict, limit: int, min_conf: float, known: set = frozenset()):
    """Saf karar: satirlar -> (adaylar, elenenler). Test edilebilsin diye DuckDB'den ayri."""
    out, dropped = [], []
    for cc, rows in rows_by_cc.items():
        # 1) once KAPALI / SITESIZ / ZINCIR(brand) / DAGITICI / hedef-disi dal
        pre = []
        for r in rows:
            name = (r.get("name") or "").strip()
            cat = r.get("cat") or ""
            why = ""
            if not name:
                why = "adsiz"
            elif cat in EXCLUDE_CATS or cat not in ACCEPT_CATS:
                why = "hedef disi dal (" + (cat or "?") + ")"
            elif (r.get("operating_status") or "open") not in ("open", ""):
                why = "KAPALI (" + str(r.get("operating_status")) + ")"
            elif r.get("brand_wd") or r.get("brand_name"):
                why = "ZINCIR (Overture marka eslesmesi: " + (r.get("brand_name") or r.get("brand_wd") or "") + ")"
            elif _DIST_RE.search(name):
                why = "DAGITICI SINYALI (adda '" + _DIST_RE.search(name).group(1) + "')"
            elif (r.get("confidence") or 0) < min_conf:
                why = "dusuk guven (%.2f)" % (r.get("confidence") or 0)
            site = ""
            for w in (r.get("websites") or []):
                site = site_identity(w)
                if site:
                    r["website"] = w.strip()
                    break
            if not why and not site:
                why = "SITESIZ (yalniz sosyal medya / rehber ya da hic)"
            r["site"] = site
            if why:
                dropped.append({**_pub(r, cc), "why": why})
            else:
                pre.append(r)
        # 2) COK SUBELI: ayni site kimligi ya da ayni ad >=3 yerde, >=2 sehirde
        by_site, by_name = collections.defaultdict(list), collections.defaultdict(list)
        for r in pre:
            by_site[r["site"]].append(r)
            by_name[norm_name(r["name"])].append(r)
        seen_site = set()
        for r in pre:
            s_grp, n_grp = by_site[r["site"]], by_name[norm_name(r["name"])]
            s_cities = {(x.get("city") or "").lower() for x in s_grp}
            n_cities = {(x.get("city") or "").lower() for x in n_grp}
            if len(s_grp) >= 3 and len(s_cities) >= 2:
                dropped.append({**_pub(r, cc), "why": f"COK SUBELI (ayni site {len(s_grp)} yerde, {len(s_cities)} sehir)"})
                continue
            if len(n_grp) >= 3 and len(n_cities) >= 2:
                dropped.append({**_pub(r, cc), "why": f"COK SUBELI (ayni ad {len(n_grp)} yerde, {len(n_cities)} sehir)"})
                continue
            if r["site"] in known:
                dropped.append({**_pub(r, cc), "why": "ZATEN KAYITLI (lead ya da hesap)"})
                continue
            if r["site"] in seen_site:      # ayni firmanin ikinci kapisi: TEK aday
                continue
            seen_site.add(r["site"])
            rec = _pub(r, cc)
            rec["branches"] = len(s_grp)
            rec["overture_email"] = bool(r.get("emails"))
            rec["site_matches_name"] = name_matches_site(rec["name"], r["site"])
            out.append(rec)
    # siteli + yuksek guven once; ulke basina tavan
    per = collections.defaultdict(list)
    for r in out:
        per[r["cc"]].append(r)
    final = []
    for cc, lst in per.items():
        lst.sort(key=lambda x: (-(1 if x["overture_email"] else 0), -(x.get("confidence") or 0), x["name"].lower()))
        final.extend(lst[:limit])
    return final, dropped


def _pub(r: dict, cc: str) -> dict:
    return {
        "name": (r.get("name") or "").strip(),
        "website": (r.get("website") or "").strip(),
        "site": r.get("site") or "",
        "city": (r.get("city") or "").strip(),
        "postcode": (r.get("postcode") or "").strip(),
        "cc": cc,
        "country": COUNTRY_NAME.get(cc, cc),
        "category": r.get("cat") or "",
        "confidence": round(float(r.get("confidence") or 0), 3),
        "overture_id": r.get("id") or "",
    }


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--countries", required=True)
    ap.add_argument("--kinds", default="shoes,accessories,bags,hats")
    ap.add_argument("--release", default="")
    ap.add_argument("--limit", type=int, default=150)
    ap.add_argument("--min-confidence", type=float, default=0.5)
    ap.add_argument("--out", required=True)
    ap.add_argument("--dropped-out", default="")
    ap.add_argument("--sample-parquet", default="")
    ap.add_argument("--known", default="", help="sunucudan: satir basina bir host ya da @alanadi")
    a = ap.parse_args()

    ccs = [c.strip().upper() for c in a.countries.split(",") if c.strip()]
    bad = [c for c in ccs if c not in BBOX]
    if bad:
        raise SystemExit("tanimsiz ulke kodu: " + ",".join(bad) + " (tanimli: " + ",".join(sorted(BBOX)) + ")")
    kinds = [k.strip().lower() for k in a.kinds.split(",") if k.strip()]
    badk = [k for k in kinds if k not in KINDS]
    if badk:
        raise SystemExit("tanimsiz tur: " + ",".join(badk) + " (tanimli: " + ",".join(KINDS) + ")")
    cats = sorted({c for k in kinds for c in KINDS[k]})

    import duckdb
    con = duckdb.connect()
    if a.sample_parquet:
        source = f"read_parquet('{a.sample_parquet}')"
        rel = "yerel-ornek"
        use_bbox = False
    else:
        con.execute("INSTALL httpfs; LOAD httpfs; SET s3_region='us-west-2';")
        rel = a.release or latest_release()
        source = f"read_parquet('s3://{S3_BUCKET}/release/{rel}/theme=places/type=place/*', hive_partitioning=1)"
        use_bbox = True
    print(f"Overture surumu: {rel} | turler: {','.join(kinds)} -> {','.join(cats)}", flush=True)

    t = time.time()
    rows, cols = query_rows(con, build_sql(source, ccs, cats, use_bbox))
    rows_by_cc = {cc: [] for cc in ccs}
    for r in rows:
        rec = dict(zip(cols, r))
        # Birlesik kutu komsu ulkeleri de kapsar: adresin KENDI ulke kodu karar verir.
        if (rec.get("cc") or "") in rows_by_cc:
            rows_by_cc[rec["cc"]].append(rec)
    print(f"  tek gecis: {len(rows)} yer, {time.time()-t:.0f} sn", flush=True)
    for cc in ccs:
        print(f"  {cc}: Overture'da {len(rows_by_cc[cc])} yer | " + category_mix(rows_by_cc[cc]), flush=True)

    known = load_known(a.known) if a.known else set()
    if a.known:
        print(f"kayitli site/alan adi (lead + hesap): {len(known)}", flush=True)
    final, dropped = classify(rows_by_cc, a.limit, a.min_confidence, known)
    json.dump(final, open(a.out, "w"), ensure_ascii=False, indent=1)
    if a.dropped_out:
        json.dump(dropped, open(a.dropped_out, "w"), ensure_ascii=False, indent=1)
    why = collections.Counter(d["why"].split(" (")[0] for d in dropped)
    print(f"ADAY: {len(final)} | ELENEN: {len(dropped)} -> " + ", ".join(f"{k} {v}" for k, v in why.most_common()), flush=True)


if __name__ == "__main__":
    main()
