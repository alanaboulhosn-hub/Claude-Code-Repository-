#!/usr/bin/env python3
"""Fika sales invoices from WooCommerce orders (REST JSON).

Template v3, 18 Nov 2026: to be approved by the owner and the accountant before the full run.
Customer data: the order JSON and the invoices made from it stay out of git.

  python3 make_invoice.py orders.json out_dir [--vat] [--ids 1292,1300] [--draft]
      --vat    tax invoice: the prices include 11% VAT; the split is shown, and the VAT in Lebanese pounds
      --ids    only these WooCommerce order ids (any status); without it, every completed order
      --draft  allow the [placeholders] still in COMPANY and BANK (samples only)
  node render_pdf.js out_dir       every INV-*.html in out_dir -> A4 PDF (Chromium, via Playwright)
  node check_invoices.js out_dir   layout check: no field wraps, overflows or overlaps; pages per invoice

Dates: the invoice date is the order date (Beirut time). A delivery date is printed only when it came from the
courier's live status updates: the "Delivered" times written by RT spreadsheet imports are the import day."""
import sys, os, re, json, html, base64, datetime
from zoneinfo import ZoneInfo

COMPANY = {
    'name': 'Fika S.A.R.L.',                        # registered name: to confirm
    'address': '[registered address], Lebanon',
    'phone': '+961 79 411 565', 'email': 'hello@swedishfikalb.com', 'web': 'swedishfikalb.com',
    'cr': '[commercial register no.]',
    'tax': '[Ministry of Finance tax no.]',
    'vat': '[VAT no.]',
}
BANK = {'bank': '[bank name]', 'iban': '[IBAN]', 'swift': '[SWIFT / BIC]'}
LBP_RATE = 89500            # Lebanese pound equivalent (the accountant may want the Sayrafa rate for VAT)
VAT_RATE = 0.11
BEIRUT = ZoneInfo('Asia/Beirut')
READY_MIX = {94, 95, 96, 1200, 1201}   # 500 g Ready Mix bags; every other product is pick-and-mix sold per 100 g
ONE_PAGE_LINES = 14        # longer orders take two pages: the last lines go over with the totals (checked on all 661);
                           # the tax invoice has two more total lines, so it holds two invoice lines fewer
FIRST_PAGE_LINES = 22      # most lines page 1 holds when the totals are on page 2
HERE = os.path.dirname(os.path.abspath(__file__))


def _b64(path):
    with open(path, 'rb') as f:
        return base64.b64encode(f.read()).decode()


def _fonts():
    """Inter (text and figures) and Noto Sans Arabic (Arabic names, addresses and the title), embedded so that every
    computer renders the invoice the same way. Both under the SIL Open Font License (fonts/OFL-*.txt)."""
    faces = [('Fika Inter', w, f'inter-latin-{w}-normal.woff2') for w in (400, 500, 600, 700)]
    faces += [('Fika Arabic', w, f'noto-sans-arabic-arabic-{w}-normal.woff2') for w in (400, 600)]
    out = ''
    for fam, w, f in faces:
        p = os.path.join(HERE, 'fonts', f)
        if os.path.exists(p):
            out += f"@font-face{{font-family:'{fam}';font-weight:{w};font-style:normal;src:url(data:font/woff2;base64,{_b64(p)}) format('woff2')}}\n"
    return out



FONTS = _fonts()


def esc(s):
    return html.escape(str(s), quote=True)


def txt(s):
    """WooCommerce sends names HTML-escaped ("Sweet &amp; Sour Mix"); collapse stray whitespace."""
    return re.sub(r'\s+', ' ', html.unescape(str(s or ''))).strip()


def nice(s):
    """Names typed all in lower or upper case get capitals (tina salman -> Tina Salman); others stay as typed."""
    s = txt(s)
    if re.search('[A-Za-z]', s) and (s == s.lower() or s == s.upper()):
        s = re.sub(r'[A-Za-z]+', lambda m: m.group(0)[0].upper() + m.group(0)[1:].lower(), s)
    return s


def first_cap(s):
    s = txt(s)
    return s[:1].upper() + s[1:] if s[:1].islower() else s


def phone(p):
    """Lebanese numbers spaced like the shop's own (+961 76 866 233, +961 3 625 131); anything else as typed."""
    p = txt(p)
    d = re.sub(r'\D', '', p)
    if d.startswith('00'):
        d, p = d[2:], '+' + d[2:]
    m = re.fullmatch(r'961(0?)(\d{7,8})', d) if p.startswith('+') or len(d) > 9 else None
    if m:
        n = m.group(2)
        return '+961 ' + (f'{n[0]} {n[1:4]} {n[4:]}' if len(n) == 7 else f'{n[:2]} {n[2:5]} {n[5:]}')
    if re.fullmatch(r'0\d{7}|[78]\d{7}', d):
        return f'{d[:2]} {d[2:5]} {d[5:]}'
    return p


def r2(x):
    return round(x + (1e-9 if x >= 0 else -1e-9), 2)


def money(x):
    return f'{x:,.2f}'


def signed(x):
    return ('−' if x < 0 else '') + money(abs(x))


def meta(o, k):
    return next((m['value'] for m in o['meta_data'] if m['key'] == k), None)


def local(gmt_iso):
    return datetime.datetime.fromisoformat(gmt_iso).replace(tzinfo=datetime.timezone.utc).astimezone(BEIRUT)


def day(d):
    return f'{d.day:02d} {d:%b %Y}'


def weight(g):
    return f'{g:,} g' if g < 1000 else f'{g / 1000:g} kg'


def words(amount):
    """US dollar amount in English words, as invoices often carry it."""
    ones = ('zero one two three four five six seven eight nine ten eleven twelve thirteen fourteen fifteen sixteen '
            'seventeen eighteen nineteen').split()
    tens = 'zero ten twenty thirty forty fifty sixty seventy eighty ninety'.split()

    def w(n):
        if n < 20:
            return ones[n]
        if n < 100:
            return tens[n // 10] + ('-' + ones[n % 10] if n % 10 else '')
        if n < 1000:
            return ones[n // 100] + ' hundred' + (' and ' + w(n % 100) if n % 100 else '')
        return w(n // 1000) + ' thousand' + (' ' + w(n % 1000) if n % 1000 else '')
    cents = round(amount * 100)
    d, c = cents // 100, cents % 100
    s = w(d).capitalize() + ' US dollar' + ('s' if d != 1 else '')
    return s + (' and ' + w(c) + ' cent' + ('s' if c != 1 else '') if c else '') + ' only'


def delivered_on(o):
    """Delivery day from the courier's live updates only; spreadsheet imports carry a note and the import time."""
    for h in reversed(meta(o, '_fika_rtd_history') or []):
        if h.get('slug') == 'Delivered' and not h.get('note'):
            return datetime.datetime.fromtimestamp(int(h['time']), BEIRUT)
    return None


def discount_label(code):
    c = code.upper()
    if c.startswith('SWIM'):
        return 'Fika Club reward'
    if c.startswith('IOU'):
        return 'Store credit'
    if c.startswith('WELCOME'):
        return 'Welcome discount'
    if c == 'FIKA10':
        return 'Welcome-back offer'
    return 'Discount'


def invoice_data(o, vat=False):
    """Everything printed on the invoice, with the arithmetic checked; problems are listed, never silently fixed."""
    problems = []
    rows = []
    for i in o['line_items']:
        m = {z['key']: z['value'] for z in i['meta_data']}
        qty = i['quantity']
        amount = float(i['subtotal'])
        price = amount / qty if qty else 0.0
        row = {'name': txt(i['name']), 'note': '', 'qty': qty, 'price': price, 'amount': amount}
        if '_fika_taste' in m:
            row.update(note='Mystery taste: free gift from the Fika Club', unit='50 g', grams=50 * qty)
        elif i['product_id'] in READY_MIX:
            row.update(unit='500 g bag', grams=500 * qty)
            if price < 10:
                problems.append(f'Ready Mix line priced {price:.2f}: {row["name"]}')
        else:
            row.update(unit='100 g', grams=100 * qty)
            if price >= 10:
                problems.append(f'pick-and-mix line priced {price:.2f}: {row["name"]}')
        rows.append(row)
    disc = [(discount_label(c['code']), txt(c['code']).upper(), float(c['discount'])) for c in o['coupon_lines']]
    extra = [(txt(l['method_title']), float(l['total'])) for l in o['shipping_lines']]
    extra += [(txt(f['name']), float(f['total'])) for f in o.get('fee_lines', [])]
    total = float(o['total'])
    sub = r2(sum(r['amount'] for r in rows))
    if abs(sub - sum(d for *_, d in disc) + sum(v for _, v in extra) - total) > 0.005:
        problems.append(f'lines {sub:.2f} - discounts + delivery does not give the total {total:.2f}')
    if o.get('refunds'):
        problems.append('order has refunds')
    tot = [('Subtotal', '', sub)] + [(lab, code, -d) for lab, code, d in disc] + [(t, '', v) for t, v in extra]
    vat_amt = 0.0
    if vat:
        # shelf prices include VAT: the lines show what was charged, and the VAT is taken out of the total
        # (31.20 paid = 28.11 excl. VAT + 3.09 VAT), as retail tax invoices do; no rounding lines
        vat_amt = r2(total * VAT_RATE / (1 + VAT_RATE))
        tot += [('Total excl. VAT', '', r2(total - vat_amt)), ('VAT 11%', '', vat_amt)]
    b, s = o['billing'], o['shipping']
    num = str(meta(o, '_fika_old_order') or o['number'])
    return {
        'num': num, 'inv': f'INV-{num}', 'order': o['number'], 'vat': vat,
        'date': local(o['date_created_gmt']), 'delivered': delivered_on(o),
        'name': nice(f"{s.get('first_name') or b.get('first_name') or ''} {s.get('last_name') or b.get('last_name') or ''}"),
        'street': first_cap(', '.join(x for x in (txt(s.get('address_1') or b.get('address_1')),
                                                  txt(s.get('address_2') or b.get('address_2'))) if x)),
        'city': nice(s.get('city') or b.get('city') or ''),
        'phone': phone(b.get('phone') or s.get('phone')), 'email': txt(b.get('email')),
        'rows': rows, 'tot': tot, 'total': total, 'vat_amt': vat_amt,
        'grams': sum(r['grams'] for r in rows), 'tracking': meta(o, '_fika_rtd_tracking'),
        'cod': o.get('payment_method') == 'cod', 'pay_title': txt(o.get('payment_method_title')),
        'problems': problems,
    }


CSS = """
@page { size: A4; margin: 14mm 0 14mm; }
@page :first { margin-top: 0; }
* { box-sizing: border-box; }
html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
body { margin: 0; background: #fff; color: #16213e;
  font: 400 9pt/1.45 'Fika Inter', Inter, 'Fika Arabic', 'Helvetica Neue', Arial, sans-serif; }
[lang=ar] { font-family: 'Fika Arabic', 'Fika Inter', sans-serif; }
.one { white-space: nowrap; }
.num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
.ph { background: #fff1b8; color: #6b4e00; border-radius: 1mm; padding: 0 1mm; }

.band { background: #fff; padding: 9mm 14mm 0; }
.band-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 10mm; }
.co { min-width: 0; }
.co-name { font-weight: 700; font-size: 18pt; line-height: 1.15; color: #004aad; margin-bottom: 1.8mm; }
.co-line { font-size: 9pt; line-height: 1.5; color: #3c4863; }
.brand { flex: 0 0 auto; display: flex; flex-direction: column; align-items: flex-end; }
.doc-title { text-align: right; }
.doc-title .en { font-weight: 700; font-size: 22pt; line-height: 1.25; letter-spacing: .16em; color: #004aad; }
.doc-title .en .last { letter-spacing: 0; }
.doc-title .ar { margin-top: 1mm; font-weight: 600; font-size: 11pt; line-height: 1.75; color: #6b7894; }
.reg { display: flex; margin-top: 5mm; border-top: .3mm solid #d5dcea; border-bottom: .6mm solid #004aad; }
.reg-item { padding: 2mm 7mm 2.5mm 0; margin-right: 7mm; border-right: .3mm solid #d5dcea; }
.reg-item:last-child { border-right: 0; margin-right: 0; padding-right: 0; }
.reg .lbl { font-weight: 600; font-size: 6.6pt; line-height: 1.3; letter-spacing: .1em; text-transform: uppercase; color: #6b7894; }
.reg .val { margin-top: .7mm; font-weight: 500; font-size: 9pt; line-height: 1.4; color: #16213e; }

.main { padding: 0 14mm; }
h2 { margin: 0 0 2.2mm; padding-bottom: 1.4mm; border-bottom: .35mm solid #d5dcea; font-weight: 700; font-size: 8pt;
  line-height: 1.2; letter-spacing: .14em; text-transform: uppercase; color: #004aad; }
.parties { display: grid; grid-template-columns: 1fr 1fr; gap: 12mm; margin-top: 6mm; }
.cust { min-width: 0; }
.cust .ln { color: #3c4863; line-height: 1.45; unicode-bidi: plaintext; text-align: left; overflow-wrap: anywhere; }
.cust .nm { font-weight: 600; font-size: 10.5pt; line-height: 1.35; color: #16213e; margin-bottom: .6mm; }
.kv { display: grid; grid-template-columns: 27mm minmax(0, 1fr); column-gap: 4mm; row-gap: .5mm; margin: 0; }
.kv dt { color: #6b7894; font-weight: 500; white-space: nowrap; }
.kv dd { margin: 0; color: #16213e; font-weight: 500; white-space: nowrap; }
.paid { display: inline-block; margin-left: 2mm; padding: 0 1.6mm; border: .35mm solid #1d7a46; border-radius: 1mm;
  font-weight: 700; font-size: 6.8pt; line-height: 1.55; letter-spacing: .12em; text-transform: uppercase; color: #1d7a46;
  vertical-align: .2mm; }

.cap { display: flex; justify-content: space-between; gap: 6mm; margin: 5.5mm 0 1.4mm; font-size: 7.6pt; color: #6b7894; }
table.items { width: 100%; border-collapse: collapse; table-layout: fixed; }
.items col.q { width: 13mm; } .items col.u { width: 24mm; } .items col.p { width: 27mm; } .items col.a { width: 27mm; }
.items thead { display: table-header-group; }
.items th { background: #004aad; color: #fff; border: .3mm solid #004aad; padding: 1.9mm 2.6mm; font-weight: 600;
  font-size: 7.3pt; line-height: 1.2; letter-spacing: .1em; text-transform: uppercase; text-align: left; white-space: nowrap; }
.items td { border: .3mm solid #d5dcea; padding: .95mm 2.6mm; line-height: 1.3; color: #16213e; vertical-align: top; }
.items tbody tr:nth-child(even) td { background: #f7f9fd; }
.items .c { text-align: center; }
.items th.num { text-align: right; }
.items .item { overflow-wrap: anywhere; }
.items .note { display: block; font-size: 7.6pt; color: #6b7894; }
.items tr { break-inside: avoid; }
.cont { break-before: page; display: flex; justify-content: space-between; margin-bottom: 3mm; padding-bottom: 1.4mm;
  border-bottom: .3mm solid #d5dcea; font-size: 7.6pt; color: #6b7894; }
.cont b { color: #004aad; font-weight: 600; }

.summary { display: flex; justify-content: space-between; align-items: flex-start; gap: 8mm; margin-top: 5mm; break-inside: avoid; }
.thanks { flex: 1 1 auto; min-width: 0; }
.thanks .big { font-weight: 700; font-size: 13.5pt; line-height: 1.2; color: #16213e; margin: .4mm 0 2.4mm; }
.notes { grid-template-columns: 27mm minmax(0, 1fr); font-size: 8.3pt; row-gap: .6mm; }
.notes dd { color: #2a3654; font-weight: 400; }
.notes dd:not(.one) { white-space: normal; }
.notes dd, .pay p, .cust .ln { text-wrap: pretty; }
.totals { flex: 0 0 84mm; }
.totals table { width: 100%; border-collapse: separate; border-spacing: 0 1mm; margin: -1mm 0; }
.totals td { background: #eef3fb; padding: 1.25mm 3.2mm; line-height: 1.35; color: #16213e; }
.totals .code { margin-left: 1.6mm; font-size: 7.6pt; letter-spacing: .03em; color: #6b7894; }
.totals tr.net td { font-weight: 600; }
.totals tr.grand td { background: #004aad; color: #fff; font-weight: 700; font-size: 11pt; padding: 2mm 3.2mm; }

.pay { margin-top: 5.5mm; break-inside: avoid; }
.pay-grid { display: grid; grid-template-columns: 46mm minmax(0, 1fr) 38mm; gap: 7mm; align-items: start; }
.pay h3 { margin: 0 0 1.2mm; font-weight: 600; font-size: 9pt; line-height: 1.3; color: #16213e;
  text-decoration: underline; text-decoration-thickness: .25mm; text-underline-offset: .8mm; }
.pay p { margin: 0; color: #3c4863; line-height: 1.45; }
.pay .kv { grid-template-columns: 22mm minmax(0, 1fr); column-gap: 3mm; }
.pay .kv dt, .pay .kv dd { font-weight: 400; }
.stamp { height: 20mm; border: .35mm dashed #b9c5dd; border-radius: 2mm; padding: 1.5mm 2.4mm; font-size: 7.2pt; color: #8590aa; }
"""


def render(d):
    ph = lambda v: f'<span class="ph">{esc(v)}</span>' if str(v).startswith('[') else esc(v)
    vat = d['vat']
    title, title_ar = ('Tax invoice', 'فاتورة ضريبية') if vat else ('Invoice', 'فاتورة')
    reg = [('Commercial register no.', COMPANY['cr']), ('Tax no. (Ministry of Finance)', COMPANY['tax'])]
    if vat:
        reg.append(('VAT registration no.', COMPANY['vat']))
    reg_html = ''.join(f'<div class="reg-item"><div class="lbl one">{esc(l)}</div><div class="val one">{ph(v)}</div></div>'
                       for l, v in reg)
    cust = ''.join(f'<div class="ln{c}">{esc(v)}</div>' for v, c in (
        (d['name'], ' nm'), (d['street'], ''), (d['city'], ''), (d['phone'], ' one'), (d['email'], ' one')) if v)
    pay = 'Cash on delivery' if d['cod'] else (d['pay_title'] or 'Paid')
    details = [('Invoice no.', esc(d['inv'])), ('Invoice date', day(d['date'])), ('Order no.', f"#{esc(d['order'])}"),
               ('Payment', f'{esc(pay)}<span class="paid">Paid</span>')]
    details_html = ''.join(f'<dt>{l}</dt><dd class="one">{v}</dd>' for l, v in details)
    caption = 'Amounts in US dollars (USD), including 11% VAT' if vat else 'Amounts in US dollars (USD)'
    rows = [
        f'<tr><td class="item"><span class="nm">{esc(r["name"])}</span>' + (f'<span class="note">{esc(r["note"])}</span>' if r['note'] else '') + '</td>'
        f'<td class="c num">{r["qty"]}</td><td class="one">{esc(r["unit"])}</td>'
        f'<td class="num">{money(r["price"])}</td><td class="num">{money(r["amount"])}</td></tr>' for r in d['rows']]
    head = ('<table class="items"><colgroup><col><col class="q"><col class="u"><col class="p"><col class="a"></colgroup>'
            '<thead><tr><th>Description</th><th class="c">Qty</th><th>Unit</th><th class="num">Unit price</th>'
            '<th class="num">Amount</th></tr></thead><tbody>')
    if len(rows) > ONE_PAGE_LINES - (2 if vat else 0):   # page 2 opens with a "continued" line and the last lines, then the totals
        split = min(len(rows) - 3, FIRST_PAGE_LINES)
        table = (head + ''.join(rows[:split]) + '</tbody></table>'
                 + f'<div class="cont"><span><b>{esc(COMPANY["name"])}</b> · Invoice {esc(d["inv"])} · continued</span>'
                 + f'<span class="one">{caption}</span></div>' + head + ''.join(rows[split:]) + '</tbody></table>')
    else:
        table = head + ''.join(rows) + '</tbody></table>'
    tot = ''.join(
        f'<tr class="{"net" if lab == "Total excl. VAT" else ""}"><td class="one">{esc(lab)}'
        + (f'<span class="code">{esc(code)}</span>' if code else '') + f'</td><td class="num">{signed(v)}</td></tr>'
        for lab, code, v in d['tot'])
    grand = 'Total incl. VAT' if vat else 'Total paid'
    total, lbp = d['total'], round(d['total'] * LBP_RATE)
    notes = [('Total weight', weight(d['grams']), True), ('Amount in words', words(total), False),
             ('Total in LBP', f'LBP {lbp:,} at {LBP_RATE:,} per USD', True)]
    if vat:
        notes.append(('VAT in LBP', f"LBP {round(d['vat_amt'] * LBP_RATE):,} (11% VAT, USD {money(d['vat_amt'])})", True))
    else:
        notes.append(('VAT', 'Not charged: Fika was not registered for VAT on the invoice date.', False))
    notes_html = ''.join(f'<dt>{esc(l)}</dt><dd{" class=one" if one else ""}>{esc(v)}</dd>' for l, v, one in notes)
    cash = ['<p>Paid in full, in cash, to our courier RT Deliveries.</p>' if d['tracking'] else
            '<p>Paid in full, in cash, on delivery.</p>']
    if d['tracking']:
        cash.append(f'<p class="one">Tracking no.: {esc(d["tracking"])}</p>')
    if d['delivered']:
        cash.append(f'<p class="one">Delivered: {day(d["delivered"])}</p>')
    bank = [('Bank', BANK['bank']), ('Account name', COMPANY['name']), ('IBAN', BANK['iban']), ('SWIFT / BIC', BANK['swift'])]
    bank_html = ''.join(f'<dt>{esc(l)}</dt><dd class="one">{ph(v)}</dd>' for l, v in bank)
    return f'''<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<title>{title} {esc(d['inv'])} · {esc(COMPANY['name'])}</title>
<meta name="fika-invoice" content="{esc(d['inv'])}"><meta name="fika-company" content="{esc(COMPANY['name'])}">
<meta name="fika-contact" content="Questions about this invoice: {esc(COMPANY['email'])} · WhatsApp {esc(COMPANY['phone'])}">
<style>{FONTS}{CSS}</style></head>
<body>
<header class="band">
  <div class="band-top">
    <div class="co">
      <div class="co-name one">{ph(COMPANY['name'])}</div>
      <div class="co-line one">{ph(COMPANY['address'])}</div>
      <div class="co-line one">{esc(COMPANY['phone'])} (WhatsApp)</div>
      <div class="co-line one">{esc(COMPANY['email'])}</div>
      <div class="co-line one">{esc(COMPANY['web'])}</div>
    </div>
    <div class="brand">
      <div class="doc-title"><div class="en one">{title.upper()[:-1]}<span class="last">{title.upper()[-1]}</span></div><div class="ar one" lang="ar" dir="rtl">{title_ar}</div></div>
    </div>
  </div>
  <div class="reg">{reg_html}</div>
</header>
<main class="main">
  <section class="parties">
    <div class="cust"><h2>Bill to</h2>{cust}</div>
    <div class="details"><h2>Invoice details</h2><dl class="kv">{details_html}</dl></div>
  </section>
  <div class="cap"><span class="one">Pick-and-mix is priced per 100 g, Ready Mix per 500 g bag</span><span class="one">{caption}</span></div>
  {table}
  <section class="summary">
    <div class="thanks"><div class="big">Thank you for choosing Fika!</div><dl class="kv notes">{notes_html}</dl></div>
    <div class="totals"><table>{tot}<tr class="grand"><td class="one">{grand}</td><td class="num">${money(total)}</td></tr></table></div>
  </section>
  <section class="pay">
    <h2>Payment information</h2>
    <div class="pay-grid">
      <div><h3>Cash on delivery</h3>{''.join(cash)}</div>
      <div><h3>Bank transfer (wholesale customers)</h3><dl class="kv">{bank_html}</dl></div>
      <div class="stamp">Company stamp and signature</div>
    </div>
  </section>
</main>
</body></html>'''


def placeholders():
    return [v for v in list(COMPANY.values()) + list(BANK.values()) if str(v).startswith('[')]


if __name__ == '__main__':
    args = sys.argv[1:]
    if len(args) < 2:
        sys.exit(__doc__)
    src, out = args[0], args[1]
    vat, draft = '--vat' in args, '--draft' in args
    ids = {int(x) for x in args[args.index('--ids') + 1].split(',')} if '--ids' in args else None
    if placeholders() and not draft:
        sys.exit('Company or bank details still missing: ' + ', '.join(placeholders()) +
                 '\nFill them in at the top of this file, or pass --draft for samples.')
    orders = json.load(open(src))
    orders = orders if isinstance(orders, list) else [orders]
    os.makedirs(out, exist_ok=True)
    n, flagged = 0, []
    for o in orders:
        if (ids and o['id'] not in ids) or (not ids and o['status'] != 'completed'):
            continue
        d = invoice_data(o, vat)
        with open(os.path.join(out, f"{d['inv']}{'-VAT' if vat else ''}.html"), 'w', encoding='utf-8') as f:
            f.write(render(d))
        n += 1
        flagged += [f"{d['inv']}: {p}" for p in d['problems']]
    print('wrote', n, 'invoice(s) to', out)
    for p in flagged:
        print('CHECK', p)
