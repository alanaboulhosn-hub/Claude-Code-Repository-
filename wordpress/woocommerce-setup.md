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

## Cartoon candies in the checkout bag (2026-10-06)

The bag now drops cartoon illustrations instead of photo cut-outs: one hand-drawn SVG per candy
(wordpress/snippets/fika-cartoons.js, keyed by product slug, embedded in the bag snippet).
Fallbacks for a product without a cartoon: its single-piece cut-out (meta fika_piece), then its photo.
To add a cartoon for a new candy, add an entry for its slug in fika-cartoons.js and update snippet 7.

## Checkout sections (2026-10-06)

- Contact information: Email address + required "Phone number" (additional checkout field
  `fika/phone`, location contact, registered in snippet 5). The phone is copied to the order's
  billing and shipping phone. The address phone field is hidden.
- "Shipping address" step renamed "Delivery address" (block attribute title on the checkout page).
- The "Shipping options" step is removed from the page and hidden with CSS (WooCommerce re-inserts
  it on the frontend); the single delivery rate for the chosen Delivery area is picked automatically
  and still shows in the order summary.

## Home page bag drawer + hover candies (2026-10-06)

- Snippet "Fika cartoons: shared candy cartoons + home bag tweaks" (wordpress/snippets/fika-home-cartoons.php):
  prints the cartoon library on the home page and checkout (the checkout bag snippet now uses it),
  moves the bag drawer / veil / Bag button to <body> so the drawer sits flush with the top of the screen,
  makes the veil invisible (click beside the drawer still closes it), and fills the header bag icon's
  hover burst with cartoon candies (a new random handful on each hover).
- Bag drawer: no delivery line; Total = candy subtotal; note says delivery is added at checkout by area.

## One bag icon (2026-10-06)

The floating "Bag 0 kg" button is hidden; the header bag icon (top right) opens the bag drawer.
It shows a count badge (number of different candies / mixes in the bag) and grows with the grams:
scale = 1 + 0.45 * (1 - e^(-grams / 1500))  (100 g ≈ 1.03, 900 g ≈ 1.2, 1.9 kg ≈ 1.32, 4 kg ≈ 1.42; max 1.45).
Implemented in snippet 8 (wordpress/snippets/fika-home-cartoons.php).

## Bag badge in kg + candies flying into the bag (2026-10-06)

- Header bag badge shows the bag weight (e.g. 0.9 kg) instead of an item count.
- Pressing + on a product sends cartoon candies on an arc from its photo into the header bag
  (3 of that candy; a Ready Mix sends 4 cartoons from its Sweet / Sour category); the bag then
  bounces and the badge pops. Off for reduced-motion users. Snippet 8.
- Bag drawer: the delivery / timing note under the total was removed.

## Bag quantities + checkout trims (2026-10-06)

- Home page bag drawer: each line has − / + (candies in 100 g steps, Ready Mix one 500 g bag at a time;
  − at the last step removes the line), the unit price under the name, and stays in sync with the
  product cards and the kg badge.
- Checkout: terms sentence removed (terms block removed from the page + hidden with CSS) and the
  "Use same address for billing" checkbox hidden (it stays ticked, so billing = delivery address).

## Footer icon animations + sticky checkout column (2026-10-06)

- Footer social icons (snippet 8): Instagram double camera-flash; WhatsApp bubble turns into a bird that
  flaps and bobs; Email flap opens, envelope folds into a paper plane that glides with a dashed trail.
- Checkout (snippet 7): on desktop the form column is sticky, so as you scroll it follows down and its
  bottom (Place Order) lines up with the bottom of the right column. Its sticky top is computed from its
  height so Place Order stays 24 px above the bottom of the screen.

## Phone polish + before-you-go popup (2026-10-07)

Phone polish:
- Ready-Mix card − / + pill: text no longer squeezed (snippet 8).
- Phone checkout: the duplicate collapsed "Order summary" at the top is hidden; the full summary stays above
  Place Order (snippet 7).
- No-hover devices: footer icon animations play in turn when the footer scrolls into view; the header bag
  icon's candy burst plays once after the page opens (snippet 8).

Before-you-go popup (snippet 9, wordpress/snippets/fika-exit-offer.php):
- Opens on the checkout when the shopper clicks "Return to store" or the Fika logo, moves the mouse out of
  the top of the window (desktop), or presses Back (phones). At most once per visit; never with an empty cart.
- Step 1 asks why: Delivery is too expensive / Delivery takes too long / The candies are too expensive /
  I want to change my order / Another reason. "Change my order" goes back to /#shop.
- Any other reason shows a one-time offer: coupon FIKA10 (id 211): 10% off the products (not delivery),
  individual use, usage limit 1 per customer (WooCommerce checks by email). "Apply" applies it to the cart
  in place. The offer is shown once per browser (localStorage fika_exit_offer_v1).
- Reason counts: GET /wp-json/fika/v1/exit-reasons (shop managers); DELETE the same URL resets them.

## Customer accounts (2026-10-07)

Groundwork for loyalty offers such as "the 8th kg is free".

WooCommerce settings (Settings > Accounts & Privacy): sign-up on the My account page and at checkout,
customers choose their own password, guest checkout still allowed, log-in reminder at checkout,
sign-up privacy line "Your details are only used to run your Fika account and deliver your orders."

Snippet 10 "Fika accounts" (wordpress/snippets/fika-accounts.php):
- Sign-up form: first name + phone (both required; phone needs at least 7 digits), email, password.
  The phone is saved as billing/shipping phone and as the checkout contact field fika/phone, so checkout
  and Account details are pre-filled. Earlier guest orders with the same email are linked to the new account.
- Totals per customer, user meta fika_totals {orders, grams, updated}: Completed orders only, 100 g per
  candy unit, 500 g per Ready Mix bag, refunds taken off. Recomputed whenever an order changes status or is
  refunded. Helpers for the reward work: fika_customer_totals( $user_id ), fika_order_grams( $order ).
  **Mark orders Completed once delivered**, otherwise they don't count.
- My account dashboard: "N kg of sweets delivered" and "N orders delivered" cards.
- WordPress Users list: "Fika orders" and "Kg delivered" columns.
- My account menu: Dashboard, Orders, Delivery address, Account details, Log out (Downloads removed, billing
  address hidden since billing always uses the delivery address). Last name optional.
- Home page header: person icon left of the bag (green dot when signed in). On hover it waves and drops down
  a menu: signed out "Log in" / "Sign up" (to /my-account/#login or #register, which scrolls to and focuses
  that form); signed in "Hi <name>!", kg delivered, Orders, Delivery address, Account details, Log out.
  On phones the first tap opens the menu. window.FIKA_USER = {in, name, url, kg, menu} is printed on every page.
- Account notice boxes (e.g. "No order has been made yet"): icon, text and button centred on one line, pill buttons.

My account page (88): Fika skin block wordpress/pages/store-skin-account.html above [woocommerce_my_account]
(centred logo, "Your account", Return to store, white cards, blue pill buttons, phone layout).

## Fika rewards: swim to your free kilo (2026-10-07, updated)

Snippet 11 "Fika rewards" (wordpress/snippets/fika-loyalty.php), needs snippet 10. Signed-in customers only.
- Home page (just above the shop) and My account dashboard: a cartoon Swedish fish swims along a water lane
  towards 8 kg. The lane shows delivered kilos (Completed, deep blue), kilos on their way (Processing /
  On hold, light blue) and what is in the bag right now (candy stripes). As the customer presses + / − in
  the shop the fish swims forwards or turns round and swims back, gram by gram.
- **Undelivered** is a new order status (order screen dropdown and bulk action "Change status to undelivered").
  Undelivered, Cancelled, Failed and Refunded orders do not count. The fish starts from where the customer last
  saw it, so after an order is marked Undelivered it visibly swims back.
- Every 8 kg *delivered* creates a one-time code FREEKG-XXXXXX (Marketing > Coupons, description
  "Fika free kilo #n for ..."), locked to the customer's email. At checkout it is worth exactly 1 kg of the
  sweets in the bag (most expensive first; a smaller bag is simply free); delivery is not included.
  Placed-but-not-delivered orders move the fish but never unlock the code ("unlocks once delivered").
  If delivered kilos fall back below the mark (order marked Undelivered), an unused code is withdrawn.
- The free kilo does not count towards the next 8 kg; anything beyond each 8 kg carries over to the next lap.
- The free kilo does not combine with FIKA10 (both are individual use), and the before-you-go popup skips its
  10% offer for customers holding a free kilo (applied or unused); it still asks the reason.
- Checkout shows "Your free kilo is ready" with a "Use my free kilo" button.
- Header account menu: "x kg to your free kilo" / "Free kilo unlocks on delivery" / "Your free kilo is ready!".
- Shop managers (signed in) can preview on the home page: /?fika_fish=5.2&fika_pending=1.
- Snippet 10 also sets the display name to the first name for accounts created at checkout.
