# Data sources: access and quirks

## Getting the data

- **WooCommerce** (orders, products): REST `https://swedishfikalb.com/wp-json/wc/v3/` with basic auth
  `WP_USER` / `WP_APP_PASSWORD` (environment). Use `scripts/fetch_orders.py`. Read-only: this skill never changes the
  shop. Products: `GET products?per_page=100&status=any` gives categories (`sweet` / `sour` / `ready-mix`),
  `stock_status` and `catalog_visibility` (hidden = off the shop).
- **Google Drive** (connector tools `mcp__Google_Drive__*`, load with ToolSearch): `search_files` with
  `title contains 'FIKA'` and the spreadsheet mime type. The FIKA sheet is owned by the owner and read-only for us.
  `read_file_content` gives a text summary of every tab (only sample rows of big tabs). For full data use
  `download_file_content` with exportMimeType xlsx: the result is base64 and too large for the chat, so it is saved
  to a tool-results file; decode it with Python (`json.load` -> `base64.b64decode(content)`) into the scratchpad.
  FIKA Orders is small enough for `read_file_content`.
- Office tools: `soffice --headless --convert-to xlsx` recalculates formulas in a generated workbook (do it before
  reading values back); Playwright Chromium at `/opt/pw-browsers/chromium` for a screenshot check of the HTML.

## Order lines (WooCommerce)

- Loose candy: meta "Weight" ("100 g", "200 g"; it is the line's total weight, not per unit).
- Ready Mix: meta "Amount" ("1 bag (500 g)", "2 bags (1 kg)"): 500 g x quantity.
- Free mystery taste: meta "Mystery taste", price 0: 50 g.
- Old-site orders (before 9 Oct 2026, "fika-import"): no labels. Unit $2.50 = 100 g loose; unit $12.50 = 500 g mix.
- Statuses: completed / processing / on-hold count. Cancelled, failed (returned by RT), refunded do not.
- Fan Favourites (product 1200) launched 26 Oct 2026: 45 bags in its launch week, then about 2.3 bags a day. Sweet &
  Sour Mix (94) and Skull Mix (1201) are hidden: replaced / seasonal.

## FIKA sheet

- **Inventory tab**: A candy (supplier name), B Ordered Quantity (kg received over all batches, website candies
  only), C In Boxes, D On table, E Est. Quant. (= C + D, the count), F KG/Day = (B - E) / days in stock.
  (B - E) / F gives the days each candy was in stock, which dates a sell-out. F is a lifetime average including the
  August launch, so do not use it as today's demand.
- **Inventory System**: settings in rows 5-49 (B8 = last full count date); the candy table from row 53: L ERT code,
  M pack kg, N SEK per pack, P batch opened, Q last counted, R out of stock since, AB Inventory Est. Quant.
  Its own forecast ("ORDER NOW", projected stock) overstated demand about 2.5x in Nov 2026 (KG/Day issue): ignore it.
- **ERT ORDER** tab: latest quotes (code, unit kg, SEK). Prices can be wrong (Sour Pineapple); cross-check FIKA Orders.
- **Stock Log**: 22 Oct baseline, 3 Nov edits (two passes; the first pass of each candy is stale), 6 Nov full count.
- **PR** and **Atypical Orders**: kilograms and money for candy outside the website. Some atypical names also have
  website orders (in Nov 2026: #1437, #1441 and #1484, delivered by hand): do not count them twice.

## FIKA Orders (supplier batches)

- Order 1 (pilot, Aug 2026): 336.6 kg, prices in SEK, includes candies never sold on the website (Skumbanan,
  Vattenmeloner, Limousiner, Regnbågsrolls, Röda läppar, Spaghetti, Minidynamit Vattenmelon).
- Order 2 (arrived 28 Sep 2026): listed 554.8 kg, prices in EUR; the Strawberry/Vanilla Diamond line (20.46 kg) was
  not bought, so about 534 kg arrived.
- Sheet2: landed cost per batch (batch 2 $15.93/kg all-in; batch 3 plan $14.39/kg without VAT).

## Product ids (WooCommerce) of note

108 Wild Berry Pomegranate Oval, 119 Tutti Frutti Diamond, 114 Sour Pineapple, 121 Forest Berry Ovals (shop name
"(Mini)"), 92 Fizzy Pop (Bubbliz), 1200 Fan Favourites Mix, 95 Sour Mix, 96 Sweet Mix, 94 Sweet & Sour Mix,
1201 Skull Mix, 9001 = placeholder used for Sour Strawberry Vanilla Diamonds (not a shop product). The full map to
FIKA sheet names is in `scripts/config.batch3.json` and `wordpress/snippets/fika-daily-sales.php`.
