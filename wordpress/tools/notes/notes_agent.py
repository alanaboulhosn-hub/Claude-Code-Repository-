#!/usr/bin/env python3
"""The owner's-notes agent's two steps (credentials: WP_USER / WP_APP_PASSWORD in the environment).

  python3 notes_agent.py pending OUT_DIR
      downloads the orders and products into OUT_DIR (a scratch folder: customer data, never git) and prints the
      facts (order_facts.py) of every order not yet packed (processing / on hold) that has no owner's note:
      once a bag is packed, a note can no longer go in it
  python3 notes_agent.py save ORDER_ID MESSAGE QUESTION
      saves the note on that order through POST /wp-json/fika/v1/owner-note/{id}; refuses when the order already
      has a note (one written or edited by the owner or the team is never overwritten)

Saving writes only the note (order meta _fika_owner_note): no status change, no email, nothing sent to RT."""
import os, sys, json, base64, subprocess, urllib.request

SITE = 'https://swedishfikalb.com/wp-json/'
HERE = os.path.dirname(os.path.abspath(__file__))


def call(path, data=None):
    auth = base64.b64encode(f"{os.environ['WP_USER']}:{os.environ['WP_APP_PASSWORD']}".encode()).decode()
    req = urllib.request.Request(SITE + path, data=json.dumps(data).encode() if data is not None else None,
                                 headers={'Authorization': 'Basic ' + auth, 'Content-Type': 'application/json'},
                                 method='POST' if data is not None else 'GET')
    return json.load(urllib.request.urlopen(req, timeout=120))


def all_pages(path):
    out = []
    for page in range(1, 50):
        d = call(f'{path}{"&" if "?" in path else "?"}per_page=100&page={page}')
        out += d
        if len(d) < 100:
            return out
    return out


def pending(out_dir):
    os.makedirs(out_dir, exist_ok=True)
    orders = all_pages('wc/v3/orders?status=any')
    products = all_pages('wc/v3/products?status=any')
    json.dump(orders, open(os.path.join(out_dir, 'orders.json'), 'w'))
    json.dump(products, open(os.path.join(out_dir, 'products.json'), 'w'))
    has_note = lambda o: any(m['key'] == '_fika_owner_note' and m['value'] for m in o['meta_data'])
    packed = lambda o: any(m['key'] == '_fika_packed' and m['value'] == 'yes' for m in o['meta_data'])
    todo = [o['id'] for o in orders if o['status'] in ('processing', 'on-hold') and not packed(o) and not has_note(o)]
    if not todo:
        print('[]')
        return
    facts = subprocess.run([sys.executable, os.path.join(HERE, 'order_facts.py'), os.path.join(out_dir, 'orders.json'),
                            os.path.join(out_dir, 'products.json'), '--ids', ','.join(map(str, todo))],
                           capture_output=True, text=True, check=True).stdout
    # order_facts matches by id or number; keep only the orders chosen by id
    print(json.dumps([f for f in json.loads(facts) if f['id'] in todo], ensure_ascii=False, indent=1))


def save(order_id, message, question):
    current = call(f'fika/v1/owner-note/{int(order_id)}')
    if current.get('message'):
        print(f'order {order_id}: already has a note (by {current.get("by")}), left as it is')
        return
    r = call(f'fika/v1/owner-note/{int(order_id)}', {'message': message, 'question': question, 'by': 'the notes agent'})
    print(f'order {order_id}: saved' if r.get('message') else f'order {order_id}: NOT saved {r}')


if __name__ == '__main__':
    if len(sys.argv) >= 3 and sys.argv[1] == 'pending':
        pending(sys.argv[2])
    elif len(sys.argv) == 5 and sys.argv[1] == 'save':
        save(sys.argv[2], sys.argv[3], sys.argv[4])
    else:
        sys.exit(__doc__)
