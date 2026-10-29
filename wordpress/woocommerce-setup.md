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
- WordPress Users list: "Phone", "Signed up" (sortable), "Fika orders" and "Kg delivered" columns.
- Log out (header menu or My account) goes straight to the home page, signed out. (The header link used to carry
  HTML-escaped &amp; in its URL, which failed WordPress's security check and showed "Do you really want to log out?".)
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

## Email sending (2026-10-07)

Why emails would land in spam: they were sent as cesar.aboulhosn@hotmail.com from Hostinger's web server
(a Hotmail address sent by a non-Microsoft server fails SPF/DMARC), with the temporary site address as name.

Done:
- WooCommerce emails: from "Fika" <hello@swedishfikalb.com>; footer "Fika · Swedish pick-and-mix in Lebanon /
  Questions? Reply to this email or WhatsApp us on 79 411 565."
- Site title: "Fika" (was the temporary hostingersite.com address; used in email subjects and browser tabs).
- Plugin WP Mail SMTP 4.10 installed and preset (wordpress/snippets/one-time-smtp-preset.php): sender forced to
  Fika <hello@swedishfikalb.com> for every email, return path on, SMTP smtp.hostinger.com : 465 SSL, login
  hello@swedishfikalb.com. Mailer is "Default (PHP)" until the mailbox password is entered.

Optional while testing (owner): WP Admin > WP Mail SMTP > Settings > Mailer "Other SMTP" > enter the
hello@swedishfikalb.com mailbox password > Save > Tools > Email Test. This only uses the mailbox to send; it
changes nothing on the live site or the mailbox.
Do NOT change swedishfikalb.com DNS (SPF / DKIM / DMARC) while the Website Builder store is live: that is part
of the launch step, planned separately (see CLAUDE.md).

## Sign-up nudges (2026-10-07)

Snippet 16 "Fika sign-up nudges" (wordpress/snippets/fika-signup-nudge.php). Visitors who are not signed in only.
- Home page cloud: a white speech bubble pointing at the account icon (the person waves while it shows).
  First time something goes into the bag: "Make every gram count. Sign up and the 300 g in your bag starts your
  swim to a free kilo" (grams update live). Otherwise after 20 s of browsing: "Join the Fika crew".
  Perks (desktop): track your orders, member-only offers, bundles before anyone else. Blue "Sign up, it's free"
  button (/my-account/#register) + "Already have an account? Log in".
  Once per visit (sessionStorage fika_nudge_seen_v1); × hides it for 7 days (localStorage fika_nudge_closed_v1);
  fades by itself after 14 s (10 s on phones; pauses while hovered); hovering the account icon replaces it with the menu.
- Checkout card above the form: "Make this order count: create a free account and this 800 g starts your swim
  to a free kilo." "Create my account" ticks WooCommerce's "Create an account with Fika" box and puts the cursor
  in "Create a password"; the card then reads "Your account comes with this order". "Log in" opens WooCommerce's
  login and returns to checkout.

## Fika customers list (2026-10-07)

Snippet 17 (wordpress/snippets/fika-customers-admin.php): WP Admin > WooCommerce > Fika customers.
One table of everyone: people with an account and people who ordered as guests (grouped by email).
Columns: name, email, phone, type (Account / Guest), signed up, orders (Processing, On hold, Completed; links to
their orders), delivered (Completed), kg delivered, spent (minus refunds), last order.
Tabs: All / Accounts / Accounts that ordered / Accounts with no orders yet / Guests. Search box (name, email or phone;
phone digits match with or without spaces) and click-to-sort columns. "Download CSV" exports what is on screen.
WooCommerce's own Customers menu item is hidden (this list replaces it; one block in the snippet brings it back).
Staff accounts (admins, shop managers) only appear if they ordered.

## Max per order (2026-10-07)

Snippet 20 (wordpress/snippets/fika-order-caps.php). Product edit page > Product data > Inventory > "Max per order
(grams)": e.g. 500 = 500 g of a candy; for Ready Mix 1000 = 2 bags. Empty = no cap (product meta fika_max_g).
- Enforced by WooCommerce: adding to the cart / changing quantities beyond the cap is refused with "You can order up
  to 500 g of Peaches per order", and checkout is blocked if a cart is over it.
- Home page: + (card and bag drawer) stops at the cap and shows a short "Max 500 g per order" tip (no label under the
  price); a bag already holding more than the cap is trimmed to it when the page opens.
Caps set: BUBS Forest Berry Ovals (product 121) max 100 g per order (2026-10-07).

## Shop page + Fan favourites (2026-10-07)

Pages are now built from shared parts (WordPress synced patterns, WP Admin > Appearance/Patterns), so the home page and
the shop page use one copy of the header, cards, bag and footer. Source and build: wordpress/pages/build-shop-pages.py,
parts in wordpress/pages/parts/ (ids.json: head 239, fish 240, mix 241, foot 242). Backups of the pages as they were:
wordpress/pages/backup/.
- Home (page 41): hero + eyes, then "Fan favourites": the products starred as Featured in WooCommerce (Products list,
  star column), in shop order, then a pink "31 sweets to mix / Shop all sweets" card. Ready Mix and the rest unchanged.
- Shop (page 40, /shop/): banner with the candy-bowl photo (media 28) that stays still while scrolling, pink wash,
  "Mix your own & Ready mix" and the description. Sticky filter bar under the header: All / Sweet / Sour / Gelatin-free /
  Ready Mix (with counts), search, sort (Most popular = shop order, A to Z, Z to A). Cards in a grid (4 per row desktop,
  3 tablet, 2 phone) in Sweet and Sour sections, Ready Mix section below. Same bag as the home page.
  /shop/#ready-mix opens on Ready Mix (footer link).
- Favourites (featured, shown first everywhere): BUBS Bubblegum Skull, Tutti Frutti Sour Melon, Sour Cherries, Sour
  Watermelon Pacifier, BUBS Banana Toffee Ovals, Tutti Frutti Passion (menu_order -60 ... -10).
- The footer's styles (blue, fonts, spacing; formerly with the home info sections) are part of the shared footer, so
  the footer looks the same on both pages. The shop filter bar slides away when the footer comes into view.
- Snippet 21 (fika-shop-page.php): WooCommerce's own product catalogue is switched off so /shop/ is our page
  (product pages unchanged). Permalinks were refreshed once.
- Snippets 8, 10, 11, 16, 20 now also run on the shop page (header bag + account icon, flying candies, fish tracker,
  sign-up cloud, caps). "Return to store" (checkout, account, before-you-go popup, empty-cart button) goes to /shop/.

## Mix your own, Ready Mix, About us (2026-10-07)

The shop page was split into two pages with the same layout, and an About page was added. Built by
wordpress/pages/build-shop-pages.py (outputs mix-your-own-40, ready-mix-38, about-us, home-41 .raw.html).
- Mix your own (page 40, /mix-your-own/): the 31 candies only. Banner (bowl photo, still while scrolling), sticky filter
  bar All / Sweet / Sour / Gelatin-free with counts and search (the sort menu was removed later; shop order). Pink "Can't decide?" band links to Ready Mix.
- Ready Mix (page 38, /ready-mix/): the 3 Ready Mix bags only, no filters, 3 per row (1 on phones). Banner with the
  jars photo (media 21). "Rather pick your own?" band links to Mix your own.
- About us (page 257, /about-us/): DRAFT copy to edit in WP Admin > Pages. Banner (media 13), Our story (with the
  mascot drawing, media 16), Freshness guaranteed (3 cards), How it works (3 steps), Start your fika buttons. The
  delivery wording follows the FAQ (1-2 / 2-3 business days), not the old Contact page's "forty-eight hours".
- Header menu (pattern 239): Home, Mix your own, Ready Mix, About us; the current page is underlined. On phones the
  links fold into a "Menu" button. Footer links updated the same way.
- /shop/ now redirects (301) to /mix-your-own/ (snippet 21). Hero, Fan favourites card and "Return to store" links go
  to /mix-your-own/.
- Snippets 8, 10, 11, 16, 20 run on all three pages; the fish tracker (11) shows on Mix your own and Ready Mix.
- Old pages 37 (Contact) and 39 (Reviews) still exist; the footer still links to Reviews.
- Update: Mix your own shows one catalogue (no Sweet / Sour headings), narrowed with the filters. The filter bar now
  sits flush under the header. Home: the carousel title is "Mix your own" with a hand-drawn "All pick & mix" link
  to /mix-your-own/ (no arrow, on the title's baseline); the "Sweets made with care" cards, the "31 sweets to mix"
  card, the line under the home title and the "3 products" count over Ready Mix were removed.
- Update: on every page (home, Mix your own, Ready Mix, About us) the blocks fade in as they scroll into view:
  banner text, titles, product cards, About sections and cards, FAQ questions, the pink bands and the footer columns.
  Blocks side by side fade one after another. Changing a filter, search or sort fades the cards in again; adding to
  the bag does not. Visitors who ask their device for reduced motion see everything without the fade. The script is
  in the shared header part (pattern 239). It replaced the earlier drop-in on the shop pages.

## Lighter pages (2026-10-07)

Each page download roughly halved (home about 2.4 MB to 1.2 MB, Mix your own about 2.8 MB to 1.35 MB):
- Snippet 23 (fika-speed.php): on home, Mix your own, Ready Mix and About us the theme's font files (Open Sans,
  Fira Sans, Montserrat as .ttf, about 1.3 MB) are not loaded; Open Sans comes as a small web font from Google Fonts.
  Checkout, cart, account and product pages are unchanged.
- The home hero photo is a file (media 274, phones get the smaller 275) instead of being pasted into the page
  (the home page itself went from 590 KB to 280 KB).
- The cute font is embedded once (header part) instead of three times.
- The product list is downloaded once per page and shared (window.fikaProducts in the header part) by the candies,
  the Ready Mix bags and the flying cartoons (snippet 8); it used to be fetched three times.
- Still photos without the shake: the home photo (with the googly eyes) and the Mix your own / Ready Mix / About us
  banner photos sit on their own fixed layer (.fika-photo) that the hero clips, so the browser keeps them still by
  itself. Before, the photo's position was recalculated on every scroll step, which looked like vibrating on some
  screens. The eyes no longer need moving on scroll either. CSS and the small sizing script: header part (pattern 239).

## No billing address + Checkout leavers screen (2026-10-07)

- Checkout asks for the delivery address only. The "Use same address for billing" box and the billing form are
  hidden for guests and signed-in customers alike (WooCommerce used to untick the box, and show the billing form,
  for customers whose saved billing address differed). The order's billing address is a copy of the delivery
  address; the email and phone stay as entered. Snippet 5 (fika-checkout.php). Tested with a signed-in test
  customer and a test order (both deleted).
- WP Admin > WooCommerce > Checkout leavers (snippet 9, fika-exit-offer.php): the reasons shoppers gave in the
  "Leaving already?" popup with shares, what they did with the 10% offer, the date counting started, and a
  "Reset counts" button. The test counts were reset on 7 October 2026.

## "Your bag is waiting" reminder (2026-10-07)

Snippet 25 (wordpress/snippets/fika-bag-reminder.php):
- The bag is saved with the email as soon as the shopper types it on the checkout (WooCommerce sends it to the
  server), or when a signed-in customer opens the checkout. Later bag changes update the saved copy. (No note is
  shown under the email field.)
- One hour after the last activity, if no order was placed with that email: one email "Your Fika bag is waiting"
  (WooCommerce layout and sender) with the candies, amounts and prices and a "Finish my order" button. The button
  refills the bag on any device (checkout and the shop's bag) and opens the checkout with the email filled in.
- Never after an order; at most one reminder per email per 7 days; "No more reminders" link in the email.
- Saved bags are kept 30 days; WooCommerce > Checkout leavers lists them with their status (waiting, reminder
  sent, ordered after the reminder, ordered, emptied, skipped, no reminders) and counts reminders and orders won back.
- Timing runs on WooCommerce's scheduler, which runs when the site gets visits. With few visitors a reminder can be
  a little late; a server cron job (Hostinger hPanel) makes it exact - part of the launch plan, not set now.
- Shop managers, for testing: GET /wp-json/fika/v1/saved-bags; POST /wp-json/fika/v1/saved-bags/{token}/send
  (?preview=1 returns the email); DELETE /wp-json/fika/v1/saved-bags/test removes test addresses.
- Tested: shop > Checkout > email typed (saved, due in 60 min), email sent, link opened in a fresh browser (bag and
  email restored), order placed (counted as won back); ordering before the hour (no reminder); "No more reminders".
  Test orders 286 and 287 and the saved test bags were deleted.
- Fix: pressing Checkout in the shop replaces the cart with the bag, which used to drop discount codes, so a
  shopper who took the popup's 10% and went back to the shop lost it. The hand-off now puts the codes back (FIKA10,
  a free kilo); a code that no longer applies is skipped. Shared shop part (pattern 241), built by
  build-shop-pages.py. Tested: 10% applied, back to the shop, bag changed, Checkout: FIKA10 still on the order.
- WooCommerce email colour (WooCommerce > Settings > Emails > Base colour) set to Fika blue #004aad
  (was WooCommerce's default purple); applies to order emails and the bag reminder.

## Lördagsmys, hidden products, reviews band, bag suggestions (2026-10-07)

- About us: new section "Lördagsmys, now in Lebanon" (after Our story): the Swedish Saturday-cosiness ritual, the
  bowl everyone picks into, and how it fits Lebanese family weekends; three cards (Everyone picks / One big bowl /
  Slow down together) and a "Build your Saturday bowl" button. Section backgrounds alternate again. DRAFT copy.
- Out of stock and hidden (WooCommerce: stock status "Out of stock", catalogue visibility "Hidden"):
  BUBS Wild Berry Pomegranate Oval (108), BUBS Tutti Frutti Diamond (119), Sour Pineapple (114). The shop now asks
  WooCommerce for shop-visible products only (catalog_visibility=catalog), so hidden products disappear from the
  cards, filters, counts and favourites; bags that held them drop them. To bring one back: set it to In stock and
  visibility "Shop and search results". The candy counts in the copy (banner, About, pink band) follow the shop.
- Home: "Loved across Lebanon" reviews band (after Ready Mix), a slow moving row of review cards that pauses on
  hover. It shows real, approved WooCommerce product reviews with 4 or 5 stars (first name + initial, product,
  "Verified buyer"), and stays hidden while there are none. WooCommerce set to "Reviews can only be left by
  verified owners". Reviews arrive on product pages and are managed in WP Admin > Products > Reviews.
  The old Reviews page (39) holds placeholder testimonials and press quotes written when the site was generated;
  they are not used anywhere.
- BUBS: product names, images and pages already read "BUBS"; only web addresses (slugs) are lower case.
- Bag drawer: "You may also like", eight candies like the ones in the bag in a row to swipe across (arrows on
  computers; the row keeps its place when a candy is added), each with an add button and the reason
  (Also BUBS, Berry flavour, Fish shaped, Also sour...). Scored on brand, flavour and shape (from the name),
  sweet / sour category and gelatin-free; ties keep the shop order. An empty bag shows "Popular picks". The caps
  still apply. Shared shop part (pattern 241).
- Mix your own: the "Most popular / A to Z / Z to A" sort menu and its code were removed; the candies always show in
  the shop order (favourites first), narrowed by the filter chips and the search.
- Footer (all four pages) comes in like a wave instead of fading: as it scrolls into view the blue water rises
  into place with a small swell, the wave on top rolls sideways and grows to full height, and the columns bob up
  one after another. Plays once per visit to a page; skipped for visitors who ask for reduced motion, and on
  pages so short that the footer is already on screen. Shared footer part (pattern 242).

## Email look (2026-10-07)

Snippet 28 (wordpress/snippets/fika-emails.php) styles every WooCommerce email (order confirmed / on hold,
delivered, failed, refunded, cancelled, note, new account, password reset, email check, the shop's own new-order
emails) and the "Your bag is waiting" reminder like the website:
- Pink background, the Fika wordmark with three candy cartoons on top (an image, media 304, so the cute font shows
  in every email app), a white rounded card, small-caps serif headings (Fanwood Text, falling back to Georgia),
  Outfit / Helvetica for text, pink row lines, blue pill buttons, and the wavy blue footer (wave image media 305)
  with WhatsApp, email and Instagram.
- One "Delivery details" box (name, address with delivery area, phone, email) instead of identical billing and
  shipping addresses.
- Subjects, headings and closing lines, e.g. "Your Fika order #123 is confirmed" / "Thank you for your order!" /
  "We are packing your sweets with care..."; "Your Fika sweets have arrived" / "Time for fika!"; "Something went
  wrong"; "Welcome to Fika!" with the free-kilo line. Text typed in WooCommerce > Settings > Emails (other than
  WooCommerce's default wording) wins over these.
- Colours and logo settings are set by the snippet (base #004aad, background #fdeaf2, header image, centred).
- The images were made from the site's font and cartoons: wordpress/email-assets/ (make-email-images.js).
- Checked with WooCommerce's email preview (sample order) on desktop and phone widths; no emails were sent.
- Banner photos: Mix your own uses media 26 (fika-background-tpQWboFmUZ8wZ5Om.jpeg, candies spilling from a Fika
  bag), Ready Mix uses media 27 (fika-background-2-U56KoRAjvfi6k05N.jpeg, a Fika bag pouring into a bowl). Both
  are small portrait photos (about 1024 px wide), so on screens wider than 1024 px they are never stretched to the
  full width: the photo sits on the right at its own shape (at most its real size, 50-70% on common screens) and
  fades into a pink panel matching its background, with the title and text on the left in blue. Up to 1024 px wide
  (tablets, phones) the full-width banner stays, which is already sharp there. banner(..., split=...) in
  build-shop-pages.py.
- Banner type matches the home hero on every page: title in the cute font clamp(40px, 4.4vw, 84px), line 1.15;
  text (and the About kicker) white small-caps Fanwood Text clamp(16px, 1.5vw, 19px), line 1.6, shadow
  0 1px 8px rgba(0,0,0,.35). Checked equal on 1280, 1920 and 390 px wide screens.
- Order email clean-up (checked on order 313, rendered without sending): no "Pay with cash upon delivery." note
  (the cash-on-delivery instructions are left out of emails only); no "Additional information / Phone number"
  block (fika/phone has show_in_order_confirmation false; the phone is in Delivery details); the delivery row reads
  "Delivery: $6.00" without the method's name; "Hi Alan," in the same font as the rest; no closing line on the
  order confirmation / on hold emails; the white card runs straight into the blue wave (no pink gap).
- Delivery details in emails as labelled rows: Name, Location (street, apartment, city, Inside / Outside Beirut,
  repeats left out), Phone number, Email; labels in blue, never wrapping. The gap between "Here's a reminder of
  what you've ordered" and "Order summary" was tightened.

## Order confirmed popup (2026-10-07)

Snippet 31 (wordpress/snippets/fika-order-confirmed.php). After Place order, WooCommerce's "Order received" page
still loads as before (kept for WooCommerce and any tools that work on it), then the browser moves straight on to
the home page, where an "Order confirmed!" popup thanks the customer by first name: order number, bag weight,
total (cash on delivery), delivery time for their area, where the receipt was emailed, "Keep shopping" and, for
signed-in customers, "See my orders"; candy cartoons and a cartoon burst. The order is read through its private
order key, only for orders from the last 24 hours; the popup shows once (the link is cleaned from the address bar).
Without JavaScript, or when the order link is opened later, the Order received page shows as normal.
A "Purchase" event is sent to a Facebook/Meta pixel if one is ever added. Tested with test order 315 (deleted).
- Emails on phones in dark mode: the emails now tell mail apps to keep the light colours (color-scheme "light only",
  respected by iPhone Mail and Outlook), and the pink background is also set as a flat gradient, which Gmail's dark
  mode does not repaint. The footer is made of pictures (media 317-321: wave and Fika band, three linked buttons
  WhatsApp / Email us / Instagram, and the contact line), because dark mode recolours backgrounds but never
  pictures; a coloured band beside the wave picture showed up as a light-blue block. Images and the script that
  makes them: wordpress/email-assets/ (foot-img.js). Phone layout: text back to 15 px (WooCommerce shrank it to
  12 px), long values wrap, nothing wider than the screen (checked at 360 and 390 px).
- Every WooCommerce email (customer and shop emails, 14 types) and the bag reminder share the order-confirmation
  template: checked side by side through WooCommerce's preview (logo, card, headings, picture footer, light-colour
  setting, Delivery details box). The bag reminder is now built from the same parts as the order emails: "Your bag"
  summary with product photos, Weight lines, quantity and price columns, a Candies total, the Finish my order button
  and a pink Delivery box (Inside / Outside Beirut, Cash on delivery). Friendlier subjects added for cancelled,
  note, refunded, invoice and password-reset emails.

## Win-back emails (2026-10-07)

Snippet 35 (wordpress/snippets/fika-winback.php), WP Admin > WooCommerce > Win-back emails.
- Who: everyone who ordered, plus account holders who never ordered; the quiet period starts at their last order
  (or account creation). Shop staff, test addresses and unsubscribed addresses are left out.
- Email 1 at 30 days: "It's been a while, {name}! Here's what's new at Fika" / "We miss you!": New drops (candies
  added in the last 45 days, otherwise the favourites) with photos and prices, the news box (title and text edited in
  WP Admin; default: Lördagsmys), "Pick your mix" button. Never-ordered account holders get their own opening line.
- Email 2, 14 days after email 1 if still no order: "A little treat to welcome you back: 10% off": a personal code
  COMEBACK-XXXXXX (percent off the candies, individual use, one use, only for their email, valid 14 days) and a
  "Use my 10%" button. The link keeps the code in a cookie and adds it as soon as the bag reaches the cart (the
  customer's email is filled in, as WooCommerce requires for personal codes). Tested: $7.50 of candies -> $6.75.
- Ordering again starts over and counts as "came back". Every email has an Unsubscribe link (signed); the list
  is option fika_marketing_stop. Order emails are not affected by unsubscribing.
- Runs daily at about 10:00 Beirut (Action Scheduler, 07:00 UTC), at most 50 emails per run.
- Mode: Test (default: only alan.aboulhosn@gmail.com), Live (everyone due) or Off. Owner (2026-10-07): not rolled out at launch, keep Test until they decide.
  Settings: discount %, validity, timing (30 / 14 days), test addresses, previews of both emails to any address
  (an email 2 preview creates a real code for that address). Lists customers with their next email and date, and
  recent activity. Shop managers: GET /wp-json/fika/v1/winback-preview?n=1|2 renders without sending.
- Checked: timing rules on examples, current audience (5 customers, first emails due 6 Nov 2026), a run in Test
  mode sent nothing.

## Every screen size (2026-10-07)

Rules at the end of the shared footer part (pattern 242), built by build-shop-pages.py:
- One content column: side margin = max(10% of the screen, (screen - 1320 px) / 2), 1480 px from 2200 px wide.
  Header, home hero text, product grids and carousels, filter bar, sections and footer start at the same edge.
  Up to about 1650 px wide this is the same 10% margin as before.
- Heroes: home at most 900 px tall (and at most 92% of the width on upright tablets); page banners between 460 and
  760 px (82% of the screen height before). The home hero text column no longer narrows on wide screens
  (title max 10.6em, text 34em).
- From 2000 px wide the hero title grows to at most 108 px and the text to 24 px.
- Checked at 360-430 (phones), 768x1024, 1024x768, 1280x800, 1440x900, 1680x1050, 1920x1080, 2560x1440 and
  3440x1440: no sideways scrolling, no errors; phones unchanged. On laptops the only visible change: the Mix your
  own / Ready Mix banner text and the filter bar now start at the logo's edge.

## Front-end clean-up after the QA pass (2026-10-07)

Snippet 38 "Fika store pages" (wordpress/snippets/fika-store-pages.php):
- Product pages (/product/...): Fika header, footer, fonts, account menu and bag (patterns 239-242 printed around
  WooCommerce's product template). "Add to cart" is replaced by "Add to bag", which writes the same bag as the shop
  (candies 100 g at a time, Ready Mix one 500 g bag at a time; order caps apply); then a − / + pill and "View bag".
  Breadcrumbs, the category/tag line and Related products are hidden; "More sweets to mix" (the shop carousel without
  this candy) or "More Ready Mixes" sits under the reviews. Hidden or out-of-stock products say "Not available right
  now". The pages stay for reviews, search engines and shared links.
- Redirects: product categories Sweet / Sour -> /mix-your-own/?f=sweet / ?f=sour, tag Gelatin-free -> ?f=gf, Ready
  Mix -> /ready-mix/, other categories and tags -> /mix-your-own/ (301). Searches ?s=... -> /mix-your-own/?q=...
  and /cart/ -> /mix-your-own/?bag=open (302). Mix your own reads ?f= and ?q=; ?bag=open opens the bag on any of
  our pages; the address is then cleaned.
- Missing pages: Fika banner "This page wandered off" (pattern 335, built as part "lost" by build-shop-pages.py)
  with Mix your own / Back home buttons and the shop carousel; still answers 404 (noindex).
- The WordPress emoji script (s.w.org) is no longer loaded.
- The header bag, account menu, cartoons, order caps, rewards, sign-up nudges and lighter fonts (snippets 8, 10,
  11, 16, 20, 23) now also run on product and not-found pages.
Also: old pages Contact (37) and Reviews (39) set to draft (they now show the 404 page); the header bag label reads
"0 kg" / "0.5 kg" like the Bag button; delivery zone 1 is "Outside Beirut" (LB:OB), so checkout shows "Delivery:
Enter address to calculate" until an area is chosen ($5 inside Beirut, $6 outside); the old "TEMP ... (delete)"
snippets were deleted.
Checked at 1440x900 and 390x844: product pages (candy and Ready Mix), add and change amounts, View bag, the bag on
Mix your own, Checkout hand-off, every redirect, the 404 page, and home / shop / About / checkout / account pages:
no sideways scrolling, no script errors, no emoji requests. No test orders were created.

Product pages closed for now (2026-10-07, owner's request: nothing should take a customer there until the product
page is built): /product/... sends customers to /mix-your-own/ (Ready Mix products to /ready-mix/), temporary 302;
product names in My account orders and in emails are not links; the win-back "New drops" items link to the shop
pages; products, categories and tags are out of the sitemap. Shop managers (logged in) still see the styled pages.
To open them later, remove the "closed for now" block in fika-store-pages.php.

## Which emails go out (2026-10-07, owner's rule)

Only these go to customers without the customer asking:
1. Order confirmation (WooCommerce "Processing order", sent when a cash-on-delivery order is placed).
2. "Your Fika bag is waiting" (snippet 25), 1 hour after a shopper leaves the checkout without ordering. Live.
3. Win-back 1 (30 days) and 4. win-back 2 (+14 days, with a code) (snippet 35). Not part of the launch: Test mode,
   only to alan.aboulhosn@gmail.com.
Sent because the customer asked: reset password, new account (only when they sign up), confirm email address.
Sent only when the shop clicks for it: Order details (invoice) and Customer note.
Switched off: Completed order, Refunded order, Failed order, Order on-hold (Cancelled order was already off).
Shop emails to hello@swedishfikalb.com: Cancelled order, Failed order, Payment gateway enabled are on; New order is
off.

## Trial customer data deleted (2026-10-07, owner's request)

All of it was trials: 6 orders (incl. one in the trash), 6 customer accounts (with their addresses, rewards and
totals), saved bags, checkout-leaver answers, the analytics customer list, shopping sessions, a queued bag
reminder, and fika10's "used by" record. Kept: products, pages, settings, the administrator account, the fika10
code itself. Done with a temporary snippet (dry run first, then delete), removed afterwards.

## Back-end tidy-up (2026-10-07)

- Search engines: "Discourage search engines" is ON for the test site (blog_public 0, robots noindex) so the
  temporary address is not listed. TURN IT OFF AT LAUNCH (Settings > Reading), on the real domain.
- Deleted WordPress's sample "Hello world!" post and its sample comment.
- Privacy policy (page 3, /privacy-policy/): written for Fika, built by build-shop-pages.py
  (privacy-policy-3.raw.html) with the Fika header, footer and bag. Kept as a DRAFT (owner, 2026-10-07: do not make it a live page; never publish it, not even
  briefly for previews, unless the owner says so). The old WordPress template text is in pages/backup/privacy-policy-3.before.html.
  The header features (account menu, cartoons, sign-up nudge, caps, lighter fonts) also run on it.
- Inbox icon (the round sender picture that shows "F"): email-assets/fika-logo-icon.svg (square, SVG Tiny PS,
  the BIMI format) and fika-logo-icon-512.png. Ways to show it, all done by the owner (mailbox / DNS):
  1. Gmail, free: create a Google account on hello@swedishfikalb.com ("use my current email address") and set
     fika-logo-icon-512.png as its profile picture. Gmail often shows it for that sender; not guaranteed.
  2. BIMI (Yahoo, AOL, Fastmail and others; Gmail and Apple Mail also need a paid VMC/CMC certificate):
     DMARC on swedishfikalb.com at p=quarantine or p=reject, the SVG uploaded to the live site, and a DNS TXT
     record  default._bimi  "v=BIMI1; l=https://swedishfikalb.com/<path>/fika-logo-icon.svg;".
     Do this only after the site sends through the mailbox (SMTP password set in WP Mail SMTP): with DMARC
     enforced, mail sent by the web server's own mailer would be rejected.

## Meta Pixel + Conversions API (2026-10-07)

Snippet 43 "Fika: Meta pixel + Conversions API" (wordpress/snippets/fika-meta.php). Settings: WP Admin >
WooCommerce > Meta pixel (pixel ID, Conversions API access token, test event code, on/off, skip shop staff).
Off until the owner enters the pixel ID and token there (the token is stored in the database only).
- Browser pixel on every shop page; each event also sent from the server with the same event ID (Meta dedupes):
  PageView (all pages), AddToCart (shop cards, Ready Mix cards, product page; with content_ids = product number),
  InitiateCheckout (bag Checkout button; value, items, content_ids), Purchase (server: in the background when the
  order is placed, with hashed email, phone (961...), name, area, country, IP, browser, _fbp/_fbc; browser: on the
  Order confirmed popup; event ID purchase.<order id>). Each order gets a note "Meta: purchase sent ..." or the
  error Meta gave.
- Shop calls go through window.fikaTrack (mix part via build-shop-pages.py, product page in fika-store-pages.php,
  popup in fika-order-confirmed.php).
- Tested with a dummy pixel ID and token and one test order (deleted): all four events with event IDs in the
  browser, the server copies sent, the Purchase reached Meta (rejected the dummy token as expected).
- Privacy policy draft updated to mention the Meta Pixel (still a draft).
- For testing on the temporary domain: enter the Test event code so server events show under Test events only;
  remove it at launch. Browser events from the test domain do reach the real pixel.

## Launch checklist (collected 2026-10-07; launch is planned with the owner)

1. Domain: screenshot every DNS record of swedishfikalb.com first; change only the website records (A / CNAME);
   MX, SPF, DKIM and DMARC stay as they are; then send and receive a test email on hello@swedishfikalb.com.
2. Email: owner enters the hello@swedishfikalb.com mailbox password in WP Mail SMTP (removes "via
   srv1317.main-hosting.eu"); send a test email.
3. Settings > Reading: untick "Discourage search engines".
4. Meta: WooCommerce > Meta pixel: pixel ID + Conversions API token, tick On, "Save and send a test event", leave
   Test event code empty. (Integration already installed and active, snippet 43.)
5. Win-back emails stay in Test mode (owner: not part of the launch). Bag reminder and order confirmation are live.
6. Privacy policy (page 3) stays a draft until the owner says to publish it.
7. Product pages stay closed to customers until they are designed (fika-store-pages.php "closed for now").
8. Owner decision: the "New order" email to the shop (currently off).
9. Optional: Gmail sender picture (Google account on hello@ with email-assets/fika-logo-icon-512.png).

## Loyalty: checkpoints at 3, 6 and 10 kg (2026-10-08)

Snippet 11 (wordpress/snippets/fika-loyalty.php) replaces the old "1 kg free at 8 kg":
- 10 kg laps (repeating: 13, 16, 20 kg ...). Checkpoints: 3 kg = $5 off the next order (SWIM3-XXXXXX),
  6 kg = 25% off up to 1 kg (SWIM6-, 25% of the customer's own first kilo, most expensive first; $6.25 at
  $25/kg), 10 kg = $25 off the next order (SWIM10-).
- A checkpoint's gift lights up and spins only when its kilos are DELIVERED (Completed); kilos on the way show
  "unlocks once delivered". Tapping the lit gift claims the reward: a personal one-use code (email-locked,
  individual use: no stacking with other rewards, FIKA10 or win-back codes; no 10% before-you-go offer while a
  reward is held). Claimed codes are listed under the tracker and at checkout with a "Use" button (one per order;
  choosing another swaps it). No expiry.
- The part of an order paid by a reward does not count (e.g. $5 = 200 g at the order's price per gram).
- Undelivered / cancelled / refunded orders stop counting; unused rewards above the new total are withdrawn.
- The tracker shows the first lap with an unclaimed reached reward, else the current lap. User meta
  fika_swim_claims; claim endpoint POST /wp-json/fika/v1/swim-claim (signed-in, REST nonce).
- Economics at $16/kg landing cost and $25/kg price ($9/kg margin, $90 per 10 kg). $5 off = 200 g free and
  $25 off = 1 kg free for the customer (all sweets $2.50 / 100 g), so both wordings cost the same. The cost
  depends on how the reward is used: as extra candy on top of the usual order, $3.20 + $6.25 + $16 = $25.45 per
  lap (28% of the profit); on an order the customer would have placed anyway, $5 + $6.25 + $25 = $36.25 (40%).
- Sign-up nudges and the privacy policy draft now say "Fika rewards" instead of "free kilo".
- Tested with a test customer and test orders (deleted): states locked / on its way / ready / claimed / used,
  claim, both codes at checkout ($5.00 and $6.25 off), grams paid by a reward not counted (1.2 kg -> 1.0 kg),
  withdrawal after "Undelivered", 10 kg and the start of lap 2, desktop and phone, My account.

Wording and checkout (2026-10-08): rewards are worded as candy with the value alongside: 3 kg "200 g on us"
(worth $5), 6 kg "25% off a kilo" (up to $6.25), 10 kg "A whole kilo on us" (worth $25); labels 200 g / 25% /
1 kg; delivery not included. Checkout takes off the best claimed reward by itself (the biggest one that fits the
bag: $25 waits for a bag of $25 or more), unless the customer has another code on or tapped "Save for later"
(remembered for the visit); "Use" switches rewards (one per order). The bag drawer says the reward comes off at
checkout. Tested (test data deleted): claim all three, $30 bag -> $25 off, Save for later, switch to $5, $10 bag
-> $5 off (the $25 one says "Best on an order of $25 or more"), order placed with its reward.

Customer's choice (2026-10-08, owner): nothing is applied by itself. At checkout every saved reward has "Use" /
"Remove"; rewards combine with each other in any mix (e.g. 200 g + the free kilo) but not with other codes
(FIKA10, win-back: refused with a message either way; reward coupons are no longer "individual use"). The 25%
reward covers at most 1 kg of the order (the priciest sweets; checkout says so on bigger orders). Tested (test
data deleted): 2 kg bag + 25% = $6.25 off; 1.2 kg bag + 200 g + 1 kg = $30 off (delivery still paid); FIKA10
refused next to rewards; order placed with two rewards.

Rewards in the order that reaches them (2026-10-08, owner): what counts is delivered + on the way (+ the bag at
checkout). A gift reached by placed orders lights up and spins (claim on the tracker); a gift the bag reaches
lights up too ("This bag reaches 3 kg!"). Rewards go ON TOP of the order: what a reward pays for (200 g, 250 g,
1 kg) does not count, so the paid part of the bag must reach the checkpoint. Example: at 2 kg, a 1.2 kg bag pays
1 kg (reaching 3 kg) and the 200 g are free. The bag drawer, tracker, header menu and checkout say how much to add
("Add 500 g more and get 200 g on us in this order", within 1 kg); checkout lists rewards this order unlocks
("New", claimed and applied with one tap) next to saved ones; lowering the bag takes a reward back off with
"Rewards go on top: add 200 g more". A refused (Undelivered) order drops out and unused rewards above the new
total are withdrawn; a reward already used on a later order stays used (accepted risk, $3.20-$16 at most).
Tested (test data deleted): the 2 kg example at 700 g / 1 kg / 1.2 kg, reward applied and placed, tally lands on
3.0 kg; 6 kg reached by an order on its way; that order marked Undelivered -> gift locked again.

Hints count to the checkpoint first (2026-10-08, owner): at 2.2 kg "Add 800 g more to reach 3 kg and get 200 g on
us in this order"; at 3 kg "You reached 3 kg! Add 200 g on top and they are on us"; at 3.2 kg "This bag unlocks
200 g on us!". Same steps in the bag drawer, checkout, tracker and header menu.

Saved rewards (2026-10-08, owner): no "on top" rule once a reward is saved: it can be used on any later order,
even an order of just the free candy (tested: 200 g + "200 g on us" = $0 + $5 delivery; 1 kg + "a whole kilo on
us" = $0 + $5 delivery). The "on top" rule only applies to a reward used in the order that reaches it.

## Loyalty ladder v3 (2026-10-08, owner): 100 g @3 · 200 g @6 · 400 g @10 · 1 kg @15

- 15 kg laps (then 18, 21, 25, 30 kg ...). Rewards are free sweets: 100 g ($2.50 off, SWIM3-), 200 g ($5, SWIM6-),
  400 g ($10, SWIM10-), a whole kilo ($25, SWIM15-). The 25% reward is gone.
- Simple checkout: when the bag reaches a checkpoint, "Use" takes the sweets off that same order (a 3 kg bag at 2.2 kg
  is reached; no "add on top" step). Saved rewards can be used on any later order, also on their own.
- Free sweets do not count towards the next checkpoint (the tracker says so under the lane).
- Hints count to the next checkpoint ahead ("Add 500 g more to reach 10 kg and get 400 g on us in this order");
  reached rewards are listed as waiting. A reward worth more than the bag says "best on a bag of 1 kg or more".
- Economics (landing $16/kg, price $25/kg, about 800 g per order): free sweets 1.7 kg per lap ($27.20 at cost) over
  about 13.3 kg paid ($119.70 profit) = about $2.05 per paid kilo (~23% of profit), only on customers who finish a
  lap. Rewards on about the 4th, 8th, 13th and 19th order.
- Tested (test data deleted): customer at 9.2 kg: 3 and 6 kg ready, 800 g bag unlocks 400 g; checkout with all
  three: $20 bag - $17.50 + $5 delivery = $7.50; desktop and phone.

## 2nd-order mystery taste: spin the wheel (2026-10-08)

Snippet 46 "Fika: 2nd-order mystery taste" (wordpress/snippets/fika-taste.php):
- Signed-in customers with at least one order get one spin (once). Before the first order a teaser card says the
  2nd order comes with a surprise. The card sits above the rewards tracker (home, Mix your own, Ready Mix) and on
  My account.
- The wheel shows every candy in the shop (in stock, visible, not Ready Mix) with its photo. Filters: All sweets,
  Gelatin-free, Gluten-free, Vegan; a filter shows once products carry the tag gelatin-free / gluten-free / vegan
  (today only gelatin-free exists: tag candies "gluten-free" and "vegan" in WP Admin > Products > Tags to add the
  other two). Tap the candy button: the server picks the candy (user meta fika_taste), the wheel lands on it.
- The win goes into the next order by itself: a free line "Mystery taste: 50 g, free" of that candy (price 0,
  quantity locked), added whenever the bag reaches checkout with something else in it; removing it at checkout
  keeps it for a later order. Placing the order uses it. The order line says "Mystery taste: 50 g, free" for packing.
- It does not count towards the rewards track (fika-loyalty.php skips it). Checkout bag and the Order confirmed
  popup count it as 50 g. Cost: 50 g at landing cost, about $0.80, once per customer.
- Tested (test data deleted): teaser, spin with All and Gelatin-free, result card, free line at checkout ($20 bag
  -> $25 with delivery, taste $0, not editable), order placed (line meta), card gone, tracker 0.8 kg; phone view.

Mystery tastes on the lane (2026-10-08, owner): the spin is no longer "the 2nd order"; it is two stops on every
15 kg lap, at 1.5 kg and 12.5 kg (little wheels in the water; gifts stay above). A stop lights up and spins once the
orders, or the bag being filled, reach it; tapping it opens the candy wheel (same filters). The win (user meta
fika_tastes, one per lap and stop) joins the order that reaches the stop, or a later one: the free 50 g line is in
the cart only while the bag reaches the stop (it follows quantity changes at checkout). The bag drawer says "Add
200 g more to reach 1.5 kg and get a mystery spin" / "a mystery spin is waiting on your rewards lane". Cost: 2 x 50 g
per lap, about $1.60 (about 12c per paid kilo). Tested (test data deleted): 1 kg delivered + 300 g / 600 g bag,
spin from the lane, taste in and out at 600 g / 400 g / 600 g, order placed with it (650 g), stop shown as used.
Spin rules (2026-10-08, owner): one spin per account, per stop, per lap; no way round it. The spin is recorded on
the account on the server (user meta fika_tastes, plus an atomic lock option fika_spin_<user>_<lap>_<stop> taken
before the candy is picked), so reloading, leaving, emptying the bag, another browser or device, clearing cookies,
or tapping several times at once cannot spin again ("You already spun this wheel"). The server picks the candy.
Emptying the bag takes the free 50 g out of the cart but keeps the candy saved on the account; it comes back by
itself when the bag reaches the stop again. If an order with the taste is refused / cancelled / refunded, the same
candy is saved again (order note), no new spin. Deleting an account removes its spin locks. Tested (test data
deleted): 3 spins at once -> 1 accepted; reload, second browser, emptied bag -> refused; candy back at 600 g;
order placed; marked Undelivered -> taste saved again, spin still refused, back in the next checkout.

Banner photos swapped (2026-10-08, owner): Mix your own now uses the bag pouring into a bowl (media 27,
fika-background-2-...jpeg), Ready Mix the candies spilling from a bag (media 26, fika-background-...jpeg); each photo
keeps its own position and wide-screen fade colours. Built by build-shop-pages.py, pages 40 and 38 republished.

## Fika dashboard (2026-10-08)

Snippet 49 "Fika: shop dashboard" (wordpress/snippets/fika-dashboard.php): WP Admin > Fika dashboard (left menu,
under Dashboard) + a "Fika: last 7 days" box on the WordPress Dashboard. Periods 7 / 30 / 90 days / all time,
compared with the period before. Shows: sales (sweets after discounts), orders, average order (with delivery), kg
sold, estimated gross profit (landing cost per kg set on the page, default $16), delivery fees, new / returning
customers, refused or cancelled; daily sales chart; launch health (SMTP, search engines, Meta, win-back mode,
privacy page, New order email, background jobs); top sweets by kg; delivery areas; leading customers (all time:
orders, kg, spend, last order, rewards lane); rewards claimed / used / cost and mystery tastes; FIKA10 and win-back
codes used; bag recovery (saved bags, reminders, orders after a reminder); why shoppers left the checkout;
refused / cancelled / refunded orders; latest customer notes. Orders with "TEST ORDER" in the note are left out.
Cached 10 minutes ("Refresh"). WhatsApp / email complaints are not on the site.

Full launch test (2026-10-08): every page on desktop and phone (no script errors, no sideways scrolling, redirects
and not-found pages right), guest order outside Beirut ($20 + $6, phone, area, weights, confirmation email,
popup, bag emptied), signed-in rewards and mystery tastes (tested earlier the same day). Home page 1.6-2.4 s on a
phone; two bursts of rapid requests stalled ~11 s at the hosting CDN (hcdn) - consider turning on page caching at
launch. Real activity seen: alan.aboulhosn@gmail.com had 3 saved bags today (1 reminder sent), left in place.

## Fresh start: customer data wiped (2026-10-08)

- All customer data from testing (including the owner's own trial use of the alan account) was deleted.
  - This covers orders, customer accounts, saved bags, exit reasons, sessions, pending bag reminders, spin locks
    and the admin's loyalty/taste tallies. The dashboard caches were cleared too.
  - Kept: the 34 products, all settings and snippets, and the admin account.
- The temporary wipe snippet was deleted afterwards.

## Rewards lane: stripes follow the bar's curve

- The striped "in your bag" part of the rewards lane was a rectangle sitting on the rounded bar.
- The water, on-the-way and in-bag fills now sit inside a rounded `.fs-clip` layer, so they take the bar's curved shape.
  The stripes also fill the bar's full height.

## Old Website Builder addresses (for the domain switch)

- The only link sent to customers is the swedishfikalb.com homepage. It opens the new home page as-is after the switch.
- Common old addresses redirect (302, and only if no real page has that address):
  - /about, /contact, /contact-us, /reviews, /testimonials go to About us;
  - /store, /products, /all-products go to Mix your own;
  - /faq and /home go to the home page.
- /shop, product addresses, categories, search and /cart were already redirected. Anything else shows the Fika "page not found".
- /privacy-policy still returns "page not found" on purpose (draft).
- To add more, edit the `$map` in fika-store-pages.php (snippet 38).

## Pre-launch polish (2026-10-08)

- **Browser tab icon:** media 400 (email-assets/fika-site-icon-512.png, the logo cropped tighter), set as the Site Icon.
- **Titles:** the tagline is "Swedish candy, delivered in Lebanon". The home page title is now
  "Fika – Swedish candy, delivered in Lebanon"; other pages are "<Page> – Fika".
- **Share previews and Google description:** snippet 53, fika-seo.php. It adds a meta description plus Open Graph and
  Twitter tags on home, Mix your own, Ready Mix and About us. The share picture is media 401
  (email-assets/fika-share-1200x630.jpg, the candy bowl). og:url follows the site address, so it switches to
  swedishfikalb.com with the domain.
- **Page caching:** the LiteSpeed Cache plugin is activated, and snippet 55 (fika-cache.php) makes it work.
  - Problem: the Hostinger theme marked every page "do not cache" on woocommerce_cart_updated, which WooCommerce
    fires on every page for a new visitor. The snippet removes that one hook.
  - Cached: the guest views of the shop pages and the redirects (cached pages answer in about 0.2–0.4 s at the
    server). The checkout, My account, signed-in visitors and the REST API are not cached.
  - Products, prices, stock and the bag load in the browser, so cached pages are never stale.
  - The cache is cleared when a snippet, a synced pattern (header/footer) or the Meta settings change. Otherwise use
    the admin bar: LiteSpeed Cache > Purge All.
  - Tested with a guest order from a cached page ($26; test orders deleted) and a desktop + phone sweep.
- **Meta PageView with caching:** the PageView event ID is now made in the browser, and the server copy goes through
  /fika/v1/meta like the other browser events. A cached page would otherwise give every visitor the same event ID.
- **"New order" email to the shop:** on, to hello@swedishfikalb.com, from WooCommerce > Settings > Emails. It was
  tested by sending to a test address, then the recipient was set back. Until the SMTP password is entered it is sent
  "via srv1317" and may land in spam.

## Past orders join an account once the email is confirmed (2026-10-08)

Owner's rule: orders from before an account (including those imported from the old Website Builder store) count
toward rewards only if the customer makes an account, matched by email.

- Before, sign-up linked guest orders with the same email right away. Account emails are not checked, so anyone
  could have signed up with a customer's email and seen their orders (address, phone) and rewards.
- Now (snippet 10, fika-accounts.php):
  - My account shows a "Ordered from Fika before?" box while there are guest orders under the account's email.
    Its "Email me a link" button sends one email, and only when the customer taps it.
  - The link (48 hours, one use, at most one email per 5 minutes) confirms the email and moves those orders into
    the account. Totals and the rewards lane update at once.
  - A password reset also confirms the email and connects the orders.
  - Once confirmed, later orders placed while logged out with that email join the account automatically.
  - Changing the account email resets the confirmation.
- Tested: sign-up does not link; a wrong token is refused; the link connects 2 orders (3.5 kg, the 3 kg reward
  lit); a reused link is refused; a later guest order joined; emails match case-insensitively. Test data deleted.
- For the import: old orders go in as guest orders (no account) with their real email, date and status Completed,
  and line items mapped to the current products, so the kilos count correctly.

## Accounts confirm their email at sign-up (2026-10-08, replaces the "Email me a link" box above)

Owner's choice: one confirmation email at sign-up. The account works once it's confirmed, and earlier orders with
that email count from then on. Snippet 10 (fika-accounts.php):
- **Sign-up on My account** sends "Confirm your email for Fika" (button "Confirm my email", valid 7 days). This
  replaces WooCommerce's "Welcome" email, which is switched off. The customer is not logged in and sees "Almost
  there! Check your email".
- **Logging in before confirming** is refused with "Please confirm your email first … send it again". The resend
  link is signed and limited to one email per 2 minutes.
- **The link** confirms the email, logs the customer in, and moves every earlier guest order with that email
  (old-store imports included) into the account. The kilos and rewards count at once, and My account says
  "Your account is confirmed – N earlier orders joined". Used or wrong links are refused.
- **Accounts made at checkout** stay logged in so the order goes through. They get the same email, and My account
  shows "Please confirm your email" with "Send the link again". They need the link to log in next time.
- **"Forgot password"** also confirms the email and connects the orders.
- **Later orders** placed while logged out with a confirmed email join the account.
- **Staff accounts** (admin, shop manager) are never held back. Changing a customer's email needs a new confirmation.
- **Tested:** sign-up, blocked log-in, resend, wrong link, real link (2 orders, 3.5 kg, the 3 kg reward lit), reused
  link, log out and log in again, and an account made at checkout. Test data deleted.

## Fish cursor from the first paint, on every page (2026-10-08)

- Problem: the cursor style was in the "fish cursor and header behaviour" pattern (240), about 90 KB into each page,
  so the normal arrow showed while each new page loaded. My account and checkout had no fish at all.
- Now snippet 58 (fika-cursor.php) prints the cursor style at the very top of <head> on every front-end page. The
  pictures are inlined, so no download is needed.
- build-shop-pages.py leaves the style out of pattern 240, which now holds only the bite animation and header
  behaviour. Page sizes are unchanged: the style moved, it wasn't duplicated.
- The cursor pictures are kept in wordpress/cursor/ for reference.
- Checked: the fish (and tilted fish on links) on home, Mix your own, Ready Mix, About us, My account and the 404
  page, a text cursor in fields, and the bite on click.

## No flash of the bare page while loading (2026-10-08)

- Problem: the theme prints the page title (e.g. "Mix your own") first in the page. Our styles that hide it come
  later, after a large inline font, so a loading page showed as a white page with that title.
- Fix: snippet 58 (fika-cursor.php, now "first paint") also hides `.hostinger-ai-page-title` from the very top of
  <head> on every page. The browser keeps the previous page on screen until the new one has its real content.
- Checked on a slowed connection (150 KB/s), clicking a menu link and reloading: no white or title-only frame. The
  old page stays up, then the new page appears with its header and banner. The web fonts arrive a moment later.

## Mystery spin pops up by itself (2026-10-08)

- When a customer reaches a spin stop (1.5 kg or 12.5 kg of the lap), counting what's in the bag, the candy wheel
  now opens by itself in the middle of the page. This happens on any page with the rewards lane: home, Mix your
  own, Ready Mix, My account. It also opens on arrival when a stop is reached but not yet spun.
- Everything else is unchanged: the same wheel, text, filters, one spin per stop per lap, and the same server checks.
- Closed without spinning: it stays on the lane (tap the little wheel) and doesn't pop up again in that browser
  visit (sessionStorage `fika_spin_pop_<user>_<lap>_<g>`). A new visit shows it again until they spin.
- Code: `autoSpin()` in the lane script (fika-loyalty.php, snippet 11). The wheel (fika-taste.php, snippet 46)
  now reports `closed(won)`.
- Tested on desktop and phone with a test customer (deleted afterwards):
  - 1.2 kg + 200 g: no pop-up;
  - + 300 g (1.5 kg): pops up, centred;
  - closed: no pop-up again on bag change or reload;
  - the lane wheel still opens it;
  - new visit: pops up on arrival; spun and won, then no more pop-ups;
  - the 12.5 kg stop works the same.

## "Tap to spin!" label on a waiting spin (2026-10-08)

- While a spin stop is reached but not spun (for example after the pop-up was closed), its little wheel on the
  lane carries a bouncing pink "Tap to spin!" bubble with a pointer. Tapping it opens the wheel, and it disappears
  once they spin.
- On phones (700 px and below) it says "Spin!" because there's little room between the gift badges. The 1.5 kg
  label leans left of its wheel, since the 3 kg badge sits right next to it.
- CSS only (fika-loyalty.php, `.fs-spin.is-ready::before` / `.is-bag::before`, keyframes fsHop / fsHopL). It
  holds still for people who turn off animations.
- Checked on desktop, 390 px and 320 px, at both stops. At 320 px it touches the badges, which already touch each
  other at that width. Test data deleted.

## Finish line at 15 kg; lap 2 starts at 0 (2026-10-08)

- The lane has a checkered finish line at 15 kg, and a small checkered flag next to "15". It replaces the dashed
  line there; the 1 kg gift still sits above it.
- Laps already restarted at 0 (FIKA_SWIM_LAP 15000). The lane moves to the next lap once every reward and spin of
  the lap is taken. What's new is the moment it happens:
  - The first time a customer sees a new lap, the fish swims from where they last saw it to the finish line.
  - The finish line glows, confetti pops, and the title says "Lap 1 finished! You crossed the finish line. Lap 2
    starts at 0 kg, with every reward and mystery spin back on the lane."
  - Then the fish restarts from 0 and swims to their lap-2 total.
  - It's remembered per browser (localStorage `fika_swim_lap_<user>`), so later visits show the normal lap-2
    messages.
- A bag that would take the customer past 15 kg says "This bag crosses the finish line!".
- Tested on desktop and phone with a test customer at 15.5 kg, every lap-1 reward claimed and both spins done
  (deleted afterwards).

## Confirmation email: button text and spam (2026-10-08)

- **Empty button:** the email look's rule `a { color: #004aad !important }` turned the white button text blue on
  blue. The confirm link now uses the `button` class like the other emails, and reads "Verify and confirm my
  email". A "Button not working? Copy this link" line underneath holds the full address.
- **Plain-text copy:** every HTML email now also carries a plain-text version (phpmailer_init in
  fika-emails.php), with links written as "text: address". Spam filters count HTML-only emails against them.
- **Why the site's emails land in spam (checked 2026-10-08):**
  - WP Mail SMTP's mailer is "mail": the web server (fr-int-web1317.main-hosting.eu) sends as
    hello@swedishfikalb.com.
  - The domain's SPF only allows Hostinger's mail servers (`include:_spf.mail.hostinger.com
    include:_spf.builder-mail.hostinger.com ~all`), and the email carries no DKIM signature for
    swedishfikalb.com. DMARC (`p=none`) therefore fails, and Gmail files it under spam.
  - The SMTP settings are ready (smtp.hostinger.com, 465, SSL, user hello@swedishfikalb.com), but no password is
    stored. DKIM keys hostingermail-a/b/c are already in DNS.
  - **Fix (owner):** WP Admin > WP Mail SMTP > Settings: choose "Other SMTP", enter the hello@ mailbox password,
    save, then Tools > Email Test. Mail then goes out through Hostinger's mail servers, signed, and SPF, DKIM and
    DMARC pass. No DNS change is needed.
- At launch, the links in emails switch from the test address to swedishfikalb.com, matching the sender. That
  helps as well.

## My account: Rewards and My details; log in goes home (2026-10-08)

- **Log in** (My account form or the header menu's "Log in") now goes to the home page. If the form asked for a
  different page, such as the checkout's log-in, it keeps that.
  - Note: WooCommerce passes the referring path ("/my-account/"), so paths are compared.
  - The confirmation-email link still opens My account > Rewards, with "Your account is confirmed".
- **Tabs:** Rewards, Orders, My details, Log out. The header dropdown has the same entries: Rewards, Orders,
  My details, Log out.
- **Rewards** (the old Dashboard):
  - WooCommerce's "Hello … From your account dashboard" lines are hidden.
  - It shows the mystery-taste card, the fish lane, the kg and order totals, then two cards.
    - "Ready to use": saved codes (with the code), gifts reached but not unlocked ("tap the glowing gift"), and
      mystery tastes on their way.
    - "Already used": rewards with "Used on order #N, date", and mystery tastes with their order.
  - A gift unlocked on the lane moves into "Ready to use" straight away (event `fikareward`).
  - Code: `fika_rewards_lists()` in fika-loyalty.php. The lane's own code list is hidden on this page.
- **My details** (edit-account): account details, name/email/phone/password, and below them the delivery address
  form.
  - The address form posts to edit-address/shipping, so WooCommerce saves the right address, then comes back here
    with "Address changed successfully".
  - /my-account/edit-address/ redirects here (#delivery).
  - If a save fails validation, WooCommerce shows the errors on the address page itself.
- Tested with a test customer (deleted): log in goes home, dropdown and tab names, the hidden intro, Ready to use →
  unlock → code shown, the code used on an order → "Already used", address saved and back on My details, and the
  old address link redirects.

## Home hero: no pink band above the candies (2026-10-08)

- The original hero photo (1920 x 1072) is plain pink over its top 41 %. On MacBook-shaped screens and phones that
  showed as a big empty band between the header and the candies.
- The home hero now uses media 426 (wordpress/pages/media/fika-hero-candy-1920.jpg): the same photo with its top
  327 px cut off (1920 x 745). A strip of pink about the header's height stays, so the candies start right under
  the menu.
- build-shop-pages.py (HERO_CROP) moves the googly-eye positions up by the same amount. One file for all screens:
  phones zoom into it, so the old 1000 px copy would look soft.
- Checked at 1440 x 820, 1920 x 1080, 1280 x 900, 768 x 1024 and 390 x 844. The eyes stay on the skulls.

## Rewards page: only what's ready, with a button each; login wording; reset-password card (2026-10-08)

- **Rewards page:** the "kg delivered / orders delivered" boxes, the line under them and the "Already used" card
  are gone. One "Ready to use" card lists everything available, each with a button:
  - Saved code → **Use**: remembers the code (sessionStorage `fika_use_code`) and opens the bag. Once the bag is
    at checkout, the checkout's rewards box taps "Use" on that code by itself, with the same checks as a tap.
  - Gift reached but not unlocked → **Unlock**: claims it (POST /fika/v1/swim-claim), shows the code, and reloads
    so the lane shows it claimed.
  - Mystery spin reached but not spun → **Spin**: opens the candy wheel. After a win the page reloads.
  - Mystery taste won → **Use**: opens the bag. It joins the order at checkout by itself.
  - Tested: Unlock (3 kg → SWIM3 code), Spin (won a taste), Use → bag → checkout with the code applied ($2.50
    off) and the free 50 g line. Test data deleted.
- **Wrong email or password:** "The email or password is incorrect. Forgot your password?" (with the link),
  instead of WordPress's "The password you entered for the email address … is incorrect." The unconfirmed-email
  message stays as it is.
- **Wording:** "Lost your password?" is now "Forgot your password?". The reset page says "Enter the email you
  signed up with, and we'll email you a link to set a new password", with "Email address" and "Email me a link".
- **Reset-password page** (store-skin-account.html, page 88): a rule for the log-in forms stripped the reset
  box's padding, corners and centring. It is now a centred white card with the title "Forgot your password?"
  ("Set a new password" on the new-password step), and a full-width button.

## Log in / Create an account: each message in its own box (2026-10-08)

- WooCommerce prints every message in one bar above both boxes. A script on the logged-out account page
  (fika-accounts.php) now moves each message into its box, restyled as a soft panel under the box title:
  - **Create an account:** sign-up errors (e.g. an email already registered), "Almost there! Check your email",
    and "Send the link again". After a successful sign-up the form is emptied, so it isn't sent twice.
  - **Log in:** "The email or password is incorrect. Forgot your password?", plus password-reset and
    confirmation-link messages.
- Tested on desktop and phone: wrong log-in, a taken email at sign-up, and a new sign-up. Test accounts deleted.

## Customer data wiped again (2026-10-08, owner's request: everything so far was trials)

- **Deleted:**
  - every order (#418);
  - every non-staff account: alan.aboul@gmail.com, alan.aboulhosn@gmail.com and sarahnatalieaboulhosn@gmail.com;
  - the personal reward code SWIM3-OBP8AC;
  - saved bags (5), checkout-leaving answers (1), spin locks (5), shopping sessions (5), the pending bag
    reminder (1) and customer lookup rows (3);
  - the admin's reward/taste meta and the dashboard caches.
- **Kept:** the admin account (cesar.aboulhosn@hotmail.com), the 34 products, the FIKA10 code (unused) and all
  settings, pages and snippets.
- Anyone who used the old accounts must sign up again; their email is free to register.
- Done with a temporary snippet (dry run first, then delete), which was then removed.

## Confirmation email: one email-safe button that always works (2026-10-08)

- Removed the "Button not working? Copy this link" line. The plain-text copy of the email still carries the link
  for text-only mail apps.
- The button is now an email-safe ("bulletproof") table button: a blue table cell with the link filling it
  (`bgcolor`, `mso-padding-alt`), so it shows and taps in Gmail, Apple Mail and Outlook for Windows.
- **The link keeps working for its 7 days instead of once.** Mail scanners (Outlook Safe Links, work inboxes)
  open links before the customer does, which used to use the link up. Now every open confirms and logs the
  customer in, landing on Rewards with "Your account is confirmed".
- If someone else is logged in on that browser, the link switches to the right account.
- A newer link (from "send it again") replaces the older one. An old link opened after confirming says "already
  confirmed, log in below".
- Tested: sign-up, a cookie-less "scanner" open, the customer's tap, and a tap in another browser. All were
  confirmed and logged in. Test account deleted.

## Home: video reviews carousel "Loved across Lebanon" (2026-10-08)

- Under the Ready Mix block on the home page. It comes from wordpress/pages/home-videos.html, which
  build-shop-pages.py inserts as `home_videos`. The text-review band below it stays hidden until real reviews exist.
- **Clips:** the owner's 5 TikTok videos (media 431-435) were cut to 20 / 22 / 20 / 22 / 9 s from the start
  (bag, unboxing, first bites).
  - The cuts land on pauses in the speech, or fade out where the sound is continuous.
  - TikTok's end screen is cut off.
  - The TikTok logo and the creator handles stay on the video as credits.
  - Encoding: H.264 576x1024, about 2 MB each, faststart. Files are media 437/439/441/443/445, with posters
    438/440/442/444/446 (copies in wordpress/pages/media/reviews/).
- **Behaviour:**
  - The middle clip plays muted once the carousel is on screen (browsers block autoplay with sound), with a
    "Sound" button and a pink progress bar. It pauses when scrolled away.
  - The clips either side sit smaller, faded (50 %) and turned away (rotateY ±38°, translateZ -260px).
  - Arrows, dots, a tap on a side clip, or a swipe turn the carousel. When a clip ends, the next turns in.
  - Only the middle clip downloads (preload none).
  - Side clips show their poster as an `<img>` over the video: browsers paint a resting video inside the tilted,
    faded cards as a grey box. A clip that moved aside returns to its picture.
- Written without "&&" and " < " (WordPress rewrites them in page content).
- **Tested:** desktop and phone; autoplay, Next, dots, tapping a side clip, sound, auto-advance after the end,
  pause when scrolled away, and the side pictures after scrolling and turning. The test browser cannot play H.264,
  so WebM copies stood in for the test only. Real browsers (Chrome, Safari, Firefox, Edge, iOS, Android) play the MP4s.

## "Verify" goes to the home page, once (2026-10-08)

- Tapping "Verify and confirm my email" now logs the customer in and lands on the **home page** (not My account)
  with a short welcome under the header: "Your account is confirmed, <name>!", plus "N earlier orders joined your
  account…" when old orders were connected.
  - It closes after 7 s or with ×, and the address is cleaned, so a reload doesn't show it again.
- **Verifying happens once.** The first open of the link starts a 30-minute window, so a mail scanner opening it
  first doesn't spoil the customer's tap. After that the link stops working.
  - Opening an old link logged out: "Your email is already confirmed – log in below".
  - Opening it already logged in: the home page.
  - Every later log-in is the usual email + password, which also goes to the home page.
- Tested: sign-up → scanner open → customer tap (home, logged in, welcome; gone after reload) → log out → password
  log-in (home, no welcome) → link after the window (log-in page, or home when logged in). Test account deleted.

## Phones: sharp photos (Hostinger CDN shrinks JPEG/PNG) (2026-10-08)

- **Cause of the blurry hero on phones:** Hostinger's CDN ("Smart Image Optimization") shrinks every JPEG/PNG on the
  way: 800 px wide for phones, 1600 px for computers, plus heavy compression. The 1920 px hero reached an iPhone
  at 800 px and was stretched about 4x.
  - Hostinger: this optimization cannot be changed on a temporary domain. After the switch to swedishfikalb.com,
    hPanel > Websites > Dashboard > Performance > CDN > Manage can turn off "Smart Image Optimization".
  - Source: https://www.hostinger.com/support/7935917-hostinger-cdn-website-optimization/
- **The CDN passes WebP through untouched** (tested with an iPhone user agent), so all page photos are now
  full-size WebP:
  - hero for computers: media 454, 1920x745;
  - hero for phones: media 455, 1920x1490, the middle of the photo enlarged 2x with the EDSR super-resolution
    model. Phones only show that middle part. The googly eyes measure from it.
  - Mix your own banner: 459. Ready Mix banner: 460. Bowl: 461. Jars: 458. Pour: 456. Gang: 457. All at the
    originals' full size, which were fetched from the server because the CDN copies were already shrunk.
  - Sources in wordpress/pages/media/.
- Phone audit (390 pt wide at 3x, every page): no photo is shown larger than its real resolution, nothing spills
  sideways, and no text is under 12 px.
- **BUBS order caps:** all BUBS products are capped at 200 g per order (fika_max_g = 200), except Forest Berry
  Ovals at 100 g. The page cache now clears itself when a product's "Max per order" changes, because the caps are
  written into the shop pages.

## Two product photos on white (2026-10-08)

- BUBS Fruity Lemon Mix Skulls (product 118) and BUBS Forest Berry Ovals (121) were the only product photos with a
  grey background (248 and about 241, with a darker vignette). All the other product photos are white (253-255)
  and were left untouched, at the owner's request.
- The new main photos are media 483 and 484 (wordpress/pages/media/products/).
  - The background connected to the photo's edge became white; the candy and its soft shadow are unchanged.
  - The old photos (129, 128) are still in the media library.
- Cleanup: the temporary snippets and the CDN test uploads (452, 453) were deleted.

## Launch run-through (2026-10-08)

Full check of the shop on desktop and phone, every customer journey, the emails and WP Admin.

**Fixed during the run-through**
- **Checkout delivery wording:** before an area is chosen, WooCommerce said "No available delivery option",
  which reads like an error. It now says "Working out your delivery…" (snippet 5).
- **Duplicate email confirmation:** WooCommerce 11.1 has its own "Confirm your email address" prompt, which
  appeared on the Orders tab next to ours. Ours is the only one now: two filters in snippet 10, and the
  WooCommerce email "Verify email" is off.
- **Empty country for new accounts:** a new account's checkout had no country, so it asked for a postcode and
  region and the order failed. The country now defaults to Lebanon for everyone (snippet 5).
- **Clock:** the site ran on UTC. It is set to Asia/Beirut, so order times and emails show Lebanon time.

**Checked and working**
- Crawl: 8 pages on desktop and phone, every link: no broken page, no failed request, no script error.
- Guest order: bag, delivery fee by area, confirmation popup, "Your Fika order is confirmed" email to the
  customer, "New order" email to the shop.
- Account: sign-up sends one email only ("Confirm your email for Fika"). The button logs the customer in and
  lands on the home page with a welcome message. Later logins are email and password only, and land on the
  home page. Wrong password, forgot password (one email, working link), log out and every My account tab work.
- Rewards: spin pop-up at 1.5 kg, mystery taste on the Rewards tab with "Use".
- Bag reminder: typing an email at the checkout and leaving saves the bag. The reminder email lists the
  bag; "Finish my order" refills it on another device and opens the checkout with the email filled in; a bad
  link goes to the shop; "No more reminders" works.
- Back end: COD is the only payment; delivery zones are Beirut $5 and outside Beirut $6, Lebanon only. The 34
  products all have a price and photo. Three are set to "Out of stock" by hand: BUBS Wild Berry Pomegranate Oval,
  Sour Pineapple, BUBS Tutti Frutti Diamond. The Fika dashboard, Fika customers, Checkout leavers, Win-back
  emails (Test mode) and Meta pixel pages all open without errors. Background jobs run on time; the only failed
  jobs belong to the switched-off Hostinger plugins (harmless).

**Customer data cleared:** every order (including drafts), every customer account, saved bags, reminder
opt-outs, spin locks, the admin's reward totals, sessions, the customer and analytics tables, and the FIKA10 use
count. The store has 0 orders and 0 customers; only the admin account (cesar.aboulhosn@hotmail.com) remains. The
"New order" email goes to hello@swedishfikalb.com again. The temporary test snippets were deleted.

**Still to do at launch** (also on the Fika dashboard under "Launch health"):
- WP Mail SMTP: enter the hello@swedishfikalb.com mailbox password. Until then, emails go out through the web
  server and can land in spam.
- Settings > Reading: untick "Discourage search engines".
- Meta pixel ID and token.
- Hostinger CDN: turn off "Smart Image Optimization" once the domain points here.
- Privacy policy: still a draft.

## Meta pixel: more events (2026-10-08)

Snippet 43 (fika-meta.php) now sends 8 events, each from the browser and from the server with the same event ID
(Meta counts each once):

| Event | When | Why it matters |
| --- | --- | --- |
| Purchase | an order is placed (value, products, hashed email/phone/name) | the main goal for sales ads |
| InitiateCheckout | the bag's Checkout button | shoppers close to buying |
| AddToCart | + on a candy or Ready Mix bag | interest, retargeting |
| ViewContent | opening Mix your own or Ready Mix (new) | retargeting people who browsed |
| CompleteRegistration | a new account confirms its email (new), ID "reg.<user id>" | sign-ups |
| Contact | a tap on WhatsApp, email or phone links (new) | many Lebanese customers order by WhatsApp |
| Search | a word typed in the sweets search (new) | what people look for |
| PageView | every page | audience size |

- Fix: the server copy of browser events did not know a signed-in customer (the request had no REST nonce, so
  WordPress treated it as signed out). Signed-in pages now add one, so those events carry the hashed email and
  phone, which improves Meta's match rate. Signed-out pages, which can be cached, carry none.
- Tested with a cookie-gated fake pixel (nothing sent to Meta): all 8 events fire with matching IDs, the signed-in
  events include the hashed email, no script errors. The test account, its data and the temp snippet were
  deleted; the store is back to 0 orders and 0 customers.
- At launch: WooCommerce > Meta pixel: pixel ID, token, tick "Send events to Meta", then "Save and send a test
  event". In Events Manager, choose Purchase as the optimisation event for sales campaigns.

## Orders imported from the old store (2026-10-09)

- Source: the owner's Website Builder export (455 orders, #1001 to #1455, 13 Aug to 9 Oct 2026, 389 email
  addresses, $11,812). Every order's lines, discount and delivery add up; every product maps to a current product at
  the same price ("Ready … (500g)" = 1 Ready Mix bag, "(1KG)" = 2 bags).
- Imported as guest orders: real email, name, phone, address, area (BA / OB), date (Beirut time), products,
  delivery fee, discount code, total. Fulfilled = Completed (434), Unfulfilled = Processing (4), Canceled = Cancelled
  (17). Order note "Imported from the old Fika store (Website Builder), order #…". No emails were sent.
- Snippet 71 (fika-imported-orders.php) shows the old number (#1455) instead of the new ID everywhere.
- Checked: the site's 455 orders match the file (totals, statuses, emails, quantities); a second run changes
  nothing; a test @example.com order joined a test account when it confirmed its email and its 2.1 kg counted
  (test data deleted).
- Delivered so far: 364.6 kg. 62 customers are past 1.5 kg, so their rewards light up once they make an account
  and confirm the email.
- Tool for the launch top-up: wordpress/tools/import-old-store/ (the CSV is not committed).
- Note: new orders get WooCommerce numbers (the next is about #946), lower than the old #1455.

## Launch: swedishfikalb.com (2026-10-09)

- The owner connected swedishfikalb.com to the WordPress site in hPanel. Hostinger set the WordPress address to
  https://swedishfikalb.com and replaced the old address everywhere (a database scan found no
  lightgoldenrodyellow-skunk-967361.hostingersite.com left). The temporary address now answers 403.
- Hostinger's CDN bot check ("Checking your browser") answers headless browsers first; real browsers, Googlebot,
  facebookexternalhit and WhatsApp get the page directly. Tests store the passed check (qa2/live-state.json).
- Email: WP Mail SMTP sends through smtp.hostinger.com as hello@swedishfikalb.com. Gmail shows DKIM, SPF and DMARC
  pass, inbox. Hostinger refuses mail to @example.com ("data not accepted"), so test-order customer emails show as
  failed; the same email to hello@ went through.
- Search engines: on (blog_public = 1), sitemap at /wp-sitemap.xml.
- Checked on the live domain: 8 pages x desktop/phone, no broken links or errors; old Builder addresses redirect;
  share tags use the new domain; guest order with both emails (links and images on swedishfikalb.com); account
  sign-up, confirm link, login, wrong password, tabs, spin; signed-in checkout handoff.
- Meta pixel was already on during these tests, so test order #946 ($26) reached Meta as a Purchase. Test orders,
  account and saved bags were deleted; 455 imported orders and the admin account remain.

## New orders continue from #1456 (2026-10-09)

- Snippet 71 (fika-imported-orders.php) now also numbers new orders: the first placed order is #1456, then #1457 …
  The counter is option fika_next_order_number, raised in one database step. Numbers are given when an order is
  placed (before the emails), never to unfinished checkouts, so abandoned bags leave no gaps. Imported orders keep
  #1001–#1455. WP Admin order search finds orders by these numbers.
- Tested with throwaway orders (no emails): a draft had no number, placing it gave #1456, the next #1457, a status
  change kept it, search found it. Test orders deleted and the counter set back to 1456.

## The 10% "before you go" offer can't be gamed (2026-10-09)

Owner's rule: once per person, existing customers included. Snippet 9 (fika-exit-offer.php):
- **Only after an offer:** when the popup reaches the 10% step it asks POST /fika/v1/exit-offer, which sets a
  signed cookie (fika_x10, HttpOnly, 2 days). FIKA10 is refused without it ("This code isn't valid"), so a typed
  or shared code does nothing. Staff can still add it to an order in WP Admin.
- **Once per person:** every order placed with FIKA10 stores hashes of its email and phone (option fika_x10_used).
  Phones are compared as Lebanese national numbers (03 123 456 = +961 3 123 456 = 009613123456). An email or
  phone in the list gets no offer in the popup ("Thanks for telling us" instead), the code is refused when
  applied, and placing the order is blocked with "The 10% offer has already been used with this email or phone
  number. Please remove the code to place your order."
- The existing per-email usage limit of the coupon stays as a second lock. The list starts empty (FIKA10 had not
  been used on the new site).
- Tested on the live site without placing orders (Meta beacons blocked): typed code refused; new shopper offered
  and 10% applied; used email gets no offer; the place-order check blocks a used phone and a used email and lets a
  new person through; an order with FIKA10 records the person. Test entries removed.
- Deploy note: Hostinger's firewall answered 403 to the PUT update of this snippet; POST to the same address worked.

## Review videos: 4 instead of 5 (2026-10-09)

- At the owner's request, review clip 1 (fika-review-1, the @rayakhouri car video) was removed from the home page
  carousel: REVIEW_CLIPS in build-shop-pages.py, then page 41 rebuilt and published (live copy matched git first).
  The carousel, dots and arrows work with 4 clips on phone and desktop.
- The clip and its poster are still in the Media Library (and wordpress/pages/media/reviews/); nothing links to them.
- 20 Oct 2026, at the owner's request: review clip 2 (fika-review-2, the @guide.lb video) removed the same way (live
  page matched git first). 3 clips now (3, 4, 5); carousel, dots and arrows checked on phone and desktop, no errors.
  Clip 2 also stays in the Media Library, unlinked.
- 20 Oct 2026: new clip 6 (owner's "swedish candy taste test" video, 2:34) added second, after the @racil clip 3
  (owner's choice): 33 s made of 0:15.5-0:37 (bag on the lap, opening it, the first sweets) + 0.4 s fade + 2:04.5-2:16.5
  (later tasting, a sweet held up to the camera), both ends on pauses in the talking, audio/video fades. Same format as
  the others (H.264 Main 576x1024, 780 kb/s 2-pass, AAC 96k, faststart, 3.7 MB); poster = the frame where the bag reads
  FIKA (it is held backwards, mirrored, in other moments). Media 1164 (mp4) + 1165 (jpg); copies in
  wordpress/pages/media/reviews/. Previewed in an artifact first. Order now 3, 6, 4, 5; checked on phone and desktop:
  4 clips, 4 dots, arrows, no errors.

## Fika Club: email code at checkout + welcome popup (2026-10-09)

The membership is called **Fika Club** everywhere a customer sees it; the My account tab stays "Rewards" (owner's
choice). Snippet 91, fika-club.php (new):
- **Checkout:** ticking "Create a Fika Club account" shows a box: "Email me my code" sends a 6-digit code
  (subject "Your Fika Club code: 123456"). Typing it creates the account already confirmed (password and details
  typed so far kept), joins every earlier order with that email, signs the customer in and reloads the checkout.
  "Resend code" waits 60 s. "Skip, confirm later by email" keeps the old way (account made with the order, link by
  email). Place order with the box ticked and neither done is held, and the box shakes with a hint.
- **Welcome popup:** lane with delivered + on the way + this bag; "Spin now" for each mystery spin reached (the free
  50 g joins the bag); "Use now" for each kilo reward reached (applied to the bag; it replaces the 10% FIKA10 code,
  and the note shows both amounts); the next stop and how far it is.
- **Limits:** codes last 15 min, 5 tries; 1 email a minute and 5 an hour per address, 15 an hour per visitor; codes
  stored hashed. An email that already has an account is told to log in.
- **Switch:** option fika_club_on (now 1). GET/POST /wp-json/fika/v1/club-status (admins) shows the preview link
  and switches it; with it off only browsers with the preview cookie see it.
- **Wording (snippets 10, 11, 16, pages about-us and privacy draft):** "Fika rewards" card -> "Fika Club";
  sign-up nudges "Join Fika Club"; checkout box "Create a Fika Club account"; My account button "Join Fika Club";
  welcome messages "Welcome to Fika Club"; reward clash messages. The confirmation email is now "Confirm your email
  to join Fika Club" and shows "N earlier orders (x kg) are waiting to join your lane" when there are any.
- **Tested** on the live site, Chrome engine, with no orders placed (checkout submissions blocked in the test
  browser, Meta beacons blocked): desktop 1920x1080, laptop 1366x768, iPad, iPhone 14, Pixel 7 and a 360 px
  Android. Cases: 1.6 kg returning customer (spin, free taste in the bag), 3.4 kg regular (spin + 100 g, with and
  without FIKA10 on the bag), new customer, Place order held, wrong code, resend wait, skip, existing account,
  My account sign-up email; then again as a normal visitor after switching on, plus a crawl of every page.
  Test accounts, orders, reward codes, spins and the temp helper were deleted.
- Fixed during testing: a stale "type your email" hint; "Use now" failed while FIKA10 was on the bag (now swapped);
  focus outline on the popup.
- Not tested: real Safari (only Chrome's engine is available here); the owner's iPhone check covers it.

## Emails in junk after launch: SPF and DMARC were missing (2026-10-09)

- Symptom: emails from hello@swedishfikalb.com landing in junk after the domain switch.
- Cause: hPanel > Emails > Domain settings showed the SPF and DMARC records missing (MX and DKIM fine). They were
  most likely removed when the domain was connected to the WordPress site. Without them strict providers (iCloud,
  Outlook) cannot verify the sender.
- Fix (owner, "Connect automatically", no other records touched): TXT @ `v=spf1 include:_spf.mail.hostinger.com ~all`
  and TXT _dmarc `v=DMARC1; p=none`. All four rows green.
- Verified on a WP Mail SMTP test to Gmail: SPF pass, DKIM pass (hostingermail-a), DMARC pass, Inbox; multipart
  (text + HTML); Message-ID on swedishfikalb.com.
- Later (a few weeks, once nothing else sends as @swedishfikalb.com): consider DMARC p=quarantine.

## RT Deliveries parcel tracking (snippet 94, `snippets/fika-rtd.php`) — 2026-10-09
- Parcels are still created by hand in RT's dashboard. RT's webhook posts each status change to
  `/wp-json/fika/v1/rtd-webhook` with header `RTD-Signature: <secret>` (secret on WooCommerce > RT Deliveries).
- Owner's mapping: Delivered -> Completed; Delivered_failed / Return_assign_to_merchant / Cancel -> Failed; every other RT
  status, and any status not in the list (e.g. "checking with merchant"), -> Processing. Cancelled /
  refunded orders are left alone. Private order note, RT history box on the order, "RT" column in Orders (RT's exact
  step + tracking ID). No order emails (customer or shop) on RT's changes, e.g. Failed -> Processing re-sends nothing.
- Linking: tracking ID saved on the order (box on the order page), or the Fika order number in RT's "Invoice no".
  Unmatched parcels wait under "Parcels to link" with same-amount suggestions; "Not a website order" dismisses one.
- Status: installed and active; end-to-end test with test orders pending the owner's OK. Webhook not yet given to RT.
- Past orders from RT's export (Parcels_Export 9 Oct 2026, 419 parcels; not committed, has customer data): matched
  370 to orders by phone + amount + date (order date vs RT parcel sequence). Applied with the WooCommerce REST API
  (no emails: completed -> failed / processing sends none): 18 -> Failed (#1082 1095 1154 1163 1175 1189 1208 1209 1215
  1226 1273 1279 1320 1343 1398 1410 1413 1450), 3 -> Processing (#1423 1446 1449), each with a private note; tracking
  ID + RT status saved on all 370. #1318 (Suzy) kept Completed, linked to the re-sent delivered parcel (owner's call).
  After: 9 processing, 413 completed, 18 failed, 17 cancelled. 49 RT parcels had no website order (mostly $0 cash).
- Orders list (same snippet): the status links are big coloured filter buttons, ordered All, Processing, Failed,
  Completed, then the rest (filter `views_woocommerce_page_wc-orders` + CSS on that screen).
- Owner's test orders deleted (permanently, owner's request, 2026-10-09): #1001, #1435 (Sarah Aboul Hosn), #1004, #1338,
  #1443 (Alan Aboulhosn), all cancelled imports. build-orders.py skips these numbers so a re-import won't bring them
  back. Other Aboulhosn orders (Zeina, Cezar, kingbakraboulhosn@) are real and were kept. 452 orders remain.

## Price raise — 2026-10-09
- All 31 pick-and-mix candies $2.50 -> **$2.75 per 100 g**; the 3 Ready Mix bags $12.50 -> **$13.75 per 500 g**
  (WooCommerce regular price, products batch). Past orders keep the prices they were placed at (line items store them).
- Page texts: Mix your own (page 40) and About (page 257) say $2.75 / $13.75; the mix pattern (block 241) fallbacks
  2.75 / 13.75 (prices otherwise come from the Store API). build-shop-pages.py and the backup source updated to match.
- Fika Club rewards follow the grams: 100 g = $2.75, 200 g = $5.50, 400 g = $11, 1 kg = $27.50 (fika-loyalty.php,
  FIKA_FREE_KG_VALUE fallback 27.5). Codes claimed before keep their old value ($2.50 / $5 / $10 / $25).
- Checked live on desktop and iPhone 14: every page shows only $2.75 / $13.75; a bag of 100 g + one Ready Mix = $16.50
  at checkout (no order placed).
- Categories (2026-10-09, owner): BUBS Forest Berry Ovals (121) moved Sour -> Sweet; BUBS Bubblegum Skull (120) was
  already Sour. Filters now Sweet 14 / Sour 14 (checked on the live Mix your own page).
- Changed again the same day (owner): **$2.80 per 100 g**, **$14.00 per 500 g** Ready Mix bag. Rewards follow the grams:
  100 g = $2.80, 200 g = $5.60, 400 g = $11.20, 1 kg = $28 (FIKA_FREE_KG_VALUE fallback 28). Page texts and the mix
  pattern fallbacks say 2.80 / 14.
- Fix (full test, 2026-10-09): old Builder product links (/product-page/peaches, /product-page/sour-mix) were read by
  WordPress as the photo's page and redirected to the .jpg. fika-store-pages.php (38) now sends /product-page/* and
  /category/* to Mix your own (names ending in "mix": Ready Mix), also when WordPress reads them as a photo's page.

## Full test — 2026-10-09 (after the $2.80 / $14 prices)
- Crawl, desktop + phone: 8 pages, every link answers, no failed requests, no script errors. 34 product addresses
  open the shop (product pages closed for now, by design); /shop, /store, /about, /contact, /category/*,
  /product-page/* redirect to our pages (product-page fix above); missing pages show the Fika 404; sitemap + robots OK.
- Customer journey (qa2/journey.js, scratchpad) on desktop 1920, laptop 1366, iPad, iPhone 14, 360 px phone: all checks
  pass (34-35 each): menu, filters (Sweet 14 / Sour 14 / Gelatin-free 24 / All 28), search, + / - / remove, Ready Mix,
  bag drawer totals, checkout lines, Fika Club box, delivery $5 inside / $6 outside Beirut (about 3.6 s for
  WooCommerce to update the fee; server answers in 0.3 s), form valid up to Place order (request stopped, no order),
  before-you-go popup, My account (log in, Join Fika Club, wrong password message, lost password), contact links.
- Meta: browser events PageView, ViewContent, Search, AddToCart, InitiateCheckout, Contact all fire with their server
  copy (held back in the test). Conversions API Purchase accepted by Meta (HTTP 200) for #1456 and #1457; browser and
  server Purchase share the event ID purchase.<order id>.
- Back end: REST lists 0.6-2.5 s; WP 7.1.3, WC 11.1.2, PHP 8.3, all plugins current; HPOS on; loopback, HTTPS,
  page cache good. Site Health "background updates disabled" = Hostinger manages updates (not changed).
- Clean-up: 12 saved test bags (@example.com) removed with their reminders; no test orders, customers or temp snippets.

## Fika Club invite popup (snippet 96, `snippets/fika-club-invite.php`) — 2026-10-09, LIVE
- Guests on a browser never signed in: visit 1, then every 3rd visit (1, 4, 7, ...; new visit after 30 min away;
  per browser, localStorage fika_visits). First page of the visit only (home, Mix your own, Ready Mix, About);
  never checkout / My account; counted but not shown when the page opens another popup (?fika_order, ?fika_club,
  ?fika_bag, ?bag=open).
- Any signed-in page view marks the browser (localStorage fika_member) for good; "Already a member? Log in" marks it
  too and opens /my-account/#login. "Join Fika Club, it's free" opens /my-account/#register.
- Content (simple, owner's wording): a bobbing skull, wiggling fish and sour strawberry; "Join the Fika Club";
  "Amazing deals and free sweets as you order. Joining is free, the sweets are sweeter!"; button "Join the Fika Club";
  "Already a member? Log in".
- The sign-up cloud (fika-signup-nudge.php) stays away on a visit where the popup showed (sessionStorage flag).
- LIVE since 2026-10-09 (FIKA_INVITE_LIVE true; checked as a new guest on desktop + iPhone). ?fika_invite=preview
  always shows it; ?fika_invite=test runs the real rules.
  Rules tested on the live site (iPhone 14): all pass. Going live = set FIKA_INVITE_LIVE to true (snippet update
  also clears the page cache).

## Final test before the ad boost — 2026-10-09
- Journey on 7 devices incl. Instagram (iOS) and Facebook (Android) in-app browsers, arriving from an ad link
  (?fbclid=...&utm_...): all checks pass (36-37 each), welcome popup shows for a first-time guest and closes.
  Crawl + 34 product addresses clean; no failed requests, no script errors.
- Meta: pixel 2660128037781548 initialises on every page; every browser event (PageView, ViewContent, AddToCart,
  Search, InitiateCheckout, Contact) has its server copy with the same event ID (one fikaTrack call does both), values
  and content_ids correct ($2.80 candy, $14 bag). This sandbox cannot reach connect.facebook.net, so the pixel script's
  own hits to Meta are checked in Events Manager by the owner. Conversions API: last real order's note "Meta:
  purchase sent to the Conversions API" (HTTP 200).
- Speed on a 4G iPhone: home LCP 1.5 s first view / 1.1 s cached; Mix your own and Ready Mix 0.8 s; checkout 1.4 s.
- Clean-up: 14 saved test bags removed; no test orders.
- Meta diagnostics (owner's Events Manager, 9 Oct): all new-site events "Multiple" (browser + server); EMQ Purchase /
  CompleteRegistration 9.3, InitiateCheckout 6.7, PageView / ViewContent / AddToCart 6.5, Contact / Search 4.4.
  Pixel-only events (ViewCart, AddShippingInfo, SelectItem, RemoveFromCart, AddPaymentInfo) are the old Builder
  store's, stopped ~20 h before (domain switch). Warning "server sending 366 fewer AddToCart than pixel (7 days)":
  mostly the Builder's pixel-only AddToCart; fix on our side: fika-meta.php (43) now sends the server copy after the
  visitor's answer has gone out (litespeed_finish_request) and waits for Meta (10 s) instead of a 1 s fire-and-forget,
  and counts accepted / refused per day (settings screen, GET /fika/v1/meta-stats).
- Dedup keys from the first event (9 Oct, before the ad boost): the pixel code sets _fbp (fb.1.<ms>.<random>) and, on
  ?fbclid=, _fbc (fb.1.<ms>.<fbclid>) itself, plus fika_vid (random 64-hex per browser, 90 days) sent as external_id
  by the pixel (fbq init) and by the server (request user, so also the guest Purchase). Checked: the landing PageView
  beacon already carries _fbp, _fbc and fika_vid; journey on desktop, iPhone, Instagram, Facebook still all pass.
- Meta self-test (fika-meta.php): POST /wp-json/fika/v1/meta-selftest (managers) sends one PageView dated 10 days ago
  the background way; Meta refuses it (400, subcode 2804003 "Event timestamp too old"), so nothing reaches the reports,
  and GET shows Meta's answer. 9 Oct: answer as expected = route, token and background sending all work. A beacon
  request answers in ~1.0 s (0.76 s for an ignored event).
- Fix (owner saw a flash of the old bag in a bottom corner on opening the home page): the old floating "Bag 0 kg"
  button (#mxFab, in the mix pattern 241) was only hidden by a rule near the end of the page (fika-home-cartoons.php),
  so it showed for a split second while the page loaded. The mix pattern itself now hides it from the first paint.
  Checked on phone + desktop with a slow connection: the button is display:none from the moment it exists.
- Load-time hunt (9 Oct, all pages, phone + desktop, slow connection):
  - Fixed: the shop grids first drew the script's placeholder list ("Sample candy 5-8", "Ready Sweet & Sour Mix" with no
    photos) for ~0.5 s before the real products. The mix pattern (241) now keeps the grids as same-size shimmer boxes
    (class mx-loading, names / prices / buttons hidden) until the products arrive; the placeholder list shows only if
    the Store API fails (or after 10 s).
  - Improved: fika-speed.php (23) requests the Bebas Neue / Fanwood Text / Outfit Google Fonts CSS from the <head>
    (non-blocking, same URLs as the @imports in the patterns), so the plain-font moment is shorter.
  - Checked, fine: no layout shift on phone pages; small shifts on desktop Mix your own (0.03, the filter chips) and
    the 404 page (0.05), both well under Google's 0.1 "good" limit. No script errors. Journey regression: all pass.
- Guest checkout tested end to end (owner's OK, 9 Oct): temp snippet 97 (for @example.com orders only: number label
  TEST, no Conversions API Purchase, no emails); iPhone, Meta browser requests blocked. Order placed as a guest:
  BUBS Bubblegum Skull $2.80 + Sweet Mix $14.00 + Beirut delivery $5.00 = $21.80, COD; address, phone, note saved;
  "Order confirmed" popup showed #TEST; bag emptied. Then the order, 9 saved test bags and snippet 97 were deleted.
  A real guest order #1458 ($24.60) came in meanwhile: numbered normally, Purchase sent to Meta, not affected.
- Meta Diagnostics "server sending 24 fewer Purchase events than pixel (7 days)": the old Builder store's browser-only
  purchases (about 2-8 Oct). Not backfilled (their event IDs are unknown, Meta would count them twice); clears by ~16 Oct.
- Invite popup timing changed (owner, option 2, 9 Oct): no longer on landing; on a due visit (1, 4, 7, ...) it opens
  ~1.1 s after the first thing goes into the bag (after the candy flies to the bag icon), once per visit; waits while
  the bag drawer (.mx-drawer.on) or another popup is open. Wording unchanged. Counts per day (shown / join / login /
  close) at GET /wp-json/fika/v1/invite-stats (managers; POSTed by the popup with sendBeacon, not in preview/test).
  Tested: rules on iPhone + desktop all pass; journey on 7 devices (incl. Instagram / Facebook in-app) all pass.
- Sign-up cloud timing (owner, 9 Oct): the cloud (fika-signup-nudge.php, 16) now shows 5 s after the page opens:
  "Join Fika Club" (version A) when the bag is empty, "Make every gram count" (version B) when it has sweets. Adding
  the first sweet no longer brings it up (that is when the Fika Club popup opens). The popup now sets
  fika_invite_shown only when it opens (was: at page start on due visits), closes a cloud that is still showing and
  stops the account icon waving. Unchanged: once per visit (per tab), × hides it 7 days, fades after 10 s / 14 s.
  Tested on iPhone + desktop (A at 5 s, B with sweets, popup on first sweet closes the cloud, no cloud after the popup,
  × keeps it away); journey on 4 devices all pass.
- Fika Club invite popup switched OFF entirely (owner, 9 Oct: too many Fika Club asks in one visit). FIKA_INVITE_LIVE
  false in fika-club-invite.php (96); the snippet stays active only to mark signed-in browsers (fika_member). What a
  guest sees now: the speech-bubble cloud 5 s after landing (A / B) and the "Make this order count" card at checkout.
- Sign-up cloud timing put back as it was before 9 Oct (owner): first sweet in the bag -> version B ~0.9 s later, or
  after 20 s of browsing -> version A (B if the bag has sweets). fika-signup-nudge.php restored from 351b77f^.
  Checked live on iPhone + desktop: no cloud at 6 s or 15 s, B after the first sweet, A at 22 s, no popup.

## Fika Club rewards in the bag (snippet 98, `snippets/fika-bag-rewards.php`) — 2026-10-10
- Signed-in customers. In "Your bag" (top of the list): the won mystery taste ("Mystery taste: <candy>, 50 g, Free")
  while orders + bag reach its spin stop (1.5 / 12.5 kg) and the bag has sweets; checkpoint rewards chosen with
  "Use now" ("100 g on us  -$2.80", a "Fika Club rewards" row, total lowered) while orders + bag reach the checkpoint;
  below it the reward leaves the bag (back when the bag goes back up); rewards not chosen show "Use now" (reminder).
- Popup when orders + bag reach a checkpoint (3 / 6 / 10 / 15 kg, each lap) for the first time: "You reached 3 kg!
  100 g on us", "Use now" / "Use later" (once per reward per browser; waits for the mystery wheel and other popups).
- Checkout: chosen rewards are applied by themselves through fika-loyalty.php's rewards box (claimed there if needed,
  only while the order reaches them); applied rows greyed with "Unapply"; unapplying takes it out of the bag choice.
  The free taste line at checkout is fika-taste.php's (same rule). Choice kept in localStorage fika_rw_use_<user>.
- GET /wp-json/fika/v1/bag-rewards (signed in) refreshes the bag data when the bag opens. Read only (no withdrawing).
- Fixes found while testing (fika-loyalty.php, 11): checkout "Remove" sent the code in capitals (cart keeps small
  letters); fika_swim_sync no longer withdraws a reward that is applied on the customer's own cart right now (going
  from checkout back to the shop deleted the reward just claimed there, and the order could not be placed with it).
- Tested on the live site with a temporary @example.com account (no orders; a 3 kg bag): iPhone 19/19 (wheel at
  1.5 kg, taste line, popup at 3 kg, Use now, discount, below / above both stops, checkout auto-apply greyed +
  Unapply, shop and back, bag after Unapply), desktop Use later path 4/4. Test account, its codes and bags deleted.
- Fix (owner, 10 Oct): the bag flickered on every + / - when the mystery-taste line was in it. The shop redraws the
  bag list on each change; our lines were put back a tick later (setTimeout), after the browser had painted the list
  without them, so it jumped. Now they go back in the MutationObserver callback (before the paint) and the same
  elements are reused (the candy photo is not reloaded). Measured over ~300 frames per device while pressing + / -:
  0 frames without the line, the candy lines never moved. Rewards test still 19/19. Test account deleted.

### Tapping the gifts on the progress lane (11 Oct 2026)
- fika-bag-rewards.php (snippet 98): a bouncing gift (reached with this bag) or a green gift (claimed) on the progress
  lane opens the reward popup: "Use now" / "Use later". If the reward is already in the bag, the popup says
  "✓ In your bag" with "Keep it" / "Take it out". Glowing gifts (reached by the orders) keep their confetti claim;
  the spin wheels are unchanged. Works on the home page and on the account page (Rewards tab); on the account page
  the popup never opens by itself, only on a tap.
- Tested live on phone and desktop with a hidden test customer and a REST test order (emails muted, no Meta, no
  order number) under a temporary safety snippet; all deleted after the test.
- Removed the reward code box under the progress lane (code + "Copy code"), on the home page and on claim
  (fika-loyalty.php, snippet 11). The tap popup and the milestone popup are unchanged; rewards are used through the
  bag and the checkout rewards box.
- Rule: when the fish swims back behind a gift, the gift is withdrawn (fika-loyalty.php, 11 Oct 2026). A reward claimed
  at checkout for a bag stayed green after the bag got smaller, because the code was still applied in the customer's
  saved cart. Now: a code on the cart is kept only while orders + that cart still reach it (else it is taken off the
  cart and deleted), and on the lane a bag-claimed gift turns grey as soon as the bag no longer reaches it (green
  again if the bag goes back up). Spin wheels keep their colour; the mystery taste still joins an order only when it
  reaches the stop. Tested live with a test account (no order placed), deleted after.
- Mystery taste line at checkout (fika-taste.php, snippet 46): the crossed-out price and "Save" showed $2.80 (a full
  100 g unit). The line's "was" price is now half the candy's 100 g price (50 g: $1.40); it still costs $0.
- The free taste line no longer shows the candy's "Price per 100 g." label (that line only).

### Gift states on the progress lane (11 Oct 2026) — live
- Deployed to snippets 11 and 98 after the owner's "go live". Tested live with hidden REST test orders (safety snippet:
  no emails, no Meta, label TEST): pink -> claim (confetti, popup) -> order with the code: green "On its way" ->
  failed: pink again, code freed -> processing again: green -> completed: faded "Enjoyed" -> failed + the other
  order cancelled: fish behind the gift, gift grey, code deleted -> crossed again: pink. All test data deleted.
- Pink striped: unlocked, not in a placed order (popup state 1 "Use now / Use later", state 2 "In your bag" with
  Keep it / Take it out, swapping in place). First tap on a new gift: confetti, then the popup.
- Green: the code is in an order on its way ("On its way!" popup). Faded, label crossed out: in a delivered order
  ("Enjoyed!" popup).
- Failed / undelivered / refunded orders give their reward codes back (WooCommerce does it itself for cancelled);
  then the usual withdraw runs if the fish is back behind the gift.
- Reward code <-> order, verified 11 Oct 2026 on every status (test order walked through processing, on hold, completed,
  failed, cancelled, refunded, undelivered and back; each time: use count, "used by", the gift, and whether checkout
  accepts the code). WooCommerce's own count drifted (cancelled -> placed -> refunded kept the code taken; a stale
  "used by" line kept checkout refusing a freed code). Now, after every status change, fika-loyalty.php sets both
  from the orders themselves with WooCommerce's own add/remove: taken (1) while an order on its way or delivered
  carries the code, free (0) otherwise.
- Deleted the owner's test account alan.aboul@gmail.com (user 71): account, its cancelled order #1388, reward codes
  SWIM3-Y9XIJL and SWIM6-U8KW34; checked the shop's own records (saved bags, reminder stop list, 10% list, win-back
  log, spin locks): nothing else held it.
- Full customer sweep, 11 Oct 2026 (no orders placed, nothing sent to Meta):
  - Guest journey on 7 screens (desktop, laptop, iPad, iPhone, Instagram and Facebook in-app, small phone): browse,
    filters, search, bag, ready mix, checkout form and delivery fees, exit popup, account pages, contact links, pixel
    events. All pass (iPad needed a second run after one slow page load; one stray font request on iPhone did not
    repeat).
  - Member journey on phone and desktop (27 checks each): spin, taste line, 3 kg popup with Use now <-> In your bag,
    bag discount up/down the checkpoint, checkout auto-apply / Unapply, Rewards / Orders / My details tabs, lane taps.
  - Every page crawled on phone and desktop (home, mix, ready mix, about, account, lost password, cart/checkout
    redirects, old /product-page/ and /category/ links, 404): no JS errors, no broken images, no sideways scroll;
    pages checked by eye screen by screen.
- Ready Mix page on wide screens (11 Oct 2026, reported from a customer's desktop): the 3 bags got narrower as the
  screen got wider (272 px at 1440, 167 px at 1920, 7 px at 2560) because the grid's side spacing grows with the
  screen while the grid was capped at 1180 px. From 761 px up the bags now keep the 892 px row they have at 1440 px,
  centred (271 px each at any width). Page 38 + build-shop-pages.py. Home, Mix your own, About and My account checked
  at 1920 and 2560: fine.
- Tablet and laptop sweep, 11 Oct 2026: 30 screen sizes (iPad Mini, iPad 7, iPad Air, iPad Pro 11 and 13, Galaxy Tab
  S4 and S9, Surface Pro, each portrait and landscape; Nexus 7, Kindle Fire; laptops/desktops 1024x768 to 2560x1440),
  each with 15 pages/states signed out and in (home, mix, ready mix, about, account, lost password, 404, phone menu,
  Rewards / Orders / My details, bag drawer, checkout with a bag). Automatic checks (sideways scroll, squeezed or
  overlapping cards, header items touching, cut-off text, broken images, JS errors), verified first against planted
  faults, plus screen-by-screen review: no problems found. Only a stray failed font request now and then: Chrome's
  preloader misreading an inline @import url('...') while the page streams in; the fonts themselves load normally.

### Packaging column in Orders (12 Oct 2026)
- fika-packing.php (snippet 103): WooCommerce > Orders gets a "Packaging" column after Status and RT. Each order shows
  Unfulfilled or Fulfilled; one click switches it (saved at once, the row does not open). Order meta _fika_packed
  (yes / no), saved on its own: no status change, no emails, no order note, rewards and RT untouched. Orders never
  marked show Fulfilled once Completed, otherwise Unfulfilled. Saved via POST /fika/v1/packed (shop managers only).
- Tested with a hidden REST test order (deleted): switching changed only that value; refused without login.
- 12 Oct 2026: marked all 478 orders numbered up to #1484 as packaged (Fulfilled) at the owner's request, through
  /fika/v1/packed; no order status changed. #1485 onwards left as they were.

### Fika customers: Rewards column (12 Oct 2026)
- fika-customers-admin.php (snippet 17): "Kg delivered" replaced by "Rewards": the kg that count in Fika Club
  (delivered + on the way, rewards-paid grams excluded, same as the lane), the lap and the next gift not yet claimed;
  chips for codes still available (personal, email-restricted codes not used up or expired: claimed rewards, win-back
  ...), gifts reached but not claimed, codes on an order on its way, codes used on a delivered order (with the order
  number), and the mystery taste. Guests: kg and codes, marked "guest, not in Fika Club". Sortable by kg. CSV export
  keeps Kg delivered and adds kg in Fika Club, next gift, codes available, codes used.
- 12 Oct 2026: "Delivery" column (same snippet 103), after Packaging: clicking Fulfilled saves the time
  (_fika_packed_at, Beirut time; switching back to Unfulfilled clears it). Due by the end of: the next day if packed
  before 17:00, the day after that from 17:00 (Inside Beirut, state BA); one more day Outside Beirut. Completed by the
  due day = "On time", after = "Late" (from the order's completed time); not delivered yet = "On time, due ..." until
  the due day ends, then "Late". Dash for: not packed, packed before times were saved (the 478 marked up to #1484),
  failed / cancelled / refunded / undelivered. Calendar days (Sundays count). Tested on 10 hidden test orders (deleted).
- 12 Oct 2026: #1485-#1494 were marked Fulfilled (15:54-17:03) before the Delivery column went live; their packing time was filled in from the order's last-changed time (the click). #1484 was switched back to Unfulfilled at 15:30 (left as is).
- 12 Oct 2026: Sundays no longer count as delivery days (due date skips them). #1493 and #1494 (clicked 17:01 / 17:03) counted as packed at 16:59 at the owner's request, like the rest of #1485-#1494.
- 13 Oct 2026: RT export of 13 Oct (470 parcels) applied through fika_rtd_apply (the webhook's code: RT column, order
  note, no emails), via a temporary admin-only endpoint (deleted). Matching: tracking already on the order (370), then
  phone + cash (45), then by name for 6 where RT's amount or phone differed (#1487; #1482 + #1483 sent as one parcel
  RTL86028171; #1497, #1503, #1505 with RT cash $1.80-$2.80 below the order). 38 status changes (37 processing ->
  completed, #1450 failed -> completed after a later delivery), 22 parcels linked / RT step updated. Delivered orders
  got the export time (13 Oct 23:51) as completed time, for the Delivery column. Cancelled #1148, #1151, #1284 left
  alone; old pre-WordPress parcels ignored. No parcel yet: #1484, #1509, #1510.

### Fizzy Pop hidden (14 Oct 2026, out of stock)
- Product 92 kept as published in WP Admin, set to catalog visibility "Hidden" and "Out of stock": gone from the
  shop grids, search, home carousel, bag suggestions and the mystery-taste wheel; its old product URL already redirects
  to Mix your own. To bring it back: Stock status "In stock" and Catalog visibility "Shop and search results".
- mix.html (pattern 241): a sweet no longer in the shop list now leaves a saved bag when the page loads (before, the
  bag's remembered name kept it, and checkout would have refused it).
- 14 Oct 2026: BUBS Forest Berry Ovals (product 121) got a new photo (media 1100, BUBS-Forest-Berry-Ovals-mini.webp,
  copy in wordpress/images/): 16 ovals cut from the old photo, laid out like the Fruity Lemon Mix Skulls photo (same
  1024 px picture, same pile size and coverage), so they read as minis. The old photo (media 484) stays in the Media
  Library. Both renamed with "(Mini)": "BUBS Forest Berry Ovals (Mini)", "BUBS Fruity Lemon Mix Skulls (Mini)"; slugs
  unchanged (the cartoons find products by slug). Checked: shop on phone and desktop, search "mini", bag, checkout.

## Checkout Fika Club invite redesign (2026-10-14)
- Snippet 16 (`fika-signup-nudge.php`): the guest invite on checkout is now "Turn this order into free sweets" with a
  "Free to join" ribbon, a line built from the bag weight (what this order counts toward and the next reward), a mini
  reward lane (Spin 1.5 kg, 100 g at 3 kg, 200 g at 6 kg, 400 g at 10 kg, 1 kg at 15 kg) with the fish at the bag
  weight, perk chips, and a pulsing "Join free & earn" button. Tapping it ticks "create account" as before and turns the
  card green ("You're joining Fika Club"). Sign-up logic unchanged. Live and checked on phone and desktop the same day.

## Rosa Hourani: guest orders joined to her account (2026-10-14)
- Owner confirmed rosahourani09@gmail.com (account, user 133) and r.hourani@ahliahschool.edu.lb (guest) are the same
  person. Guest orders #1067, #1191, #1270, #1407 (all completed) set to customer 133 the way fika_link_past_orders
  does (order note added, totals and lane refreshed). Billing emails on those orders unchanged. Rewards reach
  0.5 kg -> 2.8 kg. Done with a one-off REST endpoint (snippet 112), deleted afterwards.
- Later the same day Rosa placed #1521 (processing) with the 3 kg gift (SWIM3, $2.80), reached only because #1519
  was still processing; #1519 was then cancelled (she is at 2.8 kg). Owner chose to honour the gift: #1521 left as is.
- The "no stacking pending orders" rule (owner's final version: rewards unlock from delivered orders, + the bag
  only while no order is on its way; the fish still swims with orders on their way, and a gift it passes thanks to
  one shows a waiting sign "unlocks once order #N is delivered"; cancelled / failed orders: the fish swims back and
  a code on that order is free again): LIVE since 2026-10-15 (snippets 11, 98, 91, 46; each matched git before).
  Before: no unused gift codes were affected; 14 won mystery tastes not yet in an order (3 of those customers have an
  order on its way: their taste joins once it is delivered). Checked after: Rosa (2.3 kg delivered, #1521 on its way)
  unlocks nothing from a bag, her honoured SWIM3 on #1521 untouched; a test account with no orders and a 4.4 kg bag
  gets the gift for that bag at checkout, phone and desktop, no errors. No orders placed; test account, test saved
  bags and temp check snippets deleted.
- End-to-end check, 2026-10-15 (owner asked): hidden test account + two TEST orders (product 123, no stock
  tracking; safety snippet: no emails, no Meta, no order number), 2.5 kg delivered + 0.6 kg on its way. Passed:
  3 kg gift waits on the lane (tap: "unlocks once order #… is delivered"); the server refuses to unlock it; a 0.7 kg
  bag does not unlock it (drawer + checkout say it waits, no Use button); a saved code typed at checkout is refused
  with the same message; after the order is marked Completed the code applies; reopening the order withdraws the
  unused code again; cancelling the order on its way puts the fish back at 2.5 kg and the gift grey. No page errors on
  phone or desktop. (The lane heading shows an unspun mystery spin before the waiting gift: by design.) Test orders,
  customer, coupon, saved bag and temp snippets deleted afterwards.

## RT API key field (snippet 94) — 2026-10-16, LIVE
- WooCommerce > RT Deliveries has a new "RT API key" box: paste the key RT gave, Save (or Remove). Stored in the
  option fika_rtd_api_key (not autoloaded), shown only as its last 4 characters; saving/removing is logged.
  fika_rtd_api_key() returns it. Nothing uses it yet: parcel creation / status checks wait for RT's API docs.
- Live matched git before; after deploy the site and the webhook (401 without the secret, as before) respond normally.

## RT (Road Train Deliveries) API — docs received 2026-10-16 (wordpress/docs/RTD-Deliveries-API-v1.pdf)
- Base URL (live): https://app.rtdeliveries.net/api/v10 (reachable from the site: 401 without login, as expected).
  The sandbox URL in the docs (sendbox.rtdeliveries.net/api/v10) answers 404, so there is no working test server.
- Every request: headers apiKey (the key, saved in WooCommerce > RT Deliveries), Content-Type/Accept application/json,
  plus "Authentication: Bearer <auth_token>" from POST /merchant/signin {email, password} (the RT dashboard login).
- GET /get-shops (pickup points, shop_id), GET /get-package (packaging ids), POST /parcel-create, POST /parcel-bulk-create.
- parcel-create: shop_id, delivery_type_id (3 inside city / 4 outside), delivery_priority_id (1 express / 2 normal),
  special_request_id (4 none), invoice_no (our order number), customer_name, customer_phone ("11 digits"),
  customer_address (<=191 chars), cash_collection, currency_type (1 USD), optional packaging_id, note.
  Response gives tracking_id (links with the existing webhook). No cancel or status endpoint in the docs.
- Not built yet: needs the RT dashboard email + password (to sign in), the shop/pickup id, and RT's answer on phone
  format and on testing without a real parcel.
- 2026-10-16 (snippet 94, LIVE): the box is now "RT API login": API key + RT dashboard email/phone + password
  (options fika_rtd_api_key / _email / _password, not autoloaded; key and password never shown). "Test connection"
  signs in (POST /merchant/signin) and lists the RT shops with their IDs (GET /get-shops): read-only. Owner's choice:
  parcels will always be sent with Normal priority. Sending orders is not built yet.
- 2026-10-16: connection test OK (signed in with the owner's RT login). RT shop: "Swedish Mums", Aley, id 2392
  (default, active) = the pickup point for parcels. RT packaging list: "Box" id 3 (price 0). The saved API key is
  19 characters ending in 3456, the same shape as the key printed in RT's docs (RTD-merchant-123456), and RT's live
  server accepted it: confirm with RT it is the key meant for us. Test result now kept in an option (the site's cache
  dropped the transient).
- 2026-10-16 (snippet 94, LIVE but switched OFF): sending new orders to RT built (see the snippet header). Dry run on
  orders #1528-1530 built the right data (type 3/4, invoice = order number, COD total, note). Validation probe (a
  request missing customer_name, so RT refused it and created nothing): RT accepts 10-digit numbers (961 + 3xxxxxx).
  ONE test parcel created with the owner's OK: tracking RTL39012367, RT order 0303529, invoice FIKA-TEST-1, status
  Pending, delivery fee $5, cash 0, pickup date 16 Oct. The owner deletes it in RT's dashboard. Its webhook updates
  will wait under "Parcels to link" (no order matches): "Not a website order" clears them.
- 2026-10-16: owner's changes: no invoice number and no note sent to RT (name kept: RT requires it). Bags: 1 up to
  1.5 kg, +1 per started 1.5 kg; RT's API has no documented bags field, so 2+ bag orders get the private note
  "N bags: set the bag count in RT" (ask RT for the field name). Owner asked to send today's orders: #1526-#1530 sent
  (all 1 bag): RTL64834184 (#1526), RTL33197061 (#1527), RTL48810151 (#1528), RTL60502637 (#1529),
  RTL18444083 (#1530); tracking IDs saved on the orders. The automatic switch is still OFF.

### 2026-10-16 – RT parcels not made on the website (snippet 94)
- Parcels that reach the RT webhook but match no website order (made by hand in RT's dashboard) are now
  filed quietly under "RT parcels not from the website" on the RT Deliveries page: no menu badge, no to-do.
  The list is collapsed ("Show the list"); each row has a "Remove from list" button.
- Matching by invoice number (old manual parcels) still works as before.
- Orders the owner delivers personally are handled by hand in RT (change status or delete the parcel there).

### 2026-10-16 – Win-back emails: which name they greet (snippet 35)
- Real emails now greet the person who ordered (billing first name); the delivery name is used only when the billing
  name is empty. Checked: 455 people in the audience, 1 without a name ("Hi there,"); no order so far has a different
  billing and delivery first name, so nobody's greeting changes today.
- "Send a preview" now greets the person it is sent to (their account's first name, else their latest order's
  billing first name) and falls back to the sample name "Sara" when neither exists. alan.aboulhosn@gmail.com has no
  account or order, and the admin account has no first name, so previews to those still say "Sara".
- Later the same day: "Send a preview" has a "Name to greet (optional)" box. Order of choice: the name typed in,
  the recipient's account first name, their latest order's billing first name, the start of the address
  (alan.aboulhosn@gmail.com -> "Alan"), and only then "Sara".

### 2026-10-16 – Win-back email 1 in three parts (snippet 35)
- Designed with the owner on an interactive preview page (claude.ai artifact "Fika Reminder Email").
- Part 1 (unchanged): "We miss you!", the candy grid, the news box, "Pick your mix".
- Candy grid: new setting "Candies (email 1)" in WP Admin > WooCommerce > Win-back emails: four product pickers,
  set to BUBS Fruity Lemon Mix Skulls (Mini) 118, BUBS Forest Berry Ovals (Mini) 121, BUBS Bubblegum Skull 120,
  BUBS Raspberry Salty Licorice Skulls 107. Sold-out picks are skipped; all on "(automatic)" = the old rule.
- Part 2 "What fika means": illustration (media 1120, fika-email-friends.jpg), "Made for sharing", three boxes
  (Family time, Real quality, The warmth of sharing), bowl photo (media 1121, fika-email-bowl.jpg).
- Part 3 "Your rewards": the customer's delivered kilos in the current 15 kg lap (fika_swim_totals for accounts;
  Completed orders with that billing email for guests), a bar, the next reward and how far it is, the six stops
  (1.5 mystery taste, 3, 6, 10, 12.5 mystery taste, 15 kg) marked Reached / Next / to go. Members: "Your Fika Club",
  "See my rewards" (My account). No account: "Join the Fika Club", "your X kg count straight away", button to sign up.
  Staff accounts are treated as no account.
- Titles use the email's own h2 / .fika-mail-box styles (fika-emails.php forces p/td fonts and colours).
- Checked by rendering (not sending) for Rosa (user 133, 2.3 kg) and the preview sample. Mode stays Test
  (only alan.aboulhosn@gmail.com); nothing was sent.
- Later the same day (owner: "the fonts are great", keep the grid): headings in the website's cute font
  (NF Le Petit Cochon) as pictures, media 1122-1126 (fika-email-h-we-miss-you / fresh-from-sweden /
  made-for-sharing / your-fika-club / join-the-fika-club .png, 2x, made by wordpress/email-assets/
  make-email-headings.js). "We miss you!" replaces the header text after wrap_message (alt text keeps the words).
  Opening line: "It's been a while since your last fika! While you were away, a few new sweets found their way onto
  our candy wall. Let us introduce them." Grid heading "Fresh from Sweden" + "Four new friends on the candy wall",
  the same 2x2 grid, then "Mix them with your old favourites. Every scoop is 100 g, so a little of everything is
  easy." (the "Fan favourites" fallback keeps its text heading and has no closing line). Rendered for Rosa, not sent.
- Later the same day (owner: the bar should look like the one on the website): part 3 shows the website's fish lane
  as a picture. The real lane (fika_swim_html with the manager preview) was drawn at phone size, 3x, three times:
  empty (wheels grey), full (gifts lit, wheels ticked) and the fish alone (turned to face right): media 1130-1132
  (fika-email-lane-empty / -full / -fish .png; wordpress/email-assets/make-email-lane.js). fika_wb_lane_url($g)
  combines them with GD for the grams in the current lap (100 g steps): water up to the fish, gifts reached lit,
  wheels the water has passed lit, the fish on top; saved once as uploads/fika-lane/lane-v2-<g>.png (a plain file,
  loads for Gmail's image proxy). Without GD or the pictures it falls back to the plain bar. Earlier test uploads
  1127-1129 were deleted; uploads/fika-lane/lane-v1-2300.png is a harmless leftover.
- Lane v3 (owner: the bar must be visible; add "kg" to the numbers): the track is white with a light blue edge
  (the website's lane is pink, the same as the email box), the marked numbers read "0 kg ... 15 kg" (the little
  chequered flag after 15 is dropped, the finish flag on the lane stays). Media 1133-1135 replace 1130-1132 (deleted);
  files are now uploads/fika-lane/lane-v3-<g>.png (v1/v2 files there are unused leftovers).

### 2026-10-16 – Website lane: "kg" after the numbers (snippet 11)
- The marked numbers under the fish lane (0, 3, 6, 10, 15) now read "0 kg ... 15 kg"; the other numbers (shown on
  wide screens only) stay plain. Tick labels no longer wrap. On phones the small chequered flag after "15 kg" is
  hidden (it would poke past the card edge); the finish flag on the lane stays.
- Checked before going live with the live lane CSS at phone (358 px) and computer (1000 px) widths, and after: home,
  Mix your own, Ready Mix, My account return 200 and the lane prints the new labels.
- Reward list (owner-approved preview): names in the cute font as pictures, media 1137-1141
  (fika-email-r-mystery-taste / 100-g-on-us / 200-g-on-us / 400-g-on-us / a-whole-kilo-on-us .png, 2x, 111 px tall,
  shown 22 px; wordpress/email-assets/make-email-rewards.js), alt text = the words. Mystery taste line is now
  "A free 50 g surprise". The "Delivered orders count towards the lane..." line under the list is removed.
- Greeting names (owner: make sure each name matches the customer): fika_wb_first_name() keeps the first word with
  a capital letter ("galia" -> "Galia", "SARA" -> "Sara", "Henriette rawady" -> "Henriette", "jean-paul" ->
  "Jean-Paul"); used for the audience (latest order's billing first name, else the account's first name) and the
  admin preview. Audit of the 457 people before: 67 lowercase, 3 in capitals, 6 full names, 1 empty ("Hi there,");
  after: all clean. Each email still uses that customer's own name and kilos (looked up by their email).
- The "Mix them with your old favourites..." line under the candy grid is removed (owner).
- Review fixes (owner: fix 1-6 and the tweaks; progress moved up):
  - Order: what's new, then the Fika Club progress (with a plain dotted divider, no "Your rewards" label), then
    What fika means.
  - Candies chosen in WP Admin are "Picked for you" / "A few favourites from our candy wall" (all products date from
    the 7 October import, so "new" was not true); automatic picks from the last 45 days keep "Fresh from Sweden".
    Opening line and subject follow: "We picked a few sweets from our candy wall we think you'll love:" /
    "It's been a while, Rosa! A few sweets picked for you" (new: "Here's what's new:" / "Here's what's new at Fika").
  - Inbox preview text (hidden div at the top of the body): "Your fish is only 700 g away from 100 g of free sweets
    🍬" for members, "You already have 2.3 kg towards free sweets 🍬" without an account, else "New sweets on the
    candy wall, and free sweets to swim for 🍬".
  - Plain-text copy (snippet 28, fika-emails.php): pictures with class fika-t keep their alt text (headings, reward
    names, lane); the hidden preview text is left out. Grid names and prices sit in their own blocks, so each
    becomes its own line.
  - Candy photos: JPG copies with off-white turned white (uploads/fika-email-img/p<id>-v1.jpg, GD), alt = product
    name; names shortened in the email ("BUBS ... (Mini)" dropped); name row 52 px tall so prices line up.
  - Box titles in the cute font (media 1146-1150: picked-for-you, lordagsmys-is-here, family-time, real-quality,
    the-warmth-of-sharing); a news title without a picture falls back to the text heading.
  - Lane v4: the website lane drawn 300 px wide (media 1143-1145, fika-email-lane4-*), shown up to 400 px, so gifts
    and kg labels read better on phones; files uploads/fika-lane/lane-v4-<g>.png.
- Checked by rendering (never sent) Rosa (member, 2.3 kg) and nobody@example.com (no account), HTML and plain text.

### 2026-10-16 – Win-back email 1 goes Live on 17 October, 9:00 Beirut (snippet 35)
- Owner: this design is email 1; go live tomorrow 9:00 Lebanon time for everyone due for email 1; email 2 not live.
- New setting "Email 2" (email2, default 0, checkbox in WP Admin > WooCommerce > Win-back emails): while off, the
  run skips email 2 also in Live mode.
- One-off Action Scheduler action fika_winback_golive at 1792216800 (2026-10-17 06:00 UTC = 09:00 Beirut, EEST):
  switches the mode to Live and runs fika_winback_batch; each batch sends up to 50 (the run's cap) and schedules the
  next one 30 minutes later while 50 went out. At scheduling time: 248 due for email 1, 0 for email 2, so about five
  batches, done around 11:00; the daily run is next at 13:04 Beirut. The settings page shows the planned switch.
- Session check-ins at 09:02 and 11:15 Beirut to confirm (read-only).
- Cancelled the same night (owner: send nothing tomorrow, keep the current not-live status, more tests first):
  fika_winback_golive and any fika_winback_batch unscheduled; mode Test, email 2 off; nothing was sent (log empty,
  no send state). The daily run (13:04 Beirut) stays scheduled but in Test mode only reaches the test address,
  which is not due. The session check-ins were removed. The go-live code stays in snippet 35, unused until the
  owner says go.
- Note: one REST call at 00:12 UTC hit "Error establishing a database connection" (a Hostinger hiccup); it
  answered again 20 s later.
- New order (owner): the Fika Club progress opens the email (right after the greeting, no dotted line above it),
  then a dotted line and the candies with the news box and "Pick your mix", then What fika means. Opening line:
  "It's been a while since your last fika! Here's how close your Swedish fish is to free sweets, plus a few treats
  we picked for you." (new candies: "plus what's new on our candy wall."; never ordered: "Here's how our free sweets
  work, and what's on our candy wall right now."). Still Test mode, email 2 off, nothing scheduled, nothing sent.
- Progress check (owner: everyone has ordered, so everyone should show progress): of the 251 due for email 1,
  5 have an account and 246 do not; all 251 have delivered kilos (guest orders, old-store imports included, are
  found by billing email). The 0 kg version only appeared in previews to alan.aboulhosn@gmail.com (no orders).
- Preview: new box "Show it as this customer (their email)" - the preview uses that customer's name and Fika Club
  progress (fika_wb_email1( $to, $p, $as )) and still goes only to the preview address. The side-panel preview's
  "No account" example is now a real due customer without an account (4 kg), rendered for preview@example.com.
- Customers without an account: a reward their kilos have passed reads "Unlocks when you join" (pink) instead of
  "Reached ✓" (they cannot claim it before signing up). Of the 10 sample customers only Assil (2.4 kg) shows it.
- Checked for the loss-aversion idea: rewards on the website do not expire (no expiry anywhere in fika-loyalty.php /
  fika-taste.php). Of the 5 due members, Riwa, Zeina and Elena hold a won-but-unused mystery taste (user meta
  fika_tastes status "won"); none holds an unused gift code. Waiting on the owner's choice before any expiry wording.
- "Waiting for you" box (owner-approved; no expiry wording, since rewards do not expire): right after the greeting,
  only for members with something unused - mystery tastes won (fika_tastes status "won": "A free 50 g of Sour
  Cherries", "Fill your bag and your free 50 g is added at checkout."), claimed reward codes not used yet
  (fika_swim_open_codes), and gifts their delivered kilos reached but not claimed (fika_swim_unclaimed). Yellow
  dashed box, the candy photo, "Waiting for you" heading (media 1152, cute font), "Use it now" button (Mix your own).
  Their preview text becomes "A free 50 g of Sour Cherries is waiting for you on your Fika account 🍬".
  Of the 5 due members: Riwa, Zeina, Elena get the box.
- Opening line shortened: "It's been a while since your last fika! Here's how close your Swedish fish is to free
  sweets." (the "plus a few treats..." part removed).
- The three waiting mystery tastes were spun by the customers themselves on the website (Riwa 9 Oct, Zeina 11 Oct,
  Elena 13 Oct, Beirut time); nothing was spun or changed for them. The box line is now exact: the free 50 g joins
  the bag once delivered kilos + the bag reach the wheel's stop (fika_taste_need), so Elena (1.1 kg delivered, wheel
  at 1.5 kg) reads "Add at least 400 g to your bag and your free 50 g is added at checkout."; Riwa and Zeina
  (past 1.5 kg) keep "Fill your bag and your free 50 g is added at checkout."
- Wording (owner): "kilogram", never "kilo", in the email: "Every kilogram you order...", "A whole kilogram on us"
  (new reward-name picture, media 1153, fika-email-r-a-whole-kilogram-on-us.png; reward-name pictures may now shrink
  to fit a phone column), preview text "a whole free kilogram"; reward titles taken from the website are switched
  too. "kg" after numbers stays. Checked: no "kilo"/"kilos" left in the 10 rendered emails. The website itself
  still says "A whole kilo on us" (not changed).

### 2026-10-17 – "kilogram" instead of "kilo" on the website (snippets 10, 11, 16, 28, 91)
- Owner: never "kilo". Changed "every kilo" / "a whole kilo" to "kilogram" in the texts customers see:
  fika-loyalty.php (15 kg reward title "A whole kilogram on us", unlock text), fika-accounts.php (sign-up and
  confirm texts, welcome line), fika-club.php (club code email text; the welcome popup's "1 KG" badge regex now
  matches "a whole kilogram"), fika-emails.php (new-account email line "Every kilogram you order now swims you closer
  to a free one."), fika-signup-nudge.php (checkout invite lines and chip). Comments untouched; "kg" after numbers
  stays. Live copies matched git before; all five active without errors; home, Mix your own, Ready Mix, My account,
  About us return 200. Existing reward coupons keep their old description text (internal only).
- Win-back email: all reward-name pictures shown 17 px tall (one size, so "A whole kilogram on us" fits a phone
  column like the others).
- 2026-10-17: on the owner's request, one preview of email 1 shown as Riwa (account, 1.7 kg, waiting taste) was sent
  to alan.aboulhosn@gmail.com only (subject "[Preview] It's been a while, Riwa! A few sweets picked for you").
  No customer was emailed; mode stays Test.
- 2026-10-17: on the owner's request, six scenario previews of email 1 sent to alan.aboulhosn@gmail.com only, each
  shown as a real due customer: 1 account + reward waiting (Riwa), 2 account + waiting, needs more in the bag
  (Elena), 3 account, nothing waiting (Leen), 4 no account, below 1.5 kg (Sarah), 5 no account, passed a reward
  (Suzy), 6 signed up, never ordered (sample "Alan", 0 kg). Subjects start "[Preview n/6: scenario]". Mode Test.
- Link check before roll-out (all six scenarios rendered, not sent): "Join the Fika Club" and the lane picture for
  customers without an account now go to /my-account/#register (the page jumps to the sign-up form; with
  ?action=register it opened on the Log in form with sign-up below the fold). "See my rewards" and the members'
  lane go to /my-account/ (the Rewards page; signed-out customers see Log in first, then land on Rewards).
  "Use it now", the candy photos and names and "Pick your mix" go to /mix-your-own/. Footer: WhatsApp, mailto,
  Instagram (as in every order email). Shop links return 200; a wrong unsubscribe key shows "Link not recognised"
  and changes nothing. Mode still Test, email 2 off, nothing scheduled.

### 2026-10-17 – Win-back email 1 is Live (snippet 35)
- Owner's green light: email 1 to everyone who has not ordered in 30 days; email 2 stays off. Checked first:
  first_days 30, email2 0, 252 due for email 1, 0 for email 2. Started with fika_winback_golive at 16:23 Beirut
  (13:23 UTC): mode Live, first batch 50 sent, then a batch of 50 every 30 minutes (16:53, 17:23, 17:53, 18:23).
- One unsubscribe 30 s after the first batch, from a university address (queensu.ca, Microsoft 365): very likely a
  link scanner opening every link. Left unsubscribed (owner can decide). Fix: the unsubscribe link now opens a page
  "Unsubscribe from Fika emails?" with an "Unsubscribe me" button; only the button (a POST) unsubscribes. Checked
  with claude.unsub.test@example.com: opening the link did not unsubscribe; a wrong key still says "Link not
  recognised".
- TMP status snippet 168 ("TMP send status (Claude)") stays until the last batch is checked, then is deleted.

## Win-back email 1: stuck batch (2026-10-17/18)
- Batch 2 (17 Oct 17:03 Beirut) sent 21 emails and then hung before saving who had them; no batch 3 was scheduled.
  The daily run on 18 Oct 13:04 re-sent to 18 of those 21, then hung again at a different point in the list (so not
  one bad address; most likely the Hostinger SMTP server stalling after a burst, until PHP's 300 s limit ends the run).
- Fixed: win-back switched to Off; the 21 marked as sent (71 have email 1 now, 181 still due); fika_wb_run now saves
  the state after every email, so a run cut off part-way can never send anyone the same email twice.
- Owner's rule (18 Oct): never send anyone the same email twice; none is better than two. fika_wb_run now marks
  the email as sent and saves it BEFORE sending (a failed send is logged "not sent (mail error), not retried"), and
  only one run can go at a time (option fika_winback_lock; a lock older than 15 min, from a cut-off run, is taken over).
- Smaller batches (18 Oct): 10 emails per batch, 4 s apart, one batch an hour between 10:00 and 20:00 Beirut, at most
  60 a day (counted from the log); a mail error stops that batch. The daily action only starts the batch chain, and
  "is a batch waiting" counts pending actions only (the running batch is "in progress", which had stopped the old
  chain from planning its next batch). The 181 still due go out over about 3 days.
- 18 Oct 13:34: the first small batch froze again after 2 emails (a 3rd customer was marked and not retried). Cause
  is most likely Hostinger's per-mailbox sending limit (about 100 emails in any 24 hours on the free/legacy email
  plan; order emails count too): win-back had sent 91 in 24 h. Now: at most 50 win-back emails in any 24 hours
  (counted from the log), and a 30-second SMTP timeout on win-back sends so a held email fails instead of freezing.
  Win-back left Off until the owner says to resume.
- 19 Oct 17:11: a batch froze again on its 2nd email (only 41 sent in 24 h, so not only the mailbox limit; the
  30 s mail timeout did not end it). That customer stays marked (never retried). Fix: each batch now plans the next
  one BEFORE it sends, so a cut-off run no longer stops the chain; a batch that finds nobody due ends it.
  Two orders already came back after email 1 (#1539, #1540, 19 Oct).
- Why the 30 s timeout did not help: PHPMailer's SMTP Timelimit (wait for the server's answer) stays at 300 s;
  only Timeout (connect / each read) was 30. Now both are 30 s for win-back sends. Each send also records its steps
  with the mail server (option fika_winback_trace; a send that never reached "done" is kept in
  fika_winback_trace_stuck, the last 5), using PHPMailer's debug output filtered to commands and answers (no login
  lines, no email content). Checked with a send to an @example.com address, which Hostinger refuses at the end of
  DATA (nothing delivered): all steps recorded in 0.6 s.
- 19 Oct 2026: RT export of 19 Oct 17:12 (502 parcels) applied the same way (fika_rtd_apply, RT column + order note,
  no emails; temporary endpoint deleted). Matching: tracking already on the order, then phone + cash, and #1514 by
  name + phone (RT cash $27.80, order $27.40). 38 status changes: 35 processing -> completed (completed time = export
  time), #1461 and #1473 -> failed (RT: Cancel), #1529 -> failed (RT: Delivery failed). RT step updated on 12 open
  orders (#1507, #1522, #1531-#1540; #1532 "Return to warehouse"). #1530 is linked to RTL18444083 ($0 parcel); RT
  also has RTL74930512 ($27.40, delivered) for the same customer. Cancelled #1148, #1151, #1284 left alone; parcels
  not from the website ignored.
- 21 Oct 2026: cause of the frozen batches found. Every failed batch (7, 17-21 Oct) had been started by Action
  Scheduler "via Async Request"; every batch started "via WP Cron" finished. The async request is a loopback HTTP
  call the web server (LiteSpeed) ends part-way (no "noabort" for it). The 21 Oct 16:34 trace proves it was not the
  mail server: the 2nd email was accepted ("250 queued"), the 3rd customer was marked, and the run died before that
  email was even built. Fix: fika_winback_batch sends only when wp_doing_cron(); a run started any other way plans
  the batch again a minute later. The customers marked in a frozen batch (5 since mark-before-send) did not get the
  email (at least the last one provably not); they are not retried (owner's rule).

## Skeptical-buyer fixes (2026-10-22)
- Banner (synced pattern head, 239): "Order by 5 pm: delivered tomorrow in Beirut, in 2 days across Lebanon · Cash on
  delivery · Freshly sealed Swedish sweets" instead of the Nabatieh notice (which moved to checkout; the FAQ keeps it).
- Snippet 182 "Fika: product list in the page" (fika-products-inline.php): the Store API product list is written into
  every shop page as window.fikaProductsData (transient fika_products_json, 12 h; cleared + LiteSpeed purge on a
  product save, stock-status change or trash). window.fikaProducts() in pattern 239 uses it, else fetches as before.
  Grids list the sweets ~20 ms after the page is ready instead of ~330 ms (more on slow phones; the Store API call
  alone took ~2 s cold).
- Snippet 183 "Fika: delivery date at checkout" (fika-delivery-estimate.php): expected delivery above the Place order
  row (owner's rule: Beirut next day before 17:00 Beirut, 2 days from 17:00; outside 2 / 3 days; Sundays skipped like
  the Delivery column), both dates until an area is picked; "We can't deliver to Nabatieh and bordering cities for
  now." under the delivery area; the country field hidden (Lebanon is preset).
- About (page 257): "every kilogram swims you closer to free sweets".
- Previewed first by rewriting the live pages in a test browser only (Playwright route), then made live; the live
  copies matched git before they were replaced.

## Nightly candy sales into the FIKA Google Sheet (2026-10-22, not live yet)

- Shop side: snippet "Fika: daily candy sales for the FIKA sheet" (wordpress/snippets/fika-daily-sales.php).
  GET /wp-json/fika/v1/daily-sales?from=&to= (Beirut days, header X-Fika-Key) answers kg per candy per day under the
  Inventory tab's names. Counting: processing / on hold / completed, no TEST ORDER, 100 g per unit, mystery taste 50 g,
  refunded units off. Ready Mix stays its own line ("Ready Mix: <name>") until FIKA_DS_MIXES holds each bag's contents.
  The key is option fika_ds_key; admins read it at /wp-json/fika/v1/daily-sales-key. Never commit it.
- Sheet side: wordpress/google-sheet/fika-daily-sales.gs, pasted into FIKA > Extensions > Apps Script with the key.
  setup() once: Daily Sales tab (Date | Candy | Sold (kg) | Orders) filled from 2026-10-09; Inventory N "Sold since
  count" (orders after the candy's count date: Inventory System Q, else B8); Inventory E = C + D - N; Inventory System
  D (projected stock) = stock at count - sold since count instead of minus the forecast; nightly trigger 00:30 Beirut.
  nightly(): rewrites the last 7 days, then one Stock Log row per candy (Source "nightly orders").
- Name mapping agreed with the owner: Fizzy Blue = FIZZYPOP BLUE 6 KG LÖSVIKT, Fizzy Pop = Bubbliz Original.
- Preview from real orders 2026-10-09..22: 88 orders, 82.55 kg, all 31 candies matched; Ready Mix 21 kg unsplit.
- Tested offline (wordpress/google-sheet/test/): the snippet on the real orders matches the preview row for row; edge
  cases (refunds, 23:50 / 00:10, test / failed / cancelled, mystery taste, unmapped candy, a mix split) right; the .gs
  on a copy of FIKA recalculated in LibreOffice: setup 247 rows, nightly adds the new days, a second run the same
  night changes nothing, Cola N 0.5 -> E 31.265 to 30.765 and Inventory System D the same, no new error cells, wrong
  key = error (Google emails the owner). Google-style requests reach WordPress through the CDN; 3 tries 20 s apart.
- Go live (owner's word): fill FIKA_DS_MIXES (grams of each candy per 500 g bag, product ids), re-run the tests, then
  WORK=<scratch> wordpress/google-sheet/golive.sh (installs with POST, checks the link, writes the owner's script
  with the key), send that file, owner follows wordpress/google-sheet/README.md, then read the sheet to confirm.
- FIZZYPOP BLUE has no Est. Quant., so it gets no nightly Stock Log row (Inventory System D still subtracts its sales).
- Inventory E25 (FIZZYPOP BLUE) has no formula; setup leaves it, so the Inventory System flags it "Est. Quant. blank".

## Halloween week look (2026-10-23: installed as snippet 184, look OFF until the owner clicks)

- Snippet "Fika: Halloween week look" (wordpress/snippets/fika-halloween.php), built by build-halloween.py from
  fika-halloween.css + fika-halloween.js. Adds body class fika-hw, the Creepster font, the CSS (wp_head) and the
  decorations (wp_footer). Colours and decorations only; no markup, text, links or shop code change.
- Look: night background, pumpkin orange instead of the blue, Creepster section titles with a skull, candy photos in
  tombstone frames, banner dots as skulls, bats, cobwebs and a dangling spider in every hero, glowing googly eyes,
  skull row in the footer. Cart / checkout / My account: night page, forms stay light, orange Place order.
- On from activation until FIKA_HW_END = 2026-11-01 00:00 Beirut, then off by itself; LiteSpeed purged once on
  activation (option fika_hw_live = version) and by the one-off cron event fika_hw_ended at the end.
- Tested in a test browser (HTML rewritten, nothing live): home, Mix your own, Ready Mix, About, My account, phone
  and desktop; add to bag, the bag drawer, checkout via the bag's button, cart, phone menu: no page errors, no order.
- Every fish SVG (identified by its tail path 'M62 38 L90 22': rewards lane, sign-up card, Fika Club card and pop-up)
  is swapped for a spider, also when added later (MutationObserver). Cursor: jack-o'-lantern PNGs in
  wordpress/cursor/halloween (draw.py), lit + tilted over links; the fish bite on click is hidden.
- Off / on in one click: "Halloween look" in the WP admin top bar (option fika_hw_on, absent = off; nonce, admins only,
  purges LiteSpeed). Status for admins: GET /wp-json/fika/v1/halloween.
- 2026-10-23: snippet 184 "Fika: Halloween week look" installed active (POST), live copy = git, status on:false; all
  shop pages 200 with no theme markup; checked on phone and desktop: unchanged for customers. Switching the snippet off removes it entirely; the normal site underneath is never changed.
- Noticed, not changed: the My account page says "Every kilo you order" (owner rule: kilogram).

- Fixed in wordpress/google-sheet/golive.sh: the app password has spaces, so curl gets it as one quoted -u argument.

## About page: Lördagsmys -> Fredagsmys (2026-10-23, live)

- Owner: the section should be about fredagsmys (Friday), not lördagsmys. Changed in about-us.raw.html and
  build-shop-pages.py: heading "Fredagsmys, now in Lebanon"; "Fredagsmys (say "fray-dahgs-mees") means "Friday
  cosiness""; "When Friday evening comes ... after the week"; the Saturday-sweets sentence replaced with "All over
  Sweden, Friday night means the sofa, a film and a bowl of sweets, and the whole week builds up to it."; "We think
  fredagsmys fits right in"; "make Friday night the sweetest night of the week"; "Build your Friday bowl". The delivery
  line "Monday to Saturday" stays. Live page 257 matched git before the change.
- Still says lördagsmys: the win-back email (fika-winback.php: "Lördagsmys is here" block, "Family time" text).
- 2026-10-23 win-back email: Fredagsmys instead of Lördagsmys. Code (snippet 35, POST, live = git): defaults
  "Fredagsmys is here" / "Make Friday night the sweetest night of the week: ...", "Family time" now "Friday evening ...
  In Sweden it is called fredagsmys ...", heading picture fika-email-h-fredagsmys-is-here.png (media 1175, 817 x 104,
  drawn like the others). Saved settings (option fika_winback_settings) news_title / news_text changed by a temporary
  admin route (removed). At the change: mode live, no batch pending, lock free, next daily run 2026-10-24 08:55 UTC.
  Whole email shown in the "Fika Win-back Email" artifact (10 real customers, rendered by the shop's code, not sent).
- 2026-10-23 15:07: About page 257 updated (POST, saved text = git, live matched the checked copy first); the page
  shows Fredagsmys, no Lördagsmys; checked on phone and desktop; Halloween look still off.
- 2026-10-24 Halloween look fixes (owner's review of the preview), installed in snippet 184, still OFF:
  flat night section backgrounds (no lighter boxes); badges as a pill at the top middle of the tombstone; Ready Mix
  photos swapped (only while the look is on) for white-background copies, media 1178-1180
  (wordpress/media/halloween, made by whiten.py: only the neutral grey backdrop is whitened, bag and sweets kept);
  "Can't decide?" / "Rather pick your own?" box black with orange; About "How it works" cards, links, number circles
  and buttons themed; footer copyright readable; phones: no cobwebs (they read as patches under the fixed header),
  smaller spider between Menu and the bag icon, two bats. Preview: googly eyes placed by the live home page's own
  eye script (home-skull-eyes.html in git is an older version), photo sizing script kept.

## Fika inbox at checkout (2026-10-24, preview only, not live)

- Snippet "Fika: customer texts at checkout" (wordpress/snippets/fika-checkout-texts.php): a card just under Place
  order (.wc-block-checkout__actions_row) that plays real customer WhatsApp messages (owner's screenshots, word for
  word, no names) like a chat: typing dots + "a customer is typing...", the bubbles with their times, the pink heart.
- The fitting message first: BUBS Wild Berry Pomegranate (108) in the bag -> the Wild Berry message (left out while
  108 is out of stock); sweet and sour candies or the Sweet & Sour Mix (94) -> "loved both the sweet and the sour".
- One fixed height (the tallest message, measured at the page width), messages anchored at the bottom: the Place order
  button never moves (tested: phone and computer, 21 s, button position constant). Plays only on screen; a mouse
  or keyboard focus pauses it (touch does not); tap / arrow = next; reduced motion = no typing, no auto-play.
- "N orders delivered since August" = wc_orders_count('completed'), cached 6 h (488 on 2026-10-24).
- Halloween look: avatar fish becomes the spider by itself; fika-halloween.css tints the card's blue to the
  checkout's purple (in git, goes into snippet 184 with its next update).
- 2026-10-24 owner: switch to the next chat automatically, 1 second after each chat's animation ends. Now: next
  customer 1.32 s after the heart is added (its pop takes .32 s) -> measured 1.32 s every time; the mouse-over pause
  is gone (it made the card look stuck); keyboard focus still pauses; reduced motion: a new message every 4 s.
  After the arrow, the heading shows "Real texts from customers" again (it kept "typing...").
- Working checkout copy for the owner: wordpress/checkout-preview (capture-co.js, assemble-co.py).

## 2026-10-24: Fika inbox checkout card, where it sits (NOT live)

- Computer (two-column checkout): top of the right-hand column, sticky (top 24px), so it is 80-100% on screen while the
  whole form is filled in; the order summary scrolls under it. Tried under the summary instead: unseen for the first
  two thirds of the form, so not used.
- Phone: card stays just under Place order; a slim message (short texts only) slides up from the bottom 8 s in, then
  4.5 s every 18 s. Hidden while typing in a field, while the card or Place order is on screen, and for the visit once
  closed with x (sessionStorage fikaToastOff).
- The counter updates as soon as the next text is chosen, so a tap on the arrow during the fade moves one step.
- Preview: https://claude.ai/artifact/Cvhdgb4AqaeNancaP52jTQ (version 2). Going live: create the snippet from
  wordpress/snippets/fika-checkout-texts.php, and update Halloween snippet 184 with the .fika-texts CSS (stays OFF).

## 2026-10-24: Fika inbox LIVE; full journey; speed work (in progress)

- Customer texts at checkout went live as snippet 190 ("Fika: customer texts at checkout"), from
  wordpress/snippets/fika-checkout-texts.php. Checked on the live checkout (bag with two sweets, no order): computer card
  in the right column 80-100% on screen while scrolling; phone message slides up while filling the form, hides while
  typing and near Place order; no page errors.
- Halloween snippet 184 updated with the .fika-texts purple (live copy matched git first); look still OFF
  (GET /fika/v1/halloween on:false, no fika-hw on the home page).
- Journey on 7 devices (desktop, laptop, iPad, iPhone, Instagram, Facebook, small phone): all pass (38-39 checks
  each), no failed requests, no script errors, order request stopped (no orders). 7 saved test bags removed.
- Speed, snippet 23 (fika-speed.php) LIVE:
  - Theme fonts at checkout / cart / account (Open Sans, Fira Sans, Roboto: up to 580 KB .ttf) replaced by Latin
    .woff2 copies of the same files (Open Sans at normal width only): media 1181 (Fira Sans 32 KB), 1183 (Roboto 25 KB),
    1184 (Open Sans 54 KB); sources in wordpress/fonts. The theme's rules for those three are left out (else the full
    files were still fetched for a ✓ or an emoji); other theme fonts unchanged. Uploaded through temporary admin-only
    woff2 snippets 191 and 192 (both deleted); an earlier 94 KB Open Sans (media 1182) was deleted.
  - Shop pages: the Open Sans copy replaces Google's Open Sans style sheet (it held up the first paint).
  - Each shop page's top photo (.fika-photo) is preloaded at high priority, read from the page's own styles (home:
    phone or computer copy by width; placed after the viewport tag, else phones took the computer photo too).
  - A/B in the test browser (same page copy, 4 runs, median, first visit), main picture (LCP), phone 4G / computer:
    home 1.25 -> 1.12 s / 0.61 -> 0.64 s; Mix your own 1.12 -> 0.84 / 0.69 -> 0.59; Ready Mix 0.98 -> 0.80 /
    0.66 -> 0.63; About 1.21 -> 0.88 / 1.08 -> 0.71. Photo request starts at ~0.07 s instead of ~0.7 s.
    Phone home first text +0.13 s (the photo now shares the start). Checkout fonts on a phone 250 KB -> 32 KB.
  - Checkout and account screenshots pixel-identical before / after (phone and computer).

## 2026-10-26: Halloween look, quality pass (owner: "below par, keep the normal site's standards")

- Eyes off in the preview on computers: the captured copy kept the capture window's width (1400 px) on the hero,
  sections and shop grids, so on any other screen the photo filled the window but the eyes were laid out for 1400 px
  (20 px off at 1440). The preview now re-fits them per screen (wordpress/halloween-preview/keep-fit.js.html, the
  shop's own fitX); eyes match the shop to 0.0 px at 1280, 1366, 1536, 1920 and phones 360, 390, 430. On the live
  site the eyes were already right.
- Look fixes (fika-halloween.css/.js, in git, NOT yet in snippet 184; look still off): photos no longer washed out
  (dark band only behind the header, night fade at the bottom); webs smaller and under the header (not over the
  logo / icons); spider drops between logo and menu; bats fly below the menu; scrolled header solid (no blurred
  buttons behind it); account icon bone like the bag; video slider arrows / dots / Sound in orange; reviews card at
  checkout pale lilac with a night-purple avatar.
- Preview republished (version 5) from a fresh capture; checked every screen on computer and phone side by side
  with the normal site; no errors, no broken images, no sideways scroll.
- Owner saw a gap between the menu and the stuck filter bar on Mix your own (preview only: the shop and the live
  look have 0 px at 1280, 1440, 1920 and phone). The capture froze the bar's top (130 px, from the taller menu at the
  top of the page); keep-fit.js.html now keeps it 1 px under the menu as you scroll, like the shop. Search
  magnifier and focus ring orange in the look. assemble.py takes HW_CSS=<hw.css> to apply a CSS-only change without
  a new capture. Preview version 6.
- Owner's notes (26 Oct): the hanging spider has googly eyes (own layer <b> above the legs); the webs sit right in the
  photo's top corners again (top 0); room under the "All pick & mix" squiggle (#shop .mx-head margin 18 px: 12-13 px
  clear of the arched cards on computer, 10-42 px on phones). Preview version 7. Snippet 184 not yet updated.
- Pumpkin click (owner, 26 Oct): each left mouse click with the look on shows a soft orange glow ring from the middle
  of the pumpkin cursor and four embers (orange / slime green) drifting up, ~0.55-0.75 s, then removed. Mouse only
  (not touch), none with reduced motion, pointer-events none (links and buttons unaffected; tested). Preview
  version 8 (assemble.py HW_JS adds the boo:start..boo:end part of hw.js). Snippet 184 not yet updated.

## 2026-10-26: "Two new drops" arrival popup (preview, NOT live)

- Owner: a popup on arrival with two offers, both 500 g and $14: (1) Limited Edition Drop, sweet & sour bundle: the new
  BUBS Sour Strawberry Vanilla Diamond (not in the shop yet, no photo: drawn stand-in) with BUBS Wild Berry Pomegranate
  Ovals (product 108, now out of stock) back with limited supply; (2) Skull Mix: the 5 BUBS skulls (120 Bubblegum,
  124 Cola, 122 Lemon Raspberry, 118 Fruity Lemon Mix (Mini), 107 Raspberry Salty Licorice) in a skull-shaped bag.
- wordpress/snippets/fika-drops-popup.php: shows 0.4 s after arrival, once per visit (sessionStorage fika_drops_seen),
  home / Mix your own / Ready Mix / About only, never right after an order (?fika_order, the Order confirmed popup);
  closes with x, Keep browsing, tap outside, Esc. Bag illustrations drawn in SVG with the shop's cut-out candy photos.
  Night colours under the Halloween look. "Add to bag" only says Added in the preview.
- To go live: two products (Ready Mix category, 500 g, $14) for the bags, the Add to bag hand-off to the Fika bag,
  product 108 back in stock, the Diamond product + photo.
- Preview: https://claude.ai/artifact/FtuVBAcKfELbevxULmaetB (wordpress/drops-preview).
- Owner (26 Oct): Halloween themed, realistic pictures with the skulls (and the pomegranate drop) coming out of the
  bag. Redone: the popup shows only while the Halloween look is on (fika_hw_on(); managers: ?fika_drops=preview),
  night card with cobweb and bats, Creepster title, the arched Halloween photo frames, orange buttons; phones get the
  two offers side by side (fits 360-430 px phones and a 1280x720 laptop without scrolling). Pictures: real photo
  composites (wordpress/images/drops): the Fika bag from the Sweet Mix photo recoloured black (Skull Mix, bone skull
  sticker) and night purple (Limited Edition sticker), orange logo, real candy cut-outs inside the opening and spilling
  out. Preview version 2 (Halloween look only).
- Preview on the real site (owner, 26 Oct): snippet 193 "Fika: Halloween drops popup (hidden until FIKA_DROPS_LIVE)"
  installed with FIKA_DROPS_LIVE false: customers never see it, also when the Halloween look is switched on; photos
  media 1195 (Skull Mix) and 1196 (Limited Edition Drop). Snippet 184 updated with all the Halloween fixes since 24 Oct
  (live copy matched git first; the look stays off: /fika/v1/halloween on:false) and a private preview link:
  https://swedishfikalb.com/?fika_preview=5d13fcd594 shows the Halloween look (and the drops popup, once per visit)
  to whoever opens it, no login needed; links on the page keep the key; a note at the top says it is a preview.
  Checked on the live site, phone and computer: normal visit = normal shop, no popup; wrong key = normal shop;
  preview link = look + popup + both photos, next page keeps the look, popup not repeated; no script errors.
- Going live with the drops: create the two 500 g products (Ready Mix), connect Add to bag, stock for product 108,
  then set FIKA_DROPS_LIVE to true in snippet 193.
- Owner (26 Oct): the drop is the classic Sweet & Sour Mix with the limited candies in the bag; the Skull Mix label is
  "Halloween offer"; both bags in the Ready Mix tab, 500 g, $14 like the other Ready Mixes.
  - Products 1200 Limited Edition Drop (menu order -2) and 1201 Skull Mix (-1): simple, $14, Ready Mix, Gelatin Free,
    "One 500 g bag: ...", photos 1204 / 1205; published but catalog visibility HIDDEN, so customers' Ready Mix tab
    still shows the 3 mixes (checked: Store API catalog list and the in-page product list).
  - New drop picture from the real Sweet & Sour Mix photo (classic candies kept, limited candies mixed in), popup
    media 1199; earlier media 1196, 1197, 1198, 1202, 1203 (my own uploads, unused) deleted.
  - Snippet 193: copy "Our classic Sweet & Sour Mix with this season's limited candies in the bag: the NEW ... and
    BACK ...", sub "Sweet & sour mix, 500 g"; Skull Mix badge "Halloween offer"; Add to bag puts rw1200 / rw1201
    (500 g) in the Fika bag like the Ready Mix tab, "See my bag" opens the bag; in the preview the tab lists the two
    bags first with "Limited edition" / "Halloween offer" labels.
  - Tested on the live site through the preview link, phone + computer: popup -> bag (both $14) -> checkout (both
    bags, $28; the order request was stopped, nothing ordered); normal visit unchanged; no script errors.
  - Going live: catalog visibility of 1200 / 1201 to visible, FIKA_DROPS_LIVE true (snippet 193), stock for 108.
- Owner (26 Oct): one click on the Halloween switch should bring the updated version. Snippet 193 now has
  FIKA_DROPS_LIVE true and follows the switch: popup with the look; products 1200 / 1201 visible in the Ready Mix tab
  while the look is on (option fika_hw_on and before 1 Nov), hidden again when it is switched off or ends (checked
  on init, changed only when the switch moved, then product list + page cache cleared; option fika_drops_visibility).
  Logic tested offline (off / on / off / on / ended). Snippet 184 already held all of today's Halloween fixes.
- At install the switch was already ON (/fika/v1/halloween on:true, switched from the admin bar), so it went live at
  once: checked as a customer, phone + computer: look, popup on arrival (not again on the next page), both bags first
  in the Ready Mix tab with labels, Add to bag -> bag -> checkout $28 (order request stopped, nothing ordered), no
  script errors.
- Owner (26 Oct): the drop picture "more concentrated with the new releases", and the name "Fan Favourites Mix".
  - Picture: 21 Pomegranate Ovals / Strawberry Vanilla Diamonds packed into the spill (was a handful), sticker now
    "FAN FAVOURITES · LIMITED EDITION · 500 G" (images/drops/compose2.py, stickers.html). Popup media 1206
    (fika-drop-fan-favourites.webp), product photo 1207 (fika-ready-fan-favourites-mix.jpg, pure white); the old
    1199 and 1204 deleted (nothing used them any more).
  - Product 1200 renamed "Fan Favourites Mix", slug ready-fan-favourites-mix (product pages send customers to the
    Ready Mix tab anyway, old and new link alike); short description "...packed with this season's limited fan
    favourites...". The "Limited edition" badge and the grid label stay.
  - Snippet 193: card title, Add to bag name, picture and alt text (live copy matched git first; POST).
- Customer journey on the live site, Halloween look on, phone (390 px iPhone) + computer (1440 px), no orders
  (tools/journey: journey-halloween.js, checkout-place.js): home with the look (eyes, header, pumpkin click), popup on
  arrival with both new pictures, closes, not again in the visit; Mix your own + / -; Ready Mix: Fan Favourites Mix and
  Skull Mix first with their labels and the new photo, Add to bag 500 g; About us; bag drawer (3 lines, remove one,
  totals update); checkout: same lines and total as the bag, Inside Beirut delivery $5 priced in ~5 s, form filled,
  Place order sent the order request (stopped in the browser: the "something went wrong" message after it is the
  stop); My account login; 404 page with the shop header; no sideways scroll, no broken pictures, no script errors.
  Noted: one stray 404 for "/ready-mix/url(https:/fonts.googleap..." once on the phone, not seen again in 5 more
  visits, page unaffected; the checkout takes ~5 s to price delivery after the area is chosen (button works after).
- Owner (26 Oct): the candies' eyes red "to resemble Halloween". Previewed two options on the live home page (red iris
  on white / fully glowing red); owner chose the glowing red: .fika-eye blood-red gradient with a red glow, black
  pupils with a pale highlight (fika-halloween.css, rebuilt snippet 184; live copy matched git first, POST). Only with
  the Halloween look; the normal site keeps its white googly eyes. Checked live on phone and computer (cached pages
  carry it too).
- Owner (27 Oct): the home page's READY-MIX heading kept the normal font in the Halloween look. Its font is written
  on the heading in the page (inline font-family !important, which no style sheet can beat). Halloween snippet 184
  now sets Creepster on that heading itself while the look is on (fika-halloween.js heads(); the page is not
  touched, the normal site unchanged). Live copy matched git first, POST. Checked live, phone + computer: all four
  home section headings (Mix your own, READY-MIX, Loved across Lebanon, FAQ) in Creepster, no script errors.
- Fan Favourites picture: owner said do not put the redesigns live (v2 and v3 stay in git only); the live picture
  is still the one from 26 Oct.

### Customer note envelope in Orders (27 Oct 2026)
- Snippet 194 "Fika: customer note envelope in Orders" (fika-order-note-icon.php, admin only): in WooCommerce >
  Orders a small golden envelope next to the customer's name when the order has a customer note (the note typed at
  checkout); hovering it shows the note. Printed in a narrow column after Order, then moved next to the name by a few
  lines of script (WooCommerce's Order cell takes no extra HTML); without script it stays in that column. Orders are
  only read. Works for the HPOS orders page and the old posts page.
- Checked on the real orders list (rendered through a temporary admin-only route, snippet 195, deleted): 20 rows,
  envelopes on exactly the 2 orders with a note, none on the others. The first version of that temporary route hit a
  PHP error inside its own request (shop unaffected); WordPress may have emailed the site admin address about it.
- 27 Oct (owner: "send this one live"): the Fan Favourites picture from the owner's reference (images/drops/v3,
  final-d: their bag, sticker FAN FAVOURITES, real-look Strawberry Vanilla Diamonds and Pomegranate Ovals in front,
  classics mixed in). Product photo media 1213 (fika-ready-fan-favourites-mix-v3.jpg, 1024x1280, white); popup
  media 1212 (fika-drop-fan-favourites-v3.webp, 720x745, on the popup's bone table). Product 1200 image and snippet
  193 (picture + alt; live copy matched git first, POST) updated; old media 1206, 1207 deleted (nothing used them).
  Checked live, phone + computer: popup and Ready Mix card show the new picture, no script errors.

### 2026-10-27 – Bag reminder: Halloween version (PREVIEW, NOT LIVE)
- Owner: all the Halloween ideas for the "Your bag is waiting" reminder, preview first.
- wordpress/snippets/fika-bag-reminder.php (git) now holds the Halloween version, switched off by FIKA_BAG_HW_LIVE
  false; it is NOT installed yet: live snippet 25 is still the previous version (git differs from live until the
  owner approves; installing was held back as a change to live sending code without approval).
  - While FIKA_BAG_HW_LIVE is true, the Halloween look is on (option fika_hw_on) and before 1 Nov: night purple and
    orange, the logo with bats and a cobweb, Creepster headings as pictures, "Something sweet is still lurking in
    your bag...", orange button, "New this Halloween" panel with Fan Favourites Mix and Skull Mix (only those on
    sale), subject "Your Fika bag is waiting... 🎃". Otherwise the normal reminder (rendered pixel-identical).
  - Also in that version: the reminder is marked as sent before it goes out (CLAUDE.md rule; today it is marked
    after the send).
  - Preview route in that version: GET /fika/v1/saved-bags/sample?hw=1|0 (never sends).
- Pictures uploaded (unused until it goes live): media 1214 fika-email-hw-logo.png, 1215-1219
  fika-email-hw-foot-top/wa/mail/ig/bottom.png, 1220-1223 fika-email-hw-h-your-bag-is-waiting / your-bag / delivery /
  new-this-halloween.png (sources and tools: wordpress/email-assets/halloween).
- Preview rendered from the new code through temporary admin-only snippets 196-198 (each deleted; no sending, no
  saving), shown on the "Fika Win-back Email" artifact (now "Fika emails": Halloween + normal bag reminder, then the
  win-back customers).
- Owner (27 Oct): "Add to bag" under each Halloween bag in the email. The link is the bag's own link plus
  &fika_add=<product>: the saved bag comes back with that Halloween bag added (one more if already there), then the
  checkout opens with the email filled in. Only 1200 / 1201, only while on sale. If the bag link no longer works
  (ordered, too old), the Halloween bag is added to the cart as it is and the checkout opens. Price and button on
  their own row, so both buttons sit level. Still preview only (snippet 25 not installed); the link itself can be
  tested only once installed (plan: a saved test bag @example.com, no order).
- Owner (27 Oct): footer text orange. Footer pictures redone: all text orange (Fika wordmark, tagline, WhatsApp /
  Email us / Instagram, bottom line), icon badges bone. New names fika-email-hw2-foot-top/wa/mail/ig/bottom.png,
  media 1229-1233 (the server kept serving a cached bone copy under the old name); old 1215-1219 and 1224-1228
  deleted. Code: the footer swap now points at hw2. Still preview only.
- Owner (27 Oct): orange icon badges in the footer too (icons dark on orange): media 1234-1236
  fika-email-hw3-foot-wa / mail / ig.png; the bone-badge versions 1230-1232 deleted. Still preview only.
- 28 Oct (owner: "send it live"): snippet 25 installed with FIKA_BAG_HW_LIVE true (live copy matched the previous
  git version first; POST). Halloween look is on (until 1 Nov 00:00 Beirut), so reminders now go out in the
  Halloween look, marked as sent before the send. Checked: sample = Halloween subject, logo, 2 Add to bag buttons,
  orange icons; home / Mix your own / checkout respond normally.
  End-to-end test (claude.bagtest@example.com, no order): checkout email typed -> bag saved (2 x Bubblegum Skull);
  the email's "Add to bag" links opened in fresh browsers: phone -> checkout with Bubblegum Skull + Fan Favourites
  Mix, $19.60, email filled in; computer (Skull Mix link) -> + Skull Mix, $33.60; the shop's bag matched. Test bags
  removed (DELETE /fika/v1/saved-bags/test: 7 removed, none left), so no reminder goes to the test address.

### Order source and Meta ad results in Orders (28 Oct 2026)
- Snippet 203 "Fika: order source in Orders (Meta ad badge)" (fika-order-source.php, admin only, orders only read):
  a "Source" column after Order (Meta ad · campaign / Instagram / Facebook / Google / Direct / Other), from
  WooCommerce's own order attribution (_wc_order_attribution_*: on all orders, the script loads on every page).
  Meta ad = utm_source ig / fb / facebook / instagram + utm_medium paid (Meta adds these with campaign / ad set /
  ad numbers to every ad click); the Instagram bio link is utm_medium=social, link_in_bio.
- Box above the list: Meta ad orders and sales, today and in total, per campaign (processing, completed, on hold,
  pending; cancelled / failed / refunded not counted; cached 5 min, transient fika_src_ads2). Campaign names in
  FIKA_SRC_CAMPAIGNS: 120250075140790390 = Halloween, 120249990307440473 = Launch (10-14 Oct).
- Checked on the real list (temporary admin-only route 204, deleted): Halloween ad 2 orders $90.40 today (#1569,
  #1571), launch campaign 13 orders $573.20 (#1460-#1522, one failed not counted).
- Same day (owner: WooCommerce's Origin column already shows the source): the Source column is removed; orders from
  a Meta ad get a "Meta ad · campaign" tag under their Origin (Origin says "Ig" for the ad and the bio link alike).
  The Meta ads box above the list stays. Checked: #1569 and #1571 tagged, #1568 / #1570 / #1572 not.

### 2026-10-28 – Meta pixel / Conversions API check; Halloween look found switched off by Code Snippets
- Pixel + CAPI (snippet 43, live == git): Meta accepted every server event 21-28 Oct, none refused (28 Oct: 790,
  about double a normal day = the ad). Every order 26-28 Oct has "Meta: purchase sent to the Conversions API",
  including the two ad orders #1569 / #1571. Ad-visitor journey (phone + computer, Meta requests stopped so nothing
  reached Meta): pixel 2660128037781548 inits; PageView, ViewContent, AddToCart, InitiateCheckout fire in the browser
  and each has its server copy with the same event ID (dedup); _fbp, _fbc (from fbclid) and fika_vid set. Halloween
  popup "Add to bag": AddToCart $14, content_ids 1200, browser + server, same ID.
- Found while testing: the Halloween look, the drops popup and GET /fika/v1/halloween were gone from the live site.
  Snippet 184 active and its code intact in the database (72,860 bytes, == git), but Code Snippets 3.10.2 loaded it
  as EMPTY (get_snippet code ''), so it never ran. Bisected with inactive copies: the comment line `/* boo:end */`
  alone makes the plugin blank a snippet (the plugin must have changed: the line had been there since 26 Oct). No other
  active snippet affected (all checked: database length == loaded length). Fix: comment reworded in
  fika-halloween.js ("// end of the pumpkin click glow"), rebuilt (version 59257bc078), POST to 184: loads 72,877
  bytes, /fika/v1/halloween on:true, look + popup back on home / Ready Mix / ad links (LiteSpeed purged by the new
  version). Down for part of 28 Oct (after ~00:40 UTC, when it was last seen working, until 18:40 UTC); the Halloween
  bags stayed in the Ready Mix tab and kept selling. Rule: never write `/* name:end */` style comments in snippets.
- Temporary diagnostic snippets 207, 208 and the bisect copies deleted; earlier temp snippets 195-206 are in the
  Code Snippets trash, inactive.
- Owner (28 Oct): "never again; Halloween stays until I click the button".
  - No end date: FIKA_HW_END 2099-12-31 (build-halloween.py); /fika/v1/halloween shows ends 2099. The look, the
    drops popup, the two bags in the Ready Mix tab (193) and the Halloween bag reminder (25) all follow the admin-bar
    switch only. Snippet 184 reinstalled (live == git first).
  - Snippet 221 "Fika: snippet guard" (fika-guard.php, global, priority 99, runs after the others): watches
    184 fika_hw_on, 193 fika_drops_img, 25 fika_bags, 43 fika_meta_send, 11 fika_swim_totals. If a watched snippet is
    active but its function is missing (the plugin skipped it), the guard runs its code from the snippets table
    (rescue), logs it (option fika_guard_log) and emails the admin address (at most once per 6 h, marked before
    sending). Hourly cron (fika_guard_hourly) compares every active snippet's saved code with what the plugin loads;
    differences logged + emailed. Status: GET /wp-json/fika/v1/guard (?scan=1 for a fresh scan).
  - Proven: a throwaway active snippet with `/* boo:end */` (blanked by the plugin, so it never ran) ran via the
    guard as soon as it was watched (option fika_guard_test, test ids never alert). Test snippets 222, 223 trashed
    (inactive), option removed; scan clean, log empty, no email sent.
- 28 Oct 2026: RT export of 28 Oct 22:19 (537 parcels) applied the same way (fika_rtd_apply, RT column + order note,
  no emails; temporary admin-only endpoint, snippet 224, trashed after). Live snippet 94 checked against git first;
  each order was re-checked against the dry run (status, RT step, tracking) before it was changed: all 53 unchanged.
  All matched by tracking number. 35 status changes: 32 -> completed (#1507, #1522, #1531, #1533-#1540,
  #1542-#1544, #1546-#1548, #1550-#1558, #1560, #1561, #1563, #1564, #1566, and #1529 from failed; completed time =
  export time), #1468, #1532, #1541 -> failed (RT: return received by merchant). RT step only on 18 (#1343, #1461,
  #1473 stay failed, Cancel -> return received; 8 pending #1569-#1575, #1577; pickup #1545, #1559, #1568; back to
  warehouse #1549, #1562, #1565; at warehouse #1567). Flagged to the owner: RT cash $0 on #1533 and #1543, $26.16 vs
  $23.64 on #1574, 2 returned parcels with no website order. Parcels not from the website ignored.

## Editing Processing orders (2026-10-29)
- Owner: customers ask to change their order. WooCommerce locks the products of Processing orders (every
  cash-on-delivery order); new snippet 225 "Fika: Processing orders editable" (admin only,
  wordpress/snippets/fika-order-edit.php) adds Processing to wc_order_is_editable. Completed / failed / cancelled /
  refunded stay locked. Editing sends no email. Stock is adjusted by WooCommerce; Fika Club grams follow the
  products. Orders already created in RT get a notice: RT keeps the old cash amount, change it in the RT dashboard.
- Checked without changing any order (temporary admin-only route, snippet 226, trashed): the saved code loads
  (2205 bytes), 3 Processing orders editable after (not before), a Completed order still locked. Shop pages and the
  guard (all watched snippets running, scan clean) fine.
- Owner (screenshot of #1574): products added in the order screen had no "Weight" label (it is only added at
  checkout). Snippet 225 now gives added products the checkout's label (fika_weight_label) on
  woocommerce_ajax_order_items_added, and refreshes it on woocommerce_saved_order_items (quantity changes) for
  editable orders; mystery-taste items are skipped. Dry run over all Processing orders (temporary route, snippet 227,
  trashed): only 3 lines differed, all added by edits (#1574 Bubblegum Skull, #1580 Sour Cherries and Sour
  Watermelon Pacifier); those labels were saved (labels only, no totals, no emails). #1574's total is now $26.16,
  the amount RT has.
