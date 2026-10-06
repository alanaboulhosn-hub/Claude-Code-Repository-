# WooCommerce setup (2026-10-06)

Installed and activated through the REST API (WooCommerce 11.1.2).

- Store country: Lebanon (LB); sells to Lebanon only; currency USD; taxes off.
- Payments: Cash on delivery enabled ("Pay in cash when your sweets arrive.").
- Shipping: zone "Lebanon" (country LB), flat rate "Delivery" $5.
- Pages created by WooCommerce: Cart (86), Checkout (87), My account (88),
  Refund and Returns Policy (89, draft). The existing Shop page (40) was not changed;
  no WooCommerce shop page is assigned.

How the home page carousels read products (Store API `/wp-json/wc/store/v1/products`):
- Mix your own: products NOT in a category whose name/slug contains "ready"; price = per 100 g.
- Ready-Mix: products in a category containing "ready"; price = one 500 g bag.
- A tag/category containing "gelatin" shows the Pork Gelatin label
  (unless it says gelatin free / no gelatin / non gelatin).
- First product image is the card photo; order follows Menu order.
- Until WooCommerce has products, the pages show the fallback list written in the page script.

Still to do: the page's bag (localStorage `fika_bag_v1`) is not yet connected to the
WooCommerce cart, so the bag's Checkout button does not carry items across.
