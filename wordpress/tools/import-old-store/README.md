# Importing orders from the old Website Builder store

Used on 2026-10-09 for the first import (455 orders, #1001 to #1455). Re-run at launch with a fresh export to add the
orders placed since then; orders already imported are skipped, or get their new status (e.g. Unfulfilled → Completed).
Customer data (the CSV and the JSON built from it) is never committed.

1. Export: Website Builder > Store > Orders > Export (CSV).
2. Products: `curl -u "$WP_USER:$WP_APP_PASSWORD" "$SITE/wp-json/wc/v3/products?per_page=100&_fields=id,name,price,categories" > products.json`
3. Build: `python3 -I build-orders.py Exported_Orders.csv products.json orders.json`
   (maps old product names to current products, "Ready … (1KG)" = 2 bags; checks every price; Fulfilled = completed,
   Unfulfilled = processing, Canceled = cancelled; dates read as Beirut time).
4. Add `importer-snippet.php` as a Code Snippet (it adds POST /wp-json/fika/v1/tmp-import, admins only).
5. Dry run: POST orders.json to `/wp-json/fika/v1/tmp-import?dry=1`, then without `dry` in batches of 40.
   No emails are sent (all WooCommerce emails are off for that request, and wp_mail is blocked).
6. Check counts and totals against the file, then delete the snippet.

Imported orders keep their old number through snippet fika-imported-orders.php.
