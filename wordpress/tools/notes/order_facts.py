#!/usr/bin/env python3
"""Owner's notes: the facts behind one personal sentence and one Instagram question per order.

  python3 order_facts.py orders.json products.json [--ids 1740,1741 | --waiting] > facts.json

For each chosen order: who it is for (first name; gift or not), where they are as a customer (how many orders, since
when, days since the last one), their favourites over all their orders (by grams), sweet / sour leaning, what in this
order is a favourite again and what is new to them, Ready Mix or pick-and-mix, their area, and their own note.
Customers are matched like the KPI page: by phone, else by email. Data only; the sentences are written from it.
Customer data stays out of git: the orders file and the output are kept in a scratch folder."""
import sys, re, json, html, collections, datetime as dt
from zoneinfo import ZoneInfo

B = ZoneInfo('Asia/Beirut')
COUNTED = ('processing', 'on-hold', 'completed')


def txt(s):
    return re.sub(r'\s+', ' ', html.unescape(str(s or ''))).strip()


def first_name(s):
    s = txt(s).split(' ')[0] if txt(s) else ''
    return s[:1].upper() + s[1:].lower() if s and (s == s.lower() or s == s.upper()) else s


def key(o):
    d = re.sub(r'\D', '', o['billing'].get('phone') or '')
    d = d[3:] if d.startswith('961') else d
    d = d.lstrip('0')
    return 'p' + d if len(d) >= 7 else 'e' + (o['billing'].get('email') or '').lower()


def meta(x, k):
    return next((m['value'] for m in x['meta_data'] if m['key'] == k), None)


def main():
    orders, products = json.load(open(sys.argv[1])), json.load(open(sys.argv[2]))
    cats = {p['id']: {txt(c['name']) for c in p['categories']} for p in products}
    names = {p['id']: txt(p['name']) for p in products}
    real = [o for o in orders if o['status'] in COUNTED and 'TEST ORDER' not in (o.get('customer_note') or '')
            and not (o['billing'].get('email') or '').lower().endswith('@example.com')]
    when = lambda o: dt.datetime.fromisoformat(o['date_created_gmt']).replace(tzinfo=dt.timezone.utc).astimezone(B)
    by = collections.defaultdict(list)
    for o in real:
        by[key(o)].append(o)
    for k in by:
        by[k].sort(key=when)
    if '--ids' in sys.argv:
        ids = {int(x) for x in sys.argv[sys.argv.index('--ids') + 1].split(',')}
        chosen = [o for o in real if o['id'] in ids or int(o['number']) in ids]
    else:
        chosen = [o for o in real if o['status'] in ('processing', 'on-hold')]

    def lines(o):
        out = []
        for i in o['line_items']:
            pid, taste = i['product_id'], any(m['key'] == '_fika_taste' for m in i['meta_data'])
            c = cats.get(pid, set())
            kind = 'free taste' if taste else 'Ready Mix' if 'Ready Mix' in c else 'pick-and-mix'
            g = 50 if taste else i['quantity'] * (500 if kind == 'Ready Mix' else 100)
            out.append({'name': names.get(pid, txt(i['name'])), 'grams': g, 'kind': kind,
                        'taste': 'sour' if 'Sour' in c else 'sweet' if 'Sweet' in c else ''})
        return out

    res = []
    for o in sorted(chosen, key=when):
        hist = by[key(o)]
        before = [h for h in hist if when(h) < when(o)]
        b, s = o['billing'], o['shipping']
        buyer, receiver = txt(f"{b['first_name']} {b['last_name']}"), txt(f"{s.get('first_name') or ''} {s.get('last_name') or ''}")
        gift = bool(receiver) and receiver.lower() != buyer.lower() and txt(s.get('last_name')).lower() != txt(b['last_name']).lower()
        now_lines = lines(o)
        fav = collections.Counter()
        tastes = collections.Counter()
        seen = set()
        for h in before:
            for l in lines(h):
                if l['kind'] != 'free taste':
                    fav[l['name']] += l['grams']; seen.add(l['name'])
                    if l['taste']: tastes[l['taste']] += l['grams']
        here = collections.Counter()
        for l in now_lines:
            if l['kind'] != 'free taste':
                here[l['name']] += l['grams']
        this_taste = collections.Counter()
        for l in now_lines:
            if l['taste']:
                this_taste[l['taste']] += l['grams']
        last = when(before[-1]) if before else None
        res.append({
            'order': o['number'], 'id': o['id'], 'placed': when(o).strftime('%a %d %b %Y, %H:%M'),
            'for': first_name(s.get('first_name') or b['first_name']), 'gift': gift,
            'buyer_first_name': first_name(b['first_name']) if gift else None,
            'city': txt(s.get('city') or b.get('city')), 'area': 'inside Beirut' if (s.get('state') or b.get('state')) == 'BA' else 'outside Beirut',
            'order_count_with_this': len(before) + 1,
            'customer_since': when(hist[0]).strftime('%d %b %Y'),
            'days_since_last_order': (when(o) - last).days if last else None,
            'this_order': [f"{l['name']} {l['grams']} g ({l['kind']}{', ' + l['taste'] if l['taste'] else ''})" for l in now_lines],
            'this_order_grams': sum(l['grams'] for l in now_lines),
            'this_order_sweet_sour_g': dict(this_taste),
            'past_favourites_g': fav.most_common(6),
            'past_sweet_sour_g': dict(tastes),
            'favourites_again': [n for n in here if n in seen],
            'new_to_them': [n for n in here if n not in seen] if before else [],
            'ready_mix_only_before': bool(before) and all(l['kind'] == 'Ready Mix' for h in before for l in lines(h) if l['kind'] != 'free taste'),
            'past_order_sizes_g': [sum(l['grams'] for l in lines(h)) for h in before],
            'customer_note': txt(o.get('customer_note')),
            'told_us_before': [],          # answers they gave (Instagram replies), once those are logged
        })
    json.dump(res, sys.stdout, ensure_ascii=False, indent=1)


if __name__ == '__main__':
    main()
