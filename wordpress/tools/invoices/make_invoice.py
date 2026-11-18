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
import base64, os
_logo = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..', 'email-assets', 'fika-email-logo.png')
LOGO = 'data:image/png;base64,' + base64.b64encode(open(_logo, 'rb').read()).decode() if os.path.exists(_logo) else ''


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
    vat_note = (f'VAT 11% is included in the prices: USD {money(r2(total - net_total))} = LBP {vat_lbp:,} at LBP {LBP_RATE:,} per USD (rate to confirm: Sayrafa / Central Bank).'
                if vat else 'Not subject to VAT: Fika was not registered for VAT on the date of this sale.')
    tot_rows = ''.join(f'<tr><td>{esc(t)}</td><td class="n">{"−" if v < 0 else ""}$ {money(abs(v))}</td></tr>' for t, v in tot)
    return f'''<!doctype html><html lang="en"><head><meta charset="utf-8"><title>{title} INV-{esc(num)}</title><style>
@page {{ size: A4; margin: 12mm 0 10mm; }}
@page :first {{ margin: 0 0 10mm; }}
* {{ box-sizing: border-box; }}
body {{ font: 9.5pt/1.4 "Helvetica Neue", Arial, sans-serif; color: #1b2a4a; margin: 0; }}
.page {{ width: 210mm; }}
.band {{ background: #fdeaf2; padding: 8mm 14mm 6mm; display: flex; justify-content: space-between; align-items: flex-start; gap: 10mm; }}
.band h1 {{ margin: 0 0 3px; font-size: 17pt; letter-spacing: .03em; color: #004aad; }}
.band p {{ margin: 0; color: #4a5875; font-size: 9.5pt; line-height: 1.45; }}
.band .ids {{ margin-top: 6px; font-size: 9pt; color: #4a5875; }}
.band .ids b {{ color: #1b2a4a; font-weight: 600; }}
.brand {{ text-align: right; flex: 0 0 58mm; }}
.brand img {{ width: 58mm; background: #fff; border-radius: 12px; padding: 6px 10px; display: block; }}
.brand .t {{ margin-top: 6px; font: 700 15pt/1.1 "Helvetica Neue", Arial, sans-serif; color: #004aad; text-transform: uppercase; letter-spacing: .06em; }}
.brand .ar {{ font-size: 13pt; color: #d35d93; direction: rtl; }}
.main {{ padding: 6mm 14mm 0; }}
.two {{ display: grid; grid-template-columns: 1fr 1fr; gap: 10mm; }}
h2 {{ margin: 0 0 4px; font-size: 11pt; letter-spacing: .04em; text-transform: uppercase; color: #1b2a4a; }}
.two p {{ margin: 0; color: #4a5875; }}
.kv {{ display: grid; grid-template-columns: 34mm 1fr; gap: 2px 8px; }}
.kv b {{ color: #1b2a4a; }}
.kv span {{ color: #4a5875; }}
.ph {{ background: #fff3c4; color: #6b4e00; padding: 0 3px; border-radius: 3px; }}
table.items {{ width: 100%; border-collapse: collapse; margin-top: 6mm; }}
.items th {{ background: #004aad; color: #fff; font-weight: 600; text-align: left; padding: 5px 8px; font-size: 9pt; }}
.items td {{ padding: 4px 8px; border: 1px solid #c9d4ea; vertical-align: top; color: #1b2a4a; }}
.items td span {{ display: block; color: #6b7894; font-size: 9pt; }}
.items th.c, .items td.c {{ text-align: center; }}
.n {{ text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }}
.items tr, .sum, .pay, .fine {{ break-inside: avoid; }}
.items thead {{ display: table-header-group; }}
.items th.n {{ text-align: right; }}
.sum {{ display: flex; justify-content: space-between; align-items: flex-start; gap: 10mm; margin-top: 5mm; }}
.thanks {{ flex: 1; }}
.thanks .big {{ font: 700 15pt/1.2 "Helvetica Neue", Arial, sans-serif; color: #1b2a4a; margin-bottom: 8px; }}
.thanks p {{ margin: 2px 0; font-size: 9pt; color: #4a5875; }}
table.tot {{ width: 80mm; border-collapse: separate; border-spacing: 0 3px; }}
.tot td {{ background: #eef3fb; padding: 3px 10px; color: #1b2a4a; }}
.tot tr.grand td {{ background: #004aad; color: #fff; font-weight: 700; font-size: 11.5pt; padding: 5px 10px; }}
.tot tr.lbp td {{ background: none; color: #6b7894; font-size: 9pt; padding: 0 10px; }}
.paid {{ display: inline-block; margin-top: 6px; padding: 3px 10px; border: 2px solid #1e8a4c; color: #1e8a4c; border-radius: 6px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; font-size: 9pt; }}
.pay {{ margin: 6mm 14mm 0; padding-top: 4mm; border-top: 1px solid #c9d4ea; }}
.pay .two h3 {{ margin: 0 0 4px; font-size: 10.5pt; text-decoration: underline; color: #1b2a4a; }}
.fine {{ margin: 5mm 14mm 0; display: flex; justify-content: space-between; gap: 10mm; font-size: 8.5pt; color: #6b7894; }}
.sign {{ flex: 0 0 52mm; height: 18mm; border: 1px dashed #c9d4ea; border-radius: 8px; padding: 5px 7px; }}
</style></head><body><div class="page">
<div class="band"><div>
<h1>{ph(COMPANY['name'])}</h1>
<p>{ph(COMPANY['address'])}<br>{esc(COMPANY['phone'])} (WhatsApp)<br>{esc(COMPANY['email'])} · {esc(COMPANY['web'])}</p>
<div class="ids"><b>Commercial reg.:</b> {ph(COMPANY['cr'])} &nbsp; <b>Tax no. (MoF):</b> {ph(COMPANY['tax'])}{f" &nbsp; <b>VAT no.:</b> {ph(COMPANY['vat'])}" if vat else ''}</div>
</div><div class="brand">{f'<img src="{LOGO}" alt="Fika">' if LOGO else '<div style="font:700 28pt Georgia;color:#004aad">Fika</div>'}<div class="t">{title}</div><div class="ar">{title_ar}</div></div></div>
<div class="main">
<div class="two">
<div><h2>Bill to</h2><p><b style="color:#1b2a4a">{esc(name)}</b><br>{esc(b.get('phone', ''))}{f"<br>{esc(b['email'])}" if b.get('email') else ''}<br>{esc(addr)}</p></div>
<div><h2>Invoice details</h2><div class="kv">
<b>Invoice no.:</b><span>INV-{esc(num)}</span>
<b>Invoice date:</b><span>{deliv:%d/%m/%Y} (delivered)</span>
<b>Order no.:</b><span>#{esc(o['number'])} of {ordered:%d/%m/%Y, %H:%M}</span>
<b>Terms:</b><span>Paid in full on delivery</span>
<b>Currency:</b><span>US dollars (USD)</span></div></div>
</div>
<table class="items"><thead><tr><th>Description</th><th class="c">Weight</th><th class="c">Qty</th><th class="n">Unit price{' excl. VAT' if vat else ''}</th><th class="n">Amount{' excl. VAT' if vat else ''}</th></tr></thead>
<tbody>{''.join(f'<tr><td>{esc(r[0])}</td><td class="c">{esc(r[3] if r[3] != "100 g" else (r[1] or r[3]))}</td><td class="c">{r[2]}</td><td class="n">$ {money(nl / r[2] if r[2] else 0)}</td><td class="n">$ {money(nl)}</td></tr>' for r, nl in zip(rows, net_lines))}</tbody></table>
<div class="sum"><div class="thanks"><div class="big">Thank you for choosing Fika!</div>
<p><b>Total weight:</b> {grams / 1000:g} kg</p><p><b>Amount in words:</b> {esc(words(total))}</p><p>{esc(vat_note)}</p><span class="paid">Paid · cash on delivery</span></div>
<table class="tot">{tot_rows}<tr class="grand"><td>Total{' incl. VAT' if vat else ''} paid</td><td class="n">$ {money(total)}</td></tr><tr class="lbp"><td colspan="2" class="n">Equivalent: LBP {round(total * LBP_RATE):,} at LBP {LBP_RATE:,} per USD</td></tr></table></div>
</div>
<div class="pay"><h2>Payment information</h2><div class="two">
<div><h3>Cash on delivery</h3><p>Paid in cash to our courier, RT Deliveries, on {deliv:%d/%m/%Y}.{f"<br>Parcel tracking no.: {esc(track)}" if track else ''}</p></div>
<div><h3>Bank transfer (wholesale customers)</h3><div class="kv"><b>Bank:</b><span>{ph('[bank name]')}</span><b>Account name:</b><span>{ph(COMPANY['name'])}</span><b>IBAN:</b><span>{ph('[IBAN]')}</span></div></div>
</div></div>
<div class="fine"><div>Prices per 100 g of pick-and-mix or per 500 g Ready Mix bag. Stamp duty on this invoice is settled through Fika's monthly stamp duty declaration (to confirm with the accountant).<br>Questions about this invoice: {esc(COMPANY['email'])} · WhatsApp {esc(COMPANY['phone'])}</div><div class="sign">Company stamp and signature</div></div>
</div></body></html>'''


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
