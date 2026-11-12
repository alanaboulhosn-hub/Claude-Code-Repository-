"""Check the kilograms in the orders three ways (weight label, quantity, price paid) and print totals by status.
Usage: python3 verify_orders.py orders.json 2026-09-28 2026-11-11 [loose_price_per_100g ... ] 
Default prices: loose 2.80 or 2.50 per 100 g, Ready Mix 14.00 or 12.50 per 500 g bag. Any line that disagrees is listed."""
import sys, json, re, collections
O = json.load(open(sys.argv[1])); A, B = sys.argv[2], sys.argv[3]
LOOSE, MIX = (2.80, 2.50), (14.00, 12.50)
by_status = collections.Counter(); bad = []
for o in O:
    if not (A <= o["date_created"][:10] <= B):
        continue
    kg = 0.0
    for i in o["line_items"]:
        m = {x["key"]: str(x["value"]) for x in i["meta_data"] if not x["key"].startswith("_")}
        q = i["quantity"]; sub = float(i["subtotal"]); unit = sub / q if q else 0
        mystery = "Mystery taste" in m and sub == 0
        mix = unit >= 10 or "Amount" in m
        label = None
        if "Weight" in m:
            x = re.match(r"\s*([\d.]+)\s*(k?g)", m["Weight"]); label = float(x.group(1)) * (1000 if x.group(2) == "kg" else 1)
        elif "Amount" in m:
            x = re.search(r"\(([\d.]+)\s*(k?g)\)", m["Amount"]); label = float(x.group(1)) * (1000 if x.group(2) == "kg" else 1)
        by_qty = (50 if mystery else 500 if mix else 100) * q
        if mystery:
            by_price = by_qty
        elif mix:
            by_price = sub / min(MIX, key=lambda p: abs(unit - p)) * 500
        else:
            by_price = sub / min(LOOSE, key=lambda p: abs(unit - p)) * 100
        lab = label if label is not None else by_qty
        if abs(lab - by_qty) > 0.5 or abs(by_qty - by_price) > 0.5:
            bad.append((o["number"], i["name"], q, sub, m.get("Weight") or m.get("Amount"), lab, by_qty, round(by_price)))
        kg += by_qty / 1000
    by_status[o["status"]] += kg
print("kg by status:", {k: round(v, 1) for k, v in by_status.items()}, "total", round(sum(by_status.values()), 1))
print("lines that disagree:", len(bad))
for b in bad[:50]:
    print("  #%s %s qty %s paid %s label %s -> label %s g, qty %s g, price %s g" % b)
