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

Still to do: the page's bag (localStorage `fika_bag_v1`) is not yet connected to the
WooCommerce cart, so the bag's Checkout button does not carry items across.
