# WooCommerce setup (2026-10-06)

Installed and activated through the REST API (WooCommerce 11.1.2).

- Store country: Lebanon (LB); sells to Lebanon only; currency USD; taxes off.
- Payments: Cash on delivery enabled ("Pay in cash when your sweets arrive.").
- Shipping: zone "Lebanon" (country LB), flat rate "Delivery" $5.
- Pages created by WooCommerce: Cart (86), Checkout (87), My account (88),
  Refund and Returns Policy (89, draft). The existing Shop page (40) was not changed;
  no WooCommerce shop page is assigned.

## Catalogue (created 2026-10-06)

Categories: Pick & Mix (17, slug pick-and-mix), Ready Mix (18, slug ready-mix).
Tags: Pork Gelatin (19, slug pork-gelatin), Gelatin Free (20, slug gelatin-free).

| ID | Product | Price | Category | Tag | Menu order |
|----|---------|-------|----------|-----|------------|
| 90 | Fizzy Cola | $2.50 / 100 g | Pick & Mix | Pork Gelatin | 1 |
| 91 | Loose Teeth | $2.50 / 100 g | Pick & Mix | Pork Gelatin | 2 |
| 92 | Fizzy Pop | $2.50 / 100 g | Pick & Mix | Pork Gelatin | 3 |
| 93 | Raspberry Bites | $2.50 / 100 g | Pick & Mix | Pork Gelatin | 4 |
| 94 | Ready Sweet & Sour Mix | $12.50 / 500 g bag | Ready Mix | Gelatin Free | 1 |
| 95 | Ready Sour Mix | $12.50 / 500 g bag | Ready Mix | Gelatin Free | 2 |
| 96 | Ready Sweet Mix | $12.50 / 500 g bag | Ready Mix | Gelatin Free | 3 |

The four "Sample candy" placeholders from the page's fallback list were not added.
None of the products has a photo yet; cards show the drawn jelly-bean placeholder.

## Full catalogue published (2026-10-06)

34 products live: 31 Pick & Mix candies ($2.50 / 100 g; sub-categories Sweet (21) and Sour (22))
and 3 Ready Mixes ($12.50 / 500 g bag): Sweet Mix (96), Sweet & Sour Mix (94), Sour Mix (95).
Pork Gelatin: Fizzy Cola (90), Loose Teeth (91), Fizzy Pop (92), Raspberry Bites (93).
Every other product is tagged Gelatin Free. Photo mapping: wordpress/catalogue-images.json.

## Adding a new candy (WordPress dashboard)

1. Products -> Add New Product. Type the name.
2. Product data -> General -> Regular price: price per 100 g (Pick & Mix) or per 500 g bag (Ready Mix).
3. Product categories (right side): tick Pick & Mix or Ready Mix.
4. Product tags: Pork Gelatin or Gelatin Free.
5. Product image (right side): set the photo (square, plain background works best).
6. Product data -> Advanced -> Menu order: position in the carousel (1 = first).
7. Publish. It appears on the home page on the next page load.
To hide a candy without deleting it: set it to Draft, or Inventory -> Stock status -> Out of stock
(out-of-stock products still show; use Draft to hide).

Page fix on 2026-10-06: the gelatin check now treats "gelatin free" / "gelatin-free" / "gelatinless"
as no gelatin (before, only "free gelatin" / "no gelatin" word orders were recognised).

How the home page carousels read products (Store API `/wp-json/wc/store/v1/products`):
- Mix your own: products NOT in a category whose name/slug contains "ready"; price = per 100 g.
- Ready-Mix: products in a category containing "ready"; price = one 500 g bag.
- A tag/category containing "gelatin" shows the Pork Gelatin label
  (unless it says gelatin free / no gelatin / non gelatin).
- First product image is the card photo; order follows Menu order.
- Until WooCommerce has products, the pages show the fallback list written in the page script.

## Checkout connection (2026-10-06)

The bag's Checkout button now copies the bag into the WooCommerce cart through the Store API
(`/wp-json/wc/store/v1/batch`: empty the cart, then add each item), then opens `/checkout/`.
Quantities: Pick & Mix 1 = 100 g; Ready Mix 1 = one 500 g bag. Delivery $5 and Cash on delivery
come from WooCommerce. After an order, the home page sees the empty WooCommerce cart and empties the bag.
The Checkout link has `rel="nofollow"` and class `no-prefetch` so WordPress's link prefetching cannot
cache an empty-cart redirect, and the jump adds `?fika=<time>` to always load a fresh page.

Fixed on the same day:
- Store currency had been switched to LBP with 0 decimals (prices showed as 3 / 13). Set back to USD,
  2 decimals, "." decimal and "," thousands separators, symbol on the left. Re-running the WooCommerce
  setup wizard with Lebanon may switch it to LBP again.
- WooCommerce "Coming soon" mode was on for store pages (cart/checkout showed "launching soon" to
  visitors). Turned off (`woocommerce_coming_soon` = no).

Tested: bag -> checkout shows the right items, $5 delivery, Cash on delivery and the right total.
A real test order was not placed.

## Checkout polish (2026-10-06)

- Code Snippets plugin installed; snippet "Fika checkout: phone required, no postcode, weight labels"
  (source: wordpress/snippets/fika-checkout.php). Phone is required, the postal code is hidden for
  Lebanon, and each line shows "Weight: 300 g" (Pick & Mix) or "Amount: 1 bag (500 g)" (Ready Mix)
  in the cart, checkout, order emails and WooCommerce > Orders.
- Cart (86) and Checkout (87) pages start with a Custom HTML block (wordpress/pages/store-skin-cart.html,
  store-skin-checkout.html): Fika header, page title, pink background, Fika fonts, white cards and blue
  rounded buttons; and end with a help line (store-skin-help.html).

## Delivery areas and checkout bag (2026-10-06)

- Snippet "Fika delivery areas: Lebanon governorates" (wordpress/snippets/fika-delivery-areas.php):
  required "Delivery area" dropdown (Beirut, Mount Lebanon, North Lebanon, Akkar, Bekaa,
  Baalbek-Hermel, South Lebanon, Nabatieh; codes BA, JL, AS, AK, BI, BH, JA, NA).
- Shipping zones: "Beirut" (LB:BA, order 1) flat rate "Delivery in Beirut" $5;
  "Lebanon (outside Beirut)" (LB, order 2) flat rate "Delivery outside Beirut" $6.
- Home page bag drawer: delivery shows "$5 – $6", total reads "Total from", note updated.
- Checkout: the line under the "Checkout" title was removed.
- Snippet "Fika checkout: animated bag illustration" (wordpress/snippets/fika-checkout-bag.php):
  a Fika paper bag with a clear window at the top of the checkout's right column. Mini product
  photos drop in, with pieces in proportion to grams (up to 4 per 100 g, max ~46 pieces);
  Ready Mix bags show 5 candies from their category (Sweet / Sour / both). Updates with the cart.

## Checkout round 2 (2026-10-06)

- Delivery area dropdown now has two options: Inside Beirut (BA, $5) and Outside Beirut (OB, $6).
- Checkout header: Home / Shop links removed, Fika logo centred, "Return to store" link under the
  Checkout heading goes to /#shop (the home page "Mix your own" section now has id="shop").
- Checkout bag: only "Fika" on the bag. Candies are single-piece cut-outs made from each product
  photo (wordpress/assets/pieces/, uploaded to the media library as "Fika piece - <name>").
  Each product's meta "fika_piece" holds its piece URL; the bag snippet reads them and falls back
  to the product photo for products without one (e.g. a newly added candy).
  Pieces settle in staggered rows like a jar; counts stay in proportion to grams.
