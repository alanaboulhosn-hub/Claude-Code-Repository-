#!/usr/bin/env python3
"""Fika sales invoices from WooCommerce orders (REST JSON).

Template v3, 18 Nov 2026: to be approved by the owner and the accountant before the full run.
Customer data: the order JSON and the invoices made from it stay out of git.

  python3 make_invoice.py orders.json out_dir [--vat] [--ids 1292,1300] [--draft]
      --vat    tax invoice: the prices include 11% VAT; the split is shown, and the VAT in Lebanese pounds
      --ids    only these WooCommerce order ids (any status); without it, every completed order
      --draft  allow the [placeholders] still in COMPANY (samples only)
  node render_pdf.js out_dir       every INV-*.html in out_dir -> A4 PDF (Chromium, via Playwright)
  node check_invoices.js out_dir   layout check: no field wraps, overflows or overlaps; pages per invoice

Dates: the invoice date is the order date (Beirut time). A delivery date is printed only when it came from the
courier's live status updates: the "Delivered" times written by RT spreadsheet imports are the import day."""
import sys, os, re, json, html, base64, datetime
from zoneinfo import ZoneInfo

COMPANY = {
    'name': 'Fika S.A.R.L.',                        # registered name: to confirm
    'name_ar': 'فيكا ش.م.م.',                        # registered Arabic name: to confirm from the commercial register
    'address': '[registered address], Lebanon',
    'phone': '+961 79 411 565', 'email': 'hello@swedishfikalb.com', 'web': 'swedishfikalb.com',
    'cr': '[commercial register no.]',
    'tax': '[Ministry of Finance tax no.]',
    'vat': '[VAT no.]',
}
LBP_RATE = 89500            # Lebanese pound equivalent (the accountant may want the Sayrafa rate for VAT)
VAT_RATE = 0.11
BEIRUT = ZoneInfo('Asia/Beirut')
READY_MIX = {94, 95, 96, 1200, 1201}   # 500 g Ready Mix bags; every other product is pick-and-mix sold per 100 g
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
    title = lambda t: ' '.join('-'.join(p.capitalize() for p in x.split('-')) if x != 'and' else x for x in t.split(' '))
    s = title(w(d)) + ' US Dollar' + ('s' if d != 1 else '')
    return s + (' and ' + title(w(c)) + ' Cent' + ('s' if c != 1 else '') if c else '')


def delivered_on(o):
    """Delivery day from the courier's live updates only; spreadsheet imports carry a note and the import time."""
    for h in reversed(meta(o, '_fika_rtd_history') or []):
        if h.get('slug') == 'Delivered' and not h.get('note'):
            return datetime.datetime.fromtimestamp(int(h['time']), BEIRUT)
    return None


def discount_label(code):
    c = code.upper()
    if c.startswith('SWIM'):
        return 'Loyalty discount'
    if c.startswith('IOU'):
        return 'Store credit'
    if c.startswith('WELCOME'):
        return 'Promotional discount'
    if c == 'FIKA10':
        return 'Promotional discount'
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
            row.update(note='Free sample (loyalty programme), no charge', unit='50 g', grams=50 * qty)
        elif i['product_id'] in READY_MIX:
            row.update(unit='500 g', grams=500 * qty)
            if price < 10:
                problems.append(f'Ready Mix line priced {price:.2f}: {row["name"]}')
        else:
            row.update(unit='100 g', grams=100 * qty)
            if price >= 10:
                problems.append(f'pick-and-mix line priced {price:.2f}: {row["name"]}')
        rows.append(row)
    disc = [(discount_label(c['code']), txt(c['code']).upper(), float(c['discount'])) for c in o['coupon_lines']]
    # negative fee lines are discounts (the Black Friday deal, fika-black-friday.php)
    disc += [(txt(f['name']), '', -float(f['total'])) for f in o.get('fee_lines', []) if float(f['total']) < 0]
    # delivery is charged for and kept by the courier (RT), not Fika's revenue: the invoice covers the sweets only
    delivery = sum(float(l['total']) for l in o['shipping_lines']) + sum(float(f['total']) for f in o.get('fee_lines', []) if float(f['total']) > 0)
    sub = r2(sum(r['amount'] for r in rows))
    total = r2(sub - sum(d for *_, d in disc))
    if abs(total + delivery - float(o['total'])) > 0.005:
        problems.append(f'lines {sub:.2f} - discounts + delivery {delivery:.2f} does not give the order total {o["total"]}')
    if o.get('refunds'):
        problems.append('order has refunds')
    tot = [('Subtotal', '', sub)] + [(lab, code, -d) for lab, code, d in disc]
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
.co-name { font-weight: 700; font-size: 18pt; line-height: 1.15; color: #004aad; margin-bottom: .6mm; }
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
.kv { display: grid; grid-template-columns: max-content minmax(0, 1fr); column-gap: 4mm; row-gap: .5mm; margin: 0; }
.kv dt { color: #6b7894; font-weight: 500; white-space: nowrap; }
.kv dd { margin: 0; color: #16213e; font-weight: 500; white-space: nowrap; }
.paid { display: inline-block; margin-left: 2mm; padding: 0 1.6mm; border: .35mm solid #1d7a46; border-radius: 1mm;
  font-weight: 700; font-size: 6.8pt; line-height: 1.55; letter-spacing: .12em; text-transform: uppercase; color: #1d7a46;
  vertical-align: .2mm; }

table.items { width: 100%; border-collapse: collapse; table-layout: fixed; }
.parties + table.items { margin-top: 6mm; }
.items col.q { width: 13mm; } .items col.u { width: 28mm; } .items col.p { width: 27mm; } .items col.a { width: 27mm; }
.items thead { display: table-header-group; }
.items th { background: #004aad; color: #fff; border: .3mm solid #004aad; padding: 1.9mm 2.6mm; font-weight: 600;
  font-size: 7.3pt; line-height: 1.3; letter-spacing: .1em; text-transform: uppercase; text-align: left; white-space: nowrap; }
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

.summary { display: flex; justify-content: space-between; align-items: flex-start; gap: 7mm; margin-top: 5mm; break-inside: avoid; }
.thanks { flex: 1 1 auto; min-width: 0; }
.notes { grid-template-columns: max-content minmax(0, 1fr); font-size: 8.3pt; row-gap: .6mm; }
.notes dd { color: #2a3654; font-weight: 400; }
.notes dd:not(.one) { white-space: normal; }
.notes dd, .pay p, .cust .ln { text-wrap: pretty; }
.totals { flex: 0 0 78mm; }
.totals table { width: 100%; border-collapse: separate; border-spacing: 0 1mm; margin: -1mm 0; }
.totals td { background: #eef3fb; padding: 1.25mm 3.2mm; line-height: 1.35; color: #16213e; }
.totals .code { margin-left: 1.6mm; font-size: 7.6pt; letter-spacing: .03em; color: #6b7894; }
.totals tr.net td { font-weight: 600; }
.totals tr.grand td { background: #004aad; color: #fff; font-weight: 700; font-size: 11pt; padding: 2mm 3.2mm; }

.pay { margin-top: 5.5mm; break-inside: avoid; }
.pay-grid { display: grid; grid-template-columns: 60mm minmax(0, 1fr) 34mm; gap: 7mm; align-items: start; }
.pay h3 { margin: 0 0 1.2mm; font-weight: 600; font-size: 9pt; line-height: 1.3; color: #16213e;
  text-decoration: underline; text-decoration-thickness: .25mm; text-underline-offset: .8mm; }
.pay p { margin: 0; color: #3c4863; line-height: 1.45; }
.pay .kv { grid-template-columns: max-content minmax(0, 1fr); column-gap: 3mm; }
.pay .kv dt, .pay .kv dd { font-weight: 400; }
.ar, .dar, .h2ar, .thar, .tar, .h3ar, .nar, .par { font-family: 'Fika Arabic', 'Fika Inter', sans-serif; letter-spacing: 0; text-transform: none; }
.dar { margin-left: 1.6mm; color: #8a94ad; font-weight: 400; font-size: .9em; }
.kv dt, .kv dd { line-height: 1.7; }
.kv { row-gap: 0; }
.co-name-ar { font-weight: 600; font-size: 11pt; line-height: 1.7; color: #004aad; margin: 0 0 .6mm; text-align: left; unicode-bidi: plaintext; }
h2 { display: flex; justify-content: space-between; align-items: baseline; }
.h2ar { font-size: 9pt; font-weight: 600; color: #004aad; }
.thar { display: block; margin-top: .4mm; font-size: 7.4pt; font-weight: 400; opacity: .85; line-height: 1.6; }
.tar { display: block; font-size: 7.6pt; line-height: 1.6; color: #6b7894; font-weight: 400; unicode-bidi: plaintext; text-align: left; }
.totals tr.grand .tar { color: #dfe8fb; font-size: 8pt; }
.totals td.num { vertical-align: middle; }
.nar, .par { unicode-bidi: plaintext; text-align: left; color: #5d6985; line-height: 1.6; }
.h3ar { display: block; margin: -.6mm 0 1mm; font-size: 8.3pt; font-weight: 600; color: #3c4863; }
.items .note[lang=ar] { unicode-bidi: plaintext; }
.paid [lang=ar] { font-family: 'Fika Arabic', sans-serif; letter-spacing: 0; }
.reg .dar { display: block; margin: .3mm 0 0; font-size: 7pt; line-height: 1.5; }
.notes .dar { display: block; margin: 0; line-height: 1.5; }
.notes dt { line-height: 1.45; }
.notes dd { line-height: 1.5; }
.notes { row-gap: 1.6mm; }
table.info { width: 100%; border-collapse: collapse; font-size: 8.4pt; }
.info th, .info td { border: .3mm solid #d5dcea; padding: 1mm 2.4mm; vertical-align: top; text-align: left; }
.info th { width: 30mm; background: #f7f9fd; font-weight: 500; color: #3c4863; }
.info .ien { display: block; line-height: 1.35; white-space: nowrap; }
.info .iar { display: block; font-size: 7.4pt; font-weight: 400; color: #6b7894; line-height: 1.6; unicode-bidi: plaintext; text-align: left; white-space: nowrap; }
.info td { color: #16213e; line-height: 1.45; }
.info td .nar { color: #3c4863; }
.info .rate { display: block; font-size: 7.6pt; color: #6b7894; }
.kv dd.wrap { white-space: normal; overflow-wrap: anywhere; unicode-bidi: plaintext; text-align: left; text-wrap: pretty; }
.cust .kv dd.nm { font-weight: 600; }
.stamp { margin-top: 4mm; height: 20mm; border: .35mm dashed #b9c5dd; border-radius: 2mm; padding: 1.5mm 2.4mm; font-size: 7.2pt; color: #8590aa; }
"""


AR = {
    'Subtotal': 'المجموع الفرعي', 'Loyalty discount': 'حسم برنامج الولاء', 'Promotional discount': 'حسم ترويجي',
    'Store credit': 'رصيد شرائي', 'Discount': 'حسم', 'Delivery in Beirut': 'التوصيل داخل بيروت',
    'Delivery outside Beirut': 'التوصيل خارج بيروت', 'Total excl. VAT': 'المجموع من دون الضريبة',
    'VAT 11%': 'الضريبة على القيمة المضافة 11%', 'Total paid': 'المجموع المدفوع', 'Total incl. VAT': 'المجموع مع الضريبة',
}


def words_ar(amount):
    """The amount in Arabic words, as Lebanese invoices carry it."""
    ones = ['', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة', 'عشرة', 'أحد عشر', 'اثنا عشر',
            'ثلاثة عشر', 'أربعة عشر', 'خمسة عشر', 'ستة عشر', 'سبعة عشر', 'ثمانية عشر', 'تسعة عشر']
    tens = ['', '', 'عشرون', 'ثلاثون', 'أربعون', 'خمسون', 'ستون', 'سبعون', 'ثمانون', 'تسعون']
    hund = ['', 'مائة', 'مائتان', 'ثلاثمائة', 'أربعمائة', 'خمسمائة', 'ستمائة', 'سبعمائة', 'ثمانمائة', 'تسعمائة']

    def w(n):
        if n < 20:
            return ones[n]
        if n < 100:
            return (ones[n % 10] + ' و' if n % 10 else '') + tens[n // 10]
        if n < 1000:
            return hund[n // 100] + (' و' + w(n % 100) if n % 100 else '')
        k, r = n // 1000, n % 1000
        th = 'ألف' if k == 1 else 'ألفان' if k == 2 else w(k) + (' آلاف' if k <= 10 else ' ألف')
        return th + (' و' + w(r) if r else '')

    def counted(n, one, two, few, many):     # Arabic counted-noun forms
        if n == 1:
            return f'{one} واحد'
        if n == 2:
            return two
        t = n % 100
        noun = few if 3 <= t <= 10 else many if 11 <= t <= 99 else one
        return f'{w(n)} {noun}'
    cents = round(amount * 100)
    d, c = cents // 100, cents % 100
    parts = []
    if d:
        parts.append(counted(d, 'دولار أميركي', 'دولاران أميركيان', 'دولارات أميركية', 'دولاراً أميركياً'))
    if c:
        parts.append(counted(c, 'سنت', 'سنتان', 'سنتات', 'سنتاً'))
    return ' و'.join(parts or ['صفر دولار'])


def ar(s, cls='ar'):
    return f'<span class="{cls}" lang="ar" dir="rtl">{esc(s)}</span>' if s else ''


def render(d):
    ph = lambda v: f'<span class="ph">{esc(v)}</span>' if str(v).startswith('[') else esc(v)
    vat = d['vat']
    title, title_ar = ('Tax invoice', 'فاتورة ضريبية') if vat else ('Invoice', 'فاتورة')
    h2 = lambda en, a: f'<h2><span>{en}</span>{ar(a, "h2ar")}</h2>'
    lab = lambda en, a: f'{esc(en)}{ar(a, "dar")}'          # English label with its Arabic beside it
    reg = [('Commercial register no.', 'رقم السجل التجاري', COMPANY['cr']),
           ('Tax no. (Ministry of Finance)', 'الرقم المالي', COMPANY['tax'])]
    if vat:
        reg.append(('VAT registration no.', 'رقم التسجيل في الضريبة على القيمة المضافة', COMPANY['vat']))
    reg_html = ''.join(f'<div class="reg-item"><div class="lbl one">{lab(l, a)}</div><div class="val one">{ph(v)}</div></div>'
                       for l, a, v in reg)
    address = ', '.join(x for x in (d['street'], d['city']) if x)
    cust = ''.join(f'<dt>{l}</dt><dd class="{c}">{esc(v)}</dd>' for l, v, c in (
        (lab('Name', 'الاسم'), d['name'], 'wrap nm'), (lab('Address', 'العنوان'), address, 'wrap'),
        (lab('Phone', 'الهاتف'), d['phone'], 'one'), (lab('Email', 'البريد الإلكتروني'), d['email'], 'wrap')) if v)
    pay_en, pay_ar = ('Cash on delivery', 'نقداً عند التسليم') if d['cod'] else (d['pay_title'] or 'Paid', '')
    details = [(lab('Invoice no.', 'رقم الفاتورة'), esc(d['inv'])), (lab('Invoice date', 'تاريخ الفاتورة'), day(d['date'])),
               (lab('Order no.', 'رقم الطلب'), f"#{esc(d['order'])}"),
               (lab('Payment', 'طريقة الدفع'), f'{esc(pay_en)}{ar(pay_ar, "dar")}'),
               (lab('Currency', 'العملة'), f'US dollar (USD){ar("دولار أميركي", "dar")}')]
    details_html = ''.join(f'<dt>{l}</dt><dd class="one">{v}</dd>' for l, v in details)
    units = {}
    rows = []
    for r in d['rows']:
        u_en, u_ar = units.get(r['unit'], (r['unit'], ''))
        note = (f'<span class="note">{esc(r["note"])}</span><span class="note" lang="ar" dir="rtl">عيّنة مجانية من برنامج الولاء، من دون مقابل</span>'
                if r['note'] else '')
        rows.append(f'<tr><td class="item"><span class="nm">{esc(r["name"])}</span>{note}</td>'
                    f'<td class="c num">{r["qty"]}</td><td class="one">{esc(u_en)}{ar(u_ar, "dar")}</td>'
                    f'<td class="num">{money(r["price"])}</td><td class="num">{money(r["amount"])}</td></tr>')
    th = lambda en, a, c='': f'<th{c}>{en}{ar(a, "thar")}</th>'
    head = ('<table class="items"><colgroup><col><col class="q"><col class="u"><col class="p"><col class="a"></colgroup><thead><tr>'
            + th('Description', 'البيان') + th('Qty', 'الكمية', ' class="c"') + th('Unit', 'الوحدة')
            + th('Unit price', 'سعر الوحدة', ' class="num"') + th('Amount', 'المبلغ', ' class="num"') + '</tr></thead><tbody>')
    table = head + ''.join(rows) + '</tbody></table>'
    # render_pdf.js measures the page: if the invoice runs past page 1, it moves the last lines (at least 3) and the
    # totals to page 2, opened by this "continued" line
    table += (f'<template id="cont"><span><b>{esc(COMPANY["name"])}</b> · Invoice {esc(d["inv"])} · continued{ar("تابع", "dar")}</span>'
              '</template>')
    tot = ''.join(
        f'<tr class="{"net" if l == "Total excl. VAT" else ""}"><td><span class="one">{esc(l)}'
        + (f'<span class="code">{esc(code)}</span>' if code else '') + f'</span>{ar(AR.get(l, ""), "tar")}</td><td class="num">{signed(v)}</td></tr>'
        for l, code, v in d['tot'])
    grand = 'Total incl. VAT' if vat else 'Total paid'
    total, lbp = d['total'], round(d['total'] * LBP_RATE)
    nar = lambda s: f'<div class="nar" lang="ar" dir="rtl">{esc(s)}</div>'
    cell = lambda en, a: f'<th><span class="ien">{esc(en)}</span><span class="iar" lang="ar" dir="rtl">{esc(a)}</span></th>'
    info = [(cell('Total weight', 'الوزن الإجمالي'), f'<span class="one">{weight(d["grams"])}</span>'),
            (cell('Amount in words', 'المبلغ بالأحرف'), f'<div>{esc(words(total))}</div>{nar(words_ar(total))}'),
            (cell('Equivalent in LBP', 'المعادل بالليرة اللبنانية'), f'<span class="one">LBP {lbp:,}</span><span class="rate one">at 1 USD = {LBP_RATE:,} LBP</span>')]
    if vat:
        info.append((cell('VAT in LBP', 'الضريبة بالليرة اللبنانية'), f"<span class=\"one\">LBP {round(d['vat_amt'] * LBP_RATE):,}</span>"))
    else:
        info.append((cell('VAT', 'الضريبة على القيمة المضافة'),
                     '<div>Not applicable: the company was not registered for VAT on the invoice date.</div>'
                     + nar('غير مطبّقة: لم تكن الشركة مسجّلة في الضريبة على القيمة المضافة بتاريخ الفاتورة.')))
    notes_html = ''.join(f'<tr>{l}<td>{v}</td></tr>' for l, v in info)
    return f'''<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<title>{title} {esc(d['inv'])} · {esc(COMPANY['name'])}</title>
<meta name="fika-invoice" content="{esc(d['inv'])}"><meta name="fika-company" content="{esc(COMPANY['name'])}">
<style>{FONTS}{CSS}</style></head>
<body>
<header class="band">
  <div class="band-top">
    <div class="co">
      <div class="co-name one">{ph(COMPANY['name'])}</div>
      <div class="co-name-ar one" lang="ar" dir="rtl">{ph(COMPANY['name_ar'])}</div>
      <div class="co-line one">{ph(COMPANY['address'])}</div>
      <div class="co-line one">Tel. {esc(COMPANY['phone'])}</div>
      <div class="co-line one">{esc(COMPANY['email'])} · {esc(COMPANY['web'])}</div>
    </div>
    <div class="brand">
      <div class="doc-title"><div class="en one">{title.upper()[:-1]}<span class="last">{title.upper()[-1]}</span></div><div class="ar one" lang="ar" dir="rtl">{title_ar}</div></div>
    </div>
  </div>
  <div class="reg">{reg_html}</div>
</header>
<main class="main">
  <section class="parties">
    <div class="cust">{h2('Bill to', 'فاتورة إلى')}<dl class="kv">{cust}</dl></div>
    <div class="details">{h2('Invoice details', 'تفاصيل الفاتورة')}<dl class="kv">{details_html}</dl></div>
  </section>
  {table}
  <section class="summary">
    <div class="thanks">{h2('Additional information', 'معلومات إضافية')}<table class="info">{notes_html}</table></div>
    <div class="totals"><table>{tot}<tr class="grand"><td><span class="one">{grand}</span>{ar(AR[grand], "tar")}</td><td class="num">${money(total)}</td></tr></table>
      <div class="stamp">Company stamp and signature<br><span lang="ar" dir="rtl">ختم الشركة والتوقيع</span></div></div>
  </section>
</main>
</body></html>'''


def placeholders():
    return [v for v in COMPANY.values() if str(v).startswith('[')]


if __name__ == '__main__':
    args = sys.argv[1:]
    if len(args) < 2:
        sys.exit(__doc__)
    src, out = args[0], args[1]
    vat, draft = '--vat' in args, '--draft' in args
    ids = {int(x) for x in args[args.index('--ids') + 1].split(',')} if '--ids' in args else None
    if placeholders() and not draft:
        sys.exit('Company details still missing: ' + ', '.join(placeholders()) +
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
