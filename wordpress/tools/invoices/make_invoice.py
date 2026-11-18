"""Fika sales invoices from WooCommerce orders (REST JSON). Draft template, 18 Nov 2026: to be approved by the owner and the
accountant before the full run. Customer data: the order JSON and the invoices it makes stay out of git.

Usage: python3 make_invoice.py orders.json out_dir [--vat] [--ids 1292,1300]
  --vat  render as a VAT tax invoice (prices include 11% VAT, split shown, VAT also in Lebanese pounds)
Writes one HTML per order (out_dir/INV-<number>.html); render_pdf.js turns them into PDFs.
Company details below are placeholders until the owner gives them."""
import sys, json, html, datetime

COMPANY = {
    'name': 'Fika S.A.R.L.',                       # registered name: to confirm
    'cr': '[commercial register no.]',
    'tax': '[Ministry of Finance tax no.]',
    'vat': '[VAT no.]',
    'address': '[registered address], Lebanon',
    'phone': '+961 79 411 565', 'email': 'hello@swedishfikalb.com', 'web': 'swedishfikalb.com',
}
LBP_RATE = 89500           # Central Bank rate used for the Lebanese pound equivalent
VAT_RATE = 0.11
BEIRUT = datetime.timezone(datetime.timedelta(hours=2))
PH = lambda s: s.startswith('[')


def esc(s):
    return html.escape(str(s))


def words(amount):
    """US dollar amount in English words (invoices often carry the total in words)."""
    ones = 'zero one two three four five six seven eight nine ten eleven twelve thirteen fourteen fifteen sixteen seventeen eighteen nineteen'.split()
    tens = 'zero ten twenty thirty forty fifty sixty seventy eighty ninety'.split()
    def w(n):
        if n < 20: return ones[n]
        if n < 100: return tens[n // 10] + ('-' + ones[n % 10] if n % 10 else '')
        if n < 1000: return ones[n // 100] + ' hundred' + (' and ' + w(n % 100) if n % 100 else '')
        return w(n // 1000) + ' thousand' + (' ' + w(n % 1000) if n % 1000 else '')
    d, c = int(amount), round((amount - int(amount)) * 100)
    s = w(d).capitalize() + ' US dollar' + ('s' if d != 1 else '')
    return s + (' and ' + w(c) + ' cent' + ('s' if c != 1 else '') if c else '') + ' only'


def meta(o, k):
    return next((m['value'] for m in o['meta_data'] if m['key'] == k), None)


def delivered_at(o):
    for h in reversed(meta(o, '_fika_rtd_history') or []):
        if h.get('slug') == 'Delivered':
            return datetime.datetime.fromtimestamp(int(h['time']), BEIRUT)
    d = o.get('date_completed_gmt') or o.get('date_completed')
    return datetime.datetime.fromisoformat(d).replace(tzinfo=datetime.timezone.utc).astimezone(BEIRUT) if d else None


def money(x):
    return f'{x:,.2f}'


def invoice_html(o, vat=False):
    num = meta(o, '_fika_old_order') or o['number']
    ordered = datetime.datetime.fromisoformat(o['date_created_gmt']).replace(tzinfo=datetime.timezone.utc).astimezone(BEIRUT)
    deliv = delivered_at(o) or ordered
    b, s = o['billing'], o['shipping']
    name = f"{s.get('first_name') or b['first_name']} {s.get('last_name') or b['last_name']}".strip()
    area = {'BA': 'Inside Beirut', 'OB': 'Outside Beirut'}.get(s.get('state') or b.get('state'), '')
    addr = ', '.join(x for x in [s.get('address_1') or b.get('address_1'), s.get('address_2'), s.get('city') or b.get('city'), area] if x)
    rows, sub = [], 0.0
    for i in o['line_items']:
        m = {z['key']: z for z in i['meta_data']}
        taste = '_fika_taste' in m
        weight = (m.get('Weight') or m.get('Amount') or m.get('Mystery taste') or {}).get('value', '')
        unit = '500 g bag' if 'Amount' in m else ('free taste' if taste else '100 g')
        qty = i['quantity']
        line = float(i['subtotal'])
        price = line / qty if qty else 0
        sub += line
        rows.append((i['name'] + (' (mystery taste, free)' if taste else ''), weight, qty, unit, price, line))
    disc = [(c['code'].upper(), float(c['discount'])) for c in o['coupon_lines']]
    ship = [(l['method_title'], float(l['total'])) for l in o['shipping_lines']]
    fees = [(f['name'], float(f['total'])) for f in o.get('fee_lines', [])]
    total = float(o['total'])
    grams = 0
    for r in rows:
        if r[3] == '500 g bag': grams += 500 * r[2]
        elif r[3] == '100 g': grams += 100 * r[2]
    paid = 'Cash on delivery' if o['payment_method'] == 'cod' else o.get('payment_method_title') or ''
    track = meta(o, '_fika_rtd_tracking')

    def label(code):
        if code.startswith('SWIM'): return f'Fika Club reward ({code})'
        if code.startswith('FIKA10'): return f'10% welcome-back offer ({code})'
        return f'Discount ({code})'

    k = (1 + VAT_RATE) if vat else 1
    r2 = lambda x: round(x + 1e-9, 2)
    # amounts excl. VAT, rounded so that every column adds up to the cent (the last line takes the rounding)
    net_total = r2(total / k)
    net_ship = [(t, r2(v / k)) for t, v in ship + fees]
    net_disc = [(c, r2(d / k)) for c, d in disc]
    net_sub = r2(net_total - sum(v for _, v in net_ship) + sum(d for _, d in net_disc)) if vat else sub
    net_lines = [r2(r[5] / k) for r in rows]
    if vat and net_lines:
        net_lines[-1] = r2(net_lines[-1] + net_sub - sum(net_lines))
    lines = ''.join(
        f'<tr><td>{n}</td><td class="t"><b>{esc(r[0])}</b><span>{esc(r[1])}</span></td><td>{r[2]}</td><td>{esc(r[3])}</td>'
        f'<td class="n">{money(nl / r[2] if r[2] else 0)}</td><td class="n">{money(nl)}</td></tr>'
        for n, (r, nl) in enumerate(zip(rows, net_lines), 1))
    tot = [('Subtotal (sweets)' + (', excl. VAT' if vat else ''), net_sub)]
    tot += [(label(c), -d) for c, d in net_disc]
    tot += [(t + (', excl. VAT' if vat else ''), v) for t, v in net_ship]
    if vat:
        tot += [('Total excl. VAT', net_total), ('VAT 11%', r2(total - net_total))]
    tot_html = ''.join(f'<tr><td>{esc(t)}</td><td class="n">{"−" if v < 0 else ""}{money(abs(v))}</td></tr>' for t, v in tot)
    vat_lbp = round(round(total - total / k, 2) * LBP_RATE) if vat else 0
    ph = lambda v: f'<span class="ph">{esc(v)}</span>' if PH(v) else esc(v)
    title, title_ar = ('Tax invoice', 'فاتورة ضريبية') if vat else ('Invoice', 'فاتورة')
    vat_note = (f'VAT 11% included in the prices: USD {money(total - total / k)} = LBP {vat_lbp:,} at LBP {LBP_RATE:,}/USD (rate to confirm: Sayrafa / Central Bank).'
                if vat else 'Not subject to VAT: the supplier was not registered for VAT on the date of sale.')
    return f'''<!doctype html><html lang="en"><head><meta charset="utf-8"><title>{title} INV-{esc(num)}</title><style>
@page {{ size: A4; margin: 14mm 14mm 16mm; }}
* {{ box-sizing: border-box; }}
body {{ font: 10.5pt/1.45 "Helvetica Neue", Arial, sans-serif; color: #1b2a4a; margin: 0; }}
.top {{ display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #004aad; padding-bottom: 10px; }}
.logo {{ font: 700 30pt/1 Georgia, serif; color: #004aad; letter-spacing: .02em; }}
.logo small {{ display: block; font: 9pt Arial, sans-serif; color: #6b7894; letter-spacing: 0; margin-top: 4px; }}
.title {{ text-align: right; }}
.title h1 {{ margin: 0; font-size: 20pt; color: #004aad; text-transform: uppercase; letter-spacing: .06em; }}
.title .ar {{ font-size: 14pt; color: #d35d93; direction: rtl; }}
.grid {{ display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin: 14px 0; }}
.box {{ border: 1px solid #e3e9f5; border-radius: 8px; padding: 10px 12px; }}
.box h2 {{ margin: 0 0 6px; font-size: 8.5pt; text-transform: uppercase; letter-spacing: .08em; color: #6b7894; }}
.box p {{ margin: 0; }}
.kv {{ display: grid; grid-template-columns: auto 1fr; gap: 2px 12px; }}
.kv b {{ font-weight: 600; color: #6b7894; font-size: 9pt; }}
.ph {{ background: #fff3c4; color: #6b4e00; padding: 0 3px; border-radius: 3px; }}
table.items {{ width: 100%; border-collapse: collapse; margin-top: 4px; }}
.items th {{ background: #004aad; color: #fff; font-size: 8.5pt; text-transform: uppercase; letter-spacing: .05em; text-align: left; padding: 6px 8px; }}
.items td {{ padding: 6px 8px; border-bottom: 1px solid #eef2f9; vertical-align: top; }}
.items td.t span {{ display: block; color: #6b7894; font-size: 9pt; }}
.n {{ text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }}
.items th.n {{ text-align: right; }}
.sum {{ display: flex; justify-content: space-between; gap: 18px; margin-top: 12px; }}
.sum .left {{ flex: 1; font-size: 9.5pt; }}
table.tot {{ width: 300px; border-collapse: collapse; }}
.tot td {{ padding: 4px 8px; }}
.tot tr.grand td {{ border-top: 2px solid #004aad; font-weight: 700; font-size: 12.5pt; color: #004aad; padding-top: 7px; }}
.tot tr.lbp td {{ color: #6b7894; font-size: 9pt; }}
.paid {{ display: inline-block; margin-top: 8px; padding: 3px 10px; border: 2px solid #1e8a4c; color: #1e8a4c; border-radius: 6px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; font-size: 9pt; }}
.foot {{ margin-top: 18px; padding-top: 10px; border-top: 1px solid #e3e9f5; display: flex; justify-content: space-between; gap: 20px; font-size: 8.5pt; color: #6b7894; }}
.sign {{ width: 210px; height: 70px; border: 1px dashed #c9d4ea; border-radius: 8px; padding: 6px; font-size: 8pt; color: #6b7894; }}
</style></head><body>
<div class="top"><div class="logo">Fika<small>Swedish pick &amp; mix, delivered across Lebanon</small></div>
<div class="title"><h1>{title}</h1><div class="ar">{title_ar}</div></div></div>
<div class="grid">
<div class="box"><h2>From (seller)</h2><p><b>{ph(COMPANY['name'])}</b><br>{ph(COMPANY['address'])}</p>
<div class="kv" style="margin-top:6px"><b>Commercial reg.</b><span>{ph(COMPANY['cr'])}</span><b>Tax no. (MoF)</b><span>{ph(COMPANY['tax'])}</span>{f"<b>VAT no.</b><span>{ph(COMPANY['vat'])}</span>" if vat else ''}<b>Contact</b><span>{esc(COMPANY['phone'])} · {esc(COMPANY['email'])}</span></div></div>
<div class="box"><h2>Invoice details</h2><div class="kv">
<b>Invoice no.</b><span><b style="color:#1b2a4a;font-size:10.5pt">INV-{esc(num)}</b></span>
<b>Invoice date</b><span>{deliv:%d %B %Y} (date delivered)</span>
<b>Order no. / date</b><span>#{esc(o['number'])} · {ordered:%d %B %Y, %H:%M}</span>
<b>Payment</b><span>{esc(paid)}</span>
<b>Delivered by</b><span>RT Deliveries{f" · tracking {esc(track)}" if track else ''}</span>
<b>Currency</b><span>US dollars (USD)</span></div></div>
</div>
<div class="box" style="margin-bottom:12px"><h2>Bill to (customer)</h2><p><b>{esc(name)}</b> · {esc(b.get('phone', ''))}{f" · {esc(b['email'])}" if b.get('email') else ''}<br>{esc(addr)}</p></div>
<table class="items"><thead><tr><th>#</th><th>Item</th><th>Qty</th><th>Unit</th><th class="n">Unit price{' excl. VAT' if vat else ''}</th><th class="n">Amount{' excl. VAT' if vat else ''}</th></tr></thead>
<tbody>{lines}</tbody></table>
<div class="sum"><div class="left"><b>Total weight:</b> {grams / 1000:g} kg<br><b>Amount in words:</b> {esc(words(total))}<br>{esc(vat_note)}<br><span class="paid">Paid · cash on delivery</span></div>
<table class="tot">{tot_html}<tr class="grand"><td>Total{' incl. VAT' if vat else ''}</td><td class="n">USD {money(total)}</td></tr>
<tr class="lbp"><td>Equivalent in Lebanese pounds</td><td class="n">LBP {round(total * LBP_RATE):,}</td></tr></table></div>
<div class="foot"><div>Prices per 100 g of pick-and-mix or per 500 g Ready Mix bag. Stamp duty on this invoice is settled through Fika's monthly stamp duty declaration (to confirm with the accountant).<br>Questions about this invoice: {esc(COMPANY['email'])} · WhatsApp {esc(COMPANY['phone'])} · {esc(COMPANY['web'])}</div>
<div class="sign">Company stamp and signature</div></div>
</body></html>'''


if __name__ == '__main__':
    src, out = sys.argv[1], sys.argv[2]
    vat = '--vat' in sys.argv
    ids = None
    if '--ids' in sys.argv:
        ids = {int(x) for x in sys.argv[sys.argv.index('--ids') + 1].split(',')}
    orders = json.load(open(src))
    orders = orders if isinstance(orders, list) else [orders]
    import os
    os.makedirs(out, exist_ok=True)
    n = 0
    for o in orders:
        if ids and o['id'] not in ids:
            continue
        if not ids and o['status'] != 'completed':
            continue
        num = meta(o, '_fika_old_order') or o['number']
        open(os.path.join(out, f"INV-{num}{'-VAT' if vat else ''}.html"), 'w').write(invoice_html(o, vat))
        n += 1
    print('wrote', n, 'invoice(s) to', out)
