#!/usr/bin/env python3
"""
Ekstraktor data referensi BoQ dari Excel "Detailed Calculation BoQ Copper Residensial Industrial.xlsx".

Membaca nilai tersimpan (cache) tiap sheet katalog, lalu menulis
database/seeders/boq_reference.json — dipakai database/seeders/boq_reference.php
untuk mengisi tabel boq_ref_items. Jalankan ulang bila Excel katalog berubah:

    python tools/boq/extract_reference.py "<path ke xlsx>"

Skrip hanya MEMBACA Excel; tidak mengubah file asli.
"""
import json
import re
import sys
import warnings
from pathlib import Path

import openpyxl
from openpyxl.utils import column_index_from_string as C

warnings.filterwarnings("ignore")

SRC = sys.argv[1] if len(sys.argv) > 1 else None
if not SRC:
    sys.exit("Pakai: python tools/boq/extract_reference.py <file.xlsx>")

OUT = Path(__file__).resolve().parents[2] / "database" / "seeders" / "boq_reference.json"
wb = openpyxl.load_workbook(SRC, data_only=True)


def cell(ws, col, row):
    return ws.cell(row, C(col)).value


def num(v):
    if v is None or v == "":
        return None
    if isinstance(v, (int, float)):
        return float(v) if not float(v).is_integer() else int(v)
    s = str(v).strip().replace(",", ".")
    try:
        f = float(s)
        return int(f) if f.is_integer() else f
    except ValueError:
        return None


def text(v):
    if v is None:
        return None
    s = str(v).strip()
    return s or None


def phase_of(v):
    s = (text(v) or "").replace(" ", "")
    if s == "_1_Phase":
        return 1
    if s == "_3_Phase":
        return 3
    return None


def rating_of(v):
    m = re.match(r"^\s*(\d+(?:[.,]\d+)?)\s*A?\s*$", str(v or ""))
    return float(m.group(1).replace(",", ".")) if m else None


data = {}


def add(category, code, label, attrs, order=None):
    data.setdefault(category, []).append({
        "code": str(code),
        "label": label,
        "attrs": {k: v for k, v in attrs.items() if v is not None},
        "sort_order": order if order is not None else len(data.get(category, [])) + 1,
    })


# --------------------------------------------------------------------- PV module
ws = wb["PV Module"]
blocks = [("Trinasolar", "D", ["E", "F", "G", "H", "I", "J", "K", "L"]),
          ("Jasolar", "N", ["O", "P", "Q", "R", "S", "T", "U", "V"]),
          ("Lessosolar", "Z", ["AA", "AB", "AC", "AD", "AE", "AF", "AG", "AH"])]
for brand, mcol, cols in blocks:
    r = 3
    while text(cell(ws, mcol, r)):
        model = text(cell(ws, mcol, r))
        wp = re.search(r"(\d{3,4})\s*Wp", model, re.I)
        vals = [num(cell(ws, c, r)) for c in cols]
        add("pv_module", model, model, {
            "brand": brand, "wp": int(wp.group(1)) if wp else None,
            "vmpp": vals[0], "impp": vals[1], "voc": vals[2], "isc": vals[3],
            "length": vals[4], "width": vals[5], "thickness": vals[6], "weight": vals[7],
        })
        r += 1

# ---------------------------------------------------------------------- Inverter
ws = wb["INVERTER"]
inv_blocks = {
    # brand: (model col, imax, phase, breaker rating, breaker code, power, monitoring col, meteo col)
    "Sungrow": ("A", "B", "D", "E", "F", "G"),
    "Solax": ("M", "N", "P", "Q", "R", "S"),
    "Huawei": ("W", "X", "Z", "AA", None, "AB"),
    "Deye": ("AF", "AG", "AI", "AJ", None, "AK"),
}
for brand, (mc, ic, pc, bc, bcode, pw) in inv_blocks.items():
    r = 4
    while True:
        model = text(cell(ws, mc, r))
        if model in (None, "0") and r > 4 and not text(cell(ws, mc, r + 1)):
            break
        if model in (None, "0"):  # baris pemisah di tengah tabel (Huawei)
            r += 1
            continue
        imax = num(cell(ws, ic, r))
        add("inverter", model, model, {
            "brand": brand,
            "phase": phase_of(cell(ws, pc, r)),
            "imax": imax,
            "power_w": num(cell(ws, pw, r)),
            "breaker_rating": rating_of(cell(ws, bc, r)),
            # Kode breaker per model dari Excel dipertahankan sebagai catatan; kalkulator memilih
            # kode dari katalog breaker berdasarkan kutub + rating (lihat BoqCalculator).
            "breaker_code_excel": text(cell(ws, bcode, r)) if bcode else None,
        })
        r += 1

# Monitoring & meteo per merek + kompatibilitas
brand_meteo = {"Sungrow": "I", "Solax": "U", "Huawei": "AD", "Deye": "AM"}
brand_mon = {"Sungrow": "H", "Solax": "T", "Huawei": "AC", "Deye": "AL"}
seen = set()
for brand, col in brand_meteo.items():
    r = 4
    while r < 60:
        v = text(cell(ws, col, r))
        if v and v != "0" and (brand, v) not in seen:
            seen.add((brand, v))
            add("meteo", f"{brand}|{v}", v, {"brand": brand})
        r += 1

# flag butuh panel komunikasi (kolom AU..AX): brand | monitoring | meteo | flag
needs = {}
r = 3
while r < 60:
    b, mon, flag = text(cell(ws, "AU", r)), text(cell(ws, "AV", r)), cell(ws, "AX", r)
    if b and mon and mon != "0" and flag is not None:
        needs[(b, mon)] = int(flag)
    r += 1

# tabel kompatibilitas (BB monitoring -> BC meteo / NOT COMPATIBLE)
compat = {}
for r in range(3, 130):
    mon, met = text(cell(ws, "BB", r)), text(cell(ws, "BC", r))
    if mon and mon != "0":
        compat.setdefault(mon, [])
        if met:
            compat[mon].append(met)



def lookup_name(name, table):
    """Cocokkan nama monitoring: persis, lalu awalan (Excel menulis '3000A01EU' di satu tabel, '3000A01' di tabel lain)."""
    if name in table:
        return name
    for key in table:
        k = key[1] if isinstance(key, tuple) else key
        if k and (name.startswith(k) or k.startswith(name)):
            return key
    return None


for brand, col in brand_mon.items():
    r = 4
    while r < 60:
        v = text(cell(ws, col, r))
        if v and v != "0" and not any(x["code"] == f"{brand}|{v}" for x in data.get("monitoring", [])):
            ckey = lookup_name(v, compat)
            nkey = None
            for k in needs:
                if k[0] == brand and (k[1] == v or v.startswith(k[1]) or k[1].startswith(v)):
                    nkey = k
                    break
            meteos = [m for m in compat.get(ckey, []) if m not in ("NOT COMPATIBLE", "0")] if ckey else []
            add("monitoring", f"{brand}|{v}", v, {
                "brand": brand,
                "needs_panel": needs.get(nkey, 0) if nkey else 0,
                "compatible_meteo": meteos,
            })
        r += 1

# ---------------------------------------------------------------------- Breaker
ws = wb["Breaker Selection & CT"]
series, subtype = None, None
for r in range(2, 501):
    kind, ser = text(cell(ws, "A", r)), text(cell(ws, "B", r))
    code = text(cell(ws, "F", r))
    if not kind or not code:
        continue
    sub = text(cell(ws, "C", r))
    if sub:
        subtype = sub
    ph = text(cell(ws, "D", r))
    rating = rating_of(cell(ws, "E", r))
    add("breaker", code.strip(), f"{ser} {rating:g}A" if rating else ser, {
        "kind": kind, "series": ser, "subtype": subtype,
        "phase": 1 if (ph or "").replace(" ", "") == "_1_Phase" else 3,
        "rating": rating, "ka": num(cell(ws, "G", r)),
        "poles": text(cell(ws, "H", r)), "width": num(cell(ws, "I", r)),
    })

for r in range(2, 200):
    ratio = text(cell(ws, "O", r))
    if not ratio:
        continue
    add("ct", f"{text(cell(ws, 'L', r))}|{text(cell(ws, 'N', r))}|{ratio}", f"CT {ratio}", {
        "ct_type": text(cell(ws, "L", r)), "brand": text(cell(ws, "M", r)),
        "model": text(cell(ws, "N", r)), "ratio": ratio,
        "class": num(cell(ws, "P", r)), "burden_va": num(cell(ws, "Q", r)),
        "busbar_hole": text(cell(ws, "R", r)),
    })

# ----------------------------------------------------------------------- Kabel
# Tangga pemilihan ukuran (batas ampacity maksimum per ukuran) mengikuti persis rumus
# 'Data Cabling ON GRID' kolom I: ukuran terkecil yang batasnya >= arus perlu.
LADDER_1C = {2.5: 26, 4: 46, 6: 58, 10: 80, 16: 100, 25: 135, 35: 170, 50: 205, 70: 260, 95: 320,
             120: 375, 150: 430, 185: 490, 240: 590, 300: 680, 400: 825, 500: 960}
LADDER_MULTI = {1.5: 18, 2.5: 25, 4: 34, 6: 44, 10: 60, 16: 80, 25: 105, 35: 130, 50: 160, 70: 200,
                95: 245, 120: 285, 150: 325, 185: 370, 240: 435, 300: 500}
# Ukuran PE mengikuti rumus 'Data Cabling ON GRID' kolom G.
PE_MAP = {1.5: 1.5, 2.5: 2.5, 4: 4, 6: 6, 10: 10, 16: 16, 25: 16, 35: 25, 50: 25, 70: 35, 95: 50,
          120: 70, 150: 95, 185: 95, 240: 120, 300: 150, 400: 185, 500: 240}


def fmt_size(s):
    return f"{s:g}"


ws = wb["1C"]
for r in range(6, 24):
    s = num(cell(ws, "A", r))
    if not s:
        continue
    add("cable_1c", fmt_size(s), f"1C x {fmt_size(s)} mm²", {
        "size": s, "r20": num(cell(ws, "B", r)), "r70": num(cell(ws, "C", r)),
        "insulation": num(cell(ws, "D", r)), "i_air": num(cell(ws, "E", r)),
        "i_ground": num(cell(ws, "F", r)), "isc_ka": num(cell(ws, "G", r)),
        "od": num(cell(ws, "H", r)),
        "select_max_a": LADDER_1C.get(s), "pe_size": PE_MAP.get(s),
    })

ws = wb["3C & 4C"]
for r in range(5, 21):
    s = num(cell(ws, "A", r))
    if not s:
        continue
    add("cable_multi", fmt_size(s), f"3C/4C x {fmt_size(s)} mm²", {
        "size": s, "r20": num(cell(ws, "B", r)), "r70": num(cell(ws, "C", r)),
        "insulation": num(cell(ws, "D", r)), "i_air": num(cell(ws, "E", r)),
        "i_ground": num(cell(ws, "F", r)), "isc_ka": num(cell(ws, "G", r)),
        "od_3c": num(cell(ws, "H", r)), "od_4c": num(cell(ws, "I", r)),
        "select_max_a": LADDER_MULTI.get(s), "pe_size": PE_MAP.get(s),
    })

ws = wb["NYA"]
for r in range(3, 20):
    s = num(cell(ws, "A", r))
    if s:
        add("cable_nya", fmt_size(s), f"NYA 1x{fmt_size(s)} mm²", {"size": s, "od": num(cell(ws, "B", r))})

ws = wb["Cable Sizing Correction Factor"]
for r in range(2, 17):
    t = num(cell(ws, "A", r))
    if t is not None:
        add("cable_correction", fmt_size(t), f"{fmt_size(t)} °C", {
            "ambient_c": t, "pvc": num(cell(ws, "B", r)), "xlpe": num(cell(ws, "C", r))})

# Kabel DC (solar cable) — sheet 'Cable DC' B15:E18 (R @20 °C, arus) dan B20:C23 (diameter luar).
ws = wb["Cable DC"]
od_by_size = {}
for r in range(20, 24):
    label = text(cell(ws, "B", r))
    if label:
        od_by_size[label] = num(cell(ws, "C", r))
for r in range(15, 19):
    label = text(cell(ws, "B", r))
    if label:
        add("cable_dc", label, label + " mm²", {
            "size": num(label.split("x")[-1]), "r20": num(cell(ws, "E", r)),
            "current_a": num(cell(ws, "D", r)), "od": od_by_size.get(label)})

# ---------------------------------------------------- Skun, vinyl, gland, conduit
ws = wb["Skun & Vinyl & Gland & Conduit"]
skun_blocks = [("A", "B", "C"), ("E", "F", "G"), ("I", "J", "K"), ("S", "T", "U"), ("W", "X", "Y"),
               ("AA", "AB", "AC"), ("AE", "AF", "AG")]
for tc, cc, sc in skun_blocks:
    r = 3
    while text(cell(ws, cc, r)):
        t, code, size = text(cell(ws, tc, r)), text(cell(ws, cc, r)), num(cell(ws, sc, r))
        add("skun", f"{t}|{code}", code, {"skun_type": t, "size": size})
        r += 1

for r in range(26, 45):
    code = text(cell(ws, "D", r))
    if code:
        add("vinyl", code, code, {"diameter": num(cell(ws, "E", r)), "size": num(cell(ws, "F", r)),
                                  "color": text(cell(ws, "G", r))})

gland_blocks = [("AP", "AQ", "AR", "AS", "AT", "AU"), ("AW", "AX", "AY", "AZ", "BA", "BB"),
                ("BD", "BE", "BF", "BG", "BH", "BI"), ("BK", "BL", "BM", "BN", "BO", "BP")]
for tc, cc, rc, mn, mx, ic in gland_blocks:
    r = 3
    while text(cell(ws, cc, r)):
        t, code = text(cell(ws, tc, r)), text(cell(ws, cc, r))
        add("gland", f"{t}|{code}", f"{t} {code}", {
            "gland_type": t, "gland_code": code, "cable_range": text(cell(ws, rc, r)),
            "min_od": num(cell(ws, mn, r)), "max_od": num(cell(ws, mx, r)), "inch": text(cell(ws, ic, r))})
        r += 1

for r in range(3, 20):
    inch = text(cell(ws, "BU", r))
    if inch:
        add("conduit", inch, f"Flexible Metal Conduit {inch}", {
            "inch": inch, "mm": num(cell(ws, "BV", r)), "use": text(cell(ws, "BW", r))})

# ----------------------------------------------------------------------- Busbar
ws = wb["Busbar & Box Panel"]
for r in range(2, 23):
    size = text(cell(ws, "C", r))
    if size:
        add("busbar", size, size, {
            "width": num(cell(ws, "D", r)), "thickness": num(cell(ws, "E", r)), "area_mm2": num(cell(ws, "F", r)),
            "kha_1": num(cell(ws, "G", r)), "kha_2": num(cell(ws, "H", r)),
            "kha_3": num(cell(ws, "I", r)), "kha_4": num(cell(ws, "J", r))})

# ------------------------------------------------------------------- Tarif jasa
ws = wb["DATA Jasa"]
r = 2
while num(cell(ws, "P", r)) is not None:
    kwp = num(cell(ws, "P", r))
    add("jasa_tier", fmt_size(kwp), f"Tier {fmt_size(kwp)} kWp", {
        "kwp": kwp, "survey": num(cell(ws, "Q", r)), "drawing": num(cell(ws, "R", r)),
        "blueprint": num(cell(ws, "S", r)), "om": num(cell(ws, "T", r)),
        "troubleshooting": num(cell(ws, "U", r)),
        "engineering_total": sum(num(cell(ws, c, r)) or 0 for c in "QRSTU"),
        "training_day": num(cell(ws, "X", r)), "training_man": num(cell(ws, "Y", r)),
        "training_rate": num(cell(ws, "Z", r)),
        "training_total": (num(cell(ws, "X", r)) or 0) * (num(cell(ws, "Y", r)) or 0) * (num(cell(ws, "Z", r)) or 0),
        "atk": num(cell(ws, "AC", r))})
    r += 1

# ---------------------------------------------------------------- Parameter dasar
params = [
    ("current_factor", "Faktor arus rating breaker (x Imax inverter)", 1.3, "BoQ On Grid AC COMB!C14"),
    ("main_breaker_factor", "Faktor arus breaker utama (x total arus inverter)", 1.2, "BoQ On Grid AC COMB!C13"),
    ("ampacity_factor", "Faktor ampacity kabel AC (x arus)", 1.7, "Data Cabling ON GRID kolom H"),
    ("nominal_voltage", "Tegangan nominal AC (V)", 400, "Data Cabling ON GRID!D2"),
    ("vd_limit_percent", "Batas drop tegangan (%)", 3, "Data Cabling ON GRID kolom N"),
    ("skun_spare", "Spare skun & vinyl (fraksi)", 0.05, "BoQ On Grid (PV-INVERTER-AC)!H52"),
    ("dcac_hme_min", "DC/AC minimum (standar HME)", 1.1, "BoQ On Grid (PV-INVERTER-AC)!K1"),
    ("dcac_hme_max", "DC/AC maksimum (standar HME)", 1.35, "BoQ On Grid (PV-INVERTER-AC)!K1"),
    ("dcac_dev_min", "DC/AC minimum (standar Developer)", 1.2, "BoQ On Grid (PV-INVERTER-AC)!K1"),
    ("dcac_dev_max", "DC/AC maksimum (standar Developer)", 1.3, "BoQ On Grid (PV-INVERTER-AC)!K1"),
    ("socket_per_inverter", "Estimasi aksesori komunikasi per inverter (Rp)", 50000, "BoQ On Grid (PV-INVERTER-AC)!L45"),
    ("socket_per_monitoring", "Estimasi aksesori komunikasi per monitoring (Rp)", 35000, "BoQ On Grid (PV-INVERTER-AC)!L45"),
    ("jasa_contingency", "Contingency jasa (fraksi)", 0.05, "DATA Jasa!K5"),
    ("jasa_atk_round", "Pembulatan ATK ke atas (Rp)", 1000000, "DATA Jasa!M4"),
]
for code, label, value, src in params:
    add("param", code, label, {"value": value, "source": src})

# ------------------------------------------------------------ Aturan pilihan (dropdown Excel)
# Excel membatasi pilihan lewat data validation dinamis; tabel sumbernya dibaca di sini supaya sistem
# menegakkan aturan yang sama (inti kabel/kutub/seri/kA menurut fasa, tipe skun menurut seri breaker).
ws = wb["INVERTER"]
cores = {}
for r in range(9, 13):  # AP9:AQ12 = fasa -> jenis inti
    ph, core = phase_of(cell(ws, "AP", r)), text(cell(ws, "AQ", r))
    if ph and core:
        cores.setdefault(ph, []).append(core)
bs = wb["Breaker Selection & CT"]
series_by_phase, poles_by_phase, ka_by_phase = {1: [], 3: []}, {1: [], 3: []}, {1: [], 3: []}
for r in range(4, 23):  # AG:AH = fasa -> seri breaker yang boleh
    ph, ser = phase_of(cell(bs, "AG", r)), text(cell(bs, "AH", r))
    if ph and ser and ser not in series_by_phase[ph]:
        series_by_phase[ph].append(ser)
for r in range(4, 8):  # Y:Z = fasa -> kutub
    ph, pole = phase_of(cell(bs, "Y", r)), text(cell(bs, "Z", r))
    if ph and pole:
        poles_by_phase[ph].append(pole)
for r in range(4, 18):  # X:Y = kA yang boleh per fasa
    ph, ka = phase_of(cell(bs, "Y", r)), num(cell(bs, "X", r))
    if ph and ka is not None:
        ka_by_phase[ph].append(ka)
factors = [num(cell(bs, "AF", r)) for r in range(4, 10) if num(cell(bs, "AF", r)) is not None]

for ph in (1, 3):
    add("rule", f"cores_phase_{ph}", f"Inti kabel untuk {ph} fasa", {"values": cores.get(ph, [])})
    add("rule", f"series_phase_{ph}", f"Seri breaker untuk {ph} fasa", {"values": series_by_phase[ph]})
    add("rule", f"poles_phase_{ph}", f"Kutub breaker untuk {ph} fasa", {"values": poles_by_phase[ph]})
    add("rule", f"ka_phase_{ph}", f"Kapasitas pemutus (kA) untuk {ph} fasa", {"values": ka_by_phase[ph]})
add("rule", "breaker_factors", "Faktor arus breaker yang boleh dipilih", {"values": factors})
add("rule", "spare_options", "Pilihan spare (%)", {"values": [0, 2, 4, 5, 6, 8, 10]})  # data validation H52, G84, dst.

for r in range(4, 24):  # AN:AO = kapasitas trafo (kVA) -> kA breaker utama
    kva, ka = num(cell(bs, "AN", r)), num(cell(bs, "AO", r))
    if kva is not None and ka is not None:
        add("transformer_ka", fmt_size(kva), f"Trafo {fmt_size(kva)} kVA", {"kva": kva, "ka": ka})

sk = wb["Skun & Vinyl & Gland & Conduit"]  # O26:P81 = seri breaker -> tipe skun yang boleh
skun_by_series = {}
for r in range(26, 85):
    ser, typ = text(cell(sk, "O", r)), text(cell(sk, "P", r))
    if ser and typ and ser != "0" and typ != "0" and typ not in skun_by_series.setdefault(ser, []):
        skun_by_series[ser].append(typ)
for ser, types in skun_by_series.items():
    add("skun_rule", ser, f"Skun untuk breaker {ser}", {"types": types})

# Koreksi data Excel yang jelas salah (dikabarkan ke pengguna): model 1 fasa yang ditulis 3 fasa.
# Bukti: arus maksimum 26-31 A pada 5-6 kW (= 1 fasa ~230 V) dan nama model X1 / G05P1 (single phase).
PHASE_FIXES = {"X1-5.5K-T-D(L)": 1, "X1-6.0K-T-D(L)": 1, "SUN-5.2K-G05P1-EU-AM2": 1, "SUN-6K-G05P1-EU-AM2": 1, "SUN-6.2K-G05P1-EU-AM2": 1}
for row in data["inverter"]:
    if row["code"] in PHASE_FIXES and row["attrs"].get("phase") != PHASE_FIXES[row["code"]]:
        row["attrs"]["phase_excel"] = row["attrs"].get("phase")
        row["attrs"]["phase"] = PHASE_FIXES[row["code"]]

OUT.parent.mkdir(parents=True, exist_ok=True)
OUT.write_text(json.dumps(data, ensure_ascii=False, indent=1), encoding="utf-8")
print("Tulis", OUT)
for k, v in data.items():
    print(f"  {k:18s} {len(v):4d} baris")
