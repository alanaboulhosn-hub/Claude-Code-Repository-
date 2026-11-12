"""Download every WooCommerce order placed since a date (all statuses) to a JSON file.
Usage: python3 fetch_orders.py 2026-09-28 out.json
Needs WP_USER and WP_APP_PASSWORD in the environment. The file holds customer data: keep it out of git."""
import os, sys, json, datetime, requests
since, out = sys.argv[1], sys.argv[2]
start = datetime.datetime.fromisoformat(since) - datetime.timedelta(hours=3)  # Beirut midnight in UTC (safe for EET/EEST)
auth = (os.environ["WP_USER"], os.environ["WP_APP_PASSWORD"])
orders, page = [], 1
while True:
    r = requests.get("https://swedishfikalb.com/wp-json/wc/v3/orders", auth=auth, timeout=60,
                     params={"per_page": 100, "page": page, "after": start.strftime("%Y-%m-%dT%H:%M:%SZ"),
                             "dates_are_gmt": "true", "status": "any"})
    r.raise_for_status()
    batch = r.json()
    if not batch:
        break
    orders += batch
    page += 1
json.dump(orders, open(out, "w"))
print(len(orders), "orders", min(o["date_created"] for o in orders) if orders else "", "->", max(o["date_created"] for o in orders) if orders else "")
