"""Loyalty audit: recount every account from its orders with the shop's own rules (fika-accounts, fika-loyalty,
fika-taste) and compare with what is stored. Read-only.
Inputs (customer data: keep them out of git): loyalty/orders.json from .claude/skills/fika-batch-order/scripts/
fetch_orders.py 2026-08-01, and loyalty/data.json = {cust: /wc/v3/customers?role=all, prods: [{id, cats}],
coup: /wc/v3/coupons?search=swim}. Run from the folder that holds loyalty/. Print output with emails masked.
Expected, not rule breaks: tastes "won" above the customer's kilograms (spun with a big bag at checkout, the free
line only appears once the bag reaches the stop); a used reward whose checkpoint now shows a little below it (the
part paid by the reward stops counting after the order, by design)."""
import json, re, collections, sys
D = json.load(open('loyalty/data.json'))
orders = json.load(open('loyalty/orders.json'))
RM = {p['id'] for p in D['prods'] if 'ready-mix' in p['cats']}
LAP = 15000
CPS = {3000: 2.8, 6000: 5.6, 10000: 11.2, 15000: 28}
STOPS = (1500, 12500)
coupons = {c['code'].lower(): c for c in D['coup']}
meta = lambda x, k: next((m['value'] for m in x['meta_data'] if m['key'] == k), None)
swim = lambda c: re.match(r'^swim(3|6|10|15)-', c or '', re.I)

def order_grams(o):          # fika_order_grams
    return sum(max(0, i['quantity']) * (500 if i['product_id'] in RM else 100) for i in o['line_items'])

def swim_grams(o):           # fika_swim_order_grams
    g = order_grams(o)
    g -= sum(100 * i['quantity'] for i in o['line_items'] if any(m['key'] == '_fika_taste' for m in i['meta_data']))
    if g <= 0:
        return 0
    paid = sum(float(c['discount']) for c in o['coupon_lines'] if swim(c['code']))
    sub = sum(float(i['subtotal']) for i in o['line_items'])
    if paid > 0 and sub > 0:
        g -= round(paid / (sub / g))
    return max(0, g)

by_cust = collections.defaultdict(list)
for o in orders:
    by_cust[o['customer_id']].append(o)
email_orders = collections.defaultdict(list)
for o in orders:
    email_orders[o['billing']['email'].lower()].append(o)

issues = collections.defaultdict(list)
summary = []
refunded = [o['number'] for o in orders if o.get('refunds')]
for c in D['cust']:
    uid, em = c['id'], c['email'].lower()
    if c['role'] != 'customer':
        continue
    os_ = by_cust.get(uid, [])
    comp = [o for o in os_ if o['status'] == 'completed']
    pend = [o for o in os_ if o['status'] in ('processing', 'on-hold')]
    totals = meta(c, 'fika_totals') or {}
    exp = {'orders': len(comp), 'grams': sum(order_grams(o) for o in comp)}
    if (totals.get('orders'), totals.get('grams')) != (exp['orders'], exp['grams']):
        issues['stored total differs from orders'].append((uid, em, 'stored', totals.get('orders'), totals.get('grams'), 'actual', exp['orders'], exp['grams']))
    delivered = sum(swim_grams(o) for o in comp)
    placed = sum(swim_grams(o) for o in pend)
    confirmed = (meta(c, 'fika_email_ok') or '').lower() == em
    # guest orders that should have joined this confirmed account
    if confirmed:
        loose = [o['number'] for o in email_orders.get(em, []) if not o['customer_id'] and o['status'] in ('completed', 'processing', 'on-hold')]
        if loose:
            issues['confirmed account, guest orders with its email not joined'].append((uid, em, loose))
    # claimed rewards
    claims = meta(c, 'fika_swim_claims') or []
    used_codes = {cl['code'].lower(): o for o in orders for cl in o['coupon_lines'] if swim(cl['code'])}
    for cl in claims:
        need = (int(cl['lap']) - 1) * LAP + int(cl['g'])
        code = cl['code'].lower()
        cp = coupons.get(code)
        used = bool(cp and cp['usage_count'] >= 1) or code in used_codes
        if need > delivered and not used:
            issues['unused reward above what the customer has delivered'].append((uid, em, cl['code'], need, delivered))
        if need > delivered + placed and used:
            issues['reward used although its checkpoint was never reached'].append((uid, em, cl['code'], need, delivered, placed))
        if not cp and not used:
            issues['claimed reward has no code in the shop'].append((uid, em, cl['code']))
        if cp:
            if [e.lower() for e in cp['email_restrictions']] != [em]:
                issues['reward code not locked to the customer email'].append((uid, em, cl['code'], cp['email_restrictions']))
            if cp['usage_limit'] != 1:
                issues['reward code usable more than once'].append((uid, em, cl['code'], cp['usage_limit']))
            if cp['usage_count'] > 1:
                issues['reward code used more than once'].append((uid, em, cl['code'], cp['usage_count']))
        if code in used_codes and used_codes[code]['customer_id'] not in (uid, 0):
            issues['reward code used by another account'].append((uid, em, cl['code'], used_codes[code]['number']))
    # unclaimed rewards the customer has reached (allowed: claiming is the customer's choice; listed for the owner)
    have = {(int(cl['lap']), int(cl['g'])) for cl in claims}
    reached = [(lap, g) for lap in range(1, delivered // LAP + 2) for g in CPS if (lap - 1) * LAP + g <= delivered]
    unclaimed = [(lap, g) for lap, g in reached if (lap, g) not in have]
    # mystery tastes
    tastes = meta(c, 'fika_tastes') or {}
    tl = tastes.values() if isinstance(tastes, dict) else tastes
    for t in tl:
        need = (int(t['lap']) - 1) * LAP + int(t['g'])
        if need > delivered + placed:
            issues['mystery taste won above what the customer has'].append((uid, em, t, delivered, placed))
    stops_reached = [(lap, g) for lap in range(1, delivered // LAP + 2) for g in STOPS if (lap - 1) * LAP + g <= delivered]
    taste_keys = {(int(t['lap']), int(t['g'])) for t in tl}
    summary.append({'uid': uid, 'email': em, 'confirmed': confirmed, 'delivered_g': delivered, 'on_way_g': placed,
                    'claims': [cl['code'] for cl in claims], 'unclaimed': unclaimed, 'tastes': len(taste_keys),
                    'stops_not_spun': [s for s in stops_reached if s not in taste_keys]})
# codes used in orders but not belonging to any claim
claimed_codes = {cl['code'].lower() for c in D['cust'] for cl in (meta(c, 'fika_swim_claims') or [])}
for o in orders:
    for cl in o['coupon_lines']:
        if swim(cl['code']) and cl['code'].lower() not in claimed_codes and o['status'] in ('completed', 'processing', 'on-hold'):
            issues['reward code on an order but not in any account'].append((o['number'], cl['code'], o['status']))
# counted statuses: undelivered / failed / cancelled / refunded never count (checked by construction above)
json.dump(summary, open('loyalty/summary.json', 'w'), indent=1)
print('accounts checked:', len(summary), '| confirmed:', sum(s['confirmed'] for s in summary),
      '| with delivered kg:', sum(1 for s in summary if s['delivered_g']), '| orders with refunds:', refunded)
print('reached 1.5 kg:', sum(1 for s in summary if s['delivered_g'] >= 1500), '3 kg:', sum(1 for s in summary if s['delivered_g'] >= 3000),
      '6 kg:', sum(1 for s in summary if s['delivered_g'] >= 6000), '10 kg:', sum(1 for s in summary if s['delivered_g'] >= 10000))
print('rewards claimed:', sum(len(s['claims']) for s in summary), '| reached but not claimed yet:', sum(len(s['unclaimed']) for s in summary),
      '| spin stops reached but not spun yet:', sum(len(s['stops_not_spun']) for s in summary), '| tastes won:', sum(s['tastes'] for s in summary))
if not issues:
    print('NO RULE BROKEN')
for k, v in issues.items():
    print('\n##', k, len(v))
    for x in v[:15]:
        print('  ', x)
