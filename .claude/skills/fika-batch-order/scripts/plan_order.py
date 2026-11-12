"""Plan a batch order so every candy runs out on about the same day.
Usage: python3 plan_order.py config.json orders.json inventory.json out.xlsx
  config.json    the batch settings (see config.batch3.json and SKILL.md)
  orders.json    from fetch_orders.py (every order since the current batch arrived)
  inventory.json from read_inventory.py (the FIKA sheet)
Writes out.xlsx (order sheet + "How it was worked out") and out.xlsx.json (the numbers, for render_view.py)."""
import sys, json, re, math, datetime, collections
import openpyxl
from openpyxl.styles import Font, PatternFill, Alignment

cfg = json.load(open(sys.argv[1]))
orders = json.load(open(sys.argv[2]))
inv = json.load(open(sys.argv[3]))
OUT = sys.argv[4]
D = lambda s: datetime.date.fromisoformat(s)

# ---------- settings ----------
CANDY = {int(k): v for k, v in cfg["candies"].items()}          # product id -> {sheet, shop, group, ...}
MIXES = {int(k): v for k, v in cfg["ready_mixes"].items()}      # product id -> mix key
WIN_FROM, WIN_TO = D(cfg["window"]["from"]), D(cfg["window"]["to"])
WD = (WIN_TO - WIN_FROM).days + 1
TODAY, ARRIVE = D(cfg["today"]), D(cfg["arrival"])
COUNT = D(cfg.get("count_date") or inv["count_date"])
P = cfg["projection"]

# ---------- 1. what sold: kilograms per candy and per Ready Mix in the window ----------
def grams(item):
    m = {x["key"]: str(x["value"]) for x in item["meta_data"] if not x["key"].startswith("_")}
    q = item["quantity"]
    unit = float(item["subtotal"]) / q if q else 0
    if "Weight" in m:                       # "100 g", "1.2 kg"
        x = re.match(r"\s*([\d.]+)\s*(k?g)", m["Weight"])
        return float(x.group(1)) * (1000 if x.group(2) == "kg" else 1)
    if "Amount" in m:                       # Ready Mix: "2 bags (1 kg)" -> 500 g per bag x quantity
        return 500 * q
    if "Mystery taste" in m:
        return 50 * q
    return 500 * q if unit >= 10 else 100 * q  # old-site lines without labels

loose_g, mix_g = collections.Counter(), collections.Counter()
for o in orders:
    if o["status"] in ("cancelled", "failed", "refunded", "trash"):
        continue
    d = D(o["date_created"][:10])
    if not (WIN_FROM <= d <= WIN_TO):
        continue
    for it in o["line_items"]:
        pid = it["product_id"]
        if pid in MIXES:
            mix_g[MIXES[pid]] += grams(it)
        elif pid in CANDY:
            loose_g[pid] += grams(it)

# ---------- 2. use per day, today's level ----------
loose = {}
for pid, c in CANDY.items():
    days = c.get("days_on_sale", WD)                  # a candy that sold out: only its days on sale
    loose[pid] = loose_g[pid] / 1000 / days
SHARE = {int(k): v for k, v in cfg.get("share_from_earlier_batch", {}).items()}
others = sum(v for k, v in loose.items() if k not in SHARE)
for pid, s in SHARE.items():                          # no sales this batch: a share of loose sales from an earlier batch
    loose[pid] = others * s / (1 - sum(SHARE.values()))

sweet = {p for p, c in CANDY.items() if c["group"] == "sweet" and c.get("in_mixes", True)}
sour = {p for p, c in CANDY.items() if c["group"] == "sour" and c.get("in_mixes", True)}
mixuse = collections.defaultdict(float)
M = cfg["mix_use"]
sour_day = mix_g["sour"] / 1000 / WD
sweet_day = mix_g["sweet"] / 1000 / WD
for p in sour: mixuse[p] += sour_day / len(sour)
for p in sweet: mixuse[p] += sweet_day / len(sweet)
ff = M["fan_favourites_bags_per_day"]                  # steady rate after its launch week
for p in sweet | sour: mixuse[p] += ff * M["fan_favourites_base_kg"] / len(sweet | sour)
for k, kg in M["fan_favourites_extras_kg"].items(): mixuse[int(k)] += ff * kg

# ---------- 3. projection: losses, growth, season ----------
def factor(day):
    months = (day - TODAY).days / 30.4
    return (1 + P["growth_per_month"]) ** months * P["season"].get(str(day.month), 1.0)
def F(start, n):
    return sum(factor(start + datetime.timedelta(days=i)) for i in range(n))

rows = []
for pid, c in CANDY.items():
    if c.get("leave_out"):
        continue
    i = inv["candies"].get(c["sheet"], {})
    code = c.get("code") or i.get("code")
    pack = c.get("pack_kg") or i.get("pack_kg") or 3.0
    sek = c.get("sek_per_pack") or i.get("sek_per_pack")
    stock = 0.0 if c.get("stock_zero") else (i.get("stock_kg") or 0.0)
    l, m = loose.get(pid, 0.0), mixuse.get(pid, 0.0)
    rows.append(dict(pid=pid, shop=c["shop"], sheet=c["sheet"], code=code, pack=pack, sek=sek, stock=stock,
                     loose=l, mix=m, day=(l + m) * (1 + P["loss"]), note=c.get("note", "")))
LT = sum(r["loose"] for r in rows)
pre = F(COUNT, (ARRIVE - COUNT).days)
for r in rows:
    r["arr"] = max(0.0, r["stock"] - r["day"] * pre)              # stock left when the batch lands
    r["b2b"] = P["wholesale_kg"] * r["loose"] / LT                # wholesale share, by loose sales

FIXED = {int(k): v for k, v in cfg.get("fixed_kg", {}).items()}   # owner's set amounts, in kg

def runout(r):
    s = r["arr"] + r["packs"] * r["pack"] - r["b2b"]
    d, n = ARRIVE, 0
    while s > 0 and n < 3000:
        s -= r["day"] * factor(d); d += datetime.timedelta(days=1); n += 1
    return n

def build(T):
    post = F(ARRIVE, T)
    for r in rows:
        if r["pid"] in FIXED:
            r["packs"] = max(1, round(FIXED[r["pid"]] / r["pack"]))
        else:
            r["need"] = max(0.0, r["day"] * post - r["arr"]) + r["b2b"]
            r["packs"] = max(1, round(r["need"] / r["pack"]))        # nearest whole pack, at least one
    return sum(r["packs"] * r["pack"] for r in rows)

# ---------- 4. one common run-out day that fills the batch ----------
T = 20
while build(T) < cfg["batch_kg"]:
    T += 1
if abs(build(T - 1) - cfg["batch_kg"]) < abs(build(T) - cfg["batch_kg"]):
    T -= 1
build(T)
for r in rows: r["runout"] = runout(r)
free = [r for r in rows if r["pid"] not in FIXED]
kg = lambda: sum(r["packs"] * r["pack"] for r in rows)
for _ in range(80):   # fine-tune to the batch weight without pulling any candy away from the common day
    if kg() > cfg["batch_kg"] + 1.5:
        def after_less(r):
            r["packs"] -= 1; v = runout(r); r["packs"] += 1; return v
        r = max([r for r in free if r["packs"] > 1], key=after_less); r["packs"] -= 1
    elif kg() < cfg["batch_kg"] - 2.0:
        def after_more(r):
            r["packs"] += 1; v = runout(r); r["packs"] -= 1; return v
        c = [r for r in free if after_more(r) <= T + 7] or free
        r = min(c, key=lambda r: r["runout"]); r["packs"] += 1
    else:
        break
    r["runout"] = runout(r)
for r in rows:
    r["runout"] = runout(r); r["rdate"] = str(ARRIVE + datetime.timedelta(days=r["runout"])); r["kg"] = r["packs"] * r["pack"]
rows.sort(key=lambda r: -(r["loose"] + r["mix"]))
END = ARRIVE + datetime.timedelta(days=T)
total = kg()
print(f"common run-out day {T} ({END}), {total:.1f} kg; within a week: {sum(1 for r in rows if abs(r['runout']-T)<=7)} of {len(rows)}")

# ---------- 5. order sheet ----------
wb = openpyxl.Workbook(); ws = wb.active; ws.title = "Order sheet"
ws.append([cfg.get("title", "FIKA: next batch order")]); ws["A1"].font = Font(bold=True, size=14)
ws.append([f"{cfg.get('status','Draft')}, {TODAY:%d %b %Y}. In stock about {ARRIVE:%d %b}; every candy planned to reach 0 around {END:%d %b %Y} (day {T}). "
           f"Growth {P['growth_per_month']*100:.0f}% a month, season {P['season']}, {P['loss']*100:.0f}% for losses, {P['wholesale_kg']} kg wholesale."])
ws.append([])
H = ["ERT item code", "Candy (FIKA sheet name)", "Shop name", "Loose sales (kg/day)", "Ready Mix use (kg/day)", "Total use (kg/day, today)",
     "Stock at count (kg)", "Expected on arrival (kg)", "Wholesale (kg)", "Pack size (kg)", "Packs to order", "Order (kg)",
     "Runs out (days after arrival)", "Runs out (date)", "Price per pack (SEK)", "Cost (SEK)", "Notes"]
ws.append(H)
for c in ws[4]:
    c.fill = PatternFill("solid", fgColor="0B4EAE"); c.font = Font(color="FFFFFF", bold=True); c.alignment = Alignment(wrap_text=True)
first = 5
for r in rows:
    n = ws.max_row + 1
    note = r["note"]
    if r["runout"] > T + 14 and r["pid"] not in FIXED:
        note = (note + "; " if note else "") + "Already well stocked: even the smallest amount ERT sells lasts past the others"
    ws.append([r["code"] or "(code?)", r["sheet"], r["shop"], round(r["loose"], 3), round(r["mix"], 3), round(r["loose"] + r["mix"], 3),
               round(r["stock"], 2), round(r["arr"], 1), round(r["b2b"], 1), r["pack"], r["packs"], f"=J{n}*K{n}", r["runout"],
               r["rdate"], r["sek"], f'=IF(O{n}="","",K{n}*O{n})', note])
    ws[f"K{n}"].fill = PatternFill("solid", fgColor="FFF4C2")
last = ws.max_row
ws.append(["TOTAL", "", ""] + [f"=SUM({c}{first}:{c}{last})" for c in "DEFGHI"] + ["", f"=SUM(K{first}:K{last})", f"=SUM(L{first}:L{last})", "", "", "", f"=SUM(P{first}:P{last})", ""])
for c in ws[ws.max_row]: c.font = Font(bold=True)
a = wb.create_sheet("How it was worked out")
for line in cfg.get("explanation", []):
    a.append([line])
a.column_dimensions["A"].width = 170
wb.save(OUT)
json.dump(dict(T=T, end=str(END), arrival=str(ARRIVE), total_kg=total, landed_per_kg=cfg.get("landed_usd_per_kg"),
               vat_usd=cfg.get("vat_usd"), subtitle=ws["A2"].value, rows=rows), open(OUT + ".json", "w"), indent=1, ensure_ascii=False)
