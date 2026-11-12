"""Read the FIKA sheet (downloaded as .xlsx) into inventory.json: per candy (Inventory tab name) the stock at the last
count (Est. Quant.), ERT code, pack size, price per pack (SEK), and the count date.
Usage: python3 read_inventory.py FIKA.xlsx inventory.json"""
import sys, json, openpyxl
wb = openpyxl.load_workbook(sys.argv[1], data_only=True)
IS, ERT = wb["Inventory System"], wb["ERT ORDER"]
count_date = IS["B8"].value  # Inventory System > Last full count date
inv = {}
for r in IS.iter_rows(min_row=53, max_row=120, values_only=True):  # main candy table starts at row 53
    if not r[0]:
        continue
    inv[r[0]] = dict(code=r[11], pack_kg=r[12], sek_per_pack=r[13], stock_kg=max(0.0, r[27] or 0.0),
                     out_since=str(r[17]) if r[17] else None)
for r in ERT.iter_rows(min_row=2, values_only=True):  # the ERT ORDER tab has the latest quotes
    if not r[0] or r[0] == "Total":
        continue
    for v in inv.values():
        if v["code"] == r[0]:
            v.update(pack_kg=r[3], sek_per_pack=r[4])
json.dump(dict(count_date=str(count_date)[:10], candies=inv), open(sys.argv[2], "w"), indent=1, ensure_ascii=False)
print(len(inv), "candies, counted", str(count_date)[:10], "total", round(sum(v["stock_kg"] for v in inv.values()), 1), "kg")
