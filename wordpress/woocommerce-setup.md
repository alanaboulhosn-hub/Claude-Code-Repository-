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
