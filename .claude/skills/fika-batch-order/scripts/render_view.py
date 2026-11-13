"""Render the order (out.xlsx.json from plan_order.py) as one HTML page to show the owner. Kilograms only.
Usage: python3 render_view.py out.xlsx.json view.html"""
import sys, json, html, datetime
d = json.load(open(sys.argv[1]))
T, rows = d["T"], d["rows"]
f = lambda v, n=1: "" if v in (None, "") else f"{v:,.{n}f}"
body = ""
for r in rows:
    est = "No sales this batch" in (r["note"] or "")
    dev = r["runout"] - T
    pill = "ok" if abs(dev) <= 7 else ("late" if dev > 0 else "early")
    rd = datetime.date.fromisoformat(r["rdate"]).strftime("%d %b")
    body += (f'<tr class="{"est" if est else ""}"><td class="c">{html.escape(r["shop"])}<div class="sub">{html.escape(r["sheet"])} · {html.escape(str(r["code"]))}</div></td>'
             f'<td>{f(r["loose"],2)}</td><td>{f(r["mix"],2)}</td><td>{f(r["stock"])}</td><td>{f(r["arr"])}</td><td class="b">{f(r["kg"])}</td>'
             f'<td><span class="pill {pill}">{rd} · day {r["runout"]}</span></td><td class="n">{html.escape(r["note"] or "")}</td></tr>')
tot = sum(r["kg"] for r in rows)
body += f'<tr class="tot"><td class="c">Total</td><td>{f(sum(r["loose"] for r in rows),2)}</td><td>{f(sum(r["mix"] for r in rows),2)}</td><td>{f(sum(r["stock"] for r in rows))}</td><td>{f(sum(r["arr"] for r in rows))}</td><td class="b">{f(tot)}</td><td></td><td></td></tr>'
ok = sum(1 for r in rows if abs(r["runout"] - T) <= 7)
cost = ""
if d.get("landed_per_kg"):
    base = tot * d["landed_per_kg"]
    cost = f'Landed cost at ${d["landed_per_kg"]} per kg: about ${base:,.0f}' + (f', plus VAT (about ${d["vat_usd"]:,.0f}): about ${base + d["vat_usd"]:,.0f} in all.' if d.get("vat_usd") else ".")
css = """:root{--bg:#fff;--fg:#1d2327;--mut:#646970;--line:#e2e4e7;--head:#0b4eae;--est:#fff6d6;--tot:#eef3fb;--ok:#e3f4e8;--okf:#1a6b35;--warn:#fde8e4;--warnf:#a3341f;--late:#e8eefc;--latef:#24489e}
@media (prefers-color-scheme:dark){:root:not([data-theme="light"]){--bg:#16181b;--fg:#e8eaed;--mut:#9aa0a6;--line:#2c3036;--head:#2f6fd6;--est:#3a3214;--tot:#1e2a3d;--ok:#16301f;--okf:#7fd49b;--warn:#3d1f1a;--warnf:#f2a493;--late:#1c2740;--latef:#9db8f2}}
:root[data-theme="dark"]{--bg:#16181b;--fg:#e8eaed;--mut:#9aa0a6;--line:#2c3036;--head:#2f6fd6;--est:#3a3214;--tot:#1e2a3d;--ok:#16301f;--okf:#7fd49b;--warn:#3d1f1a;--warnf:#f2a493;--late:#1c2740;--latef:#9db8f2}
body{margin:0;background:var(--bg);color:var(--fg);font:14px/1.4 -apple-system,Segoe UI,Roboto,sans-serif;padding:16px}
h1{font-size:20px;margin:0 0 4px} p{margin:4px 0 12px;color:var(--mut);max-width:920px}
.wrap{overflow-x:auto;border:1px solid var(--line);border-radius:10px} table{border-collapse:collapse;width:100%;min-width:900px}
th{background:var(--head);color:#fff;font-weight:600;font-size:12px;text-align:right;padding:8px;position:sticky;top:0}
th:first-child,td.c{text-align:left} td{padding:7px 8px;border-top:1px solid var(--line);text-align:right;vertical-align:top;font-variant-numeric:tabular-nums}
td.b{font-weight:700} td.n{text-align:left;color:var(--mut);font-size:12px;min-width:220px}
.sub{font-size:11px;color:var(--mut)} tr.est td{background:var(--est)} tr.tot td{background:var(--tot);font-weight:700}
.pill{display:inline-block;padding:2px 8px;border-radius:10px;font-size:12px;white-space:nowrap}
.pill.ok{background:var(--ok);color:var(--okf)} .pill.early{background:var(--warn);color:var(--warnf)} .pill.late{background:var(--late);color:var(--latef)} .key span{margin-right:10px}"""
page = f'''<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Next Batch Order</title><style>{css}</style></head><body>
<h1>Next batch order</h1><p>{html.escape(d["subtitle"])}</p>
<p><b>{ok} of {len(rows)} candies run out within a week of the common day.</b> The rest are off because of fixed supplier pack sizes, stock they already have, or amounts set by hand.</p>
<p class="key"><span class="pill ok">within a week</span><span class="pill early">earlier</span><span class="pill late">later</span><span class="pill" style="background:var(--est)">yellow row</span> = no sales this batch, estimated from an earlier batch</p>
<div class="wrap"><table><thead><tr><th>Candy</th><th>Loose sales (kg/day)</th><th>Ready Mix use (kg/day)</th><th>Stock at count (kg)</th><th>Expected on arrival (kg)</th><th>Order (kg)</th><th>Runs out</th><th>Notes</th></tr></thead>
<tbody>{body}</tbody></table></div><p>Use per day is today's level; growth, season and losses are added on top when working out the order. {cost}</p></body></html>'''
open(sys.argv[2], "w").write(page)
print("rendered", len(rows), "candies,", round(tot, 1), "kg,", ok, "within a week")
